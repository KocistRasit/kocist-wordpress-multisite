<?php
/**
 * Urun detay basliklari ve degerleri.
 *
 * Urunun teknik detaylari sirali bir liste olarak '_nwcs_details' kaydinda
 * durur: [ { label, value }, ... ]. Eski tek satirlik '_nwcs_spec' metni
 * ("Ahşap Cinsi: Çam; Kurulum: Kolay") bundan turetilir ve temalar ile arama
 * icin korunur. Ikisini yazan TEK fonksiyon nwcs_product_write_details();
 * urun formu, Excel senkronu, Havuz Paketi ve baslik ekrani hep onu cagirir.
 *
 * Okuma tembel: '_nwcs_details' yoksa spec metni ayrisitirilir; bir kez
 * kaydedilene kadar veritabanina hicbir sey yazilmaz (migrasyon gerekmez).
 *
 * Baslik kaydi (ag geneli, site option 'nwcs_product_headings'):
 *   anahtar => { label, required, order }
 * Anahtar, etiketin sadelestirilmis halidir ("Ahşap Cinsi" -> "ahsapcinsi");
 * boylece "AHŞAP CİNSİ" ile "Ahşap cinsi" ayni basliktir.
 * Zorunlu basliklar global: yalnizca Excel yuklemesinde satiri reddeder,
 * urun formunda yalnizca uyari verir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_HEADINGS_OPTION = 'nwcs_product_headings';
const NWCS_DETAILS_META    = '_nwcs_details';

/**
 * Etiketin karsilastirma anahtari: kucuk harf, Turkce harfler sade, yalnizca
 * harf ve rakam.
 */
function nwcs_heading_key( string $label ): string {
	return (string) preg_replace( '/[^a-z0-9]/', '', nwcs_search_fold( trim( $label ) ) );
}

/**
 * Etiketin kayitli hali: tek satir; ':' ve ';' spec metnini ("Etiket: deger;
 * ...") bozacagi icin ':' -> ' -', ';' -> ','. Kayit, urun detaylari ve
 * Excel ayni temizligi kullanir; boylece etiket her yerde ayni kalir.
 */
function nwcs_heading_clean_label( string $label ): string {
	return trim( str_replace( array( ':', ';' ), array( ' -', ',' ), nwcs_clean_text( $label ) ) );
}

/**
 * Degerin kayitli hali: tek satir, ';' -> ','.
 */
function nwcs_detail_clean_value( string $value ): string {
	return str_replace( ';', ',', nwcs_clean_text( $value ) );
}

/**
 * Excel'deki sistem sutunlarinin anahtarlari (KİMLİK, ÜRÜN KODU, ÜRÜN ADI,
 * FİYAT, KISA AÇIKLAMA, SİTE, META BAŞLIK, META AÇIKLAMA, ÜRÜN AÇIKLAMASI) ve
 * bilgi sutunlari (SAYFA ADRESİ, EK İÇERİK). Bu anahtarla yeni detay basligi acilmaz; Excel
 * basligi sistem sutunu sayilir. Kayitta zaten bu anahtarla olan baslik
 * (Kocist zimba verisindeki "Fiyat") korunur, Excel'de "(detay)" ekiyle yazilir.
 */
function nwcs_heading_reserved_keys(): array {
	return array( 'kimlik', 'urunkodu', 'urunadi', 'fiyat', 'kisaaciklama', 'site', 'metabaslik', 'metaaciklama', 'urunaciklamasi', 'sayfaadresi', 'ekicerik' );
}

/**
 * Etiket bir baslik olabilir mi? En az bir harf icermeli ("•", "0" gibi
 * aktarim artiklari baslik kaydina girmez).
 */
function nwcs_heading_is_valid( string $label ): bool {
	return '' !== nwcs_heading_key( $label ) && (bool) preg_match( '/\p{L}/u', $label );
}

/**
 * Baslik kaydi, sirasina gore.
 *
 * @return array<string, array{label:string, required:bool, order:int}>
 */
function nwcs_product_headings(): array {
	$stored = get_site_option( NWCS_HEADINGS_OPTION, null );

	if ( ! is_array( $stored ) ) {
		$stored = nwcs_headings_seed();
	}

	$out = array();

	foreach ( $stored as $key => $row ) {
		if ( ! is_array( $row ) || '' === (string) $key ) {
			continue;
		}

		$out[ (string) $key ] = array(
			'label'    => (string) ( $row['label'] ?? $key ),
			'required' => ! empty( $row['required'] ),
			'order'    => (int) ( $row['order'] ?? 0 ),
		);
	}

	uasort( $out, static fn( array $a, array $b ): int => $a['order'] <=> $b['order'] ?: strcmp( $a['label'], $b['label'] ) );

	return $out;
}

/**
 * Kaydi yazar; sira 10'ar araliklarla yeniden numaralanir.
 */
function nwcs_save_product_headings( array $headings ): void {
	uasort( $headings, static fn( array $a, array $b ): int => (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 ) );

	$clean = array();
	$order = 10;

	foreach ( $headings as $key => $row ) {
		$label = nwcs_heading_clean_label( (string) ( $row['label'] ?? '' ) );

		if ( '' === $label || '' === (string) $key ) {
			continue;
		}

		$clean[ (string) $key ] = array(
			'label'    => $label,
			'required' => ! empty( $row['required'] ),
			'order'    => $order,
		);
		$order += 10;
	}

	update_site_option( NWCS_HEADINGS_OPTION, $clean );
}

/**
 * Zorunlu basliklar (anahtar => etiket), kayit sirasiyla.
 *
 * @return array<string, string>
 */
function nwcs_required_headings(): array {
	$out = array();

	foreach ( nwcs_product_headings() as $key => $row ) {
		if ( $row['required'] ) {
			$out[ $key ] = $row['label'];
		}
	}

	return $out;
}

/**
 * Baslik kayitta yoksa opsiyonel olarak sona ekler. Anahtari dondurur;
 * gecersiz etikette ''. $created, yeni eklendiyse true olur.
 */
function nwcs_heading_ensure( string $label, ?bool &$created = null ): string {
	$created = false;
	$label   = nwcs_heading_clean_label( $label );

	if ( ! nwcs_heading_is_valid( $label ) ) {
		return '';
	}

	$key      = nwcs_heading_key( $label );
	$headings = nwcs_product_headings();

	if ( isset( $headings[ $key ] ) ) {
		return $key;
	}

	// Sistem sutunu adiyla (Fiyat, Ürün Adı...) yeni baslik acilmaz.
	if ( in_array( $key, nwcs_heading_reserved_keys(), true ) ) {
		return '';
	}

	$last             = $headings ? max( array_column( $headings, 'order' ) ) : 0;
	$headings[ $key ] = array( 'label' => $label, 'required' => false, 'order' => $last + 10 );
	nwcs_save_product_headings( $headings );
	$created = true;

	return $key;
}

/**
 * Tohum sirasinin cekirdegi: WOOD KOCIST urunlerindeki olagan sira (olcu,
 * boyut, malzeme, yuzey, yapi, kurulum, teslimat). Kayit sirasi yalnizca
 * Excel sutunlarinin ve formdaki yeni satirlarin yerini belirler; sitede her
 * urun kendi sirasiyla gosterilir (nwcs_product_pairs).
 */
function nwcs_headings_core_order(): array {
	return array( 'Çatı Ölçüsü', 'Zemin Ölçüsü', 'Boyut Sınıfı', 'Kapasite', 'Taşıma Kapasitesi', 'Kapı Ölçüleri', 'Ahşap Cinsi', 'Yüzey Koruması', 'Gövde Rengi', 'Çatı Tipi', 'Çatı Rengi', 'Havalandırma', 'Yapı Özelliği', 'İzolasyon Sınıfı', 'Zemin Yapısı', 'Güvenlik Donanımı', 'Taşıyıcı Donanım', 'Kullanım Alanı', 'Kurulum', 'Teslimat' );
}

/**
 * Ilk acilista kaydi havuzdaki spec metinlerinden kurar: her gecerli etiket
 * opsiyonel baslik olur. Sira: once cekirdek (nwcs_headings_core_order),
 * kalanlar etiketin urunlerde durdugu ortalama yere gore. Hicbiri zorunlu yapilmaz;
 * zorunluluk "Detay başlıkları" ekranindan bilincli olarak secilir (Kocist
 * urunlerinin cogunda detay yok, global zorunluluk onlarin Excel'ini
 * reddederdi).
 */
function nwcs_headings_seed(): array {
	$counts = array();
	$labels = array();
	$places = array(); // anahtar => goreli yerlerin toplami (0 bas, 1 son)

	switch_to_blog( nwcs_pool_blog_id() );

	$ids = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		$details = nwcs_product_details( (int) $id );
		$last    = max( 1, count( $details ) - 1 );

		foreach ( $details as $index => $row ) {
			if ( ! nwcs_heading_is_valid( $row['label'] ) ) {
				continue;
			}

			$key            = nwcs_heading_key( $row['label'] );
			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
			$places[ $key ] = ( $places[ $key ] ?? 0 ) + $index / $last;
			$labels[ $key ] = $labels[ $key ] ?? $row['label'];
		}
	}

	restore_current_blog();

	$core = array_flip( array_map( 'nwcs_heading_key', nwcs_headings_core_order() ) );
	$keys = array_keys( $counts );
	usort(
		$keys,
		static fn( string $a, string $b ): int => ( $core[ $a ] ?? PHP_INT_MAX ) <=> ( $core[ $b ] ?? PHP_INT_MAX )
			?: ( $places[ $a ] / $counts[ $a ] ) <=> ( $places[ $b ] / $counts[ $b ] )
			?: $counts[ $b ] <=> $counts[ $a ]
	);

	$seed  = array();
	$order = 10;

	foreach ( $keys as $key ) {
		$seed[ $key ] = array( 'label' => $labels[ $key ], 'required' => false, 'order' => $order );
		$order       += 10;
	}

	update_site_option( NWCS_HEADINGS_OPTION, $seed );

	return $seed;
}

/* ------------------------------------------------------------------ */
/* Urun degerleri                                                       */
/* ------------------------------------------------------------------ */

/**
 * "Etiket: deger; Etiket: deger" metnini ciftlere ayirir. Parcalardan biri
 * bu bicimde degilse metin serbest nottur ("80 × 120 cm"): bos dizi doner.
 *
 * @return array<int, array{label:string, value:string}>
 */
function nwcs_parse_spec_pairs( string $spec ): array {
	$spec = trim( $spec );

	if ( '' === $spec ) {
		return array();
	}

	$pairs = array();

	foreach ( preg_split( '/\s*[;\n]\s*/u', $spec, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $part ) {
		if ( ! preg_match( '/^([^:]{1,40}):\s*(.*)$/u', trim( $part ), $match ) ) {
			return array();
		}

		$label = trim( $match[1] );
		$value = trim( $match[2] );

		// "0: Gizli Pence Aski" gibi aktarim artigi: etiketi sayi olan parca.
		if ( preg_match( '/^\d+$/', $label ) || '' === $value ) {
			continue;
		}

		$pairs[] = array( 'label' => $label, 'value' => $value );
	}

	return $pairs;
}

/**
 * Spec metni serbest bir not mu (cift degil)?
 */
function nwcs_spec_is_note( string $spec ): bool {
	return '' !== trim( $spec ) && ! nwcs_parse_spec_pairs( $spec );
}

/**
 * Urunun detaylari. Havuz baglaminda cagrilmalidir.
 *
 * @return array<int, array{label:string, value:string}>
 */
function nwcs_product_details( int $id ): array {
	$stored = get_post_meta( $id, NWCS_DETAILS_META, true );

	if ( is_array( $stored ) ) {
		return nwcs_clean_details( $stored );
	}

	return nwcs_parse_spec_pairs( (string) get_post_meta( $id, '_nwcs_spec', true ) );
}

/**
 * Detay satirlarini temizler: etiket ve deger tek satir metin, bos deger
 * atilir, ayni baslik ikinci kez gelirse ilki kalir.
 *
 * @return array<int, array{label:string, value:string}>
 */
function nwcs_clean_details( array $rows ): array {
	$out  = array();
	$seen = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		// ';' ve ':' spec metnini bozar (nwcs_heading_clean_label, nwcs_detail_clean_value).
		$label = nwcs_heading_clean_label( (string) ( $row['label'] ?? '' ) );
		$value = nwcs_detail_clean_value( (string) ( $row['value'] ?? '' ) );
		$key   = nwcs_heading_key( $label );

		if ( '' === trim( $label ) || '' === $value ) {
			continue;
		}

		// Etiketi harf icermeyen satir ("•") anahtarla degil, oldugu gibi tutulur.
		$seen_key = '' !== $key ? $key : 'raw:' . $label;

		if ( isset( $seen[ $seen_key ] ) ) {
			continue;
		}

		$seen[ $seen_key ] = true;
		$out[]             = array( 'label' => trim( $label ), 'value' => $value );
	}

	return array_slice( $out, 0, 60 );
}

/**
 * Detaylari ve turetilmis spec metnini yazar. TEK yazici. Havuz baglaminda
 * cagrilmalidir.
 *
 * Etiketler baslik kaydindaki yazilisa cevrilir ("ahşap cinsi" -> "Ahşap
 * Cinsi"). Detay yoksa spec: $note verildiyse o; verilmediyse mevcut serbest
 * not korunur (Kocist'teki "80 × 120 cm" gibi), cift bicimindeki eski metin
 * temizlenir.
 *
 * @param array<int, array{label:string, value:string}> $details
 */
function nwcs_product_write_details( int $id, array $details, ?string $note = null ): void {
	$headings = nwcs_product_headings();
	$rows     = array();

	foreach ( nwcs_clean_details( $details ) as $row ) {
		$key = nwcs_heading_key( $row['label'] );

		if ( isset( $headings[ $key ] ) ) {
			$row['label'] = $headings[ $key ]['label'];
		}

		$rows[] = $row;
	}

	if ( $rows ) {
		$spec = implode( '; ', array_map( static fn( array $row ): string => $row['label'] . ': ' . $row['value'], $rows ) );
	} elseif ( null !== $note ) {
		$spec = nwcs_clean_text( $note );
	} else {
		$current = (string) get_post_meta( $id, '_nwcs_spec', true );
		$spec    = nwcs_spec_is_note( $current ) ? $current : '';
	}

	// update_post_meta ters egik cizgiyi siler; metin aynen kalsin.
	update_post_meta( $id, NWCS_DETAILS_META, wp_slash( $rows ) );
	update_post_meta( $id, '_nwcs_spec', wp_slash( $spec ) );
}

/**
 * Detay listesinde anahtara gore deger ('' = yok).
 */
function nwcs_details_value( array $details, string $key ): string {
	foreach ( $details as $row ) {
		if ( nwcs_heading_key( (string) $row['label'] ) === $key ) {
			return (string) $row['value'];
		}
	}

	return '';
}

/**
 * Temalar icin: urunun etiket/deger ciftleri, URUNUN KENDI SIRASIYLA (once
 * detaylar, yoksa spec metni). Kayit sirasi sitedeki gosterimi degistirmez;
 * yalnizca Excel sutunlarinin sirasini belirler. Excel'den yazilan urunun
 * detaylari sutun sirasiyla yazildigi icin ayni kategoride sira kendiliginden
 * tutarli olur.
 *
 * @param array $product nwcs_site_products() / nwcs_pool_products() satiri.
 * @return array<int, array{0:string, 1:string}>
 */
function nwcs_product_pairs( array $product, bool $tidy = true ): array {
	$details = isset( $product['details'] ) && is_array( $product['details'] )
		? $product['details']
		: nwcs_parse_spec_pairs( (string) ( $product['spec'] ?? '' ) );

	if ( ! $details ) {
		return array();
	}

	// "Ithal Cam ( Firinlanmis )" -> "Ithal Cam (Firinlanmis)" yalnizca gosterimde;
	// urun formu degeri oldugu gibi alir ($tidy false), kayit degismesin.
	$clean = static fn( string $text ): string => $tidy ? (string) preg_replace( array( '/\(\s+/u', '/\s+\)/u' ), array( '(', ')' ), $text ) : $text;

	return array_map( static fn( array $row ): array => array( (string) $row['label'], $clean( (string) $row['value'] ) ), array_values( $details ) );
}

/* ------------------------------------------------------------------ */
/* Urun sayfasi veri yuvalari (Kocist ve WOOD KOCIST ortak)             */
/* ------------------------------------------------------------------ */

/**
 * Urun sayfasinda "Lojistik ve Teslimat" paneline giden detay satirinin adi.
 * Kural iki temada ayni olmali: satir bu adla eslesiyorsa teknik detaylar
 * tablosundan cikar, kendi panelinde gosterilir.
 */
const NWCS_DELIVERY_LABEL = 'Lojistik ve Teslimat';

/**
 * Detay satiri Lojistik panelinin satiri mi? (buyuk/kucuk harf ve bosluk farki onemsiz)
 *
 * @param array{0:string, 1:string} $pair
 */
function nwcs_product_is_delivery_pair( array $pair ): bool {
	return 0 === strcmp( mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', (string) ( $pair[0] ?? '' ) ) ) ), mb_strtolower( NWCS_DELIVERY_LABEL ) );
}

/**
 * Teknik detaylar tablosunun satirlari: urunun detaylari, Lojistik satiri
 * haric. Detayi olmayan urunde serbest olcu notu ("80 × 120 cm") tek satir
 * olur: array( 'Ölçü', not ). Hicbir sey uydurulmaz.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function nwcs_product_table_specs( array $product ): array {
	$pairs = nwcs_product_pairs( $product );

	if ( ! $pairs ) {
		$spec  = trim( (string) ( $product['spec'] ?? '' ) );
		$parts = preg_split( '/\s*[;\n]\s*/u', $spec, -1, PREG_SPLIT_NO_EMPTY ) ?: array();

		// "0: BOŞ KULP 3 (75); 1: ..." gibi numarali aktarim artigi olcu notu degildir: satir yok.
		if ( '' === $spec || ! nwcs_spec_is_note( $spec ) || count( preg_grep( '/^\d+\s*:/u', $parts ) ) === count( $parts ) ) {
			return array();
		}

		return array( array( 'Ölçü', (string) preg_replace( '/\s*\R\s*/u', '; ', $spec ) ) );
	}

	return array_values( array_filter( $pairs, static fn( array $pair ): bool => ! nwcs_product_is_delivery_pair( $pair ) ) );
}

/**
 * Urunun kendi "Lojistik ve Teslimat" satirinin degeri; yoksa bos metin
 * (tema o zaman paneldeki site geneli metni kullanir).
 */
function nwcs_product_delivery_row( array $product ): string {
	foreach ( nwcs_product_pairs( $product ) as $pair ) {
		if ( nwcs_product_is_delivery_pair( $pair ) && '' !== trim( $pair[1] ) ) {
			return trim( $pair[1] );
		}
	}

	return '';
}
