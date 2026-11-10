<?php
/**
 * Kurumsal: Koçist Grup. Solda metin, sagda metindeki sayilarin ozeti.
 */

defined( 'ABSPATH' ) || exit;

$facts = nwcs_rows( 'inner', 'group', 'facts' );
$image = kocist_image_or_default( nwcs_image( 'inner', 'group', 'image' ), 's2-kereste.jpg', 'Koçist kereste stok sahası' );
?>
<section class="k-section" data-nwcs-section="group">
	<div class="k-wrap k-group-info">
		<div class="k-group-info__text">
			<h2 class="k-section-title" <?php nwcs_edit_attr( 'inner', 'group', 'title' ); ?>><?php echo esc_html( nwcs_field( 'inner', 'group', 'title' ) ); ?></h2>
			<div class="k-prose" <?php nwcs_edit_attr( 'inner', 'group', 'body' ); ?>>
				<?php echo kocist_prose( nwcs_field( 'inner', 'group', 'body' ) ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_prose kacisli basar. ?>
			</div>
		</div>

		<aside class="k-group-info__side">
			<div class="k-group-info__media" <?php nwcs_edit_attr( 'inner', 'group', 'image' ); ?>>
				<?php echo kocist_image_tag( $image, '', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			</div>

			<?php if ( $facts ) : ?>
				<dl class="k-facts">
					<?php foreach ( $facts as $index => $fact ) : ?>
						<div class="k-facts__row" <?php nwcs_edit_attr( 'inner', 'group', 'facts', $index, 'value' ); ?>>
							<dt class="k-facts__value"><?php echo esc_html( $fact['value'] ?? '' ); ?></dt>
							<dd class="k-facts__label"><?php echo esc_html( $fact['label'] ?? '' ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</aside>
	</div>
</section>
