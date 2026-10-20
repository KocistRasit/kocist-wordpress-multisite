<?php
/**
 * Yerel deneme: Ürün Havuzu -> Kategoriler -> "Sık sorulan sorular".
 *
 * Kendi deneme kategorilerini ve urunlerini olusturur, Kocist ve WOOD KOCIST'te
 * gosterir:
 *   "ZZ SSS Deneme" (sorulu): urunler A, B; iki siteye yerlesik;
 *   "ZZ SSS Bos" (sorusuz): urun C; yalnizca Kocist'te (eski baglanti denenir).
 * Denenenler: temizlik yardimcilari (tek satir soru, paragrafli cevap, bos satir,
 * en fazla 10 satir), benzerlik (nwcs_seo_near_text; SEO tutarlilik kontrolu
 * ayni kalir), kaydetme (istek yolu, nonce, yetki, yerlesik olmayan site),
 * dogrulama (sorusuz cevap, fazla satir: kayit yok, yazilanlar geri gelir),
 * JS'siz ↑ ↓ ve "Sil", cevapsiz soru (kaydedilir, sitede yok), "Son işlemler"
 * ve geri alma, gecmiste birlesme (ayni kullanici, kategori ve site, 30 dakika;
 * eski hale donen kayit duser; baska site ve kullanici ayri kayit), kopya
 * uyarisi (ayni / cok benzer / kisa cevap yok sayilir; kayit yine yapilir),
 * panel ekrani (sekmeler, yerlesmeyen site satiri, baslik bilgisi, bolum yeri,
 * 10 satirda bos satir yok), kategori silme + geri alma (sorular geri gelir).
 * Sonda sitelerin sayfalari HTTP ile cizilir: kategori sayfasinda #sss akordeonu
 * ve FAQPage (sayfa dugumunde tek), grup ve urun sayfasinda FAQPage yok, urun
 * sayfasinda ilk uc soru ve "Tüm sorular" (#sss), Kocist'te sorusuz kategoride
 * eski grup SSS baglantisi, sitenin SSS sayfasi FAQPage olarak kalir, llms.txt
 * kategori sorularini listeler.
 * Sonunda her seyi siler, site ayarlarini ve "Son işlemler"i eski haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-cat-faq.php
 */

define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', true );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/wp-admin/network/admin.php';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

error_reporting( E_ALL );

class Nwcs_Redirect extends Exception {}
class Nwcs_Die extends Exception {}

add_filter(
	'wp_redirect',
	static function ( $location ) {
		throw new Nwcs_Redirect( (string) $location );
	},
	99
);

// Yanlis nonce ve yetkisiz istek wp_die ile biter: betik durmasin, istisna olsun.
add_filter(
	'wp_die_handler',
	static fn() => static function ( $message ): void {
		throw new Nwcs_Die( is_string( $message ) ? $message : 'wp_die' );
	}
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

$admin = get_user_by( 'login', get_super_admins()[0] )->ID;
wp_set_current_user( $admin );

const K_BLOG = 8;  // Kocist
const W_BLOG = 13; // WOOD KOCIST

$history_before = get_site_option( NWCS_HISTORY, array() );
$pool           = nwcs_pool_blog_id();

/** Havuzdaki soru meta satiri sayisi (baslangicta ve sonda ayni olmali). */
function faq_meta_rows(): int {
	global $wpdb;

	switch_to_blog( nwcs_pool_blog_id() );
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s", NWCS_CAT_FAQ_META ) );
	restore_current_blog();

	return $count;
}

$meta_before = faq_meta_rows();
$sites_before   = array();

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	foreach ( array( NWCS_OPTION_MODE, NWCS_OPTION_SELECTED, 'blog_public' ) as $option ) {
		$sites_before[ $blog ][ $option ] = get_blog_option( $blog, $option, null );
	}
}

/** Istek yolu: handler'i POST verisiyle calistirir, yonlendirme adresini dondurur. */
function post( callable $handler, array $data, string $nonce ): string {
	$_POST                = wp_slash( $data + array( '_wpnonce' => wp_create_nonce( $nonce ) ) );
	$_REQUEST             = $_POST;
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

	try {
		$handler();
	} catch ( Nwcs_Redirect $redirect ) {
		return $redirect->getMessage();
	} catch ( Nwcs_Die $die ) {
		return 'wp_die: ' . $die->getMessage();
	} finally {
		$_POST    = array();
		$_REQUEST = array();
	}

	return '';
}

/** Sitenin sayfasi (konteynerin icinden; Host basligi panelin adresi). */
function page( string $path ): string {
	$response = wp_remote_get(
		'http://127.0.0.1' . $path,
		array(
			'headers'     => array( 'Host' => 'localhost:8080' ),
			'timeout'     => 30,
			'redirection' => 0,
		)
	);

	return is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
}

/**
 * Sayfalari ayri bir PHP sureciyle, tam istek olarak ve E_ALL hata
 * yakalayicisiyla cizer (HTTP istegi WP_DEBUG kapaliyken notice'leri
 * gostermez). Temada ve eklentide cikan uyarilari dondurur.
 *
 * @param string[] $paths
 * @return string[]
 */
function strict_notices( array $paths ): array {
	$runner = sys_get_temp_dir() . '/nwcs-strict-render.php';
	$code   = <<<'PHP'
<?php
$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_URI']     = $argv[1];
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

/** Sayfadaki JSON-LD @graph dugumleri. */
function graph( string $html ): array {
	preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $match );
	$data = $match ? json_decode( $match[1], true ) : null;

	return is_array( $data['@graph'] ?? null ) ? $data['@graph'] : array();
}

/** FAQPage turundeki dugumler. */
function faq_nodes( array $nodes ): array {
	return array_values( array_filter( $nodes, static fn( array $node ): bool => in_array( 'FAQPage', (array) ( $node['@type'] ?? array() ), true ) ) );
}

/** En ustteki gecmis kaydini birlesme suresinden eski yapar. */
function fresh(): void {
	$list = nwcs_history_all();

	if ( $list ) {
		$list[0]['time'] = time() - NWCS_CAT_FAQ_MERGE - 60;
		update_site_option( NWCS_HISTORY, $list );
	}
}

/** Bolumun HTML'i. */
function section_html( string $slug, array $get = array() ): string {
	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug ) + $get;
	ob_start();
	nwcs_render_cat_faq( $slug );
	$_GET = array();

	return (string) ob_get_clean();
}

/** Formun satirlari: [ [soru, cevap], ... ] -> sss[0..][q|a]; sona bos "yeni" satir. */
function rows( array $pairs, bool $blank = true ): array {
	$out = array();

	foreach ( array_values( $pairs ) as $index => $pair ) {
		$out[ (string) $index ] = array( 'q' => $pair[0], 'a' => $pair[1] );
	}

	if ( $blank ) {
		$out['yeni'] = array( 'q' => '', 'a' => '' );
	}

	return $out;
}

/** Kayitli sorular (yalnizca soru metinleri). */
function questions( string $slug, string $site ): array {
	return array_column( nwcs_cat_faq_rows( $slug, $site ), 'q' );
}

/* ---------- Deneme verisi ---------- */

switch_to_blog( $pool );

$cat   = wp_insert_term( 'ZZ SSS Deneme', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-sss-deneme' ) );
$cat   = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
$empty = wp_insert_term( 'ZZ SSS Bos', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-sss-bos' ) );
$empty = is_wp_error( $empty ) ? (int) $empty->get_error_data() : (int) $empty['term_id'];

$ids = array();
foreach (
	array(
		'a' => array( 'ZZ SSS Kamelya Bir', 'ZZSSS-A', $cat ),
		'b' => array( 'ZZ SSS Kamelya Iki', 'ZZSSS-B', $cat ),
		'c' => array( 'ZZ SSS Bos Urun', 'ZZSSS-C', $empty ),
	) as $key => $row
) {
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
	update_post_meta( $ids[ $key ], '_nwcs_price', '1000' );
	wp_set_object_terms( $ids[ $key ], array( $row[2] ), NWCS_PRODUCT_TAX );
}

restore_current_blog();
nwcs_pool_flush_cache();

$slug     = 'zz-sss-deneme';
$k_key    = nwcs_catalog_site_key( K_BLOG );
$w_key    = nwcs_catalog_site_key( W_BLOG );
$k_parent = isset( nwcs_site_parents( K_BLOG )['kereste'] ) ? 'kereste' : (string) array_key_first( nwcs_site_parents( K_BLOG ) );
$w_parent = (string) array_key_first( nwcs_site_parents( W_BLOG ) );

nwcs_set_placement( $slug, $k_key, $k_parent );
nwcs_set_placement( $slug, $w_key, $w_parent );
nwcs_set_placement( 'zz-sss-bos', $k_key, $k_parent );

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	update_blog_option( $blog, NWCS_OPTION_MODE, 'selected' );
	nwcs_selection_append( $blog, array_values( $ids ) );
}

nwcs_pool_flush_cache();

[ 'a' => $a, 'b' => $b, 'c' => $c ] = $ids;

$save = static fn( string $site, array $data, string $category = 'zz-sss-deneme' ): string => post( 'nwcs_handle_cat_faq_save', $data + array( 'action' => 'nwcs_cat_faq_save', 'kategori' => $category, 'site' => $site ), 'nwcs_cat_faq_save_' . $category );

$k_qa = array(
	array( 'Kamelya kaç kişiliktir?', "Küçük model dört, büyük model sekiz kişiliktir.\n\nÖlçüye göre farklı boy da üretilir." ),
	array( 'Kamelyanın çatısı neyle kaplanır?', 'Çatı shingle ile kaplanır; antrasit, kırmızı ve yeşil renk seçilebilir.' ),
	array( 'Kamelya zemine nasıl sabitlenir?', 'Ayaklar betona dübelle ya da toprakta kazık ayakla sabitlenir; montaj ekibi yerinde karar verir.' ),
);

try {
	/* ---------- 1) Yardimcilar ---------- */

	check( 'soru: tek satir, etiket yok', 'Kaç kişilik?' === nwcs_cat_faq_question( "  Kaç\n <b>kişilik?</b> " ) );
	check( 'soru: en fazla 200 karakter', NWCS_CAT_FAQ_Q_MAX === mb_strlen( nwcs_cat_faq_question( str_repeat( 'ş', 300 ) ) ) );
	check( 'cevap: paragraf korunur, 3+ bos satir tek bosluk', "Bir.\n\nİki <20 mm.\nÜç." === nwcs_cat_faq_answer( "Bir.\r\n\r\n\r\n\r\n<p>İki <20 mm.</p>\nÜç.  " ), wp_json_encode( nwcs_cat_faq_answer( "Bir.\r\n\r\n\r\n\r\n<p>İki <20 mm.</p>\nÜç.  " ), JSON_UNESCAPED_UNICODE ) );
	check( 'cevap: en fazla 2000 karakter', NWCS_CAT_FAQ_A_MAX === mb_strlen( nwcs_cat_faq_answer( str_repeat( 'a', 2500 ) ) ) );
	check( 'temizlik: bos satir atilir, en fazla 10', 10 === count( nwcs_cat_faq_clean_rows( array_merge( array( array( 'q' => '', 'a' => ' ' ) ), array_fill( 0, 12, array( 'q' => 'S?', 'a' => 'C.' ) ) ) ) ) );
	check( 'temizlik: bos site listesi ve gecersiz anahtar atilir', array( 'kocist' ) === array_keys( nwcs_cat_faq_clean( array( 'kocist' => array( array( 'q' => 'S?', 'a' => '' ) ), 'woodkocist' => array(), '' => array( array( 'q' => 'X', 'a' => 'Y' ) ) ) ) ) );

	check( 'benzerlik: bosluk ve harf farki ayni', nwcs_seo_near_text( 'Çatı  shingle ile kaplanır.', 'çatı shingle ile kaplanır' ) );
	check( 'benzerlik: tek kelime farki cok benzer', nwcs_seo_near_text( 'Ayaklar betona dübelle ya da toprakta kazık ayakla sabitlenir; montaj ekibi yerinde karar verir.', 'Ayaklar betona dübelle ya da toprakta kazık ayakla sabitlenir; kurulum ekibi yerinde karar verir.' ) );
	check( 'benzerlik: farkli metin degil', ! nwcs_seo_near_text( 'Küçük model dört kişiliktir.', 'Çatı shingle ile kaplanır; renk seçilebilir.' ) );
	check( 'benzerlik: uzunluk cok farkliysa degil', ! nwcs_seo_near_text( 'Evet.', 'Evet. Ayrıca ölçüye göre üretim yapıyoruz ve montaj da yapıyoruz.' ) );

	/* ---------- 2) Kaydetme ---------- */

	$to = $save( $k_key, array( 'sss' => rows( $k_qa ), 'kaydet' => 1 ) );
	check( 'kaydet: kaydedildi, sekmeye doner', str_contains( $to, 'sss=kaydedildi' ) && str_contains( $to, 'sss_site=' . $k_key ) && str_contains( $to, '#nwcs-sss' ), $to );
	check( 'kaydet: 3 soru, bos satir yok sayildi', array_column( $k_qa, 0 ) === questions( $slug, $k_key ) );
	check( 'kaydet: cevap paragraflari korundu', "Küçük model dört, büyük model sekiz kişiliktir.\n\nÖlçüye göre farklı boy da üretilir." === nwcs_cat_faq_rows( $slug, $k_key )[0]['a'] );
	check( 'kaydet: WOOD KOCIST listesi bos', array() === nwcs_cat_faq_rows( $slug, $w_key ) );
	switch_to_blog( $pool );
	$meta = get_term_meta( $cat, NWCS_CAT_FAQ_META, true );
	restore_current_blog();
	check( 'veri: term meta [site_key => [ {q, a} ]]', is_array( $meta ) && array( $k_key ) === array_keys( $meta ) && array( 'q', 'a' ) === array_keys( $meta[ $k_key ][0] ) );

	$top = nwcs_history_top();
	check( 'gecmis: sss kaydi', NWCS_CAT_FAQ_KIND === ( $top['kind'] ?? '' ) && $slug === $top['slug'] && $k_key === $top['site'] && array() === $top['before'] && 3 === count( $top['after'] ) );
	$info = nwcs_history_describe( $top );
	check( 'gecmis: ozet', 'sık sorulan sorular' === $info['kind'] && 'ZZ SSS Deneme' === $info['what'] && str_contains( $info['facts'], 'Koçist’te 3 soru eklendi' ), $info['kind'] . ': ' . $info['facts'] );
	$first_id = (string) $top['id'];

	$to = $save( $k_key, array( 'sss' => rows( $k_qa ) ) );
	check( 'kaydet: degisiklik yoksa ayni, gecmis yazilmaz', str_contains( $to, 'sss=ayni' ) && $first_id === nwcs_history_top()['id'], $to );

	/* ---------- 3) Dogrulama ---------- */

	$to = $save( $k_key, array( 'sss' => rows( array_merge( $k_qa, array( array( '', 'Sorusu olmayan cevap.' ), array( 'Taslak soru?', 'Taslak cevap.' ) ) ) ) ) );
	check( 'sorusuz cevap: kaydedilmez', str_contains( $to, 'sss=sorusuz' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) && $first_id === nwcs_history_top()['id'], $to );
	$html = section_html( $slug, array( 'sss' => 'sorusuz', 'sss_site' => $k_key ) );
	check( 'sorusuz cevap: hata satir numarasini soyler', str_contains( $html, 'Kaydedilmedi: 4. satırda cevap var ama soru boş.' ) && 1 === substr_count( $html, 'aria-invalid="true"' ) );
	check( 'sorusuz cevap: yazilanlar geri geldi', str_contains( $html, 'Taslak soru?' ) && str_contains( $html, 'Sorusu olmayan cevap.' ) );
	check( 'sorusuz cevap: taslak bir kez', false === get_site_transient( NWCS_CAT_FAQ_DRAFT . get_current_user_id() ) && ! str_contains( section_html( $slug ), 'Taslak soru?' ) );

	$many = array();
	for ( $i = 1; $i <= 11; $i++ ) {
		$many[] = array( "Soru $i?", "Cevap $i." );
	}
	$to = $save( $k_key, array( 'sss' => rows( $many, false ) ) );
	check( 'en fazla 10: 11 satir kaydedilmez', str_contains( $to, 'sss=fazla' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ), $to );
	$html = section_html( $slug, array( 'sss' => 'fazla', 'sss_site' => $k_key ) );
	check( 'en fazla 10: hata ve 11 satir geri geldi', str_contains( $html, 'en fazla 10 soru olabilir, formda 11 soru var' ) && str_contains( $html, 'Soru 11?' ) );

	$to = $save( 'yoksite', array( 'sss' => rows( $k_qa ) ) );
	check( 'yerlesik olmayan site: yer', str_contains( $to, 'sss=yer' ) );

	$to = $save( $k_key, array( 'sss' => rows( array( array( 'X?', 'Y.' ) ) ) ), 'yok-boyle-kategori' );
	check( 'bilinmeyen kategori: listeye doner, yazmaz', str_contains( $to, 'page=' . NWCS_CATEGORIES_SLUG ) && ! str_contains( $to, 'sss=' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ), $to );

	$to = post( 'nwcs_handle_cat_faq_save', array( 'action' => 'nwcs_cat_faq_save', 'kategori' => $slug, 'site' => $k_key, 'sss' => rows( array() ) ), 'nwcs_cat_faq_save_baska' );
	check( 'nonce: yanlis nonce yazmaz', str_starts_with( $to, 'wp_die' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ), $to );

	wp_set_current_user( 0 );
	$to = $save( $k_key, array( 'sss' => rows( array() ) ) );
	wp_set_current_user( $admin );
	check( 'yetki: oturumsuz istek yazmaz', str_starts_with( $to, 'wp_die' ) && str_contains( $to, 'yetkiniz yok' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ), $to );

	/* ---------- 4) JS'siz ↑ ↓ ve Sil, cevapsiz soru, birlesme, geri alma ---------- */

	fresh();
	$to = $save( $k_key, array( 'sss' => rows( $k_qa ), 'tasi' => '2:up' ) );
	$order_id = (string) nwcs_history_top()['id'];
	check( 'tasi: 3. soru yukari (1, 3, 2)', str_contains( $to, 'sss=kaydedildi' ) && array( $k_qa[0][0], $k_qa[2][0], $k_qa[1][0] ) === questions( $slug, $k_key ) && $order_id !== $first_id, $to );
	check( 'tasi: ozet "sıra değişti"', str_contains( nwcs_history_describe( nwcs_history_top() )['facts'], 'Koçist’te sıra değişti' ), nwcs_history_describe( nwcs_history_top() )['facts'] );

	$moved = array( $k_qa[0], $k_qa[2], $k_qa[1] );
	$save( $k_key, array( 'sss' => rows( $moved ), 'tasi' => '0:up' ) );
	check( 'tasi: ilk satir yukari degismez', array( $k_qa[0][0], $k_qa[2][0], $k_qa[1][0] ) === questions( $slug, $k_key ) && $order_id === nwcs_history_top()['id'] );

	// Cevapsiz soru eklenir: kaydedilir, sitede gorunmez.
	$with_pending = array_merge( $moved, array( array( 'Kamelya kaç günde teslim edilir?', '' ) ) );
	$save( $k_key, array( 'sss' => rows( $with_pending ) ) );
	$top = nwcs_history_top();
	check( 'cevapsiz soru: kaydedildi, ayni kayitta (birlesme)', 4 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) && $order_id === $top['id'] && 2 === (int) $top['edits'] && str_contains( implode( ', ', $top['facts'] ), '2 düzenleme' ), implode( ', ', (array) $top['facts'] ) );
	check( 'birlesme: "onceki" ilk kayittan onceki hal', array_column( $k_qa, 0 ) === array_column( (array) $top['before'], 'q' ) );
	check( 'cevapsiz soru: sitede yok', 3 === count( nwcs_cat_faq_public( $slug, K_BLOG ) ) && ! in_array( 'Kamelya kaç günde teslim edilir?', array_column( nwcs_cat_faq_public( $slug, K_BLOG ), 'question' ), true ) );
	check( 'cevapsiz soru: panelde uyari', str_contains( section_html( $slug ), 'Cevabı boş: bu soru sitede görünmez.' ) );

	// JS'siz Sil: o satir cikar, diger duzenlemeler kaydedilir.
	$to = $save( $k_key, array( 'sss' => rows( $with_pending ), 'sil' => '3' ) );
	check( 'sil: satir silindi, silindi bildirimi', str_contains( $to, 'sss=silindi' ) && 3 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) && 3 === (int) nwcs_history_top()['edits'], $to );

	// Birlesen kayitlar eski hale donerse kayit duser.
	$save( $k_key, array( 'sss' => rows( $k_qa ) ) );
	check( 'birlesme: eski hale donen kayit duser', $first_id === nwcs_history_top()['id'] && array_column( $k_qa, 0 ) === questions( $slug, $k_key ) );

	// Baska kullanicinin kaydina eklenmez.
	$list            = nwcs_history_all();
	$list[0]['time'] = time();
	$list[0]['user'] = 999999;
	update_site_option( NWCS_HISTORY, $list );
	$save( $k_key, array( 'sss' => rows( array_reverse( $k_qa ) ) ) );
	check( 'birlesme: baska kullanicinin kaydina eklenmez', $first_id !== nwcs_history_top()['id'] );
	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: eski sira geri geldi', ! is_wp_error( $undo ) && array_column( $k_qa, 0 ) === questions( $slug, $k_key ) && $first_id === nwcs_history_top()['id'], is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	$list            = nwcs_history_all();
	$list[0]['user'] = $admin;
	update_site_option( NWCS_HISTORY, $list );

	/* ---------- 5) Ikinci site ve kopya uyarisi ---------- */

	$w_qa = array(
		array( 'Kamelyalar kaç kişilik oluyor?', 'Bahçe kamelyalarımız iki ile on kişi arasında, modeline göre değişen boylarda üretilir.' ),
		array( 'Çatı kaplaması nedir?', 'Çatı shingle ile kaplanır; antrasit, kırmızı ve yeşil renk seçilebilir.' ),
		array( 'Sabitleme nasıl yapılır?', 'Ayaklar betona dübelle ya da toprakta kazık ayakla sabitlenir; kurulum ekibi yerinde karar verir.' ),
		array( 'Garanti var mı?', 'Evet.' ),
	);
	$to    = $save( $w_key, array( 'sss' => rows( $w_qa ) ) );
	$w_top = nwcs_history_top();
	check( 'WK kaydet: kopya olsa da kaydedildi', str_contains( $to, 'sss=kaydedildi' ) && 4 === count( nwcs_cat_faq_rows( $slug, $w_key ) ), $to );
	check( 'WK kaydet: baska site ayri kayit', NWCS_CAT_FAQ_KIND === ( $w_top['kind'] ?? '' ) && $w_key === $w_top['site'] && $first_id !== $w_top['id'] && $first_id === ( nwcs_history_all()[1]['id'] ?? '' ) );
	check( 'Kocist listesi degismedi', array_column( $k_qa, 0 ) === questions( $slug, $k_key ) );

	$copies = nwcs_cat_faq_duplicates( $slug, $w_key, nwcs_cat_faq_rows( $slug, $w_key ) );
	check( 'kopya: ayni ve cok benzer bulundu, farkli ve kisa yok', array( 1, 2 ) === array_keys( $copies ) && true === $copies[1]['same'] && false === $copies[2]['same'] && 'Kamelya zemine nasıl sabitlenir?' === $copies[2]['question'] && 'Koçist' === $copies[1]['site'], wp_json_encode( $copies, JSON_UNESCAPED_UNICODE ) );

	$html = section_html( $slug, array( 'sss' => 'kaydedildi', 'sss_site' => $w_key ) );
	check( 'kopya: kayittan sonra uyari soruyu ve siteyi soyler', str_contains( $html, 'Kayıt yapıldı, ama bu cevaplar başka bir sitede de var:' ) && str_contains( $html, '“Çatı kaplaması nedir?”: cevabı Koçist’te “Kamelyanın çatısı neyle kaplanır?” sorusunun cevabıyla aynı.' ) && str_contains( $html, '“Sabitleme nasıl yapılır?”: cevabı Koçist’te “Kamelya zemine nasıl sabitlenir?” sorusunun cevabıyla çok benzer.' ), wp_strip_all_tags( $html ) );
	check( 'kopya: Kocist sekmesinde de "Dikkat"', str_contains( section_html( $slug, array( 'sss_site' => $k_key ) ), 'Dikkat, bu cevaplar başka bir sitede de var:' ) );
	// Iki sekme de sayfada (biri gizli): her sitede iki satir uyarili.
	check( 'kopya: satirda da uyari', 4 === substr_count( $html, 'Bu siteye göre yeniden yazın.' ) );
	check( 'kopya: kisa cevap ("Evet.") uyari vermez', ! str_contains( $html, '“Garanti var mı?”: cevabı' ) );

	/* ---------- 6) Panel ekrani ---------- */

	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug );
	ob_start();
	nwcs_render_category_detail( $slug, nwcs_pool_categories()[ $slug ] );
	$html = (string) ob_get_clean();
	$_GET = array();
	check( 'ekran: bolum renk gruplarindan sonra, urunlerden once', strpos( $html, 'id="nwcs-renk"' ) < strpos( $html, 'id="nwcs-sss"' ) && strpos( $html, 'id="nwcs-sss"' ) < strpos( $html, 'id="nwcs-sec-products"' ) );
	check( 'ekran: baslikta "SSS: Koçist 3, WOOD KOCIST 4 soru"', str_contains( $html, 'href="#nwcs-sss">SSS: Koçist 3, WOOD KOCIST 4 soru</a>' ) );
	check( 'ekran: iki sekme, ilk sekme acik, ikinci panel gizli', 2 === substr_count( $html, 'data-nwcs-faq-tab=' ) && 1 === substr_count( $html, 'nwcs-faqs__tab is-active' ) && str_contains( $html, 'data-nwcs-faq-panel="' . $w_key . '" hidden' ) );
	check( 'ekran: ipucu 3–6 soru ve genel sorular', str_contains( $html, '3–6 soru' ) && str_contains( $html, 'Teslimat, montaj ve ödeme gibi genel sorular sitenin SSS sayfasında kalsın.' ) );
	check( 'ekran: JSsiz satir dugmeleri, sekme basina bir Kaydet', str_contains( $html, 'value="1:up"' ) && str_contains( $html, 'name="sil" value="0"' ) && 2 === substr_count( $html, '>Soruları kaydet</button>' ) && 2 === substr_count( $html, 'name="sss[yeni][q]"' ) );
	check( 'ekran: kategori sayfasi baglantisi #sss', str_contains( $html, 'kategoriler/' . $k_parent . '/' . $slug . '/#sss' ) );

	$html = section_html( $slug, array( 'sss_site' => $w_key ) );
	check( 'ekran: ?sss_site= ile ikinci sekme acik', str_contains( $html, 'data-nwcs-faq-panel="' . $k_key . '" hidden' ) && ! str_contains( $html, 'data-nwcs-faq-panel="' . $w_key . '" hidden' ) );

	// Yerlesmeyen site: sekme yok, nedeni bir satir; kayitli sorular durur.
	nwcs_set_placement( $slug, $w_key, null );
	$html = section_html( $slug );
	check( 'yerlesmeyen site: tek sekme ve neden satiri', 1 === substr_count( $html, 'data-nwcs-faq-tab=' ) && str_contains( $html, 'WOOD KOCIST’te sekme yok: kategori o sitede gösterilmiyor.' ) && str_contains( $html, 'O site için kayıtlı 4 soru duruyor' ) );
	check( 'yerlesmeyen site: sitede soru yok, kopya uyarisi yok', array() === nwcs_cat_faq_public( $slug, W_BLOG ) && ! str_contains( $html, 'Dikkat, bu cevaplar' ) );
	$to = $save( $w_key, array( 'sss' => rows( $w_qa ) ) );
	check( 'yerlesmeyen site: kayit yapilmaz (yer)', str_contains( $to, 'sss=yer' ) );
	nwcs_set_placement( $slug, $w_key, $w_parent );
	nwcs_pool_flush_cache();
	check( 'yeniden yerlesince sorular geri', 4 === count( nwcs_cat_faq_public( $slug, W_BLOG ) ) );

	// Hic yerlesmeyen kategori: sekme yok.
	check( 'yerlesimsiz kategori: aciklama', str_contains( section_html( 'zz-sss-bos' ), 'WOOD KOCIST’te sekme yok' ) );

	// 10 soruda bos "Yeni soru" satiri yok.
	$ten = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$ten[] = array( "On soru $i?", "On cevap $i, kategoriye özel uzun bir metin." );
	}
	fresh();
	$save( $k_key, array( 'sss' => rows( $ten ) ) );
	$html = section_html( $slug );
	// Bos satir yalnizca WOOD KOCIST sekmesinde (4 soru) kalir.
	check( '10 soru: kaydedildi, bos satir yok', 10 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) && 1 === substr_count( $html, 'name="sss[yeni][q]"' ) && ! str_contains( $html, 'id="nwcs-sss-' . $k_key . '-yeni-q"' ) );
	nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: 3 soru', array_column( $k_qa, 0 ) === questions( $slug, $k_key ) );

	// Sitede dorduncu soru: urun sayfasinda ilk uc gorunsun.
	fresh();
	$k_four = array_merge( $k_qa, array( array( 'Kamelya boyanabilir mi?', 'Evet, su bazlı ahşap boyasıyla iki yılda bir boyanması önerilir.' ) ) );
	$save( $k_key, array( 'sss' => rows( $k_four ) ) );
	check( 'Kocist: 4 soru sitede', 4 === count( nwcs_cat_faq_public( $slug, K_BLOG ) ) && 3 === count( nwcs_cat_faq_public( $slug, K_BLOG, NWCS_CAT_FAQ_PRODUCT ) ) );

	/* ---------- 7) Sitelerin sayfalari (HTTP) ---------- */

	$k_cat = page( '/kocist/kategoriler/' . $k_parent . '/' . $slug . '/' );
	check( 'Kocist kategori: #sss akordeonu ve baslik', str_contains( $k_cat, '<section class="k-catfaq" id="sss"' ) && str_contains( $k_cat, '>Sık sorulan sorular</h2>' ) && 4 === substr_count( $k_cat, '<details class="k-sss__item">' ), substr( wp_strip_all_tags( $k_cat ), 0, 80 ) );
	check( 'Kocist kategori: urunlerin altinda', strpos( $k_cat, 'class="k-plist"' ) < strpos( $k_cat, 'id="sss"' ) );
	check( 'Kocist kategori: cevap paragraflari', str_contains( $k_cat, '<p>Küçük model dört, büyük model sekiz kişiliktir.</p><p>Ölçüye göre farklı boy da üretilir.</p>' ) );
	check( 'Kocist kategori: faq.css yuklu, onizleme isareti yok', str_contains( $k_cat, 'assets/css/faq.css' ) && ! str_contains( $k_cat, 'data-nwcs-source' ) );
	$nodes = graph( $k_cat );
	$faq   = faq_nodes( $nodes );
	check( 'Kocist kategori JSON-LD: tek FAQPage, sayfa dugumunde', 1 === count( $faq ) && array( 'WebPage', 'FAQPage' ) === $faq[0]['@type'] && str_ends_with( (string) $faq[0]['@id'], '#webpage' ) && 1 === count( array_filter( $nodes, static fn( array $node ): bool => str_ends_with( (string) ( $node['@id'] ?? '' ), '#webpage' ) ) ) );
	check( 'Kocist kategori JSON-LD: 4 soru, cevap tek satir', 4 === count( (array) ( $faq[0]['mainEntity'] ?? array() ) ) && 'Question' === $faq[0]['mainEntity'][0]['@type'] && 'Kamelya kaç kişiliktir?' === $faq[0]['mainEntity'][0]['name'] && 'Küçük model dört, büyük model sekiz kişiliktir. Ölçüye göre farklı boy da üretilir.' === $faq[0]['mainEntity'][0]['acceptedAnswer']['text'] );

	$k_group = page( '/kocist/kategoriler/' . $k_parent . '/' );
	check( 'Kocist grup sayfasi: soru ve FAQPage yok', '' !== $k_group && ! str_contains( $k_group, 'id="sss"' ) && ! faq_nodes( graph( $k_group ) ) );

	$k_empty = page( '/kocist/kategoriler/' . $k_parent . '/zz-sss-bos/' );
	check( 'Kocist sorusuz kategori: bolum ve FAQPage yok', str_contains( $k_empty, 'ZZ SSS Bos Urun' ) && ! str_contains( $k_empty, 'id="sss"' ) && ! faq_nodes( graph( $k_empty ) ) );

	$k_page = page( '/kocist/urun/zz-sss-kamelya-bir/' );
	check( 'Kocist urun: ilk uc soru', str_contains( $k_page, 'class="k-acc__faq k-pfaq"' ) && 3 === substr_count( $k_page, '<details class="k-sss__item">' ) && ! str_contains( $k_page, 'Kamelya boyanabilir mi?' ) );
	check( 'Kocist urun: "Tüm sorular" kategori sayfasi #sss', (bool) preg_match( '#<a class="k-acc__faq-link k-pfaq__all" href="[^"]*/kocist/kategoriler/' . preg_quote( $k_parent, '#' ) . '/' . $slug . '/\#sss">\s*<span[^>]*>Tüm sorular</span>#', $k_page ) );
	check( 'Kocist urun: eski grup baglantisi yerine', ! str_contains( $k_page, 'class="k-acc__faq">' ) );
	check( 'Kocist urun JSON-LD: FAQPage yok, Product var', ! faq_nodes( graph( $k_page ) ) && (bool) array_filter( graph( $k_page ), static fn( array $node ): bool => 'Product' === ( $node['@type'] ?? '' ) ) );

	// Uc ya da daha az soruda "Tüm sorular" yok: ayni sorulari tekrar gosteren sayfaya goturmesin.
	fresh();
	$save( $k_key, array( 'sss' => rows( $k_qa ) ) );
	$k_three = page( '/kocist/urun/zz-sss-kamelya-bir/' );
	check( 'Kocist urun: 3 soruda hepsi var, "Tüm sorular" yok', 3 === substr_count( $k_three, '<details class="k-sss__item">' ) && ! str_contains( $k_three, 'k-pfaq__all' ) );
	fresh();
	$save( $k_key, array( 'sss' => rows( $k_four ) ) );

	switch_to_blog( K_BLOG );
	$k_sss_page = get_page_by_path( 'sss', OBJECT, 'page' );
	$k_has_sss  = $k_sss_page && 'publish' === $k_sss_page->post_status && nwcs_faq_groups( 'faq' );
	restore_current_blog();

	$c_page = page( '/kocist/urun/zz-sss-bos-urun/' );
	if ( $k_has_sss ) {
		check( 'Kocist sorusuz kategori urunu: eski SSS baglantisi', str_contains( $c_page, '<p class="k-acc__faq">' ) && (bool) preg_match( '#class="k-acc__faq-link" href="[^"]*/kocist/sss/#', $c_page ) && ! str_contains( $c_page, 'k-pfaq' ) );

		$k_sss = page( '/kocist/sss/' );
		$faq   = faq_nodes( graph( $k_sss ) );
		check( 'Kocist /sss/: FAQPage degismedi (sayfa dugumunde tek)', 1 === count( $faq ) && array( 'WebPage', 'FAQPage' ) === $faq[0]['@type'] && count( (array) $faq[0]['mainEntity'] ) === count( faq_count_k() ) );
	} else {
		check( 'Kocist sorusuz kategori urunu: kategori sorusu yok', '' !== $c_page && ! str_contains( $c_page, 'k-pfaq' ) );
	}

	$w_cat = page( '/woodkocist/urun-kategori/' . $w_parent . '/' . $slug . '/' );
	check( 'WK kategori: #sss akordeonu izgaranin altinda', str_contains( $w_cat, '<section class="wk-faq wk-catfaq" id="sss"' ) && 4 === substr_count( $w_cat, '<details class="wk-faq__item">' ) && strpos( $w_cat, 'class="wk-grid"' ) < strpos( $w_cat, 'id="sss"' ), substr( wp_strip_all_tags( $w_cat ), 0, 80 ) );
	$faq = faq_nodes( graph( $w_cat ) );
	check( 'WK kategori JSON-LD: tek FAQPage, 4 soru', 1 === count( $faq ) && array( 'WebPage', 'FAQPage' ) === $faq[0]['@type'] && 4 === count( (array) ( $faq[0]['mainEntity'] ?? array() ) ) && 'Kamelyalar kaç kişilik oluyor?' === $faq[0]['mainEntity'][0]['name'] );

	$w_page = page( '/woodkocist/urun/zz-sss-kamelya-iki/' );
	check( 'WK urun: ilk uc soru ve "Tüm sorular"', str_contains( $w_page, '<section class="wk-faq wk-pfaq"' ) && 3 === substr_count( $w_page, '<details class="wk-faq__item">' ) && ! str_contains( $w_page, 'Garanti var mı?' ) && (bool) preg_match( '#<p class="wk-pfaq__all"><a href="[^"]*/woodkocist/urun-kategori/' . preg_quote( $w_parent, '#' ) . '/' . $slug . '/\#sss"[^>]*>Tüm sorular#', $w_page ) );
	check( 'WK urun JSON-LD: FAQPage yok', '' !== $w_page && ! faq_nodes( graph( $w_page ) ) );

	fresh();
	$save( $w_key, array( 'sss' => rows( array_slice( $w_qa, 0, 3 ) ) ) );
	$w_three = page( '/woodkocist/urun/zz-sss-kamelya-iki/' );
	check( 'WK urun: 3 soruda "Tüm sorular" yok', 3 === substr_count( $w_three, '<details class="wk-faq__item">' ) && ! str_contains( $w_three, 'wk-pfaq__all' ) );
	fresh();
	$save( $w_key, array( 'sss' => rows( $w_qa ) ) );

	$w_shop = page( '/woodkocist/magaza/' );
	check( 'WK magaza: kategori sorusu yok', '' !== $w_shop && ! str_contains( $w_shop, 'wk-catfaq' ) && ! faq_nodes( graph( $w_shop ) ) );

	// Cevapsiz soru sitede ve arama verisinde yok.
	fresh();
	$save( $w_key, array( 'sss' => rows( array_merge( $w_qa, array( array( 'Cevapsız WK sorusu?', '' ) ) ) ) ) );
	$w_cat = page( '/woodkocist/urun-kategori/' . $w_parent . '/' . $slug . '/' );
	check( 'cevapsiz soru: kategori sayfasinda ve JSON-LD\'de yok', ! str_contains( $w_cat, 'Cevapsız WK sorusu?' ) && 4 === count( (array) ( faq_nodes( graph( $w_cat ) )[0]['mainEntity'] ?? array() ) ) );

	/* ---------- 8) llms.txt ---------- */

	update_blog_option( K_BLOG, 'blog_public', 1 );
	update_blog_option( W_BLOG, 'blog_public', 1 );

	// Sayfalar ayri surecte E_ALL ile: kategori, urun (sorulu ve sorusuz), llms.txt.
	$strict = strict_notices(
		array(
			'/kocist/kategoriler/' . $k_parent . '/' . $slug . '/',
			'/kocist/urun/zz-sss-kamelya-bir/',
			'/kocist/urun/zz-sss-bos-urun/',
			'/kocist/llms.txt',
			'/woodkocist/urun-kategori/' . $w_parent . '/' . $slug . '/',
			'/woodkocist/urun/zz-sss-kamelya-iki/',
			'/woodkocist/llms.txt',
		)
	);
	check( 'sayfalar E_ALL: temada ve eklentide uyari yok', ! $strict, implode( ' | ', array_slice( $strict, 0, 5 ) ) );

	$k_llms = page( '/kocist/llms.txt' );
	check( 'llms Kocist: kategori sorulari bolumu', str_contains( $k_llms, "## Kategorilere özel sık sorulan sorular\n" ) && (bool) preg_match( '#\#\#\# \[[^\]]+\]\([^)]*/kocist/kategoriler/' . preg_quote( $k_parent, '#' ) . '/' . $slug . '/\#sss\)#', $k_llms ), substr( $k_llms, 0, 120 ) );
	check( 'llms Kocist: soru ve tek satir cevap', str_contains( $k_llms, '- Kamelya kaç kişiliktir? Küçük model dört, büyük model sekiz kişiliktir. Ölçüye göre farklı boy da üretilir.' ) && str_contains( $k_llms, '- Kamelya boyanabilir mi?' ) );
	check( 'llms Kocist: sorusuz kategori yok', ! str_contains( $k_llms, '/zz-sss-bos/#sss' ) );
	check( 'llms Kocist: blogdan once, site SSS bolumunden sonra', strpos( $k_llms, '## Kategorilere özel' ) < ( strpos( $k_llms, '## Blog' ) ?: PHP_INT_MAX ) && ( false === strpos( $k_llms, "## Sık sorulan sorular\n" ) || strpos( $k_llms, "## Sık sorulan sorular\n" ) < strpos( $k_llms, '## Kategorilere özel' ) ) );

	$w_llms = page( '/woodkocist/llms.txt' );
	check( 'llms WK: kendi sorulari, cevapsiz yok, Kocist sorusu yok', str_contains( $w_llms, '- Kamelyalar kaç kişilik oluyor?' ) && ! str_contains( $w_llms, 'Cevapsız WK sorusu?' ) && ! str_contains( $w_llms, 'Kamelya boyanabilir mi?' ) && str_contains( $w_llms, '/woodkocist/urun-kategori/' . $w_parent . '/' . $slug . '/#sss' ) );

	foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
		update_blog_option( $blog, 'blog_public', $sites_before[ $blog ]['blog_public'] );
	}

	/* ---------- 9) Geri alma ve kategori silme ---------- */

	$top  = nwcs_history_top();
	$undo = nwcs_history_undo( (string) $top['id'] );
	check( 'geri al (WK): cevapsiz soru yok, 4 soru', ! is_wp_error( $undo ) && 4 === count( nwcs_cat_faq_rows( $slug, $w_key ) ) && 4 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) );

	// Baska yoldan degisen liste: geri alma dokunmaz, kayit duser.
	fresh();
	$save( $w_key, array( 'sss' => rows( array_slice( $w_qa, 0, 2 ) ) ) );
	nwcs_cat_faq_write( $slug, $w_key, array( array( 'q' => 'Elle?', 'a' => 'Elle.' ) ) );
	$top  = nwcs_history_top();
	$undo = nwcs_history_undo( (string) $top['id'] );
	check( 'elle degisen: geri alinmaz, kayit listeden kalkar', is_wp_error( $undo ) && 'nwcs_undo_gone' === $undo->get_error_code() && ( nwcs_history_top()['id'] ?? '' ) !== $top['id'] && array( 'Elle?' ) === questions( $slug, $w_key ) );
	nwcs_cat_faq_write( $slug, $w_key, array_map( static fn( array $pair ): array => array( 'q' => $pair[0], 'a' => $pair[1] ), $w_qa ) );

	$to = post( 'nwcs_handle_category_delete', array( 'action' => 'nwcs_category_delete', 'kategori' => $slug ), 'nwcs_category_delete_' . $slug );
	$faq_keys = array_keys( (array) ( nwcs_history_top()['faq'] ?? array() ) );
	sort( $faq_keys );
	$want_keys = array( $k_key, $w_key );
	sort( $want_keys );
	check( 'kategori silindi: kayitta sorular', 'kategori' === ( nwcs_history_top()['kind'] ?? '' ) && $want_keys === $faq_keys && array() === nwcs_cat_faq_all( $slug ), $to );
	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'kategori geri geldi: sorulari da', ! is_wp_error( $undo ) && 4 === count( nwcs_cat_faq_rows( $slug, $k_key ) ) && 4 === count( nwcs_cat_faq_rows( $slug, $w_key ) ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	$cat = (int) nwcs_pool_categories()[ $slug ]['id'];

	// Butun sorular silinince meta satiri kalkar.
	nwcs_cat_faq_write( $slug, $k_key, array() );
	nwcs_cat_faq_write( $slug, $w_key, array() );
	switch_to_blog( $pool );
	check( 'bos liste: meta satiri silindi', '' === get_term_meta( $cat, NWCS_CAT_FAQ_META, true ) );
	restore_current_blog();
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/** Kocist /sss/ sayfasinin cevapli soru sayisi (sitenin baglaminda). */
function faq_count_k(): array {
	switch_to_blog( K_BLOG );
	$rows = nwcs_faq_rows( 'faq' );
	restore_current_blog();

	return $rows;
}

/* ---------- Temizlik ---------- */

delete_site_transient( 'nwcs_cache_note_' . get_current_user_id() );
delete_site_transient( 'nwcs_history_result_' . get_current_user_id() );
delete_site_transient( NWCS_CAT_FAQ_DRAFT . get_current_user_id() );
update_site_option( NWCS_HISTORY, $history_before );

foreach ( $sites_before as $blog => $options ) {
	foreach ( $options as $option => $value ) {
		if ( null === $value ) {
			delete_blog_option( $blog, $option );
		} else {
			update_blog_option( $blog, $option, $value );
		}
	}
}

switch_to_blog( $pool );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( 'zz-sss-deneme', 'zz-sss-bos' ) as $term_slug ) {
	$term = get_term_by( 'slug', $term_slug, NWCS_PRODUCT_TAX );

	if ( $term ) {
		wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
	}
}
restore_current_blog();
nwcs_pool_flush_cache();

$left = faq_meta_rows();
check( 'temizlik: deneme kategorisi kalmadi, soru meta satiri sayisi ayni', ! get_term_by( 'slug', 'zz-sss-deneme', NWCS_PRODUCT_TAX ) && $meta_before === $left && ! isset( nwcs_cat_faq_map()['zz-sss-deneme'] ), $meta_before . ' -> ' . $left );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
