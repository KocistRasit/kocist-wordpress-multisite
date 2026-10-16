<?php
/**
 * Ürün Havuzu -> Detay başlıkları.
 *
 * Ekran: tek tablo. Her baslik icin zorunluluk, kac urunde ve hangi
 * kategorilerde kullanildigi, sira (yukari/asagi), yeniden adlandirma,
 * birlestirme ve (hicbir urunde kullanilmiyorsa) silme.
 *
 * Urun formundaki "Detay başlıkları" blogu da burada cizilir.
 *
 * Kayit ve urun degerleri: includes/product-headings.php. Urunlerin detayi
 * her zaman nwcs_product_write_details() ile yazilir (spec onunla esit kalir).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_HEADINGS_SLUG = 'nwcs-pool-headings';

// Urun Havuzu sirasi: Urunler, Kategoriler (12), Urun aciklamalari (16), Detay basliklari (17).
add_action( 'network_admin_menu', 'nwcs_register_headings_menu', 17 );
function nwcs_register_headings_menu(): void {
	add_submenu_page( NWCS_POOL_SLUG, 'Detay başlıkları', 'Detay başlıkları', NWCS_CAPABILITY, NWCS_HEADINGS_SLUG, 'nwcs_render_headings' );
}

function nwcs_headings_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => NWCS_HEADINGS_SLUG ), $args ), network_admin_url( 'admin.php' ) );
}

/* ====================================================================== *
 * Urun formu blogu
 * ====================================================================== */

/**
 * Formdan gelen detay satirlari (temizlenmis).
 *
 * @return array<int, array{label:string, value:string}>
 */
function nwcs_posted_details(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- cagiran denetledi; nwcs_clean_details temizler.
	$raw    = isset( $_POST['details'] ) && is_array( $_POST['details'] ) ? wp_unslash( $_POST['details'] ) : array();
	// phpcs:enable
	$labels = array_values( (array) ( $raw['label'] ?? array() ) );
	$values = array_values( (array) ( $raw['value'] ?? array() ) );
	$rows   = array();

	foreach ( $labels as $index => $label ) {
		$rows[] = array(
			'label' => is_scalar( $label ) ? (string) $label : '',
			'value' => is_scalar( $values[ $index ] ?? '' ) ? (string) ( $values[ $index ] ?? '' ) : '',
		);
	}

	return nwcs_clean_details( $rows );
}

/**
 * Urun formundaki "Detay başlıkları" satirlari: urunun kendi sirasi korunur
 * (sitede de bu sirayla gorunur). Zorunlu basliklar etiketi sabit satirdir;
 * urunde olmayan zorunlu baslik, kayit sirasina gore yerine bos satir olarak
 * girer. Degerler oldugu gibi (gosterim duzeltmesi olmadan) gelir.
 *
 * @return array<int, array{label:string, value:string, required:bool}>
 */
function nwcs_product_details_form_rows( array $details ): array {
	$required = nwcs_required_headings();
	$rank     = array_flip( array_keys( nwcs_product_headings() ) );
	$rows     = array();
	$have     = array();

	foreach ( nwcs_product_pairs( array( 'details' => $details ), false ) as $pair ) {
		$key          = nwcs_heading_key( $pair[0] );
		$have[ $key ] = true;
		$rows[]       = array( 'label' => isset( $required[ $key ] ) ? $required[ $key ] : $pair[0], 'value' => $pair[1], 'required' => isset( $required[ $key ] ) );
	}

	foreach ( $required as $key => $label ) {
		if ( isset( $have[ $key ] ) ) {
			continue;
		}

		// Kayitta kendisinden sonra gelen ilk satirin onune.
		$at = count( $rows );

		foreach ( $rows as $index => $row ) {
			if ( ( $rank[ nwcs_heading_key( $row['label'] ) ] ?? PHP_INT_MAX ) > ( $rank[ $key ] ?? PHP_INT_MAX ) ) {
				$at = $index;
				break;
			}
		}

		array_splice( $rows, $at, 0, array( array( 'label' => $label, 'value' => '', 'required' => true ) ) );
	}

	return $rows;
}

/**
 * Urun formundaki "Detay başlıkları" blogu. Bos degerli satir kaydedilmez.
 */
function nwcs_render_product_details_field( ?array $product ): void {
	$headings = nwcs_product_headings();
	$details  = (array) ( $product['details'] ?? array() );
	$spec     = (string) ( $product['spec'] ?? '' );
	$rows     = nwcs_product_details_form_rows( $details );
	$row_id   = 0;
	?>
	<div class="nwcs-field nwcs-details" data-nwcs-details>
		<span class="nwcs-field__label" id="nwcs-details-title">Detay başlıkları</span>
		<p class="nwcs-hint">
			Ürün sayfasındaki teknik detaylar, sitede bu sırayla görünür (örneğin Ahşap Cinsi: Çam). Değeri boş satır kaydedilmez.
			Hangi başlıkların zorunlu olduğu <a href="<?php echo esc_url( nwcs_headings_url() ); ?>">Detay başlıkları</a> ekranından gelir.
		</p>

		<ul class="nwcs-details__list" aria-labelledby="nwcs-details-title" data-nwcs-details-list>
			<?php foreach ( array_merge( $rows, array( array( 'label' => '', 'value' => '', 'required' => false ) ) ) as $row ) : ?>
				<?php $id = 'nwcs-d-' . ( ++$row_id ); ?>
				<?php if ( $row['required'] ) : ?>
					<li class="nwcs-details__row is-required<?php echo '' === $row['value'] ? ' is-missing' : ''; ?>">
						<label class="nwcs-details__name" for="<?php echo esc_attr( $id ); ?>">
							<?php echo esc_html( $row['label'] ); ?>
							<span class="nwcs-details__badge">zorunlu</span>
							<?php if ( '' === $row['value'] ) : ?>
								<span class="nwcs-details__badge nwcs-details__badge--missing">eksik</span>
							<?php endif; ?>
						</label>
						<input type="hidden" name="details[label][]" value="<?php echo esc_attr( $row['label'] ); ?>" />
						<input class="nwcs-input" type="text" id="<?php echo esc_attr( $id ); ?>" name="details[value][]" value="<?php echo esc_attr( $row['value'] ); ?>" />
					</li>
				<?php else : ?>
					<li class="nwcs-details__row" data-nwcs-detail-row>
						<input class="nwcs-input nwcs-details__label" type="text" name="details[label][]" list="nwcs-heading-list"
							value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="Başlık" aria-label="Başlık" />
						<input class="nwcs-input" type="text" name="details[value][]" id="<?php echo esc_attr( $id ); ?>"
							value="<?php echo esc_attr( $row['value'] ); ?>" placeholder="Değer" aria-label="<?php echo esc_attr( '' !== $row['label'] ? $row['label'] : 'Değer' ); ?>" />
						<button type="button" class="nwcs-move nwcs-row__delete" data-nwcs-detail-remove hidden
							aria-label="<?php echo esc_attr( '' !== $row['label'] ? $row['label'] . ' satırını kaldır' : 'Satırı kaldır' ); ?>">×</button>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>

		<template data-nwcs-detail-template>
			<li class="nwcs-details__row" data-nwcs-detail-row>
				<input class="nwcs-input nwcs-details__label" type="text" name="details[label][]" list="nwcs-heading-list" placeholder="Başlık" aria-label="Başlık" />
				<input class="nwcs-input" type="text" name="details[value][]" placeholder="Değer" aria-label="Değer" />
				<button type="button" class="nwcs-move nwcs-row__delete" data-nwcs-detail-remove aria-label="Satırı kaldır">×</button>
			</li>
		</template>

		<button type="button" class="nwcs-details__add" data-nwcs-detail-add hidden>+ Başlık ekle</button>

		<datalist id="nwcs-heading-list">
			<?php foreach ( $headings as $key => $row ) : ?>
				<?php if ( ! $row['required'] ) : ?>
					<option value="<?php echo esc_attr( $row['label'] ); ?>"></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</datalist>

		<?php if ( ! $details && nwcs_spec_is_note( $spec ) ) : ?>
			<label class="nwcs-sublabel" for="nwcs-p-spec">Kısa not</label>
			<input class="nwcs-input" type="text" id="nwcs-p-spec" name="spec" value="<?php echo esc_attr( $spec ); ?>" />
			<p class="nwcs-hint">Bu ürünün eski serbest notu (örneğin “80 × 120 cm”). Yukarıya bir detay eklerseniz not yerine detaylar gösterilir.</p>
		<?php endif; ?>
	</div>
	<?php
}

/* ====================================================================== *
 * Detay başlıkları ekrani
 * ====================================================================== */

/**
 * Tum urunlerin (cop dahil) detaylarini $change ile yeniden yazar. $change
 * detay listesini alir, yeni listeyi ya da degismediyse null dondurur.
 *
 * @return int Degisen urun sayisi.
 */
function nwcs_headings_rewrite( callable $change ): int {
	$changed = 0;

	switch_to_blog( nwcs_pool_blog_id() );

	$ids = get_posts(
		array(
			'post_type'      => NWCS_PRODUCT_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		$details = nwcs_product_details( (int) $id );
		$new     = $details ? $change( $details ) : null;

		if ( null !== $new ) {
			nwcs_product_write_details( (int) $id, $new );
			++$changed;
		}
	}

	restore_current_blog();

	if ( $changed ) {
		nwcs_pool_flush_cache();
	}

	return $changed;
}

add_action( 'admin_post_nwcs_headings', 'nwcs_handle_headings' );
function nwcs_handle_headings(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_headings' );

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- asagida tek tek temizlenir.
	$command  = isset( $_POST['islem'] ) ? sanitize_text_field( wp_unslash( $_POST['islem'] ) ) : 'save';
	$headings = nwcs_product_headings();
	[ $verb, $key ] = array_pad( explode( ':', $command, 2 ), 2, '' );
	$key      = sanitize_key( $key );
	$result   = 'saved';
	$count    = 0;

	$was_required = array_keys( array_filter( $headings, static fn( array $row ): bool => ! empty( $row['required'] ) ) );

	if ( 'save' === $verb || 'up' === $verb || 'down' === $verb ) {
		// Zorunluluk isaretleri her kayitta tablodan okunur.
		$flags = isset( $_POST['zorunlu'] ) && is_array( $_POST['zorunlu'] ) ? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['zorunlu'] ) ) ) : array();

		if ( isset( $_POST['tablo'] ) ) {
			foreach ( $headings as $heading_key => $row ) {
				$headings[ $heading_key ]['required'] = in_array( $heading_key, $flags, true );
			}
		}

		// Tablodaki sira (JavaScript ile birkac tasima yapilip tek seferde kaydedilir).
		if ( isset( $_POST['sira'] ) && is_array( $_POST['sira'] ) ) {
			$position = 10;

			foreach ( array_map( 'sanitize_key', wp_unslash( $_POST['sira'] ) ) as $heading_key ) {
				if ( isset( $headings[ $heading_key ] ) ) {
					$headings[ $heading_key ]['order'] = $position;
					$position                         += 10;
				}
			}

			uasort( $headings, static fn( array $a, array $b ): int => $a['order'] <=> $b['order'] );
		}

		if ( isset( $headings[ $key ] ) && 'save' !== $verb ) {
			$keys  = array_keys( $headings );
			$index = array_search( $key, $keys, true );
			$swap  = 'up' === $verb ? $index - 1 : $index + 1;

			if ( isset( $keys[ $swap ] ) ) {
				$other                           = $keys[ $swap ];
				$order                           = $headings[ $key ]['order'];
				$headings[ $key ]['order']       = $headings[ $other ]['order'];
				$headings[ $other ]['order']     = $order;
			}

			$result = 'moved';
		}

		nwcs_save_product_headings( $headings );
	} elseif ( 'add' === $verb ) {
		$label = isset( $_POST['yeni'] ) ? nwcs_heading_clean_label( wp_unslash( $_POST['yeni'] ) ) : '';

		if ( ! nwcs_heading_is_valid( $label ) ) {
			$result = 'invalid';
		} elseif ( in_array( nwcs_heading_key( $label ), nwcs_heading_reserved_keys(), true ) ) {
			$result = 'reserved';
		} elseif ( isset( $headings[ nwcs_heading_key( $label ) ] ) ) {
			$result = 'exists';
		} else {
			nwcs_heading_ensure( $label );

			if ( ! empty( $_POST['yeni_zorunlu'] ) ) {
				$headings                                  = nwcs_product_headings();
				$headings[ nwcs_heading_key( $label ) ]['required'] = true;
				nwcs_save_product_headings( $headings );
			}

			$result = 'added';
		}
	} elseif ( 'rename' === $verb && isset( $headings[ $key ] ) ) {
		$label   = isset( $_POST['ad'][ $key ] ) ? nwcs_heading_clean_label( wp_unslash( $_POST['ad'][ $key ] ) ) : '';
		$new_key = nwcs_heading_key( $label );

		if ( ! nwcs_heading_is_valid( $label ) ) {
			$result = 'invalid';
		} elseif ( $new_key !== $key && in_array( $new_key, nwcs_heading_reserved_keys(), true ) ) {
			$result = 'reserved';
		} elseif ( $new_key !== $key && isset( $headings[ $new_key ] ) ) {
			$result = 'exists';
		} else {
			$row          = $headings[ $key ];
			$row['label'] = $label;
			unset( $headings[ $key ] );
			$headings[ $new_key ] = $row;
			nwcs_save_product_headings( $headings );

			$count  = nwcs_headings_rewrite(
				static function ( array $details ) use ( $key, $label ): ?array {
					$hit = false;

					foreach ( $details as &$item ) {
						if ( nwcs_heading_key( $item['label'] ) === $key && $item['label'] !== $label ) {
							$item['label'] = $label;
							$hit           = true;
						}
					}
					unset( $item );

					return $hit ? $details : null;
				}
			);
			$result = 'renamed';
		}
	} elseif ( 'merge' === $verb && isset( $headings[ $key ] ) ) {
		$target = isset( $_POST['hedef'][ $key ] ) ? sanitize_key( wp_unslash( $_POST['hedef'][ $key ] ) ) : '';

		if ( ! isset( $headings[ $target ] ) || $target === $key ) {
			$result = 'pick';
		} else {
			$target_label = $headings[ $target ]['label'];

			$count = nwcs_headings_rewrite(
				static function ( array $details ) use ( $key, $target, $target_label ): ?array {
					$has_source = false;
					$has_target = false;

					foreach ( $details as $item ) {
						$item_key    = nwcs_heading_key( $item['label'] );
						$has_source  = $has_source || $item_key === $key;
						$has_target  = $has_target || $item_key === $target;
					}

					if ( ! $has_source ) {
						return null;
					}

					$out = array();

					foreach ( $details as $item ) {
						if ( nwcs_heading_key( $item['label'] ) === $key ) {
							// Hedefte deger varsa o kalir; yoksa bu deger hedefin adiyla tasinir.
							if ( ! $has_target ) {
								$out[] = array( 'label' => $target_label, 'value' => $item['value'] );
							}
							continue;
						}

						$out[] = $item;
					}

					return $out;
				}
			);

			// Kaynak zorunluysa hedef de zorunlu olur.
			$headings[ $target ]['required'] = $headings[ $target ]['required'] || $headings[ $key ]['required'];
			unset( $headings[ $key ] );
			nwcs_save_product_headings( $headings );
			$result = 'merged';
		}
	} elseif ( 'delete' === $verb && isset( $headings[ $key ] ) ) {
		$usage = nwcs_heading_usage();

		if ( ! empty( $usage[ $key ]['count'] ) ) {
			$result = 'in_use';
		} else {
			unset( $headings[ $key ] );
			nwcs_save_product_headings( $headings );
			$result = 'deleted';
		}
	}
	// phpcs:enable

	$args = array( 'sonuc' => $result, 'adet' => $count );

	// Yeni zorunlu yapilanlar: hangi kategorilerin Excel'i takilacak, bildirimde yazar.
	$now_required = array_keys( array_filter( nwcs_product_headings(), static fn( array $row ): bool => ! empty( $row['required'] ) ) );
	$fresh        = array_values( array_diff( $now_required, $was_required ) );

	if ( $fresh ) {
		$args['zorunlu'] = implode( ',', $fresh );
	}

	if ( 'moved' === $result ) {
		$args['odak'] = $key;
	}

	wp_safe_redirect( nwcs_headings_url( $args ) . ( 'moved' === $result ? '#h-' . $key : '' ) );
	exit;
}

function nwcs_render_headings(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$headings = nwcs_product_headings();
	$usage    = nwcs_heading_usage();
	$gaps     = nwcs_heading_gaps();
	$total    = count( nwcs_pool_products() );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$result = isset( $_GET['sonuc'] ) ? sanitize_key( wp_unslash( $_GET['sonuc'] ) ) : '';
	$count  = isset( $_GET['adet'] ) ? absint( $_GET['adet'] ) : 0;
	$focus  = isset( $_GET['odak'] ) ? sanitize_key( wp_unslash( $_GET['odak'] ) ) : '';
	// phpcs:enable

	$notices = array(
		'saved'   => array( 'success', 'Değişiklikler kaydedildi.' ),
		'moved'   => array( 'success', 'Sıra değişti. Sitelerdeki teknik detay listeleri bu sırayla gösterilir.' ),
		'added'   => array( 'success', 'Başlık eklendi. Ürün formunda ve kategorilerin Excel dosyalarında kullanılabilir.' ),
		'renamed' => array( 'success', sprintf( 'Başlığın adı değişti; %d ürün güncellendi.', $count ) ),
		'merged'  => array( 'success', sprintf( 'Başlıklar birleştirildi; %d ürün güncellendi.', $count ) ),
		'deleted' => array( 'success', 'Başlık silindi.' ),
		'invalid' => array( 'error', 'Başlık adı en az bir harf içermeli. “Ahşap Cinsi” gibi bir ad yazın.' ),
		'exists'  => array( 'error', 'Bu adda bir başlık zaten var. İki başlığı tek yapmak için “Birleştir”i kullanın.' ),
		'reserved' => array( 'error', 'Bu ad Excel dosyasında zaten ayrı bir sütunun adı (Ürün kodu, Ürün adı, Fiyat, Kısa açıklama, Site, Meta başlık, Meta açıklama, Ürün açıklaması, Sayfa adresi gibi). Başka bir ad yazın, örneğin “Fiyat (Tek)”.' ),
		'pick'    => array( 'error', 'Birleştirmek için listeden başka bir başlık seçin.' ),
		'in_use'  => array( 'error', 'Bu başlık ürünlerde kullanıldığı için silinemez. Kaldırmak istiyorsanız “Düzenle” › “Birleştir” ile değerlerini başka bir başlığa taşıyın.' ),
	);
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-headings">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Detay başlıkları</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu’na dön</a>
			</div>
		</header>
		<?php // WordPress bildirimleri bu isaretin altina tasir (ust seridin icine degil). ?>
		<hr class="wp-header-end" />

		<?php nwcs_render_cache_note(); ?>

		<?php if ( isset( $notices[ $result ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notices[ $result ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $result ][1] ); ?></p></div>
		<?php endif; ?>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
		$fresh = isset( $_GET['zorunlu'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_GET['zorunlu'] ) ) ) ) : array();
		?>
		<?php foreach ( $fresh as $fresh_key ) : ?>
			<?php if ( isset( $headings[ $fresh_key ] ) ) : ?>
				<?php
				$blocked = array_filter( (array) ( $gaps[ $fresh_key ] ?? array() ), static fn( array $gap ): bool => $gap[0] > 0 );
				arsort( $blocked ); // en cok bos olan once
				$blocked = array_keys( $blocked );
				?>
				<div class="notice notice-<?php echo $blocked ? 'warning' : 'success'; ?> is-dismissible">
					<p>
						<?php
						echo esc_html(
							$blocked
								? sprintf( '“%s” zorunlu yapıldı. Şu kategorilerde bu başlık bazı ürünlerde boş; Excel yüklemesinde o ürünlerin satırları doldurulana kadar yüklenmez, diğer satırlar yüklenir: %s.', $headings[ $fresh_key ]['label'], nwcs_heading_gap_names( $blocked, 6 ) )
								: sprintf( '“%s” zorunlu yapıldı. Bütün ürünlerde dolu; hiçbir kategorinin Excel’i takılmaz.', $headings[ $fresh_key ]['label'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<section class="nwcs-pool__card nwcs-headings__card">
			<p class="nwcs-headings__lead">
				Detay başlıkları, ürün sayfasındaki teknik detay satırlarının adlarıdır (Ahşap Cinsi, Kurulum…).
				Buradaki sıra, sitelerdeki listenin sırasıdır. <strong>Zorunlu</strong> başlık, kategorilerin Excel dosyasında her üründe dolu olmalıdır; boş olan satır yüklenmez.
				Bir başlığın “Zorunlu” kutusunu işaretleyince kaç üründe boş olduğu görünür: kaydetmeden önce o ürünleri doldurmanız gerekir.
			</p>

			<?php if ( ! $headings ) : ?>
				<p class="nwcs-empty">Henüz başlık yok. Aşağıdan ekleyin ya da bir ürünün formunda “Detay başlıkları” bölümüne yazın.</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-hform">
					<input type="hidden" name="action" value="nwcs_headings" />
					<input type="hidden" name="tablo" value="1" />
					<?php wp_nonce_field( 'nwcs_headings' ); ?>
					<?php // Enter tusu tablodaki ilk "yukari" dugmesini degil, kaydetmeyi calistirsin. ?>
					<button type="submit" name="islem" value="save" class="screen-reader-text" tabindex="-1">Değişiklikleri kaydet</button>

					<div class="nwcs-headings__scroll">
						<table class="nwcs-table nwcs-headings__table">
							<thead>
								<tr>
									<th scope="col">Sıra</th>
									<th scope="col">Başlık</th>
									<th scope="col">Zorunlu</th>
									<th scope="col">Kullanıldığı yer</th>
									<th scope="col">Boş olduğu kategoriler</th>
									<th scope="col"><span class="screen-reader-text">İşlemler</span></th>
								</tr>
							</thead>
							<tbody>
								<?php $keys = array_keys( $headings ); ?>
								<?php foreach ( $headings as $key => $row ) : ?>
									<?php
									$used  = (int) ( $usage[ $key ]['count'] ?? 0 );
									$cats  = (array) ( $usage[ $key ]['categories'] ?? array() );
									$empty = max( 0, $total - $used );
									$first = $key === $keys[0];
									$last  = $key === end( $keys );
									?>
									<tr id="h-<?php echo esc_attr( $key ); ?>"<?php echo $row['required'] ? ' class="is-required"' : ''; ?> data-nwcs-hrow>
										<td class="nwcs-headings__order">
											<input type="hidden" name="sira[]" value="<?php echo esc_attr( $key ); ?>" />
											<button type="submit" class="nwcs-move" name="islem" value="up:<?php echo esc_attr( $key ); ?>" <?php disabled( $first ); ?>
												aria-label="<?php echo esc_attr( $row['label'] ); ?> başlığını yukarı taşı" <?php echo $focus === $key ? 'data-nwcs-focus' : ''; ?>>↑</button>
											<button type="submit" class="nwcs-move" name="islem" value="down:<?php echo esc_attr( $key ); ?>" <?php disabled( $last ); ?>
												aria-label="<?php echo esc_attr( $row['label'] ); ?> başlığını aşağı taşı">↓</button>
										</td>
										<th scope="row" class="nwcs-headings__name"><?php echo esc_html( $row['label'] ); ?></th>
										<td class="nwcs-headings__req">
											<label>
												<input type="checkbox" name="zorunlu[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $row['required'] ); ?> />
												Zorunlu
											</label>
											<?php if ( $empty ) : ?>
												<?php // Yalnizca kutu isaretliyken gorunur (CSS :has): isaretleyince sonucu hemen soyler. ?>
												<span class="nwcs-headings__note is-warn">
													<?php echo esc_html( sprintf( '%d üründe boş: bu ürünlerin Excel satırı yüklenmez.', $empty ) ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td class="nwcs-headings__use">
											<?php if ( $used ) : ?>
												<strong><?php echo (int) $used; ?> ürün</strong>
												<span>
													<?php
													$names = array();
													foreach ( array_slice( $cats, 0, 3, true ) as $name => $n ) {
														$names[] = $name . ' (' . (int) $n . ')';
													}
													echo esc_html( implode( ', ', $names ) . ( count( $cats ) > 3 ? sprintf( ' ve %d kategori daha', count( $cats ) - 3 ) : '' ) );
													?>
												</span>
											<?php else : ?>
												<span>Hiçbir üründe yok</span>
											<?php endif; ?>
										</td>
										<td class="nwcs-headings__gaps">
											<?php
											$row_gaps = array_filter( (array) ( $gaps[ $key ] ?? array() ), static fn( array $gap ): bool => $gap[0] > 0 );
											uasort( $row_gaps, static fn( array $a, array $b ): int => $b[0] <=> $a[0] ?: $b[1] <=> $a[1] );
											$shown = array();

											foreach ( array_slice( $row_gaps, 0, 2, true ) as $gap_name => $gap ) {
												$shown[] = sprintf( '%s: %d/%d boş', $gap_name, $gap[0], $gap[1] );
											}
											?>
											<?php if ( $shown ) : ?>
												<span><?php echo esc_html( implode( ', ', $shown ) . ( count( $row_gaps ) > 2 ? sprintf( ' ve %d kategori daha', count( $row_gaps ) - 2 ) : '' ) ); ?></span>
											<?php else : ?>
												<span class="nwcs-headings__full">Her kategoride dolu</span>
											<?php endif; ?>
										</td>
										<td class="nwcs-headings__tools">
											<details class="nwcs-headings__edit">
												<summary>Düzenle</summary>
												<div class="nwcs-headings__panel">
													<label class="nwcs-sublabel" for="ad-<?php echo esc_attr( $key ); ?>">Yeni ad</label>
													<div class="nwcs-headings__inline">
														<input class="nwcs-input" type="text" id="ad-<?php echo esc_attr( $key ); ?>" name="ad[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $row['label'] ); ?>" form="nwcs-hform-<?php echo esc_attr( $key ); ?>-rename" />
														<button type="submit" class="button" name="islem" value="rename:<?php echo esc_attr( $key ); ?>" form="nwcs-hform-<?php echo esc_attr( $key ); ?>-rename">Yeniden adlandır</button>
													</div>
													<p class="nwcs-hint">Bu başlığı kullanan <?php echo (int) $used; ?> üründe de değişir.</p>

													<?php if ( count( $headings ) > 1 ) : ?>
														<label class="nwcs-sublabel" for="hedef-<?php echo esc_attr( $key ); ?>">Başka bir başlıkla birleştir</label>
														<div class="nwcs-headings__inline">
															<select class="nwcs-input" id="hedef-<?php echo esc_attr( $key ); ?>" name="hedef[<?php echo esc_attr( $key ); ?>]" form="nwcs-hform-<?php echo esc_attr( $key ); ?>-merge">
																<option value="">— Başlık seçin —</option>
																<?php foreach ( $headings as $other_key => $other ) : ?>
																	<?php if ( $other_key !== $key ) : ?>
																		<option value="<?php echo esc_attr( $other_key ); ?>"><?php echo esc_html( $other['label'] ); ?></option>
																	<?php endif; ?>
																<?php endforeach; ?>
															</select>
															<button type="submit" class="button" name="islem" value="merge:<?php echo esc_attr( $key ); ?>" form="nwcs-hform-<?php echo esc_attr( $key ); ?>-merge">Birleştir</button>
														</div>
														<p class="nwcs-hint">“<?php echo esc_html( $row['label'] ); ?>” değerleri seçtiğiniz başlığa taşınır ve bu başlık kalkar. İkisi de doluysa seçtiğiniz başlığın değeri kalır.</p>
													<?php endif; ?>

													<?php if ( ! $used ) : ?>
														<button type="submit" class="button nwcs-row__delete" name="islem" value="delete:<?php echo esc_attr( $key ); ?>" form="nwcs-hform-<?php echo esc_attr( $key ); ?>-delete">Başlığı sil</button>
													<?php else : ?>
														<p class="nwcs-hint">Ürünlerde kullanıldığı için silinemez.</p>
													<?php endif; ?>
												</div>
											</details>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div class="nwcs-actions">
						<button type="submit" class="button button-primary" name="islem" value="save">Değişiklikleri kaydet</button>
						<span class="nwcs-actions__note" data-nwcs-hstatus aria-live="polite">Zorunlu işaretleri ve sıra bu düğmeyle kaydedilir.</span>
					</div>
				</form>

				<?php // Satir islemleri icin ayri formlar: tablodaki alanlar form="" ile baglanir. ?>
				<?php foreach ( array_keys( $headings ) as $key ) : ?>
					<?php foreach ( array( 'rename', 'merge', 'delete' ) as $kind ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-hform-<?php echo esc_attr( $key . '-' . $kind ); ?>"<?php echo 'delete' === $kind ? ' onsubmit="return confirm(\'Başlık silinsin mi?\');"' : ''; ?> hidden>
							<input type="hidden" name="action" value="nwcs_headings" />
							<?php wp_nonce_field( 'nwcs_headings', '_wpnonce', true, true ); ?>
						</form>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<section class="nwcs-pool__card nwcs-headings__card">
			<h2 class="nwcs-pool__title">Yeni başlık ekle</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nwcs_headings" />
				<input type="hidden" name="islem" value="add" />
				<?php wp_nonce_field( 'nwcs_headings' ); ?>
				<label class="nwcs-sublabel" for="nwcs-h-new">Başlık adı</label>
				<div class="nwcs-headings__inline">
					<input class="nwcs-input" type="text" id="nwcs-h-new" name="yeni" placeholder="örn. Isıtma" required />
					<label class="nwcs-headings__check"><input type="checkbox" name="yeni_zorunlu" value="1" /> Zorunlu</label>
					<button type="submit" class="button">Başlık ekle</button>
				</div>
			</form>
		</section>
	</div>
	<?php
}

/**
 * Basliklarin kategorilerdeki boslugu (yayindaki urunler): anahtar =>
 * [ kategori adi => [ bos, toplam ] ]. "Zorunlu" yapmadan once hangi
 * kategorilerin Excel'inin takilacagini gosterir.
 *
 * @return array<string, array<string, array{0:int, 1:int}>>
 */
function nwcs_heading_gaps(): array {
	$totals = array();
	$filled = array();

	foreach ( nwcs_pool_products() as $product ) {
		$have = array();

		foreach ( (array) $product['details'] as $row ) {
			if ( '' !== trim( (string) ( $row['value'] ?? '' ) ) ) {
				$have[ nwcs_heading_key( (string) $row['label'] ) ] = true;
			}
		}

		foreach ( (array) $product['categories'] as $name ) {
			$totals[ $name ] = ( $totals[ $name ] ?? 0 ) + 1;

			foreach ( array_keys( $have ) as $key ) {
				$filled[ $key ][ $name ] = ( $filled[ $key ][ $name ] ?? 0 ) + 1;
			}
		}
	}

	$out = array();

	foreach ( array_keys( nwcs_product_headings() ) as $key ) {
		foreach ( $totals as $name => $total ) {
			$out[ $key ][ $name ] = array( $total - (int) ( $filled[ $key ][ $name ] ?? 0 ), $total );
		}
	}

	return $out;
}

/**
 * "A, B, C ve 4 kategori daha" bicimi.
 */
function nwcs_heading_gap_names( array $names, int $limit ): string {
	$shown = array_slice( $names, 0, $limit );
	$more  = count( $names ) - count( $shown );

	return implode( ', ', $shown ) . ( $more > 0 ? sprintf( ' ve %d kategori daha', $more ) : '' );
}
