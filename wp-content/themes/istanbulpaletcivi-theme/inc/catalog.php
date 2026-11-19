<?php
/**
 * Urun katalogu: cesit bazli urunler (olcu, govde, kaplama, paket).
 *
 * Tek kaynak: products.catalog.items (panelden duzenlenir; varsayilanlar
 * content/catalog.php). Her satirin sayfasi /civiler/<slug>/ adresindedir.
 * WordPress alt sayfasi acilmaz: istek 404 olup yol bir urune denk gelirse
 * sayfa urun detayi olarak 200 ile cizilir (kereste temalariyla ayni yontem).
 *
 * Her urun bir civi tipine baglidir ('group' = tip sayfasinin adres adi:
 * rulo-palet-civiler, tele-dizili-civiler, dokme-civiler ...). Tip sayfasi
 * kendi urunlerini listeler; urun sayfasi ayni tipteki diger urunleri gosterir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Katalog satirlari; adres adi bos olanlar atlanir. Ayni adres iki kez
 * gecerse ilk satir kalir.
 *
 * @return array<int, array{index:int, slug:string, title:string, group:string, short:string, body:string, specs:string, image:int, seo_title:string, seo_description:string, url:string, path:string}>
 */
function pc_catalog(): array {
	static $items = null;

	if ( null !== $items ) {
		return $items;
	}

	$items = array();
	$seen  = array();

	foreach ( nwcs_rows( 'products', 'catalog', 'items' ) as $index => $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );

		if ( '' === $slug || isset( $seen[ $slug ] ) ) {
			continue;
		}

		$seen[ $slug ] = true;
		$path          = '/civiler/' . $slug . '/';

		$items[] = array(
			'index'           => (int) $index,
			'slug'            => $slug,
			'title'           => trim( (string) ( $row['title'] ?? '' ) ),
			'group'           => sanitize_title( (string) ( $row['group'] ?? '' ) ),
			'short'           => trim( (string) ( $row['short'] ?? '' ) ),
			'body'            => (string) ( $row['body'] ?? '' ),
			'specs'           => (string) ( $row['specs'] ?? '' ),
			'sizes'           => (string) ( $row['sizes'] ?? '' ),
			'image'           => (int) ( $row['image'] ?? 0 ),
			'seo_title'       => trim( (string) ( $row['seo_title'] ?? '' ) ),
			'seo_description' => trim( (string) ( $row['seo_description'] ?? '' ) ),
			'path'            => $path,
			'url'             => pc_link( $path ),
		);
	}

	return $items;
}

/**
 * Adres adina gore urun; yoksa bos dizi.
 */
function pc_catalog_item( string $slug ): array {
	foreach ( pc_catalog() as $item ) {
		if ( $item['slug'] === $slug ) {
			return $item;
		}
	}

	return array();
}

/**
 * Bir civi tipine (tip sayfasinin adres adi) bagli urunler.
 */
function pc_catalog_group( string $group ): array {
	$group = sanitize_title( $group );

	return array_values( array_filter( pc_catalog(), static fn( array $item ): bool => $item['group'] === $group ) );
}

/**
 * Tip sayfasinin adres adi (manifest anahtarindan): 'rulo' -> 'rulo-palet-civiler'.
 */
function pc_product_group_slug( string $key ): string {
	$path = (string) ( pc_manifest()['pages'][ $key ]['path'] ?? '' );

	return basename( untrailingslashit( $path ) );
}

/**
 * Urunun bagli oldugu tip sayfasi (pc_products() ogesi); yoksa bos dizi.
 */
function pc_catalog_parent( array $item ): array {
	foreach ( pc_products() as $key => $product ) {
		if ( pc_product_group_slug( $key ) === $item['group'] ) {
			return $product;
		}
	}

	return array();
}

/**
 * Urun gorseli: panelde secilen; yoksa tip sayfasinin gorseli (ornek isaretiyle).
 */
function pc_catalog_image( array $item, string $size = 'large' ): array {
	$own = nwcs_image_by_id( (int) $item['image'], $size );

	if ( '' !== ( $own['url'] ?? '' ) ) {
		return pc_img( $own, '', $item['title'] );
	}

	// Sunucunun indirdigi kaynak gorsel (inc/catalog-images.php).
	$loaded = pc_catalog_sideloaded( $item['slug'], $size );

	if ( '' !== ( $loaded['url'] ?? '' ) ) {
		return pc_img( $loaded, '', $item['title'] );
	}

	$parent = pc_catalog_parent( $item );

	if ( $parent ) {
		return pc_product_type_image( (string) $parent['key'], $item['title'], $size );
	}

	return pc_img( array(), '', $item['title'] );
}

/**
 * "Ad: deger" satirlarini ciftlere boler.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function pc_specs( string $text ): array {
	$specs = array();

	foreach ( preg_split( '/\R/u', trim( $text ) ) ?: array() as $line ) {
		$parts = explode( ':', $line, 2 );

		if ( count( $parts ) < 2 ) {
			continue;
		}

		$name  = trim( $parts[0] );
		$value = trim( $parts[1] );

		if ( '' !== $name && '' !== $value ) {
			$specs[] = array( $name, $value );
		}
	}

	return $specs;
}

/**
 * Olcu tablosu: ilk satir basliklar, sutunlar "|" ile ayrilir.
 *
 * @return array{0: string[], 1: array<int, string[]>} [basliklar, satirlar]
 */
function pc_sizes( string $text ): array {
	$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', trim( $text ) ) ?: array() ), static fn( string $l ): bool => '' !== $l ) );

	if ( count( $lines ) < 2 ) {
		return array( array(), array() );
	}

	$split = static fn( string $line ): array => array_map( 'trim', explode( '|', $line ) );
	$head  = $split( array_shift( $lines ) );
	$rows  = array();

	foreach ( $lines as $line ) {
		$cells = $split( $line );
		$cells = array_pad( array_slice( $cells, 0, count( $head ) ), count( $head ), '' );
		$rows[] = $cells;
	}

	return array( $head, $rows );
}

/**
 * Arama basligi (panelde bossa): "<urun> | İstanbul Palet Çivi"; 60'i asarsa yalniz urun.
 */
function pc_catalog_seo_title( array $item ): string {
	if ( '' !== $item['seo_title'] ) {
		return $item['seo_title'];
	}

	$long = $item['title'] . ' | İstanbul Palet Çivi';

	return mb_strlen( $long ) <= 60 ? $long : $item['title'];
}

function pc_catalog_seo_description( array $item ): string {
	if ( '' !== $item['seo_description'] ) {
		return $item['seo_description'];
	}

	$text = '' !== $item['short'] ? $item['short'] : wp_strip_all_tags( $item['body'] );

	return mb_strlen( $text ) <= 160 ? $text : rtrim( mb_substr( $text, 0, 157 ) ) . '…';
}

/* ----------------------------------------------------------------------
 * Yedek cizim: alt sayfasi olmayan urun adresi
 * ---------------------------------------------------------------------- */

function pc_fallback_item( ?array $set = null ): array {
	static $item = array();

	if ( null !== $set ) {
		$item = $set;
	}

	return $item;
}

/*
 * Tespit 'wp' aksiyonunun basinda: SEO eklentisi sayfa bilgisini (baslik,
 * aciklama, canonical, Product verisi) bu aksiyonda hesaplayip sakliyor.
 */
add_action( 'wp', 'pc_catalog_detect', 0 );
function pc_catalog_detect(): void {
	if ( ! is_404() ) {
		return;
	}

	$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$path = trim( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

	if ( '' !== $home && str_starts_with( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) + 1 );
	}

	if ( ! preg_match( '#^civiler/([^/]+)$#', $path, $match ) ) {
		return;
	}

	$item = pc_catalog_item( sanitize_title( rawurldecode( $match[1] ) ) );

	if ( ! $item ) {
		return;
	}

	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	pc_fallback_item( $item );
}

// redirect_canonical (10) benzer sayfaya yonlendirmeden once cizilir.
add_action( 'template_redirect', 'pc_catalog_fallback', 1 );
function pc_catalog_fallback(): void {
	$item = pc_fallback_item();

	if ( ! $item ) {
		return;
	}

	get_header();
	pc_part( 'product-item', array( 'item' => $item ) );
	get_footer();
	exit;
}

add_filter( 'document_title_parts', 'pc_catalog_title' );
function pc_catalog_title( array $parts ): array {
	$item = pc_fallback_item();

	if ( $item ) {
		$parts['title'] = $item['title'];
	}

	return $parts;
}

/*
 * SEO ve GEO: urun sayfalari manifestte degil; baslik, aciklama, Product
 * yapilandirilmis verisi, site haritasi ve llms.txt satiri buradan.
 */
add_filter( 'nwcs_seo_extra_pages', 'pc_catalog_seo_pages' );
function pc_catalog_seo_pages( array $pages ): array {
	foreach ( pc_catalog() as $item ) {
		$image = (int) $item['image'];

		if ( ! $image ) {
			$fallback = pc_catalog_image( $item, 'large' );
			$parent   = pc_catalog_parent( $item );
			$file     = $parent ? (string) ( PC_PRODUCT_IMAGES[ $parent['key'] ] ?? '' ) : '';

			if ( $fallback['id'] ) {
				$image = $fallback['id'];
			} elseif ( '' !== $file && function_exists( 'nwcs_seo_theme_file_image' ) ) {
				$image = nwcs_seo_theme_file_image( 'assets/img/' . $file, $item['title'] );
			}
		}

		$pages[] = array(
			'url'         => $item['url'],
			'name'        => $item['title'],
			'title'       => pc_catalog_seo_title( $item ),
			'description' => pc_catalog_seo_description( $item ),
			'image'       => $image,
			'type'        => 'Product',
			'sitemap'     => true,
			'properties'  => array_map(
				static fn( array $pair ): array => array( 'name' => $pair[0], 'value' => $pair[1] ),
				pc_specs( $item['specs'] )
			),
		);
	}

	return $pages;
}
