<?php
/**
 * Site onbellegi temizligi: havuz ya da bir sitenin panel verisi degisince
 * sitelerdeki sayfa onbellegi.
 *
 * Canlida sayfalar LiteSpeed (sunucu) ve Cloudflare (CDN) onbelleginden gelir.
 * Iki tur "kirli" isareti var:
 *   - havuz: urun, kategori, yerlesim, Excel, geri alma (nwcs_pool_flush_cache()
 *     / nwcs_pool_categories_flush()) -> urun gosteren butun siteler;
 *   - site: panel yetkisiyle bir sitenin nwcs_ ayari (icerik, ozellestirme,
 *     SEO, yonlendirme, yer tutucu) ya da yazisi degisti -> yalnizca o site.
 * Temizlik istek basina bir kez yapilir:
 *   - panel islemi yonlendirmeyle bitiyorsa yonlendirmeden hemen once (sonuc
 *     bir sonraki sayfada kisa bir notla gosterilir);
 *   - yonlendirme yoksa (AJAX, WP-CLI, MCP) istek sonunda.
 *
 * LiteSpeed: eklenti kuruluysa temizlik her sitenin KENDI adresine imzali bir
 * istekle yapilir (?nwcs_purge=). Kendi alan adina bagli site (kocist.com.tr)
 * sunucuda ayri bir sanal sunucu; panelden (panel.sanayipalet.com) gonderilen
 * temizleme basligi onun onbellegine ulasmaz. Ayrica LiteSpeed ayni istekte
 * ikinci sitenin "hepsini temizle" komutunu birinciyle ayni sayip atliyordu.
 * Kurulu degilse hicbir sey olmaz.
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
	static $state = array( 'dirty' => false, 'done' => false, 'blogs' => array() );

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
 * Tek bir sitenin verisi degisti: istek sonunda yalnizca o site temizlenir.
 */
function nwcs_cache_mark_blog_dirty( int $blog_id ): void {
	$state = &nwcs_cache_state();

	$state['blogs'][ $blog_id ] = true;
}

/**
 * Panelden sitenin ayari ya da yazisi degisti. update_blog_option siteye
 * gecerek yazar; kanca o sitenin icinde calisir. Ziyaretcinin yazmalari
 * (form istatistigi gibi) temizlik baslatmaz: yalnizca panel yetkisi.
 */
add_action( 'added_option', 'nwcs_cache_option_changed' );
add_action( 'updated_option', 'nwcs_cache_option_changed' );
add_action( 'deleted_option', 'nwcs_cache_option_changed' );
function nwcs_cache_option_changed( string $option ): void {
	if ( str_starts_with( $option, 'nwcs_' ) && 'nwcs_pool_cache_pruned' !== $option && did_action( 'init' ) && current_user_can( NWCS_CAPABILITY ) ) {
		nwcs_cache_mark_blog_dirty( get_current_blog_id() );
	}
}

add_action( 'save_post', 'nwcs_cache_post_changed' );
add_action( 'deleted_post', 'nwcs_cache_post_changed' );
function nwcs_cache_post_changed(): void {
	if ( did_action( 'init' ) && current_user_can( NWCS_CAPABILITY ) ) {
		nwcs_cache_mark_blog_dirty( get_current_blog_id() );
	}
}

/**
 * Temizlik istegi imzasi: site, zaman ve wp-config tuzlari. Ag tek kurulum;
 * hedef site ayni tuzlarla dogrular.
 */
function nwcs_cache_purge_signature( int $blog_id, int $time ): string {
	return hash_hmac( 'sha256', 'nwcs-purge|' . $blog_id . '|' . $time, wp_salt( 'auth' ) );
}

/**
 * Sitenin kendi adresine temizlik istegi. Beklemeden gider (WordPress'in
 * zamanlanmis gorev tetigi gibi): on site birden temizlenirken panel yavaslamaz.
 */
function nwcs_cache_request_purge( int $blog_id ): void {
	$time = time();

	wp_remote_get(
		add_query_arg(
			array(
				'nwcs_purge' => nwcs_cache_purge_signature( $blog_id, $time ),
				't'          => $time,
			),
			get_home_url( $blog_id, '/' )
		),
		array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		)
	);
}

/**
 * Temizlik istegini karsilar: imza ve 5 dakikalik sure dogruysa bu sitenin
 * LiteSpeed onbellegini temizler. Yanit onbellege girmez.
 */
add_action( 'init', 'nwcs_cache_purge_endpoint', 0 );
function nwcs_cache_purge_endpoint(): void {
	if ( ! isset( $_GET['nwcs_purge'], $_GET['t'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- imzali istek, asagida dogrulanir.
		return;
	}

	$time      = (int) $_GET['t']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$signature = sanitize_text_field( wp_unslash( $_GET['nwcs_purge'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	nocache_headers();
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'X-Robots-Tag: noindex' );

	if ( abs( time() - $time ) > 5 * MINUTE_IN_SECONDS || ! hash_equals( nwcs_cache_purge_signature( get_current_blog_id(), $time ), $signature ) ) {
		status_header( 403 );
		exit;
	}

	// Istegi gonderen beklemeden kapatir; temizlik yine de tamamlansin.
	ignore_user_abort( true );

	// Yonetici panelinde "temizlendi" bildirimi biriktirmesin.
	if ( ! defined( 'LITESPEED_PURGE_SILENT' ) ) {
		define( 'LITESPEED_PURGE_SILENT', true );
	}

	do_action( 'litespeed_purge_all' );

	status_header( 204 );
	exit;
}

/**
 * Sayfa onbellegi omru: en fazla 8 saat (LiteSpeed varsayilani 7 gun).
 *
 * Temalarin iletisim ve teklif formlari WordPress nonce'u tasir; nonce 12-24
 * saat gecerlidir. Daha uzun saklanan bir sayfadan gonderilen form "oturum
 * zaman asimina ugradi" diye reddedilir; sayfayi yenilemek de ise yaramaz,
 * ayni kopya gelir. 8 saat, en kisa nonce omrunun (12 saat) altinda kalir.
 * Panelden degisiklikte sayfa zaten hemen temizlenir (yukarisi).
 */
add_action( 'template_redirect', 'nwcs_cache_page_ttl', 0 );
function nwcs_cache_page_ttl(): void {
	if ( ! is_admin() ) {
		do_action( 'litespeed_control_set_ttl', 8 * HOUR_IN_SECONDS );
	}
}

/**
 * Form sonucu sayfalari onbellege girmez. Temalar hatali/basarili gonderimden
 * sonra ziyaretciyi tek kullanimlik anahtarla geri gonderir (?kc=, ?aas=,
 * ?ak=, ?mesaj=, ?ip=, ?pc=, ?teklif=, ?wk=); sayfada ziyaretcinin yazdigi ad,
 * telefon, mesaj gorunur. Paylasimli onbellekte kalmamali; anahtar da tek
 * kullanimlik olmaktan cikardi (ikinci istek onbellekten geliyordu).
 * LiteSpeed Cache nocache_headers()'a bakmaz: sabit + kendi basligi gerekir.
 * Temalar listeyi 'nwcs_cache_nocache_query_keys' ile genisletebilir.
 */
add_action( 'template_redirect', 'nwcs_cache_nocache_state_pages', 0 );
function nwcs_cache_nocache_state_pages(): void {
	$keys = (array) apply_filters( 'nwcs_cache_nocache_query_keys', array( 'kc', 'aas', 'ak', 'mesaj', 'ip', 'pc', 'teklif', 'wk' ) );

	foreach ( $keys as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca var/yok.
			continue;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}

		return;
	}
}

/**
 * Kirli siteler: havuz degistiyse urun gosteren butun siteler, ustune tek
 * tek isaretlenenler.
 *
 * @return int[]
 */
function nwcs_cache_dirty_blogs(): array {
	$state = nwcs_cache_state();
	$blogs = $state['blogs'];

	if ( $state['dirty'] ) {
		foreach ( get_sites( array( 'number' => 200, 'deleted' => 0, 'archived' => 0 ) ) as $site ) {
			if ( nwcs_site_supports_products( (int) $site->blog_id ) ) {
				$blogs[ (int) $site->blog_id ] = true;
			}
		}
	}

	return array_map( 'intval', array_keys( $blogs ) );
}

/**
 * Kirli sitelerin sayfa onbellegini ve (tanimliysa) Cloudflare'i temizler.
 *
 * @return array{litespeed:bool, cloudflare:string, error:string} cloudflare: none|ok|fail
 */
function nwcs_purge_site_caches(): array {
	$blogs          = nwcs_cache_dirty_blogs();
	$state          = &nwcs_cache_state();
	$state['done']  = true;
	$state['dirty'] = false;
	$state['blogs'] = array();
	$result         = array( 'litespeed' => false, 'cloudflare' => 'none', 'error' => '' );

	if ( has_action( 'litespeed_purge_all' ) && $blogs ) {
		foreach ( $blogs as $blog_id ) {
			nwcs_cache_request_purge( $blog_id );
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

	if ( ( $state['dirty'] || $state['blogs'] ) && is_admin() && is_user_logged_in() ) {
		$result = nwcs_purge_site_caches();

		// Not havuz ekranlarinda gosterilir: yalnizca havuz degisikliginde.
		if ( $state['dirty'] && ( $result['litespeed'] || 'none' !== $result['cloudflare'] ) ) {
			set_site_transient( 'nwcs_cache_note_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		}
	}

	return $location;
}

/**
 * Yonlendirmesiz yazmalar (AJAX, WP-CLI, MCP): istek sonunda.
 */
add_action( 'shutdown', 'nwcs_cache_purge_on_shutdown' );
function nwcs_cache_purge_on_shutdown(): void {
	$state = nwcs_cache_state();

	if ( $state['dirty'] || $state['blogs'] ) {
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
