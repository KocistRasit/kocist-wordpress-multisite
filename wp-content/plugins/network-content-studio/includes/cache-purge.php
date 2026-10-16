<?php
/**
 * Site onbellegi temizligi: havuz verisi degisince sitelerdeki sayfa onbellegi.
 *
 * Canlida sayfalar LiteSpeed (sunucu) ve Cloudflare (CDN) onbelleginden gelir.
 * Urun, kategori, yerlesim, Excel, geri alma gibi her yazma yolu havuz
 * onbellegini nwcs_pool_flush_cache() / nwcs_pool_categories_flush() ile
 * temizler; bunlar yalnizca "kirli" isareti koyar. Temizlik istek basina bir
 * kez yapilir:
 *   - panel islemi yonlendirmeyle bitiyorsa yonlendirmeden hemen once (sonuc
 *     bir sonraki sayfada kisa bir notla gosterilir);
 *   - yonlendirme yoksa (WP-CLI, MCP) istek sonunda.
 *
 * LiteSpeed: eklenti kuruluysa havuz urunlerini gosteren her sitede
 * 'litespeed_purge_all' (site basina; Havuz Paketi ve Gorsel Yer Tutucu ile
 * ayni yol). Kurulu degilse hicbir sey olmaz.
 *
 * Cloudflare: yalnizca wp-config.php'de iki sabit tanimliysa:
 *   define( 'NWCS_CF_ZONE_ID', 'bolge-kimligi' );            // birden cok alan adi: virgulle
 *   define( 'NWCS_CF_API_TOKEN', 'yalnizca Cache Purge izinli anahtar' );
 * O zaman her bolgede "purge_everything". Anahtar hicbir yere yazilmaz.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Istek ici durum (referansla).
 */
function &nwcs_cache_state(): array {
	static $state = array( 'dirty' => false, 'done' => false );

	return $state;
}

/**
 * Havuz verisi degisti: bu istegin sonunda site onbellekleri temizlenecek.
 */
function nwcs_cache_mark_dirty(): void {
	$state          = &nwcs_cache_state();
	$state['dirty'] = true;
}

/**
 * Havuz urunlerini gosteren sitelerin sayfa onbellegini ve (tanimliysa)
 * Cloudflare'i temizler.
 *
 * @return array{litespeed:bool, cloudflare:string, error:string} cloudflare: none|ok|fail
 */
function nwcs_purge_site_caches(): array {
	$state          = &nwcs_cache_state();
	$state['done']  = true;
	$state['dirty'] = false;
	$result         = array( 'litespeed' => false, 'cloudflare' => 'none', 'error' => '' );

	if ( has_action( 'litespeed_purge_all' ) ) {
		foreach ( get_sites( array( 'number' => 200, 'deleted' => 0, 'archived' => 0 ) ) as $site ) {
			if ( ! nwcs_site_supports_products( (int) $site->blog_id ) ) {
				continue;
			}

			switch_to_blog( (int) $site->blog_id );
			do_action( 'litespeed_purge_all' );
			restore_current_blog();
		}

		$result['litespeed'] = true;
	}

	if ( defined( 'NWCS_CF_ZONE_ID' ) && defined( 'NWCS_CF_API_TOKEN' ) && '' !== (string) NWCS_CF_API_TOKEN ) {
		$result['cloudflare'] = 'ok';

		foreach ( array_filter( array_map( 'trim', explode( ',', (string) NWCS_CF_ZONE_ID ) ) ) as $zone ) {
			$response = wp_remote_post(
				'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone ) . '/purge_cache',
				array(
					'timeout' => 8,
					'headers' => array(
						'Authorization' => 'Bearer ' . NWCS_CF_API_TOKEN,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
				)
			);

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body = is_wp_error( $response ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( 200 !== $code || empty( $body['success'] ) ) {
				// Yalnizca durum; anahtar ya da yanit govdesi yazilmaz.
				$result['cloudflare'] = 'fail';
				$result['error']      = is_wp_error( $response ) ? 'bağlantı kurulamadı' : 'HTTP ' . $code;
			}
		}
	}

	return $result;
}

/**
 * Panel islemi yonlendirmeyle bitiyor: once temizle, sonucu bir sonraki
 * sayfaya birak.
 */
add_filter( 'wp_redirect', 'nwcs_cache_purge_before_redirect', 1 );
function nwcs_cache_purge_before_redirect( $location ) {
	$state = nwcs_cache_state();

	if ( $state['dirty'] && is_admin() && is_user_logged_in() ) {
		$result = nwcs_purge_site_caches();

		if ( $result['litespeed'] || 'none' !== $result['cloudflare'] ) {
			set_site_transient( 'nwcs_cache_note_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		}
	}

	return $location;
}

/**
 * Yonlendirmesiz yazmalar (WP-CLI, MCP): istek sonunda.
 */
add_action( 'shutdown', 'nwcs_cache_purge_on_shutdown' );
function nwcs_cache_purge_on_shutdown(): void {
	if ( nwcs_cache_state()['dirty'] ) {
		nwcs_purge_site_caches();
	}
}

/**
 * Havuz ekranlarinda: son islemin onbellek sonucu (bir kez).
 */
function nwcs_render_cache_note(): void {
	$key    = 'nwcs_cache_note_' . get_current_user_id();
	$result = get_site_transient( $key );

	if ( ! is_array( $result ) ) {
		return;
	}

	delete_site_transient( $key );

	if ( 'fail' === ( $result['cloudflare'] ?? '' ) ) {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( 'Değişiklik kaydedildi, ancak sitelerde bir süre eski hâli görünebilir. Birkaç dakika sonra siteye bakın; eski hâl sürerse geliştiriciye haber verin. (Teknik: Cloudflare önbelleği temizlenemedi, %s; Cloudflare panelinden “Caching → Purge Everything” yapılabilir)', (string) ( $result['error'] ?? '' ) ) )
		);

		return;
	}

	echo '<div class="notice notice-info is-dismissible"><p>Site önbelleği temizlendi; değişiklik sitelerde hemen görünür.</p></div>';
}
