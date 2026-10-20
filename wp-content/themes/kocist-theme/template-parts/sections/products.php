<?php
/**
 * Havuz urunleri bolumu (gorselli kartlar).
 *
 * Kategori kartlari (catalog.php) elle girilen dort grubu gosterir; bu bolum
 * ise panelden bu site icin secilen tekil urunleri.
 *
 * Kartlar merkezi urun havuzundan gelir; bu sitenin secimi ve istisnalari
 * panelden yonetilir. Burada panelde belirlenen siranin ilk N urunu gorunur
 * (N panelden); tamami kategori sayfalarinda. Fiyat bos ise "Teklif al".
 */

defined( 'ABSPATH' ) || exit;

$items     = kocist_home_products( 'featured', (int) nwcs_field( 'home', 'products', 'count' ) ?: 8 );
$cta_label = nwcs_field( 'home', 'products', 'cta_label' );

// Ziyaretci bos bir bolum gormesin; bos uyarisi yalnizca panel onizlemesinde.
if ( ! $items && ! ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
	return;
}
?>
<section class="k-section k-section--alt" id="urunler" data-nwcs-section="products">
	<div class="k-wrap">
		<div class="k-section-head">
			<h2 class="k-section-title" <?php nwcs_edit_attr( 'home', 'products', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'title' ) ); ?></h2>
			<p class="k-section-sub" <?php nwcs_edit_attr( 'home', 'products', 'subtitle' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'subtitle' ) ); ?></p>
		</div>

		<div class="k-catalog" <?php nwcs_edit_attr( 'home', 'products', 'pool' ); ?>>
			<?php foreach ( $items as $item ) : ?>
				<article class="k-cat">
					<div class="k-cat__media" <?php kocist_product_attr( $item, 'Görsel' ); ?>>
						<?php echo kocist_image_tag( kocist_product_image( $item ), '', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					</div>
					<div class="k-cat__body">
						<h3 class="k-cat__title" <?php nwcs_edit_attr( 'home', 'products', 'pool' ); ?>><?php echo esc_html( kocist_card_title( $item ) ); ?></h3>

						<?php kocist_card_colors( $item, 'k-cat__colors' ); ?>

						<span class="k-cat__price<?php echo $item['has_price'] ? '' : ' is-quote'; ?>" <?php nwcs_edit_attr( 'home', 'products', 'pool' ); ?>>
							<?php echo esc_html( $item['price_label'] ); ?>
						</span>

						<p class="k-cat__text" <?php kocist_product_attr( $item, 'Kısa açıklama' ); ?>><?php echo esc_html( $item['short'] ); ?></p>

						<?php $has_page = kocist_product_has_page( $item ); ?>
						<a class="k-cat__link" href="<?php echo esc_url( $has_page ? $item['url'] : kocist_quote_url( $item ) ); ?>" <?php nwcs_edit_attr( 'home', 'products', $has_page ? 'detail_label' : 'cta_label' ); ?>>
							<?php echo esc_html( $has_page ? nwcs_field( 'home', 'products', 'detail_label' ) : $cta_label ); ?>
							<?php nwcs_the_icon( 'arrow', 'k-icon', 16 ); ?>
						</a>
					</div>
				</article>
			<?php endforeach; ?>

			<?php if ( ! $items ) : ?>
				<p class="k-section-sub" <?php nwcs_edit_attr( 'home', 'products', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'empty_text' ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * Telefonda ilk dort kart gorunur (style.css); tamami kategori
		 * sayfalarinda.
		 */
		if ( $items && function_exists( 'kocist_catalog_groups' ) && kocist_catalog_groups() ) :
			?>
			<p class="k-section-more">
				<a class="k-btn k-btn--dark" href="<?php echo esc_url( home_url( '/kategoriler/' ) ); ?>" <?php nwcs_edit_attr( 'home', 'products', 'all_label' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'all_label' ) ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</section>
