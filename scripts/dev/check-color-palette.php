<?php
/**
 * Yerel deneme: Ürün Havuzu -> Renkler (renk listesi) ve Renk grupları'ndaki
 * "Bu rengi listeye kaydet".
 *
 * Kendi deneme kategorilerini ve urunlerini olusturur (sitelere yerlestirmez):
 *   "ZZ Palet Deneme": A "... Antrasit", B "... Beyaz", C "... Pelin Yesili",
 *   D "ZZ Palet Sandalye" (adinda renk yok); "ZZ Palet Diger": E "... Pelin
 *   Yeşili", F "... Gri".
 * Denenenler: ilk liste (ayar yokken 13 renk, okuma yazmaz, sabit kimlik),
 * temizlik (ayni ad, gecersiz ton, en fazla 60), nwcs_color_presets() listeyi
 * dondurur; kaydetme (istek yolu, nonce, yetki): ekle (JS'siz bos satir ve
 * "+ Renk ekle"), ad ve ton degisikligi, JS'siz ↑ ↓ ve JS sirasi, "Sil" ve
 * silinen[], formda olmayan renk korunur; dogrulama (ad bos, ayni ad Turkce
 * harf ve buyuk/kucuk harf farkiyla, gecersiz ton, 60'tan fazla, bos yeni
 * satirla "+ Renk ekle": kayit yok, yazilanlar bir kez geri gelir); kullanim
 * sayilari; kullanilan rengin tonu/adi degisince onay (urun listesi, isaretli,
 * isaretsiz, karisik, "Geri dön, düzenle", baska sekmede degisen liste, suresi
 * dolan onay, ad takasi tek geciste); "Son işlemler" (kayit, birlesme, oturumun
 * basina donen kayit duser ve ilk liste geri gelir, geri alma, uyeler ya da
 * liste sonradan degisince geri almanin reddi); havuz ve site onbellegi;
 * Renk grupları'ndan "listeye kaydet" (eklenir, ayni ad ayni ton sessiz, ayni
 * ad baska ton uyari, hazir renkte ve taslakta yok sayilir, liste dolu);
 * yeni rengin adla ve urun adindan otomatik dolmasi, secim listesi ve
 * data-nwcs-colors; panel ekrani, onay sayfasi, menu sirasi.
 * Sonunda her seyi siler; "Son işlemler"i ve renk listesi ayarini eski haline
 * getirir, baska kategorilerin renk gruplarinin degismedigini denetler.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-color-palette.php
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

// Yonlendirmeden once: havuz onbellegi "kirli" mi (site onbellegi temizligi bu isarete bakar).
$dirty_at_redirect = null;
add_filter(
	'wp_redirect',
	static function ( $location ) use ( &$dirty_at_redirect ) {
		$dirty_at_redirect = nwcs_cache_state()['dirty'];

		return $location;
	},
	0
);

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

$admin_id = get_user_by( 'login', get_super_admins()[0] )->ID;
wp_set_current_user( $admin_id );

$pool           = nwcs_pool_blog_id();
$history_before = get_site_option( NWCS_HISTORY, array() );
$palette_before = get_site_option( NWCS_COLOR_PALETTE, false );

/** Havuzdaki butun kategorilerin renk grubu meta'si (ham): terim => deger. */
function all_color_metas(): array {
	switch_to_blog( nwcs_pool_blog_id() );
	$out   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => NWCS_PRODUCT_TAX,
			'hide_empty' => false,
			'meta_key'   => NWCS_COLOR_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$out[ (int) $term->term_id ] = get_term_meta( (int) $term->term_id, NWCS_COLOR_META, true );
	}

	restore_current_blog();

	return $out;
}

$metas_before = all_color_metas();

/** Istek yolu: handler'i POST verisiyle calistirir, yonlendirme adresini dondurur. */
function post( callable $handler, array $data, string $nonce ): string {
	global $dirty_at_redirect;

	$state             = &nwcs_cache_state();
	$state['dirty']    = false;
	$dirty_at_redirect = null;

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

/** Renkler ekrani (istenirse ?onay=1, ?sonuc=). */
function screen( array $get = array() ): string {
	$_GET = array( 'page' => NWCS_PALETTE_SLUG ) + $get;
	ob_start();
	nwcs_render_palette();
	$_GET = array();

	return (string) ob_get_clean();
}

/** Kategorinin renk bolumunun HTML'i. */
function section_html( string $slug ): string {
	$_GET = array( 'page' => NWCS_CATEGORIES_SLUG, 'kategori' => $slug );
	ob_start();
	nwcs_render_color_groups( $slug, nwcs_sync_category_products( $slug ) );
	$_GET = array();

	return (string) ob_get_clean();
}

/** Gorunen metin (etiketsiz, bosluklar tek). */
function text_of( string $html ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/** En ustteki gecmis kaydini birlesme suresinden eski yapar: sonraki kayit yeni kayit acar. */
function fresh(): void {
	$list = nwcs_history_all();

	if ( $list ) {
		$list[0]['time'] = time() - NWCS_COLOR_MERGE - 60;
		update_site_option( NWCS_HISTORY, $list );
	}
}

/** En ustteki gecmis kaydini simdiye ceker (fresh() ve geri almadan sonra birlesme yeniden denensin). */
function unfresh(): void {
	$list = nwcs_history_all();

	if ( $list ) {
		$list[0]['time'] = time();
		update_site_option( NWCS_HISTORY, $list );
	}
}

function names(): array {
	return array_column( nwcs_color_palette(), 'name' );
}

function last_name(): string {
	$names = names();

	return (string) end( $names );
}

function color_id( string $name ): string {
	return (string) ( nwcs_color_palette_find( $name )['id'] ?? '' );
}

/**
 * Formun "renk" alani (liste sirasiyla) + bos "Yeni renk" satiri. $edit: ad =>
 * [ ad, hex ] (degisen satir); $order: istenirse adlarla yeni sira; $drop: formda olmayacak adlar.
 */
function rows( array $edit = array(), array $new = array( 'ad' => '', 'hex' => '#ffffff' ), ?array $order = null, array $drop = array() ): array {
	$palette = nwcs_color_palette();

	if ( null !== $order ) {
		$by_name = array_column( $palette, null, 'name' );
		$palette = array_map( static fn( string $name ): array => $by_name[ $name ], $order );
	}

	$out = array();

	foreach ( $palette as $color ) {
		if ( in_array( $color['name'], $drop, true ) ) {
			continue;
		}

		$out[ $color['id'] ] = $edit[ $color['name'] ] ?? array( 'ad' => $color['name'], 'hex' => $color['hex'] );
	}

	$out['yeni'] = $new;

	return $out;
}

$save    = static fn( array $data ): string => post( 'nwcs_handle_palette_save', $data + array( 'action' => 'nwcs_palette_save' ), 'nwcs_palette_save' );
$confirm = static fn( array $data ): string => post( 'nwcs_handle_palette_confirm', $data + array( 'action' => 'nwcs_palette_confirm' ), 'nwcs_palette_confirm' );

/* ---------- Deneme verisi ---------- */

switch_to_blog( $pool );

$cat   = wp_insert_term( 'ZZ Palet Deneme', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-palet-deneme' ) );
$cat   = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
$other = wp_insert_term( 'ZZ Palet Diger', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-palet-diger' ) );
$other = is_wp_error( $other ) ? (int) $other->get_error_data() : (int) $other['term_id'];

$ids = array();
foreach (
	array(
		'a' => array( 'ZZ Palet Sandalye Antrasit', 'ZZPAL-A', $cat ),
		'b' => array( 'ZZ Palet Sandalye Beyaz', 'ZZPAL-B', $cat ),
		'c' => array( 'ZZ Palet Sandalye Pelin Yesili', 'ZZPAL-C', $cat ),
		'd' => array( 'ZZ Palet Sandalye', 'ZZPAL-D', $cat ),
		'e' => array( 'ZZ Palet Masa Pelin Yeşili', 'ZZPAL-E', $other ),
		'f' => array( 'ZZ Palet Masa Gri', 'ZZPAL-F', $other ),
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
	wp_set_object_terms( $ids[ $key ], array( $row[2] ), NWCS_PRODUCT_TAX );
}

restore_current_blog();
nwcs_pool_flush_cache();

[ 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e, 'f' => $f ] = $ids;

$slug  = 'zz-palet-deneme';
$slug2 = 'zz-palet-diger';

/** Grubu kaydeder: degismeyen uyeler kayitli ad ve tonla ("Özel renk"), $over: urun => satir. */
$gsave = static function ( string $slug, string $gid, array $over, array $extra = array() ): string {
	$members = nwcs_color_find( nwcs_color_groups( $slug ), $gid )['members'] ?? array();
	$uye     = array();

	foreach ( $members as $member ) {
		$uye[ $member['product_id'] ] = $over[ $member['product_id'] ] ?? array(
			'ad'    => $member['color_name'],
			'hazir' => 'ozel',
			'hex'   => '' !== $member['color_hex'] ? $member['color_hex'] : '#ffffff',
		);
	}

	return post(
		'nwcs_handle_color_save',
		$extra + array(
			'action'   => 'nwcs_color_save',
			'kategori' => $slug,
			'grup'     => $gid,
			'baslik'   => 'Renk',
			'sira'     => array_column( $members, 'product_id' ),
			'uye'      => $uye,
		),
		'nwcs_color_save_' . $slug
	);
};

/** Kategorideki uyenin satiri. */
$member = static function ( string $slug, int $id ): array {
	foreach ( nwcs_color_groups( $slug ) as $group ) {
		foreach ( $group['members'] as $row ) {
			if ( $row['product_id'] === $id ) {
				return $row;
			}
		}
	}

	return array();
};

try {
	// Guvenlik: deneme adlarini gercek veride kullanan uye olmamali (uyelere yazma yalnizca deneme verisine).
	$usage0 = nwcs_palette_usage();
	$safe   = ! array_intersect( array_keys( $usage0 ), array( 'pelin yesili', 'pelin yesili acik', 'gece mavisi', 'gece mavisi 2' ) );
	check( 'guvenlik: deneme renk adlari baska uyelerde yok', $safe );

	if ( ! $safe ) {
		throw new RuntimeException( 'Deneme renk adlari gercek veride var; uyelere yazan denemeler atlandi.' );
	}

	$base_antrasit = nwcs_palette_used( 'Antrasit', $usage0 );

	/* ---------- 1) Ilk liste ve yardimcilar ---------- */

	delete_site_option( NWCS_COLOR_PALETTE );
	$first = nwcs_color_palette();
	check( 'ilk liste: ayar yokken 13 hazir renk, sirayla', array_keys( nwcs_color_default_presets() ) === array_column( $first, 'name' ) && 13 === count( $first ) );
	check( 'ilk liste: kimlikler sabit (her okumada ayni)', array_column( $first, 'id' ) === array_column( nwcs_color_palette(), 'id' ) && 13 === count( array_unique( array_column( $first, 'id' ) ) ) );
	check( 'ilk liste: okuma ayari yazmaz', false === get_site_option( NWCS_COLOR_PALETTE, false ) );
	check( 'presets: ilk listede eski hazir renkler', nwcs_color_default_presets() === nwcs_color_presets() );
	check( 'ad ile ton: "ANTRASİT" -> #383e42', '#383e42' === nwcs_color_preset_hex( 'ANTRASİT' ) );

	$clean = nwcs_color_palette_clean(
		array(
			array( 'name' => 'Gri', 'hex' => '#999' ),
			array( 'name' => 'gri', 'hex' => '#000000' ),
			array( 'name' => '', 'hex' => '#ffffff' ),
			array( 'name' => 'Mor', 'hex' => 'mor' ),
			array( 'name' => 'Lila', 'hex' => '#C8A2C8', 'id' => 'x' ),
			array( 'name' => 'Lila 2', 'hex' => '#C8A2C8', 'id' => 'x' ),
		)
	);
	check( 'temizlik: ayni ad, adsiz ve gecersiz ton atilir; ton bicimlenir; tekrar eden kimlik yenilenir', array( 'Gri', 'Lila', 'Lila 2' ) === array_column( $clean, 'name' ) && '#999999' === $clean[0]['hex'] && '#c8a2c8' === $clean[1]['hex'] && 'x' === $clean[1]['id'] && 'x' !== $clean[2]['id'] );
	$many = array();
	for ( $i = 1; $i <= 70; $i++ ) {
		$many[] = array( 'name' => 'Renk ' . $i, 'hex' => '#123456' );
	}
	check( 'temizlik: en fazla 60 renk', 60 === count( nwcs_color_palette_clean( $many ) ) );

	/* ---------- 2) Ekran ---------- */

	$html = screen();
	check( 'ekran: baslik, 13 satir + bos satir + sablon, sayac', str_contains( $html, 'Ürün Havuzu › Renkler' ) && 15 === preg_match_all( '/data-nwcs-palette-row(?!s)/', $html ) && str_contains( $html, '13 / 60 renk' ) && 1 === substr_count( $html, 'data-nwcs-palette-new' ) && str_contains( $html, 'data-nwcs-palette-template' ) );
	check( 'ekran: ↑ ↓, Sil, + Renk ekle, Kaydet, Son işlemler', str_contains( $html, 'name="tasi"' ) && str_contains( $html, 'name="sil"' ) && str_contains( $html, '+ Renk ekle' ) && str_contains( $html, 'Kaydet</button>' ) && str_contains( $html, 'Son işlemler' ) );
	check( 'ekran: kullanim "Kullanılmıyor" ya da "N üründe"', str_contains( $html, 'Kullanılmıyor' ) || str_contains( $html, 'üründe' ) );
	check( 'ekran: nonce ve yetki alani', str_contains( $html, 'name="_wpnonce"' ) && str_contains( $html, 'value="nwcs_palette_save"' ) );

	/* ---------- 3) Kaydetme: degisiklik yok, ekle, ilk liste geri alma ---------- */

	$top0 = (string) ( nwcs_history_top()['id'] ?? '' );
	$to   = $save( array( 'renk' => rows() ) );
	check( 'kaydet: degisiklik yok -> ayni, ayar yazilmaz, gecmis yok', str_contains( $to, 'sonuc=ayni' ) && false === get_site_option( NWCS_COLOR_PALETTE, false ) && $top0 === (string) ( nwcs_history_top()['id'] ?? '' ), $to );

	$to = $save( array( 'renk' => rows( array(), array( 'ad' => 'Pelin Yeşili', 'hex' => '#8A9A5B' ) ), 'ekle' => 1 ) );
	$top = nwcs_history_top();
	check( 'ekle (JS\'siz bos satir + "+ Renk ekle"): eklendi, sonda, ton bicimli', str_contains( $to, 'sonuc=eklendi' ) && 'Pelin Yeşili' === last_name() && '#8a9a5b' === nwcs_color_preset_hex( 'pelin yesili' ), $to );
	check( 'ekle: ayar yazildi, ilk renklerin kimligi korundu', is_array( get_site_option( NWCS_COLOR_PALETTE, false ) ) && array_column( $first, 'id' ) === array_slice( array_column( nwcs_color_palette(), 'id' ), 0, 13 ) );
	check( 'gecmis: palet kaydi (ilk kayit isareti, ozet)', NWCS_PALETTE_KIND === ( $top['kind'] ?? '' ) && ! empty( $top['seeded'] ) && 'Renkler' === nwcs_history_describe( $top )['what'] && 'renk listesi' === nwcs_history_describe( $top )['kind'] && '“Pelin Yeşili” eklendi' === nwcs_history_describe( $top )['facts'], wp_json_encode( nwcs_history_describe( $top ), JSON_UNESCAPED_UNICODE ) );
	check( 'presets: listeyi dondurur (yeni renk dahil)', array_column( nwcs_color_palette(), 'hex', 'name' ) === nwcs_color_presets() && isset( nwcs_color_presets()['Pelin Yeşili'] ) );

	$undo = nwcs_history_undo( (string) $top['id'] );
	check( 'geri al (ilk kayit): ayar silinir, ilk 13 renk', ! is_wp_error( $undo ) && false === get_site_option( NWCS_COLOR_PALETTE, false ) && array_keys( nwcs_color_default_presets() ) === names() && $top0 === (string) ( nwcs_history_top()['id'] ?? '' ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );

	// Birlesme oturumun basina donunce kayit duser, ayar silinir.
	$save( array( 'renk' => rows( array(), array( 'ad' => 'ZZ Geçici', 'hex' => '#010203' ) ) ) );
	$tmp_top = (string) nwcs_history_top()['id'];
	$save( array( 'renk' => rows(), 'sil' => color_id( 'ZZ Geçici' ) ) );
	check( 'birlesme: eklenip silinen renk -> kayit duser, ayar silinir', $top0 === (string) ( nwcs_history_top()['id'] ?? '' ) && false === get_site_option( NWCS_COLOR_PALETTE, false ) && $tmp_top !== $top0 );

	$to   = $save( array( 'renk' => rows( array(), array( 'ad' => 'Pelin Yeşili', 'hex' => '#8a9a5b' ) ) ) );
	$rec1 = (string) nwcs_history_top()['id'];
	check( 'ekle ("Kaydet" ile): kaydedildi', str_contains( $to, 'sonuc=kaydedildi' ) && in_array( 'Pelin Yeşili', names(), true ), $to );
	check( 'ekran: kaydedildi bildirimi', str_contains( screen( array( 'sonuc' => 'kaydedildi' ) ), 'Renk listesi kaydedildi.' ) );

	/* ---------- 4) Dogrulama: kayit yok, yazilanlar geri gelir ---------- */

	$before = nwcs_color_palette();

	$to = $save( array( 'renk' => rows(), 'ekle' => 1 ) );
	$html = screen( array( 'sonuc' => 'hata' ) );
	check( 'dogrulama: bos yeni satirla "+ Renk ekle" -> hata, mesaj', str_contains( $to, 'sonuc=hata' ) && str_contains( text_of( $html ), 'Yeni renk eklenmedi: en alttaki “Yeni renk” satırına rengin adını yazın' ) && $before === nwcs_color_palette(), $to );

	$to   = $save( array( 'renk' => rows( array( 'Siyah' => array( 'ad' => '', 'hex' => '#1f1f1f' ), 'Gri' => array( 'ad' => 'Taslak Gri', 'hex' => '#9ea1a3' ) ) ) ) );
	$html = screen( array( 'sonuc' => 'hata' ) );
	check( 'dogrulama: ad bos -> kayit yok, gecmis yok', str_contains( $to, 'sonuc=hata' ) && $before === nwcs_color_palette() && $rec1 === (string) nwcs_history_top()['id'], $to );
	check( 'dogrulama: "2. satır: renk adı boş", yazilanlar geri geldi, satir isaretli', str_contains( text_of( $html ), '2. satır: renk adı boş. Adı yazın ya da satırı silin.' ) && str_contains( $html, 'value="Taslak Gri"' ) && 1 === substr_count( $html, 'aria-invalid="true"' ) && str_contains( text_of( $html ), 'Kaydedilmedi. Yazdıklarınız aşağıda duruyor; hiçbir şey değişmedi.' ) );
	check( 'dogrulama: taslak bir kez', ! str_contains( screen(), 'Taslak Gri' ) );

	$save( array( 'renk' => rows( array( 'Siyah' => array( 'ad' => 'BEYAZ', 'hex' => '#1f1f1f' ) ) ) ) );
	check( 'dogrulama: ayni ad (buyuk/kucuk harf) -> "“BEYAZ” adı 1. satırda da var"', str_contains( text_of( screen( array( 'sonuc' => 'hata' ) ) ), '2. satır: “BEYAZ” adı 1. satırda da var' ) && $before === nwcs_color_palette() );

	$save( array( 'renk' => rows( array(), array( 'ad' => 'antrasıt', 'hex' => '#000000' ) ) ) );
	check( 'dogrulama: ayni ad (Turkce harf) "antrasıt" = Antrasit', str_contains( text_of( screen( array( 'sonuc' => 'hata' ) ) ), '15. satır: “antrasıt” adı 3. satırda da var' ) && $before === nwcs_color_palette() );

	$save( array( 'renk' => rows( array( 'Gri' => array( 'ad' => 'Gri', 'hex' => '#12zz' ) ) ) ) );
	$html = screen( array( 'sonuc' => 'hata' ) );
	check( 'dogrulama: gecersiz ton -> mesaj, yazilan ton geri gelir', str_contains( text_of( $html ), '4. satır: “#12zz” geçerli bir renk kodu değil.' ) && str_contains( $html, '#12zz' ) && $before === nwcs_color_palette() );

	$extra = rows();
	unset( $extra['yeni'] );
	for ( $i = 1; $i <= 47; $i++ ) {
		$extra[ 'j' . $i ] = array( 'ad' => 'ZZ Fazla ' . $i, 'hex' => '#123456' );
	}
	$save( array( 'renk' => $extra ) );
	check( 'dogrulama: 61 renk -> "en fazla 60 renk"', str_contains( text_of( screen( array( 'sonuc' => 'hata' ) ) ), 'Listede en fazla 60 renk olabilir; formda 61 renk var.' ) && $before === nwcs_color_palette() );

	unset( $extra['j47'] );
	fresh();
	$to = $save( array( 'renk' => $extra ) );
	check( 'dogrulama: tam 60 renk kaydedilir', str_contains( $to, 'sonuc=kaydedildi' ) && 60 === count( nwcs_color_palette() ), $to );
	$undo = nwcs_history_undo( (string) nwcs_history_top()['id'] );
	check( 'geri al: 14 renge doner, ustte onceki kayit', ! is_wp_error( $undo ) && $before === nwcs_color_palette() && $rec1 === (string) nwcs_history_top()['id'] );
	unfresh();

	/* ---------- 5) Sira, ad, silme ---------- */

	$names = names();
	$to    = $save( array( 'renk' => rows(), 'tasi' => color_id( 'Pelin Yeşili' ) . ':up' ) );
	$top   = nwcs_history_top();
	check( 'JS\'siz ↑: Pelin Yeşili bir yukari, ayni kayitta (2 düzenleme)', 'Pelin Yeşili' === names()[12] && 'Sarı' === names()[13] && $rec1 === $top['id'] && 2 === (int) $top['edits'], implode( ',', names() ) );

	$order = names();
	$beyaz = array_shift( $order );
	$order[] = $beyaz;
	$save( array( 'renk' => rows( array(), array( 'ad' => '', 'hex' => '#ffffff' ), $order ) ) );
	$top = nwcs_history_top();
	check( 'JS sirasi: Beyaz sonda, ozet "sıra değişti"', 'Beyaz' === last_name() && str_contains( nwcs_history_describe( $top )['facts'], 'sıra değişti' ) && str_contains( nwcs_history_describe( $top )['facts'], '3 düzenleme' ), nwcs_history_describe( $top )['facts'] );
	$save( array( 'renk' => rows( array(), array( 'ad' => '', 'hex' => '#ffffff' ), $names ) ) );
	check( 'sira geri: Beyaz basta', $names === names() );

	fresh();
	$save( array( 'renk' => rows( array(), array( 'ad' => 'ZZ Geçici', 'hex' => '#010203' ) ) ) );
	$to = $save( array( 'renk' => rows( array( 'ZZ Geçici' => array( 'ad' => 'ZZ Geçici 2', 'hex' => '#040506' ) ) ) ) );
	check( 'ad ve ton: kullanilmayan renk onaysiz kaydedilir', str_contains( $to, 'sonuc=kaydedildi' ) && '#040506' === nwcs_color_preset_hex( 'zz gecici 2' ) && ! nwcs_color_palette_find( 'ZZ Geçici' ), $to );

	$keep = names();
	$to   = $save( array( 'renk' => rows( array(), array( 'ad' => '', 'hex' => '#ffffff' ), null, array( 'Mavi' ) ) ) );
	check( 'formda olmayan renk (baska sekmede eklenmis) silinmez, sona gecer', in_array( 'Mavi', names(), true ) && 'Mavi' === last_name() && count( $keep ) === count( names() ), $to );
	$save( array( 'renk' => rows( array(), array( 'ad' => '', 'hex' => '#ffffff' ), $keep ) ) );

	$to = $save( array( 'renk' => rows(), 'sil' => color_id( 'ZZ Geçici 2' ) ) );
	check( 'JS\'siz "Sil": silindi', str_contains( $to, 'sonuc=silindi' ) && ! nwcs_color_palette_find( 'ZZ Geçici 2' ), $to );
	check( 'ekran: silindi bildirimi', str_contains( screen( array( 'sonuc' => 'silindi' ) ), 'Bu rengi kullanan ürünler rengini korur' ) );

	$save( array( 'renk' => rows( array(), array( 'ad' => 'ZZ Geçici 3', 'hex' => '#070809' ) ) ) );
	$to = $save( array( 'renk' => rows( array(), array( 'ad' => '', 'hex' => '#ffffff' ), null, array( 'ZZ Geçici 3' ) ), 'silinen' => array( color_id( 'ZZ Geçici 3' ) ) ) );
	check( 'JS "Sil" (silinen[]): silindi', ! nwcs_color_palette_find( 'ZZ Geçici 3' ) && array_values( array_diff( $keep, array( 'ZZ Geçici 2' ) ) ) === names(), $to );
	check( 'gecmis: gecici renkler tek kayitta birlesip duser', 'renk listesi' === nwcs_history_describe( nwcs_history_top() )['kind'] && $rec1 === (string) nwcs_history_top()['id'] );

	/* ---------- 6) Gruplar: urun adindan tahmin, kullanim, adla dolma ---------- */

	$to     = post( 'nwcs_handle_color_create', array( 'action' => 'nwcs_color_create', 'kategori' => $slug, 'urunler' => array( $a, $b, $c, $d ), 'baslik' => 'Renk' ), 'nwcs_color_create_' . $slug );
	$gid    = (string) ( nwcs_color_groups( $slug )[0]['id'] ?? '' );
	$to2    = post( 'nwcs_handle_color_create', array( 'action' => 'nwcs_color_create', 'kategori' => $slug2, 'urunler' => array( $e, $f ), 'baslik' => 'Renk' ), 'nwcs_color_create_' . $slug2 );
	$gid2   = (string) ( nwcs_color_groups( $slug2 )[0]['id'] ?? '' );
	check( 'tahmin: yeni renk urun adindan ("Pelin Yesili" -> Pelin Yeşili, listedeki ton)', 'Pelin Yeşili' === $member( $slug, $c )['color_name'] && '#8a9a5b' === $member( $slug, $c )['color_hex'] && 'Pelin Yeşili' === $member( $slug2, $e )['color_name'] && 'Antrasit' === $member( $slug, $a )['color_name'] && '' === $member( $slug, $d )['color_name'], $to . ' ' . $to2 );
	check( 'tahmin: nwcs_color_guess yeni rengi bulur', 'Pelin Yeşili' === nwcs_color_guess( 'Kedi Yuvası PELİN YEŞİLİ' )['name'] );

	$to = $gsave( $slug, $gid, array( $d => array( 'ad' => 'PELİN YEŞİLİ', 'hazir' => '', 'hex' => '#ffffff' ) ) );
	check( 'adla dolma: "PELİN YEŞİLİ" yazilip renk secilmezse yeni rengin tonu', str_contains( $to, 'renk=kaydedildi' ) && '#8a9a5b' === $member( $slug, $d )['color_hex'], $to . ' ' . wp_json_encode( $member( $slug, $d ) ) );

	$usage = nwcs_palette_usage();
	check( 'kullanim: Pelin Yeşili 3 urunde (iki kategori), Antrasit +1', 3 === nwcs_palette_used( 'pelin yesili', $usage ) && $base_antrasit + 1 === nwcs_palette_used( 'Antrasit', $usage ) );
	check( 'ekran: "3 üründe"', str_contains( screen(), '3 üründe' ) );

	$html = section_html( $slug );
	$json = json_decode( html_entity_decode( (string) ( preg_match( '/data-nwcs-colors="([^"]*)"/', $html, $m ) ? $m[1] : '' ), ENT_QUOTES ), true );
	check( 'grup bolumu: data-nwcs-colors listeden (yeni renk dahil)', is_array( $json ) && '#8a9a5b' === ( $json['Pelin Yeşili'] ?? '' ) && count( $json ) === count( nwcs_color_palette() ) );
	check( 'grup bolumu: secim listesinde yeni renk iki satirda secili', 2 === preg_match_all( '/<option value="Pelin Yeşili" data-hex="#8a9a5b"\s+selected/', $html ) && str_contains( $html, '<datalist id="nwcs-renk-adlari">' ) && str_contains( $html, '<option value="Pelin Yeşili"></option>' ) );
	check( 'grup bolumu: "Bu rengi listeye kaydet" ve "Renk listesini düzenle ↗"', 4 === substr_count( $html, 'data-nwcs-color-keep' ) && str_contains( $html, 'page=' . NWCS_PALETTE_SLUG ) && str_contains( $html, 'Renk listesini düzenle' ) );

	/* ---------- 7) Renk grupları'ndan "listeye kaydet" ---------- */

	fresh();
	$to   = $gsave( $slug, $gid, array( $b => array( 'ad' => 'Gece Mavisi', 'hazir' => 'ozel', 'hex' => '#1B2A49', 'listeye' => 1 ) ) );
	$list = nwcs_history_all();
	check( 'listeye kaydet: listeye eklendi (sonda, ton), uye kaydedildi', str_contains( $to, 'renk=kaydedildi' ) && 'Gece Mavisi' === last_name() && '#1b2a49' === nwcs_color_preset_hex( 'Gece Mavisi' ) && '#1b2a49' === $member( $slug, $b )['color_hex'], $to );
	check( 'listeye kaydet: gecmiste palet kaydi (Renk grupları’ndan) ve grup kaydi', 'renk' === ( $list[0]['kind'] ?? '' ) && 'palet' === ( $list[1]['kind'] ?? '' ) && 'add' === ( $list[1]['action'] ?? '' ) && str_contains( nwcs_history_describe( $list[1] )['facts'], '“Gece Mavisi” eklendi (Renk grupları’ndan)' ), nwcs_history_describe( $list[1] )['facts'] );
	$html = section_html( $slug );
	check( 'listeye kaydet: bolumde "renk listesine eklendi" (bir kez), B listede secili', str_contains( text_of( $html ), '“Gece Mavisi” renk listesine eklendi; artık her grupta listeden seçilebilir.' ) && 1 === preg_match_all( '/<option value="Gece Mavisi" data-hex="#1b2a49"\s+selected/', $html ) && ! str_contains( section_html( $slug ), 'renk listesine eklendi' ) );

	$top = (string) nwcs_history_top()['id'];
	$pal = nwcs_color_palette();
	$to  = $gsave( $slug, $gid, array( $b => array( 'ad' => 'Gece Mavisi', 'hazir' => 'ozel', 'hex' => '#1b2a49', 'listeye' => 1 ) ) );
	check( 'listeye kaydet: ayni ad ayni ton -> sessiz (liste, gecmis, bildirim yok)', str_contains( $to, 'renk=ayni' ) && $pal === nwcs_color_palette() && $top === (string) nwcs_history_top()['id'] && false === get_site_transient( NWCS_PALETTE_NOTE . $admin_id ), $to );

	$to   = $gsave( $slug, $gid, array( $d => array( 'ad' => 'gece mavisi', 'hazir' => 'ozel', 'hex' => '#000080', 'listeye' => 1 ) ) );
	$html = section_html( $slug );
	check( 'listeye kaydet: ayni ad baska ton -> liste degismez, uye kaydedilir', str_contains( $to, 'renk=kaydedildi' ) && $pal === nwcs_color_palette() && '#000080' === $member( $slug, $d )['color_hex'] && 'gece mavisi' === $member( $slug, $d )['color_name'], $to );
	check( 'listeye kaydet: uyari "‘Gece Mavisi’ listede başka bir tonla kayıtlı; listeyi Renkler ekranından düzenleyin."', str_contains( text_of( $html ), '‘Gece Mavisi’ listede başka bir tonla kayıtlı; listeyi Renkler ekranından düzenleyin.' ) && str_contains( $html, 'href="' . esc_url( nwcs_palette_url() ) . '"' ) );

	$to = $gsave( $slug, $gid, array( $a => array( 'ad' => 'Antrasit', 'hazir' => 'Antrasit', 'hex' => '#ffffff', 'listeye' => 1 ) ) );
	check( 'listeye kaydet: listeden secilen renkte yok sayilir', $pal === nwcs_color_palette() && false === get_site_transient( NWCS_PALETTE_NOTE . $admin_id ) );

	$to = $gsave( $slug2, $gid2, array( $f => array( 'ad' => 'ZZ Listesiz', 'hazir' => 'ozel', 'hex' => '#abcdef' ) ) );
	check( 'listeye kaydet isaretsiz: ozel renk yalnizca uyede', str_contains( $to, 'renk=kaydedildi' ) && $pal === nwcs_color_palette() && '#abcdef' === $member( $slug2, $f )['color_hex'], $to );

	$to = $gsave( $slug, $gid, array( $a => array( 'ad' => '', 'hazir' => '' ), $b => array( 'ad' => 'ZZ Taslak Renk', 'hazir' => 'ozel', 'hex' => '#0a0b0c', 'listeye' => 1 ) ) );
	check( 'listeye kaydet: grup kaydedilmezse (ad bos) listeye de eklenmez', str_contains( $to, 'renk=adsiz' ) && $pal === nwcs_color_palette() );
	section_html( $slug ); // taslak bir kez gosterilir.

	$full = $pal;
	for ( $i = count( $full ); $i < NWCS_COLOR_PALETTE_MAX; $i++ ) {
		$full[] = array( 'id' => 'zzdolu' . $i, 'name' => 'ZZ Dolu ' . $i, 'hex' => '#101010' );
	}
	update_site_option( NWCS_COLOR_PALETTE, $full );
	$to = $gsave( $slug2, $gid2, array( $f => array( 'ad' => 'ZZ Yer Yok', 'hazir' => 'ozel', 'hex' => '#fedcba', 'listeye' => 1 ) ) );
	check( 'listeye kaydet: liste doluysa eklenmez, bolum soyler', 60 === count( nwcs_color_palette() ) && ! nwcs_color_palette_find( 'ZZ Yer Yok' ) && str_contains( text_of( section_html( $slug2 ) ), '“ZZ Yer Yok” listeye eklenmedi: listede en fazla 60 renk olabilir.' ), $to );
	update_site_option( NWCS_COLOR_PALETTE, $pal );

	/* ---------- 8) Onay: kullanilan rengin tonu ve adi ---------- */

	fresh();
	$pelin = color_id( 'Pelin Yeşili' );
	$gece  = color_id( 'Gece Mavisi' );
	$to    = $save( array( 'renk' => rows( array( 'Pelin Yeşili' => array( 'ad' => 'Pelin Yeşili', 'hex' => '#7d8f4e' ) ) ) ) );
	check( 'onay: kullanilan rengin tonu -> once onay, liste degismez', str_contains( $to, 'onay=1' ) && $pal === nwcs_color_palette() && is_array( get_site_transient( NWCS_PALETTE_PENDING . $admin_id ) ), $to );
	$html = screen( array( 'onay' => 1 ) );
	$text = text_of( $html );
	// Bu noktada Pelin Yeşili: C ve E (D "gece mavisi" oldu); Gece Mavisi: B ve D.
	check( 'onay sayfasi: baslik, renk, "Bu rengi kullanan 2 ürün de güncellensin" (isaretli)', str_contains( $text, 'Bu renkleri kullanan ürünler de güncellensin mi?' ) && str_contains( $text, 'Bu rengi kullanan 2 ürün de güncellensin' ) && 1 === preg_match( '/name="guncelle\[' . $pelin . '\]" value="1" checked/', $html ) && str_contains( $text, '#8a9a5b → #7d8f4e' ) );
	check( 'onay sayfasi: urunler (kod, ad, kategori)', str_contains( $text, 'ZZPAL-C' ) && str_contains( $text, 'ZZ Palet Sandalye Pelin Yesili' ) && str_contains( $text, 'ZZPAL-E' ) && str_contains( $text, 'ZZ Palet Masa Pelin Yeşili' ) && str_contains( $text, 'ZZ Palet Deneme' ) && str_contains( $text, 'ZZ Palet Diger' ) && ! str_contains( $text, 'ZZPAL-A' ) && ! str_contains( $text, 'ZZPAL-D' ) );
	check( 'onay sayfasi: Listeyi kaydet, Geri dön, Vazgeç; nonce', str_contains( $text, 'Listeyi kaydet' ) && str_contains( $text, 'Geri dön, düzenle' ) && str_contains( $text, 'Vazgeç' ) && str_contains( $html, 'value="nwcs_palette_confirm"' ) );

	$to  = $confirm( array( 'guncelle' => array( $pelin => 1 ) ) );
	$top = nwcs_history_top();
	check( 'onay (isaretli): liste ve 2 uye yeni tonda, adlar ayni, digerleri ayni', str_contains( $to, 'sonuc=kaydedildi' ) && str_contains( $to, 'urun=2' ) && '#7d8f4e' === nwcs_color_preset_hex( 'Pelin Yeşili' ) && '#7d8f4e' === $member( $slug, $c )['color_hex'] && '#7d8f4e' === $member( $slug2, $e )['color_hex'] && 'Pelin Yeşili' === $member( $slug, $c )['color_name'] && '#000080' === $member( $slug, $d )['color_hex'] && '#383e42' === $member( $slug, $a )['color_hex'], $to );
	check( 'onay (isaretli): havuz ve site onbellegi temizlendi', true === $dirty_at_redirect && '#7d8f4e' === ( nwcs_color_group_of( $e )['members'][0]['color_hex'] ?? '' ) );
	check( 'onay (isaretli): gecmis ozeti ve uyeler', 'palet' === $top['kind'] && 2 === count( $top['members'] ) && str_contains( nwcs_history_describe( $top )['facts'], '“Pelin Yeşili” tonu değişti, 2 ürünün rengi güncellendi' ), nwcs_history_describe( $top )['facts'] );
	check( 'ekran: "2 ürünün rengi de güncellendi"', str_contains( screen( array( 'sonuc' => 'kaydedildi', 'urun' => 2 ) ), '2 ürünün rengi de güncellendi' ) );
	check( 'onay: bekleyen kayit silindi, tekrar onay -> suresi', false === get_site_transient( NWCS_PALETTE_PENDING . $admin_id ) && str_contains( $confirm( array( 'guncelle' => array( $pelin => 1 ) ) ), 'sonuc=suresi' ) && str_contains( screen( array( 'onay' => 1 ) ), 'Onay sayfasının süresi doldu' ) );

	// Hicbir uyenin kullanmadigi bir ilk renk (gercek veride de): tonu onaysiz kaydedilir.
	$idle = '';
	foreach ( array_keys( nwcs_color_default_presets() ) as $name ) {
		if ( ! nwcs_palette_used( $name ) ) {
			$idle = $name;
			break;
		}
	}
	$idle_hex = nwcs_color_preset_hex( $idle );
	$to       = $save( array( 'renk' => rows( array( $idle => array( 'ad' => $idle, 'hex' => '#010101' ) ) ) ) );
	check( 'kullanilmayan rengin tonu onaysiz; uye degismedigi icin site onbellegi gerekmez', '' !== $idle && str_contains( $to, 'sonuc=kaydedildi' ) && false === $dirty_at_redirect && '#010101' === nwcs_color_preset_hex( $idle ), $idle . ' ' . $to );
	$save( array( 'renk' => rows( array( $idle => array( 'ad' => $idle, 'hex' => $idle_hex ) ) ) ) );

	// Ad degisikligi (ton ayni): uyelerin adi da.
	fresh();
	$save( array( 'renk' => rows( array( 'Pelin Yeşili' => array( 'ad' => 'Pelin Yeşili Açık', 'hex' => '#7d8f4e' ) ) ) ) );
	$text = text_of( screen( array( 'onay' => 1 ) ) );
	check( 'onay (ad): "ton aynı", yeni ad yazar', str_contains( $text, 'ton aynı (#7d8f4e)' ) && str_contains( $text, 'renk adı “Pelin Yeşili Açık”' ) );
	$confirm( array( 'guncelle' => array( $pelin => 1 ) ) );
	check( 'onay (ad, isaretli): uyelerin adi degisti, ton ayni', 'Pelin Yeşili Açık' === $member( $slug, $c )['color_name'] && '#7d8f4e' === $member( $slug, $c )['color_hex'] && 'Pelin Yeşili Açık' === $member( $slug2, $e )['color_name'] && 'gece mavisi' === $member( $slug, $d )['color_name'] && 'Pelin Yeşili Açık' === nwcs_color_palette_find( 'pelin yesili acik' )['name'] );

	// Isaretsiz: liste degisir, uyeler kalir.
	fresh();
	$save( array( 'renk' => rows( array( 'Pelin Yeşili Açık' => array( 'ad' => 'Pelin Yeşili Açık', 'hex' => '#00ff00' ) ) ) ) );
	$to = $confirm( array() );
	check( 'onay (isaretsiz): liste yeni tonda, uyeler eski tonda, gecmiste uye yok', '#00ff00' === nwcs_color_preset_hex( 'Pelin Yeşili Açık' ) && '#7d8f4e' === $member( $slug, $c )['color_hex'] && array() === nwcs_history_top()['members'] && ! str_contains( $to, 'urun=' ), $to );

	// Karisik: iki renk, yalnizca biri isaretli.
	fresh();
	$save(
		array(
			'renk' => rows(
				array(
					'Pelin Yeşili Açık' => array( 'ad' => 'Pelin Yeşili Açık', 'hex' => '#111111' ),
					'Gece Mavisi'       => array( 'ad' => 'Gece Mavisi', 'hex' => '#222222' ),
				)
			),
		)
	);
	check( 'onay (iki renk): iki bolum', 2 === substr_count( screen( array( 'onay' => 1 ) ), 'nwcs-palette__change' ) );
	$to = $confirm( array( 'guncelle' => array( $gece => 1 ) ) );
	check( 'onay (karisik): yalnizca isaretli rengin uyeleri (B ve "gece mavisi" adli D)', str_contains( $to, 'urun=2' ) && '#222222' === $member( $slug, $b )['color_hex'] && '#222222' === $member( $slug, $d )['color_hex'] && 'gece mavisi' === $member( $slug, $d )['color_name'] && '#7d8f4e' === $member( $slug, $c )['color_hex'] && '#111111' === nwcs_color_preset_hex( 'Pelin Yeşili Açık' ), $to );

	// Geri don, duzenle.
	fresh();
	$pal = nwcs_color_palette();
	$save( array( 'renk' => rows( array( 'Gece Mavisi' => array( 'ad' => 'Gece Mavisi 2', 'hex' => '#222222' ) ) ) ) );
	$to   = $confirm( array( 'geri' => 1 ) );
	$html = screen( array( 'sonuc' => 'duzenle' ) );
	check( 'onay "Geri dön, düzenle": kayit yok, yazilanlar formda', str_contains( $to, 'sonuc=duzenle' ) && $pal === nwcs_color_palette() && str_contains( $html, 'value="Gece Mavisi 2"' ) && str_contains( $html, 'Değişiklikleriniz aşağıda duruyor' ), $to );

	// Baska sekmede degisen liste.
	$save( array( 'renk' => rows( array( 'Gece Mavisi' => array( 'ad' => 'Gece Mavisi', 'hex' => '#333333' ) ) ) ) );
	update_site_option( NWCS_COLOR_PALETTE, array_merge( $pal, array( array( 'id' => 'zzsekme', 'name' => 'ZZ Sekme', 'hex' => '#444444' ) ) ) );
	$to = $confirm( array( 'guncelle' => array( $gece => 1 ) ) );
	check( 'onay: liste bu arada degistiyse kaydedilmez', str_contains( $to, 'sonuc=degisti' ) && '#222222' === $member( $slug, $b )['color_hex'] && str_contains( screen( array( 'sonuc' => 'degisti' ) ), 'başka bir sekmede değişti' ), $to );
	update_site_option( NWCS_COLOR_PALETTE, $pal );

	// Ad takasi: tek geciste (zincirleme degil).
	fresh();
	$save(
		array(
			'renk' => rows(
				array(
					'Pelin Yeşili Açık' => array( 'ad' => 'Gece Mavisi', 'hex' => '#111111' ),
					'Gece Mavisi'       => array( 'ad' => 'Pelin Yeşili Açık', 'hex' => '#222222' ),
				)
			),
		)
	);
	$to   = $confirm( array( 'guncelle' => array( $pelin => 1, $gece => 1 ) ) );
	$swap = (string) nwcs_history_top()['id'];
	check( 'onay (ad takasi): her uye bir kez degisir', 'Gece Mavisi' === $member( $slug, $c )['color_name'] && 'Pelin Yeşili Açık' === $member( $slug, $b )['color_name'] && 'Pelin Yeşili Açık' === $member( $slug, $d )['color_name'] && 'Gece Mavisi' === $member( $slug2, $e )['color_name'] && '#111111' === $member( $slug, $c )['color_hex'], $to );

	/* ---------- 9) Geri alma ---------- */

	$undo = nwcs_history_undo( $swap );
	check( 'geri al (ad takasi): liste ve 4 uye eski haline', ! is_wp_error( $undo ) && 4 === (int) ( $undo['report']['members'] ?? 0 ) && 'Pelin Yeşili Açık' === $member( $slug, $c )['color_name'] && '#7d8f4e' === $member( $slug, $c )['color_hex'] && 'Gece Mavisi' === $member( $slug, $b )['color_name'] && 'gece mavisi' === $member( $slug, $d )['color_name'] && '#222222' === $member( $slug, $d )['color_hex'] && '#111111' === nwcs_color_preset_hex( 'Pelin Yeşili Açık' ), is_wp_error( $undo ) ? $undo->get_error_message() : wp_json_encode( $undo['report'] ?? null ) );

	$_GET = array();
	set_site_transient( 'nwcs_history_result_' . $admin_id, $undo, MINUTE_IN_SECONDS );
	ob_start();
	nwcs_render_history_result();
	$text = text_of( (string) ob_get_clean() );
	check( 'geri alma sonucu: "Renk listesi eski hâline döndü", "4 ürünün rengi eski hâline döndü"', str_contains( $text, 'Geri alındı: Renkler (renk listesi)' ) && str_contains( $text, 'Renk listesi eski hâline döndü' ) && str_contains( $text, '4 ürünün rengi eski hâline döndü' ), $text );

	// Uyeler sonradan degisti: geri alinmaz, hicbir seye dokunulmaz.
	fresh();
	$save( array( 'renk' => rows( array( 'Pelin Yeşili Açık' => array( 'ad' => 'Pelin Yeşili Açık', 'hex' => '#5a6b3c' ) ) ) ) );
	$confirm( array( 'guncelle' => array( $pelin => 1 ) ) );
	$rec = (string) nwcs_history_top()['id'];
	$groups                            = nwcs_color_groups( $slug );
	$groups[0]['members'][2]['color_hex'] = '#999999'; // C (A, B, C, D sirasi)
	nwcs_color_groups_write( $slug, $groups );
	$undo = nwcs_history_undo( $rec );
	check( 'geri al reddi: uye sonradan degisti -> mesaj, liste ve diger uyeler yeni halde, kayit kalkar', is_wp_error( $undo ) && 'nwcs_undo_gone' === $undo->get_error_code() && str_contains( $undo->get_error_message(), 'sonradan değişmiş (ZZPAL-C)' ) && str_contains( $undo->get_error_message(), 'Hiçbir şey değişmedi' ) && '#5a6b3c' === nwcs_color_preset_hex( 'Pelin Yeşili Açık' ) && '#5a6b3c' === $member( $slug2, $e )['color_hex'] && $rec !== (string) ( nwcs_history_top()['id'] ?? '' ), is_wp_error( $undo ) ? $undo->get_error_message() : 'geri alindi' );

	// Liste sonradan baska yoldan degisti.
	fresh();
	$save( array( 'renk' => rows( array(), array( 'ad' => 'ZZ Elle', 'hex' => '#0f0f0f' ) ) ) );
	$rec  = (string) nwcs_history_top()['id'];
	$pal  = nwcs_color_palette();
	update_site_option( NWCS_COLOR_PALETTE, array_slice( $pal, 0, -1 ) );
	$undo = nwcs_history_undo( $rec );
	check( 'geri al reddi: liste baska yoldan degisti', is_wp_error( $undo ) && str_contains( $undo->get_error_message(), 'Renk listesi bu işlemden sonra başka yoldan değişmiş' ) && $rec !== (string) ( nwcs_history_top()['id'] ?? '' ) );

	/* ---------- 10) Guvenlik, menu ---------- */

	$pal = nwcs_color_palette();
	$to  = post( 'nwcs_handle_palette_save', array( 'action' => 'nwcs_palette_save', 'renk' => rows( array(), array( 'ad' => 'ZZ Nonce', 'hex' => '#123123' ) ) ), 'nwcs_palette_baska' );
	check( 'nonce: yanlis nonce yazmaz', str_starts_with( $to, 'wp_die' ) && $pal === nwcs_color_palette(), $to );
	$to = post( 'nwcs_handle_palette_confirm', array( 'action' => 'nwcs_palette_confirm' ), 'nwcs_palette_baska' );
	check( 'nonce: onayda yanlis nonce', str_starts_with( $to, 'wp_die' ), $to );

	wp_set_current_user( 0 );
	$to = post( 'nwcs_handle_palette_save', array( 'action' => 'nwcs_palette_save', 'renk' => rows( array(), array( 'ad' => 'ZZ Yetki', 'hex' => '#123123' ) ) ), 'nwcs_palette_save' );
	wp_set_current_user( $admin_id );
	check( 'yetki: yetkisiz kullanici yazamaz', str_contains( $to, 'yetkiniz yok' ) && $pal === nwcs_color_palette(), $to );

	global $submenu;
	do_action( 'network_admin_menu', '' );
	$pages = array_column( (array) ( $submenu[ NWCS_POOL_SLUG ] ?? array() ), 2 );
	check( 'menu: Renkler, Kategoriler\'in hemen ardinda', array_search( NWCS_PALETTE_SLUG, $pages, true ) === array_search( NWCS_CATEGORIES_SLUG, $pages, true ) + 1, implode( ',', $pages ) );
	$_GET = array( 'page' => NWCS_PALETTE_SLUG );
	check( 'menu: yonetim menusu katlanir (panel sayfasi)', str_contains( nwcs_fold_admin_menu( '' ), 'folded' ) );
	$_GET = array();
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

foreach ( array( NWCS_PALETTE_DRAFT, NWCS_PALETTE_PENDING, NWCS_PALETTE_NOTE, NWCS_COLOR_DRAFT, 'nwcs_cache_note_', 'nwcs_history_result_' ) as $prefix ) {
	delete_site_transient( $prefix . $admin_id );
}

update_site_option( NWCS_HISTORY, $history_before );

if ( false === $palette_before ) {
	delete_site_option( NWCS_COLOR_PALETTE );
} else {
	update_site_option( NWCS_COLOR_PALETTE, $palette_before );
}

switch_to_blog( $pool );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( 'zz-palet-deneme', 'zz-palet-diger' ) as $term_slug ) {
	$term = get_term_by( 'slug', $term_slug, NWCS_PRODUCT_TAX );

	if ( $term ) {
		wp_delete_term( (int) $term->term_id, NWCS_PRODUCT_TAX );
	}
}
restore_current_blog();
nwcs_pool_flush_cache();

check( 'temizlik: deneme kategorileri ve urunleri kalmadi', ! get_term_by( 'slug', 'zz-palet-deneme', NWCS_PRODUCT_TAX ) && ! array_intersect( array_keys( nwcs_color_map()['members'] ), array_values( $ids ) ) );
check( 'temizlik: renk listesi ayari eski haline dondu', $palette_before === get_site_option( NWCS_COLOR_PALETTE, false ) );
check( 'temizlik: "Son işlemler" eski haline dondu', $history_before === get_site_option( NWCS_HISTORY, array() ) );
check( 'baska kategorilerin renk gruplari degismedi', $metas_before === all_color_metas() );
check( 'eklentide ve temalarda uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
