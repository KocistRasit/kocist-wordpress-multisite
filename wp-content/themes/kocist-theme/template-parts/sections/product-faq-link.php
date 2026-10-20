<?php
/**
 * Urun sayfasi: akordeonun son satiri.
 *
 * Urunun bu sitedeki kategorisinin kendi sorulari varsa (Ürün Havuzu ->
 * Kategoriler -> Sık sorulan sorular) ilk uc soru ve kategori sayfasindaki
 * sorulara (#sss) "Tüm sorular" baglantisi; FAQ isareti yok (yalnizca kategori
 * sayfasinda). Yoksa bugunku gibi urunun grubuna ait sik sorulan sorulara
 * tek baglanti; sorularin kendisi /sss/ sayfasinda.
 *
 * Grup baglantisi, SSS'de adi urun grubuyla eslesen (sanitize_title) ve en az
 * bir cevapli sorusu olan gruba gider (/sss/#sss-kereste); oyle bir grup yoksa
 * sayfanin basina. Hic cevapli soru yoksa ya da sayfa yoksa baglanti basilmaz.
 *
 * @var array $args { group: string, category: ?array{slug:string, url:string} }
 */

defined( 'ABSPATH' ) || exit;

$faq_category = is_array( $args['category'] ?? null ) ? $args['category'] : null;
$faq_rows     = $faq_category && function_exists( 'nwcs_cat_faq_public' ) ? nwcs_cat_faq_public( (string) $faq_category['slug'], null, NWCS_CAT_FAQ_PRODUCT ) : array();

if ( $faq_rows ) :
	$faq_slug = (string) $faq_category['slug'];
	// Panel onizlemesinde cevaplar acik: tiklaninca kaynagi acilabilsin.
	$faq_open = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
	?>
	<section class="k-acc__faq k-pfaq" aria-labelledby="k-pfaq-title">
		<h2 class="k-pfaq__title" id="k-pfaq-title" <?php nwcs_edit_attr( 'product', 'detail', 'faq_title' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'faq_title' ) ); ?></h2>
		<div class="k-sss__list k-pfaq__list">
			<?php foreach ( $faq_rows as $faq_row ) : ?>
				<details class="k-sss__item"<?php echo $faq_open ? ' open' : ''; ?>>
					<summary class="k-sss__q">
						<span <?php nwcs_cat_faq_attr( $faq_slug ); ?>><?php echo esc_html( $faq_row['question'] ); ?></span>
						<span class="k-sss__icon" aria-hidden="true"></span>
					</summary>
					<div class="k-sss__a" <?php nwcs_cat_faq_attr( $faq_slug ); ?>>
						<?php echo kocist_paragraphs( $faq_row['answer'] ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_paragraphs kacisli. ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<a class="k-acc__faq-link k-pfaq__all" href="<?php echo esc_url( $faq_category['url'] . '#sss' ); ?>">
			<span <?php nwcs_edit_attr( 'product', 'detail', 'faq_all' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'faq_all' ) ); ?></span>
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
		</a>
	</section>
	<?php
	return;
endif;

$faq_page = get_page_by_path( 'sss', OBJECT, 'page' );

if ( ! $faq_page || 'publish' !== $faq_page->post_status || ! function_exists( 'nwcs_faq_groups' ) ) {
	return;
}

$faq_groups = nwcs_faq_groups( 'faq', (string) nwcs_field( 'faq', 'more', 'group_other' ) );

if ( ! $faq_groups ) {
	return;
}

$faq_slug  = sanitize_title( (string) ( $args['group'] ?? '' ) );
$faq_url   = get_permalink( $faq_page );
$faq_field = 'product_all';
$faq_label = (string) nwcs_field( 'faq', 'more', 'product_all' );

foreach ( array_keys( $faq_groups ) as $faq_name ) {
	if ( '' !== $faq_slug && sanitize_title( $faq_name ) === $faq_slug ) {
		$faq_url   .= '#sss-' . $faq_slug;
		$faq_field  = 'product_link';
		$faq_label  = kocist_text( 'faq', 'more', 'product_link', array( 'grup' => $faq_name ) );
		break;
	}
}
?>
<p class="k-acc__faq">
	<a class="k-acc__faq-link" href="<?php echo esc_url( $faq_url ); ?>">
		<span <?php nwcs_edit_attr( 'faq', 'more', $faq_field ); ?>><?php echo esc_html( $faq_label ); ?></span>
		<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
	</a>
</p>
