<?php
/**
 * Istanbul Keresteci temasi.
 *
 * Gorunur metinler sabit yazilmaz; degerler Network Content Studio veri
 * katmanindan okunur. Eklenti yoksa yedek fonksiyonlar manifest
 * varsayilanlarini dondurur; site yine eksiksiz gorunur.
 *
 * Tema kendi basina calisir: etkinlestirilince sayfalari, ana sayfa
 * ayarini ve ornek blog yazilarini kurar (inc/setup.php); panelde gorsel
 * secilmemisse assets/img icindeki fotograflar kullanilir.
 */

defined( 'ABSPATH' ) || exit;

require_once get_theme_file_path( 'inc/setup.php' );

/**
 * Manifest dizisi (content-manifest.php), istek basina bir kez okunur.
 */
function ik_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest = (array) include get_theme_file_path( 'content-manifest.php' );
	}

	return $manifest;
}

/**
 * Alanin manifestteki varsayilan degeri.
 */
function ik_manifest_default( string $page, string $component, string $field ) {
	return ik_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? '';
}

if ( ! function_exists( 'nwcs_field' ) ) {
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		return null === $fallback ? ik_manifest_default( $page, $component, $field ) : $fallback;
	}
	function nwcs_rows( $page, $component, $field ) {
		$rows = ik_manifest_default( $page, $component, $field );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}
	function nwcs_image( $page, $component, $field, $size = 'large' ) {
		return array( 'id' => 0, 'url' => '', 'alt' => '' );
	}
	function nwcs_image_by_id( $id, $size = 'large' ) {
		return array( 'id' => 0, 'url' => '', 'alt' => '' );
	}
	function nwcs_the_icon( $key, $class = '', $size = 24 ) {}
	function nwcs_edit_attr( $page, $component, $field = '', $row = null, $sub = '' ) {}
	function nwcs_section_order( $page = 'home' ) {
		return ik_manifest()['pages'][ $page ]['sortable_sections'] ?? array();
	}
}

add_action( 'after_setup_theme', 'ik_setup' );
function ik_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script' ) );
}

add_action( 'wp_enqueue_scripts', 'ik_assets' );
function ik_assets(): void {
	$version = wp_get_theme()->get( 'Version' );

	/*
	 * Stiller tek dosyada: assets/css/site-front.css (ana sayfa) ya da site-inner.css
	 * (diger sayfalar). Kaynaklar assets/css altinda; sira ayni: fonts (Archivo,
	 * tema icinden), tokens, style.css, base, header, footer, home, [pages].
	 * Kaynak degisince: python tools/build-css.py. Surum dosya zamanindan.
	 */
	$bundle = is_front_page() ? 'assets/css/site-front.css' : 'assets/css/site-inner.css';
	wp_enqueue_style( 'ik-site', get_theme_file_uri( $bundle ), array(), ik_file_version( $bundle ) );

	// Sik sorulan sorular (/sss/). Surum dosya zamanindan: tema surumu artmadan da yenilensin.
	if ( is_page( 'sss' ) ) {
		wp_enqueue_style( 'ik-faq', get_theme_file_uri( 'assets/css/faq.css' ), array( 'ik-site' ), ik_file_version( 'assets/css/faq.css' ) );
	}

	wp_enqueue_script( 'ik-nav', get_theme_file_uri( 'assets/js/nav.js' ), array(), $version, true );

	if ( is_front_page() || is_page( 'hakkimizda' ) ) {
		wp_enqueue_script( 'ik-process', get_theme_file_uri( 'assets/js/process.js' ), array(), $version, true );
	}
}

/**
 * Tema dosyasinin surumu: degisim zamani. Dosya degisince adres degisir,
 * tarayici ve onbellek eski dosyayi gostermez.
 */
function ik_file_version( string $file ): string {
	return (string) ( @filemtime( get_theme_file_path( $file ) ) ?: wp_get_theme()->get( 'Version' ) );
}

/**
 * Head'in basi: JS varsa no-js sinifi ilk boyamadan once kalkar (menu paneli
 * once acik gorunup sonra kapanarak sayfayi kaydirmasin). Ardindan Archivo
 * latin ve latin-ext dosyalari preload edilir: Turkce harfler (ş, ğ, İ)
 * latin-ext'te, ikisi de her sayfada gerekiyor.
 */
add_action( 'wp_head', 'ik_head_early', 1 );
function ik_head_early(): void {
	echo "<script>document.documentElement.classList.remove('no-js');</script>\n";

	foreach ( array( 'archivo-latin', 'archivo-latin-ext' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin />' . "\n",
			esc_url( get_theme_file_uri( 'assets/fonts/' . $font . '.woff2' ) )
		);
	}
}

/* ======================================================================
 * Urunler
 *
 * Tek kaynak: products.catalog.items. Her satirin sayfasi
 * /urunlerimiz/<slug>/ adresinde, tema kurulumunun (inc/setup.php) olusturdugu
 * alt sayfadir.
 * ====================================================================== */

/**
 * Urun satirlari; adres adi bos olanlar atlanir.
 */
function ik_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	foreach ( nwcs_rows( 'products', 'catalog', 'items' ) as $index => $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );

		if ( '' === $slug ) {
			continue;
		}

		$products[] = array(
			'index' => $index,
			'slug'  => $slug,
			'title' => (string) ( $row['title'] ?? '' ),
			'short' => (string) ( $row['short'] ?? '' ),
			'body'  => (string) ( $row['body'] ?? '' ),
			'specs' => (string) ( $row['specs'] ?? '' ),
			'image' => (int) ( $row['image'] ?? 0 ),
			'file'  => ik_product_file( $slug ),
			'url'   => ik_link( '/urunlerimiz/' . $slug . '/' ),
		);
	}

	return $products;
}

/**
 * Urunun tema icindeki yedek fotografi (assets/img/urun-<slug>.jpg).
 * Citanin kendi fotografi yok; kalas fotografi kullanilir.
 */
function ik_product_file( string $slug ): string {
	$slug = 'cita' === $slug ? 'kalas' : $slug;

	return file_exists( get_theme_file_path( 'assets/img/urun-' . $slug . '.jpg' ) ) ? 'urun-' . $slug . '.jpg' : 'urun-kereste.jpg';
}

/**
 * Urun gorseli: panelde secilen, yoksa temanin fotografi.
 */
function ik_product_image( array $product, string $size = 'medium_large' ): array {
	return ik_image_or_default( nwcs_image_by_id( (int) $product['image'], $size ), $product['file'], $product['title'] );
}

/**
 * Adres adina gore urun; yoksa bos dizi.
 */
function ik_product( string $slug ): array {
	foreach ( ik_products() as $product ) {
		if ( $product['slug'] === $slug ) {
			return $product;
		}
	}

	return array();
}

/**
 * Su anki sayfa bir urun detayi mi? /urunlerimiz/ altindaki alt sayfalar ya
 * da alt sayfasi olmayan urun icin yedek cizim (ik_product_fallback).
 */
function ik_current_product(): array {
	if ( ik_fallback_product() ) {
		return ik_fallback_product();
	}

	if ( ! is_page() ) {
		return array();
	}

	$page   = get_queried_object();
	$parent = $page instanceof WP_Post && $page->post_parent ? get_post( $page->post_parent ) : null;

	if ( ! $parent || 'urunlerimiz' !== $parent->post_name ) {
		return array();
	}

	return ik_product( $page->post_name );
}

/**
 * Alt sayfasi olmayan urun icin yedek cizimde gosterilen urun.
 *
 * Panelden sonradan eklenen urunun WordPress alt sayfasi yoktur; adresi
 * (/urunlerimiz/<slug>/) 404 verirdi. Istek 404 olup yol bir urune denk
 * geliyorsa sayfa urun detayi olarak 200 ile cizilir. Gercek alt sayfalar
 * her zaman once gelir; rewrite kurali ve flush gerekmez.
 */
function ik_fallback_product( ?array $set = null ): array {
	static $product = array();

	if ( null !== $set ) {
		$product = $set;
	}

	return $product;
}

/*
 * Tespit 'wp' aksiyonunun basinda: SEO eklentisi sayfa bilgisini (baslik,
 * aciklama, canonical, Product verisi) bu aksiyonda hesaplayip sakliyor;
 * istek o anda 404 sayilirsa urun sayfasi SEO'suz kalirdi.
 */
add_action( 'wp', 'ik_product_detect', 0 );
function ik_product_detect(): void {
	if ( ! is_404() ) {
		return;
	}

	$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$path = trim( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

	if ( '' !== $home && str_starts_with( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) + 1 );
	}

	if ( ! preg_match( '#^urunlerimiz/([^/]+)$#', $path, $match ) ) {
		return;
	}

	$product = ik_product( sanitize_title( rawurldecode( $match[1] ) ) );

	if ( ! $product ) {
		return;
	}

	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	ik_fallback_product( $product );
}

// redirect_canonical (10) benzer sayfaya yonlendirmeden once cizilir.
add_action( 'template_redirect', 'ik_product_fallback', 1 );
function ik_product_fallback(): void {
	$product = ik_fallback_product();

	if ( ! $product ) {
		return;
	}

	get_header();
	get_template_part( 'template-parts/product-detail', null, array( 'product' => $product ) );
	get_footer();
	exit;
}

/**
 * Yedek cizimde tarayici sekmesi "Sayfa bulunamadi" demesin.
 */
add_filter( 'document_title_parts', 'ik_fallback_title' );
function ik_fallback_title( array $parts ): array {
	$product = ik_fallback_product();

	if ( $product ) {
		$parts['title'] = $product['title'];
	}

	return $parts;
}

/**
 * Urunun paneldeki gizli sayfasi ('urun-<slug>'); panelden sonradan eklenen
 * urunun gizli sayfasi yoktur, bos dondurur.
 */
function ik_product_page_key( array $product ): string {
	$key = 'urun-' . $product['slug'];

	return isset( ik_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Gizli sayfadaki alan; sayfa yoksa bos.
 */
function ik_product_page_field( array $product, string $component, string $field ): string {
	$key = ik_product_page_key( $product );

	return '' === $key ? '' : trim( (string) nwcs_field( $key, $component, $field ) );
}

/**
 * Urun alt sayfalarini SEO ve GEO eklentisine bildirir: bu sayfalar manifestte
 * degil, product-detail sablonuyla ciziliyor. Her biri baslik, aciklama,
 * Product yapilandirilmis verisi ve llms.txt satiri alir.
 */
add_filter( 'nwcs_seo_extra_pages', 'ik_seo_product_pages' );
function ik_seo_product_pages( array $pages ): array {
	foreach ( ik_products() as $product ) {
		$image = (int) $product['image'];

		if ( ! $image ) {
			$file  = get_theme_file_path( 'assets/img/' . $product['file'] );
			$size  = is_readable( $file ) ? getimagesize( $file ) : false;
			$image = $size ? array(
				'url'    => get_theme_file_uri( 'assets/img/' . $product['file'] ),
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
				'alt'    => $product['title'],
			) : 0;
		}

		// Panelde gizli urun sayfasinin "Arama ve Paylasim" alanlari doluysa onlar.
		$seo_title       = ik_product_page_field( $product, 'seo', 'title' );
		$seo_description = ik_product_page_field( $product, 'seo', 'description' );
		$seo_image       = '' !== ik_product_page_key( $product ) ? (int) nwcs_field( ik_product_page_key( $product ), 'seo', 'image' ) : 0;

		$pages[] = array(
			'url'         => $product['url'],
			'name'        => '' !== $seo_title ? $seo_title : $product['title'],
			'title'       => '' !== $seo_title ? '' : ik_product_seo_title( $product['title'] ),
			'description' => '' !== $seo_description ? $seo_description : ( '' !== trim( $product['short'] ) ? $product['short'] : $product['body'] ),
			'image'       => $seo_image ? $seo_image : $image,
			'type'        => 'Product',
			'properties'  => array_map(
				static fn( array $pair ): array => array( 'name' => $pair[0], 'value' => $pair[1] ),
				ik_specs( $product['specs'] )
			),
		);
	}

	return $pages;
}

/**
 * Urun sayfasinin arama basligi (panelde "Arama basligi" bossa).
 *
 * "Kereste - Istanbul Keresteci" aramada hicbir ticari kelime tasimiyor;
 * insanlar "kereste satisi", "osb plaka fiyatlari" diye ariyor. Fiyat sayfada
 * telefonla verildigi icin "fiyatlari" sozu yaniltici degil. 60 karakteri
 * asarsa kisa kalip.
 */
function ik_product_seo_title( string $title ): string {
	$title = trim( $title );

	if ( '' === $title ) {
		return '';
	}

	$long = $title . ' Satışı ve Fiyatları | İstanbul Keresteci';

	return mb_strlen( $long ) <= 60 ? $long : $title . ' Satışı | İstanbul Keresteci';
}

/**
 * "Ad: deger" satirlarini ciftlere boler.
 */
function ik_specs( string $text ): array {
	$specs = array();

	foreach ( preg_split( '/\R/u', trim( $text ) ) as $line ) {
		$parts = explode( ':', $line, 2 );

		if ( 2 === count( $parts ) && '' !== trim( $parts[0] ) ) {
			$specs[] = array( trim( $parts[0] ), trim( $parts[1] ) );
		}
	}

	return $specs;
}

/* ======================================================================
 * Blog yazilari panelden
 *
 * Ornek yazilarin panelde gizli bir sayfasi var ('yazi-<slug>', manifest).
 * Panelde degistirilip kaydedilen alan sitede WordPress yazisinin yerine
 * gecer: yazi sayfasi, blog listesi, ana sayfa kartlari ve arama bilgisi.
 * Degistirilmeyen alan icin WordPress'teki yazi aynen kullanilir; yazi
 * Yazilar ekranindan duzenlenmeye devam edebilir.
 * ====================================================================== */

/**
 * Yazinin paneldeki gizli sayfasi; yoksa bos (sonradan yazilan yazilar).
 */
function ik_post_page_key( $post ): string {
	$post = get_post( $post );

	if ( ! $post || 'post' !== $post->post_type || '' === $post->post_name ) {
		return '';
	}

	$key = 'yazi-' . $post->post_name;

	return isset( ik_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Panelde degistirilmis deger; alan varsayilanindaysa (yazinin ilk metni) null.
 *
 * @return string|int|null
 */
function ik_post_override( $post, string $field ) {
	$key = ik_post_page_key( $post );

	if ( '' === $key ) {
		return null;
	}

	$value = nwcs_field( $key, 'post', $field );

	if ( 'image' === $field ) {
		return (int) $value ? (int) $value : null;
	}

	$clean   = static fn( $text ): string => trim( str_replace( "\r\n", "\n", (string) $text ) );
	$value   = $clean( $value );
	$default = $clean( ik_manifest_default( $key, 'post', $field ) );

	return '' === $value || $value === $default ? null : $value;
}

/**
 * Onizlemede tiklaninca yazinin paneldeki alanini acar.
 */
function ik_post_edit_attr( $post, string $field ): void {
	$key = ik_post_page_key( $post );

	if ( '' !== $key ) {
		nwcs_edit_attr( $key, 'post', $field );
	}
}

/*
 * SEO: eklenti yazi bilgisini 'wp' aksiyonunda (10) hesaplayip sakliyor.
 * Hemen once, gizli sayfanin "Arama ve Paylasim" alanlari (yoksa paneldeki
 * baslik, ozet ve kapak) gecerliyken hesaplatilir.
 */
add_action( 'wp', 'ik_post_seo', 9 );
function ik_post_seo(): void {
	if ( ! is_singular( 'post' ) || ! function_exists( 'nwcs_seo_context' ) ) {
		return;
	}

	$post = get_queried_object();
	$key  = ik_post_page_key( $post );

	if ( '' === $key ) {
		return;
	}

	$description = trim( (string) nwcs_field( $key, 'seo', 'description' ) );
	$description = '' !== $description ? $description : (string) ik_post_override( $post, 'excerpt' );
	$original    = $post->post_excerpt;

	if ( '' !== $description ) {
		$post->post_excerpt = $description;
	}

	$GLOBALS['ik_post_seo_key'] = $key;
	nwcs_seo_context();
	unset( $GLOBALS['ik_post_seo_key'] );

	$post->post_excerpt = $original;
}

/**
 * Arama bilgisi hesaplanirken gecerli olan SEO alani; diger zamanlarda bos.
 */
function ik_post_seo_field( $post, string $field ): string {
	$key = $GLOBALS['ik_post_seo_key'] ?? '';

	return '' !== $key && ik_post_page_key( $post ) === $key ? trim( (string) nwcs_field( $key, 'seo', $field ) ) : '';
}

add_filter( 'the_title', 'ik_post_title', 10, 2 );
function ik_post_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! $post_id ) {
		return $title;
	}

	$seo = ik_post_seo_field( $post_id, 'title' );

	return '' !== $seo ? $seo : ( ik_post_override( $post_id, 'title' ) ?? $title );
}

add_filter( 'get_the_excerpt', 'ik_post_excerpt', 10, 2 );
function ik_post_excerpt( $excerpt, $post = null ) {
	return is_admin() ? $excerpt : ( ik_post_override( $post, 'excerpt' ) ?? $excerpt );
}

// wpautop'tan sonra: panel metni kendi paragraflariyla gelir.
add_filter( 'the_content', 'ik_post_content', 99 );
function ik_post_content( $content ) {
	if ( is_admin() || ! in_the_loop() ) {
		return $content;
	}

	$body = ik_post_override( get_the_ID(), 'body' );

	if ( null === $body ) {
		return $content;
	}

	return ik_rich_html( $body );
}

add_filter( 'post_thumbnail_id', 'ik_post_thumbnail', 10, 2 );
function ik_post_thumbnail( $thumbnail_id, $post = null ) {
	if ( is_admin() ) {
		return $thumbnail_id;
	}

	$seo = (int) ik_post_seo_field( $post, 'image' );

	return $seo ? $seo : ( ik_post_override( $post, 'image' ) ?? $thumbnail_id );
}

/* ======================================================================
 * Menu
 * ====================================================================== */

/**
 * Menu satirlari ve acilir listeleri.
 *
 * Paneldeki "submenu" alani "urunler" ise alt ogeler 'global.menu_urunler'
 * satirlarindan gelir; o bos birakildiysa urun listesinden uretilir, menu
 * ayrica bakim istemez.
 *
 * Her alt ogenin 'edit' anahtari onizlemede tiklaninca acilacak alani verir:
 * panel satiri ya da urunun Urun Listesi satiri.
 */
function ik_menu(): array {
	$items = array();

	foreach ( ik_hide_missing_sss( nwcs_rows( 'global', 'header', 'menu' ) ) as $index => $row ) {
		$children = array();
		$submenu  = sanitize_key( (string) ( $row['submenu'] ?? '' ) );
		$rows     = 'urunler' === $submenu ? nwcs_rows( 'global', 'menu_urunler', 'items' ) : array();

		foreach ( $rows as $row_index => $child ) {
			$url = (string) ( $child['url'] ?? '' );

			if ( '' === trim( (string) ( $child['label'] ?? '' ) ) ) {
				continue;
			}

			// Baglanti bir urune gidiyorsa kucuk gorsel o urunun.
			$slug = preg_match( '#/urunlerimiz/([^/]+)/?$#', $url, $match ) ? sanitize_title( $match[1] ) : '';

			$children[] = array(
				'label'   => (string) $child['label'],
				'url'     => ik_link( $url ),
				'product' => '' !== $slug ? ik_product( $slug ) : array(),
				'edit'    => array( 'global', 'menu_urunler', 'items', (int) $row_index, 'label' ),
			);
		}

		if ( 'urunler' === $submenu && ! $children ) {
			foreach ( ik_products() as $product ) {
				$children[] = array(
					'label'   => $product['title'],
					'url'     => $product['url'],
					'product' => $product,
					'edit'    => array( 'products', 'catalog', 'items', (int) $product['index'], 'title' ),
				);
			}
		}

		$items[] = array(
			'index'    => $index,
			'label'    => (string) ( $row['label'] ?? '' ),
			'url'      => (string) ( $row['url'] ?? '' ),
			'children' => $children,
		);
	}

	return $items;
}

/**
 * Menu ogesi su an goruntulenen sayfayi mi isaret ediyor?
 *
 * Yalnizca sayfa yolu karsilastirilir. Blog yazisi acikken Blog, urun
 * detayi acikken Urunlerimiz aktif sayilir.
 */
function ik_is_current( string $url ): bool {
	$url = trim( $url );

	if ( '' === $url || str_starts_with( $url, '#' ) ) {
		return false;
	}

	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
		return false;
	}

	// Kalici baglantilar kapaliysa yol yerine sayfa kimligi karsilastirilir.
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		$link = ik_link( $url );

		if ( untrailingslashit( $link ) === untrailingslashit( home_url( '/' ) ) ) {
			return is_front_page();
		}

		$id = url_to_postid( $link );

		if ( ! $id ) {
			return false;
		}

		if ( (int) get_option( 'page_for_posts' ) === $id && ( is_home() || is_singular( 'post' ) ) ) {
			return true;
		}

		$queried = (int) get_queried_object_id();

		return $queried && ( $id === $queried || in_array( $id, get_post_ancestors( $queried ), true ) );
	}

	$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	// Alt dizin kurulumunda /istanbul-keresteci/ onekini at.
	$relative = static function ( string $path ) use ( $home ): string {
		$path = trim( $path, '/' );

		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	};

	$path    = $relative( (string) wp_parse_url( ik_link( $url ), PHP_URL_PATH ) );
	$current = $relative( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ) );

	if ( '' === $path ) {
		return is_front_page();
	}

	if ( 'blog' === $path && is_singular( 'post' ) ) {
		return true;
	}

	return $path === $current || str_starts_with( $current, $path . '/' );
}

/* ======================================================================
 * Yardimcilar
 * ====================================================================== */

/**
 * Ana sayfa bolumunu basar.
 */
function ik_section( string $key, array $args = array() ): void {
	$file = get_theme_file_path( "template-parts/sections/{$key}.php" );

	if ( file_exists( $file ) ) {
		include $file;
	}
}

/**
 * Panelden gelen baglantiyi adrese cevirir; cikti her zaman esc_url ile basilir.
 *
 * Kok-goreli yollar sitenin kendi adresine baglanir: site alt dizinde
 * (/istanbul-keresteci/) calistigi icin dogrudan basilsa ag kokune giderdi.
 * Kalici baglantilar kapaliysa (?page_id=) yol, o yoldaki sayfanin
 * adresine cevrilir; /urunlerimiz/kereste/ gibi alt sayfalar da calisir.
 */
function ik_link( $url ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( ! str_starts_with( $url, '/' ) || str_starts_with( $url, '//' ) ) {
		return ik_whatsapp_link( $url );
	}

	if ( '' !== (string) get_option( 'permalink_structure' ) ) {
		return home_url( $url );
	}

	$parts = wp_parse_url( $url );
	$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );

	if ( '' === $path ) {
		$link = home_url( '/' );
	} elseif ( 'blog' === $path && get_option( 'page_for_posts' ) ) {
		$link = get_permalink( (int) get_option( 'page_for_posts' ) );
	} else {
		$page = get_page_by_path( $path );
		$link = $page ? get_permalink( $page ) : home_url( $url );
	}

	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		$link = add_query_arg( $query, $link );
	}

	return $link . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
}

/**
 * WhatsApp baglantisina hazir mesaj ekler (wa.me/<numara>?text=...).
 *
 * Numara panelde yazildigi gibi kalir; yalnizca mesaj eklenir. Panelde
 * yazilan baglantida zaten mesaj (text=) varsa dokunulmaz. Urun sayfasinda
 * (ya da $product verilirse) mesaj urunun adini ve adresini tasir; boylece
 * yardim kutusu ve footer'daki genel WhatsApp baglantilari da o sayfada
 * urunu soyler. Diger sayfalarda genel selam.
 */
function ik_whatsapp_link( string $url, array $product = array() ): string {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( ! in_array( $host, array( 'wa.me', 'api.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com' ), true ) ) {
		return $url;
	}

	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

	if ( '' !== trim( (string) ( $query['text'] ?? '' ) ) ) {
		return $url;
	}

	$product = $product ?: ik_current_product();
	$text    = $product
		? sprintf( 'Merhaba, %s hakkında bilgi almak istiyorum. %s', $product['title'], $product['url'] )
		: 'Merhaba, web sitenizden yazıyorum. Bilgi almak istiyorum.';

	return add_query_arg( 'text', rawurlencode( $text ), remove_query_arg( 'text', $url ) );
}

/**
 * Panelde gorsel secilmemisse temanin kendi fotografini dondurur.
 *
 * Gercek fotograflar tema icinde (assets/img) duruyor; site medya
 * kutuphanesi bos da olsa yer tutucuyla acilmaz. Panelden bir gorsel
 * secildiginde her zaman o kullanilir. (kocist_image_or_default ile ayni mantik.)
 */
function ik_image_or_default( array $image, string $file, string $alt = '' ): array {
	$is_placeholder = false !== mb_stripos( (string) ( $image['alt'] ?? '' ), 'yer tutucu' );

	if ( $is_placeholder ) {
		$image = array( 'id' => 0, 'url' => '', 'alt' => '' );
	}

	if ( ! empty( $image['url'] ) || '' === $file || ! file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
		return $image;
	}

	return array(
		'id'  => 0,
		'url' => get_theme_file_uri( 'assets/img/' . ik_webp_file( $file ) ),
		'alt' => '' !== ( $image['alt'] ?? '' ) ? $image['alt'] : $alt,
	);
}

/**
 * Bos satirla ayrilmis metni paragraflara boler.
 */
/**
 * Yazi metni icin hafif bicim: bos satirla ayrilan her blok bir paragraf;
 * "## " ile baslayan blok ara baslik (h2), "### " h3, her satiri "- " ile
 * baslayan blok madde listesi, her satiri "|" ile baslayan blok tablo (ilk
 * satir baslik; "| --- |" ayirici satiri atlanir). Kurulumun yazdigi yazi
 * icerigi ve paneldeki yazi metni ayni kurallarla cizilir.
 */
function ik_rich_html( string $text ): string {
	$html = array();

	foreach ( preg_split( '/\R\s*\R/u', trim( $text ) ) as $block ) {
		$block = trim( $block );

		if ( '' === $block ) {
			continue;
		}

		$lines = array_map( 'trim', preg_split( '/\R/u', $block ) );

		if ( preg_match( '/^(#{2,3}) (.+)$/u', $block, $m ) && 1 === count( $lines ) ) {
			$level  = strlen( $m[1] );
			$html[] = sprintf( '<h%1$d>%2$s</h%1$d>', $level, esc_html( $m[2] ) );
			continue;
		}

		if ( count( $lines ) === count( preg_grep( '/^- /u', $lines ) ) ) {
			$items  = array_map( static fn( string $line ): string => '<li>' . esc_html( substr( $line, 2 ) ) . '</li>', $lines );
			$html[] = '<ul>' . implode( '', $items ) . '</ul>';
			continue;
		}

		if ( count( $lines ) === count( preg_grep( '/^\|/u', $lines ) ) ) {
			$rows = array();

			foreach ( $lines as $line ) {
				$cells = array_map( 'trim', explode( '|', trim( $line, '|' ) ) );

				if ( preg_match( '/^[\s|:-]+$/u', $line ) ) {
					continue;
				}

				$tag    = $rows ? 'td' : 'th';
				$rows[] = '<tr>' . implode( '', array_map( static fn( string $cell ): string => "<$tag>" . esc_html( $cell ) . "</$tag>", $cells ) ) . '</tr>';
			}

			$head   = array_shift( $rows );
			$html[] = '<div class="ik-table"><table><thead>' . $head . '</thead><tbody>' . implode( '', $rows ) . '</tbody></table></div>';
			continue;
		}

		$html[] = '<p>' . nl2br( esc_html( $block ) ) . '</p>';
	}

	return implode( "\n", $html );
}

function ik_paragraphs( string $text, string $class = '' ): void {
	foreach ( preg_split( '/\R\s*\R/u', trim( $text ) ) as $block ) {
		$block = trim( $block );

		if ( '' !== $block ) {
			printf( '<p%s>%s</p>', $class ? ' class="' . esc_attr( $class ) . '"' : '', nl2br( esc_html( $block ) ) );
		}
	}
}

/**
 * Tema fotografinin WebP kopyasi varsa onun adi (urun-kereste.jpg ->
 * urun-kereste.webp). JPG'ler yerinde kalir: kurulum medya kitapligina JPG
 * ekler, paylasim (og:image) gorselleri JPG'dir. WebP'si daha buyuk cikan
 * fotograflarin kopyasi yoktur; onlarda JPG kullanilir.
 */
function ik_webp_file( string $file ): string {
	$webp = (string) preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );

	return $webp !== $file && file_exists( get_theme_file_path( 'assets/img/' . $webp ) ) ? $webp : $file;
}

/**
 * img icin width/height nitelikleri (tarayici yeri onceden ayirsin). Olcu tema
 * dosyasindan ya da medya kaydindan okunur; bulunamazsa bos.
 */
function ik_image_dims( array $image ): string {
	static $cache = array();

	$url = (string) ( $image['url'] ?? '' );

	if ( '' === $url ) {
		return '';
	}

	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}

	$name  = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$dims  = array();
	$theme = get_theme_file_uri( 'assets/img/' );

	if ( str_starts_with( $url, $theme ) ) {
		$size = @getimagesize( get_theme_file_path( 'assets/img/' . $name ) );
		$dims = $size ? array( $size[0], $size[1] ) : array();
	} elseif ( ! empty( $image['id'] ) ) {
		$meta = wp_get_attachment_metadata( (int) $image['id'] );

		if ( is_array( $meta ) ) {
			if ( wp_basename( (string) ( $meta['file'] ?? '' ) ) === $name ) {
				$dims = array( $meta['width'] ?? 0, $meta['height'] ?? 0 );
			}

			foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
				if ( ( $size['file'] ?? '' ) === $name ) {
					$dims = array( $size['width'] ?? 0, $size['height'] ?? 0 );
					break;
				}
			}
		}
	}

	$cache[ $url ] = ( $dims && $dims[0] > 0 && $dims[1] > 0 ) ? sprintf( ' width="%d" height="%d"', $dims[0], $dims[1] ) : '';

	return $cache[ $url ];
}

/**
 * Tema fotografinin (kimlik 0) kucuk genislikli WebP kopyalari varsa srcset
 * ve sizes ekler: <ad>-640.webp, <ad>-960.webp, <ad>-1280.webp ve asil dosya.
 * Telefon ekrana yetecek en kucuk dosyayi indirir; gorunum ayni (ayni
 * fotograf, ayni kirpim). Panel gorsellerine dokunmaz.
 *
 * $sizes, gorselin cizildigi genislik (object-fit: cover kutuyu yukseklikten
 * dolduruyorsa kutudan genis). Kopyalar: Pillow LANCZOS, WebP q81.
 */
function ik_theme_srcset( array $image, string $sizes ): array {
	$path = ik_theme_image_path( $image );

	if ( '' === $path ) {
		return $image;
	}

	$info = pathinfo( $path );
	$size = @getimagesize( get_theme_file_path( 'assets/img/' . $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$set  = array();

	foreach ( array( 640, 960, 1280 ) as $width ) {
		$file = $info['filename'] . '-' . $width . '.webp';

		if ( $size && $width < $size[0] && file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
			$set[] = get_theme_file_uri( 'assets/img/' . $file ) . ' ' . $width . 'w';
		}
	}

	if ( $set ) {
		$set[]           = $image['url'] . ' ' . $size[0] . 'w';
		$image['srcset'] = implode( ', ', $set );
		$image['sizes']  = $sizes;
	}

	return $image;
}

/**
 * Tema fotografinin (kimlik 0) telefon kirpimi varsa <picture> kaynagi ekler:
 * <ad>-telefon.webp, $media kosulunda kullanilir. Kirpim, o kosulda ekranda
 * gorunen kismi tamamen kapsar (bkz. hero.php); gorunum ayni.
 */
function ik_theme_mobile_source( array $image, string $media ): array {
	$path = ik_theme_image_path( $image );
	$file = '' !== $path ? pathinfo( $path, PATHINFO_FILENAME ) . '-telefon.webp' : '';

	if ( '' !== $file && file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
		$image['sources'] = array( array( 'media' => $media, 'srcset' => get_theme_file_uri( 'assets/img/' . $file ) ) );
	}

	return $image;
}

/**
 * Gorsel tema klasorundeyse (assets/img) dosya adi; degilse bos.
 */
function ik_theme_image_path( array $image ): string {
	$url   = (string) ( $image['url'] ?? '' );
	$theme = get_theme_file_uri( 'assets/img/' );

	if ( ! empty( $image['id'] ) || '' === $url || ! str_starts_with( $url, $theme ) ) {
		return '';
	}

	$name = substr( $url, strlen( $theme ) );

	return preg_match( '/^[a-z0-9-]+\.(?:jpe?g|png|webp)$/i', $name ) ? $name : '';
}

/**
 * Gorsel icin img etiketi; deger yoksa isaretli yer tutucu.
 *
 * $image icinde istege bagli: 'srcset' + 'sizes' (ik_theme_srcset) ve
 * 'sources' (ik_theme_mobile_source; o zaman <picture> icinde basilir).
 */
function ik_image_tag( array $image, string $class = '', ?string $alt = null, string $loading = 'lazy', string $attrs = '' ): string {
	if ( ! empty( $image['url'] ) ) {
		// Ilk ekrandaki (eager) gorsel LCP adayi: once indirilsin.
		$priority = 'eager' === $loading ? ' fetchpriority="high"' : '';
		$srcset   = ! empty( $image['srcset'] ) ? sprintf( ' srcset="%s" sizes="%s"', esc_attr( $image['srcset'] ), esc_attr( (string) ( $image['sizes'] ?? '100vw' ) ) ) : '';

		$tag = sprintf(
			'<img src="%1$s"%8$s alt="%2$s" class="%3$s"%4$s loading="%5$s"%6$s decoding="async"%7$s />',
			esc_url( $image['url'] ),
			esc_attr( null === $alt ? $image['alt'] : $alt ),
			esc_attr( $class ),
			ik_image_dims( $image ),
			esc_attr( $loading ),
			$priority,
			$attrs,
			$srcset
		);

		if ( empty( $image['sources'] ) ) {
			return $tag;
		}

		$sources = '';

		foreach ( (array) $image['sources'] as $source ) {
			$sources .= sprintf( '<source media="%s" srcset="%s" type="image/webp" />', esc_attr( $source['media'] ), esc_url( $source['srcset'] ) );
		}

		// display: contents: sarmalayici kutu olusturmaz; img'nin yerlesimi (flex, absolute) degismez.
		return '<picture style="display:contents">' . $sources . $tag . '</picture>';
	}

	return sprintf( '<div class="ik-placeholder %s" aria-hidden="true"%s></div>', esc_attr( $class ), $attrs );
}

/**
 * Panel onizleme isaretini (nwcs_edit_attr) metin olarak dondurur;
 * ik_image_tag() gibi HTML donduren yardimcilara verilir. Onizleme
 * disinda bos.
 */
function ik_edit_attrs( ...$args ): string {
	ob_start();
	nwcs_edit_attr( ...$args );

	return (string) ob_get_clean();
}

/**
 * Blog yazisinin kapak gorseli; yoksa bos dizi.
 */
function ik_post_image( WP_Post $post, string $size = 'medium_large' ): array {
	$id = (int) get_post_thumbnail_id( $post );

	$image = $id ? nwcs_image_by_id( $id, $size ) : array();

	// Kapak secilmemis yazilarda da gercek fotograf gorunsun.
	return ik_image_or_default( $image ?: array( 'id' => 0, 'url' => '', 'alt' => '' ), 'blog-kereste.jpg' );
}

/**
 * Yazi tarihini Turkce basar ("19 Nisan 2022").
 *
 * Demo sitelerinin dili Ingilizce kurulu; Turkce dil paketi olmadan ay
 * adlari dogru ciksin diye elle cevrilir.
 */
function ik_date( WP_Post $post ): string {
	$months = array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	$time   = get_post_timestamp( $post );

	if ( ! $time ) {
		return '';
	}

	return wp_date( 'j', $time ) . ' ' . $months[ (int) wp_date( 'n', $time ) - 1 ] . ' ' . wp_date( 'Y', $time );
}

/**
 * Ikon basar: Phosphor (MIT, assets/icons/LICENSE), kalin agirlik.
 *
 * Paneldeki ikon alanlari eklentinin sabit 16 anahtarini kullanir; tema bu
 * anahtarlari ayni anlamdaki Phosphor cizimine esler. Ek olarak sosyal ag
 * ikonlari (instagram, facebook, linkedin, youtube, x, tiktok) temanin kendi
 * cizimleridir; eklentinin ikon listesinde sosyal ag isareti yoktur.
 */
function ik_icon( string $key, int $size = 24, string $class = 'ik-icon' ): void {
	static $cache = array();

	$map = array(
		'truck'     => 'truck',
		'box'       => 'package',
		'factory'   => 'factory',
		'tools'     => 'wrench',
		'tree'      => 'tree',
		'recycle'   => 'recycle',
		'shield'    => 'shield-check',
		'ruler'     => 'ruler',
		'clock'     => 'clock',
		'phone'     => 'phone',
		'whatsapp'  => 'whatsapp-logo',
		'mail'      => 'envelope-simple',
		'pin'       => 'map-pin',
		'check'     => 'check-circle',
		'star'      => 'star',
		'arrow'     => 'arrow-right',
		'instagram' => 'instagram-logo',
		'facebook'  => 'facebook-logo',
		'linkedin'  => 'linkedin-logo',
		'youtube'   => 'youtube-logo',
		'x'         => 'x-logo',
		'tiktok'    => 'tiktok-logo',
		'caret'     => 'caret-down',
	);

	$key = sanitize_key( $key );

	if ( ! isset( $map[ $key ] ) ) {
		return;
	}

	if ( ! isset( $cache[ $key ] ) ) {
		$file          = get_theme_file_path( 'assets/icons/' . $map[ $key ] . '.svg' );
		$cache[ $key ] = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	if ( '' === $cache[ $key ] ) {
		return;
	}

	echo str_replace( // phpcs:ignore WordPress.Security.EscapingOutput -- temanin kendi sabit SVG dosyalari.
		'<svg ',
		sprintf( '<svg class="%s" width="%d" height="%d" aria-hidden="true" focusable="false" ', esc_attr( $class ), $size, $size ),
		$cache[ $key ]
	);
}

/**
 * Sosyal ag baglantilari: global.topbar bileseninin <ag>_url alanlari.
 *
 * Varsayilanlar bostur; yalnizca panelde adresi girilen ag listelenir.
 * Sira ve etiketler burada sabittir. Donen dizi: [ ag => [ label, url ] ].
 */
function ik_social_links(): array {
	$networks = array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'x'         => 'X',
		'tiktok'    => 'TikTok',
	);

	$links = array();

	foreach ( $networks as $network => $label ) {
		$url = trim( (string) nwcs_field( 'global', 'topbar', $network . '_url' ) );

		if ( '' !== $url && '' !== esc_url( $url ) ) {
			$links[ $network ] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}

	return $links;
}

/**
 * Logo: panelde gorsel varsa o, yoksa agac isareti ve iki satir yazi.
 *
 * Musterinin elindeki PNG logo 146 piksel genisliginde; retina ekranda
 * bulaniklasmasin diye isaret SVG olarak cizildi, yazi Archivo ile basilir.
 */
function ik_logo( string $modifier = '' ): void {
	$logo   = nwcs_image( 'global', 'header', 'logo_image', 'medium' );
	$top    = nwcs_field( 'global', 'header', 'logo_top' );
	$bottom = nwcs_field( 'global', 'header', 'logo_bottom' );
	?>
	<?php // Logo gorseline ya da isaretine tiklaninca logo gorseli alani; yazilara tiklaninca kendi alanlari. ?>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ik-logo <?php echo esc_attr( $modifier ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'logo_image' ); ?>>
		<?php if ( ! empty( $logo['url'] ) ) : ?>
			<img class="ik-logo__image" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( trim( $top . ' ' . $bottom ) ); ?>"<?php echo preg_match( '/\.svg(\?|$)/i', $logo['url'] ) ? ' height="48"' : ''; ?> decoding="async" />
		<?php else : ?>
			<svg class="ik-logo__mark" viewBox="0 0 32 40" width="32" height="40" aria-hidden="true" focusable="false">
				<path d="M16 2 7 13h5l-8 10h6L3 33h26l-7-10h6l-8-10h5z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" />
				<path d="M16 33v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
			</svg>
			<span class="ik-logo__text">
				<span class="ik-logo__top" <?php nwcs_edit_attr( 'global', 'header', 'logo_top' ); ?>><?php echo esc_html( $top ); ?></span>
				<span class="ik-logo__bottom" <?php nwcs_edit_attr( 'global', 'header', 'logo_bottom' ); ?>><?php echo esc_html( $bottom ); ?></span>
			</span>
		<?php endif; ?>
	</a>
	<?php
}

/* ======================================================================
 * Iletisim formu
 *
 * Sahte basari ekrani yok: gonderilen mesaj gercekten kaydedilir, yonetimde
 * "İletişim Mesajları" altinda gorulur. Bildirim e-postasini Network Content
 * Studio gonderir; alici info@kocist.com.tr (ik_form_recipient).
 * ====================================================================== */

add_action( 'init', 'ik_register_message_cpt' );
function ik_register_message_cpt(): void {
	register_post_type(
		'ik_message',
		array(
			'labels'          => array(
				'name'          => 'İletişim Mesajları',
				'singular_name' => 'İletişim Mesajı',
				'menu_name'     => 'İletişim Mesajları',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 26,
			'supports'        => array( 'title', 'editor', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'query_var'       => false,
		)
	);
}

// Bildirim e-postasi (Network Content Studio, includes/forms.php) her sitede
// ortak adrese gider; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'ik_form_recipient' );
function ik_form_recipient(): string {
	return 'info@kocist.com.tr';
}

add_action( 'admin_post_ik_message', 'ik_handle_message' );
add_action( 'admin_post_nopriv_ik_message', 'ik_handle_message' );
function ik_handle_message(): void {
	$redirect = home_url( '/iletisim/' );

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['ik_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['ik_name'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['ik_phone'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['ik_email'] ?? '' ) ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['ik_product'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['ik_message'] ?? '' ) ),
	);

	$errors = array();

	if ( ! isset( $_POST['ik_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ik_nonce'] ), 'ik_message' ) ) {
		$errors['form'] = 'Formun süresi dolmuş. Sayfayı yenileyip tekrar gönderin.';
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'Adınızı ve soyadınızı yazın.';
	}

	// Kerestede musteri cogunlukla telefonla doner; ikisinden biri yeterli.
	if ( '' === $values['phone'] && '' === $values['email'] ) {
		$errors['phone'] = 'Size dönebilmemiz için telefon ya da e-posta yazın.';
	}

	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		$errors['email'] = 'E-posta adresini kontrol edin; örnek: ad@firma.com';
	}

	if ( '' === $values['message'] ) {
		$errors['message'] = 'Ölçüyü ve adedi yazın.';
	}

	// Kucuk harf onaltilik: sanitize_key() ile okununca degismesin.
	$token = bin2hex( random_bytes( 8 ) );
	$back  = add_query_arg( 'mesaj', $token, $redirect ) . '#form';

	if ( ! $errors ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'ik_message',
				'post_status'  => 'private',
				'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ?: 'Ürün belirtilmedi' ),
				'post_content' => sprintf(
					"Telefon: %s\nE-posta: %s\nÜrün: %s\n\n%s",
					$values['phone'] ?: '—',
					$values['email'] ?: '—',
					$values['product'] ?: '—',
					$values['message']
				),
				'meta_input'   => array(
					'_ik_name'    => $values['name'],
					'_ik_phone'   => $values['phone'],
					'_ik_email'   => $values['email'],
					'_ik_product' => $values['product'],
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$errors['form'] = 'Mesaj kaydedilemedi. Lütfen telefonla ya da WhatsApp’tan ulaşın.';
		}
	}

	$state = $errors ? array( 'errors' => $errors, 'values' => $values ) : array( 'success' => true );

	set_transient( 'ik_msg_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( $back );
	exit;
}

/**
 * Yonlendirmeden sonra formun durumu (hatalar, girilen degerler, basari).
 */
function ik_message_state(): array {
	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false );
	$token = isset( $_GET['mesaj'] ) ? sanitize_key( wp_unslash( $_GET['mesaj'] ) ) : '';

	if ( '' === $token ) {
		return $empty;
	}

	$state = get_transient( 'ik_msg_' . $token );

	if ( ! is_array( $state ) ) {
		return $empty;
	}

	return array(
		'errors'  => $state['errors'] ?? array(),
		'values'  => $state['values'] ?? array(),
		'success' => ! empty( $state['success'] ),
	);
}

/**
 * SEO ve GEO: gorseller tema icinde oldugundan, gorseli olmayan sayfalar
 * paylasilinca ve arama sonucunda hero fotografi gorunur.
 */
add_filter(
	'nwcs_seo_default_image',
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/hero-orman.jpg', 'Orman yolunun kenarında istiflenmiş tomruklar' ) : 0
);

/**
 * Urun gorseli buyutme ve pencere ici yakinlastirma: yalnizca urun
 * sayfalarinda yuklenir. Surum dosya degisim zamanindan; guncellemede
 * tarayici eski dosyayi gostermez.
 */
add_action( 'wp_enqueue_scripts', 'ik_zoom_assets', 20 );
function ik_zoom_assets(): void {
	if ( ! ik_current_product() ) {
		return;
	}

	$ver = static fn( string $file ): string => (string) @filemtime( get_theme_file_path( $file ) );

	wp_enqueue_style( 'ik-zoom', get_theme_file_uri( 'assets/css/zoom.css' ), array( 'ik-site' ), $ver( 'assets/css/zoom.css' ) );
	wp_enqueue_script( 'ik-zoom-engine', get_theme_file_uri( 'assets/js/zoom.js' ), array(), $ver( 'assets/js/zoom.js' ), true );
	wp_enqueue_script( 'ik-product-zoom', get_theme_file_uri( 'assets/js/product-zoom.js' ), array( 'ik-zoom-engine' ), $ver( 'assets/js/product-zoom.js' ), true );
}

/**
 * Logo icin SVG yukleme: panelde "Logo Gorseli" alanina SVG secilebilsin
 * (sanayi-palet-theme ile ayni kural). SVG betik tasiyabildigi icin yalnizca
 * filtresiz HTML yetkisi olanlar (agda yalnizca super yonetici) yukleyebilir.
 */
add_filter( 'upload_mimes', 'ik_upload_mimes' );
function ik_upload_mimes( array $mimes ): array {
	if ( current_user_can( 'unfiltered_html' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

/**
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'ik_favicon', 2 );
add_action( 'login_head', 'ik_favicon' );
function ik_favicon(): void {
	if ( has_site_icon() ) {
		return;
	}

	printf(
		'<link rel="icon" type="image/png" sizes="32x32" href="%s" />' . "\n" .
		'<link rel="icon" type="image/svg+xml" href="%s" />' . "\n" .
		'<link rel="apple-touch-icon" href="%s" />' . "\n",
		esc_url( get_theme_file_uri( 'assets/favicon-32.png' ) ),
		esc_url( get_theme_file_uri( 'assets/favicon.svg' ) ),
		esc_url( get_theme_file_uri( 'assets/apple-touch-icon.png' ) )
	);
}

/**
 * Menu ve alt bilgi satirlarindan /sss/ baglantisini, sayfa henuz acilmamissa
 * (yayinda degilse) cikarir. Sayfa yonetici panele ilk girdiginde acilir;
 * o zamana kadar ziyaretci 404'e gitmesin, onbellege 404 baglantisi girmesin.
 * Panel onizlemesinde satir kalir ki duzenlenebilsin. Anahtarlar korunur:
 * panel isaretleri satir sirasini bunlardan okur.
 */
function ik_hide_missing_sss( array $rows ): array {
	static $has_page = null;

	if ( null === $has_page ) {
		$page     = get_page_by_path( 'sss', OBJECT, 'page' );
		$has_page = $page && 'publish' === $page->post_status;
	}

	if ( $has_page || ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
		return $rows;
	}

	return array_filter(
		$rows,
		static fn( $row ): bool => '/sss' !== untrailingslashit( (string) wp_parse_url( trim( (string) ( $row['url'] ?? '' ) ), PHP_URL_PATH ) )
	);
}

/*
 * Search Console: WordPress'in on yukleme kurallarindaki /wp-*.php,
 * /wp-content/* gibi kaliplari Google adres sanip 404 raporluyor; RSD
 * baglantisi (xmlrpc.php?rsd) sunucuda kapali oldugu icin 403 donuyor.
 */
add_filter( 'wp_speculation_rules_configuration', '__return_null' );
remove_action( 'wp_head', 'rsd_link' );
