<?php
/**
 * Yerel deneme: ÜRÜN KODU zorunlu (0.23.0). Kategori taslagi indirilir,
 * satirlar degistirilip yeniden yazilir: kodsuz yeni satir hata, kodlu yeni
 * satir eklenir, mevcut urunun kodu silinirse satir hata (urun cope gitmez),
 * eski baslik ("ÜRÜN KODU", yildizsiz) da taninir, kod sutunu yoksa dosya
 * reddedilir. Urun formunda bos kod kaydedilmez. Uygulanan degisiklik geri
 * alinir; gecmis eski haline doner.
 *
 *   docker compose exec -T wordpress php /scripts/dev/check-required-code.php
 */

define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', true );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/wp-admin/network/admin.php';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

class Nwcs_Redirect extends Exception {}
add_filter( 'wp_redirect', static function ( $location ) { throw new Nwcs_Redirect( $location ); } );

$fail = 0;
function check( string $label, bool $ok, string $got = '' ): void {
	global $fail;
	echo ( $ok ? 'OK   ' : 'FAIL ' ) . $label . ( $ok || '' === $got ? '' : '  (' . $got . ')' ) . "\n";
	$fail += $ok ? 0 : 1;
}

wp_set_current_user( get_user_by( 'login', get_super_admins()[0] )->ID );
$history = get_site_option( NWCS_HISTORY );
$slug    = 'kamelyalar';

/** Taslagi indirir, satirlari $edit ile degistirip yeni dosyaya yazar. */
function edited_template( string $slug, callable $edit ): string {
	$built = nwcs_sync_build_template( $slug );
	$rows  = nwcs_xlsx_read_rows( $built['path'], NWCS_SYNC_SHEET )['rows'];
	$info  = nwcs_xlsx_read_rows( $built['path'], NWCS_SYNC_INFO )['rows'];
	wp_delete_file( $built['path'] );

	$path = nwcs_import_temp_xlsx();
	nwcs_xlsx_write(
		$path,
		array(
			array( 'name' => NWCS_SYNC_SHEET, 'rows' => $edit( $rows ) ),
			array( 'name' => NWCS_SYNC_INFO, 'rows' => $info, 'hidden' => true, 'header' => false ),
		)
	);

	return $path;
}

$built  = nwcs_sync_build_template( $slug );
$header = nwcs_xlsx_read_rows( $built['path'], NWCS_SYNC_SHEET )['rows'][0];
wp_delete_file( $built['path'] );
check( 'taslak: ÜRÜN KODU sütunu zorunlu işaretli', 'ÜRÜN KODU *' === $header[1], (string) $header[1] );
check( 'Nasıl kullanılır: otomatik kod cümlesi yok', ! preg_grep( '/kendiliğinden verilir|boş kalırsa kod/u', array_column( nwcs_sync_help_rows( 'X' ), 0 ) ) );

$width = count( $header );
$blank = static fn( array $cells ): array => array_pad( $cells, $width, '' );

$path = edited_template(
	$slug,
	static function ( array $rows ) use ( $blank ): array {
		$rows[1][1] = '';                                                       // mevcut urunun kodu silindi
		$rows[]     = $blank( array( '', '', 'Deneme kodsuz ürün' ) );            // kodsuz yeni
		$rows[]     = $blank( array( '', 'ZZ-DENEME-01', 'Deneme kodlu ürün' ) ); // kodlu yeni
		$rows[0][1] = 'ÜRÜN KODU';                                              // eski baslik (yildizsiz)

		return $rows;
	}
);

$plan = nwcs_sync_plan( $path, 'deneme.xlsx' );
wp_delete_file( $path );
check( 'plan hatasiz okundu (eski başlık tanındı)', ! is_wp_error( $plan ), is_wp_error( $plan ) ? $plan->get_error_message() : '' );

$messages = array_column( (array) $plan['errors'], 'message', 'name' );
$empty    = array_filter( $messages, static fn( string $m ): bool => str_starts_with( $m, 'ÜRÜN KODU boş.' ) );
check( 'kodsuz yeni satır: hata', isset( $empty['Deneme kodsuz ürün'] ), wp_json_encode( $messages, JSON_UNESCAPED_UNICODE ) );
// Beklenen: kodu silinen satir + kodsuz yeni satir + kategoride zaten kodu olmayan urunler.
$codeless = count( array_filter( nwcs_sync_category_products( $slug ), static fn( array $p ): bool => '' === (string) $p['code'] ) );
check( sprintf( 'kodu silinen mevcut satır ve %d kodsuz ürün: hata', $codeless ), 2 + $codeless === count( $empty ), (string) count( $empty ) );
check( 'hata metni', 'ÜRÜN KODU boş. Bu satıra ürünün kodunu yazın; her ürünün kodu olmalı, fotoğraflar ürüne bu kodla bağlanır.' === ( $empty['Deneme kodsuz ürün'] ?? '' ) );
check( 'kodu silinen ürün çöp kutusuna gitmez', ! $plan['trash'], (string) count( $plan['trash'] ) );
check( 'kodlu yeni satır: eklenecek', 1 === count( $plan['create'] ) && 'ZZ-DENEME-01' === $plan['create'][0]['data']['code'] );

$record = nwcs_sync_apply( $plan, false );
switch_to_blog( nwcs_pool_blog_id() );
$new = (int) ( $record['created'][0] ?? 0 );
check( 'uygula: yeni ürün kendi koduyla', $new && 'ZZ-DENEME-01' === get_post_meta( $new, '_nwcs_code', true ) );
restore_current_blog();
$undo = nwcs_history_undo( (string) $record['id'] );
check( 'geri al', ! is_wp_error( $undo ) && 1 === $undo['report']['removed'] );
switch_to_blog( nwcs_pool_blog_id() );
wp_delete_post( $new, true );
restore_current_blog();

// Kod sutunu tamamen silinmis dosya.
$path = edited_template(
	$slug,
	static function ( array $rows ): array {
		foreach ( $rows as &$row ) {
			array_splice( $row, 1, 1 );
		}

		return $rows;
	}
);
$plan = nwcs_sync_plan( $path, 'deneme.xlsx' );
wp_delete_file( $path );
check( 'kod sütunu yok: dosya reddedilir', is_wp_error( $plan ) && str_contains( $plan->get_error_message(), 'ÜRÜN KODU' ) );

// Urun formu: bos kod kaydedilmez, girilenler taslakta.
$_POST = $_REQUEST = array( 'urun' => '0', 'title' => 'Deneme formu', 'code' => '  ', 'price' => '123', '_wpnonce' => wp_create_nonce( 'nwcs_pool_save_0' ) );
$before = (int) wp_count_posts( NWCS_PRODUCT_TYPE )->publish;
try {
	nwcs_handle_pool_save();
	$to = '';
} catch ( Nwcs_Redirect $r ) {
	$to = $r->getMessage();
}
restore_current_blog();
check( 'form: boş kod -> code_empty, taslak', str_contains( $to, 'nwcs_pool=code_empty' ) && str_contains( $to, 'taslak=1' ), $to );
$draft = get_transient( 'nwcs_pool_draft_' . get_current_user_id() );
check( 'form: girilenler korunur', is_array( $draft ) && 'Deneme formu' === $draft['title'] && '123' === $draft['price'] && 'code_empty' === $draft['error'] );
delete_transient( 'nwcs_pool_draft_' . get_current_user_id() );
switch_to_blog( nwcs_pool_blog_id() );
check( 'form: ürün oluşmadı', $before === (int) wp_count_posts( NWCS_PRODUCT_TYPE )->publish );
restore_current_blog();

update_site_option( NWCS_HISTORY, $history );
nwcs_pool_flush_cache();

echo $fail ? "\n$fail hata\n" : "\nHepsi gecti\n";
