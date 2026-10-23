<?php
/**
 * Istanbul Keresteci temasi alan manifesti.
 *
 * Saf dizi dondurur; WordPress fonksiyonlarina bagimli degildir. Ag paneli bu
 * dosyayi siteye gecmeden dosya sisteminden okur.
 *
 * Urunler tek kaynaktan gelir: products.catalog.items. Acilir menu, ana sayfa
 * istifi, /urunlerimiz/ listesi ve urun detay sayfalari hep bu satirlari okur.
 * Urun metinleri musteriden geldi (refs/istabul-keresteci/ürünler.md).
 *
 * Alan turleri: text, textarea, url, image, icon, repeater
 *
 * Her urunun ('urun-<slug>') ve ornek blog yazisinin ('yazi-<slug>') panelde
 * gizli bir sayfasi vardir: "Sayfa bul" kutusundan acilir.
 */

$phone_label    = '+90 212 648 1090';
$phone_url      = 'tel:+902126481090';
$whatsapp_label = '+90 532 374 98 32';
$whatsapp_url   = 'https://wa.me/905323749832';
$email          = 'info@kocist.com.tr';
$address        = "Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1\nÇatalca, İstanbul";

/*
 * Varsayilan urunler. Hem Urun Listesi'nin varsayilan satirlari hem de her
 * urun icin paneldeki gizli sayfa (asagida) bu listeden uretilir.
 */
$ik_products = array(
	array(
		'slug'  => 'kereste',
		'title' => 'Kereste',
		'short' => 'Yumuşak ve sert kereste olarak ikiye ayrılan, inşaattan mobilyaya pek çok alanda kullanılan ürünler.',
		'body'  => "Yumuşak ve sert kereste olarak ikiye ayrılan keresteler, pek çok alanda kullanıma uygun ürünlerdir. İnşaat, ambalaj, mobilya, dekorasyon alanlarında sıklıkla kullanılan keresteler birçok ağaç çeşidinden imal edilebilir.\n\nTomrukların boyuna biçilmesi ile oluşturulan keresteler, birbirine paralel gelen iki yüzeye sahip ağaç parçalarından oluşmaktadır.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'ahsap-palet',
		'title' => 'Ahşap Palet',
		'short' => 'Hacmi yüksek ürünleri taşımak ve korumak için, standartlara uygun ve uzun ömürlü paletler.',
		'body'  => "Hacmi yüksek ürünleri taşımak ve muhafaza etmek amacıyla kullanılan, standartlara uygun üretime sahip ahşap paletler uzun süreli kullanıma uygun ürünlerdir.\n\nTaşımacılık sektöründe, ürünlerin muhafaza edilme süreçlerinde, ürünün temiz, kuru ve zarar görmeden saklanması amacıyla ahşap palet kullanımı uygundur. Ahşap paletler uzun süreli kullanılabilir, verimli ve sürdürülebilir ürünlerdir.\n\nStandart boyutların yanında özel üretim ahşap paletler de imal edilmektedir.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'ahsap-sandik',
		'title' => 'Ahşap Sandık',
		'short' => 'Yüksek hacimli ürünlerin taşınması, korunması ve depolanması için verimli bir çözüm.',
		'body'  => "Hacim yüksekliği fazla olan ürün veya malzemelerin taşımacılığında, muhafaza ve depolama süreçlerinde kullanıma uygun olan ahşap sandıklar iş alanında oldukça verimli kullanım imkanları sunmaktadır.\n\nZaman, maliyet ve mekân anlamında tasarruf imkanı sunarak düşük depolama maliyetleri sunmaktadır. Uzun süreli kullanım imkanının bulunmasından ötürü ahşap sandıklar, tamir edilebilir, dönüştürülebilir ve geri dönüşümü sağlanabilir.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'ahsap-kafes',
		'title' => 'Ahşap Kafes',
		'short' => 'Taşıma ve depolamayı birlikte çözen, paletin yetmediği yerde kullanılan kafesler.',
		'body'  => "Aynı anda taşınma ve depolanma imkânı sunan ahşap kafesler iş yükünü azaltma konusu başta olmak üzere bir çok konuda pratik çözümler sunmaktadır. Ham madde tedarik eden firmalar ve ihracat yapan firmalar başta olmak üzere sevkiyat işlemlerinde kullanıma uygun ürünlerdir.\n\nPaletin uygun olmadığı durumlarda genellikle ahşap kafesler kullanılmaktadır. Transpalet ve forklift ile taşımaya uygun olan kafeslerin çevresi belli aralıklarla çivi ve keresteler ile desteklenmiştir.\n\nStandart ölçüleri dışında istenilen boyutta da üretimi gerçekleştirilebilir. İstiflenmesi zor ürünlerin yerleştirilmesi için uygundur.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'tomruk',
		'title' => 'Tomruk',
		'short' => 'Kayın, kızılçam, meşe ve karaçam ağırlıklı; kereste ve palet üretiminin ham maddesi.',
		'body'  => "Kayın, kızılçam, meşe ve karaçam ağaçlarından üretimi ağırlıklı olmak üzere birçok farklı çeşitte tomruk imal edilebilir.\n\nOrman ürünleri arasında en değerli ürün olan tomruk; inşaatlık kereste ve palet imal edilirken kullanılır. Ayrıca ağaç ev yapmak isteyenler de tomruk tercih etmektedirler. Tomrukların kesim işlemine göre işlevselliği farklılıklar gösterir ve bu sayede kereste oluşturulur.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'osb-plaka',
		'title' => 'OSB Plaka',
		'short' => 'Yongaların tutkalla preslenmesiyle üretilen, çatı ve döşemede kullanılan plaka.',
		'body'  => "Ahşap parçalarının boyuna olacak şekilde rendelenmesiyle birlikte çıkan yongalar yapıştırıcı ve tutkallar ile birlikte bir araya getirilmektedir. Kimyasalların, sıcaklık ve basıncın da etkisiyle birleşen ufak parçalar OSB plakayı oluşturmaktadır.\n\nKolay uygulanabilir yapısı bulunmaktadır. Zımpara işlemi gerektirmeyen doğal bir yapıya sahiptir. Boyut olarak çeşitli boyutlarda veya istenilen ebatlarda üretimi gerçekleştirilebilir.\n\nEn fazla kullanıldığı alan ise çatı kaplamaları olmakla birlikte; dekor, döşeme, duvar ve panolarda kullanıma uygun parçalardandır.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'kontrplak',
		'title' => 'Kontrplak',
		'short' => 'Dik katmanlardan preslenen hafif, çatlamaya dayanıklı ve kolay işlenen levha.',
		'body'  => "Kayın, kavak, huş ve egzotik çam ağaçlarının gövde kısımlarından imal edilerek özel makineler eşliğinde elde edilen levhalar; dik gelecek şekilde özel bir yapıştırıcı ile yapıştırılır. Ardından preslenerek levha haline getirilir.\n\nİstenilen boyut ve ebatlarda üretimi gerçekleştirilen kontrplaklar inşaat, dekorasyon, mobilya, zemin, parke, çatı, tekne inşaatı, otomotiv, iskele ve oyuncak gibi birçok alanda kullanıma uygun ürünlerdir.\n\nHafif olması sebebiyle tercih edilme oranı çok daha yüksektir. Çatlamaya karşı direncinin olması sebebiyle uzun süreli kullanıma sahiptir. Boya tutan üst yüzeyi cilaya uygundur. Suya karşı dayanıklıdır, kolaylıkla işlenir ve vida tutar.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'kalas',
		'title' => 'Kalas',
		'short' => 'Kalitesine göre ayrılıp kurutulan, birçok ağaç çeşidinden elde edilen kalaslar.',
		'body'  => "Ağacın ahşap haline getirilmesiyle birlikte kalas olarak kullanıma uygun boyut ve şekillerde işlenmesinin ardından kalas elde edilmektedir. Ağacın kullanım alanı veya ömrü bittiğinde atölyelere götürülen malzeme kalas haline getirilmektedir.\n\nKalitesine göre ayrıştırılan kalaslar kurutularak müşterilerin erişebileceği şekilde satışa sunulmaktadır. Kullanım alanı oldukça geniş olan kalaslar bir çok ağaç çeşidinden elde edilebilirler.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'plywood',
		'title' => 'Plywood',
		'short' => 'Çapraz tabakalardan preslenen, hafif, dayanıklı ve düz yüzeyli levha.',
		'body'  => "Ahşap ince ağaç tabakalarının çapraz dik açıyla özel yapıştırıcılar eşliğinde yapıştırılarak preslenmesiyle tabaka haline getirilme işlemiyle elde edilen ahşaba plywood adı verilmektedir.\n\nDayanıklılığı oldukça yüksek olan plywoodların kullanım alanları oldukça geniştir. Tekrar tekrar kullanılarak işleme alınabilen çevreci ürünlerdir. İstenilen ölçü ve ebata getirilerek kullanıma sunulabilirler.\n\nHafif ve dayanıklı ahşap türlerindendir. Düz yüzeyli olduğu için geniş uygulama alanı sunar.",
		'specs' => '',
		'image' => 0,
	),
	array(
		'slug'  => 'cita',
		'title' => 'Çıta',
		'short' => 'Çoğunlukla çamdan, silinmiş ve zımparalanmış; standart ve özel ölçüde çıta.',
		'body'  => "Genellikle çam ağacından üretilen çıtalar silinmiş ve zımparalanmış şekliyle tüketiciye sunulabilir.\n\nStandart ölçülerinin yanı sıra farklı boyutlarda özel çıta üretimi imal edilebilir. Çıtalar günümüzde dekoratif amaçlı olarak sıklıkla kullanılmaktadır.",
		'specs' => '',
		'image' => 0,
	),
);

/*
 * Her urun icin panelde gizli bir sayfa: sekme listesinde gorunmez, "Sayfa
 * bul" kutusunda cikar ve onizlemede urun sayfasini acar. Urunun metinleri
 * Urunlerimiz > Urun Listesi'ndedir; burada yalnizca sayfaya ozel alt metin
 * ve eklentinin ekledigi arama ve paylasim alanlari var.
 *
 * Panelden sonradan eklenen urun burada cikmaz (manifest sabit); sayfasi yine
 * acilir (functions.php: ik_product_fallback).
 */
$ik_product_pages = array();

foreach ( $ik_products as $ik_row ) {
	$ik_product_pages[ 'urun-' . $ik_row['slug'] ] = array(
		'label'      => 'Ürün: ' . $ik_row['title'],
		'path'       => '/urunlerimiz/' . $ik_row['slug'] . '/',
		'hidden'     => true,
		'components' => array(
			'head' => array(
				'label'  => 'Sayfa Başlığı',
				'fields' => array(
					'lead' => array(
						'label'   => 'Alt Metin',
						'type'    => 'textarea',
						'default' => '',
						'hint'    => 'Boş bırakılırsa Ürün Listesi’ndeki kısa açıklama görünür. Ürün adı, metni, özellikleri ve fotoğrafı Ürünlerimiz → Ürün Listesi’nden düzenlenir.',
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
 * yerine gecer (functions.php: Blog yazilari). Degistirilmeyen alan icin
 * WordPress'teki yazi kullanilir.
 *
 * Yonetimden sonradan yazilan yazi burada cikmaz (manifest sabit); o yazi
 * Yazilar ekranindan duzenlenir.
 */
$ik_blog_pages = array();

foreach ( (array) include __DIR__ . '/content/blog-posts.php' as $ik_post ) {
	$ik_blog_pages[ 'yazi-' . $ik_post['slug'] ] = array(
		'label'      => 'Yazı: ' . $ik_post['title'],
		'path'       => '/' . $ik_post['slug'] . '/',
		'hidden'     => true,
		'components' => array(
			'post' => array(
				'label'  => 'Yazı',
				'fields' => array(
					'title'   => array( 'label' => 'Başlık', 'type' => 'text', 'default' => $ik_post['title'] ),
					'excerpt' => array( 'label' => 'Özet (kart ve arama sonucu)', 'type' => 'textarea', 'default' => $ik_post['excerpt'] ),
					'body'    => array(
						'label'   => 'Metin (boş satırla paragraf)',
						'type'    => 'textarea',
						'default' => $ik_post['body'],
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

$manifest_data = array(
	'site_key'   => 'istanbul-keresteci',
	'site_label' => 'İstanbul Keresteci',
	// SEO ve GEO firma bilgisi (Network Content Studio). Degerler bu sitenin
	// kendi sayfalarinda yazanlardir; panelin SEO ve GEO sekmesinden duzeltilir.
	'seo_site_defaults' => array(
		'name'        => 'İstanbul Keresteci',
		'legal_name'  => 'Koçist Grup Dış Ticaret ve Sanayi Ltd. Şti.',
		'description' => 'İstanbul Çatalca’da çam, köknar, kayın ve meşe kereste; tomruk, OSB, kontrplak ve plywood plaka. İstenen ölçüde kesip adrese teslim.',
		'phone'       => '+90 212 648 10 90',
		'email'       => 'info@kocist.com.tr',
		'street'      => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1',
		'district'    => 'Çatalca',
		'city'        => 'İstanbul',
		'postal_code' => '',
		'country'     => 'TR',
		'parent_name' => 'Koçist Orman Ürünleri',
		'parent_url'  => 'https://kocist.com.tr',
	),
	'pages'      => array(

		'global' => array(
			'label'      => 'Tüm Sayfalar (Menü ve Sayfa Altı)',
			'path'       => '/',
			'components' => array(

				'topbar' => array(
					'label'  => 'Üst Bilgi Şeridi',
					'fields' => array(
						'address'       => array( 'label' => 'Adres (tek satır)', 'type' => 'text', 'default' => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul' ),
						'address_url'   => array( 'label' => 'Adres Bağlantısı', 'type' => 'url', 'default' => '/iletisim/' ),
						'email_label'   => array( 'label' => 'E-posta Metni', 'type' => 'text', 'default' => $email ),
						'email_url'     => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:' . $email ),
						// Sosyal aglar: bos birakilan agin ikonu gorunmez; hepsi bossa alan gizlenir.
						'instagram_url' => array( 'label' => 'Instagram Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
						'facebook_url'  => array( 'label' => 'Facebook Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
						'linkedin_url'  => array( 'label' => 'LinkedIn Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
						'youtube_url'   => array( 'label' => 'YouTube Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
						'x_url'         => array( 'label' => 'X (Twitter) Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
						'tiktok_url'    => array( 'label' => 'TikTok Bağlantısı (boşsa gizli)', 'type' => 'url', 'default' => '' ),
					),
				),

				'header' => array(
					'label'  => 'Üst Menü',
					'fields' => array(
						'logo_image'  => array( 'label' => 'Logo Görseli (boşsa yazı logo)', 'type' => 'image', 'default' => 0 ),
						'logo_top'    => array( 'label' => 'Logo Üst Satır', 'type' => 'text', 'default' => 'İstanbul' ),
						'logo_bottom' => array( 'label' => 'Logo Alt Satır', 'type' => 'text', 'default' => 'Keresteci' ),
						'menu'        => array(
							'label'   => 'Menü Öğeleri',
							'type'    => 'repeater',
							'max'     => 7,
							'fields'  => array(
								'label'   => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'     => array( 'label' => 'Bağlantı', 'type' => 'url' ),
								'submenu' => array( 'label' => 'Açılır Liste ("urunler" yazılırsa ürünler açılır)', 'type' => 'text' ),
							),
							'default' => array(
								array( 'label' => 'Anasayfa', 'url' => '/', 'submenu' => '' ),
								array( 'label' => 'Hakkımızda', 'url' => '/hakkimizda/', 'submenu' => '' ),
								array( 'label' => 'Ürünlerimiz', 'url' => '/urunlerimiz/', 'submenu' => 'urunler' ),
								array( 'label' => 'Blog', 'url' => '/blog/', 'submenu' => '' ),
								array( 'label' => 'SSS', 'url' => '/sss/', 'submenu' => '' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/', 'submenu' => '' ),
							),
						),
						'phone_note'  => array( 'label' => 'Telefon Üst Yazısı', 'type' => 'text', 'default' => 'Fiyat ve stok için arayın' ),
						'phone_label' => array( 'label' => 'Telefon Metni', 'type' => 'text', 'default' => $phone_label ),
						'phone_url'   => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
						'all_label'   => array( 'label' => 'Açılır Menü: Tümü Bağlantısı', 'type' => 'text', 'default' => 'Bütün ürünleri görün' ),
					),
				),

				/*
				 * Urunlerimiz acilir listesi. Bos birakilirsa urun listesinden
				 * kendiliginden dolar; satir girilirse yalnizca girilenler gorunur.
				 * Menu satirinin "Açılır Liste" alani "urunler" oldugunda kullanilir.
				 */
				'menu_urunler' => array(
					'label'  => 'Açılır Menü: Ürünlerimiz',
					'fields' => array(
						'items' => array(
							'label'   => 'Alt Menü Öğeleri (boşsa ürün listesinden)',
							'type'    => 'repeater',
							'max'     => 24,
							'fields'  => array(
								'label' => array( 'label' => 'Menü Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(),
						),
					),
				),

				'footer' => array(
					'label'  => 'Sayfa Altı (Footer)',
					'fields' => array(
						'about_text'     => array( 'label' => 'Tanıtım Metni', 'type' => 'textarea', 'default' => 'Kereste, tomruk, plaka ve ahşap ambalaj. Çatalca’daki depomuzdan satış ve sevkiyat yapıyoruz. Koçist Grup kuruluşudur.' ),
						'products_title' => array( 'label' => 'Ürünler Başlığı', 'type' => 'text', 'default' => 'Ürünlerimiz' ),
						'pages_title'    => array( 'label' => 'Sayfalar Başlığı', 'type' => 'text', 'default' => 'Kurumsal' ),
						'links'          => array(
							'label'   => 'Sayfa Bağlantıları',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Hakkımızda', 'url' => '/hakkimizda/' ),
								array( 'label' => 'Ürünlerimiz', 'url' => '/urunlerimiz/' ),
								array( 'label' => 'Blog', 'url' => '/blog/' ),
								array( 'label' => 'Sık sorulan sorular', 'url' => '/sss/' ),
								array( 'label' => 'İletişim', 'url' => '/iletisim/' ),
							),
						),
						'contact_title'  => array( 'label' => 'İletişim Başlığı', 'type' => 'text', 'default' => 'Depo ve satış' ),
						'address'        => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => $address ),
						'phone_label'    => array( 'label' => 'Telefon Metni', 'type' => 'text', 'default' => $phone_label ),
						'phone_url'      => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Metni', 'type' => 'text', 'default' => $whatsapp_label ),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => $whatsapp_url ),
						'email_label'    => array( 'label' => 'E-posta Metni', 'type' => 'text', 'default' => $email ),
						'email_url'      => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:' . $email ),
						'copyright'      => array( 'label' => 'Telif Satırı', 'type' => 'text', 'default' => '© Koçist Grup Dış Ticaret ve Sanayi Ltd. Şti.' ),
					),
				),
			),
		),

		'home' => array(
			'label'             => 'Ana Sayfa',
			'path'              => '/',
			'sortable_sections' => array( 'about', 'products', 'process', 'blog', 'ctaband' ),
			'components'        => array(

				/*
				 * Arama ve Paylasim: eklentinin bileseniyle ayni alanlar, yalnizca
				 * ana sayfanin ilk degerleri dolu. Bos birakilinca baslik site
				 * adina ("Istanbul Keresteci") dusuyordu; aramada ticari kelime
				 * tasimiyordu. Panelden degistirilen deger bunun yerine gecer.
				 * Eklenti temanin kendi 'seo' bilesenine dokunmaz
				 * (nwcs_seo_augment_manifest).
				 */
				'seo' => array(
					'label'  => 'Arama ve Paylaşım',
					'fields' => array(
						'title'       => array(
							'label'   => 'Arama başlığı',
							'type'    => 'text',
							'default' => 'İstanbul Keresteci | Çatalca Kereste Satışı ve Fiyatları',
							'count'   => 60,
							'hint'    => 'Google sonucunda mavi görünen başlık. Boş bırakırsanız sayfa başlığı ve site adı kullanılır.',
						),
						'description' => array(
							'label'   => 'Arama açıklaması',
							'type'    => 'textarea',
							'default' => 'Çatalca’daki depomuzdan çam, köknar, kayın ve meşe kereste; tomruk, OSB, kontrplak ve plywood. İstediğiniz ölçüde kesip İstanbul’a teslim ediyoruz.',
							'count'   => 160,
							'hint'    => 'Başlığın altındaki özet. Boş bırakırsanız sayfanın öne çıkan cümlesi kullanılır.',
						),
						'image'       => array(
							'label'   => 'Paylaşım görseli',
							'type'    => 'image',
							'default' => 0,
							'hint'    => 'Bağlantı WhatsApp, LinkedIn gibi yerlerde paylaşılınca görünen görsel. Boşsa sayfanın ana görseli kullanılır.',
						),
					),
				),

				'hero' => array(
					'label'  => 'Giriş (Hero)',
					'fields' => array(
						'image'           => array( 'label' => 'Arka Plan Fotoğrafı', 'type' => 'image', 'default' => 0 ),
						'title'           => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İstanbul Keresteci' ),
						'subtitle'        => array( 'label' => 'Alt Başlık', 'type' => 'text', 'default' => 'Kereste, tomruk ve ahşap ambalaj' ),
						'text'            => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Çam, köknar, kayın ve meşe kereste; OSB, kontrplak ve plywood plaka. İstediğiniz ölçüde kesip adresinize getiriyoruz.' ),
						'primary_label'   => array( 'label' => 'Birinci Düğme Metni', 'type' => 'text', 'default' => 'Ürünleri inceleyin' ),
						'primary_url'     => array( 'label' => 'Birinci Düğme Bağlantısı', 'type' => 'url', 'default' => '/urunlerimiz/' ),
						'secondary_label' => array( 'label' => 'İkinci Düğme Metni', 'type' => 'text', 'default' => $phone_label ),
						'secondary_url'   => array( 'label' => 'İkinci Düğme Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
					),
				),

				'about' => array(
					'label'  => 'Biz Kimiz',
					'fields' => array(
						'heading'     => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Şantiyeye, atölyeye, fabrikaya kereste' ),
						'company'     => array( 'label' => 'Ticari Unvan (küçük yazı)', 'type' => 'text', 'default' => 'Koçist Grup Dış Tic. ve San. Ltd. Şti.' ),
						'text'        => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => "Kereste ve kereste alanındaki bütün ürünlerde elli yıllık tecrübemizle hizmet veriyoruz.\n\nİstanbul’daki depomuzdan mobilya, dekorasyon, doğramalık, inşaatlık, kuyuluk ve kubbelik kereste satıyoruz. Ürün yelpazemizi her yıl genişletiyor, müşterimizin istediği ölçüyü ve kaliteyi zamanında teslim etmeyi esas alıyoruz." ),
						'image'       => array( 'label' => 'Fotoğraf', 'type' => 'image', 'default' => 0 ),
						'link_label'  => array( 'label' => 'Bağlantı Metni', 'type' => 'text', 'default' => 'Hakkımızda daha fazlası' ),
						'link_url'    => array( 'label' => 'Bağlantı', 'type' => 'url', 'default' => '/hakkimizda/' ),
					),
				),

				'products' => array(
					'label'  => 'Ürün İstifi',
					'fields' => array(
						'title'     => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Depoda ne var' ),
						'text'      => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'On ürün grubu, hepsi stoktan. Ürüne tıklayın; ölçüleri ve kullanım alanlarını görün.' ),
						'all_label' => array( 'label' => 'Tümü Bağlantısı', 'type' => 'text', 'default' => 'Bütün ürünler' ),
						'all_url'   => array( 'label' => 'Tümü Bağlantısı Adresi', 'type' => 'url', 'default' => '/urunlerimiz/' ),
					),
				),

				'process' => array(
					'label'  => 'Sipariş Süreci',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Üç adımda sipariş' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Ürünü seçin, ölçüyü söyleyin, gerisini biz halledelim.' ),
						'steps' => array(
							'label'   => 'Adımlar',
							'type'    => 'repeater',
							'max'     => 4,
							'fields'  => array(
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Ürününüzü seçin', 'text' => 'Satın alacağınız ürün çeşidine karar verin: kereste, ahşap palet, kafes veya sandık.' ),
								array( 'title' => 'Sipariş verin', 'text' => 'İletişim formu ya da telefonla adet ve ölçü bilgisini bize iletin.' ),
								array( 'title' => 'Teslimat', 'text' => 'Ürünlerinizi en kısa sürede adresinize teslim edelim ya da gönderdiğiniz araca yükleyelim.' ),
							),
						),
						'cta_label' => array( 'label' => 'Düğme Metni', 'type' => 'text', 'default' => 'Sipariş formunu açın' ),
						'cta_url'   => array( 'label' => 'Düğme Bağlantısı', 'type' => 'url', 'default' => '/iletisim/#form' ),
					),
				),

				'blog' => array(
					'label'  => 'Blog Yazıları',
					'fields' => array(
						'title'     => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Keresteye dair' ),
						'all_label' => array( 'label' => 'Tümü Bağlantısı', 'type' => 'text', 'default' => 'Bütün yazılar' ),
						'all_url'   => array( 'label' => 'Tümü Bağlantısı Adresi', 'type' => 'url', 'default' => '/blog/' ),
					),
				),

				'ctaband' => array(
					'label'  => 'Fiyat Şeridi',
					'fields' => array(
						'title'           => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Güncel fiyatı telefonda söyleyelim' ),
						'text'            => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Kereste fiyatı ağaç türüne, ölçüye ve miktara göre değişir. Arayın ya da yazın; stok durumunu ve fiyatı bildirelim.' ),
						'primary_label'   => array( 'label' => 'Birinci Düğme Metni', 'type' => 'text', 'default' => $phone_label ),
						'primary_url'     => array( 'label' => 'Birinci Düğme Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
						'secondary_label' => array( 'label' => 'WhatsApp Düğmesi Metni', 'type' => 'text', 'default' => 'WhatsApp’tan yazın' ),
						'secondary_url'   => array( 'label' => 'WhatsApp Düğmesi Bağlantısı', 'type' => 'url', 'default' => $whatsapp_url ),
					),
				),
			),
		),

		'about' => array(
			'label'      => 'Hakkımızda',
			'path'       => '/hakkimizda/',
			'components' => array(
				'head'   => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Hakkımızda' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Elli yıldır kereste işindeyiz. Çatalca’daki depomuzdan İstanbul’un şantiyelerine, atölyelerine ve fabrikalarına ahşap taşıyoruz.' ),
						'image' => array( 'label' => 'Fotoğraf', 'type' => 'image', 'default' => 0 ),
					),
				),
				'story'  => array(
					'label'  => 'Hikâye',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Koçist Grup’un kereste kolu' ),
						'text'  => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => "Sahip olduğumuz bilgi birikimini ve teknik imkânlarımızı her gün ileri taşıyarak kereste ve kereste alanındaki bütün ürünlerde hizmet veriyoruz.\n\nİstanbul’daki firmamız mobilya, dekorasyon, doğramalık, inşaatlık, kuyuluk ve kubbelik kereste satışı yapıyor. Aynı çatı altında ahşap palet, sandık ve kafes de üretiyoruz.\n\nMüşteri memnuniyetini temel ilke edinerek, her yıl genişleyen ürün seçeneklerimizle sektörde emin adımlarla ilerliyoruz." ),
					),
				),
				'values' => array(
					'label'  => 'Çalışma Biçimimiz',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Nasıl çalışıyoruz' ),
						'items' => array(
							'label'   => 'Maddeler',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'icon'  => array( 'label' => 'İkon', 'type' => 'icon' ),
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'icon' => 'tree', 'title' => 'Stoktan satış', 'text' => 'Çam, köknar, kayın, meşe ve plakalar depoda hazır bekler.' ),
								array( 'icon' => 'ruler', 'title' => 'Ölçüye kesim', 'text' => 'Keresteyi ve plakayı istediğiniz boyda ve kalınlıkta kesiyoruz.' ),
								array( 'icon' => 'truck', 'title' => 'Adrese teslim', 'text' => 'Şantiyenize ya da atölyenize getiriyor, aracınıza yüklüyoruz.' ),
							),
						),
					),
				),
			),
		),

		'products' => array(
			'label'      => 'Ürünlerimiz',
			'path'       => '/urunlerimiz/',
			'components' => array(
				'head'    => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Ürünlerimiz' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Kereste ve tomruktan plakaya, paletten sandığa. Hepsi Çatalca’daki depomuzda.' ),
					),
				),
				'catalog' => array(
					'label'  => 'Ürün Listesi',
					'fields' => array(
						'items' => array(
							'label'   => 'Ürünler',
							'type'    => 'repeater',
							'max'     => 24,
							'fields'  => array(
								'slug'  => array( 'label' => 'Adres Adı (urunlerimiz/…)', 'type' => 'text' ),
								'title' => array( 'label' => 'Ürün Adı', 'type' => 'text' ),
								'short' => array( 'label' => 'Kısa Açıklama (kart)', 'type' => 'textarea' ),
								'body'  => array( 'label' => 'Detay Metni (boş satırla paragraf)', 'type' => 'textarea' ),
								'specs' => array( 'label' => 'Özellikler (her satır "Ad: değer")', 'type' => 'textarea' ),
								'image' => array( 'label' => 'Fotoğraf', 'type' => 'image' ),
							),
							'default' => $ik_products,
						),
					),
				),
				'detail'  => array(
					'label'  => 'Ürün Detay Sayfası',
					'fields' => array(
						'back_label'     => array( 'label' => 'Geri Bağlantısı', 'type' => 'text', 'default' => 'Ürünlerimiz' ),
						'specs_title'    => array( 'label' => 'Özellikler Başlığı', 'type' => 'text', 'default' => 'Ölçü ve kullanım' ),
						'quote_label'    => array( 'label' => 'Fiyat Düğmesi Metni', 'type' => 'text', 'default' => 'Fiyat isteyin' ),
						'quote_url'      => array( 'label' => 'Fiyat Düğmesi Bağlantısı', 'type' => 'url', 'default' => '/iletisim/#form' ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Düğmesi Metni', 'type' => 'text', 'default' => 'WhatsApp’tan sorun' ),
						'others_title'   => array( 'label' => 'Diğer Ürünler Başlığı', 'type' => 'text', 'default' => 'Depodaki diğer ürünler' ),
					),
				),
				'help'    => array(
					'label'  => 'WhatsApp Yardım Kutusu',
					'fields' => array(
						'image'        => array( 'label' => 'Arka Plan Fotoğrafı', 'type' => 'image', 'default' => 0 ),
						'title'        => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Yardım mı lazım?' ),
						'text'         => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'WhatsApp destek hattımız üzerinden bize yazabilirsiniz.' ),
						'number_label' => array( 'label' => 'Numara Metni', 'type' => 'text', 'default' => $whatsapp_label ),
						'number_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => $whatsapp_url ),
					),
				),
			),
		),

		'contact' => array(
			'label'      => 'İletişim',
			'path'       => '/iletisim/',
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'İletişime Geçin' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Bizimle dilediğiniz zaman iletişime geçebilirsiniz.' ),
					),
				),
				'info' => array(
					'label'  => 'İletişim Bilgileri',
					'fields' => array(
						'address_label'  => array( 'label' => 'Adres Başlığı', 'type' => 'text', 'default' => 'Depo' ),
						'address'        => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => $address ),
						'phone_label'    => array( 'label' => 'Telefon Başlığı', 'type' => 'text', 'default' => 'Telefon' ),
						'phone'          => array( 'label' => 'Telefon', 'type' => 'text', 'default' => $phone_label ),
						'phone_url'      => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => $phone_url ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Başlığı', 'type' => 'text', 'default' => 'WhatsApp' ),
						'whatsapp'       => array( 'label' => 'WhatsApp', 'type' => 'text', 'default' => $whatsapp_label ),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => $whatsapp_url ),
						'email_label'    => array( 'label' => 'E-posta Başlığı', 'type' => 'text', 'default' => 'E-posta' ),
						'email'          => array( 'label' => 'E-posta', 'type' => 'text', 'default' => $email ),
						'email_url'      => array( 'label' => 'E-posta Bağlantısı', 'type' => 'url', 'default' => 'mailto:' . $email ),
					),
				),
				'form' => array(
					'label'  => 'Mesaj Formu',
					'fields' => array(
						'title'        => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sipariş ve fiyat formu' ),
						'text'         => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Ürünü, ölçüyü ve adedi yazın; size dönüş yapalım.' ),
						'label_name'    => array( 'label' => 'Ad Soyad Kutusunun Adı', 'type' => 'text', 'default' => 'Adınız soyadınız' ),
						'label_phone'   => array( 'label' => 'Telefon Kutusunun Adı', 'type' => 'text', 'default' => 'Telefonunuz' ),
						'label_email'   => array( 'label' => 'E-posta Kutusunun Adı', 'type' => 'text', 'default' => 'E-posta' ),
						'email_note'    => array( 'label' => 'E-posta Kutusundaki Küçük Not', 'type' => 'text', 'default' => '(telefon yoksa)', 'hint' => 'Kutunun adının yanında küçük yazıyla görünür.' ),
						'label_product' => array( 'label' => 'Ürün Seçim Kutusunun Adı', 'type' => 'text', 'default' => 'Ürün', 'hint' => 'Seçenekler Ürünlerimiz sayfasındaki ürün listesinden gelir.' ),
						'label_message' => array( 'label' => 'Mesaj Kutusunun Adı', 'type' => 'text', 'default' => 'Ölçü, adet ve teslim yeri' ),
						'button_label' => array( 'label' => 'Düğme Metni', 'type' => 'text', 'default' => 'Mesajı gönder' ),
						'success'      => array( 'label' => 'Gönderildi Mesajı', 'type' => 'textarea', 'default' => 'Mesajınız bize ulaştı. Telefonla ya da e-postayla size dönüş yapacağız.' ),
					),
				),
				'map'  => array(
					'label'  => 'Harita',
					'fields' => array(
						'query'            => array( 'label' => 'Harita Adresi', 'type' => 'text', 'default' => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul' ),
						'directions_label' => array( 'label' => 'Yol Tarifi Düğmesi', 'type' => 'text', 'default' => 'Yol tarifi alın' ),
					),
				),
			),
		),

		/*
		 * Catalca depo sayfasi (/catalca-kereste-deposu/). Depo Catalca'da ama
		 * sitede "Catalca kereste" aramasina cevap veren bir sayfa yoktu. Metinler
		 * yalnizca sitenin mevcut bilgilerinden (adres, urunler, kesim, teslim,
		 * yukleme). Teslim suresi ve ucreti firmadan gelmeden yazilmaz.
		 */
		'depo' => array(
			'label'      => 'Çatalca Kereste Deposu',
			'path'       => '/catalca-kereste-deposu/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.text',
			),
			'components' => array(
				'head'   => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Çatalca Kereste Deposu' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Kestanelik’teki depomuzda çam, köknar, kayın ve meşe kereste; tomruk, OSB, kontrplak ve plywood stokta. İstediğiniz ölçüde kesip İstanbul’a teslim ediyoruz.' ),
					),
				),
				'story'  => array(
					'label'  => 'Depo',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Çatalca’dan İstanbul’a kereste' ),
						'text'  => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => "İstanbul Keresteci’nin deposu Çatalca Kestanelik Mahallesi’nde, Eski Edirne Asfaltı üzerinde. Koçist Grup’un kereste kolu olarak elli yıldır bu işteyiz.\n\nDepoda mobilya, dekorasyon, doğramalık, inşaatlık, kuyuluk ve kubbelik kereste; tomruk, kalas ve çıta; OSB, kontrplak ve plywood plaka bulunur. Aynı çatı altında ahşap palet, sandık ve kafes de üretiyoruz.\n\nKeresteyi ve plakayı istediğiniz boyda ve kalınlıkta kesiyoruz. Ürünü şantiyenize, atölyenize ya da fabrikanıza teslim ediyor ya da depoda aracınıza yüklüyoruz." ),
					),
				),
				'values' => array(
					'label'  => 'Depoda Neler Yapılır',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Depoda neler yapıyoruz' ),
						'items' => array(
							'label'   => 'Maddeler',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'icon'  => array( 'label' => 'İkon', 'type' => 'icon' ),
								'title' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'icon' => 'tree', 'title' => 'Stoktan satış', 'text' => 'Çam, köknar, kayın, meşe kereste ve plakalar depoda hazır.' ),
								array( 'icon' => 'ruler', 'title' => 'Ölçüye kesim', 'text' => 'Kereste ve plaka istediğiniz boy ve kalınlıkta kesilir.' ),
								array( 'icon' => 'truck', 'title' => 'Teslimat ya da yükleme', 'text' => 'İstanbul’un her yerine teslim; dilerseniz depoda aracınıza yükleme.' ),
							),
						),
					),
				),
				'areas'  => array(
					'label'  => 'Teslim Bölgeleri',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Teslim ettiğimiz bölgeler' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Çatalca’daki depomuzdan İstanbul’un her yerine teslim ediyoruz. Depoya yakın ilçeler:' ),
						'items' => array(
							'label'   => 'İlçeler',
							'type'    => 'repeater',
							'max'     => 40,
							'fields'  => array(
								'name' => array( 'label' => 'İlçe ya da semt', 'type' => 'text' ),
							),
							'default' => array(
								array( 'name' => 'Çatalca' ),
								array( 'name' => 'Silivri' ),
								array( 'name' => 'Büyükçekmece' ),
								array( 'name' => 'Arnavutköy' ),
								array( 'name' => 'Hadımköy' ),
								array( 'name' => 'Esenyurt' ),
								array( 'name' => 'Beylikdüzü' ),
								array( 'name' => 'Başakşehir' ),
								array( 'name' => 'Avcılar' ),
								array( 'name' => 'Küçükçekmece' ),
							),
						),
						'note'  => array( 'label' => 'Alt not', 'type' => 'textarea', 'default' => 'Anadolu Yakası dahil İstanbul’un diğer ilçelerine de teslim ediyoruz. Teslim günü için arayın.' ),
					),
				),
				'visit'  => array(
					'label'  => 'Depoya Gelmek',
					'fields' => array(
						'title'            => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Depoya gelmek isterseniz' ),
						'text'             => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => "Adres: Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul.\n\nGelmeden önce arayıp aradığınız ürünün stokta olup olmadığını sormanızı öneririz." ),
						'directions_label' => array( 'label' => 'Yol Tarifi Düğmesi', 'type' => 'text', 'default' => 'Yol tarifi alın' ),
					),
				),
			),
		),

		/*
		 * Sik sorulan sorular (/sss/). Cevaplar yalnizca sitenin mevcut
		 * metinlerinden (urun metinleri, Hakkimizda, fiyat seridi). Cevabi bos
		 * soru firmadan cevap bekliyor (DEVAM.md): sitede ve arama verisinde
		 * gorunmez.
		 */
		'faq' => array(
			'label'      => 'Sık Sorulan Sorular',
			'path'       => '/sss/',
			'seo_source' => array(
				'type'        => 'FAQPage',
				'questions'   => 'items.rows',
				'title'       => 'head.title',
				'description' => 'head.text',
			),
			'components' => array(
				'head'  => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Sık sorulan sorular' ),
						'text' => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Kereste, plaka, tomruk ve ahşap ambalaj hakkında en çok sorulanlar.' ),
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
								array( 'group' => 'Ürünler', 'question' => 'Hangi ürünleri satıyorsunuz?', 'answer' => 'Kereste, tomruk, kalas ve çıta; OSB, kontrplak ve plywood plaka; ahşap palet, sandık ve kafes. Hepsi Çatalca’daki depomuzda.' ),
								array( 'group' => 'Ürünler', 'question' => 'Kereste nedir, nerede kullanılır?', 'answer' => 'Kereste, tomrukların boyuna biçilmesiyle elde edilen, birbirine paralel iki yüzeyi olan ağaç parçasıdır. Yumuşak ve sert kereste olarak ikiye ayrılır; inşaat, ambalaj, mobilya ve dekorasyonda sıkça kullanılır.' ),
								array( 'group' => 'Ürünler', 'question' => 'OSB ile kontrplak arasındaki fark nedir?', 'answer' => 'OSB, boyuna rendelenen ahşap yongalarının tutkalla, ısı ve basınçla birleştirilmesiyle üretilir; en çok çatı kaplamasında, ayrıca döşeme, duvar ve panolarda kullanılır. Kontrplak ise ağaç katmanlarının birbirine dik gelecek şekilde yapıştırılıp preslenmesiyle elde edilir; hafiftir, çatlamaya dirençlidir, suya dayanıklıdır ve vida tutar.' ),
								array( 'group' => 'Ürünler', 'question' => 'Plywood nedir, nerede kullanılır?', 'answer' => 'Plywood, ince ağaç tabakalarının çapraz dik açıyla yapıştırılıp preslenmesiyle elde edilir. Hafif, dayanıklı ve düz yüzeylidir; bu yüzden kullanım alanı geniştir. İstenen ölçü ve ebata getirilebilir.' ),
								array( 'group' => 'Ürünler', 'question' => 'Tomruk hangi ağaçlardan?', 'answer' => 'Ağırlıklı olarak kayın, kızılçam, meşe ve karaçam. Tomruk inşaatlık kereste ve palet üretiminde kullanılır; ağaç ev yapmak isteyenler de tercih eder.' ),
								array( 'group' => 'Sipariş ve fiyat', 'question' => 'Özel ölçüde çıta ya da kalas hazırlanır mı?', 'answer' => 'Evet. Keresteyi ve plakayı istediğiniz boyda ve kalınlıkta kesiyoruz; çıtada standart ölçülerin yanında farklı boyutlarda özel üretim de yapılır.' ),
								array( 'group' => 'Sipariş ve fiyat', 'question' => 'Fiyat neye göre belirlenir?', 'answer' => 'Kereste fiyatı ağaç türüne, ölçüye ve miktara göre değişir. Güncel fiyat için arayın ya da iletişim formundan ürün, ölçü ve adedi yazın.' ),
								array( 'group' => 'Sipariş ve fiyat', 'question' => 'Sipariş nasıl verilir?', 'answer' => 'Üç adımda: ürünü seçin; ölçüyü ve adedi iletişim formundan ya da telefonla bildirin; ürün adresinize teslim edilir ya da depoda aracınıza yüklenir.' ),
								array( 'group' => 'Sipariş ve fiyat', 'question' => 'Fiyat almak için hangi bilgileri vermeliyim?', 'answer' => 'Ürünü, ağaç türünü, ölçüyü (kalınlık × genişlik × boy), adedi ve teslim yerini yazmanız yeterli. Güncel fiyatı ve stok durumunu bu bilgilere göre söyleriz.' ),
								array( 'group' => 'Teslimat', 'question' => 'Hangi bölgelere teslimat yapıyorsunuz?', 'answer' => 'Çatalca’daki depomuzdan İstanbul’un her yerine; şantiyeye, atölyeye ya da fabrikaya teslim ediyoruz.' ),
								array( 'group' => 'Teslimat', 'question' => 'Depodan gelip kendim alabilir miyim?', 'answer' => 'Evet. Ürünü Çatalca Kestanelik’teki depomuzda aracınıza yüklüyoruz. Gelmeden önce arayıp stoğu sormanızı öneririz.' ),
								array( 'group' => 'Ölçü ve hesap', 'question' => 'Kereste metreküpü nasıl hesaplanır?', 'answer' => 'Kalınlığı ve genişliği santimetreden metreye çevirip boy ve adetle çarpın. Örneğin 5 × 10 cm kesitli, 4 m boyunda 100 adet kereste: 0,05 × 0,10 × 4 × 100 = 2 m³.' ),
								array( 'group' => 'Ölçü ve hesap', 'question' => '1 metreküp kerestede kaç adet var?', 'answer' => 'Kesite ve boya göre değişir. 4 m boyunda 5 × 10 cm kereste 1 m³’te 50 adet, 10 × 10 cm kereste 25 adet eder. 3 m boyunda 5 × 10 cm keresteden yaklaşık 66 adet çıkar.' ),
								array( 'group' => 'Sipariş ve fiyat', 'question' => 'Sevkiyat süresi nedir, teslimat nasıl yapılıyor?', 'answer' => '' ),
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

		'blog' => array(
			'label'      => 'Blog',
			'path'       => '/blog/',
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Blog' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Kereste türleri, fiyatlar ve doğru malzemeyi seçmek üzerine yazılar.' ),
						'empty' => array( 'label' => 'Yazı Yokken Görünen Metin', 'type' => 'textarea', 'default' => 'Henüz yazı yok. Sorunuz varsa bize yazın; yanıtı burada paylaşalım.' ),
					),
				),
				'post' => array(
					'label'  => 'Yazı Sayfası',
					'fields' => array(
						'read_label' => array( 'label' => 'Devamı Metni', 'type' => 'text', 'default' => 'Devamını oku' ),
						'back_label' => array( 'label' => 'Geri Bağlantısı', 'type' => 'text', 'default' => 'Bütün yazılar' ),
						'more_title' => array( 'label' => 'Diğer Yazılar Başlığı', 'type' => 'text', 'default' => 'Diğer yazılar' ),
					),
				),
			),
		),

		/*
		 * Bulunamayan sayfa (404). Onizleme adresi bilerek olmayan bir sayfa.
		 * Basligin altinda urun listesi gosterilir.
		 */
		'notfound' => array(
			'label'      => '404 Sayfası',
			'path'       => '/bulunamadi-404/',
			// Gizli: sekme listesinde yok, "Sayfa bul" ile acilir.
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Sayfa Başlığı',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Bu adreste bir sayfa yok' ),
						'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Bağlantı eski ya da hatalı olabilir. Aradığınız ürün aşağıdaki listede olabilir.' ),
					),
				),
			),
		),
	) + $ik_product_pages + $ik_blog_pages,
);

// Panel ipuclari: ayni bilginin sitede yazili oldugu diger yerler, baglanti yazimi.
$manifest_hints = require __DIR__ . '/content-manifest-hints.php';

return $manifest_hints(
	$manifest_data,
	array(
		'Bu adres' => array( 'global.topbar.address', 'global.footer.address', 'contact.info.address', 'contact.map.query' ),
		'Bu telefon numarası' => array( 'global.header.phone_label', 'global.footer.phone_label', 'contact.info.phone' ),
		'Bu WhatsApp numarası' => array( 'global.footer.whatsapp_label', 'contact.info.whatsapp', 'products.help.number_label' ),
		'Bu WhatsApp bağlantısı' => array( 'global.footer.whatsapp_url', 'contact.info.whatsapp_url', 'products.help.number_url', 'home.ctaband.secondary_url' ),
		'Bu e-posta adresi' => array( 'global.topbar.email_label', 'global.footer.email_label', 'contact.info.email' ),
	)
);
