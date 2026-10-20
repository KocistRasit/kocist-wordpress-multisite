<?php
/**
 * Plugin Name:       Network Content Studio
 * Description:       Ag genelinde tek icerik veri katmani ve alan manifesti. Icerik Studyosu paneli, canli onizleme, merkezi urun havuzu, SEO ve GEO, MCP yetenekleri.
 * Version:           0.27.1
 * Network:           true
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       nwcs
 */

defined( 'ABSPATH' ) || exit;

define( 'NWCS_VERSION', '0.27.1' );
define( 'NWCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NWCS_URL', plugin_dir_url( __FILE__ ) );

require_once NWCS_DIR . 'includes/manifest.php';
require_once NWCS_DIR . 'includes/store.php';
require_once NWCS_DIR . 'includes/seo.php';
require_once NWCS_DIR . 'includes/icons.php';
require_once NWCS_DIR . 'includes/preview.php';
require_once NWCS_DIR . 'includes/search.php';
require_once NWCS_DIR . 'includes/products.php';
require_once NWCS_DIR . 'includes/product-video.php';
require_once NWCS_DIR . 'includes/product-headings.php';
require_once NWCS_DIR . 'includes/product-categories.php';
require_once NWCS_DIR . 'includes/color-groups.php';
require_once NWCS_DIR . 'includes/category-faq.php';
require_once NWCS_DIR . 'includes/cache-purge.php';
require_once NWCS_DIR . 'includes/forms.php';
require_once NWCS_DIR . 'includes/smtp.php';
require_once NWCS_DIR . 'includes/redirects.php';
require_once NWCS_DIR . 'includes/comments.php';
require_once NWCS_DIR . 'includes/placeholders.php';
require_once NWCS_DIR . 'includes/images.php';
require_once NWCS_DIR . 'includes/photos.php';
require_once NWCS_DIR . 'includes/xlsx-reader.php';
require_once NWCS_DIR . 'includes/xlsx-writer.php';

// Panel dosyalari yalnizca yonetimde degil, Abilities API cagrilari icin de
// gerekli (nwcs_editable_sites, NWCS_CAPABILITY).
require_once NWCS_DIR . 'includes/admin/panel.php';
require_once NWCS_DIR . 'includes/admin/fields.php';
require_once NWCS_DIR . 'includes/admin/save.php';
require_once NWCS_DIR . 'includes/admin/pool.php';
require_once NWCS_DIR . 'includes/admin/product-tables.php';
require_once NWCS_DIR . 'includes/admin/pool-video.php';
require_once NWCS_DIR . 'includes/admin/pool-package.php';
require_once NWCS_DIR . 'includes/admin/sync.php';
require_once NWCS_DIR . 'includes/admin/import.php';
require_once NWCS_DIR . 'includes/admin/history.php';
require_once NWCS_DIR . 'includes/admin/pool-categories.php';
require_once NWCS_DIR . 'includes/admin/color-groups.php';
require_once NWCS_DIR . 'includes/admin/color-palette.php';
require_once NWCS_DIR . 'includes/admin/category-faq.php';
require_once NWCS_DIR . 'includes/admin/pool-photos.php';
require_once NWCS_DIR . 'includes/admin/pool-headings.php';
require_once NWCS_DIR . 'includes/admin/pool-descriptions.php';
require_once NWCS_DIR . 'includes/admin/overrides.php';
require_once NWCS_DIR . 'includes/admin/media.php';
require_once NWCS_DIR . 'includes/admin/media-video.php';
require_once NWCS_DIR . 'includes/admin/media-alt.php';
require_once NWCS_DIR . 'includes/admin/seo.php';
require_once NWCS_DIR . 'includes/admin/redirects.php';
require_once NWCS_DIR . 'includes/admin/bulk-content.php';
require_once NWCS_DIR . 'includes/admin/bulk-update.php';
require_once NWCS_DIR . 'includes/admin/placeholders.php';
require_once NWCS_DIR . 'includes/admin/mail-test.php';
require_once NWCS_DIR . 'includes/admin/webp-convert.php';

// WordPress 6.9+ cekirdeginde Abilities API bulunur; yoksa sessizce atlanir.
if ( function_exists( 'wp_register_ability' ) ) {
	require_once NWCS_DIR . 'includes/abilities.php';
	// MCP Adapter eklentisi etkinse yetenekleri MCP aracina cevirir.
	require_once NWCS_DIR . 'includes/mcp-server.php';
}
