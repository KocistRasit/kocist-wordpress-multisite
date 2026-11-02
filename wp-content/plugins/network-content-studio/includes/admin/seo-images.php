<?php
/**
 * SEO ve GEO > Paylasim gorselleri: sitenin butun sayfalarinin (gorunur
 * sayfalar, gizli kategori / grup / urun / yazi sayfalari) baglanti
 * paylasilinca gorunen gorseli tek ekranda; her kartta gorselin nereden
 * geldigi ve yerinde degistirme formu.
 *
 * Kaydetme, Icerik Studyosu'nun yoluyla (nwcs_save_ajax); formlar yalnizca
 * gorsel alanlarini gonderir ('only'), baslik ve aciklama dokunulmaz kalir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kaynak kodlarinin paneldeki adi ve rozet sinifi.
 *
 * @return array<string, array{label:string, class:string, hint:string}>
 */
function nwcs_seo_share_source_labels(): array {
	return array(
		'own'     => array( 'label' => 'Elle seçildi', 'class' => 'nwcs-badge--ok', 'hint' => 'Bu sayfa için panelde seçilen görsel.' ),
		'product' => array( 'label' => 'Ürün fotoğrafı', 'class' => 'nwcs-badge--ok', 'hint' => 'Bu sayfa için seçilen ürünün fotoğrafı.' ),
		'page'    => array( 'label' => 'Sayfanın görseli', 'class' => '', 'hint' => 'Sayfadaki ilk görsel; içerik değişince kendiliğinden değişir.' ),
		'post'    => array( 'label' => 'Yazının kapağı', 'class' => '', 'hint' => 'Yazının öne çıkan görseli (WordPress → Yazılar).' ),
		'default' => array( 'label' => 'Site varsayılanı', 'class' => '', 'hint' => 'Sayfanın kendi görseli yok; yukarıdaki site varsayılanı görünür.' ),
		'logo'    => array( 'label' => 'Logo', 'class' => 'nwcs-badge--warn', 'hint' => 'Ne sayfanın ne sitenin görseli var; logo görünür. Site varsayılanı seçin.' ),
		'home'    => array( 'label' => 'Ana sayfanın görseli', 'class' => 'nwcs-badge--warn', 'hint' => 'Sayfanın ve sitenin görseli yok; ana sayfanın ilk görseli görünür.' ),
		'theme'   => array( 'label' => 'Temanın yedek görseli', 'class' => 'nwcs-badge--warn', 'hint' => 'Panelde hiç görsel yok; temanın dosyasındaki yedek görsel görünür. Site varsayılanı seçin.' ),
		'none'    => array( 'label' => 'Görsel yok', 'class' => 'nwcs-badge--warn', 'hint' => 'Bağlantı görselsiz paylaşılır. Site varsayılanı seçin.' ),
	);
}

/**
 * Site raporu: sayfa kartlari bolumlere ayrilmis, varsayilan zincir, blog yazilari.
 *
 * @return array{sections:array, default:array, counts:array, posts:array, home:string}
 */
function nwcs_seo_share_report( int $blog_id ): array {
	switch_to_blog( $blog_id );

	$manifest = nwcs_manifest();
	$sections = array();
	$counts   = array( 'pages' => 0, 'own' => 0, 'default' => 0 );
	$paths    = array();

	foreach ( (array) ( $manifest['pages'] ?? array() ) as $key => $page ) {
		if ( in_array( $key, array( 'global', NWCS_SEO_SITE_PAGE, 'notfound' ), true )
			|| empty( $page['path'] )
			|| false === ( $page['seo'] ?? true )
			|| ! isset( $page['components'][ NWCS_SEO_COMPONENT ] ) ) {
			continue;
		}

		$hidden = ! empty( $page['hidden'] );
		$label  = (string) ( $page['label'] ?? $key );
		$share  = nwcs_seo_share_image_for_page( $key, $manifest );

		// Bolum: "Kategori: Kamelya" -> Kategori; gorunur sayfalar "Sayfalar".
		if ( ! $hidden ) {
			$section = 'Sayfalar';
			$name    = nwcs_short_page_label( $label );
		} elseif ( preg_match( '/^([^:]+):\s*(.+)$/u', $label, $m ) ) {
			$section = trim( $m[1] );
			$name    = trim( $m[2] );
		} else {
			$section = 'Diğer sayfalar';
			$name    = $label;
		}

		$sections[ $section ][ $key ] = array(
			'key'    => $key,
			'name'   => $name,
			'url'    => home_url( $page['path'] ),
			'hidden' => $hidden,
			'share'  => $share,
		);

		$paths[ untrailingslashit( (string) wp_parse_url( home_url( $page['path'] ), PHP_URL_PATH ) ) ] = true;
		++$counts['pages'];

		if ( in_array( $share['source'], array( 'own', 'product' ), true ) ) {
			++$counts['own'];
		} elseif ( in_array( $share['source'], array( 'default', 'logo', 'home', 'theme', 'none' ), true ) ) {
			++$counts['default'];
		}
	}

	// Gorunur sayfalar once, gizli gruplar manifest sirasiyla, "Diger" en sonda.
	$rank = static fn( string $title ): int => 'Sayfalar' === $title ? 0 : ( 'Diğer sayfalar' === $title ? 2 : 1 );
	uksort( $sections, static fn( string $a, string $b ): int => $rank( $a ) <=> $rank( $b ) );

	// Manifestte sayfasi olmayan blog yazilari: kapak gorseli Yazilar ekranindan.
	$posts = array();

	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 50 ) ) as $post ) {
		$url = (string) get_permalink( $post );

		if ( isset( $paths[ untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ] ) ) {
			continue;
		}

		$thumb   = (int) get_post_thumbnail_id( $post );
		$posts[] = array(
			'title' => nwcs_seo_clean( get_the_title( $post ) ),
			'url'   => $url,
			'edit'  => (string) get_edit_post_link( $post, 'raw' ),
			'image' => $thumb ? nwcs_seo_image( $thumb ) : nwcs_seo_share_fallback()['image'],
			'own'   => $thumb > 0,
		);
	}

	$default_id = (int) nwcs_field( NWCS_SEO_SITE_PAGE, 'defaults', 'share_image', 0 );
	$report     = array(
		'sections' => $sections,
		'default'  => array(
			'id'       => $default_id,
			'image'    => $default_id ? nwcs_seo_image( $default_id ) : array(),
			'fallback' => nwcs_seo_share_fallback(),
		),
		'counts'   => $counts,
		'posts'    => $posts,
		'home'     => home_url( '/' ),
	);

	restore_current_blog();

	return $report;
}

/**
 * Gorsel olcusu uyarisi: paylasim kartlari 1200 × 630 (1.91:1) ister.
 */
function nwcs_seo_share_size_note( array $image ): string {
	$width  = (int) ( $image['width'] ?? 0 );
	$height = (int) ( $image['height'] ?? 0 );

	if ( ! $width || ! $height ) {
		return '';
	}

	if ( $width < 600 ) {
		return 'Küçük görsel: en az 1200 piksel genişlik önerilir; bulanık görünebilir.';
	}

	$ratio = $width / $height;

	if ( $ratio < 1.3 ) {
		return 'Kare ya da dik görsel: WhatsApp ve LinkedIn üstünü ve altını kırpar. 1200 × 630 yatay görsel önerilir.';
	}

	if ( $ratio > 2.6 ) {
		return 'Çok geniş görsel: kenarları kırpılır. 1200 × 630 önerilir.';
	}

	return '';
}

/**
 * Tek sayfa karti.
 */
function nwcs_render_seo_share_card( int $blog_id, array $manifest, array $card, array $values, array $media ): void {
	$share   = $card['share'];
	$labels  = nwcs_seo_share_source_labels();
	$source  = $share['source'];
	$auto    = $card['hidden'] && ! in_array( $source, array( 'own', 'product' ), true );
	$badge   = $auto
		? array( 'label' => 'Otomatik', 'class' => '', 'hint' => 'Sayfanın kendi görseli yok: tema sayfaya ait bir görsel verir, veremezse site varsayılanı görünür.' )
		: ( $labels[ $source ] ?? $labels['none'] );
	$image   = $share['image'];
	// Logo / tema / gorselsiz durumda rozet zaten uyariyor; olcu notu eklenmez.
	$note    = in_array( $source, array( 'logo', 'theme', 'none' ), true ) ? '' : nwcs_seo_share_size_note( $image );
	$filter  = in_array( $source, array( 'own', 'product' ), true ) ? 'own' : 'auto';
	$fields  = $manifest['pages'][ $card['key'] ]['components'][ NWCS_SEO_COMPONENT ]['fields'] ?? array();
	$only    = array_values( array_intersect( array( 'image', 'share_product' ), array_keys( $fields ) ) );
	$card_id = 'share-' . sanitize_html_class( $card['key'] );
	?>
	<article class="nwcs-share" id="<?php echo esc_attr( $card_id ); ?>" data-nwcs-share data-share-filter="<?php echo esc_attr( $filter ); ?>"
		data-share-text="<?php echo esc_attr( mb_strtolower( $card['name'] . ' ' . $card['url'] ) ); ?>">
		<div class="nwcs-share__preview" data-nwcs-share-preview>
			<?php if ( ! empty( $image['url'] ) ) : ?>
				<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<span class="nwcs-share__none"><?php echo 'theme' === $source ? 'Temanın yedek görseli' : 'Görsel yok'; ?></span>
			<?php endif; ?>
		</div>

		<div class="nwcs-share__body">
			<h4 class="nwcs-share__name">
				<?php echo esc_html( $card['name'] ); ?>
				<span class="nwcs-badge <?php echo esc_attr( $badge['class'] ); ?>" title="<?php echo esc_attr( $badge['hint'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
			</h4>
			<p class="nwcs-share__meta">
				<?php if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) : ?>
					<span><?php echo (int) $image['width']; ?> × <?php echo (int) $image['height']; ?></span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $card['url'] ); ?>" target="_blank" rel="noopener">Sayfayı aç ↗</a>
			</p>
			<?php if ( '' !== $note ) : ?>
				<p class="nwcs-share__warn"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>

			<details class="nwcs-seo__edit nwcs-share__edit">
				<summary>Değiştir</summary>
				<p class="nwcs-hint nwcs-share__hint"><?php echo esc_html( $badge['hint'] ); ?></p>
				<?php nwcs_seo_render_form( $blog_id, $manifest, $card['key'], NWCS_SEO_COMPONENT, $values, $media, array(), $only ); ?>
			</details>
		</div>
	</article>
	<?php
}

/**
 * Paylasim gorselleri gorunumu.
 */
function nwcs_render_seo_images( int $blog_id, array $site ): void {
	$manifest = $site['manifest'];
	$report   = nwcs_seo_share_report( $blog_id );
	$values   = nwcs_get_all( $blog_id );
	$media    = nwcs_site_media( $blog_id );
	$labels   = nwcs_seo_share_source_labels();
	$default  = $report['default'];
	$fallback = $default['fallback'];
	$counts   = $report['counts'];
	?>
	<div class="nwcs-shares">

		<section class="nwcs-pool__card nwcs-shares__default">
			<div class="nwcs-shares__default-text">
				<h2 class="nwcs-pool__title">Site varsayılanı</h2>
				<p class="nwcs-hint nwcs-seo__lead">
					Bir bağlantı WhatsApp, LinkedIn, Facebook ya da X'te paylaşılınca görünen görsel. Sıra: sayfa için
					seçilen görsel → kategori sayfalarında seçilen ürün fotoğrafı → sayfanın kendi görseli → bu varsayılan.
					En iyi boyut 1200 × 630 yatay.
				</p>
				<p class="nwcs-shares__summary">
					<strong><?php echo (int) $counts['pages']; ?> sayfa</strong>:
					<?php echo (int) $counts['own']; ?> tanesinde elle seçilmiş görsel var,
					<?php echo (int) $counts['default']; ?> tanesi bu varsayılana düşüyor.
					<?php if ( ! $default['id'] ) : ?>
						<span class="nwcs-seo__warn">
							Varsayılan seçilmemiş:
							<?php echo esc_html( 'none' === $fallback['source'] ? 'bu sayfalar görselsiz paylaşılır.' : 'bu sayfalarda ' . mb_strtolower( $labels[ $fallback['source'] ]['label'] ) . ' görünür.' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</div>
			<div class="nwcs-shares__default-form">
				<?php nwcs_seo_render_form( $blog_id, $manifest, NWCS_SEO_SITE_PAGE, 'defaults', $values, $media, array(), array( 'share_image' ) ); ?>
			</div>
		</section>

		<div class="nwcs-shares__tools" data-nwcs-share-tools>
			<div class="nwcs-shares__filters" role="group" aria-label="Süz">
				<button type="button" class="nwcs-shares__filter is-active" data-nwcs-share-filter="">Tümü</button>
				<button type="button" class="nwcs-shares__filter" data-nwcs-share-filter="own">Elle seçilenler</button>
				<button type="button" class="nwcs-shares__filter" data-nwcs-share-filter="auto">Otomatik ve varsayılan</button>
			</div>
			<input type="search" class="nwcs-input nwcs-shares__search" placeholder="Sayfa ara…" aria-label="Sayfa ara" data-nwcs-share-search />
		</div>

		<?php foreach ( $report['sections'] as $title => $cards ) : ?>
			<section class="nwcs-pool__card nwcs-shares__section" data-nwcs-share-section>
				<h2 class="nwcs-pool__title">
					<?php echo esc_html( $title ); ?>
					<span data-nwcs-share-count><?php echo (int) count( $cards ); ?></span>
				</h2>
				<div class="nwcs-shares__grid">
					<?php foreach ( $cards as $card ) : ?>
						<?php nwcs_render_seo_share_card( $blog_id, $manifest, $card, $values, $media ); ?>
					<?php endforeach; ?>
				</div>
				<p class="nwcs-empty nwcs-shares__empty" data-nwcs-share-empty hidden>Bu bölümde eşleşen sayfa yok.</p>
			</section>
		<?php endforeach; ?>

		<?php if ( $report['posts'] ) : ?>
			<section class="nwcs-pool__card nwcs-shares__section" data-nwcs-share-section>
				<h2 class="nwcs-pool__title">
					Blog yazıları
					<span data-nwcs-share-count><?php echo (int) count( $report['posts'] ); ?></span>
				</h2>
				<p class="nwcs-hint nwcs-seo__lead">
					Yazının paylaşım görseli, WordPress yazı düzenleyicisindeki <strong>Öne çıkan görsel</strong>dir;
					yoksa site varsayılanı görünür.
				</p>
				<div class="nwcs-shares__grid">
					<?php foreach ( $report['posts'] as $post ) : ?>
						<article class="nwcs-share" data-nwcs-share data-share-filter="<?php echo $post['own'] ? 'own' : 'auto'; ?>"
							data-share-text="<?php echo esc_attr( mb_strtolower( $post['title'] . ' ' . $post['url'] ) ); ?>">
							<div class="nwcs-share__preview">
								<?php if ( ! empty( $post['image']['url'] ) ) : ?>
									<img src="<?php echo esc_url( $post['image']['url'] ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span class="nwcs-share__none">Görsel yok</span>
								<?php endif; ?>
							</div>
							<div class="nwcs-share__body">
								<h4 class="nwcs-share__name">
									<?php echo esc_html( $post['title'] ); ?>
									<span class="nwcs-badge<?php echo $post['own'] ? ' nwcs-badge--ok' : ''; ?>"><?php echo $post['own'] ? 'Öne çıkan görsel' : 'Site varsayılanı'; ?></span>
								</h4>
								<p class="nwcs-share__meta">
									<a href="<?php echo esc_url( $post['url'] ); ?>" target="_blank" rel="noopener">Yazıyı aç ↗</a>
									<a class="button button-small" href="<?php echo esc_url( $post['edit'] ); ?>">Düzenle</a>
								</p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<p class="nwcs-empty nwcs-shares__empty" data-nwcs-share-empty hidden>Bu bölümde eşleşen yazı yok.</p>
			</section>
		<?php endif; ?>

		<p class="nwcs-hint nwcs-shares__foot">
			Ürün sayfaları kendi fotoğraflarını kullanır; onlar <a href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu</a>’ndan değişir.
			WhatsApp paylaşılmış bir bağlantının eski görselini bir süre hafızasında tutar; yeni görsel yeni paylaşımlarda görünür.
		</p>
	</div>
	<?php
}
