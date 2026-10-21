<?php
/**
 * Yerel deneme: urun XML beslemesi (eklenti includes/product-feed.php).
 *
 * Kendi deneme kategorilerini, urunlerini ve gorsellerini olusturur ("ZZ XML
 * Deneme" yerlesik, "ZZ XML Yersiz" yerlesimsiz; kodlar XMLT-...), Kocist'te ve
 * WOOD KOCIST'te gosterir. Denenenler:
 *   - /kocist/urunler.xml ve /woodkocist/urunler.xml: 200, gecerli XML (UTF-8),
 *     kok nitelikleri, satir sayisi = sitede gorunen urunler (sira ayni), her
 *     satirda alanlar sirayla ve ad/kod dolu;
 *   - fiyat: "10.250 ₺" -> 10250.00; fiyatsiz, "...'den baslayan", "$" ve 0 bos;
 *     ayristirici tek tek (binlik, kurus, birimsiz, aralik);
 *   - gizli urun yok (diger sitede var); site basina adres ve ad (diger sitede
 *     havuzdaki); renk grubunun iki uyesi ayri satir, renk adiyla;
 *   - gorseller: tam boy, galeri sirasiyla gorsel / ek_gorseller ("|") /
 *     gorseller; sitenin adresinde, aciliyor; yer tutucu (alt metin ve seed
 *     dosya adi) yazilmaz;
 *   - kategori "Ust baslik > Kategori", yerlesimsizde kategori adi;
 *   - aciklama: kisa aciklama, bossa uzun aciklamanin ilk ~300 karakteri (duz);
 *   - ozel karakterler (&, <, ", Turkce harfler) kacirilir ve aynen geri okunur;
 *   - basliklar (Content-Type, X-Robots-Tag, Cache-Control), yonlendirme yok,
 *     HEAD govdesiz; tam esitlik (sona egik cizgi, sorgu metni);
 *   - katalog olmayan siteler ve ana site 404; site haritasinda ve llms.txt'de yok;
 *   - E_ALL: temada ve eklentide uyari yok.
 * Sonunda her seyi siler, site ayarlarini eski haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-product-feed.php
 */

$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/kocist/';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

error_reporting( E_ALL );

$fail = 0;

function check( string $label, bool $ok, string $got = '' ): void {
	global $fail;
	echo ( $ok ? 'OK   ' : 'FAIL ' ) . $label . ( $ok || '' === $got ? '' : '  (' . $got . ')' ) . "\n";
	$fail += $ok ? 0 : 1;
}

// Eklentinin ve temalarin kendi kodunda uyari/notice olmasin.
$notices = array();
set_error_handler(
	static function ( int $no, string $message, string $file, int $line ) use ( &$notices ): bool {
		if ( ( error_reporting() & $no ) && ( str_contains( $file, 'network-content-studio' ) || str_contains( $file, 'themes' ) ) ) {
			$notices[] = "$message @ " . basename( $file ) . ":$line";
		}

		return false;
	},
	E_ALL
);

const K_BLOG     = 8;  // Kocist
const W_BLOG     = 13; // WOOD KOCIST
const TERM_SLUG  = 'zz-xml-deneme';
const TERM_LOOSE = 'zz-xml-yersiz';
const CODE_PART  = 'XMLT-';

if ( K_BLOG !== get_current_blog_id() || ! function_exists( 'nwcs_product_feed_xml' ) ) {
	echo "Kocist baglaminda acilmadi ya da besleme yuklu degil.\n";
	exit( 1 );
}

$pool = nwcs_pool_blog_id();

/**
 * Konteynerin icinden HTTP istegi (Host basligi panelin adresi).
 *
 * @return array{code:int, body:string, headers:array}
 */
function http( string $path, string $method = 'GET' ): array {
	$response = wp_remote_request(
		'http://127.0.0.1' . $path,
		array(
			'method'      => $method,
			'headers'     => array( 'Host' => 'localhost:8080' ),
			'timeout'     => 40,
			'redirection' => 0,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'code' => 0, 'body' => $response->get_error_message(), 'headers' => array() );
	}

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $response ),
		'body'    => (string) wp_remote_retrieve_body( $response ),
		'headers' => wp_remote_retrieve_headers( $response )->getAll(),
	);
}

function header_of( array $response, string $name ): string {
	$value = $response['headers'][ strtolower( $name ) ] ?? '';

	return is_array( $value ) ? (string) end( $value ) : (string) $value;
}

/** Tam adresin site ici yolu (http() icin). */
function path_of( string $url ): string {
	return (string) wp_parse_url( $url, PHP_URL_PATH );
}

/**
 * Beslemeyi okur: kod => satir (alanlar metin; gorseller dizi), sirali kodlar.
 *
 * @return array{xml:?SimpleXMLElement, rows:array<string, array>, codes:string[], tags_ok:bool}
 */
function feed_rows( string $body ): array {
	$xml  = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );
	$out  = array( 'xml' => $xml ?: null, 'rows' => array(), 'codes' => array(), 'tags_ok' => (bool) $xml );
	$tags = array( 'ad', 'kod', 'fiyat', 'indirimsiz_fiyat', 'gorsel', 'ek_gorseller', 'gorseller', 'link', 'kategori', 'aciklama', 'kdv_orani', 'renk' );

	foreach ( $xml ? $xml->urun : array() as $urun ) {
		$row = array();

		foreach ( $urun->children() as $child ) {
			$row[ $child->getName() ] = 'gorseller' === $child->getName()
				? array_map( 'strval', iterator_to_array( $child->url, false ) )
				: (string) $child;
		}

		// Kod bos olabilir (kodsuz eski urun satir olarak kalir); deneme urunlerinde ayrica bakilir.
		$out['tags_ok']      = $out['tags_ok'] && array_keys( $row ) === $tags && '' !== $row['ad'];
		$out['codes'][]      = (string) $row['kod'];
		$out['rows'][ $row['kod'] ] = $row;
	}

	return $out;
}

/**
 * Istekleri ayri bir PHP sureciyle, tam istek olarak ve E_ALL hata
 * yakalayicisiyla cizer. Uyarilari dondurur.
 *
 * @param string[] $paths
 * @return string[]
 */
function strict_notices( array $paths ): array {
	$runner = sys_get_temp_dir() . '/nwcs-strict-feed.php';
	$code   = <<<'PHP'
<?php
$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_URI']     = $argv[1];
error_reporting( E_ALL );
set_error_handler(
	static function ( int $no, string $message, string $file, int $line ): bool {
		if ( str_contains( $file, '/themes/' ) || str_contains( $file, 'network-content-studio' ) ) {
			fwrite( STDERR, 'NOTICE ' . $message . ' @ ' . basename( $file ) . ':' . $line . PHP_EOL );
		}

		return false;
	}
);
ob_start( static fn(): string => '' );
require '/var/www/html/wp-blog-header.php';
PHP;

	file_put_contents( $runner, $code );

	$found = array();

	foreach ( $paths as $path ) {
		$output = (string) shell_exec( 'php ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( $path ) . ' 2>&1' );

		foreach ( preg_split( '/\R/', $output ) ?: array() as $line ) {
			if ( str_starts_with( $line, 'NOTICE ' ) || str_contains( $line, 'Fatal error' ) ) {
				$found[] = $path . ': ' . $line;
			}
		}
	}

	unlink( $runner );

	return $found;
}

/* ---------- Temizlik ---------- */

/**
 * Deneme verisini siler, site ayarlarini eski haline getirir.
 */
function cleanup( array $state ): void {
	global $pool;

	foreach ( (array) ( $state['sites'] ?? array() ) as $blog => $options ) {
		foreach ( $options as $option => $value ) {
			if ( null === $value ) {
				delete_blog_option( (int) $blog, $option );
			} else {
				update_blog_option( (int) $blog, $option, $value );
			}
		}
	}

	switch_to_blog( $pool );

	$found = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => '_nwcs_code', 'value' => CODE_PART, 'compare' => 'LIKE' ) ),
		)
	);

	foreach ( array_unique( array_merge( array_map( 'intval', (array) ( $state['ids'] ?? array() ) ), array_map( 'intval', $found ) ) ) as $id ) {
		if ( str_starts_with( (string) get_post_meta( $id, '_nwcs_code', true ), CODE_PART ) || in_array( $id, (array) ( $state['ids'] ?? array() ), true ) ) {
			wp_delete_post( $id, true );
		}
	}

	foreach ( (array) ( $state['attachments'] ?? array() ) as $attachment ) {
		wp_delete_attachment( (int) $attachment, true );
	}

	foreach ( array( TERM_SLUG, TERM_LOOSE ) as $slug ) {
		$term = get_term_by( 'slug', $slug, NWCS_PRODUCT_TAX );

		if ( $term ) {
			wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
		}
	}

	restore_current_blog();
	nwcs_pool_flush_cache();
}

/* ---------- Deneme verisi ---------- */

$state = array(
	'sites'       => array(),
	'ids'         => array(),
	'attachments' => array(),
);

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	foreach ( array( NWCS_OPTION_MODE, NWCS_OPTION_SELECTED, NWCS_OPTION_OVERRIDES ) as $option ) {
		$state['sites'][ $blog ][ $option ] = get_blog_option( $blog, $option, null );
	}
}

$long = '<p>Birinci paragraf.</p><p>İkinci paragraf: akasya ağacından katlanır sandalye; geniş kolçaklar, eğimli sırt ve dış mekân yağıyla korunmuş yüzey. '
	. str_repeat( 'Bahçe, teras ve balkon için hazır gelir, kurulum gerektirmez ve uzun yıllar kullanılır. ', 4 ) . '</p>';
$spec = 'Nem %18 <20 mm & "kuru" — çam, ığdır, ŞÖĞÜÇİ';

// key => [ ad, kod, fiyat, kisa aciklama, uzun aciklama, kategori ]
$rows = array(
	'p'   => array( 'XML Deneme Fiyatlı Sandalye', 'XMLT-FIYAT', '10.250 ₺', '', $long, TERM_SLUG ),
	'n'   => array( 'XML Deneme Fiyatsız Masa', 'XMLT-TEKLIF', '', 'Kısa açıklama.', '', TERM_SLUG ),
	'r'   => array( 'XML Deneme Başlayan Fiyat', 'XMLT-BASLAYAN', "1.250 TL'den başlayan", '', '', TERM_SLUG ),
	'd'   => array( 'XML Deneme Dolar Fiyat', 'XMLT-USD', '24,50 $', '', '', TERM_SLUG ),
	'h'   => array( 'XML Deneme Gizli Ürün', 'XMLT-GIZLI', '450 TL', '', '', TERM_SLUG ),
	's'   => array( 'XML Deneme Özel Adres', 'XMLT-ADRES', '', '', '', TERM_SLUG ),
	't'   => array( 'XML Deneme Kod & Ad Çiçekli', 'XMLT-AD', '1.299,90 ₺', $spec, '', TERM_SLUG ),
	'g1'  => array( 'XML Deneme Renkli Masa Açık Meşe', 'XMLT-RNK-AM', '900 ₺', '', '', TERM_SLUG ),
	'g2'  => array( 'XML Deneme Renkli Masa Füme', 'XMLT-RNK-FM', '900 ₺', '', '', TERM_SLUG ),
	'ph'  => array( 'XML Deneme Yer Tutuculu', 'XMLT-YT1', '', '', '', TERM_SLUG ),
	'ph2' => array( 'XML Deneme Yalnız Seed Görsel', 'XMLT-YT2', '', '', '', TERM_SLUG ),
	'u'   => array( 'XML Deneme Yerleşimsiz', 'XMLT-YERSIZ', '', '', '', TERM_LOOSE ),
);

$ids = array();

try {
	switch_to_blog( $pool );

	$terms = array();

	foreach ( array( TERM_SLUG => 'ZZ XML Deneme', TERM_LOOSE => 'ZZ XML Yersiz' ) as $slug => $name ) {
		$term            = wp_insert_term( $name, NWCS_PRODUCT_TAX, array( 'slug' => $slug ) );
		$terms[ $slug ] = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
	}

	foreach ( $rows as $key => $row ) {
		$ids[ $key ] = wp_insert_post(
			array(
				'post_type'    => NWCS_PRODUCT_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $row[0],
				'post_content' => $row[4],
				'menu_order'   => 9000 + count( $ids ),
			)
		);
		update_post_meta( $ids[ $key ], '_nwcs_code', $row[1] );

		if ( '' !== $row[2] ) {
			update_post_meta( $ids[ $key ], '_nwcs_price', $row[2] );
		}

		if ( '' !== $row[3] ) {
			update_post_meta( $ids[ $key ], '_nwcs_short', $row[3] );
		}

		wp_set_object_terms( $ids[ $key ], array( $terms[ $row[5] ] ), NWCS_PRODUCT_TAX );
	}

	$state['ids'] = array_values( $ids );

	// Gorseller (GD): a, b, c, d gercek; yt alt metniyle yer tutucu, seed dosya adiyla yer tutucu.
	$upload = wp_upload_dir();
	$files  = array(
		'a'    => array( 'xmlt-a.jpg', 'XML deneme fotoğrafı A' ),
		'b'    => array( 'xmlt-b.jpg', 'XML deneme fotoğrafı B' ),
		'c'    => array( 'xmlt-c.jpg', 'XML deneme fotoğrafı C' ),
		'd'    => array( 'xmlt-d.jpg', 'XML deneme fotoğrafı D' ),
		'yt'   => array( 'xmlt-demo.jpg', 'Örnek görsel: XML Deneme (demo yer tutucusu)' ),
		'seed' => array( 'ornek-urun-xmlt.jpg', '' ),
	);
	$att    = array();

	foreach ( $files as $key => [ $name, $alt ] ) {
		$file  = trailingslashit( $upload['path'] ) . $name;
		$image = imagecreatetruecolor( 1000, 750 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 40 + 30 * count( $att ), 90, 60 ) );
		imagejpeg( $image, $file, 82 );
		imagedestroy( $image );

		$att[ $key ] = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'XMLT ' . $key, 'post_status' => 'inherit' ), $file );
		wp_update_attachment_metadata( $att[ $key ], wp_generate_attachment_metadata( $att[ $key ], $file ) );

		if ( '' !== $alt ) {
			update_post_meta( $att[ $key ], '_wp_attachment_image_alt', $alt );
		}

		$state['attachments'][] = $att[ $key ];
	}

	// Galeri sirasi kimlik sirasindan farkli: b, a, c.
	update_post_meta( $ids['p'], '_nwcs_gallery', array( $att['b'], $att['a'], $att['c'] ) );
	update_post_meta( $ids['ph'], '_nwcs_gallery', array( $att['yt'], $att['d'] ) );
	update_post_meta( $ids['ph2'], '_nwcs_gallery', array( $att['seed'] ) );

	restore_current_blog();
	nwcs_pool_flush_cache();

	$k_parent = 'kereste';
	$w_parent = (string) array_key_first( nwcs_site_parents( W_BLOG ) );

	nwcs_set_placement( TERM_SLUG, nwcs_catalog_site_key( K_BLOG ), $k_parent );
	nwcs_set_placement( TERM_SLUG, nwcs_catalog_site_key( W_BLOG ), $w_parent );

	foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
		update_blog_option( $blog, NWCS_OPTION_MODE, 'selected' );
		nwcs_selection_append( $blog, array_values( $ids ) );
	}

	// Kocist istisnalari: gizli, ozel adres, ozel ad.
	$overrides                 = (array) get_blog_option( K_BLOG, NWCS_OPTION_OVERRIDES, array() );
	$overrides[ $ids['h'] ]    = array( 'hidden' => true );
	$overrides[ $ids['s'] ]    = array( 'slug' => 'xmlt-ozel-adres' );
	$overrides[ $ids['t'] ]    = array( 'title' => 'Ölçü <20 mm & "Çift" Şık İğne' );
	update_blog_option( K_BLOG, NWCS_OPTION_OVERRIDES, $overrides );

	nwcs_color_groups_write(
		TERM_SLUG,
		array(
			array(
				'id'      => 'zzxml1',
				'label'   => 'Renk',
				'members' => array(
					array( 'product_id' => $ids['g1'], 'color_name' => 'Açık Meşe', 'color_hex' => '#c8a165' ),
					array( 'product_id' => $ids['g2'], 'color_name' => 'Füme', 'color_hex' => '#4a4a4a' ),
				),
			),
		)
	);

	nwcs_pool_flush_cache();

	/* ---------- 1) Bicim ve satirlar ---------- */

	$k     = http( '/kocist/urunler.xml' );
	$w     = http( '/woodkocist/urunler.xml' );
	$kf    = feed_rows( $k['body'] );
	$wf    = feed_rows( $w['body'] );
	$k_all = nwcs_site_products( K_BLOG );
	$w_all = nwcs_site_products( W_BLOG );

	check( 'Kocist: 200, gecerli XML (simplexml)', 200 === $k['code'] && null !== $kf['xml'], $k['code'] . ' ' . substr( $k['body'], 0, 120 ) );
	check( 'WOOD KOCIST: 200, gecerli XML', 200 === $w['code'] && null !== $wf['xml'], $w['code'] . ' ' . substr( $w['body'], 0, 120 ) );
	check( 'bildirim UTF-8, govde gecerli UTF-8', str_starts_with( $k['body'], '<?xml version="1.0" encoding="UTF-8"?>' ) && mb_check_encoding( $k['body'], 'UTF-8' ) && mb_check_encoding( $w['body'], 'UTF-8' ) );
	check( 'kok: <urunler site guncelleme adet>, site adi', $kf['xml'] && 'urunler' === $kf['xml']->getName() && html_entity_decode( get_blog_option( K_BLOG, 'blogname' ), ENT_QUOTES, 'UTF-8' ) === (string) $kf['xml']['site'] && false !== DateTime::createFromFormat( DATE_ATOM, (string) $kf['xml']['guncelleme'] ), $kf['xml'] ? (string) $kf['xml']['site'] . ' ' . (string) $kf['xml']['guncelleme'] : '' );
	check( 'Kocist: satir sayisi = gorunen urunler = adet', count( $kf['codes'] ) === count( $k_all ) && count( $k_all ) === (int) ( $kf['xml']['adet'] ?? -1 ), count( $kf['codes'] ) . ' / ' . count( $k_all ) );
	check( 'WOOD KOCIST: satir sayisi = gorunen urunler = adet', count( $wf['codes'] ) === count( $w_all ) && count( $w_all ) === (int) ( $wf['xml']['adet'] ?? -1 ), count( $wf['codes'] ) . ' / ' . count( $w_all ) );
	check( 'sira sitedeki gibi (kodlar)', array_column( $k_all, 'code' ) === $kf['codes'] && array_column( $w_all, 'code' ) === $wf['codes'] );
	check( 'her satirda 12 alan sirayla, ad dolu (iki site)', $kf['tags_ok'] && $wf['tags_ok'] );

	$kr    = $kf['rows'];
	$wr    = $wf['rows'];
	$mine  = array_map( static fn( array $row ): string => $row[1], $rows );
	$k_own = array_values( array_filter( $kf['codes'], static fn( string $code ): bool => str_starts_with( $code, CODE_PART ) ) );
	check( 'deneme urunlerinin hepsi kodlu ve beslemede (Kocist: gizli haric)', array_values( array_diff( $mine, array( 'XMLT-GIZLI' ) ) ) === $k_own && ! array_diff( $mine, $wf['codes'] ), implode( ',', $k_own ) );
	echo '     bilgi: kodsuz satir Kocist ' . count( array_keys( $kf['codes'], '', true ) ) . ', WOOD KOCIST ' . count( array_keys( $wf['codes'], '', true ) ) . " (kod bos yazilir)\n";

	/* ---------- 2) Fiyat ---------- */

	check( 'fiyat: "10.250 ₺" -> 10250.00', '10250.00' === ( $kr['XMLT-FIYAT']['fiyat'] ?? null ) && '10250.00' === ( $wr['XMLT-FIYAT']['fiyat'] ?? null ), (string) ( $kr['XMLT-FIYAT']['fiyat'] ?? '?' ) );
	check( 'fiyat: "1.299,90 ₺" -> 1299.90, "900 ₺" -> 900.00', '1299.90' === ( $kr['XMLT-AD']['fiyat'] ?? null ) && '900.00' === ( $kr['XMLT-RNK-AM']['fiyat'] ?? null ) );
	check( 'fiyatsiz urun: bos fiyat (0 degil)', array_key_exists( 'XMLT-TEKLIF', $kr ) && '' === $kr['XMLT-TEKLIF']['fiyat'] && str_contains( $k['body'], "<kod>XMLT-TEKLIF</kod>\n    <fiyat></fiyat>" ) );
	check( 'duz tutar degil ("1.250 TL\'den başlayan"): bos fiyat', array_key_exists( 'XMLT-BASLAYAN', $kr ) && '' === $kr['XMLT-BASLAYAN']['fiyat'] );
	check( 'USD ("24,50 $"): bos fiyat', array_key_exists( 'XMLT-USD', $kr ) && '' === $kr['XMLT-USD']['fiyat'] );
	check( 'indirimsiz_fiyat ve kdv_orani her satirda bos', ! array_filter( array_merge( array_column( $kr, 'indirimsiz_fiyat' ), array_column( $kr, 'kdv_orani' ), array_column( $wr, 'indirimsiz_fiyat' ), array_column( $wr, 'kdv_orani' ) ), 'strlen' ) );
	check( 'hicbir satirda 0 fiyat yok', ! preg_match( '#<fiyat>0(\.00)?</fiyat>#', $k['body'] . $w['body'] ) );

	$cases = array(
		'10.250 ₺'              => '10250.00',
		'1.299,90 ₺'            => '1299.90',
		'48.500,00 ₺'           => '48500.00',
		'450 TL'                => '450.00',
		'450 tl'                => '450.00',
		' 2.80 TL '             => '2.80',
		'3500₺'                 => '3500.00',
		'0'                     => '',
		'0 TL'                  => '',
		'10250'                 => '',
		'24,50 $'               => '',
		"1.250 TL'den başlayan" => '',
		'100 - 200 TL'          => '',
		'1.250 TL + KDV'        => '',
		'Teklif'                => '',
		''                      => '',
	);
	$wrong = array();

	foreach ( $cases as $in => $want ) {
		if ( nwcs_product_feed_price( (string) $in ) !== $want ) {
			$wrong[] = '"' . $in . '" -> "' . nwcs_product_feed_price( (string) $in ) . '"';
		}
	}

	check( 'fiyat ayristirici: ' . count( $cases ) . ' ornek', ! $wrong, implode( ', ', $wrong ) );

	if ( function_exists( 'kocist_price_number' ) ) {
		$same = true;

		foreach ( array( '10.250 ₺', '1.299,90 ₺', '450 TL', '2.80 TL', "1.250 TL'den başlayan", '100 - 200 TL', '10250' ) as $in ) {
			$number = kocist_price_number( $in );
			$same   = $same && ( $number > 0 ? number_format( $number, 2, '.', '' ) : '' ) === nwcs_product_feed_price( $in );
		}

		check( 'TL fiyatlarda Kocist JSON-LD kuraliyla (kocist_price_number) ayni', $same );
	}

	/* ---------- 3) Site istisnalari ve renk grubu ---------- */

	check( 'gizli urun Kocist\'te yok, WOOD KOCIST\'te var', ! isset( $kr['XMLT-GIZLI'] ) && isset( $wr['XMLT-GIZLI'] ) );
	check( 'site basina adres: Kocist /urun/xmlt-ozel-adres/', get_home_url( K_BLOG, '/urun/xmlt-ozel-adres/' ) === ( $kr['XMLT-ADRES']['link'] ?? '' ), (string) ( $kr['XMLT-ADRES']['link'] ?? '' ) );
	check( 'site basina adres: WOOD KOCIST havuzdaki adres', get_home_url( W_BLOG, '/urun/' . nwcs_pool_products()[ $ids['s'] ]['slug'] . '/' ) === ( $wr['XMLT-ADRES']['link'] ?? '' ), (string) ( $wr['XMLT-ADRES']['link'] ?? '' ) );
	check( 'link aciliyor (Kocist ozel adres 200)', 200 === http( path_of( (string) ( $kr['XMLT-ADRES']['link'] ?? '' ) ) )['code'] );
	check( 'site basina ad: Kocist ozel ad', 'Ölçü <20 mm & "Çift" Şık İğne' === ( $kr['XMLT-AD']['ad'] ?? '' ), (string) ( $kr['XMLT-AD']['ad'] ?? '' ) );
	check( 'site basina ad: WOOD KOCIST havuzdaki ad ("&" cozulmus)', 'XML Deneme Kod & Ad Çiçekli' === ( $wr['XMLT-AD']['ad'] ?? '' ), (string) ( $wr['XMLT-AD']['ad'] ?? '' ) );

	$collapsed = array_intersect( array_map( 'intval', array_column( nwcs_color_collapse( $k_all, K_BLOG ), 'id' ) ), array( $ids['g1'], $ids['g2'] ) );
	check( 'renk grubu: iki uye ayri satir, renk adiyla (iki site)', 'Açık Meşe' === ( $kr['XMLT-RNK-AM']['renk'] ?? '' ) && 'Füme' === ( $kr['XMLT-RNK-FM']['renk'] ?? '' ) && 'Açık Meşe' === ( $wr['XMLT-RNK-AM']['renk'] ?? '' ) && 'Füme' === ( $wr['XMLT-RNK-FM']['renk'] ?? '' ) );
	check( 'karsilastirma: kart listesinde grup tek kart (besleme sadelesmiyor)', 1 === count( $collapsed ) );
	check( 'grupta olmayan urunde renk bos', '' === ( $kr['XMLT-FIYAT']['renk'] ?? null ) );

	/* ---------- 4) Gorseller ---------- */

	$p    = $kr['XMLT-FIYAT'] ?? array();
	$base = get_home_url( K_BLOG, '/wp-content/uploads/' );
	$name = static fn( string $url ): string => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );

	check( 'gorsel: galerinin ilki (b), tam boy (olcu eki yok)', 'xmlt-b.jpg' === $name( (string) ( $p['gorsel'] ?? '' ) ), (string) ( $p['gorsel'] ?? '' ) );
	check( 'ek_gorseller: kalanlar galeri sirasiyla "|" ile (a|c)', array( 'xmlt-a.jpg', 'xmlt-c.jpg' ) === array_map( $name, explode( '|', (string) ( $p['ek_gorseller'] ?? '' ) ) ), (string) ( $p['ek_gorseller'] ?? '' ) );
	check( 'gorseller: hepsi sirayla (b, a, c), ilki gorsel', array( 'xmlt-b.jpg', 'xmlt-a.jpg', 'xmlt-c.jpg' ) === array_map( $name, (array) ( $p['gorseller'] ?? array() ) ) && ( $p['gorseller'][0] ?? '' ) === ( $p['gorsel'] ?? '-' ) );
	check( 'gorsel adresi mutlak, sitenin adresinde (Kocist)', str_starts_with( (string) ( $p['gorsel'] ?? '' ), $base ), (string) ( $p['gorsel'] ?? '' ) . ' / ' . $base );
	check( 'gorsel adresi sitenin adresinde (WOOD KOCIST)', str_starts_with( (string) ( $wr['XMLT-FIYAT']['gorsel'] ?? '' ), get_home_url( W_BLOG, '/wp-content/uploads/' ) ), (string) ( $wr['XMLT-FIYAT']['gorsel'] ?? '' ) );

	$img = http( path_of( (string) ( $p['gorsel'] ?? '' ) ) );
	check( 'gorsel aciliyor (200, image/jpeg)', 200 === $img['code'] && str_starts_with( header_of( $img, 'content-type' ), 'image/jpeg' ), $img['code'] . ' ' . header_of( $img, 'content-type' ) );

	$ph  = $kr['XMLT-YT1'] ?? array();
	$ph2 = $kr['XMLT-YT2'] ?? array();
	check( 'yer tutucu (alt metin) atlanir: gorsel d, ek yok', 'xmlt-d.jpg' === $name( (string) ( $ph['gorsel'] ?? '' ) ) && '' === ( $ph['ek_gorseller'] ?? null ) && 1 === count( (array) ( $ph['gorseller'] ?? array() ) ), wp_json_encode( $ph ) );
	check( 'yalnizca seed gorseli (ornek-urun-*) olan urunde gorsel bos', array_key_exists( 'XMLT-YT2', $kr ) && '' === $ph2['gorsel'] && '' === $ph2['ek_gorseller'] && array() === $ph2['gorseller'], wp_json_encode( $ph2 ) );
	check( 'beslemede yer tutucu dosya adi yok', ! str_contains( $k['body'] . $w['body'], 'xmlt-demo' ) && ! str_contains( $k['body'] . $w['body'], 'ornek-urun-' ) );
	check( 'fotografsiz urunde gorsel alanlari bos', '' === ( $kr['XMLT-TEKLIF']['gorsel'] ?? null ) && str_contains( $k['body'], "<kod>XMLT-TEKLIF</kod>\n    <fiyat></fiyat>\n    <indirimsiz_fiyat></indirimsiz_fiyat>\n    <gorsel></gorsel>\n    <ek_gorseller></ek_gorseller>\n    <gorseller></gorseller>" ) );

	if ( function_exists( 'kocist_is_placeholder_image' ) ) {
		check( 'Kocist temasi da ayni gorseli yer tutucu sayiyor', kocist_is_placeholder_image( array( 'alt' => $files['yt'][1], 'url' => 'http://x/wp-content/uploads/2026/10/xmlt-demo.jpg' ) ) );
	}

	/* ---------- 5) Kategori ve aciklama ---------- */

	$k_label = (string) ( nwcs_site_category_tree( K_BLOG )[ $k_parent ]['label'] ?? '' );
	$w_label = (string) ( nwcs_site_category_tree( W_BLOG )[ $w_parent ]['label'] ?? '' );
	check( 'kategori: "Ust baslik > Kategori" (Kocist)', '' !== $k_label && $k_label . ' > ZZ XML Deneme' === ( $kr['XMLT-FIYAT']['kategori'] ?? '' ) && str_contains( $k['body'], '<kategori>' . nwcs_product_feed_x( $k_label ) . ' &gt; ZZ XML Deneme</kategori>' ), (string) ( $kr['XMLT-FIYAT']['kategori'] ?? '' ) );
	check( 'kategori: "Seri > Kategori" (WOOD KOCIST)', '' !== $w_label && $w_label . ' > ZZ XML Deneme' === ( $wr['XMLT-FIYAT']['kategori'] ?? '' ), (string) ( $wr['XMLT-FIYAT']['kategori'] ?? '' ) );
	check( 'kategori: yerlesimsizde yalnizca kategori adi', 'ZZ XML Yersiz' === ( $kr['XMLT-YERSIZ']['kategori'] ?? '' ) && 'ZZ XML Yersiz' === ( $wr['XMLT-YERSIZ']['kategori'] ?? '' ), (string) ( $kr['XMLT-YERSIZ']['kategori'] ?? '' ) );

	$text = (string) ( $p['aciklama'] ?? '' );
	check( 'aciklama: kisa aciklama', 'Kısa açıklama.' === ( $kr['XMLT-TEKLIF']['aciklama'] ?? '' ) );
	check( 'aciklama: kisa bossa uzun aciklamadan ~300 karakter, duz yazi, kelime sinirinda "…"', str_starts_with( $text, 'Birinci paragraf. İkinci paragraf: akasya' ) && mb_strlen( $text ) <= 301 && mb_strlen( $text ) > 200 && str_ends_with( $text, '…' ) && ! str_contains( $text, '<' ) && ! str_contains( $text, '  ' ), mb_strlen( $text ) . ' ' . $text );
	check( 'aciklama: aciklamasiz urunde bos', '' === ( $kr['XMLT-USD']['aciklama'] ?? null ) );

	/* ---------- 6) Ozel karakterler ---------- */

	check( 'ozel karakterler geri okunur (&, <, ", Turkce harfler)', $spec === ( $kr['XMLT-AD']['aciklama'] ?? '' ), (string) ( $kr['XMLT-AD']['aciklama'] ?? '' ) );
	check( 'ozel karakterler kacirilmis (&lt; &amp; &quot;), Turkce harfler oldugu gibi', str_contains( $k['body'], '<ad>Ölçü &lt;20 mm &amp; &quot;Çift&quot; Şık İğne</ad>' ) && str_contains( $k['body'], '<aciklama>Nem %18 &lt;20 mm &amp; &quot;kuru&quot; — çam, ığdır, ŞÖĞÜÇİ</aciklama>' ) );
	check( 'XML kacirma: kontrol karakteri atilir, gecersiz UTF-8 bozmaz', 'a&lt;b&gt;c&amp;d&quot;e&apos;f' === nwcs_product_feed_x( "a<b>c&d\"e'f\x01" ) && false !== simplexml_load_string( '<x>' . nwcs_product_feed_x( "ok\xC3" ) . '</x>' ) );

	/* ---------- 7) Basliklar, yol ve diger siteler ---------- */

	foreach ( array( 'kocist' => $k, 'woodkocist' => $w ) as $site => $response ) {
		check( $site . ': Content-Type application/xml; charset=UTF-8', 'application/xml; charset=UTF-8' === header_of( $response, 'content-type' ), header_of( $response, 'content-type' ) );
		check( $site . ': X-Robots-Tag noindex, Cache-Control public, max-age=900', 'noindex' === header_of( $response, 'x-robots-tag' ) && 'public, max-age=900' === header_of( $response, 'cache-control' ), header_of( $response, 'x-robots-tag' ) . ' / ' . header_of( $response, 'cache-control' ) );
		check( $site . ': yonlendirme yok', '' === header_of( $response, 'location' ) );
	}

	$head = http( '/kocist/urunler.xml', 'HEAD' );
	check( 'HEAD: 200, basliklar, govde yok', 200 === $head['code'] && 'application/xml; charset=UTF-8' === header_of( $head, 'content-type' ) && '' === $head['body'], $head['code'] . ' ' . strlen( $head['body'] ) );

	$query = http( '/kocist/urunler.xml?v=2' );
	check( 'sorgu metni yok sayilir (?v=2 ayni besleme)', 200 === $query['code'] && feed_rows( $query['body'] )['codes'] === $kf['codes'] );

	foreach ( array( '/kocist/urunler.xml/', '/kocist/urunler.xmlx', '/kocist/urun/urunler.xml' ) as $path ) {
		$r = http( $path );
		check( 'tam esitlik: ' . $path . ' besleme degil (404)', 404 === $r['code'] && ! str_contains( $r['body'], '<urunler' ), (string) $r['code'] );
	}

	foreach ( array( '/sanayi-palet/urunler.xml', '/ahsapkasa/urunler.xml', '/urunler.xml' ) as $path ) {
		$r = http( $path );
		check( 'katalog olmayan site 404: ' . $path, 404 === $r['code'] && ! str_contains( $r['body'], '<urunler' ) && ! str_starts_with( header_of( $r, 'content-type' ), 'application/xml' ), $r['code'] . ' ' . header_of( $r, 'content-type' ) );
	}

	check( 'yalnizca katalog siteleri acik (Kocist, WOOD KOCIST)', nwcs_product_feed_enabled( K_BLOG ) && nwcs_product_feed_enabled( W_BLOG ) && ! nwcs_product_feed_enabled( 10 ) && ! nwcs_product_feed_enabled( 1 ) );

	$extra = implode( ' ', array_column( nwcs_seo_extra_pages(), 'url' ) );
	check( 'site haritasinda ve llms.txt\'de yok', ! str_contains( $extra, 'urunler.xml' ) && ! str_contains( nwcs_seo_llms_text(), 'urunler.xml' ) );

	/* ---------- 8) E_ALL ---------- */

	$strict = strict_notices( array( '/kocist/urunler.xml', '/woodkocist/urunler.xml', '/sanayi-palet/urunler.xml', '/kocist/urunler.xml/' ) );
	check( 'besleme E_ALL ile: temada ve eklentide uyari yok', ! $strict, implode( ' | ', array_slice( $strict, 0, 4 ) ) );
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

while ( ms_is_switched() ) {
	restore_current_blog();
}

cleanup( $state );

switch_to_blog( $pool );
$left = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_nwcs_code', 'value' => CODE_PART, 'compare' => 'LIKE' ) ) ) );
$att  = array_filter( array_map( 'get_post', $state['attachments'] ) );
$cats = array_filter( array( get_term_by( 'slug', TERM_SLUG, NWCS_PRODUCT_TAX ), get_term_by( 'slug', TERM_LOOSE, NWCS_PRODUCT_TAX ) ) );
restore_current_blog();

$restored = true;

foreach ( $state['sites'] as $blog => $options ) {
	foreach ( $options as $option => $value ) {
		$restored = $restored && get_blog_option( (int) $blog, $option, null ) === $value;
	}
}

check( 'temizlik: deneme urunleri, gorseller ve kategoriler kalmadi, site ayarlari eski haline dondu', ! $left && ! $att && ! $cats && $restored );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
exit( $fail ? 1 : 0 );
