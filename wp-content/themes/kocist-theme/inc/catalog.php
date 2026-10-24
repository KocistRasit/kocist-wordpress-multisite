<?php
/**
 * Kategori sayfalari ve site ici gezinme.
 *
 * Urun gruplari (Kereste, Ambalaj...) ust menunun "Urun grubu anahtari" dolu
 * satirlaridir. Hangi havuz kategorisinin hangi grubun altinda gorundugu
 * veridir: Ürün Havuzu -> Kategoriler'de "Sitelerde" kutusundan secilir
 * (eklenti, nwcs_site_category_tree). Grubun alt menusu yerlesik kategoriler
 * ile, varsa grubun acilir menu bilesenindeki (menu_hirdavat...) ek
 * baglantilardan olusur; sira manifestteki 'catalog' => 'defaults' sirasidir.
 *
 * Adresler:
 *   /kategoriler/                      tum gruplar
 *   /kategoriler/<grup>/               grubun butun urunleri
 *   /kategoriler/<grup>/<kategori>/    tek kategori
 *
 * Urunler merkezi havuzdan gelir; urunun yeri eklentiden (nwcs_product_place):
 * yerlesik bir kategorisi varsa o kategori, yoksa grubun kendi havuz
 * kategorisindeyse (Kereste, Ahşap Ambalaj...) dogrudan grup.
 *
 * Adresi havuz slug'ina gecen dort eski kategori ('catalog' => 'legacy':
 * kamelya, cardak, ahsap-sezlong, adirondack-sandalye) 301 ile yenisine gider.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Rewrite kurallari degistiginde surum artirilir; kurallar her sitede bir kez,
 * ilk istekte yeniden yazilir.
 */
const KOCIST_CATALOG_REWRITE_VERSION = '1';

add_action( 'init', 'kocist_catalog_rewrite' );
function kocist_catalog_rewrite(): void {
	add_rewrite_rule( '^kategoriler/?$', 'index.php?kocist_catalog=1', 'top' );
	add_rewrite_rule( '^kategoriler/([^/]+)/?$', 'index.php?kocist_catalog=1&kocist_group=$matches[1]', 'top' );
	add_rewrite_rule( '^kategoriler/([^/]+)/([^/]+)/?$', 'index.php?kocist_catalog=1&kocist_group=$matches[1]&kocist_sub=$matches[2]', 'top' );
}

add_action( 'init', 'kocist_catalog_maybe_flush', 99 );
function kocist_catalog_maybe_flush(): void {
	if ( KOCIST_CATALOG_REWRITE_VERSION === get_option( 'kocist_catalog_rewrite' ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'kocist_catalog_rewrite', KOCIST_CATALOG_REWRITE_VERSION );
}

add_filter( 'query_vars', 'kocist_catalog_query_vars' );
function kocist_catalog_query_vars( array $vars ): array {
	return array_merge( $vars, array( 'kocist_catalog', 'kocist_group', 'kocist_sub' ) );
}

/**
 * Kategori sayfasi mi goruntuleniyor?
 */
function kocist_is_catalog_request(): bool {
	return (bool) get_query_var( 'kocist_catalog' );
}

/* ------------------------------------------------------------------ */
/* Kategori agaci                                                       */
/* ------------------------------------------------------------------ */

/**
 * Urun gruplari ve alt kategorileri.
 *
 * Her alt oge: slug, name, url (kategori sayfasi), link (menude gidilecek yer),
 * menu_url (menuye yazilan ham adres), edit (onizlemede tiklaninca acilan alan:
 * nwcs_edit_attr argumanlari).
 *
 * @return array<string, array{slug:string, name:string, url:string, image:array, subs:array<string, array>, menu_row:int}>
 */
function kocist_catalog_groups(): array {
	static $groups = null;

	if ( null !== $groups ) {
		return $groups;
	}

	$groups  = array();
	$tree    = function_exists( 'nwcs_site_category_tree' ) ? nwcs_site_category_tree( get_current_blog_id() ) : array();
	$catalog = function_exists( 'nwcs_manifest' ) ? (array) ( nwcs_manifest()['catalog'] ?? array() ) : array();
	$rank    = array_flip( array_map( 'strval', array_keys( (array) ( $catalog['defaults'] ?? array() ) ) ) );
	$rows    = nwcs_rows( 'global', 'header', 'menu' );

	// Eskiden menude capa olan kategoriler: kayitli eski menude kalmis olsalar
	// da ek baglanti sayilmaz (kategori listesinden gelirler).
	$known = array_fill_keys( array_map( 'strval', array_keys( (array) ( $catalog['seed'] ?? array() ) ) ), true )
		+ array_fill_keys( array_map( 'strval', array_keys( (array) ( $catalog['legacy'] ?? array() ) ) ), true );

	foreach ( $tree as $slug => $parent ) {
		$row     = (array) ( $rows[ $parent['row'] ] ?? array() );
		$label   = trim( (string) ( $row['label'] ?? $parent['label'] ) );
		$submenu = trim( (string) ( $row['submenu'] ?? '' ) );
		$entries = array();

		foreach ( $parent['children'] as $sub_slug => $child ) {
			$page    = 'kat-' . $sub_slug;
			$name    = trim( (string) nwcs_field( $page, 'head', 'name' ) );
			$sub_url = home_url( "/kategoriler/{$slug}/{$sub_slug}/" );

			$entries[] = array(
				'rank' => $rank[ (string) $sub_slug ] ?? 100000,
				'sub'  => array(
					'slug'     => (string) $sub_slug,
					'name'     => '' !== $name ? $name : (string) $child['name'],
					'url'      => $sub_url,
					'link'     => $sub_url,
					'menu_url' => $sub_url,
					'edit'     => array( $page, 'head', 'name' ),
				),
			);
		}

		// Grubun acilir menu bilesenindeki ek baglantilar (havuzda karsiligi olmayan
		// metin kategorileri ya da baska sayfaya giden satirlar).
		foreach ( '' !== $submenu ? nwcs_rows( 'global', $submenu, 'items' ) : array() as $child_index => $child ) {
			$name = trim( (string) ( $child['label'] ?? '' ) );
			$url  = trim( (string) ( $child['url'] ?? '' ) );

			if ( '' === $name ) {
				continue;
			}

			$anchor   = kocist_is_dead_anchor( $url );
			$sub_slug = $anchor ? sanitize_title( substr( $url, 1 ) ) : sanitize_title( $name );

			if ( '' === $sub_slug || isset( $known[ $sub_slug ] ) || isset( $parent['children'][ $sub_slug ] ) ) {
				continue;
			}

			$sub_url = home_url( "/kategoriler/{$slug}/{$sub_slug}/" );

			$entries[] = array(
				'rank' => $rank[ $sub_slug ] ?? 200000 + (int) $child_index,
				'sub'  => array(
					'slug'     => $sub_slug,
					'name'     => $name,
					'url'      => $sub_url,
					// Panelde gercek bir adres yazildiysa menu oraya gider.
					'link'     => $anchor ? $sub_url : kocist_link( $url ),
					'menu_url' => $anchor ? $sub_url : $url,
					'edit'     => array( 'global', $submenu, 'items', (int) $child_index, 'label' ),
				),
			);
		}

		// Kararli siralama (PHP 8): ayni sirada olanlar eklendikleri sirayla.
		usort( $entries, static fn( array $a, array $b ): int => $a['rank'] <=> $b['rank'] );

		$subs = array();

		foreach ( $entries as $entry ) {
			$subs[ $entry['sub']['slug'] ] ??= $entry['sub'];
		}

		$groups[ $slug ] = array(
			'slug'     => (string) $slug,
			'name'     => '' !== $label ? $label : (string) $parent['label'],
			'url'      => home_url( "/kategoriler/{$slug}/" ),
			'image'    => kocist_catalog_group_image( (string) $slug, $label ),
			'subs'     => $subs,
			'menu_row' => (int) $parent['row'],
		);
	}

	return $groups;
}

/**
 * Alt kategorinin onizleme isareti: tiklaninca adinin duzenlendigi alan
 * (yerlesik kategoride kat-<slug> sayfasi, ek baglantida menu satiri).
 */
function kocist_sub_edit_attr( array $sub ): void {
	if ( ! empty( $sub['edit'] ) ) {
		nwcs_edit_attr( ...$sub['edit'] );
	}
}

/**
 * Menunun satir sirasindaki urun grubu; grup degilse null.
 */
function kocist_catalog_group_for_row( int $row_index ): ?array {
	foreach ( kocist_catalog_groups() as $group ) {
		if ( $group['menu_row'] === $row_index ) {
			return $group;
		}
	}

	return null;
}

/**
 * Grubun fotografi: ana sayfadaki urun grubu kartinda secilen gorsel, yoksa
 * temadaki grup fotografi.
 */
function kocist_catalog_group_image( string $slug, string $label ): array {
	$files = array(
		'kereste'    => 's2-kereste.jpg',
		'ambalaj'    => 's2-ambalaj.jpg',
		'dekorasyon' => 's2-dekorasyon.jpg',
		'hirdavat'   => 's2-hirdavat.jpg',
	);

	$file = $files[ $slug ] ?? 'atolye.jpg';

	foreach ( nwcs_rows( 'home', 'catalog', 'items' ) as $item ) {
		if ( str_contains( sanitize_title( (string) ( $item['title'] ?? '' ) ), $slug ) ) {
			return kocist_image_or_default( nwcs_image_by_id( (int) ( $item['image'] ?? 0 ), 'large' ), $file, $label );
		}
	}

	return kocist_image_or_default( array(), $file, $label );
}

/**
 * Grup fotografinin panelde duzenlendigi satir (home.catalog.items); yoksa -1.
 * Onizlemede kategori sayfasindaki grup gorseline tiklaninca bu satir acilir.
 */
function kocist_catalog_group_image_row( string $slug ): int {
	foreach ( nwcs_rows( 'home', 'catalog', 'items' ) as $index => $item ) {
		if ( str_contains( sanitize_title( (string) ( $item['title'] ?? '' ) ), $slug ) ) {
			return (int) $index;
		}
	}

	return -1;
}

/**
 * Bu siteye secilmis urunler, grup ve kategori bilgisiyle.
 *
 * Her urune 'group' ve 'sub' anahtarlari eklenir (bos olabilir).
 *
 * @return array<int, array>
 */
function kocist_catalog_products(): array {
	static $products = null;

	if ( null !== $products ) {
		return $products;
	}

	$products = array();

	if ( ! function_exists( 'nwcs_site_products' ) ) {
		return $products;
	}

	$groups = kocist_catalog_groups();

	foreach ( nwcs_site_products() as $product ) {
		$place            = function_exists( 'nwcs_product_place' ) ? nwcs_product_place( $product, get_current_blog_id() ) : array( 'parent' => null, 'child' => null );
		$group            = (string) $place['parent'];
		$sub              = (string) $place['child'];
		$product['group'] = isset( $groups[ $group ] ) ? $group : '';
		$product['sub']   = '' !== $product['group'] && isset( $groups[ $group ]['subs'][ $sub ] ) ? $sub : '';
		$products[]       = $product;
	}

	return $products;
}

/**
 * Kategori ya da grubun paneldeki sayfa anahtari; yoksa bos.
 */
function kocist_catalog_page_key( string $group, string $sub = '' ): string {
	$pages = function_exists( 'nwcs_manifest' ) ? ( nwcs_manifest()['pages'] ?? array() ) : array();
	$key   = '' !== $sub ? 'kat-' . $sub : 'grp-' . $group;

	return isset( $pages[ $key ] ) ? $key : '';
}

/**
 * Urun listesinin kartlari: ayni urunun renkleri (Ürün Havuzu -> Kategoriler
 * -> Renk grupları) tek kart olur; kartta one cikan urun ve "3 renk" etiketi
 * gorunur (eklenti, nwcs_color_collapse). Grup ya da kategori sayfasi, ana
 * sayfa seritleri ve menudeki sayilar bununla; urun sayfalari, site haritasi
 * ve llms.txt her rengi ayri urun olarak gorur (kocist_catalog_products).
 */
function kocist_catalog_cards( array $products ): array {
	return function_exists( 'nwcs_color_collapse' ) ? nwcs_color_collapse( $products ) : array_values( $products );
}

/**
 * Grup ya da kategoriye dusen urunler, kart listesi olarak (renk grubu tek kart).
 */
function kocist_catalog_products_in( string $group, string $sub = '' ): array {
	return kocist_catalog_cards(
		array_filter(
			kocist_catalog_products(),
			static fn( array $product ): bool => $product['group'] === $group && ( '' === $sub || $product['sub'] === $sub )
		)
	);
}

/**
 * Kategori basina kart sayisi (grup => [kategori => sayi, '' => toplam]).
 * Renk grubu, sayfadaki gibi tek urun sayilir.
 */
function kocist_catalog_counts(): array {
	static $counts = null;

	if ( null !== $counts ) {
		return $counts;
	}

	$counts = array();
	$lists  = array();

	foreach ( kocist_catalog_products() as $product ) {
		if ( '' === $product['group'] ) {
			continue;
		}

		$lists[ $product['group'] ][''][] = $product;

		if ( '' !== $product['sub'] ) {
			$lists[ $product['group'] ][ $product['sub'] ][] = $product;
		}
	}

	foreach ( $lists as $group => $subs ) {
		foreach ( $subs as $sub => $list ) {
			$counts[ $group ][ $sub ] = count( kocist_catalog_cards( $list ) );
		}
	}

	return $counts;
}

/**
 * Kart basligi: renk grubunda one cikan urunun adi, adinda renk yoksa sonunda
 * rengi ("Adirondack Sandalye – Antrasit"); grupta degilse urunun adi.
 */
function kocist_card_title( array $product ): string {
	return (string) ( $product['card_title'] ?? $product['title'] );
}

/**
 * Karttaki "3 renk" etiketi (panel: Ortak Metinler -> Ürün Kartında Renk
 * Sayısı); renk grubunda olmayan kartta bos.
 */
function kocist_card_colors( array $product, string $class ): void {
	$count = (int) ( $product['color_count'] ?? 0 );

	if ( $count < 2 ) {
		return;
	}
	?>
	<span class="<?php echo esc_attr( $class ); ?>" <?php nwcs_edit_attr( 'global', 'texts', 'colors' ); ?>><?php echo esc_html( kocist_text( 'global', 'texts', 'colors', array( 'sayi' => $count ) ) ); ?></span>
	<?php
}

/**
 * Urun karti (kategori ve grup sayfasi, arama sonuclari): gorsel, kategori,
 * ad, renk sayisi, kisa aciklama, fiyat ya da "Fiyat teklifle".
 *
 * @param bool $show_kind      Kartta urunun kategorisi yazsin mi (grup sayfasi, arama).
 * @param bool $group_fallback Kategorisi yoksa grubun adi yazsin mi (arama).
 */
function kocist_product_card( array $product, bool $show_kind = false, bool $group_fallback = false ): void {
	$groups   = kocist_catalog_groups();
	$group    = $groups[ $product['group'] ?? '' ] ?? null;
	$sub_item = $group['subs'][ $product['sub'] ?? '' ] ?? null;
	$has_page = kocist_product_has_page( $product );
	$href     = $has_page ? $product['url'] : kocist_quote_url( $product );
	$kind     = $show_kind ? (string) ( $sub_item['name'] ?? ( $group_fallback ? ( $group['name'] ?? '' ) : '' ) ) : '';
	?>
	<li class="k-pcard">
		<a class="k-pcard__link" href="<?php echo esc_url( $href ); ?>">
			<span class="k-pcard__media" <?php kocist_product_attr( $product, 'Görsel' ); ?>>
				<?php echo kocist_image_tag( kocist_product_image( $product ), 'k-pcard__img', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			</span>

			<span class="k-pcard__body">
				<?php if ( '' !== $kind ) : ?>
					<span class="k-pcard__kind" <?php $sub_item ? kocist_sub_edit_attr( $sub_item ) : nwcs_edit_attr( 'global', 'header', 'menu', (int) $group['menu_row'], 'label' ); ?>><?php echo esc_html( $kind ); ?></span>
				<?php endif; ?>

				<span class="k-pcard__title" <?php kocist_product_attr( $product, 'Ürün adı' ); ?>><?php echo esc_html( kocist_card_title( $product ) ); ?></span>

				<?php kocist_card_colors( $product, 'k-pcard__colors' ); ?>

				<?php if ( $product['short'] ) : ?>
					<span class="k-pcard__text" <?php kocist_product_attr( $product, 'Kısa açıklama' ); ?>><?php echo esc_html( $product['short'] ); ?></span>
				<?php endif; ?>

				<span class="k-pcard__foot">
					<?php if ( $product['has_price'] ) : ?>
						<span class="k-pcard__price" <?php kocist_product_attr( $product, 'Fiyat' ); ?>><?php echo esc_html( $product['price_label'] ); ?></span>
					<?php else : ?>
						<span class="k-pcard__price is-quote" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'price_quote' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'price_quote' ) ); ?></span>
					<?php endif; ?>
					<span class="k-pcard__go" <?php nwcs_edit_attr( 'kategoriler', 'texts', $has_page ? 'go_detail' : 'go_quote' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', $has_page ? 'go_detail' : 'go_quote' ) ); ?></span>
				</span>
			</span>
		</a>
	</li>
	<?php
}

/**
 * Serbest bir metne (slayt basligi gibi) en uygun kategori ya da grup adresi.
 *
 * Kategorinin adindaki anlamli kelimelerin hepsi metinde geciyorsa kategori
 * ("Plywood ve Kontrplak Levha" -> Plywood Levha); yoksa grup adi geciyorsa
 * grup ("Bahce ve Dekorasyon Urunleri" -> Dekorasyon). Bulunamazsa bos.
 */
function kocist_catalog_url_for_text( string $text ): string {
	$haystack = '-' . sanitize_title( $text ) . '-';
	$best     = '';
	$score    = 0;

	// "Ahsap" neredeyse her adda geciyor; eslesmeyi belirlemesin.
	$ignore = array( 'ahsap', 've', 'grubu', 'urunleri', 'levha' );

	foreach ( kocist_catalog_groups() as $group ) {
		foreach ( $group['subs'] as $sub ) {
			$words = array_diff( explode( '-', sanitize_title( $sub['name'] ) ), $ignore );
			$words = array_filter( $words, static fn( string $word ): bool => strlen( $word ) > 2 );

			if ( ! $words ) {
				continue;
			}

			foreach ( $words as $word ) {
				if ( ! str_contains( $haystack, '-' . $word . '-' ) ) {
					continue 2;
				}
			}

			if ( count( $words ) > $score ) {
				$score = count( $words );
				$best  = $sub['url'];
			}
		}
	}

	if ( '' !== $best ) {
		return $best;
	}

	foreach ( kocist_catalog_groups() as $group ) {
		if ( str_contains( $haystack, '-' . $group['slug'] . '-' ) ) {
			return $group['url'];
		}
	}

	return '';
}

/**
 * Panelde yazilmis baglanti hala varsayilan "katalog" capasi mi?
 * Oyleyse tema onu uygun kategori sayfasina baglar; panelde baska bir adres
 * yazildiysa her zaman o gecerlidir.
 */
function kocist_is_catalog_placeholder( $url ): bool {
	return in_array( trim( (string) $url ), array( '', '#', '#katalog', '/#katalog' ), true );
}

/* ------------------------------------------------------------------ */
/* Konum yolu (breadcrumb)                                              */
/* ------------------------------------------------------------------ */

/**
 * Kategori ya da urun icin konum yolu.
 *
 * @return array<int, array{name:string, url:string}> Son oge su anki sayfadir.
 */
function kocist_catalog_trail( string $group = '', string $sub = '', string $product = '', int $product_id = 0 ): array {
	$groups = kocist_catalog_groups();
	$trail  = array(
		array( 'name' => nwcs_field( 'global', 'texts', 'home_crumb' ), 'url' => home_url( '/' ), 'edit' => array( 'global', 'texts', 'home_crumb' ) ),
		array( 'name' => nwcs_field( 'kategoriler', 'index', 'crumb' ), 'url' => home_url( '/kategoriler/' ), 'edit' => array( 'kategoriler', 'index', 'crumb' ) ),
	);

	if ( isset( $groups[ $group ] ) ) {
		$trail[] = array(
			'name' => $groups[ $group ]['name'],
			'url'  => $groups[ $group ]['url'],
			'edit' => array( 'global', 'header', 'menu', $groups[ $group ]['menu_row'], 'label' ),
		);

		if ( isset( $groups[ $group ]['subs'][ $sub ] ) ) {
			$item    = $groups[ $group ]['subs'][ $sub ];
			$trail[] = array(
				'name' => $item['name'],
				'url'  => $item['url'],
				'edit' => $item['edit'],
			);
		}
	}

	if ( '' !== $product ) {
		$trail[] = array( 'name' => $product, 'url' => '', 'product' => $product_id );
	}

	return $trail;
}

/**
 * Konum yolunu basar. Son oge baglanti degil, aria-current="page".
 */
function kocist_the_trail( array $trail, string $class = '' ): void {
	$last = count( $trail ) - 1;
	?>
	<nav class="k-crumbs <?php echo esc_attr( $class ); ?>" aria-label="Konum">
		<ol class="k-crumbs__list">
			<?php foreach ( $trail as $index => $step ) : ?>
				<li class="k-crumbs__item">
					<?php
					// Onizlemede: adin geldigi yer (menu satiri, ortak metin ya da havuzdaki urun).
					ob_start();
					if ( ! empty( $step['product'] ) ) {
						kocist_product_attr( array( 'id' => $step['product'] ), 'Ürün adı' );
					} elseif ( ! empty( $step['edit'] ) ) {
						nwcs_edit_attr( ...$step['edit'] );
					}
					$attr = ob_get_clean();
					?>
					<?php if ( $index === $last ) : ?>
						<span aria-current="page" <?php echo $attr; // phpcs:ignore WordPress.Security.EscapingOutput -- nwcs_*_attr kacirir. ?>><?php echo esc_html( $step['name'] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $step['url'] ); ?>" <?php echo $attr; // phpcs:ignore WordPress.Security.EscapingOutput ?>><?php echo esc_html( $step['name'] ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/* ------------------------------------------------------------------ */
/* Sayfanin cizilmesi                                                   */
/* ------------------------------------------------------------------ */

/**
 * Istenen grup ve kategori; bilinmeyen adres icin null.
 *
 * @return array{group:?array, sub:?array}|null
 */
function kocist_catalog_current(): ?array {
	$groups = kocist_catalog_groups();
	$group  = sanitize_title( (string) get_query_var( 'kocist_group' ) );
	$sub    = sanitize_title( (string) get_query_var( 'kocist_sub' ) );

	if ( '' === $group ) {
		return array( 'group' => null, 'sub' => null );
	}

	if ( ! isset( $groups[ $group ] ) ) {
		return null;
	}

	if ( '' === $sub ) {
		return array( 'group' => $groups[ $group ], 'sub' => null );
	}

	if ( ! isset( $groups[ $group ]['subs'][ $sub ] ) ) {
		return null;
	}

	return array( 'group' => $groups[ $group ], 'sub' => $groups[ $group ]['subs'][ $sub ] );
}

// Eklentinin urun detayi (oncelik 5) ile ayni yontem: sablon basilip cikilir.
add_action( 'template_redirect', 'kocist_catalog_template', 6 );
function kocist_catalog_template(): void {
	if ( ! kocist_is_catalog_request() ) {
		return;
	}

	$current = kocist_catalog_current();

	if ( null === $current ) {
		// Adresi degisen eski kategori (kamelya -> kamelyalar): kalici yonlendirme.
		$legacy = (array) ( nwcs_manifest()['catalog']['legacy'] ?? array() );
		$old    = sanitize_title( (string) get_query_var( 'kocist_sub' ) );

		if ( '' !== $old && isset( $legacy[ $old ] ) ) {
			foreach ( kocist_catalog_groups() as $group ) {
				if ( isset( $group['subs'][ $legacy[ $old ] ] ) ) {
					wp_safe_redirect( $group['subs'][ $legacy[ $old ] ]['url'], 301 );
					exit;
				}
			}
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		include get_query_template( '404' ) ?: get_index_template();
		exit;
	}

	status_header( 200 );

	get_template_part( 'template-parts/category-page', null, $current );
	exit;
}

/**
 * Sekme basligi: kategori adi | site adi. SEO eklentisi kendi basligini
 * ek sayfa listesinden (asagida) verir; eklenti yoksa bu gecerli olur.
 */
add_filter( 'document_title_parts', 'kocist_catalog_title_parts' );
function kocist_catalog_title_parts( array $parts ): array {
	if ( ! kocist_is_catalog_request() ) {
		return $parts;
	}

	$current = kocist_catalog_current();

	if ( ! $current ) {
		return $parts;
	}

	$parts['title'] = $current['sub']['name'] ?? $current['group']['name'] ?? 'Kategoriler';
	$parts['site']  = get_bloginfo( 'name', 'display' );
	unset( $parts['tagline'] );

	return $parts;
}

/**
 * Grup ya da kategorinin panelde girilen "Arama ve Paylasim" degerleri
 * (grp-<grup> / kat-<kategori> gizli sayfasinin 'seo' bileseni). Bos alan bos
 * doner; o zaman temanin urettigi deger gecerli kalir.
 *
 * Eklenti gizli sayfalarin SEO alanlarini kendisi okumuyor (nwcs_seo_pages
 * gizlileri atlar); bu sayfalar ona ek sayfa olarak bildirildigi icin
 * degerleri tema aktarir.
 *
 * @return array{title:string, description:string, image:int}
 */
function kocist_catalog_seo_custom( string $group, string $sub = '' ): array {
	$key    = kocist_catalog_page_key( $group, $sub );
	$clean  = static fn( $text ): string => function_exists( 'nwcs_seo_clean' ) ? nwcs_seo_clean( $text ) : trim( wp_strip_all_tags( (string) $text ) );
	$custom = array( 'title' => '', 'description' => '', 'image' => 0 );

	if ( '' === $key ) {
		return $custom;
	}

	$custom['title']       = $clean( nwcs_field( $key, 'seo', 'title', '' ) );
	$custom['description'] = $clean( nwcs_field( $key, 'seo', 'description', '' ) );
	$image                 = (int) nwcs_field( $key, 'seo', 'image', 0 );
	$custom['image']       = $image > 0 && wp_attachment_is_image( $image ) ? $image : 0;

	return $custom;
}

/**
 * Goruntulenen grup ya da kategori sayfasinin paneldeki arama basligi; yoksa bos.
 */
function kocist_catalog_current_seo_title(): string {
	if ( ! kocist_is_catalog_request() ) {
		return '';
	}

	$current = kocist_catalog_current();

	if ( empty( $current['group'] ) ) {
		return '';
	}

	return kocist_catalog_seo_custom( $current['group']['slug'], $current['sub']['slug'] ?? '' )['title'];
}

/*
 * Paneldeki arama basligi, eklentinin normal sayfalarda yaptigi gibi oldugu
 * gibi kullanilir (site adi eklenmez). Bu duzenek, eklenti ek sayfalara baslik
 * vermeyi desteklemezken kuruldu:
 *   - sekme basligi: eklentinin suzgecinden (20) sonra,
 *   - og:title ve JSON-LD WebPage adi: eklentinin wp_head ciktisinda (1)
 *     yalnizca o iki deger degistirilir. Yeni etiket eklenmez, tekrar olmaz.
 * Panelde baslik bossa hicbiri calismaz; cikti eskisiyle aynidir.
 * Eklenti 0.26.0'dan beri ek sayfanin 'title' anahtarini aliyor (urun sayfalari
 * onu kullanir, asagida); grup/kategori satirlarina 'title' verilerek bu iki
 * kanca kaldirilabilir.
 */
add_filter( 'pre_get_document_title', 'kocist_catalog_document_title', 30 );
function kocist_catalog_document_title( $title ) {
	$custom = kocist_catalog_current_seo_title();

	// WordPress bu suzgecten gelen basligi kacirmadan basar.
	return '' !== $custom ? esc_html( $custom ) : $title;
}

add_action( 'wp_head', 'kocist_catalog_seo_head_start', 0 );
function kocist_catalog_seo_head_start(): void {
	if ( '' !== kocist_catalog_current_seo_title() && has_action( 'wp_head', 'nwcs_seo_head' ) ) {
		$GLOBALS['kocist_catalog_seo_buffer'] = ob_start();
	}
}

add_action( 'wp_head', 'kocist_catalog_seo_head_end', 2 );
function kocist_catalog_seo_head_end(): void {
	if ( empty( $GLOBALS['kocist_catalog_seo_buffer'] ) ) {
		return;
	}

	unset( $GLOBALS['kocist_catalog_seo_buffer'] );

	$html    = (string) ob_get_clean();
	$custom  = kocist_catalog_current_seo_title();
	$context = function_exists( 'nwcs_seo_context' ) ? nwcs_seo_context() : array();
	$old     = (string) ( $context['title'] ?? '' );

	if ( '' !== $old && $old !== $custom ) {
		$html = str_replace(
			sprintf( '<meta property="og:title" content="%s" />', esc_attr( $old ) ),
			sprintf( '<meta property="og:title" content="%s" />', esc_attr( $custom ) ),
			$html
		);

		// JSON-LD: yalnizca WebPage dugumunun adi (konum yolu adlari ayni kalir).
		// Dugum kimligiyle bulunur: kategori sorulari varsa turu ["WebPage","FAQPage"].
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
		$start = strpos( $html, '#webpage"' );
		$find  = '"name":' . wp_json_encode( $old, $flags );
		$at    = false !== $start ? strpos( $html, $find, $start ) : false;

		if ( false !== $at ) {
			$html = substr_replace( $html, '"name":' . wp_json_encode( $custom, $flags ), $at, strlen( $find ) );
		}
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapingOutput -- eklentinin kacirilmis ciktisi.
}

/**
 * /kategoriler/ alt metnindeki degiskenler: {grup} grup sayisi, {kategori}
 * toplam kategori sayisi. Sayfa ve SEO aciklamasi ayni sayilari kullanir.
 *
 * @return array{grup:int, kategori:int}
 */
function kocist_catalog_index_vars(): array {
	$groups = kocist_catalog_groups();

	return array(
		'grup'     => count( $groups ),
		'kategori' => array_sum( array_map( static fn( array $item ): int => count( $item['subs'] ), $groups ) ),
	);
}

/*
 * /kategoriler/ panelde bir sayfa ('kategoriler'); eklenti aciklamasini o
 * sayfanin alt metninden alir. Sayfadaki gibi doldurulsun: yoksa arama
 * sonucunda ve llms.txt'te ham "{grup} ürün grubunda {kategori} kategori"
 * gorunuyordu. Suzgec NWCS 0.27.5'ten beri var.
 */
add_filter( 'nwcs_seo_field_text', 'kocist_seo_field_text', 10, 4 );
function kocist_seo_field_text( string $text, string $page, string $component, string $field ): string {
	if ( 'kategoriler' !== $page || 'index' !== $component || 'lead' !== $field ) {
		return $text;
	}

	$map = array();

	foreach ( kocist_catalog_index_vars() as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}

	return trim( strtr( $text, $map ) );
}

/**
 * SEO eklentisine kategori ve urun sayfalarini bildirir: baslik, aciklama,
 * canonical, paylasim gorseli ve llms.txt bunlardan uretilir.
 */
add_filter( 'nwcs_seo_extra_pages', 'kocist_catalog_seo_pages' );
function kocist_catalog_seo_pages( array $pages ): array {
	$counts = kocist_catalog_counts();
	$groups = kocist_catalog_groups();

	if ( ! $groups ) {
		return $pages;
	}

	$pages[] = array(
		'url'         => home_url( '/kategoriler/' ),
		'name'        => 'Ürün Kategorileri',
		'description' => sprintf(
			'%s ürün gruplarımız ve alt kategorileri.',
			implode( ', ', wp_list_pluck( $groups, 'name' ) )
		),
	);

	foreach ( $groups as $group ) {
		$custom    = kocist_catalog_seo_custom( $group['slug'] );
		$sub_names = array_slice( wp_list_pluck( $group['subs'], 'name' ), 0, 8 );
		$pages[]   = array(
			'url'         => $group['url'],
			'name'        => $group['name'],
			// Kategorisiz grupta "ürünler: ." kalmasin.
			'description' => '' !== $custom['description'] ? $custom['description'] : (
				$sub_names
					? sprintf( '%s grubundaki ürünler: %s.', $group['name'], implode( ', ', $sub_names ) )
					: sprintf( '%s grubunda ölçü ve adede göre üretim ve tedarik.', $group['name'] )
			),
			'image'       => $custom['image'] ? $custom['image'] : ( $group['image']['url'] ? array( 'url' => $group['image']['url'], 'alt' => $group['image']['alt'] ) : 0 ),
		);

		foreach ( $group['subs'] as $sub ) {
			$count  = (int) ( $counts[ $group['slug'] ][ $sub['slug'] ] ?? 0 );
			$custom = kocist_catalog_seo_custom( $group['slug'], $sub['slug'] );
			$page   = array(
				'url'         => $sub['url'],
				'name'        => $sub['name'],
				'description' => '' !== $custom['description'] ? $custom['description'] : (
					$count
						? sprintf( '%s kategorisinde %d ürün. Ölçü ve adede göre fiyat için bize ulaşın.', $sub['name'], $count )
						: sprintf( '%s için ölçü ve adede göre fiyat teklifi alın.', $sub['name'] )
				),
			);

			if ( $custom['image'] ) {
				$page['image'] = $custom['image'];
			}

			$pages[] = $page;
		}
	}

	// Urun detay sayfalari: WordPress sorgusu bunlari yazi listesi sanmasin.
	foreach ( kocist_catalog_products() as $product ) {
		if ( ! kocist_product_has_page( $product ) ) {
			continue;
		}

		$image     = kocist_product_image( $product );
		$meta_desc = trim( (string) ( $product['meta_desc'] ?? '' ) );
		$pages[]   = array(
			'url'         => $product['url'],
			'name'        => $product['title'],
			// Havuzdaki meta baslik oldugu gibi; bossa eklenti "ad – site" uretir.
			'title'       => (string) ( $product['meta_title'] ?? '' ),
			'description' => '' !== $meta_desc ? $meta_desc : ( $product['short'] ?: ( trim( (string) $product['body'] ) ? wp_strip_all_tags( $product['body'] ) : $product['title'] ) ),
			'image'       => $image['url'] ? array( 'url' => $image['url'], 'alt' => $image['alt'] ) : 0,
			'type'        => 'Product',
			// Site haritasina girer (/urun/<slug>/ WordPress sayfasi degil).
			'sitemap'     => true,
			'sku'         => (string) ( $product['code'] ?? '' ),
			// Yalnizca duz tutar (kocist_price_number); fiyatsiz ya da "...'den baslayan" urunde 0: offers yazilmaz.
			'price'       => $product['has_price'] ? kocist_price_number( (string) $product['price'] ) : 0,
			'currency'    => kocist_price_currency( (string) $product['price'] ),
			// Sayfadaki Teknik Detaylar ile ayni kural (serbest not "Ölçü"; numarali aktarim artigi yok).
			'properties'  => array_map( static fn( array $pair ): array => array( 'name' => $pair[0], 'value' => $pair[1] ), kocist_product_table_specs( $product ) ),
			// Urun videosu: Product'a VideoObject olarak eklenir (sayfadaki galeriyle ayni video).
			'video'       => (array) ( $product['video'] ?? array() ),
		);
	}

	return $pages;
}
