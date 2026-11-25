<?php
/**
 * Sayfanin soru-cevap bolumu (#sss): kategori, grup ve ana sayfa sorulari ayni
 * cizimle. Acma kapama <details>: betik yok (gorunum assets/css/faq.css).
 * Ayni satirlar FAQPage olarak isaretlenir (kategori: eklenti; grup ve ana
 * sayfa: kocist_seo_page_faq). Satir yoksa hicbir sey basilmaz.
 *
 * @var array $args {
 *     rows:       array<int, array{question:string, answer:string}>,
 *     title:      string,
 *     title_edit: array  basligin nwcs_edit_attr argumanlari,
 *     row_attr:   callable(array $row, string $field): void  onizlemede satirin tiklama hedefi,
 *     links:      bool  cevapta [yazi](/adres/) baglanti olsun mu (ana sayfa ve grup sorulari;
 *                 kategori sorulari eklentiden duz metin gelir),
 * }
 */

defined( 'ABSPATH' ) || exit;

$qa_rows = (array) ( $args['rows'] ?? array() );

if ( ! $qa_rows ) {
	return;
}

$qa_attr = is_callable( $args['row_attr'] ?? null ) ? $args['row_attr'] : static function (): void {};
$qa_open = function_exists( 'nwcs_is_preview' ) && nwcs_is_preview();
?>
<section class="k-catfaq" id="sss" aria-labelledby="sss-baslik">
	<h2 class="k-catfaq__title" id="sss-baslik" <?php if ( ! empty( $args['title_edit'] ) ) { nwcs_edit_attr( ...$args['title_edit'] ); } ?>><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></h2>
	<div class="k-sss__list">
		<?php foreach ( $qa_rows as $qa_row ) : ?>
			<details class="k-sss__item"<?php echo $qa_open ? ' open' : ''; ?>>
				<summary class="k-sss__q">
					<span <?php $qa_attr( $qa_row, 'question' ); ?>><?php echo esc_html( $qa_row['question'] ); ?></span>
					<span class="k-sss__icon" aria-hidden="true"></span>
				</summary>
				<div class="k-sss__a" <?php $qa_attr( $qa_row, 'answer' ); ?>>
					<?php echo kocist_paragraphs( $qa_row['answer'], '', ! empty( $args['links'] ) ); // phpcs:ignore WordPress.Security.EscapingOutput -- kocist_paragraphs kacisli. ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
