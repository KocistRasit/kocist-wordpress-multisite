<?php
/**
 * Siparis ve fiyat formu.
 *
 * Sahte basari ekrani yok: gonderilen form kaydedilir (yonetimde "Siparis
 * Istekleri"), Network Content Studio bildirim e-postasini gonderir
 * (includes/forms.php; alici info@kocist.com.tr, pc_form_recipient). Ana sayfa
 * ve Iletisim ayni formu (template-parts/quote-form.php) kullanir.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'pc_register_quote_cpt' );
function pc_register_quote_cpt(): void {
	register_post_type(
		'pc_quote',
		array(
			'labels'          => array(
				'name'          => 'Sipariş İstekleri',
				'singular_name' => 'Sipariş İsteği',
				'menu_name'     => 'Sipariş İstekleri',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 26,
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

// Bildirim e-postasi: eklentinin form listesine bu tema da eklenir.
add_filter(
	'nwcs_form_post_types',
	static function ( $types ) {
		$types['pc_quote'] = '_pc_';

		return $types;
	}
);

// Bildirim alicisi her sitede ortak adres; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'pc_form_recipient' );
function pc_form_recipient(): string {
	return 'info@kocist.com.tr';
}

/**
 * Yonlendirme sonrasi durumu tasiyan gecici kaydin anahtari: kucuk harfli
 * onaltilik (harf duyarli nesne onbelleginde kaybolmasin).
 */
function pc_quote_token(): string {
	return bin2hex( random_bytes( 10 ) );
}

function pc_quote_redirect( string $redirect, array $state ): void {
	$token = pc_quote_token();

	set_transient( 'pc_quote_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'pc', $token, $redirect ) . '#siparis' );
	exit;
}

add_action( 'admin_post_pc_quote', 'pc_handle_quote' );
add_action( 'admin_post_nopriv_pc_quote', 'pc_handle_quote' );
function pc_handle_quote(): void {
	$fallback = home_url( '/iletisim/' );
	$referer  = wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	$redirect = strtok( remove_query_arg( 'pc', '' !== $redirect ? $redirect : $fallback ), '#' );

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['pc_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['pc_name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['pc_company'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['pc_phone'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['pc_email'] ?? '' ) ) ),
		'product' => sanitize_text_field( wp_unslash( $_POST['pc_product'] ?? '' ) ),
		'size'    => sanitize_textarea_field( wp_unslash( $_POST['pc_size'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['pc_message'] ?? '' ) ),
	);

	$errors = array();

	if ( ! isset( $_POST['pc_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pc_quote_nonce'] ), 'pc_quote' ) ) {
		$errors['form'] = 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.';
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'Adınızı yazın.';
	}

	// Donus yapabilmek icin telefon ya da e-posta: ikisinden biri yeterli.
	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		$errors['email'] = 'E-posta adresi geçerli görünmüyor.';
	} elseif ( '' === $values['email'] && '' === $values['phone'] ) {
		$errors['phone'] = 'Size dönebilmemiz için telefon ya da e-posta yazın.';
	}

	if ( '' === $values['size'] && '' === $values['message'] ) {
		$errors['size'] = 'Ölçü ve adedi ya da sorunuzu yazın.';
	}

	// Hiz siniri (eklenti, nwcs_form_rate_*): yalnizca gecerli talepte.
	if ( ! $errors && function_exists( 'nwcs_form_rate_limited' ) && nwcs_form_rate_limited() ) {
		$errors['form'] = function_exists( 'nwcs_form_rate_message' ) ? nwcs_form_rate_message() : 'Lütfen biraz sonra yeniden deneyin.';
	}

	if ( $errors ) {
		pc_quote_redirect( $redirect, array( 'errors' => $errors, 'values' => $values ) );
	}

	// Urun secenekleri manifestten gelir; baska deger kabul edilmez.
	if ( ! in_array( $values['product'], wp_list_pluck( pc_products(), 'name' ), true ) ) {
		$values['product'] = 'Belirtilmedi';
	}

	if ( function_exists( 'nwcs_form_rate_hit' ) ) {
		nwcs_form_rate_hit();
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'pc_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $values['product'] ),
			'post_content' => sprintf(
				"Firma: %s\nTelefon: %s\nE-posta: %s\nÜrün: %s\n\nÖlçü ve adet:\n%s\n\nMesaj:\n%s",
				$values['company'] ?: '—',
				$values['phone'] ?: '—',
				$values['email'] ?: '—',
				$values['product'],
				$values['size'] ?: '—',
				$values['message'] ?: '—'
			),
			'meta_input'   => array(
				'_pc_name'    => $values['name'],
				'_pc_company' => $values['company'],
				'_pc_phone'   => $values['phone'],
				'_pc_email'   => $values['email'],
				'_pc_product' => $values['product'],
				'_pc_size'    => $values['size'],
				'_pc_message' => $values['message'],
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		pc_quote_redirect(
			$redirect,
			array(
				'errors' => array( 'form' => 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ),
				'values' => $values,
			)
		);
	}

	pc_quote_redirect( $redirect, array( 'success' => true ) );
}

function pc_quote_state(): array {
	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false );
	$token = isset( $_GET['pc'] ) ? sanitize_key( wp_unslash( $_GET['pc'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return $empty;
	}

	$state = get_transient( 'pc_quote_' . $token );

	if ( ! is_array( $state ) ) {
		return $empty;
	}

	return array(
		'errors'  => $state['errors'] ?? array(),
		'values'  => $state['values'] ?? array(),
		'success' => ! empty( $state['success'] ),
	);
}
