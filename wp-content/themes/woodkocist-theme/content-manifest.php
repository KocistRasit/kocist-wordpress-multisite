<?php
/**
 * woodkocist temasi alan manifesti.
 *
 * Urunler burada tanimlanmaz: merkezi Urun Havuzu'ndan gelir. Hangi urunun
 * sitede gorunecegi Icerik Studyosu -> Magaza -> Tum Urunler alanindan, ya da
 * kategori sekmelerinden (Kopek Kulubeleri...) yalnizca o kategori icin secilir
 * (urun ekle / cikar / sirala / siteye ozel ad-fiyat). Kod gerekmez.
 *
 * Metinler woodkocist.com.tr'nin kendi sayfalarindan (25 Eylul 2026): ana sayfa,
 * Sirketimiz, Cozum Merkezi (/iletisim/), Ozel Uretim, SSS ve odeme adimi.
 * Iletisim: sitenin kendi /iletisim/ sayfasi (WhatsApp 0549 648 19 19, kurumsal
 * hat 0212 648 19 19, info@kocist.com.tr). Adres ve "50 yil" butun
 * sitelerde ayni (kullanici karari, 25 Eylul 2026): Kestanelik ... 2125/1.
 *
 * Yasal ve kurumsal metinler (KVKK, mesafeli satis...) WordPress sayfasidir:
 * content/pages.php ilk metni verir, sonrasi WordPress duzenleyicisinden.
 */

$address = "Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1\nÇatalca, İstanbul";

$head = static function ( string $title, string $lead ): array {
	return array(
		'label'  => 'Sayfa Başı',
		'fields' => array(
			'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => $title ),
			'lead'  => array( 'label' => 'Öne Çıkan Cümle', 'type' => 'textarea', 'default' => $lead ),
		),
	);
};

/*
 * Kategori sayfalari (/urun-kategori/<seri>/<kategori>/) ve seri sayfalari
 * (/urun-kategori/<seri>/): hangi havuz kategorisinin hangi serinin altinda
 * gorundugu veridir, Ürün Havuzu -> Kategoriler'den yonetilir (eklenti,
 * includes/product-categories.php). Eklenti her yerlesik kategori icin
 * asagidaki sablondan 'kat-<havuz-slug>' panel sayfasini, her seri icin gizli
 * 'seri-<seri>' sayfasini uretir. Seriler Ana Sayfa -> Seriler satirlaridir.
 *
 * 'defaults': eski sabit listedeki metinler (panelde bos kalan alanlarda
 * gorunur). 'seed': ilk kurulumdaki yerlesim; bir kez yazilir, sonra panelden
 * degisir.
 */
$catalog = array(
	'parents'       => array( 'rows' => array( 'home', 'catalog', 'lines' ), 'key' => 'category', 'label' => 'label' ),
	'parent_prefix' => 'seri-',
	// Seri icindeki kategori sirasi: magazadaki urun sirasinda ilk gorunus.
	'order'         => 'first_product',
	// Bu sitede urunu olmayan kategori menude ve magazada yok, adresi 404;
	// panel "bu sitede urunu olunca gorunur" der.
	'hide_empty'    => true,
	// Panelde sekmeler Magaza'dan sonra.
	'after'         => 'shop',
	'page'          => array(
		'label'          => '{name} (Mağaza)',
		'path'           => '/urun-kategori/{parent}/{slug}/',
		'seo_source'     => array(
			'title'       => 'head.title',
			'description' => 'head.lead',
		),
		'components'     => array(
			'head' => array(
				'label'  => 'Sayfa Başı ve Menü Adı',
				'fields' => array(
					'name'  => array( 'label' => 'Kategori adı (menü, kenar listesi, ana sayfa kutuları)', 'type' => 'text', 'default' => '{name}', 'hint' => 'Yalnızca bu sitede görünen ad; Ürün Havuzu’ndaki kategori adı değişmez.' ),
					'title' => array( 'label' => 'Sayfa başlığı', 'type' => 'text', 'default' => '{name}' ),
					'lead'  => array( 'label' => 'Öne çıkan cümle', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
		'products_label' => '{name}: ürünler (seçin, sıralayın, bu siteye özel ad ve fiyat)',
	),
	'parent_page'   => array(
		'label'      => 'Seri: {name}',
		'path'       => '/urun-kategori/{parent}/',
		'hidden'     => true,
		'components' => array(
			'head' => array(
				'label'  => 'Sayfa Başı',
				'fields' => array(
					'lead' => array(
						'label'   => 'Alt metin',
						'type'    => 'textarea',
						'default' => '',
						'hint'    => 'Boş bırakılırsa Ana Sayfa → Seriler’deki kısa açıklama görünür. Sayfa başlığı serinin adıdır (Ana Sayfa → Seriler).',
					),
				),
			),
		),
	),
	'defaults'      => array(
		'kopek-kulubeleri' => array( 'title' => 'Ahşap köpek kulübeleri', 'lead' => 'Verandalı, bölmeli ve farklı boylarda ahşap köpek kulübeleri. Ölçü, ahşap cinsi ve KDV dahil fiyatlarıyla.' ),
		'kedi-yuvalari'    => array( 'title' => 'Ahşap kedi yuvaları', 'lead' => 'Tekli, üçlü ve altılı ahşap kedi yuvaları; farklı renk seçenekleriyle.' ),
		'kamelyalar'       => array( 'title' => 'Ahşap kamelyalar', 'lead' => 'Klasik, Prestij ve Master seri ahşap kamelyalar; zemin platformlu ve kuşluklu modeller.' ),
		'cardaklar'        => array( 'title' => 'Ahşap çardaklar', 'lead' => 'Kompakt, orta ve büyük boy ahşap çardaklar.' ),
		'piknik-masalari'  => array( 'title' => 'Ahşap piknik masaları', 'lead' => 'Bahçe ve teras için oturaklı ahşap piknik masaları.' ),
		'ahsap-sezlonglar' => array( 'title' => 'Ahşap şezlonglar', 'lead' => 'Bahçe, havuz başı ve teras için ahşap şezlonglar.' ),
		'adirondack'       => array( 'title' => 'Adirondack ahşap sandalyeler', 'lead' => 'Katlanır ve sabit, küçük ve büyük boy Adirondack sandalyeler.' ),
	),
	'seed'          => array(
		'kopek-kulubeleri' => 'woodpets',
		'kedi-yuvalari'    => 'woodpets',
		'kamelyalar'       => 'woodgarden',
		'cardaklar'        => 'woodgarden',
		'piknik-masalari'  => 'woodgarden',
		'ahsap-sezlonglar' => 'woodgarden',
		'adirondack'       => 'woodliving',
	),
);

return array(
	'site_key'          => 'woodkocist',
	// Kategori yerlesimi ve kategori sayfalarinin sablonu (yukarida).
	'catalog'           => $catalog,
	// Aciklamasi (detay metni) olmayan havuz urununun sayfasi da acik kalir:
	// ad, gorsel ve teknik detaylar gosterilir (eklenti: nwcs_product_has_page).
	'product_page_always' => true,
	// Havuz urunlerinin sayfalarindaki (/urun/<urun>/) ortak metinler bu panel sayfasinda.
	'product_page'      => 'product',
	'site_label'        => 'Koçist · woodkocist.com.tr',
	// Panelde gorunen kisa ad (alan adi olmadan).
	'panel_label'       => 'WOOD KOCIST',

	'seo_site_defaults' => array(
		'name'        => 'WOOD KOCIST',
		'legal_name'  => '',
		'description' => 'Ahşap bahçe mobilyası, ev ürünleri ve evcil hayvan yuvaları: Adirondack sandalye, çardak, kamelya, piknik masası, şezlong, kedi yuvası ve köpek kulübesi. Koçist Orman Ürünleri markası.',
		'phone'       => '+90 212 648 19 19',
		'email'       => 'info@kocist.com.tr',
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

		'global' => array(
			'label'      => 'Tüm Sayfalar (Üst Menü, Alt Bilgi, Mağaza Ayarları)',
			'path'       => '/',
			'components' => array(
				'header' => array(
					'label'  => 'Üst Menü',
					'fields' => array(
						'logo_text'      => array( 'label' => 'Marka', 'type' => 'text', 'default' => 'WOOD KOCIST' ),
						'menu'           => array(
							'label'   => 'Menü Öğeleri (adresi /magaza/ olana ürün menüsü, /sirketimiz/ olana Hakkımızda menüsü açılır)',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı Adresi', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Anasayfa', 'url' => '/' ),
								array( 'label' => 'Mağaza', 'url' => '/magaza/' ),
								array( 'label' => 'Hakkımızda', 'url' => '/sirketimiz/' ),
								array( 'label' => 'Çözüm Merkezi', 'url' => '/iletisim/' ),
							),
						),
						'about_menu'     => array(
							'label'   => 'Hakkımızda açılır menüsü (alt bilgideki "Kurumsal" listesi de bu)',
							'type'    => 'repeater',
							'max'     => 10,
							'fields'  => array(
								'label' => array( 'label' => 'Bağlantı Metni', 'type' => 'text' ),
								'url'   => array( 'label' => 'Bağlantı Adresi', 'type' => 'url' ),
							),
							'default' => array(
								array( 'label' => 'Hikayemiz', 'url' => '/sirketimiz/' ),
								array( 'label' => 'Sürdürülebilirlik', 'url' => '/surdurulebilirlik/' ),
								array( 'label' => 'Kurumsal Politikalar', 'url' => '/politikalar/' ),
								array( 'label' => 'Sıkça Sorulan Sorular', 'url' => '/sss/' ),
								array( 'label' => 'Çözüm Merkezi', 'url' => '/iletisim/' ),
								array( 'label' => 'Özel Üretim', 'url' => '/ozel-uretim-talep-formu/' ),
							),
						),
						'whatsapp_url'   => array( 'label' => 'WhatsApp Bağlantısı', 'type' => 'url', 'default' => 'https://wa.me/905496481919' ),
						'whatsapp_label' => array( 'label' => 'WhatsApp Numarası (görünen)', 'type' => 'text', 'default' => '0549 648 19 19' ),
						'whatsapp_text'  => array( 'label' => 'WhatsApp hazır mesajı (genel)', 'type' => 'text', 'default' => 'Merhaba, WOOD Koçist ürünleri hakkında bilgi almak istiyorum.', 'hint' => 'Ürün dışındaki WhatsApp düğmelerinde mesaj kutusuna hazır gelir.' ),
						'whatsapp_product_text' => array( 'label' => 'WhatsApp hazır mesajı (ürün sayfası)', 'type' => 'text', 'default' => 'Merhaba, {urun} ({kategori}) hakkında bilgi almak istiyorum. Ürün kodu: {kod} {url}', 'hint' => '{urun} ürün adı, {kategori} kategorisi, {kod} ürün kodu, {url} ürün sayfasının adresidir; silmeyin.' ),
						'phone_label'    => array( 'label' => 'Kurumsal Telefon (boşsa gizlenir)', 'type' => 'text', 'default' => '0212 648 19 19' ),
						'phone_url'      => array( 'label' => 'Telefon Bağlantısı', 'type' => 'url', 'default' => 'tel:+902126481919' ),
						'email'          => array( 'label' => 'E-posta (boşsa gizlenir)', 'type' => 'text', 'default' => 'info@kocist.com.tr' ),
						'custom_label'   => array( 'label' => 'Özel Üretim düğmesi (üst menü)', 'type' => 'text', 'default' => 'Özel Üretim' ),
						'mega_all'       => array( 'label' => 'Ürün menüsü: tüm ürünler bağlantısı', 'type' => 'text', 'default' => 'Tüm ürünleri görüntüle' ),
						'search_label'   => array( 'label' => 'Arama kutusu etiketi (ekran okuyucu)', 'type' => 'text', 'default' => 'Ürün adı ya da kodu' ),
						'search_hint'    => array( 'label' => 'Arama kutusu içindeki örnek yazı', 'type' => 'text', 'default' => 'Ürün adı ya da kodu: kamelya, W-KAM-300…' ),
						'search_button'  => array( 'label' => 'Arama düğmesi', 'type' => 'text', 'default' => 'Ara' ),
						'search_toggle'  => array( 'label' => 'Arama simgesi (ekran okuyucu)', 'type' => 'text', 'default' => 'Ürün ara' ),
						// Canli arama: kutuya yazarken acilan liste (eklenti assets/live-search.js).
						'live_all'       => array( 'label' => 'Canlı arama: tüm sonuçlar bağlantısı', 'type' => 'text', 'default' => 'Tüm sonuçlar ({sayi})', 'hint' => 'Kutuya yazarken açılan listenin altında; mağazadaki arama sonuçlarını açar. {sayi} sonuç sayısıdır; silmeyin. Sonuç yoksa listede Mağaza › "Sonuç yok" metinleri görünür; fiyat ve düğme yazıları Ürün Kartları bölümünden gelir.' ),
						'live_count'     => array( 'label' => 'Canlı arama: sonuç sayısı (ekran okuyucu)', 'type' => 'text', 'default' => '{sayi} ürün bulundu', 'hint' => '{sayi} sonuç sayısıdır; silmeyin.' ),
						'live_loading'   => array( 'label' => 'Canlı arama: sonuç gecikince', 'type' => 'text', 'default' => 'Aranıyor…' ),
						'live_code'      => array( 'label' => 'Canlı arama: ürün kodu satırı', 'type' => 'text', 'default' => 'Ürün kodu: {kod}', 'hint' => '{kod} ürünün kodudur; silmeyin.' ),
						'live_list'      => array( 'label' => 'Canlı arama: listenin adı (ekran okuyucu)', 'type' => 'text', 'default' => 'Arama önerileri' ),
						'live_qty'       => array( 'label' => 'Canlı arama: adet kutusu (ekran okuyucu)', 'type' => 'text', 'default' => 'Adet' ),
						'live_less'      => array( 'label' => 'Canlı arama: adet azalt (ekran okuyucu)', 'type' => 'text', 'default' => 'Adedi azalt' ),
						'live_more'      => array( 'label' => 'Canlı arama: adet artır (ekran okuyucu)', 'type' => 'text', 'default' => 'Adedi artır' ),
						'live_full'      => array( 'label' => 'Canlı arama: sepet doluysa', 'type' => 'text', 'default' => 'Sepet dolu: en fazla {sayi} çeşit ürün eklenebilir. Önce sepetteki ürünlerden çıkarın.', 'hint' => '{sayi} sepetteki en fazla ürün çeşidi sayısıdır; silmeyin.' ),
						'cart_label'     => array( 'label' => 'Sepet simgesi (ekran okuyucu)', 'type' => 'text', 'default' => 'Sepet, {sayi} ürün', 'hint' => '{sayi} sepetteki ürün adedidir; silmeyin.' ),
						'menu_open'      => array( 'label' => 'Mobil menü: aç düğmesi (ekran okuyucu)', 'type' => 'text', 'default' => 'Menüyü aç' ),
						'menu_close'     => array( 'label' => 'Mobil menü: kapat düğmesi (ekran okuyucu)', 'type' => 'text', 'default' => 'Menüyü kapat' ),
						'drawer_all'     => array( 'label' => 'Mobil menü: tüm ürünler bağlantısı', 'type' => 'text', 'default' => 'Tüm ürünler' ),
						'drawer_cart'    => array( 'label' => 'Mobil menü: sepet düğmesi', 'type' => 'text', 'default' => 'Sepetim' ),
						'drawer_wa'      => array( 'label' => 'Mobil menü: WhatsApp düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
					),
				),
				'footer' => array(
					'label'  => 'Alt Bilgi',
					'fields' => array(
						'band'      => array( 'label' => 'Marka Şeridi', 'type' => 'text', 'default' => 'WOOD Koçist bir Koçist Orman Ürünleri markasıdır.' ),
						'tagline'   => array( 'label' => 'Alt Bilgi Metni', 'type' => 'textarea', 'default' => 'Sadece kendi evlerimizde ve bahçelerimizde görmek isteyeceğimiz, zanaatına ve kalitesine bizzat güvendiğimiz özel tasarımları üretiyoruz.' ),
						'address'   => array( 'label' => 'Adres', 'type' => 'textarea', 'default' => $address ),
						'copyright' => array( 'label' => 'Telif Satırı', 'type' => 'text', 'default' => 'Koçist Orman Ürünleri' ),
						'wa_prefix'      => array( 'label' => 'WhatsApp satırının başı', 'type' => 'text', 'default' => 'WhatsApp:' ),
						'col_collection' => array( 'label' => 'Sütun başlığı: koleksiyon', 'type' => 'text', 'default' => 'Koleksiyon' ),
						'all_products'   => array( 'label' => 'Koleksiyon: tüm ürünler bağlantısı', 'type' => 'text', 'default' => 'Tüm ürünler' ),
						'col_corporate'  => array( 'label' => 'Sütun başlığı: kurumsal (yasal sayfaların yan listesinde de)', 'type' => 'text', 'default' => 'Kurumsal' ),
						'col_legal'      => array( 'label' => 'Sütun başlığı: yasal sayfalar (yasal sayfaların yan listesinde de)', 'type' => 'text', 'default' => 'Çözüm Merkezi' ),
						'parent_link'    => array( 'label' => 'Ana firma bağlantısının yazısı', 'type' => 'text', 'default' => 'kocist.com.tr' ),
						'callbar'        => array( 'label' => 'Mobil alt WhatsApp çubuğu', 'type' => 'text', 'default' => 'WhatsApp’tan sorun' ),
					),
				),
				'legal'  => array(
					'label'  => 'Yasal Sayfa Adları (alt bilgi ve yan liste)',
					'fields' => array(
						'odeme_teslimat' => array( 'label' => 'Ödeme ve teslimat', 'type' => 'text', 'default' => 'Ödeme ve Teslimat', 'hint' => 'Sayfanın kendi başlığı ve metni WordPress sayfasından düzenlenir.' ),
						'iptal_iade'     => array( 'label' => 'İptal ve iade', 'type' => 'text', 'default' => 'İptal ve İade Koşulları' ),
						'mesafeli'       => array( 'label' => 'Mesafeli satış', 'type' => 'text', 'default' => 'Mesafeli Satış Sözleşmesi' ),
						'kvkk'           => array( 'label' => 'KVKK', 'type' => 'text', 'default' => 'KVKK Aydınlatma Metni' ),
						'gizlilik'       => array( 'label' => 'Gizlilik', 'type' => 'text', 'default' => 'Gizlilik Politikası' ),
						'cookies'        => array( 'label' => 'Çerezler', 'type' => 'text', 'default' => 'Çerez Politikası' ),
						'kullanim'       => array( 'label' => 'Kullanım koşulları', 'type' => 'text', 'default' => 'Koşullar ve Fikri Mülkiyet' ),
						'crumb_home'     => array( 'label' => 'Konum satırı: ana sayfa (tüm sayfalar)', 'type' => 'text', 'default' => 'Anasayfa' ),
						'crumb_shop'     => array( 'label' => 'Konum satırı: mağaza (tüm sayfalar)', 'type' => 'text', 'default' => 'Mağaza' ),
						'crumb_corporate' => array( 'label' => 'Konum satırı: kurumsal (yasal sayfalar)', 'type' => 'text', 'default' => 'Kurumsal' ),
						'updated'        => array( 'label' => 'Yasal sayfa: güncelleme tarihi satırı', 'type' => 'text', 'default' => 'Son güncelleme: {tarih}', 'hint' => '{tarih} sayfanın son değiştirildiği gündür; silmeyin.' ),
					),
				),
				'card'   => array(
					'label'  => 'Ürün Kartları ve Fiyat Yazıları (tüm sayfalar)',
					'fields' => array(
						'price_ask'    => array( 'label' => 'Fiyatı olmayan ürün: fiyat yerine', 'type' => 'text', 'default' => 'Fiyat teklifle' ),
						'price_reason' => array( 'label' => 'Fiyatı olmayan ürün: nedeni (ürün sayfasında, tüm ürünler)', 'type' => 'textarea', 'default' => 'Bu ürün sipariş üzerine üretildiği için sitede sabit fiyat gösterilmiyor; fiyat ölçü, donanım ve teslim şekline göre belirlenir. Hızlı Sipariş’ten ya da formla sorun.', 'hint' => 'Fiyatın neden gösterilmediğini ürüne bağlı anlatın; kişiye özel fiyat izlenimi veren ifade kullanmayın.' ),
						'ask_button'   => array( 'label' => 'Fiyatı olmayan ürün: kart düğmesi', 'type' => 'text', 'default' => 'Fiyat sor' ),
						'add_button'   => array( 'label' => 'Sepete ekle düğmesi', 'type' => 'text', 'default' => 'Sepete ekle' ),
						'vat_included' => array( 'label' => 'Fiyat yanındaki not (KDV dahil)', 'type' => 'text', 'default' => 'KDV dahil' ),
						'vat_excluded' => array( 'label' => 'Fiyat yanındaki not (KDV hariç)', 'type' => 'text', 'default' => '+ KDV' ),
						'count'        => array( 'label' => 'Ürün sayısı yazısı', 'type' => 'text', 'default' => '{sayi} ürün', 'hint' => '{sayi} ürün adedidir; silmeyin.' ),
						'colors'       => array( 'label' => 'Renk sayısı yazısı (kartta)', 'type' => 'text', 'default' => '{sayi} renk', 'hint' => 'Aynı ürünün renkleri tek kartta toplandığında (Ürün Havuzu › Kategoriler › Renk grupları) kartın altında görünür. {sayi} renk adedidir; silmeyin.' ),
					),
				),
				'cart'   => array(
					'label'  => 'Sepet Sayfası (/sepet/)',
					'fields' => array(
						'heading'         => array( 'label' => 'Sayfa başlığı', 'type' => 'text', 'default' => 'Sepetim' ),
						'removed'         => array( 'label' => 'Sepetten çıkarılan ürün uyarısı', 'type' => 'textarea', 'default' => 'Şu ürünler artık satışta olmadığı ya da fiyatı değiştiği için sepetinizden çıkarıldı: {urunler}.', 'hint' => '{urunler} çıkarılan ürünlerin adlarıdır; silmeyin.' ),
						'empty_title'     => array( 'label' => 'Boş sepet: başlık', 'type' => 'text', 'default' => 'Sepetiniz boş' ),
						'empty_desc'      => array( 'label' => 'Boş sepet: açıklama', 'type' => 'textarea', 'default' => 'Beğendiğiniz ürünün sayfasında “Sepete ekle”ye basın; ürün burada görünür.' ),
						'empty_button'    => array( 'label' => 'Boş sepet: düğme', 'type' => 'text', 'default' => 'Ürünlere göz atın' ),
						'col_product'     => array( 'label' => 'Tablo: ürün sütunu', 'type' => 'text', 'default' => 'Ürün' ),
						'col_unit'        => array( 'label' => 'Tablo: birim fiyat sütunu', 'type' => 'text', 'default' => 'Birim fiyat' ),
						'col_qty'         => array( 'label' => 'Tablo: adet sütunu', 'type' => 'text', 'default' => 'Adet' ),
						'col_total'       => array( 'label' => 'Tablo: tutar sütunu', 'type' => 'text', 'default' => 'Tutar' ),
						'continue'        => array( 'label' => 'Alışverişe devam bağlantısı (sipariş onayında da)', 'type' => 'text', 'default' => 'Alışverişe devam et' ),
						'update'          => array( 'label' => 'Sepeti güncelle düğmesi', 'type' => 'text', 'default' => 'Sepeti güncelle' ),
						'summary_title'   => array( 'label' => 'Özet kutusu başlığı (ödeme sayfasında da)', 'type' => 'text', 'default' => 'Sipariş özeti' ),
						'checkout_button' => array( 'label' => 'Siparişi tamamla düğmesi', 'type' => 'text', 'default' => 'Siparişi tamamla' ),
						'assure'          => array( 'label' => 'Özet altındaki güvence satırı', 'type' => 'text', 'default' => 'Üyelik gerekmez; ad, telefon ve adres yeterli.' ),
						'step_cart'       => array( 'label' => 'Adım göstergesi: 1', 'type' => 'text', 'default' => 'Sepet' ),
						'step_details'    => array( 'label' => 'Adım göstergesi: 2', 'type' => 'text', 'default' => 'Bilgiler ve ödeme' ),
						'step_done'       => array( 'label' => 'Adım göstergesi: 3', 'type' => 'text', 'default' => 'Onay' ),
						'subtotal'        => array( 'label' => 'Tutar dökümü: ara toplam', 'type' => 'text', 'default' => 'Ara toplam' ),
						'subtotal_count'  => array( 'label' => 'Tutar dökümü: ara toplamın yanındaki adet', 'type' => 'text', 'default' => '({sayi} ürün)', 'hint' => '{sayi} sepetteki ürün adedidir; silmeyin.' ),
						'vat'             => array( 'label' => 'Tutar dökümü: KDV', 'type' => 'text', 'default' => 'KDV' ),
						'shipping'        => array( 'label' => 'Tutar dökümü: kargo', 'type' => 'text', 'default' => 'Kargo' ),
						'total'           => array( 'label' => 'Tutar dökümü: toplam', 'type' => 'text', 'default' => 'Toplam' ),
						'total_vat'       => array( 'label' => 'Tutar dökümü: toplamın yanındaki KDV notu', 'type' => 'text', 'default' => '(KDV dahil)' ),
					),
				),
				'checkout' => array(
					'label'  => 'Ödeme Sayfası (/odeme/)',
					'fields' => array(
						'heading'          => array( 'label' => 'Sayfa başlığı', 'type' => 'text', 'default' => 'Bilgiler ve ödeme' ),
						'errors_title'     => array( 'label' => 'Hata kutusu: başlık', 'type' => 'text', 'default' => 'Siparişiniz henüz verilmedi.' ),
						'errors_count'     => array( 'label' => 'Hata kutusu: açıklama', 'type' => 'text', 'default' => 'Düzeltilmesi gereken {sayi} alan var; aşağıda kırmızıyla işaretli.', 'hint' => '{sayi} hatalı alan sayısıdır; silmeyin.' ),
						'contact_title'    => array( 'label' => '1. bölüm başlığı', 'type' => 'text', 'default' => 'İletişim bilgileri' ),
						'contact_desc'     => array( 'label' => '1. bölüm açıklaması', 'type' => 'text', 'default' => 'Üyelik gerekmez. Teslimat günü için sizi bu numaradan arayacağız.' ),
						'first_label'      => array( 'label' => 'Alan: ad', 'type' => 'text', 'default' => 'Ad' ),
						'last_label'       => array( 'label' => 'Alan: soyad', 'type' => 'text', 'default' => 'Soyad' ),
						'phone_label'      => array( 'label' => 'Alan: telefon', 'type' => 'text', 'default' => 'Cep telefonu' ),
						'phone_hint'       => array( 'label' => 'Alan: telefon örnek yazısı', 'type' => 'text', 'default' => '05XX XXX XX XX' ),
						'email_label'      => array( 'label' => 'Alan: e-posta', 'type' => 'text', 'default' => 'E-posta' ),
						'email_help'       => array( 'label' => 'Alan: e-posta açıklaması', 'type' => 'text', 'default' => 'Sipariş numaranızı ve ödeme bilgisini buraya göndeririz.' ),
						'delivery_title'   => array( 'label' => '2. bölüm başlığı', 'type' => 'text', 'default' => 'Teslimat' ),
						'ship_option'      => array( 'label' => 'Seçenek: adrese gönderim', 'type' => 'text', 'default' => 'Adrese gönderim' ),
						'pickup_option'    => array( 'label' => 'Seçenek: fabrikadan teslim', 'type' => 'text', 'default' => 'Fabrikadan teslim alırım' ),
						'pickup_price'     => array( 'label' => 'Seçenek: fabrikadan teslim ücreti', 'type' => 'text', 'default' => 'Ücretsiz' ),
						'city_label'       => array( 'label' => 'Alan: il', 'type' => 'text', 'default' => 'İl' ),
						'choose'           => array( 'label' => 'Liste: boş seçenek', 'type' => 'text', 'default' => 'Seçin' ),
						'district_label'   => array( 'label' => 'Alan: ilçe', 'type' => 'text', 'default' => 'İlçe' ),
						'address_label'    => array( 'label' => 'Alan: açık adres', 'type' => 'text', 'default' => 'Açık adres' ),
						'address_hint'     => array( 'label' => 'Alan: açık adres örnek yazısı', 'type' => 'text', 'default' => 'Mahalle, cadde/sokak, bina ve daire no' ),
						'postcode_label'   => array( 'label' => 'Alan: posta kodu', 'type' => 'text', 'default' => 'Posta kodu' ),
						'optional'         => array( 'label' => 'İsteğe bağlı alan notu', 'type' => 'text', 'default' => '(isteğe bağlı)' ),
						'invoice_title'    => array( 'label' => '3. bölüm başlığı', 'type' => 'text', 'default' => 'Fatura' ),
						'corporate_check'  => array( 'label' => 'Kutu: kurumsal fatura', 'type' => 'text', 'default' => 'Kurumsal fatura istiyorum' ),
						'company_label'    => array( 'label' => 'Alan: firma unvanı', 'type' => 'text', 'default' => 'Firma unvanı' ),
						'tax_office_label' => array( 'label' => 'Alan: vergi dairesi', 'type' => 'text', 'default' => 'Vergi dairesi' ),
						'tax_no_label'     => array( 'label' => 'Alan: vergi / TC kimlik no', 'type' => 'text', 'default' => 'Vergi / TC kimlik no' ),
						'bill_diff_check'  => array( 'label' => 'Kutu: farklı fatura adresi', 'type' => 'text', 'default' => 'Fatura adresim teslimat adresinden farklı' ),
						'bill_addr_label'  => array( 'label' => 'Alan: fatura adresi', 'type' => 'text', 'default' => 'Fatura adresi' ),
						'payment_title'    => array( 'label' => '4. bölüm başlığı', 'type' => 'text', 'default' => 'Ödeme' ),
						'payment_option'   => array( 'label' => 'Seçenek: havale / EFT', 'type' => 'text', 'default' => 'Banka Havalesi / EFT' ),
						'bank_next'        => array( 'label' => 'Banka hesabı girilmişse', 'type' => 'text', 'default' => 'Banka hesap bilgileri sipariş numaranızla birlikte bir sonraki sayfada görünür.' ),
						'bank_later'       => array( 'label' => 'Banka hesabı girilmemişse', 'type' => 'text', 'default' => 'Sipariş numaranız bir sonraki sayfada görünür; banka hesap bilgilerini size ayrıca iletiriz.' ),
						'order_note_label' => array( 'label' => 'Alan: sipariş notu', 'type' => 'text', 'default' => 'Sipariş notu' ),
						'order_note_hint'  => array( 'label' => 'Alan: sipariş notu örnek yazısı', 'type' => 'text', 'default' => 'Teslimat için uygun gün/saat, site giriş bilgisi, renk tercihi…' ),
						'edit_cart'        => array( 'label' => 'Sepeti düzenle bağlantısı', 'type' => 'text', 'default' => 'Sepeti düzenle' ),
						'terms'            => array( 'label' => 'Sözleşme onayı', 'type' => 'text', 'default' => '{sozlesme}’ni ve {iade} okudum, kabul ediyorum.', 'hint' => '{sozlesme} ve {iade} aşağıdaki bağlantı yazılarıdır; silmeyin.' ),
						'terms_contract'   => array( 'label' => 'Sözleşme onayı: sözleşme bağlantısı', 'type' => 'text', 'default' => 'Mesafeli Satış Sözleşmesi' ),
						'terms_returns'    => array( 'label' => 'Sözleşme onayı: iade bağlantısı', 'type' => 'text', 'default' => 'iptal ve iade koşullarını' ),
						'kvkk_notice'      => array( 'label' => 'KVKK notu', 'type' => 'text', 'default' => 'Kişisel verileriniz {kvkk} kapsamında yalnızca bu sipariş için işlenir.', 'hint' => '{kvkk} aşağıdaki bağlantı yazısıdır; silmeyin.' ),
						'kvkk_link'        => array( 'label' => 'KVKK notu: bağlantı', 'type' => 'text', 'default' => 'KVKK Aydınlatma Metni' ),
						'submit'           => array( 'label' => 'Siparişi ver düğmesi', 'type' => 'text', 'default' => 'Siparişi ver' ),
						'submit_note'      => array( 'label' => 'Düğme altı not', 'type' => 'text', 'default' => 'Ödemeyi sipariş sonrasında havale/EFT ile yaparsınız; bu adımda kart bilgisi istenmez.' ),
						'err_nonce'        => array( 'label' => 'Uyarı: form süresi doldu', 'type' => 'text', 'default' => 'Sayfa uzun süre açık kaldığı için form yenilendi. Bilgilerinizi kontrol edip “Siparişi ver”e tekrar basın.' ),
						'err_price'        => array( 'label' => 'Uyarı: fiyat değişti', 'type' => 'text', 'default' => 'Sepetinizdeki bir ürünün fiyatı az önce güncellendi. Yeni tutarı kontrol edip siparişi tekrar verin.' ),
						'err_first'        => array( 'label' => 'Uyarı: ad boş', 'type' => 'text', 'default' => 'Adınızı yazın.' ),
						'err_last'         => array( 'label' => 'Uyarı: soyad boş', 'type' => 'text', 'default' => 'Soyadınızı yazın.' ),
						'err_phone'        => array( 'label' => 'Uyarı: telefon', 'type' => 'text', 'default' => 'Telefon numaranızı alan koduyla yazın (ör. 0532 123 45 67). Teslimat için sizi arayacağız.' ),
						'err_email'        => array( 'label' => 'Uyarı: e-posta boş', 'type' => 'text', 'default' => 'E-posta adresinizi yazın; sipariş numaranızı buraya da göndereceğiz.' ),
						'err_email_bad'    => array( 'label' => 'Uyarı: e-posta geçersiz', 'type' => 'text', 'default' => 'E-posta adresi geçerli görünmüyor (ör. ad@alanadi.com).' ),
						'err_city'         => array( 'label' => 'Uyarı: il', 'type' => 'text', 'default' => 'Teslimat ilini seçin.' ),
						'err_district'     => array( 'label' => 'Uyarı: ilçe', 'type' => 'text', 'default' => 'İlçeyi yazın.' ),
						'err_address'      => array( 'label' => 'Uyarı: açık adres', 'type' => 'text', 'default' => 'Mahalle, cadde/sokak ve kapı numarasıyla açık adresi yazın.' ),
						'err_company'      => array( 'label' => 'Uyarı: firma unvanı', 'type' => 'text', 'default' => 'Firma unvanını yazın.' ),
						'err_tax_office'   => array( 'label' => 'Uyarı: vergi dairesi', 'type' => 'text', 'default' => 'Vergi dairesini yazın.' ),
						'err_tax_no'       => array( 'label' => 'Uyarı: vergi / TC kimlik no', 'type' => 'text', 'default' => 'Vergi numarası 10, TC kimlik numarası 11 hanelidir.' ),
						'err_bill_addr'    => array( 'label' => 'Uyarı: fatura adresi', 'type' => 'text', 'default' => 'Fatura adresini yazın.' ),
						'err_terms'        => array( 'label' => 'Uyarı: sözleşme onayı', 'type' => 'text', 'default' => 'Siparişi verebilmek için Mesafeli Satış Sözleşmesi’ni onaylayın.' ),
						'err_rate'         => array( 'label' => 'Uyarı: çok sayıda deneme', 'type' => 'text', 'default' => 'Kısa sürede çok sayıda sipariş denendi. Birkaç dakika sonra tekrar deneyin ya da WhatsApp’tan yazın.' ),
						'err_save'         => array( 'label' => 'Uyarı: sipariş kaydedilemedi', 'type' => 'text', 'default' => 'Sipariş kaydedilemedi. Lütfen tekrar deneyin ya da WhatsApp’tan yazın; sepetiniz duruyor.' ),
					),
				),
				'order'  => array(
					'label'  => 'Sipariş Onayı (ödemeden sonra)',
					'fields' => array(
						'done_title'    => array( 'label' => 'Başlık: yeni sipariş', 'type' => 'text', 'default' => 'Siparişiniz alındı' ),
						'status_title'  => array( 'label' => 'Başlık: eski sipariş', 'type' => 'text', 'default' => 'Sipariş durumu' ),
						'number_label'  => array( 'label' => 'Sipariş numarası etiketi', 'type' => 'text', 'default' => 'Sipariş numaranız' ),
						'status_label'  => array( 'label' => 'Durum etiketi', 'type' => 'text', 'default' => 'Durum:' ),
						'pay_title'     => array( 'label' => 'Ödeme bölümü başlığı', 'type' => 'text', 'default' => 'Ödeme: Banka Havalesi / EFT' ),
						'pay_step1'     => array( 'label' => 'Ödeme adımı 1', 'type' => 'text', 'default' => 'Toplam {tutar} tutarını {hesap} gönderin.', 'hint' => '{tutar} sipariş tutarı, {hesap} aşağıdaki iki yazıdan biridir; silmeyin.' ),
						'account_below' => array( 'label' => 'Ödeme adımı 1: banka hesabı girilmişse', 'type' => 'text', 'default' => 'aşağıdaki hesaba' ),
						'account_later' => array( 'label' => 'Ödeme adımı 1: banka hesabı girilmemişse', 'type' => 'text', 'default' => 'size ileteceğimiz banka hesabına' ),
						'pay_step2'     => array( 'label' => 'Ödeme adımı 2', 'type' => 'text', 'default' => 'Açıklama kısmına sipariş numaranızı yazın: {numara}', 'hint' => '{numara} sipariş numarasıdır; silmeyin.' ),
						'pay_step3'     => array( 'label' => 'Ödeme adımı 3', 'type' => 'text', 'default' => 'Ödemeniz onaylanınca siparişiniz üretim programına alınır; teslimat günü için sizi ararız.' ),
						'bank_missing'  => array( 'label' => 'Banka hesabı girilmemişse not', 'type' => 'textarea', 'default' => 'Banka hesap bilgilerini sipariş numaranızla birlikte telefon ya da e-postayla size ileteceğiz. Beklemek istemezseniz WhatsApp’tan sipariş numaranızı yazın.' ),
						'items_title'   => array( 'label' => 'Ürünler bölümü başlığı', 'type' => 'text', 'default' => 'Ürünler' ),
						'pickup_row'    => array( 'label' => 'Kargo satırı: fabrikadan teslimde', 'type' => 'text', 'default' => 'Fabrikadan teslim' ),
						'ship_title'    => array( 'label' => 'Teslimat bölümü başlığı', 'type' => 'text', 'default' => 'Teslimat' ),
						'pickup_prefix' => array( 'label' => 'Teslimat: fabrikadan teslim satırının başı', 'type' => 'text', 'default' => 'Fabrikadan teslim:' ),
						'help_title'    => array( 'label' => 'Yardım kutusu başlığı', 'type' => 'text', 'default' => 'Bir sorun mu var?' ),
						'help_desc'     => array( 'label' => 'Yardım kutusu metni', 'type' => 'text', 'default' => 'Adres ya da fatura bilgisini değiştirmek için sipariş numaranızla bize yazın.' ),
						'help_button'   => array( 'label' => 'Yardım kutusu düğmesi', 'type' => 'text', 'default' => 'WhatsApp’tan yazın' ),
					),
				),
				'forms'  => array(
					'label'  => 'Talep Formları: ortak alanlar ve uyarılar (Çözüm Merkezi, Özel Üretim)',
					'fields' => array(
						'name_label'      => array( 'label' => 'Alan: ad soyad', 'type' => 'text', 'default' => 'Ad soyad' ),
						'company_label'   => array( 'label' => 'Alan: firma unvanı', 'type' => 'text', 'default' => 'Firma unvanı' ),
						'phone_label'     => array( 'label' => 'Alan: telefon', 'type' => 'text', 'default' => 'Telefon' ),
						'phone_hint'      => array( 'label' => 'Alan: telefon örnek yazısı', 'type' => 'text', 'default' => '05XX XXX XX XX' ),
						'email_label'     => array( 'label' => 'Alan: e-posta', 'type' => 'text', 'default' => 'E-posta' ),
						'optional'        => array( 'label' => 'İsteğe bağlı alan notu', 'type' => 'text', 'default' => '(isteğe bağlı)' ),
						'choose'          => array( 'label' => 'Liste: boş seçenek', 'type' => 'text', 'default' => 'Seçin' ),
						'product_label'   => array( 'label' => 'Alan: ürün (Çözüm Merkezi)', 'type' => 'text', 'default' => 'Ürün' ),
						'product_none'    => array( 'label' => 'Alan: ürün, boş seçenek', 'type' => 'text', 'default' => 'Belirli bir ürün değil' ),
						'prefill_contact' => array( 'label' => 'Üründen gelince mesaj alanına hazır yazı (Çözüm Merkezi)', 'type' => 'text', 'default' => '{urun} ({kategori}) için fiyat teklifi almak istiyorum. Ürün kodu: {kod}', 'hint' => 'Ürün sayfasındaki form düğmesiyle gelinince yazılır. {urun}, {kategori}, {kod}, {url} ürünün bilgileridir.' ),
						'prefill_custom'  => array( 'label' => 'Üründen gelince mesaj alanına hazır yazı (Özel Üretim)', 'type' => 'text', 'default' => '{urun} ({kategori}, ürün kodu {kod}) ürününü farklı ölçüde istiyorum. İstediğim ölçüler:', 'hint' => 'Ürün sayfasındaki "Özel üretim isteyin" bağlantısıyla gelinince yazılır. {urun}, {kategori}, {kod}, {url} ürünün bilgileridir.' ),
						'file_label'      => array( 'label' => 'Alan: dosya (Özel Üretim)', 'type' => 'text', 'default' => 'Teknik çizim ya da referans görsel' ),
						'file_help'       => array( 'label' => 'Alan: dosya açıklaması', 'type' => 'text', 'default' => 'En fazla 5 MB. PDF, JPG, PNG ya da DWG.' ),
						'consent'         => array( 'label' => 'KVKK onayı', 'type' => 'text', 'default' => '{kvkk}’ni okudum; kişisel verilerimin bu talep için işlenmesini kabul ediyorum.', 'hint' => '{kvkk} aşağıdaki bağlantı yazısıdır; silmeyin.' ),
						'consent_link'    => array( 'label' => 'KVKK onayı: bağlantı', 'type' => 'text', 'default' => 'KVKK Aydınlatma Metni' ),
						'thanks'          => array( 'label' => 'Gönderildi kutusu: ilk kelime', 'type' => 'text', 'default' => 'Teşekkürler.' ),
						'errors_count'    => array( 'label' => 'Hata kutusu', 'type' => 'text', 'default' => 'Formda düzeltilmesi gereken {sayi} alan var; aşağıda kırmızıyla işaretli.', 'hint' => '{sayi} hatalı alan sayısıdır; silmeyin.' ),
						'err_nonce'       => array( 'label' => 'Uyarı: form süresi doldu', 'type' => 'text', 'default' => 'Form oturumu zaman aşımına uğradı. Lütfen tekrar gönderin.' ),
						'err_rate'        => array( 'label' => 'Uyarı: çok sık gönderim', 'type' => 'text', 'default' => 'Az önce bir talep gönderdiniz; lütfen biraz sonra yeniden deneyin.' ),
						'err_name'        => array( 'label' => 'Uyarı: ad soyad', 'type' => 'text', 'default' => 'Adınızı ve soyadınızı yazın.' ),
						'err_email'       => array( 'label' => 'Uyarı: e-posta boş', 'type' => 'text', 'default' => 'E-posta adresinizi yazın.' ),
						'err_email_bad'   => array( 'label' => 'Uyarı: e-posta geçersiz', 'type' => 'text', 'default' => 'E-posta adresi geçerli görünmüyor (ör. ad@alanadi.com).' ),
						'err_phone'       => array( 'label' => 'Uyarı: telefon', 'type' => 'text', 'default' => 'Telefon numaranızı alan koduyla yazın (ör. 0532 123 45 67).' ),
						'err_department'  => array( 'label' => 'Uyarı: departman (Çözüm Merkezi)', 'type' => 'text', 'default' => 'Talebinizin ilgili olduğu departmanı seçin.' ),
						'err_category'    => array( 'label' => 'Uyarı: proje kategorisi (Özel Üretim)', 'type' => 'text', 'default' => 'Proje kategorisini seçin.' ),
						'err_message'     => array( 'label' => 'Uyarı: proje detayları (Özel Üretim)', 'type' => 'text', 'default' => 'Projenizi birkaç cümleyle anlatın: yaklaşık ölçü, malzeme, kullanım yeri.' ),
						'err_consent'     => array( 'label' => 'Uyarı: KVKK onayı', 'type' => 'text', 'default' => 'Devam etmek için KVKK Aydınlatma Metni’ni onaylayın.' ),
						'err_save'        => array( 'label' => 'Uyarı: kaydedilemedi', 'type' => 'text', 'default' => 'Kayıt sırasında bir sorun oldu. Lütfen WhatsApp’tan ulaşın.' ),
						'err_file_size'   => array( 'label' => 'Uyarı: dosya çok büyük', 'type' => 'text', 'default' => 'Dosya 5 MB’tan büyük. Daha küçük bir dosya seçin ya da WhatsApp’tan gönderin.' ),
						'err_file_upload' => array( 'label' => 'Uyarı: dosya yüklenemedi', 'type' => 'text', 'default' => 'Dosya yüklenemedi. Tekrar deneyin ya da WhatsApp’tan gönderin.' ),
						'err_file_type'   => array( 'label' => 'Uyarı: dosya türü', 'type' => 'text', 'default' => 'Yalnızca PDF, JPG, PNG ya da DWG dosyası yükleyebilirsiniz.' ),
						'err_file_save'   => array( 'label' => 'Uyarı: dosya kaydedilemedi', 'type' => 'text', 'default' => 'Dosya kaydedilemedi. Tekrar deneyin ya da WhatsApp’tan gönderin.' ),
					),
				),
				'shop'   => array(
					'label'  => 'Mağaza Ayarları (sepet ve ödeme)',
					'fields' => array(
						'vat_mode'       => array( 'label' => 'Fiyatlar KDV: "dahil" ya da "haric"', 'type' => 'text', 'default' => 'dahil', 'hint' => 'Ürün Havuzu’ndaki fiyatlar KDV’yi içeriyorsa "dahil" (woodkocist.com.tr’de fiyatlar KDV dahil). "haric" yazılırsa sepette KDV ayrıca eklenir.' ),
						'vat_rate'       => array( 'label' => 'KDV Oranı (%)', 'type' => 'text', 'default' => '20' ),
						'shipping_label' => array( 'label' => 'Kargo Satırında Yazan', 'type' => 'text', 'default' => 'Ücretsiz' ),
						'shipping_note'  => array( 'label' => 'Teslimat Notu (ürün sayfası ve sepet)', 'type' => 'text', 'default' => 'Küçük ürünler anlaşmalı lojistikle, büyük ve kurulum gerektiren ürünler saha ekibimizle gelir.' ),
						'lead_time'      => array( 'label' => 'Üretim ve Teslim Süresi', 'type' => 'text', 'default' => 'Standart modellerde üretim ve kalite kontrol ortalama 7–14 iş günü sürer.' ),
						'pickup_address' => array( 'label' => 'Fabrikadan Teslim Adresi', 'type' => 'textarea', 'default' => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul' ),
						'payment_text'   => array( 'label' => 'Havale / EFT Açıklaması', 'type' => 'textarea', 'default' => 'Ödemenizi doğrudan şirketimizin banka hesabına yapın. Lütfen ilgili sipariş numarasını ödeme açıklamanızda belirtin. Siparişiniz, ödemeniz onaylandıktan sonra üretim programına alınacaktır.' ),
						'bank_accounts'  => array( 'label' => 'Banka Hesapları (sipariş onayında görünür)', 'type' => 'textarea', 'default' => '', 'hint' => 'Her satıra bir bilgi: Banka adı, hesap sahibi, IBAN. Boşsa onay sayfası "hesap bilgilerini size ileteceğiz" der.' ),
					),
				),
			),
		),

		'home' => array(
			'label'      => 'Ana Sayfa',
			'path'       => '/',
			'seo_source' => array(
				'title'       => 'hero.title',
				'description' => 'hero.lead',
			),
			'components' => array(
				'hero' => array(
					'label'  => 'Giriş',
					'fields' => array(
						'title'     => array( 'label' => 'Başlık (iki satır: satır sonuyla ayırın)', 'type' => 'textarea', 'default' => "Doğanın Estetiği\nMühendisliğin Kusursuzluğu" ),
						'lead'      => array( 'label' => 'Alt Metin', 'type' => 'textarea', 'default' => 'Yarım asırlık Koçist® tecrübesiyle, yaşam alanlarınızı zamansız tasarımlarla buluşturuyoruz. Koleksiyonumuzu keşfedin veya mimari projeleriniz için bizimle tasarım sürecini başlatın.' ),
						'cta'       => array( 'label' => 'Ana Düğme', 'type' => 'text', 'default' => 'Koleksiyonu keşfet' ),
						'cta_alt'   => array( 'label' => 'İkinci Düğme (Özel Üretim)', 'type' => 'text', 'default' => 'Özel üretim isteyin' ),
					),
				),
				'catalog' => array(
					'label'  => 'Seriler',
					'fields' => array(
						'lines' => array(
							'label'   => 'Seriler (menü, ana sayfa kutuları, mağaza)',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'label'    => array( 'label' => 'Seri Adı', 'type' => 'text' ),
								'category' => array( 'label' => 'Seri anahtarı (değiştirmeyin)', 'type' => 'text', 'hint' => 'Ürün Havuzu’ndaki seri kategorisinin adı (WOODPets gibi). Kategoriler bu anahtarla serinin altına yerleşir; değiştirirseniz bu serideki kategoriler menüden ve mağazadan düşer. Görünen adı "Seri Adı" alanından değiştirin.' ),
								'text'     => array( 'label' => 'Kısa Açıklama', 'type' => 'text' ),
							),
							'default' => array(
								array( 'label' => 'WOODPets', 'category' => 'WOODPets', 'text' => 'Köpek kulübeleri ve kedi yuvaları' ),
								array( 'label' => 'WOODGarden', 'category' => 'WOODGarden', 'text' => 'Kamelyalar, çardaklar, piknik masaları ve şezlonglar' ),
								array( 'label' => 'WOODLiving', 'category' => 'WOODLiving', 'text' => 'Adirondack sandalyeler' ),
							),
						),
					),
				),
				'rows' => array(
					'label'  => 'Ürün Satırları',
					'fields' => array(
						'items' => array(
							'label'   => 'Satırlar (kategori adı havuzdakiyle birebir)',
							'type'    => 'repeater',
							'max'     => 8,
							'fields'  => array(
								'title'    => array( 'label' => 'Başlık', 'type' => 'text' ),
								'category' => array( 'label' => 'Havuzdaki Kategori Adı', 'type' => 'text' ),
							),
							'default' => array(
								array( 'title' => 'Ahşap Kamelyalar', 'category' => 'Kamelyalar' ),
								array( 'title' => 'Adirondack Sandalyeler', 'category' => 'Adirondack' ),
								array( 'title' => 'Ahşap Köpek Kulübeleri', 'category' => 'Köpek Kulübeleri' ),
								array( 'title' => 'Ahşap Çardaklar', 'category' => 'Çardaklar' ),
								array( 'title' => 'Ahşap Kedi Yuvaları', 'category' => 'Kedi Yuvaları' ),
							),
						),
					),
				),
				'banner' => array(
					'label'  => 'Tanıtım Bandı',
					'fields' => array(
						'title'    => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'Bahçenize değer katan, her mevsim koşuluna dayanıklı el işçiliği lüks ahşap kamelya ve çardak tasarımları.' ),
						'button'   => array( 'label' => 'Düğme', 'type' => 'text', 'default' => 'Kamelyaları inceleyin' ),
						'category' => array( 'label' => 'Düğmenin Açtığı Kategori (havuzdaki ad)', 'type' => 'text', 'default' => 'Kamelyalar' ),
					),
				),
				'steps' => array(
					'label'  => 'Nasıl Sipariş Verilir',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Nasıl sipariş verilir' ),
						'items' => array(
							'label'   => 'Adımlar',
							'type'    => 'repeater',
							'max'     => 4,
							'fields'  => array(
								'title' => array( 'label' => 'Adım', 'type' => 'text' ),
								'text'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Ürünü sepete ekleyin', 'text' => 'Ürün sayfasında adedi seçip “Sepete ekle”ye basın. Fiyatı yazmayan ürünleri WhatsApp’tan sorun.' ),
								array( 'title' => 'Teslimat bilgilerinizi girin', 'text' => 'Üyelik gerekmez: ad, telefon, e-posta ve adres yeterli. İsterseniz fabrikadan teslim alın.' ),
								array( 'title' => 'Havale / EFT ile ödeyin', 'text' => 'Sipariş numaranızı açıklamaya yazın. Ödeme onaylanınca siparişiniz üretim programına alınır.' ),
							),
						),
					),
				),
				'about' => array(
					'label'  => 'Marka Bölümü',
					'fields' => array(
						'title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'WOOD Koçist Orman Ürünleri' ),
						'text'  => array( 'label' => 'Metin', 'type' => 'textarea', 'default' => 'Sadece kendi evlerimizde ve bahçelerimizde görmek isteyeceğimiz, zanaatına ve kalitesine bizzat güvendiğimiz özel tasarımları üretiyoruz. Değerli ahşap yapıların taşımacılığında derin bir tecrübeye sahibiz; ürünlerimizi her yere korumalı ve güvenli nakliye standartlarıyla ulaştırıyoruz.' ),
						'items' => array(
							'label'   => 'Öne Çıkanlar',
							'type'    => 'repeater',
							'max'     => 4,
							'fields'  => array(
								'text' => array( 'label' => 'Metin', 'type' => 'text' ),
							),
							'default' => array(
								array( 'text' => '50 Yıllık Tecrübe' ),
								array( 'text' => 'Birinci Sınıf İşçilik' ),
								array( 'text' => 'Yüksek Kalite' ),
							),
						),
					),
				),
				'sections' => array(
					'label'  => 'Bölüm Başlıkları ve Bağlantılar',
					'fields' => array(
						'serie_suffix' => array( 'label' => 'Seri kutusu: seri adından sonra', 'type' => 'text', 'default' => 'Serisi' ),
						'serie_more'   => array( 'label' => 'Seri kutusu: bağlantı', 'type' => 'text', 'default' => '{sayi} ürünü görüntüle', 'hint' => '{sayi} serideki ürün adedidir; silmeyin.' ),
						'cats_title'   => array( 'label' => 'Kategoriler başlığı', 'type' => 'text', 'default' => 'Kategoriler' ),
						'cats_all'     => array( 'label' => 'Kategoriler: tüm ürünler bağlantısı', 'type' => 'text', 'default' => 'Tüm ürünler' ),
						'row_all'      => array( 'label' => 'Ürün satırı: tümünü gör bağlantısı', 'type' => 'text', 'default' => 'Tümünü gör ({sayi})', 'hint' => '{sayi} kategorideki ürün adedidir; silmeyin.' ),
						'link_story'   => array( 'label' => 'Marka bölümü: 1. bağlantı', 'type' => 'text', 'default' => 'Hikayemiz' ),
						'link_faq'     => array( 'label' => 'Marka bölümü: 2. bağlantı', 'type' => 'text', 'default' => 'Sıkça sorulan sorular' ),
						'link_contact' => array( 'label' => 'Marka bölümü: 3. bağlantı', 'type' => 'text', 'default' => 'Çözüm Merkezi' ),
					),
				),
			),
		),

		'shop' => array(
			'label'      => 'Mağaza',
			'path'       => '/magaza/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => $head( 'Mağaza', 'Kamelya, çardak, Adirondack sandalye, piknik masası, şezlong, köpek kulübesi ve kedi yuvası. Fiyatlar KDV dahil; üyelik gerekmeden sipariş verin.' ),
				'pool' => array(
					'label'  => 'Tüm Ürünler',
					'fields' => array(
						'pool' => array(
							'label' => 'Sitede görünen ürünler (Ürün Havuzu’ndan seçin, sıralayın)',
							'type'  => 'products',
						),
					),
				),
				'list' => array(
					'label'  => 'Liste, Kenar Kutusu ve Arama Yazıları (kategori sayfalarında da)',
					'fields' => array(
						'heading'         => array( 'label' => 'Sayfada görünen başlık', 'type' => 'text', 'default' => 'Mağaza', 'hint' => 'Arama motorundaki başlık yukarıdaki "Başlık" alanından gelir.' ),
						'search_heading'  => array( 'label' => 'Arama sonuçları başlığı', 'type' => 'text', 'default' => 'Arama sonuçları' ),
						'filter_title'    => array( 'label' => 'Kenar: kategoriler başlığı', 'type' => 'text', 'default' => 'Kategoriler' ),
						'all_label'       => array( 'label' => 'Kenar: tüm ürünler', 'type' => 'text', 'default' => 'Tüm ürünler' ),
						'help_title'      => array( 'label' => 'Yardım kutusu: başlık', 'type' => 'text', 'default' => 'Aradığınızı bulamadınız mı?' ),
						'help_desc'       => array( 'label' => 'Yardım kutusu: metin', 'type' => 'textarea', 'default' => 'Ölçüye özel üretim yapıyoruz. Ürün kodunu ya da ihtiyacınızı yazın, fiyatla dönelim.' ),
						'help_button'     => array( 'label' => 'Yardım kutusu: düğme', 'type' => 'text', 'default' => 'WhatsApp’tan sorun' ),
						'search_for'      => array( 'label' => 'Arama: sonuç satırının başı', 'type' => 'text', 'default' => '“{arama}” için', 'hint' => '{arama} ziyaretçinin yazdığı kelimedir; silmeyin.' ),
						'clear_search'    => array( 'label' => 'Arama: temizle bağlantısı', 'type' => 'text', 'default' => 'Aramayı temizle' ),
						'sort_label'      => array( 'label' => 'Sıralama etiketi', 'type' => 'text', 'default' => 'Sırala' ),
						'sort_default'    => array( 'label' => 'Sıralama: önerilen', 'type' => 'text', 'default' => 'Önerilen sıra' ),
						'sort_price_asc'  => array( 'label' => 'Sıralama: fiyat artan', 'type' => 'text', 'default' => 'Fiyat: düşükten yükseğe' ),
						'sort_price_desc' => array( 'label' => 'Sıralama: fiyat azalan', 'type' => 'text', 'default' => 'Fiyat: yüksekten düşüğe' ),
						'sort_name'       => array( 'label' => 'Sıralama: ürün adı', 'type' => 'text', 'default' => 'Ürün adına göre' ),
						'sort_apply'      => array( 'label' => 'Sıralama: uygula düğmesi (JavaScript kapalıyken)', 'type' => 'text', 'default' => 'Uygula' ),
						'empty_title'     => array( 'label' => 'Sonuç yok: başlık', 'type' => 'text', 'default' => 'Bu aramayla eşleşen ürün yok.' ),
						'empty_desc'      => array( 'label' => 'Sonuç yok: açıklama', 'type' => 'textarea', 'default' => 'Ürün kodunu (ör. W-KAM-300) ya da ürün türünü (kamelya, çardak, kulübe) yazmayı deneyin.' ),
						'empty_button'    => array( 'label' => 'Sonuç yok: düğme', 'type' => 'text', 'default' => 'Tüm ürünleri göster' ),
						'faq_title'       => array( 'label' => 'Kategori sayfası: soru-cevap başlığı (ürünlerin altında)', 'type' => 'text', 'default' => 'Sık sorulan sorular', 'hint' => 'Soruların kendisi Ürün Havuzu › Kategoriler › kategori › “Sık sorulan sorular”dan yazılır; sorusu olmayan kategoride bu bölüm görünmez.' ),
					),
				),
			),
		),
	) + array(

		'about' => array(
			'label'      => 'Hakkımızda (Şirketimiz)',
			'path'       => '/sirketimiz/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'story.intro',
			),
			'components' => array(
				'head'  => $head( 'Doğanın dokusunu 50 yıllık tecrübeyle şekillendiriyoruz', 'Yarım asırlık Koçist® güvencesine hoş geldiniz.' ),
				'story' => array(
					'label'  => 'Hikayemiz',
					'fields' => array(
						'intro' => array( 'label' => 'Giriş', 'type' => 'textarea', 'default' => 'Koçist Grup olarak, orman ürünleri sektöründeki 50 yıllık köklü geçmişimizle, doğanın bize sunduğu değeri yaşam alanlarınızı güzelleştiren estetik dokunuşlara dönüştürüyoruz.' ),
						'items' => array(
							'label'   => 'Öne Çıkanlar',
							'type'    => 'repeater',
							'max'     => 4,
							'fields'  => array(
								'text' => array( 'label' => 'Metin', 'type' => 'text' ),
							),
							'default' => array(
								array( 'text' => '50 Yıllık Tecrübe' ),
								array( 'text' => 'Yüksek Standartlar' ),
								array( 'text' => 'Birinci Sınıf İşçilik' ),
							),
						),
						'title' => array( 'label' => 'İkinci Başlık', 'type' => 'text', 'default' => 'İhtiyacınıza özel uçtan uca üretim süreci' ),
						'text'  => array( 'label' => 'Metin (paragrafları boş satırla ayırın)', 'type' => 'textarea', 'default' => "Endüstriyel ahşap uzmanlığımızı bir adım öteye taşıyarak; ahşabın sıcaklığını yansıtan modern dekorasyon ürünleri ve sadık dostlarımız için köpek kulübeleri gibi özel tasarım ahşap yapılar üretiyoruz.\n\nAmacımız, yarım asırlık tecrübemiz ve işçiliğimizle, ilkelerimizden taviz vermeden ahşabın en doğal ve şık halini sizlere sunmaktır.\n\nKereste ve endüstriyel ambalaj çözümlerimizin yanı sıra; evinize, bahçenize ve evcil hayvanlarınıza özel ahşap projeleri hayata geçiriyoruz. Standart ölçülerin dışına çıkarak, tamamen sizin taleplerinize ve alanınıza uygun dekoratif ahşap yapılar dizayn ediyor, uzman ekibimizle en hızlı şekilde üreterek güvenle adresinize sevk ediyoruz." ),
					),
				),
				'cta'   => array(
					'label'  => 'Düğmeler',
					'fields' => array(
						'shop_button'   => array( 'label' => 'Birinci düğme (mağaza)', 'type' => 'text', 'default' => 'Koleksiyonu keşfet' ),
						'custom_button' => array( 'label' => 'İkinci düğme (özel üretim)', 'type' => 'text', 'default' => 'Özel üretim isteyin' ),
					),
				),
			),
		),

		'faq' => array(
			'label'      => 'Sıkça Sorulan Sorular',
			'path'       => '/sss/',
			'seo_source' => array(
				'type'        => 'FAQPage',
				'questions'   => 'items.rows',
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head'  => $head( 'Sıkça sorulan sorular', 'Üretim ve malzeme, özel üretim, nakliye ve kurulum, sipariş ve ödeme, iade ve garanti hakkında en çok sorulanlar.' ),
				'items' => array(
					'label'  => 'Sorular',
					'fields' => array(
						'rows' => array(
							'label'   => 'Soru ve Cevaplar (aynı grup adı bir başlık altında toplanır)',
							'type'    => 'repeater',
							'max'     => 40,
							'fields'  => array(
								'group'    => array( 'label' => 'Grup', 'type' => 'text' ),
								'question' => array( 'label' => 'Soru', 'type' => 'text' ),
								'answer'   => array( 'label' => 'Cevap', 'type' => 'textarea' ),
							),
							'default' => require __DIR__ . '/content/faq.php',
						),
					),
				),
				'help'  => array(
					'label'  => 'Yardım Kutusu ve Konular',
					'fields' => array(
						'group_other'    => array( 'label' => 'Grubu boş sorular için grup adı', 'type' => 'text', 'default' => 'Diğer' ),
						'help_title'     => array( 'label' => 'Yardım kutusu: başlık', 'type' => 'text', 'default' => 'Sorunuzun cevabı burada yok mu?' ),
						'help_desc'      => array( 'label' => 'Yardım kutusu: metin', 'type' => 'textarea', 'default' => 'Çözüm Merkezi’ne yazın ya da WhatsApp’tan sorun; ilgili ekip size dönsün.' ),
						'contact_button' => array( 'label' => 'Yardım kutusu: birinci düğme', 'type' => 'text', 'default' => 'Çözüm Merkezi’ne yazın' ),
						'wa_button'      => array( 'label' => 'Yardım kutusu: WhatsApp düğmesi', 'type' => 'text', 'default' => 'WhatsApp' ),
						'topics'         => array( 'label' => 'Yan liste başlığı', 'type' => 'text', 'default' => 'Konular' ),
					),
				),
			),
		),

		'contact' => array(
			'label'      => 'Çözüm Merkezi (İletişim)',
			'path'       => '/iletisim/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head'    => $head( 'Çözüm Merkezi', 'Sorularınız, özel üretim talepleriniz veya kurumsal iş birlikleri için bize ulaşın; talebiniz ilgili departmana iletilir ve en kısa sürede dönüş yapılır.' ),
				'details' => array(
					'label'  => 'Bilgiler',
					'fields' => array(
						'lines'   => array(
							'label'   => 'Adresler',
							'type'    => 'repeater',
							'max'     => 5,
							'fields'  => array(
								'label' => array( 'label' => 'Başlık', 'type' => 'text' ),
								'value' => array( 'label' => 'Adres', 'type' => 'text' ),
							),
							'default' => array(
								array( 'label' => 'Adres (fabrika ve showroom)', 'value' => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul' ),
							),
						),
						'map'     => array( 'label' => 'Harita Araması (boşsa harita yok)', 'type' => 'text', 'default' => 'Koçist Orman Ürünleri' ),
					),
				),
				'labels'  => array(
					'label'  => 'Hat Başlıkları, Bağlantılar ve Form Başlığı',
					'fields' => array(
						'wa_line'       => array( 'label' => 'WhatsApp hattı başlığı', 'type' => 'text', 'default' => 'Müşteri destek hattı ve WhatsApp' ),
						'phone_line'    => array( 'label' => 'Kurumsal hat başlığı', 'type' => 'text', 'default' => 'Kurumsal iletişim hattı' ),
						'email_line'    => array( 'label' => 'E-posta başlığı', 'type' => 'text', 'default' => 'E-posta' ),
						'quick_faq'     => array( 'label' => 'Hızlı bağlantı 1 (SSS)', 'type' => 'text', 'default' => 'Sıkça sorulan sorular' ),
						'quick_returns' => array( 'label' => 'Hızlı bağlantı 2 (iptal ve iade)', 'type' => 'text', 'default' => 'Kargo, iptal ve iade' ),
						'quick_payment' => array( 'label' => 'Hızlı bağlantı 3 (ödeme ve teslimat)', 'type' => 'text', 'default' => 'Ödeme ve teslimat' ),
						'quick_custom'  => array( 'label' => 'Hızlı bağlantı 4 (özel üretim)', 'type' => 'text', 'default' => 'Özel üretim talep formu' ),
						'form_title'    => array( 'label' => 'Form başlığı', 'type' => 'text', 'default' => 'Talebinizi yazın' ),
						'form_desc'     => array( 'label' => 'Form açıklaması', 'type' => 'text', 'default' => 'Talebiniz ilgili departmana iletilir; en kısa sürede dönüş yapılır.' ),
					),
				),
				'request' => array(
					'label'  => 'Form Alanları ve Düğme',
					'fields' => array(
						'select_label' => array( 'label' => 'Liste başlığı', 'type' => 'text', 'default' => 'İlgili departman' ),
						'options'      => array( 'label' => 'Liste seçenekleri (her satıra bir seçenek)', 'type' => 'textarea', 'default' => "Genel Bilgi ve Destek\nÖzel Ölçü ve Mimari Proje Talebi\nKurumsal Satış ve Bayilik\nSatış Sonrası Hizmetler" ),
						'message_label' => array( 'label' => 'Mesaj alanı başlığı', 'type' => 'text', 'default' => 'Mesajınız (isteğe bağlı)' ),
						'message_hint' => array( 'label' => 'Mesaj alanı örnek yazısı', 'type' => 'text', 'default' => 'Ürün kodu, adet, özel ölçü ya da merak ettiğiniz diğer detaylar…' ),
						'button'       => array( 'label' => 'Gönder düğmesi', 'type' => 'text', 'default' => 'Talebi ilet' ),
						'success'      => array( 'label' => 'Gönderildi mesajı', 'type' => 'textarea', 'default' => 'Talebiniz ilgili departmana iletildi. En kısa sürede size dönüş yapacağız.' ),
					),
				),
			),
		),

		'custom' => array(
			'label'      => 'Özel Üretim',
			'path'       => '/ozel-uretim-talep-formu/',
			'seo_source' => array(
				'title'       => 'head.title',
				'description' => 'head.lead',
			),
			'components' => array(
				'head' => $head( 'Mekânlarınıza özel mimari ustalık ve mühendislik çözümleri', 'Koçist Grup’un yarım asırlık orman ürünleri tecrübesiyle, hayallerinizdeki yapıyı projelendiriyor, doğanın estetiğini mühendislik hassasiyetiyle birleştiriyoruz.' ),
				'form' => array(
					'label'  => 'Form',
					'fields' => array(
						'title' => array( 'label' => 'Form Başlığı', 'type' => 'text', 'default' => 'Özel proje talep formu' ),
						'lead'  => array( 'label' => 'Form Açıklaması', 'type' => 'textarea', 'default' => 'Mekânınızın teknik gereksinimlerini ve mimari beklentilerinizi bizimle paylaşın; uzman mühendislik ekibimiz projenizi hayata geçirmek üzere sizinle iletişime geçsin.' ),
					),
				),
				'process' => array(
					'label'  => 'Süreç',
					'fields' => array(
						'process_title' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Süreç nasıl ilerliyor?' ),
						'steps'         => array(
							'label'   => 'Adımlar',
							'type'    => 'repeater',
							'max'     => 6,
							'fields'  => array(
								'title' => array( 'label' => 'Adım', 'type' => 'text' ),
								'desc'  => array( 'label' => 'Açıklama', 'type' => 'textarea' ),
							),
							'default' => array(
								array( 'title' => 'Talebinizi paylaşın.', 'desc' => 'Ölçüleri, kullanım yerini ve varsa çizim ya da fotoğrafı formla gönderin.' ),
								array( 'title' => 'Projelendirme.', 'desc' => 'Mühendislik ve mimari ekibimiz statik ve estetik dengeyi hesaplayıp üç boyutlu modeli hazırlar.' ),
								array( 'title' => 'Onay ve fiyat.', 'desc' => 'Tüm maliyet tablosu onaydan önce size sunulur; sonradan ek ödeme çıkmaz.' ),
								array( 'title' => 'Üretim ve teslim.', 'desc' => 'Onaylanan tasarım üretime alınır, her aşamada bilgilendirilirsiniz.' ),
							),
						),
						'returns_note'  => array( 'label' => 'Alt not', 'type' => 'text', 'default' => 'Özel ölçüyle üretilen ürünlerde yasal cayma hakkı yoktur; ayrıntılar {baglanti}.', 'hint' => '{baglanti} aşağıdaki bağlantı yazısıdır; silmeyin.' ),
						'returns_link'  => array( 'label' => 'Alt not: bağlantı', 'type' => 'text', 'default' => 'iptal ve iade koşullarında' ),
					),
				),
				'request' => array(
					'label'  => 'Form Alanları ve Düğme',
					'fields' => array(
						'select_label' => array( 'label' => 'Liste başlığı', 'type' => 'text', 'default' => 'Proje kategorisi' ),
						'options'      => array( 'label' => 'Liste seçenekleri (her satıra bir seçenek)', 'type' => 'textarea', 'default' => "Kamelya & Çardak\nAhşap Ev & Kabin\nOturma Grubu\nEvcil Hayvan Yuvası\nDiğer Mimari Projeler" ),
						'message_label' => array( 'label' => 'Mesaj alanı başlığı', 'type' => 'text', 'default' => 'Proje detayları ve beklentileriniz' ),
						'message_hint' => array( 'label' => 'Mesaj alanı örnek yazısı', 'type' => 'text', 'default' => 'Mekânın yaklaşık ölçüleri, malzeme tercihleriniz ve projenize dair teknik beklentileriniz…' ),
						'button'       => array( 'label' => 'Gönder düğmesi', 'type' => 'text', 'default' => 'Talebi gönder' ),
						'success'      => array( 'label' => 'Gönderildi mesajı', 'type' => 'textarea', 'default' => 'Proje talebiniz bize ulaştı. Ekibimiz ölçü ve detaylar için sizinle iletişime geçecek.' ),
					),
				),
			),
		),

		/*
		 * Havuz urunlerinin sayfalari (/urun/<urun>/): tum urunlerde ortak
		 * yazilar. Urunun kendi adi, kodu, fiyati ve metni Urun Havuzu'nda.
		 * Sekme listesinde gorunmez; "Sayfa bul" kutusunda her urun cikar.
		 */
		'product' => array(
			'label'      => 'Ürün Sayfaları (tüm ürünler)',
			'path'       => '/urun/',
			'hidden'     => true,
			'components' => array(
				'labels' => array(
					'label'  => 'Ürün Sayfası Yazıları',
					'fields' => array(
						'code_label'      => array( 'label' => 'Ürün kodu etiketi', 'type' => 'text', 'default' => 'Ürün kodu' ),
						'form_ask'        => array( 'label' => 'Fiyatsız üründe form düğmesi', 'type' => 'text', 'default' => 'Formla sor' ),
						'custom_question' => array( 'label' => 'Özel ölçü satırı', 'type' => 'text', 'default' => 'Farklı ölçü mü lazım?' ),
						'custom_link'     => array( 'label' => 'Özel ölçü satırı: bağlantı', 'type' => 'text', 'default' => 'Özel üretim isteyin' ),
						'wa_order'        => array( 'label' => 'Hızlı Sipariş düğmesi (WhatsApp)', 'type' => 'text', 'default' => 'Hızlı Sipariş', 'hint' => 'WhatsApp mesajına ürünün adı ve kodu kendiliğinden eklenir.' ),
						'specs_title'     => array( 'label' => '1. sekme: teknik detaylar', 'type' => 'text', 'default' => 'Teknik Detaylar' ),
						'specs_empty'     => array( 'label' => '1. sekme: detay girilmemişse', 'type' => 'text', 'default' => 'Bu ürünün teknik detayları henüz girilmedi. Ölçü ve malzeme bilgisi için bize yazın.' ),
						'tab_desc'        => array( 'label' => '2. sekme: ürün açıklaması', 'type' => 'text', 'default' => 'Ürün Açıklaması' ),
						'desc_empty'      => array( 'label' => '2. sekme: açıklama girilmemişse', 'type' => 'text', 'default' => 'Açıklama henüz girilmedi.' ),
						'tab_delivery'    => array( 'label' => '3. sekme: lojistik ve teslimat', 'type' => 'text', 'default' => 'Lojistik ve Teslimat' ),
						'delivery_text'   => array( 'label' => '3. sekme: metin (tüm ürünler)', 'type' => 'textarea', 'default' => "Küçük ürünler anlaşmalı lojistikle, büyük ve kurulum gerektiren ürünler saha ekibimizle gelir. Standart modellerde üretim ve kalite kontrol ortalama 7–14 iş günü sürer.\n\nİsterseniz ürününüzü fabrikamızdan teslim alabilirsiniz.", 'hint' => 'Üründe "Lojistik ve Teslimat" adlı bir detay satırı varsa o ürünün sayfasında bu metin yerine o satır gösterilir.' ),
						'tab_reviews'     => array( 'label' => '4. sekme: müşteri görüşleri', 'type' => 'text', 'default' => 'Müşteri Görüşleri' ),
						'reviews_empty'   => array( 'label' => '4. sekme: görüş yokken', 'type' => 'text', 'default' => 'Henüz değerlendirme yapılmadı.' ),
						'reviews_note'    => array( 'label' => '4. sekme: açıklama', 'type' => 'text', 'default' => 'Görüşünüzü WhatsApp’tan ya da formla iletebilirsiniz.' ),
						'related_title'   => array( 'label' => 'İlgili ürünler başlığı', 'type' => 'text', 'default' => 'İlgili ürünler', 'hint' => 'İsterseniz {kategori} yazın: ürünün kategorisi (küçük harfle) gelir.' ),
						'related_all'     => array( 'label' => 'İlgili ürünler başlığı (kategorisiz ürün)', 'type' => 'text', 'default' => 'İlgili ürünler' ),
						'faq_title'       => array( 'label' => 'Sekmelerin altı: kategori soruları başlığı', 'type' => 'text', 'default' => 'Sık sorulan sorular', 'hint' => 'Ürünün kategorisinde soru varsa ilk üçü sekmelerin altında görünür (Ürün Havuzu › Kategoriler › “Sık sorulan sorular”).' ),
						'faq_all'         => array( 'label' => 'Sekmelerin altı: kategorinin bütün sorularına bağlantı', 'type' => 'text', 'default' => 'Tüm sorular' ),
					),
				),
			),
		),

		// Bulunamayan sayfa (404). Sekme listesinde gorunmez; "Sayfa bul" kutusunda cikar.
		'notfound' => array(
			'label'      => 'Bulunamayan Sayfa (404)',
			'path'       => '/bulunamadi-404/',
			'hidden'     => true,
			'components' => array(
				'head' => array(
					'label'  => 'Metin',
					'fields' => array(
						'heading' => array( 'label' => 'Başlık', 'type' => 'text', 'default' => 'Bu sayfa bulunamadı' ),
						'message' => array( 'label' => 'Açıklama', 'type' => 'textarea', 'default' => 'Ürün kaldırılmış ya da adresi değişmiş olabilir. Ürünlere göz atın ya da WhatsApp’tan sorun.' ),
						'button'  => array( 'label' => 'Düğme', 'type' => 'text', 'default' => 'Ürünlere dön' ),
					),
				),
			),
		),
	),
);
