<?php
/**
 * Katalog sayfasi (/katalog/): belge ve sertifika dosyalarinin cozumu.
 *
 * Panelde girilen baglanti uc bicimde olabilir:
 *   /tema/<dosya>        temadaki assets/katalog/<dosya> (varsayilan dosyalar)
 *   /wp-content/uploads/ Ortam Kutuphanesi'ne yuklenmis dosya
 *   https://...          dis adres
 */

defined( 'ABSPATH' ) || exit;

/**
 * Baglantiyi acilabilir adrese, bulunabiliyorsa diskteki yoluna cozer.
 *
 * @return array{url: string, path: string, ext: string}
 */
function kocist_katalog_file( string $url ): array {
	$url = trim( $url );

	/*
	 * Panel url alanini kaydederken semasiz degere http:// ekleyebiliyor;
	 * 'http://tema/...' de temadaki dosya sayilir.
	 */
	if ( preg_match( '#^(?:https?://)?/?tema/(.+)$#', $url, $match ) ) {
		$relative = 'assets/katalog/' . ltrim( str_replace( '..', '', $match[1] ), '/' );
		$url      = get_theme_file_uri( $relative );
		$path     = get_theme_file_path( $relative );
	} else {
		$url  = '' === $url ? '' : kocist_link( $url );
		$id   = $url ? attachment_url_to_postid( $url ) : 0;
		$path = $id ? (string) get_attached_file( $id ) : '';
	}

	$ext = strtolower( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	return array(
		'url'  => $url,
		'path' => $path && file_exists( $path ) ? $path : '',
		'ext'  => $ext,
	);
}

/**
 * Hangi sekme acik? Adres ?tab=belgeler ise belgeler, degilse sertifikalar.
 * Betik yokken de sekme baglantilari calissin diye sunucuda secilir.
 */
function kocist_katalog_active_tab(): string {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	return 'belgeler' === $tab ? 'belgeler' : 'sertifikalar';
}


/**
 * /katalog/ ile /belgelerimiz/ ayni belgeleri gosteriyordu (cift icerik).
 * Ziyaretci ve arama motoru tek adres olan /belgelerimiz/ sayfasina 301 ile
 * gider; panel onizlemesi sayfayi gormeye devam eder.
 */
add_action( 'template_redirect', 'kocist_katalog_redirect' );
function kocist_katalog_redirect(): void {
	if ( ! is_page( 'katalog' ) || ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
		return;
	}

	wp_safe_redirect( home_url( '/belgelerimiz/' ), 301 );
	exit;
}
