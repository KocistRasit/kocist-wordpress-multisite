<?php
/**
 * Urun XML beslemesi: Ailways asistaninin urun aktaricisi icin site basina liste.
 *
 * Adres: <site>/urunler.xml (yerelde /kocist/urunler.xml, canlida
 * https://<alan-adi>/urunler.xml). Urun adresleri siteye gore degistigi icin
 * her site kendi listesini verir. Yalnizca katalog sitelerinde (temasinin
 * manifestinde 'catalog' ve 'site_key' olan: Kocist, WOOD KOCIST;
 * nwcs_catalog_site_key); digerlerinde istek WordPress'e kalir (404).
 *
 * Yol parse_request'te tam esitlikle yakalanir: sitenin ev yolu + "urunler.xml"
 * (sorgu metni yok sayilir). Yeniden yazma kurali yok (kalici baglanti
 * yenilemesi gerekmez); sorgu ve kanonik yonlendirmeden once cikildigi icin
 * WordPress yonlendirme ya da sona egik cizgi eklemez.
 *
 * Satirlar nwcs_site_products(): secim, gizli urunler, site basina ad ve adres
 * uygulanmis. Renk gruplari sadelesmez: her renk ayri <urun>. Alanlar:
 *   ad                sitedeki ad (site istisnasi dahil);
 *   kod               urun kodu;
 *   fiyat             duz tutar "10250.00" (nokta, iki hane); fiyatsiz,
 *                     "...'den baslayan", birden cok tutarli, "$" ya da
 *                     okunamayan fiyatta bos = teklif. 0 yazilmaz;
 *   indirimsiz_fiyat  her zaman bos (sistemde indirim yok);
 *   gorsel            ilk galeri gorseli, tam boy; ek_gorseller kalanlar
 *                     galeri sirasiyla "|" ile; gorseller hepsi ic ice <url> (ust duzeydeki <gorsel> ile karismasin);
 *   link              urunun bu sitedeki sayfasi (site basina adres dahil);
 *   kategori          "Ust baslik > Kategori" (bu sitedeki yeri); yeri yoksa
 *                     havuzdaki ilk kategori adi;
 *   aciklama          sitedeki kisa aciklama; bossa uzun aciklamanin ilk ~300
 *                     karakteri (kelime sinirinda);
 *   kdv_orani         simdilik bos (kullanici karari);
 *   renk              renk grubundaysa renk adi.
 * Metinler duz yazi (HTML yok), ENT_XML1 ile kacirilir; XML 1.0'da yasak
 * kontrol karakterleri atilir.
 *
 * Gorseller havuz sitesinin medyasindan, tam boy ('full'). Adres o sitenin
 * sayfalarindaki gibi kurulur (istek o sitede: canlida sitenin alan adi).
 * Yer tutucu (seed) gorsel yazilmaz (nwcs_product_feed_is_placeholder).
 *
 * Basliklar: application/xml; charset=UTF-8, X-Robots-Tag: noindex,
 * Cache-Control: public, max-age=900. Site haritasina ve llms.txt'ye girmez.
 * Ayri cikti onbellegi yok: liste onbellekli havuzdan kurulur, gorsel kayitlari
 * tek seferde okunur; besleme panel degisikliginden sonra hic eskimez.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PRODUCT_FEED_FILE   = 'urunler.xml';
const NWCS_PRODUCT_FEED_MAXAGE = 900; // Tarayici/aktarici onbellegi (saniye).
const NWCS_PRODUCT_FEED_TEXT   = 300; // Uzun aciklamadan alinan en fazla karakter.

/**
 * Sitede besleme var mi? Katalog siteleri (canli aramadaki gibi tema bazli
 * degil, manifestteki site anahtarina gore).
 */
function nwcs_product_feed_enabled( ?int $blog_id = null ): bool {
	return '' !== nwcs_catalog_site_key( $blog_id ?? get_current_blog_id() );
}

/**
 * Beslemenin yolu: sitenin ev yolu + urunler.xml ("/kocist/urunler.xml", canlida "/urunler.xml").
 */
function nwcs_product_feed_path(): string {
	return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) . NWCS_PRODUCT_FEED_FILE;
}

add_action( 'parse_request', 'nwcs_product_feed_maybe_serve', 1 );
function nwcs_product_feed_maybe_serve(): void {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- yalnizca sabit yolla karsilastirilir.
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

	if ( nwcs_product_feed_path() !== $path || ! nwcs_product_feed_enabled() ) {
		return;
	}

	$xml = nwcs_product_feed_xml();

	status_header( 200 );
	header_remove( 'X-Powered-By' );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=' . NWCS_PRODUCT_FEED_MAXAGE );

	if ( 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		echo $xml; // phpcs:ignore WordPress.Security.EscapingOutput -- metinler nwcs_product_feed_x ile kacirildi.
	}

	exit;
}

/**
 * Beslemenin tamami (sitenin baglaminda cagrilir: gorsel adresleri ve tema).
 */
function nwcs_product_feed_xml(): string {
	$rows = nwcs_product_feed_rows();
	$x    = 'nwcs_product_feed_x';
	$site = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$out .= sprintf( '<urunler site="%s" guncelleme="%s" adet="%d">', $x( $site ), $x( wp_date( 'c' ) ), count( $rows ) ) . "\n";

	foreach ( $rows as $row ) {
		$out .= "  <urun>\n";

		foreach ( $row as $tag => $value ) {
			if ( 'gorseller' === $tag ) {
				$list = '';

				foreach ( $value as $url ) {
					$list .= '      <url>' . $x( $url ) . "</url>\n";
				}

				$out .= '' !== $list ? "    <gorseller>\n" . $list . "    </gorseller>\n" : "    <gorseller></gorseller>\n";
				continue;
			}

			$out .= sprintf( "    <%1\$s>%2\$s</%1\$s>\n", $tag, $x( (string) $value ) );
		}

		$out .= "  </urun>\n";
	}

	return $out . "</urunler>\n";
}

/**
 * Beslemenin satirlari, sitedeki sirayla. Her satir: ad, kod, fiyat,
 * indirimsiz_fiyat, gorsel, ek_gorseller, gorseller (dizi), link, kategori,
 * aciklama, kdv_orani, renk.
 *
 * @return array<int, array<string, string|string[]>>
 */
function nwcs_product_feed_rows( ?int $blog_id = null ): array {
	$blog_id  = $blog_id ?? get_current_blog_id();
	$products = nwcs_site_products( $blog_id );
	$images   = nwcs_product_feed_images( $products );
	$rows     = array();

	foreach ( $products as $product ) {
		$id   = (int) $product['id'];
		$list = $images[ $id ] ?? array();
		$text = nwcs_product_feed_text( (string) ( $product['short'] ?? '' ) );

		if ( '' === $text ) {
			$text = nwcs_product_feed_text( (string) ( $product['body'] ?? '' ), NWCS_PRODUCT_FEED_TEXT );
		}

		$rows[] = array(
			'ad'               => nwcs_product_feed_text( (string) ( $product['title'] ?? '' ) ),
			'kod'              => nwcs_product_feed_text( (string) ( $product['code'] ?? '' ) ),
			'fiyat'            => ! empty( $product['has_price'] ) ? nwcs_product_feed_price( (string) $product['price'] ) : '',
			'indirimsiz_fiyat' => '',
			'gorsel'           => (string) ( $list[0] ?? '' ),
			'ek_gorseller'     => implode( '|', array_slice( $list, 1 ) ),
			'gorseller'        => $list,
			'link'             => nwcs_product_has_page( $product, $blog_id ) ? esc_url_raw( (string) $product['url'] ) : '',
			'kategori'         => nwcs_product_feed_category( $product, $blog_id ),
			'aciklama'         => $text,
			'kdv_orani'        => '',
			'renk'             => nwcs_product_feed_color( $product ),
		);
	}

	return $rows;
}

/**
 * Fiyat metninden aktariciya yazilacak tutar: "10250.00"; duz tutar degilse bos.
 *
 * Kural Kocist'in JSON-LD Offer fiyatiyla ayni (kocist_price_number; eklentinin
 * seo.php'si yalnizca o sayiyi alir): "450 TL", "3.500 ₺", "48.500,00 ₺",
 * "2.80 TL". Aralik/baslangic ("1.250 TL'den başlayan"), birden cok tutar,
 * birimsiz sayi ve 0 bos kalir. "$" iceren (USD) fiyat da bos: beslemede para
 * birimi alani yok, aktarici TL sanardi.
 */
function nwcs_product_feed_price( string $price ): string {
	if ( ! preg_match( '/^\s*([0-9][0-9.,]*)\s*(?:TL|₺)\s*$/iu', $price, $match ) ) {
		return '';
	}

	$number = $match[1];

	if ( preg_match( '/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $number ) ) {
		// Binlik noktali: "3.500", "48.500,00".
		$number = str_replace( array( '.', ',' ), array( '', '.' ), $number );
	} elseif ( preg_match( '/^\d+(?:,\d{1,2})?$/', $number ) ) {
		$number = str_replace( ',', '.', $number );
	} elseif ( ! preg_match( '/^\d+\.\d{1,2}$/', $number ) ) {
		return '';
	}

	return (float) $number > 0 ? number_format( (float) $number, 2, '.', '' ) : '';
}

/**
 * Duz yazi: etiketler atilir (etiket olmayan "<" korunur: "<20 mm"), HTML
 * karakterleri cozulur, bosluklar toplanir. $max verilirse kelime sinirinda
 * kesilir ("…"). Blok sonlari bosluk olur: "<p>a</p><p>b</p>" -> "a b".
 */
function nwcs_product_feed_text( string $text, int $max = PHP_INT_MAX ): string {
	$text = (string) preg_replace( '#<(?:br|/p|/div|/li|/h[1-6]|/t[dhr])\b[^>]*>#i', '$0 ', $text );

	return nwcs_live_search_excerpt( wp_pre_kses_less_than( $text ), $max );
}

/**
 * XML metni: yasak kontrol karakterleri atilir, ENT_XML1 ile kacirilir.
 */
function nwcs_product_feed_x( string $text ): string {
	$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', wp_check_invalid_utf8( $text ) );

	return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Urunlerin gorselleri, galeri sirasiyla (site istisnasi gorseli once): tam boy
 * mutlak adresler. Havuz sitesine bir kez gecilir; ek kayitlari ve meta'lari
 * tek seferde okunur. Yer tutucu ve dosyasi olmayan gorsel atlanir.
 *
 * @param array<int, array> $products
 * @return array<int, string[]> urun kimligi => adresler
 */
function nwcs_product_feed_images( array $products ): array {
	$ids = array();

	foreach ( $products as $product ) {
		foreach ( (array) ( $product['images'] ?? array() ) as $image ) {
			if ( (int) ( $image['id'] ?? 0 ) > 0 ) {
				$ids[] = (int) $image['id'];
			}
		}
	}

	if ( ! $ids ) {
		return array();
	}

	// Tema demo dosyalari sitenin temasinda aranir: havuza gecmeden once.
	$demo_dir = trailingslashit( get_stylesheet_directory() ) . 'assets/img/';
	$switched = is_multisite() && (int) nwcs_pool_blog_id() !== get_current_blog_id();

	if ( $switched ) {
		switch_to_blog( (int) nwcs_pool_blog_id() );
	}

	_prime_post_caches( array_values( array_unique( $ids ) ), false, true );

	$out = array();

	foreach ( $products as $product ) {
		$id   = (int) $product['id'];
		$list = array();

		foreach ( (array) ( $product['images'] ?? array() ) as $image ) {
			$src = (int) ( $image['id'] ?? 0 ) > 0 ? wp_get_attachment_image_src( (int) $image['id'], 'full' ) : false;
			$url = $src ? esc_url_raw( (string) $src[0] ) : '';

			if ( '' === $url || nwcs_product_feed_is_placeholder( (string) ( $image['alt'] ?? '' ), $url, $demo_dir ) || in_array( $url, $list, true ) ) {
				continue;
			}

			$list[] = $url;
		}

		$out[ $id ] = $list;
	}

	if ( $switched ) {
		restore_current_blog();
	}

	return $out;
}

/**
 * Yer tutucu (seed) gorsel mi? Kocist'in kurali (kocist_is_placeholder_image):
 * alt metinde "yer tutucu" ya da "örnek", ya da dosya adi temadaki bir demo
 * fotografin adi (assets/img); ek olarak seed dosyalari (ornek-urun-*).
 */
function nwcs_product_feed_is_placeholder( string $alt, string $url, string $demo_dir ): bool {
	if ( false !== mb_stripos( $alt, 'yer tutucu' ) || false !== mb_stripos( $alt, 'örnek' ) ) {
		return true;
	}

	$name = (string) preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

	return '' !== $name && ( str_starts_with( $name, 'ornek-urun-' ) || file_exists( $demo_dir . $name ) );
}

/**
 * Urunun bu sitedeki yeri: "Ust baslik > Kategori" (nwcs_product_place);
 * yalnizca ust basligin kendi kategorisindeyse ust baslik; yeri yoksa havuzdaki
 * ilk kategori adi.
 */
function nwcs_product_feed_category( array $product, int $blog_id ): string {
	$place  = nwcs_product_place( $product, $blog_id );
	$tree   = nwcs_site_category_tree( $blog_id );
	$parent = null !== $place['parent'] ? (string) ( $tree[ $place['parent'] ]['label'] ?? '' ) : '';
	$child  = null !== $place['child'] ? (string) ( nwcs_pool_categories()[ $place['child'] ]['name'] ?? '' ) : '';

	if ( '' === $parent && '' === $child ) {
		$names = array_values( (array) ( $product['categories'] ?? array() ) );
		$child = (string) ( $names[0] ?? '' );
	}

	return nwcs_product_feed_text( implode( ' > ', array_filter( array( $parent, $child ), 'strlen' ) ) );
}

/**
 * Renk grubundaki urunun renk adi; grupta degilse (ya da grubun kategorisinden
 * cikmissa) bos.
 */
function nwcs_product_feed_color( array $product ): string {
	$group = nwcs_color_group_of( (int) $product['id'] );

	if ( ! $group || ! isset( $product['categories'][ $group['slug'] ] ) ) {
		return '';
	}

	return nwcs_product_feed_text( (string) ( $group['members'][ $group['position'] ]['color_name'] ?? '' ) );
}
