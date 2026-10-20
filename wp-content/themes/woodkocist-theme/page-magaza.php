<?php
/**
 * Magaza (/magaza/) ve kategori sayfalari (/urun-kategori/<seri>/<kategori>/).
 * Solda kategori agaci, sagda siralama ve urun izgarasi. Arama: ?ara=.
 * Kategori sayfasinda izgaranin altinda kategorinin kendi sorulari (#sss).
 * Suzgecler duz baglanti/form: JavaScript gerekmez, adresler paylasilabilir.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum suzgeci.
$slug   = sanitize_title( (string) get_query_var( 'wk_kat' ) );
$search = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : '';
$sort   = isset( $_GET['sirala'] ) ? sanitize_key( wp_unslash( $_GET['sirala'] ) ) : '';
// phpcs:enable

$category = '' !== $slug ? wk_category( $slug ) : null; // Bilinmeyen kategori: 404 (inc/catalog.php).

$sort     = isset( wk_sort_options()[ $sort ] ) ? $sort : '';
$products = wk_filter_products( $category ? $category['slug'] : '', $search, $sort );
$base     = $category ? $category['url'] : wk_shop_url();
$title    = $category ? $category['label'] : (string) nwcs_field( 'shop', 'list', '' !== $search ? 'search_heading' : 'heading' );
$parent   = $category['parent'] ?? null;
$tree     = wk_category_tree();

// Panelde bu sayfanin karsiligi: kategori sekmesi (kat-...) ya da Magaza.
// Seri sayfasinin kendi urun listesi yok; kartlar Magaza'nin listesini acar.
$page_key = $category ? wk_category_page_key( $category['slug'] ) : 'shop';
$edit     = $page_key && 'shop' !== $page_key ? array( $page_key, 'products', 'pool' ) : array( 'shop', 'pool', 'pool' );
$head     = $page_key && 'shop' !== $page_key && '' === $search;
// Seri sayfasi (/urun-kategori/<seri>/): panelde gizli 'seri-...' sayfasi.
$serie_key = $category && ! $parent ? wk_serie_page_key( $category['slug'] ) : '';
$serie_lead = $serie_key ? trim( (string) nwcs_field( $serie_key, 'head', 'lead' ) ) : '';

/** Onizlemede baslik: kategori sayfasi, seri satiri ya da Magaza alani. */
$title_attr = static function () use ( $head, $page_key, $category, $parent, $search ): void {
	if ( $head ) {
		nwcs_edit_attr( $page_key, 'head', 'title' );
	} elseif ( $category && ! $parent && isset( $category['row'] ) ) {
		nwcs_edit_attr( 'home', 'catalog', 'lines', (int) $category['row'], 'label' );
	} elseif ( $category && $page_key ) {
		nwcs_edit_attr( $page_key, 'head', 'name' );
	} elseif ( ! $category ) {
		nwcs_edit_attr( 'shop', 'list', '' !== $search ? 'search_heading' : 'heading' );
	}
};

if ( $head ) {
	$title = (string) nwcs_field( $page_key, 'head', 'title' );
}

get_header();
?>

<section class="wk-pagehead wk-pagehead--compact">
	<div class="wk-wrap">
		<nav class="wk-crumbs wk-crumbs--light" aria-label="Konum">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php nwcs_edit_attr( 'global', 'legal', 'crumb_home' ); ?>><?php echo esc_html( nwcs_field( 'global', 'legal', 'crumb_home' ) ); ?></a></li>
				<?php if ( $category ) : ?>
					<li><a href="<?php echo esc_url( wk_shop_url() ); ?>" <?php nwcs_edit_attr( 'global', 'legal', 'crumb_shop' ); ?>><?php echo esc_html( nwcs_field( 'global', 'legal', 'crumb_shop' ) ); ?></a></li>
					<?php if ( $parent ) : ?>
						<li><a href="<?php echo esc_url( $parent['url'] ); ?>" <?php nwcs_edit_attr( 'home', 'catalog', 'lines', (int) $parent['row'], 'label' ); ?>><?php echo esc_html( $parent['label'] ); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $category && $parent && $page_key ) : ?>
					<li aria-current="page" <?php nwcs_edit_attr( $page_key, 'head', 'name' ); ?>><?php echo esc_html( $category['label'] ); ?></li>
				<?php elseif ( $category ) : ?>
					<li aria-current="page" <?php nwcs_edit_attr( 'home', 'catalog', 'lines', (int) ( $category['row'] ?? 0 ), 'label' ); ?>><?php echo esc_html( $category['label'] ); ?></li>
				<?php else : ?>
					<li aria-current="page" <?php nwcs_edit_attr( 'global', 'legal', 'crumb_shop' ); ?>><?php echo esc_html( nwcs_field( 'global', 'legal', 'crumb_shop' ) ); ?></li>
				<?php endif; ?>
			</ol>
		</nav>
		<h1 class="wk-hero__title" <?php $title_attr(); ?>><?php echo esc_html( $title ); ?></h1>
		<?php if ( $head ) : ?>
			<p class="wk-hero__lead" <?php nwcs_edit_attr( $page_key, 'head', 'lead' ); ?>><?php echo esc_html( nwcs_field( $page_key, 'head', 'lead' ) ); ?></p>
		<?php elseif ( $category && ! $parent && '' !== $serie_lead ) : ?>
			<p class="wk-hero__lead" <?php nwcs_edit_attr( $serie_key, 'head', 'lead' ); ?>><?php echo esc_html( $serie_lead ); ?></p>
		<?php elseif ( $category && ! $parent && '' !== ( $category['text'] ?? '' ) ) : ?>
			<p class="wk-hero__lead" <?php $serie_key ? nwcs_edit_attr( $serie_key, 'head', 'lead' ) : nwcs_edit_attr( 'home', 'catalog', 'lines', (int) $category['row'], 'text' ); ?>><?php echo esc_html( $category['text'] ); ?></p>
		<?php elseif ( ! $category && '' === $search ) : ?>
			<p class="wk-hero__lead" <?php nwcs_edit_attr( 'shop', 'head', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'head', 'lead' ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<div class="wk-wrap wk-shop">
	<aside class="wk-shop__side" aria-label="Kategoriler">
		<details class="wk-filter" data-filter-details open>
			<summary class="wk-filter__summary" <?php nwcs_edit_attr( 'shop', 'list', 'filter_title' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'filter_title' ) ); ?></summary>
			<ul class="wk-tree">
				<li>
					<a href="<?php echo esc_url( wk_shop_url() ); ?>" <?php echo ! $category ? 'aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'shop', 'list', 'all_label' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'all_label' ) ); ?> <span class="wk-num"><?php echo (int) wk_card_count(); ?></span></a>
				</li>
				<?php foreach ( $tree as $line ) : ?>
					<?php $open = $category && ( $category['slug'] === $line['slug'] || ( $parent && $parent['slug'] === $line['slug'] ) ); ?>
					<li class="wk-tree__line">
						<a href="<?php echo esc_url( $line['url'] ); ?>" <?php echo $category && $category['slug'] === $line['slug'] ? 'aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'home', 'catalog', 'lines', $line['row'], 'label' ); ?>><?php echo esc_html( $line['label'] ); ?> <span class="wk-num"><?php echo (int) $line['count']; ?></span></a>
						<ul>
							<?php foreach ( $line['children'] as $child ) : ?>
								<?php $child_page = wk_category_page_key( $child['slug'] ); ?>
								<li><a href="<?php echo esc_url( $child['url'] ); ?>" <?php echo $category && $category['slug'] === $child['slug'] ? 'aria-current="page"' : ''; ?> <?php $child_page && nwcs_edit_attr( $child_page, 'head', 'name' ); ?>><?php echo esc_html( $child['label'] ); ?> <span class="wk-num"><?php echo (int) $child['count']; ?></span></a></li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>

		<div class="wk-help">
			<p class="wk-help__title" <?php nwcs_edit_attr( 'shop', 'list', 'help_title' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'help_title' ) ); ?></p>
			<p <?php nwcs_edit_attr( 'shop', 'list', 'help_desc' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'help_desc' ) ); ?></p>
			<?php if ( wk_whatsapp() ) : ?>
				<a class="wk-btn wk-btn--line" href="<?php echo esc_url( wk_whatsapp() ); ?>" target="_blank" rel="noopener" <?php nwcs_edit_attr( 'shop', 'list', 'help_button' ); ?>><?php echo wk_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?> <?php echo esc_html( nwcs_field( 'shop', 'list', 'help_button' ) ); ?></a>
			<?php endif; ?>
		</div>
	</aside>

	<section class="wk-shop__main" aria-labelledby="wk-shop-count">
		<div class="wk-toolbar">
			<p id="wk-shop-count" class="wk-toolbar__count">
				<?php if ( '' !== $search ) : ?>
					<span <?php nwcs_edit_attr( 'shop', 'list', 'search_for' ); ?>><?php echo esc_html( wk_text( 'shop', 'list', 'search_for', array( 'arama' => $search ) ) ); ?></span> <strong class="wk-num" <?php nwcs_edit_attr( 'global', 'card', 'count' ); ?>><?php echo esc_html( wk_text( 'global', 'card', 'count', array( 'sayi' => count( $products ) ) ) ); ?></strong>
					<a class="wk-toolbar__clear" href="<?php echo esc_url( $base ); ?>" <?php nwcs_edit_attr( 'shop', 'list', 'clear_search' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'clear_search' ) ); ?></a>
				<?php else : ?>
					<strong class="wk-num" <?php nwcs_edit_attr( 'global', 'card', 'count' ); ?>><?php echo esc_html( wk_text( 'global', 'card', 'count', array( 'sayi' => count( $products ) ) ) ); ?></strong>
				<?php endif; ?>
			</p>
			<form class="wk-toolbar__sort" method="get" action="<?php echo esc_url( $base ); ?>">
				<?php if ( '' !== $search ) : ?>
					<input type="hidden" name="ara" value="<?php echo esc_attr( $search ); ?>" />
				<?php endif; ?>
				<label for="wk-sort" <?php nwcs_edit_attr( 'shop', 'list', 'sort_label' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'sort_label' ) ); ?></label>
				<select id="wk-sort" name="sirala" data-autosubmit <?php nwcs_edit_attr( 'shop', 'list', wk_sort_field( $sort ) ); ?>>
					<?php foreach ( wk_sort_options() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?> <?php nwcs_edit_attr( 'shop', 'list', wk_sort_field( (string) $value ) ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<noscript><button type="submit" class="wk-btn wk-btn--line" <?php nwcs_edit_attr( 'shop', 'list', 'sort_apply' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'sort_apply' ) ); ?></button></noscript>
			</form>
		</div>

		<?php if ( $category && ! $parent && $category['children'] ) : ?>
			<nav class="wk-subcats" aria-label="<?php echo esc_attr( $category['label'] ); ?> alt kategorileri">
				<?php foreach ( $category['children'] as $child ) : ?>
					<?php $child_page = wk_category_page_key( $child['slug'] ); ?>
					<a class="wk-chip" href="<?php echo esc_url( $child['url'] ); ?>" <?php $child_page && nwcs_edit_attr( $child_page, 'head', 'name' ); ?>><?php echo esc_html( $child['label'] ); ?> <span class="wk-num"><?php echo (int) $child['count']; ?></span></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $products ) : ?>
			<div class="wk-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php wk_part( 'product-card', array( 'product' => $product, 'heading' => 'h2', 'edit' => $edit ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="wk-empty">
				<p <?php nwcs_edit_attr( 'shop', 'list', 'empty_desc' ); ?>><strong <?php nwcs_edit_attr( 'shop', 'list', 'empty_title' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'empty_title' ) ); ?></strong> <?php echo esc_html( nwcs_field( 'shop', 'list', 'empty_desc' ) ); ?></p>
				<p><a class="wk-btn wk-btn--primary" href="<?php echo esc_url( wk_shop_url() ); ?>" <?php nwcs_edit_attr( 'shop', 'list', 'empty_button' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'empty_button' ) ); ?></a></p>
			</div>
		<?php endif; ?>

		<?php
		/*
		 * Kategorinin kendi sorulari (Ürün Havuzu -> Kategoriler -> Sık sorulan
		 * sorular), urunlerin altinda; yalnizca alt kategori sayfasinda. Yalnizca
		 * sorusu ve cevabi dolu satirlar; eklenti ayni sorulari bu sayfada FAQPage
		 * olarak isaretler. Urun sayfasindaki "Tüm sorular" #sss'ye gelir.
		 * Gorunum SSS sayfasiyla ayni (.wk-faq__item); acma kapama <details>.
		 */
		$faq_rows = $category && $parent && function_exists( 'nwcs_cat_faq_public' ) ? nwcs_cat_faq_public( (string) $category['slug'] ) : array();
		?>
		<?php if ( $faq_rows ) : ?>
			<section class="wk-faq wk-catfaq" id="sss" aria-labelledby="sss-baslik">
				<h2 id="sss-baslik" class="wk-faq__title" <?php nwcs_edit_attr( 'shop', 'list', 'faq_title' ); ?>><?php echo esc_html( nwcs_field( 'shop', 'list', 'faq_title' ) ); ?></h2>
				<?php foreach ( $faq_rows as $faq_row ) : ?>
					<details class="wk-faq__item"<?php echo function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ? ' open' : ''; ?>>
						<summary><span <?php nwcs_cat_faq_attr( (string) $category['slug'] ); ?>><?php echo esc_html( $faq_row['question'] ); ?></span><?php echo wk_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapingOutput ?></summary>
						<div class="wk-faq__answer" <?php nwcs_cat_faq_attr( (string) $category['slug'] ); ?>><?php echo wk_paragraphs( $faq_row['answer'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?></div>
					</details>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>
	</section>
</div>

<?php
get_footer();
