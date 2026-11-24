<?php
/**
 * Ana sayfa giris metni: hero'nun hemen altinda, urun gruplarindan once.
 * Baslik H2; metinde bos satir paragraf, [yazi](/adres/) site ici baglanti.
 * Baslik da metin de bossa (varsayilan) hicbir sey basilmaz.
 */

defined( 'ABSPATH' ) || exit;

$intro_title = trim( (string) nwcs_field( 'home', 'intro', 'title' ) );
$intro_text  = trim( (string) nwcs_field( 'home', 'intro', 'text' ) );

if ( '' === $intro_title && '' === $intro_text ) {
	return;
}
?>
<section class="k-section k-intro" id="giris" data-nwcs-section="intro">
	<div class="k-wrap">
		<?php if ( '' !== $intro_title ) : ?>
			<h2 class="k-section-title" <?php nwcs_edit_attr( 'home', 'intro', 'title' ); ?>><?php echo esc_html( $intro_title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $intro_text ) : ?>
			<div class="k-prose" <?php nwcs_edit_attr( 'home', 'intro', 'text' ); ?>>
				<?php echo kocist_paragraphs( $intro_text, '', true ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_paragraphs kacisli. ?>
			</div>
		<?php endif; ?>
	</div>
</section>
