<?php
/**
 * Gorsel site haritasi: /image-sitemap.xml (yalnizca Kocist sitesi)
 *
 * WordPress'in kendi site haritasi <image:image> yazamaz (yalnizca loc,
 * lastmod, changefreq, priority). Bu yuzden ayri bir dosya: temanin
 * 'sitemap' => true isaretledigi ek sayfalar (urun ve kategori sayfalari)
 * ana gorselleriyle, yazilar one cikan gorselleriyle listelenir. Adres
 * robots.txt'ye "Sitemap:" satiri olarak eklenir.
 *
 * Icerikten her istekte uretilir: panelde gorsel degisince harita da
 * kendiliginden degisir. Arama motorlarina kapali sitede 404 doner.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_IMAGE_SITEMAP = 'image-sitemap.xml';

/**
 * Yalnizca Kocist (kocist.com.tr, kocist-theme). Diger ag sitelerinde ne
 * dosya ne robots.txt satiri olur.
 */
function nwcs_image_sitemap_enabled(): bool {
	return 'kocist-theme' === (string) get_option( 'stylesheet' ) && ! empty( nwcs_manifest()['pages'] );
}

add_action( 'parse_request', 'nwcs_image_sitemap_maybe_serve' );
function nwcs_image_sitemap_maybe_serve( WP $wp ): void {
	if ( NWCS_IMAGE_SITEMAP !== $wp->request || ! nwcs_image_sitemap_enabled() ) {
		return;
	}

	if ( ! get_option( 'blog_public' ) ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );

	echo nwcs_image_sitemap_xml(); // phpcs:ignore WordPress.Security.EscapingOutput -- XML asagida kacislanir.
	exit;
}

/**
 * robots.txt: gorsel site haritasinin adresi (site aciksa ve manifest varsa).
 */
add_filter( 'robots_txt', 'nwcs_image_sitemap_robots', 20, 2 );
function nwcs_image_sitemap_robots( $output, $public ) {
	if ( ! $public || ! nwcs_image_sitemap_enabled() ) {
		return $output;
	}

	$line = 'Sitemap: ' . home_url( '/' . NWCS_IMAGE_SITEMAP );

	return str_contains( (string) $output, $line ) ? $output : rtrim( (string) $output ) . "\n" . $line . "\n";
}

/**
 * Gorsel adresini sitenin kendi adresine cevirir: ag sitelerinin yuklemeleri
 * ayni wp-content/uploads klasorunden her alan adinda sunulur; panel adresi
 * ya da http ile verilmis gorsel sitenin https adresine tasinir.
 */
function nwcs_image_sitemap_url( string $url ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( '' !== $path && preg_match( '#/wp-content/(uploads|themes|plugins)/#', $path ) ) {
		// Alt klasorlu sitede de (/woodkocist/) dosya alan adinin kokundeki wp-content'ten sunulur.
		$host     = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$relative = (string) preg_replace( '#^.*?(/wp-content/)#', '$1', $path );

		return 'https://' . $host . $relative;
	}

	return set_url_scheme( $url, 'https' );
}

/**
 * Haritadaki satirlar: sayfa adresi => gorsel adresleri.
 *
 * @return array<string, string[]>
 */
function nwcs_image_sitemap_rows(): array {
	$rows = array();

	foreach ( nwcs_seo_extra_pages() as $page ) {
		$loc = ! empty( $page['sitemap'] ) ? esc_url_raw( (string) $page['url'] ) : '';

		if ( '' === $loc || empty( $page['image'] ) ) {
			continue;
		}

		$image = nwcs_seo_image_any( $page['image'] );
		$src   = nwcs_image_sitemap_url( (string) ( $image['url'] ?? '' ) );

		if ( '' !== $src && ! in_array( $src, $rows[ $loc ] ?? array(), true ) ) {
			$rows[ $loc ][] = $src;
		}
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post_id ) {
		$src = nwcs_image_sitemap_url( (string) get_the_post_thumbnail_url( $post_id, 'full' ) );
		$loc = (string) get_permalink( $post_id );

		if ( '' !== $src && '' !== $loc ) {
			$rows[ $loc ][] = $src;
		}
	}

	return $rows;
}

/**
 * XML metni (Google gorsel site haritasi bicimi).
 */
function nwcs_image_sitemap_xml(): string {
	$out = array(
		'<?xml version="1.0" encoding="UTF-8"?>',
		'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">',
	);

	foreach ( nwcs_image_sitemap_rows() as $loc => $images ) {
		$out[] = '<url><loc>' . esc_xml( esc_url( $loc ) ) . '</loc>';

		foreach ( array_slice( array_values( array_unique( $images ) ), 0, 1000 ) as $src ) {
			$out[] = '<image:image><image:loc>' . esc_xml( esc_url( $src ) ) . '</image:loc></image:image>';
		}

		$out[] = '</url>';
	}

	$out[] = '</urlset>';

	return implode( "\n", $out ) . "\n";
}
