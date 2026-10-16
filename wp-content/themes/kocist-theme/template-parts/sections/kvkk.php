<?php
/**
 * KVKK aydinlatma metni. Solda baslik, giris ve guncelleme notu; sagda
 * baslikli kisa bolumler. Bolumler sira bildirmedigi icin numarasiz.
 */

defined( 'ABSPATH' ) || exit;

$items   = nwcs_rows( 'kvkk', 'notice', 'items' );
$updated = trim( (string) nwcs_field( 'kvkk', 'notice', 'updated' ) );
?>
<section class="k-section" data-nwcs-section="notice">
	<div class="k-wrap k-split">
		<div class="k-split__head">
			<h2 class="k-section-title" <?php nwcs_edit_attr( 'kvkk', 'notice', 'title' ); ?>><?php echo esc_html( nwcs_field( 'kvkk', 'notice', 'title' ) ); ?></h2>
			<p class="k-split__intro" <?php nwcs_edit_attr( 'kvkk', 'notice', 'intro' ); ?>><?php echo esc_html( nwcs_field( 'kvkk', 'notice', 'intro' ) ); ?></p>
			<?php if ( '' !== $updated ) : ?>
				<p class="k-legal__updated" <?php nwcs_edit_attr( 'kvkk', 'notice', 'updated' ); ?>><?php echo esc_html( $updated ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $items ) : ?>
			<div class="k-legal">
				<?php foreach ( $items as $index => $item ) : ?>
					<div class="k-legal__item">
						<h3 class="k-legal__title" <?php nwcs_edit_attr( 'kvkk', 'notice', 'items', $index, 'title' ); ?>><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
						<div class="k-prose" <?php nwcs_edit_attr( 'kvkk', 'notice', 'items', $index, 'body' ); ?>>
							<?php echo kocist_paragraphs( $item['body'] ?? '' ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_paragraphs kacisli basar. ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
