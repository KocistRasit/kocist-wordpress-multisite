<?php
/**
 * Kocist temasi alan manifesti.
 *
 * Bu dosya saf bir dizi dondurur; hicbir WordPress fonksiyonuna bagimli degildir.
 * Boylece ag paneli, siteye gecmeden dosya sisteminden okuyabilir.
 *
 * Alan turleri: text, textarea, url, image, icon, repeater, products
 *
 * Her ornek blog yazisinin panelde gizli bir sayfasi vardir ('yazi-<slug>'):
 * "Sayfa bul" kutusundan acilir.
 */

/*
 * Urun kategorileri (/kategoriler/<grup>/<kategori>/). Hangi havuz
 * kategorisinin hangi urun grubunun altinda gorundugu veridir; Ürün Havuzu ->
 * Kategoriler'den yonetilir (eklenti, includes/product-categories.php).
 * Eklenti her yerlesik kategori icin asagidaki sablondan gizli
 * 'kat-<havuz-slug>' panel sayfasini, her grup icin 'grp-<grup>' sayfasini
 * uretir. Urun gruplari ust menunun "Grup anahtari" dolu satirlaridir.
 *
 * 'defaults': sira (menude ve kategori listelerinde) ve eski menu adlari.
 * Havuzda karsiligi olmayan iki metin kategori (klipsler, baglama-telleri)
 * menu_hirdavat'ta capali baglanti olarak kalir; sayfalari asagida elle.
 * 'seed': ilk yerlesim, bir kez yazilir. 'legacy': slug'i havuz slug'ina
 * gecen dort eski adres; eski adres 301 ile yenisine gider.
 */
$kocist_catalog = array(
	'parents'       => array( 'rows' => array( 'global', 'header', 'menu' ), 'key' => 'key', 'label' => 'label' ),
	'parent_prefix' => 'grp-',
	// Grubun kendi havuz kategorisi (slug): yalnizca ona bagli urunler dogrudan
	// gruba duser. Slug ile: havuzdaki ad degisse de bag kopmaz.
	'parent_pool'   => array(
		'kereste'    => 'kereste',
		'ambalaj'    => 'ahsap-ambalaj',
		'dekorasyon' => 'dekorasyon',
		'hirdavat'   => 'hirdavat',
	),
	'page'          => array(
		'label'          => 'Kategori: {name}',
		'path'           => '/kategoriler/{parent}/{slug}/',
		'hidden'         => true,
		'components'     => array(
			'head' => array(
				'label'  => 'Sayfa Başlığı',
				'fields' => array(
					'name' => array(
						'label'   => 'Kategori adı (menü, sayfa başlığı, kategori listeleri)',
						'type'    => 'text',
						'default' => '{name}',
						'hint'    => 'Yalnızca bu sitede görünen ad; Ürün Havuzu’ndaki kategori adı değişmez.',
					),
					'lead' => array(
						'label'   => 'Alt Metin',
						'type'    => 'textarea',
						'default' => '',
						'hint'    => 'Boş bırakılırsa Kategoriler sekmesindeki ortak metin görünür.',
					),
				),
			),
		),
		'products_label' => 'Bu sayfadaki ürünler (Ürün Havuzu: {name})',
	),
	'parent_page'   => array(
		'label'          => 'Grup: {name}',
		'path'           => '/kategoriler/{parent}/',
		'hidden'         => true,
		'components'     => array(
			'head' => array(
				'label'  => 'Sayfa Başlığı',
				'fields' => array(
					'lead' => array(
						'label'   => 'Alt Metin',
						'type'    => 'textarea',
						'default' => '',
						'hint'    => 'Boş bırakılırsa Kategoriler sekmesindeki ortak metin görünür. Sayfa başlığı menüdeki addır.',
					),
				),
			),
		),
		'products_label' => 'Bu sayfadaki ürünler (Ürün Havuzu: {pool})',
	),
	'defaults'      => array(
		// kereste
		'ahsap-kalas' => array( 'name' => 'Ahşap Kalas' ),
		'ahsap-cita' => array( 'name' => 'Ahşap Çıta' ),
		'ahsap-rabita' => array( 'name' => 'Ahşap Rabıta (Lambiri & Döşeme)' ),
		'osb-plaka-levha' => array( 'name' => 'OSB Plaka Levha' ),
		'kontrplak-levha' => array( 'name' => 'Kontrplak Levha' ),
		'plywood-levha' => array( 'name' => 'Plywood Levha' ),
		'maden-diregi-kereste' => array( 'name' => 'Maden Direği Kereste' ),
		'ahsap-takoz' => array( 'name' => 'Ahşap Takoz' ),
		'insaatlik-catilik-kereste' => array( 'name' => 'İnşaatlık & Çatılık Kereste' ),
		// ambalaj
		'ahsap-palet' => array( 'name' => 'Ahşap Palet (İhracat & Endüstriyel)' ),
		'ahsap-sandik' => array( 'name' => 'Ahşap Sandık & İhracat Kasası' ),
		'ahsap-kafes' => array( 'name' => 'Ahşap Kafes & Sarma Kafes' ),
		'triplex-bariyer' => array( 'name' => 'Triplex Bariyer & Şantiye Bariyeri' ),
		'cam-tasima-sehpasi' => array( 'name' => 'Ahşap Cam Taşıma Sehpası' ),
		// dekorasyon
		'ahsap-ev' => array( 'name' => 'Ahşap Ev' ),
		'kamelyalar' => array( 'name' => 'Kamelya' ),
		'cardaklar' => array( 'name' => 'Çardak' ),
		'ahsap-bank' => array( 'name' => 'Ahşap Oturma Grubu - Ahşap Bank' ),
		'piknik-masalari' => array(),
		'saksi-sebze-yatagi' => array( 'name' => 'Saksı & Sebze Yatağı' ),
		'ahsap-salincak' => array( 'name' => 'Ahşap Salıncak' ),
		'ahsap-pergola' => array( 'name' => 'Ahşap Pergola' ),
		'ahsap-sezlonglar' => array( 'name' => 'Ahşap Şezlong' ),
		'ahsap-tahterevalli' => array( 'name' => 'Ahşap Tahterevalli' ),
		'ahsap-yatak' => array( 'name' => 'Ahşap Yatak' ),
		'ahsap-ayak-alti' => array( 'name' => 'Ahşap Ayak Altı' ),
		'hayvan-barinaklari' => array( 'name' => 'Hayvan Barınakları' ),
		'kopek-kulubeleri' => array(),
		'kedi-yuvalari' => array(),
		'adirondack' => array( 'name' => 'Adirondack Sandalye' ),
		'celik-yapi' => array( 'name' => 'Çelik Yapı' ),
		// hirdavat
		'civiler' => array( 'name' => 'Çiviler' ),
		'zimba-telleri' => array( 'name' => 'Zımba Telleri' ),
		'klipsler' => array( 'name' => 'Klipsler' ),
		'baglama-telleri' => array( 'name' => 'Bağlama Telleri & Çelik Teller' ),
		'civi-tabancalari' => array( 'name' => 'Zımba & Çivi Tabancaları' ),
		'sartlandiricilar' => array( 'name' => 'Şartlandırıcılar' ),
		'kompresorler' => array( 'name' => 'Kompresörler' ),
		'somun-sokme-aletleri' => array( 'name' => 'Somun Sökme Aletleri' ),
		'yuzey-gelistirme-diskleri' => array( 'name' => 'Yüzey Geliştirme Diskleri' ),
		'maket-bicagi' => array( 'name' => 'Maket Bıçağı ve Çivili Kroşe' ),
		'kulplar' => array( 'name' => 'Kulplar' ),
		'menteseler' => array( 'name' => 'Menteşeler' ),
		'surguler' => array( 'name' => 'Sürgüler' ),
		'gonyeler' => array( 'name' => 'Gönyeler' ),
		'aski-sabitleme' => array( 'name' => 'Askı & Sabitleme Elemanları' ),
		'vida-grubu' => array( 'name' => 'Vida Grubu' ),
		'civata-grubu' => array( 'name' => 'Civata Grubu' ),
		'mandallar' => array( 'name' => 'Ahşap & Endüstriyel Mandallar' ),
		'pergola-ayaklari' => array( 'name' => 'Pergola Ayakları' ),
		'matkap-uclari' => array( 'name' => 'Matkap & Tornavida Uçları' ),
		'purmuzler' => array( 'name' => 'Pürmüzler & Gazlı Aletler' ),
		'shingle-cati' => array( 'name' => 'Shingle Çatı Kaplamaları' ),
		'likit-membran' => array( 'name' => 'Likit Membran ve Sürme Yalıtım' ),
		'su-yalitim-membranlari' => array( 'name' => 'Su Yalıtım Membranları' ),
	),
	'seed'          => array(
		// kereste
		'ahsap-kalas' => 'kereste',
		'ahsap-cita' => 'kereste',
		'ahsap-rabita' => 'kereste',
		'osb-plaka-levha' => 'kereste',
		'kontrplak-levha' => 'kereste',
		'plywood-levha' => 'kereste',
		'maden-diregi-kereste' => 'kereste',
		'ahsap-takoz' => 'kereste',
		'insaatlik-catilik-kereste' => 'kereste',
		// ambalaj
		'ahsap-palet' => 'ambalaj',
		'ahsap-sandik' => 'ambalaj',
		'ahsap-kafes' => 'ambalaj',
		'triplex-bariyer' => 'ambalaj',
		'cam-tasima-sehpasi' => 'ambalaj',
		// dekorasyon
		'ahsap-ev' => 'dekorasyon',
		'kamelyalar' => 'dekorasyon',
		'cardaklar' => 'dekorasyon',
		'ahsap-bank' => 'dekorasyon',
		'piknik-masalari' => 'dekorasyon',
		'saksi-sebze-yatagi' => 'dekorasyon',
		'ahsap-salincak' => 'dekorasyon',
		'ahsap-pergola' => 'dekorasyon',
		'ahsap-sezlonglar' => 'dekorasyon',
		'ahsap-tahterevalli' => 'dekorasyon',
		'ahsap-yatak' => 'dekorasyon',
		'ahsap-ayak-alti' => 'dekorasyon',
		'hayvan-barinaklari' => 'dekorasyon',
		'kopek-kulubeleri' => 'dekorasyon',
		'kedi-yuvalari' => 'dekorasyon',
		'adirondack' => 'dekorasyon',
		'celik-yapi' => 'dekorasyon',
		// hirdavat
		'civiler' => 'hirdavat',
		'zimba-telleri' => 'hirdavat',
		'civi-tabancalari' => 'hirdavat',
		'sartlandiricilar' => 'hirdavat',
		'kompresorler' => 'hirdavat',
		'somun-sokme-aletleri' => 'hirdavat',
		'yuzey-gelistirme-diskleri' => 'hirdavat',
		'maket-bicagi' => 'hirdavat',
		'kulplar' => 'hirdavat',
		'menteseler' => 'hirdavat',
		'surguler' => 'hirdavat',
		'gonyeler' => 'hirdavat',
		'aski-sabitleme' => 'hirdavat',
		'vida-grubu' => 'hirdavat',
		'civata-grubu' => 'hirdavat',
		'mandallar' => 'hirdavat',
		'pergola-ayaklari' => 'hirdavat',
		'matkap-uclari' => 'hirdavat',
		'purmuzler' => 'hirdavat',
		'shingle-cati' => 'hirdavat',
		'likit-membran' => 'hirdavat',
		'su-yalitim-membranlari' => 'hirdavat',
	),
	'legacy'        => array(
		'kamelya'             => 'kamelyalar',
		'cardak'              => 'cardaklar',
		'ahsap-sezlong'       => 'ahsap-sezlonglar',
		'adirondack-sandalye' => 'adirondack',
	),
);

/*
 * Havuzda karsiligi olmayan metin kategorileri: menude capali baglanti
 * (menu_hirdavat), sayfasi burada elle; urun listesi yok.
 */
$kocist_category_pages = array();

foreach ( array( 'klipsler' => 'Klipsler', 'baglama-telleri' => 'Bağlama Telleri' ) as $kocist_sub => $kocist_label ) {
	$kocist_category_pages[ 'kat-' . $kocist_sub ] = array(
		'label'      => 'Kategori: ' . $kocist_label,
		'path'       => '/kategoriler/hirdavat/' . $kocist_sub . '/',
		'hidden'     => true,
		'components' => array(
			'head' => array(
				'label'  => 'Sayfa Başlığı',
				'fields' => array(
					'lead' => array(
						'label'   => 'Alt Metin',
						'type'    => 'textarea',
						'default' => '',
						'hint'    => 'Boş bırakılırsa Kategoriler sekmesindeki ortak metin görünür. Sayfa başlığı menüdeki addır.',
					),
				),
			),
		),
	);
}

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
$kocist_blog_pages = array();

foreach ( (array) include __DIR__ . '/content/blog-posts.php' as $kocist_post ) {
	$kocist_blog_pages[ 'yazi-' . $kocist_post['slug'] ] = array(
		'label'      => 'Yazı: ' . $kocist_post['title'],
		'path'       => '/' . $kocist_post['slug'] . '/',
		'hidden'     => true,
		'components' => array(
			'post' => array(
				'label'  => 'Yazı',
				'fields' => array(
					'title'   => array( 'label' => 'Başlık', 'type' => 'text', 'default' => $kocist_post['title'] ),
					'excerpt' => array( 'label' => 'Özet (kart ve arama sonucu)', 'type' => 'textarea', 'default' => $kocist_post['excerpt'] ),
					'body'    => array(
						'label'   => 'Metin (boş satırla paragraf)',
						'type'    => 'textarea',
						'default' => $kocist_post['body'],
						'hint'    => 'Burada değiştirdiğiniz metin sitede görünür. Tam metni yazınca “' . KOCIST_BLOG_PENDING . '” satırını silin; o satır sitede soluk görünür.',
					),
					'image'   => array(
						'label'   => 'Kapak Görseli',
						'type'    => 'image',
						'default' => 0,
						'hint'    => 'Boş bırakılırsa yazının kendi kapak görseli, o da yoksa kategorisine uygun fotoğraf kullanılır.',
					),
				),
			),
		),
	);
}

$manifest_data = array(
	'site_key'   => 'kocist',
	// Kategori yerlesimi ve kategori sayfalarinin sablonu (yukarida).
	'catalog'    => $kocist_catalog,
	'site_label' => 'Koçist',
	// Havuz urun sayfalarinin (/urun/<urun>/) ortak metinleri bu panel sayfasinda.
	'product_page' => 'product',
	// SEO ve GEO firma bilgisi (Network Content Studio). Degerler bu sitenin
	// kendi sayfalarinda yazanlardir; panelin SEO ve GEO sekmesinden duzeltilir.
	'seo_site_defaults' => array(
		'name'        => 'Koçist Orman Ürünleri',
		'legal_name'  => 'Koçist Orman Ürünleri İnş. ve İnş. Yap. Malz. San. Tic. Ltd. Şti.',
		'description' => 'Koçist Orman Ürünleri: özel ölçüde kereste, palet, sandık ve kafes üretimi. Tedarik, üretim ve sevkiyat aynı çatı altında.',
		'phone'       => '+90 549 648 19 19',
		'email'       => 'info@kocist.com.tr',
		'street'      => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1',
		'district'    => 'Çatalca',
		'city'        => 'İstanbul',
		'postal_code' => '', // 34494 Catalca'nin kodu degil; tek adres kurali (Toplu Guncelleme)
		'country'     => 'TR',
	),
	// Urun Havuzu'ndaki urun formunda "Ürün Tabloları" bolumu bu sitede
	// gosterilen urunler icin acilir (bkz. kocist_product_tables()).
	'product_tables' => true,
	// Urun formunda "Ürün videosu" bu sitede gosterilen urunler icin
	// listelenir; video urun sayfasindaki galerinin sonunda (product-detail.php).
	'product_video'  => true,
	// Aciklamasi (detay metni) olmayan havuz urununun sayfasi da acik kalir:
	// ad, gorsel ve teknik detaylar gosterilir (eklenti: nwcs_product_has_page).
	'product_page_always' => true,
	'pages'      => array(

		'global' => array(
			'label'      => 'Tüm Sayfalar (Üst Bilgi, Menü, Sayfa Altı)',
			'path'       => '/',
			'components' => array(

				/*
				 * Ust serit: solda teslim notu + calisma saati ve banka
				 * bilgileri baglantisi, sagda telefon ve e-posta.
				 *
				 * 'hours' ayri bir alan olarak duruyor (notun icine gomulmedi)
				 * cunku shared_map uzerinden panelden dagitilan kanonik
				 * "calisma saatleri" degeri buraya yaziliyor. Ikisi ekranda
				 * ince bir ayrac ile yan yana basiliyor.
				 *
				 * Ikon alanlari kaldirildi: serit artik sade metin.
				 */
				'topbar' => array(
					'label'  => 'Üst Bilgi Şeridi',
					'fields' => array(
						'note'        => array( 'label' => 'Şerit Metni', 'type' => 'text', 'default' => 'Çatalca, İstanbul' ),
						'hours'       => array( 'label' => 'Çalışma Saatleri', 'type' => 'text', 'default' => 'Çalışma: 08:00-19:00' ),
						'bank_label'  => array( 'label' => 'Banka Bilgileri Metni', 'type' => 'text', 'default' => 'Banka Bilgilerimiz' ),
						'bank_url'    => array( 'label' => 'Banka Bilgileri Bağlantısı', 'type' => 'url', 'default' => '/banka-bilgilerimiz/' ),
						'phone_icon'  => array( 'label' => 'Telefon İkonu', 'type' => 'icon', 'default' => 'phone' ),
						'phone_label' => array( 'label' => 'Telefon Metni', 'type' => 'text', 'default' => '0212 648 19 19' ),
						'phone_url'   => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => 'tel:+902126481919' ),
						'mobile_label' => array( 'label' => 'Cep Telefonu Metni', 'type' => 'text', 'default' => '0549 648 19 19' ),
						'mobile_url'  => array( 'label' => 'Cep Telefonu Bağlantısı', 'type' => 'url', 'default' => 'tel:+905496481919' ),
						'email_icon'  => array( 'label' => 'E-posta İkonu', 'type' => 'icon', 'default' => 'mail' ),
						'email_label' => array( 'label' => 'E-posta Metni', 'type' => 'text', 'default' => 'info@kocist.com.tr' ),
						'email_url'   => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:info@kocist.com.tr' ),
					),
				),

				'header' => array(
					'label'  => 'Üst Menü / Başlık',
					'fields' => array(
						'logo_image' => array( 'label' => 'Logo Görseli', 'type' => 'image', 'default' => 0 ),
						'logo_text'  => array( 'label' => 'Logo Yazısı', 'type' => 'text', 'default' => 'KOÇİST' ),
						'logo_sub'   => array( 'label' => 'Logo Alt Yazısı', 'type' => 'text', 'default' => 'Orman Ürünleri' ),

						/*
						 * Ust seviye menu. Bir satirin acilir menusu olacaksa
						 * 'submenu' alanina alt menu bileseninin anahtari yazilir
						 * (menu_kereste, menu_hirdavat...). Bos birakilirsa oge
						 * duz bir baglanti olur.
						 *
						 * Neden ayri bilesenler: sema bir repeater'a en fazla 50
						 * satir veriyor (schema.php, NWCS_REPEATER_MAX_CAP) ve
						 * repeater icinde repeater'i reddediyor. Tum menu tek
						 * listeye sigmadigi gibi, 60 satirlik tek form panelde
						 * duzenlenemez hale gelirdi. Her acilir menu kendi karti
						 * olarak duruyor.
						 *
						 * 'key': urun grubunun sabit anahtari (kereste, ambalaj...).
						 * Grup sayfasinin adresi (/kategoriler/<anahtar>/), urunlerin
						 * gruba eslenmesi ve grubun paneldeki gizli sayfasi
						 * (grp-<anahtar>) buna baglidir; menu metni degisse de
						 * bunlar degismez. Bossa eski kural: menu metninden
						 * (bkz. inc/catalog.php kocist_catalog_group_key()).
						 */
						'menu'       => array(
							'label'   => 'Menü Öğeleri',
							'type'    => 'repeater',
							'max'     => 12,
							'fields'  => array(
								'label'   => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'     => array( 'label' => 'Bağlantı', 'type' => 'url' ),
								'submenu' => array( 'label' => 'Alt Menü Anahtarı (boşsa açılır menü yok)', 'type' => 'text' ),
								'key'     => array(
									'label' => 'Ürün grubu anahtarı (boşsa bu satır ürün grubu değildir)',
									'type'  => 'text',
									'hint'  => 'Ürün grubunun sabit adı: kereste, ambalaj, dekorasyon, hirdavat. Değiştirmeyin: grubun adresi, metinleri ve Ürün Havuzu › Kategoriler’deki yerleşimler bu anahtara bağlıdır. Menü metnini istediğiniz gibi değiştirebilirsiniz. Grup olmayan satırlarda boş kalır.',
								),
							),
							'default' => array(
								array( 'label' => 'Ana Sayfa', 'url' => '/', 'submenu' => '', 'key' => '' ),
								array( 'label' => 'Kereste', 'url' => '/#katalog', 'submenu' => 'menu_kereste', 'key' => 'kereste' ),
								array( 'label' => 'Ambalaj', 'url' => '/#katalog', 'submenu' => 'menu_ambalaj', 'key' => 'ambalaj' ),
								array( 'label' => 'Dekorasyon', 'url' => '/#katalog', 'submenu' => 'menu_dekorasyon', 'key' => 'dekorasyon' ),
								array( 'label' => 'Hırdavat', 'url' => '/#katalog', 'submenu' => 'menu_hirdavat', 'key' => 'hirdavat' ),
								array( 'label' => 'Kurumsal', 'url' => '/kurumsal/', 'submenu' => 'menu_kurumsal', 'key' => '' ),
								array( 'label' => 'Katalog', 'url' => '/katalog/', 'submenu' => '', 'key' => '' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/', 'submenu' => '', 'key' => '' ),
							),
						),
					),
				),

				'menu_kereste' => array(
					'label'  => 'Açılır Menü: Kereste',
					'fields' => array(
						'items' => array(
							'label'   => 'Ek menü bağlantıları (ürün kategorileri Ürün Havuzu › Kategoriler’den gelir)',
							'type'    => 'repeater',
							'max'     => 20,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
							),
						),
					),
				),

				'menu_ambalaj' => array(
					'label'  => 'Açılır Menü: Ambalaj',
					'fields' => array(
						'items' => array(
							'label'   => 'Ek menü bağlantıları (ürün kategorileri Ürün Havuzu › Kategoriler’den gelir)',
							'type'    => 'repeater',
							'max'     => 20,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
							),
						),
					),
				),

				'menu_dekorasyon' => array(
					'label'  => 'Açılır Menü: Dekorasyon',
					'fields' => array(
						'items' => array(
							'label'   => 'Ek menü bağlantıları (ürün kategorileri Ürün Havuzu › Kategoriler’den gelir)',
							'type'    => 'repeater',
							'max'     => 24,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
							),
						),
					),
				),

				'menu_hirdavat' => array(
					'label'  => 'Açılır Menü: Hırdavat',
					'fields' => array(
						'items' => array(
							'label'   => 'Ek menü bağlantıları (ürün kategorileri Ürün Havuzu › Kategoriler’den gelir)',
							'type'    => 'repeater',
							'max'     => 36,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Klipsler', 'url' => '#klipsler' ),
								array( 'label' => 'Bağlama Telleri & Çelik Teller', 'url' => '#baglama-telleri' ),
							),
						),
					),
				),

				'menu_kurumsal' => array(
					'label'  => 'Açılır Menü: Kurumsal',
					'fields' => array(
						'items' => array(
							'label'   => 'Alt Menü Öğeleri',
							'type'    => 'repeater',
							'max'     => 12,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Hakkımızda', 'url' => '/kurumsal/' ),
								array( 'label' => 'İnsan Kaynakları', 'url' => '/insan-kaynaklari/' ),
								array( 'label' => 'Referanslar', 'url' => '/kurumsal/' ),
								array( 'label' => 'Blog – Haberler', 'url' => '/blog/' ),
								array( 'label' => 'Sık Sorulan Sorular', 'url' => '/sss/' ),
								array( 'label' => 'KVKK Aydınlatma Metni', 'url' => '/kvkk/' ),
							),
						),
					),
				),

				/*
				 * Ust menunun sagindaki arama ve teklif sepeti dugmeleri
				 * (inc/search.php, inc/basket.php). Sepetteki urun sayisi
				 * sayfada betikle cerezden okunur (sayfa onbellekte kalabilir).
				 */
				'tools' => array(
					'label'  => 'Üst Menü: Arama ve Teklif Sepeti',
					'fields' => array(
						'search_toggle' => array( 'label' => 'Arama düğmesi (ekran okuyucu için)', 'type' => 'text', 'default' => 'Ürün ara' ),
						'search_label'  => array( 'label' => 'Arama kutusunun adı (ekran okuyucu için)', 'type' => 'text', 'default' => 'Ürün kodu, adı ya da kategorisi' ),
						'search_hint'   => array( 'label' => 'Arama kutusundaki ipucu', 'type' => 'text', 'default' => 'Ürün kodu ya da adı' ),
						'search_button' => array( 'label' => 'Arama butonu', 'type' => 'text', 'default' => 'Ara' ),
						'search_region_bar'    => array( 'label' => 'Arama bölgesinin adı: üst menüde açılan kutu (ekran okuyucu için)', 'type' => 'text', 'default' => 'Ürün ara' ),
						'search_region_drawer' => array( 'label' => 'Arama bölgesinin adı: telefon menüsündeki kutu (ekran okuyucu için)', 'type' => 'text', 'default' => 'Menüde ürün ara' ),
						'search_region_page'   => array( 'label' => 'Arama bölgesinin adı: arama sonuçları sayfasındaki kutu (ekran okuyucu için)', 'type' => 'text', 'default' => 'Sonuçlarda yeniden ara' ),
						'basket_label'  => array( 'label' => 'Teklif sepeti düğmesi (sepet boşken, ekran okuyucu için)', 'type' => 'text', 'default' => 'Teklif sepeti' ),
						'basket_count'  => array( 'label' => 'Teklif sepeti düğmesi (ürün varken) — {sayi} ürün sayısı olur', 'type' => 'text', 'default' => 'Teklif sepeti, {sayi} ürün', 'hint' => 'Simgenin üstündeki rakam sepetteki ürün çeşidi sayısıdır (adet toplamı değil).' ),

						// Canli arama: kutuya yazarken acilan liste (eklenti assets/live-search.js).
						'live_all'      => array( 'label' => 'Canlı arama: tüm sonuçlar bağlantısı — {sayi} sonuç sayısı olur', 'type' => 'text', 'default' => 'Tüm sonuçlar ({sayi})', 'hint' => 'Kutuya yazarken açılan listenin altında; arama sayfasını açar. Sonuç yoksa listede Arama Sayfası › Sonuçlar bölümündeki "Sonuç yoksa" metinleri, iletişim ve WhatsApp bağlantısı görünür; ekleme metinleri Teklif Sepeti sayfasından gelir.' ),
						'live_count'    => array( 'label' => 'Canlı arama: sonuç sayısı (ekran okuyucu için) — {sayi}', 'type' => 'text', 'default' => '{sayi} ürün bulundu' ),
						'live_loading'  => array( 'label' => 'Canlı arama: sonuç gecikince', 'type' => 'text', 'default' => 'Aranıyor…' ),
						'live_code'     => array( 'label' => 'Canlı arama: ürün kodu satırı — {kod} ürünün kodu olur', 'type' => 'text', 'default' => 'Ürün kodu: {kod}' ),
						'live_list'     => array( 'label' => 'Canlı arama: listenin adı (ekran okuyucu için)', 'type' => 'text', 'default' => 'Arama önerileri' ),
						'live_error'    => array( 'label' => 'Canlı arama: teklif sepetine eklenemediyse (çerez kapalı)', 'type' => 'text', 'default' => 'Ürün sepete eklenemedi: tarayıcınız çerezleri engelliyor olabilir. Ürün sayfasını açıp teklif formunu kullanın.' ),
					),
				),

				/*
				 * Sitenin her yerinde gecen kisa metinler.
				 */
				'texts' => array(
					'label'  => 'Ortak Metinler',
					'fields' => array(
						'image_soon' => array( 'label' => 'Görseli Olmayan Alandaki Yazı', 'type' => 'text', 'default' => 'Görsel yakında' ),
						'home_crumb' => array( 'label' => 'Yol Göstergesi: Ana Sayfa', 'type' => 'text', 'default' => 'Ana Sayfa' ),
						'colors'     => array( 'label' => 'Ürün Kartında Renk Sayısı — {sayi} renk sayısı olur', 'type' => 'text', 'default' => '{sayi} renk', 'hint' => 'Aynı ürünün renkleri tek kartta toplandığında (Ürün Havuzu › Kategoriler › Renk grupları) kartta görünür. {sayi} silmeyin.' ),
						'wa_message' => array( 'label' => 'WhatsApp Hazır Mesajı (genel düğmeler)', 'type' => 'textarea', 'default' => 'Merhaba, Koçist web sitesinden ulaşıyorum. Bilgi almak istiyorum.', 'hint' => 'Mesajı yazılmamış her WhatsApp bağlantısında (footer, iletişim sayfası) sohbet bu metinle açılır. Ürünlerden açılan WhatsApp mesajı Ürün Sayfaları › WhatsApp ve Teklif bölümündedir.' ),
					),
				),

				/*
				 * Footer ve ustundeki hareketli serit.
				 *
				 * Serit ogeleri saga dogru kesintisiz akar. Ayni liste
				 * sablonda iki kez basilir; kopya oldugu icin ekran
				 * okuyuculardan gizlenir (footer.php).
				 *
				 * Sosyal ikonlar eklentinin ikon kutuphanesinden secilir
				 * (instagram, facebook, linkedin, youtube, whatsapp);
				 * panelden keyfi SVG girilemez.
				 */
				'footer' => array(
					'label'  => 'Sayfa Altı (Footer)',
					'fields' => array(

						// Ustteki hareketli serit
						'ticker'          => array(
							'label'   => 'Hareketli Şerit Öğeleri',
							'type'    => 'repeater',
							'max'     => 12,
							'fields'  => array(
								'text' => array( 'label' => 'Şerit Metni', 'type' => 'text' ),
							),
							'default' => array(
								array( 'text' => 'ÇATALCA ÜRETİM TESİSİ' ),
								array( 'text' => 'İHRACATA UYGUN ISIL İŞLEM' ),
								array( 'text' => 'ÖZEL ÖLÇÜ ÜRETİM' ),
								array( 'text' => 'YAZILI FİYAT TEKLİFİ' ),
								array( 'text' => 'ÇATALCA’DAN SEVKİYAT' ),
								array( 'text' => 'TOPTAN SATIŞ' ),
							),
						),

						// Sol sutun
						'about_text'      => array( 'label' => 'Tanıtım Metni', 'type' => 'textarea', 'default' => 'Kereste, ahşap ambalaj, dekorasyon ve hırdavat gruplarında üretim ve toptan tedarik. Kendi tesisimizde üretiyor, planlı sevkiyatla teslim ediyoruz.' ),
						'social'          => array(
							'label'   => 'Sosyal Medya',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'icon'  => array( 'label' => 'İkon', 'type' => 'icon' ),
								'label' => array( 'label' => 'Ad (ekran okuyucu için)', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'icon' => 'instagram', 'label' => 'Instagram', 'url' => '' ),
								array( 'icon' => 'facebook', 'label' => 'Facebook', 'url' => '' ),
								array( 'icon' => 'linkedin', 'label' => 'LinkedIn', 'url' => '' ),
								array( 'icon' => 'whatsapp', 'label' => 'WhatsApp', 'url' => 'https://wa.me/905496481919' ),
							),
						),

						// Birinci baglanti sutunu
						'links_title'     => array( 'label' => '1. Sütun Başlığı', 'type' => 'text', 'default' => 'Ürün Grupları' ),
						'links'           => array(
							'label'   => '1. Sütun Bağlantıları',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Kereste', 'url' => '/#katalog' ),
								array( 'label' => 'Ambalaj', 'url' => '/#katalog' ),
								array( 'label' => 'Ahşap Dekorasyon', 'url' => '/#katalog' ),
								array( 'label' => 'Hırdavat Grubu', 'url' => '/#katalog' ),
							),
						),

						// Ikinci baglanti sutunu
						'corporate_title' => array( 'label' => '2. Sütun Başlığı', 'type' => 'text', 'default' => 'Kurumsal' ),
						'corporate'       => array(
							'label'   => '2. Sütun Bağlantıları',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Hakkımızda', 'url' => '/kurumsal/' ),
								array( 'label' => 'İnsan Kaynakları', 'url' => '/insan-kaynaklari/' ),
								array( 'label' => 'Blog – Haberler', 'url' => '/blog/' ),
								array( 'label' => 'Belgelerimiz', 'url' => '/kurumsal/' ),
								array( 'label' => 'Referanslar', 'url' => '/kurumsal/' ),
								array( 'label' => 'Sık Sorulan Sorular', 'url' => '/sss/' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/' ),
							),
						),

						// Iletisim sutunu
						'contact_title'   => array( 'label' => 'İletişim Başlığı', 'type' => 'text', 'default' => 'İletişim' ),
						'address_icon'    => array( 'label' => 'Adres İkonu', 'type' => 'icon', 'default' => 'pin' ),
						'address'         => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => 'Kestanelik Mahallesi, Çatalca / İstanbul' ),
						'map_label'       => array( 'label' => 'Harita Bağlantı Metni', 'type' => 'text', 'default' => 'Haritada göster' ),
						'map_url'         => array( 'label' => 'Harita Bağlantısı', 'type' => 'url', 'default' => '/iletisim/#harita' ),
						'phone_label'     => array( 'label' => 'Telefon Metni', 'type' => 'text', 'default' => '0212 648 19 19' ),
						'phone_url'       => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => 'tel:+902126481919' ),
						'mobile_label'    => array( 'label' => 'Cep Telefonu Metni', 'type' => 'text', 'default' => '0549 648 19 19' ),
						'mobile_url'      => array( 'label' => 'Cep Telefonu Bağlantısı', 'type' => 'url', 'default' => 'tel:+905496481919' ),
						'email_label'     => array( 'label' => 'E-posta Metni', 'type' => 'text', 'default' => 'info@kocist.com.tr' ),
						'email_url'       => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:info@kocist.com.tr' ),

						// Alt serit
						'copyright'       => array( 'label' => 'Telif Satırı', 'type' => 'text', 'default' => '© 2026 Koçist Orman Ürünleri — yerel demo kurulumu' ),
						'legal'           => array(
							'label'   => 'Alt Şerit Bağlantıları',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Gizlilik Politikası', 'url' => '#gizlilik' ),
								array( 'label' => 'KVKK Metni', 'url' => '/kvkk/' ),
								array( 'label' => 'Çerez Politikası', 'url' => '#cerez' ),
							),
						),
						'demo_note'       => array( 'label' => 'Demo Uyarısı', 'type' => 'text', 'default' => 'Bu bir demo kurulumudur.' ),
					),
				),
			),
		),

		'home' => array(
			'label'             => 'Ana Sayfa',
			'path'              => '/',
			'sortable_sections' => array( 'catalog', 'latest', 'process', 'products', 'blog' ),
			'components'        => array(

				/*
				 * Hero kenardan kenara tesis videosudur (assets/video/, temaya
				 * gomulu; panelde video alani yok). Logo global.header.logo_image
				 * alanindan okunur ki marka gorseli tek yerden yonetilsin.
				 *
				 * Guven seridi bir repeater; repeater ICINDE repeater yasak
				 * (schema.php).
				 */
				'hero' => array(
					'label'  => 'Hero (Giriş Bölümü)',
					'fields' => array(

						// Video ustundeki slogan ve buton
						'title'     => array( 'label' => 'Slogan', 'type' => 'text', 'default' => 'Ahşaptan ilham aldık' ),
						'cta_label' => array( 'label' => 'Buton Metni', 'type' => 'text', 'default' => 'Ürün Gruplarımız' ),
						'cta_url'   => array( 'label' => 'Buton Bağlantısı', 'type' => 'url', 'default' => '#katalog' ),

						// Hero altindaki guven seridi
						'trust'     => array(
							'label'   => 'Güven Şeridi',
							'type'    => 'repeater',
							'max'     => 5,
							'fields'  => array(
								'icon'  => array( 'label' => 'İkon', 'type' => 'icon' ),
								'label' => array( 'label' => 'Metin', 'type' => 'text' ),
							),
							'default' => array(
								array( 'icon' => 'truck', 'label' => 'Sevkiyat teklifle birlikte planlanır' ),
								array( 'icon' => 'factory', 'label' => 'Çatalca tesisinde kendi üretimimiz' ),
								array( 'icon' => 'shield', 'label' => 'İhracata uygun ısıl işlem' ),
								array( 'icon' => 'check', 'label' => 'Toptan satış' ),
							),
						),
					),
				),

				/*
				 * Ana urun gamlari: dort kart yan yana. Her kart tam kaplama
				 * gorsel + ustune ortalanmis baslik, alt baslik ve Kesfet
				 * butonu.
				 *
				 * Not: musterinin kategori gorsellerinin bir kismi hazir
				 * banner; kosede kendi etiketini tasiyor. Uzerine bizim
				 * basligimiz da bindiginde kosede ikinci bir etiket gorunur.
				 * Bu bilincli bir tercih (gercek sitedeki durumla ayni);
				 * temiz foto geldiginde tek yapilacak sey gorseli degistirmek.
				 */
				'catalog' => array(
					'label'  => 'Ürün Grupları',
					'fields' => array(
						'title'    => array( 'label' => 'Bölüm Etiketi', 'type' => 'text', 'default' => 'Ürün Grupları' ),
						'items'    => array(
							'label'   => 'Grup Kartları',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'image'      => array( 'label' => 'Görsel', 'type' => 'image' ),
								'title'      => array( 'label' => 'Başlık', 'type' => 'text' ),
								'link_label' => array( 'label' => 'Buton Metni', 'type' => 'text' ),
								'link_url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'image' => 0, 'title' => 'Kereste', 'link_label' => 'Keşfet', 'link_url' => '/#katalog' ),
								array( 'image' => 0, 'title' => 'Ambalaj', 'link_label' => 'Keşfet', 'link_url' => '/#katalog' ),
								array( 'image' => 0, 'title' => 'Ahşap Dekorasyon', 'link_label' => 'Keşfet', 'link_url' => '/#katalog' ),
								array( 'image' => 0, 'title' => 'Hırdavat Grubu', 'link_label' => 'Keşfet', 'link_url' => '/#katalog' ),
							),
						),
					),
				),

				/*
				 * Son eklenen urunler: tek satir, saga dogru kayan serit.
				 * Kartlar Urun Havuzu'ndan gelir (bu siteye secilen urunlerden
				 * havuza en son eklenenler); burada yalnizca metinler ve adet.
				 */
				'latest' => array(
					'label'  => 'Son Eklenen Ürünler',
					'fields' => array(
						'title'      => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Son eklenen ürünler' ),
						'subtitle'   => array( 'label' => 'Bölüm Alt Başlığı', 'type' => 'textarea', 'default' => 'Kataloğa yeni giren ürünler. Ölçü ve adet için teklif isteyin.' ),
						'link_label' => array( 'label' => 'Kart Bağlantı Metni', 'type' => 'text', 'default' => 'Teklif alın' ),
						'count'      => array( 'label' => 'Gösterilecek Ürün Sayısı', 'type' => 'text', 'default' => '8', 'hint' => 'Ürünler Ürün Havuzu’ndan gelir: bu siteye seçilenlerden havuza en son eklenenler.' ),
					),
				),

				/*
				 * Siparis sureci: uc adim. Her adimin cizimi sabittir (olcu, teklif,
				 * sevkiyat); panelden yalnizca metinler degisir.
				 */
				'process' => array(
					'label'  => 'Sipariş Süreci',
					'fields' => array(
						'title'     => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Siparişiniz nasıl ilerler' ),
						'subtitle'  => array( 'label' => 'Bölüm Alt Başlığı', 'type' => 'textarea', 'default' => 'Ölçüyü siz verin; üretimi ve sevkiyatı biz planlayalım.' ),
						'items'     => array(
							'label'   => 'Adımlar',
							'type'    => 'repeater',
							'max'     => 3,
							'fields'  => array(
								'title' => array( 'label' => 'Adım Başlığı', 'type' => 'text' ),
								'text'  => array( 'label' => 'Adım Metni', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Ölçü ve adedi iletin', 'text' => 'Ürünü, ölçüyü, adedi ve teslim adresini telefonla, e-postayla ya da iletişim formundan gönderin.' ),
								array( 'title' => 'Yazılı teklifinizi alın', 'text' => 'Fiyatı ve teslim tarihini yazılı olarak iletiyoruz. Onayınızla sipariş üretime girer.' ),
								array( 'title' => 'Üretim ve sevkiyat', 'text' => 'Siparişiniz Çatalca tesisinde hazırlanır; sevkiyat şekli ve süresi teklifle birlikte yazılı olarak bildirilir.' ),
							),
						),
						'cta_label' => array( 'label' => 'Buton Metni', 'type' => 'text', 'default' => 'Teklif isteyin' ),
						'cta_url'   => array( 'label' => 'Buton Bağlantısı', 'type' => 'url', 'default' => '/iletisim/' ),
					),
				),

				/*
				 * Merkezi urun havuzundan gelen urunler. Kategori kartlarindan
				 * (catalog) ayri bir bolum: orada elle girilen dort grup var,
				 * burada panelden bu site icin secilen tekil urunler.
				 * Fiyat bos ise kartta "Teklif al" gorunur.
				 */
				'products' => array(
					'label'  => 'Ürünler (Havuzdan)',
					'fields' => array(
						'title'     => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Öne Çıkan Ürünler' ),
						'subtitle'  => array( 'label' => 'Bölüm Alt Başlığı', 'type' => 'textarea', 'default' => 'Stoktan hızlı teslim edilen ürünlerimizden bir seçki.' ),
						'pool'      => array(
							'label' => 'Ürünler (merkezî havuzdan)',
							'type'  => 'products',
						),
						'cta_label'    => array( 'label' => 'Kart Bağlantı Metni (detay sayfası yoksa)', 'type' => 'text', 'default' => 'Teklif Al' ),
						'detail_label' => array( 'label' => 'Kart Bağlantı Metni (detay sayfası varsa)', 'type' => 'text', 'default' => 'Ürün detayı' ),
						'count'        => array( 'label' => 'Gösterilecek Ürün Sayısı', 'type' => 'text', 'default' => '8', 'hint' => 'Yukarıdaki listenin ilk ürünleri gösterilir; sırayı oklarla değiştirin.' ),
						'all_label'    => array( 'label' => 'Tüm Ürünler Buton Metni', 'type' => 'text', 'default' => 'Tüm ürünler' ),
						'empty_text'   => array( 'label' => 'Ürün Seçilmemişse (yalnızca panel önizlemesinde)', 'type' => 'text', 'default' => 'Bu site için henüz ürün seçilmedi.' ),
					),
				),

				/*
				 * Son uc blog yazisi. Yazi yoksa bolum hic basilmaz.
				 */
				'blog' => array(
					'label'  => 'Blog – Son Yazılar',
					'fields' => array(
						'title'      => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Blog – Haberler' ),
						'subtitle'   => array( 'label' => 'Bölüm Alt Başlığı', 'type' => 'textarea', 'default' => 'Koçist’ten haberler; orman ürünlerinde güncel trendler ve bilgilendirmeler.' ),
						'link_label' => array( 'label' => 'Tüm Yazılar Bağlantı Metni', 'type' => 'text', 'default' => 'Tüm yazılar' ),
					),
				),
			),
		),

		/*
		 * Urun kategorileri: /kategoriler/ ve tum grup/kategori sayfalarinda
		 * ortak metinler. {suslu parantez} icindeki kelimeler sayfada
		 * gercek degerle degisir (grup adi, urun sayisi...).
		 */
		'kategoriler' => array(
			'label'      => 'Kategoriler',
			'path'       => '/kategoriler/',
			'components' => array(
				'index' => array(
					'label'  => 'Kategoriler Sayfası',
					'fields' => array(
						'crumb' => array( 'label' => 'Yol Göstergesindeki Adı', 'type' => 'text', 'default' => 'Kategoriler' ),
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Ürün kategorileri' ),
						'lead'  => array( 'label' => 'Alt Metin — {grup} grup sayısı, {kategori} kategori sayısı olur', 'type' => 'textarea', 'default' => '{grup} ürün grubunda {kategori} kategori. Aradığınızı listede görmüyorsanız ölçüsünü yazın, fiyatlandıralım.' ),
					),
				),
				'texts' => array(
					'label'  => 'Grup ve Kategori Sayfaları (ortak)',
					'fields' => array(
						'lead_products'      => array( 'label' => 'Alt Metin: ürün varsa — {urun} ürün sayısı', 'type' => 'textarea', 'default' => '{urun} ürün listeleniyor. Fiyatlar ölçü ve adede göre değişir; ölçü ve adedi yazın, yazılı teklif gönderelim.' ),
						'lead_sub_empty'     => array( 'label' => 'Alt Metin: ürünsüz kategori — {grup} grup adı', 'type' => 'textarea', 'default' => '{grup} grubunda, ölçü ve adede göre üretim ve tedarik.' ),
						'lead_group_empty'   => array( 'label' => 'Alt Metin: ürünsüz grup — {kategori} kategori sayısı', 'type' => 'textarea', 'default' => '{kategori} kategoride ölçü ve adede göre üretim ve tedarik.' ),
						'group_all'          => array( 'label' => 'Grubun Tümü Bağlantısı — {grup} grup adı', 'type' => 'text', 'default' => 'Tüm {grup} ürünleri' ),
						'meta_subs'          => array( 'label' => 'Kategori Sayısı — {kategori}', 'type' => 'text', 'default' => '{kategori} kategori' ),
						'meta_subs_products' => array( 'label' => 'Kategori ve Ürün Sayısı — {kategori}, {urun}', 'type' => 'text', 'default' => '{kategori} kategori, {urun} ürün' ),
						'category_label'     => array( 'label' => 'Kategori Listesi Başlığı (telefonda)', 'type' => 'text', 'default' => 'Kategori' ),
						'all_label'          => array( 'label' => 'Listede "Tümü"', 'type' => 'text', 'default' => 'Tümü' ),
						'others_title'       => array( 'label' => 'Diğer Gruplar Başlığı', 'type' => 'text', 'default' => 'Diğer ürün grupları' ),
						'price_quote'        => array( 'label' => 'Fiyatı Olmayan Ürün Kartında', 'type' => 'text', 'default' => 'Fiyat teklifle' ),
						'go_detail'          => array( 'label' => 'Kart Bağlantısı (detay sayfası varsa)', 'type' => 'text', 'default' => 'İncele' ),
						'go_quote'           => array( 'label' => 'Kart Bağlantısı (detay sayfası yoksa)', 'type' => 'text', 'default' => 'Teklif iste' ),
						'empty_title'        => array( 'label' => 'Ürün Yoksa Başlık — {ad} sayfa adı', 'type' => 'text', 'default' => '{ad} için listelenmiş ürün yok' ),
						'empty_text'         => array( 'label' => 'Ürün Yoksa Metin', 'type' => 'textarea', 'default' => 'Bu kategorideki ürünleri siparişe göre hazırlıyoruz. Ölçü ve adedi yazın, yazılı teklif gönderelim.' ),
						'empty_cta'          => array( 'label' => 'Ürün Yoksa Buton', 'type' => 'text', 'default' => 'Teklif isteyin' ),
						'faq_title'          => array( 'label' => 'Kategori Sayfası: Soru-Cevap Başlığı (ürünlerin altında)', 'type' => 'text', 'default' => 'Sık sorulan sorular', 'hint' => 'Soruların kendisi Ürün Havuzu › Kategoriler › kategori › “Sık sorulan sorular”dan yazılır; sorusu olmayan kategoride bu bölüm görünmez.' ),
					),
				),
			),
		),

		/*
		 * Katalog (/katalog/): belgeler ve sertifikalar. Iki sekme: sertifika
		 * gorselleri (tiklayinca buyur) ve indirilebilir belgeler.
		 *
		 * Varsayilan dosyalar temada (assets/katalog/); '/tema/' ile baslayan
		 * baglanti o klasore cozulur. Yeni belge: dosyayi Ortam Kutuphanesi'ne
		 * yukleyip adresini Baglanti alanina yapistirin.
		 */
		'katalog' => array(
			'label'      => 'Katalog',
			'path'       => '/katalog/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Katalog' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Belgeler ve Sertifikalar' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'ISPM-15 ve HT yetki belgelerimiz, ısıl işlem tesisimiz ve kurumsal dokümanlarımız.' ),
					),
				),

				'certificates' => array(
					'label'  => 'Sertifikalar',
					'fields' => array(
						'tab_label'  => array( 'label' => 'Sekme Adı', 'type' => 'text', 'default' => 'Sertifikalar' ),
						'empty_text' => array( 'label' => 'Görsel Yoksa Gösterilecek Metin', 'type' => 'text', 'default' => 'Henüz sertifika eklenmedi.' ),
						'items'     => array(
							'label'   => 'Görseller',
							'type'    => 'repeater',
							'max'     => 30,
							'fields'  => array(
								'image' => array( 'label' => 'Görsel', 'type' => 'image' ),
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
							),
							'default' => array(
								array( 'image' => 0, 'title' => 'Ahşap Ambalaj Malzemesi İşaretleme İzin Belgesi' ),
								array( 'image' => 0, 'title' => 'Isıl İşlem Operatör Belgesi' ),
								array( 'image' => 0, 'title' => 'ISPM-15 ısıl işlem fırını' ),
								array( 'image' => 0, 'title' => 'ISPM-15 ısıl işlem fırını, kapalı' ),
								array( 'image' => 0, 'title' => 'HT damgalı kereste' ),
								array( 'image' => 0, 'title' => 'HT damgalı palet takozu' ),
								array( 'image' => 0, 'title' => 'Sandık üretim atölyesi' ),
								array( 'image' => 0, 'title' => 'HT damgalı kereste istifi' ),
								array( 'image' => 0, 'title' => 'Koçist damgalı kereste paketi' ),
								array( 'image' => 0, 'title' => 'TR-1080 HT damgası' ),
							),
						),
					),
				),

				'documents' => array(
					'label'  => 'Belgeler',
					'fields' => array(
						'tab_label'  => array( 'label' => 'Sekme Adı', 'type' => 'text', 'default' => 'Belgeler' ),
						'open_label' => array( 'label' => 'Aç Bağlantısı Metni', 'type' => 'text', 'default' => 'Aç' ),
						'file_label' => array( 'label' => 'Uzantısı Bilinmeyen Dosya Etiketi', 'type' => 'text', 'default' => 'DOSYA' ),
						'empty_text' => array( 'label' => 'Belge Yoksa Gösterilecek Metin', 'type' => 'text', 'default' => 'Henüz belge eklenmedi.' ),
						'items'     => array(
							'label'   => 'Belgeler',
							'type'    => 'repeater',
							'max'     => 30,
							'fields'  => array(
								'title' => array( 'label' => 'Belge Adı', 'type' => 'text' ),
								'url'   => array( 'label' => 'Dosya Bağlantısı (PDF, JPG…)', 'type' => 'url' ),
							),
							'default' => array(
								array( 'title' => 'Ahşap Ambalaj Malzemesi İşaretleme İzin Belgesi', 'url' => '/tema/isaretleme-izin-belgesi.jpg' ),
								array( 'title' => 'Kapasite Raporu', 'url' => '/tema/belgeler/kapasite-raporu.pdf' ),
								array( 'title' => 'Sanayi Sicil Belgesi', 'url' => '/tema/belgeler/sanayi-sicil-belgesi.pdf' ),
								array( 'title' => 'Vida, Somun ve Bits Uç Listesi 2025', 'url' => '/tema/belgeler/vida-somun-bits-uc-listesi-2025.pdf' ),
							),
						),
					),
				),
			),
		),

		/*
		 * Banka Bilgilerimiz (/banka-bilgilerimiz/): havale ve EFT hesaplari.
		 * Ust seritteki "Banka Bilgilerimiz" baglantisi buraya gelir.
		 *
		 * Hesaplar panelden girilir; varsayilan liste bos, cunku firmanin
		 * gercek hesap bilgisi henuz yok. Liste bosken sayfa, ziyaretciyi
		 * hesap bilgisini telefonla ya da WhatsApp'tan istemeye yonlendirir.
		 */
		'banka' => array(
			'label'      => 'Banka Bilgilerimiz',
			'path'       => '/banka-bilgilerimiz/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Banka Bilgilerimiz' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Banka Bilgilerimiz' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Havale ve EFT ile ödeme yapabileceğiniz hesaplarımız.' ),
					),
				),

				'accounts' => array(
					'label'  => 'Hesaplar',
					'fields' => array(
						'items' => array(
							'label'   => 'Banka Hesapları',
							'type'    => 'repeater',
							'max'     => 12,
							'fields'  => array(
								'bank'     => array( 'label' => 'Banka Adı', 'type' => 'text' ),
								'holder'   => array( 'label' => 'Hesap Sahibi (Unvan)', 'type' => 'text' ),
								'branch'   => array( 'label' => 'Şube (adı ve kodu)', 'type' => 'text' ),
								'account'  => array( 'label' => 'Hesap No', 'type' => 'text' ),
								'currency' => array( 'label' => 'Para Birimi (TL, USD, EUR)', 'type' => 'text' ),
								'iban'     => array( 'label' => 'IBAN', 'type' => 'text' ),
							),
							'default' => array(),
						),
						'note'  => array( 'label' => 'Ödeme Notu', 'type' => 'textarea', 'default' => 'Havale ve EFT açıklamasına firma adınızı ya da teklif numaranızı yazın. Ödeme yapmadan önce IBAN\'ı telefonla bizden doğrulayın; hesap değişikliğini e-posta ile bildirmeyiz.' ),
					),
				),

				'empty' => array(
					'label'  => 'Hesap Girilmemişken',
					'fields' => array(
						'title'    => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Hesap bilgilerini bizden isteyin' ),
						'text'     => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'Banka ve IBAN bilgilerimizi telefonla ya da WhatsApp\'tan hemen iletiyoruz.' ),
						'wa_label' => array( 'label' => 'WhatsApp Düğmesi', 'type' => 'text', 'default' => 'WhatsApp\'tan isteyin' ),
						'wa_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => 'https://wa.me/905496481919?text=Merhaba%2C%20banka%20hesap%20bilgilerinizi%20alabilir%20miyim%3F' ),
					),
				),
			),
		),

		/*
		 * Kurumsal sayfasi (/kurumsal/). Metinler firmanin kendi kurumsal
		 * metnidir; yazim hatalari duzeltildi, anlam korundu.
		 *
		 * Eski 'story' ve 'values' bilesenleri yerine yeni anahtarlar
		 * kullaniliyor: panel deposunda eski demo metinleri kayitli olan
		 * sitelerde de yeni icerik varsayilandan gorunsun diye.
		 */
		'inner' => array(
			'label'      => 'Kurumsal',
			'path'       => '/kurumsal/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Kurumsal' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Kurumsal' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Özel ölçüde kereste, palet, sandık ve kafes üretiminde kalite, dürüstlük ve hız.' ),
					),
				),

				'profile' => array(
					'label'  => 'Profilimiz',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Profilimiz' ),
						'body'  => array(
							'label'   => 'Metin (paragraflar arasında boş satır bırakın)',
							'type'    => 'textarea',
							'default' => "Firmamız, yeni bir ürün için yaptıracağınız veya mevcut kullanımınızın yerini alacak her ölçüde kereste, palet, sandık ve kafesi sizin çıkarlarınız doğrultusunda en uygun şekilde dizayn eder ve size sunar; ya da mevcut kullandığınız palet, sandık ve kafes ölçülerini, şartnamelerini veya proje çizimlerini alarak istekleriniz doğrultusunda en hızlı şekilde üretime geçer. Tüm palet, sandık ve kafes elemanlarını içeren demonte paketleri hazırlayarak size sevk eder.\n\nKaliteli, dürüst ve hızlı bir pazarlama ilkesi benimsemiş olan firmamız, müşterilerini memnun etmek amacıyla özel boy ve ebatta kereste, palet, sandık, kafes vb. ahşap ambalaj malzemeleri üretmektedir. Bu amaçla yola çıkan firmamız, kalitesinden ve dürüstlüğünden taviz vermeden hizmetlerine devam etmektedir.",
						),
						'image' => array( 'label' => 'Görsel', 'type' => 'image', 'default' => 0 ),
					),
				),

				'aim' => array(
					'label'  => 'Amacımız',
					'fields' => array(
						'title'     => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Amacımız' ),
						'statement' => array( 'label' => 'Ana Cümle', 'type' => 'textarea', 'default' => 'En kaliteli malzemeyi en kısa zamanda müşterimizin hizmetine sunmak.' ),
						'body'      => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Bu bağlamda firmamızın çalışmaları, daha kaliteli ve daha hesaplı malzemeyi tüketicinin hizmetine sunma ilkesiyle devam etmektedir.' ),
					),
				),

				'quality' => array(
					'label'  => 'Kalite Politikamız',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Kalite Politikamız' ),
						'intro' => array( 'label' => 'Giriş', 'type' => 'textarea', 'default' => 'Aşağıdaki ilkeleri kalite politikamız olarak belirledik ve hedefledik.' ),
						'items' => array(
							'label'   => 'İlkeler',
							'type'    => 'repeater',
							'max'     => 10,
							'fields'  => array(
								'text' => array( 'label' => 'İlke', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'text' => 'Ürünlerimizi zamanında teslim ederek, uygun fiyat avantajı sağlayarak ve kaliteli ürün ve ekipmanlarımızı sunarak müşteri ihtiyaç ve beklentilerini karşılamak, sürekli gelişmelerini sağlamak.' ),
								array( 'text' => 'Kalite bilincini yerleştirerek, çalışanlarımızın sağlığı ve çevrenin korunması için maddi ve insan kaynaklarımızı seferber etmek ve tüm çalışanların gelişmelerini sağlamak.' ),
								array( 'text' => 'Kuruluş olarak her alanda sürekli iyileşme ve gelişme sağlamak.' ),
								array( 'text' => 'Yaptığımız işi ilk seferinde ve her seferinde doğru yapmak.' ),
								array( 'text' => 'Müşteri istek ve görüşlerini esas almak; işimizi standartlara ve kalite yönetim sistemimize uygun olarak yapmak.' ),
								array( 'text' => 'Kalite bilincinin artması için tedarikçilerimiz ile birlikte koordineli çalışma içinde bulunmak.' ),
							),
						),
					),
				),

				'group' => array(
					'label'  => 'Koçist Grup',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Koçist Grup' ),
						'body'  => array(
							'label'   => 'Metin (paragraflar arasında boş satır bırakın)',
							'type'    => 'textarea',
							'default' => "Koçist Grup, orman ürünleri alanındaki yatırımı ile 37 yıldır; yapı ve inşaat sektörünün malzeme tedarikçisi olarak 2 yıldır hizmet vermektedir. 37 yıllık tecrübe ile Koçist Grup; doğal ortamları daha güzel ve yaşanabilir ortamlara dönüştürme becerisine sahip olan, insan eliyle oluşan yapılar inşa etmeye karar vermiştir.\n\nYapı ve inşaat sektörü; zaman içinde gelişim göstererek, ilkel yaşam alanlarından akıllı binalara, tozlu patikalardan peyzaj ve çevre düzenlemesi yapılmış huzurlu yaşam alanlarına dönüşerek çeşitli teknik ve estetik evrimlere uğramıştır. Dolayısıyla sektör temsilcileri, insanlık gelişim tarihini ve kültür evrimini günümüze taşıyan ve geleceği inşa eden yatırımcılardır.\n\nKoçist Yapı İnşaat, sizleri mutlu edecek yaşam ve çalışma alanları inşa etmekte ve sizler için geleceği inşa eden bir yatırımcı olmayı hedeflemektedir. Koçist Grup, 2016 yılından itibaren Koçist Teknoloji markası ile bilişim alanında da yatırım yapmıştır.",
						),
						'facts' => array(
							'label'   => 'Öne Çıkan Bilgiler',
							'type'    => 'repeater',
							'max'     => 4,
							'fields'  => array(
								'value' => array( 'label' => 'Değer', 'type' => 'text' ),
								'label' => array( 'label' => 'Açıklama', 'type' => 'text' ),
							),
							'default' => array(
								array( 'value' => '37 yıl', 'label' => 'orman ürünleri alanında' ),
								array( 'value' => '2 yıl', 'label' => 'yapı ve inşaat malzemesi tedarikinde' ),
								array( 'value' => '2016', 'label' => 'Koçist Teknoloji ile bilişim yatırımı' ),
							),
						),
						'image' => array( 'label' => 'Görsel', 'type' => 'image', 'default' => 0 ),
					),
				),
			),
		),

		/*
		 * Insan Kaynaklari (/insan-kaynaklari/). Ise alim gercek bir sirali
		 * surec oldugu icin adimlar numarali basilir; politika ilkeleri sira
		 * bildirmedigi icin numarasiz liste.
		 */
		'hr' => array(
			'label'      => 'İnsan Kaynakları',
			'path'       => '/insan-kaynaklari/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Kurumsal / İnsan Kaynakları' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İnsan Kaynakları' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Başarımıza çalışanlarımızın gücü ve desteğiyle ulaştık; en önemli değerimiz insan kaynağımız.' ),
					),
				),

				'policy' => array(
					'label'  => 'İnsan Kaynakları Politikamız',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İnsan Kaynakları Politikamız' ),
						'intro' => array( 'label' => 'Giriş', 'type' => 'textarea', 'default' => 'Sahip olduğu başarıya çalışanlarının gücü ve desteği ile ulaşan ve en önemli değerinin insan kaynağı olduğuna inanan Koçist Grup, aşağıdaki ilkeleri insan kaynakları politikası olarak benimsemiştir.' ),
						'items' => array(
							'label'   => 'İlkeler',
							'type'    => 'repeater',
							'max'     => 10,
							'fields'  => array(
								'text' => array( 'label' => 'İlke', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'text' => 'Görevin gerektirdiği bilgi ve beceriye sahip kaliteli insan gücünü bünyesine kazandırmak.' ),
								array( 'text' => 'Çalışanlarının yaratıcılıklarını kullanabilecekleri ve fikirlerini dile getirebilecekleri etkin bir iletişim ve motivasyon ortamı sağlamak.' ),
								array( 'text' => 'Farklı bakış açıları ve bilgi birikimlerini bir arada barındıran katılımcı yönetim politikası izlemek.' ),
								array( 'text' => 'Çalışanlarının kişisel ve mesleki gelişimlerini ön planda tutarak sürekli öğrenme ve gelişimi desteklemek.' ),
								array( 'text' => 'Çalışan performanslarını objektif kriterlerle değerlendirerek yüksek performansı ödüllendirmek ve teşvik etmek.' ),
								array( 'text' => 'Yenilikçi insan kaynakları uygulamalarını hayata geçirerek çalışanlarına daima en iyiyi sunmak.' ),
							),
						),
					),
				),

				'hiring' => array(
					'label'  => 'İşe Alım Süreci',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İşe Alım Süreci' ),
						'intro' => array( 'label' => 'Giriş', 'type' => 'textarea', 'default' => 'İşe alım sürecimizin amacı, doğru adayı uygun pozisyonla eşleştirmektir.' ),
						'steps' => array(
							'label'   => 'Adımlar (sırayla)',
							'type'    => 'repeater',
							'max'     => 10,
							'fields'  => array(
								'title' => array( 'label' => 'Adım', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Başvuru', 'text' => 'Açık pozisyonlar için Koçist Grup’un ihtiyaç ve istihdam çalışmaları ile bireysel başvurular değerlendirilir.' ),
								array( 'title' => 'Ön görüşme', 'text' => 'Uygun adaylar İnsan Kaynakları Departmanı ile ön görüşmeye davet edilir.' ),
								array( 'title' => 'Testler', 'text' => 'Ön görüşmede uygun görülen adaylara, pozisyonun gerekliliğine göre dikkat testi, genel yetenek testi, yabancı dil testi, kişilik envanteri ve vaka çalışmaları uygulanır.' ),
								array( 'title' => 'Yönetici görüşmesi', 'text' => 'Kısa listeye kalan adaylar ilgili bölüm yöneticileri ile görüşür.' ),
								array( 'title' => 'Referans ve teklif', 'text' => 'Bölüm yöneticisi tarafından onaylanan adaylara, referans ve ücret çalışmaları sonrasında teklif sunulur.' ),
								array( 'title' => 'İşe başlama ve oryantasyon', 'text' => 'Teklifi kabul eden aday işe başlar; oryantasyon ve İSG eğitimleri ile göreve adaptasyonu sağlanır.' ),
							),
						),
					),
				),

				'performance' => array(
					'label'  => 'Performans Değerlendirme',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Performans Değerlendirme' ),
						'body'  => array(
							'label'   => 'Metin (paragraflar arasında boş satır bırakın)',
							'type'    => 'textarea',
							'default' => "Koçist Grup, hedeflerle yönetim prensipleri doğrultusunda çalışanlarının performanslarını ölçer. Çalışanların performansı, hedeflerin yanı sıra yetkinliklerin de kullanıldığı bir sistem ile ölçülür.\n\nKoçist Grup yetkinlikleri, tüm grup şirketlerinin yöneticileri ile düzenlenen çalıştaylarda belirlenmiş olup, yetkinlikler Koçist Grup’un işlerinde başarıyı getiren davranış örnekleri üzerine belirlenmiştir. Genel esasları Koçist Grup tarafından belirlenmiş olan Performans Yönetim Sistemi, şirket bazında uygulamada farklı ihtiyaçlara cevap verecek esnekliğe sahiptir.\n\nPerformans Değerlendirme Sistemi, ara dönem ve yıl sonu olmak üzere 2 dönemde değerlendirilir. Performans Yönetim Sistemi; Eğitim ve Gelişim, Kariyer Yönetimi, Ödül Yönetimi ve Potansiyel Değerlendirme süreçlerine girdi sağlar.",
						),
					),
				),

				'apply' => array(
					'label'  => 'Başvuru Çağrısı',
					'fields' => array(
						'title'        => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Ekibimize katılmak ister misiniz?' ),
						'text'         => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'Özgeçmişinizi ve ilgilendiğiniz pozisyonu e-posta ile gönderin; İnsan Kaynakları Departmanımız başvurunuzu değerlendirip size dönüş yapar.' ),
						'button_label' => array( 'label' => 'Buton Metni', 'type' => 'text', 'default' => 'Özgeçmiş gönderin' ),
						'button_url'   => array( 'label' => 'Buton Bağlantısı', 'type' => 'url', 'default' => 'mailto:info@kocist.com.tr?subject=%C4%B0%C5%9F%20ba%C5%9Fvurusu' ),
						'email'        => array( 'label' => 'Görünen E-posta', 'type' => 'text', 'default' => 'info@kocist.com.tr' ),
					),
				),
			),
		),

		/*
		 * KVKK aydinlatma metni (/kvkk/). Kisa tutuldu: formlarin topladigi
		 * veriler, amac, saklama ve basvuru yolu. Alt seritteki "#kvkk"
		 * capasi ve form onay kutusu bu sayfaya baglanir.
		 */
		'kvkk' => array(
			'label'      => 'KVKK Metni',
			'path'       => '/kvkk/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Kurumsal / KVKK' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'KVKK Aydınlatma Metni' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Kişisel verilerinizi hangi amaçla işlediğimizi ve haklarınızı kısaca anlatıyoruz.' ),
					),
				),

				'notice' => array(
					'label'  => 'Aydınlatma Metni',
					'fields' => array(
						'title'   => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Kişisel verileriniz' ),
						'intro'   => array( 'label' => 'Giriş', 'type' => 'textarea', 'default' => '6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) uyarınca, veri sorumlusu sıfatıyla sizi bilgilendiriyoruz.' ),
						'updated' => array( 'label' => 'Güncelleme Notu', 'type' => 'text', 'default' => 'Son güncelleme: Ekim 2026', 'hint' => 'Metni değiştirdiğinizde tarihi de güncelleyin. Boş bırakılırsa görünmez.' ),
						'items'   => array(
							'label'   => 'Bölümler',
							'type'    => 'repeater',
							'max'     => 12,
							'fields'  => array(
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'body'  => array( 'label' => 'Metin (paragraflar arasında boş satır bırakın)', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Veri sorumlusu', 'body' => 'Koçist Orman Ürünleri, Kestanelik Mahallesi, Çatalca / İstanbul.' ),
								array( 'title' => 'Hangi verileri, neden işliyoruz', 'body' => 'Teklif ve iletişim formlarıyla ya da telefon, e-posta ve WhatsApp üzerinden ilettiğiniz ad soyad, firma adı, telefon, e-posta ve talep içeriğini; talebinize dönüş yapmak, teklif hazırlamak ve siparişinizi yürütmek için işliyoruz.' ),
								array( 'title' => 'Hukuki sebep', 'body' => 'Verileriniz KVKK madde 5/2 kapsamında, bir sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması ve meşru menfaatimiz sebeplerine dayanılarak işlenir.' ),
								array( 'title' => 'Aktarım ve saklama', 'body' => 'Verileriniz satılmaz ve pazarlama amacıyla paylaşılmaz. Yalnızca hizmet aldığımız barındırma ve e-posta sağlayıcılarıyla ve yasal zorunluluk hâlinde yetkili kurumlarla paylaşılabilir. Talebin gerektirdiği ve mevzuatın öngördüğü süre boyunca saklanır, ardından silinir ya da anonim hâle getirilir.' ),
								array( 'title' => 'Haklarınız', 'body' => "KVKK madde 11 uyarınca verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini isteme ve işlenmesine itiraz etme haklarına sahipsiniz.\n\nBaşvurunuzu info@kocist.com.tr adresine iletebilirsiniz; en geç 30 gün içinde yanıtlarız." ),
							),
						),
					),
				),
			),
		),

		/*
		 * Blog (/blog/, kategori arsivleri ve tekil yazi). Yazilarin kendisi
		 * WordPress yazisidir (tema ilk kurulumda bir kez olusturur); burada
		 * yalnizca sayfa metinleri duruyor.
		 */
		'blog' => array(
			'label'      => 'Blog',
			'path'       => '/blog/',
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Blog' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Blog – Haberler' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Koçist’ten haberler; orman ürünlerinde güncel trendler ve bilgilendirmeler.' ),
					),
				),

				'listing' => array(
					'label'  => 'Yazı Listesi',
					'fields' => array(
						'all_label'  => array( 'label' => 'Filtre: Tümü Metni', 'type' => 'text', 'default' => 'Tüm yazılar' ),
						'filter_label' => array( 'label' => 'Filtre Başlığı (ekran okuyucu için)', 'type' => 'text', 'default' => 'Kategoriye göre süz' ),
						'read_label' => array( 'label' => 'Kart Bağlantı Metni', 'type' => 'text', 'default' => 'Yazıyı okuyun' ),
						'empty_text' => array( 'label' => 'Yazı Yoksa Gösterilecek Metin', 'type' => 'text', 'default' => 'Bu kategoride henüz yazı yok.' ),
						'prev_label' => array( 'label' => 'Sayfalama: Önceki', 'type' => 'text', 'default' => 'Önceki' ),
						'next_label' => array( 'label' => 'Sayfalama: Sonraki', 'type' => 'text', 'default' => 'Sonraki' ),
					),
				),

				'single' => array(
					'label'  => 'Yazı Sayfası',
					'fields' => array(
						'back_label'    => array( 'label' => 'Geri Bağlantı Metni', 'type' => 'text', 'default' => 'Tüm yazılar' ),
						'related_title' => array( 'label' => 'İlgili Yazılar Başlığı', 'type' => 'text', 'default' => 'İlgili yazılar' ),
					),
				),
			),
		),

		/*
		 * Urun sayfasi. Solda galeri, sagda urun bilgileri; altinda teknik
		 * ozellik tablosu ve sikca sorulan sorular.
		 *
		 * Demo kapsaminda tek urun var (ahsap tavuk kumesi). Gercek bir
		 * katalogda bunun yerine bir icerik turu (CPT) gelir; burada amac
		 * sayfa tasariminin panelden yonetilebildigini gostermek.
		 *
		 * Fiyat ve sepet alani bilincli olarak yok: bu bir e-ticaret degil,
		 * teklif toplayan bir kurumsal site.
		 *
		 * Yayinda ornek urun yok: /urun/ adresi /kategoriler/'e 301 ile gider
		 * (functions.php, panel onizlemesi haric). 'seo' => false: SEO sayfasi
		 * sayilmaz (meta, JSON-LD, llms.txt, site haritasi, SEO puani; NWCS
		 * 0.27.5). Butonlar ve ortak yazilar tum havuz urunlerinde
		 * (/urun/<slug>/) kullanildigi icin sayfa panelde duruyor.
		 */
		'product' => array(
			'label'      => 'Ürün Sayfaları',
			'path'       => '/urun/',
			'seo'        => false,
			'components' => array(

				'main' => array(
					'label'  => 'Butonlar (tüm ürünler) ve Örnek Ürün',
					'fields' => array(
						'image'           => array( 'label' => 'Ana Görsel', 'type' => 'image', 'default' => 0 ),
						'gallery'         => array(
							'label'   => 'Galeri Görselleri',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'image' => array( 'label' => 'Görsel', 'type' => 'image' ),
								'alt'   => array( 'label' => 'Görsel Açıklaması', 'type' => 'text' ),
							),
							'default' => array(
								array( 'image' => 0, 'alt' => 'Bahçede ahşap tavuk kümesi' ),
								array( 'image' => 0, 'alt' => 'Ahşap tavuk kümesi yandan görünüm' ),
								array( 'image' => 0, 'alt' => 'Beyaz ahşap tavuk kümesi' ),
							),
						),
						'category'        => array( 'label' => 'Kategori Etiketi', 'type' => 'text', 'default' => 'Ahşap Dekorasyon' ),
						'title'           => array( 'label' => 'Ürün Adı', 'type' => 'text', 'default' => 'Ahşap Tavuk Kümesi' ),
						'subtitle'        => array( 'label' => 'Ürün Alt Başlığı', 'type' => 'text', 'default' => 'Emprenyeli ahşaptan, dış mekâna dayanıklı, montaja hazır' ),
						'description'     => array( 'label' => 'Ürün Açıklaması', 'type' => 'textarea', 'default' => 'Kümes, birinci sınıf emprenyeli çam kerestesinden üretilir. Gezinti alanı galvanizli tel örgü ile kaplanır, yuva bölümü yalıtımlıdır. Yumurta toplama kapağı dışarıdan açılır; temizlik için taban tablası çekmece şeklinde çıkar.' ),
						'features'        => array(
							'label'   => 'Öne Çıkan Özellikler',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'icon' => array( 'label' => 'İkon', 'type' => 'icon' ),
								'text' => array( 'label' => 'Özellik', 'type' => 'text' ),
							),
							'default' => array(
								array( 'icon' => 'tree', 'text' => 'Birinci sınıf emprenyeli çam kerestesi' ),
								array( 'icon' => 'shield', 'text' => 'Dış mekâna dayanıklı, yalıtımlı yuva bölümü' ),
								array( 'icon' => 'tools', 'text' => 'Kurulu teslim, ek montaj gerektirmez' ),
								array( 'icon' => 'ruler', 'text' => 'Talebe göre özel ölçü üretim' ),
							),
						),
						'cta_label'       => array( 'label' => 'Birincil Buton Metni', 'type' => 'text', 'default' => 'Teklif Alın' ),
						'cta_url'         => array( 'label' => 'Birincil Buton Bağlantısı', 'type' => 'url', 'default' => '/iletisim/' ),
						'secondary_label' => array( 'label' => 'İkincil Buton Metni', 'type' => 'text', 'default' => 'WhatsApp’tan Sorun' ),
						'secondary_url'   => array( 'label' => 'İkincil Buton Bağlantısı', 'type' => 'url', 'default' => 'https://wa.me/905496481919' ),
					),
				),

				/*
				 * Havuzdan gelen her urunun sayfasinda (/urun/<urun>/) ortak
				 * metinler. Urunun kendi bilgileri Urun Havuzu'ndadir.
				 */
				'detail' => array(
					'label'  => 'Havuz Ürün Sayfaları (ortak metinler)',
					'fields' => array(
						'related_sub'   => array( 'label' => 'Benzer Ürünler Başlığı (kategori) — {kategori} kategorinin adı olur', 'type' => 'text', 'default' => '{kategori} kategorisinde diğer ürünler' ),
						'related_group' => array( 'label' => 'Benzer Ürünler Başlığı (grup) — {grup} grubun adı olur', 'type' => 'text', 'default' => '{grup} grubunda diğer ürünler' ),
						'see_all'       => array( 'label' => 'Tümünü Gör Bağlantısı', 'type' => 'text', 'default' => 'Tümünü gör' ),

						// Akordeonun altinda: urunun kategorisinin ilk uc sorusu (Ürün Havuzu -> Kategoriler).
						'faq_title'     => array( 'label' => 'Akordeonun altı: kategori soruları başlığı', 'type' => 'text', 'default' => 'Sık sorulan sorular', 'hint' => 'Ürünün kategorisinde soru varsa ilk üçü burada görünür; yoksa yerine SSS sayfasına bağlantı çıkar.' ),
						'faq_all'       => array( 'label' => 'Akordeonun altı: kategorinin bütün sorularına bağlantı', 'type' => 'text', 'default' => 'Tüm sorular' ),

						// Galerinin altindaki akordeon: uc panel, ayni anda biri acik.
						'tab_specs'     => array( 'label' => 'Akordeon 1. başlık (ürün kodu ve teknik detaylar)', 'type' => 'text', 'default' => 'Teknik Detaylar' ),
						'code_label'    => array( 'label' => 'Akordeon 1: ilk satır (ürün kodu)', 'type' => 'text', 'default' => 'Ürün kodu' ),
						'specs_empty'   => array( 'label' => 'Akordeon 1: teknik detayı olmayan üründe', 'type' => 'text', 'default' => 'Teknik detay henüz girilmedi.' ),
						'tab_desc'      => array( 'label' => 'Akordeon 2. başlık (ürünün detay metni ve tabloları)', 'type' => 'text', 'default' => 'Ürün Açıklaması' ),
						'desc_empty'    => array( 'label' => 'Akordeon 2: detay metni olmayan üründe', 'type' => 'text', 'default' => 'Açıklama henüz girilmedi.' ),
						'tab_delivery'  => array( 'label' => 'Akordeon 3. başlık', 'type' => 'text', 'default' => 'Lojistik ve Teslimat' ),
						'delivery_text' => array( 'label' => 'Akordeon 3: metin (tüm ürünler)', 'type' => 'textarea', 'default' => 'Sevkiyat şekli ve süresi teklifle birlikte yazılı olarak bildirilir.', 'hint' => 'Üründe "Lojistik ve Teslimat" adlı bir detay satırı varsa o ürünün sayfasında bu metin yerine o satır gösterilir.' ),

						// Fiyat yuvasi: fiyatli urunde fiyat, fiyatsizda rozet + neden.
						'price_badge'   => array( 'label' => 'Fiyatı olmayan ürün: fiyat yerine rozet', 'type' => 'text', 'default' => 'Fiyat teklifle' ),
						'price_reason'  => array( 'label' => 'Fiyatı olmayan ürün: nedeni (tüm ürünler)', 'type' => 'textarea', 'default' => 'Bu ürün için sitede sabit fiyat yok: fiyat stok durumuna, ölçüye ve adede göre belirlenir. Ölçü ve adedi yazın; yazılı teklif gönderelim.', 'hint' => 'Ürün grubuna özel metin "WhatsApp ve Teklif" bölümünde girilebilir; boşsa bu metin görünür. Kişiye özel fiyat izlenimi veren ifade kullanmayın.' ),

						// Sayfadaki teklif formu (alanlar Iletisim formunun etiketlerini de kullanir).
						'form_title'     => array( 'label' => 'Teklif formu başlığı', 'type' => 'text', 'default' => 'Teklif isteyin' ),
						'company_label'  => array( 'label' => 'Teklif formu: şirket etiketi', 'type' => 'text', 'default' => 'Şirket' ),
						'qty_label'      => array( 'label' => 'Teklif formu: adet / ölçü etiketi', 'type' => 'text', 'default' => 'Adet / Ölçü' ),
						'qty_ph'         => array( 'label' => 'Teklif formu: adet / ölçü ipucu', 'type' => 'text', 'default' => 'Kaç adet, hangi ölçüde?' ),
						'consent_label'  => array( 'label' => 'Onay kutusu metni (ürün ve iletişim formu)', 'type' => 'text', 'default' => 'Bilgilerimin bu talebe dönüş için kullanılmasını kabul ediyorum.', 'hint' => 'Alt şeritteki KVKK bağlantısı gerçek bir sayfaya gidince metnin yanında bağlantı kendiliğinden görünür.' ),
						'form_jump'      => array( 'label' => 'Telefonda: forma git bağlantısı', 'type' => 'text', 'default' => 'Teklif formuna git' ),
						'form_jump_note' => array( 'label' => 'Telefonda: bağlantının yanındaki not', 'type' => 'text', 'default' => 'Form, ürün bilgilerinin altında.' ),
					),
				),

				/*
				 * Urunden acilan WhatsApp ve teklif formu (inc/quote.php).
				 * Numara urunun grubuna gore secilir (sabit grup anahtari).
				 * {urun} urun adi, {kategori} kategori adi, {url} urun sayfasi,
				 * {ozellik} urunun ozellik/olcu metni olur; degeri bos olan
				 * satir mesajdan atilir.
				 */
				'whatsapp' => array(
					'label'  => 'WhatsApp ve Teklif (ürünlerden)',
					'fields' => array(
						'wa_kereste'    => array( 'label' => 'WhatsApp Numarası: Kereste ürünleri', 'type' => 'text', 'default' => '0532 374 98 32' ),
						'wa_ambalaj'    => array( 'label' => 'WhatsApp Numarası: Ambalaj ürünleri', 'type' => 'text', 'default' => '0532 374 98 32' ),
						'wa_hirdavat'   => array( 'label' => 'WhatsApp Numarası: Hırdavat ürünleri', 'type' => 'text', 'default' => '0532 374 98 32' ),
						'wa_dekorasyon' => array( 'label' => 'WhatsApp Numarası: Dekorasyon ürünleri', 'type' => 'text', 'default' => '0549 648 19 19' ),
						'product_msg'   => array( 'label' => 'Üründen WhatsApp Mesajı — {urun}, {kategori}, {url}, {ozellik}', 'type' => 'textarea', 'default' => 'Merhaba, {urun} ({kategori}) hakkında bilgi almak istiyorum. {url}' ),
						'quote_subject' => array( 'label' => 'Teklif Formu Konusu (üründen gelince) — {urun}, {kategori}', 'type' => 'text', 'default' => '{urun} ({kategori})' ),
						'quote_msg'     => array( 'label' => 'Teklif Formu Mesajı (üründen gelince) — {urun}, {kategori}, {url}, {ozellik}', 'type' => 'textarea', 'default' => "Merhaba, {urun} ({kategori}) için fiyat teklifi almak istiyorum.\nÖzellik: {ozellik}\nÖlçü ve adet: " ),
						'quote_label'   => array( 'label' => 'Teklif Formunda Seçilen Ürün Yazısı', 'type' => 'text', 'default' => 'Teklif istediğiniz ürün' ),

						// Fiyati olmayan urunde neden metni, gruba ozel. Bos: "Havuz Urun Sayfalari"ndaki site geneli metin.
						'reason_kereste'    => array( 'label' => 'Fiyat gösterilmeme nedeni: Kereste ürünleri (boşsa ortak metin)', 'type' => 'textarea', 'default' => '' ),
						'reason_ambalaj'    => array( 'label' => 'Fiyat gösterilmeme nedeni: Ambalaj ürünleri (boşsa ortak metin)', 'type' => 'textarea', 'default' => '' ),
						'reason_hirdavat'   => array( 'label' => 'Fiyat gösterilmeme nedeni: Hırdavat ürünleri (boşsa ortak metin)', 'type' => 'textarea', 'default' => '' ),
						'reason_dekorasyon' => array( 'label' => 'Fiyat gösterilmeme nedeni: Dekorasyon ürünleri (boşsa ortak metin)', 'type' => 'textarea', 'default' => '' ),
					),
				),

				'specs' => array(
					'label'  => 'Teknik Özellikler (yalnızca örnek ürün)',
					'fields' => array(
						'title' => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Teknik Özellikler' ),
						'name'  => array( 'label' => 'Tablo Başlığı (ürün modeli)', 'type' => 'text', 'default' => 'Ahşap Tavuk Kümesi — Standart Model' ),
						'rows'  => array(
							'label'   => 'Özellik Satırları',
							'type'    => 'repeater',
							'max'     => 16,
							'fields'  => array(
								'label' => array( 'label' => 'Özellik Adı', 'type' => 'text' ),
								'value' => array( 'label' => 'Değer', 'type' => 'text' ),
							),
							'default' => array(
								array( 'label' => 'Genişlik', 'value' => '~150 cm' ),
								array( 'label' => 'Uzunluk', 'value' => '~200 cm (modele göre değişir)' ),
								array( 'label' => 'Yükseklik', 'value' => '~160 cm' ),
								array( 'label' => 'Kapasite', 'value' => '8 - 12 tavuk' ),
								array( 'label' => 'Dış Kaplama', 'value' => 'Emprenyeli çam, su bazlı boya' ),
								array( 'label' => 'Çatı Kaplaması', 'value' => 'Shingle (antrasit / kırmızı / yeşil)' ),
								array( 'label' => 'Gezinti Alanı', 'value' => 'Galvanizli tel örgü' ),
								array( 'label' => 'Zemin', 'value' => 'Çekmece tablalı, temizlenebilir' ),
								array( 'label' => 'Montaj Durumu', 'value' => 'KURULU TESLİM' ),
							),
						),
					),
				),
			),
		),

		/*
		 * Sik sorulan sorular (/sss/). Urun sayfalarindaki eski ortak SSS
		 * bolumunun yerine gecer; urun sayfasinda yalnizca urunun grubuna
		 * giden bir baglanti kalir (grup adi = urun grubu: Kereste, Ambalaj,
		 * Dekorasyon, Hirdavat; baglanti #sss-<grup> capasina gider).
		 *
		 * Cevaplar yalnizca sitenin mevcut metinlerinden (siparis sureci,
		 * banka notu, kurumsal profil, katalog). Cevabi bos sorular firmadan
		 * cevap bekliyor (DEVAM.md): sitede ve arama verisinde gorunmezler.
		 */
		'faq' => array(
			'label'      => 'Sık Sorulan Sorular',
			'path'       => '/sss/',
			'seo_source' => array(
				'type'        => 'FAQPage',
				'questions'   => 'items.rows',
				'title'       => 'page_head.title',
				'description' => 'page_head.subtitle',
			),
			'components' => array(

				'page_head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'breadcrumb' => array( 'label' => 'Yol Göstergesi', 'type' => 'text', 'default' => 'Ana Sayfa / Sık Sorulan Sorular' ),
						'title'      => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sık sorulan sorular' ),
						'subtitle'   => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Teklif, ödeme, özel ölçü üretim, kereste, ambalaj ve hırdavat hakkında en çok sorulanlar.' ),
					),
				),

				'items' => array(
					'label'  => 'Sorular',
					'fields' => array(
						'rows' => array(
							'label'   => 'Soru ve Cevaplar (aynı grup adı bir başlık altında toplanır)',
							'type'    => 'repeater',
							'max'     => 40,
							'hint'    => 'Cevabı boş soru sitede ve arama verisinde görünmez; cevabı firmadan gelince doldurun. Grup adları ürün gruplarıyla aynı kalmalı (Kereste, Ambalaj, Dekorasyon, Hırdavat): ürün sayfasındaki bağlantı ürünün grubuna gider.',
							'fields'  => array(
								'group'    => array( 'label' => 'Grup', 'type' => 'text' ),
								'question' => array( 'label' => 'Soru', 'type' => 'text' ),
								'answer'   => array( 'label' => 'Cevap', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'group' => 'Genel', 'question' => 'Fiyat teklifi nasıl alırım?', 'answer' => 'Ürünü, ölçüyü, adedi ve teslim adresini telefonla, e-postayla ya da iletişim formundan gönderin.' ),
								array( 'group' => 'Genel', 'question' => 'Ödeme nasıl yapılır?', 'answer' => '' ),
								array( 'group' => 'Genel', 'question' => 'Özel ölçüde üretim yapıyor musunuz?', 'answer' => 'Evet. Kereste, palet, sandık ve kafesi her ölçüde, ihtiyacınıza göre tasarlıyoruz. Kullandığınız ölçüleri, şartnameleri ya da proje çizimlerini gönderirseniz onlara göre üretime geçiyoruz.' ),
								array( 'group' => 'Genel', 'question' => 'Tesisiniz nerede, ziyaret edebilir miyim?', 'answer' => 'Tesisimiz Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca / İstanbul adresinde. Ziyarete de bekleriz.' ),
								array( 'group' => 'Genel', 'question' => 'Sevkiyat süresi nedir?', 'answer' => '' ),
								array( 'group' => 'Genel', 'question' => 'Teslimat hangi illere, nasıl yapılıyor?', 'answer' => '' ),
								array( 'group' => 'Genel', 'question' => 'En az sipariş miktarı var mı?', 'answer' => '' ),
								array( 'group' => 'Genel', 'question' => 'Toptan fiyat uygulanıyor mu?', 'answer' => '' ),

								array( 'group' => 'Kereste', 'question' => 'Hangi kereste ve levha türleri var?', 'answer' => 'Kerestede kalas, çıta, rabıta (lambiri ve döşeme), maden direği, takoz ve inşaatlık / çatılık kereste; levhada OSB, kontrplak ve plywood. Güncel liste menüdeki Kereste kategorilerinde.' ),
								array( 'group' => 'Kereste', 'question' => 'Kerestenin metreküpü nasıl hesaplanır?', 'answer' => 'Kalınlığı ve genişliği santimetreden metreye çevirin, boy ve adetle çarpın. Örneğin 5 × 20 cm kesitli, 4 m boyunda 50 adet kalas: 0,05 × 0,20 × 4 × 50 = 2 m³.' ),
								array( 'group' => 'Kereste', 'question' => 'İstediğim ölçüde kesim yapılıyor mu?', 'answer' => '' ),
								array( 'group' => 'Kereste', 'question' => 'Emprenyeli ya da fırınlanmış kereste var mı?', 'answer' => '' ),
								array( 'group' => 'Kereste', 'question' => 'Levha ölçüleri nelerdir?', 'answer' => '' ),

								array( 'group' => 'Ambalaj', 'question' => 'İhracat için ısıl işlem (ISPM 15) yapıyor musunuz?', 'answer' => 'Evet, ihracata uygun ısıl işlem uyguluyoruz. ISPM-15 ve HT yetki belgelerimiz ile ısıl işlem tesisimizin görselleri Katalog sayfasında.' ),
								array( 'group' => 'Ambalaj', 'question' => 'Sandık ve kafes ölçüye göre yapılıyor mu?', 'answer' => 'Evet. Palet, sandık ve kafesi ihtiyacınıza göre tasarlıyor ya da mevcut ölçülerinizi, şartnamenizi veya proje çiziminizi alarak üretiyoruz. Tüm elemanları içeren demonte paketleri hazırlayıp sevk ediyoruz.' ),
								array( 'group' => 'Ambalaj', 'question' => 'Euro paletin ölçüsü ve yük kapasitesi nedir?', 'answer' => '' ),
								array( 'group' => 'Ambalaj', 'question' => 'İkinci el palet alıyor ya da satıyor musunuz?', 'answer' => '' ),
								array( 'group' => 'Ambalaj', 'question' => 'Palet damgası teklifte belirtilir mi?', 'answer' => '' ),

								array( 'group' => 'Dekorasyon', 'question' => 'Montaj dahil mi?', 'answer' => '' ),
								array( 'group' => 'Dekorasyon', 'question' => 'Ahşap dış koşullara dayanıklı mı, bakım gerekir mi?', 'answer' => '' ),
								array( 'group' => 'Dekorasyon', 'question' => 'Özel ölçüde yapılıyor mu?', 'answer' => '' ),
								array( 'group' => 'Dekorasyon', 'question' => 'Sevkiyat süresi nedir?', 'answer' => '' ),
								array( 'group' => 'Dekorasyon', 'question' => 'Teslimat hangi illere yapılıyor?', 'answer' => '' ),

								array( 'group' => 'Hırdavat', 'question' => 'Katalog ya da ürün listesi var mı?', 'answer' => 'Vida, somun ve bits uç listemiz (2025) Katalog sayfasında, Belgeler sekmesinde.' ),
								array( 'group' => 'Hırdavat', 'question' => 'Çivi ve zımba teli hangi tabancalara uyar?', 'answer' => '' ),
								array( 'group' => 'Hırdavat', 'question' => 'Koli ve paket adetleri nedir?', 'answer' => '' ),
								array( 'group' => 'Hırdavat', 'question' => 'Yalnızca toptan mı satıyorsunuz?', 'answer' => '' ),
							),
						),
					),
				),

				'more' => array(
					'label'  => 'Kapanış ve Bağlantılar',
					'fields' => array(
						'group_other'  => array( 'label' => 'Grubu boş sorular için grup adı', 'type' => 'text', 'default' => 'Genel' ),
						'text'         => array( 'label' => 'Kapanış satırı (yanındaki numara üst şeritteki telefondur)', 'type' => 'text', 'default' => 'Sorunuzun cevabı burada yoksa arayın:' ),
						'product_link' => array( 'label' => 'Ürün sayfasındaki bağlantı — {grup}: SSS grup adı', 'type' => 'text', 'default' => '{grup} ürünleriyle ilgili sık sorulan sorular' ),
						'product_all'  => array( 'label' => 'Ürün sayfasındaki bağlantı (ürünün grubunda cevaplı soru yoksa)', 'type' => 'text', 'default' => 'Sık sorulan sorular' ),
					),
				),
			),
		),

		/*
		 * Iletisim sayfasi.
		 *
		 * Harita: ayni tesisteki uc isletme sekme sekme. Panelde iframe adresi
		 * degil isletmenin adi, koordinati ve Google kaydi tutuluyor; gomme
		 * adresini tema kuruyor (template-parts/sections/contact-main.php).
		 * Boylece iframe kaynagi her zaman google.com kaliyor; panele girilen
		 * bir deger sayfaya keyfi bir site gomemiyor.
		 *
		 * Form bilincli olarak DEMO: hicbir yere gonderilmiyor, kaydedilmiyor
		 * ve "gonderildi" basarisi gosterilmiyor (paletci temasindaki teklif
		 * formuyla ayni yaklasim). Gercek iletisim icin telefon, WhatsApp ve
		 * e-posta baglantilari veriliyor.
		 */
		'contact' => array(
			'label'      => 'İletişim Sayfası',
			'path'       => '/iletisim/',
			// Arama aciklamasi bandin alt basligindan baslar; tek basina form metni (66 karakter) kisa kaliyordu.
			'seo_source' => array( 'description' => 'head.subtitle' ),
			'components' => array(

				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'eyebrow'  => array( 'label' => 'Üst Etiket', 'type' => 'text', 'default' => 'İLETİŞİM' ),
						'title'    => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Bize ulaşın' ),
						'subtitle' => array( 'label' => 'Alt Başlık', 'type' => 'textarea', 'default' => 'Ölçü ve adedi yazın, yazılı teklif gönderelim. Çatalca’daki tesisimize ziyarete de bekleriz.' ),
					),
				),

				'info' => array(
					'label'  => 'İletişim Bilgileri',
					'fields' => array(
						'title'          => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'İletişim Bilgileri' ),
						'address_icon'   => array( 'label' => 'Adres İkonu', 'type' => 'icon', 'default' => 'pin' ),
						'address_title'  => array( 'label' => 'Adres Başlığı', 'type' => 'text', 'default' => 'Adres' ),
						'address'        => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, 34494 Çatalca/İstanbul' ),
						'phone_icon'     => array( 'label' => 'Telefon İkonu', 'type' => 'icon', 'default' => 'phone' ),
						'phone_title'    => array( 'label' => 'Telefon Başlığı', 'type' => 'text', 'default' => 'Telefon' ),
						'phone_label'    => array( 'label' => 'Telefon Metni', 'type' => 'text', 'default' => '0212 648 19 19' ),
						'phone_url'      => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => 'tel:+902126481919' ),
						'whatsapp_icon'  => array( 'label' => 'WhatsApp İkonu', 'type' => 'icon', 'default' => 'whatsapp' ),
						'whatsapp_title' => array( 'label' => 'WhatsApp Başlığı', 'type' => 'text', 'default' => 'WhatsApp' ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Metni', 'type' => 'text', 'default' => 'Hızlı fiyat için yazın' ),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => 'https://wa.me/905496481919' ),
						'email_icon'     => array( 'label' => 'E-posta İkonu', 'type' => 'icon', 'default' => 'mail' ),
						'email_title'    => array( 'label' => 'E-posta Başlığı', 'type' => 'text', 'default' => 'E-posta' ),
						'email_label'    => array( 'label' => 'E-posta Metni', 'type' => 'text', 'default' => 'info@kocist.com.tr' ),
						'email_url'      => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:info@kocist.com.tr' ),
						'hours_icon'     => array( 'label' => 'Çalışma Saati İkonu', 'type' => 'icon', 'default' => 'clock' ),
						'hours_title'    => array( 'label' => 'Çalışma Saatleri Başlığı', 'type' => 'text', 'default' => 'Çalışma Saatleri' ),
						'hours'          => array( 'label' => 'Çalışma Saatleri', 'type' => 'text', 'default' => 'Hafta içi 08:00 - 19:00 · Cumartesi 08:00 - 16:00' ),
					),
				),

				'form' => array(
					'label'  => 'İletişim Formu',
					'fields' => array(
						'title'        => array( 'label' => 'Form Başlığı', 'type' => 'text', 'default' => 'Teklif ve bilgi talebi' ),
						'text'         => array( 'label' => 'Form Açıklaması', 'type' => 'textarea', 'default' => 'Aradığınız ürünü ve ölçüleri yazın; en kısa sürede dönüş yapalım.' ),
						'form_note'    => array( 'label' => 'Form Alt Notu', 'type' => 'text', 'default' => 'Bilgileriniz yalnızca talebinize dönüş için kullanılır.' ),
						'success_msg'  => array( 'label' => 'Gönderim Başarılı Mesajı', 'type' => 'text', 'default' => 'Talebiniz bize ulaştı. En kısa sürede size dönüş yapacağız.' ),
						'name_label'    => array( 'label' => 'Ad Soyad Etiketi', 'type' => 'text', 'default' => 'Ad Soyad' ),
						'name_ph'       => array( 'label' => 'Ad Soyad İpucu', 'type' => 'text', 'default' => 'Adınız ve soyadınız' ),
						'phone_label'   => array( 'label' => 'Telefon Etiketi', 'type' => 'text', 'default' => 'Telefon' ),
						'phone_ph'      => array( 'label' => 'Telefon İpucu', 'type' => 'text', 'default' => '05XX XXX XX XX' ),
						'email_label'   => array( 'label' => 'E-posta Etiketi', 'type' => 'text', 'default' => 'E-posta' ),
						'email_ph'      => array( 'label' => 'E-posta İpucu', 'type' => 'text', 'default' => 'ornek@firma.com' ),
						'subject_label' => array( 'label' => 'Konu Etiketi', 'type' => 'text', 'default' => 'Konu' ),
						'subject_ph'    => array( 'label' => 'Konu İpucu', 'type' => 'text', 'default' => 'Hangi ürün grubu?' ),
						'detail_label'  => array( 'label' => 'Mesaj Etiketi', 'type' => 'text', 'default' => 'Mesajınız' ),
						'detail_ph'     => array( 'label' => 'Mesaj İpucu', 'type' => 'text', 'default' => 'Ürün, ölçü ve adet bilgisini yazın; örneğin “5x10 cm kalas, 100 adet, 3 metre”.' ),
						'submit_label'  => array( 'label' => 'Buton Metni', 'type' => 'text', 'default' => 'Gönder' ),
					),
				),

				'map' => array(
					'label'  => 'Harita',
					'fields' => array(
						'title'      => array( 'label' => 'Bölüm Başlığı', 'type' => 'text', 'default' => 'Tesisimiz' ),
						/*
						 * Eski tek konum alanlari (query, coords) kaldirildi; kayitli
						 * degerleri okunmuyor. Bu liste kaydedilmemisse asagidaki
						 * varsayilan uc isletme gorunur.
						 */
						'places'     => array(
							'label'   => 'İşletmeler (haritanın üstündeki sekmeler)',
							'type'    => 'repeater',
							'max'     => 3,
							'hint'    => 'İlk satır sayfa açılınca gösterilir. Tek satır kalırsa sekmeler görünmez.',
							'fields'  => array(
								'label'  => array( 'label' => 'Sekme Adı (kısa)', 'type' => 'text' ),
								'name'   => array( 'label' => 'İşletmenin Tam Adı', 'type' => 'text', 'hint' => 'Google Haritalar’daki adıyla aynı yazın: harita bu adla arar ve kartta görünür.' ),
								'coords' => array( 'label' => 'Koordinat (enlem,boylam)', 'type' => 'text', 'hint' => 'Örn. 41.2309908,28.5009723. Harita bu noktayı ortalar.' ),
								'url'    => array( 'label' => 'Google Haritalar Bağlantısı (Yol tarifi al)', 'type' => 'url', 'hint' => 'Google Haritalar’da “Paylaş” ile alınan bağlantı (maps.app.goo.gl/...). Boş ya da Google dışı bir adres olursa işletmenin adıyla arama bağlantısı kullanılır.' ),
								'place'  => array( 'label' => 'Google Kayıt Kodu (isteğe bağlı)', 'type' => 'text', 'hint' => 'Doluysa harita işletmenin kendi Google kartıyla açılır. İşletme Google Haritalar’da açıkken adres çubuğundaki bağlantının tamamını buraya yapıştırın (içindeki 0x…:0x… kodu kullanılır). Boşsa harita adla arar. Bağlantıyı başka işletmeye çevirirseniz bunu da güncelleyin.' ),
							),
							'default' => array(
								array(
									'label'  => 'Kereste',
									'name'   => 'KOÇİST Kereste, Orman Ürünleri ve İnşaat Malzemeleri',
									'coords' => '41.2309908,28.5009723',
									'url'    => 'https://maps.app.goo.gl/twqD4fMuUQ4prbVeA',
									'place'  => '0x14caaf39a0c83359:0x34d4e59176236bdc',
								),
								array(
									'label'  => 'Ambalaj ve Sandık',
									'name'   => 'KOÇİST Ahşap Ambalaj Ürünleri ve Sandık İmalatı Atölyesi',
									'coords' => '41.23092,28.5007532',
									'url'    => 'https://maps.app.goo.gl/iq9kGdNRHxXEyTN8A',
									'place'  => '0x14caa64d17e1232b:0x40f34e17a74dcad1',
								),
								array(
									'label'  => 'Kamelya ve Dekorasyon',
									'name'   => 'KOÇİST Kamelya ve Ahşap Dekorasyon',
									'coords' => '41.2312164,28.5016346',
									'url'    => 'https://maps.app.goo.gl/n5S9BacDQXZNqJvj7',
									'place'  => '0x14caaf977eb226a3:0xc9d691df9b0f8f0d',
								),
							),
						),
						'link_label' => array( 'label' => 'Yol Tarifi Buton Metni', 'type' => 'text', 'default' => 'Yol tarifi al' ),
					),
				),
			),
		),

		/*
		 * Urun aramasi (/arama/?ara=..., inc/search.php). Sonuc sayfasi arama
		 * motorlarina kapali (noindex, follow) ve site haritasinda yok.
		 * {terim} aranan metin, {sayi} sonuc sayisi olur.
		 */
		'arama' => array(
			'label'      => 'Arama Sayfası',
			'path'       => '/arama/',
			// Gizli: sekme listesinde yok, "Sayfa bul" ile acilir; SEO listelerine (llms.txt) girmez.
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'crumb' => array( 'label' => 'Yol göstergesindeki adı', 'type' => 'text', 'default' => 'Arama' ),
						'title' => array( 'label' => 'Başlık (arama yapılmadan)', 'type' => 'text', 'default' => 'Ürün arayın' ),
						'found' => array( 'label' => 'Başlık (sonuç varsa) — {terim}, {sayi}', 'type' => 'text', 'default' => '‘{terim}’ için {sayi} ürün' ),
						'lead'  => array( 'label' => 'Alt metin', 'type' => 'textarea', 'default' => 'Ürün kodunu, adını ya da kategorisini yazın. Türkçe karakter, boşluk ve tire fark etmez: “adrk” ile “ADR-K” aynı sonucu verir.' ),
					),
				),
				'results' => array(
					'label'  => 'Sonuçlar',
					'fields' => array(
						'short_term' => array( 'label' => 'Terim çok kısaysa', 'type' => 'text', 'default' => 'En az 2 harf ya da rakam yazın.' ),
						'limit_note' => array( 'label' => 'Sonuç çoksa not — {sayi} gösterilen sonuç sayısı', 'type' => 'text', 'default' => 'İlk {sayi} sonuç gösteriliyor. Daha az sonuç için kodu ya da adı daha uzun yazın.' ),
						'empty_title' => array( 'label' => 'Sonuç yoksa başlık — {terim}', 'type' => 'text', 'default' => '‘{terim}’ için sonuç bulunamadı', 'hint' => 'Sonuç yoksa metni, iletişim ve WhatsApp bağlantısıyla birlikte üst menüdeki canlı aramada da görünür.' ),
						'empty_text'  => array( 'label' => 'Sonuç yoksa metin', 'type' => 'textarea', 'default' => 'Kodu kısaltarak ya da ürünün adıyla tekrar arayın. Aradığınız ürün listede yoksa ölçü ve adedi yazın; tedarik edip teklif gönderelim.' ),
						'contact_label' => array( 'label' => 'Sonuç yoksa: iletişim bağlantısı', 'type' => 'text', 'default' => 'Bize yazın' ),
						'wa_label'    => array( 'label' => 'Sonuç yoksa: WhatsApp bağlantısı', 'type' => 'text', 'default' => 'WhatsApp’tan sorun' ),
						'wa_msg'      => array( 'label' => 'Sonuç yoksa: WhatsApp hazır mesajı — {terim}', 'type' => 'textarea', 'default' => 'Merhaba, Koçist web sitesinde “{terim}” aradım ama bulamadım. Bu ürün için bilgi almak istiyorum.' ),
						'all_label'   => array( 'label' => 'Tüm kategoriler bağlantısı', 'type' => 'text', 'default' => 'Tüm ürün kategorileri' ),
					),
				),
			),
		),

		/*
		 * Teklif sepeti (/teklif-sepeti/, inc/basket.php). Siparis ve odeme
		 * yok: ziyaretci urunleri toplayip tek seferde fiyat ister (e-posta ile
		 * "Teklif Talepleri"ne ya da WhatsApp'tan). Sayfa arama motorlarina
		 * kapali ve site haritasinda yok.
		 */
		'teklif' => array(
			'label'      => 'Teklif Sepeti',
			'path'       => '/teklif-sepeti/',
			// Gizli: sekme listesinde yok, "Sayfa bul" ile acilir; SEO listelerine (llms.txt) girmez.
			'hidden'     => true,
			'components' => array(
				'add' => array(
					'label'  => 'Ürün Sayfasında: Sepete Ekleme',
					'fields' => array(
						'qty_label'  => array( 'label' => 'Adet etiketi', 'type' => 'text', 'default' => 'Adet' ),
						'qty_less'   => array( 'label' => 'Adet azalt düğmesi (ekran okuyucu için)', 'type' => 'text', 'default' => 'Adedi azalt' ),
						'qty_more'   => array( 'label' => 'Adet artır düğmesi (ekran okuyucu için)', 'type' => 'text', 'default' => 'Adedi artır' ),
						'button'     => array( 'label' => 'Ekle butonu', 'type' => 'text', 'default' => 'Teklif sepetine ekle' ),
						'hint'       => array( 'label' => 'Butonun altındaki not', 'type' => 'text', 'default' => 'Birden çok ürünü sepete ekleyip tek talepte fiyat isteyebilirsiniz.' ),
						'added'      => array( 'label' => 'Eklenince — {adet} bu ürünün sepetteki adedi olur', 'type' => 'text', 'default' => 'Teklif sepetine eklendi: bu üründen {adet} adet.', 'hint' => 'Üst menüdeki rakam ürün çeşidi sayısıdır; buradaki adet yalnızca bu ürünün.' ),
						'go'         => array( 'label' => 'Eklenince: sepete git bağlantısı', 'type' => 'text', 'default' => 'Sepete git' ),
						'full'       => array( 'label' => 'Sepet doluysa — {sayi} en fazla ürün sayısı', 'type' => 'text', 'default' => 'Teklif sepeti dolu: en fazla {sayi} ürün eklenebilir. Önce sepettekiler için teklif isteyin ya da bazı ürünleri çıkarın.' ),
						'no_cookie'  => array( 'label' => 'Eklenemediyse (çerez kapalı)', 'type' => 'text', 'default' => 'Ürün sepete eklenemedi: tarayıcınız çerezleri engelliyor olabilir. Çerezlere izin verin ya da bu sayfadaki teklif formunu kullanın.' ),
					),
				),
				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'crumb' => array( 'label' => 'Yol göstergesindeki adı', 'type' => 'text', 'default' => 'Teklif sepeti' ),
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Teklif sepeti' ),
						'lead'  => array( 'label' => 'Alt metin', 'type' => 'textarea', 'default' => 'Seçtiğiniz ürünler için tek seferde fiyat isteyin. Bu bir sipariş değildir; ödeme alınmaz.' ),
					),
				),
				'list' => array(
					'label'  => 'Ürün Listesi',
					'fields' => array(
						'count'        => array( 'label' => 'Ürün sayısı — {sayi}', 'type' => 'text', 'default' => 'Sepette {sayi} ürün' ),
						'price_quote'  => array( 'label' => 'Fiyatı olmayan üründe', 'type' => 'text', 'default' => 'Fiyat teklifle' ),
						'remove'       => array( 'label' => 'Satırı çıkar butonu', 'type' => 'text', 'default' => 'Çıkar' ),
						'update'       => array( 'label' => 'Adetleri kaydet butonu (betik kapalıyken görünür)', 'type' => 'text', 'default' => 'Adetleri güncelle' ),
						'clear'        => array( 'label' => 'Sepeti temizle butonu', 'type' => 'text', 'default' => 'Sepeti temizle' ),
						'clear_ask'    => array( 'label' => 'Sepeti temizle: onay sorusu', 'type' => 'text', 'default' => 'Sepetteki bütün ürünler çıkarılsın mı?' ),
						'more'         => array( 'label' => 'Ürün eklemeye devam bağlantısı', 'type' => 'text', 'default' => 'Ürün eklemeye devam edin' ),
						'removed'      => array( 'label' => 'Sitede olmayan ürün çıkarıldıysa — {sayi}', 'type' => 'text', 'default' => '{sayi} ürün artık sitede olmadığı için sepetten çıkarıldı.' ),
						'empty_title'  => array( 'label' => 'Sepet boşsa başlık', 'type' => 'text', 'default' => 'Teklif sepetiniz boş' ),
						'empty_text'   => array( 'label' => 'Sepet boşsa metin', 'type' => 'textarea', 'default' => 'Ürün sayfalarındaki “Teklif sepetine ekle” butonuyla ürün ekleyin; sonra hepsi için tek seferde fiyat isteyin.' ),
						'empty_cta'    => array( 'label' => 'Sepet boşsa buton', 'type' => 'text', 'default' => 'Ürün kategorilerine git' ),
					),
				),
				'send' => array(
					'label'  => 'Teklif İsteme (E-posta ve WhatsApp)',
					'fields' => array(
						'title'         => array( 'label' => 'Bölüm başlığı', 'type' => 'text', 'default' => 'Teklif isteyin' ),
						'wa_title'      => array( 'label' => 'WhatsApp: başlık', 'type' => 'text', 'default' => 'WhatsApp ile gönderin' ),
						'wa_text'       => array( 'label' => 'WhatsApp: açıklama', 'type' => 'textarea', 'default' => 'Ürün listesi hazır bir mesaj olarak açılır; göndermeden önce ölçü ve teslim yerini ekleyebilirsiniz.' ),
						'wa_button'     => array( 'label' => 'WhatsApp: buton', 'type' => 'text', 'default' => 'WhatsApp’tan gönder' ),
						'wa_intro'      => array( 'label' => 'WhatsApp: mesajın ilk satırı', 'type' => 'textarea', 'default' => 'Merhaba, Koçist web sitesinden şu ürünler için fiyat teklifi istiyorum:' ),
						'wa_line'       => array( 'label' => 'WhatsApp ve e-posta: ürün satırı — {kod}, {urun}, {adet}', 'type' => 'text', 'default' => '{kod} – {urun} – {adet} adet', 'hint' => 'Kodu olmayan üründe {kod} kendiliğinden çıkar. Ürün sayfasının adresi, sığdığı sürece satırın altına eklenir.' ),
						'wa_more'       => array( 'label' => 'WhatsApp: liste uzunsa son satır — {sayi}', 'type' => 'text', 'default' => '+{sayi} ürün daha' ),
						'mail_title'    => array( 'label' => 'E-posta: başlık', 'type' => 'text', 'default' => 'E-posta ile gönderin' ),
						'mail_text'     => array( 'label' => 'E-posta: açıklama', 'type' => 'textarea', 'default' => 'Bilgilerinizi yazın; yazılı teklifimizi e-posta ya da telefonla iletelim.' ),
						'contact_hint'  => array( 'label' => 'E-posta: telefon ve e-posta notu', 'type' => 'text', 'default' => 'Telefon ya da e-postadan biri yeterli.' ),
						'company_label' => array( 'label' => 'E-posta: firma etiketi', 'type' => 'text', 'default' => 'Firma (isteğe bağlı)' ),
						'note_label'    => array( 'label' => 'E-posta: not etiketi', 'type' => 'text', 'default' => 'Not (isteğe bağlı)' ),
						'note_ph'       => array( 'label' => 'E-posta: not ipucu', 'type' => 'text', 'default' => 'Ölçü, teslim yeri, istenen tarih gibi ayrıntılar' ),
						'submit'        => array( 'label' => 'E-posta: gönder butonu', 'type' => 'text', 'default' => 'Teklif talebini gönder' ),
						'success_title' => array( 'label' => 'Gönderilince: başlık', 'type' => 'text', 'default' => 'Talebiniz alındı' ),
						'success_text'  => array( 'label' => 'Gönderilince: metin', 'type' => 'textarea', 'default' => 'Ürün listeniz bize ulaştı. En kısa sürede yazılı teklifle size dönüş yapacağız.' ),
						'mail_error'    => array( 'label' => 'Bildirim e-postası gidemediyse', 'type' => 'textarea', 'default' => 'Talebiniz kaydedildi ama bize bildirim e-postası gönderilemedi. Gecikmemesi için ürün listesini WhatsApp’tan da gönderin ya da bizi arayın.' ),
						'save_error'    => array( 'label' => 'Talep kaydedilemediyse', 'type' => 'textarea', 'default' => 'Talebiniz kaydedilemedi. Lütfen tekrar deneyin ya da ürün listesini WhatsApp’tan gönderin.' ),
						'empty_error'   => array( 'label' => 'Sepet boşken gönderilirse', 'type' => 'text', 'default' => 'Teklif sepetiniz boş: önce ürün ekleyin.' ),
					),
				),
			),
		),

		/*
		 * Bulunamayan sayfa (404). Onizleme adresi bilerek olmayan bir sayfa.
		 */
		'notfound' => array(
			'label'      => '404 Sayfası',
			'path'       => '/bulunamadi-404/',
			// Gizli: sekme listesinde yok, "Sayfa bul" ile acilir; SEO listelerine (llms.txt) girmez.
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Metinleri',
					'fields' => array(
						'crumb'  => array( 'label' => 'Üst Etiket', 'type' => 'text', 'default' => '404' ),
						'title'  => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sayfa bulunamadı' ),
						'text'   => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Aradığınız sayfa taşınmış ya da hiç var olmamış olabilir.' ),
						'button' => array( 'label' => 'Buton Metni', 'type' => 'text', 'default' => 'Ana sayfaya dön' ),
					),
				),
			),
		),
	) + $kocist_category_pages + $kocist_blog_pages,
);

// Panel ipuclari: ayni bilginin sitede yazili oldugu diger yerler, baglanti yazimi.
$manifest_hints = require __DIR__ . '/content-manifest-hints.php';

return $manifest_hints(
	$manifest_data,
	array(
		'Bu telefon numarası' => array( 'global.topbar.phone_label', 'global.footer.phone_label', 'contact.info.phone_label' ),
		'Bu cep numarası' => array( 'global.topbar.mobile_label', 'global.footer.mobile_label' ),
		'Bu e-posta adresi' => array( 'global.topbar.email_label', 'global.footer.email_label', 'contact.info.email_label', 'hr.apply.email' ),
		'Bu adres' => array( 'global.footer.address', 'contact.info.address' ),
		'Çalışma saatleri' => array( 'global.topbar.hours', 'contact.info.hours' ),
		'Bu WhatsApp bağlantısı' => array( 'contact.info.whatsapp_url', 'banka.empty.wa_url' ),
	)
);
