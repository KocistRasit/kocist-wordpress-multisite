<?php
/**
 * Ana sayfa. Hero ve hemen altindaki giris metni sabit konumdadir; diger
 * bolumler kayitli siraya gore basilir, sayfanin sorulari en sonda. Giris
 * metni ve sorular bossa (varsayilan) basilmaz.
 */

defined( 'ABSPATH' ) || exit;

get_header();

kocist_section( 'hero' );
kocist_section( 'intro' );

foreach ( kocist_home_sections() as $section ) {
	kocist_section( $section );
}

$home_faq = kocist_page_faq_rows( 'home' );

if ( $home_faq ) :
	?>
	<div class="k-section k-home-faq" data-nwcs-section="faq">
		<div class="k-wrap">
			<?php
			kocist_section(
				'qa',
				array(
					'rows'       => $home_faq,
					'title'      => (string) nwcs_field( 'home', 'faq', 'title' ),
					'title_edit' => array( 'home', 'faq', 'title' ),
					'row_attr'   => static function ( array $row, string $field ): void {
						nwcs_edit_attr( 'home', 'faq', 'items', (int) $row['_row'], $field );
					},
				)
			);
			?>
		</div>
	</div>
	<?php
endif;

get_footer();
