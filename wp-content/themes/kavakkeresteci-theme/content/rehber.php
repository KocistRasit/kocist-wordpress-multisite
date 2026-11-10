<?php
/**
 * Kavak Keresteci rehber sayfalari.
 *
 * Saf dizi dondurur; WordPress fonksiyonu kullanmaz. inc/rehber.php bu
 * sayfalari sitede yoksa olusturur, metni sonradan elle degistirilmemisse
 * gunceller. Metinler bu siteye ozgudur: kocist.com.tr ve istanbulkeresteci.com
 * metinlerinden kopyalanmadi (kopya icerik olmasin).
 *
 * Kaynak bilgiler (2026-11-10, kocist.com.tr): kavak m3 fiyat araligi
 * (KDV haric 8.500-12.500 TL), standart kavak olculeri (10'luk, 15'lik,
 * 10x5, 10x10, 15x15, ozel imalat), kuru kereste nemi %12-18, kendi ISPM-15
 * isil islem firini (TR-1080-HT), sevkiyat Istanbul ve Trakya. Agacin genel
 * ozellikleri (yogunluk, dayaniklilik sinifi) yaygin teknik kaynaklardan,
 * "yaklasik" diye yazildi.
 *
 * 'faq' bolumu sayfanin sonunda gorunur ve FAQPage isaretine gider.
 * 'updated' sayfada "Son guncelleme" olarak yazar.
 */

return array(
	array(
		'slug'    => 'kavak-kereste-fiyatlari',
		'title'   => 'Kavak Kereste Fiyatları (Kasım 2026): Metreküp Fiyatı ve Hesap Örneği',
		'excerpt' => 'Kasım 2026 itibarıyla kavak kerestenin metreküp fiyatı KDV hariç 8.500–12.500 TL. Fiyatı ölçü, boy, kalite ve miktar belirler; 1 m³ kaç adet eder, toplam nasıl hesaplanır?',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Kasım 2026 itibarıyla kavak kerestenin metreküp fiyatı KDV hariç 8.500 TL ile 12.500 TL arasındadır.</strong> Siparişinizin bu aralıkta nereye düşeceğini kesit, boy, kalite ve miktar belirler. Kesin rakamı ölçünüzü ve adedinizi aldıktan sonra yazılı teklifle veriyoruz.</p>

<h2>Güncel fiyat tablosu</h2>
<table>
<thead><tr><th>Ürün</th><th>Birim</th><th>Fiyat aralığı (KDV hariç)</th></tr></thead>
<tbody>
<tr><td>Kavak kereste, kalas ve tahta</td><td>m³</td><td>8.500 – 12.500 TL</td></tr>
</tbody>
</table>
<p>Tablo, Koçist Orman Ürünleri’nin Çatalca deposundaki liste aralığıdır. Nakliye ayrıca hesaplanır ve teklifte toplam olarak yazılır.</p>

<h2>Fiyatı ne değiştirir?</h2>
<ul>
<li><strong>Kesit:</strong> 10 × 5, 10 × 10 ve 15 × 15 cm gibi standart ölçüler stoktan çıkar; özel kesit ek biçme işçiliği ister.</li>
<li><strong>Boy:</strong> Uzun boylar daha az bulunduğu için birim fiyatı yükseltebilir.</li>
<li><strong>Kalite:</strong> Budak, çatlak ve lif düzgünlüğü fiyata yansır. Ambalajlık kerestede görünüş ikinci plandadır, mobilyalıkta önemlidir.</li>
<li><strong>Kurutma:</strong> Fırınlanmış (kurutulmuş) kavak, ham kavaktan pahalıdır ama çarpılma ve küf riski azdır.</li>
<li><strong>Miktar:</strong> Toptan ve düzenli alımda metreküp fiyatı aralığın alt tarafına yaklaşır.</li>
</ul>

<h2>1 m³ kavak kereste kaç adet eder?</h2>
<p>Bir parçanın hacmi en × kalınlık × boy ile bulunur (ölçüler metreye çevrilir). 1 m³’ü bu sayıya bölünce kaç parça aldığınız çıkar.</p>
<table>
<thead><tr><th>Kesit (cm)</th><th>Boy</th><th>1 parça (m³)</th><th>1 m³’te adet</th></tr></thead>
<tbody>
<tr><td>10 × 5</td><td>3 m</td><td>0,015</td><td>66</td></tr>
<tr><td>10 × 5</td><td>4 m</td><td>0,020</td><td>50</td></tr>
<tr><td>10 × 10</td><td>3 m</td><td>0,030</td><td>33</td></tr>
<tr><td>10 × 10</td><td>4 m</td><td>0,040</td><td>25</td></tr>
<tr><td>15 × 15</td><td>3 m</td><td>0,0675</td><td>14</td></tr>
<tr><td>15 × 15</td><td>4 m</td><td>0,090</td><td>11</td></tr>
</tbody>
</table>
<p>Kesirli sonuçlar aşağı yuvarlandı; yarım parça satılmaz.</p>

<h2>Örnek: 100 adet 10 × 5 cm, 4 m kavak</h2>
<p>100 × 0,020 = 2 m³. Kasım 2026 aralığıyla bu sipariş 17.000 – 25.000 TL + KDV eder. Teklifleri karşılaştırırken metreküp fiyatını ve toplam metreküpü birlikte sorun; yalnızca adet fiyatına bakmak yanıltabilir.</p>

<h2>Teklif için ne yazmalısınız?</h2>
<ul>
<li>Kesit ve boy (örneğin 10 × 5 cm, 4 m)</li>
<li>Adet ya da toplam metreküp</li>
<li>Fırınlanmış mı ham mı</li>
<li>Kullanım yeri (palet, sandık, mobilya…)</li>
<li>Teslim adresi</li>
</ul>
<p>Bilgileri <a href="/iletisim/#teklif">teklif formuna</a> yazmanız yeterli. Hacmi kendiniz hesaplamak isterseniz <a href="/kavak-kereste-metrekup-hesaplama/">metreküp hesaplama rehberine</a> bakın.</p>
HTML,
		'faq'     => array(
			array( '1 m³ kavak kereste kaç TL?', 'Kasım 2026 itibarıyla KDV hariç 8.500 ile 12.500 TL arasındadır. Kesin fiyat kesit, boy, kalite, kurutma ve miktara göre teklifte verilir.' ),
			array( 'Kavak kereste çamdan ucuz mu?', 'Genellikle evet. Kavak hızlı büyüyen bir ağaçtır ve metreküp fiyatı çoğu zaman çamın altında kalır; bu yüzden ambalaj ve palet üretiminde tercih edilir.' ),
			array( 'Fiyata nakliye dahil mi?', 'Hayır. Nakliye mesafeye göre ayrıca hesaplanır ve teklifte toplam tutar olarak yazılır.' ),
			array( 'Fiyat neden aralık olarak veriliyor?', 'Kesit, boy, kalite ve miktar değiştikçe metreküp fiyatı da değişir. Tek rakam çoğu siparişte yanlış olacağı için aralık veriyor, kesin fiyatı ölçünüze göre yazıyoruz.' ),
		),
	),
	array(
		'slug'    => 'kavak-kereste-ozellikleri',
		'title'   => 'Kavak Kereste Nedir? Özellikleri, Kullanım Alanları ve Çamla Farkı',
		'excerpt' => 'Kavak hafif, açık renkli ve kolay işlenen bir kerestedir. Yoğunluğu, neme dayanımı, en çok kullanıldığı işler ve çam keresteyle karşılaştırması.',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Kavak kereste, hızlı büyüyen kavak ağacından biçilen hafif, açık renkli ve kolay işlenen bir kerestedir.</strong> Düşük ağırlığı ve uygun fiyatı nedeniyle en çok palet, sandık ve ahşap ambalaj üretiminde; ayrıca mobilya iskeletinde, iç mekân işlerinde ve inşaatta geçici destek olarak kullanılır.</p>

<h2>Kavak kerestenin özellikleri</h2>
<table>
<thead><tr><th>Özellik</th><th>Kavak</th></tr></thead>
<tbody>
<tr><td>Renk</td><td>Beyazımsı krem, açık gri tonlar</td></tr>
<tr><td>Yoğunluk (yaklaşık, %12 nemde)</td><td>400 – 450 kg/m³</td></tr>
<tr><td>Lif yapısı</td><td>Düz, ince ve homojen</td></tr>
<tr><td>İşlenme</td><td>Kolay kesilir, çivi ve zımba tutar, yarılmaya dirençlidir</td></tr>
<tr><td>Koku ve reçine</td><td>Kokusuz, reçinesiz</td></tr>
<tr><td>Dış mekân dayanımı</td><td>Düşük; açıkta kalacaksa emprenye ya da boya gerekir</td></tr>
</tbody>
</table>

<h2>Nerelerde kullanılır?</h2>
<ul>
<li><strong>Palet ve sandık:</strong> Hafif olduğu için taşınan yükün toplam ağırlığını az artırır; çiviyi yarılmadan tutar.</li>
<li><strong>Ahşap ambalaj ve kafes:</strong> Reçinesiz ve kokusuz olması gıda ve hassas ürün ambalajında avantajdır.</li>
<li><strong>Mobilya iskeleti:</strong> Kanepe, yatak ve dolap karkasında; dışarıdan görünmeyen taşıyıcı parçalarda.</li>
<li><strong>Kontrplak ve iç işler:</strong> Kontrplak çekirdeği, raf, lambri ve iç mekân kaplamaları.</li>
<li><strong>İnşaat:</strong> Kalıp ve iskelede geçici destek gibi kısa süreli işler.</li>
</ul>

<h2>Kavak ile çam arasındaki fark</h2>
<table>
<thead><tr><th></th><th>Kavak</th><th>Çam</th></tr></thead>
<tbody>
<tr><td>Ağırlık</td><td>Daha hafif (yaklaşık 400–450 kg/m³)</td><td>Daha ağır (yaklaşık 500–550 kg/m³)</td></tr>
<tr><td>Reçine</td><td>Yok</td><td>Var</td></tr>
<tr><td>Taşıyıcılık</td><td>Hafif yük ve ambalaj</td><td>Çatı, kalıp, yapı iskeleti</td></tr>
<tr><td>Fiyat</td><td>Genellikle daha uygun</td><td>Genellikle daha yüksek</td></tr>
<tr><td>Dış mekân</td><td>Emprenyesiz önerilmez</td><td>Emprenyeli olarak yaygın</td></tr>
</tbody>
</table>
<p>Kısaca: yük taşıyan çatı ve yapı işleri için çam, ağırlığın ve maliyetin önemli olduğu ambalaj ve palet işleri için kavak seçilir.</p>

<h2>Kurutma ve nem</h2>
<p>Kuru kerestede nem yaklaşık %12–18, yaş kerestede %30’un üzerindedir. Yaş kavak kuruyunca çekip çarpılabilir ve kapalı sandıkta küflenebilir. Bu yüzden ihracat ambalajı ve mobilya için <strong>fırınlanmış kavak</strong> önerilir.</p>

<h2>Hangi ölçülerde bulunur?</h2>
<p>Depoda standart olarak 10’luk ve 15’lik tahta, 10 × 5 cm kereste, 10 × 10 ve 15 × 15 cm kalas bulunur. Bunların dışındaki kesit ve boylar siparişe göre kesilir. Fiyat için <a href="/kavak-kereste-fiyatlari/">kavak kereste fiyatları</a> sayfasına, ürün için <a href="/urunler/kavak-kereste/">Kavak Kereste</a> sayfasına bakabilirsiniz.</p>
HTML,
		'faq'     => array(
			array( 'Kavak kereste sağlam mı?', 'Hafif yük, ambalaj ve iç mekân işleri için yeterince sağlamdır ve çiviyi yarılmadan tutar. Çatı ve yapı iskeleti gibi taşıyıcı işlerde çam ya da daha sert ağaçlar tercih edilir.' ),
			array( 'Kavak kereste suya dayanıklı mı?', 'Hayır, doğal hâliyle neme ve dış etkilere dayanımı düşüktür. Açıkta kalacaksa emprenye edilmeli ya da boyanmalıdır.' ),
			array( 'Kavak kerestenin yoğunluğu ne kadar?', 'Yaklaşık 400–450 kg/m³ (%12 nemde). Çamdan hafif olduğu için palet ve sandıkta ağırlık avantajı sağlar.' ),
			array( 'Kavak mı çam mı?', 'Ambalaj, palet ve sandık için kavak; çatı, kalıp ve yapı iskeleti için çam daha uygundur.' ),
		),
	),
	array(
		'slug'    => 'kavak-kereste-metrekup-hesaplama',
		'title'   => 'Kavak Kereste ve Çıta Metreküp Hesaplama: Formül ve Hazır Tablo',
		'excerpt' => 'Kereste ve çıtanın metreküpü en × kalınlık × boy × adet ile hesaplanır. Adım adım örnekler, sık kullanılan kavak ölçüleri için hazır adet tablosu.',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Kerestenin metreküpü en × kalınlık × boy × adet formülüyle bulunur; bütün ölçüler önce metreye çevrilir.</strong> Örneğin 10 × 5 cm kesitli, 4 m boyunda 50 adet kavak tam 1 m³ eder.</p>

<h2>Dört adımda hesap</h2>
<ol>
<li>Santimetreyi metreye çevirin: 10 cm = 0,10 m, 5 cm = 0,05 m.</li>
<li>Bir parçanın hacmini bulun: 0,10 × 0,05 × 4 = 0,02 m³.</li>
<li>Adetle çarpın: 120 adet × 0,02 = 2,4 m³.</li>
<li>Kaç parça aldığınızı bulmak için tersini yapın: 1 ÷ 0,02 = 50 adet.</li>
</ol>

<h2>Çıta örneği</h2>
<p>2,5 × 5 cm kesitli, 3 m boyunda 400 adet çıta: 0,025 × 0,05 × 3 × 400 = <strong>1,5 m³</strong>. Çıta ince olduğu için adet hızla artar; teklif isterken adedi ve boyu birlikte yazın.</p>

<h2>Hazır tablo: 1 m³’te kaç adet?</h2>
<table>
<thead><tr><th>Kesit (cm)</th><th>2,5 m</th><th>3 m</th><th>4 m</th></tr></thead>
<tbody>
<tr><td>2,5 × 5 (çıta)</td><td>320</td><td>266</td><td>200</td></tr>
<tr><td>3 × 5 (çıta)</td><td>266</td><td>222</td><td>166</td></tr>
<tr><td>10 × 5</td><td>80</td><td>66</td><td>50</td></tr>
<tr><td>10 × 10</td><td>40</td><td>33</td><td>25</td></tr>
<tr><td>15 × 15</td><td>17</td><td>14</td><td>11</td></tr>
</tbody>
</table>
<p>Kesirler aşağı yuvarlandı. Tabloda olmayan ölçüler için formülü kullanın.</p>

<h2>Palet ve sandık için toplam hacim</h2>
<p>Bir palet ya da sandığın parça listesini (kesit, boy, adet) satır satır yazın, her satırın hacmini bulup toplayın, sonra sipariş adediyle çarpın. Kesim firesi için listeye küçük bir pay eklemek yaygın bir uygulamadır.</p>

<h2>Sık yapılan hatalar</h2>
<ul>
<li>Santimetreyi metreye çevirmeden çarpmak (sonuç 10.000 kat büyük çıkar).</li>
<li>Kesiti tek ölçüyle yazmak: “10’luk” yalnızca eni söyler, kalınlığı da belirtin.</li>
<li>Toplam metreküpü sormadan yalnızca adet fiyatını karşılaştırmak.</li>
</ul>
<p>Parça listenizi <a href="/iletisim/#teklif">teklif formuna</a> yazarsanız metreküp hesabını biz yaparız. Güncel metreküp fiyatı için <a href="/kavak-kereste-fiyatlari/">kavak kereste fiyatları</a> sayfasına bakın.</p>
HTML,
		'faq'     => array(
			array( '1 m³ kerestede kaç adet 10 × 5 var?', '4 metre boyda 50 adet, 3 metre boyda 66 adet.' ),
			array( 'Çıta metreküpü nasıl hesaplanır?', 'Kesitin iki ölçüsünü ve boyu metreye çevirip çarpın, sonra adetle çarpın. 2,5 × 5 cm, 3 m, 400 adet çıta 1,5 m³ eder.' ),
			array( '10 × 10 kalasın 1 adedi kaç metreküp?', '4 metre boyda 0,04 m³, 3 metre boyda 0,03 m³.' ),
		),
	),
	array(
		'slug'    => 'palet-ve-sandik-icin-kavak-kereste',
		'title'   => 'Palet ve Sandık İçin Kavak Kereste: Ölçü, Kurutma ve ISPM-15',
		'excerpt' => 'Palet, sandık ve ihracat ambalajında kavak kereste neden tercih edilir, hangi ölçüler kullanılır, ne zaman fırınlanmış kereste ve ISPM-15 ısıl işlem gerekir?',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Palet ve sandık üretiminde kavak kereste tercih edilir; çünkü hafiftir, çiviyi yarılmadan tutar ve metreküp fiyatı çamdan genellikle düşüktür.</strong> Yurt dışına gidecek ambalajda ise kerestenin ISPM-15 standardına göre ısıl işlem görmüş ve damgalanmış olması gerekir.</p>

<h2>Neden kavak?</h2>
<ul>
<li><strong>Ağırlık:</strong> Yaklaşık 400–450 kg/m³ yoğunlukla çamdan hafiftir; navlun ve elle taşıma kolaylaşır.</li>
<li><strong>Çivi tutma:</strong> Homojen lif yapısı sayesinde zımba ve çivide kolay yarılmaz.</li>
<li><strong>Temizlik:</strong> Reçine ve koku bırakmaz; ürüne leke yapmaz.</li>
<li><strong>Maliyet:</strong> Seri palet ve sandık üretiminde birim maliyeti düşürür.</li>
</ul>

<h2>Hangi parçada hangi ölçü?</h2>
<table>
<thead><tr><th>Parça</th><th>Sık kullanılan kavak ölçüsü</th></tr></thead>
<tbody>
<tr><td>Palet üst ve alt tahtası</td><td>10’luk ve 15’lik tahta</td></tr>
<tr><td>Sandık kayağı ve taban kirişi</td><td>10 × 5 cm kereste, 10 × 10 cm kalas</td></tr>
<tr><td>Ağır yük kızağı</td><td>15 × 15 cm kalas</td></tr>
<tr><td>Kafes ve iç destek</td><td>Çıta (kesit siparişe göre)</td></tr>
<tr><td>Palet ayağı</td><td>Ahşap palet takozu</td></tr>
</tbody>
</table>
<p>Ölçüler yükün ağırlığına ve sandığın boyutuna göre değişir; listede olmayan kesit ve boylar siparişe göre kesilir.</p>

<h2>Fırınlanmış mı ham mı?</h2>
<p>Yaş kerestede nem %30’un üzerindedir; kapalı sandıkta yolculuk boyunca terleme ve küf yapabilir. Kuru kerestede nem yaklaşık %12–18’dir. Uzun yolculuk, deniz yolu ve kapalı sandık için fırınlanmış kavak seçin.</p>

<h2>ISPM-15 nedir, ne zaman gerekir?</h2>
<p>ISPM-15, ahşap ambalajla zararlı böcek taşınmasını önlemek için uluslararası bir standarttır. Isıl işlemde (HT) ahşabın çekirdek sıcaklığı en az 56 °C’de en az 30 dakika tutulur, sonra ambalaj IPPC işaretli damgayla işaretlenir. Damgada ülke kodu, üretici numarası ve işlem kodu yer alır.</p>
<p>Koçist Orman Ürünleri’nin Çatalca’daki tesisinde kendi ISPM-15 ısıl işlem fırını vardır; damga kodu <strong>TR-1080-HT</strong>’dir. İhracata gidecek palet, sandık ve takozlar bu fırında işlenip damgalanabilir. Yurt içi sevkiyatta ısıl işlem zorunlu değildir.</p>

<h2>Sipariş öncesi kontrol listesi</h2>
<ul>
<li>Parça listesi: her parçanın kesiti, boyu ve adedi</li>
<li>Kurutma: fırınlanmış mı ham mı</li>
<li>İhracat varsa ISPM-15 ısıl işlem ve damga</li>
<li>Aylık ya da dönemsel tahmini ihtiyaç (düzenli alımda fiyat avantajı)</li>
<li>Teslim adresi: İstanbul ve Trakya’ya kendi araçlarımızla gönderiyoruz</li>
</ul>
<p>Listenizi <a href="/iletisim/#teklif">teklif formuyla</a> gönderin. Hacim hesabı için <a href="/kavak-kereste-metrekup-hesaplama/">metreküp rehberine</a>, ağacın özellikleri için <a href="/kavak-kereste-ozellikleri/">kavak kereste özellikleri</a> sayfasına bakabilirsiniz.</p>
HTML,
		'faq'     => array(
			array( 'Palet için kavak mı çam mı kullanılır?', 'İkisi de kullanılır. Kavak daha hafif ve genellikle daha uygun fiyatlıdır; tek kullanımlık ve hafif-orta yük paletlerinde öne çıkar. Çok ağır yükte çam ya da daha kalın kesitler tercih edilir.' ),
			array( 'İhracat sandığında ISPM-15 şart mı?', 'Evet. Yurt dışına giden ahşap ambalajın ISPM-15’e göre ısıl işlem görmüş ve IPPC damgalı olması gerekir. Yurt içinde zorunlu değildir.' ),
			array( 'Isıl işlem kaç derecede yapılır?', 'Ahşabın çekirdek sıcaklığı en az 56 °C’ye çıkarılır ve en az 30 dakika bu sıcaklıkta tutulur.' ),
			array( 'Kavak kereste nereye gönderiliyor?', 'İstanbul ve Trakya’ya (Edirne, Kırklareli, Tekirdağ) kendi araçlarımızla gönderiyoruz. Nakliye ücreti teklifte ayrıca yazılır.' ),
		),
	),
	array(
		'slug'    => 'kavak-cita',
		'title'   => 'Kavak Çıta: Ölçüler, Kullanım Alanları ve Sipariş Rehberi',
		'excerpt' => 'Kavak çıta ince kesitli, uzun kesilmiş kavak kerestedir. Sandık takviyesi, kaplama altı iskelet ve çerçevede kullanılır; kesit ve boy siparişe göre kesilir.',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Kavak çıta, kavak tomruğundan ince kesitli ve uzun olarak biçilmiş kerestedir.</strong> Hafif olduğu, çiviyi yarılmadan tuttuğu ve reçine bırakmadığı için en çok ambalaj, sandık ve kaplama altı iskelet işlerinde kullanılır. Kesit ve boy siparişe göre kesilir; stok ölçüsüne bağlı kalmazsınız.</p>

<h2>Çıta ile kereste arasındaki fark ne?</h2>
<p>İkisi de aynı ağaçtan biçilir; fark kesittedir. Çıta, eni ve kalınlığı birkaç santimetreyi geçmeyen ince parçadır. Yük taşıyan kiriş ve kayak işlerinde kalın kereste ya da kalas, bağlantı, takviye ve iskelet işlerinde çıta kullanılır.</p>

<h2>Kavak çıta nerelerde kullanılır?</h2>
<ul>
<li><strong>Sandık ve kafes:</strong> Panelleri birleştiren takviye, köşe ve kafes çıtası.</li>
<li><strong>Paketleme:</strong> Yük arasında ayırıcı ve istif çıtası; ürünler birbirine sürtünmez.</li>
<li><strong>Kaplama altı iskelet:</strong> Lambiri, alçıpan ya da levha kaplamanın arkasındaki taşıyıcı ızgara.</li>
<li><strong>Konstrüksiyon:</strong> Hafif bölme, kalıp desteği ve geçici iskelet.</li>
<li><strong>Çerçeve ve hobi:</strong> Tablo, pano ve maket çerçeveleri.</li>
</ul>

<h2>Hangi ölçüde sipariş vermeliyim?</h2>
<p>Çıta için sabit bir ölçü listesi yoktur; işin yüküne ve vida-çivi boyuna göre seçilir. Sık sorulan kesitler 2 × 4 cm, 2,5 × 5 cm ve 3 × 5 cm civarındadır. Boyu da işe göre seçin: kısa parçayı uzun çıtadan kendiniz kesmek fire demektir, doğrudan kullanacağınız boyda isteyin.</p>
<table>
<thead><tr><th>İş</th><th>Seçerken dikkat</th></tr></thead>
<tbody>
<tr><td>Sandık takviyesi</td><td>Çivinin çıtayı delip geçmeyeceği kalınlık</td></tr>
<tr><td>İstif ve ayırıcı</td><td>Yükün ağırlığı; ince kesit ezilebilir</td></tr>
<tr><td>Kaplama altı ızgara</td><td>Düzgünlük ve kuruluk; eğri çıta kaplamayı dalgalandırır</td></tr>
<tr><td>Çerçeve</td><td>Yüzey kalitesi ve düz lif</td></tr>
</tbody>
</table>

<h2>Kaç metreküp eder?</h2>
<p>Kesiti metreye çevirin, boy ve adetle çarpın. Örnek: 2,5 × 5 cm kesitli, 3 m boyunda 400 adet çıta = 0,025 × 0,05 × 3 × 400 = <strong>1,5 m³</strong>. Daha fazla örnek ve hazır tablo <a href="/kavak-kereste-metrekup-hesaplama/">metreküp hesaplama rehberinde</a>.</p>

<h2>Kuru mu yaş mı?</h2>
<p>Kaplama altı ve kapalı sandık işlerinde kuru çıta seçin; yaş çıta kurudukça çeker ve eğilebilir. Kısa süreli istif ve açık ambalajda yaş çıta da iş görür. İhracat ambalajında kullanılacaksa çıtanın da ISPM-15 ısıl işlem görmüş olması gerekir.</p>

<h2>Sipariş için ne gönderin?</h2>
<ul>
<li>Kesit (en × kalınlık, cm) ve boy (m)</li>
<li>Adet ya da toplam metreküp</li>
<li>Kuru mu yaş mı, ihracat için ısıl işlem gerekip gerekmediği</li>
<li>Teslim adresi</li>
</ul>
<p>Bilgileri <a href="/iletisim/#teklif">teklif formuyla</a> gönderin; ürün özellikleri için <a href="/cita/">çıta sayfasına</a> bakabilirsiniz.</p>
HTML,
		'faq'     => array(
			array( 'Kavak çıta ne işe yarar?', 'Sandık ve kafes takviyesi, yük arasında ayırıcı, kaplama altı taşıyıcı ızgara, hafif konstrüksiyon ve çerçeve işlerinde kullanılır.' ),
			array( 'Kavak çıta hangi ölçülerde kesilir?', 'Sabit bir ölçü listesi yoktur; kesit ve boy siparişe göre kesilir. Sık istenen kesitler 2 × 4, 2,5 × 5 ve 3 × 5 cm civarındadır.' ),
			array( 'Çıtanın metreküpü nasıl hesaplanır?', 'Kesit ölçüleri metreye çevrilip boy ve adetle çarpılır. 2,5 × 5 cm, 3 m, 400 adet çıta 0,025 × 0,05 × 3 × 400 = 1,5 m³ eder.' ),
			array( 'Çıta fiyatı neye göre değişir?', 'Kesite, boya, kuruluğa ve miktara göre değişir. Ölçü ve adedi gönderirseniz KDV hariç yazılı teklif hazırlanır.' ),
		),
	),
	array(
		'slug'    => 'ahsap-takoz-cesitleri',
		'title'   => 'Ahşap Takoz Çeşitleri: Palet, Kanallı, Üçgen ve Semer Takoz',
		'excerpt' => 'Ahşap takoz yükü taşır, ayırır ya da sabitler. Palet takozu, kanallı, üçgen (kama), mil ve semer takoz ne işe yarar, hangisi ne zaman seçilir?',
		'updated' => '2026-11-10',
		'body'    => <<<'HTML'
<p><strong>Ahşap takoz, yükü taşımak, yerden yükseltmek, birbirinden ayırmak ya da kaymasını önlemek için kesilmiş kısa ahşap bloktur.</strong> Şekline göre farklı işler görür: kare blok paleti taşır, kanallı takoz kayışı geçirir, üçgen takoz tekerleği ve yükü kilitler, semer takoz boru ve tankı yatırır.</p>

<h2>Takoz çeşitleri ve kullanım yerleri</h2>
<table>
<thead><tr><th>Takoz</th><th>Şekli</th><th>Ne işe yarar?</th></tr></thead>
<tbody>
<tr><td>Standart takoz</td><td>Kare ya da dikdörtgen blok</td><td>Makine ve pano altlığı, yükseltme, zemin dengeleme</td></tr>
<tr><td>Palet takozu</td><td>Kare ya da dikdörtgen blok</td><td>Palet ayağı; forklift çatalının girdiği boşluğu oluşturur</td></tr>
<tr><td>Kanallı takoz</td><td>Yüzeyine yuva açılmış blok</td><td>Çember, kayış ya da çatal için geçiş; istifte kaymayı önler</td></tr>
<tr><td>Üçgen (kama) takoz</td><td>Üçgen kesit</td><td>Tekerlek ve yükün yuvarlanmasını, devrilmesini önler</td></tr>
<tr><td>Mil ve merdane takozu</td><td>Yuvarlak oyuklu</td><td>Rulo ve merdanenin yuvarlanmasını önler</td></tr>
<tr><td>Semer takoz</td><td>Çapa göre oyulmuş yatak</td><td>Boru, tank, kazan gibi silindirik yükü taşır</td></tr>
</tbody>
</table>

<h2>Neden fırınlanmış masif ahşap?</h2>
<p>Yaş ahşaptan kesilen takoz kurudukça çatlar ve ölçüsü değişir; altına konduğu makine ya da palet dengesini kaybeder. Fırınlanmış masif takoz şeklini korur, çatlamaya karşı dayanıklıdır. Yüzeyi zımparalanmış takozla çalışmak da daha güvenlidir; kıymık yapmaz.</p>

<h2>İhracat yükünde takoz</h2>
<p>Yurt dışına giden palet ve ambalajda takoz da ahşap ambalaj sayılır ve ISPM-15’e göre ısıl işlem görmüş, damgalı olmalıdır. Koçist Orman Ürünleri’nin Çatalca’daki fırınında ısıl işlem yapılır ve <strong>TR-1080-HT</strong> damgası basılır.</p>

<h2>Takoz ölçüsü nasıl seçilir?</h2>
<ul>
<li><strong>Yük:</strong> Ağır yükte takozun taban alanı ve yüksekliği artar.</li>
<li><strong>Yükseklik:</strong> Palet takozunda forklift ya da transpalet çatalının gireceği boşluğu hesaba katın.</li>
<li><strong>Zemin:</strong> Dış mekânda neme dayanıklı, kuru ve masif takoz seçin.</li>
<li><strong>Şekil:</strong> Yuvarlanabilen yükte üçgen, mil ya da semer takoz kullanın.</li>
</ul>
<p>Hacim hesabı kerestedeki gibidir: 10 × 10 × 10 cm takoz 0,1 × 0,1 × 0,1 = 0,001 m³’tür; 1.000 adedi 1 m³ eder.</p>

<h2>Sipariş için ne gönderin?</h2>
<p>Takoz tipini, ölçüsünü (en × boy × yükseklik), adedini ve ihracat için ısıl işlem gerekip gerekmediğini <a href="/iletisim/#teklif">teklif formuyla</a> yazın. Ürün özellikleri için <a href="/ahsap-takoz/">ahşap takoz sayfasına</a>, palet ölçüleri için <a href="/palet-ve-sandik-icin-kavak-kereste/">palet ve sandık rehberine</a> bakabilirsiniz.</p>
HTML,
		'faq'     => array(
			array( 'Ahşap takoz ne işe yarar?', 'Yükü taşımak, yerden yükseltmek, ayırmak ve kaymasını önlemek için kullanılır. Palet ayağı, makine ve pano altlığı, istif ve sevkiyatta yük sabitleme en yaygın kullanımlarıdır.' ),
			array( 'Üçgen takoz ne için kullanılır?', 'Kama şeklindeki üçgen takoz, tekerleklerin ve yuvarlanabilen yüklerin kaymasını ve devrilmesini önler.' ),
			array( 'Palet takozunda ISPM-15 gerekir mi?', 'Palet yurt dışına gidecekse evet; takoz da ahşap ambalaj sayılır, ısıl işlem görmüş ve damgalı olmalıdır. Yurt içinde zorunlu değildir.' ),
			array( 'Takoz özel ölçüde kesilir mi?', 'Evet. Takozlar istenen en, boy ve yükseklikte, istenen adette kesilir.' ),
		),
	),
);
