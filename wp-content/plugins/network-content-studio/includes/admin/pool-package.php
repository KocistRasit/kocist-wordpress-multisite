<?php
/**
 * Ag Yonetimi -> Gelistirici -> Havuz Paketi.
 *
 * Bir kurulumun havuzunu (urunler, kategoriler, sitelerin urun secimi ve
 * sirasi) tek bir JSON dosyasi olarak baska bir kuruluma tasir. Ornek: yerelde
 * hazirlanan Koçist ve WOOD KOCIST urunlerini canli panele aktarmak.
 *
 * Aktarim guvenli tarafta durur:
 * - Ayni urun iki kez acilmaz. Eslesme sirasiyla: urun kodu, kaynak adresi
 *   (_nwcs_source), adres adi (slug).
 * - Hedefte zaten olan urune DOKUNULMAZ (metin, fiyat, kategori, yayin durumu).
 *   Tek istisna: hic tablosu olmayan urune paketteki urun tablolari eklenir.
 * - Gorseller tasinmaz (medya kimlikleri kurulumlar arasinda gecerli degil).
 * - Site secimi site anahtariyla (manifest 'site_key') eslenir; sitenin mevcut
 *   secimi ve sirasi korunur, pakette olup sitede olmayanlar sona eklenir.
 *   Pakette secimi olan ama hedefte ayni anahtarla bulunmayan site atlanir.
 * - Once "yalnizca dene" ile ne olacagi raporlanir; yazma ayri bir adimdir.
 * - Surum 2 (0.21.0): urunlerin detay basliklari ('details') ve baslik kaydi
 *   ('headings') da tasinir. Hedefte olmayan basliklar eklenir; olanlara
 *   dokunulmaz. Surum 1 paketleri de kabul edilir (spec metninden okunur).
 * - Surum 3 (0.22.0): kategori yerlesimleri ('placements': hangi kategori hangi
 *   sitede hangi ust basligin altinda) da tasinir. Hedefte yerlesimi olmayan
 *   kategori/site ciftine yazilir; olan yerlesime dokunulmaz. Yeni acilan
 *   urunler kategorilerinin yerlestigi sitelerde de secilir. Surum 1 ve 2 de
 *   kabul edilir.
 * - 0.26.0: urunun meta basligi ve aciklamasi ('meta_title', 'meta_desc') da
 *   tasinir. Istege bagli alan oldugu icin paket surumu degismedi; eski
 *   paketlerde bos kalir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PACKAGE_SLUG    = 'nwcs-pool-package';
const NWCS_PACKAGE_FORMAT  = 'nwcs-pool-package';
const NWCS_PACKAGE_VERSION = 3;
const NWCS_PACKAGE_REPORT  = 'nwcs_pool_package_report';

// Gelistirici menusunun sonunda (E-posta Testi 32'den sonra).
add_action( 'network_admin_menu', 'nwcs_register_package_menu', 33 );
function nwcs_register_package_menu(): void {
	add_submenu_page( NWCS_DEV_MENU_SLUG, 'Havuz Paketi', 'Havuz Paketi', NWCS_CAPABILITY, NWCS_PACKAGE_SLUG, 'nwcs_render_package' );
}

/**
 * Urunun kurulumlar arasi anahtari: kod > kaynak adresi > adres adi.
 */
function nwcs_package_key( string $code, string $source, string $slug ): string {
	if ( '' !== $code ) {
		return 'kod:' . $code;
	}

	return '' !== $source ? 'kaynak:' . $source : 'slug:' . $slug;
}

/**
 * Paket verisi (havuz sitesinde calisir).
 */
function nwcs_package_build(): array {
	$pool     = nwcs_pool_products( true ) ?: nwcs_pool_products();
	$products = array();
	$keys     = array();

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $pool as $id => $product ) {
		$source = (string) get_post_meta( $id, '_nwcs_source', true );
		$terms  = array();

		foreach ( wp_get_object_terms( $id, NWCS_PRODUCT_TAX ) as $term ) {
			$terms[] = array( 'name' => $term->name, 'slug' => $term->slug );
		}

		$key          = nwcs_package_key( (string) $product['code'], $source, (string) $product['slug'] );
		$keys[ $id ]  = $key;
		$products[]   = array(
			'key'        => $key,
			'code'       => (string) $product['code'],
			'source'     => $source,
			'slug'       => (string) $product['slug'],
			'title'      => (string) $product['title'],
			'short'      => (string) $product['short'],
			'body'       => (string) $product['body'],
			'meta_title' => (string) ( $product['meta_title'] ?? '' ),
			'meta_desc'  => (string) ( $product['meta_desc'] ?? '' ),
			'price'      => (string) $product['price'],
			'spec'       => (string) $product['spec'],
			'details'    => (array) ( $product['details'] ?? array() ),
			'order'      => (int) $product['order'],
			'categories' => $terms,
			'tables'     => (array) ( $product['tables'] ?? array() ),
		);
	}

	restore_current_blog();

	$sites = array();

	foreach ( nwcs_editable_sites() as $blog_id => $site ) {
		$site_key = (string) ( $site['manifest']['site_key'] ?? '' );
		$settings = nwcs_site_product_settings( (int) $blog_id );

		if ( '' === $site_key || ! $settings['selected'] ) {
			continue;
		}

		$selected = array();
		foreach ( $settings['selected'] as $id ) {
			if ( isset( $keys[ $id ] ) ) {
				$selected[] = $keys[ $id ];
			}
		}

		$sites[] = array(
			'site_key' => $site_key,
			'label'    => (string) $site['label'],
			'selected' => $selected,
		);
	}

	$placements = array();

	foreach ( nwcs_pool_categories() as $slug => $term ) {
		if ( $term['placement'] ) {
			$placements[] = array(
				'slug'      => (string) $slug,
				'name'      => (string) $term['name'],
				'placement' => $term['placement'],
			);
		}
	}

	return array(
		'format'     => NWCS_PACKAGE_FORMAT,
		'version'    => NWCS_PACKAGE_VERSION,
		'created'    => gmdate( 'c' ),
		'from'       => network_home_url( '/' ),
		'headings'   => nwcs_product_headings(),
		'products'   => $products,
		'sites'      => $sites,
		'placements' => $placements,
	);
}

add_action( 'admin_post_nwcs_package_export', 'nwcs_handle_package_export' );
function nwcs_handle_package_export(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_package_export' );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=havuz-paketi-' . gmdate( 'Y-m-d' ) . '.json' );

	echo wp_json_encode( nwcs_package_build(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapingOutput -- JSON indirme.
	exit;
}

/**
 * Paketi hedef havuza uygular. $apply false ise hicbir sey yazilmaz, yalnizca rapor.
 *
 * @return array Rapor: sayilar, site satirlari, uyarilar.
 */
function nwcs_package_apply( array $package, bool $apply ): array {
	$report = array(
		'apply'    => $apply,
		'new'      => 0,
		'existing' => 0,
		'skipped'  => 0,
		'terms'    => 0,
		'tables'   => 0,
		'headings' => 0,
		'placements' => 0,
		'sites'    => array(),
		'warnings' => array(),
	);

	if ( NWCS_PACKAGE_FORMAT !== ( $package['format'] ?? '' ) || ! in_array( (int) ( $package['version'] ?? 0 ), array( 1, 2, NWCS_PACKAGE_VERSION ), true ) || ! is_array( $package['products'] ?? null ) ) {
		$report['warnings'][] = 'Bu dosya bir Havuz Paketi değil ya da bu panelle uyumlu olmayan bir sürüm. Diğer kurulumdan “Paketi indir” ile yeni bir paket alıp onu yükleyin.';

		return $report;
	}

	// Detay basliklari: hedefte olmayanlar eklenir (zorunluluguyla); olanlar degismez.
	$headings = nwcs_product_headings();
	$last     = $headings ? max( array_column( $headings, 'order' ) ) : 0;

	foreach ( (array) ( $package['headings'] ?? array() ) as $row ) {
		$label = nwcs_clean_text( (string) ( $row['label'] ?? '' ) );
		$key   = nwcs_heading_key( $label );

		if ( ! nwcs_heading_is_valid( $label ) || isset( $headings[ $key ] ) ) {
			continue;
		}

		$last            += 10;
		$headings[ $key ] = array( 'label' => $label, 'required' => ! empty( $row['required'] ), 'order' => $last );
		++$report['headings'];
	}

	if ( $apply && $report['headings'] ) {
		nwcs_save_product_headings( $headings );
	}

	$map     = array(); // paket anahtari => hedefteki urun kimligi
	$created = array(); // yeni acilan urun => kategori slug'lari

	switch_to_blog( nwcs_pool_blog_id() );

	foreach ( $package['products'] as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$key    = (string) ( $item['key'] ?? '' );
		$title  = nwcs_clean_text( $item['title'] ?? '' );
		$code   = nwcs_normalize_product_code( (string) ( $item['code'] ?? '' ) );
		$source = esc_url_raw( (string) ( $item['source'] ?? '' ) );
		$slug   = sanitize_title( (string) ( $item['slug'] ?? '' ) );

		if ( '' === $key || '' === $title ) {
			++$report['skipped'];
			continue;
		}

		// Hedefte ayni urun: kod, kaynak, adres adi (cope atilmis olanlar da; geri gelmesinler).
		$id = '' !== $code ? nwcs_product_id_by_code( $code ) : 0;

		if ( ! $id && '' !== $source ) {
			$found = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ), 'meta_key' => '_nwcs_source', 'meta_value' => $source, 'numberposts' => 1, 'fields' => 'ids' ) );
			$id    = (int) ( $found[0] ?? 0 );
		}

		if ( ! $id && '' !== $slug ) {
			$found = get_posts( array( 'post_type' => NWCS_PRODUCT_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ), 'name' => $slug, 'numberposts' => 1, 'fields' => 'ids' ) );
			$id    = (int) ( $found[0] ?? 0 );
		}

		$tables = nwcs_sanitize_product_tables( (array) ( $item['tables'] ?? array() ) );

		if ( $id ) {
			++$report['existing'];

			// Mevcut urune dokunulmaz; tek istisna hic tablosu yoksa paketteki
			// tablolarin eklenmesi (hedefteki bir duzenlemenin ustune yazilmaz).
			if ( $tables && ! get_post_meta( $id, '_nwcs_tables', true ) ) {
				++$report['tables'];

				if ( $apply ) {
					update_post_meta( $id, '_nwcs_tables', wp_slash( $tables ) );
				}
			}

			if ( 'publish' === get_post_status( $id ) ) {
				$map[ $key ] = $id;
			}
			continue;
		}

		++$report['new'];

		// Denemede yeni urunler gecici (eksi) kimlikle sayilir.
		if ( ! $apply ) {
			$map[ $key ] = -$report['new'];
			continue;
		}

		$id = (int) wp_insert_post(
			wp_slash(
				array(
					'post_type'    => NWCS_PRODUCT_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => wp_kses_post( (string) ( $item['body'] ?? '' ) ),
					'menu_order'   => (int) ( $item['order'] ?? 0 ),
				)
			)
		);

		if ( ! $id ) {
			$report['warnings'][] = 'Ürün eklenemedi: ' . $title . '. Paketi yeniden aktarın; sorun sürerse geliştiriciye haber verin.';
			continue;
		}

		// Kod baska urunde varsa yazilmaz (kod havuzda tekildir).
		if ( '' !== $code && nwcs_product_id_by_code( $code, $id ) ) {
			$code = '';
		}

		update_post_meta( $id, '_nwcs_code', $code );
		update_post_meta( $id, '_nwcs_source', wp_slash( $source ) );
		update_post_meta( $id, '_nwcs_short', wp_slash( nwcs_clean_text( $item['short'] ?? '', true ) ) );
		update_post_meta( $id, '_nwcs_price', wp_slash( nwcs_clean_text( $item['price'] ?? '' ) ) );
		// Eski paketlerde yok: bos kalir (sayfa otomatik baslik/aciklama kullanir).
		nwcs_product_set_meta_text( $id, '_nwcs_meta_title', nwcs_clean_text( $item['meta_title'] ?? '' ) );
		nwcs_product_set_meta_text( $id, '_nwcs_meta_desc', nwcs_clean_text( $item['meta_desc'] ?? '' ) );

		// Detaylar ve spec tek yaziciyla: surum 2'de 'details', surum 1'de spec metni.
		$spec    = nwcs_clean_text( $item['spec'] ?? '' );
		$details = is_array( $item['details'] ?? null ) && $item['details'] ? $item['details'] : nwcs_parse_spec_pairs( $spec );
		nwcs_product_write_details( $id, $details, nwcs_spec_is_note( $spec ) ? $spec : '' );

		if ( $tables ) {
			update_post_meta( $id, '_nwcs_tables', wp_slash( $tables ) );
		}

		$term_ids = array();
		foreach ( (array) ( $item['categories'] ?? array() ) as $category ) {
			$name = nwcs_clean_text( $category['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$term = get_term_by( 'name', $name, NWCS_PRODUCT_TAX );

			if ( ! $term ) {
				$made = wp_insert_term( $name, NWCS_PRODUCT_TAX, array( 'slug' => sanitize_title( (string) ( $category['slug'] ?? $name ) ) ) );
				$term = is_wp_error( $made ) ? null : get_term( (int) $made['term_id'], NWCS_PRODUCT_TAX );
				++$report['terms'];
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		if ( $term_ids ) {
			wp_set_object_terms( $id, $term_ids, NWCS_PRODUCT_TAX, false );
		}

		$map[ $key ]    = $id;
		$created[ $id ] = wp_get_object_terms( $id, NWCS_PRODUCT_TAX, array( 'fields' => 'slugs' ) );
	}

	restore_current_blog();

	if ( $apply ) {
		nwcs_pool_flush_cache();
		nwcs_pool_products( true );
	}

	// Kategori yerlesimleri (surum 3): hedefte bos olan kategori/site cifti yazilir.
	$site_keys = array_flip( array_column( nwcs_catalog_sites(), 'site_key' ) );
	$targets   = nwcs_pool_categories();

	foreach ( (array) ( $package['placements'] ?? array() ) as $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$name = nwcs_clean_text( (string) ( $row['name'] ?? '' ) );

		if ( ! isset( $targets[ $slug ] ) ) {
			$slug = '';

			foreach ( $targets as $target_slug => $target ) {
				if ( '' !== $name && $target['name'] === $name ) {
					$slug = (string) $target_slug;
				}
			}
		}

		if ( '' === $slug ) {
			continue;
		}

		foreach ( nwcs_clean_placement( (array) ( $row['placement'] ?? array() ) ) as $site_key => $parent ) {
			if ( ! isset( $site_keys[ $site_key ] ) || '' !== ( $targets[ $slug ]['placement'][ $site_key ] ?? '' ) ) {
				continue;
			}

			++$report['placements'];

			if ( $apply ) {
				nwcs_set_placement( $slug, $site_key, $parent );
			}
		}
	}

	// Yeni urunler kategorilerinin yerlestigi sitelerde de gorunur.
	if ( $apply ) {
		foreach ( $created as $id => $slugs ) {
			nwcs_placement_reveal( array( (int) $id ), is_array( $slugs ) ? $slugs : array() );
		}
	}

	// Site secimleri: site anahtariyla.
	$by_key = array();
	foreach ( nwcs_editable_sites() as $blog_id => $site ) {
		$site_key = (string) ( $site['manifest']['site_key'] ?? '' );

		if ( '' !== $site_key ) {
			$by_key[ $site_key ] = array( (int) $blog_id, (string) $site['label'] );
		}
	}

	foreach ( (array) ( $package['sites'] ?? array() ) as $row ) {
		$site_key = sanitize_key( (string) ( $row['site_key'] ?? '' ) );

		if ( ! isset( $by_key[ $site_key ] ) ) {
			$report['warnings'][] = sprintf( 'Pakette "%s" sitesinin seçimi var ama bu kurulumda o site yok; atlandı.', (string) ( $row['label'] ?? $site_key ) );
			continue;
		}

		list( $blog_id, $label ) = $by_key[ $site_key ];

		$current = nwcs_site_product_settings( $blog_id );
		$wanted  = array();

		foreach ( (array) ( $row['selected'] ?? array() ) as $key ) {
			$id = $map[ (string) $key ] ?? 0;

			if ( $id ) {
				$wanted[] = $id;
			}
		}

		// Mevcut secim ve sira korunur; yeni olanlar sona.
		$add = array_values( array_filter( array_unique( $wanted ), static fn( int $id ): bool => $id < 0 || ! in_array( $id, $current['selected'], true ) ) );

		$report['sites'][] = array(
			'label'   => $label,
			'before'  => count( $current['selected'] ),
			'added'   => count( $add ),
			'package' => count( (array) ( $row['selected'] ?? array() ) ),
		);

		if ( ! $apply || ! $add ) {
			continue;
		}

		switch_to_blog( $blog_id );
		nwcs_save_site_product_settings(
			array(
				'mode'      => 'selected',
				'selected'  => array_merge( $current['selected'], $add ),
				'overrides' => $current['overrides'],
			)
		);
		restore_current_blog();

		// Sayfa onbellegi eklentisi varsa (LiteSpeed Cache) site temizlenir (includes/cache-purge.php).
		nwcs_cache_mark_blog_dirty( $blog_id );
	}

	return $report;
}

add_action( 'admin_post_nwcs_package_import', 'nwcs_handle_package_import' );
function nwcs_handle_package_import(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_package_import' );

	$file   = $_FILES['paket']['tmp_name'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida dogrulanir.
	$name   = sanitize_file_name( (string) ( $_FILES['paket']['name'] ?? '' ) );
	$apply  = ! empty( $_POST['uygula'] );
	$report = array( 'apply' => $apply, 'warnings' => array() );

	if ( ! $file || ! is_uploaded_file( $file ) || ! str_ends_with( strtolower( $name ), '.json' ) || filesize( $file ) > 20 * MB_IN_BYTES ) {
		$report['warnings'][] = 'Dosya yüklenmedi. Uzantısı .json olan bir Havuz Paketi dosyası seçin (en fazla 20 MB).';
	} else {
		$package = json_decode( (string) file_get_contents( $file ), true );

		if ( ! is_array( $package ) ) {
			$report['warnings'][] = 'Dosya okunamadı; bozuk olabilir. Diğer kurulumdan paketi yeniden indirip yükleyin. (Teknik: geçerli bir JSON değil)';
		} else {
			// Uzun paketlerde istek yarida kesilmesin.
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}

			$report = nwcs_package_apply( $package, $apply );
		}
	}

	$report['file'] = $name;
	set_site_transient( NWCS_PACKAGE_REPORT . '_' . get_current_user_id(), $report, HOUR_IN_SECONDS );

	wp_safe_redirect( add_query_arg( array( 'page' => NWCS_PACKAGE_SLUG, 'rapor' => 1 ), network_admin_url( 'admin.php' ) ) );
	exit;
}

function nwcs_render_package(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$report = isset( $_GET['rapor'] ) ? get_site_transient( NWCS_PACKAGE_REPORT . '_' . get_current_user_id() ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gosterim.
	$count  = count( nwcs_pool_products() );
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-seo">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Havuz Paketi: kurulumlar arası taşıma (ileri düzey)</h1>
			</div>
		</header>
		<hr class="wp-header-end" />
		<?php nwcs_render_cache_note(); ?>

		<?php if ( is_array( $report ) ) : ?>
			<section class="nwcs-pool__card">
				<h2 class="nwcs-pool__title"><?php echo esc_html( $report['apply'] ? 'Aktarım tamamlandı' : 'Deneme sonucu (hiçbir şey değiştirilmedi)' ); ?></h2>

				<?php if ( isset( $report['new'] ) ) : ?>
					<p class="nwcs-seo__lead">
						<strong><?php echo esc_html( (string) ( $report['file'] ?? '' ) ); ?></strong>:
						<?php echo (int) $report['new']; ?> yeni ürün <?php echo esc_html( $report['apply'] ? 'eklendi' : 'eklenecek' ); ?>,
						<?php echo (int) $report['existing']; ?> ürün bu kurulumda zaten var (dokunulmaz)<?php echo $report['skipped'] ? ', ' . (int) $report['skipped'] . ' ürün adı olmadığı için atlandı' : ''; ?>.
						<?php if ( $report['terms'] ) : ?>
							<?php echo (int) $report['terms']; ?> yeni kategori açıldı.
						<?php endif; ?>
						<?php if ( ! empty( $report['headings'] ) ) : ?>
							<?php echo (int) $report['headings']; ?> yeni detay başlığı <?php echo esc_html( $report['apply'] ? 'eklendi' : 'eklenecek' ); ?>.
						<?php endif; ?>
						<?php if ( ! empty( $report['tables'] ) ) : ?>
							Tablosu olmayan <?php echo (int) $report['tables']; ?> mevcut ürüne paketteki tablolar <?php echo esc_html( $report['apply'] ? 'eklendi' : 'eklenecek' ); ?>.
						<?php endif; ?>
						<?php if ( ! empty( $report['placements'] ) ) : ?>
							<?php echo (int) $report['placements']; ?> kategori yerleşimi (hangi sitede hangi başlığın altında) <?php echo esc_html( $report['apply'] ? 'yazıldı' : 'yazılacak' ); ?>.
						<?php endif; ?>
					</p>

					<?php if ( $report['sites'] ) : ?>
						<table class="widefat striped" style="max-width:640px">
							<thead><tr><th>Site</th><th>Seçili ürün (önce)</th><th><?php echo esc_html( $report['apply'] ? 'Eklenen' : 'Eklenecek' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $report['sites'] as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['label'] ); ?></td>
										<td><?php echo (int) $row['before']; ?></td>
										<td><?php echo (int) $row['added']; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php endif; ?>

				<?php foreach ( (array) $report['warnings'] as $warning ) : ?>
					<p class="nwcs-badge nwcs-badge--warn"><?php echo esc_html( $warning ); ?></p>
				<?php endforeach; ?>

				<?php if ( ! $report['apply'] && ( ! empty( $report['new'] ) || ! empty( $report['tables'] ) || ! empty( $report['headings'] ) || ! empty( $report['placements'] ) || array_sum( array_column( (array) ( $report['sites'] ?? array() ), 'added' ) ) ) ) : ?>
					<p class="nwcs-seo__lead">Sonuç doğruysa aynı dosyayı aşağıdan <strong>Aktar</strong> seçeneğiyle yükleyin.</p>
				<?php elseif ( ! $report['apply'] && isset( $report['new'] ) ) : ?>
					<p class="nwcs-seo__lead">Aktarılacak yeni bir şey yok: paketteki her şey bu kurulumda zaten var.</p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="nwcs-pool__card">
			<h2 class="nwcs-pool__title">Paketi yükle</h2>
			<p class="nwcs-seo__lead">
				Başka bir kurulumdan indirilen Havuz Paketi’ni (.json) bu havuza aktarır.
				Bu havuzda zaten olan ürünlere dokunulmaz; aynı ürün iki kez eklenmez. Sitelerin mevcut ürün seçimi ve sırası korunur, yeni ürünler sona eklenir.
				Görseller taşınmaz.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="nwcs_package_import" />
				<?php wp_nonce_field( 'nwcs_package_import' ); ?>
				<p><input type="file" name="paket" accept=".json,application/json" required /></p>
				<p>
					<label><input type="radio" name="uygula" value="" checked /> Önce dene: ne olacağını göster, hiçbir şey yazma</label><br />
					<label><input type="radio" name="uygula" value="1" /> Aktar: ürünleri ekle ve site seçimlerini güncelle</label>
				</p>
				<p><button type="submit" class="button button-primary">Yükle</button></p>
			</form>
		</section>

		<section class="nwcs-pool__card">
			<h2 class="nwcs-pool__title">Bu havuzun paketini indir</h2>
			<p class="nwcs-seo__lead">
				Bu havuzdaki <?php echo (int) $count; ?> ürün, kategorileri, detay başlıkları ve her sitenin ürün seçimi tek dosyada.
				Dosyada kişisel veri ya da parola yoktur; ürün metinleri ve seçimler vardır.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nwcs_package_export" />
				<?php wp_nonce_field( 'nwcs_package_export' ); ?>
				<p><button type="submit" class="button">Paketi indir (.json)</button></p>
			</form>
		</section>
	</div>
	<?php
}
