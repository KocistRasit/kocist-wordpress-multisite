<?php
/**
 * Iletisim / teklif formu gonderimi.
 *
 * Iki form ayni isleyiciye gelir: iletisim sayfasindaki form ve havuz urun
 * sayfasindaki teklif formu (gizli kc_product; mesaj yerine Adet / Olcu,
 * Sirket istege bagli). Ikisinde de onay kutusu (kc-consent) zorunlu.
 * Teklif sepetinin e-posta formu ayni kayit turune ve bildirime yazar, kendi
 * isleyicisiyle (inc/basket.php, kocist_handle_basket_send).
 *
 * Gonderilen form kaydedilir (yonetimde "Teklif Talepleri"), Network Content
 * Studio bildirim e-postasini gonderir (includes/forms.php; alici
 * info@kocist.com.tr, kocist_form_recipient). Urunden gelinmisse ?urun= slug'i
 * gizli alanla tasinir ve sunucuda urun listesinde aranir; bulunamazsa
 * yok sayilir.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'kocist_register_quote_cpt' );
function kocist_register_quote_cpt(): void {
	register_post_type(
		'kc_quote',
		array(
			'labels'          => array(
				'name'          => 'Teklif Talepleri',
				'singular_name' => 'Teklif Talebi',
				'menu_name'     => 'Teklif Talepleri',
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
		$types['kc_quote'] = '_kc_';

		return $types;
	}
);

// Bildirim alicisi her sitede ortak adres; paneldeki SEO firma e-postasindan bagimsiz.
add_filter( 'nwcs_form_recipient', 'kocist_form_recipient' );
function kocist_form_recipient(): string {
	return 'info@kocist.com.tr';
}

/**
 * Yonlendirme sonrasi durumu tasiyan gecici kayit. Anahtar kucuk harfli
 * onaltilik (harf duyarli nesne onbelleginde kaybolmasin). $anchor: donulen
 * sayfadaki formun kimligi (teklif sepetinde 'teklif-gonder', inc/basket.php).
 */
function kocist_quote_redirect( string $redirect, array $state, string $anchor = 'teklif-formu' ): void {
	$token = bin2hex( random_bytes( 10 ) );

	set_transient( 'kc_quote_' . $token, $state, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'kc', $token, $redirect ) . '#' . $anchor );
	exit;
}

/*
 * Hiz siniri (e-posta seli): ayni adresten en fazla 30 saniyede bir ve saatte
 * 10 talep. Teklif formu ve teklif sepetinin e-posta formu ortak sayar. Adres
 * tuzlu ozetle tutulur (gecici kayitta acik IP yok). Cloudflare arkasinda
 * REMOTE_ADDR Cloudflare'in adresidir; gercek ziyaretci CF-Connecting-IP'de
 * (WOOD KOCIST odeme formuyla ayni; yalnizca hiz siniri, baska yetki yok).
 */
const KOCIST_FORM_GAP    = 30; // Iki talep arasi en az saniye.
const KOCIST_FORM_HOURLY = 10; // Saatte en fazla talep.

function kocist_form_rate_key(): string {
	$ip = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' );

	return 'kc_rate_' . substr( wp_hash( 'kocist-form|' . $ip ), 0, 24 );
}

/**
 * Son bir saatteki talep zamanlari.
 *
 * @return int[]
 */
function kocist_form_rate_times(): array {
	$times = get_transient( kocist_form_rate_key() );

	return array_values( array_filter( array_map( 'intval', is_array( $times ) ? $times : array() ), static fn( int $time ): bool => $time > time() - HOUR_IN_SECONDS ) );
}

/**
 * Bu adres sinirda mi? Siniri asan talep kaydedilmez, e-posta gonderilmez.
 */
function kocist_form_rate_limited(): bool {
	$times = kocist_form_rate_times();

	return ( $times && time() - max( $times ) < KOCIST_FORM_GAP ) || count( $times ) >= KOCIST_FORM_HOURLY;
}

/**
 * Kaydedilen talebi sayar.
 */
function kocist_form_rate_hit(): void {
	$times   = kocist_form_rate_times();
	$times[] = time();

	set_transient( kocist_form_rate_key(), $times, HOUR_IN_SECONDS );
}

function kocist_form_rate_message(): string {
	return 'Az önce bir talep gönderdiniz; lütfen biraz sonra yeniden deneyin.';
}

add_action( 'admin_post_kc_quote', 'kocist_handle_quote' );
add_action( 'admin_post_nopriv_kc_quote', 'kocist_handle_quote' );
function kocist_handle_quote(): void {
	$fallback = home_url( '/iletisim/' );
	// Donus adresi: formun kendi bildirdigi sayfa (urun formu kc_return tasir;
	// Referer gondermeyen tarayicida da ayni sayfaya donulur), yoksa Referer,
	// o da yoksa iletisim sayfasi. Yalnizca bu sitenin adresi kabul edilir.
	$return   = esc_url_raw( wp_unslash( $_POST['kc_return'] ?? '' ) );
	$referer  = '' !== $return ? $return : wp_get_referer();
	$redirect = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	$redirect = strtok( remove_query_arg( 'kc', '' !== $redirect ? $redirect : $fallback ), '#' );

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don.
	if ( ! empty( $_POST['kc_website'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	$values = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['kc-name'] ?? '' ) ),
		'company' => sanitize_text_field( wp_unslash( $_POST['kc-company'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['kc-phone'] ?? '' ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['kc-email'] ?? '' ) ) ),
		'subject' => sanitize_text_field( wp_unslash( $_POST['kc-subject'] ?? '' ) ),
		'qty'     => trim( sanitize_textarea_field( wp_unslash( $_POST['kc-qty'] ?? '' ) ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['kc-detail'] ?? '' ) ),
		'consent' => ! empty( $_POST['kc-consent'] ),
	);

	// Urun sayfasindaki form (gizli kc_form=product): mesaj alani yok; yerine Adet / Olcu sorulur.
	$from_product = 'product' === sanitize_key( wp_unslash( $_POST['kc_form'] ?? '' ) );

	$errors = array();

	if ( ! isset( $_POST['kc_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kc_quote_nonce'] ), 'kc_quote' ) ) {
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

	if ( $from_product ) {
		if ( '' === $values['qty'] ) {
			$errors['qty'] = 'Adet ya da ölçü yazın.';
		}
	} elseif ( '' === $values['message'] ) {
		$errors['message'] = 'Aradığınız ürünü veya sorunuzu yazın.';
	}

	if ( ! $values['consent'] ) {
		$errors['consent'] = 'Onay kutusunu işaretleyin.';
	}

	// Hiz siniri yalnizca gecerli talepte: hatasini duzelten ziyaretci beklemez.
	if ( ! $errors && kocist_form_rate_limited() ) {
		$errors['form'] = kocist_form_rate_message();
	}

	if ( $errors ) {
		kocist_quote_redirect( $redirect, array( 'errors' => $errors, 'values' => $values ) );
	}

	kocist_form_rate_hit();

	// Urun: yalnizca sitedeki urun listesinde bulunan slug kabul edilir.
	$product = '';
	$slug    = sanitize_title( wp_unslash( $_POST['kc_product'] ?? '' ) );

	// Adres panelde degistirildiyse eski adres (havuz ya da onceki) de kabul edilir.
	if ( '' !== $slug && function_exists( 'nwcs_product_slug_resolve' ) ) {
		$slug = nwcs_product_slug_resolve( $slug ) ?: $slug;
	}

	if ( '' !== $slug && function_exists( 'kocist_catalog_products' ) ) {
		foreach ( kocist_catalog_products() as $candidate ) {
			if ( (string) ( $candidate['slug'] ?? '' ) === $slug ) {
				$context = kocist_product_context( $candidate );
				$product = trim( $context['name'] . ( '' !== $context['category'] ? ' (' . $context['category'] . ')' : '' ) );
				break;
			}
		}
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'kc_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], '' !== $product ? $product : ( $values['subject'] ?: 'İletişim formu' ) ),
			'post_content' => sprintf(
				"Telefon: %s\nE-posta: %s\n%sKonu: %s\nÜrün: %s\n%s\nMesaj:\n%s\n\nOnay kutusu: işaretli",
				$values['phone'] ?: '—',
				$values['email'] ?: '—',
				'' !== $values['company'] ? 'Şirket: ' . $values['company'] . "\n" : '',
				$values['subject'] ?: '—',
				$product ?: '—',
				'' !== $values['qty'] ? 'Adet / Ölçü: ' . $values['qty'] . "\n" : '',
				'' !== $values['message'] ? $values['message'] : '—'
			),
			// Sirket ve Adet / Olcu yalnizca yazildiysa; bildirim e-postasinda
			// "Firma" ve "Ölçü ve adet" olarak cikar (eklenti forms.php etiketleri).
			'meta_input'   => array_merge(
				array(
					'_kc_name'    => $values['name'],
					'_kc_phone'   => $values['phone'],
					'_kc_email'   => $values['email'],
					'_kc_subject' => $values['subject'],
					'_kc_product' => $product,
					'_kc_message' => $values['message'],
				),
				'' !== $values['company'] ? array( '_kc_company' => $values['company'] ) : array(),
				'' !== $values['qty'] ? array( '_kc_size' => $values['qty'] ) : array()
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		kocist_quote_redirect(
			$redirect,
			array(
				'errors' => array( 'form' => 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ),
				'values' => $values,
			)
		);
	}

	kocist_quote_redirect( $redirect, array( 'success' => true ) );
}

/**
 * Yonlendirme sonrasi form durumu: hatalar, girilen degerler, basari.
 */
function kocist_quote_state(): array {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$empty = array( 'errors' => array(), 'values' => array(), 'success' => false );
	$cache = $empty;
	$token = isset( $_GET['kc'] ) ? sanitize_key( wp_unslash( $_GET['kc'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! preg_match( '/^[a-f0-9]{20}$/', $token ) ) {
		return $empty;
	}

	$state = get_transient( 'kc_quote_' . $token );

	if ( ! is_array( $state ) ) {
		return $empty;
	}

	// Tek kullanimlik: sayfa yenilenince ya da adres paylasilinca girilen bilgiler bir daha gorunmez.
	delete_transient( 'kc_quote_' . $token );

	$cache = array(
		'errors'  => $state['errors'] ?? array(),
		'values'  => $state['values'] ?? array(),
		'success' => ! empty( $state['success'] ),
	);

	return $cache;
}

/**
 * Onay kutusu: iki formda ayni alan (kc-consent) ve ayni metin (Urun
 * Sayfalari > Havuz Urun Sayfalari > "Onay kutusu metni"). Alt seritteki
 * KVKK baglantisi gercek bir adrese cozuluyorsa metnin altinda baglanti
 * olur; "#kvkk" gibi hedefsiz capa iken yalnizca metin.
 *
 * @param string $error   Alanin hata metni (bos: hata yok).
 * @param bool   $checked Hata sonrasi geri donuste isaretli miydi.
 */
function kocist_consent_field( string $error = '', bool $checked = false ): void {
	$kvkk_url   = '';
	$kvkk_label = '';

	foreach ( nwcs_rows( 'global', 'footer', 'legal' ) as $row ) {
		if ( false !== mb_stripos( (string) ( $row['label'] ?? '' ), 'kvkk' ) ) {
			$url = kocist_link( $row['url'] ?? '' );

			if ( str_starts_with( $url, 'http' ) ) {
				$kvkk_url   = $url;
				$kvkk_label = (string) $row['label'];
			}
			break;
		}
	}

	$described = '' !== $error ? ' aria-invalid="true" aria-describedby="kc-consent-error"' : '';
	?>
	<div class="k-field k-field--wide k-consent">
		<label class="k-consent__label" for="kc-consent">
			<input type="checkbox" id="kc-consent" name="kc-consent" value="1" required<?php echo $checked ? ' checked' : ''; ?><?php echo $described; // phpcs:ignore WordPress.Security.EscapingOutput -- sabit metin. ?> />
			<span <?php nwcs_edit_attr( 'product', 'detail', 'consent_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'consent_label' ) ); ?></span>
		</label>
		<?php if ( '' !== $kvkk_url ) : ?>
			<a class="k-consent__link" href="<?php echo esc_url( $kvkk_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $kvkk_label ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<span class="k-field__error" id="kc-consent-error" role="alert"><?php echo esc_html( $error ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}
