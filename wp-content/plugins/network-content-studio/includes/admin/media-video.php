<?php
/**
 * Ag Yonetimi -> Medya Havuzu -> Videolar (?tur=video).
 *
 * Urun videolari (MP4, en fazla NWCS_VIDEO_MAX_BYTES) gorsellerle ayni
 * kitaplikta durur; bu sekme onlari listeler, yukler, adlandirir ve siler.
 * Bir urune bagli video silinemez (nwcs_media_usage). Urune baglama urun
 * formundaki "Kitaplıktan seç" ile yapilir (pool-video.php).
 *
 * Yukleme: sunucunun istek siniri (post_max_size) yuzunden videolar tek tek
 * gider. JavaScript varsa birden cok secim sirayla gonderilir
 * (assets/media-video.js, nwcs_media_video_upload istegi); yoksa form tek
 * istekte gider ve toplam sinira takilan dosyalar bildirilir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gorseller | Videolar sekmeleri.
 */
function nwcs_render_media_tabs( bool $is_video ): void {
	?>
	<nav class="nwcs-viewtoggle nwcs-mediatabs" aria-label="Medya türü">
		<a class="<?php echo $is_video ? '' : 'is-active'; ?>" href="<?php echo esc_url( nwcs_media_url() ); ?>"<?php echo $is_video ? '' : ' aria-current="page"'; ?>>Görseller</a>
		<a class="<?php echo $is_video ? 'is-active' : ''; ?>" href="<?php echo esc_url( nwcs_media_url( array( 'tur' => 'video' ) ) ); ?>"<?php echo $is_video ? ' aria-current="page"' : ''; ?>>Videolar</a>
	</nav>
	<?php
}

/**
 * Kitaplikteki MP4'ler: arama ve sayfalama ile.
 *
 * @return array{items: array<int, array>, total: int, pages: int}
 */
function nwcs_media_video_query( string $search, int $page ): array {
	switch_to_blog( nwcs_pool_blog_id() );

	$query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'video/mp4',
			'post_status'    => 'inherit',
			'posts_per_page' => NWCS_MEDIA_PER_PAGE,
			'paged'          => max( 1, $page ),
			's'              => $search,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	$items = array_values( array_filter( array_map( static fn( $id ): array => nwcs_video_by_id( (int) $id ), $query->posts ) ) );
	$total = (int) $query->found_posts;
	$pages = (int) $query->max_num_pages;

	restore_current_blog();

	return array(
		'items' => $items,
		'total' => $total,
		'pages' => max( 1, $pages ),
	);
}

/**
 * Videolar sekmesinin govdesi (nwcs_render_media icinden).
 */
function nwcs_render_media_videos( string $search, int $page ): void {
	$result = nwcs_media_video_query( $search, $page );
	$usage  = nwcs_media_usage();
	$limit  = nwcs_video_upload_limit();
	$base   = array( 'tur' => 'video' );
	?>
	<p class="nwcs-help">
		<span class="nwcs-help__step"><b>1</b> Videoyu buraya yükleyin (MP4, en fazla <?php echo esc_html( size_format( $limit ) ); ?>)</span>
		<span class="nwcs-help__step"><b>2</b> <a href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu</a>'nda ürünü açın</span>
		<span class="nwcs-help__step"><b>3</b> “Ürün videosu › Kitaplıktan seç” ile bağlayıp kaydedin</span>
	</p>

	<?php nwcs_render_media_video_notices(); ?>

	<div class="nwcs-pool">
		<section class="nwcs-pool__list">
			<div class="nwcs-toolbar">
				<form method="get" class="nwcs-toolbar__search">
					<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_MEDIA_SLUG ); ?>" />
					<input type="hidden" name="tur" value="video" />
					<input class="nwcs-input" type="search" name="ara" value="<?php echo esc_attr( $search ); ?>"
						placeholder="Video ara…" />
					<button type="submit" class="button">Ara</button>
					<?php if ( '' !== $search ) : ?>
						<a class="button" href="<?php echo esc_url( nwcs_media_url( $base ) ); ?>">Temizle</a>
					<?php endif; ?>
				</form>
				<span class="nwcs-toolbar__count"><?php echo (int) $result['total']; ?> video</span>
			</div>

			<?php if ( ! $result['items'] ) : ?>
				<p class="nwcs-empty"><?php echo '' !== $search ? 'Bu aramaya uyan video yok.' : 'Henüz video yok. Sağdaki “Video yükle” ile ekleyin.'; ?></p>
			<?php else : ?>
				<div class="nwcs-mediagrid nwcs-mediagrid--video">
					<?php foreach ( $result['items'] as $video ) : ?>
						<?php $used = $usage[ $video['id'] ] ?? array(); ?>
						<figure class="nwcs-mediacard">
							<div class="nwcs-mediacard__thumb nwcs-mediacard__thumb--video">
								<?php // Yalnizca ust bilgi: ilk kare gorunur, oynatilmadan video inmez. ?>
								<video src="<?php echo esc_url( $video['url'] ); ?>" controls preload="metadata" playsinline></video>
							</div>

							<figcaption>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-mediacard__form">
									<input type="hidden" name="action" value="nwcs_media_video_update" />
									<input type="hidden" name="attachment" value="<?php echo (int) $video['id']; ?>" />
									<?php wp_nonce_field( 'nwcs_media_video_update_' . $video['id'] ); ?>

									<label class="nwcs-sublabel" for="nwcs-video-title-<?php echo (int) $video['id']; ?>">Başlık</label>
									<input class="nwcs-input" type="text" id="nwcs-video-title-<?php echo (int) $video['id']; ?>" name="title" value="<?php echo esc_attr( $video['title'] ); ?>" />

									<p class="nwcs-mediacard__meta"><?php echo esc_html( nwcs_video_summary( $video ) ); ?></p>

									<?php if ( $used ) : ?>
										<p class="nwcs-mediacard__used">Kullanımda: <?php nwcs_media_render_usage( $used ); ?></p>
										<p class="nwcs-hint">Silmek için önce üründen kaldırın (ürün formunda “Video yok”).</p>
									<?php else : ?>
										<p class="nwcs-hint">Hiçbir ürüne bağlı değil.</p>
									<?php endif; ?>

									<div class="nwcs-mediacard__actions">
										<button type="submit" class="button button-small">Kaydet</button>
									</div>
								</form>

								<?php if ( ! $used ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
										onsubmit="return confirm('Bu video kalıcı olarak silinsin mi?');">
										<input type="hidden" name="action" value="nwcs_media_video_delete" />
										<input type="hidden" name="attachment" value="<?php echo (int) $video['id']; ?>" />
										<?php wp_nonce_field( 'nwcs_media_video_delete_' . $video['id'] ); ?>
										<button type="submit" class="button button-small nwcs-row__delete">Sil</button>
									</form>
								<?php endif; ?>
							</figcaption>
						</figure>
					<?php endforeach; ?>
				</div>

				<?php
				nwcs_render_pagination(
					$result['pages'],
					$page,
					static fn( int $target ): string => nwcs_media_url( $base + array( 'ara' => $search, 'sayfa' => $target ) )
				);
				?>
			<?php endif; ?>
		</section>

		<section class="nwcs-pool__form">
			<div class="nwcs-pool__card">
				<h2 class="nwcs-pool__title">Video yükle</h2>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					data-nwcs-video-bulk data-max="<?php echo (int) $limit; ?>" data-max-text="<?php echo esc_attr( size_format( $limit ) ); ?>"
					data-done="<?php echo esc_url( nwcs_media_url( $base ) ); ?>">
					<input type="hidden" name="action" value="nwcs_media_video_upload" />
					<?php wp_nonce_field( 'nwcs_media_video_upload' ); ?>

					<div class="nwcs-field">
						<label class="nwcs-field__label" for="nwcs-media-videos">Videolar</label>
						<input class="nwcs-file" type="file" id="nwcs-media-videos" name="files[]" accept="video/mp4,.mp4" multiple required />
						<p class="nwcs-hint">
							Yalnızca MP4, video başına en fazla <strong><?php echo esc_html( size_format( $limit ) ); ?></strong>.
							Önerilen: 15–30 saniye, 720p, sessiz. Birden fazla video seçerseniz sırayla yüklenir.
						</p>
						<p class="nwcs-hint">Buradan yüklenen video ürüne kendiliğinden bağlanmaz; ürün formunda “Kitaplıktan seç” ile bağlayın.</p>
					</div>

					<p class="nwcs-hint" data-nwcs-video-progress role="status" aria-live="polite" hidden></p>

					<div class="nwcs-actions">
						<button type="submit" class="button button-primary">Yükle</button>
					</div>
				</form>

				<?php if ( $limit < NWCS_VIDEO_MAX_BYTES ) : ?>
					<p class="nwcs-hint nwcs-video__server">
						Sunucu şu an en fazla <strong><?php echo esc_html( size_format( $limit ) ); ?></strong> kabul ediyor. 20 MB için:
						hosting panelinde PHP <code>upload_max_filesize</code> ve <code>post_max_size</code> en az 24M olmalı<?php if ( nwcs_video_network_limit_applies() ) : ?>;
						Ağ Yönetimi → Ayarlar → “En büyük yükleme dosya boyutu” en az 20480 KB olmalı<?php endif; ?>.
					</p>
				<?php endif; ?>
			</div>
		</section>
	</div>
	<?php
}

function nwcs_render_media_video_notices(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$key     = isset( $_GET['nwcs_media'] ) ? sanitize_key( wp_unslash( $_GET['nwcs_media'] ) ) : '';
	$count   = isset( $_GET['adet'] ) ? absint( $_GET['adet'] ) : 0;
	$too_big = isset( $_GET['buyuk'] ) ? absint( $_GET['buyuk'] ) : 0;
	$wrong   = isset( $_GET['tursuz'] ) ? absint( $_GET['tursuz'] ) : 0;
	$failed  = isset( $_GET['hatali'] ) ? absint( $_GET['hatali'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$problems = trim(
		( $too_big ? sprintf( ' %1$d video %2$s sınırını aştığı için yüklenmedi; sıkıştırıp yeniden deneyin.', $too_big, size_format( nwcs_video_upload_limit() ) ) : '' )
		. ( $wrong ? sprintf( ' %d dosya MP4 olmadığı için yüklenmedi.', $wrong ) : '' )
		. ( $failed ? sprintf( ' %d video yüklenemedi; dosya bozuk olabilir.', $failed ) : '' )
	);

	$map = array(
		'video_uploaded' => array( $problems ? 'warning' : 'success', trim( sprintf( '%d video yüklendi. ', $count ) . $problems ) ),
		'video_failed'   => array( 'error', '' !== $problems ? 'Hiçbir video yüklenmedi.' . ' ' . $problems : 'Hiçbir video yüklenmedi.' ),
		'video_too_big'  => array( 'error', sprintf( 'Seçilen videoların toplamı sunucunun tek seferde alabileceğinden büyük; hiçbiri yüklenmedi. Videoları tek tek yükleyin (video başına en fazla %s).', size_format( nwcs_video_upload_limit() ) ) ),
		'video_updated'  => array( 'success', 'Video başlığı kaydedildi.' ),
		'video_deleted'  => array( 'success', 'Video silindi.' ),
		'video_in_use'   => array( 'error', 'Bu video bir üründe kullanılıyor; önce üründen kaldırın.' ),
	);

	if ( isset( $map[ $key ] ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $key ][0] ),
			esc_html( $map[ $key ][1] )
		);
	}
}

/* ------------------------------------------------------------------ */
/* Yazma islemleri                                                      */
/* ------------------------------------------------------------------ */

/**
 * Tek bir videoyu ($_FILES girisi) denetleyip kitapliga ekler. Havuz
 * baglaminda cagrilir.
 *
 * @return int|string Ek kimligi ya da hata anahtari (video_size, video_type, video_upload).
 */
function nwcs_media_store_video( array $file ) {
	$error = nwcs_video_file_error( $file );

	if ( '' !== $error ) {
		return $error;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// media_handle_upload tek dosya bekler; gecici olarak tek girise indiriyoruz.
	$_FILES['nwcs_single'] = $file;
	$attachment_id         = media_handle_upload( 'nwcs_single', 0, array(), array( 'test_form' => false, 'mimes' => array( 'mp4' => 'video/mp4' ) ) );
	unset( $_FILES['nwcs_single'] );

	return is_wp_error( $attachment_id ) ? 'video_upload' : (int) $attachment_id;
}

/**
 * JavaScript'siz yukleme: secilen tum videolar tek istekte.
 */
add_action( 'admin_post_nwcs_media_video_upload', 'nwcs_handle_media_video_upload' );
function nwcs_handle_media_video_upload(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_media_video_upload' );

	$files  = $_FILES['files'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_media_store_video denetler.
	$counts = array(
		'adet'   => 0,
		'buyuk'  => 0,
		'tursuz' => 0,
		'hatali' => 0,
	);
	$keys   = array(
		'video_size'   => 'buyuk',
		'video_type'   => 'tursuz',
		'video_upload' => 'hatali',
	);

	if ( ! is_array( $files ) || ! isset( $files['name'] ) || ! is_array( $files['name'] ) ) {
		// Secilenlerin toplami post_max_size'i asinca PHP $_FILES'i bos birakir.
		wp_safe_redirect( nwcs_media_url( array( 'tur' => 'video', 'nwcs_media' => 'video_too_big' ) ) );
		exit;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( array_keys( $files['name'] ) as $index ) {
		$file = array(
			'name'     => $files['name'][ $index ],
			'type'     => $files['type'][ $index ],
			'tmp_name' => $files['tmp_name'][ $index ],
			'error'    => $files['error'][ $index ],
			'size'     => $files['size'][ $index ],
		);

		if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			continue;
		}

		$stored = nwcs_media_store_video( $file );
		++$counts[ is_int( $stored ) ? 'adet' : $keys[ $stored ] ];
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	wp_safe_redirect(
		nwcs_media_url(
			array_filter(
				array(
					'tur'        => 'video',
					'nwcs_media' => $counts['adet'] ? 'video_uploaded' : 'video_failed',
				) + $counts
			)
		)
	);
	exit;
}

/**
 * JavaScript'li yukleme: istek basina tek video (assets/media-video.js).
 */
add_action( 'wp_ajax_nwcs_media_video_upload', 'nwcs_ajax_media_video_upload' );
function nwcs_ajax_media_video_upload(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) || ! check_ajax_referer( 'nwcs_media_video_upload', false, false ) ) {
		wp_send_json_error( array( 'error' => 'video_upload' ), 403 );
	}

	$file = $_FILES['file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_media_store_video denetler.

	if ( ! is_array( $file ) ) {
		wp_send_json_error( array( 'error' => 'video_size' ) );
	}

	switch_to_blog( nwcs_pool_blog_id() );
	$stored = nwcs_media_store_video( $file );
	restore_current_blog();

	if ( ! is_int( $stored ) ) {
		wp_send_json_error( array( 'error' => '' !== $stored ? $stored : 'video_upload' ) );
	}

	nwcs_pool_flush_cache();
	wp_send_json_success( array( 'id' => $stored ) );
}

add_action( 'admin_post_nwcs_media_video_update', 'nwcs_handle_media_video_update' );
function nwcs_handle_media_video_update(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	$attachment_id = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	check_admin_referer( 'nwcs_media_video_update_' . $attachment_id );

	switch_to_blog( nwcs_pool_blog_id() );

	if ( 'video/mp4' === get_post_mime_type( $attachment_id ) ) {
		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => wp_slash( isset( $_POST['title'] ) ? nwcs_clean_text( wp_unslash( $_POST['title'] ) ) : '' ),
			)
		);
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	wp_safe_redirect( nwcs_media_url( array( 'tur' => 'video', 'nwcs_media' => 'video_updated' ) ) );
	exit;
}

add_action( 'admin_post_nwcs_media_video_delete', 'nwcs_handle_media_video_delete' );
function nwcs_handle_media_video_delete(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	$attachment_id = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	check_admin_referer( 'nwcs_media_video_delete_' . $attachment_id );

	if ( isset( nwcs_media_usage()[ $attachment_id ] ) ) {
		wp_safe_redirect( nwcs_media_url( array( 'tur' => 'video', 'nwcs_media' => 'video_in_use' ) ) );
		exit;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	if ( 'video/mp4' === get_post_mime_type( $attachment_id ) ) {
		wp_delete_attachment( $attachment_id, true );
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	wp_safe_redirect( nwcs_media_url( array( 'tur' => 'video', 'nwcs_media' => 'video_deleted' ) ) );
	exit;
}
