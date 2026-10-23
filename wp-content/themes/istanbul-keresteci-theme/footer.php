<?php
/**
 * Alt bilgi. Tum sayfalarda aynidir; icerik global.footer bileseninden,
 * urun bagantilari urun listesinden gelir.
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="ik-footer">
	<div class="ik-wrap">
		<div class="ik-footer__grid">
			<div class="ik-footer__about">
				<?php ik_logo( 'ik-logo--footer' ); ?>
				<p <?php nwcs_edit_attr( 'global', 'footer', 'about_text' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'about_text' ) ); ?></p>
			</div>

			<nav class="ik-footer__col" aria-labelledby="ik-footer-products">
				<h2 class="ik-footer__title" id="ik-footer-products" <?php nwcs_edit_attr( 'global', 'footer', 'products_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'products_title' ) ); ?></h2>
				<ul class="ik-footer__list ik-footer__list--two">
					<?php foreach ( ik_products() as $product ) : ?>
						<li><a href="<?php echo esc_url( $product['url'] ); ?>" <?php nwcs_edit_attr( 'products', 'catalog', 'items', (int) $product['index'], 'title' ); ?>><?php echo esc_html( $product['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<nav class="ik-footer__col" aria-labelledby="ik-footer-pages">
				<h2 class="ik-footer__title" id="ik-footer-pages" <?php nwcs_edit_attr( 'global', 'footer', 'pages_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'pages_title' ) ); ?></h2>
				<ul class="ik-footer__list" <?php nwcs_edit_attr( 'global', 'footer', 'links' ); ?>>
					<?php foreach ( ik_hide_missing_sss( nwcs_rows( 'global', 'footer', 'links' ) ) as $link ) : ?>
						<li><a href="<?php echo esc_url( ik_link( $link['url'] ?? '' ) ); ?>"><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="ik-footer__col">
				<h2 class="ik-footer__title" <?php nwcs_edit_attr( 'global', 'footer', 'contact_title' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'contact_title' ) ); ?></h2>
				<address class="ik-footer__contact">
					<?php // Adres Catalca depo sayfasina gider (yol tarifi ve teslim bolgeleri orada). ?>
					<p <?php nwcs_edit_attr( 'global', 'footer', 'address' ); ?>>
						<a href="<?php echo esc_url( ik_link( '/catalca-kereste-deposu/' ) ); ?>">
							<?php ik_icon( 'pin', 18 ); ?>
							<span><?php echo nl2br( esc_html( nwcs_field( 'global', 'footer', 'address' ) ) ); ?></span>
						</a>
					</p>
					<?php foreach ( array( 'phone' => 'phone', 'whatsapp' => 'whatsapp', 'email' => 'mail' ) as $channel => $icon ) : ?>
						<p>
							<a href="<?php echo esc_url( ik_link( nwcs_field( 'global', 'footer', $channel . '_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'footer', $channel . '_label' ); ?>>
								<?php ik_icon( $icon, 18 ); ?>
								<span><?php echo esc_html( nwcs_field( 'global', 'footer', $channel . '_label' ) ); ?></span>
							</a>
						</p>
					<?php endforeach; ?>
				</address>
			</div>
		</div>

		<p class="ik-footer__legal" <?php nwcs_edit_attr( 'global', 'footer', 'copyright' ); ?>><?php echo esc_html( nwcs_field( 'global', 'footer', 'copyright' ) ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
