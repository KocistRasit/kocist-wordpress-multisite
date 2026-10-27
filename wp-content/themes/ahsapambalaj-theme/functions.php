<?php
/**
 * ahsapambalaj temasi (ahsapambalajsanayi.com; ahsapkasa temasindan turetildi).
 *
 * Tema gorunur metinleri sabit yazmaz; tum degerler Network Content Studio
 * veri katmanindan (nwcs_*) okunur. Eklenti devre disi kalirsa sayfa fatal
 * vermesin diye asagida guvenli yedek fonksiyonlar tanimlanir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manifest dizisi (content-manifest.php); eklenti kapaliyken yedek
 * fonksiyonlar varsayilan metinleri buradan okur.
 */
function ahsapambalaj_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$file     = get_theme_file_path( 'content-manifest.php' );
		$loaded   = file_exists( $file ) ? include $file : array();
		$manifest = is_array( $loaded ) ? $loaded : array();
	}

	return $manifest;
}

if ( ! function_exists( 'nwcs_field' ) ) {
	// Eklenti kapaliyken sayfa bos kalmasin: manifestteki varsayilan metin.
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		if ( null !== $fallback ) {
			return $fallback;
		}
		$default = ahsapambalaj_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? '';
		return null === $default ? '' : $default;
	}
	function nwcs_rows( $page, $component, $field ) {
		$rows = nwcs_field( $page, $component, $field );
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
		return array( 'intro', 'stats', 'family', 'ctaband' );
	}
}

add_action( 'after_setup_theme', 'ahsapambalaj_setup' );
function ahsapambalaj_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form' ) );
}

add_action( 'wp_enqueue_scripts', 'ahsapambalaj_assets' );
function ahsapambalaj_assets(): void {
	// Surum = tema surumu + varliklarin en son degisim zamani: CSS/JS her
	// degistiginde adres degisir, tarayici ve onbellek eski dosyayi gostermez.
	$files   = array_merge( glob( get_theme_file_path( 'assets/*.{css,js}' ), GLOB_BRACE ) ?: array(), glob( get_theme_file_path( 'assets/*/*.{css,js}' ), GLOB_BRACE ) ?: array() );
	$version = wp_get_theme()->get( 'Version' ) . '.' . ( $files ? max( array_map( 'filemtime', $files ) ) : 0 );

	// Tek CSS dosyasi: assets/site.css (scripts/build-css.mjs uretir). Icinde
	// sirasiyla yerel Archivo (assets/fonts/fonts.css), derlenmis Tailwind,
	// style.css ve elle yazilmis duzeltmeler (assets/theme.css) var. Dosya yoksa
	// (ya da yerelde kaynaklardan biri ondan yeniyse) ayni dosyalar ayni sirayla
	// ayri ayri yuklenir.
	$bundle  = 'assets/site.css';
	$sources = ahsapambalaj_css_sources();

	if ( ahsapambalaj_css_bundle_ready( $bundle, $sources ) ) {
		wp_enqueue_style( 'ahsapambalaj-site', get_theme_file_uri( $bundle ), array(), wp_get_theme()->get( 'Version' ) . '.' . (int) filemtime( get_theme_file_path( $bundle ) ) );
	} else {
		$previous = array();

		foreach ( $sources as $handle => $file ) {
			wp_enqueue_style( $handle, get_theme_file_uri( $file ), $previous, wp_get_theme()->get( 'Version' ) . '.' . (int) @filemtime( get_theme_file_path( $file ) ) );
			$previous = array( $handle );
		}
	}

	wp_enqueue_script( 'ahsapambalaj-nav', get_theme_file_uri( 'assets/nav.js' ), array(), $version, true );
	wp_enqueue_script( 'ahsapambalaj-media', get_theme_file_uri( 'assets/media.js' ), array(), $version, true );
	wp_enqueue_script( 'ahsapambalaj-motion', get_theme_file_uri( 'assets/motion.js' ), array(), $version, true );
}

/**
 * Tek CSS dosyasinin kaynaklari, yukleme sirasiyla (tutamac => dosya).
 * scripts/build-css.mjs ayni sirayi kullanir.
 */
function ahsapambalaj_css_sources(): array {
	return array(
		'ahsapambalaj-fonts'    => 'assets/fonts/fonts.css',
		'ahsapambalaj-tailwind' => 'assets/tailwind.css',
		'ahsapambalaj-style'    => 'style.css',
		'ahsapambalaj-theme'    => 'assets/theme.css',
	);
}

/**
 * Tek dosya kullanilabilir mi: dosya var ve (yalnizca yerelde) kaynaklardan
 * eski degil. Canlida dosya varsa her zaman o yuklenir.
 */
function ahsapambalaj_css_bundle_ready( string $bundle, array $sources ): bool {
	$path = get_theme_file_path( $bundle );

	if ( ! is_readable( $path ) ) {
		return false;
	}

	if ( 'local' !== wp_get_environment_type() ) {
		return true;
	}

	$built = (int) filemtime( $path );

	foreach ( $sources as $file ) {
		if ( (int) @filemtime( get_theme_file_path( $file ) ) > $built ) {
			return false;
		}
	}

	return true;
}

/**
 * Archivo yerelden geliyor: Turkce metin hem latin hem latin-ext alt kumesini
 * kullandigi icin (ı latin'de; ş, ğ, İ latin-ext'te) ikisi de erken istenir.
 */
add_action( 'wp_head', 'ahsapambalaj_preload_fonts', 1 );
function ahsapambalaj_preload_fonts(): void {
	foreach ( array( 'archivo-latin.woff2', 'archivo-latin-ext.woff2' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_theme_file_uri( 'assets/fonts/' . $font ) )
		);
	}
}

/**
 * Ust menu ve alt bilgi satirlari. /sss/ baglantisi yalnizca sayfa acilmis
 * (yayinda) ve en az bir cevapli soru varken gorunur (cevabi bos soru sitede
 * gorunmez; bos ya da henuz olmayan sayfaya menuden gidilmesin). Panel onizlemesinde satir kalir ki
 * duzenlenebilsin. Anahtarlar korunur: panel isaretleri satir sirasini
 * bunlardan okur.
 */
function ahsapambalaj_menu(): array {
	$menu = nwcs_rows( 'global', 'header', 'menu' );

	$page = get_page_by_path( 'sss', OBJECT, 'page' );

	if ( ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) || ( $page && 'publish' === $page->post_status && function_exists( 'nwcs_faq_rows' ) && nwcs_faq_rows( 'faq' ) ) ) {
		return $menu;
	}

	return array_filter(
		$menu,
		static fn( $item ): bool => '/sss' !== untrailingslashit( (string) wp_parse_url( trim( (string) ( $item['url'] ?? '' ) ), PHP_URL_PATH ) )
	);
}

/**
 * Cok paragrafli cevap: bos satirla ayrilan her blok bir paragraf.
 */
function ahsapambalaj_paragraphs( string $text ): string {
	$html = '';

	foreach ( preg_split( '/\R\s*\R/u', trim( $text ) ) ?: array() as $block ) {
		$block = trim( $block );

		if ( '' !== $block ) {
			$html .= '<p>' . nl2br( esc_html( $block ) ) . '</p>';
		}
	}

	return $html;
}

/**
 * Bos baglantilari '#' yapar, koke gore yazilmis yollari site adresine baglar.
 * Alt dizin multisite'ta '/iletisim/' ag kokune gider; home_url ile duzeltiyoruz.
 */
function ahsapambalaj_link( $url ): string {
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
 * WhatsApp baglantisi, paneldeki hazir mesajla. Mesaj yalnizca wa.me /
 * api.whatsapp.com adreslerine ve adreste zaten ?text= yoksa eklenir.
 */
function ahsapambalaj_whatsapp_url( $url ): string {
	$link = ahsapambalaj_link( $url );
	$host = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );

	if ( ! in_array( $host, array( 'wa.me', 'api.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com' ), true ) ) {
		return $link;
	}

	parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
	$text = trim( (string) nwcs_field( 'global', 'header', 'whatsapp_message' ) );

	if ( '' === $text || '' !== trim( (string) ( $query['text'] ?? '' ) ) ) {
		return $link;
	}

	return add_query_arg( 'text', rawurlencode( $text ), $link );
}

/**
 * Menudeki baglantinin bulunulan sayfa olup olmadigini soyler.
 */
function ahsapambalaj_is_current( string $url ): bool {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	$target  = untrailingslashit( (string) wp_parse_url( ahsapambalaj_link( $url ), PHP_URL_PATH ) );
	$current = untrailingslashit( (string) wp_parse_url( $request, PHP_URL_PATH ) );

	return $target === $current;
}

/**
 * Gorsel alani icin img etiketi; deger yoksa sessiz bir yer tutucu.
 */
function ahsapambalaj_image_tag( array $image, string $class = '', string $alt_fallback = '' ): string {
	if ( ! empty( $image['url'] ) ) {
		return sprintf(
			'<img src="%1$s" alt="%2$s" class="%3$s" loading="lazy" decoding="async" />',
			esc_url( $image['url'] ),
			esc_attr( $image['alt'] ?: $alt_fallback ),
			esc_attr( $class )
		);
	}

	return sprintf(
		'<div class="%1$s flex items-center justify-center bg-dust text-moss font-display text-xs tracking-[0.14em]" role="img" aria-label="%2$s">%2$s</div>',
		esc_attr( $class ),
		esc_html( $alt_fallback ?: 'Görsel eklenmedi' )
	);
}

/**
 * Panel gorselinin piksel olculeri (width/height ozellikleri icin). Tema
 * yedek gorsellerinde (kimlik 0) null doner.
 *
 * @return array{0:int,1:int}|null
 */
function ahsapambalaj_image_dimensions( array $image, string $size = 'full' ): ?array {
	$id  = (int) ( $image['id'] ?? 0 );
	$src = $id > 0 ? wp_get_attachment_image_src( $id, $size ) : false;

	if ( ! $src || empty( $src[1] ) || empty( $src[2] ) ) {
		return null;
	}

	return array( (int) $src[1], (int) $src[2] );
}

/**
 * Panel gorseli icin srcset ve sizes: tarayici ekrana yetecek en kucuk
 * WordPress boyutunu indirir (gorunum ayni, dosya kucuk). Tema yedek
 * gorsellerinde (tek boyut) ya da srcset uretilemezse bos doner.
 *
 * Gorsel object-cover ile tam genislik bir kutuyu doldurur; kutu yuksekligi
 * ($box_rem, en buyuk kirilimdaki yukseklik) orana gore ekran genisligini
 * asarsa gorsel ekrandan genis cizilir. $scale: kutuya uygulanan buyutme
 * (hero'da parallax 1.08 x ken burns 1.12). sizes bu genisligi soyler; yoksa
 * tarayici telefonda bulanik kalacak kucuk bir boyut secerdi.
 */
function ahsapambalaj_srcset_attrs( array $image, float $box_rem, float $scale = 1.0 ): string {
	$id     = (int) ( $image['id'] ?? 0 );
	$srcset = $id > 0 ? wp_get_attachment_image_srcset( $id, 'full' ) : false;
	$size   = ahsapambalaj_image_dimensions( $image );

	if ( ! $srcset || ! $size ) {
		return '';
	}

	$cover = ceil( $box_rem * $size[0] / $size[1] * $scale );

	return sprintf(
		' srcset="%1$s" sizes="(max-width: %2$srem) %3$srem, %4$svw"',
		esc_attr( $srcset ),
		esc_attr( (string) ceil( $cover / $scale ) ),
		esc_attr( (string) $cover ),
		esc_attr( (string) ceil( 100 * $scale ) )
	);
}

/**
 * Hero tema gorseli icin telefon kaynagi: <picture> icindeki <source>.
 *
 * Hero kutusu en az 32rem yuksek; ekran 30em'den darsa kutunun orani en fazla
 * 30/32 = 0.9375. Gorsel object-cover ve ortada; bu oranda gorunen kisim
 * gorselin ortadaki %53'u. "<ad>-telefon.webp" ayni fotografin ortadan, tam
 * yukseklikte kesilmis 848 x 900 parcasi (1600 x 900'un ortasi; oran 0.94):
 * telefonda ekranda gorunen pikseller ayni, dosya yaklasik yarisi. Parallax ve
 * ken burns ayni kutuya uygulandigi icin sonuc degismez.
 *
 * Yalnizca tema yedek gorsellerinde (kimlik 0) ve dosya varsa; yoksa bos.
 * Kirpimlar: Pillow, hero-N.jpg'den crop((376, 0, 1224, 900)), WebP q81.
 */
function ahsapambalaj_hero_mobile_source( array $image ): string {
	$url = (string) ( $image['url'] ?? '' );

	if ( (int) ( $image['id'] ?? 0 ) > 0 || ! preg_match( '~/assets/img/([a-z0-9-]+)\.webp$~', $url, $match ) ) {
		return '';
	}

	$file = $match[1] . '-telefon.webp';

	if ( ! file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
		return '';
	}

	return sprintf( '<source media="(max-width: 30em)" srcset="%s" type="image/webp" />', esc_url( get_theme_file_uri( 'assets/img/' . $file ) ) );
}

/**
 * Gorsel alani bossa temanin kendi gorselini (assets/img) dondurur.
 * Panelden secilen gorsel her zaman kazanir; yedek yalnizca bos alani doldurur.
 * Boylece tema, seed ya da medya yuklemesi olmadan da eksiksiz gorunur.
 */
function ahsapambalaj_image_or_default( array $image, string $file, string $alt = '' ): array {
	if ( ! empty( $image['url'] ) || '' === $file || ! file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
		return $image;
	}

	return array(
		'id'  => 0,
		'url' => get_theme_file_uri( 'assets/img/' . $file ),
		'alt' => '' !== ( $image['alt'] ?? '' ) ? $image['alt'] : $alt,
	);
}

/**
 * Yedek gorseller: hangi alan bos kalirsa hangi dosya ve alt metin gelir.
 * Tekrarli alanlarda sira numarasina gore secilir. Dosyalar WebP (ayni piksel
 * boyutu); eski JPG/PNG kopyalari assets/img'de duruyor (SEO paylasim gorseli
 * hero-1.jpg'yi kullaniyor, eski adresler de calismaya devam etsin).
 */
function ahsapambalaj_default_images(): array {
	$img = array(
		'logo'   => array( 'logo.webp', 'Koçist Orman Ürünleri logosu' ),
		'hero-1' => array( 'hero-1.webp', 'Farklı ölçülerde üretilmiş ahşap sandık ve kasalar' ),
		'hero-2' => array( 'hero-2.webp', 'Tesis önünde sevkiyata hazır bekleyen ahşap sandıklar' ),
		'hero-3' => array( 'hero-3.webp', 'Atölyede istiflenmiş ahşap kasa ve sandıklar' ),
		'atolye' => array( 'atolye.webp', 'Çatalca atölyesinde ölçüye göre üretilen ahşap sandık' ),
		'palet'  => array( 'palet.webp', 'Üst üste dizilmiş ahşap paletler' ),
		'sandik' => array( 'sandik.webp', 'Sevkiyata hazır kapalı ahşap sandık' ),
		'kafes'  => array( 'kafes.webp', 'Ölçüye göre üretilmiş ahşap kafes' ),
	);

	return array(
		'logo'     => $img['logo'],
		// Hakkimizda hero'su: tek kareli atolye fotografi (kolaj degil), koyu perde altinda da okunur.
		'about'    => array( 'atolye.webp', 'Çatalca atölyesinde kereste istifleri önünde üretilmiş ahşap sandık' ),
		'slides'   => array( $img['hero-1'], $img['hero-2'], $img['hero-3'] ),
		'family'   => array( $img['palet'], $img['sandik'], $img['kafes'], $img['atolye'] ),
		// Hizmetler: her urunun kapak + ek gorselleri (image, image_2, image_3).
		'services' => array(
			array( $img['palet'], $img['hero-2'], $img['hero-3'] ),
			array( $img['sandik'], $img['hero-1'], $img['atolye'] ),
			array( $img['kafes'], $img['hero-3'], $img['hero-1'] ),
			array( $img['atolye'], $img['hero-2'], $img['hero-1'] ),
		),
	);
}

/**
 * Kisayol: ahsapambalaj_default_images() icindeki yola gore yedekli gorsel.
 * Ornek: ahsapambalaj_image( $image, 'family', 2 ) ya da ( $image, 'services', 1, 0 ).
 */
function ahsapambalaj_image( array $image, string $group, ?int $index = null, ?int $sub = null ): array {
	$entry = ahsapambalaj_default_images()[ $group ] ?? null;

	if ( null !== $index ) {
		$entry = $entry[ $index ] ?? null;
	}

	if ( null !== $sub ) {
		$entry = $entry[ $sub ] ?? null;
	}

	if ( ! is_array( $entry ) || ! is_string( $entry[0] ?? null ) ) {
		return $image;
	}

	return ahsapambalaj_image_or_default( $image, $entry[0], $entry[1] ?? '' );
}

/* ====================================================================== *
 * Kurulum: sayfalar ve site ayarlari
 *
 * Tema etkinlestirildiginde (ve surum degistiginde bir kez yonetim
 * panelinde) menudeki sayfalari olusturur. Idempotent: ayni slug varsa
 * dokunmaz, kullanicinin sayfasini ezmez.
 * ====================================================================== */

const AHSAPAMBALAJ_SETUP_VERSION = '2'; // 2: Sik Sorulan Sorular (/sss/).

add_action( 'after_switch_theme', 'ahsapambalaj_setup_site' );
add_action( 'admin_init', 'ahsapambalaj_maybe_setup_site' );

function ahsapambalaj_maybe_setup_site(): void {
	// Yedek yol yalnizca yonetici ya da WP-CLI icin: admin_init anonim
	// admin-post.php (form) isteklerinde de tetiklenir; ziyaretci kurulumu
	// (sayfa acma, kalici baglanti, rewrite) baslatamasin.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	if ( get_option( 'ahsapambalaj_setup_version' ) !== AHSAPAMBALAJ_SETUP_VERSION ) {
		ahsapambalaj_setup_site();
	}
}

function ahsapambalaj_setup_site(): void {
	if ( AHSAPAMBALAJ_SETUP_VERSION === get_option( 'ahsapambalaj_setup_version' ) ) {
		return;
	}

	// Ayni anda iki istek gelirse sayfalar iki kez acilmasin.
	if ( get_transient( 'ahsapambalaj_setup_lock' ) ) {
		return;
	}

	set_transient( 'ahsapambalaj_setup_lock', 1, MINUTE_IN_SECONDS );

	$pages = array(
		'hakkimizda'    => 'Hakkımızda',
		'hizmetlerimiz' => 'Hizmetlerimiz',
		'iletisim'      => 'İletişim',
		'sss'           => 'Sık Sorulan Sorular',
	);

	foreach ( $pages as $slug => $title ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => $slug,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
	}

	// Ana sayfa front-page.php'den gelsin; statik bir sayfa secilmemisse
	// yazi listesi ayarini koruruz (front-page.php her iki durumda da kullanilir).
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'posts' );
	}

	// Menudeki /hakkimizda/ gibi adresler duz kalici baglantiyla 404 verir.
	// Yalnizca hic ayarlanmamissa (varsayilan "duz") yazi adina cevrilir.
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}

	flush_rewrite_rules( false );
	update_option( 'ahsapambalaj_setup_version', AHSAPAMBALAJ_SETUP_VERSION );
}

/**
 * Satirlari korunarak basilan cok satirli metin.
 */
function ahsapambalaj_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

/**
 * Icinde degisken olan panel metni: {sayi} gibi yer tutucular doldurulur.
 * Ornek: ahsapambalaj_text( 'services', 'grid', 'gallery_count', array( 'sayi' => 3 ) ).
 */
function ahsapambalaj_text( string $page, string $component, string $field, array $vars = array() ): string {
	$map = array();

	foreach ( $vars as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}

	return trim( strtr( (string) nwcs_field( $page, $component, $field ), $map ) );
}

/**
 * Panel onizlemesi mi? Eklenti kapaliyken tema yine calisir.
 */
function ahsapambalaj_is_preview(): bool {
	return function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
}

/**
 * Bolum dosyasini basar.
 */
function ahsapambalaj_section( string $key, array $args = array() ): void {
	$file = get_theme_file_path( "template-parts/sections/{$key}.php" );

	if ( file_exists( $file ) ) {
		include $file;
	}
}

/* ====================================================================== *
 * Teklif formu
 *
 * Demo sahte basari ekrani gostermez: gonderilen form gercekten kaydedilir,
 * yonetimde "Teklif İstekleri" altinda gorulur.
 * ====================================================================== */

add_action( 'init', 'ahsapambalaj_register_quote_cpt' );
function ahsapambalaj_register_quote_cpt(): void {
	register_post_type(
		'aas_quote',
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

// Bildirim e-postasi (Network Content Studio, includes/forms.php) her sitede
// ortak adrese gider; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'ahsapambalaj_form_recipient' );
function ahsapambalaj_form_recipient(): string {
	return 'info@kocist.com.tr';
}

add_action( 'admin_post_ahsapambalaj_quote', 'ahsapambalaj_handle_quote' );
add_action( 'admin_post_nopriv_ahsapambalaj_quote', 'ahsapambalaj_handle_quote' );
function ahsapambalaj_handle_quote(): void {
	// Form yalnizca iletisim sayfasinda bulunur; referer bos gelirse oraya doneriz.
	$fallback = home_url( '/iletisim/' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;

	if ( '' === $redirect ) {
		$redirect = $fallback;
	}

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['ahsapambalaj_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['aas_name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['aas_company'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['aas_email'] ?? '' ) ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['aas_phone'] ?? '' ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['aas_product'] ?? '' ) ),
		'size'    => sanitize_textarea_field( wp_unslash( $_POST['aas_size'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['aas_message'] ?? '' ) ),
	);

	$errors = array();

	if ( ! isset( $_POST['ahsapambalaj_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ahsapambalaj_quote_nonce'] ), 'ahsapambalaj_quote' ) ) {
		$errors['form'] = 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.';
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'Adınızı yazın.';
	}

	if ( '' === $values['email'] || ! is_email( $values['email'] ) ) {
		$errors['email'] = 'Geçerli bir e-posta adresi yazın.';
	}

	if ( '' === $values['size'] ) {
		$errors['size'] = 'En, boy ve yükseklik bilgisini yazın.';
	}

	// Kucuk harf onaltilik: okurken sanitize_key kucultur; karisik harfli
	// belirtec nesne onbellegi (Redis, LiteSpeed) olan sunucuda bulunamazdi.
	$token = bin2hex( random_bytes( 10 ) );

	// Hiz siniri (eklenti, nwcs_form_rate_*): yalnizca gecerli talepte.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = function_exists( 'nwcs_form_rate_message' ) ? nwcs_form_rate_message() : 'Lütfen biraz sonra yeniden deneyin.';
	}

	if ( $errors ) {
		set_transient( 'ahsapambalaj_quote_' . $token, array( 'errors' => $errors, 'values' => $values ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'aas', $token, $redirect ) . '#teklif' );
		exit;
	}

	// Urun secenekleri manifestten gelir; elle gonderilen baska bir deger kabul edilmez.
	$allowed_products = array();
	foreach ( nwcs_rows( 'services', 'grid', 'items' ) as $row ) {
		if ( ! empty( $row['title'] ) ) {
			$allowed_products[] = $row['title'];
		}
	}

	if ( ! in_array( $values['product'], $allowed_products, true ) ) {
		$values['product'] = 'Belirtilmedi';
	}

	$body = sprintf(
		"Firma: %s\nE-posta: %s\nTelefon: %s\nÜrün: %s\n\nÖlçüler ve adet:\n%s\n\nMesaj:\n%s",
		$values['company'] ?: '—',
		$values['email'],
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
			'post_type'    => 'aas_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ),
			'post_content' => $body,
			'meta_input'   => array(
				'_aas_name'    => $values['name'],
				'_aas_company' => $values['company'],
				'_aas_email'   => $values['email'],
				'_aas_phone'   => $values['phone'],
				'_aas_product' => $values['product'],
				'_aas_size'    => $values['size'],
				'_aas_message' => $values['message'],
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		set_transient(
			'ahsapambalaj_quote_' . $token,
			array( 'errors' => array( 'form' => 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ), 'values' => $values ),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'aas', $token, $redirect ) . '#teklif' );
		exit;
	}

	set_transient( 'ahsapambalaj_quote_' . $token, array( 'success' => true ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'aas', $token, $redirect ) . '#teklif' );
	exit;
}

/**
 * Yonlendirmeden sonra formun durumunu (hata / deger / basari) getirir.
 */
function ahsapambalaj_quote_state(): array {
	$token = isset( $_GET['aas'] ) ? sanitize_key( wp_unslash( $_GET['aas'] ) ) : '';
	$token = preg_match( '/^[a-f0-9]{20}$/', $token ) ? $token : '';

	if ( ! $token ) {
		return array( 'errors' => array(), 'values' => array(), 'success' => false );
	}

	$state = get_transient( 'ahsapambalaj_quote_' . $token );

	if ( ! is_array( $state ) ) {
		return array( 'errors' => array(), 'values' => array(), 'success' => false );
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
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/hero-1.jpg', 'Ahşap sandık ve kafes üretimi' ) : 0
);

/**
 * Logo icin SVG yukleme: panelde "Logo Gorseli" alanina SVG secilebilsin
 * (sanayi-palet-theme ile ayni kural). SVG betik tasiyabildigi icin yalnizca
 * filtresiz HTML yetkisi olanlar (agda yalnizca super yonetici) yukleyebilir.
 */
add_filter( 'upload_mimes', 'ahsapambalaj_upload_mimes' );
function ahsapambalaj_upload_mimes( array $mimes ): array {
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
add_action( 'wp_head', 'ahsapambalaj_favicon', 2 );
add_action( 'login_head', 'ahsapambalaj_favicon' );
function ahsapambalaj_favicon(): void {
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

/*
 * Search Console: WordPress'in on yukleme kurallarindaki /wp-*.php,
 * /wp-content/* gibi kaliplari Google adres sanip 404 raporluyor; RSD
 * baglantisi (xmlrpc.php?rsd) sunucuda kapali oldugu icin 403 donuyor.
 */
add_filter( 'wp_speculation_rules_configuration', '__return_null' );
remove_action( 'wp_head', 'rsd_link' );
