<?php
/**
 * Iletisim: bilgiler, siparis ve fiyat formu, harita.
 *
 * Form admin-post.php'ye gider ve mesaji gercekten kaydeder (ik_handle_message).
 * Harita anahtarsiz Google Maps gomme adresiyle, gecikmeli yuklenir.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$state  = ik_message_state();
$errors = $state['errors'];
$values = $state['values'];
$query  = nwcs_field( 'contact', 'map', 'query' );

// Isletmenin Google Haritalar kaydi: "KOÇİST Kereste, Orman Ürünleri ve İnşaat
// Malzemeleri". Kisa baglanti gomulemez; harita isletmenin koordinatini gosterir.
// Paneldeki "Harita Adresi" yalnizca baslik ve haritanin gorunurlugu icin.
$map_link  = 'https://maps.app.goo.gl/goTd8wPiXnbDb8BY9';
$map_embed = 'https://www.google.com/maps?q=41.2311136,28.5012366&z=17&output=embed';

$info = array(
	array( 'icon' => 'pin', 'label' => 'address_label', 'value' => 'address', 'url' => '' ),
	array( 'icon' => 'phone', 'label' => 'phone_label', 'value' => 'phone', 'url' => 'phone_url' ),
	array( 'icon' => 'whatsapp', 'label' => 'whatsapp_label', 'value' => 'whatsapp', 'url' => 'whatsapp_url' ),
	array( 'icon' => 'mail', 'label' => 'email_label', 'value' => 'email', 'url' => 'email_url' ),
	array( 'icon' => 'clock', 'label' => 'hours_label', 'value' => 'hours', 'url' => '' ),
);

/**
 * Tek form alani: etiket, girdi, varsa hata. Etiket ve not panelde
 * (contact.form) duzenlenir; $label ve $note o alanlarin anahtaridir.
 */
$field = static function ( string $key, string $label, string $type, string $note, string $autocomplete, string $extra = '' ) use ( $errors, $values ): void {
	$id    = 'ik-' . $key;
	$error = $errors[ $key ] ?? '';
	$attrs = $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : '';
	?>
	<div class="ik-field<?php echo 'message' === $key ? ' ik-field--wide' : ''; ?>">
		<label class="ik-field__label" for="<?php echo esc_attr( $id ); ?>">
			<span <?php nwcs_edit_attr( 'contact', 'form', $label ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', $label ) ); ?></span>
			<?php if ( $note ) : ?>
				<span class="ik-field__note" <?php nwcs_edit_attr( 'contact', 'form', $note ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', $note ) ); ?></span>
			<?php endif; ?>
		</label>

		<?php if ( 'textarea' === $type ) : ?>
			<textarea class="ik-field__input" id="<?php echo esc_attr( $id ); ?>" name="ik_<?php echo esc_attr( $key ); ?>" rows="5"<?php echo $attrs; // phpcs:ignore ?>><?php echo esc_textarea( $values[ $key ] ?? '' ); ?></textarea>
		<?php elseif ( 'select' === $type ) : ?>
			<select class="ik-field__input" id="<?php echo esc_attr( $id ); ?>" name="ik_<?php echo esc_attr( $key ); ?>">
				<option value="">Seçin</option>
				<?php foreach ( ik_products() as $product ) : ?>
					<option value="<?php echo esc_attr( $product['title'] ); ?>"<?php selected( $values[ $key ] ?? $extra, $product['title'] ); ?>><?php echo esc_html( $product['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input class="ik-field__input" id="<?php echo esc_attr( $id ); ?>" name="ik_<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>" autocomplete="<?php echo esc_attr( $autocomplete ); ?>" value="<?php echo esc_attr( $values[ $key ] ?? '' ); ?>"<?php echo $attrs; // phpcs:ignore ?> />
		<?php endif; ?>

		<?php if ( $error ) : ?>
			<p class="ik-field__error" id="<?php echo esc_attr( $id ); ?>-error"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
	</div>
	<?php
};

get_template_part( 'template-parts/page-head', null, array( 'page' => 'contact' ) );
?>

<section class="ik-section ik-contact">
	<div class="ik-wrap ik-contact__grid">

		<ul class="ik-contact__info">
			<?php foreach ( $info as $row ) : ?>
				<?php
				$value = nwcs_field( 'contact', 'info', $row['value'] );
				$url   = $row['url'] ? nwcs_field( 'contact', 'info', $row['url'] ) : '';

				if ( '' === trim( $value ) ) {
					continue;
				}
				?>
				<li class="ik-contact__item">
					<span class="ik-contact__icon"><?php ik_icon( $row['icon'], 24 ); ?></span>
					<div>
						<h2 class="ik-contact__label" <?php nwcs_edit_attr( 'contact', 'info', $row['label'] ); ?>><?php echo esc_html( nwcs_field( 'contact', 'info', $row['label'] ) ); ?></h2>
						<?php if ( $url ) : ?>
							<a class="ik-contact__value" href="<?php echo esc_url( ik_link( $url ) ); ?>" <?php nwcs_edit_attr( 'contact', 'info', $row['value'] ); ?>><?php echo esc_html( $value ); ?></a>
						<?php else : ?>
							<p class="ik-contact__value" <?php nwcs_edit_attr( 'contact', 'info', $row['value'] ); ?>><?php echo nl2br( esc_html( $value ) ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="ik-form" id="form">
			<h2 class="ik-form__title" <?php nwcs_edit_attr( 'contact', 'form', 'title' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'title' ) ); ?></h2>

			<?php if ( $state['success'] ) : ?>
				<div class="ik-form__notice ik-form__notice--ok" role="status">
					<?php ik_icon( 'check', 24 ); ?>
					<p <?php nwcs_edit_attr( 'contact', 'form', 'success' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'success' ) ); ?></p>
				</div>
			<?php else : ?>
				<p class="ik-form__text" <?php nwcs_edit_attr( 'contact', 'form', 'text' ); ?>><?php echo esc_html( nwcs_field( 'contact', 'form', 'text' ) ); ?></p>

				<?php if ( $errors ) : ?>
					<div class="ik-form__notice ik-form__notice--error" role="alert">
						<p><?php echo esc_html( $errors['form'] ?? 'Mesaj gönderilmedi. İşaretli alanları düzeltip tekrar gönderin.' ); ?></p>
					</div>
				<?php endif; ?>

				<form class="ik-form__grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="ik_message" />
					<?php wp_nonce_field( 'ik_message', 'ik_nonce' ); ?>

					<?php
					$field( 'name', 'label_name', 'text', '', 'name' );
					$field( 'phone', 'label_phone', 'tel', '', 'tel' );
					$field( 'email', 'label_email', 'email', 'email_note', 'email' );
					$field( 'product', 'label_product', 'select', '', 'off', sanitize_text_field( wp_unslash( $_GET['urun'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
					$field( 'message', 'label_message', 'textarea', '', 'off' );
					?>

					<?php // Bot tuzagi: ekran okuyuculardan ve klavyeden gizli. ?>
					<div class="screen-reader-text" aria-hidden="true">
						<label for="ik-website">Web siteniz</label>
						<input type="text" id="ik-website" name="ik_website" tabindex="-1" autocomplete="off" />
					</div>

					<div class="ik-form__actions">
						<button type="submit" class="btn btn--solid" <?php nwcs_edit_attr( 'contact', 'form', 'button_label' ); ?>>
							<?php echo esc_html( nwcs_field( 'contact', 'form', 'button_label' ) ); ?>
						</button>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ( '' !== trim( $query ) ) : ?>
	<section class="ik-map" aria-label="Harita">
		<iframe
			class="ik-map__frame"
			title="<?php echo esc_attr( 'Harita: ' . $query ); ?>"
			src="<?php echo esc_url( $map_embed ); ?>"
			loading="lazy"
			referrerpolicy="no-referrer-when-downgrade"></iframe>

		<div class="ik-wrap ik-map__bar">
			<a class="btn btn--solid" href="<?php echo esc_url( $map_link ); ?>" target="_blank" rel="noopener" <?php nwcs_edit_attr( 'contact', 'map', 'directions_label' ); ?>>
				<?php ik_icon( 'pin', 18 ); ?>
				<?php echo esc_html( nwcs_field( 'contact', 'map', 'directions_label' ) ); ?>
				<span class="screen-reader-text">(yeni sekmede açılır)</span>
			</a>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
