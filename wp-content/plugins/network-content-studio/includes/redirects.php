<?php
/**
 * Eski adres yonlendirmeleri (301) ve kaldirilan adresler (410).
 *
 * Siteler yeni sunucuya tasinirken eski sitenin adresleri Google'da ve
 * baska sitelerdeki baglantilarda yasamaya devam eder. Her site kendi
 * listesini tutar (site secenegi 'nwcs_redirects', duz metin); liste ag
 * yonetiminde Gelistirici -> Yonlendirmeler sayfasindan duzenlenir.
 *
 * Satir bicimi (yollar sitenin kokune gore; alan adi degisse de gecerli):
 *
 *   /eski/yol/          /yeni/yol/     301 kalici yonlendirme
 *   /tag/*              /blog/         onekle eslesen butun adresler
 *   /portfolio-item/*   410            kaldirildi (Google dizinden cikarir)
 *   # aciklama satiri
 *
 * Guvenlik: kural yalnizca istek 404 olacaksa (ya da yalnizca bir gorselin
 * ek sayfasina denk geliyorsa) devreye girer; var olan hicbir sayfayi ezemez.
 * Hedef kendine donuyorsa atlanir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_REDIRECTS_OPTION = 'nwcs_redirects';

/**
 * Yol bicimi: basinda ve sonunda egik cizgi, kucuk harf, kodlama cozulmus.
 */
function nwcs_redirect_normalize_path( string $path ): string {
	$path = rawurldecode( (string) wp_parse_url( $path, PHP_URL_PATH ) );
	$path = '/' . trim( $path, '/' );

	return mb_strtolower( '/' === $path ? '/' : $path . '/' );
}

/**
 * Duz metni kurallara ayirir. Hatali satirlar 'errors' listesine duser.
 *
 * @return array{rules: array<int, array{from:string, prefix:bool, to:string, gone:bool, line:int}>, errors: array<int, string>}
 */
function nwcs_redirects_parse( string $text ): array {
	$rules  = array();
	$errors = array();

	foreach ( preg_split( '/\R/u', $text ) as $index => $raw ) {
		$line = trim( (string) preg_replace( '/\s+#.*$/u', '', $raw ) );

		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}

		$parts = preg_split( '/\s+/u', $line );

		if ( 2 !== count( $parts ) || ! str_starts_with( $parts[0], '/' ) ) {
			$errors[] = sprintf( '%d. satır anlaşılmadı: "%s". Satıra "/" ile başlayan eski adresi, bir boşluk ve yeni adresi ya da 410 yazın (ör. /eski/ /yeni/ veya /eski/ 410). Bu satır düzeltilene kadar çalışmaz.', $index + 1, $raw );
			continue;
		}

		$from   = $parts[0];
		$prefix = str_ends_with( $from, '*' );
		$gone   = '410' === $parts[1];
		$to     = $gone ? '' : $parts[1];

		if ( ! $gone && ! str_starts_with( $to, '/' ) && ! wp_http_validate_url( $to ) ) {
			$errors[] = sprintf( '%d. satırdaki yeni adres anlaşılmadı. Yeni adres "/" ile başlamalı (ör. /urunler/), tam bir adres olmalı (ör. https://ornek.com/) ya da 410 yazılmalı. Bu satır düzeltilene kadar çalışmaz.', $index + 1 );
			continue;
		}

		$rules[] = array(
			// Onek kurali sona egik cizgi almaz: /country* hem /country/... hem
			// /country-category/... adreslerine uyar.
			'from'   => $prefix ? mb_strtolower( '/' . ltrim( rawurldecode( rtrim( $from, '*' ) ), '/' ) ) : nwcs_redirect_normalize_path( $from ),
			'prefix' => $prefix,
			'to'     => $to,
			'gone'   => $gone,
			'line'   => $index + 1,
		);
	}

	return array(
		'rules'  => $rules,
		'errors' => $errors,
	);
}

/**
 * Istek yoluna uyan kural: once birebir, sonra en uzun onek.
 */
function nwcs_redirect_match( string $path, array $rules ): ?array {
	foreach ( $rules as $rule ) {
		if ( ! $rule['prefix'] && $rule['from'] === $path ) {
			return $rule;
		}
	}

	$best = null;

	foreach ( $rules as $rule ) {
		if ( $rule['prefix'] && str_starts_with( $path, $rule['from'] ) && ( ! $best || strlen( $rule['from'] ) > strlen( $best['from'] ) ) ) {
			$best = $rule;
		}
	}

	return $best;
}

/**
 * Isteğin site kokune gore yolu (/sanayi-palet/urunler/ -> /urunler/).
 */
function nwcs_redirect_request_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida yol olarak normalize edilir.
	$path = nwcs_redirect_normalize_path( (string) $uri );
	$home = nwcs_redirect_normalize_path( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

	if ( '/' !== $home && str_starts_with( $path, $home ) ) {
		$path = '/' . substr( $path, strlen( $home ) );
	}

	return $path;
}

/**
 * 404 olacak istegi listeye gore yonlendirir ya da 410 yapar. WordPress'in
 * kendi "benzer adresi tahmin et" yonlendirmesinden (oncelik 10) once calisir.
 */
add_action( 'template_redirect', 'nwcs_redirects_apply', 1 );
function nwcs_redirects_apply(): void {
	// Ek (gorsel) sayfalari da gercek icerik sayilmaz: eski /urunler/ahsap-palet/
	// adresi, ayni adli bir gorselin ek sayfasina denk gelip resim dosyasina
	// gidiyordu. Kural varsa o kazanir.
	if ( ! is_404() && ! is_attachment() ) {
		return;
	}

	$text = (string) get_option( NWCS_REDIRECTS_OPTION, '' );

	if ( '' === trim( $text ) ) {
		return;
	}

	$path = nwcs_redirect_request_path();
	$rule = nwcs_redirect_match( $path, nwcs_redirects_parse( $text )['rules'] );

	if ( ! $rule ) {
		return;
	}

	if ( $rule['gone'] ) {
		status_header( 410 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex' );
		return; // 404 sablonu 410 durumuyla cizilir.
	}

	$target = str_starts_with( $rule['to'], '/' ) ? home_url( $rule['to'] ) : $rule['to'];

	// Kendine donen kural dongu yaratmasin.
	if ( nwcs_redirect_normalize_path( $target ) === nwcs_redirect_normalize_path( home_url( $path ) ) ) {
		return;
	}

	wp_redirect( $target, 301, 'Network Content Studio' ); // phpcs:ignore WordPress.Security.SafeRedirect -- hedef yonetici tarafindan girilir; baska alan adina (ornegin eski firma sitesi) da gidebilir.
	exit;
}
