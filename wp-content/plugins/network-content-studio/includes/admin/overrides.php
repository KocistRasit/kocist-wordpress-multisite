<?php
/**
 * Urun Havuzu sayfasindaki "Ozellestirmeler" bolumu.
 *
 * Bir urunun havuzdaki hali her sitede aynidir; bir site icin farkli bir ad,
 * aciklama, fiyat veya gorsel istendiginde o degerler ilgili sitenin
 * nwcs_products_overrides kaydina yazilir. Bu bolum o kayitlari sayfadan
 * ayrilmadan duzenlemeye yarar.
 *
 * Gorunurluk burada degistirilmez; onu sitenin kendi urun secimi belirler
 * (Icerik Studyosu -> ilgili sayfa -> urun bolumu).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bir sitedeki tek bir urunun istisna kaydi.
 */
function nwcs_override_get( int $blog_id, int $product_id ): array {
	$settings = nwcs_site_product_settings( $blog_id );

	$row = $settings['overrides'][ $product_id ] ?? array();

	return is_array( $row ) ? $row : array();
}

/**
 * Istisna kaydini yazar; bos kalirsa kaydi tamamen kaldirir.
 *
 * Sayfa adresi (slug, slug_old) $values icinde verilmezse kayittaki hali
 * korunur; adres yalnizca nwcs_override_set_slug() ile degisir.
 */
function nwcs_override_put( int $blog_id, int $product_id, array $values ): void {
	$settings  = nwcs_site_product_settings( $blog_id );
	$overrides = $settings['overrides'];
	$current   = is_array( $overrides[ $product_id ] ?? null ) ? $overrides[ $product_id ] : array();

	$values = array_merge( array_intersect_key( $current, array_flip( array( 'slug', 'slug_old' ) ) ), $values );

	$values = array_filter(
		$values,
		static fn( $value ): bool => '' !== $value && null !== $value && false !== $value && 0 !== $value && array() !== $value
	);

	if ( $values ) {
		$overrides[ $product_id ] = $values;
	} else {
		unset( $overrides[ $product_id ] );
	}

	update_blog_option( $blog_id, NWCS_OPTION_OVERRIDES, $overrides );
}

/**
 * Bu urunun havuzdaki gorselleri; istisna olarak secilebilecek secenekler.
 */
function nwcs_override_image_choices( array $product ): array {
	$choices = array( 0 => 'Havuzdaki görsel (varsayılan)' );

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $product['gallery_ids'] ?? array() as $index => $image_id ) {
		$post = get_post( (int) $image_id );

		$choices[ (int) $image_id ] = $post
			? sprintf( '%d. görsel — %s', $index + 1, $post->post_title )
			: sprintf( '%d. görsel (#%d)', $index + 1, (int) $image_id );
	}

	restore_current_blog();

	return $choices;
}

/**
 * Kayittaki ozellestirme sayisi (eski adres listesi sayilmaz: o yalnizca
 * yonlendirme icin tutulur).
 */
function nwcs_override_count( array $override ): int {
	unset( $override['slug_old'] );

	return count( array_filter( $override, static fn( $v ): bool => '' !== $v && 0 !== $v && null !== $v && array() !== $v ) );
}

/* ====================================================================== *
 * Sayfa adresi (site basina /urun/<adres>/)
 * ====================================================================== */

/** Bir urun icin tutulan en fazla eski adres. */
const NWCS_SLUG_OLD_MAX = 20;

/**
 * Urunun havuzdaki (otomatik) adresi. Cop kutusundaki urun de bulunur.
 */
function nwcs_override_pool_slug( int $product_id ): string {
	$pool = nwcs_pool_products();

	if ( isset( $pool[ $product_id ] ) ) {
		return (string) $pool[ $product_id ]['slug'];
	}

	switch_to_blog( nwcs_pool_blog_id() );
	$post = get_post( $product_id );
	restore_current_blog();

	if ( ! $post || NWCS_PRODUCT_TYPE !== $post->post_type ) {
		return '';
	}

	// Cop kutusundaki yazinin adi "__trashed" ekiyle tutulur.
	return (string) preg_replace( '/__trashed(-\d+)?$/', '', (string) $post->post_name );
}

/**
 * Bu sitede $slug adresini kullanan baska bir sey varsa duz Turkce hata
 * metni; yoksa bos. Bakilan: diger urunlerin guncel adresleri (ozel ya da
 * otomatik) ve sitenin sayfalari.
 */
function nwcs_override_slug_conflict( int $blog_id, int $product_id, string $slug ): string {
	$overrides = nwcs_site_product_settings( $blog_id )['overrides'];

	foreach ( nwcs_pool_products() as $id => $product ) {
		if ( (int) $id === $product_id ) {
			continue;
		}

		$override = is_array( $overrides[ $id ] ?? null ) ? $overrides[ $id ] : array();
		$custom   = nwcs_override_slug( $override );

		$name = '' !== (string) ( $override['title'] ?? '' ) ? (string) $override['title'] : (string) $product['title'];

		if ( ( '' !== $custom ? $custom : (string) $product['slug'] ) === $slug ) {
			return sprintf( 'Bu adres bu sitede “%s” ürününün adresi. Başka bir adres yazın.', $name );
		}

		// O urun bu sitede ozel adres kullansa da havuzdaki adresi ona yonlenir
		// (arama motorunda kayitli olabilir); baska urune verilmez.
		if ( (string) $product['slug'] === $slug ) {
			return sprintf( 'Bu adres “%s” ürününün ana adresi; özel adres verilse bile eski bağlantılar buradan ona yönlenir. Başka bir adres yazın.', $name );
		}
	}

	// Cop kutusundaki urunlerin bu sitedeki ozel adresleri de dolu sayilir:
	// geri getirilince adresleri cakismasin.
	foreach ( $overrides as $id => $override ) {
		if ( (int) $id !== $product_id && is_array( $override ) && nwcs_override_slug( $override ) === $slug ) {
			return 'Bu adres bu sitede çöp kutusundaki bir ürüne ait; ürün geri getirilirse bu adresi kullanacak. Başka bir adres yazın.';
		}
	}

	switch_to_blog( $blog_id );
	$page = get_page_by_path( $slug );
	restore_current_blog();

	if ( $page ) {
		return sprintf( 'Bu adres bu sitede “%s” sayfasının adresi. Başka bir adres yazın.', '' !== $page->post_title ? $page->post_title : $slug );
	}

	return '';
}

/**
 * Urunun bu sitedeki adresini degistirir. $raw bos ya da havuzdaki adresle
 * ayniysa otomatik adrese doner. Onceki ozel adres eski adresler listesine
 * girer (yeni adrese 301 ile yonlenir); yeni adres baska bir urunun eski
 * adresleri arasindaysa oradan duser.
 *
 * @return array{ok:bool, message:string, slug:string, auto:string, url:string, old:string[], changed:bool}
 */
function nwcs_override_set_slug( int $blog_id, int $product_id, string $raw ): array {
	$auto      = nwcs_override_pool_slug( $product_id );
	$settings  = nwcs_site_product_settings( $blog_id );
	$overrides = $settings['overrides'];
	$override  = is_array( $overrides[ $product_id ] ?? null ) ? $overrides[ $product_id ] : array();
	$custom    = nwcs_override_slug( $override );
	$current   = '' !== $custom ? $custom : $auto;
	$old       = nwcs_override_slug_old( $override );

	$result = static function ( bool $ok, string $message, string $slug, array $old, bool $changed = false ) use ( $auto, $blog_id ): array {
		return array(
			'ok'      => $ok,
			'message' => $message,
			'slug'    => $slug,
			'auto'    => $auto,
			'url'     => nwcs_product_url( $slug, $blog_id ),
			'old'     => array_values( array_diff( array_unique( array_merge( $auto !== $slug ? array( $auto ) : array(), $old ) ), array( $slug ) ) ),
			'changed' => $changed,
		);
	};

	if ( '' === $auto ) {
		return $result( false, 'Ürün bulunamadı; silinmiş olabilir.', $current, $old );
	}

	$target = nwcs_slug_clean( $raw );

	if ( '' !== trim( $raw ) && '' === $target ) {
		return $result( false, 'Adres en az bir harf ya da rakam içermeli; yalnızca işaretlerden oluşamaz. Örneğin: verandali-kopek-kulubesi', $current, $old );
	}

	$target = '' !== $target ? $target : $auto;

	if ( $target === $current ) {
		return $result( true, '', $current, $old );
	}

	$conflict = nwcs_override_slug_conflict( $blog_id, $product_id, $target );

	if ( '' !== $conflict ) {
		if ( $target === $auto ) {
			// "Otomatiğe dön" icin: baska adres yazmak degil, digerini degistirmek gerekir.
			$conflict = str_replace(
				array( 'Bu adres', 'Başka bir adres yazın.' ),
				array( 'Otomatik adres (' . $auto . ')', 'Önce onun adresini değiştirin.' ),
				$conflict
			);
		}

		return $result( false, $conflict, $current, $old );
	}

	// Onceki ozel adres eski adreslere; havuz adresi zaten hep yonlenir.
	if ( $current !== $auto ) {
		$old[] = $current;
	}

	$old = array_values( array_diff( array_unique( $old ), array( $target, $auto ) ) );
	$old = array_slice( $old, -NWCS_SLUG_OLD_MAX );

	$override['slug']     = $target !== $auto ? $target : '';
	$override['slug_old'] = $old;

	// Yeni adres baska bir urunun eski adresiyse o urunden duser (yonlendirme karismasin).
	foreach ( $overrides as $id => $row ) {
		if ( (int) $id === $product_id || ! is_array( $row ) ) {
			continue;
		}

		$their = nwcs_override_slug_old( $row );

		if ( in_array( $target, $their, true ) ) {
			$overrides[ $id ]['slug_old'] = array_values( array_diff( $their, array( $target ) ) );

			if ( ! $overrides[ $id ]['slug_old'] ) {
				unset( $overrides[ $id ]['slug_old'] );
			}
		}
	}

	$override = array_filter( $override, static fn( $v ): bool => '' !== $v && array() !== $v && null !== $v );

	if ( $override ) {
		$overrides[ $product_id ] = $override;
	} else {
		unset( $overrides[ $product_id ] );
	}

	update_blog_option( $blog_id, NWCS_OPTION_OVERRIDES, $overrides );

	return $result( true, '', $target, $old, true );
}

/* ====================================================================== *
 * Arayuz
 * ====================================================================== */

/**
 * Urunun sitelere gore ozellestirilmis hali; duzenlenebilir.
 */
function nwcs_render_product_customizations( ?array $product, array $media ): void {
	if ( empty( $product['id'] ) ) {
		return;
	}

	$id          = (int) $product['id'];
	$images      = nwcs_override_image_choices( $product );
	$unsupported = array();
	$sites       = array();

	foreach ( nwcs_editable_sites() as $blog_id => $site ) {
		$blog_id = (int) $blog_id;

		if ( ! nwcs_site_supports_products( $blog_id ) ) {
			$unsupported[] = $site['label'];
			continue;
		}

		$settings = nwcs_site_product_settings( $blog_id );

		$sites[] = array(
			'blog_id'  => $blog_id,
			'label'    => $site['label'],
			'home'     => untrailingslashit( preg_replace( '#^https?://#', '', get_home_url( $blog_id ) ) ),
			'visible'  => 'all' === $settings['mode'] || in_array( $id, $settings['selected'], true ),
			'override' => nwcs_override_get( $blog_id, $id ),
		);
	}
	?>
	<div class="nwcs-field nwcs-custom" data-nwcs-overrides data-product="<?php echo esc_attr( (string) $id ); ?>">
		<span class="nwcs-field__label">Özelleştirmeler</span>

		<p class="nwcs-hint">
			Ürün her sitede Ürün Havuzu’ndaki hâliyle görünür. Bir site için farklı bir ad, açıklama,
			fiyat veya görsel istiyorsanız aşağıdan girin; boş bıraktığınız alanlar Ürün Havuzu’ndaki
			değeri kullanır.
		</p>

		<?php foreach ( $sites as $site ) :
			$override = $site['override'];
			$count    = nwcs_override_count( $override );
			$custom   = nwcs_override_slug( $override );
			$auto     = (string) $product['slug'];
			$moved    = array_values( array_diff( array_unique( array_merge( '' !== $custom ? array( $auto ) : array(), nwcs_override_slug_old( $override ) ) ), array( $custom ) ) );
			?>
			<details class="nwcs-ovr" id="nwcs-ovr-<?php echo esc_attr( (string) $site['blog_id'] ); ?>" <?php echo $count ? 'open' : ''; ?> data-blog="<?php echo esc_attr( (string) $site['blog_id'] ); ?>">
				<summary class="nwcs-ovr__head">
					<span class="nwcs-ovr__name"><?php echo esc_html( $site['label'] ); ?></span>

					<?php if ( ! $site['visible'] ) : ?>
						<span class="nwcs-ovr__tag nwcs-ovr__tag--off">Bu sitede seçili değil</span>
					<?php elseif ( $count ) : ?>
						<span class="nwcs-ovr__tag nwcs-ovr__tag--on"><?php echo (int) $count; ?> özelleştirme</span>
					<?php else : ?>
						<span class="nwcs-ovr__tag">Havuzdaki gibi</span>
					<?php endif; ?>
				</summary>

				<div class="nwcs-ovr__body">
					<div class="nwcs-field nwcs-slug" data-ovr-slug-box data-auto="<?php echo esc_attr( $auto ); ?>">
						<?php $slug_id = 'nwcs-slug-' . $site['blog_id']; ?>
						<label class="nwcs-sublabel" for="<?php echo esc_attr( $slug_id ); ?>">Sayfa adresi</label>
						<div class="nwcs-slug__bar">
							<span class="nwcs-slug__base"><?php echo esc_html( $site['home'] ); ?>/urun/</span>
							<input class="nwcs-input nwcs-slug__input" type="text" id="<?php echo esc_attr( $slug_id ); ?>" data-ovr="slug"
								value="<?php echo esc_attr( $custom ); ?>" placeholder="<?php echo esc_attr( $auto ); ?>"
								autocomplete="off" spellcheck="false" />
							<button type="button" class="button" data-ovr-slug-reset <?php disabled( '' === $custom ); ?>>Otomatiğe dön</button>
						</div>
						<p class="nwcs-hint" data-ovr-slug-note>
							Otomatik: <code><?php echo esc_html( $auto ); ?></code>. Boş bırakırsanız otomatik adres kullanılır.
							Türkçe harfler sadeleşir, boşluklar tire olur.
							<?php if ( $moved ) : ?>
								<br />Eski <?php echo 1 === count( $moved ) ? 'adres' : 'adresler'; ?>
								(<?php echo esc_html( implode( ', ', $moved ) ); ?>) güncel adrese yönlenir.
							<?php endif; ?>
						</p>
					</div>

					<div class="nwcs-field">
						<label class="nwcs-sublabel">Ürün adı</label>
						<input class="nwcs-input" type="text" data-ovr="title"
							value="<?php echo esc_attr( (string) ( $override['title'] ?? '' ) ); ?>"
							placeholder="<?php echo esc_attr( $product['title'] ); ?>" />
					</div>

					<div class="nwcs-field">
						<label class="nwcs-sublabel">Kart açıklaması</label>
						<textarea class="nwcs-input" rows="2" data-ovr="short"
							placeholder="<?php echo esc_attr( $product['short'] ); ?>"><?php echo esc_textarea( (string) ( $override['short'] ?? '' ) ); ?></textarea>
					</div>

					<div class="nwcs-field">
						<label class="nwcs-check">
							<input type="checkbox" data-ovr="price_override" <?php checked( ! empty( $override['price_override'] ) ); ?> />
							Bu sitede farklı fiyat
						</label>

						<input class="nwcs-input" type="text" data-ovr="price"
							value="<?php echo esc_attr( (string) ( $override['price'] ?? '' ) ); ?>"
							placeholder="<?php echo esc_attr( '' !== trim( $product['price'] ) ? $product['price'] : 'Teklif al' ); ?>"
							<?php disabled( empty( $override['price_override'] ) ); ?> />
						<p class="nwcs-hint">İşaretleyip boş bırakırsanız bu sitede “Teklif al” görünür.</p>
					</div>

					<div class="nwcs-field">
						<label class="nwcs-sublabel">Öne çıkan görsel</label>
						<select class="nwcs-input" data-ovr="image">
							<?php foreach ( $images as $value => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $value ); ?>"
									<?php selected( (int) ( $override['image'] ?? 0 ), (int) $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="nwcs-actions">
						<button type="button" class="button button-primary" data-ovr-save>Kaydet</button>
						<button type="button" class="button" data-ovr-clear <?php disabled( ! $count ); ?>>Özelleştirmeyi kaldır</button>
						<span class="nwcs-ovr__status" data-ovr-status aria-live="polite"></span>
					</div>
				</div>
			</details>
		<?php endforeach; ?>

		<?php if ( $unsupported ) : ?>
			<p class="nwcs-hint">
				Şu sitelerde ürün gösterilmiyor, çünkü sitenin tasarımında ürün bölümü yok:
				<strong><?php echo esc_html( implode( ', ', $unsupported ) ); ?></strong>.
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/* ====================================================================== *
 * Kaydetme uclari
 * ====================================================================== */

/**
 * Ortak denetim; gecerli site kimligini dondurur.
 */
function nwcs_override_guard(): array {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), 403 );
	}

	if ( ! check_ajax_referer( 'nwcs_panel', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Oturumun süresi dolmuş olabilir; sayfayı yenileyip yeniden deneyin.' ), 400 );
	}

	$blog_id    = isset( $_POST['blog'] ) ? absint( $_POST['blog'] ) : 0;
	$product_id = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;

	if ( ! $blog_id || ! $product_id || ! array_key_exists( $blog_id, nwcs_editable_sites() ) ) {
		wp_send_json_error( array( 'message' => 'Site ya da ürün bulunamadı. Sayfayı yenileyip yeniden deneyin.' ) );
	}

	return array( $blog_id, $product_id );
}

add_action( 'wp_ajax_nwcs_override_save', 'nwcs_ajax_override_save' );
function nwcs_ajax_override_save(): void {
	list( $blog_id, $product_id ) = nwcs_override_guard();

	// Once adres: hataliysa (cakisma) hicbir alan kaydedilmez.
	$address = null;

	if ( isset( $_POST['slug'] ) ) {
		$address = nwcs_override_set_slug( $blog_id, $product_id, (string) wp_unslash( $_POST['slug'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_slug_clean temizler.

		if ( ! $address['ok'] ) {
			wp_send_json_error( array( 'message' => $address['message'], 'field' => 'slug' ) );
		}
	}

	$price_override = ! empty( $_POST['price_override'] );

	$values = array(
		'title'          => nwcs_clean_text( wp_unslash( $_POST['title'] ?? '' ) ),
		'short'          => nwcs_clean_text( wp_unslash( $_POST['short'] ?? '' ), true ),
		'image'          => absint( $_POST['image'] ?? 0 ),
		'price_override' => $price_override ? 1 : 0,
		// Fiyat yalnizca isaretliyse anlamli; isaretliyken bos birakmak
		// "Teklif al" demektir, bu yuzden bos deger de saklanir.
		'price'          => $price_override ? nwcs_clean_text( wp_unslash( $_POST['price'] ?? '' ) ) : '',
	);

	// price_override isaretliyse kayit bos sayilmasin diye ayri tutulur.
	$store = array_filter(
		$values,
		static fn( $value ): bool => '' !== $value && 0 !== $value
	);

	if ( $price_override ) {
		$store['price_override'] = 1;
		$store['price']          = $values['price'];
	}

	nwcs_override_put( $blog_id, $product_id, $store );
	nwcs_pool_flush_cache();

	$count = nwcs_override_count( nwcs_override_get( $blog_id, $product_id ) );

	wp_send_json_success(
		array(
			'message' => $count ? 'Kaydedildi' : 'Özelleştirme kalmadı',
			'count'   => $count,
			'address' => $address ?? nwcs_override_set_slug( $blog_id, $product_id, nwcs_override_slug( nwcs_override_get( $blog_id, $product_id ) ) ),
		)
	);
}

/**
 * "Otomatiğe dön": yalnizca sayfa adresini havuzdaki adrese dondurur; diger
 * ozellestirmelere dokunmaz. Ozel adres eski adreslere girer, yonlenir.
 */
add_action( 'wp_ajax_nwcs_override_slug_reset', 'nwcs_ajax_override_slug_reset' );
function nwcs_ajax_override_slug_reset(): void {
	list( $blog_id, $product_id ) = nwcs_override_guard();

	$address = nwcs_override_set_slug( $blog_id, $product_id, '' );

	if ( ! $address['ok'] ) {
		wp_send_json_error( array( 'message' => $address['message'], 'field' => 'slug' ) );
	}

	nwcs_pool_flush_cache();

	wp_send_json_success(
		array(
			'message' => 'Otomatik adrese dönüldü',
			'count'   => nwcs_override_count( nwcs_override_get( $blog_id, $product_id ) ),
			'address' => $address,
		)
	);
}

add_action( 'wp_ajax_nwcs_override_clear', 'nwcs_ajax_override_clear' );
function nwcs_ajax_override_clear(): void {
	list( $blog_id, $product_id ) = nwcs_override_guard();

	// Ozel adres de kalkar (otomatige doner); eski adresler yonlenmeye devam eder.
	$address = nwcs_override_set_slug( $blog_id, $product_id, '' );

	if ( ! $address['ok'] ) {
		wp_send_json_error( array( 'message' => $address['message'], 'field' => 'slug' ) );
	}

	nwcs_override_put( $blog_id, $product_id, array() );
	nwcs_pool_flush_cache();

	wp_send_json_success( array( 'message' => 'Kaldırıldı', 'address' => $address ) );
}
