<?php
/**
 * Yerel deneme: Ürün Havuzu -> Kategoriler -> "Renk grupları".
 *
 * Kendi deneme kategorisini ve urunlerini olusturur, Kocist ve WOOD KOCIST'te
 * gosterir:
 *   A "ZZ Renk Sandalye Antrasit", B "ZZ Renk Sandalye Beyaz",
 *   C "ZZ Renk Sandalye" (adinda renk yok), D "ZZ Renk Masa" (grupta degil),
 *   E baska kategoride (secilemez).
 * Denenenler: yardimcilar (renk kodu, acik renk, kart basligi, renk tahmini),
 * grup olusturma (istek yolu, nonce), hatali istekler (tek urun, baska grupta,
 * kategori disi), kaydetme (renk adi, hazir/ozel renk, baslik, JS'siz ↑ ↓,
 * cikar, ekle, bosalinca silinme), silme, "Son işlemler" ve geri alma,
 * zorunlu renk adi (kayit yok, hata kodu soyler, yazilanlar geri gelir;
 * olusturmada bos ad uyarisi; adsiz renk sitede sayilmaz), gecmiste birlesme
 * (ayni kullanici ve grup, 30 dakika; olusturma ve silme ayri; eski hale donen
 * kayit duser; tek geri alma oturumun basina doner), "Öne çıkan" yalnizca 1. satirda,
 * sadelestirme (tek kart, renk sayisi, kart basligi, one cikan gizliyse
 * ikinci uye, tek uye kalinca grup yok sayilir, site basina adres), menu
 * sayilari, coptaki ve kategoriden cikan uye, panel ekrani ve urun formu
 * satiri, kategori silme + geri alma (gruplar geri gelir). Sonda sitelerin
 * sayfalari HTTP ile cizilir: kategori sayfasinda tek kart ve "3 renk",
 * urun sayfasinda uc renk baglantisi, aria-current, JSON-LD (color,
 * ProductGroup) ve canonical; WOOD KOCIST aramasi her rengi ayri bulur.
 * Sonunda her seyi siler, site ayarlarini ve "Son işlemler"i eski haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-color-groups.php
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

// Yanlis nonce wp_die ile biter: betik durmasin, istisna olsun.
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

wp_set_current_user( get_user_by( 'login', get_super_admins()[0] )->ID );

const K_BLOG = 8;  // Kocist
const W_BLOG = 13; // WOOD KOCIST

$history_before = get_site_option( NWCS_HISTORY, array() );
$pool           = nwcs_pool_blog_id();
$sites_before   = array();

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	foreach ( array( NWCS_OPTION_MODE, NWCS_OPTION_SELECTED, NWCS_OPTION_OVERRIDES ) as $option ) {
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

/** Sayfadaki JSON-LD @graph dugumleri. */
function graph( string $html ): array {
	preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $match );
	$data = $match ? json_decode( $match[1], true ) : null;

	return is_array( $data['@graph'] ?? null ) ? $data['@graph'] : array();
}

/** En ustteki gecmis kaydini birlesme suresinden eski yapar: sonraki kayit ona eklenmez, yeni kayit acar. */
function fresh(): void {
	$list = nwcs_history_all();

	if ( $list ) {
		$list[0]['time'] = time() - NWCS_COLOR_MERGE - 60;
		update_site_option( NWCS_HISTORY, $list );
	}
}

/** Kategorinin renk bolumunun HTML'i. */
function section_html( string $slug, array $get = array() ): string {
	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug ) + $get;
	ob_start();
	nwcs_render_color_groups( $slug, nwcs_sync_category_products( $slug ) );
	$_GET = array();

	return (string) ob_get_clean();
}

/** Site urunlerini (sadelestirilmis) kimlige gore. */
function cards( int $blog ): array {
	$out = array();

	foreach ( nwcs_color_collapse( nwcs_site_products( $blog ), $blog ) as $row ) {
		$out[ (int) $row['id'] ] = $row;
	}

	return $out;
}

/** Bir sitenin istisnasini yazar (gizle, adres) ve onbellegi temizler. */
function override( int $blog, int $id, array $row ): void {
	$overrides = get_blog_option( $blog, NWCS_OPTION_OVERRIDES, array() );
	$overrides = is_array( $overrides ) ? $overrides : array();

	if ( $row ) {
		$overrides[ $id ] = $row + (array) ( $overrides[ $id ] ?? array() );
	} else {
		unset( $overrides[ $id ] );
	}

	update_blog_option( $blog, NWCS_OPTION_OVERRIDES, $overrides );
	nwcs_pool_flush_cache();
}

/* ---------- Deneme verisi ---------- */

switch_to_blog( $pool );

$cat   = wp_insert_term( 'ZZ Renk Deneme', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-renk-deneme' ) );
$cat   = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
$other = wp_insert_term( 'ZZ Renk Diger', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-renk-diger' ) );
$other = is_wp_error( $other ) ? (int) $other->get_error_data() : (int) $other['term_id'];

$ids = array();
foreach (
	array(
		'a' => array( 'ZZ Renk Sandalye Antrasit', 'ZZRNK-A', $cat ),
		'b' => array( 'ZZ Renk Sandalye Beyaz', 'ZZRNK-B', $cat ),
		'c' => array( 'ZZ Renk Sandalye', 'ZZRNK-C', $cat ),
		'd' => array( 'ZZ Renk Masa', 'ZZRNK-D', $cat ),
		'e' => array( 'ZZ Renk Dolap Siyah', 'ZZRNK-E', $other ),
	) as $key => $row
) {
	$ids[ $key ] = wp_insert_post(
		array(
			'post_type'   => NWCS_PRODUCT_TYPE,
			'post_status' => 'publish',
			'post_title'  => $row[0],
			'menu_order'  => count( $ids ) + 1,
		)
	);
	update_post_meta( $ids[ $key ], '_nwcs_code', $row[1] );
	update_post_meta( $ids[ $key ], '_nwcs_price', '1000' );
	wp_set_object_terms( $ids[ $key ], array( $row[2] ), NWCS_PRODUCT_TAX );
}

restore_current_blog();
nwcs_pool_flush_cache();

$slug     = 'zz-renk-deneme';
$k_parent = isset( nwcs_site_parents( K_BLOG )['kereste'] ) ? 'kereste' : (string) array_key_first( nwcs_site_parents( K_BLOG ) );
$w_parent = (string) array_key_first( nwcs_site_parents( W_BLOG ) );

nwcs_set_placement( $slug, nwcs_catalog_site_key( K_BLOG ), $k_parent );
nwcs_set_placement( $slug, nwcs_catalog_site_key( W_BLOG ), $w_parent );

foreach ( array( K_BLOG, W_BLOG ) as $blog ) {
	update_blog_option( $blog, NWCS_OPTION_MODE, 'selected' );
	nwcs_selection_append( $blog, array_values( $ids ) );
}

nwcs_pool_flush_cache();

[ 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e ] = $ids;

$create = static fn( array $data ): string => post( 'nwcs_handle_color_create', $data + array( 'action' => 'nwcs_color_create', 'kategori' => 'zz-renk-deneme' ), 'nwcs_color_create_zz-renk-deneme' );
$save   = static fn( array $data ): string => post( 'nwcs_handle_color_save', $data + array( 'action' => 'nwcs_color_save', 'kategori' => 'zz-renk-deneme' ), 'nwcs_color_save_zz-renk-deneme' );

try {
	/* ---------- 1) Yardimcilar ---------- */

	check( 'hex: #ABC -> #aabbcc', '#aabbcc' === nwcs_color_hex( '#ABC' ) );
	check( 'hex: gecersiz bos', '' === nwcs_color_hex( 'kirmizi' ) && '' === nwcs_color_hex( '#12345' ) );
	check( 'acik renk: beyaz evet, antrasit hayir', nwcs_color_is_light( '#ffffff' ) && ! nwcs_color_is_light( '#383e42' ) );
	check( 'kart basligi: renk adda varsa eklenmez', 'ZZ Renk Sandalye Antrasit' === nwcs_color_card_title( 'ZZ Renk Sandalye Antrasit', 'antrasit' ) );
	check( 'kart basligi: Turkce harf farki yok', 'Kedi Yuvası DOGAL CAM' === nwcs_color_card_title( 'Kedi Yuvası DOGAL CAM', 'Doğal Çam' ) );
	check( 'kart basligi: renk yoksa " – renk"', 'ZZ Renk Sandalye – Teak' === nwcs_color_card_title( 'ZZ Renk Sandalye', 'Teak' ) );
	check( 'kart basligi: parantezli rengin bir parcasi adda varsa eklenmez', 'Kedi Yuvası Amber' === nwcs_color_card_title( 'Kedi Yuvası Amber', 'Amber (Açık Meşe)' ) && 'Kedi Yuvası Acik Mese' === nwcs_color_card_title( 'Kedi Yuvası Acik Mese', 'Amber (Açık Meşe)' ) );
	check( 'kart basligi: parantezli renk adda yoksa tamami eklenir', 'Kedi Yuvası – Amber (Açık Meşe)' === nwcs_color_card_title( 'Kedi Yuvası', 'Amber (Açık Meşe)' ) );
	check( 'tahmin: Antrasit', 'Antrasit' === nwcs_color_guess( 'Sandalye ANTRASİT' )['name'] );
	check( 'tahmin: Açık Meşe -> Amber (Açık Meşe)', 'Amber (Açık Meşe)' === nwcs_color_guess( 'Açık Meşe Masa' )['name'] );
	check( 'tahmin: kelime parcasi eslesmez', '' === nwcs_color_guess( 'Grimsi Sandalye' )['name'] );
	check( 'temizlik: ayni urun iki grupta -> ikinciden duser, bos grup atilir', 1 === count( nwcs_color_clean( array( array( 'id' => 'x', 'members' => array( array( 'product_id' => 5 ) ) ), array( 'id' => 'y', 'members' => array( array( 'product_id' => 5 ) ) ) ) ) ) );

	/* ---------- 2) Olusturma ---------- */

	$to = $create( array( 'urunler' => array( $a ), 'baslik' => 'Renk' ) );
	check( 'olustur: tek urun -> az', str_contains( $to, 'renk=az' ) && ! nwcs_color_groups( $slug ), $to );

	$to = $create( array( 'urunler' => array( $a, $e ), 'baslik' => 'Renk' ) );
	check( 'olustur: kategori disi urun -> disarida', str_contains( $to, 'renk=disarida' ) && ! nwcs_color_groups( $slug ), $to );

	$to = $create( array( 'urunler' => array( $c, $a, $b ), 'baslik' => 'Renk' ) );
	$groups = nwcs_color_groups( $slug );
	$gid    = (string) ( $groups[0]['id'] ?? '' );
	check( 'olustur: olustu, grubun kartina doner', str_contains( $to, 'renk=olustu' ) && str_contains( $to, '#nwcs-renk-' . $gid ), $to );
	check( 'olustur: uyeler liste sirasiyla (A, B, C)', array( $a, $b, $c ) === array_column( $groups[0]['members'] ?? array(), 'product_id' ) );
	check( 'olustur: ad ve yuvarlak tahmin edildi', 'Antrasit' === $groups[0]['members'][0]['color_name'] && '#ffffff' === $groups[0]['members'][1]['color_hex'] && '' === $groups[0]['members'][2]['color_name'] );
	check( 'olustur: baslik Renk', 'Renk' === $groups[0]['label'] );

	$top = nwcs_history_top();
	check( 'gecmis: renk kaydi', NWCS_COLOR_KIND === ( $top['kind'] ?? '' ) && $slug === $top['slug'] && array() === $top['before'] );
	$info = nwcs_history_describe( $top );
	check( 'gecmis: ozet', 'renk grubu' === $info['kind'] && str_contains( $info['facts'], 'oluşturuldu: 3 ürün' ), $info['kind'] . ': ' . $info['facts'] );

	$to = $create( array( 'urunler' => array( $b, $d ), 'baslik' => 'Renk' ) );
	check( 'olustur: baska gruptaki urun -> dolu', str_contains( $to, 'renk=dolu' ) && str_contains( $to, 'urun=' . $b ) && 1 === count( nwcs_color_groups( $slug ) ), $to );

	check( 'harita: B grubun 2. uyesi', 1 === ( nwcs_color_group_of( $b )['position'] ?? -1 ) && $slug . '/' . $gid === nwcs_color_group_of( $b )['key'] );
	check( 'harita: D grupta degil', null === nwcs_color_group_of( $d ) );
	check( 'uye kimlikleri', array( $a, $b, $c ) === nwcs_color_member_ids( $b ) && array( $d ) === nwcs_color_member_ids( $d ) );
	check( 'gecmis: olusturma kaydi kendi kaydi (action, grup, kullanici)', 'create' === ( $top['action'] ?? '' ) && $gid === ( $top['group'] ?? '' ) && get_current_user_id() === (int) ( $top['user'] ?? 0 ) );

	// Adi tahmin edilemeyen C: alan bos, grup karti uyarir, sitede C renk sayilmaz.
	$html = section_html( $slug );
	check( 'ad bos (olusturma): "Renk adı boş olan ürünler: ZZRNK-C"', str_contains( $html, 'Renk adı boş olan ürünler: ZZRNK-C.' ) );
	check( 'one cikan: ilk satirda, digerlerinde hidden', 1 === substr_count( $html, 'data-nwcs-color-lead>' ) && 2 === substr_count( $html, 'data-nwcs-color-lead hidden>' ) );
	$k = cards( K_BLOG );
	check( 'ad bos: sitede 2 renk, C kendi karti', 2 === ( $k[ $a ]['color_count'] ?? 0 ) && isset( $k[ $c ] ) && ! isset( $k[ $c ]['colors'] ) && ! isset( $k[ $b ] ) );
	check( 'ad bos: dugmelerde C yok, C sayfasinda secenek yok', array( 'Antrasit', 'Beyaz' ) === array_column( nwcs_color_options( $a, K_BLOG )['colors'] ?? array(), 'name' ) && array() === nwcs_color_options( $c, K_BLOG ) );

	/* ---------- 3) Kaydetme, renk adi zorunlu, gecmiste birlesme ---------- */

	$create_id = (string) nwcs_history_top()['id'];
	$named     = array(
		$a => array( 'ad' => 'Antrasit', 'hazir' => 'Antrasit' ),
		$b => array( 'ad' => 'Beyaz', 'hazir' => 'Beyaz' ),
		$c => array( 'ad' => 'Teak', 'hazir' => 'Teak' ),
	);

	// Renk adi bos: hicbir sey yazilmaz, hata kodu soyler, yazilanlar geri gelir.
	$to = $save( array( 'grup' => $gid, 'baslik' => 'Taslak başlık', 'sira' => array( $b, $a, $c ), 'uye' => array( $c => array( 'ad' => '', 'hazir' => '' ) ) + $named ) );
	check( 'ad bos (kaydet): kaydedilmez, adsiz', str_contains( $to, 'renk=adsiz' ) && 'Renk' === nwcs_color_groups( $slug )[0]['label'] && array( $a, $b, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ) && $create_id === nwcs_history_top()['id'], $to );
	$html = section_html( $slug, array( 'renk' => 'adsiz' ) );
	check( 'ad bos (kaydet): "Kaydedilmedi: ZZRNK-C için renk adı yazın."', str_contains( $html, 'Kaydedilmedi: ZZRNK-C için renk adı yazın.' ) && 1 === substr_count( $html, 'aria-invalid="true"' ) && ! str_contains( $html, 'renk adı boş olan ürün var' ) );
	check( 'ad bos (kaydet): yazilanlar geri geldi (baslik, sira)', str_contains( $html, 'value="Taslak başlık"' ) && strpos( $html, 'value="' . $b . ':up"' ) < strpos( $html, 'value="' . $a . ':up"' ) );
	check( 'ad bos (kaydet): taslak bir kez', false === get_site_transient( NWCS_COLOR_DRAFT . get_current_user_id() ) && ! str_contains( section_html( $slug ), 'Taslak başlık' ) );

	$to = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $b, $c ), 'uye' => array( $a => array( 'ad' => '', 'hazir' => '' ), $c => array( 'ad' => '', 'hazir' => '' ) ) + $named ) );
	check( 'ad bos (iki urun): "ZZRNK-A ve ZZRNK-C için"', str_contains( $to, 'renk=adsiz' ) && str_contains( section_html( $slug ), 'Kaydedilmedi: ZZRNK-A ve ZZRNK-C için renk adı yazın.' ) );

	// Gruptan cikarilan satirin adi aranmaz.
	$to = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $b, $c ), 'uye' => array( $c => array( 'ad' => '', 'hazir' => '' ) ) + $named, 'cikar' => $c ) );
	check( 'ad bos ama cikariliyor: kaydedilir', str_contains( $to, 'renk=cikarildi' ) && array( $a, $b ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ), $to );
	nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: C geri, olusturma kaydi ustte', array( $a, $b, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ) && $create_id === nwcs_history_top()['id'] );

	// Ilk kayit: olusturma kaydina eklenmez, yeni kayit acar.
	$to = $save(
		array(
			'grup'   => $gid,
			'baslik' => 'Gövde rengi',
			'sira'   => array( $a, $b, $c ),
			'uye'    => array(
				$a => array( 'ad' => 'Antrasit', 'hazir' => 'ozel', 'hex' => '#123456' ),
				$b => array( 'ad' => 'Beyaz', 'hazir' => '', 'hex' => '#ffffff' ),
				$c => array( 'ad' => '', 'hazir' => 'Teak', 'hex' => '#ffffff' ),
			),
			'kaydet' => 1,
		)
	);
	$members   = nwcs_color_groups( $slug )[0]['members'];
	$saved_top = (string) nwcs_history_top()['id'];
	check( 'kaydet: kaydedildi', str_contains( $to, 'renk=kaydedildi' ), $to );
	check( 'kaydet: baslik', 'Gövde rengi' === nwcs_color_groups( $slug )[0]['label'] );
	check( 'kaydet: ozel renk', '#123456' === $members[0]['color_hex'] );
	check( 'kaydet: yuvarlak secilmedi, ad hazir renk -> onun rengi', '#ffffff' === $members[1]['color_hex'] );
	check( 'kaydet: hazir renk, ad bos -> ad yazilir (zorunluluk gecer)', 'Teak' === $members[2]['color_name'] && '#a3683b' === $members[2]['color_hex'] );
	check( 'kaydet: yeni kayit, olusturma kaydi altta', $saved_top !== $create_id && $create_id === ( nwcs_history_all()[1]['id'] ?? '' ) && 'save' === nwcs_history_top()['action'] && str_contains( nwcs_history_describe( nwcs_history_top() )['facts'], 'başlık “Gövde rengi” oldu' ) && str_contains( nwcs_history_describe( nwcs_history_top() )['facts'], '2 ürünün rengi değişti' ), nwcs_history_describe( nwcs_history_top() )['facts'] );

	$govde = array( $a => array( 'ad' => 'Antrasit', 'hazir' => 'ozel', 'hex' => '#123456' ) ) + $named;
	$to    = $save( array( 'grup' => $gid, 'baslik' => 'Gövde rengi', 'sira' => array( $a, $b, $c ), 'uye' => $govde ) );
	check( 'kaydet: degisiklik yoksa ayni, gecmis yazilmaz', str_contains( $to, 'renk=ayni' ) && nwcs_history_top()['id'] === $saved_top, $to );

	// JS'siz ↑: ayni kayda eklenir (birlesme).
	$to = $save( array( 'grup' => $gid, 'baslik' => 'Gövde rengi', 'sira' => array( $a, $b, $c ), 'uye' => $govde, 'tasi' => $c . ':up' ) );
	$top = nwcs_history_top();
	check( 'birlesme: tasi ayni kayitta (A, C, B)', array( $a, $c, $b ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ) && $saved_top === $top['id'] && 2 === (int) $top['edits'] && str_contains( implode( ', ', $top['facts'] ), 'sıra değişti' ), implode( ', ', $top['facts'] ) );
	check( 'birlesme: "onceki" ilk duzenlemeden onceki hal', 'Renk' === ( $top['before'][0]['label'] ?? '' ) && '' === ( $top['before'][0]['members'][2]['color_name'] ?? 'x' ) );

	// JS ile tasinan sira: B one, yine ayni kayit.
	$save( array( 'grup' => $gid, 'baslik' => 'Gövde rengi', 'sira' => array( $b, $a, $c ), 'uye' => $govde ) );
	$top = nwcs_history_top();
	check( 'birlesme: 3 duzenleme tek kayit, ozet oturumun basina gore', $saved_top === $top['id'] && str_contains( nwcs_history_describe( $top )['facts'], 'öne çıkan: ZZ Renk Sandalye Beyaz' ) && str_contains( nwcs_history_describe( $top )['facts'], 'başlık “Gövde rengi” oldu' ) && str_contains( nwcs_history_describe( $top )['facts'], '3 düzenleme' ), nwcs_history_describe( $top )['facts'] );

	// Tek geri alma oturumun basina (olusturma sonrasi hal) doner.
	$undo = nwcs_history_undo( $saved_top );
	$g    = nwcs_color_groups( $slug )[0];
	check( 'geri al (birlesmis): olusturma sonrasi hal', ! is_wp_error( $undo ) && 'Renk' === $g['label'] && array( $a, $b, $c ) === array_column( $g['members'], 'product_id' ) && '' === $g['members'][2]['color_name'] && $create_id === nwcs_history_top()['id'], is_wp_error( $undo ) ? $undo->get_error_message() : '' );

	// Sonraki adimlar icin: baslik "Renk", adlar dolu (A hazir Antrasit).
	$to    = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $b, $c ), 'uye' => $named ) );
	$r_id  = (string) nwcs_history_top()['id'];
	check( 'kaydet: adlar dolu, yeni kayit', str_contains( $to, 'renk=kaydedildi' ) && 'Teak' === nwcs_color_groups( $slug )[0]['members'][2]['color_name'] && $r_id !== $create_id );

	// Birlesme suresi gecti: yeni kayit. Geri donen birlesme kaydi duser.
	fresh();
	$save( array( 'grup' => $gid, 'baslik' => 'Geçici', 'sira' => array( $a, $b, $c ), 'uye' => $named ) );
	$tmp_id = (string) nwcs_history_top()['id'];
	check( 'birlesme: 30 dakikadan eski kayda eklenmez', $tmp_id !== $r_id && $r_id === ( nwcs_history_all()[1]['id'] ?? '' ) );
	$save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $b, $c ), 'uye' => $named ) );
	check( 'birlesme: eski hale donen kayit duser', $r_id === nwcs_history_top()['id'] && 'Renk' === nwcs_color_groups( $slug )[0]['label'] );

	// Baska kullanicinin kaydina eklenmez.
	$list            = nwcs_history_all();
	$list[0]['time'] = time();
	$list[0]['user'] = 999999;
	update_site_option( NWCS_HISTORY, $list );
	$save( array( 'grup' => $gid, 'baslik' => 'Geçici', 'sira' => array( $a, $b, $c ), 'uye' => $named ) );
	check( 'birlesme: baska kullanicinin kaydina eklenmez', $r_id !== nwcs_history_top()['id'] );
	nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: baslik Renk', 'Renk' === nwcs_color_groups( $slug )[0]['label'] && $r_id === nwcs_history_top()['id'] );
	fresh();

	/* ---------- 4) Sitede: sadelestirme ---------- */

	$k = cards( K_BLOG );
	check( 'Kocist: A kart, B ve C listede yok, D kendi karti', isset( $k[ $a ], $k[ $d ] ) && ! isset( $k[ $b ] ) && ! isset( $k[ $c ] ) );
	check( 'Kocist: 3 renk, etiket, kendi rengi', 3 === ( $k[ $a ]['color_count'] ?? 0 ) && 'Renk' === $k[ $a ]['color_label'] && 'Antrasit' === $k[ $a ]['color_name'] );
	check( 'Kocist: kart basligi (adda renk var) ayni', 'ZZ Renk Sandalye Antrasit' === $k[ $a ]['card_title'] );
	check( 'Kocist: renkler sirayla, A guncel', array( 'Antrasit', 'Beyaz', 'Teak' ) === array_column( $k[ $a ]['colors'], 'name' ) && true === $k[ $a ]['colors'][0]['current'] && false === $k[ $a ]['colors'][1]['current'] );
	check( 'Kocist: beyaz acik renk', true === $k[ $a ]['colors'][1]['light'] && false === $k[ $a ]['colors'][0]['light'] );
	check( 'Kocist: D renk bilgisi almaz', ! isset( $k[ $d ]['colors'] ) );

	// Liste sirasi B, D, A, C: one cikan A, grubun listedeki ilk yerine (B'nin yeri) oturur.
	$rows = array();
	foreach ( nwcs_site_products( K_BLOG ) as $row ) {
		$rows[ (int) $row['id'] ] = $row;
	}
	$list = array_column( nwcs_color_collapse( array( $rows[ $b ], $rows[ $d ], $rows[ $a ], $rows[ $c ] ), K_BLOG ), 'id' );
	check( 'Kocist: kart grubun listedeki ilk yerinde, one cikan A', array( $a, $d ) === $list, implode( ',', $list ) );
	$list = array_column( nwcs_color_collapse( array( $rows[ $d ], $rows[ $c ] ), K_BLOG ), 'id' );
	check( 'Kocist: listede yalnizca C varsa C kart (renkler site geneli)', array( $d, $c ) === $list && 3 === ( nwcs_color_collapse( array( $rows[ $c ] ), K_BLOG )[0]['color_count'] ?? 0 ) );
	check( 'Kocist: kategori sayisi 2 (renk grubu + D)', 2 === (int) ( nwcs_site_category_tree( K_BLOG )[ $k_parent ]['children'][ $slug ]['count'] ?? -1 ), (string) ( nwcs_site_category_tree( K_BLOG )[ $k_parent ]['children'][ $slug ]['count'] ?? 'yok' ) );

	$options = nwcs_color_options( $b, K_BLOG );
	check( 'urun sayfasi: B icin 3 renk, B guncel', 3 === count( $options['colors'] ?? array() ) && 'Beyaz' === $options['name'] && true === $options['colors'][1]['current'] );
	check( 'urun sayfasi: adresler bu sitenin', nwcs_product_url( 'zz-renk-sandalye', K_BLOG ) === $options['colors'][2]['url'] );
	check( 'urun sayfasi: grup kimligi sabit', 'RENK-' . strtoupper( $gid ) === $options['code'] );
	check( 'urun sayfasi: grupta olmayan D bos', array() === nwcs_color_options( $d, K_BLOG ) );

	// Site basina adres: C'nin Kocist'teki adresi ozel.
	override( K_BLOG, $c, array( 'slug' => 'zz-renk-ozel-teak' ) );
	$options = nwcs_color_options( $a, K_BLOG );
	check( 'site basina adres: C dugmesi ozel adrese gider', nwcs_product_url( 'zz-renk-ozel-teak', K_BLOG ) === ( $options['colors'][2]['url'] ?? '' ), (string) ( $options['colors'][2]['url'] ?? '' ) );
	check( 'site basina adres: WOOD KOCIST havuz adresinde', nwcs_product_url( 'zz-renk-sandalye', W_BLOG ) === ( nwcs_color_options( $a, W_BLOG )['colors'][2]['url'] ?? '' ) );

	// One cikan WOOD KOCIST'te gizli: ikinci uye one cikar.
	override( W_BLOG, $a, array( 'hidden' => 1 ) );
	$w = cards( W_BLOG );
	check( 'gizli one cikan: WK\'da B kart, 2 renk', isset( $w[ $b ] ) && ! isset( $w[ $a ] ) && ! isset( $w[ $c ] ) && 2 === ( $w[ $b ]['color_count'] ?? 0 ) );
	check( 'gizli one cikan: WK kart basligi B', 'ZZ Renk Sandalye Beyaz' === $w[ $b ]['card_title'] );
	check( 'gizli one cikan: Kocist\'te hala A', isset( cards( K_BLOG )[ $a ] ) && 3 === cards( K_BLOG )[ $a ]['color_count'] );
	check( 'gizli one cikan: A\'nin WK sayfasi yok, secenek bos', array() === nwcs_color_options( $a, W_BLOG ) );

	// Tek uye kalinca grup yok sayilir.
	override( W_BLOG, $b, array( 'hidden' => 1 ) );
	$w = cards( W_BLOG );
	check( 'tek gorunen: C normal kart, renk bilgisi yok', isset( $w[ $c ] ) && ! isset( $w[ $c ]['colors'] ) && array() === nwcs_color_options( $c, W_BLOG ) );
	check( 'tek gorunen: C kart basligi eklenmez', ! isset( $w[ $c ]['card_title'] ) );
	override( W_BLOG, $a, array() );
	override( W_BLOG, $b, array() );

	// Kart basligi: adinda renk olmayan one cikan (C one alinir, sonra geri alinir).
	override( K_BLOG, $c, array() );
	fresh();
	$save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $c, $a, $b ) ) );
	$k = cards( K_BLOG );
	check( 'kart basligi: C one cikan -> "ZZ Renk Sandalye – Teak"', 'ZZ Renk Sandalye – Teak' === ( $k[ $c ]['card_title'] ?? '' ), (string) ( $k[ $c ]['card_title'] ?? 'yok' ) );
	nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: A yine one cikan', $a === nwcs_color_groups( $slug )[0]['members'][0]['product_id'] );

	/* ---------- 5) Gecersiz uyeler ---------- */

	switch_to_blog( $pool );
	wp_trash_post( $c );
	restore_current_blog();
	nwcs_pool_flush_cache();
	check( 'cop: C durumu trash', 'trash' === nwcs_color_member_state( $c, $slug ) );
	check( 'cop: Kocist\'te 2 renk', 2 === ( cards( K_BLOG )[ $a ]['color_count'] ?? 0 ) );

	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug );
	ob_start();
	nwcs_render_color_groups( $slug, nwcs_sync_category_products( $slug ) );
	$html = ob_get_clean();
	check( 'ekran: copteki uye uyariyla', str_contains( $html, 'Çöp kutusunda: sitede gösterilmiyor' ) && str_contains( $html, 'ZZ Renk Sandalye' ) );

	switch_to_blog( $pool );
	wp_untrash_post( $c );
	wp_publish_post( $c );
	wp_set_object_terms( $b, array( $other ), NWCS_PRODUCT_TAX );
	restore_current_blog();
	nwcs_pool_flush_cache();
	check( 'kategoriden cikan: B durumu moved, sitede sayilmaz', 'moved' === nwcs_color_member_state( $b, $slug ) && 2 === ( cards( K_BLOG )[ $a ]['color_count'] ?? 0 ) && isset( cards( K_BLOG )[ $b ] ) );
	switch_to_blog( $pool );
	wp_set_object_terms( $b, array( $cat ), NWCS_PRODUCT_TAX );
	restore_current_blog();
	nwcs_pool_flush_cache();
	check( 'geri: 3 renk', 3 === ( cards( K_BLOG )[ $a ]['color_count'] ?? 0 ) );

	/* ---------- 6) Panel ekrani ve urun formu ---------- */

	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug );
	ob_start();
	nwcs_render_category_detail( $slug, nwcs_pool_categories()[ $slug ] );
	$html = ob_get_clean();
	check( 'ekran: bolum fotograf kutusundan sonra, urunlerden once', strpos( $html, 'id="nwcs-fotograf"' ) < strpos( $html, 'id="nwcs-renk"' ) && strpos( $html, 'id="nwcs-renk"' ) < strpos( $html, 'id="nwcs-sec-products"' ) );
	check( 'ekran: 3 uye satiri, one cikan etiketi', 3 === substr_count( $html, 'data-nwcs-color-row data-nwcs-color-code=' ) && 1 === substr_count( $html, 'data-nwcs-color-lead>' ) );
	check( 'ekran: Gruptan cikar, ↑ ↓, hazir ve ozel renk', str_contains( $html, 'Gruptan çıkar' ) && str_contains( $html, 'value="' . $b . ':up"' ) && str_contains( $html, 'Kiremit Kırmızı' ) && str_contains( $html, 'type="color"' ) );
	check( 'ekran: urunler tablosunda rozet', 3 === substr_count( $html, 'renk grubu: Renk' ) );
	check( 'ekran: secilebilir urun kalmayinca olusturma formu yerine not', str_contains( $html, 'gruba alınabilecek ürün kalmadı' ) );
	ob_start();
	nwcs_render_color_picker( nwcs_color_choices( $slug, nwcs_sync_category_products( $slug ) ), 'urunler[]', 'zz' );
	$picker = ob_get_clean();
	check( 'secim listesi: gruptakiler secilemez, nedeni yazar', 3 === substr_count( $picker, "disabled='disabled'" ) && str_contains( $picker, 'Başka bir renk grubunda: ZZ Renk Deneme › “Renk” (ZZ Renk Sandalye Antrasit)' ) && str_contains( $picker, '1 ürün seçilebilir.' ), wp_strip_all_tags( $picker ) );
	check( 'ekran: sitelerdeki durum', str_contains( $html, '3 renk' ) );
	check( 'ekran: adlar dolu, bos ad uyarisi yok', ! str_contains( $html, 'Renk adı boş olan' ) );

	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug, 'renk_sil' => $gid );
	ob_start();
	nwcs_render_color_groups( $slug, nwcs_sync_category_products( $slug ) );
	$html = ob_get_clean();
	check( 'ekran: silme onayi', str_contains( $html, '“Renk” grubu silinsin mi?' ) && str_contains( $html, 'nwcs_color_delete' ) );
	$_GET = array();

	ob_start();
	nwcs_render_product_color_line( nwcs_pool_products()[ $b ] );
	$html = ob_get_clean();
	check( 'urun formu: "Renk grubu: Renk · 3 renk · Düzenle ↗"', str_contains( $html, 'Renk grubu: <strong>Renk</strong>' ) && str_contains( $html, '3 renk' ) && str_contains( $html, '#nwcs-renk-' . $gid ) && str_contains( $html, 'Düzenle' ) );

	/* ---------- 7) Sitelerin sayfalari (HTTP) ---------- */

	$k_cat = page( '/kocist/kategoriler/' . $k_parent . '/' . $slug . '/' );
	check( 'Kocist kategori: tek kart ve "3 renk"', 1 === substr_count( $k_cat, '>3 renk<' ) && str_contains( $k_cat, 'ZZ Renk Sandalye Antrasit' ) && ! str_contains( $k_cat, 'ZZ Renk Sandalye Beyaz' ) && str_contains( $k_cat, 'ZZ Renk Masa' ), substr( wp_strip_all_tags( $k_cat ), 0, 80 ) );
	check( 'Kocist kategori: kartta renk yuvarlagi yok', ! str_contains( $k_cat, 'k-swatch' ) );

	$k_page = page( '/kocist/urun/zz-renk-sandalye-beyaz/' );
	check( 'Kocist urun: "Renk: Beyaz" ve 3 renk baglantisi', str_contains( $k_page, 'Renk: <strong>Beyaz</strong>' ) && 3 === substr_count( $k_page, 'class="k-swatch__dot' ) );
	check( 'Kocist urun: guncel renk aria-current', 1 === preg_match_all( '#k-swatch__dot is-current is-light" href="[^"]*zz-renk-sandalye-beyaz/"[^>]*aria-current="page"#', $k_page ) );
	check( 'Kocist urun: baglantilar dogru sayfalara', str_contains( $k_page, 'href="http://localhost:8080/kocist/urun/zz-renk-sandalye-antrasit/"' ) && str_contains( $k_page, 'href="http://localhost:8080/kocist/urun/zz-renk-sandalye/"' ) );
	check( 'Kocist urun: canonical kendi adresi', str_contains( $k_page, '<link rel="canonical" href="http://localhost:8080/kocist/urun/zz-renk-sandalye-beyaz/" />' ) );

	$nodes   = graph( $k_page );
	$product = current( array_filter( $nodes, static fn( array $node ): bool => 'Product' === ( $node['@type'] ?? '' ) ) ) ?: array();
	$group   = current( array_filter( $nodes, static fn( array $node ): bool => 'ProductGroup' === ( $node['@type'] ?? '' ) ) ) ?: array();
	check( 'JSON-LD: Product color ve inProductGroupWithID', 'Beyaz' === ( $product['color'] ?? '' ) && 'RENK-' . strtoupper( $gid ) === ( $product['inProductGroupWithID'] ?? '' ) && ( $group['@id'] ?? 'x' ) === ( $product['isVariantOf']['@id'] ?? 'y' ) );
	check( 'JSON-LD: ProductGroup variesBy ve 3 varyant', 'https://schema.org/color' === ( $group['variesBy'] ?? '' ) && 'RENK-' . strtoupper( $gid ) === ( $group['productGroupID'] ?? '' ) && 3 === count( (array) ( $group['hasVariant'] ?? array() ) ) );
	check( 'JSON-LD: varyant adresleri ve renkleri', array( 'Antrasit', 'Beyaz', 'Teak' ) === array_column( (array) ( $group['hasVariant'] ?? array() ), 'color' ) && 'http://localhost:8080/kocist/urun/zz-renk-sandalye/' === ( $group['hasVariant'][2]['url'] ?? '' ) );

	$d_page = page( '/kocist/urun/zz-renk-masa/' );
	check( 'grupta olmayan urun: dugme ve ProductGroup yok', '' !== $d_page && ! str_contains( $d_page, 'k-swatch' ) && ! str_contains( $d_page, 'ProductGroup' ) );

	$w_cat = page( '/woodkocist/urun-kategori/' . $w_parent . '/' . $slug . '/' );
	check( 'WK kategori: tek kart ve "3 renk"', 1 === substr_count( $w_cat, '>3 renk<' ) && str_contains( $w_cat, 'ZZ Renk Sandalye Antrasit' ) && ! str_contains( $w_cat, 'ZZ Renk Sandalye Beyaz' ), substr( wp_strip_all_tags( $w_cat ), 0, 80 ) );
	check( 'WK kategori: sayi 2 urun', str_contains( $w_cat, '2 ürün' ) );

	$w_search = page( '/woodkocist/magaza/?ara=ZZRNK' );
	check( 'WK arama: her renk ayri (A, B, C, D)', str_contains( $w_search, 'ZZ Renk Sandalye Antrasit' ) && str_contains( $w_search, 'ZZ Renk Sandalye Beyaz' ) && str_contains( $w_search, '>ZZ Renk Sandalye<' ) && ! str_contains( $w_search, '>3 renk<' ) );

	$w_page = page( '/woodkocist/urun/zz-renk-sandalye/' );
	check( 'WK urun: "Renk: Teak", 3 dugme, Teak guncel', str_contains( $w_page, 'Renk: <strong>Teak</strong>' ) && 3 === substr_count( $w_page, 'class="wk-swatch__dot' ) && 1 === preg_match_all( '#wk-swatch__dot is-current" href="[^"]*/zz-renk-sandalye/"[^>]*aria-current="page"#', $w_page ) );
	check( 'WK urun: ilgili urunlerde ayni grubun renkleri yok', ! preg_match( '#wk-related.*ZZ Renk Sandalye Antrasit#s', $w_page ) );

	/* ---------- 8) Ekle, cikar, bosalinca silinme, silme ---------- */

	fresh();
	$to     = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $b, $c ), 'cikar' => $b ) );
	$cut_id = (string) nwcs_history_top()['id'];
	check( 'cikar: B gruptan cikti, sitede ayri kart', str_contains( $to, 'renk=cikarildi' ) && array( $a, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ) && isset( cards( K_BLOG )[ $b ] ) && str_contains( implode( ', ', nwcs_history_top()['facts'] ), '“ZZ Renk Sandalye Beyaz” gruptan çıkarıldı' ) );

	$to = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $c ), 'ekle_gonder' => 1, 'ekle' => array() ) );
	check( 'ekle: bos secim -> secilmedi', str_contains( $to, 'renk=secilmedi' ), $to );

	$to = $save( array( 'grup' => $gid, 'baslik' => 'Renk', 'sira' => array( $a, $c ), 'ekle_gonder' => 1, 'ekle' => array( $b, $d ) ) );
	$members = nwcs_color_groups( $slug )[0]['members'];
	check( 'ekle: B ve D sona eklendi, B rengi tahmin', str_contains( $to, 'renk=eklendi' ) && array( $a, $c, $b, $d ) === array_column( $members, 'product_id' ) && 'Beyaz' === $members[2]['color_name'], $to );
	check( 'ekle: D adsiz, grup karti uyarir', '' === $members[3]['color_name'] && str_contains( section_html( $slug ), 'Renk adı boş olan ürünler: ZZRNK-D.' ) );
	check( 'birlesme: cikar + ekle tek kayit', $cut_id === nwcs_history_top()['id'] && str_contains( implode( ', ', nwcs_history_top()['facts'] ), '1 ürün eklendi' ), implode( ', ', nwcs_history_top()['facts'] ) );

	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al (cikar + ekle): A, B, C', ! is_wp_error( $undo ) && array( $a, $b, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'], 'product_id' ) );

	// Ikinci grup ayni kategoride; sonra tek tek cikarilip bosalinca silinir.
	$create( array( 'urunler' => array( $d ), 'baslik' => 'X' ) );
	$to = $create( array( 'urunler' => array( $d, $c ), 'baslik' => 'X' ) );
	check( 'ikinci grup: C baska grupta -> dolu', str_contains( $to, 'renk=dolu' ) );

	$before_delete = (string) ( nwcs_history_top()['id'] ?? '' );
	$to            = post( 'nwcs_handle_color_delete', array( 'action' => 'nwcs_color_delete', 'kategori' => $slug, 'grup' => $gid ), 'nwcs_color_delete_zz-renk-deneme' );
	check( 'sil: kendi kaydi', 'delete' === ( nwcs_history_top()['action'] ?? '' ) && $before_delete === ( nwcs_history_all()[1]['id'] ?? '' ) );
	check( 'sil: grup silindi, meta yok', str_contains( $to, 'renk=silindi' ) && array() === nwcs_color_groups( $slug ) && null === nwcs_color_group_of( $a ) );
	switch_to_blog( $pool );
	check( 'sil: terim meta satiri silindi', '' === get_term_meta( $cat, NWCS_COLOR_META, true ) );
	restore_current_blog();
	check( 'sil: sitede uc ayri kart', isset( cards( K_BLOG )[ $a ], cards( K_BLOG )[ $b ], cards( K_BLOG )[ $c ] ) );

	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al (sil): grup geri geldi', ! is_wp_error( $undo ) && 1 === count( nwcs_color_groups( $slug ) ) && 3 === ( cards( K_BLOG )[ $a ]['color_count'] ?? 0 ) );

	$to = post( 'nwcs_handle_color_save', array( 'action' => 'nwcs_color_save', 'kategori' => $slug, 'grup' => 'yokboyle', 'sira' => array() ), 'nwcs_color_save_zz-renk-deneme' );
	check( 'kaydet: bilinmeyen grup -> yok', str_contains( $to, 'renk=yok' ) );

	$to = post( 'nwcs_handle_color_save', array( 'action' => 'nwcs_color_save', 'kategori' => $slug, 'grup' => $gid, 'sira' => array( $a, $b, $c ), 'cikar' => $a ), 'nwcs_color_save_baska' );
	check( 'nonce: yanlis nonce yazmaz', str_starts_with( $to, 'wp_die' ) && 3 === count( nwcs_color_groups( $slug )[0]['members'] ), $to );

	// Bosalinca grup silinir; uc cikarma tek kayitta, tek geri alma.
	fresh();
	$save( array( 'grup' => $gid, 'sira' => array( $a, $b, $c ), 'cikar' => $a ) );
	$save( array( 'grup' => $gid, 'sira' => array( $b, $c ), 'cikar' => $b ) );
	$to = $save( array( 'grup' => $gid, 'sira' => array( $c ), 'cikar' => $c ) );
	check( 'bosalinca: grup silindi, uc duzenleme tek kayit', str_contains( $to, 'renk=bosaldi' ) && array() === nwcs_color_groups( $slug ) && 3 === (int) ( nwcs_history_top()['edits'] ?? 0 ) && str_contains( implode( ', ', nwcs_history_top()['facts'] ), 'ürün kalmadı, grup silindi' ), $to . ' ' . implode( ', ', (array) ( nwcs_history_top()['facts'] ?? array() ) ) );
	nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al (tek): A, B, C', array( $a, $b, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'] ?? array(), 'product_id' ) );

	/* ---------- 9) Kategori silme ve geri alma ---------- */

	$to = post( 'nwcs_handle_category_delete', array( 'action' => 'nwcs_category_delete', 'kategori' => $slug ), 'nwcs_category_delete_' . $slug );
	check( 'kategori silindi: kayitta renk gruplari', 'kategori' === ( nwcs_history_top()['kind'] ?? '' ) && 1 === count( (array) ( nwcs_history_top()['colors'] ?? array() ) ) && null === nwcs_color_group_of( $a ), $to );
	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'kategori geri geldi: renk grubu da', ! is_wp_error( $undo ) && array( $a, $b, $c ) === array_column( nwcs_color_groups( $slug )[0]['members'] ?? array(), 'product_id' ) && 3 === ( cards( K_BLOG )[ $a ]['color_count'] ?? 0 ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	$cat = (int) nwcs_pool_categories()[ $slug ]['id'];

	// Baska yoldan degisen gruplar: geri alma dokunmaz, kayit duser.
	fresh();
	$save( array( 'grup' => $gid, 'baslik' => 'Değişti', 'sira' => array( $a, $b, $c ) ) );
	switch_to_blog( $pool );
	update_term_meta( $cat, NWCS_COLOR_META, array() );
	restore_current_blog();
	nwcs_pool_flush_cache();
	$top  = nwcs_history_top();
	$undo = nwcs_history_undo( (string) $top['id'] );
	check( 'elle degisen: geri alinmaz, kayit listeden kalkar', is_wp_error( $undo ) && 'nwcs_undo_gone' === $undo->get_error_code() && ( nwcs_history_top()['id'] ?? '' ) !== $top['id'] );
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

delete_site_transient( 'nwcs_cache_note_' . get_current_user_id() );
delete_site_transient( 'nwcs_history_result_' . get_current_user_id() );
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
foreach ( array( 'zz-renk-deneme', 'zz-renk-diger' ) as $term_slug ) {
	$term = get_term_by( 'slug', $term_slug, NWCS_PRODUCT_TAX );

	if ( $term ) {
		wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
	}
}
restore_current_blog();
nwcs_pool_flush_cache();

check( 'temizlik: deneme grubu kalmadi', ! array_intersect( array_keys( nwcs_color_map()['members'] ), array_values( $ids ) ) && ! get_term_by( 'slug', 'zz-renk-deneme', NWCS_PRODUCT_TAX ) );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
