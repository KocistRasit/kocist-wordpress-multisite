<?php
/**
 * Arama ve yapay zeka gorunurlugu icin tema ekleri. Ortak eklentiye
 * (network-content-studio) dokunmadan yalnizca bu sitede calisir.
 *
 * - SSS isareti (FAQPage): urun sayfalarindaki sorular (content/product-extras.php)
 *   ve yonetimden yazilan sayfalarin "Sik sorulan sorular" bolumu (ilce
 *   sayfalari gibi). Eklentinin sayfa dugumune eklenir (nwcs_seo_graph).
 * - llms.txt: eklentinin listesine yonetimden yazilan sayfalar eklenir
 *   (eklenti yalnizca manifest ve urun sayfalarini yaziyor).
 * - IndexNow: yazi ya da sayfa yayinlaninca, guncellenince ya da kaldirilinca
 *   adres Bing ve Yandex'e hemen bildirilir. Anahtar dosyasi /<anahtar>.txt.
 */

defined( 'ABSPATH' ) || exit;

/* ======================================================================
 * SSS isareti
 * ====================================================================== */

/**
 * "[metin](/adres/)" bicimindeki baglantilari duz metne cevirir.
 */
function ik_seo_plain( string $text ): string {
	$text = (string) preg_replace( '~\[([^\]\n]+)\]\([^)\s]*\)~u', '$1', $text );

	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Sayfa HTML'indeki "Sik sorulan sorular" basligindan sonraki h3 + paragraf
 * ciftleri. Sonraki h2'de durur.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function ik_seo_faq_from_html( string $html ): array {
	if ( ! preg_match( '~<h2[^>]*>\s*Sık sorulan sorular\s*</h2>(.*?)(?=<h2|$)~isu', $html, $section ) ) {
		return array();
	}

	preg_match_all( '~<h3[^>]*>(.*?)</h3>(.*?)(?=<h3|$)~isu', $section[1], $matches, PREG_SET_ORDER );

	$items = array();

	foreach ( $matches as $match ) {
		$question = ik_seo_plain( $match[1] );
		$answer   = ik_seo_plain( $match[2] );

		if ( '' !== $question && '' !== $answer ) {
			$items[] = array( $question, $answer );
		}
	}

	return $items;
}

/**
 * Bu sayfanin sorulari: urun sayfasiysa urunun SSS'si, yonetimden yazilan
 * sayfaysa metnindeki SSS bolumu. Panelin SSS sayfasini eklenti zaten isaretler.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function ik_seo_page_faq(): array {
	$product = ik_current_product();

	if ( $product ) {
		$extras = ik_product_extras( $product['slug'] );

		return array_map(
			static fn( array $item ): array => array( ik_seo_plain( $item[0] ), ik_seo_plain( $item[1] ) ),
			$extras['faq'] ?? array()
		);
	}

	if ( ! is_page() ) {
		return array();
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post || ik_seo_is_manifest_page( $post ) ) {
		return array();
	}

	return ik_seo_faq_from_html( (string) $post->post_content );
}

/**
 * Sayfa panelde duzenlenen (manifest) bir sayfa mi?
 */
function ik_seo_is_manifest_page( WP_Post $post ): bool {
	if ( ! function_exists( 'nwcs_seo_page_for_path' ) ) {
		return false;
	}

	$path = untrailingslashit( (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH ) );

	return '' !== nwcs_seo_page_for_path( $path );
}

/*
 * Sorular eklentinin sayfa dugumune eklenir (nwcs_seo_graph): WebPage ayni
 * zamanda FAQPage olur, eklentinin SSS ve kategori sorularindaki gibi. Ayri
 * blok basilmaz; sayfa iki ayri varlik gibi gorunmesin. Sayfanin ana ogesi
 * zaten varsa (eklentinin kendi sorulari, liste) dokunulmaz.
 */
add_filter(
	'nwcs_seo_graph',
	static function ( array $graph ): array {
		if ( is_admin() || is_404() ) {
			return $graph;
		}

		$faq = ik_seo_page_faq();

		if ( ! $faq ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! str_ends_with( (string) ( $node['@id'] ?? '' ), '#webpage' ) || isset( $node['mainEntity'] ) ) {
				continue;
			}

			$graph[ $index ]['@type']      = array_values( array_unique( array_merge( (array) $node['@type'], array( 'FAQPage' ) ) ) );
			$graph[ $index ]['mainEntity'] = array_map(
				static fn( array $item ): array => array(
					'@type'          => 'Question',
					'name'           => $item[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item[1],
					),
				),
				$faq
			);
		}

		return $graph;
	}
);

/* ======================================================================
 * llms.txt: yonetimden yazilan sayfalar
 * ====================================================================== */

/**
 * Eklentinin llms.txt'sinde olmayan yayindaki sayfalar (ilce sayfalari gibi):
 * ana sayfa, blog sayfasi, panel sayfalari ve urun alt sayfalari haric.
 *
 * @return string[] Markdown satirlari
 */
function ik_seo_llms_page_lines(): array {
	$skip  = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ) );
	$shop  = get_page_by_path( 'urunlerimiz' );
	$lines = array();

	foreach ( get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) ) as $page ) {
		if ( in_array( (int) $page->ID, $skip, true ) || ( $shop && (int) $page->post_parent === (int) $shop->ID ) || ik_seo_is_manifest_page( $page ) ) {
			continue;
		}

		$text = function_exists( 'nwcs_seo_post_text' ) ? nwcs_seo_post_text( $page ) : wp_strip_all_tags( $page->post_content );
		$text = function_exists( 'nwcs_seo_clean' ) ? nwcs_seo_clean( $text, 160 ) : wp_trim_words( $text, 25 );
		$line = sprintf( '- [%s](%s)', ik_seo_plain( get_the_title( $page ) ), get_permalink( $page ) );

		$lines[] = '' !== $text ? $line . ': ' . $text : $line;
	}

	return $lines;
}

// Eklenti llms.txt'yi parse_request'te (10) yazip cikiyor; once tampon acilir,
// cikista eklenti metnine "Bolge sayfalari" bolumu eklenir.
add_action(
	'parse_request',
	static function ( WP $wp ): void {
		if ( 'llms.txt' !== $wp->request ) {
			return;
		}

		ob_start(
			static function ( string $text ): string {
				$lines = ik_seo_llms_page_lines();

				if ( ! $lines || 0 !== strpos( $text, '# ' ) ) {
					return $text;
				}

				$block = "\n## Bölge ve bilgi sayfaları\n\n" . implode( "\n", $lines ) . "\n";

				foreach ( array( "\n## Sık sorulan sorular\n", "\n## Blog\n", "\n## Ek\n" ) as $marker ) {
					$at = strpos( $text, $marker );

					if ( false !== $at ) {
						return substr( $text, 0, $at ) . $block . substr( $text, $at );
					}
				}

				return $text . $block;
			}
		);
	},
	1
);

/* ======================================================================
 * IndexNow
 * ====================================================================== */

/**
 * Sitenin IndexNow anahtari; yoksa olusturulur (32 karakter, onaltilik).
 */
function ik_indexnow_key(): string {
	$key = (string) get_option( 'ik_indexnow_key' );

	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'ik_indexnow_key', $key, false );
	}

	return $key;
}

/**
 * Bildirim yalnizca arama motorlarina acik, gercek alan adindaki sitede.
 */
function ik_indexnow_enabled(): bool {
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	return get_option( 'blog_public' )
		&& '' !== $host
		&& false !== strpos( $host, '.' )
		&& ! preg_match( '/(^|\.)(localhost|test|local|invalid|example)$/', $host )
		&& 'local' !== wp_get_environment_type();
}

// Anahtar dosyasi: /<anahtar>.txt -> anahtar.
add_action(
	'parse_request',
	static function ( WP $wp ): void {
		$key = (string) get_option( 'ik_indexnow_key' );

		if ( '' === $key || $wp->request !== $key . '.txt' ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $key );
		exit;
	},
	0
);

// Anahtar, ilk bildirimden once dosyasi hazir olsun diye yonetim acilinca olusur.
add_action(
	'admin_init',
	static function (): void {
		if ( current_user_can( 'manage_options' ) && ik_indexnow_enabled() ) {
			ik_indexnow_key();
		}
	}
);

/**
 * Istek sonunda bildirilecek adresler.
 */
function ik_indexnow_queue( string $url = '' ): array {
	static $urls = array();

	if ( '' !== $url ) {
		$urls[ $url ] = true;
	}

	return array_keys( $urls );
}

add_action(
	'transition_post_status',
	static function ( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$url = (string) get_permalink( $post );

		// Yayindan kalkan sayfanin adresi taslak bicimine doner; kalici adres kullanilir.
		if ( 'publish' !== $new_status ) {
			$url = home_url( user_trailingslashit( get_page_uri( $post ) ) );
		}

		if ( '' !== $url && ik_indexnow_enabled() ) {
			ik_indexnow_queue( $url );
		}
	},
	10,
	3
);

add_action(
	'shutdown',
	static function (): void {
		$urls = ik_indexnow_queue();

		if ( ! $urls || ! ik_indexnow_enabled() ) {
			return;
		}

		$key = ik_indexnow_key();

		wp_remote_post(
			'https://api.indexnow.org/indexnow',
			array(
				'blocking' => false,
				'timeout'  => 2,
				'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'     => wp_json_encode(
					array(
						'host'        => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
						'key'         => $key,
						'keyLocation' => home_url( '/' . $key . '.txt' ),
						'urlList'     => array_values( $urls ),
					)
				),
			)
		);
	}
);
