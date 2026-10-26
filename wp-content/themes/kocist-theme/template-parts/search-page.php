<?php
/**
 * Arama sonuclari (/arama/?ara=<terim>, inc/search.php).
 *
 * Baslik sonucu soyler ("‘ADR’ için 6 ürün"); kartlar kategori sayfasinin
 * karti (kocist_product_card). Renk grubu sadelestirilmez: her renk ayri kart.
 * Sonuc yoksa ne yapilacagi, iletisim ve WhatsApp baglantisi. Sayfa noindex,
 * follow ve site haritasinda yok. Gorunum category.css + teklif.css.
 */

defined( 'ABSPATH' ) || exit;

$term    = kocist_search_term();
$long    = '' !== $term && kocist_search_long_enough( $term );
$results = $long ? kocist_search( $term ) : array();
$total   = count( $results );
$shown   = array_slice( $results, 0, KOCIST_SEARCH_MAX );

if ( $total ) {
	$title       = kocist_text( 'arama', 'head', 'found', array( 'terim' => $term, 'sayi' => $total ) );
	$title_field = array( 'arama', 'head', 'found' );
} elseif ( $long ) {
	$title       = kocist_text( 'arama', 'results', 'empty_title', array( 'terim' => $term ) );
	$title_field = array( 'arama', 'results', 'empty_title' );
} else {
	$title       = (string) nwcs_field( 'arama', 'head', 'title' );
	$title_field = array( 'arama', 'head', 'title' );
}

$trail = array(
	array( 'name' => nwcs_field( 'global', 'texts', 'home_crumb' ), 'url' => home_url( '/' ), 'edit' => array( 'global', 'texts', 'home_crumb' ) ),
	array( 'name' => nwcs_field( 'arama', 'head', 'crumb' ), 'url' => '', 'edit' => array( 'arama', 'head', 'crumb' ) ),
);
?>
<div class="k-pagehead k-pagehead--browse k-pagehead--tool">
	<div class="k-wrap">
		<?php kocist_the_trail( $trail, 'k-crumbs--on-dark' ); ?>
		<h1 class="k-pagehead__title k-results__title" <?php nwcs_edit_attr( ...$title_field ); ?>><?php echo esc_html( $title ); ?></h1>
		<p class="k-pagehead__sub" <?php nwcs_edit_attr( 'arama', 'head', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'head', 'lead' ) ); ?></p>
	</div>
</div>

<section class="k-section k-section--tight k-results">
	<div class="k-wrap">
		<?php kocist_search_form( 'k-search-page', 'k-searchform--page', $term, 'search_region_page' ); ?>

		<?php if ( '' !== $term && ! $long ) : ?>
			<p class="k-results__note" <?php nwcs_edit_attr( 'arama', 'results', 'short_term' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'results', 'short_term' ) ); ?></p>
		<?php elseif ( $total ) : ?>
			<?php if ( $total > count( $shown ) ) : ?>
				<p class="k-results__note" <?php nwcs_edit_attr( 'arama', 'results', 'limit_note' ); ?>><?php echo esc_html( kocist_text( 'arama', 'results', 'limit_note', array( 'sayi' => count( $shown ) ) ) ); ?></p>
			<?php endif; ?>

			<ul class="k-plist k-results__list">
				<?php
				foreach ( array_values( $shown ) as $index => $product ) {
					kocist_product_card( $product, true, true, 0 === $index );
				}
				?>
			</ul>
		<?php elseif ( $long ) : ?>
			<?php
			$wa_digits = kocist_general_wa_digits();
			$wa_text   = kocist_text( 'arama', 'results', 'wa_msg', array( 'terim' => $term ) );
			$wa_href   = '' !== $wa_digits ? kocist_wa_url( $wa_digits, $wa_text ) : '';
			?>
			<div class="k-empty k-results__empty">
				<p class="k-empty__text" <?php nwcs_edit_attr( 'arama', 'results', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'results', 'empty_text' ) ); ?></p>
				<div class="k-empty__actions">
					<a class="k-product__btn k-product__btn--primary" href="<?php echo esc_url( home_url( '/iletisim/' ) ); ?>" <?php nwcs_edit_attr( 'arama', 'results', 'contact_label' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'results', 'contact_label' ) ); ?></a>
					<?php if ( '' !== $wa_href ) : ?>
						<a class="k-product__btn k-product__btn--ghost k-results__wa" href="<?php echo esc_url( $wa_href ); ?>" target="_blank" rel="noopener">
							<?php nwcs_the_icon( 'whatsapp', 'k-results__wa-icon', 18 ); ?>
							<span <?php nwcs_edit_attr( 'arama', 'results', 'wa_label' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'results', 'wa_label' ) ); ?></span>
						</a>
					<?php endif; ?>
					<a class="k-product__btn k-product__btn--ghost" href="<?php echo esc_url( home_url( '/kategoriler/' ) ); ?>" <?php nwcs_edit_attr( 'arama', 'results', 'all_label' ); ?>><?php echo esc_html( nwcs_field( 'arama', 'results', 'all_label' ) ); ?></a>
				</div>
			</div>
		<?php else : ?>
			<p class="k-results__more">
				<a class="k-shelf__all" href="<?php echo esc_url( home_url( '/kategoriler/' ) ); ?>" <?php nwcs_edit_attr( 'arama', 'results', 'all_label' ); ?>>
					<?php echo esc_html( nwcs_field( 'arama', 'results', 'all_label' ) ); ?>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</a>
			</p>
		<?php endif; ?>
	</div>
</section>
