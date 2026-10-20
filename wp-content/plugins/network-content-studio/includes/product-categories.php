<?php
/**
 * Kategori yerlesimi: hangi havuz kategorisi hangi sitede, hangi ust basligin
 * (WOOD KOCIST'te seri, Kocist'te urun grubu) altinda gorunur.
 *
 * Bilgi veridir, tek yerden yonetilir (Ürün Havuzu -> Kategoriler):
 *   havuz kategorisinin terim meta'si '_nwcs_placement' = [ site_key => ust_baslik_anahtari ]
 * Taksonomide hiyerarsi kullanilmaz; butun terimler parent=0 kalir.
 *
 * Temalar yerlesimi okur, kendi listelerini tasimaz. Havuzu tuketen temanin
 * manifestinde bir 'catalog' blogu bulunur:
 *   'catalog' => array(
 *       'parents'     => array( 'rows' => array( sayfa, bilesen, alan ), 'key' => ..., 'label' => ... ),
 *       'page'        => kategori sayfasi sablonu ('kat-<havuz-slug>'),
 *       'parent_page' => ust baslik sayfasi sablonu ('seri-<anahtar>' / 'grp-<anahtar>'),
 *       'defaults'    => [ havuz-slug => [ alan => varsayilan ] ],  eski sabit metinler
 *       'seed'        => [ havuz-slug => ust_baslik ],               tek seferlik yerlesim tohumu
 *   )
 * Eklenti bu sablondan her yerlesik kategori icin panel sayfasini uretir
 * (nwcs_catalog_augment_manifest, manifest.php'den cagrilir).
 *
 * Gorunurluk kurali: kategori bir siteye yerlesince urunleri o sitede gorunur.
 * Site "secilenler" kipindeyse urun kimlikleri secim listesinin sonuna eklenir
 * (yerlesim yapildiginda, urun kategoriye eklendiginde, urun yaratildiginda);
 * "hepsi" kipinde hicbir sey yazilmaz. Surekli senkron yoktur: Icerik
 * Studyosu'ndan cikarilan urun geri eklenmez.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PLACEMENT_META     = '_nwcs_placement';
const NWCS_POOL_CATS_KEY      = 'nwcs_pool_categories_v4';
const NWCS_PLACEMENT_SEED     = 'nwcs_placement_seed_version';
const NWCS_PLACEMENT_SEED_VER = 1;

/* ====================================================================== *
 * Havuz kategorileri (yerlesimle birlikte)
 * ====================================================================== */

/**
 * Havuzdaki kategoriler: slug => [ id, name, count, placement ].
 *
 * Sonuc istek ici degiskende ve havuz sitesinin transient'inda tutulur;
 * kategori/urun degisikliklerinde nwcs_pool_categories_flush() temizler.
 * Taksonomi henuz kayitli degilse (init oncesi) bos doner ve saklanmaz.
 *
 * @return array<string, array{id:int, name:string, count:int, placement:array<string,string>}>
 */
function nwcs_pool_categories( bool $reset = false ): array {
	static $cache = null;

	if ( $reset ) {
		$cache = null;

		return array();
	}

	if ( null !== $cache ) {
		return $cache;
	}

	if ( ! taxonomy_exists( NWCS_PRODUCT_TAX ) ) {
		return array();
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$stored = get_transient( NWCS_POOL_CATS_KEY );

	if ( is_array( $stored ) ) {
		restore_current_blog();
		$cache = $stored;

		return $cache;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => NWCS_PRODUCT_TAX,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	$out = array();

	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$placement = get_term_meta( (int) $term->term_id, NWCS_PLACEMENT_META, true );

		$out[ $term->slug ] = array(
			'id'        => (int) $term->term_id,
			'name'      => $term->name,
			'count'     => (int) $term->count,
			'placement' => nwcs_clean_placement( is_array( $placement ) ? $placement : array() ),
		);
	}

	if ( ! is_wp_error( $terms ) ) {
		set_transient( NWCS_POOL_CATS_KEY, $out, HOUR_IN_SECONDS );
	}

	restore_current_blog();

	$cache = $out;

	return $out;
}

/**
 * Kategori onbellegini (ve ondan uretilen manifest sayfalarini) temizler.
 */
function nwcs_pool_categories_flush(): void {
	nwcs_cache_mark_dirty();

	switch_to_blog( nwcs_pool_blog_id() );
	delete_transient( NWCS_POOL_CATS_KEY );
	restore_current_blog();

	nwcs_pool_categories( true );
	nwcs_site_category_tree( 0, true );

	// Renk gruplari kategorinin meta'sinda; urun-kategori baglari da onlari etkiler.
	if ( function_exists( 'nwcs_color_flush' ) ) {
		nwcs_color_flush();
	}

	// Kategoriye ozel sorular da kategorinin meta'sinda (includes/category-faq.php).
	if ( function_exists( 'nwcs_cat_faq_flush' ) ) {
		nwcs_cat_faq_flush();
	}

	if ( function_exists( 'nwcs_manifest_reset' ) ) {
		nwcs_manifest_reset();
	}
}

// Kategori ya da urun-kategori baglari degisince (form, Excel, paket, silme).
add_action( 'created_' . NWCS_PRODUCT_TAX, 'nwcs_pool_categories_flush' );
add_action( 'edited_' . NWCS_PRODUCT_TAX, 'nwcs_pool_categories_flush' );
add_action( 'delete_' . NWCS_PRODUCT_TAX, 'nwcs_pool_categories_flush' );
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ): void {
		if ( NWCS_PRODUCT_TAX === $taxonomy ) {
			nwcs_pool_categories_flush();
		}
	},
	10,
	4
);

/**
 * Yerlesim dizisini temizler: site_key => ust baslik anahtari (ikisi de dolu).
 */
function nwcs_clean_placement( array $placement ): array {
	$out = array();

	foreach ( $placement as $site_key => $parent ) {
		$site_key = sanitize_key( (string) $site_key );
		$parent   = sanitize_title( (string) $parent );

		if ( '' !== $site_key && '' !== $parent ) {
			$out[ $site_key ] = $parent;
		}
	}

	return $out;
}

/**
 * Butun yerlesimler: havuz-slug => [ site_key => ust baslik ].
 */
function nwcs_category_placements(): array {
	return array_filter( array_map( static fn( array $term ): array => $term['placement'], nwcs_pool_categories() ) );
}

/**
 * Tek kategorinin tek sitedeki yerlesimini yazar ya da kaldirir ($parent null).
 * Yalnizca veriyi yazar; site secimlerine dokunmaz (bkz. nwcs_placement_update).
 *
 * @return bool Degisti mi?
 */
function nwcs_set_placement( string $slug, string $site_key, ?string $parent ): bool {
	$categories = nwcs_pool_categories();
	$site_key   = sanitize_key( $site_key );

	if ( ! isset( $categories[ $slug ] ) || '' === $site_key ) {
		return false;
	}

	$placement = $categories[ $slug ]['placement'];
	$before    = $placement;

	if ( null === $parent || '' === sanitize_title( $parent ) ) {
		unset( $placement[ $site_key ] );
	} else {
		$placement[ $site_key ] = sanitize_title( $parent );
	}

	if ( $placement === $before ) {
		return false;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	if ( $placement ) {
		update_term_meta( (int) $categories[ $slug ]['id'], NWCS_PLACEMENT_META, $placement );
	} else {
		delete_term_meta( (int) $categories[ $slug ]['id'], NWCS_PLACEMENT_META );
	}

	restore_current_blog();

	nwcs_pool_categories_flush();

	return true;
}

/* ====================================================================== *
 * Siteler ve ust basliklar
 * ====================================================================== */

/**
 * Havuzu kategori yerlesimiyle tuketen siteler (temasinin manifestinde
 * 'catalog' ve 'site_key' olanlar): blog_id => [ site_key, label, catalog ].
 * Temanin kendi manifesti okunur (uretilen sayfalar gerekmez; dongu olmaz).
 */
function nwcs_catalog_sites(): array {
	static $sites = null;

	if ( null !== $sites ) {
		return $sites;
	}

	$sites = array();

	foreach ( get_sites( array( 'number' => 200 ) ) as $site ) {
		$blog_id  = (int) $site->blog_id;
		$theme    = (string) get_blog_option( $blog_id, 'stylesheet', '' );
		$manifest = '' !== $theme ? nwcs_manifest_for_theme( $theme ) : array();

		if ( empty( $manifest['catalog'] ) || '' === (string) ( $manifest['site_key'] ?? '' ) ) {
			continue;
		}

		$sites[ $blog_id ] = array(
			'blog_id'  => $blog_id,
			'site_key' => sanitize_key( (string) $manifest['site_key'] ),
			'label'    => function_exists( 'nwcs_site_panel_name' ) ? nwcs_site_panel_name( $manifest, $blog_id ) : (string) ( $manifest['site_label'] ?? $manifest['site_key'] ),
			'catalog'  => (array) $manifest['catalog'],
		);
	}

	return $sites;
}

/**
 * Sitenin site anahtari (manifest 'site_key'); yoksa bos.
 */
function nwcs_catalog_site_key( int $blog_id ): string {
	return nwcs_catalog_sites()[ $blog_id ]['site_key'] ?? '';
}

/**
 * Sitenin ust basliklari (seri / urun grubu), panelde duzenlenen satirlardan.
 * Once sitenin kayitli icerigi, yoksa manifest varsayilani okunur.
 *
 * Anahtar: satirdaki anahtar alaninin slug'i (WK'da "WOODPets" -> woodpets,
 * Kocist'te "kereste"). Yerlesimler bu anahtara baglidir; alanin metni
 * degisirse o basliga bagli yerlesimler "yetim" kalir (Kategoriler sayfasi
 * uyarir). Anahtari bos satir ust baslik degildir.
 *
 * Ust basligin kendi havuz kategorisi (dogrudan ona bagli urunler) slug ile
 * bulunur: anahtarla ayni slug'li kategori, yoksa manifestteki 'parent_pool'
 * (slug ya da ad), yoksa ayni adli kategori. Havuzdaki kategori yeniden
 * adlandirilsa da bag kopmaz.
 *
 * @return array<string, array{key:string, label:string, pool:string, pool_slug:string, row:int}>
 */
function nwcs_site_parents( int $blog_id ): array {
	static $cache = array();

	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	$site = nwcs_catalog_sites()[ $blog_id ] ?? null;

	if ( ! $site ) {
		return array();
	}

	$spec  = (array) ( $site['catalog']['parents'] ?? array() );
	$where = array_values( (array) ( $spec['rows'] ?? array() ) );

	if ( 3 !== count( $where ) ) {
		return array();
	}

	[ $page, $component, $field ] = array_map( 'strval', $where );

	$stored = nwcs_get_all( $blog_id )[ $page ][ $component ][ $field ] ?? null;
	$rows   = is_array( $stored ) && $stored ? $stored : nwcs_field_default( nwcs_manifest_for_theme( (string) get_blog_option( $blog_id, 'stylesheet', '' ) ), $page, $component, $field );
	$rows   = is_array( $rows ) ? array_values( $rows ) : array();

	$key_field   = (string) ( $spec['key'] ?? 'key' );
	$label_field = (string) ( $spec['label'] ?? 'label' );
	$pools       = (array) ( $site['catalog']['parent_pool'] ?? array() );
	$categories  = nwcs_pool_categories();
	$by_name     = array();

	foreach ( $categories as $slug => $term ) {
		$by_name[ $term['name'] ] = (string) $slug;
	}

	$parents = array();

	foreach ( $rows as $index => $row ) {
		$raw = trim( (string) ( $row[ $key_field ] ?? '' ) );

		if ( '' === $raw ) {
			continue;
		}

		$key = sanitize_title( $raw );

		if ( '' === $key || isset( $parents[ $key ] ) ) {
			continue;
		}

		$hint      = (string) ( $pools[ $key ] ?? '' );
		$pool_slug = isset( $categories[ $key ] ) ? $key : ( isset( $categories[ $hint ] ) ? $hint : ( $by_name[ $hint ] ?? $by_name[ $raw ] ?? '' ) );

		$parents[ $key ] = array(
			'key'       => $key,
			'label'     => trim( (string) ( $row[ $label_field ] ?? '' ) ) ?: $raw,
			'pool'      => '' !== $pool_slug ? (string) $categories[ $pool_slug ]['name'] : '',
			'pool_slug' => (string) $pool_slug,
			'row'       => (int) $index,
		);
	}

	$cache[ $blog_id ] = $parents;

	return $parents;
}

/* ====================================================================== *
 * Sitedeki kategori agaci
 * ====================================================================== */

/**
 * Sitenin iki seviyeli agaci: ust basliklar ve altlarina yerlesen havuz
 * kategorileri, bu sitede gosterilen urun sayilariyla. Renk grubu (ayni
 * urunun renkleri) kartta oldugu gibi tek urun sayilir (nwcs_color_count).
 *
 * Alt siralama manifestten ('catalog' => 'order'):
 *   'first_product' - sitedeki urun sirasinda ilk gorunuse gore (WK; urunu
 *                     olmayanlar sonda, varsayilan sirayla);
 *   varsayilan      - 'defaults' anahtarlarinin sirasi, sonra ada gore.
 * Ust basligin sayisi: ust basligin kendi kategorisinde ya da altindaki bir
 * kategoride olan urunler.
 *
 * @return array<string, array{key:string, label:string, pool:string, row:int, count:int, children:array<string, array{slug:string, name:string, count:int}>}>
 */
function nwcs_site_category_tree( int $blog_id, bool $reset = false ): array {
	static $cache = array();

	if ( $reset ) {
		$cache = array();

		return array();
	}

	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	$site     = nwcs_catalog_sites()[ $blog_id ] ?? null;
	$parents  = nwcs_site_parents( $blog_id );
	$tree     = array();

	if ( ! $site || ! $parents ) {
		return $tree;
	}

	$site_key   = $site['site_key'];
	$categories = nwcs_pool_categories();
	$defaults   = array_keys( (array) ( $site['catalog']['defaults'] ?? array() ) );
	$products   = nwcs_site_products( $blog_id );
	$first      = array(); // slug => sitedeki ilk urunun sirasi
	$count      = array(); // slug => kart sayisi (renk grubu tek kart: nwcs_color_count)
	$members    = array(); // slug => kategorideki urunler

	foreach ( $products as $position => $product ) {
		foreach ( array_keys( (array) $product['categories'] ) as $slug ) {
			$first[ $slug ] ??= $position;
			$members[ $slug ][] = $product;
		}
	}

	foreach ( $members as $slug => $list ) {
		$count[ $slug ] = nwcs_color_count( $list, $blog_id );
	}

	foreach ( $parents as $key => $parent ) {
		$tree[ $key ] = $parent + array( 'count' => 0, 'children' => array() );
	}

	foreach ( $categories as $slug => $term ) {
		$parent = $term['placement'][ $site_key ] ?? '';

		if ( '' === $parent || ! isset( $tree[ $parent ] ) || $parent === $slug ) {
			continue;
		}

		$tree[ $parent ]['children'][ $slug ] = array(
			'slug'  => (string) $slug,
			'name'  => (string) $term['name'],
			'count' => (int) ( $count[ $slug ] ?? 0 ),
		);
	}

	$by_first = 'first_product' === ( $site['catalog']['order'] ?? '' );
	$collator = class_exists( 'Collator' ) ? new Collator( 'tr_TR' ) : null;
	$rank     = array_flip( $defaults );

	foreach ( $tree as $key => $parent ) {
		$children = $parent['children'];

		uasort(
			$children,
			static function ( array $a, array $b ) use ( $by_first, $first, $rank, $collator ): int {
				if ( $by_first ) {
					$fa = $first[ $a['slug'] ] ?? PHP_INT_MAX;
					$fb = $first[ $b['slug'] ] ?? PHP_INT_MAX;

					if ( $fa !== $fb ) {
						return $fa <=> $fb;
					}
				}

				$ra = $rank[ $a['slug'] ] ?? PHP_INT_MAX;
				$rb = $rank[ $b['slug'] ] ?? PHP_INT_MAX;

				if ( $ra !== $rb ) {
					return $ra <=> $rb;
				}

				return $collator ? (int) $collator->compare( $a['name'], $b['name'] ) : strcasecmp( $a['name'], $b['name'] );
			}
		);

		$tree[ $key ]['children'] = $children;

		// Ust basligin urunleri: kendi kategorisinde ya da bir alt kategoride.
		$pool_slug = (string) $parent['pool_slug'];
		$within    = array();

		foreach ( $products as $product ) {
			$own = (array) $product['categories'];

			if ( ( '' !== $pool_slug && isset( $own[ $pool_slug ] ) ) || array_intersect_key( $own, $children ) ) {
				$within[] = $product;
			}
		}

		$tree[ $key ]['count'] = nwcs_color_count( $within, $blog_id );
	}

	$cache[ $blog_id ] = $tree;

	return $tree;
}

/**
 * Urunun bu sitedeki yeri: agac sirasiyla ilk eslesen alt kategori; yoksa
 * urun bir ust basligin kendi kategorisindeyse yalnizca o ust baslik.
 *
 * @return array{parent:?string, child:?string}
 */
function nwcs_product_place( array $product, int $blog_id ): array {
	$own = (array) ( $product['categories'] ?? array() );

	foreach ( nwcs_site_category_tree( $blog_id ) as $key => $parent ) {
		foreach ( array_keys( $parent['children'] ) as $slug ) {
			if ( isset( $own[ $slug ] ) ) {
				return array( 'parent' => (string) $key, 'child' => (string) $slug );
			}
		}
	}

	foreach ( nwcs_site_category_tree( $blog_id ) as $key => $parent ) {
		if ( '' !== ( $parent['pool_slug'] ?? '' ) && isset( $own[ $parent['pool_slug'] ] ) ) {
			return array( 'parent' => (string) $key, 'child' => null );
		}
	}

	return array( 'parent' => null, 'child' => null );
}

/* ====================================================================== *
 * Manifest: kategori sayfalari
 * ====================================================================== */

/**
 * Sablondaki {name} {slug} {parent} {parent_name} yer tutucularini doldurur
 * (dizilerde ic ice).
 *
 * @param mixed $value
 * @return mixed
 */
function nwcs_catalog_fill( $value, array $vars ) {
	if ( is_array( $value ) ) {
		return array_map( static fn( $item ) => nwcs_catalog_fill( $item, $vars ), $value );
	}

	if ( ! is_string( $value ) ) {
		return $value;
	}

	$pairs = array();

	foreach ( $vars as $name => $text ) {
		$pairs[ '{' . $name . '}' ] = (string) $text;
	}

	return strtr( $value, $pairs );
}

/**
 * Sablondan tek bir panel sayfasi kurar.
 *
 * @param array  $template 'page' ya da 'parent_page' blogu
 * @param array  $defaults [ alan => varsayilan ]: sablonun ayni adli alanlarina yazilir
 * @param string $pool     'products' alaninin havuz kategori adi; bossa alan yok
 */
function nwcs_catalog_page( array $template, array $vars, array $defaults, string $pool ): array {
	$page = array(
		'label'      => (string) nwcs_catalog_fill( (string) ( $template['label'] ?? '{name}' ), $vars ),
		'path'       => (string) nwcs_catalog_fill( (string) ( $template['path'] ?? '/' ), $vars ),
		'components' => array(),
	);

	if ( ! empty( $template['hidden'] ) ) {
		$page['hidden'] = true;
	}

	if ( ! empty( $template['seo_source'] ) ) {
		$page['seo_source'] = (array) $template['seo_source'];
	}

	foreach ( (array) ( $template['components'] ?? array() ) as $component_key => $component ) {
		$component = nwcs_catalog_fill( (array) $component, $vars );

		foreach ( (array) ( $component['fields'] ?? array() ) as $field_key => $field ) {
			if ( array_key_exists( $field_key, $defaults ) ) {
				$component['fields'][ $field_key ]['default'] = $defaults[ $field_key ];
			}
		}

		$page['components'][ $component_key ] = $component;
	}

	if ( '' !== $pool && ! empty( $template['products_label'] ) ) {
		$page['components']['products'] = array(
			'label'  => 'Ürünler',
			'fields' => array(
				'pool' => array(
					'label'    => (string) nwcs_catalog_fill( (string) $template['products_label'], $vars + array( 'pool' => $pool ) ),
					'type'     => 'products',
					'category' => $pool,
				),
			),
		);
	}

	// Her sayfaya "Arama ve Paylasim" (manifest yuklenirken eklenenle ayni).
	if ( function_exists( 'nwcs_seo_page_component' ) ) {
		$page['components'][ NWCS_SEO_COMPONENT ] = nwcs_seo_page_component();
	}

	return $page;
}

/**
 * Sitenin manifestine yerlesik kategorilerin ve ust basliklarin sayfalarini
 * ekler: 'kat-<havuz-slug>' ve 'seri-<anahtar>' / 'grp-<anahtar>'. Manifestte
 * ayni anahtarla elle tanimlanmis sayfa varsa ona dokunulmaz.
 */
function nwcs_catalog_augment_manifest( array $manifest, int $blog_id ): array {
	$catalog = (array) ( $manifest['catalog'] ?? array() );
	$site    = nwcs_catalog_sites()[ $blog_id ] ?? null;

	if ( ! $catalog || ! $site ) {
		return $manifest;
	}

	$parents    = nwcs_site_parents( $blog_id );
	$categories = nwcs_pool_categories();
	$defaults   = (array) ( $catalog['defaults'] ?? array() );
	$rank       = array_flip( array_keys( $defaults ) );
	$prefix     = (string) ( $catalog['parent_prefix'] ?? 'grp-' );
	$pages      = array();
	$tops       = array();

	// Ust basliklar (satir sirasiyla); kategori sayfalarindan sonra eklenir.
	if ( ! empty( $catalog['parent_page'] ) ) {
		foreach ( $parents as $key => $parent ) {
			$vars = array( 'name' => $parent['label'], 'slug' => $key, 'parent' => $key, 'parent_name' => $parent['label'] );

			$tops[ $prefix . $key ] = nwcs_catalog_page(
				(array) $catalog['parent_page'],
				$vars,
				(array) ( $catalog['parent_defaults'][ $key ] ?? array() ),
				(string) $parent['pool']
			);
		}
	}

	// Kategoriler: varsayilan listedeki sirayla, sonra ada gore.
	$placed = array();

	foreach ( $categories as $slug => $term ) {
		$parent = $term['placement'][ $site['site_key'] ] ?? '';

		if ( '' !== $parent && isset( $parents[ $parent ] ) && $parent !== $slug ) {
			$placed[ $slug ] = $term + array( 'parent' => $parent );
		}
	}

	uksort(
		$placed,
		static fn( $a, $b ): int => ( $rank[ $a ] ?? PHP_INT_MAX ) <=> ( $rank[ $b ] ?? PHP_INT_MAX ) ?: strcmp( (string) $a, (string) $b )
	);

	foreach ( $placed as $slug => $term ) {
		$parent = $parents[ $term['parent'] ];
		$vars   = array( 'name' => $term['name'], 'slug' => $slug, 'parent' => $parent['key'], 'parent_name' => $parent['label'] );
		$own    = (array) ( $defaults[ $slug ] ?? array() );

		$pages[ 'kat-' . $slug ] = nwcs_catalog_page( (array) ( $catalog['page'] ?? array() ), $vars, $own, (string) $term['name'] );
	}

	$pages += $tops;

	// Ayni anahtarda elle tanimli sayfa varsa: kategori sayfasi (kat-) uretilen kazanir
	// (havuzda o slug'la kategori acilip yerlestirildi; kayitli metni ayni anahtarda
	// kalir), ust baslik sayfalarinda elle tanimli kazanir.
	foreach ( array_keys( $pages ) as $key ) {
		if ( isset( $manifest['pages'][ $key ] ) ) {
			if ( str_starts_with( (string) $key, 'kat-' ) ) {
				unset( $manifest['pages'][ $key ] );
			} else {
				unset( $pages[ $key ] );
			}
		}
	}

	$after = (string) ( $catalog['after'] ?? '' );
	$out   = array();

	foreach ( (array) ( $manifest['pages'] ?? array() ) as $key => $page ) {
		$out[ $key ] = $page;

		if ( $key === $after ) {
			$out += $pages;
			$pages = array();
		}
	}

	$manifest['pages'] = $out + $pages;

	return $manifest;
}

/* ====================================================================== *
 * Gorunurluk: yerlesim -> site secimi (yalnizca ekleme; kaldirmada hesapli)
 * ====================================================================== */

/**
 * Urunlerin site secimine eklenmesi ("secilenler" kipi; sonuna, sirasi
 * korunarak). "Hepsi" kipinde bir sey yazilmaz.
 *
 * @param int[] $ids
 * @return int[] Gercekten eklenen kimlikler.
 */
function nwcs_selection_append( int $blog_id, array $ids ): array {
	$settings = nwcs_site_product_settings( $blog_id );

	if ( 'selected' !== $settings['mode'] || ! $ids ) {
		return array();
	}

	$add = array_values( array_diff( array_unique( array_map( 'intval', $ids ) ), $settings['selected'] ) );

	if ( $add ) {
		switch_to_blog( $blog_id );
		update_option( NWCS_OPTION_SELECTED, array_values( array_merge( $settings['selected'], $add ) ) );
		restore_current_blog();
	}

	return $add;
}

/**
 * Urunlerin site seciminden cikarilmasi.
 *
 * @param int[] $ids
 * @return int[] Cikarilanlar.
 */
function nwcs_selection_remove( int $blog_id, array $ids ): array {
	$settings = nwcs_site_product_settings( $blog_id );

	if ( 'selected' !== $settings['mode'] || ! $ids ) {
		return array();
	}

	$ids    = array_map( 'intval', $ids );
	$remove = array_values( array_intersect( $settings['selected'], $ids ) );

	if ( $remove ) {
		switch_to_blog( $blog_id );
		update_option( NWCS_OPTION_SELECTED, array_values( array_diff( $settings['selected'], $remove ) ) );
		restore_current_blog();
	}

	return $remove;
}

/**
 * Bir kategorinin yayindaki urun kimlikleri (havuz sirasiyla).
 *
 * @return int[]
 */
function nwcs_category_product_ids( string $slug ): array {
	$ids = array();

	foreach ( nwcs_pool_products() as $id => $product ) {
		if ( isset( $product['categories'][ $slug ] ) ) {
			$ids[] = (int) $id;
		}
	}

	return $ids;
}

/**
 * Yerlesim kaldirilirsa o siteden cikacak urunler: kategorideki, sitede
 * secili olan ve o sitede baska yerlesik kategorisi olmayan urunler.
 *
 * @return int[]
 */
function nwcs_placement_orphans( string $slug, int $blog_id ): array {
	$site_key = nwcs_catalog_site_key( $blog_id );
	$settings = nwcs_site_product_settings( $blog_id );

	if ( '' === $site_key || 'selected' !== $settings['mode'] ) {
		return array();
	}

	$categories = nwcs_pool_categories();
	$pool       = nwcs_pool_products();
	$out        = array();

	foreach ( nwcs_category_product_ids( $slug ) as $id ) {
		if ( ! in_array( $id, $settings['selected'], true ) ) {
			continue;
		}

		$elsewhere = false;

		foreach ( array_keys( (array) $pool[ $id ]['categories'] ) as $other ) {
			if ( $other !== $slug && '' !== ( $categories[ $other ]['placement'][ $site_key ] ?? '' ) ) {
				$elsewhere = true;
				break;
			}
		}

		if ( ! $elsewhere ) {
			$out[] = $id;
		}
	}

	return $out;
}

/**
 * Kategorinin butun sitelerdeki yerlesimini ister gibi yapar ve site
 * secimlerini buna gore gunceller:
 *   - yeni yerlesen sitede kategorinin urunleri secimin sonuna eklenir;
 *   - yerlesimi kalkan sitede, orada baska yerlesik kategorisi olmayan
 *     urunler secimden cikar.
 * Geri alma icin her sitenin onceki ve sonraki secimi kaydedilir.
 *
 * @param array<int, ?string> $wanted blog_id => ust baslik (null: gosterme)
 * @return array{changed:bool, before:array, after:array, sites:array<int, array{before:int[], after:int[], added:int[], removed:int[]}>}
 */
function nwcs_placement_update( string $slug, array $wanted ): array {
	$categories = nwcs_pool_categories();
	$result     = array( 'changed' => false, 'before' => array(), 'after' => array(), 'sites' => array() );

	if ( ! isset( $categories[ $slug ] ) ) {
		return $result;
	}

	$result['before'] = $categories[ $slug ]['placement'];

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		if ( ! array_key_exists( $blog_id, $wanted ) ) {
			continue;
		}

		$parent  = $wanted[ $blog_id ];
		$parents = nwcs_site_parents( (int) $blog_id );
		$was     = $categories[ $slug ]['placement'][ $site['site_key'] ] ?? '';

		// Sitede artik olmayan bir baslik gelirse (panel satiri silinmis/degismis)
		// hicbir sey yapilmaz: kategori sessizce baska yere tasinmasin ya da kalkmasin.
		if ( null !== $parent && ! isset( $parents[ $parent ] ) ) {
			continue;
		}

		// Bir sitenin ust baslik kategorisi (WOODPets, Dekorasyon...) o sitede yerlesmez.
		if ( null !== $parent && nwcs_is_parent_category( $slug, (int) $blog_id ) ) {
			continue;
		}

		if ( ( $was ?: null ) === $parent ) {
			continue;
		}

		$before = nwcs_site_product_settings( (int) $blog_id )['selected'];
		$added  = array();
		$gone   = array();

		// Cikacaklar yerlesim kaldirilmadan hesaplanir (kategori hala yerlesik).
		if ( null === $parent ) {
			$gone = nwcs_placement_orphans( $slug, (int) $blog_id );
		}

		nwcs_set_placement( $slug, $site['site_key'], $parent );

		if ( null === $parent ) {
			$gone = nwcs_selection_remove( (int) $blog_id, $gone );
		} elseif ( '' === $was ) {
			$added = nwcs_selection_append( (int) $blog_id, nwcs_category_product_ids( $slug ) );
		}

		$result['changed']           = true;
		$result['sites'][ $blog_id ] = array(
			'before'  => $before,
			'after'   => nwcs_site_product_settings( (int) $blog_id )['selected'],
			'added'   => $added,
			'removed' => $gone,
		);
	}

	$result['after'] = nwcs_pool_categories()[ $slug ]['placement'] ?? array();

	if ( $result['changed'] ) {
		nwcs_pool_flush_cache();
	}

	return $result;
}

/**
 * Yeni ya da kategoriye yeni eklenen urunleri, kategorinin yerlestigi
 * sitelerin secimine ekler. Geri alma icin eklenenleri dondurur.
 *
 * @param int[]    $ids
 * @param string[] $slugs Urunlerin (yeni) kategorileri
 * @return array<int, int[]> blog_id => eklenen kimlikler
 */
function nwcs_placement_reveal( array $ids, array $slugs ): array {
	$categories = nwcs_pool_categories();
	$out        = array();

	if ( ! $ids ) {
		return $out;
	}

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$placed = false;

		foreach ( $slugs as $slug ) {
			if ( '' !== ( $categories[ $slug ]['placement'][ $site['site_key'] ] ?? '' ) ) {
				$placed = true;
				break;
			}
		}

		if ( ! $placed ) {
			continue;
		}

		$added = nwcs_selection_append( (int) $blog_id, $ids );

		if ( $added ) {
			$out[ (int) $blog_id ] = $added;
		}
	}

	return $out;
}

/**
 * Kategorinin yerlestigi siteler, okunur: [ blog_id => "WOOD KOCIST (WOODGarden)" ].
 */
function nwcs_placement_labels( string $slug ): array {
	$placement = nwcs_pool_categories()[ $slug ]['placement'] ?? array();
	$out       = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$parent = $placement[ $site['site_key'] ] ?? '';

		if ( '' === $parent ) {
			continue;
		}

		$parents          = nwcs_site_parents( (int) $blog_id );
		$out[ $blog_id ] = sprintf( '%s (%s)', $site['label'], $parents[ $parent ]['label'] ?? $parent );
	}

	return $out;
}

/* ====================================================================== *
 * Tek seferlik tohum (eski sabit listelerden yerlesim)
 * ====================================================================== */

/**
 * Temalarin manifestindeki 'seed' listesinden yerlesim yazar. Yalnizca bos
 * yerlesimi doldurur (idempotent); site secimlerine dokunmaz. Kocist'te slug'i
 * degisen dort kategorinin kayitli sayfa icerigi ('kat-<eski>') yeni anahtara
 * kopyalanir ('legacy').
 *
 * Her site anahtari bir kez tohumlanir (site option: site_key => surum);
 * sonradan 'catalog' kazanan tema da kendi sirasi gelince tohumlanir.
 *
 * @return array{placed:int, copied:int}
 */
function nwcs_placement_seed( bool $force = false ): array {
	$report = array( 'placed' => 0, 'copied' => 0 );

	if ( ! taxonomy_exists( NWCS_PRODUCT_TAX ) ) {
		return $report;
	}

	$done  = get_site_option( NWCS_PLACEMENT_SEED, array() );
	$done  = is_array( $done ) ? $done : array();
	$sites = array_filter(
		nwcs_catalog_sites(),
		static fn( array $site ): bool => $force || (int) ( $done[ $site['site_key'] ] ?? 0 ) < NWCS_PLACEMENT_SEED_VER
	);

	if ( ! $sites ) {
		return $report;
	}

	// Once isaret: ayni anda gelen iki istek tohumu iki kez calistirmasin.
	foreach ( $sites as $site ) {
		$done[ $site['site_key'] ] = NWCS_PLACEMENT_SEED_VER;
	}

	update_site_option( NWCS_PLACEMENT_SEED, $done );

	$categories = nwcs_pool_categories();

	foreach ( $sites as $blog_id => $site ) {
		foreach ( (array) ( $site['catalog']['seed'] ?? array() ) as $slug => $parent ) {
			if ( isset( $categories[ $slug ] ) && '' === ( $categories[ $slug ]['placement'][ $site['site_key'] ] ?? '' ) ) {
				if ( nwcs_set_placement( (string) $slug, $site['site_key'], (string) $parent ) ) {
					++$report['placed'];
					$categories = nwcs_pool_categories();
				}
			}
		}

		// Eski sayfa anahtarlarinin kayitli icerigi yeni anahtara.
		$legacy = (array) ( $site['catalog']['legacy'] ?? array() );

		if ( $legacy ) {
			$data    = nwcs_get_all( (int) $blog_id );
			$changed = false;

			foreach ( $legacy as $old => $new ) {
				if ( isset( $data[ 'kat-' . $old ] ) && ! isset( $data[ 'kat-' . $new ] ) ) {
					$data[ 'kat-' . $new ] = $data[ 'kat-' . $old ];
					$changed               = true;
					++$report['copied'];
				}
			}

			if ( $changed ) {
				update_blog_option( (int) $blog_id, NWCS_OPTION_CONTENT, $data );
			}
		}
	}

	nwcs_pool_categories_flush();

	return $report;
}

/*
 * init'te (taksonomi kayitli): canliya dagitimdan sonraki ilk istekte kategori
 * sayfalari bos kalmasin diye yonetici girisi beklenmez. Ucuz on denetim:
 * yalnizca temasinda 'catalog' olan sitenin istegi ve o sitenin anahtari
 * tohumlanmamissa calisir (havuzu tuketmeyen sitelerde manifestler taranmaz).
 * Kategoriler sayfasi da acilirken cagirir.
 */
add_action( 'init', 'nwcs_placement_seed_maybe', 30 );
function nwcs_placement_seed_maybe(): void {
	$manifest = nwcs_manifest_for_theme( (string) get_option( 'stylesheet' ) );
	$site_key = sanitize_key( (string) ( $manifest['site_key'] ?? '' ) );

	if ( empty( $manifest['catalog'] ) || '' === $site_key ) {
		return;
	}

	$done = get_site_option( NWCS_PLACEMENT_SEED, array() );

	if ( is_array( $done ) && (int) ( $done[ $site_key ] ?? 0 ) >= NWCS_PLACEMENT_SEED_VER ) {
		return;
	}

	nwcs_placement_seed();
}

/**
 * Kategori bu sitede bir ust basligin kendi kategorisi mi (WOODPets,
 * Dekorasyon, Ahşap Ambalaj...)? Oyle ise o sitede baska basligin altina
 * yerlesmez; urunleri o basligin sayfasinda gorunur.
 */
function nwcs_is_parent_category( string $slug, int $blog_id ): bool {
	foreach ( nwcs_site_parents( $blog_id ) as $parent ) {
		if ( $parent['pool_slug'] === $slug ) {
			return true;
		}
	}

	return false;
}

/**
 * Ust baslik kategorileri: slug => [ blog_id => [ site, baslik ] ].
 *
 * @return array<string, array<int, array{site:string, label:string}>>
 */
function nwcs_parent_categories(): array {
	$out = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		foreach ( nwcs_site_parents( (int) $blog_id ) as $parent ) {
			if ( '' !== $parent['pool_slug'] ) {
				$out[ $parent['pool_slug'] ][ (int) $blog_id ] = array( 'site' => $site['label'], 'label' => $parent['label'] );
			}
		}
	}

	return $out;
}

/**
 * Yetim yerlesimler: sitede artik olmayan bir basliga bagli kategoriler
 * (seri/grup satirinin anahtari degisti ya da satir silindi). Bu kategoriler
 * o sitede gorunmez.
 *
 * @return array<string, array<int, string>> slug => [ blog_id => eski anahtar ]
 */
function nwcs_orphan_placements(): array {
	$out = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$parents = nwcs_site_parents( (int) $blog_id );

		foreach ( nwcs_pool_categories() as $slug => $term ) {
			$key = $term['placement'][ $site['site_key'] ] ?? '';

			if ( '' !== $key && ! isset( $parents[ $key ] ) ) {
				$out[ $slug ][ (int) $blog_id ] = $key;
			}
		}
	}

	return $out;
}
