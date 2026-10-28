<?php
/**
 * Panel alan bilesenlerinin ciktisi.
 *
 * Girdi adlandirmasi:
 *   basit alan          fields[<alan>]
 *   tekrarli satir      fields[<alan>][<satir>][<alt_alan>]  + gizli _sort
 *   gorsel yukleme      img_upload__<yol>       ( yol: alan  veya  alan__satir__altalan )
 *   gorsel alt metni    img_alt__<yol>
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ic ice alanlar icin duz yol anahtari.
 */
function nwcs_field_path( string $field_key, string $row_id = '', string $sub_key = '' ): string {
	return '' === $row_id ? $field_key : $field_key . '__' . $row_id . '__' . $sub_key;
}

/**
 * Girdi adi uretir.
 */
function nwcs_field_name( string $field_key, string $row_id = '', string $sub_key = '' ): string {
	return '' === $row_id
		? 'fields[' . $field_key . ']'
		: 'fields[' . $field_key . '][' . $row_id . '][' . $sub_key . ']';
}

/**
 * Tek bir alani cizer.
 */
function nwcs_render_field( string $field_key, array $definition, $value, array $media, int $blog_id, string $row_id = '', string $sub_key = '' ): void {
	$type  = $definition['type'] ?? 'text';
	$name  = nwcs_field_name( $field_key, $row_id, $sub_key );
	$path  = nwcs_field_path( $field_key, $row_id, $sub_key );
	$id    = 'nwcs-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $path ) );
	$label = $definition['label'] ?? $field_key;

	if ( 'repeater' === $type ) {
		nwcs_render_repeater( $field_key, $definition, $value, $media, $blog_id );

		return;
	}

	if ( 'products' === $type ) {
		nwcs_render_products_field( $definition, $blog_id );

		return;
	}

	if ( 'product_photo' === $type ) {
		nwcs_render_product_photo_field( $field_key, $definition, is_array( $value ) ? $value : array(), $blog_id );

		return;
	}

	// data-nwcs-field: onizlemeden gelen tiklamada dogru alana odaklanmak icin.
	printf(
		'<div class="nwcs-field nwcs-field--%1$s" data-nwcs-field="%2$s"%3$s>',
		esc_attr( $type ),
		esc_attr( '' === $row_id ? $field_key : $field_key . '.' . $sub_key ),
		'' === $row_id ? '' : ' data-nwcs-field-row="' . esc_attr( $row_id ) . '"'
	);
	printf( '<label class="nwcs-field__label" for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );

	// Istege bagli: gri yer tutucu (orn. otomatik SEO degeri) ve karakter sayaci.
	$extra = '';
	if ( ! empty( $definition['placeholder'] ) ) {
		$extra .= ' placeholder="' . esc_attr( (string) $definition['placeholder'] ) . '"';
	}
	if ( ! empty( $definition['count'] ) ) {
		$extra .= ' data-nwcs-count="' . esc_attr( (string) (int) $definition['count'] ) . '"';
	}

	switch ( $type ) {
		case 'textarea':
			printf(
				'<textarea class="nwcs-input" id="%s" name="%s" rows="4"%s>%s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				$extra, // phpcs:ignore WordPress.Security.EscapingOutput -- yukarida kacirildi.
				esc_textarea( (string) $value )
			);
			break;

		case 'url':
			printf(
				'<input class="nwcs-input" type="text" id="%s" name="%s" value="%s" inputmode="url" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
			echo '<p class="nwcs-hint">Site içi yol (<code>/kurumsal/</code>), bağlantı (<code>#teklif</code>), <code>tel:</code>, <code>mailto:</code> veya tam adres yazabilirsiniz.</p>';
			break;

		case 'image':
			nwcs_render_image_field( $id, $name, $path, (int) $value, $media );
			break;

		case 'icon':
			nwcs_render_icon_field( $id, $name, (string) $value );
			break;

		case 'text':
		default:
			printf(
				'<input class="nwcs-input" type="text" id="%s" name="%s" value="%s"%s />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				$extra // phpcs:ignore WordPress.Security.EscapingOutput -- yukarida kacirildi.
			);
			break;
	}

	if ( ! empty( $definition['hint'] ) ) {
		printf( '<p class="nwcs-hint">%s</p>', esc_html( (string) $definition['hint'] ) );
	}

	echo '</div>';
}

/**
 * Gorsel alani: mevcut medyadan sec, yeni yukle, kaldir, alt metin.
 */
function nwcs_render_image_field( string $id, string $name, string $path, int $value, array $media ): void {
	$current = $media[ $value ] ?? null;
	?>
	<div class="nwcs-image">
		<div class="nwcs-image__preview">
			<?php if ( $current && $current['thumb'] ) : ?>
				<img src="<?php echo esc_url( $current['thumb'] ); ?>" alt="" />
			<?php else : ?>
				<span class="nwcs-image__empty">Görsel yok</span>
			<?php endif; ?>
		</div>

		<div class="nwcs-image__controls">
			<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>">Medya kitaplığından seç</label>
			<select class="nwcs-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="0"><?php echo esc_html( '— Görsel yok (kaldır) —' ); ?></option>
				<?php foreach ( $media as $item ) : ?>
					<option value="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php selected( $item['id'], $value ); ?>>
						<?php echo esc_html( $item['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>-upload">Yeni görsel yükle</label>
			<input class="nwcs-file" type="file" id="<?php echo esc_attr( $id ); ?>-upload"
				name="img_upload__<?php echo esc_attr( $path ); ?>" accept="image/*" />
			<p class="nwcs-hint">Yeni dosya seçerseniz yukarıdaki seçimin yerine o kullanılır.</p>

			<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>-alt">Alt metin (erişilebilirlik)</label>
			<input class="nwcs-input" type="text" id="<?php echo esc_attr( $id ); ?>-alt"
				name="img_alt__<?php echo esc_attr( $path ); ?>"
				value="<?php echo esc_attr( $current['alt'] ?? '' ); ?>" />
		</div>
	</div>
	<?php
}

/**
 * Paylasim fotografi: once bu sitede gorunen urunlerden (alan 'category'
 * verilmisse yalnizca o havuz kategorisindekiler) bir urun, sonra o urunun
 * galerisinden bir fotograf secilir. Deger: array( product, image ) — ikisi de
 * havuz sitesindeki kimlikler. Fotograf listesi butun urunler icin basilir;
 * panel betigi (admin.js) secili urune gore daraltir ve onizlemeyi gunceller.
 */
function nwcs_render_product_photo_field( string $field_key, array $definition, array $value, int $blog_id ): void {
	$id       = 'nwcs-' . sanitize_html_class( $field_key );
	$name     = nwcs_field_name( $field_key );
	$category = trim( (string) ( $definition['category'] ?? '' ) );
	$picked   = array( 'product' => (int) ( $value['product'] ?? 0 ), 'image' => (int) ( $value['image'] ?? 0 ) );
	$products = array();
	$preview  = '';

	foreach ( nwcs_site_products( $blog_id ) as $product ) {
		if ( '' !== $category && ! in_array( $category, (array) $product['categories'], true ) ) {
			continue;
		}

		$images = array_values( array_filter( (array) ( $product['images'] ?? array() ), static fn( $image ): bool => is_array( $image ) && ! empty( $image['url'] ) && ! empty( $image['id'] ) ) );

		if ( ! $images ) {
			continue;
		}

		$products[] = array( 'id' => (int) $product['id'], 'title' => (string) $product['title'], 'code' => (string) ( $product['code'] ?? '' ), 'images' => $images );

		if ( (int) $product['id'] === $picked['product'] ) {
			foreach ( $images as $image ) {
				if ( (int) $image['id'] === $picked['image'] ) {
					$preview = (string) $image['url'];
				}
			}

			$preview = '' !== $preview ? $preview : (string) $images[0]['url'];
		}
	}
	?>
	<div class="nwcs-field nwcs-field--product_photo" data-nwcs-field="<?php echo esc_attr( $field_key ); ?>" data-nwcs-product-photo>
		<label class="nwcs-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) ( $definition['label'] ?? $field_key ) ); ?></label>

		<?php if ( ! $products ) : ?>
			<p class="nwcs-hint">Bu sitede <?php echo '' !== $category ? 'bu kategoride ' : ''; ?>fotoğraflı ürün yok. Ürünü ve fotoğrafını <a href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu</a>'nda ekleyin.</p>
		<?php else : ?>
			<div class="nwcs-image">
				<div class="nwcs-image__preview" data-nwcs-pp-preview>
					<?php if ( '' !== $preview ) : ?>
						<img src="<?php echo esc_url( $preview ); ?>" alt="" />
					<?php else : ?>
						<span class="nwcs-image__empty">Ürün seçilmedi</span>
					<?php endif; ?>
				</div>

				<div class="nwcs-image__controls">
					<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>">Ürün</label>
					<select class="nwcs-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[product]" data-nwcs-pp-product>
						<option value="0">— Seçilmedi —</option>
						<?php foreach ( $products as $product ) : ?>
							<option value="<?php echo esc_attr( (string) $product['id'] ); ?>" <?php selected( $product['id'], $picked['product'] ); ?>>
								<?php echo esc_html( $product['title'] . ( '' !== $product['code'] ? ' (' . $product['code'] . ')' : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>-image">Fotoğraf</label>
					<select class="nwcs-input" id="<?php echo esc_attr( $id ); ?>-image" name="<?php echo esc_attr( $name ); ?>[image]" data-nwcs-pp-image>
						<?php foreach ( $products as $product ) : ?>
							<optgroup label="<?php echo esc_attr( $product['title'] ); ?>" data-nwcs-pp-of="<?php echo esc_attr( (string) $product['id'] ); ?>"<?php echo $product['id'] === $picked['product'] ? '' : ' hidden'; ?>>
								<?php foreach ( $product['images'] as $index => $image ) : ?>
									<option value="<?php echo esc_attr( (string) $image['id'] ); ?>" data-thumb="<?php echo esc_url( (string) $image['url'] ); ?>" <?php selected( $product['id'] === $picked['product'] && (int) $image['id'] === $picked['image'] ); ?>>
										<?php echo esc_html( sprintf( '%d. fotoğraf', $index + 1 ) . ( '' !== trim( (string) ( $image['alt'] ?? '' ) ) ? ' — ' . $image['alt'] : '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $definition['hint'] ) ) : ?>
			<p class="nwcs-hint"><?php echo esc_html( (string) $definition['hint'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Ikon alani: sinirli listeden secim.
 *
 * Varsayilan olarak yalnizca secili ikon gosterilir; liste "Değiştir" ile acilir.
 * Boylece form sikisik gorunmez.
 */
function nwcs_render_icon_field( string $id, string $name, string $value ): void {
	$library = nwcs_icon_library();
	$current = $library[ $value ] ?? null;
	?>
	<div class="nwcs-iconpick">
		<span class="nwcs-iconpick__current" data-nwcs-icon-current>
			<?php if ( $current ) : ?>
				<?php nwcs_the_icon( $value, 'nwcs-icon__svg', 20 ); ?>
				<?php echo esc_html( $current['label'] ); ?>
			<?php else : ?>
				<?php echo esc_html( 'İkon yok' ); ?>
			<?php endif; ?>
		</span>
		<button type="button" class="nwcs-iconpick__toggle" data-nwcs-icon-toggle aria-expanded="false">Değiştir</button>
	</div>
	<?php

	echo '<div class="nwcs-icons" id="' . esc_attr( $id ) . '">';

	printf(
		'<label class="nwcs-icon nwcs-icon--none%s"><input type="radio" name="%s" value="" %s /><span class="nwcs-icon__box">yok</span></label>',
		'' === $value ? ' is-active' : '',
		esc_attr( $name ),
		checked( '', $value, false )
	);

	foreach ( nwcs_icon_library() as $key => $icon ) {
		printf(
			'<label class="nwcs-icon%s" title="%s"><input type="radio" name="%s" value="%s" %s /><span class="nwcs-icon__box">%s</span><span class="nwcs-icon__label">%s</span></label>',
			$key === $value ? ' is-active' : '',
			esc_attr( $icon['label'] ),
			esc_attr( $name ),
			esc_attr( $key ),
			checked( $key, $value, false ),
			nwcs_icon_svg( $key, 'nwcs-icon__svg', 22 ), // phpcs:ignore WordPress.Security.EscapingOutput -- sabit ikon listesi.
			esc_html( $icon['label'] )
		);
	}

	echo '</div>';
}

/**
 * Tekrarli bilesen: satir ekle/sil, yukari/asagi tasi.
 */
function nwcs_render_repeater( string $field_key, array $definition, $value, array $media, int $blog_id ): void {
	$rows      = is_array( $value ) ? array_values( $value ) : array();
	$sub_defs  = $definition['fields'] ?? array();
	$max       = (int) ( $definition['max'] ?? 20 );
	$sortable  = true;
	?>
	<div class="nwcs-repeater" data-nwcs-repeater data-nwcs-field="<?php echo esc_attr( $field_key ); ?>" data-field="<?php echo esc_attr( $field_key ); ?>" data-max="<?php echo esc_attr( (string) $max ); ?>">
		<div class="nwcs-repeater__head">
			<span class="nwcs-field__label"><?php echo esc_html( $definition['label'] ?? $field_key ); ?></span>
			<span class="nwcs-repeater__meta">en fazla <?php echo (int) $max; ?> satır<?php echo $sortable ? ' · sırayı yukarı/aşağı taşıyabilirsiniz' : ''; ?></span>
		</div>

		<?php if ( ! empty( $definition['hint'] ) ) : ?>
			<p class="nwcs-hint"><?php echo esc_html( (string) $definition['hint'] ); ?></p>
		<?php endif; ?>

		<div class="nwcs-repeater__rows" data-nwcs-rows>
			<?php
			foreach ( $rows as $index => $row ) {
				nwcs_render_repeater_row( $field_key, $sub_defs, (array) $row, 'r' . $index, $index, $media, $blog_id );
			}
			?>
		</div>

		<button type="button" class="button nwcs-repeater__add" data-nwcs-add-row>+ Satır ekle</button>

		<template data-nwcs-row-template>
			<?php
			$empty = array();
			foreach ( $sub_defs as $sub_key => $sub_def ) {
				$empty[ $sub_key ] = 'image' === ( $sub_def['type'] ?? 'text' ) ? 0 : '';
			}
			nwcs_render_repeater_row( $field_key, $sub_defs, $empty, '__ROW__', 0, $media, $blog_id );
			?>
		</template>
	</div>
	<?php
}

/**
 * Tek bir tekrarli satir.
 */
function nwcs_render_repeater_row( string $field_key, array $sub_defs, array $row, string $row_id, int $sort, array $media, int $blog_id ): void {
	?>
	<div class="nwcs-row" data-nwcs-row data-nwcs-row-index="<?php echo esc_attr( (string) $sort ); ?>">
		<div class="nwcs-row__bar">
			<span class="nwcs-row__handle" data-nwcs-row-number><?php echo esc_html( (string) ( $sort + 1 ) ); ?></span>
			<div class="nwcs-row__tools">
				<button type="button" class="button button-small" data-nwcs-move="up" title="Yukarı taşı" aria-label="Yukarı taşı">↑</button>
				<button type="button" class="button button-small" data-nwcs-move="down" title="Aşağı taşı" aria-label="Aşağı taşı">↓</button>
				<button type="button" class="button button-small nwcs-row__delete" data-nwcs-remove-row>Sil</button>
			</div>
		</div>

		<input type="hidden" name="fields[<?php echo esc_attr( $field_key ); ?>][<?php echo esc_attr( $row_id ); ?>][_sort]"
			value="<?php echo esc_attr( (string) $sort ); ?>" data-nwcs-sort />

		<div class="nwcs-row__fields">
			<?php
			foreach ( $sub_defs as $sub_key => $sub_def ) {
				nwcs_render_field( $field_key, $sub_def, $row[ $sub_key ] ?? '', $media, $blog_id, $row_id, $sub_key );
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Urun havuzu secimi alani.
 *
 * Deger nwcs_content icinde degil, sitenin kendi urun ayarlarinda tutulur
 * (nwcs_products_mode / _selected / _overrides). Bu yuzden ayri POST
 * anahtarlari kullanilir: products[...]
 */
/**
 * Studyo urun satirinda uc nokta: fotograf, aciklama, teknik detay (dolu /
 * bos). Fotografi yoksa kategorinin Fotograf Kutusu'na baglanti.
 */
function nwcs_render_gap_dots( array $product ): void {
	$gaps   = nwcs_product_gaps( $product );
	$labels = nwcs_product_gap_labels();
	$say    = $gaps ? 'Eksik: ' . implode( ', ', array_map( static fn( string $gap ): string => $labels[ $gap ], $gaps ) ) : 'Fotoğraf, açıklama ve detay var';
	$dots   = '';

	// Sira: fotograf, aciklama, detay. Dolu nokta = var. (Yuzlerce satir: isaretleme kisa tutulur.)
	foreach ( array_keys( $labels ) as $key ) {
		$dots .= in_array( $key, $gaps, true ) ? '<i></i>' : '<i class="is-on"></i>';
	}

	printf( ' <span class="nwcs-gaps" title="%1$s" aria-hidden="true">%2$s</span><span class="screen-reader-text">%1$s</span>', esc_attr( $say ), $dots ); // phpcs:ignore WordPress.Security.EscapingOutput -- sabit isaretleme.

	$first = (string) array_key_first( (array) ( $product['categories'] ?? array() ) );

	if ( in_array( 'photo', $gaps, true ) && '' !== $first ) {
		printf(
			' <a class="nwcs-gaps__add" href="%s" target="_blank" rel="noopener">Fotoğraf ekle ↗</a>',
			esc_url( nwcs_pool_categories_url( array( 'kategori' => $first ) ) . '#nwcs-fotograf' )
		);
	}
}

function nwcs_render_products_field( array $definition, int $blog_id ): void {
	$pool     = nwcs_pool_products();
	$settings = nwcs_site_product_settings( $blog_id );
	$media    = nwcs_pool_media();

	// Once secili olanlar (kayitli sirada), sonra kalanlar.
	$ordered = array();

	foreach ( $settings['selected'] as $id ) {
		if ( isset( $pool[ $id ] ) ) {
			$ordered[ $id ] = $pool[ $id ];
		}
	}

	foreach ( $pool as $id => $product ) {
		if ( ! isset( $ordered[ $id ] ) ) {
			$ordered[ $id ] = $product;
		}
	}

	// Kategori sayfasi: yalnizca o kategorinin urunleri (kayit da yalnizca onlari yazar).
	$category = trim( (string) ( $definition['category'] ?? '' ) );

	if ( '' !== $category ) {
		$ordered = array_filter( $ordered, static fn( array $product ): bool => in_array( $category, (array) $product['categories'], true ) );
	}
	?>
	<div class="nwcs-field nwcs-field--products" data-nwcs-field="pool">
		<span class="nwcs-field__label"><?php echo esc_html( $definition['label'] ?? 'Ürünler' ); ?></span>

		<?php if ( ! $pool ) : ?>
			<p class="nwcs-hint">
				Henüz ürün yok.
				<a href="<?php echo esc_url( nwcs_pool_url( array( 'yeni' => 1 ) ) ); ?>">Ürün Havuzu</a> sayfasından ekleyin.
			</p>
		<?php else : ?>

			<?php if ( '' !== $category ) : ?>
				<p class="nwcs-hint">
					Bu listede yalnızca <strong><?php echo esc_html( $category ); ?></strong> kategorisindeki
					<?php echo (int) count( $ordered ); ?> ürün var. İşareti kaldırılan ürün bu sitede görünmez; oklarla sırayı değiştirin.
					Diğer kategorilere dokunulmaz.
				</p>
			<?php else : ?>
			<div class="nwcs-modes" role="radiogroup" aria-label="Ürün seçim kipi">
				<label class="nwcs-mode<?php echo 'all' === $settings['mode'] ? ' is-active' : ''; ?>">
					<input type="radio" name="products[mode]" value="all" <?php checked( 'all', $settings['mode'] ); ?> data-nwcs-mode />
					<span class="nwcs-mode__title">Hepsi</span>
					<span class="nwcs-mode__text">Havuzdaki tüm ürünler görünür; yeni ürün eklenince otomatik çıkar.</span>
				</label>
				<label class="nwcs-mode<?php echo 'selected' === $settings['mode'] ? ' is-active' : ''; ?>">
					<input type="radio" name="products[mode]" value="selected" <?php checked( 'selected', $settings['mode'] ); ?> data-nwcs-mode />
					<span class="nwcs-mode__title">Seçilenler</span>
					<span class="nwcs-mode__text">Yalnızca işaretledikleriniz; sırayı da siz belirlersiniz.</span>
				</label>
			</div>
			<?php endif; ?>

			<p class="nwcs-hint">
				Ürünün kendisi <a href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürün Havuzu</a>'nda düzenlenir.
				Buradaki alanlar <strong>yalnızca bu site için</strong> özelleştirmedir; boş bırakılan alan Ürün Havuzu’ndaki değeri kullanır.
			</p>

			<?php // Uzun listede arama ve "yalnizca secilenler" suzgeci; kayda etkisi yok, yalnizca gorunumu daraltir. ?>
			<div class="nwcs-products__tools">
				<input class="nwcs-input" type="search" data-nwcs-products-search
					placeholder="Ürün adı veya kodu ara…" aria-label="Listede ürün ara" />
				<label class="nwcs-products__only">
					<input type="checkbox" data-nwcs-products-only /> Yalnızca seçilenler
				</label>
				<span class="nwcs-products__count" data-nwcs-products-count aria-live="polite"></span>
			</div>

			<template data-nwcs-media-options>
				<option value="0">— Havuzdaki görsel —</option>
				<?php foreach ( $media as $item ) : ?>
					<option value="<?php echo esc_attr( (string) $item['id'] ); ?>"><?php echo esc_html( $item['title'] ); ?></option>
				<?php endforeach; ?>
			</template>

			<div class="nwcs-products" data-nwcs-products>
				<?php
				// "Hepsi" kipinde her urun gosterildigi icin kutular da isaretli gelir.
				$show_all = 'all' === $settings['mode'];

				foreach ( $ordered as $id => $product ) :
					$override    = $settings['overrides'][ $id ] ?? array();
					$is_selected = $show_all || in_array( $id, $settings['selected'], true );
					?>
					<div class="nwcs-product<?php echo $is_selected ? ' is-selected' : ''; ?>" data-nwcs-product
						data-search="<?php echo esc_attr( nwcs_search_fold( $product['title'] . ' ' . $product['code'] . ' ' . implode( ' ', (array) $product['categories'] ) ) ); ?>">
						<div class="nwcs-product__bar">
							<label class="nwcs-product__pick">
								<input type="checkbox" name="products[selected][]" value="<?php echo esc_attr( (string) $id ); ?>"
									<?php checked( $is_selected ); ?> data-nwcs-product-pick />
								<span class="nwcs-product__thumb">
									<?php if ( $product['image']['url'] ) : ?>
										<img src="<?php echo esc_url( $product['image']['url'] ); ?>" alt="" />
									<?php endif; ?>
								</span>
								<span class="nwcs-product__name">
									<?php echo esc_html( $product['title'] ); ?>
									<small>
										<?php
										echo '' !== $product['code'] ? esc_html( $product['code'] ) . ' · ' : '';
										echo '' !== trim( $product['price'] )
											? esc_html( $product['price'] )
											: '<em>Teklif al</em>'; // phpcs:ignore WordPress.Security.EscapingOutput -- sabit metin.
										nwcs_render_gap_dots( $product );
										?>
									</small>
								</span>
							</label>

							<div class="nwcs-product__tools">
								<button type="button" class="nwcs-move" data-nwcs-product-move="top" aria-label="En üste taşı" title="En üste taşı">⤒</button>
								<button type="button" class="nwcs-move" data-nwcs-product-move="up" aria-label="Yukarı taşı">↑</button>
								<button type="button" class="nwcs-move" data-nwcs-product-move="down" aria-label="Aşağı taşı">↓</button>
								<button type="button" class="nwcs-iconpick__toggle" data-nwcs-product-toggle aria-expanded="false">Özelleştirilmiş</button>
							</div>
						</div>

						<div class="nwcs-product__overrides">
							<p class="nwcs-hint">
								Kod, özellikler, detay metni ve görseller tüm sitelerde ortak:
								<a href="<?php echo esc_url( nwcs_pool_url( array( 'urun' => $id ) ) ); ?>" target="_blank" rel="noopener">Ürün Havuzu’nda düzenle ↗</a>
							</p>
							<?php
							// Sayfa adresi burada yalnizca gosterilir; tek duzenleme yeri
							// Urun Havuzu -> urun formu -> Ozellestirmeler (bu sitenin kutusu).
							$custom_slug = nwcs_override_slug( $override );
							$site_slug   = '' !== $custom_slug ? $custom_slug : (string) $product['slug'];
							?>
							<p class="nwcs-product__address">
								Sayfa adresi: <code>/urun/<?php echo esc_html( $site_slug ); ?>/</code><?php echo '' !== $custom_slug ? ' (bu siteye özel)' : ''; ?>
								— <a href="<?php echo esc_url( nwcs_pool_url( array( 'urun' => $id ) ) . '#nwcs-ovr-' . $blog_id ); ?>" target="_blank" rel="noopener">değiştir<span class="screen-reader-text"> (yeni sekmede açılır)</span> ↗</a>
							</p>
							<?php if ( ! empty( $override['hidden'] ) ) : ?>
								<?php // Gizleme Urun Havuzu'ndaki toplu islemden yonetilir; deger burada korunur. ?>
								<input type="hidden" name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][hidden]" value="1" />
								<p class="nwcs-hint">
									Bu ürün <strong>Ürün Havuzu</strong>'ndaki toplu işlemle bu sitede gizlenmiş.
								</p>
							<?php endif; ?>

							<div class="nwcs-field">
								<label class="nwcs-sublabel">Ürün adı (bu sitede)</label>
								<input class="nwcs-input" type="text"
									name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][title]"
									value="<?php echo esc_attr( $override['title'] ?? '' ); ?>"
									placeholder="<?php echo esc_attr( $product['title'] ); ?>" />
							</div>

							<div class="nwcs-field">
								<label class="nwcs-sublabel">Açıklama (bu sitede)</label>
								<textarea class="nwcs-input" rows="2"
									name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][short]"
									placeholder="<?php echo esc_attr( $product['short'] ); ?>"><?php echo esc_textarea( $override['short'] ?? '' ); ?></textarea>
							</div>

							<div class="nwcs-field">
								<label class="nwcs-product__priceflag">
									<input type="checkbox" value="1"
										name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][price_override]"
										<?php checked( ! empty( $override['price_override'] ) ); ?> />
									Fiyatı bu sitede değiştir
								</label>
								<input class="nwcs-input" type="text"
									name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][price]"
									value="<?php echo esc_attr( $override['price'] ?? '' ); ?>"
									placeholder="<?php echo esc_attr( $product['price'] ); ?>" />
								<p class="nwcs-hint">İşaretleyip boş bırakırsanız bu sitede <em>“Teklif al”</em> görünür.</p>
							</div>

							<div class="nwcs-field">
								<label class="nwcs-sublabel">Görsel (bu sitede)</label>
								<?php
								// Secenekler tiklaninca doldurulur (ortak sablondan): yuzlerce urun x
								// yuzlerce gorsel sayfayi megabaytlarca buyutmesin.
								$current = (int) ( $override['image'] ?? 0 );
								?>
								<select class="nwcs-input" name="products[overrides][<?php echo esc_attr( (string) $id ); ?>][image]" data-nwcs-lazy-media>
									<option value="0">— Havuzdaki görsel —</option>
									<?php foreach ( $media as $item ) : ?>
										<?php if ( (int) $item['id'] === $current ) : ?>
											<option value="<?php echo esc_attr( (string) $item['id'] ); ?>" selected><?php echo esc_html( $item['title'] ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
