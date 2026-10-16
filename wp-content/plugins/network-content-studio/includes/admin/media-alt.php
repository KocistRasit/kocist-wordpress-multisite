<?php
/**
 * Medya Havuzu -> "Alt metinleri Excel ile düzenleyin".
 *
 * Kategori Excel'i ve Ürün açıklamaları ile ayni akis: indir -> doldur ->
 * yukle -> onizleme -> uygula -> "Son işlemler"den geri al.
 *
 * Dosya: "Görseller" | "Nasıl kullanılır" | gizli "_bilgi" (tur=alt, kaynak).
 * Sutunlar: KİMLİK (gizli, ek kimligi) | DOSYA | ÜRÜN | SİTE | ALT METİN.
 * Yalnizca KİMLİK ve ALT METİN okunur; digerleri bilgi icindir.
 *
 * Kapsam: tum gorseller, alt metni bos olanlar ya da bir kategorinin
 * (yayindaki) urunlerinin galerilerindeki gorseller. Fotograf Kutusu'nda
 * bekleyen dosyalar (henuz urune baglanmamis) taslaga girmez.
 *
 * Bos hucre alt metni siler (kullanici karari); onizleme silinecekleri ayri
 * listeler. Alt metin tek satir, en fazla NWCS_ALT_MAX karakter; uzunu satir
 * hatasi olur (kesilmez: kesik cumle sitede yanlis okunur).
 *
 * Geri alma: kayit her gorselin onceki ham degerini (meta yoksa null) tutar;
 * geri almada o deger aynen yazilir, yoksa meta silinir. Uygulamadan sonra
 * elle degistirilen gorsele dokunulmaz.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_ALT_KIND  = 'alt';
const NWCS_ALT_SHEET = 'Görseller';
const NWCS_ALT_PLAN  = 'nwcs_alt_plan_';
const NWCS_ALT_MAX   = 250;
const NWCS_ALT_META  = '_wp_attachment_image_alt';

/* ====================================================================== *
 * Kapsam ve satirlar
 * ====================================================================== */

/**
 * Formdaki kapsam degeri: "tumu", "bos" ya da "kategori:<slug>".
 *
 * @return array{type:string, slug:string, name:string, key:string}|WP_Error
 */
function nwcs_alt_scope( string $value ) {
	$value = trim( $value );

	if ( '' === $value || 'tumu' === $value ) {
		return array( 'type' => 'all', 'slug' => '', 'name' => 'Tüm görseller', 'key' => 'tumu' );
	}

	if ( 'bos' === $value ) {
		return array( 'type' => 'empty', 'slug' => '', 'name' => 'Alt metni boş görseller', 'key' => 'alt-metni-bos' );
	}

	if ( str_starts_with( $value, 'kategori:' ) ) {
		$slug       = sanitize_title( substr( $value, 9 ) );
		$categories = nwcs_pool_categories();

		if ( isset( $categories[ $slug ] ) ) {
			return array( 'type' => 'cat', 'slug' => $slug, 'name' => (string) $categories[ $slug ]['name'], 'key' => $slug );
		}
	}

	return new WP_Error( 'nwcs_alt_scope', 'Seçilen görsel grubu bulunamadı. Sayfayı yenileyip yeniden seçin.' );
}

/**
 * Havuz sitesindeki gorsel ekleri (bekleyen Fotograf Kutusu dosyalari
 * haric), en yeni once. $only_empty: alt metni bos (ya da yalnizca bosluk)
 * olanlar.
 *
 * @return int[]
 */
function nwcs_alt_image_ids( bool $only_empty = false ): array {
	global $wpdb;

	switch_to_blog( nwcs_pool_blog_id() );

	$empty = $only_empty
		? $wpdb->prepare( " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} a WHERE a.post_id = p.ID AND a.meta_key = %s AND TRIM( a.meta_value ) <> '' )", NWCS_ALT_META )
		: '';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- $empty yukarida hazirlandi.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = %s )"
			. $empty .
			' ORDER BY p.post_date DESC, p.ID DESC',
			'image/%',
			NWCS_PHOTO_META
		)
	);
	// phpcs:enable

	restore_current_blog();

	return array_map( 'intval', (array) $ids );
}

/**
 * Kapsamdaki gorseller, sirayla. Kategori: urun sirasi, sonra galeri sirasi.
 *
 * @return int[]
 */
function nwcs_alt_scope_ids( array $scope ): array {
	if ( 'cat' !== $scope['type'] ) {
		return nwcs_alt_image_ids( 'empty' === $scope['type'] );
	}

	$ids = array();

	foreach ( nwcs_sync_category_products( $scope['slug'] ) as $product ) {
		foreach ( (array) $product['gallery_ids'] as $id ) {
			$ids[ (int) $id ] = (int) $id;
		}
	}

	// Silinmis ya da gorsel olmayan kimlikler (bozuk galeri) dusurulur.
	$images = array_flip( nwcs_alt_image_ids() );

	return array_values( array_filter( $ids, static fn( int $id ): bool => isset( $images[ $id ] ) ) );
}

/**
 * Kategori basina gorsel sayisi (secim listesi icin). Indirmeyle ayni kural
 * (nwcs_alt_scope_ids): yayindaki urunlerin galerileri, yalnizca var olan
 * gorseller. $images: nwcs_alt_image_ids() sonucu (karttaki "Tümü" sayisiyla
 * paylasilir; sorgu bir kez calisir).
 *
 * @param int[] $images
 * @return array<string, int>
 */
function nwcs_alt_category_counts( array $images ): array {
	$images = array_flip( $images );
	$sets   = array();

	foreach ( nwcs_pool_products() as $product ) {
		foreach ( array_keys( (array) $product['categories'] ) as $slug ) {
			foreach ( (array) $product['gallery_ids'] as $id ) {
				if ( isset( $images[ (int) $id ] ) ) {
					$sets[ $slug ][ (int) $id ] = true;
				}
			}
		}
	}

	return array_map( 'count', $sets );
}

/**
 * Taslak satirlari icin her gorselin kullanimi: urunler ("KOD · Ad") ve
 * urunlerin gorundugu siteler.
 *
 * @return array<int, array{products:string, sites:string}>
 */
function nwcs_alt_usage_texts(): array {
	$products = array();

	foreach ( nwcs_pool_products() as $product ) {
		$products[ (int) $product['id'] ] = $product;
	}

	$out = array();

	foreach ( nwcs_media_usage() as $attachment_id => $used ) {
		$names = array();
		$sites = array();

		foreach ( $used as $row ) {
			$product = $products[ (int) $row['id'] ] ?? null;
			$code    = $product ? trim( (string) $product['code'] ) : '';
			$names[] = ( '' !== $code ? $code . ' · ' : '' ) . $row['title'] . ( $row['trashed'] ? ' (çöp kutusunda)' : '' );

			if ( ! $row['trashed'] ) {
				$sites = array_merge( $sites, nwcs_sync_product_sites( (int) $row['id'] ) );
			}
		}

		$out[ (int) $attachment_id ] = array(
			'products' => implode( ', ', array_unique( $names ) ),
			'sites'    => nwcs_sync_sites_text( array_unique( $sites ) ),
		);
	}

	return $out;
}

/* ====================================================================== *
 * Indirme, plan, uygulama, geri alma
 * ====================================================================== */

/**
 * @return array{path:string, filename:string, count:int}|WP_Error
 */
function nwcs_alt_build_template( string $value ) {
	$scope = nwcs_alt_scope( $value );

	if ( is_wp_error( $scope ) ) {
		return $scope;
	}

	$ids   = nwcs_alt_scope_ids( $scope );
	$usage = nwcs_alt_usage_texts();
	$rows  = array( array( 'KİMLİK', 'DOSYA', 'ÜRÜN', 'SİTE', 'ALT METİN' ) );

	switch_to_blog( nwcs_pool_blog_id() );

	if ( $ids ) {
		update_meta_cache( 'post', $ids );
	}

	foreach ( $ids as $id ) {
		$rows[] = array(
			(string) $id,
			wp_basename( (string) get_post_meta( $id, '_wp_attached_file', true ) ),
			$usage[ $id ]['products'] ?? '',
			$usage[ $id ]['sites'] ?? '',
			(string) get_post_meta( $id, NWCS_ALT_META, true ),
		);
	}

	restore_current_blog();

	$now  = time();
	$path = nwcs_import_temp_xlsx();

	$write = nwcs_xlsx_write(
		$path,
		array(
			array(
				'name'    => NWCS_ALT_SHEET,
				'rows'    => $rows,
				'columns' => array(
					0 => array( 'hidden' => true, 'width' => 8 ),
					1 => array( 'width' => 34 ),
					2 => array( 'width' => 40, 'wrap' => true ),
					3 => array( 'width' => 18 ),
					4 => array( 'width' => 70, 'wrap' => true ),
				),
			),
			array(
				'name'    => 'Nasıl kullanılır',
				'columns' => array( 0 => array( 'width' => 110, 'wrap' => true ) ),
				'rows'    => array(
					array( 'Bu dosya “' . $scope['name'] . '” için görsel alt metinleridir. Alt metin, görseli göremeyen kişiye (ekran okuyucu kullananlara) ve arama motorlarına görselde ne olduğunu anlatır. Her satır bir görseldir.' ),
					array( '1. Yalnızca ALT METİN sütununu değiştirin. Görseller gizli bir sütunla tanınır; DOSYA, ÜRÜN ve SİTE yalnızca bilgi içindir.' ),
					array( '2. Görseli kısa ve somut tarif edin. Örnek: “İki katlı ahşap kedi evi, doğal renk”.' ),
					array( '3. Tek satır yazın; en fazla ' . NWCS_ALT_MAX . ' karakter.' ),
					array( '4. Hücreyi boşaltırsanız o görselin alt metni silinir. Önizlemede silinecek görseller ayrıca listelenir.' ),
					array( '5. Satır silmek bir şey değiştirmez: dosyada olmayan görsele dokunulmaz. Bu dosyayla yeni görsel eklenemez.' ),
					array( '6. Bitince dosyayı .xlsx olarak kaydedin ve Medya Havuzu’ndaki “Alt metinleri Excel ile düzenleyin” kutusundan yükleyin. Önce neyin değişeceği gösterilir; uyguladıktan sonra “Son işlemler”den geri alabilirsiniz.' ),
				),
			),
			array(
				'name'   => NWCS_SYNC_INFO,
				'hidden' => true,
				'header' => false,
				'rows'   => array(
					array( 'anahtar', 'değer' ),
					array( 'tur', NWCS_ALT_KIND ),
					array( 'kapsam', $scope['name'] ),
					array( 'indirme', (string) $now ),
					array( 'surum', '1' ),
					array( 'kaynak', network_home_url( '/' ) ),
				),
			),
		)
	);

	if ( is_wp_error( $write ) ) {
		return $write;
	}

	return array(
		'path'     => $path,
		'filename' => 'gorsel-alt-metinleri-' . sanitize_title( $scope['key'] ) . '-' . wp_date( 'Y-m-d', $now ) . '.xlsx',
		'count'    => count( $ids ),
	);
}

/**
 * Gorselin ham alt metni; meta hic yoksa null (geri almada aynen geri gelsin).
 * Havuz baglaminda cagrilmalidir.
 */
function nwcs_alt_raw( int $id ): ?string {
	return metadata_exists( 'post', $id, NWCS_ALT_META ) ? (string) get_post_meta( $id, NWCS_ALT_META, true ) : null;
}

/**
 * Dosyadan alt metin plani. Hicbir sey yazilmaz.
 *
 * @return array|WP_Error
 */
function nwcs_alt_plan( string $path, string $filename ) {
	$info = nwcs_sync_read_info( $path, NWCS_ALT_KIND );

	if ( is_wp_error( $info ) ) {
		return $info;
	}

	$read = nwcs_xlsx_read_rows( $path, NWCS_ALT_SHEET );

	if ( is_wp_error( $read ) ) {
		return 'nwcs_xlsx_nosheet' === $read->get_error_code()
			? new WP_Error( 'nwcs_sheet', 'Excel’de “Görseller” sayfası bulunamadı. Excel’in altındaki sayfa sekmesinin adını değiştirdiyseniz yeniden “Görseller” yapın.' )
			: $read;
	}

	if ( $read['truncated'] ) {
		return new WP_Error( 'nwcs_rows', sprintf( 'Dosyada çok fazla satır var: en fazla %s satır okunabiliyor. Daha küçük bir grup (örneğin bir kategori) seçip Excel’i yeniden indirin.', number_format_i18n( NWCS_XLSX_MAX_ROWS ) ) );
	}

	$rows   = $read['rows'];
	$header = array_map( static fn( $label ): string => trim( (string) $label ), (array) array_shift( $rows ) );
	$col    = array_flip( $header );

	if ( ! isset( $col['KİMLİK'], $col['ALT METİN'] ) ) {
		return new WP_Error( 'nwcs_cols', 'Sütun adları değiştirilmiş ya da bir sütun silinmiş, bu yüzden dosya okunamadı. Excel’i yeniden indirip onu doldurun. (ALT METİN sütunu ya da gizli sütun bulunamadı)' );
	}

	$plan = array(
		'time'       => time(),
		'file'       => sanitize_file_name( $filename ),
		'scope'      => (string) ( $info['kapsam'] ?? '' ),
		'downloaded' => (int) ( $info['indirme'] ?? 0 ),
		'update'     => array(),
		'clear'      => array(),
		'same'       => 0,
		'errors'     => array(),
	);
	$seen = array();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $rows as $offset => $row ) {
		$line = $offset + 2;
		$row  = (array) $row;

		if ( '' === trim( implode( '', array_map( 'strval', $row ) ) ) ) {
			continue;
		}

		$raw_id = trim( (string) ( $row[ $col['KİMLİK'] ] ?? '' ) );
		$file   = trim( (string) ( $row[ $col['DOSYA'] ?? -1 ] ?? '' ) );
		$used   = trim( (string) ( $row[ $col['ÜRÜN'] ?? -1 ] ?? '' ) );
		$label  = '' !== $file ? $file : ( '' !== $raw_id ? 'Görsel no ' . $raw_id : '' );
		$post   = preg_match( '/^\d+$/', $raw_id ) ? get_post( (int) $raw_id ) : null;

		if ( ! $post || 'attachment' !== $post->post_type ) {
			$plan['errors'][] = array(
				'line'    => $line,
				'name'    => $label,
				'message' => '' === $raw_id
					? 'Bu satırın hangi görsele ait olduğu anlaşılamadı; satır sonradan eklenmiş olabilir. Bu dosyayla yeni görsel eklenemez. Görselin satırını, Excel’i yeniden indirip oradan alın. (Teknik: gizli KİMLİK hücresi boş)'
					: 'Bu satırın görseli Medya Havuzu’nda bulunamadı: görsel silinmiş ya da satır başka bir Excel dosyasından kopyalanmış olabilir. Bu satırı silebilirsiniz; diğer satırlar etkilenmez.',
			);
			continue;
		}

		if ( ! wp_attachment_is_image( $post ) ) {
			$plan['errors'][] = array( 'line' => $line, 'name' => $label, 'message' => 'Bu satır bir görsele değil, başka bir dosyaya (örneğin videoya) ait. Alt metin yalnızca görsellere yazılır; bu satırı silin.' );
			continue;
		}

		$id   = (int) $post->ID;
		$file = wp_basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );

		if ( isset( $seen[ $id ] ) ) {
			$plan['errors'][] = array( 'line' => $line, 'name' => $file, 'message' => sprintf( 'Bu görsel %d. satırda da var. Satırlardan birini silin.', $seen[ $id ] ) );
			continue;
		}

		$seen[ $id ] = $line;
		$new         = nwcs_clean_text( (string) ( $row[ $col['ALT METİN'] ] ?? '' ) );
		$length      = mb_strlen( $new );

		if ( $length > NWCS_ALT_MAX ) {
			$plan['errors'][] = array( 'line' => $line, 'name' => $file, 'message' => sprintf( 'Alt metin çok uzun (%1$d karakter). En fazla %2$d karakter yazın; görseli kısa tarif etmek yeterli.', $length, NWCS_ALT_MAX ) );
			continue;
		}

		$raw = nwcs_alt_raw( $id );
		$old = nwcs_clean_text( (string) $raw );

		if ( $new === $old ) {
			++$plan['same'];
			continue;
		}

		$src  = wp_get_attachment_image_src( $id, 'thumbnail' );
		$item = array(
			'id'       => $id,
			'file'     => $file,
			'products' => $used,
			'thumb'    => $src ? (string) $src[0] : '',
			'raw'      => $raw,
			'old'      => $old,
			'new'      => $new,
		);

		if ( '' === $new ) {
			$plan['clear'][] = $item;
		} else {
			$plan['update'][] = $item;
		}
	}

	restore_current_blog();

	return $plan;
}

/**
 * Plani uygular; geri alma kaydi "Son işlemler" yiginina girer.
 *
 * Kayda her gorselin onceki ham degeri (before) ve yazilan deger (after; silme
 * icin null) girer. Kayit 50 gorselde bir, yazmadan once guncellenir: istek
 * yarida kesilse de yapilanlar geri alinabilir (yazilmamis olanlar geri almada
 * "zaten eski hali" sayilir).
 */
function nwcs_alt_apply( array $plan ): array {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$record = array(
		'id'       => bin2hex( random_bytes( 6 ) ),
		'kind'     => NWCS_ALT_KIND,
		'file'     => $plan['file'],
		'scope'    => $plan['scope'],
		'complete' => false,
		'before'   => array(),
		'after'    => array(),
		'names'    => array(),
		'skipped'  => array(),
		'counts'   => array( 'updated' => 0, 'cleared' => 0, 'skipped' => 0 ),
	);

	$save = static function () use ( &$record ): void {
		$record['time']              = time();
		$record['counts']['skipped'] = count( $record['skipped'] );
		nwcs_history_save( $record );
	};

	nwcs_history_push( $record + array( 'time' => time() ) );

	$items = array_merge(
		array_map( static fn( array $item ): array => $item + array( 'clear' => false ), (array) $plan['update'] ),
		array_map( static fn( array $item ): array => $item + array( 'clear' => true ), (array) $plan['clear'] )
	);

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( array_chunk( $items, 50 ) as $chunk ) {
		$todo = array();

		foreach ( $chunk as $item ) {
			$id   = (int) $item['id'];
			$post = get_post( $id );

			// Onizlemeden sonra silinmis ya da alt metni degismis: dokunulmaz.
			if ( ! $post || 'attachment' !== $post->post_type || nwcs_alt_raw( $id ) !== $item['raw'] ) {
				$record['skipped'][] = (string) $item['file'];
				continue;
			}

			$record['before'][ $id ] = $item['raw'];
			$record['after'][ $id ]  = $item['clear'] ? null : (string) $item['new'];
			$record['names'][ $id ]  = (string) $item['file'];
			$todo[]                  = $item;
		}

		$save();

		foreach ( $todo as $item ) {
			if ( $item['clear'] ) {
				delete_post_meta( (int) $item['id'], NWCS_ALT_META );
				++$record['counts']['cleared'];
			} else {
				// update_post_meta degeri unslash eder; ters bolu kaybolmasin.
				update_post_meta( (int) $item['id'], NWCS_ALT_META, wp_slash( (string) $item['new'] ) );
				++$record['counts']['updated'];
			}
		}
	}

	restore_current_blog();

	$record['complete'] = true;
	$save();
	nwcs_pool_flush_cache();

	return $record;
}

/**
 * Bir alt metin yuklemesini geri alir.
 *
 * @return array{alts:int, kept:string[]}
 */
function nwcs_alt_undo( array $record ): array {
	$report = array( 'alts' => 0, 'kept' => array() );
	$after  = (array) ( $record['after'] ?? array() );

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( (array) ( $record['before'] ?? array() ) as $id => $before ) {
		$id   = (int) $id;
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			continue;
		}

		$now = nwcs_alt_raw( $id );

		// Yazilmadan kesilmis (zaten eski hali) ya da sonradan elle degistirilmis.
		if ( $now === $before ) {
			continue;
		}

		if ( $now !== ( $after[ $id ] ?? null ) ) {
			$report['kept'][] = (string) ( $record['names'][ $id ] ?? ( '#' . $id ) );
			continue;
		}

		if ( null === $before ) {
			delete_post_meta( $id, NWCS_ALT_META );
		} else {
			update_post_meta( $id, NWCS_ALT_META, wp_slash( (string) $before ) );
		}

		++$report['alts'];
	}

	restore_current_blog();

	nwcs_pool_flush_cache();

	return $report;
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

function nwcs_alt_redirect( string $step ): void {
	wp_safe_redirect( nwcs_media_url( array( 'adim' => $step ) ) . '#nwcs-excel-result' );
	exit;
}

add_action( 'admin_post_nwcs_alt_download', 'nwcs_handle_alt_download' );
function nwcs_handle_alt_download(): void {
	nwcs_sync_guard( 'nwcs_alt_download' );

	$result = nwcs_alt_build_template( isset( $_GET['kapsam'] ) ? sanitize_text_field( wp_unslash( $_GET['kapsam'] ) ) : '' );

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	nwcs_xlsx_send( $result['path'], $result['filename'] );
}

add_action( 'admin_post_nwcs_alt_upload', 'nwcs_handle_alt_upload' );
function nwcs_handle_alt_upload(): void {
	nwcs_sync_guard( 'nwcs_alt_upload' );

	$key  = NWCS_ALT_PLAN . get_current_user_id();
	$path = nwcs_import_uploaded_xlsx( 'dosya' );
	$plan = is_wp_error( $path ) ? $path : nwcs_alt_plan( $path, sanitize_file_name( (string) ( $_FILES['dosya']['name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( ! is_wp_error( $path ) ) {
		wp_delete_file( $path );
	}

	if ( is_wp_error( $plan ) ) {
		set_site_transient( $key . '_hata', $plan->get_error_message(), 10 * MINUTE_IN_SECONDS );
		nwcs_alt_redirect( 'alt-hata' );
	}

	set_site_transient( $key, $plan, HOUR_IN_SECONDS );
	nwcs_alt_redirect( 'alt-onizleme' );
}

add_action( 'admin_post_nwcs_alt_apply', 'nwcs_handle_alt_apply' );
function nwcs_handle_alt_apply(): void {
	nwcs_sync_guard( 'nwcs_alt_apply' );

	$key  = NWCS_ALT_PLAN . get_current_user_id();
	$plan = get_site_transient( $key );

	if ( ! is_array( $plan ) || (int) ( $_POST['plan'] ?? 0 ) !== (int) $plan['time'] ) {
		set_site_transient( $key . '_hata', 'Önizlemenin süresi doldu ya da başka bir dosya yüklendi. Dosyayı yeniden yükleyin.', 10 * MINUTE_IN_SECONDS );
		nwcs_alt_redirect( 'alt-hata' );
	}

	// Once onizleme silinir: iki kez basilan "Uygula" ikinci kez yazmaz.
	if ( ! delete_site_transient( $key ) ) {
		nwcs_alt_redirect( 'alt-uygulandi' );
	}

	nwcs_alt_apply( $plan );
	nwcs_alt_redirect( 'alt-uygulandi' );
}

add_action( 'admin_post_nwcs_alt_cancel', 'nwcs_handle_alt_cancel' );
function nwcs_handle_alt_cancel(): void {
	nwcs_sync_guard( 'nwcs_alt_cancel' );
	delete_site_transient( NWCS_ALT_PLAN . get_current_user_id() );
	wp_safe_redirect( nwcs_media_url() );
	exit;
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

/**
 * Medya Havuzu'nun ust kismi: hata, uygulama sonucu ya da onizleme.
 * Onizleme cizildiyse true (sayfanin geri kalani cizilmez).
 */
function nwcs_render_alt_step(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gosterim.
	$step = isset( $_GET['adim'] ) ? sanitize_key( wp_unslash( $_GET['adim'] ) ) : '';
	$key  = NWCS_ALT_PLAN . get_current_user_id();

	if ( 'alt-hata' === $step ) {
		$message = get_site_transient( $key . '_hata' );
		delete_site_transient( $key . '_hata' );

		if ( is_string( $message ) ) {
			echo '<div id="nwcs-excel-result" class="nwcs-sync nwcs-sync--error" role="alert" tabindex="-1"><h2 class="nwcs-sync__title">Excel yüklenemedi</h2><p>' . esc_html( $message ) . '</p><p class="nwcs-sync__muted">Hiçbir alt metin değişmedi.</p></div>';
		}

		return false;
	}

	if ( 'alt-uygulandi' === $step ) {
		$last = nwcs_history_latest( NWCS_ALT_KIND );

		if ( is_array( $last ) ) {
			$counts = (array) $last['counts'];
			echo '<div id="nwcs-excel-result" class="nwcs-sync nwcs-sync--done" role="status" tabindex="-1"><h2 class="nwcs-sync__title">Alt metinler güncellendi</h2><p>'
				. esc_html( sprintf( '%d görselin alt metni değişti, %d görselin alt metni silindi.', (int) ( $counts['updated'] ?? 0 ), (int) ( $counts['cleared'] ?? 0 ) ) )
				. ' Yanlışlık varsa “Son işlemler” kutusundan geri alabilirsiniz.</p>';

			if ( ! empty( $last['skipped'] ) ) {
				echo '<p class="nwcs-sync__warn">' . esc_html( 'Önizlemeden sonra değiştiği ya da silindiği için atlananlar: ' . implode( ', ', (array) $last['skipped'] ) . '.' ) . '</p>';
			}

			echo '</div>';
		}

		return false;
	}

	if ( 'alt-onizleme' === $step ) {
		$plan = get_site_transient( $key );

		if ( is_array( $plan ) ) {
			nwcs_render_alt_preview( $plan );

			return true;
		}
	}

	return false;
}

/**
 * Sag sutundaki kart: indir ve yukle.
 */
function nwcs_render_alt_card(): void {
	$categories = nwcs_pool_categories();
	$images     = nwcs_alt_image_ids();
	$counts     = nwcs_alt_category_counts( $images );
	$all        = count( $images );
	$empty      = count( nwcs_alt_image_ids( true ) );
	?>
	<div class="nwcs-pool__card nwcs-altx" id="nwcs-alt-excel">
		<h2 class="nwcs-pool__title">Alt metinleri Excel ile düzenleyin</h2>
		<p class="nwcs-hint">Çok sayıda görselin alt metnini tek dosyada yazın. Önce neyin değişeceği gösterilir.</p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nwcs_alt_download" />
			<?php wp_nonce_field( 'nwcs_alt_download', '_wpnonce', false ); ?>
			<label class="nwcs-sublabel" for="nwcs-alt-scope">Hangi görseller?</label>
			<div class="nwcs-excel__row">
				<select class="nwcs-input" id="nwcs-alt-scope" name="kapsam">
					<option value="tumu">Tümü (<?php echo (int) $all; ?>)</option>
					<option value="bos">Alt metni boş olanlar (<?php echo (int) $empty; ?>)</option>
					<?php if ( $categories ) : ?>
						<optgroup label="Kategorinin ürün görselleri">
							<?php foreach ( $categories as $slug => $term ) : ?>
								<option value="<?php echo esc_attr( 'kategori:' . $slug ); ?>"><?php echo esc_html( $term['name'] ); ?> (<?php echo (int) ( $counts[ $slug ] ?? 0 ); ?>)</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				</select>
				<button type="submit" class="button">Excel indir</button>
			</div>
		</form>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nwcs_alt_upload" />
			<?php wp_nonce_field( 'nwcs_alt_upload' ); ?>
			<label class="nwcs-sublabel" for="nwcs-alt-file">Düzenlediğiniz dosya (.xlsx)</label>
			<div class="nwcs-excel__row">
				<input class="nwcs-file" type="file" id="nwcs-alt-file" name="dosya" accept=".xlsx" required />
				<button type="submit" class="button button-primary">Yükle</button>
			</div>
			<p class="nwcs-hint nwcs-altx__note">Yalnızca ALT METİN sütununu değiştirin. Boş bıraktığınız hücre o görselin alt metnini siler.</p>
		</form>
	</div>
	<?php
}

function nwcs_render_alt_preview( array $plan ): void {
	$nothing = ! $plan['update'] && ! $plan['clear'];
	$cleared = count( $plan['clear'] );
	?>
	<section class="nwcs-sync nwcs-altx-preview" id="nwcs-excel-result" tabindex="-1" aria-labelledby="nwcs-alt-title">
		<header class="nwcs-sync__head">
			<h2 class="nwcs-sync__title" id="nwcs-alt-title">Önizleme: görsel alt metinleri (<?php echo esc_html( $plan['scope'] ); ?>)</h2>
			<p class="nwcs-sync__muted"><?php echo esc_html( $plan['file'] ); ?></p>
			<p class="nwcs-sync__lead">Henüz hiçbir şey değişmedi. Aşağıyı kontrol edin, doğruysa <strong>Değişiklikleri uygula</strong>’ya basın.</p>
			<?php $order = nwcs_history_order_note(); ?>
			<?php if ( '' !== $order ) : ?>
				<p class="nwcs-sync__muted"><?php echo esc_html( $order ); ?></p>
			<?php endif; ?>
		</header>

		<ul class="nwcs-sync__sum">
			<li class="nwcs-sync__chip nwcs-sync__chip--update"><b><?php echo count( $plan['update'] ); ?></b> değişecek</li>
			<li class="nwcs-sync__chip nwcs-sync__chip--trash"><b><?php echo (int) $cleared; ?></b> silinecek</li>
			<li class="nwcs-sync__chip nwcs-sync__chip--error"><b><?php echo count( $plan['errors'] ); ?></b> satırda hata</li>
			<li class="nwcs-sync__chip"><b><?php echo (int) $plan['same']; ?></b> değişmedi</li>
		</ul>

		<?php if ( $plan['errors'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--error">
				<h3>Yüklenmeyecek satırlar (<?php echo count( $plan['errors'] ); ?>)</h3>
				<p class="nwcs-sync__muted">Bu satırlara dokunulmaz; diğerleri uygulanabilir.</p>
				<ul class="nwcs-sync__list">
					<?php foreach ( $plan['errors'] as $error ) : ?>
						<li>
							<span class="nwcs-sync__line"><?php echo (int) $error['line']; ?>. satır</span>
							<?php if ( '' !== (string) $error['name'] ) : ?>
								<strong><?php echo esc_html( $error['name'] ); ?></strong>
							<?php endif; ?>
							<span><?php echo esc_html( $error['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $plan['clear'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--trash">
				<h3><?php echo esc_html( sprintf( '%d görselin alt metni silinecek', $cleared ) ); ?></h3>
				<p class="nwcs-sync__muted">Bu görsellerin ALT METİN hücresi boş. Bilerek boşalttıysanız sorun yok; yanlışlıkla boş kaldıysa dosyayı düzeltip yeniden yükleyin.</p>
				<?php nwcs_render_alt_table( $plan['clear'], false ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $plan['update'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--update">
				<h3><?php echo esc_html( sprintf( '%d görselin alt metni değişecek', count( $plan['update'] ) ) ); ?></h3>
				<?php nwcs_render_alt_table( $plan['update'], true ); ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-sync__form">
			<input type="hidden" name="action" value="nwcs_alt_apply" />
			<input type="hidden" name="plan" value="<?php echo esc_attr( (string) $plan['time'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_alt_apply' ); ?>
			<div class="nwcs-sync__actions">
				<?php if ( ! $nothing ) : ?>
					<button type="submit" class="button button-primary button-hero">Değişiklikleri uygula</button>
				<?php else : ?>
					<p class="nwcs-sync__muted">Uygulanacak bir değişiklik yok: dosyadaki alt metinler şimdikilerle aynı.</p>
				<?php endif; ?>
				<button type="submit" class="button" form="nwcs-alt-cancel">Vazgeç</button>
			</div>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-alt-cancel" hidden>
			<input type="hidden" name="action" value="nwcs_alt_cancel" />
			<?php wp_nonce_field( 'nwcs_alt_cancel' ); ?>
		</form>
	</section>
	<?php
}

/**
 * Onizleme tablosu: gorsel | simdi | (Excel'deki).
 */
function nwcs_render_alt_table( array $items, bool $with_new ): void {
	?>
	<div class="nwcs-altx__scroll">
		<table class="nwcs-sync__diff nwcs-altx__table">
			<thead>
				<tr>
					<th scope="col">Görsel</th>
					<th scope="col">Şimdiki alt metin</th>
					<?php if ( $with_new ) : ?>
						<th scope="col">Excel’deki</th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<th scope="row">
							<span class="nwcs-altx__pic">
								<?php if ( '' !== (string) $item['thumb'] ) : ?>
									<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" loading="lazy" width="40" height="40" />
								<?php endif; ?>
								<span>
									<?php echo esc_html( $item['file'] ); ?>
									<?php if ( '' !== (string) $item['products'] ) : ?>
										<small><?php echo esc_html( $item['products'] ); ?></small>
									<?php endif; ?>
								</span>
							</span>
						</th>
						<td data-label="Şimdi"><?php echo '' !== (string) $item['old'] ? esc_html( $item['old'] ) : '<em class="nwcs-sync__none">boş</em>'; ?></td>
						<?php if ( $with_new ) : ?>
							<td data-label="Excel’deki"><?php echo esc_html( $item['new'] ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
