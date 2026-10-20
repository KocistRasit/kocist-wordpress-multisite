<?php
/**
 * Teklif sepeti sayfasi (/teklif-sepeti/, inc/basket.php).
 *
 * Solda (telefonda ustte) urun satirlari: kucuk resim, kod, ad, varsa fiyat
 * (toplam yok), adet (− +), "Çıkar". Tek form: betik yoksa +/− ve "Çıkar"
 * formu gonderir, yazilan adet "Adetleri güncelle" ile kaydedilir; betik
 * varsa adet degisince form kendisi gonderilir (assets/js/teklif.js).
 * Sagda iki gonderme yolu: WhatsApp (hazir mesaj, form yok) ve e-posta formu
 * ("Teklif Talepleri" + bildirim). Basari ya da hata ayni sayfaya ?kc= ile
 * doner (#teklif-gonder); hatada yazilanlar ve sepet korunur.
 * Gorunum category.css (konum yolu, bos durum), product.css + form.css
 * (butonlar, form alanlari) ve teklif.css.
 */

defined( 'ABSPATH' ) || exit;

$state   = kocist_quote_state();
$errors  = $state['errors'];
$basket  = kocist_basket();
$lines   = $state['success'] ? array() : $basket['lines'];
$removed = kocist_basket_pruned() + $basket['removed'];
$preview = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
$wa_href = $lines ? kocist_basket_wa_url( $lines ) : '';
$wa_href = str_starts_with( $wa_href, 'https://wa.me/' ) ? $wa_href : '';

$value = static function ( string $key ) use ( $state ): string {
	return is_scalar( $state['values'][ $key ] ?? null ) ? (string) $state['values'][ $key ] : '';
};
$field = static function ( string $key, string $id ) use ( $errors ): void {
	if ( ! empty( $errors[ $key ] ) ) {
		echo ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"';
	}
};
$error = static function ( string $key, string $id ) use ( $errors ): void {
	if ( ! empty( $errors[ $key ] ) ) {
		echo '<span class="k-field__error" id="' . esc_attr( $id ) . '-error" role="alert">' . esc_html( $errors[ $key ] ) . '</span>';
	}
};

$trail = array(
	array( 'name' => nwcs_field( 'global', 'texts', 'home_crumb' ), 'url' => home_url( '/' ), 'edit' => array( 'global', 'texts', 'home_crumb' ) ),
	array( 'name' => nwcs_field( 'teklif', 'head', 'crumb' ), 'url' => '', 'edit' => array( 'teklif', 'head', 'crumb' ) ),
);
$categories = home_url( '/kategoriler/' );
?>
<div class="k-pagehead k-pagehead--browse k-pagehead--tool">
	<div class="k-wrap">
		<?php kocist_the_trail( $trail, 'k-crumbs--on-dark' ); ?>
		<h1 class="k-pagehead__title" <?php nwcs_edit_attr( 'teklif', 'head', 'title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'head', 'title' ) ); ?></h1>
		<p class="k-pagehead__sub" <?php nwcs_edit_attr( 'teklif', 'head', 'lead' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'head', 'lead' ) ); ?></p>
	</div>
</div>

<section class="k-section k-section--tight k-basket" id="sepet">
	<div class="k-wrap">
		<?php if ( $state['success'] ) : ?>
			<?php // Talep gonderildi: sunucu sepeti bosaltti; betik de cerezi ve ust menudeki sayiyi siler. ?>
			<div class="k-basket__done" id="teklif-gonder" role="status" tabindex="-1" data-k-basket-done data-k-focus>
				<span class="k-basket__done-mark" aria-hidden="true">✓</span>
				<h2 class="k-basket__done-title" <?php nwcs_edit_attr( 'teklif', 'send', 'success_title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'success_title' ) ); ?></h2>
				<p class="k-basket__done-text" <?php nwcs_edit_attr( 'teklif', 'send', 'success_text' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'success_text' ) ); ?></p>
				<div class="k-empty__actions">
					<a class="k-product__btn k-product__btn--primary" href="<?php echo esc_url( $categories ); ?>" <?php nwcs_edit_attr( 'teklif', 'list', 'empty_cta' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'empty_cta' ) ); ?></a>
				</div>
			</div>

		<?php elseif ( ! $lines ) : ?>
			<?php if ( ! empty( $errors['form'] ) ) : ?>
				<div class="k-contact__notice k-contact__notice--err" id="teklif-gonder" role="alert" tabindex="-1" data-k-focus><?php echo esc_html( $errors['form'] ); ?></div>
			<?php endif; ?>
			<?php if ( $removed ) : ?>
				<p class="k-contact__notice k-contact__notice--err" role="status" <?php nwcs_edit_attr( 'teklif', 'list', 'removed' ); ?>><?php echo esc_html( kocist_text( 'teklif', 'list', 'removed', array( 'sayi' => $removed ) ) ); ?></p>
			<?php endif; ?>
			<div class="k-empty k-basket__empty">
				<h2 class="k-empty__title" <?php nwcs_edit_attr( 'teklif', 'list', 'empty_title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'empty_title' ) ); ?></h2>
				<p class="k-empty__text" <?php nwcs_edit_attr( 'teklif', 'list', 'empty_text' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'empty_text' ) ); ?></p>
				<div class="k-empty__actions">
					<a class="k-product__btn k-product__btn--primary" href="<?php echo esc_url( $categories ); ?>" <?php nwcs_edit_attr( 'teklif', 'list', 'empty_cta' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'empty_cta' ) ); ?></a>
				</div>
			</div>

		<?php else : ?>
			<div class="k-basket__grid">
				<div class="k-basket__main">
					<?php if ( $removed ) : ?>
						<p class="k-contact__notice k-contact__notice--err" role="status" <?php nwcs_edit_attr( 'teklif', 'list', 'removed' ); ?>><?php echo esc_html( kocist_text( 'teklif', 'list', 'removed', array( 'sayi' => $removed ) ) ); ?></p>
					<?php endif; ?>

					<h2 class="k-basket__count" <?php nwcs_edit_attr( 'teklif', 'list', 'count' ); ?>><?php echo esc_html( kocist_text( 'teklif', 'list', 'count', array( 'sayi' => count( $lines ) ) ) ); ?></h2>

					<form class="k-basket__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-k-basket-form>
						<input type="hidden" name="action" value="kc_basket" />
						<input type="hidden" name="kc_do" value="update" />
						<?php // Varsayilan dugme: adet kutusunda Enter ilk satirin −'sine basmasin, yalnizca adetler kaydedilsin. ?>
						<button type="submit" class="k-hp" tabindex="-1" aria-hidden="true"><?php echo esc_html( nwcs_field( 'teklif', 'list', 'update' ) ); ?></button>

						<ul class="k-basket__list">
							<?php foreach ( $lines as $line ) : ?>
								<?php
								$product = $line['product'];
								$id      = (int) $product['id'];
								$url     = kocist_basket_product_url( $product );
								$code    = trim( (string) ( $product['code'] ?? '' ) );
								$title   = (string) $product['title'];
								$image   = kocist_pool_image_large( kocist_product_image( $product ), 'thumbnail' );
								?>
								<li class="k-bline">
									<span class="k-bline__media" aria-hidden="true">
										<?php echo kocist_image_tag( array( 'url' => $image['url'] ?? '', 'alt' => '' ), 'k-bline__img' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
									</span>

									<div class="k-bline__body">
										<?php if ( '' !== $code ) : ?>
											<p class="k-bline__code"><?php echo esc_html( $code ); ?></p>
										<?php endif; ?>
										<p class="k-bline__name" <?php kocist_product_attr( $product, 'Ürün adı' ); ?>>
											<?php if ( '' !== $url ) : ?>
												<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $title ); ?>
											<?php endif; ?>
										</p>
										<?php if ( $product['has_price'] ) : ?>
											<p class="k-bline__price" <?php kocist_product_attr( $product, 'Fiyat' ); ?>><?php echo esc_html( (string) $product['price'] ); ?></p>
										<?php else : ?>
											<p class="k-bline__price is-quote" <?php nwcs_edit_attr( 'teklif', 'list', 'price_quote' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'price_quote' ) ); ?></p>
										<?php endif; ?>
									</div>

									<div class="k-bline__qty">
										<label class="screen-reader-text" for="kc-qty-<?php echo (int) $id; ?>"><?php echo esc_html( nwcs_field( 'teklif', 'add', 'qty_label' ) . ': ' . $title ); ?></label>
										<?php kocist_qty_field( array( 'id' => 'kc-qty-' . $id, 'name' => 'kc_qty[' . $id . ']', 'value' => (int) $line['qty'], 'min' => 0, 'label' => $title, 'submit' => $id ) ); ?>
									</div>

									<button type="submit" class="k-bline__remove" name="kc_remove" value="<?php echo (int) $id; ?>">
										<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5" /></svg>
										<span <?php nwcs_edit_attr( 'teklif', 'list', 'remove' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'remove' ) ); ?></span><span class="screen-reader-text">: <?php echo esc_html( $title ); ?></span>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>

						<div class="k-basket__tools">
							<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="k-product__btn k-product__btn--ghost k-basket__update" data-k-basket-update <?php nwcs_edit_attr( 'teklif', 'list', 'update' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'update' ) ); ?></button>
							<a class="k-shelf__all k-basket__more" href="<?php echo esc_url( $categories ); ?>" <?php nwcs_edit_attr( 'teklif', 'list', 'more' ); ?>>
								<?php echo esc_html( nwcs_field( 'teklif', 'list', 'more' ) ); ?>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
							</a>
						</div>
					</form>

					<form class="k-basket__clear" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-k-confirm="<?php echo esc_attr( (string) nwcs_field( 'teklif', 'list', 'clear_ask' ) ); ?>">
						<input type="hidden" name="action" value="kc_basket" />
						<input type="hidden" name="kc_do" value="clear" />
						<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="k-basket__clear-btn" <?php nwcs_edit_attr( 'teklif', 'list', 'clear' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'list', 'clear' ) ); ?></button>
					</form>
				</div>

				<aside class="k-basket__send" aria-labelledby="k-send-title">
					<h2 class="k-basket__send-title" id="k-send-title" <?php nwcs_edit_attr( 'teklif', 'send', 'title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'title' ) ); ?></h2>

					<?php if ( '' !== $wa_href ) : ?>
						<div class="k-send k-send--wa">
							<h3 class="k-send__title" <?php nwcs_edit_attr( 'teklif', 'send', 'wa_title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'wa_title' ) ); ?></h3>
							<p class="k-send__text" <?php nwcs_edit_attr( 'teklif', 'send', 'wa_text' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'wa_text' ) ); ?></p>
							<?php
							/*
							 * esc_attr, esc_url degil: esc_url kodlanmis satir sonlarini (%0A)
							 * siler, mesaj tek satira yapisirdi. Adres burada kuruldu
							 * (kocist_wa_url: https://wa.me/ + rakamlar + rawurlencode metin).
							 */
							?>
							<a class="k-product__btn k-product__btn--ghost k-qform__wa k-send__wa" href="<?php echo esc_attr( $wa_href ); ?>" target="_blank" rel="noopener" data-k-basket-wa>
								<?php nwcs_the_icon( 'whatsapp', 'k-qform__wa-icon', 18 ); ?>
								<span <?php nwcs_edit_attr( 'teklif', 'send', 'wa_button' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'wa_button' ) ); ?></span>
							</a>
						</div>
					<?php endif; ?>

					<div class="k-send k-send--mail" id="teklif-gonder">
						<h3 class="k-send__title" <?php nwcs_edit_attr( 'teklif', 'send', 'mail_title' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'mail_title' ) ); ?></h3>
						<p class="k-send__text" <?php nwcs_edit_attr( 'teklif', 'send', 'mail_text' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'mail_text' ) ); ?></p>

						<?php if ( ! empty( $errors['form'] ) ) : ?>
							<div class="k-contact__notice k-contact__notice--err" role="alert" tabindex="-1" data-k-focus><?php echo esc_html( $errors['form'] ); ?></div>
						<?php endif; ?>

						<form class="k-qform__form k-send__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
							<input type="hidden" name="action" value="kc_basket_send" />
							<?php wp_nonce_field( 'kc_basket_send', 'kc_basket_nonce', false ); ?>
							<?php /* Bot tuzagi: ekranda gorunmez, insan doldurmaz. */ ?>
							<div class="k-hp" aria-hidden="true">
								<label for="kc-website">Web sitesi</label>
								<input type="text" id="kc-website" name="kc_website" tabindex="-1" autocomplete="off" />
							</div>

							<div class="k-field k-field--wide">
								<label for="kc-name" <?php nwcs_edit_attr( 'contact', 'form', 'name_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'name_label' ) ); ?></label>
								<input type="text" id="kc-name" name="kc-name" autocomplete="name" required value="<?php echo esc_attr( $value( 'name' ) ); ?>"<?php $field( 'name', 'kc-name' ); ?> />
								<?php $error( 'name', 'kc-name' ); ?>
							</div>

							<p class="k-send__hint k-field--wide" id="kc-contact-hint" <?php nwcs_edit_attr( 'teklif', 'send', 'contact_hint' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'contact_hint' ) ); ?></p>

							<div class="k-field">
								<label for="kc-phone" <?php nwcs_edit_attr( 'contact', 'form', 'phone_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'phone_label' ) ); ?></label>
								<input type="tel" id="kc-phone" name="kc-phone" autocomplete="tel" value="<?php echo esc_attr( $value( 'phone' ) ); ?>"<?php echo ! empty( $errors['phone'] ) ? ' aria-invalid="true" aria-describedby="kc-phone-error kc-contact-hint"' : ' aria-describedby="kc-contact-hint"'; ?> />
								<?php $error( 'phone', 'kc-phone' ); ?>
							</div>

							<div class="k-field">
								<label for="kc-email" <?php nwcs_edit_attr( 'contact', 'form', 'email_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'email_label' ) ); ?></label>
								<input type="email" id="kc-email" name="kc-email" autocomplete="email" value="<?php echo esc_attr( $value( 'email' ) ); ?>"<?php echo ! empty( $errors['email'] ) ? ' aria-invalid="true" aria-describedby="kc-email-error kc-contact-hint"' : ' aria-describedby="kc-contact-hint"'; ?> />
								<?php $error( 'email', 'kc-email' ); ?>
							</div>

							<div class="k-field k-field--wide">
								<label for="kc-company" <?php nwcs_edit_attr( 'teklif', 'send', 'company_label' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'company_label' ) ); ?></label>
								<input type="text" id="kc-company" name="kc-company" autocomplete="organization" value="<?php echo esc_attr( $value( 'company' ) ); ?>" />
							</div>

							<div class="k-field k-field--wide">
								<label for="kc-note" <?php nwcs_edit_attr( 'teklif', 'send', 'note_label' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'note_label' ) ); ?></label>
								<textarea id="kc-note" name="kc-note" rows="3" placeholder="<?php echo esc_attr( nwcs_field( 'teklif', 'send', 'note_ph' ) ); ?>" <?php nwcs_edit_attr( 'teklif', 'send', 'note_ph' ); ?>><?php echo esc_textarea( $value( 'message' ) ); ?></textarea>
							</div>

							<?php kocist_consent_field( (string) ( $errors['consent'] ?? '' ), ! empty( $state['values']['consent'] ) ); ?>

							<div class="k-qform__actions">
								<?php // Panel onizlemesinde gonderim yok: metin tiklanabilsin diye type="button". ?>
								<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="k-contact__submit k-qform__submit">
									<span <?php nwcs_edit_attr( 'teklif', 'send', 'submit' ); ?>><?php echo esc_html( nwcs_field( 'teklif', 'send', 'submit' ) ); ?></span>
								</button>
							</div>
						</form>
					</div>
				</aside>
			</div>
		<?php endif; ?>
	</div>
</section>
