<?php
/**
 * Renk gruplari: ayni urunun renkleri.
 *
 * Excel'de her renk ayri urun olarak girilir (kendi kodu, fotografi, fiyati,
 * sayfasi). Panelde bir kategorinin icinde bu urunler renk grubu olarak
 * birlestirilir (Ürün Havuzu -> Kategoriler -> kategori -> Renk grupları,
 * includes/admin/color-groups.php). Grubun 1. uyesi one cikan urundur.
 *
 * Veri: havuz kategorisinin terim meta'si '_nwcs_color_groups' = sirali liste:
 *   [ { id, label, members: [ { product_id, color_name, color_hex }, ... ] }, ... ]
 *   label: varsayilan "Renk"; "Çatı rengi", "Gövde rengi" gibi degisebilir.
 *
 * Kurallar:
 *   - bir urun havuz genelinde en fazla bir grupta olur (kayitta denetlenir;
 *     haritada ilk gelen kazanir);
 *   - uye o kategoride olmalidir;
 *   - renk adi zorunludur (panel kaydi reddeder); adi bos uye sitede sayilmaz;
 *   - copteki, silinmis ya da kategoriden cikmis uye sitede yok sayilir
 *     (panel uyariyla gosterir).
 *
 * Okuma: nwcs_color_map(), havuz basina onbellekli harita (urun -> grup, sira).
 * Kategori onbellegiyle birlikte temizlenir (nwcs_pool_categories_flush;
 * nwcs_pool_flush_cache de onu cagirir).
 *
 * Sitede:
 *   - nwcs_color_collapse(): site urun listesini sadelestirir. Her grubun o
 *     sitede gorunen ilk uyesi kalir (grubun listedeki ilk yerinde), diger
 *     uyeler listeden cikar. Kalan satira 'colors', 'color_count',
 *     'color_label', 'color_name' ve 'card_title' eklenir. Sitede tek uye
 *     gorunuyorsa grup yok sayilir. Kategori, seri, magaza, ana sayfa
 *     listeleri ve menudeki sayilar bununla sayilir; arama, site haritasi,
 *     llms.txt ve sepet her uyeyi ayri gorur.
 *   - nwcs_color_options(): urun sayfasindaki renk dugmeleri.
 *   - nwcs_color_seo(): Product'a color/inProductGroupWithID ve ProductGroup
 *     (includes/seo.php cagirir). Her renk sayfasi kendi adresinde ve kendi
 *     canonical'inda kalir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_COLOR_META      = '_nwcs_color_groups';
const NWCS_COLOR_CACHE_KEY = 'nwcs_color_groups_v1';
const NWCS_COLOR_LABEL     = 'Renk';

/* ====================================================================== *
 * Renkler
 * ====================================================================== */

/**
 * Hazir renkler: ad => yuvarlagin rengi. Panelde secim listesi; adi bos
 * birakilan uyeye secilen hazir rengin adi yazilir.
 *
 * @return array<string, string>
 */
function nwcs_color_presets(): array {
	return array(
		'Beyaz'             => '#ffffff',
		'Siyah'             => '#1f1f1f',
		'Antrasit'          => '#383e42',
		'Gri'               => '#9ea1a3',
		'Kiremit Kırmızı'   => '#a5432f',
		'Zümrüt Yeşili'     => '#1f6d4e',
		'Teak'              => '#a3683b',
		'Ceviz'             => '#5e3c25',
		'Doğal Çam'         => '#e3c391',
		'Amber (Açık Meşe)' => '#c99a5b',
		'Nussbaum'          => '#6f4a2e',
		'Mavi'              => '#2f5f8f',
		'Sarı'              => '#e6b42c',
	);
}

/**
 * Renk kodunu bicime sokar: "#abc" ya da "#aabbcc" -> "#aabbcc"; gecersizse bos.
 */
function nwcs_color_hex( string $value ): string {
	$value = strtolower( trim( $value ) );

	if ( preg_match( '/^#?([0-9a-f]{3})$/', $value, $match ) ) {
		$value = '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
	}

	return preg_match( '/^#?[0-9a-f]{6}$/', $value ) ? '#' . ltrim( $value, '#' ) : '';
}

/**
 * Acik renk mi (beyaz, krem)? Yuvarlagin cevresine ince cizgi gerekir.
 * Bagil parlaklik (WCAG) 0,7'den buyukse acik sayilir.
 */
function nwcs_color_is_light( string $hex ): bool {
	$hex = nwcs_color_hex( $hex );

	if ( '' === $hex ) {
		return true; // Rengi girilmemis yuvarlak acik zeminde cizgiyle gorunsun.
	}

	$channel = static function ( string $pair ): float {
		$value = hexdec( $pair ) / 255;

		return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
	};

	$luminance = 0.2126 * $channel( substr( $hex, 1, 2 ) ) + 0.7152 * $channel( substr( $hex, 3, 2 ) ) + 0.0722 * $channel( substr( $hex, 5, 2 ) );

	return $luminance > 0.7;
}

/**
 * Hazir renk adina gore yuvarlak rengi (Turkce harf ve buyuk/kucuk harf farki
 * gozetilmez); yoksa bos.
 */
function nwcs_color_preset_hex( string $name ): string {
	$name = nwcs_search_fold( trim( $name ) );

	foreach ( nwcs_color_presets() as $label => $hex ) {
		if ( '' !== $name && nwcs_search_fold( $label ) === $name ) {
			return $hex;
		}
	}

	return '';
}

/**
 * Urun adinda gecen hazir renk (grup kurulurken uye satirini doldurmak icin):
 * en uzun eslesme kazanir ("Açık Meşe" "Meşe"den once). Yoksa bos.
 *
 * @return array{name:string, hex:string}
 */
function nwcs_color_guess( string $title ): array {
	$haystack = ' ' . preg_replace( '/[^a-z0-9]+/', ' ', nwcs_search_fold( $title ) ) . ' ';
	$best     = array( 'name' => '', 'hex' => '' );
	$length   = 0;

	foreach ( nwcs_color_presets() as $label => $hex ) {
		// "Amber (Açık Meşe)": parantezli adin iki parcasi da aranir.
		$parts = array_filter( array_map( 'trim', preg_split( '/[()]/u', $label ) ?: array() ) );

		foreach ( $parts as $part ) {
			$needle = trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', nwcs_search_fold( $part ) ) );

			if ( '' !== $needle && str_contains( $haystack, ' ' . $needle . ' ' ) && strlen( $needle ) > $length ) {
				$best   = array( 'name' => $label, 'hex' => $hex );
				$length = strlen( $needle );
			}
		}
	}

	return $best;
}

/**
 * Kart basligi: one cikan urunun adi; adinda renk adi gecmiyorsa sonuna
 * " – <renk>" eklenir (Turkce harf ve buyuk/kucuk harf farki gozetilmez).
 * Parantezli renk adinin ("Amber (Açık Meşe)") bir parcasi adda geciyorsa
 * renk adda var sayilir (nwcs_color_guess ile ayni bolme).
 */
function nwcs_color_card_title( string $title, string $color ): string {
	$color = trim( $color );

	if ( '' === $color ) {
		return $title;
	}

	$haystack = nwcs_search_fold( $title );

	foreach ( array_filter( array_map( 'trim', preg_split( '/[()]/u', $color ) ?: array() ) ) as $part ) {
		if ( str_contains( $haystack, nwcs_search_fold( $part ) ) ) {
			return $title;
		}
	}

	return $title . ' – ' . $color;
}

/* ====================================================================== *
 * Veri
 * ====================================================================== */

/**
 * Yeni grup kimligi (kategori icinde tekil, degismez).
 */
function nwcs_color_new_id(): string {
	return 'g' . bin2hex( random_bytes( 4 ) );
}

/**
 * Grup listesini temizler (kayit ve okuma icin ayni kural). Uyesi kalmayan
 * grup atilir; ayni urun listede ikinci kez gecerse ikincisi atilir.
 *
 * @param mixed $raw
 * @return array<int, array{id:string, label:string, members:array<int, array{product_id:int, color_name:string, color_hex:string}>}>
 */
function nwcs_color_clean( $raw ): array {
	$out  = array();
	$ids  = array();
	$seen = array();

	foreach ( is_array( $raw ) ? $raw : array() as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}

		$members = array();

		foreach ( is_array( $group['members'] ?? null ) ? $group['members'] : array() as $member ) {
			$product_id = is_array( $member ) ? absint( $member['product_id'] ?? 0 ) : 0;

			if ( ! $product_id || isset( $seen[ $product_id ] ) ) {
				continue;
			}

			$seen[ $product_id ] = true;
			$members[]           = array(
				'product_id' => $product_id,
				'color_name' => mb_substr( nwcs_clean_text( $member['color_name'] ?? '' ), 0, 40 ),
				'color_hex'  => nwcs_color_hex( (string) ( $member['color_hex'] ?? '' ) ),
			);
		}

		if ( ! $members ) {
			continue;
		}

		$id = sanitize_key( (string) ( $group['id'] ?? '' ) );

		if ( '' === $id || isset( $ids[ $id ] ) ) {
			$id = nwcs_color_new_id();
		}

		$ids[ $id ] = true;
		$label      = mb_substr( nwcs_clean_text( $group['label'] ?? '' ), 0, 40 );

		$out[] = array(
			'id'      => $id,
			'label'   => '' !== $label ? $label : NWCS_COLOR_LABEL,
			'members' => $members,
		);
	}

	return $out;
}

/**
 * Kategorinin renk gruplari (kayitli sirayla, temizlenmis).
 */
function nwcs_color_groups( string $slug ): array {
	$term_id = (int) ( nwcs_pool_categories()[ $slug ]['id'] ?? 0 );

	if ( ! $term_id ) {
		return array();
	}

	switch_to_blog( nwcs_pool_blog_id() );
	$raw = get_term_meta( $term_id, NWCS_COLOR_META, true );
	restore_current_blog();

	return nwcs_color_clean( $raw );
}

/**
 * Kategorinin renk gruplarini yazar (bos listede meta silinir) ve havuz
 * onbellegini temizler (site onbellegi istek sonunda: cache-purge.php).
 *
 * @return bool Kategori var mi (yazildi mi)?
 */
function nwcs_color_groups_write( string $slug, array $groups ): bool {
	$term_id = (int) ( nwcs_pool_categories()[ $slug ]['id'] ?? 0 );

	if ( ! $term_id ) {
		return false;
	}

	$groups = nwcs_color_clean( $groups );

	switch_to_blog( nwcs_pool_blog_id() );

	if ( $groups ) {
		update_term_meta( $term_id, NWCS_COLOR_META, $groups );
	} else {
		delete_term_meta( $term_id, NWCS_COLOR_META );
	}

	restore_current_blog();

	nwcs_pool_flush_cache();

	return true;
}

/**
 * Havuzun renk haritasi:
 *   groups  => [ 'kategori/grup' => { key, slug, id, label, members } ]
 *   members => [ urun_id => { group: 'kategori/grup', position } ]
 * Istek ici degiskende ve havuz sitesinin transient'inda tutulur.
 *
 * @return array{groups: array<string, array>, members: array<int, array{group:string, position:int}>}
 */
function nwcs_color_map( bool $reset = false ): array {
	static $cache = null;

	$empty = array( 'groups' => array(), 'members' => array() );

	if ( $reset ) {
		$cache = null;

		return $empty;
	}

	if ( null !== $cache ) {
		return $cache;
	}

	// Taksonomi henuz kayitli degilse (init oncesi) bos doner ve saklanmaz.
	if ( ! taxonomy_exists( NWCS_PRODUCT_TAX ) ) {
		return $empty;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$stored = get_transient( NWCS_COLOR_CACHE_KEY );

	if ( is_array( $stored ) && isset( $stored['groups'], $stored['members'] ) ) {
		restore_current_blog();
		$cache = $stored;

		return $cache;
	}

	// Yalnizca grubu olan kategoriler (meta onbellegi sorguyla birlikte dolar).
	$terms = get_terms(
		array(
			'taxonomy'   => NWCS_PRODUCT_TAX,
			'hide_empty' => false,
			'orderby'    => 'name',
			'meta_key'   => NWCS_COLOR_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	$map = $empty;

	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		foreach ( nwcs_color_clean( get_term_meta( (int) $term->term_id, NWCS_COLOR_META, true ) ) as $group ) {
			$key = $term->slug . '/' . $group['id'];

			$map['groups'][ $key ] = $group + array(
				'key'  => $key,
				'slug' => (string) $term->slug,
			);

			foreach ( $group['members'] as $position => $member ) {
				// Bir urun tek grupta: (elle bozulmus veride) ilk gelen kazanir.
				$map['members'][ $member['product_id'] ] ??= array(
					'group'    => $key,
					'position' => (int) $position,
				);
			}
		}
	}

	if ( ! is_wp_error( $terms ) ) {
		set_transient( NWCS_COLOR_CACHE_KEY, $map, HOUR_IN_SECONDS );
	}

	restore_current_blog();

	$cache = $map;

	return $map;
}

/**
 * Renk haritasini ve sitelerin urun dizinini temizler.
 */
function nwcs_color_flush(): void {
	switch_to_blog( nwcs_pool_blog_id() );
	delete_transient( NWCS_COLOR_CACHE_KEY );
	restore_current_blog();

	nwcs_color_map( true );
	nwcs_color_site_index( 0, true );
}

/**
 * Urunun grubu (uyeleri ve urunun sirasiyla); grupta degilse null.
 *
 * @return array{key:string, slug:string, id:string, label:string, members:array, position:int}|null
 */
function nwcs_color_group_of( int $product_id ): ?array {
	$map  = nwcs_color_map();
	$info = $map['members'][ $product_id ] ?? null;

	if ( ! $info || ! isset( $map['groups'][ $info['group'] ] ) ) {
		return null;
	}

	return $map['groups'][ $info['group'] ] + array( 'position' => (int) $info['position'] );
}

/**
 * Urunun grubundaki butun urunlerin kimlikleri (kendisi dahil); grupta degilse
 * yalnizca kendisi. Ilgili urunler seridi ayni grubu tekrar gostermesin diye.
 *
 * @return int[]
 */
function nwcs_color_member_ids( int $product_id ): array {
	$group = nwcs_color_group_of( $product_id );

	return $group ? array_map( static fn( array $member ): int => (int) $member['product_id'], $group['members'] ) : array( $product_id );
}

/**
 * Uyenin havuzdaki durumu: '' (gecerli), 'trash' (cop kutusunda), 'gone'
 * (silinmis ya da yayinda degil), 'moved' (artik bu kategoride degil).
 * Gecerli olmayan uye sitede yok sayilir.
 */
function nwcs_color_member_state( int $product_id, string $slug ): string {
	$pool = nwcs_pool_products();

	if ( isset( $pool[ $product_id ] ) ) {
		return isset( $pool[ $product_id ]['categories'][ $slug ] ) ? '' : 'moved';
	}

	return in_array( $product_id, nwcs_pool_trashed_ids(), true ) ? 'trash' : 'gone';
}

/**
 * Sitenin gosterdigi urunler, kimlige gore (istek ici onbellek;
 * nwcs_color_flush temizler).
 *
 * @return array<int, array>
 */
function nwcs_color_site_index( int $blog_id, bool $reset = false ): array {
	static $cache = array();

	if ( $reset ) {
		$cache = array();

		return array();
	}

	if ( ! isset( $cache[ $blog_id ] ) ) {
		$cache[ $blog_id ] = array();

		foreach ( nwcs_site_products( $blog_id ) as $row ) {
			$cache[ $blog_id ][ (int) $row['id'] ] = $row;
		}
	}

	return $cache[ $blog_id ];
}

/**
 * Grubun bu sitede gorunen renkleri, grup sirasiyla. Sayilan uye: renk adi
 * yazilmis, havuzda yayinda, kategoride, sitede gosteriliyor ve sayfasi var.
 * Adsiz renk dugmesi basilmaz; adi bos uye grupta sayilmaz, sitede kendi
 * karti olarak kalir (panel "Renk adı boş olan ürünler" diye uyarir).
 *
 * Her satir: id, name (renk adi), hex, light, url (urunun bu sitedeki
 * sayfasi; site basina adres dahil), title.
 *
 * @return array<int, array{id:int, name:string, hex:string, light:bool, url:string, title:string}>
 */
function nwcs_color_site_colors( array $group, int $blog_id ): array {
	$pool   = nwcs_pool_products();
	$index  = nwcs_color_site_index( $blog_id );
	$colors = array();

	foreach ( (array) ( $group['members'] ?? array() ) as $member ) {
		$id  = (int) $member['product_id'];
		$row = $index[ $id ] ?? null;

		if ( '' === trim( (string) $member['color_name'] ) || ! $row || ! isset( $pool[ $id ]['categories'][ $group['slug'] ] ) || ! nwcs_product_has_page( $row, $blog_id ) ) {
			continue;
		}

		$colors[] = array(
			'id'    => $id,
			'name'  => (string) $member['color_name'],
			'hex'   => $member['color_hex'],
			'light' => nwcs_color_is_light( $member['color_hex'] ),
			'url'   => (string) $row['url'],
			'title' => (string) $row['title'],
		);
	}

	return $colors;
}

/**
 * Urun sayfasinin renk secenekleri; urun grupta degilse ya da sitede ikiden
 * az renk gorunuyorsa bos dizi.
 *
 * @return array{key:string, label:string, name:string, code:string, colors:array<int, array>}|array{}
 */
function nwcs_color_options( int $product_id, ?int $blog_id = null ): array {
	$blog_id = $blog_id ?? get_current_blog_id();
	$group   = nwcs_color_group_of( $product_id );

	if ( ! $group ) {
		return array();
	}

	$colors = nwcs_color_site_colors( $group, $blog_id );
	$ids    = array_column( $colors, 'id' );

	if ( count( $colors ) < 2 || ! in_array( $product_id, $ids, true ) ) {
		return array();
	}

	$name = '';

	foreach ( $colors as $index => $color ) {
		$colors[ $index ]['current'] = $color['id'] === $product_id;

		if ( $colors[ $index ]['current'] ) {
			$name = $color['name'];
		}
	}

	return array(
		'key'    => $group['key'],
		'label'  => $group['label'],
		'name'   => $name,
		'code'   => nwcs_color_group_code( $group ),
		'colors' => $colors,
	);
}

/**
 * Grubun yapisal veri kimligi (productGroupID): sabit, uye sirasi degisince
 * de ayni kalir.
 */
function nwcs_color_group_code( array $group ): string {
	return 'RENK-' . strtoupper( (string) ( $group['id'] ?? '' ) );
}

/**
 * Site urun listesini renk gruplarina gore sadelestirir (bkz. dosya basi).
 *
 * Grubun o sitede gorunen ve listede bulunan ilk uyesi (grup sirasiyla) kalir;
 * grubun listedeki ilk yerine oturur. Ayni grubun listedeki diger uyeleri
 * cikar. Listede olmayan ama sitede gorunen uyeler renk olarak sayilir
 * (renk sayisi ve dugmeler site geneli). Sitede tek uye gorunuyorsa grup
 * yok sayilir; satirlar oldugu gibi kalir.
 *
 * Kalan satira eklenenler: colors (renk listesi, 'current' bu satir mi),
 * color_count, color_label, color_name, card_title.
 *
 * @param array<int, array> $products nwcs_site_products() satirlari (tema ek anahtarlariyla).
 * @return array<int, array>
 */
function nwcs_color_collapse( array $products, ?int $blog_id = null ): array {
	$products = array_values( $products );
	$map      = nwcs_color_map();

	if ( ! $map['members'] || ! $products ) {
		return $products;
	}

	$blog_id = $blog_id ?? get_current_blog_id();
	$present = array(); // grup => [ urun_id => listedeki yeri ]

	foreach ( $products as $index => $row ) {
		$id   = (int) ( $row['id'] ?? 0 );
		$info = $map['members'][ $id ] ?? null;

		if ( $info && ! isset( $present[ $info['group'] ][ $id ] ) ) {
			$present[ $info['group'] ][ $id ] = $index;
		}
	}

	$drop    = array();
	$replace = array();

	foreach ( $present as $key => $in_list ) {
		$group  = $map['groups'][ $key ] ?? null;
		$colors = $group ? nwcs_color_site_colors( $group, $blog_id ) : array();

		if ( count( $colors ) < 2 ) {
			continue;
		}

		$visible = array_column( $colors, 'id' );
		$in_list = array_intersect_key( $in_list, array_flip( $visible ) );

		if ( ! $in_list ) {
			continue;
		}

		$lead = 0;

		foreach ( $visible as $id ) {
			if ( isset( $in_list[ $id ] ) ) {
				$lead = (int) $id;
				break;
			}
		}

		$slot = min( $in_list );

		foreach ( $in_list as $index ) {
			$drop[ $index ] = true;
		}

		unset( $drop[ $slot ] );
		$replace[ $slot ] = nwcs_color_decorate( $products[ $in_list[ $lead ] ], $group, $colors );
	}

	if ( ! $replace ) {
		return $products;
	}

	$out = array();

	foreach ( $products as $index => $row ) {
		if ( ! isset( $drop[ $index ] ) ) {
			$out[] = $replace[ $index ] ?? $row;
		}
	}

	return $out;
}

/**
 * Listede kalan satira renk bilgisini ekler.
 */
function nwcs_color_decorate( array $row, array $group, array $colors ): array {
	$name = '';

	foreach ( $colors as $index => $color ) {
		$colors[ $index ]['current'] = (int) $color['id'] === (int) $row['id'];

		if ( $colors[ $index ]['current'] ) {
			$name = $color['name'];
		}
	}

	$row['colors']      = $colors;
	$row['color_count'] = count( $colors );
	$row['color_label'] = (string) $group['label'];
	$row['color_name']  = $name;
	$row['card_title']  = nwcs_color_card_title( (string) $row['title'], $name );

	return $row;
}

/**
 * Listenin kart sayisi (renk gruplari tek kart). Menu ve kategori sayilari icin.
 */
function nwcs_color_count( array $products, ?int $blog_id = null ): int {
	return count( nwcs_color_collapse( $products, $blog_id ) );
}

/* ====================================================================== *
 * Yapisal veri
 * ====================================================================== */

/**
 * Urun sayfasinin JSON-LD eki (includes/seo.php, nwcs_seo_graph):
 *   product: Product dugumune eklenecek alanlar (color, inProductGroupWithID, isVariantOf);
 *   group:   ProductGroup dugumu (productGroupID, variesBy, hasVariant).
 * Urun sayfasi degilse ya da urun renk grubunda degilse bos dizi.
 *
 * @return array{product?: array, group?: array}
 */
function nwcs_color_seo( array $context ): array {
	$slug = sanitize_title( (string) get_query_var( 'nwcs_product' ) );

	if ( '' === $slug ) {
		return array();
	}

	$product = nwcs_site_product_by_slug( $slug );

	if ( ! $product || untrailingslashit( (string) $product['url'] ) !== untrailingslashit( (string) ( $context['url'] ?? '' ) ) ) {
		return array();
	}

	$options = nwcs_color_options( (int) $product['id'] );

	if ( ! $options ) {
		return array();
	}

	// Grup dugumunun kimligi her renk sayfasinda ayni: sitedeki ilk rengin adresi.
	$group_id = $options['colors'][0]['url'] . '#renk-grubu';

	return array(
		'product' => array(
			'color'                => $options['name'],
			'inProductGroupWithID' => $options['code'],
			'isVariantOf'          => array( '@id' => $group_id ),
		),
		'group'   => array(
			'@type'          => 'ProductGroup',
			'@id'            => $group_id,
			'name'           => (string) $options['colors'][0]['title'],
			'productGroupID' => $options['code'],
			'variesBy'       => 'https://schema.org/color',
			'hasVariant'     => array_map(
				static fn( array $color ): array => array(
					'@type' => 'Product',
					'@id'   => $color['url'] . '#product',
					'name'  => $color['title'],
					'url'   => $color['url'],
					'color' => $color['name'],
				),
				$options['colors']
			),
		),
	);
}
