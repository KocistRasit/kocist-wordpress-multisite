<?php
/**
 * Urun detayi: /urunlerimiz/<slug>/.
 *
 * Solda fotograf ve metin, sagda butun urunlerin listesi (bulunulan urun
 * isaretli), altinda WhatsApp yardim kutusu. Beklenen $args: product.
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? array();

if ( ! $product ) {
	return;
}

// Gorsel sayfa genisliginde (~1240px) basildigi icin 'large' (1024px) yetmez;
// 1536px boyu yoksa WordPress tam boyu verir.
$image = ik_product_image( $product, '1536x1536' );

// Panel gorselinde telefon kucuk boyu, genis ekran buyugu indirir.
$srcset      = ! empty( $image['id'] ) ? (string) wp_get_attachment_image_srcset( (int) $image['id'], '1536x1536' ) : '';
$image_attrs = '' !== $srcset ? sprintf( ' srcset="%s" sizes="(min-width: 1304px) 760px, (min-width: 960px) 58vw, 100vw"', esc_attr( $srcset ) ) : '';
$specs = ik_specs( $product['specs'] );
$row   = array( 'products', 'catalog', 'items', (int) $product['index'] );

// Alt metin: urunun gizli panel sayfasinda yazildiysa o, yoksa kisa aciklama.
$lead      = ik_product_page_field( $product, 'head', 'lead' );
$lead_edit = '' !== $lead ? array( ik_product_page_key( $product ), 'head', 'lead' ) : array( ...$row, 'short' );

get_template_part(
	'template-parts/page-head',
	null,
	array(
		'title'      => $product['title'],
		'text'       => '' !== $lead ? $lead : $product['short'],
		'title_edit' => array( ...$row, 'title' ),
		'text_edit'  => $lead_edit,
		'crumbs'     => array(
			array( nwcs_field( 'products', 'detail', 'back_label' ), ik_link( '/urunlerimiz/' ), array( 'products', 'detail', 'back_label' ) ),
		),
		// Urun adi ve alt metin bilgi sutununda: gorsel ve dugmeler sayfanin basinda.
		'compact'    => true,
	)
);
?>
<section class="ik-section ik-product">
	<div class="ik-wrap ik-product__grid">
		<?php // Genis ekranda sagda buyuk gorsel, solda metin ve yan liste; telefonda gorsel ustte (pages.css). ?>
			<figure class="ik-product__media" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $product['index'], 'image' ); ?>>
				<?php if ( ! empty( $image['url'] ) ) : ?>
					<?php // Buyutme: tam boy gorsel pencerede, icinde ikinci kademe yakinlastirma (assets/js/product-zoom.js). ?>
					<button type="button" class="ik-zoom-open" data-ik-zoom-open
						data-full="<?php echo esc_url( ik_product_image( $product, 'full' )['url'] ?? $image['url'] ); ?>"
						aria-label="<?php echo esc_attr( $product['title'] . ' görselini büyüt' ); ?>">
						<?php echo ik_image_tag( $image, 'ik-product__image', null, 'eager', $image_attrs ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
						<span class="ik-zoom-open__badge" aria-hidden="true">Büyüt</span>
					</button>
				<?php else : ?>
					<?php echo ik_image_tag( $image, 'ik-product__image', null, 'eager' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<?php endif; ?>
			</figure>

			<?php if ( ! empty( $image['url'] ) ) : ?>
				<dialog class="ik-zoom" data-ik-zoom aria-label="<?php echo esc_attr( $product['title'] ); ?>">
					<div class="ik-zoom__frame">
						<div class="ik-zoom__stage" data-ik-zoom-stage>
							<img class="ik-zoom__image" data-ik-zoom-image src="" alt="<?php echo esc_attr( $image['alt'] ?: $product['title'] ); ?>" />
						</div>

						<form method="dialog" class="ik-zoom__close">
							<button type="submit" class="ik-zoom__btn" aria-label="Kapat">&times;</button>
						</form>

						<div class="ik-zoom__bar" role="group" aria-label="Yakınlaştırma">
							<button type="button" class="ik-zoom__btn" data-ik-zoom-out aria-label="Uzaklaştır">&minus;</button>
							<span class="ik-zoom__level" data-ik-zoom-level aria-live="polite">%100</span>
							<button type="button" class="ik-zoom__btn" data-ik-zoom-in aria-label="Yakınlaştır">+</button>
							<button type="button" class="ik-zoom__btn ik-zoom__btn--text" data-ik-zoom-reset>Sığdır</button>
						</div>
					</div>
					<p class="ik-zoom__hint">Yakınlaştırmak için görsele tıklayın ya da tekerleği kullanın; yakınken sürükleyerek gezinin.</p>
				</dialog>
			<?php endif; ?>

		<?php // Genis ekranda solda ad, kisa metin ve dugmeler (yapisik), sagda buyuk gorsel. ?>
		<div class="ik-product__buy">
			<h1 class="ik-product__title" <?php nwcs_edit_attr( ...array_merge( $row, array( 'title' ) ) ); ?>><?php echo esc_html( $product['title'] ); ?></h1>
			<?php $intro = '' !== $lead ? $lead : $product['short']; ?>
			<?php if ( '' !== trim( (string) $intro ) ) : ?>
				<p class="ik-product__lead" <?php nwcs_edit_attr( ...$lead_edit ); ?>><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<div class="ik-product__actions">
				<a class="btn btn--solid" href="<?php echo esc_url( add_query_arg( 'urun', rawurlencode( $product['title'] ), ik_link( nwcs_field( 'products', 'detail', 'quote_url' ) ) ) ); ?>" <?php nwcs_edit_attr( 'products', 'detail', 'quote_label' ); ?>>
					<?php echo esc_html( nwcs_field( 'products', 'detail', 'quote_label' ) ); ?>
				</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( ik_whatsapp_link( ik_link( nwcs_field( 'products', 'help', 'number_url' ) ), $product ) ); ?>" target="_blank" rel="noopener" <?php nwcs_edit_attr( 'products', 'detail', 'whatsapp_label' ); ?>>
					<?php ik_icon( 'whatsapp', 20 ); ?>
					<?php echo esc_html( nwcs_field( 'products', 'detail', 'whatsapp_label' ) ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php // Metin ve ozellikler ust bolumun altinda, kendi kabinda: yapisik sutun bunlarin ustune binmez. ?>
	<div class="ik-wrap ik-product__below">
		<article class="ik-product__main<?php echo $specs ? ' ik-product__main--split' : ''; ?>">
			<div class="ik-prose ik-product__body" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $product['index'], 'body' ); ?>>
				<?php $extras = ik_product_extras( $product['slug'] ); ?>
				<?php if ( $extras && '' !== $extras['answer'] ) : ?>
					<?php // Kisa cevap: arama ve yapay zeka ozetleri sayfanin ilk paragrafini alir. ?>
					<p class="ik-product__answer"><?php echo esc_html( $extras['answer'] ); ?></p>
				<?php endif; ?>
				<?php ik_paragraphs( $product['body'] ); ?>
				<?php if ( $extras && '' !== $extras['sizes'] ) : ?>
					<h2 class="ik-product__subtitle">Ölçüler</h2>
					<?php echo ik_rich_html( $extras['sizes'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<?php endif; ?>
				<?php if ( $extras && $extras['faq'] ) : ?>
					<h2 class="ik-product__subtitle">Sık sorulan sorular</h2>
					<div class="ik-product__faq">
						<?php foreach ( $extras['faq'] as $item ) : ?>
							<h3><?php echo esc_html( $item[0] ); ?></h3>
							<?php echo ik_rich_html( $item[1] ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php $palet_note = 'ahsap-palet' === $product['slug'] ? trim( (string) nwcs_field( 'products', 'detail', 'palet_note' ) ) : ''; ?>
				<?php if ( '' !== $palet_note ) : ?>
					<p class="ik-product__related">
						<span <?php nwcs_edit_attr( 'products', 'detail', 'palet_note' ); ?>><?php echo esc_html( $palet_note ); ?></span>
						<a href="<?php echo esc_url( (string) nwcs_field( 'products', 'detail', 'palet_url' ) ); ?>" <?php nwcs_edit_attr( 'products', 'detail', 'palet_label' ); ?>><?php echo esc_html( (string) nwcs_field( 'products', 'detail', 'palet_label' ) ); ?></a>
					</p>
				<?php endif; ?>
				<?php $guides = ik_product_guides( $product['slug'] ); ?>
				<?php if ( $guides ) : ?>
					<h2 class="ik-product__subtitle" <?php nwcs_edit_attr( 'products', 'detail', 'guides_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'detail', 'guides_title' ) ); ?></h2>
					<ul class="ik-product__guides">
						<?php foreach ( $guides as $guide ) : ?>
							<li><a href="<?php echo esc_url( $guide['url'] ); ?>"><?php echo esc_html( $guide['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( $specs ) : ?>
				<div class="ik-product__specs">
					<h2 class="ik-product__subtitle" <?php nwcs_edit_attr( 'products', 'detail', 'specs_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'detail', 'specs_title' ) ); ?></h2>
					<dl class="ik-specs" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $product['index'], 'specs' ); ?>>
						<?php foreach ( $specs as $spec ) : ?>
							<div class="ik-specs__row">
								<dt><?php echo esc_html( $spec[0] ); ?></dt>
								<dd><?php echo esc_html( $spec[1] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			<?php endif; ?>
		</article>

		<aside class="ik-product__aside">
			<nav class="ik-sidelist" aria-labelledby="ik-sidelist-title">
				<h2 class="ik-sidelist__title" id="ik-sidelist-title" <?php nwcs_edit_attr( 'products', 'detail', 'others_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'detail', 'others_title' ) ); ?></h2>
				<ul>
					<?php foreach ( ik_products() as $item ) : ?>
						<li>
							<a class="ik-sidelist__link" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['slug'] === $product['slug'] ? ' aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'products', 'catalog', 'items', (int) $item['index'], 'title' ); ?>>
								<?php echo esc_html( $item['title'] ); ?>
								<?php ik_icon( 'arrow', 16 ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<?php get_template_part( 'template-parts/help-box' ); ?>
		</aside>
	</div>
</section>
