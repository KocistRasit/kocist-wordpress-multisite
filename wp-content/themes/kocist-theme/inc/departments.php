<?php
/**
 * Koçist bolumleri: uc ayri Google Isletme Profili (Ambalaj, Kamelya ve
 * Dekorasyon, Kereste) icin ayri yerel isletme kayitlari (LocalBusiness).
 *
 * Ana firma dugumu (#organization) sitenin tamamini temsil eder; her bolum
 * kendi adi, adresi (2125, 2126, 2125/1), telefonu, konumu ve harita
 * baglantisiyla ayri bir dugumdur. Google Haritalar profili ile site arasindaki
 * ad-adres-telefon eslesmesi bu kayitlarla kurulur.
 *
 * Nerede basilir:
 * - Ana sayfa ve Iletisim: uc bolumun tamami.
 * - Grup ve alt kategori sayfasi (/kategoriler/ambalaj/, /kategoriler/
 *   kereste/ahsap-kalas/ vb.): yalniz o bolum; sayfanin "about" alani
 *   bolume baglanir.
 * - Her sayfada ana firma dugumu "department" ile bolumlere baglanir.
 *
 * Bolum sayfalarinda (grup, alt kategori ve bolumun urunleri) ust serit ve
 * sayfa altindaki telefonlar ile WhatsApp bolumun kendi hattidir
 * (kocist_contact_phones, kocist_department_wa_url).
 *
 * Bilgiler Google Isletme Profili'ndeki degerlerle birebir ayni tutulur.
 * Profilde ad, adres, telefon ya da saat degisirse burasi da guncellenir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bolum verileri. Anahtar, grup sayfasinin adresindeki addir.
 */
function kocist_departments(): array {
	$hours = array(
		array( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ), '08:00', '19:00' ),
		array( array( 'Sunday' ), '10:00', '19:00' ),
	);

	$address = static fn( string $no ): array => array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => 'Kestanelik Mah. Eski Edirne Asfaltı Cad. No:' . $no,
		'addressLocality' => 'Çatalca',
		'addressRegion'   => 'İstanbul',
		'postalCode'      => '34540',
		'addressCountry'  => 'TR',
	);

	return array(
		'ambalaj'    => array(
			'name'    => 'KOÇİST Ahşap Ambalaj Ürünleri ve Sandık İmalatı Atölyesi',
			'desc'    => 'Çatalca\'da ahşap palet, Euro palet, ISPM-15 ısıl işlemli palet, ihracat sandığı ve ahşap kafes üretimi.',
			'phone'   => '+90 532 374 98 32',
			'phone2'  => '+90 212 648 10 90',
			'address' => $address( '2125' ),
			'lat'     => 41.23092,
			'lng'     => 28.5007532,
			'cid'     => '4680170301266184913',
			'same_as' => array( 'https://www.instagram.com/kocistambalaj/' ),
			'hours'   => $hours,
		),
		'dekorasyon' => array(
			'name'    => 'KOÇİST Kamelya ve Ahşap Dekorasyon',
			'desc'    => 'Çatalca atölyesinde ahşap kamelya, çardak, pergola, piknik masası ve bahçe mobilyası imalatı ve montajı.',
			'phone'   => '+90 549 648 19 19',
			'phone2'  => '+90 212 648 19 19',
			'address' => $address( '2126' ),
			'lat'     => 41.2312164,
			'lng'     => 28.5016346,
			'cid'     => '14543972436204818189',
			'same_as' => array( 'https://www.instagram.com/kocistdekorasyon/', 'https://tr.pinterest.com/kocistorman/', 'https://www.youtube.com/@KocistOrman' ),
			'hours'   => $hours,
		),
		'kereste'    => array(
			'name'    => 'KOÇİST Kereste, Orman Ürünleri ve İnşaat Malzemeleri',
			'desc'    => 'Çatalca\'da inşaatlık, emprenyeli ve fırınlanmış kereste, kalas, çıta ve levha satışı.',
			'phone'   => '+90 538 609 48 62',
			'phone2'  => '',
			'address' => $address( '2125/1' ),
			'lat'     => 41.2309908,
			'lng'     => 28.5009723,
			'cid'     => '3806919997949963228',
			'same_as' => array( 'https://www.instagram.com/kocistkereste/' ),
			'hours'   => $hours,
		),
	);
}

/* ------------------------------------------------------------------ */
/* Sayfanin bolumu ve bolumun hatlari                                   */
/* ------------------------------------------------------------------ */

/**
 * Bu sayfanin bolumu: /kategoriler/<bolum>/... ya da bolumun urun sayfasi
 * (/urun/...). Bolume ait degilse (ana sayfa, kurumsal, hirdavat...) bos.
 */
function kocist_current_department(): string {
	static $key = null;

	if ( null !== $key ) {
		return $key;
	}

	// Sorgu cozulmeden bolum bilinemez; sonuc da saklanmaz.
	if ( ! did_action( 'wp' ) ) {
		return '';
	}

	$group = '';

	if ( get_query_var( 'kocist_catalog' ) ) {
		$group = sanitize_title( (string) get_query_var( 'kocist_group' ) );
	} elseif ( '' !== (string) get_query_var( 'nwcs_product' ) && function_exists( 'nwcs_site_product_by_slug' ) ) {
		$product = nwcs_site_product_by_slug( sanitize_title( (string) get_query_var( 'nwcs_product' ) ) );
		$group   = is_array( $product ) ? (string) ( $product['group'] ?? '' ) : '';
	}

	$key = isset( kocist_departments()[ $group ] ) ? $group : '';

	return $key;
}

/**
 * +90 532 374 98 32 -> 0532 374 98 32
 */
function kocist_phone_local( string $intl ): string {
	return (string) preg_replace( '/^\+90\s*/', '0', trim( $intl ) );
}

/**
 * Ust serit ve sayfa altindaki telefonlar.
 *
 * Bolum sayfasinda bolumun kendi hatlari: Google Isletme Profili'ndeki
 * birincil ve ek numara, ayni sirayla. Haritadan urun sayfasina gelen kisi
 * profilde gordugu numarayi gorur. Diger sayfalarda paneldeki genel hatlar.
 *
 * @param string $component Panel bileseni: 'topbar' ya da 'footer'.
 * @return array<int, array{label:string, url:string, field:string}> field: onizlemede
 *         tiklaninca acilan panel alani; bolum hattinda bos (numara bu dosyada).
 */
function kocist_contact_phones( string $component ): array {
	$lines = array();
	$key   = kocist_current_department();

	if ( '' !== $key ) {
		$dept = kocist_departments()[ $key ];

		foreach ( array( (string) $dept['phone'], (string) ( $dept['phone2'] ?? '' ) ) as $intl ) {
			if ( '' !== $intl ) {
				$lines[] = array(
					'label' => kocist_phone_local( $intl ),
					'url'   => 'tel:' . preg_replace( '/[^\d+]/', '', $intl ),
					'field' => '',
				);
			}
		}

		return $lines;
	}

	foreach ( array( 'phone', 'mobile' ) as $name ) {
		$label = trim( (string) nwcs_field( 'global', $component, $name . '_label' ) );

		if ( '' !== $label ) {
			$lines[] = array(
				'label' => $label,
				'url'   => kocist_link( nwcs_field( 'global', $component, $name . '_url' ) ),
				'field' => $name . '_label',
			);
		}
	}

	return $lines;
}

/**
 * Bolum sayfasinda WhatsApp baglantisi bolumun birincil hattina gider
 * (uc profilde de WhatsApp birincil numaradir). Diger baglantilar aynen doner.
 */
function kocist_department_wa_url( string $url ): string {
	$key = kocist_current_department();

	if ( '' === $key || ! function_exists( 'kocist_is_whatsapp_url' ) || ! kocist_is_whatsapp_url( $url ) ) {
		return $url;
	}

	return 'https://wa.me/' . preg_replace( '/\D/', '', (string) kocist_departments()[ $key ]['phone'] );
}

/**
 * Bir bolumun schema.org dugumu.
 */
function kocist_department_node( string $key, array $dept, string $org_id, string $image ): array {
	$home = home_url( '/' );
	$map  = 'https://www.google.com/maps?cid=' . $dept['cid'];

	$node = array(
		'@type'                     => 'LocalBusiness',
		'@id'                       => $home . '#isletme-' . $key,
		'name'                      => $dept['name'],
		'description'               => $dept['desc'],
		'url'                       => home_url( '/kategoriler/' . $key . '/' ),
		'telephone'                 => $dept['phone'],
		'address'                   => $dept['address'],
		'geo'                       => array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $dept['lat'],
			'longitude' => $dept['lng'],
		),
		'hasMap'                    => $map,
		'sameAs'                    => array_values( array_merge( array( $map ), $dept['same_as'] ) ),
		'openingHoursSpecification' => array_map(
			static fn( array $row ): array => array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $row[0],
				'opens'     => $row[1],
				'closes'    => $row[2],
			),
			$dept['hours']
		),
		'parentOrganization'        => array( '@id' => $org_id ),
	);

	if ( '' !== $dept['phone2'] ) {
		$node['contactPoint'] = array(
			array(
				'@type'             => 'ContactPoint',
				'telephone'         => $dept['phone2'],
				'contactType'       => 'customer service',
				'availableLanguage' => 'Turkish',
			),
		);
	}

	if ( '' !== $image ) {
		$node['image'] = $image;
	}

	return $node;
}

add_filter( 'nwcs_seo_graph', 'kocist_seo_departments', 20, 2 );
function kocist_seo_departments( array $graph, array $context = array() ): array {
	$home   = home_url( '/' );
	$org_id = $home . '#organization';
	$depts  = kocist_departments();
	$path   = trim( (string) wp_parse_url( (string) ( $context['url'] ?? '' ), PHP_URL_PATH ), '/' );
	$image  = '';
	$org_i  = null;

	foreach ( $graph as $index => $node ) {
		if ( ( $node['@id'] ?? '' ) === $org_id ) {
			$org_i = $index;
			$image = is_string( $node['image'] ?? null ) ? $node['image'] : '';
			break;
		}
	}

	if ( null === $org_i ) {
		return $graph;
	}

	// Ana firma her sayfada bolumlerine baglanir.
	$graph[ $org_i ]['department'] = array_map(
		static fn( string $key ): array => array( '@id' => $home . '#isletme-' . $key ),
		array_keys( $depts )
	);

	// Hangi bolumler tam dugum olarak basilir.
	$print = array();

	if ( '' === $path || 'iletisim' === $path ) {
		$print = array_keys( $depts );
	} elseif ( preg_match( '#^kategoriler/(ambalaj|dekorasyon|kereste)(/[^/]+)?$#', $path, $m ) ) {
		$print = array( $m[1] );
	}

	foreach ( $print as $key ) {
		$graph[] = kocist_department_node( $key, $depts[ $key ], $org_id, $image );
	}

	// Grup sayfasi kendi bolumunu anlatir.
	if ( 1 === count( $print ) && '' !== $path ) {
		foreach ( $graph as $index => $node ) {
			if ( ( $node['@id'] ?? '' ) === (string) ( $context['url'] ?? '' ) . '#webpage' ) {
				$graph[ $index ]['about'] = array( '@id' => $home . '#isletme-' . $print[0] );
			}
		}
	}

	return $graph;
}
