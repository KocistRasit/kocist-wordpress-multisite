<?php
/**
 * Ana sayfa hero bolumu.
 *
 * Kenardan kenara Catalca tesisinin drone videosu; slogan ve buton sol altta
 * (logo yalnizca ust menude). Altinda guven seridi. Davranis assets/js/hero.js, gorunum
 * assets/css/hero.css.
 *
 * Video temaya gomulu (assets/video/): panelde video alani yok. Telefona kare
 * kirpilmis hafif surum gider; kapak karesi video yuklenene kadar ve hareket
 * azaltma acikken gorunur.
 */

defined( 'ABSPATH' ) || exit;

$trust = nwcs_rows( 'home', 'hero', 'trust' );

/*
 * Panelde baglanti hala varsayilan katalog capasiysa kategori sayfalarina
 * gidilir (inc/catalog.php). Panelde baska adres yazilirsa o gecerlidir.
 */
$cta_url = nwcs_field( 'home', 'hero', 'cta_url' );

if ( kocist_is_catalog_placeholder( $cta_url ) && kocist_catalog_groups() ) {
	$cta_url = home_url( '/kategoriler/' );
}
?>
<section class="k-hero" id="hero" data-nwcs-section="hero">
	<div class="k-hero__stage" data-k-hero-video>
		<video
			class="k-hero__video"
			poster="<?php echo esc_url( get_theme_file_uri( 'assets/video/tesis-kapak.jpg' ) ); ?>"
			muted
			loop
			playsinline
			preload="metadata"
			aria-hidden="true"
		>
			<source src="<?php echo esc_url( get_theme_file_uri( 'assets/video/tesis-mobil.mp4' ) ); ?>" type="video/mp4" media="(max-width: 767.98px)" />
			<source src="<?php echo esc_url( get_theme_file_uri( 'assets/video/tesis.mp4' ) ); ?>" type="video/mp4" />
		</video>

		<div class="k-wrap k-hero__inner">
			<div class="k-hero__body">
				<h1 class="k-hero__title">
					<span class="k-hero__title-inner" <?php nwcs_edit_attr( 'home', 'hero', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'hero', 'title' ) ); ?></span>
				</h1>

				<?php if ( nwcs_field( 'home', 'hero', 'cta_label' ) ) : ?>
					<a class="k-hero__btn" href="<?php echo esc_url( kocist_link( $cta_url ) ); ?>" <?php nwcs_edit_attr( 'home', 'hero', 'cta_label' ); ?>>
						<?php echo esc_html( nwcs_field( 'home', 'hero', 'cta_label' ) ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php
			/*
			 * Kendiliginden oynayan, 5 saniyeden uzun hareketli icerik
			 * durdurulabilmeli (WCAG 2.2.2). JS yoksa video da oynamaz,
			 * dugme gizli kalir.
			 */
			?>
			<button type="button" class="k-hero__toggle" data-k-hero-toggle hidden>
				<svg class="k-hero__toggle-pause" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
					<rect x="3.5" y="2.5" width="3" height="11" rx="1" fill="currentColor" />
					<rect x="9.5" y="2.5" width="3" height="11" rx="1" fill="currentColor" />
				</svg>
				<svg class="k-hero__toggle-play" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
					<path d="M4.5 2.8v10.4a.8.8 0 0 0 1.2.7l8.3-5.2a.8.8 0 0 0 0-1.4L5.7 2.1a.8.8 0 0 0-1.2.7Z" fill="currentColor" />
				</svg>
				<span class="screen-reader-text" data-k-hero-toggle-label>Videoyu durdur</span>
			</button>
		</div>
	</div>

	<?php if ( $trust ) : ?>
		<div class="k-wrap">
			<ul class="k-trust">
				<?php foreach ( $trust as $trust_index => $entry ) : ?>
					<li class="k-trust__item" <?php nwcs_edit_attr( 'home', 'hero', 'trust', $trust_index, 'label' ); ?>>
						<?php nwcs_the_icon( (string) ( $entry['icon'] ?? '' ), 'k-trust__icon', 22 ); ?>
						<span><?php echo esc_html( $entry['label'] ?? '' ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
