<?php
/**
 * ahsapkasa temasi (Kocist Orman Urunleri yeniden tasarimi).
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
		return array( 'intro', 'family', 'ctaband' );
	}
}

add_action( 'after_setup_theme', 'ahsapkasa_setup' );
function ahsapkasa_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form' ) );
}

add_action( 'wp_enqueue_scripts', 'ahsapkasa_assets' );
function ahsapkasa_assets(): void {
	// Surum = tema surumu + dosya degisim zamani: dosya her derlendiginde adres
	// degisir, tarayici ve onbellek eski CSS/JS'i gostermez.
	$ver = static fn( string $file ): string => wp_get_theme()->get( 'Version' ) . '.' . (int) @filemtime( get_theme_file_path( $file ) );

	// Tek CSS dosyasi: assets/site.css (scripts/build-css.mjs uretir). Icinde
	// sirasiyla yerel Archivo (assets/fonts/fonts.css), derlenmis Tailwind ve
	// style.css var. Dosya yoksa (ya da yerelde kaynaklardan biri ondan yeniyse)
	// ayni dosyalar ayni sirayla ayri ayri yuklenir.
	$bundle  = 'assets/site.css';
	$sources = ahsapkasa_css_sources();

	if ( ahsapkasa_css_bundle_ready( $bundle, $sources ) ) {
		wp_enqueue_style( 'ahsapkasa-site', get_theme_file_uri( $bundle ), array(), $ver( $bundle ) );
	} else {
		$previous = array();

		foreach ( $sources as $handle => $file ) {
			wp_enqueue_style( $handle, get_theme_file_uri( $file ), $previous, $ver( $file ) );
			$previous = array( $handle );
		}
	}

	wp_enqueue_script( 'ahsapkasa-nav', get_theme_file_uri( 'assets/nav.js' ), array(), $ver( 'assets/nav.js' ), true );
	wp_enqueue_script( 'ahsapkasa-media', get_theme_file_uri( 'assets/media.js' ), array(), $ver( 'assets/media.js' ), true );
}

/*
 * Temanin sonradan ekledigi sayfalar. Sayfalar ilk kurulumda betikle
 * (scripts/seed-ahsapkasa.php) acildi; canlida WP-CLI olmadigi icin yeni
 * sayfayi tema kendisi acar: yonetici panele ilk girdiginde, surum secenegi
 * degismisse bir kez. Ayni adreste yayinda ya da taslak sayfa varsa dokunulmaz
 * (cop kutusundaki sayfa '__trashed' ekiyle adres degistirdigi icin sayilmaz).
 *
 * Surum 1: Sik Sorulan Sorular (/sss/).
 */
const AHSAPKASA_PAGES_VERSION = '1';

add_action( 'admin_init', 'ahsapkasa_maybe_ensure_pages' );
function ahsapkasa_maybe_ensure_pages(): void {
	// Yalnizca yonetici ya da WP-CLI: admin_init anonim admin-post.php
	// (teklif formu) isteklerinde de tetiklenir.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	if ( AHSAPKASA_PAGES_VERSION === get_option( 'ahsapkasa_pages_version' ) ) {
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

	update_option( 'ahsapkasa_pages_version', AHSAPKASA_PAGES_VERSION );
}

/**
 * Tek CSS dosyasinin kaynaklari, yukleme sirasiyla (tutamac => dosya).
 * scripts/build-css.mjs ayni sirayi kullanir.
 */
function ahsapkasa_css_sources(): array {
	return array(
		'ahsapkasa-fonts'    => 'assets/fonts/fonts.css',
		'ahsapkasa-tailwind' => 'assets/tailwind.css',
		'ahsapkasa-style'    => 'style.css',
	);
}

/**
 * Tek dosya kullanilabilir mi: dosya var ve (yalnizca yerelde) kaynaklardan
 * eski degil. Canlida dosya varsa her zaman o yuklenir.
 */
function ahsapkasa_css_bundle_ready( string $bundle, array $sources ): bool {
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
add_action( 'wp_head', 'ahsapkasa_preload_fonts', 1 );
function ahsapkasa_preload_fonts(): void {
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
function ahsapkasa_menu(): array {
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
function ahsapkasa_paragraphs( string $text ): string {
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
function ahsapkasa_link( $url ): string {
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
function ahsapkasa_whatsapp_url( $url ): string {
	$link = ahsapkasa_link( $url );
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
function ahsapkasa_is_current( string $url ): bool {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	$target  = untrailingslashit( (string) wp_parse_url( ahsapkasa_link( $url ), PHP_URL_PATH ) );
	$current = untrailingslashit( (string) wp_parse_url( $request, PHP_URL_PATH ) );

	return $target === $current;
}

/**
 * Gorsel alani icin img etiketi; deger yoksa sessiz bir yer tutucu.
 */
function ahsapkasa_image_tag( array $image, string $class = '', string $alt_fallback = '' ): string {
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
 * Panel gorseli icin srcset ve sizes: tarayici ekrana yetecek en kucuk
 * WordPress boyutunu indirir (gorunum ayni, dosya kucuk). Gorsel kimligi
 * yoksa ya da WordPress srcset uretemezse bos doner.
 *
 * Gorsel object-cover ile tam genislik bir kutuyu doldurur; kutu yuksekligi
 * ($box_rem, en buyuk kirilimdaki yukseklik) orana gore ekran genisligini
 * asarsa gorsel ekrandan genis cizilir. sizes bu genisligi soyler; yoksa
 * tarayici telefonda bulanik kalacak kucuk bir boyut secerdi.
 */
function ahsapkasa_srcset_attrs( array $image, float $box_rem ): string {
	$id     = (int) ( $image['id'] ?? 0 );
	$srcset = $id > 0 ? wp_get_attachment_image_srcset( $id, 'full' ) : false;
	$size   = ahsapkasa_image_dimensions( $image );

	if ( ! $srcset || ! $size ) {
		return '';
	}

	$cover = (string) ceil( $box_rem * $size[0] / $size[1] );

	return sprintf( ' srcset="%s" sizes="(max-width: %2$srem) %2$srem, 100vw"', esc_attr( $srcset ), esc_attr( $cover ) );
}

/**
 * Hero slaydi icin telefon kaynagi: <picture> icindeki <source>; yoksa bos.
 *
 * Hero kutusu en az 32rem yuksek; ekran 30em'den darsa kutunun orani en fazla
 * 30/32 = 0.9375. Gorsel object-cover ve ortada (object-position 50%); ortadan,
 * tam yukseklikte kesilmis ve orani 0.9375'ten az olmayan bir kopya ekranda ayni
 * pikselleri gosterir, dosya yaklasik yarisi. Kopyalar manifestteki
 * 'image_sizes' (ahsapkasa-hero-sm / -md) ile uretilir; yalnizca yandan
 * kesilmis (orani asilinkinden kucuk ya da esit) olanlar kullanilir.
 */
function ahsapkasa_hero_mobile_source( array $image ): string {
	$id   = (int) ( $image['id'] ?? 0 );
	$full = $id > 0 ? wp_get_attachment_image_src( $id, 'full' ) : false;

	if ( ! $full || empty( $full[1] ) || empty( $full[2] ) ) {
		return '';
	}

	$ratio  = $full[1] / $full[2];
	$aspect = null;
	$set    = array();

	foreach ( array( 'ahsapkasa-hero-sm', 'ahsapkasa-hero-md' ) as $name ) {
		$size = image_get_intermediate_size( $id, $name );

		if ( ! $size || empty( $size['width'] ) || empty( $size['height'] ) || empty( $size['url'] ) ) {
			continue;
		}

		$a = $size['width'] / $size['height'];

		// Kutudan dar ya da ustten/alttan kesilmis kopya olmaz; tek srcset'te tek oran.
		if ( $a < 0.9375 || $a > $ratio + 0.005 || ( null !== $aspect && abs( $a - $aspect ) > 0.005 ) ) {
			continue;
		}

		$aspect = $aspect ?? $a;
		$set[]  = $size['url'] . ' ' . (int) $size['width'] . 'w';
	}

	if ( ! $set ) {
		return '';
	}

	// Gorsel kutu yuksekligine (en az 32rem) gore cizilir: genislik = 32rem x oran.
	return sprintf( '<source media="(max-width: 30em)" srcset="%s" sizes="%srem" />', esc_attr( implode( ', ', $set ) ), esc_attr( (string) round( 32 * $aspect, 2 ) ) );
}

/**
 * Panel gorselinin piksel olculeri (width/height ozellikleri icin). Gorsel
 * kutusu yuklenmeden once dogru oranda yer ayrilsin.
 *
 * @return array{0:int,1:int}|null
 */
function ahsapkasa_image_dimensions( array $image, string $size = 'full' ): ?array {
	$id  = (int) ( $image['id'] ?? 0 );
	$src = $id > 0 ? wp_get_attachment_image_src( $id, $size ) : false;

	if ( ! $src || empty( $src[1] ) || empty( $src[2] ) ) {
		return null;
	}

	return array( (int) $src[1], (int) $src[2] );
}

/**
 * Satirlari korunarak basilan cok satirli metin.
 */
function ahsapkasa_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

/**
 * Icinde degisken olan panel metni: {sayi} gibi yer tutucular doldurulur.
 * Ornek: ahsapkasa_text( 'services', 'grid', 'gallery_count', array( 'sayi' => 3 ) ).
 */
function ahsapkasa_text( string $page, string $component, string $field, array $vars = array() ): string {
	$map = array();

	foreach ( $vars as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}

	return trim( strtr( (string) nwcs_field( $page, $component, $field ), $map ) );
}

/**
 * Panel onizlemesi mi? Eklenti kapaliyken tema yine calisir.
 */
function ahsapkasa_is_preview(): bool {
	return function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
}

/**
 * Bolum dosyasini basar.
 */
function ahsapkasa_section( string $key, array $args = array() ): void {
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

add_action( 'init', 'ahsapkasa_register_quote_cpt' );
function ahsapkasa_register_quote_cpt(): void {
	register_post_type(
		'ak_quote',
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
add_filter( 'nwcs_form_recipient', 'ahsapkasa_form_recipient' );
function ahsapkasa_form_recipient(): string {
	return 'info@kocist.com.tr';
}

add_action( 'admin_post_ahsapkasa_quote', 'ahsapkasa_handle_quote' );
add_action( 'admin_post_nopriv_ahsapkasa_quote', 'ahsapkasa_handle_quote' );
function ahsapkasa_handle_quote(): void {
	// Form yalnizca iletisim sayfasinda bulunur; referer bos gelirse oraya doneriz.
	$fallback = home_url( '/iletisim/' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;

	if ( '' === $redirect ) {
		$redirect = $fallback;
	}

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['ahsapkasa_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['ak_name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['ak_company'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['ak_email'] ?? '' ) ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['ak_phone'] ?? '' ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['ak_product'] ?? '' ) ),
		'size'    => sanitize_textarea_field( wp_unslash( $_POST['ak_size'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['ak_message'] ?? '' ) ),
	);

	$errors = array();

	if ( ! isset( $_POST['ahsapkasa_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ahsapkasa_quote_nonce'] ), 'ahsapkasa_quote' ) ) {
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

	// Kucuk harfli onaltilik: okurken sanitize_key() buyuk harfi kucultur.
	// Karisik harfli anahtar yerelde (harf duyarsiz MySQL) calisir ama harf
	// duyarli nesne onbelleginde (LiteSpeed, Redis) bulunamaz.
	$token = bin2hex( random_bytes( 10 ) );

	// Hiz siniri (eklenti, nwcs_form_rate_*): yalnizca gecerli talepte.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = function_exists( 'nwcs_form_rate_message' ) ? nwcs_form_rate_message() : 'Lütfen biraz sonra yeniden deneyin.';
	}

	if ( $errors ) {
		set_transient( 'ahsapkasa_quote_' . $token, array( 'errors' => $errors, 'values' => $values ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'ak', $token, $redirect ) . '#teklif' );
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
			'post_type'    => 'ak_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ),
			'post_content' => $body,
			'meta_input'   => array(
				'_ak_name'    => $values['name'],
				'_ak_company' => $values['company'],
				'_ak_email'   => $values['email'],
				'_ak_phone'   => $values['phone'],
				'_ak_product' => $values['product'],
				'_ak_size'    => $values['size'],
				'_ak_message' => $values['message'],
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		set_transient(
			'ahsapkasa_quote_' . $token,
			array( 'errors' => array( 'form' => 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ), 'values' => $values ),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'ak', $token, $redirect ) . '#teklif' );
		exit;
	}

	set_transient( 'ahsapkasa_quote_' . $token, array( 'success' => true ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'ak', $token, $redirect ) . '#teklif' );
	exit;
}

/**
 * Yonlendirmeden sonra formun durumunu (hata / deger / basari) getirir.
 */
function ahsapkasa_quote_state(): array {
	$token = isset( $_GET['ak'] ) ? sanitize_key( wp_unslash( $_GET['ak'] ) ) : '';

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return array( 'errors' => array(), 'values' => array(), 'success' => false );
	}

	$state = get_transient( 'ahsapkasa_quote_' . $token );

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
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'ahsapkasa_favicon', 2 );
add_action( 'login_head', 'ahsapkasa_favicon' );
function ahsapkasa_favicon(): void {
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
