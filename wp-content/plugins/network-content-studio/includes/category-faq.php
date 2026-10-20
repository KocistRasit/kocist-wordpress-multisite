<?php
/**
 * Kategoriye ozel sik sorulan sorular.
 *
 * Panelde Ürün Havuzu -> Kategoriler -> kategori -> "Sık sorulan sorular"
 * bolumunden yazilir (includes/admin/category-faq.php). Kategorinin yerlestigi
 * her sitenin kendi listesi vardir: ayni soru iki sitede ayni cevapla
 * yazilmasin diye (panel benzer cevabi uyarir).
 *
 * Veri: havuz kategorisinin terim meta'si '_nwcs_cat_faq' =
 *   [ site_key => [ { q, a }, ... ] ]   site basina en fazla NWCS_CAT_FAQ_MAX satir
 *   q: tek satir soru; a: duz metin cevap (etiket yok, paragraf boslugu korunur).
 * Cevabi bos soru kayitta durur (firmadan cevap bekliyor) ama sitede ve arama
 * verisinde gorunmez; sitenin SSS sayfasiyla ayni kural (nwcs_faq_rows).
 *
 * Okuma: nwcs_cat_faq_map(), havuz basina onbellekli harita (kategori -> site
 * -> satirlar). Kategori onbellegiyle birlikte temizlenir
 * (nwcs_pool_categories_flush; nwcs_pool_flush_cache de onu cagirir).
 *
 * Sitede:
 *   - kategori sayfasi: urunlerin altinda "Sık sorulan sorular" akordeonu
 *     (id="sss"); FAQPage isareti yalnizca bu sayfada (nwcs_cat_faq_for_context,
 *     includes/seo.php sayfa dugumune ekler);
 *   - urun sayfasi: urunun bu sitedeki yerlesik kategorisinin ilk
 *     NWCS_CAT_FAQ_PRODUCT sorusu ve kategori sayfasina (#sss) baglanti; FAQ
 *     isareti yok;
 *   - llms.txt: kategori sayfalarinin sorulari (nwcs_cat_faq_llms_lines).
 * Kategori sayfasinin adresi eklentinin urettigi 'kat-<slug>' panel sayfasinin
 * adresidir (manifestteki 'catalog' => 'page' => 'path'): temalarin cizdigi
 * adresle ayni.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_CAT_FAQ_META      = '_nwcs_cat_faq';
const NWCS_CAT_FAQ_CACHE_KEY = 'nwcs_cat_faq_v1';
const NWCS_CAT_FAQ_MAX       = 10;
const NWCS_CAT_FAQ_Q_MAX     = 200;
const NWCS_CAT_FAQ_A_MAX     = 2000;
const NWCS_CAT_FAQ_PRODUCT   = 3;

/* ====================================================================== *
 * Temizlik
 * ====================================================================== */

/**
 * Soru: tek satir, etiketsiz, en fazla NWCS_CAT_FAQ_Q_MAX karakter.
 *
 * @param mixed $value
 */
function nwcs_cat_faq_question( $value ): string {
	return trim( mb_substr( nwcs_clean_text( $value ), 0, NWCS_CAT_FAQ_Q_MAX ) );
}

/**
 * Cevap: duz metin; satirlar korunur, ikiden fazla bos satir tek paragraf
 * boslugu olur. En fazla NWCS_CAT_FAQ_A_MAX karakter.
 *
 * @param mixed $value
 */
function nwcs_cat_faq_answer( $value ): string {
	$text = (string) preg_replace( "/\n{3,}/", "\n\n", nwcs_clean_text( $value, true ) );

	return trim( mb_substr( $text, 0, NWCS_CAT_FAQ_A_MAX ) );
}

/**
 * Bir sitenin satirlari (kayit ve okuma icin ayni kural): bos satir atilir,
 * en fazla NWCS_CAT_FAQ_MAX satir kalir.
 *
 * @param mixed $raw
 * @return array<int, array{q:string, a:string}>
 */
function nwcs_cat_faq_clean_rows( $raw ): array {
	$out = array();

	foreach ( is_array( $raw ) ? $raw : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$question = nwcs_cat_faq_question( $row['q'] ?? '' );
		$answer   = nwcs_cat_faq_answer( $row['a'] ?? '' );

		if ( '' === $question && '' === $answer ) {
			continue;
		}

		$out[] = array(
			'q' => $question,
			'a' => $answer,
		);
	}

	return array_slice( $out, 0, NWCS_CAT_FAQ_MAX );
}

/**
 * Butun sitelerin listesi: site_key => satirlar; bos liste atilir.
 *
 * @param mixed $raw
 * @return array<string, array<int, array{q:string, a:string}>>
 */
function nwcs_cat_faq_clean( $raw ): array {
	$out = array();

	foreach ( is_array( $raw ) ? $raw : array() as $site_key => $rows ) {
		$site_key = sanitize_key( (string) $site_key );
		$rows     = nwcs_cat_faq_clean_rows( $rows );

		if ( '' !== $site_key && $rows ) {
			$out[ $site_key ] = $rows;
		}
	}

	return $out;
}

/* ====================================================================== *
 * Veri
 * ====================================================================== */

/**
 * Havuzun soru haritasi: kategori slug => [ site_key => satirlar ]. Istek ici
 * degiskende ve havuz sitesinin transient'inda tutulur.
 *
 * @return array<string, array<string, array<int, array{q:string, a:string}>>>
 */
function nwcs_cat_faq_map( bool $reset = false ): array {
	static $cache = null;

	if ( $reset ) {
		$cache = null;

		return array();
	}

	if ( null !== $cache ) {
		return $cache;
	}

	// Taksonomi henuz kayitli degilse (init oncesi) bos doner ve saklanmaz.
	if ( ! taxonomy_exists( NWCS_PRODUCT_TAX ) ) {
		return array();
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$stored = get_transient( NWCS_CAT_FAQ_CACHE_KEY );

	if ( is_array( $stored ) ) {
		restore_current_blog();
		$cache = $stored;

		return $cache;
	}

	// Yalnizca sorusu olan kategoriler (meta onbellegi sorguyla birlikte dolar).
	$terms = get_terms(
		array(
			'taxonomy'   => NWCS_PRODUCT_TAX,
			'hide_empty' => false,
			'meta_key'   => NWCS_CAT_FAQ_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	$map = array();

	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$faq = nwcs_cat_faq_clean( get_term_meta( (int) $term->term_id, NWCS_CAT_FAQ_META, true ) );

		if ( $faq ) {
			$map[ (string) $term->slug ] = $faq;
		}
	}

	if ( ! is_wp_error( $terms ) ) {
		set_transient( NWCS_CAT_FAQ_CACHE_KEY, $map, HOUR_IN_SECONDS );
	}

	restore_current_blog();

	$cache = $map;

	return $map;
}

/**
 * Soru haritasini temizler.
 */
function nwcs_cat_faq_flush(): void {
	switch_to_blog( nwcs_pool_blog_id() );
	delete_transient( NWCS_CAT_FAQ_CACHE_KEY );
	restore_current_blog();

	nwcs_cat_faq_map( true );
}

/**
 * Kategorinin butun sitelerdeki sorulari (site_key => satirlar).
 *
 * @return array<string, array<int, array{q:string, a:string}>>
 */
function nwcs_cat_faq_all( string $slug ): array {
	return nwcs_cat_faq_map()[ $slug ] ?? array();
}

/**
 * Kategorinin bir sitedeki satirlari (cevabi bos olanlar dahil, kayitli sirayla).
 *
 * @return array<int, array{q:string, a:string}>
 */
function nwcs_cat_faq_rows( string $slug, string $site_key ): array {
	return nwcs_cat_faq_all( $slug )[ sanitize_key( $site_key ) ] ?? array();
}

/**
 * Kategorinin bir sitedeki listesini yazar (bos liste o siteyi siler; hic
 * site kalmazsa meta silinir) ve havuz onbellegini temizler (site onbellegi
 * istek sonunda: cache-purge.php).
 *
 * @return bool Kategori var mi (yazildi mi)?
 */
function nwcs_cat_faq_write( string $slug, string $site_key, array $rows ): bool {
	$term_id  = (int) ( nwcs_pool_categories()[ $slug ]['id'] ?? 0 );
	$site_key = sanitize_key( $site_key );

	if ( ! $term_id || '' === $site_key ) {
		return false;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	// Haritadan degil, meta'nin kendisinden: baska sitenin listesi kaybolmasin.
	$all              = nwcs_cat_faq_clean( get_term_meta( $term_id, NWCS_CAT_FAQ_META, true ) );
	$all[ $site_key ] = nwcs_cat_faq_clean_rows( $rows );
	$all              = nwcs_cat_faq_clean( $all );

	if ( $all ) {
		update_term_meta( $term_id, NWCS_CAT_FAQ_META, $all );
	} else {
		delete_term_meta( $term_id, NWCS_CAT_FAQ_META );
	}

	restore_current_blog();

	nwcs_pool_flush_cache();

	return true;
}

/* ====================================================================== *
 * Sitede
 * ====================================================================== */

/**
 * Kategorinin bu sitede gorunen sorulari: kategori bu siteye yerlesik,
 * sorusu ve cevabi dolu satirlar, panel sirasiyla. Bicim nwcs_seo_faq ile
 * ayni (question, answer); $limit 0 ise hepsi.
 *
 * @return array<int, array{question:string, answer:string}>
 */
function nwcs_cat_faq_public( string $slug, ?int $blog_id = null, int $limit = 0 ): array {
	$blog_id  = $blog_id ?? get_current_blog_id();
	$site_key = nwcs_catalog_site_key( $blog_id );

	if ( '' === $site_key || '' === ( nwcs_pool_categories()[ $slug ]['placement'][ $site_key ] ?? '' ) ) {
		return array();
	}

	$items = array();

	foreach ( nwcs_cat_faq_rows( $slug, $site_key ) as $row ) {
		if ( '' !== $row['q'] && '' !== $row['a'] ) {
			$items[] = array(
				'question' => $row['q'],
				'answer'   => $row['a'],
			);
		}
	}

	return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
}

/**
 * Kategorinin bu sitedeki sayfasinin adresi ('kat-<slug>' panel sayfasinin
 * adresi); kategori sitede yerlesik degilse bos.
 */
function nwcs_cat_faq_page_url( string $slug, ?int $blog_id = null ): string {
	$blog_id = $blog_id ?? get_current_blog_id();
	$path    = (string) ( nwcs_manifest_for_blog( $blog_id )['pages'][ 'kat-' . $slug ]['path'] ?? '' );

	return '' !== $path ? get_home_url( $blog_id, $path ) : '';
}

/**
 * Adresi verilen sayfa bu sitenin bir kategori sayfasi mi; oyleyse kategorinin
 * slug'i, degilse bos. Kategori sayfalari temanin ek sayfasi (Kocist) ya da
 * manifest sayfasi (WOOD KOCIST) olabilir; ikisi de 'kat-<slug>' adresindedir.
 */
function nwcs_cat_faq_slug_for_url( string $url ): string {
	$path_of = static fn( string $address ): string => untrailingslashit( (string) wp_parse_url( $address, PHP_URL_PATH ) );
	$want    = $path_of( $url );

	if ( '' === $want ) {
		return '';
	}

	foreach ( (array) ( nwcs_manifest()['pages'] ?? array() ) as $key => $page ) {
		$key = (string) $key;

		if ( str_starts_with( $key, 'kat-' ) && isset( $page['path'] ) && $path_of( home_url( (string) $page['path'] ) ) === $want ) {
			return substr( $key, 4 );
		}
	}

	return '';
}

/**
 * Sayfanin kategori sorulari (JSON-LD FAQPage icin; includes/seo.php). Yalnizca
 * kategori sayfasinda dolu; urun sayfasi ve diger sayfalarda bos.
 *
 * @return array<int, array{question:string, answer:string}>
 */
function nwcs_cat_faq_for_context( array $context ): array {
	if ( 'page' !== ( $context['kind'] ?? '' ) || 'Product' === ( $context['schema'] ?? '' ) || is_paged() ) {
		return array();
	}

	$slug = nwcs_cat_faq_slug_for_url( (string) ( $context['url'] ?? '' ) );

	return '' !== $slug ? nwcs_cat_faq_public( $slug ) : array();
}

/**
 * Onizlemede: sorular bu sitenin panelinde degil, Ürün Havuzu -> Kategoriler'de
 * duzenlenir; tiklaninca orasi yeni sekmede acilir.
 */
function nwcs_cat_faq_attr( string $slug ): void {
	if ( ! function_exists( 'nwcs_is_preview' ) || ! nwcs_is_preview() || ! function_exists( 'nwcs_pool_categories_url' ) ) {
		return;
	}

	$url = add_query_arg( 'sss_site', nwcs_catalog_site_key( get_current_blog_id() ), nwcs_pool_categories_url( array( 'kategori' => $slug ) ) ) . '#nwcs-sss';

	nwcs_source_attr( 'admin', $url, 'Kategori soruları (Ürün Havuzu › Kategoriler)' );
}

/**
 * llms.txt: bu sitenin kategori sayfalarinin sorulari, menu sirasiyla. Bos
 * kategoriyi gizleyen sitede (WOOD KOCIST) urunu olmayan kategori atlanir.
 *
 * @return string[] Markdown satirlari (baslik dahil); soru yoksa bos.
 */
function nwcs_cat_faq_llms_lines(): array {
	$blog_id = get_current_blog_id();
	$site    = nwcs_catalog_sites()[ $blog_id ] ?? null;

	if ( ! $site || ! nwcs_cat_faq_map() ) {
		return array();
	}

	$hide  = ! empty( $site['catalog']['hide_empty'] );
	$names = nwcs_pool_categories();
	$lines = array();

	foreach ( nwcs_site_category_tree( $blog_id ) as $parent ) {
		foreach ( $parent['children'] as $slug => $child ) {
			$items = nwcs_cat_faq_public( (string) $slug, $blog_id );
			$url   = nwcs_cat_faq_page_url( (string) $slug, $blog_id );

			if ( ! $items || '' === $url || ( $hide && (int) $child['count'] < 1 ) ) {
				continue;
			}

			$name = nwcs_seo_clean( nwcs_field( 'kat-' . $slug, 'head', 'name' ) );
			$name = '' !== $name ? $name : (string) ( $names[ $slug ]['name'] ?? $slug );

			$lines[] = '';
			$lines[] = sprintf( '### [%s](%s)', $name, $url . '#sss' );
			$lines[] = '';

			foreach ( $items as $item ) {
				$lines[] = '- ' . nwcs_seo_clean( $item['question'] ) . ' ' . nwcs_seo_clean( $item['answer'] );
			}
		}
	}

	if ( ! $lines ) {
		return array();
	}

	return array_merge( array( '', '## Kategorilere özel sık sorulan sorular' ), $lines );
}
