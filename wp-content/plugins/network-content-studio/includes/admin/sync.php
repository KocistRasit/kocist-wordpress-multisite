<?php
/**
 * Kategori bazli Excel senkronu: taslak indirme, plan, uygulama, geri alma.
 *
 * Akis (ekranlar import.php'de):
 *   1. Ürün Havuzu -> Kategoriler -> "Excel indir": kategorinin urunleri,
 *      sistem sutunlari + detay basliklari. Gizli "_bilgi" sayfasi dosyanin
 *      hangi kategoriye ait oldugunu tasir; yuklemede kullanici hicbir sey
 *      secmez, sutun eslestirmesi yoktur.
 *   2. "Excel yükle": once plan hesaplanir, HICBIR SEY YAZILMAZ; onizleme.
 *   3. "Değişiklikleri uygula": plan yazilir; geri alma kaydi "Son işlemler"
 *      yiginina girer (admin/history.php).
 *
 * Kurallar (PLAN-detay-basliklari.md, 4. bolum):
 *   - ÜRÜN KODU zorunlu (0.23.0): bos kodlu satir yuklenmez; otomatik kod verilmez.
 *   - Eslesme: KİMLİK (cop dahil) > ÜRÜN KODU (cop dahil) > yeni urun.
 *   - Bos hucre = temizle (bos fiyat "Teklif al" olur). Zorunlu baslik bossa
 *     satir reddedilir; hatali satir yazilmaz, urun oldugu gibi kalir.
 *   - Dosyada olmayan (kategoride yayinda olan) urun, onay kutusu
 *     isaretliyse cop kutusuna gider.
 *   - Dosyada olmayan alanlara dokunulmaz: gorseller, uzun aciklama,
 *     tablolar, sira, diger kategoriler, site ozellestirmeleri.
 *   - SİTE (istege bagli): urunun gorunecegi siteler ("Hepsi" ya da site
 *     adlari virgulle). Bos hucre = degismez (temizle degil: bos = her yerde
 *     gizle olurdu). Listede olmayan sitede urun gizlenir (hidden istisnasi),
 *     listedekinde gosterilir (nwcs_product_show_on_site). Yeni urunde bossa
 *     kategorinin yerlesimi gecerli.
 *   - SAYFA ADRESİ (0.26.0): site basina ozel adres (Özelleştirmeler'in yolu,
 *     nwcs_override_set_slug); bos hucre = degismez. Dosyanin butun adresleri
 *     birlikte denetlenir (nwcs_sync_check_addresses), en sonda yazilir.
 *   - Hizli Excel (sablon=hizli): yalnizca kod, ad, fiyat, site; yeni urun ve
 *     cop kutusu yok, dosyada olmayan urune dokunulmaz.
 *
 * Ortak yardimcilar (gecici klasor, metin sadelestirme) da burada; Ürün
 * Açıklamaları ekrani da bunlari kullanir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_SYNC_PLAN     = 'nwcs_sync_plan_';
const NWCS_SYNC_KIND     = 'urunler';
const NWCS_SYNC_SHEET    = 'Ürünler';
const NWCS_SYNC_INFO     = '_bilgi';
// 2 (0.26.0): META BAŞLIK, META AÇIKLAMA, ÜRÜN AÇIKLAMASI ve bilgi sutunlari.
// 3 (0.26.0): SAYFA ADRESİ duzenlenebilir. Eski taslaklar da yuklenir: olmayan
// sutunun alanina dokunulmaz; surum 2'nin "(bilgi, …)" adres sutunu okunmaz.
const NWCS_SYNC_VERSION  = 3;
// Hizli Excel: _bilgi sayfasinda sablon=hizli. Ayni motor; yeni urun, cop kutusu
// ve detay basliklari yok, yalnizca NWCS_SYNC_QUICK_FIELDS okunur.
const NWCS_SYNC_QUICK        = 'hizli';
const NWCS_SYNC_QUICK_FIELDS = array( 'id', 'code', 'title', 'price', 'site' );

/* ====================================================================== *
 * Ortak yardimcilar
 * ====================================================================== */

/**
 * Gecici dosyalarin tutuldugu klasor. Icine yalnizca kullanicinin kendi
 * yukledigi ya da indirdigi veri, kisa sureligine konur.
 */
function nwcs_import_dir(): string {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'nwcs-import';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// Dizin listelemeyi ve dogrudan erisimi engelle.
		file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/index.php', "<?php // sessiz\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return $dir;
}

/**
 * Bir gun once kalmis gecici dosyalari temizler.
 */
function nwcs_import_sweep(): void {
	// GLOB_BRACE her sistemde yok (Alpine/musl); iki ayri desen.
	$files = array_merge( glob( nwcs_import_dir() . '/*.json' ) ?: array(), glob( nwcs_import_dir() . '/*.xlsx' ) ?: array() );

	foreach ( $files as $file ) {
		if ( filemtime( $file ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $file );
		}
	}
}

/**
 * Gecici .xlsx yolu (indirme icin).
 */
function nwcs_import_temp_xlsx(): string {
	nwcs_import_sweep();

	return nwcs_import_dir() . '/' . bin2hex( random_bytes( 10 ) ) . '.xlsx';
}

/**
 * Yuklenen dosyayi dogrular ve eklentinin gecici klasorune tasir (open_basedir
 * kisitli sunucularda PHP'nin gecici klasoru okunamayabilir). Yol ya da hata;
 * cagiran isi bitince dosyayi siler (wp_delete_file).
 *
 * @return string|WP_Error
 */
function nwcs_import_uploaded_xlsx( string $field ) {
	$file = $_FILES[ $field ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- cagiran denetledi.

	if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
		return new WP_Error( 'nwcs_upload', 'Dosya alınamadı. Bir .xlsx dosyası seçip yeniden deneyin.' );
	}

	$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );

	if ( 'xlsx' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'nwcs_upload', 'Yalnızca .xlsx dosyası yüklenebilir. Excel’de “Farklı Kaydet → Excel Çalışma Kitabı (.xlsx)” seçin.' );
	}

	$path = nwcs_import_temp_xlsx();

	if ( ! move_uploaded_file( (string) $file['tmp_name'], $path ) ) {
		return new WP_Error( 'nwcs_upload', 'Dosya kaydedilemedi. Yeniden deneyin; sorun sürerse geliştiriciye haber verin. (Teknik: move_uploaded_file başarısız)' );
	}

	return $path;
}

/**
 * Taslagin gizli "_bilgi" sayfasini okur (anahtar => deger).
 *
 * @return array<string, string>|WP_Error
 */
function nwcs_sync_read_info( string $path, string $kind ) {
	$read = nwcs_xlsx_read_rows( $path, NWCS_SYNC_INFO );

	if ( is_wp_error( $read ) ) {
		if ( 'nwcs_xlsx_nosheet' === $read->get_error_code() ) {
			return new WP_Error( 'nwcs_not_template', 'Bu dosya panelden indirilen Excel değil (kendi açtığınız bir dosya olabilir ya da gizli sayfası silinmiş olabilir). Panelden “Excel indir” ile Excel’i indirin, bilgileri ona yazıp yükleyin.' );
		}

		return $read;
	}

	$info = array();

	foreach ( $read['rows'] as $row ) {
		$key = trim( (string) ( $row[0] ?? '' ) );

		if ( '' !== $key ) {
			$info[ $key ] = trim( (string) ( $row[1] ?? '' ) );
		}
	}

	// KİMLİK'ler yalnizca bu kurulumda gecerli: baska panelden (ornegin yerelden)
	// indirilen dosya yanlis urunleri guncelleyebilirdi.
	if ( '' !== ( $info['kaynak'] ?? '' ) && untrailingslashit( $info['kaynak'] ) !== untrailingslashit( network_home_url( '/' ) ) ) {
		return new WP_Error( 'nwcs_other_panel', sprintf( 'Bu dosya başka bir yönetim panelinden (%s) indirilmiş; burada kullanılamaz. Bu panelden “Excel indir” ile yeni bir Excel indirip onu doldurun.', $info['kaynak'] ) );
	}

	if ( ( $info['tur'] ?? '' ) !== $kind ) {
		$others = array(
			'aciklamalar' => 'Bu dosya ürün açıklamaları Excel’i. Onu Ürün Havuzu › Ürün açıklamaları ekranından yükleyin.',
			'alt'         => 'Bu dosya görsel alt metinleri Excel’i. Onu Medya Havuzu’ndaki “Alt metinleri Excel ile düzenleyin” kutusundan yükleyin.',
		);
		$other  = $others[ $info['tur'] ?? '' ] ?? 'Bu dosya bu ekranın Excel’i değil. Bu ekrandaki “Excel indir” ile doğru Excel’i indirin.';

		return new WP_Error(
			'nwcs_wrong_template',
			NWCS_SYNC_KIND === ( $info['tur'] ?? '' ) ? 'Bu dosya ürün Excel’i (kategori Excel’i ya da Hızlı Excel). Onu Ürün Havuzu › Kategoriler sayfasında “Yükle” ile yükleyin: kategori Excel’ini o kategorinin sayfasında, tüm ürünlerin Hızlı Excel’ini kategori seçmeden açılan “Tüm ürünler” kutusunda.' : $other
		);
	}

	return $info;
}

/**
 * Havuzda (cop dahil) bir urunu kimligiyle getirir; urun degilse null.
 * Havuz baglaminda cagrilmalidir.
 */
function nwcs_sync_product_post( int $id ): ?WP_Post {
	$post = $id ? get_post( $id ) : null;

	if ( ! $post || NWCS_PRODUCT_TYPE !== $post->post_type || 'auto-draft' === $post->post_status ) {
		return null;
	}

	return $post;
}

/* ====================================================================== *
 * Taslak (indirme)
 * ====================================================================== */

/**
 * Sistem sutunlari: alan => Excel basligi. Yuklemede baslik, harf ve Turkce
 * karakter farki gozetilmeden taninir ("Fiyat", "FIYAT", "FİYAT" ayni).
 * Bu adlarla yeni detay basligi acilmaz (nwcs_heading_reserved_keys).
 */
function nwcs_sync_system_columns(): array {
	return array(
		'id'         => 'KİMLİK',
		'code'       => 'ÜRÜN KODU',
		'title'      => 'ÜRÜN ADI',
		'site'       => 'SİTE',
		'price'      => 'FİYAT',
		'short'      => 'KISA AÇIKLAMA',
		'meta_title' => 'META BAŞLIK',
		'meta_desc'  => 'META AÇIKLAMA',
		'body'       => 'ÜRÜN AÇIKLAMASI',
		'address'    => 'SAYFA ADRESİ',
	);
}

/**
 * Taslakta sistem sutununun yanina yazilan aciklama: "META BAŞLIK (en fazla
 * 60 karakter)". Dosyayi dolduran kisi ve yapay zeka sinirlari basliktan
 * gorur; yuklemede parantez atilip sutun yine taninir.
 */
function nwcs_sync_column_hints(): array {
	return array(
		'meta_title' => 'en fazla 60 karakter',
		'meta_desc'  => 'en fazla 160 karakter',
		'body'       => 'detay sayfası metni',
		'address'    => 'yalnızca /urun/ sonrası değişir; boş = değişmez',
	);
}

/**
 * Yalnizca bilgi icin yazilan sutunlar: yuklemede okunmaz. Basliginda
 * "(bilgi" olan her sutun da boyle sayilir (Ürün açıklamaları dosyasindaki
 * gibi); bu anahtarlar ekin silinmesine karsi ayrica taninir.
 */
function nwcs_sync_info_columns(): array {
	return array(
		'extra' => 'EK İÇERİK (bilgi)',
	);
}

/**
 * Meta alanlari icin onerilen uzunluk; asan deger yuklenir, onizlemede uyarilir.
 */
const NWCS_SYNC_META_LIMITS = array( 'meta_title' => 60, 'meta_desc' => 160 );

/**
 * Detay basliginin Excel'deki sutun adi. Adi bir sistem sutunuyla ayni olan
 * eski baslik (Kocist verisindeki "Fiyat") "(detay)" ekiyle yazilir; yuklemede
 * ayni basliga geri eslenir.
 */
function nwcs_sync_heading_header( string $label ): string {
	return in_array( nwcs_heading_key( $label ), nwcs_heading_reserved_keys(), true ) ? $label . ' (detay)' : $label;
}

/**
 * Urunun gorundugu sitelerin adlari (temasinda urun alani olan, urunu
 * gizlemeyen ve secmis ya da "hepsi" kipindeki siteler).
 *
 * @return string[]
 */
function nwcs_product_site_labels( int $product_id ): array {
	static $sites = null;

	if ( null === $sites ) {
		$sites = array();

		foreach ( nwcs_editable_sites() as $blog_id => $site ) {
			if ( nwcs_site_supports_products( (int) $blog_id ) ) {
				$sites[ (int) $blog_id ] = array( (string) $site['label'], nwcs_site_product_settings( (int) $blog_id ) );
			}
		}
	}

	$labels = array();

	foreach ( $sites as $row ) {
		[ $label, $settings ] = $row;

		if ( empty( $settings['overrides'][ $product_id ]['hidden'] ) && ( 'all' === $settings['mode'] || in_array( $product_id, $settings['selected'], true ) ) ) {
			$labels[] = $label;
		}
	}

	return $labels;
}

/**
 * Urunun her sitedeki durumu ve (varsa) tek tikla duzeltme. Temasi urun
 * gostermeyen site listelenmez. Site basina ilk uyan kural:
 *   trash            cop kutusunda                                  -> untrash
 *   hidden           sitede gizlenmis (toplu islem)                 -> unhide
 *   unplaced         secili degil, kategorisi o siteye yerlesmemis  -> place (Kategoriler)
 *   unselected       secili degil                                   -> select
 *   visible_unplaced gorunuyor ama kategori sayfasi yok             -> place
 *   visible          gorunuyor: "WOODGarden › Kamelyalar · 12 / 59"
 *
 * @return array<int, array{site:string, state:string, reason:string, fix:string, url:string, category_url:string}>
 */
function nwcs_product_site_status( int $product_id ): array {
	$pool    = nwcs_pool_products();
	$product = $pool[ $product_id ] ?? null;
	$trashed = in_array( $product_id, nwcs_pool_trashed_ids(), true );

	if ( ! $product ) {
		switch_to_blog( nwcs_pool_blog_id() );

		$product = array(
			'id'         => $product_id,
			'slug'       => (string) get_post_field( 'post_name', $product_id ),
			'body'       => (string) get_post_field( 'post_content', $product_id ),
			'categories' => array(),
		);

		foreach ( wp_get_object_terms( $product_id, NWCS_PRODUCT_TAX ) as $term ) {
			$product['categories'][ $term->slug ] = $term->name;
		}

		restore_current_blog();
	}

	$categories = nwcs_pool_categories();
	$catalog    = nwcs_catalog_sites();
	$tops       = nwcs_parent_categories();
	$first      = (string) array_key_first( (array) $product['categories'] );
	$cat_url    = '' !== $first ? nwcs_pool_categories_url( array( 'kategori' => $first ) ) : nwcs_pool_categories_url();
	$names      = implode( ', ', (array) $product['categories'] );
	$out        = array();

	foreach ( nwcs_editable_sites() as $blog_id => $site ) {
		$blog_id = (int) $blog_id;

		if ( ! nwcs_site_supports_products( $blog_id ) ) {
			continue;
		}

		$settings = nwcs_site_product_settings( $blog_id );
		$row      = array( 'site' => (string) $site['label'], 'state' => 'visible', 'reason' => '', 'fix' => '', 'url' => '', 'category_url' => $cat_url );
		$listed   = 'all' === $settings['mode'] || in_array( $product_id, $settings['selected'], true );
		$key      = (string) ( $catalog[ $blog_id ]['site_key'] ?? '' );
		$placed   = false;

		foreach ( array_keys( (array) $product['categories'] ) as $slug ) {
			if ( ( '' !== $key && '' !== ( $categories[ $slug ]['placement'][ $key ] ?? '' ) ) || isset( $tops[ $slug ][ $blog_id ] ) ) {
				$placed = true;
				break;
			}
		}

		if ( $trashed ) {
			$row = array_merge( $row, array( 'state' => 'trash', 'reason' => 'çöp kutusunda', 'fix' => 'untrash' ) );
		} elseif ( ! empty( $settings['overrides'][ $product_id ]['hidden'] ) ) {
			$row = array_merge( $row, array( 'state' => 'hidden', 'reason' => 'bu sitede gizlenmiş (Ürün Havuzu toplu işlemi, ürün formundaki Siteler ya da Excel SİTE sütunu)', 'fix' => 'unhide' ) );
		} elseif ( ! $listed && '' !== $key && ! $placed ) {
			$row = array_merge( $row, array( 'state' => 'unplaced', 'reason' => '' !== $names ? sprintf( 'kategorisi (%s) bu siteye yerleşmemiş', $names ) : 'kategorisi yok', 'fix' => 'place' ) );
		} elseif ( ! $listed ) {
			$row = array_merge( $row, array( 'state' => 'unselected', 'reason' => 'sitenin ürün listesinde değil', 'fix' => 'select' ) );
		} else {
			$list  = nwcs_site_products( $blog_id );
			$index = array_search( $product_id, array_map( static fn( array $item ): int => (int) $item['id'], $list ), true );
			$where = false !== $index ? sprintf( '%d / %d', $index + 1, count( $list ) ) : '';

			if ( '' !== $key ) {
				$place = nwcs_product_place( $product, $blog_id );

				if ( null === $place['parent'] ) {
					$row['state']  = 'visible_unplaced';
					$row['fix']    = 'place';
					$row['reason'] = 'görünüyor; kategori sayfası yok (kategorisi yerleşmemiş)' . ( ! empty( $catalog[ $blog_id ]['catalog']['hide_empty'] ) ? '. Bu sitede kategori sayfası ürün olunca açılır' : '' );
				} else {
					$parent = (string) ( nwcs_site_parents( $blog_id )[ $place['parent'] ]['label'] ?? $place['parent'] );
					$child  = '';

					if ( null !== $place['child'] ) {
						$child = nwcs_category_site_name( (string) $place['child'], $blog_id );
						$child = '' !== $child ? $child : (string) ( $categories[ $place['child'] ]['name'] ?? $place['child'] );
					}

					$where = $parent . ( '' !== $child ? ' › ' . $child : '' ) . ( '' !== $where ? ' · ' . $where : '' );
				}
			}

			if ( 'visible' === $row['state'] ) {
				$row['reason'] = $where;
			}

			if ( nwcs_product_has_page( $product, $blog_id ) ) {
				// Siteye ozel adres varsa "Sitede gör" onu acar.
				$row['url'] = nwcs_product_url( nwcs_product_site_slug( $product_id, (string) $product['slug'], $blog_id ), $blog_id );
			}
		}

		$out[ $blog_id ] = $row;
	}

	return $out;
}

/**
 * Urunleri bir sitede gosterir ya da gizler. Toplu "göster/gizle" ve urun
 * formundaki "Listeye ekle" / "Göster" ayni yaziciyi kullanir. Göster:
 * gizleme kalkar; "Seçilenler" kipindeki sitede listenin sonuna eklenir.
 *
 * @param int[] $ids
 */
function nwcs_product_show_on_site( int $blog_id, array $ids, bool $show = true ): void {
	switch_to_blog( $blog_id );

	$settings = nwcs_site_product_settings();

	foreach ( $ids as $id ) {
		$override = $settings['overrides'][ $id ] ?? array();

		if ( ! $show ) {
			$override['hidden'] = 1;
		} else {
			$override['hidden'] = 0;

			// "Secilenler" kipinde gosterebilmek icin listeye de eklenir.
			if ( 'selected' === $settings['mode'] && ! in_array( $id, $settings['selected'], true ) ) {
				$settings['selected'][] = $id;
			}
		}

		$settings['overrides'][ $id ] = $override;
	}

	nwcs_save_site_product_settings( $settings );
	restore_current_blog();
}

/* ====================================================================== *
 * SİTE sutunu: urunun gorunecegi siteler
 * ====================================================================== */

/**
 * SİTE sutununun siteleri: havuzu kategori yerlesimiyle tuketen ve temasi
 * urun gosteren siteler (bugun Koçist, WOOD KOCIST), sirayla: blog_id => ad.
 * Yeni katalog sitesi kendiliginden girer ("Hepsi" onu da kapsar).
 *
 * @return array<int, string>
 */
function nwcs_sync_sites(): array {
	$out = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		if ( nwcs_site_supports_products( (int) $blog_id ) ) {
			$out[ (int) $blog_id ] = (string) $site['label'];
		}
	}

	return $out;
}

/**
 * Site adinin karsilastirma hali: buyuk/kucuk harf, Turkce harf ve bosluk
 * farki yok; alan adi yazildiysa ilk parcasi ("https://www.woodkocist.com.tr/"
 * -> "woodkocist").
 */
function nwcs_sync_site_fold( string $text ): string {
	$text = nwcs_search_fold( trim( $text ) );
	$text = (string) preg_replace( '#^[a-z]+://#', '', $text );
	$text = (string) preg_replace( '/^www\./', '', $text );
	$text = (string) strtok( $text . '/', '/' );

	if ( preg_match( '/^([^.]+)\.[a-z]{2,}/', $text, $match ) ) {
		$text = $match[1];
	}

	return (string) preg_replace( '/[^a-z0-9]/', '', $text );
}

/**
 * Taninan yazimlar: sade ad => blog_id. Panel adi, site anahtari, alt
 * klasor ve (ag alan adindan farkliysa) alan adi. Iki siteye uyan yazim
 * belirsizdir, taninmaz.
 *
 * @return array<string, int>
 */
function nwcs_sync_site_aliases(): array {
	$network = nwcs_sync_site_fold( (string) get_network()->domain );
	$catalog = nwcs_catalog_sites();
	$out     = array();
	$clash   = array();

	foreach ( nwcs_sync_sites() as $blog_id => $label ) {
		$site  = get_site( $blog_id );
		$names = array( $label, (string) ( $catalog[ $blog_id ]['site_key'] ?? '' ) );

		if ( $site ) {
			$names[] = trim( (string) $site->path, '/' );
			$names[] = (string) $site->domain;
		}

		foreach ( $names as $name ) {
			$key = nwcs_sync_site_fold( $name );

			if ( '' === $key || $key === $network ) {
				continue;
			}

			if ( isset( $out[ $key ] ) && $out[ $key ] !== $blog_id ) {
				$clash[ $key ] = true;
			}

			$out[ $key ] = $blog_id;
		}
	}

	return array_diff_key( $out, $clash );
}

/**
 * SİTE hucresini okur. Bos: null (degismez). "Hepsi" butun siteler; site
 * adlari virgul ya da noktali virgulle. Anlasilmayan ad: hata.
 *
 * @return int[]|null|WP_Error blog_id listesi, site sirasiyla.
 */
function nwcs_sync_parse_sites( string $value ) {
	static $aliases = null;

	$sites   = nwcs_sync_sites();
	$aliases = $aliases ?? nwcs_sync_site_aliases();
	$wanted  = array();
	$unknown = array();

	foreach ( preg_split( '/[,;\n]+/u', $value ) as $part ) {
		$part = trim( (string) $part );
		$key  = nwcs_sync_site_fold( $part );

		if ( '' === $key ) {
			continue;
		}

		if ( in_array( $key, array( 'hepsi', 'hepsinde', 'tumu', 'tum', 'tumsiteler', 'butunsiteler' ), true ) ) {
			$wanted = array_merge( $wanted, array_keys( $sites ) );
		} elseif ( isset( $aliases[ $key ] ) ) {
			$wanted[] = $aliases[ $key ];
		} else {
			$unknown[] = $part;
		}
	}

	if ( $unknown ) {
		return new WP_Error(
			'nwcs_site',
			sprintf(
				'SİTE değeri anlaşılamadı: %s. Yazılabilecekler: Hepsi, %s (virgülle birden fazla).',
				'“' . implode( '”, “', $unknown ) . '”',
				implode( ', ', $sites )
			)
		);
	}

	return $wanted ? array_values( array_intersect( array_keys( $sites ), $wanted ) ) : null;
}

/**
 * Urunun gorundugu SİTE siteleri (gizli degil ve "hepsi" kipinde ya da
 * secili). Site ayarlari istek boyunca bir kez okunur; yazdiktan sonra
 * $reset ile yenilenir.
 *
 * @return int[]
 */
function nwcs_sync_product_sites( int $product_id, bool $reset = false ): array {
	static $settings = null;

	if ( $reset || null === $settings ) {
		$settings = array();

		foreach ( array_keys( nwcs_sync_sites() ) as $blog_id ) {
			$row                  = nwcs_site_product_settings( $blog_id );
			$row['selected']      = array_flip( $row['selected'] );
			$settings[ $blog_id ] = $row;
		}
	}

	$out = array();

	foreach ( $settings as $blog_id => $row ) {
		if ( empty( $row['overrides'][ $product_id ]['hidden'] ) && ( 'all' === $row['mode'] || isset( $row['selected'][ $product_id ] ) ) ) {
			$out[] = (int) $blog_id;
		}
	}

	return $out;
}

/**
 * SİTE hucresine yazilan hal: butun sitelerdeyse "Hepsi", degilse adlar
 * virgulle; hicbir sitede degilse bos.
 *
 * @param int[] $blog_ids
 */
function nwcs_sync_sites_text( array $blog_ids ): string {
	$sites    = nwcs_sync_sites();
	$blog_ids = array_map( 'intval', $blog_ids );

	if ( count( $sites ) > 1 && ! array_diff( array_keys( $sites ), $blog_ids ) ) {
		return 'Hepsi';
	}

	return implode( ', ', array_intersect_key( $sites, array_flip( $blog_ids ) ) );
}

/**
 * SAYFA ADRESİ (bilgi) hucresi: urunun gorundugu her sitedeki adresi (siteye
 * ozel adres varsa o), satir satir. Hicbir sitede degilse havuzdaki adres.
 * Hucre duzenlenebilir: yuklemede nwcs_sync_address_lines okur (bos = degismez).
 */
function nwcs_sync_address_text( int $product_id, string $pool_slug ): string {
	// Kategori dosyasinda her satir icin: site ayarlari ve ana adres site basina bir kez.
	static $settings = array();
	static $homes    = array();

	$urls = array();

	foreach ( nwcs_sync_product_sites( $product_id ) as $blog_id ) {
		$settings[ $blog_id ] ??= nwcs_site_product_settings( $blog_id );
		$homes[ $blog_id ]    ??= get_home_url( $blog_id, '/urun/' );

		$override = $settings[ $blog_id ]['overrides'][ $product_id ] ?? array();
		$slug     = nwcs_override_slug( is_array( $override ) ? $override : array() );
		$urls[]   = $homes[ $blog_id ] . ( '' !== $slug ? $slug : $pool_slug ) . '/';
	}

	return $urls ? implode( "\n", $urls ) : '/urun/' . $pool_slug . '/ (hiçbir sitede görünmüyor)';
}

/**
 * SAYFA ADRESİ hucresinin satirlari: her satir bir sitenin adresi.
 *   - Tam adres (http/https, www. onemsiz): sitenin /urun/ onekiyle siteye eslenir.
 *   - Yalnizca adres ("ahsap-cardak", "/urun/ahsap-cardak"): site belirtilmemis.
 *   - "otomatik": havuzdaki adrese don.
 * Adres nwcs_slug_clean ile sadelesir (Turkce harfler, bosluk -> tire).
 *
 * @return array<int, array{blog:?int, slug:string, auto:bool, unknown:bool, raw:string}>
 */
function nwcs_sync_address_lines( string $text ): array {
	static $prefixes = null;

	if ( null === $prefixes ) {
		$prefixes = array();

		foreach ( array_keys( nwcs_sync_sites() ) as $blog_id ) {
			$prefixes[ (int) $blog_id ] = (string) preg_replace( '#^https?://(www\.)?#i', '', get_home_url( (int) $blog_id, '/urun/' ) );
		}

		// Uzun onek once: biri digerinin basiysa dogru site secilsin.
		uasort( $prefixes, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
	}

	$out = array();

	foreach ( (array) preg_split( '/\R/u', $text ) as $raw ) {
		$line = trim( str_replace( '(hiçbir sitede görünmüyor)', '', (string) $raw ) );

		if ( '' === $line ) {
			continue;
		}

		if ( 'otomatik' === nwcs_search_fold( $line ) ) {
			$out[] = array( 'blog' => null, 'slug' => '', 'auto' => true, 'unknown' => false, 'raw' => $line );
			continue;
		}

		$bare   = (string) preg_replace( '#^https?://(www\.)?#i', '', $line );
		$blog   = null;
		$prefix = '';

		foreach ( $prefixes as $id => $candidate ) {
			if ( 0 === stripos( $bare, $candidate ) ) {
				$blog   = $id;
				$prefix = $candidate;
				break;
			}
		}

		// Alan adiyla yazilmis ama hicbir sitenin onekine uymayan satir (eski site,
		// yazim hatasi): hangi siteye ait oldugu belli degil; butun sitelere
		// uygulanmasin, satir hata versin.
		$at      = strripos( $bare, '/urun/' );
		$unknown = null === $blog && ( false !== strpos( $line, '://' ) || ( false !== $at && $at > 0 ) );
		$part    = '' !== $prefix ? substr( $bare, strlen( $prefix ) ) : ( false !== $at ? substr( $bare, $at + 6 ) : $line );
		$out[]   = array(
			'blog'    => $blog,
			'slug'    => nwcs_slug_clean( $part ),
			'auto'    => false,
			'unknown' => $unknown,
			'raw'     => $line,
		);
	}

	return $out;
}

/**
 * Plan icin adres cakismasi, yazmadan. nwcs_override_slug_conflict ile ayni
 * kurallar, iki farkla:
 *   - ayni dosyada adresinden ayrilan urunun ozel adresi bos sayilir
 *     ($released: adres => urun kimligi); takas ve zincir planda denetlenir;
 *   - cop kutusundaki urunlerin havuz adresi de dolu sayilir (geri gelince
 *     o urun sitede bulunamazdi).
 * $product_id 0: yeni urun.
 */
function nwcs_sync_slug_conflict( int $blog_id, int $product_id, string $slug, array $released ): string {
	$overrides = nwcs_site_product_settings( $blog_id )['overrides'];
	$pool      = nwcs_pool_products();
	$free      = static fn( int $id ): bool => (int) ( $released[ $slug ] ?? 0 ) === $id;

	foreach ( $pool as $id => $product ) {
		if ( (int) $id === $product_id ) {
			continue;
		}

		$override = is_array( $overrides[ $id ] ?? null ) ? $overrides[ $id ] : array();
		$custom   = nwcs_override_slug( $override );
		$name     = '' !== (string) ( $override['title'] ?? '' ) ? (string) $override['title'] : (string) $product['title'];

		// Havuz adresi hep o urune yonlenir; ayrilsa da baskasina verilmez.
		if ( (string) $product['slug'] === $slug ) {
			return sprintf( 'Bu adres “%s” ürününün ana adresi; özel adres verilse bile eski bağlantılar buradan ona yönlenir. Başka bir adres yazın.', $name );
		}

		if ( '' !== $custom && $custom === $slug && ! $free( (int) $id ) ) {
			return sprintf( 'Bu adres bu sitede “%s” ürününün adresi. Başka bir adres yazın.', $name );
		}
	}

	foreach ( $overrides as $id => $override ) {
		if ( (int) $id !== $product_id && ! isset( $pool[ $id ] ) && is_array( $override ) && nwcs_override_slug( $override ) === $slug ) {
			return 'Bu adres bu sitede çöp kutusundaki bir ürüne ait; ürün geri getirilirse bu adresi kullanacak. Başka bir adres yazın.';
		}
	}

	foreach ( nwcs_pool_trashed_ids() as $id ) {
		if ( (int) $id !== $product_id && nwcs_override_pool_slug( (int) $id ) === $slug ) {
			return 'Bu adres çöp kutusundaki bir ürüne ait; ürün geri getirilirse bu adresi kullanacak. Başka bir adres yazın.';
		}
	}

	switch_to_blog( $blog_id );
	$page = get_page_by_path( $slug );
	restore_current_blog();

	if ( $page ) {
		return sprintf( 'Bu adres bu sitede “%s” sayfasının adresi. Başka bir adres yazın.', '' !== $page->post_title ? $page->post_title : $slug );
	}

	return '';
}

/**
 * $slug bu sitede baska bir urunun eski adresiyse o urunun adi; degilse bos.
 * Adres alinabilir (nwcs_override_set_slug onu eski adreslerden dusurur), ama
 * o urunun eski baglantisi artik ona yonlenmez: onizleme soyler.
 */
function nwcs_sync_slug_old_owner( int $blog_id, int $product_id, string $slug ): string {
	foreach ( nwcs_site_product_settings( $blog_id )['overrides'] as $id => $override ) {
		if ( (int) $id !== $product_id && is_array( $override ) && in_array( $slug, nwcs_override_slug_old( $override ), true ) ) {
			return (string) ( nwcs_pool_products()[ $id ]['title'] ?? get_the_title( (int) $id ) );
		}
	}

	return '';
}

/**
 * Var olan urunun SAYFA ADRESİ hucresinden site basina istenen adres.
 * $sites: urunun bu yuklemeden sonra gorunecegi siteler. Tam adres satiri
 * kendi sitesine, site belirtilmemis tek adres butun sitelere uygulanir.
 *
 * @param int[] $sites
 * @return array{changes: array<int, array{0:string, 1:string}>, notes: string[]}|string Hata metni.
 */
function nwcs_sync_plan_address( int $product_id, string $title, string $cell, array $sites ) {
	$pool_slug = nwcs_override_pool_slug( $product_id );
	$names     = nwcs_sync_sites();
	$wanted    = array(); // blog => adres
	$bare      = array();
	$notes     = array();
	$lines     = nwcs_sync_address_lines( $cell );

	foreach ( $lines as $item ) {
		$slug = $item['auto'] ? $pool_slug : $item['slug'];

		if ( $item['unknown'] ) {
			return sprintf( 'SAYFA ADRESİ hücresindeki “%s” adresinin hangi siteye ait olduğu anlaşılamadı. Adresin başını değiştirmeyin: yalnızca /urun/ sonrasını değiştirin ya da yalnızca yeni adresi yazın (örneğin ahsap-cardak-2x2).', $item['raw'] );
		}

		if ( '' === $slug ) {
			return sprintf( 'SAYFA ADRESİ hücresindeki “%s” adres olarak kullanılamaz. Adreste en az bir harf ya da rakam olmalı; örneğin ahsap-cardak-2x2.', $item['raw'] );
		}

		if ( null === $item['blog'] ) {
			$bare[ $slug ] = true;
			continue;
		}

		// SİTE'den cikan (ya da zaten olmayan) site: satir yok sayilir; degistirildiyse soylenir.
		if ( ! in_array( $item['blog'], $sites, true ) ) {
			if ( $slug !== nwcs_product_site_slug( $product_id, $pool_slug, (int) $item['blog'] ) ) {
				$notes[] = sprintf( '“%s”: SAYFA ADRESİ hücresindeki %s adresi kullanılmadı, çünkü ürün o sitede görünmeyecek.', $title, $names[ $item['blog'] ] ?? $item['blog'] );
			}

			continue;
		}

		if ( isset( $wanted[ $item['blog'] ] ) && $wanted[ $item['blog'] ] !== $slug ) {
			return sprintf( 'SAYFA ADRESİ hücresinde %s için iki farklı adres var. Her site için tek satır yazın.', $names[ $item['blog'] ] ?? $item['blog'] );
		}

		$wanted[ $item['blog'] ] = $slug;
	}

	if ( count( $bare ) > 1 ) {
		return 'SAYFA ADRESİ hücresinde, hangi siteye ait olduğu yazmayan birden çok adres var. Ya tek adres yazın ya da hücredeki her satıra o sitedeki tam adresi yazın (Excel’i indirdiğinizde geldiği gibi).';
	}

	// Urun hicbir sitede degil: adres site basina tutuldugu icin yazilacak yer yok.
	if ( ! $sites ) {
		foreach ( array_merge( array_keys( $bare ), array_column( $lines, 'slug' ) ) as $slug ) {
			if ( '' !== $slug && $slug !== $pool_slug ) {
				return 'Ürün hiçbir sitede görünmediği için adresi değiştirilemez; adres her site için ayrı tutulur. Önce SİTE hücresine ürünün görüneceği siteyi yazın ya da SAYFA ADRESİ hücresini boş bırakın (boş hücre adresi değiştirmez).';
			}
		}

		return array( 'changes' => array(), 'notes' => $notes );
	}

	if ( $bare ) {
		foreach ( $sites as $blog_id ) {
			$wanted[ $blog_id ] = $wanted[ $blog_id ] ?? (string) array_key_first( $bare );
		}
	}

	$changes = array();

	foreach ( $wanted as $blog_id => $slug ) {
		$now = nwcs_product_site_slug( $product_id, $pool_slug, (int) $blog_id );

		if ( $slug !== $now ) {
			$changes[ (int) $blog_id ] = array( $now, $slug );
		}
	}

	return array( 'changes' => $changes, 'notes' => $notes );
}

/**
 * Dosyanin butun adres degisiklikleri birlikte denetlenir; hatali satir
 * (urunun diger alanlariyla birlikte) yuklenmeyecekler listesine gecer.
 *   - ayni sitede ayni yeni adresi iki satir istiyorsa ikisi de hata;
 *   - cakisma nwcs_sync_slug_conflict ile; ayni dosyada adresinden ayrilan
 *     urunun ozel adresi alinabilir (zincir: A b'ye, B c'ye);
 *   - siraya konamayan degisiklik (takas: A b'ye, B a'ya) hata;
 *   - baska urunun eski adresi alinirsa not.
 */
function nwcs_sync_check_addresses( array &$plan, array $pending, array $new ): void {
	if ( ! $pending && ! $new ) {
		return;
	}

	$bad      = array(); // urun kimligi => mesaj
	$bad_new  = array(); // satir => mesaj
	$released = array(); // blog => [ozel adres => urun]
	$claimed  = array(); // "blog|adres" => satir
	$notes    = array(); // array( urun, adresi birakan urun, metin ): hatali satirda yazilmaz
	$names    = nwcs_sync_sites();
	$on       = static fn( int $blog_id, string $message ): string => sprintf( '%s adresi: %s', $names[ $blog_id ] ?? $blog_id, $message );

	foreach ( $pending as $item ) {
		if ( $item['from'] !== nwcs_override_pool_slug( $item['id'] ) ) {
			$released[ $item['blog'] ][ $item['from'] ] = $item['id'];
		}
	}

	foreach ( $pending as $item ) {
		$key = $item['blog'] . '|' . $item['to'];

		if ( isset( $claimed[ $key ] ) ) {
			$bad[ $item['id'] ] = $on( $item['blog'], sprintf( '“%s” adresi bu dosyada %d. satırda da yazılı. İki ürün aynı adresi kullanamaz; birine başka bir adres yazın.', $item['to'], $claimed[ $key ]['line'] ) );
			$bad[ $claimed[ $key ]['id'] ] ??= $on( $item['blog'], sprintf( '“%s” adresi bu dosyada %d. satırda da yazılı. İki ürün aynı adresi kullanamaz; birine başka bir adres yazın.', $item['to'], $item['line'] ) );
			continue;
		}

		$claimed[ $key ] = $item;
		$conflict        = nwcs_sync_slug_conflict( $item['blog'], $item['id'], $item['to'], $released[ $item['blog'] ] ?? array() );

		if ( '' !== $conflict ) {
			$bad[ $item['id'] ] = $on( $item['blog'], $conflict );
			continue;
		}

		// Zincir: adres ayni dosyada baska urunden bosaliyor. Gecerli, ama o urunun
		// bu adresle paylasilmis baglantilari artik bu urunu acar.
		$from_id = (int) ( $released[ $item['blog'] ][ $item['to'] ] ?? 0 );

		if ( $from_id && $from_id !== $item['id'] ) {
			$from_title = (string) ( array_column( $pending, 'title', 'id' )[ $from_id ] ?? $from_id );
			$notes[]    = array( $item['id'], $from_id, sprintf( '“%s” şu anda “%s” ürününün adresi; o ürün yeni adresine geçince bu adres “%s” ürününe verilecek. “%s” ürününün bu adresle paylaşılmış bağlantıları artık “%s” ürününü açar.', $item['to'], $from_title, $item['title'], $from_title, $item['title'] ) );
		}

		$owner = nwcs_sync_slug_old_owner( $item['blog'], $item['id'], $item['to'] );

		if ( '' !== $owner ) {
			$notes[] = array( $item['id'], 0, sprintf( '“%s” şu anda “%s” ürününün eski adresi; “%s” bu adresi alınca o eski bağlantı artık “%s” ürününe yönlenmez.', $item['to'], $owner, $item['title'], $owner ) );
		}
	}

	// Sira: yeni adresi baska bir bekleyen urunun simdiki adresiyse o once
	// tasinmali. Ilerleme kalmadiginda kalanlar takas ya da hatali satira bagli.
	$holder = array();

	foreach ( $pending as $item ) {
		if ( isset( $released[ $item['blog'] ][ $item['from'] ] ) ) {
			$holder[ $item['blog'] ][ $item['from'] ] = $item['id'];
		}
	}

	$left = array_filter( $pending, static fn( array $item ): bool => ! isset( $bad[ $item['id'] ] ) );

	do {
		$progress = false;

		foreach ( $left as $index => $item ) {
			$held = $holder[ $item['blog'] ][ $item['to'] ] ?? 0;

			if ( $held && $held !== $item['id'] ) {
				continue;
			}

			if ( ( $holder[ $item['blog'] ][ $item['from'] ] ?? 0 ) === $item['id'] ) {
				unset( $holder[ $item['blog'] ][ $item['from'] ] );
			}

			$holder[ $item['blog'] ][ $item['to'] ] = $item['id'];
			unset( $left[ $index ] );
			$progress = true;
		}
	} while ( $progress && $left );

	foreach ( $left as $item ) {
		$bad[ $item['id'] ] = $on( $item['blog'], sprintf( 'Yeni adres (%s) şu anda başka bir ürünün adresi. O ürünün adresi de bu dosyada değişiyor ama sırayla yapılamıyor. İki ürün adreslerini birbiriyle değiştiriyorsa: önce birine geçici bir adres yazıp dosyayı yükleyin, sonra asıl adresleri yazıp yeniden yükleyin. O ürünün satırında hata varsa önce o hatayı düzeltin.', $item['to'] ) );
	}

	// Notlar yalnizca yuklenecek satirlar icin (ve adresi birakan satir da yuklenecekse).
	foreach ( $notes as $note ) {
		if ( ! isset( $bad[ $note[0] ] ) && ( ! $note[1] || ! isset( $bad[ $note[1] ] ) ) ) {
			$plan['notes'][] = $note[2];
		}
	}

	// Yeni urunler: havuz adresi ag genelidir; gorunecegi sitelerde de bos olmali.
	$placed = array_keys( nwcs_placement_labels( (string) $plan['slug'] ) );

	foreach ( $new as $item ) {
		$sites = null !== $item['sites'] ? $item['sites'] : $placed;

		foreach ( $sites as $blog_id ) {
			$key = $blog_id . '|' . $item['slug'];

			if ( isset( $claimed[ $key ] ) ) {
				$bad_new[ $item['line'] ] = sprintf( '“%s” adresi bu dosyada %d. satırda da yazılı. İki ürün aynı adresi kullanamaz; birine başka bir adres yazın.', $item['slug'], $claimed[ $key ]['line'] );
				break;
			}

			$claimed[ $key ] = $item + array( 'id' => 0 );
			$conflict        = nwcs_sync_slug_conflict( (int) $blog_id, 0, $item['slug'], $released[ $blog_id ] ?? array() );

			if ( '' !== $conflict ) {
				$bad_new[ $item['line'] ] = $on( (int) $blog_id, $conflict );
				break;
			}
		}

		if ( isset( $bad_new[ $item['line'] ] ) ) {
			continue;
		}

		// Havuz adresi ag geneli: gorunecegi site olmasa da tekil olmali.
		if ( isset( $claimed[ '*|' . $item['slug'] ] ) ) {
			$bad_new[ $item['line'] ] = sprintf( '“%s” adresi bu dosyada %d. satırda da yazılı. İki ürün aynı adresi kullanamaz; birine başka bir adres yazın.', $item['slug'], $claimed[ '*|' . $item['slug'] ]['line'] );
			continue;
		}

		$claimed[ '*|' . $item['slug'] ] = $item;

		foreach ( nwcs_pool_trashed_ids() as $trashed ) {
			if ( nwcs_override_pool_slug( (int) $trashed ) === $item['slug'] ) {
				$bad_new[ $item['line'] ] = 'Bu adres çöp kutusundaki bir ürüne ait; ürün geri getirilirse bu adresi kullanacak. Başka bir adres yazın.';
				continue 2;
			}
		}

		// Havuzda (cop dahil) ayni adla urun: WordPress sessizce "-2" eklerdi.
		$taken = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'name' => $item['slug'], 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1 ) );

		if ( $taken ) {
			$bad_new[ $item['line'] ] = sprintf( 'Bu adresi (%s) başka bir ürün kullanıyor. Başka bir adres yazın.', $item['slug'] );
		}
	}

	if ( ! $bad && ! $bad_new ) {
		return;
	}

	foreach ( $plan['update'] as $index => $item ) {
		if ( isset( $bad[ $item['id'] ] ) ) {
			$plan['errors'][] = array( 'line' => $item['line'], 'name' => $item['title'], 'message' => $bad[ $item['id'] ], 'missing' => array() );
			$plan['stale']    = array_values( array_diff( $plan['stale'], array( $item['title'] ) ) );
			unset( $plan['update'][ $index ] );
		}
	}

	foreach ( $plan['create'] as $index => $item ) {
		if ( isset( $bad_new[ $item['line'] ] ) ) {
			$plan['errors'][] = array( 'line' => $item['line'], 'name' => $item['data']['title'], 'message' => $bad_new[ $item['line'] ], 'missing' => array() );
			unset( $plan['create'][ $index ] );
		}
	}

	$plan['update'] = array_values( $plan['update'] );
	$plan['create'] = array_values( $plan['create'] );
}

/**
 * Onizlemedeki site degisikligi: "Koçist’ten kalkacak, WOOD KOCIST’e eklenecek".
 *
 * @param int[] $now
 * @param int[] $wanted
 */
function nwcs_sync_site_moves( array $now, array $wanted ): string {
	$parts = array();

	foreach ( nwcs_sync_sites() as $blog_id => $label ) {
		$was  = in_array( $blog_id, $now, true );
		$will = in_array( $blog_id, $wanted, true );

		if ( $was && ! $will ) {
			$parts[] = nwcs_ablative( $label ) . ' kalkacak';
		} elseif ( ! $was && $will ) {
			$parts[] = nwcs_dative( $label ) . ' eklenecek';
		}
	}

	return implode( ', ', $parts );
}

/**
 * Turkce ayrilma eki: "Koçist" -> "Koçist’ten", "WOOD KOCIST" -> "WOOD KOCIST’ten".
 */
function nwcs_ablative( string $name ): string {
	$low    = mb_strtolower( str_replace( 'I', 'i', $name ), 'UTF-8' );
	$vowels = preg_replace( '/[^aeıioöuü]/u', '', $low );
	$front  = in_array( '' !== $vowels ? mb_substr( $vowels, -1 ) : 'e', array( 'e', 'i', 'ö', 'ü' ), true );
	$hard   = in_array( mb_substr( $low, -1 ), array( 'f', 's', 't', 'k', 'ç', 'ş', 'h', 'p' ), true );

	return $name . '’' . ( $hard ? 't' : 'd' ) . ( $front ? 'en' : 'an' );
}

/**
 * Turkce yonelme eki: "Koçist" -> "Koçist’e", "Ahşap Kasa" -> "Ahşap Kasa’ya".
 */
function nwcs_dative( string $name ): string {
	$low    = mb_strtolower( str_replace( 'I', 'i', $name ), 'UTF-8' );
	$vowels = preg_replace( '/[^aeıioöuü]/u', '', $low );
	$front  = in_array( '' !== $vowels ? mb_substr( $vowels, -1 ) : 'e', array( 'e', 'i', 'ö', 'ü' ), true );
	$vowel  = in_array( mb_substr( $low, -1 ), array( 'a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü' ), true );

	return $name . '’' . ( $vowel ? 'y' : '' ) . ( $front ? 'e' : 'a' );
}

/**
 * Urunleri istenen sitelerde gosterir, digerlerinde gizler. Site basina en
 * fazla iki yazim (goster, gizle; nwcs_product_show_on_site). Yalnizca
 * gercekten degisenlerin onceki hali doner (geri alma icin).
 *
 * @param array<int, int[]> $wanted urun => blog_id listesi
 * @return array<int, array<int, array{hidden:int, selected:int}>> blog_id => urun => onceki hal
 */
function nwcs_sync_apply_sites( array $wanted ): array {
	$before = array();

	if ( ! $wanted ) {
		return $before;
	}

	// Yeni urunler havuz onbellegine girsin (site ayari yazici havuzda olmayani atar).
	nwcs_pool_flush_cache();

	foreach ( array_keys( nwcs_sync_sites() ) as $blog_id ) {
		$settings = nwcs_site_product_settings( $blog_id );
		$selected = array_flip( $settings['selected'] );
		$show     = array();
		$hide     = array();

		foreach ( $wanted as $id => $sites ) {
			$id      = (int) $id;
			$hidden  = ! empty( $settings['overrides'][ $id ]['hidden'] );
			$visible = ! $hidden && ( 'all' === $settings['mode'] || isset( $selected[ $id ] ) );
			$want    = in_array( $blog_id, array_map( 'intval', (array) $sites ), true );

			if ( $want === $visible ) {
				continue;
			}

			$before[ $blog_id ][ $id ] = array( 'hidden' => $hidden ? 1 : 0, 'selected' => isset( $selected[ $id ] ) ? 1 : 0 );

			if ( $want ) {
				$show[] = $id;
			} else {
				$hide[] = $id;
			}
		}

		if ( $show ) {
			nwcs_product_show_on_site( $blog_id, $show, true );
		}

		if ( $hide ) {
			nwcs_product_show_on_site( $blog_id, $hide, false );
		}
	}

	nwcs_sync_product_sites( 0, true );

	return $before;
}

/**
 * Site gorunurlugunu kayittaki onceki haline getirir (yalnizca $ids).
 * Gizleme bayragi aynen; islem secime eklediyse secimden cikar. Site basina
 * bir yazim.
 *
 * @param array<int, array<int, array{hidden:int, selected:int}>> $visibility
 * @param int[]                                                    $ids
 * @return int Eski haline donen urun-site sayisi.
 */
function nwcs_sync_undo_sites( array $visibility, array $ids ): int {
	$ids   = array_flip( array_map( 'intval', $ids ) );
	$count = 0;

	foreach ( $visibility as $blog_id => $rows ) {
		$rows = array_intersect_key( (array) $rows, $ids );

		if ( ! $rows ) {
			continue;
		}

		switch_to_blog( (int) $blog_id );

		$settings = nwcs_site_product_settings();
		$remove   = array();

		foreach ( $rows as $id => $row ) {
			$id = (int) $id;

			$settings['overrides'][ $id ] = array_merge( (array) ( $settings['overrides'][ $id ] ?? array() ), array( 'hidden' => (int) ( $row['hidden'] ?? 0 ) ) );

			if ( empty( $row['selected'] ) ) {
				$remove[] = $id;
			}

			++$count;
		}

		$settings['selected'] = array_values( array_diff( $settings['selected'], $remove ) );
		nwcs_save_site_product_settings( $settings );

		// Yazici cop kutusundaki urunu onceki secimdeki yerine geri koyar; burada cikmali.
		if ( $remove ) {
			update_option( NWCS_OPTION_SELECTED, array_values( array_diff( array_map( 'intval', (array) get_option( NWCS_OPTION_SELECTED, array() ) ), $remove ) ) );
		}

		restore_current_blog();
	}

	nwcs_sync_product_sites( 0, true );

	return $count;
}

/**
 * Kategorideki yayindaki urunler, havuz sirasiyla.
 *
 * @return array<int, array> nwcs_pool_products() satirlari.
 */
function nwcs_sync_category_products( string $slug ): array {
	return array_filter( nwcs_pool_products(), static fn( array $product ): bool => isset( $product['categories'][ $slug ] ) );
}

/**
 * Kategorinin detay sutunlari: zorunlu basliklarin hepsi + kategorinin
 * urunlerinden en az birinde dolu olan opsiyoneller, kayit sirasiyla.
 *
 * @return array<string, array{label:string, required:bool}>
 */
function nwcs_sync_category_headings( string $slug ): array {
	$used = array();

	foreach ( nwcs_sync_category_products( $slug ) as $product ) {
		foreach ( (array) $product['details'] as $row ) {
			$used[ nwcs_heading_key( (string) $row['label'] ) ] = true;
		}
	}

	$out = array();

	foreach ( nwcs_product_headings() as $key => $row ) {
		if ( $row['required'] || isset( $used[ $key ] ) ) {
			$out[ $key ] = array( 'label' => $row['label'], 'required' => $row['required'] );
		}
	}

	return $out;
}

/**
 * Kategori taslagini gecici bir dosyaya yazar.
 *
 * @return array{path:string, filename:string}|WP_Error
 */
function nwcs_sync_build_template( string $slug ) {
	$categories = nwcs_pool_categories();

	if ( ! isset( $categories[ $slug ] ) ) {
		return new WP_Error( 'nwcs_cat', 'Kategori bulunamadı; silinmiş olabilir. Ürün Havuzu › Kategoriler sayfasını yenileyip yeniden deneyin.' );
	}

	$term     = $categories[ $slug ];
	$system   = nwcs_sync_system_columns();
	$headings = nwcs_sync_category_headings( $slug );

	$hints   = nwcs_sync_column_hints();
	$info    = nwcs_sync_info_columns();
	$hinted  = static fn( string $field ): string => $system[ $field ] . ' (' . $hints[ $field ] . ')';
	$header  = array( $system['id'], $system['code'] . ' *', $system['title'] . ' *', $system['site'], $system['price'], $system['short'], $hinted( 'meta_title' ), $hinted( 'meta_desc' ), $hinted( 'body' ), $hinted( 'address' ) );
	$columns = array(
		0 => array( 'hidden' => true, 'width' => 8 ),
		1 => array( 'width' => 16 ),
		2 => array( 'width' => 38 ),
		3 => array( 'width' => 22 ),
		4 => array( 'width' => 14 ),
		5 => array( 'width' => 44, 'wrap' => true ),
		6 => array( 'width' => 40, 'wrap' => true ),
		7 => array( 'width' => 50, 'wrap' => true ),
		8 => array( 'width' => 70, 'wrap' => true ),
		9 => array( 'width' => 44, 'wrap' => true ),
	);

	foreach ( $headings as $row ) {
		$columns[ count( $header ) ] = array( 'width' => max( 14, min( 30, mb_strlen( $row['label'] ) + 4 ) ) );
		$header[]                    = nwcs_sync_heading_header( $row['label'] ) . ( $row['required'] ? ' *' : '' );
	}

	// Bilgi sutunu en sonda: doldurulacak sutunlarin arasina girmesin.
	$columns[ count( $header ) ] = array( 'width' => 26 );
	$header[]                    = $info['extra'];

	$rows = array( $header );

	foreach ( nwcs_sync_category_products( $slug ) as $product ) {
		$body   = (string) $product['body'];
		$tables = count( nwcs_desc_split( $body )['tables'] );
		$line   = array( (string) $product['id'], (string) $product['code'], (string) $product['title'], nwcs_sync_sites_text( nwcs_sync_product_sites( (int) $product['id'] ) ), (string) $product['price'], (string) $product['short'], (string) ( $product['meta_title'] ?? '' ), (string) ( $product['meta_desc'] ?? '' ), nwcs_desc_html_to_text( $body ), nwcs_sync_address_text( (int) $product['id'], (string) $product['slug'] ) );

		foreach ( array_keys( $headings ) as $key ) {
			$line[] = nwcs_details_value( (array) $product['details'], $key );
		}

		$line[] = $tables ? sprintf( '%d tablo: bu dosyada yok, değişmez', $tables ) : '';
		$rows[] = $line;
	}

	$now  = time();
	$info = array(
		array( 'anahtar', 'değer' ),
		array( 'tur', NWCS_SYNC_KIND ),
		array( 'kategori', $slug ),
		array( 'kategori_id', (string) $term['id'] ),
		array( 'kategori_adi', $term['name'] ),
		array( 'indirme', (string) $now ),
		array( 'indirme_tarihi', wp_date( 'd.m.Y H:i', $now ) ),
		array( 'surum', (string) NWCS_SYNC_VERSION ),
		array( 'kaynak', network_home_url( '/' ) ),
	);

	$path  = nwcs_import_temp_xlsx();
	$write = nwcs_xlsx_write(
		$path,
		array(
			array( 'name' => NWCS_SYNC_SHEET, 'rows' => $rows, 'columns' => $columns ),
			array( 'name' => 'Nasıl kullanılır', 'rows' => nwcs_sync_help_rows( $term['name'] ), 'columns' => array( 0 => array( 'width' => 110, 'wrap' => true ) ) ),
			array( 'name' => NWCS_SYNC_INFO, 'rows' => $info, 'hidden' => true, 'header' => false, 'columns' => array( 0 => array( 'width' => 16 ), 1 => array( 'width' => 40 ) ) ),
		)
	);

	if ( is_wp_error( $write ) ) {
		return $write;
	}

	return array(
		'path'     => $path,
		'filename' => 'urunler-' . sanitize_title( $term['name'] ) . '-' . wp_date( 'Y-m-d', $now ) . '.xlsx',
	);
}

/**
 * "Nasıl kullanılır" sayfasi.
 */
function nwcs_sync_help_rows( string $category ): array {
	return array(
		array( 'Bu dosya “' . $category . '” kategorisinin ürün listesidir. Ürünler “Ürünler” sayfasındadır; her satır bir üründür.' ),
		array( 'TEMEL KURALLAR' ),
		array( '1. Yıldızlı (*) sütunlar zorunludur. Biri boş kalan satır yüklenmez; hangi satırda ne eksik olduğu panelde yazar.' ),
		array( '2. ÜRÜN KODU zorunludur; her ürüne bir kod yazın. Fotoğraf adları bu kodla başlar (örnek: W-KAM-400DUB-1.jpg). Kodu boş olan satır yüklenmez.' ),
		array( '3. Boş hücre o bilgiyi siler. Örnekler:' ),
		array( '• FİYAT boşsa sitede “Teklif al” görünür.' ),
		array( '• META BAŞLIK ve META AÇIKLAMA boşsa ürün sayfası otomatik başlık ve açıklamayı kullanır.' ),
		array( '• ÜRÜN AÇIKLAMASI boşsa açıklama silinir. Ürün sayfası açık kalır; yalnızca açıklama bölümü görünmez.' ),
		array( '4. İki istisna: SİTE ve SAYFA ADRESİ boş bırakılırsa değişmez.' ),
		array( '5. Sütun adlarını ve gizli sayfaları değiştirmeyin.' ),
		array( '6. Excel bazı değerleri tarihe çevirebilir (örneğin 1/2). Böyle hücreleri kontrol edin.' ),
		array( 'YENİ ÜRÜN, ÜRÜN KALDIRMA, YENİ BAŞLIK' ),
		array( '7. Yeni ürün: en alta bir satır ekleyin ve ÜRÜN KODU hücresine kodunu yazın. Başka bir satırı kopyaladıysanız yeni satırın ÜRÜN KODU hücresine yeni ürünün kodunu yazın.' ),
		array( '8. Ürün kaldırma: satırı silin. Yüklemeden sonra çıkan önizlemede “çöp kutusuna taşı” kutusunu işaretlerseniz ürün çöp kutusuna taşınır; oradan geri getirilebilir. İşaretlemezseniz ürüne dokunulmaz.' ),
		array( '9. Yeni detay başlığı: sağa bir sütun ekleyin ve ilk satırına başlığın adını yazın. En az bir hücresi doluysa başlık olarak eklenir. Var olan sütun adlarını (ÜRÜN KODU, FİYAT, META BAŞLIK gibi) başlık adı olarak kullanmayın.' ),
		array( 'SÜTUNLAR' ),
		array( 'SİTE: ürünün görüneceği site. Hepsi ya da site adları virgülle: ' . implode( ', ', nwcs_sync_sites() ) . '. Boş bırakırsanız değişmez; yeni ürün, kategorinin yerleştiği sitelerde görünür. Yazmadığınız sitede ürün gizlenir ama silinmez.' ),
		array( 'META BAŞLIK ve META AÇIKLAMA: Google’da ve bağlantı paylaşıldığında görünen başlık ve açıklama.' ),
		array( '• Başlık sayfaya olduğu gibi yazılır; site adı eklenmez.' ),
		array( '• Başlık en fazla 60, açıklama en fazla 160 karakter olmalı. Uzunsa yine yüklenir, ama önizlemede uyarı çıkar ve Google’da sonu kesilir.' ),
		array( '• İkisi de tek satır olarak kaydedilir.' ),
		array( 'ÜRÜN AÇIKLAMASI: ürün sayfasındaki uzun metin.' ),
		array( '• Paragrafları boş bir satırla ayırın. Hücre içinde yeni satır: Alt+Enter (Mac’te Option+Enter).' ),
		array( '• Noktasız, kısa (en fazla 80 karakter), tek başına duran bir satır alt başlık olur (örnek: “Teknik özellikler”). Sonunda nokta, virgül, iki nokta gibi bir işaret varsa ya da açıklama tek paragraftan oluşuyorsa başlık yapılmaz.' ),
		array( '• “• ”, “- ” ya da “* ” ile başlayan satırlar madde listesi olur; “> ” ile başlayanlar alıntı olur.' ),
		array( '• Yalnızca boşlukları ya da paragraf düzeni değişen açıklama değişmemiş sayılır.' ),
		array( '• Açıklamadaki tablolar bu dosyada yok ve değişmez (EK İÇERİK sütununda yazar).' ),
		array( '• “' . NWCS_DESC_MARKER . '” yazan hücrede görsel ya da bağlantı gibi özel içerik var. Dokunmazsanız değişmez; üzerine yazarsanız özel içerik silinir.' ),
		array( 'SAYFA ADRESİ: ürünün göründüğü her sitedeki adresi; her site ayrı satırda.' ),
		array( '• Yalnızca /urun/ sonrasını değiştirin ya da yalnızca yeni adresi yazın (örnek: ahsap-cardak-2x2). Türkçe harfler sadeleşir, boşluklar tire olur.' ),
		array( '• Eski adres yeni adrese yönlenir; paylaşılmış bağlantılar kırılmaz.' ),
		array( '• Ürün iki sitedeyse her satır kendi sitesinin adresidir. Tek adres yazarsanız iki sitede de o adres olur.' ),
		array( '• Otomatik adrese dönmek için “otomatik” yazın. Boş bırakırsanız adres değişmez.' ),
		array( '• Yeni üründe boşsa adres ürün adından üretilir. Başka bir satırı kopyaladıysanız adres hücresini boşaltın ya da yeni bir adres yazın.' ),
		array( 'EK İÇERİK: yalnızca bilgi içindir; yüklemede okunmaz.' ),
		array( 'YÜKLEME' ),
		array( '10. Bitince dosyayı .xlsx olarak kaydedin. Ürün Havuzu › Kategoriler sayfasında bu kategoriyi açıp “Yükle” ile yükleyin.' ),
		array( '11. Önce neyin değişeceği gösterilir; siz onaylamadan hiçbir şey değişmez.' ),
		array( 'BU DOSYADA OLMAYANLAR (bu dosyayla değişmez)' ),
		array( '• Fotoğraflar ve görseller. Fotoğraf dosyalarını ürün koduyla adlandırın (W-KAM-400DUB-1.jpg, -2.jpg …) ve aynı kategori sayfasındaki “Fotoğrafları yükleyin” alanına bırakın.' ),
		array( '• Açıklamadaki tablolar.' ),
		array( '• Sitelere özel ad, kart açıklaması, fiyat ve görsel ayarları (Özelleştirmeler).' ),
	);
}

/**
 * Hizli Excel taslagi: yalnizca KİMLİK (gizli), ÜRÜN KODU, ÜRÜN ADI, FİYAT, SİTE.
 * $slug bossa havuzdaki butun urunler. Yukleme kategori Excel'iyle ayni
 * ekrandan; _bilgi sayfasindaki sablon=hizli plani "yalnizca guncelle" kipine alir.
 *
 * @return array{path:string, filename:string}|WP_Error
 */
function nwcs_sync_build_quick_template( string $slug ) {
	$categories = nwcs_pool_categories();

	if ( '' !== $slug && ! isset( $categories[ $slug ] ) ) {
		return new WP_Error( 'nwcs_cat', 'Kategori bulunamadı; silinmiş olabilir. Ürün Havuzu › Kategoriler sayfasını yenileyip yeniden deneyin.' );
	}

	$system   = nwcs_sync_system_columns();
	$name     = '' === $slug ? 'Tüm ürünler' : $categories[ $slug ]['name'];
	$products = '' === $slug ? nwcs_pool_products() : nwcs_sync_category_products( $slug );
	$rows     = array( array( $system['id'], $system['code'] . ' *', $system['title'] . ' *', $system['price'], $system['site'] ) );

	foreach ( $products as $product ) {
		$rows[] = array( (string) $product['id'], (string) $product['code'], (string) $product['title'], (string) $product['price'], nwcs_sync_sites_text( nwcs_sync_product_sites( (int) $product['id'] ) ) );
	}

	$now  = time();
	$info = array(
		array( 'anahtar', 'değer' ),
		array( 'tur', NWCS_SYNC_KIND ),
		array( 'sablon', NWCS_SYNC_QUICK ),
		array( 'kategori', $slug ),
		array( 'kategori_id', '' === $slug ? '' : (string) $categories[ $slug ]['id'] ),
		array( 'kategori_adi', $name ),
		array( 'indirme', (string) $now ),
		array( 'indirme_tarihi', wp_date( 'd.m.Y H:i', $now ) ),
		array( 'surum', (string) NWCS_SYNC_VERSION ),
		array( 'kaynak', network_home_url( '/' ) ),
	);

	$path  = nwcs_import_temp_xlsx();
	$write = nwcs_xlsx_write(
		$path,
		array(
			array(
				'name'    => NWCS_SYNC_SHEET,
				'rows'    => $rows,
				'columns' => array(
					0 => array( 'hidden' => true, 'width' => 8 ),
					1 => array( 'width' => 18 ),
					2 => array( 'width' => 50 ),
					3 => array( 'width' => 16 ),
					4 => array( 'width' => 26 ),
				),
			),
			array( 'name' => 'Nasıl kullanılır', 'rows' => nwcs_sync_quick_help_rows( $name ), 'columns' => array( 0 => array( 'width' => 110, 'wrap' => true ) ) ),
			array( 'name' => NWCS_SYNC_INFO, 'rows' => $info, 'hidden' => true, 'header' => false, 'columns' => array( 0 => array( 'width' => 16 ), 1 => array( 'width' => 40 ) ) ),
		)
	);

	if ( is_wp_error( $write ) ) {
		return $write;
	}

	return array(
		'path'     => $path,
		'filename' => 'hizli-' . sanitize_title( '' === $slug ? 'tum-urunler' : $name ) . '-' . wp_date( 'Y-m-d', $now ) . '.xlsx',
	);
}

/**
 * Hizli Excel'in "Nasıl kullanılır" sayfasi.
 */
function nwcs_sync_quick_help_rows( string $scope ): array {
	return array(
		array( 'Bu dosya “' . $scope . '” için Hızlı Excel’dir: yalnızca ürün kodu, adı, fiyatı ve sitesi.' ),
		array( '1. Yalnızca var olan ürünler güncellenir. Yeni ürün eklemek ya da açıklama, meta başlık, teknik detay değiştirmek için kategorinin Excel’ini kullanın.' ),
		array( '2. Satır silmek ürünü silmez; ürüne dokunulmaz. İsterseniz yalnızca değiştireceğiniz satırları bırakabilirsiniz.' ),
		array( '3. ÜRÜN KODU ve ÜRÜN ADI zorunludur.' ),
		array( '4. Kodu değiştirmek için hücredeki kodu düzeltin. Satırı silip yeni kodla yeniden yazmayın: ürün, satırdaki gizli bir sütunla ya da eski koduyla tanınır; yeni kodla yazılan satırın hangi ürün olduğu anlaşılamaz ve yüklenmez.' ),
		array( '5. Kodu olmayan ürünün satırı değişmediyse atlanır. Bu ürünü değiştirecekseniz önce ÜRÜN KODU hücresine kodunu yazın.' ),
		array( '6. FİYAT boşsa sitede “Teklif al” görünür.' ),
		array( 'SİTE: ürünün görüneceği site. Hepsi ya da site adları virgülle: ' . implode( ', ', nwcs_sync_sites() ) . '. Boş bırakırsanız değişmez. Yazmadığınız sitede ürün gizlenir ama silinmez.' ),
		array( '7. Sütun adlarını ve gizli sayfaları değiştirmeyin. Eklediğiniz başka sütunlar okunmaz.' ),
		array( '8. Bitince dosyayı .xlsx olarak kaydedin ve Ürün Havuzu › Kategoriler sayfasında “Yükle” ile yükleyin:' ),
		array( '• kategorinin Hızlı Excel’ini o kategorinin sayfasında,' ),
		array( '• tüm ürünlerin Hızlı Excel’ini kategori seçmeden açılan “Tüm ürünler” kutusunda.' ),
		array( '9. Önce neyin değişeceği gösterilir; siz onaylamadan hiçbir şey değişmez.' ),
	);
}

/* ====================================================================== *
 * Plan (onizleme; hicbir sey yazilmaz)
 * ====================================================================== */

/**
 * Yalnizca rakamdan olusan fiyati okunur bicime getirir: 3500 -> "3.500 ₺".
 *   - Excel'in sayi olarak sakladigi hucre ("3500", "1250.5");
 *   - metin olarak yazilmis salt rakam ("3500") ya da virgullu kurus ("3500,5").
 * Birimli ya da noktali metne ("48.500 ₺", "450 TL", "48.500") dokunulmaz.
 */
function nwcs_sync_price( string $value, bool $numeric ): string {
	$value  = nwcs_clean_text( $value );
	$number = null;

	if ( $numeric && preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) {
		$number = (float) $value;
	} elseif ( preg_match( '/^\d+(,\d{1,2})?$/', $value ) ) {
		$number = (float) str_replace( ',', '.', $value );
	}

	if ( null === $number ) {
		return $value;
	}

	$decimals = floor( $number ) === $number ? 0 : 2;

	return number_format( $number, $decimals, ',', '.' ) . ' ₺';
}

/**
 * Dosyadan plan cikarir. Havuza hicbir sey yazilmaz.
 *
 * @return array|WP_Error
 */
function nwcs_sync_plan( string $path, string $filename ) {
	$info = nwcs_sync_read_info( $path, NWCS_SYNC_KIND );

	if ( is_wp_error( $info ) ) {
		return $info;
	}

	$read = nwcs_xlsx_read_rows( $path, NWCS_SYNC_SHEET );

	if ( is_wp_error( $read ) ) {
		return 'nwcs_xlsx_nosheet' === $read->get_error_code()
			? new WP_Error( 'nwcs_sheet', 'Excel’de “Ürünler” sayfası bulunamadı. Excel’in altındaki sayfa sekmesinin adını değiştirdiyseniz yeniden “Ürünler” yapın.' )
			: $read;
	}

	$categories = nwcs_pool_categories();
	$slug       = sanitize_title( $info['kategori'] ?? '' );
	// Hizli Excel (sablon=hizli): yalnizca var olan urunler; kategori bos = tum urunler.
	$quick      = NWCS_SYNC_QUICK === ( $info['sablon'] ?? '' );
	$all        = $quick && '' === $slug && '' === (string) ( $info['kategori_id'] ?? '' );

	// Kategori adi degistiyse de kimlikle bulunur.
	if ( ! $all && ! isset( $categories[ $slug ] ) ) {
		foreach ( $categories as $candidate_slug => $candidate ) {
			if ( (int) $candidate['id'] === (int) ( $info['kategori_id'] ?? 0 ) ) {
				$slug = $candidate_slug;
			}
		}
	}

	if ( ! $all && ! isset( $categories[ $slug ] ) ) {
		return new WP_Error( 'nwcs_cat', sprintf( 'Bu dosyanın kategorisi (%s) artık yok; silinmiş olabilir. Kategori yakın zamanda silindiyse Ürün Havuzu › Kategoriler sayfasındaki “Son işlemler”den silme işlemini geri alın. Aynı adla yeni kategori açmayın; o zaman geri alınamaz. Kategori bilerek silindiyse başka bir kategorinin Excel’ini indirin.', $info['kategori_adi'] ?? $slug ) );
	}

	// Kesilen dosyada son satirlar okunmaz; o urunler "dosyada yok" sayilip cope giderdi.
	if ( $read['truncated'] ) {
		return new WP_Error( 'nwcs_rows', sprintf( 'Dosyada çok fazla satır var: en fazla %s satır okunabiliyor. Dosyayı bölün; her kategori için ayrı Excel kullanın.', number_format_i18n( NWCS_XLSX_MAX_ROWS ) ) );
	}

	$rows    = $read['rows'];
	$numeric = $read['numeric'];
	$header  = array_map( 'strval', (array) array_shift( $rows ) );

	/* ---------- Sutunlar ---------- */
	$system   = array();
	$headings = nwcs_product_headings();
	$columns  = array();
	$seen     = array();

	foreach ( nwcs_sync_system_columns() as $field => $title ) {
		$system[ nwcs_heading_key( $title ) ] = $field;
	}

	$info_keys = array();
	$unread      = array(); // taslakta olmayan bilgi sutunlari
	$old_address = false;   // surum 2 taslaginin okunmayan adres sutunu

	foreach ( nwcs_sync_info_columns() as $field => $title ) {
		$info_keys[ nwcs_heading_key( (string) preg_replace( '/\s*\([^()]*\)$/u', '', $title ) ) ] = $field;
	}

	foreach ( $header as $index => $label ) {
		$base = trim( (string) preg_replace( '/\s*\*+\s*$/u', '', trim( $label ) ) );
		$key  = nwcs_heading_key( $base );

		if ( '' === $base ) {
			continue;
		}

		// Sondaki parantez ("META BAŞLIK (en fazla 60 karakter)") yalnizca aciklama
		// yazilan sistem sutunlarinda atilir. Kayitli detay basligi her zaman
		// baslik kalir: "Fiyat (Tek)", "Fiyat (Set)" (civi tabancalari)
		// FİYAT sayilsaydi sutun iki kez var hatasi verirdi.
		$plain     = trim( (string) preg_replace( '/\s*\([^()]*\)$/u', '', $base ) );
		$is_detail = (bool) preg_match( '/\(detay\)$/iu', $base ) || isset( $headings[ $key ] );
		$hinted    = $system[ nwcs_heading_key( $plain ) ] ?? '';

		// Bilgi sutunlari okunmaz. Parantez atilmadan once bakilir: surum 2
		// taslaklarinin "SAYFA ADRESİ (bilgi, panelden değişir)" sutunu adres
		// sutunu sayilsaydi eski dosyalar adresleri degistirirdi.
		if ( ! $is_detail && ! isset( $system[ $key ] ) && ( preg_match( '/\(\s*bilgi\b/', nwcs_search_fold( $base ) ) || isset( $info_keys[ nwcs_heading_key( $plain ) ] ) ) ) {
			$columns[ $index ] = array( 'type' => 'info', 'key' => $info_keys[ nwcs_heading_key( $plain ) ] ?? '', 'label' => $base );

			if ( 'address' === $hinted ) {
				$old_address = true;
			} elseif ( '' === $columns[ $index ]['key'] ) {
				// Taslakta olmayan bilgi sutunu ("Notlar (bilgi)"): okunmadigi onizlemede yazsin.
				$unread[] = $base;
			}

			continue;
		}

		if ( ! isset( $system[ $key ] ) && ! $is_detail && $plain !== $base && isset( nwcs_sync_column_hints()[ $hinted ] ) ) {
			$base = $plain;
			$key  = nwcs_heading_key( $plain );
		}

		if ( isset( $system[ $key ] ) ) {
			if ( isset( $seen[ $system[ $key ] ] ) ) {
				return new WP_Error( 'nwcs_cols', sprintf( '“%s” sütunu dosyada iki kez var. Birini silin.', $base ) );
			}

			$columns[ $index ]        = array( 'type' => $system[ $key ], 'label' => $base );
			$seen[ $system[ $key ] ] = true;
			continue;
		}

		// "Fiyat (detay)": adi sistem sutunuyla ayni olan eski detay basligi.
		if ( preg_match( '/^(.+?)\s*\(detay\)$/iu', $base, $match ) && in_array( nwcs_heading_key( $match[1] ), nwcs_heading_reserved_keys(), true ) ) {
			$base = trim( $match[1] );
			$key  = nwcs_heading_key( $base );

			// Ayrilmis adla yeni baslik acilmaz; yazilsaydi detaya anahtar etiketiyle girerdi.
			if ( ! isset( $headings[ $key ] ) ) {
				return new WP_Error( 'nwcs_cols', sprintf( '“%s (detay)” sütununun adı tanınmadı: bu adla bir detay başlığı yok ve bu adla yeni başlık açılamaz. Sütunun adını değiştirin ya da sütunu silin.', $base ) );
			}
		}

		if ( ! nwcs_heading_is_valid( $base ) ) {
			continue;
		}

		if ( isset( $seen[ 'h:' . $key ] ) ) {
			return new WP_Error( 'nwcs_cols', sprintf( '“%s” başlığı dosyada iki kez var. Sütunlardan birini silin ya da adını değiştirin.', $base ) );
		}

		$seen[ 'h:' . $key ] = true;
		$columns[ $index ]   = array(
			'type'  => 'heading',
			'key'   => $key,
			'label' => isset( $headings[ $key ] ) ? $headings[ $key ]['label'] : nwcs_heading_clean_label( $base ),
			'new'   => ! isset( $headings[ $key ] ),
		);
	}

	// Hizli Excel: yalnizca kod, ad, fiyat ve site. Eklenen baska sutun (detay
	// basligi, kisa aciklama...) okunmaz; onizleme hangilerinin atlandigini yazar.
	$quick_skipped = array();

	if ( $quick ) {
		foreach ( $columns as $index => $column ) {
			if ( ! in_array( $column['type'], NWCS_SYNC_QUICK_FIELDS, true ) ) {
				// Taslakta olmayan bilgi sutunu zaten ayri notta yazar.
				if ( 'info' !== $column['type'] ) {
					$quick_skipped[] = $column['label'];
				}

				unset( $columns[ $index ] );
			}
		}
	}

	if ( empty( $seen['title'] ) ) {
		return new WP_Error( 'nwcs_cols', '“ÜRÜN ADI” sütunu bulunamadı. Sütun adlarını değiştirdiyseniz eski hâline getirin ya da Excel’i yeniden indirin.' );
	}

	// Urun kodu zorunlu: her urunun kodu olur, fotograflar bu kodla eslesir.
	if ( empty( $seen['code'] ) ) {
		return new WP_Error( 'nwcs_cols', '“ÜRÜN KODU” sütunu bulunamadı; silinmiş ya da adı değiştirilmiş olabilir. Excel’i yeniden indirin ve her ürünün kodunu ÜRÜN KODU sütununa yazın.' );
	}

	foreach ( $quick ? array() : nwcs_required_headings() as $key => $label ) {
		if ( empty( $seen[ 'h:' . $key ] ) ) {
			return new WP_Error( 'nwcs_cols', sprintf( '“%s” sütunu dosyada yok; bu başlık zorunlu. Excel’i yeniden indirin (bu sütun içinde gelir) ve onu doldurun.', $label ) );
		}
	}

	// Yeni sutun: en az bir hucresi doluysa yeni baslik olur, bossa atlanir.
	$new_headings = array();
	$ignored      = array();

	foreach ( $columns as $index => $column ) {
		if ( 'heading' !== $column['type'] || ! $column['new'] ) {
			continue;
		}

		// "KISA AÇIKLAMA (en fazla 300)": sistem sutununa aciklama eklenmis, asil
		// sutun dosyada yok. Sessizce yeni detay basligi olsaydi kisa aciklama
		// hic degismez, urunlere o adla detay yazilirdi.
		$like = $system[ nwcs_heading_key( (string) preg_replace( '/\s*\([^()]*\)$/u', '', $column['label'] ) ) ] ?? '';

		if ( '' !== $like && empty( $seen[ $like ] ) ) {
			return new WP_Error( 'nwcs_cols', sprintf( '“%s” sütununun adı değiştirilmiş. Sütun adlarını değiştirmeyin: parantez içini silin ya da Excel’i yeniden indirin.', $column['label'] ) );
		}

		$filled = false;

		foreach ( $rows as $row ) {
			if ( '' !== trim( (string) ( $row[ $index ] ?? '' ) ) ) {
				$filled = true;
				break;
			}
		}

		if ( $filled ) {
			$new_headings[ $column['key'] ] = $column['label'];
		} else {
			$ignored[] = $column['label'];
			unset( $columns[ $index ] );
		}
	}

	$required = $quick ? array() : nwcs_required_headings();

	/* ---------- Satirlar ---------- */
	switch_to_blog( nwcs_pool_blog_id() );

	$downloaded = (int) ( $info['indirme'] ?? 0 );
	$plan       = array(
		'time'       => time(),
		'file'       => sanitize_file_name( $filename ),
		'slug'       => $slug,
		'term_id'    => $all ? 0 : (int) $categories[ $slug ]['id'],
		'term_name'  => $all ? 'Tüm ürünler' : $categories[ $slug ]['name'],
		'quick'      => $quick,
		// Hizli dosyada kodu olmayan ve degismemis satirlar (hata sayilmaz).
		'codeless'   => array(),
		'downloaded' => $downloaded,
		'update'     => array(),
		'same'       => array(),
		'create'     => array(),
		'trash'      => array(),
		'errors'     => array(),
		'headings'   => $new_headings,
		'ignored'    => $ignored,
		'stale'      => array(),
		'restored'   => array(),
		'notes'      => array(),
		// Meta alani => onerilen uzunlugu asan urunler (yuklenir, uyarilir).
		'long'       => array(),
		// Aciklamasi silinecek urunler (bos ÜRÜN AÇIKLAMASI hucresi).
		'cleared'    => array(),
		// Cop kutusu kapaliysa (EMPTY_TRASH_DAYS = 0) cope atma kalici silme olurdu.
		'trash_off'  => ! nwcs_trash_enabled(),
	);

	if ( $unread ) {
		$plan['notes'][] = sprintf( 'Adında “(bilgi)” yazdığı için okunmayan sütunlar: %s. Bu sütunlara yazılanlar kaydedilmez.', implode( ', ', $unread ) );
	}

	if ( $old_address ) {
		$plan['notes'][] = 'Bu dosya eski bir Excel: içindeki SAYFA ADRESİ sütunu yalnızca bilgi içindir, adresler değişmez. Adresi Excel’den değiştirmek için Excel’i yeniden indirin.';
	}

	if ( $quick_skipped ) {
		$plan['notes'][] = sprintf( 'Hızlı Excel yalnızca ÜRÜN KODU, ÜRÜN ADI, FİYAT ve SİTE sütunlarını okur; okunmayan sütunlar: %s. Bunlar için kategorinin Excel’ini kullanın.', implode( ', ', $quick_skipped ) );
	}

	// Sayfa adresleri: once butun satirlar toplanir, cakisma dosyanin tamamina
	// bakilarak (takas, zincir) satirlardan sonra denetlenir.
	$addr_pending = array(); // var olan urun: id, blog, from, to, title, line
	$addr_new     = array(); // yeni urun: line, title, slug, sites

	// Havuzdaki ve sitelere ozel butun urun adresleri (yalnizca gerekirse, bir kez).
	$known_slugs = static function (): array {
		static $known = null;

		if ( null === $known ) {
			$known = array_flip( array_column( nwcs_pool_products(), 'slug' ) );

			foreach ( array_keys( nwcs_sync_sites() ) as $blog_id ) {
				foreach ( (array) nwcs_site_product_settings( $blog_id )['overrides'] as $override ) {
					$custom = nwcs_override_slug( is_array( $override ) ? $override : array() );

					if ( '' !== $custom ) {
						$known[ $custom ] = true;
					}
				}
			}
		}

		return $known;
	};

	// Hizli dosyada satir urunun simdiki haliyle ayni mi (ad, fiyat, site)?
	$quick_same = static function ( WP_Post $post, callable $cell ): bool {
		if ( nwcs_clean_text( (string) $cell( 'title' ) ) !== $post->post_title ) {
			return false;
		}

		$price = $cell( 'price' );

		if ( null !== $price ) {
			$raw = nwcs_clean_text( $price );
			$now = (string) get_post_meta( $post->ID, '_nwcs_price', true );

			if ( $raw !== $now && nwcs_sync_price( $raw, true ) !== $now ) {
				return false;
			}
		}

		$site = (string) $cell( 'site' );

		if ( '' !== $site ) {
			$wanted = nwcs_sync_parse_sites( $site );

			if ( is_wp_error( $wanted ) || $wanted !== nwcs_sync_product_sites( (int) $post->ID ) ) {
				return false;
			}
		}

		return true;
	};

	$check_long = static function ( string $field, string $value, string $name ) use ( &$plan ): void {
		if ( mb_strlen( $value ) > NWCS_SYNC_META_LIMITS[ $field ] ) {
			$plan['long'][ $field ][] = $name;
		}
	};

	$by_id   = array(); // urun kimligi => satir no
	$by_code = array(); // kod => satir no
	$matched = array(); // bu dosyada karsiligi olan urunler (hatali satirlar dahil)

	$site_column = ! empty( $seen['site'] );
	$site_rows   = array(); // SİTE'si yazilan (degisen ya da yeni) satirlarin siteleri
	$nowhere     = array(); // SİTE bos ve hicbir sitede gorunmeyen urunler

	foreach ( $rows as $offset => $row ) {
		$line = $offset + 2;

		if ( '' === trim( implode( '', array_map( 'strval', (array) $row ) ) ) ) {
			continue; // Tamamen bos satir.
		}

		$cell = static function ( string $type ) use ( $columns, $row ): ?string {
			foreach ( $columns as $index => $column ) {
				if ( $column['type'] === $type ) {
					return trim( (string) ( $row[ $index ] ?? '' ) );
				}
			}

			return null; // Sutun dosyada yok: alana dokunulmaz.
		};

		$raw_id = (string) $cell( 'id' );
		$title  = nwcs_clean_text( (string) $cell( 'title' ) );
		$code   = null === $cell( 'code' ) ? null : nwcs_normalize_product_code( (string) $cell( 'code' ) );
		$name   = '' !== $title ? $title : ( '' !== (string) $code ? (string) $code : 'adsız satır' );
		$fail   = static function ( string $message, array $missing = array() ) use ( &$plan, $line, $name ): void {
			$plan['errors'][] = array( 'line' => $line, 'name' => $name, 'message' => $message, 'missing' => $missing );
		};

		// Kod zorunlu: bos kodlu satir yuklenmez (urun oldugu gibi kalir; cope de gitmez).
		if ( '' === (string) $code ) {
			$own = preg_match( '/^\d+$/', $raw_id ) ? nwcs_sync_product_post( (int) $raw_id ) : null;

			if ( $own ) {
				$matched[ $own->ID ] = true;
			}

			// Hizli dosyada kodsuz ama degistirilmemis satir hata degil: tum urunler
			// dosyasinda kodu olmayan her urun icin bir hata satiri olurdu.
			if ( $quick && $own && '' === (string) get_post_meta( $own->ID, '_nwcs_code', true ) && $quick_same( $own, $cell ) ) {
				$plan['codeless'][] = $own->post_title;
				continue;
			}

			$fail( 'ÜRÜN KODU boş. Bu satıra ürünün kodunu yazın; her ürünün kodu olmalı, fotoğraflar ürüne bu kodla bağlanır.' );
			continue;
		}

		/* Eslesme: KIMLIK > KOD > yeni. */
		$post = null;

		if ( '' !== $raw_id ) {
			$post = preg_match( '/^\d+$/', $raw_id ) ? nwcs_sync_product_post( (int) $raw_id ) : null;

			if ( ! $post ) {
				$fail( $quick
					? 'Bu satırdaki ürün bulunamadı: ürün silinmiş ya da satır başka bir Excel dosyasından kopyalanmış olabilir. Hızlı Excel’le yeni ürün eklenemez; bu satırı silin, yeni ürünü kategorinin Excel’iyle ekleyin.'
					: 'Bu satırdaki ürün bulunamadı: ürün silinmiş ya da satır başka bir Excel dosyasından kopyalanmış olabilir. Bu ürünü yeni ürün olarak eklemek için satırı en alta yeniden yazın.' );
				continue;
			}

			// Kopyalanmis satir: KIMLIK ikinci kez geliyor. Kod ayniysa ayni urun
			// iki kez yazilmis demektir (hata); degilse satir yeni urun ya da
			// koduyla baska bir urun sayilir.
			if ( isset( $by_id[ $post->ID ] ) ) {
				$own_code = (string) get_post_meta( $post->ID, '_nwcs_code', true );

				if ( null === $code || $code === $own_code ) {
					$matched[ $post->ID ] = true;
					$fail( sprintf( 'Bu satır %d. satırdaki ürünle aynı (ürün kodu da aynı). Satırı kopyalayıp yeni ürün eklemek istediyseniz ÜRÜN KODU hücresine yeni ürünün kodunu yazın; değilse satırlardan birini silin.', $by_id[ $post->ID ] ) );
					continue;
				}

				$plan['notes'][] = sprintf( '%d. satır %d. satırdan kopyalanmış; ürün kodu farklı olduğu için ayrı bir ürün sayıldı.', $line, $by_id[ $post->ID ] );
				$post            = null;
				$raw_id          = '';
			} else {
				$by_id[ $post->ID ] = $line;
			}
		}

		if ( '' === $raw_id && '' !== (string) $code ) {
			$found = nwcs_product_id_by_code( (string) $code );
			$post  = $found ? nwcs_sync_product_post( $found ) : null;

			if ( $post && isset( $by_id[ $post->ID ] ) ) {
				$matched[ $post->ID ] = true;
				$fail( sprintf( 'Bu ürün kodu (%s) %d. satırdaki ürüne ait. Her ürünün kodu farklı olmalı; bu satıra başka bir kod yazın.', $code, $by_id[ $post->ID ] ) );
				continue;
			}

			if ( $post ) {
				$by_id[ $post->ID ] = $line;
			}
		}

		if ( $post ) {
			$matched[ $post->ID ] = true;
		}

		if ( $quick && $post && 'trash' === $post->post_status ) {
			$fail( 'Bu ürün çöp kutusunda. Hızlı Excel çöpteki ürünü geri getirmez; ürünü Ürün Havuzu’ndan ya da kategorinin Excel’iyle geri getirin.' );
			continue;
		}

		/* Satir denetimleri. */
		if ( '' === $title ) {
			$fail( 'ÜRÜN ADI boş. Bu satıra ürünün adını yazın.' . ( $quick ? '' : ' Ürünü kaldırmak istiyorsanız satırı tamamen silin.' ) );
			continue;
		}

		if ( null !== $code && '' !== $code ) {
			if ( isset( $by_code[ $code ] ) ) {
				$fail( sprintf( 'Bu ürün kodu (%s) %d. satırda da yazılı. Her ürünün kodu farklı olmalı; satırlardan birinin kodunu değiştirin.', $code, $by_code[ $code ] ) );
				continue;
			}

			$owner = nwcs_product_id_by_code( $code, $post ? (int) $post->ID : 0 );

			if ( $owner ) {
				$fail( sprintf( 'Bu ürün kodu (%s) başka bir ürüne ait: “%s”%s. Bu satıra farklı bir kod yazın.', $code, get_the_title( $owner ), 'trash' === get_post_status( $owner ) ? ' (çöp kutusunda)' : '' ) );
				continue;
			}

			$by_code[ $code ] = $line;
		}

		// Detay degerleri (dosyadaki basliklar).
		$values  = array();
		$missing = array();

		foreach ( $columns as $index => $column ) {
			if ( 'heading' === $column['type'] ) {
				$values[ $column['key'] ] = nwcs_detail_clean_value( (string) ( $row[ $index ] ?? '' ) );
			}
		}

		foreach ( $required as $key => $label ) {
			if ( '' === ( $values[ $key ] ?? '' ) ) {
				$missing[ $key ] = $label;
			}
		}

		if ( $missing ) {
			$fail( sprintf( '%s boş. %s zorunlu; bu satırda doldurun.', '“' . implode( '”, “', $missing ) . '”', count( $missing ) > 1 ? 'Bu başlıklar' : 'Bu başlık' ), $missing );
			continue;
		}

		// SİTE: bos ya da sutun yok = degismez (null).
		$sites = null === $cell( 'site' ) ? null : nwcs_sync_parse_sites( (string) $cell( 'site' ) );

		if ( is_wp_error( $sites ) ) {
			$fail( $sites->get_error_message() );
			continue;
		}

		// Aciklama metni (sutun yoksa null). Ozel icerik isareti yalnizca indirilen
		// urunun kendi satirinda anlamli: kopyalanan satirda metin diye yazilirdi.
		$body_text = $cell( 'body' );

		if ( NWCS_DESC_MARKER === $body_text && ( ! $post || ! nwcs_desc_is_complex( $post->post_content ) ) ) {
			$fail( sprintf( 'ÜRÜN AÇIKLAMASI hücresinde “%s” yazıyor. Bu yazı yalnızca özel içerikli ürünün kendi satırında kalabilir. Satırı kopyaladıysanız bu ürünün açıklamasını yazın ya da hücreyi boşaltın.', NWCS_DESC_MARKER ) );
			continue;
		}

		$data = array(
			'title'      => $title,
			'code'       => $code,
			'price'      => null,
			'short'      => null === $cell( 'short' ) ? null : nwcs_clean_text( (string) $cell( 'short' ), true ),
			'meta_title' => null === $cell( 'meta_title' ) ? null : nwcs_clean_text( (string) $cell( 'meta_title' ) ),
			'meta_desc'  => null === $cell( 'meta_desc' ) ? null : nwcs_clean_text( (string) $cell( 'meta_desc' ) ),
			// Yazilacak aciklama HTML'i; null = dokunulmaz (sutun yok ya da metin ayni).
			'body'       => null,
			'details'    => $values,
			'sites'      => $sites,
		);

		foreach ( $columns as $index => $column ) {
			if ( 'price' === $column['type'] ) {
				$raw_price     = nwcs_clean_text( (string) ( $row[ $index ] ?? '' ) );
				$data['price'] = nwcs_sync_price( $raw_price, ! empty( $numeric[ $offset + 1 ][ $index ] ) );

				// Hucre urundeki fiyatla birebir aynisiysa dokunulmaz (eski "3500" gibi).
				if ( $post && $raw_price === (string) get_post_meta( $post->ID, '_nwcs_price', true ) ) {
					$data['price'] = $raw_price;
				}
			}
		}

		if ( ! $post && $quick ) {
			$fail( 'Bu satırdaki ürün bulunamadı. Hızlı Excel’le yeni ürün eklenemez; yeni ürünleri kategorinin Excel’iyle ekleyin.' );
			continue;
		}

		if ( ! $post ) {
			$data['body'] = null === $body_text || '' === $body_text ? '' : nwcs_desc_text_to_html( $body_text );

			foreach ( array_keys( NWCS_SYNC_META_LIMITS ) as $field ) {
				if ( null !== $data[ $field ] ) {
					$check_long( $field, $data[ $field ], $title );
				}
			}

			// Yeni urunun adresi: hucrede tek adres varsa o, bossa addan uretilir.
			// Baska urunun adresi yaziliysa (kopyalanan satir) yok sayilir.
			$data['post_name'] = '';
			$addr_lines        = nwcs_sync_address_lines( (string) $cell( 'address' ) );
			$wanted            = array_values( array_unique( array_filter( array_column( $addr_lines, 'slug' ) ) ) );

			if ( in_array( true, array_column( $addr_lines, 'unknown' ), true ) ) {
				$fail( 'SAYFA ADRESİ hücresindeki adresin hangi siteye ait olduğu anlaşılamadı. Yeni ürün için yalnızca adresi yazın (örneğin ahsap-cardak-2x2) ya da hücreyi boş bırakın; o zaman adres ürün adından üretilir.' );
				continue;
			}

			if ( count( $wanted ) > 1 ) {
				$fail( 'SAYFA ADRESİ hücresinde birden çok adres var. Yeni ürün için tek adres yazın ya da hücreyi boş bırakın (adres ürün adından üretilir).' );
				continue;
			}

			if ( $wanted && isset( $known_slugs()[ $wanted[0] ] ) ) {
				$plan['notes'][] = sprintf( '%d. satır (%s): SAYFA ADRESİ hücresindeki adres başka bir ürünün şu anki adresi; yeni ürünün adresi ürün adından üretilecek. Başka bir adres istiyorsanız hücreye yeni bir adres yazın.', $line, $title );
			} elseif ( $wanted ) {
				$data['post_name'] = $wanted[0];
				$addr_new[]        = array( 'line' => $line, 'title' => $title, 'slug' => $wanted[0], 'sites' => $sites );
			}

			$plan['create'][] = array( 'line' => $line, 'data' => $data );

			if ( null !== $sites ) {
				$site_rows[] = $sites;
			}

			continue;
		}

		/* Mevcut urunle karsilastir. */
		$details = nwcs_product_details( (int) $post->ID );
		$changes = array();
		$current = array(
			'title' => $post->post_title,
			'code'  => (string) get_post_meta( $post->ID, '_nwcs_code', true ),
			'price' => (string) get_post_meta( $post->ID, '_nwcs_price', true ),
			'short' => (string) get_post_meta( $post->ID, '_nwcs_short', true ),
		);

		foreach ( array( 'title' => 'Ürün adı', 'code' => 'Ürün kodu', 'price' => 'Fiyat', 'short' => 'Kısa açıklama' ) as $field => $label ) {
			// Bos kod degismez: urunun kodu kalir (her urunun kodu olur).
			if ( null === $data[ $field ] || ( 'code' === $field && '' === $data[ $field ] ) ) {
				continue;
			}

			if ( $data[ $field ] !== $current[ $field ] ) {
				$changes[] = array( $label, $current[ $field ], $data[ $field ], $field );
			}
		}

		foreach ( array( 'meta_title' => array( '_nwcs_meta_title', 'Meta başlık' ), 'meta_desc' => array( '_nwcs_meta_desc', 'Meta açıklama' ) ) as $field => $meta ) {
			$old = (string) get_post_meta( $post->ID, $meta[0], true );

			if ( null !== $data[ $field ] && $data[ $field ] !== $old ) {
				$changes[] = array( $meta[1], $old, $data[ $field ], $field );
				$check_long( $field, $data[ $field ], $post->post_title );
			}
		}

		// Aciklama metin olarak karsilastirilir (Ürün açıklamaları dosyasiyla ayni
		// kural): degismeyen hucre urune dokunmaz, bicimi korunur.
		if ( null !== $body_text ) {
			$old_text = nwcs_desc_html_to_text( $post->post_content );

			if ( nwcs_desc_fold( $body_text ) !== nwcs_desc_fold( $old_text ) ) {
				$data['body'] = nwcs_desc_text_to_html( $body_text, nwcs_desc_split( $post->post_content )['tables'] );
				$excerpts     = nwcs_desc_diff_excerpts( $old_text, $body_text );
				$changes[]    = array( 'Ürün açıklaması', $excerpts[0], $excerpts[1], 'body' );

				if ( '' === $body_text ) {
					$plan['cleared'][] = $post->post_title;
				}

				if ( NWCS_DESC_MARKER === $old_text ) {
					$plan['notes'][] = sprintf( '“%s” ürününün açıklamasındaki özel içerik (görsel, bağlantı) silinecek; yerine Excel’deki metin gelecek.', $post->post_title );
				}
			}
		}

		// Sayfa adresi: bos hucre ya da sutun yoksa degismez (SİTE gibi istisna:
		// bos hucre ozel adresi otomatige dondurseydi baglanti fark edilmeden degisirdi).
		$addr_cell = (string) $cell( 'address' );

		if ( '' !== $addr_cell && 'trash' === $post->post_status ) {
			$plan['notes'][] = sprintf( '“%s” çöp kutusundan geri gelecek; SAYFA ADRESİ bu yüklemede kullanılmadı. Ürün geri geldikten sonra adresi değiştirebilirsiniz.', $post->post_title );
		} elseif ( '' !== $addr_cell ) {
			$address = nwcs_sync_plan_address( (int) $post->ID, $post->post_title, $addr_cell, null !== $sites ? $sites : nwcs_sync_product_sites( (int) $post->ID ) );

			if ( is_string( $address ) ) {
				$fail( $address );
				continue;
			}

			foreach ( $address['changes'] as $blog_id => $pair ) {
				$changes[]      = array( sprintf( 'Sayfa adresi (%s)', nwcs_sync_sites()[ $blog_id ] ?? $blog_id ), '/urun/' . $pair[0] . '/', '/urun/' . $pair[1] . '/', 'address' );
				$addr_pending[] = array( 'id' => (int) $post->ID, 'blog' => (int) $blog_id, 'from' => $pair[0], 'to' => $pair[1], 'title' => $post->post_title, 'line' => $line );
			}

			$data['addresses'] = array_map( static fn( array $pair ): string => $pair[1], $address['changes'] );
			$plan['notes']     = array_merge( $plan['notes'], $address['notes'] );
		}

		$details_changed = false;

		foreach ( $values as $key => $value ) {
			$old = nwcs_details_value( $details, $key );

			if ( $old !== $value ) {
				$label           = $headings[ $key ]['label'] ?? $new_headings[ $key ] ?? $key;
				$changes[]       = array( $label, $old, $value, 'detail' );
				$details_changed = true;
			}
		}

		$data['details_changed'] = $details_changed;

		// Site: yalnizca gorundugu siteler degisecekse.
		$now_sites = nwcs_sync_product_sites( (int) $post->ID );
		$moves     = '';

		if ( null !== $sites && $sites !== $now_sites ) {
			$changes[]   = array( 'Site', '' !== nwcs_sync_sites_text( $now_sites ) ? nwcs_sync_sites_text( $now_sites ) : 'hiçbir sitede', nwcs_sync_sites_text( $sites ), 'site' );
			$moves       = nwcs_sync_site_moves( $now_sites, $sites );
			$site_rows[] = $sites;
		} elseif ( null === $sites && $site_column && ! $now_sites ) {
			$nowhere[] = $post->post_title;
		}

		$item = array(
			'id'       => (int) $post->ID,
			'line'     => $line,
			'title'    => $post->post_title,
			'modified' => $post->post_modified_gmt,
			'restore'  => 'trash' === $post->post_status,
			'changes'  => $changes,
			'data'     => $data,
			'sites'    => nwcs_product_site_labels( (int) $post->ID ),
			'moves'    => $moves,
		);

		if ( $item['restore'] ) {
			$plan['restored'][] = $post->post_title;
		}

		if ( $downloaded && strtotime( $post->post_modified_gmt . ' UTC' ) > $downloaded + 5 && $changes ) {
			$plan['stale'][] = $post->post_title;
		}

		if ( $changes || $item['restore'] ) {
			$plan['update'][] = $item;
		} else {
			$plan['same'][] = array( 'id' => (int) $post->ID, 'title' => $post->post_title );
		}
	}

	/* ---------- Sayfa adresleri: cakisma, dosya icinde tekrar, takas ---------- */
	nwcs_sync_check_addresses( $plan, $addr_pending, $addr_new );

	/* ---------- Cop kutusuna gidecekler (hizli dosyada yok: satir silmek urune dokunmaz) ---------- */
	foreach ( $quick ? array() : nwcs_sync_category_products( $slug ) as $id => $product ) {
		if ( isset( $matched[ $id ] ) ) {
			continue;
		}

		$others = array_values( array_diff_key( (array) $product['categories'], array( $slug => true ) ) );

		$plan['trash'][] = array(
			'id'       => (int) $id,
			'title'    => (string) $product['title'],
			'code'     => (string) $product['code'],
			'others'   => $others,
			'sites'    => nwcs_product_site_labels( (int) $id ),
			'modified' => (string) get_post_field( 'post_modified_gmt', (int) $id ),
		);
	}

	restore_current_blog();

	/* ---------- Paylasilan kategori: urunleri birden cok sitede ---------- */
	$category_sites = array();

	foreach ( array_keys( nwcs_sync_category_products( $slug ) ) as $id ) {
		foreach ( nwcs_product_site_labels( (int) $id ) as $label ) {
			$category_sites[ $label ] = true;
		}
	}

	$plan['shared'] = count( $category_sites ) > 1 ? array_keys( $category_sites ) : array();

	/* ---------- Yeni urunlerin gorunecegi siteler: kategorinin yerlesimi ---------- */
	$plan['sites'] = nwcs_placement_labels( $slug );

	/* ---------- SİTE sutunu notlari ---------- */
	$all_sites = nwcs_sync_sites();
	$unplaced  = array();

	// Tum urunler dosyasinda tek bir kategori yerlesimi yok; not anlamsiz olurdu.
	foreach ( $all ? array() : $site_rows as $row_sites ) {
		foreach ( $row_sites as $blog_id ) {
			if ( ! isset( $plan['sites'][ $blog_id ] ) && isset( $all_sites[ $blog_id ] ) ) {
				$unplaced[ $blog_id ] = $all_sites[ $blog_id ];
			}
		}
	}

	if ( $unplaced ) {
		$plan['notes'][] = sprintf(
			'SİTE sütununa yazdığınız %s bu kategori yerleştirilmemiş: ürün o sitede listelenir ama kategori sayfasında görünmez. Kategoriyi bu sayfadaki “Sitelerde” kutusundan o siteye yerleştirin.',
			nwcs_join_and( array_map( 'nwcs_dative', array_values( $unplaced ) ) )
		);
	}

	if ( $nowhere ) {
		$plan['notes'][] = sprintf(
			'%d ürünün SİTE hücresi boş ve bu ürünler hiçbir sitede görünmüyor: %s. Göstermek için SİTE hücresine site adını ya da Hepsi yazın.',
			count( $nowhere ),
			implode( ', ', array_slice( $nowhere, 0, 10 ) ) . ( count( $nowhere ) > 10 ? '…' : '' )
		);
	}

	return $plan;
}

/* ====================================================================== *
 * Uygulama
 * ====================================================================== */

/**
 * Urunun geri alma icin gereken hali. Havuz baglaminda cagrilir.
 */
function nwcs_import_snapshot( int $id ): array {
	$post = get_post( $id );

	if ( ! $post ) {
		return array();
	}

	$details = get_post_meta( $id, NWCS_DETAILS_META, true );

	return array(
		'title'      => $post->post_title,
		'content'    => $post->post_content,
		'name'       => $post->post_name,
		'status'     => $post->post_status,
		'code'       => (string) get_post_meta( $id, '_nwcs_code', true ),
		'short'      => (string) get_post_meta( $id, '_nwcs_short', true ),
		'price'      => (string) get_post_meta( $id, '_nwcs_price', true ),
		'meta_title' => (string) get_post_meta( $id, '_nwcs_meta_title', true ),
		'meta_desc'  => (string) get_post_meta( $id, '_nwcs_meta_desc', true ),
		'spec'       => (string) get_post_meta( $id, '_nwcs_spec', true ),
		// Kayit yoksa null: geri alirken meta silinir (tembel okuma yeniden spec'ten).
		'details'    => is_array( $details ) ? $details : null,
		'categories' => wp_get_object_terms( $id, NWCS_PRODUCT_TAX, array( 'fields' => 'ids' ) ),
		// Geri almada degisiklik zamani da eski haline doner (bkz. nwcs_restore_post_modified).
		'modified'     => $post->post_modified,
		'modified_gmt' => $post->post_modified_gmt,
	);
}

/**
 * Geri alinan urunun degisiklik zamanini islemden onceki haline koyar.
 *
 * Geri alma yiginda sirayla ilerler: ustteki islem geri alinirken urun
 * yeniden yazilir ve zamani "simdi" olurdu; alttaki islemin "islemden sonra
 * elle duzenlendi" denetimi bunu elle duzenleme sanip urunu atlardi. Zaman
 * geri konunca yalnizca gercekten sonradan yapilan duzenleme yakalanir.
 * Havuz baglaminda cagrilir.
 */
function nwcs_restore_post_modified( int $id, string $local, string $gmt ): void {
	global $wpdb;

	if ( '' === $local || '' === $gmt ) {
		return;
	}

	$wpdb->update( $wpdb->posts, array( 'post_modified' => $local, 'post_modified_gmt' => $gmt ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $id );
}

/**
 * Cope atilmis urunu onceki durumuyla geri getirir (WordPress varsayilani
 * "taslak"; urun sitelerden kaybolurdu).
 */
function nwcs_untrash_product( int $id ): void {
	add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
	wp_untrash_post( $id );
	remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );

	if ( 'publish' !== get_post_status( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}
}

/**
 * Dosyadaki detay degerlerini urune uygular. Sonuc sirasi: once dosyanin
 * sutun sirasiyla dosyadaki dolu basliklar, sonra dosyada olmayan (dokunulmayan)
 * basliklar kendi sirasiyla. Bos hucre basligi urunden cikarir. Boylece Excel'den
 * yazilan urunler ayni kategoride ayni sirayla gorunur; hic detayi degismeyen
 * urune dokunulmaz (bkz. nwcs_sync_apply).
 *
 * @param array<string, string> $values baslik anahtari => deger, sutun sirasiyla
 */
function nwcs_sync_merge_details( array $details, array $values, array $labels ): array {
	$current = array();

	foreach ( $details as $row ) {
		$current[ nwcs_heading_key( (string) $row['label'] ) ] = (string) $row['label'];
	}

	$out = array();

	foreach ( $values as $key => $value ) {
		if ( '' !== $value ) {
			$out[] = array( 'label' => $current[ $key ] ?? $labels[ $key ] ?? $key, 'value' => $value );
		}
	}

	foreach ( $details as $row ) {
		if ( ! array_key_exists( nwcs_heading_key( (string) $row['label'] ), $values ) ) {
			$out[] = $row;
		}
	}

	return $out;
}

/**
 * Cop kutusu acik mi? EMPTY_TRASH_DAYS = 0 ise WordPress cope atmak yerine
 * kalici siler; o durumda cope atma islemleri yapilmaz.
 */
function nwcs_trash_enabled(): bool {
	return ! defined( 'EMPTY_TRASH_DAYS' ) || EMPTY_TRASH_DAYS > 0;
}

/**
 * Plani uygular. Geri alma kaydi en basta (bos) yazilir ve her urunden sonra
 * guncellenir: istek yarida kesilse de o ana kadar yapilanlar geri alinabilir
 * ('complete' false kalir, serit bunu soyler).
 *
 * @param bool $trash Dosyada olmayan urunler cope atilsin mi (onay kutusu).
 * @return array Rapor.
 */
function nwcs_sync_apply( array $plan, bool $trash ): array {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$record = array(
		'id'       => bin2hex( random_bytes( 6 ) ),
		'kind'     => NWCS_SYNC_KIND,
		'slug'     => (string) $plan['slug'],
		'file'     => (string) $plan['file'],
		// "Son işlemler"de Hizli Excel ayirt edilsin.
		'category' => (string) $plan['term_name'] . ( ! empty( $plan['quick'] ) ? ' (Hızlı Excel)' : '' ),
		'quick'    => ! empty( $plan['quick'] ),
		'user'     => get_current_user_id(),
		'complete' => false,
		'created'  => array(),
		'updated'  => array(),
		'trashed'  => array(),
		'headings' => array(),
		'sites'    => array(),
		'skipped'  => array(),
		// Sayfa adresleri: blog => [urun => before (slug, slug_old), after]; alinamayanlar.
		'slugs'          => array(),
		'address_failed' => array(),
	);

	// Her yazimdan sonra: zaman (geri almada "sonradan duzenlendi mi" siniri) ve sayilar.
	$save = static function () use ( &$record, $plan ): void {
		$record['time']   = time();
		$record['counts'] = array(
			'created' => count( $record['created'] ),
			'updated' => count( $record['updated'] ),
			'trashed' => count( $record['trashed'] ),
			'skipped' => count( $record['skipped'] ),
			'errors'  => count( (array) $plan['errors'] ),
			// SİTE sutunuyla sitesi degisen urunler.
			'sites'   => count( array_unique( array_merge( array(), ...array_map( 'array_keys', array_values( (array) ( $record['visibility'] ?? array() ) ) ) ) ) ),
			// Sayfa adresi degisen (urun, site) ciftleri.
			'addresses' => array_sum( array_map( 'count', (array) ( $record['slugs'] ?? array() ) ) ),
		);

		nwcs_history_save( $record );
	};

	nwcs_history_push( $record + array( 'time' => time() ) );
	$save();
	// Eski sihirbazin geri alma kaydi artik gecersiz.
	delete_site_option( 'nwcs_import_last' );

	// Yeni basliklar (en az bir hucresi dolu sutunlar).
	foreach ( (array) $plan['headings'] as $label ) {
		$created = false;
		$key     = nwcs_heading_ensure( (string) $label, $created );

		if ( $created ) {
			$record['headings'][] = $key;
		}
	}

	$save();

	$headings = nwcs_product_headings();
	$labels   = array_map( static fn( array $row ): string => $row['label'], $headings );

	switch_to_blog( nwcs_pool_blog_id() );

	$returned  = array(); // cop kutusundan geri gelenler (gorunurlukte yeni urun gibi)
	$addr_jobs = array(); // sayfa adresi degisiklikleri: id, blog, to, title
	$visible  = array(); // SİTE sutunu: urun => istenen siteler (en sonda, toplu yazilir)

	/* Guncellemeler. */
	foreach ( (array) $plan['update'] as $item ) {
		$id   = (int) $item['id'];
		$post = nwcs_sync_product_post( $id );

		// Onizlemeden sonra degisen urun atlanir: baskasinin duzenlemesi ezilmesin.
		if ( ! $post || $post->post_modified_gmt !== $item['modified'] ) {
			$record['skipped'][] = $item['title'];
			continue;
		}

		// Kayit, yazmadan once: yarida kesilirse bu urun de geri alinabilsin.
		$record['updated'][ $id ] = nwcs_import_snapshot( $id );
		$save();

		$data = $item['data'];

		if ( 'trash' === $post->post_status ) {
			nwcs_untrash_product( $id );
			$returned[] = $id;
		}

		// Adres (post_name) degismez: baglantilar ve arama motoru kaydi korunur.
		$postarr = array( 'ID' => $id, 'post_title' => wp_slash( $data['title'] ) );

		if ( null !== ( $data['body'] ?? null ) ) {
			$postarr['post_content'] = wp_slash( $data['body'] );
		}

		wp_update_post( $postarr );

		if ( null !== $data['code'] && '' !== $data['code'] ) {
			update_post_meta( $id, '_nwcs_code', $data['code'] );
		}

		if ( null !== $data['price'] ) {
			update_post_meta( $id, '_nwcs_price', wp_slash( $data['price'] ) );
		}

		if ( null !== $data['short'] ) {
			update_post_meta( $id, '_nwcs_short', wp_slash( $data['short'] ) );
		}

		// Meta alanlari: sutun yoksa (eski taslak) null, dokunulmaz.
		if ( null !== ( $data['meta_title'] ?? null ) ) {
			nwcs_product_set_meta_text( $id, '_nwcs_meta_title', $data['meta_title'] );
		}

		if ( null !== ( $data['meta_desc'] ?? null ) ) {
			nwcs_product_set_meta_text( $id, '_nwcs_meta_desc', $data['meta_desc'] );
		}

		// Detaylar yalnizca bir degeri degistiyse yazilir; aksi halde urunun sirasi aynen kalir.
		if ( ! empty( $data['details_changed'] ) ) {
			nwcs_product_write_details( $id, nwcs_sync_merge_details( nwcs_product_details( $id ), (array) $data['details'], $labels ) );
		}

		if ( is_array( $data['sites'] ?? null ) ) {
			$visible[ $id ] = $data['sites'];
		}

		// Adresler en sonda, hepsi birlikte (takas/zincir sirasi).
		foreach ( (array) ( $data['addresses'] ?? array() ) as $blog_id => $to ) {
			$addr_jobs[] = array( 'id' => $id, 'blog' => (int) $blog_id, 'to' => (string) $to, 'title' => (string) $item['title'] );
		}

		$save();
	}

	/* Yeni urunler: yalnizca indirilen kategoriye (sitelerde yerini kategorinin yerlesimi belirler). */
	$term_ids = array( (int) $plan['term_id'] );

	foreach ( (array) $plan['create'] as $item ) {
		$data = $item['data'];

		// Kod plan sirasinda serbestti; arada baska urune verildiyse urun eklenmez
		// (otomatik kod verilmez, kod zorunlu).
		if ( '' === (string) $data['code'] || nwcs_product_id_by_code( (string) $data['code'] ) ) {
			$record['skipped'][] = $data['title'];
			continue;
		}

		$id   = (int) wp_insert_post(
			array(
				'post_type'    => NWCS_PRODUCT_TYPE,
				'post_status'  => 'publish',
				'post_title'   => wp_slash( $data['title'] ),
				'post_name'    => '' !== (string) ( $data['post_name'] ?? '' ) ? $data['post_name'] : sanitize_title( $data['title'] ),
				'post_content' => wp_slash( (string) ( $data['body'] ?? '' ) ),
			)
		);

		if ( ! $id ) {
			$record['skipped'][] = $data['title'];
			continue;
		}

		// Plan denetiminden sonra adres alindiysa WordPress sessizce "-2" ekler.
		if ( '' !== (string) ( $data['post_name'] ?? '' ) && get_post_field( 'post_name', $id ) !== $data['post_name'] ) {
			$record['address_failed'][] = sprintf( '%s: “%s” adresi alınamadı, “%s” verildi.', $data['title'], $data['post_name'], get_post_field( 'post_name', $id ) );
		}

		$record['created'][] = $id;
		$save();

		update_post_meta( $id, '_nwcs_code', $data['code'] );

		update_post_meta( $id, '_nwcs_price', wp_slash( (string) $data['price'] ) );
		update_post_meta( $id, '_nwcs_short', wp_slash( (string) $data['short'] ) );
		nwcs_product_set_meta_text( $id, '_nwcs_meta_title', (string) ( $data['meta_title'] ?? '' ) );
		nwcs_product_set_meta_text( $id, '_nwcs_meta_desc', (string) ( $data['meta_desc'] ?? '' ) );
		nwcs_product_write_details( $id, nwcs_sync_merge_details( array(), (array) $data['details'], $labels ) );
		wp_set_object_terms( $id, $term_ids, NWCS_PRODUCT_TAX, false );

		if ( is_array( $data['sites'] ?? null ) ) {
			$visible[ $id ] = $data['sites'];
		}
	}

	/* Dosyada olmayanlar: cop kutusuna (site secimleri korunur; geri gelince yerine doner). */
	if ( $trash && nwcs_trash_enabled() ) {
		foreach ( (array) $plan['trash'] as $item ) {
			$id   = (int) $item['id'];
			$post = nwcs_sync_product_post( $id );

			// Onizlemeden sonra duzenlenen ya da kategoriden cikan urun cope gitmez.
			if ( ! $post || 'publish' !== $post->post_status || ! has_term( (int) $plan['term_id'], NWCS_PRODUCT_TAX, $id ) || ( isset( $item['modified'] ) && $post->post_modified_gmt !== $item['modified'] ) ) {
				$record['skipped'][] = $item['title'];
				continue;
			}

			wp_trash_post( $id );
			$record['trashed'][] = $id;
			$save();
		}
	}

	restore_current_blog();

	/* Sayfa adresleri: site basina ozel adres (nwcs_override_set_slug: cakisma
	 * denetimi, eski adres -> 301). Zincirde (A b'ye, B c'ye) once B tasinmali:
	 * ilerleme kalmayana kadar yinelenir; kalan alinamayan adres olarak yazilir. */
	if ( $addr_jobs ) {
		nwcs_pool_flush_cache();
		$addr_errors = array();

		do {
			$progress = false;

			foreach ( $addr_jobs as $index => $job ) {
				$row = nwcs_site_product_settings( $job['blog'] )['overrides'][ $job['id'] ] ?? array();
				$row = is_array( $row ) ? $row : array();

				// Geri alma icin onceki hal, ilk denemede ve yazmadan once.
				if ( ! isset( $record['slugs'][ $job['blog'] ][ $job['id'] ] ) ) {
					$record['slugs'][ $job['blog'] ][ $job['id'] ] = array(
						'title'  => $job['title'],
						'before' => array( 'slug' => nwcs_override_slug( $row ), 'slug_old' => nwcs_override_slug_old( $row ) ),
						'after'  => $job['to'] !== nwcs_override_pool_slug( $job['id'] ) ? $job['to'] : '',
					);
					$save();
				}

				$result = nwcs_override_set_slug( $job['blog'], $job['id'], $job['to'] );

				if ( $result['ok'] ) {
					unset( $addr_jobs[ $index ], $addr_errors[ $index ] );
					$progress = true;
				} else {
					$addr_errors[ $index ] = $result['message'];
				}
			}
		} while ( $progress && $addr_jobs );

		foreach ( $addr_jobs as $index => $job ) {
			unset( $record['slugs'][ $job['blog'] ][ $job['id'] ] );
			$record['address_failed'][] = sprintf( '%s (%s): %s', $job['title'], nwcs_sync_sites()[ $job['blog'] ] ?? $job['blog'], $addr_errors[ $index ] ?? '' );
		}

		$record['slugs'] = array_filter( $record['slugs'] );
		$save();
	}

	/* Yeni urunler, kategorinin yerlestigi sitelerin seciminde de ("secilenler" kipi). */
	// Cop kutusundan geri gelen urun de, sitenin seciminde yoksa, yeni urun gibi eklenir.
	if ( $record['created'] || $returned ) {
		$record['sites'] = nwcs_placement_reveal( array_merge( $record['created'], $returned ), array( (string) $plan['slug'] ) );
		$save();
	}

	/* SİTE sutunu: kategorinin yerlesiminden sonra (yeni urunde onu duzeltir). Site basina toplu. */
	if ( $visible ) {
		$record['visibility'] = nwcs_sync_apply_sites( $visible );
		$save();
	}

	$record['complete'] = true;
	$save();

	nwcs_pool_flush_cache();

	return $record;
}

/* ====================================================================== *
 * Geri alma ("Son işlemler" yigininin en ustundeyse)
 * ====================================================================== */

/**
 * En yeni urun Excel'i kaydi (uygulama ozeti icin).
 */
function nwcs_sync_last(): ?array {
	return nwcs_history_latest( NWCS_SYNC_KIND );
}

/**
 * Bir urun Excel'i yuklemesini geri alir (yiginin en ustundeki kayit):
 *   - yeni urunler cop kutusuna, sitelere eklenmisse secimden cikar;
 *   - guncellenenler eski haline doner (cöpten gelmisse yeniden cope);
 *   - cope gidenler geri gelir;
 *   - bu yuklemede dogan basliklar, kullanilmiyorsa kayittan silinir.
 * Yuklemeden sonra elle duzenlenen urune dokunulmaz, raporlanir.
 *
 * @return array{removed:int, restored:int, untrashed:int, headings:int, kept:string[]}|WP_Error
 */
function nwcs_sync_undo( array $record ) {
	$time   = (int) ( $record['time'] ?? 0 );
	$report = array( 'removed' => 0, 'restored' => 0, 'untrashed' => 0, 'headings' => 0, 'addresses' => 0, 'kept' => array() );
	$after  = static fn( WP_Post $post ): bool => strtotime( $post->post_modified_gmt . ' UTC' ) > $time + 5;
	$undone = array(); // bu geri almada gercekten eski haline donen urunler

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( (array) ( $record['created'] ?? array() ) as $id ) {
		$post = nwcs_sync_product_post( (int) $id );

		if ( ! $post || 'trash' === $post->post_status ) {
			continue;
		}

		if ( $after( $post ) ) {
			$report['kept'][] = $post->post_title;
			continue;
		}

		wp_trash_post( (int) $id );
		$undone[] = (int) $id;
		++$report['removed'];
	}

	foreach ( (array) ( $record['updated'] ?? array() ) as $id => $snapshot ) {
		$id   = (int) $id;
		$post = nwcs_sync_product_post( $id );

		if ( ! $post || ! is_array( $snapshot ) || ! $snapshot ) {
			continue;
		}

		if ( $after( $post ) ) {
			$report['kept'][] = $post->post_title;
			continue;
		}

		// Adres (post_name) yazilmaz: yukleme onu hic degistirmez. Cop kutusundan
		// gelen urunun kayittaki adi "..__trashed" olur; yazilsaydi yayindaki
		// urunun adresi bozulurdu. Cop/geri getirme adresi WordPress kendisi tasir.
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => wp_slash( (string) $snapshot['title'] ),
				'post_content' => wp_slash( (string) $snapshot['content'] ),
			)
		);

		update_post_meta( $id, '_nwcs_code', (string) $snapshot['code'] );
		update_post_meta( $id, '_nwcs_short', wp_slash( (string) $snapshot['short'] ) );
		update_post_meta( $id, '_nwcs_price', wp_slash( (string) $snapshot['price'] ) );

		// 0.26.0 oncesi kayitlarda meta alanlari yok: o zaman dokunulmaz.
		foreach ( array( 'meta_title' => '_nwcs_meta_title', 'meta_desc' => '_nwcs_meta_desc' ) as $field => $meta_key ) {
			if ( array_key_exists( $field, $snapshot ) ) {
				nwcs_product_set_meta_text( $id, $meta_key, (string) $snapshot[ $field ] );
			}
		}

		// Detay ve spec birlikte, kayittaki haliyle (ikisi zaten tutarliydi).
		update_post_meta( $id, '_nwcs_spec', wp_slash( (string) $snapshot['spec'] ) );

		if ( is_array( $snapshot['details'] ?? null ) ) {
			update_post_meta( $id, NWCS_DETAILS_META, wp_slash( $snapshot['details'] ) );
		} else {
			delete_post_meta( $id, NWCS_DETAILS_META );
		}

		if ( 'trash' === ( $snapshot['status'] ?? '' ) ) {
			wp_trash_post( $id );
		}

		nwcs_restore_post_modified( $id, (string) ( $snapshot['modified'] ?? '' ), (string) ( $snapshot['modified_gmt'] ?? '' ) );

		$undone[] = $id;
		++$report['restored'];
	}

	foreach ( (array) ( $record['trashed'] ?? array() ) as $id ) {
		if ( 'trash' === get_post_status( (int) $id ) ) {
			nwcs_untrash_product( (int) $id );
			++$report['untrashed'];
		}
	}

	restore_current_blog();

	// Sitelere eklenenler: yalnizca geri alinabilenler secimden cikar (elle duzenlenip
	// dokunulmayan urun sitede kalir).
	foreach ( (array) ( $record['sites'] ?? array() ) as $blog_id => $ids ) {
		$ids = array_intersect( array_map( 'intval', (array) $ids ), $undone );

		switch_to_blog( (int) $blog_id );
		$selected = nwcs_site_product_settings()['selected'];
		update_option( NWCS_OPTION_SELECTED, array_values( array_diff( $selected, array_map( 'intval', (array) $ids ) ) ) );
		restore_current_blog();
	}

	// SİTE sutunuyla degisen gorunurluk (yalnizca geri alinabilen urunlerde) eski haline.
	if ( ! empty( $record['visibility'] ) ) {
		nwcs_sync_undo_sites( (array) $record['visibility'], $undone );
	}

	// Sayfa adresleri: urunun degisiklik zamanindan bagimsiz (adres yazimi onu
	// degistirmez). Simdiki ozel adres uygulanan adresse onceki hal geri yazilir;
	// uygulanan adres eski adreslere girer (arada paylasilan baglanti yonlenir).
	// Panelden sonradan degistirilmisse dokunulmaz.
	foreach ( (array) ( $record['slugs'] ?? array() ) as $blog_id => $rows ) {
		$overrides = get_blog_option( (int) $blog_id, NWCS_OPTION_OVERRIDES, array() );
		$overrides = is_array( $overrides ) ? $overrides : array();
		$changed   = false;
		$site_name = nwcs_sync_sites()[ (int) $blog_id ] ?? $blog_id;
		$restore   = array();

		foreach ( (array) $rows as $id => $entry ) {
			$row = is_array( $overrides[ $id ] ?? null ) ? $overrides[ $id ] : array();

			if ( nwcs_override_slug( $row ) !== (string) ( $entry['after'] ?? '' ) ) {
				$report['kept'][] = sprintf( '%s (%s adresi)', (string) ( $entry['title'] ?? $id ), $site_name );
				continue;
			}

			$restore[ $id ] = $entry;
		}

		// Geri alinmayan urunlerin simdiki ozel adresleri dolu; bu turda geri
		// alinanlarinki bos sayilir (zincir birlikte geri doner).
		$taken = array();

		foreach ( $overrides as $id => $row ) {
			$custom = ! isset( $restore[ $id ] ) && is_array( $row ) ? nwcs_override_slug( $row ) : '';

			if ( '' !== $custom ) {
				$taken[ $custom ] = true;
			}
		}

		foreach ( $restore as $id => $entry ) {
			$row    = is_array( $overrides[ $id ] ?? null ) ? $overrides[ $id ] : array();
			$before = (string) ( $entry['before']['slug'] ?? '' );

			if ( '' !== $before && isset( $taken[ $before ] ) ) {
				$report['kept'][] = sprintf( '%s (%s adresi; önceki adresi artık başka bir üründe)', (string) ( $entry['title'] ?? $id ), $site_name );
				continue;
			}

			$old = array_map( 'strval', (array) ( $entry['before']['slug_old'] ?? array() ) );

			if ( '' !== (string) $entry['after'] ) {
				$old[] = (string) $entry['after'];
			}

			$row['slug']     = $before;
			$row['slug_old'] = array_slice( array_values( array_diff( array_unique( $old ), array( $before, nwcs_override_pool_slug( (int) $id ) ) ) ), -NWCS_SLUG_OLD_MAX );
			$row             = array_filter( $row, static fn( $v ): bool => '' !== $v && array() !== $v && null !== $v );

			if ( $row ) {
				$overrides[ $id ] = $row;
			} else {
				unset( $overrides[ $id ] );
			}

			$changed = true;
			++$report['addresses'];
		}

		// Zincir geri alininca (A b'ye donup B'nin aldigi a'yi geri alir) bir urunun
		// eski adresleri baska urunun guncel adresini tutmasin: o adres ona aittir.
		if ( $changed ) {
			$current = array();

			foreach ( $overrides as $id => $row ) {
				$custom = is_array( $row ) ? nwcs_override_slug( $row ) : '';

				if ( '' !== $custom ) {
					$current[ $custom ] = (int) $id;
				}
			}

			foreach ( array_keys( (array) $rows ) as $id ) {
				if ( empty( $overrides[ $id ]['slug_old'] ) ) {
					continue;
				}

				$overrides[ $id ]['slug_old'] = array_values( array_filter( (array) $overrides[ $id ]['slug_old'], static fn( $slug ): bool => ! isset( $current[ $slug ] ) || $current[ $slug ] === (int) $id ) );

				if ( ! $overrides[ $id ]['slug_old'] ) {
					unset( $overrides[ $id ]['slug_old'] );
				}

				if ( ! $overrides[ $id ] ) {
					unset( $overrides[ $id ] );
				}
			}

			update_blog_option( (int) $blog_id, NWCS_OPTION_OVERRIDES, $overrides );
		}
	}

	nwcs_pool_flush_cache();

	if ( ! empty( $record['headings'] ) ) {
		$usage    = nwcs_heading_usage();
		$headings = nwcs_product_headings();

		foreach ( (array) $record['headings'] as $key ) {
			if ( isset( $headings[ $key ] ) && empty( $usage[ $key ]['count'] ) ) {
				unset( $headings[ $key ] );
				++$report['headings'];
			}
		}

		nwcs_save_product_headings( $headings );
	}

	return $report;
}

/**
 * Basliklarin kullanimi (yayindaki urunler): anahtar => { count, categories
 * [ad => adet] } (kategoriler cok kullanandan aza).
 *
 * @return array<string, array{count:int, categories:array<string,int>}>
 */
function nwcs_heading_usage(): array {
	$usage = array();

	foreach ( nwcs_pool_products() as $product ) {
		foreach ( (array) $product['details'] as $row ) {
			$key = nwcs_heading_key( (string) $row['label'] );

			if ( '' === $key ) {
				continue;
			}

			$usage[ $key ] = $usage[ $key ] ?? array( 'count' => 0, 'categories' => array() );
			++$usage[ $key ]['count'];

			foreach ( (array) $product['categories'] as $name ) {
				$usage[ $key ]['categories'][ $name ] = ( $usage[ $key ]['categories'][ $name ] ?? 0 ) + 1;
			}
		}
	}

	foreach ( array_keys( $usage ) as $key ) {
		arsort( $usage[ $key ]['categories'] );
	}

	return $usage;
}
