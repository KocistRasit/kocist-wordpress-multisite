<?php
/**
 * istanbulpaletcivi temasi alan manifesti.
 *
 * Saf bir dizi dondurur; hicbir WordPress fonksiyonuna bagimli degildir.
 *
 * Metinler istanbulpaletcivi.com'un kendi sayfalarindan alindi. Teknik olarak
 * yanlis anlatimlar duzeltildi (dokme civi = dizisiz civi; tele dizili civi =
 * serit tabancasiyla cakilan tel dizili civi); firmaya ozel yeni bir iddia
 * (olcu, stok, kapasite, sertifika) eklenmedi. Grup bilgisi diger 7 siteyle
 * ayni: Kestanelik / Catalca adresi, 50 yil.
 *
 * Alan turleri: text, textarea, url, image, repeater
 *
 * Her ornek blog yazisinin panelde gizli bir sayfasi vardir ('yazi-<slug>'):
 * "Sayfa bul" kutusundan acilir.
 */

/*
 * Uc urun sayfasi ayni alan yapisini paylasir.
 */
$product_page = static function ( string $label, string $path, array $card, array $detail, array $faq = array() ): array {
	return array(
		'label'      => $label,
		'path'       => $path,
		// Tema bu isaretle urun sablonunu secer.
		'template'   => 'product',
		'seo_source' => array(
			'title'       => 'card.name',
			'description' => 'detail.lead',
			'image'       => 'card.image',
			'type'        => 'Product',
		),
		'components' => array(

			'card' => array(
				'label'  => 'Ürün Kartı (listelerde görünür)',
				'fields' => array(
					'name'  => array( 'label' => 'Ürün Adı', 'type' => 'text', 'default' => $card['name'] ),
					'short' => array( 'label' => 'Kısa Açıklama', 'type' => 'textarea', 'default' => $card['short'] ),
					'tool'  => array( 'label' => 'Nasıl Çakılır (tek satır)', 'type' => 'text', 'default' => $card['tool'] ),
					'image' => array( 'label' => 'Ürün Görseli', 'type' => 'image', 'default' => 0 ),
				),
			),

			'detail' => array(
				'label'  => 'Ürün Sayfası',
				'fields' => array(
					'lead' => array( 'label' => 'Öne Çıkan Cümle', 'type' => 'textarea', 'default' => $detail['lead'] ),
					'body' => array( 'label' => 'Ürün Açıklaması (paragrafları boş satırla ayırın)', 'type' => 'textarea', 'default' => $detail['body'] ),
					'uses' => array(
						'label'   => 'Kullanıldığı İşler',
						'type'    => 'repeater',
						'max'     => 8,
						'fields'  => array(
							'text' => array( 'label' => 'İş', 'type' => 'text' ),
						),
						'default' => array_map( static fn( string $text ): array => array( 'text' => $text ), $detail['uses'] ),
					),
				),
			),

			'faq' => array(
				'label'  => 'Sık Sorulan Sorular (tip sayfasının altında)',
				'fields' => array(
					'items' => array(
						'label'   => 'Sorular',
						'type'    => 'repeater',
						'max'     => 8,
						'fields'  => array(
							'question' => array( 'label' => 'Soru', 'type' => 'text' ),
							'answer'   => array( 'label' => 'Cevap', 'type' => 'textarea' ),
						),
						'default' => $faq,
					),
				),
			),
		),
	);
};

$phone_label  = '+90 212 648 10 90';
$phone_url    = 'tel:+902126481090';
$mobile_label = '+90 532 374 98 32';
$mobile_url   = 'tel:+905323749832';
$whatsapp_url = 'https://wa.me/905323749832';
$email        = 'info@kocist.com.tr';
$address      = "Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1\nÇatalca, İstanbul";
$address_line = 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul';
$price_note   = 'Ölçü, stok ve fiyat bilgisi için bizi arayın ya da WhatsApp’tan yazın. Tabancanızın modelini ve çakacağınız tahtaların kalınlığını söylemeniz yeterli.';

/*
 * Her ornek blog yazisi icin panelde gizli bir sayfa ('yazi-<slug>'): "Sayfa
 * bul" kutusunda cikar, onizlemede yaziyi acar. Alanlar yazinin bugunku
 * metniyle dolu gelir; panelde degistirilen alan sitede WordPress yazisinin
 * yerine gecer (functions.php: Blog yazilari panelden). Degistirilmeyen alan
 * icin WordPress'teki yazi kullanilir.
 *
 * Yonetimden sonradan yazilan yazi burada cikmaz (manifest sabit); o yazi
 * Yazilar ekranindan duzenlenir.
 */
$pc_blog_pages = array();

foreach ( (array) include __DIR__ . '/content/blog-posts.php' as $pc_post ) {
	$pc_blog_pages[ 'yazi-' . $pc_post['slug'] ] = array(
		'label'      => 'Yazı: ' . $pc_post['title'],
		'path'       => '/' . $pc_post['slug'] . '/',
		'hidden'     => true,
		'components' => array(
			'post' => array(
				'label'  => 'Yazı',
				'fields' => array(
					'title'   => array( 'label' => 'Başlık', 'type' => 'text', 'default' => $pc_post['title'] ),
					'excerpt' => array( 'label' => 'Özet (arama sonucu)', 'type' => 'textarea', 'default' => $pc_post['excerpt'] ),
					'body'    => array(
						'label'   => 'Metin (boş satırla paragraf, ## ile ara başlık)',
						'type'    => 'textarea',
						'default' => $pc_post['body'],
						'hint'    => 'Burada değiştirdiğiniz metin sitede görünür. Değiştirmediğiniz alanlarda WordPress’teki yazı kullanılır.',
					),
					'image'   => array(
						'label'   => 'Kapak Görseli',
						'type'    => 'image',
						'default' => 0,
						'hint'    => 'Boş bırakılırsa yazının kendi kapak görseli kullanılır.',
					),
				),
			),
		),
	);
}

return array(
	'site_key'          => 'istanbulpaletcivi',
	'site_label'        => 'Koçist · istanbulpaletcivi.com',
	// Panelde gorunen kisa ad (alan adi olmadan).
	'panel_label'       => 'İstanbul Palet Çivi',

	'seo_site_defaults' => array(
		'name'        => 'İstanbul Palet Çivi',
		'legal_name'  => '',
		'description' => 'Rulo, tele dizili ve dökme palet çivisi. Çatalca’dan satış ve sevkiyat. Koçist Grup kuruluşu; grup 50 yıldır orman ürünleri alanında.',
		'phone'       => $phone_label,
		'email'       => $email,
		'street'      => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1',
		'district'    => 'Çatalca',
		'city'        => 'İstanbul',
		'postal_code' => '',
		'country'     => 'TR',
		'same_as'     => array(),
		'parent_name' => 'Koçist Orman Ürünleri',
		'parent_url'  => 'https://kocist.com.tr',
	),

	'pages'             => array(

		/* ---------------------------------------------------------- *
		 * Tum sayfalarda ortak: ust menu ve alt bilgi
		 * ---------------------------------------------------------- */
		'global' => array(
			'label'      => 'Tüm Sayfalar (Üst Menü, Alt Bilgi)',
			'path'       => '/',
			'components' => array(

				'header' => array(
					'label'  => 'Üst Menü',
					'fields' => array(
						'logo_image'     => array( 'label' => 'Logo', 'type' => 'image', 'default' => 0 ),
						'logo_text'      => array( 'label' => 'Firma Adı', 'type' => 'text', 'default' => 'İstanbul Palet Çivi' ),
						'menu'           => array(
							'label'   => 'Menü Öğeleri',
							'type'    => 'repeater',
							'max'     => 7,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı Adresi', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Anasayfa', 'url' => '/' ),
								array( 'label' => 'Hakkımızda', 'url' => '/hakkimizda/' ),
								array( 'label' => 'Palet Çivileri', 'url' => '/palet-civileri/' ),
								array( 'label' => 'Blog ve Haberler', 'url' => '/haberler-blog/' ),
								array( 'label' => 'SSS', 'url' => '/sss/' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/' ),
							),
						),
						'address'        => array( 'label' => 'Üst Şerit Adresi', 'type' => 'text', 'default' => $address_line ),
						'phone_label'    => array( 'label' => 'Telefon (görünen)', 'type' => 'text', 'default' => $phone_label ),
						'phone_url'      => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => $whatsapp_url ),
						'whatsapp_message' => array( 'label' => 'WhatsApp Hazır Mesajı', 'type' => 'text', 'default' => 'Merhaba, palet çivisi hakkında bilgi almak istiyorum.', 'hint' => 'WhatsApp açıldığında yazı kutusunda hazır gelir. Ürün düğmelerinde ürüne özel mesaj kullanılır.' ),
						'email'          => array( 'label' => 'E-posta', 'type' => 'text', 'default' => $email ),
						'menu_toggle'    => array( 'label' => 'Mobil Menü Düğmesi', 'type' => 'text', 'default' => 'Menü' ),
					),
				),

				'footer' => array(
					'label'  => 'Alt Bilgi',
					'fields' => array(
						'tagline'        => array( 'label' => 'Alt Bilgi Metni', 'type' => 'textarea', 'default' => 'Rulo, tele dizili ve dökme palet çivisi. Koçist Grup kuruluşudur; Çatalca’dan satış ve sevkiyat yapıyoruz.' ),
						'mobile_label'   => array( 'label' => 'Cep Telefonu (görünen)', 'type' => 'text', 'default' => $mobile_label ),
						'mobile_url'     => array( 'label' => 'Cep Telefonu Bağlantısı', 'type' => 'url', 'default' => $mobile_url ),
						'address'        => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => $address ),
						'copyright'      => array( 'label' => 'Telif Satırı', 'type' => 'text', 'default' => 'İstanbul Palet Çivi · Koçist Grup' ),
						'external_label' => array( 'label' => 'Grup Bağlantısı Metni', 'type' => 'text', 'default' => 'kocist.com.tr' ),
						'external_url'   => array( 'label' => 'Grup Bağlantısı', 'type' => 'url', 'default' => 'https://kocist.com.tr' ),
						'pages_title'    => array( 'label' => 'Sayfalar Sütunu Başlığı', 'type' => 'text', 'default' => 'Sayfalar' ),
						'products_title' => array( 'label' => 'Çiviler Sütunu Başlığı', 'type' => 'text', 'default' => 'Çiviler' ),
						'contact_title'  => array( 'label' => 'İletişim Sütunu Başlığı', 'type' => 'text', 'default' => 'İletişim' ),
						'call_label'     => array( 'label' => 'Mobil Alt Çubuk: Arama Düğmesi', 'type' => 'text', 'default' => 'Ara' ),
						'wa_label'       => array( 'label' => 'Mobil Alt Çubuk: WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
					),
				),

				'common' => array(
					'label'  => 'Ortak Metinler',
					'fields' => array(
						'crumb_home'  => array( 'label' => 'Konum Yolu: Ana Sayfa', 'type' => 'text', 'default' => 'Anasayfa' ),
						'sample_note' => array( 'label' => 'Örnek Görsel Notu', 'type' => 'text', 'default' => 'Örnek görsel' ),
					),
				),
			),
		),

		/* ---------------------------------------------------------- *
		 * Ana sayfa
		 * ---------------------------------------------------------- */
		'home' => array(
			'label'      => 'Ana Sayfa',
			'path'       => '/',
			'seo_source' => array(
				'title'       => 'hero.title',
				'description' => 'hero.lead',
				'image'       => 'hero.image',
			),
			'components' => array(

				'hero' => array(
					'label'  => 'Giriş',
					'fields' => array(
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Palet çivisi' ),
						'lead'       => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Rulo, tele dizili ve dökme palet çivisi. Çatalca’dan satış ve sevkiyat yapıyoruz.' ),
						'call_label' => array( 'label' => 'Arama Düğmesi', 'type' => 'text', 'default' => 'Telefonla sipariş' ),
						'wa_label'   => array( 'label' => 'WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp’tan yazın' ),
						'image'      => array( 'label' => 'Fotoğraf', 'type' => 'image', 'default' => 0 ),
						'image_note' => array( 'label' => 'Fotoğraf Notu (gerçek fotoğraf gelince silin)', 'type' => 'text', 'default' => 'Örnek görsel' ),
					),
				),

				'ruler' => array(
					'label'  => 'Sarı Cetvel Şeridi',
					'fields' => array(
						'text'  => array( 'label' => 'Metin', 'type' => 'text', 'default' => 'Palet çivi ürünlerini inceleyin ve sipariş verin' ),
						'label' => array( 'label' => 'Düğme', 'type' => 'text', 'default' => 'Palet çivileri' ),
					),
				),

				'products' => array(
					'label'  => 'Ürünler',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Çivi tipleri, tabancanıza göre' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Hangi çivinin size uyduğu, elinizdeki tabancaya ve yaptığınız işe bağlı. Ölçü ve fiyat için arayın.' ),
						'more'  => array( 'label' => 'Ürün Bağlantısı Metni', 'type' => 'text', 'default' => 'Ürünü inceleyin' ),
					),
				),

				'uses' => array(
					'label'  => 'Kullanım Alanları',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Kullanım alanları' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Çivilerin kullanım alanlarını inceleyin.' ),
						'items' => array(
							'label'   => 'Alanlar',
							'type'    => 'repeater',
							'max'     => 9,
							'fields'  => array(
								'title' => array( 'label' => 'Alan', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Ambalajlama', 'text' => 'Ahşap paletlerin ve sandıkların yapımında çiviler kullanılır. Bu çiviler yükleri taşımak ve ambalajı güçlendirmek için kullanılır.' ),
								array( 'title' => 'Ahşap işleri', 'text' => 'Ahşap palet, kafes ve diğer ahşap ürünlerin imalatında palet çivisi kullanılır. Kullanılacak çivi ürüne göre farklılık gösterebilir.' ),
								array( 'title' => 'İnşaat sektörü', 'text' => 'Çiviler yapıların inşasında sıkça kullanılır. Ahşap karkas, kalıp ve çatı işlerinde ahşap elemanları bir arada tutar.' ),
								array( 'title' => 'Mobilya sektörü', 'text' => 'Ahşap mobilya üretiminde çiviler yaygın bir bağlama elemanıdır. Parçaları bir arada tutar; çoğu zaman sonradan gizlenir ya da dolgu malzemesiyle kapatılır.' ),
								array( 'title' => 'Marangozluk', 'text' => 'Ahşap işleri ve marangozluk projelerinde ahşap parçaları bir araya getirmek ve sabitlemek için kullanılır.' ),
								array( 'title' => 'Sanat ve el işleri', 'text' => 'Bazı sanat projelerinde ve el işlerinde malzemeleri bir arada tutar. Örneğin iplik ya da sicimle birbirine bağlanan çivilerle duvar süslemeleri yapılır.' ),
							),
						),
					),
				),

				'group' => array(
					'label'  => 'Koçist Grup Bandı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Koçist Orman Ürünleri olarak palet çivisi tedarik ediyoruz.' ),
						'text'  => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'İstanbul Palet Çivi, Koçist Grup kuruluşudur. Grup 50 yıldır orman ürünleri alanında çalışıyor; ahşap palet, sandık ve kafes üretiyor.' ),
					),
				),

				'blog' => array(
					'label'  => 'Blog Bölümü',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Palet çivisi hakkında bilmeniz gerekenler' ),
						'more'  => array( 'label' => 'Tüm Yazılar Bağlantısı', 'type' => 'text', 'default' => 'Tüm yazılar' ),
					),
				),

				'contact' => array(
					'label'  => 'Sipariş Bölümü',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sipariş ve fiyat' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Çivi tipini, ölçüyü ve adedi yazın; size en kısa sürede dönüş yapalım. Acil siparişte arayın.' ),
					),
				),
			),
		),

		/* ---------------------------------------------------------- *
		 * Palet Civileri (urunler sayfasi)
		 * ---------------------------------------------------------- */
		'products' => array(
			'label'      => 'Palet Çivileri',
			'path'       => '/palet-civileri/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İstanbul palet çivileri' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Rulo, şerit ve dökme palet çivileri; beton ve ince iş çivileri. Palet, sandık ve kafes üretiminden mobilya ve şantiyeye; aralarındaki fark, nasıl ve neye çakıldıkları.' ),
					),
				),
				'help' => array(
					'label'  => 'Yardım Kutusu',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Size nasıl yardımcı olabiliriz?' ),
						'text'  => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'Hangi çivinin size uyduğundan emin değilseniz arayın; tabancanızın modelini ve yaptığınız işi söyleyin, doğru çiviyi birlikte seçelim.' ),
						'wa_label' => array( 'label' => 'WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
					),
				),
				'shared' => array(
					'label'  => 'Ürün Sayfalarında Ortak',
					'fields' => array(
						'price_note' => array( 'label' => 'Fiyat ve Ölçü Notu', 'type' => 'textarea', 'default' => $price_note ),
						'others'     => array( 'label' => 'Diğer Ürünler Başlığı', 'type' => 'text', 'default' => 'Diğer çivi tipleri' ),
						'uses_title'  => array( 'label' => 'Kullanıldığı İşler Başlığı', 'type' => 'text', 'default' => 'Kullanıldığı işler' ),
						'order_title' => array( 'label' => 'Sipariş Kutusu Başlığı', 'type' => 'text', 'default' => 'Ölçü ve fiyat' ),
						'wa_label'    => array( 'label' => 'Sipariş Kutusu: WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp’tan fiyat sorun' ),
						'form_label'  => array( 'label' => 'Sipariş Kutusu: Form Bağlantısı', 'type' => 'text', 'default' => 'Formla sipariş isteği gönderin' ),
						'wa_message'  => array( 'label' => 'Ürün WhatsApp Hazır Mesajı', 'type' => 'text', 'default' => 'Merhaba, {urun} hakkında bilgi almak istiyorum.', 'hint' => '{urun} yerine ürünün adı yazılır; sonuna ürün sayfasının adresi eklenir.' ),
						'specs_title'    => array( 'label' => 'Teknik Özellikler Başlığı', 'type' => 'text', 'default' => 'Teknik özellikler' ),
						'sizes_title'    => array( 'label' => 'Ölçü Tablosu Başlığı', 'type' => 'text', 'default' => 'Ölçüler ve paketleme' ),
						'items_title'    => array( 'label' => 'Tip Sayfası: Ürün Listesi Başlığı', 'type' => 'text', 'default' => '{tip} çeşitleri', 'hint' => '{tip} yerine çivi tipinin adı yazılır.' ),
						'items_lead'     => array( 'label' => 'Tip Sayfası: Ürün Listesi Alt Metni', 'type' => 'textarea', 'default' => 'Ölçü, gövde ve kaplamaya göre stoktaki seçenekler. Listede olmayan ölçüler için arayın.' ),
						'faq_title'      => array( 'label' => 'Tip Sayfası: Soru-Cevap Başlığı', 'type' => 'text', 'default' => '{tip} hakkında sık sorulanlar', 'hint' => '{tip} yerine çivi tipinin adı yazılır.' ),
						'siblings_title' => array( 'label' => 'Ürün Sayfası: Diğer Çeşitler Başlığı', 'type' => 'text', 'default' => 'Diğer {tip}', 'hint' => '{tip} yerine çivi tipinin adı yazılır.' ),
					),
				),
				'catalog' => array(
					'label'  => 'Ürün Listesi (çeşit bazlı)',
					'fields' => array(
						'items' => array(
							'label'   => 'Ürünler',
							'type'    => 'repeater',
							'max'     => 120,
							'fields'  => array(
								'slug'            => array( 'label' => 'Adres Adı (civiler/…)', 'type' => 'text' ),
								'title'           => array( 'label' => 'Ürün Adı', 'type' => 'text' ),
								'group'           => array( 'label' => 'Çivi Tipi (tip sayfasının adres adı: rulo-palet-civiler, tele-dizili-civiler, dokme-civiler…)', 'type' => 'text' ),
								'short'           => array( 'label' => 'Kart Açıklaması (kısa)', 'type' => 'textarea' ),
								'body'            => array( 'label' => 'Ürün Açıklaması (boş satırla paragraf)', 'type' => 'textarea' ),
								'specs'           => array( 'label' => 'Teknik Özellikler (her satır "Ad: değer")', 'type' => 'textarea' ),
								'sizes'           => array( 'label' => 'Ölçü Tablosu (ilk satır başlıklar; sütunlar "|" ile ayrılır)', 'type' => 'textarea', 'hint' => 'Örnek: Ebat (mm) | Ad/Rulo | Ad/Koli — altına her ölçü için bir satır.' ),
								'image'           => array( 'label' => 'Ürün Görseli', 'type' => 'image' ),
								'seo_title'       => array( 'label' => 'Meta Başlık (≤60)', 'type' => 'text' ),
								'seo_description' => array( 'label' => 'Meta Açıklama (≤160)', 'type' => 'textarea' ),
							),
							'default' => (array) include __DIR__ . '/content/catalog.php',
						),
					),
				),
				'list' => array(
					'label'  => 'Ürün Satırı Düğmeleri',
					'fields' => array(
						'more' => array( 'label' => 'Ürün Bağlantısı Metni', 'type' => 'text', 'default' => 'Ürünü inceleyin' ),
						'ask'  => array( 'label' => 'Fiyat Sorma Düğmesi (WhatsApp)', 'type' => 'text', 'default' => 'Fiyat sorun' ),
					),
				),
			),
		),

		'rulo' => $product_page(
			'Ürün: Rulo Palet Çiviler',
			'/civiler/rulo-palet-civiler/',
			array(
				'name'  => 'Rulo Palet Çiviler',
				'short' => 'İnce tellerle birbirine bağlanıp rulo hâline getirilmiş çiviler. Palet ve sandık üretiminde en yaygın tip.',
				'tool'  => 'Rulo çivi tabancasıyla çakılır',
			),
			array(
				'lead' => 'Rulo palet çivisi, çivilerin ince tellerle birbirine bağlanıp rulo (bobin) hâline getirildiği çivi tipidir. Rulo çivi tabancasıyla hızlı ve seri çakılır.',
				'body' => "Rulo çiviler çelik telden üretilir. Çiviler yan yana dizilir, ince tellerle birbirine tutturulur ve düz bir rulo hâlinde sarılır. Rulo tabancanın haznesine takılır; tabanca her tetikte bir çiviyi ayırıp çakar.\n\nPalet ve sandık üretiminde en çok kullanılan çivi tipidir. Tek tek çakmaya göre çok daha hızlıdır ve seri üretimde işçilik süresini kısaltır. Ahşap döşeme, çit, çatı kaplaması ve kasa yapımında da kullanılır.\n\nGövdesi düz, halkalı ya da vida dişli olabilir. Halkalı ve vida dişli gövde ahşapta daha sıkı tutunur, çivinin zamanla gevşemesini azaltır. Çıplak (parlak) ve galvaniz kaplamalı seçenekler vardır; nemli ortamda ve dış mekânda galvaniz tercih edilir.\n\nÇivinin boyu ve kalınlığı, birleştirilecek tahtaların kalınlığına ve tabancanızın kabul ettiği ölçüye göre seçilir. Çakarken çivinin doğru yerleştiğinden emin olun, koruyucu gözlük ve eldiven kullanın.",
				'uses' => array( 'Palet üretimi ve onarımı', 'Sandık ve kafes', 'Ahşap döşeme', 'Çit ve çatı kaplaması', 'Mobilya imalatı' ),
			),
			array(
				array( 'question' => 'Rulo çivi ile tele dizili çivi aynı şey mi?', 'answer' => 'Evet. Çiviler iki ince telle kaynaklanıp bobin (rulo) halinde sarılır; sektörde "tele dizili", "rulo" ve "coil" aynı ürün için kullanılır.' ),
				array( 'question' => 'Rulo palet çivisi kaç derece olmalı?', 'answer' => 'Türkiye\'de satılan rulo tabancaların büyük çoğunluğu 16° tel dizimli çivi kullanır; Sertel rulo serisi 16°\'dir. 15° ithal tabancalarda şarjör toleransı modeline göre değişir, model bilgisini iletin.' ),
				array( 'question' => 'Bir kolide kaç rulo çivi var?', 'answer' => 'Ebada göre değişir: 2,5x40 mm\'de 300\'lük 40 rulo (12.000 adet), 2,5x50–70 mm\'de 300\'lük 30 rulo (9.000 adet), 2,8x80 mm\'de 250\'lik 20 rulo (5.000 adet), 3,4x130 mm\'de 175\'lik 10 rulo (1.750 adet).' ),
				array( 'question' => 'Halkalı mı düz mü almalıyım?', 'answer' => 'Çam gibi yumuşak ahşapta ve yeniden kullanılan paletlerde ring (halkalı) gövde çivinin geri çıkmasını engeller; tek seferlik hafif paletlerde düz gövde yeterlidir; meşe gibi sert ahşapta helikal (vidalı) gövde çatlamayı azaltır.' ),
			)
		),

		'tele' => $product_page(
			'Ürün: Tele Dizili Çiviler',
			'/civiler/tele-dizili-civiler/',
			array(
				'name'  => 'Tele Dizili Çiviler',
				'short' => 'İnce tellerle yan yana şerit hâlinde dizilmiş çiviler. Uzun gövdeli çivilerde ve kalın ahşap birleştirmede yaygın.',
				'tool'  => 'Şerit çivi tabancasıyla çakılır',
			),
			array(
				'lead' => 'Tele dizili çivi, çivilerin ince tellerle yan yana şerit hâlinde dizildiği çivi tipidir. Şerit çivi tabancasıyla çakılır.',
				'body' => "Çiviler düz bir şerit boyunca yan yana dizilir ve ince tellerle birbirine bağlanır. Şerit tabancanın şarjörüne yerleştirilir; tabanca her tetikte bir çiviyi teli koparak çakar. Şeritler genellikle açılıdır; açı, tabancanın şarjörüne uygun seçilmelidir.\n\nUzun gövdeli çivilerde yaygındır. Ahşap karkas ve çatı iskeleti, kalıp işleri, kasa ve palet üretimi gibi kalın ahşabın birleştirildiği işlerde kullanılır. Çivilerin düzenli dizilmesi, her çakışta aynı derinlikte ve sağlam bir bağlantı sağlar.\n\nRulo çiviye göre şarjörde daha az çivi taşır, ama uzun ve kalın çivilerde tabancayı daha dengeli tutar. Hangi tipi kullanacağınız elinizdeki tabancaya bağlıdır.\n\nGövde tipi (düz, halkalı), kaplama (çıplak, galvaniz) ve ölçü seçimi için bizi arayın; tabancanızın modelini söylemeniz yeterli.",
				'uses' => array( 'Ahşap karkas ve çatı iskeleti', 'Kalıp işleri', 'Kasa yapımı', 'Palet üretimi', 'Ahşap zemin döşeme' ),
			),
			array(
				array( 'question' => '21°, 28° ve 34° çivi arasındaki fark ne?', 'answer' => 'Açı, şeritin tabanca şarjörüne giriş eğimidir. 21° plastik şeritli tam başlı, 28° tel kaynaklı şeritli, 34° kağıt şeritli kesik başlı çividir; her tabanca yalnızca kendi açısındaki şeridi kabul eder.' ),
				array( 'question' => 'Kağıda dizili çivi neden kesik başlı?', 'answer' => 'Kesik (D) baş çivilerin birbirine daha yakın dizilmesini sağlar; böylece şarjöre daha çok çivi sığar ve dar köşelerde çakım kolaylaşır.' ),
				array( 'question' => 'Plastiğe dizili çivi paletçilikte kullanılır mı?', 'answer' => 'Çoğunlukla karkas ve kalıp işinde kullanılır; ağır sandık ve kablo makarası gibi 50 mm üstü kalın birleşimlerde 2,87–3,3 mm ebatlar palet atölyelerinde de tercih edilir.' ),
				array( 'question' => 'Şerit çiviyi nasıl saklamalıyım?', 'answer' => 'Kağıt bant nemde yumuşar; kolileri kapalı ve kuru ortamda, yere değmeden saklayın. Plastik şerit nemden etkilenmez.' ),
			)
		),

		'dokme' => $product_page(
			'Ürün: Dökme Çiviler',
			'/civiler/dokme-civiler/',
			array(
				'name'  => 'Dökme Çiviler',
				'short' => 'Tel ya da bantla dizilmemiş, toplu hâlde satılan çiviler. Elle çakımda ve onarım işlerinde pratik.',
				'tool'  => 'Çekiçle ya da dökme çivi kullanan makinelerde',
			),
			array(
				'lead' => 'Dökme çivi, herhangi bir tel ya da bantla dizilmemiş, toplu hâlde satılan çividir. Çekiçle elle çakılır ya da dökme çivi besleyen makinelerde kullanılır.',
				'body' => "Dökme çiviler çelik telden, başı ve ucu şekillendirilerek üretilir; dizme işleminden geçmez. Bu yüzden kilo ya da koli ile satılır.\n\nTabanca kullanılmayan işlerde, onarımda, küçük adetli üretimde ve dökme çivi kullanan çakım makinelerinde tercih edilir. Palet onarımı, sandık kapağı ve saha işlerinde pratiktir.\n\nBaşı düz, gövdesi düz ya da halkalı olabilir; çıplak ve galvaniz seçenekleri vardır. Dayanıklılık gereken işlerde halkalı gövde ahşapta daha iyi tutunur.\n\nİhtiyacınız olan boyu ve kalınlığı söyleyin, stok ve fiyat bilgisini iletelim.",
				'uses' => array( 'Palet onarımı', 'Sandık ve kafes kapağı', 'Elle çakım gereken işler', 'Küçük adetli üretim', 'Saha ve şantiye işleri' ),
			),
			array(
				array( 'question' => 'Dökme çivi dökme demirden mi yapılır?', 'answer' => 'Hayır. Dökme çivi düşük karbonlu çelik telden soğuk şekillendirilir; "dökme" kelimesi çivilerin dizilmeden kutuya dökülmüş olmasını anlatır.' ),
				array( 'question' => 'EPAL çivisi ile normal palet çivisi farkı ne?', 'answer' => 'EPAL çivisinin kafasında üreticiyi tanımlayan iki harfli kod (AT, AU, AV, AW, AX, AY, AZ) bulunur ve çap–boy değerleri EPAL şartnamesinde sabittir; lisanslı Euro palet üretiminde bu kodlu çivi zorunludur.' ),
				array( 'question' => 'Bir EPAL palette kaç çivi kullanılır?', 'answer' => '78 ile 81 adet arasında; takoz, üst tahta ve alt tahta için farklı kodlu çiviler kullanılır.' ),
				array( 'question' => 'Spike çivi nereye çakılır?', 'answer' => '20–30 cm boyundaki spike çiviler kalın palet takozu, kalas birleştirme, ahşap travers ve kablo makarası gövdesi gibi 100 mm üzeri kesitlerde kullanılır; 8,3 mm burgulu gövde kendini ahşaba kilitler.' ),
			)
		),

		'beton' => $product_page(
			'Ürün: Beton Çivileri',
			'/civiler/beton-civileri/',
			array(
				'name'  => 'Beton Çivileri',
				'short' => 'Sertleştirilmiş çelikten beton, tuğla ve sıva çivileri: elden çakılan çelik çivi, gazlı ve havalı tabanca serileri, tavan askı çivisi.',
				'tool'  => 'Çekiçle, gazlı ya da havalı beton tabancasıyla çakılır',
			),
			array(
				'lead' => 'Beton çivisi, ısıl işlemle sertleştirilmiş çelikten üretilir; betona, tuğlaya ve sıvaya bükülmeden girer.',
				'body' => 'Beton çivileri sertleştirilmiş çelikten üretilir; betona, tuğlaya ve sıvaya çekiç, havalı veya gazlı tabancayla çakılır. Sertel grubunda elden çakılan Çelik Beton Çivisi (3x30–5x100 mm), gazlı tabancalar için GP 2,9 plastiğe dizili seri, havalı tabancalar için NK serisi (22–72 mm) ve asma tavan için L tipi askı çivisi bulunur. Palet ve ambalaj tesislerinde beton zemine kızak ve çıta montajında, şantiyede kalıp ve alçıpan işlerinde kullanılır. KOÇİST poşet, kutu ve koli bazında ekonomik fiyat verir; İstanbul ve Trakya\'ya kendi araçlarımızla sevk eder, Çatalca showroomundan elden teslim eder.',
				'uses' => array( 'Beton zemine kızak ve çıta montajı', 'Kalıp ve şantiye işleri', 'Alçıpan ve asma tavan askısı', 'Palet ve ambalaj tesisi zemin işleri', 'Elektrik ve tesisat kelepçeleri' ),
			),
			array(
				array( 'question' => 'Beton çivisi normal çividen farkı ne?', 'answer' => 'Beton çivisi ısıl işlemle sertleştirilmiş çelikten yapılır; normal tel çivi yumuşak çeliktir ve betona çakılınca bükülür.' ),
				array( 'question' => 'Gazlı tabanca çivisi hangi tabancada çalışır?', 'answer' => 'GP 2,9 mm plastiğe dizili seri, gaz kartuşuyla çalışan beton tabancalarında kullanılır; her kutu 1.000 çivi ve gaz içerir.' ),
				array( 'question' => 'NK çivi nedir?', 'answer' => 'Havalı beton tabancalarında kullanılan, 5,6 mm kafalı dizili çivi tipidir; 22 mm\'den 72 mm\'ye kadar on boyu vardır.' ),
				array( 'question' => 'Çelik beton çivisi kolide kaç adet gelir?', 'answer' => '100\'lük poşetlerde; 3x30 mm kolide 100 poşet (10.000 adet), 4x40–60 mm kolide 50 poşet (5.000 adet), 5x80–100 mm kolide 25 poşet (2.500 adet).' ),
			)
		),

		'inceis' => $product_page(
			'Ürün: İnce İş Çivileri',
			'/civiler/ince-is-civileri/',
			array(
				'name'  => 'İnce İş Çivileri',
				'short' => 'Görünür iz bırakmadan birleştiren tabanca çivileri: T başlı ST, lambiri için FST, 16–23 GA brad ve başsız pin çivi.',
				'tool'  => 'Havalı ince iş (brad / pin) tabancasıyla çakılır',
			),
			array(
				'lead' => 'İnce iş çivileri, mobilya, ambalaj kutusu ve dekorasyon işlerinde parçayı iz bırakmadan tutan ince tel çivilerdir.',
				'body' => 'İnce iş çivileri, ahşapta görünür iz bırakmadan birleştirme yapan tabanca çivileridir. Sertel grubunda T başlı Çelik ST Çivi (2,2 mm, 18–64 mm), lambiri ve süpürgelik için FST serisi (1,8 mm, 20–40 mm), 16–23 GA Brad çiviler ve başsız Pin çivi yer alır. Mobilya atölyeleri, ambalaj kutusu üreticileri, kapı-pencere ve dekorasyon ekipleri kullanır. KOÇİST tüm GA ve boyları tek teklifte ekonomik fiyatla sunar; stoktan hızlı çıkış, İstanbul ve Trakya\'ya kendi araçlarımızla sevkiyat, Çatalca showroomunda tabanca denemesi. 0212 648 19 19.',
				'uses' => array( 'Mobilya ve koltuk iskeleti', 'Kontrplak ambalaj kutusu', 'Lambiri ve süpürgelik', 'Kapı, pencere ve pervaz', 'Dekorasyon ve çıta işleri' ),
			),
			array(
				array( 'question' => 'GA (gauge) ne demek?', 'answer' => 'Çivi telinin kalınlık numarasıdır; sayı büyüdükçe çivi incelir. 16 GA 1,6 mm, 18 GA 1,25 mm, 21 GA 0,81 mm, 23 GA 0,64 mm teldir.' ),
				array( 'question' => 'Brad çivi ile pin çivi farkı ne?', 'answer' => 'Brad çivinin küçük bir başı vardır ve tutuş sağlar; pin çivi başsızdır, yalnızca tutkal kururken parçayı sabitler ve neredeyse iz bırakmaz.' ),
				array( 'question' => 'ST çivi ile FST çivi farkı ne?', 'answer' => 'ST 2,2 mm çapında T başlı, betona ve sert zemine çakılan sertleştirilmiş çividir; FST 1,8 mm çapında daha ince olup lambiri ve süpürgelik gibi ince çıtalar içindir.' ),
				array( 'question' => 'Ambalaj kutusu için hangi brad çivi?', 'answer' => 'Kontrplak ve ince tahta kutularda 16 GA T serisi 30–40 mm; MDF ve ince kaplama kapaklarda 18 GA F serisi 20–30 mm yaygındır.' ),
			)
		),

		/* ---------------------------------------------------------- *
		 * Hakkimizda
		 * ---------------------------------------------------------- */
		'about' => array(
			'label'      => 'Hakkımızda',
			'path'       => '/hakkimizda/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Hakkımızda' ),
						'lead'  => array( 'label' => 'Öne Çıkan Cümle', 'type' => 'textarea', 'default' => 'İstanbul Palet Çivi, Koçist Grup’un palet çivisi tedarik markasıdır. Palet ve ambalaj üreticilerine rulo, tele dizili ve dökme çivi sağlıyoruz.' ),
					),
				),
				'profile' => array(
					'label'  => 'Profilimiz',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Profilimiz' ),
						'text'  => array( 'label' => 'Metin (paragrafları boş satırla ayırın)', 'type' => 'textarea', 'default' => "Koçist Grup 50 yıldır orman ürünleri alanında çalışıyor; her ölçüde kereste, palet, sandık ve kafes üretiyor. İstanbul Palet Çivi bu deneyimle, palet ve ambalaj üreticilerinin çivi ihtiyacını karşılıyor.\n\nKaliteli, dürüst ve hızlı bir satış ilkesi benimsedik. Amacımız en kaliteli malzemeyi en kısa zamanda müşterimizin hizmetine sunmak; bu doğrultuda daha kaliteli ve daha hesaplı malzemeyi sunma ilkesiyle çalışıyoruz." ),
					),
				),
				'quality' => array(
					'label'  => 'Kalite Politikamız',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Kalite politikamız' ),
						'items' => array(
							'label'   => 'Maddeler',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'text' => array( 'label' => 'Madde', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'text' => 'Ürünlerimizi zamanında teslim etmek, uygun fiyat avantajı sağlamak ve kaliteli ürün sunarak müşteri ihtiyaç ve beklentilerini karşılamak.' ),
								array( 'text' => 'Kalite bilincini yerleştirerek çalışanlarımızın sağlığı ve çevrenin korunması için maddi ve insan kaynaklarımızı seferber etmek, tüm çalışanlarımızın gelişmesini sağlamak.' ),
								array( 'text' => 'Kuruluş olarak her alanda sürekli iyileşme ve gelişme sağlamak.' ),
								array( 'text' => 'Yaptığımız işi ilk seferinde ve her seferinde doğru yapmak; müşteri istek ve görüşlerini esas almak, standartlara ve kalite yönetim sistemimize uygun çalışmak.' ),
								array( 'text' => 'Kalite bilincinin artması için tedarikçilerimizle koordineli çalışmak.' ),
							),
						),
					),
				),
				'group' => array(
					'label'  => 'Koçist Grup',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Koçist Grup' ),
						'text'  => array( 'label' => 'Metin (paragrafları boş satırla ayırın)', 'type' => 'textarea', 'default' => "Koçist Grup, orman ürünleri alanındaki yatırımıyla 50 yıldır hizmet veriyor. Grup, bu tecrübeyle yapı ve inşaat sektöründe de malzeme tedarikçisi olarak çalışıyor.\n\nKoçist Yapı İnşaat, yaşam ve çalışma alanları inşa etmeyi hedefliyor. Koçist Grup 2016 yılından itibaren Koçist Teknoloji markasıyla bilişim alanında da yatırım yapıyor." ),
					),
				),
			),
		),

		/* ---------------------------------------------------------- *
		 * Blog
		 * ---------------------------------------------------------- */
		'blog' => array(
			'label'      => 'Blog ve Haberler',
			'path'       => '/haberler-blog/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Blog ve haberler' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Palet çivisi, rulo çivi, tele dizili çivi ve dökme çivi hakkında bilmeniz gerekenler.' ),
					),
				),
				'list' => array(
					'label'  => 'Yazı Listesi',
					'fields' => array(
						'prev'          => array( 'label' => 'Önceki Sayfa Bağlantısı', 'type' => 'text', 'default' => 'Önceki' ),
						'next'          => array( 'label' => 'Sonraki Sayfa Bağlantısı', 'type' => 'text', 'default' => 'Sonraki' ),
						'empty'         => array( 'label' => 'Yazı Yokken Görünen Metin', 'type' => 'text', 'default' => 'Henüz yazı yok.' ),
						'archive_empty' => array( 'label' => 'Boş Arşiv Metni', 'type' => 'text', 'default' => 'Bu sayfada içerik yok.' ),
						'search_title'  => array( 'label' => 'Arama Sonuçları Başlığı', 'type' => 'text', 'default' => 'Arşivler' ),
					),
				),
				'post' => array(
					'label'  => 'Yazı Sayfası',
					'fields' => array(
						'cta'      => array( 'label' => 'Sipariş Çağrısı', 'type' => 'textarea', 'default' => 'Palet çivisi siparişi ve fiyat için arayın ya da WhatsApp’tan yazın.' ),
						'wa_label' => array( 'label' => 'WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
						'others'   => array( 'label' => 'Diğer Yazılar Başlığı', 'type' => 'text', 'default' => 'Diğer yazılar' ),
					),
				),
			),
		),

		/*
		 * Sik sorulan sorular (/sss/). Cevaplar yalnizca sitenin mevcut
		 * metinlerinden (urun sayfalari, fiyat notu). Cevabi bos soru firmadan
		 * cevap bekliyor (DEVAM.md): sitede ve arama verisinde gorunmez.
		 */
		'faq' => array(
			'label'      => 'Sık Sorulan Sorular',
			'path'       => '/sss/',
			'seo_source' => array(
				'type'        => 'FAQPage',
				'questions'   => 'items.rows',
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head'  => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sık sorulan sorular' ),
						'lead' => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Rulo, tele dizili ve dökme çivi; tabanca uyumu, çivi boyu ve sipariş hakkında en çok sorulanlar.' ),
					),
				),
				'items' => array(
					'label'  => 'Sorular',
					'fields' => array(
						'rows' => array(
							'label'   => 'Soru ve Cevaplar',
							'type'    => 'repeater',
							'max'     => 40,
							'hint'    => 'Cevabı boş soru sitede ve arama verisinde görünmez; cevabı firmadan gelince doldurun. Aynı grup adı bir başlık altında toplanır.',
							'fields'  => array(
								'group'    => array( 'label' => 'Grup', 'type' => 'text' ),
								'question' => array( 'label' => 'Soru', 'type' => 'text' ),
								'answer'   => array( 'label' => 'Cevap', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'group' => 'Çivi seçimi', 'question' => 'Rulo, tele dizili ve dökme çivi arasındaki fark nedir?', 'answer' => 'Üçü de palet, sandık ve kafes üretiminde kullanılır; fark nasıl çakıldıklarında. Rulo çivi ince tellerle bağlanıp rulo hâline getirilir ve rulo çivi tabancasıyla çakılır. Tele dizili çivi ince tellerle yan yana şerit hâlinde dizilir ve şerit çivi tabancasıyla çakılır. Dökme çivi dizilmemiştir; çekiçle ya da dökme çivi besleyen makinelerde kullanılır.' ),
								array( 'group' => 'Çivi seçimi', 'question' => 'Tabancama hangi çivi uyar?', 'answer' => 'Rulo çivi tabancası rulo çiviyle, şerit çivi tabancası tele dizili çiviyle çalışır. Şeritler genellikle açılıdır; açı, tabancanın şarjörüne uygun seçilmelidir. Emin değilseniz tabancanızın modelini söyleyin, doğru çiviyi birlikte seçelim.' ),
								array( 'group' => 'Çivi seçimi', 'question' => 'Çivi boyu nasıl seçilir?', 'answer' => 'Çivinin boyu ve kalınlığı, birleştirilecek tahtaların kalınlığına ve tabancanızın kabul ettiği ölçüye göre seçilir. Tabancanızın modelini ve çakacağınız tahtaların kalınlığını söylemeniz yeterli.' ),
								array( 'group' => 'Sipariş', 'question' => 'Fiyat ve stok nasıl öğrenilir?', 'answer' => 'Ölçü, stok ve fiyat bilgisi için bizi arayın ya da WhatsApp’tan yazın. Tabancanızın modelini ve çakacağınız tahtaların kalınlığını söylemeniz yeterli.' ),
								array( 'group' => 'Sipariş', 'question' => 'Koli ya da kutuda kaç çivi var?', 'answer' => '' ),
								array( 'group' => 'Sipariş', 'question' => 'En az sipariş miktarı var mı, toptan satış yapıyor musunuz?', 'answer' => '' ),
								array( 'group' => 'Sipariş', 'question' => 'Teslimat nasıl ve ne kadar sürede yapılıyor?', 'answer' => '' ),
							),
						),
					),
				),
				'more'  => array(
					'label'  => 'Kapanış',
					'fields' => array(
						'group_other' => array( 'label' => 'Grubu boş sorular için grup adı', 'type' => 'text', 'default' => 'Genel' ),
						'text'        => array( 'label' => 'Kapanış satırı (yanındaki numara sitenin telefonudur)', 'type' => 'text', 'default' => 'Sorunuzun cevabı burada yoksa arayın:' ),
					),
				),
			),
		),

		/* ---------------------------------------------------------- *
		 * Iletisim
		 * ---------------------------------------------------------- */
		'contact' => array(
			'label'      => 'İletişim',
			'path'       => '/iletisim/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Bize ulaşın' ),
						'lead'  => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Aşağıdaki iletişim bilgilerinden bize ulaşabilirsiniz; size en kısa sürede dönüş yaparız.' ),
					),
				),
				'details' => array(
					'label'  => 'İletişim Bilgileri',
					'fields' => array(
						'address'   => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => $address ),
						'map_query' => array( 'label' => 'Haritada Aranacak Adres', 'type' => 'text', 'default' => $address_line ),
						'hours'     => array( 'label' => 'Çalışma Saatleri (boşsa gizlenir)', 'type' => 'text', 'default' => '' ),
						'phone_title'   => array( 'label' => 'Telefon Satırı Başlığı', 'type' => 'text', 'default' => 'Telefon' ),
						'mobile_title'  => array( 'label' => 'Cep Satırı Başlığı', 'type' => 'text', 'default' => 'Cep ve WhatsApp' ),
						'wa_label'      => array( 'label' => 'WhatsApp Bağlantısı Metni', 'type' => 'text', 'default' => 'WhatsApp’tan yazın' ),
						'email_title'   => array( 'label' => 'E-posta Satırı Başlığı', 'type' => 'text', 'default' => 'E-posta' ),
						'address_title' => array( 'label' => 'Adres Satırı Başlığı', 'type' => 'text', 'default' => 'Adres' ),
						'hours_title'   => array( 'label' => 'Çalışma Saatleri Satırı Başlığı', 'type' => 'text', 'default' => 'Çalışma saatleri' ),
					),
				),
				'form' => array(
					'label'  => 'Form',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İletişim formu' ),
						'name_label'       => array( 'label' => 'Ad Alanı Etiketi', 'type' => 'text', 'default' => 'Ad soyad' ),
						'company_label'    => array( 'label' => 'Firma Alanı Etiketi', 'type' => 'text', 'default' => 'Firma (isteğe bağlı)' ),
						'phone_label'      => array( 'label' => 'Telefon Alanı Etiketi', 'type' => 'text', 'default' => 'Telefon' ),
						'email_label'      => array( 'label' => 'E-posta Alanı Etiketi', 'type' => 'text', 'default' => 'E-posta' ),
						'product_label'    => array( 'label' => 'Çivi Tipi Alanı Etiketi', 'type' => 'text', 'default' => 'Çivi tipi' ),
						'product_none'     => array( 'label' => 'Çivi Tipi: Seçilmemiş Seçenek', 'type' => 'text', 'default' => 'Emin değilim' ),
						'size_label'       => array( 'label' => 'Ölçü Alanı Etiketi', 'type' => 'text', 'default' => 'Ölçü ve adet' ),
						'size_placeholder' => array( 'label' => 'Ölçü Alanı İpucu', 'type' => 'text', 'default' => 'Örneğin: boy, kalınlık, kaç kutu ya da kilo; tabancanızın modeli' ),
						'message_label'    => array( 'label' => 'Mesaj Alanı Etiketi', 'type' => 'text', 'default' => 'Mesajınız (isteğe bağlı)' ),
						'form_note'        => array( 'label' => 'Form Notu', 'type' => 'text', 'default' => 'Telefon ya da e-postadan birini yazmanız yeterli.' ),
						'submit'           => array( 'label' => 'Gönder Düğmesi', 'type' => 'text', 'default' => 'İsteği gönder' ),
						'success_title'    => array( 'label' => 'Gönderildi: Başlık', 'type' => 'text', 'default' => 'İsteğiniz bize ulaştı.' ),
						'success_text'     => array( 'label' => 'Gönderildi: Metin', 'type' => 'textarea', 'default' => 'En kısa sürede sizi arayacağız ya da e-postayla dönüş yapacağız. Acil siparişte telefonla ulaşın.' ),
						'error_note'       => array( 'label' => 'Hatalı Form Uyarısı', 'type' => 'text', 'default' => 'Formda eksik ya da hatalı alanlar var; aşağıda işaretli.' ),
					),
				),
			),
		),

		/* ---------------------------------------------------------- *
		 * Bulunamayan sayfa (404). Onizleme adresi bilerek olmayan bir
		 * sayfa. Gizli: "Sayfa bul" kutusunda listelenir, site
		 * haritasina ve llms.txt'ye girmez.
		 * ---------------------------------------------------------- */
		'notfound' => array(
			'label'      => '404 Sayfası',
			'path'       => '/bulunamadi-404/',
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Metinleri',
					'fields' => array(
						'title'  => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Bu sayfa bulunamadı' ),
						'lead'   => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Aradığınız adres taşınmış ya da kaldırılmış olabilir. Çivi tiplerine göz atın ya da bizi arayın.' ),
						'button' => array( 'label' => 'Düğme Metni', 'type' => 'text', 'default' => 'Palet çivileri' ),
					),
				),
			),
		),
	) + $pc_blog_pages,
);
