<?php
/**
 * Katalog sayfasi govdesi: iki sekme (sertifikalar, belgeler).
 *
 * Sekmeler gercek baglantidir (?tab=belgeler); betik varsa sayfa
 * yenilenmeden degisir. Sertifika gorseline tiklayinca buyuk gorunum acilir
 * (assets/js/katalog.js); gorseli henuz secilmemis kartta "Gorsel yakinda"
 * alani gorunur. Dosya cozumu inc/katalog.php.
 */

defined( 'ABSPATH' ) || exit;

$certificates = nwcs_rows( 'katalog', 'certificates', 'items' );
$documents    = nwcs_rows( 'katalog', 'documents', 'items' );
$active       = kocist_katalog_active_tab();
$page_url     = get_permalink();

$tabs = array(
	'sertifikalar' => array(
		'label'     => nwcs_field( 'katalog', 'certificates', 'tab_label' ),
		'component' => 'certificates',
		'count'     => count( $certificates ),
	),
	'belgeler'     => array(
		'label'     => nwcs_field( 'katalog', 'documents', 'tab_label' ),
		'component' => 'documents',
		'count'     => count( $documents ),
	),
);
?>
<section class="k-section k-katalog" data-k-katalog>
	<div class="k-wrap">

		<div class="k-katalog__tabs" role="tablist" aria-label="Katalog bölümleri">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a
					class="k-katalog__tab"
					id="k-tab-<?php echo esc_attr( $key ); ?>"
					href="<?php echo esc_url( add_query_arg( 'tab', $key, $page_url ) ); ?>"
					role="tab"
					aria-controls="k-panel-<?php echo esc_attr( $key ); ?>"
					aria-selected="<?php echo $active === $key ? 'true' : 'false'; ?>"
					tabindex="<?php echo $active === $key ? '0' : '-1'; ?>"
					data-k-tab="<?php echo esc_attr( $key ); ?>"
					<?php nwcs_edit_attr( 'katalog', $tab['component'], 'tab_label' ); ?>
				>
					<?php echo esc_html( $tab['label'] ); ?>
					<span class="k-katalog__count"><?php echo (int) $tab['count']; ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<?php /* ---------- Sertifikalar ---------- */ ?>
		<div
			class="k-katalog__panel"
			id="k-panel-sertifikalar"
			role="tabpanel"
			aria-labelledby="k-tab-sertifikalar"
			data-k-panel="sertifikalar"
			data-nwcs-section="certificates"
			<?php echo 'sertifikalar' === $active ? '' : 'hidden'; ?>
		>
			<?php if ( $certificates ) : ?>
				<ul class="k-certs" <?php nwcs_edit_attr( 'katalog', 'certificates', 'items' ); ?>>
					<?php
					foreach ( $certificates as $index => $item ) :
						$title = (string) ( $item['title'] ?? '' );
						$image = nwcs_image_by_id( (int) ( $item['image'] ?? 0 ), 'large' );
						$full  = nwcs_image_by_id( (int) ( $item['image'] ?? 0 ), 'full' );
						$has   = ! empty( $image['url'] ) && ! kocist_is_placeholder_image( $image );
						?>
						<li class="k-certs__item">
							<?php if ( $has ) : ?>
								<a
									class="k-cert"
									href="<?php echo esc_url( $full['url'] ?? $image['url'] ); ?>"
									data-k-zoom
									data-k-caption="<?php echo esc_attr( $title ); ?>"
									<?php nwcs_edit_attr( 'katalog', 'certificates', 'items', $index, 'image' ); ?>
								>
									<span class="k-cert__media">
										<img class="k-cert__img" src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async" />
									</span>
									<?php if ( '' !== $title ) : ?>
										<span class="k-cert__title" <?php nwcs_edit_attr( 'katalog', 'certificates', 'items', $index, 'title' ); ?>><?php echo esc_html( $title ); ?></span>
									<?php endif; ?>
								</a>
							<?php else : ?>
								<?php // Gorsel henuz yok: buyutulecek bir sey olmadigi icin baglanti degil. ?>
								<div class="k-cert k-cert--empty" <?php nwcs_edit_attr( 'katalog', 'certificates', 'items', $index, 'image' ); ?>>
									<span class="k-cert__media">
										<?php echo kocist_placeholder( 'k-cert__ph', $title ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
									</span>
									<?php if ( '' !== $title ) : ?>
										<span class="k-cert__title" <?php nwcs_edit_attr( 'katalog', 'certificates', 'items', $index, 'title' ); ?>><?php echo esc_html( $title ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="k-katalog__empty" <?php nwcs_edit_attr( 'katalog', 'certificates', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'katalog', 'certificates', 'empty_text' ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php /* ---------- Belgeler ---------- */ ?>
		<div
			class="k-katalog__panel"
			id="k-panel-belgeler"
			role="tabpanel"
			aria-labelledby="k-tab-belgeler"
			data-k-panel="belgeler"
			data-nwcs-section="documents"
			<?php echo 'belgeler' === $active ? '' : 'hidden'; ?>
		>
			<?php if ( $documents ) : ?>
				<ul class="k-docs" <?php nwcs_edit_attr( 'katalog', 'documents', 'items' ); ?>>
					<?php
					foreach ( $documents as $index => $item ) :
						$file  = kocist_katalog_file( (string) ( $item['url'] ?? '' ) );
						$title = (string) ( $item['title'] ?? '' );

						if ( '' === $file['url'] ) {
							continue;
						}

						$ext  = '' !== $file['ext'] ? strtoupper( $file['ext'] ) : nwcs_field( 'katalog', 'documents', 'file_label' );
						$size = $file['path'] ? size_format( (int) filesize( $file['path'] ), 1 ) : '';
						?>
						<li class="k-docs__item">
							<a
								class="k-doc"
								href="<?php echo esc_url( $file['url'] ); ?>"
								target="_blank"
								rel="noopener"
								<?php nwcs_edit_attr( 'katalog', 'documents', 'items', $index, 'title' ); ?>
							>
								<span class="k-doc__icon" aria-hidden="true">
									<svg viewBox="0 0 40 48" width="40" height="48" focusable="false">
										<path d="M5 3h21l10 10v32H5z" fill="#fff" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
										<path d="M26 3v10h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
									</svg>
									<span class="k-doc__ext"><?php echo esc_html( $ext ); ?></span>
								</span>

								<span class="k-doc__body">
									<span class="k-doc__title"><?php echo esc_html( $title ); ?></span>
									<span class="k-doc__meta">
										<?php echo esc_html( $size ? $ext . ', ' . $size : $ext ); ?>
									</span>
								</span>

								<span class="k-doc__action" <?php nwcs_edit_attr( 'katalog', 'documents', 'open_label' ); ?>>
									<?php echo esc_html( nwcs_field( 'katalog', 'documents', 'open_label' ) ); ?>
									<span class="screen-reader-text">(yeni sekmede açılır)</span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="k-katalog__empty" <?php nwcs_edit_attr( 'katalog', 'documents', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'katalog', 'documents', 'empty_text' ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Buyuk gorunum. Betik yoksa gorsel baglantisi dosyayi dogrudan acar. */ ?>
	<dialog class="k-zoom" data-k-zoom-dialog aria-label="Sertifika görseli">
		<figure class="k-zoom__figure">
			<img class="k-zoom__img" src="" alt="" data-k-zoom-img />
			<figcaption class="k-zoom__caption" data-k-zoom-caption></figcaption>
		</figure>

		<button type="button" class="k-zoom__btn k-zoom__btn--close" data-k-zoom-close aria-label="Kapat">
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" /></svg>
		</button>
		<button type="button" class="k-zoom__btn k-zoom__btn--prev" data-k-zoom-prev aria-label="Önceki görsel">
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
		</button>
		<button type="button" class="k-zoom__btn k-zoom__btn--next" data-k-zoom-next aria-label="Sonraki görsel">
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
		</button>
	</dialog>
</section>
