<?php
/**
 * Canli arama: ust menudeki arama kutusuna yazarken acilan sonuc listesi.
 *
 * Veri ucu: GET <site>/wp-json/nwcs/v1/arama?q=<terim>. Her site kendi
 * adresinden, kendi urun listesiyle cevap verir. Yalnizca temasi acan sitede
 * vardir (suzgec 'nwcs_live_search'); digerlerinde yol yok (404).
 *
 * Tema ayari (suzgecten dizi):
 *   'search' => callable( string $terim ): array  sonuc sayfasinin listesi, ayni sirayla;
 *   'url'    => callable( string $terim ): string sonuc sayfasinin adresi.
 * Uc yalnizca q'yu okur: temizlenir, 60 karakterde kesilir; 2 harf/rakamdan
 * kisa terim aranmaz. Sonuc sayfasiyla ayni arama cagrildigi icin liste,
 * siralama ve toplam sayfayla bire bir aynidir; renk gruplari sadelesmez.
 *
 * Yanit: { q, total, url, items[] } (en fazla 8 oge). Oge: id, url, title,
 * code, price, price_note, has_price, can_add, thumb, image {url,width,height}
 * ya da null, text (~160 karakter). Tema ogeyi 'nwcs_live_search_item'
 * suzgeciyle kartindaki gibi bicimler (fiyat notu, "Fiyat teklifle").
 *
 * Onbellek: tarayici 5 dakika tutar (Cache-Control: public, max-age=300);
 * LiteSpeed tutmaz: rastgele terimler sunucu onbellegini doldurmasin (Kocist
 * terimli /arama/ sayfasiyla ayni gerekce).
 *
 * Ekran tarafi assets/live-search.js (tema nwcs_live_search_enqueue ile ekler).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_LIVE_SEARCH_LIMIT  = 8;   // Listede en fazla oge.
const NWCS_LIVE_SEARCH_MIN    = 2;   // Terimde en az harf ya da rakam.
const NWCS_LIVE_SEARCH_CHARS  = 60;  // Terimin en fazla uzunlugu.
const NWCS_LIVE_SEARCH_TEXT   = 160; // Kisa metnin en fazla uzunlugu.
const NWCS_LIVE_SEARCH_MAXAGE = 300; // Tarayici onbellegi (saniye).

/**
 * Sitenin canli arama ayari; tema acmadiysa null.
 *
 * @return array{search: callable, url: callable}|null
 */
function nwcs_live_search_config(): ?array {
	$config = apply_filters( 'nwcs_live_search', null );

	if ( ! is_array( $config ) || ! is_callable( $config['search'] ?? null ) || ! is_callable( $config['url'] ?? null ) ) {
		return null;
	}

	return $config;
}

add_action( 'rest_api_init', 'nwcs_live_search_route' );
function nwcs_live_search_route(): void {
	if ( ! nwcs_live_search_config() ) {
		return;
	}

	register_rest_route(
		'nwcs/v1',
		'/arama',
		array(
			'methods'             => 'GET',
			'callback'            => 'nwcs_live_search_rest',
			'permission_callback' => '__return_true', // Herkese acik, yalnizca okuma: sitede zaten gorunen urunler.
			'args'                => array(
				'q' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		)
	);
}

/**
 * Terim: etiketler ve kontrol karakterleri atilir (sonuc sayfalari gibi
 * sanitize_text_field), 60 karakterde kesilir.
 */
function nwcs_live_search_term( $raw ): string {
	$term = is_string( $raw ) ? sanitize_text_field( $raw ) : '';

	return trim( mb_substr( $term, 0, NWCS_LIVE_SEARCH_CHARS ) );
}

/**
 * Terim aranabilecek kadar uzun mu (ayiraclar sayilmaz)?
 */
function nwcs_live_search_long_enough( string $term ): bool {
	return mb_strlen( nwcs_search_compact( $term ) ) >= NWCS_LIVE_SEARCH_MIN;
}

/**
 * Ucun cevabi (REST disinda da cagrilabilir: deneme betigi).
 *
 * @return array{q:string, total:int, url:string, items:array<int, array>}
 */
function nwcs_live_search_data( string $raw ): array {
	$config = nwcs_live_search_config();
	$term   = nwcs_live_search_term( $raw );

	if ( ! $config || ! nwcs_live_search_long_enough( $term ) ) {
		return array(
			'q'     => $term,
			'total' => 0,
			'url'   => '',
			'items' => array(),
		);
	}

	$found = array_values( array_filter( (array) call_user_func( $config['search'], $term ), 'is_array' ) );

	return array(
		'q'     => $term,
		'total' => count( $found ),
		'url'   => esc_url_raw( (string) call_user_func( $config['url'], $term ) ),
		'items' => nwcs_live_search_items( array_slice( $found, 0, NWCS_LIVE_SEARCH_LIMIT ) ),
	);
}

function nwcs_live_search_rest( WP_REST_Request $request ): WP_REST_Response {
	// 'q' disinda parametre okunmaz; dizi gelirse REST dogrulamasi 400 verir.
	$q        = $request->get_param( 'q' );
	$response = new WP_REST_Response( nwcs_live_search_data( is_string( $q ) ? $q : '' ), 200 );

	$response->header( 'Cache-Control', 'public, max-age=' . NWCS_LIVE_SEARCH_MAXAGE );
	$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );

	return $response;
}

/**
 * Metni duz yaziya cevirip ~160 karakterde, kelime sinirinda keser.
 */
function nwcs_live_search_excerpt( string $text, int $max = NWCS_LIVE_SEARCH_TEXT ): string {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $max );
	$space = mb_strrpos( $cut, ' ' );

	if ( false !== $space && $space > $max * 0.6 ) {
		$cut = mb_substr( $cut, 0, $space );
	}

	return rtrim( $cut, " \t,.;:–—-" ) . '…';
}

/**
 * Listedeki urunlerin ogeleri. Gorseller havuz sitesinin medyasinda: tek
 * gecisle kucuk resim ('thumbnail') ve orta boy ('medium_large', olculeriyle).
 *
 * @param array<int, array> $products
 * @return array<int, array>
 */
function nwcs_live_search_items( array $products ): array {
	$media = array();

	foreach ( $products as $product ) {
		foreach ( (array) ( $product['images'] ?? array() ) as $image ) {
			if ( ! empty( $image['url'] ) ) {
				$media[ (int) $product['id'] ] = array( (int) ( $image['id'] ?? 0 ), (string) $image['url'] );
				break;
			}
		}
	}

	$sizes    = array();
	$switched = is_multisite() && (int) nwcs_pool_blog_id() !== get_current_blog_id();

	if ( $media && $switched ) {
		switch_to_blog( (int) nwcs_pool_blog_id() );
	}

	foreach ( $media as $product_id => [ $attachment_id, $url ] ) {
		$thumb = $attachment_id > 0 ? wp_get_attachment_image_src( $attachment_id, 'thumbnail' ) : false;
		$large = $attachment_id > 0 ? wp_get_attachment_image_src( $attachment_id, 'medium_large' ) : false;

		$sizes[ $product_id ] = array(
			'thumb' => $thumb ? (string) $thumb[0] : $url,
			'image' => array(
				'url'    => $large ? (string) $large[0] : $url,
				'width'  => $large ? (int) $large[1] : 0,
				'height' => $large ? (int) $large[2] : 0,
			),
		);
	}

	if ( $media && $switched ) {
		restore_current_blog();
	}

	$items = array();

	foreach ( $products as $product ) {
		$id       = (int) ( $product['id'] ?? 0 );
		$has_page = nwcs_product_has_page( $product );
		$item     = array(
			'id'         => $id,
			'url'        => $has_page ? (string) ( $product['url'] ?? '' ) : '',
			'title'      => html_entity_decode( (string) ( $product['title'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'code'       => (string) ( $product['code'] ?? '' ),
			'price'      => ! empty( $product['has_price'] ) ? (string) ( $product['price_label'] ?? $product['price'] ?? '' ) : '',
			'price_note' => '',
			'has_price'  => ! empty( $product['has_price'] ),
			'can_add'    => false,
			'thumb'      => $sizes[ $id ]['thumb'] ?? '',
			'image'      => $sizes[ $id ]['image'] ?? null,
			'text'       => nwcs_live_search_excerpt( (string) ( $product['short'] ?? '' ) ),
		);

		/**
		 * Temanin ogeyi kartindaki gibi bicimlemesi (fiyat, fiyat notu,
		 * eklenebilir mi, adres, gorsel). $product sitenin urun satiri.
		 */
		$item = (array) apply_filters( 'nwcs_live_search_item', $item, $product );

		$image = is_array( $item['image'] ?? null ) && '' !== (string) ( $item['image']['url'] ?? '' )
			? array(
				'url'    => esc_url_raw( (string) $item['image']['url'] ),
				'width'  => max( 0, (int) ( $item['image']['width'] ?? 0 ) ),
				'height' => max( 0, (int) ( $item['image']['height'] ?? 0 ) ),
			)
			: null;

		$items[] = array(
			'id'         => (int) ( $item['id'] ?? $id ),
			'url'        => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
			'title'      => (string) ( $item['title'] ?? '' ),
			'code'       => (string) ( $item['code'] ?? '' ),
			'price'      => (string) ( $item['price'] ?? '' ),
			'price_note' => (string) ( $item['price_note'] ?? '' ),
			'has_price'  => (bool) ( $item['has_price'] ?? false ),
			'can_add'    => (bool) ( $item['can_add'] ?? false ),
			'thumb'      => esc_url_raw( (string) ( $item['thumb'] ?? '' ) ),
			'image'      => $image,
			'text'       => (string) ( $item['text'] ?? '' ),
		);
	}

	return $items;
}

/**
 * Canli aramanin betigi ve ayarlari (tema wp_enqueue_scripts'te cagirir).
 * Site acmadiysa ya da panel onizlemesindeyse hicbir sey eklenmez: onizlemede
 * tiklama alani duzenlemeye acar, liste ise yalnizca ziyaretci icindir.
 *
 * $args temanin gorunum ayarlari ve metinleri (assets/live-search.js basi):
 * prefix (sinif oneki), sr (ekran okuyucu sinifi), texts, classes, empty...
 */
function nwcs_live_search_enqueue( array $args ): bool {
	if ( ! nwcs_live_search_active() ) {
		return false;
	}

	$file = NWCS_DIR . 'assets/live-search.js';

	wp_enqueue_script( 'nwcs-live-search', NWCS_URL . 'assets/live-search.js', array(), (string) @filemtime( $file ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$data = array_merge(
		$args,
		array(
			'endpoint' => rest_url( 'nwcs/v1/arama' ),
			'min'      => NWCS_LIVE_SEARCH_MIN,
			'max'      => NWCS_LIVE_SEARCH_CHARS,
		)
	);

	// JSON_HEX_TAG: panel metnindeki "</script>" betigi kapatamasin.
	wp_add_inline_script( 'nwcs-live-search', 'window.nwcsLiveSearch = ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );

	return true;
}

/**
 * Bu sayfada canli arama calisir mi? Arama kutusuna data-nwcs-live niteligi
 * yalnizca o zaman basilir.
 */
function nwcs_live_search_active(): bool {
	if ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) {
		return false;
	}

	return null !== nwcs_live_search_config();
}
