<?php
/**
 * Urun videosu: urun basina tek, kisa MP4.
 *
 * Video havuz sitesinin medya kitapligindadir (fotograflarla ayni yer:
 * wp-content/uploads/<yil>/<ay>/). Urun yalnizca ek kimligini tutar
 * ('_nwcs_video'); urun verisinde 'video' anahtariyla gelir (bos dizi = yok).
 * Videoyu gosteren temalar manifestte 'product_video' der (bugun Kocist).
 *
 * Form ve kayit: includes/admin/pool-video.php.
 */

defined( 'ABSPATH' ) || exit;

/** Panelden yuklenebilecek en buyuk video (12 MB). */
const NWCS_VIDEO_MAX_BYTES = 12 * 1024 * 1024;

/**
 * Videonun sitelere giden hali. Havuz baglaminda cagrilmalidir.
 *
 * @return array{id:int, url:string, mime:string, size:int, length:int, width:int, height:int, name:string, title:string, date:string}|array{}
 */
function nwcs_video_by_id( int $attachment_id ): array {
	if ( $attachment_id <= 0 || 'video/mp4' !== get_post_mime_type( $attachment_id ) ) {
		return array();
	}

	$url = (string) wp_get_attachment_url( $attachment_id );

	if ( '' === $url ) {
		return array();
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$meta = is_array( $meta ) ? $meta : array();
	$file = (string) get_attached_file( $attachment_id );
	$size = (int) ( $meta['filesize'] ?? 0 );

	if ( ! $size && '' !== $file && file_exists( $file ) ) {
		$size = (int) filesize( $file );
	}

	return array(
		'id'     => $attachment_id,
		'url'    => $url,
		'mime'   => 'video/mp4',
		'size'   => $size,
		'length' => (int) ( $meta['length'] ?? 0 ),
		'width'  => (int) ( $meta['width'] ?? 0 ),
		'height' => (int) ( $meta['height'] ?? 0 ),
		'name'   => wp_basename( '' !== $file ? $file : $url ),
		'title'  => get_the_title( $attachment_id ),
		'date'   => (string) get_post_time( 'c', true, $attachment_id ),
	);
}

/**
 * Sitenin temasi urun videosunu gosteriyor mu (manifestte 'product_video').
 */
function nwcs_site_supports_product_video( int $blog_id ): bool {
	return ! empty( nwcs_manifest_for_blog( $blog_id )['product_video'] );
}

/**
 * Sure "0:22" biciminde; bilinmiyorsa bos.
 */
function nwcs_video_length_label( int $seconds ): string {
	return $seconds > 0 ? sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 ) : '';
}
