<?php
/**
 * Blog yazisi: baslik, kapak, okuma sutunu; yanda WhatsApp kutusu,
 * altta diger yazilar.
 *
 * Ornek yazilarin baslik, kapak ve metni panelde de duzenlenir; onizlemede
 * tiklaninca yazinin gizli panel sayfasi acilir (functions.php: Blog
 * yazilari panelden).
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$current = get_post();
	$image   = ik_post_image( $current, 'full' );
	$others  = get_posts(
		array(
			'numberposts'  => 3,
			'post__not_in' => array( $current->ID ),
			'post_status'  => 'publish',
		)
	);
	?>

	<article class="ik-article">
		<header class="ik-pagehead">
			<div class="ik-wrap">
				<nav class="ik-crumbs" aria-label="Konum">
					<ol>
						<li><a href="<?php echo esc_url( ik_link( '/blog/' ) ); ?>" <?php nwcs_edit_attr( 'blog', 'post', 'back_label' ); ?>><?php echo esc_html( nwcs_field( 'blog', 'post', 'back_label' ) ); ?></a></li>
					</ol>
				</nav>
				<h1 class="ik-pagehead__title ik-article__title" <?php ik_post_edit_attr( $current, 'title' ); ?>><?php the_title(); ?></h1>
				<?php // Guncelleme tarihi yayin gununden farkliysa gorunur (dateModified ile ayni tarih). ?>
				<?php $updated = ik_date( $current, 'modified' ); ?>
				<p class="ik-article__date">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" <?php nwcs_post_attr( (int) $current->ID, 'Yayın tarihi' ); ?>><?php echo esc_html( ik_date( $current ) ); ?></time>
					<?php if ( '' !== $updated && ik_date( $current ) !== $updated ) : ?>
						<span class="ik-article__updated">· Güncellendi: <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( $updated ); ?></time></span>
					<?php endif; ?>
				</p>
			</div>
		</header>

		<div class="ik-section ik-article__body">
			<div class="ik-wrap ik-article__grid">
				<div class="ik-article__main">
					<?php if ( ! empty( $image['url'] ) ) : ?>
						<img class="ik-article__cover" <?php ik_post_edit_attr( $current, 'image' ); ?> src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>"<?php echo ik_image_dims( $image ); // phpcs:ignore WordPress.Security.EscapingOutput ?> fetchpriority="high" decoding="async" />
					<?php endif; ?>
					<div class="ik-prose ik-prose--page" <?php ik_post_edit_attr( $current, 'body' ); ?>>
						<?php the_content(); ?>
					</div>
				</div>

				<aside class="ik-article__aside">
					<?php get_template_part( 'template-parts/help-box' ); ?>
				</aside>
			</div>
		</div>
	</article>

	<?php if ( $others ) : ?>
		<section class="ik-section ik-more">
			<div class="ik-wrap">
				<h2 class="ik-title" <?php nwcs_edit_attr( 'blog', 'post', 'more_title' ); ?>><?php echo esc_html( nwcs_field( 'blog', 'post', 'more_title' ) ); ?></h2>
				<div class="ik-posts">
					<?php foreach ( $others as $post_item ) : ?>
						<?php get_template_part( 'template-parts/post-card', null, array( 'post' => $post_item ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
endwhile;

get_footer();
