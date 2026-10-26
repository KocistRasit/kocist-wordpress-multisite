<?php
/**
 * Panel icindeki canli onizleme modu.
 *
 * Site, panelden gelen iframe icinde `?nwcs_preview=1` ile acilir. Mod yalnizca
 * yetkili kullanicilar icin acilir; ziyaretciye hicbir isaret veya admin araci
 * sizmaz. Tema sablonlari nwcs_edit_attr() ile alan isaretlerini basar.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Onizleme modunda miyiz?
 *
 * Yetki kontrolu her istekte yapilir; parametrenin tek basina olmasi yetmez.
 */
function nwcs_is_preview(): bool {
	static $is_preview = null;

	if ( null !== $is_preview ) {
		return $is_preview;
	}

	$param = isset( $_GET['nwcs_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['nwcs_preview'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- salt gorunum modu, yazma yok.

	// Kendi alan adina bagli sitede panelin oturum cerezi onizlemeye ulasmaz
	// (baska alan adi, ucuncu taraf cerez): yetkiyi panelin imzali anahtari tasir.
	$value = '' !== $param
		&& ( nwcs_preview_token_valid( $param ) || ( is_user_logged_in() && current_user_can( 'manage_network_options' ) ) );

	// Kullanici 'init' oncesinde henuz belirlenmemis olabilir; o asamada
	// sonucu onbellege almiyoruz ki yanlis deger sabitlenmesin.
	if ( did_action( 'init' ) ) {
		$is_preview = $value;
	}

	return $value;
}

/**
 * Onizleme anahtari: "<kullanici>.<bitis>.<imza>". Imza site, kullanici ve
 * bitis zamanini wp-config tuzlariyla baglar; baska sitede, suresi gecince
 * ya da yetkisi alinmis kullaniciyla gecersizdir. Anahtar yalnizca alan
 * isaretlerini acar; sayfa icerigi zaten herkese acik.
 */
function nwcs_preview_token( int $blog_id ): string {
	$user    = get_current_user_id();
	$expires = time() + 12 * HOUR_IN_SECONDS;

	return $user . '.' . $expires . '.' . hash_hmac( 'sha256', $blog_id . '|' . $user . '|' . $expires, wp_salt( 'auth' ) );
}

function nwcs_preview_token_valid( string $token ): bool {
	$parts = explode( '.', $token );

	if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) || (int) $parts[1] < time() ) {
		return false;
	}

	$expected = hash_hmac( 'sha256', get_current_blog_id() . '|' . $parts[0] . '|' . $parts[1], wp_salt( 'auth' ) );

	return hash_equals( $expected, $parts[2] ) && is_super_admin( (int) $parts[0] );
}

// Admin cubugu 'init' sirasinda karara baglanir; filtre bu yuzden erken eklenir.
add_filter( 'show_admin_bar', 'nwcs_preview_hide_admin_bar', 99 );
function nwcs_preview_hide_admin_bar( $show ) {
	return nwcs_is_preview() ? false : $show;
}

// Erken: urun ve kategori sayfalari template_redirect'te (5, 6) cizilip cikiyor.
add_action( 'template_redirect', 'nwcs_preview_setup', 1 );
function nwcs_preview_setup(): void {
	if ( ! nwcs_is_preview() ) {
		return;
	}

	// Anahtarli adres onbellege ve arama motoruna girmesin. LiteSpeed Cache
	// nocache_headers()'a bakmaz; sabit ve kendi basligi gerekir.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	nocache_headers();
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	add_filter( 'body_class', 'nwcs_preview_body_class' );
	add_action( 'wp_enqueue_scripts', 'nwcs_preview_assets', 20 );
}

function nwcs_preview_body_class( array $classes ): array {
	$classes[] = 'nwcs-preview-mode';

	return $classes;
}

function nwcs_preview_assets(): void {
	wp_enqueue_style( 'nwcs-preview', NWCS_URL . 'assets/preview.css', array(), NWCS_VERSION );
	wp_enqueue_script( 'nwcs-preview', NWCS_URL . 'assets/preview.js', array(), NWCS_VERSION, true );
	wp_localize_script(
		'nwcs-preview',
		'nwcsPreview',
		array(
			'blogId' => get_current_blog_id(),
			'origin' => untrailingslashit( network_site_url() ),
			'labels' => nwcs_preview_labels(),
			// Kaynak baglantilari (nwcs_source_attr) yalnizca bu adreslere acilir.
			'sources' => array_values( array_unique( array( network_admin_url(), admin_url() ) ) ),
		)
	);
}

/**
 * Isaretlenen alanlarin Turkce etiketleri (ipucu balonu icin).
 */
function nwcs_preview_labels(): array {
	$manifest = nwcs_manifest();
	$labels   = array();

	foreach ( $manifest['pages'] ?? array() as $page_key => $page ) {
		foreach ( $page['components'] as $component_key => $component ) {
			$labels[ $page_key . '.' . $component_key ] = $component['label'];

			foreach ( $component['fields'] as $field_key => $definition ) {
				$labels[ $page_key . '.' . $component_key . '.' . $field_key ] = $definition['label'];

				foreach ( $definition['fields'] ?? array() as $sub_key => $sub_def ) {
					$labels[ $page_key . '.' . $component_key . '.' . $field_key . '.' . $sub_key ] = $sub_def['label'];
				}
			}
		}
	}

	return $labels;
}

/**
 * Tema sablonlarinda kullanilir: duzenlenebilir alani isaretler.
 *
 * Ornek: <h1 <?php nwcs_edit_attr( 'home', 'hero', 'title' ); ?>>
 *
 * Tekrarli satirlarda $row ve $sub verilir:
 *   nwcs_edit_attr( 'home', 'products', 'items', $index, 'title' )
 */
function nwcs_edit_attr( string $page, string $component, string $field = '', ?int $row = null, string $sub = '' ): void {
	if ( ! nwcs_is_preview() ) {
		return;
	}

	$parts = array( $page, $component );

	if ( '' !== $field ) {
		$parts[] = $field;
	}

	$target = implode( '.', $parts );

	if ( null !== $row && '' !== $sub ) {
		$target .= '.' . $sub;
	}

	printf(
		' data-nwcs-edit="%s"%s data-nwcs-component="%s"',
		esc_attr( $target ),
		null !== $row ? ' data-nwcs-row="' . esc_attr( (string) $row ) . '"' : '',
		esc_attr( $page . '.' . $component )
	);
}

/**
 * Icerigi bu sitenin panelinde degil baska bir yerde duzenlenen ogeyi isaretler.
 * Onizlemede tiklaninca kaynak yeni sekmede acilir; balon neden burada
 * duzenlenmedigini soyler.
 *
 * $kind: 'product' (Urun Havuzu'ndaki urunun ozelligi, tum sitelerde ortak),
 *        'post' (blog yazisi) ya da 'admin' (diger yonetim sayfalari).
 *
 * Ornek: <h1 <?php nwcs_source_attr( 'product', nwcs_pool_url( array( 'urun' => $id ) ), 'Ürün adı' ); ?>>
 */
function nwcs_source_attr( string $kind, string $url, string $label ): void {
	if ( ! nwcs_is_preview() || '' === $url ) {
		return;
	}

	printf(
		' data-nwcs-source="%s" data-nwcs-source-url="%s" data-nwcs-source-label="%s"',
		esc_attr( $kind ),
		esc_url( $url ),
		esc_attr( $label )
	);
}

/**
 * Urun havuzundaki bir urunun alani: "Ürüne ait özellik".
 *
 * Ornek: <span <?php nwcs_product_attr( $product['id'], 'Fiyat' ); ?>>
 */
function nwcs_product_attr( int $product_id, string $label ): void {
	if ( ! nwcs_is_preview() || ! function_exists( 'nwcs_pool_url' ) ) {
		return;
	}

	nwcs_source_attr( 'product', nwcs_pool_url( array( 'urun' => $product_id ) ), $label );
}

/**
 * Blog yazisi (baslik, ozet, gorsel): yazinin duzenleme ekrani.
 */
function nwcs_post_attr( int $post_id, string $label = 'Blog yazısı' ): void {
	if ( ! nwcs_is_preview() ) {
		return;
	}

	nwcs_source_attr( 'post', admin_url( 'post.php?post=' . $post_id . '&action=edit' ), $label );
}
