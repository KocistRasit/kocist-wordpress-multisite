<?php
/**
 * Urun aramasi: /arama/?ara=<terim>.
 *
 * Neden ayri sayfa (/kategoriler/?ara= degil): /kategoriler/ dizine giren,
 * kendi canonical'i olan bir sayfa; ayni adresin sorgulu hali noindex olunca
 * arama motoruna celisen isaret gider (biri dizinle der, digeri dizinleme).
 * /arama/ her zaman noindex, follow ve site haritasinda yok; ust menudeki form
 * hep ayni adrese gider. Sayfa diger tema sayfalari gibi acilir
 * (functions.php, kocist_ensure_pages); cizimi template-parts/search-page.php.
 *
 * Eslestirme ve siralama eklentide ortak (includes/search.php,
 * nwcs_search_products): kod, ad ve kategori adi; Turkce harf, buyuk/kucuk
 * harf, bosluk ve tire farki yok. Renk gruplari sadelestirilmez: her renk
 * ayri urun olarak bulunur (kodla arayan dogru urunu bulur).
 */

defined( 'ABSPATH' ) || exit;

const KOCIST_SEARCH_MAX   = 60; // Sayfada en fazla bu kadar sonuc.
const KOCIST_SEARCH_MIN   = 2;  // Terimde en az bu kadar harf ya da rakam.
const KOCIST_SEARCH_CHARS = 80; // Terimin en fazla uzunlugu.

function kocist_search_url( string $term = '' ): string {
	$url = home_url( '/arama/' );

	return '' !== $term ? add_query_arg( 'ara', rawurlencode( $term ), $url ) : $url;
}

/**
 * Adresteki terim (?ara=), temizlenmis ve kisaltilmis.
 */
function kocist_search_term(): string {
	$term = isset( $_GET['ara'] ) && is_string( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca okuma.

	return trim( mb_substr( $term, 0, KOCIST_SEARCH_CHARS ) );
}

/**
 * Terim aranabilecek kadar uzun mu (ayiraclar sayilmaz)?
 */
function kocist_search_long_enough( string $term ): bool {
	$compact = function_exists( 'nwcs_search_compact' ) ? nwcs_search_compact( $term ) : (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $term );

	return mb_strlen( $compact ) >= KOCIST_SEARCH_MIN;
}

/**
 * Terimle eslesen urunler, siralanmis. Sitede gorunen kategori ve grup adi da
 * aranir (panelde kategoriye bu site icin baska ad verilmis olabilir).
 *
 * @return array<int, array>
 */
function kocist_search( string $term ): array {
	if ( ! function_exists( 'nwcs_search_products' ) || ! kocist_search_long_enough( $term ) ) {
		return array();
	}

	$groups = kocist_catalog_groups();

	return nwcs_search_products(
		kocist_catalog_products(),
		$term,
		static function ( array $product ) use ( $groups ): array {
			$group = $groups[ $product['group'] ?? '' ] ?? null;

			return array_filter(
				array(
					(string) ( $group['name'] ?? '' ),
					(string) ( $group['subs'][ $product['sub'] ?? '' ]['name'] ?? '' ),
				)
			);
		}
	);
}

/**
 * Arama ve teklif sepeti sayfasi acik mi? Sayfalar yonetici panele ilk
 * girdiginde acilir (kocist_ensure_pages); o zamana kadar ust menudeki
 * dugmeler ve urun sayfasindaki ekleme formu basilmaz, ziyaretci 404'e gitmez.
 * Panel onizlemesinde her zaman var: duzenlenebilsin.
 */
function kocist_page_ready( string $slug ): bool {
	static $ready = array();

	if ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) {
		return true;
	}

	if ( ! isset( $ready[ $slug ] ) ) {
		$page            = get_page_by_path( $slug, OBJECT, 'page' );
		$ready[ $slug ] = $page && 'publish' === $page->post_status;
	}

	return $ready[ $slug ];
}

/*
 * Terimli arama onbellege alinmaz: rastgele terimler LiteSpeed onbellegini
 * doldurmasin. Terimsiz /arama/ (bos form) onbellekte kalir.
 */
add_action( 'template_redirect', 'kocist_search_nocache', 1 );
function kocist_search_nocache(): void {
	if ( is_page( 'arama' ) && '' !== kocist_search_term() && function_exists( 'kocist_basket_nocache_headers' ) ) {
		kocist_basket_nocache_headers();
	}
}

/*
 * Arama ve teklif sepeti sayfalari arama motorlarina kapali (icerikleri
 * ziyaretciye gore degisir) ve site haritasinda yok. Urun sayfasinin
 * "sepete eklendi" donusu (?teklif=) kendi canonical'iyla urun sayfasini gosterir.
 */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( is_page( array( 'arama', 'teklif-sepeti' ) ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );

			// Site arama motorlarina kapaliyken (panelde onay bekleyen site) nofollow kalir.
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
		}

		return $robots;
	}
);

add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( array $args, string $post_type ): array {
		if ( 'page' === $post_type ) {
			foreach ( array( 'arama', 'teklif-sepeti' ) as $slug ) {
				$page = get_page_by_path( $slug, OBJECT, 'page' );

				if ( $page ) {
					$args['post__not_in'][] = (int) $page->ID;
				}
			}
		}

		return $args;
	},
	10,
	2
);

/**
 * Sekme basligi: "‘ADR’ araması | Koçist". Eklentinin basligindan (20) ve
 * kategori basligindan (30) sonra; yalnizca arama sayfasinda.
 */
add_filter( 'pre_get_document_title', 'kocist_search_document_title', 40 );
function kocist_search_document_title( $title ) {
	if ( ! is_page( 'arama' ) ) {
		return $title;
	}

	$term = kocist_search_term();
	$name = trim( (string) nwcs_field( 'arama', 'head', 'crumb' ) );
	$site = function_exists( 'nwcs_seo_site_name' ) ? nwcs_seo_site_name() : get_bloginfo( 'name', 'display' );

	// WordPress bu suzgecten gelen basligi kacirmadan basar.
	return esc_html( ( '' !== $term ? '‘' . $term . '’ – ' : '' ) . ( '' !== $name ? $name : 'Arama' ) . ' | ' . $site );
}

/**
 * Arama formu: ust menude, cekmecede ve arama sayfasinda ayni form. Her
 * kopyanin kendi bolge adi var (ekran okuyucunun bolge listesinde ayirt
 * edilsin): panelde Tüm Sayfalar > "Üst Menü: Arama ve Teklif Sepeti".
 *
 * @param string $id    Kutunun kimligi (sayfada tekil).
 * @param string $class Formun ek sinifi.
 * @param string $value Kutudaki terim.
 * @param string $name  Bolge adinin panel alani (search_region_bar, _drawer, _page).
 * @param bool   $live  Yazarken sonuc listesi acilsin mi (ust menu ve cekmece; bkz. asagi).
 */
function kocist_search_form( string $id, string $class = '', string $value = '', string $name = 'search_region_bar', bool $live = false ): void {
	$live = $live && function_exists( 'nwcs_live_search_active' ) && nwcs_live_search_active();
	?>
	<form class="k-searchform<?php echo '' !== $class ? ' ' . esc_attr( $class ) : ''; ?>" role="search" aria-label="<?php echo esc_attr( nwcs_field( 'global', 'tools', $name ) ); ?>" method="get" action="<?php echo esc_url( kocist_search_url() ); ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>" <?php nwcs_edit_attr( 'global', 'tools', 'search_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'tools', 'search_label' ) ); ?></label>
		<svg class="k-searchform__icon" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m12.6 12.6 4.4 4.4" /></svg>
		<input class="k-searchform__input" id="<?php echo esc_attr( $id ); ?>" type="search" name="ara" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo (int) KOCIST_SEARCH_CHARS; ?>" autocomplete="off" enterkeyhint="search" placeholder="<?php echo esc_attr( nwcs_field( 'global', 'tools', 'search_hint' ) ); ?>" <?php nwcs_edit_attr( 'global', 'tools', 'search_hint' ); ?><?php echo $live ? ' data-nwcs-live' : ''; ?> />
		<button type="submit" class="k-searchform__btn" <?php nwcs_edit_attr( 'global', 'tools', 'search_button' ); ?>><?php echo esc_html( nwcs_field( 'global', 'tools', 'search_button' ) ); ?></button>
	</form>
	<?php
}

/*
 * Canli arama (eklenti: includes/live-search.php, assets/live-search.js).
 *
 * Ust menudeki kutuya ve cekmecedeki kutuya yazarken liste acilir; veri ucu
 * <site>/wp-json/nwcs/v1/arama?q= bu sayfanin aramasini (kocist_search)
 * cagirir: liste, sira ve "Tüm sonuçlar (N)" sonuc sayfasiyla ayni. Arama
 * sayfasi acilmadan (kocist_page_ready) uc da, liste de yok. Genis ekranda
 * yandaki onizlemeden teklif sepetine eklenir (teklif.js, ayni cerez yolu).
 * Metinler panelde: Tüm Sayfalar > "Üst Menü: Arama ve Teklif Sepeti";
 * sonuc yoksa Arama Sayfasi'nin metinleri, ekleme Teklif Sepeti'nin metinleri.
 */
add_filter( 'nwcs_live_search', 'kocist_live_search_config' );
function kocist_live_search_config( $config ) {
	if ( ! kocist_page_ready( 'arama' ) ) {
		return $config;
	}

	return array(
		'search' => 'kocist_search',
		'url'    => 'kocist_search_url',
	);
}

/**
 * Listedeki urun, kartindaki gibi: detay sayfasi yoksa teklif formuna gider,
 * fiyatsiz urunde "Fiyat teklifle", her urun teklif sepetine eklenebilir
 * (sepet sayfasi aciksa). Yer tutucu gorsel gosterilmez.
 */
add_filter( 'nwcs_live_search_item', 'kocist_live_search_item', 10, 2 );
function kocist_live_search_item( array $item, array $product ): array {
	$item['url']     = kocist_product_has_page( $product ) ? (string) $product['url'] : kocist_quote_url( $product );
	$item['price']   = ! empty( $product['has_price'] ) ? (string) $product['price_label'] : (string) nwcs_field( 'kategoriler', 'texts', 'price_quote' );
	$item['can_add'] = kocist_page_ready( 'teklif-sepeti' );

	if ( '' === (string) ( kocist_product_image( $product )['url'] ?? '' ) ) {
		$item['thumb'] = '';
		$item['image'] = null;
	}

	return $item;
}

add_action( 'wp_enqueue_scripts', 'kocist_live_search_assets', 20 );
function kocist_live_search_assets(): void {
	if ( ! function_exists( 'nwcs_live_search_enqueue' ) || ! kocist_page_ready( 'arama' ) ) {
		return;
	}

	$wa     = kocist_general_wa_digits();
	$basket = kocist_page_ready( 'teklif-sepeti' );
	$field  = static fn( string $page, string $component, string $name ): string => (string) nwcs_field( $page, $component, $name );

	nwcs_live_search_enqueue(
		array(
			'prefix'  => 'k-ls',
			'sr'      => 'screen-reader-text',
			'maxQty'  => KOCIST_BASKET_QTY,
			'cart'    => $basket ? kocist_basket_url() : '',
			'texts'   => array(
				'list'       => $field( 'global', 'tools', 'live_list' ),
				'loading'    => $field( 'global', 'tools', 'live_loading' ),
				'count'      => $field( 'global', 'tools', 'live_count' ),
				'all'        => $field( 'global', 'tools', 'live_all' ),
				'code'       => $field( 'global', 'tools', 'live_code' ),
				'error'      => $field( 'global', 'tools', 'live_error' ),
				'emptyTitle' => $field( 'arama', 'results', 'empty_title' ),
				'emptyText'  => $field( 'arama', 'results', 'empty_text' ),
				'add'        => $field( 'teklif', 'add', 'button' ),
				'qty'        => $field( 'teklif', 'add', 'qty_label' ),
				'less'       => $field( 'teklif', 'add', 'qty_less' ),
				'more'       => $field( 'teklif', 'add', 'qty_more' ),
				'added'      => $field( 'teklif', 'add', 'added' ),
				'go'         => $field( 'teklif', 'add', 'go' ),
				'full'       => kocist_text( 'teklif', 'add', 'full', array( 'sayi' => KOCIST_BASKET_MAX ) ),
				'open'       => $field( 'kategoriler', 'texts', 'go_detail' ),
				'noImage'    => $field( 'global', 'texts', 'image_soon' ),
			),
			// Sitenin kendi siniflari: adet secici (header.css), arama dugmesi.
			'classes' => array(
				'qty'      => 'k-qty',
				'qtyBtn'   => 'k-qty__btn',
				'qtyInput' => 'k-qty__input',
				'add'      => 'k-searchform__btn',
				'open'     => 'k-searchform__btn',
			),
			// Sonuc yoksa arama sayfasindaki gibi: iletisim ve WhatsApp (hazir mesajda terim).
			'links'   => array(
				array(
					'label' => $field( 'arama', 'results', 'contact_label' ),
					'url'   => home_url( '/iletisim/' ),
				),
			),
			'wa'      => '' !== $wa ? array(
				'base'  => kocist_wa_url( $wa ),
				'label' => $field( 'arama', 'results', 'wa_label' ),
				'msg'   => $field( 'arama', 'results', 'wa_msg' ),
			) : null,
		)
	);
}
