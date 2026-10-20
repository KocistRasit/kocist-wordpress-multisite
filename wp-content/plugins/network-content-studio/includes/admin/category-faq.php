<?php
/**
 * Ürün Havuzu -> Kategoriler -> kategori -> "Sık sorulan sorular".
 *
 * Kategorinin yerlestigi her site icin bir sekme; her sekme kendi formu ve
 * kendi "Kaydet" dugmesi. Yerlesmedigi site icin sekme yok, nedeni bir satirla
 * yazilir. Bolum "Renk grupları"ndan sonra, Ürünler tablosundan once gelir
 * (nwcs_render_category_detail). Veri ve sitedeki kullanimi:
 * includes/category-faq.php.
 *
 *   - satir: soru (tek satir) ve cevap (duz metin, paragraf boslugu korunur);
 *     ↑ ↓ ve "Sil"; site basina en fazla NWCS_CAT_FAQ_MAX satir. Listenin
 *     sonunda her zaman bir bos "Yeni soru" satiri durur (JS gerekmez);
 *     "Soru ekle" (JS) yenisini ekler.
 *   - cevabi bos soru kaydedilir ama sitede gorunmez (satirda yazar);
 *     cevabi olup sorusu bos satir kaydedilmez, yazilanlar geri gelir
 *     (NWCS_CAT_FAQ_DRAFT).
 *   - kopya uyarisi: ayni kategorinin baska bir sitesinde ayni ya da cok
 *     benzer cevap varsa kayit yapilir, uyari soruyu ve siteyi soyler.
 *     Benzerlik SEO ve GEO tutarlilik kontroluyle ayni (nwcs_seo_near_text).
 *
 * JavaScript gerekmez: sekmeler baglanti (?sss_site=), her islem bir form
 * (admin-post.php, nonce + yetki); ↑ ↓ ve "Sil" JS'siz formu o haliyle
 * kaydeder. JS (assets/category-faq.js) sekmeleri yerinde degistirir, satiri
 * yerinde tasir ve siler ("Kaydet" ile yazilir), "Soru ekle" ile satir ekler.
 *
 * Her kayit "Son işlemler"e 'sss' kaydi birakir: o sitenin onceki ve sonraki
 * listesi; geri alma oncekini yazar. Ayni kullanicinin ayni kategori ve
 * sitedeki art arda kayitlari son kayittan NWCS_CAT_FAQ_MERGE icinde tek
 * kayitta birlesir (renk gruplariyla ayni kural). Kayittan sonra havuz
 * onbellegi ve sitelerin sayfa onbellegi temizlenir (nwcs_cat_faq_write).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_CAT_FAQ_KIND  = 'sss';
const NWCS_CAT_FAQ_DRAFT = 'nwcs_cat_faq_draft_';
// Ayni kategori ve sitede art arda yapilan kayitlar bu sure icinde tek "Son işlemler" kaydinda birlesir.
const NWCS_CAT_FAQ_MERGE = 30 * MINUTE_IN_SECONDS;
// Bundan kisa cevap ("Evet.") kopya uyarisi vermez: arama motoru icin kopya sayilmaz.
const NWCS_CAT_FAQ_COPY_MIN = 30;

add_action( 'admin_enqueue_scripts', 'nwcs_cat_faq_admin_assets' );
function nwcs_cat_faq_admin_assets( string $hook ): void {
	if ( ! str_contains( $hook, NWCS_CATEGORIES_SLUG ) ) {
		return;
	}

	// Surum + dosya zamani: dosya degisince tarayici eski kopyayi tutmasin.
	$ver = static fn( string $file ): string => NWCS_VERSION . '.' . (int) filemtime( NWCS_DIR . $file );

	wp_enqueue_style( 'nwcs-category-faq', NWCS_URL . 'assets/category-faq.css', array( 'nwcs-admin' ), $ver( 'assets/category-faq.css' ) );
	wp_enqueue_script( 'nwcs-category-faq', NWCS_URL . 'assets/category-faq.js', array(), $ver( 'assets/category-faq.js' ), true );
}

/**
 * Bolumun adresi (Kategoriler -> kategori, istenirse bir sitenin sekmesi).
 */
function nwcs_cat_faq_url( string $slug, string $site_key = '', array $args = array() ): string {
	$args = array_merge( array( 'kategori' => $slug ), '' !== $site_key ? array( 'sss_site' => $site_key ) : array(), $args );

	return nwcs_pool_categories_url( $args ) . '#nwcs-sss';
}

/**
 * Kategorinin sitede gorundugu siteler (sekmeler): blog_id => site. Yerlesimi
 * olan ve bagli oldugu baslik sitede hala duran siteler.
 *
 * @return array<int, array{blog_id:int, site_key:string, label:string, catalog:array}>
 */
function nwcs_cat_faq_sites( string $slug ): array {
	$placement = nwcs_pool_categories()[ $slug ]['placement'] ?? array();
	$out       = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$parent = $placement[ $site['site_key'] ] ?? '';

		if ( '' !== $parent && isset( nwcs_site_parents( (int) $blog_id )[ $parent ] ) ) {
			$out[ (int) $blog_id ] = $site;
		}
	}

	return $out;
}

/**
 * Sekmesi olmayan sitelerin nedeni, site basina bir satir.
 *
 * @return string[]
 */
function nwcs_cat_faq_unplaced_lines( string $slug ): array {
	$placement = nwcs_pool_categories()[ $slug ]['placement'] ?? array();
	$placed    = nwcs_cat_faq_sites( $slug );
	$lines     = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		if ( isset( $placed[ $blog_id ] ) ) {
			continue;
		}

		$where = nwcs_locative( $site['label'] );

		if ( nwcs_is_parent_category( $slug, (int) $blog_id ) ) {
			$line = sprintf( '%s sekme yok: bu kategori bir üst başlığın kendi kategorisi. Sorular yalnızca alt kategorilerde gösterilir.', $where );
		} elseif ( '' !== ( $placement[ $site['site_key'] ] ?? '' ) ) {
			$line = sprintf( '%s sekme yok: kategorinin bağlı olduğu başlık o sitede artık yok. Yukarıdaki “Sitelerde” bölümünden yeni bir başlık seçin.', $where );
		} else {
			$line = sprintf( '%s sekme yok: kategori o sitede gösterilmiyor. Sorular, kategori “Sitelerde” bölümünden o siteye yerleştirilince yazılır.', $where );
		}

		$kept = count( nwcs_cat_faq_rows( $slug, $site['site_key'] ) );

		if ( $kept ) {
			$line .= sprintf( ' O site için kayıtlı %d soru duruyor; kategori yeniden yerleşince görünür.', $kept );
		}

		$lines[] = $line;
	}

	return $lines;
}

/**
 * Kategori basligindaki kisa bilgi: "SSS: 4 soru" ya da birden cok sitede
 * "SSS: Koçist 4, WOOD KOCIST 3 soru". Soru yoksa bos.
 */
function nwcs_cat_faq_header_fact( string $slug ): string {
	$counts = array();

	foreach ( nwcs_cat_faq_sites( $slug ) as $site ) {
		$count = count( nwcs_cat_faq_rows( $slug, $site['site_key'] ) );

		if ( $count ) {
			$counts[ $site['label'] ] = $count;
		}
	}

	if ( ! $counts ) {
		return '';
	}

	if ( 1 === count( $counts ) ) {
		return sprintf( 'SSS: %d soru', current( $counts ) );
	}

	$parts = array();

	foreach ( $counts as $label => $count ) {
		$parts[] = $label . ' ' . $count;
	}

	return 'SSS: ' . implode( ', ', $parts ) . ' soru';
}

/**
 * Baska sitelerdeki ayni ya da cok benzer cevaplar: satir sirasi => ilk
 * benzer (site adi, o sitedeki soru, ayni mi). Yalnizca kategorinin gorundugu
 * siteler karsilastirilir; kisa cevaplar (NWCS_CAT_FAQ_COPY_MIN) sayilmaz.
 *
 * @param array<int, array{q:string, a:string}> $rows
 * @return array<int, array{site:string, question:string, same:bool}>
 */
function nwcs_cat_faq_duplicates( string $slug, string $site_key, array $rows ): array {
	$others = array();

	foreach ( nwcs_cat_faq_sites( $slug ) as $site ) {
		if ( $site['site_key'] === $site_key ) {
			continue;
		}

		foreach ( nwcs_cat_faq_rows( $slug, $site['site_key'] ) as $row ) {
			if ( mb_strlen( nwcs_seo_near_normal( $row['a'] ) ) >= NWCS_CAT_FAQ_COPY_MIN ) {
				$others[] = array( 'site' => $site['label'], 'question' => $row['q'], 'answer' => $row['a'] );
			}
		}
	}

	$out = array();

	if ( ! $others ) {
		return $out;
	}

	foreach ( array_values( $rows ) as $index => $row ) {
		$answer = (string) ( $row['a'] ?? '' );

		if ( mb_strlen( nwcs_seo_near_normal( $answer ) ) < NWCS_CAT_FAQ_COPY_MIN ) {
			continue;
		}

		foreach ( $others as $other ) {
			if ( nwcs_seo_near_text( $answer, $other['answer'] ) ) {
				$out[ $index ] = array(
					'site'     => $other['site'],
					'question' => $other['question'],
					'same'     => nwcs_seo_near_normal( $answer ) === nwcs_seo_near_normal( $other['answer'] ),
				);
				break;
			}
		}
	}

	return $out;
}

/**
 * Iki liste arasindaki degisiklikler, "Son işlemler" satiri icin:
 * "Koçist’te “Soru” eklendi", "1 cevap değişti", "sıra değişti".
 * Soru metni degisen satir silinmis ve eklenmis sayilir.
 *
 * @return string[]
 */
function nwcs_cat_faq_diff_facts( string $label, array $old, array $new ): array {
	$key     = static fn( array $row ): string => nwcs_search_fold( (string) $row['q'] );
	$old_q   = array_map( $key, $old );
	$new_q   = array_map( $key, $new );
	$added   = array_keys( array_diff( $new_q, $old_q ) );
	$removed = array_keys( array_diff( $old_q, $new_q ) );
	$facts   = array();
	$name    = static fn( array $row ): string => '“' . ( '' !== $row['q'] ? mb_strimwidth( $row['q'], 0, 60, '…' ) : 'sorusuz satır' ) . '”';

	if ( $added ) {
		$facts[] = 1 === count( $added ) ? sprintf( '%s eklendi', $name( $new[ $added[0] ] ) ) : sprintf( '%d soru eklendi', count( $added ) );
	}

	if ( $removed ) {
		$facts[] = 1 === count( $removed ) ? sprintf( '%s silindi', $name( $old[ $removed[0] ] ) ) : sprintf( '%d soru silindi', count( $removed ) );
	}

	$was     = array_combine( $old_q, array_column( $old, 'a' ) ) ?: array();
	$changed = 0;

	foreach ( $new as $index => $row ) {
		if ( isset( $was[ $new_q[ $index ] ] ) && $was[ $new_q[ $index ] ] !== $row['a'] ) {
			++$changed;
		}
	}

	if ( $changed ) {
		$facts[] = sprintf( '%d cevap değişti', $changed );
	}

	if ( array_values( array_intersect( $old_q, $new_q ) ) !== array_values( array_intersect( $new_q, $old_q ) ) ) {
		$facts[] = 'sıra değişti';
	}

	if ( $facts ) {
		$facts[0] = nwcs_locative( $label ) . ' ' . $facts[0];
	}

	return $facts;
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

/**
 * Kategorinin "Sık sorulan sorular" bolumu.
 */
function nwcs_render_cat_faq( string $slug ): void {
	$sites  = nwcs_cat_faq_sites( $slug );
	$keys   = array_column( $sites, 'site_key' );
	$others = nwcs_cat_faq_unplaced_lines( $slug );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum secimi ve bildirim.
	$result = isset( $_GET['sss'] ) ? sanitize_key( wp_unslash( $_GET['sss'] ) ) : '';
	$chosen = isset( $_GET['sss_site'] ) ? sanitize_key( wp_unslash( $_GET['sss_site'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Kaydedilmeyen form: yazilanlar bir kez geri gelir.
	$draft = get_site_transient( NWCS_CAT_FAQ_DRAFT . get_current_user_id() );
	$draft = is_array( $draft ) && ( $draft['slug'] ?? '' ) === $slug && in_array( (string) ( $draft['site'] ?? '' ), $keys, true ) ? $draft : array();

	if ( $draft ) {
		delete_site_transient( NWCS_CAT_FAQ_DRAFT . get_current_user_id() );
		$chosen = (string) $draft['site'];
	}

	$active = in_array( $chosen, $keys, true ) ? $chosen : (string) ( $keys[0] ?? '' );
	?>
	<section class="nwcs-cat__section nwcs-faqs" aria-labelledby="nwcs-sec-faq" id="nwcs-sss" data-nwcs-faq>
		<h3 class="nwcs-section__title" id="nwcs-sec-faq">Sık sorulan sorular</h3>
		<p class="nwcs-hint nwcs-faqs__lead">
			Bu kategoriye özgü <strong>3–6 soru</strong> yazın: ölçü, malzeme, kullanım gibi. Teslimat, montaj ve ödeme gibi genel sorular sitenin SSS sayfasında kalsın.
			Sorular kategori sayfasında ürünlerin altında görünür, ürün sayfalarında ilk <?php echo (int) NWCS_CAT_FAQ_PRODUCT; ?>’ü. Her sitenin kendi listesi var; iki sitede aynı cevabı yazmayın.
		</p>

		<?php foreach ( $others as $line ) : ?>
			<p class="nwcs-hint nwcs-faqs__nosite"><?php echo esc_html( $line ); ?></p>
		<?php endforeach; ?>

		<?php if ( 'yer' === $result ) : ?>
			<div class="notice notice-error inline nwcs-faqs__notice" role="alert"><p>Kaydedilmedi: kategori o sitede artık gösterilmiyor (başka bir sekmede yerleşim değişmiş olabilir). Sayfayı yenileyip yeniden deneyin.</p></div>
		<?php endif; ?>

		<?php if ( ! $sites ) : ?>
			<p class="nwcs-faqs__empty">Kategori hiçbir sitede gösterilmiyor. Önce yukarıdaki “Sitelerde” bölümünden bir site seçin; sonra o sitenin soruları burada yazılır.</p>
		<?php else : ?>
			<div class="nwcs-faqs__tabs" data-nwcs-faq-tabs>
				<?php foreach ( $sites as $site ) : ?>
					<?php $count = count( nwcs_cat_faq_rows( $slug, $site['site_key'] ) ); ?>
					<a class="nwcs-faqs__tab<?php echo $site['site_key'] === $active ? ' is-active' : ''; ?>" id="nwcs-sss-tab-<?php echo esc_attr( $site['site_key'] ); ?>"
						href="<?php echo esc_url( nwcs_cat_faq_url( $slug, $site['site_key'] ) ); ?>"
						data-nwcs-faq-tab="<?php echo esc_attr( $site['site_key'] ); ?>"
						<?php echo $site['site_key'] === $active ? 'aria-current="true"' : ''; ?>>
						<?php echo esc_html( $site['label'] ); ?>
						<span class="nwcs-faqs__badge"><?php echo esc_html( sprintf( '%d soru', $count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $sites as $blog_id => $site ) : ?>
				<?php
				$is_active = $site['site_key'] === $active;
				nwcs_render_cat_faq_panel(
					$slug,
					(int) $blog_id,
					$site,
					$is_active,
					$is_active ? $result : '',
					( $draft['site'] ?? '' ) === $site['site_key'] ? $draft : array()
				);
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Islem sonucu (sekmenin icinde; WordPress bildirimi ust seride tasimasin diye 'inline').
 */
function nwcs_render_cat_faq_notice( string $result, string $label, array $draft ): void {
	$numbers = implode( ', ', array_map( static fn( int $index ): string => ( $index + 1 ) . '.', (array) ( $draft['bad'] ?? array() ) ) );

	$map = array(
		'kaydedildi' => array( 'success', sprintf( 'Sorular kaydedildi. %s hemen görünür; yanlışsa “Son işlemler”den geri alabilirsiniz.', $label ) ),
		'silindi'    => array( 'success', 'Soru silindi. Diğer sorular kaydedildi.' ),
		'ayni'       => array( 'info', 'Sorular değişmedi.' ),
		'sorusuz'    => array( 'error', sprintf( 'Kaydedilmedi: %s satırda cevap var ama soru boş. Soruyu yazın ya da satırı silin. Yazdıklarınız aşağıda duruyor; hiçbir şey değişmedi.', $numbers ) ),
		'fazla'      => array( 'error', sprintf( 'Kaydedilmedi: bir sitede en fazla %d soru olabilir, formda %d soru var. Fazla satırları silip yeniden kaydedin; yazdıklarınız aşağıda duruyor.', NWCS_CAT_FAQ_MAX, count( (array) ( $draft['rows'] ?? array() ) ) ) ),
	);

	if ( isset( $map[ $result ] ) ) {
		printf(
			'<div class="notice notice-%1$s inline nwcs-faqs__notice" role="%3$s"><p>%2$s</p></div>',
			esc_attr( $map[ $result ][0] ),
			esc_html( $map[ $result ][1] ),
			'error' === $map[ $result ][0] ? 'alert' : 'status'
		);
	}
}

/**
 * Bir sitenin sekmesi (tek form).
 *
 * $draft: kaydedilmeyen form (sorusuz satir ya da fazla satir); alanlar
 * kullanicinin yazdiklariyla dolar, hatali satirlar isaretlenir.
 */
function nwcs_render_cat_faq_panel( string $slug, int $blog_id, array $site, bool $active, string $result, array $draft ): void {
	$key     = $site['site_key'];
	$saved   = nwcs_cat_faq_rows( $slug, $key );
	$rows    = $draft ? array_values( (array) $draft['rows'] ) : $saved;
	$bad     = array_map( 'intval', (array) ( $draft['bad'] ?? array() ) );
	$copies  = nwcs_cat_faq_duplicates( $slug, $key, $rows );
	$page    = nwcs_cat_faq_page_url( $slug, $blog_id );
	$hidden  = ! empty( $site['catalog']['hide_empty'] ) && 0 === (int) ( nwcs_site_category_tree( $blog_id )[ nwcs_pool_categories()[ $slug ]['placement'][ $key ] ?? '' ]['children'][ $slug ]['count'] ?? 0 );
	$form_id = 'nwcs-sss-form-' . $key;
	$label   = $site['label'];
	?>
	<div class="nwcs-faqs__panel" id="nwcs-sss-<?php echo esc_attr( $key ); ?>" data-nwcs-faq-panel="<?php echo esc_attr( $key ); ?>"<?php echo $active ? '' : ' hidden'; ?>>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-faqs__form" id="<?php echo esc_attr( $form_id ); ?>" data-nwcs-faq-form
			data-nwcs-faq-max="<?php echo (int) NWCS_CAT_FAQ_MAX; ?>" aria-label="<?php echo esc_attr( $label . ' soruları' ); ?>">
			<input type="hidden" name="action" value="nwcs_cat_faq_save" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<input type="hidden" name="site" value="<?php echo esc_attr( $key ); ?>" />
			<?php wp_nonce_field( 'nwcs_cat_faq_save_' . $slug ); ?>
			<?php // Enter tusu bu dugmeyi kullanir (formdaki ilk gonder dugmesi ↑ olmasin). ?>
			<button type="submit" name="kaydet" value="1" class="nwcs-faqs__default" tabindex="-1" aria-hidden="true">Kaydet</button>

			<?php nwcs_render_cat_faq_notice( $result, $label, $draft ); ?>

			<?php if ( $copies ) : ?>
				<div class="notice notice-warning inline nwcs-faqs__notice" role="status">
					<p>
						<?php echo esc_html( in_array( $result, array( 'kaydedildi', 'silindi' ), true ) ? 'Kayıt yapıldı, ama bu cevaplar başka bir sitede de var:' : 'Dikkat, bu cevaplar başka bir sitede de var:' ); ?>
					</p>
					<ul>
						<?php foreach ( $copies as $index => $copy ) : ?>
							<li><?php echo esc_html( sprintf( '“%s”: cevabı %s “%s” sorusunun cevabıyla %s.', $rows[ $index ]['q'], nwcs_locative( $copy['site'] ), $copy['question'], $copy['same'] ? 'aynı' : 'çok benzer' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p>Arama motorları iki sitede aynı metni kopya içerik sayar. Cevabı bu siteye göre yeniden yazın.</p>
				</div>
			<?php endif; ?>

			<?php if ( $hidden ) : ?>
				<p class="nwcs-photos__warn"><?php echo esc_html( sprintf( 'Kategori %s henüz görünmüyor: bu sitede ürünü olunca görünür. Sorular da o zaman çıkar.', nwcs_locative( $label ) ) ); ?></p>
			<?php endif; ?>

			<ol class="nwcs-faqs__list" data-nwcs-faq-rows>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php nwcs_render_cat_faq_row( $key, (string) $index, (int) $index, count( $rows ), $row, in_array( (int) $index, $bad, true ), $copies[ $index ] ?? null ); ?>
				<?php endforeach; ?>
				<?php if ( count( $rows ) < NWCS_CAT_FAQ_MAX ) : ?>
					<?php nwcs_render_cat_faq_row( $key, 'yeni', count( $rows ), count( $rows ), array( 'q' => '', 'a' => '' ), false, null ); ?>
				<?php endif; ?>
			</ol>

			<template data-nwcs-faq-template>
				<?php nwcs_render_cat_faq_row( $key, '__KEY__', 0, 0, array( 'q' => '', 'a' => '' ), false, null, true ); ?>
			</template>

			<p class="nwcs-faqs__status" data-nwcs-faq-status aria-live="polite"></p>
			<p class="nwcs-faqs__err" data-nwcs-faq-error role="alert" hidden></p>

			<div class="nwcs-actions nwcs-faqs__actions">
				<button type="button" class="button" data-nwcs-faq-add hidden>+ Soru ekle</button>
				<button type="submit" name="kaydet" value="1" class="button button-primary">Soruları kaydet</button>
				<span class="nwcs-hint nwcs-faqs__count" data-nwcs-faq-count><?php echo esc_html( sprintf( '%d / %d soru', count( array_filter( $rows, static fn( array $row ): bool => '' !== $row['q'] ) ), NWCS_CAT_FAQ_MAX ) ); ?></span>
				<?php if ( '' !== $page && ! $hidden ) : ?>
					<a class="nwcs-faqs__view" href="<?php echo esc_url( $page . '#sss' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( nwcs_locative( $label ) ); ?> kategori sayfasında gör<span class="screen-reader-text"> (yeni sekmede açılır)</span> ↗</a>
				<?php endif; ?>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Tek soru satiri. $key: formdaki satir anahtari (kayitli satirda sira,
 * bos satirda 'yeni', JS sablonunda '__KEY__').
 *
 * Kayitli satirda ↑ ↓ ve "Sil" formu gonderir (JS varsa satiri yerinde tasir
 * ve kaldirir). Formun sonundaki bos "Yeni soru" satirinda dugme yok. JS
 * sablonundaki (eklenen) satirda dugmeler yalnizca JS ile calisir.
 *
 * @param array{site:string, question:string, same:bool}|null $copy
 */
function nwcs_render_cat_faq_row( string $site_key, string $key, int $position, int $total, array $row, bool $invalid, ?array $copy, bool $template = false ): void {
	$blank  = 'yeni' === $key && ! $template;
	$type   = $template ? 'button' : 'submit';
	$field  = 'nwcs-sss-' . $site_key . '-' . $key;
	$number = $position + 1;
	$label  = $blank ? 'Yeni soru' : sprintf( '%d. soru', $number );
	?>
	<li class="nwcs-faqs__row<?php echo $blank ? ' is-new' : ''; ?><?php echo $invalid ? ' is-invalid' : ''; ?>" data-nwcs-faq-row<?php echo $blank ? ' data-nwcs-faq-new' : ''; ?>>
		<div class="nwcs-faqs__side">
			<span class="nwcs-faqs__n" data-nwcs-faq-n aria-hidden="true"><?php echo $blank ? '+' : (int) $number; ?></span>
			<?php if ( ! $blank ) : ?>
				<button type="<?php echo esc_attr( $type ); ?>" name="tasi" value="<?php echo esc_attr( $key . ':up' ); ?>" class="nwcs-move" data-nwcs-faq-move="up" aria-label="<?php echo esc_attr( 'Yukarı taşı: ' . $label ); ?>"<?php disabled( 0 === $position ); ?>>↑</button>
				<button type="<?php echo esc_attr( $type ); ?>" name="tasi" value="<?php echo esc_attr( $key . ':down' ); ?>" class="nwcs-move" data-nwcs-faq-move="down" aria-label="<?php echo esc_attr( 'Aşağı taşı: ' . $label ); ?>"<?php disabled( $total - 1 === $position ); ?>>↓</button>
			<?php endif; ?>
		</div>
		<div class="nwcs-faqs__fields">
			<?php // Numara solda gorunur; ekran okuyucu icin etikette de ("2. soru"). ?>
			<label class="nwcs-sublabel nwcs-faqs__label" for="<?php echo esc_attr( $field ); ?>-q"><?php if ( $blank ) : ?>Yeni soru<?php else : ?><span class="screen-reader-text" data-nwcs-faq-label><?php echo esc_html( $number . '. ' ); ?></span>Soru<?php endif; ?></label>
			<input class="nwcs-input" type="text" id="<?php echo esc_attr( $field ); ?>-q" name="<?php echo esc_attr( 'sss[' . $key . '][q]' ); ?>" value="<?php echo esc_attr( $row['q'] ); ?>"
				maxlength="<?php echo (int) NWCS_CAT_FAQ_Q_MAX; ?>" placeholder="örn. Kamelya kaç kişiliktir?" data-nwcs-faq-q
				<?php echo $invalid ? 'aria-invalid="true" aria-describedby="' . esc_attr( $field ) . '-hata"' : ''; ?> />
			<?php if ( $invalid ) : ?>
				<span class="nwcs-faqs__err" id="<?php echo esc_attr( $field ); ?>-hata">Soruyu yazın ya da satırı silin.</span>
			<?php endif; ?>
			<label class="nwcs-sublabel" for="<?php echo esc_attr( $field ); ?>-a"><?php if ( ! $blank ) : ?><span class="screen-reader-text" data-nwcs-faq-label-a><?php echo esc_html( $number . '. ' ); ?></span><?php endif; ?>Cevap</label>
			<textarea class="nwcs-input" id="<?php echo esc_attr( $field ); ?>-a" name="<?php echo esc_attr( 'sss[' . $key . '][a]' ); ?>" rows="3" maxlength="<?php echo (int) NWCS_CAT_FAQ_A_MAX; ?>" data-nwcs-faq-a><?php echo esc_textarea( $row['a'] ); ?></textarea>
			<?php if ( ! $blank && '' !== $row['q'] && '' === $row['a'] ) : ?>
				<p class="nwcs-faqs__warn">Cevabı boş: bu soru sitede görünmez. Cevabı yazıp kaydedince görünür.</p>
			<?php endif; ?>
			<?php if ( $copy ) : ?>
				<p class="nwcs-faqs__warn"><?php echo esc_html( sprintf( 'Cevap %s “%s” sorusunun cevabıyla %s. Bu siteye göre yeniden yazın.', nwcs_locative( $copy['site'] ), $copy['question'], $copy['same'] ? 'aynı' : 'çok benzer' ) ); ?></p>
			<?php endif; ?>
		</div>
		<div class="nwcs-faqs__out">
			<?php if ( ! $blank ) : ?>
				<button type="<?php echo esc_attr( $type ); ?>" name="sil" value="<?php echo esc_attr( $key ); ?>" class="button-link nwcs-row__delete" data-nwcs-faq-remove>Sil<span class="screen-reader-text" data-nwcs-faq-sr>: <?php echo esc_html( $label ); ?></span></button>
			<?php endif; ?>
		</div>
	</li>
	<?php
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

/**
 * Sonuca yonlendirir (sitenin sekmesine).
 */
function nwcs_cat_faq_redirect( string $slug, string $site_key, string $result ): void {
	wp_safe_redirect( nwcs_cat_faq_url( $slug, $site_key, array( 'sss' => $result ) ) );
	exit;
}

/**
 * Kaydeder ve "Son işlemler"e yazar. Ust kayit ayni kullanicinin ayni kategori
 * ve sitedeki kaydiysa ve NWCS_CAT_FAQ_MERGE icindeyse ona eklenir: "onceki"
 * hal (ilk kayittan once) korunur, "sonraki" ve ozet guncellenir. Birlesen
 * kayitlar listeyi oturumun basindaki haline geri getirdiyse kayit kalkar.
 */
function nwcs_cat_faq_commit( string $slug, array $site, array $before, array $after ): void {
	$key = $site['site_key'];

	nwcs_cat_faq_write( $slug, $key, $after );

	$now  = nwcs_cat_faq_rows( $slug, $key );
	$user = get_current_user_id();
	$top  = nwcs_history_top();

	if ( $top
		&& NWCS_CAT_FAQ_KIND === ( $top['kind'] ?? '' )
		&& 'save' === ( $top['action'] ?? '' )
		&& $slug === ( $top['slug'] ?? '' )
		&& $key === ( $top['site'] ?? '' )
		&& $user === (int) ( $top['user'] ?? 0 )
		&& time() - (int) ( $top['time'] ?? 0 ) <= NWCS_CAT_FAQ_MERGE ) {
		$first = nwcs_cat_faq_clean_rows( $top['before'] ?? array() );

		if ( $first === $now ) {
			nwcs_history_drop( (string) $top['id'] );

			return;
		}

		$edits = (int) ( $top['edits'] ?? 1 ) + 1;
		$facts = nwcs_cat_faq_diff_facts( $site['label'], $first, $now ) ?: array( nwcs_locative( $site['label'] ) . ' sorular güncellendi' );

		$top['after'] = $now;
		$top['edits'] = $edits;
		$top['facts'] = array_merge( $facts, array( sprintf( '%d düzenleme', $edits ) ) );
		$top['time']  = time();

		nwcs_history_save( $top );

		return;
	}

	nwcs_history_push(
		array(
			'kind'       => NWCS_CAT_FAQ_KIND,
			'action'     => 'save',
			'slug'       => $slug,
			'site'       => $key,
			'site_label' => $site['label'],
			'user'       => $user,
			'edits'      => 1,
			'category'   => (string) ( nwcs_pool_categories()[ $slug ]['name'] ?? $slug ),
			'before'     => $before,
			'after'      => $now,
			'facts'      => nwcs_cat_faq_diff_facts( $site['label'], $before, $now ) ?: array( nwcs_locative( $site['label'] ) . ' sorular güncellendi' ),
			'complete'   => true,
		)
	);
}

add_action( 'admin_post_nwcs_cat_faq_save', 'nwcs_handle_cat_faq_save' );
function nwcs_handle_cat_faq_save(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce asagida, kategoriye bagli.
	check_admin_referer( 'nwcs_cat_faq_save_' . $slug );

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		wp_safe_redirect( nwcs_pool_categories_url() );
		exit;
	}

	$key  = isset( $_POST['site'] ) ? sanitize_key( wp_unslash( $_POST['site'] ) ) : '';
	$site = null;

	foreach ( nwcs_cat_faq_sites( $slug ) as $candidate ) {
		if ( $candidate['site_key'] === $key ) {
			$site = $candidate;
			break;
		}
	}

	if ( ! $site ) {
		nwcs_cat_faq_redirect( $slug, '', 'yer' );
	}

	// Formdaki sira (tarayici satirlari ekrandaki sirayla gonderir); anahtar satirin kimligi.
	$posted = isset( $_POST['sss'] ) && is_array( $_POST['sss'] ) ? wp_unslash( $_POST['sss'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- alan alan asagida temizlenir.
	$delete = isset( $_POST['sil'] ) ? sanitize_key( wp_unslash( $_POST['sil'] ) ) : '';
	$move   = isset( $_POST['tasi'] ) ? sanitize_text_field( wp_unslash( $_POST['tasi'] ) ) : '';
	$rows   = array();

	foreach ( $posted as $row_key => $row ) {
		$row_key = sanitize_key( (string) $row_key );

		if ( ! is_array( $row ) || '' === $row_key || $row_key === $delete ) {
			continue;
		}

		$question = nwcs_cat_faq_question( $row['q'] ?? '' );
		$answer   = nwcs_cat_faq_answer( $row['a'] ?? '' );

		// Bos satir (formun sonundaki "Yeni soru" gibi) yok sayilir.
		if ( '' === $question && '' === $answer ) {
			continue;
		}

		$rows[ $row_key ] = array(
			'q' => $question,
			'a' => $answer,
		);
	}

	// ↑ ↓ (JavaScript yoksa): formdaki degisikliklerle birlikte kaydedilir.
	// Anahtarlar metin olarak karsilastirilir (PHP "0" anahtarini sayiya cevirir).
	$order = array_map( 'strval', array_keys( $rows ) );
	$rows  = array_values( $rows );

	if ( preg_match( '/^([a-z0-9_-]+):(up|down)$/', $move, $match ) ) {
		$from = array_search( $match[1], $order, true );
		$to   = false === $from ? -1 : $from + ( 'up' === $match[2] ? -1 : 1 );

		if ( $to >= 0 && $to < count( $rows ) ) {
			$moved = array_splice( $rows, (int) $from, 1 );
			array_splice( $rows, $to, 0, $moved );
		}
	}

	// Cevabi olup sorusu bos satir ve fazla satir: hicbir sey yazilmaz, yazilanlar geri gelir.
	$bad   = array_keys( array_filter( $rows, static fn( array $row ): bool => '' === $row['q'] ) );
	$error = $bad ? 'sorusuz' : ( count( $rows ) > NWCS_CAT_FAQ_MAX ? 'fazla' : '' );

	if ( '' !== $error ) {
		set_site_transient(
			NWCS_CAT_FAQ_DRAFT . get_current_user_id(),
			array(
				'slug' => $slug,
				'site' => $key,
				'rows' => $rows,
				'bad'  => $bad,
			),
			15 * MINUTE_IN_SECONDS
		);

		nwcs_cat_faq_redirect( $slug, $key, $error );
	}

	$before = nwcs_cat_faq_rows( $slug, $key );

	if ( nwcs_cat_faq_clean_rows( $rows ) === $before ) {
		nwcs_cat_faq_redirect( $slug, $key, 'ayni' );
	}

	nwcs_cat_faq_commit( $slug, $site, $before, $rows );

	nwcs_cat_faq_redirect( $slug, $key, '' !== $delete ? 'silindi' : 'kaydedildi' );
}

/* ====================================================================== *
 * Geri alma (includes/admin/history.php cagirir)
 * ====================================================================== */

/**
 * Soru degisikligini geri alir: o sitenin onceki listesi yazilir. Liste bu
 * islemden sonra baska yoldan degistiyse dokunulmaz, kayit listeden kalkar.
 *
 * @return array{faq:int}|WP_Error
 */
function nwcs_cat_faq_undo( array $record ) {
	$slug = (string) ( $record['slug'] ?? '' );
	$key  = sanitize_key( (string) ( $record['site'] ?? '' ) );
	$name = (string) ( $record['category'] ?? $slug );

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( '“%s” kategorisi sonradan silinmiş; bu soru değişikliği geri alınamaz. Bu işlem listeden kaldırıldı.', $name ) );
	}

	if ( nwcs_cat_faq_rows( $slug, $key ) !== nwcs_cat_faq_clean_rows( $record['after'] ?? array() ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( '“%s” kategorisinin %s soruları bu işlemden sonra başka yoldan değişmiş; eski hâline döndürülmedi. Bu işlem listeden kaldırıldı.', $name, (string) ( $record['site_label'] ?? $key ) ) );
	}

	nwcs_cat_faq_write( $slug, $key, (array) ( $record['before'] ?? array() ) );

	return array( 'faq' => 1 );
}
