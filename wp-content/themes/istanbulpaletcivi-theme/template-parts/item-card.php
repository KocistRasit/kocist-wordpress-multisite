<?php
/**
 * Katalog urun karti (tip sayfasi ve "ayni tipteki diger urunler").
 *
 * $args: item (pc_catalog() ogesi), heading (h2/h3)
 */

defined( 'ABSPATH' ) || exit;

$item = $args['item'] ?? null;

if ( ! $item ) {
	return;
}

$tag   = in_array( $args['heading'] ?? '', array( 'h2', 'h3' ), true ) ? $args['heading'] : 'h3';
$image = pc_catalog_image( $item, 'medium_large' );
$specs = array_slice( pc_specs( $item['specs'] ), 0, 3 );
$wa    = pc_whatsapp_product( array( 'name' => $item['title'], 'url' => $item['url'] ) );
?>
<article class="pc-icard">
	<a href="<?php echo esc_url( $item['url'] ); ?>" class="pc-icard__media" tabindex="-1" aria-hidden="true"<?php nwcs_edit_attr( 'products', 'catalog', 'items', (int) $item['index'], 'image' ); ?>>
		<?php echo pc_img_tag( $image, 'pc-icard__img' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
		<?php if ( $image['sample'] ) : ?>
			<span class="pc-sample"><?php echo esc_html( nwcs_field( 'global', 'common', 'sample_note' ) ); ?></span>
		<?php endif; ?>
	</a>
	<div class="pc-icard__body">
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapingOutput ?> class="pc-icard__name"<?php nwcs_edit_attr( 'products', 'catalog', 'items', (int) $item['index'], 'title' ); ?>>
			<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapingOutput ?>>
		<?php if ( $specs ) : ?>
			<dl class="pc-icard__specs">
				<?php foreach ( $specs as $pair ) : ?>
					<div><dt><?php echo esc_html( $pair[0] ); ?></dt><dd><?php echo esc_html( $pair[1] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
		<p class="pc-icard__short"<?php nwcs_edit_attr( 'products', 'catalog', 'items', (int) $item['index'], 'short' ); ?>><?php echo esc_html( $item['short'] ); ?></p>
		<div class="pc-icard__actions">
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="pc-btn pc-btn--navy"<?php nwcs_edit_attr( 'products', 'list', 'more' ); ?>><?php echo esc_html( nwcs_field( 'products', 'list', 'more' ) ); ?></a>
			<?php if ( $wa ) : ?>
				<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--line"<?php nwcs_edit_attr( 'products', 'list', 'ask' ); ?>><?php echo esc_html( nwcs_field( 'products', 'list', 'ask' ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</article>
