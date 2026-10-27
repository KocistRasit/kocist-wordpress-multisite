<?php
/**
 * Form bildirimleri.
 *
 * Temalarin iletisim / teklif formlari gonderimi kendi CPT'lerine ozel
 * kayit olarak yazar (ak_quote, ip_quote, kr_quote, ik_message, sp_message,
 * aas_quote). Bu dosya yeni kayit olusunca site sahibine duz metin e-posta
 * gonderir. Temalara dokunmaz: tek baglanti noktasi wp_after_insert_post.
 *
 * Kurallar:
 *   - Yalnizca yeni kayit, yalnizca listedeki turler, kayit basina bir kez
 *     (_nwcs_notified meta: pending | sent | failed).
 *   - Gonderim shutdown'a ertelenir; yonlendirme ziyaretciye once ulasir.
 *     wp_mail hatasi formun basari durumunu degistirmez, yalnizca error_log.
 *   - Alici: nwcs_form_recipient filtresi; varsayilan SEO sirket e-postasi,
 *     gecersizse admin_email.
 *   - Basliklara giden ad ve adreslerden satir sonlari temizlenir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_FORM_NOTIFIED_META = '_nwcs_notified';

/**
 * Bildirim gonderilen form turleri: post_type => meta on eki.
 * Filtre duz liste de dondurebilir; on ek "_<ilk parca>_" kabul edilir.
 */
function nwcs_form_post_types(): array {
	$defaults = array(
		'ak_quote'   => '_ak_',   // ahsapkasa-theme
		'ip_quote'   => '_ip_',   // istanbulpaletci-theme
		'kr_quote'   => '_kr_',   // kereste-base (ithalkeresteci, kavakkeresteci)
		'ik_message' => '_ik_',   // istanbul-keresteci-theme
		'sp_message' => '_sp_',   // sanayi-palet-theme
		'aas_quote'  => '_aas_',  // ahsapambalaj-theme
		'pc_quote'   => '_pc_',   // istanbulpaletcivi-theme
		'wk_order'   => '_wk_',   // woodkocist-theme
		'wk_request' => '_wkr_',  // woodkocist-theme (numune, proje, bayilik)
	);

	$types = apply_filters( 'nwcs_form_post_types', $defaults );
	$clean = array();

	foreach ( (array) $types as $key => $value ) {
		$type = is_int( $key ) ? sanitize_key( (string) $value ) : sanitize_key( (string) $key );

		if ( '' === $type ) {
			continue;
		}

		$prefix = is_int( $key ) ? '' : (string) $value;

		if ( '' === $prefix ) {
			$prefix = '_' . strtok( $type, '_' ) . '_';
		}

		$clean[ $type ] = $prefix;
	}

	return $clean;
}

/**
 * Bilinen alan adlari icin Turkce etiketler; sira e-postadaki siradir.
 */
function nwcs_form_field_labels(): array {
	return array(
		'name'    => 'Ad Soyad',
		'company' => 'Firma',
		'email'   => 'E-posta',
		'phone'   => 'Telefon',
		'subject' => 'Konu',
		'product' => 'Ürün',
		'items'   => 'Ürünler', // Cok urunlu talep (Kocist teklif sepeti): satir satir kod, ad, adet, adres.
		'size'    => 'Ölçü ve adet',
		'message' => 'Mesaj',
	);
}

/**
 * Istek icindeki bekleyen bildirimler. $add verilirse kuyruga ekler,
 * $take true ise kuyrugu bosaltip dondurur.
 */
function nwcs_form_queue( ?array $add = null, bool $take = false ): array {
	static $queue = array();

	if ( null !== $add ) {
		$queue[ $add['blog_id'] . ':' . $add['post_id'] ] = $add;
	}

	if ( $take ) {
		$items = array_values( $queue );
		$queue = array();

		return $items;
	}

	return $queue;
}

add_action( 'wp_after_insert_post', 'nwcs_form_on_insert', 10, 3 );
function nwcs_form_on_insert( int $post_id, WP_Post $post, bool $update ): void {
	if ( $update || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) ) {
		return;
	}

	if ( ! isset( nwcs_form_post_types()[ $post->post_type ] ) || in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
		return;
	}

	// Kayit basina tek bildirim: meta zaten varsa (baska bir istek islediyse) atla.
	if ( ! add_post_meta( $post_id, NWCS_FORM_NOTIFIED_META, 'pending', true ) ) {
		return;
	}

	nwcs_form_queue(
		array(
			'blog_id' => get_current_blog_id(),
			'post_id' => $post_id,
		)
	);

	if ( ! has_action( 'shutdown', 'nwcs_form_flush' ) ) {
		add_action( 'shutdown', 'nwcs_form_flush', 20 );
	}
}

/**
 * Kuyruktaki bildirimleri gonderir. Once yaniti ziyaretciye kapatir
 * (PHP-FPM / LiteSpeed), sonra e-postayi gonderir; SMTP gecikmesi
 * yonlendirmeyi bekletmez.
 */
function nwcs_form_flush(): void {
	$items = nwcs_form_queue( null, true );

	if ( ! $items ) {
		return;
	}

	if ( 'cli' !== PHP_SAPI ) {
		ignore_user_abort( true );

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
	}

	foreach ( $items as $item ) {
		$switched = is_multisite() && (int) $item['blog_id'] !== get_current_blog_id();

		if ( $switched ) {
			switch_to_blog( (int) $item['blog_id'] );
		}

		try {
			nwcs_form_send_notification( (int) $item['post_id'] );
		} catch ( Throwable $e ) {
			error_log( sprintf( '[nwcs-forms] Bildirim hatasi (site %d, kayit %d): %s', (int) $item['blog_id'], (int) $item['post_id'], $e->getMessage() ) );
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}
}

/**
 * Tek satir: satir sonu ve kontrol karakterleri bosluga cevrilir.
 */
function nwcs_form_single_line( string $value ): string {
	return trim( preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '' );
}

/**
 * Adres basligindaki ad icin: tek satir, adres ayiraclari da atilir.
 */
function nwcs_form_header_text( string $value ): string {
	return trim( str_replace( array( '<', '>', '"', ',', ';', ':' ), '', nwcs_form_single_line( $value ) ) );
}

/**
 * Tek bir e-posta adresini dogrular; gecersizse bos dizge.
 */
function nwcs_form_clean_email( $value ): string {
	$email = sanitize_email( nwcs_form_header_text( (string) $value ) );

	return ( '' !== $email && is_email( $email ) ) ? $email : '';
}

/**
 * Sitenin panelde girilmis firma e-postasi (SEO ve GEO -> Firma). Temanin
 * varsayilan adresi bilerek kullanilmaz: firma onaylamadan bildirimler var
 * olup olmadigi bilinmeyen bir kutuya gitmesin; o zaman yonetici adresine
 * gider ve E-posta Testi sayfasi uyarir. Cagiran site baglaminda olmali.
 *
 * $with_default: sitede gorunen (tema varsayilani dahil) adres.
 */
function nwcs_form_site_email( bool $with_default = false ): string {
	if ( ! function_exists( 'nwcs_field' ) ) {
		return '';
	}

	return nwcs_form_clean_email( (string) nwcs_field( NWCS_SEO_SITE_PAGE, 'org', 'email', $with_default ? null : '' ) );
}

/**
 * Bildirim alicilari. Filtre dizge (virgulle ayrilmis olabilir) ya da dizi
 * dondurebilir; gecersiz adresler atilir.
 */
function nwcs_form_recipients( WP_Post $post ): array {
	$default = nwcs_form_site_email();

	if ( '' === $default ) {
		$default = nwcs_form_clean_email( get_option( 'admin_email' ) );
	}

	$raw  = apply_filters( 'nwcs_form_recipient', $default, $post );
	$list = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
	$out  = array();

	foreach ( $list as $item ) {
		$email = nwcs_form_clean_email( $item );

		if ( '' !== $email ) {
			$out[ strtolower( $email ) ] = $email;
		}
	}

	return array_values( $out );
}

/**
 * Kaydin alanlari: [ alan => [ etiket, deger ] ]. Bilinen alanlar sabit
 * sirada, digerleri meta anahtariyla alfabetik.
 */
function nwcs_form_fields( WP_Post $post, string $prefix ): array {
	$labels = nwcs_form_field_labels();
	$known  = array();
	$other  = array();

	foreach ( get_post_meta( $post->ID ) as $key => $values ) {
		$key = (string) $key;

		if ( ! str_starts_with( $key, $prefix ) || ! isset( $values[0] ) || ! is_scalar( $values[0] ) ) {
			continue;
		}

		$field = substr( $key, strlen( $prefix ) );
		$value = nwcs_clean_text( $values[0], true );

		if ( isset( $labels[ $field ] ) ) {
			$known[ $field ] = array( $labels[ $field ], $value );
		} elseif ( '' !== $value ) {
			$other[ $key ] = array( $key, $value );
		}
	}

	$ordered = array();

	foreach ( array_keys( $labels ) as $field ) {
		if ( isset( $known[ $field ] ) ) {
			$ordered[ $field ] = $known[ $field ];
		}
	}

	ksort( $other );

	return $ordered + $other;
}

/**
 * E-posta konusunun basi: kaydin turune gore. Siparis numarasi kayit
 * basligindan ("WK260925-37 — Ad — Tutar") alinir.
 */
function nwcs_form_kind( WP_Post $post ): string {
	if ( 'wk_order' === $post->post_type ) {
		$number = (string) strtok( (string) $post->post_title, ' ' );

		return preg_match( '/^[A-Z]{2}\d/', $number ) ? 'Yeni sipariş ' . $number : 'Yeni sipariş';
	}

	if ( str_ends_with( $post->post_type, '_request' ) ) {
		return 'Yeni talep';
	}

	return str_ends_with( $post->post_type, '_quote' ) ? 'Yeni teklif isteği' : 'Yeni iletişim mesajı';
}

/**
 * Tek kaydin bildirimini gonderir. Sonuc _nwcs_notified metasina yazilir.
 */
function nwcs_form_send_notification( int $post_id ): bool {
	$post  = get_post( $post_id );
	$types = nwcs_form_post_types();

	if ( ! $post instanceof WP_Post || ! isset( $types[ $post->post_type ] ) ) {
		return false;
	}

	if ( 'pending' !== get_post_meta( $post_id, NWCS_FORM_NOTIFIED_META, true ) ) {
		return false;
	}

	$recipients = nwcs_form_recipients( $post );

	if ( ! $recipients ) {
		update_post_meta( $post_id, NWCS_FORM_NOTIFIED_META, 'failed' );
		error_log( sprintf( '[nwcs-forms] Gecerli alici yok (site %d, kayit %d).', get_current_blog_id(), $post_id ) );

		return false;
	}

	$fields    = nwcs_form_fields( $post, $types[ $post->post_type ] );
	$site_name = nwcs_form_single_line( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$kind      = nwcs_form_kind( $post );
	$subject   = sprintf( '%s: %s', $kind, '' !== $site_name ? $site_name : wp_parse_url( home_url(), PHP_URL_HOST ) );

	$lines = array( $subject, '' );

	foreach ( $fields as $field ) {
		// Formda olmayan ya da bos birakilan alan yazilmaz ("Firma: —" gibi).
		if ( '' === $field[1] ) {
			continue;
		}

		$value = $field[1];

		if ( str_contains( $value, "\n" ) ) {
			$lines[] = $field[0] . ':';
			$lines[] = $value;
			$lines[] = '';
		} else {
			$lines[] = $field[0] . ': ' . $value;
		}
	}

	// Mesaj metasi olmayan turlerde (ik_message, sp_message) mesaj yalnizca
	// kayit metninde durur; metnin tamami eklenir.
	if ( ! isset( $fields['message'] ) ) {
		// Kayit metni kses'ten gecmis (&amp;): duz metne cevrilir; alan satirlarinda
		// zaten yazilan satirlar ("Telefon: ...") tekrar edilmez.
		$content = html_entity_decode( wp_strip_all_tags( $post->post_content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$written = array();

		foreach ( $fields as $field ) {
			$written[] = mb_strtolower( trim( $field[0] . ': ' . $field[1] ), 'UTF-8' );
			$written[] = mb_strtolower( trim( $field[1] ), 'UTF-8' );
		}

		$content = implode(
			"\n",
			array_filter(
				preg_split( '/\R/u', $content ),
				// Temanin bos alan icin yazdigi "E-posta: —" satirlari da atlanir.
				static fn( string $line ): bool => ! in_array( mb_strtolower( trim( $line ), 'UTF-8' ), array_filter( $written ), true )
					&& ! preg_match( '/^[^:]{1,40}:\s*[—–-]\s*$/u', trim( $line ) )
			)
		);
		$content = trim( (string) preg_replace( "/\n{3,}/", "\n\n", $content ) );

		if ( '' !== $content ) {
			$lines[] = '';
			$lines[] = 'Kayıt metni:';
			$lines[] = $content;
		}
	}

	$lines[] = '';
	$lines[] = 'Gönderim zamanı: ' . wp_date( 'd.m.Y H:i', (int) get_post_timestamp( $post ) );
	$lines[] = 'Kaydı yönetim panelinde açın: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	$lines[] = 'Tüm kayıtlar: ' . admin_url( 'edit.php?post_type=' . $post->post_type );
	$lines[] = '';
	$lines[] = sprintf( 'Bu e-posta %s adresindeki formdan otomatik gönderildi.', home_url( '/' ) );

	$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );
	$reply_to = nwcs_form_clean_email( $fields['email'][1] ?? '' );

	if ( '' !== $reply_to ) {
		$name      = nwcs_form_header_text( $fields['name'][1] ?? '' );
		$headers[] = 'Reply-To: ' . ( '' !== $name ? $name . ' <' . $reply_to . '>' : $reply_to );
		$lines[]   = 'Yanıtla dediğinizde e-posta doğrudan müşteriye gider.';
	}

	$error  = '';
	$listen = static function ( WP_Error $wp_error ) use ( &$error ): void {
		$error = $wp_error->get_error_message();
	};

	add_action( 'wp_mail_failed', $listen );

	try {
		$sent = (bool) wp_mail( $recipients, $subject, implode( "\n", $lines ) . "\n", $headers );
	} catch ( Throwable $e ) {
		$sent  = false;
		$error = $e->getMessage();
	}

	remove_action( 'wp_mail_failed', $listen );

	update_post_meta( $post_id, NWCS_FORM_NOTIFIED_META, $sent ? 'sent' : 'failed' );

	if ( ! $sent ) {
		error_log( sprintf( '[nwcs-forms] E-posta gonderilemedi (site %d, kayit %d): %s', get_current_blog_id(), $post_id, '' !== $error ? $error : 'wp_mail false dondu' ) );
	}

	return $sent;
}

/*
 * Gonderen adi: WordPress'in varsayilani "WordPress"; alici hangi sitenin
 * yazdigini gorsun diye site adi. Baska bir eklenti (SMTP) ad verdiyse dokunulmaz.
 */
add_filter(
	'wp_mail_from_name',
	static function ( $name ) {
		if ( 'WordPress' !== $name ) {
			return $name;
		}

		$site = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

		return '' !== $site ? nwcs_form_single_line( $site ) : $name;
	}
);

/*
 * Saat dilimi secilmemis site (ayar bos, UTC+0): e-postalardaki saat ve siparis
 * numarasindaki tarih Turkiye saatiyle yazilsin. Panelde bir dilim secilirse o gecerli.
 */
function nwcs_default_timezone( $value ) {
	static $busy = false;

	// get_option( 'gmt_offset' ) cekirdekte tekrar timezone_string'i okur
	// (wp_timezone_override_offset): kendi icinden cagrilinca dokunma.
	if ( $busy || '' !== (string) $value ) {
		return $value;
	}

	$busy   = true;
	$offset = (float) get_option( 'gmt_offset', 0 );
	$busy   = false;

	return 0.0 === $offset ? 'Europe/Istanbul' : $value;
}
// Ayar kayitli ama bos / hic kayitli degil: ikisi ayri kanca.
add_filter( 'option_timezone_string', 'nwcs_default_timezone' );
add_filter( 'default_option_timezone_string', 'nwcs_default_timezone' );

/* ======================================================================
 * Form hiz siniri (butun temalar icin ortak)
 *
 * Anonim nonce butun ziyaretcilerde ortaktir ve 12-24 saat gecerlidir; bal
 * kupu basit bir botu durdurmaz. Betikle saniyede yuzlerce POST her biri bir
 * kayit + bildirim e-postasi olurdu. Adres basina: iki talep arasi en az
 * 30 saniye, saatte en fazla 10. Adres tuzlu ozetle tutulur (acik IP yok).
 * Cloudflare arkasinda REMOTE_ADDR Cloudflare'in adresi; gercek ziyaretci
 * CF-Connecting-IP'de (yalnizca hiz siniri; baska yetki yok). Koçist temasinin
 * kocist_form_rate_* kalibi; temalar bunu kullanir.
 * ====================================================================== */

const NWCS_FORM_RATE_GAP    = 30;
const NWCS_FORM_RATE_HOURLY = 10;

function nwcs_form_rate_key(): string {
	$ip = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' );

	return 'nwcs_rate_' . substr( wp_hash( 'nwcs-form|' . $ip ), 0, 24 );
}

/**
 * Son bir saatteki talep zamanlari.
 *
 * @return int[]
 */
function nwcs_form_rate_times(): array {
	$times = get_transient( nwcs_form_rate_key() );

	return array_values( array_filter( array_map( 'intval', is_array( $times ) ? $times : array() ), static fn( int $time ): bool => $time > time() - HOUR_IN_SECONDS ) );
}

/**
 * Bu adres sinirda mi? Temalar yalnizca gecerli (hatasiz) talepte sorar:
 * hatasini duzelten ziyaretci beklemez.
 */
function nwcs_form_rate_limited(): bool {
	$times = nwcs_form_rate_times();

	return ( $times && time() - max( $times ) < NWCS_FORM_RATE_GAP ) || count( $times ) >= NWCS_FORM_RATE_HOURLY;
}

/**
 * Kaydedilen talebi sayar (kayittan hemen once cagrilir).
 */
function nwcs_form_rate_hit(): void {
	$times   = nwcs_form_rate_times();
	$times[] = time();

	set_transient( nwcs_form_rate_key(), $times, HOUR_IN_SECONDS );
}

function nwcs_form_rate_message(): string {
	return 'Az önce bir talep gönderdiniz; lütfen biraz sonra yeniden deneyin.';
}
