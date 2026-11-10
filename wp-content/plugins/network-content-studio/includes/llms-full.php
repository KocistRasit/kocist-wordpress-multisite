<?php
/**
 * /llms-full.txt: llms.txt'nin tam metinli bicimi (https://llmstxt.org/).
 *
 * Icerik: llms.txt'deki her sey, ardindan
 * - bu sitede sayfasi olan urunlerin uzun aciklamalari (ayni metni paylasan
 *   renk ve olculer tek baslik altinda, her birinin adresi ve fiyatiyla),
 * - yayindaki blog yazilarinin tam metni.
 *
 * Yeni bilgi uretilmez: metinler urun havuzundan ve yazilardan oldugu gibi,
 * HTML'siz alinir. Her istekte icerikten uretilir; elle guncellenmesi gerekmez.
 * Arama motorlarina kapali sitede 404 verir.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'parse_request', 'nwcs_llms_full_maybe_serve' );
function nwcs_llms_full_maybe_serve( WP $wp ): void {
	if ( 'llms-full.txt' !== $wp->request || empty( nwcs_manifest()['pages'] ) ) {
		return;
	}

	if ( ! get_option( 'blog_public' ) ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );

	echo nwcs_llms_full_text(); // phpcs:ignore WordPress.Security.EscapingOutput -- duz metin; Markdown.
	exit;
}

/**
 * llms-full.txt icerigi.
 */
function nwcs_llms_full_text(): string {
	$lines = array( rtrim( nwcs_seo_llms_text() ) );
	$lines = array_merge( $lines, nwcs_llms_full_product_lines(), nwcs_llms_full_post_lines() );

	return implode( "\n", $lines ) . "\n";
}

/**
 * Urun aciklamalari. Ayni uzun metni paylasan urunler (renkler) tek baslik
 * altinda toplanir; her birinin adi, adresi ve sitedeki fiyati yazilir.
 *
 * @return string[]
 */
function nwcs_llms_full_product_lines(): array {
	if ( ! function_exists( 'nwcs_site_products' ) || ! function_exists( 'nwcs_product_feed_long_text' ) ) {
		return array();
	}

	$groups = array();

	foreach ( nwcs_site_products() as $product ) {
		if ( ! nwcs_product_has_page( $product ) ) {
			continue;
		}

		$text = trim( nwcs_product_feed_long_text( (string) ( $product['body'] ?? '' ) ) );

		if ( '' === $text ) {
			continue;
		}

		$key = md5( $text );

		$groups[ $key ]['text']    = $text;
		$groups[ $key ]['items'][] = $product;
	}

	if ( ! $groups ) {
		return array();
	}

	$lines = array( '', '## Ürün açıklamaları', '' );

	foreach ( $groups as $group ) {
		$lines[] = '### ' . nwcs_seo_clean( (string) $group['items'][0]['title'] );
		$lines[] = '';

		foreach ( $group['items'] as $item ) {
			$price = ! empty( $item['has_price'] ) ? nwcs_seo_clean( (string) $item['price'] ) : 'teklifle';

			$lines[] = sprintf( '- [%s](%s): Fiyat %s', nwcs_seo_clean( (string) $item['title'] ), esc_url_raw( (string) $item['url'] ), $price );
		}

		$lines[] = '';
		$lines[] = $group['text'];
		$lines[] = '';
	}

	return $lines;
}

/**
 * Yayindaki blog yazilarinin tam metni, en yeniden eskiye.
 *
 * @return string[]
 */
function nwcs_llms_full_post_lines(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$lines = array();

	foreach ( $posts as $post ) {
		$text = trim( nwcs_product_feed_long_text( nwcs_llms_full_post_html( $post ) ) );

		if ( '' === $text ) {
			continue;
		}

		if ( ! $lines ) {
			$lines = array( '', '## Blog yazıları', '' );
		}

		$lines[] = sprintf( '### [%s](%s)', nwcs_seo_clean( get_the_title( $post ) ), get_permalink( $post ) );
		$lines[] = '';
		$lines[] = 'Son güncelleme: ' . get_the_modified_date( 'Y-m-d', $post );
		$lines[] = '';
		$lines[] = $text;
		$lines[] = '';
	}

	return $lines;
}

/**
 * Yazinin sayfada gorunen govdesi (the_content). Tema metni donguyken
 * uyguladigi icin yazi gecici olarak donguye alinir (nwcs_seo_post_text gibi).
 */
function nwcs_llms_full_post_html( WP_Post $post ): string {
	global $wp_query;

	$saved_post = $GLOBALS['post'] ?? null;
	$saved_loop = $wp_query instanceof WP_Query ? $wp_query->in_the_loop : false;

	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- asagida geri alinir.
	setup_postdata( $post );

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->in_the_loop = true;
	}

	$html = (string) apply_filters( 'the_content', $post->post_content );

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->in_the_loop = $saved_loop;
	}

	$GLOBALS['post'] = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $saved_post instanceof WP_Post ) {
		setup_postdata( $saved_post );
	}

	return $html;
}
