<?php
/**
 * Urun sayfasi (/civiler/<urun>/). pc_product_template() bu dosyayi secer.
 *
 * Solda anlatim ve "kullanildigi isler"; sagda yapiskan siparis kutusu
 * (olcu ve fiyat icin arayin: gercek olcu listesi gelene kadar tablo yok).
 */

defined( 'ABSPATH' ) || exit;

get_header();

$key      = pc_current_product_key();
$products = pc_products();
$product  = $products[ $key ] ?? null;

if ( ! $product ) {
	get_footer();
	return;
}

$hub      = pc_manifest()['pages']['products'] ?? array();
$phone    = pc_phone();
$wa       = pc_whatsapp_product( $product );
$uses     = nwcs_rows( $key, 'detail', 'uses' );
$contact  = pc_link( pc_manifest()['pages']['contact']['path'] ?? '/iletisim/' );
$quote    = add_query_arg( 'urun', rawurlencode( $product['name'] ), $contact ) . '#siparis';
$others   = array_diff_key( $products, array( $key => true ) );

// Urun sayfasindaki buyuk gorsel genis kolonu 2x ekranda da net doldursun:
// 1536 boy (yoksa asil dosya). Kartlar 'large' ile kalir.
$hero_img = pc_product_type_image( $key, $product['name'], '1536x1536' );

// Gorsel yoksa buyutme yok. Eksik dosyanin yerine sayfada "Gorsel gelecek" basilirsa
// (eklentinin yer tutucusu) buyutmeyi assets/site.js ve site.css atlar.
$has_photo = '' !== ( $hero_img['url'] ?? '' );

pc_part(
	'page-head',
	array(
		'title'  => $product['name'],
		'lead'   => (string) nwcs_field( $key, 'detail', 'lead' ),
		'crumbs' => array( array( (string) nwcs_field( 'products', 'head', 'title' ), pc_link( $hub['path'] ?? '/' ), array( 'products', 'head', 'title' ) ) ),
		'title_edit' => array( $key, 'card', 'name' ),
		'lead_edit'  => array( $key, 'detail', 'lead' ),
		// Urun adi ve alt metin bilgi sutununda: gorsel ve siparis dugmeleri sayfanin basinda.
		'compact'    => true,
	)
);
?>

<section class="pc-section">
	<?php
	// Genis ekranda solda cesit bilgisi ve siparis kutusu (yapisik), sagda buyuk,
	// cercevesiz gorsel; altta aciklama. Telefonda gorsel ustte. Fareyle uzerine
	// gelince imlecin oldugu yer buyur, tiklayinca tam ekran (assets/site.js).
	?>
	<div class="pc-wrap pc-product">
		<figure class="pc-product__figure" <?php nwcs_edit_attr( $key, 'card', 'image' ); ?>>
			<?php if ( $has_photo ) : ?>
				<button type="button" class="pc-product__stage" data-pc-stage data-full="<?php echo esc_url( $hero_img['url'] ); ?>"
					aria-label="<?php echo esc_attr( $product['name'] ); ?> görselini tam ekranda aç">
					<?php echo pc_img_tag( $hero_img, 'pc-product__img', true ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				</button>
			<?php else : ?>
				<?php echo pc_img_tag( $hero_img, 'pc-product__img', true ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			<?php endif; ?>
			<?php if ( $hero_img['sample'] ) : ?>
				<span class="pc-sample"><?php echo esc_html( nwcs_field( 'global', 'common', 'sample_note' ) ); ?></span>
			<?php endif; ?>
		</figure>

		<div class="pc-product__side">
			<h1 class="pc-product__title" <?php nwcs_edit_attr( $key, 'card', 'name' ); ?>><?php echo esc_html( $product['name'] ); ?></h1>
			<?php if ( '' !== (string) nwcs_field( $key, 'detail', 'lead' ) ) : ?>
				<p class="pc-product__lead" <?php nwcs_edit_attr( $key, 'detail', 'lead' ); ?>><?php echo esc_html( nwcs_field( $key, 'detail', 'lead' ) ); ?></p>
			<?php endif; ?>
			<p class="pc-product__tool" <?php nwcs_edit_attr( $key, 'card', 'tool' ); ?>><?php echo esc_html( $product['tool'] ); ?></p>

			<aside class="pc-order-card" aria-label="Sipariş">
				<h2 class="pc-order-card__title"<?php nwcs_edit_attr( 'products', 'shared', 'order_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'order_title' ) ); ?></h2>
				<p <?php nwcs_edit_attr( 'products', 'shared', 'price_note' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'price_note' ) ); ?></p>
				<a href="<?php echo esc_url( $phone['url'] ); ?>" class="pc-btn pc-btn--navy pc-btn--block"<?php nwcs_edit_attr( 'global', 'header', 'phone_label' ); ?>>
					<?php echo pc_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					<span class="pc-num"><?php echo esc_html( $phone['label'] ); ?></span>
				</a>
				<?php if ( $wa ) : ?>
					<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--wa pc-btn--block"<?php nwcs_edit_attr( 'products', 'shared', 'wa_label' ); ?>>
						<?php echo pc_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
						<?php echo esc_html( nwcs_field( 'products', 'shared', 'wa_label' ) ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $quote ); ?>" class="pc-link"<?php nwcs_edit_attr( 'products', 'shared', 'form_label' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'form_label' ) ); ?></a>
			</aside>
		</div>

		<?php if ( $has_photo ) : ?>
			<dialog class="pc-lb" data-pc-lb aria-label="<?php echo esc_attr( $product['name'] ); ?>">
				<div class="pc-lb__stage" data-pc-lb-stage>
					<img class="pc-lb__img" data-pc-lb-img src="" alt="<?php echo esc_attr( $hero_img['alt'] ?? $product['name'] ); ?>" />
				</div>
				<p class="pc-lb__hint">Yakınlaştırmak için görsele tıklayın; yakınken fareyle gezinin.</p>
				<button type="button" class="pc-lb__close" data-pc-lb-close aria-label="Kapat"><span aria-hidden="true">×</span></button>
			</dialog>
		<?php endif; ?>
	</div>

	<?php // Aciklama ust bolumun altinda, kendi alaninda: solda metin, sagda kullanildigi isler. ?>
	<div class="pc-wrap pc-pdetail">
		<div class="pc-product__main">
			<div class="pc-prose" <?php nwcs_edit_attr( $key, 'detail', 'body' ); ?>>
				<?php echo pc_paragraphs( (string) nwcs_field( $key, 'detail', 'body' ) ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			</div>

			<?php if ( $uses ) : ?>
				<h2 class="pc-product__subtitle"<?php nwcs_edit_attr( 'products', 'shared', 'uses_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'uses_title' ) ); ?></h2>
				<ul class="pc-ticks" <?php nwcs_edit_attr( $key, 'detail', 'uses' ); ?>>
					<?php foreach ( $uses as $index => $use ) : ?>
						<li<?php nwcs_edit_attr( $key, 'detail', 'uses', (int) $index, 'text' ); ?>><?php echo esc_html( $use['text'] ?? '' ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php $items = pc_catalog_group( pc_product_group_slug( $key ) ); ?>
<?php if ( $items ) : ?>
	<section class="pc-section pc-section--items" aria-labelledby="pc-items">
		<div class="pc-wrap">
			<header class="pc-head">
				<h2 id="pc-items" <?php nwcs_edit_attr( 'products', 'shared', 'items_title' ); ?>><?php echo esc_html( strtr( (string) nwcs_field( 'products', 'shared', 'items_title' ), array( '{tip}' => $product['name'] ) ) ); ?></h2>
				<p <?php nwcs_edit_attr( 'products', 'shared', 'items_lead' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'items_lead' ) ); ?></p>
			</header>
			<div class="pc-igrid">
				<?php foreach ( $items as $item ) : ?>
					<?php pc_part( 'item-card', array( 'item' => $item ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php $faq = array_values( array_filter( nwcs_rows( $key, 'faq', 'items' ), static fn( $r ): bool => '' !== trim( (string) ( $r['question'] ?? '' ) ) && '' !== trim( (string) ( $r['answer'] ?? '' ) ) ) ); ?>
<?php if ( $faq ) : ?>
	<section class="pc-section pc-section--faq" aria-labelledby="pc-faq">
		<div class="pc-wrap pc-faqwrap">
			<h2 id="pc-faq" <?php nwcs_edit_attr( 'products', 'shared', 'faq_title' ); ?>><?php echo esc_html( strtr( (string) nwcs_field( 'products', 'shared', 'faq_title' ), array( '{tip}' => $product['name'] ) ) ); ?></h2>
			<div class="pc-faq" <?php nwcs_edit_attr( $key, 'faq', 'items' ); ?>>
				<?php foreach ( $faq as $i => $qa ) : ?>
					<details class="pc-faq__item"<?php echo 0 === $i ? ' open' : ''; ?>>
						<summary<?php nwcs_edit_attr( $key, 'faq', 'items', (int) $i, 'question' ); ?>><?php echo esc_html( $qa['question'] ); ?></summary>
						<div class="pc-faq__answer"<?php nwcs_edit_attr( $key, 'faq', 'items', (int) $i, 'answer' ); ?>><?php echo pc_paragraphs( (string) $qa['answer'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $others ) : ?>
	<section class="pc-section pc-section--steel" aria-labelledby="pc-others">
		<div class="pc-wrap">
			<header class="pc-head">
				<h2 id="pc-others" <?php nwcs_edit_attr( 'products', 'shared', 'others' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'others' ) ); ?></h2>
			</header>
			<div class="pc-prows">
				<?php foreach ( $others as $other ) : ?>
					<?php pc_part( 'product-row', array( 'product' => $other ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
