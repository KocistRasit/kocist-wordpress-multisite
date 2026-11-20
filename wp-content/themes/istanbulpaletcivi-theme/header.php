<?php
/**
 * Ust serit (adres, telefon, e-posta) ve ust menu. Icerik global.header'dan.
 */

defined( 'ABSPATH' ) || exit;

$logo     = pc_logo();
$menu     = pc_hide_missing_sss( nwcs_rows( 'global', 'header', 'menu' ) );
$phone    = pc_phone();
$whatsapp = pc_whatsapp();
$email    = trim( (string) nwcs_field( 'global', 'header', 'email' ) );

// Urunler baglantisinin altinda urun gruplari alt alta (acilir liste).
$groups   = pc_products();
$hub_path = pc_link_path( (string) ( pc_manifest()['pages']['products']['path'] ?? '' ) );
$current  = pc_current_product_key();
$is_hub   = static fn( string $url ): bool => '' !== $hub_path && pc_link_path( $url ) === $hub_path && ! empty( $groups );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#icerik" class="pc-skip">İçeriğe geç</a>

<div class="pc-topbar">
	<div class="pc-wrap pc-topbar__row">
		<p class="pc-topbar__item pc-topbar__address" <?php nwcs_edit_attr( 'global', 'header', 'address' ); ?>>
			<?php echo esc_html( nwcs_field( 'global', 'header', 'address' ) ); ?>
		</p>
		<?php if ( $email ) : ?>
			<a class="pc-topbar__item" href="<?php echo esc_url( 'mailto:' . $email ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'email' ); ?>>
				<?php echo esc_html( $email ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>

<header class="pc-header" data-header>
	<div class="pc-wrap pc-header__row">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pc-header__logo" <?php nwcs_edit_attr( 'global', 'header', 'logo_image' ); ?>>
			<?php if ( $logo['url'] ) : ?>
				<img src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( nwcs_field( 'global', 'header', 'logo_text' ) ); ?>" width="205" height="40" />
			<?php else : ?>
				<?php echo esc_html( nwcs_field( 'global', 'header', 'logo_text' ) ); ?>
			<?php endif; ?>
		</a>

		<nav class="pc-nav" aria-label="Ana menü" <?php nwcs_edit_attr( 'global', 'header', 'menu' ); ?>>
			<ul>
				<?php foreach ( $menu as $index => $item ) :
					$url = (string) ( $item['url'] ?? '' );
					$sub = $is_hub( $url );
					?>
					<li<?php echo $sub ? ' class="pc-nav__has-sub"' : ''; ?>>
						<a href="<?php echo esc_url( pc_link( $url ) ); ?>"
							<?php echo pc_is_current( $url ) ? 'aria-current="page"' : ( pc_is_within( $url ) ? 'data-within' : '' ); ?><?php nwcs_edit_attr( 'global', 'header', 'menu', (int) $index, 'label' ); ?>>
							<?php echo esc_html( $item['label'] ?? '' ); ?><?php echo $sub ? '<span class="pc-nav__caret" aria-hidden="true"></span>' : ''; ?>
						</a>
						<?php if ( $sub ) : ?>
							<ul class="pc-nav__sub">
								<?php foreach ( $groups as $group ) : ?>
									<li><a href="<?php echo esc_url( $group['url'] ); ?>"<?php echo $current === $group['key'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $group['name'] ); ?></a></li>
								<?php endforeach; ?>
								<li class="pc-nav__cta">
									<a class="pc-nav__all" href="<?php echo esc_url( pc_link( $url ) ); ?>">Tüm ürünler</a>
									<?php if ( $whatsapp ) : ?>
										<a class="pc-nav__wa" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">WhatsApp'tan fiyat al</a>
									<?php endif; ?>
								</li>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="pc-header__actions">
			<a href="<?php echo esc_url( $phone['url'] ); ?>" class="pc-btn pc-btn--line pc-header__call" <?php nwcs_edit_attr( 'global', 'header', 'phone_label' ); ?>>
				<?php echo pc_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
				<span class="pc-num"><?php echo esc_html( $phone['label'] ); ?></span>
			</a>
			<?php if ( $whatsapp ) : ?>
				<a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn--wa pc-header__wa" <?php nwcs_edit_attr( 'global', 'header', 'whatsapp_label' ); ?>>
					<?php echo pc_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>
					<?php echo esc_html( nwcs_field( 'global', 'header', 'whatsapp_label' ) ); ?>
				</a>
			<?php endif; ?>

			<button type="button" class="pc-menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="pc-mobile-nav">
				<span class="pc-menu-toggle__bars" aria-hidden="true"></span>
				<span class="pc-menu-toggle__text"<?php nwcs_edit_attr( 'global', 'header', 'menu_toggle' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'menu_toggle' ) ); ?></span>
			</button>
		</div>
	</div>

	<nav id="pc-mobile-nav" class="pc-mobile-nav" aria-label="Mobil menü" hidden>
		<ul class="pc-wrap">
			<?php foreach ( $menu as $index => $item ) :
				$url = (string) ( $item['url'] ?? '' );
				?>
				<li>
					<a href="<?php echo esc_url( pc_link( $url ) ); ?>" <?php echo pc_is_current( $url ) ? 'aria-current="page"' : ''; ?><?php nwcs_edit_attr( 'global', 'header', 'menu', (int) $index, 'label' ); ?>>
						<?php echo esc_html( $item['label'] ?? '' ); ?>
					</a>
					<?php if ( $is_hub( $url ) ) : ?>
						<ul class="pc-mobile-nav__sub">
							<?php foreach ( $groups as $group ) : ?>
								<li><a href="<?php echo esc_url( $group['url'] ); ?>"<?php echo $current === $group['key'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $group['name'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
</header>

<main id="icerik">
