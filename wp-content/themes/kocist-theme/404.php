<?php
/**
 * 404 sayfasi.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="k-pagehead">
	<div class="k-wrap">
		<p class="k-pagehead__crumb" <?php nwcs_edit_attr( 'notfound', 'head', 'crumb' ); ?>><?php echo esc_html( nwcs_field( 'notfound', 'head', 'crumb' ) ); ?></p>
		<h1 class="k-pagehead__title" <?php nwcs_edit_attr( 'notfound', 'head', 'title' ); ?>><?php echo esc_html( nwcs_field( 'notfound', 'head', 'title' ) ); ?></h1>
		<p class="k-pagehead__sub" <?php nwcs_edit_attr( 'notfound', 'head', 'text' ); ?>><?php echo esc_html( nwcs_field( 'notfound', 'head', 'text' ) ); ?></p>
	</div>
</div>

<div class="k-section">
	<div class="k-wrap">
		<a class="k-btn k-btn--dark" href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php nwcs_edit_attr( 'notfound', 'head', 'button' ); ?>><?php echo esc_html( nwcs_field( 'notfound', 'head', 'button' ) ); ?></a>
		<?php
		// Ziyaretci ve tarayici bos sayfada kalmasin: ana urun gruplarina baglanti
		// (kaldirilan/tasinan urun adreslerinden gelenler icin en yakin cikis).
		$kocist_404_groups = function_exists( 'kocist_catalog_groups' ) ? kocist_catalog_groups() : array();

		if ( $kocist_404_groups ) :
			?>
			<p style="margin:1.5rem 0 .5rem">Ürün gruplarımız:</p>
			<p>
				<?php foreach ( $kocist_404_groups as $kocist_404_group ) : ?>
					<a class="k-btn k-btn--primary" style="margin:0 .5rem .5rem 0" href="<?php echo esc_url( $kocist_404_group['url'] ); ?>"><?php echo esc_html( $kocist_404_group['name'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
