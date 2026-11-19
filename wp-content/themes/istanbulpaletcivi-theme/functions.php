<?php
/**
 * istanbulpaletcivi temasi (Istanbul Palet Civi yeniden tasarimi).
 *
 * Gorunur metinler Network Content Studio veri katmanindan (nwcs_*) okunur;
 * varsayilanlari content-manifest.php'dedir. Gorseller tema klasorundedir
 * (assets/img): panelden gorsel secilmemis alan temanin gorselini gosterir.
 * Boylece tema, medya yuklemesi ya da veritabani tasimasi olmadan eksiksiz
 * kurulur (bkz. inc/setup.php).
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
	function nwcs_the_icon( $key, $class = '', $size = 24 ) {}
	function nwcs_edit_attr( $page, $component, $field = '', $row = null, $sub = '' ) {}
}

require_once get_theme_file_path( 'inc/posts.php' );
require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/quote.php' );
require_once get_theme_file_path( 'inc/catalog.php' );
require_once get_theme_file_path( 'inc/catalog-images.php' );

add_action( 'after_setup_theme', 'pc_setup' );
function pc_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form', 'gallery', 'caption' ) );
}

add_action( 'wp_enqueue_scripts', 'pc_assets' );
function pc_assets(): void {
	// Surum = tema surumu + dosya degisim zamani: dosya degisince adres
	// degisir, tarayici ve onbellek eski CSS/JS'i gostermez.
	$ver = static fn( string $file ): string => wp_get_theme()->get( 'Version' ) . '.' . (int) @filemtime( get_theme_file_path( $file ) );

	wp_enqueue_style(
		'pc-fonts',
		'https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'pc-site', get_theme_file_uri( 'assets/site.css' ), array( 'pc-fonts' ), $ver( 'assets/site.css' ) );
	// Urun gorseli tam ekraninda ikinci kademe yakinlastirma (kereste temalariyla ayni motor).
	wp_enqueue_script( 'pc-zoom', get_theme_file_uri( 'assets/zoom.js' ), array(), $ver( 'assets/zoom.js' ), true );
	wp_enqueue_script( 'pc-site', get_theme_file_uri( 'assets/site.js' ), array( 'pc-zoom' ), $ver( 'assets/site.js' ), true );
}

add_action( 'wp_head', 'pc_preconnect', 1 );
function pc_preconnect(): void {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}

/* ====================================================================== *
 * Genel yardimcilar
 * ====================================================================== */

/**
 * Koke gore yazilmis yollari site adresine baglar (alt dizin multisite'ta
 * '/iletisim/' ag kokune gider); bos baglanti '#'.
 */
function pc_link( $url ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	return $url;
}

function pc_request_path(): string {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return untrailingslashit( (string) wp_parse_url( $request, PHP_URL_PATH ) );
}

function pc_link_path( string $url ): string {
	return untrailingslashit( (string) wp_parse_url( pc_link( $url ), PHP_URL_PATH ) );
}

function pc_is_current( string $url ): bool {
	return pc_link_path( $url ) === pc_request_path();
}

/**
 * Bulunulan sayfa bu baglantinin altinda mi (urun sayfasinda "Palet
 * Civileri", yazida "Blog" isaretli kalsin). Ana sayfa haric.
 */
function pc_is_within( string $url ): bool {
	$target = pc_link_path( $url );

	if ( $target === untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) ) {
		return false;
	}

	if ( is_singular( 'post' ) && $target === pc_link_path( (string) ( pc_manifest()['pages']['blog']['path'] ?? '' ) ) ) {
		return true;
	}

	if ( '' !== pc_current_product_key() && $target === pc_link_path( (string) ( pc_manifest()['pages']['products']['path'] ?? '' ) ) ) {
		return true;
	}

	return str_starts_with( pc_request_path() . '/', $target . '/' );
}

function pc_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

/**
 * Bos satirla ayrilmis metni paragraflara boler.
 */
function pc_paragraphs( string $value, string $class = '' ): string {
	$html = '';

	foreach ( preg_split( '/\R\s*\R/u', trim( $value ) ) ?: array() as $block ) {
		$block = trim( $block );

		if ( '' !== $block ) {
			$html .= sprintf( '<p%s>%s</p>', $class ? ' class="' . esc_attr( $class ) . '"' : '', pc_multiline( $block ) );
		}
	}

	return $html;
}

/**
 * Manifestte olmayan WordPress sayfasi metni (yasal metin gibi): onizlemede
 * sayfanin duzenleme ekrani acilir. Eklenti kapaliyken hicbir sey basmaz.
 * Ornek blog yazilari panelde duzenlenir (Blog yazilari panelden).
 */
function pc_post_attr( int $post_id, string $label = 'Blog yazısı' ): void {
	if ( function_exists( 'nwcs_post_attr' ) && $post_id ) {
		nwcs_post_attr( $post_id, $label );
	}
}

/**
 * Isaret tanimi: array( sayfa, bilesen, alan[, satir, alt alan] ) ya da
 * array( 'post', sayfa_id, etiket ) (WordPress sayfasi) ya da array( 'source', tur, adres, etiket ).
 * Sayfa basi ve konum yolu gibi ortak parcalar bu tanimi arguman olarak alir.
 */
function pc_edit( $spec ): void {
	if ( ! is_array( $spec ) || ! $spec ) {
		return;
	}

	if ( 'post' === $spec[0] ) {
		pc_post_attr( (int) ( $spec[1] ?? 0 ), (string) ( $spec[2] ?? 'Blog yazısı' ) );
		return;
	}

	if ( 'source' === $spec[0] ) {
		if ( function_exists( 'nwcs_source_attr' ) ) {
			nwcs_source_attr( (string) ( $spec[1] ?? 'admin' ), (string) ( $spec[2] ?? '' ), (string) ( $spec[3] ?? '' ) );
		}
		return;
	}

	nwcs_edit_attr( (string) $spec[0], (string) ( $spec[1] ?? '' ), (string) ( $spec[2] ?? '' ), isset( $spec[3] ) ? (int) $spec[3] : null, (string) ( $spec[4] ?? '' ) );
}

/**
 * pc_edit() ciktisi metin olarak (the_posts_pagination gibi HTML alan
 * argumanlara isaret koymak icin).
 */
function pc_edit_string( $spec ): string {
	ob_start();
	pc_edit( $spec );

	return (string) ob_get_clean();
}

/**
 * Yazi listesi sayfalama; Onceki / Sonraki metinleri panelden. Isaret
 * yalnizca onizlemede basilir, ziyaretcinin gordugu HTML degismez.
 */
function pc_posts_pagination(): void {
	$label = static function ( string $field ): string {
		$text = esc_html( nwcs_field( 'blog', 'list', $field ) );
		$attr = pc_edit_string( array( 'blog', 'list', $field ) );

		return '' !== $attr ? '<span' . $attr . '>' . $text . '</span>' : $text;
	};

	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => $label( 'prev' ),
			'next_text' => $label( 'next' ),
		)
	);
}

function pc_part( string $name, array $args = array() ): void {
	get_template_part( 'template-parts/' . $name, null, $args );
}

/**
 * Manifest; eklenti kapaliyken de urun listesi kurulabilsin diye dosyadan.
 */
function pc_manifest(): array {
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
 * Alanin manifestteki varsayilan degeri.
 */
function pc_manifest_default( string $page, string $component, string $field ) {
	return pc_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? '';
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
function pc_post_page_key( $post ): string {
	$post = get_post( $post );

	if ( ! $post || 'post' !== $post->post_type || '' === $post->post_name ) {
		return '';
	}

	$key = 'yazi-' . $post->post_name;

	return isset( pc_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Panelde degistirilmis deger; alan varsayilanindaysa (yazinin ilk metni) null.
 *
 * @return string|int|null
 */
function pc_post_override( $post, string $field ) {
	$key = pc_post_page_key( $post );

	if ( '' === $key ) {
		return null;
	}

	$value = nwcs_field( $key, 'post', $field );

	if ( 'image' === $field ) {
		return (int) $value ? (int) $value : null;
	}

	$clean   = static fn( $text ): string => trim( str_replace( "\r\n", "\n", (string) $text ) );
	$value   = $clean( $value );
	$default = $clean( pc_manifest_default( $key, 'post', $field ) );

	return '' === $value || $value === $default ? null : $value;
}

/**
 * Onizlemede tiklaninca yazinin paneldeki alanini acar.
 */
function pc_post_edit_attr( $post, string $field ): void {
	$key = pc_post_page_key( $post );

	if ( '' !== $key ) {
		nwcs_edit_attr( $key, 'post', $field );
	}
}

/**
 * pc_edit() isaret tanimi (sayfa basi gibi ortak parcalar icin); gizli
 * sayfasi olmayan yazida null.
 */
function pc_post_edit_spec( $post, string $field ): ?array {
	$key = pc_post_page_key( $post );

	return '' !== $key ? array( $key, 'post', $field ) : null;
}

/*
 * SEO: eklenti yazi bilgisini 'wp' aksiyonunda (10) hesaplayip sakliyor.
 * Hemen once, gizli sayfanin "Arama ve Paylasim" alanlari (yoksa paneldeki
 * baslik, ozet ve kapak) gecerliyken hesaplatilir.
 */
add_action( 'wp', 'pc_post_seo', 9 );
function pc_post_seo(): void {
	if ( ! is_singular( 'post' ) || ! function_exists( 'nwcs_seo_context' ) ) {
		return;
	}

	$post = get_queried_object();
	$key  = pc_post_page_key( $post );

	if ( '' === $key ) {
		return;
	}

	$description = trim( (string) nwcs_field( $key, 'seo', 'description' ) );
	$description = '' !== $description ? $description : (string) pc_post_override( $post, 'excerpt' );
	$original    = $post->post_excerpt;

	if ( '' !== $description ) {
		$post->post_excerpt = $description;
	}

	$GLOBALS['pc_post_seo_key'] = $key;
	nwcs_seo_context();
	unset( $GLOBALS['pc_post_seo_key'] );

	$post->post_excerpt = $original;
}

/**
 * Arama bilgisi hesaplanirken gecerli olan SEO alani; diger zamanlarda bos.
 */
function pc_post_seo_field( $post, string $field ): string {
	$key = $GLOBALS['pc_post_seo_key'] ?? '';

	return '' !== $key && pc_post_page_key( $post ) === $key ? trim( (string) nwcs_field( $key, 'seo', $field ) ) : '';
}

add_filter( 'the_title', 'pc_post_title', 10, 2 );
function pc_post_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! $post_id ) {
		return $title;
	}

	$seo = pc_post_seo_field( $post_id, 'title' );

	return '' !== $seo ? $seo : ( pc_post_override( $post_id, 'title' ) ?? $title );
}

add_filter( 'get_the_excerpt', 'pc_post_excerpt', 10, 2 );
function pc_post_excerpt( $excerpt, $post = null ) {
	return is_admin() ? $excerpt : ( pc_post_override( $post, 'excerpt' ) ?? $excerpt );
}

// Bloklar islendikten sonra: panel metni kendi paragraf ve basliklariyla gelir.
add_filter( 'the_content', 'pc_post_content', 99 );
function pc_post_content( $content ) {
	if ( is_admin() || ! in_the_loop() ) {
		return $content;
	}

	$body = pc_post_override( get_the_ID(), 'body' );

	return null === $body ? $content : pc_post_html( $body );
}

add_filter( 'post_thumbnail_id', 'pc_post_thumbnail', 10, 2 );
function pc_post_thumbnail( $thumbnail_id, $post = null ) {
	if ( is_admin() ) {
		return $thumbnail_id;
	}

	$seo = (int) pc_post_seo_field( $post, 'image' );

	return $seo ? $seo : ( pc_post_override( $post, 'image' ) ?? $thumbnail_id );
}

/* ====================================================================== *
 * Gorseller
 *
 * Panelden secilen gorsel her zaman kazanir; alan bossa temanin kendi
 * gorseli (assets/img) gelir. Tema gorselleri yapay zekayla uretilmis ornek
 * gorsellerdir ve sayfada "Ornek gorsel" notuyla isaretlenir.
 * ====================================================================== */

/**
 * @param array{id?:int, url?:string, alt?:string} $image
 * @return array{id:int, url:string, alt:string, sample:bool}
 */
function pc_img( array $image, string $file, string $alt = '' ): array {
	if ( ! empty( $image['url'] ) ) {
		return array(
			'id'     => (int) ( $image['id'] ?? 0 ),
			'url'    => (string) $image['url'],
			'alt'    => (string) ( ( $image['alt'] ?? '' ) ?: $alt ),
			'sample' => false,
		);
	}

	$exists = '' !== $file && file_exists( get_theme_file_path( 'assets/img/' . $file ) );

	return array(
		'id'     => 0,
		'url'    => $exists ? get_theme_file_uri( 'assets/img/' . $file ) : '',
		'alt'    => $alt,
		'sample' => $exists,
	);
}

/**
 * img etiketi; gorsel yoksa sessiz yer tutucu.
 */
function pc_img_tag( array $image, string $class = '', bool $eager = false ): string {
	if ( '' === ( $image['url'] ?? '' ) ) {
		return sprintf( '<span class="%s pc-noimg" role="img" aria-label="%s"></span>', esc_attr( $class ), esc_attr( $image['alt'] ?? '' ) );
	}

	return sprintf(
		'<img src="%1$s" alt="%2$s" class="%3$s" %4$s decoding="async" />',
		esc_url( $image['url'] ),
		esc_attr( $image['alt'] ?? '' ),
		esc_attr( $class ),
		$eager ? 'fetchpriority="high"' : 'loading="lazy"'
	);
}

function pc_logo(): array {
	return pc_img( nwcs_image( 'global', 'header', 'logo_image', 'medium' ), 'logo.png', (string) nwcs_field( 'global', 'header', 'logo_text' ) );
}

/**
 * Yazinin gorseli: one cikan gorsel, yoksa kurulumda yaziya baglanan tema
 * gorseli (_pc_image), o da yoksa atolye fotografi.
 */
function pc_post_image( $post = null ): array {
	$post  = get_post( $post );
	$thumb = $post ? (int) get_post_thumbnail_id( $post ) : 0;

	if ( $thumb ) {
		$src = wp_get_attachment_image_src( $thumb, 'large' );

		return pc_img(
			array(
				'id'  => $thumb,
				'url' => $src ? (string) $src[0] : '',
				'alt' => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
			),
			'',
			get_the_title( $post )
		);
	}

	$file = $post ? (string) get_post_meta( $post->ID, '_pc_image', true ) : '';

	return pc_img( array(), $file ?: 'hero.jpg', $post ? get_the_title( $post ) : '' );
}

/* ====================================================================== *
 * Urunler
 *
 * Tek kaynak manifest: 'template' => 'product' isaretli sayfalar. Menu,
 * ana sayfa, Palet Civileri, alt bilgi, form secenekleri ve "diger
 * urunler" hep buradan beslenir.
 * ====================================================================== */

const PC_PRODUCT_IMAGES = array(
	'rulo'  => 'rulo.jpg',
	'tele'  => 'tele.jpg',
	'dokme' => 'dokme.jpg',
);

/**
 * @return array<string, array{key:string, path:string, url:string, name:string, short:string, tool:string, image:array}>
 */
function pc_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	foreach ( pc_manifest()['pages'] ?? array() as $key => $page ) {
		if ( 'product' !== ( $page['template'] ?? '' ) ) {
			continue;
		}

		$name             = (string) nwcs_field( $key, 'card', 'name' );
		$products[ $key ] = array(
			'key'   => $key,
			'path'  => (string) ( $page['path'] ?? '/' ),
			'url'   => pc_link( $page['path'] ?? '/' ),
			'name'  => $name,
			'short' => (string) nwcs_field( $key, 'card', 'short' ),
			'tool'  => (string) nwcs_field( $key, 'card', 'tool' ),
			'image' => pc_product_type_image( $key, $name, 'large' ),
		);
	}

	return $products;
}

/**
 * Tip sayfasinin gorseli: panelde secilen; yoksa sunucunun indirdigi kaynak
 * gorsel (tip:<anahtar>); o da yoksa temanin ornek gorseli.
 */
function pc_product_type_image( string $key, string $alt, string $size = 'large' ): array {
	$own = nwcs_image( $key, 'card', 'image', $size );

	if ( '' !== ( $own['url'] ?? '' ) ) {
		return pc_img( $own, '', $alt );
	}

	$loaded = pc_catalog_sideloaded( 'tip:' . $key, $size );

	if ( '' !== ( $loaded['url'] ?? '' ) ) {
		return pc_img( $loaded, '', $alt );
	}

	return pc_img( array(), PC_PRODUCT_IMAGES[ $key ] ?? '', $alt );
}

function pc_current_product_key(): string {
	static $key = null;

	if ( null !== $key ) {
		return $key;
	}

	$key     = '';
	$current = pc_request_path();

	foreach ( pc_manifest()['pages'] ?? array() as $page_key => $page ) {
		if ( 'product' === ( $page['template'] ?? '' ) && pc_link_path( (string) $page['path'] ) === $current ) {
			$key = $page_key;
			break;
		}
	}

	return $key;
}

/**
 * Urun sayfalari (/civiler/<urun>/) tek sablonla cizilir.
 */
add_filter( 'template_include', 'pc_product_template' );
function pc_product_template( string $template ): string {
	if ( is_page() && '' !== pc_current_product_key() ) {
		$file = get_theme_file_path( 'template-parts/product-page.php' );

		return file_exists( $file ) ? $file : $template;
	}

	return $template;
}

/**
 * /civiler/ yalnizca urun sayfalarinin ust klasoru; kendi icerigi yok.
 * Ziyaretci ve arama motoru Palet Civileri sayfasina gider.
 */
add_action( 'template_redirect', 'pc_redirect_product_parent' );
function pc_redirect_product_parent(): void {
	if ( is_page( 'civiler' ) ) {
		$target = pc_link( pc_manifest()['pages']['products']['path'] ?? '/' );
		$status = 301;

		// Panel onizlemesi yonlendirmeden sonra da onizlemede kalsin (alan
		// isaretleri); ziyaretcinin adresi degismez.
		if ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() && isset( $_GET['nwcs_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target = add_query_arg( 'nwcs_preview', rawurlencode( sanitize_text_field( wp_unslash( $_GET['nwcs_preview'] ) ) ), $target ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status = 302;
		}

		wp_safe_redirect( $target, $status );
		exit;
	}
}

/* ====================================================================== *
 * Iletisim
 * ====================================================================== */

/**
 * WhatsApp baglantisi, hazir mesajla. $text verilmezse paneldeki genel mesaj
 * kullanilir. Mesaj yalnizca wa.me / api.whatsapp.com adreslerine ve adreste
 * zaten ?text= yoksa eklenir.
 */
function pc_whatsapp( string $text = '' ): string {
	$url = trim( (string) nwcs_field( 'global', 'header', 'whatsapp_url' ) );

	if ( '' === $url ) {
		return $url;
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( ! in_array( $host, array( 'wa.me', 'api.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com' ), true ) ) {
		return $url;
	}

	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

	if ( '' !== trim( (string) ( $query['text'] ?? '' ) ) ) {
		return $url;
	}

	if ( '' === $text ) {
		$text = trim( (string) nwcs_field( 'global', 'header', 'whatsapp_message' ) );
	}

	return '' === $text ? $url : add_query_arg( 'text', rawurlencode( $text ), $url );
}

/**
 * Urune ozel WhatsApp baglantisi: urun adi ve urun sayfasinin adresi.
 */
function pc_whatsapp_product( array $product ): string {
	$text = strtr(
		(string) nwcs_field( 'products', 'shared', 'wa_message' ),
		array( '{urun}' => (string) ( $product['name'] ?? '' ) )
	);

	return pc_whatsapp( trim( $text . ' ' . ( $product['url'] ?? '' ) ) );
}

/**
 * Satir ici simge (dekoratif; yaninda her zaman metin var).
 */
function pc_icon( string $name ): string {
	$paths = array(
		'phone'    => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
		'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8.8 8.5c.3-.6.6-.6.9-.6h.6c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.6l-.5.6c-.1.2-.2.3 0 .6a7 7 0 0 0 3 2.6c.3.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.8.9c.3.1.4.2.4.4 0 .6-.2 1.3-.9 1.7-.7.4-1.6.5-3.2-.1a10 10 0 0 1-4.5-4c-.8-1.3-.9-2.6-.4-3.4z" fill="currentColor"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg class="pc-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

function pc_phone(): array {
	return array(
		'label' => (string) nwcs_field( 'global', 'header', 'phone_label' ),
		'url'   => pc_link( nwcs_field( 'global', 'header', 'phone_url' ) ),
	);
}

/* ====================================================================== *
 * SEO ve GEO: gorseller tema icinde; gorseli olmayan sayfa paylasilinca
 * atolye fotografi, firma verisinde logo gorunur.
 * ====================================================================== */

add_filter(
	'nwcs_seo_default_image',
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/hero.jpg', 'Palet üretiminde çivi tabancasıyla çakım' ) : 0
);

add_filter(
	'nwcs_seo_default_logo',
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/logo.png', 'İstanbul Palet Çivi' ) : 0
);

/**
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'pc_favicon', 2 );
add_action( 'login_head', 'pc_favicon' );
function pc_favicon(): void {
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
function pc_hide_missing_sss( array $rows ): array {
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
