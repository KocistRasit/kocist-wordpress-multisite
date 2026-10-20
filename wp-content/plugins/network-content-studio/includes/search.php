<?php
/**
 * Urun aramasi: Turkce harf katlama, esnek eslestirme ve siralama.
 *
 * Panelin aramalari (Urun Havuzu listesi, secim kutulari) ve sitelerin urun
 * aramasi ayni katlamayi kullanir (nwcs_search_fold):
 *   - ı/i/İ/I, ş/s, ğ/g, ü/u, ö/o, ç/c ve â/î/û ayni harf sayilir;
 *   - buyuk/kucuk harf farki yok.
 *
 * Site aramasi (nwcs_search_products; Kocist /arama/) ayrica:
 *   - bosluk, tire ve nokta gibi ayiraclari yok sayar: "ADR K", "adr-k" ve
 *     "adrk" ayni terimdir ("WKC-ADR-K-TK" bulunur);
 *   - kelimeleri sirasiz da arar: "3x3 kamelya" = "kamelya 3x3";
 *   - kod, ad ve kategori adlarinda arar;
 *   - siralar: kodu tam tutan, kodu terimle baslayan, kodunda gecen, adinda ya
 *     da kategorisinde gecen, en sonda kelimeleri ayri ayri gecen urunler.
 *     Ayni siradakiler listedeki sirasini korur.
 *
 * Renk gruplari sadelestirilmez: her renk ayri urundur, kodla arayan dogru
 * urunu bulur (includes/color-groups.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aramada Turkce harf ve buyuk/kucuk harf farki gozetilmesin diye metni
 * sadelestirir: "ÇİVİ", "çivi", "civi" ayni sonucu verir.
 */
function nwcs_search_fold( string $text ): string {
	$text = mb_strtolower( strtr( $text, array( 'İ' => 'i', 'I' => 'ı' ) ), 'UTF-8' );

	return strtr( $text, array( 'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u' ) );
}

/**
 * Katlanmis metnin yalnizca harf ve rakamlari: "WKC-ADR K.TK" -> "wkcadrktk".
 */
function nwcs_search_compact( string $text ): string {
	return (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', nwcs_search_fold( $text ) );
}

/**
 * Terimin kelimeleri (katlanmis, ayiracsiz); bos kelime yok.
 *
 * @return string[]
 */
function nwcs_search_words( string $term ): array {
	$words = array();

	foreach ( preg_split( '/\s+/u', nwcs_search_fold( $term ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $word ) {
		$word = nwcs_search_compact( $word );

		if ( '' !== $word ) {
			$words[] = $word;
		}
	}

	return $words;
}

/**
 * Urunun terimle eslesme derecesi; eslesmiyorsa -1. Kucuk sayi once gelir:
 *   0 kodu tam tutuyor, 1 kodu terimle basliyor, 2 kodunda geciyor,
 *   3 adinda ya da kategorisinde geciyor, 4 kelimeleri ayri ayri geciyor.
 *
 * @param array    $product Urun satiri (code, title, categories).
 * @param string[] $extra   Aranacak ek metinler (sitede gorunen kategori adi gibi).
 */
function nwcs_search_rank( array $product, string $term, array $extra = array() ): int {
	$needle = nwcs_search_compact( $term );

	if ( '' === $needle ) {
		return -1;
	}

	$code = nwcs_search_compact( (string) ( $product['code'] ?? '' ) );

	if ( '' !== $code ) {
		if ( $code === $needle ) {
			return 0;
		}

		if ( str_starts_with( $code, $needle ) ) {
			return 1;
		}

		if ( str_contains( $code, $needle ) ) {
			return 2;
		}
	}

	// Her alan ayri sadelesir; "|" ayrac: bir kelime iki alanin birlesiminden eslesmesin.
	$fields = array_merge(
		array( (string) ( $product['title'] ?? '' ) ),
		array_map( 'strval', array_values( (array) ( $product['categories'] ?? array() ) ) ),
		array_map( 'strval', $extra )
	);
	$text   = implode( '|', array_filter( array_map( 'nwcs_search_compact', $fields ) ) );

	if ( '' !== $text && str_contains( $text, $needle ) ) {
		return 3;
	}

	$all = '' !== $code ? $code . '|' . $text : $text;

	foreach ( nwcs_search_words( $term ) as $word ) {
		if ( ! str_contains( $all, $word ) ) {
			return -1;
		}
	}

	return 4;
}

/**
 * Terimle eslesen urunler, siralanmis (bkz. dosya basi). Bos terimde bos dizi.
 *
 * @param array<int, array> $products Site urun listesi (renk grubu sadelestirilmemis).
 * @param callable|null     $extra    Urun satirindan ek arama metinleri (string[]) dondurur.
 * @return array<int, array>
 */
function nwcs_search_products( array $products, string $term, ?callable $extra = null ): array {
	if ( '' === nwcs_search_compact( $term ) ) {
		return array();
	}

	$found = array();

	foreach ( array_values( $products ) as $index => $product ) {
		$rank = nwcs_search_rank( $product, $term, $extra ? (array) $extra( $product ) : array() );

		if ( $rank >= 0 ) {
			$found[] = array( $rank, $index, $product );
		}
	}

	usort( $found, static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );

	return array_column( $found, 2 );
}
