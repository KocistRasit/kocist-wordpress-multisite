<?php
/**
 * Talepler: Cozum Merkezi formu (/iletisim/) ve Ozel Uretim formu.
 * Sahte basari yok: kayit yonetimde "Talepler" altina duser, Network Content
 * Studio bildirim e-postasini gonderir. Siparisler ayri: inc/checkout.php.
 *
 * Alanlar woodkocist.com.tr'deki formlarla ayni (ad, firma, e-posta, telefon,
 * departman / proje kategorisi, mesaj, KVKK onayi; ozel uretimde dosya).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'wk_register_request_cpt' );
function wk_register_request_cpt(): void {
	register_post_type(
		'wk_request',
		array(
			'labels'          => array(
				'name'          => 'Talepler',
				'singular_name' => 'Talep',
				'menu_name'     => 'Talepler',
				'all_items'     => 'Tüm talepler',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 27,
			'supports'        => array( 'title', 'editor', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'query_var'       => false,
		)
	);
}

add_filter(
	'nwcs_form_post_types',
	static function ( $types ) {
		$types['wk_order']   = '_wk_';
		$types['wk_request'] = '_wkr_';

		return $types;
	}
);

// Siparis ve talep bildirimleri her sitede ortak adrese gider; paneldeki SEO
// firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'wk_form_recipient' );
function wk_form_recipient(): string {
	return 'info@kocist.com.tr';
}

/**
 * Form turleri: alanlar, secenekler ve dugme metni. Gorunen yazilar panelden:
 * Cozum Merkezi -> Form Alanlari (contact.request), Ozel Uretim -> Form
 * Alanlari (custom.request); ortak alanlar Tum Sayfalar -> Talep Formlari.
 */
function wk_request_kinds(): array {
	$field   = static fn( string $page, string $name ): string => (string) nwcs_field( $page, 'request', $name );
	$options = static fn( string $page ): array => array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $field( $page, 'options' ) ) ?: array() ), 'strlen' ) );

	return array(
		'iletisim' => array(
			'label'    => 'Çözüm Merkezi',
			'page'     => 'contact',
			'select'   => array( 'department', $field( 'contact', 'select_label' ), $options( 'contact' ) ),
			'message'  => array( $field( 'contact', 'message_label' ), false, $field( 'contact', 'message_hint' ) ),
			'company'  => true,
			'file'     => false,
			'button'   => $field( 'contact', 'button' ),
			'success'  => $field( 'contact', 'success' ),
		),
		'ozel'     => array(
			'label'    => 'Özel Üretim',
			'page'     => 'custom',
			'select'   => array( 'department', $field( 'custom', 'select_label' ), $options( 'custom' ) ),
			'message'  => array( $field( 'custom', 'message_label' ), true, $field( 'custom', 'message_hint' ) ),
			'company'  => false,
			'file'     => true,
			'button'   => $field( 'custom', 'button' ),
			'success'  => $field( 'custom', 'success' ),
		),
	);
}

/**
 * Onizlemede uyari metninin panel alani: metin, bilesendeki "err_" ile
 * baslayan alanlardan birinin degeriyse o alan (orn. global.forms.err_name).
 */
function wk_error_attr( string $component, string $message ): string {
	if ( ! function_exists( 'nwcs_manifest' ) || ! function_exists( 'nwcs_is_preview' ) || ! nwcs_is_preview() ) {
		return '';
	}

	foreach ( array_keys( nwcs_manifest()['pages']['global']['components'][ $component ]['fields'] ?? array() ) as $name ) {
		if ( str_starts_with( (string) $name, 'err_' ) && (string) nwcs_field( 'global', $component, $name ) === $message ) {
			return wk_attr_string( 'global', $component, (string) $name );
		}
	}

	return '';
}

/** Talep formu uyarisi (Tum Sayfalar -> Talep Formlari). */
function wk_form_error( string $field ): string {
	return (string) nwcs_field( 'global', 'forms', $field );
}

const WK_UPLOAD_MAX = 5 * MB_IN_BYTES;

function wk_upload_mimes(): array {
	return array(
		'pdf'      => 'application/pdf',
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'dwg'      => 'application/acad',
	);
}

function wk_request_redirect( string $redirect, array $state ): void {
	$token = bin2hex( random_bytes( 10 ) );

	set_transient( 'wk_request_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'wk', $token, $redirect ) . '#talep' );
	exit;
}

add_action( 'admin_post_wk_request', 'wk_handle_request' );
add_action( 'admin_post_nopriv_wk_request', 'wk_handle_request' );
function wk_handle_request(): void {
	$kind  = sanitize_key( wp_unslash( $_POST['wk_kind'] ?? '' ) );
	$kinds = wk_request_kinds();
	$kind  = isset( $kinds[ $kind ] ) ? $kind : 'iletisim';
	$def   = $kinds[ $kind ];

	$fallback = wk_page_url( 'ozel' === $kind ? 'ozel-uretim-talep-formu' : 'iletisim' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	$redirect = strtok( remove_query_arg( 'wk', '' !== $redirect ? $redirect : $fallback ), '#' );

	if ( ! empty( $_POST['wk_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'       => sanitize_text_field( wp_unslash( $_POST['wk_name'] ?? '' ) ),
		'company'    => sanitize_text_field( wp_unslash( $_POST['wk_company'] ?? '' ) ),
		'phone'      => sanitize_text_field( wp_unslash( $_POST['wk_phone'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'      => trim( sanitize_text_field( wp_unslash( $_POST['wk_email'] ?? '' ) ) ),
		'department' => sanitize_text_field( wp_unslash( $_POST['wk_department'] ?? '' ) ),
		'product'    => sanitize_text_field( wp_unslash( $_POST['wk_product'] ?? '' ) ),
		'message'    => sanitize_textarea_field( wp_unslash( $_POST['wk_message'] ?? '' ) ),
		'consent'    => ! empty( $_POST['wk_consent'] ),
	);

	$errors = array();

	if ( ! isset( $_POST['wk_request_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wk_request_nonce'] ), 'wk_request' ) ) {
		$errors['form'] = wk_form_error( 'err_nonce' );
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = wk_form_error( 'err_name' );
	}

	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = wk_form_error( '' === $values['email'] ? 'err_email' : 'err_email_bad' );
	}

	if ( strlen( preg_replace( '/\D/', '', $values['phone'] ) ) < 10 ) {
		$errors['phone'] = wk_form_error( 'err_phone' );
	}

	if ( ! in_array( $values['department'], $def['select'][2], true ) ) {
		$errors['department'] = wk_form_error( 'ozel' === $kind ? 'err_category' : 'err_department' );
	}

	if ( $def['message'][1] && '' === trim( $values['message'] ) ) {
		$errors['message'] = wk_form_error( 'err_message' );
	}

	if ( ! $values['consent'] ) {
		$errors['consent'] = wk_form_error( 'err_consent' );
	}

	// Urun: yalnizca sitede gosterilen urunlerden biri ("KOD Ad").
	$allowed = array_map( static fn( array $p ): string => trim( $p['code'] . ' ' . $p['title'] ), wk_products() );

	if ( ! in_array( $values['product'], $allowed, true ) ) {
		$values['product'] = '';
	}

	$upload = null;

	// Hiz siniri (eklenti): dosya yuklemeden ONCE, disk dolmasin.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = wk_form_error( 'err_rate' );
	}

	if ( $def['file'] && ! empty( $_FILES['wk_file']['name'] ) && ! $errors ) {
		$upload = wk_request_upload();

		if ( is_string( $upload ) ) {
			$errors['file'] = $upload;
			$upload         = null;
		}
	}

	if ( $errors ) {
		// Oturum (nonce) hatasinda ziyaretci verisi saklanmaz: botlarin her
		// istegi gecici kayit olarak birikmesin.
		wk_request_redirect( $redirect, array( 'errors' => $errors, 'values' => isset( $errors['form'] ) ? array() : $values ) );
	}

	$subject = sprintf( '%s: %s', $def['label'], $values['department'] );
	$lines   = array(
		'Talep türü: ' . $subject,
		'Firma: ' . ( $values['company'] ?: '—' ),
		'Telefon: ' . $values['phone'],
		'E-posta: ' . $values['email'],
	);

	if ( $values['product'] ) {
		$lines[] = 'Ürün: ' . $values['product'];
	}

	if ( $upload ) {
		$lines[] = 'Ek dosya: ' . $upload['url'];
	}

	if ( function_exists( 'nwcs_form_rate_hit' ) ) {
		nwcs_form_rate_hit();
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'wk_request',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $subject ),
			'post_content' => implode( "\n", $lines ) . "\n\nMesaj:\n" . ( $values['message'] ?: '—' ),
			'meta_input'   => array(
				'_wkr_name'    => $values['name'],
				'_wkr_company' => $values['company'],
				'_wkr_phone'   => $values['phone'],
				'_wkr_email'   => $values['email'],
				'_wkr_subject' => $subject,
				'_wkr_product' => $values['product'],
				'_wkr_message' => trim( $values['message'] . ( $upload ? "\n\nEk dosya: " . $upload['url'] : '' ) ),
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		wk_request_redirect( $redirect, array( 'errors' => array( 'form' => wk_form_error( 'err_save' ) ), 'values' => $values ) );
	}

	wk_request_redirect( $redirect, array( 'success' => true, 'kind' => $kind ) );
}

/**
 * Ozel uretim eki: en fazla 5 MB; PDF, JPG, PNG, DWG. Dosya tahmin edilemez
 * bir adla wk-talepler klasorune yazilir; klasor listelenmez.
 *
 * @return array{url:string, file:string}|string Hata metni ya da dosya.
 */
function wk_request_upload() {
	$file = $_FILES['wk_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida denetlenir.

	if ( ! empty( $file['error'] ) ) {
		return in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
			? wk_form_error( 'err_file_size' )
			: wk_form_error( 'err_file_upload' );
	}

	if ( (int) $file['size'] > WK_UPLOAD_MAX ) {
		return wk_form_error( 'err_file_size' );
	}

	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], wk_upload_mimes() );

	if ( empty( $check['ext'] ) ) {
		// DWG'nin MIME turu sunucudan sunucuya degisir; uzanti yeterli.
		$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );

		if ( 'dwg' !== $ext ) {
			return wk_form_error( 'err_file_type' );
		}
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$dir_filter = static function ( array $dirs ): array {
		$dirs['subdir'] = '/wk-talepler';
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		return $dirs;
	};

	$file['name'] = wp_generate_password( 16, false ) . '.' . strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );

	add_filter( 'upload_dir', $dir_filter );
	$result = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => wk_upload_mimes() + array( 'dwg' => 'application/octet-stream' ),
			'test_type' => false,
		)
	);
	remove_filter( 'upload_dir', $dir_filter );

	if ( empty( $result['url'] ) ) {
		return wk_form_error( 'err_file_save' );
	}

	$index = dirname( $result['file'] ) . '/index.php';

	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Sessizlik.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return array( 'url' => $result['url'], 'file' => $result['file'] );
}

function wk_request_state(): array {
	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false, 'kind' => '' );
	$token = isset( $_GET['wk'] ) ? sanitize_key( wp_unslash( $_GET['wk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return $empty;
	}

	$state = get_transient( 'wk_request_' . $token );

	return is_array( $state ) ? array_merge( $empty, $state, array( 'success' => ! empty( $state['success'] ) ) ) : $empty;
}
