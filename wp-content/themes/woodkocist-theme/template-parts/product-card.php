<?php
/**
 * Urun karti (canlidaki gibi): cercevesiz, dikey gorsel kirpilmadan, altinda
 * ad ve fiyat. Gorsel yoksa urun kodu buyuk bir plaka olarak gorunur (ahsaba
 * yakilmis damga gibi). Sepete ekleme ve fiyat sorma urun sayfasinda.
 *
 * $args['product']: wk_products() ogesi. $args['heading']: baslik duzeyi (h2/h3).
 * $args['edit']: panel onizlemesinde karta tiklayinca acilacak alan
 * (sayfa, bilesen, alan); orn. kategori sayfasinda o kategorinin listesi.
 * Ad ve fiyat siteye ozel degistirilebildigi icin karta (listeye) baglidir;
 * gorsel havuzdaki urunu acar (wk_product_src).
 *
 * Renk grubunda (wk_cards) kart one cikan urunun karti: adinda renk yoksa
 * sonunda rengi ("… – Antrasit") ve altinda "3 renk". Renk yuvarlaklari
 * yalnizca urun sayfasinda.
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? null;

if ( ! $product ) {
	return;
}

$heading = in_array( $args['heading'] ?? 'h3', array( 'h2', 'h3' ), true ) ? $args['heading'] : 'h3';
$image   = wk_image( $product );
$buy     = wk_can_buy( $product );
$title   = (string) ( $product['card_title'] ?? $product['title'] );
$colors  = (int) ( $product['color_count'] ?? 0 );
?>
<article class="wk-card" <?php ! empty( $args['edit'] ) && nwcs_edit_attr( ...$args['edit'] ); ?>>
	<a href="<?php echo esc_url( $product['url'] ); ?>" class="wk-card__media" tabindex="-1" aria-hidden="true" <?php wk_product_src( $product, 'Görsel' ); ?>>
		<?php if ( $image['url'] ) : ?>
			<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" loading="lazy" decoding="async" />
		<?php else : ?>
			<span class="wk-plate"><?php echo esc_html( $product['code'] ); ?></span>
		<?php endif; ?>
	</a>
	<div class="wk-card__body">
		<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapingOutput ?> class="wk-card__title">
			<a href="<?php echo esc_url( $product['url'] ); ?>"><?php echo esc_html( $title ); ?></a>
		</<?php echo $heading; // phpcs:ignore WordPress.Security.EscapingOutput ?>>
		<?php if ( $colors > 1 ) : ?>
			<p class="wk-card__colors" <?php nwcs_edit_attr( 'global', 'card', 'colors' ); ?>><?php echo esc_html( wk_text( 'global', 'card', 'colors', array( 'sayi' => $colors ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( $buy ) : ?>
			<p class="wk-card__price"><span class="wk-num"><?php echo esc_html( $product['price'] ); ?></span> <small <?php wk_price_note_attr(); ?>><?php echo esc_html( wk_price_note() ); ?></small></p>
		<?php else : ?>
			<p class="wk-card__price wk-card__price--ask" <?php nwcs_edit_attr( 'global', 'card', 'price_ask' ); ?>><?php echo esc_html( nwcs_field( 'global', 'card', 'price_ask' ) ); ?></p>
		<?php endif; ?>
	</div>
</article>
