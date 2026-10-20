<?php
/**
 * Katalog: seriler ve alt kategoriler, kategori adresleri, magaza suzgeci.
 *
 * Seriler (WOODGarden...) panelde Ana Sayfa -> Seriler satirlaridir. Hangi
 * havuz kategorisinin hangi serinin altinda gorundugu veridir: Ürün Havuzu ->
 * Kategoriler'de "Sitelerde" kutusundan secilir (eklenti,
 * nwcs_site_category_tree). Bu sitede urunu olan kategori menude, magazada ve
 * kendi adresinde gorunur; tema kendi kategori listesini tasimaz.
 *
 * Adresler gercek sitedeki gibi: /urun-kategori/woodgarden/kamelyalar/
 * (canliya gecerken eski adresler aynen calisir). Sayfayi page-magaza.php cizer.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'wk_catalog_rewrite' );
function wk_catalog_rewrite(): void {
	add_rewrite_rule( '^urun-kategori/(?:[^/]+/)*([^/]+)/?$', 'index.php?pagename=magaza&wk_kat=$matches[1]', 'top' );
}

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		$vars[] = 'wk_kat';

		return $vars;
	}
);

/* Bilinmeyen kategori adresi 404 verir (bos magaza sayfasi degil). */
add_action( 'template_redirect', 'wk_catalog_404', 4 );
function wk_catalog_404(): void {
	$slug = (string) get_query_var( 'wk_kat' );

	if ( '' !== $slug && ! wk_category( sanitize_title( $slug ) ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}

// WordPress 404'te benzer adrese yonlendirmeyi dener (/magaza/); kategori adreslerinde dogru cevap 404.
add_filter(
	'redirect_canonical',
	static fn( $url ) => '' !== (string) get_query_var( 'wk_kat' ) ? false : $url
);

/**
 * Seri agaci: her seri ve altindaki kategoriler (urun sayisiyla). Yalnizca bu
 * sitede urunu olan kategoriler listelenir.
 *
 * @return array<int, array{slug:string, label:string, text:string, count:int, url:string, children:array<int, array{slug:string, label:string, count:int, url:string}>}>
 */
function wk_category_tree(): array {
	static $tree = null;

	if ( null !== $tree ) {
		return $tree;
	}

	$tree   = array();
	$placed = function_exists( 'nwcs_site_category_tree' ) ? nwcs_site_category_tree( get_current_blog_id() ) : array();

	foreach ( wk_lines() as $line ) {
		$children = array();

		foreach ( $placed[ $line['slug'] ]['children'] ?? array() as $slug => $child ) {
			if ( $child['count'] < 1 ) {
				continue;
			}

			// Panelde kategori sekmesinde verilen ad (yalnizca bu sitede); bossa havuzdaki ad.
			$page = wk_category_page_key( (string) $slug );
			$name = $page ? trim( (string) nwcs_field( $page, 'head', 'name' ) ) : '';

			$children[] = array(
				'slug'  => (string) $slug,
				'label' => '' !== $name ? $name : (string) $child['name'],
				'count' => (int) $child['count'],
				'url'   => home_url( '/urun-kategori/' . $line['slug'] . '/' . $slug . '/' ),
			);
		}

		$tree[] = array(
			'row'      => $line['row'],
			'slug'     => $line['slug'],
			'label'    => $line['label'],
			'text'     => $line['text'],
			// Seride ya da altindaki bir kategoride olan urunler.
			'count'    => (int) ( $placed[ $line['slug'] ]['count'] ?? $line['count'] ),
			'url'      => home_url( '/urun-kategori/' . $line['slug'] . '/' ),
			'children' => $children,
		);
	}

	return $tree;
}

/**
 * Kisaltmaya gore kategori (seri ya da alt kategori) ve bagli oldugu seri.
 *
 * @return array{slug:string, label:string, url:string, count:int, parent:?array}|null
 */
function wk_category( string $slug ): ?array {
	foreach ( wk_category_tree() as $line ) {
		if ( $line['slug'] === $slug ) {
			return $line + array( 'parent' => null );
		}

		foreach ( $line['children'] as $child ) {
			if ( $child['slug'] === $slug ) {
				return $child + array( 'parent' => $line );
			}
		}
	}

	return null;
}

function wk_category_url( string $slug ): string {
	$category = wk_category( $slug );

	return $category ? $category['url'] : home_url( '/magaza/' );
}

/**
 * Kategorinin panel sayfasi (manifestte 'kat-<kisaltma>'); yoksa bos.
 */
function wk_category_page_key( string $slug ): string {
	$key = 'kat-' . $slug;

	return function_exists( 'nwcs_manifest' ) && isset( nwcs_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Seri sayfasinin panel sayfasi (manifestte 'seri-<kisaltma>', gizli); yoksa bos.
 */
function wk_serie_page_key( string $slug ): string {
	$key = 'seri-' . $slug;

	return function_exists( 'nwcs_manifest' ) && isset( nwcs_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Siralama seceneginin panel alani (onizlemede tiklaninca acilir).
 */
function wk_sort_field( string $value ): string {
	$fields = array(
		''             => 'sort_default',
		'fiyat-artan'  => 'sort_price_asc',
		'fiyat-azalan' => 'sort_price_desc',
		'ad'           => 'sort_name',
	);

	return $fields[ $value ] ?? 'sort_default';
}

function wk_shop_url(): string {
	return home_url( '/magaza/' );
}

/**
 * Urunun serisi ve alt kategorisi (eklentinin yerlesiminden: agac sirasiyla
 * ilk eslesen alt kategori; yoksa urunun dogrudan bagli oldugu seri).
 *
 * @return array{line:?array, child:?array}
 */
function wk_product_place( array $product ): array {
	$place = function_exists( 'nwcs_product_place' ) ? nwcs_product_place( $product, get_current_blog_id() ) : array( 'parent' => null, 'child' => null );

	foreach ( wk_category_tree() as $line ) {
		if ( $line['slug'] !== $place['parent'] ) {
			continue;
		}

		foreach ( $line['children'] as $child ) {
			if ( $child['slug'] === $place['child'] ) {
				return array( 'line' => $line, 'child' => $child );
			}
		}

		return array( 'line' => $line, 'child' => null );
	}

	return array( 'line' => null, 'child' => null );
}

/**
 * Magaza suzgeci: kategori, arama ve siralama.
 *
 * Aramasiz listede renk grubu tek kart (wk_cards); aramada her renk ayri
 * gorunur: kodla arayan dogru urunu bulur.
 *
 * @return array<int, array<string, mixed>>
 */
function wk_filter_products( string $category = '', string $search = '', string $sort = '' ): array {
	$products = wk_products();

	if ( '' !== $category ) {
		// Seri secildiyse seride ya da altindaki bir kategoride olan urunler.
		$within = array( $category => true );

		foreach ( wk_category_tree() as $line ) {
			if ( $line['slug'] === $category ) {
				$within += array_fill_keys( wp_list_pluck( $line['children'], 'slug' ), true );
			}
		}

		$products = array_filter( $products, static fn( array $p ): bool => (bool) array_intersect_key( (array) $p['categories'], $within ) );
	}

	$search = trim( $search );

	if ( '' !== $search ) {
		// Turkce harf katlamasi eklentide ortak (nwcs_search_fold, Kocist aramasi
		// da kullanir): "ÇİVİ" = "çivi" = "civi". Eslestirme eskisi gibi tek parca.
		$fold     = static fn( string $text ): string => function_exists( 'nwcs_search_fold' ) ? nwcs_search_fold( $text ) : mb_strtolower( $text );
		$needle   = $fold( $search );
		$products = array_filter(
			$products,
			static fn( array $p ): bool => str_contains( $fold( $p['code'] . ' ' . $p['title'] . ' ' . implode( ' ', $p['categories'] ) ), $needle )
		);
	}

	$products = '' === $search ? wk_cards( $products ) : array_values( $products );

	if ( 'fiyat-artan' === $sort || 'fiyat-azalan' === $sort ) {
		// Fiyati olmayanlar her iki siralamada da sonda kalir.
		usort(
			$products,
			static function ( array $a, array $b ) use ( $sort ): int {
				$pa = wk_price_number( (string) $a['price'] );
				$pb = wk_price_number( (string) $b['price'] );

				if ( ! $pa || ! $pb ) {
					return $pa ? -1 : ( $pb ? 1 : 0 );
				}

				return 'fiyat-artan' === $sort ? $pa <=> $pb : $pb <=> $pa;
			}
		);
	} elseif ( 'ad' === $sort ) {
		// Turkce alfabe sirasi (C, Ç, ... I, İ); intl yoksa buyuk/kucuk harfe duyarsiz.
		$collator = class_exists( 'Collator' ) ? new Collator( 'tr_TR' ) : null;
		usort(
			$products,
			static fn( array $a, array $b ): int => $collator
				? (int) $collator->compare( $a['title'], $b['title'] )
				: strcasecmp( $a['title'], $b['title'] )
		);
	}

	return $products;
}

function wk_sort_options(): array {
	return array(
		''             => (string) nwcs_field( 'shop', 'list', 'sort_default' ),
		'fiyat-artan'  => (string) nwcs_field( 'shop', 'list', 'sort_price_asc' ),
		'fiyat-azalan' => (string) nwcs_field( 'shop', 'list', 'sort_price_desc' ),
		'ad'           => (string) nwcs_field( 'shop', 'list', 'sort_name' ),
	);
}

/**
 * Tutar yazimi: 10250 -> "10.250 ₺", 1299.9 -> "1.299,90 ₺".
 */
function wk_money( float $amount ): string {
	$decimals = abs( $amount - round( $amount ) ) > 0.004 ? 2 : 0;

	return number_format( $amount, $decimals, ',', '.' ) . ' ₺';
}

/* SEO: kategori sayfalari eklentiye bildirilir (baslik, aciklama, canonical, site haritasi). */
add_filter( 'nwcs_seo_extra_pages', 'wk_seo_category_pages' );
function wk_seo_category_pages( array $pages ): array {
	foreach ( wk_category_tree() as $line ) {
		$pages[] = array(
			'url'         => $line['url'],
			'name'        => $line['label'] . ' Serisi',
			'description' => trim( sprintf( '%s serisi: %s. %d ürün; fiyat, ölçü ve ahşap cinsi ürün sayfalarında.', $line['label'], implode( ', ', wp_list_pluck( $line['children'], 'label' ) ), $line['count'] ) ),
			'sitemap'     => true,
		);

		foreach ( $line['children'] as $child ) {
			$pages[] = array(
				'url'         => $child['url'],
				'name'        => sprintf( 'Ahşap %s', $child['label'] ),
				'description' => sprintf( 'Ahşap %s: %s serisinde %d model. Ölçü, ahşap cinsi ve KDV dahil fiyatlarıyla; sepete ekleyip üyeliksiz sipariş verin.', mb_strtolower( $child['label'] ), $line['label'], $child['count'] ),
				'sitemap'     => true,
			);
		}
	}

	return $pages;
}
