<?php
/**
 * istanbulpaletci temasi (Istanbul Paletci yeniden tasarimi).
 *
 * Tema gorunur metinleri sabit yazmaz; tum degerler Network Content Studio
 * veri katmanindan (nwcs_*) okunur. Eklenti devre disi kalirsa sayfa fatal
 * vermesin diye asagida guvenli yedek fonksiyonlar tanimlanir.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nwcs_field' ) ) {
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		return null === $fallback ? '' : $fallback;
	}
	function nwcs_rows( $page, $component, $field ) {
		return array();
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
		return array( 'products', 'process', 'export', 'blog', 'quote' );
	}
}

add_action( 'after_setup_theme', 'ip_setup' );
function ip_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form', 'gallery', 'caption' ) );
}

add_action( 'wp_enqueue_scripts', 'ip_assets' );
function ip_assets(): void {
	// Surum = tema surumu + dosya degisim zamani: dosya her derlendiginde adres
	// degisir, tarayici ve onbellek eski CSS/JS'i gostermez.
	$ver = static fn( string $file ): string => wp_get_theme()->get( 'Version' ) . '.' . (int) @filemtime( get_theme_file_path( $file ) );

	// Tek CSS dosyasi: yerel Archivo + Tailwind + style.css, bu sirayla
	// (scripts/build-css.mjs uretir). Yerelde kaynaklardan biri site.css'ten
	// yeniyse (derleme unutulduysa) kaynaklar eskisi gibi ayri yuklenir; gorunum
	// ayni, duzenleme hemen gorunur. Canlida dosya tarihleri yuklemeye gore
	// degistigi icin bu kiyas yapilmaz: site.css varsa o kullanilir.
	$built = (int) @filemtime( get_theme_file_path( 'assets/site.css' ) );
	$fresh = $built > 0;

	if ( $fresh && 'local' === wp_get_environment_type() ) {
		foreach ( array( 'assets/fonts/archivo.css', 'assets/tailwind.css', 'style.css' ) as $source ) {
			if ( (int) @filemtime( get_theme_file_path( $source ) ) > $built ) {
				$fresh = false;
			}
		}
	}

	if ( $fresh ) {
		wp_enqueue_style( 'ip-site', get_theme_file_uri( 'assets/site.css' ), array(), $ver( 'assets/site.css' ) );
	} else {
		wp_enqueue_style( 'ip-fonts', get_theme_file_uri( 'assets/fonts/archivo.css' ), array(), $ver( 'assets/fonts/archivo.css' ) );
		wp_enqueue_style( 'ip-tailwind', get_theme_file_uri( 'assets/tailwind.css' ), array( 'ip-fonts' ), $ver( 'assets/tailwind.css' ) );
		wp_enqueue_style( 'ip-site', get_stylesheet_uri(), array( 'ip-tailwind' ), $ver( 'style.css' ) );
	}

	wp_enqueue_script( 'ip-nav', get_theme_file_uri( 'assets/nav.js' ), array(), $ver( 'assets/nav.js' ), true );
	wp_enqueue_script( 'ip-media', get_theme_file_uri( 'assets/media.js' ), array(), $ver( 'assets/media.js' ), true );
}

// Yazi tipi yerelde (assets/fonts). Turkce metnin cogu latin alt kumesinde;
// CSS'i beklemeden indirilsin diye on yukleme.
add_action( 'wp_head', 'ip_preload_font', 1 );
function ip_preload_font(): void {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_theme_file_uri( 'assets/fonts/archivo-latin.woff2' ) )
	);
}

/*
 * Temanin sonradan ekledigi sayfalar. Sayfalar ilk kurulumda betikle
 * (scripts/seed-istanbulpaletci.php) acildi; canlida WP-CLI olmadigi icin
 * yeni sayfayi tema kendisi acar: yonetici panele ilk girdiginde, surum
 * secenegi degismisse bir kez. Ayni adreste yayinda ya da taslak sayfa varsa
 * dokunulmaz (cop kutusundaki sayfa '__trashed' ekiyle adres degistirdigi icin
 * sayilmaz); surum secenegi yazildiktan sonra silinen sayfa geri gelmez.
 *
 * Surum 1: Sik Sorulan Sorular (/sss/).
 */
const IP_PAGES_VERSION = '1';

add_action( 'admin_init', 'ip_maybe_ensure_pages' );
function ip_maybe_ensure_pages(): void {
	// Yalnizca yonetici ya da WP-CLI: admin_init anonim admin-post.php
	// (teklif formu) isteklerinde de tetiklenir.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	if ( IP_PAGES_VERSION === get_option( 'ip_pages_version' ) ) {
		return;
	}

	$pages = array(
		'sss' => 'Sık Sorulan Sorular',
	);

	foreach ( $pages as $slug => $title ) {
		if ( ! get_page_by_path( $slug, OBJECT, 'page' ) ) {
			wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_name'   => $slug,
					'post_title'  => $title,
				)
			);
		}
	}

	update_option( 'ip_pages_version', IP_PAGES_VERSION );
}

/* ====================================================================== *
 * Genel yardimcilar
 * ====================================================================== */

/**
 * Bos baglantilari '#' yapar, koke gore yazilmis yollari site adresine baglar.
 * Alt dizin multisite'ta '/iletisim/' ag kokune gider; home_url ile duzeltiyoruz.
 */
function ip_link( $url ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	return $url;
}

/**
 * Istek adresinin yolu (sorgu ve capa olmadan), sonda egik cizgi yok.
 */
function ip_request_path(): string {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return untrailingslashit( (string) wp_parse_url( $request, PHP_URL_PATH ) );
}

/**
 * Baglantinin yolu; ip_request_path() ile ayni bicimde.
 */
function ip_link_path( string $url ): string {
	return untrailingslashit( (string) wp_parse_url( ip_link( $url ), PHP_URL_PATH ) );
}

/**
 * Menudeki baglanti bulunulan sayfa mi?
 */
function ip_is_current( string $url ): bool {
	return ip_link_path( $url ) === ip_request_path();
}

/**
 * Bulunulan sayfa bu baglantinin altinda mi? (Urun sayfasindayken
 * "Urunlerimiz" de isaretli kalsin diye.) Ana sayfa her seyin ustu
 * oldugu icin haric tutulur.
 */
function ip_is_within( string $url ): bool {
	$target = ip_link_path( $url );

	if ( $target === untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) ) {
		return false;
	}

	return str_starts_with( ip_request_path() . '/', $target . '/' );
}

/**
 * Gorsel alani icin img etiketi; deger yoksa sessiz bir yer tutucu.
 */
function ip_image_tag( array $image, string $class = '', string $alt_fallback = '', bool $eager = false ): string {
	if ( ! empty( $image['url'] ) ) {
		return sprintf(
			'<img src="%1$s" alt="%2$s" class="%3$s" %4$s decoding="async" />',
			esc_url( $image['url'] ),
			esc_attr( $image['alt'] ?: $alt_fallback ),
			esc_attr( $class ),
			$eager ? 'fetchpriority="high"' : 'loading="lazy"'
		);
	}

	return sprintf(
		'<span class="%1$s flex items-center justify-center bg-mist text-steel text-sm" role="img" aria-label="%2$s">%2$s</span>',
		esc_attr( $class ),
		esc_html( $alt_fallback ?: 'Görsel eklenmedi' )
	);
}

/**
 * Satir sonlari korunarak basilan metin.
 */
function ip_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

/**
 * nwcs_edit_attr() ciktisini metin olarak dondurur (sprintf ile kurulan
 * baglantilar icin). Onizleme disinda bos metindir.
 */
function ip_edit_attr( string $page, string $component, string $field = '', ?int $row = null, string $sub = '' ): string {
	if ( ! function_exists( 'nwcs_edit_attr' ) ) {
		return '';
	}

	ob_start();
	nwcs_edit_attr( $page, $component, $field, $row, $sub );

	return (string) ob_get_clean();
}

/**
 * Manifestte olmayan WordPress sayfasi (yasal metinler gibi): onizlemede
 * tiklaninca sayfanin duzenleme ekrani acilir. Ornek blog yazilari panelde
 * duzenlenir; onlar icin ip_post_edit_attr() kullanilir.
 */
function ip_post_attr( int $post_id, string $label = 'Yazı başlığı' ): void {
	if ( function_exists( 'nwcs_post_attr' ) ) {
		nwcs_post_attr( $post_id, $label );
	}
}

/**
 * Bos satirla ayrilmis metni paragraflara boler.
 */
function ip_paragraphs( string $value, string $class = '' ): string {
	$blocks = preg_split( '/\R\s*\R/u', trim( $value ) ) ?: array();
	$html   = '';

	foreach ( $blocks as $block ) {
		$block = trim( $block );

		if ( '' !== $block ) {
			$html .= sprintf( '<p%s>%s</p>', $class ? ' class="' . esc_attr( $class ) . '"' : '', ip_multiline( $block ) );
		}
	}

	return $html;
}

/**
 * Ana sayfa bolum dosyasini basar.
 */
function ip_section( string $key, array $args = array() ): void {
	$file = get_theme_file_path( "template-parts/sections/{$key}.php" );

	if ( file_exists( $file ) ) {
		include $file;
	}
}

/**
 * Temanin manifesti. Eklenti kapaliyken de urun listesi kurulabilsin diye
 * dosyadan dogrudan okunabilir.
 */
function ip_manifest(): array {
	if ( function_exists( 'nwcs_manifest' ) ) {
		return nwcs_manifest();
	}

	static $manifest = null;

	if ( null === $manifest ) {
		$loaded   = include get_theme_file_path( 'content-manifest.php' );
		$manifest = is_array( $loaded ) ? $loaded : array();
	}

	return $manifest;
}

/**
 * Manifestteki alanin varsayilan degeri.
 *
 * @return mixed
 */
function ip_manifest_default( string $page, string $component, string $field ) {
	return ip_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? '';
}

/* ====================================================================== *
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
function ip_post_page_key( $post ): string {
	$post = get_post( $post );

	if ( ! $post || 'post' !== $post->post_type || '' === $post->post_name ) {
		return '';
	}

	$key = 'yazi-' . $post->post_name;

	return isset( ip_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Panelde degistirilmis deger; alan varsayilanindaysa (yazinin ilk metni) null.
 *
 * @return string|int|null
 */
function ip_post_override( $post, string $field ) {
	$key = ip_post_page_key( $post );

	if ( '' === $key ) {
		return null;
	}

	$value = nwcs_field( $key, 'post', $field );

	if ( 'image' === $field ) {
		return (int) $value ? (int) $value : null;
	}

	$clean   = static fn( $text ): string => trim( str_replace( "\r\n", "\n", (string) $text ) );
	$value   = $clean( $value );
	$default = $clean( ip_manifest_default( $key, 'post', $field ) );

	return '' === $value || $value === $default ? null : $value;
}

/**
 * Onizlemede tiklaninca yazinin paneldeki alanini acar.
 */
function ip_post_edit_attr( $post, string $field ): void {
	$key = ip_post_page_key( $post );

	if ( '' !== $key ) {
		nwcs_edit_attr( $key, 'post', $field );
	}
}

/**
 * Panel metnini HTML'e cevirir: bos satir paragraf, '## ' ara baslik,
 * her satiri '- ' ile baslayan blok madde listesi.
 */
function ip_post_body_html( string $text ): string {
	$html = '';

	foreach ( preg_split( '/\R\s*\R/u', trim( $text ) ) ?: array() as $block ) {
		$block = trim( $block );
		$lines = preg_split( '/\R/u', $block ) ?: array();

		if ( '' === $block ) {
			continue;
		}

		if ( str_starts_with( $block, '## ' ) ) {
			$html .= '<h2>' . esc_html( trim( substr( $block, 3 ) ) ) . "</h2>\n";
		} elseif ( count( $lines ) === count( preg_grep( '/^\s*-\s+/u', $lines ) ) ) {
			$items = array_map( static fn( string $line ): string => '<li>' . esc_html( trim( (string) preg_replace( '/^\s*-\s+/u', '', $line ) ) ) . '</li>', $lines );
			$html .= "<ul>\n" . implode( "\n", $items ) . "\n</ul>\n";
		} else {
			$html .= '<p>' . ip_multiline( $block ) . "</p>\n";
		}
	}

	return $html;
}

/*
 * SEO: eklenti yazi bilgisini 'wp' aksiyonunda (10) hesaplayip sakliyor.
 * Hemen once, gizli sayfanin "Arama ve Paylasim" alanlari (yoksa paneldeki
 * baslik, ozet ve kapak) gecerliyken hesaplatilir.
 */
add_action( 'wp', 'ip_post_seo', 9 );
function ip_post_seo(): void {
	if ( ! is_singular( 'post' ) || ! function_exists( 'nwcs_seo_context' ) ) {
		return;
	}

	$post = get_queried_object();
	$key  = ip_post_page_key( $post );

	if ( '' === $key ) {
		return;
	}

	$description = trim( (string) nwcs_field( $key, 'seo', 'description' ) );
	$description = '' !== $description ? $description : (string) ip_post_override( $post, 'excerpt' );
	$original    = $post->post_excerpt;

	if ( '' !== $description ) {
		$post->post_excerpt = $description;
	}

	$GLOBALS['ip_post_seo_key'] = $key;
	nwcs_seo_context();
	unset( $GLOBALS['ip_post_seo_key'] );

	$post->post_excerpt = $original;
}

/**
 * Arama bilgisi hesaplanirken gecerli olan SEO alani; diger zamanlarda bos.
 */
function ip_post_seo_field( $post, string $field ): string {
	$key = $GLOBALS['ip_post_seo_key'] ?? '';

	return '' !== $key && ip_post_page_key( $post ) === $key ? trim( (string) nwcs_field( $key, 'seo', $field ) ) : '';
}

add_filter( 'the_title', 'ip_post_title', 10, 2 );
function ip_post_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! $post_id ) {
		return $title;
	}

	$seo = ip_post_seo_field( $post_id, 'title' );

	return '' !== $seo ? $seo : ( ip_post_override( $post_id, 'title' ) ?? $title );
}

add_filter( 'get_the_excerpt', 'ip_post_excerpt', 10, 2 );
function ip_post_excerpt( $excerpt, $post = null ) {
	return is_admin() ? $excerpt : ( ip_post_override( $post, 'excerpt' ) ?? $excerpt );
}

// wpautop'tan sonra: panel metni kendi paragraflariyla gelir.
add_filter( 'the_content', 'ip_post_content', 99 );
function ip_post_content( $content ) {
	if ( is_admin() || ! in_the_loop() ) {
		return $content;
	}

	$body = ip_post_override( get_the_ID(), 'body' );

	return null === $body ? $content : ip_post_body_html( $body );
}

add_filter( 'post_thumbnail_id', 'ip_post_thumbnail', 10, 2 );
function ip_post_thumbnail( $thumbnail_id, $post = null ) {
	if ( is_admin() ) {
		return $thumbnail_id;
	}

	$seo = (int) ip_post_seo_field( $post, 'image' );

	return $seo ? $seo : ( ip_post_override( $post, 'image' ) ?? $thumbnail_id );
}

/* ====================================================================== *
 * Urunler
 *
 * Urun listesinin tek kaynagi manifesttir: 'template' => 'product' isaretli
 * sayfalar. Ust menunun acilir paneli, ana sayfa izgarasi, Urunlerimiz
 * listesi, alt bilgi, form secenekleri ve "diger urunler" hep buradan beslenir.
 * ====================================================================== */

/**
 * @return array<string, array{key:string, path:string, url:string, name:string, short:string, size:string, image:array}>
 */
function ip_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	foreach ( ip_manifest()['pages'] ?? array() as $key => $page ) {
		if ( 'product' !== ( $page['template'] ?? '' ) ) {
			continue;
		}

		$products[ $key ] = array(
			'key'   => $key,
			'path'  => (string) ( $page['path'] ?? '/' ),
			'url'   => ip_link( $page['path'] ?? '/' ),
			'name'  => (string) nwcs_field( $key, 'card', 'name' ),
			'short' => (string) nwcs_field( $key, 'card', 'short' ),
			'size'  => (string) nwcs_field( $key, 'card', 'size' ),
			'image' => nwcs_image( $key, 'card', 'image', 'large' ),
		);
	}

	return $products;
}

/**
 * Bulunulan sayfa bir urun sayfasiysa onun manifest anahtari.
 */
function ip_current_product_key(): string {
	$current = ip_request_path();

	foreach ( ip_products() as $key => $product ) {
		if ( ip_link_path( $product['path'] ) === $current ) {
			return $key;
		}
	}

	return '';
}

/**
 * Urunun buyutulebilir galerisi: kart gorseli en basta, ardindan galeri
 * satirlari. Ayni gorsel iki kez eklenmez.
 *
 * @return array<int, array{url:string, alt:string, thumb:string}>
 */
function ip_product_gallery( string $key ): array {
	$ids = array( (int) nwcs_field( $key, 'card', 'image', 0 ) );

	foreach ( nwcs_rows( $key, 'detail', 'gallery' ) as $row ) {
		$ids[] = (int) ( $row['image'] ?? 0 );
	}

	$gallery = array();
	$name    = (string) nwcs_field( $key, 'card', 'name' );

	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		// Buyuk gorsel genis kolonu 2x ekranda da net doldursun: 1536 boy
		// (yoksa WordPress asil dosyayi verir). Kucukler orta boyda kalir.
		$large = nwcs_image_by_id( $id, '1536x1536' );
		$thumb = nwcs_image_by_id( $id, 'medium' );

		if ( '' === $large['url'] ) {
			continue;
		}

		$gallery[] = array(
			'url'   => $large['url'],
			'alt'   => $large['alt'] ?: $name,
			'thumb' => $thumb['url'] ?: $large['url'],
		);
	}

	return $gallery;
}

/**
 * Urun icin teklif formuna giden adres: form urunu secili getirir.
 */
function ip_quote_url( string $product_name ): string {
	$base = ip_link( nwcs_field( 'products', 'shared', 'quote_url' ) );

	return add_query_arg( 'urun', rawurlencode( $product_name ), $base ) . '#teklif';
}

/**
 * WhatsApp baglantisina hazir mesaj ekler. Mesaj yalnizca wa.me /
 * api.whatsapp.com adreslerine ve icinde zaten mesaj yoksa eklenir; panelde
 * baska bir adres (ya da hazir ?text=) girildiyse dokunulmaz.
 *
 * Urun verilirse mesaj urun adini ve sayfa adresini tasir, verilmezse
 * genel mesaj kullanilir. Iki metin de panelden degisir.
 */
function ip_whatsapp_url( $url, array $product = array() ): string {
	$link = ip_link( $url );
	$host = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );

	if ( ! in_array( $host, array( 'wa.me', 'api.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com' ), true ) ) {
		return $link;
	}

	parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );

	if ( '' !== trim( (string) ( $query['text'] ?? '' ) ) ) {
		return $link;
	}

	if ( ! empty( $product['name'] ) ) {
		$text = strtr(
			(string) nwcs_field( 'products', 'shared', 'whatsapp_message' ),
			array( '{urun}' => $product['name'] )
		);
		$text = trim( $text . ' ' . ( $product['url'] ?? '' ) );
	} else {
		$text = trim( (string) nwcs_field( 'global', 'header', 'whatsapp_message' ) );
	}

	if ( '' === $text ) {
		return $link;
	}

	return add_query_arg( 'text', rawurlencode( $text ), $link );
}

/* ====================================================================== *
 * Teklif formu
 *
 * Sahte basari ekrani yok: gonderilen form gercekten kaydedilir, yonetimde
 * "Teklif Istekleri" altinda gorulur. Ana sayfa ve Iletisim ayni formu
 * (template-parts/quote-form.php) ve ayni isleyiciyi kullanir.
 * ====================================================================== */

add_action( 'init', 'ip_register_quote_cpt' );
function ip_register_quote_cpt(): void {
	register_post_type(
		'ip_quote',
		array(
			'labels'          => array(
				'name'          => 'Teklif İstekleri',
				'singular_name' => 'Teklif İsteği',
				'menu_name'     => 'Teklif İstekleri',
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

/**
 * Yonlendirmeden sonra formun durumunu tasiyan gecici kaydin anahtari.
 * Kucuk harfli onaltilik: sanitize_key() buyuk harfi kucultur; karisik
 * harfli anahtar, harf duyarli nesne onbelleginde (LiteSpeed, Redis)
 * bulunamaz ve form hatasi/basari mesaji kaybolur.
 */
function ip_quote_token(): string {
	return bin2hex( random_bytes( 10 ) );
}

function ip_quote_redirect( string $redirect, string $token, array $state ): void {
	set_transient( 'ip_quote_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'ip', $token, $redirect ) . '#teklif' );
	exit;
}

// Bildirim e-postasi (Network Content Studio, includes/forms.php) her sitede
// ortak adrese gider; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'ip_form_recipient' );
function ip_form_recipient(): string {
	return 'info@kocist.com.tr';
}

add_action( 'admin_post_ip_quote', 'ip_handle_quote' );
add_action( 'admin_post_nopriv_ip_quote', 'ip_handle_quote' );
function ip_handle_quote(): void {
	// Referer bos ya da yabanciysa iletisim sayfasina doneriz. Sorgudaki eski
	// durum anahtari tasinmasin diye temizlenir.
	$fallback = home_url( '/iletisim/' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	$redirect = remove_query_arg( 'ip', '' !== $redirect ? $redirect : $fallback );
	$redirect = strtok( $redirect, '#' );

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['ip_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['ip_name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['ip_company'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['ip_email'] ?? '' ) ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['ip_phone'] ?? '' ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['ip_product'] ?? '' ) ),
		'size'    => sanitize_textarea_field( wp_unslash( $_POST['ip_size'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['ip_message'] ?? '' ) ),
	);

	$errors = array();
	$token  = ip_quote_token();

	if ( ! isset( $_POST['ip_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ip_quote_nonce'] ), 'ip_quote' ) ) {
		$errors['form'] = 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.';
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'Adınızı yazın.';
	}

	// E-posta ya da telefon: ikisinden biri yeterli, donus yapabilelim.
	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		$errors['email'] = 'E-posta adresi geçerli görünmüyor.';
	} elseif ( '' === $values['email'] && '' === $values['phone'] ) {
		$errors['email'] = 'Size dönebilmemiz için e-posta ya da telefon yazın.';
	}

	if ( '' === $values['size'] ) {
		$errors['size'] = 'Ölçü ve adet bilgisini yazın.';
	}

	// Hiz siniri (eklenti, nwcs_form_rate_*): yalnizca gecerli talepte.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = function_exists( 'nwcs_form_rate_message' ) ? nwcs_form_rate_message() : 'Lütfen biraz sonra yeniden deneyin.';
	}

	if ( $errors ) {
		ip_quote_redirect( $redirect, $token, array( 'errors' => $errors, 'values' => $values ) );
	}

	// Urun secenekleri manifestten gelir; baska bir deger kabul edilmez.
	$allowed = wp_list_pluck( ip_products(), 'name' );

	if ( ! in_array( $values['product'], $allowed, true ) ) {
		$values['product'] = 'Belirtilmedi';
	}

	$body = sprintf(
		"Firma: %s\nE-posta: %s\nTelefon: %s\nÜrün: %s\n\nÖlçüler ve adet:\n%s\n\nMesaj:\n%s",
		$values['company'] ?: '—',
		$values['email'] ?: '—',
		$values['phone'] ?: '—',
		$values['product'],
		$values['size'],
		$values['message'] ?: '—'
	);

	if ( function_exists( 'nwcs_form_rate_hit' ) ) {
		nwcs_form_rate_hit();
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'ip_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ),
			'post_content' => $body,
			'meta_input'   => array(
				'_ip_name'    => $values['name'],
				'_ip_company' => $values['company'],
				'_ip_email'   => $values['email'],
				'_ip_phone'   => $values['phone'],
				'_ip_product' => $values['product'],
				'_ip_size'    => $values['size'],
				'_ip_message' => $values['message'],
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		ip_quote_redirect(
			$redirect,
			$token,
			array(
				'errors' => array( 'form' => 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ),
				'values' => $values,
			)
		);
	}

	ip_quote_redirect( $redirect, $token, array( 'success' => true ) );
}

/**
 * Yonlendirmeden sonra formun durumunu (hata / deger / basari) getirir.
 */
function ip_quote_state(): array {
	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false );
	$token = isset( $_GET['ip'] ) ? sanitize_key( wp_unslash( $_GET['ip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return $empty;
	}

	$state = get_transient( 'ip_quote_' . $token );

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
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'ip_favicon', 2 );
add_action( 'login_head', 'ip_favicon' );
function ip_favicon(): void {
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
function ip_hide_missing_sss( array $rows ): array {
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
