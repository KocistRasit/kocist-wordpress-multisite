<?php
/**
 * Kuzen kereste siteleri icin manifest kurucusu.
 *
 * Alan yapisi iki sitede aynidir; varsayilan metinler, urunler ve ana sayfada
 * hangi modullerin gorunecegi cocuk temadan gelir. Ana sayfada yalnizca
 * home_sections listesindeki moduller alan olarak acilir (ithalkeresteci
 * "tedarik", kavakkeresteci "kullanim alanlari" modulunu kullanir):
 *
 *   $build = require dirname( __DIR__ ) . '/kereste-base/manifest.php';
 *   return $build( array( ...site ayarlari... ) );
 *
 * Saf PHP: WordPress fonksiyonu kullanilmaz, cunku ag paneli manifesti siteye
 * gecmeden dosyadan okur. Dosya birden cok kez okunabilecegi icin fonksiyon
 * tanimlamaz, bir kurucu (closure) dondurur.
 */

return static function ( array $s ): array {

	$text = static fn( string $label, string $default, string $type = 'text' ): array => array(
		'label'   => $label,
		'type'    => $type,
		'default' => $default,
	);

	$image = static fn( string $label ): array => array(
		'label'   => $label,
		'type'    => 'image',
		'default' => 0,
	);

	$rows = static fn( string $label, int $max, array $fields, array $default ): array => array(
		'label'   => $label,
		'type'    => 'repeater',
		'max'     => $max,
		'fields'  => $fields,
		'default' => $default,
	);

	$head = static fn( string $title, string $lead ): array => array(
		'label'  => 'Sayfa Başlığı',
		'fields' => array(
			'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => $title ),
			'lead'  => array( 'label' => 'Öne Çıkan Cümle', 'type' => 'textarea', 'default' => $lead ),
		),
	);

	$c = $s['contact'];

	// Siteye gore bulunmayabilecek moduller.
	$supply = ( $s['supply'] ?? array() ) + array( 'title' => '', 'text' => '', 'points' => array() );
	$uses   = ( $s['uses'] ?? array() ) + array( 'title' => '', 'text' => '', 'items' => array() );

	/* ------------------------------------------------------------ *
	 * Urun sayfalari: dort urun, ayni alan yapisi
	 * ------------------------------------------------------------ */
	$products = array();

	foreach ( $s['products'] as $key => $p ) {
		$products[ 'urun_' . $key ] = array(
			'label'      => 'Ürün: ' . $p['name'],
			'path'       => '/urunler/' . $p['slug'] . '/',
			'template'   => 'product',
			'seo_source' => array(
				'title'       => 'card.name',
				'description' => 'detail.lead',
				'image'       => 'card.image',
				'type'        => 'Product',
				'properties'  => 'specs.rows',
			),
			'components' => array(
				'card'   => array(
					'label'  => 'Ürün Kartı (listelerde görünür)',
					'fields' => array(
						'name'  => $text( 'Ürün Adı', $p['name'] ),
						'short' => $text( 'Kısa Açıklama', $p['short'], 'textarea' ),
						'mark'  => $text( 'Etiket Yazısı (şablon harf; ölçü ya da tür)', $p['mark'] ),
						'image' => $image( 'Kart Görseli' ),
					),
				),
				'detail' => array(
					'label'  => 'Ürün Sayfası',
					'fields' => array(
						'lead'    => $text( 'Öne Çıkan Cümle', $p['lead'], 'textarea' ),
						'body'    => $text( 'Ürün Açıklaması', $p['body'], 'textarea' ),
						'gallery' => $rows( 'Galeri', 8, array( 'image' => array( 'label' => 'Görsel', 'type' => 'image' ) ), array() ),
					),
				),
				'specs'  => array(
					'label'  => 'Şartname Tablosu',
					'fields' => array(
						'rows' => $rows(
							'Satırlar',
							10,
							array(
								'label' => array( 'label' => 'Özellik', 'type' => 'text' ),
								'value' => array( 'label' => 'Değer', 'type' => 'text' ),
							),
							$p['specs']
						),
					),
				),
			),
		);
	}

	$pages = array(

		/* ------------------------------------------------------------ *
		 * Ortak: ust menu ve alt bilgi
		 * ------------------------------------------------------------ */
		'global' => array(
			'label'      => 'Tüm Sayfalar (Üst Menü, Alt Bilgi)',
			'path'       => '/',
			'components' => array(
				'header' => array(
					'label'  => 'Üst Menü',
					'fields' => array(
						'logo_image'   => $image( 'Logo görseli (boşsa çam ağaçlı yazı logosu kullanılır)' ),
						'logo_word'    => $text( 'Logo: üst satır', $s['logo'][0] ),
						'logo_rest'    => $text( 'Logo: alt satır', $s['logo'][1] ),
						'menu'         => $rows(
							'Menü Öğeleri',
							7,
							array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı Adresi', 'type' => 'url' ),
							),
							array(
								array( 'label' => 'Ürünler', 'url' => '/urunler/' ),
								array( 'label' => 'Sık sorulanlar', 'url' => '/sik-sorulan-sorular/' ),
								array( 'label' => 'Hakkımızda', 'url' => '/hakkimizda/' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/' ),
							)
						),
						'phone_label'  => $text( 'Telefon Metni', $c['phone_label'] ),
						'phone_url'    => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $c['phone_url'] ),
						'mobile_label' => $text( 'Cep Telefonu Metni', $c['mobile_label'] ?? '' ),
						'mobile_url'   => array( 'label' => 'Cep Telefonu Bağlantısı', 'type' => 'url', 'default' => $c['mobile_url'] ?? '' ),
						'phone_note'   => $text( 'Telefon Kutusu Üst Yazısı', 'Fiyat ve stok için arayın' ),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı (boşsa düğme gizlenir)', 'type' => 'url', 'default' => $c['whatsapp_url'] ),
						'whatsapp_label' => $text( 'WhatsApp Düğmesi Metni', 'WhatsApp' ),
						'cta_label'      => $text( 'Teklif Düğmesi Metni', 'Teklif al' ),
					),
				),
				'footer' => array(
					'label'  => 'Alt Bilgi',
					'fields' => array(
						'tagline'   => $text( 'Alt Bilgi Metni', $s['footer_tagline'], 'textarea' ),
						'hours'     => $text( 'Çalışma Saatleri', $c['hours'] ),
						'email'     => $text( 'E-posta', $c['email'] ),
						'address'   => $text( 'Adres', $c['address'], 'textarea' ),
						'family'    => $rows(
							'Kardeş Siteler',
							8,
							array(
								'label' => array( 'label' => 'Site Adı', 'type' => 'text' ),
								'note'  => array( 'label' => 'Kısa Not', 'type' => 'text' ),
								'url'   => array( 'label' => 'Adres', 'type' => 'url' ),
							),
							$s['family']
						),
						'copyright' => $text( 'Telif Satırı', 'Koçist Orman Ürünleri' ),
						'pages_title'    => $text( 'Sütun Başlığı: Sayfalar', 'Sayfalar' ),
						'products_title' => $text( 'Sütun Başlığı: Ürünler', 'Ürünler' ),
						'contact_title'  => $text( 'Sütun Başlığı: İletişim', 'İletişim' ),
						'family_title'   => $text( 'Kardeş Siteler Başlığı', 'Kardeş sitelerimiz' ),
					),
				),
			),
		),

		/* ------------------------------------------------------------ *
		 * Ana sayfa
		 * ------------------------------------------------------------ */
		'home' => array(
			'label'             => 'Ana Sayfa',
			'path'              => '/',
			'sortable_sections' => $s['home_sections'],
			'components'        => array_intersect_key( array(
				'hero'     => array(
					'label'  => 'Giriş (Hero)',
					'fields' => array(
						'title'       => $text( 'Başlık', $s['hero']['title'], 'textarea' ),
						'lead'        => $text( 'Alt Metin', $s['hero']['lead'], 'textarea' ),
						'image'       => $image( 'Fotoğraf' ),
						'image_note'  => $text( 'Fotoğraf Notu (gerçek fotoğraf yüklenince silin)', 'Örnek görsel' ),
						'secondary'   => $text( 'İkinci Düğme Metni', 'Ürünleri görün' ),
					),
				),
				'products' => array(
					'label'  => 'Kereste Çeşitleri',
					'fields' => array(
						'title'      => $text( 'Bölüm Başlığı', 'Kereste çeşitleri' ),
						'text'       => $text( 'Bölüm Metni', $s['products_text'], 'textarea' ),
						'link_label' => $text( 'Bağlantı Metni', 'Bütün ürünler' ),
					),
				),
				'supply'   => array(
					'label'  => 'Toptan Tedarik',
					'fields' => array(
						'title'  => $text( 'Başlık', $supply['title'] ),
						'text'   => $text( 'Metin', $supply['text'], 'textarea' ),
						'points' => $rows(
							'Öne Çıkanlar',
							4,
							array(
								'value' => array( 'label' => 'Büyük Yazı', 'type' => 'text' ),
								'label' => array( 'label' => 'Açıklama', 'type' => 'text' ),
							),
							$supply['points']
						),
						'photos' => $rows( 'Fotoğraflar', 3, array( 'image' => array( 'label' => 'Fotoğraf', 'type' => 'image' ) ), array() ),
					),
				),
				'uses'     => array(
					'label'  => 'Kullanım Alanları',
					'fields' => array(
						'title' => $text( 'Başlık', $uses['title'] ),
						'text'  => $text( 'Metin', $uses['text'], 'textarea' ),
						'items' => $rows(
							'Alanlar',
							8,
							array(
								'title' => array( 'label' => 'Alan', 'type' => 'text' ),
								'text'  => array( 'label' => 'Kısa Açıklama', 'type' => 'text' ),
								'image' => array( 'label' => 'Fotoğraf', 'type' => 'image' ),
							),
							$uses['items']
						),
					),
				),
				'cousin'   => array(
					'label'  => 'Kuzen Site Köprüsü',
					'fields' => array(
						'title'        => $text( 'Başlık', $s['cousin']['title'] ),
						'text'         => $text( 'Metin', $s['cousin']['text'], 'textarea' ),
						'button_label' => $text( 'Düğme Metni', $s['cousin']['button'] ),
						'button_url'   => array( 'label' => 'Düğme Adresi', 'type' => 'url', 'default' => $s['cousin']['url'] ),
					),
				),
				'faq'      => array(
					'label'  => 'Sık Sorulanlardan Seçme',
					'fields' => array(
						'title'      => $text( 'Bölüm Başlığı', 'Sık sorulanlar' ),
						'count'      => $text( 'Kaç soru gösterilsin', '3' ),
						'link_label' => $text( 'Bağlantı Metni', 'Bütün sorular' ),
					),
				),
				'quote'    => array(
					'label'  => 'Teklif Şeridi',
					'fields' => array(
						'title'        => $text( 'Başlık', 'Fiyat için ölçü ve adet yeterli.' ),
						'text'         => $text( 'Metin', 'Kereste fiyatı türe, ölçüye ve miktara göre değişir. Ölçüleri iletin, güncel fiyatla dönelim.', 'textarea' ),
						'button_label' => $text( 'Düğme Metni', 'Teklif al' ),
					),
				),
			), array_flip( array_merge( array( 'hero' ), $s['home_sections'] ) ) ),
		),

		/* ------------------------------------------------------------ *
		 * Urunler (liste)
		 * ------------------------------------------------------------ */
		'products' => array(
			'label'      => 'Ürünler',
			'path'       => '/urunler/',
			'components' => array(
				'head'   => $head( 'Ürünler', $s['products_lead'] ),
				'shared' => array(
					'label'  => 'Ürün Sayfalarının Ortak Metinleri',
					'fields' => array(
						'detail_label'  => $text( 'İncele Düğmesi', 'Ürünü inceleyin' ),
						'quote_label'   => $text( 'Teklif Düğmesi', 'Bu ürün için teklif al' ),
						'specs_title'   => $text( 'Şartname Başlığı', 'Teknik bilgiler' ),
						'related_title' => $text( 'Diğer Ürünler Başlığı', 'Diğer kereste çeşitleri' ),
						'zoom_label'    => $text( 'Galeri: Büyüt Etiketi', 'Büyüt' ),
						'zoom_fit'      => $text( 'Büyütme Penceresi: Sığdır Düğmesi', 'Sığdır' ),
						'zoom_hint'     => $text( 'Büyütme Penceresi: Kullanım Notu', 'Yakınlaştırmak için görsele tıklayın ya da tekerleği kullanın; yakınken sürükleyerek gezinin.', 'textarea' ),
					),
				),
			),
		),
	);

	$pages += $products;

	$pages += array(

		'faq' => array(
			'label'      => 'Sık Sorulan Sorular',
			'path'       => '/sik-sorulan-sorular/',
			'seo_source' => array(
				'type'      => 'FAQPage',
				'questions' => 'items.rows',
			),
			'components' => array(
				'head'  => $head( 'Sık sorulan sorular', $s['faq_lead'] ?? 'Sipariş, sevkiyat süresi, ödeme seçenekleri, ölçüye göre kesim ve kereste metreküp hesabıyla ilgili en çok sorulanlar.' ),
				'items' => array(
					'label'  => 'Sorular',
					'fields' => array(
						'rows' => $rows(
							'Soru ve Cevaplar',
							20,
							array(
								'question' => array( 'label' => 'Soru', 'type' => 'text' ),
								'answer'   => array( 'label' => 'Cevap', 'type' => 'textarea' ),
							),
							$s['faq']
						),
					),
				),
				'more'  => array(
					'label'  => 'Alt Satır (Sorunuz burada yok mu?)',
					'fields' => array(
						'lead' => $text( 'Soru', 'Sorunuz burada yok mu?' ),
						'link' => $text( 'Yazın Bağlantısı', 'Bize yazın' ),
						'or'   => $text( 'Bağlaç', 'ya da' ),
						'call' => $text( 'Telefondan Sonraki Metin', 'numarasını arayın.' ),
					),
				),
			),
		),

		'about' => array(
			'label'      => 'Hakkımızda',
			'path'       => '/hakkimizda/',
			'components' => array(
				'head'   => $head( 'Hakkımızda', $s['about']['lead'] ),
				'story'  => array(
					'label'  => 'Firma Metni',
					'fields' => array(
						'image' => $image( 'Fotoğraf' ),
						'p1'    => $text( 'Birinci Paragraf', $s['about']['p1'], 'textarea' ),
						'p2'    => $text( 'İkinci Paragraf', $s['about']['p2'], 'textarea' ),
					),
				),
				'values' => array(
					'label'  => 'İlkeler',
					'fields' => array(
						'rows' => $rows(
							'İlkeler',
							6,
							array(
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							$s['about']['values']
						),
					),
				),
			),
		),

		'contact' => array(
			'label'      => 'İletişim',
			'path'       => '/iletisim/',
			'components' => array(
				'head'    => $head( 'İletişim', $s['contact_lead'] ?? 'Ölçü ve adedi yazın ya da arayın; size en kısa sürede dönelim.' ),
				'details' => array(
					'label'  => 'İletişim Bilgileri',
					'fields' => array(
						'phone_label' => $text( 'Telefon', $c['phone_label'] ),
						'phone_url'   => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $c['phone_url'] ),
						'mobile_label' => $text( 'Cep ve WhatsApp', $c['mobile_label'] ?? '' ),
						'mobile_url'  => array( 'label' => 'Cep Telefonu Bağlantısı', 'type' => 'url', 'default' => $c['mobile_url'] ?? '' ),
						'email'       => $text( 'E-posta', $c['email'] ),
						'address'     => $text( 'Adres', $c['address'], 'textarea' ),
						'hours'       => $text( 'Çalışma Saatleri', $c['hours'] ),
						'map_label'   => $text( 'Yol Tarifi Metni', 'Yol tarifi al' ),
						'map_url'     => array( 'label' => 'Harita Bağlantısı', 'type' => 'url', 'default' => $c['map_url'] ),
						'phone_title'   => $text( 'Satır Başlığı: Telefon', 'Telefon' ),
						'mobile_title'  => $text( 'Satır Başlığı: Cep', 'Cep ve WhatsApp' ),
						'email_title'   => $text( 'Satır Başlığı: E-posta', 'E-posta' ),
						'address_title' => $text( 'Satır Başlığı: Adres', 'Adres' ),
						'hours_title'   => $text( 'Satır Başlığı: Çalışma Saatleri', 'Çalışma saatleri' ),
					),
				),
				'form'    => array(
					'label'  => 'Teklif Formu',
					'fields' => array(
						'title'         => $text( 'Form Başlığı', 'Teklif isteyin' ),
						'note'          => $text( 'Form Açıklaması', $s['form_note'] ?? 'Ürün, ölçü ve adet yeterli; fiyatla dönelim.', 'textarea' ),
						'size_hint'     => $text( 'Ölçü Alanı İpucu', $s['size_hint'] ?? 'Örn. 5 × 10 cm, 4 m, 100 adet' ),
						'submit_label'  => $text( 'Gönder Düğmesi', 'Teklif isteğini gönder' ),
						'privacy_note'  => $text( 'Gizlilik Notu', 'Bilgileriniz yalnızca teklifinizi hazırlamak için kullanılır.' ),
						'success_title' => $text( 'Başarı Başlığı', 'Teklif isteğiniz bize ulaştı.' ),
						'success_text'  => $text( 'Başarı Metni', 'En kısa sürede dönüş yapacağız. Acele bir iş için telefonla da ulaşabilirsiniz.', 'textarea' ),
						'label_name'    => $text( 'Alan Adı: Ad Soyad', 'Ad soyad' ),
						'label_company' => $text( 'Alan Adı: Firma', 'Firma' ),
						'label_phone'   => $text( 'Alan Adı: Telefon', 'Telefon' ),
						'label_email'   => $text( 'Alan Adı: E-posta', 'E-posta' ),
						'label_product' => $text( 'Alan Adı: Ürün', 'Ürün' ),
						'product_empty' => $text( 'Ürün Listesi: Boş Seçenek', 'Seçin' ),
						'label_size'    => $text( 'Alan Adı: Ölçü ve Adet', 'Ölçü ve adet' ),
						'label_message' => $text( 'Alan Adı: Mesaj', 'Eklemek istedikleriniz' ),
						'error_summary' => $text( 'Hata: Genel Uyarı', 'Eksik ya da hatalı alanlar var; işaretli alanları düzeltip tekrar gönderin.', 'textarea' ),
						'error_name'    => $text( 'Hata: Ad Eksik', 'Adınızı yazın.' ),
						'error_email'   => $text( 'Hata: E-posta Geçersiz', 'E-posta adresi geçerli görünmüyor.' ),
						'error_contact' => $text( 'Hata: Telefon ya da E-posta Eksik', 'Size dönebilmemiz için telefon ya da e-posta yazın.' ),
						'error_size'    => $text( 'Hata: Ölçü Eksik', 'Ölçü ve adet bilgisini yazın.' ),
						'error_session' => $text( 'Hata: Oturum Zaman Aşımı', 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.' ),
						'error_rate'    => $text( 'Hata: Çok Sık Gönderim', 'Az önce bir talep gönderdiniz; lütfen biraz sonra yeniden deneyin.' ),
						'error_save'    => $text( 'Hata: Kayıt Sorunu', 'Kayıt sırasında bir sorun oldu. Lütfen telefonla ulaşın.' ),
					),
				),
			),
		),

		/* ------------------------------------------------------------ *
		 * Bulunamayan sayfa (404). Onizleme adresi bilerek olmayan bir
		 * sayfa. Gizli: sekme degil, panelde "Sayfa bul" ile acilir;
		 * llms.txt ve SEO sayfa listesine girmez.
		 * ------------------------------------------------------------ */
		'notfound' => array(
			'label'      => '404 Sayfası',
			'path'       => '/bulunamadi-404/',
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Metinleri',
					'fields' => array(
						'title'           => $text( 'Başlık', $s['notfound_title'] ?? 'Aradığınız sayfa burada değil.' ),
						'text'            => $text( 'Açıklama', $s['notfound_text'] ?? 'Bağlantı eskimiş olabilir. Kereste çeşitlerine ürünler sayfasından, fiyat için teklif formundan ulaşabilirsiniz.', 'textarea' ),
						'products_button' => $text( 'Ürünler Düğmesi', 'Ürünler' ),
						'quote_button'    => $text( 'Teklif Düğmesi', 'Teklif al' ),
					),
				),
			),
		),
	);

	$manifest = array(
		'site_key'          => $s['key'],
		'site_label'        => $s['label'],
		// Panelde gorunen kisa ad: logodaki ad ("İthal Keresteci"), alan adi olmadan.
		'panel_label'       => $s['panel_label'] ?? implode( ' ', (array) ( $s['logo'] ?? array() ) ),
		'seo_site_defaults' => $s['seo'],
		// Calisma saatleri (arama verisi, bkz. kr_seo_opening_hours): gun listesi, acilis, kapanis.
		'opening_hours'     => $s['opening_hours'] ?? array(),
		'pages'             => $pages,
	);

	// Ayni bilgi hem her sayfada (ust menu, alt bilgi) hem Iletisim sayfasinda ayri alan.
	$hints = require __DIR__ . '/content-manifest-hints.php';

	return $hints(
		$manifest,
		array(
			'Bu telefon numarası'    => array( 'global.header.phone_label', 'contact.details.phone_label' ),
			'Bu cep numarası'        => array( 'global.header.mobile_label', 'contact.details.mobile_label' ),
			'Bu e-posta adresi'      => array( 'global.footer.email', 'contact.details.email' ),
			'Bu adres'               => array( 'global.footer.address', 'contact.details.address' ),
			'Çalışma saatleri'       => array( 'global.footer.hours', 'contact.details.hours' ),
		)
	);
};
