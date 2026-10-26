<?php
/**
 * Ag Yonetimi -> Gelistirici -> Gorsel Yer Tutucu: site basina ac/kapa
 * (bkz. includes/placeholders.php). Fotograflar silinmez; kapatinca geri gelir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PLACEHOLDER_SLUG = 'nwcs-placeholders';

add_action( 'network_admin_menu', 'nwcs_register_placeholder_menu', 31 );
function nwcs_register_placeholder_menu(): void {
	add_submenu_page( NWCS_DEV_MENU_SLUG, 'Görsel Yer Tutucu', 'Görsel Yer Tutucu', NWCS_CAPABILITY, NWCS_PLACEHOLDER_SLUG, 'nwcs_render_placeholders' );
}

add_action( 'admin_post_nwcs_placeholders_save', 'nwcs_placeholders_save' );
function nwcs_placeholders_save(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) || ! check_admin_referer( 'nwcs_placeholders_save' ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$on = array_map( 'absint', (array) ( $_POST['sites'] ?? array() ) );

	foreach ( nwcs_placeholder_sites() as $site ) {
		$id  = (int) $site->blog_id;
		$new = in_array( $id, $on, true ) ? '1' : '0';

		if ( get_blog_option( $id, NWCS_PLACEHOLDER_OPTION, '0' ) === $new ) {
			continue;
		}

		update_blog_option( $id, NWCS_PLACEHOLDER_OPTION, $new );

		// Sayfa onbellegi eklentisi varsa (LiteSpeed Cache) degisen site temizlenir (includes/cache-purge.php).
		nwcs_cache_mark_blog_dirty( $id );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => NWCS_PLACEHOLDER_SLUG, 'kaydedildi' => 1 ), network_admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Etkin siteler (arsivlenmis ve silinmis haric).
 */
function nwcs_placeholder_sites(): array {
	return get_sites( array( 'number' => 100, 'deleted' => 0, 'archived' => 0 ) );
}

function nwcs_render_placeholders(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-seo">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Görsel Yer Tutucu</h1>
			</div>
		</header>

		<section class="nwcs-pool__card">
			<p class="nwcs-seo__lead">
				İşaretli sitelerde sayfadaki her fotoğrafın yerinde aynı boyutta “Görsel gelecek” kutusu görünür; her kutunun deseni farklıdır.
				Fotoğraflar silinmez: işareti kaldırınca hepsi geri gelir. Logolar ve küçük simgeler değişmez. Paylaşım önizlemesi görseli değişmez.
			</p>

			<?php if ( isset( $_GET['kaydedildi'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca bilgi. ?>
				<p class="nwcs-badge nwcs-badge--ok">Kaydedildi. Değişiklik sitede hemen görünmüyorsa site önbelleğini bir kez temizleyin (LiteSpeed).</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nwcs_placeholders_save" />
				<?php wp_nonce_field( 'nwcs_placeholders_save' ); ?>
				<ul style="list-style:none;margin:1rem 0;padding:0;display:grid;gap:.5rem">
					<?php foreach ( nwcs_placeholder_sites() as $site ) :
						$id = (int) $site->blog_id;
						?>
						<li>
							<label>
								<input type="checkbox" name="sites[]" value="<?php echo esc_attr( (string) $id ); ?>" <?php checked( nwcs_placeholders_enabled( $id ) ); ?> />
								<strong><?php echo esc_html( get_blog_option( $id, 'blogname' ) ); ?></strong>
								<span class="description"><?php echo esc_html( untrailingslashit( get_home_url( $id, '/' ) ) ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<button type="button" class="button" onclick="this.form.querySelectorAll('input[type=checkbox]').forEach(function(c){c.checked=true})">Tümünü seç</button>
					<button type="submit" class="button button-primary">Kaydet</button>
				</p>
			</form>
		</section>
	</div>
	<?php
}
