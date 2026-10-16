<?php
/**
 * Ürün Havuzu -> kategori Excel'i: indirme, yukleme, onizleme, uygulama,
 * geri alma ekranlari ve admin-post islemleri.
 *
 * Hesap ve yazma sync.php'de; burada yalnizca istekler ve arayuz. JavaScript
 * gerekmez: her adim bir form, sonuc gecici kayitla (transient) sayfaya
 * tasinir.
 *
 * 0.21.0'a kadar burada serbest Excel icin sutun eslestirme sihirbazi vardi;
 * kaldirildi (PLAN-detay-basliklari.md, 8. bolum).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ortak yetki denetimi.
 */
function nwcs_sync_guard( string $nonce_action ): void {
	if ( ! current_user_can( NWCS_CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok. Yönetici hesabıyla giriş yapıp yeniden deneyin.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( $nonce_action );
}

/**
 * Kullanicinin bekleyen onizlemesi.
 */
function nwcs_sync_pending(): ?array {
	$plan = get_site_transient( NWCS_SYNC_PLAN . get_current_user_id() );

	return is_array( $plan ) ? $plan : null;
}

/**
 * Kategoriler sayfasina (kategori acik), Excel adimi bilgisiyle doner.
 */
function nwcs_sync_redirect( string $step, string $slug = '' ): void {
	wp_safe_redirect( nwcs_pool_categories_url( array_filter( array( 'kategori' => $slug, 'excel' => $step ) ) ) . '#nwcs-excel-result' );
	exit;
}

/* ====================================================================== *
 * Istekler
 * ====================================================================== */

add_action( 'admin_post_nwcs_sync_download', 'nwcs_handle_sync_download' );
function nwcs_handle_sync_download(): void {
	nwcs_sync_guard( 'nwcs_sync_download' );

	$slug   = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : '';
	$quick  = isset( $_GET['sablon'] ) && NWCS_SYNC_QUICK === sanitize_key( wp_unslash( $_GET['sablon'] ) );
	$result = $quick ? nwcs_sync_build_quick_template( $slug ) : nwcs_sync_build_template( $slug );

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	nwcs_xlsx_send( $result['path'], $result['filename'] );
}

add_action( 'admin_post_nwcs_sync_upload', 'nwcs_handle_sync_upload' );
function nwcs_handle_sync_upload(): void {
	nwcs_sync_guard( 'nwcs_sync_upload' );

	$key  = NWCS_SYNC_PLAN . get_current_user_id();
	$from = isset( $_POST['kategori'] ) ? sanitize_title( wp_unslash( $_POST['kategori'] ) ) : '';
	$path = nwcs_import_uploaded_xlsx( 'dosya' );

	if ( is_wp_error( $path ) ) {
		set_site_transient( $key . '_hata', $path->get_error_message(), 10 * MINUTE_IN_SECONDS );
		nwcs_sync_redirect( 'hata', $from );
	}

	$name = sanitize_file_name( (string) ( $_FILES['dosya']['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$plan = nwcs_sync_plan( $path, $name );
	wp_delete_file( $path );

	if ( is_wp_error( $plan ) ) {
		set_site_transient( $key . '_hata', $plan->get_error_message(), 10 * MINUTE_IN_SECONDS );
		nwcs_sync_redirect( 'hata', $from );
	}

	// Dosya baska bir kategorinin taslagiysa onizleme o kategorinin sayfasinda acilir.
	set_site_transient( $key, $plan, HOUR_IN_SECONDS );
	nwcs_sync_redirect( 'onizleme', (string) $plan['slug'] );
}

add_action( 'admin_post_nwcs_sync_apply', 'nwcs_handle_sync_apply' );
function nwcs_handle_sync_apply(): void {
	nwcs_sync_guard( 'nwcs_sync_apply' );

	$plan = nwcs_sync_pending();

	// Baska sekmede yeni bir dosya yuklendiyse eski onizlemenin dugmesi calismaz.
	if ( ! $plan || (int) ( $_POST['plan'] ?? 0 ) !== (int) $plan['time'] ) {
		set_site_transient( NWCS_SYNC_PLAN . get_current_user_id() . '_hata', 'Önizlemenin süresi doldu ya da başka bir dosya yüklendi. Dosyayı yeniden yükleyin.', 10 * MINUTE_IN_SECONDS );
		nwcs_sync_redirect( 'hata' );
	}

	// Once onizleme silinir: "Uygula"ya iki kez basilirsa ikinci istek bos doner,
	// yeni urunler iki kez eklenmez (silme veritabaninda tek istege basarili olur).
	if ( ! delete_site_transient( NWCS_SYNC_PLAN . get_current_user_id() ) ) {
		nwcs_sync_redirect( 'uygulandi', (string) $plan['slug'] );
	}

	nwcs_sync_apply( $plan, ! empty( $_POST['cope_tasi'] ) );

	nwcs_sync_redirect( 'uygulandi', (string) $plan['slug'] );
}

add_action( 'admin_post_nwcs_sync_cancel', 'nwcs_handle_sync_cancel' );
function nwcs_handle_sync_cancel(): void {
	nwcs_sync_guard( 'nwcs_sync_cancel' );

	$plan = nwcs_sync_pending();

	delete_site_transient( NWCS_SYNC_PLAN . get_current_user_id() );

	wp_safe_redirect( nwcs_pool_categories_url( array_filter( array( 'kategori' => (string) ( $plan['slug'] ?? '' ) ) ) ) );
	exit;
}

/* ====================================================================== *
 * Arayuz
 * ====================================================================== */

/**
 * Kategori listesindeki "Excel indir" baglantisi. $quick: Hizli Excel (kod, ad,
 * fiyat, site); $slug bossa tum urunler.
 */
function nwcs_sync_download_url( string $slug, bool $quick = false ): string {
	$args = array( 'action' => 'nwcs_sync_download', 'kategori' => $slug );

	if ( $quick ) {
		$args['sablon'] = NWCS_SYNC_QUICK;
	}

	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'nwcs_sync_download' );
}

/**
 * Kategoriler sayfasinin basinda Excel adiminin sonucu: onizleme, hata ya da
 * uygulama ozeti. Onizleme varken sayfanin geri kalani gosterilmez (true doner).
 * Geri almanin sonucu "Son işlemler"dedir (admin/history.php).
 */
function nwcs_render_sync_step(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnizca gosterim.
	$step = isset( $_GET['excel'] ) ? sanitize_key( wp_unslash( $_GET['excel'] ) ) : '';
	$key  = NWCS_SYNC_PLAN . get_current_user_id();

	if ( 'hata' === $step ) {
		$message = get_site_transient( $key . '_hata' );
		delete_site_transient( $key . '_hata' );

		if ( is_string( $message ) ) {
			?>
			<div class="nwcs-sync nwcs-sync--error" id="nwcs-excel-result" role="alert">
				<h2 class="nwcs-sync__title">Excel yüklenemedi</h2>
				<p><?php echo esc_html( $message ); ?></p>
				<p class="nwcs-sync__muted">Hiçbir ürün değişmedi.</p>
			</div>
			<?php
		}

		return false;
	}

	if ( 'uygulandi' === $step ) {
		$record = nwcs_sync_last();

		if ( $record ) {
			$counts = $record['counts'];
			?>
			<div class="nwcs-sync nwcs-sync--done" id="nwcs-excel-result" role="status">
				<h2 class="nwcs-sync__title">Değişiklikler uygulandı: <?php echo esc_html( $record['category'] ); ?></h2>
				<?php $labels = nwcs_placement_labels( (string) ( $record['slug'] ?? '' ) ); ?>
				<ul class="nwcs-sync__facts">
					<li><?php echo (int) $counts['updated']; ?> ürün güncellendi</li>
					<?php if ( empty( $record['quick'] ) ) : ?>
						<li><?php echo (int) $counts['created']; ?> yeni ürün eklendi</li>
						<li><?php echo (int) $counts['trashed']; ?> ürün çöp kutusuna taşındı</li>
					<?php endif; ?>
					<?php if ( ! empty( $counts['sites'] ) ) : ?>
						<li><?php echo (int) $counts['sites']; ?> ürünün sitesi değişti (SİTE sütunu)</li>
					<?php endif; ?>
					<?php if ( ! empty( $counts['addresses'] ) ) : ?>
						<li><?php echo (int) $counts['addresses']; ?> sayfa adresi değişti; eski adresler yeni adreslere yönleniyor</li>
					<?php endif; ?>
					<?php if ( $counts['errors'] ) : ?>
						<li class="is-warn"><?php echo (int) $counts['errors']; ?> hatalı satır yüklenmedi</li>
					<?php endif; ?>
				</ul>
				<?php if ( ! empty( $record['address_failed'] ) ) : ?>
					<p class="nwcs-sync__warn">
						Şu sayfa adresleri değiştirilemedi (ürünlerin diğer değişiklikleri uygulandı): <?php echo esc_html( implode( '; ', (array) $record['address_failed'] ) ); ?>.
						Bu adresleri Ürün Havuzu’nda ürünü açıp Özelleştirmeler › Sayfa adresi bölümünden değiştirebilirsiniz.
					</p>
				<?php endif; ?>
				<?php if ( $record['skipped'] ) : ?>
					<p class="nwcs-sync__warn">
						Önizlemeden sonra panelde değiştiği için atlanan ürünler: <?php echo esc_html( implode( ', ', $record['skipped'] ) ); ?>.
						Bu ürünler değişmedi; onlar için Excel’i yeniden indirip tekrar deneyin.
					</p>
				<?php endif; ?>
				<?php if ( $counts['created'] && $labels ) : ?>
					<p><?php echo esc_html( sprintf( 'Yeni ürünler %s sitelerinde görünüyor%s.', nwcs_join_and( array_values( $labels ) ), ! empty( $counts['sites'] ) ? '; SİTE sütunu yazılanlar yalnızca yazılan sitelerde' : '' ) ); ?></p>
				<?php elseif ( $counts['created'] ) : ?>
					<p class="nwcs-sync__warn">Bu kategori hiçbir siteye yerleştirilmemiş; yeni ürünler sitede görünmez. Kategoriyi aşağıdaki “Sitelerde” kutusundan sitelere yerleştirin.</p>
				<?php endif; ?>
				<p class="nwcs-sync__muted">Bir yanlışlık varsa aşağıdaki “Son işlemler”den bu yüklemenin tamamını geri alabilirsiniz.</p>
			</div>
			<?php
		}

		return false;
	}

	if ( 'onizleme' !== $step ) {
		return false;
	}

	$plan = nwcs_sync_pending();

	if ( ! $plan ) {
		echo '<div class="nwcs-sync nwcs-sync--error"><p>Önizlemenin süresi doldu. Dosyayı yeniden yükleyin.</p></div>';

		return false;
	}

	nwcs_render_sync_preview( $plan );

	return true;
}

/**
 * Deger gosterimi: bos hucre acikca "(boş)" yazar.
 */
function nwcs_sync_value( string $value, string $field = '', bool $is_new = false ): string {
	if ( '' !== $value ) {
		return esc_html( $value );
	}

	// Yeni deger bossa silme demektir: kirmizi. Eski deger bossa yalnizca bilgi.
	$class = $is_new ? 'nwcs-sync__empty' : 'nwcs-sync__none';

	// Bos meta silinmez, sayfa otomatik degere doner: kirmizi degil.
	if ( 'meta_title' === $field || 'meta_desc' === $field ) {
		return '<em class="nwcs-sync__none">boş (otomatik)</em>';
	}

	// $field alan anahtari ('price', 'detail'...): etiket degil; "Fiyat" adli detay basligi karismaz.
	return 'price' === $field ? '<em class="' . $class . '">boş (“Teklif al”)</em>' : '<em class="' . $class . '">' . ( $is_new ? 'boş (silinir)' : 'boş' ) . '</em>';
}

/**
 * Onizleme karti.
 */
function nwcs_render_sync_preview( array $plan ): void {
	$update  = (array) $plan['update'];
	$create  = (array) $plan['create'];
	$trash   = (array) $plan['trash'];
	$errors  = (array) $plan['errors'];
	$same    = (array) $plan['same'];
	$nothing = ! $update && ! $create && ( ! $trash || ! empty( $plan['trash_off'] ) );
	$labels  = array_values( (array) ( $plan['sites'] ?? array() ) );
	$order   = nwcs_history_order_note();
	$quick   = ! empty( $plan['quick'] );
	// SİTE sutunuyla sitesi belirlenen satir var mi (yeni urun ya da site degisikligi).
	$by_site = (bool) array_filter( $create, static fn( array $item ): bool => is_array( $item['data']['sites'] ?? null ) )
		|| (bool) array_filter( $update, static fn( array $item ): bool => '' !== (string) ( $item['moves'] ?? '' ) );
	?>
	<section class="nwcs-sync" id="nwcs-excel-result" aria-labelledby="nwcs-sync-title">
		<header class="nwcs-sync__head">
			<h2 class="nwcs-sync__title" id="nwcs-sync-title">Önizleme<?php echo $quick ? ' (Hızlı Excel)' : ''; ?>: <?php echo esc_html( $plan['term_name'] ); ?></h2>
			<p class="nwcs-sync__muted">
				<?php echo esc_html( $plan['file'] ); ?>
				<?php if ( $plan['downloaded'] ) : ?>
					— <?php echo esc_html( wp_date( 'd.m.Y H:i', (int) $plan['downloaded'] ) ); ?> tarihinde indirilen Excel
				<?php endif; ?>
			</p>
			<p class="nwcs-sync__lead">Henüz hiçbir şey değişmedi. Aşağıyı kontrol edin, doğruysa <strong>Değişiklikleri uygula</strong>’ya basın.</p>
			<?php if ( $quick ) : ?>
				<p class="nwcs-sync__sites">Yalnızca var olan ürünler güncellenir. Dosyada olmayan ürünlere ve bu dosyada olmayan alanlara (açıklamalar, kısa açıklama, meta başlık ve meta açıklama, teknik detaylar) dokunulmaz.</p>
			<?php elseif ( $labels ) : ?>
				<p class="nwcs-sync__sites"><?php echo esc_html( sprintf( 'Bu ürünler %s sitelerinde görünecek%s.', nwcs_join_and( $labels ), $by_site ? '; SİTE sütunu yazılanlar yalnızca yazılan sitelerde' : '' ) ); ?></p>
			<?php else : ?>
				<p class="nwcs-sync__warn">Bu kategori hiçbir siteye yerleştirilmemiş; ürünler yüklenir ama sitede görünmez. Yükledikten sonra kategoriyi bu sayfadaki “Sitelerde” kutusundan sitelere yerleştirin.</p>
			<?php endif; ?>
			<?php if ( '' !== $order && ! $nothing ) : ?>
				<p class="nwcs-sync__muted"><?php echo esc_html( $order ); ?></p>
			<?php endif; ?>
		</header>

		<ul class="nwcs-sync__sum">
			<li class="nwcs-sync__chip nwcs-sync__chip--update"><b><?php echo count( $update ); ?></b> ürün güncellenecek</li>
			<?php if ( ! $quick ) : ?>
				<li class="nwcs-sync__chip nwcs-sync__chip--new"><b><?php echo count( $create ); ?></b> yeni ürün</li>
				<li class="nwcs-sync__chip nwcs-sync__chip--trash"><b><?php echo count( $trash ); ?></b> ürün çöp kutusuna</li>
			<?php endif; ?>
			<li class="nwcs-sync__chip nwcs-sync__chip--error"><b><?php echo count( $errors ); ?></b> satırda hata</li>
			<li class="nwcs-sync__chip"><b><?php echo count( $same ); ?></b> ürün aynı kalacak</li>
		</ul>

		<?php if ( ! empty( $plan['shared'] ) ) : ?>
			<p class="nwcs-sync__warn">
				<strong>Ortak kategori:</strong> bu kategorideki ürünler birden çok sitede görünüyor
				(<?php echo esc_html( implode( ', ', $plan['shared'] ) ); ?>). Yaptığınız değişiklikler bu sitelerin hepsinde geçerli olur.
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $plan['notes'] ) ) : ?>
			<ul class="nwcs-sync__notes">
				<?php foreach ( $plan['notes'] as $note ) : ?>
					<li><?php echo esc_html( $note ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $plan['stale'] ) : ?>
			<p class="nwcs-sync__warn">
				Bu dosyayı indirdikten sonra panelde değişen <?php echo count( $plan['stale'] ); ?> ürün var:
				<?php echo esc_html( implode( ', ', $plan['stale'] ) ); ?>.
				Uygularsanız Excel’deki hâli geçerli olur; paneldeki son değişiklikler kaybolur.
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $plan['codeless'] ) ) : ?>
			<p class="nwcs-sync__muted">
				<?php echo esc_html( sprintf( '%d ürünün kodu yok; satırları değişmediği için atlandı. Bu ürünleri güncellemek için önce ÜRÜN KODU hücresini doldurun.', count( $plan['codeless'] ) ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $plan['cleared'] ) ) : ?>
			<p class="nwcs-sync__warn">
				<strong><?php echo count( $plan['cleared'] ); ?> ürünün açıklaması silinecek</strong> (ÜRÜN AÇIKLAMASI hücresi boş):
				<?php echo esc_html( nwcs_sync_name_list( $plan['cleared'] ) ); ?>.
				Ürün sayfaları açık kalır; yalnızca açıklama metni görünmez (varsa tabloları kalır). İstemiyorsanız hücreleri doldurup dosyayı yeniden yükleyin.
			</p>
		<?php endif; ?>

		<?php foreach ( array( 'meta_title' => 'meta başlığı', 'meta_desc' => 'meta açıklaması' ) as $field => $label ) : ?>
			<?php if ( ! empty( $plan['long'][ $field ] ) ) : ?>
				<p class="nwcs-sync__warn">
					<?php echo esc_html( sprintf( '%d ürünün %s %d karakteri geçiyor; yüklenir ama arama sonucunda kesilir: %s.', count( $plan['long'][ $field ] ), $label, NWCS_SYNC_META_LIMITS[ $field ], nwcs_sync_name_list( $plan['long'][ $field ] ) ) ); ?>
				</p>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php
		$moving    = array_filter( $update, static fn( array $item ): bool => ! empty( $item['data']['addresses'] ) );
		$addresses = array_sum( array_map( static fn( array $item ): int => count( (array) $item['data']['addresses'] ), $moving ) );
		?>
		<?php if ( $moving ) : ?>
			<p class="nwcs-sync__warn">
				<?php echo esc_html( sprintf( '%d sayfa adresi değişecek (%d ürün). Eski adresler yeni adreslere kendiliğinden yönlenir; paylaşılmış bağlantılar kırılmaz.', $addresses, count( $moving ) ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $errors ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--error">
				<h3>Yüklenmeyecek satırlar (<?php echo count( $errors ); ?>)</h3>
				<?php $cause = nwcs_sync_common_missing( $errors ); ?>
				<?php if ( '' !== $cause ) : ?>
					<p class="nwcs-sync__warn">
						<?php echo esc_html( sprintf( '%d satırın hepsinde “%s” boş. Bu başlık bu kategoride kullanılmıyorsa', count( $errors ), $cause ) ); ?>
						<a href="<?php echo esc_url( nwcs_headings_url() ); ?>" target="_blank" rel="noopener">Detay başlıkları’ndan zorunlu işaretini kaldırın<span class="screen-reader-text"> (yeni sekmede açılır)</span> ↗</a>,
						sonra dosyayı yeniden yükleyin.
					</p>
				<?php endif; ?>
				<p class="nwcs-sync__muted">Bu satırlardaki ürünler olduğu gibi kalır. Excel’de düzeltip dosyayı yeniden yükleyebilirsiniz.</p>
				<ul class="nwcs-sync__list">
					<?php foreach ( $errors as $error ) : ?>
						<li>
							<span class="nwcs-sync__line"><?php echo (int) $error['line']; ?>. satır</span>
							<strong><?php echo esc_html( $error['name'] ); ?></strong>
							<span><?php echo esc_html( $error['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $update ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--update">
				<h3>Güncellenecek ürünler (<?php echo count( $update ); ?>)</h3>
				<ul class="nwcs-sync__list">
					<?php foreach ( $update as $item ) : ?>
						<li>
							<details class="nwcs-sync__item">
								<summary>
									<strong><?php echo esc_html( $item['title'] ); ?></strong>
									<span class="nwcs-sync__muted">
										<?php echo $item['restore'] ? 'çöp kutusundan geri gelecek, ' : ''; ?>
										<?php echo count( $item['changes'] ); ?> değişiklik<?php echo ! empty( $item['sites'] ) ? ', ' . esc_html( implode( ', ', $item['sites'] ) ) . ' sitesinde' : ''; ?>
									</span>
									<?php if ( '' !== (string) ( $item['moves'] ?? '' ) ) : ?>
										<span class="nwcs-sync__moves"><?php echo esc_html( $item['moves'] ); ?></span>
									<?php endif; ?>
								</summary>
								<?php if ( $item['changes'] ) : ?>
									<table class="nwcs-sync__diff">
										<thead><tr><th scope="col">Alan</th><th scope="col">Şimdi</th><th scope="col">Excel’deki</th></tr></thead>
										<tbody>
											<?php foreach ( $item['changes'] as $change ) : ?>
												<tr>
													<th scope="row"><?php echo esc_html( $change[0] ); ?></th>
													<td><?php echo nwcs_sync_value( (string) $change[1], (string) ( $change[3] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapingOutput -- kacisli. ?></td>
													<td><?php echo nwcs_sync_value( (string) $change[2], (string) ( $change[3] ?? '' ), true ); // phpcs:ignore WordPress.Security.EscapingOutput -- kacisli. ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</details>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $create ) : ?>
			<div class="nwcs-sync__group nwcs-sync__group--new">
				<h3>Eklenecek yeni ürünler (<?php echo count( $create ); ?>)</h3>
				<p class="nwcs-sync__muted">
					Yalnızca “<?php echo esc_html( $plan['term_name'] ); ?>” kategorisine eklenir.
					<?php echo $labels ? esc_html( sprintf( 'Görüneceği siteler: %s%s.', nwcs_join_and( $labels ), $by_site ? ' (SİTE yazılan satırlarda yazılan siteler)' : '' ) ) : 'Kategori hiçbir siteye yerleştirilmediği için sitede görünmez.'; ?>
				</p>
				<ul class="nwcs-sync__list">
					<?php foreach ( $create as $item ) : ?>
						<li>
							<span class="nwcs-sync__line"><?php echo (int) $item['line']; ?>. satır</span>
							<strong><?php echo esc_html( $item['data']['title'] ); ?></strong>
							<span class="nwcs-sync__muted"><?php echo esc_html( (string) $item['data']['code'] ); ?></span>
							<span class="nwcs-sync__newfacts"><?php echo esc_html( nwcs_sync_new_facts( (array) $item['data'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $plan['headings'] || $plan['ignored'] ) : ?>
			<div class="nwcs-sync__group">
				<?php if ( $plan['headings'] ) : ?>
					<h3>Yeni detay başlığı</h3>
					<p>
						<?php echo esc_html( implode( ', ', $plan['headings'] ) ); ?> —
						başlık listesine eklenecek; bu kategorinin sonraki Excel dosyasında sütun olarak gelir.
					</p>
				<?php endif; ?>
				<?php if ( $plan['ignored'] ) : ?>
					<p class="nwcs-sync__muted">Hücreleri boş olduğu için atlanan yeni sütunlar: <?php echo esc_html( implode( ', ', $plan['ignored'] ) ); ?>.</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nwcs-sync__form">
			<input type="hidden" name="action" value="nwcs_sync_apply" />
			<input type="hidden" name="plan" value="<?php echo esc_attr( (string) $plan['time'] ); ?>" />
			<?php wp_nonce_field( 'nwcs_sync_apply' ); ?>

			<?php if ( $trash && ! empty( $plan['trash_off'] ) ) : ?>
				<p class="nwcs-sync__warn">
					Dosyada olmayan <?php echo count( $trash ); ?> ürün var, ama bu panelde çöp kutusu kapalı.
					Çöpe atmak kalıcı silmek olacağı için bu ürünlere dokunulmayacak. Bu ürünleri kaldırmanız gerekiyorsa geliştiriciye haber verin. (Teknik: EMPTY_TRASH_DAYS = 0)
				</p>
			<?php elseif ( $trash ) : ?>
				<div class="nwcs-sync__danger" role="group" aria-labelledby="nwcs-sync-trash">
					<h3 id="nwcs-sync-trash">Dikkat: <?php echo count( $trash ); ?> ürün dosyada yok</h3>
					<p>
						Bu ürünler “<?php echo esc_html( $plan['term_name'] ); ?>” kategorisinde ama yüklediğiniz dosyada yoklar.
						Onay verirseniz <strong>çöp kutusuna</strong> taşınır ve gösterildikleri <strong>bütün sitelerden kalkar</strong>
						(diğer kategorilerindeki listeler dahil). Çöp kutusundan geri getirilebilirler.
					</p>
					<ul class="nwcs-sync__list">
						<?php foreach ( $trash as $item ) : ?>
							<li>
								<strong><?php echo esc_html( $item['title'] ); ?></strong>
								<span class="nwcs-sync__muted"><?php echo esc_html( $item['code'] ); ?></span>
								<span>
									<?php echo $item['others'] ? 'Diğer kategorileri: ' . esc_html( implode( ', ', $item['others'] ) ) . '. ' : 'Başka kategorisi yok. '; ?>
									<?php echo $item['sites'] ? 'Göründüğü siteler: ' . esc_html( implode( ', ', $item['sites'] ) ) . '.' : 'Hiçbir sitede görünmüyor.'; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
					<label class="nwcs-sync__confirm">
						<input type="checkbox" name="cope_tasi" value="1" />
						Evet, bu <?php echo count( $trash ); ?> ürünü çöp kutusuna taşı
					</label>
					<p class="nwcs-sync__muted">İşaretlemezseniz bu ürünlere dokunulmaz; yalnızca diğer değişiklikler uygulanır.</p>
				</div>
			<?php endif; ?>

			<div class="nwcs-sync__actions">
				<?php if ( ! $nothing ) : ?>
					<button type="submit" class="button button-primary button-hero">Değişiklikleri uygula</button>
				<?php else : ?>
					<p class="nwcs-sync__muted">Uygulanacak bir değişiklik yok: dosyadaki bilgiler paneldekiyle aynı<?php echo $errors ? ' ya da değişen satırlar hatalı' : ''; ?>.</p>
				<?php endif; ?>
				<button type="submit" class="button" form="nwcs-sync-cancel">Vazgeç</button>
			</div>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nwcs-sync-cancel" hidden>
			<input type="hidden" name="action" value="nwcs_sync_cancel" />
			<?php wp_nonce_field( 'nwcs_sync_cancel' ); ?>
		</form>
	</section>
	<?php
}

/**
 * "A, B ve C" bicimi.
 */
function nwcs_join_and( array $items ): string {
	$items = array_values( array_filter( array_map( 'strval', $items ), 'strlen' ) );
	$last  = array_pop( $items );

	return $items ? implode( ', ', $items ) . ' ve ' . $last : (string) $last;
}

/**
 * Uyari satirindaki urun adlari: ilk 10'u, fazlasi "…".
 */
function nwcs_sync_name_list( array $names ): string {
	return implode( ', ', array_slice( $names, 0, 10 ) ) . ( count( $names ) > 10 ? '…' : '' );
}

/**
 * Hatali satirlarin hepsi ayni zorunlu baslikta takildiysa o baslik; yoksa bos.
 */
function nwcs_sync_common_missing( array $errors ): string {
	$common = null;

	foreach ( $errors as $error ) {
		$missing = (array) ( $error['missing'] ?? array() );

		if ( ! $missing ) {
			return '';
		}

		$common = null === $common ? $missing : array_intersect_key( $common, $missing );
	}

	return $common ? (string) reset( $common ) : '';
}

/**
 * Yeni urun satirinin ozeti: fiyat ve zorunlu basliklarin degerleri.
 */
function nwcs_sync_new_facts( array $data ): string {
	$parts = array( '' !== (string) ( $data['price'] ?? '' ) ? (string) $data['price'] : 'fiyat yok (“Teklif al”)' );

	// SİTE yazildiysa kategorinin yerlesimi yerine bu siteler.
	if ( is_array( $data['sites'] ?? null ) ) {
		$parts[] = 'Site: ' . nwcs_sync_sites_text( $data['sites'] );
	}

	if ( '' !== (string) ( $data['post_name'] ?? '' ) ) {
		$parts[] = 'Adres: /urun/' . $data['post_name'] . '/';
	}

	foreach ( nwcs_required_headings() as $key => $label ) {
		$value = (string) ( $data['details'][ $key ] ?? '' );

		if ( '' !== $value ) {
			$parts[] = $label . ': ' . $value;
		}
	}

	return implode( ' · ', $parts );
}
