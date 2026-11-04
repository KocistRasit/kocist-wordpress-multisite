<?php
/**
 * Blog: ilk kurulum yazilari ve sablon yardimcilari.
 *
 * Yazilarin metinleri content/blog-posts.php'de; panel de ayni dosyadan
 * okur (her yazinin gizli sayfasi, functions.php: Blog yazilari panelden).
 * Tema yazilari yalnizca bir kez olusturur (kocist_blog_seeded):
 * kullanici bir yaziyi silerse geri gelmez. Olusturulan her yazi
 * _kocist_seed meta anahtariyla isaretlenir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ornek yazilar (content/blog-posts.php): slug, title, excerpt, body, date,
 * category.
 */
function kocist_blog_seed_posts(): array {
	static $posts = null;

	if ( null === $posts ) {
		$posts = (array) include get_theme_file_path( 'content/blog-posts.php' );
	}

	return $posts;
}

/**
 * Yazi metnini HTML'e cevirir: bos satirla ayrilan her blok bir paragraf,
 * "hazirlaniyor" notu soluk. Ilk kurulum ve paneldeki metin ayni yoldan
 * gecer.
 */
function kocist_blog_body_html( string $text ): string {
	kocist_blog_seed_posts(); // KOCIST_BLOG_PENDING orada tanimli.

	$blocks = array_filter( array_map( 'trim', preg_split( '/\R\s*\R/u', trim( $text ) ) ), 'strlen' );
	$html   = array();

	foreach ( $blocks as $block ) {
		$class  = KOCIST_BLOG_PENDING === $block ? ' class="k-post__pending"' : '';
		$html[] = '<p' . $class . '>' . nl2br( esc_html( $block ) ) . '</p>';
	}

	return implode( "\n\n", $html );
}

/**
 * Yazilari bir kez olusturur. Bayrak once yazilir: ayni anda gelen iki
 * istek yazilari iki kez eklemesin. Adres adi zaten varsa (cop kutusu dahil)
 * o yazi atlanir.
 */
function kocist_seed_blog_posts(): void {
	if ( get_option( 'kocist_blog_seeded' ) ) {
		return;
	}

	update_option( 'kocist_blog_seeded', 1 );

	// WordPress'in kendi ornek yazisi listede gorunmesin.
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' ) ?: get_page_by_path( 'merhaba-dunya', OBJECT, 'post' );

	if ( $hello && 'publish' === $hello->post_status ) {
		wp_update_post( array( 'ID' => $hello->ID, 'post_status' => 'draft' ) );
	}

	foreach ( kocist_blog_seed_posts() as $post ) {
		$slug     = $post['slug'];
		$category = $post['category'];

		$existing = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => 'post',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( $existing ) {
			continue;
		}

		$term = term_exists( $category, 'category' );

		if ( ! $term ) {
			$term = wp_insert_term( $category, 'category' );
		}

		$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

		$post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_name'     => $slug,
				'post_title'    => $post['title'],
				'post_excerpt'  => $post['excerpt'],
				'post_content'  => kocist_blog_body_html( $post['body'] ),
				'post_date'     => $post['date'],
				'post_category' => $term_id ? array( $term_id ) : array(),
			),
			true
		);

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_kocist_seed', $slug );
		}
	}
}

/**
 * Yazilar sayfasinin adresi. Okuma ayari yoksa /blog/ yolu.
 */
function kocist_blog_url(): string {
	$page = (int) get_option( 'page_for_posts' );

	return $page ? (string) get_permalink( $page ) : home_url( '/blog/' );
}

/**
 * Yazinin ilk kategorisi (Kategorisiz haric).
 */
function kocist_post_category( ?int $post_id = null ): ?WP_Term {
	foreach ( get_the_category( $post_id ?? get_the_ID() ) as $term ) {
		if ( 'uncategorized' !== $term->slug && (int) get_option( 'default_category' ) !== (int) $term->term_id ) {
			return $term;
		}
	}

	return null;
}

/**
 * Yazinin kapak gorseli; yoksa kategorisine gore temadaki fotograf.
 */
function kocist_post_image( ?int $post_id = null, string $size = 'large' ): array {
	$post_id = $post_id ?? get_the_ID();
	$thumb   = (int) get_post_thumbnail_id( $post_id );

	if ( $thumb ) {
		$src = wp_get_attachment_image_src( $thumb, $size );

		if ( $src ) {
			return array(
				'id'     => $thumb,
				'url'    => $src[0],
				'alt'    => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	$term  = kocist_post_category( $post_id );
	// Ham baslik: panelde baslik degisse de yedek fotograf ayni kalir.
	$key   = sanitize_title( ( $term ? $term->name : '' ) . ' ' . get_post_field( 'post_title', $post_id ) );
	$rules = array(
		'kamelya'    => 'ahsap-kamelya-3x3-zeminli.webp',
		'dekorasyon' => 's2-dekorasyon.jpg',
		'kontrplak'  => 'playwood.webp',
		'osb'        => 'playwood.webp',
		'kereste'    => 's2-kereste.jpg',
		'tomruk'     => 's2-kereste.jpg',
		'palet'      => 's2-ambalaj.jpg',
		'sandik'     => 's2-ambalaj.jpg',
		'ambalaj'    => 's2-ambalaj.jpg',
		'ihracat'    => 's2-ambalaj.jpg',
		'lojistik'   => 's2-ambalaj.jpg',
	);
	$file  = 'atolye.jpg';

	foreach ( $rules as $needle => $candidate ) {
		if ( str_contains( $key, $needle ) ) {
			$file = $candidate;
			break;
		}
	}

	return kocist_image_or_default( array(), $file, '' );
}

/**
 * Yazinin tarihi Turkce ay adiyla (12 Ocak 2025).
 *
 * Site dili Turkce olmayabilir (ag dil ayari tema isi degil); ay adlari
 * WordPress ceviri dosyasina baglanmadan buradan basilir.
 */
function kocist_post_date( ?int $post_id = null ): string {
	$months = array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	$time   = (int) get_post_time( 'U', false, $post_id ?? get_the_ID() );

	return gmdate( 'j', $time ) . ' ' . $months[ (int) gmdate( 'n', $time ) - 1 ] . ' ' . gmdate( 'Y', $time );
}

/**
 * Blog karti (liste, ana sayfa, ilgili yazilar ayni karti kullanir).
 *
 * Onizlemede baslik, ozet ve gorsel yazinin panel sayfasindaki alani acar
 * (sonradan yazilan yazida isaret yok).
 */
function kocist_post_card( string $heading = 'h2' ): void {
	$term  = kocist_post_category();
	$image = kocist_post_image( null, 'medium_large' );
	$tag   = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h2';
	?>
	<article class="k-post-card">
		<a class="k-post-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true" <?php kocist_post_edit_attr( get_the_ID(), 'image' ); ?>>
			<?php echo kocist_image_tag( $image, '', '' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
		</a>
		<div class="k-post-card__body">
			<p class="k-post-card__meta">
				<?php if ( $term ) : ?>
					<span class="k-post-card__cat" <?php kocist_term_attr( $term ); ?>><?php echo esc_html( $term->name ); ?></span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" <?php nwcs_post_attr( (int) get_the_ID(), 'Yayın tarihi' ); ?>><?php echo esc_html( kocist_post_date() ); ?></time>
			</p>
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapingOutput -- sabit liste. ?> class="k-post-card__title" <?php kocist_post_edit_attr( get_the_ID(), 'title' ); ?>>
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapingOutput ?>>
			<p class="k-post-card__excerpt" <?php kocist_post_edit_attr( get_the_ID(), 'excerpt' ); ?>><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26, '…' ) ); ?></p>
		</div>
	</article>
	<?php
}
