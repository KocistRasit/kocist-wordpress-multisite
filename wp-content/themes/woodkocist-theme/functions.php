<?php
/**
 * woodkocist temasi (WOOD KOCIST, YEREL DENEME).
 *
 * Urunler merkezi Urun Havuzu'ndan gelir (nwcs_site_products): panelde bu
 * site icin secilen urunler, secim sirasiyla. Urun eklenince kart eklenir,
 * cikarilinca kaybolur; gorsel eklenince kod plakasinin yerini fotograf alir.
 * Urun detay sayfasi /urun/<slug>/ (eklenti yonlendirir, bu tema cizer).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nwcs_field' ) ) {
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		return null === $fallback ? '' : $fallback;
	}
	function nwcs_rows( $page, $component, $field ) {
		return array();
	}
	function nwcs_edit_attr( $page, $component, $field = '', $row = null, $sub = '' ) {}
}

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/requests.php' );
require_once get_theme_file_path( 'inc/checkout.php' );
require_once get_theme_file_path( 'inc/catalog.php' );
require_once get_theme_file_path( 'inc/cart.php' );
require_once get_theme_file_path( 'inc/nav.php' );

add_action( 'after_setup_theme', 'wk_setup' );
function wk_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form', 'gallery', 'caption' ) );
	// Kurumsal sayfalarin ozeti sayfa basinda ve arama aciklamasinda kullanilir.
	add_post_type_support( 'page', 'excerpt' );
}

add_action( 'wp_enqueue_scripts', 'wk_assets' );
function wk_assets(): void {
	$ver = static fn( string $file ): string => wp_get_theme()->get( 'Version' ) . '.' . (int) @filemtime( get_theme_file_path( $file ) );

	wp_enqueue_style( 'wk-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&display=swap', array(), null );
	wp_enqueue_style( 'wk-site', get_theme_file_uri( 'assets/site.css' ), array( 'wk-fonts' ), $ver( 'assets/site.css' ) );
	wp_enqueue_script( 'wk-site', get_theme_file_uri( 'assets/site.js' ), array(), $ver( 'assets/site.js' ), true );
}

add_action( 'wp_head', 'wk_preconnect', 1 );
function wk_preconnect(): void {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}

/* ====================================================================== *
 * Yardimcilar
 * ====================================================================== */

function wk_link( $url ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	return $url;
}

function wk_multiline( string $value ): string {
	return nl2br( esc_html( $value ) );
}

function wk_paragraphs( string $value ): string {
	$html = '';

	foreach ( preg_split( '/\R\s*\R/u', trim( $value ) ) ?: array() as $block ) {
		if ( '' !== trim( $block ) ) {
			$html .= '<p>' . wk_multiline( trim( $block ) ) . '</p>';
		}
	}

	return $html;
}

function wk_part( string $name, array $args = array() ): void {
	get_template_part( 'template-parts/' . $name, null, $args );
}

/**
 * WhatsApp baglantisi; mesaj kutusuna hazir yazi gelir. $text verilmezse
 * urun sayfasinda urunun mesaji (wk_order_text), baska yerde paneldeki
 * genel mesaj kullanilir. Numara paneldeki whatsapp_url'den.
 */
function wk_whatsapp( string $text = '' ): string {
	$url = trim( (string) nwcs_field( 'global', 'header', 'whatsapp_url' ) );

	if ( '' === $url ) {
		return '';
	}

	if ( '' === $text ) {
		$text = ! empty( $GLOBALS['wk_current_product'] )
			? wk_order_text( $GLOBALS['wk_current_product'] )
			: trim( (string) nwcs_field( 'global', 'header', 'whatsapp_text' ) );
	}

	return '' === $text ? $url : add_query_arg( 'text', rawurlencode( $text ), remove_query_arg( 'text', $url ) );
}

/**
 * Marka: gercek sitedeki logo gibi harf karolari ("WOOD" buyuk, "KOCIST"
 * kucuk). Yazi paneldeki logo_text'ten; tek kelimeyse duz yazi kalir.
 * Karolar gorsel; ekran okuyucu tam adi okur.
 */
function wk_brand_mark(): string {
	$text  = trim( (string) nwcs_field( 'global', 'header', 'logo_text' ) );
	$words = preg_split( '/\s+/u', $text, 2 ) ?: array();

	if ( count( $words ) < 2 ) {
		return esc_html( $text );
	}

	$tiles = static function ( string $word, string $class ): string {
		$html = '';

		foreach ( mb_str_split( $word ) as $char ) {
			$html .= '<span>' . esc_html( $char ) . '</span>';
		}

		return '<span class="' . $class . '" aria-hidden="true">' . $html . '</span>';
	};

	return '<span class="wk-sr">' . esc_html( $text ) . '</span>'
		. $tiles( $words[0], 'wk-mark__top' )
		. $tiles( $words[1], 'wk-mark__sub' );
}

/**
 * Panel onizlemesi: urune ait metin/gorsel tiklaninca Urun Havuzu'ndaki urun
 * acilir (eklentinin nwcs_product_attr'i; eklenti eskiyse hicbir sey basmaz).
 */
function wk_product_src( array $product, string $label ): void {
	if ( function_exists( 'nwcs_product_attr' ) ) {
		nwcs_product_attr( (int) $product['id'], $label );
	}
}

/**
 * Panelde duzenlenen, icinde {degisken} gecen metin (orn. "{sayi} ürün").
 * Degiskenler sablondan gelir; panelde metnin yalnizca kalani degisir.
 *
 * @param array<string, string|int> $vars Degisken adi => deger.
 */
function wk_text( string $page, string $component, string $field, array $vars = array() ): string {
	$map = array();

	foreach ( $vars as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}

	return strtr( (string) nwcs_field( $page, $component, $field ), $map );
}

/**
 * Panel metni icine HTML parca (baglanti, kalin sayi) yerlestirir: metin
 * kacirilir, {degisken}ler verilen hazir HTML ile degisir.
 *
 * @param array<string, string> $html Degisken adi => kacirilmis HTML.
 */
function wk_text_html( string $page, string $component, string $field, array $html ): string {
	$map = array();

	foreach ( $html as $key => $value ) {
		$map[ '{' . $key . '}' ] = $value;
	}

	return strtr( esc_html( (string) nwcs_field( $page, $component, $field ) ), $map );
}

/**
 * nwcs_edit_attr ciktisi metin olarak (bastan bosluklu; onizleme disinda bos):
 * PHP icinde kurulan HTML parcalari (metin icindeki baglanti) icin.
 */
function wk_attr_string( string $page, string $component, string $field = '', ?int $row = null, string $sub = '' ): string {
	ob_start();
	nwcs_edit_attr( $page, $component, $field, $row, $sub );

	return (string) ob_get_clean();
}

/**
 * Onizlemede WordPress sayfasinin/yazisinin basligi ve metni: tiklaninca
 * yazinin duzenleme ekrani acilir (eklenti yoksa hicbir sey basmaz).
 */
function wk_post_src( int $post_id, string $label = 'Sayfa metni' ): void {
	if ( function_exists( 'nwcs_post_attr' ) ) {
		nwcs_post_attr( $post_id, $label );
	}
}

function wk_icon( string $name ): string {
	$paths = array(
		'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8.8 8.5c.3-.6.6-.6.9-.6h.6c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.6l-.5.6c-.1.2-.2.3 0 .6a7 7 0 0 0 3 2.6c.3.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.8.9c.3.1.4.2.4.4 0 .6-.2 1.3-.9 1.7-.7.4-1.6.5-3.2-.1a10 10 0 0 1-4.5-4c-.8-1.3-.9-2.6-.4-3.4z" fill="currentColor"/>',
		'phone'    => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
		'cart'     => '<path d="M3 4h2.2l2.1 10.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.5" cy="19.5" r="1.5" fill="currentColor"/><circle cx="17" cy="19.5" r="1.5" fill="currentColor"/>',
		'search'   => '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'close'    => '<path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'chevron'  => '<path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'prev'     => '<path d="m14 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'next'     => '<path d="m10 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'truck'    => '<path d="M3 6h11v9H3zM14 9h4l3 3.5V15h-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="1.8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="17.5" r="1.8" fill="none" stroke="currentColor" stroke-width="2"/>',
		'shield'   => '<path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m9 12 2.2 2.2L15.5 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'ruler'    => '<path d="M3 16 16 3l5 5L8 21z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m7 12 2 2M10 9l2 2M13 6l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'trash'    => '<path d="M5 7h14M10 7V4.5h4V7M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
	);

	return isset( $paths[ $name ] ) ? '<svg class="wk-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>' : '';
}

/* ====================================================================== *
 * Urunler (Urun Havuzu)
 * ====================================================================== */

/**
 * Sitede gosterilen urunler; her satira havuzdaki urun kodu eklenir.
 *
 * @return array<int, array<string, mixed>>
 */
function wk_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	if ( ! function_exists( 'nwcs_site_products' ) ) {
		return $products;
	}

	$pool = nwcs_pool_products();

	foreach ( nwcs_site_products() as $product ) {
		$product['code'] = (string) ( $pool[ $product['id'] ]['code'] ?? '' );
		$products[]      = $product;
	}

	return $products;
}

/**
 * Urun listesinin kartlari: ayni urunun renkleri (Ürün Havuzu -> Kategoriler
 * -> Renk grupları) tek kart olur; kartta one cikan urun ve "3 renk" etiketi
 * gorunur (eklenti, nwcs_color_collapse). Magaza, kategori, ana sayfa
 * seritleri ve sayilar bununla; arama, sepet, site haritasi ve llms.txt her
 * rengi ayri urun olarak gorur (wk_products).
 *
 * @param array<int, array<string, mixed>> $products
 * @return array<int, array<string, mixed>>
 */
function wk_cards( array $products ): array {
	return function_exists( 'nwcs_color_collapse' ) ? nwcs_color_collapse( $products ) : array_values( $products );
}

/**
 * Sitedeki kart sayisi (Magaza'daki "Tümü", ust menudeki toplam): renk grubu tek urun.
 */
function wk_card_count(): int {
	static $count = null;

	return $count ??= count( wk_cards( wk_products() ) );
}

/**
 * Seriler (panelde duzenlenir): etiket, havuz kategori adi, kisa aciklama ve
 * o seride kac urun oldugu.
 *
 * @return array<int, array{label:string, category:string, text:string, slug:string, count:int}>
 */
function wk_lines(): array {
	$lines = array();

	foreach ( nwcs_rows( 'home', 'catalog', 'lines' ) as $index => $row ) {
		$category = trim( (string) ( $row['category'] ?? '' ) );

		if ( '' === $category ) {
			continue;
		}

		// Kisaltma havuzdaki kategoriden: ad "Şezlonglar" iken kisaltma
		// "ahsap-sezlonglar" olabilir; suzgec kartlardaki kisaltmayla calisir.
		$slug = sanitize_title( $category );

		foreach ( wk_products() as $product ) {
			$found = array_search( $category, $product['categories'], true );

			if ( false !== $found ) {
				$slug = (string) $found;
				break;
			}
		}

		$lines[] = array(
			'row'      => (int) $index, // Panel onizlemesinde Ana Sayfa -> Seriler'in bu satiri.
			'label'    => (string) ( $row['label'] ?? $category ),
			'category' => $category,
			'text'     => (string) ( $row['text'] ?? '' ),
			'slug'     => $slug,
			'count'    => count( wk_cards( array_filter( wk_products(), static fn( array $p ): bool => in_array( $category, $p['categories'], true ) ) ) ),
		);
	}

	return $lines;
}

/**
 * Urun turleri (seri olmayan kategoriler), urun sayisina gore.
 *
 * @return array<string, array{label:string, count:int}>
 */
function wk_types(): array {
	$line_names = wp_list_pluck( wk_lines(), 'category' );
	$types      = array();

	foreach ( wk_products() as $product ) {
		foreach ( $product['categories'] as $slug => $name ) {
			if ( in_array( $name, $line_names, true ) ) {
				continue;
			}

			$types[ $slug ] = array(
				'label' => $name,
				'count' => ( $types[ $slug ]['count'] ?? 0 ) + 1,
			);
		}
	}

	return $types;
}

/**
 * Havuzdaki tek satirlik ozellik metni ("Ad: deger; Ad: deger") -> ciftler.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function wk_specs( string $spec ): array {
	$pairs = array();

	foreach ( preg_split( '/\s*;\s*/u', trim( $spec ) ) ?: array() as $part ) {
		$split = explode( ':', $part, 2 );

		if ( 2 === count( $split ) && '' !== trim( $split[0] ) && '' !== trim( $split[1] ) ) {
			// "Ithal Cam ( Firinlanmis )" -> "Ithal Cam (Firinlanmis)": parantez ici bosluklar toplanir.
			$value   = (string) preg_replace( array( '/\(\s+/u', '/\s+\)/u' ), array( '(', ')' ), trim( $split[1] ) );
			$pairs[] = array( trim( $split[0] ), $value );
		}
	}

	return $pairs;
}

/**
 * Urunun teknik detaylari (etiket, deger). Eklentinin detay basliklari
 * kaydindan, onun sirasiyla (nwcs_product_pairs); eski eklentide spec metni.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function wk_product_specs( array $product ): array {
	return function_exists( 'nwcs_product_pairs' ) ? nwcs_product_pairs( $product ) : wk_specs( (string) ( $product['spec'] ?? '' ) );
}

/**
 * Urun sayfasinin basligi (h1, konum satirinin son ogesi, SEO adi):
 * canlidaki gibi kod + ad ("W-SEZ-2 Kompakt Seri, ..."). Ad zaten kodla
 * basliyorsa ya da kod yoksa yalnizca ad.
 */
function wk_product_heading( array $product ): string {
	$title = trim( (string) $product['title'] );
	$code  = trim( (string) ( $product['code'] ?? '' ) );

	if ( '' === $code || 0 === mb_stripos( $title, $code ) ) {
		return $title;
	}

	return $code . ' ' . $title;
}

/** Urun detaylarinda Lojistik sekmesine giden satirin adi (eklentide NWCS_DELIVERY_LABEL). */
const WK_DELIVERY_LABEL = 'Lojistik ve Teslimat';

/**
 * Detay satiri Lojistik sekmesinin satiri mi? Kural eklentide ortak
 * (Kocist ile ayni); eski eklentide buradaki kopya.
 */
function wk_is_delivery_pair( array $pair ): bool {
	if ( function_exists( 'nwcs_product_is_delivery_pair' ) ) {
		return nwcs_product_is_delivery_pair( $pair );
	}

	return 0 === strcmp( mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $pair[0] ) ) ), mb_strtolower( WK_DELIVERY_LABEL ) );
}

/**
 * Teknik Detaylar tablosunun satirlari: urunun detaylari, Lojistik satiri haric
 * (o satir kendi sekmesinde gosterilir). Eklentideki ortak yardimciya devreder.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function wk_product_table_specs( array $product ): array {
	if ( function_exists( 'nwcs_product_table_specs' ) ) {
		return nwcs_product_table_specs( $product );
	}

	return array_values( array_filter( wk_product_specs( $product ), static fn( array $pair ): bool => ! wk_is_delivery_pair( $pair ) ) );
}

/**
 * Lojistik ve Teslimat sekmesinin metni: urunde "Lojistik ve Teslimat" detay
 * satiri varsa o, yoksa paneldeki site geneli metin.
 *
 * @return array{text:string, own:bool} own: metin urunun kendi satirindan.
 */
function wk_product_delivery_text( array $product ): array {
	$own = '';

	if ( function_exists( 'nwcs_product_delivery_row' ) ) {
		$own = nwcs_product_delivery_row( $product );
	} else {
		foreach ( wk_product_specs( $product ) as $pair ) {
			if ( wk_is_delivery_pair( $pair ) && '' !== trim( $pair[1] ) ) {
				$own = trim( $pair[1] );
				break;
			}
		}
	}

	if ( '' !== $own ) {
		return array( 'text' => $own, 'own' => true );
	}

	return array( 'text' => trim( (string) nwcs_field( 'product', 'labels', 'delivery_text' ) ), 'own' => false );
}

/**
 * Urunun kendi sayfasi var mi? Bu temada aciklamasiz urunun sayfasi da
 * acik (manifest 'product_page_always'); eski eklentide detay metni sart.
 */
function wk_product_has_page( array $product ): bool {
	return function_exists( 'nwcs_product_has_page' ) ? nwcs_product_has_page( $product ) : '' !== trim( (string) ( $product['body'] ?? '' ) );
}

function wk_image( array $product ): array {
	foreach ( (array) ( $product['images'] ?? array() ) as $image ) {
		if ( ! empty( $image['url'] ) ) {
			return $image;
		}
	}

	return array( 'url' => '', 'alt' => '' );
}

/**
 * Urun sayfasinin buyuk gorseli: havuzdaki gorsel 768px (medium_large) gelir,
 * genis ekranda ve retinada bulanik kalir. Havuz sitesinde en buyuk boyut ve
 * srcset alinir; tarayici ekrana uygun olani secer. Olmazsa $image['url'].
 *
 * @return array{src:string, srcset:string, width:int, height:int}
 */
function wk_image_large( array $image ): array {
	$out = array( 'src' => (string) ( $image['url'] ?? '' ), 'srcset' => '', 'width' => 0, 'height' => 0 );
	$id  = (int) ( $image['id'] ?? 0 );

	if ( $id <= 0 ) {
		return $out;
	}

	$switched = function_exists( 'nwcs_pool_blog_id' ) && is_multisite() && (int) nwcs_pool_blog_id() !== get_current_blog_id();

	if ( $switched ) {
		switch_to_blog( (int) nwcs_pool_blog_id() );
	}

	$src = wp_get_attachment_image_src( $id, 'full' );

	if ( $src ) {
		$out['src']    = (string) $src[0];
		$out['width']  = (int) $src[1];
		$out['height'] = (int) $src[2];
		$out['srcset'] = (string) wp_get_attachment_image_srcset( $id, 'full' );
	}

	if ( $switched ) {
		restore_current_blog();
	}

	return $out;
}

/**
 * Urun sayfasinin galerisi: her gorsel icin buyuk hali (wk_image_large) ve
 * kucuk resim. Kucuk resim havuz sitesinden 'thumbnail' boyutunda alinir.
 *
 * @return array<int, array{src:string, srcset:string, width:int, height:int, alt:string, thumb:string}>
 */
function wk_gallery( array $product ): array {
	$out      = array();
	$switched = function_exists( 'nwcs_pool_blog_id' ) && is_multisite() && (int) nwcs_pool_blog_id() !== get_current_blog_id();

	foreach ( (array) ( $product['images'] ?? array() ) as $image ) {
		if ( empty( $image['url'] ) ) {
			continue;
		}

		$large = wk_image_large( $image );
		$thumb = (string) $image['url'];
		$id    = (int) ( $image['id'] ?? 0 );

		if ( $id > 0 ) {
			if ( $switched ) {
				switch_to_blog( (int) nwcs_pool_blog_id() );
			}

			$small = wp_get_attachment_image_src( $id, 'thumbnail' );
			$thumb = $small ? (string) $small[0] : $thumb;

			if ( $switched ) {
				restore_current_blog();
			}
		}

		$out[] = $large + array(
			'alt'   => (string) ( $image['alt'] ?? '' ),
			'thumb' => $thumb,
		);
	}

	return $out;
}

/**
 * Havuzdaki fiyat metni ("10.250 ₺", "1.299,90 ₺") -> sayi; okunamazsa 0
 * (0 ise yapilandirilmis veride teklif uretilmez).
 */
function wk_price_number( string $price ): float {
	$digits = preg_replace( '/[^0-9,]/', '', $price );

	if ( '' === $digits || null === $digits ) {
		return 0.0;
	}

	return (float) str_replace( ',', '.', $digits );
}

/**
 * Urunun WhatsApp mesaji (panel: global.header.whatsapp_product_text).
 */
function wk_order_text( array $product ): string {
	return wk_product_text( 'global', 'header', 'whatsapp_product_text', $product );
}

/**
 * Urunun kategori adi: alt kategori, yoksa seri, yoksa havuzdaki ilk kategori.
 */
function wk_product_category( array $product ): string {
	$place = wk_product_place( $product );
	$group = $place['child'] ?? $place['line'];

	if ( $group ) {
		return (string) $group['label'];
	}

	$names = array_values( (array) ( $product['categories'] ?? array() ) );

	return (string) ( $names[0] ?? '' );
}

/**
 * Panel metnindeki {urun} {kategori} {kod} {url} urunun bilgileriyle dolar;
 * bos kalan "()" ve fazla bosluklar temizlenir.
 */
function wk_product_text( string $page, string $component, string $field, array $product ): string {
	$text = wk_text(
		$page,
		$component,
		$field,
		array(
			'urun'     => html_entity_decode( (string) $product['title'], ENT_QUOTES, 'UTF-8' ),
			'kategori' => html_entity_decode( wk_product_category( $product ), ENT_QUOTES, 'UTF-8' ),
			'kod'      => (string) $product['code'],
			'url'      => (string) ( $product['url'] ?? '' ),
		)
	);

	$text = preg_replace( array( '/\(\s*,\s*/u', '/\s*\(\s*\)/u', '/[ \t]{2,}/u' ), array( '(', '', ' ' ), $text );

	return trim( (string) $text );
}

/**
 * Formlarda ?urun=<havuz no> (eski baglantilar icin "KOD Ad" ya da kod da
 * olur): yalnizca sitede gosterilen bir urune cozulur, yoksa null.
 */
function wk_product_by_ref( string $ref ): ?array {
	$ref = trim( $ref );

	if ( '' === $ref ) {
		return null;
	}

	foreach ( wk_products() as $product ) {
		if ( ( ctype_digit( $ref ) && (int) $ref === (int) $product['id'] )
			|| ( '' !== $product['code'] && 0 === strcasecmp( $ref, (string) $product['code'] ) )
			|| trim( $product['code'] . ' ' . $product['title'] ) === $ref ) {
			return $product;
		}
	}

	return null;
}

/* ====================================================================== *
 * SEO ve GEO: havuz urunlerinin sayfalari (/urun/<slug>/) eklentiye
 * bildirilir; baslik, aciklama, canonical, Product verisi ve llms.txt.
 * ====================================================================== */

add_filter( 'nwcs_seo_extra_pages', 'wk_seo_product_pages' );
function wk_seo_product_pages( array $pages ): array {
	foreach ( wk_products() as $product ) {
		if ( ! wk_product_has_page( $product ) ) {
			continue; // Sayfasi olmayan urun (eklenti 404 verir).
		}

		$image     = wk_image( $product );
		$meta_desc = trim( (string) ( $product['meta_desc'] ?? '' ) );

		$pages[] = array(
			'url'         => $product['url'],
			'name'        => wk_product_heading( $product ), // h1 ile ayni: kod + ad (canlidaki gibi).
			// Havuzdaki meta baslik oldugu gibi; bossa eklenti "ad – site" uretir.
			'title'       => (string) ( $product['meta_title'] ?? '' ),
			'description' => '' !== $meta_desc ? $meta_desc : ( '' !== trim( (string) $product['short'] ) ? $product['short'] : ( '' !== trim( (string) $product['body'] ) ? wp_strip_all_tags( (string) $product['body'] ) : wk_product_fallback_description( $product ) ) ),
			'image'       => ! empty( $image['id'] ) ? (int) $image['id'] : 0,
			'type'        => 'Product',
			'sitemap'     => true,
			'sku'         => $product['code'],
			'price'       => wk_price_number( (string) $product['price'] ),
			'currency'    => 'TRY',
			'properties'  => array_map(
				static fn( array $pair ): array => array( 'name' => $pair[0], 'value' => $pair[1] ),
				array_merge( array( array( 'Ürün kodu', $product['code'] ) ), wk_product_table_specs( $product ) )
			),
		);
	}

	return $pages;
}

/**
 * Kisa aciklamasi ve detay metni olmayan urunun arama aciklamasi: ad ve ilk
 * uc teknik detay ("W-CAR-400K Klasik Seri ... Boyut Sınıfı: Mega Boy, ...").
 * Detay da yoksa yalnizca ad.
 */
function wk_product_fallback_description( array $product ): string {
	$pairs = array_map(
		static fn( array $pair ): string => $pair[0] . ': ' . $pair[1],
		array_slice( wk_product_table_specs( $product ), 0, 3 )
	);

	return wk_product_heading( $product ) . ( $pairs ? '. ' . implode( ', ', $pairs ) . '.' : '' );
}

/**
 * Favicon: SVG (modern tarayicilar), 32px PNG yedegi ve iOS icin 180px ikon.
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'wk_favicon', 2 );
add_action( 'login_head', 'wk_favicon' );
function wk_favicon(): void {
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
