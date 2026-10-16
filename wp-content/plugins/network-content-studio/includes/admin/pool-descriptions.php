<?php
/**
 * Ürün Havuzu -> Ürün açıklamaları.
 *
 * Uzun aciklamalar (urunun detay sayfasi metni, post_content) ayri bir
 * dosyayla: KİMLİK (gizli) | ÜRÜN KODU | ÜRÜN ADI | ÜRÜN AÇIKLAMASI. 0.26.0'dan
 * beri kategori Excel'inde de ayni sutun var (ayni donusum fonksiyonlari,
 * admin/sync.php); bu ekran tum urunleri tek dosyada tasimak icin kaldi.
 *
 * HTML <-> duz metin:
 *   - indirme: basliklar ve yalnizca kalin yazidan olusan satirlar kendi
 *     satirinda, paragraflar bos satirla ayrilir, madde listesi "• " ile,
 *     alinti "> " ile; gorsel, baglanti gibi karmasik HTML iceren aciklama
 *     "[HTML içerik — panelden düzenleyin]" olarak yazilir ve o hucre
 *     degismedikce yuklemede atlanir;
 *   - yukleme: bos satir yeni paragraf, noktasiz kisa tek satir alt baslik,
 *     "• " ile baslayan satirlar madde listesi, "> " ile baslayanlar alinti.
 * Aciklamadaki tablolar (Kocist'in olcu/model tablolari, 182 urun) dosyaya
 * girmez ve hic degismez: metin yazilirken sona aynen eklenir. Boylece
 * tablolu aciklamalarin metni de Excel'den duzenlenebilir; "EK İÇERİK"
 * sutunu hangi urunde tablo oldugunu bilgi olarak gosterir.
 * Degismeyen hucre (metni indirilen metinle ayni) urune dokunmaz; boylece
 * yalnizca duzenlenen aciklamalar yeniden bicimlenir.
 *
 * Bos hucre aciklamayi siler. Urun sayfasi kapanmaz: WOOD KOCIST ve
 * Kocist'te aciklamasiz urunun sayfasi ad, gorsel ve teknik detaylarla
 * acik kalir (nwcs_product_has_page).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_DESC_SLUG   = 'nwcs-pool-descriptions';
const NWCS_DESC_KIND   = 'aciklamalar';
const NWCS_DESC_SHEET  = 'Açıklamalar';
const NWCS_DESC_PLAN   = 'nwcs_desc_plan_';
const NWCS_DESC_MARKER = '[HTML içerik — panelden düzenleyin]';

add_action( 'network_admin_menu', 'nwcs_register_descriptions_menu', 16 );
function nwcs_register_descriptions_menu(): void {
	add_submenu_page( NWCS_POOL_SLUG, 'Ürün açıklamaları', 'Ürün açıklamaları', NWCS_CAPABILITY, NWCS_DESC_SLUG, 'nwcs_render_descriptions' );
}

function nwcs_descriptions_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => NWCS_DESC_SLUG ), $args ), network_admin_url( 'admin.php' ) );
}

/* ====================================================================== *
 * HTML <-> metin
 * ====================================================================== */

/**
 * Aciklamayi metin ve tablolara ayirir. Tablo (varsa sarmalayan <div> ile)
 * metinden cikarilir; yuklemede aynen geri eklenir.
 *
 * @return array{text:string, tables:string[]}
 */
function nwcs_desc_split( string $html ): array {
	$tables = array();

	$text = (string) preg_replace_callback(
		'#(?:<div\b[^>]*>\s*)?<table\b.*?</table>(?:\s*</div>)?#is',
		static function ( array $match ) use ( &$tables ): string {
			$tables[] = $match[0];
			return "\n";
		},
		$html
	);

	return array( 'text' => trim( $text ), 'tables' => $tables );
}

/**
 * Aciklama duz metne cevrilemeyecek kadar karmasik mi (gorsel, baglanti,
 * gomulu icerik...)? Tablolar nwcs_desc_split ile once ayrilir.
 */
function nwcs_desc_is_complex( string $html ): bool {
	preg_match_all( '#<\s*([a-z0-9]+)#i', nwcs_desc_split( $html )['text'], $tags );

	$simple = array( 'p', 'br', 'strong', 'b', 'em', 'i', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'span', 'blockquote' );

	foreach ( $tags[1] as $tag ) {
		if ( ! in_array( strtolower( $tag ), $simple, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Aciklamanin Excel hucresindeki hali (tablolar haric).
 */
function nwcs_desc_html_to_text( string $html ): string {
	$html = trim( $html );

	if ( '' === $html ) {
		return '';
	}

	if ( nwcs_desc_is_complex( $html ) ) {
		return NWCS_DESC_MARKER;
	}

	$html = nwcs_desc_split( $html )['text'];

	if ( '' === $html ) {
		return '';
	}

	if ( false === stripos( $html, '<p' ) && false === stripos( $html, '<h' ) && false === stripos( $html, '<ul' ) && false === stripos( $html, '<ol' ) ) {
		$html = wpautop( $html );
	}

	$html = (string) preg_replace( '#<h[2-4][^>]*>(.*?)</h[2-4]>#is', "\n\n$1\n\n", $html );
	$html = (string) preg_replace( '#<p[^>]*>\s*<(strong|b)>([^<]*)</\1>\s*</p>#is', "\n\n$2\n\n", $html );
	$html = (string) preg_replace( '#<li[^>]*>(.*?)</li>#is', "\n• $1", $html );
	// Alinti: her satiri "> " ile.
	$html = (string) preg_replace_callback(
		'#<blockquote[^>]*>(.*?)</blockquote>#is',
		static function ( array $match ): string {
			$inner = trim( html_entity_decode( wp_strip_all_tags( (string) preg_replace( '#</p>\s*<p[^>]*>|<br\s*/?>#i', "\n", $match[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			return "\n\n" . implode( "\n", array_map( static fn( string $line ): string => '> ' . trim( $line ), explode( "\n", $inner ) ) ) . "\n\n";
		},
		$html
	);
	$html = (string) preg_replace( '#</?(ul|ol)[^>]*>#i', "\n\n", $html );
	$html = (string) preg_replace( '#<br\s*/?>#i', "\n", $html );
	$html = (string) preg_replace( '#</?p[^>]*>#i', "\n\n", $html );

	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = str_replace( array( "\r\n", "\r", "\u{00A0}" ), array( "\n", "\n", ' ' ), $text );
	$text = (string) preg_replace( '/[ \t]+\n/', "\n", $text );
	$text = (string) preg_replace( '/\n[ \t]+/', "\n", $text );
	$text = (string) preg_replace( "/\n{3,}/", "\n\n", $text );

	return trim( $text );
}

/**
 * Excel hucresindeki metni aciklama HTML'ine cevirir. $tables (mevcut
 * aciklamanin tablolari) sona aynen eklenir.
 */
function nwcs_desc_text_to_html( string $text, array $tables = array() ): string {
	$text   = trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
	$blocks = preg_split( '/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
	$html   = '';

	foreach ( $blocks as $block ) {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $block ) ), 'strlen' ) );

		if ( ! $lines ) {
			continue;
		}

		$bullets = array_filter( $lines, static fn( string $line ): bool => (bool) preg_match( '/^[•\-\*]\s+/u', $line ) );
		$quotes  = array_filter( $lines, static fn( string $line ): bool => str_starts_with( $line, '>' ) );

		if ( count( $quotes ) === count( $lines ) ) {
			$html .= '<blockquote><p>' . implode( "<br />\n", array_map( static fn( string $line ): string => esc_html( ltrim( substr( $line, 1 ) ) ), $lines ) ) . "</p></blockquote>\n";
			continue;
		}

		if ( count( $bullets ) === count( $lines ) ) {
			$html .= '<ul>' . implode( '', array_map( static fn( string $line ): string => '<li>' . esc_html( (string) preg_replace( '/^[•\-\*]\s+/u', '', $line ) ) . '</li>', $lines ) ) . "</ul>\n";
			continue;
		}

		// Noktasiz kisa tek satir: alt baslik. Hucrede tek blok varsa (kisa bir
		// aciklama) baslik yapilmaz; tum aciklama kalin gorunurdu.
		if ( count( $blocks ) > 1 && 1 === count( $lines ) && mb_strlen( $lines[0] ) <= 80 && ! preg_match( '/[.!?…:;,]$/u', $lines[0] ) ) {
			$html .= '<p><strong>' . esc_html( $lines[0] ) . "</strong></p>\n";
			continue;
		}

		$html .= '<p>' . implode( "<br />\n", array_map( 'esc_html', $lines ) ) . "</p>\n";
	}

	$html = trim( wp_kses_post( $html ) );

	// Tablolar metinle birlikte gelmez; oldugu gibi korunur.
	foreach ( $tables as $table ) {
		$html .= "\n" . $table;
	}

	return trim( $html );
}

/**
 * Karsilastirma icin metin (bosluklar tek).
 */
function nwcs_desc_fold( string $text ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/* ====================================================================== *
 * Indirme, plan, uygulama, geri alma
 * ====================================================================== */

/**
 * @return array{path:string, filename:string}|WP_Error
 */
function nwcs_desc_build_template( string $slug ) {
	$categories = nwcs_pool_categories();

	if ( '' !== $slug && ! isset( $categories[ $slug ] ) ) {
		return new WP_Error( 'nwcs_cat', 'Kategori bulunamadı; silinmiş olabilir. Sayfayı yenileyip yeniden seçin.' );
	}

	$products = '' === $slug ? nwcs_pool_products() : nwcs_sync_category_products( $slug );
	$name     = '' === $slug ? 'Tüm ürünler' : $categories[ $slug ]['name'];
	$rows     = array( array( 'KİMLİK', 'ÜRÜN KODU', 'ÜRÜN ADI', 'ÜRÜN AÇIKLAMASI', 'EK İÇERİK (bilgi)' ) );

	foreach ( $products as $product ) {
		$tables = count( nwcs_desc_split( (string) $product['body'] )['tables'] );
		$rows[] = array(
			(string) $product['id'],
			(string) $product['code'],
			(string) $product['title'],
			nwcs_desc_html_to_text( (string) $product['body'] ),
			$tables ? sprintf( '%d tablo: bu dosyada yok, değişmez', $tables ) : '',
		);
	}

	$now  = time();
	$path = nwcs_import_temp_xlsx();

	$write = nwcs_xlsx_write(
		$path,
		array(
			array(
				'name'    => NWCS_DESC_SHEET,
				'rows'    => $rows,
				'columns' => array(
					0 => array( 'hidden' => true, 'width' => 8 ),
					1 => array( 'width' => 16 ),
					2 => array( 'width' => 36 ),
					3 => array( 'width' => 100, 'wrap' => true ),
					4 => array( 'width' => 26 ),
				),
			),
			array(
				'name'    => 'Nasıl kullanılır',
				'columns' => array( 0 => array( 'width' => 110, 'wrap' => true ) ),
				'rows'    => array(
					array( 'Bu dosya “' . $name . '” için ürün açıklamalarıdır (ürün sayfasındaki uzun metin). Her satır bir üründür.' ),
					array( '1. Yalnızca ÜRÜN AÇIKLAMASI sütununu değiştirin. Ürünler gizli bir sütunla ve ÜRÜN KODU ile tanınır. ÜRÜN ADI yalnızca bilgi içindir; burada değiştirmek ürünün adını değiştirmez.' ),
					array( '2. Paragrafları boş bir satırla ayırın. Hücre içinde yeni satır: Alt+Enter (Mac’te Option+Enter).' ),
					array( '3. Noktasız, kısa (en fazla 80 karakter), tek başına duran bir satır alt başlık olur (örnek: “Teknik özellikler”). Sonunda nokta, virgül, iki nokta gibi bir işaret varsa ya da açıklama tek paragraftan oluşuyorsa başlık yapılmaz.' ),
					array( '4. “• ”, “- ” ya da “* ” ile başlayan satırlar madde listesi olur.' ),
					array( '5. “> ” ile başlayan satırlar alıntı (vurgulu not) olur.' ),
					array( '6. Hücreyi boşaltırsanız açıklama silinir. Ürün sayfası açık kalır; yalnızca açıklama bölümü görünmez.' ),
					array( '7. Açıklamadaki tablolar bu dosyada yok ve hiç değişmez (EK İÇERİK sütununda yazar).' ),
					array( '8. “' . NWCS_DESC_MARKER . '” yazan hücrede görsel ya da bağlantı gibi özel içerik var. Dokunmazsanız değişmez; üzerine yazarsanız özel içerik silinir, yerine yazdığınız metin gelir.' ),
					array( '9. Kalın, italik gibi biçimler düz metne çevrilir. Bu yalnızca değiştirdiğiniz açıklamaları etkiler; dokunmadıklarınızın biçimi korunur.' ),
					array( '10. Bitince dosyayı .xlsx olarak kaydedin ve Ürün Havuzu › Ürün açıklamaları ekranından yükleyin. Önce neyin değişeceği gösterilir; siz onaylamadan hiçbir şey değişmez.' ),
				),
			),
			array(
				'name'    => NWCS_SYNC_INFO,
				'hidden'  => true,
				'header'  => false,
				'rows'    => array(
					array( 'anahtar', 'değer' ),
					array( 'tur', NWCS_DESC_KIND ),
					array( 'kategori', $slug ),
					array( 'kapsam', $name ),
					array( 'indirme', (string) $now ),
					array( 'surum', '1' ),
					array( 'kaynak', network_home_url( '/' ) ),
				),
			),
		)
	);

	if ( is_wp_error( $write ) ) {
		return $write;
	}

	return array(
		'path'     => $path,
		'filename' => 'urun-aciklamalari-' . sanitize_title( '' === $slug ? 'tum-urunler' : $name ) . '-' . wp_date( 'Y-m-d', $now ) . '.xlsx',
	);
}

/**
 * Dosyadan aciklama plani. Hicbir sey yazilmaz.
 *
 * @return array|WP_Error
 */
function nwcs_desc_plan( string $path, string $filename ) {
	$info = nwcs_sync_read_info( $path, NWCS_DESC_KIND );

	if ( is_wp_error( $info ) ) {
		return $info;
	}

	$read = nwcs_xlsx_read_rows( $path, NWCS_DESC_SHEET );

	if ( is_wp_error( $read ) ) {
		return 'nwcs_xlsx_nosheet' === $read->get_error_code()
			? new WP_Error( 'nwcs_sheet', 'Excel’de “Açıklamalar” sayfası bulunamadı. Excel’in altındaki sayfa sekmesinin adını değiştirdiyseniz yeniden “Açıklamalar” yapın.' )
			: $read;
	}

	$rows   = $read['rows'];
	$header = array_map( static fn( $label ): string => trim( (string) $label ), (array) array_shift( $rows ) );
	$col    = array_flip( $header );

	if ( ! isset( $col['ÜRÜN AÇIKLAMASI'] ) || ( ! isset( $col['KİMLİK'] ) && ! isset( $col['ÜRÜN KODU'] ) ) ) {
		return new WP_Error( 'nwcs_cols', 'Sütun adları değiştirilmiş ya da bir sütun silinmiş (ÜRÜN AÇIKLAMASI ya da ÜRÜN KODU bulunamadı). Excel’i yeniden indirip onu doldurun.' );
	}

	$plan = array(
		'time'       => time(),
		'file'       => sanitize_file_name( $filename ),
		'scope'      => (string) ( $info['kapsam'] ?? '' ),
		'downloaded' => (int) ( $info['indirme'] ?? 0 ),
		'update'     => array(),
		'clear'      => array(),
		'same'       => 0,
		'errors'     => array(),
	);
	$seen = array();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $rows as $offset => $row ) {
		$line = $offset + 2;

		if ( '' === trim( implode( '', array_map( 'strval', (array) $row ) ) ) ) {
			continue;
		}

		$raw_id = trim( (string) ( $row[ $col['KİMLİK'] ?? -1 ] ?? '' ) );
		$code   = nwcs_normalize_product_code( (string) ( $row[ $col['ÜRÜN KODU'] ?? -1 ] ?? '' ) );
		$name   = trim( (string) ( $row[ $col['ÜRÜN ADI'] ?? -1 ] ?? '' ) );
		$text   = trim( (string) ( $row[ $col['ÜRÜN AÇIKLAMASI'] ] ?? '' ) );
		$post   = null;

		if ( preg_match( '/^\d+$/', $raw_id ) ) {
			$post = nwcs_sync_product_post( (int) $raw_id );
		}

		if ( ! $post && '' !== $code ) {
			$found = nwcs_product_id_by_code( $code );
			$post  = $found ? nwcs_sync_product_post( $found ) : null;
		}

		if ( ! $post ) {
			$plan['errors'][] = array( 'line' => $line, 'name' => '' !== $name ? $name : $code, 'message' => 'Bu satırdaki ürün bulunamadı: ürün silinmiş ya da satır sonradan eklenmiş olabilir. Bu dosyadan yeni ürün eklenemez; yeni ürünü önce Ürün Havuzu’nda oluşturun.' );
			continue;
		}

		if ( isset( $seen[ $post->ID ] ) ) {
			$plan['errors'][] = array( 'line' => $line, 'name' => $post->post_title, 'message' => sprintf( 'Bu ürün %d. satırda da var. Satırlardan birini silin.', $seen[ $post->ID ] ) );
			continue;
		}

		$seen[ $post->ID ] = $line;
		$current           = nwcs_desc_html_to_text( $post->post_content );

		if ( nwcs_desc_fold( $text ) === nwcs_desc_fold( $current ) ) {
			++$plan['same'];
			continue;
		}

		$item = array(
			'id'       => (int) $post->ID,
			'title'    => $post->post_title,
			'modified' => $post->post_modified_gmt,
			'old'      => $current,
			'new'      => $text,
			'complex'  => NWCS_DESC_MARKER === $current,
			'html'     => nwcs_desc_text_to_html( $text, nwcs_desc_split( $post->post_content )['tables'] ),
		);

		if ( '' === $text ) {
			$plan['clear'][] = $item;
		} else {
			$plan['update'][] = $item;
		}
	}

	restore_current_blog();

	return $plan;
}

/**
 * Plani uygular; geri alma kaydi "Son işlemler" yiginina girer (admin/history.php).
 */
function nwcs_desc_apply( array $plan ): array {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$record = array( 'id' => bin2hex( random_bytes( 6 ) ), 'kind' => 'aciklamalar', 'file' => $plan['file'], 'scope' => $plan['scope'], 'complete' => false, 'updated' => array(), 'skipped' => array() );

	// Kayit en basta yazilir, her urunden once guncellenir: istek yarida
	// kesilse de yapilanlar geri alinabilir.
	$save = static function () use ( &$record, $plan ): void {
		$record['time']   = time();
		$record['counts'] = array(
			'updated' => count( $plan['update'] ),
			'cleared' => count( $plan['clear'] ),
			'skipped' => count( $record['skipped'] ),
		);

		nwcs_history_save( $record );
	};

	nwcs_history_push( $record + array( 'time' => time() ) );
	$save();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( array_merge( (array) $plan['update'], (array) $plan['clear'] ) as $item ) {
		$post = nwcs_sync_product_post( (int) $item['id'] );

		if ( ! $post || $post->post_modified_gmt !== $item['modified'] ) {
			$record['skipped'][] = $item['title'];
			continue;
		}

		$record['updated'][ (int) $item['id'] ]  = $post->post_content;
		$record['modified'][ (int) $item['id'] ] = array( $post->post_modified, $post->post_modified_gmt );
		$save();

		wp_update_post( array( 'ID' => (int) $item['id'], 'post_content' => wp_slash( (string) $item['html'] ) ) );
		$save();
	}

	restore_current_blog();

	$record['complete'] = true;
	$save();
	nwcs_pool_flush_cache();

	return $record;
}

/**
 * Bir aciklama yuklemesini geri alir (yiginin en ustundeki kayit).
 *
 * @return array{restored:int, kept:string[]}
 */
function nwcs_desc_undo( array $record ): array {
	$report = array( 'restored' => 0, 'kept' => array() );

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( (array) $record['updated'] as $id => $content ) {
		$post = nwcs_sync_product_post( (int) $id );

		if ( ! $post ) {
			continue;
		}

		if ( strtotime( $post->post_modified_gmt . ' UTC' ) > (int) $record['time'] + 5 ) {
			$report['kept'][] = $post->post_title;
			continue;
		}

		wp_update_post( array( 'ID' => (int) $id, 'post_content' => wp_slash( (string) $content ) ) );

		// Zaman da eski haline: alttaki islemin geri almasi bunu elle duzenleme sanmasin.
		$was = (array) ( $record['modified'][ $id ] ?? array() );
		nwcs_restore_post_modified( (int) $id, (string) ( $was[0] ?? '' ), (string) ( $was[1] ?? '' ) );

		++$report['restored'];
	}

	restore_current_blog();

	nwcs_pool_flush_cache();

	return $report;
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

function nwcs_desc_redirect( string $step ): void {
	wp_safe_redirect( nwcs_descriptions_url( array( 'adim' => $step ) ) );
	exit;
}

add_action( 'admin_post_nwcs_desc_download', 'nwcs_handle_desc_download' );
function nwcs_handle_desc_download(): void {
	nwcs_sync_guard( 'nwcs_desc_download' );

	$result = nwcs_desc_build_template( isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : '' );

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	nwcs_xlsx_send( $result['path'], $result['filename'] );
}

add_action( 'admin_post_nwcs_desc_upload', 'nwcs_handle_desc_upload' );
function nwcs_handle_desc_upload(): void {
	nwcs_sync_guard( 'nwcs_desc_upload' );

	$key  = NWCS_DESC_PLAN . get_current_user_id();
	$path = nwcs_import_uploaded_xlsx( 'dosya' );
	$plan = is_wp_error( $path ) ? $path : nwcs_desc_plan( $path, sanitize_file_name( (string) ( $_FILES['dosya']['name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( ! is_wp_error( $path ) ) {
		wp_delete_file( $path );
	}

	if ( is_wp_error( $plan ) ) {
		set_site_transient( $key . '_hata', $plan->get_error_message(), 10 * MINUTE_IN_SECONDS );
		nwcs_desc_redirect( 'hata' );
	}

	set_site_transient( $key, $plan, HOUR_IN_SECONDS );
	nwcs_desc_redirect( 'onizleme' );
}

add_action( 'admin_post_nwcs_desc_apply', 'nwcs_handle_desc_apply' );
function nwcs_handle_desc_apply(): void {
	nwcs_sync_guard( 'nwcs_desc_apply' );

	$key  = NWCS_DESC_PLAN . get_current_user_id();
	$plan = get_site_transient( $key );

	if ( ! is_array( $plan ) || (int) ( $_POST['plan'] ?? 0 ) !== (int) $plan['time'] ) {
		set_site_transient( $key . '_hata', 'Önizlemenin süresi doldu ya da başka bir dosya yüklendi. Dosyayı yeniden yükleyin.', 10 * MINUTE_IN_SECONDS );
		nwcs_desc_redirect( 'hata' );
	}

	// Once onizleme silinir: iki kez basilan "Uygula" ikinci kez yazmaz.
	if ( ! delete_site_transient( $key ) ) {
		nwcs_desc_redirect( 'uygulandi' );
	}

	nwcs_desc_apply( $plan );
	nwcs_desc_redirect( 'uygulandi' );
}

add_action( 'admin_post_nwcs_desc_cancel', 'nwcs_handle_desc_cancel' );
function nwcs_handle_desc_cancel(): void {
	nwcs_sync_guard( 'nwcs_desc_cancel' );
	delete_site_transient( NWCS_DESC_PLAN . get_current_user_id() );
	nwcs_desc_redirect( '' );
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

/**
 * Metnin kisa onizlemesi (ilk 160 karakter).
 */
function nwcs_desc_excerpt( string $text ): string {
	$text = nwcs_desc_fold( $text );

	return mb_strlen( $text ) > 160 ? mb_substr( $text, 0, 160 ) . '…' : $text;
}

function nwcs_render_descriptions(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gosterim.
	$step       = isset( $_GET['adim'] ) ? sanitize_key( wp_unslash( $_GET['adim'] ) ) : '';
	$key        = NWCS_DESC_PLAN . get_current_user_id();
	$categories = nwcs_pool_categories();
	$last       = nwcs_history_latest( 'aciklamalar' );
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-descs">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Ürün açıklamaları</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu’na dön</a>
			</div>
		</header>

		<?php nwcs_render_history_result(); ?>
		<?php nwcs_render_cache_note(); ?>

		<?php
		if ( 'hata' === $step ) {
			$message = get_site_transient( $key . '_hata' );
			delete_site_transient( $key . '_hata' );

			if ( is_string( $message ) ) {
				echo '<div id="nwcs-excel-result" class="nwcs-sync nwcs-sync--error" role="alert"><h2 class="nwcs-sync__title">Excel yüklenemedi</h2><p>' . esc_html( $message ) . '</p><p class="nwcs-sync__muted">Hiçbir açıklama değişmedi.</p></div>';
			}
		} elseif ( 'uygulandi' === $step && is_array( $last ) ) {
			echo '<div id="nwcs-excel-result" class="nwcs-sync nwcs-sync--done" role="status"><h2 class="nwcs-sync__title">Açıklamalar güncellendi</h2><p>' . esc_html( sprintf( '%d açıklama güncellendi, %d açıklama boşaltıldı.', (int) $last['counts']['updated'], (int) $last['counts']['cleared'] ) ) . '</p>';

			if ( $last['skipped'] ) {
				echo '<p class="nwcs-sync__warn">' . esc_html( 'Önizlemeden sonra değiştiği için atlananlar: ' . implode( ', ', $last['skipped'] ) . '.' ) . '</p>';
			}

			echo '</div>';
		} elseif ( 'onizleme' === $step ) {
			$plan = get_site_transient( $key );

			if ( is_array( $plan ) ) {
				nwcs_render_desc_preview( $plan );
				echo '</div>';

				return;
			}
		}
		?>

		<div class="nwcs-descs__grid">
			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">1. Excel indir</h2>
				<p class="nwcs-hint">Seçtiğiniz ürünlerin açıklamaları tek dosyada gelir. Her satır bir ürün; açıklama son sütunda.</p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nwcs_desc_download" />
					<?php wp_nonce_field( 'nwcs_desc_download', '_wpnonce', false ); ?>
					<label class="nwcs-sublabel" for="nwcs-desc-cat">Hangi ürünler?</label>
					<div class="nwcs-excel__row">
						<select class="nwcs-input" id="nwcs-desc-cat" name="kategori">
							<option value="">Tüm ürünler (<?php echo (int) count( nwcs_pool_products() ); ?>)</option>
							<?php foreach ( $categories as $slug => $term ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $term['name'] ); ?> (<?php echo (int) $term['count']; ?>)</option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary">Excel indir</button>
					</div>
				</form>
			</section>

			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">2. Excel yükle</h2>
				<p class="nwcs-hint">
					Düzenlediğiniz dosyayı yükleyin; önce neyin değişeceği gösterilir. Boş hücre açıklamayı siler,
					ama ürün sayfası kapanmaz: ad, görsel ve teknik detaylar görünmeye devam eder.
				</p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nwcs_desc_upload" />
					<?php wp_nonce_field( 'nwcs_desc_upload' ); ?>
					<label class="nwcs-sublabel" for="nwcs-desc-file">Düzenlenmiş Excel dosyası (.xlsx)</label>
					<div class="nwcs-excel__row">
						<input class="nwcs-file" type="file" id="nwcs-desc-file" name="dosya" accept=".xlsx" required />
						<button type="submit" class="button button-primary">Excel yükle</button>
					</div>
				</form>
			</section>
		</div>

		<?php nwcs_render_history_card( nwcs_descriptions_url() ); ?>
	</div>
	<?php
}

/**
 * Iki metnin ilk ayristigi yerden kisa kesitler: degisiklik metnin sonundaysa
 * da onizlemede gorunsun.
 *
 * @return array{0:string, 1:string}
 */
function nwcs_desc_diff_excerpts( string $old, string $new ): array {
	$old  = nwcs_desc_fold( $old );
	$new  = nwcs_desc_fold( $new );
	$same = 0;
	$max  = min( mb_strlen( $old ), mb_strlen( $new ) );

	while ( $same < $max && mb_substr( $old, $same, 1 ) === mb_substr( $new, $same, 1 ) ) {
		++$same;
	}

	$start = max( 0, $same - 50 );
	$cut   = static function ( string $text ) use ( $start ): string {
		$part = mb_substr( $text, $start, 220 );

		return ( $start ? '…' : '' ) . $part . ( mb_strlen( $text ) > $start + 220 ? '…' : '' );
	};

	return array( '' === $old ? '' : $cut( $old ), '' === $new ? '' : $cut( $new ) );
}

function nwcs_render_desc_preview( array $plan ): void {
	$nothing = ! $plan['update'] && ! $plan['clear'];
	?>
	<section class="nwcs-sync" id="nwcs-excel-result" aria-labelledby="nwcs-desc-title">
		<header class="nwcs-sync__head">
			<h2 class="nwcs-sync__title" id="nwcs-desc-title">Önizleme: ürün açıklamaları (<?php echo esc_html( $plan['scope'] ); ?>)</h2>
			<p class="nwcs-sync__muted"><?php echo esc_html( $plan['file'] ); ?></p>
			<p class="nwcs-sync__lead">Henüz hiçbir şey değişmedi. Aşağıyı kontrol edin, doğruysa <strong>Değişiklikleri uygula</strong>’ya basın.</p>
			<?php $order = nwcs_history_order_note(); ?>
			<?php if ( '' !== $order ) : ?>
				<p class="nwcs-sync__muted"><?php echo esc_html( $order ); ?></p>
			<?php endif; ?>
		</header>

		<ul class="nwcs-sync__sum">
			<li class="nwcs-sync__chip nwcs-sync__chip--update"><b><?php echo count( $plan['update'] ); ?></b> güncellenecek</li>
			<li class="nwcs-sync__chip nwcs-sync__chip--trash"><b><?php echo count( $plan['clear'] ); ?></b> boşaltılacak</li>
			<li class="nwcs-sync__chip nwcs-sync__chip--error"><b><?php echo count( $plan['errors'] ); ?></b> satırda hata</li>
			<li class="nwcs-sync__chip"><b><?php echo (int) $plan['same']; ?></b> değişmedi</li>
		</ul>

		<?php if ( $plan['errors'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--error">
				<h3>Yüklenmeyecek satırlar (<?php echo count( $plan['errors'] ); ?>)</h3>
				<ul class="nwcs-sync__list">
					<?php foreach ( $plan['errors'] as $error ) : ?>
						<li>
							<span class="nwcs-sync__line"><?php echo (int) $error['line']; ?>. satır</span>
							<strong><?php echo esc_html( $error['name'] ); ?></strong>
							<span><?php echo esc_html( $error['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $plan['clear'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--trash">
				<h3>Açıklaması silinecek ürünler (<?php echo count( $plan['clear'] ); ?>)</h3>
				<p class="nwcs-sync__muted">Bu ürünlerin hücresi boş. Ürün sayfaları açık kalır; yalnızca açıklama metni görünmez (varsa tabloları kalır).</p>
				<ul class="nwcs-sync__list">
					<?php foreach ( $plan['clear'] as $item ) : ?>
						<li><strong><?php echo esc_html( $item['title'] ); ?></strong> <span class="nwcs-sync__muted"><?php echo esc_html( nwcs_desc_excerpt( $item['old'] ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $plan['update'] ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--update">
				<h3>Güncellenecek açıklamalar (<?php echo count( $plan['update'] ); ?>)</h3>
				<ul class="nwcs-sync__list">
					<?php foreach ( $plan['update'] as $item ) : ?>
						<li>
							<details class="nwcs-sync__item">
								<summary>
									<strong><?php echo esc_html( $item['title'] ); ?></strong>
									<?php if ( $item['complex'] ) : ?>
										<span class="nwcs-sync__flag">özel içerik (görsel, bağlantı…) silinecek, yerine yazdığınız metin gelecek; tablolar kalır</span>
									<?php endif; ?>
								</summary>
								<table class="nwcs-sync__diff">
									<thead><tr><th scope="col">Şimdi</th><th scope="col">Excel’deki</th></tr></thead>
									<?php $diff = nwcs_desc_diff_excerpts( (string) $item['old'], (string) $item['new'] ); ?>
									<tbody><tr><td><?php echo '' !== $diff[0] ? esc_html( $diff[0] ) : '<em class="nwcs-sync__none">boş</em>'; ?></td><td><?php echo esc_html( $diff[1] ); ?></td></tr></tbody>
								</table>
							</details>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-sync__form">
			<input type="hidden" name="action" value="nwcs_desc_apply" />
			<input type="hidden" name="plan" value="<?php echo esc_attr( (string) $plan['time'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_desc_apply' ); ?>
			<div class="nwcs-sync__actions">
				<?php if ( ! $nothing ) : ?>
					<button type="submit" class="button button-primary button-hero">Değişiklikleri uygula</button>
				<?php else : ?>
					<p class="nwcs-sync__muted">Uygulanacak bir değişiklik yok: dosyadaki açıklamalar paneldekilerle aynı.</p>
				<?php endif; ?>
				<button type="submit" class="button" form="nwcs-desc-cancel">Vazgeç</button>
			</div>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-desc-cancel" hidden>
			<input type="hidden" name="action" value="nwcs_desc_cancel" />
			<?php wp_nonce_field( 'nwcs_desc_cancel' ); ?>
		</form>
	</section>
	<?php
}
