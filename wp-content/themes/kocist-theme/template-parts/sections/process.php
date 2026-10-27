<?php
/**
 * Siparis sureci: uc adim, her adimin ustunde cizgi bir cizim.
 *
 * Cizimler sabit (olcu alinan palet, damgali teklif, yoldaki kamyon); metinler
 * panelden. Bolum gorunume girince adimlari baglayan cizgi soldan saga cizilir,
 * cizimler sirayla kendini cizer (assets/js/process.js + assets/css/process.css).
 * Betik calismazsa ya da hareket azaltilmissa her sey son haliyle gorunur.
 *
 * Cizimdeki her yol pathLength="1" tasir: CSS tek bir dasharray degeriyle
 * hepsini ayni sekilde cizebilsin.
 */

defined( 'ABSPATH' ) || exit;

$items = array_slice( nwcs_rows( 'home', 'process', 'items' ), 0, 3 );

if ( ! $items ) {
	return;
}

$cta_label = nwcs_field( 'home', 'process', 'cta_label' );

/*
 * Adim cizimleri. Sinif anlamlari: .d cizilen cizgi, .d--accent yaprak yesili
 * cizgi, .f cizgiden sonra beliren dolgu/yazi. --d adimin icindeki gecikme.
 */
$art = array();

// 1. Olcu: onden gorunen palet, ustunde genislik olcusu, altinda cetvel.
ob_start();
?>
<svg class="k-process__svg" viewBox="0 0 160 120" aria-hidden="true" focusable="false">
	<path class="d d--accent" pathLength="1" d="M20 40H140M20 34v12M140 34v12M26 36l-6 4 6 4M134 36l6 4-6 4" style="--d:.7s" />
	<rect class="d" pathLength="1" x="20" y="52" width="120" height="10" rx="1.5" />
	<rect class="d" pathLength="1" x="24" y="62" width="16" height="14" style="--d:.15s" />
	<rect class="d" pathLength="1" x="72" y="62" width="16" height="14" style="--d:.2s" />
	<rect class="d" pathLength="1" x="120" y="62" width="16" height="14" style="--d:.25s" />
	<rect class="d" pathLength="1" x="20" y="76" width="120" height="8" rx="1.5" style="--d:.35s" />
	<path class="d" pathLength="1" d="M20 100H140M20 94v6M40 97v3M60 94v6M80 97v3M100 94v6M120 97v3M140 94v6" style="--d:.9s" />
</svg>
<?php
$art[] = ob_get_clean();

// 2. Teklif: belge, satirlar, fiyat kutusu ve onay damgasi.
ob_start();
?>
<svg class="k-process__svg" viewBox="0 0 160 120" aria-hidden="true" focusable="false">
	<path class="d" pathLength="1" d="M46 12h52l16 16v80H46z" />
	<path class="d" pathLength="1" d="M98 12v16h16" style="--d:.35s" />
	<path class="d" pathLength="1" d="M56 38h34M56 50h48M56 62h40" style="--d:.45s" />
	<rect class="d" pathLength="1" x="56" y="76" width="30" height="16" rx="2" style="--d:.65s" />
	<circle class="f k-process__stamp" cx="110" cy="90" r="17" style="--d:1.15s" />
	<circle class="d d--accent" pathLength="1" cx="110" cy="90" r="17" style="--d:.9s" />
	<path class="d d--accent k-process__check" pathLength="1" d="M102 90l6 6 11-12" style="--d:1.2s" />
</svg>
<?php
$art[] = ob_get_clean();

// 3. Sevkiyat: yuklu kamyon, hiz cizgileri ve yol.
ob_start();
?>
<svg class="k-process__svg" viewBox="0 0 160 120" aria-hidden="true" focusable="false">
	<path class="d d--accent k-process__road" pathLength="1" d="M6 104H154" style="--d:.9s" />
	<g class="k-process__truck">
		<rect class="d" pathLength="1" x="22" y="44" width="76" height="44" rx="3" />
		<path class="d" pathLength="1" d="M30 88V70h26v18M60 88V76h30v12M30 79h26M60 82h30" style="--d:.4s" />
		<path class="d" pathLength="1" d="M98 88V56h22l16 18v14z" style="--d:.2s" />
		<path class="d" pathLength="1" d="M104 61h13l10 11h-23z" style="--d:.5s" />
		<circle class="d" pathLength="1" cx="44" cy="94" r="8" style="--d:.6s" />
		<circle class="d" pathLength="1" cx="118" cy="94" r="8" style="--d:.65s" />
		<path class="d k-process__speed" pathLength="1" d="M4 58h12M0 68h12M6 78h10" style="--d:1.1s" />
	</g>
</svg>
<?php
$art[] = ob_get_clean();
?>
<section class="k-process" id="siparis-sureci" data-nwcs-section="process" data-k-process aria-labelledby="k-process-title">
	<div class="k-wrap">
		<div class="k-process__head">
			<h2 class="k-process__title" id="k-process-title" <?php nwcs_edit_attr( 'home', 'process', 'title' ); ?>><?php echo esc_html( nwcs_field( 'home', 'process', 'title' ) ); ?></h2>
			<p class="k-process__sub" <?php nwcs_edit_attr( 'home', 'process', 'subtitle' ); ?>><?php echo esc_html( nwcs_field( 'home', 'process', 'subtitle' ) ); ?></p>
		</div>

		<ol class="k-process__steps" style="--count:<?php echo (int) count( $items ); ?>">
			<?php foreach ( $items as $index => $item ) : ?>
				<li class="k-process__step" style="--i:<?php echo (int) $index; ?>">
					<div class="k-process__art">
						<?php echo $art[ $index ]; // phpcs:ignore WordPress.Security.EscapingOutput -- sabit SVG ?>
					</div>

					<span class="k-process__num" aria-hidden="true"><?php echo (int) $index + 1; ?></span>

					<h3 class="k-process__step-title" <?php nwcs_edit_attr( 'home', 'process', 'items', $index, 'title' ); ?>><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
					<p class="k-process__step-text" <?php nwcs_edit_attr( 'home', 'process', 'items', $index, 'text' ); ?>><?php echo esc_html( $item['text'] ?? '' ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php if ( $cta_label ) : ?>
			<div class="k-process__cta">
				<a class="k-btn k-btn--primary" href="<?php echo esc_url( kocist_link( nwcs_field( 'home', 'process', 'cta_url' ), '/iletisim/' ) ); ?>" <?php nwcs_edit_attr( 'home', 'process', 'cta_label' ); ?>>
					<?php echo esc_html( $cta_label ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</section>
