<?php
/**
 * Kategori sayfalari (inc/catalog.php):
 *   /kategoriler/                    tum gruplar, her birinin alt kategorileri
 *   /kategoriler/<grup>/             grubun urunleri
 *   /kategoriler/<grup>/<kategori>/  tek kategorinin urunleri
 *
 * Grup ve kategori sayfalarinda solda grubun kategori listesi, sagda urunler.
 * Kategori sayfasinda urunlerin altinda kategorinin kendi sorulari (#sss).
 * Gorunum assets/css/category.css.
 *
 * @var array $args { group: ?array, sub: ?array }
 */

defined( 'ABSPATH' ) || exit;

$group  = $args['group'] ?? null;
$sub    = $args['sub'] ?? null;
$groups = kocist_catalog_groups();
$counts = kocist_catalog_counts();

$quote_url = kocist_link( '#teklif' );

// Panelde bu kategori ya da grup icin ayri sayfa varsa (kat-<slug>, grp-<slug>).
$page_key = $group ? kocist_catalog_page_key( $group['slug'], $sub['slug'] ?? '' ) : '';
$lead     = $page_key ? trim( (string) nwcs_field( $page_key, 'head', 'lead' ) ) : '';

get_header();

if ( ! $group ) :
	$sub_total = array_sum( array_map( static fn( array $item ): int => count( $item['subs'] ), $groups ) );
	?>
	<div class="k-pagehead">
		<div class="k-wrap">
			<?php kocist_the_trail( kocist_catalog_trail(), 'k-crumbs--on-dark' ); ?>
			<h1 class="k-pagehead__title" <?php nwcs_edit_attr( 'kategoriler', 'index', 'title' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'index', 'title' ) ); ?></h1>
			<p class="k-pagehead__sub" <?php nwcs_edit_attr( 'kategoriler', 'index', 'lead' ); ?>>
				<?php echo esc_html( kocist_text( 'kategoriler', 'index', 'lead', array( 'grup' => count( $groups ), 'kategori' => $sub_total ) ) ); ?>
			</p>
		</div>
	</div>

	<section class="k-section">
		<div class="k-wrap">
			<div class="k-shelf">
				<?php foreach ( $groups as $item ) : ?>
					<?php
					$item_count = (int) ( $counts[ $item['slug'] ][''] ?? 0 );
					$image_row  = kocist_catalog_group_image_row( $item['slug'] );
					?>
					<article class="k-shelf__row">
						<?php // Grup fotografi ana sayfadaki "Urun gruplari" kartindan gelir; tiklaninca o satir acilir. ?>
						<a class="k-shelf__media" href="<?php echo esc_url( $item['url'] ); ?>" tabindex="-1" aria-hidden="true" <?php if ( $image_row >= 0 ) { nwcs_edit_attr( 'home', 'catalog', 'items', $image_row, 'image' ); } ?>>
							<?php echo kocist_image_tag( $item['image'], 'k-shelf__img', $item['name'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
						</a>

						<div class="k-shelf__body">
							<h2 class="k-shelf__title" <?php nwcs_edit_attr( 'global', 'header', 'menu', $item['menu_row'], 'label' ); ?>>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							</h2>
							<p class="k-shelf__meta" <?php nwcs_edit_attr( 'kategoriler', 'texts', $item_count ? 'meta_subs_products' : 'meta_subs' ); ?>>
								<?php echo esc_html( kocist_text( 'kategoriler', 'texts', $item_count ? 'meta_subs_products' : 'meta_subs', array( 'kategori' => count( $item['subs'] ), 'urun' => $item_count ) ) ); ?>
							</p>

							<ul class="k-shelf__subs">
								<?php foreach ( $item['subs'] as $sub_item ) : ?>
									<li><a href="<?php echo esc_url( $sub_item['url'] ); ?>" <?php kocist_sub_edit_attr( $sub_item ); ?>><?php echo esc_html( $sub_item['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>

							<a class="k-shelf__all" href="<?php echo esc_url( $item['url'] ); ?>" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'group_all' ); ?>>
								<?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'group_all', array( 'grup' => $item['name'] ) ) ); ?>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
									<path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
else :
	$products = kocist_catalog_products_in( $group['slug'], $sub['slug'] ?? '' );
	$title    = $sub['name'] ?? $group['name'];
	?>
	<div class="k-pagehead k-pagehead--browse">
		<div class="k-wrap">
			<?php kocist_the_trail( kocist_catalog_trail( $group['slug'], $sub['slug'] ?? '' ), 'k-crumbs--on-dark' ); ?>
			<?php
			// Baslik menudeki addir; tiklaninca menudeki satir acilir.
			$title_edit = $sub ? $sub['edit'] + array( 3 => null, 4 => '' ) : array( 'global', 'header', 'menu', $group['menu_row'], 'label' );

			// Alt metin: sayfaya ozel yazildiysa o, yoksa ortak metin. Sayfanin
			// kendi alani varsa tiklama oraya gider: orada yazilan metin ortak
			// metnin yerine gecer.
			if ( '' === $lead ) {
				$lead_field = $products ? 'lead_products' : ( $sub ? 'lead_sub_empty' : 'lead_group_empty' );
				$lead       = kocist_text( 'kategoriler', 'texts', $lead_field, array( 'urun' => count( $products ), 'grup' => $group['name'], 'kategori' => count( $group['subs'] ) ) );
				$lead_edit  = $page_key ? array( $page_key, 'head', 'lead' ) : array( 'kategoriler', 'texts', $lead_field );
			} else {
				$lead_edit = array( $page_key, 'head', 'lead' );
			}
			?>
			<h1 class="k-pagehead__title" <?php nwcs_edit_attr( $title_edit[0], $title_edit[1], $title_edit[2], $title_edit[3], $title_edit[4] ); ?>><?php echo esc_html( $title ); ?></h1>
			<p class="k-pagehead__sub" <?php nwcs_edit_attr( $lead_edit[0], $lead_edit[1], $lead_edit[2] ); ?>><?php echo esc_html( $lead ); ?></p>
		</div>
	</div>

	<?php
	/*
	 * Kategori listesi solda, urunler sagda. Grubun tum kategorileri tek
	 * sutunda: 24 kategorili Hirdavat'ta bile satir satir okunur. Bos
	 * kategoriler soluk; secili olanin solunda yesil cizgi. Dar ekranda liste
	 * "Kategori: ..." basligiyla acilir kapanir (details).
	 */
	$group_total = (int) ( $counts[ $group['slug'] ][''] ?? 0 );
	$all_label   = nwcs_field( 'kategoriler', 'texts', 'all_label' );
	$current_row = $sub ? $sub['name'] : $all_label;
	?>
	<section class="k-section k-section--tight">
		<div class="k-wrap k-browse">
			<aside class="k-browse__side">
				<details class="k-cats" data-k-cats open>
					<summary class="k-cats__summary">
						<span class="k-cats__summary-label" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'category_label' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'category_label' ) ); ?></span>
						<span class="k-cats__summary-value" <?php $sub ? kocist_sub_edit_attr( $sub ) : nwcs_edit_attr( 'kategoriler', 'texts', 'all_label' ); ?>><?php echo esc_html( $current_row ); ?></span>
						<svg class="k-cats__chevron" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
					</summary>

					<nav aria-label="<?php echo esc_attr( $group['name'] . ' kategorileri' ); ?>">
						<p class="k-cats__group" <?php nwcs_edit_attr( 'global', 'header', 'menu', $group['menu_row'], 'label' ); ?>><?php echo esc_html( $group['name'] ); ?></p>
						<ul class="k-cats__list">
							<li>
								<a class="k-cats__link<?php echo $sub ? '' : ' is-current'; ?>" href="<?php echo esc_url( $group['url'] ); ?>" <?php echo $sub ? '' : 'aria-current="page"'; ?>>
									<span <?php nwcs_edit_attr( 'kategoriler', 'texts', 'all_label' ); ?>><?php echo esc_html( $all_label ); ?></span>
									<?php if ( $group_total ) : ?>
										<span class="k-cats__count"><?php echo (int) $group_total; ?></span>
									<?php endif; ?>
								</a>
							</li>
							<?php foreach ( $group['subs'] as $sub_item ) : ?>
								<?php
								$is_here   = $sub && $sub['slug'] === $sub_item['slug'];
								$sub_count = (int) ( $counts[ $group['slug'] ][ $sub_item['slug'] ] ?? 0 );
								?>
								<li>
									<a class="k-cats__link<?php echo $is_here ? ' is-current' : ''; ?><?php echo $sub_count ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $sub_item['url'] ); ?>" <?php echo $is_here ? 'aria-current="page"' : ''; ?>>
										<span <?php kocist_sub_edit_attr( $sub_item ); ?>><?php echo esc_html( $sub_item['name'] ); ?></span>
										<?php if ( $sub_count ) : ?>
											<span class="k-cats__count"><?php echo (int) $sub_count; ?></span>
										<?php endif; ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>

						<?php $others = array_filter( $groups, static fn( array $item ): bool => $item['slug'] !== $group['slug'] ); ?>
						<?php if ( $others ) : ?>
							<p class="k-cats__group k-cats__group--others" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'others_title' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'others_title' ) ); ?></p>
							<ul class="k-cats__list k-cats__list--others">
								<?php foreach ( $others as $other ) : ?>
									<li><a class="k-cats__link" href="<?php echo esc_url( $other['url'] ); ?>"><span <?php nwcs_edit_attr( 'global', 'header', 'menu', $other['menu_row'], 'label' ); ?>><?php echo esc_html( $other['name'] ); ?></span></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</nav>
				</details>
				<script>
					// Dar ekranda liste kapali baslar; genis ekranda hep acik.
					( function () {
						var box = document.querySelector( '[data-k-cats]' );
						if ( box && window.matchMedia( '(max-width: 860px)' ).matches ) {
							box.open = false;
						}
					}() );
				</script>
			</aside>

			<div class="k-browse__main">
			<?php if ( $products ) : ?>
				<ul class="k-plist" <?php if ( $page_key ) { nwcs_edit_attr( $page_key, 'products', 'pool' ); } ?>>
					<?php
					// Kart inc/catalog.php'de (arama sonuclari da ayni karti kullanir);
					// grup sayfasinda kartta urunun kategorisi yazar.
					foreach ( $products as $product ) {
						kocist_product_card( $product, ! $sub );
					}
					?>
				</ul>
			<?php else : ?>
				<div class="k-empty">
					<h2 class="k-empty__title" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'empty_title' ); ?>>
						<?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'empty_title', array( 'ad' => $title ) ) ); ?>
					</h2>
					<p class="k-empty__text" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'empty_text' ) ); ?></p>
					<div class="k-empty__actions">
						<a class="k-product__btn k-product__btn--primary" href="<?php echo esc_url( $quote_url ); ?>" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'empty_cta' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'empty_cta' ) ); ?></a>
						<?php if ( $sub ) : ?>
							<a class="k-product__btn k-product__btn--ghost" href="<?php echo esc_url( $group['url'] ); ?>" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'group_all' ); ?>><?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'group_all', array( 'grup' => $group['name'] ) ) ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * Kategorinin kendi sorulari (Ürün Havuzu -> Kategoriler -> Sık sorulan
			 * sorular), urunlerin altinda. Yalnizca sorusu ve cevabi dolu satirlar;
			 * eklenti ayni sorulari bu sayfada FAQPage olarak isaretler. Urun
			 * sayfasindaki "Tüm sorular" baglantisi #sss'ye gelir. Acma kapama
			 * <details>: betik yok (gorunum assets/css/faq.css + category.css).
			 */
			$faq_rows = $sub && function_exists( 'nwcs_cat_faq_public' ) ? nwcs_cat_faq_public( (string) $sub['slug'] ) : array();
			$faq_open = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
			?>
			<?php if ( $faq_rows ) : ?>
				<section class="k-catfaq" id="sss" aria-labelledby="sss-baslik">
					<h2 class="k-catfaq__title" id="sss-baslik" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'faq_title' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'faq_title' ) ); ?></h2>
					<div class="k-sss__list">
						<?php foreach ( $faq_rows as $faq_row ) : ?>
							<details class="k-sss__item"<?php echo $faq_open ? ' open' : ''; ?>>
								<summary class="k-sss__q">
									<span <?php nwcs_cat_faq_attr( (string) $sub['slug'] ); ?>><?php echo esc_html( $faq_row['question'] ); ?></span>
									<span class="k-sss__icon" aria-hidden="true"></span>
								</summary>
								<div class="k-sss__a" <?php nwcs_cat_faq_attr( (string) $sub['slug'] ); ?>>
									<?php echo kocist_paragraphs( $faq_row['answer'] ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_paragraphs kacisli. ?>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
endif;

get_footer();
