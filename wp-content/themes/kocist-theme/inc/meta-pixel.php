<?php
/**
 * Meta (Facebook/Instagram) reklam pikseli.
 *
 * Piksel: Koçist Grup işletmesindeki "Koçist Teknoloji'nin Pikseli" (veri kümesi
 * 1670790346292450).
 *
 * Çerez onayı: piksel betiği (fbevents.js) yalnızca ziyaretçi çerez çubuğunda
 * "Kabul et" demişse yüklenir (localStorage k-cerez = kabul). Onay yoksa Meta'ya
 * hiçbir istek gitmez. Onay aynı sayfada verilirse footer.php'deki çubuk
 * window.kocistPixelLoad() çağırır.
 *
 * Olaylar:
 * - PageView: her sayfa.
 * - ViewContent: ürün detay sayfası.
 * - Contact: WhatsApp (wa.me, api.whatsapp.com), telefon (tel:) ve e-posta
 *   (mailto:) bağlantısına tıklama.
 * - Lead: teklif formu ya da teklif sepeti başarıyla gönderilince (sunucu
 *   gönderimi doğruladıktan sonra, ?kc= tek kullanımlık durumundan).
 *
 * Kişisel veri (ad, telefon, e-posta) piksele gönderilmez.
 */

const KOCIST_META_PIXEL_ID = '1670790346292450';

add_action( 'wp_head', 'kocist_meta_pixel_head', 100 );
function kocist_meta_pixel_head(): void {
	if ( function_exists( 'nwcs_is_preview' ) && nwcs_is_preview() ) {
		return;
	}

	$view_content = '';
	if ( get_query_var( 'nwcs_product' ) ) {
		$view_content = wp_strip_all_tags( wp_get_document_title() );
	}
	?>
<script>
(function () {
	var PIXEL = '<?php echo esc_js( KOCIST_META_PIXEL_ID ); ?>';
	var viewContent = <?php echo wp_json_encode( $view_content ); ?>;

	function consented() {
		var c = '';
		try { c = localStorage.getItem('k-cerez') || ''; } catch (e) {}
		return 'kabul' === c;
	}

	window.kocistPixelLoad = function () {
		if (window.kocistPixelReady || !consented()) { return; }
		window.kocistPixelReady = true;
		!function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
		window.fbq('init', PIXEL);
		window.fbq('track', 'PageView');
		if (viewContent) { window.fbq('track', 'ViewContent', { content_name: viewContent, content_type: 'product' }); }
	};

	// Onay yoksa sessizce düşer; onay varsa piksel yüklü değilse önce yükler.
	window.kocistTrack = function (name, params) {
		if (!consented()) { return; }
		window.kocistPixelLoad();
		if (typeof window.fbq === 'function') { window.fbq('track', name, params || {}); }
	};

	window.kocistPixelLoad();

	document.addEventListener('click', function (event) {
		var a = event.target.closest ? event.target.closest('a[href]') : null;
		if (!a) { return; }
		var href = a.getAttribute('href') || '';
		var method = '';
		if (/^https?:\/\/(www\.)?(wa\.me|api\.whatsapp\.com)\//i.test(href)) { method = 'whatsapp'; }
		else if (/^tel:/i.test(href)) { method = 'telefon'; }
		else if (/^mailto:/i.test(href)) { method = 'eposta'; }
		if (method) { window.kocistTrack('Contact', { content_category: method }); }
	}, true);
})();
</script>
	<?php
}

/**
 * Lead: form gönderimi sunucuda başarılı olduysa (kocist_quote_state success).
 * Durum tek kullanımlıktır; sayfa yenilenince ikinci Lead sayılmaz.
 */
add_action( 'wp_footer', 'kocist_meta_pixel_lead', 100 );
function kocist_meta_pixel_lead(): void {
	if ( ! function_exists( 'kocist_quote_state' ) || ! isset( $_GET['kc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$state = kocist_quote_state();

	if ( empty( $state['success'] ) ) {
		return;
	}

	$source = is_page( 'teklif-sepeti' ) ? 'teklif-sepeti' : ( get_query_var( 'nwcs_product' ) ? 'urun-teklif-formu' : 'iletisim-teklif-formu' );
	?>
<script>if (typeof window.kocistTrack === 'function') { window.kocistTrack('Lead', { content_category: <?php echo wp_json_encode( $source ); ?> }); }</script>
	<?php
}
