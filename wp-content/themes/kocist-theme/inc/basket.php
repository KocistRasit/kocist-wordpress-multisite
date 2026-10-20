<?php
/**
 * Teklif sepeti (/teklif-sepeti/). Siparis ve odeme YOK: ziyaretci urunleri
 * toplar, hepsi icin tek seferde fiyat ister. Yasal satis sozlesmesi gerekmez;
 * e-postayla gonderimde yalnizca KVKK onayi alinir.
 *
 * Sepet ziyaretcinin tarayicisinda tek bir cerezde durur ("urunKimligi:adet|...",
 * WOOD KOCIST inc/cart.php ile ayni bicim, ayri cerez adi). Cerezde yalnizca
 * kimlik ve adet var; ad, kod ve fiyat her zaman sunucuda Urun Havuzu'ndan
 * okunur. Fiyatli ya da fiyatsiz her urun eklenebilir; toplam yok.
 *
 * Onbellek: sayfalar onbellekte kalabilir. Ust menudeki sayi betikle cerezden
 * okunur (assets/js/teklif.js); sepet sayfasi, urun sayfasinin "eklendi"
 * donusu (?teklif=) ve isleyiciler onbellege alinmaz (LiteSpeed no-cache).
 *
 * JavaScript varsa teklif.js cerezi kendisi yazar ve sayfa yenilenmez; yoksa
 * formlar admin-post.php'ye gider, cerez burada yazilir. Iki yol ayni bicim.
 *
 * Iki gonderme yolu:
 *   1. E-posta: mevcut form akisi (inc/form.php): "Teklif Talepleri" kaydi ve
 *      eklentinin bildirim e-postasi (includes/forms.php, alici
 *      kocist_form_recipient). Bildirim burada hemen gonderilir ve sonucu
 *      beklenir: e-posta gitmezse ziyaretciye basari degil hata gosterilir.
 *   2. WhatsApp: form yok; urun listesiyle hazir mesaj. Butun urunler ayni
 *      urun grubundaysa o grubun numarasi (inc/quote.php), degilse genel numara.
 *      Sepet kendiliginden bosalmaz; "Sepeti temizle" vardir.
 */

defined( 'ABSPATH' ) || exit;

const KOCIST_BASKET_COOKIE = 'kc_teklif';
const KOCIST_BASKET_MAX    = 30;   // Sepette en fazla urun (satir).
const KOCIST_BASKET_QTY    = 9999; // Satir basina en fazla adet.
const KOCIST_BASKET_WA_MAX = 1900; // Hazir mesajli wa.me adresinin en fazla uzunlugu.

function kocist_basket_url(): string {
	return home_url( '/teklif-sepeti/' );
}

/**
 * Cerezin yolu: sitenin adresi ("/" canlida, "/kocist/" yerelde). Betik de
 * ayni yolu kullanir (ust menudeki baglantinin data-path niteligi).
 */
function kocist_basket_cookie_path(): string {
	$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	return '' !== $path ? trailingslashit( $path ) : '/';
}

/**
 * Cerezdeki ham sepet: [ urunKimligi => adet ], cerezdeki sirayla.
 *
 * @return array<int, int>
 */
function kocist_basket_raw(): array {
	$raw   = isset( $_COOKIE[ KOCIST_BASKET_COOKIE ] ) && is_string( $_COOKIE[ KOCIST_BASKET_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ KOCIST_BASKET_COOKIE ] ) ) : '';
	$items = array();

	foreach ( explode( '|', $raw ) as $pair ) {
		if ( ! preg_match( '/^(\d{1,10}):(\d{1,4})$/', $pair, $match ) ) {
			continue;
		}

		$id  = (int) $match[1];
		$qty = min( KOCIST_BASKET_QTY, (int) $match[2] );

		if ( $id > 0 && $qty > 0 && ( isset( $items[ $id ] ) || count( $items ) < KOCIST_BASKET_MAX ) ) {
			$items[ $id ] = $qty;
		}
	}

	return $items;
}

/**
 * Sepeti cereze yazar; bos sepet cerezi siler.
 *
 * @param array<int, int> $items
 */
function kocist_basket_save( array $items ): void {
	$pairs = array();

	foreach ( $items as $id => $qty ) {
		$id  = (int) $id;
		$qty = min( KOCIST_BASKET_QTY, (int) $qty );

		if ( $id > 0 && $qty > 0 ) {
			$pairs[] = $id . ':' . $qty;
		}
	}

	$value = implode( '|', array_slice( $pairs, 0, KOCIST_BASKET_MAX ) );

	if ( ! headers_sent() ) {
		setcookie(
			KOCIST_BASKET_COOKIE,
			$value,
			array(
				'expires'  => '' === $value ? time() - HOUR_IN_SECONDS : time() + 30 * DAY_IN_SECONDS,
				'path'     => kocist_basket_cookie_path(), // teklif.js ile ayni yol; farkli olursa iki ayri cerez olusur.
				'secure'   => is_ssl(),
				'httponly' => false, // teklif.js de okuyup yazar.
				'samesite' => 'Lax',
			)
		);
	}

	$_COOKIE[ KOCIST_BASKET_COOKIE ] = $value;
}

/**
 * Sitedeki urunler kimlige gore (renk grubu sadelestirilmemis: her renk ayri urun).
 *
 * @return array<int, array>
 */
function kocist_basket_products(): array {
	$map = array();

	foreach ( kocist_catalog_products() as $product ) {
		$map[ (int) $product['id'] ] = $product;
	}

	return $map;
}

/**
 * Sepetin sunucuda okunmus hali. Artik sitede olmayan urunler satirlara girmez.
 *
 * @return array{lines: array<int, array{product:array, qty:int}>, count:int, removed:int}
 */
function kocist_basket(): array {
	$products = kocist_basket_products();
	$lines    = array();
	$removed  = 0;

	foreach ( kocist_basket_raw() as $id => $qty ) {
		if ( ! isset( $products[ $id ] ) ) {
			++$removed;
			continue;
		}

		$lines[] = array(
			'product' => $products[ $id ],
			'qty'     => $qty,
		);
	}

	return array(
		'lines'   => $lines,
		'count'   => count( $lines ),
		'removed' => $removed,
	);
}

/**
 * Sepet sayfasi acilirken cerezden cikarilan (sitede artik olmayan) urun sayisi.
 */
function kocist_basket_pruned( ?int $set = null ): int {
	static $pruned = 0;

	if ( null !== $set ) {
		$pruned = $set;
	}

	return $pruned;
}

/* ---------------------------------------------------------------------- */
/* Onbellek, ekleme / guncelleme / silme (JavaScript'siz yol)              */
/* ---------------------------------------------------------------------- */

function kocist_basket_nocache_headers(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
}

/**
 * Sepet sayfasi ve urun sayfasinin "eklendi" donusu onbellege alinmaz (cerezle
 * degisir). Eklentinin urun sablonundan (5) once calisir.
 */
add_action( 'template_redirect', 'kocist_basket_nocache', 1 );
function kocist_basket_nocache(): void {
	$is_basket = is_page( 'teklif-sepeti' );

	if ( $is_basket || ( get_query_var( 'nwcs_product' ) && isset( $_GET['teklif'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		kocist_basket_nocache_headers();
	}

	if ( ! $is_basket ) {
		return;
	}

	// Sitede artik olmayan urunler cerezden de cikar; sayfada bir satirla bildirilir.
	$raw  = kocist_basket_raw();
	$keep = array_intersect_key( $raw, kocist_basket_products() );

	if ( count( $keep ) !== count( $raw ) ) {
		kocist_basket_pruned( count( $raw ) - count( $keep ) );
		kocist_basket_save( $keep );
	}
}

/**
 * Donus adresi: formun bildirdigi bu sitenin adresi, yoksa sepet sayfasi.
 * Onceki donus parametreleri ve capa atilir.
 */
function kocist_basket_return( string $fallback ): string {
	$return = esc_url_raw( wp_unslash( (string) ( $_POST['kc_return'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// Yolun kodlanmis hali de cozulur ("%2e%2e" tarayicida ".." olur; ic ice kodlama da).
	$path = (string) wp_parse_url( $return, PHP_URL_PATH );

	for ( $round = 0; $round < 3 && rawurldecode( $path ) !== $path; $round++ ) {
		$path = rawurldecode( $path );
	}

	// Yalnizca bu sitenin adresi; "/kocist/../woodkocist/" gibi yol da ayni agdaki baska siteye cikmasin.
	if ( '' === $return || ! str_starts_with( $return, home_url( '/' ) ) || str_contains( $path, '..' ) || str_contains( $path, '\\' ) ) {
		return $fallback;
	}

	// Son kapi inc/form.php'deki gibi: izinli alan adi degilse yedek adres.
	return wp_validate_redirect( (string) strtok( remove_query_arg( array( 'teklif', 'kc' ), $return ), '#' ), $fallback );
}

add_action( 'admin_post_kc_basket', 'kocist_handle_basket' );
add_action( 'admin_post_nopriv_kc_basket', 'kocist_handle_basket' );
function kocist_handle_basket(): void {
	// Nonce yok (bilincli; WOOD KOCIST sepetiyle ayni): urun sayfalari onbellekte
	// bekler, nonce eskir. Sepete ekleme/cikarma baskasi adina yapilsa da yalnizca
	// o tarayicinin cerezini degistirir. Gonderim (kc_basket_send) nonce ile korunur.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	kocist_basket_nocache_headers();

	$basket   = kocist_basket_url();
	$items    = kocist_basket_raw();
	$products = kocist_basket_products();
	$do       = sanitize_key( wp_unslash( (string) ( $_POST['kc_do'] ?? '' ) ) );

	if ( 'add' === $do ) {
		$id     = absint( $_POST['kc_id'] ?? 0 );
		$qty    = max( 1, min( KOCIST_BASKET_QTY, absint( $_POST['kc_qty'] ?? 1 ) ) );
		$return = kocist_basket_return( $basket );

		if ( ! isset( $products[ $id ] ) ) {
			wp_safe_redirect( $return );
			exit;
		}

		if ( ! isset( $items[ $id ] ) && count( $items ) >= KOCIST_BASKET_MAX ) {
			wp_safe_redirect( add_query_arg( 'teklif', 'dolu', $return ) . '#teklif-sepeti-ekle' );
			exit;
		}

		$items[ $id ] = min( KOCIST_BASKET_QTY, ( $items[ $id ] ?? 0 ) + $qty );
		kocist_basket_save( $items );

		// Donen sayfa cerezde urunu gorurse "eklendi", goremezse (cerez kapali) hata yazar.
		wp_safe_redirect( add_query_arg( 'teklif', 'eklendi', $return ) . '#teklif-sepeti-ekle' );
		exit;
	}

	if ( 'clear' === $do ) {
		kocist_basket_save( array() );
		wp_safe_redirect( $basket );
		exit;
	}

	// Sepet sayfasindaki tek form: yazilan adetler, +/− ve "Cikar". Adet 0 satiri siler.
	foreach ( (array) ( $_POST['kc_qty'] ?? array() ) as $id => $qty ) {
		$id = absint( $id );

		if ( ! isset( $items[ $id ] ) || ! is_scalar( $qty ) || '' === trim( (string) $qty ) ) {
			continue;
		}

		$qty = absint( $qty );

		if ( $qty > 0 ) {
			$items[ $id ] = min( KOCIST_BASKET_QTY, $qty );
		} else {
			unset( $items[ $id ] );
		}
	}

	$step = sanitize_text_field( wp_unslash( (string) ( $_POST['kc_step'] ?? '' ) ) );

	if ( preg_match( '/^(\d{1,10}):([+-]1)$/', $step, $match ) && isset( $items[ (int) $match[1] ] ) ) {
		$items[ (int) $match[1] ] = max( 1, min( KOCIST_BASKET_QTY, $items[ (int) $match[1] ] + (int) $match[2] ) );
	}

	if ( isset( $_POST['kc_remove'] ) ) {
		unset( $items[ absint( $_POST['kc_remove'] ) ] );
	}

	kocist_basket_save( $items );
	wp_safe_redirect( $basket . '#sepet' );
	exit;
	// phpcs:enable
}

/* ---------------------------------------------------------------------- */
/* Urun satiri metni, WhatsApp mesaji                                      */
/* ---------------------------------------------------------------------- */

/**
 * Tek urun satiri: "WKC-ADR-K-TK – Adirondack Sandalye – 5 adet" (panel:
 * Teklif Sepeti > "ürün satırı"). Kodu olmayan urunde bos kalan ayirac atilir.
 */
function kocist_basket_line( array $product, int $qty ): string {
	$template = trim( (string) nwcs_field( 'teklif', 'send', 'wa_line' ) );
	$template = '' !== $template ? $template : '{kod} – {urun} – {adet} adet';
	$text     = strtr(
		$template,
		array(
			'{kod}'  => trim( (string) ( $product['code'] ?? '' ) ),
			'{urun}' => trim( (string) ( $product['title'] ?? '' ) ),
			'{adet}' => (string) $qty,
		)
	);

	$text = (string) preg_replace( '/^[\s–—|·,:-]+/u', '', $text );
	$text = (string) preg_replace( '/\s+[–—|·]\s+(?:[–—|·]\s+)+/u', ' – ', $text );

	return trim( $text );
}

/**
 * Urunun bu sitedeki sayfasi; sayfasi yoksa bos.
 */
function kocist_basket_product_url( array $product ): string {
	return kocist_product_has_page( $product ) ? (string) ( $product['url'] ?? '' ) : '';
}

/**
 * Kayit ve e-posta icin okunur liste: sira, kod, ad, adet ve sayfa adresi.
 */
function kocist_basket_list_text( array $lines ): string {
	$out = array();

	foreach ( array_values( $lines ) as $index => $line ) {
		$out[] = ( $index + 1 ) . '. ' . kocist_basket_line( $line['product'], (int) $line['qty'] );
		$url   = kocist_basket_product_url( $line['product'] );

		if ( '' !== $url ) {
			$out[] = '   ' . $url;
		}
	}

	return implode( "\n", $out );
}

/**
 * Panelde yazilmis WhatsApp baglantisinin numarasi (wa.me/905... ya da
 * api.whatsapp.com/send?phone=...); yoksa bos.
 */
function kocist_wa_url_digits( string $url ): string {
	$url = trim( $url );

	if ( ! kocist_is_whatsapp_url( $url ) ) {
		return '';
	}

	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
	$number = isset( $args['phone'] ) && is_string( $args['phone'] ) ? $args['phone'] : trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );

	return kocist_wa_digits( $number );
}

/**
 * Genel WhatsApp numarasi: urun sayfalarinin WhatsApp butonu (grubu belli
 * olmayan urun de bunu kullanir), yoksa Iletisim sayfasindaki WhatsApp.
 */
function kocist_general_wa_digits(): string {
	foreach ( array( nwcs_field( 'product', 'main', 'secondary_url' ), nwcs_field( 'contact', 'info', 'whatsapp_url' ) ) as $url ) {
		$digits = kocist_wa_url_digits( (string) $url );

		if ( '' !== $digits ) {
			return $digits;
		}
	}

	return '';
}

/**
 * Sepetin WhatsApp numarasi: butun urunler ayni urun grubundaysa o grubun
 * numarasi (kereste, ambalaj, hirdavat, dekorasyon), degilse genel numara.
 *
 * @param array<int, array{product:array, qty:int}> $lines
 */
function kocist_basket_wa_digits( array $lines ): string {
	$groups = array_values( array_unique( array_map( static fn( array $line ): string => (string) ( $line['product']['group'] ?? '' ), $lines ) ) );

	if ( 1 === count( $groups ) && '' !== $groups[0] ) {
		$digits = kocist_group_wa_digits( $groups[0] );

		if ( '' !== $digits ) {
			return $digits;
		}
	}

	return kocist_general_wa_digits();
}

/**
 * WhatsApp mesaji: giris satiri, her urun bir satir (istenirse altinda sayfa
 * adresi), listenin ilk $shown urunu; kalan varsa "+N ürün daha".
 *
 * @param array<int, array{product:array, qty:int}> $lines
 */
function kocist_basket_wa_message( array $lines, bool $links = true, ?int $shown = null ): string {
	$lines = array_values( $lines );
	$shown = null === $shown ? count( $lines ) : max( 1, min( count( $lines ), $shown ) );
	$intro = trim( (string) nwcs_field( 'teklif', 'send', 'wa_intro' ) );
	$out   = array( '' !== $intro ? $intro : 'Merhaba, Koçist web sitesinden şu ürünler için fiyat teklifi istiyorum:', '' );

	foreach ( array_slice( $lines, 0, $shown ) as $index => $line ) {
		$out[] = ( $index + 1 ) . '. ' . kocist_basket_line( $line['product'], (int) $line['qty'] );
		$url   = $links ? kocist_basket_product_url( $line['product'] ) : '';

		if ( '' !== $url ) {
			$out[] = $url;
		}
	}

	$rest = count( $lines ) - $shown;

	if ( $rest > 0 ) {
		$more  = kocist_text( 'teklif', 'send', 'wa_more', array( 'sayi' => $rest ) );
		$out[] = '';
		$out[] = '' !== $more ? $more : '+' . $rest . ' ürün daha';
	}

	return implode( "\n", $out );
}

/**
 * Hazir mesajli WhatsApp adresi; numara yoksa ya da sepet bossa bos.
 *
 * Adres KOCIST_BASKET_WA_MAX karakteri gecmez (uzun adresleri bazi telefonlar
 * ve tarayicilar acmiyor): once sayfa adresleri atilir, yine sigmiyorsa liste
 * kisalir ve sona "+N ürün daha" yazilir.
 *
 * @param array<int, array{product:array, qty:int}> $lines
 */
function kocist_basket_wa_url( array $lines ): string {
	$digits = kocist_basket_wa_digits( $lines );

	if ( '' === $digits || ! $lines ) {
		return '';
	}

	$total = count( $lines );
	$tries = array( array( true, $total ), array( false, $total ) );

	for ( $shown = $total - 1; $shown >= 1; $shown-- ) {
		$tries[] = array( false, $shown );
	}

	foreach ( $tries as $try ) {
		$url = kocist_wa_url( $digits, kocist_basket_wa_message( $lines, $try[0], $try[1] ) );

		if ( strlen( $url ) <= KOCIST_BASKET_WA_MAX ) {
			return $url;
		}
	}

	// Tek urunun satiri bile sigmiyor (cok uzun ad): ilk urunle acilir.
	return kocist_wa_url( $digits, kocist_basket_wa_message( $lines, false, 1 ) );
}

/* ---------------------------------------------------------------------- */
/* E-posta ile gonderim                                                    */
/* ---------------------------------------------------------------------- */

add_action( 'admin_post_kc_basket_send', 'kocist_handle_basket_send' );
add_action( 'admin_post_nopriv_kc_basket_send', 'kocist_handle_basket_send' );
function kocist_handle_basket_send(): void {
	kocist_basket_nocache_headers();

	$redirect = kocist_basket_url();

	// Bot tuzagi: gorunmeyen alan dolmussa sessizce geri don (kayit ve e-posta yok).
	if ( ! empty( $_POST['kc_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wp_safe_redirect( $redirect );
		exit;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce asagida dogrulaniyor.
	$values = array(
		'name'    => sanitize_text_field( wp_unslash( (string) ( $_POST['kc-name'] ?? '' ) ) ),
		'company' => sanitize_text_field( wp_unslash( (string) ( $_POST['kc-company'] ?? '' ) ) ),
		'phone'   => sanitize_text_field( wp_unslash( (string) ( $_POST['kc-phone'] ?? '' ) ) ),
		// Ham deger: gecersiz adres sessizce silinmesin; asagida is_email ile reddedilir.
		'email'   => trim( sanitize_text_field( wp_unslash( (string) ( $_POST['kc-email'] ?? '' ) ) ) ),
		'message' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['kc-note'] ?? '' ) ) ),
		'consent' => ! empty( $_POST['kc-consent'] ),
	);
	$nonce  = sanitize_key( wp_unslash( (string) ( $_POST['kc_basket_nonce'] ?? '' ) ) );
	// phpcs:enable

	$errors = array();
	$basket = kocist_basket();

	if ( ! wp_verify_nonce( $nonce, 'kc_basket_send' ) ) {
		$errors['form'] = 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.';
	} elseif ( ! $basket['lines'] ) {
		$errors['form'] = (string) nwcs_field( 'teklif', 'send', 'empty_error' );
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

	if ( ! $values['consent'] ) {
		$errors['consent'] = 'Onay kutusunu işaretleyin.';
	}

	// Hiz siniri (inc/form.php; teklif formuyla ortak): yalnizca gecerli talepte.
	if ( ! $errors && kocist_form_rate_limited() ) {
		$errors['form'] = kocist_form_rate_message();
	}

	if ( $errors ) {
		kocist_quote_redirect( $redirect, array( 'errors' => $errors, 'values' => $values ), 'teklif-gonder' );
	}

	kocist_form_rate_hit();

	$count   = count( $basket['lines'] );
	$list    = kocist_basket_list_text( $basket['lines'] );
	$subject = sprintf( 'Teklif sepeti (%d ürün)', $count );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'kc_quote',
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $values['name'], $subject ),
			'post_content' => sprintf(
				"Telefon: %s\nE-posta: %s\n%sKonu: %s\n\nÜrünler:\n%s\n\nNot:\n%s\n\nOnay kutusu: işaretli",
				$values['phone'] ?: '—',
				$values['email'] ?: '—',
				'' !== $values['company'] ? 'Firma: ' . $values['company'] . "\n" : '',
				$subject,
				$list,
				'' !== $values['message'] ? $values['message'] : '—'
			),
			// Bildirim e-postasinda "Ürünler" listesi (eklenti forms.php etiketi 'items').
			'meta_input'   => array_merge(
				array(
					'_kc_name'    => $values['name'],
					'_kc_phone'   => $values['phone'],
					'_kc_email'   => $values['email'],
					'_kc_subject' => $subject,
					'_kc_items'   => $list,
					'_kc_message' => $values['message'],
				),
				'' !== $values['company'] ? array( '_kc_company' => $values['company'] ) : array()
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		kocist_quote_redirect( $redirect, array( 'errors' => array( 'form' => (string) nwcs_field( 'teklif', 'send', 'save_error' ) ), 'values' => $values ), 'teklif-gonder' );
	}

	/*
	 * Bildirim hemen gonderilir ve sonucu beklenir (eklenti normalde istegin
	 * sonuna erteler ve sonucu ziyaretciye yansitmaz). Kayit "bekliyor" iken
	 * gonderilir; istegin sonundaki kuyruk ayni kaydi ikinci kez gondermez.
	 */
	$sent = function_exists( 'nwcs_form_send_notification' ) && nwcs_form_send_notification( (int) $post_id );

	if ( ! $sent ) {
		// Kayit duruyor (panelde gorunur); sepet ve yazilanlar korunur, WhatsApp yolu acik kalir.
		kocist_quote_redirect( $redirect, array( 'errors' => array( 'form' => (string) nwcs_field( 'teklif', 'send', 'mail_error' ) ), 'values' => $values ), 'teklif-gonder' );
	}

	kocist_basket_save( array() );
	kocist_quote_redirect( $redirect, array( 'success' => true ), 'teklif-gonder' );
}

/* ---------------------------------------------------------------------- */
/* Urun sayfasi: "Teklif sepetine ekle"                                    */
/* ---------------------------------------------------------------------- */

/**
 * Adet secici: −, sayi kutusu, +. Urun sayfasinda dugmeler betikle acilir
 * (betik yoksa yalnizca kutu); sepet sayfasinda dugmeler formu gonderir.
 *
 * @param array $args { id, name, value, min, label (ekran okuyucu eki), submit (sepet satiri kimligi; 0: betikli dugme) }
 */
function kocist_qty_field( array $args ): void {
	$less   = (string) nwcs_field( 'teklif', 'add', 'qty_less' );
	$more   = (string) nwcs_field( 'teklif', 'add', 'qty_more' );
	$suffix = '' !== ( $args['label'] ?? '' ) ? ': ' . $args['label'] : '';
	$submit = (int) ( $args['submit'] ?? 0 );
	$value  = (int) $args['value'];
	$min    = (int) ( $args['min'] ?? 1 );

	$button = static function ( int $step, string $text, string $sign ) use ( $submit, $value, $suffix ): void {
		if ( $submit ) {
			// Sepet satiri: betik olmadan da calisan gonder dugmesi.
			printf(
				'<button type="submit" class="k-qty__btn" name="kc_step" value="%1$s" data-k-step="%2$d"%3$s><span aria-hidden="true">%4$s</span><span class="screen-reader-text">%5$s</span></button>',
				esc_attr( $submit . ':' . ( $step > 0 ? '+1' : '-1' ) ),
				(int) $step,
				$step < 0 && $value <= 1 ? ' disabled' : '',
				esc_html( $sign ),
				esc_html( $text . $suffix )
			);
		} else {
			printf(
				'<button type="button" class="k-qty__btn" data-k-step="%1$d" hidden><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></button>',
				(int) $step,
				esc_html( $sign ),
				esc_html( $text . $suffix )
			);
		}
	};
	?>
	<div class="k-qty" data-k-qty>
		<?php $button( -1, $less, '−' ); ?>
		<input class="k-qty__input" id="<?php echo esc_attr( $args['id'] ); ?>" type="number" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo (int) $value; ?>" min="<?php echo (int) $min; ?>" max="<?php echo (int) KOCIST_BASKET_QTY; ?>" step="1" inputmode="numeric" />
		<?php $button( 1, $more, '+' ); ?>
	</div>
	<?php
}

/**
 * Urun sayfasindaki ekleme formu. Betik formu yakalar, cerezi yazar ve
 * sonucu canli bolgede yazar; betik yoksa form admin-post.php'ye gider ve
 * sayfa ?teklif=eklendi ile doner (o donus cerezi okuyup sonucu yazar).
 */
function kocist_basket_add_form( array $product ): void {
	if ( ! kocist_page_ready( 'teklif-sepeti' ) || empty( $product['id'] ) ) {
		return;
	}

	$preview = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
	$id      = (int) $product['id'];
	$state   = isset( $_GET['teklif'] ) ? sanitize_key( wp_unslash( (string) $_GET['teklif'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca okuma.
	$items   = kocist_basket_raw();
	$notice  = '';
	$is_ok   = false;

	if ( 'eklendi' === $state && isset( $items[ $id ] ) ) {
		$notice = kocist_text( 'teklif', 'add', 'added', array( 'adet' => $items[ $id ] ) );
		$is_ok  = true;
	} elseif ( 'eklendi' === $state ) {
		$notice = (string) nwcs_field( 'teklif', 'add', 'no_cookie' );
	} elseif ( 'dolu' === $state ) {
		$notice = kocist_text( 'teklif', 'add', 'full', array( 'sayi' => KOCIST_BASKET_MAX ) );
	}

	$hint = trim( (string) nwcs_field( 'teklif', 'add', 'hint' ) );
	?>
	<div class="k-badd" id="teklif-sepeti-ekle">
		<form class="k-badd__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-k-basket-add data-id="<?php echo (int) $id; ?>">
			<input type="hidden" name="action" value="kc_basket" />
			<input type="hidden" name="kc_do" value="add" />
			<input type="hidden" name="kc_id" value="<?php echo (int) $id; ?>" />
			<input type="hidden" name="kc_return" value="<?php echo esc_url( (string) ( $product['url'] ?? '' ) ); ?>" />

			<div class="k-badd__qty">
				<label class="k-badd__label" for="kc-add-qty" <?php nwcs_edit_attr( 'teklif', 'add', 'qty_label' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'add', 'qty_label' ) ); ?></label>
				<?php kocist_qty_field( array( 'id' => 'kc-add-qty', 'name' => 'kc_qty', 'value' => 1 ) ); ?>
			</div>

			<?php // Panel onizlemesinde gonderim yok: metin tiklanabilsin diye type="button". ?>
			<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="k-product__btn k-product__btn--primary k-badd__btn">
				<?php kocist_basket_icon( 'k-badd__icon' ); ?>
				<span <?php nwcs_edit_attr( 'teklif', 'add', 'button' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'add', 'button' ) ); ?></span>
			</button>
		</form>

		<?php
		/*
		 * Canli bolge: betik eklemeyi burada duyurur ("eklendi", "dolu", cerez
		 * kapali). Metin kaliplari panelden; {adet} betikte doldurulur.
		 */
		?>
		<div class="k-badd__status<?php echo '' !== $notice ? ( $is_ok ? ' is-ok' : ' is-err' ) : ''; ?>" role="status" data-k-basket-status
			data-added="<?php echo esc_attr( (string) nwcs_field( 'teklif', 'add', 'added' ) ); ?>"
			data-full="<?php echo esc_attr( kocist_text( 'teklif', 'add', 'full', array( 'sayi' => KOCIST_BASKET_MAX ) ) ); ?>"
			data-error="<?php echo esc_attr( (string) nwcs_field( 'teklif', 'add', 'no_cookie' ) ); ?>"
			data-go="<?php echo esc_attr( (string) nwcs_field( 'teklif', 'add', 'go' ) ); ?>"
			data-max-lines="<?php echo (int) KOCIST_BASKET_MAX; ?>"
			data-max-qty="<?php echo (int) KOCIST_BASKET_QTY; ?>"><?php if ( '' !== $notice ) : ?><span class="k-badd__msg"><?php echo esc_html( $notice ); ?></span><?php if ( $is_ok ) : ?> <a class="k-badd__go" href="<?php echo esc_url( kocist_basket_url() ); ?>" <?php nwcs_edit_attr( 'teklif', 'add', 'go' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'add', 'go' ) ); ?></a><?php endif; ?><?php endif; ?></div>

		<?php if ( '' !== $hint ) : ?>
			<p class="k-badd__hint" <?php nwcs_edit_attr( 'teklif', 'add', 'hint' ); ?>><?php echo esc_html( $hint ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Teklif sepeti simgesi (pano + liste): urun sayfasi, ust menu.
 */
function kocist_basket_icon( string $class = '', int $size = 20 ): void {
	printf(
		'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 4h6v3H9z" /><path d="M15 5.5h2.5A1.5 1.5 0 0 1 19 7v12.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19.5V7a1.5 1.5 0 0 1 1.5-1.5H9" /><path d="M9 11.5h6M9 15.5h4" /></svg>',
		esc_attr( $class ),
		(int) $size
	);
}
