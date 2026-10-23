<?php
/**
 * Catalca kereste deposu (/catalca-kereste-deposu/): manifestteki 'depo'
 * sayfasindan. Baslik, depo metni, depoda yapilanlar, teslim bolgeleri, depoya
 * gelis (yol tarifi), siparis sureci ve iletisim seridi.
 */

defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/page-head', null, array( 'page' => 'depo' ) );

$items = nwcs_rows( 'depo', 'values', 'items' );
$areas = array_filter( array_map( static fn( array $row ): string => trim( (string) ( $row['name'] ?? '' ) ), nwcs_rows( 'depo', 'areas', 'items' ) ) );
// Isletmenin Google Haritalar kaydi (page-iletisim.php ile ayni).
$map_link = 'https://maps.app.goo.gl/goTd8wPiXnbDb8BY9';
?>
<section class="ik-section ik-story" aria-labelledby="ik-depo-title">
	<div class="ik-wrap ik-story__grid">
		<h2 class="ik-title" id="ik-depo-title" <?php nwcs_edit_attr( 'depo', 'story', 'title' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'story', 'title' ) ); ?></h2>
		<div class="ik-prose" <?php nwcs_edit_attr( 'depo', 'story', 'text' ); ?>>
			<?php ik_paragraphs( nwcs_field( 'depo', 'story', 'text' ) ); ?>
		</div>
	</div>
</section>

<?php if ( $items ) : ?>
	<section class="ik-section ik-values" aria-labelledby="ik-depo-values">
		<div class="ik-wrap">
			<h2 class="ik-title" id="ik-depo-values" <?php nwcs_edit_attr( 'depo', 'values', 'title' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'values', 'title' ) ); ?></h2>
			<ul class="ik-values__list" <?php nwcs_edit_attr( 'depo', 'values', 'items' ); ?>>
				<?php foreach ( $items as $item ) : ?>
					<li class="ik-values__item">
						<span class="ik-values__icon"><?php ik_icon( (string) ( $item['icon'] ?? '' ), 28 ); ?></span>
						<h3 class="ik-values__title"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
						<p><?php echo esc_html( $item['text'] ?? '' ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<section class="ik-section ik-depo" aria-labelledby="ik-depo-areas">
	<div class="ik-wrap ik-story__grid">
		<div>
			<h2 class="ik-title" id="ik-depo-areas" <?php nwcs_edit_attr( 'depo', 'areas', 'title' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'areas', 'title' ) ); ?></h2>
			<div class="ik-prose ik-prose--page">
				<p <?php nwcs_edit_attr( 'depo', 'areas', 'text' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'areas', 'text' ) ); ?></p>
				<?php if ( $areas ) : ?>
					<ul class="ik-depo__areas" <?php nwcs_edit_attr( 'depo', 'areas', 'items' ); ?>>
						<?php foreach ( $areas as $area ) : ?>
							<li><?php echo esc_html( $area ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p <?php nwcs_edit_attr( 'depo', 'areas', 'note' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'areas', 'note' ) ); ?></p>
			</div>
		</div>
		<div>
			<h2 class="ik-title" <?php nwcs_edit_attr( 'depo', 'visit', 'title' ); ?>><?php echo esc_html( nwcs_field( 'depo', 'visit', 'title' ) ); ?></h2>
			<div class="ik-prose ik-prose--page" <?php nwcs_edit_attr( 'depo', 'visit', 'text' ); ?>>
				<?php ik_paragraphs( nwcs_field( 'depo', 'visit', 'text' ) ); ?>
			</div>
			<p class="ik-depo__actions">
				<a class="btn btn--solid" href="<?php echo esc_url( $map_link ); ?>" target="_blank" rel="noopener" <?php nwcs_edit_attr( 'depo', 'visit', 'directions_label' ); ?>>
					<?php ik_icon( 'pin', 18 ); ?>
					<?php echo esc_html( nwcs_field( 'depo', 'visit', 'directions_label' ) ); ?>
				</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( ik_link( (string) nwcs_field( 'global', 'header', 'phone_url' ) ) ); ?>">
					<?php ik_icon( 'phone', 18 ); ?>
					<?php echo esc_html( (string) nwcs_field( 'global', 'header', 'phone_label' ) ); ?>
				</a>
			</p>
		</div>
	</div>
</section>

<?php
ik_section( 'process' );
ik_section( 'ctaband' );

get_footer();
