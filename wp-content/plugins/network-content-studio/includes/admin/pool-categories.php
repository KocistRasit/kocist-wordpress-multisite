<?php
/**
 * Ag Yonetimi -> Ürün Havuzu -> Kategoriler.
 *
 * Tek ekranda bir kategorinin her seyi: adi, hangi sitede hangi ust basligin
 * (WOOD KOCIST'te seri, Kocist'te urun grubu) altinda gorundugu, Excel ile
 * urun girisi (indir, yukle, onizleme, uygula) ve son islemler.
 *
 * Adres: ?page=nwcs-pool-categories&kategori=<slug>  (yeni kategori: &yeni=1)
 *
 * JavaScript gerekmez: her islem bir form (admin-post.php, nonce + yetki).
 * JS yalnizca listeyi yazdikca suzer ve yeni kategori adinda benzer adi haber verir.
 */

defined( 'ABSPATH' ) || exit;

const NWCS_CATEGORIES_SLUG = 'nwcs-pool-categories';

// Havuzun ilk alt ogesi "Ürünler" adini alsin (WordPress varsayilani ust menunun adi).
add_action( 'network_admin_menu', 'nwcs_register_categories_menu', 12 );
function nwcs_register_categories_menu(): void {
	add_submenu_page( NWCS_POOL_SLUG, 'Ürünler', 'Ürünler', NWCS_CAPABILITY, NWCS_POOL_SLUG, 'nwcs_render_pool' );
	add_submenu_page( NWCS_POOL_SLUG, 'Kategoriler', 'Kategoriler', NWCS_CAPABILITY, NWCS_CATEGORIES_SLUG, 'nwcs_render_categories' );
}

/**
 * Kategoriler sayfasinin adresi.
 */
function nwcs_pool_categories_url( array $args = array() ): string {
	return add_query_arg(
		array_merge( array( 'page' => NWCS_CATEGORIES_SLUG ), $args ),
		network_admin_url( 'admin.php' )
	);
}

/**
 * Turkce bulunma eki: "WOOD KOCIST" -> "WOOD KOCIST'te", "Koçist" -> "Koçist'te".
 * Buyuk harfli marka adlarinda I, i sayilir (KOCIST = Koçist).
 */
function nwcs_locative( string $name ): string {
	$low    = mb_strtolower( str_replace( 'I', 'i', $name ), 'UTF-8' );
	$vowels = preg_replace( '/[^aeıioöuü]/u', '', $low );
	$last   = '' !== $vowels ? mb_substr( $vowels, -1 ) : 'e';
	$front  = in_array( $last, array( 'e', 'i', 'ö', 'ü' ), true );
	$hard   = in_array( mb_substr( $low, -1 ), array( 'f', 's', 't', 'k', 'ç', 'ş', 'h', 'p' ), true );

	return $name . '’' . ( $hard ? 't' : 'd' ) . ( $front ? 'e' : 'a' );
}

/**
 * "Nerede gorunuyor" cumlesi: "WOOD KOCIST'te WOODGarden altında · Koçist'te gösterilmiyor".
 */
function nwcs_category_where( string $slug ): string {
	$term      = nwcs_pool_categories()[ $slug ] ?? array( 'name' => '', 'placement' => array() );
	$placement = $term['placement'];
	$parts     = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		$parent  = $placement[ $site['site_key'] ] ?? '';
		$parents = nwcs_site_parents( (int) $blog_id );
		$where   = nwcs_locative( $site['label'] );

		foreach ( $parents as $row ) {
			if ( $row['pool_slug'] === $slug ) {
				$parts[] = sprintf( '%s “%s” başlığının kendi kategorisi', $where, $row['label'] );
				continue 2;
			}
		}

		if ( '' === $parent ) {
			$parts[] = sprintf( '%s gösterilmiyor', $where );
			continue;
		}

		if ( ! isset( $parents[ $parent ] ) ) {
			$parts[] = sprintf( '%s görünmüyor (bağlı olduğu “%s” başlığı artık yok)', $where, $parent );
			continue;
		}

		$text = sprintf( '%s %s altında', $where, $parents[ $parent ]['label'] );
		$name = nwcs_category_site_name( $slug, (int) $blog_id );

		if ( '' !== $name && $name !== $term['name'] ) {
			$text .= sprintf( ' (“%s” adıyla)', $name );
		}

		// Urunu olmayan kategoriyi gizleyen sitede (WK) ne zaman gorunecegi.
		if ( ! empty( $site['catalog']['hide_empty'] ) && 0 === (int) ( nwcs_site_category_tree( (int) $blog_id )[ $parent ]['children'][ $slug ]['count'] ?? 0 ) ) {
			$text .= ', bu sitede ürünü olunca görünür';
		}

		$parts[] = $text;
	}

	return implode( ' · ', $parts );
}

/**
 * Kategorinin sitedeki adi (kat-<slug> sayfasinin "Kategori adi" alani: kayitli
 * deger, yoksa manifest varsayilani). Alan yoksa bos.
 */
function nwcs_category_site_name( string $slug, int $blog_id ): string {
	$manifest = nwcs_manifest_for_blog( $blog_id );
	$key      = 'kat-' . $slug;

	if ( ! isset( $manifest['pages'][ $key ]['components']['head']['fields']['name'] ) ) {
		return '';
	}

	$raw = trim( (string) ( nwcs_get_all( $blog_id )[ $key ]['head']['name'] ?? '' ) );

	return '' !== $raw ? $raw : trim( (string) nwcs_field_default( $manifest, $key, 'head', 'name' ) );
}

/**
 * Benzer adli kategoriler (yalnizca uyari): anlamli kelimelerden biri
 * digerinin basi ise ("Salıncaklar" ~ "Ahşap Salıncak").
 *
 * @return array<string, array{name:string, count:int}>
 */
function nwcs_category_similar( string $name, string $except = '' ): array {
	$ignore = array( 'ahsap', 've', 'ile', 'icin', 'urun', 'urunleri' );
	$words  = static function ( string $text ) use ( $ignore ): array {
		$list = preg_split( '/[^a-z0-9]+/', nwcs_search_fold( $text ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();

		return array_values( array_filter( $list, static fn( string $word ): bool => strlen( $word ) >= 4 && ! in_array( $word, $ignore, true ) ) );
	};

	$mine = $words( $name );
	$out  = array();

	foreach ( nwcs_pool_categories() as $slug => $term ) {
		if ( $slug === $except ) {
			continue;
		}

		foreach ( $words( $term['name'] ) as $other ) {
			foreach ( $mine as $word ) {
				if ( str_starts_with( $other, $word ) || str_starts_with( $word, $other ) ) {
					$out[ $slug ] = array( 'name' => $term['name'], 'count' => (int) $term['count'] );
					continue 3;
				}
			}
		}
	}

	return $out;
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

function nwcs_render_categories(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	// Yeni eklenen havuz tuketici site varsa yerlesim tohumu (tek seferlik).
	nwcs_placement_seed();

	// Uygulanmadan kalan fotograf partileri (24 saatten eski) silinir.
	nwcs_photos_sweep();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum secimi.
	$categories = nwcs_pool_categories();
	$current    = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : '';
	$current    = isset( $categories[ $current ] ) ? $current : '';
	$is_new     = isset( $_GET['yeni'] );
	$search     = isset( $_GET['ara'] ) ? nwcs_clean_text( wp_unslash( $_GET['ara'] ) ) : '';
	$unplaced   = isset( $_GET['gorunmeyen'] );
	$result     = isset( $_GET['sonuc'] ) ? sanitize_key( wp_unslash( $_GET['sonuc'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-cats">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Ürün Havuzu › Kategoriler</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout nwcs-linkout--accent" href="<?php echo esc_url( nwcs_pool_categories_url( array( 'yeni' => 1 ) ) ); ?>">+ Yeni kategori</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürünler</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_headings_url() ); ?>">Detay başlıkları</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_descriptions_url() ); ?>">Ürün açıklamaları</a>
			</div>
		</header>
		<?php // WordPress bildirimleri bu isaretin altina tasir (ust seridin icine degil). ?>
		<hr class="wp-header-end" />

		<?php nwcs_render_category_notice( $result ); ?>
		<?php nwcs_render_orphan_notice(); ?>
		<?php nwcs_render_history_result(); ?>
		<?php nwcs_render_cache_note(); ?>

		<?php
		// Excel ya da fotograf onizlemesi varken sayfa yalnizca onu gosterir: once karar.
		if ( nwcs_render_sync_step() || nwcs_render_photo_step() ) {
			echo '</div>';

			return;
		}
		?>

		<div class="nwcs-cats__grid">
			<?php nwcs_render_category_rail( $categories, $current, $search, $unplaced ); ?>

			<main class="nwcs-cats__main">
				<?php
				if ( $is_new ) {
					nwcs_render_category_new();
				} elseif ( '' !== $current ) {
					nwcs_render_category_detail( $current, $categories[ $current ] );
				} else {
					?>
					<div class="nwcs-cat nwcs-cat--empty">
						<h2 class="nwcs-cat__name">Bir kategori seçin</h2>
						<p class="nwcs-cat__lead">
							Soldaki listeden bir kategori seçin: hangi sitede nerede göründüğünü, ürünlerini ve Excel dosyasını burada görürsünüz.
							Yeni bir ürün grubu için yeni kategori açın.
						</p>
						<p><a class="button button-primary" href="<?php echo esc_url( nwcs_pool_categories_url( array( 'yeni' => 1 ) ) ); ?>">Yeni kategori aç</a></p>

						<section class="nwcs-cat__section" aria-labelledby="nwcs-sec-quick" id="nwcs-excel">
							<h3 class="nwcs-section__title" id="nwcs-sec-quick">Tüm ürünler: Hızlı Excel</h3>
							<p class="nwcs-hint">
								Bütün ürünlerin kodu, adı, fiyatı ve sitesi tek dosyada; toplu fiyat ya da kod güncellemesi için.
								Yalnızca var olan ürünler güncellenir; dosyadan sildiğiniz satırlara dokunulmaz. Yüklemeden önce neyin değişeceği gösterilir.
							</p>
							<div class="nwcs-excel__steps">
								<a class="button" href="<?php echo esc_url( nwcs_sync_download_url( '', true ) ); ?>">Hızlı Excel indir</a>
								<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-excel__up">
									<input type="hidden" name="action" value="nwcs_sync_upload" />
									<input type="hidden" name="kategori" value="" />
									<?php wp_nonce_field( 'nwcs_sync_upload' ); ?>
									<label class="nwcs-sublabel" for="nwcs-excel-file">Düzenlenmiş dosya (.xlsx)</label>
									<div class="nwcs-excel__row">
										<input class="nwcs-file" type="file" id="nwcs-excel-file" name="dosya" accept=".xlsx" required />
										<button type="submit" class="button button-primary">Yükle</button>
									</div>
								</form>
							</div>
						</section>
					</div>
					<?php
				}
				?>
			</main>
		</div>
	</div>
	<?php
}

/**
 * Yetim yerlesim uyarisi: bagli oldugu seri/grup satiri artik olmayan kategoriler.
 */
function nwcs_render_orphan_notice(): void {
	$orphans = nwcs_orphan_placements();

	if ( ! $orphans ) {
		return;
	}

	$links = array();
	$names = nwcs_pool_categories();

	foreach ( array_keys( $orphans ) as $slug ) {
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( nwcs_pool_categories_url( array( 'kategori' => $slug ) ) ), esc_html( $names[ $slug ]['name'] ?? $slug ) );
	}

	printf(
		'<div class="notice notice-warning"><p>%s %s</p></div>',
		esc_html( sprintf( '%d kategori sitede görünmüyor, çünkü bağlı olduğu başlık (seri ya da ürün grubu) artık yok. Başlık İçerik Stüdyosu’nda silinmiş ya da değiştirilmiş olabilir. Kategoriyi açıp listeden yeni bir başlık seçin:', count( $orphans ) ) ),
		implode( ', ', $links ) // phpcs:ignore WordPress.Security.EscapingOutput -- yukarida kacirildi.
	);
}

/**
 * Islem sonucu bildirimi.
 */
function nwcs_render_category_notice( string $result ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca bildirim.
	$extra = isset( $_GET['adet'] ) ? absint( $_GET['adet'] ) : 0;

	$map = array(
		'acildi'      => array( 'success', 'Kategori açıldı. Şimdi Excel’i indirin, ürünleri doldurun ve yükleyin.' ),
		'var'         => array( 'warning', 'Bu adda bir kategori zaten var; o açıldı.' ),
		'bos'         => array( 'error', 'Kategori adı boş. Bir ad yazıp yeniden deneyin.' ),
		'acilamadi'   => array( 'error', 'Kategori açılamadı. Adı yalnızca işaretlerden oluşuyor olabilir; harf ya da rakam içeren bir ad yazıp yeniden deneyin.' ),
		'ust'         => array( 'warning', 'Yerleşim kaydedilmedi: bu kategori bir sitedeki üst başlığın (seri ya da ürün grubu) kendi kategorisi, başka bir başlığın altına konamaz.' ),
		'sil_ust'     => array( 'error', 'Kategori silinmedi: bu kategori bir sitedeki üst başlığın (seri ya da ürün grubu) kendi kategorisi; silinirse o başlığa doğrudan bağlı ürünler başlıktan düşer. Silmek için önce İçerik Stüdyosu’nda başlığı kaldırın.' ),
		'ad'          => array( 'success', 'Kategorinin adı değişti. Adresi (bağlantısı) aynı kaldı.' ),
		'ad_var'      => array( 'error', 'Bu adda başka bir kategori var. Farklı bir ad yazın.' ),
		'yerlesim'    => array( 'success', 'Yerleşim kaydedildi. Sitelerde hemen görünür; yanlışsa “Son işlemler”den geri alabilirsiniz.' ),
		'ayni'        => array( 'info', 'Yerleşim değişmedi.' ),
		'silindi'     => array( 'success', 'Kategori silindi. Ürünleri silinmedi.' ),
		'budandi'     => array( 'success', sprintf( 'Ürünü olmayan ve hiçbir sitede yeri olmayan %d kategori silindi.', $extra ) ),
		'vazgecildi'  => array( 'info', 'Değişiklik yapılmadı.' ),
	);

	if ( isset( $map[ $result ] ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $result ][0] ),
			esc_html( $map[ $result ][1] )
		);
	}

	// Yeni kategori acilirken benzer ad (engellemez, haber verir).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$similar = isset( $_GET['benzer'] ) ? sanitize_title( wp_unslash( $_GET['benzer'] ) ) : '';
	$term    = nwcs_pool_categories()[ $similar ] ?? null;

	if ( $term ) {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( sprintf( 'Benzer adlı bir kategori de var: “%s” (%d ürün). Aynı ürünler içinse onu kullanın:', $term['name'], (int) $term['count'] ) ),
			esc_url( nwcs_pool_categories_url( array( 'kategori' => $similar ) ) ),
			esc_html( $term['name'] . ' kategorisine git' )
		);
	}
}

/**
 * Sol liste: arama, urun sayilari, "hicbir sitede gorunmeyen" suzgeci.
 */
function nwcs_render_category_rail( array $categories, string $current, string $search, bool $unplaced ): void {
	$sites   = nwcs_catalog_sites();
	$tops    = nwcs_parent_categories();
	$nowhere = array_filter( $categories, static fn( array $term, string $slug ): bool => ! $term['placement'] && ! isset( $tops[ $slug ] ), ARRAY_FILTER_USE_BOTH );
	$needle  = nwcs_search_fold( $search );
	$shown   = array_filter(
		$categories,
		static fn( array $term ): bool => ( ! $unplaced || ! $term['placement'] ) && ( '' === $needle || str_contains( nwcs_search_fold( $term['name'] ), $needle ) )
	);
	$empty   = nwcs_prunable_categories();
	$keep    = array_filter( array( 'ara' => $search, 'gorunmeyen' => $unplaced ? 1 : null ) );
	?>
	<nav class="nwcs-rail" aria-label="Kategoriler">
		<form method="get" class="nwcs-rail__search" role="search">
			<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_CATEGORIES_SLUG ); ?>" />
			<?php if ( '' !== $current ) : ?>
				<input type="hidden" name="kategori" value="<?php echo esc_attr( $current ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="nwcs-rail-q">Kategori ara</label>
			<input class="nwcs-input" type="search" id="nwcs-rail-q" name="ara" value="<?php echo esc_attr( $search ); ?>" placeholder="Kategori ara…" data-nwcs-rail-filter />
			<button type="submit" class="button nwcs-rail__go">Ara</button>
		</form>

		<p class="nwcs-rail__count"><?php echo esc_html( sprintf( '%d kategori', count( $categories ) ) ); ?></p>

		<ul class="nwcs-rail__list" data-nwcs-rail>
			<?php foreach ( $shown as $slug => $term ) : ?>
				<li data-nwcs-rail-name="<?php echo esc_attr( nwcs_search_fold( $term['name'] ) ); ?>">
					<?php $is_top = isset( $tops[ $slug ] ); ?>
					<a class="nwcs-rail__item<?php echo $slug === $current ? ' is-current' : ''; ?><?php echo $term['placement'] || $is_top || ! $sites ? '' : ' is-nowhere'; ?>"
						href="<?php echo esc_url( nwcs_pool_categories_url( array_merge( $keep, array( 'kategori' => $slug ) ) ) ); ?>"
						<?php echo $slug === $current ? 'aria-current="page"' : ''; ?>
						<?php echo $term['placement'] || $is_top ? '' : 'title="Hiçbir sitede görünmüyor"'; ?>>
						<span class="nwcs-rail__name">
							<?php echo esc_html( $term['name'] ); ?>
							<?php if ( $is_top ) : ?>
								<span class="nwcs-rail__tag">üst başlık</span>
							<?php endif; ?>
						</span>
						<span class="nwcs-rail__num"><?php echo (int) $term['count']; ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( ! $shown ) : ?>
			<p class="nwcs-hint">Bu aramaya uyan kategori yok.</p>
		<?php endif; ?>

		<div class="nwcs-rail__foot">
			<?php if ( $unplaced ) : ?>
				<a href="<?php echo esc_url( nwcs_pool_categories_url( array_filter( array( 'kategori' => $current ) ) ) ); ?>">Bütün kategorileri göster</a>
			<?php elseif ( $nowhere && $sites ) : ?>
				<a href="<?php echo esc_url( nwcs_pool_categories_url( array_filter( array( 'kategori' => $current, 'gorunmeyen' => 1 ) ) ) ); ?>">Hiçbir sitede görünmeyen: <?php echo (int) count( $nowhere ); ?></a>
			<?php endif; ?>

			<?php if ( count( $empty ) > 1 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="return confirm('Ürünü olmayan ve hiçbir sitede yeri olmayan <?php echo (int) count( $empty ); ?> kategori silinecek: <?php echo esc_js( implode( ', ', array_column( $empty, 'name' ) ) ); ?>. Devam edilsin mi?');">
					<input type="hidden" name="action" value="nwcs_pool_category_prune" />
					<?php wp_nonce_field( 'nwcs_pool_category_prune' ); ?>
					<button type="submit" class="button-link nwcs-rail__prune">Boş ve yersiz <?php echo (int) count( $empty ); ?> kategoriyi sil</button>
				</form>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}

/**
 * Sitelerde yerlesim satirlari (yeni kategori formu ve ayarlar ortak).
 *
 * @param array<string,string> $placement site_key => ust baslik
 */
function nwcs_render_placement_rows( array $placement, bool $fresh ): void {
	$sites = nwcs_catalog_sites();

	if ( ! $sites ) {
		echo '<p class="nwcs-hint">Kategori yerleştirilebilecek bir site yok; hiçbir sitede ürün kataloğu bölümü bulunmuyor. Bunu geliştiriciye haber verin.</p>';

		return;
	}

	foreach ( $sites as $blog_id => $site ) {
		$parents = nwcs_site_parents( (int) $blog_id );
		$chosen  = $placement[ $site['site_key'] ] ?? '';
		$id      = 'nwcs-place-' . (int) $blog_id;
		?>
		<div class="nwcs-place">
			<label class="nwcs-place__site" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="yer[<?php echo (int) $blog_id; ?>]" value="1" <?php checked( '' !== $chosen ); ?> data-nwcs-place />
				<strong><?php echo esc_html( $site['label'] ); ?></strong>
			</label>
			<label class="nwcs-place__parent">
				<span><?php echo esc_html( 'Hangi başlığın altında' ); ?></span>
				<select class="nwcs-input" name="ust[<?php echo (int) $blog_id; ?>]">
					<?php if ( '' !== $chosen && ! isset( $parents[ $chosen ] ) ) : ?>
						<?php // Artik olmayan baslik: secili kalir ki kaydedince kategori sessizce tasinmasin. ?>
						<option value="<?php echo esc_attr( $chosen ); ?>" selected><?php echo esc_html( sprintf( '“%s” (artık yok, başka bir başlık seçin)', $chosen ) ); ?></option>
					<?php endif; ?>
					<?php foreach ( $parents as $key => $parent ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $chosen ); ?>><?php echo esc_html( $parent['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( '' !== $chosen && ! isset( $parents[ $chosen ] ) ) : ?>
				<p class="nwcs-place__warn"><?php echo esc_html( sprintf( 'Bu kategori %s “%s” başlığına bağlı, ama bu başlık artık yok; bu yüzden kategori o sitede görünmüyor. Listeden bir başlık seçip kaydedin.', nwcs_locative( $site['label'] ), $chosen ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	if ( ! $fresh ) {
		echo '<p class="nwcs-hint">İşareti kaldırırsanız bu kategorinin ürünleri o sitede görünmez (o sitede başka bir kategoriyle görünenler kalır).</p>';
	}
}

/**
 * Yeni kategori formu.
 */
function nwcs_render_category_new(): void {
	$names = array();

	foreach ( nwcs_pool_categories() as $slug => $term ) {
		$names[] = array( 'slug' => $slug, 'name' => $term['name'], 'count' => (int) $term['count'], 'url' => nwcs_pool_categories_url( array( 'kategori' => $slug ) ) );
	}
	?>
	<article class="nwcs-cat">
		<h2 class="nwcs-cat__name">Yeni kategori</h2>
		<p class="nwcs-cat__lead">Kategoriyi açın ve hangi sitede nerede görüneceğini seçin. Açılınca bu sayfada kalırsınız: Excel’i indirip ürünleri girersiniz.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-cat__form">
			<input type="hidden" name="action" value="nwcs_category_create" />
			<?php wp_nonce_field( 'nwcs_category_create' ); ?>

			<div class="nwcs-field">
				<label class="nwcs-field__label" for="nwcs-cat-name">Adı</label>
				<input class="nwcs-input" type="text" id="nwcs-cat-name" name="isim" required autocomplete="off" placeholder="örn. Salıncaklar"
					data-nwcs-similar="<?php echo esc_attr( wp_json_encode( $names ) ); ?>" aria-describedby="nwcs-cat-similar" />
				<p class="nwcs-similar" id="nwcs-cat-similar" aria-live="polite"></p>
			</div>

			<fieldset class="nwcs-field nwcs-cat__sites">
				<legend class="nwcs-field__label">Sitelerde</legend>
				<?php nwcs_render_placement_rows( array(), true ); ?>
			</fieldset>

			<div class="nwcs-actions">
				<button type="submit" class="button button-primary">Kategoriyi aç</button>
				<a class="button" href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Vazgeç</a>
			</div>
		</form>
	</article>
	<?php
}

/**
 * Tek kategorinin ekrani.
 */
function nwcs_render_category_detail( string $slug, array $term ): void {
	$sites    = nwcs_catalog_sites();
	$products = nwcs_sync_category_products( $slug );
	$mine     = nwcs_history_latest_for( $slug );
	$on_top   = $mine && ( nwcs_history_top()['id'] ?? '' ) === ( $mine['id'] ?? '' );
	$tops     = nwcs_parent_categories()[ $slug ] ?? array();
	$changed  = $mine ? array_map( 'intval', array_keys( (array) ( $mine['updated'] ?? array() ) ) ) : array();
	$created  = $mine ? array_map( 'intval', (array) ( $mine['created'] ?? array() ) ) : array();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gorunum secimi.
	$only     = isset( $_GET['degisen'] ) && $mine;
	$here     = nwcs_pool_categories_url( array( 'kategori' => $slug ) );
	$photos   = nwcs_history_latest( NWCS_PHOTO_KIND );
	$photoed  = $photos && ( $photos['slug'] ?? '' ) === $slug ? array_map( 'intval', array_keys( (array) ( $photos['products'] ?? array() ) ) ) : array();
	?>
	<article class="nwcs-cat" aria-labelledby="nwcs-cat-title">
		<header class="nwcs-cat__head">
			<h2 class="nwcs-cat__name" id="nwcs-cat-title"><?php echo esc_html( $term['name'] ); ?></h2>
			<details class="nwcs-cat__rename">
				<summary>Adı değiştir</summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nwcs_category_rename" />
					<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
					<?php wp_nonce_field( 'nwcs_category_rename_' . $slug ); ?>
					<label class="nwcs-sublabel" for="nwcs-cat-rename">Yeni ad</label>
					<div class="nwcs-excel__row">
						<input class="nwcs-input" type="text" id="nwcs-cat-rename" name="isim" value="<?php echo esc_attr( $term['name'] ); ?>" required />
						<button type="submit" class="button">Adı kaydet</button>
					</div>
					<p class="nwcs-hint">Adres (bağlantı) değişmez. Sitelerin menüsündeki ad, sitenin kendi “Sayfa metni”nden ayrıca değiştirilebilir.</p>
					<?php if ( $tops ) : ?>
						<p class="nwcs-hint">Bu kategori bir sitenin üst başlığının kendi kategorisi. Başlığa bağı adrese göre kurulur; ad değişince bağ kopmaz. Sitede görünen başlık adı İçerik Stüdyosu’ndan değişir.</p>
					<?php endif; ?>
				</form>
			</details>
		</header>

		<p class="nwcs-cat__where">
			<span class="nwcs-cat__count"><?php echo esc_html( sprintf( '%d ürün', count( $products ) ) ); ?></span>
			<?php if ( $sites ) : ?>
				· <?php echo esc_html( nwcs_category_where( $slug ) ); ?>
			<?php endif; ?>
			<?php $faq_fact = nwcs_cat_faq_header_fact( $slug ); ?>
			<?php if ( '' !== $faq_fact ) : ?>
				· <a class="nwcs-cat__faq" href="#nwcs-sss"><?php echo esc_html( $faq_fact ); ?></a>
			<?php endif; ?>
		</p>

		<section class="nwcs-cat__section" aria-labelledby="nwcs-sec-sites">
			<h3 class="nwcs-section__title" id="nwcs-sec-sites">Sitelerde</h3>
			<?php if ( $tops ) : ?>
				<?php foreach ( $tops as $top ) : ?>
					<p class="nwcs-cat__lead"><?php echo esc_html( sprintf( '%s “%s” başlığının kendi kategorisidir: bu kategorideki ürünler o başlığın sayfasında görünür. Üst başlık kategorisi başka bir başlığın altına yerleştirilmez.', nwcs_locative( $top['site'] ), $top['label'] ) ); ?></p>
				<?php endforeach; ?>
			<?php else : ?>
				<?php nwcs_render_placement_form( $slug, $term ); ?>
			<?php endif; ?>
		</section>

		<section class="nwcs-cat__section" aria-labelledby="nwcs-sec-excel" id="nwcs-excel">
			<h3 class="nwcs-section__title" id="nwcs-sec-excel">Ürünleri Excel ile girin</h3>
			<p class="nwcs-hint">Excel’i indirin, satırları doldurun ya da düzeltin, sonra yükleyin. Dosyada ürün adı, kodu, fiyatı, kısa açıklaması, meta başlığı ve açıklaması, ürün açıklaması, sayfa adresi ve teknik detaylar var. Yüklemeden önce neyin değişeceği gösterilir; onaylamadan hiçbir şey yazılmaz.</p>
			<p class="nwcs-hint">Yalnızca kod, ad, fiyat ve site güncellenecekse <strong>Hızlı Excel</strong> yeter: var olan ürünleri günceller, yeni ürün eklemez, dosyadan sildiğiniz satırlara dokunmaz. İkisi de aynı “Yükle” ile yüklenir.</p>
			<div class="nwcs-excel__steps">
				<a class="button" href="<?php echo esc_url( nwcs_sync_download_url( $slug ) ); ?>">Excel indir</a>
				<a class="button" href="<?php echo esc_url( nwcs_sync_download_url( $slug, true ) ); ?>">Hızlı Excel indir</a>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-excel__up">
					<input type="hidden" name="action" value="nwcs_sync_upload" />
					<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
					<?php wp_nonce_field( 'nwcs_sync_upload' ); ?>
					<label class="nwcs-sublabel" for="nwcs-excel-file">Düzenlenmiş dosya (.xlsx)</label>
					<div class="nwcs-excel__row">
						<input class="nwcs-file" type="file" id="nwcs-excel-file" name="dosya" accept=".xlsx" required />
						<button type="submit" class="button button-primary">Yükle</button>
					</div>
				</form>
			</div>
			<?php if ( $mine ) : ?>
				<?php $info = nwcs_history_describe( $mine ); ?>
				<p class="nwcs-excel__last">
					Son yükleme: <?php echo esc_html( wp_date( 'd.m H:i', (int) ( $mine['time'] ?? 0 ) ) . ', ' . $info['facts'] ); ?>.
					<?php echo $on_top ? 'Yanlışsa aşağıdaki “Son işlemler”den geri alabilirsiniz.' : 'Geri almak için “Son işlemler”de önce üstündeki işlemleri geri alın.'; ?>
				</p>
			<?php endif; ?>
		</section>

		<?php nwcs_render_photo_box( $slug, $products ); ?>

		<?php nwcs_render_color_groups( $slug, $products ); ?>

		<?php nwcs_render_cat_faq( $slug ); ?>

		<section class="nwcs-cat__section" aria-labelledby="nwcs-sec-products">
			<div class="nwcs-section__bar">
				<h3 class="nwcs-section__title" id="nwcs-sec-products">Ürünler (<?php echo count( $products ); ?>)</h3>
				<?php if ( $mine && ( $changed || $created ) ) : ?>
					<?php if ( $only ) : ?>
						<a href="<?php echo esc_url( $here ); ?>">Bütün ürünleri göster</a>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'degisen', 1, $here ) ); ?>">Yalnızca son yüklemede değişenler</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( ! $products ) : ?>
				<div class="nwcs-cat__empty">
					<p>Bu kategoride henüz ürün yok. Excel’i indirin, doldurun, yükleyin.</p>
					<a class="button" href="<?php echo esc_url( nwcs_sync_download_url( $slug ) ); ?>">Excel indir</a>
				</div>
			<?php else : ?>
				<div class="nwcs-cat__scroll">
					<table class="nwcs-table nwcs-cat__table">
						<thead>
							<tr>
								<th scope="col">Kod</th>
								<th scope="col">Ürün</th>
								<th scope="col">Fiyat</th>
								<th scope="col">Detay</th>
								<th scope="col">Fotoğraf</th>
								<th scope="col"><span class="screen-reader-text">Son yükleme</span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $products as $id => $product ) : ?>
								<?php
								$state = in_array( (int) $id, $created, true ) ? 'yeni' : ( in_array( (int) $id, $changed, true ) ? 'değişti' : '' );

								if ( $only && '' === $state ) {
									continue;
								}
								?>
								<tr<?php echo '' !== $state ? ' class="is-changed"' : ''; ?>>
									<td class="nwcs-cat__code"><?php echo esc_html( (string) $product['code'] ); ?></td>
									<td><a href="<?php echo esc_url( nwcs_pool_url( array( 'urun' => (int) $id ) ) ); ?>"><?php echo esc_html( $product['title'] ); ?></a></td>
									<td class="nwcs-nowrap"><?php echo '' !== trim( (string) $product['price'] ) ? esc_html( $product['price'] ) : '<em class="nwcs-quote">Teklif al</em>'; ?></td>
									<td class="nwcs-nowrap"><?php echo esc_html( sprintf( '%d detay', count( (array) $product['details'] ) ) ); ?></td>
									<td class="nwcs-nowrap"><?php echo $product['images'] ? esc_html( (string) count( (array) $product['images'] ) ) : '<span class="nwcs-cat__none">yok</span>'; ?></td>
									<td>
										<?php echo '' !== $state ? '<span class="nwcs-badge nwcs-badge--' . ( 'yeni' === $state ? 'new' : 'changed' ) . '">' . esc_html( $state ) . '</span>' : ''; ?>
										<?php echo in_array( (int) $id, $photoed, true ) ? '<span class="nwcs-badge nwcs-badge--new">fotoğraf</span>' : ''; ?>
										<?php echo nwcs_color_badge( (int) $id, $slug ); // phpcs:ignore WordPress.Security.EscapingOutput -- nwcs_color_badge kacirir. ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

		<?php nwcs_render_history_card( $here ); ?>

		<footer class="nwcs-cat__foot" id="nwcs-sil">
			<?php nwcs_render_category_delete( $slug, $term, $products ); ?>
		</footer>
	</article>
	<?php
}

/**
 * Kategoriyi silme: once ne olacagi sunucuda gosterilir, sonra onay.
 */
function nwcs_render_category_delete( string $slug, array $term, array $products ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gorunum secimi.
	$asking = isset( $_GET['sil'] );

	if ( ! $asking ) {
		?>
		<form method="get" action="<?php echo esc_url( network_admin_url( 'admin.php' ) . '#nwcs-sil' ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( NWCS_CATEGORIES_SLUG ); ?>" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<input type="hidden" name="sil" value="1" />
			<button type="submit" class="button-link nwcs-row__delete">Kategoriyi sil…</button>
			<span class="nwcs-hint">Önce ne olacağı gösterilir; ürünler silinmez.</span>
		</form>
		<?php

		return;
	}

	$loss = array();

	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		if ( '' !== ( $term['placement'][ $site['site_key'] ] ?? '' ) ) {
			$loss[] = sprintf( '%s %d ürün görünmez olur (orada başka kategoriyle görünenler kalır)', nwcs_locative( $site['label'] ), count( nwcs_placement_orphans( $slug, (int) $blog_id ) ) );
		}
	}
	?>
	<div class="nwcs-confirm" role="alertdialog" aria-labelledby="nwcs-del-title">
		<h4 id="nwcs-del-title">“<?php echo esc_html( $term['name'] ); ?>” silinsin mi?</h4>
		<ul>
			<li><?php echo esc_html( sprintf( '%d ürün bu kategoriden çıkar; ürünler silinmez, Ürün Havuzu’nda kalır.', count( $products ) ) ); ?></li>
			<?php foreach ( $loss as $line ) : ?>
				<li><?php echo esc_html( $line ); ?></li>
			<?php endforeach; ?>
			<li>Sitelerdeki kategori sayfası kapanır; o adrese gelen ziyaretçi “sayfa bulunamadı” görür.</li>
		</ul>
		<p class="nwcs-hint">Yanlışsa “Son işlemler”den geri alınabilir: kategori, ürünleri ve sitelerdeki yeri geri gelir.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-actions">
			<input type="hidden" name="action" value="nwcs_category_delete" />
			<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
			<?php wp_nonce_field( 'nwcs_category_delete_' . $slug ); ?>
			<button type="submit" class="button button-primary nwcs-danger-btn">Evet, kategoriyi sil</button>
			<a class="button" href="<?php echo esc_url( nwcs_pool_categories_url( array( 'kategori' => $slug ) ) ); ?>">Vazgeç</a>
		</form>
	</div>
	<?php
}

/**
 * "Sitelerde" formu; kaldirma onayi bekliyorsa onay kutusu.
 */
function nwcs_render_placement_form( string $slug, array $term ): void {
	$pending = get_site_transient( 'nwcs_place_confirm_' . get_current_user_id() );
	$pending = is_array( $pending ) && ( $pending['slug'] ?? '' ) === $slug ? $pending : null;
	$sites   = nwcs_catalog_sites();

	if ( $pending ) {
		?>
		<div class="nwcs-confirm" role="alertdialog" aria-labelledby="nwcs-confirm-title">
			<h4 id="nwcs-confirm-title">Kaldırmadan önce</h4>
			<ul>
				<?php foreach ( (array) $pending['loss'] as $blog_id => $count ) : ?>
					<li><?php echo esc_html( sprintf( '%s kaldırırsanız bu kategorinin %d ürünü o sitede görünmez olur.', nwcs_locative( $sites[ $blog_id ]['label'] ?? '' ) . 'n', (int) $count ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="nwcs-hint">
				O sitede başka bir kategoriyle görünen ürünler kalır. İşlem “Son işlemler”den geri alınabilir.
				<?php echo esc_html( sprintf( 'Not: sonra yeniden işaretlerseniz kategorinin bütün ürünleri (%d) o sitenin listesinin sonuna yeniden eklenir; İçerik Stüdyosu’ndaki sıraları kaybolur.', count( nwcs_category_product_ids( $slug ) ) ) ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-actions">
				<input type="hidden" name="action" value="nwcs_category_place" />
				<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
				<input type="hidden" name="onay" value="1" />
				<?php wp_nonce_field( 'nwcs_category_place_' . $slug ); ?>
				<button type="submit" class="button button-primary">Evet, kaldır</button>
				<button type="submit" name="vazgec" value="1" class="button">Vazgeç</button>
			</form>
		</div>
		<?php

		return;
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-cat__form">
		<input type="hidden" name="action" value="nwcs_category_place" />
		<input type="hidden" name="kategori" value="<?php echo esc_attr( $slug ); ?>" />
		<?php wp_nonce_field( 'nwcs_category_place_' . $slug ); ?>

		<?php nwcs_render_placement_rows( $term['placement'], false ); ?>

		<?php
		$links = array();

		foreach ( $sites as $blog_id => $site ) {
			if ( '' !== ( $term['placement'][ $site['site_key'] ] ?? '' ) ) {
				$links[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s düzenle<span class="screen-reader-text"> (yeni sekmede açılır)</span> ↗</a>',
					esc_url( nwcs_panel_url( (int) $blog_id, 'kat-' . $slug ) ),
					esc_html( nwcs_locative( $site['label'] ) )
				);
			}
		}
		?>
		<?php if ( $links ) : ?>
			<p class="nwcs-cat__pages">Sayfa metni (başlık, tanıtım cümlesi): <?php echo implode( ' · ', $links ); // phpcs:ignore WordPress.Security.EscapingOutput -- yukarida kacirildi. ?></p>
		<?php endif; ?>

		<div class="nwcs-actions">
			<button type="submit" class="button">Yerleşimi kaydet</button>
		</div>
	</form>
	<?php
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

/**
 * Formdaki site isaretleri: blog_id => ust baslik (null: gosterme).
 *
 * @return array<int, ?string>
 */
function nwcs_posted_placement(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- cagiran denetledi.
	$checked = isset( $_POST['yer'] ) && is_array( $_POST['yer'] ) ? array_map( 'absint', array_keys( wp_unslash( $_POST['yer'] ) ) ) : array();
	$parents = isset( $_POST['ust'] ) && is_array( $_POST['ust'] ) ? wp_unslash( $_POST['ust'] ) : array();
	// phpcs:enable

	$wanted = array();

	foreach ( array_keys( nwcs_catalog_sites() ) as $blog_id ) {
		$parent             = sanitize_title( (string) ( $parents[ $blog_id ] ?? '' ) );
		$wanted[ $blog_id ] = in_array( (int) $blog_id, $checked, true ) && '' !== $parent ? $parent : null;
	}

	return $wanted;
}

/**
 * Yerlesim degisikligini uygular ve "Son işlemler"e yazar.
 */
function nwcs_category_apply_placement( string $slug, array $wanted ): bool {
	$result = nwcs_placement_update( $slug, $wanted );

	if ( ! $result['changed'] ) {
		return false;
	}

	$sites = nwcs_catalog_sites();
	$facts = array();

	foreach ( $sites as $blog_id => $site ) {
		$was = $result['before'][ $site['site_key'] ] ?? '';
		$now = $result['after'][ $site['site_key'] ] ?? '';

		if ( $was === $now ) {
			continue;
		}

		$row     = $result['sites'][ $blog_id ] ?? array( 'added' => array(), 'removed' => array() );
		$parents = nwcs_site_parents( (int) $blog_id );

		if ( '' === $now ) {
			$facts[] = sprintf( '%s kaldırıldı (%d ürün seçimden çıktı)', $site['label'], count( $row['removed'] ) );
		} elseif ( '' === $was ) {
			$facts[] = sprintf( '%s: %s altına (%d ürün seçime girdi)', $site['label'], $parents[ $now ]['label'] ?? $now, count( $row['added'] ) );
		} else {
			$facts[] = sprintf( '%s: %s altına taşındı', $site['label'], $parents[ $now ]['label'] ?? $now );
		}
	}

	nwcs_history_push(
		array(
			'kind'     => 'yerlesim',
			'slug'     => $slug,
			'category' => (string) ( nwcs_pool_categories()[ $slug ]['name'] ?? $slug ),
			'before'   => $result['before'],
			'after'    => $result['after'],
			'sites'    => $result['sites'],
			'facts'    => $facts,
			'complete' => true,
		)
	);

	return true;
}

add_action( 'admin_post_nwcs_category_create', 'nwcs_handle_category_create' );
function nwcs_handle_category_create(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nwcs_category_create' );

	$name = isset( $_POST['isim'] ) ? nwcs_clean_text( wp_unslash( $_POST['isim'] ) ) : '';

	if ( '' === $name ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'yeni' => 1, 'sonuc' => 'bos' ) ) );
		exit;
	}

	$similar = array_keys( nwcs_category_similar( $name ) );

	switch_to_blog( nwcs_pool_blog_id() );
	$made = wp_insert_term( wp_slash( $name ), NWCS_PRODUCT_TAX );
	$term = is_wp_error( $made )
		? ( $made->get_error_data( 'term_exists' ) ? get_term( (int) $made->get_error_data( 'term_exists' ), NWCS_PRODUCT_TAX ) : null )
		: get_term( (int) $made['term_id'], NWCS_PRODUCT_TAX );
	restore_current_blog();

	nwcs_pool_categories_flush();

	if ( ! $term instanceof WP_Term ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'yeni' => 1, 'sonuc' => 'acilamadi' ) ) );
		exit;
	}

	if ( is_wp_error( $made ) ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $term->slug, 'sonuc' => 'var' ) ) );
		exit;
	}

	// Bos kategoride secim degismez; yerlesim yalnizca yazilir (geri alma kaydi gerekmez).
	nwcs_placement_update( $term->slug, nwcs_posted_placement() );

	$args = array( 'kategori' => $term->slug, 'sonuc' => 'acildi' );
	$left = array_values( array_diff( $similar, array( $term->slug ) ) );

	if ( $left ) {
		$args['benzer'] = $left[0];
	}

	wp_safe_redirect( nwcs_pool_categories_url( $args ) );
	exit;
}

add_action( 'admin_post_nwcs_category_rename', 'nwcs_handle_category_rename' );
function nwcs_handle_category_rename(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	check_admin_referer( 'nwcs_category_rename_' . $slug );

	$name = isset( $_POST['isim'] ) ? nwcs_clean_text( wp_unslash( $_POST['isim'] ) ) : '';
	$term = nwcs_pool_categories()[ $slug ] ?? null;

	if ( ! $term || '' === $name ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'bos' ) ) );
		exit;
	}

	foreach ( nwcs_pool_categories() as $other_slug => $other ) {
		if ( $other_slug !== $slug && nwcs_search_fold( $other['name'] ) === nwcs_search_fold( $name ) ) {
			wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'ad_var' ) ) );
			exit;
		}
	}

	// Yalnizca ad: slug (adres, yerlesim, sayfa anahtari) sabit kalir.
	switch_to_blog( nwcs_pool_blog_id() );
	wp_update_term( (int) $term['id'], NWCS_PRODUCT_TAX, array( 'name' => wp_slash( $name ) ) );
	restore_current_blog();

	nwcs_pool_flush_cache();

	wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'ad' ) ) );
	exit;
}

add_action( 'admin_post_nwcs_category_place', 'nwcs_handle_category_place' );
function nwcs_handle_category_place(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	check_admin_referer( 'nwcs_category_place_' . $slug );

	$key  = 'nwcs_place_confirm_' . get_current_user_id();
	$term = nwcs_pool_categories()[ $slug ] ?? null;

	if ( ! $term ) {
		wp_safe_redirect( nwcs_pool_categories_url() );
		exit;
	}

	// Onay adimindan gelindi: bekleyen istek uygulanir ya da birakilir.
	if ( ! empty( $_POST['onay'] ) ) {
		$pending = get_site_transient( $key );
		delete_site_transient( $key );

		if ( ! empty( $_POST['vazgec'] ) || ! is_array( $pending ) || ( $pending['slug'] ?? '' ) !== $slug ) {
			wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'vazgecildi' ) ) );
			exit;
		}

		$changed = nwcs_category_apply_placement( $slug, (array) $pending['wanted'] );

		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => $changed ? 'yerlesim' : 'ayni' ) ) );
		exit;
	}

	$wanted = nwcs_posted_placement();
	$loss   = array();

	if ( nwcs_parent_categories()[ $slug ] ?? null ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'ust' ) ) );
		exit;
	}

	// Kaldirilan sitede kac urun gorunmez olacak? Varsa once sorulur.
	foreach ( nwcs_catalog_sites() as $blog_id => $site ) {
		if ( null === $wanted[ $blog_id ] && '' !== ( $term['placement'][ $site['site_key'] ] ?? '' ) ) {
			$count = count( nwcs_placement_orphans( $slug, (int) $blog_id ) );

			if ( $count ) {
				$loss[ $blog_id ] = $count;
			}
		}
	}

	if ( $loss ) {
		set_site_transient( $key, array( 'slug' => $slug, 'wanted' => $wanted, 'loss' => $loss ), 15 * MINUTE_IN_SECONDS );
		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug ) ) . '#nwcs-sec-sites' );
		exit;
	}

	$changed = nwcs_category_apply_placement( $slug, $wanted );

	wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => $changed ? 'yerlesim' : 'ayni' ) ) );
	exit;
}

/**
 * Budanabilecek kategoriler: urunu yok, hicbir sitede yeri yok, ust baslik degil.
 *
 * @return array<string, array> slug => kategori
 */
function nwcs_prunable_categories(): array {
	$tops = nwcs_parent_categories();

	return array_filter(
		nwcs_pool_categories(),
		static fn( array $term, string $slug ): bool => 0 === (int) $term['count'] && ! $term['placement'] && ! isset( $tops[ $slug ] ),
		ARRAY_FILTER_USE_BOTH
	);
}

add_action( 'admin_post_nwcs_category_delete', 'nwcs_handle_category_delete' );
function nwcs_handle_category_delete(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	$slug = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	check_admin_referer( 'nwcs_category_delete_' . $slug );

	$term = nwcs_pool_categories()[ $slug ] ?? null;

	if ( ! $term ) {
		wp_safe_redirect( nwcs_pool_categories_url() );
		exit;
	}

	if ( isset( nwcs_parent_categories()[ $slug ] ) ) {
		wp_safe_redirect( nwcs_pool_categories_url( array( 'kategori' => $slug, 'sonuc' => 'sil_ust' ) ) );
		exit;
	}

	// Once yerlesimler kaldirilir (site secimleri yerlesim kuralina gore), sonra terim.
	// Renk gruplari ve kategori sorulari terim meta'siyla birlikte silinir; geri alma icin kayitta durur.
	$products = nwcs_category_product_ids( $slug );
	$colors   = nwcs_color_groups( $slug );
	$faq      = nwcs_cat_faq_all( $slug );
	$result   = nwcs_placement_update( $slug, array_fill_keys( array_keys( nwcs_catalog_sites() ), null ) );

	switch_to_blog( nwcs_pool_blog_id() );
	wp_delete_term( (int) $term['id'], NWCS_PRODUCT_TAX );
	restore_current_blog();

	nwcs_pool_flush_cache();

	$facts = array( sprintf( '%d ürün kategoriden çıktı', count( $products ) ) );

	foreach ( $result['sites'] as $blog_id => $row ) {
		$facts[] = sprintf( '%s: %d ürün seçimden çıktı', nwcs_catalog_sites()[ $blog_id ]['label'] ?? $blog_id, count( $row['removed'] ) );
	}

	nwcs_history_push(
		array(
			'kind'     => 'kategori',
			'slug'     => $slug,
			'category' => (string) $term['name'],
			'name'     => (string) $term['name'],
			'before'   => $result['before'] ?: $term['placement'],
			'products' => $products,
			'colors'   => $colors,
			'faq'      => $faq,
			'sites'    => $result['sites'],
			'facts'    => $facts,
			'complete' => true,
		)
	);

	wp_safe_redirect( nwcs_pool_categories_url( array( 'sonuc' => 'silindi' ) ) );
	exit;
}
