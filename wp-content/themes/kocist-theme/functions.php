<?php
/**
 * Kocist temasi.
 *
 * Tema gorunur metinleri sabit yazmaz; tum degerler Network Content Studio
 * veri katmanindan (nwcs_*) okunur. Eklenti devre disi kalirsa sayfa fatal
 * vermesin diye asagida guvenli yedek fonksiyonlar tanimlanir.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nwcs_field' ) ) {
	// Eklenti kapaliyken metinler manifestteki varsayilanlardan gelir.
	function nwcs_field( $page, $component, $field, $fallback = null ) {
		static $manifest = null;

		if ( null === $manifest ) {
			$manifest = include __DIR__ . '/content-manifest.php';
		}

		$default = $manifest['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? null;

		return is_scalar( $default ) ? $default : ( null === $fallback ? '' : $fallback );
	}
	function nwcs_rows( $page, $component, $field ) {
		// Menu, alt bilgi ve listeler de manifestteki varsayilan satirlarla gelsin.
		$rows = kocist_manifest()['pages'][ $page ]['components'][ $component ]['fields'][ $field ]['default'] ?? array();
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
		return array( 'catalog', 'latest', 'process', 'products', 'blog' );
	}
}

require_once __DIR__ . '/inc/blog.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__ . '/inc/katalog.php';
require_once __DIR__ . '/inc/quote.php';
require_once __DIR__ . '/inc/form.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/basket.php';

add_action( 'after_setup_theme', 'kocist_setup' );
function kocist_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script' ) );
}

/*
 * Temanin sablonla cizdigi sayfalar (page.php slug'a bakiyor). Kurulum
 * betigi bunlari olusturmasa da site eksiksiz acilsin diye tema kendisi
 * acar. Surum secenegi sayesinde her istekte sorgu yapilmaz; var olan bir
 * sayfaya dokunulmaz, kullanici silerse ayni surumde geri getirilmez.
 *
 * Surum 2: Insan Kaynaklari ve Blog sayfalari, yazilar sayfasi ayari ve
 * ilk kurulum blog yazilari (inc/blog.php).
 * Surum 4: Banka Bilgilerimiz sayfasi.
 * Surum 5: Sik Sorulan Sorular sayfasi (/sss/).
 * Surum 6: KVKK aydinlatma metni sayfasi (/kvkk/).
 * Surum 7: Arama (/arama/) ve Teklif Sepeti (/teklif-sepeti/) sayfalari
 * (inc/search.php, inc/basket.php). Acilana kadar ust menudeki arama ve sepet
 * dugmeleri ile urun sayfasindaki "Teklif sepetine ekle" basilmaz.
 */
const KOCIST_PAGES_VERSION = '7';

add_action( 'after_switch_theme', 'kocist_ensure_pages' );
add_action( 'admin_init', 'kocist_maybe_ensure_pages' );

function kocist_maybe_ensure_pages(): void {
	// Yedek yol yalnizca yonetici ya da WP-CLI icin: admin_init anonim
	// admin-post.php (form) isteklerinde de tetiklenir; ziyaretci kurulumu
	// (sayfa acma, kalici baglanti, rewrite) baslatamasin.
	if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	if ( KOCIST_PAGES_VERSION !== get_option( 'kocist_pages_version' ) ) {
		kocist_ensure_pages();
	}
}

function kocist_ensure_pages(): void {
	// Ayni istekte iki kanca birden tetiklenirse ikinci kez calisma.
	static $done = false;

	if ( $done ) {
		return;
	}

	$done = true;

	$pages = array(
		'kurumsal'         => 'Kurumsal',
		'urun'             => 'Ahşap Tavuk Kümesi',
		'iletisim'         => 'İletişim',
		'insan-kaynaklari' => 'İnsan Kaynakları',
		'blog'             => 'Blog – Haberler',
		'katalog'          => 'Katalog',
		'banka-bilgilerimiz' => 'Banka Bilgilerimiz',
		'sss'              => 'Sık Sorulan Sorular',
		'kvkk'             => 'KVKK Aydınlatma Metni',
		'arama'            => 'Arama',
		'teklif-sepeti'    => 'Teklif Sepeti',
	);

	$ids = array();

	foreach ( $pages as $slug => $title ) {
		$ids[ $slug ] = kocist_ensure_page( $slug, $title );
	}

	/*
	 * Yazilar sayfasi yalnizca statik on sayfa secildiginde calisir. On sayfa
	 * hic secilmemisse (yeni site) bos bir "Ana Sayfa" acilip secilir;
	 * front-page.php her iki durumda da ayni ana sayfayi cizer. Kullanicinin
	 * yaptigi secim ezilmez.
	 */
	if ( 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) {
		$front = kocist_ensure_page( 'ana-sayfa', 'Ana Sayfa' );

		if ( $front && ! get_option( 'page_on_front' ) ) {
			update_option( 'page_on_front', $front );
		}

		if ( get_option( 'page_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
		}
	}

	if ( ! get_option( 'page_for_posts' ) && $ids['blog'] ) {
		update_option( 'page_for_posts', $ids['blog'] );
	}

	kocist_seed_blog_posts();

	update_option( 'kocist_pages_version', KOCIST_PAGES_VERSION );
}

/**
 * Sayfayi acar; ayni adres adinda sayfa varsa ona dokunmadan kimligini dondurur.
 * Not: get_page_by_path cop kutusundaki sayfayi bulmaz (WordPress cope atilan
 * sayfanin adresine '__trashed' ekler); o durumda yeni sayfa acilir. 'trash'
 * kontrolu yalnizca bu eki almamis eski kayitlar icin.
 */
function kocist_ensure_page( string $slug, string $title ): int {
	$page = get_page_by_path( $slug, OBJECT, 'page' );

	if ( $page ) {
		return 'trash' === $page->post_status ? 0 : (int) $page->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
}

add_action( 'wp_enqueue_scripts', 'kocist_assets' );
function kocist_assets(): void {
	/*
	 * Yazi tipi (Archivo) ve Tailwind sifirlamasi: sabit dosya, kendi
	 * sunucumuzdan (assets/css/base.css). Eskiden Tailwind CDN betigi ve
	 * Google Fonts sayfanin boyanmasini geciktiriyordu.
	 */
	wp_enqueue_style(
		'kocist-base',
		get_theme_file_uri( 'assets/css/base.css' ),
		array(),
		(string) filemtime( get_theme_file_path( 'assets/css/base.css' ) )
	);

	// Surum dosyanin degisim zamani: stil degisince tarayici eski kopyayi tutmasin.
	wp_enqueue_style( 'kocist-style', get_stylesheet_uri(), array( 'kocist-base' ), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );

	// Header kendi dosyasinda: style.css bolum bolum Tailwind'e tasinirken
	// kucultulecek, header kurallari onunla birlikte dagilmasin.
	wp_enqueue_style(
		'kocist-header',
		get_theme_file_uri( 'assets/css/header.css' ),
		array( 'kocist-style' ),
		(string) filemtime( get_theme_file_path( 'assets/css/header.css' ) )
	);

	// Footer ve ustundeki hareketli serit her sayfada.
	wp_enqueue_style(
		'kocist-footer',
		get_theme_file_uri( 'assets/css/footer.css' ),
		array( 'kocist-style' ),
		wp_get_theme()->get( 'Version' )
	);

	// Kayan menu gostergesi, acilir alt menuler ve mobil menu.
	wp_enqueue_script(
		'kocist-nav',
		get_theme_file_uri( 'assets/js/nav.js' ),
		array(),
		(string) filemtime( get_theme_file_path( 'assets/js/nav.js' ) ),
		true
	);

	// Ust menudeki arama kutusu ve teklif sepeti sayisi (cerezden) her sayfada;
	// urun sayfasinda sepete ekleme, sepet sayfasinda adetler (inc/basket.php).
	wp_enqueue_script(
		'kocist-teklif',
		get_theme_file_uri( 'assets/js/teklif.js' ),
		array(),
		(string) filemtime( get_theme_file_path( 'assets/js/teklif.js' ) ),
		true
	);

	$is_search = is_page( 'arama' );
	$is_basket = is_page( 'teklif-sepeti' );

	// Sepete ekleme (urun sayfasi), sepet ve arama sayfasi.
	wp_register_style(
		'kocist-teklif',
		get_theme_file_uri( 'assets/css/teklif.css' ),
		array( 'kocist-style' ),
		(string) filemtime( get_theme_file_path( 'assets/css/teklif.css' ) )
	);

	// Hero ve urun gamlari yalnizca ana sayfada; varliklari da orada yuklensin.
	if ( is_front_page() ) {
		wp_enqueue_style(
			'kocist-hero',
			get_theme_file_uri( 'assets/css/hero.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/hero.css' ) )
		);

		wp_enqueue_style(
			'kocist-catalog',
			get_theme_file_uri( 'assets/css/catalog.css' ),
			array( 'kocist-style' ),
			wp_get_theme()->get( 'Version' )
		);

		wp_enqueue_script(
			'kocist-hero',
			get_theme_file_uri( 'assets/js/hero.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/hero.js' ) ),
			true
		);

		wp_enqueue_script(
			'kocist-catalog',
			get_theme_file_uri( 'assets/js/catalog.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Siparis sureci: cizgi animasyonu bolum gorunume girince baslar.
		wp_enqueue_style(
			'kocist-process',
			get_theme_file_uri( 'assets/css/process.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/process.css' ) )
		);

		wp_enqueue_script(
			'kocist-process',
			get_theme_file_uri( 'assets/js/process.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/process.js' ) ),
			true
		);

		// Son eklenen urunler seridi. Surum dosya zamanindan: degisiklik
		// tarayici onbelleginde takilmasin.
		wp_enqueue_style(
			'kocist-latest',
			get_theme_file_uri( 'assets/css/latest.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/latest.css' ) )
		);

		wp_enqueue_script(
			'kocist-latest',
			get_theme_file_uri( 'assets/js/latest.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/latest.js' ) ),
			true
		);
	}

	// Iletisim sayfasi varliklari yalnizca o sayfada. Form duz POST; betik
	// yalnizca haritadaki isletme sekmeleri icin (assets/js/contact.js).
	// Form alanlari (assets/css/form.css) iletisim ve urun sayfasinda ortak.
	wp_register_style(
		'kocist-form',
		get_theme_file_uri( 'assets/css/form.css' ),
		array( 'kocist-style' ),
		(string) filemtime( get_theme_file_path( 'assets/css/form.css' ) )
	);

	if ( is_page( 'iletisim' ) ) {
		wp_enqueue_style(
			'kocist-contact',
			get_theme_file_uri( 'assets/css/contact.css' ),
			array( 'kocist-style', 'kocist-form' ),
			(string) filemtime( get_theme_file_path( 'assets/css/contact.css' ) )
		);

		wp_enqueue_script(
			'kocist-contact',
			get_theme_file_uri( 'assets/js/contact.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/contact.js' ) ),
			true
		);
	}

	// Katalog: belge ve sertifika sekmeleri, sertifika buyuk gorunumu.
	if ( is_page( 'katalog' ) ) {
		wp_enqueue_style(
			'kocist-katalog',
			get_theme_file_uri( 'assets/css/katalog.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/katalog.css' ) )
		);

		wp_enqueue_script(
			'kocist-katalog',
			get_theme_file_uri( 'assets/js/katalog.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/katalog.js' ) ),
			true
		);
	}

	// Banka Bilgilerimiz: hesap kartlari ve IBAN kopyalama.
	if ( is_page( 'banka-bilgilerimiz' ) ) {
		wp_enqueue_style(
			'kocist-banka',
			get_theme_file_uri( 'assets/css/banka.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/banka.css' ) )
		);

		wp_enqueue_script(
			'kocist-banka',
			get_theme_file_uri( 'assets/js/banka.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/banka.js' ) ),
			true
		);
	}

	// Sik sorulan sorular: /sss/ ve kategori sorulari (kategori sayfasi ve urun
	// sayfasi ayni soru-cevap cizimini kullanir; template-parts/category-page.php,
	// sections/product-faq-link.php).
	if ( is_page( 'sss' ) || kocist_is_catalog_request() || get_query_var( 'nwcs_product' ) ) {
		wp_enqueue_style(
			'kocist-faq',
			get_theme_file_uri( 'assets/css/faq.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/faq.css' ) )
		);
	}

	// Kurumsal, Insan Kaynaklari ve KVKK sayfalari.
	if ( is_page( array( 'kurumsal', 'insan-kaynaklari', 'kvkk' ) ) ) {
		wp_enqueue_style(
			'kocist-pages',
			get_theme_file_uri( 'assets/css/pages.css' ),
			array( 'kocist-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}

	// Blog listesi, arsivler, tekil yazi ve ana sayfadaki son yazilar. Kategori
	// ve urun detayi WordPress'e yazi listesi gibi gorunur; onlarda gerekmez.
	$is_blog_like = ( is_home() || is_archive() ) && ! kocist_is_catalog_request() && ! get_query_var( 'nwcs_product' );

	if ( $is_blog_like || is_singular( 'post' ) || is_front_page() ) {
		wp_enqueue_style(
			'kocist-blog',
			get_theme_file_uri( 'assets/css/blog.css' ),
			array( 'kocist-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}

	// Kategori sayfalari, konum yolu ve urun detayindaki "ayni kategoride" seridi.
	$is_product_detail = (bool) get_query_var( 'nwcs_product' );

	// Arama sonuclari ve sepet satirlari da kategori kartlari ve konum yoluyla cizilir.
	if ( kocist_is_catalog_request() || $is_product_detail || $is_search || $is_basket ) {
		wp_enqueue_style(
			'kocist-category',
			get_theme_file_uri( 'assets/css/category.css' ),
			array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/category.css' ) )
		);
	}

	// Kategori sayfasinin bos durum butonlari urun sayfasinin buton stilini
	// kullanir; arama ve sepet sayfasi da (sepette e-posta formu: form.css).
	if ( kocist_is_catalog_request() || $is_search || $is_basket ) {
		wp_enqueue_style(
			'kocist-product',
			get_theme_file_uri( 'assets/css/product.css' ),
			$is_basket ? array( 'kocist-style', 'kocist-form' ) : array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/product.css' ) )
		);
	}

	// Urun sayfasi varliklari: ornek urun sayfasi ve havuz urunu detayi
	// (havuz urununde teklif formu sayfada: form alanlari da yuklenir).
	if ( is_page( 'urun' ) || $is_product_detail ) {
		wp_enqueue_style(
			'kocist-product',
			get_theme_file_uri( 'assets/css/product.css' ),
			$is_product_detail ? array( 'kocist-style', 'kocist-form' ) : array( 'kocist-style' ),
			(string) filemtime( get_theme_file_path( 'assets/css/product.css' ) )
		);

		wp_enqueue_script(
			'kocist-product',
			get_theme_file_uri( 'assets/js/product.js' ),
			array(),
			(string) filemtime( get_theme_file_path( 'assets/js/product.js' ) ),
			true
		);

		// Gorsel buyutme penceresi. Panel onizlemesinde yuklenmez: orada
		// gorsele tiklamak alanini duzenlemeye acar.
		if ( ! ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
			wp_enqueue_style(
				'kocist-lightbox',
				get_theme_file_uri( 'assets/css/lightbox.css' ),
				array( 'kocist-product' ),
				(string) filemtime( get_theme_file_path( 'assets/css/lightbox.css' ) )
			);

			// Pencere icinde ikinci kademe yakinlastirma (kereste temalariyla ayni motor).
			wp_enqueue_script(
				'kocist-zoom',
				get_theme_file_uri( 'assets/js/zoom.js' ),
				array(),
				(string) filemtime( get_theme_file_path( 'assets/js/zoom.js' ) ),
				true
			);

			wp_enqueue_script(
				'kocist-lightbox',
				get_theme_file_uri( 'assets/js/lightbox.js' ),
				array( 'kocist-zoom' ),
				(string) filemtime( get_theme_file_path( 'assets/js/lightbox.js' ) ),
				true
			);
		}
	}

	// Teklif sepeti ve arama gorunumu en sonda: urun ve kategori stillerinin ustune.
	if ( $is_product_detail || $is_search || $is_basket ) {
		wp_enqueue_style( 'kocist-teklif' );
	}
}

/**
 * Erken yukleme: yazi tipi dosyalari ve ana sayfada hero kapak karesi
 * (sayfanin en buyuk ogesi, LCP). Ucu de CSS icinden istenir; tarayici
 * onlari ancak CSS inince bulurdu. Turkce metin iki alt kumeyi de kullanir
 * (ç ö ü latin, ğ ş İ latin-ext).
 */
add_action( 'wp_head', 'kocist_preload_assets', 1 );
function kocist_preload_assets(): void {
	foreach ( array( 'archivo-latin', 'archivo-latin-ext' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin />' . PHP_EOL,
			esc_url( get_theme_file_uri( 'assets/fonts/' . $font . '.woff2' ) )
		);
	}

	if ( is_front_page() ) {
		printf(
			'<link rel="preload" href="%s" as="image" fetchpriority="high" />' . PHP_EOL,
			esc_url( get_theme_file_uri( 'assets/video/tesis-kapak.jpg' ) )
		);
	}
}

/**
 * Urun detayinda akordeonun kapali panelleri ilk boyamada da kapali olsun:
 * betik yuklenecek sayfada <html>'e erken "k-js" sinifi. Betik yoksa sinif da
 * yok, paneller acik kalir (assets/css/product.css).
 */
add_action( 'wp_head', 'kocist_product_js_flag', 1 );
function kocist_product_js_flag(): void {
	if ( get_query_var( 'nwcs_product' ) ) {
		echo "<script>document.documentElement.classList.add('k-js');</script>" . PHP_EOL;
	}
}

/**
 * Ana menuyu alt menuleriyle birlikte kurar.
 *
 * Ust seviye 'global.header.menu' repeater'indan gelir. Bir satirin 'submenu'
 * degeri bos degilse, o deger bir bilesen anahtari olarak okunur ve alt menu
 * ogeleri 'global.<anahtar>.items' repeater'indan alinir.
 *
 * Neden ayri bilesenler: sema bir repeater'a en fazla 50 satir veriyor
 * (schema.php, NWCS_REPEATER_MAX_CAP) ve repeater icinde repeater'i
 * reddediyor. Tum menu tek listeye sigmiyor; ayrica 60 satirlik tek form
 * panelde duzenlenebilir olmaktan cikardi.
 *
 * 'submenu' degeri panelden gelen serbest metindir, ama manifestte karsiligi
 * olmayan bir anahtar nwcs_rows() tarafindan bos dizi olarak doner; uydurma
 * bir deger menuyu bozmaz, yalnizca acilir menu olusmaz.
 *
 * @return array Her oge: array{ label: string, url: string, children: array }
 */
function kocist_menu_items(): array {
	$menu = array();

	foreach ( nwcs_rows( 'global', 'header', 'menu' ) as $row_index => $row ) {
		$label = trim( (string) ( $row['label'] ?? '' ) );

		if ( '' === $label ) {
			continue;
		}

		$children = array();
		$submenu  = trim( (string) ( $row['submenu'] ?? '' ) );
		$url      = (string) ( $row['url'] ?? '' );

		// Urun grubuysa (inc/catalog.php) alt ogeler kategori sayfalarina,
		// ust oge de panelde hala varsayilan katalog capasi yaziyorsa grup
		// sayfasina gider. Grup menu metninden degil satirdan bulunur ("Urun
		// grubu anahtari"): ad degisse de bag kopmaz.
		$group = kocist_catalog_group_for_row( (int) $row_index );

		if ( $group && kocist_is_catalog_placeholder( $url ) ) {
			$url = $group['url'];
		}

		if ( $group ) {
			// Urun grubu: yerlesik kategoriler ve grubun ek baglantilari (inc/catalog.php).
			foreach ( $group['subs'] as $sub ) {
				$children[] = array(
					'label' => $sub['name'],
					'url'   => $sub['menu_url'],
					'edit'  => $sub['edit'],
				);
			}
		} elseif ( '' !== $submenu ) {
			foreach ( kocist_hide_missing_sss( nwcs_rows( 'global', $submenu, 'items' ) ) as $child_index => $child ) {
				$child_label = trim( (string) ( $child['label'] ?? '' ) );

				if ( '' === $child_label ) {
					continue;
				}

				$children[] = array(
					'label' => $child_label,
					'url'   => (string) ( $child['url'] ?? '' ),
					// Panelde hangi satir (onizlemede tiklaninca o satir acilir).
					'edit'  => array( 'global', $submenu, 'items', (int) $child_index, 'label' ),
				);
			}
		}

		$menu[] = array(
			'label'    => $label,
			'url'      => $url,
			'children' => $children,
			'group'    => $group,
			'submenu'  => $submenu,
		);
	}

	return $menu;
}

/**
 * Acilir menu kac sutuna bolunsun.
 *
 * Hirdavat'ta 24, Dekorasyon'da 14 oge var; tek sutunda panel ekrani asiyor.
 * Sutun sayisi oge sayisindan turetilir, elle ayar gerekmez.
 */
function kocist_menu_columns( int $count ): int {
	if ( $count > 16 ) {
		return 3;
	}

	if ( $count > 7 ) {
		return 2;
	}

	return 1;
}

/**
 * Havuz urunu detay sayfasinda (/urun/<slug>/) sekme basligi urunun adi olsun.
 *
 * Detay sayfasi eklentinin sorgu degiskeniyle aciliyor; WordPress'in kendi
 * sorgusunda bir yazi olmadigindan baslik yalnizca site adina dusuyordu.
 */
add_filter( 'document_title_parts', 'kocist_product_title_parts' );
function kocist_product_title_parts( array $parts ): array {
	$slug = get_query_var( 'nwcs_product' );

	if ( ! $slug || ! function_exists( 'nwcs_site_product_by_slug' ) ) {
		return $parts;
	}

	$product = nwcs_site_product_by_slug( sanitize_title( (string) $slug ) );

	if ( ! $product || '' === trim( (string) ( $product['title'] ?? '' ) ) ) {
		return $parts;
	}

	$parts['title'] = $product['title'];
	$parts['site']  = get_bloginfo( 'name', 'display' );
	unset( $parts['tagline'] );

	return $parts;
}

/*
 * /urun/ (urunsuz) panelde duzenlenen ornek urun sablonu, yayinda bir urun
 * degil: ziyaretci ve arama motoru urun kategorilerine gider. Panel
 * onizlemesi sablonu gormeye devam eder. Havuz urunleri (/urun/<slug>/)
 * eklentinin sorgu degiskeniyle acilir, bu sayfaya dusmez.
 */
add_action( 'template_redirect', 'kocist_sample_product_redirect' );
function kocist_sample_product_redirect(): void {
	if ( ! is_page( 'urun' ) || get_query_var( 'nwcs_product' ) || ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
		return;
	}

	wp_safe_redirect( home_url( '/kategoriler/' ), 301 );
	exit;
}

/**
 * Logo icin SVG yukleme: panelde "Logo Gorseli" alanina SVG secilebilsin
 * (sanayi-palet-theme ile ayni kural). SVG betik tasiyabildigi icin yalnizca
 * filtresiz HTML yetkisi olanlar (agda yalnizca super yonetici) yukleyebilir.
 */
add_filter( 'upload_mimes', 'kocist_upload_mimes' );
function kocist_upload_mimes( array $mimes ): array {
	if ( current_user_can( 'unfiltered_html' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

/**
 * Favicon: kocist.com.tr'nin kendi yaprak ikonu (42x42, canli siteden).
 * Panelde (Ozellestir > Site Kimligi) site ikonu secilirse WordPress'inki
 * gecerli olur; tema kendi ikonunu basmaz.
 */
add_action( 'wp_head', 'kocist_favicon', 2 );
add_action( 'login_head', 'kocist_favicon' );
function kocist_favicon(): void {
	if ( has_site_icon() ) {
		return;
	}

	printf(
		'<link rel="icon" type="image/png" href="%s" />' . "
",
		esc_url( get_theme_file_uri( 'assets/img/favicon.png' ) )
	);
}

/**
 * Ana sayfa bolum sirasi.
 *
 * Panel, kayitli siraya sonradan eklenen bolumu en sona koyuyor. Sonradan
 * eklenen bolum kayitli sirada yoksa (kullanici henuz yerini secmemis)
 * tasarlandigi yere alinir; panelde sira kaydedildiyse o gecerlidir:
 * son eklenen urunler urun gruplarinin, siparis sureci son eklenenlerin altina.
 */
function kocist_home_sections(): array {
	$order  = nwcs_section_order( 'home' );
	$stored = get_option( 'nwcs_section_order', array() );
	$saved  = is_array( $stored ) && isset( $stored['home'] ) && is_array( $stored['home'] ) ? $stored['home'] : array();

	$placements = array(
		'latest'  => 'catalog',
		'process' => 'latest',
	);

	foreach ( $placements as $key => $after ) {
		if ( in_array( $key, $saved, true ) || ! in_array( $key, $order, true ) || ! in_array( $after, $order, true ) ) {
			continue;
		}

		$order = array_values( array_diff( $order, array( $key ) ) );
		array_splice( $order, (int) array_search( $after, $order, true ) + 1, 0, array( $key ) );
	}

	return $order;
}

/**
 * Blog listesi ve arsivlerde sayfa basina 12 yazi: uc sutunlu izgarayi
 * tam satirlarla doldurur.
 */
add_action( 'pre_get_posts', 'kocist_blog_per_page' );
function kocist_blog_per_page( WP_Query $query ): void {
	if ( ! is_admin() && $query->is_main_query() && ( $query->is_home() || $query->is_archive() ) ) {
		$query->set( 'posts_per_page', 12 );
	}
}

/**
 * Bolum sablonunu basar. $args sablonun icinde ayni adla okunur
 * (ornegin page-head.php hangi sayfanin basligini cizecegini buradan alir).
 */
function kocist_section( string $key, array $args = array() ): void {
	$file = get_theme_file_path( "template-parts/sections/{$key}.php" );

	if ( file_exists( $file ) ) {
		include $file;
	}
}

/**
 * Menu ogesi su an goruntulenen sayfayi mi isaret ediyor?
 *
 * Yalnizca sayfa yolu karsilastirilir. Capali baglantilar (/#katalog, #kereste)
 * ve dis adresler hicbir zaman aktif sayilmaz; kayan gosterge bunlara
 * demirlenmez. Yol, kocist_link() ile cozulmus tam adresten alinir; alt dizin
 * kurulumunda (/kocist/kurumsal/) istek yoluyla bire bir karsilastirilabilsin.
 */
function kocist_is_current_menu_item( string $url ): bool {
	$url = trim( $url );

	if ( '' === $url || str_contains( $url, '#' ) ) {
		return false;
	}

	$url  = kocist_link( $url );
	$host = wp_parse_url( $url, PHP_URL_HOST );

	// Baska bir alan adina gidiyorsa aktif olamaz.
	if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return false;
	}

	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );

	return $path === kocist_current_path();
}

/**
 * Menu karsilastirmasi icin su anki yol.
 *
 * Blog listesi, arsivler ve tekil yazilar menude "Blog" ogesine baglidir;
 * bu sayfalarda yol, yazilar sayfasinin yolu sayilir.
 */
function kocist_current_path(): string {
	// Havuz urunu detayi (/urun/<slug>/) ve kategori sayfalari WordPress
	// sorgusunda yazi listesi gibi gorunur; blog sayilmasin.
	$is_blog = ( is_home() && ! is_front_page() ) || is_archive() || is_singular( 'post' );

	if ( $is_blog && ! get_query_var( 'nwcs_product' ) && ! kocist_is_catalog_request() ) {
		return trim( (string) wp_parse_url( kocist_blog_url(), PHP_URL_PATH ), '/' );
	}

	return trim( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );
}

/**
 * Ust menu ogesi aktif mi: kendi adresi ya da alt ogelerinden biri su anki
 * sayfa ise (Insan Kaynaklari acikken "Kurumsal" isaretli kalir).
 */
function kocist_is_current_menu_branch( array $item ): bool {
	if ( kocist_is_current_menu_item( (string) $item['url'] ) ) {
		return true;
	}

	foreach ( $item['children'] ?? array() as $child ) {
		if ( kocist_is_current_menu_item( kocist_link( $child['url'], (string) $item['url'] ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Sayfalarda gercekten var olan capalar ve bulunduklari yol.
 *
 * Panelde '#katalog' gibi yalin capa yazilabiliyor; bu capa yalnizca ana
 * sayfada karsiligi olan bir bolumu isaret eder. Baska sayfadan tiklandiginda
 * da dogru yere gitsin diye yalin capa buradaki yola baglanir.
 */
function kocist_anchor_targets(): array {
	return array(
		'katalog'          => '/#katalog',
		'urunler'          => '/#urunler',
		'teklif'           => '/iletisim/',
		'harita'           => '/iletisim/#harita',

		// Panelde eski demo menusunden kalan capalar; artik gercek sayfalari var.
		'insan-kaynaklari' => '/insan-kaynaklari/',
		'blog'             => '/blog/',
		'referanslar'      => '/kurumsal/',
		'belgeler'         => '/katalog/?tab=belgeler',
		'banka-bilgileri'  => '/banka-bilgilerimiz/',
		'kvkk'             => '/kvkk/',
	);
}

/**
 * Hedefi olmayan yalin capa mi (#instagram, #kereste)?
 */
function kocist_is_dead_anchor( $url ): bool {
	$url = trim( (string) $url );

	if ( ! str_starts_with( $url, '#' ) ) {
		return false;
	}

	return ! isset( kocist_anchor_targets()[ substr( $url, 1 ) ] );
}

/**
 * Panelden gelen baglantiyi siteye gore cozer; cikti her zaman esc_url ile basilir.
 *
 * - Bos deger '#' olur.
 * - '/' ile baslayan yol alt sitenin adresine baglanir: ag alt dizinle
 *   kuruldugunda '/kurumsal/' ana siteye degil '/kocist/kurumsal/'a gider.
 * - Bilinen yalin capa (#katalog) bulundugu sayfanin tam adresine baglanir.
 * - Hedefi olmayan yalin capa $fallback verilmisse ona duser (menude ust
 *   ogenin adresi, slaytta katalog); verilmemisse oldugu gibi kalir.
 * - Tam adres, tel:, mailto: dokunulmadan doner.
 */
function kocist_link( $url, string $fallback = '' ): string {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}

	if ( str_starts_with( $url, '#' ) && '#' !== $url ) {
		$targets = kocist_anchor_targets();
		$anchor  = substr( $url, 1 );

		if ( isset( $targets[ $anchor ] ) ) {
			return home_url( $targets[ $anchor ] );
		}

		return '' !== trim( $fallback ) ? kocist_link( $fallback ) : $url;
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	// Mesajsiz WhatsApp baglantisi ortak varsayilan mesaji alir (inc/quote.php).
	if ( function_exists( 'kocist_is_whatsapp_url' ) && kocist_is_whatsapp_url( $url ) ) {
		return kocist_wa_with_text( $url, kocist_wa_default_message() );
	}

	return $url;
}

/**
 * Footer sosyal ikonu basar.
 *
 * Once eklentinin ikon kutuphanesine sorulur. Eklentide olmayan sosyal ag
 * isaretleri (instagram, facebook, linkedin, youtube) icin temadaki sabit
 * listeye dusulur; eklentiye dokunmadan footer bos kalmaz. Eklenti ileride
 * bu anahtarlari eklerse onun cizimi kullanilir.
 *
 * Panel, kutuphanesinde olmayan ikon anahtarini kayitta bosaltir; bu yuzden
 * ikon bos gelirse satirin adindan (Instagram -> instagram) tahmin edilir.
 */
function kocist_social_icon( string $key, string $label = '', string $class = 'k-footer__social-icon', int $size = 18 ): void {
	if ( '' === trim( $key ) ) {
		$key = $label;
	}

	if ( function_exists( 'nwcs_icon_svg' ) ) {
		$svg = nwcs_icon_svg( $key, $class, $size );

		if ( '' !== $svg ) {
			echo $svg; // phpcs:ignore WordPress.Security.EscapingOutput -- eklentinin sabit ikon listesi.
			return;
		}
	}

	$paths = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="16.8" cy="7.2" r="1"/>',
		'facebook'  => '<path d="M15 3h-2.2A3.8 3.8 0 0 0 9 6.8V10H6.5v3.5H9V21h3.5v-7.5H15l.6-3.5h-3.1V6.8a.4.4 0 0 1 .4-.4H15z"/>',
		'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10.5V17"/><path d="M8 7.4v.2"/><path d="M12 17v-3.6a2.1 2.1 0 0 1 4.2 0V17"/><path d="M12 10.5V17"/>',
		'youtube'   => '<rect x="2.5" y="6" width="19" height="12" rx="3.6"/><path d="m10.4 9.6 5.1 2.4-5.1 2.4z"/>',
	);

	$key = sanitize_key( $key );

	if ( ! isset( $paths[ $key ] ) ) {
		return;
	}

	printf(
		'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $class ),
		$size,
		$paths[ $key ] // phpcs:ignore WordPress.Security.EscapingOutput -- sabit liste.
	);
}

/**
 * Panelde gorsel secilmemisse temanin kendi gorselini dondurur.
 *
 * Gercek marka gorselleri tema icinde (assets/img) duruyor; kurulum betigi
 * onlari medyaya yuklemese de site yer tutucuyla acilmasin. Panelden bir
 * gorsel secildiginde her zaman o kullanilir.
 *
 * Ortak seed betigi medyaya "demo yer tutucusu" alt metinli uretilmis
 * gorseller koyuyor; bunlar da bos sayilir ki gercek fotograf gorunsun.
 */
function kocist_image_or_default( array $image, string $file, string $alt = '', bool $theme_fallback = false ): array {
	if ( kocist_is_placeholder_image( $image ) ) {
		$image = array( 'id' => 0, 'url' => '', 'alt' => '' );
	}

	/*
	 * Tema fotograflari simdilik kullanilmiyor: gercek fotograflar gelene
	 * kadar her gorsel alaninda "gorsel yakinda" alani gorunur. Yalnizca
	 * logo ile acikca istenen yerler (ana sayfa hero'sunun sol karti)
	 * temadaki dosyaya duser.
	 */
	if ( ! $theme_fallback && 'logo.png' !== $file ) {
		$file = '';
	}

	if ( ! empty( $image['url'] ) || '' === $file || ! file_exists( get_theme_file_path( 'assets/img/' . $file ) ) ) {
		return $image;
	}

	// Boyut img etiketine yazilir: gorsel inene kadar yeri ayrilir, sayfa kaymaz.
	$size = wp_getimagesize( get_theme_file_path( 'assets/img/' . $file ) );

	return array(
		'id'     => 0,
		'url'    => get_theme_file_uri( 'assets/img/' . $file ),
		'alt'    => '' !== ( $image['alt'] ?? '' ) ? $image['alt'] : $alt,
		'width'  => $size ? (int) $size[0] : 0,
		'height' => $size ? (int) $size[1] : 0,
	);
}

/**
 * img etiketi icin width/height nitelikleri (biliniyorsa); bilinmiyorsa bos.
 */
function kocist_image_size_attr( array $image ): string {
	if ( empty( $image['width'] ) || empty( $image['height'] ) ) {
		return '';
	}

	return sprintf( ' width="%d" height="%d"', (int) $image['width'], (int) $image['height'] );
}

/**
 * Seed betiginin urettigi yer tutucu gorsel mi? Alt metninden anlasilir.
 */
function kocist_is_placeholder_image( array $image ): bool {
	$alt = (string) ( $image['alt'] ?? '' );

	if ( false !== mb_stripos( $alt, 'yer tutucu' ) || false !== mb_stripos( $alt, 'örnek' ) ) {
		return true;
	}

	/*
	 * Temadaki demo fotograflarin medyaya yuklenmis kopyalari da yer tutucu
	 * sayilir (ornek: uploads/.../playwood-300x200.webp). Ayni adli dosya
	 * assets/img icinde varsa gercek urun fotografi degildir.
	 */
	$path = (string) wp_parse_url( (string) ( $image['url'] ?? '' ), PHP_URL_PATH );

	if ( '' === $path || ! str_contains( $path, '/uploads/' ) ) {
		return false;
	}

	$name = preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', basename( $path ) );

	return file_exists( get_theme_file_path( 'assets/img/' . $name ) );
}

/**
 * Havuz urunu icin gosterilecek gorsel.
 *
 * Havuzdaki gorsel gercekse o kullanilir. Bos ya da yer tutucuysa urunun
 * adina bakilip temadaki en yakin grup fotografina dusulur.
 */
function kocist_product_image( array $product ): array {
	$key = sanitize_title( ( $product['slug'] ?? '' ) . ' ' . ( $product['title'] ?? '' ) );
	$map = array(
		'kereste'    => 's2-kereste.jpg',
		'dekorasyon' => 's2-dekorasyon.jpg',
		'hirdavat'   => 's2-hirdavat.jpg',
		'palet'      => 's2-ambalaj.jpg',
		'kafes'      => 's2-ambalaj.jpg',
		'sandik'     => 's2-ambalaj.jpg',
	);
	// Adindan cikmiyorsa urunun grubunun fotografi (inc/catalog.php), en son atolye.
	$by_group = array(
		'kereste'    => 's2-kereste.jpg',
		'ambalaj'    => 's2-ambalaj.jpg',
		'dekorasyon' => 's2-dekorasyon.jpg',
		'hirdavat'   => 's2-hirdavat.jpg',
	);
	$file     = $by_group[ $product['group'] ?? '' ] ?? 'atolye.jpg';

	foreach ( $map as $needle => $candidate ) {
		if ( str_contains( $key, $needle ) ) {
			$file = $candidate;
			break;
		}
	}

	return kocist_image_or_default( (array) ( $product['image'] ?? array() ), $file, (string) ( $product['title'] ?? '' ) );
}

/**
 * Panelden gelen cok paragrafli metni basar: bos satirlar paragraf ayirir.
 */
function kocist_paragraphs( $text, string $class = '' ): string {
	$parts = preg_split( '/\R\s*\R/u', trim( (string) $text ) );
	$out   = '';

	foreach ( $parts as $part ) {
		$part = trim( $part );

		if ( '' !== $part ) {
			$out .= sprintf( '<p%s>%s</p>', $class ? ' class="' . esc_attr( $class ) . '"' : '', nl2br( esc_html( $part ) ) );
		}
	}

	return $out;
}

/**
 * Panelde duzenlenen, icinde {degisken} gecen metin (orn. "{urun} ürün
 * listeleniyor"). Degiskenler sablondan gelir; panelde metnin yalnizca
 * kalanı degisir.
 *
 * @param array<string, string|int> $vars Degisken adi => deger.
 */
function kocist_text( string $page, string $component, string $field, array $vars = array() ): string {
	$text = (string) nwcs_field( $page, $component, $field );
	$map  = array();

	foreach ( $vars as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}

	return trim( strtr( $text, $map ) );
}

/**
 * Havuz urununun ozelligi (ad, kod, aciklama, gorsel...): panel onizlemesinde
 * "Ürüne ait özellik" olarak isaretlenir, tiklaninca Urun Havuzu'nda urun acilir.
 */
function kocist_product_attr( array $product, string $label ): void {
	if ( function_exists( 'nwcs_product_attr' ) && ! empty( $product['id'] ) ) {
		nwcs_product_attr( (int) $product['id'], $label );
	}
}

/**
 * Urunun kendi sayfasi var mi? Bu temada aciklamasiz urunun sayfasi da acik
 * (manifest 'product_page_always'); eski eklentide detay metni sart.
 */
function kocist_product_has_page( array $product ): bool {
	return function_exists( 'nwcs_product_has_page' ) ? nwcs_product_has_page( $product ) : '' !== trim( (string) ( $product['body'] ?? '' ) );
}

/**
 * Urunun teknik detaylari (etiket, deger): eklentinin detay basliklari
 * kaydindan, onun sirasiyla (nwcs_product_pairs). Serbest not ("80 × 120
 * cm") cift degildir: bos dizi, not bilgi satirinda oldugu gibi kalir.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function kocist_product_pairs( array $product ): array {
	return function_exists( 'nwcs_product_pairs' ) ? nwcs_product_pairs( $product ) : kocist_spec_pairs( (string) ( $product['spec'] ?? '' ), 1 );
}

/**
 * Havuzdaki fiyat metninden arama motoruna bildirilecek tutar. Yalnizca
 * duz tutar ayrilir: "450 TL", "450 tl", "3.500 ₺", "24,50 $", "2.80 $";
 * sonunda KDV notu olabilir: "449 ₺ + KDV", "449 TL (KDV hariç)".
 * "1.250 TL'den başlayan" gibi aralik/baslangic ya da birden cok tutar
 * iceren metinde 0 doner; o urunde JSON-LD "offers" yazilmaz.
 */
function kocist_price_number( string $price ): float {
	if ( ! preg_match( '/^\s*([0-9][0-9.,]*)\s*(?:TL|₺|\$)\s*' . KOCIST_PRICE_VAT_NOTE . '?\s*$/iu', $price, $match ) ) {
		return 0.0;
	}

	$number = $match[1];

	if ( preg_match( '/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $number ) ) {
		// Binlik noktali: "3.500", "48.500,00".
		$number = str_replace( array( '.', ',' ), array( '', '.' ), $number );
	} elseif ( preg_match( '/^\d+(?:,\d{1,2})?$/', $number ) ) {
		$number = str_replace( ',', '.', $number );
	} elseif ( ! preg_match( '/^\d+\.\d{1,2}$/', $number ) ) {
		return 0.0;
	}

	return (float) $number;
}

/**
 * Fiyat metnindeki KDV notu: "+ KDV", "+KDV", "KDV hariç", "(KDV hariç)".
 */
const KOCIST_PRICE_VAT_NOTE = '(?:\+\s*KDV|\(?\s*KDV\s+hari[çc]\s*\)?)';

/**
 * Fiyat KDV haric mi: metin KDV notuyla bitiyorsa false (KDV dahil degil),
 * notu yoksa null (bilinmiyor; JSON-LD'ye KDV bilgisi yazilmaz).
 */
function kocist_price_vat_included( string $price ): ?bool {
	return preg_match( '/' . KOCIST_PRICE_VAT_NOTE . '\s*$/iu', $price ) ? false : null;
}

/**
 * Fiyat metninin para birimi (ISO 4217): "$" iceren USD, digerleri TRY.
 */
function kocist_price_currency( string $price ): string {
	return str_contains( $price, '$' ) ? 'USD' : 'TRY';
}

/**
 * Urun sayfasindaki "Teknik Detaylar" satirlari: detaylar, "Lojistik ve
 * Teslimat" satiri haric; detayi olmayan urunde serbest olcu notu "Ölçü"
 * satiri olur. Kural eklentide WOOD KOCIST ile ortak (nwcs_product_table_specs).
 *
 * @return array<int, array{0:string, 1:string}>
 */
function kocist_product_table_specs( array $product ): array {
	if ( function_exists( 'nwcs_product_table_specs' ) ) {
		return nwcs_product_table_specs( $product );
	}

	$pairs = kocist_product_pairs( $product );
	$spec  = trim( (string) ( $product['spec'] ?? '' ) );

	return $pairs ? $pairs : ( '' !== $spec ? array( array( 'Ölçü', $spec ) ) : array() );
}

/**
 * Havuzdaki olcu/ozellik metnini ("Boyut Sinifi: Mega Boy; Ahsap Cinsi: Cam")
 * etiket / deger ciftlerine ayirir. En az $min "Etiket: deger" parcasi yoksa
 * (ya da bir parca etiketsizse) bos dizi: kisa metin ("80 x 120 cm") bilgi
 * sutununda oldugu gibi kalir.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function kocist_spec_pairs( string $spec, int $min = 2 ): array {
	$pairs = array();

	foreach ( preg_split( '/\s*[;\n]\s*/u', trim( $spec ) ) ?: array() as $part ) {
		if ( ! preg_match( '/^([^:]{1,40}):\s*(.+)$/u', trim( $part ), $match ) ) {
			return array();
		}

		// "0: Gizli Pence Aski" gibi aktarim artigi: etiketi sayi olan parca ozellik degil.
		if ( preg_match( '/^\d+$/', trim( $match[1] ) ) ) {
			continue;
		}

		// "Ithal Cam ( Firinlanmis )" -> "Ithal Cam (Firinlanmis)": parantez ici bosluklar toplanir.
		$pairs[] = array( trim( $match[1] ), (string) preg_replace( array( '/\(\s+/u', '/\s+\)/u' ), array( '(', ')' ), trim( $match[2] ) ) );
	}

	return count( $pairs ) >= max( 1, $min ) ? $pairs : array();
}

/**
 * Urune ozel tablolar: Urun Havuzu'nda urun formundaki "Ürün Tabloları"
 * bolumunden girilir (yalnizca Kocist'te gosterilen urunlerde acilir) ve
 * urunle birlikte gelir ($product['tables']). Eklenti hucreleri temizleyip
 * bos satir/sutunlari atar; burada yalnizca satiri olmayan tablo atlanir ve
 * sutun sayisi en genis satira gore esitlenir.
 *
 * @return array<int, array{title:string, head:string[], rows:array<int, string[]>, cols:int}>
 */
function kocist_product_tables( array $product ): array {
	$tables = array();

	foreach ( (array) ( $product['tables'] ?? array() ) as $table ) {
		$head = array_map( 'strval', (array) ( $table['head'] ?? array() ) );
		$body = array_values( array_filter( (array) ( $table['rows'] ?? array() ), 'is_array' ) );

		if ( ! $body ) {
			continue;
		}

		if ( '' === implode( '', $head ) ) {
			$head = array();
		}

		$cols = max( array_merge( array( count( $head ), 1 ), array_map( 'count', $body ) ) );

		$tables[] = array(
			'title' => trim( (string) ( $table['title'] ?? '' ) ),
			'head'  => $head ? array_pad( $head, $cols, '' ) : array(),
			'rows'  => array_map( static fn( array $cells ): array => array_pad( array_map( 'strval', $cells ), $cols, '' ), $body ),
			'cols'  => $cols,
		);
	}

	return $tables;
}

/**
 * Tek urun tablosu (kocist_product_tables() ogesi). Genis tablo kartin
 * icinde yatay kayar; sayfa tasmaz. Panel onizlemesinde tiklaninca urun
 * Urun Havuzu'nda acilir; tablolar orada duzenlenir.
 */
function kocist_render_product_table( array $table, array $product ): void {
	$label = '' !== $table['title'] ? $table['title'] : 'Ürün tablosu';
	?>
	<div class="k-ptable" style="--k-cols: <?php echo (int) $table['cols']; ?>" <?php kocist_product_attr( $product, 'Ürün tablosu' ); ?>>
		<?php if ( '' !== $table['title'] ) : ?>
			<h3 class="k-ptable__title"><?php echo esc_html( $table['title'] ); ?></h3>
		<?php endif; ?>

		<div class="k-ptable__scroll" role="region" aria-label="<?php echo esc_attr( $label ); ?>" tabindex="0">
			<table class="k-ptable__table">
				<?php if ( $table['head'] ) : ?>
					<thead>
						<tr>
							<?php foreach ( $table['head'] as $cell ) : ?>
								<th scope="col"><?php echo esc_html( $cell ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
				<?php endif; ?>
				<tbody>
					<?php foreach ( $table['rows'] as $cells ) : ?>
						<tr>
							<?php foreach ( $cells as $cell ) : ?>
								<td><?php echo esc_html( $cell ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

/**
 * Havuz gorselinin buyuk hali. Havuz urunleri 768 px'lik (medium_large)
 * kopyayla gelir; detay sayfasinda buyuk sahnede bulaniklasmasin diye
 * 'large' kopyasi, srcset ve buyutme icin tam boy adresi eklenir.
 * Gorsel havuz sitesinin medyasindadir; bulunamazsa gelen deger aynen doner.
 *
 * @return array{id:int, url:string, alt:string, full:string, thumb:string, srcset:string}
 */
function kocist_pool_image_large( array $image, string $size = 'large' ): array {
	$image['full']   = (string) ( $image['url'] ?? '' );
	$image['thumb']  = (string) ( $image['url'] ?? '' );
	$image['srcset'] = '';
	$id              = (int) ( $image['id'] ?? 0 );

	if ( $id <= 0 || ! function_exists( 'nwcs_pool_blog_id' ) || kocist_is_placeholder_image( $image ) ) {
		return $image;
	}

	$switched = (int) nwcs_pool_blog_id() !== get_current_blog_id();

	if ( $switched ) {
		switch_to_blog( (int) nwcs_pool_blog_id() );
	}

	$large = wp_get_attachment_image_src( $id, $size );
	$full  = wp_get_attachment_image_src( $id, 'full' );

	if ( $large ) {
		$image['url']    = $large[0];
		$image['srcset'] = (string) wp_get_attachment_image_srcset( $id, $size );
	}

	if ( $full ) {
		$image['full'] = $full[0];
	}

	// Kucuk resim satiri 64px: 768px'lik kopya yerine kucuk boyut (hizli yuklenir).
	$small = wp_get_attachment_image_src( $id, 'thumbnail' );

	if ( $small ) {
		$image['thumb'] = $small[0];
	}

	if ( $switched ) {
		restore_current_blog();
	}

	return $image;
}

/**
 * Urun galerisindeki "buyut" dugmesi: gorselin tamamini kaplar, tiklaninca
 * assets/js/lightbox.js gorseli tam boyutta acar. Gorsel yoksa (yer tutucu)
 * ya da panel onizlemesindeyse cizilmez: onizlemede gorsele tiklamak alani
 * duzenlemeye acar.
 */
function kocist_zoom_button( array $image ): void {
	if ( empty( $image['url'] ) || kocist_is_placeholder_image( $image ) ) {
		return;
	}

	if ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) {
		return;
	}
	?>
	<button type="button" class="k-zoom" data-k-zoom data-full="<?php echo esc_url( ! empty( $image['full'] ) ? $image['full'] : $image['url'] ); ?>" data-alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>">
		<span class="screen-reader-text">Görseli büyüt</span>
		<span class="k-zoom__badge" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
				<circle cx="8.5" cy="8.5" r="5.5" />
				<path d="m12.6 12.6 4.4 4.4M8.5 6v5M6 8.5h5" />
			</svg>
		</span>
	</button>
	<?php
}

/**
 * Blog kategorisinin adi: onizlemede kategorinin duzenleme ekrani.
 */
function kocist_term_attr( $term ): void {
	if ( $term instanceof WP_Term && function_exists( 'nwcs_source_attr' ) && function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) {
		nwcs_source_attr( 'admin', admin_url( 'term.php?taxonomy=' . $term->taxonomy . '&tag_ID=' . $term->term_id ), 'Blog kategorisi' );
	}
}

/* ======================================================================
 * Blog yazilari panelden
 *
 * Ornek yazilarin panelde gizli bir sayfasi var ('yazi-<slug>', manifest).
 * Panelde degistirilip kaydedilen alan sitede WordPress yazisinin yerine
 * gecer: yazi sayfasi, blog listesi, ana sayfa kartlari, ilgili yazilar ve
 * arama bilgisi. Degistirilmeyen alan icin WordPress'teki yazi aynen
 * kullanilir; yazi Yazilar ekranindan duzenlenmeye devam edebilir.
 * ====================================================================== */

/**
 * Temanin alan manifesti (eklenti kapaliyken de okunur).
 */
function kocist_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest = (array) include get_theme_file_path( 'content-manifest.php' );
	}

	return $manifest;
}

/**
 * Yazinin paneldeki gizli sayfasi; yoksa bos (sonradan yazilan yazilar).
 */
function kocist_post_page_key( $post ): string {
	$post = get_post( $post );

	if ( ! $post || 'post' !== $post->post_type || '' === $post->post_name ) {
		return '';
	}

	$key = 'yazi-' . $post->post_name;

	return isset( kocist_manifest()['pages'][ $key ] ) ? $key : '';
}

/**
 * Panelde degistirilmis deger; alan varsayilanindaysa (yazinin ilk metni) null.
 *
 * @return string|int|null
 */
function kocist_post_override( $post, string $field ) {
	$key = kocist_post_page_key( $post );

	if ( '' === $key ) {
		return null;
	}

	$value = nwcs_field( $key, 'post', $field );

	if ( 'image' === $field ) {
		return (int) $value ? (int) $value : null;
	}

	$clean   = static fn( $text ): string => trim( str_replace( "\r\n", "\n", (string) $text ) );
	$value   = $clean( $value );
	$default = $clean( kocist_manifest()['pages'][ $key ]['components']['post']['fields'][ $field ]['default'] ?? '' );

	return '' === $value || $value === $default ? null : $value;
}

/**
 * Onizlemede tiklaninca yazinin paneldeki alanini acar.
 */
function kocist_post_edit_attr( $post, string $field ): void {
	$key = kocist_post_page_key( $post );

	if ( '' !== $key ) {
		nwcs_edit_attr( $key, 'post', $field );
	}
}

/*
 * SEO: eklenti yazi bilgisini 'wp' aksiyonunda (10) hesaplayip sakliyor.
 * Hemen once, gizli sayfanin "Arama ve Paylasim" alanlari (yoksa paneldeki
 * baslik, ozet ve kapak) gecerliyken hesaplatilir.
 */
add_action( 'wp', 'kocist_post_seo', 9 );
function kocist_post_seo(): void {
	if ( ! is_singular( 'post' ) || ! function_exists( 'nwcs_seo_context' ) ) {
		return;
	}

	$post = get_queried_object();
	$key  = kocist_post_page_key( $post );

	if ( '' === $key ) {
		return;
	}

	$description = trim( (string) nwcs_field( $key, 'seo', 'description' ) );
	$description = '' !== $description ? $description : (string) kocist_post_override( $post, 'excerpt' );
	$original    = $post->post_excerpt;

	if ( '' !== $description ) {
		$post->post_excerpt = $description;
	}

	$GLOBALS['kocist_post_seo_key'] = $key;
	nwcs_seo_context();
	unset( $GLOBALS['kocist_post_seo_key'] );

	$post->post_excerpt = $original;
}

/**
 * Arama bilgisi hesaplanirken gecerli olan SEO alani; diger zamanlarda bos.
 */
function kocist_post_seo_field( $post, string $field ): string {
	$key = $GLOBALS['kocist_post_seo_key'] ?? '';

	return '' !== $key && kocist_post_page_key( $post ) === $key ? trim( (string) nwcs_field( $key, 'seo', $field ) ) : '';
}

add_filter( 'the_title', 'kocist_post_title', 10, 2 );
function kocist_post_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! $post_id ) {
		return $title;
	}

	$seo = kocist_post_seo_field( $post_id, 'title' );

	return '' !== $seo ? $seo : ( kocist_post_override( $post_id, 'title' ) ?? $title );
}

add_filter( 'get_the_excerpt', 'kocist_post_excerpt', 10, 2 );
function kocist_post_excerpt( $excerpt, $post = null ) {
	return is_admin() ? $excerpt : ( kocist_post_override( $post, 'excerpt' ) ?? $excerpt );
}

// wpautop'tan sonra: panel metni kendi paragraflariyla gelir.
add_filter( 'the_content', 'kocist_post_content', 99 );
function kocist_post_content( $content ) {
	if ( is_admin() || ! in_the_loop() ) {
		return $content;
	}

	$body = kocist_post_override( get_the_ID(), 'body' );

	return null === $body ? $content : kocist_blog_body_html( $body );
}

add_filter( 'post_thumbnail_id', 'kocist_post_thumbnail', 10, 2 );
function kocist_post_thumbnail( $thumbnail_id, $post = null ) {
	if ( is_admin() ) {
		return $thumbnail_id;
	}

	$seo = (int) kocist_post_seo_field( $post, 'image' );

	return $seo ? $seo : ( kocist_post_override( $post, 'image' ) ?? $thumbnail_id );
}

/**
 * Ana sayfa seritleri icin sinirli urun listesi.
 *
 * 'latest' en son havuza eklenenler (kimlik buyukten kucuge), 'featured'
 * panelde bu site icin belirlenen siranin ilk N urunu. Renk grubu tek kart
 * (kocist_catalog_cards).
 */
function kocist_home_products( string $which, int $count ): array {
	$products = function_exists( 'kocist_catalog_products' ) ? kocist_catalog_cards( kocist_catalog_products() ) : array();

	if ( 'latest' === $which ) {
		usort( $products, static fn( array $a, array $b ): int => (int) $b['id'] <=> (int) $a['id'] );
	}

	return array_slice( $products, 0, max( 1, $count ) );
}

/**
 * Urunun ozellik metninden ilk satir (kart alti kisa bilgi): "Ölçü: 5×10 cm; ..." -> "5×10 cm".
 */
function kocist_product_first_spec( array $product ): string {
	$first = trim( (string) strtok( (string) ( $product['spec'] ?? '' ), ";\n" ) );

	if ( '' === $first ) {
		return '';
	}

	$parts = explode( ':', $first, 2 );

	return trim( $parts[1] ?? $parts[0] );
}

/**
 * Gorsel alani icin img etiketi; deger yoksa isaretli yer tutucu.
 *
 * Varsayilan tembel yukleme. Sayfa acilir acilmaz gorunen gorselde (urun
 * fotografi, listenin ilk karti) $priority true: hemen ve oncelikli iner,
 * en buyuk oge (LCP) gecikmez. Gorselde srcset varsa $sizes ile yazilir:
 * telefon kucuk kopyayi indirir.
 */
function kocist_image_tag( array $image, string $class = '', string $placeholder = 'Örnek görsel', bool $priority = false, string $sizes = '' ): string {
	if ( ! empty( $image['url'] ) && ! kocist_is_placeholder_image( $image ) ) {
		$srcset = '' !== $sizes && ! empty( $image['srcset'] )
			? sprintf( ' srcset="%s" sizes="%s"', esc_attr( $image['srcset'] ), esc_attr( $sizes ) )
			: '';

		return sprintf(
			'<img src="%1$s"%2$s alt="%3$s" class="%4$s"%5$s %6$s decoding="async" />',
			esc_url( $image['url'] ),
			$srcset,
			esc_attr( $image['alt'] ),
			esc_attr( $class ),
			kocist_image_size_attr( $image ),
			$priority ? 'fetchpriority="high"' : 'loading="lazy"'
		);
	}

	return kocist_placeholder( $class, $placeholder );
}

/**
 * Gorseli henuz gelmemis alan: ahsap kesitini andiran yillik halkalar,
 * ortada kamera rozeti ve "Gorsel yakinda" notu.
 *
 * Halkalarin merkezi her alanda biraz kayar; yan yana dizilen kartlar
 * birbirinin kopyasi gibi durmasin. Renkler bulundugu yere gore CSS'ten
 * gelir (acik kartlarda acik, koyu kartlarda koyu zemin).
 */
function kocist_placeholder( string $class = '', string $label = '' ): string {
	static $count = 0;

	$centers = array( array( 12, 108 ), array( 96, -14 ), array( 30, -22 ), array( 104, 92 ) );
	list( $cx, $cy ) = $centers[ $count++ % count( $centers ) ];

	$rings = '';

	// Duz daire yerine hafif dalgali halkalar: gercek kesitteki gibi.
	for ( $r = 9; $r <= 150; $r += 7 + ( $r % 5 ) ) {
		$points = array();

		for ( $a = 0; $a < 360; $a += 12 ) {
			$rad      = deg2rad( $a );
			$wobble   = 1 + 0.035 * sin( 3 * $rad + $r ) + 0.02 * sin( 7 * $rad );
			$points[] = round( $cx + cos( $rad ) * $r * 1.25 * $wobble, 1 ) . ',' . round( $cy + sin( $rad ) * $r * $wobble, 1 );
		}

		$rings .= '<polygon points="' . implode( ' ', $points ) . '" />';
	}

	$soon  = (string) nwcs_field( 'global', 'texts', 'image_soon' );
	$soon  = '' !== trim( $soon ) ? $soon : 'Görsel yakında';
	$label = '' !== trim( $label ) && ! kocist_is_placeholder_image( array( 'alt' => $label ) ) ? $soon . ': ' . $label : $soon;

	return sprintf(
		'<div class="k-placeholder %1$s" role="img" aria-label="%2$s">'
			. '<svg class="k-placeholder__rings" viewBox="0 0 120 90" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">%3$s</svg>'
			. '<span class="k-placeholder__badge" aria-hidden="true">'
				. '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
					. '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2.2l1.5-2.2h5.6L16.3 7h2.2A1.5 1.5 0 0 1 20 8.5v9A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5z" />'
					. '<circle cx="12" cy="12.8" r="3.3" />'
				. '</svg>'
			. '</span>'
			. '<span class="k-placeholder__text" aria-hidden="true">%4$s</span>'
		. '</div>',
		esc_attr( $class ),
		esc_attr( $label ),
		$rings,
		esc_html( $soon )
	);
}

/**
 * SEO ve GEO: gorseller tema icinde oldugundan, gorseli olmayan sayfalar
 * paylasilinca ve arama sonucunda hero fotografi gorunur.
 */
add_filter(
	'nwcs_seo_default_image',
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/atolye.jpg', 'Ahşap atölyesinde el aletleri ve talaş' ) : 0
);
add_filter(
	'nwcs_seo_default_logo',
	static fn() => function_exists( 'nwcs_seo_theme_file_image' ) ? nwcs_seo_theme_file_image( 'assets/img/logo.png', 'Koçist Orman Ürünleri' ) : 0
);

/**
 * Calisma saatleri (arama verisi). Sitede gorunen metin ust seritteki ve
 * Iletisim sayfasindaki "Calisma Saatleri": Hafta ici-Cumartesi 08:00 - 19:00,
 * Pazar 10:00 - 19:00. Saat degisirse ikisi birlikte guncellenir.
 */
const KOCIST_OPENING_HOURS = array(
	array( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ), '08:00', '19:00' ),
	array( array( 'Sunday' ), '10:00', '19:00' ),
);

add_filter( 'nwcs_seo_graph', 'kocist_seo_opening_hours' );
function kocist_seo_opening_hours( array $graph ): array {
	foreach ( $graph as $index => $node ) {
		if ( str_ends_with( (string) ( $node['@id'] ?? '' ), '#organization' ) ) {
			$graph[ $index ]['openingHoursSpecification'] = array_map(
				static fn( array $row ): array => array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $row[0],
					'opens'     => $row[1],
					'closes'    => $row[2],
				),
				KOCIST_OPENING_HOURS
			);
		}
	}

	return $graph;
}

/*
 * Fiyatli urunlerde satici girisi alanlari (Search Console "Satici girisleri").
 * Degerler Rasit Bey'in 27 Ekim'de bildirdigi kosullar (eklentiye elle
 * eklenmisti, buraya tasindi). DIKKAT: sitedeki sayfalarla henuz ayni degil;
 * Odeme ve Teslimat "Istanbul ve Trakya'ya, sure teklifte", Iptal-Iade
 * "her talep ayri degerlendirilir" diyor. Firma dogru bilgiyi verince sayfa
 * ile bu alanlar birlikte esitlenmeli.
 */
add_filter(
	'nwcs_seo_merchant_offer_fields',
	static fn(): array => array(
		'itemCondition'           => 'https://schema.org/NewCondition',
		'availability'            => 'https://schema.org/InStock',
		'shippingDetails'         => array(
			'@type'               => 'OfferShippingDetails',
			'shippingDestination' => array(
				'@type'          => 'DefinedRegion',
				'addressCountry' => 'TR',
			),
			'deliveryTime'        => array(
				'@type'        => 'ShippingDeliveryTime',
				'handlingTime' => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => 0,
					'maxValue' => 1,
					'unitCode' => 'DAY',
				),
				'transitTime'  => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => 1,
					'maxValue' => 2,
					'unitCode' => 'DAY',
				),
			),
		),
		'hasMerchantReturnPolicy' => array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => 'TR',
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
			'description'          => 'Ürünler özel olarak imal edilir; müşteri ürünü renk ve görünüm olarak görüp onayladıktan sonra sevk edilir.',
		),
	)
);

/**
 * Menu ve alt bilgi satirlarindan /sss/ baglantisini, sayfa henuz acilmamissa
 * (yayinda degilse) cikarir. Sayfa yonetici panele ilk girdiginde acilir;
 * o zamana kadar ziyaretci 404'e gitmesin, onbellege 404 baglantisi girmesin.
 * Panel onizlemesinde satir kalir ki duzenlenebilsin. Anahtarlar korunur:
 * panel isaretleri satir sirasini bunlardan okur.
 */
function kocist_hide_missing_sss( array $rows ): array {
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
