<?php
/**
 * Urun tipi karti (ana sayfada ilk uc tipten sonrakiler): gorsel, ad, nasil
 * kullanilir, kisa aciklama, iki eylem.
 *
 * $args: product (pc_products() ogesi)
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? null;

if ( ! $product ) {
	return;
}

$wa = pc_whatsapp_product( $product );
?>
<article class="pc-icard">
	<a href="<?php echo esc_url( $product['url'] ); ?>" class="pc-icard__media" tabindex="-1" aria-hidden="true"<?php nwcs_edit_attr( $product['key'], 'card', 'image' ); ?>>
		<?php echo pc_img_tag( $product['image'], 'pc-icard__img' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
		<?php if ( $product['image']['sample'] ) : ?>
			<span class="pc-sample"><?php echo esc_html( nwcs_field( 'global', 'common', 'sample_note' ) ); ?></span>
		<?php endif; ?>
	</a>
	<div class="pc-icard__body">
		<h3 class="pc-icard__name"<?php nwcs_edit_attr( $product['key'], 'card', 'name' ); ?>><a href="<?php echo esc_url( $product['url'] ); ?>"><?php echo esc_html( $product['name'] ); ?></a></h3>
		<p class="pc-prow__tool"<?php nwcs_edit_attr( $product['key'], 'card', 'tool' ); ?>><?php echo esc_html( $product['tool'] ); ?></p>
		<p class="pc-icard__short"<?php nwcs_edit_attr( $product['key'], 'card', 'short' ); ?>><?php echo esc_html( $product['short'] ); ?></p>
		<div class="pc-icard__actions">
			<a href="<?php echo esc_url( $product['url'] ); ?>" class="pc-btn pc-btn--navy"<?php nwcs_edit_attr( 'home', 'products', 'more' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'more' ) ); ?></a>
			<?php if ( $wa ) : ?>
				<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--line"<?php nwcs_edit_attr( 'products', 'list', 'ask' ); ?>><?php echo esc_html( nwcs_field( 'products', 'list', 'ask' ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</article>
