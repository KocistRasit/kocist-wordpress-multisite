<?php
/**
 * Urun Havuzu -> urun formu -> "Ürün videosu".
 *
 * Urun basina tek MP4. Yeni dosya yuklenir ya da kitapliktaki bir video
 * secilir; "Video yok" secilirse urunden ayrilir (dosya kitaplikta kalir).
 * Yukleme, urune hicbir sey yazilmadan once denetlenir: buyuk ya da MP4
 * olmayan dosyada form taslak olarak geri doner (nwcs_pool_keep_draft).
 *
 * Veri ve kural: includes/product-video.php. Tarayici tarafi (boyut on
 * denetimi, onizleme): assets/admin.js.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Su an yuklenebilecek en buyuk video: 20 MB ile sunucunun sinirinin kucugu.
 *
 * wp_max_upload_size kullanilmaz: cok siteli kurulumda Ag Yonetimi'ndeki
 * "En büyük yükleme dosya boyutu"nu (varsayilan 1500 KB) her zaman katar,
 * oysa WordPress o siniri yalnizca yer denetimi aciksa uygular
 * (check_upload_size). Burada uygulanan sinirlar sayilir.
 */
function nwcs_video_upload_limit(): int {
	$limits = array(
		NWCS_VIDEO_MAX_BYTES,
		wp_convert_hr_to_bytes( (string) ini_get( 'upload_max_filesize' ) ),
		wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ),
	);

	if ( nwcs_video_network_limit_applies() ) {
		$limits[] = KB_IN_BYTES * (int) get_site_option( 'fileupload_maxk', 1500 );
	}

	return (int) min( array_filter( $limits, static fn( int $bytes ): bool => $bytes > 0 ) );
}

/**
 * Ag Yonetimi'ndeki yukleme siniri gercekten uygulaniyor mu.
 */
function nwcs_video_network_limit_applies(): bool {
	return is_multisite() && ! get_site_option( 'upload_space_check_disabled' );
}

/**
 * Urunun, video gosteren sitelerden hangilerinde yayinda oldugu (site adlari).
 *
 * @return string[]
 */
function nwcs_product_video_sites( int $product_id ): array {
	$labels = array();

	foreach ( nwcs_editable_sites() as $blog_id => $site ) {
		if ( ! nwcs_site_supports_product_video( (int) $blog_id ) ) {
			continue;
		}

		$settings = nwcs_site_product_settings( (int) $blog_id );

		if ( $product_id && ! empty( $settings['overrides'][ $product_id ]['hidden'] ) ) {
			continue;
		}

		if ( 'all' === $settings['mode'] || ( $product_id && in_array( $product_id, $settings['selected'], true ) ) ) {
			$labels[] = $site['label'];
		}
	}

	return $labels;
}

/**
 * Havuz kitapligindaki MP4'ler (en yenisi once), secim kutusu icin.
 *
 * @return array<int, string> ek kimligi => "dosya.mp4 · 4,2 MB"
 */
function nwcs_pool_video_choices(): array {
	switch_to_blog( nwcs_pool_blog_id() );

	$choices = array();

	foreach (
		get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'video/mp4',
				'post_status'    => 'inherit',
				'posts_per_page' => 300,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		) as $attachment_id
	) {
		$video = nwcs_video_by_id( (int) $attachment_id );

		if ( $video ) {
			$choices[ (int) $attachment_id ] = nwcs_video_summary( $video );
		}
	}

	restore_current_blog();

	return $choices;
}

/**
 * "dosya.mp4 · 4,2 MB · 0:22"
 */
function nwcs_video_summary( array $video ): string {
	return implode(
		' · ',
		array_filter(
			array(
				$video['name'],
				$video['size'] ? size_format( $video['size'], 1 ) : '',
				nwcs_video_length_label( (int) $video['length'] ),
			)
		)
	);
}

/**
 * Formdaki bolum. Video gosteren sitede olmayan urunde de gorunur: video
 * saklanir, site gosterince acilir.
 */
function nwcs_render_product_video_field( ?array $product ): void {
	$id      = (int) ( $product['id'] ?? 0 );
	$video   = (array) ( $product['video'] ?? array() );
	$current = (int) ( $video['id'] ?? 0 );
	$sites   = nwcs_product_video_sites( $id );
	$limit   = nwcs_video_upload_limit();
	$choices = nwcs_pool_video_choices();
	$error   = (string) ( $product['error'] ?? '' );
	$errors  = array(
		'video_size'   => sprintf( 'Video %s sınırını aşıyor. Videoyu sıkıştırıp yeniden seçin.', size_format( $limit ) ),
		'video_type'   => 'Video MP4 olmalı. Telefon videosunu MP4 olarak dışa aktarıp yeniden seçin.',
		'video_upload' => 'Video yüklenemedi. Dosyayı yeniden seçin; sorun sürerse dosya bozuk olabilir.',
	);
	$invalid = isset( $errors[ $error ] );

	// Secili video kitaplik listesinde yoksa (silinmis/eski) yine de secenek olarak kalsin.
	if ( $current && ! isset( $choices[ $current ] ) && $video ) {
		$choices = array( $current => nwcs_video_summary( $video ) ) + $choices;
	}
	?>
	<div class="nwcs-field nwcs-video<?php echo $invalid ? ' is-invalid' : ''; ?>" data-nwcs-video>
		<span class="nwcs-field__label">Ürün videosu</span>
		<p class="nwcs-hint">
			<?php if ( $sites ) : ?>
				<strong><?php echo esc_html( implode( ', ', $sites ) ); ?></strong> sitesinde, ürün sayfasındaki galerinin sonunda ▶ olarak görünür.
			<?php else : ?>
				Bu ürün video gösteren bir sitede yayında değil; video saklanır ama şu an görünmez.
			<?php endif; ?>
			Ürün başına tek video. Ziyaretçi tıklamadan video indirilmez.
		</p>

		<input type="hidden" name="video_present" value="1" />

		<div class="nwcs-video__current" data-nwcs-video-current<?php echo $video ? '' : ' hidden'; ?>>
			<?php if ( $video ) : ?>
				<video class="nwcs-video__player" src="<?php echo esc_url( $video['url'] ); ?>" controls preload="metadata" playsinline></video>
				<p class="nwcs-video__meta"><?php echo esc_html( nwcs_video_summary( $video ) ); ?></p>
				<button type="button" class="button button-small nwcs-row__delete" data-nwcs-video-remove>Videoyu üründen kaldır</button>
			<?php endif; ?>
		</div>

		<label class="nwcs-sublabel" for="nwcs-p-video">Kitaplıktan seç</label>
		<select class="nwcs-input" id="nwcs-p-video" name="video_id" data-nwcs-video-select>
			<option value="0"<?php selected( $current, 0 ); ?>>— Video yok —</option>
			<?php foreach ( $choices as $choice_id => $label ) : ?>
				<option value="<?php echo (int) $choice_id; ?>"<?php selected( $current, (int) $choice_id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="nwcs-hint">“Video yok” seçip kaydederseniz video üründen ayrılır; dosya kitaplıkta kalır.</p>

		<label class="nwcs-sublabel" for="nwcs-p-video-upload">Yeni video yükle</label>
		<input class="nwcs-file" type="file" id="nwcs-p-video-upload" name="video_upload" accept="video/mp4,.mp4"
			data-nwcs-video-upload data-max="<?php echo (int) $limit; ?>" data-max-text="<?php echo esc_attr( size_format( $limit ) ); ?>"
			aria-describedby="nwcs-p-video-hint<?php echo $invalid ? ' nwcs-p-video-error' : ''; ?>" />
		<p class="nwcs-field__error" id="nwcs-p-video-error" data-nwcs-video-error role="alert"<?php echo $invalid ? '' : ' hidden'; ?>><?php echo $invalid ? esc_html( $errors[ $error ] ) : ''; ?></p>
		<p class="nwcs-hint" id="nwcs-p-video-hint">
			Yalnızca MP4, en fazla <strong><?php echo esc_html( size_format( $limit ) ); ?></strong>. Önerilen: 15–30 saniye, 720p, sessiz.
			Yüklenen video seçili videonun yerine geçer. Videolar <a href="<?php echo esc_url( nwcs_media_url( array( 'tur' => 'video' ) ) ); ?>">Medya Havuzu › Videolar</a>'dan da yüklenebilir.
		</p>

		<?php if ( $limit < NWCS_VIDEO_MAX_BYTES ) : ?>
			<p class="nwcs-hint nwcs-video__server">
				Şu an en fazla <strong><?php echo esc_html( size_format( $limit ) ); ?></strong> boyutunda video yüklenebiliyor; 20 MB’a kadar video için bu sınırın yükseltilmesi gerekir. Bunu geliştiriciye haber verin.
				(Teknik: hosting panelinde PHP <code>upload_max_filesize</code> ve <code>post_max_size</code> en az 24M olmalı<?php if ( nwcs_video_network_limit_applies() ) : ?>;
				Ağ Yönetimi → Ayarlar → “En büyük yükleme dosya boyutu” en az 20480 KB olmalı<?php endif; ?>.)
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Formla gelen video dosyasinin denetimi; urune bir sey yazilmadan once.
 *
 * @return string Hata anahtari (video_size, video_type, video_upload) ya da ''.
 */
function nwcs_video_upload_error(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nwcs_handle_pool_save denetledi; dosya asagida denetlenir.
	return nwcs_video_file_error( $_FILES['video_upload'] ?? null );
}

/**
 * Tek bir yuklenen dosyanin ($_FILES girisi) video denetimi. Urun formu ve
 * Medya Havuzu > Videolar ayni kurali kullanir.
 *
 * @param mixed $file
 * @return string Hata anahtari (video_size, video_type, video_upload); dosya yoksa ya da uygunsa ''.
 */
function nwcs_video_file_error( $file ): string {
	if ( ! is_array( $file ) || ! isset( $file['error'] ) || is_array( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return '';
	}

	if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) $file['size'] > nwcs_video_upload_limit() ) {
		return 'video_size';
	}

	if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
		return 'video_upload';
	}

	$type = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], array( 'mp4' => 'video/mp4' ) );

	return 'video/mp4' === ( $type['type'] ?? '' ) ? '' : 'video_type';
}

/**
 * Formdaki secimi ve yuklemeyi kaydeder. Havuz baglaminda, yukleme
 * nwcs_video_upload_error ile denetlendikten sonra cagrilir. Bolum formda
 * yoksa kayda dokunulmaz.
 *
 * @return bool Yukleme basarisiz olduysa false (secim degismez).
 */
function nwcs_save_product_video( int $product_id ): bool {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nwcs_handle_pool_save denetledi.
	if ( empty( $_POST['video_present'] ) ) {
		return true;
	}

	$video = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
	$file  = $_FILES['video_upload'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_video_upload_error denetledi.
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( $video && 'video/mp4' !== get_post_mime_type( $video ) ) {
		$video = 0;
	}

	if ( is_array( $file ) && isset( $file['error'] ) && UPLOAD_ERR_OK === (int) $file['error'] ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploaded = media_handle_upload( 'video_upload', 0, array(), array( 'test_form' => false, 'mimes' => array( 'mp4' => 'video/mp4' ) ) );

		if ( is_wp_error( $uploaded ) ) {
			return false;
		}

		$video = (int) $uploaded;
	}

	if ( $video ) {
		update_post_meta( $product_id, '_nwcs_video', $video );
	} else {
		delete_post_meta( $product_id, '_nwcs_video' );
	}

	return true;
}
