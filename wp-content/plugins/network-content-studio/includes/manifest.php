<?php
/**
 * Alan manifesti kesfi.
 *
 * Multisite'da switch_to_blog() baska sitenin tema kodunu yuklemez. Bu yuzden
 * manifestleri tema dizininden dogrudan dosya sistemi uzerinden okuyoruz:
 * her tema kokunde, hicbir WordPress fonksiyonuna bagimli olmayan, sadece dizi
 * donduren bir content-manifest.php bulunur.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tema slug'ina gore manifesti yukler. Tema aktif olmak zorunda degildir.
 */
function nwcs_manifest_for_theme( string $theme_slug ): array {
	static $cache = array();

	if ( isset( $cache[ $theme_slug ] ) ) {
		return $cache[ $theme_slug ];
	}

	$slug = sanitize_file_name( $theme_slug );
	$file = trailingslashit( WP_CONTENT_DIR ) . 'themes/' . $slug . '/content-manifest.php';

	$manifest = array();
	if ( $slug && file_exists( $file ) ) {
		$loaded = include $file;
		if ( is_array( $loaded ) ) {
			$manifest = nwcs_seo_augment_manifest( $loaded );
		}
	}

	$cache[ $theme_slug ] = $manifest;

	return $manifest;
}

/**
 * Belirtilen sitenin manifesti. Site baglamina gecmeden calisir.
 *
 * Temasi havuzu kategori yerlesimiyle tuketen sitede ('catalog' blogu)
 * yerlesik kategorilerin ve ust basliklarin panel sayfalari eklenir
 * (includes/product-categories.php). Sonuc blog basina istek icinde saklanir;
 * havuz taksonomisi henuz kayitli degilse (init oncesi) saklanmaz.
 */
function nwcs_manifest_for_blog( int $blog_id ): array {
	$cache = &nwcs_manifest_cache();

	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	$theme    = get_blog_option( $blog_id, 'stylesheet', '' );
	$manifest = $theme ? nwcs_manifest_for_theme( (string) $theme ) : array();

	if ( ! empty( $manifest['catalog'] ) && function_exists( 'nwcs_catalog_augment_manifest' ) ) {
		if ( ! taxonomy_exists( NWCS_PRODUCT_TAX ) ) {
			return $manifest;
		}

		$manifest = nwcs_catalog_augment_manifest( $manifest, $blog_id );
	}

	$cache[ $blog_id ] = $manifest;

	return $manifest;
}

/**
 * Blog basina manifest saklama alani (referansla).
 */
function &nwcs_manifest_cache(): array {
	static $cache = array();

	return $cache;
}

/**
 * Kategori yerlesimi degisince uretilen sayfalar yeniden kurulsun.
 */
function nwcs_manifest_reset(): void {
	$cache = &nwcs_manifest_cache();
	$cache = array();
}

/**
 * Aktif sitenin manifesti (tema tarafinda kullanilir).
 */
function nwcs_manifest(): array {
	return nwcs_manifest_for_blog( get_current_blog_id() );
}

/**
 * Manifestten tek bir alan tanimi getirir.
 */
function nwcs_field_def( array $manifest, string $page, string $component, string $field ): ?array {
	return $manifest['pages'][ $page ]['components'][ $component ]['fields'][ $field ] ?? null;
}

/**
 * Manifestteki varsayilan degeri getirir.
 */
function nwcs_field_default( array $manifest, string $page, string $component, string $field ) {
	$def = nwcs_field_def( $manifest, $page, $component, $field );

	return $def['default'] ?? '';
}

/**
 * Manifestte tanimli, siralanabilir bolum anahtarlari.
 */
function nwcs_sortable_sections( array $manifest, string $page = 'home' ): array {
	$sections = $manifest['pages'][ $page ]['sortable_sections'] ?? array();

	return is_array( $sections ) ? $sections : array();
}

/* ====================================================================== *
 * SEO ve GEO alanlari
 *
 * Temalar SEO alani tanimlamaz; eklenti her manifeste iki sey ekler:
 *   - her sayfaya 'seo' bileseni (arama basligi, aciklama, paylasim gorseli)
 *   - gizli 'site_seo' sayfasi (firma bilgisi ve site geneli varsayilanlar)
 *
 * Boylece kaydetme, temizleme, onizleme ve MCP yetenekleri SEO alanlari
 * icin de ek kod olmadan calisir. 'global' sayfasi ortak menu/alt bilgi
 * oldugu icin kendi adresi yoktur; ona SEO bileseni eklenmez.
 *
 * Tema, manifestinde istege bagli olarak sunlari verebilir:
 *   'seo_site_defaults' => [ alan => varsayilan ]  firma bilgisi varsayilanlari
 *   sayfa basina 'seo_source' => [ 'title' => 'bilesen.alan', ... ]
 *     otomatik baslik/aciklama/gorselin hangi alandan gelecegi; 'type' =>
 *     'Product' ve 'properties' => 'bilesen.tekrarli_alan' urun verisi icin.
 * ====================================================================== */

const NWCS_SEO_COMPONENT = 'seo';
const NWCS_SEO_SITE_PAGE = 'site_seo';

/**
 * Manifeste SEO bilesenlerini ekler. Temanin kendi 'seo' bileseni varsa
 * ona dokunulmaz. 'seo' => false isaretli sayfa arama sonucuna cikmadigi
 * icin "Arama ve Paylaşım" bileseni almaz (bkz. nwcs_seo_pages).
 */
function nwcs_seo_augment_manifest( array $manifest ): array {
	if ( empty( $manifest['pages'] ) || ! is_array( $manifest['pages'] ) ) {
		return $manifest;
	}

	foreach ( $manifest['pages'] as $key => $page ) {
		if ( 'global' === $key || isset( $page['components'][ NWCS_SEO_COMPONENT ] ) || false === ( $page['seo'] ?? true ) ) {
			continue;
		}

		$manifest['pages'][ $key ]['components'][ NWCS_SEO_COMPONENT ] = nwcs_seo_page_component();
	}

	$manifest['pages'][ NWCS_SEO_SITE_PAGE ] = nwcs_seo_site_page( (array) ( $manifest['seo_site_defaults'] ?? array() ) );

	return $manifest;
}

/**
 * Her sayfanin "Arama ve Paylasim" bileseni. Bos alan otomatik doldurulur.
 */
function nwcs_seo_page_component(): array {
	return array(
		'label'  => 'Arama ve Paylaşım',
		'fields' => array(
			'title'       => array(
				'label'   => 'Arama başlığı',
				'type'    => 'text',
				'default' => '',
				'count'   => 60,
				'hint'    => 'Google sonucunda mavi görünen başlık. Boş bırakırsanız sayfa başlığı ve site adı kullanılır.',
			),
			'description' => array(
				'label'   => 'Arama açıklaması',
				'type'    => 'textarea',
				'default' => '',
				'count'   => 160,
				'hint'    => 'Başlığın altındaki özet. Boş bırakırsanız sayfanın öne çıkan cümlesi kullanılır.',
			),
			'image'       => array(
				'label'   => 'Paylaşım görseli',
				'type'    => 'image',
				'default' => 0,
				'hint'    => 'Bağlantı WhatsApp, LinkedIn gibi yerlerde paylaşılınca görünen görsel. Boşsa sayfanın ana görseli kullanılır.',
			),
		),
	);
}

/**
 * Site geneli: firma bilgisi ve varsayilanlar. Icerik Studyosu'nun sayfa
 * listesinde gorunmez ('hidden'); SEO ve GEO sekmesinden duzenlenir.
 */
function nwcs_seo_site_page( array $defaults ): array {
	$field = static function ( string $key, string $label, string $type = 'text', string $hint = '' ) use ( $defaults ): array {
		$definition = array(
			'label'   => $label,
			'type'    => $type,
			'default' => $defaults[ $key ] ?? ( 'image' === $type ? 0 : '' ),
		);

		if ( $hint ) {
			$definition['hint'] = $hint;
		}

		return $definition;
	};

	return array(
		'label'      => 'Arama ve Paylaşım: Site Geneli',
		'path'       => '/',
		'hidden'     => true,
		'components' => array(
			'org'      => array(
				'label'  => 'Firma Bilgisi',
				'fields' => array(
					'name'        => $field( 'name', 'Firma adı', 'text', 'Aramalarda görünen kısa ad. Örn. İstanbul Paletçi' ),
					'legal_name'  => $field( 'legal_name', 'Resmî unvan', 'text', 'Ağdaki bütün sitelerde birebir aynı yazılmalı.' ),
					'description' => $field( 'description', 'Firma tanımı', 'textarea', 'Bir iki cümle: ne üretiyorsunuz, nerede, kime. Yapay zekâ aramaları bu cümleyi alıntılar.' ),
					'logo'        => $field( 'logo', 'Logo', 'image' ),
					'phone'       => $field( 'phone', 'Telefon', 'text', 'Uluslararası biçimde: +90 212 648 10 90' ),
					'mobile'      => $field( 'mobile', 'Cep ve WhatsApp', 'text', 'Uluslararası biçimde: +90 532 374 98 32. Boşsa sitenin cep hattı (Toplu Güncelleme) yazılır.' ),
					'email'       => $field( 'email', 'E-posta' ),
					'street'      => $field( 'street', 'Açık adres', 'text', 'Mahalle, cadde, numara' ),
					'district'    => $field( 'district', 'İlçe' ),
					'city'        => $field( 'city', 'İl' ),
					'postal_code' => $field( 'postal_code', 'Posta kodu' ),
					'country'     => $field( 'country', 'Ülke kodu', 'text', 'İki harf: TR' ),
					'latitude'    => $field( 'latitude', 'Enlem', 'text', 'Google Haritalar’da konuma sağ tıklayınca çıkan ilk sayı. Örn. 41.0781' ),
					'longitude'   => $field( 'longitude', 'Boylam', 'text', 'İkinci sayı. Örn. 28.7836' ),
					'same_as'     => array(
						'label'   => 'Firmanın diğer adresleri',
						'type'    => 'repeater',
						'max'     => 12,
						'hint'    => 'Yalnızca bu firmanın kendi hesapları: sosyal medya, Google İşletme, harita kaydı. Kardeş siteler buraya değil, "Bağlı olduğu grup" alanına.',
						'fields'  => array(
							'url' => array( 'label' => 'Adres', 'type' => 'url' ),
						),
						'default' => $defaults['same_as'] ?? array(),
					),
					'parent_name' => $field( 'parent_name', 'Bağlı olduğu grup', 'text', 'Siteler aynı grubun parçasıysa grubun adı. Örn. Koçist Orman Ürünleri' ),
					'parent_url'  => $field( 'parent_url', 'Grubun web adresi', 'url', 'Örn. https://www.kocist.com.tr' ),
				),
			),
			'defaults' => array(
				'label'  => 'Varsayılanlar',
				'fields' => array(
					'share_image' => $field( 'share_image', 'Varsayılan paylaşım görseli', 'image', 'Kendi görseli olmayan sayfalar paylaşılınca bu görünür. En iyi boyut 1200 × 630.' ),
				),
			),
		),
	);
}

/**
 * Icerik Studyosu'nda sekme olarak gosterilecek sayfalar (gizliler haric).
 */
function nwcs_visible_pages( array $manifest ): array {
	return array_filter(
		$manifest['pages'] ?? array(),
		static fn( $page ): bool => empty( $page['hidden'] )
	);
}
