<?php
/**
 * IndexNow: degisen adresleri Bing, Yandex ve diger IndexNow motorlarina
 * bildirir (https://www.indexnow.org/).
 *
 * - Her site kendi anahtarini uretir ('nwcs_indexnow_key' secenegi) ve
 *   /<anahtar>.txt adresinden duz metin olarak sunar.
 * - Yazi ya da sayfa yayinlaninca veya guncellenince bir dakika icinde; urun
 *   ve kategori sayfalari icin saatte bir, site haritasindaki 'lastmod'
 *   tarihi son gonderimden yeni olan adresler gonderilir.
 * - Ilk calismada sitenin butun adresleri bir kez gonderilir.
 * - Arama motorlarina kapali sitede (blog_public = 0) hicbir sey gonderilmez,
 *   zamanlanmis gorev silinir ve anahtar dosyasi 404 verir.
 * - Son gonderimin sonucu 'nwcs_indexnow_last' seceneginde tutulur.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';
const NWCS_INDEXNOW_HOOK     = 'nwcs_indexnow_run';

/**
 * Bu sitede IndexNow calissin mi: arama motorlarina acik, manifestli ve
 * canli (production) site.
 */
function nwcs_indexnow_enabled(): bool {
	if ( ! get_option( 'blog_public' ) || empty( nwcs_manifest()['pages'] ) ) {
		return false;
	}

	return ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type();
}

/**
 * Sitenin IndexNow anahtari; yoksa bir kez uretilir (32 karakter, a-z 0-9).
 */
function nwcs_indexnow_key(): string {
	$key = (string) get_option( 'nwcs_indexnow_key', '' );

	if ( ! preg_match( '/^[a-z0-9]{32}$/', $key ) ) {
		$key = strtolower( wp_generate_password( 32, false, false ) );
		update_option( 'nwcs_indexnow_key', $key, false );
	}

	return $key;
}

/**
 * /<anahtar>.txt: arama motoru anahtarin bu siteye ait oldugunu buradan dogrular.
 */
add_action( 'parse_request', 'nwcs_indexnow_maybe_key_file' );
function nwcs_indexnow_maybe_key_file( WP $wp ): void {
	$key = (string) get_option( 'nwcs_indexnow_key', '' );

	if ( '' === $key || $key . '.txt' !== $wp->request ) {
		return;
	}

	if ( ! nwcs_indexnow_enabled() ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	echo $key; // phpcs:ignore WordPress.Security.EscapingOutput -- a-z 0-9.
	exit;
}

/**
 * Saatlik gorev: acik sitede kurulur, kapali sitede silinir.
 */
add_action( 'init', 'nwcs_indexnow_schedule' );
function nwcs_indexnow_schedule(): void {
	$next = wp_next_scheduled( NWCS_INDEXNOW_HOOK );

	if ( ! nwcs_indexnow_enabled() ) {
		if ( $next ) {
			wp_clear_scheduled_hook( NWCS_INDEXNOW_HOOK );
		}
		return;
	}

	if ( ! $next ) {
		wp_schedule_event( time() + 300, 'hourly', NWCS_INDEXNOW_HOOK );
	}
}

/**
 * Yazi ya da sayfa yayinlaninca veya yayindayken guncellenince saatlik
 * gorevi beklemeden bir dakika sonra gonderim yapilir.
 */
add_action( 'transition_post_status', 'nwcs_indexnow_on_publish', 10, 3 );
function nwcs_indexnow_on_publish( $new_status, $old_status, $post ): void {
	if ( 'publish' !== $new_status || ! ( $post instanceof WP_Post ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}

	if ( nwcs_indexnow_enabled() && ! wp_next_scheduled( NWCS_INDEXNOW_HOOK, array( 'yayin' ) ) ) {
		wp_schedule_single_event( time() + 60, NWCS_INDEXNOW_HOOK, array( 'yayin' ) );
	}
}

/**
 * $since (Unix zamani) sonrasinda degisen adresler; $since 0 ise hepsi.
 * Yalnizca sitenin kendi alan adindaki adresler, her biri bir kez.
 *
 * @return string[]
 */
function nwcs_indexnow_changed_urls( int $since ): array {
	$urls = array();

	if ( 0 === $since ) {
		$urls[] = home_url( '/' );
	}

	$query = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);

	if ( $since > 0 ) {
		$query['date_query'] = array(
			array(
				'column'    => 'post_modified_gmt',
				'after'     => gmdate( 'Y-m-d H:i:s', $since ),
				'inclusive' => true,
			),
		);
	}

	$excluded = function_exists( 'nwcs_seo_excluded_page_ids' ) ? (array) nwcs_seo_excluded_page_ids() : array();

	if ( $excluded ) {
		$query['post__not_in'] = array_map( 'intval', $excluded );
	}

	foreach ( get_posts( $query ) as $id ) {
		$link = get_permalink( $id );

		if ( $link ) {
			$urls[] = (string) $link;
		}
	}

	foreach ( nwcs_seo_extra_sitemap_entries() as $row ) {
		$changed = empty( $row['lastmod'] ) ? 0 : (int) strtotime( (string) $row['lastmod'] );

		if ( 0 === $since || $changed >= $since ) {
			$urls[] = (string) $row['loc'];
		}
	}

	$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

	$urls = array_filter(
		$urls,
		static function ( $url ) use ( $host ) {
			return '' !== $url && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === $host;
		}
	);

	return array_values( array_unique( $urls ) );
}

/**
 * Adres listesini IndexNow'a gonderir.
 *
 * @param string[] $urls En fazla 10.000 adres.
 * @return array{0:int,1:string} HTTP kodu (0 = baglanti hatasi) ve hata metni.
 */
function nwcs_indexnow_send( array $urls ): array {
	$key  = nwcs_indexnow_key();
	$body = array(
		'host'        => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
		'key'         => $key,
		'keyLocation' => home_url( '/' . $key . '.txt' ),
		'urlList'     => array_values( $urls ),
	);

	$response = wp_remote_post(
		NWCS_INDEXNOW_ENDPOINT,
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 0, $response->get_error_message() );
	}

	return array( (int) wp_remote_retrieve_response_code( $response ), '' );
}

/**
 * Gorev: degisen adresleri gonderir. Basarili olursa (200 ya da 202) bir
 * sonraki calisma bu calismanin baslangicindan sonrasina bakar; basarisizsa
 * ayni adresler bir sonraki calismada yeniden denenir.
 */
add_action( NWCS_INDEXNOW_HOOK, 'nwcs_indexnow_run' );
function nwcs_indexnow_run( $reason = '' ): void {
	if ( ! nwcs_indexnow_enabled() ) {
		return;
	}

	$started = time();
	$since   = (int) get_option( 'nwcs_indexnow_since', 0 );
	$urls    = nwcs_indexnow_changed_urls( $since );

	if ( ! $urls ) {
		update_option( 'nwcs_indexnow_since', $started, false );
		return;
	}

	$sent  = 0;
	$code  = 0;
	$error = '';

	foreach ( array_chunk( $urls, 10000 ) as $chunk ) {
		list( $code, $error ) = nwcs_indexnow_send( $chunk );

		if ( 200 !== $code && 202 !== $code ) {
			break;
		}

		$sent += count( $chunk );
	}

	if ( count( $urls ) === $sent ) {
		update_option( 'nwcs_indexnow_since', $started, false );
	}

	update_option(
		'nwcs_indexnow_last',
		array(
			'time'   => $started,
			'reason' => (string) $reason,
			'code'   => $code,
			'urls'   => count( $urls ),
			'sent'   => $sent,
			'error'  => $error,
		),
		false
	);
}
