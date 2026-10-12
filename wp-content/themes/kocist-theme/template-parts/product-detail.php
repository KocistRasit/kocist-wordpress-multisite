<?php
/**
 * Urun detay sayfasi (/urun/<slug>/) — Kocist temasi.
 *
 * Ust bolum canlidaki (kocist.com.tr/urun/...) duzen: solda buyuk galeri,
 * sagda teklif formu. Galerinin altinda WOOD KOCIST'teki akordeon: Teknik
 * Detaylar, Urun Aciklamasi, Lojistik ve Teslimat. Veri yuvalari iki sitede
 * ayni havuz alanlarindan dolar (PLAN-kocist-urun-sayfasi.md §2).
 *
 * Kaynak sirasi = telefondaki sira (okuma ve klavye sirasi ayni):
 * galeri → ad / fiyat / kisa aciklama → akordeon → teklif formu.
 * Masaustunde (≥1024) form sag sutunda, adin altinda ve yapisik.
 *
 * Fiyatsiz urunde fiyat yerine "Fiyat teklifle" ve nedeni (kocist_price_reason):
 * neden urune baglidir, ziyaretciye ozel fiyat izlenimi verilmez.
 *
 * Urun videosu (havuzda urun basina tek MP4) galerinin son karesidir: kucuk
 * resmi ana fotografin ustunde oynat isareti; secilince sahnede oynar.
 * <video> sayfaya <template> icinde gelir, ziyaretci secmeden hicbir sey
 * indirilmez. Fotografi olmayan urunde sahnenin kendisi videodur.
 *
 * Form inc/form.php'ye gider (kayit + e-posta bildirimi); basari ya da hata
 * ayni sayfaya #teklif-formu ile doner. Galeri ve akordeon assets/js/product.js,
 * gorunum assets/css/product.css ve form.css.
 */

defined( 'ABSPATH' ) || exit;

/** @var array $product Eklentiden: site istisnalari uygulanmis havuz urunu. */

// Grup ve kategori bilgisi kategori agacindaki kaydindan.
foreach ( kocist_catalog_products() as $candidate ) {
	if ( $candidate['id'] === $product['id'] ) {
		$product = $candidate;
		break;
	}
}

$groups   = kocist_catalog_groups();
$group    = $groups[ $product['group'] ?? '' ] ?? null;
$sub      = $group['subs'][ $product['sub'] ?? '' ] ?? null;
$category = $sub ?? $group;
$preview  = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();

// Galeri: yalnizca havuzdaki gercek gorseller; yoksa isaretli yer tutucu.
$images = array_values( array_filter( (array) ( $product['images'] ?? array() ), static fn( $image ): bool => ! empty( $image['url'] ) && ! kocist_is_placeholder_image( $image ) ) );

$has_photos = (bool) $images;

if ( ! $images ) {
	$images = array( kocist_product_image( $product ) );
}

// Buyuk sahne icin buyuk kopya (havuz 768 px veriyor), buyutmede tam boy.
$images = array_map( static fn( array $image ): array => kocist_pool_image_large( $image, '1536x1536' ), $images );
$main   = $images[0];

// Urun videosu: fotografli urunde galerinin son karesi, fotografsizda sahnenin kendisi.
$video        = ! empty( $product['video']['url'] ) ? $product['video'] : array();
$video_length = $video && function_exists( 'nwcs_video_length_label' ) ? nwcs_video_length_label( (int) $video['length'] ) : '';
$video_label  = $product['title'] . ' videosu';
$slides       = count( $images ) + ( $video && $has_photos ? 1 : 0 );

// WhatsApp: urunun grubunun numarasi ve urun mesaji (inc/quote.php).
$wa_panel = (string) nwcs_field( 'product', 'main', 'secondary_url' );
$wa_href  = kocist_is_whatsapp_url( $wa_panel ) ? kocist_product_wa_url( $product, $wa_panel ) : kocist_link( $wa_panel );

// Ayni kategorideki (yoksa ayni gruptaki) diger urunler, en fazla dort.
$related       = array();
$related_head  = '';
$related_scope = $category;

if ( $group ) {
	$pool = $sub ? kocist_catalog_products_in( $group['slug'], $sub['slug'] ) : array();

	if ( count( $pool ) > 1 ) {
		$related_key  = 'related_sub';
		$related_head = kocist_text( 'product', 'detail', 'related_sub', array( 'kategori' => $sub['name'] ) );
	} else {
		$pool          = kocist_catalog_products_in( $group['slug'] );
		$related_key   = 'related_group';
		$related_head  = kocist_text( 'product', 'detail', 'related_group', array( 'grup' => $group['name'] ) );
		$related_scope = $group;
	}

	$related = array_slice(
		array_values( array_filter( $pool, static fn( array $item ): bool => $item['id'] !== $product['id'] ) ),
		0,
		4
	);
}

/*
 * Akordeon verisi. Hicbir sey uydurulmaz: bos panelde panelden duzenlenen
 * durust bos durum metni gorunur.
 */
$specs    = kocist_product_table_specs( $product );
$code     = trim( (string) ( $product['code'] ?? '' ) );
$body     = trim( (string) $product['body'] ) !== '' ? wp_kses_post( wpautop( $product['body'] ) ) : '';
$split    = function_exists( 'nwcs_product_split_tables' ) ? nwcs_product_split_tables( $body ) : array( 'text' => $body, 'tables' => array() );
$tables   = kocist_product_tables( $product );
$own_ship = function_exists( 'nwcs_product_delivery_row' ) ? nwcs_product_delivery_row( $product ) : '';
$delivery = '' !== $own_ship ? $own_ship : trim( (string) nwcs_field( 'product', 'detail', 'delivery_text' ) );
$has_desc = '' !== trim( wp_strip_all_tags( $split['text'] ) ) || $split['tables'] || $tables;

// Metindeki tablolar urun tablosu gorunumunde basilir.
$inline_table = static function ( string $html ): string {
	return (string) preg_replace( '#<table\b(?![^>]*\bclass=)#i', '<table class="k-ptable__table"', $html, 1 );
};

// Acik baslayan panel: Teknik Detaylar; tabloda koddan baska satir yoksa Urun Aciklamasi.
// Panel onizlemesinde hepsi acik: kapali paneldeki alan da tiklanabilsin.
$first_open = $specs || ! $has_desc ? 'specs' : 'desc';
$panels     = array(
	'specs'    => 'tab_specs',
	'desc'     => 'tab_desc',
	'delivery' => 'tab_delivery',
);

// Fiyat yuvasi: fiyatli urunde fiyat, fiyatsizda rozet + neden.
$reason = $product['has_price'] ? array( 'text' => '', 'field' => '' ) : kocist_price_reason( $product );

// Form durumu (inc/form.php): hata olursa ziyaretcinin yazdiklari geri gelir.
$form_state  = kocist_quote_state();
$form_errors = $form_state['errors'];
$form_value  = static function ( string $key ) use ( $form_state ): string {
	return is_scalar( $form_state['values'][ $key ] ?? null ) ? (string) $form_state['values'][ $key ] : '';
};
$form_field  = static function ( string $key, string $id ) use ( $form_errors ): void {
	if ( ! empty( $form_errors[ $key ] ) ) {
		echo ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"';
	}
};
$form_error  = static function ( string $key, string $id ) use ( $form_errors ): void {
	if ( ! empty( $form_errors[ $key ] ) ) {
		echo '<span class="k-field__error" id="' . esc_attr( $id ) . '-error" role="alert">' . esc_html( $form_errors[ $key ] ) . '</span>';
	}
};

get_header();
?>
<div class="k-wrap k-product__crumbs">
	<?php kocist_the_trail( kocist_catalog_trail( $product['group'] ?? '', $product['sub'] ?? '', $product['title'], (int) $product['id'] ) ); ?>
</div>

<section class="k-product k-product--pool">
	<div class="k-wrap k-pp">

		<div class="k-pp__gallery k-product__gallery" data-k-gallery>
			<?php if ( $slides > 1 ) : ?>
				<div class="k-product__thumbs">
					<?php foreach ( $images as $thumb_index => $thumb ) : ?>
						<button
							type="button"
							class="k-product__thumb<?php echo 0 === $thumb_index ? ' is-active' : ''; ?>"
							data-k-thumb
							data-full="<?php echo esc_url( $thumb['url'] ); ?>"
							data-zoom="<?php echo esc_url( $thumb['full'] ?: $thumb['url'] ); ?>"
							data-alt="<?php echo esc_attr( $thumb['alt'] ?? '' ); ?>"
							aria-label="<?php echo esc_attr( sprintf( '%d. görsel', $thumb_index + 1 ) ); ?>"
						>
							<img src="<?php echo esc_url( $thumb['thumb'] ?: $thumb['url'] ); ?>" alt="<?php echo esc_attr( $thumb['alt'] ?? '' ); ?>" width="64" height="64" decoding="async" />
						</button>
					<?php endforeach; ?>

					<?php if ( $video && $has_photos ) : ?>
						<button
							type="button"
							class="k-product__thumb k-product__thumb--video"
							data-k-thumb
							data-k-video-thumb
							aria-label="<?php echo esc_attr( trim( 'Ürün videosu ' . $video_length ) ); ?>"
						>
							<img src="<?php echo esc_url( $main['thumb'] ?: $main['url'] ); ?>" alt="" width="64" height="64" decoding="async" />
							<span class="k-product__play" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12"><path d="M3 1.6v8.8L10.2 6z" fill="currentColor" /></svg>
							</span>
							<?php if ( '' !== $video_length ) : ?>
								<span class="k-product__thumb-time" aria-hidden="true"><?php echo esc_html( $video_length ); ?></span>
							<?php endif; ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="k-product__stage<?php echo $video && ! $has_photos ? ' k-product__stage--video' : ''; ?>" <?php kocist_product_attr( $product, $video ? 'Görseller ve video' : 'Görseller' ); ?>>
				<?php if ( $video && ! $has_photos ) : ?>
					<?php // Fotograf yok: sahne bir oynat dugmesi; video basinca gelir. ?>
					<button type="button" class="k-product__video-start" data-k-video-start>
						<span class="k-product__play k-product__play--big" aria-hidden="true">
							<svg width="26" height="26" viewBox="0 0 12 12"><path d="M3 1.6v8.8L10.2 6z" fill="currentColor" /></svg>
						</span>
						<span class="k-product__video-start-text">Ürün videosunu izle<?php echo '' !== $video_length ? esc_html( ' (' . $video_length . ')' ) : ''; ?></span>
					</button>
				<?php else : ?>
					<?php echo kocist_image_tag( $main, 'k-product__photo', 'Örnek görsel — ürün' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>

					<?php kocist_zoom_button( $main ); ?>
				<?php endif; ?>

				<?php if ( $video ) : ?>
					<template data-k-video-tpl>
						<video class="k-product__video" src="<?php echo esc_url( $video['url'] ); ?>"<?php echo $has_photos ? ' poster="' . esc_url( $main['url'] ) . '"' : ''; ?> controls playsinline preload="none" aria-label="<?php echo esc_attr( $video_label ); ?>"></video>
					</template>
				<?php endif; ?>

				<?php if ( $slides > 1 ) : ?>
					<button type="button" class="k-product__arrow k-product__arrow--prev" data-k-gallery-prev aria-label="Önceki görsel">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M11 4 6 9l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
					</button>
					<button type="button" class="k-product__arrow k-product__arrow--next" data-k-gallery-next aria-label="Sonraki görsel">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="m7 4 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
					</button>
					<span class="k-product__counter" data-k-gallery-counter aria-live="polite"><?php echo esc_html( '1 / ' . $slides ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="k-pp__head">
			<?php if ( $category ) : ?>
				<p class="k-product__category">
					<a href="<?php echo esc_url( $category['url'] ); ?>" <?php $sub ? kocist_sub_edit_attr( $sub ) : nwcs_edit_attr( 'global', 'header', 'menu', $group['menu_row'], 'label' ); ?>><?php echo esc_html( $category['name'] ); ?></a>
				</p>
			<?php endif; ?>

			<h1 class="k-product__title k-pp__title" <?php kocist_product_attr( $product, 'Ürün adı' ); ?>><?php echo esc_html( $product['title'] ); ?></h1>

			<div class="k-pp__price">
				<?php if ( $product['has_price'] ) : ?>
					<p class="k-pp__amount" <?php kocist_product_attr( $product, 'Fiyat' ); ?>><?php echo esc_html( $product['price'] ); ?></p>
				<?php else : ?>
					<p class="k-pp__badge" <?php nwcs_edit_attr( 'product', 'detail', 'price_badge' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'price_badge' ) ); ?></p>
					<?php if ( '' !== $reason['text'] ) : ?>
						<?php list( $reason_component, $reason_field ) = explode( '.', $reason['field'] ); ?>
						<p class="k-pp__reason" <?php nwcs_edit_attr( 'product', $reason_component, $reason_field ); ?>><?php echo esc_html( $reason['text'] ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( '' !== trim( (string) $product['short'] ) ) : ?>
				<p class="k-product__subtitle k-pp__short" <?php kocist_product_attr( $product, 'Kısa açıklama' ); ?>><?php echo esc_html( $product['short'] ); ?></p>
			<?php endif; ?>

			<?php // Telefonda form akordeonun altinda: burada oraya goturen baglanti (masaustunde gizli). ?>
			<p class="k-pp__jump">
				<a class="k-pp__jump-link" href="#teklif-formu" data-k-form-jump>
					<span <?php nwcs_edit_attr( 'product', 'detail', 'form_jump' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'form_jump' ) ); ?></span>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v9.5M4 8.5l4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</a>
				<span class="k-pp__jump-note" <?php nwcs_edit_attr( 'product', 'detail', 'form_jump_note' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'form_jump_note' ) ); ?></span>
			</p>
		</div>

		<?php // Akordeon: basliga basinca paneli acilir, oteki kapanir (assets/js/product.js). JavaScript yoksa hepsi acik. ?>
		<div class="k-pp__details k-acc" data-k-acc>
			<?php foreach ( $panels as $key => $field ) : ?>
				<?php $open = $preview || $first_open === $key; ?>
				<h2 class="k-acc__head">
					<button type="button" class="k-acc__btn" id="k-acc-btn-<?php echo esc_attr( $key ); ?>" data-k-acc-btn
						aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="k-acc-<?php echo esc_attr( $key ); ?>">
						<span <?php nwcs_edit_attr( 'product', 'detail', $field ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', $field ) ); ?></span>
						<span class="k-acc__icon" aria-hidden="true"></span>
					</button>
				</h2>
				<div class="k-acc__panel<?php echo $open ? ' is-open' : ''; ?>" id="k-acc-<?php echo esc_attr( $key ); ?>" role="region" aria-labelledby="k-acc-btn-<?php echo esc_attr( $key ); ?>">
					<?php if ( 'specs' === $key ) : ?>
						<?php if ( '' !== $code || $specs ) : ?>
							<dl class="k-specs-list k-acc__specs" <?php kocist_product_attr( $product, 'Teknik özellikler' ); ?>>
								<?php if ( '' !== $code ) : ?>
									<?php // Ilk satir urun kodu: teklif isterken bu kodla sorulur. ?>
									<div class="k-specs-list__code">
										<dt <?php nwcs_edit_attr( 'product', 'detail', 'code_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'code_label' ) ); ?></dt>
										<dd><?php echo esc_html( $code ); ?></dd>
									</div>
								<?php endif; ?>
								<?php foreach ( $specs as $pair ) : ?>
									<div>
										<dt><?php echo esc_html( $pair[0] ); ?></dt>
										<dd><?php echo esc_html( $pair[1] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>
						<?php if ( ! $specs ) : ?>
							<p class="k-acc__empty" <?php nwcs_edit_attr( 'product', 'detail', 'specs_empty' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'specs_empty' ) ); ?></p>
						<?php endif; ?>

					<?php elseif ( 'desc' === $key ) : ?>
						<?php if ( '' !== trim( wp_strip_all_tags( $split['text'] ) ) ) : ?>
							<div class="k-acc__text" <?php kocist_product_attr( $product, 'Detay metni' ); ?>>
								<?php echo $split['text']; // phpcs:ignore WordPress.Security.EscapingOutput -- wp_kses_post ile suzuldu. ?>
							</div>
						<?php endif; ?>

						<?php if ( $split['tables'] || $tables || $preview ) : ?>
							<div class="k-acc__tables">
								<?php foreach ( $split['tables'] as $table_html ) : ?>
									<div class="k-ptable" <?php kocist_product_attr( $product, 'Detay metni' ); ?>>
										<div class="k-ptable__scroll" role="region" aria-label="<?php echo esc_attr( $product['title'] ); ?> tablosu" tabindex="0">
											<?php echo wp_kses_post( $inline_table( $table_html ) ); ?>
										</div>
									</div>
								<?php endforeach; ?>

								<?php foreach ( $tables as $table ) : ?>
									<?php kocist_render_product_table( $table, $product ); ?>
								<?php endforeach; ?>

								<?php if ( ! $tables && $preview ) : ?>
									<?php // Yalnizca panel onizlemesinde: tiklaninca urun Urun Havuzu'nda acilir. ?>
									<div class="k-ptable k-ptable--empty" <?php kocist_product_attr( $product, 'Ürün tablosu' ); ?>>
										<p class="k-ptable__empty-title">Bu ürüne tablo ekleyin</p>
										<p class="k-ptable__empty-text">Tıklayın; ürün Ürün Havuzu'nda açılır. “Ürün Tabloları” bölümünden tabloyu hücre hücre doldurun. Bu kutu yalnızca panelde görünür.</p>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! $has_desc ) : ?>
							<p class="k-acc__empty" <?php nwcs_edit_attr( 'product', 'detail', 'desc_empty' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'desc_empty' ) ); ?></p>
						<?php endif; ?>

					<?php else : ?>
						<?php if ( '' !== $delivery ) : ?>
							<div class="k-acc__text" <?php '' !== $own_ship ? kocist_product_attr( $product, 'Teknik özellikler' ) : nwcs_edit_attr( 'product', 'detail', 'delivery_text' ); ?>>
								<?php echo kocist_paragraphs( $delivery ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<?php kocist_section( 'product-faq-link', array( 'group' => $group['slug'] ?? '' ) ); ?>
		</div>

		<div class="k-pp__form" id="teklif-formu">
			<div class="k-qform">
				<h2 class="k-qform__title" <?php nwcs_edit_attr( 'product', 'detail', 'form_title' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'form_title' ) ); ?></h2>
				<p class="k-qform__for">
					<span class="k-qform__for-label" <?php nwcs_edit_attr( 'product', 'whatsapp', 'quote_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'whatsapp', 'quote_label' ) ); ?></span>
					<span class="k-qform__for-name"><?php echo esc_html( $product['title'] ); ?><?php echo '' !== $code ? ' (' . esc_html( $code ) . ')' : ''; ?></span>
				</p>

				<?php if ( $form_state['success'] ) : ?>
					<div class="k-contact__notice k-contact__notice--ok" role="status" tabindex="-1" data-k-form-notice <?php nwcs_edit_attr( 'contact', 'form', 'success_msg' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'success_msg' ) ); ?></div>
				<?php elseif ( ! empty( $form_errors['form'] ) ) : ?>
					<div class="k-contact__notice k-contact__notice--err" role="alert" tabindex="-1" data-k-form-notice><?php echo esc_html( $form_errors['form'] ); ?></div>
				<?php endif; ?>

				<form class="k-qform__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="kc_quote" />
					<?php wp_nonce_field( 'kc_quote', 'kc_quote_nonce', false ); ?>
					<input type="hidden" name="kc_product" value="<?php echo esc_attr( (string) ( $product['slug'] ?? '' ) ); ?>" />
					<input type="hidden" name="kc_form" value="product" />
					<input type="hidden" name="kc_return" value="<?php echo esc_url( (string) $product['url'] ); ?>" />
					<?php /* Bot tuzagi: ekranda gorunmez, insan doldurmaz. */ ?>
					<div class="k-hp" aria-hidden="true">
						<label for="kc-website">Web sitesi</label>
						<input type="text" id="kc-website" name="kc_website" tabindex="-1" autocomplete="off" />
					</div>

					<div class="k-field">
						<label for="kc-name" <?php nwcs_edit_attr( 'contact', 'form', 'name_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'name_label' ) ); ?></label>
						<input type="text" id="kc-name" name="kc-name" autocomplete="name" required value="<?php echo esc_attr( $form_value( 'name' ) ); ?>"<?php $form_field( 'name', 'kc-name' ); ?> />
						<?php $form_error( 'name', 'kc-name' ); ?>
					</div>

					<div class="k-field">
						<label for="kc-company" <?php nwcs_edit_attr( 'product', 'detail', 'company_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'company_label' ) ); ?></label>
						<input type="text" id="kc-company" name="kc-company" autocomplete="organization" value="<?php echo esc_attr( $form_value( 'company' ) ); ?>" />
					</div>

					<div class="k-field">
						<label for="kc-phone" <?php nwcs_edit_attr( 'contact', 'form', 'phone_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'phone_label' ) ); ?></label>
						<input type="tel" id="kc-phone" name="kc-phone" autocomplete="tel" value="<?php echo esc_attr( $form_value( 'phone' ) ); ?>"<?php $form_field( 'phone', 'kc-phone' ); ?> />
						<?php $form_error( 'phone', 'kc-phone' ); ?>
					</div>

					<div class="k-field">
						<label for="kc-email" <?php nwcs_edit_attr( 'contact', 'form', 'email_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'email_label' ) ); ?></label>
						<input type="email" id="kc-email" name="kc-email" autocomplete="email" value="<?php echo esc_attr( $form_value( 'email' ) ); ?>"<?php $form_field( 'email', 'kc-email' ); ?> />
						<?php $form_error( 'email', 'kc-email' ); ?>
					</div>

					<div class="k-field k-field--wide">
						<label for="kc-qty" <?php nwcs_edit_attr( 'product', 'detail', 'qty_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'detail', 'qty_label' ) ); ?></label>
						<textarea id="kc-qty" name="kc-qty" rows="2" required <?php nwcs_edit_attr( 'product', 'detail', 'qty_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'product', 'detail', 'qty_ph' ) ); ?>"<?php $form_field( 'qty', 'kc-qty' ); ?>><?php echo esc_textarea( $form_value( 'qty' ) ); ?></textarea>
						<?php $form_error( 'qty', 'kc-qty' ); ?>
					</div>

					<?php kocist_consent_field( (string) ( $form_errors['consent'] ?? '' ), ! empty( $form_state['values']['consent'] ) ); ?>

					<div class="k-qform__actions">
						<?php // Panel onizlemesinde gonderim yok: metin tiklanabilsin diye type="button". ?>
						<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="k-contact__submit k-qform__submit">
							<span <?php nwcs_edit_attr( 'contact', 'form', 'submit_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'submit_label' ) ); ?></span>
						</button>
						<?php if ( nwcs_field( 'product', 'main', 'secondary_label' ) ) : ?>
							<a class="k-product__btn k-product__btn--ghost k-qform__wa" href="<?php echo esc_url( $wa_href ); ?>"<?php echo kocist_is_whatsapp_url( $wa_href ) ? ' target="_blank" rel="noopener"' : ''; ?>>
								<?php nwcs_the_icon( 'whatsapp', 'k-qform__wa-icon', 18 ); ?>
								<span <?php nwcs_edit_attr( 'product', 'main', 'secondary_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'main', 'secondary_label' ) ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>
	</div>
</section>

<?php if ( $related ) : ?>
	<section class="k-section k-related">
		<div class="k-wrap">
			<div class="k-related__head">
				<h2 class="k-related__title" <?php nwcs_edit_attr( 'product', 'detail', $related_key ); ?>><?php echo esc_html( $related_head ); ?></h2>
				<a class="k-shelf__all" href="<?php echo esc_url( $related_scope['url'] ); ?>" <?php nwcs_edit_attr( 'product', 'detail', 'see_all' ); ?>>
					<?php echo esc_html( nwcs_field( 'product', 'detail', 'see_all' ) ); ?>
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</a>
			</div>

			<ul class="k-plist">
				<?php foreach ( $related as $item ) : ?>
					<?php $has_page = kocist_product_has_page( $item ); ?>
					<li class="k-pcard">
						<a class="k-pcard__link" href="<?php echo esc_url( $has_page ? $item['url'] : kocist_quote_url( $item ) ); ?>">
							<span class="k-pcard__media" <?php kocist_product_attr( $item, 'Görsel' ); ?>>
								<?php echo kocist_image_tag( kocist_product_image( $item ), 'k-pcard__img', 'Örnek görsel' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
							</span>
							<span class="k-pcard__body">
								<span class="k-pcard__title" <?php kocist_product_attr( $item, 'Ürün adı' ); ?>><?php echo esc_html( $item['title'] ); ?></span>
								<span class="k-pcard__foot">
									<?php if ( $item['has_price'] ) : ?>
										<span class="k-pcard__price" <?php kocist_product_attr( $item, 'Fiyat' ); ?>><?php echo esc_html( $item['price_label'] ); ?></span>
									<?php else : ?>
										<span class="k-pcard__price is-quote" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'price_quote' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', 'price_quote' ) ); ?></span>
									<?php endif; ?>
									<span class="k-pcard__go" <?php nwcs_edit_attr( 'kategoriler', 'texts', $has_page ? 'go_detail' : 'go_quote' ); ?>><?php echo esc_html( nwcs_field( 'kategoriler', 'texts', $has_page ? 'go_detail' : 'go_quote' ) ); ?></span>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
