<?php
/**
 * Yorumlar kapali (tum ag).
 *
 * Hicbir tema yorum formu ya da yorum listesi gostermiyor; ama WordPress
 * yazilarda yorumu varsayilan olarak acik tutar. Botlar formu gormeden
 * dogrudan wp-comments-post.php'ye yorum gonderiyordu; her biri yonetici
 * e-postasina "onay bekleyen yorum" bildirimi dusuruyordu (Ekim 2026,
 * sanayipalet.com'da kumar reklami).
 *
 * comments_open ve pings_open her yazida false doner:
 *   - wp-comments-post.php gonderimi kaydetmeden reddeder
 *     (wp_handle_comment_submission -> 'comment_closed');
 *   - REST ile yorum eklenemez (rest_comment_closed);
 *   - geri baglanti (pingback, trackback) alinmaz.
 *
 * Formlar etkilenmez: temalarin formlari admin-post.php isleyicileriyle
 * kendi kayit turlerine (kc_quote, wk_order ...) yazar, yorum sistemini
 * kullanmaz. Yonetimdeki Yorumlar ekrani yerinde kalir: daha once gelmis
 * yorumlar oradan silinebilir.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
