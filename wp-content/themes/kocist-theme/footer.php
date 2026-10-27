<?php
/**
 * Footer ve ustundeki hareketli serit. Degerler 'global' sayfasindan okunur.
 *
 * Serit ogeleri kesintisiz akar. Teknigi: ayni liste iki kez basilir ve
 * seridin tamami -%50 otelenir; ikinci kopya birincinin bittigi yerden
 * devam ettigi icin dongu gorunmez. Kopya yalnizca gorsel oldugundan
 * ekran okuyuculardan gizleniyor.
 */

defined( 'ABSPATH' ) || exit;

$ticker    = nwcs_rows( 'global', 'footer', 'ticker' );
$social    = nwcs_rows( 'global', 'footer', 'social' );
$links     = nwcs_rows( 'global', 'footer', 'links' );
$corporate = kocist_hide_missing_sss( nwcs_rows( 'global', 'footer', 'corporate' ) );
$legal     = nwcs_rows( 'global', 'footer', 'legal' );
$logo      = kocist_image_or_default( nwcs_image( 'global', 'header', 'logo_image', 'medium' ), 'logo.png', 'Koçist Orman Ürünleri logosu' );

// Adresi girilmemis (bos ya da #instagram gibi hedefsiz capa) sosyal hesap
// ziyaretciye gosterilmez. Panel onizlemesinde kalir ki duzenlenebilsin.
// Anahtarlar korunuyor: nwcs_edit_attr satir sirasini bunlardan okuyor.
if ( ! ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) ) {
	$social = array_filter(
		$social,
		static function ( $item ): bool {
			$url = trim( (string) ( $item['url'] ?? '' ) );

			return '' !== $url && '#' !== $url && ! kocist_is_dead_anchor( $url );
		}
	);
}
?>
</main>

<?php if ( $ticker ) : ?>
	<div class="k-ticker" data-k-ticker>
		<div class="k-ticker__track">
			<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
				<ul class="k-ticker__list" <?php echo 0 === $copy ? '' : 'aria-hidden="true"'; ?>>
					<?php foreach ( $ticker as $ticker_index => $entry ) : ?>
						<?php // Iki kopya da isaretli: kayan seritte ikinci kopya da ekranda durur, tiklaninca ayni satir acilir. ?>
						<li class="k-ticker__item" <?php nwcs_edit_attr( 'global', 'footer', 'ticker', $ticker_index, 'text' ); ?>>
							<span class="k-ticker__mark" aria-hidden="true">◆</span>
							<?php echo esc_html( $entry['text'] ?? '' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endfor; ?>
		</div>
	</div>
<?php endif; ?>

<footer class="k-footer" data-nwcs-section="footer">
	<div class="k-wrap">
		<div class="k-footer__grid">

			<div class="k-footer__brand">
				<?php if ( ! empty( $logo['url'] ) ) : ?>
					<img class="k-footer__logo" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ?: get_bloginfo( 'name' ) ); ?>"<?php echo kocist_image_size_attr( $logo ); // phpcs:ignore WordPress.Security.EscapingOutput -- tamsayi. ?> <?php nwcs_edit_attr( 'global', 'header', 'logo_image' ); ?> />
				<?php endif; ?>

				<p class="k-footer__about" <?php nwcs_edit_attr( 'global', 'footer', 'about_text' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'about_text' ) ); ?></p>

				<?php if ( $social ) : ?>
					<ul class="k-footer__social">
						<?php foreach ( $social as $social_index => $item ) : ?>
							<li>
								<a class="k-footer__social-link" href="<?php echo esc_url( kocist_link( $item['url'] ?? '' ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'social', $social_index, 'url' ); ?>>
									<span class="screen-reader-text"><?php echo esc_html( $item['label'] ?? '' ); ?></span>
									<?php kocist_social_icon( (string) ( $item['icon'] ?? '' ), (string) ( $item['label'] ?? '' ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="k-footer__col">
				<h2 class="k-footer__heading" <?php nwcs_edit_attr( 'global', 'footer', 'links_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'links_title' ) ); ?></h2>
				<ul class="k-footer__list">
					<?php foreach ( $links as $index => $link ) : ?>
						<li><a href="<?php echo esc_url( kocist_link( $link['url'] ?? '' ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'links', $index, 'label' ); ?>><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="k-footer__col">
				<h2 class="k-footer__heading" <?php nwcs_edit_attr( 'global', 'footer', 'corporate_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'corporate_title' ) ); ?></h2>
				<ul class="k-footer__list">
					<?php foreach ( $corporate as $index => $link ) : ?>
						<li><a href="<?php echo esc_url( kocist_link( $link['url'] ?? '' ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'corporate', $index, 'label' ); ?>><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="k-footer__col">
				<h2 class="k-footer__heading" <?php nwcs_edit_attr( 'global', 'footer', 'contact_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'contact_title' ) ); ?></h2>
				<ul class="k-footer__list k-footer__list--contact">
					<li class="k-footer__address" <?php nwcs_edit_attr( 'global', 'footer', 'address' ); ?>>
						<?php nwcs_the_icon( nwcs_field( 'global', 'footer', 'address_icon' ), 'k-footer__icon', 17 ); ?>
						<span><?php echo esc_html( nwcs_field( 'global', 'footer', 'address' ) ); ?></span>
					</li>
					<li>
						<a class="k-footer__map" href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'footer', 'map_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'map_label' ); ?>>
							<?php echo esc_html( nwcs_field( 'global', 'footer', 'map_label' ) ); ?>
							<span aria-hidden="true">→</span>
						</a>
					</li>
					<li><a href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'footer', 'phone_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'phone_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'phone_label' ) ); ?></a></li>
					<?php if ( '' !== trim( (string) nwcs_field( 'global', 'footer', 'mobile_label' ) ) ) : ?>
						<li><a href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'footer', 'mobile_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'mobile_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'mobile_label' ) ); ?></a></li>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'footer', 'email_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'email_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'email_label' ) ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="k-footer__bottom">
			<span class="k-footer__copy" <?php nwcs_edit_attr( 'global', 'footer', 'copyright' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'copyright' ) ); ?></span>

			<?php if ( $legal ) : ?>
				<ul class="k-footer__legal">
					<?php foreach ( $legal as $index => $link ) : ?>
						<li><a href="<?php echo esc_url( kocist_link( $link['url'] ?? '' ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', 'legal', $index, 'label' ); ?>><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>

<!-- Ailways asistan balonu -->
<script
	src="https://app.ailways.com/widget-v2.js"
	data-user="fc05dd5a-4316-4977-9b07-874b5ae9caa7"
	data-agent="c41e70de-641e-4315-a597-1e2ab29803c1"
	data-position="right"
	data-color="#22c55e"
	data-api="https://app.ailways.com/api">
</script>
</body>
</html>
