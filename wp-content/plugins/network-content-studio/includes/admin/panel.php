<?php
/**
 * Ag yonetimindeki tek giris noktasi: Icerik Studyosu.
 *
 * Duzen: solda sayfa secimi + bolum listesi + duzenleyici, ortada sitenin
 * calisan onizlemesi, sagda dikey site secici.
 *
 * JavaScript kapaliyken de calisir: bolum baglantilari normal baglanti,
 * duzenleyici sunucu tarafinda cizilir ve form admin-post.php'ye gider.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_MENU_SLUG  = 'nwcs-studio';
const NWCS_CAPABILITY = 'manage_network_options';

add_action( 'network_admin_menu', 'nwcs_register_menu' );
function nwcs_register_menu(): void {
	add_menu_page(
		'İçerik Stüdyosu',
		'İçerik Stüdyosu',
		NWCS_CAPABILITY,
		NWCS_MENU_SLUG,
		'nwcs_render_panel',
		'dashicons-edit-page',
		// Son kullanici menuleri art arda: Icerik Studyosu (3), Urun Havuzu
		// (3.1), Medya Havuzu (3.2). Gelistirici menusu en altta (seo.php).
		3
	);
}

add_action( 'admin_enqueue_scripts', 'nwcs_admin_assets' );
function nwcs_admin_assets( string $hook ): void {
	// Icerik Studyosu, Urun Havuzu ve Medya Havuzu ayni varliklari kullanir.
	$ours = str_contains( $hook, NWCS_MENU_SLUG )
		|| str_contains( $hook, NWCS_POOL_SLUG )
		|| str_contains( $hook, NWCS_MEDIA_SLUG )
		|| str_contains( $hook, NWCS_SEO_SLUG )
		|| str_contains( $hook, 'nwcs-redirects' )
		|| str_contains( $hook, 'nwcs-bulk-update' )
		|| str_contains( $hook, 'nwcs-placeholders' )
		|| str_contains( $hook, 'nwcs-pool-package' )
		|| str_contains( $hook, 'nwcs-mail-test' )
		|| str_contains( $hook, 'nwcs-webp' );

	if ( ! $ours ) {
		return;
	}

	wp_enqueue_style( 'nwcs-admin', NWCS_URL . 'assets/admin.css', array(), NWCS_VERSION );
	wp_enqueue_script( 'nwcs-admin', NWCS_URL . 'assets/admin.js', array(), NWCS_VERSION, true );

	// Urun formu (detay basliklari, tablolar, site istisnalari) ve Excel
	// adimlari yalnizca Urun Havuzu sayfalarinda gerekir.
	if ( str_contains( $hook, NWCS_POOL_SLUG ) ) {
		wp_enqueue_script( 'nwcs-import', NWCS_URL . 'assets/import.js', array(), NWCS_VERSION, true );
		wp_enqueue_script( 'nwcs-pool-details', NWCS_URL . 'assets/pool-details.js', array(), NWCS_VERSION, true );
		wp_enqueue_script( 'nwcs-overrides', NWCS_URL . 'assets/overrides.js', array( 'nwcs-admin' ), NWCS_VERSION, true );
		wp_enqueue_script( 'nwcs-pool-tables', NWCS_URL . 'assets/pool-tables.js', array(), NWCS_VERSION, true );
		wp_enqueue_script( 'nwcs-pool-video', NWCS_URL . 'assets/pool-video.js', array(), NWCS_VERSION, true );
	}

	// Fotograf Kutusu yalnizca Kategoriler sayfasinda.
	if ( str_contains( $hook, NWCS_CATEGORIES_SLUG ) ) {
		wp_enqueue_script( 'nwcs-pool-photos', NWCS_URL . 'assets/pool-photos.js', array( 'nwcs-admin' ), NWCS_VERSION, true );
	}
	wp_localize_script(
		'nwcs-admin',
		'nwcsPanel',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'nwcs_panel' ),
			// Fotograf Kutusu: tarayici bu sinirdan buyuk dosyayi gondermez.
			'uploadMax'     => wp_max_upload_size(),
			'uploadMaxText' => size_format( wp_max_upload_size() ),
			'text'       => array(
				'confirmDiscard' => 'Kaydedilmemiş değişiklikleriniz var. Son kaydedilen hâle dönülsün mü?',
				'confirmLeave'   => 'Kaydedilmemiş değişiklikleriniz var.',
				'confirmRow'     => 'Bu satır silinsin mi? Kaydettiğinizde kalıcı olur.',
				'saving'         => 'Kaydediliyor…',
				'saved'          => 'Yayınlandı',
				'saveError'      => 'Kaydedilemedi. Lütfen tekrar deneyin.',
				'loading'        => 'Yükleniyor…',
			),
		)
	);
}

/**
 * Panel sayfalarinda WordPress yonetim menusu katlanmis baslar; boylece
 * onizlemeye daha fazla yer kalir.
 *
 * Kullanici WordPress'in kendi "Menuyu daralt" dugmesiyle menuyu actiysa
 * (mfold = o) ona dokunmayiz; tercih kullanicinin kalir.
 */
add_filter( 'admin_body_class', 'nwcs_fold_admin_menu' );
function nwcs_fold_admin_menu( string $classes ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$ours = in_array( $page, array( NWCS_MENU_SLUG, NWCS_POOL_SLUG, NWCS_MEDIA_SLUG, NWCS_SEO_SLUG, 'nwcs-redirects', 'nwcs-bulk-update', 'nwcs-placeholders', 'nwcs-pool-package', 'nwcs-pool-categories', 'nwcs-pool-headings', 'nwcs-pool-descriptions', 'nwcs-mail-test', 'nwcs-webp' ), true );

	if ( $ours && 'o' !== get_user_setting( 'mfold' ) ) {
		$classes .= ' folded';
	}

	return $classes;
}

/**
 * Panelde gorunen site adi: alan adi olmadan, kisa. Manifestte 'panel_label'
 * varsa o; yoksa 'site_label'in sonundaki " · alanadi.com" kismi atilir.
 */
function nwcs_site_panel_name( array $manifest, int $blog_id ): string {
	$name = trim( (string) ( $manifest['panel_label'] ?? '' ) );

	if ( '' === $name ) {
		$name = trim( (string) preg_replace( '/\s*[·|\-]\s*[a-z0-9.-]+\.(?:com|net|org|com\.tr|tr)$/iu', '', (string) ( $manifest['site_label'] ?? '' ) ) );
	}

	return '' !== $name ? $name : (string) get_blog_option( $blog_id, 'blogname', '' );
}

/**
 * Ag icindeki, manifesti olan siteler. Ana site (ag yonetimi) listeye girmez.
 */
function nwcs_editable_sites(): array {
	$sites = array();

	foreach ( get_sites( array( 'number' => 100 ) ) as $site ) {
		$blog_id  = (int) $site->blog_id;
		$manifest = nwcs_manifest_for_blog( $blog_id );

		if ( empty( $manifest['pages'] ) ) {
			continue;
		}

		$sites[ $blog_id ] = array(
			'blog_id'  => $blog_id,
			'label'    => nwcs_site_panel_name( $manifest, $blog_id ),
			'url'      => get_home_url( $blog_id, '/' ),
			'path'     => $site->path,
			'manifest' => $manifest,
		);
	}

	return $sites;
}

/**
 * Sayfadaki bilesenleri gosterim sirasina gore verir: once sabit bilesenler
 * (manifest sirasi), sonra kayitli siraya gore siralanabilir bolumler.
 */
function nwcs_ordered_components( array $manifest, string $page_key, int $blog_id ): array {
	$components = array_keys( $manifest['pages'][ $page_key ]['components'] ?? array() );
	$sortable   = nwcs_sortable_sections( $manifest, $page_key );

	// Arama ve Paylasim her sayfada en sonda durur; sayfa iceriginin parcasi degil.
	$seo        = in_array( NWCS_SEO_COMPONENT, $components, true ) ? array( NWCS_SEO_COMPONENT ) : array();
	$components = array_values( array_diff( $components, $seo ) );

	if ( ! $sortable ) {
		return array_merge( $components, $seo );
	}

	$fixed  = array_values( array_diff( $components, $sortable ) );
	$sorted = nwcs_section_order( $page_key, $blog_id, $manifest );

	return array_merge( $fixed, $sorted, $seo );
}

/**
 * Panel ana govdesi.
 */
function nwcs_render_panel(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.' ) );
	}

	$sites = nwcs_editable_sites();

	if ( ! $sites ) {
		echo '<div class="wrap"><h1>İçerik Stüdyosu</h1><div class="notice notice-error"><p>';
		echo 'Alan manifesti olan bir alt site bulunamadı. Temaların ağ genelinde etkin olduğundan emin olun.';
		echo '</p></div></div>';

		return;
	}

	$site_ids = array_keys( $sites );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum secimi.
	$blog_id = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : 0;
	if ( ! in_array( $blog_id, $site_ids, true ) ) {
		$blog_id = (int) reset( $site_ids );
	}

	$site     = $sites[ $blog_id ];
	$manifest = $site['manifest'];
	// Gizli sayfalar (orn. site geneli SEO) kendi sekmelerinden duzenlenir.
	$pages    = nwcs_visible_pages( $manifest );

	$page_key = isset( $_GET['content_page'] ) ? sanitize_key( wp_unslash( $_GET['content_page'] ) ) : '';

	// Sekmesi olmayan sayfa (kategori sayfalari gibi) sayfa seciciden acilir;
	// o sayfa acikken sekme listesinin sonunda gorunur.
	if ( ! isset( $pages[ $page_key ] ) && isset( nwcs_pickable_pages( $manifest )[ $page_key ] ) ) {
		$pages[ $page_key ] = $manifest['pages'][ $page_key ];
	}

	if ( ! isset( $pages[ $page_key ] ) ) {
		$page_key = isset( $pages['home'] ) ? 'home' : (string) array_key_first( $pages );
	}

	$component_key = isset( $_GET['component'] ) ? sanitize_key( wp_unslash( $_GET['component'] ) ) : '';
	if ( ! isset( $pages[ $page_key ]['components'][ $component_key ] ) ) {
		$component_key = '';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$ordered  = nwcs_ordered_components( $manifest, $page_key, $blog_id );
	$sortable = nwcs_sortable_sections( $manifest, $page_key );
	$preview  = nwcs_preview_url( $blog_id, $manifest, $page_key );

	// Sayfa seciciden bir urun sayfasi secildiyse onizleme o adreste acilir.
	$preview_path = isset( $_GET['onizleme'] ) ? nwcs_clean_preview_path( wp_unslash( $_GET['onizleme'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $preview_path ) {
		$preview = get_home_url( $blog_id, $preview_path );
	}
	?>
	<div class="wrap nwcs-wrap"
		data-nwcs-site="<?php echo esc_attr( (string) $blog_id ); ?>"
		data-nwcs-page="<?php echo esc_attr( $page_key ); ?>">

		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu
					aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>İçerik Stüdyosu</h1>
			</div>

			<nav class="nwcs-bar__sites" aria-label="Site seçici">
				<?php foreach ( $sites as $id => $s ) : ?>
					<a class="nwcs-sitepill<?php echo $id === $blog_id ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( nwcs_panel_url( $id ) ); ?>"
						<?php echo $id === $blog_id ? 'aria-current="page"' : ''; ?>>
						<span class="nwcs-sitepill__dot" aria-hidden="true"></span>
						<span class="nwcs-sitepill__name"><?php echo esc_html( $s['label'] ); ?></span>
						<span class="nwcs-sitepill__path"><?php echo esc_html( $s['path'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="nwcs-bar__tools">
				<div class="nwcs-device" role="group" aria-label="Önizleme genişliği">
					<button type="button" class="is-active" data-nwcs-device="desktop" title="Masaüstü genişliği">Masaüstü</button>
					<button type="button" data-nwcs-device="mobile" title="Telefon genişliği">Telefon</button>
				</div>
				<a class="nwcs-linkout" href="<?php echo esc_url( $site['url'] ); ?>" target="_blank" rel="noopener">Siteyi aç ↗</a>
			</div>
		</header>

		<?php nwcs_render_notices(); ?>

		<p class="nwcs-help">
			<span class="nwcs-help__step"><b>1</b> Üstten siteyi seçin</span>
			<span class="nwcs-help__step"><b>2</b> Önizlemede değiştirmek istediğiniz yazıya veya görsele tıklayın</span>
			<span class="nwcs-help__step"><b>3</b> Soldaki formu doldurup <em>Kaydet ve Yayınla</em> deyin</span>
		</p>

		<div class="nwcs-app">
			<!-- Sol: bolum listesi + duzenleyici -->
			<div class="nwcs-side">
				<div class="nwcs-pages" role="tablist" aria-label="Sayfalar">
					<?php foreach ( $pages as $key => $page ) : ?>
						<a class="nwcs-pages__tab<?php echo $key === $page_key ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( nwcs_panel_url( $blog_id, $key ) ); ?>">
							<?php echo esc_html( nwcs_short_page_label( $page['label'] ) ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<?php nwcs_render_page_picker( $blog_id, $manifest, $page_key, $preview_path ); ?>

				<!-- Ekran 1: bolum listesi -->
				<div class="nwcs-screen nwcs-screen--list<?php echo $component_key ? '' : ' is-active'; ?>" data-nwcs-screen="list">
					<p class="nwcs-screen__hint">
						Bir bölüme tıklayın ya da önizlemede düzenlemek istediğiniz yere tıklayın.
						Değişiklik yalnızca <strong><?php echo esc_html( $site['label'] ); ?></strong> sitesine kaydedilir.
					</p>

					<ul class="nwcs-sections" data-nwcs-sections>
						<?php
						foreach ( $ordered as $index => $key ) :
							$component   = $pages[ $page_key ]['components'][ $key ] ?? null;
							$is_sortable = in_array( $key, $sortable, true );

							if ( ! $component ) {
								continue;
							}
							?>
							<li class="nwcs-section<?php echo $is_sortable ? ' is-sortable' : ''; ?>" data-nwcs-section-key="<?php echo esc_attr( $key ); ?>">
								<a class="nwcs-section__open<?php echo $key === $component_key ? ' is-active' : ''; ?>"
									href="<?php echo esc_url( nwcs_panel_url( $blog_id, $page_key, $key ) ); ?>"
									data-nwcs-open-component="<?php echo esc_attr( $key ); ?>">
									<span class="nwcs-section__name"><?php echo esc_html( $component['label'] ); ?></span>
									<span class="nwcs-section__meta"><?php echo (int) count( $component['fields'] ); ?> alan</span>
								</a>

								<?php if ( $is_sortable ) : ?>
									<span class="nwcs-section__move">
										<button type="button" class="nwcs-move" data-nwcs-move="up" aria-label="Yukarı taşı" title="Yukarı taşı">↑</button>
										<button type="button" class="nwcs-move" data-nwcs-move="down" aria-label="Aşağı taşı" title="Aşağı taşı">↓</button>
									</span>
								<?php else : ?>
									<span class="nwcs-section__fixed" title="Bu bölümün yeri sabittir">sabit</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $sortable ) : ?>
						<p class="nwcs-screen__hint nwcs-screen__hint--muted">
							Okları kullanarak bölümlerin sırasını değiştirebilirsiniz. Üst menü ve footer sabittir.
						</p>
					<?php endif; ?>
				</div>

				<!-- Ekran 2: duzenleyici -->
				<div class="nwcs-screen nwcs-screen--editor<?php echo $component_key ? ' is-active' : ''; ?>" data-nwcs-screen="editor">
					<div class="nwcs-editor" data-nwcs-editor>
						<?php
						if ( $component_key ) {
							nwcs_render_editor_form( $blog_id, $manifest, $page_key, $component_key );
						}
						?>
					</div>
				</div>
			</div>

			<!-- Sag: calisan onizleme -->
			<div class="nwcs-preview" data-nwcs-preview-wrap>
				<div class="nwcs-preview__bar">
					<span class="nwcs-preview__badge">Canlı önizleme</span>
					<span class="nwcs-preview__url"><?php echo esc_html( str_replace( array( 'http://', 'https://' ), '', $preview ) ); ?></span>
					<button type="button" class="nwcs-reload" data-nwcs-reload>Yenile</button>
				</div>
				<div class="nwcs-preview__frame">
					<iframe
						data-nwcs-preview
						title="Site önizlemesi"
						src="<?php echo esc_url( add_query_arg( 'nwcs_preview', nwcs_preview_token( $blog_id ), $preview ) ); ?>"></iframe>
				</div>
			</div>

		</div>

		<div class="nwcs-toast" data-nwcs-toast hidden></div>
	</div>
	<?php
}

/**
 * Sekmelerde kisa sayfa adi.
 */
function nwcs_short_page_label( string $label ): string {
	$short = preg_replace( '/\s*\(.*\)$/u', '', $label );

	return $short ? $short : $label;
}

/**
 * Sekmesi olmayan ama panelden acilabilen sayfalar (kategori sayfalari gibi).
 * Site geneli SEO sayfasi kendi ekranindan duzenlendigi icin haric.
 */
function nwcs_pickable_pages( array $manifest ): array {
	return array_filter(
		$manifest['pages'] ?? array(),
		static fn( $page, $key ): bool => ! empty( $page['hidden'] ) && 'site_seo' !== $key && ! empty( $page['path'] ),
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Onizleme adresi olarak kabul edilen yol: sitenin kendi icinde, "/" ile
 * baslayan, sema ya da baska alan adi tasimayan bir yol.
 */
function nwcs_clean_preview_path( $path ): string {
	$path = (string) $path;

	if ( ! preg_match( '#^/[A-Za-z0-9/_\-.%]*$#', $path ) || str_contains( $path, '//' ) || str_contains( $path, '..' ) ) {
		return '';
	}

	return $path;
}

/**
 * Sitede bu yolda gercek bir WordPress sayfasi var mi (Kocist'teki ornek /urun/ gibi)?
 */
function nwcs_page_path_exists( int $blog_id, string $path ): bool {
	switch_to_blog( $blog_id );
	$page = get_page_by_path( trim( $path, '/' ), OBJECT, 'page' );
	restore_current_blog();

	return $page instanceof WP_Post && 'publish' === $page->post_status;
}

/**
 * Havuz urunlerinin ortak metinlerinin durdugu manifest sayfasi: manifestte
 * 'product_page' yazilidir; yoksa adresi /urun/ olan sayfa; yoksa bos.
 */
function nwcs_product_page_key( array $manifest ): string {
	$key = (string) ( $manifest['product_page'] ?? '' );

	if ( '' !== $key && isset( $manifest['pages'][ $key ] ) ) {
		return $key;
	}

	foreach ( $manifest['pages'] ?? array() as $page_key => $page ) {
		if ( '/urun/' === ( $page['path'] ?? '' ) ) {
			return (string) $page_key;
		}
	}

	return '';
}

/**
 * Sayfa bulucu bu sitede acik mi? Manifestte 'page_finder' => false ile ya da
 * asagidaki listeyle kapatilir. Liste bos: İstanbul Keresteci de kendi gizli
 * urun ve yazi sayfalariyla bulucuyu kullaniyor.
 */
const NWCS_PAGE_FINDER_OFF = array();

function nwcs_page_finder_enabled( array $manifest ): bool {
	if ( false === ( $manifest['page_finder'] ?? true ) ) {
		return false;
	}

	return ! in_array( (string) ( $manifest['site_key'] ?? '' ), NWCS_PAGE_FINDER_OFF, true );
}

/**
 * Sayfa bulucu: sitenin panelden acilabilen her yeri tek arama kutusunda.
 * Sekmeli sayfalar, sekmesi olmayan sayfalar (kategori sayfalari) ve bu
 * sitede gosterilen her urunun sayfasi. Secilince panel o sayfayla acilir;
 * onizlemede metne tiklamak her zamanki gibi calisir (urune ait alanlar
 * Urun Havuzu'na gider).
 *
 * Liste sunucuda uretilir, arama tarayicida yapilir (assets/admin.js).
 * JavaScript kapaliysa kutu yerine duz bir baglanti listesi kalir.
 */
function nwcs_render_page_picker( int $blog_id, array $manifest, string $page_key, string $preview_path ): void {
	if ( ! nwcs_page_finder_enabled( $manifest ) ) {
		return;
	}

	$items   = array();
	$current = '';

	foreach ( nwcs_visible_pages( $manifest ) as $key => $page ) {
		$items[] = array(
			't' => nwcs_short_page_label( (string) $page['label'] ),
			'k' => 'Sayfa',
			'g' => 'Sayfalar',
			'u' => nwcs_panel_url( $blog_id, (string) $key ),
		);
	}

	// Urunlerin ortak metin sayfasi (/urun/): kendi adresi urun gostermez; onizleme
	// sitedeki ilk urunun sayfasinda acilir (yoksa 404 gorunurdu).
	$product_page_key = nwcs_product_page_key( $manifest );
	$sample_product   = '';

	if ( '' !== $product_page_key && function_exists( 'nwcs_site_products' ) && nwcs_site_supports_products( $blog_id ) ) {
		switch_to_blog( $blog_id );
		foreach ( nwcs_site_products() as $product ) {
			if ( nwcs_product_has_page( $product, $blog_id ) ) {
				$sample_product = '/urun/' . $product['slug'] . '/';
				break;
			}
		}
		restore_current_blog();
	}

	foreach ( nwcs_pickable_pages( $manifest ) as $key => $page ) {
		// "Kategori: Kamelya" -> ad "Kamelya", tur "Kategori"
		$label = (string) $page['label'];
		$kind  = 'Sayfa';

		if ( preg_match( '/^([^:]{2,20}):\s*(.+)$/u', $label, $m ) ) {
			$kind  = $m[1];
			$label = $m[2];
		}

		// Grup, sayfa anahtarinin onekinden: yazi-, kat-/grp-/seri-, urun-.
		$key   = (string) $key;
		$group = 'Diğer sayfalar';

		if ( str_starts_with( $key, 'yazi-' ) ) {
			$group = 'Blog yazıları';
		} elseif ( preg_match( '/^(kat|grp|seri)-/', $key ) ) {
			$group = 'Kategori sayfaları';
		} elseif ( str_starts_with( $key, 'urun-' ) ) {
			$group = 'Ürün sayfaları';
		}

		$url = nwcs_panel_url( $blog_id, $key );

		if ( $key === $product_page_key && '' !== $sample_product && '/urun/' === ( $page['path'] ?? '' ) && ! nwcs_page_path_exists( $blog_id, '/urun/' ) ) {
			$url = add_query_arg( 'onizleme', rawurlencode( $sample_product ), $url );
		}

		$items[] = array(
			't' => $label,
			'k' => $kind,
			'g' => $group,
			'u' => $url,
		);

		if ( '' === $preview_path && $key === $page_key ) {
			$current = 'Sayfa' === $kind ? $label : $label . ' (' . mb_strtolower( $kind, 'UTF-8' ) . ')';
		}
	}

	$product_count = 0;

	if ( function_exists( 'nwcs_site_products' ) && nwcs_site_supports_products( $blog_id ) ) {
		$product_page = nwcs_product_page_key( $manifest );

		switch_to_blog( $blog_id );
		$products = nwcs_site_products();
		restore_current_blog();

		foreach ( $products as $product ) {
			// Detay sayfasi olmayan urunun acilacak sayfasi yok.
			if ( ! nwcs_product_has_page( $product, $blog_id ) ) {
				continue;
			}

			$path = '/urun/' . $product['slug'] . '/';
			$cats = array_values( (array) $product['categories'] );

			$items[] = array(
				't' => (string) $product['title'],
				'k' => (string) ( $cats[0] ?? 'Ürün' ),
				'g' => 'Ürünler',
				'u' => add_query_arg( 'onizleme', rawurlencode( $path ), nwcs_panel_url( $blog_id, '' !== $product_page ? $product_page : $page_key ) ),
			);
			++$product_count;

			if ( $path === $preview_path ) {
				$current = $product['title'] . ' (ürün)';
			}
		}
	}

	// Blog yazilari ve manifestte sayfasi olmayan WordPress sayfalari (yasal
	// metinler, sepet...): onizleme o adreste acilir; metinleri tiklaninca
	// WordPress duzenleyicisine ya da panel alanina gider.
	$manifest_paths = array();
	foreach ( $manifest['pages'] ?? array() as $page ) {
		$manifest_paths[ (string) ( $page['path'] ?? '' ) ] = true;
	}

	switch_to_blog( $blog_id );

	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$skip      = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ) );
	$wp_items  = array();

	foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
		if ( in_array( (int) $post->ID, $skip, true ) ) {
			continue;
		}

		$path = (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH );
		$path = '/' . ltrim( '' !== $home_path && str_starts_with( $path, $home_path ) ? substr( $path, strlen( $home_path ) ) : $path, '/' );

		if ( isset( $manifest_paths[ $path ] ) || '' === nwcs_clean_preview_path( $path ) ) {
			continue;
		}

		$is_post    = 'post' === $post->post_type;
		$wp_items[] = array(
			't'    => get_the_title( $post ) ?: $path,
			'k'    => $is_post ? 'Blog yazısı' : 'Sayfa',
			'g'    => $is_post ? 'Blog yazıları' : 'Diğer sayfalar',
			'path' => $path,
		);
	}

	restore_current_blog();

	// Yazi ve sayfalar panelde en yakin sekmeyle acilir: blog ya da site geneli.
	$post_page  = isset( $manifest['pages']['blog'] ) ? 'blog' : ( isset( $manifest['pages']['global'] ) ? 'global' : $page_key );
	$other_page = isset( $manifest['pages']['global'] ) ? 'global' : $page_key;

	foreach ( $wp_items as $item ) {
		$items[] = array(
			't' => $item['t'],
			'k' => $item['k'],
			'g' => $item['g'],
			'u' => add_query_arg( 'onizleme', rawurlencode( $item['path'] ), nwcs_panel_url( $blog_id, 'Blog yazısı' === $item['k'] ? $post_page : $other_page ) ),
		);

		if ( $item['path'] === $preview_path ) {
			$current = $item['t'] . ( 'Blog yazısı' === $item['k'] ? ' (blog yazısı)' : ' (sayfa)' );
		}
	}

	$placeholder = $product_count
		? sprintf( 'Sayfa, yazı ya da ürün bul (%d ürün)', $product_count )
		: 'Sayfa ya da yazı bul';
	?>
	<div class="nwcs-finder" data-nwcs-finder>
		<label class="nwcs-finder__label" for="nwcs-finder-input">Sayfa bul</label>
		<div class="nwcs-finder__box">
			<svg class="nwcs-finder__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
			<input
				id="nwcs-finder-input"
				class="nwcs-finder__input"
				type="search"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				aria-expanded="false"
				aria-controls="nwcs-finder-list"
				aria-autocomplete="list"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				data-nwcs-finder-input
			/>
			<kbd class="nwcs-finder__key" title="Bu kutuya gitmek için / tuşuna basın">/</kbd>
		</div>

		<?php if ( '' !== $current ) : ?>
			<p class="nwcs-finder__now">Önizlemede: <strong><?php echo esc_html( $current ); ?></strong></p>
		<?php endif; ?>

		<ul id="nwcs-finder-list" class="nwcs-finder__list" role="listbox" aria-label="Bulunan sayfalar" hidden data-nwcs-finder-list></ul>
		<p class="nwcs-finder__status screen-reader-text" role="status" aria-live="polite" data-nwcs-finder-status></p>

		<script type="application/json" data-nwcs-finder-data><?php echo wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>

		<noscript>
			<details class="nwcs-finder__fallback">
				<summary>Tüm sayfalar ve ürünler</summary>
				<ul>
					<?php foreach ( $items as $item ) : ?>
						<li><a href="<?php echo esc_url( $item['u'] ); ?>"><?php echo esc_html( $item['t'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</details>
		</noscript>
	</div>
	<?php
}

/**
 * Panel baglantisi uretir.
 */
function nwcs_panel_url( int $blog_id, string $page_key = '', string $component_key = '' ): string {
	$args = array(
		'page' => NWCS_MENU_SLUG,
		'site' => $blog_id,
	);

	if ( $page_key ) {
		$args['content_page'] = $page_key;
	}

	if ( $component_key ) {
		$args['component'] = $component_key;
	}

	return add_query_arg( $args, network_admin_url( 'admin.php' ) );
}

/**
 * Secili sayfanin sitedeki gercek adresi.
 */
function nwcs_preview_url( int $blog_id, array $manifest, string $page_key ): string {
	$path = $manifest['pages'][ $page_key ]['path'] ?? '/';

	return get_home_url( $blog_id, $path );
}

/**
 * Secili bilesenin duzenleme formu.
 *
 * JavaScript acikken AJAX ile bu HTML alinip yerine konur; kapaliyken sunucu
 * tarafinda ayni cikti basilir ve form admin-post.php'ye gider.
 */
function nwcs_render_editor_form( int $blog_id, array $manifest, string $page_key, string $component_key ): void {
	$component = $manifest['pages'][ $page_key ]['components'][ $component_key ] ?? null;

	if ( ! $component ) {
		echo '<p class="nwcs-empty">Bu bölüm bulunamadı.</p>';

		return;
	}

	$values = nwcs_get_all( $blog_id );
	$media  = nwcs_site_media( $blog_id );

	// Arama ve Paylasim: bos alanda gri olarak otomatik deger gorunsun.
	$placeholders = NWCS_SEO_COMPONENT === $component_key ? nwcs_seo_placeholders( $blog_id, $page_key ) : array();
	?>
	<?php // admin-post.php yalnizca wp-admin kokunde bulunur; ag dizininde yoktur. ?>
	<form class="nwcs-form" method="post" enctype="multipart/form-data"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		data-nwcs-form>

		<input type="hidden" name="action" value="nwcs_save" />
		<input type="hidden" name="site" value="<?php echo esc_attr( (string) $blog_id ); ?>" />
		<input type="hidden" name="content_page" value="<?php echo esc_attr( $page_key ); ?>" />
		<input type="hidden" name="component" value="<?php echo esc_attr( $component_key ); ?>" />
		<?php wp_nonce_field( 'nwcs_save_' . $blog_id . '_' . $page_key . '_' . $component_key ); ?>

		<header class="nwcs-form__head">
			<a class="nwcs-back" href="<?php echo esc_url( nwcs_panel_url( $blog_id, $page_key ) ); ?>" data-nwcs-back>← Bölümler</a>
			<h2 class="nwcs-form__title"><?php echo esc_html( $component['label'] ); ?></h2>
		</header>

		<div class="nwcs-fields">
			<?php
			foreach ( $component['fields'] as $field_key => $definition ) {
				if ( ! empty( $placeholders[ $field_key ] ) ) {
					$definition['placeholder'] = $placeholders[ $field_key ];
				}

				$value = $values[ $page_key ][ $component_key ][ $field_key ] ?? ( $definition['default'] ?? '' );
				nwcs_render_field( $field_key, $definition, $value, $media, $blog_id );
			}
			?>
		</div>

		<footer class="nwcs-actions">
			<button type="submit" class="button button-primary">Kaydet ve Yayınla</button>
			<button type="button" class="button" data-nwcs-discard>Vazgeç</button>
			<span class="nwcs-dirty" data-nwcs-dirty hidden>● kaydedilmedi</span>
			<?php // Not cubugun icinde: ikisi ayri yapisinca not dugmelerin ustune biniyordu. ?>
			<p class="nwcs-actions__note">Kaydettiğiniz anda sitede yayınlanır; taslak tutulmaz.</p>
		</footer>
	</form>
	<?php
}

/**
 * Secili sitenin medya kitapligi (gorseller). Site baglamina gecip geri doner.
 */
function nwcs_site_media( int $blog_id ): array {
	switch_to_blog( $blog_id );

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$media = array();

	foreach ( $attachments as $attachment ) {
		$src = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' );

		$media[ (int) $attachment->ID ] = array(
			'id'    => (int) $attachment->ID,
			'title' => $attachment->post_title ? $attachment->post_title : ( '#' . $attachment->ID ),
			'thumb' => $src ? $src[0] : '',
			'alt'   => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		);
	}

	restore_current_blog();

	return $media;
}
