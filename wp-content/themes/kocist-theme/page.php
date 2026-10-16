<?php
/**
 * Sayfa sablonu.
 *
 * Temanin actigi sayfalar (kurumsal, insan-kaynaklari, katalog, banka-bilgilerimiz,
 * iletisim, urun, sss, kvkk)
 * manifestteki bilesenlerden cizilir; diger sayfalar normal icerikle.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_page( 'kurumsal' ) ) {
	kocist_section( 'page-head', array( 'page' => 'inner' ) );
	kocist_section( 'about-profile' );
	kocist_section( 'about-aim' );
	kocist_section( 'about-quality' );
	kocist_section( 'about-group' );
} elseif ( is_page( 'insan-kaynaklari' ) ) {
	kocist_section( 'page-head', array( 'page' => 'hr' ) );
	kocist_section( 'hr-policy' );
	kocist_section( 'hr-hiring' );
	kocist_section( 'hr-performance' );
	kocist_section( 'hr-apply' );
} elseif ( is_page( 'blog' ) ) {
	// Okuma ayarinda yazilar sayfasi baska secilmisse /blog/ yine liste gostersin.
	get_template_part(
		'template-parts/blog-list',
		null,
		array(
			'query' => new WP_Query(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => -1,
					'ignore_sticky_posts' => true,
				)
			),
		)
	);
} elseif ( is_page( 'katalog' ) ) {
	// Belgeler ve sertifikalar: manifestteki 'katalog' bilesenlerinden.
	kocist_section( 'page-head', array( 'page' => 'katalog' ) );
	kocist_section( 'katalog' );
} elseif ( is_page( 'banka-bilgilerimiz' ) ) {
	// Havale ve EFT hesaplari: manifestteki 'banka' bilesenlerinden.
	kocist_section( 'page-head', array( 'page' => 'banka' ) );
	kocist_section( 'banka' );
} elseif ( is_page( 'sss' ) ) {
	// Sik sorulan sorular: manifestteki 'faq' bilesenlerinden.
	kocist_section( 'page-head', array( 'page' => 'faq' ) );
	kocist_section( 'faq' );
} elseif ( is_page( 'kvkk' ) ) {
	// KVKK aydinlatma metni: manifestteki 'kvkk' bilesenlerinden.
	kocist_section( 'page-head', array( 'page' => 'kvkk' ) );
	kocist_section( 'kvkk' );
} elseif ( is_page( 'iletisim' ) ) {
	// Iletisim sayfasi: manifestteki 'contact' bilesenlerinden cizilir.
	kocist_section( 'contact-main' );
} elseif ( is_page( 'urun' ) ) {
	// Ornek urun sayfasi: manifestteki 'product' bilesenlerinden cizilir.
	kocist_section( 'product-main' );
	kocist_section( 'product-specs' );
} else {
	?>
	<div class="k-pagehead">
		<div class="k-wrap">
			<h1 class="k-pagehead__title"><?php the_title(); ?></h1>
		</div>
	</div>
	<div class="k-section">
		<div class="k-wrap">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</div>
	<?php
}

get_footer();
