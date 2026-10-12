<?php
/**
 * Merkezi urun havuzu.
 *
 * Urunler agin ana sitesinde (Ag Yonetimi'nin bagli oldugu site) tek bir yerde
 * tutulur; alt siteler onlari kopyalamaz, referansla gosterir. Gorseller de ayni
 * yerin WordPress medya kitapliginda durur.
 *
 * Her alt site kendi option'inda yalnizca su bilgiyi tutar:
 *   nwcs_products_mode      'all' | 'selected'
 *   nwcs_products_selected  [ urun_id, ... ]   (sira = gosterim sirasi)
 *   nwcs_products_overrides [ urun_id => [ title, short, price, image, hidden, slug, slug_old ] ]
 *
 * Adres kurali: urun sayfasi /urun/<slug>/. slug bos ise havuzdaki adres
 * (post_name) kullanilir; doluysa yalnizca o sitede ozel adres gecerlidir.
 * slug_old o sitede daha once kullanilmis adreslerdir; havuz adresi ile
 * birlikte guncel adrese 301 ile yonlenir (nwcs_product_template).
 *
 * Fiyat kurali: fiyat serbest metindir. Bos birakilirsa site fiyat yerine
 * "Teklif al" gosterir. Kural hem havuz degeri hem site istisnasi icin gecerlidir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PRODUCT_TYPE     = 'nwcs_product';
const NWCS_PRODUCT_TAX      = 'nwcs_product_cat';
const NWCS_OPTION_MODE      = 'nwcs_products_mode';
const NWCS_OPTION_SELECTED  = 'nwcs_products_selected';
const NWCS_OPTION_OVERRIDES = 'nwcs_products_overrides';

/**
 * Havuzun bulundugu site (agin ana sitesi).
 */
function nwcs_pool_blog_id(): int {
	return (int) get_main_site_id();
}

add_action( 'init', 'nwcs_register_product_type' );
function nwcs_register_product_type(): void {
	// Kayit her sitede yapilir; kayitlar yalnizca havuz sitesinde bulunur.
	// Boylece switch_to_blog ile havuza gecildiginde sorgu calisir.
	register_post_type(
		NWCS_PRODUCT_TYPE,
		array(
			'labels'          => array(
				'name'          => 'Ürünler',
				'singular_name' => 'Ürün',
			),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'hierarchical'    => false,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'capability_type' => 'post',
		)
	);

	register_taxonomy(
		NWCS_PRODUCT_TAX,
		NWCS_PRODUCT_TYPE,
		array(
			'labels'       => array(
				'name'          => 'Ürün Kategorileri',
				'singular_name' => 'Ürün Kategorisi',
			),
			'public'       => false,
			'show_ui'      => false,
			'hierarchical' => true,
			'rewrite'      => false,
		)
	);
}

/* ------------------------------------------------------------------ */
/* Havuz okuma                                                          */
/* ------------------------------------------------------------------ */

// Anahtar surumlu: urun verisinin sekli degistiginde eski onbellek kendiliginden
// gecersiz olur, elle temizlemek gerekmez.
const NWCS_POOL_CACHE_KEY = 'nwcs_pool_products_v5';

/**
 * Havuz onbellegini temizler. Urun/gorsel degisikliklerinden sonra cagrilir.
 */
function nwcs_pool_flush_cache(): void {
	// Sitelerin sayfa onbellegi (LiteSpeed, Cloudflare) istek sonunda: includes/cache-purge.php.
	nwcs_cache_mark_dirty();

	switch_to_blog( nwcs_pool_blog_id() );
	delete_transient( NWCS_POOL_CACHE_KEY );
	restore_current_blog();

	nwcs_pool_products( true );

	// Kategori sayilari (cope atma, geri getirme) ve kategori agaclari da.
	if ( function_exists( 'nwcs_pool_categories_flush' ) ) {
		nwcs_pool_categories_flush();
	}
}

/**
 * Havuzdaki tum urunler (siralama: menu_order, sonra baslik).
 *
 * Her sayfa ciziminde havuza gecmemek icin sonuc hem istek ici degiskende hem
 * de transient'ta tutulur; yazma islemleri onbellegi temizler.
 *
 * @return array<int, array<string, mixed>>
 */
function nwcs_pool_products( bool $reset = false ): array {
	static $cache = null;

	if ( $reset ) {
		$cache = null;

		return array();
	}

	if ( null !== $cache ) {
		return $cache;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$stored = get_transient( NWCS_POOL_CACHE_KEY );

	if ( is_array( $stored ) ) {
		restore_current_blog();
		$cache = $stored;

		return $cache;
	}

	$posts = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'publish',
			// Sinirsiz: sinir asilinca fazlasi hicbir sitede gorunmuyor, secimlerden de dusuyordu.
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);

	$products = array();

	foreach ( $posts as $post ) {
		$products[ (int) $post->ID ] = nwcs_pool_product_data( $post );
	}

	set_transient( NWCS_POOL_CACHE_KEY, $products, HOUR_IN_SECONDS );

	restore_current_blog();

	$cache = $products;

	return $products;
}

/**
 * Cop kutusundaki havuz urunlerinin kimlikleri.
 *
 * @return int[]
 */
function nwcs_pool_trashed_ids(): array {
	switch_to_blog( nwcs_pool_blog_id() );

	$ids = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'trash',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	restore_current_blog();

	return array_map( 'intval', $ids );
}

/**
 * Aramada Turkce harf ve buyuk/kucuk harf farki gozetilmesin diye metni
 * sadelestirir: "ÇİVİ", "çivi", "civi" ayni sonucu verir.
 */
function nwcs_search_fold( string $text ): string {
	$text = mb_strtolower( strtr( $text, array( 'İ' => 'i', 'I' => 'ı' ) ), 'UTF-8' );

	return strtr( $text, array( 'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u' ) );
}

/**
 * Yonetim listesi icin filtrelenmis/sayfalanmis sorgu.
 *
 * @return array{items: array<int, array>, total: int, pages: int, page: int}
 */
function nwcs_pool_query( array $args = array() ): array {
	$search   = trim( (string) ( $args['search'] ?? '' ) );
	$category = trim( (string) ( $args['category'] ?? '' ) );
	$missing  = trim( (string) ( $args['missing'] ?? '' ) );
	$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
	$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );

	$all = nwcs_pool_products();

	$filtered = array_filter(
		$all,
		static function ( array $product ) use ( $search, $category, $missing ): bool {
			if ( '' !== $category && ! isset( $product['categories'][ $category ] ) ) {
				return false;
			}

			// Eksik suzgeci: fotograf / aciklama / detay ya da hicbir sitede gorunmeyen.
			if ( '' !== $missing ) {
				$gone = 'nowhere' === $missing
					? ! nwcs_product_site_labels( (int) $product['id'] )
					: in_array( $missing, nwcs_product_gaps( $product ), true );

				if ( ! $gone ) {
					return false;
				}
			}

			if ( '' === $search ) {
				return true;
			}

			$haystack = nwcs_search_fold( $product['title'] . ' ' . $product['code'] . ' ' . $product['short'] . ' ' . $product['spec'] . ' ' . implode( ' ', $product['categories'] ) );

			// Her kelime bir yerde gecmeli; sira onemsiz ("kamelya 3x3" = "3x3 kamelya").
			foreach ( preg_split( '/\s+/u', nwcs_search_fold( $search ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
				if ( ! str_contains( $haystack, $word ) ) {
					return false;
				}
			}

			return true;
		}
	);

	$total = count( $filtered );
	$pages = max( 1, (int) ceil( $total / $per_page ) );
	$page  = min( $page, $pages );

	return array(
		'items' => array_slice( $filtered, ( $page - 1 ) * $per_page, $per_page, true ),
		'total' => $total,
		'pages' => $pages,
		'page'  => $page,
	);
}

/**
 * Urunun eksikleri: 'photo' (gorsel yok), 'body' (uzun aciklama yok),
 * 'details' (teknik detay yok; serbest not varsa dolu sayilir). Ornek
 * gorsel (ornek-urun-*.png) fotograf sayilir. Liste, form ve Studyo ayni
 * kurali kullanir.
 *
 * @return string[]
 */
function nwcs_product_gaps( array $product ): array {
	$gaps = array();

	if ( empty( $product['images'] ) ) {
		$gaps[] = 'photo';
	}

	if ( '' === trim( wp_strip_all_tags( (string) ( $product['body'] ?? '' ) ) ) ) {
		$gaps[] = 'body';
	}

	if ( empty( $product['details'] ) && '' === trim( (string) ( $product['spec'] ?? '' ) ) ) {
		$gaps[] = 'details';
	}

	return $gaps;
}

/**
 * Eksiklerin panel adlari.
 *
 * @return array<string, string>
 */
function nwcs_product_gap_labels(): array {
	return array(
		'photo'   => 'fotoğraf',
		'body'    => 'açıklama',
		'details' => 'detay',
	);
}

/*
 * Havuzdaki kategoriler (yerlesimleriyle): nwcs_pool_categories(),
 * includes/product-categories.php.
 */

/**
 * Tek urunun havuzdaki hali. Havuz baglaminda cagrilmalidir.
 */
function nwcs_pool_product_data( WP_Post $post ): array {
	// Galeri: sirali ek kimlikleri. Ilki kart gorseli olarak kullanilir.
	$gallery = get_post_meta( $post->ID, '_nwcs_gallery', true );
	$gallery = is_array( $gallery ) ? array_values( array_filter( array_map( 'absint', $gallery ) ) ) : array();

	// Eski kayitlarla uyum: galeri bossa one cikan gorsel kullanilir.
	if ( ! $gallery ) {
		$thumb = (int) get_post_thumbnail_id( $post->ID );

		if ( $thumb ) {
			$gallery = array( $thumb );
		}
	}

	$images = array();
	foreach ( $gallery as $attachment_id ) {
		$images[] = nwcs_image_by_id( $attachment_id, 'medium_large' );
	}

	$categories = array();
	foreach ( wp_get_object_terms( $post->ID, NWCS_PRODUCT_TAX ) as $term ) {
		$categories[ $term->slug ] = $term->name;
	}

	return array(
		'id'          => (int) $post->ID,
		'slug'        => $post->post_name,
		'code'        => (string) get_post_meta( $post->ID, '_nwcs_code', true ),
		'title'       => $post->post_title,
		'short'       => (string) get_post_meta( $post->ID, '_nwcs_short', true ),
		'body'        => $post->post_content,
		'price'       => (string) get_post_meta( $post->ID, '_nwcs_price', true ),
		'spec'        => (string) get_post_meta( $post->ID, '_nwcs_spec', true ),
		'details'     => nwcs_product_details( (int) $post->ID ),
		'image_id'    => $gallery ? (int) $gallery[0] : 0,
		'image'       => $images ? $images[0] : nwcs_image_by_id( 0 ),
		'gallery_ids' => $gallery,
		'images'      => $images,
		'categories'  => $categories,
		'tables'      => nwcs_sanitize_product_tables( (array) get_post_meta( $post->ID, '_nwcs_tables', true ) ),
		'video'       => nwcs_video_by_id( (int) get_post_meta( $post->ID, '_nwcs_video', true ) ),
		'order'       => (int) $post->menu_order,
	);
}

/**
 * Havuzdaki gorselleri (medya kitapligi) listeler.
 */
function nwcs_pool_media(): array {
	switch_to_blog( nwcs_pool_blog_id() );

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$media = array();

	foreach ( $attachments as $attachment ) {
		$src = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' );

		$media[ (int) $attachment->ID ] = array(
			'id'    => (int) $attachment->ID,
			'title' => $attachment->post_title ? $attachment->post_title : ( '#' . $attachment->ID ),
			'thumb' => $src ? $src[0] : '',
			'alt'   => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		);
	}

	restore_current_blog();

	return $media;
}

/* ------------------------------------------------------------------ */
/* Site ayarlari                                                        */
/* ------------------------------------------------------------------ */

/**
 * Sitenin urun ayarlari.
 *
 * @return array{mode:string, selected:int[], overrides:array<int, array>}
 */
/**
 * Sitenin temasi havuz urunlerini basabiliyor mu?
 *
 * Manifestte 'products' turunde bir alan yoksa o sitede urunleri gosterecek
 * hicbir yer yoktur; havuz ozetlerinde "gorunur" saymak yaniltici olur.
 */
/**
 * Urun kodunu bicime sokar: buyuk harf, rakam ve tire.
 */
function nwcs_normalize_product_code( string $code ): string {
	$code = strtoupper( trim( $code ) );
	$code = preg_replace( '/[^A-Z0-9\-_.]/', '-', $code ) ?? '';
	$code = preg_replace( '/-+/', '-', $code ) ?? '';

	return trim( $code, '-' );
}

/**
 * Bu kod baska bir urunde kullaniliyor mu? Kullaniliyorsa o urunun kimligi.
 * Havuz sitesi baglaminda cagrilmalidir.
 *
 * Cop kutusundaki urunler de aranir ('any' onlari kapsamaz): aksi halde cope
 * atilmis bir urunun kodu yeniden gelince ayni kodla ikinci urun acilirdi.
 */
function nwcs_product_id_by_code( string $code, int $ignore_id = 0 ): int {
	$code = nwcs_normalize_product_code( $code );

	if ( '' === $code ) {
		return 0;
	}

	$found = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'exclude'        => $ignore_id ? array( $ignore_id ) : array(),
			'meta_key'       => '_nwcs_code', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $code, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	return $found ? (int) $found[0] : 0;
}

/**
 * Urunun kodunu dondurur; yoksa kimliginden bir tane uretip kaydeder.
 * Her urunun kendine ait, degismeyen bir kodu olsun diye.
 */
function nwcs_ensure_product_code( int $id ): string {
	$code = nwcs_normalize_product_code( (string) get_post_meta( $id, '_nwcs_code', true ) );

	if ( '' !== $code ) {
		return $code;
	}

	$code = 'URN-' . str_pad( (string) $id, 4, '0', STR_PAD_LEFT );
	update_post_meta( $id, '_nwcs_code', $code );

	return $code;
}

function nwcs_site_supports_products( int $blog_id ): bool {
	static $cache = array();

	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	$supports = false;
	$manifest = nwcs_manifest_for_blog( $blog_id );

	foreach ( $manifest['pages'] ?? array() as $page ) {
		foreach ( $page['components'] ?? array() as $component ) {
			foreach ( $component['fields'] ?? array() as $field ) {
				if ( 'products' === ( $field['type'] ?? '' ) ) {
					$supports = true;
					break 3;
				}
			}
		}
	}

	$cache[ $blog_id ] = $supports;

	return $supports;
}

function nwcs_site_product_settings( ?int $blog_id = null ): array {
	$read = static function ( string $option, $default ) use ( $blog_id ) {
		return null === $blog_id
			? get_option( $option, $default )
			: get_blog_option( $blog_id, $option, $default );
	};

	// Kayit yoksa 'selected' kabul edilir: hic secim yapilmamis bir site,
	// kimse istemeden havuzdaki her urunu gostermeye baslamasin.
	$mode = $read( NWCS_OPTION_MODE, 'selected' );

	$selected = $read( NWCS_OPTION_SELECTED, array() );
	$selected = is_array( $selected ) ? array_values( array_map( 'absint', $selected ) ) : array();

	$overrides = $read( NWCS_OPTION_OVERRIDES, array() );
	$overrides = is_array( $overrides ) ? $overrides : array();

	return array(
		'mode'      => in_array( $mode, array( 'all', 'selected' ), true ) ? $mode : 'all',
		'selected'  => $selected,
		'overrides' => $overrides,
	);
}

/**
 * Site ayarlarini yazar. Aktif site baglaminda cagrilir.
 */
function nwcs_save_site_product_settings( array $settings ): void {
	// Cop kutusundaki urunler de gecerli: secimde yerinde kalir, geri
	// getirilince sitede eski yerine doner (nwcs_pool_products yalnizca yayindakiler).
	$pool = nwcs_pool_products() + array_fill_keys( nwcs_pool_trashed_ids(), true );

	$mode = in_array( $settings['mode'] ?? 'all', array( 'all', 'selected' ), true ) ? $settings['mode'] : 'all';

	$selected = array();
	foreach ( (array) ( $settings['selected'] ?? array() ) as $id ) {
		$id = absint( $id );
		if ( $id && isset( $pool[ $id ] ) && ! in_array( $id, $selected, true ) ) {
			$selected[] = $id;
		}
	}

	// Paneldeki urun secicisi cop kutusundakileri gostermez, gonderilen listede
	// olmazlar. Onceki secimdeki cop kutusu urunleri eski yerlerine (onceki
	// komsusunun arkasina) geri konur; boylece geri getirilen urun sitede yerini bulur.
	$trashed  = nwcs_pool_trashed_ids();
	$previous = get_option( NWCS_OPTION_SELECTED, array() );
	$previous = is_array( $previous ) ? array_values( array_map( 'absint', $previous ) ) : array();

	foreach ( $previous as $index => $id ) {
		if ( ! in_array( $id, $trashed, true ) || in_array( $id, $selected, true ) ) {
			continue;
		}

		$at = 0;

		for ( $back = $index - 1; $back >= 0; $back-- ) {
			$found = array_search( $previous[ $back ], $selected, true );

			if ( false !== $found ) {
				$at = $found + 1;
				break;
			}
		}

		array_splice( $selected, $at, 0, array( $id ) );
	}

	$overrides = array();
	foreach ( (array) ( $settings['overrides'] ?? array() ) as $id => $row ) {
		$id = absint( $id );

		if ( ! $id || ! isset( $pool[ $id ] ) || ! is_array( $row ) ) {
			continue;
		}

		$clean = array(
			'title'  => nwcs_clean_text( $row['title'] ?? '' ),
			'short'  => nwcs_clean_text( $row['short'] ?? '', true ),
			'price'  => nwcs_clean_text( $row['price'] ?? '' ),
			'image'  => absint( $row['image'] ?? 0 ),
			'hidden' => ! empty( $row['hidden'] ) ? 1 : 0,
			// Fiyatin bilincli olarak bos birakildigini, "hic dokunulmadi"dan
			// ayirt edebilmek icin ayri bir bayrak tutuyoruz.
			'price_override' => ! empty( $row['price_override'] ) ? 1 : 0,
		);

		if ( '' === $clean['title'] && '' === $clean['short'] && '' === $clean['price']
			&& 0 === $clean['image'] && 0 === $clean['hidden'] && 0 === $clean['price_override'] ) {
			continue; // Bos istisna saklanmaz.
		}

		$overrides[ $id ] = $clean;
	}

	// Sayfa adresi (slug, slug_old) yalnizca Urun Havuzu -> Ozellestirmeler'den
	// yazilir (includes/admin/overrides.php). Buraya gelen ayar dizileri (Icerik
	// Studyosu, toplu islemler) onu tasimaz; kayitli deger aynen korunur.
	$stored = get_option( NWCS_OPTION_OVERRIDES, array() );

	foreach ( is_array( $stored ) ? $stored : array() as $id => $row ) {
		$id = absint( $id );

		if ( ! $id || ! isset( $pool[ $id ] ) || ! is_array( $row ) ) {
			continue;
		}

		$address = array_intersect_key( $row, array_flip( array( 'slug', 'slug_old' ) ) );
		$address = array_filter( $address, static fn( $value ): bool => '' !== $value && array() !== $value && null !== $value );

		if ( $address ) {
			$overrides[ $id ] = array_merge( $overrides[ $id ] ?? array(), $address );
		}
	}

	update_option( NWCS_OPTION_MODE, $mode );
	update_option( NWCS_OPTION_SELECTED, $selected );
	update_option( NWCS_OPTION_OVERRIDES, $overrides );
}

/**
 * Havuzda bu kategoride (adiyla) olan urunlerin kimlikleri, havuz sirasiyla.
 *
 * @return int[]
 */
function nwcs_pool_ids_in_category( string $category ): array {
	$ids = array();

	foreach ( nwcs_pool_products() as $id => $product ) {
		if ( in_array( $category, (array) $product['categories'], true ) ) {
			$ids[] = (int) $id;
		}
	}

	return $ids;
}

/**
 * Tek kategorinin urun listesi kaydedilirken (panelde kategori sayfasi)
 * sitenin geri kalan secimi korunur: diger urunlerin secimi, sirasi ve
 * istisnalari degismez; kategorinin urunleri listedeki yerlerinde yeni
 * sirayla durur, yeni isaretlenenler sona eklenir.
 *
 * "Hepsi" kipindeki site, kategori listesi olduğu gibi kaydedilirse o kipte
 * kalir; bir urun cikarilir ya da sira degisirse "Secilenler" kipine gecer
 * (havuzun geri kalani secili kalir).
 *
 * @param array  $current nwcs_site_product_settings()
 * @param array  $posted  nwcs_posted_products()
 * @return array Kaydedilecek ayarlar (nwcs_save_site_product_settings'e).
 */
function nwcs_merge_scoped_products( array $current, array $posted, string $category ): array {
	$scope  = nwcs_pool_ids_in_category( $category );
	$picked = array();

	foreach ( (array) ( $posted['selected'] ?? array() ) as $id ) {
		$id = absint( $id );

		if ( in_array( $id, $scope, true ) && ! in_array( $id, $picked, true ) ) {
			$picked[] = $id;
		}
	}

	$base = 'all' === $current['mode'] ? array_map( 'intval', array_keys( nwcs_pool_products() ) ) : $current['selected'];

	// Kategorinin mevcut sirasi (tabandaki yerleriyle).
	$before = array_values( array_filter( $base, static fn( int $id ): bool => in_array( $id, $scope, true ) ) );
	$mode   = ( 'all' === $current['mode'] && $picked === $before ) ? 'all' : 'selected';

	$queue    = $picked;
	$selected = array();

	foreach ( $base as $id ) {
		if ( ! in_array( $id, $scope, true ) ) {
			$selected[] = $id;
		} elseif ( $queue ) {
			$selected[] = array_shift( $queue );
		}
	}

	$selected = array_merge( $selected, $queue );

	// Istisnalar: kategori disindakiler aynen, kategoridekiler formdan.
	$overrides = array();

	foreach ( $current['overrides'] as $id => $row ) {
		if ( ! in_array( (int) $id, $scope, true ) ) {
			$overrides[ $id ] = $row;
		}
	}

	foreach ( (array) ( $posted['overrides'] ?? array() ) as $id => $row ) {
		if ( in_array( absint( $id ), $scope, true ) ) {
			$overrides[ absint( $id ) ] = $row;
		}
	}

	return array(
		'mode'      => $mode,
		'selected'  => 'all' === $mode ? $current['selected'] : $selected,
		'overrides' => $overrides,
	);
}

/* ------------------------------------------------------------------ */
/* Sitede gosterilecek urunler                                          */
/* ------------------------------------------------------------------ */

/**
 * Sitenin temasi urun tablolarini gosteriyor mu (manifestte 'product_tables').
 * Urun Havuzu'ndaki "Ürün Tabloları" bolumu yalnizca bu sitelerde gorunen
 * urunler icin acilir.
 */
function nwcs_site_supports_product_tables( int $blog_id ): bool {
	return ! empty( nwcs_manifest_for_blog( $blog_id )['product_tables'] );
}

/**
 * Urun tablolarini temizler (kayit ve okuma icin ayni kural).
 *
 * Her tablo: title, head (sutun basliklari), rows (hucre dizileri). Tamamen
 * bos satirlar ve basligi da hucreleri de bos sutunlar atilir; hicbir
 * icerigi kalmayan tablo dusurulur. Sinirlar asiri buyuk girisi keser.
 *
 * @return array<int, array{title:string, head:string[], rows:array<int, string[]>}>
 */
function nwcs_sanitize_product_tables( array $raw ): array {
	$clean = static fn( $cell ): string => nwcs_clean_text( $cell );
	$out   = array();

	foreach ( array_slice( $raw, 0, 20 ) as $table ) {
		if ( ! is_array( $table ) ) {
			continue;
		}

		$title = $clean( $table['title'] ?? '' );
		$head  = array_map( $clean, array_slice( array_values( (array) ( $table['head'] ?? array() ) ), 0, 30 ) );
		$rows  = array();

		foreach ( array_slice( (array) ( $table['rows'] ?? array() ), 0, 500 ) as $row ) {
			$cells = array_map( $clean, array_slice( array_values( (array) $row ), 0, 30 ) );

			if ( '' !== implode( '', $cells ) ) {
				$rows[] = $cells;
			}
		}

		$cols = max( array_merge( array( count( $head ) ), array_map( 'count', $rows ) ) );
		$head = array_pad( $head, $cols, '' );
		$rows = array_map( static fn( array $cells ): array => array_pad( $cells, $cols, '' ), $rows );

		// Bos sutunlari sagdan sola atar (indeksler kaymasin).
		for ( $col = $cols - 1; $col >= 0; $col-- ) {
			$used = '' !== $head[ $col ];

			foreach ( $rows as $cells ) {
				$used = $used || '' !== $cells[ $col ];
			}

			if ( ! $used ) {
				array_splice( $head, $col, 1 );

				foreach ( $rows as &$cells ) {
					array_splice( $cells, $col, 1 );
				}
				unset( $cells );
			}
		}

		if ( '' === $title && ! $rows && '' === implode( '', $head ) ) {
			continue;
		}

		$out[] = array(
			'title' => $title,
			'head'  => $head,
			'rows'  => $rows,
		);
	}

	return $out;
}

/**
 * Sitenin gosterecegi urunler: kip + secim sirasi + site istisnalari uygulanmis.
 *
 * Her satir: id, title, short, price, price_label, has_price, spec, image, url
 */
function nwcs_site_products( ?int $blog_id = null ): array {
	$blog_id  = $blog_id ?? get_current_blog_id();
	$pool     = nwcs_pool_products();
	$settings = nwcs_site_product_settings( $blog_id );

	$ids = 'selected' === $settings['mode']
		? $settings['selected']
		: array_keys( $pool );

	$out = array();

	foreach ( $ids as $id ) {
		if ( ! isset( $pool[ $id ] ) ) {
			continue;
		}

		$product  = $pool[ $id ];
		$override = $settings['overrides'][ $id ] ?? array();

		if ( ! empty( $override['hidden'] ) ) {
			continue;
		}

		$title = '' !== ( $override['title'] ?? '' ) ? $override['title'] : $product['title'];
		$short = '' !== ( $override['short'] ?? '' ) ? $override['short'] : $product['short'];

		// Fiyat: istisna isaretliyse (bos olsa bile) istisna kazanir.
		$price = ! empty( $override['price_override'] )
			? (string) ( $override['price'] ?? '' )
			: $product['price'];

		// Site istisnasi varsa o gorsel one gecer; galerinin kalani korunur.
		$images = $product['images'] ?? array();

		if ( ! empty( $override['image'] ) ) {
			switch_to_blog( nwcs_pool_blog_id() );
			$override_image = nwcs_image_by_id( (int) $override['image'], 'medium_large' );
			restore_current_blog();

			$images = array_merge(
				array( $override_image ),
				array_values(
					array_filter(
						$product['images'] ?? array(),
						static fn( array $item ): bool => (int) $item["id"] !== (int) $override["image"]
					)
				)
			);
		}

		$image = $images ? $images[0] : nwcs_image_by_id( 0 );

		// Bu siteye ozel sayfa adresi varsa satirin adresi odur; havuzdaki
		// adres pool_slug olarak kalir (eski adres yonlendirmesi icin).
		$slug = nwcs_override_slug( $override );
		$slug = '' !== $slug ? $slug : $product['slug'];

		$out[] = array(
			'id'          => $product['id'],
			'slug'        => $slug,
			'pool_slug'   => $product['slug'],
			'custom_slug' => $slug !== $product['slug'],
			'title'       => $title,
			'short'       => $short,
			'body'        => $product['body'],
			'spec'        => $product['spec'],
			'details'     => $product['details'] ?? array(),
			'code'        => $product['code'] ?? '',
			'price'       => $price,
			'has_price'   => '' !== trim( $price ),
			'price_label' => '' !== trim( $price ) ? $price : 'Teklif al',
			'image'       => $image,
			'images'      => $images,
			'url'         => nwcs_product_url( $slug, $blog_id ),
			'categories'  => $product['categories'],
			'tables'      => $product['tables'] ?? array(),
			'video'       => $product['video'] ?? array(),
		);
	}

	return $out;
}

/**
 * Urunun bu sitede kendi sayfasi var mi?
 *
 * Detay metni (aciklama) olan her urunun sayfasi vardir. Aciklamasi olmayan
 * urunun sayfasi, temasi manifestinde 'product_page_always' diyen sitelerde
 * de acilir (WOOD KOCIST, Kocist): ad, gorsel ve teknik detaylar gosterilir,
 * yalnizca aciklama bolumu olmaz. Diger temalar karti yalnizca detay metni
 * olan urunde sayfaya baglar; onlarda eski kural surer.
 */
function nwcs_product_has_page( array $product, ?int $blog_id = null ): bool {
	if ( '' === (string) ( $product['slug'] ?? '' ) ) {
		return false;
	}

	if ( '' !== trim( (string) ( $product['body'] ?? '' ) ) ) {
		return true;
	}

	return ! empty( nwcs_manifest_for_blog( $blog_id ?? get_current_blog_id() )['product_page_always'] );
}

/**
 * Urun detay sayfasinin adresi (alt sitede /urun/<slug>/).
 */
function nwcs_product_url( string $slug, ?int $blog_id = null ): string {
	if ( '' === $slug ) {
		return '';
	}

	return get_home_url( $blog_id ?? get_current_blog_id(), '/urun/' . $slug . '/' );
}

/**
 * Slug'a gore tek urun (site istisnalari uygulanmis).
 */
function nwcs_site_product_by_slug( string $slug, ?int $blog_id = null ): ?array {
	$found = null;

	foreach ( nwcs_site_products( $blog_id ) as $product ) {
		if ( $product['slug'] !== $slug ) {
			continue;
		}

		// Ozel adres, sonradan ayni adla eklenen bir havuz urununun otomatik
		// adresinden once gelir: sitede elle verilmis adres el degistirmesin.
		if ( ! empty( $product['custom_slug'] ) ) {
			return $product;
		}

		$found = $found ?? $product;
	}

	return $found;
}

/* ------------------------------------------------------------------ */
/* Site basina sayfa adresi                                             */
/* ------------------------------------------------------------------ */

/**
 * Adres metnini temizler: Turkce harfler sadelesir (ş→s, ı→i...), bosluklar
 * tire olur, kucuk harfe cevrilir. "Verandalı Köpek Kulübesi" ->
 * "verandali-kopek-kulubesi". Tam adres yapistirilirsa yalnizca son parca alinir.
 */
function nwcs_slug_clean( string $text ): string {
	$text = strtr(
		trim( $text ),
		array(
			'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u',
			'Ş' => 's', 'ş' => 's', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c',
			'Â' => 'a', 'â' => 'a', 'Î' => 'i', 'î' => 'i', 'Û' => 'u', 'û' => 'u',
		)
	);

	$text = trim( strtok( $text, '?#' ) ?: '', '/ ' );

	if ( false !== strpos( $text, '/' ) ) {
		$parts = array_values( array_filter( explode( '/', $text ), 'strlen' ) );
		$text  = (string) end( $parts );
	}

	$slug = sanitize_title( remove_accents( $text ) );

	// sanitize_title yuzde kodlu karakter birakabilir (emoji vb.); adres sade kalsin.
	$slug = (string) preg_replace( '/%[0-9a-f]{2}/i', '', $slug );
	$slug = (string) preg_replace( '/-{2,}/', '-', $slug );

	return trim( substr( $slug, 0, 190 ), '-' );
}

/**
 * Istisna kaydindaki ozel adres (yoksa bos).
 */
function nwcs_override_slug( array $override ): string {
	return is_string( $override['slug'] ?? null ) ? (string) $override['slug'] : '';
}

/**
 * Istisna kaydindaki eski adresler (en yenisi sonda).
 *
 * @return string[]
 */
function nwcs_override_slug_old( array $override ): array {
	$old = $override['slug_old'] ?? array();

	return is_array( $old ) ? array_values( array_filter( array_map( 'strval', $old ), 'strlen' ) ) : array();
}

/**
 * Urunun bu sitedeki adresi (ozel adres ya da havuzdaki).
 */
function nwcs_product_site_slug( int $product_id, string $pool_slug, ?int $blog_id = null ): string {
	$override = nwcs_site_product_settings( $blog_id ?? get_current_blog_id() )['overrides'][ $product_id ] ?? array();
	$slug     = nwcs_override_slug( is_array( $override ) ? $override : array() );

	return '' !== $slug ? $slug : $pool_slug;
}

/**
 * Bir adresin bu sitedeki guncel karsiligi: adres bir urunun guncel adresiyse
 * kendisi; havuzdaki adresi ya da eski ozel adreslerinden biriyse o urunun
 * guncel adresi; hicbiri degilse bos. Temalar eski baglantilari (ornegin
 * Kocist teklif formu ?urun=<adres>) bununla cozer.
 */
function nwcs_product_slug_resolve( string $slug, ?int $blog_id = null ): string {
	if ( '' === $slug ) {
		return '';
	}

	$blog_id  = $blog_id ?? get_current_blog_id();
	$products = nwcs_site_products( $blog_id );

	foreach ( $products as $product ) {
		if ( $product['slug'] === $slug ) {
			return $slug;
		}
	}

	$overrides = nwcs_site_product_settings( $blog_id )['overrides'];

	foreach ( $products as $product ) {
		$override = $overrides[ $product['id'] ] ?? array();

		if ( $product['pool_slug'] === $slug || in_array( $slug, nwcs_override_slug_old( is_array( $override ) ? $override : array() ), true ) ) {
			return (string) $product['slug'];
		}
	}

	return '';
}

/**
 * Bulunamayan /urun/<slug>/ icin guncel adres: slug bu sitede bir urunun
 * havuzdaki adresi ya da eski ozel adreslerinden biriyse o urunun guncel
 * sayfasi; degilse bos. Eski adreslerin hepsi dogrudan guncel adrese gider
 * (zincir yok).
 */
function nwcs_product_moved_url( string $slug, ?int $blog_id = null ): string {
	if ( '' === $slug ) {
		return '';
	}

	$blog_id   = $blog_id ?? get_current_blog_id();
	$overrides = nwcs_site_product_settings( $blog_id )['overrides'];

	foreach ( nwcs_site_products( $blog_id ) as $product ) {
		if ( $product['slug'] === $slug || ! nwcs_product_has_page( $product, $blog_id ) ) {
			continue;
		}

		$override = $overrides[ $product['id'] ] ?? array();
		$old      = nwcs_override_slug_old( is_array( $override ) ? $override : array() );

		if ( $product['pool_slug'] === $slug || in_array( $slug, $old, true ) ) {
			return (string) $product['url'];
		}
	}

	return '';
}

/* ------------------------------------------------------------------ */
/* Detay sayfasi yonlendirmesi                                          */
/* ------------------------------------------------------------------ */

add_action( 'init', 'nwcs_product_rewrite' );
function nwcs_product_rewrite(): void {
	add_rewrite_rule( '^urun/([^/]+)/?$', 'index.php?nwcs_product=$matches[1]', 'top' );
}

add_filter( 'query_vars', 'nwcs_product_query_var' );
function nwcs_product_query_var( array $vars ): array {
	$vars[] = 'nwcs_product';

	return $vars;
}

add_action( 'template_redirect', 'nwcs_product_template', 5 );
function nwcs_product_template(): void {
	$slug = get_query_var( 'nwcs_product' );

	if ( ! $slug ) {
		return;
	}

	$product = nwcs_site_product_by_slug( sanitize_title( (string) $slug ) );

	// Bu sitede gosterilmeyen urunun sayfasi yoktur; detay metni girilmemis
	// urunun sayfasi yalnizca temasi bunu isteyen sitede acilir
	// (nwcs_product_has_page). Aksi halde /urun/<slug>/ bos sayfa donerdi.
	// Adres bu sitede degistirilmisse eski adres (havuzdaki ya da onceki ozel
	// adres) yeni adrese kalici yonlenir. Yonlendirme listesinden once bakilir;
	// hedef her zaman guncel adrestir, zincir olusmaz.
	if ( ! $product ) {
		$moved = nwcs_product_moved_url( sanitize_title( (string) $slug ) );

		if ( '' !== $moved ) {
			$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- adrese aynen eklenir, wp_redirect temizler.

			wp_redirect( $moved . ( '' !== $query ? '?' . $query : '' ), 301, 'Network Content Studio' ); // phpcs:ignore WordPress.Security.SafeRedirect -- ayni sitenin adresi.
			exit;
		}
	}

	if ( ! $product || ! nwcs_product_has_page( $product ) ) {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		// Eski adresler icin yonlendirme listesi (ornegin kaldirilan urun):
		// liste yalnizca bulunamayan sayfada calisir, burada ilk kez 404 oldu.
		// Kural varsa yonlendirip cikar ya da durumu 410 yapar.
		if ( function_exists( 'nwcs_redirects_apply' ) ) {
			nwcs_redirects_apply();
		}

		// Tema 404.php vermiyorsa index.php'ye dus.
		$not_found = get_query_template( '404' );

		if ( ! $not_found ) {
			$not_found = get_index_template();
		}

		if ( $not_found ) {
			include $not_found;
		}

		exit;
	}

	status_header( 200 );

	// Tema kendi detay sablonunu verebilir; yoksa eklentinin sadesi kullanilir.
	$template = locate_template( 'template-parts/product-detail.php' );

	if ( ! $template ) {
		$template = NWCS_DIR . 'templates/product-detail.php';
	}

	// $product sablonda kullanilir.
	include $template;
	exit;
}

/**
 * Aga yeni bir site eklendiginde urun ayarlarini acikca yazar; boylece
 * "kayit yok" hali hic olusmaz ve site sahibi ne sectigini bilerek baslar.
 */
add_action( 'wp_initialize_site', 'nwcs_seed_new_site_product_settings', 20, 1 );
function nwcs_seed_new_site_product_settings( $site ): void {
	$blog_id = (int) ( is_object( $site ) ? $site->blog_id : $site );

	if ( ! $blog_id ) {
		return;
	}

	add_blog_option( $blog_id, NWCS_OPTION_MODE, 'selected' );
	add_blog_option( $blog_id, NWCS_OPTION_SELECTED, array() );
	add_blog_option( $blog_id, NWCS_OPTION_OVERRIDES, array() );
}

/* ------------------------------------------------------------------ */
/* Urun sayfasi alt bolumu: detay metni bolumleri ve one cikan ozellikler */
/* ------------------------------------------------------------------ */

/**
 * Urunun detay metnini (wp_kses_post'tan gecmis HTML) bolumlere ayirir.
 *
 * Bolum basligi sayilanlar:
 *  - <h2>-<h4>, ya da yalnizca kalin yazidan olusan paragraf (<p><b>Baslik</b></p>);
 *  - emojiyle baslayan kisa satir ("🔧 Teknik Ozellikler": eski sitelerden
 *    gelen metinler boyle; emoji basliktan atilir);
 *  - iki noktayla biten kisa satir ("Kullanim Alanlari:").
 * Bir bolumdeki ardisik kisa satirlar (✔️ / • / - ile baslayan ya da noktasiz
 * kisa cumleler) madde listesine cevrilir; en az uc ardisik "Etiket: deger"
 * satiri ozellik cifti olarak ayrilir (pairs) ve metinden cikarilir.
 * Basliksiz metinde her paragraf basliksiz bir bolum olur. Ilk basliktan
 * onceki metin "giris" (lead) olarak ayrilir.
 *
 * @return array{lead: string, chapters: array<int, array{title: string, html: string}>, pairs: array<int, array{0:string, 1:string}>}
 */
function nwcs_product_body_sections( string $html ): array {
	$html = trim( $html );
	$out  = array( 'lead' => '', 'chapters' => array(), 'pairs' => array() );

	if ( '' === $html ) {
		return $out;
	}

	if ( false === stripos( $html, '<p' ) && false === stripos( $html, '<h' ) ) {
		$html = wpautop( $html );
	}

	$emoji = '[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{FE0F}\x{200D}\x{20E3}]';
	$mark  = '/^(?:' . $emoji . '|[•·▪►▶✓✔\-–—*])+\s*/u';

	preg_match_all( '#<(h[2-4]|p|ul|ol|blockquote|table)\b[^>]*>.*?</\1>#is', $html, $blocks );

	// Once duz bloklar: [tur, baslik, html, duz metin].
	$items = array();

	foreach ( $blocks[0] as $block ) {
		$inner = trim( (string) preg_replace( '#^<p\b[^>]*>|</p>$#i', '', $block ) );
		$plain = trim( html_entity_decode( wp_strip_all_tags( $block ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( '' === $plain ) {
			continue;
		}

		$title = '';

		if ( preg_match( '#^<h[2-4]\b[^>]*>(.*?)</h[2-4]>$#is', $block, $match ) ) {
			$title = trim( wp_strip_all_tags( $match[1] ) );
		} elseif ( preg_match( '#^<(b|strong)>([^<]{2,140})</\1>$#i', $inner, $match ) ) {
			$title = trim( $match[2] );
		} elseif ( str_starts_with( strtolower( $block ), '<p' ) && mb_strlen( $plain ) <= 90 && preg_match( '/^' . $emoji . '/u', $plain ) && ! preg_match( '/^' . $emoji . '*\s*[✓✔]/u', $plain ) ) {
			$title = trim( (string) preg_replace( $mark, '', $plain ) );
		} elseif ( str_starts_with( strtolower( $block ), '<p' ) && mb_strlen( $plain ) <= 60 && str_ends_with( $plain, ':' ) ) {
			$title = rtrim( $plain, ': ' );
		}

		// Emojili kisa satir, hemen bir basligin ardindan geliyorsa baslik degil
		// o basligin madde satiridir ("🚛 Neden ...?" altindaki "🇹🇷 ..." satirlari).
		$emoji_title = '' !== $title && ! preg_match( '#^<(h[2-4]|p><(b|strong))#i', $block ) && ! str_ends_with( $plain, ':' );

		if ( $emoji_title && $items && 'title' === end( $items )[0] ) {
			$items[] = array( 'text', '', $block, $plain, true );
			continue;
		}

		if ( $emoji_title && $items && 'text' === end( $items )[0] && ! empty( end( $items )[4] ) ) {
			$items[] = array( 'text', '', $block, $plain, true );
			continue;
		}

		$items[] = '' !== $title ? array( 'title', $title, '', '' ) : array( 'text', '', $block, $plain, false );
	}

	// Bolumler: basliktan onceki metin giris; her baslik yeni bolum.
	$current = null;
	$lead    = array();
	$groups  = array();

	foreach ( $items as $item ) {
		if ( 'title' === $item[0] ) {
			if ( null !== $current ) {
				$groups[] = $current;
			}
			$current = array( 'title' => $item[1], 'items' => array() );
			continue;
		}

		if ( null === $current ) {
			$lead[] = $item;
		} else {
			$current['items'][] = $item;
		}
	}

	if ( null !== $current ) {
		$groups[] = $current;
	}

	// Bir bolumun satirlarini HTML'e cevirir: ozellik ciftleri ayrilir, kisa satir dizileri liste olur.
	$render = static function ( array $rows ) use ( $mark, &$out ): string {
		$pair_re = '/^([^:.!?]{2,40}):\s*(\S.{0,120})$/u';
		$pairs   = array_values( array_filter( $rows, static fn( array $row ): bool => (bool) preg_match( $pair_re, $row[3] ) && ! str_contains( strtolower( $row[2] ), '<ul' ) ) );

		// Satirlarin cogu "Etiket: deger" ise bunlar ozellik; metinden cikar.
		if ( count( $pairs ) >= 3 && count( $pairs ) >= 0.7 * count( $rows ) ) {
			foreach ( $pairs as $row ) {
				preg_match( $pair_re, (string) preg_replace( $mark, '', $row[3] ), $match );

				if ( $match ) {
					$out['pairs'][] = array( trim( $match[1] ), trim( $match[2] ) );
				}
			}

			$rows = array_values( array_filter( $rows, static fn( array $row ): bool => ! in_array( $row, $pairs, true ) ) );
		}

		$html  = '';
		$run   = array();
		$flush = static function () use ( &$run, &$html ): void {
			if ( count( $run ) >= 3 ) {
				$html .= '<ul>' . implode( '', array_map( static fn( string $line ): string => '<li>' . esc_html( $line ) . '</li>', $run ) ) . "</ul>\n";
			} else {
				foreach ( $run as $line ) {
					$html .= '<p>' . esc_html( $line ) . "</p>\n";
				}
			}
			$run = array();
		};

		foreach ( $rows as $row ) {
			$short = str_starts_with( strtolower( $row[2] ), '<p' ) && mb_strlen( $row[3] ) <= 110 && ( preg_match( $mark, $row[3] ) || ! preg_match( '/[.!?…]$/u', $row[3] ) );

			if ( $short ) {
				$run[] = trim( (string) preg_replace( $mark, '', $row[3] ) );
				continue;
			}

			$flush();
			$html .= $row[2] . "\n";
		}

		$flush();

		return $html;
	};

	$out['lead'] = $lead ? $render( $lead ) : '';

	foreach ( $groups as $group ) {
		$body = $render( $group['items'] );

		// Tamami ozellige donusen bolum (ornek: "Teknik Ozellikler") ayrica basilmaz.
		if ( '' === trim( $body ) ) {
			continue;
		}

		$out['chapters'][] = array( 'title' => $group['title'], 'html' => $body );
	}

	// Hic baslik yoksa: ilk paragraf giris, kalanlar basliksiz bolumler.
	if ( ! $out['chapters'] && '' !== $out['lead'] ) {
		preg_match_all( '#<(p|ul|ol|blockquote|table)\b[^>]*>.*?</\1>#is', $out['lead'], $parts );
		$parts       = $parts[0] ?: array( $out['lead'] );
		$out['lead'] = (string) array_shift( $parts );

		foreach ( $parts as $part ) {
			$out['chapters'][] = array( 'title' => '', 'html' => $part );
		}
	}

	return $out;
}

/**
 * Ozellik ciftlerinden (etiket, deger) sayfanin basinda one cikacak en fazla
 * $limit tanesi: olcu, malzeme, kurulum ve teslimat once; uzun degerler
 * (40 karakterden uzun) atlanir.
 *
 * @param array<int, array{0:string, 1:string}> $pairs
 * @return array<int, array{0:string, 1:string}>
 */
function nwcs_product_key_facts( array $pairs, int $limit = 4 ): array {
	$priority = array( 'boyut', 'ölçü', 'olcu', 'ebat', 'ahşap', 'ahsap', 'malzeme', 'kurulum', 'montaj', 'teslim', 'yüzey', 'yuzey' );
	$scored   = array();

	foreach ( array_values( $pairs ) as $index => $pair ) {
		if ( mb_strlen( (string) $pair[1] ) > 40 ) {
			continue;
		}

		$label = mb_strtolower( (string) $pair[0] );
		$rank  = count( $priority ) + $index;

		foreach ( $priority as $position => $needle ) {
			if ( str_contains( $label, $needle ) ) {
				$rank = $position;
				break;
			}
		}

		$scored[] = array( $rank, $index, $pair );
	}

	usort( $scored, static fn( array $a, array $b ): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1] );

	// "Ithal Cam ( Firinlanmis )" gibi parantez ici bosluklar gorunumde toplanir.
	$tidy = static fn( string $text ): string => (string) preg_replace( array( '/\(\s+/u', '/\s+\)/u' ), array( '(', ')' ), $text );

	return array_map( static fn( array $row ): array => array( $tidy( (string) $row[2][0] ), $tidy( (string) $row[2][1] ) ), array_slice( $scored, 0, $limit ) );
}

/**
 * Detay metnindeki tablolari metinden ayirir: urun sayfasi tabloyu (olcu ve
 * model listesi) metnin icine sikistirmadan, kendi alaninda gosterebilsin.
 *
 * @return array{text: string, tables: string[]}
 */
function nwcs_product_split_tables( string $html ): array {
	$tables = array();

	$text = (string) preg_replace_callback(
		'#<table\b[^>]*>.*?</table>#is',
		static function ( array $match ) use ( &$tables ): string {
			$tables[] = $match[0];
			return '';
		},
		$html
	);

	// Tablo cikinca bos kalan paragraflar atilir.
	$text = (string) preg_replace( '#<p>\s*(?:&nbsp;|<br\s*/?>|\s)*</p>#i', '', $text );

	return array( 'text' => trim( $text ), 'tables' => $tables );
}

/**
 * Urunun ayrinti seviyesi: sayfanin alt bolumu buna gore kurulur.
 *   rich   - en az iki ek fotograf ve iki bolum: fotograflarla anlatim
 *   table  - kisa metin + tablo (olcu / model listesi): tablo one cikar
 *   brief  - kisa metin, tablo yok: tek sutun, sade
 *   medium - uzun metin ya da en az uc ozellik: giris + iki sutun + foy
 *
 * @param int $text_length Tablolar cikarilmis metnin karakter sayisi.
 */
function nwcs_product_detail_level( int $text_length, int $chapters, int $extra_photos, int $spec_count, bool $has_tables ): string {
	if ( $extra_photos >= 2 && $chapters >= 2 ) {
		return 'rich';
	}

	if ( $text_length < 700 && $has_tables ) {
		return 'table';
	}

	if ( $text_length < 700 && $spec_count < 3 ) {
		return 'brief';
	}

	return 'medium';
}

/**
 * Girisi, urunun kisa aciklamasini tekrar ediyorsa (kisa aciklama cogu urunde
 * detay metninin ilk cumleleri) normal bolume indirir: ayni cumle sayfada iki
 * kez vurgulanmasin.
 *
 * @param array{lead: string, chapters: array} $sections nwcs_product_body_sections()
 */
function nwcs_product_demote_duplicate_lead( array $sections, string $short ): array {
	$fold  = static fn( string $text ): string => mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) ) );
	$short = (string) preg_replace( '/[\s.…]+$/u', '', $fold( $short ) );
	$lead  = $fold( $sections['lead'] ?? '' );

	if ( '' === $lead || mb_strlen( $short ) < 20 ) {
		return $sections;
	}

	if ( str_starts_with( $lead, mb_substr( $short, 0, 60 ) ) ) {
		array_unshift( $sections['chapters'], array( 'title' => '', 'html' => $sections['lead'] ) );
		$sections['lead'] = '';
	}

	return $sections;
}
