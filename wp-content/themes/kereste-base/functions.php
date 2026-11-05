<?php
/**
 * kereste-base: kuzen kereste sitelerinin (ithalkeresteci, kavakkeresteci)
 * ortak ana temasi.
 *
 * Sablonlar, bolumler ve teklif formu burada; cocuk temalar
 * yalnizca renk (style.css) ve icerik manifestini (content-manifest.php)
 * tasir. Gorunen metinler Network Content Studio veri katmanindan okunur;
 * eklenti kapaliysa sayfa fatal vermesin diye yedek fonksiyonlar var.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nwcs_field' ) ) {
	// Eklenti kapaliyken sayfa bos kalmasin: manifestteki varsayilan metin.
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		if ( null !== $fallback ) {
			return $fallback;
		}
		$default = kr_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? '';
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
		$sections = kr_manifest()['pages'][ $page ]['sortable_sections'] ?? null;
		return is_array( $sections ) ? $sections : array( 'products', 'cousin', 'faq', 'quote' );
	}
}

add_action( 'after_setup_theme', 'kr_setup' );
function kr_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form' ) );
}

add_action( 'wp_enqueue_scripts', 'kr_assets' );
function kr_assets(): void {
	// Surum = tema surumu + dosya degisim zamani: dosya her derlendiginde adres
	// degisir, tarayici ve onbellek eski CSS/JS'i gostermez.
	$base  = wp_get_theme( get_template() )->get( 'Version' );
	$ver   = static fn( string $file ): string => $base . '.' . (int) @filemtime( get_template_directory() . '/' . $file );

	// Tek CSS dosyasi ana temadan: yerel Archivo + Tailwind, bu sirayla
	// (scripts/build-css.mjs uretir); iki keresteci sitesi de ayni dosyayi
	// kullanir. Yerelde kaynaklardan biri site.css'ten yeniyse (derleme
	// unutulduysa) kaynaklar eskisi gibi ayri yuklenir; canlida dosya tarihleri
	// yuklemeye gore degistigi icin kiyas yapilmaz.
	$built = (int) @filemtime( get_template_directory() . '/assets/site.css' );
	$fresh = $built > 0;

	if ( $fresh && 'local' === wp_get_environment_type() ) {
		foreach ( array( 'assets/fonts/archivo.css', 'assets/tailwind.css' ) as $source ) {
			if ( (int) @filemtime( get_template_directory() . '/' . $source ) > $built ) {
				$fresh = false;
			}
		}
	}

	if ( $fresh ) {
		wp_enqueue_style( 'kr-tailwind', get_template_directory_uri() . '/assets/site.css', array(), $ver( 'assets/site.css' ) );
	} else {
		wp_enqueue_style( 'kr-fonts', get_template_directory_uri() . '/assets/fonts/archivo.css', array(), $ver( 'assets/fonts/archivo.css' ) );
		wp_enqueue_style( 'kr-tailwind', get_template_directory_uri() . '/assets/tailwind.css', array( 'kr-fonts' ), $ver( 'assets/tailwind.css' ) );
	}

	// Yakinlastirma motoru yalnizca urun sayfalarinda (buyutulen galeri orada).
	$site_deps = array();

	if ( kr_current_product_key() ) {
		wp_enqueue_script( 'kr-zoom', get_template_directory_uri() . '/assets/zoom.js', array(), $ver( 'assets/zoom.js' ), true );
		$site_deps[] = 'kr-zoom';

		// Buyutulen gorselin altindaki yazi sayfanin ustunde okunsun: perde koyu.
		wp_add_inline_style( 'kr-tailwind', '.kr-lightbox::backdrop{background:color-mix(in srgb,var(--color-ink) 92%,transparent)}' );

		// (Tailwind derlemesi tema disinda; bu kurallar burada.)
		// Urun sayfasi: solda bilgi, sagda buyuk galeri (firmanin istegi, eski
		// woodkocist.com.tr urun sayfasi gibi). Olculer WOOD KOCIST ile ayni: sutunlar
		// 472/728, arada 5rem; ana gorsel 1280x1714 dikey cercevede (firmanin
		// istegi), yatay fotografin kenarlari kirpilir, tam hali buyutmede.
		wp_add_inline_style(
			'kr-tailwind',
			'@media (max-width:68.74rem){.kr-pt-gallery,.kr-pt-info{width:100%;max-width:45rem;margin-inline:auto}}@media (max-width:39.99rem){.kr-pt-gallery{width:auto;max-width:none;margin-inline:-1.25rem}}@media (min-width:68.75rem){.kr-product-top{grid-template-columns:minmax(0,472fr) minmax(0,728fr);column-gap:5rem;align-items:start}.kr-pt-gallery{grid-column:2;grid-row:1}.kr-pt-info{grid-column:1;grid-row:1;position:sticky;top:8rem}}'
			. '.kr-product-shot{aspect-ratio:auto;overflow:hidden}.kr-product-shot>img,.kr-product-shot>[role=img]{position:static;display:block;height:auto;object-fit:contain}'
			. '.kr-product-shot>img{width:100%;aspect-ratio:1280/1714;object-fit:cover}'
			// Gorsel yuklenmemisse yer tutucu da ayni dikey cercevede (eklentinin 4:3 satir ici oranini ezer).
			. '.kr-product-shot>.nwcs-ph{width:100%;aspect-ratio:1280/1714!important}'
			// Tablette dikey cerceve ekrani tek basina doldurmasin.
			. '@media (min-width:40rem) and (max-width:68.74rem){.kr-product-shot>img,.kr-product-shot>.nwcs-ph{width:auto;max-width:100%;height:min(calc(100svh - 220px),60rem);margin-inline:auto}}'
			// Fareyle uzerine gelince imlecin oldugu yer buyur (assets/site.js).
			. '.kr-product-shot>img{transform-origin:var(--zx,50%) var(--zy,50%);transition:transform .25s ease}.kr-product-shot.is-lens>img{transform:scale(2.2);transition-duration:.12s}'
			. '@media (prefers-reduced-motion:reduce){.kr-product-shot>img{transition:none}}'
			// Yer tutucuda buyutme etiketi, imleci ve bos kucuk resim sirasi yok.
			. '.kr-product-shot:not(:has(img)){cursor:default}.kr-product-shot:not(:has(img))>.absolute{display:none}.kr-pt-gallery ul:not(:has(img)){display:none}'
		);
	}

	// Cocuk temanin style.css'i yalnizca parti boyasini (renk degiskenleri)
	// tanimlar (~1 KB); ayri istek yerine satir ici, eskisi gibi en sonda.
	// Yorumlar (tema basligi dahil) atilir.
	$child_css = (string) @file_get_contents( get_stylesheet_directory() . '/style.css' );
	$child_css = trim( (string) preg_replace( '#/\*.*?\*/#s', '', $child_css ) );

	if ( '' !== $child_css ) {
		wp_add_inline_style( 'kr-tailwind', $child_css );
	}

	wp_enqueue_script( 'kr-site', get_template_directory_uri() . '/assets/site.js', $site_deps, $ver( 'assets/site.js' ), true );
}

// Yazi tipi ana temada yerelde (assets/fonts); iki keresteci sitesi de ayni
// dosyalari kullanir. Turkce metnin cogu latin alt kumesinde; CSS'i
// beklemeden indirilsin diye on yukleme.
add_action( 'wp_head', 'kr_preload_font', 1 );
function kr_preload_font(): void {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_template_directory_uri() . '/assets/fonts/archivo-latin.woff2' )
	);
}

/* ====================================================================== *
 * Genel yardimcilar
 * ====================================================================== */

/**
 * WhatsApp baglantisi. Panelde "boşsa düğme gizlenir" yaziyor: alan
 * bilerek bosaltilip kaydedildiyse bos doner (dugmeler gizlenir). Hic
 * kaydedilmediyse manifest varsayilani. nwcs_field() bos degeri
 * varsayilanla doldurdugu icin burada ham deger okunur.
 */
function kr_whatsapp_url(): string {
	$raw = function_exists( 'nwcs_raw' ) ? nwcs_raw( 'global', 'header', 'whatsapp_url' ) : null;

	return trim( (string) ( null === $raw ? nwcs_field( 'global', 'header', 'whatsapp_url' ) : $raw ) );
}

/**
 * Koke gore yazilmis yolu site adresine baglar; bos baglanti '#'.
 */
function kr_link( $url ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	return kr_whatsapp_message( $url );
}

/**
 * WhatsApp baglantisina hazir mesaj ekler (wa.me/<numara>?text=...).
 *
 * Numara panelde yazildigi gibi kalir; yalnizca mesaj eklenir. Panelde
 * yazilan baglantida zaten mesaj (text=) varsa dokunulmaz. Urun sayfasinda
 * mesaj urunun adini ve adresini tasir (ustteki ve urun sayfasindaki
 * WhatsApp dugmeleri); diger sayfalarda genel selam.
 */
function kr_whatsapp_message( string $url ): string {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( ! in_array( $host, array( 'wa.me', 'api.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com' ), true ) ) {
		return $url;
	}

	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

	if ( '' !== trim( (string) ( $query['text'] ?? '' ) ) ) {
		return $url;
	}

	$key     = kr_current_product_key();
	$product = '' !== $key ? kr_products()[ $key ] : array();
	$text    = $product
		? sprintf( 'Merhaba, %s hakkında bilgi almak istiyorum. %s', $product['name'], kr_link( $product['path'] ) )
		: 'Merhaba, web sitenizden yazıyorum. Bilgi almak istiyorum.';

	return add_query_arg( 'text', rawurlencode( $text ), remove_query_arg( 'text', $url ) );
}

function kr_request_path(): string {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return untrailingslashit( (string) wp_parse_url( $request, PHP_URL_PATH ) );
}

function kr_link_path( string $url ): string {
	return untrailingslashit( (string) wp_parse_url( kr_link( $url ), PHP_URL_PATH ) );
}

function kr_is_current( string $url ): bool {
	return kr_link_path( $url ) === kr_request_path();
}

/**
 * Bulunulan sayfa bu baglantinin altinda mi (urun sayfasinda "Urunler").
 */
function kr_is_within( string $url ): bool {
	$target = kr_link_path( $url );

	if ( $target === untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) ) {
		return false;
	}

	return str_starts_with( kr_request_path() . '/', $target . '/' );
}

/**
 * Gorsel etiketi; gorsel yoksa sessiz yer tutucu.
 */
function kr_image_tag( array $image, string $class = '', string $alt_fallback = '', bool $eager = false ): string {
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
		'<span class="%1$s flex items-center justify-center bg-stone text-sm text-muted" role="img" aria-label="%2$s">%2$s</span>',
		esc_attr( $class ),
		esc_html( $alt_fallback ?: 'Görsel eklenmedi' )
	);
}

/**
 * Logo: cam agaci isareti ve iki satir dar buyuk harf (ust satir sitenin
 * adi, alt satir "Keresteci"). Istanbul Keresteci ile ayni logo dili; uc
 * keresteci sitesi ust kisimda akraba gorunur. Logo gorseli yuklenirse o
 * kullanilir.
 *
 * $tone: 'dark' koyu zemin (ust bar, alt bilgi), 'light' acik zemin.
 */
function kr_logo( string $tone = 'dark', string $class = '' ): void {
	$image = nwcs_image( 'global', 'header', 'logo_image', 'medium' );
	$top   = (string) nwcs_field( 'global', 'header', 'logo_word' );
	$rest  = (string) nwcs_field( 'global', 'header', 'logo_rest' );
	?>
	<?php // Logo gorseline ya da isaretine tiklaninca logo gorseli alani; yazilara tiklaninca kendi alanlari. ?>
	<span class="kr-logo kr-logo--<?php echo esc_attr( $tone ); ?> <?php echo esc_attr( $class ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'logo_image' ); ?>>
		<?php if ( $image['url'] ) : ?>
			<img class="kr-logo__image" src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( trim( $top . ' ' . $rest ) ); ?>"<?php echo preg_match( '/\.svg(\?|$)/i', $image['url'] ) ? ' height="48"' : ''; ?> decoding="async" />
		<?php else : ?>
			<svg class="kr-logo__mark" viewBox="0 0 32 40" width="32" height="40" aria-hidden="true" focusable="false">
				<path d="M16 2 7 13h5l-8 10h6L3 33h26l-7-10h6l-8-10h5z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" />
				<path d="M16 33v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
			</svg>
			<span class="kr-logo__text">
				<span class="kr-logo__top" <?php nwcs_edit_attr( 'global', 'header', 'logo_word' ); ?>><?php echo esc_html( $top ); ?></span>
				<span class="kr-logo__bottom" <?php nwcs_edit_attr( 'global', 'header', 'logo_rest' ); ?>><?php echo esc_html( $rest ); ?></span>
			</span>
		<?php endif; ?>
	</span>
	<?php
}

function kr_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

/**
 * Bos satirla ayrilmis metni paragraflara boler.
 */
function kr_paragraphs( string $value, string $class = '' ): string {
	$html = '';

	foreach ( preg_split( '/\R\s*\R/u', trim( $value ) ) ?: array() as $block ) {
		$block = trim( $block );

		if ( '' !== $block ) {
			$html .= sprintf( '<p%s>%s</p>', $class ? ' class="' . esc_attr( $class ) . '"' : '', kr_multiline( $block ) );
		}
	}

	return $html;
}

function kr_section( string $key ): void {
	$file = get_theme_file_path( "template-parts/sections/{$key}.php" );

	if ( file_exists( $file ) ) {
		include $file;
	}
}

/**
 * Sitenin manifesti (cocuk temanin content-manifest.php dosyasi).
 */
function kr_manifest(): array {
	if ( function_exists( 'nwcs_manifest' ) ) {
		return nwcs_manifest();
	}

	static $manifest = null;

	if ( null === $manifest ) {
		$file     = get_stylesheet_directory() . '/content-manifest.php';
		$loaded   = file_exists( $file ) ? include $file : array();
		$manifest = is_array( $loaded ) ? $loaded : array();
	}

	return $manifest;
}

function kr_page_path( string $key, string $fallback = '/' ): string {
	return (string) ( kr_manifest()['pages'][ $key ]['path'] ?? $fallback );
}

/* ====================================================================== *
 * Urunler: tek kaynak manifest ('template' => 'product' isaretli sayfalar)
 * ====================================================================== */

function kr_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	foreach ( kr_manifest()['pages'] ?? array() as $key => $page ) {
		if ( 'product' !== ( $page['template'] ?? '' ) ) {
			continue;
		}

		$products[ $key ] = array(
			'key'   => $key,
			'path'  => (string) $page['path'],
			'url'   => kr_link( $page['path'] ),
			'name'  => (string) nwcs_field( $key, 'card', 'name' ),
			'short' => (string) nwcs_field( $key, 'card', 'short' ),
			'mark'  => (string) nwcs_field( $key, 'card', 'mark' ),
			'image' => nwcs_image( $key, 'card', 'image', 'large' ),
		);
	}

	return $products;
}

function kr_current_product_key(): string {
	foreach ( kr_products() as $key => $product ) {
		if ( kr_link_path( $product['path'] ) === kr_request_path() ) {
			return $key;
		}
	}

	return '';
}

/**
 * Urun galerisi: kart gorseli basta, sonra galeri satirlari.
 */
function kr_product_gallery( string $key ): array {
	$ids = array( (int) nwcs_field( $key, 'card', 'image', 0 ) );

	foreach ( nwcs_rows( $key, 'detail', 'gallery' ) as $row ) {
		$ids[] = (int) ( $row['image'] ?? 0 );
	}

	$gallery = array();
	$name    = (string) nwcs_field( $key, 'card', 'name' );

	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		// Ana gorsel genis sutunda (~700px, retina ekranda iki kati): 1536px
		// boyu; yoksa WordPress tam boyu verir.
		$large = nwcs_image_by_id( $id, '1536x1536' );

		if ( '' !== $large['url'] ) {
			$gallery[] = array(
				'url'   => $large['url'],
				// Buyutmede yakinlastirma netligi icin tam boy.
				'full'  => nwcs_image_by_id( $id, 'full' )['url'] ?: $large['url'],
				'alt'   => $large['alt'] ?: $name,
				'thumb' => nwcs_image_by_id( $id, 'medium' )['url'] ?: $large['url'],
			);
		}
	}

	return $gallery;
}

/**
 * Teklif dugmesinin JavaScript'siz calisan yedegi: iletisim sayfasindaki
 * form, urunu adresten okuyup secili getirir.
 */
function kr_quote_fallback_url( string $product = '' ): string {
	$url = kr_link( kr_page_path( 'contact', '/iletisim/' ) );

	if ( '' !== $product ) {
		$url = add_query_arg( 'urun', rawurlencode( $product ), $url );
	}

	return $url . '#teklif';
}

/* ====================================================================== *
 * Teklif formu
 *
 * Her sayfada bir teklif penceresi var (template-parts/quote-dialog.php);
 * Iletisim sayfasinda ayni form sayfanin icinde. Gonderim gercekten
 * kaydedilir: yonetimde "Teklif Istekleri". Sahte basari ekrani yok.
 * ====================================================================== */

add_action( 'init', 'kr_register_quote_cpt' );
function kr_register_quote_cpt(): void {
	register_post_type(
		'kr_quote',
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
 * Yonlendirmeden sonra form durumunu tasiyan anahtar: kucuk harfli
 * onaltilik; harf duyarli nesne onbelleginde de bulunur (DECISIONS.md).
 */
function kr_quote_redirect( string $redirect, array $state ): void {
	$token = bin2hex( random_bytes( 10 ) );

	set_transient( 'kr_quote_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'teklif', $token, $redirect ) . '#teklif' );
	exit;
}

// Bildirim e-postasi (Network Content Studio, includes/forms.php) her sitede
// ortak adrese gider; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'kr_form_recipient' );
function kr_form_recipient(): string {
	return 'info@kocist.com.tr';
}

add_action( 'admin_post_kr_quote', 'kr_handle_quote' );
add_action( 'admin_post_nopriv_kr_quote', 'kr_handle_quote' );
function kr_handle_quote(): void {
	$fallback = home_url( '/' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	$redirect = strtok( remove_query_arg( 'teklif', '' !== $redirect ? $redirect : $fallback ), '#' );

	// Bot tuzagi.
	if ( ! empty( $_POST['kr_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['kr_name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['kr_company'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['kr_phone'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['kr_email'] ?? '' ) ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['kr_product'] ?? '' ) ),
		'size'    => sanitize_textarea_field( wp_unslash( $_POST['kr_size'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['kr_message'] ?? '' ) ),
	);

	$errors = array();

	if ( ! isset( $_POST['kr_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kr_quote_nonce'] ), 'kr_quote' ) ) {
		$errors['form'] = 'error_session';
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'error_name';
	}

	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		$errors['email'] = 'error_email';
	} elseif ( '' === $values['email'] && '' === $values['phone'] ) {
		$errors['phone'] = 'error_contact';
	}

	if ( '' === $values['size'] ) {
		$errors['size'] = 'error_size';
	}

	// Hiz siniri (eklenti, nwcs_form_rate_*): yalnizca gecerli talepte.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = 'error_rate';
	}

	if ( $errors ) {
		kr_quote_redirect( $redirect, array( 'errors' => $errors, 'values' => $values ) );
	}

	if ( ! in_array( $values['product'], wp_list_pluck( kr_products(), 'name' ), true ) ) {
		$values['product'] = 'Belirtilmedi';
	}

	if ( function_exists( 'nwcs_form_rate_hit' ) ) {
		nwcs_form_rate_hit();
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'kr_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ),
			'post_content' => sprintf(
				"Firma: %s\nTelefon: %s\nE-posta: %s\nÜrün: %s\n\nÖlçü ve adet:\n%s\n\nMesaj:\n%s",
				$values['company'] ?: '—',
				$values['phone'] ?: '—',
				$values['email'] ?: '—',
				$values['product'],
				$values['size'],
				$values['message'] ?: '—'
			),
			'meta_input'   => array(
				'_kr_name'    => $values['name'],
				'_kr_company' => $values['company'],
				'_kr_phone'   => $values['phone'],
				'_kr_email'   => $values['email'],
				'_kr_product' => $values['product'],
				'_kr_size'    => $values['size'],
				'_kr_message' => $values['message'],
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		kr_quote_redirect(
			$redirect,
			array(
				'errors' => array( 'form' => 'error_save' ),
				'values' => $values,
			)
		);
	}

	kr_quote_redirect( $redirect, array( 'success' => true ) );
}

/**
 * Hata iletisi: gonderimde hatanin panel alani (contact.form.error_*) saklanir,
 * metin gosterilirken panelden okunur. Eski kayitlarda duz metin olabilir.
 *
 * @return array{0:string,1:string} metin ve alan anahtari (duz metinde bos).
 */
function kr_quote_error( string $code ): array {
	if ( str_starts_with( $code, 'error_' ) ) {
		return array( (string) nwcs_field( 'contact', 'form', $code ), $code );
	}

	return array( $code, '' );
}

/**
 * Teklif formu alaninin onizleme isareti; alan anahtari bossa bir sey basmaz.
 */
function kr_form_attr( string $field ): void {
	if ( '' !== $field ) {
		nwcs_edit_attr( 'contact', 'form', $field );
	}
}

/**
 * WordPress yazisi/sayfasi metni: onizlemede yazinin duzenleme ekranini acar.
 */
function kr_post_attr( int $post_id, string $label = 'Sayfa metni' ): void {
	if ( $post_id && function_exists( 'nwcs_post_attr' ) ) {
		nwcs_post_attr( $post_id, $label );
	}
}

function kr_quote_state(): array {
	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false, 'active' => false );
	$token = isset( $_GET['teklif'] ) ? sanitize_key( wp_unslash( $_GET['teklif'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return $empty;
	}

	$state = get_transient( 'kr_quote_' . $token );

	if ( ! is_array( $state ) ) {
		return $empty;
	}

	return array(
		'errors'  => $state['errors'] ?? array(),
		'values'  => $state['values'] ?? array(),
		'success' => ! empty( $state['success'] ),
		'active'  => true,
	);
}

/**
 * Logo icin SVG yukleme: panelde "Logo gorseli" alanina SVG secilebilsin
 * (sanayi-palet-theme ile ayni kural). SVG betik tasiyabildigi icin yalnizca
 * filtresiz HTML yetkisi olanlar (agda yalnizca super yonetici) yukleyebilir.
 */
add_filter( 'upload_mimes', 'kr_upload_mimes' );
function kr_upload_mimes( array $mimes ): array {
	if ( current_user_can( 'unfiltered_html' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

/**
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Cocuk tema (ithalkeresteci, kavakkeresteci) kendi assets/ klasorundeki
 * ikonlari verir; get_theme_file_uri once cocuk temaya bakar.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'kr_favicon', 2 );
add_action( 'login_head', 'kr_favicon' );
function kr_favicon(): void {
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

/**
 * Firma dugumune calisma saatleri ve harita baglantisi (arama verisi).
 *
 * Saatler cocuk temanin manifestindeki 'opening_hours' listesinden gelir;
 * sitede gorunen metin panelin "Calisma Saatleri" alanidir, saat degisirse
 * ikisi birlikte guncellenir. Liste bossa (ithalkeresteci) dugume dokunulmaz.
 * Harita: Iletisim sayfasindaki "Harita Baglantisi" alani.
 */
add_filter( 'nwcs_seo_graph', 'kr_seo_opening_hours' );
function kr_seo_opening_hours( array $graph ): array {
	$hours = (array) ( kr_manifest()['opening_hours'] ?? array() );
	$map   = esc_url_raw( (string) nwcs_field( 'contact', 'details', 'map_url' ) );

	foreach ( $graph as $index => $node ) {
		if ( ! str_ends_with( (string) ( $node['@id'] ?? '' ), '#organization' ) ) {
			continue;
		}

		if ( $hours ) {
			$graph[ $index ]['openingHoursSpecification'] = array_map(
				static fn( array $row ): array => array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $row[0],
					'opens'     => $row[1],
					'closes'    => $row[2],
				),
				$hours
			);
		}

		if ( '' !== $map ) {
			$graph[ $index ]['hasMap'] = $map;
		}
	}

	return $graph;
}
remove_action( 'wp_head', 'rsd_link' );
