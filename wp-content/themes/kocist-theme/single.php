<?php
/**
 * Tekil blog yazisi.
 *
 * Metin sutunu okunur genislikte (en fazla ~68 karakter); kapak gorseli
 * yoksa kategorisine gore temadaki fotograf. Altta ayni kategoriden, yoksa
 * en yeni yazilardan uc ilgili yazi.
 *
 * Ornek yazilarin baslik, kapak ve metni panelde de duzenlenir; onizlemede
 * tiklaninca yazinin gizli panel sayfasi acilir (functions.php: Blog
 * yazilari panelden).
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$post_id  = get_the_ID();
	$term     = kocist_post_category( $post_id );
	$image    = kocist_post_image( $post_id, 'large' );
	$blog_url = kocist_blog_url();
	?>
	<article class="k-post">
		<header class="k-post__head">
			<div class="k-wrap">
				<nav class="k-post__crumb" aria-label="Konum">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php nwcs_edit_attr( 'global', 'texts', 'home_crumb' ); ?>><?php echo esc_html( nwcs_field( 'global', 'texts', 'home_crumb' ) ); ?></a>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $blog_url ); ?>" <?php nwcs_edit_attr( 'blog', 'page_head', 'title' ); ?>><?php echo esc_html( nwcs_field( 'blog', 'page_head', 'title' ) ); ?></a>
					<?php if ( $term ) : ?>
						<span aria-hidden="true">/</span>
						<a href="<?php echo esc_url( get_category_link( $term ) ); ?>" <?php kocist_term_attr( $term ); ?>><?php echo esc_html( $term->name ); ?></a>
					<?php endif; ?>
				</nav>

				<h1 class="k-post__title" <?php kocist_post_edit_attr( $post_id, 'title' ); ?>><?php the_title(); ?></h1>

				<p class="k-post__meta">
					<?php if ( $term ) : ?>
						<a class="k-post-card__cat" href="<?php echo esc_url( get_category_link( $term ) ); ?>" <?php kocist_term_attr( $term ); ?>><?php echo esc_html( $term->name ); ?></a>
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" <?php nwcs_post_attr( (int) $post_id, 'Yayın tarihi' ); ?>><?php echo esc_html( kocist_post_date( $post_id ) ); ?></time>
<span class="k-post__author">Yazar: Koçist Grup</span>
				</p>
			</div>
		</header>

		<div class="k-wrap">
			<figure class="k-post__cover" <?php kocist_post_edit_attr( $post_id, 'image' ); ?>>
				<?php echo kocist_image_tag( $image, '', '' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			</figure>

			<div class="k-post__body" <?php kocist_post_edit_attr( $post_id, 'body' ); ?>>
				<?php the_content(); ?>
			</div>

			<p class="k-post__back">
				<a href="<?php echo esc_url( $blog_url ); ?>" <?php nwcs_edit_attr( 'blog', 'single', 'back_label' ); ?>>
					<?php nwcs_the_icon( 'arrow', 'k-post__back-icon', 16 ); ?>
					<?php echo esc_html( nwcs_field( 'blog', 'single', 'back_label' ) ); ?>
				</a>
			</p>
		</div>
	</article>

	<?php
	$related_args = array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( $term ) {
		$related_args['cat'] = (int) $term->term_id;
	}

	$related = new WP_Query( $related_args );

	// Kategoride uc yazi yoksa eksik kalan yer en yeni yazilarla dolar.
	if ( $term && $related->post_count < 3 ) {
		$ids = array_merge(
			wp_list_pluck( $related->posts, 'ID' ),
			get_posts(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => 3 - $related->post_count,
					'post__not_in'        => array_merge( array( $post_id ), wp_list_pluck( $related->posts, 'ID' ) ),
					'ignore_sticky_posts' => true,
					'fields'              => 'ids',
				)
			)
		);

		$related = new WP_Query(
			array(
				'post_type'           => 'post',
				'post__in'            => $ids ? $ids : array( 0 ),
				'orderby'             => 'post__in',
				'posts_per_page'      => 3,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}

	if ( $related->have_posts() ) :
		?>
		<section class="k-section k-section--alt k-related" aria-labelledby="k-related-title">
			<div class="k-wrap">
				<h2 class="k-section-title" id="k-related-title" <?php nwcs_edit_attr( 'blog', 'single', 'related_title' ); ?>><?php echo esc_html( nwcs_field( 'blog', 'single', 'related_title' ) ); ?></h2>
				<div class="k-post-grid">
					<?php
					while ( $related->have_posts() ) :
						$related->the_post();
						kocist_post_card( 'h3' );
					endwhile;
					?>
				</div>
			</div>
		</section>
		<?php
	endif;

	wp_reset_postdata();
endwhile;

get_footer();
