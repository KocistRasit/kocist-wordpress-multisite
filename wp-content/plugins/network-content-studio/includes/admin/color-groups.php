<?php
/**
 * Ürün Havuzu -> Kategoriler -> kategori -> "Renk grupları".
 *
 * Ayni urunun renkleri (Excel'de her renk ayri urun) burada tek gruba
 * toplanir. Bolum Fotograf Kutusu'ndan sonra, Ürünler tablosundan once gelir
 * (nwcs_render_category_detail). Veri ve sitedeki kullanimi:
 * includes/color-groups.php.
 *
 *   - "Renk grubu oluştur": kategorinin urunlerinden en az ikisi secilir
 *     (kod ve ada gore aranir); baska grupta olan secilemez, nedeni yazar.
 *     Urun adinda hazir bir renk geciyorsa renk adi ve yuvarlagi dolar.
 *   - Grup karti: baslik, uye satirlari (sira ↑ ↓, kucuk resim, kod, ad,
 *     renk adi, renk yuvarlagi: hazir renkler ya da ozel renk), "Gruptan
 *     çıkar", "Gruba ürün ekle", "Grubu sil…" (onay sunucuda sorulur).
 *     1. satir "Öne çıkan"dir: kartta o gorunur.
 *   - Copteki, silinmis ya da kategoriden cikmis uye uyariyla gosterilir;
 *     sitede yok sayilir.
 *
 * Ürün formunda tek satir ozet ve buraya baglanti (nwcs_render_product_color_line);
 * ikinci bir duzenleme yeri yok. Ürünler tablosunda "renk grubu: X" rozeti
 * (nwcs_color_badge).
 *
 * JavaScript gerekmez: her islem bir form (admin-post.php, nonce + yetki);
 * ↑ ↓ ve "Gruptan çıkar" JS'siz formu o haliyle kaydeder. JS (assets/color-groups.js)
 * satiri yerinde tasir ("Grubu kaydet" ile yazilir), urun listesini yazdikca
 * suzer ve renk yuvarlagini canli gosterir.
 *
 * Her degisiklik "Son işlemler"e 'renk' kaydi birakir: kategorinin onceki ve
 * sonraki gruplari; geri alma oncekini yazar. Ayni kullanicinin ayni gruptaki
 * art arda duzenlemeleri (sira, renk, cikar, ekle) son duzenlemeden 30 dakika
 * icinde tek kayitta birlesir: kaydin "onceki" hali ilk duzenlemeden onceki
 * hal kalir, "sonraki" guncellenir; geri alma oturumun basina doner. Grup
 * olusturma ve silme her zaman kendi kaydini birakir. Kayittan sonra havuz
 * onbellegi ve sitelerin sayfa onbellegi temizlenir (nwcs_color_groups_write).
 *
 * Renk adi zorunlu: adi bos uye varken kayit yapilmaz, hata urun kodunu
 * soyler, yazilanlar geri gelir (NWCS_COLOR_DRAFT). Olustururken ya da
 * eklerken adi tahmin edilemeyen uye bos kalir; grup karti "Renk adı boş olan
 * ürünler" diye uyarir, sitede o renk sayilmaz.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_COLOR_KIND  = 'renk';
const NWCS_COLOR_DRAFT = 'nwcs_color_draft_';
// Ayni grupta art arda yapilan kucuk duzenlemeler (sira, renk, cikar, ekle) bu sure
// icinde tek "Son işlemler" kaydinda birlesir: liste (en fazla 10) Excel yuklemelerini kaybetmesin.
const NWCS_COLOR_MERGE = 30 * MINUTE_IN_SECONDS;

add_action( 'admin_enqueue_scripts', 'nwcs_color_admin_assets' );
function nwcs_color_admin_assets( string $hook ): void {
	// Urun formundaki ozet satiri da ayni stili kullanir (Kategoriler de Ürün Havuzu'nun alt sayfasi).
	if ( ! str_contains( $hook, NWCS_POOL_SLUG ) ) {
		return;
	}

	// Surum + dosya zamani: dosya degisince tarayici eski kopyayi tutmasin.
	$ver = static fn( string $file ): string => NWCS_VERSION . '.' . (int) filemtime( NWCS_DIR . $file );

	wp_enqueue_style( 'nwcs-color-groups', NWCS_URL . 'assets/color-groups.css', array( 'nwcs-admin' ), $ver( 'assets/color-groups.css' ) );

	if ( str_contains( $hook, NWCS_CATEGORIES_SLUG ) ) {
		wp_enqueue_script( 'nwcs-color-groups', NWCS_URL . 'assets/color-groups.js', array(), $ver( 'assets/color-groups.js' ), true );
	}
}

/**
 * Bir gruba bagli adres (Kategoriler -> kategori, grubun karti).
 */
function nwcs_color_group_url( string $slug, string $group_id = '', array $args = array() ): string {
	return nwcs_pool_categories_url( array_merge( array( 'kategori' => $slug ), $args ) ) . '#' . ( '' !== $group_id ? 'nwcs-renk-' . $group_id : 'nwcs-renk' );
}

/**
 * Urunlerin kucuk resimleri (havuzdaki ilk gorsel), ek kimligine gore. Tek
 * site gecisi ve toplu sorguyla: kategoride yuzlerce urun olabilir.
 *
 * @return array<int, string>
 */
function nwcs_color_thumbs( array $products ): array {
	static $cache = array();

	$ids = array_values( array_diff( array_unique( array_filter( array_map( static fn( array $product ): int => (int) ( $product['image_id'] ?? 0 ), $products ) ) ), array_keys( $cache ) ) );

	if ( $ids ) {
		switch_to_blog( nwcs_pool_blog_id() );
		_prime_post_caches( $ids, false, true );

		foreach ( $ids as $id ) {
			$src          = wp_get_attachment_image_src( $id, 'thumbnail' );
			$cache[ $id ] = $src ? (string) $src[0] : '';
		}

		restore_current_blog();
	}

	return $cache;
}

/**
 * Urunun kucuk resmi; yoksa bos.
 */
function nwcs_color_thumb( array $product ): string {
	$id = (int) ( $product['image_id'] ?? 0 );

	return $id ? (string) ( nwcs_color_thumbs( array( $product ) )[ $id ] ?? '' ) : '';
}

/**
 * Ürünler tablosundaki rozet: "renk grubu: Renk" (grup baska kategorideyse
 * onun adiyla). Grupta degilse bos.
 */
function nwcs_color_badge( int $product_id, string $slug ): string {
	$group = nwcs_color_group_of( $product_id );

	if ( ! $group ) {
		return '';
	}

	$text = 'renk grubu: ' . $group['label'];

	if ( $group['slug'] !== $slug ) {
		$text .= ' (' . ( nwcs_pool_categories()[ $group['slug'] ]['name'] ?? $group['slug'] ) . ')';
	}

	return sprintf(
		'<a class="nwcs-badge nwcs-badge--color" href="%s">%s</a>',
		esc_url( nwcs_color_group_url( $group['slug'], $group['id'] ) ),
		esc_html( $text )
	);
}

/**
 * Urunun baska bir grupta olma nedeni (secim listesinde yazilir); degilse bos.
 */
function nwcs_color_taken_reason( int $product_id, string $except_key = '' ): string {
	$group = nwcs_color_group_of( $product_id );

	if ( ! $group || $group['key'] === $except_key ) {
		return '';
	}

	$category = (string) ( nwcs_pool_categories()[ $group['slug'] ]['name'] ?? $group['slug'] );
	$lead     = (int) ( $group['members'][0]['product_id'] ?? 0 );
	$lead     = (string) ( nwcs_pool_products()[ $lead ]['title'] ?? '' );

	return '' !== $lead
		? sprintf( 'Başka bir renk grubunda: %s › “%s” (%s)', $category, $group['label'], $lead )
		: sprintf( 'Başka bir renk grubunda: %s › “%s”', $category, $group['label'] );
}

/**
 * Uye sitede renk olarak sayilir mi: renk adi yazilmis, havuzda yayinda ve
 * bu kategoride (sitede gorunup gorunmedigi ayrica: nwcs_color_site_colors).
 */
function nwcs_color_member_counts( array $member, string $slug ): bool {
	return '' !== trim( (string) $member['color_name'] ) && '' === nwcs_color_member_state( (int) $member['product_id'], $slug );
}

/**
 * Urunun panel mesajlarindaki adi: kodu; kodu yoksa adi tirnak icinde.
 */
function nwcs_color_product_label( int $id ): string {
	$product = nwcs_pool_products()[ $id ] ?? null;
	$code    = $product ? (string) $product['code'] : '';
	$title   = $product ? (string) $product['title'] : '';

	if ( ! $product ) {
		switch_to_blog( nwcs_pool_blog_id() );
		$code  = (string) get_post_meta( $id, '_nwcs_code', true );
		$title = (string) get_the_title( $id );
		restore_current_blog();
	}

	return '' !== $code ? $code : ( '' !== $title ? '“' . $title . '”' : sprintf( 'Ürün #%d', $id ) );
}

/**
 * Turkce liste: "A", "A ve B", "A, B ve C".
 *
 * @param string[] $items
 */
function nwcs_color_join( array $items ): string {
	$items = array_values( $items );
	$last  = array_pop( $items );

	return $items ? implode( ', ', $items ) . ' ve ' . $last : (string) $last;
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

/**
 * Kategorinin "Renk grupları" bolumu.
 *
 * @param array<int, array> $products Kategorinin havuz urunleri (nwcs_sync_category_products).
 */
function nwcs_render_color_groups( string $slug, array $products ): void {
	$groups = nwcs_color_groups( $slug );

	nwcs_color_thumbs( $products );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum secimi ve bildirim.
	$result = isset( $_GET['renk'] ) ? sanitize_key( wp_unslash( $_GET['renk'] ) ) : '';
	$asking = isset( $_GET['renk_sil'] ) ? sanitize_key( wp_unslash( $_GET['renk_sil'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Kaydedilmeyen form (renk adi bos): yazilanlar bir kez geri gelir.
	$draft = get_site_transient( NWCS_COLOR_DRAFT . get_current_user_id() );
	$draft = is_array( $draft ) && ( $draft['slug'] ?? '' ) === $slug ? $draft : array();

	if ( $draft ) {
		delete_site_transient( NWCS_COLOR_DRAFT . get_current_user_id() );
		$result = 'adsiz' === $result ? '' : $result;
	}
	?>
	<section class="nwcs-cat__section nwcs-colors" aria-labelledby="nwcs-sec-colors" id="nwcs-renk"
		data-nwcs-colors="<?php echo esc_attr( (string) wp_json_encode( nwcs_color_presets() ) ); ?>">
		<h3 class="nwcs-section__title" id="nwcs-sec-colors">Renk grupları</h3>
		<p class="nwcs-hint nwcs-colors__lead">
			Aynı ürünün renkleri Excel’de ayrı ürün olarak girilir; burada tek gruba toplanır.
			Sitede kategoride tek kart ve “3 renk” gibi bir etiket görünür; ürün sayfasında her renk kendi sayfasına giden bir yuvarlaktır.
			Grubun ilk ürünü <strong>öne çıkan</strong> üründür: kartta o görünür.
		</p>

		<?php nwcs_render_color_notice( $result ); ?>

		<?php foreach ( $groups as $group ) : ?>
			<?php
			if ( $asking === $group['id'] ) {
				nwcs_render_color_delete_confirm( $slug, $group );
			} else {
				nwcs_render_color_group( $slug, $group, $products, ( $draft['group'] ?? '' ) === $group['id'] ? $draft : array() );
			}
			?>
		<?php endforeach; ?>

		<?php nwcs_render_color_create( $slug, $products, (bool) $groups ); ?>
	</section>
	<?php
}

/**
 * Islem sonucu (bolumun icinde; WordPress bildirimi ust seride tasimasin diye 'inline').
 */
function nwcs_render_color_notice( string $result ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$product = isset( $_GET['urun'] ) ? absint( $_GET['urun'] ) : 0;
	$title   = (string) ( nwcs_pool_products()[ $product ]['title'] ?? '' );

	$map = array(
		'olustu'     => array( 'success', 'Renk grubu oluşturuldu. Sitede bu ürünler artık tek kart; ürün sayfasında renk seçenekleri görünür. Renk adlarını ve yuvarlakları kontrol edin.' ),
		'kaydedildi' => array( 'success', 'Renk grubu kaydedildi. Sitelerde hemen görünür; yanlışsa “Son işlemler”den geri alabilirsiniz.' ),
		'eklendi'    => array( 'success', 'Ürünler gruba eklendi. Renk adlarını ve yuvarlakları kontrol edin.' ),
		'cikarildi'  => array( 'success', 'Ürün gruptan çıkarıldı; sitede yeniden ayrı kart olarak görünür.' ),
		'silindi'    => array( 'success', 'Renk grubu silindi. Ürünler silinmedi; sitede yeniden ayrı kart olarak görünür.' ),
		'bosaldi'    => array( 'success', 'Grupta ürün kalmadığı için grup silindi. Ürünler silinmedi.' ),
		'ayni'       => array( 'info', 'Renk grubu değişmedi.' ),
		'az'         => array( 'error', 'Renk grubu oluşturulmadı: en az iki ürün işaretleyin. Grup, aynı ürünün en az iki rengini birleştirir.' ),
		'secilmedi'  => array( 'error', 'Ürün eklenmedi: listeden en az bir ürün işaretleyip yeniden deneyin.' ),
		'dolu'       => array( 'error', '' !== $title ? sprintf( 'Kaydedilmedi: “%s” başka bir renk grubunda. Bir ürün yalnızca bir grupta olabilir; önce o gruptan çıkarın.', $title ) : 'Kaydedilmedi: seçtiğiniz ürünlerden biri başka bir renk grubunda. Önce o gruptan çıkarın.' ),
		'disarida'   => array( 'error', 'Kaydedilmedi: seçtiğiniz ürünlerden biri bu kategoride değil. Sayfayı yenileyip yeniden deneyin.' ),
		'yok'        => array( 'error', 'Renk grubu bulunamadı; başka bir sekmede silinmiş olabilir. Sayfayı yenileyip yeniden deneyin.' ),
		'adsiz'      => array( 'error', 'Kaydedilmedi: renk adı boş olan ürün var. Her ürünün renk adını yazıp yeniden kaydedin.' ),
	);

	if ( isset( $map[ $result ] ) ) {
		printf(
			'<div class="notice notice-%1$s inline nwcs-colors__notice" role="%3$s"><p>%2$s</p></div>',
			esc_attr( $map[ $result ][0] ),
			esc_html( $map[ $result ][1] ),
			'error' === $map[ $result ][0] ? 'alert' : 'status'
		);
	}
}

/**
 * Grubun sitelerdeki durumu: "Koçist’te 3 renk · WOOD KOCIST’te 2 renk".
 * Ikiden az renk gorunen sitede renk secenegi cikmaz; bu da yazilir.
 */
function nwcs_color_sites_line( string $slug, array $group ): string {
	$parts = array();
	$group = $group + array( 'slug' => $slug );

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$count = count( nwcs_color_site_colors( $group, (int) $blog_id ) );

		if ( $count >= 2 ) {
			$parts[] = sprintf( '%s %d renk', nwcs_locative( $site['label'] ), $count );
		} elseif ( 1 === $count ) {
			$parts[] = sprintf( '%s tek renk görünüyor, renk seçeneği çıkmaz', nwcs_locative( $site['label'] ) );
		}
	}

	return $parts ? implode( ' · ', $parts ) : 'Hiçbir sitede görünmüyor: üyeler bir sitede gösterilince renk seçenekleri çıkar.';
}

/**
 * Tek grubun karti (tek form).
 *
 * $draft: kaydedilmeyen form (renk adi bos kaldi). Alanlar kullanicinin
 * yazdiklariyla dolar, adi bos satirlar isaretlenir; baslik, sayilar ve
 * sitelerdeki durum kayitli halden.
 */
function nwcs_render_color_group( string $slug, array $group, array $products, array $draft = array() ): void {
	$gid      = $group['id'];
	$key      = $slug . '/' . $gid;
	$presets  = nwcs_color_presets();
	$saved    = $group;
	$inside   = array_map( static fn( array $member ): int => (int) $member['product_id'], $group['members'] );
	$form_id  = 'nwcs-renk-form-' . $gid;
	$label_id = 'nwcs-renk-label-' . $gid;
	$missing  = array_map( 'intval', (array) ( $draft['missing'] ?? array() ) );
	$valid    = count( array_filter( $saved['members'], static fn( array $member ): bool => nwcs_color_member_counts( $member, $slug ) ) );
	$unnamed  = array_values( array_filter( $saved['members'], static fn( array $member ): bool => '' === trim( (string) $member['color_name'] ) ) );

	if ( $draft ) {
		// Taslaktaki sira ve degerler; taslakta olmayan (baska sekmede eklenmis) uyeler sonda.
		$rows = array();

		foreach ( (array) ( $draft['members'] ?? array() ) as $member ) {
			$id = (int) ( $member['product_id'] ?? 0 );

			if ( in_array( $id, $inside, true ) ) {
				$rows[ $id ] = array(
					'product_id' => $id,
					'color_name' => mb_substr( nwcs_clean_text( $member['color_name'] ?? '' ), 0, 40 ),
					'color_hex'  => nwcs_color_hex( (string) ( $member['color_hex'] ?? '' ) ),
				);
			}
		}

		foreach ( $saved['members'] as $member ) {
			$rows[ $member['product_id'] ] ??= $member;
		}

		$group['members'] = array_values( $rows );
		$group['label']   = '' !== (string) ( $draft['label'] ?? '' ) ? (string) $draft['label'] : $group['label'];
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-colors__group" id="nwcs-renk-<?php echo esc_attr( $gid ); ?>" data-nwcs-color-group aria-labelledby="<?php echo esc_attr( $form_id ); ?>-title">
		<input type="hidden" name="action" value="nwcs_color_save" />
		<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
		<input type="hidden" name="grup" value="<?php echo esc_attr( $gid ); ?>" />
		<?php wp_nonce_field( 'nwcs_color_save_' . $slug ); ?>
		<?php // Enter tusu bu dugmeyi kullanir (formdaki ilk gonder dugmesi ↑ olmasin). ?>
		<button type="submit" name="kaydet" value="1" class="nwcs-colors__default" tabindex="-1" aria-hidden="true">Grubu kaydet</button>

		<div class="nwcs-colors__head">
			<h4 class="nwcs-colors__title" id="<?php echo esc_attr( $form_id ); ?>-title">
				<?php echo esc_html( sprintf( '%s · %d renk', $saved['label'], $valid ) ); ?>
			</h4>
			<p class="nwcs-colors__sites"><?php echo esc_html( nwcs_color_sites_line( $slug, $saved ) ); ?></p>
		</div>

		<div class="nwcs-colors__label">
			<label class="nwcs-sublabel" for="<?php echo esc_attr( $label_id ); ?>">Grubun başlığı</label>
			<input class="nwcs-input" type="text" id="<?php echo esc_attr( $label_id ); ?>" name="baslik" value="<?php echo esc_attr( $group['label'] ); ?>" maxlength="40" required aria-describedby="<?php echo esc_attr( $label_id ); ?>-hint" />
			<p class="nwcs-hint" id="<?php echo esc_attr( $label_id ); ?>-hint">Ürün sayfasında “<?php echo esc_html( $group['label'] ); ?>: Antrasit” diye görünür. “Çatı rengi”, “Gövde rengi” gibi değiştirebilirsiniz.</p>
		</div>

		<?php if ( $missing ) : ?>
			<div class="notice notice-error inline nwcs-colors__notice" role="alert">
				<p><?php echo esc_html( sprintf( 'Kaydedilmedi: %s için renk adı yazın. Yazdıklarınız aşağıda duruyor; hiçbir şey değişmedi.', nwcs_color_join( array_map( 'nwcs_color_product_label', $missing ) ) ) ); ?></p>
			</div>
		<?php elseif ( $unnamed ) : ?>
			<p class="nwcs-photos__warn" data-nwcs-color-unnamed>
				<?php echo esc_html( sprintf( 'Renk adı boş olan ürünler: %s. Renk adını yazıp “Grubu kaydet”e basın; adı olmayan renk sitede gösterilmez.', nwcs_color_join( array_map( static fn( array $member ): string => nwcs_color_product_label( (int) $member['product_id'] ), $unnamed ) ) ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $valid < 2 ) : ?>
			<p class="nwcs-photos__warn">Sitede renk seçeneği çıkmaz: grupta renk adı yazılmış ve yayında olan en az iki ürün olmalı. Renk adlarını yazın, gruba ürün ekleyin ya da grubu silin.</p>
		<?php endif; ?>

		<div class="nwcs-cat__scroll">
			<table class="nwcs-table nwcs-colors__table">
				<thead>
					<tr>
						<th scope="col">Sıra</th>
						<th scope="col">Ürün</th>
						<th scope="col">Renk adı</th>
						<th scope="col">Renk</th>
						<th scope="col"><span class="screen-reader-text">Gruptan çıkar</span></th>
					</tr>
				</thead>
				<tbody data-nwcs-color-rows>
					<?php foreach ( $group['members'] as $position => $member ) : ?>
						<?php
						$id      = (int) $member['product_id'];
						$state   = nwcs_color_member_state( $id, $slug );
						$product = nwcs_pool_products()[ $id ] ?? null;
						$name    = $product ? (string) $product['title'] : '';
						$code    = $product ? (string) $product['code'] : '';

						// Cop kutusundaki (havuz listesinde olmayan) urunun adi ve kodu.
						if ( ! $product ) {
							switch_to_blog( nwcs_pool_blog_id() );
							$post = get_post( $id );
							$name = $post ? (string) $post->post_title : '';
							$code = $post ? (string) get_post_meta( $id, '_nwcs_code', true ) : '';
							restore_current_blog();
						}

						$name   = '' !== $name ? $name : sprintf( 'Ürün #%d', $id );
						$thumb  = $product ? nwcs_color_thumb( $product ) : '';
						$hex    = (string) $member['color_hex'];
						$preset = (string) ( array_search( $hex, $presets, true ) ?: ( '' !== $hex ? 'ozel' : '' ) );
						$field  = 'nwcs-renk-' . $gid . '-' . $id;
						$warn   = array(
							'trash' => 'Çöp kutusunda: sitede gösterilmiyor. Geri getirin ya da gruptan çıkarın.',
							'gone'  => 'Ürün silinmiş: sitede gösterilmiyor. Gruptan çıkarın.',
							'moved' => 'Artık bu kategoride değil: sitede bu grupta gösterilmiyor. Ürünü kategoriye geri ekleyin ya da gruptan çıkarın.',
						);
						?>
						<tr class="nwcs-colors__row<?php echo '' !== $state ? ' is-invalid' : ''; ?>" data-nwcs-color-row data-nwcs-color-code="<?php echo esc_attr( '' !== $code ? $code : '“' . $name . '”' ); ?>">
							<td class="nwcs-colors__order">
								<input type="hidden" name="sira[]" value="<?php echo (int) $id; ?>" />
								<button type="submit" name="tasi" value="<?php echo (int) $id; ?>:up" class="nwcs-move" data-nwcs-color-move="up" aria-label="<?php echo esc_attr( 'Yukarı taşı: ' . $name ); ?>"<?php disabled( 0 === $position ); ?>>↑</button>
								<button type="submit" name="tasi" value="<?php echo (int) $id; ?>:down" class="nwcs-move" data-nwcs-color-move="down" aria-label="<?php echo esc_attr( 'Aşağı taşı: ' . $name ); ?>"<?php disabled( count( $group['members'] ) - 1 === $position ); ?>>↓</button>
							</td>
							<td class="nwcs-colors__product">
								<div class="nwcs-colors__prod">
									<span class="nwcs-colors__thumb" aria-hidden="true">
										<?php if ( '' !== $thumb ) : ?>
											<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="40" height="40" loading="lazy" />
										<?php endif; ?>
									</span>
									<span class="nwcs-colors__name">
										<?php if ( '' !== $code ) : ?>
											<span class="nwcs-cat__code"><?php echo esc_html( $code ); ?></span>
										<?php endif; ?>
										<?php if ( $product ) : ?>
											<a href="<?php echo esc_url( nwcs_pool_url( array( 'urun' => $id ) ) ); ?>"><?php echo esc_html( $name ); ?></a>
										<?php else : ?>
											<span><?php echo esc_html( $name ); ?></span>
										<?php endif; ?>
										<span class="nwcs-badge nwcs-badge--new nwcs-colors__lead-tag" data-nwcs-color-lead<?php echo 0 === $position ? '' : ' hidden'; ?>>Öne çıkan</span>
										<?php if ( '' !== $state ) : ?>
											<span class="nwcs-colors__warn"><?php echo esc_html( $warn[ $state ] ); ?></span>
										<?php endif; ?>
									</span>
								</div>
							</td>
							<td class="nwcs-colors__cname">
								<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>-ad"><?php echo esc_html( 'Renk adı: ' . $name ); ?></label>
								<?php $no_name = in_array( $id, $missing, true ); ?>
								<input class="nwcs-input" type="text" id="<?php echo esc_attr( $field ); ?>-ad" name="uye[<?php echo (int) $id; ?>][ad]" value="<?php echo esc_attr( $member['color_name'] ); ?>" maxlength="40" list="nwcs-renk-adlari" placeholder="örn. Antrasit" data-nwcs-color-name
									<?php echo $no_name ? 'aria-invalid="true" aria-describedby="' . esc_attr( $field ) . '-hata"' : ''; ?> />
								<?php if ( $no_name ) : ?>
									<span class="nwcs-colors__err" id="<?php echo esc_attr( $field ); ?>-hata">Renk adı yazın.</span>
								<?php endif; ?>
							</td>
							<td class="nwcs-colors__pick">
								<span class="nwcs-colors__dot<?php echo nwcs_color_is_light( $hex ) ? ' is-light' : ''; ?><?php echo '' === $hex ? ' is-empty' : ''; ?>" style="<?php echo '' !== $hex ? esc_attr( '--nwcs-dot:' . $hex ) : ''; ?>" data-nwcs-color-dot aria-hidden="true"></span>
								<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>-hazir"><?php echo esc_html( 'Renk yuvarlağı: ' . $name ); ?></label>
								<select class="nwcs-input" id="<?php echo esc_attr( $field ); ?>-hazir" name="uye[<?php echo (int) $id; ?>][hazir]" data-nwcs-color-preset>
									<option value="" <?php selected( $preset, '' ); ?>>Renk seçin</option>
									<?php foreach ( $presets as $preset_name => $preset_hex ) : ?>
										<option value="<?php echo esc_attr( $preset_name ); ?>" data-hex="<?php echo esc_attr( $preset_hex ); ?>" <?php selected( $preset, $preset_name ); ?>><?php echo esc_html( $preset_name ); ?></option>
									<?php endforeach; ?>
									<option value="ozel" <?php selected( $preset, 'ozel' ); ?>>Özel renk (yandaki seçici)</option>
								</select>
								<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>-hex"><?php echo esc_html( 'Özel renk: ' . $name ); ?></label>
								<input class="nwcs-colors__custom" type="color" id="<?php echo esc_attr( $field ); ?>-hex" name="uye[<?php echo (int) $id; ?>][hex]" value="<?php echo esc_attr( '' !== $hex ? $hex : '#ffffff' ); ?>" data-nwcs-color-custom />
							</td>
							<td class="nwcs-colors__out">
								<button type="submit" name="cikar" value="<?php echo (int) $id; ?>" class="button-link nwcs-row__delete">Gruptan çıkar<span class="screen-reader-text">: <?php echo esc_html( $name ); ?></span></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p class="nwcs-colors__status" data-nwcs-color-status aria-live="polite"></p>
		<p class="nwcs-colors__err" data-nwcs-color-missing role="alert" hidden></p>

		<?php $addable = nwcs_color_choices( $slug, $products, $key, $inside ); ?>
		<?php if ( $addable ) : ?>
			<details class="nwcs-colors__add">
				<summary>Gruba ürün ekle</summary>
				<?php nwcs_render_color_picker( $addable, 'ekle[]', 'nwcs-renk-ekle-' . $gid ); ?>
				<button type="submit" name="ekle_gonder" value="1" class="button">Seçilenleri gruba ekle</button>
			</details>
		<?php endif; ?>

		<div class="nwcs-actions nwcs-colors__actions">
			<button type="submit" name="kaydet" value="1" class="button button-primary">Grubu kaydet</button>
			<a class="nwcs-row__delete" href="<?php echo esc_url( nwcs_color_group_url( $slug, $gid, array( 'renk_sil' => $gid ) ) ); ?>">Grubu sil…</a>
		</div>
	</form>
	<?php

	// Renk adi onerileri (hazir renkler); sayfada bir kez.
	static $datalist = false;

	if ( ! $datalist ) {
		$datalist = true;
		echo '<datalist id="nwcs-renk-adlari">';

		foreach ( array_keys( $presets ) as $preset_name ) {
			echo '<option value="' . esc_attr( $preset_name ) . '"></option>';
		}

		echo '</datalist>';
	}
}

/**
 * Secim listesine girecek urunler: kategorinin urunleri; baska grupta olanlar
 * nedeniyle (secilemez). $inside: bu grubun uyeleri (listede yer almaz).
 *
 * @return array<int, array{id:int, code:string, title:string, thumb:string, taken:string}>
 */
function nwcs_color_choices( string $slug, array $products, string $own_key = '', array $inside = array() ): array {
	$out = array();

	foreach ( $products as $id => $product ) {
		$id = (int) $id;

		if ( in_array( $id, $inside, true ) ) {
			continue;
		}

		$out[] = array(
			'id'    => $id,
			'code'  => (string) $product['code'],
			'title' => (string) $product['title'],
			'thumb' => nwcs_color_thumb( $product ),
			'taken' => nwcs_color_taken_reason( $id, $own_key ),
		);
	}

	return $out;
}

/**
 * Aranabilir urun secim listesi (isaret kutulari). Arama kutusu JavaScript'le
 * gorunur; JS yoksa butun liste acik durur.
 */
function nwcs_render_color_picker( array $choices, string $name, string $id ): void {
	$free = count( array_filter( $choices, static fn( array $choice ): bool => '' === $choice['taken'] ) );
	?>
	<div class="nwcs-colors__picker" data-nwcs-color-picker>
		<div class="nwcs-colors__search" data-nwcs-color-search hidden>
			<label class="nwcs-sublabel" for="<?php echo esc_attr( $id ); ?>-ara">Ürün ara</label>
			<input class="nwcs-input" type="search" id="<?php echo esc_attr( $id ); ?>-ara" placeholder="Kod ya da ad" autocomplete="off" data-nwcs-color-filter aria-describedby="<?php echo esc_attr( $id ); ?>-sayi" />
		</div>
		<p class="nwcs-hint" id="<?php echo esc_attr( $id ); ?>-sayi" data-nwcs-color-count aria-live="polite">
			<?php echo esc_html( sprintf( '%d ürün seçilebilir.', $free ) ); ?>
		</p>
		<ul class="nwcs-colors__choices">
			<?php foreach ( $choices as $choice ) : ?>
				<?php $box = $id . '-' . $choice['id']; ?>
				<li class="nwcs-colors__choice<?php echo '' !== $choice['taken'] ? ' is-taken' : ''; ?>" data-nwcs-color-item="<?php echo esc_attr( nwcs_search_fold( $choice['code'] . ' ' . $choice['title'] ) ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $box ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo (int) $choice['id']; ?>"
						<?php disabled( '' !== $choice['taken'] ); ?><?php echo '' !== $choice['taken'] ? ' aria-describedby="' . esc_attr( $box ) . '-neden"' : ''; ?> />
					<label for="<?php echo esc_attr( $box ); ?>">
						<span class="nwcs-colors__thumb" aria-hidden="true">
							<?php if ( '' !== $choice['thumb'] ) : ?>
								<img src="<?php echo esc_url( $choice['thumb'] ); ?>" alt="" width="40" height="40" loading="lazy" />
							<?php endif; ?>
						</span>
						<span class="nwcs-colors__name">
							<?php if ( '' !== $choice['code'] ) : ?>
								<span class="nwcs-cat__code"><?php echo esc_html( $choice['code'] ); ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( $choice['title'] ); ?></span>
						</span>
					</label>
					<?php if ( '' !== $choice['taken'] ) : ?>
						<span class="nwcs-colors__why" id="<?php echo esc_attr( $box ); ?>-neden"><?php echo esc_html( $choice['taken'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="nwcs-hint" data-nwcs-color-none hidden>Bu aramaya uyan ürün yok.</p>
	</div>
	<?php
}

/**
 * "Renk grubu oluştur" formu.
 */
function nwcs_render_color_create( string $slug, array $products, bool $has_groups ): void {
	if ( count( $products ) < 2 ) {
		echo '<p class="nwcs-hint">Renk grubu için kategoride en az iki ürün olmalı.</p>';

		return;
	}

	$choices = nwcs_color_choices( $slug, $products );
	$free    = count( array_filter( $choices, static fn( array $choice ): bool => '' === $choice['taken'] ) );
	?>
	<details class="nwcs-colors__create"<?php echo $has_groups ? '' : ' open'; ?>>
		<summary class="nwcs-colors__toggle"><?php echo $has_groups ? 'Yeni renk grubu oluştur' : 'Renk grubu oluştur'; ?></summary>

		<?php if ( $free < 2 ) : ?>
			<p class="nwcs-hint">Bu kategoride gruba alınabilecek ürün kalmadı: ürünler başka bir renk grubunda.</p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-colors__new" data-nwcs-color-create>
				<input type="hidden" name="action" value="nwcs_color_create" />
				<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
				<?php wp_nonce_field( 'nwcs_color_create_' . $slug ); ?>

				<p class="nwcs-hint">
					Aynı ürünün renklerini işaretleyin (en az iki). İlk işaretlenen değil, listedeki ilk ürün öne çıkan olur; sırayı sonra ↑ ↓ ile değiştirirsiniz.
					Ürün adında hazır bir renk geçiyorsa (Antrasit, Beyaz, Teak…) renk adı ve yuvarlağı kendiliğinden dolar.
				</p>

				<div class="nwcs-colors__label">
					<label class="nwcs-sublabel" for="nwcs-renk-yeni-baslik">Grubun başlığı</label>
					<input class="nwcs-input" type="text" id="nwcs-renk-yeni-baslik" name="baslik" value="<?php echo esc_attr( NWCS_COLOR_LABEL ); ?>" maxlength="40" required />
				</div>

				<?php nwcs_render_color_picker( $choices, 'urunler[]', 'nwcs-renk-yeni' ); ?>

				<p class="nwcs-field__error" data-nwcs-color-error role="alert" hidden>En az iki ürün işaretleyin.</p>

				<div class="nwcs-actions">
					<button type="submit" class="button button-primary">Grubu oluştur</button>
				</div>
			</form>
		<?php endif; ?>
	</details>
	<?php
}

/**
 * Grubu silme onayi (sunucuda: JS gerekmez).
 */
function nwcs_render_color_delete_confirm( string $slug, array $group ): void {
	$count = count( $group['members'] );
	?>
	<div class="nwcs-confirm nwcs-colors__confirm" role="alertdialog" aria-labelledby="nwcs-renk-sil-<?php echo esc_attr( $group['id'] ); ?>" id="nwcs-renk-<?php echo esc_attr( $group['id'] ); ?>">
		<h4 id="nwcs-renk-sil-<?php echo esc_attr( $group['id'] ); ?>"><?php echo esc_html( sprintf( '“%s” grubu silinsin mi?', $group['label'] ) ); ?></h4>
		<ul>
			<li><?php echo esc_html( sprintf( '%d ürün gruptan çıkar; ürünler silinmez, Ürün Havuzu’nda kalır.', $count ) ); ?></li>
			<li>Sitelerde her renk yeniden ayrı kart olarak görünür; ürün sayfalarındaki renk düğmeleri kalkar.</li>
		</ul>
		<p class="nwcs-hint">Yanlışsa “Son işlemler”den geri alınabilir.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-actions">
			<input type="hidden" name="action" value="nwcs_color_delete" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<input type="hidden" name="grup" value="<?php echo esc_attr( $group['id'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_color_delete_' . $slug ); ?>
			<button type="submit" class="button button-primary nwcs-danger-btn">Evet, grubu sil</button>
			<a class="button" href="<?php echo esc_url( nwcs_color_group_url( $slug, $group['id'] ) ); ?>">Vazgeç</a>
		</form>
	</div>
	<?php
}

/**
 * Urun formundaki tek satir: "Renk grubu: Renk · 3 renk · Düzenle ↗".
 */
function nwcs_render_product_color_line( array $product ): void {
	$group = nwcs_color_group_of( (int) ( $product['id'] ?? 0 ) );

	if ( ! $group ) {
		return;
	}

	$valid = count( array_filter( $group['members'], static fn( array $member ): bool => nwcs_color_member_counts( $member, $group['slug'] ) ) );
	$lead  = 0 === $group['position'];
	?>
	<p class="nwcs-colorline">
		<span class="nwcs-colorline__dot<?php echo nwcs_color_is_light( (string) $group['members'][ $group['position'] ]['color_hex'] ) ? ' is-light' : ''; ?>" style="<?php echo esc_attr( '--nwcs-dot:' . ( $group['members'][ $group['position'] ]['color_hex'] ?: '#ffffff' ) ); ?>" aria-hidden="true"></span>
		Renk grubu: <strong><?php echo esc_html( $group['label'] ); ?></strong>
		· <?php echo esc_html( sprintf( '%d renk', $valid ) ); ?>
		<?php if ( $lead ) : ?>
			· <span class="nwcs-colorline__lead">öne çıkan ürün</span>
		<?php endif; ?>
		· <a href="<?php echo esc_url( nwcs_color_group_url( $group['slug'], $group['id'] ) ); ?>">Düzenle<span class="screen-reader-text"> (Kategoriler, <?php echo esc_html( (string) ( nwcs_pool_categories()[ $group['slug'] ]['name'] ?? '' ) ); ?>)</span> ↗</a>
	</p>
	<?php
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

/**
 * Yetki ve nonce; kategori yoksa listeye doner.
 */
function nwcs_color_request( string $nonce_prefix ): string {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce asagida, kategoriye bagli.
	check_admin_referer( $nonce_prefix . $slug );

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		wp_safe_redirect( nwcs_pool_categories_url() );
		exit;
	}

	return $slug;
}

/**
 * Sonuca yonlendirir.
 */
function nwcs_color_redirect( string $slug, string $result, string $group_id = '', array $args = array() ): void {
	wp_safe_redirect( nwcs_color_group_url( $slug, $group_id, array_merge( array( 'renk' => $result ), $args ) ) );
	exit;
}

/**
 * Yeni uye satiri: urun adinda hazir renk geciyorsa ad ve yuvarlak dolar.
 */
function nwcs_color_new_member( int $product_id ): array {
	$guess = nwcs_color_guess( (string) ( nwcs_pool_products()[ $product_id ]['title'] ?? '' ) );

	return array(
		'product_id' => $product_id,
		'color_name' => $guess['name'],
		'color_hex'  => $guess['hex'],
	);
}

/**
 * Kategorinin gruplarinda kimligi verilen grup; yoksa null.
 */
function nwcs_color_find( array $groups, string $group_id ): ?array {
	foreach ( nwcs_color_clean( $groups ) as $group ) {
		if ( $group['id'] === $group_id ) {
			return $group;
		}
	}

	return null;
}

/**
 * Bir grubun iki hali arasindaki degisiklikler, "Son işlemler" satiri icin:
 * baslik, cikan ve eklenen urunler, sira (one cikan), renk adi ve yuvarlagi.
 *
 * @return string[]
 */
function nwcs_color_diff_facts( ?array $old, ?array $new ): array {
	if ( ! $old || ! $new ) {
		return $old ? array( sprintf( '“%s” grubunda ürün kalmadı, grup silindi', $old['label'] ) ) : array();
	}

	$facts   = array();
	$old_ids = array_column( $old['members'], 'product_id' );
	$new_ids = array_column( $new['members'], 'product_id' );
	$removed = array_values( array_diff( $old_ids, $new_ids ) );
	$added   = array_values( array_diff( $new_ids, $old_ids ) );

	if ( $new['label'] !== $old['label'] ) {
		$facts[] = sprintf( 'başlık “%s” oldu', $new['label'] );
	}

	if ( $removed ) {
		$facts[] = count( $removed ) > 2
			? sprintf( '%d ürün gruptan çıkarıldı', count( $removed ) )
			: sprintf( '%s gruptan çıkarıldı', nwcs_color_join( array_map( static fn( int $id ): string => '“' . ( (string) ( nwcs_pool_products()[ $id ]['title'] ?? '#' . $id ) ) . '”', $removed ) ) );
	}

	if ( $added ) {
		$facts[] = sprintf( '%d ürün eklendi', count( $added ) );
	}

	$kept_old = array_values( array_intersect( $old_ids, $new_ids ) );
	$kept_new = array_values( array_intersect( $new_ids, $old_ids ) );

	$lead  = (string) ( nwcs_pool_products()[ $new_ids[0] ]['title'] ?? '' );
	$moved = $new_ids[0] !== $old_ids[0] && '' !== $lead;

	if ( $kept_old !== $kept_new ) {
		$facts[] = $moved ? sprintf( 'sıra değişti, öne çıkan: %s', $lead ) : 'sıra değişti';
	} elseif ( $moved ) {
		$facts[] = sprintf( 'öne çıkan: %s', $lead );
	}

	$was     = array_column( $old['members'], null, 'product_id' );
	$changed = 0;

	foreach ( $new['members'] as $member ) {
		$before = $was[ $member['product_id'] ] ?? null;

		if ( $before && ( $before['color_name'] !== $member['color_name'] || $before['color_hex'] !== $member['color_hex'] ) ) {
			++$changed;
		}
	}

	if ( $changed ) {
		$facts[] = sprintf( '%d ürünün rengi değişti', $changed );
	}

	return $facts;
}

/**
 * Kategorinin gruplarini yazar ve "Son işlemler"e kaydeder.
 *
 * $action: 'create' | 'save' | 'delete'. Kaydetme, en ustteki kayit ayni
 * kullanicinin ayni gruptaki kaydetme kaydiysa ve son duzenleme
 * NWCS_COLOR_MERGE icindeyse ona eklenir: "onceki" hal (ilk duzenlemeden
 * once) korunur, "sonraki" ve ozet guncellenir. Birlesen duzenlemeler grubu
 * oturumun basindaki haline geri getirdiyse kayit kalkar.
 *
 * @param string[] $facts
 */
function nwcs_color_commit( string $slug, array $before, array $after, string $label, array $facts, string $action = 'save', string $group_id = '' ): void {
	nwcs_color_groups_write( $slug, $after );

	$now  = nwcs_color_groups( $slug );
	$user = get_current_user_id();
	$top  = nwcs_history_top();

	if ( 'save' === $action && $top
		&& NWCS_COLOR_KIND === ( $top['kind'] ?? '' )
		&& 'save' === ( $top['action'] ?? '' )
		&& $slug === ( $top['slug'] ?? '' )
		&& $group_id === ( $top['group'] ?? '' )
		&& $user === (int) ( $top['user'] ?? 0 )
		&& time() - (int) ( $top['time'] ?? 0 ) <= NWCS_COLOR_MERGE ) {
		if ( nwcs_color_clean( $top['before'] ?? array() ) === $now ) {
			nwcs_history_drop( (string) $top['id'] );

			return;
		}

		$edits = (int) ( $top['edits'] ?? 1 ) + 1;
		$facts = nwcs_color_diff_facts( nwcs_color_find( (array) $top['before'], $group_id ), nwcs_color_find( $now, $group_id ) ) ?: array( 'renk grubu güncellendi' );

		$top['after'] = $now;
		$top['label'] = $label;
		$top['edits'] = $edits;
		$top['facts'] = array_merge( $facts, array( sprintf( '%d düzenleme', $edits ) ) );
		$top['time']  = time();

		nwcs_history_save( $top );

		return;
	}

	nwcs_history_push(
		array(
			'kind'     => NWCS_COLOR_KIND,
			'action'   => $action,
			'slug'     => $slug,
			'group'    => $group_id,
			'user'     => $user,
			'edits'    => 1,
			'category' => (string) ( nwcs_pool_categories()[ $slug ]['name'] ?? $slug ),
			'label'    => $label,
			'before'   => $before,
			'after'    => $now,
			'facts'    => $facts,
			'complete' => true,
		)
	);
}

/**
 * Isaretlenen urunlerden kategoride olan ve baska grupta olmayanlar (liste
 * sirasiyla). Kategoride olmayan ya da baska grupta olan varsa hata kodu.
 *
 * @param mixed $posted
 * @return array{ids:int[], error:string, product:int}
 */
function nwcs_color_pick( string $slug, $posted, string $own_key = '' ): array {
	$posted = is_array( $posted ) ? array_map( 'absint', array_filter( $posted, 'is_scalar' ) ) : array();
	$scope  = array_map( 'intval', array_keys( nwcs_sync_category_products( $slug ) ) );
	$ids    = array();

	foreach ( $posted as $id ) {
		if ( ! in_array( $id, $scope, true ) ) {
			return array( 'ids' => array(), 'error' => 'disarida', 'product' => $id );
		}

		$group = nwcs_color_group_of( $id );

		if ( $group && $group['key'] !== $own_key ) {
			return array( 'ids' => array(), 'error' => 'dolu', 'product' => $id );
		}
	}

	// Liste (kategori) sirasi: ilk urun one cikan olur.
	foreach ( $scope as $id ) {
		if ( in_array( $id, $posted, true ) ) {
			$ids[] = $id;
		}
	}

	return array( 'ids' => $ids, 'error' => '', 'product' => 0 );
}

add_action( 'admin_post_nwcs_color_create', 'nwcs_handle_color_create' );
function nwcs_handle_color_create(): void {
	$slug  = nwcs_color_request( 'nwcs_color_create_' );
	$label = isset( $_POST['baslik'] ) ? mb_substr( nwcs_clean_text( wp_unslash( $_POST['baslik'] ) ), 0, 40 ) : '';
	$label = '' !== $label ? $label : NWCS_COLOR_LABEL;
	$pick  = nwcs_color_pick( $slug, isset( $_POST['urunler'] ) ? wp_unslash( $_POST['urunler'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_color_pick absint uygular.

	if ( '' !== $pick['error'] ) {
		nwcs_color_redirect( $slug, $pick['error'], '', array( 'urun' => $pick['product'] ) );
	}

	if ( count( $pick['ids'] ) < 2 ) {
		nwcs_color_redirect( $slug, 'az' );
	}

	$before  = nwcs_color_groups( $slug );
	$members = array_map( 'nwcs_color_new_member', $pick['ids'] );
	$group   = array(
		'id'      => nwcs_color_new_id(),
		'label'   => $label,
		'members' => $members,
	);
	$names   = array_filter( array_column( $members, 'color_name' ) );

	nwcs_color_commit(
		$slug,
		$before,
		array_merge( $before, array( $group ) ),
		$label,
		array( sprintf( '“%s” grubu oluşturuldu: %d ürün%s', $label, count( $members ), $names ? ' (' . implode( ', ', $names ) . ')' : '' ) ),
		'create',
		$group['id']
	);

	nwcs_color_redirect( $slug, 'olustu', $group['id'] );
}

add_action( 'admin_post_nwcs_color_save', 'nwcs_handle_color_save' );
function nwcs_handle_color_save(): void {
	$slug   = nwcs_color_request( 'nwcs_color_save_' );
	$gid    = isset( $_POST['grup'] ) ? sanitize_key( wp_unslash( $_POST['grup'] ) ) : '';
	$before = nwcs_color_groups( $slug );
	$index  = null;

	foreach ( $before as $position => $group ) {
		if ( $group['id'] === $gid ) {
			$index = $position;
			break;
		}
	}

	if ( null === $index ) {
		nwcs_color_redirect( $slug, 'yok' );
	}

	$old     = $before[ $index ];
	$presets = nwcs_color_presets();
	$posted  = isset( $_POST['uye'] ) && is_array( $_POST['uye'] ) ? wp_unslash( $_POST['uye'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- alan alan asagida temizlenir.
	$order   = isset( $_POST['sira'] ) && is_array( $_POST['sira'] ) ? array_map( 'absint', wp_unslash( $_POST['sira'] ) ) : array();
	$label   = isset( $_POST['baslik'] ) ? mb_substr( nwcs_clean_text( wp_unslash( $_POST['baslik'] ) ), 0, 40 ) : '';
	$by_id   = array();

	foreach ( $old['members'] as $member ) {
		$by_id[ $member['product_id'] ] = $member;
	}

	// Formdaki sira; formda olmayan uyeler (baska sekmede eklenmis) eski siralariyla sonda.
	$ids = array_values( array_unique( array_merge( array_values( array_intersect( $order, array_keys( $by_id ) ) ), array_keys( $by_id ) ) ) );

	$members = array();

	foreach ( $ids as $id ) {
		$row    = is_array( $posted[ $id ] ?? null ) ? $posted[ $id ] : null;
		$member = $by_id[ $id ];

		if ( $row ) {
			$name   = mb_substr( nwcs_clean_text( $row['ad'] ?? '' ), 0, 40 );
			$choice = nwcs_clean_text( $row['hazir'] ?? '' );

			if ( isset( $presets[ $choice ] ) ) {
				$hex  = $presets[ $choice ];
				$name = '' !== $name ? $name : $choice;
			} elseif ( 'ozel' === $choice ) {
				$hex = nwcs_color_hex( (string) ( $row['hex'] ?? '' ) );
			} else {
				// Yuvarlak secilmedi: renk adi hazir renklerden biriyse onun rengi.
				$hex = nwcs_color_preset_hex( $name );
			}

			$member = array(
				'product_id' => $id,
				'color_name' => $name,
				'color_hex'  => $hex,
			);
		}

		$members[] = $member;
	}

	// Renk adi zorunlu (gruptan cikarilan haric): bos varsa hicbir sey yazilmaz,
	// yazilanlar taslak olarak geri gelir ve hata urun kodunu soyler.
	$out     = isset( $_POST['cikar'] ) ? absint( $_POST['cikar'] ) : 0;
	$missing = array();

	foreach ( $members as $member ) {
		if ( $member['product_id'] !== $out && '' === $member['color_name'] ) {
			$missing[] = (int) $member['product_id'];
		}
	}

	if ( $missing ) {
		set_site_transient(
			NWCS_COLOR_DRAFT . get_current_user_id(),
			array(
				'slug'    => $slug,
				'group'   => $gid,
				'label'   => $label,
				'members' => $members,
				'missing' => $missing,
			),
			15 * MINUTE_IN_SECONDS
		);

		nwcs_color_redirect( $slug, 'adsiz', $gid );
	}

	$result = 'kaydedildi';

	// ↑ ↓ (JavaScript yoksa): formdaki degisikliklerle birlikte kaydedilir.
	$move = isset( $_POST['tasi'] ) ? sanitize_text_field( wp_unslash( $_POST['tasi'] ) ) : '';

	if ( preg_match( '/^(\d+):(up|down)$/', $move, $match ) ) {
		$from = array_search( (int) $match[1], array_column( $members, 'product_id' ), true );
		$to   = false === $from ? false : $from + ( 'up' === $match[2] ? -1 : 1 );

		if ( false !== $to && $to >= 0 && $to < count( $members ) ) {
			$moved = array_splice( $members, $from, 1 );
			array_splice( $members, $to, 0, $moved );
		}
	}

	// Gruptan cikar.
	if ( $out && isset( $by_id[ $out ] ) ) {
		$members = array_values( array_filter( $members, static fn( array $member ): bool => $member['product_id'] !== $out ) );
		$result  = 'cikarildi';
	}

	// Gruba urun ekle.
	if ( ! empty( $_POST['ekle_gonder'] ) ) {
		$pick = nwcs_color_pick( $slug, isset( $_POST['ekle'] ) ? wp_unslash( $_POST['ekle'] ) : array(), $slug . '/' . $gid ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nwcs_color_pick absint uygular.

		if ( '' !== $pick['error'] ) {
			nwcs_color_redirect( $slug, $pick['error'], $gid, array( 'urun' => $pick['product'] ) );
		}

		$add = array_values( array_diff( $pick['ids'], array_column( $members, 'product_id' ) ) );

		if ( ! $add ) {
			nwcs_color_redirect( $slug, 'secilmedi', $gid );
		}

		foreach ( $add as $id ) {
			$members[] = nwcs_color_new_member( $id );
		}

		$result = 'eklendi';
	}

	$group = array(
		'id'      => $gid,
		'label'   => '' !== $label ? $label : NWCS_COLOR_LABEL,
		'members' => $members,
	);

	$after = $before;

	if ( $members ) {
		$after[ $index ] = $group;
	} else {
		array_splice( $after, $index, 1 );
		$result = 'bosaldi';
	}

	$after = nwcs_color_clean( $after );

	if ( $after === $before ) {
		nwcs_color_redirect( $slug, 'ayni', $gid );
	}

	// Ozet: degisen her sey tek satirda (birlesen kayitta oturumun basina gore yeniden yazilir).
	$facts = nwcs_color_diff_facts( $old, nwcs_color_find( $after, $gid ) ) ?: array( 'renk grubu güncellendi' );

	nwcs_color_commit( $slug, $before, $after, $members ? $group['label'] : $old['label'], $facts, 'save', $gid );

	nwcs_color_redirect( $slug, $result, $members ? $gid : '' );
}

add_action( 'admin_post_nwcs_color_delete', 'nwcs_handle_color_delete' );
function nwcs_handle_color_delete(): void {
	$slug   = nwcs_color_request( 'nwcs_color_delete_' );
	$gid    = isset( $_POST['grup'] ) ? sanitize_key( wp_unslash( $_POST['grup'] ) ) : '';
	$before = nwcs_color_groups( $slug );
	$gone   = null;
	$after  = array();

	foreach ( $before as $group ) {
		if ( $group['id'] === $gid ) {
			$gone = $group;
		} else {
			$after[] = $group;
		}
	}

	if ( ! $gone ) {
		nwcs_color_redirect( $slug, 'yok' );
	}

	nwcs_color_commit( $slug, $before, $after, $gone['label'], array( sprintf( '“%s” grubu silindi (%d ürün gruptan çıktı)', $gone['label'], count( $gone['members'] ) ) ), 'delete', $gid );

	nwcs_color_redirect( $slug, 'silindi' );
}

/* ====================================================================== *
 * Geri alma (includes/admin/history.php cagirir)
 * ====================================================================== */

/**
 * Renk grubu degisikligini geri alir: kategorinin onceki gruplari yazilir.
 * Gruplar bu islemden sonra baska yoldan degistiyse (beklenmez: her degisiklik
 * kendi kaydini birakir ve once o geri alinir) dokunulmaz, kayit listeden kalkar.
 *
 * @return array{colors:int}|WP_Error
 */
function nwcs_color_undo( array $record ) {
	$slug = (string) ( $record['slug'] ?? '' );
	$name = (string) ( $record['category'] ?? $slug );

	if ( ! isset( nwcs_pool_categories()[ $slug ] ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( '“%s” kategorisi sonradan silinmiş; bu renk grubu değişikliği geri alınamaz. Bu işlem listeden kaldırıldı.', $name ) );
	}

	if ( nwcs_color_groups( $slug ) !== nwcs_color_clean( $record['after'] ?? array() ) ) {
		return new WP_Error( 'nwcs_undo_gone', sprintf( '“%s” kategorisinin renk grupları bu işlemden sonra başka yoldan değişmiş; eski hâline döndürülmedi. Bu işlem listeden kaldırıldı.', $name ) );
	}

	nwcs_color_groups_write( $slug, (array) ( $record['before'] ?? array() ) );

	return array( 'colors' => 1 );
}
