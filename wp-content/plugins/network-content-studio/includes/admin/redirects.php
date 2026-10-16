<?php
/**
 * Ag yonetimi -> Gelistirici -> Yonlendirmeler: her sitenin eski adres
 * listesi (bkz. includes/redirects.php). Duz metin; bir satir bir kural.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_REDIRECTS_SLUG = 'nwcs-redirects';

add_action( 'network_admin_menu', 'nwcs_register_redirects_menu', 20 );
function nwcs_register_redirects_menu(): void {
	add_submenu_page(
		NWCS_DEV_MENU_SLUG,
		'Yönlendirmeler',
		'Yönlendirmeler',
		NWCS_CAPABILITY,
		NWCS_REDIRECTS_SLUG,
		'nwcs_render_redirects'
	);
}

function nwcs_redirects_url( int $blog_id = 0, array $args = array() ): string {
	return add_query_arg( array_filter( array( 'page' => NWCS_REDIRECTS_SLUG, 'site' => $blog_id ?: null ) + $args ), network_admin_url( 'admin.php' ) );
}

add_action( 'admin_post_nwcs_save_redirects', 'nwcs_save_redirects' );
function nwcs_save_redirects(): void {
	$blog_id = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;

	if ( ! current_user_can( NWCS_CAPABILITY ) || ! check_admin_referer( 'nwcs_save_redirects_' . $blog_id ) || ! isset( nwcs_editable_sites()[ $blog_id ] ) ) {
		wp_die( esc_html__( 'Kaydedilmedi: bu işlem için yetkiniz yok ya da site bulunamadı. Geri dönüp sayfayı yenileyin ve yeniden deneyin.' ) );
	}

	// sanitize_textarea_field %20 gibi kodlamalari siler; eski adreslerde
	// bunlar gercek. Etiketler ve kontrol karakterleri temizlenir, gerisi kalir.
	$text = isset( $_POST['redirects'] ) ? (string) wp_check_invalid_utf8( (string) wp_unslash( $_POST['redirects'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asagida temizlenir.
	$text = (string) preg_replace( '/[^\P{C}\n\t]/u', '', wp_strip_all_tags( str_replace( "\r\n", "\n", $text ) ) );

	switch_to_blog( $blog_id );
	update_option( NWCS_REDIRECTS_OPTION, $text, false );
	restore_current_blog();

	wp_safe_redirect( nwcs_redirects_url( $blog_id, array( 'kaydedildi' => 1 ) ) );
	exit;
}

function nwcs_render_redirects(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$sites   = nwcs_editable_sites();
	$blog_id = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gorunum.

	if ( ! isset( $sites[ $blog_id ] ) ) {
		$blog_id = (int) array_key_first( $sites );
	}

	$text   = $blog_id ? (string) get_blog_option( $blog_id, NWCS_REDIRECTS_OPTION, '' ) : '';
	$parsed = nwcs_redirects_parse( $text );
	$moved  = count( array_filter( $parsed['rules'], static fn( array $rule ): bool => ! $rule['gone'] ) );
	$gone   = count( $parsed['rules'] ) - $moved;
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-seo">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu
					aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Yönlendirmeler</h1>
			</div>

			<nav class="nwcs-bar__sites" aria-label="Site seçici">
				<?php foreach ( $sites as $id => $site ) :
					$count = count( nwcs_redirects_parse( (string) get_blog_option( $id, NWCS_REDIRECTS_OPTION, '' ) )['rules'] );
					?>
					<a class="nwcs-sitepill<?php echo $id === $blog_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( nwcs_redirects_url( $id ) ); ?>">
						<span class="nwcs-sitepill__dot" aria-hidden="true"></span>
						<span class="nwcs-sitepill__name"><?php echo esc_html( $site['label'] ); ?></span>
						<span class="nwcs-sitepill__path"><?php echo esc_html( sprintf( '%d kural', $count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</header>

		<?php if ( $blog_id ) : ?>
			<div class="nwcs-seo__layout nwcs-redirects">
				<section class="nwcs-pool__card">
					<h2 class="nwcs-pool__title">Eski adresler</h2>
					<p class="nwcs-seo__lead">
						Site yeni sunucuya taşındığında eski sitenin adresleri Google'da ve başka sitelerdeki bağlantılarda yaşamaya devam eder.
						Buradaki kurallar o adresleri yeni sayfalara taşır (301) ya da kaldırıldığını bildirir (410). Kural yalnızca
						adres sitede bulunamadığında çalışır; var olan bir sayfayı etkilemez.
					</p>

					<?php if ( isset( $_GET['kaydedildi'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca bilgi. ?>
						<p class="nwcs-badge nwcs-badge--ok">Kaydedildi.</p>
					<?php endif; ?>

					<?php foreach ( $parsed['errors'] as $error ) : ?>
						<p class="nwcs-badge nwcs-badge--warn"><?php echo esc_html( $error ); ?></p>
					<?php endforeach; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="nwcs_save_redirects" />
						<input type="hidden" name="site" value="<?php echo esc_attr( (string) $blog_id ); ?>" />
						<?php wp_nonce_field( 'nwcs_save_redirects_' . $blog_id ); ?>

						<label class="screen-reader-text" for="nwcs-redirects-text">Yönlendirme kuralları</label>
						<textarea id="nwcs-redirects-text" name="redirects" rows="22" spellcheck="false"
							class="nwcs-redirects__text large-text code"><?php echo esc_textarea( $text ); ?></textarea>

						<p><button type="submit" class="button button-primary">Kaydet</button></p>
					</form>
				</section>

				<aside class="nwcs-pool__card nwcs-seo__side">
					<h2 class="nwcs-pool__title">Özet</h2>
					<p><?php echo esc_html( sprintf( '%d yönlendirme, %d kaldırılan adres kuralı.', $moved, $gone ) ); ?></p>

					<h3>Satır biçimi</h3>
					<pre class="nwcs-redirects__help">/eski/yol/          /yeni/yol/
/tag/*              /blog/
/portfolio-item/*   410
# açıklama satırı</pre>
					<ul class="nwcs-redirects__notes">
						<li>Yollar sitenin köküne göredir; alan adı değişse de kurallar geçerli kalır.</li>
						<li><code>*</code> ile biten kural, o önekle başlayan bütün adreslere uyar.</li>
						<li>Hedef başka bir alan adı da olabilir (tam adresle).</li>
						<li>410: sayfa bilerek kaldırıldı; Google dizinden daha hızlı çıkarır. Eski temadan kalan demo sayfalar için.</li>
					</ul>
				</aside>
			</div>
		<?php else : ?>
			<p class="nwcs-empty">Düzenlenebilecek site bulunamadı; geliştiriciye haber verin. (Teknik: alan manifesti olan site yok)</p>
		<?php endif; ?>
	</div>
	<?php
}
