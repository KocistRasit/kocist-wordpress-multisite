<?php
/**
 * Kurumsal: Profilimiz. Solda metin, sagda atolye/ambalaj fotografi.
 */

defined( 'ABSPATH' ) || exit;

$image = kocist_image_or_default( nwcs_image( 'inner', 'profile', 'image' ), 's2-ambalaj.jpg', 'Koçist tesisinde istiflenmiş ahşap palet ve sandıklar' );
?>
<section class="k-section" data-nwcs-section="profile">
	<div class="k-wrap k-about">
		<div class="k-about__text">
			<h2 class="k-section-title" <?php nwcs_edit_attr( 'inner', 'profile', 'title' ); ?>><?php echo esc_html( nwcs_field( 'inner', 'profile', 'title' ) ); ?></h2>
			<div class="k-prose" <?php nwcs_edit_attr( 'inner', 'profile', 'body' ); ?>>
				<?php echo kocist_prose( nwcs_field( 'inner', 'profile', 'body' ) ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_prose kacisli basar. ?>
			</div>
		</div>

		<div class="k-about__media" <?php nwcs_edit_attr( 'inner', 'profile', 'image' ); ?>>
			<?php echo kocist_image_tag( $image, '', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
		</div>
	</div>
</section>
