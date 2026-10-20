<?php
/**
 * Ag Yonetimi -> Urun Havuzu.
 *
 * Urunler burada bir kez girilir; siteler bu havuzdan beslenir. Gorseller
 * havuz sitesinin WordPress medya kitapligindadir (bkz. Medya Havuzu sayfasi).
 *
 * Tum yazma islemleri admin-post.php uzerinden, nonce + yetki kontrolu ile.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_POOL_SLUG     = 'nwcs-pool';
const NWCS_POOL_PER_PAGE = 20;

add_action( 'network_admin_menu', 'nwcs_register_pool_menu' );
function nwcs_register_pool_menu(): void {
	add_menu_page(
		'Ürün Havuzu',
		'Ürün Havuzu',
		NWCS_CAPABILITY,
		NWCS_POOL_SLUG,
		'nwcs_render_pool',
		'dashicons-screenoptions',
		'3.1' // Icerik Studyosu'nun hemen alti (bkz. panel.php).
	);
}

/**
 * Havuz sayfasi adresi.
 */
function nwcs_pool_url( array $args = array() ): string {
	return add_query_arg(
		array_merge( array( 'page' => NWCS_POOL_SLUG ), $args ),
		network_admin_url( 'admin.php' )
	);
}

/**
 * Sayfa govdesi: arac cubugu + liste + urun formu. Kategoriler, yerlesim ve
 * Excel ayri sayfada (Ürün Havuzu -> Kategoriler, admin/pool-categories.php).
 */
function nwcs_render_pool(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum secimi.
	$edit_id  = isset( $_GET['urun'] ) ? absint( $_GET['urun'] ) : 0;
	$is_new   = isset( $_GET['yeni'] );
	$search   = isset( $_GET['ara'] ) ? nwcs_clean_text( wp_unslash( $_GET['ara'] ) ) : '';
	$category = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : '';
	$page     = isset( $_GET['sayfa'] ) ? max( 1, absint( $_GET['sayfa'] ) ) : 1;
	$missing  = isset( $_GET['eksik'] ) ? sanitize_key( wp_unslash( $_GET['eksik'] ) ) : '';
	$missing  = isset( nwcs_pool_missing_filters()[ $missing ] ) ? $missing : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$all        = nwcs_pool_products();
	$categories = nwcs_pool_categories();
	$result     = nwcs_pool_query(
		array(
			'search'   => $search,
			'category' => $category,
			'missing'  => $missing,
			'page'     => $page,
			'per_page' => NWCS_POOL_PER_PAGE,
		)
	);

	$editing = $edit_id && isset( $all[ $edit_id ] ) ? $all[ $edit_id ] : null;
	$trashed = nwcs_pool_trashed_count();

	// Kaydedilemeyen form: girilenler geri gelir.
	if ( isset( $_GET['taslak'] ) && ( $editing || $is_new ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing = nwcs_pool_take_draft( $editing ) ?? $editing;
	}
	$filters = array_filter( array( 'ara' => $search, 'kategori' => $category, 'eksik' => $missing ) );
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu
					aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Ürün Havuzu</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url( array( 'yeni' => 1 ) ) ); ?>">+ Yeni ürün</a>
				<a class="nwcs-linkout nwcs-linkout--accent" href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Kategoriler ve Excel</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_headings_url() ); ?>">Detay başlıkları</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_descriptions_url() ); ?>">Ürün açıklamaları</a>
				<?php if ( $trashed ) : ?>
					<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url( array( 'cop' => 1 ) ) ); ?>">Çöp kutusu (<?php echo (int) $trashed; ?>)</a>
				<?php endif; ?>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_media_url() ); ?>">Medya Havuzu ↗</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_panel_url( 0 ) ); ?>">İçerik Stüdyosu ↗</a>
			</div>
		</header>
		<?php // WordPress bildirimleri bu isaretin altina tasir (ust seridin icine degil). ?>
		<hr class="wp-header-end" />

		<p class="nwcs-help">
			<span class="nwcs-help__step"><b>1</b> <a href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Kategoriyi aç ve sitelere yerleştir</a></span>
			<span class="nwcs-help__step"><b>2</b> Excel’i indir, doldur, yükle</span>
			<span class="nwcs-help__step"><b>3</b> <a href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Fotoğrafları kategorinin sayfasından yükleyin</a> (dosya adı = ürün kodu)</span>
		</p>

		<?php nwcs_render_pool_notices(); ?>
		<?php nwcs_render_cache_note(); ?>

		<?php
		if ( isset( $_GET['cop'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gorunum secimi.
			nwcs_render_pool_trash();
			echo '</div>';
			return;
		}
		?>

		<div class="nwcs-pool">
			<section class="nwcs-pool__list">
				<?php nwcs_render_pool_toolbar( $search, $category, $categories, (int) $result['total'], $missing ); ?>

				<?php if ( ! $result['items'] ) : ?>
					<p class="nwcs-empty">
						<?php echo $all ? 'Bu filtreye uyan ürün yok.' : 'Henüz ürün yok. Sağ üstteki “+ Yeni ürün” ile başlayın.'; ?>
					</p>
				<?php else : ?>
					<?php nwcs_render_pool_table( $result['items'], $editing, $categories, $filters ); ?>

					<?php
					nwcs_render_pagination(
						(int) $result['pages'],
						(int) $result['page'],
						static fn( int $target ): string => nwcs_pool_url( array_merge( $filters, array( 'sayfa' => $target ) ) )
					);
					?>
				<?php endif; ?>
			</section>

			<section class="nwcs-pool__form">
				<?php nwcs_render_pool_form( $editing, $categories, $is_new ); ?>
			</section>
		</div>
	</div>
	<?php
}

/**
 * Arama, kategori filtresi ve sonuc sayisi.
 */
/**
 * Ürünler listesindeki "Eksik" süzgeci: değer => etiket.
 *
 * @return array<string, string>
 */
function nwcs_pool_missing_filters(): array {
	return array(
		'photo'   => 'Fotoğrafı olmayan',
		'body'    => 'Açıklaması olmayan',
		'details' => 'Teknik detayı olmayan',
		'nowhere' => 'Hiçbir sitede görünmeyen',
	);
}

function nwcs_render_pool_toolbar( string $search, string $category, array $categories, int $total, string $missing = '' ): void {
	$no_photo = '' === $search . $category . $missing
		? count( array_filter( nwcs_pool_products(), static fn( array $product ): bool => empty( $product['images'] ) ) )
		: 0;
	?>
	<div class="nwcs-toolbar">
		<form method="get" class="nwcs-toolbar__search">
			<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_POOL_SLUG ); ?>" />

			<input class="nwcs-input" type="search" name="ara" value="<?php echo esc_attr( $search ); ?>"
				placeholder="Ürün adı veya kodu ara…" />

			<select class="nwcs-input" name="kategori">
				<option value="">Tüm kategoriler</option>
				<?php foreach ( $categories as $slug => $term ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $category ); ?>>
						<?php echo esc_html( $term['name'] ); ?> (<?php echo (int) $term['count']; ?>)
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="nwcs-pool-missing">Eksik</label>
			<select class="nwcs-input" name="eksik" id="nwcs-pool-missing">
				<option value="">Eksik: hepsi</option>
				<?php foreach ( nwcs_pool_missing_filters() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $missing ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button">Filtrele</button>

			<?php if ( '' !== $search || '' !== $category || '' !== $missing ) : ?>
				<a class="button" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Temizle</a>
			<?php endif; ?>
		</form>

		<span class="nwcs-toolbar__count">
			<?php echo (int) $total; ?> ürün
			<?php if ( $no_photo ) : ?>
			· <a href="<?php echo esc_url( nwcs_pool_url( array( 'eksik' => 'photo' ) ) ); ?>"><?php echo esc_html( sprintf( '%d üründe fotoğraf yok', $no_photo ) ); ?></a>
			<?php endif; ?>
		</span>
	</div>
	<?php
}

/**
 * Urun tablosu + toplu islemler.
 */
function nwcs_render_pool_table( array $items, ?array $editing, array $categories, array $filters ): void {
	$sites = nwcs_editable_sites();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-bulkform"
		onsubmit="return this.bulk_action.value !== 'trash:' || confirm('Seçilen ürünler çöp kutusuna taşınsın mı? Gösterildikleri bütün sitelerden kalkarlar. Çöp kutusundan geri getirebilirsiniz.');">
		<input type="hidden" name="action" value="nwcs_pool_bulk" />
		<?php wp_nonce_field( 'nwcs_pool_bulk' ); ?>
		<?php foreach ( $filters as $key => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<?php endforeach; ?>

		<div class="nwcs-bulkbar">
			<label class="nwcs-bulkbar__all">
				<input type="checkbox" data-nwcs-check-all /> Tümünü seç
			</label>

			<select class="nwcs-input" name="bulk_action">
				<option value="">Toplu işlem…</option>
				<option value="trash:">Çöp kutusuna taşı</option>
				<?php foreach ( $sites as $blog_id => $site ) : ?>
					<option value="show:<?php echo esc_attr( (string) $blog_id ); ?>">
						<?php echo esc_html( $site['label'] ); ?> sitesinde göster
					</option>
					<option value="hide:<?php echo esc_attr( (string) $blog_id ); ?>">
						<?php echo esc_html( $site['label'] ); ?> sitesinde gizle
					</option>
				<?php endforeach; ?>
				<?php foreach ( $categories as $slug => $term ) : ?>
					<option value="cat:<?php echo esc_attr( $slug ); ?>">
						“<?php echo esc_html( $term['name'] ); ?>” kategorisine ekle
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button">Uygula</button>
		</div>

		<table class="nwcs-table">
			<thead>
				<tr>
					<th></th>
					<th>Görsel</th>
					<th>Ürün</th>
					<th>Fiyat</th>
					<th>Kategori</th>
					<th>Sitelerde</th>
					<th>Eksik</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $product ) : ?>
					<tr<?php echo $editing && $editing['id'] === $product['id'] ? ' class="is-editing"' : ''; ?>>
						<td class="nwcs-table__check">
							<input type="checkbox" name="urunler[]" value="<?php echo esc_attr( (string) $product['id'] ); ?>" data-nwcs-check />
						</td>
						<td class="nwcs-table__thumb">
							<?php if ( $product['image']['url'] ) : ?>
								<img src="<?php echo esc_url( $product['image']['url'] ); ?>" alt="" />
							<?php else : ?>
								<span class="nwcs-image__empty">yok</span>
							<?php endif; ?>
							<?php if ( count( $product['images'] ) > 1 ) : ?>
								<span class="nwcs-table__count">+<?php echo (int) ( count( $product['images'] ) - 1 ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( $product['title'] ); ?></strong>
							<?php if ( ! empty( $product['video'] ) ) : ?>
								<span class="nwcs-table__video" title="Videosu var">▶<span class="screen-reader-text"> Videosu var</span></span>
							<?php endif; ?>
							<?php $labels = array_column( (array) $product['details'], 'label' ); ?>
							<?php if ( $labels ) : ?>
								<span class="nwcs-table__spec"><?php echo esc_html( sprintf( '%d detay: %s%s', count( $labels ), implode( ', ', array_slice( $labels, 0, 3 ) ), count( $labels ) > 3 ? ', …' : '' ) ); ?></span>
							<?php elseif ( $product['spec'] ) : ?>
								<span class="nwcs-table__spec"><?php echo esc_html( $product['spec'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="nwcs-nowrap">
							<?php if ( '' !== trim( $product['price'] ) ) : ?>
								<?php echo esc_html( $product['price'] ); ?>
							<?php else : ?>
								<em class="nwcs-quote">Teklif al</em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( implode( ', ', $product['categories'] ) ); ?></td>
						<td><?php echo esc_html( nwcs_product_usage_label( $product['id'] ) ); ?></td>
						<td><?php nwcs_render_product_gaps( $product ); ?></td>
						<td>
							<div class="nwcs-table__actions">
								<a class="button button-small" href="<?php echo esc_url( nwcs_pool_url( array( 'urun' => $product['id'] ) ) ); ?>">Düzenle</a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<?php
}

/**
 * Eksik cipleri: "fotoğraf · açıklama · detay" (yalnizca eksikler); tamsa "—".
 */
function nwcs_render_product_gaps( array $product ): void {
	$labels = nwcs_product_gap_labels();
	$gaps   = nwcs_product_gaps( $product );

	if ( ! $gaps ) {
		echo '<span class="nwcs-gaps__none">—</span>';

		return;
	}

	echo '<span class="nwcs-gaps__chips">';

	foreach ( $gaps as $gap ) {
		echo '<span class="nwcs-gaps__chip">' . esc_html( $labels[ $gap ] ) . '</span>';
	}

	echo '</span>';
}

/**
 * Urunun hangi sitelerde gorundugunu ozetler.
 */
function nwcs_product_usage_label( int $product_id ): string {
	// Temasinda urun alani olmayan site urun gosteremez; sayilmaz (nwcs_product_site_labels).
	$labels = nwcs_product_site_labels( $product_id );

	return $labels ? implode( ', ', $labels ) : '—';
}

/**
 * Yeni/duzenleme formu.
 */
function nwcs_render_pool_form( ?array $product, array $categories, bool $is_new ): void {
	if ( ! $product && ! $is_new ) {
		echo '<div class="nwcs-pool__card nwcs-pool__card--hint">';
		echo '<h2 class="nwcs-pool__title">Ürün ekle / düzenle</h2>';
		echo '<p class="nwcs-empty">Soldaki listeden bir ürün seçin ya da “+ Yeni ürün” deyin.</p>';
		echo '</div>';

		return;
	}

	$id       = $product['id'] ?? 0;
	$selected = $product['categories'] ?? array();
	$media    = nwcs_pool_media();
	?>
	<div class="nwcs-pool__card">
		<h2 class="nwcs-pool__title"><?php echo $id ? 'Ürünü düzenle' : 'Yeni ürün'; ?></h2>

		<?php
		if ( $id && ! isset( $product['taken_by'] ) ) {
			nwcs_render_product_status( $product );
			nwcs_render_product_color_line( $product );
		}
		?>

		<?php if ( isset( $product['taken_by'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong>Kaydedilmedi.</strong> Yazdıklarınız aşağıda duruyor; düzeltip yeniden kaydedin.
					<?php if ( '' !== $product['taken_by'] ) : ?>
						Bu kod şu üründe kullanılıyor: <strong><?php echo esc_html( $product['taken_by'] ); ?></strong>.
					<?php endif; ?>
					<?php if ( ! empty( $product['had_upload'] ) ) : ?>
						Seçtiğiniz yeni görsel dosyalarını yeniden seçmeniz gerekiyor.
					<?php endif; ?>
					<?php if ( ! empty( $product['had_video'] ) ) : ?>
						Video dosyasını yeniden seçmeniz gerekiyor.
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nwcs_pool_save" />
			<input type="hidden" name="urun" value="<?php echo esc_attr( (string) $id ); ?>" />
			<?php wp_nonce_field( 'nwcs_pool_save_' . $id ); ?>

			<div class="nwcs-field">
				<label class="nwcs-field__label" for="nwcs-p-title">Ürün adı</label>
				<input class="nwcs-input" type="text" id="nwcs-p-title" name="title" required
					value="<?php echo esc_attr( $product['title'] ?? '' ); ?>" />
			</div>

			<?php $code_missing = 'code_empty' === ( $product['error'] ?? '' ); ?>
			<div class="nwcs-field<?php echo $code_missing ? ' is-invalid' : ''; ?>">
				<label class="nwcs-field__label" for="nwcs-p-code">Ürün kodu <span class="nwcs-req" aria-hidden="true">*</span></label>
				<input class="nwcs-input" type="text" id="nwcs-p-code" name="code" required aria-required="true"
					<?php echo $code_missing ? 'aria-invalid="true" aria-describedby="nwcs-p-code-error nwcs-p-code-hint"' : 'aria-describedby="nwcs-p-code-hint"'; ?>
					value="<?php echo esc_attr( $product['code'] ?? '' ); ?>" placeholder="örn. W-KAM-400DUB" />
				<?php if ( $code_missing ) : ?>
					<p class="nwcs-field__error" id="nwcs-p-code-error">Ürün kodu boş. Bu alana ürünün kodunu yazın; fotoğraflar ürüne bu kodla bağlanır.</p>
				<?php endif; ?>
				<p class="nwcs-hint" id="nwcs-p-code-hint">
					Zorunlu; her ürünün kendine ait kodu. Fotoğraf adları bu kodla başlar (W-KAM-400DUB-1.jpg);
					Excel yüklemesi de bu kodla eşleştirme yapar.
				</p>
			</div>

			<div class="nwcs-field">
				<label class="nwcs-field__label" for="nwcs-p-short">Kart açıklaması (kısa)</label>
				<textarea class="nwcs-input" id="nwcs-p-short" name="short" rows="3"><?php echo esc_textarea( $product['short'] ?? '' ); ?></textarea>
				<p class="nwcs-hint">Sitelerdeki ürün kartında görünür.</p>
			</div>

			<div class="nwcs-field">
				<label class="nwcs-field__label" for="nwcs-p-price">Fiyat</label>
				<input class="nwcs-input" type="text" id="nwcs-p-price" name="price"
					value="<?php echo esc_attr( $product['price'] ?? '' ); ?>" placeholder="örn. 450 TL" />
				<p class="nwcs-hint"><strong>Boş bırakırsanız</strong> sitede fiyat yerine <em>“Teklif al”</em> görünür.</p>
			</div>

			<?php nwcs_render_product_details_field( $product ); ?>

			<?php nwcs_render_product_customizations( $product, $media ); ?>

			<div class="nwcs-field">
				<span class="nwcs-field__label">Kategoriler</span>

				<?php if ( ! $categories ) : ?>
					<p class="nwcs-hint">Henüz kategori yok. <a href="<?php echo esc_url( nwcs_pool_categories_url( array( 'yeni' => 1 ) ) ); ?>">Kategoriler</a> sayfasından açın.</p>
				<?php else : ?>
					<div class="nwcs-tags">
						<?php foreach ( $categories as $slug => $term ) : ?>
							<label class="nwcs-tag<?php echo isset( $selected[ $slug ] ) ? ' is-active' : ''; ?>">
								<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( isset( $selected[ $slug ] ) ); ?> data-nwcs-tag />
								<?php echo esc_html( $term['name'] ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<label class="nwcs-sublabel" for="nwcs-p-newcat">Yeni kategori ekle</label>
				<input class="nwcs-input" type="text" id="nwcs-p-newcat" name="new_category"
					value="<?php echo esc_attr( $product['new_category'] ?? '' ); ?>"
					placeholder="Yazıp kaydedin; bu ürüne de eklenir" />
				<p class="nwcs-hint">Ürün, kategorilerinin yerleştiği sitelerde görünür. Yerleşim <a href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Kategoriler</a> sayfasından seçilir.</p>
			</div>

			<?php nwcs_render_product_sites_field( $product ); ?>

			<div class="nwcs-field">
				<span class="nwcs-field__label">Görseller</span>
				<p class="nwcs-hint">
					İlk görsel kartta kullanılır. Sıra değiştirmek için <strong>↑ ↓</strong> düğmelerini kullanın.
					Yeni dosyalar <a href="<?php echo esc_url( nwcs_media_url() ); ?>">Medya Havuzu</a>'na eklenir.
					<?php $photo_cat = (string) array_key_first( (array) $selected ); ?>
					Toplu fotoğraf için kategorinin sayfası: <a href="<?php echo esc_url( nwcs_pool_categories_url( array_filter( array( 'kategori' => $photo_cat ) ) ) . '#nwcs-fotograf' ); ?>">Fotoğrafları yükleyin ↗</a>
				</p>

				<div class="nwcs-gallery" data-nwcs-gallery>
					<?php foreach ( $product['images'] ?? array() as $image ) : ?>
						<div class="nwcs-gallery__item" data-nwcs-gallery-item>
							<input type="hidden" name="gallery[]" value="<?php echo esc_attr( (string) $image['id'] ); ?>" />
							<?php if ( $image['url'] ) : ?>
								<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" />
							<?php endif; ?>
							<div class="nwcs-gallery__tools">
								<button type="button" class="nwcs-move" data-nwcs-gallery-move="up" aria-label="Öne al">↑</button>
								<button type="button" class="nwcs-move" data-nwcs-gallery-move="down" aria-label="Geri al">↓</button>
								<button type="button" class="nwcs-move nwcs-row__delete" data-nwcs-gallery-remove aria-label="Çıkar">×</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php nwcs_render_media_find( $product ); ?>

				<label class="nwcs-sublabel" for="nwcs-p-upload">Yeni görsel yükle</label>
				<input class="nwcs-file" type="file" id="nwcs-p-upload" name="gallery_upload[]" accept="image/*" multiple />
			</div>

			<?php nwcs_render_product_video_field( $product ); ?>

			<div class="nwcs-field">
				<label class="nwcs-field__label" for="nwcs-p-body">Ürün açıklaması (detay sayfası metni)</label>
				<textarea class="nwcs-input" id="nwcs-p-body" name="body" rows="5"><?php echo esc_textarea( $product['body'] ?? '' ); ?></textarea>
				<p class="nwcs-hint">
					Ürün sayfasında (<code>/urun/<?php echo esc_html( $product['slug'] ?? 'urun-adi' ); ?>/</code>) açıklama bölümü olarak görünür.
					Boş bırakılırsa WOOD KOCIST ve Koçist’te sayfa yine açılır (ad, görsel, teknik detaylar); diğer sitelerde ürünün kendi sayfası olmaz.
					Toplu düzenleme için kategorinin Excel’i ya da <a href="<?php echo esc_url( nwcs_descriptions_url() ); ?>">Ürün açıklamaları</a>.
				</p>
			</div>

			<?php nwcs_render_product_tables_field( $product ); ?>

			<?php
			// Yer tutucu otomatik deger: sayac bos alanda onun uzunlugunu gosterir
			// (assets/seo.js). Aciklama kesilmeden verilir; 160'i asarsa sayac kirmizi olur.
			$auto_desc = nwcs_seo_clean( '' !== trim( (string) ( $product['short'] ?? '' ) ) ? $product['short'] : ( $product['body'] ?? '' ) );
			?>
			<div class="nwcs-field">
				<span class="nwcs-field__label">Arama sonucu (meta)</span>
				<p class="nwcs-hint">
					Google’da ve bağlantı paylaşıldığında görünen başlık ve açıklama. Boş bırakırsanız otomatik:
					başlık ürün adından (WOOD KOCIST’te kod + ad; sığarsa sonuna site adı eklenir), açıklama kart açıklamasından.
				</p>

				<label class="nwcs-sublabel" for="nwcs-p-meta-title">Meta başlık</label>
				<input class="nwcs-input" type="text" id="nwcs-p-meta-title" name="meta_title" data-nwcs-count="60"
					value="<?php echo esc_attr( $product['meta_title'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( nwcs_seo_clean( $product['title'] ?? '' ) ); ?>" />

				<label class="nwcs-sublabel" for="nwcs-p-meta-desc">Meta açıklama</label>
				<textarea class="nwcs-input" id="nwcs-p-meta-desc" name="meta_desc" rows="3" data-nwcs-count="160"
					placeholder="<?php echo esc_attr( $auto_desc ); ?>"><?php echo esc_textarea( $product['meta_desc'] ?? '' ); ?></textarea>

				<p class="nwcs-hint">
					Meta başlık sayfaya olduğu gibi yazılır; site adı eklenmez. Önerilen uzunluk: başlık en fazla 60,
					açıklama en fazla 160 karakter. Uzun açıklama arama sonucunda kesilir. İkisi de tek satır kaydedilir.
				</p>
			</div>

			<div class="nwcs-actions">
				<button type="submit" class="button button-primary">Kaydet</button>
				<a class="button" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Vazgeç</a>

				<?php if ( $id ) : ?>
					<span class="nwcs-actions__spacer"></span>
				<?php endif; ?>
			</div>
		</form>

		<form method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) ); ?>" id="nwcs-find-form" hidden>
			<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_POOL_SLUG ); ?>" />
			<input type="hidden" name="<?php echo $id ? 'urun' : 'yeni'; ?>" value="<?php echo esc_attr( (string) ( $id ? $id : 1 ) ); ?>" />
		</form>

		<?php if ( $id ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-danger"
				onsubmit="return confirm('Ürün çöp kutusuna taşınsın mı? Gösterildiği bütün sitelerden kalkar. Çöp kutusundan geri getirebilirsiniz.');">
				<input type="hidden" name="action" value="nwcs_pool_delete" />
				<input type="hidden" name="urun" value="<?php echo esc_attr( (string) $id ); ?>" />
				<?php wp_nonce_field( 'nwcs_pool_delete_' . $id ); ?>
				<button type="submit" class="button button-small nwcs-row__delete">Çöp kutusuna taşı</button>
				<span class="nwcs-hint">Sitelerden kalkar; çöp kutusundan geri getirilebilir.</span>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Urun formundaki "Siteler" kutulari: Excel'deki SİTE sutununun formdaki
 * karsiligi (ayni siteler, ayni yazici: nwcs_sync_apply_sites). Isaretli
 * sitede gosterilir, isaretsizde gizlenir (silinmez). Acilistaki hal
 * sites_before ile gelir; isaretler degismediyse hicbir sey yazilmaz
 * (Excel'deki "bos = degismez"), yeni urun kategorinin yerlesimine kalir.
 */
function nwcs_render_product_sites_field( ?array $product ): void {
	$sites = nwcs_sync_sites();

	if ( ! $sites ) {
		return;
	}

	$id      = (int) ( $product['id'] ?? 0 );
	$checked = isset( $product['sites'] ) ? array_map( 'intval', (array) $product['sites'] ) : ( $id ? nwcs_sync_product_sites( $id ) : array() );
	$before  = isset( $product['sites_before'] ) ? array_map( 'intval', (array) $product['sites_before'] ) : $checked;
	?>
	<div class="nwcs-field">
		<span class="nwcs-field__label" id="nwcs-p-sites-label">Siteler</span>
		<input type="hidden" name="sites_before" value="<?php echo esc_attr( implode( ',', $before ) ); ?>" />
		<div class="nwcs-tags" role="group" aria-labelledby="nwcs-p-sites-label">
			<?php foreach ( $sites as $blog_id => $label ) : ?>
				<?php $on = in_array( (int) $blog_id, $checked, true ); ?>
				<label class="nwcs-tag<?php echo $on ? ' is-active' : ''; ?>">
					<input type="checkbox" name="sites[]" value="<?php echo (int) $blog_id; ?>" <?php checked( $on ); ?> data-nwcs-tag />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="nwcs-hint">
			Ürün işaretli sitelerde görünür; işaretsiz sitede gizlenir (silinmez, yeniden işaretleyince geri gelir).
			<?php if ( ! $id ) : ?>
				Hiçbirini işaretlemezseniz kategorinin yerleştiği sitelerde görünür.
			<?php endif; ?>
			Kategori o siteye yerleşmemişse ürün orada listelenir ama kategori sayfasında görünmez.
		</p>
	</div>
	<?php
}

/**
 * Formdan gelen site secimi: degistiyse blog_id listesi, degismediyse null.
 *
 * @return int[]|null
 */
function nwcs_posted_product_sites(): ?array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- cagiran denetledi.
	if ( ! isset( $_POST['sites_before'] ) ) {
		return null;
	}

	$known  = array_keys( nwcs_sync_sites() );
	$pick   = static fn( array $ids ): array => array_values( array_intersect( $known, array_map( 'absint', $ids ) ) );
	$before = $pick( explode( ',', (string) wp_unslash( $_POST['sites_before'] ) ) );
	$now    = $pick( isset( $_POST['sites'] ) && is_array( $_POST['sites'] ) ? array_filter( wp_unslash( $_POST['sites'] ), 'is_scalar' ) : array() );
	// phpcs:enable

	return $now === $before ? null : $now;
}

/**
 * Formun ustundeki durum seridi: urun hangi sitede gorunuyor, gorunmuyorsa
 * neden ve tek tikla duzeltme; altinda eksikler.
 */
function nwcs_render_product_status( array $product ): void {
	$id     = (int) $product['id'];
	$rows   = nwcs_product_site_status( $id );
	$labels = nwcs_product_gap_labels();
	$gaps   = nwcs_product_gaps( $product );
	$first  = (string) array_key_first( (array) ( $product['categories'] ?? array() ) );
	$verbs  = array(
		'select'  => 'Listeye ekle',
		'unhide'  => 'Göster',
		'untrash' => 'Çöpten geri getir',
	);

	if ( ! $rows && ! $gaps ) {
		return;
	}
	?>
	<div class="nwcs-status" role="group" aria-label="Sitelerde durum">
		<?php foreach ( $rows as $blog_id => $row ) : ?>
			<?php $on = str_starts_with( $row['state'], 'visible' ); ?>
			<div class="nwcs-status__row">
				<span class="nwcs-status__site"><?php echo esc_html( $row['site'] ); ?></span>
				<span class="nwcs-ovr__tag nwcs-ovr__tag--<?php echo $on ? 'on' : 'off'; ?>"><?php echo $on ? 'görünüyor' : 'görünmüyor'; ?></span>
				<span class="nwcs-status__text"><?php echo esc_html( $row['reason'] ); ?></span>
				<span class="nwcs-status__act">
					<?php if ( isset( $verbs[ $row['fix'] ] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="nwcs_product_fix" />
							<input type="hidden" name="urun" value="<?php echo (int) $id; ?>" />
							<input type="hidden" name="site" value="<?php echo (int) $blog_id; ?>" />
							<input type="hidden" name="ne" value="<?php echo esc_attr( $row['fix'] ); ?>" />
							<?php wp_nonce_field( 'nwcs_product_fix_' . $id ); ?>
							<button type="submit" class="button button-small"><?php echo esc_html( $verbs[ $row['fix'] ] ); ?><span class="screen-reader-text"> (<?php echo esc_html( $row['site'] ); ?>)</span></button>
						</form>
					<?php elseif ( 'place' === $row['fix'] ) : ?>
						<a href="<?php echo esc_url( $row['category_url'] ); ?>">Kategoriyi yerleştir</a>
					<?php endif; ?>
					<?php if ( '' !== $row['url'] ) : ?>
						<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener">Sitede gör<span class="screen-reader-text"> (<?php echo esc_html( $row['site'] ); ?>, yeni sekmede açılır)</span> ↗</a>
					<?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
		<?php if ( $gaps ) : ?>
			<p class="nwcs-status__gaps">
				Eksik: <?php echo esc_html( implode( ' · ', array_map( static fn( string $gap ): string => $labels[ $gap ], $gaps ) ) ); ?>
				<?php if ( in_array( 'photo', $gaps, true ) && '' !== $first ) : ?>
					— <a href="<?php echo esc_url( nwcs_pool_categories_url( array( 'kategori' => $first ) ) . '#nwcs-fotograf' ); ?>">Fotoğrafları kategorinin sayfasından yükleyin</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Galeriye kitapliktan gorsel ekleme: arama kutusu. JS'li durumda sonuclar
 * yazdikca gelir ("Ekle"); JS yoksa kutu bir GET formudur (gorsel_ara), form
 * yeniden acilir ve sonuclar isaretlenebilir kutular olarak gelir
 * (gallery_add[], kayitta galerinin sonuna eklenir).
 */
function nwcs_render_media_find( ?array $product ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca arama.
	$search  = isset( $_GET['gorsel_ara'] ) ? nwcs_clean_text( wp_unslash( $_GET['gorsel_ara'] ) ) : null;
	$exclude = array_map( static fn( array $image ): int => (int) $image['id'], (array) ( $product['images'] ?? array() ) );
	$found   = null !== $search ? nwcs_media_find( $search, $exclude ) : array();
	?>
	<div class="nwcs-find" data-nwcs-media-find-box>
		<label class="nwcs-sublabel" for="nwcs-p-find">Kitaplıktan ekle</label>
		<div class="nwcs-find__bar">
			<input class="nwcs-input" type="search" id="nwcs-p-find" name="gorsel_ara" form="nwcs-find-form"
				value="<?php echo esc_attr( (string) $search ); ?>" data-nwcs-media-find autocomplete="off"
				placeholder="Kitaplıkta ara: dosya adı, başlık ya da ürün kodu" aria-describedby="nwcs-p-find-hint" />
			<button type="submit" class="button" form="nwcs-find-form" data-nwcs-media-find-go>Ara</button>
		</div>
		<p class="nwcs-hint" id="nwcs-p-find-hint">Büyük/küçük harf fark etmez. Boş bırakıp “Ara”ya basarsanız son yüklenenler gelir.</p>
		<ul class="nwcs-find__list" data-nwcs-media-find-list aria-live="polite">
			<?php foreach ( $found as $item ) : ?>
				<li class="nwcs-find__item">
					<label class="nwcs-find__pick">
						<input type="checkbox" name="gallery_add[]" value="<?php echo (int) $item['id']; ?>" />
						<?php if ( '' !== $item['thumb'] ) : ?>
							<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" loading="lazy" />
						<?php endif; ?>
						<span class="nwcs-find__name"><strong><?php echo esc_html( $item['title'] ); ?></strong> <small><?php echo esc_html( $item['name'] ); ?></small></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( null !== $search ) : ?>
			<p class="nwcs-hint"><?php echo $found ? esc_html( sprintf( '%d görsel bulundu. İşaretleyip “Kaydet”e basın; galerinin sonuna eklenir.', count( $found ) ) ) : 'Bu aramaya uyan görsel yok.'; ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Havuz bildirimleri.
 */
function nwcs_render_pool_notices(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$key   = isset( $_GET['nwcs_pool'] ) ? sanitize_key( wp_unslash( $_GET['nwcs_pool'] ) ) : '';
	$count = isset( $_GET['adet'] ) ? absint( $_GET['adet'] ) : 0;
	$site  = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$site_label = (string) ( nwcs_editable_sites()[ $site ]['label'] ?? '' );

	$map = array(
		'saved'        => array( 'success', 'Ürün kaydedildi.' ),
		'saved_missing' => array( 'warning', sprintf( 'Ürün kaydedildi, ama %d zorunlu detay boş. Bunlar dolmadan ürünün Excel satırı yüklenmez; formda “eksik” yazan satırları doldurup yeniden kaydedin.', $count ) ),
		'deleted'      => array( 'success', 'Ürün çöp kutusuna taşındı ve sitelerden kalktı. Çöp kutusundan geri getirebilirsiniz.' ),
		'restored'     => array( 'success', 'Ürün geri getirildi; daha önce göründüğü sitelerde yeniden görünüyor.' ),
		'purged'       => array( 'success', 'Ürün kalıcı olarak silindi.' ),
		'bulk_trashed' => array( 'success', sprintf( '%d ürün çöp kutusuna taşındı ve sitelerden kalktı. Çöp kutusundan geri getirebilirsiniz.', $count ) ),
		'bulk_restored' => array( 'success', sprintf( '%d ürün geri getirildi; daha önce göründükleri sitelerde yeniden görünüyor.', $count ) ),
		'bulk_purged'  => array( 'success', sprintf( '%d ürün kalıcı olarak silindi.', $count ) ),
		'trash_empty'  => array( 'error', 'Ürün seçilmedi. Listeden en az bir ürün işaretleyip “Uygula”ya basın.' ),
		'trash_off'    => array( 'error', 'Bu panelde çöp kutusu kapalı olduğu için ürün çöpe taşınamıyor; taşınsaydı kalıcı olarak silinirdi. Bunu geliştiriciye haber verin. (Teknik: EMPTY_TRASH_DAYS = 0)' ),
		'cat_added'    => array( 'success', 'Kategori eklendi.' ),
		'cat_deleted'  => array( 'success', 'Kategori silindi.' ),
		'cats_pruned'  => array( 'success', sprintf( 'Ürünü olmayan %d kategori silindi.', $count ) ),
		'bulk'         => array( 'success', sprintf( '%d ürün güncellendi.', $count ) ),
		'bulk_empty'   => array( 'error', 'Ürün ya da işlem seçilmedi. Listeden en az bir ürün işaretleyin, “Toplu işlem…” kutusundan bir işlem seçip “Uygula”ya basın.' ),
		'upload'       => array( 'error', 'Görsel yüklenemedi, kayıt yarım kaldı: ad, kod, fiyat, metinler, detaylar ve kategoriler kaydedildi; görsellerde, tablolarda, videoda ve site seçiminde yaptığınız değişiklikler kaydedilmedi (eski hâlleri duruyor). Dosyanın bir resim (JPG, PNG ya da WebP) olduğunu ve çok büyük olmadığını kontrol edip bu bölümleri yeniden kaydedin.' ),
		'video_size'   => array( 'error', sprintf( 'Kaydedilmedi: video %s sınırını aşıyor. Videoyu sıkıştırıp yeniden seçin.', size_format( nwcs_video_upload_limit() ) ) ),
		'video_type'   => array( 'error', 'Kaydedilmedi: video MP4 olmalı. Telefon videosunu MP4 olarak dışa aktarıp yeniden seçin.' ),
		'video_upload' => array( 'error', 'Kaydedilmedi: video yüklenemedi. Dosyayı yeniden seçin.' ),
		'video_failed' => array( 'error', 'Ürün kaydedildi ama video yüklenemedi; ürünün videosu değişmedi. Dosyayı yeniden seçip kaydedin.' ),
		'title'        => array( 'error', 'Kaydedilmedi: ürün adı boş. Ürün adını yazıp yeniden kaydedin.' ),
		'code_taken'   => array( 'error', 'Bu ürün kodu başka bir üründe kullanılıyor. Farklı bir kod yazın.' ),
		'code_empty'   => array( 'error', 'Kaydedilmedi: ürün kodu boş. Her ürünün kodu olmalı; fotoğraflar ürüne bu kodla bağlanır. Kodu yazıp yeniden kaydedin.' ),
		'fixed'        => array( 'success', '' !== $site_label ? sprintf( '%s görünür oldu.', nwcs_locative( $site_label ) ) : 'Ürün görünür oldu.' ),
		'untrashed'    => array( 'success', 'Ürün çöp kutusundan geri geldi; daha önce göründüğü sitelerde yeniden görünüyor.' ),
		'fix_failed'   => array( 'error', 'Düzeltilemedi: site ya da ürün bulunamadı. Sayfayı yenileyip yeniden deneyin.' ),
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

add_action( 'admin_post_nwcs_pool_save', 'nwcs_handle_pool_save' );
function nwcs_handle_pool_save(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;
	check_admin_referer( 'nwcs_pool_save_' . $id );

	$title = isset( $_POST['title'] ) ? nwcs_clean_text( wp_unslash( $_POST['title'] ) ) : '';
	$code  = isset( $_POST['code'] ) ? nwcs_normalize_product_code( wp_unslash( $_POST['code'] ) ) : '';

	if ( '' === $title ) {
		nwcs_pool_keep_draft( $id, 'title' );
	}

	// Kod zorunlu (otomatik kod verilmez): bos kodla hicbir sey yazilmaz, girilenler formda kalir.
	if ( '' === $code ) {
		nwcs_pool_keep_draft( $id, 'code_empty' );
	}

	// Video dosyasi da yazmadan once denetlenir: buyuk/MP4 olmayan dosyada urun degismez.
	$video_error = nwcs_video_upload_error();

	if ( '' !== $video_error ) {
		nwcs_pool_keep_draft( $id, $video_error );
	}

	switch_to_blog( nwcs_pool_blog_id() );

	// Kod, hicbir sey yazilmadan once denetlenir: cakisirsa yeni urun
	// olusmaz, mevcut urun degismez; girilenler formda geri gelir.
	$taken = '' !== $code ? nwcs_product_id_by_code( $code, $id ) : 0;

	if ( $taken ) {
		$taken_title = get_the_title( $taken );
		restore_current_blog();
		nwcs_pool_keep_draft( $id, 'code_taken', $taken_title );
	}

	// wp_insert_post ve update_post_meta ters egik cizgiyi siler; metin aynen kalsin.
	$postarr = array(
		'post_type'    => NWCS_PRODUCT_TYPE,
		'post_status'  => 'publish',
		'post_title'   => wp_slash( $title ),
		'post_content' => isset( $_POST['body'] ) ? wp_slash( wp_kses_post( wp_unslash( $_POST['body'] ) ) ) : '',
	);

	if ( $id ) {
		$postarr['ID'] = $id;
		wp_update_post( $postarr );
	} else {
		$id = (int) wp_insert_post( $postarr );
	}

	if ( ! $id ) {
		restore_current_blog();
		nwcs_pool_keep_draft( 0, 'title' );
	}

	// Urun kodu (zorunlu; benzersizligi yukarida denetlendi).
	update_post_meta( $id, '_nwcs_code', $code );

	update_post_meta( $id, '_nwcs_short', wp_slash( isset( $_POST['short'] ) ? nwcs_clean_text( wp_unslash( $_POST['short'] ), true ) : '' ) );
	update_post_meta( $id, '_nwcs_price', wp_slash( isset( $_POST['price'] ) ? nwcs_clean_text( wp_unslash( $_POST['price'] ) ) : '' ) );
	// Arama sonucu: tek satir; bossa urun sayfasi otomatik baslik/aciklama kullanir.
	nwcs_product_set_meta_text( $id, '_nwcs_meta_title', isset( $_POST['meta_title'] ) ? nwcs_clean_text( wp_unslash( $_POST['meta_title'] ) ) : '' );
	nwcs_product_set_meta_text( $id, '_nwcs_meta_desc', isset( $_POST['meta_desc'] ) ? nwcs_clean_text( wp_unslash( $_POST['meta_desc'] ) ) : '' );
	// Detaylar ve turetilmis spec tek yaziciyla. Serbest not ("Ölçü / not")
	// yalnizca formda gosterildiyse gelir (urunde eski bir not varsa).
	$details = nwcs_posted_details();

	foreach ( $details as $row ) {
		nwcs_heading_ensure( $row['label'] );
	}

	nwcs_product_write_details( $id, $details, isset( $_POST['spec'] ) ? nwcs_clean_text( wp_unslash( $_POST['spec'] ) ) : null );

	$have    = array_flip( array_map( static fn( array $row ): string => nwcs_heading_key( $row['label'] ), $details ) );
	$missing = count( array_diff_key( nwcs_required_headings(), $have ) );

	// Kategoriler: listeden secilenler + varsa yeni olusturulan.
	$slugs = isset( $_POST['categories'] ) && is_array( $_POST['categories'] )
		? array_map( 'sanitize_title', wp_unslash( $_POST['categories'] ) )
		: array();

	$term_ids = array();
	$before   = $id ? wp_get_object_terms( $id, NWCS_PRODUCT_TAX, array( 'fields' => 'slugs' ) ) : array();

	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, NWCS_PRODUCT_TAX );

		if ( $term ) {
			$term_ids[] = (int) $term->term_id;
		}
	}

	$new_category = isset( $_POST['new_category'] ) ? nwcs_clean_text( wp_unslash( $_POST['new_category'] ) ) : '';

	if ( '' !== $new_category ) {
		$created = wp_insert_term( $new_category, NWCS_PRODUCT_TAX );

		if ( ! is_wp_error( $created ) ) {
			$term_ids[] = (int) $created['term_id'];
		} elseif ( $created->get_error_data( 'term_exists' ) ) {
			$term_ids[] = (int) $created->get_error_data( 'term_exists' );
		}
	}

	wp_set_object_terms( $id, $term_ids, NWCS_PRODUCT_TAX, false );

	$gained = array_values( array_diff( wp_get_object_terms( $id, NWCS_PRODUCT_TAX, array( 'fields' => 'slugs' ) ), is_array( $before ) ? $before : array() ) );

	// Galeri: formdaki sira + yeni yuklenenler sona eklenir.
	$gallery = isset( $_POST['gallery'] ) && is_array( $_POST['gallery'] )
		? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['gallery'] ) ) ) )
		: array();

	// JS'siz kitaplik aramasinda isaretlenenler de sona eklenir.
	$picked = isset( $_POST['gallery_add'] ) && is_array( $_POST['gallery_add'] )
		? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['gallery_add'] ) ), static fn( int $attachment ): bool => 'attachment' === get_post_type( $attachment ) && wp_attachment_is_image( $attachment ) ) )
		: array();
	$gallery = array_merge( $gallery, $picked );

	$uploaded = nwcs_handle_gallery_upload();

	if ( null === $uploaded ) {
		restore_current_blog();
		nwcs_pool_redirect( 'upload', $id );
	}

	$gallery = array_values( array_unique( array_merge( $gallery, $uploaded ) ) );

	update_post_meta( $id, '_nwcs_gallery', $gallery );

	nwcs_save_product_tables( $id );

	$video_saved = nwcs_save_product_video( $id );

	// One cikan gorsel, galerinin ilki (eski kodla uyum icin).
	if ( $gallery ) {
		set_post_thumbnail( $id, (int) $gallery[0] );
	} else {
		delete_post_thumbnail( $id );
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	// Gorunurluk: yeni urun ya da yeni eklenen kategori, o kategorinin yerlestigi
	// sitelerin secimine girer ("secilenler" kipi; sona). Cikarilan geri eklenmez.
	if ( $gained ) {
		nwcs_placement_reveal( array( $id ), $gained );
	}

	// Siteler: isaretler degistiyse, yerlesimden sonra (Excel SİTE sutunuyla ayni yazici).
	$sites = nwcs_posted_product_sites();

	if ( null !== $sites ) {
		nwcs_sync_apply_sites( array( $id => $sites ) );
	}

	if ( ! $video_saved ) {
		nwcs_pool_redirect( 'video_failed', $id );
	}

	if ( $missing ) {
		wp_safe_redirect( nwcs_pool_url( array( 'nwcs_pool' => 'saved_missing', 'urun' => $id, 'adet' => $missing ) ) );
		exit;
	}

	nwcs_pool_redirect( 'saved', $id );
}

/**
 * Kaydedilemeyen formu kullanicinin taslagina alir ve forma geri doner.
 *
 * Kod cakismasi ya da bos ad gibi bir hatada yazilanlar kaybolmasin diye
 * ham degerler 15 dakikalik bir gecici kayda yazilir; form acilirken
 * nwcs_pool_take_draft ile geri doldurulur. Yuklenen dosyalar tasinamaz;
 * kullaniciya yeniden secmesi soylenir.
 */
function nwcs_pool_keep_draft( int $id, string $error, string $taken_by = '' ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- cagiran denetledi; degerler formda kacisla basilir.
	$field = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';

	$draft = array(
		'id'           => $id,
		'title'        => nwcs_clean_text( $field( 'title' ) ),
		'code'         => nwcs_clean_text( $field( 'code' ) ),
		'short'        => nwcs_clean_text( $field( 'short' ), true ),
		'price'        => nwcs_clean_text( $field( 'price' ) ),
		'spec'         => nwcs_clean_text( $field( 'spec' ) ),
		'details'      => nwcs_posted_details(),
		'body'         => wp_kses_post( $field( 'body' ) ),
		'meta_title'   => nwcs_clean_text( $field( 'meta_title' ) ),
		'meta_desc'    => nwcs_clean_text( $field( 'meta_desc' ) ),
		'new_category' => nwcs_clean_text( $field( 'new_category' ) ),
		'categories'   => isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'sanitize_title', array_filter( wp_unslash( $_POST['categories'] ), 'is_scalar' ) ) : array(),
		'gallery'      => isset( $_POST['gallery'] ) && is_array( $_POST['gallery'] ) ? array_values( array_filter( array_map( 'absint', array_filter( $_POST['gallery'], 'is_scalar' ) ) ) ) : array(),
		'tables'       => nwcs_sanitize_product_tables( (array) json_decode( $field( 'tables_json' ), true ) ),
		'sites'        => isset( $_POST['sites'] ) && is_array( $_POST['sites'] ) ? array_values( array_filter( array_map( 'absint', array_filter( $_POST['sites'], 'is_scalar' ) ) ) ) : array(),
		'sites_before' => array_values( array_filter( array_map( 'absint', explode( ',', $field( 'sites_before' ) ) ) ) ),
		'had_upload'   => ! empty( $_FILES['gallery_upload']['name'][0] ),
		'video'        => absint( $field( 'video_id' ) ),
		'had_video'    => ! empty( $_FILES['video_upload']['name'] ),
		'taken_by'     => $taken_by,
		'error'        => $error,
	);
	// phpcs:enable

	set_transient( 'nwcs_pool_draft_' . get_current_user_id(), $draft, 15 * MINUTE_IN_SECONDS );

	$args = array( 'nwcs_pool' => $error, 'taslak' => 1 );
	$args += $id ? array( 'urun' => $id ) : array( 'yeni' => 1 );

	wp_safe_redirect( nwcs_pool_url( $args ) );
	exit;
}

/**
 * nwcs_pool_keep_draft ile saklanan taslak; bir kez okunur. Urun formunun
 * bekledigi bicime cevrilir (gorseller ve kategoriler dahil).
 */
function nwcs_pool_take_draft( ?array $product ): ?array {
	$key   = 'nwcs_pool_draft_' . get_current_user_id();
	$draft = get_transient( $key );

	if ( ! is_array( $draft ) || (int) $draft['id'] !== (int) ( $product['id'] ?? 0 ) ) {
		return null;
	}

	delete_transient( $key );

	$categories = array();
	$pool       = nwcs_pool_categories();

	foreach ( $draft['categories'] as $slug ) {
		if ( isset( $pool[ $slug ] ) ) {
			$categories[ $slug ] = $pool[ $slug ]['name'];
		}
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$images = array();

	foreach ( $draft['gallery'] as $image_id ) {
		$src      = wp_get_attachment_image_src( $image_id, 'thumbnail' );
		$images[] = array( 'id' => $image_id, 'url' => $src ? $src[0] : '' );
	}

	$video = nwcs_video_by_id( (int) ( $draft['video'] ?? 0 ) );

	restore_current_blog();

	return array_merge(
		(array) $product,
		array(
			'id'           => (int) $draft['id'],
			'title'        => $draft['title'],
			'code'         => $draft['code'],
			'short'        => $draft['short'],
			'price'        => $draft['price'],
			'spec'         => $draft['spec'],
			'details'      => $draft['details'] ?? array(),
			'body'         => $draft['body'],
			'meta_title'   => $draft['meta_title'] ?? '',
			'meta_desc'    => $draft['meta_desc'] ?? '',
			'categories'   => $categories,
			'images'       => $images,
			'tables'       => $draft['tables'],
			'sites'        => $draft['sites'] ?? array(),
			'sites_before' => $draft['sites_before'] ?? array(),
			'new_category' => $draft['new_category'],
			'had_upload'   => $draft['had_upload'],
			'video'        => $video,
			'had_video'    => ! empty( $draft['had_video'] ),
			'taken_by'     => $draft['taken_by'],
			'error'        => (string) ( $draft['error'] ?? '' ),
		)
	);
}

/**
 * Formdaki coklu gorsel yuklemesini isler. Havuz baglaminda cagrilir.
 *
 * @return int[]|null Yuklenen ek kimlikleri; hata durumunda null.
 */
function nwcs_handle_gallery_upload(): ?array {
	$files = $_FILES['gallery_upload'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida tek tek islenir.

	if ( ! is_array( $files ) || ! isset( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return array();
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ids = array();

	foreach ( array_keys( $files['name'] ) as $index ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $files['error'][ $index ] ) {
			continue;
		}

		$_FILES['nwcs_single'] = array(
			'name'     => $files['name'][ $index ],
			'type'     => $files['type'][ $index ],
			'tmp_name' => $files['tmp_name'][ $index ],
			'error'    => $files['error'][ $index ],
			'size'     => $files['size'][ $index ],
		);

		$attachment_id = media_handle_upload( 'nwcs_single', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			unset( $_FILES['nwcs_single'] );

			return null;
		}

		$ids[] = (int) $attachment_id;
	}

	unset( $_FILES['nwcs_single'] );

	return $ids;
}

add_action( 'admin_post_nwcs_pool_delete', 'nwcs_handle_pool_delete' );
function nwcs_handle_pool_delete(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;
	check_admin_referer( 'nwcs_pool_delete_' . $id );

	// Cop kutusu kapaliysa (EMPTY_TRASH_DAYS = 0) wp_trash_post kalici siler.
	if ( ! nwcs_trash_enabled() ) {
		nwcs_pool_redirect( 'trash_off', $id );
	}

	if ( $id ) {
		// Cop kutusuna: site secimleri korunur; geri getirilince urun sitelerde
		// eski yerine doner. Kalici silme cop kutusu gorunumunden.
		switch_to_blog( nwcs_pool_blog_id() );

		if ( nwcs_sync_product_post( $id ) ) {
			wp_trash_post( $id );
		}

		restore_current_blog();
		nwcs_pool_flush_cache();
	}

	nwcs_pool_redirect( 'deleted', 0 );
}

/**
 * Cop kutusundaki urun sayisi.
 */
function nwcs_pool_trashed_count(): int {
	switch_to_blog( nwcs_pool_blog_id() );
	$count = (int) ( wp_count_posts( NWCS_PRODUCT_TYPE )->trash ?? 0 );
	restore_current_blog();

	return $count;
}

/**
 * Cop kutusu gorunumu: her urunde "Geri getir" ve "Kalıcı sil".
 */
function nwcs_render_pool_trash(): void {
	switch_to_blog( nwcs_pool_blog_id() );

	$posts = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => 'trash',
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);

	$rows = array();

	foreach ( $posts as $post ) {
		$trashed = (int) get_post_meta( $post->ID, '_wp_trash_meta_time', true );
		$rows[]  = array(
			'id'         => (int) $post->ID,
			'title'      => $post->post_title,
			'code'       => (string) get_post_meta( $post->ID, '_nwcs_code', true ),
			'categories' => wp_get_object_terms( $post->ID, NWCS_PRODUCT_TAX, array( 'fields' => 'names' ) ),
			'when'       => $trashed,
			'left'       => $trashed ? max( 0, (int) ceil( ( $trashed + EMPTY_TRASH_DAYS * DAY_IN_SECONDS - time() ) / DAY_IN_SECONDS ) ) : 0,
		);
	}

	restore_current_blog();
	?>
	<section class="nwcs-pool__card nwcs-trash" aria-labelledby="nwcs-trash-title">
		<div class="nwcs-trash__head">
			<h2 class="nwcs-pool__title" id="nwcs-trash-title">Çöp kutusu <span><?php echo count( $rows ); ?></span></h2>
			<a class="button" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün listesine dön</a>
		</div>
		<p class="nwcs-hint">
			Çöp kutusundaki ürünler hiçbir sitede görünmez. <strong>Geri getir</strong>, ürünü daha önce göründüğü sitelerde
			aynı yerine koyar. WordPress çöp kutusunu <?php echo (int) EMPTY_TRASH_DAYS; ?> gün sonra kendiliğinden boşaltır.
		</p>

		<?php if ( ! $rows ) : ?>
			<p class="nwcs-empty">Çöp kutusu boş.</p>
		<?php else : ?>
			<?php // Satirlarda tekli formlar var; kutular bu forma form="" ile baglanir (ic ice form olmaz). ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-trash-bulk" class="nwcs-bulkbar"
				onsubmit="return this.trash_action.value !== 'purge' || confirm('Seçilen ürünler kalıcı olarak silinsin mi? Bu işlem geri alınamaz.');">
				<input type="hidden" name="action" value="nwcs_pool_trash_bulk" />
				<?php wp_nonce_field( 'nwcs_pool_trash_bulk' ); ?>
				<label class="nwcs-bulkbar__all">
					<input type="checkbox" data-nwcs-check-all /> Tümünü seç
				</label>
				<select class="nwcs-input" name="trash_action">
					<option value="restore">Seçilenleri geri getir</option>
					<option value="purge">Seçilenleri kalıcı sil</option>
				</select>
				<button type="submit" class="button">Uygula</button>
			</form>

			<table class="nwcs-table">
				<thead>
					<tr>
						<th scope="col" class="nwcs-table__check"><span class="screen-reader-text">Seç</span></th>
						<th scope="col">Ürün</th>
						<th scope="col">Kategori</th>
						<th scope="col">Çöpe atıldı</th>
						<th scope="col"><span class="screen-reader-text">İşlemler</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="nwcs-table__check">
								<input type="checkbox" name="urunler[]" value="<?php echo esc_attr( (string) $row['id'] ); ?>"
									form="nwcs-trash-bulk" data-nwcs-check aria-label="<?php echo esc_attr( $row['title'] ); ?>" />
							</td>
							<td>
								<strong><?php echo esc_html( $row['title'] ); ?></strong>
								<span class="nwcs-table__spec"><?php echo esc_html( $row['code'] ); ?></span>
							</td>
							<td><?php echo esc_html( implode( ', ', $row['categories'] ) ); ?></td>
							<td>
								<?php echo esc_html( $row['when'] ? wp_date( 'd.m.Y H:i', $row['when'] ) : '—' ); ?>
								<?php if ( $row['when'] ) : ?>
									<span class="nwcs-table__spec"><?php echo (int) $row['left']; ?> gün sonra kendiliğinden silinir</span>
								<?php endif; ?>
							</td>
							<td>
								<div class="nwcs-table__actions">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="nwcs_pool_restore" />
										<input type="hidden" name="urun" value="<?php echo esc_attr( (string) $row['id'] ); ?>" />
										<?php wp_nonce_field( 'nwcs_pool_restore_' . $row['id'] ); ?>
										<button type="submit" class="button button-small">Geri getir</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
										onsubmit="return confirm('Ürün kalıcı olarak silinsin mi? Bu işlem geri alınamaz.');">
										<input type="hidden" name="action" value="nwcs_pool_purge" />
										<input type="hidden" name="urun" value="<?php echo esc_attr( (string) $row['id'] ); ?>" />
										<?php wp_nonce_field( 'nwcs_pool_purge_' . $row['id'] ); ?>
										<button type="submit" class="button button-small nwcs-row__delete">Kalıcı sil</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
	<?php
}

add_action( 'admin_post_nwcs_pool_restore', 'nwcs_handle_pool_restore' );
function nwcs_handle_pool_restore(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;
	check_admin_referer( 'nwcs_pool_restore_' . $id );

	switch_to_blog( nwcs_pool_blog_id() );

	if ( $id && 'trash' === get_post_status( $id ) && nwcs_sync_product_post( $id ) ) {
		nwcs_untrash_product( $id );
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	nwcs_pool_redirect( 'restored', $id );
}

add_action( 'admin_post_nwcs_pool_purge', 'nwcs_handle_pool_purge' );
function nwcs_handle_pool_purge(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;
	check_admin_referer( 'nwcs_pool_purge_' . $id );

	switch_to_blog( nwcs_pool_blog_id() );

	// Yalnizca cop kutusundaki urun kalici silinir.
	$purge = $id && 'trash' === get_post_status( $id ) && nwcs_sync_product_post( $id );

	if ( $purge ) {
		wp_delete_post( $id, true );
	}

	restore_current_blog();

	// Sitelerin seciminden cikarma: nwcs_forget_deleted_product (deleted_post kancasi).

	wp_safe_redirect( nwcs_pool_url( array( 'cop' => 1, 'nwcs_pool' => 'purged' ) ) );
	exit;
}

/**
 * Cop kutusunda toplu "Geri getir" / "Kalıcı sil". Yalnizca coptekiler islenir.
 */
add_action( 'admin_post_nwcs_pool_trash_bulk', 'nwcs_handle_pool_trash_bulk' );
function nwcs_handle_pool_trash_bulk(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_pool_trash_bulk' );

	$ids = isset( $_POST['urunler'] ) && is_array( $_POST['urunler'] )
		? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['urunler'] ) ) ) )
		: array();

	$action = isset( $_POST['trash_action'] ) ? sanitize_key( wp_unslash( $_POST['trash_action'] ) ) : '';

	if ( ! $ids || ! in_array( $action, array( 'restore', 'purge' ), true ) ) {
		wp_safe_redirect( nwcs_pool_url( array( 'cop' => 1, 'nwcs_pool' => 'trash_empty' ) ) );
		exit;
	}

	$done   = 0;
	$purged = array();

	// Kalici silmede site secimleri urun basina degil, sonda tek geciste temizlenir.
	remove_action( 'deleted_post', 'nwcs_forget_deleted_product', 10 );

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $ids as $id ) {
		if ( 'trash' !== get_post_status( $id ) || ! nwcs_sync_product_post( $id ) ) {
			continue;
		}

		if ( 'restore' === $action ) {
			nwcs_untrash_product( $id );
		} elseif ( wp_delete_post( $id, true ) ) {
			$purged[] = $id;
		} else {
			continue;
		}

		++$done;
	}

	restore_current_blog();

	add_action( 'deleted_post', 'nwcs_forget_deleted_product', 10, 2 );
	nwcs_forget_products( $purged );
	nwcs_pool_flush_cache();

	$args = array(
		'nwcs_pool' => 'restore' === $action ? 'bulk_restored' : 'bulk_purged',
		'adet'      => $done,
	);

	// Cop bosaldiysa urun listesine donulur.
	if ( nwcs_pool_trashed_count() ) {
		$args['cop'] = 1;
	}

	wp_safe_redirect( nwcs_pool_url( $args ) );
	exit;
}

/**
 * Havuzdan kalici silinen urun (Kalıcı sil, WordPress'in 30 gunluk cop
 * temizligi, geri almada cop kutusu kapaliysa) sitelerin seciminden de cikar.
 * Cope atma bu kancayi tetiklemez: secim korunur, geri gelince yerine doner.
 */
add_action( 'deleted_post', 'nwcs_forget_deleted_product', 10, 2 );
function nwcs_forget_deleted_product( int $post_id, $post = null ): void {
	if ( ! $post instanceof WP_Post || NWCS_PRODUCT_TYPE !== $post->post_type || get_current_blog_id() !== nwcs_pool_blog_id() ) {
		return;
	}

	nwcs_forget_product( $post_id );
}

/**
 * Silinen urunu sitelerin secim ve istisnalarindan temizler.
 */
function nwcs_forget_product( int $product_id ): void {
	nwcs_forget_products( array( $product_id ) );
}

/**
 * Birden cok urunu site basina tek geciste temizler (toplu kalici silme:
 * urun basina 10 site gecisi yerine toplam 10).
 *
 * @param int[] $product_ids
 */
function nwcs_forget_products( array $product_ids ): void {
	if ( ! $product_ids ) {
		return;
	}

	foreach ( array_keys( nwcs_editable_sites() ) as $blog_id ) {
		switch_to_blog( $blog_id );

		$settings              = nwcs_site_product_settings();
		$settings['selected']  = array_values( array_diff( $settings['selected'], $product_ids ) );
		$settings['overrides'] = array_diff_key( $settings['overrides'], array_flip( $product_ids ) );

		update_option( NWCS_OPTION_SELECTED, $settings['selected'] );
		update_option( NWCS_OPTION_OVERRIDES, $settings['overrides'] );

		restore_current_blog();
	}
}

/* ---------------- Tek tikla duzeltme (durum seridi) ---------------- */

add_action( 'admin_post_nwcs_product_fix', 'nwcs_handle_product_fix' );
function nwcs_handle_product_fix(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;
	check_admin_referer( 'nwcs_product_fix_' . $id );

	$blog_id = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
	$what    = isset( $_POST['ne'] ) ? sanitize_key( wp_unslash( $_POST['ne'] ) ) : '';

	switch_to_blog( nwcs_pool_blog_id() );
	$post = $id ? nwcs_sync_product_post( $id ) : null;
	restore_current_blog();

	if ( ! $post || ! in_array( $what, array( 'select', 'unhide', 'untrash' ), true ) ) {
		nwcs_pool_redirect( 'fix_failed', $id );
	}

	if ( 'untrash' === $what ) {
		switch_to_blog( nwcs_pool_blog_id() );

		if ( 'trash' === $post->post_status ) {
			nwcs_untrash_product( $id );
		}

		restore_current_blog();
		nwcs_pool_flush_cache();
		nwcs_pool_redirect( 'untrashed', $id );
	}

	if ( ! isset( nwcs_editable_sites()[ $blog_id ] ) ) {
		nwcs_pool_redirect( 'fix_failed', $id );
	}

	// Toplu "göster" ile ayni yazici.
	nwcs_product_show_on_site( $blog_id, array( $id ) );
	nwcs_pool_flush_cache();

	wp_safe_redirect( nwcs_pool_url( array( 'nwcs_pool' => 'fixed', 'urun' => $id, 'site' => $blog_id ) ) );
	exit;
}

/* ---------------- Toplu islemler ---------------- */

add_action( 'admin_post_nwcs_pool_bulk', 'nwcs_handle_pool_bulk' );
function nwcs_handle_pool_bulk(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_pool_bulk' );

	$ids = isset( $_POST['urunler'] ) && is_array( $_POST['urunler'] )
		? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['urunler'] ) ) ) )
		: array();

	$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';

	if ( ! $ids || '' === $action ) {
		wp_safe_redirect(
			nwcs_pool_url(
				array_filter(
					array(
						'ara'       => isset( $_POST['ara'] ) ? nwcs_clean_text( wp_unslash( $_POST['ara'] ) ) : '',
						'kategori'  => isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '',
						'eksik'     => isset( $_POST['eksik'] ) ? sanitize_key( wp_unslash( $_POST['eksik'] ) ) : '',
						'nwcs_pool' => 'bulk_empty',
					)
				)
			)
		);
		exit;
	}

	[ $verb, $target ] = array_pad( explode( ':', $action, 2 ), 2, '' );
	$pool              = nwcs_pool_products();
	$ids               = array_values( array_filter( $ids, static fn( int $id ): bool => isset( $pool[ $id ] ) ) );

	if ( 'trash' === $verb ) {
		// Tek urun silmeyle ayni: cope atilir, site secimleri korunur.
		if ( ! nwcs_trash_enabled() ) {
			nwcs_pool_redirect( 'trash_off' );
		}

		$trashed = 0;

		switch_to_blog( nwcs_pool_blog_id() );

		foreach ( $ids as $id ) {
			if ( nwcs_sync_product_post( $id ) && wp_trash_post( $id ) ) {
				++$trashed;
			}
		}

		restore_current_blog();
		nwcs_pool_flush_cache();

		// Diger toplu islemler gibi ayni suzgecle (kategori, arama) donulur.
		wp_safe_redirect(
			nwcs_pool_url(
				array_filter(
					array(
						'ara'       => isset( $_POST['ara'] ) ? nwcs_clean_text( wp_unslash( $_POST['ara'] ) ) : '',
						'kategori'  => isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '',
						'eksik'     => isset( $_POST['eksik'] ) ? sanitize_key( wp_unslash( $_POST['eksik'] ) ) : '',
						'nwcs_pool' => 'bulk_trashed',
						'adet'      => $trashed,
					)
				)
			)
		);
		exit;
	}

	if ( 'cat' === $verb ) {
		$term = get_term_by( 'slug', sanitize_title( $target ), NWCS_PRODUCT_TAX );

		if ( $term ) {
			switch_to_blog( nwcs_pool_blog_id() );

			foreach ( $ids as $id ) {
				wp_set_object_terms( $id, array( (int) $term->term_id ), NWCS_PRODUCT_TAX, true );
			}

			restore_current_blog();

			// Kategori bir siteye yerlesmisse urunler o sitede de gorunur.
			nwcs_placement_reveal( $ids, array( (string) $term->slug ) );
		}
	} elseif ( in_array( $verb, array( 'show', 'hide' ), true ) ) {
		$blog_id = absint( $target );
		$sites   = nwcs_editable_sites();

		if ( isset( $sites[ $blog_id ] ) ) {
			nwcs_product_show_on_site( $blog_id, $ids, 'show' === $verb );
		}
	}

	nwcs_pool_flush_cache();

	// Suzgec (kategori, arama) korunur: ayni listeye donulur.
	$filters = array_filter(
		array(
			'ara'      => isset( $_POST['ara'] ) ? nwcs_clean_text( wp_unslash( $_POST['ara'] ) ) : '',
			'kategori' => isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '',
			'eksik'    => isset( $_POST['eksik'] ) ? sanitize_key( wp_unslash( $_POST['eksik'] ) ) : '',
		)
	);

	wp_safe_redirect( nwcs_pool_url( $filters + array( 'nwcs_pool' => 'bulk', 'adet' => count( $ids ) ) ) );
	exit;
}

/* ---------------- Kategoriler ---------------- */

/**
 * Urunu kalmamis kategorileri toplu siler. Excel yuklemesi geri alindiginda
 * o partinin kategorileri zaten temizlenir; bu, elle birikenler icindir.
 */
add_action( 'admin_post_nwcs_pool_category_prune', 'nwcs_handle_pool_category_prune' );
function nwcs_handle_pool_category_prune(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_pool_category_prune' );

	// Yalnizca urunu olmayan, hicbir sitede yeri olmayan ve ust baslik olmayanlar:
	// yeni acilip yerlestirilmis (henuz bos) kategori silinmez.
	$prune   = nwcs_prunable_categories();
	$removed = 0;

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $prune as $term ) {
		wp_delete_term( (int) $term['id'], NWCS_PRODUCT_TAX );
		++$removed;
	}

	restore_current_blog();
	nwcs_pool_flush_cache();

	wp_safe_redirect( nwcs_pool_categories_url( array( 'sonuc' => 'budandi', 'adet' => $removed ) ) );
	exit;
}

/**
 * Havuz sayfasina bildirimli donus.
 *
 * $context: duzenleme formunu acik tutmak icin urun kimligi ya da bildirimde
 * kullanilacak adet (toplu islem, ice aktarma).
 */
function nwcs_pool_redirect( string $key, int $context = 0, bool $as_count = false ): void {
	$args = array( 'nwcs_pool' => $key );

	if ( $context ) {
		$args[ $as_count ? 'adet' : 'urun' ] = $context;
	}

	wp_safe_redirect( nwcs_pool_url( $args ) );
	exit;
}

/**
 * Tek seferlik temizlik: kaynak sitelerden kopyalanan bazi urun adlarinin
 * sonunda gorunmez satir ayirici (U+2028/U+2029) kalmis; sayfa basliginda
 * ve aramada sorun cikariyordu. Ad nwcs_clean_text'ten gecirilir, adres
 * (post_name) degismez. Bir kez calisir.
 */
add_action( 'admin_init', 'nwcs_pool_cleanup_line_separators' );
function nwcs_pool_cleanup_line_separators(): void {
	if ( get_site_option( 'nwcs_cleanup_line_separators' ) || ! current_user_can( NWCS_CAPABILITY ) ) {
		return;
	}

	update_site_option( 'nwcs_cleanup_line_separators', 1 );

	global $wpdb;

	switch_to_blog( nwcs_pool_blog_id() );

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ( post_title LIKE %s OR post_title LIKE %s )",
			NWCS_PRODUCT_TYPE,
			'%' . $wpdb->esc_like( "\u{2028}" ) . '%',
			'%' . $wpdb->esc_like( "\u{2029}" ) . '%'
		)
	);

	foreach ( $ids as $id ) {
		wp_update_post(
			array(
				'ID'         => (int) $id,
				'post_title' => wp_slash( nwcs_clean_text( get_post_field( 'post_title', (int) $id ) ) ),
			)
		);
	}

	restore_current_blog();

	if ( $ids ) {
		nwcs_pool_flush_cache();
	}
}
