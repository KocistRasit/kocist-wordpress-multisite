<?php
/**
 * Rehber sayfalari: content/rehber.php'deki yazilar WordPress sayfasi olarak
 * acilir, bir de hepsini listeleyen /rehber/ sayfasi.
 *
 * - Kurulum surumlu: KK_REHBER_VERSION degisince bir kez calisir. Sayfa yoksa
 *   olusturulur; varsa ve metni son kurulumdan beri elle degistirilmemisse
 *   yeni metne gecer. Panelden ya da yonetimden duzenlenen sayfaya dokunulmaz.
 * - SSS isareti (FAQPage): eklentinin sayfa dugumune eklenir (nwcs_seo_graph),
 *   ayri blok basilmaz.
 * - llms.txt: eklenti yalnizca manifest sayfalarini yaziyor; rehber sayfalari
 *   "Rehber" bolumu olarak eklenir.
 */

defined( 'ABSPATH' ) || exit;

const KK_REHBER_VERSION = 2;
const KK_REHBER_HUB     = 'rehber';
const KK_REHBER_HUB_EXCERPT = 'Kavak kereste fiyatları, özellikleri, metreküp hesabı, palet-sandık ölçüleri, kavak çıta ve ahşap takoz çeşitleri: alım öncesi bilmeniz gerekenler.';

/**
 * @return array<int, array<string, mixed>>
 */
function kk_rehber_items(): array {
	static $items = null;

	if ( null === $items ) {
		$items = (array) include get_stylesheet_directory() . '/content/rehber.php';
	}

	return $items;
}

/**
 * Rehber yazisinin sayfa metni: tarih satiri, govde ve SSS bolumu.
 */
function kk_rehber_html( array $item ): string {
	$date = date_create( (string) $item['updated'] );
	$html = '<p class="text-base opacity-75">Son güncelleme: ' . esc_html( $date ? kk_rehber_date( $date ) : (string) $item['updated'] ) . ' · Koçist Orman Ürünleri</p>' . "\n\n";
	$html .= trim( (string) $item['body'] );

	if ( ! empty( $item['faq'] ) ) {
		$html .= "\n\n<h2>Sık sorulan sorular</h2>\n";

		foreach ( $item['faq'] as $qa ) {
			$html .= '<h3>' . esc_html( $qa[0] ) . "</h3>\n<p>" . esc_html( $qa[1] ) . "</p>\n";
		}
	}

	$others = array_filter( kk_rehber_items(), static fn( array $other ): bool => $other['slug'] !== $item['slug'] );

	if ( $others ) {
		$html .= "\n<h2>Diğer rehberler</h2>\n<ul>\n";

		foreach ( $others as $other ) {
			$html .= '<li><a href="/' . esc_attr( $other['slug'] ) . '/">' . esc_html( $other['title'] ) . "</a></li>\n";
		}

		$html .= "</ul>\n";
	}

	return $html;
}

/**
 * Turkce tarih: 10 Kasım 2026.
 */
function kk_rehber_date( DateTimeInterface $date ): string {
	$months = array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );

	return $date->format( 'j' ) . ' ' . $months[ (int) $date->format( 'n' ) - 1 ] . ' ' . $date->format( 'Y' );
}

/**
 * /rehber/ sayfasinin metni: tum yazilar, ozetleriyle.
 */
function kk_rehber_hub_html(): string {
	$html = "<p>Kavak kereste, çıta ve takoz alırken en çok sorulanlar: güncel metreküp fiyatı, ağacın özellikleri, hacim hesabı, palet ya da sandık için doğru ölçü ve hangi takozun ne işe yaradığı. Her rehber, Koçist Orman Ürünleri’nin Çatalca deposundaki bilgilere dayanır.</p>\n<ul>\n";

	foreach ( kk_rehber_items() as $item ) {
		$html .= '<li><a href="/' . esc_attr( $item['slug'] ) . '/"><strong>' . esc_html( $item['title'] ) . '</strong></a><br>' . esc_html( $item['excerpt'] ) . "</li>\n";
	}

	return $html . "</ul>\n<p>Aradığınız bilgi burada yoksa <a href=\"/sik-sorulan-sorular/\">sık sorulan sorulara</a> bakın ya da <a href=\"/iletisim/#teklif\">bize yazın</a>.</p>\n";
}

/**
 * Bir sayfayi olusturur ya da (elle degistirilmemisse) gunceller.
 */
function kk_rehber_upsert( string $slug, string $title, string $excerpt, string $html, int $order ): void {
	$hashes = (array) get_option( 'kk_rehber_hashes', array() );
	$page   = get_page_by_path( $slug, OBJECT, 'page' );
	$data   = array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_name'      => $slug,
		'post_title'     => $title,
		'post_excerpt'   => $excerpt,
		'post_content'   => $html,
		'menu_order'     => $order,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);

	if ( $page instanceof WP_Post ) {
		$seeded = $hashes[ $slug ] ?? '';

		// Ayni metinle elle acilmis sayfa (2026-11-10'da yonetimden acildi):
		// bizim sayilir, sonraki surumlerde guncellenebilsin.
		if ( '' === $seeded && md5( (string) $page->post_content ) === md5( $html ) ) {
			$hashes[ $slug ] = md5( $html );
			update_option( 'kk_rehber_hashes', $hashes, false );

			return;
		}

		// Elle duzenlenmis sayfaya dokunma.
		if ( '' === $seeded || md5( (string) $page->post_content ) !== $seeded ) {
			return;
		}

		$data['ID'] = $page->ID;
		$id         = wp_update_post( wp_slash( $data ), true );
	} else {
		$id = wp_insert_post( wp_slash( $data ), true );
	}

	if ( is_wp_error( $id ) || ! $id ) {
		return;
	}

	$saved           = get_post( (int) $id );
	$hashes[ $slug ] = $saved ? md5( (string) $saved->post_content ) : '';
	update_option( 'kk_rehber_hashes', $hashes, false );
}

/**
 * Surum degistiyse rehber sayfalarini kurar.
 */
function kk_rehber_setup(): void {
	if ( (int) get_option( 'kk_rehber_version' ) >= KK_REHBER_VERSION ) {
		return;
	}

	// Ayni anda iki istek kurmasin.
	if ( get_transient( 'kk_rehber_lock' ) ) {
		return;
	}
	set_transient( 'kk_rehber_lock', 1, 60 );

	kk_rehber_upsert( KK_REHBER_HUB, 'Kavak Kereste Rehberi', KK_REHBER_HUB_EXCERPT, kk_rehber_hub_html(), 90 );

	foreach ( kk_rehber_items() as $index => $item ) {
		kk_rehber_upsert( (string) $item['slug'], (string) $item['title'], (string) $item['excerpt'], kk_rehber_html( $item ), 91 + (int) $index );
	}

	update_option( 'kk_rehber_version', KK_REHBER_VERSION, false );
	delete_transient( 'kk_rehber_lock' );
}
add_action( 'init', 'kk_rehber_setup', 20 );

// Sayfa ozeti arama aciklamasi olarak kullanilir.
add_action(
	'init',
	static function (): void {
		add_post_type_support( 'page', 'excerpt' );
	}
);

/**
 * Su anki sayfa bir rehber yazisiysa onun verisi.
 */
function kk_rehber_current(): array {
	if ( ! is_page() ) {
		return array();
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	foreach ( kk_rehber_items() as $item ) {
		if ( $item['slug'] === $post->post_name ) {
			return $item;
		}
	}

	return array();
}

/*
 * SSS isareti: WebPage ayni zamanda FAQPage olur. Sayfanin ana ogesi zaten
 * varsa dokunulmaz.
 */
add_filter(
	'nwcs_seo_graph',
	static function ( array $graph ): array {
		if ( is_admin() || is_404() ) {
			return $graph;
		}

		$item = kk_rehber_current();

		if ( empty( $item['faq'] ) ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! str_ends_with( (string) ( $node['@id'] ?? '' ), '#webpage' ) || isset( $node['mainEntity'] ) ) {
				continue;
			}

			$graph[ $index ]['@type']        = array_values( array_unique( array_merge( (array) $node['@type'], array( 'FAQPage' ) ) ) );
			$graph[ $index ]['dateModified'] = (string) $item['updated'];
			$graph[ $index ]['mainEntity']   = array_map(
				static fn( array $qa ): array => array(
					'@type'          => 'Question',
					'name'           => $qa[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $qa[1],
					),
				),
				$item['faq']
			);
		}

		return $graph;
	}
);

/*
 * llms.txt: eklenti metni parse_request'te (10) yazip cikiyor; once tampon
 * acilir, cikista "Rehber" bolumu eklenir.
 */
add_action(
	'parse_request',
	static function ( WP $wp ): void {
		if ( 'llms.txt' !== $wp->request ) {
			return;
		}

		ob_start(
			static function ( string $text ): string {
				if ( 0 !== strpos( $text, '# ' ) ) {
					return $text;
				}

				$lines = array();

				foreach ( kk_rehber_items() as $item ) {
					if ( get_page_by_path( (string) $item['slug'], OBJECT, 'page' ) ) {
						$lines[] = sprintf( '- [%s](%s): %s', $item['title'], home_url( '/' . $item['slug'] . '/' ), $item['excerpt'] );
					}
				}

				if ( ! $lines ) {
					return $text;
				}

				$block = "\n## Rehber\n\n" . implode( "\n", $lines ) . "\n";

				foreach ( array( "\n## Sık sorulan sorular\n", "\n## Kardeş sitelerimiz\n" ) as $marker ) {
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
