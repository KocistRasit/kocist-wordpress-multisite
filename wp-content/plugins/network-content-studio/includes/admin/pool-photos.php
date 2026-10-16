<?php
/**
 * Ürün Havuzu -> Kategoriler -> "Fotoğrafları yükleyin" (Fotograf Kutusu).
 *
 * Akis: dosyalar tek tek (JS) ya da toplu (JS'siz form) havuzun medya
 * kitapligina parti isaretiyle yuklenir -> onizleme (hicbir urun degismez)
 * -> "Fotoğrafları uygula" -> "Son işlemler"den geri alinabilir.
 *
 * Eslesme, plan, uygulama ve geri alma: includes/photos.php.
 * Kullanici basina tek bekleyen parti vardir (site transient'i, 1 saat).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bekleyen partinin transient anahtari.
 */
function nwcs_photos_pending_key(): string {
	return 'nwcs_photos_batch_' . get_current_user_id();
}

/**
 * Kullanicinin bekleyen partisi: { id, slug, rejected: [ [ad, neden] ], time }.
 */
function nwcs_photos_pending(): ?array {
	$pending = get_site_transient( nwcs_photos_pending_key() );

	return is_array( $pending ) && ! empty( $pending['id'] ) ? $pending : null;
}

/**
 * Yeni parti acar; bekleyen eski partinin ekleri silinir (kullanici basina tek parti).
 */
function nwcs_photos_start( string $slug ): array {
	$old = nwcs_photos_pending();

	if ( $old ) {
		nwcs_photos_delete_batch( (string) $old['id'] );
	}

	$pending = array(
		'id'       => bin2hex( random_bytes( 6 ) ),
		'slug'     => $slug,
		'rejected' => array(),
		'time'     => time(),
	);

	set_site_transient( nwcs_photos_pending_key(), $pending, HOUR_IN_SECONDS );

	return $pending;
}

/**
 * Kategoriler sayfasina (kategori acik), fotograf adimi bilgisiyle doner.
 */
function nwcs_photos_redirect( string $step, string $slug, string $error = '' ): void {
	if ( '' !== $error ) {
		set_site_transient( nwcs_photos_pending_key() . '_hata', $error, 10 * MINUTE_IN_SECONDS );
	}

	wp_safe_redirect( nwcs_pool_categories_url( array_filter( array( 'kategori' => $slug, 'fotograf' => $step ) ) ) . '#nwcs-fotograf-sonuc' );
	exit;
}

/**
 * JavaScript kapaliyken coklu yukleme sinirlari (ipucu metni).
 */
function nwcs_photos_form_limits(): array {
	return array(
		'files' => (int) ini_get( 'max_file_uploads' ),
		'total' => size_format( wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) ),
	);
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

/**
 * Tek dosya (JS kuyrugu). Yanit her zaman basarili: dosyaya ozgu sorun
 * 'reason' ile doner, kuyruk durmaz. Yalnizca yetki/oturum/parti sorunu hata.
 */
add_action( 'wp_ajax_nwcs_photo_upload', 'nwcs_ajax_photo_upload' );
function nwcs_ajax_photo_upload(): void {
	check_ajax_referer( 'nwcs_panel' );

	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), 403 );
	}

	$slug  = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	$batch = isset( $_POST['parti'] ) ? sanitize_key( wp_unslash( $_POST['parti'] ) ) : '';

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		wp_send_json_error( array( 'message' => 'Kategori bulunamadı; silinmiş olabilir. Sayfayı yenileyip yeniden deneyin.' ), 400 );
	}

	$pending = nwcs_photos_pending();

	if ( '' === $batch ) {
		$pending = nwcs_photos_start( $slug );
	} elseif ( ! $pending || $pending['id'] !== $batch ) {
		wp_send_json_error( array( 'message' => 'Başka bir sekmede yeni bir yükleme başladı ya da bu yüklemenin süresi doldu. Fotoğrafları yeniden seçin.' ), 409 );
	}

	$result = nwcs_photo_store( 'dosya', (string) $pending['id'], (string) $pending['slug'] );
	$match  = null;

	if ( '' !== $result['reason'] ) {
		$pending['rejected'][] = array( $result['name'], $result['reason'] );
	} else {
		$found = nwcs_photo_match( $result['name'], nwcs_photo_candidates() );
		$pool  = nwcs_pool_products();

		if ( $found['id'] > 0 && isset( $pool[ $found['id'] ] ) ) {
			$match = array( 'code' => $pool[ $found['id'] ]['code'], 'title' => $pool[ $found['id'] ]['title'] );
		}
	}

	$pending['time'] = time();
	set_site_transient( nwcs_photos_pending_key(), $pending, HOUR_IN_SECONDS );

	wp_send_json_success(
		array(
			'parti'  => $pending['id'],
			'id'     => $result['id'],
			'name'   => $result['name'],
			'reason' => '' !== $result['reason'] ? $result['reason'] : ( $match ? '' : 'dosya adında bir ürün kodu bulunamadı' ),
			'stored' => '' === $result['reason'],
			'match'  => $match,
		)
	);
}

/**
 * JavaScript kapaliyken: coklu dosya alani + "Yükle".
 */
add_action( 'admin_post_nwcs_photos_upload_all', 'nwcs_handle_photos_upload_all' );
function nwcs_handle_photos_upload_all(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	// Kategori adreste (GET) de gelir: toplam post_max_size'i asinca PHP $_POST'u da bosaltir.
	$slug = isset( $_REQUEST['kategori'] ) ? sanitize_title( wp_unslash( $_REQUEST['kategori'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) ) {
		$limits = nwcs_photos_form_limits();
		nwcs_photos_redirect( 'hata', $slug, sprintf( 'Seçtiğiniz dosyaların toplam boyutu sınırı (%s) aşıyor; hiçbiri yüklenmedi. Daha az dosya seçip yeniden deneyin. (Teknik: JavaScript kapalıyken tek seferde gönderilebilecek sınır aşıldı)', $limits['total'] ) );
	}

	check_admin_referer( 'nwcs_photos_upload' );

	$files = $_FILES['dosyalar'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida tek tek islenir.

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		nwcs_photos_redirect( 'hata', '', 'Kategori bulunamadı; silinmiş olabilir. Sayfayı yenileyip yeniden deneyin.' );
	}

	if ( ! is_array( $files ) || ! is_array( $files['name'] ?? null ) || UPLOAD_ERR_NO_FILE === (int) ( $files['error'][0] ?? UPLOAD_ERR_NO_FILE ) ) {
		nwcs_photos_redirect( 'hata', $slug, 'Dosya seçilmedi. Fotoğrafları seçip “Yükle”ye basın.' );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$pending = nwcs_photos_start( $slug );

	foreach ( array_keys( $files['name'] ) as $index ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $files['error'][ $index ] ) {
			continue;
		}

		$_FILES['nwcs_photo_single'] = array(
			'name'     => $files['name'][ $index ],
			'type'     => $files['type'][ $index ],
			'tmp_name' => $files['tmp_name'][ $index ],
			'error'    => $files['error'][ $index ],
			'size'     => $files['size'][ $index ],
		);

		$result = nwcs_photo_store( 'nwcs_photo_single', (string) $pending['id'], $slug );

		if ( '' !== $result['reason'] ) {
			$pending['rejected'][] = array( $result['name'], $result['reason'] );
		}
	}

	unset( $_FILES['nwcs_photo_single'] );

	set_site_transient( nwcs_photos_pending_key(), $pending, HOUR_IN_SECONDS );
	nwcs_photos_redirect( 'onizleme', $slug );
}

/**
 * JS kuyrugu bitince: tarayicinin reddettigi dosyalar (cok buyuk, tur) de
 * listeye eklenir, onizleme acilir.
 */
add_action( 'admin_post_nwcs_photos_preview', 'nwcs_handle_photos_preview' );
function nwcs_handle_photos_preview(): void {
	nwcs_sync_guard( 'nwcs_photos_preview' );

	$batch   = isset( $_POST['parti'] ) ? sanitize_key( wp_unslash( $_POST['parti'] ) ) : '';
	$pending = nwcs_photos_pending();

	if ( ! $pending || $pending['id'] !== $batch ) {
		$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
		nwcs_photos_redirect( 'hata', $slug, 'Önizlemenin süresi doldu ya da başka bir yükleme başladı. Fotoğrafları yeniden yükleyin.' );
	}

	$client = isset( $_POST['red'] ) && is_array( $_POST['red'] ) ? array_slice( wp_unslash( $_POST['red'] ), 0, 200 ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida temizlenir.

	foreach ( $client as $row ) {
		$row = json_decode( (string) $row, true );

		if ( is_array( $row ) && isset( $row[0], $row[1] ) ) {
			$pending['rejected'][] = array( nwcs_clean_text( (string) $row[0] ), nwcs_clean_text( (string) $row[1] ) );
		}
	}

	set_site_transient( nwcs_photos_pending_key(), $pending, HOUR_IN_SECONDS );
	nwcs_photos_redirect( 'onizleme', (string) $pending['slug'] );
}

add_action( 'admin_post_nwcs_photos_apply', 'nwcs_handle_photos_apply' );
function nwcs_handle_photos_apply(): void {
	nwcs_sync_guard( 'nwcs_photos_apply' );

	$batch   = isset( $_POST['parti'] ) ? sanitize_key( wp_unslash( $_POST['parti'] ) ) : '';
	$slug    = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	$mode    = isset( $_POST['mod'] ) && 'replace' === $_POST['mod'] ? 'replace' : 'append';
	$exclude = isset( $_POST['cikar'] ) && is_array( $_POST['cikar'] ) ? array_map( 'absint', wp_unslash( $_POST['cikar'] ) ) : array();
	$pending = nwcs_photos_pending();

	$applied = static function () use ( $batch ): bool {
		foreach ( nwcs_history_all() as $row ) {
			if ( NWCS_PHOTO_KIND === ( $row['kind'] ?? '' ) && ( $row['batch'] ?? '' ) === $batch ) {
				return true;
			}
		}

		return false;
	};

	if ( ! $pending || '' === $batch || $pending['id'] !== $batch ) {
		if ( '' !== $batch && $applied() ) {
			nwcs_photos_redirect( 'uygulandi', $slug, 'Bu yükleme zaten uygulandı; ikinci kez uygulanmadı.' );
		}

		nwcs_photos_redirect( 'hata', $slug, 'Önizlemenin süresi doldu ya da başka bir yükleme başladı. Fotoğrafları yeniden yükleyin.' );
	}

	// Once bekleyen parti silinir: "Uygula"ya iki kez basilirsa ikinci istek bos doner.
	if ( ! delete_site_transient( nwcs_photos_pending_key() ) ) {
		nwcs_photos_redirect( 'uygulandi', (string) $pending['slug'], 'Bu yükleme zaten uygulandı; ikinci kez uygulanmadı.' );
	}

	$plan = nwcs_photos_plan( $batch, (string) $pending['slug'], (array) $pending['rejected'], $exclude );

	if ( ! $plan['groups'] ) {
		nwcs_photos_delete_batch( $batch );
		nwcs_photos_redirect( 'vazgecildi', (string) $pending['slug'] );
	}

	nwcs_photos_apply( $plan, $mode );

	nwcs_photos_redirect( 'uygulandi', (string) $pending['slug'] );
}

add_action( 'admin_post_nwcs_photos_cancel', 'nwcs_handle_photos_cancel' );
function nwcs_handle_photos_cancel(): void {
	nwcs_sync_guard( 'nwcs_photos_cancel' );

	$pending = nwcs_photos_pending();
	$slug    = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';

	if ( $pending ) {
		nwcs_photos_delete_batch( (string) $pending['id'] );
		delete_site_transient( nwcs_photos_pending_key() );
		$slug = (string) $pending['slug'];
	}

	nwcs_photos_redirect( 'vazgecildi', $slug );
}

/* ====================================================================== *
 * Arayuz
 * ====================================================================== */

/**
 * Kategorinin "Fotoğrafları yükleyin" karti.
 */
function nwcs_render_photo_box( string $slug, array $products ): void {
	$example = '';
	$no_code = 0;

	foreach ( $products as $product ) {
		if ( '' === (string) $product['code'] ) {
			++$no_code;
		} elseif ( '' === $example ) {
			$example = (string) $product['code'];
		}
	}

	$example = '' !== $example ? $example : 'URUN-KODU';
	$pending = nwcs_photos_pending();
	$waiting = $pending ? count( nwcs_photos_batch_ids( (string) $pending['id'] ) ) + count( (array) $pending['rejected'] ) : 0;
	$limits  = nwcs_photos_form_limits();
	$action  = add_query_arg( array( 'action' => 'nwcs_photos_upload_all', 'kategori' => $slug ), admin_url( 'admin-post.php' ) );
	?>
	<section class="nwcs-cat__section nwcs-photos" aria-labelledby="nwcs-sec-photos" id="nwcs-fotograf">
		<h3 class="nwcs-section__title" id="nwcs-sec-photos">Fotoğrafları yükleyin</h3>
		<p class="nwcs-photos__rule">
			Dosya adı ürün koduyla başlasın:
			<span class="nwcs-fname"><b><?php echo esc_html( $example ); ?></b>-1.jpg</span>,
			<span class="nwcs-fname"><b><?php echo esc_html( $example ); ?></b>-2.jpg</span> …
		</p>
		<p class="nwcs-hint">
			İlk numara kartta kullanılır. JPG, PNG, WebP ve iPhone HEIC olur; dosya başına en fazla <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?>.
			Yüklemeden sonra hangi fotoğrafın hangi ürüne gideceği gösterilir; onaylamadan hiçbir ürün değişmez.
		</p>

		<?php if ( $no_code ) : ?>
			<p class="nwcs-photos__warn">
				<?php echo esc_html( sprintf( 'Bu kategoride %d ürünün kodu yok. Kodu ürün sayfasından ya da Excel’in ÜRÜN KODU sütunundan girin; fotoğraflar kodla eşleşir.', $no_code ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $pending && $waiting ) : ?>
			<p class="nwcs-photos__pending">
				<?php echo esc_html( sprintf( 'Bekleyen %d dosya var.', $waiting ) ); ?>
				<a href="<?php echo esc_url( nwcs_pool_categories_url( array( 'kategori' => (string) $pending['slug'], 'fotograf' => 'onizleme' ) ) . '#nwcs-fotograf-sonuc' ); ?>">Önizlemeyi aç</a>.
				Yeni yükleme başlatırsanız bunlar silinir.
			</p>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( $action ); ?>" class="nwcs-drop" data-nwcs-drop
			data-kategori="<?php echo esc_attr( $slug ); ?>">
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<?php wp_nonce_field( 'nwcs_photos_upload' ); ?>

			<div class="nwcs-drop__zone" data-nwcs-drop-zone>
				<p class="nwcs-drop__text">
					<span data-nwcs-drop-js hidden>Fotoğrafları buraya bırakın ya da</span>
					<label for="nwcs-photo-files" class="nwcs-drop__label">Fotoğraf dosyaları</label>
				</p>
				<button type="button" class="button nwcs-drop__pick" data-nwcs-drop-pick hidden>Dosya seçin</button>
				<input class="nwcs-file nwcs-drop__input" type="file" id="nwcs-photo-files" name="dosyalar[]" multiple
					accept=".jpg,.jpeg,.png,.webp,.heic,.heif,image/jpeg,image/png,image/webp,image/heic,image/heif" />
			</div>

			<div class="nwcs-drop__nojs" data-nwcs-drop-nojs>
				<p class="nwcs-hint">
					<?php echo esc_html( sprintf( 'JavaScript kapalıyken bir seferde en fazla %d dosya, toplam %s.', $limits['files'], $limits['total'] ) ); ?>
				</p>
				<button type="submit" class="button button-primary">Yükle</button>
			</div>

			<div class="nwcs-drop__progress" data-nwcs-drop-progress hidden>
				<div class="nwcs-drop__bar">
					<progress max="1" value="0" data-nwcs-drop-meter></progress>
					<span class="nwcs-drop__count" data-nwcs-drop-count aria-live="polite"></span>
				</div>
				<ul class="nwcs-drop__errors" data-nwcs-drop-errors aria-live="polite"></ul>
			</div>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-nwcs-drop-preview hidden>
			<input type="hidden" name="action" value="nwcs_photos_preview" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<input type="hidden" name="parti" value="" />
			<?php wp_nonce_field( 'nwcs_photos_preview' ); ?>
		</form>
	</section>
	<?php
}

/**
 * Sayfanin basinda fotograf adiminin sonucu: onizleme, hata, uygulama ozeti.
 * Onizleme varken sayfanin geri kalani gosterilmez (true doner).
 */
function nwcs_render_photo_step(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gosterim.
	$step  = isset( $_GET['fotograf'] ) ? sanitize_key( wp_unslash( $_GET['fotograf'] ) ) : '';
	$key   = nwcs_photos_pending_key() . '_hata';
	$extra = get_site_transient( $key );

	if ( '' === $step ) {
		return false;
	}

	if ( false !== $extra ) {
		delete_site_transient( $key );
	}

	if ( 'hata' === $step ) {
		if ( is_string( $extra ) ) {
			?>
			<div class="nwcs-sync nwcs-sync--error" id="nwcs-fotograf-sonuc" role="alert" tabindex="-1">
				<h2 class="nwcs-sync__title">Fotoğraflar yüklenemedi</h2>
				<p><?php echo esc_html( $extra ); ?></p>
				<p class="nwcs-sync__muted">Hiçbir ürün değişmedi.</p>
			</div>
			<?php
		}

		return false;
	}

	if ( 'vazgecildi' === $step ) {
		echo '<div class="notice notice-info is-dismissible"><p>Yüklenen fotoğraflar silindi; hiçbir ürün değişmedi.</p></div>';

		return false;
	}

	if ( 'uygulandi' === $step ) {
		$record = nwcs_history_latest( NWCS_PHOTO_KIND );

		if ( $record ) {
			$counts = (array) ( $record['counts'] ?? array() );
			?>
			<div class="nwcs-sync nwcs-sync--done" id="nwcs-fotograf-sonuc" role="status" tabindex="-1">
				<h2 class="nwcs-sync__title">Fotoğraflar uygulandı: <?php echo esc_html( (string) $record['category'] ); ?></h2>
				<?php if ( is_string( $extra ) ) : ?>
					<p class="nwcs-sync__warn"><?php echo esc_html( $extra ); ?></p>
				<?php endif; ?>
				<ul class="nwcs-sync__facts">
					<li>
						<?php
						echo esc_html(
							'replace' === ( $record['mode'] ?? '' )
								? sprintf( '%d ürünün galerisi değişti (%d fotoğraf)', (int) ( $counts['products'] ?? 0 ), (int) ( $counts['photos'] ?? 0 ) )
								: sprintf( '%d ürüne %d fotoğraf eklendi', (int) ( $counts['products'] ?? 0 ), (int) ( $counts['photos'] ?? 0 ) )
						);
						?>
					</li>
					<?php if ( ! empty( $record['unmatched'] ) ) : ?>
						<li><?php echo esc_html( sprintf( '%d eşleşmeyen dosya hiçbir yere bağlanmadı ve silindi', (int) $record['unmatched'] ) ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( ! empty( $record['skipped'] ) ) : ?>
					<p class="nwcs-sync__warn">Önizlemeden sonra çöp kutusuna taşındığı için fotoğraf bağlanmayan ürünler: <?php echo esc_html( implode( ', ', (array) $record['skipped'] ) ); ?>.</p>
				<?php endif; ?>
				<p class="nwcs-sync__muted">Bir yanlışlık varsa aşağıdaki “Son işlemler”den bu yüklemenin tamamını geri alabilirsiniz.</p>
			</div>
			<?php
		}

		return false;
	}

	if ( 'onizleme' !== $step ) {
		return false;
	}

	$pending = nwcs_photos_pending();

	if ( ! $pending ) {
		echo '<div class="nwcs-sync nwcs-sync--error" id="nwcs-fotograf-sonuc" role="alert"><p>Önizlemenin süresi doldu ya da yükleme uygulandı. Fotoğrafları yeniden yükleyin.</p></div>';

		return false;
	}

	nwcs_render_photos_preview( nwcs_photos_plan( (string) $pending['id'], (string) $pending['slug'], (array) $pending['rejected'] ) );

	return true;
}

/**
 * Bir fotograf kucuk resmi (onizleme).
 */
function nwcs_render_photo_thumb( array $file, int $position, bool $removable ): void {
	$size = $file['width'] && $file['height'] ? sprintf( '%d×%d', $file['width'], $file['height'] ) : '';
	?>
	<li class="nwcs-photos__shot">
		<span class="nwcs-photos__img">
			<?php if ( '' !== $file['thumb'] ) : ?>
				<img src="<?php echo esc_url( $file['thumb'] ); ?>" alt="" loading="lazy" />
			<?php endif; ?>
		</span>
		<span class="nwcs-photos__cap">
			<b><?php echo (int) $position; ?></b>
			<span><?php echo esc_html( $size ); ?></span>
		</span>
		<span class="nwcs-photos__file" title="<?php echo esc_attr( $file['name'] ); ?>"><?php echo esc_html( $file['name'] ); ?></span>
		<?php if ( '' !== $file['note'] ) : ?>
			<span class="nwcs-photos__note"><?php echo esc_html( $file['note'] ); ?></span>
		<?php endif; ?>
		<?php if ( $removable ) : ?>
			<label class="nwcs-photos__out">
				<input type="checkbox" name="cikar[]" value="<?php echo (int) $file['id']; ?>" data-nwcs-photo-out />
				<span>Çıkar<span class="screen-reader-text">: <?php echo esc_html( $file['name'] ); ?></span></span>
			</label>
		<?php endif; ?>
	</li>
	<?php
}

/**
 * Onizleme karti.
 */
function nwcs_render_photos_preview( array $plan ): void {
	$groups    = (array) $plan['groups'];
	$here      = array_filter( $groups, static fn( array $group ): bool => $group['here'] );
	$elsewhere = array_filter( $groups, static fn( array $group ): bool => ! $group['here'] );
	$counts    = $plan['counts'];
	$order     = nwcs_history_order_note();
	$existing  = array_filter( $groups, static fn( array $group ): bool => (bool) $group['existing'] );
	?>
	<section class="nwcs-sync nwcs-photos-preview" id="nwcs-fotograf-sonuc" aria-labelledby="nwcs-photos-title" tabindex="-1" data-nwcs-photos-preview>
		<header class="nwcs-sync__head">
			<h2 class="nwcs-sync__title" id="nwcs-photos-title">Önizleme: <?php echo esc_html( $plan['category'] ); ?></h2>
			<p class="nwcs-sync__muted"><?php echo esc_html( sprintf( '%d dosya', (int) $counts['files'] ) ); ?></p>
			<?php if ( $groups ) : ?>
				<p class="nwcs-sync__lead">Henüz hiçbir ürüne fotoğraf bağlanmadı. Aşağıyı kontrol edin, doğruysa <strong>Fotoğrafları uygula</strong>’ya basın.</p>
				<?php if ( '' !== $order ) : ?>
					<p class="nwcs-sync__muted"><?php echo esc_html( $order ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="nwcs-sync__lead">Hiçbir dosya bir ürün koduyla eşleşmedi. Dosya adlarını ürün koduyla başlatıp yeniden yükleyin.</p>
			<?php endif; ?>
		</header>

		<ul class="nwcs-sync__sum">
			<li class="nwcs-sync__chip nwcs-sync__chip--new"><b data-nwcs-photos-count><?php echo (int) $counts['photos']; ?></b> fotoğraf eklenecek</li>
			<li class="nwcs-sync__chip"><b><?php echo (int) $counts['products']; ?></b> ürün</li>
			<li class="nwcs-sync__chip nwcs-sync__chip--error"><b><?php echo (int) $counts['unmatched']; ?></b> dosya eşleşmedi</li>
		</ul>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-sync__form">
			<input type="hidden" name="action" value="nwcs_photos_apply" />
			<input type="hidden" name="parti" value="<?php echo esc_attr( $plan['batch'] ); ?>" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $plan['slug'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_photos_apply' ); ?>

			<?php if ( $existing ) : ?>
				<fieldset class="nwcs-photos__mode">
					<legend><?php echo esc_html( sprintf( 'Mevcut galerisi olan %d üründe:', count( $existing ) ) ); ?></legend>
					<label><input type="radio" name="mod" value="append" checked /> Sona ekle</label>
					<label><input type="radio" name="mod" value="replace" /> Galeriyi değiştir <span class="nwcs-sync__muted">(eski fotoğraflar Medya Havuzu’nda kalır)</span></label>
				</fieldset>
			<?php endif; ?>

			<?php
			foreach ( array( array( $here, 'Bu kategorinin ürünleri', 'new' ), array( $elsewhere, 'Başka kategorideki ürünler', 'update' ) ) as [ $list, $heading, $tone ] ) :
				if ( ! $list ) {
					continue;
				}
				?>
				<div class="nwcs-sync__group nwcs-sync__group--<?php echo esc_attr( $tone ); ?>">
					<h3><?php echo esc_html( sprintf( '%s (%d)', $heading, count( $list ) ) ); ?></h3>
					<?php if ( 'update' === $tone ) : ?>
						<p class="nwcs-sync__muted">Dosya adı bu ürünlerin koduyla başlıyor; fotoğraflar onlara bağlanır.</p>
					<?php endif; ?>
					<ul class="nwcs-photos__list">
						<?php foreach ( $list as $group ) : ?>
							<?php $before = count( $group['existing'] ); ?>
							<li class="nwcs-photos__row">
								<div class="nwcs-photos__who">
									<span class="nwcs-photos__code"><?php echo esc_html( $group['code'] ); ?></span>
									<strong class="nwcs-photos__title"><?php echo esc_html( $group['title'] ); ?></strong>
									<span class="nwcs-photos__nums">
										<?php echo esc_html( sprintf( '%d yeni (mevcut %d)', count( $group['files'] ), $before ) ); ?>
										<?php if ( ! $group['here'] ) : ?>
											· <?php echo esc_html( sprintf( 'başka kategoride (%s)', $group['other'] ) ); ?>
										<?php endif; ?>
									</span>
									<?php foreach ( $group['notes'] as $note ) : ?>
										<span class="nwcs-photos__note"><?php echo esc_html( $note ); ?></span>
									<?php endforeach; ?>
								</div>
								<div class="nwcs-photos__strip">
									<?php if ( $before ) : ?>
										<ul class="nwcs-photos__shots nwcs-photos__shots--old" aria-label="<?php echo esc_attr( sprintf( 'Mevcut %d fotoğraf', $before ) ); ?>">
											<?php foreach ( array_slice( $group['existing'], 0, 4 ) as $url ) : ?>
												<li class="nwcs-photos__shot"><span class="nwcs-photos__img"><?php if ( '' !== $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" /><?php endif; ?></span></li>
											<?php endforeach; ?>
											<?php if ( $before > 4 ) : ?>
												<li class="nwcs-photos__more">+<?php echo (int) ( $before - 4 ); ?></li>
											<?php endif; ?>
										</ul>
									<?php endif; ?>
									<ul class="nwcs-photos__shots" aria-label="Yeni fotoğraflar, galerideki sırasıyla">
										<?php foreach ( $group['files'] as $index => $file ) : ?>
											<?php nwcs_render_photo_thumb( $file, $index + 1, true ); ?>
										<?php endforeach; ?>
									</ul>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>

			<?php if ( $plan['unmatched'] ) : ?>
				<div class="nwcs-sync__group nwcs-sync__group--error">
					<h3><?php echo esc_html( sprintf( 'Eşleşmeyen dosyalar (%d)', count( $plan['unmatched'] ) ) ); ?></h3>
					<p class="nwcs-sync__muted">Hiçbir ürüne bağlanmaz; uygulayınca da vazgeçince de silinir. Adını ürün koduyla başlatıp yeniden yükleyebilirsiniz.</p>
					<ul class="nwcs-sync__list">
						<?php foreach ( $plan['unmatched'] as $file ) : ?>
							<li>
								<strong class="nwcs-photos__file"><?php echo esc_html( $file['name'] ); ?></strong>
								<span><?php echo esc_html( $file['reason'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="nwcs-sync__actions">
				<?php if ( $groups ) : ?>
					<button type="submit" class="button button-primary button-hero">Fotoğrafları uygula</button>
				<?php endif; ?>
				<button type="submit" class="button" form="nwcs-photos-cancel">Vazgeç</button>
			</div>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-photos-cancel" hidden>
			<input type="hidden" name="action" value="nwcs_photos_cancel" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $plan['slug'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_photos_cancel' ); ?>
		</form>
	</section>
	<?php
}
