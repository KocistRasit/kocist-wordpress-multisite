<?php
/**
 * Ana sayfa: giris, sari cetvel, uc civi tipi, kullanim alanlari, Kocist
 * Grup bandi, blog, siparis formu.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hero     = pc_img( nwcs_image( 'home', 'hero', 'image', '2048x2048' ), 'hero.jpg', 'Palet üretiminde çivi tabancasıyla çakım' );
$note     = trim( (string) nwcs_field( 'home', 'hero', 'image_note' ) );
$phone    = pc_phone();
$whatsapp = pc_whatsapp();
$uses     = nwcs_rows( 'home', 'uses', 'items' );
$hub      = pc_link( pc_manifest()['pages']['products']['path'] ?? '/' );
$blog_url = pc_link( pc_manifest()['pages']['blog']['path'] ?? '/' );
$posts    = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 4,
		'ignore_sticky_posts' => true,
	)
);
?>

<section class="pc-hero">
	<div class="pc-hero__media" <?php nwcs_edit_attr( 'home', 'hero', 'image' ); ?>>
		<?php echo pc_img_tag( $hero, 'pc-hero__img', true ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
	</div>
	<div class="pc-wrap pc-hero__body">
		<h1 class="pc-hero__title" <?php nwcs_edit_attr( 'home', 'hero', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'hero', 'title' ) ); ?></h1>
		<p class="pc-hero__lead" <?php nwcs_edit_attr( 'home', 'hero', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'home', 'hero', 'lead' ) ); ?></p>
		<div class="pc-hero__actions">
			<a href="<?php echo esc_url( $phone['url'] ); ?>" class="pc-btn pc-btn--yellow pc-btn--lg" <?php nwcs_edit_attr( 'home', 'hero', 'call_label' ); ?>>
				<?php echo pc_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<?php echo esc_html( nwcs_field( 'home', 'hero', 'call_label' ) ); ?>
			</a>
			<?php if ( $whatsapp ) : ?>
				<a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--ghost pc-btn--lg" <?php nwcs_edit_attr( 'home', 'hero', 'wa_label' ); ?>>
					<?php echo pc_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					<?php echo esc_html( nwcs_field( 'home', 'hero', 'wa_label' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<p class="pc-hero__num pc-num"<?php nwcs_edit_attr( 'global', 'header', 'phone_label' ); ?>><?php echo esc_html( $phone['label'] ); ?></p>
	</div>
	<?php if ( $note && $hero['sample'] ) : ?>
		<span class="pc-sample" <?php nwcs_edit_attr( 'home', 'hero', 'image_note' ); ?>><?php echo esc_html( $note ); ?></span>
	<?php endif; ?>
</section>

<?php
pc_part(
	'ruler',
	array(
		'text'      => (string) nwcs_field( 'home', 'ruler', 'text' ),
		'label'     => (string) nwcs_field( 'home', 'ruler', 'label' ),
		'url'       => $hub,
		'page'      => 'home',
		'component' => 'ruler',
	)
);
?>

<section class="pc-section" aria-labelledby="pc-products-title">
	<div class="pc-wrap">
		<header class="pc-head">
			<h2 id="pc-products-title" <?php nwcs_edit_attr( 'home', 'products', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'title' ) ); ?></h2>
			<p <?php nwcs_edit_attr( 'home', 'products', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'home', 'products', 'lead' ) ); ?></p>
		</header>
		<?php
		// Ilk uc tip (palet civileri) buyuk satir; digerleri kompakt kart.
		$pc_types = array_values( pc_products() );
		?>
		<div class="pc-prows">
			<?php foreach ( array_slice( $pc_types, 0, 3 ) as $product ) : ?>
				<?php pc_part( 'product-row', array( 'product' => $product, 'more' => (string) nwcs_field( 'home', 'products', 'more' ), 'more_edit' => array( 'home', 'products', 'more' ) ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $pc_types ) > 3 ) : ?>
			<div class="pc-igrid pc-igrid--types">
				<?php foreach ( array_slice( $pc_types, 3 ) as $product ) : ?>
					<?php pc_part( 'type-card', array( 'product' => $product ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $uses ) : ?>
	<section class="pc-section pc-section--steel" aria-labelledby="pc-uses-title">
		<div class="pc-wrap pc-uses">
			<header class="pc-head pc-uses__head">
				<h2 id="pc-uses-title" <?php nwcs_edit_attr( 'home', 'uses', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'uses', 'title' ) ); ?></h2>
				<p <?php nwcs_edit_attr( 'home', 'uses', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'home', 'uses', 'lead' ) ); ?></p>
			</header>
			<dl class="pc-uses__list" <?php nwcs_edit_attr( 'home', 'uses', 'items' ); ?>>
				<?php foreach ( $uses as $index => $use ) : ?>
					<div>
						<dt<?php nwcs_edit_attr( 'home', 'uses', 'items', (int) $index, 'title' ); ?>><?php echo esc_html( $use['title'] ?? '' ); ?></dt>
						<dd<?php nwcs_edit_attr( 'home', 'uses', 'items', (int) $index, 'text' ); ?>><?php echo esc_html( $use['text'] ?? '' ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</div>
	</section>
<?php endif; ?>

<section class="pc-group">
	<div class="pc-wrap pc-group__row">
		<p class="pc-group__title" <?php nwcs_edit_attr( 'home', 'group', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'group', 'title' ) ); ?></p>
		<div>
			<p class="pc-group__text" <?php nwcs_edit_attr( 'home', 'group', 'text' ); ?>><?php echo esc_html( nwcs_field( 'home', 'group', 'text' ) ); ?></p>
			<p class="pc-group__address"<?php nwcs_edit_attr( 'global', 'header', 'address' ); ?>><?php echo pc_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapingOutput ?><?php echo esc_html( nwcs_field( 'global', 'header', 'address' ) ); ?></p>
		</div>
	</div>
</section>

<?php if ( $posts->have_posts() ) : ?>
	<section class="pc-section" aria-labelledby="pc-blog-title">
		<div class="pc-wrap">
			<header class="pc-head pc-head--row">
				<h2 id="pc-blog-title" <?php nwcs_edit_attr( 'home', 'blog', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'blog', 'title' ) ); ?></h2>
				<a href="<?php echo esc_url( $blog_url ); ?>" class="pc-link" <?php nwcs_edit_attr( 'home', 'blog', 'more' ); ?>><?php echo esc_html( nwcs_field( 'home', 'blog', 'more' ) ); ?></a>
			</header>
			<div class="pc-posts">
				<?php
				while ( $posts->have_posts() ) :
					$posts->the_post();
					pc_part( 'post-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="pc-section pc-section--steel" aria-labelledby="pc-order-title">
	<div class="pc-wrap pc-order">
		<div>
			<h2 id="pc-order-title" class="pc-order__title" <?php nwcs_edit_attr( 'home', 'contact', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'contact', 'title' ) ); ?></h2>
			<p class="pc-order__lead" <?php nwcs_edit_attr( 'home', 'contact', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'home', 'contact', 'lead' ) ); ?></p>
			<?php pc_part( 'contact-lines' ); ?>
		</div>
		<?php pc_part( 'quote-form' ); ?>
	</div>
</section>

<?php
get_footer();
