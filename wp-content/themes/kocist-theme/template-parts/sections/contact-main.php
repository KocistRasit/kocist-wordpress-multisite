<?php
/**
 * Iletisim sayfasi.
 *
 * Duzen: koyu yesil bir baslik bandi, bandin altina binen beyaz iletisim
 * seridi, ardindan solda form sagda harita. Simetrik "kart listesi + form
 * karti" duzeninden kacinildi; agirlik banda ve haritaya verildi.
 *
 * Form gercek gonderim yapar (inc/form.php): kayit olarak saklanir, bildirim
 * info@kocist.com.tr adresine gider. Hata olursa girilen degerler korunur.
 *
 * Harita: ayni tesisteki uc isletme (kereste, ambalaj ve sandik, kamelya ve
 * dekorasyon) haritanin ustunde sekme sekme. Gomme adresi panelden gelmiyor;
 * panelde isletmenin adi, koordinati ve Google kaydi tutuluyor, adres burada
 * kuruluyor. Boylece iframe kaynagi her zaman google.com kalir. Sekmeler
 * assets/js/contact.js; betik yoksa ilk harita gorunur, kartta uc isletme de
 * yol tarifiyle listelenir.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serit ogesi. Degeri bos olan oge hic cizilmez.
 */
$kocist_strip_item = static function ( string $icon, string $title, string $text, string $url = '', string $key = '' ): void {
	if ( '' === trim( $text ) ) {
		return;
	}

	$tag = '' === trim( $url ) ? 'div' : 'a';
	?>
	<<?php echo esc_html( $tag ); ?>
		class="k-strip__item"
		<?php nwcs_edit_attr( 'contact', 'info', $key ); ?>
		<?php if ( 'a' === $tag ) : ?>
			href="<?php echo esc_url( kocist_link( $url ) ); ?>"
		<?php endif; ?>
	>
		<span class="k-strip__label">
			<?php nwcs_the_icon( $icon, 'k-strip__icon', 15 ); ?>
			<?php echo esc_html( $title ); ?>
		</span>
		<span class="k-strip__value"><?php echo esc_html( $text ); ?></span>
	</<?php echo esc_html( $tag ); ?>>
	<?php
};

/*
 * Haritadaki isletmeler (panelde contact.map.places). Adi bos satir atlanir.
 * Eski tek konum alanlari (query, coords) kayitliysa da okunmaz.
 *
 * Gomme adresi (Ekim 2026'da basliksiz Chrome'la, uc isletmeyle denendi):
 *   - Google kayit kodu (0x…:0x…) ve koordinat varsa Google'in kendi "Haritayi
 *     yerlestir" bicimi (/maps/embed?pb=…): harita isletmenin Google kartiyla
 *     (ad, adres, puan) acilir; uc isletmede de dogru kayit.
 *   - Kod yoksa ad + koordinat aramasi (maps?q=<ad>&ll=<koordinat>&z=17):
 *     isletme adiyla isaretli gorunur, ama "Kereste" ve "Ambalaj" tesisteki
 *     diger KOÇİST kayitlariyla birlikte arama sonucu olarak (kartsiz) cikiyor;
 *     yalnizca "Kamelya" kartla aciliyor. Ada ", Kestanelik, Çatalca, İstanbul"
 *     eklemek "Kamelya"yi da coklu sonuca ceviriyordu; eklenmiyor.
 * Ikisinde de koordinatli ciplak isaret yok.
 */
$map_places = array();

foreach ( nwcs_rows( 'contact', 'map', 'places' ) as $row_index => $row ) {
	$row  = is_array( $row ) ? $row : array();
	$name = trim( (string) ( $row['name'] ?? '' ) );

	if ( '' === $name ) {
		continue;
	}

	// Bicim dogrulaniyor; "41.14,28.46" disinda bir sey yazilirsa yok sayilir.
	$coords = trim( (string) ( $row['coords'] ?? '' ) );
	$coords = preg_match( '/^-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/', $coords ) ? preg_replace( '/\s+/', '', $coords ) : '';

	// Yol tarifi yalnizca Google adresine; degilse isletmenin adiyla Google aramasi.
	$link  = trim( (string) ( $row['url'] ?? '' ) );
	$parts = wp_parse_url( $link );
	$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
	$valid = 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
		&& ( 'google.com' === $host || str_ends_with( $host, '.google.com' ) || 'maps.app.goo.gl' === $host || 'goo.gl' === $host );

	if ( ! $valid ) {
		$link = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $name );
	}

	// Google kayit kodu: alana kod ya da isletmenin uzun Google Haritalar adresi yazilabilir.
	$ftid = '';

	foreach ( array( $row['place'] ?? '', $link ) as $source ) {
		if ( preg_match( '/0x[0-9a-f]{1,16}:0x[0-9a-f]{1,16}/i', rawurldecode( (string) $source ), $found ) ) {
			$ftid = strtolower( $found[0] );
			break;
		}
	}

	if ( '' !== $ftid && '' !== $coords ) {
		list( $lat, $lng ) = explode( ',', $coords );

		$embed = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1200!2d' . $lng . '!3d' . $lat
			. '!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s' . str_replace( ':', '%3A', $ftid )
			. '!2s' . rawurlencode( $name ) . '!5e0!3m2!1str!2str';
	} else {
		$embed = 'https://www.google.com/maps?q=' . rawurlencode( $name ) . ( '' !== $coords ? '&ll=' . $coords : '' ) . '&z=17&output=embed';
	}

	$label = trim( (string) ( $row['label'] ?? '' ) );

	$map_places[] = array(
		'row'   => (int) $row_index,
		'label' => '' !== $label ? $label : $name,
		'name'  => $name,
		'link'  => $link,
		'embed' => $embed,
	);
}

// Urunden "Teklif Al" ile gelindiyse (?urun=<slug>) konu ve mesaj o urunle dolar (inc/quote.php).
$prefill = function_exists( 'kocist_quote_prefill' ) ? kocist_quote_prefill() : array( 'product' => null, 'context' => array(), 'subject' => '', 'message' => '' );

// Gonderim sonrasi durum (inc/form.php). Hata varsa ziyaretcinin yazdiklari on dolgunun yerine gecer.
$form_state  = function_exists( 'kocist_quote_state' ) ? kocist_quote_state() : array( 'errors' => array(), 'values' => array(), 'success' => false );
$form_errors = $form_state['errors'];
$form_value  = static function ( string $key, string $fallback = '' ) use ( $form_state ): string {
	return array_key_exists( $key, $form_state['values'] ) ? (string) $form_state['values'][ $key ] : $fallback;
};
$form_error  = static function ( string $key ) use ( $form_errors ): void {
	if ( ! empty( $form_errors[ $key ] ) ) {
		echo '<span class="k-field__error" id="kc-' . esc_attr( $key ) . '-error" role="alert">' . esc_html( $form_errors[ $key ] ) . '</span>';
	}
};
// Hatali alan: ekran okuyucu hatayi alanla birlikte okusun.
$form_field  = static function ( string $key ) use ( $form_errors ): void {
	if ( ! empty( $form_errors[ $key ] ) ) {
		echo ' aria-invalid="true" aria-describedby="kc-' . esc_attr( $key ) . '-error"';
	}
};
?>
<section class="k-contact" data-nwcs-section="head">

	<div class="k-contact__band">
		<div class="k-wrap">
			<p class="k-contact__eyebrow" <?php nwcs_edit_attr( 'contact', 'head', 'eyebrow' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'head', 'eyebrow' ) ); ?></p>
			<h1 class="k-contact__title" <?php nwcs_edit_attr( 'contact', 'head', 'title' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'head', 'title' ) ); ?></h1>
			<p class="k-contact__subtitle" <?php nwcs_edit_attr( 'contact', 'head', 'subtitle' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'head', 'subtitle' ) ); ?></p>
		</div>
	</div>

	<div class="k-wrap">

		<?php /* Bandin altina binen serit: en cok kullanilan dort bilgi. */ ?>
		<div class="k-strip" data-nwcs-section="info">
			<?php
			$kocist_strip_item(
				(string) nwcs_field( 'contact', 'info', 'phone_icon' ),
				(string) nwcs_field( 'contact', 'info', 'phone_title' ),
				(string) nwcs_field( 'contact', 'info', 'phone_label' ),
				(string) nwcs_field( 'contact', 'info', 'phone_url' ),
			'phone_label'
		);

			$kocist_strip_item(
				(string) nwcs_field( 'contact', 'info', 'whatsapp_icon' ),
				(string) nwcs_field( 'contact', 'info', 'whatsapp_title' ),
				(string) nwcs_field( 'contact', 'info', 'whatsapp_label' ),
				(string) nwcs_field( 'contact', 'info', 'whatsapp_url' ),
			'whatsapp_label'
		);

			$kocist_strip_item(
				(string) nwcs_field( 'contact', 'info', 'email_icon' ),
				(string) nwcs_field( 'contact', 'info', 'email_title' ),
				(string) nwcs_field( 'contact', 'info', 'email_label' ),
				(string) nwcs_field( 'contact', 'info', 'email_url' ),
			'email_label'
		);

			$kocist_strip_item(
				(string) nwcs_field( 'contact', 'info', 'hours_icon' ),
				(string) nwcs_field( 'contact', 'info', 'hours_title' ),
				(string) nwcs_field( 'contact', 'info', 'hours' ),
				'',
				'hours'
			);
			?>
		</div>

		<div class="k-contact__grid">

			<div class="k-contact__form-col" id="teklif-formu" data-nwcs-section="form">
				<h2 class="k-contact__h2" <?php nwcs_edit_attr( 'contact', 'form', 'title' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'title' ) ); ?></h2>
				<p class="k-contact__lead" <?php nwcs_edit_attr( 'contact', 'form', 'text' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'text' ) ); ?></p>

				<?php if ( $prefill['product'] ) : ?>
					<?php
					$quote_ctx   = $prefill['context'];
					$quote_image = kocist_product_image( $prefill['product'] );
					?>
					<div class="k-quote-item">
						<span class="k-quote-item__media">
							<?php echo kocist_image_tag( $quote_image, 'k-quote-item__img', '' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
						</span>
						<span class="k-quote-item__body">
							<span class="k-quote-item__label" <?php nwcs_edit_attr( 'product', 'whatsapp', 'quote_label' ); ?>><?php echo esc_html( nwcs_field( 'product', 'whatsapp', 'quote_label' ) ); ?></span>
							<?php if ( '' !== $quote_ctx['url'] ) : ?>
								<a class="k-quote-item__name" href="<?php echo esc_url( $quote_ctx['url'] ); ?>"><?php echo esc_html( $quote_ctx['name'] ); ?></a>
							<?php else : ?>
								<span class="k-quote-item__name"><?php echo esc_html( $quote_ctx['name'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $quote_ctx['category'] ) : ?>
								<span class="k-quote-item__meta"><?php echo esc_html( $quote_ctx['category'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( $form_state['success'] ) : ?>
					<div class="k-contact__notice k-contact__notice--ok" role="status" <?php nwcs_edit_attr( 'contact', 'form', 'success_msg' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'success_msg' ) ); ?></div>
				<?php elseif ( ! empty( $form_errors['form'] ) ) : ?>
					<div class="k-contact__notice k-contact__notice--err" role="alert"><?php echo esc_html( $form_errors['form'] ); ?></div>
				<?php endif; ?>

				<form class="k-contact__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="kc_quote" />
					<?php wp_nonce_field( 'kc_quote', 'kc_quote_nonce', false ); ?>
					<?php if ( $prefill['product'] ) : ?>
						<input type="hidden" name="kc_product" value="<?php echo esc_attr( (string) ( $prefill['product']['slug'] ?? '' ) ); ?>" />
					<?php endif; ?>
					<?php /* Bot tuzagi: ekranda gorunmez, insan doldurmaz. */ ?>
					<div class="k-hp" aria-hidden="true">
						<label for="kc-website">Web sitesi</label>
						<input type="text" id="kc-website" name="kc_website" tabindex="-1" autocomplete="off" />
					</div>
					<div class="k-field">
						<label for="kc-name" <?php nwcs_edit_attr( 'contact', 'form', 'name_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'name_label' ) ); ?></label>
						<input type="text" id="kc-name" name="kc-name" autocomplete="name" <?php nwcs_edit_attr( 'contact', 'form', 'name_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'contact', 'form', 'name_ph' ) ); ?>" value="<?php echo esc_attr( $form_value( 'name' ) ); ?>"<?php $form_field( 'name' ); ?> />
						<?php $form_error( 'name' ); ?>
					</div>

					<div class="k-field">
						<label for="kc-phone" <?php nwcs_edit_attr( 'contact', 'form', 'phone_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'phone_label' ) ); ?></label>
						<input type="tel" id="kc-phone" name="kc-phone" autocomplete="tel" <?php nwcs_edit_attr( 'contact', 'form', 'phone_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'contact', 'form', 'phone_ph' ) ); ?>" value="<?php echo esc_attr( $form_value( 'phone' ) ); ?>"<?php $form_field( 'phone' ); ?> />
						<?php $form_error( 'phone' ); ?>
					</div>

					<div class="k-field">
						<label for="kc-email" <?php nwcs_edit_attr( 'contact', 'form', 'email_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'email_label' ) ); ?></label>
						<input type="email" id="kc-email" name="kc-email" autocomplete="email" <?php nwcs_edit_attr( 'contact', 'form', 'email_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'contact', 'form', 'email_ph' ) ); ?>" value="<?php echo esc_attr( $form_value( 'email' ) ); ?>"<?php $form_field( 'email' ); ?> />
						<?php $form_error( 'email' ); ?>
					</div>

					<div class="k-field">
						<label for="kc-subject" <?php nwcs_edit_attr( 'contact', 'form', 'subject_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'subject_label' ) ); ?></label>
						<input type="text" id="kc-subject" name="kc-subject" autocomplete="off" <?php nwcs_edit_attr( 'contact', 'form', 'subject_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'contact', 'form', 'subject_ph' ) ); ?>" value="<?php echo esc_attr( $form_value( 'subject', $prefill['subject'] ) ); ?>" />
					</div>

					<div class="k-field k-field--wide">
						<label for="kc-detail" <?php nwcs_edit_attr( 'contact', 'form', 'detail_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'detail_label' ) ); ?></label>
						<textarea id="kc-detail" name="kc-detail" rows="5" <?php nwcs_edit_attr( 'contact', 'form', 'detail_ph' ); ?> placeholder="<?php echo esc_attr( nwcs_field( 'contact', 'form', 'detail_ph' ) ); ?>"<?php $form_field( 'message' ); ?>><?php echo esc_textarea( $form_value( 'message', $prefill['message'] ) ); ?></textarea>
						<?php $form_error( 'message' ); ?>
					</div>

					<?php
					// Onay kutusu urun sayfasindaki formla ortak (inc/form.php).
					kocist_consent_field( (string) ( $form_errors['consent'] ?? '' ), ! empty( $form_state['values']['consent'] ) );
					?>

					<div class="k-field k-field--wide k-contact__submit-row">
						<?php
						// Panel onizlemesinde gonderim yok: metin tiklanabilsin diye type="button".
						$submit_type = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ? 'button' : 'submit';
						?>
						<button type="<?php echo esc_attr( $submit_type ); ?>" class="k-contact__submit">
							<span <?php nwcs_edit_attr( 'contact', 'form', 'submit_label' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'submit_label' ) ); ?></span>
						</button>

						<span class="k-contact__demo" <?php nwcs_edit_attr( 'contact', 'form', 'form_note' ); ?>>
							<?php nwcs_the_icon( 'shield', 'k-strip__icon', 15 ); ?>
							<?php echo esc_html( nwcs_field( 'contact', 'form', 'form_note' ) ); ?>
						</span>
					</div>
				</form>
			</div>

			<?php if ( $map_places ) : ?>
				<div class="k-contact__map-col" id="harita" data-nwcs-section="map" data-k-map>
					<?php
					/*
					 * Sekmeler betikle acilir (assets/js/contact.js); betik yoksa
					 * ise yaramayacaklari icin gizli kalir. Kendiliginden donmez:
					 * her geciste harita yeniden yukleniyor.
					 */
					?>
					<?php if ( count( $map_places ) > 1 ) : ?>
						<div class="k-map__tabs" role="tablist" aria-label="Tesisteki işletmeler" data-k-map-tabs hidden>
							<?php foreach ( $map_places as $place_index => $map_place ) : ?>
								<button
									type="button"
									class="k-map__tab"
									id="k-map-tab-<?php echo (int) $place_index; ?>"
									role="tab"
									aria-controls="k-map-panel"
									aria-selected="<?php echo 0 === $place_index ? 'true' : 'false'; ?>"
									tabindex="<?php echo 0 === $place_index ? '0' : '-1'; ?>"
									data-k-map-tab
									data-k-map-src="<?php echo esc_url( $map_place['embed'] ); ?>"
									data-k-map-name="<?php echo esc_attr( $map_place['name'] ); ?>"
								>
									<span <?php nwcs_edit_attr( 'contact', 'map', 'places', $map_place['row'], 'label' ); ?>><?php echo esc_html( $map_place['label'] ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="k-map" id="k-map-panel">
						<iframe
							class="k-map__frame"
							src="<?php echo esc_url( $map_places[0]['embed'] ); ?>"
							title="<?php echo esc_attr( $map_places[0]['name'] ); ?>"
							loading="lazy"
							referrerpolicy="no-referrer-when-downgrade"
							allowfullscreen
							data-k-map-frame
						></iframe>

						<?php /* Adres ve yol tarifi haritanin uzerine biniyor. Betikle yalnizca secili isletme gorunur. */ ?>
						<div class="k-map__card">
							<p class="k-map__title" <?php nwcs_edit_attr( 'contact', 'map', 'title' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'map', 'title' ) ); ?></p>
							<p class="k-map__address" <?php nwcs_edit_attr( 'contact', 'info', 'address' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'info', 'address' ) ); ?></p>

							<ul class="k-map__places">
								<?php foreach ( $map_places as $place_index => $map_place ) : ?>
									<li class="k-map__place" data-k-map-place>
										<span class="k-map__name" <?php nwcs_edit_attr( 'contact', 'map', 'places', $map_place['row'], 'name' ); ?>><?php echo esc_html( $map_place['name'] ); ?></span>

										<a
											class="k-map__link"
											href="<?php echo esc_url( $map_place['link'] ); ?>"
											target="_blank"
											rel="noopener noreferrer"
											<?php nwcs_edit_attr( 'contact', 'map', 'link_label' ); ?>
										>
											<?php echo esc_html( nwcs_field( 'contact', 'map', 'link_label' ) ); ?>
											<span class="screen-reader-text">: <?php echo esc_html( $map_place['name'] ); ?></span>
											<span aria-hidden="true">→</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
