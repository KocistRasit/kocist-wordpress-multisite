<?php
/**
 * Ust bilgi seridi, logo ve ana menu. Degerler 'global' sayfasindan okunur.
 *
 * Duzen referansi tuin.co.uk, menu hareketi revenuecat.com: acilir menuler
 * ayri kutular degil, tek bir kutu ogeler arasinda boyut degistirerek kayar.
 * Davranis assets/js/nav.js, gorunum assets/css/header.css icinde.
 *
 * nwcs_edit_attr() yalnizca panel onizlemesinde isaret basar; ziyaretcide
 * hicbir ek nitelik gorunmez.
 */

defined( 'ABSPATH' ) || exit;

$logo = kocist_image_or_default( nwcs_image( 'global', 'header', 'logo_image', 'medium' ), 'logo.png', 'Koçist Orman Ürünleri logosu' );
$menu = kocist_menu_items();

// Ortak panoda hangi menu ogesinin hangi panoya dustugu; sablonda iki kez
// dolasmamak icin once indeksler cikariliyor.
$menu_panes = array();

foreach ( $menu as $menu_index => $menu_item ) {
	if ( ! empty( $menu_item['children'] ) ) {
		$menu_panes[ $menu_index ] = $menu_item['children'];
	}
}

// Arama ve teklif sepeti (inc/search.php, inc/basket.php): sayfalari acilmadan basilmaz.
$has_search = kocist_page_ready( 'arama' );
$has_basket = kocist_page_ready( 'teklif-sepeti' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>

	<?php /* Tasarim token'lari: hem Tailwind renk adi (bg-forest, text-leaf) hem CSS degiskeni. */ ?>
	<style type="text/tailwindcss">
		@theme {
			--color-forest: #17542f;
			--color-forest-2: #1e6b3c;
			--color-forest-3: #0f3d21;
			--color-leaf: #7ab648;
			--color-leaf-dark: #5e9433;
			--color-leaf-soft: #eaf5e1;
			--color-paper: #ffffff;
			--color-paper-2: #f4f6f3;
			--color-ktext: #1f2a24;
			--color-muted: #5f6b63;
			--color-line: #dfe5e0;
		}
	</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="screen-reader-text k-skip" href="#icerik">İçeriğe geç</a>

<div class="k-topbar" data-nwcs-section="topbar">
	<div class="k-wrap k-topbar__inner">
		<div class="k-topbar__meta">
			<span class="k-topbar__note">
				<span <?php nwcs_edit_attr( 'global', 'topbar', 'note' ); ?>><?php echo esc_html( nwcs_field( 'global', 'topbar', 'note' ) ); ?></span>
				<span class="k-topbar__dot" aria-hidden="true">•</span>
				<span <?php nwcs_edit_attr( 'global', 'topbar', 'hours' ); ?>><?php echo esc_html( nwcs_field( 'global', 'topbar', 'hours' ) ); ?></span>
			</span>

			<?php if ( nwcs_field( 'global', 'topbar', 'bank_label' ) ) : ?>
				<a class="k-topbar__item" href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'topbar', 'bank_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'topbar', 'bank_label' ); ?>>
					<?php echo esc_html( nwcs_field( 'global', 'topbar', 'bank_label' ) ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="k-topbar__meta">
			<a class="k-topbar__item" href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'topbar', 'phone_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'topbar', 'phone_label' ); ?>>
				<?php nwcs_the_icon( nwcs_field( 'global', 'topbar', 'phone_icon' ), 'k-icon', 15 ); ?>
				<?php echo esc_html( nwcs_field( 'global', 'topbar', 'phone_label' ) ); ?>
			</a>
			<?php if ( '' !== trim( (string) nwcs_field( 'global', 'topbar', 'mobile_label' ) ) ) : ?>
				<a class="k-topbar__item" href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'topbar', 'mobile_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'topbar', 'mobile_label' ); ?>>
					<?php echo esc_html( nwcs_field( 'global', 'topbar', 'mobile_label' ) ); ?>
				</a>
			<?php endif; ?>
			<a class="k-topbar__item" href="<?php echo esc_url( kocist_link( nwcs_field( 'global', 'topbar', 'email_url' ) ) ); ?>" <?php nwcs_edit_attr( 'global', 'topbar', 'email_label' ); ?>>
				<?php nwcs_the_icon( nwcs_field( 'global', 'topbar', 'email_icon' ), 'k-icon', 15 ); ?>
				<?php echo esc_html( nwcs_field( 'global', 'topbar', 'email_label' ) ); ?>
			</a>
		</div>
	</div>
</div>

<header class="k-header" data-k-header data-nwcs-section="header">
	<div class="k-wrap k-header__inner<?php echo $has_search || $has_basket ? ' k-header__inner--tools' : ''; ?>">

		<a class="k-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( ! empty( $logo['url'] ) ) : ?>
				<img class="k-logo__img" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ?: get_bloginfo( 'name' ) ); ?>" <?php nwcs_edit_attr( 'global', 'header', 'logo_image' ); ?> />
			<?php else : ?>
				<span class="k-logo__mark" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="k-logo__text-wrap">
				<span class="k-logo__text" <?php nwcs_edit_attr( 'global', 'header', 'logo_text' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'logo_text' ) ); ?></span>
				<span class="k-logo__sub" <?php nwcs_edit_attr( 'global', 'header', 'logo_sub' ); ?>><?php echo esc_html( nwcs_field( 'global', 'header', 'logo_sub' ) ); ?></span>
			</span>
		</a>

		<button type="button" class="k-burger" data-k-burger aria-expanded="false" aria-controls="k-nav">
			<span class="screen-reader-text">Menüyü aç veya kapat</span>
			<span class="k-burger__bar" aria-hidden="true"></span>
			<span class="k-burger__bar" aria-hidden="true"></span>
			<span class="k-burger__bar" aria-hidden="true"></span>
		</button>

		<nav class="k-nav" id="k-nav" data-k-nav aria-label="Ana menü">
			<span class="k-nav__indicator" data-k-nav-indicator aria-hidden="true"></span>

			<?php if ( $has_search ) : ?>
				<?php /* Telefonda cekmecenin basinda arama kutusu (masaustunde gizli; orada ust menudeki dugme acar). */ ?>
				<div class="k-nav__search">
					<?php kocist_search_form( 'k-nav-search', 'k-searchform--drawer', '', 'search_region_drawer', true ); ?>
				</div>
			<?php endif; ?>

			<ul class="k-nav__list">
				<?php foreach ( $menu as $index => $item ) : ?>
					<?php
					$children     = $item['children'];
					$has_children = ! empty( $children );
					$is_exact     = kocist_is_current_menu_item( $item['url'] );
					$is_current   = $is_exact || kocist_is_current_menu_branch( $item );

					if ( $has_children ) {
						$columns   = kocist_menu_columns( count( $children ) );
						$pane_rows = (int) ceil( count( $children ) / $columns );
					}
					?>
					<li
						class="k-nav__item<?php echo $has_children ? ' k-nav__item--parent' : ''; ?>"
						data-k-nav-item
						<?php echo $has_children ? 'data-k-nav-pane="' . (int) $index . '"' : ''; ?>
					>
						<a
							class="k-nav__link<?php echo $is_current ? ' is-current' : ''; ?>"
							href="<?php echo esc_url( kocist_link( $item['url'] ) ); ?>"
							<?php echo $is_exact ? 'aria-current="page"' : ( $is_current ? 'aria-current="true"' : '' ); ?>
							data-k-nav-link
							<?php nwcs_edit_attr( 'global', 'header', 'menu', $index, 'label' ); ?>
						><?php echo esc_html( $item['label'] ); ?></a>

						<?php if ( $has_children ) : ?>
							<button type="button" class="k-nav__caret" data-k-nav-toggle aria-expanded="false">
								<span class="screen-reader-text">
									<?php echo esc_html( $item['label'] ); ?> alt menüsünü aç
								</span>
								<svg class="k-nav__chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
									<path d="M2.5 4.25 6 7.75l3.5-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</button>

							<?php /* Dar ekran kopyasi; masaustunde gizli, ortak pano devreye girer. */ ?>
							<div class="k-nav__accordion">
								<ul class="k-dropdown__list">
									<?php if ( ! empty( $item['group'] ) ) : ?>
										<li class="k-dropdown__item">
											<a class="k-dropdown__link k-dropdown__link--all" href="<?php echo esc_url( $item['group']['url'] ); ?>" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'group_all' ); ?>>
												<?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'group_all', array( 'grup' => $item['group']['name'] ) ) ); ?>
											</a>
										</li>
									<?php endif; ?>
									<?php foreach ( $children as $child ) : ?>
										<li class="k-dropdown__item">
											<a class="k-dropdown__link" href="<?php echo esc_url( kocist_link( $child['url'], $item['url'] ) ); ?>" <?php nwcs_edit_attr( ...$child['edit'] ); ?>>
												<?php echo esc_html( $child['label'] ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $menu_panes ) : ?>
				<?php /* Masaustu: tek kutu, ogeler arasinda boyut degistirerek kayar. */ ?>
				<div class="k-dropdown" data-k-dropdown>
					<div class="k-dropdown__shell" data-k-dropdown-shell>
						<?php foreach ( $menu_panes as $pane_index => $pane_children ) : ?>
							<?php
							$pane_columns = kocist_menu_columns( count( $pane_children ) );
							$pane_rows    = (int) ceil( count( $pane_children ) / $pane_columns );
							?>
							<div class="k-dropdown__pane" data-k-dropdown-pane="<?php echo (int) $pane_index; ?>">
								<?php
								/*
								 * Urun grubunda panonun basi grubun tamamina gider; alt
								 * kategori sayisi hangi genislikte bir liste acildigini
								 * onceden soyler.
								 */
								$pane_group = $menu[ $pane_index ]['group'] ?? null;
								?>
								<?php if ( $pane_group ) : ?>
									<a class="k-dropdown__head" href="<?php echo esc_url( $pane_group['url'] ); ?>">
										<span class="k-dropdown__head-title" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'group_all' ); ?>><?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'group_all', array( 'grup' => $pane_group['name'] ) ) ); ?></span>
										<span class="k-dropdown__head-meta" <?php nwcs_edit_attr( 'kategoriler', 'texts', 'meta_subs' ); ?>><?php echo esc_html( kocist_text( 'kategoriler', 'texts', 'meta_subs', array( 'kategori' => count( $pane_group['subs'] ) ) ) ); ?></span>
										<svg class="k-dropdown__head-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
											<path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									</a>
								<?php endif; ?>
								<ul
									class="k-dropdown__list"
									style="--cols: <?php echo (int) $pane_columns; ?>; --rows: <?php echo (int) $pane_rows; ?>"
								>
									<?php foreach ( $pane_children as $child_index => $child ) : ?>
										<?php $child_href = kocist_link( $child['url'], $menu[ $pane_index ]['url'] ); ?>
										<li class="k-dropdown__item" style="--i: <?php echo (int) $child_index; ?>">
											<a
												class="k-dropdown__link<?php echo kocist_is_current_menu_item( $child_href ) ? ' is-current' : ''; ?>"
												href="<?php echo esc_url( $child_href ); ?>"
												<?php echo kocist_is_current_menu_item( $child_href ) ? 'aria-current="page"' : ''; ?>
												<?php nwcs_edit_attr( ...$child['edit'] ); ?>
											>
												<?php echo esc_html( $child['label'] ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</nav>

		<?php if ( $has_search || $has_basket ) : ?>
			<div class="k-tools">
				<?php if ( $has_search ) : ?>
					<?php
					/*
					 * Gercek baglanti: betik yoksa arama sayfasina gider (orada buyuk
					 * arama kutusu var). Betik varsa (assets/js/teklif.js) asagidaki
					 * arama kutusunu acip kapatan dugme olur.
					 */
					?>
					<a class="k-tool" href="<?php echo esc_url( kocist_search_url() ); ?>" data-k-search-toggle aria-controls="k-search">
						<svg class="k-tool__icon" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m12.6 12.6 4.4 4.4" /></svg>
						<span class="screen-reader-text" <?php nwcs_edit_attr( 'global', 'tools', 'search_toggle' ); ?>><?php echo esc_html( nwcs_field( 'global', 'tools', 'search_toggle' ) ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $has_basket ) : ?>
					<?php
					// Sayi betikle cerezden yazilir: sayfa onbellekte kalsa da dogru (inc/basket.php).
					$basket_label = trim( (string) nwcs_field( 'global', 'tools', 'basket_label' ) );
					?>
					<a class="k-tool k-tool--basket<?php echo is_page( 'teklif-sepeti' ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( kocist_basket_url() ); ?>" data-k-basket-link data-path="<?php echo esc_attr( kocist_basket_cookie_path() ); ?>"<?php echo is_page( 'teklif-sepeti' ) ? ' aria-current="page"' : ''; ?> <?php nwcs_edit_attr( 'global', 'tools', 'basket_label' ); ?>>
						<?php kocist_basket_icon( 'k-tool__icon' ); ?>
						<span class="screen-reader-text" data-k-basket-sr data-empty="<?php echo esc_attr( $basket_label ); ?>" data-count="<?php echo esc_attr( (string) nwcs_field( 'global', 'tools', 'basket_count' ) ); ?>"><?php echo esc_html( $basket_label ); ?></span>
						<span class="k-tool__count" data-k-basket-count aria-hidden="true" hidden>0</span>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $has_search ) : ?>
		<div class="k-search" id="k-search" data-k-search hidden>
			<div class="k-wrap">
				<?php kocist_search_form( 'k-search-input', 'k-searchform--bar', '', 'search_region_bar', true ); ?>
			</div>
		</div>
	<?php endif; ?>
</header>

<main id="icerik">
