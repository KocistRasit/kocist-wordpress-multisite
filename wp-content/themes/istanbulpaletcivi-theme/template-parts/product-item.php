<?php
/**
 * Katalog urun sayfasi (/civiler/<urun>/). pc_catalog_fallback() cizer.
 *
 * Ustte gorsel + ad, kisa aciklama ve siparis kutusu; altta teknik ozellik
 * tablosu ve aciklama; en altta ayni tipteki diger urunler.
 *
 * $args: item (pc_catalog() ogesi)
 */

defined( 'ABSPATH' ) || exit;

$item = $args['item'] ?? null;

if ( ! $item ) {
	return;
}

$row      = (int) $item['index'];
$parent   = pc_catalog_parent( $item );
$hub      = pc_manifest()['pages']['products'] ?? array();
$phone    = pc_phone();
$wa       = pc_whatsapp_product( array( 'name' => $item['title'], 'url' => $item['url'] ) );
$specs    = pc_specs( $item['specs'] );
[ $size_head, $size_rows ] = pc_sizes( $item['sizes'] );
$contact  = pc_link( pc_manifest()['pages']['contact']['path'] ?? '/iletisim/' );
$quote    = add_query_arg( array( 'urun' => rawurlencode( (string) ( $parent['name'] ?? '' ) ), 'olcu' => rawurlencode( $item['title'] ) ), $contact ) . '#siparis';
$hero_img = pc_catalog_image( $item, '1536x1536' );
$has_photo = '' !== ( $hero_img['url'] ?? '' );
$siblings = $parent ? array_values( array_filter( pc_catalog_group( $item['group'] ), static fn( array $other ): bool => $other['slug'] !== $item['slug'] ) ) : array();

$crumbs = array( array( (string) nwcs_field( 'products', 'head', 'title' ), pc_link( $hub['path'] ?? '/' ), array( 'products', 'head', 'title' ) ) );

if ( $parent ) {
	$crumbs[] = array( (string) $parent['name'], (string) $parent['url'], array( $parent['key'], 'card', 'name' ) );
}

pc_part(
	'page-head',
	array(
		'title'      => $item['title'],
		'lead'       => $item['short'],
		'crumbs'     => $crumbs,
		'title_edit' => array( 'products', 'catalog', 'items', $row, 'title' ),
		'lead_edit'  => array( 'products', 'catalog', 'items', $row, 'short' ),
		'compact'    => true,
	)
);
?>

<section class="pc-section">
	<div class="pc-wrap pc-product">
		<figure class="pc-product__figure" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'image' ); ?>>
			<?php if ( $has_photo ) : ?>
				<button type="button" class="pc-product__stage" data-pc-stage data-full="<?php echo esc_url( $hero_img['url'] ); ?>"
					aria-label="<?php echo esc_attr( $item['title'] ); ?> görselini tam ekranda aç">
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
			<h1 class="pc-product__title" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'title' ); ?>><?php echo esc_html( $item['title'] ); ?></h1>
			<?php if ( '' !== $item['short'] ) : ?>
				<p class="pc-product__lead" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'short' ); ?>><?php echo esc_html( $item['short'] ); ?></p>
			<?php endif; ?>
			<?php if ( $parent ) : ?>
				<p class="pc-product__tool"><a href="<?php echo esc_url( $parent['url'] ); ?>"><?php echo esc_html( $parent['name'] ); ?></a> · <span<?php nwcs_edit_attr( $parent['key'], 'card', 'tool' ); ?>><?php echo esc_html( $parent['tool'] ); ?></span></p>
			<?php endif; ?>

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
			<dialog class="pc-lb" data-pc-lb aria-label="<?php echo esc_attr( $item['title'] ); ?>">
				<div class="pc-lb__stage" data-pc-lb-stage>
					<img class="pc-lb__img" data-pc-lb-img src="" alt="<?php echo esc_attr( $hero_img['alt'] ?? $item['title'] ); ?>" />
				</div>
				<p class="pc-lb__hint">Yakınlaştırmak için görsele tıklayın; yakınken fareyle gezinin.</p>
				<button type="button" class="pc-lb__close" data-pc-lb-close aria-label="Kapat"><span aria-hidden="true">×</span></button>
			</dialog>
		<?php endif; ?>
	</div>

	<div class="pc-wrap pc-pdetail">
		<div class="pc-product__main">
			<?php if ( $specs ) : ?>
				<h2 class="pc-product__subtitle"<?php nwcs_edit_attr( 'products', 'shared', 'specs_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'specs_title' ) ); ?></h2>
				<table class="pc-specs" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'specs' ); ?>>
					<tbody>
						<?php foreach ( $specs as $pair ) : ?>
							<tr><th scope="row"><?php echo esc_html( $pair[0] ); ?></th><td><?php echo esc_html( $pair[1] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $size_rows ) : ?>
				<h2 class="pc-product__subtitle"<?php nwcs_edit_attr( 'products', 'shared', 'sizes_title' ); ?>><?php echo esc_html( nwcs_field( 'products', 'shared', 'sizes_title' ) ); ?></h2>
				<div class="pc-sizes-wrap" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'sizes' ); ?>>
					<table class="pc-sizes">
						<thead><tr><?php foreach ( $size_head as $cell ) : ?><th scope="col"><?php echo esc_html( $cell ); ?></th><?php endforeach; ?></tr></thead>
						<tbody>
							<?php foreach ( $size_rows as $cells ) : ?>
								<tr><?php foreach ( $cells as $i => $cell ) : ?><?php if ( 0 === $i ) : ?><th scope="row"><?php echo esc_html( $cell ); ?></th><?php else : ?><td><?php echo esc_html( $cell ); ?></td><?php endif; ?><?php endforeach; ?></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="pc-prose" <?php nwcs_edit_attr( 'products', 'catalog', 'items', $row, 'body' ); ?>>
				<?php echo pc_paragraphs( (string) $item['body'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			</div>
		</div>
	</div>
</section>

<?php if ( $siblings ) : ?>
	<section class="pc-section pc-section--steel" aria-labelledby="pc-siblings">
		<div class="pc-wrap">
			<header class="pc-head">
				<h2 id="pc-siblings" <?php nwcs_edit_attr( 'products', 'shared', 'siblings_title' ); ?>><?php echo esc_html( strtr( (string) nwcs_field( 'products', 'shared', 'siblings_title' ), array( '{tip}' => $parent['name'] ) ) ); ?></h2>
			</header>
			<div class="pc-igrid">
				<?php foreach ( array_slice( $siblings, 0, 6 ) as $other ) : ?>
					<?php pc_part( 'item-card', array( 'item' => $other ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php if ( $parent && count( $siblings ) > 6 ) : ?>
				<p class="pc-igrid__more"><a class="pc-link" href="<?php echo esc_url( $parent['url'] ); ?>"><?php echo esc_html( $parent['name'] ); ?> →</a></p>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
