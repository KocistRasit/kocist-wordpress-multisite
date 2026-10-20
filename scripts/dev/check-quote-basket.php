<?php
/**
 * Yerel deneme: Kocist arama (/arama/) ve teklif sepeti (/teklif-sepeti/).
 *
 * Kendi deneme kategorilerini ve urunlerini olusturur, Kocist'te (ve aramayi
 * karsilastirmak icin WOOD KOCIST'te) gosterir:
 *   "ZZ Teklif Deneme" (Kereste grubunda): kodlari ADR/ADRK etrafinda alti
 *   urun, Turkce harfli bir urun ve iki uyeli bir renk grubu;
 *   "ZZ Teklif Dekor" (Dekorasyon grubunda): tek urun (WhatsApp numarasi).
 * Denenenler:
 *   - eklentinin arama yardimcilari: Turkce katlama, ayiracsiz eslesme,
 *     siralama (tam kod, kodla baslayan, kodda gecen, adda, kelimeler), bos ve
 *     kisa terim; renk grubunun uyeleri ayri ayri bulunur;
 *   - arama sayfasi (HTTP): baslik, kart sirasi, sonuc yok (iletisim ve
 *     WhatsApp), noindex, follow; site haritasi ve llms.txt'de yok;
 *   - cerez okuma ve yazma (bicim, sinirlar);
 *   - JavaScript'siz isleyiciler: ekle (donus adresi, bilinmeyen urun, dolu
 *     sepet), adet, +/−, cikar, temizle; HTTP ile Set-Cookie ve yonlendirme,
 *     urun sayfasinin "eklendi" donusu, sepet sayfasi (satirlar, fiyat, toplam
 *     yok), onbellek basliklari;
 *   - e-posta ile gonderim: bot tuzagi, nonce, onay kutusu, bos sepet, eksik
 *     alanlar (yazilanlar geri gelir), e-posta gitmezse durust hata (sepet
 *     kalir), basari (kayit, Mailpit'e bildirim, sepet bosalir, ikinci
 *     bildirim yok), donus sayfasi ("Talebiniz alındı" ve hata);
 *   - WhatsApp mesaji ve numara secimi (tek grup, karisik, gruba ait olmayan),
 *     uzun listede kisaltma ve adres siniri;
 *   - sayfalar acilmadan ust menu ve ekleme formu basilmaz;
 *   - WOOD KOCIST aramasi eskisi gibi (yalnizca Turkce katlama eklendi) ve
 *     sepeti eskisi gibi;
 *   - sayfalar E_ALL ile cizilince temada ve eklentide uyari yok.
 * Sonunda her seyi siler, site ayarlarini eski haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-quote-basket.php
 */

define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/kocist/wp-admin/admin-post.php';

require '/var/www/html/wp-load.php';

error_reporting( E_ALL );

class Nwcs_Redirect extends Exception {}

add_filter(
	'wp_redirect',
	static function ( $location ) {
		throw new Nwcs_Redirect( (string) $location );
	},
	99
);

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

const K_BLOG = 8;  // Kocist
const W_BLOG = 13; // WOOD KOCIST

if ( K_BLOG !== get_current_blog_id() || ! function_exists( 'kocist_handle_basket' ) ) {
	echo "Kocist baglaminda acilmadi.\n";
	exit( 1 );
}

$admin = get_user_by( 'login', get_super_admins()[0] )->ID;
wp_set_current_user( $admin );

$pool         = nwcs_pool_blog_id();
$sites_before = array();

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	foreach ( array( NWCS_OPTION_MODE, NWCS_OPTION_SELECTED, 'blog_public' ) as $option ) {
		$sites_before[ $blog ][ $option ] = get_blog_option( $blog, $option, null );
	}
}

$quotes_before = get_posts( array( 'post_type' => 'kc_quote', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
$tokens        = array(); // Olusan gecici durum kayitlari (temizlikte silinir).

/** Yonlendirme adresindeki ?kc= durum kaydi. */
function state_of( string $location ): array {
	global $tokens;

	parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $args );
	$token = (string) ( $args['kc'] ?? '' );

	if ( '' === $token ) {
		return array();
	}

	$tokens[] = $token;
	$state    = get_transient( 'kc_quote_' . $token );

	return is_array( $state ) ? $state : array();
}

/** Isleyiciyi POST verisiyle calistirir, yonlendirme adresini dondurur. */
function post( callable $handler, array $data ): string {
	$_POST    = wp_slash( $data );
	$_REQUEST = $_POST;

	try {
		$handler();
	} catch ( Nwcs_Redirect $redirect ) {
		return $redirect->getMessage();
	} finally {
		$_POST    = array();
		$_REQUEST = array();
	}

	return '';
}

/** Cerezdeki sepet (ham metin). */
function cookie(): string {
	return (string) ( $_COOKIE[ KOCIST_BASKET_COOKIE ] ?? '' );
}

function set_cookie_value( string $value ): void {
	$_COOKIE[ KOCIST_BASKET_COOKIE ] = $value;
}

/**
 * Konteynerin icinden HTTP istegi (Host basligi panelin adresi). Yonlendirme
 * izlenmez: Location ve Set-Cookie okunur.
 *
 * @return array{code:int, body:string, headers:array}
 */
function http( string $path, array $cookies = array(), ?array $post = null ): array {
	$args = array(
		'headers'     => array( 'Host' => 'localhost:8080' ),
		'timeout'     => 40,
		'redirection' => 0,
		'cookies'     => $cookies,
	);

	$response = null === $post
		? wp_remote_get( 'http://127.0.0.1' . $path, $args )
		: wp_remote_post( 'http://127.0.0.1' . $path, $args + array( 'body' => $post ) );

	if ( is_wp_error( $response ) ) {
		return array( 'code' => 0, 'body' => $response->get_error_message(), 'headers' => array() );
	}

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $response ),
		'body'    => (string) wp_remote_retrieve_body( $response ),
		'headers' => wp_remote_retrieve_headers( $response )->getAll(),
	);
}

/** Yanittaki Set-Cookie basliklarindan bir cerez satiri. */
function set_cookie_header( array $response, string $name ): string {
	foreach ( (array) ( $response['headers']['set-cookie'] ?? array() ) as $line ) {
		if ( str_starts_with( (string) $line, $name . '=' ) ) {
			return (string) $line;
		}
	}

	return '';
}

/**
 * Sayfalari ayri bir PHP sureciyle, tam istek olarak ve E_ALL hata
 * yakalayicisiyla cizer. Temada ve eklentide cikan uyarilari dondurur.
 *
 * @param array<int, array{0:string, 1:string}> $requests [ yol, teklif cerezi ]
 * @return string[]
 */
function strict_notices( array $requests ): array {
	$runner = sys_get_temp_dir() . '/nwcs-strict-basket.php';
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
if ( '' !== ( $argv[2] ?? '' ) ) {
	$_COOKIE['kc_teklif'] = $argv[2];
}
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

	foreach ( $requests as $request ) {
		$output = (string) shell_exec( 'php ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( $request[0] ) . ' ' . escapeshellarg( $request[1] ) . ' 2>&1' );

		foreach ( preg_split( '/\R/', $output ) ?: array() as $line ) {
			if ( str_starts_with( $line, 'NOTICE ' ) || str_contains( $line, 'Fatal error' ) ) {
				$found[] = $request[0] . ': ' . $line;
			}
		}
	}

	unlink( $runner );

	return $found;
}

/**
 * WOOD KOCIST baglaminda (ayri surec): her terim icin wk_filter_products, eski
 * eslestirme (mb_strtolower + str_contains) ve ortak sirali arama
 * (nwcs_search_products) kimlikleri; aramasiz kartlar.
 */
function wk_search_run( array $terms ): array {
	$runner = sys_get_temp_dir() . '/nwcs-wk-search.php';
	$code   = <<<'PHP'
<?php
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/woodkocist/';
error_reporting( E_ALL );
require '/var/www/html/wp-load.php';
$ids = static fn( array $list ): array => array_map( static fn( array $p ): int => (int) $p['id'], array_values( $list ) );
$old = static function ( string $search ) use ( $ids ): array {
	$needle = mb_strtolower( trim( $search ) );

	return $ids( array_filter( wk_products(), static fn( array $p ): bool => str_contains( mb_strtolower( $p['code'] . ' ' . $p['title'] . ' ' . implode( ' ', $p['categories'] ) ), $needle ) ) );
};
$out = array( '__cards' => $ids( wk_filter_products( '', '', '' ) ), '__all' => $ids( wk_products() ) );
foreach ( json_decode( $argv[1], true ) as $term ) {
	$out[ $term ] = array( 'new' => $ids( wk_filter_products( '', $term, '' ) ), 'old' => $old( $term ), 'ranked' => $ids( nwcs_search_products( wk_products(), $term ) ) );
}
echo json_encode( $out );
PHP;

	file_put_contents( $runner, $code );
	$output = (string) shell_exec( 'php ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( (string) wp_json_encode( $terms ) ) . ' 2>/dev/null' );
	unlink( $runner );

	return (array) json_decode( $output, true );
}

/** Mailpit: aramaya uyan iletiler. */
function mailpit_search( string $query ): array {
	$response = wp_remote_get( 'http://mailpit:8025/api/v1/search?query=' . rawurlencode( $query ), array( 'timeout' => 10 ) );

	return is_wp_error( $response ) ? array() : (array) ( json_decode( (string) wp_remote_retrieve_body( $response ), true )['messages'] ?? array() );
}

function mailpit_text( string $id ): string {
	$response = wp_remote_get( 'http://mailpit:8025/api/v1/message/' . rawurlencode( $id ), array( 'timeout' => 10 ) );

	return is_wp_error( $response ) ? '' : (string) ( json_decode( (string) wp_remote_retrieve_body( $response ), true )['Text'] ?? '' );
}

/** Kartlardaki urun adlari, sayfadaki sirayla. */
function card_titles( string $html ): array {
	preg_match_all( '#<span class="k-pcard__title"[^>]*>([^<]*)</span>#', $html, $match );

	return array_map( static fn( string $text ): string => html_entity_decode( trim( $text ), ENT_QUOTES, 'UTF-8' ), $match[1] );
}

/** Sayfadaki robots meta etiketinin icerigi. */
function robots_of( string $html ): string {
	return preg_match( "#<meta name=['\"]robots['\"] content=['\"]([^'\"]*)['\"]#", $html, $match ) ? $match[1] : '';
}

/* ---------- Deneme verisi ---------- */

// Sayfalar (arama, teklif sepeti) yoksa tema acar; deneme sonunda yalnizca bu betigin actiklari silinir.
$pages_version = get_option( 'kocist_pages_version', null );
$pages_before  = array();

foreach ( array( 'arama', 'teklif-sepeti', 'kvkk' ) as $slug ) {
	$page                  = get_page_by_path( $slug, OBJECT, 'page' );
	$pages_before[ $slug ] = $page ? (int) $page->ID : 0;
}

kocist_ensure_pages();

switch_to_blog( $pool );

$terms = array();
foreach ( array( 'zz-teklif-deneme' => 'ZZ Teklif Deneme', 'zz-teklif-dekor' => 'ZZ Teklif Dekor' ) as $term_slug => $term_name ) {
	$term                = wp_insert_term( $term_name, NWCS_PRODUCT_TAX, array( 'slug' => $term_slug ) );
	$terms[ $term_slug ] = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
}

$ids  = array();
$rows = array(
	'a' => array( 'QZ Deneme Adirondack Sandalye Tik', 'QZT-ADR-K-TK', '', 'zz-teklif-deneme' ),
	'b' => array( 'QZ Deneme Adirondack Sandalye Füme', 'QZT-ADR-FB01', '1.250 TL', 'zz-teklif-deneme' ),
	'c' => array( 'QZ Deneme Şezlong', 'ADRK-QZT', '', 'zz-teklif-deneme' ),
	'd' => array( 'QZ Deneme Çardak ADR K Uyumlu', 'QZT-KML-01', '', 'zz-teklif-deneme' ),
	'e' => array( 'QZ Deneme Çiçekli İğne Yastık', 'QZT-IGNE', '', 'zz-teklif-deneme' ),
	'f' => array( 'QZ Deneme Tam Kod', 'ADRK', '', 'zz-teklif-deneme' ),
	'g' => array( 'QZ Deneme Renkli Masa Beyaz', 'QZT-RNK-B', '900 TL', 'zz-teklif-deneme' ),
	'h' => array( 'QZ Deneme Renkli Masa Siyah', 'QZT-RNK-S', '900 TL', 'zz-teklif-deneme' ),
	'i' => array( 'QZ Deneme Dekor Saksı', 'QZT-DKR-01', '', 'zz-teklif-dekor' ),
);

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

	wp_set_object_terms( $ids[ $key ], array( $terms[ $row[3] ] ), NWCS_PRODUCT_TAX );
}

restore_current_blog();
nwcs_pool_flush_cache();

$k_key    = nwcs_catalog_site_key( K_BLOG );
$w_key    = nwcs_catalog_site_key( W_BLOG );
$w_parent = (string) array_key_first( nwcs_site_parents( W_BLOG ) );

nwcs_set_placement( 'zz-teklif-deneme', $k_key, 'kereste' );
nwcs_set_placement( 'zz-teklif-dekor', $k_key, 'dekorasyon' );
nwcs_set_placement( 'zz-teklif-deneme', $w_key, $w_parent );

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	update_blog_option( $blog, NWCS_OPTION_MODE, 'selected' );
	nwcs_selection_append( $blog, array_values( $ids ) );
}

nwcs_color_groups_write(
	'zz-teklif-deneme',
	array(
		array(
			'id'      => 'zzteklif1',
			'label'   => 'Renk',
			'members' => array(
				array( 'product_id' => $ids['g'], 'color_name' => 'Beyaz', 'color_hex' => '#ffffff' ),
				array( 'product_id' => $ids['h'], 'color_name' => 'Siyah', 'color_hex' => '#1f1f1f' ),
			),
		),
	)
);

nwcs_pool_flush_cache();

$by_id = kocist_basket_products();
$p     = array();

foreach ( $ids as $key => $id ) {
	$p[ $key ] = $by_id[ $id ] ?? array();
}

[ 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e, 'f' => $f, 'g' => $g, 'h' => $h, 'i' => $i ] = $ids;

$only_test = static fn( array $list ): array => array_values( array_filter( array_map( static fn( array $item ): int => (int) $item['id'], $list ), static fn( int $id ): bool => in_array( $id, $ids, true ) ) );
$home      = home_url( '/' );
$basket    = kocist_basket_url();

try {
	check( 'deneme verisi: urunler Kocist\'te, gruplari dogru', 'kereste' === ( $p['a']['group'] ?? '' ) && 'dekorasyon' === ( $p['i']['group'] ?? '' ) && 'zz-teklif-deneme' === ( $p['a']['sub'] ?? '' ), wp_json_encode( array( $p['a']['group'] ?? null, $p['i']['group'] ?? null ) ) );
	check( 'deneme verisi: sayfalar acik (arama, teklif-sepeti), surum 7', kocist_page_ready( 'arama' ) && kocist_page_ready( 'teklif-sepeti' ) && '7' === get_option( 'kocist_pages_version' ) );

	/* ---------- 1) Arama yardimcilari (eklenti) ---------- */

	check( 'katlama: ÇİVİ = çivi = civi', 'civi' === nwcs_search_fold( 'ÇİVİ' ) && 'civi' === nwcs_search_fold( 'çivi' ) );
	check( 'katlama: I/ı/İ, ş, ğ, ü, ö, ç', 'igdir sisli ucgen olcu' === nwcs_search_fold( 'IĞDIR ŞİŞLİ ÜÇGEN ÖLÇÜ' ) && 'kapi' === nwcs_search_fold( 'KAPı' ) );
	check( 'sadelestirme: bosluk, tire, nokta yok', 'wkcadrktk' === nwcs_search_compact( 'WKC-ADR K.TK' ) && 'adrk' === nwcs_search_compact( ' adr - K ' ) );
	check( 'kelimeler: bos kelime yok, tire kelime icinde', array( 'adrk', 'tik' ) === nwcs_search_words( '  ADR-K   tik ' ) && array() === nwcs_search_words( ' - ' ) );

	$item = array( 'code' => 'WKC-ADR-K-TK', 'title' => 'Adirondack Sandalye Tik', 'categories' => array( 'x' => 'Bahçe Mobilyası' ) );
	check( 'derece 0: kodu tam tutan ("adrk" degil, "wkc adr k tk")', 0 === nwcs_search_rank( $item, 'wkc adr k tk' ) && 0 === nwcs_search_rank( $item, 'WKCADRKTK' ) );
	check( 'derece 1: kodu terimle baslayan', 1 === nwcs_search_rank( $item, 'WKC-ADR' ) );
	check( 'derece 2: kodunda gecen ("ADR K" ve "adrk")', 2 === nwcs_search_rank( $item, 'ADR K' ) && 2 === nwcs_search_rank( $item, 'adrk' ) );
	check( 'derece 3: adinda ya da kategorisinde gecen', 3 === nwcs_search_rank( $item, 'adirondack sandalye' ) && 3 === nwcs_search_rank( $item, 'BAHCE MOBILYASI' ) );
	check( 'derece 3: ek metin (sitedeki kategori adi)', -1 === nwcs_search_rank( $item, 'kamelya' ) && 3 === nwcs_search_rank( $item, 'kamelya', array( 'Kamelyalar' ) ) );
	check( 'derece 4: kelimeler sirasiz, ayri alanlarda', 4 === nwcs_search_rank( $item, 'tik wkc' ) && 4 === nwcs_search_rank( $item, 'sandalye bahce' ) );
	check( 'eslesmez: -1; bir kelime iki alanin birlesiminden olusmaz', -1 === nwcs_search_rank( $item, 'kanepe' ) && -1 === nwcs_search_rank( $item, 'tikbahce' ) );
	check( 'bos terim: bos liste', array() === nwcs_search_products( array( $item ), ' - ' ) );

	$list  = array(
		array( 'id' => 1, 'code' => 'X-1', 'title' => 'Masa ADR', 'categories' => array() ),
		array( 'id' => 2, 'code' => 'Q-ADR', 'title' => 'Sandalye', 'categories' => array() ),
		array( 'id' => 3, 'code' => 'ADR', 'title' => 'Tam', 'categories' => array() ),
		array( 'id' => 4, 'code' => 'ADR-2', 'title' => 'Baslayan', 'categories' => array() ),
		array( 'id' => 5, 'code' => 'Z-ADR-9', 'title' => 'Icinde', 'categories' => array() ),
		array( 'id' => 6, 'code' => 'ADR-3', 'title' => 'Baslayan iki', 'categories' => array() ),
	);
	$order = array_map( static fn( array $row ): int => $row['id'], nwcs_search_products( $list, 'adr' ) );
	check( 'siralama: tam kod, baslayanlar (liste sirasi), icinde gecenler, adda gecen', array( 3, 4, 6, 2, 5, 1 ) === $order, implode( ',', $order ) );

	/* Sitedeki urunlerle (kocist_search). */
	check( 'Kocist "adrk": ADRK, ADRK-QZT, QZT-ADR-K-TK, adinda "ADR K"', array( $f, $c, $a, $d ) === $only_test( kocist_search( 'adrk' ) ), implode( ',', $only_test( kocist_search( 'adrk' ) ) ) );
	check( 'Kocist "ADR K": ayni sira, kelimeleri gecen en sonda', array( $f, $c, $a, $d, $b ) === $only_test( kocist_search( 'ADR K' ) ), implode( ',', $only_test( kocist_search( 'ADR K' ) ) ) );
	check( 'Kocist "qzt adr k tk": tam kod en ustte', $a === (int) ( kocist_search( 'qzt adr k tk' )[0]['id'] ?? 0 ) );
	check( 'Kocist Turkce: "cicekli igne", "ÇİÇEKLİ İĞNE", "İğne" ayni urunu bulur', array( $e ) === $only_test( kocist_search( 'cicekli igne' ) ) && array( $e ) === $only_test( kocist_search( 'ÇİÇEKLİ İĞNE' ) ) && array( $e ) === $only_test( kocist_search( 'İğne' ) ) );
	check( 'Kocist kategori adi: "zz teklif dekor" dekor urununu bulur', array( $i ) === $only_test( kocist_search( 'zz teklif dekor' ) ) );
	check( 'Kocist renk grubu: arama sadelestirmez, iki renk ayri', array( $g, $h ) === $only_test( kocist_search( 'QZT-RNK' ) ) );
	$cards = array_column( kocist_catalog_products_in( 'kereste', 'zz-teklif-deneme' ), 'id' );
	check( 'Kocist renk grubu: kategori sayfasinda tek kart (degismedi)', in_array( $g, $cards, true ) && ! in_array( $h, $cards, true ) );
	check( 'kisa terim: 1 harf aranmaz, 2 harf aranir', ! kocist_search_long_enough( 'a' ) && ! kocist_search_long_enough( 'a-' ) && kocist_search_long_enough( 'ab' ) && array() === kocist_search( 'a' ) );

	/* ---------- 2) Cerez ---------- */

	set_cookie_value( '12:3|abc|14:0|15:99999|16:2|-1:4' );
	check( 'cerez okuma: bicime uymayan, 0 ve 5 haneli adet atilir', array( 12 => 3, 16 => 2 ) === kocist_basket_raw(), wp_json_encode( kocist_basket_raw() ) );
	set_cookie_value( implode( '|', array_map( static fn( int $n ): string => ( 900000 + $n ) . ':1', range( 1, 35 ) ) ) );
	check( 'cerez okuma: en fazla 30 satir', KOCIST_BASKET_MAX === count( kocist_basket_raw() ) );
	set_cookie_value( '12:3|12:5' );
	check( 'cerez okuma: ayni urun iki kez: son adet', array( 12 => 5 ) === kocist_basket_raw() );
	kocist_basket_save( array( 12 => 3, 0 => 4, 16 => 0, 17 => 20000 ) );
	check( 'cerez yazma: "kimlik:adet|..." (WOOD KOCIST bicimi), adet en fazla 9999', '12:3|17:9999' === cookie(), cookie() );
	kocist_basket_save( array() );
	check( 'cerez yazma: bos sepet bos deger', '' === cookie() && array() === kocist_basket_raw() );
	check( 'cerez yolu: sitenin adresi', '/kocist/' === kocist_basket_cookie_path() );

	/* ---------- 3) JavaScript'siz isleyiciler ---------- */

	wp_set_current_user( 0 );

	$add = static fn( int $id, int $qty, string $return ) => post( 'kocist_handle_basket', array( 'action' => 'kc_basket', 'kc_do' => 'add', 'kc_id' => $id, 'kc_qty' => $qty, 'kc_return' => $return ) );

	$to = $add( $a, 3, $p['a']['url'] . '?teklif=dolu#x' );
	check( 'ekle: urun sayfasina ?teklif=eklendi#teklif-sepeti-ekle ile doner', $p['a']['url'] . '?teklif=eklendi#teklif-sepeti-ekle' === $to, $to );
	check( 'ekle: cerez a:3', $a . ':3' === cookie(), cookie() );
	$add( $a, 2, $p['a']['url'] );
	check( 'ekle: ayni urun adet toplanir (a:5)', $a . ':5' === cookie(), cookie() );
	$to = $add( $b, 0, $p['b']['url'] );
	check( 'ekle: adet 0 gelirse 1 sayilir; sira korunur', $a . ':5|' . $b . ':1' === cookie(), cookie() );
	$to = $add( 999999999, 1, $p['a']['url'] );
	check( 'ekle: sitede olmayan urun eklenmez, sayfaya doner', $p['a']['url'] === $to && $a . ':5|' . $b . ':1' === cookie(), $to . ' ' . cookie() );
	$to = $add( $c, 1, 'http://kotu.example/x/' );
	check( 'ekle: baska adrese donulmez (sepete doner)', $basket . '?teklif=eklendi#teklif-sepeti-ekle' === $to, $to );
	$to = $add( $c, 1, home_url( '/../woodkocist/sepet/' ) );
	check( 'ekle: ayni alan adindaki baska siteye de donulmez (/kocist/../woodkocist/)', $basket . '?teklif=eklendi#teklif-sepeti-ekle' === $to, $to );
	$encoded = array( $add( $c, 1, home_url( '/%2e%2e/woodkocist/sepet/' ) ), $add( $c, 1, home_url( '/%2E%2e/woodkocist/' ) ), $add( $c, 1, home_url( '/%252e%252e/woodkocist/' ) ) );
	check( 'ekle: kodlanmis ".." (%2e%2e, ic ice %252e) de reddedilir', array_fill( 0, 3, $basket . '?teklif=eklendi#teklif-sepeti-ekle' ) === $encoded, implode( ' ', $encoded ) );

	set_cookie_value( implode( '|', array_map( static fn( int $n ): string => ( 900000 + $n ) . ':1', range( 1, 30 ) ) ) );
	$full = cookie();
	$to   = $add( $d, 1, $p['d']['url'] );
	check( 'ekle: sepet doluysa (30) ?teklif=dolu, cerez degismez', $p['d']['url'] . '?teklif=dolu#teklif-sepeti-ekle' === $to && $full === cookie(), $to );
	$add( 900001, 1, $p['d']['url'] );
	check( 'ekle: sitede olmayan kimlik dolu sepette de eklenmez', $full === cookie() );

	$update = static fn( array $data ) => post( 'kocist_handle_basket', $data + array( 'action' => 'kc_basket', 'kc_do' => 'update' ) );

	set_cookie_value( $a . ':5|' . $b . ':2' );
	$to = $update( array( 'kc_qty' => array( $a => '7', $b => '2' ) ) );
	check( 'guncelle: yazilan adet kaydedilir, sepete #sepet ile doner', $a . ':7|' . $b . ':2' === cookie() && $basket . '#sepet' === $to, cookie() . ' ' . $to );
	$update( array( 'kc_qty' => array( $a => '7', $b => '2' ), 'kc_step' => $b . ':+1' ) );
	check( 'guncelle: + dugmesi (b:3)', $a . ':7|' . $b . ':3' === cookie(), cookie() );
	$update( array( 'kc_qty' => array( $a => '7', $b => '3' ), 'kc_step' => $a . ':-1' ) );
	check( 'guncelle: − dugmesi (a:6)', $a . ':6|' . $b . ':3' === cookie(), cookie() );
	set_cookie_value( $a . ':1' );
	$update( array( 'kc_step' => $a . ':-1' ) );
	check( 'guncelle: − adedi 1\'in altina indirmez', $a . ':1' === cookie(), cookie() );
	set_cookie_value( $a . ':4|' . $b . ':2|' . $c . ':1' );
	$update( array( 'kc_qty' => array( $a => '0', $b => 'abc', $c => '' ) ) );
	check( 'guncelle: 0 satiri siler, gecersiz deger 0 sayilir, bos alan dokunulmaz', $c . ':1' === cookie(), cookie() );
	set_cookie_value( $a . ':4|' . $b . ':2' );
	$update( array( 'kc_qty' => array( $a => '4', $b => '2' ), 'kc_remove' => (string) $b ) );
	check( 'guncelle: "Çıkar" satiri siler', $a . ':4' === cookie(), cookie() );
	$update( array( 'kc_qty' => array( $a => '20000', 999 => '5' ) ) );
	check( 'guncelle: adet en fazla 9999, sepette olmayan kimlik eklenmez', $a . ':9999' === cookie(), cookie() );
	$to = post( 'kocist_handle_basket', array( 'action' => 'kc_basket', 'kc_do' => 'clear' ) );
	check( 'temizle: cerez bos, sepete doner', '' === cookie() && $basket === $to, $to );

	/* ---------- 4) WhatsApp ---------- */

	$line  = static fn( string $key, int $qty ): array => array( 'product' => $p[ $key ], 'qty' => $qty );
	$k_num = kocist_group_wa_digits( 'kereste' );
	$d_num = kocist_group_wa_digits( 'dekorasyon' );
	$g_num = kocist_general_wa_digits();

	check( 'numara: panel numaralari okunuyor (kereste, genel farkli)', '' !== $k_num && '' !== $g_num && $k_num !== $g_num, $k_num . ' / ' . $g_num );
	check( 'numara: hepsi Kereste: Kereste numarasi', $k_num === kocist_basket_wa_digits( array( $line( 'a', 1 ), $line( 'b', 2 ) ) ) );
	check( 'numara: hepsi Dekorasyon: Dekorasyon numarasi', $d_num === kocist_basket_wa_digits( array( $line( 'i', 1 ) ) ) );
	check( 'numara: karisik gruplar: genel numara', $g_num === kocist_basket_wa_digits( array( $line( 'a', 1 ), $line( 'i', 1 ) ) ) );
	check( 'numara: grubu olmayan urun: genel numara', $g_num === kocist_basket_wa_digits( array( array( 'product' => array( 'group' => '' ) + $p['a'], 'qty' => 1 ) ) ) );
	check( 'numara: genel = urun sayfasi WhatsApp butonu (wa.me/...)', $g_num === kocist_wa_url_digits( (string) nwcs_field( 'product', 'main', 'secondary_url' ) ) && '905321112233' === kocist_wa_url_digits( 'https://api.whatsapp.com/send?phone=905321112233&text=x' ) );

	$message = kocist_basket_wa_message( array( $line( 'a', 3 ), $line( 'b', 1 ) ) );
	$intro   = (string) nwcs_field( 'teklif', 'send', 'wa_intro' );
	check( 'mesaj: giris satiri, her urun bir satir ve adresi', str_starts_with( $message, $intro . "\n\n1. QZT-ADR-K-TK – QZ Deneme Adirondack Sandalye Tik – 3 adet\n" . $p['a']['url'] . "\n2. QZT-ADR-FB01 – QZ Deneme Adirondack Sandalye Füme – 1 adet\n" . $p['b']['url'] ), $message );
	check( 'satir: kodu olmayan urunde bas ayirac yok', 'Kodsuz Ürün – 2 adet' === kocist_basket_line( array( 'code' => '', 'title' => 'Kodsuz Ürün' ), 2 ), kocist_basket_line( array( 'code' => '', 'title' => 'Kodsuz Ürün' ), 2 ) );

	$url = kocist_basket_wa_url( array( $line( 'a', 3 ), $line( 'b', 1 ) ) );
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $wa_args );
	check( 'adres: https://wa.me/<kereste>?text=<mesaj>', str_starts_with( $url, 'https://wa.me/' . $k_num . '?text=' ) && $message === ( $wa_args['text'] ?? '' ), $url );
	check( 'adres: bos sepet bos', '' === kocist_basket_wa_url( array() ) );

	$long = array();
	foreach ( range( 1, 30 ) as $n ) {
		$long[] = array(
			'product' => array( 'code' => 'UZUN-KOD-' . $n, 'title' => str_repeat( 'Uzun Ürün Adı ', 6 ) . $n, 'group' => 'kereste', 'slug' => 'uzun-' . $n, 'url' => home_url( '/urun/cok-uzun-bir-urun-adresi-' . $n . '/' ), 'body' => 'x' ),
			'qty'     => $n,
		);
	}
	$long_url = kocist_basket_wa_url( $long );
	parse_str( (string) wp_parse_url( $long_url, PHP_URL_QUERY ), $long_args );
	$long_text = (string) ( $long_args['text'] ?? '' );
	check( 'uzun liste: adres en fazla ' . KOCIST_BASKET_WA_MAX . ' karakter', strlen( $long_url ) <= KOCIST_BASKET_WA_MAX && strlen( $long_url ) > 1000, (string) strlen( $long_url ) );
	check( 'uzun liste: once sayfa adresleri atilir, sonra "+N ürün daha"', ! str_contains( $long_text, '/urun/' ) && 1 === preg_match( '/\n\n\+(\d+) ürün daha$/u', $long_text, $more ) && str_contains( $long_text, "\n1. UZUN-KOD-1 – " ) );
	$listed = substr_count( $long_text, 'UZUN-KOD-' );
	check( 'uzun liste: yazilan + kalan = 30', 30 === $listed + (int) ( $more[1] ?? 0 ), $listed . ' + ' . ( $more[1] ?? '?' ) );
	// Orta uzunlukta liste: adresleriyle sigmiyor, adressiz sigiyor.
	$mid_count = 0;
	foreach ( range( 1, 30 ) as $n ) {
		$slice = array_slice( $long, 0, $n );

		if ( strlen( kocist_wa_url( $k_num, kocist_basket_wa_message( $slice, true ) ) ) > KOCIST_BASKET_WA_MAX && strlen( kocist_wa_url( $k_num, kocist_basket_wa_message( $slice, false ) ) ) <= KOCIST_BASKET_WA_MAX ) {
			$mid_count = $n;
		}
	}
	$mid_url = $mid_count ? kocist_basket_wa_url( array_slice( $long, 0, $mid_count ) ) : '';
	parse_str( (string) wp_parse_url( $mid_url, PHP_URL_QUERY ), $mid_args );
	$mid_text = (string) ( $mid_args['text'] ?? '' );
	check( 'orta liste (' . $mid_count . ' urun): adresler sigmiyorsa yalnizca adresler atilir, liste tam', $mid_count > 0 && ! str_contains( $mid_text, '/urun/' ) && $mid_count === substr_count( $mid_text, 'UZUN-KOD-' ) && ! str_contains( $mid_text, 'ürün daha' ) );

	/* ---------- 5) E-posta ile gonderim ---------- */

	$mails = array();
	add_filter(
		'wp_mail',
		static function ( array $args ) use ( &$mails ): array {
			$mails[] = $args;

			return $args;
		}
	);

	$quote_ids = static fn(): array => get_posts( array( 'post_type' => 'kc_quote', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'post__not_in' => $quotes_before ?: array( 0 ) ) );
	$send      = static fn( array $data ): string => post( 'kocist_handle_basket_send', $data + array( 'action' => 'kc_basket_send', 'kc_basket_nonce' => wp_create_nonce( 'kc_basket_send' ) ) );
	$person    = array( 'kc-name' => 'Deneme Kişi', 'kc-phone' => '0532 000 00 00', 'kc-email' => 'deneme@example.com', 'kc-company' => 'Deneme A.Ş.', 'kc-note' => "Teslim Çatalca.\nAcil değil.", 'kc-consent' => '1' );

	set_cookie_value( $a . ':3|' . $b . ':1' );

	$to = $send( $person + array( 'kc_website' => 'http://spam.example' ) );
	check( 'bot tuzagi: sessizce sepete doner, kayit yok, sepet duruyor', $basket === $to && ! $quote_ids() && $a . ':3|' . $b . ':1' === cookie(), $to );

	$to    = $send( array( 'kc_basket_nonce' => 'yanlis' ) + $person );
	$state = state_of( $to );
	check( 'nonce yanlis: hata, kayit yok', str_contains( $to, '#teklif-gonder' ) && str_contains( (string) ( $state['errors']['form'] ?? '' ), 'zaman aşımı' ) && ! $quote_ids(), $to );

	$no_consent = $person;
	unset( $no_consent['kc-consent'] );
	$to    = $send( $no_consent );
	$state = state_of( $to );
	check( 'onay kutusu zorunlu: hata, kayit yok, e-posta yok', isset( $state['errors']['consent'] ) && ! $quote_ids() && ! $mails, wp_json_encode( $state, JSON_UNESCAPED_UNICODE ) );
	check( 'hata: yazilanlar geri gelir (ad, firma, not), sepet duruyor', 'Deneme Kişi' === ( $state['values']['name'] ?? '' ) && 'Deneme A.Ş.' === ( $state['values']['company'] ?? '' ) && "Teslim Çatalca.\nAcil değil." === ( $state['values']['message'] ?? '' ) && $a . ':3|' . $b . ':1' === cookie() );

	$to    = $send( array( 'kc-name' => '', 'kc-phone' => '', 'kc-email' => '', 'kc-consent' => '1' ) );
	$state = state_of( $to );
	check( 'eksik alan: ad ve "telefon ya da e-posta" istenir', isset( $state['errors']['name'], $state['errors']['phone'] ) && ! $quote_ids() );
	$to    = $send( array( 'kc-email' => 'gecersiz' ) + $person );
	$state = state_of( $to );
	check( 'gecersiz e-posta: hata; ham deger geri gelir', isset( $state['errors']['email'] ) && 'gecersiz' === ( $state['values']['email'] ?? '' ) );
	$to    = $send( array( 'kc-email' => '' ) + $person );
	check( 'telefon yeterli (e-posta bos): gecer', ! isset( state_of( $to )['errors'] ) && 1 === count( $quote_ids() ), $to );

	// Hiz siniri: ayni adresten 30 saniyede bir, saatte 10 talep.
	set_cookie_value( $a . ':3|' . $b . ':1' );
	$to    = $send( $person );
	$state = state_of( $to );
	check( 'hiz siniri: 30 saniye dolmadan ikinci talep: durust hata, kayit yok', kocist_form_rate_message() === ( $state['errors']['form'] ?? '' ) && 1 === count( $quote_ids() ), wp_json_encode( $state, JSON_UNESCAPED_UNICODE ) );
	check( 'hiz siniri: yazilanlar ve sepet korunur', 'Deneme Kişi' === ( $state['values']['name'] ?? '' ) && "Teslim Çatalca.\nAcil değil." === ( $state['values']['message'] ?? '' ) && $a . ':3|' . $b . ':1' === cookie() );
	$no_consent_again = $person;
	unset( $no_consent_again['kc-consent'] );
	check( 'hiz siniri: hatali talep sayilmaz, once alan hatasi gorunur', isset( state_of( $send( $no_consent_again ) )['errors']['consent'] ) && 1 === count( kocist_form_rate_times() ) );
	set_transient( kocist_form_rate_key(), array_map( static fn( int $n ): int => time() - 120 - $n * 60, range( 0, 9 ) ), HOUR_IN_SECONDS );
	check( 'hiz siniri: saatte 10 talep dolduysa (sonuncusu 2 dk once) yine sinirda', kocist_form_rate_limited() );
	set_transient( kocist_form_rate_key(), array_merge( array_map( static fn( int $n ): int => time() - 120 - $n * 60, range( 0, 8 ) ), array( time() - 2 * HOUR_IN_SECONDS ) ), HOUR_IN_SECONDS );
	check( 'hiz siniri: 9 talep + bir saatten eski talep: sinirda degil', ! kocist_form_rate_limited() && 9 === count( kocist_form_rate_times() ) );
	check( 'hiz siniri: adres tuzlu ozetle tutulur (acik IP yok)', 1 === preg_match( '/^kc_rate_[a-f0-9]{24}$/', kocist_form_rate_key() ) );
	delete_transient( kocist_form_rate_key() );

	foreach ( $quote_ids() as $quote ) {
		wp_delete_post( $quote, true );
	}
	$mails = array();
	set_cookie_value( $a . ':3|' . $b . ':1' );

	set_cookie_value( '' );
	$to    = $send( $person );
	$state = state_of( $to );
	check( 'bos sepet: hata metni, kayit yok', (string) nwcs_field( 'teklif', 'send', 'empty_error' ) === ( $state['errors']['form'] ?? '' ) && ! $quote_ids() );

	// E-posta gidemedi: kayit kalir ama ziyaretciye basari denmez; sepet ve yazilanlar korunur.
	set_cookie_value( $a . ':3|' . $b . ':1' );
	$deny = static fn() => false;
	add_filter( 'pre_wp_mail', $deny );
	$to    = $send( $person );
	$state = state_of( $to );
	remove_filter( 'pre_wp_mail', $deny );
	$made  = $quote_ids();
	check( 'e-posta gidemedi: durust hata (basari yok)', (string) nwcs_field( 'teklif', 'send', 'mail_error' ) === ( $state['errors']['form'] ?? '' ) && empty( $state['success'] ), wp_json_encode( $state, JSON_UNESCAPED_UNICODE ) );
	check( 'e-posta gidemedi: kayit duruyor (bildirim "failed"), sepet ve yazilanlar korunur', 1 === count( $made ) && 'failed' === get_post_meta( $made[0], NWCS_FORM_NOTIFIED_META, true ) && $a . ':3|' . $b . ':1' === cookie() && 'Deneme Kişi' === ( $state['values']['name'] ?? '' ) );
	foreach ( $made as $quote ) {
		wp_delete_post( $quote, true );
	}

	// Basari.
	delete_transient( kocist_form_rate_key() );
	$mails = array();
	$to    = $send( $person );
	$state = state_of( $to );
	$made  = $quote_ids();
	$post  = $made ? get_post( $made[0] ) : null;
	check( 'gonderim: basari durumu, #teklif-gonder', ! empty( $state['success'] ) && str_starts_with( $to, $basket . '?kc=' ) && str_ends_with( $to, '#teklif-gonder' ), $to );
	check( 'gonderim: "Teklif Talepleri" kaydi (ozel), baslikta ad ve urun sayisi', $post && 'kc_quote' === $post->post_type && 'private' === $post->post_status && 'Deneme Kişi — Teklif sepeti (2 ürün)' === $post->post_title, $post ? $post->post_title : 'yok' );
	$items = $post ? (string) get_post_meta( $post->ID, '_kc_items', true ) : '';
	check( 'gonderim: kayitta urun listesi (kod, ad, adet, adres)', "1. QZT-ADR-K-TK – QZ Deneme Adirondack Sandalye Tik – 3 adet\n   " . $p['a']['url'] . "\n2. QZT-ADR-FB01 – QZ Deneme Adirondack Sandalye Füme – 1 adet\n   " . $p['b']['url'] === $items && $post && str_contains( $post->post_content, 'QZT-ADR-FB01 – QZ Deneme Adirondack Sandalye Füme – 1 adet' ), $items );
	check( 'gonderim: kayitta firma, telefon, e-posta, not', $post && 'Deneme A.Ş.' === get_post_meta( $post->ID, '_kc_company', true ) && '0532 000 00 00' === get_post_meta( $post->ID, '_kc_phone', true ) && "Teslim Çatalca.\nAcil değil." === get_post_meta( $post->ID, '_kc_message', true ) );
	check( 'gonderim: bildirim gitti ("sent"), alici info@kocist.com.tr', $post && 'sent' === get_post_meta( $post->ID, NWCS_FORM_NOTIFIED_META, true ) && 1 === count( $mails ) && array( 'info@kocist.com.tr' ) === (array) $mails[0]['to'], wp_json_encode( array_map( static fn( array $mail ) => $mail['to'], $mails ) ) );
	$body = (string) ( $mails[0]['message'] ?? '' );
	check( 'e-posta: "Ürünler:" listesi, ad, firma, not; Yanitla musteriye', str_contains( $body, "Ürünler:\n1. QZT-ADR-K-TK – QZ Deneme Adirondack Sandalye Tik – 3 adet\n   " . $p['a']['url'] ) && str_contains( $body, 'Ad Soyad: Deneme Kişi' ) && str_contains( $body, 'Firma: Deneme A.Ş.' ) && str_contains( $body, "Mesaj:\nTeslim Çatalca." ) && in_array( 'Reply-To: Deneme Kişi <deneme@example.com>', (array) $mails[0]['headers'], true ), $body );
	check( 'gonderim: sepet bosaldi', '' === cookie() );
	nwcs_form_flush();
	check( 'gonderim: istegin sonundaki kuyruk ikinci e-posta gondermez', 1 === count( $mails ) );

	usleep( 500000 ); // Mailpit iletiyi dizinlesin.
	$found = mailpit_search( 'subject:"Yeni teklif isteği" QZT-ADR-K-TK' );
	$text  = $found ? mailpit_text( (string) $found[0]['ID'] ) : '';
	check( 'Mailpit: bildirim geldi (info@kocist.com.tr, urun listesiyle)', $found && 'info@kocist.com.tr' === ( $found[0]['To'][0]['Address'] ?? '' ) && str_contains( $text, 'QZT-ADR-FB01' ), (string) count( $found ) );

	// Tek urunluk teklif formu (inc/form.php) ayni siniri kullanir; sayac ortak.
	check( 'hiz siniri: sepet gonderimi saydi (hemen ardindan sinirda)', kocist_form_rate_limited() );
	delete_transient( kocist_form_rate_key() );
	$quote_form = static fn(): string => post( 'kocist_handle_quote', array( 'action' => 'kc_quote', 'kc_quote_nonce' => wp_create_nonce( 'kc_quote' ), 'kc_form' => 'product', 'kc_product' => $p['a']['slug'], 'kc_return' => $p['a']['url'], 'kc-name' => 'Deneme Kişi', 'kc-phone' => '0532 000 00 00', 'kc-qty' => '5 adet', 'kc-consent' => '1' ) );
	$before_q   = count( $quote_ids() );
	$first      = state_of( $quote_form() );
	$second     = state_of( $quote_form() );
	check( 'teklif formu: ilk talep gecer, 30 saniye dolmadan ikincisi durust hata, tek kayit', ! empty( $first['success'] ) && kocist_form_rate_message() === ( $second['errors']['form'] ?? '' ) && $before_q + 1 === count( $quote_ids() ), wp_json_encode( $second, JSON_UNESCAPED_UNICODE ) );
	check( 'teklif formu: sinirda yazilanlar geri gelir', '5 adet' === ( $second['values']['qty'] ?? '' ) && 'Deneme Kişi' === ( $second['values']['name'] ?? '' ) );
	delete_transient( kocist_form_rate_key() );

	wp_set_current_user( $admin );

	/* ---------- 6) Sayfalar (HTTP, ziyaretci) ---------- */

	update_blog_option( K_BLOG, 'blog_public', 1 );

	$search = http( '/kocist/arama/?ara=adrk' );
	$titles = card_titles( $search['body'] );
	$want   = array( $p['f']['title'], $p['c']['title'], $p['a']['title'], $p['d']['title'] );
	check( 'arama sayfasi: 200, baslik "‘adrk’ için N ürün"', 200 === $search['code'] && 1 === preg_match( '#<h1[^>]*>‘adrk’ için (\d+) ürün</h1>#u', $search['body'], $count ) && (int) $count[1] === count( $titles ), (string) $search['code'] );
	check( 'arama sayfasi: kart sirasi (tam kod, baslayan, icinde, adinda)', $want === array_values( array_intersect( $titles, $want ) ), implode( ' | ', $titles ) );
	check( 'arama sayfasi: kartta kategori adi, form terimle dolu', str_contains( $search['body'], 'k-pcard__kind' ) && str_contains( $search['body'], 'name="ara" value="adrk"' ) );
	check( 'arama sayfasi: noindex, follow', 'noindex, follow' === robots_of( $search['body'] ), robots_of( $search['body'] ) );
	$rnk = card_titles( http( '/kocist/arama/?ara=QZT-RNK' )['body'] );
	check( 'arama sayfasi: renk grubunun iki uyesi ayri kart', array( $p['g']['title'], $p['h']['title'] ) === $rnk, implode( ' | ', $rnk ) );

	$none = http( '/kocist/arama/?ara=zzyyxxq' );
	preg_match( '#href="(https://wa\.me/[^"]+)"#', $none['body'], $wa_none );
	parse_str( (string) wp_parse_url( html_entity_decode( $wa_none[1] ?? '' ), PHP_URL_QUERY ), $none_args );
	check( 'sonuc yok: baslik, metin, iletisim ve WhatsApp (terimli mesaj)', str_contains( $none['body'], '‘zzyyxxq’ için sonuç bulunamadı' ) && str_contains( $none['body'], 'href="' . home_url( '/iletisim/' ) . '"' ) && str_contains( (string) ( $none_args['text'] ?? '' ), '“zzyyxxq”' ) && ! card_titles( $none['body'] ) );
	$short = http( '/kocist/arama/?ara=a' );
	check( 'kisa terim: uyari, sonuc yok', str_contains( $short['body'], (string) nwcs_field( 'arama', 'results', 'short_term' ) ) && ! card_titles( $short['body'] ) );
	$empty = http( '/kocist/arama/' );
	check( 'aramasiz: "Ürün arayın" ve form', str_contains( $empty['body'], '>' . nwcs_field( 'arama', 'head', 'title' ) . '</h1>' ) && str_contains( $empty['body'], 'id="k-search-page"' ) );
	check( 'onbellek: terimli arama no-cache, terimsiz /arama/ onbellege girer', 'no-cache' === ( $search['headers']['x-litespeed-cache-control'] ?? '' ) && 'no-cache' === ( $none['headers']['x-litespeed-cache-control'] ?? '' ) && ! isset( $empty['headers']['x-litespeed-cache-control'] ) );
	preg_match_all( '#<form class="k-searchform[^"]*" role="search" aria-label="([^"]+)"#u', $search['body'], $regions );
	check( 'arama bolgeleri: her formun kendi adi (menude, ust menude, sonuclarda)', array( 'Menüde ürün ara', 'Ürün ara', 'Sonuçlarda yeniden ara' ) === $regions[1], implode( ' | ', $regions[1] ) );

	$sitemap = http( '/kocist/wp-sitemap-posts-page-1.xml' );
	check( 'site haritasi: arama ve teklif sepeti yok (KVKK var)', 200 === $sitemap['code'] && str_contains( $sitemap['body'], '/kvkk/' ) && ! str_contains( $sitemap['body'], '/arama/' ) && ! str_contains( $sitemap['body'], '/teklif-sepeti/' ), (string) $sitemap['code'] );
	$extra = http( '/kocist/wp-sitemap-nwcsextra-1.xml' );
	check( 'ek site haritasi: arama ve teklif sepeti yok', ! str_contains( $extra['body'], '/arama/' ) && ! str_contains( $extra['body'], '/teklif-sepeti/' ) );
	$llms = http( '/kocist/llms.txt' );
	check( 'llms.txt: arama ve teklif sepeti yok', 200 === $llms['code'] && ! str_contains( $llms['body'], '/arama/' ) && ! str_contains( $llms['body'], 'teklif-sepeti' ), (string) $llms['code'] );

	// Ust menu.
	$product_page = http( wp_parse_url( $p['a']['url'], PHP_URL_PATH ) );
	check( 'ust menu: buyutec arama sayfasina giden baglanti, cekmecede arama kutusu', str_contains( $product_page['body'], 'class="k-tool" href="' . kocist_search_url() . '" data-k-search-toggle' ) && str_contains( $product_page['body'], 'id="k-nav-search"' ) && str_contains( $product_page['body'], 'id="k-search" data-k-search hidden' ) );
	check( 'ust menu: sepet baglantisi, sayi gizli (betik yazar), cerez yolu', str_contains( $product_page['body'], 'href="' . $basket . '" data-k-basket-link data-path="/kocist/"' ) && str_contains( $product_page['body'], 'data-k-basket-count aria-hidden="true" hidden>0<' ) );

	// Urun sayfasi.
	check( 'urun sayfasi: adet + "Teklif sepetine ekle", +/− betikle acilir, canli bolge', str_contains( $product_page['body'], 'data-k-basket-add data-id="' . $a . '"' ) && str_contains( $product_page['body'], '<label class="k-badd__label" for="kc-add-qty"' ) && 2 === substr_count( $product_page['body'], 'data-k-step="' ) && str_contains( $product_page['body'], 'data-k-step="-1" hidden' ) && str_contains( $product_page['body'], 'role="status" data-k-basket-status' ) );
	check( 'urun sayfasi: tek urunluk teklif formu ve WhatsApp yerinde', str_contains( $product_page['body'], 'name="action" value="kc_quote"' ) && str_contains( $product_page['body'], 'k-qform__wa' ) );
	check( 'urun sayfasi: onbellek basligi yok (onbellege girer)', ! isset( $product_page['headers']['x-litespeed-cache-control'] ) );

	// JavaScript'siz ekleme: gercek istek, Set-Cookie ve yonlendirme.
	$res    = http( '/kocist/wp-admin/admin-post.php', array(), array( 'action' => 'kc_basket', 'kc_do' => 'add', 'kc_id' => $a, 'kc_qty' => '2', 'kc_return' => $p['a']['url'] ) );
	$set    = set_cookie_header( $res, KOCIST_BASKET_COOKIE );
	$value  = rawurldecode( (string) strtok( substr( $set, strlen( KOCIST_BASKET_COOKIE ) + 1 ), ';' ) );
	check( 'HTTP ekle: 302, urun sayfasina ?teklif=eklendi', 302 === $res['code'] && $p['a']['url'] . '?teklif=eklendi#teklif-sepeti-ekle' === ( $res['headers']['location'] ?? '' ), $res['code'] . ' ' . ( $res['headers']['location'] ?? '' ) );
	check( 'HTTP ekle: Set-Cookie kc_teklif=a:2; path=/kocist/; SameSite=Lax; onbellek yok', $a . ':2' === $value && str_contains( $set, 'path=/kocist/' ) && str_contains( strtolower( $set ), 'samesite=lax' ) && 'no-cache' === ( $res['headers']['x-litespeed-cache-control'] ?? '' ), $set );

	$back = http( wp_parse_url( $p['a']['url'], PHP_URL_PATH ) . '?teklif=eklendi', array( KOCIST_BASKET_COOKIE => $value ) );
	check( 'donus: "Teklif sepetine eklendi (sepette 2 adet)" ve "Sepete git"; onbellege alinmaz', str_contains( $back['body'], kocist_text( 'teklif', 'add', 'added', array( 'adet' => 2 ) ) ) && str_contains( $back['body'], 'class="k-badd__go" href="' . $basket . '"' ) && 'no-cache' === ( $back['headers']['x-litespeed-cache-control'] ?? '' ) );
	$nocookie = http( wp_parse_url( $p['a']['url'], PHP_URL_PATH ) . '?teklif=eklendi' );
	check( 'donus: cerez yoksa "eklendi" denmez, ne yapilacagi yazar', str_contains( $nocookie['body'], (string) nwcs_field( 'teklif', 'add', 'no_cookie' ) ) && ! str_contains( $nocookie['body'], 'class="k-badd__go"' ) );
	$full_page = http( wp_parse_url( $p['d']['url'], PHP_URL_PATH ) . '?teklif=dolu' );
	check( 'donus: dolu sepet metni', str_contains( $full_page['body'], esc_html( kocist_text( 'teklif', 'add', 'full', array( 'sayi' => KOCIST_BASKET_MAX ) ) ) ) );

	// Sepet sayfasi.
	$cookie_ab = $a . ':3|' . $b . ':1|' . $i . ':2|999999998:4';
	$page      = http( '/kocist/teklif-sepeti/', array( KOCIST_BASKET_COOKIE => $cookie_ab ) );
	check( 'sepet sayfasi: 200, onbellege alinmaz, noindex', 200 === $page['code'] && 'no-cache' === ( $page['headers']['x-litespeed-cache-control'] ?? '' ) && str_starts_with( robots_of( $page['body'] ), 'noindex' ), $page['code'] . ' ' . robots_of( $page['body'] ) );
	check( 'sepet sayfasi: 3 satir (kod, ad baglantisi), sitede olmayan urun cikti', 3 === substr_count( $page['body'], '<li class="k-bline">' ) && str_contains( $page['body'], '<p class="k-bline__code">QZT-ADR-K-TK</p>' ) && str_contains( $page['body'], '<a href="' . $p['b']['url'] . '">' ) && str_contains( $page['body'], esc_html( kocist_text( 'teklif', 'list', 'removed', array( 'sayi' => 1 ) ) ) ) );
	check( 'sepet sayfasi: sitede olmayan urun cerezden de silindi', $a . ':3|' . $b . ':1|' . $i . ':2' === rawurldecode( (string) strtok( substr( set_cookie_header( $page, KOCIST_BASKET_COOKIE ), strlen( KOCIST_BASKET_COOKIE ) + 1 ), ';' ) ) );
	check( 'sepet sayfasi: fiyat varsa fiyat, yoksa "Fiyat teklifle"; toplam yok', str_contains( $page['body'], '>1.250 TL</p>' ) && 2 === substr_count( $page['body'], 'k-bline__price is-quote' ) && ! preg_match( '/toplam/iu', wp_strip_all_tags( $page['body'] ) ) );
	check( 'sepet sayfasi: adet kutusu etiketli, +/− ve "Çıkar" adli gonder dugmeleri', str_contains( $page['body'], 'name="kc_qty[' . $a . ']" value="3"' ) && str_contains( $page['body'], 'for="kc-qty-' . $a . '"' ) && str_contains( $page['body'], 'name="kc_step" value="' . $a . ':+1"' ) && str_contains( $page['body'], 'name="kc_step" value="' . $b . ':-1" data-k-step="-1" disabled' ) && str_contains( $page['body'], 'name="kc_remove" value="' . $b . '"' ) && str_contains( $page['body'], '<span class="screen-reader-text">: ' . $p['b']['title'] . '</span>' ) );
	preg_match( '#href="(https://wa\.me/[^"]+)"[^>]*data-k-basket-wa#', $page['body'], $wa_page );
	check( 'sepet sayfasi: WhatsApp mesaji satir satir (%0A korunur)', str_contains( (string) ( $wa_page[1] ?? '' ), '%0A1.%20QZT-ADR-K-TK' ), (string) ( $wa_page[1] ?? '' ) );
	check( 'sepet sayfasi: WhatsApp (karisik grup: genel numara), sepeti temizle, e-posta formu', str_starts_with( html_entity_decode( $wa_page[1] ?? '' ), 'https://wa.me/' . $g_num . '?text=' ) && str_contains( $page['body'], 'name="kc_do" value="clear"' ) && str_contains( $page['body'], 'name="action" value="kc_basket_send"' ) && str_contains( $page['body'], 'name="kc_website"' ) && str_contains( $page['body'], 'id="kc-consent"' ) && str_contains( $page['body'], home_url( '/kvkk/' ) ) );
	$kereste_only = http( '/kocist/teklif-sepeti/', array( KOCIST_BASKET_COOKIE => $a . ':1|' . $b . ':1' ) );
	check( 'sepet sayfasi: hepsi Kereste: Kereste numarasi', str_contains( $kereste_only['body'], 'href="https://wa.me/' . $k_num . '?text=' ) );
	$empty_page = http( '/kocist/teklif-sepeti/' );
	check( 'bos sepet: baslik ve kategorilere baglanti, form yok', str_contains( $empty_page['body'], (string) nwcs_field( 'teklif', 'list', 'empty_title' ) ) && str_contains( $empty_page['body'], 'href="' . home_url( '/kategoriler/' ) . '"' ) && ! str_contains( $empty_page['body'], 'kc_basket_send' ) );

	// Gonderim donusu (sayfa).
	$tokens[] = $ok_token = bin2hex( random_bytes( 10 ) );
	set_transient( 'kc_quote_' . $ok_token, array( 'success' => true ), 600 );
	$done = http( '/kocist/teklif-sepeti/?kc=' . $ok_token, array( KOCIST_BASKET_COOKIE => $a . ':1' ) );
	check( 'donus (basari): "Talebiniz alındı", betik cerezi siler, liste yok', str_contains( $done['body'], (string) nwcs_field( 'teklif', 'send', 'success_title' ) ) && str_contains( $done['body'], 'data-k-basket-done' ) && ! str_contains( $done['body'], 'k-bline' ) );
	$tokens[] = $err_token = bin2hex( random_bytes( 10 ) );
	set_transient( 'kc_quote_' . $err_token, array( 'errors' => array( 'form' => (string) nwcs_field( 'teklif', 'send', 'mail_error' ), 'consent' => 'Onay kutusunu işaretleyin.' ), 'values' => array( 'name' => 'Deneme Kişi', 'company' => 'Deneme A.Ş.', 'message' => 'Notum' ) ), 600 );
	$err = http( '/kocist/teklif-sepeti/?kc=' . $err_token, array( KOCIST_BASKET_COOKIE => $a . ':1' ) );
	check( 'donus (hata): hata bandi, alan hatasi, yazilanlar ve sepet duruyor', str_contains( $err['body'], esc_html( (string) nwcs_field( 'teklif', 'send', 'mail_error' ) ) ) && str_contains( $err['body'], 'id="kc-consent-error"' ) && str_contains( $err['body'], 'value="Deneme Kişi"' ) && str_contains( $err['body'], '>Notum</textarea>' ) && str_contains( $err['body'], 'k-bline' ) );

	// Gercek istekle gonderim (JavaScript'siz form): nonce sayfadan, cerez tarayicidan.
	$form_page = http( '/kocist/teklif-sepeti/', array( KOCIST_BASKET_COOKIE => $a . ':2' ) );
	preg_match( '#name="kc_basket_nonce" value="([a-f0-9]+)"#', $form_page['body'], $nonce );
	$sent = http( '/kocist/wp-admin/admin-post.php', array( KOCIST_BASKET_COOKIE => $a . ':2' ), array( 'action' => 'kc_basket_send', 'kc_basket_nonce' => $nonce[1] ?? '', 'kc-name' => 'Deneme Kişi HTTP', 'kc-email' => 'http@example.com', 'kc-consent' => '1' ) );
	$clear = set_cookie_header( $sent, KOCIST_BASKET_COOKIE );
	check( 'HTTP gonderim: 302 ?kc=...#teklif-gonder, cerez silinir', 302 === $sent['code'] && str_contains( (string) ( $sent['headers']['location'] ?? '' ), '#teklif-gonder' ) && str_starts_with( $clear, KOCIST_BASKET_COOKIE . '=' ) && str_contains( strtolower( $clear ), 'max-age=0' ), $sent['code'] . ' ' . $clear );
	$http_quotes = array_values( array_filter( get_posts( array( 'post_type' => 'kc_quote', 'post_status' => 'any', 'posts_per_page' => -1, 'post__not_in' => $quotes_before ?: array( 0 ) ) ), static fn( WP_Post $quote ): bool => str_starts_with( $quote->post_title, 'Deneme Kişi HTTP — ' ) ) );
	usleep( 500000 ); // Mailpit iletiyi dizinlesin.
	check( 'HTTP gonderim: kayit olustu, bildirim gitti', 1 === count( $http_quotes ) && 'sent' === get_post_meta( $http_quotes[0]->ID, NWCS_FORM_NOTIFIED_META, true ), (string) count( $http_quotes ) );
	check( 'HTTP gonderim: Mailpit\'te bildirim', (bool) mailpit_search( 'http@example.com' ) );

	// Sayfa acilmamissa (yayinda degil) ust menu ve ekleme formu basilmaz.
	$basket_page = get_page_by_path( 'teklif-sepeti', OBJECT, 'page' );
	wp_update_post( array( 'ID' => $basket_page->ID, 'post_status' => 'draft' ) );
	$hidden = http( wp_parse_url( $p['a']['url'], PHP_URL_PATH ) );
	wp_update_post( array( 'ID' => $basket_page->ID, 'post_status' => 'publish' ) );
	check( 'sepet sayfasi yayinda degilse: ust menude sepet yok, urun sayfasinda ekleme formu yok', ! str_contains( $hidden['body'], 'data-k-basket-link' ) && ! str_contains( $hidden['body'], 'data-k-basket-add' ) && str_contains( $hidden['body'], 'data-k-search-toggle' ) );

	update_blog_option( K_BLOG, 'blog_public', $sites_before[ K_BLOG ]['blog_public'] );
	$closed = http( '/kocist/teklif-sepeti/' );
	check( 'site arama motorlarina kapaliyken: noindex, nofollow (follow eklenmez)', 'noindex, nofollow' === robots_of( $closed['body'] ) || '0' !== (string) $sites_before[ K_BLOG ]['blog_public'], robots_of( $closed['body'] ) );

	/* ---------- 7) WOOD KOCIST: arama ve sepet eskisi gibi ---------- */

	$wk_terms = array( 'QZ Deneme', 'qzt-adr', 'QZT-ADR-K-TK', 'renkli masa', 'çiçekli', 'CICEKLI', 'İĞNE', 'zzz-yok', 'a', 'sandalye', 'KAMELYA', 'masa' );
	$wk       = wk_search_run( $wk_terms );
	check( 'WK: arama calisti', isset( $wk['__all'], $wk['QZ Deneme'] ), wp_json_encode( array_keys( $wk ) ) );

	if ( isset( $wk['__all'] ) ) {
		$same = array();
		$wide = array();

		foreach ( $wk_terms as $term ) {
			$new = $wk[ $term ]['new'] ?? array();
			$old = $wk[ $term ]['old'] ?? array();
			$same[ $term ] = $new === ( $wk[ $term ]['ranked'] ?? null );
			$wide[ $term ] = array() === array_diff( $old, $new );
		}

		// 0.27.2'den beri magaza aramasi Kocist'le ayni sirali ortak arama (nwcs_search_products).
		check( 'WK: magaza aramasi = ortak sirali arama (sonuc ve sira)', ! in_array( false, $same, true ), wp_json_encode( array_keys( array_filter( $same, static fn( $ok ) => ! $ok ) ) ) );
		check( 'WK: eskiden bulunan her urun yine bulunur', ! in_array( false, $wide, true ) );
		check( 'WK: "çiçekli" eskisi gibi; "CICEKLI" ve "İĞNE" artik da buluyor (katlama)', array( $e ) === $wk['çiçekli']['new'] && array( $e ) === $wk['çiçekli']['old'] && array( $e ) === $wk['CICEKLI']['new'] && array() === $wk['CICEKLI']['old'] && array( $e ) === $wk['İĞNE']['new'] );
		check( 'WK: arama renk grubunu sadelestirmez; aramasiz liste tek kart', array( $g, $h ) === array_values( array_intersect( $wk['renkli masa']['new'], array( $g, $h ) ) ) && in_array( $g, $wk['__cards'], true ) && ! in_array( $h, $wk['__cards'], true ) );
	}

	$wk_page = http( '/woodkocist/magaza/?ara=CICEKLI' );
	check( 'WK magaza: "CICEKLI" Turkce harfli urunu bulur', str_contains( $wk_page['body'], esc_html( $p['e']['title'] ) ) );

	$wk_add = http( '/woodkocist/wp-admin/admin-post.php', array(), array( 'action' => 'wk_cart', 'wk_do' => 'add', 'wk_id' => $b, 'wk_qty' => '2' ) );
	check( 'WK sepet: fiyatli urun eklenir (wk_cart=b:2, /sepet/), Kocist cerezine dokunmaz', 302 === $wk_add['code'] && str_ends_with( (string) ( $wk_add['headers']['location'] ?? '' ), '/woodkocist/sepet/' ) && $b . ':2' === rawurldecode( (string) strtok( substr( set_cookie_header( $wk_add, 'wk_cart' ), 8 ), ';' ) ) && '' === set_cookie_header( $wk_add, KOCIST_BASKET_COOKIE ), $wk_add['code'] . ' ' . set_cookie_header( $wk_add, 'wk_cart' ) );
	$wk_free = http( '/woodkocist/wp-admin/admin-post.php', array( 'wk_cart' => $b . ':2' ), array( 'action' => 'wk_cart', 'wk_do' => 'add', 'wk_id' => $a, 'wk_qty' => '1' ) );
	check( 'WK sepet: fiyatsiz urun eklenmez (eskisi gibi)', $b . ':2' === rawurldecode( (string) strtok( substr( set_cookie_header( $wk_free, 'wk_cart' ), 8 ), ';' ) ) );
	$wk_cart = http( '/woodkocist/sepet/', array( 'wk_cart' => $b . ':2' ) );
	check( 'WK sepet sayfasi: urun satiri ve toplam (eskisi gibi)', 200 === $wk_cart['code'] && str_contains( $wk_cart['body'], esc_html( $p['b']['title'] ) ) && str_contains( $wk_cart['body'], '2.500' ) );

	/* ---------- 8) E_ALL ---------- */

	$strict = strict_notices(
		array(
			array( '/kocist/arama/?ara=adrk', '' ),
			array( '/kocist/arama/?ara=zzyyxxq', '' ),
			array( '/kocist/arama/?ara=a', '' ),
			array( '/kocist/arama/', '' ),
			array( '/kocist/teklif-sepeti/', '' ),
			array( '/kocist/teklif-sepeti/', $a . ':3|' . $b . ':1|' . $i . ':2|999999998:4' ),
			array( '/kocist/teklif-sepeti/?kc=' . $err_token, $a . ':1' ),
			array( wp_parse_url( $p['a']['url'], PHP_URL_PATH ) . '?teklif=eklendi', $a . ':2' ),
			array( wp_parse_url( $p['a']['url'], PHP_URL_PATH ), '' ),
			array( '/kocist/kategoriler/kereste/', '' ),
			array( '/woodkocist/magaza/?ara=cicekli', '' ),
		)
	);
	check( 'sayfalar E_ALL ile: temada ve eklentide uyari yok', ! $strict, implode( ' | ', array_slice( $strict, 0, 4 ) ) );
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

wp_set_current_user( $admin );
set_cookie_value( '' );

foreach ( get_posts( array( 'post_type' => 'kc_quote', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'post__not_in' => $quotes_before ?: array( 0 ) ) ) as $quote ) {
	wp_delete_post( $quote, true );
}

// Hiz siniri sayaclari: betigin kendi sureci (adres yok) ve HTTP istekleri (127.0.0.1).
delete_transient( kocist_form_rate_key() );
$remote_addr            = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
delete_transient( kocist_form_rate_key() );
if ( null === $remote_addr ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $remote_addr;
}

foreach ( array_unique( $tokens ) as $token ) {
	delete_transient( 'kc_quote_' . $token );
}

foreach ( $sites_before as $blog => $options ) {
	foreach ( $options as $option => $value ) {
		if ( null === $value ) {
			delete_blog_option( $blog, $option );
		} else {
			update_blog_option( $blog, $option, $value );
		}
	}
}

foreach ( $pages_before as $slug => $before ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );

	if ( ! $before && $page ) {
		wp_delete_post( $page->ID, true );
	}
}

if ( null === $pages_version ) {
	delete_option( 'kocist_pages_version' );
} else {
	update_option( 'kocist_pages_version', $pages_version );
}

switch_to_blog( $pool );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array_keys( $terms ) as $term_slug ) {
	$term = get_term_by( 'slug', $term_slug, NWCS_PRODUCT_TAX );

	if ( $term ) {
		wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
	}
}
restore_current_blog();
nwcs_pool_flush_cache();

// Mailpit'teki deneme iletileri.
$mail_ids = array_column( array_merge( mailpit_search( 'QZT-ADR-K-TK' ), mailpit_search( '"Deneme Kişi"' ) ), 'ID' );
if ( $mail_ids ) {
	wp_remote_request( 'http://mailpit:8025/api/v1/messages', array( 'method' => 'DELETE', 'timeout' => 10, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'IDs' => array_values( array_unique( $mail_ids ) ) ) ) ) );
}

$left_quotes = get_posts( array( 'post_type' => 'kc_quote', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
check( 'temizlik: deneme urunleri, kategorileri ve talepleri kalmadi', ! get_term_by( 'slug', 'zz-teklif-deneme', NWCS_PRODUCT_TAX ) && ! array_diff( $left_quotes, $quotes_before ) && ! mailpit_search( 'QZT-ADR-K-TK' ) );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
