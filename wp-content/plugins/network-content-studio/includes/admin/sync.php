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
 *
 * Ortak yardimcilar (gecici klasor, metin sadelestirme) da burada; Ürün
 * Açıklamaları ekrani da bunlari kullanir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_SYNC_PLAN     = 'nwcs_sync_plan_';
const NWCS_SYNC_KIND     = 'urunler';
const NWCS_SYNC_SHEET    = 'Ürünler';
const NWCS_SYNC_INFO     = '_bilgi';
const NWCS_SYNC_VERSION  = 1;

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
		return new WP_Error( 'nwcs_upload', 'Dosya sunucuda açılamadı. Yeniden deneyin; sorun sürerse yöneticiye bildirin.' );
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
			return new WP_Error( 'nwcs_not_template', 'Bu dosya panelden indirilen taslak değil. Önce panelden “Excel indir” ile taslağı indirin, onu doldurup yükleyin.' );
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
		return new WP_Error( 'nwcs_other_panel', sprintf( 'Bu dosya başka bir panelden (%s) indirilmiş. Bu panelden “Excel indir” ile yeniden indirip onu doldurun.', $info['kaynak'] ) );
	}

	if ( ( $info['tur'] ?? '' ) !== $kind ) {
		$others = array(
			'aciklamalar' => 'Bu, Ürün Açıklamaları dosyası; onu “Ürün açıklamaları” ekranından yükleyin.',
			'alt'         => 'Bu, görsel alt metinleri dosyası; onu Medya Havuzu’ndaki “Alt metinleri Excel ile düzenleyin” kutusundan yükleyin.',
		);
		$other  = $others[ $info['tur'] ?? '' ] ?? 'Bu dosya bu ekranın taslağı değil.';

		return new WP_Error(
			'nwcs_wrong_template',
			NWCS_SYNC_KIND === ( $info['tur'] ?? '' ) ? 'Bu, kategori ürün dosyası; onu Ürün Havuzu › Kategoriler sayfasında kategorinin “Yükle” düğmesiyle yükleyin.' : $other
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
		'id'    => 'KİMLİK',
		'code'  => 'ÜRÜN KODU',
		'title' => 'ÜRÜN ADI',
		'site'  => 'SİTE',
		'price' => 'FİYAT',
		'short' => 'KISA AÇIKLAMA',
	);
}

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
		return new WP_Error( 'nwcs_cat', 'Kategori bulunamadı.' );
	}

	$term     = $categories[ $slug ];
	$system   = nwcs_sync_system_columns();
	$headings = nwcs_sync_category_headings( $slug );

	$header  = array( $system['id'], $system['code'] . ' *', $system['title'] . ' *', $system['site'], $system['price'], $system['short'] );
	$columns = array(
		0 => array( 'hidden' => true, 'width' => 8 ),
		1 => array( 'width' => 16 ),
		2 => array( 'width' => 38 ),
		3 => array( 'width' => 22 ),
		4 => array( 'width' => 14 ),
		5 => array( 'width' => 44, 'wrap' => true ),
	);

	foreach ( $headings as $row ) {
		$columns[ count( $header ) ] = array( 'width' => max( 14, min( 30, mb_strlen( $row['label'] ) + 4 ) ) );
		$header[]                    = nwcs_sync_heading_header( $row['label'] ) . ( $row['required'] ? ' *' : '' );
	}

	$rows = array( $header );

	foreach ( nwcs_sync_category_products( $slug ) as $product ) {
		$line = array( (string) $product['id'], (string) $product['code'], (string) $product['title'], nwcs_sync_sites_text( nwcs_sync_product_sites( (int) $product['id'] ) ), (string) $product['price'], (string) $product['short'] );

		foreach ( array_keys( $headings ) as $key ) {
			$line[] = nwcs_details_value( (array) $product['details'], $key );
		}

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
		array( 'Bu dosya “' . $category . '” kategorisinin ürün listesidir.' ),
		array( '1. “Ürünler” sayfasındaki satırları düzenleyin. Her satır bir üründür.' ),
		array( '2. Yeni ürün için en alta bir satır ekleyin. ÜRÜN KODU zorunludur: her ürüne bir kod yazın; fotoğraf adları bu kodla başlar (W-KAM-400DUB-1.jpg). Kod boş olan satır yüklenmez. Başka bir satırı kopyaladıysanız yeni satırın ÜRÜN KODU hücresine yeni ürünün kodunu yazın.' ),
		array( '3. Yıldızlı (*) sütunlar zorunludur. Boş bırakılan satır yüklenmez; hangi satırda ne eksik olduğu panelde yazar.' ),
		array( '4. Boş hücre o bilgiyi siler. FİYAT boşsa sitede “Teklif al” görünür.' ),
		array( 'SİTE: ürünün görüneceği site. Hepsi ya da site adları virgülle: ' . implode( ', ', nwcs_sync_sites() ) . '. Boş bırakırsanız değişmez (yeni ürün kategorinin yerleştiği sitelerde görünür). Yazılmayan sitede ürün gizlenir; silinmez.' ),
		array( '5. Bir satırı silerseniz o ürün, onay verirseniz çöp kutusuna taşınır. Çöp kutusundan geri getirilebilir.' ),
		array( '6. Yeni bir detay başlığı için sağa bir sütun ekleyin ve başlığını yazın. En az bir hücresi doluysa başlık olarak eklenir.' ),
		array( '7. Sütun başlıklarını ve gizli sayfaları değiştirmeyin. Excel sayıları tarihe çevirebilir (örneğin 1/2): böyle hücreleri kontrol edin.' ),
		array( '8. Bitince dosyayı .xlsx olarak kaydedin. Ürün Havuzu › Kategoriler sayfasında bu kategoriyi açıp “Yükle” ile yükleyin. Önce ne değişeceği gösterilir; onaylamadan hiçbir şey yazılmaz.' ),
		array( 'Görseller, uzun açıklama, tablolar ve sitelere özel ayarlar bu dosyada yok; yüklemede değişmezler.' ),
		array( 'Fotoğraflar bu dosyada yok. Fotoğraf dosyalarını ürün koduyla adlandırın (W-KAM-400DUB-1.jpg, -2.jpg …) ve aynı kategori sayfasındaki “Fotoğrafları yükleyin” alanına bırakın. Uzun açıklamalar için “Ürün açıklamaları” ekranını kullanın.' ),
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
			? new WP_Error( 'nwcs_sheet', '“Ürünler” sayfası bulunamadı. Sayfanın adını değiştirdiyseniz geri “Ürünler” yapın.' )
			: $read;
	}

	$categories = nwcs_pool_categories();
	$slug       = sanitize_title( $info['kategori'] ?? '' );

	// Kategori adi degistiyse de kimlikle bulunur.
	if ( ! isset( $categories[ $slug ] ) ) {
		foreach ( $categories as $candidate_slug => $candidate ) {
			if ( (int) $candidate['id'] === (int) ( $info['kategori_id'] ?? 0 ) ) {
				$slug = $candidate_slug;
			}
		}
	}

	if ( ! isset( $categories[ $slug ] ) ) {
		return new WP_Error( 'nwcs_cat', sprintf( 'Bu dosyanın kategorisi (%s) artık havuzda yok. Kategoriyi yeniden açın ya da başka bir kategorinin taslağını indirin.', $info['kategori_adi'] ?? $slug ) );
	}

	// Kesilen dosyada son satirlar okunmaz; o urunler "dosyada yok" sayilip cope giderdi.
	if ( $read['truncated'] ) {
		return new WP_Error( 'nwcs_rows', sprintf( 'Dosya çok büyük: en fazla %s satır okunabiliyor. Dosyayı bölün; kategori başına bir dosya kullanın.', number_format_i18n( NWCS_XLSX_MAX_ROWS ) ) );
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

	foreach ( $header as $index => $label ) {
		$base = trim( (string) preg_replace( '/\s*\*+\s*$/u', '', trim( $label ) ) );
		$key  = nwcs_heading_key( $base );

		if ( '' === $base ) {
			continue;
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
		if ( preg_match( '/^(.+?)\s*\(detay\)$/u', $base, $match ) && in_array( nwcs_heading_key( $match[1] ), nwcs_heading_reserved_keys(), true ) ) {
			$base = trim( $match[1] );
			$key  = nwcs_heading_key( $base );
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

	if ( empty( $seen['title'] ) ) {
		return new WP_Error( 'nwcs_cols', '“ÜRÜN ADI” sütunu bulunamadı. Sütun başlıklarını değiştirdiyseniz geri alın ya da taslağı yeniden indirin.' );
	}

	// Urun kodu zorunlu: her urunun kodu olur, fotograflar bu kodla eslesir.
	if ( empty( $seen['code'] ) ) {
		return new WP_Error( 'nwcs_cols', '“ÜRÜN KODU” sütunu bulunamadı. Her ürünün kodu olmalı; taslağı yeniden indirip ÜRÜN KODU sütununu doldurun.' );
	}

	foreach ( nwcs_required_headings() as $key => $label ) {
		if ( empty( $seen[ 'h:' . $key ] ) ) {
			return new WP_Error( 'nwcs_cols', sprintf( '“%s” sütunu dosyada yok. Bu başlık zorunlu. Taslağı yeniden indirip onu doldurun.', $label ) );
		}
	}

	// Yeni sutun: en az bir hucresi doluysa yeni baslik olur, bossa atlanir.
	$new_headings = array();
	$ignored      = array();

	foreach ( $columns as $index => $column ) {
		if ( 'heading' !== $column['type'] || ! $column['new'] ) {
			continue;
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

	$required = nwcs_required_headings();

	/* ---------- Satirlar ---------- */
	switch_to_blog( nwcs_pool_blog_id() );

	$downloaded = (int) ( $info['indirme'] ?? 0 );
	$plan       = array(
		'time'       => time(),
		'file'       => sanitize_file_name( $filename ),
		'slug'       => $slug,
		'term_id'    => (int) $categories[ $slug ]['id'],
		'term_name'  => $categories[ $slug ]['name'],
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
		// Cop kutusu kapaliysa (EMPTY_TRASH_DAYS = 0) cope atma kalici silme olurdu.
		'trash_off'  => ! nwcs_trash_enabled(),
	);

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

			$fail( 'ÜRÜN KODU boş. Her ürünün kodu olmalı; fotoğraflar bu kodla eşleşir.' );
			continue;
		}

		/* Eslesme: KIMLIK > KOD > yeni. */
		$post = null;

		if ( '' !== $raw_id ) {
			$post = preg_match( '/^\d+$/', $raw_id ) ? nwcs_sync_product_post( (int) $raw_id ) : null;

			if ( ! $post ) {
				$fail( 'Gizli KİMLİK sütunundaki değer havuzdaki bir ürüne ait değil. Satırı başka bir dosyadan kopyaladıysanız bu dosyada yeniden yazın.' );
				continue;
			}

			// Kopyalanmis satir: KIMLIK ikinci kez geliyor. Kod ayniysa ayni urun
			// iki kez yazilmis demektir (hata); degilse satir yeni urun ya da
			// koduyla baska bir urun sayilir.
			if ( isset( $by_id[ $post->ID ] ) ) {
				$own_code = (string) get_post_meta( $post->ID, '_nwcs_code', true );

				if ( null === $code || $code === $own_code ) {
					$matched[ $post->ID ] = true;
					$fail( sprintf( 'Bu satır %d. satırdaki ürünün aynısı (aynı ürün kodu). Yeni ürün eklemek istiyorsanız ÜRÜN KODU hücresine yeni ürünün kodunu yazın.', $by_id[ $post->ID ] ) );
					continue;
				}

				$plan['notes'][] = sprintf( '%d. satır %d. satırdan kopyalanmış; farklı kodu olduğu için ayrı ürün olarak alındı.', $line, $by_id[ $post->ID ] );
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
				$fail( sprintf( 'ÜRÜN KODU (%s) %d. satırdaki ürünün kodu. Kodlar tekil olmalı; farklı bir kod yazın.', $code, $by_id[ $post->ID ] ) );
				continue;
			}

			if ( $post ) {
				$by_id[ $post->ID ] = $line;
			}
		}

		if ( $post ) {
			$matched[ $post->ID ] = true;
		}

		/* Satir denetimleri. */
		if ( '' === $title ) {
			$fail( 'Ürün adı boş. Adı yazın; ürünü kaldırmak istiyorsanız satırı tamamen silin.' );
			continue;
		}

		if ( null !== $code && '' !== $code ) {
			if ( isset( $by_code[ $code ] ) ) {
				$fail( sprintf( 'ÜRÜN KODU (%s) %d. satırda da var. Kodlar tekil olmalı; birini değiştirin.', $code, $by_code[ $code ] ) );
				continue;
			}

			$owner = nwcs_product_id_by_code( $code, $post ? (int) $post->ID : 0 );

			if ( $owner ) {
				$fail( sprintf( 'ÜRÜN KODU (%s) başka bir üründe kullanılıyor: “%s”%s. Farklı bir kod yazın.', $code, get_the_title( $owner ), 'trash' === get_post_status( $owner ) ? ' (çöp kutusunda)' : '' ) );
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
			$fail( sprintf( '%s boş. %s zorunlu; doldurun.', '“' . implode( '”, “', $missing ) . '”', count( $missing ) > 1 ? 'Bu başlıklar' : 'Bu başlık' ), $missing );
			continue;
		}

		// SİTE: bos ya da sutun yok = degismez (null).
		$sites = null === $cell( 'site' ) ? null : nwcs_sync_parse_sites( (string) $cell( 'site' ) );

		if ( is_wp_error( $sites ) ) {
			$fail( $sites->get_error_message() );
			continue;
		}

		$data = array(
			'title'   => $title,
			'code'    => $code,
			'price'   => null,
			'short'   => null === $cell( 'short' ) ? null : nwcs_clean_text( (string) $cell( 'short' ), true ),
			'details' => $values,
			'sites'   => $sites,
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

		if ( ! $post ) {
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

	/* ---------- Cop kutusuna gidecekler ---------- */
	foreach ( nwcs_sync_category_products( $slug ) as $id => $product ) {
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

	foreach ( $site_rows as $row_sites ) {
		foreach ( $row_sites as $blog_id ) {
			if ( ! isset( $plan['sites'][ $blog_id ] ) && isset( $all_sites[ $blog_id ] ) ) {
				$unplaced[ $blog_id ] = $all_sites[ $blog_id ];
			}
		}
	}

	if ( $unplaced ) {
		$plan['notes'][] = sprintf(
			'SİTE sütununda yazılan %s bu kategori yerleşmemiş: ürün orada listelenir ama kategori sayfasında görünmez. Kategorinin “Sitelerde” kutusundan yerleştirin.',
			nwcs_join_and( array_map( 'nwcs_dative', array_values( $unplaced ) ) )
		);
	}

	if ( $nowhere ) {
		$plan['notes'][] = sprintf(
			'%d ürünün SİTE hücresi boş ve hiçbir sitede görünmüyor: %s. Göstermek için SİTE’ye site adını ya da Hepsi yazın.',
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
		'category' => (string) $plan['term_name'],
		'user'     => get_current_user_id(),
		'complete' => false,
		'created'  => array(),
		'updated'  => array(),
		'trashed'  => array(),
		'headings' => array(),
		'sites'    => array(),
		'skipped'  => array(),
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

	$returned = array(); // cop kutusundan geri gelenler (gorunurlukte yeni urun gibi)
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
		wp_update_post( array( 'ID' => $id, 'post_title' => wp_slash( $data['title'] ) ) );

		if ( null !== $data['code'] && '' !== $data['code'] ) {
			update_post_meta( $id, '_nwcs_code', $data['code'] );
		}

		if ( null !== $data['price'] ) {
			update_post_meta( $id, '_nwcs_price', wp_slash( $data['price'] ) );
		}

		if ( null !== $data['short'] ) {
			update_post_meta( $id, '_nwcs_short', wp_slash( $data['short'] ) );
		}

		// Detaylar yalnizca bir degeri degistiyse yazilir; aksi halde urunun sirasi aynen kalir.
		if ( ! empty( $data['details_changed'] ) ) {
			nwcs_product_write_details( $id, nwcs_sync_merge_details( nwcs_product_details( $id ), (array) $data['details'], $labels ) );
		}

		if ( is_array( $data['sites'] ?? null ) ) {
			$visible[ $id ] = $data['sites'];
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
				'post_type'   => NWCS_PRODUCT_TYPE,
				'post_status' => 'publish',
				'post_title'  => wp_slash( $data['title'] ),
				'post_name'   => sanitize_title( $data['title'] ),
			)
		);

		if ( ! $id ) {
			$record['skipped'][] = $data['title'];
			continue;
		}

		$record['created'][] = $id;
		$save();

		update_post_meta( $id, '_nwcs_code', $data['code'] );

		update_post_meta( $id, '_nwcs_price', wp_slash( (string) $data['price'] ) );
		update_post_meta( $id, '_nwcs_short', wp_slash( (string) $data['short'] ) );
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
	$report = array( 'removed' => 0, 'restored' => 0, 'untrashed' => 0, 'headings' => 0, 'kept' => array() );
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
