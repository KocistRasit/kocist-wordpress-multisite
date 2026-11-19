<?php
/**
 * Katalog gorselleri: content/catalog-images.php listesindeki kaynak
 * gorselleri bir kez medya kutuphanesine indirir (sunucu tarafi,
 * media_sideload_image) ve slug -> ek kimligi eslemesini 'pc_catalog_images'
 * secenegine yazar. Panelde urun icin gorsel secilmisse o kazanir; bu esleme
 * yalnizca gorseli bos urunlerde devreye girer (pc_catalog_image).
 *
 * Calisma: yonetici panele girdiginde (admin_init), istek basina en fazla
 * PC_CATALOG_IMG_BATCH gorsel; bitince 'pc_catalog_images_done' = surum.
 * Indirme basarisiz olan adres bir sonraki girisde yeniden denenir (en fazla
 * 3 deneme). Veritabani tasimasi gerektirmez; cPanel Git deploy yeterli.
 */

defined( 'ABSPATH' ) || exit;

const PC_CATALOG_IMG_VERSION = '1';
const PC_CATALOG_IMG_BATCH   = 6;

/**
 * @return array<string, int> slug (ya da 'tip:<anahtar>') -> ek kimligi
 */
function pc_catalog_image_map(): array {
	$map = get_option( 'pc_catalog_images', array() );

	return is_array( $map ) ? array_map( 'intval', $map ) : array();
}

/**
 * Kaynak listesi (slug -> URL).
 */
function pc_catalog_image_sources(): array {
	$file = get_theme_file_path( 'content/catalog-images.php' );
	$list = file_exists( $file ) ? include $file : array();

	return is_array( $list ) ? array_filter( array_map( 'strval', $list ) ) : array();
}

add_action( 'admin_init', 'pc_catalog_images_maybe_fetch' );
function pc_catalog_images_maybe_fetch(): void {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}

	if ( PC_CATALOG_IMG_VERSION === (string) get_option( 'pc_catalog_images_done' ) || get_transient( 'pc_catalog_images_lock' ) ) {
		return;
	}

	set_transient( 'pc_catalog_images_lock', 1, 2 * MINUTE_IN_SECONDS );

	$sources = pc_catalog_image_sources();
	$map     = pc_catalog_image_map();
	$tries   = (array) get_option( 'pc_catalog_images_tries', array() );
	$by_url  = array();

	// Ayni URL daha once inmisse ek kaydini paylas.
	foreach ( $map as $slug => $id ) {
		if ( $id && isset( $sources[ $slug ] ) ) {
			$by_url[ $sources[ $slug ] ] = $id;
		}
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$done     = 0;
	$pending  = 0;

	foreach ( $sources as $slug => $url ) {
		if ( ! empty( $map[ $slug ] ) && get_post( (int) $map[ $slug ] ) ) {
			continue;
		}

		if ( ( $tries[ $slug ] ?? 0 ) >= 3 ) {
			continue;
		}

		if ( isset( $by_url[ $url ] ) ) {
			$map[ $slug ] = (int) $by_url[ $url ];
			continue;
		}

		if ( $done >= PC_CATALOG_IMG_BATCH ) {
			++$pending;
			continue;
		}

		++$done;
		$tries[ $slug ] = ( $tries[ $slug ] ?? 0 ) + 1;

		$title = pc_catalog_image_title( $slug );
		$id    = media_sideload_image( $url, 0, $title, 'id' );

		if ( is_wp_error( $id ) || ! $id ) {
			++$pending;
			continue;
		}

		update_post_meta( (int) $id, '_wp_attachment_image_alt', $title );
		wp_update_post( array( 'ID' => (int) $id, 'post_title' => $title, 'post_excerpt' => 'Kaynak: Sertel Vida Metal ürün kataloğu (KOÇİST satış bayisi).' ) );

		$map[ $slug ]  = (int) $id;
		$by_url[ $url ] = (int) $id;
	}

	update_option( 'pc_catalog_images', $map, false );
	update_option( 'pc_catalog_images_tries', $tries, false );

	if ( 0 === $pending ) {
		update_option( 'pc_catalog_images_done', PC_CATALOG_IMG_VERSION, false );
	}

	delete_transient( 'pc_catalog_images_lock' );
}

/**
 * Gorsel basligi / alt metni: urun adi; tip icin tip adi.
 */
function pc_catalog_image_title( string $slug ): string {
	if ( str_starts_with( $slug, 'tip:' ) ) {
		$key = substr( $slug, 4 );

		return (string) ( pc_manifest()['pages'][ $key ]['components']['card']['fields']['name']['default'] ?? $key );
	}

	foreach ( pc_manifest()['pages']['products']['components']['catalog']['fields']['items']['default'] ?? array() as $row ) {
		if ( ( $row['slug'] ?? '' ) === $slug ) {
			return (string) ( $row['title'] ?? $slug );
		}
	}

	return $slug;
}

/**
 * Indirilen gorsel (varsa) nwcs_image_by_id bicimiyle.
 */
function pc_catalog_sideloaded( string $slug, string $size = 'large' ): array {
	$id = pc_catalog_image_map()[ $slug ] ?? 0;

	return $id ? nwcs_image_by_id( (int) $id, $size ) : array( 'id' => 0, 'url' => '', 'alt' => '' );
}
