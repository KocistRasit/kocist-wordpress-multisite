<?php
/**
 * Tema kurulumu: tema etkinlestirilince sitenin eksiksiz acilmasi icin
 * gereken her seyi bir kez yapar. Ayri bir seed betigi gerekmez.
 *
 * - Sayfalar: Anasayfa, Hakkimizda, Urunlerimiz (ve her urun icin alt
 *   sayfa), Blog, Iletisim. Adres adi varsa dokunulmaz.
 * - Okuma ayarlari: ana sayfa Anasayfa, yazilar sayfasi Blog. Yalnizca
 *   henuz ayarlanmamissa yazilir; kullanicinin secimi ezilmez.
 * - Ornek blog yazilari ve kapak gorselleri: yalnizca ilk kurulumda, bir
 *   kez. Kullanici silerse geri gelmez (ik_demo_posts_done).
 *
 * Tetikleme: after_switch_theme (etkinlestirmeden sonraki ilk istekte,
 * sorgudan once) ve admin_init. Surum option'i (ik_setup_version) sayesinde
 * ayni surum ikinci kez calismaz.
 *
 * Surum 2: Sik Sorulan Sorular sayfasi (/sss/). Kurulu sitede (surum 1)
 * yalnizca bu sayfa acilir; kurulumun geri kalani yeniden calismaz.
 *
 * Surum 3: rehber yazilari (content/blog-posts.php'ye sonradan eklenenler)
 * kurulu siteye de eklenir; "Istanbul Kereste Fiyatlari" yazisi, metni ilk
 * kurulumdaki haliyle duruyorsa yeni metne gecer. Elle duzenlenmis yaziya
 * dokunulmaz.
 *
 * Surum 4: Catalca kereste deposu sayfasi (/catalca-kereste-deposu/).
 */

defined( 'ABSPATH' ) || exit;

const IK_SETUP_VERSION = 4;

add_action( 'after_switch_theme', 'ik_run_setup' );
add_action( 'admin_init', 'ik_maybe_setup' );

function ik_maybe_setup(): void {
	// Yedek yol yalnizca yonetici ya da WP-CLI icin: admin_init anonim
	// admin-post.php (form) isteklerinde de tetiklenir; ziyaretci kurulumu
	// (sayfa acma, kalici baglanti, rewrite) baslatamasin.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$installed = (int) get_option( 'ik_setup_version' );

	if ( $installed >= IK_SETUP_VERSION ) {
		return;
	}

	if ( $installed >= 1 ) {
		// Kurulu site: yalnizca sonradan eklenenler.
		if ( $installed < 2 ) {
			ik_setup_page( 'sss', 'Sık Sorulan Sorular' );
		}

		if ( $installed < 3 ) {
			ik_setup_posts();
			ik_setup_refresh_price_post();
		}

		if ( $installed < 4 ) {
			ik_setup_page( 'catalca-kereste-deposu', 'Çatalca Kereste Deposu' );
		}

		update_option( 'ik_setup_version', IK_SETUP_VERSION );
		return;
	}

	ik_run_setup();
}

function ik_run_setup(): void {
	// Ayni istekte iki kanca birden tetiklenirse ikinci kez calisma.
	static $done = false;

	if ( $done ) {
		return;
	}

	$done = true;

	$pages = array(
		'anasayfa'    => 'Anasayfa',
		'hakkimizda'  => 'Hakkımızda',
		'urunlerimiz' => 'Ürünlerimiz',
		'blog'        => 'Blog',
		'iletisim'    => 'İletişim',
		'sss'         => 'Sık Sorulan Sorular',
		'catalca-kereste-deposu' => 'Çatalca Kereste Deposu',
	);

	$ids = array();

	foreach ( $pages as $slug => $title ) {
		$ids[ $slug ] = ik_setup_page( $slug, $title );
	}

	if ( $ids['urunlerimiz'] ) {
		foreach ( nwcs_rows( 'products', 'catalog', 'items' ) as $row ) {
			$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );

			if ( '' !== $slug ) {
				ik_setup_page( $slug, (string) ( $row['title'] ?? $slug ), $ids['urunlerimiz'] );
			}
		}
	}

	// Okuma ayarlari: yalnizca henuz statik sayfa secilmemisse.
	if ( 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) {
		if ( $ids['anasayfa'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['anasayfa'] );
		}
	}

	if ( ! get_option( 'page_for_posts' ) && $ids['blog'] ) {
		update_option( 'page_for_posts', $ids['blog'] );
	}

	if ( ! get_option( 'ik_demo_posts_done' ) ) {
		ik_setup_demo_posts();
		update_option( 'ik_demo_posts_done', 1 );
	}

	update_option( 'ik_setup_version', IK_SETUP_VERSION );
}

/**
 * Sayfayi olusturur; ayni yolda sayfa varsa dokunmaz. Not: get_page_by_path
 * cop kutusundaki sayfayi bulmaz (WordPress cope atilan sayfanin adresine
 * '__trashed' ekler); o durumda yeni sayfa acilir. 'trash' kontrolu yalnizca
 * bu eki almamis eski kayitlar icin.
 */
function ik_setup_page( string $slug, string $title, int $parent = 0 ): int {
	$path = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
	$page = get_page_by_path( $path, OBJECT, 'page' );

	if ( $page ) {
		return 'trash' === $page->post_status ? 0 : (int) $page->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_name'   => $slug,
			'post_title'  => $title,
			'post_parent' => $parent,
			'post_status' => 'publish',
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Ornek blog yazilari (basliklar ve ozetler eski siteden) ve kapaklari.
 *
 * Adres adi zaten varsa yazi yeniden olusturulmaz. WordPress'in kendi
 * "Merhaba dunya" yazisi, listede gorunmesin diye taslaga alinir.
 */
function ik_setup_demo_posts(): void {
	$hello = get_page_by_path( 'merhaba-dunya', OBJECT, 'post' ) ?: get_page_by_path( 'hello-world', OBJECT, 'post' );

	if ( $hello && 'publish' === $hello->post_status ) {
		wp_update_post( array( 'ID' => $hello->ID, 'post_status' => 'draft' ) );
	}

	ik_setup_posts();
}

/**
 * content/blog-posts.php'deki yazilardan sitede olmayanlari acar. Adres adi
 * (cop kutusundakiler dahil) varsa yazi yeniden olusturulmaz.
 */
function ik_setup_posts(): void {
	// Metinler content/blog-posts.php'de; panel de ayni dosyadan okur.
	$posts = (array) include get_theme_file_path( 'content/blog-posts.php' );

	foreach ( $posts as $post ) {
		$existing = get_posts(
			array(
				'name'           => $post['slug'],
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( $existing ) {
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_name'    => $post['slug'],
				'post_title'   => $post['title'],
				'post_excerpt' => $post['excerpt'],
				'post_content' => ik_setup_paragraphs( $post['body'] ),
				'post_status'  => 'publish',
				'post_date'    => $post['date'],
				'meta_input'   => array( '_ik_demo' => 1 ),
			)
		);

		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}

		$media = ik_setup_media( ...$post['image'] );

		if ( $media ) {
			set_post_thumbnail( $id, $media );
		}
	}
}

/**
 * Yazi metnini HTML'e cevirir: paragraf, ara baslik, liste ve tablo
 * (functions.php: ik_rich_html).
 */
function ik_setup_paragraphs( string $text ): string {
	return ik_rich_html( $text );
}

/**
 * "Istanbul Kereste Fiyatlari" yazisi ilk kurulumda 75 kelimelik bir girisle
 * acilmisti; tek rakam bile icermiyordu. Yazi hala o metinle duruyorsa (baslik,
 * ozet ve metin birebir) yeni metne gecer. Yonetimden ya da panelden
 * degistirilmis yaziya dokunulmaz. Yayin tarihi korunur; guncelleme tarihi
 * WordPress tarafindan yazilir.
 */
function ik_setup_refresh_price_post(): void {
	$old = array(
		'title'   => 'İstanbul Kereste Fiyatları Nedir?',
		'excerpt' => 'Kereste tüm Türkiye genelinde oldukça yaygın kullanım alanına sahip bir üründür. İstanbul’da kereste fiyatları hakkında güncel bilgileri bu yazıda derledik.',
		'body'    => "Kereste tüm Türkiye genelinde oldukça yaygın kullanım alanına sahip bir üründür. İstanbul içerisinde kereste fiyatları hakkında güncel bilgileri derlediğimiz bu yazımızda sizlere güncel kereste fiyatları hakkında bilgiler derledik.\n\nKereste pek çok alanda kullanıma uygun bir ürün olmasından ötürü kolay işleme alınarak hazırlanabilmektedir. Uygun fiyatlı olan bu keresteler mobilya, dekorasyon ve inşaat sektörlerinin kullanım alanlarında önemli bir yere sahiptir.",
	);

	$found = get_posts(
		array(
			'name'           => 'istanbul-kereste-fiyatlari',
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);

	if ( ! $found ) {
		return;
	}

	$post = $found[0];
	$text = static fn( string $html ): string => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );

	if ( $post->post_title !== $old['title'] || $text( $post->post_content ) !== $text( ik_rich_html( $old['body'] ) ) ) {
		return;
	}

	foreach ( (array) include get_theme_file_path( 'content/blog-posts.php' ) as $new ) {
		if ( 'istanbul-kereste-fiyatlari' !== $new['slug'] ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_title'   => $new['title'],
				'post_excerpt' => $post->post_excerpt === $old['excerpt'] ? $new['excerpt'] : $post->post_excerpt,
				'post_content' => ik_setup_paragraphs( $new['body'] ),
			)
		);
	}
}

/**
 * Tema icindeki fotografi medya kutuphanesine bir kez ekler.
 *
 * Ayni dosya ikinci kez yuklenmez: ek kaydi _ik_asset meta anahtariyla
 * isaretlenir.
 */
function ik_setup_media( string $file, string $title, string $alt ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_ik_asset', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = get_theme_file_path( 'assets/img/' . $file );

	if ( ! file_exists( $source ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_upload_bits( 'ik-' . $file, null, (string) file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}

	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_ik_asset', $file );

	return (int) $id;
}
