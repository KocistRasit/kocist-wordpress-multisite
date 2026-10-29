<?php
/**
 * kavakkeresteci alan manifesti.
 *
 * Alan yapisi ana temadan (kereste-base/manifest.php) gelir; burada yalnizca
 * bu siteye ozgu varsayilan metinler, urunler ve ana sayfa modulleri var.
 * ithalkeresteci'den farki: "toptan tedarik" bandi yerine "kullanim alanlari".
 *
 * Metinler kocist.com.tr'deki kavak kereste, cita, takoz ve OSB sayfalarindan
 * turetildi; 29 Ekim 2026'da kocist.com.tr ile ayni cumleler kalmasin diye
 * (kopya icerik) yeniden yazildi. Olcu listesi saticidan gelecek.
 * Butun urunlerde istenen ebatta ozel kesim var (firma onayi, 29 Ekim 2026). Adres, telefon ve e-posta kocist.com.tr'de
 * yazdigi gibidir (sahibi SEO ve GEO sekmesinden duzeltir).
 */

$build = require dirname( __DIR__ ) . '/kereste-base/manifest.php';

$address = "Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1\nÇatalca, İstanbul";

return $build(
	array(
		'key'            => 'kavakkeresteci',
		'label'          => 'Koçist · kavakkeresteci.com',
		'logo'           => array( 'Kavak', 'Keresteci' ),

		'contact'        => array(
			// Koçist ve WOOD KOCIST disindaki siteler: sabit hat + cep (WhatsApp).
			'phone_label'  => '0212 648 10 90',
			'phone_url'    => 'tel:+902126481090',
			'mobile_label' => '0532 374 98 32',
			'mobile_url'   => 'tel:+905323749832',
			'whatsapp_url' => 'https://wa.me/905323749832',
			'email'        => 'info@kocist.com.tr',
			'address'      => $address,
			'hours'        => 'Pzt–Cmt 08:00–19:00 · Pazar 10:00–19:00',
			'map_url'      => 'https://maps.app.goo.gl/goTd8wPiXnbDb8BY9', // KOÇİST Kereste, Orman Ürünleri ve İnşaat Malzemeleri
		),

		// Arama verisi; kocist.com.tr ile ayni saatler. Gorunen metin: contact.hours.
		'opening_hours'  => array(
			array( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ), '08:00', '19:00' ),
			array( array( 'Sunday' ), '10:00', '19:00' ),
		),

		'footer_tagline' => 'Kavak kereste, çıta, ahşap takoz ve OSB levha. Ambalaj, palet ve mobilya üretimine uygun; toptan alımda Koçist Orman Ürünleri güvencesiyle.',

		'family'         => array(
			array( 'label' => 'İthal Keresteci', 'note' => 'İthal kereste, kalas, plywood', 'url' => 'https://ithalkeresteci.com' ),
			array( 'label' => 'Koçist Orman Ürünleri', 'note' => 'Kereste, ambalaj, dekorasyon', 'url' => 'https://kocist.com.tr' ),
			array( 'label' => 'İstanbul Paletçi', 'note' => 'Ahşap palet ve Euro palet', 'url' => 'https://istanbulpaletci.com' ),
			array( 'label' => 'ahsapkasa.com', 'note' => 'Ahşap sandık ve kafes', 'url' => 'https://ahsapkasa.com' ),
		),

		// Sayfalara ozel metinler (kardes siteyle ayni kalmasin: kopya icerik).
		'faq_lead'       => 'Kavak kereste, çıta ve takozla ilgili sorular: nerede kullanılır, ölçüye kesim, sevkiyat ve metreküp hesabı.',
		'contact_lead'   => 'Kavak kereste, çıta ya da takoz için ölçüyü ve adedi yazın ya da arayın; size en kısa sürede dönelim.',
		'form_note'      => 'Hangi ürün, hangi ölçü ve kaç adet: bu kadarı yeterli.',
		'size_hint'      => 'Örn. çıta 3 × 5 cm, 3 m, 200 adet',
		'home_sections'  => array( 'products', 'uses', 'cousin', 'faq', 'quote' ),

		'hero'           => array(
			'title'      => "Sandık, palet ve ambalaj için\nkavak kereste",
			'lead'       => 'Kavak kereste, çıta, takoz ve OSB levha; hepsi siparişteki ebada göre kesilir. Toptan alıma uygun, İstanbul teslim.',
		),

		'products_text'  => 'Üretim ve ambalaj işleri için kavak kereste, çıta, takoz ve OSB levha.',
		'products_lead'  => 'Kavak kereste ve onu tamamlayan çıta, ahşap takoz ve OSB levha. Ambalaj, sandık, palet ve mobilya üretimine uygun; toptan alım.',

		'uses'           => array(
			'title' => 'Kavak nerede kullanılır?',
			'text'  => 'Hafif, kolay işlenen ve ekonomik bir ağaç. En çok şu işler için alınıyor.',
			'items' => array(
				array( 'title' => 'Ambalaj, sandık, palet', 'text' => 'Hafif olduğu için taşınan yükün ağırlığını artırmaz.' ),
				array( 'title' => 'Mobilya', 'text' => 'Kolay kesilir ve işlenir; atölyede zaman kazandırır.' ),
				array( 'title' => 'İnşaat', 'text' => 'Destek ve kaplama işlerinde ekonomik bir seçenek.' ),
				array( 'title' => 'İç dekorasyon', 'text' => 'Açık rengi ve doğal dokusuyla iç mekânda.' ),
			),
		),

		'cousin'         => array(
			'title'  => 'Taşıyıcı, dayanıklı kereste mi gerekiyor?',
			'text'   => 'Çatı, kalıp ve yapı iskeleti için çam ve ithal kereste, kalas ve plywood kuzen sitemizde.',
			'button' => 'İthal keresteye göz atın',
			'url'    => 'https://ithalkeresteci.com',
		),

		'faq'            => array(
			array( 'question' => 'Kavak kereste hangi işlerde kullanılır?', 'answer' => 'En çok ambalaj, sandık ve palet üretiminde; ayrıca mobilya, inşaatta destek ve kaplama işlerinde ve iç dekorasyonda kullanılır.' ),
			array( 'question' => 'Ahşap takoz ne işe yarar?', 'answer' => 'Zemin dengeleme, yükseklik ayarı ve sabitleme için kullanılır. Fırınlanmış masif ahşaptan, zımparalanmış olarak hazırlanır; iç ve dış mekânda kullanılabilir.' ),
			array( 'question' => 'Kavak kereste, çıta ve takoz ne kadar sürede gönderilir?', 'answer' => 'Normal koşullarda sipariş 3–5 iş günü içinde sevk edilir.' ),
			array( 'question' => 'Ürünler özel ölçüde kesiliyor mu?', 'answer' => 'Evet. Kavak kereste, çıta, takoz ve OSB levha siparişte yazdığınız ebada göre kesilir.' ),
			array( 'question' => 'Çıtanın metreküpü nasıl hesaplanır?', 'answer' => 'Kesit ölçülerini metreye çevirip boy ve adetle çarpın. Örneğin 3 × 5 cm kesitli, 3 m boyunda 200 adet çıta: 0,03 × 0,05 × 3 × 200 = 0,9 m³.' ),
		),

		'about'          => array(
			'lead'   => 'Koçist Orman Ürünleri’nin kavak kereste sitesi: kavak kereste, çıta, takoz ve OSB tedariki.',
			'p1'     => 'Ailemizin ahşap ambalaj işi 1975’te başladı; bugün Koçist Grup adıyla Çatalca’da çalışıyoruz. Kavak kereste, çıta, takoz ve OSB levhayı istediğiniz ebatta kesiyor, ambalaj ve palet üreticilerine düzenli tedarik sağlıyoruz.',
			'p2'     => 'Amacımız kaliteli malzemeyi en kısa sürede ve uygun fiyatla teslim etmek. Ölçü ve adedi iletmeniz yeterli.',
			'values' => array(
				array( 'title' => 'Ölçüye göre kesim', 'text' => 'Kereste, çıta, takoz ve OSB levha istediğiniz ebatta kesilir.' ),
				array( 'title' => 'Hızlı sevkiyat', 'text' => 'Normal koşullarda 3–5 iş günü içinde teslim.' ),
				array( 'title' => 'Toptan fiyat', 'text' => 'Büyük siparişlerde toptan fiyat avantajı.' ),
			),
		),

		'seo'            => array(
			'name'        => 'Kavak Keresteci',
			'legal_name'  => 'Koçist Orman Ürünleri İnş. ve İnş. Yap. Malz. San. Tic. Ltd. Şti.',
			'description' => 'Koçist Orman Ürünleri’nin kavak odaklı sitesi: sandık, palet ve ambalaj imalatçılarına kavak kereste, çıta, ahşap takoz ve OSB levha. Siparişe özel ebatta kesim, toptan alım, İstanbul teslim.',
			'phone'       => '+90 549 648 19 19',
			'email'       => 'info@kocist.com.tr',
			'street'      => 'Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1',
			'district'    => 'Çatalca',
			'city'        => 'İstanbul',
			'postal_code' => '',
			'country'     => 'TR',
			// Kardes siteler sameAs degil; grup iliskisi parentOrganization ile.
			'same_as'     => array(),
			'parent_name' => 'Koçist Orman Ürünleri',
			'parent_url'  => 'https://kocist.com.tr',
		),

		'products'       => array(
			'kavak' => array(
				'slug'  => 'kavak-kereste',
				'name'  => 'Kavak Kereste',
				'mark'  => 'KAVAK',
				'short' => 'Ağırlığı düşük, bütçe dostu kereste; ebadı siparişe göre kesilir.',
				'lead'  => 'Hafifliği, kolay işlenmesi ve uygun fiyatıyla ambalaj, palet ve mobilya üretiminde en çok tercih edilen kerestelerden.',
				'body'  => "Kavak, hızlı büyüyen ve açık renkli bir ağaçtır. Hafif olması taşımayı kolaylaştırır; kolay kesilip işlenmesi üretimde zaman kazandırır.\n\nAmbalaj, sandık ve palet üretiminde, mobilyada, inşaatta destek ve kaplama işlerinde ve iç dekorasyonda kullanılır.",
				'specs' => array(
					array( 'label' => 'Ağaç türü', 'value' => 'Kavak' ),
					array( 'label' => 'Özellik', 'value' => 'Hafif, kolay işlenir, ekonomik, doğal' ),
					array( 'label' => 'Kullanım', 'value' => 'Ambalaj, sandık, palet, mobilya, iç dekorasyon' ),
					array( 'label' => 'Ölçü', 'value' => 'İstenen ölçüde kesim' ),
					array( 'label' => 'Satış', 'value' => 'Toptan alıma uygun' ),
				),
			),
			'cita'  => array(
				'slug'  => 'cita',
				'name'  => 'Çıta',
				'mark'  => 'ÇITA',
				'short' => 'Paketleme ve konstrüksiyon işleri için ince kesitli kavak çıta.',
				'lead'  => 'Paketleme, konstrüksiyon ve lambiri işlerinde kullanılan, düzgün kesilmiş ince kesitli çıta.',
				'body'  => "Çıta, kerestenin ince kesitli halidir. Ambalajda paket ve sandık iskeletinde, konstrüksiyonda ara bağlantı ve destek olarak kullanılır.\n\nİstenen kesit ve boyda hazırlanır.",
				'specs' => array(
					array( 'label' => 'Malzeme', 'value' => 'Kavak' ),
					array( 'label' => 'Kullanım', 'value' => 'Paketleme, konstrüksiyon, lambiri' ),
					array( 'label' => 'Ölçü', 'value' => 'İstenen kesit ve boyda' ),
				),
			),
			'takoz' => array(
				'slug'  => 'ahsap-takoz',
				'name'  => 'Ahşap Takoz',
				'mark'  => 'TAKOZ',
				'short' => 'Fırınlanmış masif takoz; zemin dengeleme, yükseklik ayarı ve sabitleme için.',
				'lead'  => 'Fırınlanmış masif ahşaptan, çatlamaya dayanıklı; yükü dengelemek, yükseltmek ve sabitlemek için.',
				'body'  => "Takozlar fırınlanmış masif ahşaptan kesilir; bu sayede çatlamaya ve şekil değiştirmeye karşı dayanıklıdır. Yüzeyleri zımparalanmıştır.\n\nZemin dengeleme, yükseklik ayarı ve yük sabitleme işlerinde; iç ve dış mekânda kullanılır.",
				'specs' => array(
					array( 'label' => 'Malzeme', 'value' => 'Fırınlanmış masif ahşap' ),
					array( 'label' => 'Dayanım', 'value' => 'Çatlamaya dayanıklı' ),
					array( 'label' => 'Yüzey', 'value' => 'Zımparalanmış' ),
					array( 'label' => 'Kullanım', 'value' => 'Zemin dengeleme, yükseklik ayarı, sabitleme' ),
					array( 'label' => 'Ortam', 'value' => 'İç ve dış mekân' ),
					array( 'label' => 'Ölçü', 'value' => 'İstenen ebatta kesim' ),
				),
			),
			'osb'   => array(
				'slug'  => 'osb-levha',
				'name'  => 'OSB Levha',
				'mark'  => 'OSB',
				'short' => 'Yönlendirilmiş yongalardan preslenmiş, ekonomik ve dayanıklı levha.',
				'lead'  => 'Ağaç yongalarının yönlendirilerek preslenmesiyle üretilen, ekonomik ve dayanıklı yapı levhası.',
				'body'  => "OSB (yönlendirilmiş yonga levha), ince ahşap yongaların katmanlar halinde yönlendirilip preslenmesiyle üretilir. Ekonomiktir ve geri dönüştürülebilir.\n\nÇatı ve duvar kaplamada, kamelya ve bahçe yapılarında, mobilya ve inşaat işlerinde kullanılır.",
				'specs' => array(
					array( 'label' => 'Yapı', 'value' => 'Yönlendirilmiş yonga levha' ),
					array( 'label' => 'Özellik', 'value' => 'Ekonomik, geri dönüştürülebilir' ),
					array( 'label' => 'Kullanım', 'value' => 'Çatı ve duvar kaplama, kamelya, mobilya, inşaat' ),
					array( 'label' => 'Ölçü', 'value' => 'İstenen ebatta kesim' ),
				),
			),
		),
	)
);
