<?php
/**
 * Urun sayfasi (/urun/<slug>/). Eklenti (nwcs_product_template) bu dosyayi
 * $product ile cagirir: site istisnalari uygulanmis havuz urunu.
 *
 * Duzen canli woodkocist.com.tr urun sayfasinin birebir kopyasi (firmanin
 * istegi: "degistirmeyin"); tum urunlerde ayni iskelet:
 *   konum -> [ozet | galeri] -> ozetin altinda 4 basliklik akordeon -> ilgili urunler.
 * Solda: kod + ad (h1), fiyat, kisa aciklama, adet + "Sepete ekle" (fiyatsiz
 * urunde "Fiyat icin sorun" + form), yesil "Hizli Siparis" (WhatsApp) ve
 * akordeon: Teknik Detaylar (acik), Urun Aciklamasi, Lojistik ve Teslimat,
 * Musteri Gorusleri. Ayni anda tek panel acik; JavaScript yoksa hepsi acik.
 * Sagda buyuk galeri, kucuk resimler gorselin ustune bindirilmis (yoksa kod
 * plakasi). Gorsel kendi oraninda, cercevesiz; fareyle uzerine gelince
 * imlecin oldugu yer buyur, tiklayinca tam ekran (assets/site.js).
 */

defined( 'ABSPATH' ) || exit;

/** @var array $product */

foreach ( wk_products() as $candidate ) {
	if ( $candidate['id'] === $product['id'] ) {
		$product = $candidate;
		break;
	}
}

$heading  = wk_product_heading( $product );
$gallery  = wk_gallery( $product );
$specs    = wk_product_table_specs( $product );
$delivery = wk_product_delivery_text( $product );
$wa       = wk_whatsapp( wk_order_text( $product ) );
// Formlar ?urun=<havuz no> ile acilir; urun sunucuda cozulur ve form dolu gelir.
$contact = add_query_arg( 'urun', (int) $product['id'], wk_page_url( 'iletisim' ) ) . '#talep';
$custom  = add_query_arg( 'urun', (int) $product['id'], wk_page_url( 'ozel-uretim-talep-formu' ) ) . '#talep';
$place   = wk_product_place( $product );
$group   = $place['child'] ?? $place['line'];
$buy     = wk_can_buy( $product );

// Urun Aciklamasi: detay metni duz paragraflar; metindeki tablolar panelin sonunda.
$body  = wp_kses_post( wpautop( (string) $product['body'] ) );
$split = function_exists( 'nwcs_product_split_tables' ) ? nwcs_product_split_tables( $body ) : array( 'text' => $body, 'tables' => array() );

// Mobil WhatsApp cubugu (footer.php) bu urunun mesajini kullansin.
$GLOBALS['wk_current_product'] = $product;

$related = array_slice(
	array_values(
		array_filter(
			wk_products(),
			static fn( array $p ): bool => $p['id'] !== $product['id'] && ( ! $group || isset( $p['categories'][ $group['slug'] ] ) )
		)
	),
	0,
	4
);

// Icerik Studyosu onizlemesinde dort panel acik baslar: kapali paneldeki alan
// (Lojistik metni, bos durum metinleri) tiklanip vurgulanabilsin. On yuz etkilenmez.
$preview = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();

// Akordeon panelleri: kimlik => panel alani (baslik onizlemede bu alani acar).
$tabs = array(
	'specs'    => 'specs_title',
	'desc'     => 'tab_desc',
	'delivery' => 'tab_delivery',
	'reviews'  => 'tab_reviews',
);

get_header();
?>

<nav class="wk-wrap wk-crumbs wk-crumbs--product" aria-label="Konum">
	<ol>
		<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php nwcs_edit_attr( 'global', 'legal', 'crumb_home' ); ?>><?php echo esc_html( nwcs_field( 'global', 'legal', 'crumb_home' ) ); ?></a></li>
		<li><a href="<?php echo esc_url( wk_shop_url() ); ?>" <?php nwcs_edit_attr( 'global', 'legal', 'crumb_shop' ); ?>><?php echo esc_html( nwcs_field( 'global', 'legal', 'crumb_shop' ) ); ?></a></li>
		<?php if ( $place['line'] ) : ?>
			<li><a href="<?php echo esc_url( $place['line']['url'] ); ?>" <?php nwcs_edit_attr( 'home', 'catalog', 'lines', (int) $place['line']['row'], 'label' ); ?>><?php echo esc_html( $place['line']['label'] ); ?></a></li>
		<?php endif; ?>
		<?php if ( $place['child'] ) : ?>
			<?php $child_page = wk_category_page_key( $place['child']['slug'] ); ?>
			<li><a href="<?php echo esc_url( $place['child']['url'] ); ?>" <?php $child_page && nwcs_edit_attr( $child_page, 'head', 'name' ); ?>><?php echo esc_html( $place['child']['label'] ); ?></a></li>
		<?php endif; ?>
		<li aria-current="page" <?php wk_product_src( $product, 'Ürün adı' ); ?>><?php echo esc_html( $heading ); ?></li>
	</ol>
</nav>

<article class="wk-wrap wk-product">
	<div class="wk-product__media" data-wk-gallery <?php wk_product_src( $product, 'Görsel' ); ?>>
		<?php if ( $gallery ) : ?>
			<?php if ( count( $gallery ) > 1 ) : ?>
				<?php // Canlidaki gibi: kucuk resimler buyuk gorselin ustune bindirilmis, ortali bir sira. ?>
				<ul class="wk-product__thumbs" aria-label="<?php echo esc_attr( $product['title'] ); ?> görselleri">
					<?php foreach ( $gallery as $index => $item ) : ?>
						<li>
							<button type="button" class="wk-product__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
								data-wk-thumb
								data-src="<?php echo esc_url( $item['src'] ); ?>"
								data-srcset="<?php echo esc_attr( $item['srcset'] ); ?>"
								data-width="<?php echo (int) $item['width']; ?>" data-height="<?php echo (int) $item['height']; ?>"
								data-alt="<?php echo esc_attr( $item['alt'] ?: $product['title'] ); ?>"
								aria-label="<?php echo esc_attr( sprintf( 'Görsel %d', $index + 1 ) ); ?>"
								aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>">
								<?php // Alt metin arama motorlari icin; ekran okuyucu dugmenin adini okur. ?>
								<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" width="60" height="60" loading="lazy" decoding="async" />
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php $first = $gallery[0]; ?>
			<?php // Fareyle uzerine gelince imlecin oldugu yer buyur; tiklayinca tam ekran (assets/site.js). ?>
			<button type="button" class="wk-product__stage" data-wk-stage data-full="<?php echo esc_url( $first['src'] ); ?>"
				aria-label="<?php echo esc_attr( $product['title'] ); ?> görselini tam ekranda aç">
				<img class="wk-product__photo" data-wk-photo src="<?php echo esc_url( $first['src'] ); ?>"
					<?php if ( $first['srcset'] ) : ?>srcset="<?php echo esc_attr( $first['srcset'] ); ?>" sizes="(min-width: 1280px) 740px, (min-width: 1100px) 58vw, 100vw"<?php endif; ?>
					<?php if ( $first['width'] && $first['height'] ) : ?>width="<?php echo (int) $first['width']; ?>" height="<?php echo (int) $first['height']; ?>"<?php endif; ?>
					alt="<?php echo esc_attr( $first['alt'] ?: $product['title'] ); ?>" fetchpriority="high" decoding="async" />
			</button>

			<dialog class="wk-lb" data-wk-lb aria-label="<?php echo esc_attr( $product['title'] ); ?>">
				<div class="wk-lb__stage" data-wk-lb-stage>
					<img class="wk-lb__img" data-wk-lb-img src="" alt="<?php echo esc_attr( $product['title'] ); ?>" />
				</div>
				<p class="wk-lb__hint" data-wk-lb-hint>Yakınlaştırmak için görsele tıklayın; yakınken fareyle gezinin.</p>
				<?php if ( count( $gallery ) > 1 ) : ?>
					<button type="button" class="wk-lb__nav wk-lb__nav--prev" data-wk-lb-prev aria-label="Önceki görsel"><span aria-hidden="true">‹</span></button>
					<button type="button" class="wk-lb__nav wk-lb__nav--next" data-wk-lb-next aria-label="Sonraki görsel"><span aria-hidden="true">›</span></button>
					<p class="wk-lb__count" data-wk-lb-count aria-live="polite"></p>
				<?php endif; ?>
				<button type="button" class="wk-lb__close" data-wk-lb-close aria-label="Kapat"><span aria-hidden="true">×</span></button>
			</dialog>
		<?php else : ?>
			<span class="wk-plate wk-plate--lg" aria-hidden="true"><?php echo esc_html( $product['code'] ); ?></span>
		<?php endif; ?>
	</div>

	<div class="wk-product__info">
		<h1 class="wk-product__title" <?php wk_product_src( $product, 'Ürün adı' ); ?>><?php echo esc_html( $heading ); ?></h1>

		<?php if ( $buy ) : ?>
			<?php // "5.500 ₺": tutar buyuk, para birimi ve KDV notu kucuk (canlidaki gibi). ?>
			<p class="wk-product__price"><span class="wk-product__amount wk-num" <?php wk_product_src( $product, 'Fiyat' ); ?>><?php echo str_replace( '₺', '<span class="wk-product__cur">₺</span>', esc_html( $product['price'] ) ); // phpcs:ignore WordPress.Security.EscapingOutput ?></span> <small <?php wk_price_note_attr(); ?>><?php echo esc_html( wk_price_note() ); ?></small></p>
		<?php else : ?>
			<p class="wk-product__price wk-product__price--ask" <?php nwcs_edit_attr( 'global', 'card', 'price_ask' ); ?>><?php echo esc_html( nwcs_field( 'global', 'card', 'price_ask' ) ); ?></p>
			<?php // Fiyatin neden gosterilmedigi: urune bagli (siparis uzerine uretim), kisiye ozel fiyat izlenimi yok. ?>
			<?php if ( '' !== trim( (string) nwcs_field( 'global', 'card', 'price_reason' ) ) ) : ?>
				<p class="wk-product__reason" <?php nwcs_edit_attr( 'global', 'card', 'price_reason' ); ?>><?php echo esc_html( nwcs_field( 'global', 'card', 'price_reason' ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $product['short'] ) ) : ?>
			<p class="wk-product__short" <?php wk_product_src( $product, 'Kısa açıklama' ); ?>><?php echo esc_html( $product['short'] ); ?></p>
		<?php endif; ?>

		<div class="wk-product__buy">
			<?php if ( $buy ) : ?>
				<?php wk_add_to_cart_form( $product, true ); ?>
			<?php endif; ?>
			<?php if ( $wa ) : ?>
				<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" class="wk-product__order" <?php nwcs_edit_attr( 'product', 'labels', 'wa_order' ); ?>>
					<?php echo wk_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					<?php echo esc_html( nwcs_field( 'product', 'labels', 'wa_order' ) ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! $buy ) : ?>
				<?php // Fiyatsiz urun: WhatsApp'in yaninda formla sorma (form urunle dolu acilir). ?>
				<a href="<?php echo esc_url( $contact ); ?>" class="wk-btn wk-btn--line wk-btn--lg wk-product__ask" <?php nwcs_edit_attr( 'product', 'labels', 'form_ask' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'form_ask' ) ); ?></a>
			<?php endif; ?>
		</div>

		<?php // Akordeon: basliga basinca paneli acilir, oteki kapanir (assets/site.js). JavaScript yoksa hepsi acik. ?>
		<div class="wk-tabs" data-wk-tabs>
			<?php foreach ( $tabs as $key => $field ) : ?>
				<?php $open = $preview || 'specs' === $key; ?>
				<h2 class="wk-tabs__head">
					<button type="button" class="wk-tabs__btn" id="wk-tab-<?php echo esc_attr( $key ); ?>" data-wk-tab
						aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="wk-panel-<?php echo esc_attr( $key ); ?>">
						<span <?php nwcs_edit_attr( 'product', 'labels', $field ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', $field ) ); ?></span>
						<span class="wk-tabs__icon" aria-hidden="true"></span>
					</button>
				</h2>
				<div class="wk-tabs__panel<?php echo $open ? ' is-open' : ''; ?>" id="wk-panel-<?php echo esc_attr( $key ); ?>" role="region" aria-labelledby="wk-tab-<?php echo esc_attr( $key ); ?>">
					<?php if ( 'specs' === $key ) : ?>
						<table class="wk-attrs" <?php wk_product_src( $product, 'Teknik özellikler' ); ?>>
							<?php if ( '' !== (string) $product['code'] ) : ?>
								<tr>
									<th scope="row" <?php nwcs_edit_attr( 'product', 'labels', 'code_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'code_label' ) ); ?></th>
									<td><?php echo esc_html( $product['code'] ); ?></td>
								</tr>
							<?php endif; ?>
							<?php foreach ( $specs as $pair ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $pair[0] ); ?></th>
									<td><?php echo esc_html( $pair[1] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
						<?php if ( ! $specs ) : ?>
							<p class="wk-tabs__empty" <?php nwcs_edit_attr( 'product', 'labels', 'specs_empty' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'specs_empty' ) ); ?></p>
						<?php endif; ?>

					<?php elseif ( 'desc' === $key ) : ?>
						<?php if ( '' !== trim( wp_strip_all_tags( $split['text'] ) ) || $split['tables'] ) : ?>
							<div class="wk-tabs__text" <?php wk_product_src( $product, 'Detay metni' ); ?>>
								<?php echo $split['text']; // phpcs:ignore WordPress.Security.EscapingOutput -- wp_kses_post ile suzuldu. ?>
								<?php foreach ( $split['tables'] as $table_html ) : ?>
									<div class="wk-tabs__table" role="region" aria-label="<?php echo esc_attr( $product['title'] ); ?> tablosu" tabindex="0"><?php echo wp_kses_post( $table_html ); ?></div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="wk-tabs__empty" <?php nwcs_edit_attr( 'product', 'labels', 'desc_empty' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'desc_empty' ) ); ?></p>
						<?php endif; ?>

					<?php elseif ( 'delivery' === $key ) : ?>
						<?php if ( '' !== $delivery['text'] ) : ?>
							<blockquote class="wk-tabs__quote" <?php
						if ( $delivery['own'] ) {
							wk_product_src( $product, 'Teknik özellikler' ); // Urunun kendi "Lojistik ve Teslimat" satiri.
						} else {
							nwcs_edit_attr( 'product', 'labels', 'delivery_text' );
						}
						?>><?php echo wk_paragraphs( $delivery['text'] ); // phpcs:ignore WordPress.Security.EscapingOutput ?></blockquote>
						<?php endif; ?>
						<p class="wk-tabs__custom">
							<span <?php nwcs_edit_attr( 'product', 'labels', 'custom_question' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'custom_question' ) ); ?></span>
							<a href="<?php echo esc_url( $custom ); ?>" <?php nwcs_edit_attr( 'product', 'labels', 'custom_link' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'custom_link' ) ); ?></a>
						</p>

					<?php else : ?>
						<p class="wk-tabs__empty" <?php nwcs_edit_attr( 'product', 'labels', 'reviews_empty' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'reviews_empty' ) ); ?></p>
						<p class="wk-tabs__note" <?php nwcs_edit_attr( 'product', 'labels', 'reviews_note' ); ?>><?php echo esc_html( nwcs_field( 'product', 'labels', 'reviews_note' ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</article>

<?php if ( $related ) : ?>
	<section class="wk-related" aria-labelledby="wk-related-title">
		<div class="wk-wrap">
			<h2 id="wk-related-title" class="wk-related__title" <?php nwcs_edit_attr( 'product', 'labels', $group ? 'related_title' : 'related_all' ); ?>><?php echo esc_html( $group ? wk_text( 'product', 'labels', 'related_title', array( 'kategori' => mb_strtolower( $group['label'] ) ) ) : nwcs_field( 'product', 'labels', 'related_all' ) ); ?></h2>
			<?php
			// Karta tiklayinca bu kategorinin (yoksa Magaza'nin) urun listesi acilir; ad ve fiyat orada siteye ozel.
			$related_page = $group ? wk_category_page_key( $group['slug'] ) : '';
			$related_edit = $related_page ? array( $related_page, 'products', 'pool' ) : array( 'shop', 'pool', 'pool' );
			?>
			<div class="wk-grid wk-grid--4">
				<?php foreach ( $related as $item ) : ?>
					<?php wk_part( 'product-card', array( 'product' => $item, 'heading' => 'h3', 'edit' => $related_edit ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
