<?php
/**
 * Ag Yonetimi -> Medya Havuzu.
 *
 * Urun gorsellerinin toplu yuklendigi, alt metinlerinin duzenlendigi ve
 * silindigi sayfa. Dosyalar havuz sitesinin (ag ana sitesi) WordPress medya
 * kitapliginda durur; ayri bir depo kullanilmaz.
 *
 * Bir urune bagli gorsel silinemez: once urunun galerisinden cikarilmalidir.
 * Videolar ayni kitaplikta, ayri sekmede (?tur=video): media-video.php.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_MEDIA_SLUG     = 'nwcs-media';
const NWCS_MEDIA_PER_PAGE = 24;

add_action( 'network_admin_menu', 'nwcs_register_media_menu' );
function nwcs_register_media_menu(): void {
	add_menu_page(
		'Medya Havuzu',
		'Medya Havuzu',
		NWCS_CAPABILITY,
		NWCS_MEDIA_SLUG,
		'nwcs_render_media',
		'dashicons-format-gallery',
		'3.2' // Urun Havuzu'nun hemen alti (bkz. panel.php).
	);
}

function nwcs_media_url( array $args = array() ): string {
	return add_query_arg(
		array_merge( array( 'page' => NWCS_MEDIA_SLUG ), $args ),
		network_admin_url( 'admin.php' )
	);
}

/**
 * Hangi gorsel hangi urunlerde kullaniliyor. Cop kutusundaki urunler de
 * sayilir: geri getirilen urunun galerisi bos kalmasin.
 *
 * @return array<int, array<int, array{id:int, title:string, trashed:bool}>>
 */
function nwcs_media_usage(): array {
	$usage = array();

	foreach ( nwcs_pool_products() as $product ) {
		foreach ( $product['gallery_ids'] as $attachment_id ) {
			$usage[ $attachment_id ][] = array( 'id' => (int) $product['id'], 'title' => $product['title'], 'trashed' => false );
		}

		// Urun videosu da kullanim sayilir: bagli video silinemez.
		if ( ! empty( $product['video']['id'] ) ) {
			$usage[ (int) $product['video']['id'] ][] = array( 'id' => (int) $product['id'], 'title' => $product['title'], 'trashed' => false );
		}
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$trashed = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'trash',
			'posts_per_page' => -1,
		)
	);

	foreach ( $trashed as $post ) {
		$gallery = get_post_meta( $post->ID, '_nwcs_gallery', true );
		$gallery = is_array( $gallery ) ? array_filter( array_map( 'absint', $gallery ) ) : array();
		$thumb   = (int) get_post_thumbnail_id( $post->ID );

		if ( ! $gallery && $thumb ) {
			$gallery = array( $thumb );
		}

		$video = (int) get_post_meta( $post->ID, '_nwcs_video', true );

		if ( $video ) {
			$gallery[] = $video;
		}

		foreach ( $gallery as $attachment_id ) {
			$usage[ $attachment_id ][] = array( 'id' => (int) $post->ID, 'title' => $post->post_title, 'trashed' => true );
		}
	}

	restore_current_blog();

	return $usage;
}

/**
 * Kullanim listesi: urun adlari, duzenleme sayfasina bagli.
 */
function nwcs_media_render_usage( array $used ): void {
	$links = array();

	foreach ( $used as $product ) {
		$url     = $product['trashed'] ? nwcs_pool_url( array( 'cop' => 1 ) ) : nwcs_pool_url( array( 'urun' => $product['id'] ) );
		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>%3$s',
			esc_url( $url ),
			esc_html( $product['title'] ),
			$product['trashed'] ? ' <small>(çöp kutusunda)</small>' : ''
		);
	}

	echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- yukarida kacirildi.
}

/**
 * Medya kitapligini arama ve sayfalama ile getirir.
 */
function nwcs_media_query( string $search, int $page ): array {
	switch_to_blog( nwcs_pool_blog_id() );

	$query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => NWCS_MEDIA_PER_PAGE,
			'paged'          => max( 1, $page ),
			's'              => $search,
			'orderby'        => 'date',
			'order'          => 'DESC',
			// Fotograf Kutusu'nda bekleyen (henuz uygulanmamis) dosyalar listelenmez;
			// "kullanilmayan gorsel" sanilip elle silinmesinler.
			'meta_query'     => array( array( 'key' => NWCS_PHOTO_META, 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	$items = array();

	foreach ( $query->posts as $attachment ) {
		$src  = wp_get_attachment_image_src( $attachment->ID, 'medium' );
		$file = get_attached_file( $attachment->ID );

		$items[] = array(
			'id'    => (int) $attachment->ID,
			'title' => $attachment->post_title,
			'alt'   => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'thumb' => $src ? $src[0] : '',
			'name'  => $file ? basename( $file ) : '',
			'size'  => $file && file_exists( $file ) ? size_format( (int) filesize( $file ) ) : '',
		);
	}

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
 * Urun formundaki "Kitaplıkta ara": dosya adi, baslik ya da alt metinde
 * gecen gorseller (en cok 30, en yeni once). Bos aramada son 30 gorsel.
 * Turkce harf katlamasi veritabaninda yapilamaz; arama hem yazildigi gibi
 * hem sadelestirilmis haliyle ("Şezlong" ve "sezlong") denenir. Bekleyen
 * Fotograf Kutusu dosyalari listelenmez.
 *
 * @param int[] $exclude Galeride zaten olanlar.
 * @return array<int, array{id:int, title:string, name:string, thumb:string}>
 */
function nwcs_media_find( string $search, array $exclude = array(), int $limit = 30 ): array {
	global $wpdb;

	$search  = trim( $search );
	$exclude = array_values( array_filter( array_map( 'absint', $exclude ) ) );
	$terms   = array_values( array_unique( array_filter( array( $search, nwcs_search_fold( $search ) ) ) ) );

	switch_to_blog( nwcs_pool_blog_id() );

	$base = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post__not_in'   => $exclude,
		'meta_query'     => array( array( 'key' => NWCS_PHOTO_META, 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	);

	$ids = array();

	if ( ! $terms ) {
		$ids = get_posts( $base );
	} else {
		foreach ( $terms as $term ) {
			// Baslik, aciklama, alt metin (WordPress aramasi).
			$ids = array_merge( $ids, get_posts( $base + array( 's' => $term ) ) );

			// Dosya adi ve alt metin.
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			$ids  = array_merge(
				$ids,
				array_map(
					'intval',
					$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '_wp_attached_file', '_wp_attachment_image_alt' ) AND meta_value LIKE %s ORDER BY post_id DESC LIMIT %d",
							$like,
							$limit * 2
						)
					)
				)
			);
		}

		$ids = $ids ? get_posts( array_merge( $base, array( 'post__in' => array_values( array_unique( array_map( 'intval', $ids ) ) ) ) ) ) : array();
	}

	$out = array();

	foreach ( $ids as $id ) {
		$src  = wp_get_attachment_image_src( (int) $id, 'thumbnail' );
		$file = (string) get_post_meta( (int) $id, '_wp_attached_file', true );

		$out[] = array(
			'id'    => (int) $id,
			'title' => (string) get_the_title( (int) $id ),
			'name'  => wp_basename( $file ),
			'thumb' => $src ? (string) $src[0] : '',
		);
	}

	restore_current_blog();

	return $out;
}

add_action( 'wp_ajax_nwcs_media_find', 'nwcs_ajax_media_find' );
function nwcs_ajax_media_find(): void {
	check_ajax_referer( 'nwcs_panel', 'nonce' );

	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
	}

	$search  = isset( $_POST['q'] ) ? nwcs_clean_text( wp_unslash( $_POST['q'] ) ) : '';
	$exclude = isset( $_POST['haric'] ) && is_array( $_POST['haric'] ) ? array_map( 'absint', wp_unslash( $_POST['haric'] ) ) : array();

	wp_send_json_success( array( 'items' => nwcs_media_find( $search, $exclude ) ) );
}

/**
 * Gorunum tercihi: kart mi liste mi. Adres cubugunda gelirse kullanici
 * basina kaydedilir, yoksa son tercih kullanilir.
 */
function nwcs_media_view(): string {
	$user = get_current_user_id();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum tercihi.
	$asked = isset( $_GET['gorunum'] ) ? sanitize_key( wp_unslash( $_GET['gorunum'] ) ) : '';

	if ( in_array( $asked, array( 'grid', 'list' ), true ) ) {
		update_user_meta( $user, 'nwcs_media_view', $asked );

		return $asked;
	}

	$saved = (string) get_user_meta( $user, 'nwcs_media_view', true );

	return 'list' === $saved ? 'list' : 'grid';
}

/**
 * Sayfa govdesi.
 */
function nwcs_render_media(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum filtresi.
	$search = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';
	$page   = isset( $_GET['sayfa'] ) ? max( 1, absint( $_GET['sayfa'] ) ) : 1;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$is_video = isset( $_GET['tur'] ) && 'video' === $_GET['tur']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum.
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu
					aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Medya Havuzu</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu ↗</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_panel_url( 0 ) ); ?>">İçerik Stüdyosu ↗</a>
			</div>
		</header>

		<?php nwcs_render_media_tabs( $is_video ); ?>

		<?php
		if ( $is_video ) {
			nwcs_render_media_videos( $search, $page );
			echo '</div>';

			return;
		}

		$view   = nwcs_media_view();
		$result = nwcs_media_query( $search, $page );
		$usage  = nwcs_media_usage();
		?>

		<p class="nwcs-help">
			<span class="nwcs-help__step"><b>1</b> Ürün fotoğrafları: <a href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Ürün Havuzu › Kategoriler</a> › kategori › Fotoğrafları yükleyin; dosya adı ürün koduyla başlar</span>
			<span class="nwcs-help__step"><b>2</b> Site görselleri (logo, hero, blog) buraya yüklenir</span>
			<span class="nwcs-help__step"><b>3</b> Alt metni doldurun</span>
		</p>

		<?php nwcs_render_media_notices(); ?>

		<div class="nwcs-pool">
			<section class="nwcs-pool__list">
				<div class="nwcs-toolbar">
					<form method="get" class="nwcs-toolbar__search">
						<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_MEDIA_SLUG ); ?>" />
						<input class="nwcs-input" type="search" name="ara" value="<?php echo esc_attr( $search ); ?>"
							placeholder="Görsel ara…" />
						<button type="submit" class="button">Ara</button>
						<?php if ( '' !== $search ) : ?>
							<a class="button" href="<?php echo esc_url( nwcs_media_url() ); ?>">Temizle</a>
						<?php endif; ?>
					</form>
					<span class="nwcs-toolbar__count"><?php echo (int) $result['total']; ?> görsel</span>

					<div class="nwcs-viewtoggle" role="group" aria-label="Görünüm">
						<a class="<?php echo 'grid' === $view ? 'is-active' : ''; ?>"
							href="<?php echo esc_url( nwcs_media_url( array( 'ara' => $search, 'gorunum' => 'grid' ) ) ); ?>">Kart</a>
						<a class="<?php echo 'list' === $view ? 'is-active' : ''; ?>"
							href="<?php echo esc_url( nwcs_media_url( array( 'ara' => $search, 'gorunum' => 'list' ) ) ); ?>">Liste</a>
					</div>
				</div>

				<?php if ( $result['items'] ) : ?>
					<?php // Kartlarda tekli formlar var; kutular bu forma form="" ile baglanir (ic ice form olmaz). ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-media-bulk" class="nwcs-bulkbar"
						onsubmit="return confirm('Seçilen görseller kalıcı olarak silinsin mi? Bu işlem geri alınamaz.');">
						<input type="hidden" name="action" value="nwcs_media_bulk_delete" />
						<input type="hidden" name="ara" value="<?php echo esc_attr( $search ); ?>" />
						<?php wp_nonce_field( 'nwcs_media_bulk_delete' ); ?>
						<label class="nwcs-bulkbar__all">
							<input type="checkbox" data-nwcs-check-all /> Tümünü seç
						</label>
						<button type="submit" class="button nwcs-row__delete">Seçilenleri sil</button>
						<span class="nwcs-hint">Bir üründe kullanılan görsel seçilemez; önce ürünün galerisinden çıkarın.</span>
					</form>
				<?php endif; ?>

				<?php if ( ! $result['items'] ) : ?>
					<p class="nwcs-empty">Görsel bulunamadı.</p>
				<?php elseif ( 'list' === $view ) : ?>
					<table class="nwcs-medialist">
						<thead>
							<tr>
								<th class="nwcs-table__check"><span class="screen-reader-text">Seç</span></th>
								<th class="nwcs-medialist__pic"></th>
								<th>Dosya</th>
								<th>Başlık ve alt metin</th>
								<th>Kullanım</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['items'] as $item ) : ?>
								<?php $used = $usage[ $item['id'] ] ?? array(); ?>
								<tr>
									<td class="nwcs-table__check">
										<?php if ( ! $used ) : ?>
											<input type="checkbox" name="gorseller[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>"
												form="nwcs-media-bulk" data-nwcs-check aria-label="<?php echo esc_attr( $item['name'] ); ?>" />
										<?php endif; ?>
									</td>
									<td class="nwcs-medialist__pic">
										<?php if ( $item['thumb'] ) : ?>
											<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" />
										<?php endif; ?>
									</td>

									<td>
										<span class="nwcs-medialist__name"><?php echo esc_html( $item['name'] ); ?></span>
										<?php if ( $item['size'] ) : ?>
											<span class="nwcs-medialist__size"><?php echo esc_html( $item['size'] ); ?></span>
										<?php endif; ?>
									</td>

									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-medialist__form">
											<input type="hidden" name="action" value="nwcs_media_update" />
											<input type="hidden" name="attachment" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
											<?php wp_nonce_field( 'nwcs_media_update_' . $item['id'] ); ?>

											<input class="nwcs-input" type="text" name="title"
												value="<?php echo esc_attr( $item['title'] ); ?>" aria-label="Başlık" />
											<input class="nwcs-input" type="text" name="alt"
												value="<?php echo esc_attr( $item['alt'] ); ?>"
												placeholder="Alt metin" aria-label="Alt metin" />
											<button type="submit" class="button button-small">Kaydet</button>
										</form>
									</td>

									<td class="nwcs-medialist__used">
										<?php if ( $used ) : ?>
											<?php nwcs_media_render_usage( $used ); ?>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>

									<td>
										<?php if ( ! $used ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
												onsubmit="return confirm('Bu görsel kalıcı olarak silinsin mi?');">
												<input type="hidden" name="action" value="nwcs_media_delete" />
												<input type="hidden" name="attachment" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
												<?php wp_nonce_field( 'nwcs_media_delete_' . $item['id'] ); ?>
												<button type="submit" class="button button-small nwcs-row__delete">Sil</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="nwcs-mediagrid">
						<?php foreach ( $result['items'] as $item ) : ?>
							<?php $used = $usage[ $item['id'] ] ?? array(); ?>
							<figure class="nwcs-mediacard">
								<div class="nwcs-mediacard__thumb">
									<?php if ( ! $used ) : ?>
										<input type="checkbox" class="nwcs-mediacard__check" name="gorseller[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>"
											form="nwcs-media-bulk" data-nwcs-check aria-label="<?php echo esc_attr( $item['name'] ); ?>" />
									<?php endif; ?>
									<?php if ( $item['thumb'] ) : ?>
										<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" />
									<?php endif; ?>
								</div>

								<figcaption>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-mediacard__form">
										<input type="hidden" name="action" value="nwcs_media_update" />
										<input type="hidden" name="attachment" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
										<?php wp_nonce_field( 'nwcs_media_update_' . $item['id'] ); ?>

										<label class="nwcs-sublabel">Başlık</label>
										<input class="nwcs-input" type="text" name="title" value="<?php echo esc_attr( $item['title'] ); ?>" />

										<label class="nwcs-sublabel">Alt metin</label>
										<input class="nwcs-input" type="text" name="alt" value="<?php echo esc_attr( $item['alt'] ); ?>"
											placeholder="Görseli kısaca tarif edin" />

										<p class="nwcs-mediacard__meta">
											<?php echo esc_html( $item['name'] ); ?>
											<?php if ( $item['size'] ) : ?>· <?php echo esc_html( $item['size'] ); ?><?php endif; ?>
										</p>

										<?php if ( $used ) : ?>
											<p class="nwcs-mediacard__used">Kullanımda: <?php nwcs_media_render_usage( $used ); ?></p>
											<p class="nwcs-hint">Silmek için önce ürünün galerisinden çıkarın.</p>
										<?php endif; ?>

										<div class="nwcs-mediacard__actions">
											<button type="submit" class="button button-small">Kaydet</button>
										</div>
									</form>

									<?php if ( ! $used ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
											onsubmit="return confirm('Bu görsel kalıcı olarak silinsin mi?');">
											<input type="hidden" name="action" value="nwcs_media_delete" />
											<input type="hidden" name="attachment" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
											<?php wp_nonce_field( 'nwcs_media_delete_' . $item['id'] ); ?>
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
						static fn( int $target ): string => nwcs_media_url( array( 'ara' => $search, 'sayfa' => $target ) )
					);
					?>
				<?php endif; ?>
			</section>

			<section class="nwcs-pool__form">
				<div class="nwcs-pool__card">
					<h2 class="nwcs-pool__title">Görsel yükle</h2>

					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="nwcs_media_upload" />
						<?php wp_nonce_field( 'nwcs_media_upload' ); ?>

						<div class="nwcs-field">
							<label class="nwcs-field__label" for="nwcs-media-files">Dosyalar</label>
							<input class="nwcs-file" type="file" id="nwcs-media-files" name="files[]" accept="image/*" multiple />
							<p class="nwcs-hint">
								Birden fazla dosya seçebilirsiniz. JPG, PNG, WebP ve iPhone (HEIC) fotoğrafları olur;
								dosya başına en fazla <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?>.
								<?php if ( function_exists( 'nwcs_image_webp_supported' ) && nwcs_image_webp_supported() ) : ?>
									Fotoğraflar yüklenirken sitede hızlı açılacak şekilde küçültülür ve WebP'ye çevrilir.
								<?php endif; ?>
							</p>
							<p class="nwcs-hint">Ürün fotoğrafı için kategorinin sayfasındaki Fotoğraf Kutusu’nu kullanın; buradan yüklenen görsel ürüne kendiliğinden bağlanmaz.</p>
						</div>

						<div class="nwcs-field">
							<label class="nwcs-field__label" for="nwcs-media-alt">Ortak alt metin (isteğe bağlı)</label>
							<input class="nwcs-input" type="text" id="nwcs-media-alt" name="alt" />
							<p class="nwcs-hint">Doldurursanız yüklenen tüm görsellere aynı alt metin yazılır; sonra tek tek düzenleyebilirsiniz.</p>
						</div>

						<div class="nwcs-actions">
							<button type="submit" class="button button-primary">Yükle</button>
						</div>
					</form>
				</div>
			</section>
		</div>
	</div>
	<?php
}

/**
 * Sayfalama baglantilari.
 */
function nwcs_render_pagination( int $pages, int $current, callable $url_for ): void {
	if ( $pages < 2 ) {
		return;
	}

	echo '<nav class="nwcs-pagination" aria-label="Sayfalama">';

	for ( $i = 1; $i <= $pages; $i++ ) {
		printf(
			'<a class="nwcs-pagination__link%1$s" href="%2$s">%3$d</a>',
			$i === $current ? ' is-active' : '',
			esc_url( $url_for( $i ) ),
			$i
		);
	}

	echo '</nav>';
}

function nwcs_render_media_notices(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$key   = isset( $_GET['nwcs_media'] ) ? sanitize_key( wp_unslash( $_GET['nwcs_media'] ) ) : '';
	$count   = isset( $_GET['adet'] ) ? absint( $_GET['adet'] ) : 0;
	$skipped = isset( $_GET['atlanan'] ) ? absint( $_GET['atlanan'] ) : 0;
	$too_big = isset( $_GET['buyuk'] ) ? absint( $_GET['buyuk'] ) : 0;
	$failed  = isset( $_GET['hatali'] ) ? absint( $_GET['hatali'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$limit    = size_format( wp_max_upload_size() );
	$problems = trim(
		( $too_big ? sprintf( ' %1$d dosya çok büyük olduğu için yüklenmedi (dosya başına en fazla %2$s).', $too_big, $limit ) : '' )
		. ( $failed ? sprintf( ' %d dosya yüklenemedi; dosya türü desteklenmiyor ya da dosya bozuk olabilir.', $failed ) : '' )
	);

	$map = array(
		'uploaded' => array( $problems ? 'warning' : 'success', trim( sprintf( '%d görsel yüklendi. ', $count ) . $problems ) ),
		'updated'  => array( 'success', 'Görsel bilgileri kaydedildi.' ),
		'deleted'  => array( 'success', 'Görsel silindi.' ),
		'bulk_deleted' => array(
			'success',
			sprintf( '%d görsel silindi.', $count ) . ( $skipped ? sprintf( ' %d görsel bir üründe kullanıldığı için silinmedi.', $skipped ) : '' ),
		),
		'bulk_empty' => array( 'error', 'Görsel seçilmedi.' ),
		'in_use'   => array( 'error', 'Bu görsel bir üründe kullanılıyor; önce üründen çıkarın.' ),
		'failed'   => array( 'error', '' !== $problems ? 'Yükleme başarısız oldu.' . ' ' . $problems : 'Yükleme başarısız oldu. Dosya türü veya boyutu uygun olmayabilir.' ),
		'too_big_total' => array( 'error', sprintf( 'Seçilen dosyaların toplamı çok büyük; sunucu hiçbirini almadı. Daha az dosyayla tekrar deneyin (dosya başına en fazla %s).', $limit ) ),
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

add_action( 'admin_post_nwcs_media_upload', 'nwcs_handle_media_upload' );
function nwcs_handle_media_upload(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_media_upload' );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$alt     = isset( $_POST['alt'] ) ? nwcs_clean_text( wp_unslash( $_POST['alt'] ) ) : '';
	$files   = $_FILES['files'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida tek tek islenir.
	$count   = 0;
	$too_big = 0;
	$failed  = 0;
	$limit   = wp_max_upload_size();

	if ( ! is_array( $files ) || ! isset( $files['name'] ) || ! is_array( $files['name'] ) ) {
		// Secilenlerin toplami post_max_size'i asinca PHP $_FILES'i bos birakir.
		nwcs_media_redirect( 'too_big_total', 0 );
	}

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( array_keys( $files['name'] ) as $index ) {
		$error = (int) $files['error'][ $index ];

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			continue;
		}

		if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) $files['size'][ $index ] > $limit ) {
			++$too_big;
			continue;
		}

		// media_handle_upload tek dosya bekler; gecici olarak tek girise indiriyoruz.
		$_FILES['nwcs_single'] = array(
			'name'     => $files['name'][ $index ],
			'type'     => $files['type'][ $index ],
			'tmp_name' => $files['tmp_name'][ $index ],
			'error'    => $files['error'][ $index ],
			'size'     => $files['size'][ $index ],
		);

		$attachment_id = media_handle_upload( 'nwcs_single', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			++$failed;
			continue;
		}

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		++$count;
	}

	unset( $_FILES['nwcs_single'] );

	restore_current_blog();

	nwcs_pool_flush_cache();

	wp_safe_redirect(
		nwcs_media_url(
			array_filter(
				array(
					'nwcs_media' => $count ? 'uploaded' : 'failed',
					'adet'       => $count,
					'buyuk'      => $too_big,
					'hatali'     => $failed,
				)
			)
		)
	);
	exit;
}

add_action( 'admin_post_nwcs_media_update', 'nwcs_handle_media_update' );
function nwcs_handle_media_update(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	$attachment_id = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	check_admin_referer( 'nwcs_media_update_' . $attachment_id );

	if ( $attachment_id ) {
		switch_to_blog( nwcs_pool_blog_id() );

		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => isset( $_POST['title'] ) ? nwcs_clean_text( wp_unslash( $_POST['title'] ) ) : '',
			)
		);

		update_post_meta(
			$attachment_id,
			'_wp_attachment_image_alt',
			isset( $_POST['alt'] ) ? nwcs_clean_text( wp_unslash( $_POST['alt'] ) ) : ''
		);

		restore_current_blog();
		nwcs_pool_flush_cache();
	}

	nwcs_media_redirect( 'updated', 0 );
}

add_action( 'admin_post_nwcs_media_delete', 'nwcs_handle_media_delete' );
function nwcs_handle_media_delete(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	$attachment_id = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	check_admin_referer( 'nwcs_media_delete_' . $attachment_id );

	if ( isset( nwcs_media_usage()[ $attachment_id ] ) ) {
		nwcs_media_redirect( 'in_use', 0 );
	}

	if ( $attachment_id ) {
		switch_to_blog( nwcs_pool_blog_id() );
		wp_delete_attachment( $attachment_id, true );
		restore_current_blog();
		nwcs_pool_flush_cache();
	}

	nwcs_media_redirect( 'deleted', 0 );
}

/**
 * Secilen gorselleri siler. Bir urunde (cop dahil) kullanilanlar atlanir.
 */
add_action( 'admin_post_nwcs_media_bulk_delete', 'nwcs_handle_media_bulk_delete' );
function nwcs_handle_media_bulk_delete(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_media_bulk_delete' );

	$ids = isset( $_POST['gorseller'] ) && is_array( $_POST['gorseller'] )
		? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['gorseller'] ) ) ) ) )
		: array();

	if ( ! $ids ) {
		nwcs_media_redirect( 'bulk_empty', 0 );
	}

	$usage   = nwcs_media_usage();
	$deleted = 0;
	$skipped = 0;

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $ids as $attachment_id ) {
		if ( isset( $usage[ $attachment_id ] ) ) {
			++$skipped;
			continue;
		}

		if ( 'attachment' === get_post_type( $attachment_id ) && wp_delete_attachment( $attachment_id, true ) ) {
			++$deleted;
		}
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	$search = isset( $_POST['ara'] ) ? sanitize_text_field( wp_unslash( $_POST['ara'] ) ) : '';

	wp_safe_redirect(
		nwcs_media_url(
			array_filter(
				array(
					'nwcs_media' => 'bulk_deleted',
					'adet'       => $deleted,
					'atlanan'    => $skipped,
					'ara'        => $search,
				)
			)
		)
	);
	exit;
}

function nwcs_media_redirect( string $key, int $count ): void {
	$args = array( 'nwcs_media' => $key );

	if ( $count ) {
		$args['adet'] = $count;
	}

	wp_safe_redirect( nwcs_media_url( $args ) );
	exit;
}
