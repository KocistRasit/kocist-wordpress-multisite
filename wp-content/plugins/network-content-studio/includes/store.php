<?php
/**
 * Icerik veri katmani.
 *
 * Tum duzenlenebilir degerler, ilgili alt sitenin kendi option kaydinda tutulur:
 *   nwcs_content       -> [ sayfa ][ bilesen ][ alan ] = deger
 *   nwcs_section_order -> [ sayfa ] = [ bolum anahtarlari ]
 *
 * Boylece degerler sayfa yenilense de container yeniden baslasa da korunur ve
 * bir sitenin verisi digerini etkilemez.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_OPTION_CONTENT = 'nwcs_content';
const NWCS_OPTION_ORDER   = 'nwcs_section_order';

/**
 * Aktif (ya da verilen) sitenin tum kayitli icerigi.
 */
function nwcs_get_all( ?int $blog_id = null ): array {
	$data = null === $blog_id
		? get_option( NWCS_OPTION_CONTENT, array() )
		: get_blog_option( $blog_id, NWCS_OPTION_CONTENT, array() );

	return is_array( $data ) ? $data : array();
}

/**
 * Ham kayitli deger; yoksa null.
 */
function nwcs_raw( string $page, string $component, string $field, ?int $blog_id = null ) {
	$data = nwcs_get_all( $blog_id );

	return $data[ $page ][ $component ][ $field ] ?? null;
}

/**
 * Tema tarafinda kullanilan okuyucu: kayitli deger yoksa manifest varsayilani.
 */
function nwcs_field( string $page, string $component, string $field, $fallback = null ) {
	$value = nwcs_raw( $page, $component, $field );

	if ( null !== $value && '' !== $value ) {
		return $value;
	}

	if ( null !== $fallback ) {
		return $fallback;
	}

	return nwcs_field_default( nwcs_manifest(), $page, $component, $field );
}

/**
 * Tekrarli bilesen satirlari (repeater).
 */
function nwcs_rows( string $page, string $component, string $field ): array {
	$value = nwcs_field( $page, $component, $field, null );

	return is_array( $value ) ? array_values( $value ) : array();
}

/**
 * Gorsel alani: ek kaydi (attachment) bilgisiyle birlikte doner.
 */
function nwcs_image( string $page, string $component, string $field, string $size = 'large' ): array {
	$attachment_id = (int) nwcs_field( $page, $component, $field, 0 );

	return nwcs_image_by_id( $attachment_id, $size );
}

/**
 * Ek kimliginden gorsel bilgisi. Alt metin WordPress medya kaydindan okunur.
 */
function nwcs_image_by_id( int $attachment_id, string $size = 'large' ): array {
	if ( $attachment_id <= 0 ) {
		return array(
			'id'  => 0,
			'url' => '',
			'alt' => '',
		);
	}

	$src = wp_get_attachment_image_src( $attachment_id, $size );

	return array(
		'id'  => $attachment_id,
		'url' => $src ? $src[0] : '',
		'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Metin temizleme: etiketleri ve kontrol karakterlerini atar, bosluklari toplar.
 *
 * sanitize_text_field yerine kullanilir: o, adres kodlamasi sandigi "%" ile
 * baslayan iki haneyi siler ve Turkce yazimdaki yuzdeler kaybolur
 * ("Nem %18 - %20" -> "Nem -"). Tek satirlik metinde satir sonlari (U+2028
 * dahil) bosluga doner; cok satirlida satirlar korunur.
 */
function nwcs_clean_text( $value, bool $multiline = false ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	// Etiket olmayan "<" ("<20 mm", "x <= 5") korunur: once kacirilir, etiketler
	// atilir, sonra geri cevrilir. Ciktida her zaman esc_* kullanilir.
	$text = wp_pre_kses_less_than( wp_check_invalid_utf8( (string) $value ) );
	$text = str_replace( '&lt;', '<', wp_strip_all_tags( $text ) );
	$text = str_replace( array( "\r\n", "\r", "\u{2028}", "\u{2029}" ), "\n", $text );

	if ( $multiline ) {
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text );

		return trim( preg_replace( '/[ \t]+$/mu', '', (string) $text ) );
	}

	$text = preg_replace( '/[\x00-\x1F\x7F\s]+/u', ' ', $text );

	return trim( (string) $text );
}

/**
 * Tur bazli girdi temizleme. Panel ve seed tek yazma yolunu paylasir.
 */
function nwcs_sanitize_value( $value, array $definition ) {
	$type = $definition['type'] ?? 'text';

	switch ( $type ) {
		case 'textarea':
			return nwcs_clean_text( $value, true );

		case 'url':
			return esc_url_raw( trim( (string) $value ) );

		case 'image':
			return absint( $value );

		// Havuzdaki urun ve o urunun fotografi (ek kimlikleri havuz sitesinde).
		case 'product_photo':
			$value = is_array( $value ) ? $value : array();

			return array(
				'product' => absint( $value['product'] ?? 0 ),
				'image'   => absint( $value['image'] ?? 0 ),
			);

		case 'icon':
			return nwcs_sanitize_icon( (string) $value );

		case 'repeater':
			$rows       = is_array( $value ) ? $value : array();
			$row_fields = $definition['fields'] ?? array();
			$max        = (int) ( $definition['max'] ?? 20 );
			$clean      = array();

			foreach ( array_slice( array_values( $rows ), 0, $max ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean_row = array();
				foreach ( $row_fields as $key => $row_def ) {
					$clean_row[ $key ] = nwcs_sanitize_value( $row[ $key ] ?? '', $row_def );
				}
				$clean[] = $clean_row;
			}

			return $clean;

		case 'text':
		default:
			return nwcs_clean_text( $value );
	}
}

/**
 * Tek alani aktif siteye yazar. Alan manifestte tanimli degilse yazmaz.
 */
function nwcs_update_field( string $page, string $component, string $field, $value, ?array $manifest = null ): bool {
	$manifest   = $manifest ?? nwcs_manifest();
	$definition = nwcs_field_def( $manifest, $page, $component, $field );

	if ( null === $definition ) {
		return false;
	}

	$data = nwcs_get_all();

	$data[ $page ][ $component ][ $field ] = nwcs_sanitize_value( $value, $definition );

	return (bool) update_option( NWCS_OPTION_CONTENT, $data );
}

/**
 * Ana sayfa bolum sirasi. Kayitli sira manifestle dogrulanir; manifestte
 * olmayan anahtarlar atilir, yeni eklenenler sona eklenir.
 */
function nwcs_section_order( string $page = 'home', ?int $blog_id = null, ?array $manifest = null ): array {
	$manifest = $manifest ?? ( null === $blog_id ? nwcs_manifest() : nwcs_manifest_for_blog( $blog_id ) );
	$allowed  = nwcs_sortable_sections( $manifest, $page );

	$stored = null === $blog_id
		? get_option( NWCS_OPTION_ORDER, array() )
		: get_blog_option( $blog_id, NWCS_OPTION_ORDER, array() );

	$saved = is_array( $stored ) && isset( $stored[ $page ] ) && is_array( $stored[ $page ] )
		? $stored[ $page ]
		: array();

	$order = array_values( array_intersect( $saved, $allowed ) );

	foreach ( $allowed as $key ) {
		if ( ! in_array( $key, $order, true ) ) {
			$order[] = $key;
		}
	}

	return $order;
}

/**
 * Bolum sirasini aktif siteye yazar.
 */
function nwcs_set_section_order( string $page, array $order, ?array $manifest = null ): bool {
	$manifest = $manifest ?? nwcs_manifest();
	$allowed  = nwcs_sortable_sections( $manifest, $page );

	$clean = array_values( array_intersect( array_map( 'sanitize_key', $order ), $allowed ) );

	foreach ( $allowed as $key ) {
		if ( ! in_array( $key, $clean, true ) ) {
			$clean[] = $key;
		}
	}

	$stored = get_option( NWCS_OPTION_ORDER, array() );
	$stored = is_array( $stored ) ? $stored : array();

	$stored[ $page ] = $clean;

	return (bool) update_option( NWCS_OPTION_ORDER, $stored );
}
