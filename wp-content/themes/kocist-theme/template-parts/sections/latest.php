<?php
/**
 * Son eklenen urunler: tek satirlik, saga dogru kayan serit.
 *
 * Baslik konteynerle hizali; kartlar ayni sol cizgiden baslayip ekranin sag
 * kenarindan tasiyor, yarim gorunen son kart devami oldugunu belli ediyor.
 * Oklar, ilerleme cizgisi ve fareyle surukleme assets/js/latest.js'te.
 *
 * Kartlar merkezi Urun Havuzu'ndan gelir: bu siteye secilmis urunlerden
 * havuza en son eklenenler. Kac kart gorunecegi panelden secilir.
 *
 * Kart alti kategori kartlariyla ayni kurali izler: fiyati olan urunde fiyat
 * (price_label), fiyatsiz urunde panelden gelen "Teklif alin" baglanti metni.
 */

defined( 'ABSPATH' ) || exit;

$items      = kocist_home_products( 'latest', (int) nwcs_field( 'home', 'latest', 'count' ) ?: 8 );
$link_label = nwcs_field( 'home', 'latest', 'link_label' );
$groups     = kocist_catalog_groups();

if ( ! $items ) {
	return;
}
?>
<section class="k-section k-section--alt k-latest" id="yeni-urunler" data-nwcs-section="latest" aria-labelledby="k-latest-title">
	<div class="k-wrap k-latest__head">
		<div>
			<h2 class="k-section-title" id="k-latest-title" <?php nwcs_edit_attr( 'home', 'latest', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'latest', 'title' ) ); ?></h2>
			<p class="k-section-sub" <?php nwcs_edit_attr( 'home', 'latest', 'subtitle' ); ?>><?php echo esc_html( nwcs_field( 'home', 'latest', 'subtitle' ) ); ?></p>
		</div>

		<div class="k-latest__nav" data-k-latest-nav hidden>
			<button type="button" class="k-latest__arrow" data-k-latest-prev aria-label="Önceki ürünler">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<button type="button" class="k-latest__arrow" data-k-latest-next aria-label="Sonraki ürünler">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
		</div>
	</div>

	<ul class="k-latest__track" data-k-latest-track tabindex="0" aria-label="<?php echo esc_attr( nwcs_field( 'home', 'latest', 'title' ) ); ?>" <?php nwcs_edit_attr( 'home', 'latest', 'count' ); ?>>
		<?php
		foreach ( $items as $item ) :
			$has_page = kocist_product_has_page( $item );
			$kind_sub = $groups[ $item['group'] ]['subs'][ $item['sub'] ] ?? null;
			$kind     = $kind_sub['name'] ?? ( $groups[ $item['group'] ]['name'] ?? '' );
			$spec     = kocist_product_first_spec( $item );
			?>
			<li class="k-latest__item">
				<a class="k-latest__card" href="<?php echo esc_url( $has_page ? $item['url'] : kocist_quote_url( $item ) ); ?>" draggable="false">
					<span class="k-latest__media" <?php kocist_product_attr( $item, 'Görsel' ); ?>>
						<?php echo kocist_image_tag( kocist_product_image( $item ), 'k-latest__img', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					</span>

					<span class="k-latest__body">
						<?php if ( '' !== $kind ) : ?>
							<span class="k-latest__cat" <?php $kind_sub ? kocist_sub_edit_attr( $kind_sub ) : nwcs_edit_attr( 'global', 'header', 'menu', $groups[ $item['group'] ]['menu_row'], 'label' ); ?>><?php echo esc_html( $kind ); ?></span>
						<?php endif; ?>

						<span class="k-latest__title" <?php nwcs_edit_attr( 'home', 'products', 'pool' ); ?>><?php echo esc_html( kocist_card_title( $item ) ); ?></span>

						<?php kocist_card_colors( $item, 'k-latest__colors' ); ?>

						<?php if ( '' !== $spec ) : ?>
							<span class="k-latest__spec" <?php kocist_product_attr( $item, 'Özellikler' ); ?>><?php echo esc_html( $spec ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $item['has_price'] ) ) : ?>
							<span class="k-latest__price" <?php kocist_product_attr( $item, 'Fiyat' ); ?>><?php echo esc_html( $item['price_label'] ); ?></span>
						<?php elseif ( $link_label ) : ?>
							<span class="k-latest__cta" <?php nwcs_edit_attr( 'home', 'latest', 'link_label' ); ?>><?php echo esc_html( $link_label ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="k-wrap">
		<div class="k-latest__progress" aria-hidden="true"><span data-k-latest-bar></span></div>
	</div>
</section>
