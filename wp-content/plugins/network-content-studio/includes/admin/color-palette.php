<?php
/**
 * Ürün Havuzu -> Renkler: renk listesi.
 *
 * Renk grupları'ndaki (Kategoriler -> kategori) uye satirinin renk secim
 * listesi buradan gelir. Veri: ag geneli site ayari 'nwcs_color_palette' =
 * sirali [ { id, name, hex } ] (includes/color-groups.php). Ayar yoksa ilk 13
 * renk; ilk kayitta yazilir.
 *
 *   - satir: renk yuvarlagi, ad, ton (renk secici), kac urunde kullanildigi
 *     ("6 üründe": renk adi Turkce harf ve buyuk/kucuk harf farki gozetilmeden
 *     eslesen grup uyeleri), ↑ ↓ ve "Sil"; "+ Renk ekle" ve "Kaydet".
 *   - kurallar: ad zorunlu; adlar tekil (nwcs_search_fold); ton gecerli
 *     (nwcs_color_hex); en fazla NWCS_COLOR_PALETTE_MAX renk. Hata varsa hicbir
 *     sey yazilmaz, yazilanlar bir kez geri gelir (NWCS_PALETTE_DRAFT).
 *   - uyeler kendi adini ve tonunu saklar: renk silmek gruplari bozmaz. Kayitli
 *     bir rengin adi ya da tonu degisir ve o rengi kullanan uye varsa once onay
 *     sorulur (NWCS_PALETTE_PENDING): her renk icin etkilenen urunler (kod, ad,
 *     kategori) ve "Bu rengi kullanan N ürün de güncellensin" (isaretli gelir).
 *     Isaretli renklerde her havuz kategorisinin '_nwcs_color_groups' meta'sinda
 *     uyenin tonu, ad degistiyse adi da yazilir. Etkilenen uye yoksa dogrudan kaydedilir.
 *
 * JavaScript gerekmez: ↑ ↓, "Sil" ve "+ Renk ekle" formu o haliyle kaydeder;
 * formun sonunda bos bir "Yeni renk" satiri durur. JS (assets/color-palette.js)
 * satiri yerinde tasir, siler ve ekler; kayit "Kaydet" ile yapilir.
 *
 * Her kayit "Son işlemler"e 'palet' kaydi birakir: onceki ve sonraki liste ve
 * degisen uyeler (onceki ve sonraki ad/ton). Geri alma listeyi ve uyeleri eski
 * haline getirir; o uyeler sonradan degistiyse hicbir seye dokunmaz. Ayni
 * kullanicinin art arda kayitlari NWCS_COLOR_MERGE icinde tek kayitta birlesir
 * (renk gruplariyla ayni kural). Uye degisince havuz onbellegi ve sitelerin
 * sayfa onbellegi temizlenir (nwcs_pool_flush_cache).
 *
 * Renk grupları'ndan da eklenir: "Özel renk" secili uye satirinda "Bu rengi
 * listeye kaydet" (nwcs_palette_add_from_group).
 */

defined( 'ABSPATH' ) || exit;

const NWCS_PALETTE_SLUG    = 'nwcs-pool-colors';
const NWCS_PALETTE_KIND    = 'palet';
const NWCS_PALETTE_DRAFT   = 'nwcs_palette_draft_';
const NWCS_PALETTE_PENDING = 'nwcs_palette_pending_';
const NWCS_PALETTE_NOTE    = 'nwcs_palette_note_';

// Urun Havuzu sirasi: Urunler, Kategoriler (12), Renkler (13), Urun aciklamalari (16), Detay basliklari (17).
add_action( 'network_admin_menu', 'nwcs_register_palette_menu', 13 );
function nwcs_register_palette_menu(): void {
	add_submenu_page( NWCS_POOL_SLUG, 'Renkler', 'Renkler', NWCS_CAPABILITY, NWCS_PALETTE_SLUG, 'nwcs_render_palette' );
}

/**
 * Renkler sayfasinin adresi.
 */
function nwcs_palette_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => NWCS_PALETTE_SLUG ), $args ), network_admin_url( 'admin.php' ) );
}

add_action( 'admin_enqueue_scripts', 'nwcs_palette_admin_assets' );
function nwcs_palette_admin_assets( string $hook ): void {
	// Stil color-groups.css'te (Urun Havuzu sayfalarinin hepsinde yuklenir).
	if ( ! str_contains( $hook, NWCS_PALETTE_SLUG ) ) {
		return;
	}

	// Surum + dosya zamani: dosya degisince tarayici eski kopyayi tutmasin.
	$ver = static fn( string $file ): string => NWCS_VERSION . '.' . (int) filemtime( NWCS_DIR . $file );

	wp_enqueue_script( 'nwcs-color-palette', NWCS_URL . 'assets/color-palette.js', array(), $ver( 'assets/color-palette.js' ), true );
}

/* ====================================================================== *
 * Yardimcilar
 * ====================================================================== */

/**
 * Renklerin kullanimi: katlanmis renk adi => [ urun_id => { slug, group,
 * product_id } ]. Havuzun butun renk gruplari (nwcs_color_map); adi bos uye
 * sayilmaz.
 *
 * @return array<string, array<int, array{slug:string, group:string, product_id:int}>>
 */
function nwcs_palette_usage(): array {
	$out = array();

	foreach ( nwcs_color_map()['groups'] as $group ) {
		foreach ( $group['members'] as $member ) {
			$key = nwcs_search_fold( trim( (string) $member['color_name'] ) );
			$id  = (int) $member['product_id'];

			if ( '' !== $key ) {
				$out[ $key ][ $id ] ??= array(
					'slug'       => (string) $group['slug'],
					'group'      => (string) $group['id'],
					'product_id' => $id,
				);
			}
		}
	}

	return $out;
}

/**
 * Adi verilen rengi kullanan uye sayisi.
 */
function nwcs_palette_used( string $name, ?array $usage = null ): int {
	$usage = $usage ?? nwcs_palette_usage();

	return count( $usage[ nwcs_search_fold( trim( $name ) ) ] ?? array() );
}

/**
 * "6 üründe" / "Kullanılmıyor".
 */
function nwcs_palette_used_text( int $count ): string {
	return $count ? sprintf( '%d üründe', $count ) : 'Kullanılmıyor';
}

/**
 * Iki liste arasinda adi ya da tonu degisen kayitli renkler (kimlige gore):
 * id => { old_name, old_hex, name, hex }.
 *
 * @return array<string, array{old_name:string, old_hex:string, name:string, hex:string}>
 */
function nwcs_palette_changes( array $old, array $new ): array {
	$was = array_column( $old, null, 'id' );
	$out = array();

	foreach ( $new as $color ) {
		$before = $was[ $color['id'] ] ?? null;

		if ( $before && ( $before['name'] !== $color['name'] || $before['hex'] !== $color['hex'] ) ) {
			$out[ $color['id'] ] = array(
				'old_name' => $before['name'],
				'old_hex'  => $before['hex'],
				'name'     => $color['name'],
				'hex'      => $color['hex'],
			);
		}
	}

	return $out;
}

/**
 * Iki liste arasindaki degisiklikler, "Son işlemler" satiri icin: eklenen,
 * silinen, adi ve tonu degisen renkler, sira, guncellenen urun sayisi.
 *
 * @return string[]
 */
function nwcs_palette_facts( array $old, array $new, int $members = 0 ): array {
	$was     = array_column( $old, null, 'id' );
	$now     = array_column( $new, null, 'id' );
	$added   = array_values( array_diff_key( $now, $was ) );
	$removed = array_values( array_diff_key( $was, $now ) );
	$renamed = array();
	$toned   = array();
	$facts   = array();

	foreach ( $now as $id => $color ) {
		if ( ! isset( $was[ $id ] ) ) {
			continue;
		}

		if ( $was[ $id ]['name'] !== $color['name'] ) {
			$renamed[] = array( $was[ $id ]['name'], $color['name'] );
		}

		if ( $was[ $id ]['hex'] !== $color['hex'] ) {
			$toned[] = $color['name'];
		}
	}

	if ( $added ) {
		$facts[] = 1 === count( $added ) ? sprintf( '“%s” eklendi', $added[0]['name'] ) : sprintf( '%d renk eklendi', count( $added ) );
	}

	if ( $removed ) {
		$facts[] = 1 === count( $removed ) ? sprintf( '“%s” silindi', $removed[0]['name'] ) : sprintf( '%d renk silindi', count( $removed ) );
	}

	if ( $renamed ) {
		$facts[] = 1 === count( $renamed ) ? sprintf( '“%s” adı “%s” oldu', $renamed[0][0], $renamed[0][1] ) : sprintf( '%d rengin adı değişti', count( $renamed ) );
	}

	if ( $toned ) {
		$facts[] = 1 === count( $toned ) ? sprintf( '“%s” tonu değişti', $toned[0] ) : sprintf( '%d rengin tonu değişti', count( $toned ) );
	}

	$kept_old = array_values( array_intersect( array_keys( $was ), array_keys( $now ) ) );
	$kept_new = array_values( array_intersect( array_keys( $now ), array_keys( $was ) ) );

	if ( $kept_old !== $kept_new ) {
		$facts[] = 'sıra değişti';
	}

	if ( $members ) {
		$facts[] = sprintf( '%d ürünün rengi güncellendi', $members );
	}

	return $facts;
}

/**
 * Urunun panel satiri (onay sayfasi): kod, ad, kategori adi. Coptaki ya da
 * silinmis urunde ad ve kod yazinin kendisinden.
 *
 * @return array{code:string, title:string, category:string}
 */
function nwcs_palette_product( int $id, string $slug ): array {
	$product = nwcs_pool_products()[ $id ] ?? null;
	$code    = $product ? (string) $product['code'] : '';
	$title   = $product ? (string) $product['title'] : '';

	if ( ! $product ) {
		switch_to_blog( nwcs_pool_blog_id() );
		$post  = get_post( $id );
		$code  = $post ? (string) get_post_meta( $id, '_nwcs_code', true ) : '';
		$title = $post ? (string) $post->post_title : '';
		restore_current_blog();
	}

	return array(
		'code'     => $code,
		'title'    => '' !== $title ? $title : sprintf( 'Ürün #%d', $id ),
		'category' => (string) ( nwcs_pool_categories()[ $slug ]['name'] ?? $slug ),
	);
}

/**
 * Uyelere renk degisikliklerini yazar. $changes: eski adin katlanmis hali =>
 * { name: yeni ad (ad degismediyse bos), hex: yeni ton }. Eslesme uyenin
 * kayitli adiyla (tek gecis: zincirleme ad degisikligi birbirini etkilemez).
 * Her havuz kategorisinin '_nwcs_color_groups' meta'si; degisen varsa havuz ve
 * site onbellegi temizlenir.
 *
 * @param array<string, array{name:string, hex:string}> $changes
 * @return array<int, array{slug:string, group:string, product_id:int, before:array, after:array}>
 */
function nwcs_palette_propagate( array $changes ): array {
	$log = array();

	if ( ! $changes ) {
		return $log;
	}

	switch_to_blog( nwcs_pool_blog_id() );

	$terms = get_terms(
		array(
			'taxonomy'   => NWCS_PRODUCT_TAX,
			'hide_empty' => false,
			'meta_key'   => NWCS_COLOR_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$groups = nwcs_color_clean( get_term_meta( (int) $term->term_id, NWCS_COLOR_META, true ) );
		$dirty  = false;

		foreach ( $groups as $g => $group ) {
			foreach ( $group['members'] as $m => $member ) {
				$change = $changes[ nwcs_search_fold( trim( (string) $member['color_name'] ) ) ] ?? null;

				if ( ! $change ) {
					continue;
				}

				$after = array(
					'color_name' => '' !== $change['name'] ? $change['name'] : $member['color_name'],
					'color_hex'  => $change['hex'],
				);

				if ( $after['color_name'] === $member['color_name'] && $after['color_hex'] === $member['color_hex'] ) {
					continue;
				}

				$log[] = array(
					'slug'       => (string) $term->slug,
					'group'      => $group['id'],
					'product_id' => (int) $member['product_id'],
					'before'     => array(
						'color_name' => $member['color_name'],
						'color_hex'  => $member['color_hex'],
					),
					'after'      => $after,
				);

				$groups[ $g ]['members'][ $m ] = array_merge( $member, $after );
				$dirty                         = true;
			}
		}

		if ( $dirty ) {
			update_term_meta( (int) $term->term_id, NWCS_COLOR_META, $groups );
		}
	}

	restore_current_blog();

	if ( $log ) {
		nwcs_pool_flush_cache();
	}

	return $log;
}

/**
 * Iki uye degisikligi listesini birlestirir (ayni kategori ve urun: ilkinin
 * "onceki"si, sonuncunun "sonraki"si). Onceki ve sonraki hali ayni kalan atilir.
 *
 * @return array<int, array>
 */
function nwcs_palette_merge_members( array $first, array $next ): array {
	$out = array();

	foreach ( array_merge( $first, $next ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$key = (string) ( $row['slug'] ?? '' ) . '|' . (int) ( $row['product_id'] ?? 0 );

		if ( isset( $out[ $key ] ) ) {
			$out[ $key ]['after'] = $row['after'];
		} else {
			$out[ $key ] = $row;
		}
	}

	return array_values( array_filter( $out, static fn( array $row ): bool => $row['before'] !== $row['after'] ) );
}

/**
 * Listeyi yazar ve "Son işlemler"e kaydeder. $members: bu kayitta degisen uyeler
 * (nwcs_palette_propagate). En ustteki kayit ayni kullanicinin renk listesi
 * kaydiysa ve NWCS_COLOR_MERGE icindeyse ona eklenir: "onceki" hal korunur,
 * "sonraki", uyeler ve ozet guncellenir. Birlesen kayitlar listeyi ve uyeleri
 * oturumun basindaki haline geri getirdiyse kayit kalkar (liste ilk kez
 * yazilmissa ayar da silinir: ilk 13 renge doner).
 *
 * $source: ozetin sonuna eklenen kaynak ("Renk grupları’ndan").
 */
function nwcs_palette_commit( array $before, array $after, array $members, string $action = 'save', string $source = '' ): void {
	$seeded = false === get_site_option( NWCS_COLOR_PALETTE, false );

	update_site_option( NWCS_COLOR_PALETTE, nwcs_color_palette_clean( $after ) );

	$now  = nwcs_color_palette();
	$user = get_current_user_id();
	$top  = nwcs_history_top();
	$tail = '' !== $source ? ' (' . $source . ')' : '';

	if ( $top
		&& NWCS_PALETTE_KIND === ( $top['kind'] ?? '' )
		&& $user === (int) ( $top['user'] ?? 0 )
		&& time() - (int) ( $top['time'] ?? 0 ) <= NWCS_COLOR_MERGE ) {
		$first  = nwcs_color_palette_clean( $top['before'] ?? array() );
		$merged = nwcs_palette_merge_members( (array) ( $top['members'] ?? array() ), $members );

		if ( $first === $now && ! $merged ) {
			if ( ! empty( $top['seeded'] ) ) {
				delete_site_option( NWCS_COLOR_PALETTE );
			}

			nwcs_history_drop( (string) $top['id'] );

			return;
		}

		$edits = (int) ( $top['edits'] ?? 1 ) + 1;
		$facts = nwcs_palette_facts( $first, $now, count( $merged ) ) ?: array( 'renk listesi güncellendi' );

		$top['after']   = $now;
		$top['members'] = $merged;
		$top['edits']   = $edits;
		$top['facts']   = array_merge( $facts, array( sprintf( '%d düzenleme', $edits ) ) );
		$top['time']    = time();

		nwcs_history_save( $top );

		return;
	}

	$facts = nwcs_palette_facts( $before, $now, count( $members ) ) ?: array( 'renk listesi güncellendi' );

	$facts[ count( $facts ) - 1 ] .= $tail;

	nwcs_history_push(
		array(
			'kind'     => NWCS_PALETTE_KIND,
			'action'   => $action,
			'user'     => $user,
			'edits'    => 1,
			'seeded'   => $seeded,
			'before'   => nwcs_color_palette_clean( $before ),
			'after'    => $now,
			'members'  => $members,
			'facts'    => $facts,
			'complete' => true,
		)
	);
}

/**
 * Renk grupları'ndan "Bu rengi listeye kaydet" (Özel renk). Ad listede yoksa
 * sona eklenir ve "Son işlemler"e yazilir. Ad listede ayni tonla varsa bir
 * sey yapilmaz; baska tonla varsa liste degismez, ad 'other'da doner (grup
 * bolumu uyarir). Liste doluysa ad 'full'da doner.
 *
 * @param array<int, array{name:string, hex:string}> $colors
 * @return array{added:string[], other:string[], full:string[]}
 */
function nwcs_palette_add_from_group( array $colors ): array {
	$before = nwcs_color_palette();
	$after  = $before;
	$out    = array( 'added' => array(), 'other' => array(), 'full' => array() );

	foreach ( $colors as $color ) {
		$name = mb_substr( nwcs_clean_text( $color['name'] ?? '' ), 0, NWCS_COLOR_NAME_MAX );
		$hex  = nwcs_color_hex( (string) ( $color['hex'] ?? '' ) );

		if ( '' === $name || '' === $hex ) {
			continue;
		}

		$found = nwcs_color_palette_find( $name, $after );

		if ( $found ) {
			if ( $found['hex'] !== $hex && ! in_array( $found['name'], $out['other'], true ) ) {
				$out['other'][] = $found['name'];
			}

			continue;
		}

		if ( count( $after ) >= NWCS_COLOR_PALETTE_MAX ) {
			$out['full'][] = $name;
			continue;
		}

		$after[]        = array(
			'id'   => nwcs_color_palette_new_id(),
			'name' => $name,
			'hex'  => $hex,
		);
		$out['added'][] = $name;
	}

	if ( $out['added'] ) {
		nwcs_palette_commit( $before, $after, array(), 'add', 'Renk grupları’ndan' );
	}

	return $out;
}

/**
 * Renk grupları bolumundeki sonuc ("listeye eklendi", "başka bir tonla
 * kayıtlı", "liste dolu"); bir kez gosterilir.
 */
function nwcs_render_palette_note( string $slug ): void {
	$key  = NWCS_PALETTE_NOTE . get_current_user_id();
	$note = get_site_transient( $key );

	if ( ! is_array( $note ) || ( $note['slug'] ?? '' ) !== $slug ) {
		return;
	}

	delete_site_transient( $key );

	$quote = static fn( string $name ): string => '“' . $name . '”';
	$link  = sprintf( '<a href="%s">%s</a>', esc_url( nwcs_palette_url() ), esc_html( 'Renkler ekranından' ) );

	if ( ! empty( $note['added'] ) ) {
		printf(
			'<div class="notice notice-success inline nwcs-colors__notice" role="status"><p>%s</p></div>',
			esc_html( sprintf( '%s renk listesine eklendi; artık her grupta listeden seçilebilir.', nwcs_color_join( array_map( $quote, (array) $note['added'] ) ) ) )
		);
	}

	foreach ( (array) ( $note['other'] ?? array() ) as $name ) {
		printf(
			'<div class="notice notice-warning inline nwcs-colors__notice" role="status"><p>%s</p></div>',
			// Metin: "‘<ad>’ listede başka bir tonla kayıtlı; listeyi Renkler ekranından düzenleyin."
			esc_html( sprintf( '‘%s’ listede başka bir tonla kayıtlı; listeyi ', $name ) ) . $link . esc_html( ' düzenleyin.' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link yukarida kacirildi.
		);
	}

	if ( ! empty( $note['full'] ) ) {
		printf(
			'<div class="notice notice-warning inline nwcs-colors__notice" role="status"><p>%s</p></div>',
			esc_html( sprintf( '%s listeye eklenmedi: listede en fazla %d renk olabilir. Kullanmadığınız renkleri Renkler ekranından silin.', nwcs_color_join( array_map( $quote, (array) $note['full'] ) ), NWCS_COLOR_PALETTE_MAX ) )
		);
	}
}

/* ====================================================================== *
 * Ekran
 * ====================================================================== */

function nwcs_render_palette(): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ) );
	}

	$user = get_current_user_id();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gorunum secimi ve bildirim.
	$result  = isset( $_GET['sonuc'] ) ? sanitize_key( wp_unslash( $_GET['sonuc'] ) ) : '';
	$count   = isset( $_GET['urun'] ) ? absint( $_GET['urun'] ) : 0;
	$confirm = isset( $_GET['onay'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$pending = get_site_transient( NWCS_PALETTE_PENDING . $user );
	$pending = is_array( $pending ) && isset( $pending['base'], $pending['after'] ) ? $pending : null;

	if ( $confirm && ! $pending ) {
		$result = 'suresi';
	}

	// Onaydan vazgecilip listeye donulduyse bekleyen kayit kalmasin.
	if ( ! $confirm && $pending ) {
		delete_site_transient( NWCS_PALETTE_PENDING . $user );
		$pending = null;
	}

	// Kaydedilmeyen form: yazilanlar bir kez geri gelir.
	$draft = get_site_transient( NWCS_PALETTE_DRAFT . $user );
	$draft = is_array( $draft ) && isset( $draft['rows'] ) ? $draft : array();

	if ( $draft ) {
		delete_site_transient( NWCS_PALETTE_DRAFT . $user );
	}
	?>
	<div class="wrap nwcs-wrap nwcs-wrap--pool nwcs-palette">
		<header class="nwcs-bar">
			<div class="nwcs-bar__brand">
				<button type="button" class="nwcs-bar__mark" data-nwcs-menu aria-label="Yönetim menüsünü aç/kapat" title="Yönetim menüsünü aç/kapat"></button>
				<h1>Ürün Havuzu › Renkler</h1>
			</div>
			<div class="nwcs-bar__tools">
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_categories_url() ); ?>">Kategoriler</a>
				<a class="nwcs-linkout" href="<?php echo esc_url( nwcs_pool_url() ); ?>">Ürünler</a>
			</div>
		</header>
		<?php // WordPress bildirimleri bu isaretin altina tasir (ust seridin icine degil). ?>
		<hr class="wp-header-end" />

		<?php nwcs_render_history_result(); ?>
		<?php nwcs_render_cache_note(); ?>

		<?php
		if ( $confirm && $pending ) {
			nwcs_render_palette_confirm( $pending );
			echo '</div>';

			return;
		}
		?>

		<?php nwcs_render_palette_notice( $result, $count, $draft ); ?>

		<?php nwcs_render_palette_form( $draft ); ?>

		<?php nwcs_render_history_card( nwcs_palette_url() ); ?>
	</div>
	<?php
}

/**
 * Islem sonucu (kartin ustunde).
 */
function nwcs_render_palette_notice( string $result, int $count, array $draft ): void {
	$map = array(
		'kaydedildi' => array( 'success', 'Renk listesi kaydedildi. Renk grupları’ndaki seçim listesi hemen güncellenir; yanlışsa “Son işlemler”den geri alabilirsiniz.' ),
		'eklendi'    => array( 'success', 'Renk eklendi. Renk grupları’nda artık listeden seçilebilir; yanlışsa “Son işlemler”den geri alabilirsiniz.' ),
		'silindi'    => array( 'success', 'Renk listeden silindi. Bu rengi kullanan ürünler rengini korur; renk grupları bozulmaz.' ),
		'ayni'       => array( 'info', 'Renk listesi değişmedi.' ),
		'duzenle'    => array( 'info', 'Kaydedilmedi. Değişiklikleriniz aşağıda duruyor; düzenleyip yeniden kaydedin.' ),
		'degisti'    => array( 'error', 'Kaydedilmedi: renk listesi bu arada başka bir sekmede değişti. Listeye yeniden bakıp değişikliği tekrar yapın.' ),
		'suresi'     => array( 'error', 'Onay sayfasının süresi doldu; hiçbir şey kaydedilmedi. Değişikliği yeniden yapıp kaydedin.' ),
	);

	if ( 'hata' === $result && $draft ) {
		$lines = nwcs_palette_error_lines( $draft );
		?>
		<div class="notice notice-error nwcs-palette__notice" role="alert">
			<p><strong>Kaydedilmedi.</strong> Yazdıklarınız aşağıda duruyor; hiçbir şey değişmedi.</p>
			<?php if ( $lines ) : ?>
				<ul>
					<?php foreach ( $lines as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php

		return;
	}

	if ( ! isset( $map[ $result ] ) ) {
		return;
	}

	$text = $map[ $result ][1];

	if ( $count && 'success' === $map[ $result ][0] ) {
		$text .= sprintf( ' %d ürünün rengi de güncellendi; sitelerde hemen görünür.', $count );
	}

	printf(
		'<div class="notice notice-%1$s nwcs-palette__notice" role="%3$s"><p>%2$s</p></div>',
		esc_attr( $map[ $result ][0] ),
		esc_html( $text ),
		'error' === $map[ $result ][0] ? 'alert' : 'status'
	);
}

/**
 * Taslaktaki hatalarin acik metni (satir numarasiyla).
 *
 * @return string[]
 */
function nwcs_palette_error_lines( array $draft ): array {
	$rows  = array_values( (array) ( $draft['rows'] ?? array() ) );
	$lines = array();

	foreach ( (array) ( $draft['errors'] ?? array() ) as $index => $error ) {
		$number = (int) $index + 1;
		$row    = $rows[ $index ] ?? array();

		switch ( $error[0] ?? '' ) {
			case 'adsiz':
				$lines[] = sprintf( '%d. satır: renk adı boş. Adı yazın ya da satırı silin.', $number );
				break;

			case 'ayni':
				$lines[] = sprintf( '%1$d. satır: “%2$s” adı %3$d. satırda da var (büyük/küçük harf ve Türkçe harf farkı sayılmaz). Adı değiştirin ya da satırı silin.', $number, (string) ( $row['name'] ?? '' ), (int) ( $error[1] ?? 0 ) + 1 );
				break;

			case 'hex':
				$lines[] = sprintf( '%1$d. satır: “%2$s” geçerli bir renk kodu değil. Renk seçiciden bir ton seçin ya da #383e42 gibi yazın.', $number, (string) ( $row['raw'] ?? '' ) );
				break;
		}
	}

	foreach ( (array) ( $draft['global'] ?? array() ) as $code ) {
		if ( 'fazla' === $code ) {
			$lines[] = sprintf( 'Listede en fazla %1$d renk olabilir; formda %2$d renk var. Kullanmadığınız renkleri silip yeniden kaydedin.', NWCS_COLOR_PALETTE_MAX, count( $rows ) );
		} elseif ( 'yeni_bos' === $code ) {
			$lines[] = 'Yeni renk eklenmedi: en alttaki “Yeni renk” satırına rengin adını yazın, tonunu seçin, sonra “+ Renk ekle”ye basın.';
		}
	}

	return $lines;
}

/**
 * Listenin formu. $draft varsa satirlar kullanicinin yazdiklariyla dolar,
 * hatali satirlar isaretlenir.
 */
function nwcs_render_palette_form( array $draft ): void {
	$saved  = nwcs_color_palette();
	$by_id  = array_column( $saved, null, 'id' );
	$usage  = nwcs_palette_usage();
	$errors = (array) ( $draft['errors'] ?? array() );
	$gone   = array_values( array_intersect( array_map( 'strval', (array) ( $draft['gone'] ?? array() ) ), array_keys( $by_id ) ) );
	$rows   = array();

	if ( $draft ) {
		foreach ( array_values( (array) $draft['rows'] ) as $index => $row ) {
			$id     = isset( $by_id[ (string) ( $row['id'] ?? '' ) ] ) ? (string) $row['id'] : '';
			$rows[] = array(
				// Yeni satirin anahtari "n1", "n2"... (sunucu kimligi kayitta verir).
				'key'  => '' !== $id ? $id : 'n' . ( $index + 1 ),
				'id'   => $id,
				'name' => (string) ( $row['name'] ?? '' ),
				'hex'  => nwcs_color_hex( (string) ( $row['hex'] ?? '' ) ),
				'raw'  => (string) ( $row['raw'] ?? '' ),
			);
		}
	} else {
		foreach ( $saved as $color ) {
			$rows[] = array(
				'key'  => $color['id'],
				'id'   => $color['id'],
				'name' => $color['name'],
				'hex'  => $color['hex'],
				'raw'  => $color['hex'],
			);
		}
	}

	$total = count( $rows );
	?>
	<section class="nwcs-pool__card nwcs-palette__card" id="nwcs-renkler" aria-labelledby="nwcs-renkler-title">
		<h2 class="nwcs-pool__title" id="nwcs-renkler-title">Renk listesi <span><?php echo esc_html( sprintf( '%d renk', count( $saved ) ) ); ?></span></h2>
		<p class="nwcs-palette__lead">
			Renk grupları’nda her ürünün renk yuvarlağı bu listeden seçilir (Kategoriler › kategori › Renk grupları). Bir rengi bir kez ekleyin, her grupta tek tıkla seçin; ürün adında listedeki bir renk geçiyorsa grup kurulurken kendiliğinden dolar.
			Ürünler kendi renk adını ve tonunu saklar: listeden renk silmek grupları bozmaz. Kullanılan bir rengin adını ya da tonunu değiştirirseniz, kaydetmeden önce o ürünlerin de güncellenip güncellenmeyeceği sorulur.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-palette__form" data-nwcs-palette
			data-nwcs-palette-max="<?php echo (int) NWCS_COLOR_PALETTE_MAX; ?>">
			<input type="hidden" name="action" value="nwcs_palette_save" />
			<?php wp_nonce_field( 'nwcs_palette_save' ); ?>
			<?php // Enter tusu bu dugmeyi kullanir (formdaki ilk gonder dugmesi ↑ olmasin). ?>
			<button type="submit" name="kaydet" value="1" class="nwcs-palette__default" tabindex="-1" aria-hidden="true">Kaydet</button>
			<?php foreach ( $gone as $id ) : ?>
				<input type="hidden" name="silinen[]" value="<?php echo esc_attr( $id ); ?>" />
			<?php endforeach; ?>

			<div class="nwcs-palette__scroll">
				<table class="nwcs-table nwcs-palette__table">
					<thead>
						<tr>
							<th scope="col">Sıra</th>
							<th scope="col"><span class="screen-reader-text">Görünüm</span></th>
							<th scope="col">Renk adı</th>
							<th scope="col">Ton</th>
							<th scope="col">Kullanım</th>
							<th scope="col"><span class="screen-reader-text">Sil</span></th>
						</tr>
					</thead>
					<tbody data-nwcs-palette-rows>
						<?php foreach ( $rows as $index => $row ) : ?>
							<?php
							// Kayitli rengin kullanimi kayitli adiyla; yeni satirinki yazilan adla.
							$used = nwcs_palette_used( '' !== $row['id'] ? $by_id[ $row['id'] ]['name'] : $row['name'], $usage );
							nwcs_render_palette_row( $row, $index, $total, $used, (array) ( $errors[ $index ] ?? array() ) );
							?>
						<?php endforeach; ?>
						<?php
						nwcs_render_palette_row(
							array( 'key' => 'yeni', 'id' => '', 'name' => '', 'hex' => '#ffffff', 'raw' => '#ffffff' ),
							$total,
							$total + 1,
							0,
							array(),
							'blank'
						);
						?>
					</tbody>
				</table>
			</div>

			<template data-nwcs-palette-template>
				<?php nwcs_render_palette_row( array( 'key' => '__KEY__', 'id' => '', 'name' => '', 'hex' => '#ffffff', 'raw' => '#ffffff' ), 0, 0, 0, array(), 'template' ); ?>
			</template>

			<p class="nwcs-palette__status" data-nwcs-palette-status aria-live="polite"></p>
			<p class="nwcs-palette__err" data-nwcs-palette-error role="alert" hidden></p>

			<div class="nwcs-actions nwcs-palette__actions">
				<button type="submit" name="ekle" value="1" class="button" data-nwcs-palette-add>+ Renk ekle</button>
				<button type="submit" name="kaydet" value="1" class="button button-primary">Kaydet</button>
				<span class="nwcs-hint nwcs-palette__count" data-nwcs-palette-count><?php echo esc_html( sprintf( '%d / %d renk', $total, NWCS_COLOR_PALETTE_MAX ) ); ?></span>
			</div>
		</form>
	</section>
	<?php
}

/**
 * Tek renk satiri. $mode: '' (kayitli ya da taslaktaki satir), 'blank'
 * (formun sonundaki bos "Yeni renk": JS'siz ekleme), 'template' (JS'in
 * ekledigi satir: dugmeler yalnizca JS ile calisir).
 *
 * @param array{key:string, id:string, name:string, hex:string, raw:string} $row
 * @param array{0?:string, 1?:int}                                         $error
 */
function nwcs_render_palette_row( array $row, int $position, int $total, int $used, array $error, string $mode = '' ): void {
	$blank  = 'blank' === $mode;
	$type   = 'template' === $mode ? 'button' : 'submit';
	$key    = $row['key'];
	$field  = 'nwcs-palet-' . $key;
	$hex    = $row['hex'];
	$number = $position + 1;
	$label  = '' !== $row['name'] ? $row['name'] : ( $blank ? 'Yeni renk' : sprintf( '%d. renk', $number ) );
	$code   = (string) ( $error[0] ?? '' );
	$texts  = array(
		'adsiz' => 'Renk adı yazın ya da satırı silin.',
		'ayni'  => sprintf( 'Bu ad %d. satırda da var. Adı değiştirin.', (int) ( $error[1] ?? 0 ) + 1 ),
		'hex'   => 'Geçerli bir ton seçin.',
	);
	?>
	<tr class="nwcs-palette__row<?php echo $blank ? ' is-new' : ''; ?><?php echo '' !== $code ? ' is-invalid' : ''; ?>" data-nwcs-palette-row
		<?php echo '' !== $row['id'] ? 'data-nwcs-palette-id="' . esc_attr( $row['id'] ) . '"' : ''; ?><?php echo $blank ? ' data-nwcs-palette-new' : ''; ?>>
		<td class="nwcs-palette__order">
			<span class="nwcs-palette__n" data-nwcs-palette-n aria-hidden="true"><?php echo $blank ? '+' : (int) $number; ?></span>
			<?php if ( ! $blank ) : ?>
				<button type="<?php echo esc_attr( $type ); ?>" name="tasi" value="<?php echo esc_attr( $key . ':up' ); ?>" class="nwcs-move" data-nwcs-palette-move="up" aria-label="<?php echo esc_attr( 'Yukarı taşı: ' . $label ); ?>"<?php disabled( 0 === $position ); ?>>↑</button>
				<button type="<?php echo esc_attr( $type ); ?>" name="tasi" value="<?php echo esc_attr( $key . ':down' ); ?>" class="nwcs-move" data-nwcs-palette-move="down" aria-label="<?php echo esc_attr( 'Aşağı taşı: ' . $label ); ?>"<?php disabled( $total - 1 === $position ); ?>>↓</button>
			<?php endif; ?>
		</td>
		<td class="nwcs-palette__look">
			<span class="nwcs-colors__dot<?php echo nwcs_color_is_light( $hex ) ? ' is-light' : ''; ?><?php echo '' === $hex ? ' is-empty' : ''; ?>" style="<?php echo '' !== $hex ? esc_attr( '--nwcs-dot:' . $hex ) : ''; ?>" data-nwcs-palette-dot aria-hidden="true"></span>
		</td>
		<td class="nwcs-palette__name">
			<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>-ad"><?php echo esc_html( $blank ? 'Yeni rengin adı' : 'Renk adı' ); ?></label>
			<input class="nwcs-input" type="text" id="<?php echo esc_attr( $field ); ?>-ad" name="<?php echo esc_attr( 'renk[' . $key . '][ad]' ); ?>" value="<?php echo esc_attr( $row['name'] ); ?>"
				maxlength="<?php echo (int) NWCS_COLOR_NAME_MAX; ?>" placeholder="<?php echo esc_attr( $blank ? 'Yeni renk adı, örn. Koyu Ceviz' : 'örn. Antrasit' ); ?>" autocomplete="off" data-nwcs-palette-name
				<?php echo '' !== $code && 'hex' !== $code ? 'aria-invalid="true" aria-describedby="' . esc_attr( $field ) . '-hata"' : ''; ?> />
			<?php if ( '' !== $code ) : ?>
				<span class="nwcs-palette__err" id="<?php echo esc_attr( $field ); ?>-hata"><?php echo esc_html( $texts[ $code ] ?? '' ); ?></span>
			<?php endif; ?>
		</td>
		<td class="nwcs-palette__tone">
			<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>-ton"><?php echo esc_html( 'Ton: ' . $label ); ?></label>
			<input class="nwcs-colors__custom" type="color" id="<?php echo esc_attr( $field ); ?>-ton" name="<?php echo esc_attr( 'renk[' . $key . '][hex]' ); ?>" value="<?php echo esc_attr( '' !== $hex ? $hex : $row['raw'] ); ?>" data-nwcs-palette-hex
				<?php echo 'hex' === $code ? 'aria-invalid="true" aria-describedby="' . esc_attr( $field ) . '-hata"' : ''; ?> />
			<code class="nwcs-palette__code" data-nwcs-palette-code><?php echo esc_html( '' !== $hex ? $hex : $row['raw'] ); ?></code>
		</td>
		<td class="nwcs-palette__use<?php echo $used ? '' : ' is-none'; ?>" data-nwcs-palette-use>
			<?php
			if ( 'template' === $mode || ( '' === $row['id'] && ! $blank && ! $used ) ) {
				echo 'Yeni';
			} elseif ( ! $blank ) {
				echo esc_html( nwcs_palette_used_text( $used ) );
			}
			?>
		</td>
		<td class="nwcs-palette__out">
			<?php if ( ! $blank ) : ?>
				<button type="<?php echo esc_attr( $type ); ?>" name="sil" value="<?php echo esc_attr( $key ); ?>" class="button-link nwcs-row__delete" data-nwcs-palette-remove>Sil<span class="screen-reader-text" data-nwcs-palette-sr>: <?php echo esc_html( $label ); ?></span></button>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Onay: adi ya da tonu degisen ve kullanilan renkler, her biri icin
 * etkilenen urunler ve "Bu rengi kullanan N ürün de güncellensin".
 */
function nwcs_render_palette_confirm( array $pending ): void {
	$base    = nwcs_color_palette_clean( $pending['base'] );
	$after   = nwcs_color_palette_clean( $pending['after'] );
	$usage   = nwcs_palette_usage();
	$facts   = nwcs_palette_facts( $base, $after );
	$changes = nwcs_palette_changes( $base, $after );
	$dot     = static function ( string $hex ): string {
		return sprintf(
			'<span class="nwcs-colors__dot%s" style="%s" aria-hidden="true"></span>',
			nwcs_color_is_light( $hex ) ? ' is-light' : '',
			esc_attr( '--nwcs-dot:' . $hex )
		);
	};
	?>
	<div class="nwcs-sync nwcs-palette__confirm" id="nwcs-renkler" role="region" aria-labelledby="nwcs-palet-onay" tabindex="-1">
		<div class="nwcs-sync__head">
			<h2 class="nwcs-sync__title" id="nwcs-palet-onay">Bu renkleri kullanan ürünler de güncellensin mi?</h2>
			<p class="nwcs-sync__lead">
				Adını ya da tonunu değiştirdiğiniz renkler ürünlerde kullanılıyor. Ürünler kendi renk adını ve tonunu saklar:
				işaretli renklerde bu ürünler yeni tona (ad değiştiyse yeni ada) geçer; işareti kaldırdığınız renklerde ürünler eski hâliyle kalır.
				Sitelerde hemen görünür; yanlışsa “Son işlemler”den geri alabilirsiniz.
			</p>
			<?php if ( $facts ) : ?>
				<p class="nwcs-sync__muted nwcs-palette__facts"><?php echo esc_html( 'Listede kaydedilecekler: ' . implode( ', ', $facts ) . '.' ); ?></p>
			<?php endif; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nwcs_palette_confirm" />
			<?php wp_nonce_field( 'nwcs_palette_confirm' ); ?>

			<?php foreach ( $changes as $id => $change ) : ?>
				<?php
				$users = $usage[ nwcs_search_fold( trim( $change['old_name'] ) ) ] ?? array();

				if ( ! $users ) {
					continue;
				}

				$box     = 'nwcs-palet-guncelle-' . $id;
				$renamed = $change['old_name'] !== $change['name'];
				?>
				<section class="nwcs-sync__group nwcs-sync__group--update nwcs-palette__change" aria-labelledby="<?php echo esc_attr( $box ); ?>-baslik">
					<h3 class="nwcs-palette__swap" id="<?php echo esc_attr( $box ); ?>-baslik">
						<?php echo $dot( $change['old_hex'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $dot kacirir. ?>
						<span><?php echo esc_html( '“' . $change['old_name'] . '”' ); ?></span>
						<span class="nwcs-palette__arrow" aria-hidden="true">→</span>
						<span class="screen-reader-text">yeni hâli:</span>
						<?php echo $dot( $change['hex'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $dot kacirir. ?>
						<span><?php echo esc_html( '“' . $change['name'] . '”' ); ?></span>
						<span class="nwcs-palette__codes"><?php echo esc_html( $change['old_hex'] === $change['hex'] ? 'ton aynı (' . $change['hex'] . ')' : $change['old_hex'] . ' → ' . $change['hex'] ); ?></span>
					</h3>
					<label class="nwcs-sync__confirm nwcs-palette__check" for="<?php echo esc_attr( $box ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $box ); ?>" name="<?php echo esc_attr( 'guncelle[' . $id . ']' ); ?>" value="1" checked />
						<?php echo esc_html( sprintf( 'Bu rengi kullanan %d ürün de güncellensin', count( $users ) ) ); ?>
					</label>
					<p class="nwcs-hint nwcs-palette__what">
						<?php
						echo esc_html(
							$renamed
								? sprintf( 'İşaretliyse bu ürünlerin renk adı “%s”, tonu %s olur.', $change['name'], $change['hex'] )
								: sprintf( 'İşaretliyse bu ürünlerin tonu %s olur; renk adları değişmez.', $change['hex'] )
						);
						?>
					</p>
					<ul class="nwcs-sync__list nwcs-palette__products">
						<?php foreach ( $users as $ref ) : ?>
							<?php $info = nwcs_palette_product( (int) $ref['product_id'], (string) $ref['slug'] ); ?>
							<li>
								<?php if ( '' !== $info['code'] ) : ?>
									<span class="nwcs-cat__code"><?php echo esc_html( $info['code'] ); ?></span>
								<?php endif; ?>
								<span class="nwcs-palette__ptitle"><?php echo esc_html( $info['title'] ); ?></span>
								<span class="nwcs-palette__pcat"><?php echo esc_html( $info['category'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>

			<div class="nwcs-sync__actions">
				<button type="submit" class="button button-primary">Listeyi kaydet</button>
				<button type="submit" name="geri" value="1" class="button">Geri dön, düzenle</button>
				<a href="<?php echo esc_url( nwcs_palette_url() ); ?>">Vazgeç<span class="screen-reader-text"> (hiçbir şey kaydedilmez)</span></a>
			</div>
		</form>
	</div>
	<?php
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

/**
 * Yetki ve nonce.
 */
function nwcs_palette_request( string $nonce ): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( $nonce );
}

/**
 * Sonuca yonlendirir.
 */
function nwcs_palette_redirect( string $result, array $args = array() ): void {
	wp_safe_redirect( nwcs_palette_url( array_merge( array( 'sonuc' => $result ), $args ) ) . '#nwcs-renkler' );
	exit;
}

/**
 * Taslagi yazar (yazilanlar bir kez geri gelir) ve hataya yonlendirir.
 */
function nwcs_palette_draft( array $rows, array $errors, array $global, array $gone, string $result = 'hata' ): void {
	set_site_transient(
		NWCS_PALETTE_DRAFT . get_current_user_id(),
		array(
			'rows'   => array_values( $rows ),
			'errors' => $errors,
			'global' => $global,
			'gone'   => array_values( $gone ),
		),
		15 * MINUTE_IN_SECONDS
	);

	nwcs_palette_redirect( $result );
}

add_action( 'admin_post_nwcs_palette_save', 'nwcs_handle_palette_save' );
function nwcs_handle_palette_save(): void {
	nwcs_palette_request( 'nwcs_palette_save' );

	$current = nwcs_color_palette();
	$by_id   = array_column( $current, null, 'id' );
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- alan alan asagida temizlenir.
	$posted = isset( $_POST['renk'] ) && is_array( $_POST['renk'] ) ? wp_unslash( $_POST['renk'] ) : array();
	$gone   = isset( $_POST['silinen'] ) && is_array( $_POST['silinen'] ) ? array_map( 'sanitize_key', array_filter( wp_unslash( $_POST['silinen'] ), 'is_scalar' ) ) : array();
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput
	$delete = isset( $_POST['sil'] ) ? sanitize_key( wp_unslash( $_POST['sil'] ) ) : '';
	$move   = isset( $_POST['tasi'] ) ? sanitize_text_field( wp_unslash( $_POST['tasi'] ) ) : '';
	$adding = ! empty( $_POST['ekle'] );
	$rows   = array();
	$seen   = array();
	$blank  = false;

	if ( '' !== $delete ) {
		$gone[] = $delete;
	}

	// Formdaki sira (tarayici satirlari ekrandaki sirayla gonderir); anahtar kayitli rengin kimligi ya da yeni satirinki.
	foreach ( $posted as $key => $row ) {
		$key = sanitize_key( (string) $key );

		if ( ! is_array( $row ) || '' === $key || isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;

		if ( in_array( $key, $gone, true ) ) {
			continue;
		}

		$name = mb_substr( nwcs_clean_text( $row['ad'] ?? '' ), 0, NWCS_COLOR_NAME_MAX );
		$raw  = is_scalar( $row['hex'] ?? '' ) ? trim( sanitize_text_field( (string) ( $row['hex'] ?? '' ) ) ) : '';
		$id   = isset( $by_id[ $key ] ) ? $key : '';

		// Adi bos yeni satir (formun sonundaki "Yeni renk" gibi) yok sayilir.
		if ( '' === $id && '' === $name ) {
			$blank = $blank || 'yeni' === $key;
			continue;
		}

		$rows[] = array(
			'key'  => $key,
			'id'   => $id,
			'name' => $name,
			'hex'  => nwcs_color_hex( $raw ),
			'raw'  => $raw,
		);
	}

	// Formda olmayan kayitli renkler (baska sekmede eklenmis): silinmediyse sonda kalir.
	foreach ( $current as $color ) {
		if ( ! isset( $seen[ $color['id'] ] ) && ! in_array( $color['id'], $gone, true ) ) {
			$rows[] = array(
				'key'  => $color['id'],
				'id'   => $color['id'],
				'name' => $color['name'],
				'hex'  => $color['hex'],
				'raw'  => $color['hex'],
			);
		}
	}

	// ↑ ↓ (JavaScript yoksa): formdaki degisikliklerle birlikte kaydedilir.
	if ( preg_match( '/^([a-z0-9_-]+):(up|down)$/', $move, $match ) ) {
		$from = array_search( $match[1], array_column( $rows, 'key' ), true );
		$to   = false === $from ? -1 : $from + ( 'up' === $match[2] ? -1 : 1 );

		if ( $to >= 0 && $to < count( $rows ) ) {
			$moved = array_splice( $rows, (int) $from, 1 );
			array_splice( $rows, $to, 0, $moved );
		}
	}

	// Kurallar: ad zorunlu ve tekil, ton gecerli, en fazla NWCS_COLOR_PALETTE_MAX renk.
	$errors = array();
	$names  = array();
	$global = array();

	foreach ( $rows as $index => $row ) {
		if ( '' === $row['name'] ) {
			$errors[ $index ] = array( 'adsiz' );
			continue;
		}

		$fold = nwcs_search_fold( $row['name'] );

		if ( isset( $names[ $fold ] ) ) {
			$errors[ $index ] = array( 'ayni', $names[ $fold ] );
			continue;
		}

		$names[ $fold ] = $index;

		if ( '' === $row['hex'] ) {
			$errors[ $index ] = array( 'hex' );
		}
	}

	if ( count( $rows ) > NWCS_COLOR_PALETTE_MAX ) {
		$global[] = 'fazla';
	}

	// JS'siz "+ Renk ekle" bos yeni satirla: eklenecek bir sey yok.
	if ( $adding && $blank ) {
		$global[] = 'yeni_bos';
	}

	$gone = array_values( array_intersect( $gone, array_keys( $by_id ) ) );

	if ( $errors || $global ) {
		nwcs_palette_draft( $rows, $errors, $global, $gone );
	}

	$after = array();

	foreach ( $rows as $row ) {
		$after[] = array(
			'id'   => '' !== $row['id'] ? $row['id'] : nwcs_color_palette_new_id(),
			'name' => $row['name'],
			'hex'  => $row['hex'],
		);
	}

	$after = nwcs_color_palette_clean( $after );

	if ( $after === $current ) {
		nwcs_palette_redirect( 'ayni' );
	}

	$result = $adding ? 'eklendi' : ( '' !== $delete ? 'silindi' : 'kaydedildi' );
	$usage  = nwcs_palette_usage();

	// Kullanilan bir rengin adi ya da tonu degistiyse once sorulur.
	foreach ( nwcs_palette_changes( $current, $after ) as $change ) {
		if ( nwcs_palette_used( $change['old_name'], $usage ) ) {
			set_site_transient(
				NWCS_PALETTE_PENDING . get_current_user_id(),
				array(
					'base'   => $current,
					'after'  => $after,
					'result' => $result,
				),
				30 * MINUTE_IN_SECONDS
			);

			wp_safe_redirect( nwcs_palette_url( array( 'onay' => 1 ) ) . '#nwcs-renkler' );
			exit;
		}
	}

	nwcs_palette_commit( $current, $after, array() );

	nwcs_palette_redirect( $result );
}

add_action( 'admin_post_nwcs_palette_confirm', 'nwcs_handle_palette_confirm' );
function nwcs_handle_palette_confirm(): void {
	nwcs_palette_request( 'nwcs_palette_confirm' );

	$key     = NWCS_PALETTE_PENDING . get_current_user_id();
	$pending = get_site_transient( $key );

	if ( ! is_array( $pending ) || ! isset( $pending['base'], $pending['after'] ) ) {
		nwcs_palette_redirect( 'suresi' );
	}

	delete_site_transient( $key );

	$base  = nwcs_color_palette_clean( $pending['base'] );
	$after = nwcs_color_palette_clean( $pending['after'] );

	// "Geri dön, düzenle": hicbir sey yazilmaz, yazilanlar formda geri gelir.
	if ( ! empty( $_POST['geri'] ) ) {
		$known = array_column( $base, null, 'id' );
		$rows  = array();

		foreach ( $after as $color ) {
			$rows[] = array(
				'key'  => $color['id'],
				'id'   => isset( $known[ $color['id'] ] ) ? $color['id'] : '',
				'name' => $color['name'],
				'hex'  => $color['hex'],
				'raw'  => $color['hex'],
			);
		}

		nwcs_palette_draft( $rows, array(), array(), array_values( array_diff( array_keys( $known ), array_column( $after, 'id' ) ) ), 'duzenle' );
	}

	if ( nwcs_color_palette() !== $base ) {
		nwcs_palette_redirect( 'degisti' );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- yalnizca anahtarlar, sanitize_key.
	$checked = isset( $_POST['guncelle'] ) && is_array( $_POST['guncelle'] ) ? array_map( 'sanitize_key', array_map( 'strval', array_keys( wp_unslash( $_POST['guncelle'] ) ) ) ) : array();
	$changes = array();

	foreach ( nwcs_palette_changes( $base, $after ) as $id => $change ) {
		if ( in_array( (string) $id, $checked, true ) ) {
			$changes[ nwcs_search_fold( trim( $change['old_name'] ) ) ] = array(
				'name' => $change['old_name'] !== $change['name'] ? $change['name'] : '',
				'hex'  => $change['hex'],
			);
		}
	}

	$members = nwcs_palette_propagate( $changes );

	nwcs_palette_commit( $base, $after, $members );

	nwcs_palette_redirect( (string) ( $pending['result'] ?? 'kaydedildi' ), $members ? array( 'urun' => count( $members ) ) : array() );
}

/* ====================================================================== *
 * Geri alma (includes/admin/history.php cagirir)
 * ====================================================================== */

/**
 * Kategorinin gruplarinda urunun uye satiri; yoksa null.
 */
function nwcs_palette_member_in( array $groups, int $product_id ): ?array {
	foreach ( $groups as $group ) {
		foreach ( $group['members'] as $member ) {
			if ( (int) $member['product_id'] === $product_id ) {
				return $member;
			}
		}
	}

	return null;
}

/**
 * Renk listesi degisikligini geri alir: onceki liste (liste ilk kez
 * yazilmissa ayar silinir, ilk 13 renge doner) ve bu islemin guncelledigi
 * uyelerin onceki adi ve tonu. Liste ya da o uyeler bu islemden sonra baska
 * yoldan degistiyse hicbir seye dokunulmaz, kayit listeden kalkar.
 *
 * @return array{palette:int, members:int}|WP_Error
 */
function nwcs_color_palette_undo( array $record ) {
	if ( nwcs_color_palette() !== nwcs_color_palette_clean( $record['after'] ?? array() ) ) {
		return new WP_Error( 'nwcs_undo_gone', 'Renk listesi bu işlemden sonra başka yoldan değişmiş; eski hâline döndürülmedi. Bu işlem listeden kaldırıldı.' );
	}

	$members = array_values( array_filter( (array) ( $record['members'] ?? array() ), 'is_array' ) );
	$groups  = array();
	$changed = array();

	foreach ( $members as $row ) {
		$slug             = (string) ( $row['slug'] ?? '' );
		$groups[ $slug ] ??= nwcs_color_groups( $slug );
		$now              = nwcs_palette_member_in( $groups[ $slug ], (int) ( $row['product_id'] ?? 0 ) );

		if ( ! $now || $now['color_name'] !== (string) ( $row['after']['color_name'] ?? '' ) || $now['color_hex'] !== (string) ( $row['after']['color_hex'] ?? '' ) ) {
			$changed[] = nwcs_color_product_label( (int) ( $row['product_id'] ?? 0 ) );
		}
	}

	if ( $changed ) {
		return new WP_Error(
			'nwcs_undo_gone',
			sprintf(
				'Renk listesi geri alınmadı: bu işlemin güncellediği ürünlerin rengi sonradan değişmiş (%s). Hiçbir şey değişmedi; bu işlem listeden kaldırıldı. Gerekirse renkleri Renkler ve Renk grupları ekranlarından elle düzeltin.',
				nwcs_color_join( array_values( array_unique( $changed ) ) )
			)
		);
	}

	if ( ! empty( $record['seeded'] ) ) {
		delete_site_option( NWCS_COLOR_PALETTE );
	} else {
		update_site_option( NWCS_COLOR_PALETTE, nwcs_color_palette_clean( $record['before'] ?? array() ) );
	}

	if ( $members ) {
		switch_to_blog( nwcs_pool_blog_id() );

		foreach ( $groups as $slug => $list ) {
			$slug = (string) $slug; // Rakamli adres dizide sayi anahtar olur.

			foreach ( $list as $g => $group ) {
				foreach ( $group['members'] as $m => $member ) {
					foreach ( $members as $row ) {
						if ( (string) $row['slug'] === $slug && (int) $row['product_id'] === (int) $member['product_id'] ) {
							$list[ $g ]['members'][ $m ]['color_name'] = (string) ( $row['before']['color_name'] ?? '' );
							$list[ $g ]['members'][ $m ]['color_hex']  = (string) ( $row['before']['color_hex'] ?? '' );
						}
					}
				}
			}

			$term_id = (int) ( nwcs_pool_categories()[ $slug ]['id'] ?? 0 );

			if ( $term_id ) {
				update_term_meta( $term_id, NWCS_COLOR_META, nwcs_color_clean( $list ) );
			}
		}

		restore_current_blog();
		nwcs_pool_flush_cache();
	}

	return array(
		'palette' => 1,
		'members' => count( $members ),
	);
}
