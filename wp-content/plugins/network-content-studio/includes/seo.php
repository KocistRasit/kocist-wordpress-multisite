<?php
/**
 * SEO ve GEO: sitenin arama motorlarina ve yapay zeka aramalarina verdigi
 * bilgi.
 *
 * Temalar bu konuda hicbir sey yapmaz; eklenti manifestli her sitede sunlari
 * uretir:
 *   - belge basligi, meta aciklama, paylasim onizlemesi (Open Graph, X)
 *   - yapilandirilmis veri (JSON-LD): firma, web sitesi, sayfa, konum yolu,
 *     urun ve blog yazisi
 *   - /llms.txt: yapay zeka motorlari icin sitenin duz metin ozeti
 *   - her sayfada tek canonical adres
 *   - arsiv sayfalarinda noindex; site haritasindan kullanici listesi ve
 *     kategori/etiket arsivleri cikarilir
 *
 * Manifestte olmayan sayfalar (ornegin temanin tek sablonla cizdigi urun alt
 * sayfalari) icin tema 'nwcs_seo_extra_pages' suzgeciyle bilgi verir; bkz.
 * nwcs_seo_extra_pages().
 *
 * Her deger once panelde girilen ozel degerden, yoksa sayfanin kendi
 * iceriginden gelir. Site sahibi hicbir sey girmese de her sayfa baslik,
 * aciklama ve gorselle yayina cikar.
 */

defined( 'ABSPATH' ) || exit;

/* ====================================================================== *
 * Yardimcilar
 * ====================================================================== */

/**
 * Metni tek satira indirir; istenirse kelime sinirinda kisaltir.
 */
function nwcs_seo_clean( $text, int $limit = 0 ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );

	if ( $limit > 0 && mb_strlen( $text ) > $limit ) {
		$cut  = mb_substr( $text, 0, $limit - 1 );
		$cut  = preg_replace( '/\s+\S*$/u', '', $cut );
		$text = rtrim( (string) $cut, " ,.;:–-" ) . '…';
	}

	return $text;
}

/**
 * "bilesen.alan" yolundan aktif sitenin degerini okur.
 */
function nwcs_seo_path_value( string $page_key, string $path ) {
	$parts = explode( '.', $path, 2 );

	if ( 2 !== count( $parts ) ) {
		return '';
	}

	return nwcs_field( $page_key, $parts[0], $parts[1] );
}

/**
 * Sayfa alaninin baslik ve aciklamada kullanilan metni.
 *
 * Tema bir alani sayfada {degisken} doldurarak gosteriyorsa ("{sayi} ürün"),
 * ayni dolu metni suzgecle verir; vermezse aciklamada ham "{sayi}" gorunur:
 *
 *   add_filter( 'nwcs_seo_field_text', function ( string $text, string $page, string $component, string $field ): string {
 *       return 'kategoriler' === $page && 'lead' === $field ? strtr( $text, array( '{sayi}' => '12' ) ) : $text;
 *   }, 10, 4 );
 *
 * Panelde (ag yonetimi) temanin suzgeci yuklu degildir; orada ham metin gorunur.
 */
function nwcs_seo_field_text( string $page_key, string $component, string $field ): string {
	$value = nwcs_field( $page_key, $component, $field );
	$value = is_scalar( $value ) ? (string) $value : '';

	return nwcs_seo_clean( (string) apply_filters( 'nwcs_seo_field_text', $value, $page_key, $component, $field ) );
}

/**
 * Deger bir gorsel kimligi ya da gorselli tekrarli satirlarsa ilk gorsel.
 */
function nwcs_seo_first_image( $value ): int {
	if ( is_array( $value ) ) {
		foreach ( $value as $row ) {
			foreach ( (array) $row as $cell ) {
				if ( is_numeric( $cell ) && (int) $cell > 0 && wp_attachment_is_image( (int) $cell ) ) {
					return (int) $cell;
				}
			}
		}

		return 0;
	}

	return is_numeric( $value ) ? (int) $value : 0;
}

/**
 * Gorsel kimliginden paylasim icin boyutlu bilgi.
 */
function nwcs_seo_image( int $attachment_id ): array {
	if ( $attachment_id <= 0 ) {
		return array();
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'large' );

	if ( ! $src ) {
		return array();
	}

	return array(
		'id'     => $attachment_id,
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Manifestteki, kendi adresi olan sayfalar (ortak ve gizli sayfalar haric).
 *
 * Temanin 'seo' => false isaretledigi sayfa da haric: panelde duzenlenen ama
 * kendisi yayinda olmayan bir sablon (Kocist'in ornek urun sayfasi /urun/).
 * Meta ve JSON-LD, llms.txt, site haritasi ve SEO puani onu saymaz; adresi
 * noindex olur (bkz. nwcs_seo_excluded_page_ids).
 */
function nwcs_seo_pages( ?array $manifest = null ): array {
	$manifest = $manifest ?? nwcs_manifest();
	$pages    = array();

	foreach ( nwcs_visible_pages( $manifest ) as $key => $page ) {
		if ( 'global' !== $key && isset( $page['path'] ) && false !== ( $page['seo'] ?? true ) ) {
			$pages[ $key ] = $page;
		}
	}

	return $pages;
}

/**
 * 'seo' => false isaretli manifest sayfalarinin WordPress sayfa kimlikleri
 * (yolu bir WordPress sayfasina denk gelenler).
 *
 * @return int[]
 */
function nwcs_seo_excluded_page_ids(): array {
	$ids = array();

	foreach ( (array) ( nwcs_manifest()['pages'] ?? array() ) as $page ) {
		if ( false !== ( $page['seo'] ?? true ) || empty( $page['path'] ) ) {
			continue;
		}

		$found = get_page_by_path( trim( (string) $page['path'], '/' ), OBJECT, 'page' );

		if ( $found ) {
			$ids[] = (int) $found->ID;
		}
	}

	return $ids;
}

/**
 * Bu istek 'seo' => false isaretli bir sayfa mi?
 */
function nwcs_seo_is_excluded_request(): bool {
	return is_page() && in_array( (int) get_queried_object_id(), nwcs_seo_excluded_page_ids(), true );
}

/**
 * Site adi: firma bilgisindeki kisa ad, yoksa WordPress site basligi.
 */
function nwcs_seo_site_name(): string {
	$name = nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'name' ) );

	return '' !== $name ? $name : nwcs_seo_clean( get_bloginfo( 'name' ) );
}

/* ====================================================================== *
 * Sayfanin otomatik degerleri
 * ====================================================================== */

/**
 * Bir sayfanin icerikten turetilen adi, aciklamasi ve gorseli.
 *
 * Tema manifestte 'seo_source' verdiyse oradan; vermediyse bilesenlerde
 * yaygin alan adlari aranir (baslik: title/name, aciklama: lead/short/...).
 *
 * @return array{name:string, description:string, image:int}
 */
function nwcs_seo_page_auto( string $page_key, ?array $manifest = null ): array {
	$manifest   = $manifest ?? nwcs_manifest();
	$page       = $manifest['pages'][ $page_key ] ?? array();
	$source     = (array) ( $page['seo_source'] ?? array() );
	$components = array_diff_key( (array) ( $page['components'] ?? array() ), array( NWCS_SEO_COMPONENT => true ) );

	// Belirtilen alan adlarindan, bilesen sirasiyla dolu metinler.
	$texts = static function ( array $names ) use ( $components, $page_key ): array {
		$found = array();

		foreach ( $names as $name ) {
			foreach ( $components as $component_key => $component ) {
				$type = $component['fields'][ $name ]['type'] ?? '';

				if ( 'text' === $type || 'textarea' === $type ) {
					$value = nwcs_seo_field_text( $page_key, $component_key, $name );

					if ( '' !== $value && ! in_array( $value, $found, true ) ) {
						$found[] = $value;
					}
				}
			}
		}

		return $found;
	};

	$find_text = static fn( array $names ): string => $texts( $names )[0] ?? '';

	$find_image = static function () use ( $components, $page_key ): int {
		foreach ( $components as $component_key => $component ) {
			foreach ( $component['fields'] as $name => $definition ) {
				$type = $definition['type'] ?? '';

				if ( 'image' !== $type && 'repeater' !== $type ) {
					continue;
				}

				$id = nwcs_seo_first_image( nwcs_field( $page_key, $component_key, $name ) );

				if ( $id ) {
					return $id;
				}
			}
		}

		return 0;
	};

	$name = isset( $source['title'] )
		? nwcs_seo_clean( nwcs_seo_path_value( $page_key, $source['title'] ) )
		: $find_text( array( 'title', 'name' ) );

	$candidates = $texts( array( 'lead', 'short', 'intro', 'text', 'tagline', 'note', 'body', 'p1' ) );

	if ( isset( $source['description'] ) ) {
		array_unshift( $candidates, nwcs_seo_clean( nwcs_seo_path_value( $page_key, $source['description'] ) ) );
	}

	$description = nwcs_seo_join_description( $candidates );

	$image = isset( $source['image'] )
		? nwcs_seo_first_image( nwcs_seo_path_value( $page_key, $source['image'] ) )
		: $find_image();

	if ( '' === $name ) {
		$name = nwcs_seo_clean( preg_replace( '/^[^:]+:\s*/u', '', (string) ( $page['label'] ?? $page_key ) ) );
	}

	return array(
		'name'        => $name,
		'description' => nwcs_seo_clean( $description, 160 ),
		'image'       => $image,
	);
}

/**
 * Aciklama: ilk metin; arama sonucunu doldurmayacak kadar kisaysa (100
 * karakterden az) sayfanin sonraki metinleri eklenir. Sonuc 160 karakterde
 * kelime sinirindan kesilir.
 */
function nwcs_seo_join_description( array $candidates ): string {
	$description = '';

	foreach ( array_values( array_filter( array_map( 'nwcs_seo_clean', $candidates ) ) ) as $text ) {
		if ( '' === $description ) {
			$description = $text;
		} elseif ( mb_strlen( $description ) < 100 && false === mb_strpos( $description, $text ) ) {
			$description = rtrim( $description, ' .' ) . '. ' . $text;
		}

		if ( mb_strlen( $description ) >= 100 ) {
			break;
		}
	}

	return nwcs_seo_clean( $description, 160 );
}

/**
 * Baslik: "sayfa – site"; 60 karakteri asarsa site adi eklenmez (arama
 * sonucunda zaten kesilir, sayfa adi one ciksin).
 */
function nwcs_seo_title_with_site( string $name, string $site, bool $site_first = false ): string {
	if ( '' === $name || $name === $site ) {
		return $site;
	}

	$title = $site_first ? $site . ' – ' . $name : $name . ' – ' . $site;

	return mb_strlen( $title ) <= 60 ? $title : $name;
}

/**
 * Sayfanin arama sonucunda gorunecek hali: ozel deger varsa o, yoksa
 * otomatik. Panel onizlemesi ve on yuz ayni fonksiyonu kullanir.
 *
 * @return array{name:string, title:string, description:string, image:int, custom:array}
 */
function nwcs_seo_page_resolved( string $page_key, ?array $manifest = null ): array {
	$auto   = nwcs_seo_page_auto( $page_key, $manifest );
	$site   = nwcs_seo_site_name();
	$custom = array(
		'title'       => nwcs_seo_clean( nwcs_field( $page_key, NWCS_SEO_COMPONENT, 'title' ) ),
		'description' => nwcs_seo_clean( nwcs_field( $page_key, NWCS_SEO_COMPONENT, 'description' ) ),
		'image'       => (int) nwcs_field( $page_key, NWCS_SEO_COMPONENT, 'image', 0 ),
	);

	// Ana sayfada marka once gelir; ic sayfalarda sayfa adi.
	$auto_title = nwcs_seo_title_with_site( $auto['name'], $site, 'home' === $page_key );

	// Ana sayfanin aciklamasi firmayi anlatmali: sayfadan cikan metin kisaysa
	// firma tanimi kullanilir.
	if ( 'home' === $page_key && mb_strlen( $auto['description'] ) < 100 ) {
		$org_description = nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'description' ), 160 );

		if ( mb_strlen( $org_description ) > mb_strlen( $auto['description'] ) ) {
			$auto['description'] = $org_description;
		}
	}

	return array(
		'name'        => $auto['name'],
		'title'       => '' !== $custom['title'] ? $custom['title'] : $auto_title,
		'description' => '' !== $custom['description'] ? $custom['description'] : $auto['description'],
		'image'       => $custom['image'] ? $custom['image'] : $auto['image'],
		'auto'        => array( 'title' => $auto_title ) + $auto,
		'custom'      => $custom,
	);
}

/* ====================================================================== *
 * Istegin baglami
 * ====================================================================== */

/**
 * Istek adresinin yolu (sorgu ve capa olmadan), sonda egik cizgi yok.
 */
function nwcs_seo_request_path(): string {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
}

/**
 * Adresi verilen yola denk gelen manifest sayfasi.
 */
function nwcs_seo_page_for_path( string $path ): string {
	foreach ( nwcs_seo_pages() as $key => $page ) {
		if ( untrailingslashit( (string) wp_parse_url( home_url( $page['path'] ), PHP_URL_PATH ) ) === $path ) {
			return $key;
		}
	}

	return '';
}

/**
 * Manifestte olmayan ama temanin cizdigi sayfalar. Tema suzgecle liste verir:
 *
 *   add_filter( 'nwcs_seo_extra_pages', function ( array $pages ): array {
 *       $pages[] = array(
 *           'url'         => 'https://…/urunlerimiz/kalas/',
 *           'name'        => 'Kalas',
 *           'title'       => 'Kalas | Çam ve Göknar', // istege bagli: arama basligi, oldugu gibi yazilir (site adi eklenmez); bossa "ad – site"
 *           'description' => '…',
 *           'image'       => 12,            // ek kimligi ya da array( url, width, height, alt )
 *           'type'        => 'Product',     // istege bagli; liste sayfasinda 'CollectionPage'
 *           'items'       => array( array( 'url' => '…', 'name' => '…' ) ), // CollectionPage: sayfadaki ogeler (ItemList)
 *           'properties'  => array( array( 'name' => 'Ağaç', 'value' => 'Çam' ) ),
 *           'sitemap'     => true,          // istege bagli, asagiya bakin
 *           'sku'         => 'W-ADR-FB01',  // istege bagli: urun kodu
 *           'price'       => 10250,         // istege bagli: sayi; varsa Offer uretilir
 *           'currency'    => 'TRY',
 *           'vat_included' => false,        // istege bagli: fiyat KDV haric ("+ KDV"); verilmezse KDV bilgisi yazilmaz
 *           'lastmod'     => '2026-10-26 09:00:00', // istege bagli: son degisiklik (GMT); site haritasina yazilir
 *           'video'       => $product['video'], // istege bagli: nwcs_video_by_id(); Product'a VideoObject eklenir
 *       );
 *       return $pages;
 *   } );
 *
 * Bu sayfalar da baslik, aciklama, JSON-LD (urunse Product) ve llms.txt alir.
 * 'sitemap' => true verilirse site haritasina da girer: WordPress sayfasi
 * olmayan adresler icin (havuz urunleri /urun/<slug>/). Gercek WordPress
 * sayfasi olan ek sayfalarda verilmez; WordPress onu zaten listeler.
 */
function nwcs_seo_extra_pages(): array {
	static $pages = null;

	if ( null === $pages ) {
		$pages = array_values(
			array_filter(
				(array) apply_filters( 'nwcs_seo_extra_pages', array() ),
				static fn( $page ): bool => is_array( $page ) && ! empty( $page['url'] ) && ! empty( $page['name'] )
			)
		);
	}

	return $pages;
}

/**
 * Gorsel: ek kimligi ya da temanin verdigi hazir bilgi (url, width, height, alt).
 */
function nwcs_seo_image_any( $image ): array {
	if ( is_array( $image ) ) {
		return empty( $image['url'] ) ? array() : $image + array( 'id' => 0, 'width' => 0, 'height' => 0, 'alt' => '' );
	}

	return nwcs_seo_image( (int) $image );
}

/**
 * Tema klasorundeki bir gorsel dosyasindan paylasim bilgisi. Gorselleri medya
 * kitapliginda degil tema icinde tutan temalar, varsayilan gorsel ve logo
 * suzgeclerinde kullanir:
 *
 *   add_filter( 'nwcs_seo_default_image', fn() => nwcs_seo_theme_file_image( 'assets/img/hero.jpg', 'Aciklama' ) );
 *   add_filter( 'nwcs_seo_default_logo', fn() => nwcs_seo_theme_file_image( 'assets/img/logo.png', 'Firma' ) );
 */
function nwcs_seo_theme_file_image( string $relative, string $alt = '' ): array {
	$file = get_theme_file_path( $relative );
	$size = is_readable( $file ) ? getimagesize( $file ) : false;

	if ( ! $size ) {
		return array();
	}

	return array(
		'id'     => 0,
		'url'    => get_theme_file_uri( $relative ),
		'width'  => (int) $size[0],
		'height' => (int) $size[1],
		'alt'    => $alt,
	);
}

/**
 * Bu istek icin SEO bilgisi: manifest sayfasi, temanin bildirdigi ek sayfa,
 * blog yazisi ya da WordPress'in kendi sayfalari (blog listesi, arsiv, diger
 * sayfalar). Manifestsiz sitede, beslemede ya da 404'te bos; o zaman WordPress'in
 * varsayilanlari gecerli kalir.
 */
function nwcs_seo_context(): array {
	static $context = null;

	if ( null !== $context ) {
		return $context;
	}

	$context = array();

	// Beslemede sayfa bilgisi kullanilmaz; olmayan yazinin beslemesi (/x/feed/)
	// 404 sayilmaz ve sorgulanan yazi bos gelir. SEO disi sablon sayfasi da bos.
	if ( is_admin() || is_feed() || is_404() || empty( nwcs_manifest()['pages'] ) || nwcs_seo_is_excluded_request() ) {
		return $context;
	}

	$site = nwcs_seo_site_name();
	$base = array(
		'kind'       => 'page',
		'page_key'   => '',
		'post'       => null,
		'schema'     => '',
		'properties' => array(),
	);

	if ( is_singular( 'post' ) ) {
		$post    = get_queried_object();
		$name    = nwcs_seo_clean( get_the_title( $post ) );
		$excerpt = nwcs_seo_post_text( $post );

		$context = array(
			'kind'        => 'post',
			'post'        => $post,
			'name'        => $name,
			'title'       => nwcs_seo_title_with_site( $name, $site ),
			'description' => nwcs_seo_clean( $excerpt, 160 ),
			'image'       => (int) get_post_thumbnail_id( $post ),
			'url'         => (string) get_permalink( $post ),
		) + $base;
	} else {
		$path     = nwcs_seo_request_path();
		$page_key = nwcs_seo_page_for_path( $path );

		if ( '' !== $page_key ) {
			$resolved = nwcs_seo_page_resolved( $page_key );
			$page     = nwcs_manifest()['pages'][ $page_key ];

			$context = array(
				'page_key'    => $page_key,
				'schema'      => (string) ( $page['seo_source']['type'] ?? '' ),
				'name'        => $resolved['name'],
				'title'       => $resolved['title'],
				'description' => $resolved['description'],
				'image'       => $resolved['image'],
				'url'         => home_url( $page['path'] ),
			) + $base;

			// Liste sayfasi: ogelerini tema ayni adresteki ek sayfayla bildirir.
			if ( 'CollectionPage' === $context['schema'] ) {
				$context['items'] = (array) ( nwcs_seo_context_extra( $path, $site, $base )['items'] ?? array() );
			}
		} else {
			$context = nwcs_seo_context_extra( $path, $site, $base ) ?: nwcs_seo_context_wordpress( $site, $base );
		}
	}

	if ( ! $context ) {
		return $context;
	}

	// Aciklama yoksa firma tanimi; sayfa bos gorunmesin.
	if ( '' === $context['description'] ) {
		$context['description'] = nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'description' ), 160 );
	}

	// Gorsel yoksa: site geneli paylasim gorseli, logo, ana sayfanin ilk
	// gorseli, en son temanin bildirdigi gorsel (nwcs_seo_default_image).
	// Paylasilan her sayfa bir gorselle gorunsun.
	if ( ! $context['image'] ) {
		$context['image'] = (int) nwcs_field( NWCS_SEO_SITE_PAGE, 'defaults', 'share_image', 0 )
			?: nwcs_seo_logo_id()
			?: ( isset( nwcs_manifest()['pages']['home'] ) ? nwcs_seo_page_auto( 'home' )['image'] : 0 )
			?: apply_filters( 'nwcs_seo_default_image', 0 );
	}

	$context['image'] = nwcs_seo_image_any( $context['image'] );

	return $context;
}

/**
 * Temanin bildirdigi ek sayfa (bkz. nwcs_seo_extra_pages).
 */
function nwcs_seo_context_extra( string $path, string $site, array $base ): array {
	foreach ( nwcs_seo_extra_pages() as $page ) {
		if ( untrailingslashit( (string) wp_parse_url( $page['url'], PHP_URL_PATH ) ) !== $path ) {
			continue;
		}

		$name  = nwcs_seo_clean( $page['name'] );
		$title = nwcs_seo_clean( (string) ( $page['title'] ?? '' ) );

		return array(
			'schema'       => (string) ( $page['type'] ?? '' ),
			'properties'   => (array) ( $page['properties'] ?? array() ),
			'name'         => $name,
			'title'        => '' !== $title ? $title : nwcs_seo_title_with_site( $name, $site ),
			'description'  => nwcs_seo_clean( (string) ( $page['description'] ?? '' ), 160 ),
			'image'        => $page['image'] ?? 0,
			'url'          => (string) $page['url'],
			'sku'          => nwcs_seo_clean( (string) ( $page['sku'] ?? '' ) ),
			'price'        => is_numeric( $page['price'] ?? null ) && (float) $page['price'] > 0 ? (float) $page['price'] : 0.0,
			'currency'     => (string) ( $page['currency'] ?? 'TRY' ),
			// null: fiyat metni KDV'den soz etmiyor; Offer'a KDV bilgisi yazilmaz.
			'vat_included' => isset( $page['vat_included'] ) ? (bool) $page['vat_included'] : null,
			'video'        => is_array( $page['video'] ?? null ) ? $page['video'] : array(),
			// CollectionPage: sayfada listelenen ogeler (url, name) -> ItemList.
			'items'        => array_values( array_filter( (array) ( $page['items'] ?? array() ), static fn( $item ): bool => is_array( $item ) && ! empty( $item['url'] ) ) ),
		) + $base;
	}

	return array();
}

/**
 * WordPress'in kendi sayfalari: blog listesi, kategori/etiket arsivi ve
 * manifestte olmayan duz sayfalar.
 */
function nwcs_seo_context_wordpress( string $site, array $base ): array {
	$name        = '';
	$description = '';
	$url         = '';
	$image       = 0;

	if ( is_home() && ! is_front_page() && get_option( 'page_for_posts' ) ) {
		$page        = get_post( (int) get_option( 'page_for_posts' ) );
		$name        = nwcs_seo_clean( get_the_title( $page ) );
		$description = $page->post_excerpt ?: $page->post_content;
		$url         = (string) get_permalink( $page );
		$image       = (int) get_post_thumbnail_id( $page );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term        = get_queried_object();
		$name        = nwcs_seo_clean( single_term_title( '', false ) );
		$description = term_description( $term );
		$url         = (string) get_term_link( $term );
	} elseif ( is_singular() ) {
		$post        = get_queried_object();
		$name        = nwcs_seo_clean( get_the_title( $post ) );
		$description = nwcs_seo_post_text( $post );
		$url         = (string) get_permalink( $post );
		$image       = (int) get_post_thumbnail_id( $post );
	}

	if ( '' === $name || '' === $url ) {
		return array();
	}

	return array(
		'name'        => $name,
		'title'       => nwcs_seo_title_with_site( $name, $site ),
		'description' => nwcs_seo_clean( $description, 160 ),
		'image'       => $image,
		'url'         => $url,
	) + $base;
}

/**
 * Firma logosu: SEO firma bilgisindeki, yoksa temanin ust menu logosu.
 */
function nwcs_seo_logo_id(): int {
	$logo = (int) nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'logo', 0 );

	if ( ! $logo && null !== nwcs_field_def( nwcs_manifest(), 'global', 'header', 'logo_image' ) ) {
		$logo = (int) nwcs_field( 'global', 'header', 'logo_image', 0 );
	}

	return $logo;
}

/* ====================================================================== *
 * Belge basligi, meta etiketleri
 * ====================================================================== */

add_filter( 'pre_get_document_title', 'nwcs_seo_document_title', 20 );
function nwcs_seo_document_title( $title ) {
	$context = nwcs_seo_context();

	return $context['title'] ?? $title;
}

/**
 * WordPress canonical'i yalnizca tekil sayfalarda yazar; ana sayfa (yazi
 * listesi), blog listesi ve arsivler canonical'siz kalir. Baglam varsa
 * eklenti her sayfada kendisi yazar; ikisi birden cikmasin.
 */
add_action( 'wp', 'nwcs_seo_take_canonical' );
function nwcs_seo_take_canonical(): void {
	if ( nwcs_seo_context() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}

add_action( 'wp_head', 'nwcs_seo_head', 1 );
function nwcs_seo_head(): void {
	$context = nwcs_seo_context();

	if ( ! $context ) {
		return;
	}

	$is_post = 'post' === $context['kind'];
	$image   = $context['image'];
	$meta    = array();

	if ( '' !== $context['description'] ) {
		$meta[] = array( 'name', 'description', $context['description'] );
	}

	$meta[] = array( 'property', 'og:locale', get_locale() );
	$meta[] = array( 'property', 'og:type', $is_post ? 'article' : 'website' );
	$meta[] = array( 'property', 'og:site_name', nwcs_seo_site_name() );
	$meta[] = array( 'property', 'og:title', $context['title'] );

	if ( '' !== $context['description'] ) {
		$meta[] = array( 'property', 'og:description', $context['description'] );
	}

	$meta[] = array( 'property', 'og:url', $context['url'] );

	if ( $image ) {
		$meta[] = array( 'property', 'og:image', $image['url'] );
		$meta[] = array( 'property', 'og:image:width', (string) $image['width'] );
		$meta[] = array( 'property', 'og:image:height', (string) $image['height'] );

		if ( '' !== $image['alt'] ) {
			$meta[] = array( 'property', 'og:image:alt', $image['alt'] );
		}
	}

	if ( $is_post ) {
		$meta[] = array( 'property', 'article:published_time', get_the_date( 'c', $context['post'] ) );
		$meta[] = array( 'property', 'article:modified_time', get_the_modified_date( 'c', $context['post'] ) );
	}

	$meta[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

	echo "\n<!-- Network Content Studio: SEO ve GEO -->\n";

	// Tek canonical adres (WordPress'inki kaldirildi, bkz. nwcs_seo_take_canonical).
	$canonical = is_paged() ? get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) ) : $context['url'];
	printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );

	foreach ( $meta as $tag ) {
		printf( '<meta %s="%s" content="%s" />' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( nwcs_seo_graph( $context ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
	);
}

/**
 * Yazinin ozeti (arama aciklamasi, llms.txt): elle yazilmis ozet, yoksa metnin
 * basi. Temanin panelden gelen degisiklikleri (baslik, ozet, metin) de
 * uygulansin diye yazi dongude okunuyormus gibi okunur: temalar metin
 * degisikligini yalnizca donguyken uyguluyor.
 */
function nwcs_seo_post_text( WP_Post $post ): string {
	global $wp_query;

	$saved_post = $GLOBALS['post'] ?? null;
	$saved_loop = $wp_query instanceof WP_Query ? $wp_query->in_the_loop : false;

	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- asagida geri alinir.
	setup_postdata( $post );

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->in_the_loop = true;
	}

	$text = has_excerpt( $post )
		? (string) apply_filters( 'get_the_excerpt', $post->post_excerpt, $post )
		: (string) apply_filters( 'get_the_excerpt', '', $post );

	// Ozet bos ve tema bir sey vermediyse: temanin uyguladigi metin.
	if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
		$text = (string) apply_filters( 'the_content', $post->post_content );
	}

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->in_the_loop = $saved_loop;
	}

	$GLOBALS['post'] = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $saved_post instanceof WP_Post ) {
		setup_postdata( $saved_post );
	}

	// wp_trim_excerpt'in "devami" isareti aciklamaya girmesin.
	return trim( str_replace( array( '[&hellip;]', '[…]', '&hellip;' ), '', $text ) );
}

/* ====================================================================== *
 * Yapilandirilmis veri (JSON-LD)
 * ====================================================================== */

/**
 * Firma bilgisi: panelde girilen degerler; bos olanlar yazilmaz.
 */
function nwcs_seo_org_data(): array {
	$keys = array( 'name', 'legal_name', 'description', 'phone', 'mobile', 'email', 'street', 'district', 'city', 'postal_code', 'country', 'latitude', 'longitude', 'parent_name', 'parent_url' );
	$org  = array();

	foreach ( $keys as $key ) {
		$org[ $key ] = nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', $key ) );
	}

	$org['name']       = nwcs_seo_site_name();
	$org['parent_url'] = esc_url_raw( $org['parent_url'] );

	// Cep hatti panelde bos birakildiysa sitenin hat grubundaki cep (Toplu Guncelleme).
	if ( '' === $org['mobile'] && function_exists( 'nwcs_bulk_phone_set' ) ) {
		$digits = (string) ( nwcs_bulk_phone_set( (string) get_option( 'stylesheet' ) )['mobile'] ?? '' );

		if ( 10 === strlen( $digits ) ) {
			$org['mobile'] = sprintf( '+90 %s %s %s %s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6, 2 ), substr( $digits, 8, 2 ) );
		}
	}

	$org['same_as'] = array_values(
		array_filter(
			array_map(
				static fn( $row ): string => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
				nwcs_rows( NWCS_SEO_SITE_PAGE, 'org', 'same_as' )
			)
		)
	);

	return $org;
}

/**
 * Sayfanin @graph dizisi.
 */
function nwcs_seo_graph( array $context ): array {
	$home     = home_url( '/' );
	$org      = nwcs_seo_org_data();
	$org_id   = $home . '#organization';
	$site_id  = $home . '#website';
	$page_id  = $context['url'] . '#webpage';
	$language = str_replace( '_', '-', get_locale() );

	// Adresi ve telefonu olan firma yerel isletmedir; Haritalar eslesmesi icin.
	$has_address = '' !== $org['street'] || '' !== $org['city'];
	$business    = array_filter(
		array(
			'@type'       => $has_address && '' !== $org['phone'] ? 'LocalBusiness' : 'Organization',
			'@id'         => $org_id,
			'name'        => $org['name'],
			'legalName'   => $org['legal_name'],
			'description' => $org['description'],
			'url'         => $home,
			'telephone'   => $org['phone'],
			'email'       => $org['email'],
			'sameAs'      => $org['same_as'],
		)
	);

	// Sabit hat 'telephone'da; cep ve WhatsApp hatti ayri iletisim noktasi.
	if ( '' !== $org['mobile'] && $org['mobile'] !== $org['phone'] ) {
		$business['contactPoint'] = array(
			array(
				'@type'             => 'ContactPoint',
				'telephone'         => $org['mobile'],
				'contactType'       => 'customer service',
				'availableLanguage' => 'Turkish',
			),
		);
	}

	$logo = nwcs_seo_image( nwcs_seo_logo_id() ) ?: nwcs_seo_image_any( apply_filters( 'nwcs_seo_default_logo', 0 ) );

	if ( $logo ) {
		$business['logo'] = array(
			'@type'  => 'ImageObject',
			'url'    => $logo['url'],
			'width'  => $logo['width'],
			'height' => $logo['height'],
		);
	}

	// Yerel isletme gorseli (Google onerir): sitenin paylasim gorseli, yoksa logo.
	$photo = nwcs_seo_image( (int) nwcs_field( NWCS_SEO_SITE_PAGE, 'defaults', 'share_image', 0 ) ) ?: $logo;

	if ( 'LocalBusiness' === $business['@type'] && $photo ) {
		$business['image'] = $photo['url'];
	}

	if ( $has_address ) {
		$business['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $org['street'],
				'addressLocality' => $org['district'],
				'addressRegion'   => $org['city'],
				'postalCode'      => $org['postal_code'],
				'addressCountry'  => $org['country'],
			)
		);
	}

	// Grup iliskisi: kardes siteler sameAs degil (ayni kurum degiller), ortak
	// ust kurumla baglanir. Yapay zeka motorlari siteleri tek grup olarak tanir.
	if ( '' !== $org['parent_name'] ) {
		$business['parentOrganization'] = array_filter(
			array(
				'@type' => 'Organization',
				'name'  => $org['parent_name'],
				'url'   => $org['parent_url'],
			)
		);
	}

	if ( is_numeric( str_replace( ',', '.', $org['latitude'] ) ) && is_numeric( str_replace( ',', '.', $org['longitude'] ) ) ) {
		$business['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) str_replace( ',', '.', $org['latitude'] ),
			'longitude' => (float) str_replace( ',', '.', $org['longitude'] ),
		);
	}

	$graph = array(
		$business,
		array(
			'@type'      => 'WebSite',
			'@id'        => $site_id,
			'url'        => $home,
			'name'       => $org['name'],
			'inLanguage' => $language,
			'publisher'  => array( '@id' => $org_id ),
		),
	);

	$webpage = array_filter(
		array(
			'@type'       => 'WebPage',
			'@id'         => $page_id,
			'url'         => $context['url'],
			'name'        => $context['title'],
			'description' => $context['description'],
			'inLanguage'  => $language,
			'isPartOf'    => array( '@id' => $site_id ),
			'about'       => array( '@id' => $org_id ),
		)
	);

	if ( $context['image'] ) {
		$webpage['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $context['image']['url'] );
	}

	$crumbs = nwcs_seo_breadcrumbs( $context );

	if ( count( $crumbs ) > 1 ) {
		$webpage['breadcrumb'] = array( '@id' => $context['url'] . '#breadcrumb' );

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $context['url'] . '#breadcrumb',
			'itemListElement' => array_map(
				static fn( array $crumb, int $index ): array => array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => $crumb['name'],
					'item'     => $crumb['url'],
				),
				$crumbs,
				array_keys( $crumbs )
			),
		);
	}

	$graph[] = $webpage;

	if ( 'post' === $context['kind'] ) {
		$graph[] = nwcs_seo_article( $context, $org_id, $page_id, $language );
	} elseif ( 'Product' === $context['schema'] && empty( $context['price'] ) ) {
		// Teklifle satilan urun (fiyat yok): Google'in Product kurali fiyat,
		// puan ya da yorum ister; uydurmak yerine hizmet olarak isaretlenir.
		// Butun sitelerde (Search Console "Urun snippet'leri" hatasi).
		$graph[] = nwcs_seo_quote_service( $context, $org_id, $page_id );
	} elseif ( 'Product' === $context['schema'] ) {
		$product = nwcs_seo_product( $context, $org_id, $page_id );
		// Renk grubundaki urun yalnizca kendi rengini tasir. ProductGroup
		// yazilmaz: varyantlarin fiyati burada bilinmez, Google hasVariant'ta
		// her varyant icin offers istiyor (Satici girisleri hatasi).
		$colors = function_exists( 'nwcs_color_seo' ) ? nwcs_color_seo( $context ) : array();

		$graph[] = $colors ? $product + array_intersect_key( $colors['product'], array( 'color' => true ) ) : $product;
	} else {
		// Soru-cevap: sitenin SSS sayfasi (FAQPage) ya da kategori sayfasinin
		// kendi sorulari (Ürün Havuzu -> Kategoriler -> Sık sorulan sorular,
		// includes/category-faq.php). Ikisi ayni sayfada olmaz; urun sayfasinda
		// hic yok. Sayfa dugumu tek kalir: WebPage + FAQPage.
		$faq = 'FAQPage' === $context['schema']
			? nwcs_seo_faq( $context['page_key'] )
			: ( function_exists( 'nwcs_cat_faq_for_context' ) ? nwcs_cat_faq_for_context( $context ) : array() );

		$last       = array_key_last( $graph );
		$collection = 'CollectionPage' === $context['schema'];

		// Liste sayfasi (kategori): WebPage yerine CollectionPage, listesi ItemList.
		// Sorulari da varsa ana oge sorular kalir, liste sayfanin parcasi olur.
		if ( $collection ) {
			$graph[ $last ]['@type'] = 'CollectionPage';

			if ( ! empty( $context['items'] ) ) {
				$graph[ $last ][ $faq ? 'hasPart' : 'mainEntity' ] = nwcs_seo_item_list( $context['items'] );
			}
		}

		if ( $faq ) {
			// Sayfanin kendisi soru-cevap sayfasi: WebPage yerine FAQPage.
			$graph[ $last ]['@type'] = array( $collection ? 'CollectionPage' : 'WebPage', 'FAQPage' );
			$graph[ $last ]['mainEntity'] = array_map(
				static fn( array $item ): array => array(
					'@type'          => 'Question',
					'name'           => nwcs_seo_clean( $item['question'] ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => nwcs_seo_clean( $item['answer'] ),
					),
				),
				$faq
			);
		}
	}

	/*
	 * Tema eki: firma dugumune calisma saati, sayfaya kendi sorulari gibi
	 * bilgiler ayni @graph'a eklenir. Ayri JSON-LD blogu basilmaz: ayni @id'li
	 * ikinci dugum (adi, adresi olmayan "LocalBusiness") denetimde eksik sayilir.
	 */
	$graph = (array) apply_filters( 'nwcs_seo_graph', $graph, $context );

	return array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( $graph ),
	);
}

/**
 * Liste sayfasinin ogeleri (ItemList), sayfadaki sirayla.
 *
 * @param array<int, array{url:string, name?:string}> $items
 */
function nwcs_seo_item_list( array $items ): array {
	return array(
		'@type'           => 'ItemList',
		'numberOfItems'   => count( $items ),
		'itemListElement' => array_map(
			static fn( array $item, int $index ): array => array_filter(
				array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'url'      => (string) $item['url'],
					'name'     => nwcs_seo_clean( (string) ( $item['name'] ?? '' ) ),
				)
			),
			array_values( $items ),
			array_keys( array_values( $items ) )
		),
	);
}

/**
 * Konum yolu: Anasayfa > ust sayfalar > bu sayfa.
 *
 * @return array<int, array{name:string, url:string}>
 */
function nwcs_seo_breadcrumbs( array $context ): array {
	$crumbs = array(
		array(
			'name' => 'Anasayfa',
			'url'  => home_url( '/' ),
		),
	);

	if ( 'page' === $context['kind'] && 'home' === $context['page_key'] ) {
		return $crumbs;
	}

	$path_of = static fn( string $url ): string => untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$home    = $path_of( home_url( '/' ) );

	// Sayfanin ustleri adres yolundan bulunur (/urunlerimiz/euro-palet/ ->
	// /urunlerimiz/). Blog yazisinin adresi kokte oldugu icin ustu, blog
	// listesi sayfasi ve onun ustleridir.
	$anchor    = $path_of( $context['url'] );
	$inclusive = false;

	if ( 'post' === $context['kind'] && get_option( 'page_for_posts' ) ) {
		$anchor    = $path_of( (string) get_permalink( (int) get_option( 'page_for_posts' ) ) );
		$inclusive = true;
	}

	$ancestors = array();

	foreach ( nwcs_seo_pages() as $key => $page ) {
		$path     = $path_of( home_url( $page['path'] ) );
		$is_upper = $inclusive ? $path === $anchor || str_starts_with( $anchor . '/', $path . '/' ) : $path !== $anchor && str_starts_with( $anchor . '/', $path . '/' );

		if ( $path !== $home && $is_upper ) {
			$ancestors[ strlen( $path ) ] = array(
				'name' => nwcs_seo_page_auto( $key )['name'],
				'url'  => home_url( $page['path'] ),
			);
		}
	}

	ksort( $ancestors );

	return array_merge(
		$crumbs,
		array_values( $ancestors ),
		array(
			array(
				'name' => $context['name'],
				'url'  => $context['url'],
			),
		)
	);
}

/**
 * Urun sayfasi: ad, aciklama, gorsel ve sartname satirlari.
 * Fiyat, puan ve yorum uydurulmaz; fiyat yoksa 'offers' hic yazilmaz.
 */
function nwcs_seo_product( array $context, string $org_id, string $page_id ): array {
	$properties = array_map(
		static fn( array $pair ): array => array(
			'@type' => 'PropertyValue',
			'name'  => $pair['name'],
			'value' => $pair['value'],
		),
		nwcs_seo_product_specs( $context['page_key'], $context['properties'] )
	);

	return array_filter(
		array(
			'@type'              => 'Product',
			'@id'                => $context['url'] . '#product',
			'name'               => $context['name'],
			'description'        => $context['description'],
			'image'              => $context['image']['url'] ?? '',
			'url'                => $context['url'],
			'brand'              => nwcs_seo_brand(),
			'manufacturer'       => array( '@id' => $org_id ),
			'mainEntityOfPage'   => array( '@id' => $page_id ),
			'additionalProperty' => $properties,
			'sku'                => (string) ( $context['sku'] ?? '' ),
			// Yalnizca temanin bildirdigi gercek fiyat; fiyat yoksa teklif yok.
			'offers'             => ! empty( $context['price'] ) ? nwcs_seo_product_offer( $context, $org_id ) : array(),
			'subjectOf'          => nwcs_seo_product_video( $context ),
		)
	);
}

/**
 * Urunun teklifi (Offer). Fiyat metni KDV'den soz ediyorsa ("449 ₺ + KDV")
 * tutar oldugu gibi yazilir ve priceSpecification KDV'nin dahil olmadigini
 * bildirir; KDV hesaplanip eklenmez (oran urune gore degisebilir).
 */
function nwcs_seo_product_offer( array $context, string $org_id ): array {
	$price    = (string) round( (float) $context['price'], 2 );
	$currency = (string) ( $context['currency'] ?? 'TRY' );
	$offer    = array(
		'@type'         => 'Offer',
		'price'         => $price,
		'priceCurrency' => $currency,
		'url'           => $context['url'],
		'seller'        => array( '@id' => $org_id ),
	);

	if ( is_bool( $context['vat_included'] ?? null ) ) {
		$offer['priceSpecification'] = array(
			'@type'                 => 'UnitPriceSpecification',
			'price'                 => $price,
			'priceCurrency'         => $currency,
			'valueAddedTaxIncluded' => $context['vat_included'],
		);
	}

	// Stok, nakliye ve iade: yalnizca tema firmanin gercek kosullarini verirse.
	return $offer + nwcs_seo_merchant_offer_fields();
}

/**
 * Satici girisi (merchant listing) icin teklife eklenen alanlar: stok durumu,
 * urun durumu, nakliye (shippingDetails) ve iade (hasMerchantReturnPolicy).
 *
 * Bunlar firmanin kosullaridir; eklenti uydurmaz, varsayilan bos. Tema
 * 'nwcs_seo_merchant_offer_fields' suzgeciyle verir (bkz. kocist-theme) ve
 * degerler sitenin Odeme/Teslimat ve Iade sayfalarindaki metinle ayni olmali:
 * Google yapilandirilmis verinin sayfada gorunenle tutarli olmasini ister.
 *
 * @return array<string, mixed>
 */
function nwcs_seo_merchant_offer_fields(): array {
	return (array) apply_filters( 'nwcs_seo_merchant_offer_fields', array() );
}

/**
 * Marka: Google 'brand' icin Brand ya da Organization turu ister; yerel
 * isletme dugumune @id baglantisi Satici girislerinde hata veriyordu.
 */
function nwcs_seo_brand(): array {
	return array(
		'@type' => 'Brand',
		'name'  => nwcs_seo_site_name(),
	);
}

/**
 * Teklifle satilan urunun hizmet dugumu. Fiyat, puan ve yorum yazilmaz.
 */
function nwcs_seo_quote_service( array $context, string $org_id, string $page_id ): array {
	return array_filter(
		array(
			'@type'            => 'Service',
			'@id'              => $context['url'] . '#product',
			'name'             => $context['name'],
			'serviceType'      => $context['name'],
			'description'      => $context['description'],
			'image'            => $context['image']['url'] ?? '',
			'url'              => $context['url'],
			'provider'         => array( '@id' => $org_id ),
			// Hizmet bolgesi yazilmaz: sitelerin teslimat sayfalari farkli
			// bolgeler soyluyor (Koçist: Istanbul ve Trakya); uydurulmaz.
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'subjectOf'        => nwcs_seo_product_video( $context ),
		)
	);
}

/**
 * Urun videosu (VideoObject). Google'in istedigi ad, kapak ve yukleme tarihi
 * yoksa hic yazilmaz; kapak urunun gorselidir.
 */
function nwcs_seo_product_video( array $context ): array {
	$video = (array) ( $context['video'] ?? array() );
	$thumb = (string) ( $context['image']['url'] ?? '' );

	if ( empty( $video['url'] ) || '' === $thumb || empty( $video['date'] ) ) {
		return array();
	}

	$length = (int) ( $video['length'] ?? 0 );

	return array_filter(
		array(
			'@type'        => 'VideoObject',
			'name'         => $context['name'],
			'description'  => '' !== $context['description'] ? $context['description'] : $context['name'],
			'thumbnailUrl' => $thumb,
			'contentUrl'   => (string) $video['url'],
			'uploadDate'   => (string) $video['date'],
			'duration'     => $length > 0 ? sprintf( 'PT%dM%dS', intdiv( $length, 60 ), $length % 60 ) : '',
		)
	);
}

/**
 * Urunun sartname satirlari: manifest sayfasinda seo_source.properties
 * (label/value satirlari), temanin ek sayfasinda dogrudan verilen ciftler.
 *
 * @return array<int, array{name:string, value:string}>
 */
function nwcs_seo_product_specs( string $page_key, array $given = array() ): array {
	$rows = $given;

	if ( '' !== $page_key ) {
		$source = nwcs_manifest()['pages'][ $page_key ]['seo_source'] ?? array();
		$rows   = empty( $source['properties'] ) ? array() : (array) nwcs_seo_path_value( $page_key, $source['properties'] );
	}

	$specs = array();

	foreach ( $rows as $row ) {
		$name  = nwcs_seo_clean( $row['name'] ?? $row['label'] ?? '' );
		$value = nwcs_seo_clean( $row['value'] ?? '' );

		if ( '' !== $name && '' !== $value ) {
			$specs[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}
	}

	return $specs;
}

/**
 * Soru-cevap sayfasinin satirlari: seo_source.questions (question/answer
 * alanli tekrarli satirlar).
 *
 * @return array<int, array{question:string, answer:string}>
 */
function nwcs_seo_faq( string $page_key ): array {
	$source = nwcs_manifest()['pages'][ $page_key ]['seo_source'] ?? array();
	$items  = array();

	if ( '' === $page_key || empty( $source['questions'] ) ) {
		return $items;
	}

	foreach ( (array) nwcs_seo_path_value( $page_key, $source['questions'] ) as $row ) {
		$question = nwcs_seo_clean( $row['question'] ?? '' );
		$answer   = nwcs_seo_clean( $row['answer'] ?? '' );

		if ( '' !== $question && '' !== $answer ) {
			$items[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}
	}

	return $items;
}

/**
 * SSS sayfasinda gorunecek satirlar: temalarin ortak okuyucusu.
 *
 * Yalnizca sorusu VE cevabi dolu satirlar doner (FAQPage isaretiyle ayni
 * kural): cevabi bos soru ne sayfada ne arama verisinde gorunur; firmadan
 * cevap gelince panelden doldurulur. `_row` satirin paneldeki sirasi,
 * onizlemede tiklaninca dogru satir acilsin diye.
 *
 * @return array<int, array{group:string, question:string, answer:string, _row:int}>
 */
function nwcs_faq_rows( string $page_key, string $component = 'items', string $field = 'rows' ): array {
	$rows = array();

	foreach ( nwcs_rows( $page_key, $component, $field ) as $index => $row ) {
		$question = trim( (string) ( $row['question'] ?? '' ) );
		$answer   = trim( (string) ( $row['answer'] ?? '' ) );

		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$rows[] = array(
			'group'    => trim( (string) ( $row['group'] ?? '' ) ),
			'question' => $question,
			'answer'   => $answer,
			'_row'     => (int) $index,
		);
	}

	return $rows;
}

/**
 * SSS satirlari grup adina gore; bos grup adi $other altinda toplanir.
 * Sira panel sirasidir (grubun ilk sorusu grubun yerini belirler).
 *
 * @return array<string, array<int, array{group:string, question:string, answer:string, _row:int}>>
 */
function nwcs_faq_groups( string $page_key, string $other = 'Genel' ): array {
	$groups = array();

	foreach ( nwcs_faq_rows( $page_key ) as $row ) {
		$groups[ '' !== $row['group'] ? $row['group'] : $other ][] = $row;
	}

	return $groups;
}

/**
 * Blog yazisi.
 */
function nwcs_seo_article( array $context, string $org_id, string $page_id, string $language ): array {
	$post = $context['post'];

	return array_filter(
		array(
			'@type'            => 'BlogPosting',
			'@id'              => $context['url'] . '#article',
			'headline'         => $context['name'],
			'description'      => $context['description'],
			'image'            => $context['image']['url'] ?? '',
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'inLanguage'       => $language,
			'author'           => array( '@id' => $org_id ),
			'publisher'        => array( '@id' => $org_id ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
		)
	);
}

/* ====================================================================== *
 * Dizinleme kurallari ve site haritasi
 * ====================================================================== */

/**
 * Icerigi olmayan arsivler dizine girmesin: yazar, tarih, arama, ek sayfasi,
 * kategori ve etiket (bu sitelerde birkac yazilik ince listeler; yazilarin
 * kendisi ve blog sayfasi dizinde).
 */
add_filter( 'wp_robots', 'nwcs_seo_robots' );
function nwcs_seo_robots( array $robots ): array {
	if ( empty( nwcs_manifest()['pages'] ) ) {
		return $robots;
	}

	if ( is_author() || is_date() || is_search() || is_attachment() || is_category() || is_tag() || is_tax() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}

	// SEO disi sablon sayfasi ('seo' => false). Site arama motorlarina kapaliysa nofollow kalir.
	if ( nwcs_seo_is_excluded_request() ) {
		$robots['noindex'] = true;
		unset( $robots['max-image-preview'] );

		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
	}

	return $robots;
}

/**
 * Yazar arsivi yok: bu sitelerde yazar sayfasi kullanilmiyor, ?author=1 ise
 * yonetici kullanici adini aciga cikarir (giris denemelerinin ilk adimi).
 * Istek 404 olur; eski sitenin /author/ adresleri icin yonlendirme listesindeki
 * 410 kurali boylece calisir (liste yalnizca bulunamayan adreste devreye girer).
 */
add_filter( 'request', 'nwcs_seo_no_author_archives' );
function nwcs_seo_no_author_archives( array $query_vars ): array {
	// Agin ana sitesi (panel kokunun) manifesti yok ama ayni kullanicilari tasir.
	if ( is_admin() || ( empty( nwcs_manifest()['pages'] ) && ! is_main_site() ) ) {
		return $query_vars;
	}

	// Sorgu hic calismadan bulunamayan sayfa: var olan (?author=1) ile olmayan
	// (?author=2) kullanici ayni 404'u alir; kullanici kimligi dogrulanamaz.
	if ( isset( $query_vars['author'] ) || isset( $query_vars['author_name'] ) ) {
		return array( 'error' => '404' );
	}

	return $query_vars;
}

/**
 * REST API kullanici listesi yalnizca giris yapmis kullaniciya: yazisi olan
 * sitede /wp-json/wp/v2/users yonetici kullanici adini herkese gosteriyordu.
 * Panel (blok duzenleyici) giris yapmis istekle calistigi icin etkilenmez.
 */
add_filter( 'rest_endpoints', 'nwcs_seo_hide_rest_users' );
function nwcs_seo_hide_rest_users( array $endpoints ): array {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	foreach ( array_keys( $endpoints ) as $route ) {
		if ( str_starts_with( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
}

// Yerlestirme (oEmbed) verisinde yazar adi ve adresi de kullanici adini tasir.
add_filter(
	'oembed_response_data',
	static function ( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}
);

/**
 * Agin ana sitesi (panel adresinin koku) ziyaretci icin bir sey gostermez:
 * arama motoruna hicbir kosulda girmesin. Alt siteler ve yonetim etkilenmez.
 */
add_action( 'send_headers', 'nwcs_seo_noindex_main_site' );

/**
 * Surum ve kullanici adi sizintisi: WordPress surumu (generator etiketi,
 * cekirdek dosyalarindaki ?ver=), PHP surumu (X-Powered-By) ve beslemelerdeki
 * yazar adi gizlenir. Baska sitelerin sayfalari cerceve icinde gostermesi
 * (clickjacking) yalnizca ayni site ve panel adresiyle sinirlanir; panelin
 * canli onizlemesi calismaya devam eder.
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

add_filter( 'style_loader_src', 'nwcs_seo_strip_core_version', 20 );
add_filter( 'script_loader_src', 'nwcs_seo_strip_core_version', 20 );
function nwcs_seo_strip_core_version( $src ) {
	if ( is_string( $src ) && str_contains( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
		return remove_query_arg( 'ver', $src );
	}

	return $src;
}

add_filter(
	'the_author',
	static fn( $name ) => is_feed() ? '' : $name
);

add_action( 'send_headers', 'nwcs_seo_security_headers' );
function nwcs_seo_security_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	header_remove( 'X-Powered-By' );

	if ( is_admin() ) {
		return;
	}

	// Panel adresi kayitta http olup tarayicida https acilabilir (Cloudflare):
	// iki sema da izinli, yoksa canli onizleme bos kalir.
	$host = (string) wp_parse_url( network_site_url(), PHP_URL_HOST );
	$port = wp_parse_url( network_site_url(), PHP_URL_PORT );
	$host = $port ? $host . ':' . $port : $host;

	if ( '' === $host ) {
		return;
	}

	header( sprintf( "Content-Security-Policy: frame-ancestors 'self' https://%1\$s http://%1\$s", $host ), true );
}
function nwcs_seo_noindex_main_site(): void {
	if ( ! is_multisite() || is_admin() || headers_sent() ) {
		return;
	}

	// Ana site ve arama motorlarina kapali alt siteler (panelde onay bekleyen
	// Kocist, WOOD KOCIST...): beslemeler dahil her yanitta.
	if ( is_main_site() || '0' === (string) get_option( 'blog_public' ) ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}

/**
 * Ek sayfalar icin site haritasi (/wp-sitemap-nwcsextra-1.xml): temanin
 * 'sitemap' => true isaretledigi adresler. Site arama motorlarina kapaliysa
 * WordPress site haritasini zaten kapatir.
 */
add_action( 'init', 'nwcs_seo_register_extra_sitemap' );
function nwcs_seo_register_extra_sitemap(): void {
	if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
		return;
	}

	if ( ! class_exists( 'NWCS_Sitemap_Extra' ) ) {
		require_once NWCS_DIR . 'includes/sitemap-extra.php';
	}

	wp_register_sitemap_provider( 'nwcsextra', new NWCS_Sitemap_Extra() );
}

/**
 * Site haritasina girecek ek sayfalar: adres ve (tema verdiyse) son
 * degisiklik tarihi. Ayni adres bir kez girer.
 *
 * @return array<int, array{loc:string, lastmod?:string}>
 */
function nwcs_seo_extra_sitemap_entries(): array {
	$entries = array();

	foreach ( nwcs_seo_extra_pages() as $page ) {
		$loc = ! empty( $page['sitemap'] ) ? esc_url_raw( (string) $page['url'] ) : '';

		if ( '' === $loc || isset( $entries[ $loc ] ) ) {
			continue;
		}

		$entries[ $loc ] = array( 'loc' => $loc );
		$modified        = strtotime( (string) ( $page['lastmod'] ?? '' ) . ' UTC' );

		// Bos ya da "0000-00-00 00:00:00" tarih yazilmaz.
		if ( '' !== (string) ( $page['lastmod'] ?? '' ) && $modified > 0 ) {
			$entries[ $loc ]['lastmod'] = gmdate( DATE_W3C, $modified );
		}
	}

	return array_values( $entries );
}

/**
 * Site haritasindan kullanici listesi cikarilir: yonetici kullanici adini
 * herkese acik bir dosyada listelemek gereksiz bir guvenlik acigi. Kategori
 * ve etiket arsivleri de cikar: noindex olan adres site haritasinda olmaz.
 */
add_filter( 'wp_sitemaps_add_provider', 'nwcs_seo_sitemap_providers', 10, 2 );
function nwcs_seo_sitemap_providers( $provider, string $name ) {
	return in_array( $name, array( 'users', 'taxonomies' ), true ) ? false : $provider;
}

/**
 * 'seo' => false isaretli sayfa site haritasinda yok.
 */
add_filter( 'wp_sitemaps_posts_query_args', 'nwcs_seo_sitemap_skip_excluded', 10, 2 );
function nwcs_seo_sitemap_skip_excluded( array $args, string $post_type ): array {
	if ( 'page' !== $post_type || empty( nwcs_manifest()['pages'] ) ) {
		return $args;
	}

	$ids = nwcs_seo_excluded_page_ids();

	if ( $ids ) {
		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $ids );
	}

	return $args;
}

/* ====================================================================== *
 * /llms.txt
 *
 * Yapay zeka motorlari icin sitenin duz metin ozeti (llmstxt.org bicimi):
 * firma, sayfalar ve kisa aciklamalari, urunlerin sartname bilgileri,
 * blog yazilari ve iletisim. Icerikten her istekte uretilir; elle
 * guncellenmesi gerekmez.
 * ====================================================================== */

add_action( 'parse_request', 'nwcs_seo_maybe_llms' );
function nwcs_seo_maybe_llms( WP $wp ): void {
	if ( 'llms.txt' !== $wp->request || empty( nwcs_manifest()['pages'] ) ) {
		return;
	}

	// Arama motorlarina kapali sitede yapay zeka ozeti de verilmez.
	if ( ! get_option( 'blog_public' ) ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );

	echo nwcs_seo_llms_text(); // phpcs:ignore WordPress.Security.EscapingOutput -- duz metin; Markdown.
	exit;
}

/**
 * llms.txt icerigi.
 */
function nwcs_seo_llms_text(): string {
	$org   = nwcs_seo_org_data();
	$lines = array( '# ' . $org['name'], '' );

	$summary = '' !== $org['description'] ? $org['description'] : nwcs_seo_clean( get_bloginfo( 'description' ) );

	if ( '' !== $summary ) {
		$lines[] = '> ' . $summary;
		$lines[] = '';
	}

	$facts = array_filter(
		array(
			'' !== $org['legal_name'] ? 'Resmî unvan: ' . $org['legal_name'] : '',
			'' !== $org['street'] ? 'Adres: ' . implode( ', ', array_filter( array( $org['street'], $org['district'], $org['city'], $org['postal_code'] ) ) ) : '',
			'' !== $org['phone'] ? 'Telefon: ' . $org['phone'] : '',
			'' !== $org['mobile'] && $org['mobile'] !== $org['phone'] ? 'Cep ve WhatsApp: ' . $org['mobile'] : '',
			'' !== $org['email'] ? 'E-posta: ' . $org['email'] : '',
			'' !== $org['parent_name'] ? 'Bağlı olduğu grup: ' . $org['parent_name'] . ( '' !== $org['parent_url'] ? ' (' . $org['parent_url'] . ')' : '' ) : '',
		)
	);

	foreach ( $facts as $fact ) {
		$lines[] = '- ' . $fact;
	}

	if ( $facts ) {
		$lines[] = '';
	}

	$lines[] = '## Sayfalar';
	$lines[] = '';

	foreach ( nwcs_seo_pages() as $key => $page ) {
		$resolved = nwcs_seo_page_resolved( $key );
		$line     = sprintf( '- [%s](%s)', $resolved['name'], home_url( $page['path'] ) );

		if ( '' !== $resolved['description'] ) {
			$line .= ': ' . $resolved['description'];
		}

		// Urunlerde sartname duz metin olarak: olculer gorselde degil yazida.
		if ( 'Product' === ( $page['seo_source']['type'] ?? '' ) ) {
			$line .= nwcs_seo_llms_specs( nwcs_seo_product_specs( $key ) );
		}

		$lines[] = $line;
	}

	// Temanin bildirdigi ek sayfalar (tek sablonla cizilen urun sayfalari gibi).
	foreach ( nwcs_seo_extra_pages() as $page ) {
		// Ayni adres manifestte de sayfaysa (panelde duzenlenen kategori sayfasi) yukarida yazildi.
		if ( '' !== nwcs_seo_page_for_path( untrailingslashit( (string) wp_parse_url( $page['url'], PHP_URL_PATH ) ) ) ) {
			continue;
		}

		$line = sprintf( '- [%s](%s)', nwcs_seo_clean( $page['name'] ), $page['url'] );

		if ( '' !== nwcs_seo_clean( $page['description'] ?? '' ) ) {
			$line .= ': ' . nwcs_seo_clean( $page['description'], 160 );
		}

		$lines[] = $line . nwcs_seo_llms_specs( nwcs_seo_product_specs( '', (array) ( $page['properties'] ?? array() ) ) );
	}

	// Sik sorulan sorular: yapay zeka yanitlarinin en dogrudan kaynagi.
	foreach ( nwcs_seo_pages() as $key => $page ) {
		if ( 'FAQPage' !== ( $page['seo_source']['type'] ?? '' ) ) {
			continue;
		}

		$faq = nwcs_seo_faq( $key );

		if ( $faq ) {
			$lines[] = '';
			$lines[] = '## Sık sorulan sorular';
			$lines[] = '';

			foreach ( $faq as $item ) {
				$lines[] = '- ' . $item['question'] . ' ' . $item['answer'];
			}
		}
	}

	// Kategori sayfalarinin kendi sorulari (kategori basligi ve sayfasinin #sss capasiyla).
	if ( function_exists( 'nwcs_cat_faq_llms_lines' ) ) {
		$lines = array_merge( $lines, nwcs_cat_faq_llms_lines() );
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
		)
	);

	if ( $posts ) {
		$lines[] = '';
		$lines[] = '## Blog';
		$lines[] = '';

		foreach ( $posts as $post ) {
			$excerpt = nwcs_seo_clean( nwcs_seo_post_text( $post ), 140 );
			$lines[] = sprintf( '- [%s](%s): %s', nwcs_seo_clean( get_the_title( $post ) ), get_permalink( $post ), $excerpt );
		}
	}

	$lines[] = '';
	$lines[] = '## Ek';
	$lines[] = '';
	$lines[] = sprintf( '- [Site haritası](%s)', home_url( '/wp-sitemap.xml' ) );

	foreach ( $org['same_as'] as $url ) {
		$lines[] = sprintf( '- [Firmanın diğer adresi](%s)', $url );
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * llms.txt satirina eklenen sartname: " (Ad: deger; Ad: deger)".
 */
function nwcs_seo_llms_specs( array $specs ): string {
	if ( ! $specs ) {
		return '';
	}

	return ' (' . implode( '; ', array_map( static fn( array $pair ): string => $pair['name'] . ': ' . $pair['value'], $specs ) ) . ')';
}
