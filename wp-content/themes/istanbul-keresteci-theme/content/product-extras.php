<?php
/**
 * Urun sayfalarinin ek bolumleri: basta kisa cevap, ölçü tablosu ve ürün SSS'si.
 *
 * Yalnizca firmanin verdigi bilgiler (Ekim 2026): Istanbul icine ayni gun
 * teslimat, her ebatta ozel imalat, insaatlik cam 10.000–15.000 TL/m³ + KDV,
 * calisma saatleri. Olculer sitedeki "Insaatlik Kereste Olculeri" yazisindan.
 * OSB, kontrplak ve plywood icin kalinlik ve fiyat verilmedigi icin tablo yok.
 * Ahsap Palet sayfasi bilerek yok: palet aramalari grubun palet sitesine gider.
 *
 * Metinler ik_rich_html ile basilir: "| a | b |" tablo, [yazi](/adres/) site ici
 * baglanti. Satir anahtari urunun adres adi (slug).
 *
 * slug => array(
 *     'answer' => kisa cevap (sayfadaki ilk paragraf),
 *     'sizes'  => olcu tablosu ve not (bos olabilir),
 *     'faq'    => array( array( soru, cevap ), … ),
 * )
 */

defined( 'ABSPATH' ) || exit;

$ik_delivery = 'Çatalca’daki depomuzdan İstanbul’un her yerine aynı gün teslim ediyoruz. Pazartesi–Cumartesi 08:00–19:00, Pazar 10:00–19:00 açığız.';
$ik_custom   = 'Evet. Her ebatta özel imalat yapıyoruz; ölçüyü, adedi ve teslim adresini telefonla ya da WhatsApp’tan bildirmeniz yeterli.';

return array(
	'kereste'   => array(
		'answer' => 'Çatalca’daki depomuzda çam, köknar, kayın ve meşe kereste satıyoruz. İnşaatlık çam kerestenin metreküp fiyatı Ekim 2026 itibarıyla 10.000–15.000 TL + KDV. İstanbul’un her yerine aynı gün teslim ediyor, her ebatta özel imalat yapıyoruz.',
		'sizes'  => "| Kesit (cm) | Halk arasındaki adı | Nerede kullanılır |\n| --- | --- | --- |\n| 2,5 × 10 | Tahta | Kalıp tahtası, döşeme, iskele altı |\n| 5 × 5 | Çıta, kadron | Karkas, çatı çıtası, destek |\n| 5 × 10 | Kereste, mertek | Kalıp kirişi, çatı merteği, karkas |\n| 5 × 20 | Kalas | İskele, kalıp kirişi, rampa |\n| 10 × 10 | Dikme, direk | Dikme, taşıyıcı, çatı aşığı |\n\nBoylar çoğunlukla 3, 4 ve 6 metredir; kereste istenen boyda kesilir. Ayrıntı için [İnşaatlık Kereste Ölçüleri](/insaatlik-kereste-olculeri/) yazımıza bakın.",
		'faq'    => array(
			array( 'Kereste fiyatı ne kadar?', 'İnşaatlık çam kereste Ekim 2026 itibarıyla metreküpü 10.000–15.000 TL + KDV. Fiyat ağaç türüne, ölçüye, kaliteye ve miktara göre değişir; kesin fiyat için ölçü listenizi gönderin. Fiyatı neyin belirlediğini [İstanbul Kereste Fiyatları](/istanbul-kereste-fiyatlari/) yazımızda anlattık.' ),
			array( 'Kereste ne zaman teslim edilir?', $ik_delivery ),
			array( 'Özel ölçüde kereste kesiyor musunuz?', $ik_custom ),
			array( '1 metreküp kerestede kaç adet çıkar?', '4 metrelik 5 × 10 cm kereste için 1 m³’te 50 adet çıkar. Hesabın nasıl yapıldığını [Kereste Metreküp Hesaplama](/kereste-metrekup-hesaplama/) yazımızda örneklerle gösterdik.' ),
		),
	),
	'kalas'     => array(
		'answer' => 'Kalas, kalın ve geniş kesitli kerestedir; inşaatta en çok 5 × 20 cm kalas iskele, kalıp kirişi ve rampada kullanılır. Kalaslarımız kalitesine göre ayrılır ve kurutulur. Her ebatta özel imalat yapıyor, İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => "| Kesit (cm) | Nerede kullanılır |\n| --- | --- |\n| 5 × 20 | İskele, kalıp kirişi, rampa |\n| Diğer ölçüler | Her ebatta özel imalat |\n\nBoylar çoğunlukla 3, 4 ve 6 metredir; kalas istenen boyda kesilir.",
		'faq'    => array(
			array( 'Kalas fiyatı ne kadar?', 'Fiyat ağaç türüne, ölçüye ve miktara göre değişir. Fikir vermesi için: inşaatlık çam kereste Ekim 2026 itibarıyla metreküpü 10.000–15.000 TL + KDV. Kesin fiyat için ölçü ve adet bildirin.' ),
			array( 'Kalas ne zaman teslim edilir?', $ik_delivery ),
			array( 'Özel ölçüde kalas yapıyor musunuz?', $ik_custom ),
		),
	),
	'cita'      => array(
		'answer' => 'Çıtalarımız çoğunlukla çamdandır; silinmiş ve zımparalanmış olarak satılır. İnşaatta en çok 5 × 5 cm çıta (kadron) kullanılır; dekoratif işler için standart ve özel ölçüde çıta üretiyoruz. İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => "| Kesit (cm) | Nerede kullanılır |\n| --- | --- |\n| 5 × 5 | Karkas, çatı çıtası, destek |\n| Özel ölçü | Dekoratif işler, her ebatta özel imalat |",
		'faq'    => array(
			array( 'Çıta ne zaman teslim edilir?', $ik_delivery ),
			array( 'Özel ölçüde çıta yapıyor musunuz?', $ik_custom ),
			array( 'Çıta hangi ağaçtan yapılır?', 'Çoğunlukla çamdan. Çıtalar silinip zımparalanarak teslim edilir.' ),
		),
	),
	'tomruk'    => array(
		'answer' => 'Depomuzda kayın, kızılçam, meşe ve karaçam tomruk bulunur. Tomruk; inşaatlık kereste, palet ve ağaç ev yapımında kullanılır. İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'Hangi ağaç türlerinde tomruk var?', 'Kayın, kızılçam, meşe ve karaçam. Stok durumunu gelmeden önce telefonla sormanızı öneririz.' ),
			array( 'Tomruk ne zaman teslim edilir?', $ik_delivery ),
			array( 'Tomruktan kereste kesiyor musunuz?', 'Evet. Kereste istenen boy ve kalınlıkta kesilir; her ebatta özel imalat yapıyoruz.' ),
		),
	),
	'ahsap-sandik' => array(
		'answer' => 'Ahşap sandığı ürününüzün ölçüsüne göre, her ebatta özel imalatla üretiyoruz. Sandıklar taşıma, muhafaza ve depolamada kullanılır; tamir edilebilir ve geri dönüştürülebilir. İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'Özel ölçüde sandık yapıyor musunuz?', $ik_custom ),
			array( 'Sandık ne zaman teslim edilir?', $ik_delivery ),
		),
	),
	'ahsap-kafes'  => array(
		'answer' => 'Ahşap kafesi standart ve özel ölçüde, her ebatta özel imalatla üretiyoruz. Kafesler transpalet ve forkliftle taşınır; ham madde ve ihracat sevkiyatında kullanılır. İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'Özel ölçüde kafes yapıyor musunuz?', $ik_custom ),
			array( 'Kafes ne zaman teslim edilir?', $ik_delivery ),
		),
	),
	'osb-plaka'    => array(
		'answer' => 'OSB, tutkalla preslenmiş ahşap yongalardan yapılan plakadır; çatı kaplaması, döşeme, duvar ve panoda kullanılır. OSB’yi istediğiniz ebatta kesiyor, İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'OSB, kontrplak ve plywood arasındaki fark nedir?', 'OSB yongalardan, kontrplak ve plywood ince ağaç katmanlarından yapılır. Karşılaştırmayı [OSB, Kontrplak ve Plywood Farkı](/osb-kontrplak-plywood-farki/) yazımızda bulabilirsiniz.' ),
			array( 'OSB’yi ölçüye göre kesiyor musunuz?', 'Evet. Plakayı istediğiniz ebatta kesiyoruz.' ),
			array( 'OSB ne zaman teslim edilir?', $ik_delivery ),
		),
	),
	'kontrplak'    => array(
		'answer' => 'Kontrplak; kayın, kavak, huş ya da egzotik çam katmanlarının dik açıyla yapıştırılıp preslenmesiyle yapılır. Hafiftir, çatlamaya dayanıklıdır ve vida tutar. Kontrplağı istediğiniz ebatta kesiyor, İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'Kontrplak ile plywood aynı şey mi?', 'İkisi de ince ağaç katmanlarından yapılır. Farkları ve hangisinin nerede kullanıldığını [OSB, Kontrplak ve Plywood Farkı](/osb-kontrplak-plywood-farki/) yazımızda anlattık.' ),
			array( 'Kontrplağı ölçüye göre kesiyor musunuz?', 'Evet. Plakayı istediğiniz ebatta kesiyoruz.' ),
			array( 'Kontrplak ne zaman teslim edilir?', $ik_delivery ),
		),
	),
	'plywood'      => array(
		'answer' => 'Plywood, çapraz dik yerleştirilmiş ince tabakalardan yapılan, yüzeyi düz, hafif ve dayanıklı bir plakadır. Plywood’u istediğiniz ölçü ve ebatta kesiyor, İstanbul’un her yerine aynı gün teslim ediyoruz.',
		'sizes'  => '',
		'faq'    => array(
			array( 'Plywood ile OSB arasındaki fark nedir?', 'Plywood ince ağaç tabakalarından, OSB ise preslenmiş yongalardan yapılır. Karşılaştırmayı [OSB, Kontrplak ve Plywood Farkı](/osb-kontrplak-plywood-farki/) yazımızda bulabilirsiniz.' ),
			array( 'Plywood’u ölçüye göre kesiyor musunuz?', 'Evet. Plakayı istediğiniz ölçü ve ebatta kesiyoruz.' ),
			array( 'Plywood ne zaman teslim edilir?', $ik_delivery ),
		),
	),
);
