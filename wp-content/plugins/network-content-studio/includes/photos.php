<?php
/**
 * Fotograf Kutusu: dosya adindan urun kodu eslemesi, parti (yukleme) yonetimi,
 * uygulama ve geri alma.
 *
 * Kural: dosya adi urun koduyla baslar (W-KAM-400DUB-1.jpg). Ayrac serbesttir:
 * tire, alt cizgi, nokta, bosluk ve parantez ayni sayilir. Kodun ardindan gelen
 * ilk sayi galerideki siradir; sayisi olmayan dosya numaralilardan sonra gelir.
 *
 * Dosyalar dogrudan havuz sitesinin medya kitapligina "parti" isaretiyle
 * (_nwcs_photo_batch) yuklenir; gecici klasor yoktur. Onizleme (plan) her
 * gorunumde partideki eklerden ve havuzun o anki kodlarindan yeniden
 * hesaplanir; saklanmaz. Uygulama urunlerin galerisine (_nwcs_gallery) yazar
 * ve "Son işlemler" yiginina geri alinabilir bir kayit birakir.
 *
 * Ekranlar ve istekler: admin/pool-photos.php. Temalar bu dosyayi kullanmaz.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PHOTO_KIND      = 'fotograflar';
const NWCS_PHOTO_META      = '_nwcs_photo_batch';
const NWCS_PHOTO_MIN_INNER = 5; // Icerme eslesmesi icin en kisa kod.
const NWCS_PHOTO_RATIO     = 1280 / 1714; // Urun gorseli dikey cercevesi (genislik / yukseklik).

/* ====================================================================== *
 * Eslesme (saf yardimcilar)
 * ====================================================================== */

/**
 * Kod ya da ad parcasindan karsilastirma anahtari: nwcs_normalize_product_code
 * + alt cizgi ve nokta da tire olur, ardisik tireler teklenir.
 * "W_KAM_400DUB.1" ve "w-kam-400dub (1)" -> "W-KAM-400DUB-1".
 */
function nwcs_photo_code_key( string $code ): string {
	$key = nwcs_normalize_product_code( $code );
	$key = (string) preg_replace( '/[_.]/', '-', $key );
	$key = (string) preg_replace( '/-+/', '-', $key );

	return trim( $key, '-' );
}

/**
 * Dosya adinin anahtari: uzanti atilir, sonra nwcs_photo_code_key.
 */
function nwcs_photo_key( string $filename ): string {
	return nwcs_photo_code_key( (string) pathinfo( wp_basename( $filename ), PATHINFO_FILENAME ) );
}

/**
 * Eslesebilecek kodlar: anahtar => urun kimligi. Yalnizca yayindaki ve kodu
 * olan urunler; $trashed true ise cop kutusundakiler. Iki urunun kodu ayni
 * anahtara dusuyorsa (ornek "A_B" ve "A-B") anahtar -1 olur: dosya hicbirine
 * baglanmaz, onizleme nedenini yazar.
 *
 * @return array<string, int>
 */
function nwcs_photo_candidates( bool $trashed = false ): array {
	$codes = array();

	if ( $trashed ) {
		switch_to_blog( nwcs_pool_blog_id() );

		foreach ( nwcs_pool_trashed_ids() as $id ) {
			$codes[ $id ] = (string) get_post_meta( $id, '_nwcs_code', true );
		}

		restore_current_blog();
	} else {
		foreach ( nwcs_pool_products() as $id => $product ) {
			$codes[ (int) $id ] = (string) $product['code'];
		}
	}

	$out = array();

	foreach ( $codes as $id => $code ) {
		$key = nwcs_photo_code_key( $code );

		if ( '' === $key ) {
			continue;
		}

		$out[ $key ] = isset( $out[ $key ] ) && $out[ $key ] !== (int) $id ? -1 : (int) $id;
	}

	return $out;
}

/**
 * Dosya adini bir urun koduyla esler.
 *
 *  1. Onek: anahtar kodun kendisi ya da "KOD-" ile baslar; birden cok kod
 *     uyarsa en uzunu kazanir (W-KAM-400 kodu W-KAM-400DUB-1'i yakalamaz).
 *  2. Icerme (yedek): kod adin ortasinda "-KOD-" ya da sonunda "-KOD" olarak
 *     geciyor ve en az NWCS_PHOTO_MIN_INNER karakter; en uzunu kazanir
 *     ("woodpets-w-dog-v06-img010" -> W-DOG-V06).
 *
 * @param array<string, int> $candidates nwcs_photo_candidates()
 * @return array{key:string, id:int, code:string, tail:string, seq:int, via:string}
 */
function nwcs_photo_match( string $name, array $candidates ): array {
	$key = nwcs_photo_key( $name );
	$out = array( 'key' => $key, 'id' => 0, 'code' => '', 'tail' => '', 'seq' => PHP_INT_MAX, 'via' => '' );

	if ( '' === $key ) {
		return $out;
	}

	// 1) Onek: anahtarin tire sinirlarindaki her baslangic parcasi denenir.
	$found = '';

	if ( isset( $candidates[ $key ] ) ) {
		$found = $key;
	} else {
		for ( $pos = strrpos( $key, '-' ); false !== $pos && $pos > 0; $pos = strrpos( substr( $key, 0, $pos ), '-' ) ) {
			$prefix = substr( $key, 0, $pos );

			if ( isset( $candidates[ $prefix ] ) ) {
				$found = $prefix;
				break; // Sondan basa: ilk bulunan en uzunu.
			}
		}
	}

	if ( '' !== $found ) {
		$out['code'] = $found;
		$out['tail'] = (string) substr( $key, strlen( $found ) );
		$out['via']  = 'prefix';
	} else {
		// 2) Icerme.
		$padded = '-' . $key . '-';

		foreach ( $candidates as $code => $id ) {
			$code = (string) $code;

			if ( strlen( $code ) < NWCS_PHOTO_MIN_INNER || strlen( $code ) <= strlen( $found ) ) {
				continue;
			}

			if ( str_contains( $padded, '-' . $code . '-' ) ) {
				$found = $code;
			}
		}

		if ( '' !== $found ) {
			$at          = strpos( $padded, '-' . $found . '-' );
			$out['code'] = $found;
			$out['tail'] = rtrim( (string) substr( $padded, $at + strlen( $found ) + 1 ), '-' );
			$out['via']  = 'contains';
		}
	}

	if ( '' === $found ) {
		return $out;
	}

	$out['id']  = (int) $candidates[ $found ];
	$out['seq'] = nwcs_photo_seq( $out['tail'] );

	return $out;
}

/**
 * Kuyruktaki ilk sayi galerideki siradir ("-IMG010" -> 10, "-2-KOPYA" -> 2);
 * sayi yoksa PHP_INT_MAX (numaralilardan sonra).
 */
function nwcs_photo_seq( string $tail ): int {
	return preg_match( '/\d+/', $tail, $match ) ? (int) min( (float) $match[0], (float) ( PHP_INT_MAX - 1 ) ) : PHP_INT_MAX;
}

/* ====================================================================== *
 * Yukleme
 * ====================================================================== */

/**
 * Yuklenebilen turler (tarayicidaki accept ile ayni).
 *
 * @return array<string, string> uzanti => tur
 */
function nwcs_photo_upload_types(): array {
	return array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpe'  => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'heic' => 'image/heic',
		'heif' => 'image/heif',
	);
}

/**
 * Galeriye girebilen (tarayicilarin gosterebildigi) son turler.
 */
function nwcs_photo_web_types(): array {
	return array( 'image/jpeg', 'image/png', 'image/webp' );
}

/**
 * Tek dosyayi havuzun medya kitapligina parti isaretiyle yukler.
 * Panel yuklemesiyle ayni yol: media_handle_upload -> wp_handle_upload
 * filtresi (images.php) WebP'ye cevirir. Ileride wp_handle_sideload
 * kullanilirsa images.php'ye ayni filtrenin 'wp_handle_sideload' karsiligi
 * eklenmelidir.
 *
 * @param string $field $_FILES anahtari (tek dosya).
 * @return array{id:int, name:string, reason:string} reason bos degilse yuklenmedi.
 */
function nwcs_photo_store( string $field, string $batch, string $slug ): array {
	$file = $_FILES[ $field ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- cagiran denetledi.
	$name = is_array( $file ) ? nwcs_clean_text( (string) ( $file['name'] ?? '' ) ) : '';
	$fail = static fn( string $reason ): array => array( 'id' => 0, 'name' => $name, 'reason' => $reason );

	if ( ! is_array( $file ) || '' === $name ) {
		return $fail( 'dosya alınamadı; yeniden deneyin' );
	}

	$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
	$limit = size_format( wp_max_upload_size() );

	if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) ( $file['size'] ?? 0 ) > wp_max_upload_size() ) {
		return $fail( sprintf( 'çok büyük (sınır %s)', $limit ) );
	}

	if ( UPLOAD_ERR_OK !== $error ) {
		return $fail( 'yükleme yarıda kesildi; yeniden deneyin' );
	}

	$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

	if ( ! isset( nwcs_photo_upload_types()[ $ext ] ) ) {
		return $fail( 'görsel türü uygun değil (JPG, PNG, WebP ya da HEIC olmalı)' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	switch_to_blog( nwcs_pool_blog_id() );

	$id = media_handle_upload( $field, 0, array(), array( 'test_form' => false, 'mimes' => nwcs_photo_upload_types() ) );

	if ( is_wp_error( $id ) ) {
		restore_current_blog();

		// Cok siteli agin dosya boyutu siniri (Ağ Ayarları) metni Ingilizce gelir; sadelesir.
		$message = $id->get_error_message();

		return $fail( preg_match( '/too (big|large)|büyük/i', $message ) ? sprintf( 'çok büyük (sınır %s)', $limit ) : 'yüklenemedi; yeniden deneyin, sorun sürerse geliştiriciye haber verin (Teknik: ' . wp_strip_all_tags( $message ) . ')' );
	}

	$id   = (int) $id;
	$mime = (string) get_post_mime_type( $id );

	// Sunucu HEIC'i acamadiysa dosya HEIC kalir: tarayici gosteremez, galeriye girmez.
	if ( ! in_array( $mime, nwcs_photo_web_types(), true ) ) {
		wp_delete_attachment( $id, true );
		restore_current_blog();

		return $fail( in_array( $mime, array( 'image/heic', 'image/heif' ), true ) ? 'bu HEIC fotoğrafı açılamadı; fotoğrafı JPG olarak kaydedip yükleyin' : 'görsel türü uygun değil (JPG, PNG, WebP ya da HEIC olmalı)' );
	}

	update_post_meta( $id, NWCS_PHOTO_META, $batch );
	update_post_meta( $id, '_nwcs_photo_user', get_current_user_id() );
	update_post_meta( $id, '_nwcs_photo_name', wp_slash( $name ) );
	update_post_meta( $id, '_nwcs_photo_cat', $slug );

	restore_current_blog();

	return array( 'id' => $id, 'name' => $name, 'reason' => '' );
}

/**
 * Partideki ekler (yuklenme sirasiyla). Havuz baglaminda cagrilmaz; kendisi gecer.
 *
 * @return int[]
 */
function nwcs_photos_batch_ids( string $batch ): array {
	if ( '' === $batch ) {
		return array();
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => NWCS_PHOTO_META, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $batch, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	restore_current_blog();

	return array_map( 'intval', $ids );
}

/**
 * Partinin eklerini siler (vazgec, yeni parti, supurme).
 */
function nwcs_photos_delete_batch( string $batch ): int {
	$ids = nwcs_photos_batch_ids( $batch );

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $ids as $id ) {
		wp_delete_attachment( $id, true );
	}

	restore_current_blog();

	return count( $ids );
}

/**
 * 24 saatten eski, uygulanmamis parti eklerini siler.
 */
function nwcs_photos_sweep(): int {
	switch_to_blog( nwcs_pool_blog_id() );

	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - (int) apply_filters( 'nwcs_photos_sweep_age', DAY_IN_SECONDS ) ), 'column' => 'post_date_gmt' ) ),
			'meta_query'     => array( array( 'key' => NWCS_PHOTO_META, 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_attachment( (int) $id, true );
	}

	restore_current_blog();

	return count( $ids );
}

/* ====================================================================== *
 * Plan (onizleme; hicbir sey yazilmaz)
 * ====================================================================== */

/**
 * Partiden plan cikarir: hangi dosya hangi urune, hangi sirayla.
 *
 * @param array  $rejected Yuklenemeyen dosyalar [ [name, reason] ] (transient'tan).
 * @param int[]  $exclude  Onizlemede "çıkar" denen ek kimlikleri.
 * @return array{batch:string, slug:string, category:string, groups:array, unmatched:array, counts:array, notes:array}
 */
function nwcs_photos_plan( string $batch, string $slug, array $rejected = array(), array $exclude = array() ): array {
	$pool       = nwcs_pool_products();
	$candidates = nwcs_photo_candidates();
	$trashed    = null;
	$categories = nwcs_pool_categories();
	$exclude    = array_map( 'intval', $exclude );
	$groups     = array();
	$unmatched  = array();
	$names      = array();

	foreach ( $rejected as $row ) {
		$unmatched[] = array( 'id' => 0, 'name' => (string) ( $row[0] ?? '' ), 'reason' => (string) ( $row[1] ?? '' ), 'thumb' => '' );
	}

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( nwcs_photos_batch_ids( $batch ) as $id ) {
		$name  = (string) get_post_meta( $id, '_nwcs_photo_name', true );
		$name  = '' !== $name ? $name : wp_basename( (string) get_attached_file( $id ) );
		$meta  = wp_get_attachment_metadata( $id );
		$src   = wp_get_attachment_image_src( $id, 'medium' );
		$file  = array(
			'id'     => $id,
			'name'   => $name,
			'thumb'  => $src ? (string) $src[0] : '',
			'width'  => (int) ( $meta['width'] ?? 0 ),
			'height' => (int) ( $meta['height'] ?? 0 ),
			'seq'    => PHP_INT_MAX,
			'note'   => '',
		);

		$names[ strtolower( $name ) ] = ( $names[ strtolower( $name ) ] ?? 0 ) + 1;

		if ( $file['width'] && $file['height'] ) {
			$ratio = $file['width'] / $file['height'];

			if ( $ratio > 1 ) {
				$file['note'] = 'yatay; sitede kendi oranında, kırpılmadan gösterilir';
			} elseif ( abs( $ratio - NWCS_PHOTO_RATIO ) / NWCS_PHOTO_RATIO > 0.10 ) {
				$file['note'] = 'oranı 1280×1714’ten farklı; sitede kırpılmadan gösterilir';
			}
		}

		if ( in_array( $id, $exclude, true ) ) {
			$unmatched[] = $file + array( 'reason' => 'önizlemede çıkarıldı' );
			continue;
		}

		$match = nwcs_photo_match( $name, $candidates );

		if ( $match['id'] < 0 ) {
			$unmatched[] = $file + array( 'reason' => sprintf( 'iki ürünün kodu dosya adında aynı görünüyor (%s); ürünlerden birinin kodunu değiştirin', $match['code'] ) );
			continue;
		}

		if ( ! $match['id'] || ! isset( $pool[ $match['id'] ] ) ) {
			$trashed  = $trashed ?? nwcs_photo_candidates( true );
			$in_trash = nwcs_photo_match( $name, $trashed );

			$unmatched[] = $file + array( 'reason' => $in_trash['id'] > 0 ? sprintf( 'çöp kutusundaki bir ürünün kodu (%s); ürünü çöp kutusundan geri getirip yeniden yükleyin', $in_trash['code'] ) : 'dosya adında bir ürün kodu bulunamadı' );
			continue;
		}

		$product        = $pool[ $match['id'] ];
		$file['seq']    = $match['seq'];
		$pid            = (int) $product['id'];

		if ( ! isset( $groups[ $pid ] ) ) {
			$other           = isset( $product['categories'][ $slug ] ) ? '' : implode( ', ', (array) $product['categories'] );
			$groups[ $pid ] = array(
				'id'       => $pid,
				'code'     => (string) $product['code'],
				'title'    => (string) $product['title'],
				'here'     => isset( $product['categories'][ $slug ] ),
				'other'    => '' !== $other ? $other : 'kategorisi yok',
				'existing' => array_map( static fn( array $image ): string => (string) $image['url'], (array) $product['images'] ),
				'files'    => array(),
				'notes'    => array(),
			);
		}

		$groups[ $pid ]['files'][] = $file;
	}

	restore_current_blog();

	// Urun icinde sira: dosyadaki numara, sonra ad (dogal siralama).
	foreach ( $groups as $pid => $group ) {
		usort(
			$group['files'],
			static fn( array $a, array $b ): int => $a['seq'] <=> $b['seq'] ?: strnatcasecmp( (string) pathinfo( $a['name'], PATHINFO_FILENAME ), (string) pathinfo( $b['name'], PATHINFO_FILENAME ) ) ?: strnatcasecmp( $a['name'], $b['name'] )
		);

		$seen = array();

		foreach ( $group['files'] as $file ) {
			if ( PHP_INT_MAX !== $file['seq'] && isset( $seen[ $file['seq'] ] ) ) {
				$group['notes'][ 'seq' . $file['seq'] ] = sprintf( 'iki dosyada da %d numarası var; ad sırasıyla dizildi', $file['seq'] );
			}

			if ( ( $names[ strtolower( $file['name'] ) ] ?? 0 ) > 1 ) {
				$group['notes'][ 'name' . strtolower( $file['name'] ) ] = sprintf( 'aynı adlı %d dosya: %s', $names[ strtolower( $file['name'] ) ], $file['name'] );
			}

			$seen[ $file['seq'] ] = true;
		}

		$group['notes'] = array_values( $group['notes'] );
		$groups[ $pid ] = $group;
	}

	// Once bu kategorinin urunleri (havuz sirasiyla), sonra baska kategoridekiler.
	$order   = array_flip( array_map( 'intval', array_keys( $pool ) ) );
	$sorted  = $groups;
	uasort( $sorted, static fn( array $a, array $b ): int => (int) $b['here'] <=> (int) $a['here'] ?: ( $order[ $a['id'] ] ?? 0 ) <=> ( $order[ $b['id'] ] ?? 0 ) );

	$photos = array_sum( array_map( static fn( array $group ): int => count( $group['files'] ), $sorted ) );

	return array(
		'batch'     => $batch,
		'slug'      => $slug,
		'category'  => (string) ( $categories[ $slug ]['name'] ?? $slug ),
		'groups'    => $sorted,
		'unmatched' => $unmatched,
		'counts'    => array(
			'photos'    => $photos,
			'products'  => count( $sorted ),
			'unmatched' => count( $unmatched ),
			'files'     => $photos + count( $unmatched ),
		),
	);
}

/* ====================================================================== *
 * Uygulama ve geri alma
 * ====================================================================== */

/**
 * Urunun su anki galerisi (okuma nwcs_pool_product_data ile ayni: bossa one
 * cikan gorsel). Havuz baglaminda cagrilir.
 *
 * @return int[]
 */
function nwcs_photo_current_gallery( int $id ): array {
	$gallery = get_post_meta( $id, '_nwcs_gallery', true );
	$gallery = is_array( $gallery ) ? array_values( array_filter( array_map( 'absint', $gallery ) ) ) : array();

	if ( ! $gallery ) {
		$thumb = (int) get_post_thumbnail_id( $id );

		if ( $thumb ) {
			$gallery = array( $thumb );
		}
	}

	return $gallery;
}

/**
 * Plani uygular. Kayit en basta yigina girer ve her urunden sonra guncellenir:
 * istek yarida kesilse de o ana kadar yapilanlar geri alinabilir.
 *
 * @param string $mode 'append' (sona ekle) | 'replace' (galeriyi degistir)
 * @return array Kayit.
 */
function nwcs_photos_apply( array $plan, string $mode ): array {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$mode   = 'replace' === $mode ? 'replace' : 'append';
	$record = array(
		'id'        => bin2hex( random_bytes( 6 ) ),
		'kind'      => NWCS_PHOTO_KIND,
		'batch'     => (string) $plan['batch'],
		'slug'      => (string) $plan['slug'],
		'category'  => (string) $plan['category'],
		'user'      => get_current_user_id(),
		'mode'      => $mode,
		'complete'  => false,
		'products'  => array(),
		'skipped'   => array(),
		'unmatched' => count( (array) $plan['unmatched'] ),
	);

	$save = static function () use ( &$record ): void {
		$record['time']   = time();
		$record['counts'] = array(
			'photos'   => array_sum( array_map( static fn( array $row ): int => count( (array) $row['added'] ), $record['products'] ) ),
			'products' => count( $record['products'] ),
		);

		nwcs_history_save( $record );
	};

	nwcs_history_push( $record + array( 'time' => time() ) );
	$save();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( (array) $plan['groups'] as $pid => $group ) {
		$pid   = (int) $pid;
		$added = array_map( static fn( array $file ): int => (int) $file['id'], (array) $group['files'] );
		$post  = nwcs_sync_product_post( $pid );

		// Onizlemeden sonra cope giden ya da silinen urune fotograf baglanmaz.
		if ( ! $post || 'publish' !== $post->post_status ) {
			foreach ( $added as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}

			$record['skipped'][] = (string) $group['title'];
			continue;
		}

		$before = nwcs_photo_current_gallery( $pid );

		// Kayit, yazmadan once: yarida kesilirse bu urun de geri alinabilsin.
		$record['products'][ $pid ] = array(
			'code'   => (string) $group['code'],
			'title'  => (string) $group['title'],
			'before' => $before,
			'added'  => $added,
		);
		$save();

		// Kilit: ekler partiden cikar (ikinci "Uygula" bu ekleri bulamaz).
		foreach ( $added as $attachment_id ) {
			delete_post_meta( $attachment_id, NWCS_PHOTO_META );
		}

		$gallery = 'append' === $mode ? array_values( array_unique( array_merge( $before, $added ) ) ) : $added;

		update_post_meta( $pid, '_nwcs_gallery', $gallery );
		set_post_thumbnail( $pid, (int) $gallery[0] );

		foreach ( $added as $attachment_id ) {
			$position = (int) array_search( $attachment_id, $gallery, true ) + 1;

			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => wp_slash( sprintf( '%s görsel %02d', (string) $group['code'], $position ) ),
				)
			);

			if ( '' === (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( (string) $group['title'] ) );
			}

			delete_post_meta( $attachment_id, '_nwcs_photo_user' );
			delete_post_meta( $attachment_id, '_nwcs_photo_name' );
			delete_post_meta( $attachment_id, '_nwcs_photo_cat' );
		}

		$save();

		/** Gelistirme denemeleri icin (yarida kesilme benzetimi). */
		do_action( 'nwcs_photos_applied_product', $pid, $record );
	}

	// Eslesmeyenler hicbir yere baglanmaz; silinir.
	foreach ( (array) $plan['unmatched'] as $file ) {
		if ( ! empty( $file['id'] ) && get_post_meta( (int) $file['id'], NWCS_PHOTO_META, true ) ) {
			wp_delete_attachment( (int) $file['id'], true );
		}
	}

	restore_current_blog();

	$record['complete'] = true;
	$save();

	nwcs_pool_flush_cache();

	return $record;
}

/**
 * Fotograf yuklemesini geri alir. Urun basina: galeri uygulamadaki hâlindeyse
 * eski galeri geri yazilir; sonradan elle degistiyse dokunulmaz (kept). Geri
 * alinan urunlere eklenen dosyalar baska bir urunde kullanilmiyorsa silinir.
 *
 * @return array{restored:int, kept:string[], deleted_photos:int}
 */
function nwcs_photos_undo( array $record ): array {
	$report  = array( 'restored' => 0, 'kept' => array(), 'deleted_photos' => 0 );
	$replace = 'replace' === ( $record['mode'] ?? 'append' );
	$drop    = array(); // geri alinan urunlerden cikan ekler

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( (array) ( $record['products'] ?? array() ) as $pid => $row ) {
		$pid    = (int) $pid;
		$before = array_map( 'intval', (array) ( $row['before'] ?? array() ) );
		$added  = array_map( 'intval', (array) ( $row['added'] ?? array() ) );
		$post   = nwcs_sync_product_post( $pid );

		if ( ! $post ) {
			$report['kept'][] = (string) ( $row['title'] ?? $pid );
			continue;
		}

		$current  = nwcs_photo_current_gallery( $pid );
		$expected = $replace ? $added : array_values( array_unique( array_merge( $before, $added ) ) );

		// Yarida kesilen uygulama: kayit yazildi ama galeri henuz degismedi.
		if ( $current === $before && $current !== $expected ) {
			$drop = array_merge( $drop, $added );
			continue;
		}

		if ( $current !== $expected ) {
			$report['kept'][] = (string) $post->post_title;
			continue;
		}

		update_post_meta( $pid, '_nwcs_gallery', $before );

		if ( $before ) {
			set_post_thumbnail( $pid, $before[0] );
		} else {
			delete_post_thumbnail( $pid );
		}

		$drop = array_merge( $drop, $added );
		++$report['restored'];
	}

	restore_current_blog();

	// Kullanim, galeriler geri yazildiktan sonra okunur (onbellek tazelenir).
	nwcs_pool_flush_cache();
	$usage = nwcs_media_usage();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( array_unique( $drop ) as $attachment_id ) {
		if ( ! isset( $usage[ $attachment_id ] ) && 'attachment' === get_post_type( $attachment_id ) && wp_delete_attachment( $attachment_id, true ) ) {
			++$report['deleted_photos'];
		}
	}

	restore_current_blog();

	nwcs_pool_flush_cache();

	return $report;
}
