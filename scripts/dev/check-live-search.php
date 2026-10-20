<?php
/**
 * Yerel deneme: canli arama (eklenti includes/live-search.php, assets/live-search.js;
 * Kocist inc/search.php, WOOD KOCIST inc/catalog.php).
 *
 * Kendi deneme kategorisini ve urunlerini olusturur ("ZZ Canli Deneme"; kodlar
 * LVS-..., bir fotografli urun, Turkce harfli urun, iki uyeli renk grubu ve
 * sinirin asilmasi icin sekiz "Çoklu" urun), Kocist'te ve WOOD KOCIST'te
 * gosterir. Denenenler:
 *   - ucun bicimi: anahtarlar, turler, en fazla 8 oge, toplam, sonuc adresi;
 *     en az 2 harf/rakam, 60 karakter siniri, q disindaki parametreler ve
 *     dizi q (400);
 *   - siralama sonuc sayfasiyla ayni: Kocist kocist_search ve /arama/ kartlari
 *     (tam kod, kodla baslayan, kodda gecen, adda gecen), WOOD KOCIST
 *     wk_filter_products ve /magaza/?ara= kartlari;
 *   - Turkce katlama (ÇİÇEKLİ = cicekli = çiçekli, İĞNE = igne) iki sitede;
 *   - renk grubu sadelesmez: iki renk iki oge (aramasiz kart listesi tek kart);
 *   - siteye gore bicim: WOOD KOCIST fiyat + "+ KDV" / "KDV dahil", fiyatsizda
 *     "Fiyat teklifle" ve sepete eklenemez; Kocist fiyatsizda "Fiyat teklifle",
 *     her urun teklif sepetine eklenebilir; kucuk resim ve olculu orta boy
 *     gorsel; kisa metin ~160 karakter;
 *   - uc yalnizca acan sitede: diger temalar ve ana site 404; Kocist'te arama
 *     sayfasi yayinda degilken uc da, kutudaki isaret de yok;
 *   - onbellek basliklari (public, max-age=300; LiteSpeed no-cache);
 *   - sayfalarda betik, ayar ve data-nwcs-live; baska sitede betik yok;
 *   - uc ve sayfalar E_ALL ile: temada ve eklentide uyari yok.
 * Sonunda her seyi siler, site ayarlarini eski haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-live-search.php
 *   ... check-live-search.php keep    deneme verisini birakir (tarayici denemesi)
 *   ... check-live-search.php clean   birakilan deneme verisini siler
 */

$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/kocist/';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

error_reporting( E_ALL );

$mode = (string) ( $argv[1] ?? '' );
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
const OTHER_BLOG = 10; // Sanayi Palet (canli arama yok)
const TERM_SLUG  = 'zz-canli-deneme';
const STATE_KEY  = 'nwcs_check_live_state'; // keep -> clean arasinda eski ayarlar.

if ( K_BLOG !== get_current_blog_id() || ! function_exists( 'kocist_live_search_config' ) || ! function_exists( 'nwcs_live_search_data' ) ) {
	echo "Kocist baglaminda acilmadi ya da canli arama yuklu degil.\n";
	exit( 1 );
}

$pool = nwcs_pool_blog_id();

/**
 * Konteynerin icinden HTTP istegi (Host basligi panelin adresi).
 *
 * @return array{code:int, body:string, headers:array, json:mixed}
 */
function http( string $path ): array {
	$response = wp_remote_get(
		'http://127.0.0.1' . $path,
		array(
			'headers'     => array( 'Host' => 'localhost:8080' ),
			'timeout'     => 40,
			'redirection' => 0,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'code' => 0, 'body' => $response->get_error_message(), 'headers' => array(), 'json' => null );
	}

	$body = (string) wp_remote_retrieve_body( $response );

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $response ),
		'body'    => $body,
		'headers' => wp_remote_retrieve_headers( $response )->getAll(),
		'json'    => json_decode( $body, true ),
	);
}

/** Sitenin veri ucu (HTTP). */
function live( string $site, string $q, string $extra = '' ): array {
	return http( '/' . $site . '/wp-json/nwcs/v1/arama?q=' . rawurlencode( $q ) . $extra );
}

function ids_of( array $list ): array {
	return array_map( static fn( $item ): int => (int) ( $item['id'] ?? 0 ), array_values( $list ) );
}

function header_of( array $response, string $name ): string {
	$value = $response['headers'][ strtolower( $name ) ] ?? '';

	return is_array( $value ) ? (string) end( $value ) : (string) $value;
}

/** Kocist arama sayfasindaki kart adlari, sayfadaki sirayla. */
function k_card_titles( string $html ): array {
	preg_match_all( '#<span class="k-pcard__title"[^>]*>([^<]*)</span>#', $html, $match );

	return array_map( static fn( string $text ): string => html_entity_decode( trim( $text ), ENT_QUOTES, 'UTF-8' ), $match[1] );
}

/** WOOD KOCIST magaza kartlarindaki adlar, sayfadaki sirayla. */
function w_card_titles( string $html ): array {
	preg_match_all( '#class="wk-card__title">\s*<a href="[^"]*">([^<]*)</a>#', $html, $match );

	return array_map( static fn( string $text ): string => html_entity_decode( trim( $text ), ENT_QUOTES, 'UTF-8' ), $match[1] );
}

/**
 * WOOD KOCIST baglaminda (ayri surec): her terim icin magazanin aramasi
 * (wk_filter_products, onerilen sira) ve ucun ayni surecteki cevabi;
 * aramasiz kart listesi (renk grubu tek kart).
 */
function wk_run( array $terms ): array {
	$runner = sys_get_temp_dir() . '/nwcs-wk-live.php';
	$code   = <<<'PHP'
<?php
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/woodkocist/';
error_reporting( E_ALL );
require '/var/www/html/wp-load.php';
$ids = static fn( array $list ): array => array_map( static fn( array $p ): int => (int) $p['id'], array_values( $list ) );
$out = array( '__cards' => $ids( wk_filter_products( '', '', '' ) ), '__all' => $ids( wk_products() ) );
foreach ( json_decode( $argv[1], true ) as $term ) {
	$data         = nwcs_live_search_data( $term );
	$out[ $term ] = array( 'shop' => $ids( wk_filter_products( '', $term, '' ) ), 'live' => $ids( $data['items'] ), 'total' => $data['total'] );
}
echo json_encode( $out );
PHP;

	file_put_contents( $runner, $code );
	$output = (string) shell_exec( 'php ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( (string) wp_json_encode( $terms ) ) . ' 2>/dev/null' );
	unlink( $runner );

	return (array) json_decode( $output, true );
}

/**
 * Istekleri ayri bir PHP sureciyle, tam istek olarak ve E_ALL hata
 * yakalayicisiyla cizer (sayfalar ve REST). Uyarilari dondurur.
 *
 * @param string[] $paths
 * @return string[]
 */
function strict_notices( array $paths ): array {
	$runner = sys_get_temp_dir() . '/nwcs-strict-live.php';
	$code   = <<<'PHP'
<?php
$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_URI']     = $argv[1];
$_GET                       = array();
parse_str( (string) parse_url( $argv[1], PHP_URL_QUERY ), $_GET );
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

/** Sayfadaki window.nwcsLiveSearch ayari; yoksa null. */
function live_config_of( string $html ): ?array {
	// Tek satir JSON; WordPress satirin altina "//# sourceURL" ekler.
	if ( ! preg_match( '#^window\.nwcsLiveSearch = (\{.*\});$#m', $html, $match ) ) {
		return null;
	}

	$config = json_decode( $match[1], true );

	return is_array( $config ) ? $config : null;
}

/* ---------- Temizlik (her kipte ayni yol) ---------- */

/**
 * Deneme verisini siler, site ayarlarini ve sayfalari eski haline getirir.
 * $state: kurulumda saklanan eski degerler.
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

	foreach ( (array) ( $state['pages'] ?? array() ) as $slug => $before ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );

		if ( ! $before && $page ) {
			wp_delete_post( $page->ID, true );
		}
	}

	if ( array_key_exists( 'pages_version', $state ) ) {
		if ( null === $state['pages_version'] ) {
			delete_option( 'kocist_pages_version' );
		} else {
			update_option( 'kocist_pages_version', $state['pages_version'] );
		}
	}

	switch_to_blog( $pool );

	// Kimlik listesi kaybolmus olsa da (yarida kalan deneme) kategori ve kodlardan bulunur.
	$found = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => '_nwcs_code', 'value' => 'LVS', 'compare' => 'LIKE' ) ),
		)
	);

	foreach ( array_unique( array_merge( array_map( 'intval', (array) ( $state['ids'] ?? array() ) ), array_map( 'intval', $found ) ) ) as $id ) {
		$code = (string) get_post_meta( $id, '_nwcs_code', true );

		if ( preg_match( '/^LVS-|-LVS$/', $code ) || in_array( $id, (array) ( $state['ids'] ?? array() ), true ) ) {
			wp_delete_post( $id, true );
		}
	}

	foreach ( (array) ( $state['attachments'] ?? array() ) as $attachment ) {
		wp_delete_attachment( (int) $attachment, true );
	}

	$term = get_term_by( 'slug', TERM_SLUG, NWCS_PRODUCT_TAX );

	if ( $term ) {
		wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
	}

	restore_current_blog();
	nwcs_pool_flush_cache();
	delete_site_option( STATE_KEY );
}

if ( 'clean' === $mode ) {
	cleanup( (array) get_site_option( STATE_KEY, array() ) );
	switch_to_blog( $pool );
	$left = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_nwcs_code', 'value' => 'LVS', 'compare' => 'LIKE' ) ) ) );
	restore_current_blog();
	check( 'temizlik: deneme urunleri ve kategorisi kalmadi', ! $left && ! get_term_by( 'slug', TERM_SLUG, NWCS_PRODUCT_TAX ) && ! get_site_option( STATE_KEY ) );
	echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
	exit( $fail ? 1 : 0 );
}

if ( get_site_option( STATE_KEY ) ) {
	echo "Onceki 'keep' denemesinin verisi duruyor: once 'clean' calistirin.\n";
	exit( 1 );
}

/* ---------- Deneme verisi ---------- */

$state = array(
	'sites'         => array(),
	'pages'         => array(),
	'pages_version' => get_option( 'kocist_pages_version', null ),
	'ids'           => array(),
	'attachments'   => array(),
);

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	foreach ( array( NWCS_OPTION_MODE, NWCS_OPTION_SELECTED, NWCS_OPTION_CONTENT ) as $option ) {
		$state['sites'][ $blog ][ $option ] = get_blog_option( $blog, $option, null );
	}
}

foreach ( array( 'arama', 'teklif-sepeti' ) as $slug ) {
	$page                    = get_page_by_path( $slug, OBJECT, 'page' );
	$state['pages'][ $slug ] = $page ? (int) $page->ID : 0;
}

kocist_ensure_pages();

switch_to_blog( $pool );

$term    = wp_insert_term( 'ZZ Canlı Deneme', NWCS_PRODUCT_TAX, array( 'slug' => TERM_SLUG ) );
$term_id = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
$long    = 'Akasya ağacından katlanır Adirondack sandalye; geniş kolçaklar, eğimli sırt ve dış mekân yağıyla korunmuş yüzey. Bahçe, teras ve balkon için hazır gelir, kurulum gerektirmez ve uzun yıllar kullanılır.';

$rows = array(
	'a' => array( 'LV Deneme Adirondack Sandalye Füme', 'LVS-ADR-FB01', '10.250 ₺', $long ),
	'b' => array( 'LV Deneme Adirondack Sandalye Tik', 'LVS-ADR-K-TK', '', 'Kısa açıklama.' ),
	'c' => array( 'LV Deneme Şezlong', 'ADRK-LVS', '1.299,90 ₺', '' ),
	'd' => array( 'LV Deneme Çardak ADR K Uyumlu', 'LVS-KML-01', '', '' ),
	'e' => array( 'LV Deneme Çiçekli İğne Yastık', 'LVS-IGNE', '450 ₺', '' ),
	'g' => array( 'LV Deneme Renkli Masa Beyaz', 'LVS-RNK-B', '900 ₺', '' ),
	'h' => array( 'LV Deneme Renkli Masa Siyah', 'LVS-RNK-S', '900 ₺', '' ),
);

for ( $n = 1; $n <= 8; $n++ ) {
	$rows[ 'm' . $n ] = array( 'LV Deneme Çoklu Ürün ' . $n, sprintf( 'LVS-COK-%02d', $n ), 0 === $n % 2 ? '1.000 ₺' : '', '' );
}

$ids = array();

foreach ( $rows as $key => $row ) {
	$ids[ $key ] = wp_insert_post(
		array(
			'post_type'    => NWCS_PRODUCT_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $row[0],
			'post_content' => 'Deneme ürünü açıklaması.',
			'menu_order'   => count( $ids ) + 1,
		)
	);
	update_post_meta( $ids[ $key ], '_nwcs_code', $row[1] );

	if ( '' !== $row[2] ) {
		update_post_meta( $ids[ $key ], '_nwcs_price', $row[2] );
	}

	if ( '' !== $row[3] ) {
		update_post_meta( $ids[ $key ], '_nwcs_short', $row[3] );
	}

	wp_set_object_terms( $ids[ $key ], array( $term_id ), NWCS_PRODUCT_TAX );
}

// Fotograf: a ve g (GD ile uretilmis; yer tutucu sayilmayan ad ve alt metin).
$upload = wp_upload_dir();
$colors = array( 'a' => array( 122, 86, 52 ), 'g' => array( 230, 226, 214 ) );

foreach ( $colors as $key => $rgb ) {
	$file  = trailingslashit( $upload['path'] ) . 'lvs-canli-deneme-' . $key . '.jpg';
	$image = imagecreatetruecolor( 1200, 900 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] ) );
	imagefilledrectangle( $image, 300, 200, 900, 700, imagecolorallocate( $image, 245, 240, 230 ) );
	imagejpeg( $image, $file, 82 );
	imagedestroy( $image );

	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'LVS canlı deneme ' . $key,
			'post_status'    => 'inherit',
		),
		$file
	);
	wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $file ) );
	update_post_meta( $attachment, '_wp_attachment_image_alt', 'LVS canlı arama deneme fotoğrafı' );
	update_post_meta( $ids[ $key ], '_nwcs_gallery', array( $attachment ) );
	$state['attachments'][] = $attachment;
}

restore_current_blog();

$state['ids'] = array_values( $ids );
nwcs_pool_flush_cache();

$k_key    = nwcs_catalog_site_key( K_BLOG );
$w_key    = nwcs_catalog_site_key( W_BLOG );
$w_parent = (string) array_key_first( nwcs_site_parents( W_BLOG ) );

nwcs_set_placement( TERM_SLUG, $k_key, 'kereste' );
nwcs_set_placement( TERM_SLUG, $w_key, $w_parent );

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	update_blog_option( $blog, NWCS_OPTION_MODE, 'selected' );
	nwcs_selection_append( $blog, array_values( $ids ) );
}

nwcs_color_groups_write(
	TERM_SLUG,
	array(
		array(
			'id'      => 'zzcanli1',
			'label'   => 'Renk',
			'members' => array(
				array( 'product_id' => $ids['g'], 'color_name' => 'Beyaz', 'color_hex' => '#ffffff' ),
				array( 'product_id' => $ids['h'], 'color_name' => 'Siyah', 'color_hex' => '#1f1f1f' ),
			),
		),
	)
);

nwcs_pool_flush_cache();

if ( 'keep' === $mode ) {
	update_site_option( STATE_KEY, $state );
	echo "Deneme verisi birakildi (" . count( $ids ) . " urun). Silmek icin: check-live-search.php clean\n";
	echo "  Kocist: http://localhost:8080/kocist/  WOOD KOCIST: http://localhost:8080/woodkocist/  (orn. 'lvs', 'adr k', 'cicekli', 'renkli masa')\n";
	exit( 0 );
}

/* ---------- Denemeler ---------- */

[ 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e, 'g' => $g, 'h' => $h ] = $ids;

try {
	$k_products = array();

	foreach ( kocist_catalog_products() as $product ) {
		$k_products[ (int) $product['id'] ] = $product;
	}

	check( 'deneme verisi: urunler Kocist\'te, arama ve sepet sayfasi yayinda', isset( $k_products[ $a ], $k_products[ $h ] ) && kocist_page_ready( 'arama' ) && kocist_page_ready( 'teklif-sepeti' ) );

	/* ---------- 1) Ucun bicimi ve sinirlar ---------- */

	$r    = live( 'kocist', 'lvs' );
	$json = (array) $r['json'];
	$keys = array( 'id', 'url', 'title', 'code', 'price', 'price_note', 'has_price', 'can_add', 'thumb', 'image', 'text' );

	check( 'uc: 200, JSON { q, total, url, items }', 200 === $r['code'] && array( 'q', 'total', 'url', 'items' ) === array_keys( $json ), $r['code'] . ' ' . substr( $r['body'], 0, 120 ) );
	check( 'uc: en fazla 8 oge, toplam sonuc sayfasiyla ayni', 8 === count( (array) ( $json['items'] ?? array() ) ) && count( kocist_search( 'lvs' ) ) === (int) ( $json['total'] ?? -1 ) && (int) $json['total'] >= 15, ( $json['total'] ?? '?' ) . ' / ' . count( kocist_search( 'lvs' ) ) );
	check( 'uc: sonuc adresi Kocist /arama/?ara=', kocist_search_url( 'lvs' ) === ( $json['url'] ?? '' ) && str_contains( (string) $json['url'], '/kocist/arama/?ara=lvs' ), (string) ( $json['url'] ?? '' ) );

	$first = (array) ( $json['items'][0] ?? array() );
	$types = is_int( $first['id'] ?? null ) && is_string( $first['url'] ?? null ) && is_string( $first['title'] ?? null ) && is_string( $first['code'] ?? null )
		&& is_string( $first['price'] ?? null ) && is_string( $first['price_note'] ?? null ) && is_bool( $first['has_price'] ?? null ) && is_bool( $first['can_add'] ?? null )
		&& is_string( $first['thumb'] ?? null ) && ( null === $first['image'] || is_array( $first['image'] ) ) && is_string( $first['text'] ?? null );
	check( 'oge: anahtarlar ve turler', $keys === array_keys( $first ) && $types, implode( ',', array_keys( $first ) ) );

	foreach ( array( 'l', ' l- ', '--', '' ) as $short ) {
		$s = live( 'kocist', $short );
		check( 'en az 2 harf/rakam: "' . $short . '" aranmaz (200, bos)', 200 === $s['code'] && 0 === ( $s['json']['total'] ?? -1 ) && array() === ( $s['json']['items'] ?? null ) && '' === ( $s['json']['url'] ?? null ), $s['code'] . ' ' . substr( $s['body'], 0, 100 ) );
	}

	$s = live( 'kocist', 'lv' );
	check( '2 harf yeter: "lv" aranir', ( $s['json']['total'] ?? 0 ) >= 15 );

	$s = live( 'kocist', str_repeat( 'ab', 50 ) );
	check( 'uzun terim 60 karakterde kesilir', 60 === mb_strlen( (string) ( $s['json']['q'] ?? '' ) ) && 200 === $s['code'], (string) mb_strlen( (string) ( $s['json']['q'] ?? '' ) ) );

	$s = live( 'kocist', 'lvs', '&limit=50&per_page=50&site=13&blog=13' );
	check( 'q disindaki parametreler yok sayilir (sinir ve site degismez)', 8 === count( (array) ( $s['json']['items'] ?? array() ) ) && ( $s['json']['total'] ?? 0 ) === $json['total'] && str_contains( (string) ( $s['json']['url'] ?? '' ), '/kocist/' ) );

	$s = http( '/kocist/wp-json/nwcs/v1/arama?q[]=lvs' );
	check( 'dizi q: 400', 400 === $s['code'], (string) $s['code'] );

	$s = live( 'kocist', '<b>lvs</b>' );
	check( 'etiketler atilir: "<b>lvs</b>" = "lvs"', 'lvs' === ( $s['json']['q'] ?? '' ) && ( $s['json']['total'] ?? 0 ) === $json['total'], (string) ( $s['json']['q'] ?? '' ) );

	/* ---------- 2) Onbellek basliklari ---------- */

	check( 'onbellek: Cache-Control public, max-age=300', 'public, max-age=300' === header_of( $r, 'cache-control' ), header_of( $r, 'cache-control' ) );
	check( 'onbellek: LiteSpeed tutmaz (no-cache)', 'no-cache' === header_of( $r, 'x-litespeed-cache-control' ), header_of( $r, 'x-litespeed-cache-control' ) );
	$s = live( 'kocist', 'l' );
	check( 'onbellek: kisa terimde de ayni basliklar', 'public, max-age=300' === header_of( $s, 'cache-control' ) );

	/* ---------- 3) Siralama: Kocist sonuc sayfasiyla ayni ---------- */

	$terms = array( 'lvs', 'adr', 'adrk', 'adr k', 'lvs adr fb01', 'LVS-COK', 'çoklu', 'sandalye lvs', 'renkli masa', 'cicekli' );

	foreach ( $terms as $term ) {
		$live = live( 'kocist', $term );
		$page = array_slice( ids_of( kocist_search( $term ) ), 0, 8 );
		check( 'Kocist siralama = kocist_search: "' . $term . '"', ids_of( (array) ( $live['json']['items'] ?? array() ) ) === $page && count( kocist_search( $term ) ) === (int) ( $live['json']['total'] ?? -1 ), implode( ',', ids_of( (array) ( $live['json']['items'] ?? array() ) ) ) . ' / ' . implode( ',', $page ) );
	}

	$order = ids_of( (array) live( 'kocist', 'adrk' )['json']['items'] );
	$mine  = array_values( array_intersect( $order, array( $b, $c, $d ) ) );
	check( 'derece: "adrk" once kodu baslayan (ADRK-LVS), sonra kodda gecen, sonra adda gecen', array( $c, $b, $d ) === $mine, implode( ',', $mine ) );
	check( 'derece: tam kod ilk ("lvs adr fb01")', $a === ( ids_of( (array) live( 'kocist', 'lvs adr fb01' )['json']['items'] )[0] ?? 0 ) );

	foreach ( array( 'lvs', 'adr k' ) as $term ) {
		$html   = http( '/kocist/arama/?ara=' . rawurlencode( $term ) )['body'];
		$titles = array_slice( k_card_titles( $html ), 0, 8 );
		$items  = array_column( (array) live( 'kocist', $term )['json']['items'], 'title' );
		check( 'Kocist /arama/ sayfasindaki kart sirasi = liste: "' . $term . '"', $titles === $items && $titles, implode( ' | ', array_slice( $titles, 0, 3 ) ) );
	}

	/* ---------- 4) Siralama: WOOD KOCIST magaza aramasiyla ayni ---------- */

	$w_terms = array( 'lvs', 'adr', 'lvs-adr', 'renkli masa', 'cicekli', 'ÇİÇEKLİ', 'İĞNE', 'çoklu' );
	$wk      = wk_run( $w_terms );

	check( 'WOOD KOCIST baglami calisti', isset( $wk['__all'] ) && in_array( $a, (array) $wk['__all'], true ), substr( (string) wp_json_encode( $wk ), 0, 120 ) );

	foreach ( $w_terms as $term ) {
		$w    = live( 'woodkocist', $term );
		$shop = array_map( 'intval', (array) ( $wk[ $term ]['shop'] ?? array() ) );
		check( 'WOOD KOCIST siralama = wk_filter_products: "' . $term . '"', ids_of( (array) ( $w['json']['items'] ?? array() ) ) === array_slice( $shop, 0, 8 ) && count( $shop ) === (int) ( $w['json']['total'] ?? -1 ) && ids_of( (array) ( $w['json']['items'] ?? array() ) ) === array_map( 'intval', (array) ( $wk[ $term ]['live'] ?? array() ) ), implode( ',', ids_of( (array) ( $w['json']['items'] ?? array() ) ) ) . ' / ' . implode( ',', array_slice( $shop, 0, 8 ) ) );
	}

	$w = live( 'woodkocist', 'lvs' );
	check( 'WOOD KOCIST: sonuc adresi /magaza/?ara=', str_contains( (string) ( $w['json']['url'] ?? '' ), '/woodkocist/magaza/?ara=lvs' ) && 8 === count( (array) ( $w['json']['items'] ?? array() ) ), (string) ( $w['json']['url'] ?? '' ) );

	$html   = http( '/woodkocist/magaza/?ara=lvs' )['body'];
	$titles = array_slice( w_card_titles( $html ), 0, 8 );
	check( 'WOOD KOCIST /magaza/?ara= kart sirasi = liste', $titles && $titles === array_column( (array) $w['json']['items'], 'title' ), implode( ' | ', array_slice( $titles, 0, 3 ) ) );

	/* ---------- 5) Turkce katlama ---------- */

	foreach ( array( 'ÇİÇEKLİ', 'cicekli', 'çiçekli', 'CICEKLI', 'İĞNE', 'igne', 'IGNE yastik' ) as $term ) {
		$k = ids_of( (array) live( 'kocist', $term )['json']['items'] );
		check( 'katlama (Kocist): "' . $term . '" Çiçekli İğne Yastık\'i bulur', in_array( $e, $k, true ), implode( ',', $k ) );
	}

	foreach ( array( 'ÇİÇEKLİ', 'cicekli', 'İĞNE', 'igne' ) as $term ) {
		$w = ids_of( (array) live( 'woodkocist', $term )['json']['items'] );
		check( 'katlama (WOOD KOCIST): "' . $term . '" bulur', in_array( $e, $w, true ), implode( ',', $w ) );
	}

	/* ---------- 6) Renk grubu sadelesmez ---------- */

	foreach ( array( 'kocist', 'woodkocist' ) as $site ) {
		$res = live( $site, 'renkli masa' );
		$got = ids_of( (array) $res['json']['items'] );
		check( 'renk grubu (' . $site . '): iki renk iki ayri oge, toplam 2', in_array( $g, $got, true ) && in_array( $h, $got, true ) && 2 === (int) $res['json']['total'], implode( ',', $got ) );
	}

	$cards = array_map( 'intval', (array) ( $wk['__cards'] ?? array() ) );
	check( 'karsilastirma: aramasiz kart listesinde grup tek kart', 1 === count( array_intersect( $cards, array( $g, $h ) ) ) );

	/* ---------- 7) Siteye gore bicim ---------- */

	$by = static function ( array $response ): array {
		$out = array();

		foreach ( (array) ( $response['json']['items'] ?? array() ) as $item ) {
			$out[ (int) $item['id'] ] = $item;
		}

		return $out;
	};

	$k  = $by( live( 'kocist', 'lvs adr' ) );
	$ka = $k[ $a ] ?? array();
	$kb = $k[ $b ] ?? array();

	check( 'Kocist: fiyatli urun fiyatla, not yok, sepete eklenebilir', '10.250 ₺' === ( $ka['price'] ?? '' ) && true === ( $ka['has_price'] ?? null ) && '' === ( $ka['price_note'] ?? null ) && true === ( $ka['can_add'] ?? null ), wp_json_encode( $ka ) );
	check( 'Kocist: fiyatsiz urun "Fiyat teklifle" (kartin metni), yine de eklenebilir', (string) nwcs_field( 'kategoriler', 'texts', 'price_quote' ) === ( $kb['price'] ?? '' ) && 'Fiyat teklifle' === ( $kb['price'] ?? '' ) && false === ( $kb['has_price'] ?? null ) && true === ( $kb['can_add'] ?? null ), wp_json_encode( $kb ) );
	check( 'Kocist: urun adresi urun sayfasi, ad ve kod', $k_products[ $a ]['url'] === ( $ka['url'] ?? '' ) && 'LV Deneme Adirondack Sandalye Füme' === ( $ka['title'] ?? '' ) && 'LVS-ADR-FB01' === ( $ka['code'] ?? '' ) );
	check( 'gorsel: kucuk resim (thumbnail) ve olculu orta boy', (bool) preg_match( '#-150x150\.jpg$#', (string) ( $ka['thumb'] ?? '' ) ) && ( $ka['image']['width'] ?? 0 ) > 0 && ( $ka['image']['height'] ?? 0 ) > 0 && str_contains( (string) ( $ka['image']['url'] ?? '' ), 'lvs-canli-deneme-a' ), wp_json_encode( $ka['image'] ?? null ) . ' ' . ( $ka['thumb'] ?? '' ) );
	check( 'gorsel: fotografsiz urunde bos (yer tutucu yok)', array_key_exists( 'image', $kb ) && '' === $kb['thumb'] && null === $kb['image'], wp_json_encode( $kb ) );
	check( 'kisa metin ~160 karakter, kelime sinirinda, "…" ile', mb_strlen( (string) ( $ka['text'] ?? '' ) ) <= 161 && str_ends_with( (string) ( $ka['text'] ?? '' ), '…' ) && str_starts_with( (string) ( $ka['text'] ?? '' ), 'Akasya ağacından' ) && 'Kısa açıklama.' === ( $kb['text'] ?? '' ), (string) ( $ka['text'] ?? '' ) );

	// WOOD KOCIST: KDV haric (+ KDV) ve dahil (KDV dahil); fiyatsiz urun eklenemez.
	$w_content = (array) get_blog_option( W_BLOG, NWCS_OPTION_CONTENT, array() );

	foreach ( array( 'haric' => '+ KDV', 'dahil' => 'KDV dahil' ) as $vat => $note ) {
		$set                                   = $w_content;
		$set['global']['shop']['vat_mode']     = $vat;
		$set['global']['card']['vat_excluded'] = '+ KDV';
		$set['global']['card']['vat_included'] = 'KDV dahil';
		update_blog_option( W_BLOG, NWCS_OPTION_CONTENT, $set );

		// "lvs-adr": iki urun de bulunur.
		$w  = $by( live( 'woodkocist', 'lvs-adr' ) );
		$wa = $w[ $a ] ?? array();
		$wb = $w[ $b ] ?? array();
		check( 'WOOD KOCIST (' . $vat . '): fiyat + "' . $note . '", sepete eklenebilir', '10.250 ₺' === ( $wa['price'] ?? '' ) && $note === ( $wa['price_note'] ?? '' ) && true === ( $wa['can_add'] ?? null ) && true === ( $wa['has_price'] ?? null ), wp_json_encode( $wa ) );
		check( 'WOOD KOCIST (' . $vat . '): fiyatsizda "Fiyat teklifle", not yok, eklenemez', 'Fiyat teklifle' === ( $wb['price'] ?? '' ) && '' === ( $wb['price_note'] ?? null ) && false === ( $wb['can_add'] ?? null ) && false === ( $wb['has_price'] ?? null ), wp_json_encode( $wb ) );
	}

	update_blog_option( W_BLOG, NWCS_OPTION_CONTENT, $w_content );
	check( 'WOOD KOCIST: urun adresi urun sayfasi', str_contains( (string) ( $wa['url'] ?? '' ), '/woodkocist/urun/' ) );

	/* ---------- 8) Uc yalnizca acan sitede ---------- */

	foreach ( array( '/sanayi-palet', '/ahsapkasa', '' ) as $prefix ) {
		$s = http( $prefix . '/wp-json/nwcs/v1/arama?q=lvs' );
		check( 'uc yok: ' . ( '' !== $prefix ? $prefix : 'ana site' ) . ' 404', 404 === $s['code'] && 'rest_no_route' === ( $s['json']['code'] ?? '' ), (string) $s['code'] );
	}

	$other = http( '/sanayi-palet/' )['body'];
	check( 'baska sitede betik ve isaret yok', '' !== $other && ! str_contains( $other, 'live-search.js' ) && ! str_contains( $other, 'data-nwcs-live' ) );

	// Kocist: arama sayfasi yayinda degilken uc yok, kutuda isaret yok.
	$arama = get_page_by_path( 'arama', OBJECT, 'page' );
	wp_update_post( array( 'ID' => $arama->ID, 'post_status' => 'draft' ) );

	try {
		$s    = http( '/kocist/wp-json/nwcs/v1/arama?q=lvs' );
		$home = http( '/kocist/' )['body'];
		check( 'Kocist arama sayfasi yokken: uc 404', 404 === $s['code'], (string) $s['code'] );
		check( 'Kocist arama sayfasi yokken: arama kutusu, betik ve isaret yok', '' !== $home && ! str_contains( $home, 'data-nwcs-live' ) && ! str_contains( $home, 'live-search.js' ) && ! str_contains( $home, 'k-search-input' ) );
	} finally {
		wp_update_post( array( 'ID' => $arama->ID, 'post_status' => 'publish' ) );
	}

	check( 'Kocist arama sayfasi geri yayinda, uc yine 200', 'publish' === get_post_status( $arama->ID ) && 200 === live( 'kocist', 'lvs' )['code'] );

	/* ---------- 9) Sayfalarda betik, ayar ve isaret ---------- */

	$home   = http( '/kocist/' )['body'];
	$config = live_config_of( $home );
	$ver    = (string) filemtime( NWCS_DIR . 'assets/live-search.js' );

	check( 'Kocist: ust menu ve cekmece kutusunda data-nwcs-live', (bool) preg_match( '#id="k-search-input"[^>]*data-nwcs-live#', $home ) && (bool) preg_match( '#id="k-nav-search"[^>]*data-nwcs-live#', $home ) );
	check( 'Kocist: betik dosya zamaniyla surumlu', str_contains( $home, 'network-content-studio/assets/live-search.js?ver=' . $ver ) );
	check( 'Kocist: ayar (veri ucu, onek, metinler panelden)', is_array( $config ) && rest_url( 'nwcs/v1/arama' ) === ( $config['endpoint'] ?? '' ) && 'k-ls' === ( $config['prefix'] ?? '' ) && 'Tüm sonuçlar ({sayi})' === ( $config['texts']['all'] ?? '' ) && 'Teklif sepetine ekle' === ( $config['texts']['add'] ?? '' ) && kocist_basket_url() === ( $config['cart'] ?? '' ) && 60 === ( $config['max'] ?? 0 ) && 2 === ( $config['min'] ?? 0 ), substr( (string) wp_json_encode( $config ), 0, 160 ) );
	check( 'Kocist: sonuc yoksa iletisim ve WhatsApp (terimli mesaj kalibi)', 'Bize yazın' === ( $config['links'][0]['label'] ?? '' ) && str_starts_with( (string) ( $config['wa']['base'] ?? '' ), 'https://wa.me/' ) && str_contains( (string) ( $config['wa']['msg'] ?? '' ), '{terim}' ) );

	$page = http( '/kocist/arama/?ara=lvs' )['body'];
	check( 'Kocist: arama sayfasindaki buyuk kutuda isaret yok (yalnizca ust menu ve cekmece)', ! preg_match( '#id="k-search-page"[^>]*data-nwcs-live#', $page ) && str_contains( $page, 'id="k-search-page"' ) );

	$w_home   = http( '/woodkocist/' )['body'];
	$w_config = live_config_of( $w_home );
	check( 'WOOD KOCIST: arama kutusunda data-nwcs-live', (bool) preg_match( '#id="wk-search-input"[^>]*data-nwcs-live#', $w_home ) );
	check( 'WOOD KOCIST: ayar (kendi veri ucu, sepet, metinler)', is_array( $w_config ) && str_contains( (string) ( $w_config['endpoint'] ?? '' ), '/woodkocist/wp-json/nwcs/v1/arama' ) && 'wk-ls' === ( $w_config['prefix'] ?? '' ) && 'Sepete ekle' === ( $w_config['texts']['add'] ?? '' ) && 99 === ( $w_config['maxQty'] ?? 0 ) && true === ( $w_config['plate'] ?? null ), substr( (string) wp_json_encode( $w_config ), 0, 160 ) );
	preg_match( '#^window\.nwcsLiveSearch = .*$#m', $home, $line );
	check( 'ayar: "</script>" gibi metin betigi kapatamaz (JSON_HEX_TAG: satirda < > yok)', isset( $line[0] ) && ! preg_match( '/[<>]/', $line[0] ) );

	/* ---------- 10) E_ALL ---------- */

	$strict = strict_notices(
		array(
			'/kocist/wp-json/nwcs/v1/arama?q=lvs',
			'/kocist/wp-json/nwcs/v1/arama?q=zzzyokboyle',
			'/kocist/wp-json/nwcs/v1/arama?q=a',
			'/woodkocist/wp-json/nwcs/v1/arama?q=lvs',
			'/woodkocist/wp-json/nwcs/v1/arama?q=renkli%20masa',
			'/sanayi-palet/wp-json/nwcs/v1/arama?q=lvs',
			'/kocist/',
			'/kocist/arama/?ara=lvs',
			'/woodkocist/',
			'/woodkocist/magaza/?ara=lvs',
		)
	);
	check( 'uc ve sayfalar E_ALL ile: temada ve eklentide uyari yok', ! $strict, implode( ' | ', array_slice( $strict, 0, 4 ) ) );
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

cleanup( $state );

switch_to_blog( $pool );
$left = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_nwcs_code', 'value' => 'LVS', 'compare' => 'LIKE' ) ) ) );
$att  = array_filter( array_map( 'get_post', $state['attachments'] ) );
restore_current_blog();

check( 'temizlik: deneme urunleri, gorseller ve kategorisi kalmadi, site ayarlari eski haline dondu', ! $left && ! $att && ! get_term_by( 'slug', TERM_SLUG, NWCS_PRODUCT_TAX ) && $state['sites'][ W_BLOG ][ NWCS_OPTION_CONTENT ] === get_blog_option( W_BLOG, NWCS_OPTION_CONTENT, null ) );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
exit( $fail ? 1 : 0 );
