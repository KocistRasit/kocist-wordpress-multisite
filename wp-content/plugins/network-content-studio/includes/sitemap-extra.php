<?php
/**
 * Site haritasi saglayicisi: temanin 'sitemap' => true isaretledigi ek
 * sayfalar (bkz. includes/seo.php, nwcs_seo_extra_sitemap_entries). 'init'
 * sirasinda, site haritalari etkinse yuklenir (WP_Sitemaps_Provider vardir).
 */

defined( 'ABSPATH' ) || exit;

class NWCS_Sitemap_Extra extends WP_Sitemaps_Provider {

	public function __construct() {
		// Saglayici adi yalnizca kucuk harf olabilir (WordPress'in adres kurali).
		$this->name        = 'nwcsextra';
		$this->object_type = 'nwcsextra';
	}

	/**
	 * @param int    $page_num       Sayfa numarasi (1'den baslar).
	 * @param string $object_subtype Kullanilmiyor.
	 * @return array<int, array{loc:string, lastmod?:string}>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$per_page = (int) wp_sitemaps_get_max_urls( $this->object_type );

		// lastmod: temanin bildirdigi son degisiklik (urunlerde havuzdaki tarih).
		return array_slice( nwcs_seo_extra_sitemap_entries(), max( 0, ( (int) $page_num - 1 ) * $per_page ), $per_page );
	}

	/**
	 * @param string $object_subtype Kullanilmiyor.
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$count = count( nwcs_seo_extra_sitemap_entries() );

		return $count ? (int) ceil( $count / (int) wp_sitemaps_get_max_urls( $this->object_type ) ) : 0;
	}
}
