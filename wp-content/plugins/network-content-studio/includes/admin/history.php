<?php
/**
 * Ürün Havuzu -> "Son işlemler": geri alinabilir islemlerin yigini.
 *
 * Kategori Excel'i (urunler), Ürün açıklamaları (aciklamalar), kategori
 * yerlesimi (yerlesim), kategori silme (kategori), Fotograf Kutusu
 * (fotograflar) ve Medya Havuzu alt metin Excel'i (alt) her uygulamada bir kayit
 * birakir. En yeni en ustte;
 * en fazla NWCS_HISTORY_MAX kayit tutulur. Yalnizca en ustteki geri alinir
 * (son giren ilk cikar): sonraki islemler once geri alindigindan "yuklemeden
 * sonra elle duzenlendi" denetimi yanlis alarm vermez.
 *
 * Kayit bicimi: onceki tek kayitlarin (nwcs_sync_last, nwcs_desc_last) aynisi
 * + 'id', 'kind', 'time'. Eski tek kayitlar ilk acilista yigina tasinir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_HISTORY     = 'nwcs_pool_history';
const NWCS_HISTORY_MAX = 10;

/**
 * Butun kayitlar, en yeniden eskiye.
 *
 * @return array<int, array>
 */
function nwcs_history_all(): array {
	$list = get_site_option( NWCS_HISTORY, array() );

	return is_array( $list ) ? array_values( array_filter( $list, 'is_array' ) ) : array();
}

/**
 * En ustteki (geri alinabilecek) kayit.
 */
function nwcs_history_top(): ?array {
	$list = nwcs_history_all();

	return $list[0] ?? null;
}

/**
 * Yeni kaydi en uste koyar; kimligini dondurur. Onuncudan eskiler duser.
 */
function nwcs_history_push( array $record ): string {
	$record['id']   = $record['id'] ?? bin2hex( random_bytes( 6 ) );
	$record['time'] = $record['time'] ?? time();

	$list = nwcs_history_all();
	array_unshift( $list, $record );

	update_site_option( NWCS_HISTORY, array_slice( $list, 0, NWCS_HISTORY_MAX ) );

	return (string) $record['id'];
}

/**
 * Kimligi verilen kaydi yerinde gunceller (uygulama sirasinda her adimda).
 */
function nwcs_history_save( array $record ): void {
	$list = nwcs_history_all();

	foreach ( $list as $index => $row ) {
		if ( ( $row['id'] ?? '' ) === ( $record['id'] ?? '' ) ) {
			$list[ $index ] = $record;
			update_site_option( NWCS_HISTORY, $list );

			return;
		}
	}

	nwcs_history_push( $record );
}

/**
 * Kimligi verilen kaydi yigindan cikarir.
 */
function nwcs_history_drop( string $id ): void {
	$list = array_values( array_filter( nwcs_history_all(), static fn( array $row ): bool => ( $row['id'] ?? '' ) !== $id ) );

	update_site_option( NWCS_HISTORY, $list );
}

/**
 * Belirli turdeki en yeni kayit (uygulama ozeti icin).
 */
function nwcs_history_latest( string $kind ): ?array {
	foreach ( nwcs_history_all() as $row ) {
		if ( ( $row['kind'] ?? '' ) === $kind ) {
			return $row;
		}
	}

	return null;
}

/**
 * Bir kategorinin en yeni urun Excel'i kaydi (kategori ekrani icin).
 */
function nwcs_history_latest_for( string $slug ): ?array {
	foreach ( nwcs_history_all() as $row ) {
		if ( NWCS_SYNC_KIND === ( $row['kind'] ?? '' ) && ( $row['slug'] ?? '' ) === $slug ) {
			return $row;
		}
	}

	return null;
}

/**
 * Tek seferlik tasima: eski tek seviyeli kayitlar yigina. Bir kez calisir
 * (bayrak); her yonetim isteginde option silinmez.
 */
add_action( 'admin_init', 'nwcs_history_migrate' );
function nwcs_history_migrate(): void {
	if ( get_site_option( 'nwcs_history_migrated' ) ) {
		return;
	}

	update_site_option( 'nwcs_history_migrated', 1 );

	$old = array();

	foreach ( array( 'nwcs_sync_last' => 'urunler', 'nwcs_desc_last' => 'aciklamalar' ) as $option => $kind ) {
		$record = get_site_option( $option, null );

		if ( is_array( $record ) ) {
			$record['kind'] = $kind;
			$old[]          = $record;
		}

		delete_site_option( $option );
	}

	if ( ! $old ) {
		return;
	}

	usort( $old, static fn( array $a, array $b ): int => (int) ( $a['time'] ?? 0 ) <=> (int) ( $b['time'] ?? 0 ) );

	foreach ( $old as $record ) {
		nwcs_history_push( $record );
	}
}

/**
 * Kaydin tek satirlik ozeti: "Köpek Kulübeleri · ürün Excel'i · 2 güncellendi, 1 yeni".
 *
 * @return array{what:string, kind:string, facts:string}
 */
function nwcs_history_describe( array $record ): array {
	$counts = (array) ( $record['counts'] ?? array() );
	$facts  = array();

	switch ( $record['kind'] ?? '' ) {
		case 'aciklamalar':
			$what = (string) ( $record['scope'] ?? '' );
			$kind = 'açıklamalar';

			if ( ! empty( $counts['updated'] ) ) {
				$facts[] = sprintf( '%d güncellendi', (int) $counts['updated'] );
			}

			if ( ! empty( $counts['cleared'] ) ) {
				$facts[] = sprintf( '%d boşaltıldı', (int) $counts['cleared'] );
			}
			break;

		case 'alt':
			$what = (string) ( $record['scope'] ?? '' );
			$kind = 'görsel alt metinleri';

			if ( ! empty( $counts['updated'] ) ) {
				$facts[] = sprintf( '%d değişti', (int) $counts['updated'] );
			}

			if ( ! empty( $counts['cleared'] ) ) {
				$facts[] = sprintf( '%d silindi', (int) $counts['cleared'] );
			}
			break;

		case 'yerlesim':
			$what  = (string) ( $record['category'] ?? '' );
			$kind  = 'sitelerde yerleşim';
			$facts = (array) ( $record['facts'] ?? array() );
			break;

		case 'kategori':
			$what  = (string) ( $record['category'] ?? '' );
			$kind  = 'kategori silindi';
			$facts = (array) ( $record['facts'] ?? array() );
			break;

		case 'fotograflar':
			$what    = (string) ( $record['category'] ?? '' );
			$kind    = 'fotoğraflar';
			$facts[] = sprintf(
				'%d fotoğraf, %d ürün (%s)',
				(int) ( $counts['photos'] ?? 0 ),
				(int) ( $counts['products'] ?? 0 ),
				'replace' === ( $record['mode'] ?? '' ) ? 'galeri değişti' : 'sona eklendi'
			);
			break;

		default:
			$what = (string) ( $record['category'] ?? '' );
			$kind = 'ürün Excel’i';

			foreach ( array( 'updated' => '%d güncellendi', 'created' => '%d yeni', 'trashed' => '%d çöp kutusuna', 'sites' => '%d ürünün sitesi değişti', 'addresses' => '%d sayfa adresi değişti' ) as $key => $format ) {
				if ( ! empty( $counts[ $key ] ) ) {
					$facts[] = sprintf( $format, (int) $counts[ $key ] );
				}
			}
	}

	return array(
		'what'  => $what,
		'kind'  => $kind,
		'facts' => $facts ? implode( ', ', $facts ) : 'değişiklik yok',
	);
}

/**
 * En ustteki kaydi geri alir.
 *
 * @return array{kind:string, report:array}|WP_Error
 */
function nwcs_history_undo( string $id ) {
	$top = nwcs_history_top();

	if ( ! $top ) {
		return new WP_Error( 'nwcs_undo', 'Geri alınacak bir işlem yok.' );
	}

	if ( ( $top['id'] ?? '' ) !== $id ) {
		return new WP_Error( 'nwcs_undo', 'Yalnızca en üstteki işlem geri alınabilir. Sayfayı yenileyip listeye yeniden bakın.' );
	}

	switch ( $top['kind'] ?? '' ) {
		case 'aciklamalar':
			$report = nwcs_desc_undo( $top );
			break;

		case 'yerlesim':
			$report = nwcs_placement_undo( $top );
			break;

		case 'kategori':
			$report = nwcs_category_undelete( $top );
			break;

		case 'fotograflar':
			$report = nwcs_photos_undo( $top );
			break;

		case 'alt':
			$report = nwcs_alt_undo( $top );
			break;

		default:
			$report = nwcs_sync_undo( $top );
	}

	if ( is_wp_error( $report ) ) {
		// Geri alinamayacak kadar degismis kayit (kategorisi baska yoldan silinmis)
		// yigini kilitlemesin: listeden kalkar, neden yazilir.
		if ( 'nwcs_undo_gone' === $report->get_error_code() ) {
			nwcs_history_drop( $id );
		}

		return $report;
	}

	nwcs_history_drop( $id );

	return array( 'kind' => (string) ( $top['kind'] ?? 'urunler' ), 'record' => $top, 'report' => $report );
}

/**
 * Yerlesim degisikligini geri alir: kategorinin onceki yerlesimi ve sitelerin
 * secimi. Secim o arada degismediyse aynen onceki haline doner; degistiyse
 * yalnizca bu islemin ekledikleri cikar, cikardiklari sona eklenir.
 *
 * @return array{sites:int}
 */
function nwcs_placement_undo( array $record ) {
	$slug   = (string) ( $record['slug'] ?? '' );
	$before = (array) ( $record['before'] ?? array() );

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( '“%s” kategorisi sonradan silinmiş; bu yerleşim değişikliği geri alınamaz. Bu işlem listeden kaldırıldı.', (string) ( $record['category'] ?? $slug ) ) );
	}

	foreach ( nwcs_catalog_sites() as $site ) {
		nwcs_set_placement( $slug, $site['site_key'], $before[ $site['site_key'] ] ?? null );
	}

	$sites = nwcs_selection_restore( (array) ( $record['sites'] ?? array() ) );

	nwcs_pool_flush_cache();

	return array( 'sites' => $sites );
}

/**
 * Sitelerin secimini bir islemden onceki haline getirir: secim o arada
 * degismediyse aynen onceki; degistiyse yalnizca islemin ekledikleri cikar,
 * cikardiklari sona eklenir.
 *
 * @param array<int, array{before:int[], after:int[], added:int[], removed:int[]}> $rows
 * @return int Dokunulan site sayisi.
 */
function nwcs_selection_restore( array $rows ): int {
	$sites = 0;

	foreach ( $rows as $blog_id => $row ) {
		$current = nwcs_site_product_settings( (int) $blog_id )['selected'];

		if ( $current === array_map( 'intval', (array) $row['after'] ) ) {
			$restored = array_map( 'intval', (array) $row['before'] );
		} else {
			$restored = array_values( array_diff( $current, array_map( 'intval', (array) $row['added'] ) ) );
			$restored = array_values( array_unique( array_merge( $restored, array_map( 'intval', (array) $row['removed'] ) ) ) );
		}

		switch_to_blog( (int) $blog_id );
		update_option( NWCS_OPTION_SELECTED, $restored );
		restore_current_blog();
		++$sites;
	}

	return $sites;
}

/**
 * Silinen kategoriyi geri getirir: ayni ad ve adresle yeniden acilir, urunleri
 * (havuzda duranlar) geri baglanir, sitelerdeki yeri ve site secimleri eski
 * haline doner.
 *
 * @return array{sites:int, restored:int}|WP_Error
 */
function nwcs_category_undelete( array $record ) {
	$slug = (string) ( $record['slug'] ?? '' );
	$name = (string) ( $record['name'] ?? $slug );

	if ( isset( nwcs_pool_categories()[ $slug ] ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( 'Silinen kategorinin adresiyle (%s) yeni bir kategori oluşturulmuş; silinen kategori geri getirilemez. Bu işlem listeden kaldırıldı.', $slug ) );
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$made = wp_insert_term( wp_slash( $name ), NWCS_PRODUCT_TAX, array( 'slug' => $slug ) );

	if ( is_wp_error( $made ) ) {
		restore_current_blog();

		return new WP_Error( 'nwcs_undo', 'Kategori geri getirilemedi: aynı adda başka bir kategori var. O kategorinin adını değiştirip yeniden deneyin.' );
	}

	$term_id  = (int) $made['term_id'];
	$restored = 0;

	foreach ( array_map( 'intval', (array) ( $record['products'] ?? array() ) ) as $id ) {
		if ( nwcs_sync_product_post( $id ) ) {
			wp_add_object_terms( $id, array( $term_id ), NWCS_PRODUCT_TAX );
			++$restored;
		}
	}

	$placement = nwcs_clean_placement( (array) ( $record['before'] ?? array() ) );

	if ( $placement ) {
		update_term_meta( $term_id, NWCS_PLACEMENT_META, $placement );
	}

	restore_current_blog();

	nwcs_pool_categories_flush();
	$sites = nwcs_selection_restore( (array) ( $record['sites'] ?? array() ) );
	nwcs_pool_flush_cache();

	return array( 'sites' => $sites, 'restored' => $restored );
}

add_action( 'admin_post_nwcs_history_undo', 'nwcs_handle_history_undo' );
function nwcs_handle_history_undo(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_history_undo' );

	$id     = isset( $_POST['kayit'] ) ? sanitize_key( wp_unslash( $_POST['kayit'] ) ) : '';
	$back   = isset( $_POST['donus'] ) ? esc_url_raw( wp_unslash( $_POST['donus'] ) ) : '';
	$back   = $back ? wp_validate_redirect( $back, nwcs_pool_categories_url() ) : nwcs_pool_categories_url();
	$result = nwcs_history_undo( $id );
	$key    = 'nwcs_history_result_' . get_current_user_id();

	set_site_transient( $key, is_wp_error( $result ) ? $result->get_error_message() : $result, 10 * MINUTE_IN_SECONDS );

	wp_safe_redirect( remove_query_arg( array( 'excel', 'adim', 'onay' ), $back ) . '#nwcs-history' );
	exit;
}

/**
 * Geri almanin sonucu (bir kez gosterilir).
 */
function nwcs_render_history_result(): void {
	$key    = 'nwcs_history_result_' . get_current_user_id();
	$result = get_site_transient( $key );

	if ( false === $result ) {
		return;
	}

	delete_site_transient( $key );

	if ( is_string( $result ) ) {
		echo '<div class="nwcs-sync nwcs-sync--error" role="alert"><h2 class="nwcs-sync__title">Geri alınamadı</h2><p>' . esc_html( $result ) . '</p></div>';

		return;
	}

	$report = (array) $result['report'];
	$info   = nwcs_history_describe( (array) $result['record'] );
	$facts  = array();

	foreach (
		array(
			'restored'  => 'kategori' === ( $result['kind'] ?? '' ) ? '%d ürün kategoriye geri bağlandı' : '%d ürün eski hâline döndü',
			'removed'   => '%d yeni ürün çöp kutusuna taşındı',
			'untrashed' => '%d ürün çöp kutusundan geri geldi',
			'headings'  => '%d detay başlığı kaldırıldı',
			'sites'     => '%d sitenin ürün seçimi eski hâline döndü',
			'deleted_photos' => '%d fotoğraf dosyası silindi',
			'alts'      => '%d görselin alt metni eski hâline döndü',
			'addresses' => '%d sayfa adresi eski hâline döndü',
		) as $field => $format
	) {
		if ( ! empty( $report[ $field ] ) ) {
			$facts[] = sprintf( $format, (int) $report[ $field ] );
		}
	}
	?>
	<div class="nwcs-sync nwcs-sync--done" role="status">
		<h2 class="nwcs-sync__title">Geri alındı: <?php echo esc_html( $info['what'] . ' (' . $info['kind'] . ')' ); ?></h2>
		<?php if ( $facts ) : ?>
			<ul class="nwcs-sync__facts">
				<?php foreach ( $facts as $fact ) : ?>
					<li><?php echo esc_html( $fact ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( ! empty( $report['kept'] ) ) : ?>
			<p class="nwcs-sync__warn">İşlemden sonra değiştirildiği için eski hâline döndürülmeyen <?php echo 'alt' === ( $result['kind'] ?? '' ) ? 'görseller' : 'ürünler'; ?>: <?php echo esc_html( implode( ', ', (array) $report['kept'] ) ); ?>. Gerekirse bunları elle düzeltin.</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * "Son işlemler" karti. $back: geri almadan sonra donulecek sayfa.
 */
function nwcs_render_history_card( string $back ): void {
	$list = nwcs_history_all();
	?>
	<section class="nwcs-history" id="nwcs-history" aria-labelledby="nwcs-history-title">
		<h3 class="nwcs-section__title" id="nwcs-history-title">Son işlemler</h3>

		<?php if ( ! $list ) : ?>
			<p class="nwcs-hint">Henüz geri alınabilecek bir işlem yok. Excel yüklemeleri, fotoğraf yüklemeleri ve sitelerdeki yerleşim değişiklikleri burada listelenir.</p>
		<?php else : ?>
			<p class="nwcs-hint">En yeniden eskiye. Yalnızca en üstteki geri alınır; daha eskisini geri almak için önce üsttekileri geri alın.</p>
			<ol class="nwcs-history__list">
				<?php foreach ( $list as $index => $row ) : ?>
					<?php $info = nwcs_history_describe( $row ); ?>
					<li class="nwcs-history__row<?php echo 0 === $index ? ' is-top' : ''; ?>">
						<span class="nwcs-history__when"><?php echo esc_html( wp_date( 'd.m H:i', (int) ( $row['time'] ?? 0 ) ) ); ?></span>
						<span class="nwcs-history__what">
							<strong><?php echo esc_html( $info['what'] ); ?></strong>
							<span><?php echo esc_html( $info['kind'] . ': ' . $info['facts'] ); ?></span>
							<?php if ( isset( $row['complete'] ) && ! $row['complete'] ) : ?>
								<span class="nwcs-history__warn">Bu işlem yarıda kesildi; “Geri al” o ana kadar yapılanları geri alır.</span>
							<?php endif; ?>
						</span>
						<?php if ( 0 === $index ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								onsubmit="return confirm('Bu işlem geri alınacak: <?php echo esc_js( $info['what'] . ' (' . $info['kind'] . ')' ); ?>. Devam edilsin mi?');">
								<input type="hidden" name="action" value="nwcs_history_undo" />
								<input type="hidden" name="kayit" value="<?php echo esc_attr( (string) $row['id'] ); ?>" />
								<input type="hidden" name="donus" value="<?php echo esc_attr( $back ); ?>" />
								<?php wp_nonce_field( 'nwcs_history_undo' ); ?>
								<button type="submit" class="button">Geri al</button>
							</form>
						<?php else : ?>
							<span class="nwcs-history__later">önce üsttekini geri alın</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Onizlemede: uygulanirsa geri alma sirasi.
 */
function nwcs_history_order_note(): string {
	$top = nwcs_history_top();

	if ( ! $top ) {
		return '';
	}

	$info = nwcs_history_describe( $top );

	return sprintf(
		'Uygularsanız geri alma sırası: önce bu yükleme, sonra %s (%s, %s).',
		$info['what'],
		$info['kind'],
		wp_date( 'd.m H:i', (int) ( $top['time'] ?? 0 ) )
	);
}
