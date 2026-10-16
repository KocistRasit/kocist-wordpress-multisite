<?php
/**
 * SEO ve GEO sekmesi (ag yonetimi).
 *
 * Iki gorunum:
 *   - Ag ozeti: butun sitelerin durumu tek tabloda ve siteler arasi firma
 *     bilgisi tutarliligi (ad, unvan, telefon, adres).
 *   - Site: firma bilgisi, varsayilanlar ve her sayfanin Google onizlemesiyle
 *     birlikte arama basligi / aciklamasi / paylasim gorseli.
 *
 * Yeni kaydetme kodu yok: formlar Icerik Studyosu'nun AJAX kaydetme yolunu
 * (nwcs_save_ajax) ve ayni guvenlik zincirini kullanir. SEO alanlari
 * manifeste eklenti tarafindan eklenen alanlardir (includes/manifest.php).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_SEO_SLUG = 'nwcs-seo';

/**
 * "Gelistirici" ust menusu: son kullanicinin ihtiyac duymadigi ekranlar
 * (SEO ve GEO, Yonlendirmeler, Toplu Guncelleme, Gorsel Yer Tutucu,
 * E-posta Testi, Havuz Paketi) burada toplanir. Menunun kendi sayfasi
 * SEO ve GEO'dur; boylece sayfa adresleri (admin.php?page=...) degismez.
 */
const NWCS_DEV_MENU_SLUG = NWCS_SEO_SLUG;

add_action( 'network_admin_menu', 'nwcs_register_seo_menu' );
function nwcs_register_seo_menu(): void {
	add_menu_page(
		'SEO ve GEO',
		'Geliştirici',
		NWCS_CAPABILITY,
		NWCS_DEV_MENU_SLUG,
		'nwcs_render_seo',
		'dashicons-admin-tools',
		98 // Ag menusunun en alti (Ayarlar 25, ayirici 99).
	);
	// Ilk alt ogenin adi ust menuyle ayni olmasin diye acikca eklenir.
	add_submenu_page( NWCS_DEV_MENU_SLUG, 'SEO ve GEO', 'SEO ve GEO', NWCS_CAPABILITY, NWCS_SEO_SLUG, 'nwcs_render_seo' );
}

add_action( 'admin_enqueue_scripts', 'nwcs_seo_admin_assets', 20 );
function nwcs_seo_admin_assets( string $hook ): void {
	// Studyo'da da yuklenir: sayfalarin "Arama ve Paylasim" bolumu orada da var.
	// Urun Havuzu'nda urun formunun meta alanlari sayac kullanir.
	if ( str_contains( $hook, NWCS_SEO_SLUG ) || str_contains( $hook, NWCS_MENU_SLUG ) || str_contains( $hook, NWCS_POOL_SLUG ) ) {
		wp_enqueue_script( 'nwcs-seo', NWCS_URL . 'assets/seo.js', array( 'nwcs-admin' ), NWCS_VERSION, true );
	}
}

/**
 * Sekme adresi. $blog_id 0 ise ag ozeti.
 */
function nwcs_seo_url( int $blog_id = 0 ): string {
	$args = array( 'page' => NWCS_SEO_SLUG );

	if ( $blog_id ) {
		$args['site'] = $blog_id;
	}

	return add_query_arg( $args, network_admin_url( 'admin.php' ) );
}

/* ====================================================================== *
 * Durum raporu
 * ====================================================================== */

/**
 * Firma bilgisinde bulunmasi gereken alanlar ve Turkce adlari.
 */
function nwcs_seo_required_org_fields(): array {
	return array(
		'name'        => 'firma adı',
		'legal_name'  => 'resmî unvan',
		'description' => 'firma tanımı',
		'phone'       => 'telefon',
		'street'      => 'adres',
		'city'        => 'il',
	);
}

/**
 * Bir sitenin SEO durumu. Site baglamina gecip geri doner.
 */
function nwcs_seo_site_report( int $blog_id ): array {
	switch_to_blog( $blog_id );

	$manifest = nwcs_manifest();
	$pages    = array();
	$custom   = 0;
	$empty    = 0;

	foreach ( nwcs_seo_pages( $manifest ) as $key => $page ) {
		$resolved = nwcs_seo_page_resolved( $key, $manifest );

		if ( '' !== $resolved['custom']['title'] || '' !== $resolved['custom']['description'] ) {
			++$custom;
		}

		if ( '' === $resolved['description'] ) {
			++$empty;
		}

		$pages[ $key ] = array(
			'label'    => $page['label'],
			'url'      => home_url( $page['path'] ),
			'resolved' => $resolved,
		);
	}

	$org     = nwcs_seo_org_data();
	$missing = array();

	foreach ( nwcs_seo_required_org_fields() as $key => $label ) {
		if ( '' === ( 'name' === $key ? nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'name' ) ) : $org[ $key ] ) ) {
			$missing[] = $label;
		}
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		)
	);

	$report = array(
		'pages'   => $pages,
		'custom'  => $custom,
		'empty'   => $empty,
		'org'     => $org,
		'missing' => $missing,
		'public'  => (bool) get_option( 'blog_public' ),
		'home'    => home_url( '/' ),
		'sitemap' => home_url( '/wp-sitemap.xml' ),
		'llms'    => home_url( '/llms.txt' ),
		'reading' => admin_url( 'options-reading.php' ),
		'posts'   => array_map(
			static fn( WP_Post $post ): array => array(
				'title'       => nwcs_seo_clean( get_the_title( $post ) ),
				'url'         => (string) get_permalink( $post ),
				'edit'        => (string) get_edit_post_link( $post, 'raw' ),
				'description' => nwcs_seo_clean( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content, 160 ),
				'own'         => has_excerpt( $post ),
			),
			$posts
		),
	);

	restore_current_blog();

	return $report;
}

/**
 * Siteler arasi firma bilgisi tutarliligi. Ayni firmanin sitelerinde unvan,
 * telefon ve adres birebir ayni yazilmali; bos alanlar karsilastirilmaz.
 *
 * @return array<string, array{label:string, values:array<string, string[]>}>
 */
function nwcs_seo_consistency( array $reports, array $sites ): array {
	$checks = array(
		'legal_name' => 'Resmî unvan',
		'phone'      => 'Telefon',
		'address'    => 'Adres',
	);

	$result = array();

	foreach ( $checks as $key => $label ) {
		$values = array();

		foreach ( $reports as $blog_id => $report ) {
			$org   = $report['org'];
			$value = 'address' === $key
				? implode( ', ', array_filter( array( $org['street'], $org['district'], $org['city'] ) ) )
				: $org[ $key ];

			if ( '' === $value ) {
				continue;
			}

			// Bosluk ve buyuk-kucuk harf farki tutarsizlik sayilmaz.
			$normal = mb_strtolower( (string) preg_replace( '/[\s.,]+/u', ' ', $value ) );

			$values[ $normal ]['text']    = $value;
			$values[ $normal ]['sites'][] = $sites[ $blog_id ]['label'];
		}

		$result[ $key ] = array(
			'label'  => $label,
			'values' => array_values( $values ),
		);
	}

	return $result;
}

/* ====================================================================== *
 * Uyumluluk puani
 *
 * Her site icin 0-100 arasi puan: SEO (arama motorlari) ve GEO (yapay zeka
 * aramalari) alt puanlari ile her kontrolun durumu ve duzeltme onerisi.
 * Yalnizca okur; veritabanina yazmaz. Siteye uymayan kontrol (ornegin urun
 * sayfasi olmayan sitede urun ozellikleri) puana katilmaz, eksik sayilmaz.
 * ====================================================================== */

/**
 * Puan bandi: 85 ve ustu guclu, 60-84 orta, altinda zayif.
 *
 * @return array{key:string, label:string}
 */
function nwcs_seo_score_band( int $score ): array {
	if ( $score >= 85 ) {
		return array( 'key' => 'strong', 'label' => 'Güçlü' );
	}

	if ( $score >= 60 ) {
		return array( 'key' => 'medium', 'label' => 'Orta' );
	}

	return array( 'key' => 'weak', 'label' => 'Zayıf' );
}

/**
 * Aktif sitenin temasi (ya da ust temasi) functions.php dosyasinda verilen
 * suzgece baglaniyor mu. Ag yonetiminde sitenin temasi yuklu olmadigindan
 * suzgec calistirilamaz; temanin bu bilgiyi verdigi dosyadan anlasilir.
 */
function nwcs_seo_score_theme_declares( string $hook ): bool {
	static $sources = array();

	$found = false;

	foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
		$file = $dir . '/functions.php';

		if ( ! isset( $sources[ $file ] ) ) {
			$sources[ $file ] = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions -- yerel tema dosyasi.
		}

		if ( str_contains( $sources[ $file ], "'" . $hook . "'" ) ) {
			$found = true;
		}
	}

	return $found;
}

/**
 * Butun sitelerin firma bilgisi ve siteler arasi tutarlilik (istek boyunca
 * bir kez hesaplanir).
 *
 * @return array{sites:array, consistency:array}
 */
function nwcs_seo_score_network(): array {
	static $network = null;

	if ( null !== $network ) {
		return $network;
	}

	$sites   = nwcs_editable_sites();
	$reports = array();

	foreach ( array_keys( $sites ) as $id ) {
		switch_to_blog( $id );
		$reports[ $id ] = array( 'org' => nwcs_seo_org_data() );
		restore_current_blog();
	}

	$network = array(
		'sites'       => $sites,
		'consistency' => nwcs_seo_consistency( $reports, $sites ),
	);

	return $network;
}

/**
 * Bir sitenin bilgisi, agdaki baska bir sitedekinin kucuk farkla yazilmis
 * hali mi. Tamamen farkli degerler (baska sube, baska sirket) sorun degil;
 * ayni bilginin iki yazimi sorun.
 *
 * @return string[] Farkli yazildigi alanlarin adlari.
 */
function nwcs_seo_score_near_variants( int $blog_id ): array {
	$network = nwcs_seo_score_network();
	$label   = $network['sites'][ $blog_id ]['label'] ?? '';
	$found   = array();

	$normal = static fn( string $text ): string => mb_strtolower( (string) preg_replace( '/[\s.,]+/u', ' ', $text ) );
	$digits = static fn( string $text ): string => (string) preg_replace( '/\D+/', '', $text );

	foreach ( $network['consistency'] as $key => $check ) {
		$own = null;

		foreach ( $check['values'] as $variant ) {
			if ( in_array( $label, $variant['sites'], true ) ) {
				$own = $variant['text'];
			}
		}

		if ( null === $own ) {
			continue;
		}

		foreach ( $check['values'] as $variant ) {
			if ( $variant['text'] === $own ) {
				continue;
			}

			if ( 'phone' === $key ) {
				// Telefon: rakamlari ayni, bicimi farkli.
				$near = $digits( $variant['text'] ) === $digits( $own );
			} else {
				similar_text( $normal( $variant['text'] ), $normal( $own ), $percent );
				$near = $percent >= 85;
			}

			if ( $near ) {
				$found[] = mb_strtolower( $check['label'] );
				break;
			}
		}
	}

	return $found;
}

/**
 * Sitenin SEO ve GEO uyumluluk puani.
 *
 * Her kontrol: key, group (seo|geo), label, weight, passed (0..1), detail
 * (kisa durum, ornegin "8/10 sayfa") ve hint (tam gecmediyse ne yapilmali).
 * Puan = gecen agirlik / uygulanan agirlik. SEO kontrollerinin agirlik
 * toplami 60, GEO 40.
 *
 * @return array{score:int, seo:int, geo:int, band:array, checks:array, notes:string[]}
 */
function nwcs_seo_score( int $blog_id ): array {
	static $cache = array();

	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	// Once ag geneli (kendi icinde siteler arasi gecer), sonra bu site.
	$near_variants = nwcs_seo_score_near_variants( $blog_id );
	$site_count    = count( nwcs_seo_score_network()['sites'] );
	$loaded_theme  = get_stylesheet();

	switch_to_blog( $blog_id );

	$manifest  = nwcs_manifest();
	$org       = nwcs_seo_org_data();
	$site_name = nwcs_seo_site_name();
	$raw_name  = nwcs_seo_clean( nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'name' ) );
	$public    = (bool) get_option( 'blog_public' );
	$notes     = array();

	// Sitenin temasi bu istekte yukluyse (on yuz, wp-cli --url) suzgecleri
	// dogrudan calisir; ag yonetiminde temanin dosyasina bakilir.
	$theme_live = get_stylesheet() === $loaded_theme;
	$theme_has  = static fn( string $hook ): bool => $theme_live
		? (bool) nwcs_seo_image_any( apply_filters( $hook, 0 ) )
		: nwcs_seo_score_theme_declares( $hook );

	$logo = (bool) nwcs_seo_image( nwcs_seo_logo_id() ) || $theme_has( 'nwcs_seo_default_logo' );

	$fallback_image = (int) nwcs_field( NWCS_SEO_SITE_PAGE, 'defaults', 'share_image', 0 )
		|| $logo
		|| ( isset( $manifest['pages']['home'] ) && nwcs_seo_page_auto( 'home', $manifest )['image'] )
		|| $theme_has( 'nwcs_seo_default_image' );

	// Sayfalar: manifest sayfalari ve temanin bildirdigi ek sayfalar.
	$pages         = array();
	$faq_page      = false;
	$faq_items     = 0;
	$products      = 0;
	$products_spec = 0;
	$no_specs      = array();

	foreach ( nwcs_seo_pages( $manifest ) as $key => $page ) {
		$resolved = nwcs_seo_page_resolved( $key, $manifest );
		$type     = (string) ( $page['seo_source']['type'] ?? '' );

		$pages[] = array(
			'name'        => $resolved['name'],
			'title'       => $resolved['title'],
			'description' => $resolved['description'],
			'image'       => (bool) $resolved['image'],
		);

		if ( 'FAQPage' === $type ) {
			$faq_page   = true;
			$faq_items += count( nwcs_seo_faq( $key ) );
		} elseif ( 'Product' === $type ) {
			++$products;

			if ( nwcs_seo_product_specs( $key ) ) {
				++$products_spec;
			} else {
				$no_specs[] = $resolved['name'];
			}
		}
	}

	if ( $theme_live ) {
		foreach ( nwcs_seo_extra_pages() as $extra ) {
			$name  = nwcs_seo_clean( $extra['name'] );
			$title = nwcs_seo_clean( (string) ( $extra['title'] ?? '' ) );

			// On yuzle ayni kural (nwcs_seo_context_extra): tema basligi verdiyse o.
			$pages[] = array(
				'name'        => $name,
				'title'       => '' !== $title ? $title : nwcs_seo_title_with_site( $name, $site_name ),
				'description' => nwcs_seo_clean( (string) ( $extra['description'] ?? '' ), 160 ),
				'image'       => (bool) nwcs_seo_image_any( $extra['image'] ?? 0 ),
			);

			if ( 'Product' === ( $extra['type'] ?? '' ) ) {
				++$products;

				if ( nwcs_seo_product_specs( '', (array) ( $extra['properties'] ?? array() ) ) ) {
					++$products_spec;
				} else {
					$no_specs[] = $name;
				}
			}
		}
	} elseif ( nwcs_seo_score_theme_declares( 'nwcs_seo_extra_pages' ) ) {
		$notes[] = 'Temanın kendi oluşturduğu ek sayfalar (ör. ürün alt sayfaları) bu ekrandan ölçülemiyor; puan yalnızca İçerik Stüdyosu’nda düzenlenen sayfalara göre hesaplandı.';
	}

	restore_current_blog();

	// Sayfa bazli oranlar.
	$count      = count( $pages );
	$long_title = array();
	$bad_desc   = array();
	$desc_sum   = 0.0;
	$no_image   = 0;

	foreach ( $pages as $page ) {
		if ( '' === $page['title'] || mb_strlen( $page['title'] ) > 60 ) {
			$long_title[] = $page['name'];
		}

		$length = mb_strlen( $page['description'] );

		if ( $length >= 70 && $length <= 160 ) {
			$desc_sum += 1;
		} else {
			// Kisa ya da uzun aciklama yarim puan; hic yoksa sifir.
			$desc_sum  += $length > 0 ? 0.5 : 0;
			$bad_desc[] = $page['name'];
		}

		if ( ! $page['image'] && ! $fallback_image ) {
			++$no_image;
		}
	}

	$list = static function ( array $names ): string {
		$names = array_values( array_unique( array_filter( $names ) ) );
		$more  = count( $names ) - 3;

		return implode( ', ', array_slice( $names, 0, 3 ) ) . ( $more > 0 ? ' ve ' . $more . ' sayfa daha' : '' );
	};

	$street_city     = '' !== $org['street'] && '' !== $org['city'];
	$description_len = mb_strlen( $org['description'] );
	$coordinates     = is_numeric( str_replace( ',', '.', $org['latitude'] ) ) && is_numeric( str_replace( ',', '.', $org['longitude'] ) );

	$checks = array();

	$add = static function ( string $key, string $group, string $label, int $weight, float $passed, string $detail, string $hint ) use ( &$checks ): void {
		$checks[] = array(
			'key'    => $key,
			'group'  => $group,
			'label'  => $label,
			'weight' => $weight,
			'passed' => max( 0.0, min( 1.0, $passed ) ),
			'detail' => $detail,
			'hint'   => $hint,
		);
	};

	/* SEO: arama motorlari (agirlik toplami 60) */

	$add( 'public', 'seo', 'Site arama motorlarına açık', 12, $public ? 1 : 0, $public ? 'Açık' : 'Kapalı',
		'Ayarlar › Okuma sayfasında “Arama motorlarının bu siteyi dizine eklemesini engelle” kutusunun işaretini kaldırın.' );

	$add( 'name', 'seo', 'Firma adı', 4, '' !== $raw_name ? 1 : 0.5, '' !== $raw_name ? $raw_name : 'Site başlığı kullanılıyor',
		'Firma bilgisine aramalarda görünecek kısa firma adını yazın.' );

	$add( 'phone', 'seo', 'Telefon', 6, '' !== $org['phone'] ? 1 : 0, '' !== $org['phone'] ? $org['phone'] : 'Girilmemiş',
		'Firma bilgisine telefonu uluslararası biçimde girin (+90 …).' );

	$add( 'address', 'seo', 'Açık adres ve il', 7, $street_city ? 1 : ( '' !== $org['street'] || '' !== $org['city'] ? 0.5 : 0 ),
		$street_city ? 'Girilmiş' : 'Eksik',
		'Firma bilgisine açık adresi ve ili girin; Google Haritalar eşleşmesi bu bilgiyle olur.' );

	$add( 'postal_code', 'seo', 'Posta kodu', 3, '' !== $org['postal_code'] ? 1 : 0, '' !== $org['postal_code'] ? $org['postal_code'] : 'Girilmemiş',
		'Firma bilgisine posta kodunu ekleyin.' );

	$add( 'logo', 'seo', 'Logo', 5, $logo ? 1 : 0, $logo ? 'Var' : 'Yok',
		'Firma bilgisine logoyu yükleyin; arama sonucundaki firma kutusunda görünür.' );

	if ( $count ) {
		$add( 'titles', 'seo', 'Sayfa başlıkları 60 karakteri aşmıyor', 8, ( $count - count( $long_title ) ) / $count,
			( $count - count( $long_title ) ) . '/' . $count . ' sayfa',
			'Uzun başlıklar arama sonucunda kesilir. Kısaltın: ' . $list( $long_title ) . '.' );

		$add( 'descriptions', 'seo', 'Sayfa açıklamaları 70–160 karakter', 10, $desc_sum / $count,
			( $count - count( $bad_desc ) ) . '/' . $count . ' sayfa',
			'Boş, çok kısa ya da uzun açıklamaları düzeltin: ' . $list( $bad_desc ) . '.' );

		$add( 'images', 'seo', 'Sayfaların paylaşım görseli', 5, ( $count - $no_image ) / $count,
			( $count - $no_image ) . '/' . $count . ' sayfa',
			'Varsayılanlar bölümüne bir paylaşım görseli (1200 × 630) yükleyin; görseli olmayan sayfalar onu kullanır.' );
	}

	/* GEO: yapay zeka aramalari (agirlik toplami 40) */

	$add( 'description', 'geo', 'Firma tanımı (en az 80 karakter)', 8, $description_len >= 80 ? 1 : ( $description_len > 0 ? 0.5 : 0 ),
		$description_len > 0 ? $description_len . ' karakter' : 'Girilmemiş',
		'Firma bilgisine ne ürettiğinizi, nerede ve kime hizmet verdiğinizi anlatan bir iki cümle yazın; yapay zekâ aramaları bu cümleyi alıntılar.' );

	$add( 'legal_name', 'geo', 'Resmî unvan', 5, '' !== $org['legal_name'] ? 1 : 0, '' !== $org['legal_name'] ? 'Girilmiş' : 'Girilmemiş',
		'Firma bilgisine resmî unvanı, ağdaki diğer sitelerle birebir aynı yazılışla girin.' );

	$add( 'llms', 'geo', 'llms.txt yayında', 5, $public ? 1 : 0, $public ? 'Yayında' : 'Site kapalı olduğu için yayında değil',
		'llms.txt yalnızca arama motorlarına açık sitede yayınlanır; önce siteyi arama motorlarına açın.' );

	$relation = '' !== $org['parent_name'] || $org['same_as'];
	$add( 'relation', 'geo', 'Grup bağlantısı ya da firmanın diğer hesapları', 5, $relation ? 1 : 0,
		'' !== $org['parent_name'] ? $org['parent_name'] : ( $org['same_as'] ? count( $org['same_as'] ) . ' adres' : 'Yok' ),
		'“Bağlı olduğu grup” alanını ya da firmanın sosyal medya, Google İşletme gibi kendi adreslerini girin.' );

	$add( 'coordinates', 'geo', 'Harita konumu (enlem ve boylam)', 5, $coordinates ? 1 : 0, $coordinates ? 'Girilmiş' : 'Girilmemiş',
		'Google Haritalar’da işletmeye sağ tıklayın; çıkan enlem ve boylamı firma bilgisine girin.' );

	$add( 'faq', 'geo', 'Sık sorulan sorular', 5, $faq_items ? 1 : 0,
		$faq_items ? $faq_items . ' soru' : ( $faq_page ? 'Sayfa boş' : 'Sayfa yok' ),
		$faq_page
			? 'Sık sorulan sorular sayfasına soru ve cevap ekleyin; yapay zekâ aramaları en çok bu tür içerikten alıntı yapar.'
			: 'Temada soru-cevap sayfası yok; eklenmesi için geliştiriciye haber verin. Yapay zekâ aramaları en çok bu tür içerikten alıntı yapar.' );

	if ( $products ) {
		$add( 'specs', 'geo', 'Ürünlerde teknik özellikler', 4, $products_spec / $products, $products_spec . '/' . $products . ' ürün',
			'Ölçü, ağaç türü gibi özellik satırlarını boş olan ürünlere ekleyin: ' . $list( $no_specs ) . '.' );
	}

	if ( $site_count > 1 ) {
		$add( 'consistency', 'geo', 'Siteler arası tutarlı yazım', 3, $near_variants ? 0 : 1,
			$near_variants ? 'Farklı yazım: ' . implode( ', ', $near_variants ) : 'Tutarlı',
			'Ağdaki başka bir sitede aynı bilginin biraz farklı yazımı var (' . implode( ', ', $near_variants ) . '). Ağ özetindeki tutarlılık kartından karşılaştırın.' );
	}

	// Puanlar: gecen agirlik / uygulanan agirlik.
	$sum = static function ( ?string $group ) use ( $checks ): int {
		$total  = 0;
		$earned = 0.0;

		foreach ( $checks as $check ) {
			if ( null === $group || $group === $check['group'] ) {
				$total  += $check['weight'];
				$earned += $check['weight'] * $check['passed'];
			}
		}

		return $total ? (int) round( 100 * $earned / $total ) : 0;
	};

	$score = $sum( null );

	$cache[ $blog_id ] = array(
		'score'  => $score,
		'seo'    => $sum( 'seo' ),
		'geo'    => $sum( 'geo' ),
		'band'   => nwcs_seo_score_band( $score ),
		'checks' => $checks,
		'notes'  => $notes,
	);

	return $cache[ $blog_id ];
}

/**
 * Kontrolun durumu: tam, kismen, eksik.
 *
 * @return array{key:string, label:string}
 */
function nwcs_seo_check_state( array $check ): array {
	if ( $check['passed'] >= 1 ) {
		return array( 'key' => 'pass', 'label' => 'Tamam' );
	}

	return $check['passed'] > 0
		? array( 'key' => 'partial', 'label' => 'Kısmen' )
		: array( 'key' => 'fail', 'label' => 'Eksik' );
}

/**
 * Kucuk puan gostergesi (ag ozeti tablosu).
 */
function nwcs_render_seo_score_gauge( array $score ): void {
	?>
	<div class="nwcs-score nwcs-score--<?php echo esc_attr( $score['band']['key'] ); ?>">
		<div class="nwcs-score__row">
			<strong class="nwcs-score__num"><?php echo (int) $score['score']; ?></strong>
			<span class="nwcs-score__band"><?php echo esc_html( $score['band']['label'] ); ?></span>
		</div>
		<div class="nwcs-score__bar" role="meter" aria-valuemin="0" aria-valuemax="100"
			aria-valuenow="<?php echo esc_attr( (string) $score['score'] ); ?>"
			aria-valuetext="<?php echo esc_attr( $score['score'] . ' / 100, ' . $score['band']['label'] ); ?>"
			aria-label="Uyumluluk puanı">
			<span style="width: <?php echo esc_attr( (string) $score['score'] ); ?>%"></span>
		</div>
		<span class="nwcs-table__spec">SEO <?php echo (int) $score['seo']; ?> · GEO <?php echo (int) $score['geo']; ?></span>
	</div>
	<?php
}

/**
 * Site gorunumundeki puan karti: buyuk puan, alt puanlar ve kontrol listesi.
 */
function nwcs_render_seo_score_card( array $score ): void {
	$groups = array(
		'seo' => array( 'label' => 'SEO', 'lead' => 'Arama motorları' ),
		'geo' => array( 'label' => 'GEO', 'lead' => 'Yapay zekâ aramaları' ),
	);
	?>
	<section class="nwcs-pool__card nwcs-scorecard nwcs-score--<?php echo esc_attr( $score['band']['key'] ); ?>" aria-labelledby="nwcs-scorecard-title">
		<h2 class="nwcs-pool__title" id="nwcs-scorecard-title">Uyumluluk puanı</h2>

		<div class="nwcs-scorecard__head">
			<div class="nwcs-scorecard__total">
				<span class="nwcs-scorecard__num"><?php echo (int) $score['score']; ?></span><span class="nwcs-scorecard__of">/100</span>
				<span class="nwcs-score__band"><?php echo esc_html( $score['band']['label'] ); ?></span>
			</div>

			<div class="nwcs-scorecard__subs">
				<?php foreach ( $groups as $group => $info ) : ?>
					<div class="nwcs-scorecard__sub nwcs-score--<?php echo esc_attr( nwcs_seo_score_band( (int) $score[ $group ] )['key'] ); ?>">
						<span class="nwcs-scorecard__sub-label">
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<?php echo esc_html( $info['lead'] ); ?>
						</span>
						<meter class="nwcs-scorecard__meter" min="0" max="100" low="60" high="85" optimum="100"
							value="<?php echo esc_attr( (string) $score[ $group ] ); ?>"
							aria-label="<?php echo esc_attr( $info['label'] . ' puanı' ); ?>"></meter>
						<span class="nwcs-scorecard__sub-num"><?php echo (int) $score[ $group ]; ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php foreach ( $groups as $group => $info ) :
			$checks = array_filter( $score['checks'], static fn( array $check ): bool => $group === $check['group'] );
			$open   = array_filter( $checks, static fn( array $check ): bool => $check['passed'] < 1 );
			$done   = array_filter( $checks, static fn( array $check ): bool => $check['passed'] >= 1 );
			?>
			<h3 class="nwcs-scorecard__group"><?php echo esc_html( $info['label'] . ' · ' . $info['lead'] ); ?></h3>

			<?php if ( $open ) : ?>
				<ul class="nwcs-scorecard__list">
					<?php foreach ( $open as $check ) :
						$state = nwcs_seo_check_state( $check );
						?>
						<li class="nwcs-scorecard__item is-<?php echo esc_attr( $state['key'] ); ?>">
							<span class="nwcs-scorecard__mark" aria-hidden="true"></span>
							<div>
								<div class="nwcs-scorecard__line">
									<strong><?php echo esc_html( $check['label'] ); ?></strong>
									<span class="nwcs-scorecard__state"><?php echo esc_html( $state['label'] ); ?></span>
								</div>
								<span class="nwcs-table__spec"><?php echo esc_html( $check['detail'] ); ?> · <?php echo (int) round( $check['weight'] * ( 1 - $check['passed'] ) ); ?> puan kayıp</span>
								<p class="nwcs-scorecard__hint"><?php echo esc_html( $check['hint'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $done ) : ?>
				<details class="nwcs-scorecard__done"<?php echo $open ? '' : ' open'; ?>>
					<summary>Tamamlananlar (<?php echo (int) count( $done ); ?>)</summary>
					<ul class="nwcs-scorecard__list">
						<?php foreach ( $done as $check ) : ?>
							<li class="nwcs-scorecard__item is-pass">
								<span class="nwcs-scorecard__mark" aria-hidden="true"></span>
								<div class="nwcs-scorecard__line">
									<span><?php echo esc_html( $check['label'] ); ?></span>
									<span class="nwcs-scorecard__state"><span class="screen-reader-text">Tamam: </span><?php echo esc_html( $check['detail'] ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php foreach ( $score['notes'] as $note ) : ?>
			<p class="nwcs-hint"><?php echo esc_html( $note ); ?></p>
		<?php endforeach; ?>
	</section>
	<?php
}

/* ====================================================================== *
 * Arayuz
 * ====================================================================== */

function nwcs_render_seo(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$sites   = nwcs_editable_sites();
	$blog_id = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum.

	if ( ! isset( $sites[ $blog_id ] ) ) {
		$blog_id = 0;
	}
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-seo" data-nwcs-site="<?php echo esc_attr( (string) $blog_id ); ?>">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu
					aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>SEO ve GEO</h1>
			</div>

			<nav class="nwcs-bar__sites" aria-label="Görünüm seçici">
				<a class="nwcs-sitepill<?php echo 0 === $blog_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( nwcs_seo_url() ); ?>">
					<span class="nwcs-sitepill__dot" aria-hidden="true"></span>
					<span class="nwcs-sitepill__name">Ağ özeti</span>
				</a>
				<?php foreach ( $sites as $id => $site ) : ?>
					<a class="nwcs-sitepill<?php echo $id === $blog_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( nwcs_seo_url( $id ) ); ?>">
						<span class="nwcs-sitepill__dot" aria-hidden="true"></span>
						<span class="nwcs-sitepill__name"><?php echo esc_html( $site['label'] ); ?></span>
						<span class="nwcs-sitepill__path"><?php echo esc_html( $site['path'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</header>

		<?php
		if ( ! $sites ) {
			echo '<p class="nwcs-empty">Düzenlenebilecek site bulunamadı; geliştiriciye haber verin. (Teknik: alan manifesti olan site yok)</p>';
		} elseif ( $blog_id ) {
			nwcs_render_seo_site( $blog_id, $sites[ $blog_id ] );
		} else {
			nwcs_render_seo_overview( $sites );
		}
		?>

		<div class="nwcs-toast" data-nwcs-toast hidden></div>
	</div>
	<?php
}

/**
 * Ag ozeti.
 */
function nwcs_render_seo_overview( array $sites ): void {
	$reports = array();

	foreach ( $sites as $id => $site ) {
		$reports[ $id ] = nwcs_seo_site_report( $id );
	}

	$consistency = nwcs_seo_consistency( $reports, $sites );
	?>
	<p class="nwcs-help">
		<span class="nwcs-help__step"><b>1</b> Her sayfa, hiçbir şey girilmese de içeriğinden başlık, açıklama ve görsel alır</span>
		<span class="nwcs-help__step"><b>2</b> Firma bilgisini her site için bir kez girin</span>
		<span class="nwcs-help__step"><b>3</b> Önemli sayfaların başlığını ve açıklamasını elle iyileştirin</span>
	</p>

	<div class="nwcs-seo__grid">
		<section class="nwcs-pool__card">
			<h2 class="nwcs-pool__title">Siteler</h2>

			<table class="nwcs-table nwcs-seo__table">
				<thead>
					<tr>
						<th>Site</th>
						<th>Puan</th>
						<th>Sayfa</th>
						<th>Elle iyileştirilen</th>
						<th>Firma bilgisi</th>
						<th>Arama motorları</th>
						<th>Dosyalar</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $reports as $id => $report ) : ?>
						<tr>
							<td>
								<a class="nwcs-seo__site" href="<?php echo esc_url( nwcs_seo_url( $id ) ); ?>"><?php echo esc_html( $sites[ $id ]['label'] ); ?></a>
								<span class="nwcs-table__spec"><?php echo esc_html( str_replace( array( 'http://', 'https://' ), '', $report['home'] ) ); ?></span>
							</td>
							<td class="nwcs-seo__score-cell"><?php nwcs_render_seo_score_gauge( nwcs_seo_score( $id ) ); ?></td>
							<td><?php echo (int) count( $report['pages'] ); ?></td>
							<td>
								<?php echo (int) $report['custom']; ?> / <?php echo (int) count( $report['pages'] ); ?>
								<?php if ( $report['empty'] ) : ?>
									<span class="nwcs-table__spec nwcs-seo__warn"><?php echo (int) $report['empty']; ?> sayfanın açıklaması yok</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $report['missing'] ) : ?>
									<span class="nwcs-badge nwcs-badge--warn">Eksik</span>
									<span class="nwcs-table__spec"><?php echo esc_html( implode( ', ', $report['missing'] ) ); ?></span>
								<?php else : ?>
									<span class="nwcs-badge nwcs-badge--ok">Tamam</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $report['public'] ) : ?>
									<span class="nwcs-badge nwcs-badge--ok">Açık</span>
								<?php else : ?>
									<span class="nwcs-badge nwcs-badge--warn">Kapalı</span>
									<a class="nwcs-table__spec" href="<?php echo esc_url( $report['reading'] ); ?>">Okuma ayarları</a>
								<?php endif; ?>
							</td>
							<td class="nwcs-seo__files">
								<a href="<?php echo esc_url( $report['sitemap'] ); ?>" target="_blank" rel="noopener">Site haritası</a>
								<a href="<?php echo esc_url( $report['llms'] ); ?>" target="_blank" rel="noopener">llms.txt</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<section class="nwcs-pool__card">
			<h2 class="nwcs-pool__title">Siteler arası tutarlılık</h2>
			<p class="nwcs-hint nwcs-seo__lead">
				Aynı firmanın sitelerinde unvan, telefon ve adres birebir aynı yazılmalı. Arama
				motorları ve yapay zekâ aramaları firmayı bu bilgilerden tek bir şirket olarak tanır.
			</p>

			<ul class="nwcs-seo__checks">
				<?php foreach ( $consistency as $check ) :
					$count = count( $check['values'] );
					?>
					<li class="nwcs-seo__check">
						<div class="nwcs-seo__check-head">
							<strong><?php echo esc_html( $check['label'] ); ?></strong>
							<?php if ( 0 === $count ) : ?>
								<span class="nwcs-badge">Girilmemiş</span>
							<?php elseif ( 1 === $count ) : ?>
								<span class="nwcs-badge nwcs-badge--ok">Tutarlı</span>
							<?php else : ?>
								<span class="nwcs-badge nwcs-badge--warn"><?php echo (int) $count; ?> farklı yazım</span>
							<?php endif; ?>
						</div>

						<?php if ( $count > 1 ) : ?>
							<ul class="nwcs-seo__variants">
								<?php foreach ( $check['values'] as $variant ) : ?>
									<li>
										<span class="nwcs-seo__variant"><?php echo esc_html( $variant['text'] ); ?></span>
										<span class="nwcs-table__spec"><?php echo esc_html( implode( ', ', $variant['sites'] ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>
	<?php
}

/**
 * Tek site: firma bilgisi, varsayilanlar, sayfalar, blog yazilari.
 */
function nwcs_render_seo_site( int $blog_id, array $site ): void {
	$manifest = $site['manifest'];
	$report   = nwcs_seo_site_report( $blog_id );
	$values   = nwcs_get_all( $blog_id );
	$media    = nwcs_site_media( $blog_id );
	$host     = str_replace( array( 'http://', 'https://' ), '', untrailingslashit( $report['home'] ) );
	?>
	<div class="nwcs-seo__layout">

		<div class="nwcs-seo__main">
			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">
					Sayfalar
					<span><?php echo (int) count( $report['pages'] ); ?></span>
				</h2>
				<p class="nwcs-hint nwcs-seo__lead">
					Gri yazılar otomatik değerdir; sayfanın içeriğinden gelir ve içerik değişince kendiliğinden güncellenir.
					Bir alanı doldurduğunuzda o sayfa için otomatik değerin yerine sizinki kullanılır.
				</p>

				<?php foreach ( $report['pages'] as $key => $page ) :
					$resolved = $page['resolved'];
					$path     = str_replace( $report['home'], '', $page['url'] );
					$is_own   = '' !== $resolved['custom']['title'] || '' !== $resolved['custom']['description'] || $resolved['custom']['image'];
					?>
					<article class="nwcs-seo__page" id="seo-<?php echo esc_attr( $key ); ?>" data-nwcs-seo-page>
						<header class="nwcs-seo__page-head">
							<h3>
								<?php echo esc_html( nwcs_short_page_label( preg_replace( '/^[^:]+:\s*/u', '', $page['label'] ) ) ); ?>
								<span class="nwcs-badge<?php echo $is_own ? ' nwcs-badge--ok' : ''; ?>"><?php echo $is_own ? 'Elle iyileştirildi' : 'Otomatik'; ?></span>
							</h3>
							<a class="nwcs-table__spec" href="<?php echo esc_url( $page['url'] ); ?>" target="_blank" rel="noopener">Sayfayı aç ↗</a>
						</header>

						<div class="nwcs-serp" aria-label="Google önizlemesi">
							<span class="nwcs-serp__url"><?php echo esc_html( $host . ( '' !== trim( $path, '/' ) ? ' › ' . str_replace( '/', ' › ', trim( $path, '/' ) ) : '' ) ); ?></span>
							<span class="nwcs-serp__title" data-nwcs-serp-title><?php echo esc_html( $resolved['title'] ); ?></span>
							<span class="nwcs-serp__desc" data-nwcs-serp-desc><?php echo esc_html( '' !== $resolved['description'] ? $resolved['description'] : 'Açıklama yok: Google sayfadan kendi seçtiği bir bölümü gösterir.' ); ?></span>
						</div>

						<?php // Varsayilan kapali: once on izlemeler taranir, gerekirse acilir. ?>
						<details class="nwcs-seo__edit">
							<summary>Başlığı, açıklamayı ve paylaşım görselini düzenle</summary>
							<?php
							nwcs_seo_render_form(
								$blog_id,
								$manifest,
								$key,
								NWCS_SEO_COMPONENT,
								$values,
								$media,
								array(
									'title'       => $resolved['auto']['title'],
									'description' => $resolved['auto']['description'],
								)
							);
							?>
						</details>
					</article>
				<?php endforeach; ?>
			</section>

			<?php if ( $report['posts'] ) : ?>
				<section class="nwcs-pool__card">
					<h2 class="nwcs-pool__title">
						Blog yazıları
						<span><?php echo (int) count( $report['posts'] ); ?></span>
					</h2>
					<p class="nwcs-hint nwcs-seo__lead">
						Yazıların arama açıklaması, WordPress yazı düzenleyicisindeki <strong>Özet</strong> alanından gelir; özet
						boşsa yazının ilk cümleleri kullanılır. Başlık yazının kendi başlığıdır.
					</p>

					<table class="nwcs-table">
						<tbody>
							<?php foreach ( $report['posts'] as $post ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $post['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $post['title'] ); ?></a>
										<span class="nwcs-table__spec"><?php echo esc_html( $post['description'] ); ?></span>
									</td>
									<td class="nwcs-table__actions">
										<span class="nwcs-badge<?php echo $post['own'] ? ' nwcs-badge--ok' : ''; ?>"><?php echo $post['own'] ? 'Özet var' : 'Otomatik'; ?></span>
										<a class="button button-small" href="<?php echo esc_url( $post['edit'] ); ?>">Düzenle</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endif; ?>
		</div>

		<aside class="nwcs-seo__side">
			<?php nwcs_render_seo_score_card( nwcs_seo_score( $blog_id ) ); ?>

			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">
					Firma bilgisi
					<?php if ( $report['missing'] ) : ?>
						<span class="nwcs-seo__warn-pill"><?php echo (int) count( $report['missing'] ); ?> eksik</span>
					<?php endif; ?>
				</h2>
				<p class="nwcs-hint nwcs-seo__lead">
					Sitenin bütün sayfalarına işlenir: Google Haritalar eşleşmesi, arama sonucundaki firma kutusu ve
					yapay zekâ aramalarının firmayı tanıması bu bilgilerden olur.
				</p>
				<?php nwcs_seo_render_form( $blog_id, $manifest, NWCS_SEO_SITE_PAGE, 'org', $values, $media ); ?>
			</section>

			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">Varsayılanlar</h2>
				<?php nwcs_seo_render_form( $blog_id, $manifest, NWCS_SEO_SITE_PAGE, 'defaults', $values, $media ); ?>
			</section>

			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">Otomatik üretilenler</h2>
				<ul class="nwcs-seo__auto">
					<li>
						<strong>Arama motorları</strong>
						<?php echo $report['public'] ? 'Siteyi dizine ekleyebilir.' : 'Site arama motorlarına <em>kapalı</em>.'; ?>
						<a href="<?php echo esc_url( $report['reading'] ); ?>">Okuma ayarları</a>
					</li>
					<li>
						<strong>Site haritası</strong>
						Sayfalar ve yazılar eklendikçe güncellenir.
						<a href="<?php echo esc_url( $report['sitemap'] ); ?>" target="_blank" rel="noopener">Aç</a>
					</li>
					<li>
						<strong>llms.txt</strong>
						Yapay zekâ aramaları için sitenin özeti: sayfalar, ürün ölçüleri, yazılar, iletişim.
						<a href="<?php echo esc_url( $report['llms'] ); ?>" target="_blank" rel="noopener">Aç</a>
					</li>
					<li>
						<strong>Yapılandırılmış veri</strong>
						Firma, konum yolu, ürün ve yazı bilgisi her sayfaya kendiliğinden eklenir.
					</li>
				</ul>
			</section>
		</aside>
	</div>
	<?php
}

/**
 * Bir bilesenin formu; Icerik Studyosu'nun kaydetme yolunu kullanir.
 * $placeholders: bos alanlarda gri gorunecek otomatik degerler.
 */
function nwcs_seo_render_form( int $blog_id, array $manifest, string $page_key, string $component_key, array $values, array $media, array $placeholders = array() ): void {
	$component = $manifest['pages'][ $page_key ]['components'][ $component_key ] ?? null;

	if ( ! $component ) {
		return;
	}
	?>
	<form class="nwcs-form nwcs-seo__form" method="post" enctype="multipart/form-data"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-nwcs-form>

		<input type="hidden" name="action" value="nwcs_save" />
		<input type="hidden" name="site" value="<?php echo esc_attr( (string) $blog_id ); ?>" />
		<input type="hidden" name="content_page" value="<?php echo esc_attr( $page_key ); ?>" />
		<input type="hidden" name="component" value="<?php echo esc_attr( $component_key ); ?>" />
		<?php wp_nonce_field( 'nwcs_save_' . $blog_id . '_' . $page_key . '_' . $component_key ); ?>

		<div class="nwcs-fields">
			<?php
			foreach ( $component['fields'] as $field_key => $definition ) {
				if ( isset( $placeholders[ $field_key ] ) && '' !== $placeholders[ $field_key ] ) {
					$definition['placeholder'] = $placeholders[ $field_key ];
				}

				$value = $values[ $page_key ][ $component_key ][ $field_key ] ?? ( $definition['default'] ?? '' );
				nwcs_render_field( $field_key, $definition, $value, $media, $blog_id );
			}
			?>
		</div>

		<footer class="nwcs-actions">
			<button type="submit" class="button button-primary">Kaydet ve Yayınla</button>
			<span class="nwcs-dirty" data-nwcs-dirty hidden>● kaydedilmedi</span>
		</footer>
	</form>
	<?php
}

/**
 * Icerik Studyosu'ndaki "Arama ve Paylasim" bolumu icin otomatik degerler.
 */
function nwcs_seo_placeholders( int $blog_id, string $page_key ): array {
	switch_to_blog( $blog_id );
	$resolved = nwcs_seo_page_resolved( $page_key );
	restore_current_blog();

	return array(
		'title'       => $resolved['auto']['title'],
		'description' => $resolved['auto']['description'],
	);
}
