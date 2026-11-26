<?php
/**
 * Urunden teklif ve WhatsApp.
 *
 * - "Teklif Al" baglantilari iletisim formuna ?urun=<slug> ile gider. Form
 *   bu slug'i sitedeki urun listesinde arar; bulursa konu ve mesaj alanlarini
 *   urunun GERCEK bilgisiyle (ad, kategori, ozellik) doldurur. Adresten gelen
 *   metin hicbir yere yazilmaz; bilinmeyen slug yok sayilir.
 * - Urunden acilan WhatsApp, urunun grubuna gore numaraya gider ve hazir
 *   mesaj tasir. Grup, menu metni degil sabit grup anahtaridir
 *   (kocist_catalog_group_key: kereste, ambalaj, hirdavat, dekorasyon).
 * - Genel WhatsApp baglantilari (footer, iletisim seridi...) mesaj
 *   yazilmamissa ortak varsayilan mesaji alir (kocist_link icinde).
 *
 * Numaralar ve mesaj kaliplari panelde: Urun Sayfalari > "WhatsApp ve Teklif",
 * genel mesaj Ortak Metinler'de.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metin kalibindaki {degisken}leri doldurur. Degeri bos bir degisken iceren
 * satir atilir; "()" gibi bos kalan parantezler temizlenir.
 *
 * @param array<string, string> $vars
 */
function kocist_fill_template( string $template, array $vars ): string {
	$lines = array();

	foreach ( preg_split( '/\R/u', $template ) as $line ) {
		foreach ( $vars as $key => $value ) {
			if ( '' === trim( (string) $value ) && str_contains( $line, '{' . $key . '}' ) ) {
				// Satirda baska metin de varsa yalnizca degisken silinir.
				$rest = trim( str_replace( '{' . $key . '}', '', $line ) );
				$line = ( '' === $rest || str_ends_with( $rest, ':' ) ) ? null : $line;

				if ( null === $line ) {
					continue 2;
				}
			}
		}

		$lines[] = $line;
	}

	$map = array();
	foreach ( $vars as $key => $value ) {
		$map[ '{' . $key . '}' ] = trim( (string) $value );
	}

	$text = strtr( implode( "\n", $lines ), $map );
	$text = (string) preg_replace( '/[ \t]*\(\s*\)/u', '', $text );
	$text = (string) preg_replace( '/[ \t]{2,}/u', ' ', $text );

	return trim( $text );
}

/**
 * Urunun konusulacak bilgileri: ad, kategori, grup anahtari, adres, ozellik.
 *
 * @return array{name:string, category:string, group:string, url:string, spec:string, slug:string}
 */
function kocist_product_context( array $product ): array {
	$groups = kocist_catalog_groups();
	$group  = $groups[ $product['group'] ?? '' ] ?? null;
	$sub    = $group['subs'][ $product['sub'] ?? '' ] ?? null;
	$url    = kocist_product_has_page( $product ) ? (string) ( $product['url'] ?? '' ) : '';

	return array(
		'name'     => (string) ( $product['title'] ?? '' ),
		'category' => (string) ( $sub['name'] ?? $group['name'] ?? '' ),
		'group'    => (string) ( $group['slug'] ?? '' ),
		'url'      => $url,
		'spec'     => trim( (string) preg_replace( '/\s*\R\s*/u', '; ', (string) ( $product['spec'] ?? '' ) ) ),
		'slug'     => (string) ( $product['slug'] ?? '' ),
	);
}

/* ------------------------------------------------------------------ */
/* WhatsApp                                                             */
/* ------------------------------------------------------------------ */

/**
 * WhatsApp baglantisi mi (wa.me, api.whatsapp.com)?
 */
function kocist_is_whatsapp_url( $url ): bool {
	$host = strtolower( (string) wp_parse_url( trim( (string) $url ), PHP_URL_HOST ) );

	return in_array( $host, array( 'wa.me', 'www.wa.me', 'api.whatsapp.com' ), true );
}

/**
 * Yazilan numarayi wa.me bicimine cevirir: "0532 374 98 32" -> 905323749832.
 */
function kocist_wa_digits( string $number ): string {
	$digits = (string) preg_replace( '/\D+/', '', $number );

	if ( str_starts_with( $digits, '00' ) ) {
		$digits = substr( $digits, 2 );
	} elseif ( str_starts_with( $digits, '0' ) ) {
		$digits = '90' . substr( $digits, 1 );
	} elseif ( 10 === strlen( $digits ) && str_starts_with( $digits, '5' ) ) {
		$digits = '90' . $digits;
	}

	return strlen( $digits ) >= 10 ? $digits : '';
}

/**
 * Mesajli wa.me adresi. Mesaj rawurlencode ile kodlanir.
 */
function kocist_wa_url( string $digits, string $message = '' ): string {
	if ( '' === $digits ) {
		return '';
	}

	$url = 'https://wa.me/' . $digits;

	return '' !== trim( $message ) ? $url . '?text=' . rawurlencode( $message ) : $url;
}

/**
 * Mevcut bir WhatsApp adresine (mesaj yoksa) mesaj ekler.
 */
function kocist_wa_with_text( string $url, string $message ): string {
	if ( '' === trim( $message ) || ! kocist_is_whatsapp_url( $url ) ) {
		return $url;
	}

	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	parse_str( $query, $args );

	if ( isset( $args['text'] ) && '' !== trim( (string) $args['text'] ) ) {
		return $url;
	}

	$base = (string) preg_replace( '/[?#].*$/', '', $url );
	unset( $args['text'] );
	$rest = $args ? http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) . '&' : '';

	return $base . '?' . $rest . 'text=' . rawurlencode( $message );
}

/**
 * Genel WhatsApp mesaji (urunden bagimsiz dugmeler).
 */
function kocist_wa_default_message(): string {
	$text = trim( (string) nwcs_field( 'global', 'texts', 'wa_message' ) );

	return '' !== $text ? $text : 'Merhaba, Koçist web sitesinden ulaşıyorum. Bilgi almak istiyorum.';
}

/**
 * Urun grubunun WhatsApp numarasi (wa.me rakamlari); tanimsizsa bos.
 *
 * Her bolum kendi hattina gider: kereste 0538, ambalaj ve hirdavat 0532,
 * dekorasyon 0549 (Google Isletme Profillerindeki WhatsApp numaralari).
 * Anahtarlar sabit grup anahtaridir, menude gorunen ad degil.
 */
function kocist_group_wa_digits( string $group ): string {
	$fields = array(
		'kereste'    => array( 'wa_kereste', '0538 609 48 62' ),
		'ambalaj'    => array( 'wa_ambalaj', '0532 374 98 32' ),
		'hirdavat'   => array( 'wa_hirdavat', '0532 374 98 32' ),
		'dekorasyon' => array( 'wa_dekorasyon', '0549 648 19 19' ),
	);

	if ( ! isset( $fields[ $group ] ) ) {
		return '';
	}

	$number = trim( (string) nwcs_field( 'product', 'whatsapp', $fields[ $group ][0] ) );

	return kocist_wa_digits( '' !== $number ? $number : $fields[ $group ][1] );
}

/**
 * Urunden acilan WhatsApp adresi: grubun numarasi + urun mesaji.
 *
 * Grubu belli olmayan urunde $fallback (urun sayfasinin genel WhatsApp
 * baglantisi) kullanilir; yine de urun mesaji eklenir.
 *
 * @param array $product Havuz urunu (kocist_catalog_products ogesi) ya da
 *                       array( 'title', 'category', 'url' ) ile ornek urun.
 */
function kocist_product_wa_url( array $product, string $fallback = '' ): string {
	$context = isset( $product['category'] ) && ! isset( $product['id'] )
		? array_merge( array( 'name' => '', 'category' => '', 'group' => '', 'url' => '', 'spec' => '' ), $product )
		: kocist_product_context( $product );

	$template = trim( (string) nwcs_field( 'product', 'whatsapp', 'product_msg' ) );
	$message  = kocist_fill_template(
		'' !== $template ? $template : 'Merhaba, {urun} ({kategori}) hakkında bilgi almak istiyorum. {url}',
		array(
			'urun'     => $context['name'],
			'kategori' => $context['category'],
			'url'      => $context['url'],
			'ozellik'  => $context['spec'],
		)
	);

	$digits = kocist_group_wa_digits( $context['group'] );

	if ( '' !== $digits ) {
		return kocist_wa_url( $digits, $message );
	}

	if ( kocist_is_whatsapp_url( $fallback ) ) {
		$base = (string) preg_replace( '/[?#].*$/', '', $fallback );

		return kocist_wa_with_text( $base, $message );
	}

	return $fallback;
}

/* ------------------------------------------------------------------ */
/* Teklif formu                                                         */
/* ------------------------------------------------------------------ */

/**
 * Urunden teklif adresi: iletisim formu + ?urun=<slug>.
 */
function kocist_quote_url( array $product = array() ): string {
	$base = kocist_link( nwcs_field( 'product', 'main', 'cta_url' ) ?: '#teklif' );
	$slug = sanitize_title( (string) ( $product['slug'] ?? '' ) );

	if ( '' === $slug || str_starts_with( $base, '#' ) || kocist_is_whatsapp_url( $base ) || ! str_starts_with( $base, 'http' ) ) {
		return $base;
	}

	// Formun kendisine insin: capa yoksa form bolumu.
	$url = add_query_arg( 'urun', $slug, $base );

	return str_contains( $url, '#' ) ? $url : $url . '#teklif-formu';
}

/**
 * Adresteki ?urun= degerine karsilik gelen urun; yoksa null. Deger yalnizca
 * arama anahtari olarak kullanilir.
 */
function kocist_quote_product(): ?array {
	static $found = false;
	static $product = null;

	if ( $found ) {
		return $product;
	}

	$found = true;
	$slug  = isset( $_GET['urun'] ) ? sanitize_title( wp_unslash( (string) $_GET['urun'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- yalnizca okuma.

	if ( '' === $slug || ! function_exists( 'kocist_catalog_products' ) ) {
		return null;
	}

	// Adres panelde degistirildiyse eski baglanti (havuz adresi ya da onceki adres) de bulunur.
	if ( function_exists( 'nwcs_product_slug_resolve' ) ) {
		$slug = nwcs_product_slug_resolve( $slug ) ?: $slug;
	}

	foreach ( kocist_catalog_products() as $candidate ) {
		if ( (string) ( $candidate['slug'] ?? '' ) === $slug ) {
			$product = $candidate;
			break;
		}
	}

	return $product;
}

/**
 * Formun on dolgusu: konu ve mesaj. Urun yoksa bos degerler.
 *
 * @return array{product:?array, context:array, subject:string, message:string}
 */
function kocist_quote_prefill(): array {
	$product = kocist_quote_product();

	if ( ! $product ) {
		return array( 'product' => null, 'context' => array(), 'subject' => '', 'message' => '' );
	}

	$context = kocist_product_context( $product );
	$vars    = array(
		'urun'     => $context['name'],
		'kategori' => $context['category'],
		'url'      => $context['url'],
		'ozellik'  => $context['spec'],
	);

	$subject = trim( (string) nwcs_field( 'product', 'whatsapp', 'quote_subject' ) );
	$message = trim( (string) nwcs_field( 'product', 'whatsapp', 'quote_msg' ) );

	return array(
		'product' => $product,
		'context' => $context,
		'subject' => kocist_fill_template( '' !== $subject ? $subject : '{urun} ({kategori})', $vars ),
		'message' => kocist_fill_template( '' !== $message ? $message : "Merhaba, {urun} ({kategori}) için fiyat teklifi almak istiyorum.\nÖzellik: {ozellik}\nÖlçü ve adet: ", $vars ),
	);
}

/* ------------------------------------------------------------------ */
/* Fiyati olmayan urun: neden                                           */
/* ------------------------------------------------------------------ */

/**
 * Fiyati gosterilmeyen urunde nedeni anlatan metin. Oncelik: grubun metni
 * (WhatsApp ve Teklif > "Fiyat gösterilmeme nedeni: <grup>"), bossa site
 * geneli metin (Havuz Urun Sayfalari > "Fiyatı olmayan ürün: nedeni").
 * Ileride urune ozel bir alan gelirse ilk once o okunur (tek nokta).
 *
 * @return array{text:string, field:string} field: metnin panel alani (onizleme icin).
 */
function kocist_price_reason( array $product ): array {
	$group = kocist_product_context( $product )['group'];

	if ( in_array( $group, array( 'kereste', 'ambalaj', 'hirdavat', 'dekorasyon' ), true ) ) {
		$text = trim( (string) nwcs_field( 'product', 'whatsapp', 'reason_' . $group ) );

		if ( '' !== $text ) {
			return array( 'text' => $text, 'field' => 'whatsapp.reason_' . $group );
		}
	}

	return array( 'text' => trim( (string) nwcs_field( 'product', 'detail', 'price_reason' ) ), 'field' => 'detail.price_reason' );
}
