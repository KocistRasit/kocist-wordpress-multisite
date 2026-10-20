<?php
/**
 * Ust menu: marka, menu (Magaza buyuk menusu, Hakkimizda acilir menusu),
 * arama, Ozel Uretim, sepet. Mobilde soldan acilan cekmece.
 * Menu ogeleri global.header'dan; acilir menuler inc/nav.php.
 */

defined( 'ABSPATH' ) || exit;

$nav       = wk_nav();
$tree      = wk_category_tree();
$corporate = wk_corporate_pages();
$custom    = wk_existing_pages( array( 'ozel-uretim-talep-formu' => 'Özel Üretim' ) );
$search    = isset( $_GET['ara'] ) ? sanitize_text_field( wp_unslash( $_GET['ara'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$submenu = static function ( array $item, string $prefix ) use ( $tree, $corporate ): void {
	if ( 'shop' === $item['kind'] && $tree ) :
		?>
		<div class="wk-mega" id="<?php echo esc_attr( $prefix ); ?>-shop" data-panel>
			<div class="wk-mega__cols">
				<?php foreach ( $tree as $line ) : ?>
					<div class="wk-mega__col">
						<a class="wk-mega__head" href="<?php echo esc_url( $line['url'] ); ?>" <?php nwcs_edit_attr( 'home', 'catalog', 'lines' ); ?>>
							<span <?php nwcs_edit_attr( 'home', 'catalog', 'lines', $line['row'], 'label' ); ?>><?php echo esc_html( $line['label'] ); ?></span>
							<?php if ( '' !== $line['text'] ) : ?>
								<small <?php nwcs_edit_attr( 'home', 'catalog', 'lines', $line['row'], 'text' ); ?>><?php echo esc_html( $line['text'] ); ?></small>
							<?php endif; ?>
						</a>
						<ul>
							<?php foreach ( $line['children'] as $child ) : ?>
								<?php $child_page = wk_category_page_key( $child['slug'] ); ?>
								<li><a href="<?php echo esc_url( $child['url'] ); ?>" <?php $child_page && nwcs_edit_attr( $child_page, 'head', 'name' ); ?>><?php echo esc_html( $child['label'] ); ?> <span class="wk-num"><?php echo (int) $child['count']; ?></span></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
			<a class="wk-mega__all" href="<?php echo esc_url( wk_shop_url() ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'mega_all' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'mega_all' ) ); ?> <span class="wk-num">(<?php echo (int) wk_card_count(); ?>)</span></a>
		</div>
		<?php
	elseif ( 'about' === $item['kind'] && $corporate ) :
		?>
		<ul class="wk-drop" id="<?php echo esc_attr( $prefix ); ?>-about" data-panel>
			<?php foreach ( $corporate as $page ) : ?>
				<li><a href="<?php echo esc_url( $page['url'] ); ?>" <?php wk_page_link_attr( $page ); ?>><?php echo esc_html( $page['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	endif;
};
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<script>document.documentElement.classList.remove('no-js');</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#icerik" class="wk-skip">İçeriğe geç</a>

<header class="wk-header" data-header>
	<div class="wk-wrap wk-header__row">
		<button type="button" class="wk-iconbtn wk-header__burger" data-drawer-open aria-controls="wk-drawer" aria-expanded="false">
			<?php echo wk_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			<span class="wk-sr" <?php nwcs_edit_attr( 'global', 'header', 'menu_open' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'menu_open' ) ); ?></span>
		</button>

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="wk-brand wk-mark" <?php nwcs_edit_attr( 'global', 'header', 'logo_text' ); ?>>
			<?php echo wk_brand_mark(); // phpcs:ignore WordPress.Security.EscapingOutput -- ogeler kacirildi. ?>
		</a>

		<nav class="wk-nav" aria-label="Ana menü" <?php nwcs_edit_attr( 'global', 'header', 'menu' ); ?>>
			<ul class="wk-nav__list">
				<?php foreach ( $nav as $i => $item ) : ?>
					<?php $has = ( 'shop' === $item['kind'] && $tree ) || ( 'about' === $item['kind'] && $corporate ); ?>
					<li class="wk-nav__item<?php echo $has ? ' wk-nav__item--has' : ''; ?><?php echo 'shop' === $item['kind'] ? ' wk-nav__item--mega' : ''; ?>" <?php echo $has ? 'data-dropdown' : ''; ?>>
						<a class="wk-nav__link" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo $item['current'] ? 'aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'global', 'header', 'menu', (int) $i, 'label' ); ?>><?php echo esc_html( $item['label'] ); ?></a>
						<?php if ( $has ) : ?>
							<button type="button" class="wk-nav__toggle" aria-expanded="false" aria-controls="wk-nav-<?php echo (int) $i; ?>-<?php echo esc_attr( $item['kind'] ); ?>">
								<?php echo wk_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
								<span class="wk-sr"><?php echo esc_html( $item['label'] ); ?> alt menüsü</span>
							</button>
							<?php $submenu( $item, 'wk-nav-' . $i ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="wk-header__tools">
			<?php $contact_phone = trim( (string) nwcs_field( 'global', 'header', 'phone_label' ) ); ?>
			<?php if ( '' !== $contact_phone ) : ?>
				<a class="wk-callbtn" href="<?php echo esc_url( wk_page_url( 'iletisim' ) ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'phone_label' ); ?>>
					<?php echo wk_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					<span class="wk-callbtn__text"><span class="wk-sr">İletişim: </span><span class="wk-num"><?php echo esc_html( $contact_phone ); ?></span></span>
				</a>
			<?php endif; ?>
			<button type="button" class="wk-iconbtn" data-search-toggle aria-controls="wk-search" aria-expanded="false">
				<?php echo wk_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<span class="wk-sr" <?php nwcs_edit_attr( 'global', 'header', 'search_toggle' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'search_toggle' ) ); ?></span>
			</button>
			<?php if ( $custom ) : ?>
				<a class="wk-btn wk-btn--ghost wk-header__custom" href="<?php echo esc_url( $custom[0]['url'] ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'custom_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'custom_label' ) ); ?></a>
			<?php endif; ?>
			<?php $cart_label = explode( '{sayi}', (string) nwcs_field( 'global', 'header', 'cart_label' ), 2 ) + array( '', '' ); ?>
			<a class="wk-iconbtn wk-cartbtn" href="<?php echo esc_url( home_url( '/sepet/' ) ); ?>" data-cart-link <?php nwcs_edit_attr( 'global', 'header', 'cart_label' ); ?>>
				<?php echo wk_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<span class="wk-sr"><?php echo esc_html( trim( $cart_label[0] ) ); ?></span>
				<span class="wk-cartbtn__count" data-cart-count hidden>0</span>
				<span class="wk-sr"><?php echo esc_html( trim( $cart_label[1] ) ); ?></span>
			</a>
		</div>
	</div>

	<div class="wk-search" id="wk-search" data-search <?php echo '' === $search ? 'hidden' : ''; ?>>
		<form class="wk-wrap wk-search__form" role="search" method="get" action="<?php echo esc_url( wk_shop_url() ); ?>">
			<label class="wk-sr" for="wk-search-input" <?php nwcs_edit_attr( 'global', 'header', 'search_label' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'search_label' ) ); ?></label>
			<?php echo wk_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
			<input id="wk-search-input" type="search" name="ara" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr( nwcs_field( 'global', 'header', 'search_hint' ) ); ?>" autocomplete="off" <?php nwcs_edit_attr( 'global', 'header', 'search_hint' ); ?> />
			<button type="submit" class="wk-btn wk-btn--primary" <?php nwcs_edit_attr( 'global', 'header', 'search_button' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'search_button' ) ); ?></button>
		</form>
	</div>
</header>

<div class="wk-drawer" id="wk-drawer" data-drawer hidden>
	<div class="wk-drawer__backdrop" data-drawer-close></div>
	<div class="wk-drawer__panel" role="dialog" aria-modal="true" aria-label="Menü">
		<div class="wk-drawer__top">
			<span class="wk-mark" <?php nwcs_edit_attr( 'global', 'header', 'logo_text' ); ?>><?php echo wk_brand_mark(); // phpcs:ignore WordPress.Security.EscapingOutput ?></span>
			<button type="button" class="wk-iconbtn" data-drawer-close>
				<?php echo wk_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<span class="wk-sr" <?php nwcs_edit_attr( 'global', 'header', 'menu_close' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'menu_close' ) ); ?></span>
			</button>
		</div>
		<nav aria-label="Mobil menü">
			<ul class="wk-drawer__list">
				<?php foreach ( $nav as $i => $item ) : ?>
					<?php $has = ( 'shop' === $item['kind'] && $tree ) || ( 'about' === $item['kind'] && $corporate ); ?>
					<li>
						<?php if ( $has ) : ?>
							<button type="button" class="wk-drawer__group" aria-expanded="<?php echo $item['current'] ? 'true' : 'false'; ?>" aria-controls="wk-m-<?php echo (int) $i; ?>" data-accordion <?php nwcs_edit_attr( 'global', 'header', 'menu', (int) $i, 'label' ); ?>>
								<?php echo esc_html( $item['label'] ); ?>
								<?php echo wk_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
							</button>
							<div id="wk-m-<?php echo (int) $i; ?>" class="wk-drawer__sub" <?php echo $item['current'] ? '' : 'hidden'; ?>>
								<a class="wk-drawer__all" href="<?php echo esc_url( $item['url'] ); ?>" <?php 'shop' === $item['kind'] ? nwcs_edit_attr( 'global', 'header', 'drawer_all' ) : nwcs_edit_attr( 'global', 'header', 'menu', (int) $i, 'label' ); ?>><?php echo esc_html( 'shop' === $item['kind'] ? nwcs_field( 'global', 'header', 'drawer_all' ) : $item['label'] ); ?></a>
								<?php if ( 'shop' === $item['kind'] ) : ?>
									<?php foreach ( $tree as $line ) : ?>
										<a class="wk-drawer__line" href="<?php echo esc_url( $line['url'] ); ?>" <?php nwcs_edit_attr( 'home', 'catalog', 'lines', $line['row'], 'label' ); ?>><?php echo esc_html( $line['label'] ); ?></a>
										<?php foreach ( $line['children'] as $child ) : ?>
											<?php $child_page = wk_category_page_key( $child['slug'] ); ?>
											<a href="<?php echo esc_url( $child['url'] ); ?>" <?php $child_page && nwcs_edit_attr( $child_page, 'head', 'name' ); ?>><?php echo esc_html( $child['label'] ); ?></a>
										<?php endforeach; ?>
									<?php endforeach; ?>
								<?php else : ?>
									<?php foreach ( $corporate as $page ) : ?>
										<a href="<?php echo esc_url( $page['url'] ); ?>" <?php wk_page_link_attr( $page ); ?>><?php echo esc_html( $page['label'] ); ?></a>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<a class="wk-drawer__link" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo $item['current'] ? 'aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'global', 'header', 'menu', (int) $i, 'label' ); ?>><?php echo esc_html( $item['label'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="wk-drawer__foot">
			<a class="wk-btn wk-btn--primary" href="<?php echo esc_url( home_url( '/sepet/' ) ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'drawer_cart' ); ?>><?php echo wk_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapingOutput ?> <?php echo esc_html( nwcs_field( 'global', 'header', 'drawer_cart' ) ); ?></a>
			<?php if ( wk_whatsapp() ) : ?>
				<a class="wk-btn wk-btn--line" href="<?php echo esc_url( wk_whatsapp() ); ?>" target="_blank" rel="noopener" <?php nwcs_edit_attr( 'global', 'header', 'drawer_wa' ); ?>><?php echo wk_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?> <?php echo esc_html( nwcs_field( 'global', 'header', 'drawer_wa' ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>

<main id="icerik">
