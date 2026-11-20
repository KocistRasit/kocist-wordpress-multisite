<?php
/**
 * Kurulum: tema bir siteye ilk kez atandiginda siteyi hazirlar.
 *
 * Canliya tasima veritabani aktarmadan yapilir: cPanel'de Git ile tema
 * gelir, Ag Yonetimi'nde yeni site acilip bu tema secilir; ilk yonetim
 * sayfasi acilisinda (ya da tema etkinlesince) bu dosya:
 *   - menudeki sayfalari ve uc urun sayfasini (/civiler/<urun>/) acar,
 *   - eski sitenin 4 blog yazisini ayni adres ve tarihle ekler,
 *   - on sayfa / blog sayfasi ve kalici baglanti ayarlarini yapar,
 *   - WordPress'in ornek yazi ve sayfasini kaldirir,
 *   - eski sitenin demo adresleri icin yonlendirme listesini yazar (bossa).
 *
 * Idempotent: var olan sayfaya, yaziya ve dolu yonlendirme listesine
 * dokunmaz. Surum numarasi degisirse eksikleri tamamlamak icin bir kez
 * daha calisir.
 *
 * Surum 2: Sik Sorulan Sorular sayfasi (/sss/). Kurulu sitede (surum 1)
 * yalnizca bu sayfa acilir; kurulumun geri kalani yeniden calismaz.
 *
 * Surum 3: katalog (cesit bazli urunler, inc/catalog.php). Kurulu sitede
 * manifeste sonradan eklenen civi tipi sayfalari (/civiler/<tip>/) acilir ve
 * eski sitenin /service/... adresleri yonlendirme listesine eklenir (yoksa).
 * Cesit sayfalari WordPress sayfasi degildir; yedek cizimle 200 doner.
 *
 * Surum 4: Civi Tabancalari tipi (/civiler/civi-tabancalari/) ve Sertel
 * tabanca modelleri katalogda; kurulu sitede yalnizca yeni tip sayfasi acilir.
 */

defined( 'ABSPATH' ) || exit;

const PC_SETUP_VERSION = '5';

add_action( 'after_switch_theme', 'pc_setup_site' );
add_action( 'admin_init', 'pc_maybe_setup_site' );

function pc_maybe_setup_site(): void {
	// admin_init anonim admin-post.php (form) isteklerinde de tetiklenir;
	// ziyaretci kurulumu baslatamasin.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	pc_setup_run();
}

/**
 * Kurulumu ya da surum yukseltmesini calistirir (yetki kontrolu cagirandadir:
 * admin_init'te yonetici, arka planda WP-Cron).
 */
function pc_setup_run(): void {
	$installed = (string) get_option( 'pc_setup_version' );

	if ( PC_SETUP_VERSION === $installed ) {
		return;
	}

	if ( '' !== $installed ) {
		// Kurulu site: yalnizca sonradan eklenen sayfalar. Tam kurulum slogani,
		// on sayfayi, kalici baglantiyi ve dili yeniden yazar; panelde yapilan
		// degisiklikler ezilmesin.
		pc_setup_page( 'sss', 'Sık Sorulan Sorular' );
		pc_setup_product_pages();
		pc_setup_merge_redirects();
		update_option( 'pc_setup_version', PC_SETUP_VERSION );
		return;
	}

	pc_setup_site();
}

/*
 * Arka plan bakimi: tema guncellenince (cPanel Git deploy) yoneticinin bu
 * sitenin paneline girmesi beklenmez. Surum ya da katalog gorselleri eksikse
 * ilk ziyarette tek seferlik bir WP-Cron gorevi kurulur; gorev kurulum
 * yukseltmesini calistirir ve gorselleri parca parca indirir (bitene kadar
 * kendini yeniden kurar). Ziyaretci hicbir sey beklemez: gorev wp-cron.php
 * isteginde calisir.
 */
add_action( 'init', 'pc_schedule_maintenance', 20 );
function pc_schedule_maintenance(): void {
	$need = PC_SETUP_VERSION !== (string) get_option( 'pc_setup_version' )
		|| ( defined( 'PC_CATALOG_IMG_VERSION' ) && PC_CATALOG_IMG_VERSION !== (string) get_option( 'pc_catalog_images_done' ) );

	if ( $need && ! wp_next_scheduled( 'pc_bg_maintenance' ) ) {
		wp_schedule_single_event( time(), 'pc_bg_maintenance' );
	}
}

add_action( 'pc_bg_maintenance', 'pc_bg_maintenance' );
function pc_bg_maintenance(): void {
	pc_setup_run();

	if ( function_exists( 'pc_catalog_images_fetch' ) && ! pc_catalog_images_fetch() ) {
		// Indirilecek gorsel kaldi: bir dakika sonra devam.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'pc_bg_maintenance' );
	}
}

function pc_setup_site(): void {
	if ( PC_SETUP_VERSION === get_option( 'pc_setup_version' ) || get_transient( 'pc_setup_lock' ) ) {
		return;
	}

	set_transient( 'pc_setup_lock', 1, MINUTE_IN_SECONDS );

	$manifest = pc_manifest();
	$pages    = $manifest['pages'] ?? array();

	// On sayfa bir sayfaya baglanir; blog listesi /haberler-blog/ olur.
	$front = pc_setup_page( 'anasayfa', 'Anasayfa' );
	$blog  = pc_setup_page( 'haberler-blog', 'Blog ve Haberler' );

	pc_setup_page( 'hakkimizda', 'Hakkımızda' );
	pc_setup_page( 'palet-civileri', 'Palet Çivileri' );
	pc_setup_page( 'iletisim', 'İletişim' );
	pc_setup_page( 'sss', 'Sık Sorulan Sorular' );

	pc_setup_product_pages();

	foreach ( pc_seed_posts() as $post ) {
		pc_setup_post( $post );
	}

	pc_setup_remove_samples();

	if ( $front && $blog ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $blog );
	}

	update_option( 'blogdescription', 'Rulo, tele dizili ve dökme palet çivisi' );

	// Yeni site ag varsayilaniyla (cogu zaman Ingilizce) acilir; Turkce dil
	// paketi kuruluysa site dili Turkce olur (tarih, 404, sayfalama).
	if ( in_array( 'tr_TR', get_available_languages(), true ) ) {
		update_option( 'WPLANG', 'tr_TR' );
	}

	if ( '' === trim( (string) get_option( 'nwcs_redirects', '' ) ) ) {
		update_option( 'nwcs_redirects', pc_setup_redirects(), false );
	}

	// Menudeki /hakkimizda/ gibi adresler duz kalici baglantiyla 404 verir.
	if ( '/%postname%/' !== (string) get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}

	flush_rewrite_rules( false );
	update_option( 'pc_setup_version', PC_SETUP_VERSION );
	delete_transient( 'pc_setup_lock' );
}

/**
 * /civiler/ ust sayfasi ve manifestteki her civi tipi icin alt sayfa
 * (var olana dokunmaz).
 */
function pc_setup_product_pages(): void {
	$parent = pc_setup_page( 'civiler', 'Çiviler' );

	foreach ( pc_manifest()['pages'] ?? array() as $page ) {
		if ( 'product' !== ( $page['template'] ?? '' ) ) {
			continue;
		}

		$slug = basename( untrailingslashit( (string) $page['path'] ) );
		$name = (string) ( $page['components']['card']['fields']['name']['default'] ?? $slug );

		pc_setup_page( $slug, $name, $parent );
	}
}

/**
 * Yonlendirme listesinde olmayan tohum kurallarini sona ekler; var olan
 * satirlara dokunmaz (liste panelden duzenlenmis olabilir).
 */
function pc_setup_merge_redirects(): void {
	$current = (string) get_option( 'nwcs_redirects', '' );
	$have    = array();

	foreach ( preg_split( '/\R/u', $current ) ?: array() as $line ) {
		$line = trim( (string) preg_replace( '/\s+#.*$/u', '', $line ) );

		if ( '' !== $line && '#' !== $line[0] ) {
			$have[ (string) preg_split( '/\s+/', $line )[0] ] = true;
		}
	}

	$missing = array();

	foreach ( preg_split( '/\R/u', pc_setup_redirects() ) ?: array() as $line ) {
		$clean = trim( (string) preg_replace( '/\s+#.*$/u', '', $line ) );

		if ( '' === $clean || '#' === $clean[0] ) {
			continue;
		}

		if ( ! isset( $have[ (string) preg_split( '/\s+/', $clean )[0] ] ) ) {
			$missing[] = $line;
		}
	}

	if ( $missing ) {
		update_option( 'nwcs_redirects', rtrim( $current ) . "\n\n# Kurulum " . PC_SETUP_VERSION . " ile eklendi\n" . implode( "\n", $missing ) . "\n", false );
	}
}

/**
 * Sayfayi olusturur ya da var olanin numarasini dondurur.
 */
function pc_setup_page( string $slug, string $title, int $parent = 0 ): int {
	$path     = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
	$existing = get_page_by_path( $path );

	if ( $existing ) {
		return (int) $existing->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_name'   => $slug,
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_parent' => $parent,
		),
		true
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Blog yazisini yoksa ekler (ayni adres, ayni tarih).
 */
function pc_setup_post( array $post ): void {
	$existing = get_posts(
		array(
			'name'        => $post['slug'],
			'post_type'   => 'post',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);

	if ( $existing ) {
		return;
	}

	wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_name'     => $post['slug'],
			'post_title'    => $post['title'],
			'post_content'  => $post['content'],
			'post_excerpt'  => $post['excerpt'],
			'post_date'     => $post['date'],
			'post_date_gmt' => get_gmt_from_date( $post['date'] ),
			'meta_input'    => array( '_pc_image' => $post['image'] ),
		)
	);
}

/**
 * WordPress'in yeni sitede actigi ornek yazi, sayfa ve yorum.
 * Yalnizca hic duzenlenmemisse (ilk halindeyse) silinir.
 */
function pc_setup_remove_samples(): void {
	foreach ( array( 'hello-world', 'merhaba-dunya' ) as $slug ) {
		$posts = get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );

		if ( $posts && $posts[0]->post_date === $posts[0]->post_modified ) {
			wp_delete_post( $posts[0]->ID, true );
		}
	}

	foreach ( array( 'sample-page', 'ornek-sayfa' ) as $slug ) {
		$page = get_page_by_path( $slug );

		if ( $page && $page->post_date === $page->post_modified ) {
			wp_delete_post( $page->ID, true );
		}
	}
}

/**
 * Eski sitenin (Consulting temasi demosu, WooCommerce ornek urunleri)
 * arama motorlarinda kalan adresleri: 410 "kalici olarak kaldirildi".
 * Gercek sayfalar yeni sitede ayni adreste oldugu icin yonlendirme gerekmez.
 * Liste Ag Yonetimi -> SEO ve GEO -> Yonlendirmeler'den duzenlenir.
 */
function pc_setup_redirects(): string {
	return implode(
		"\n",
		array(
			'# Eski temanin demo icerigi (etkinlik, kariyer, ekip, proje, magaza)',
			'/events/*                  410',
			'/careers_archive/*         410',
			'/staff/*                   410',
			'/works/*                   410',
			'/portfolio/*               410',
			'/vc_sidebar/*              410',
			'/stm-zoom/*                410',
			'/stm_works_category/*      410',
			'/stm_portfolio_category/*  410',
			'/product/*                 410',
			'/product-category/*        410',
			'/product-tag/*             410',
			'/shop/                     410',
			'/cart/                     410',
			'/checkout/                 410',
			'/my-account/               410',
			'/author/*                  410',
			'',
			'# Kategori arsivi blog sayfasina',
			'/category/*                /haberler-blog/',
			'',
			'# Eski temanin hizmet adresleri (ana sayfadan baglaniyordu)',
			'/service/rulo-palet-civiler/   /civiler/rulo-palet-civiler/',
			'/service/tele-dizili-civiler/  /civiler/tele-dizili-civiler/',
			'/service/dokme-civiler/        /civiler/dokme-civiler/',
			'/service/*                     /palet-civileri/',
			'',
			'# Toplu brad sayfasi GA sayfalarina bolundu (Kasim 2026)',
			'/civiler/brad-civi/            /civiler/18-ga-brad-civi/',
			'',
			'# Eski site haritalari',
			'/sitemap_index.xml         /wp-sitemap.xml',
			'/post-sitemap.xml          /wp-sitemap.xml',
			'/page-sitemap.xml          /wp-sitemap.xml',
			'',
		)
	);
}
