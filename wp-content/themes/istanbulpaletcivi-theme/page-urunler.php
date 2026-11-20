<?php
/**
 * Urunler (/urunler/, eski /palet-civileri/): tum urun gruplari alt alta, altta yardim kutusu.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$phone    = pc_phone();
$whatsapp = pc_whatsapp();

pc_part(
	'page-head',
	array(
		'title' => (string) nwcs_field( 'products', 'head', 'title' ),
		'lead'  => (string) nwcs_field( 'products', 'head', 'lead' ),
		'page'  => 'products',
	)
);
?>

<section class="pc-section">
	<div class="pc-wrap">
		<div class="pc-prows">
			<?php foreach ( pc_products() as $product ) : ?>
				<?php pc_part( 'product-row', array( 'product' => $product, 'heading' => 'h2' ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="pc-group">
	<div class="pc-wrap pc-group__row">
		<h2 class="pc-group__title" <?php nwcs_edit_attr( 'products', 'help', 'title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'help', 'title' ) ); ?></h2>
		<div>
			<p class="pc-group__text" <?php nwcs_edit_attr( 'products', 'help', 'text' ); ?>><?php echo esc_html( nwcs_field( 'products', 'help', 'text' ) ); ?></p>
			<div class="pc-hero__actions">
				<a href="<?php echo esc_url( $phone['url'] ); ?>" class="pc-btn pc-btn--yellow"<?php nwcs_edit_attr( 'global', 'header', 'phone_label' ); ?>><?php echo pc_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapingOutput ?><span class="pc-num"><?php echo esc_html( $phone['label'] ); ?></span></a>
				<?php if ( $whatsapp ) : ?>
					<a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--ghost"<?php nwcs_edit_attr( 'products', 'help', 'wa_label' ); ?>><?php echo pc_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?><?php echo esc_html( nwcs_field( 'products', 'help', 'wa_label' ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
