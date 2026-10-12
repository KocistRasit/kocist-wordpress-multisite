<?php
/**
 * Yerel deneme: Medya Havuzu -> "Alt metinleri Excel ile düzenleyin".
 *
 * Kendi deneme kategorisini, urununu ve gorsellerini olusturur:
 *   A (urunde, alt "Eski A"), B (urunde, alt "Eski B"), C (urunde, alt yok),
 *   D (urunsuz, alt bos), V (video: gorsel degil).
 * Taslak her kapsam icin (tumu, bos, kategori) indirilir; kategori taslaginda
 * A degistirilir, B bosaltilir (silme), C ayni kalir; bilinmeyen kimlik,
 * video kimligi, cift satir ve uzun metin satir hatasi olur. Yanlis tur,
 * baska panel ve _bilgi'siz dosya reddedilir. Uygulama, geri alma (birebir
 * eski hal: meta yoksa yok), sonradan elle degistirilen gorselin korunmasi
 * ve ekranin cizimi denenir. Sonunda her seyi siler, "Son işlemler"i eski
 * haline getirir.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-alt-excel.php
 *   (ya da: docker compose cp scripts/dev/check-alt-excel.php wordpress:/tmp/ ve /tmp/ yolu)
 */

define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', true );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/wp-admin/network/admin.php';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

class Nwcs_Redirect extends Exception {}

add_filter(
	'wp_redirect',
	static function ( $location ) {
		throw new Nwcs_Redirect( $location );
	},
	99
);

$fail = 0;
function check( string $label, bool $ok, string $got = '' ): void {
	global $fail;
	echo ( $ok ? 'OK   ' : 'FAIL ' ) . $label . ( $ok || '' === $got ? '' : '  (' . $got . ')' ) . "\n";
	$fail += $ok ? 0 : 1;
}

// Eklentinin kendi kodunda uyari/notice olmasin.
$notices = array();
set_error_handler(
	static function ( int $no, string $message, string $file, int $line ) use ( &$notices ): bool {
		if ( ( error_reporting() & $no ) && str_contains( $file, 'network-content-studio' ) ) {
			$notices[] = "$message @ " . basename( $file ) . ":$line";
		}

		return false;
	},
	E_ALL
);

wp_set_current_user( get_user_by( 'login', get_super_admins()[0] )->ID );

$history_before = get_site_option( NWCS_HISTORY, array() );
$pool           = nwcs_pool_blog_id();

/* ---------- Deneme verisi ---------- */

switch_to_blog( $pool );

$term = wp_insert_term( 'ZZ Alt Deneme', NWCS_PRODUCT_TAX, array( 'slug' => 'zz-alt-deneme' ) );
$term = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];

$img = array();
foreach ( array( 'a' => 'Eski A', 'b' => 'Eski B', 'c' => null, 'd' => '' ) as $key => $alt ) {
	$img[ $key ] = wp_insert_attachment(
		array(
			'post_title'     => "zz-alt-$key",
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		),
		"zz/zz-alt-$key.jpg"
	);

	if ( null !== $alt ) {
		update_post_meta( $img[ $key ], NWCS_ALT_META, $alt );
	}
}

$video = wp_insert_attachment(
	array(
		'post_title'     => 'zz-alt-video',
		'post_mime_type' => 'video/mp4',
		'post_status'    => 'inherit',
	),
	'zz/zz-alt-video.mp4'
);

$product = wp_insert_post(
	array(
		'post_type'   => NWCS_PRODUCT_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'ZZ alt deneme ürünü',
	)
);
update_post_meta( $product, '_nwcs_code', 'ZZALT1' );
// Galeride video ve silinmis bir kimlik de var: ne taslaga ne secim listesindeki sayiya girer.
update_post_meta( $product, '_nwcs_gallery', array( $img['a'], $img['b'], $img['c'], $video, 99999998 ) );
wp_set_object_terms( $product, array( $term ), NWCS_PRODUCT_TAX );

restore_current_blog();
nwcs_pool_flush_cache();

/** Gorselin ham alt metni (meta yoksa null). */
function alt_of( int $id ): ?string {
	switch_to_blog( nwcs_pool_blog_id() );
	$raw = nwcs_alt_raw( $id );
	restore_current_blog();

	return $raw;
}

/** Taslagi indirir, satirlarini (ve _bilgi'sini) $edit ile degistirip yeni dosyaya yazar. */
function edited( string $scope, callable $edit, ?callable $edit_info = null, bool $with_info = true ): string {
	$built = nwcs_alt_build_template( $scope );
	$rows  = nwcs_xlsx_read_rows( $built['path'], NWCS_ALT_SHEET )['rows'];
	$info  = nwcs_xlsx_read_rows( $built['path'], NWCS_SYNC_INFO )['rows'];
	wp_delete_file( $built['path'] );

	$sheets = array( array( 'name' => NWCS_ALT_SHEET, 'rows' => $edit( $rows ) ) );

	if ( $with_info ) {
		$sheets[] = array( 'name' => NWCS_SYNC_INFO, 'rows' => $edit_info ? $edit_info( $info ) : $info, 'hidden' => true, 'header' => false );
	}

	$path = nwcs_import_temp_xlsx();
	nwcs_xlsx_write( $path, $sheets );

	return $path;
}

/** Satirlari kimlige gore. */
function by_id( array $rows ): array {
	$out = array();
	foreach ( array_slice( $rows, 1 ) as $row ) {
		$out[ (int) $row[0] ] = $row;
	}

	return $out;
}

try {
	/* ---------- 1) Taslaklar ---------- */

	foreach ( array( 'tumu', 'bos', 'kategori:zz-alt-deneme' ) as $scope ) {
		$built = nwcs_alt_build_template( $scope );
		check( "taslak $scope: dosya hazir", ! is_wp_error( $built ) && file_exists( $built['path'] ), is_wp_error( $built ) ? $built->get_error_message() : '' );

		if ( is_wp_error( $built ) ) {
			continue;
		}

		$rows = nwcs_xlsx_read_rows( $built['path'], NWCS_ALT_SHEET )['rows'];
		$info = array_column( nwcs_xlsx_read_rows( $built['path'], NWCS_SYNC_INFO )['rows'], 1, 0 );
		$ids  = array_keys( by_id( $rows ) );
		wp_delete_file( $built['path'] );

		check( "taslak $scope: basliklar", array( 'KİMLİK', 'DOSYA', 'ÜRÜN', 'SİTE', 'ALT METİN' ) === $rows[0], implode( '|', $rows[0] ) );
		check( "taslak $scope: _bilgi tur=alt ve kaynak", 'alt' === ( $info['tur'] ?? '' ) && untrailingslashit( (string) ( $info['kaynak'] ?? '' ) ) === untrailingslashit( network_home_url( '/' ) ) );
		check( "taslak $scope: video yok", ! in_array( $video, $ids, true ) );

		if ( 'tumu' === $scope ) {
			check( 'taslak tumu: A, B, C, D var', ! array_diff( array_values( $img ), $ids ) );
			$a = by_id( $rows )[ $img['a'] ];
			check( 'taslak tumu: A satiri dosya, urun, alt', 'zz-alt-a.jpg' === $a[1] && str_contains( $a[2], 'ZZALT1 · ZZ alt deneme ürünü' ) && 'Eski A' === $a[4], implode( '|', $a ) );
			check( 'taslak tumu: D urunsuz (ÜRÜN bos)', '' === by_id( $rows )[ $img['d'] ][2] );
			check( 'taslak tumu: sayi = nwcs_alt_image_ids', count( $ids ) === count( nwcs_alt_image_ids() ) );
		} elseif ( 'bos' === $scope ) {
			check( 'taslak bos: C ve D var', in_array( $img['c'], $ids, true ) && in_array( $img['d'], $ids, true ) );
			check( 'taslak bos: A ve B yok', ! in_array( $img['a'], $ids, true ) && ! in_array( $img['b'], $ids, true ) );
		} else {
			check( 'taslak kategori: tam olarak A, B, C (galeri sirasi)', array( $img['a'], $img['b'], $img['c'] ) === $ids, implode( ',', $ids ) );
			$count = nwcs_alt_category_counts( nwcs_alt_image_ids() )['zz-alt-deneme'] ?? -1;
			check( 'secim listesi sayisi = taslak satir sayisi (3)', 3 === $count && count( $ids ) === $count, (string) $count );
		}
	}

	check( 'taslak: bilinmeyen kapsam hata', is_wp_error( nwcs_alt_build_template( 'kategori:yok-boyle-kategori' ) ) );

	/* ---------- 2) Plan ---------- */

	$long = str_repeat( 'uzun ', 59 ) . 'uzun!'; // 300 karakter (sonda bosluk yok: temizlik kirpmasin)
	$path = edited(
		'kategori:zz-alt-deneme',
		static function ( array $rows ) use ( $img, $video, $long ): array {
			foreach ( $rows as $i => $row ) {
				if ( (int) $row[0] === $img['a'] ) {
					$rows[ $i ][4] = "  Yeni A \\ ters\nbolu  ";
					$rows[ $i ][2] = 'ÜRÜN sütunu değişse de önemsiz';
				} elseif ( (int) $row[0] === $img['b'] ) {
					$rows[ $i ][4] = '';
				}
			}
			$rows[] = array( '99999999', 'yok.jpg', '', '', 'bir şey' );
			$rows[] = array( (string) $video, 'video', '', '', 'video alt' );
			$rows[] = array( (string) $img['d'], 'zz-alt-d.jpg', '', '', $long );
			$rows[] = array( (string) $img['c'], 'zz-alt-c.jpg', '', '', 'cift satir' );
			$rows[] = array( '', 'kimliksiz.jpg', '', '', 'x' );

			return $rows;
		}
	);
	$plan = nwcs_alt_plan( $path, 'deneme.xlsx' );
	wp_delete_file( $path );

	check( 'plan: hata yok', ! is_wp_error( $plan ), is_wp_error( $plan ) ? $plan->get_error_message() : '' );
	check( 'plan: 1 degisecek (A)', 1 === count( $plan['update'] ) && $img['a'] === $plan['update'][0]['id'] );
	check( 'plan: A yeni deger tek satir, ters bolu korunur', 'Yeni A \\ ters bolu' === ( $plan['update'][0]['new'] ?? '' ), (string) ( $plan['update'][0]['new'] ?? '' ) );
	check( 'plan: 1 silinecek (B)', 1 === count( $plan['clear'] ) && $img['b'] === $plan['clear'][0]['id'] );
	check( 'plan: 1 degismedi (C)', 1 === $plan['same'], (string) $plan['same'] );
	check( 'plan: 5 hata (bilinmeyen, video, uzun, cift, kimliksiz)', 5 === count( $plan['errors'] ), wp_json_encode( $plan['errors'], JSON_UNESCAPED_UNICODE ) );
	$messages = implode( ' / ', array_column( $plan['errors'], 'message' ) );
	check( 'plan: hata metinleri', str_contains( $messages, 'bulunamadı' ) && str_contains( $messages, 'görsele değil' ) && str_contains( $messages, 'çok uzun (300' ) && str_contains( $messages, 'satırda da var' ) && str_contains( $messages, 'KİMLİK hücresi boş' ), $messages );
	check( 'plan: hicbir sey yazilmadi', 'Eski A' === alt_of( $img['a'] ) && 'Eski B' === alt_of( $img['b'] ) );

	// Reddedilen dosyalar.
	$wrong = edited( 'tumu', static fn( array $rows ): array => $rows, static fn( array $info ): array => array_map( static fn( array $row ): array => 'tur' === $row[0] ? array( 'tur', 'aciklamalar' ) : $row, $info ) );
	$got   = nwcs_alt_plan( $wrong, 'x.xlsx' );
	wp_delete_file( $wrong );
	check( 'red: yanlis tur (aciklamalar)', is_wp_error( $got ) && str_contains( $got->get_error_message(), 'Ürün açıklamaları' ), is_wp_error( $got ) ? $got->get_error_message() : 'kabul edildi' );

	$other = edited( 'tumu', static fn( array $rows ): array => $rows, static fn( array $info ): array => array_map( static fn( array $row ): array => 'kaynak' === $row[0] ? array( 'kaynak', 'https://baska-panel.example/' ) : $row, $info ) );
	$got   = nwcs_alt_plan( $other, 'x.xlsx' );
	wp_delete_file( $other );
	check( 'red: baska panel', is_wp_error( $got ) && 'nwcs_other_panel' === $got->get_error_code() );

	$bare = edited( 'tumu', static fn( array $rows ): array => $rows, null, false );
	$got  = nwcs_alt_plan( $bare, 'x.xlsx' );
	check( 'red: _bilgi yok (panel taslagi degil)', is_wp_error( $got ) && 'nwcs_not_template' === $got->get_error_code() );
	wp_delete_file( $bare );

	$alt_file = edited( 'tumu', static fn( array $rows ): array => $rows );
	$got      = nwcs_desc_plan( $alt_file, 'x.xlsx' );
	wp_delete_file( $alt_file );
	check( 'red: alt dosyasi aciklamalar ekraninda Medya Havuzu’nu soyler', is_wp_error( $got ) && str_contains( $got->get_error_message(), 'Medya Havuzu' ), is_wp_error( $got ) ? $got->get_error_message() : 'kabul edildi' );

	/* ---------- 3) Onizleme ekrani ---------- */

	$key = NWCS_ALT_PLAN . get_current_user_id();
	set_site_transient( $key, $plan, HOUR_IN_SECONDS );
	$_GET = array( 'page' => NWCS_MEDIA_SLUG, 'adim' => 'alt-onizleme' );
	ob_start();
	nwcs_render_media();
	$html = ob_get_clean();
	check( 'ekran: onizleme "1 görselin alt metni silinecek"', str_contains( $html, '1 görselin alt metni silinecek' ) );
	check( 'ekran: onizlemede B dosyasi ve Uygula dugmesi', str_contains( $html, 'zz-alt-b.jpg' ) && str_contains( $html, 'Değişiklikleri uygula' ) );
	check( 'ekran: onizlemede gorsel listesi cizilmez', ! str_contains( $html, 'id="nwcs-media-bulk"' ) );

	$_GET = array( 'page' => NWCS_MEDIA_SLUG );
	ob_start();
	nwcs_render_media();
	$html = ob_get_clean();
	check( 'ekran: kart ve kapsam secimi', str_contains( $html, 'Alt metinleri Excel ile düzenleyin' ) && str_contains( $html, 'value="kategori:zz-alt-deneme"' ) && str_contains( $html, 'Alt metni boş olanlar' ) );

	/* ---------- 4) Uygula (istek yolu) ---------- */

	$_POST                = array( 'plan' => (string) $plan['time'], '_wpnonce' => wp_create_nonce( 'nwcs_alt_apply' ) );
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];
	$to                   = '';
	try {
		nwcs_handle_alt_apply();
	} catch ( Nwcs_Redirect $redirect ) {
		$to = $redirect->getMessage();
	}
	check( 'uygula: alt-uygulandi adresine doner', str_contains( $to, 'adim=alt-uygulandi' ), $to );
	check( 'uygula: onizleme silindi', false === get_site_transient( $key ) );
	check( 'uygula: A yeni deger (ters bolu dahil)', 'Yeni A \\ ters bolu' === alt_of( $img['a'] ), (string) alt_of( $img['a'] ) );
	check( 'uygula: B alt metni silindi (meta yok)', null === alt_of( $img['b'] ) );
	check( 'uygula: C dokunulmadi (meta yok)', null === alt_of( $img['c'] ) );
	check( 'uygula: D dokunulmadi', '' === alt_of( $img['d'] ) );

	$top = nwcs_history_top();
	check( 'gecmis: en ustte alt kaydi, 1 degisti 1 silindi', 'alt' === ( $top['kind'] ?? '' ) && 1 === (int) $top['counts']['updated'] && 1 === (int) $top['counts']['cleared'] && ! empty( $top['complete'] ) );
	$info = nwcs_history_describe( $top );
	check( 'gecmis: ozet', 'görsel alt metinleri' === $info['kind'] && str_contains( $info['facts'], '1 değişti' ) && str_contains( $info['facts'], '1 silindi' ), $info['kind'] . ': ' . $info['facts'] );

	$_GET = array( 'page' => NWCS_MEDIA_SLUG, 'adim' => 'alt-uygulandi' );
	ob_start();
	nwcs_render_media();
	$html = ob_get_clean();
	check( 'ekran: sonuc ve Son işlemler karti', str_contains( $html, 'Alt metinler güncellendi' ) && str_contains( $html, 'id="nwcs-history"' ) );

	// Ikinci "Uygula" (onizleme silinmis): yazmaz, hata sayfasina doner.
	try {
		nwcs_handle_alt_apply();
	} catch ( Nwcs_Redirect $redirect ) {
		$to = $redirect->getMessage();
	}
	check( 'uygula: ikinci basis yazmaz', str_contains( $to, 'adim=alt-hata' ) && nwcs_history_top()['id'] === $top['id'] );

	/* ---------- 5) Geri al: birebir eski hal ---------- */

	$undo = nwcs_history_undo( (string) $top['id'] );
	check( 'geri al: hata yok', ! is_wp_error( $undo ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	check( 'geri al: 2 gorsel dondu', 2 === (int) ( $undo['report']['alts'] ?? 0 ) );
	check( 'geri al: A "Eski A"', 'Eski A' === alt_of( $img['a'] ) );
	check( 'geri al: B "Eski B"', 'Eski B' === alt_of( $img['b'] ) );
	check( 'geri al: C hala meta yok', null === alt_of( $img['c'] ) );
	check( 'geri al: kayit yigindan cikti', ( nwcs_history_top()['id'] ?? '' ) !== $top['id'] );

	/* ---------- 6) Sonradan elle degistirilen korunur ---------- */

	$plan2 = nwcs_alt_plan(
		$p = edited(
			'tumu',
			static function ( array $rows ) use ( $img ): array {
				foreach ( $rows as $i => $row ) {
					if ( (int) $row[0] === $img['a'] ) {
						$rows[ $i ][4] = 'İkinci A';
					} elseif ( (int) $row[0] === $img['c'] ) {
						$rows[ $i ][4] = 'Yeni C';
					}
				}

				return $rows;
			}
		),
		'ikinci.xlsx'
	);
	wp_delete_file( $p );
	check( 'ikinci plan: A ve C degisecek', 2 === count( $plan2['update'] ) && 0 === count( $plan2['clear'] ), wp_json_encode( array_column( $plan2['update'], 'id' ) ) );

	// Onizlemeden sonra A elle degisti: uygulamada atlanir.
	switch_to_blog( $pool );
	update_post_meta( $img['a'], NWCS_ALT_META, 'Elle A' );
	restore_current_blog();
	$record = nwcs_alt_apply( $plan2 );
	check( 'ikinci uygulama: A atlandi (onizlemeden sonra degisti)', in_array( 'zz-alt-a.jpg', $record['skipped'], true ) && 'Elle A' === alt_of( $img['a'] ) );
	check( 'ikinci uygulama: C yazildi', 'Yeni C' === alt_of( $img['c'] ) );

	// Uygulamadan sonra C elle degisti: geri alma dokunmaz.
	switch_to_blog( $pool );
	update_post_meta( $img['c'], NWCS_ALT_META, 'Elle C' );
	restore_current_blog();
	$undo = nwcs_history_undo( (string) $record['id'] );
	check( 'ikinci geri al: C korundu (kept)', ! is_wp_error( $undo ) && in_array( 'zz-alt-c.jpg', (array) $undo['report']['kept'], true ) && 'Elle C' === alt_of( $img['c'] ) );
} catch ( Throwable $error ) {
	check( 'beklenmeyen hata: ' . $error->getMessage() . ' @ ' . basename( $error->getFile() ) . ':' . $error->getLine(), false );
}

/* ---------- Temizlik ---------- */

delete_site_transient( NWCS_ALT_PLAN . get_current_user_id() );
delete_site_transient( 'nwcs_cache_note_' . get_current_user_id() );
delete_site_transient( 'nwcs_history_result_' . get_current_user_id() );
update_site_option( NWCS_HISTORY, $history_before );

switch_to_blog( $pool );
wp_delete_post( $product, true );
foreach ( array_merge( array_values( $img ), array( $video ) ) as $id ) {
	wp_delete_attachment( $id, true );
}
wp_delete_term( $term, NWCS_PRODUCT_TAX );
restore_current_blog();
nwcs_pool_flush_cache();

check( 'eklentide uyari yok', ! $notices, implode( ' | ', array_slice( $notices, 0, 5 ) ) );

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
