<?php
/**
 * Ag Yonetimi -> Gelistirici -> WebP Donusturme.
 *
 * Yeni yuklemeler zaten WebP'ye cevriliyor (includes/images.php). Bu ekran,
 * o kuraldan once yuklenmis JPG/PNG (istege bagli HEIC) ekleri site site
 * WebP'ye cevirir:
 *   - asil dosya ayni piksel boyutunda WebP olarak yazilir (kalite: WordPress
 *     goruntu duzenleyicisinin WebP varsayilani; images.php de ayni ayari
 *     kullanir), ara boyutlar wp_generate_attachment_metadata ile yeniden
 *     uretilir (images.php'nin cikti bicimi filtresiyle WebP olur), srcset de
 *     WebP boyutlari gosterir;
 *   - eski JPG/PNG dosyalari (asil + ara boyutlar) diskte KALIR: yazilara gomulu
 *     ya da onbellekteki eski adresler 404 vermez;
 *   - eski dosya yolu, tur ve metadata "_nwcs_webp_original" meta'sinda saklanir;
 *     "Geri al" bunlari geri yazar ve yalnizca bu aracin urettigi WebP
 *     dosyalarini siler;
 *   - WebP asildan buyuk (ya da esit) cikarsa ek atlanir, sebebi
 *     "_nwcs_webp_skip" meta'sina yazilir. Donusturulemeyenler de (hata) ayni
 *     yolla isaretlenir ki parti dongusu ayni eke takilmasin.
 *   - Yazilarin icerigindeki (post_content) eski adresler yeni adreslerle
 *     degistirilir; degisen yazilar kaydedilir, geri almada eski adres doner.
 *     Icerik Studyosu gorselleri kimlikle (ek ID) tutar; orada degisecek adres yok.
 *
 * Isler kucuk AJAX partileriyle yurur (istek basina en fazla
 * NWCS_WEBP_BATCH_MAX ek ya da ~NWCS_WEBP_BATCH_SECONDS saniye): paylasimli
 * sunucuda zaman asimi olmaz. Durum veritabaninda (ek meta'lari) durdugu icin
 * sayfa kapansa da ayni dugme kaldigi yerden devam eder.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_WEBP_SLUG          = 'nwcs-webp';
const NWCS_WEBP_BACKUP_META   = '_nwcs_webp_original';
const NWCS_WEBP_SKIP_META     = '_nwcs_webp_skip';
const NWCS_WEBP_BATCH_MAX     = 10;
const NWCS_WEBP_BATCH_SECONDS = 15;

/**
 * Temanin manifestte bildirdigi ek gorsel boyutlari uretilir:
 * 'image_sizes' => [ ad => [ genislik, yukseklik, kirp ] ].
 *
 * Panel yuklemeleri ve bu aracin donusumu ag yonetiminde, sitenin temasi
 * yuklenmeden calisir; temanin add_image_size cagrisi orada gecmez. Boyut bu
 * yuzden manifestten (tema dosyasi, siteye gecmeden okunur) alinir.
 * Ornek: ahsapkasa-theme hero'sunun telefon kirpimlari.
 */
add_filter( 'intermediate_image_sizes_advanced', 'nwcs_manifest_image_sizes' );
function nwcs_manifest_image_sizes( $sizes ) {
	if ( ! is_array( $sizes ) || ! function_exists( 'nwcs_manifest_for_blog' ) ) {
		return $sizes;
	}

	$manifest = nwcs_manifest_for_blog( get_current_blog_id() );

	foreach ( (array) ( $manifest['image_sizes'] ?? array() ) as $name => $size ) {
		if ( is_string( $name ) && is_array( $size ) && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
			$sizes[ $name ] = array(
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
				'crop'   => ! empty( $size[2] ),
			);
		}
	}

	return $sizes;
}

add_action( 'network_admin_menu', 'nwcs_register_webp_menu', 33 );
function nwcs_register_webp_menu(): void {
	add_submenu_page( NWCS_DEV_MENU_SLUG, 'Görselleri WebP’ye Çevir', 'WebP Dönüştürme', NWCS_CAPABILITY, NWCS_WEBP_SLUG, 'nwcs_render_webp' );
}

/**
 * Cevrilecek turler. HEIC yalnizca istenirse (sunucu okuyamayabilir).
 *
 * @return string[]
 */
function nwcs_webp_source_types( bool $heic = false ): array {
	$types = array( 'image/jpeg', 'image/png' );

	return $heic ? array_merge( $types, array( 'image/heic', 'image/heif' ) ) : $types;
}

/**
 * Sitenin durumu (cagiran site baglaminda olmali).
 *
 * @return array{pending:int, converted:int, skipped:int, errors:int}
 */
function nwcs_webp_site_counts( bool $heic = false ): array {
	global $wpdb;

	$types = nwcs_webp_source_types( $heic );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
	$pending = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_mime_type IN ($in) AND s.meta_id IS NULL",
			array_merge( array( NWCS_WEBP_SKIP_META ), $types )
		)
	);

	$converted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s", NWCS_WEBP_BACKUP_META ) );

	$skips = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", NWCS_WEBP_SKIP_META ) );
	// phpcs:enable

	$skipped = 0;
	$errors  = 0;

	foreach ( $skips as $raw ) {
		$skip = maybe_unserialize( $raw );

		if ( is_array( $skip ) && 'larger' === ( $skip['reason'] ?? '' ) ) {
			++$skipped;
		} else {
			++$errors;
		}
	}

	return compact( 'pending', 'converted', 'skipped', 'errors' );
}

/**
 * Siradaki cevrilecek ekler.
 *
 * @return int[]
 */
function nwcs_webp_pending_ids( int $limit, bool $heic = false ): array {
	global $wpdb;

	$types = nwcs_webp_source_types( $heic );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
	return array_map(
		'intval',
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_mime_type IN ($in) AND s.meta_id IS NULL
				ORDER BY p.ID ASC LIMIT %d",
				array_merge( array( NWCS_WEBP_SKIP_META ), $types, array( $limit ) )
			)
		)
	);
	// phpcs:enable
}

/**
 * Bu aracla cevrilmis (geri alinabilir) ekler.
 *
 * @return int[]
 */
function nwcs_webp_converted_ids( int $limit ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d", NWCS_WEBP_BACKUP_META, $limit ) ) );
}

/**
 * Ekin turunu dogrudan yazar: wp_update_post kancalari (gecmis, onbellek
 * temizligi, degistirilme tarihi) gereksiz.
 */
function nwcs_webp_set_mime( int $id, string $mime ): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $mime ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}

/**
 * Yukleme klasorune gore goreli yol baska bir ekin dosyasi mi?
 */
function nwcs_webp_file_in_use( string $relative, int $except_id ): bool {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s AND post_id <> %d LIMIT 1", $relative, $except_id ) );
}

/**
 * Metadata'daki dosyalarin URL yolu (alan adi olmadan) => dosya adi.
 * Asil "full" anahtariyla, ara boyutlar kendi adlariyla.
 *
 * @return array<string, array{path:string, file:string, w:int, h:int}>
 */
function nwcs_webp_meta_paths( string $relative, array $meta ): array {
	$base = (string) wp_parse_url( wp_get_upload_dir()['baseurl'], PHP_URL_PATH );
	$dir  = dirname( $relative );
	$dir  = '.' === $dir ? '' : $dir . '/';
	$out  = array(
		'full' => array(
			'path' => $base . '/' . $relative,
			'file' => wp_basename( $relative ),
			'w'    => (int) ( $meta['width'] ?? 0 ),
			'h'    => (int) ( $meta['height'] ?? 0 ),
		),
	);

	foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
		if ( ! empty( $size['file'] ) ) {
			$out[ $name ] = array(
				'path' => $base . '/' . $dir . $size['file'],
				'file' => (string) $size['file'],
				'w'    => (int) ( $size['width'] ?? 0 ),
				'h'    => (int) ( $size['height'] ?? 0 ),
			);
		}
	}

	return $out;
}

/**
 * Metinde yol degisimi: yol ancak tam dosya adi olarak gectiginde degisir
 * (ardindan harf, rakam, nokta, tire, alt cizgi gelmez; "a.jpg.bak" degismez).
 *
 * @param array<string,string> $map eski yol => yeni yol.
 */
function nwcs_webp_replace_paths( string $text, array $map ): string {
	if ( ! $map ) {
		return $text;
	}

	$quoted = array_map( static fn( $path ) => preg_quote( $path, '~' ), array_keys( $map ) );

	// Uzun yol once: ayni onekli yollarda dogru eslesme.
	usort( $quoted, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

	return (string) preg_replace_callback(
		'~(?:' . implode( '|', $quoted ) . ')(?![\w.-])~',
		static fn( $match ) => $map[ $match[0] ] ?? $match[0],
		$text
	);
}

/**
 * Yazi iceriklerinde eski -> yeni adres degisimi. Ayni boyut adinda ve
 * olcusunde karsiligi olan dosyalar eslenir. post_content seriyalize degil;
 * duz metin degisimi guvenli.
 *
 * @param array<string,string> $map eski yol => yeni yol.
 * @return int[] degisen yazilar
 */
function nwcs_webp_replace_in_content( array $map ): array {
	global $wpdb;

	$map = array_filter( $map, static fn( $new, $old ) => '' !== $old && $old !== $new, ARRAY_FILTER_USE_BOTH );

	if ( ! $map ) {
		return array();
	}

	$changed = array();
	$like    = array();

	foreach ( array_keys( $map ) as $old ) {
		$like[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $old ) . '%' );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- parcalar yukarida hazirlandi.
	$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') AND (" . implode( ' OR ', $like ) . ')' );

	foreach ( $rows as $row ) {
		$content = nwcs_webp_replace_paths( (string) $row->post_content, $map );

		if ( $content !== $row->post_content ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => (int) $row->ID ) );
			clean_post_cache( (int) $row->ID );
			$changed[] = (int) $row->ID;
		}
	}

	return $changed;
}

/**
 * Bir eki WebP'ye cevirir. Cagiran site baglaminda olmali.
 *
 * @return array{status:string, message:string, old:int, new:int}
 *         status: converted | skipped | error
 */
function nwcs_webp_convert_attachment( int $id ): array {
	$result = array( 'status' => 'error', 'message' => '', 'old' => 0, 'new' => 0 );
	$fail   = static function ( string $message, string $reason = 'error' ) use ( $id, &$result ): array {
		update_post_meta( $id, NWCS_WEBP_SKIP_META, array( 'reason' => $reason, 'message' => $message, 'time' => time() ) );
		$result['status']  = 'larger' === $reason ? 'skipped' : 'error';
		$result['message'] = $message;

		return $result;
	};

	$relative = (string) get_post_meta( $id, '_wp_attached_file', true );
	$file     = get_attached_file( $id, true );
	$mime     = (string) get_post_mime_type( $id );
	$meta     = wp_get_attachment_metadata( $id );

	if ( ! $file || '' === $relative || ! file_exists( $file ) ) {
		return $fail( 'Görselin dosyası bulunamadı; görsel olduğu gibi bırakıldı.' );
	}

	if ( ! is_array( $meta ) ) {
		$meta = array();
	}

	$editor = wp_get_image_editor( $file );

	if ( is_wp_error( $editor ) ) {
		return $fail( 'Görsel açılamadı, dosya bozuk olabilir; görsel olduğu gibi bırakıldı. (Teknik: ' . $editor->get_error_message() . ')' );
	}

	// images.php ile ayni: yon EXIF'te ise once uygulanir (EXIF WebP'de duser).
	if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
		$editor->maybe_exif_rotate();
	}

	$dir    = dirname( $file );
	$name   = pathinfo( $file, PATHINFO_FILENAME ) . '.webp';
	$target = trailingslashit( $dir ) . $name;
	$rel    = ( '.' === dirname( $relative ) ? '' : dirname( $relative ) . '/' ) . $name;

	// Ayni adda baska bir ekin dosyasi varsa ustune yazilmaz; yetim (yarim kalmis
	// onceki deneme) ise yazilir.
	if ( file_exists( $target ) && nwcs_webp_file_in_use( $rel, $id ) ) {
		$name   = wp_unique_filename( $dir, $name );
		$target = trailingslashit( $dir ) . $name;
		$rel    = ( '.' === dirname( $relative ) ? '' : dirname( $relative ) . '/' ) . $name;
	}

	$saved = $editor->save( $target, 'image/webp' );

	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
		return $fail( 'WebP dosyası oluşturulamadı; görsel olduğu gibi bırakıldı.' . ( is_wp_error( $saved ) ? ' (Teknik: ' . $saved->get_error_message() . ')' : '' ) );
	}

	$old_size = (int) filesize( $file );
	$new_size = (int) filesize( $saved['path'] );

	$result['old'] = $old_size;
	$result['new'] = $new_size;

	if ( $new_size >= $old_size ) {
		wp_delete_file( $saved['path'] );

		return $fail( sprintf( 'WebP hâli daha büyük çıktı; görsel olduğu gibi bırakıldı (%s → %s).', size_format( $old_size, 1 ), size_format( $new_size, 1 ) ), 'larger' );
	}

	// Geri alma bilgisi, ek degismeden once.
	update_post_meta(
		$id,
		NWCS_WEBP_BACKUP_META,
		array(
			'file' => $relative,
			'mime' => $mime,
			'meta' => $meta,
			'webp' => $rel,
			'time' => time(),
		)
	);

	update_attached_file( $id, $saved['path'] );
	nwcs_webp_set_mime( $id, 'image/webp' );

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$new_meta = wp_generate_attachment_metadata( $id, $saved['path'] );

	if ( ! is_array( $new_meta ) || empty( $new_meta['width'] ) ) {
		// Yarim kalmasin: eski hale don.
		nwcs_webp_restore_attachment( $id );

		return $fail( 'Görselin küçük boyutları oluşturulamadı; görsel eski hâline döndürüldü.' );
	}

	// Alt metin gibi eklenti alanlari post meta'da; metadata'da yalnizca
	// goruntu bilgisi var. Eski image_meta (baslik, telif) korunur.
	if ( ! empty( $meta['image_meta'] ) && is_array( $meta['image_meta'] ) ) {
		$new_meta['image_meta'] = $meta['image_meta'];
	}

	wp_update_attachment_metadata( $id, $new_meta );

	// Yazi iceriklerindeki eski adresler: ayni boyut adi ve olcusu olanlar eslenir.
	$old_paths = nwcs_webp_meta_paths( $relative, $meta );
	$new_paths = nwcs_webp_meta_paths( $rel, $new_meta );
	$map       = array();

	foreach ( $old_paths as $key => $old ) {
		if ( isset( $new_paths[ $key ] ) && $new_paths[ $key ]['w'] === $old['w'] && $new_paths[ $key ]['h'] === $old['h'] ) {
			$map[ $old['path'] ] = $new_paths[ $key ]['path'];
		}
	}

	$posts = nwcs_webp_replace_in_content( $map );

	if ( $posts ) {
		$backup          = get_post_meta( $id, NWCS_WEBP_BACKUP_META, true );
		$backup['posts'] = $posts;
		$backup['map']   = $map;
		update_post_meta( $id, NWCS_WEBP_BACKUP_META, $backup );
	}

	$result['status']  = 'converted';
	$result['message'] = sprintf( '%s → %s', size_format( $old_size, 1 ), size_format( $new_size, 1 ) ) . ( $posts ? sprintf( ', %d yazıda adres güncellendi', count( $posts ) ) : '' );

	return $result;
}

/**
 * Donusumu geri alir: eski dosya yolu, tur ve metadata geri yazilir; bu aracin
 * urettigi ve eski metadata'da gecmeyen WebP dosyalari silinir.
 */
function nwcs_webp_restore_attachment( int $id ): bool {
	$backup = get_post_meta( $id, NWCS_WEBP_BACKUP_META, true );

	if ( ! is_array( $backup ) || empty( $backup['file'] ) ) {
		return false;
	}

	$current_rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
	$current_meta = wp_get_attachment_metadata( $id );
	$old_meta     = is_array( $backup['meta'] ?? null ) ? $backup['meta'] : array();

	// Yazilardaki adresler geri.
	if ( ! empty( $backup['posts'] ) && ! empty( $backup['map'] ) ) {
		global $wpdb;

		$reverse = array_flip( (array) $backup['map'] );

		foreach ( (array) $backup['posts'] as $post_id ) {
			$content = (string) get_post_field( 'post_content', (int) $post_id, 'raw' );
			$new     = nwcs_webp_replace_paths( $content, $reverse );

			if ( $new !== $content ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => (int) $post_id ) );
				clean_post_cache( (int) $post_id );
			}
		}
	}

	update_post_meta( $id, '_wp_attached_file', $backup['file'] );
	nwcs_webp_set_mime( $id, (string) ( $backup['mime'] ?? 'image/jpeg' ) );
	wp_update_attachment_metadata( $id, $old_meta );

	// Silinecekler: yeni asil + yeni ara boyutlar; eski metadata'da da gecen
	// (eski ara boyutlar zaten WebP uretilmisse) dosyalar kalir.
	$keep = array();

	foreach ( nwcs_webp_meta_paths( (string) $backup['file'], $old_meta ) as $item ) {
		$keep[ $item['file'] ] = true;
	}

	$uploads = wp_get_upload_dir()['basedir'];
	$dir     = trailingslashit( $uploads ) . ( '.' === dirname( $current_rel ) ? '' : dirname( $current_rel ) . '/' );

	if ( $current_rel && $current_rel !== $backup['file'] && is_array( $current_meta ) ) {
		foreach ( nwcs_webp_meta_paths( $current_rel, $current_meta ) as $item ) {
			if ( empty( $keep[ $item['file'] ] ) && str_ends_with( strtolower( $item['file'] ), '.webp' ) && ! nwcs_webp_file_in_use( ltrim( dirname( $current_rel ) . '/', './' ) . $item['file'], $id ) ) {
				wp_delete_file( $dir . $item['file'] );
			}
		}
	}

	delete_post_meta( $id, NWCS_WEBP_BACKUP_META );
	delete_post_meta( $id, NWCS_WEBP_SKIP_META );

	return true;
}

/**
 * Islem sonu: havuz/kategori onbellegi, sitenin sayfa onbellegi ve (tanimliysa)
 * Cloudflare.
 */
function nwcs_webp_flush( int $blog_id ): void {
	if ( function_exists( 'nwcs_pool_flush_cache' ) ) {
		nwcs_pool_flush_cache();
	}

	if ( has_action( 'litespeed_purge_all' ) ) {
		switch_to_blog( $blog_id );
		do_action( 'litespeed_purge_all' );
		restore_current_blog();
	}

	if ( function_exists( 'nwcs_purge_site_caches' ) ) {
		nwcs_purge_site_caches();
	}
}

/**
 * AJAX ortak giris: yetki, nonce, site.
 */
function nwcs_webp_ajax_blog(): int {
	if ( ! current_user_can( NWCS_CAPABILITY ) || ! check_ajax_referer( 'nwcs_webp', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok ya da oturumun süresi dolmuş olabilir; sayfayı yenileyip yeniden deneyin.' ), 403 );
	}

	$blog_id = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;

	if ( ! $blog_id || ! get_site( $blog_id ) ) {
		wp_send_json_error( array( 'message' => 'Site bulunamadı. Sayfayı yenileyip yeniden deneyin.' ), 400 );
	}

	return $blog_id;
}

add_action( 'wp_ajax_nwcs_webp_batch', 'nwcs_webp_ajax_batch' );
function nwcs_webp_ajax_batch(): void {
	$blog_id = nwcs_webp_ajax_blog();
	$mode    = isset( $_POST['mode'] ) && 'restore' === $_POST['mode'] ? 'restore' : 'convert'; // phpcs:ignore WordPress.Security.NonceVerification -- nwcs_webp_ajax_blog dogruladi.
	$heic    = ! empty( $_POST['heic'] ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( 'convert' === $mode && ! nwcs_image_webp_supported() ) {
		wp_send_json_error( array( 'message' => 'Bu panelde görseller WebP’ye çevrilemiyor; geliştiriciye haber verin. (Teknik: sunucunun görüntü kütüphanesi WebP yazamıyor)' ), 500 );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	wp_raise_memory_limit( 'image' );
	switch_to_blog( $blog_id );

	$start = microtime( true );
	$items = array();
	$done  = 0;

	while ( $done < NWCS_WEBP_BATCH_MAX && microtime( true ) - $start < NWCS_WEBP_BATCH_SECONDS ) {
		$ids = 'restore' === $mode ? nwcs_webp_converted_ids( 1 ) : nwcs_webp_pending_ids( 1, $heic );

		if ( ! $ids ) {
			break;
		}

		$id    = $ids[0];
		$title = wp_basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );

		if ( 'restore' === $mode ) {
			$ok      = nwcs_webp_restore_attachment( $id );
			$items[] = array( 'id' => $id, 'file' => $title, 'status' => $ok ? 'restored' : 'error', 'message' => $ok ? 'eski hâline döndü' : 'eski hâli kayıtlı değil, geri alınamadı' );

			if ( ! $ok ) {
				// Dongu ayni eke takilmasin.
				delete_post_meta( $id, NWCS_WEBP_BACKUP_META );
			}
		} else {
			try {
				$row = nwcs_webp_convert_attachment( $id );
			} catch ( Throwable $e ) {
				update_post_meta( $id, NWCS_WEBP_SKIP_META, array( 'reason' => 'error', 'message' => $e->getMessage(), 'time' => time() ) );
				$row = array( 'status' => 'error', 'message' => 'Görsel çevrilemedi; olduğu gibi bırakıldı. (Teknik: ' . $e->getMessage() . ')' );
			}

			$items[] = array( 'id' => $id, 'file' => $title, 'status' => $row['status'], 'message' => $row['message'] );
		}

		++$done;
	}

	if ( 'restore' === $mode && ! nwcs_webp_converted_ids( 1 ) ) {
		// Geri alma bitti: atlanan/hatali isaretleri de kalkar, yeniden denenebilir.
		delete_metadata( 'post', 0, NWCS_WEBP_SKIP_META, '', true );
	}

	$counts    = nwcs_webp_site_counts( $heic );
	$remaining = 'restore' === $mode ? $counts['converted'] : $counts['pending'];

	restore_current_blog();

	wp_send_json_success(
		array(
			'items'     => $items,
			'counts'    => $counts,
			'remaining' => $remaining,
		)
	);
}

add_action( 'wp_ajax_nwcs_webp_finish', 'nwcs_webp_ajax_finish' );
function nwcs_webp_ajax_finish(): void {
	$blog_id = nwcs_webp_ajax_blog();

	nwcs_webp_flush( $blog_id );

	wp_send_json_success( array( 'message' => 'Önbellekler temizlendi.' ) );
}

/**
 * Ekranda gosterilen siteler: ag sitelerinin hepsi (havuz sitesi dahil).
 *
 * @return array<int, string> blog_id => ad
 */
function nwcs_webp_sites(): array {
	$editable = nwcs_editable_sites();
	$out      = array();

	foreach ( get_sites( array( 'number' => 200, 'deleted' => 0 ) ) as $site ) {
		$blog_id = (int) $site->blog_id;

		if ( isset( $editable[ $blog_id ] ) ) {
			$out[ $blog_id ] = $editable[ $blog_id ]['label'];
		} else {
			$out[ $blog_id ] = (string) get_blog_option( $blog_id, 'blogname', '' ) . ( nwcs_pool_blog_id() === $blog_id ? ' (ürün havuzu)' : '' );
		}
	}

	return $out;
}

function nwcs_render_webp(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$supported = nwcs_image_webp_supported();
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-seo nwcs-webp">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Görselleri WebP’ye Çevir</h1>
			</div>
		</header>

		<section class="nwcs-pool__card">
			<p class="nwcs-seo__lead">
				Yeni yüklenen fotoğraflar zaten WebP’ye çevriliyor. Bu ekran, daha önce yüklenmiş JPG ve PNG görselleri site site WebP’ye çevirir.
				Görselin ölçüsü değişmez; WordPress’in küçük boyutları da WebP olarak yeniden üretilir.
				Eski JPG/PNG dosyaları silinmez; eski adresler çalışmaya devam eder. <strong>Geri al</strong> siteyi eski hâline döndürür.
				WebP dosyası eskisinden büyük çıkarsa o görsel atlanır.
			</p>

			<div class="notice notice-warning inline">
				<p><strong>Çalıştırmadan önce sitenin yedeğini alın (veritabanı ve <code>wp-content/uploads</code> klasörü).</strong>
				İşlem küçük parçalar hâlinde yürür; sayfayı kapatırsanız aynı düğmeyle kaldığı yerden devam eder.
				Bittiğinde site önbelleği (LiteSpeed, tanımlıysa Cloudflare) kendiliğinden temizlenir.</p>
			</div>

			<?php if ( ! $supported ) : ?>
				<div class="notice notice-error inline"><p><strong>Bu kurulumda görseller WebP’ye çevrilemiyor; geliştiriciye haber verin.</strong> (Teknik: sunucunun görüntü kütüphanesi WebP yazamıyor.)</p></div>
			<?php endif; ?>

			<p>
				<label><input type="checkbox" id="nwcs-webp-heic" /> iPhone HEIC dosyalarını da dahil et (sunucu okuyabiliyorsa)</label>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>Site</th>
						<th>Çevrilecek (JPG/PNG)</th>
						<th>Çevrildi</th>
						<th>Atlandı (WebP daha büyük)</th>
						<th>Hata</th>
						<th>İşlem</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( nwcs_webp_sites() as $blog_id => $label ) : ?>
						<?php
						switch_to_blog( (int) $blog_id );
						$counts = nwcs_webp_site_counts();
						$url    = home_url( '/' );
						restore_current_blog();
						?>
						<tr data-site="<?php echo (int) $blog_id; ?>">
							<td><strong><?php echo esc_html( $label ); ?></strong><br /><span class="description"><?php echo esc_html( untrailingslashit( $url ) ); ?></span></td>
							<td data-count="pending"><?php echo (int) $counts['pending']; ?></td>
							<td data-count="converted"><?php echo (int) $counts['converted']; ?></td>
							<td data-count="skipped"><?php echo (int) $counts['skipped']; ?></td>
							<td data-count="errors"><?php echo (int) $counts['errors']; ?></td>
							<td>
								<button type="button" class="button button-primary" data-webp="convert" <?php disabled( ! $supported ); ?>>Dönüştür</button>
								<button type="button" class="button" data-webp="restore">Geri al</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="nwcs-badge" id="nwcs-webp-status" hidden></p>
			<ol id="nwcs-webp-log" class="description" style="max-height:320px;overflow:auto"></ol>
		</section>
	</div>

	<script>
	( function () {
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce = <?php echo wp_json_encode( wp_create_nonce( 'nwcs_webp' ) ); ?>;
		const status = document.getElementById( 'nwcs-webp-status' );
		const log = document.getElementById( 'nwcs-webp-log' );
		const heic = document.getElementById( 'nwcs-webp-heic' );
		let busy = false;

		const post = async ( data ) => {
			const body = new FormData();
			Object.entries( Object.assign( { nonce }, data ) ).forEach( ( [ k, v ] ) => body.append( k, v ) );
			const response = await fetch( ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } );
			const json = await response.json().catch( () => null );
			if ( ! json || ! json.success ) {
				throw new Error( ( json && json.data && json.data.message ) || 'Yanıt alınamadı; bağlantı kesilmiş ya da oturumun süresi dolmuş olabilir. Gerekirse sayfayı yenileyin. (Teknik: HTTP ' + response.status + ')' );
			}
			return json.data;
		};

		const show = ( text, ok ) => {
			status.hidden = false;
			status.textContent = text;
			status.className = 'nwcs-badge ' + ( ok ? 'nwcs-badge--ok' : 'nwcs-badge--warn' );
		};

		const line = ( text ) => {
			const li = document.createElement( 'li' );
			li.textContent = text;
			log.appendChild( li );
			log.scrollTop = log.scrollHeight;
		};

		const labels = { converted: 'çevrildi', skipped: 'atlandı', error: 'hata', restored: 'geri alındı' };

		document.querySelectorAll( '[data-webp]' ).forEach( ( button ) => {
			button.addEventListener( 'click', async () => {
				if ( busy ) {
					return;
				}
				const row = button.closest( 'tr' );
				const site = row.dataset.site;
				const mode = button.dataset.webp;
				const name = row.querySelector( 'strong' ).textContent;

				if ( 'restore' === mode && ! window.confirm( name + ': bu araçla çevrilen görseller eski JPG/PNG hâline döndürülsün mü?' ) ) {
					return;
				}

				busy = true;
				document.querySelectorAll( '[data-webp]' ).forEach( ( b ) => { b.disabled = true; } );
				log.textContent = '';
				const sum = { converted: 0, skipped: 0, error: 0, restored: 0 };

				try {
					let remaining = 1;
					while ( remaining > 0 ) {
						const data = await post( { action: 'nwcs_webp_batch', site, mode, heic: heic.checked ? 1 : '' } );
						data.items.forEach( ( item ) => {
							sum[ item.status ] = ( sum[ item.status ] || 0 ) + 1;
							line( item.file + ': ' + ( labels[ item.status ] || item.status ) + ( item.message ? ' (' + item.message + ')' : '' ) );
						} );
						Object.entries( data.counts ).forEach( ( [ key, value ] ) => {
							const cell = row.querySelector( '[data-count="' + key + '"]' );
							if ( cell ) {
								cell.textContent = value;
							}
						} );
						remaining = data.items.length ? data.remaining : 0;
						show( name + ': ' + ( 'restore' === mode ? sum.restored + ' görsel geri alındı' : sum.converted + ' çevrildi, ' + sum.skipped + ' atlandı, ' + sum.error + ' hata' ) + ( remaining ? ' — ' + remaining + ' kaldı…' : '' ), true );
					}

					show( name + ': önbellek temizleniyor…', true );
					await post( { action: 'nwcs_webp_finish', site } );
					show(
						'restore' === mode
							? name + ': ' + sum.restored + ' görsel geri alındı' + ( sum.error ? ', ' + sum.error + ' hata' : '' ) + '. Önbellek temizlendi.'
							: name + ': ' + sum.converted + ' görsel çevrildi, ' + sum.skipped + ' atlandı (WebP daha büyük), ' + sum.error + ' hata. Önbellek temizlendi.',
						! sum.error
					);
				} catch ( err ) {
					show( name + ': durdu — ' + err.message + ' Düğmeye yeniden basarak kaldığı yerden devam edebilirsiniz.', false );
				}

				busy = false;
				document.querySelectorAll( '[data-webp]' ).forEach( ( b ) => { b.disabled = false; } );
			} );
		} );
	}() );
	</script>
	<?php
}
