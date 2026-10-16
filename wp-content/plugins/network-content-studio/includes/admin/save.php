<?php
/**
 * Panel yazma islemleri.
 *
 * Guvenlik zinciri (her yol icin ayni):
 *   yetki -> nonce -> hedef sitenin ag icinde ve manifestli oldugunun
 *   dogrulanmasi -> switch_to_blog -> tur bazli temizleme -> tek option
 *   yazimi -> restore_current_blog
 *
 * Yalnizca manifestte tanimli alanlar yazilir; fazlalik POST anahtarlari
 * sessizce yok sayilir.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ */
/* Ortak cekirdek                                                       */
/* ------------------------------------------------------------------ */

/**
 * Hedef siteyi ve bileseni dogrular; gecerliyse manifesti dondurur.
 */
function nwcs_validate_target( int $blog_id, string $page_key, string $component_key ) {
	$sites = nwcs_editable_sites();

	if ( ! isset( $sites[ $blog_id ] ) ) {
		return new WP_Error( 'nwcs_site', 'Seçilen site bulunamadı. Paneli yenileyip siteyi yeniden seçin.' );
	}

	$manifest = $sites[ $blog_id ]['manifest'];

	if ( ! isset( $manifest['pages'][ $page_key ] ) ) {
		return new WP_Error( 'nwcs_page', 'Seçilen sayfa bu sitede bulunamadı. Paneli yenileyip sayfayı yeniden seçin.' );
	}

	if ( '' !== $component_key && ! isset( $manifest['pages'][ $page_key ]['components'][ $component_key ] ) ) {
		return new WP_Error( 'nwcs_component', 'Seçilen bölüm bulunamadı. Paneli yenileyip bölümü yeniden seçin.' );
	}

	return $manifest;
}

/**
 * Bir bilesenin alanlarini secili siteye yazar.
 *
 * @return array{fields:int, media:int, errors:string[]}
 */
function nwcs_save_component( int $blog_id, array $manifest, string $page_key, string $component_key, array $posted ): array {
	$fields        = $manifest['pages'][ $page_key ]['components'][ $component_key ]['fields'];
	$media_updates = 0;

	switch_to_blog( $blog_id );

	$data = nwcs_get_all();

	foreach ( $fields as $field_key => $definition ) {
		$type = $definition['type'] ?? 'text';

		// Urun secimi nwcs_content'e degil, sitenin urun ayarlarina yazilir.
		if ( 'products' === $type ) {
			$category = trim( (string) ( $definition['category'] ?? '' ) );

			// Kategori sayfasindaki liste yalnizca o kategoriyi kaydeder.
			nwcs_save_site_product_settings(
				'' === $category
					? nwcs_posted_products()
					: nwcs_merge_scoped_products( nwcs_site_product_settings(), nwcs_posted_products(), $category )
			);

			continue;
		}

		if ( 'repeater' === $type ) {
			$value = nwcs_collect_repeater( $field_key, $definition, $posted[ $field_key ] ?? array(), $media_updates );
		} elseif ( 'image' === $type ) {
			$value = nwcs_collect_image( $field_key, (int) ( $posted[ $field_key ] ?? 0 ), $media_updates );
		} else {
			$value = $posted[ $field_key ] ?? '';
		}

		$data[ $page_key ][ $component_key ][ $field_key ] = nwcs_sanitize_value( $value, $definition );
	}

	update_option( NWCS_OPTION_CONTENT, $data );

	restore_current_blog();

	return array(
		'fields' => count( $fields ),
		'media'  => $media_updates,
		'errors' => nwcs_add_save_error( '' ),
	);
}

/**
 * POST'tan gelen ham urun secimi (temizleme nwcs_save_site_product_settings icinde).
 */
function nwcs_posted_products(): array {
	$posted = nwcs_posted_group( 'products' );

	return array(
		'mode'      => $posted['mode'] ?? 'all',
		'selected'  => $posted['selected'] ?? array(),
		'overrides' => $posted['overrides'] ?? array(),
	);
}

/**
 * POST'tan gelen ham alan dizisi (temizleme asagida tur bazli yapilir).
 */
function nwcs_posted_fields(): array {
	return nwcs_posted_group( 'fields' );
}

/**
 * fields / products dizisini POST'tan okur. Panel betigi bunlari tek bir
 * JSON alaninda gonderir (<ad>_json): yuzlerce urun satiri sunucunun alan
 * sayisi sinirini (max_input_vars) asip secimleri yarida kesmesin.
 * JavaScript kapaliyken klasik dizi alanlari okunur.
 */
function nwcs_posted_group( string $name ): array {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- tur bazli temizleme kaydedicide.
	if ( isset( $_POST[ $name . '_json' ] ) && is_string( $_POST[ $name . '_json' ] ) ) {
		$decoded = json_decode( wp_unslash( $_POST[ $name . '_json' ] ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	return isset( $_POST[ $name ] ) && is_array( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : array();
	// phpcs:enable
}

/* ------------------------------------------------------------------ */
/* JavaScript kapaliyken: klasik form gonderimi                         */
/* ------------------------------------------------------------------ */

add_action( 'admin_post_nwcs_save', 'nwcs_handle_save' );
function nwcs_handle_save(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$blog_id       = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
	$page_key      = isset( $_POST['content_page'] ) ? sanitize_key( wp_unslash( $_POST['content_page'] ) ) : '';
	$component_key = isset( $_POST['component'] ) ? sanitize_key( wp_unslash( $_POST['component'] ) ) : '';

	check_admin_referer( 'nwcs_save_' . $blog_id . '_' . $page_key . '_' . $component_key );

	$manifest = nwcs_validate_target( $blog_id, $page_key, $component_key );

	if ( is_wp_error( $manifest ) ) {
		nwcs_redirect_error( $blog_id, $page_key, $component_key, $manifest->get_error_message() );
	}

	$result = nwcs_save_component( $blog_id, $manifest, $page_key, $component_key, nwcs_posted_fields() );

	$args = array(
		'nwcs_saved' => $result['fields'],
		'nwcs_media' => $result['media'],
	);

	if ( $result['errors'] ) {
		$args['nwcs_error'] = rawurlencode( implode( ' ', $result['errors'] ) );
	}

	wp_safe_redirect( add_query_arg( $args, nwcs_panel_url( $blog_id, $page_key, $component_key ) ) );
	exit;
}

/* ------------------------------------------------------------------ */
/* JavaScript acikken: AJAX uclari                                      */
/* ------------------------------------------------------------------ */

/**
 * Bilesenin duzenleme formunu dondurur.
 */
add_action( 'wp_ajax_nwcs_editor', 'nwcs_ajax_editor' );
function nwcs_ajax_editor(): void {
	check_ajax_referer( 'nwcs_panel', 'nonce' );

	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), 403 );
	}

	$blog_id       = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
	$page_key      = isset( $_POST['content_page'] ) ? sanitize_key( wp_unslash( $_POST['content_page'] ) ) : '';
	$component_key = isset( $_POST['component'] ) ? sanitize_key( wp_unslash( $_POST['component'] ) ) : '';

	$manifest = nwcs_validate_target( $blog_id, $page_key, $component_key );

	if ( is_wp_error( $manifest ) ) {
		wp_send_json_error( array( 'message' => $manifest->get_error_message() ), 400 );
	}

	ob_start();
	nwcs_render_editor_form( $blog_id, $manifest, $page_key, $component_key );
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}

/**
 * Alanlari kaydeder (dosya yuklemeleri dahil).
 */
add_action( 'wp_ajax_nwcs_save_ajax', 'nwcs_ajax_save' );
function nwcs_ajax_save(): void {
	check_ajax_referer( 'nwcs_panel', 'nonce' );

	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), 403 );
	}

	$blog_id       = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
	$page_key      = isset( $_POST['content_page'] ) ? sanitize_key( wp_unslash( $_POST['content_page'] ) ) : '';
	$component_key = isset( $_POST['component'] ) ? sanitize_key( wp_unslash( $_POST['component'] ) ) : '';

	$manifest = nwcs_validate_target( $blog_id, $page_key, $component_key );

	if ( is_wp_error( $manifest ) ) {
		wp_send_json_error( array( 'message' => $manifest->get_error_message() ), 400 );
	}

	$result = nwcs_save_component( $blog_id, $manifest, $page_key, $component_key, nwcs_posted_fields() );

	wp_send_json_success(
		array(
			'message' => $result['errors']
				? implode( ' ', $result['errors'] )
				: sprintf( '%d alan yayınlandı.', $result['fields'] ),
			'hasError' => (bool) $result['errors'],
		)
	);
}

/**
 * Ana sayfa bolum sirasini kaydeder.
 */
add_action( 'wp_ajax_nwcs_order', 'nwcs_ajax_order' );
function nwcs_ajax_order(): void {
	check_ajax_referer( 'nwcs_panel', 'nonce' );

	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), 403 );
	}

	$blog_id  = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
	$page_key = isset( $_POST['content_page'] ) ? sanitize_key( wp_unslash( $_POST['content_page'] ) ) : '';

	$manifest = nwcs_validate_target( $blog_id, $page_key, '' );

	if ( is_wp_error( $manifest ) ) {
		wp_send_json_error( array( 'message' => $manifest->get_error_message() ), 400 );
	}

	$posted = isset( $_POST['order'] ) && is_array( $_POST['order'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['order'] ) )
		: array();

	switch_to_blog( $blog_id );
	nwcs_set_section_order( $page_key, $posted, $manifest );
	$saved = nwcs_section_order( $page_key, null, $manifest );
	restore_current_blog();

	wp_send_json_success(
		array(
			'order'   => $saved,
			'message' => 'Bölüm sırası kaydedildi.',
		)
	);
}

/* ------------------------------------------------------------------ */
/* Alan toplayicilar                                                    */
/* ------------------------------------------------------------------ */

/**
 * Tekrarli alan satirlarini toplar: _sort'a gore siralar, gorselleri isler.
 *
 * Satir anahtarlari kullanici tarafindan belirlenmez; yalnizca siralama ve
 * alt alan degerleri kullanilir, cikti her zaman yeniden indekslenir.
 */
function nwcs_collect_repeater( string $field_key, array $definition, $posted_rows, int &$media_updates ): array {
	if ( ! is_array( $posted_rows ) ) {
		return array();
	}

	$sub_defs = $definition['fields'] ?? array();
	$rows     = array();

	foreach ( $posted_rows as $row_id => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$sort  = isset( $row['_sort'] ) ? (int) $row['_sort'] : count( $rows );
		$clean = array();

		foreach ( $sub_defs as $sub_key => $sub_def ) {
			if ( 'image' === ( $sub_def['type'] ?? '' ) ) {
				$clean[ $sub_key ] = nwcs_collect_image(
					nwcs_field_path( $field_key, (string) $row_id, $sub_key ),
					(int) ( $row[ $sub_key ] ?? 0 ),
					$media_updates
				);
			} else {
				$clean[ $sub_key ] = $row[ $sub_key ] ?? '';
			}
		}

		// Tamamen bos satirlar kaydedilmez (kullanici satir ekleyip doldurmazsa).
		$has_value = false;
		foreach ( $clean as $sub_value ) {
			if ( '' !== $sub_value && 0 !== $sub_value && '0' !== $sub_value ) {
				$has_value = true;
				break;
			}
		}

		if ( ! $has_value ) {
			continue;
		}

		$rows[] = array(
			'_sort' => $sort,
			'row'   => $clean,
		);
	}

	usort(
		$rows,
		static function ( array $a, array $b ): int {
			return $a['_sort'] <=> $b['_sort'];
		}
	);

	return array_column( $rows, 'row' );
}

/**
 * Gorsel alani: yeni dosya varsa yukler, yoksa secilen kaydi kullanir.
 * Alt metin her durumda medya kaydina yazilir.
 *
 * Aktif site baglami cagiran tarafindan (switch_to_blog) saglanir; boylece
 * yukleme dogru alt sitenin uploads klasorune gider.
 */
function nwcs_collect_image( string $path, int $selected_id, int &$media_updates ): int {
	$upload_key = 'img_upload__' . $path;
	$alt_key    = 'img_alt__' . $path;

	$attachment_id = $selected_id;

	if ( isset( $_FILES[ $upload_key ] ) && is_array( $_FILES[ $upload_key ] ) && UPLOAD_ERR_NO_FILE !== (int) ( $_FILES[ $upload_key ]['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploaded = media_handle_upload( $upload_key, 0 );

		if ( is_wp_error( $uploaded ) ) {
			// Yukleme basarisizsa mevcut secim korunur ve kullaniciya soylenir;
			// sahte basari gosterilmez.
			nwcs_add_save_error( 'Görsel yüklenemedi, eski görsel yerinde kaldı. JPG, PNG ya da WebP bir dosya seçip yeniden kaydedin. (Teknik: ' . $uploaded->get_error_message() . ')' );
		} else {
			$attachment_id = (int) $uploaded;
			++$media_updates;
		}
	}

	if ( $attachment_id > 0 && isset( $_POST[ $alt_key ] ) ) {
		$alt = nwcs_clean_text( wp_unslash( $_POST[ $alt_key ] ) );

		if ( $alt !== (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			++$media_updates;
		}
	}

	return $attachment_id;
}

/**
 * Kaydetme sirasinda olusan hatalari biriktirir (or. basarisiz gorsel yukleme).
 */
function nwcs_add_save_error( string $message ): array {
	static $errors = array();

	if ( '' !== $message ) {
		$errors[] = $message;
	}

	return $errors;
}

/**
 * Hata ile panele geri doner.
 */
function nwcs_redirect_error( int $blog_id, string $page_key, string $component_key, string $message ): void {
	wp_safe_redirect(
		add_query_arg(
			array( 'nwcs_error' => rawurlencode( $message ) ),
			nwcs_panel_url( $blog_id, $page_key, $component_key )
		)
	);
	exit;
}

/**
 * Kaydetme sonrasi bildirimleri (JavaScript kapali akis icin).
 */
function nwcs_render_notices(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim gosterimi.
	if ( isset( $_GET['nwcs_saved'] ) ) {
		$count   = absint( $_GET['nwcs_saved'] );
		$media   = isset( $_GET['nwcs_media'] ) ? absint( $_GET['nwcs_media'] ) : 0;
		$message = sprintf( '%d alan kaydedildi ve sitede yayınlandı.', $count );

		if ( $media ) {
			$message .= sprintf( ' %d görsel güncellendi.', $media );
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>Yayınlandı.</strong> %s</p></div>',
			esc_html( $message )
		);
	}

	if ( isset( $_GET['nwcs_error'] ) ) {
		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html( sanitize_text_field( wp_unslash( $_GET['nwcs_error'] ) ) )
		);
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
