# Kararlar

Kısa karar günlüğü: ne seçildi, neden.

## MVP 1

**Docker Desktop + Compose (WordPress 6.8 + MariaDB 11 + WP-CLI)**
En az kurulum gerektiren, tekrarlanabilir yöntem. Bilgisayarda Docker zaten kurulu ve
çalışır durumdaydı; PHP/MySQL/WP-CLI host'a kurulmadı. Host'ta 3306'da başka bir MySQL
çalıştığı için veritabanı portu dışarı açılmadı.

**Alt dizin tabanlı Multisite (`/kocist/`, `/paletci/`)**
Aynı origin altında çalışır; MVP 3'teki gömülü önizlemede `postMessage`/iframe
kısıtlarına takılmamak için tercih edildi. Alt alan adı moduna veya gerçek alan adlarına
geçiş, `DOMAIN_CURRENT_SITE` ve site kayıtları değiştirilerek sonradan yapılabilir.

**Multisite sabitleri `.multisite-ready` işaret dosyasıyla devreye giriyor**
`MULTISITE` sabiti ağ tabloları oluşmadan tanımlanırsa WordPress hata verir. Bu yüzden
`docker-compose.yml` içindeki `WORDPRESS_CONFIG_EXTRA`, `/var/www/html/.multisite-ready`
dosyası varsa ağ sabitlerini, yoksa yalnızca `WP_ALLOW_MULTISITE` tanımlar; dosyayı
`install.sh` ağ kurulumundan sonra oluşturur. Böylece kurulum tek komutla ve
tekrarlanabilir şekilde çalışır.

- Yol **mutlak** yazıldı: bu blok `wp-config.php` içinde `ABSPATH` tanımlanmadan önce
  çalışıyor, ayrıca WP-CLI `wp-config.php`'yi kendi dosya bağlamında `eval` ettiği için
  `__DIR__` de WP-CLI'nin yolunu veriyordu. İkisi de denendi, ikisi de hatalıydı.
- Aynı `WORDPRESS_CONFIG_EXTRA` bloğu YAML capasi (anchor) ile hem web hem WP-CLI
  container'ına veriliyor; yalnızca web'e verildiğinde WP-CLI "this is not a multisite
  installation" hatası veriyordu.

**Manifest dosya sisteminden okunuyor, `switch_to_blog()`'a güvenilmiyor**
`switch_to_blog()` başka sitenin tema kodunu yüklemez. Her tema kökündeki
`content-manifest.php` hiçbir WordPress fonksiyonuna bağımlı olmayan saf bir dizi
döndürür; eklenti, sitenin `stylesheet` option'ından tema slug'ını alıp dosyayı doğrudan
okur. Ağ panelinden (MVP 2) site bağlamına girmeden alan listesi üretmek bu sayede güvenli.

**Değerler `option` kaydında, sayfa içeriğinde değil**
Her alt sitenin kendi `nwcs_content` option'ı: `[sayfa][bileşen][alan]`. Tek bir kayıt
olduğu için okuma ucuz, site başına tamamen izole ve `docker compose down`/`up` sonrası
korunuyor. Temalarda gömülü metin bırakılmadı — "baştan sona düzenlenebilir" iddiası
ancak böyle ölçülebilir.

**Yazma tek yoldan: `nwcs_update_field()`**
Tür bazlı temizleme (text/textarea/url/image/icon/repeater) tek yerde. Seed betiği de
panel de aynı fonksiyonu kullanır; ikinci bir bağımsız yazma yolu üretilmedi.

**İkonlar sabit listeden**
16 ikonluk liste eklenti içinde satır içi SVG olarak duruyor. Panelden serbest SVG/HTML
girilemez; `nwcs_sanitize_icon()` liste dışını boşa düşürür.

**Örnek görseller GD ile yerel üretiliyor**
Kullanım hakkı sorunu olmasın ve gerçek marka görseli sanılmasın diye, `seed.php` her
görseli "ÖRNEK GÖRSEL" yazısıyla üretip gerçek WordPress medya kaydı olarak ekliyor
(alt metin dahil). Dışarıdan tek bir fotoğraf indirilmedi. Gerçek görseller sizden
geldiğinde aynı alanlara panelden yüklenecek.

**Demo formu bilinçli olarak "gönderildi" demiyor**
Paletçi'deki teklif formu gönderimi engelliyor ve "bilgileriniz hiçbir yere gönderilmedi"
uyarısı gösteriyor. Sahte başarı durumu üretilmedi; gerçek iletişim için telefon/WhatsApp
bağlantıları veriliyor. Gerçek e-posta teslimi ve spam koruması canlıya geçişte ayrı iş.

**İki tema ortaklaştırılmadı**
Koçist: koyu lacivert/amber, sıkışık grid, büyük harf başlıklar, keskin köşeler, rakam
şeridi. Paletçi: krem/orman yeşili/kiremit, serif başlıklar, ortalanmış hero, yuvarlak
kartlar, numaralı süreç adımları. Paylaşılan tek şey veri katmanı ve ikon listesi.

**Doğrulama gerçek tarayıcıyla yapıldı**
Bilgisayarda kurulu Chrome `--headless --screenshot` ile dört sayfanın ekran görüntüsü
alındı ve incelendi; ayrıca HTTP durum kodları, görsel yüklenmesi ve PHP hata günlüğü
kontrol edildi. Ek bir araç kurulmadı.

## MVP 2

**Panel sunucu tarafında çiziliyor, JavaScript yalnızca yardımcı**
Formlar normal HTML; JS sadece satır ekle/sil/taşı, kaydedilmemiş değişiklik uyarısı ve
ikon/görsel önizlemesi için var. JS kapalıyken de alanlar düzenlenip kaydedilebilir.
Panelde içerik yazan tek yol form gönderimidir; ikinci bir AJAX yazma yolu açılmadı.

**Form `admin_url('admin-post.php')` adresine gönderiliyor**
`admin-post.php` yalnızca `wp-admin/` kökünde bulunur; `wp-admin/network/` altında yoktur.
İlk denemede ağ adresine gönderildiği için 404 alındı, düzeltildi.

**Güvenlik zinciri**
`manage_network_options` yetkisi → bileşene özel nonce (`nwcs_save_<site>_<sayfa>_<bileşen>`)
→ hedef sitenin ağ içinde ve manifestli olduğunun doğrulanması → `switch_to_blog()` →
tür bazlı temizleme → tek `update_option` → `restore_current_blog()`. POST'tan gelen,
manifestte karşılığı olmayan anahtarlar sessizce yok sayılır; çıktı tarafında her değer
`esc_html`/`esc_url`/`esc_attr` ile basılır.

**Medya için wp.media yerine sunucu tarafı yükleme**
Ağ yönetiminde `wp.media` modalı admin-ajax'ı ana site bağlamında çağırır; dosya yanlış
sitenin medya kitaplığına düşer. Bunun yerine form içinde dosya girdisi kullanıldı ve
`media_handle_upload()` `switch_to_blog()` içinde çalıştırıldı — dosya doğru alt sitenin
`uploads/sites/<id>/` klasörüne gidiyor. Mevcut görseller de yine o sitenin kitaplığından
listeleniyor. Alt metin gerçek medya kaydına (`_wp_attachment_image_alt`) yazılıyor.

**Sıralama gizli `_sort` alanıyla**
PHP, form dizilerini DOM sırasına göre değil anahtarlara göre kurar; bu yüzden ↑/↓
düğmeleri satırı DOM'da taşırken her satırın gizli `_sort` değerini de günceller, sunucu
da satırları buna göre sıralayıp yeniden indeksler. Sürükle bırak eklenmedi (demo şartı
değil), sayfa yenilemeden çalışır.

**"Vazgeç" sayfayı yeniden yüklüyor**
`form.reset()` JS ile eklenen satırları geri almaz. Vazgeç, kaydedilmemiş değişiklik varsa
onay sorup sayfayı yeniden yükler; böylece her zaman veritabanındaki son kayıtlı hâl gelir.

**Tamamen boş tekrarlı satırlar kaydedilmiyor**
Kullanıcı satır ekleyip doldurmazsa sitede boş kart çıkmasın diye, hiçbir alanı dolu
olmayan satırlar yazılmadan eleniyor.

**Panel gerçek kaydetmelerle sınandı**
Oturum açıp formu ayrıştıran ve gönderen bir test betiğiyle: hero başlığı, footer metni,
ikon değişimi, menü sırası, ürün kartı silme ve gerçek görsel yükleme denendi; her
seferinde yalnızca hedef sitenin değiştiği, diğerinin değişmediği ve `docker compose
down && up` sonrasında değerlerin korunduğu doğrulandı. Sonrasında demo içeriği seed ile
temiz hâle getirildi.

## MVP 3

**Önizleme aynı origin'de iframe; tıklama postMessage ile geliyor**
Alt dizin tabanlı Multisite sayesinde panel ve siteler aynı origin'de. Tema şablonları
`nwcs_edit_attr()` ile her düzenlenebilir öğeye `data-nwcs-edit="sayfa.bileşen.alan"`
basar; önizleme içindeki küçük script tıklamayı yakalayıp panele `postMessage` gönderir.
İki taraf da mesajın origin'ini doğrular. Gömülü tıklama çalıştığı için belgelenmiş
yedek (yalnızca bölüm listesinden seçim) devreye girmedi; JavaScript kapalıyken zaten
o yedek davranış geçerli.

**Önizleme modu yalnızca yetkili kullanıcıda açılır**
`?nwcs_preview=1` tek başına yetmez; her istekte `manage_network_options` kontrol edilir.
Ziyaretçi aynı adresi açtığında hiçbir işaret, script veya ek veri gönderilmez (doğrulandı:
yetkisiz istekte 0 işaret). Admin çubuğu önizlemeye sızmasın diye `show_admin_bar` filtresi
erken (init'ten önce) eklenir — `template_redirect` çok geç kalıyordu.

**Düzenleyici AJAX ile yükleniyor, form hâlâ klasik yolla da çalışıyor**
Önizlemeden gelen tıklama sayfayı yenilemeden ilgili formu açar ve alanı vurgular.
Aynı HTML, JavaScript kapalıyken sunucu tarafında basılır ve form `admin-post.php`'ye
gider. Tek yazma mantığı (`nwcs_save_component`) her iki yolda da ortaktır.

**Bölüm sırası anında kaydediliyor**
↑↓ okları DOM'da taşır ve sırayı hemen `nwcs_order` ucuna yazar; kaydet düğmesi
beklemez. Sıra manifestteki "sıralanabilir" listesiyle doğrulanır, header/footer/hero
dışarıdadır. Yeniden başlatma sonrası korunduğu doğrulandı.

**WordPress 6.8 → 7.1.1 yükseltmesi**
Resmî MCP yolu Abilities API'yi gerektiriyor; bu API WordPress **6.9** ile çekirdeğe
girdi (ayrı eklenti dönemi bitti, `wordpress/abilities-api` deposu arşivlendi). Bu yüzden
çekirdek güncellendi. Resmî imaj dolu bir kuruluma dokunmadığı için
[update-core.sh](scripts/update-core.sh) dosyaları imajın içindeki `/usr/src/wordpress`
sürümünden eşitler (indirme yok, deterministik), ardından `core update-db --network`
çalışır. Yükseltme öncesi veritabanı yedeği alındı.

**MCP: resmî adapter + kendi yeteneklerimiz**
Çekirdek yalnızca üç okuma yeteneği kaydediyor (`core/get-site-info`, `get-user-info`,
`get-environment-info`) — içerik yazma yeteneği yok. Adapter da her şeyi kendiliğinden
açmaz; sunucu oluştururken hangi yeteneklerin araç olacağı tek tek sayılır. Bu demoda
kendi dört yeteneğimiz tanımlandı ve yalnızca onlar sunuldu. Yazma tarafında: yetki
kontrolü, yalnızca manifestli demo siteleri, yalnızca yerel adresler, manifestte olmayan
alanın reddi, görsel/repeater alanlarının bu yüzeyden dışlanması. Site kimliği her
çağrıda açıkça geçirilir (`site: "paletci"` ya da sayısal blog kimliği).

**Kimlik doğrulama: WordPress uygulama parolası**
Ayrı bir token sistemi kurulmadı. `WP_ENVIRONMENT_TYPE=local` tanımlandığı için uygulama
parolaları HTTPS olmadan da üretilebiliyor; bu yalnızca yerel demo içindir.
`claude mcp add --scope local` kullanılır, böylece kimlik bilgisi depoya girmez.

**Yedek dosyası web kökünden çıkarıldı**
Yükseltme öncesi alınan SQL yedeği `/var/www/html` altına düştüğü için tarayıcıdan
indirilebilir hâldeydi (HTTP 200). Fark edilip `/var/backups` altına taşındı. Yedekler
hiçbir zaman web köküne yazılmamalı.

## MVP 4 — nasıl yapıldı

**Havuz ağın ana sitesinde bir custom post type**
`nwcs_product` (+ `nwcs_product_cat` taksonomisi) ağ ana sitesinde tutulur; alt siteler
`switch_to_blog()` ile okur, kopya çıkarmaz. Post type her sitede kayıtlıdır (PHP
sürecinde global olduğu için), kayıtlar yalnızca havuz sitesinde bulunur. `show_ui`
kapalı: tek arayüz Ağ Yönetimi'ndeki Ürün Havuzu sayfasıdır.

**Site tarafında yalnızca üç option**
`nwcs_products_mode` (all/selected), `nwcs_products_selected` (sıralı kimlik listesi) ve
`nwcs_products_overrides` (ürün başına istisna). Ürün metni/görseli tekrar edilmez;
site yalnızca "neyi, hangi sırayla, hangi istisnayla gösteriyorum" bilgisini tutar.

**Fiyat serbest metin, boşluk anlamlı**
`450 TL`, `1.250 TL'den başlayan` gibi yazılabilir. Boş fiyat "Teklif al" demektir.
Site istisnasında "hiç dokunulmadı" ile "bilerek boşaltıldı" ayrımı için ayrı bir
`price_override` bayrağı tutulur; olmasaydı bir sitede fiyatı kaldırmak mümkün olmazdı.

**Detay sayfası: rewrite kuralı, gerçek post değil**
Alt sitede `/urun/<slug>/` adresi `nwcs_product` sorgu değişkenine bağlanır; şablon
havuzdan okur ve site istisnalarını uygular. Detay metni boş olan ürünün sayfası yoktur
(404). İlk denemede eksik eşleşme ana sayfayı döndürüyordu; ayrıca temalarda `404.php`
olmadığı için yedek şablon zinciri (`404` → `index`) eklendi.

**Arayüz: site seçici üstte, önizleme büyük**
Brief'teki "sağda dikey site seçici" şartı müşteri isteğiyle değişti. Seçici üst şeritte
hap biçiminde duruyor (adlar görünür), sağ sütun tamamen kalktı ve önizleme o alanı
aldı. İkon ızgarası varsayılan olarak kapalı ("Değiştir" ile açılıyor) — asıl sıkışıklık
oradan geliyordu. Renk olarak wp-admin grisinden ayrışan indigo/teal bir palet kullanıldı.

**Panel varlıkları iki sayfada da yükleniyor**
İlk denemede CSS yalnızca İçerik Stüdyosu'na yükleniyordu; Ürün Havuzu sayfası biçimsiz
açıldı. Enqueue koşulu iki menü anahtarını da kapsayacak şekilde düzeltildi.

## MVP 4 kapsamı (müşteri kararları)

Müşteri kararları — 21 Eylül 2026:

**1. Ürün havuzu Ağ Yönetimi'nde durur.**
Havuz belirli bir alt sitenin sayfası değil; tüm siteleri yöneten en üst seviyedeki
yönetim panelinde, İçerik Stüdyosu'nun yanında kendi sayfası olur ("Ürün Havuzu").
Ürünler orada bir kez girilir, siteler oradan beslenir.

**2. Görseller WordPress medya kitaplığında durur.**
Ürün görselleri havuzun bulunduğu yerin (ağ ana sitesi) medya kitaplığına yüklenir,
oradan silinir; dış bir depo veya dosya sistemi kullanılmaz. Alt siteler görseli
referansla gösterir, kopya oluşmaz.

**3. Site bazlı istisnalar gerekli.**
Müşteri tarafından açıkça istendi. Havuzdaki kayıt kaynaktır; site kaydı onun üzerine
yazılan ince bir katmandır. Siteye göre ezilebilen alanlar:

| Alan | Site bazında ezilebilir mi |
| --- | --- |
| Ürün adı | evet |
| Ürün açıklaması | evet |
| Ürün görseli | evet |
| Ürün fiyatı | evet (boş bırakılabilir) |
| Gizle / göster | evet |
| Sıra | evet |
| Kategori | hayır — havuzdan gelir |

**Fiyat kuralı:** fiyat alanı doludur ya da boştur. Boş olduğunda site, fiyat yerine
**"Teklif al"** gösterir. Bu kural hem havuzdaki değer hem de site bazlı ezme için
geçerlidir; yani bir üründe genel fiyat yazılıp tek bir sitede boş bırakılarak o sitede
"Teklif al" gösterilebilir.

**4. Site seçimi her sitede üç kipten biri olur:**
"hepsi" (yeni ürün eklenince otomatik görünür), "seçilenler" (panelden işaretlenenler),
"kurallı" (ör. belirli kategori). Böylece bir sitede tüm katalog, diğerinde alt küme
gösterilebilir ve binlerce ürün her siteye elle girilmez.

**5. Panel arayüzü elden geçirilecek** (aşağıdaki istekler):
- Önizleme alanı belirgin şekilde büyütülecek; asıl odak orası olacak.
- Sol sütun daraltılıp sıkışıklık giderilecek; alanlar daha rahat nefes alacak.
- Site seçici sağdaki dikey sütundan **üst şeride** taşınacak; site adları görünür
  kaldığı sürece küçük olabilir. Böylece ortadaki önizlemeye daha çok yer kalır.
- Arayüz renklendirilecek; wp-admin'in gri düzeninden ayrışan, daha okunaklı ve
  kullanıcı dostu bir görünüm hedeflenecek.
- Teknik bilgisi olmayan site sahibinin tek başına kullanabilmesi ölçüt olacak.

Not: brief'teki "sağda dikey site seçici" şartı, müşterinin bu isteğiyle değişti;
seçici üst şeride taşınıyor.

## MVP 5 sonrası hot-fix'ler

- **"Hepsi" kipinde tüm ürünler işaretli geliyor.** Kip zaten hepsini gösteriyordu ama
  kutular boş duruyordu; artık hem sunucu tarafında hem de kipi değiştirdiğiniz anda
  işaretleniyor.
- **"Bu sitede gizle" kutusu İçerik Stüdyosu'ndan kaldırıldı.** Gizleme Ürün
  Havuzu'ndaki toplu işlemden yönetiliyor. Daha önce gizlenmiş bir ürünün değeri
  kaybolmasın diye form o bilgiyi gizli alanda taşıyor ve kullanıcıya bir satırla
  bildiriyor.
- **Üst şeritteki ☰ düğmesi kaldırıldı.** Menü katlaması duruyor ama artık
  WordPress'in kendi "Menüyü daralt" tercihine saygı gösteriyor: kullanıcı menüyü
  açtıysa (`mfold = o`) panel onu tekrar katlamıyor.
- **"İstisna" yerine "Özelleştirilmiş/Özelleştirmeler".** Panelde tek bir dil kullanılıyor.
- **CSV kutusu açılır pencereye alındı.** Sağ üstteki "CSV ile toplu giriş" düğmesi
  native `<dialog>` açıyor; tarayıcıda tıklama testiyle doğrulandı.
- **Ürün formuna "Özelleştirmeler" bölümü eklendi** (Kategoriler'in üstünde). Ürünün
  hangi sitede hangi alanının havuzdakinden farklı kaydedildiğini üstü çizili/vurgulu
  bir tabloda gösterir. Şimdilik salt okunur; düzenleme İçerik Stüdyosu'nda yapılıyor.
  Veri zaten site option'larında tutulduğu için buradan düzenlenebilir hâle getirmek
  ileride yalnızca form alanı eklemek olacak.

## MVP 5 — nasıl yapıldı

**Önizleme tıklamasında sayfa anahtarı (hata düzeltmesi)**
Önizleme `sayfa.bileşen.alan` biçiminde mesaj gönderiyordu ama panel ilk parçayı
yok sayıp bileşeni o an açık sayfada arıyordu; "Tüm Sayfalar"a ait üst menü/footer
tıklanınca "Bölüm yüklenemedi" çıkıyordu. Artık sayfa anahtarı mesajdan okunuyor,
sekme kendiliğinden değişiyor ve "← Bölümler" doğru sayfanın listesine dönüyor.
Tarayıcıda çalışan bir gerileme testiyle doğrulandı (`content_page=global` gidiyor).

**Kategoriler taksonomiye taşındı**
Virgüllü metin kutusu yerine kategori listesi: havuzda kategori oluşturulup
silinebiliyor, üründe etiket gibi işaretleniyor, filtre kutusuna besleniyor.
Ürün formundaki "yeni kategori" alanı hem kategoriyi oluşturur hem ürüne ekler.

**Çoklu görsel**
Ürün görselleri `_nwcs_gallery` meta'sında sıralı liste. İlk görsel kart görselidir;
galerinin ilki aynı zamanda öne çıkan görsel olarak da yazılır, böylece eski
kayıtlar ve tek görselli kod yolları çalışmaya devam eder. Site istisnası bir
görsel seçtiğinde o görsel başa geçer, galerinin kalanı korunur.

**Medya Havuzu ayrı sayfa**
Toplu yükleme (tek seferde birden fazla dosya, isteğe bağlı ortak alt metin),
arama, sayfalama, başlık/alt metin düzenleme ve silme. Bir ürüne bağlı görsel
silinemiyor; hangi üründe kullanıldığı kartta yazıyor.

**Ölçek işleri**
CSV dışa/içe aktarma (slug ile eşleşme: varsa günceller, yoksa oluşturur; görsel
sütununda medya kimliği veya dosya adı kabul edilir), arama + kategori filtresi +
sayfalama, toplu işlemler (siteye göster/gizle, kategori ata) ve havuz okumasının
transient ile önbelleğe alınması. Önbellek anahtarı sürümlü: veri şekli değişince
eski önbellek kendiliğinden geçersiz olur — ilk denemede bu yüzden uyarı almıştık.

**Arayüz**
Adım şeridi ortalandı. WordPress yönetim menüsü panel sayfalarında katlanmış
başlıyor, üst şeritteki düğmeyle açılıyor ve tercih tarayıcıda hatırlanıyor.
Göz yorgunluğu için zemin yumuşatıldı: sol sütun gri, alanlar beyaz kartlar hâline
geldi, aralarına boşluk kondu.

## MVP 5 kapsamı — demonun son iterasyonu

Müşteri istekleri (21 Eylül 2026):

**1. Kategoriler metin kutusu değil, gerçek liste olacak.**
Ürün Havuzu'nda kategori oluşturulacak, üründe listeden etiket gibi seçilecek
(çoklu seçim, yazarken filtreleme, yeni kategori ekleme). Şu anki virgüllü metin
kutusu yazım hatasıyla mükerrer kategori üretmeye açık.

**2. Bir ürüne birden fazla görsel.**
Galeri: sıralanabilir görsel listesi. Kartta ilk görsel, detay sayfasında galeri.
Site istisnasında hangi görselin kullanılacağı seçilebilir.

**3. Adım şeridi ortalanacak.**
İçerik Stüdyosu ve Ürün Havuzu'ndaki "1 … 2 … 3 …" şeridi şu an sola yapışık.

**4. Medya havuzu sayfası.**
Görsellerin toplu yüklendiği, arandığı, alt metninin düzenlendiği ve silindiği ayrı
bir sayfa. Ürün Havuzu'ndaki küçük medya bölümü bunun yerini tutmuyor.

**5. Önizlemede sayfa algısı düzeltilecek (hata).**
"Ana Sayfa" sekmesindeyken önizlemede üst menü/footer gibi *tüm sayfalara* ait bir
bileşene tıklanınca "Bölüm yüklenemedi" çıkıyor. Sebep: önizleme mesajı
`global.header.logo_text` biçiminde geliyor, panel ilk parçayı yok sayıp bileşeni
açık sayfada (`home`) arıyor. Düzeltme: sayfa anahtarı mesajdan okunacak ve panel
gerekirse o sayfaya kendiliğinden geçecek.

**6. WordPress sol menüsü katlanacak.**
Panel açıkken yönetim menüsü varsayılan olarak dar/kapalı olacak, gerektiğinde
açılabilecek. Böylece önizlemeye daha fazla yer kalır.

**7. Görsel yorgunluğu azaltılacak.**
Uzun formlarda her yer beyaz olduğu için göz yoruluyor. Daha yumuşak zemin, bölüm
gruplama, katlanabilir alan grupları ve daha net görsel hiyerarşi.

Ölçek için eklenenler (öneri üzerine kabul edildi):

**8. CSV içe/dışa aktarma.** Ürün listesi tek dosyayla havuza alınabilecek ve dışa
aktarılabilecek. "Binlerce ürünü elle girmeyelim" isteğinin asıl karşılığı budur.

**9. Havuzda arama, kategori filtresi ve sayfalama.** Tek listede 200 kayıt sınırı
50 üründen sonra kullanılamaz hâle geliyor.

**10. Toplu işlemler.** Çoklu seçimle "şu sitede göster/gizle" ve "kategori ata".

**11. Havuz okumasının önbelleğe alınması.** Her sayfa çiziminde havuza geçiliyor;
ürün sayısı büyüdüğünde yavaşlar.

**Yeni ürün alanı eklenmeyecek.** Stok durumu / teslim süresi / minimum sipariş
sorusu soruldu; mevcut alanlar (ad, açıklama, fiyat, ölçü/not, kategori, görseller,
detay metni) yeterli görüldü.

## Açık bırakılanlar

- Taslak/revizyon akışı kapsam dışı: kaydetme davranışı "hemen yayınla".
- Görsel yükleme ve tekrarlı satır ekleme MCP yüzeyinde yok; panelden yapılır.
- Ürün havuzu (MVP 4): ürünler tek merkezde tutulup her sitede "hepsi" / "seçilenler" /
  kurallı gösterim ile sunulacak. Havuzun yeri (ana site mi ayrı bir havuz sitesi mi),
  görsellerin hangi sitenin medya kitaplığında duracağı ve site bazlı istisnalar
  (farklı başlık/fiyat/gizleme) o aşamada kararlaştırılacak.

---

## ahsapkasa yeniden tasarımı — tasarım kararları

Kapsam: ahsapkasa.com (Koçist Orman Ürünleri) sitesinin yeniden tasarımı. Ağa
`ahsapkasa-theme` ile üçüncü alt site olarak eklendi. Dört sayfa: ana sayfa,
hakkımızda, hizmetlerimiz, iletişim. Üst menü ve alt bilgi dört sayfada da aynı.

### Taşıyıcı fikir: ölçü

Mevcut sitedeki her ürün açıklaması "istediğiniz ölçü ve ebatlarda" diye başlıyor.
Firmanın kimliği kalite ya da kıdem değil, **müşterinin verdiği ölçüye göre üretmek**.
Tasarımın tek gösterişli öğesi bu yüzden teknik çizimdeki **ölçü çizgisi**: uçlarında
çentik, ortasında etiket. Hero başlığının altında bir kez kullanılır ve sayfa açılışında
soldan sağa bir kez çizilir (`prefers-reduced-motion` seçiliyse çizilmez). Başka hiçbir
yerde tekrarlanmaz.

### Renk

Brief: gri taban, ahşap ve ağaç yeşili.

| Jeton | Değer | İşi |
| --- | --- | --- |
| `bone` | `#eff0ee` | sayfa zemini — yeşile çalan açık gri |
| `dust` | `#dde0dc` | ikincil zemin, kenarlık |
| `moss` | `#6b716b` | ikincil metin |
| `ink` | `#212a24` | metin ve alt bilgi zemini |
| `forest` | `#2e5e3b` | **eylem** |
| `timber` | `#b98a4b` | **ölçü / yapı** |

Renk kuralı bilgi taşır: yeşil her zaman "tıklanabilir" demektir (bağlantı, düğme, odak
halkası), ahşap tonu her zaman "ölçü veya yapı" demektir (çentik, bölme çizgisi, teknik
etiket). Gölge, degrade ve dekoratif renk kullanılmaz.

### Tipografi

- **Archivo** (değişken genişlik ekseni, `wdth 112`) — başlık, menü, düğme, etiket.
  Geniş eksende kurulan başlıklar, sandık üstüne şablonla vurulan sevkiyat yazısını
  andırır; konuyla doğrudan ilgili bir seçim.
- **Literata** — okuma metni. Uzun soluklu okuma için tasarlanmış bir serif; Hakkımızda
  sayfasındaki uzun paragrafların 19px/1.75 ve ~66 karakterlik satırla rahat okunmasını
  sağlıyor.

Yaygın kalıbın tersi tercih edildi: başlıkta grotesk, gövdede serif.

### Eleme

Plan, yazılmadan önce "her benzer brief'te çıkacak varsayılan" olup olmadığı açısından
gözden geçirildi ve üç şey değiştirildi:

1. Gövde için ilk seçenek Source Serif'ti; fazla alışıldık olduğu için Literata'ya geçildi.
2. Hero'ya konması düşünülen "40 yıl / 4 ürün" rakam şeridi çıkarıldı — büyük rakam +
   küçük etiket + istatistik dizilimi en bilindik kalıplardan biri.
3. Ölçü rakamları için düşünülen monospace yazı tipinden vazgeçildi; aynı kalıbın parçası.

Ayrıca bilinçli olarak kullanılmayanlar: krem zemin, tümü büyük harf etiketler,
başlıkta tek kelimeyi renklendirme, düğme metnine "→" ekleme, orta noktayla birleştirilmiş
meta satırları, her bölüme ayrı gölgeli kart.

### Dörtlü ürün karesi

Mevcut sitede de dört ürünün ortasında "Teklif Alın" düğmesi var; fikir korundu, yeniden
kuruldu. Tek bir kare, `gap-px` ile ahşap tonunda ince çizgilerle dört bölmeye ayrılıyor;
kesişim noktasında zemin rengiyle halkalanmış dairesel bir düğme oturuyor.

Bölme içerikleri **dışa** hizalanır (üst sıra yukarı, alt sıra aşağı) ve iç tarafta düğmeye
pay bırakılır; böylece düğme hiçbir metnin üstüne binmez. Bu payın `md:` kademesi ayrıca
yazılmak zorunda: Tailwind'de `md:p-9` kısayolu, daha önce gelen `sm:pb-32` uzun yazımını
ezer. Mobilde daire gizlenir, kare tek sütuna iner ve düğme listenin altında tam genişlikte
görünür.

### Üst menü

Tailwind sınıflarıyla, durum değişimi küçük bir betikle (`assets/nav.js`):

- Sayfa kaydırılınca şerit 88px'den 64px'e iner, zemin ve alt çizgi kazanır (`data-stuck`).
- Menü bağlantısının altında tam alt çizgi yerine kısa bir çentik; ortadan dışa doğru açılır.
  Bulunulan sayfada sabit durur (`aria-current="page"`).
- Mobilde hamburger düğmesi çarpıya dönüşür, panel kademeli olarak açılır (`data-open`),
  Esc ve panel dışına tıklama ile kapanır.

Logo görseli firma adını zaten taşıdığı için yanında ayrıca yazı gösterilmez; `logo_text`
ve `logo_sub` alanları görselin alt metnini ve alt bilgiyi besler.

### Metin

Hakkımızda ve Amacımız metinleri ahsapkasa.com'daki içeriğin aynısıdır.

**Kalite Politikamız kısaltıldı** (806 → 242 karakter), müşterinin isteği üzerine: özgün metin
tek bir uzun devrik cümleydi ve yan yana durduğu Amacımız kutusuyla boy tutmuyordu. Kısaltmada
maddelerin hepsi korundu (zamanında ve uygun fiyatla teslim, müşteri beklentisi, işi ilk
seferinde doğru yapmak, çalışan sağlığı ve çevre, tedarikçilerle birlikte gelişme); yalnızca
tekrarlar atıldı. Özgün metin bu dosyanın git geçmişinde durur.

Ayrıca üç açık yazım hatası düzeltildi: "doğrultusun da" → "doğrultusunda",
"sepeklerini" → "spesifikasyonlarını", "ahsap" → "ahşap". Sitede karşılığı olmayan
(dolayısıyla yazılan) metinler: hero başlığı, ana sayfa giriş paragrafı, "Ahşap ambalaj"
ürün açıklaması, teklif şeridi, iletişim sayfası açıklamaları ve form metinleri.

### Teklif formu

Mevcut sitede form yok; sıfırdan kuruldu. Demoda sahte başarı ekranı gösterilmemesi
kuralı gereği gönderim gerçekten kaydediliyor: nonce doğrulaması, zorunlu alan kontrolü
(ad, e-posta, ölçü), görünmez bot tuzağı, ürün seçeneklerinin manifeste göre doğrulanması,
ardından `ak_quote` kayıt türüne özel kayıt. Hata durumunda alan alan mesaj gösterilir ve
girilen değerler korunur.

### Görseller

`resources/` klasöründeki fotoğraflar firmanın kendi sitesinden geliyor; yer tutucu
üretilmedi. `background01.jpg` (sisli dağ) konuyla ilgisiz olduğu için kullanılmadı;
`background02.jpg` (çam ormanı) yalnızca Hakkımızda sayfasında, firmanın hammaddesine
işaret ettiği için kullanıldı.

### Revizyonlar

Müşteri geri bildirimi sonrası yapılan değişiklikler:

1. **Yönetim şeridi çakışması.** Sabit üst menü, WordPress yönetim şeridinin altına giriyordu.
   Kural katman dışına yazılmak zorunda: Tailwind'in `top-0` yardımcı sınıfı `@layer utilities`
   içinde durur ve `@layer base`'deki kuralı ezer; katmansız kural ikisini de geçer.
2. **Hero.** Tipografi bloğu + ayrı fotoğraf şeridi kurgusu bırakıldı. Yerine kenardan kenara
   fotoğraf, üzerinde nötr koyu perde, ortalanmış başlık ve düğmeler geldi. Hero koyulaştığı
   için üst menü artık her zaman zeminli.
3. **Kutulaşma.** Giriş paragrafı, Hakkımızda metinleri ve hizmet kalemleri çerçeveli kutulara
   alındı (`.card`, `.panel`, `.frame`). Gövde metninin tek blok hâlinde uzaması yoruyordu.
4. **Hizmetler.** Tek kare / dört bölme fikri bırakıldı. Dört bağımsız kutu, aralarında boşluk,
   her birinde solda yazı sağda görsel; teklif düğmesi kutuların kesiştiği boşlukta durmaya
   devam ediyor. Düğmeye pay bırakan iç dolgu `md:` kademesinde ayrıca yazılmalı, yoksa
   `md:p-8` kısayolu onu eziyor.
5. **Renk.** Alt bilgi yeşile çalan koyu tondan nötr koyuya (`night`) alındı; kullanılmayan
   `forest-light` jetonu silindi. Sayfada tek yeşil kaldı ve yalnızca eylem anlamına geliyor.

### Hero slayt gösterisi ve görsel büyütme

**Hero slaytı.** `home.hero.slides` bir tekrarlayıcıdır; her satırda bir fotoğraf. Geçiş
çapraz solma (opaklık), süre 6 saniye. Fare üzerindeyken, odak içerideyken ve sekme arka
plandayken durur; `prefers-reduced-motion` seçiliyse otomatik ilerleme hiç başlamaz, slaytlar
yalnızca noktalarla gezilir. Tek slayt varsa noktalar basılmaz.

**Görsel büyütme.** Hizmetler sayfasındaki her kutu `data-gallery` içinde kendi görsellerini
JSON olarak taşır; kapak ve küçük görseller `data-lightbox-open="<sıra>"` ile hangi görselden
açılacağını söyler. Pencere yerli `<dialog>` üzerine kuruludur: `showModal()` odak tuzağını ve
Esc ile kapanmayı kendi halleder, üzerine ok tuşları ve ileri/geri düğmeleri eklendi. Kitaplık
kullanılmadı.

Ürün başına dört görsel alanı var (`image`, `image_2`, `image_3`, `image_4`). Eklentinin
tekrarlayıcısı iç içe tekrarlayıcı desteklemediği için galeri bu şekilde düz alanlarla kuruldu;
hepsi İçerik Stüdyosu'ndan düzenlenebilir.

**Hizmetler dizilimi.** Satır arası boşluk, ortadaki teklif düğmesini tamamen içine alacak
kadar geniş tutuldu (`sm:gap-y-44`). Böylece dört kutu da birebir aynı yapıda kalıyor ve
görseller aynı hizada duruyor; düğme hiçbir kutunun üzerine binmiyor. Önceki sürümde kutulara
pay bırakılıyordu, bu da alt sıradaki görselleri aşağı kaydırıyordu.

**Hakkımızda hero.** Sayfa, ana sayfayla aynı kurguya geçti: fotoğraf + koyu perde + ortalanmış
başlık. Dağ fotoğrafı zaten açık ve düşük kontrastlı olduğu için perde burada daha hafif
(`night/66`, ana sayfada `night/78`). Firma metni kısaltıldı; uzun hâli git geçmişinde duruyor.

---

## Excel'den toplu ürün yükleme

### Neden kütüphanesiz

`.xlsx`, içinde XML dosyaları bulunan bir ZIP paketidir. PhpSpreadsheet her Excel özelliğini
doğru okur ama Composer kurulumu ve depoya ~10 MB'lık bir `vendor` klasörü getirir. Sunucuda
`ZipArchive`, `XMLReader` ve `SimpleXML` zaten bulunduğu için `includes/xlsx-reader.php` bu
işi ~200 satırda yapıyor: paketin ilk sayfası ve paylaşılan metin tablosu akışlı okunuyor,
atlanan hücreler sütun sırası korunarak boşla dolduruluyor.

Kapsam dışı bırakılanlar bilinçli: tarih biçimleri (hücrenin ham Excel seri numarası okunur),
formüller (Excel'in kaydettiği son değer okunur), birden çok sayfa (yalnızca ilki) ve `.xls`.
Ürün listesi için bunların hiçbiri gerekmiyor.

**Okuyucuda yakalanan hata:** `XMLReader` ile `readOuterXml()` + `next()` kullanırken dıştaki
`read()` de ilerlettiği için her ikinci öğe atlanıyordu — paylaşılan metinlerin ve satırların
yarısı kayboluyordu. Doğru kalıp, ilk öğeye kadar `read()` ile gidip sonrasında yalnızca
`next( 'row' )` ile yürümek.

### Eşleştirme

Başlıklar Türkçe karakterler sadeleştirilip (ı→i, ş→s…) harf/rakam dışı atılarak eş anlamlı
listeyle karşılaştırılıyor. Sıra önemli: "açıklama" hem Kısa Açıklama'ya hem Detay Metni'ne
benziyor, önce Kısa Açıklama deneniyor. Bir alan iki sütuna atanamıyor; kullanıcı ikinciyi
seçince birincisi serbest bırakılıyor.

### Geri alma

Her yükleme bir "parti" olarak ağ seçeneğinde saklanıyor: oluşturulan ürünlerin kimlikleri ve
**güncellenen ürünlerin yükleme öncesi tam hâli** (başlık, içerik, kısa ad, fiyat, ölçü,
kategoriler). Geri alma, eklenenleri siliyor ve güncellenenleri geri yazıyor.

Bir ürünün `post_modified_gmt` değeri parti zamanından sonraysa o ürün elle düzenlenmiş
demektir; geri alma ona dokunmuyor ve kullanıcıya hangilerini atladığını söylüyor. Sessizce
üzerine yazmak, kullanıcının yükleme sonrası yaptığı işi yok ederdi.

Yalnızca son yükleme saklanıyor. Birden fazla partiyi tutmak geri alma sırasını ve çakışan
düzenlemeleri yönetmeyi gerektirirdi; istenen "son yüklemeyi geri al" davranışı için gereksiz.

### Güvenlik ve sınırlar

- İki AJAX ucu da `manage_network_options` yetkisi ve panel nonce'u istiyor.
- Geçici dosyalar `uploads/nwcs-import/` altında, `.htaccess` ile dışarıya kapalı; iş bitince
  siliniyor, bir günden eski artıklar her yüklemede süpürülüyor.
- Oturum belirteci küçük harf onaltılık (`bin2hex`). İlk sürümde `wp_generate_password()`
  kullanılmıştı ama `sanitize_key()` büyük harfleri düşürdüğü için dosya adı tutmuyordu.
- Satır sınırı 20.000; üstü okunmuyor ve kullanıcıya bildiriliyor.
- Yükleme 100'erli parçalar hâlinde işleniyor. Beklenen ölçek birkaç yüz ürün olsa da parçalı
  yapı hem ilerleme çubuğunu besliyor hem de ölçek büyürse yeniden yazmayı gerektirmiyor.

---

## Ürün kodu, site bazlı özelleştirme ve medya görünümü

### Ürün kodu neden eklendi

Excel sihirbazının ilk sürümünde "Ürün Kodu" sütunu eşleştirilebiliyordu ama havuzda böyle
bir alan yoktu; kod yalnızca kısa ad (`post_name`) üretmek için kullanılıyordu. Yani arayüz
olmayan bir alana işaret ediyordu.

Alanı kaldırmak yerine gerçeğini ekledik: `_nwcs_code`. Boş bırakılırsa ürünün kimliğinden
`URN-0006` biçiminde üretilir, böylece her ürünün değişmeyen bir kimliği olur. Aynı kodun iki
üründe kullanılması engellenir. Excel yüklemesi önce bu kodla eşleştirir, bulamazsa kısa ada
düşer — müşterinin kendi stok kodunu kullanabilmesi için.

### Özelleştirmeler artık düzenlenebilir

Bölüm önce yalnızca bilgi veriyordu; düzenleme İçerik Stüdyosu'nda yapılıyordu. Aynı ürün için
iki ayrı ekran arasında gidip gelmek gereksizdi. Veri katmanı (`nwcs_products_overrides`)
zaten yerinde olduğu için eklenen tek şey arayüz ve iki AJAX ucu oldu.

Görünürlük bilerek dışarıda bırakıldı: "bu sitede gizle" anahtarı daha önce panelden
kaldırılmıştı, aynı işlevi ikinci bir yerden geri getirmek kafa karıştırırdı. Ürünün hangi
sitede görüneceğini sitenin kendi seçimi belirler; burada yalnızca durumu okunur olarak
gösteriliyor.

Fiyat için ayrı bir işaret kutusu var, çünkü boş fiyat anlamlı bir değer: "Teklif al" demek.
İşaret kutusu olmadan "fiyatı boşalt" ile "fiyatı özelleştirme" ayırt edilemezdi.

### Medya görünümü

Görünüm tercihi kullanıcı başına `nwcs_media_view` meta alanında saklanır; adres çubuğundaki
`gorunum` parametresi geldiğinde yazılır, gelmediğinde son tercih kullanılır. JavaScript
gerektirmez, yer imi verilebilir.

---

## Yüzey, düğme ve görsel standardı (revizyon)

Müşteri geri bildirimi: hizmet kutularının rengi kötü, yazılar sıkışık, görsel çerçevesiz ve
alakasız duruyor, düğmelerin standardı yok.

### Düğme sistemi

Denetim yapınca sitede **yedi farklı dolgu ve dört farklı punto** çıktı: `px-8/py-4`,
`px-7/py-3.5`, `px-5/py-2.5`, `px-6/py-3`, `px-5/py-3`, `px-10/py-5`, `px-3/py-1`. Her düğme
yazıldığı yerde elle ölçülendirilmiş. Eleştiri yerindeydi.

Yerine tek bir bileşen: `.btn` + üç boy (`sm` / `md` / `lg`) + dört çeşit (`solid`, `outline`,
`light`, `light-outline`). Şablonlarda elle dolgu ya da punto yazılmıyor. Sitedeki on bir
düğmenin tamamı bu sisteme bağlı.

Yuvarlak köşe artık bilgi taşıyor: **hap köşe yalnızca düğmelere ait** (yuvarlak = basılabilir),
kart ve çerçeveler 4px köşede kalıyor. Tek yarıçapı her şeye uygulamak yaygın bir yapay-zekâ
kalıbı; iki yarıçaplı ayrım bilinçli.

### Yüzey tonları

Kart (`#e6e8e4`) sayfadan (`#eff0ee`) **daha koyuydu**. Sayfadan koyu bir kart nesne gibi değil,
çukur gibi okunur. Mantık tersine çevrildi: sayfa biraz derinleşti (`#e9ebe7`), kart beyaza
yaklaştı (yeni `surface` = `#f7f8f5`). Ayrım gölgeyle değil, yükselme + saç çizgisiyle sağlanıyor
— gölgeli kart yığını da bir kalıp.

**Ahşap tonu kenarlık rengi olmaktan çıkarıldı.** Baştaki kural "timber = ölçü ve yapı" idi;
kart kenarına %26 saydamlıkla sürülünce hem anlamını kaybediyor hem gri üstünde çamurlaşıyordu.
Kenarlıklar nötr `line` (`#d3d8cf`) oldu; ahşap tonu yalnızca ölçü çizgisinde, `.panel` sol
çentiğinde ve galeri noktalarında kaldı.

### Görsel standardı

Tek kural: **3:4 dikey, çerçeve içinde.** Hizmet kartlarındaki bütün ürün görselleri aynı
`.frame` bileşenini kullanıyor — Hakkımızda sayfasındaki görselle aynı dil. Ana sayfadaki ürün
kartları farklı bir bağlam (görsel önde, kenardan kenara) olduğu için 4:3 oranında sabitlendi;
kendi içinde tutarlı, ayrı bir muamele.

### Mobilde yatay taşma

Yan yana dizilimde `.btn`'in `white-space: nowrap` özelliği, dar metin kolonunu düğmenin
genişliğine zorluyor ve sayfayı 2px taşırıyordu. Kart telefonda dikey diziliyor (görsel üstte,
metin tam genişlikte), 640px üstünde yan yana geçiyor. Oran her iki dizilimde de 3:4.

### İkinci tur düzeltmeler

- **Ana sayfa ürün kartları `.card` sistemine bağlanmamıştı**; doğrudan `bg-bone-deep`
  yazılmıştı ve o ton bu turda daha da koyulaşınca kartlar iyice koyu kaldı. Sistem sınıfına
  geçirildi. Ders: yüzey rengi bileşene ait, şablona değil.

- **Küçük çerçeveli önizleme pul gibi duruyordu.** Görsel artık kartın sağ yarısını kenardan
  kenara dolduruyor; çerçeve işini kartın kendi kenarı görüyor. `.frame` bileşeni yalnızca
  Hakkımızda'daki büyük görselde kaldı — orada boyut yeterli olduğu için çalışıyor.

- **Başlık ile açıklama arasındaki fark yetersizdi** (24px'e karşı 19px, oran 1,26). Başlık
  28px'e çıkarıldı, açıklama 16px'e indirildi; oran 1,75. Kart düğmesi de `sm`'den `md`'ye
  alındı, gövde metniyle aynı puntoda.

- **İletişim düzeni**: başlık solda, iletişim bilgileri sağda bir kutuda, teklif formu ikisinin
  altında ortada ayrı bir kutuda. Telefon ve e-posta 24px'ten 21px'e indi; adres satır
  ortasından bölünüyordu.

## istanbulpaletci yeniden tasarımı

İkinci gerçek site. ahsapkasa ile aynı şirket (Koçist Orman Ürünleri), bu yüzden
asıl soru "iki kardeş site nasıl birbirine benzemeden aynı sistemde durur" oldu.

### Renk: logodan ölçüldü, görevleri ayrıldı

- **İndigo `#3D34ED`** logodaki "İSTANBUL" yazısından piksel piksel ölçüldü; canlı
  sitede hiç kullanılmıyordu (tema demosunun donuk laciverti vardı). Tek görevi
  eylem: düğme, bağlantı, etkin menü, odak halkası.
- **İndigo açık `#A09BFF`** logonun koyu zemin sürümünden alındı; koyu yüzeyde
  indigo'nun yerine geçer.
- **Sinyal sarısı `#F2B705`** yalnızca ölçü ve teknik veri: hero'daki ölçü
  çizgileri, kartlardaki ölçü satırı, şartname föyünün üst çizgisi. Başka yerde yok.
- **Arayüzde ahşap tonu yok.** ahsapkasa'da ahşap ve yeşil arayüzün kendisiydi;
  burada sıcaklığı yalnızca fotoğraflar taşır. İki site böylece aynı şirketin iki
  ayrı yüzü olur.

### Tipografi

Barlow Condensed (yalnızca 32 px üstü başlıklar) + Barlow (metin). Tek süper aile,
iki belirgin genişlik; kamu altyapı tabelaları için çizilmiş, endüstriyel. Dar kesim
küçük boyda "spor kulübü" gibi okunduğu için kart başlıkları normal genişliktedir.
Ölçüler `tabular-nums` ile hizalanır; monospace kullanılmadı.

### Yüzeyler ve gölge

Bant (kenardan kenara dolu renk), föy (`.sheet`, keskin köşe) ve kart (`.card`,
4 px). Yüzeylerde gölge yok; ayrım renk ve çizgiyle. **Tek istisna** üst menünün
açılır ürün paneli: sayfanın üstünde süzülen bir katman olduğu ancak gölgeyle
anlaşılıyor (beyaz ürün görselinin üstüne binince kenarı kayboluyordu).

Düğmeler 3 px köşeli, sert; ahsapkasa'nın hap düğmelerinden bilinçli olarak farklı.
Boy ve çeşit sistemi aynı mantıkta (`.btn--sm/md/lg`, `--solid/outline/light/light-outline`).

### Tek gösterişli an

Hero'da indigo alan üzerinde beyaz bir şartname föyü; çevresindeki sarı ölçü
çizgileri sayfa açılışında bir kez çizilir. Bölüm bölüm kayarak gelen animasyonlar
yok. Ölçü çizgisinin uç çentikleri önce 1 px idi ve görünmüyordu; 2 px'e çıkarıldı,
çizim animasyonunun kırpma alanı da çentikleri kesmesin diye 20 px dışa taşırıldı.

### Ürün sayfaları tek şablon

Beş ürün sayfası manifestte `'template' => 'product'` ile işaretli ayrı sayfalardır
(panelde her biri ayrı düzenlenir) ama tek şablonla basılır (`page.php` →
`template-parts/product-page.php`). Ürün listesinin tek kaynağı manifesttir:
menü, ızgara, liste, alt bilgi, form seçenekleri ve "diğer ürünler" oradan beslenir.
Manifestteki beş sayfa aynı alan yapısını bir kurucu fonksiyonla paylaşır; dosya hâlâ
WordPress'e bağımlı olmayan saf bir dizi döndürür.

Ürün havuzu kullanılmadı: ürün sayfaları şartname, galeri ve uzun açıklama taşıyor,
havuz ise kart düzeyinde ürünler için tasarlandı. ahsapkasa'da da ürünler manifestte.

### Şartname tablosu ve içerik dürüstlüğü

Canlı sitenin ürün sayfalarında hiçbir teknik veri yoktu; B2B alıcı için en büyük
eksik buydu. Şartname tablosu eklendi ama **firmaya özel değer uydurulmadı**: Euro
palet verileri (ölçüler, 35 kg, 1500 kg, 45,234 dm³) firmanın kendi sayfasından,
diğer satırlar kendi ürün metinlerinden türetildi. Euro palet metnindeki "80 adet
tahtadan imal edilir" cümlesi kaldırıldı; standart bir Euro palet ~20 parçadan
oluşur, cümle büyük olasılıkla çivi sayısıyla karışmış.

### Görseller

- Ana sayfa slaytlarının üçü kolajdı; beyaz ayırıcı çizgiler ölçülerek içlerinden
  tek fotoğraf kırpıldı (kurulum betiğinde, koordinatlarıyla).
- `home1-welcome` ve `home1-contact-pic` tema demosundan kalma stok görseller
  ("DIGITAL MARKETING" panolu adam, işaret eden kadın); kullanılmadı.
- `background01` (sisli dağ) ahsapkasa'da kullanıldığı için, `background02` (tren
  yolu) marka dışı olduğu için kullanılmadı.
- Planda Ahşap Sandık ve İkinci El Palet için "ÖRNEK GÖRSEL" öngörülmüştü; gerek
  kalmadı: `featured02` gerçek bir OSB sandık, `palet_2` yıpranmış bir palet.
- Logo yalnızca 160×51 px; retina ekranda yumuşak görünür. SVG ya da yüksek
  çözünürlüklü sürümü gelince değiştirilmeli.

### Canlı sitede bulunan hata

WhatsApp bağlantısında numara boşluklu yazılmış (`phone=+9053237498%2032`), bu
yüzden çalışmıyor. Yeni sitede `https://wa.me/905323749832` kullanıldı.

### Teklif formunda durum anahtarı (ahsapkasa'da da düzeltildi)

Form, yönlendirmeden sonra hata/başarı durumunu geçici kayıtta tutar ve anahtarı
adresle taşır. ahsapkasa bu anahtarı büyük-küçük harf karışık üretiyor, okurken
`sanitize_key()` küçültüyordu. Yerelde MySQL harf duyarsız olduğu için çalışıyordu;
harf duyarlı nesne önbelleğinde (LiteSpeed, Redis) mesajlar kaybolurdu. İki temada
da anahtar artık küçük harfli onaltılık ve okurken biçimi doğrulanıyor. Excel içe
aktarmadaki hatanın aynısı.

### Dil

Blog yazıları firma tarafından WordPress yönetiminden düzenleneceği için bu siteye
Türkçe dil paketi kuruldu (tarihler "11 Mayıs 2022", `lang="tr-TR"`). Diğer siteler
hâlâ İngilizce; ahsapkasa canlıya alınmadan önce aynı işlem yapılmalı.

### Revizyon: hero zemini

Düz indigo hero alanı göz alıyordu. İlk denenen koyu lacivert + ışık + ızgara
beğenilmedi. Son hâl: logodaki indigo korunup üstüne %60 siyah perde çekildi
(`--hero-veil` değişkeni, tek yerden ayarlanır). Föy hafif iki katmanlı gölgeyle
zeminden kalkar (`.sheet--raised`); bu, gölge kuralının ikinci istisnası.

### WhatsApp düğmesi marka yeşilinde

WhatsApp düğmeleri (üst menü, mobil menü, ürün sayfası) yeşil zemin, beyaz ikon ve
yazı: `.btn--whatsapp`. Parlak marka yeşili `#25D366` üzerinde beyaz yazının
kontrastı 2:1, okunabilirlik sınırı 4.5:1; bu yüzden düğme zemini aynı yeşilin koyu
tonu `#157F48` (5:1). Parlak yeşil beyaz zemindeki ikonlarda kalır (iletişim
listeleri). Tek istisna renk: WhatsApp anında tanınsın diye.

## SEO ve GEO — kalıcı ilke

Kullanıcı kararı: ağdaki bütün siteler (hedef 10) **SEO ve GEO destekli** olacak.
GEO (Generative Engine Optimization), ChatGPT, Perplexity ve Google'ın yapay zekâ
özetleri gibi motorlarda kaynak olarak gösterilmek demek. Bu bölüm bundan sonraki her
tema ve eklenti değişikliği için kontrol listesidir; yeni bir site bu listeyi
karşılamadan "tamam" sayılmaz.

### Nerede yaşar

Temada değil, **eklentide**. On site için tek kod; panelde her sayfanın kendi SEO
bölümü, site düzeyinde varsayılanlar. Temanın görevi yalnızca anlamlı HTML üretmek.
Uygulama: ağ yönetiminde "SEO ve GEO" sekmesi (ayrıntı: "SEO ve GEO — uygulama").

### Her sayfada

- Tek H1, atlamayan başlık hiyerarşisi, `lang="tr"`, `header/nav/main/footer`.
- Arama başlığı ve meta açıklama. Boşsa sayfa başlığı ve öne çıkan cümleden üretilir.
- Kanonik adres; alan adı eşlemesinden sonra her site kendi alan adını kanonik gösterir.
- Paylaşım önizlemesi (Open Graph + Twitter): başlık, açıklama, görsel.
- Görsellerde anlamlı alternatif metin; boyutu belli, tembel yüklenen görseller.

### Yapılandırılmış veri (JSON-LD)

- Site geneli: `Organization` / `LocalBusiness` (ad, adres, telefon, e-posta,
  koordinat, `sameAs` ile kardeş siteler), `WebSite`.
- Ürün sayfaları: `Product` (ad, açıklama, görsel, şartname satırları
  `additionalProperty` olarak). **Uydurma fiyat, puan, yorum yok.** Fiyat yoksa
  `offers` hiç yazılmaz.
- Blog: `BlogPosting` (tarih, yazar olarak firma). Tüm iç sayfalar: `BreadcrumbList`.
- `FAQPage` yalnızca sayfada gerçekten soru-cevap varsa.

### GEO'ya özel

- Her sitede `/llms.txt`: sitenin ne olduğu, sayfalar ve kısa açıklamaları.
- Önemli bilgiler **metin olarak** durur: ölçüler, kapasiteler, adres. Görselin
  içine gömülü bilgi yapay zekâ tarafından okunmaz. Şartname tabloları bu yüzden
  gerçek `<dl>`.
- Firma adı, adres ve telefon (NAP) 10 sitede birebir aynı yazılır. Tutarsızlık hem
  yerel SEO'yu hem yapay zekânın firmayı tek varlık olarak tanımasını bozar.
- İçerik dürüstlüğü burada SEO konusu da: yapay zekâ motorları metni alıntılar.
  Uydurma bir rakam on sitede çoğalır ve firma adına tekrarlanır.

### Ağ düzeyinde

- Site başına site haritası (WordPress çekirdeği: `/wp-sitemap.xml`).
- `robots.txt` alt dizin multisite'ta yalnızca kök alan adında çalışır; her site
  kendi alan adına eşlendiğinde site başına üretilmeli.
- Kardeş siteler birbirine bağlantı verirken aynı içeriği kopyalamaz (yinelenen
  içerik). ahsapkasa ve istanbulpaletci aynı firmanın farklı metinleriyle durur.
- Hız (Core Web Vitals) sıralama sinyalidir: sayfa oluşturucu kullanılmaz, CSS
  derlenmiş tek dosya, yazı tipleri önceden bağlanır.

## SEO ve GEO — uygulama

### Alanlar manifeste eklentiden eklenir

Temalar SEO alanı tanımlamaz. `nwcs_manifest_for_theme()` manifesti okurken her
sayfaya (ortak `global` hariç) bir `seo` bileşeni ("Arama ve Paylaşım") ve gizli bir
`site_seo` sayfası (firma bilgisi, varsayılanlar) ekler. Sonuç: kaydetme, temizleme,
alan çizimi, canlı önizleme ve MCP yetenekleri SEO alanlarında **ek kod olmadan**
çalışır. Yeni SEO sekmesi de ayrı bir kaydetme yolu açmaz; İçerik Stüdyosu'nun AJAX
kaydetmesini ve aynı güvenlik zincirini kullanır. `seo` ve `site_seo` adları ayrılmıştır.

Gizli sayfa (`'hidden' => true`) Stüdyo'nun sekmelerinde görünmez; firma bilgisi yalnızca
SEO sekmesinden düzenlenir.

### Boş alan = otomatik

Site sahibi hiçbir şey girmese de her sayfa başlık, açıklama ve görselle çıkar: başlık
sayfanın kendi başlığından (`title`/`name` alanları), açıklama öne çıkan cümleden
(`lead`/`short`/`intro`/`text`…), görsel ilk dolu görsel alanından. Tema isterse
`seo_source` ile hangi alanın kullanılacağını açıkça söyler. Otomatik değer panelde gri
yer tutucu olarak görünür; içerik değişince kendiliğinden güncellenir. Ana sayfada marka
önce gelir ("İstanbul Paletçi – İsteğe özel…"), iç sayfalarda sayfa adı.

### Ürün verisi şartnameden

`seo_source.type = Product` olan sayfalarda şartname satırları `additionalProperty`
olarak ürün verisine ve `llms.txt`'ye düz metin olarak geçer; aynı veri iki kez
girilmez. **Fiyat, puan, yorum üretilmez**: Google yanlış ürün verisini cezalandırır,
yapay zekâ motorları da alıntılar.

### Arayüz: önce önizleme, gerekirse düzenleme

Sitenin sayfaları Google sonucu önizlemesi olarak listelenir; düzenleyici her kartın
altında kapalı durur. İlk sürümde bütün formlar açıktı ve sekme ~9000 piksel tutuyordu;
kapalı hâliyle ~3400. Başlıkta 60, açıklamada 160 karakter sayacı var; alan boşken
sayaç otomatik değerin uzunluğunu gösterir. Stüdyo'nun kaydet çubuğu tek form için
yapışıktır; bu sekmede çok form olduğu için yerinde durur. "Kaydedilmedi" göstergesi
de artık olayın geldiği forma bağlı (önceden sayfadaki ilk formu varsayıyordu).

### Siteler arası tutarlılık

Ağ özeti, sitelerin firma bilgisinde unvan, telefon ve adresi karşılaştırır (boşluk ve
büyük-küçük harf farkı sayılmaz). İlk çalıştırmada gerçek bir tutarsızlık buldu: iki
site aynı adresi farklı yazıyor ("Şahintepe Mh. … No. 176/A-B-C-D" / "Şahintepe, …
No: 176"). Varsayılanlar her sitenin **kendi yayındaki** bilgisinden alındı; hangisinin
doğru olduğuna firma karar verir.

### Güvenlik ve temizlik

Site haritasından kullanıcı listesi çıkarıldı: WordPress varsayılanı yönetici kullanıcı
adını herkese açık bir dosyada listeliyordu. Yazar, tarih, arama ve ek sayfası
arşivleri `noindex`. `llms.txt` arama motorlarına kapalı sitede verilmez.

### Kapsam dışı

`robots.txt` alt dizin multisite'ta yalnızca kök alan adında çalışır; site başına dosya
alan adı eşlemesinden sonra. Google Search Console kaydı canlıya geçişte. Blog
yazılarının açıklaması yazının WordPress "Özet" alanından gelir; SEO sekmesi yazıları
listeler ama düzenlemeyi WordPress'in yazı düzenleyicisine bırakır.

## Demo sitelerin kaldırılması

İlk aşamanın iki demo sitesi (`/kocist/`, `/paletci/`), temaları, `seed.php`,
`seed-only.sh` ve Docker bağlantıları kaldırıldı; artık yalnızca gerçek siteler var.
Silmeden önce veritabanının tamamı `backups/` altına alındı (Git dışı, web kökü dışı).
Ürün havuzu özelliği ve içindeki ürünler (kullanıcının denemeleri dahil) duruyor; şu an
havuzu kullanan bir site yok.

`install.sh` artık betiğin başındaki `SITES` listesinden gerçek siteleri kurar ve var
olan bir sitenin içeriğine dokunmaz (seed alanları varsayılana geri yazdığı için
yalnızca site ilk kez oluşurken çalışır).

### Tema klasörü tek başına site değildir

Tema = tasarım + düzenlenebilir alan listesi. Site ayrıca açılır (Ağ Yönetimi → Siteler),
tema ona atanır, manifestteki adresler için sayfalar oluşturulur. Silerken önce site,
sonra tema: tersi sırada site temasız kalır. Bu dört adımı tek ekrana indiren bir "Yeni
site" sihirbazı sıradaki adım olarak önerildi.

## ithalkeresteci — kuzen kereste sitelerinin ilki

### Bulgu: alan adlarının kendi içeriği yok

ithalkeresteci.com ve kavakkeresteci.com aynı sunucuya bakıyor ve yalnızca
kocist.com.tr'yi tam ekran bir çerçeve (iframe) içinde gösteriyor; HTTPS çalışmıyor.
Çerçeve içeriği Google'da kocist.com.tr'ye sayılır, alan adlarının kendi değeri sıfır.
kocist.com.tr'nin kendisi 604 adresli (vida, tabanca, kompresör, kulübe modelleri) bir
katalog. Yeniden tasarımda her alan adı **kendi adının karşılığı olan odaklı bir kereste
sitesi** oldu; 604 adres yerine 10 sayfa.

### Tek ana tema, iki çocuk tema

WordPress'in standart ana/çocuk tema yapısı: `kereste-base` bütün şablonları,
hesaplayıcıyı, formu ve stili taşır; çocuk tema yalnızca üç renk değişkeni ve içerik
manifestidir. Manifest, ana temadaki kurucuya (`manifest.php`, bir closure döndürür)
site ayarlarını verir; dosya panel tarafından birden çok kez okunabildiği için fonksiyon
tanımlamaz. Eklenti değişmedi: çocuk temanın manifestini, SEO alanlarıyla birlikte,
kendiliğinden tanıdı (12 sayfa, 153 alan). İki kuzeni ayıran modüller manifestteki
`sortable_sections` listesinden seçilir.

### Tasarım: "parti boyası"

Kereste depolarında paket ve tomruk uçları partiyi belli etmek için boyanır; iki site
aynı depodan iki parti gibi, farklı işaret rengiyle. Diğer iki siteden ayrışma:

- beyaz zemin, **Zilla Slab** başlıklar (ahşap matbaa harfi karakteri), Source Sans 3 metin;
- **Saira Stencil One** yalnızca kesit ölçüsü ve tür etiketlerinde (paketlere böyle püskürtülür);
- köşesiz düğmeler (ahsapkasa hap, istanbulpaletci 3 px köşeli);
- ürün kartları **paket etiketi**: delik, ip, şablon harfli ölçü;
- vurgu ithalde petrol `#0B5C6B` (beyazda 7.6:1); kavak için ilk plan pastı, kullanıcı
  isteğiyle yeşil-kahveye döndü (aşağıda, kavakkeresteci).

İmza öğe **hero'daki m³ hesaplayıcısı**: kereste metreküple satılır; hesaplanan ölçü
"teklif al" ile teklif penceresine yazılır. Sayfa açılışında animasyon yok.

Hero'da fotoğraf ızgara hücresini hem en-boy oranı hem tam yükseklikle alınca sütunu
itiyordu (hesaplayıcı 263 piksele sıkıştı, ölçülerek bulundu); masaüstünde fotoğraf
hücreyi mutlak konumla dolduruyor.

### İçerik kaynakları ve bilinçli boşluklar

Metinler kocist.com.tr'deki ürün sayfalarından kısaltıldı; teknik satırlar oradaki
verilerle sınırlı (inşaatlık kereste: sarıçam/karaçam/ladin, 5×10 ve 10×10, 1./2.
sınıf; plywood: aspen/huş, filmli/filmsiz; kalas: çam/gürgen, fırınlanmış). Ürün
listesi ve ölçüler firmadan teyit bekliyor. Fotoğraflar firmanın kendi fotoğrafları
(822 px, ortada filigran); örnek görsel üretmek gerekmedi.

Adres ve telefon, kullanıcı kararıyla kaynak sitedeki gibi (Çatalca, 0549 648 19 19)
bırakıldı; firma sahibi SEO ve GEO sekmesinden düzeltecek. Sekme şu an iki telefon ve
üç adres yazımını tutarsızlık olarak gösteriyor. Kaynakta WhatsApp numarası boş
(`wa.me/`); düğme sonradan kullanıcı isteğiyle telefon hattıyla dolduruldu (aşağıda). Kaynak Hakkımızda "37 yıl" diyor, diğer siteler
"40 yıl"; burada kaynak korundu. kocist.com.tr'deki kontrplak sayfasının metni
"ASDASDASD…" test yazısı.

## kavakkeresteci — kuzen sitenin ikincisi

### m³ hesaplayıcısı kaynakta yoktu

Kullanıcı sordu: kocist.com.tr'de hesaplayıcı yok; onaylanan tasarım planında imza öğe
olarak önerilen bir ekleme. Satıcı istemezse ana sayfadan tek bölüm olarak kaldırılır
(hero'daki hesaplayıcı ve Hacim hesaplama sayfası); başka hiçbir şey ona bağlı değil.

### Renk: yeşil ve kahve

Kullanıcı kavak için pas yerine yeşil-kahve istedi: "tahtayla çok haşır neşiriz ve logosu
da yeşil". İşaret rengi doğrudan Koçist logosunun yeşili `#508038`; beyaz zeminde ve
beyaz yazıyla 4,7:1, koyulaştırmak gerekmedi. Şablon harfler kahve `#7A4A26` (7,4:1),
metin ve alt bilgi koyu ahşap kahvesi `#2A221C`, bölüm zemini açık adaçayı `#EEF0E9`.
Bunun için ana temaya üç yeni isteğe bağlı değişken eklendi (`--site-stamp`,
`--site-ink`, `--site-stone`); tanımlamayan ithal eski haliyle kalır.

### Modül farkı: tedarik yerine kullanım alanları

ithal ana sayfasında "toptan tedarik, hızlı sevkiyat" bandı var (tır ve forklift
fotoğrafları). Kavakta sevkiyat fotoğrafı yok, alıcının sorusu da farklı: "bu işime uyar
mı?". Yeni `uses` modülü kaynaktaki kullanım alanlarını (ambalaj/sandık/palet, mobilya,
inşaat, iç dekorasyon) sıralar; fotoğraf alanı isteğe bağlı, eşleşen fotoğraf olmadığı
için boş. Manifest kurucusu artık ana sayfada yalnızca `home_sections` listesindeki
modüllerin alanlarını açar; panelde kullanılmayan alan görünmez.

### Ürünler

Kavak kereste, çıta, ahşap takoz, OSB levha. kocist.com.tr'de kavak ailesinden içeriği
ve fotoğrafı olan dört ürün bunlar; "paletlik kereste"nin metni olmadığı için OSB seçildi.
Teknik satırlar kaynaktaki bilgiyle sınırlı; ölçü yazılmadı ve hesaplayıcının hazır
kesitleri boş bırakıldı (ölçü listesi satıcıdan gelecek).

### Koçist logosu ve WhatsApp (iki sitede)

- Grup logosu (`global.header.parent_logo`): üst menüde site adının yanında (xl ve
  üstü; lg'de menü satırı dolu), mobil menünün altında ve alt bilgide beyaz plaka üzerinde
  (logo açık zemine göre çizilmiş). Bağlantısı kocist.com.tr.
- WhatsApp: kaynak sitedeki bağlantıda numara yok. Kullanıcı "butonu görelim" dedi;
  **telefonla aynı hat (0549 648 19 19) varsayıldı**, `wa.me/905496481919`. Firma başka
  bir hat verirse panelden değişir. Düğme istanbulpaletci'deki gibi yeşil zemin
  `#157F48`, beyaz simge. Üst menüde md'de yazılı, lg'de gizli (yer yok), xl'de yalnız
  simge, 2xl'de yazılı; telefon yazısı 2xl'e alındı. Ürün sayfasında üç büyük düğme
  satırı taşırdığı için "Hacim hesapla" düğmesi metin bağlantısına indi.

### Yerel bağlantılar

Kuzen köprüsü ve alt bilgideki kardeş siteler canlı alan adlarını gösterir. Yerel ağda
seed betikleri bunları buradaki kopyalara çevirir (`kr_localize_links`); sonradan
kurulan kuzen, öncekinin bağlantısını da çevirir. Canlıda kod değişmeden alan adları
geçerli.

## Kereste siteleri: hesaplayıcı kaldırıldı, hero yeniden tasarlandı

Kullanıcı kararıyla m³ hesaplayıcısı iki siteden de kaldırıldı: hero'daki kutu, Hacim
hesaplama sayfası (WordPress sayfası da silindi, adres 404), menü öğesi, ürün
sayfasındaki bağlantı, manifestteki `calc_*` alanları, `calc` sayfası ve hazır kesitler,
site.js'deki hesaplama kodu. SSS'deki "metreküp nasıl hesaplanır" sorusu kaynaktan
geldiği için kaldı; yalnızca hesaplama sayfasına yönlendiren cümle çıktı.

### Yeni hero: ipe asılı paket etiketi

Değerlendirmede eski hero'nun zayıf yanı: sayfanın ilk ekranı bir forma benziyordu,
fotoğraf küçük ve filigranlıydı (kavakta beyaz fonlu ürün kesimi). Yeni hero:

- **Tam genişlik fotoğraf**: kereste paketlerinin boyayla işaretlenmiş uçları. Tasarımın
  "parti boyası" fikri artık fotoğrafın içinde: ithalde petrol, kavakta yeşil çizgiler.
- **Perde**: metin tarafı koyu (masaüstünde soldan, mobilde alttan), `--color-ink`
  üzerinden; kavakta koyu kahve, ithalde kurşuni.
- **İmza öğe**: sağda, hero'nun üst kenarından inen ipe asılı büyük **paket etiketi**.
  Üstte sitenin rozeti ve adı (parti etiketi), altında şablon harfli dört ürün; her satır
  ürün sayfasına gider. Ürün kartlarındaki etiketin hero boyu. Mobilde gizli (kartlar
  hemen altta).
- **Tek hareket**: etiket açılışta bir kez ipinde yerine oturur (-9° → -2°);
  `prefers-reduced-motion` açıkken hareket yok.
- İkincil düğme koyu zemin için yeni `btn--ghost`.

### Yapay zekayla üretilen örnek görseller

Hero fotoğrafları Flux 2 Max ile üretildi (`resources/kereste/ornek-hero-ithal.jpg`,
`ornek-hero-kavak.jpg`, 2400 px). Proje kuralı gereği sitede **"Örnek görsel"** notuyla
görünür (`home.hero.image_note`; gerçek fotoğraf yüklenince silinir). Dört aday
üretildi; boyası harfe benzeyen (yapay zeka izi belli olan) aday elendi. Ürün
fotoğrafları firmanın gerçek fotoğrafları olarak kaldı: ürünü doğru gösteren gerçek
görsel, daha güzel ama uydurma bir görselden iyidir.

## PR #1 entegrasyonu: 8 site tek panelde

Emirhan'ın tema paketi (Koçist + İstanbul Keresteci, Sanayi Palet, Ahşap Ambalaj)
`feature/entegrasyon` dalında `feature/seo-kereste` üzerine birleştirildi.
Öncesinde yedek alındı: veritabanı ve yüklemeler `backups/oncesi-entegrasyon-*`,
git etiketleri `yedek/oncesi-entegrasyon-main` ve `yedek/oncesi-entegrasyon-pr`.

- **Koçist**: yerelde silinen demo tema yerine PR'daki tema bütünüyle alındı. Düz
  birleştirme PR'ın değiştirmediği 5 dosyayı (index.php dahil) silinmiş bırakıyordu;
  index.php olmadan WordPress temayı bozuk sayar.
- **Kurulum**: Emirhan'ın temaları seed betiği kullanmaz, sayfalarını tema etkinleşince
  kendileri açar (`after_switch_theme`). `install.sh` listesinde seed yerine `-`.
- **Güvenlik düzeltmesi**: temaların "kurulum çalışmadıysa çalıştır" yedek yolu
  `admin_init`'te (Koçist'te `init`) herkese açıktı; anonim form gönderimleri
  (`admin-post.php`) de tetikliyordu. Artık yalnızca yönetici ya da WP-CLI; Koçist
  `admin_init`'e alındı, Ahşap Ambalaj'a eşzamanlı çalışma kilidi eklendi.
  Sonucu: temanın sayfa sürümü ileride artırılırsa (ör. `KOCIST_PAGES_VERSION`),
  canlıdaki sitede yeni sayfalar ilk ziyaretçiyle değil, bir yönetici wp-admin'i
  açınca oluşur. İlk kurulum etkilenmez (`after_switch_theme`).
- **ISPM 15 belgesi**: firmanın kendi açık sitelerinde yayımlı olduğu için kullanıcı
  onayıyla repoda kalıyor.
- `.gitattributes`: `*.sh` her zaman LF (Windows'ta CRLF'e dönen betik konteynerde
  çalışmaz).
- Duman testi: 8 sitede 92 adres 200, her sayfada tek `<title>`, PHP uyarısı yok.
  Bilinen SEO eksikleri (6. adım): yeni 4 temada firma bilgisi yok; İstanbul
  Keresteci'nin 10 ürün sayfası (`/urunlerimiz/<ürün>/`) ve Koçist blog sayfası
  açıklamasız.

## istanbulpaletci hero: düz renk yerine fotoğraf

Kullanıcı hero'yu beğendi ama diğer sitelerin fotoğraflı hero'larına uymasını istedi.
Föy, ölçü çizgileri ve bilgi şeridi aynı kaldı; düz indigo zeminin yerine fotoğraf ve
indigo tonlu perde geldi (`home.hero.bg_image`, not alanı `bg_note`). Fotoğraf yoksa eski
perdeli indigo zemine döner. İlk denemede palet sahası/forklift fotoğrafı kullanıldı;
aşağıdaki "kuzen" kararıyla palet duvarına geçildi.

## Tarayıcı önbelleği: dosya sürümü

Tema varlıkları (Tailwind CSS, JS) tema sürümüyle (`?ver=0.1.0`) yükleniyordu; dosya
değişse de adres aynı kalıyor, tarayıcı eski CSS'i gösteriyordu (yerelde yeni hero'yu
bozuk gösterdi; canlıda güncelleme sonrası geri dönen ziyaretçide aynısı olurdu).
ahsapkasa, istanbulpaletci ve kereste-base artık `tema sürümü + dosya değişim zamanı`
kullanıyor. Emirhan'ın dört teması da aynı sorunu taşıyor; kendi dalında düzeltecek.

## SEO ve GEO — tamamlama (8 site)

Canlıya çıkmadan önce 8 sitenin site haritasındaki her adres ölçülerek denetlendi
(başlık, açıklama, canonical, paylaşım görseli, JSON-LD, h1, alt metin, dil, llms.txt).
İlk denetimde bulunanlar ve çözümleri:

- **Emirhan'ın 4 sitesinde firma bilgisi yoktu** (JSON-LD'de yalnızca ad). Temalara
  `seo_site_defaults` eklendi; değerler her sitenin kendi sayfalarında yazanlar
  (telefon aynı hattın uluslararası yazımı). Ahşap Ambalaj'da görünür bir resmî unvan
  olmadığı için unvan boş.
- **`sameAs` yanlış kullanılıyordu**: kardeş siteler "aynı kurum" olarak işaretliydi.
  Kaldırıldı; yerine yeni "Bağlı olduğu grup" alanı → `parentOrganization` (Koçist Orman
  Ürünleri). ahsapkasa Koçist'in kendisi olduğu için onda `sameAs: kocist.com.tr` kaldı.
- **İstanbul Keresteci'nin 10 ürün sayfası** (`/urunlerimiz/<ürün>/`) manifestte değil,
  tek şablonla çiziliyor; açıklamasız ve yapılandırılmış verisizdi. Eklentiye
  `nwcs_seo_extra_pages` süzgeci eklendi; tema ürünlerini bildiriyor → başlık, açıklama,
  Product verisi ve llms.txt satırı.
- **Canonical eksikti**: WordPress yalnızca tekil sayfalarda yazıyor. Eklenti artık her
  sayfada tek canonical yazıyor (WordPress'inki kaldırılıyor).
- **Kategori arşivleri** (Koçist'te 9 tane, birkaç yazılık ince listeler) açıklamasız
  ve dizindeydi: `noindex` + site haritasından çıkarıldı.
- **Paylaşım görseli eksikti** (Emirhan'ın temaları görselleri tema klasöründe tutuyor):
  `nwcs_seo_default_image` / `nwcs_seo_default_logo` süzgeçleri; her tema kendi hero
  fotoğrafını bildiriyor, Koçist ayrıca logosunu.
- **Kısa açıklamalar**: sayfanın ilk metni 100 karakterden kısaysa sonraki metinleri
  eklenir; ana sayfada kısa kalırsa firma tanımı kullanılır. Başlık 60 karakteri aşarsa
  site adı eklenmez.
- **GEO**: kereste sitelerinin SSS sayfası `FAQPage` olarak işaretli; sık sorulanlar
  llms.txt'de de var (yapay zekâ yanıtlarının en doğrudan kaynağı).
- ahsapkasa'nın sayfa dili `en-US` idi → `tr`.

Kalan, içerik düzeyinde küçük noktalar (Emirhan'ın metinleri; SEO sekmesinden
iyileştirilebilir): Koçist'te 4 blog yazısının kendi başlığı 60 karakterden uzun,
6 sayfanın açıklaması 55–69 karakter. İçerik tutarsızlıkları (tecrübe yılı, ünvan,
adres, çalışma saati, Euro palet değerleri) firma sahibinin kararını bekliyor.

## Tasarım birliği turu (feature/tasarim-birlik)

### Ortak font: Archivo

Kullanıcı isteği: 7 sitede (Koçist hariç) ortak font; seçim, şu an en çok kullanılan
font. Sayım: Archivo 3 site (ahsapkasa, İstanbul Keresteci, Ahşap Ambalaj), Literata 2,
diğerleri birer site. Archivo değişken bir font (genişlik ekseni 62–125, ağırlık
300–900): tek aileyle hem dar başlık hem normal metin yazılır. Başlıkların eski
karakteri genişlik ekseniyle korundu (dar fontların yerine dar kesim).

**Geri dönüş için önceki fontlar** (her dosyada da yorum olarak duruyor):

| Site | Önceki başlık | Önceki metin | Şimdi |
| --- | --- | --- | --- |
| ahsapkasa | Archivo | Literata | Archivo (başlık aynı) |
| istanbulpaletci | Barlow Condensed 500–700 | Barlow 400–600 | Archivo, başlık wdth 75 |
| ithal/kavakkeresteci | Zilla Slab 600/700; ölçü etiketi Saira Stencil One | Source Sans 3 | Archivo, başlık wdth 75 800, etiket wdth 62 900 |
| İstanbul Keresteci | Archivo | Archivo | değişmedi |
| Sanayi Palet | Big Shoulders 600–800; damga Big Shoulders Stencil 800 | Instrument Sans | Archivo, başlık ve damga wdth 62 |
| Ahşap Ambalaj | Archivo | Literata | Archivo |
| Koçist | Archivo | Archivo | dokunulmadı |

Değişen yerler: temaların `functions.php` Google Fonts adresi ve font jetonları
(`build/*.css`, `sanayi-palet-theme/assets/css/tokens.css`,
`ahsapambalaj-theme/assets/src/tailwind.css`). Geri dönmek için adres ve jeton eski
değerine alınır, CSS yeniden derlenir.

### Üç keresteci sitesinin üst kısmı

İthal ve Kavak Keresteci'nin üst kısmı İstanbul Keresteci'ninkine benzetildi: ince
bilgi şeridi (adres, e-posta, çalışma saati), koyu menü bandı, çam ağaçlı iki satır
logo (üst satır sitenin adı, alt satır "Keresteci"), sağda "Fiyat ve stok için arayın"
telefon kutusu. Logo işareti İstanbul Keresteci'nin çizimiyle aynı; renkler her sitenin
kendi parti boyası (ithal petrol, kavak yeşil-kahve). Baş harf rozeti (İK/KK) ve Koçist
logosu iki siteden de kaldırıldı (alanlar: `logo_mark`, `parent_logo`, `parent_label`,
`parent_url` manifestten çıktı; yeni `phone_note`).

### İstanbul Paletçi ↔ Sanayi Palet: aynı hero zemini

Kullanıcı: iki site kuzen; hero arka planı benzesin, bileşenler değil. Paletçi'nin hero
zemini artık Sanayi Palet'inkiyle aynı: palet duvarı fotoğrafı (`resources/palet-duvari.jpg`,
Sanayi Palet temasındaki müşteri onaylı görsel) ve üstünde düz, yarı saydam perde (Sanayi
Palet'teki gibi %58). Perde rengi Sanayi Palet'te yanık kahve, Paletçi'de koyu indigo;
föy, ölçü çizgileri ve bilgi şeridi aynı kaldı.

### Ahşap Ambalaj: ahsapkasa'dan ayrışma

Ahşap Ambalaj, ahsapkasa'nın neredeyse kopyasıydı (aynı Koçist logosu, düzen, renk).
Tema bozulmadan (alanlar, sayfalar, yapı aynı) şu kısımlar değişti:

- **Logo**: Koçist varsayılanı yerine sitenin kendi adı; ihracat sandığına basılan
  şablon etiketi gibi çerçeveli dar büyük harf ("AHŞAP AMBALAJ / SANAYİ"). Tema içindeki
  Koçist logo dosyası artık otomatik kullanılmıyor; panelden yüklenen logo öncelikli.
- **Eylem rengi**: orman yeşili (ahsapkasa ile aynı) → sandık uyarı etiketlerinin pas
  kırmızısı `#9B3A1F` (jeton adı `forest` → `signal`).
- **Hero**: ortalanmış başlık yerine sola hizalı dar başlık, soldan koyulaşan perde.
- **Giriş**: ortalı kutu yerine iki sütun (solda dar başlık, sağda metin).
- **Ürün grubu**: dört dikey kart yerine iki sütunlu yatay satırlar.
- **Teklif şeridi**: yeşil bant yerine koyu bant, solda metin sağda düğme.

Tailwind kaynağı temanın içinde kaldı (`assets/src/tailwind.css`); derlemek için
`cd build && npm run build:ahsapambalaj` (`build/theme-src.mjs`). Formdaki büyük/küçük
harfli belirteç hatası da düzeltildi (nesne önbellekli sunucuda başarı mesajı
kaybolurdu).

Metin düzeyinde hâlâ ahsapkasa ile ortak cümleler var (Hakkımızda); bunlar içerik
kararı, firma/Emirhan tarafından yazılacak.

### Panelde SEO ve GEO uyumluluk puanı

SEO ve GEO sekmesinde her site için 0–100 puan (SEO ve GEO alt puanlarıyla): ağ
özetinde sütun, site görünümünde kontrol listesi (eksik olan, kaybedilen puan, nasıl
düzeltileceği). Puan gerçek veriden hesaplanır: firma bilgisi, başlık ve açıklama
uzunlukları, paylaşım görseli, llms.txt, grup ilişkisi, harita konumu, soru-cevap
sayfası, ürün şartnameleri, siteler arası tutarlılık. Siteye uymayan kontrol (ürünü
olmayan sitede ürün şartnamesi) başarısız sayılmaz, hesaba katılmaz. İlk ölçüm:
ahsapkasa 86, istanbulpaletci 90, ithal 90, kavak 90, Koçist 81, İstanbul Keresteci 83,
Sanayi Palet 83, Ahşap Ambalaj 76. Ortak eksik: hiçbir sitede harita koordinatı yok.

### Formlar: e-posta bildirimi

7 sitenin formu da gönderimi kaydediyordu (yönetimde "Teklif İstekleri" / "İletişim
Mesajları") ama hiçbiri e-posta göndermiyordu. Eklentiye ortak modül eklendi
(`includes/forms.php`): yeni kayıtta sitenin firma e-postasına (SEO sekmesindeki; yoksa
yönetici e-postası) bildirim, müşteri e-postası Yanıtla adresinde. Gönderim ziyaretçinin
yönlendirmesinden sonra yapılır; e-posta hatası formu bozmaz, yalnızca günlüğe yazılır.
Yerelde posta sunucusu yok, gönderim bilinçli olarak başarısız olur. Canlıda gereken:
her alan adı için cPanel posta kutusu, SMTP eklentisi, SPF/DKIM/DMARC (README →
"Formlar ve e-posta").

## Eski adres yönlendirmeleri (301) — canlıya geçiş öncesi

Firma sahibinin açık isteği: sitelerin eski adresleri kaybolmasın. Risk alan adı
yeni sunucuya çevrildiği anda başlar; bu yüzden her alan adının listesi DNS
değişmeden önce hazır olmalı. Koçist yarın ayrıca çıkacak; bugünkü 7 site tarandı
(site haritaları + sayfa bağlantıları, yalnızca okuma):

| Alan adı | Eski adres | Yeni sitede aynı | 301 | 410 (demo) |
| --- | --- | --- | --- | --- |
| istanbulpaletci.com | 31 | 14 | 1 | 16 |
| sanayipalet.com | 87 | 10 | 27 | 50 |
| istanbulkeresteci.com | 19 | 16 | 3 | 0 |
| ahsapkasa.com | 2 | 1 | 1 | 0 |
| ahsapambalajsanayi.com | 2 | 1 | 1 | 0 |
| ithalkeresteci.com, kavakkeresteci.com | yalnızca ana sayfa (kocist.com.tr'yi çerçevede gösteriyor) | ✓ | 0 | 0 |

Kararlar:
- **Mekanizma eklentide** (`includes/redirects.php`, panel: SEO ve GEO →
  Yönlendirmeler), `.htaccess` değil: çok siteli ağda tek `.htaccess` 8 alan adını
  birden yönetir ve alan adı değişince kurallar bozulurdu. Kural yalnızca 404'te (ya da
  yalnızca bir görselin ek sayfasına denk gelen adreste) çalışır; var olan sayfayı ezemez.
- **Eski temanın demo içeriği 410**: Sanayi Palet'te vize/koçluk/ülke/ekip (51 sayfa),
  İstanbul Paletçi'de portfolyo (16 sayfa), lorem ipsum. Ana sayfaya yönlendirmek
  Google'da "yumuşak 404" sayılır; 410 dizinden hızlı çıkarır.
- **Sanayi Palet'in 4 blog yazısı yeni sitede yok**; en yakın konuya yönlendirildi.
  Öneri: firmanın kendi yazıları olduğu için yeni sitede aynı adresle yeniden
  yayımlanmaları (kural o zaman kendiliğinden devre dışı kalır).
- İstanbul Keresteci'de 2 yazının adresi kısaltılmış; eski adresler yeni adrese 301.
- Test: 141 eski adresin tamamı yerel kopyada denendi; aynı adresler 200, taşınanlar
  301 ve hedefleri 200, demo sayfalar 410, gerçek sayfalar etkilenmedi.


## Tek adres ve firma yaşı (24 Eylül 2026)

Firma sahibinin kararıyla Koçist dışındaki 7 site tek adres ve tek yaş kullanır:

- **Adres:** Kestanelik Mahallesi Eski Edirne Asfaltı Cad. 2125/1, Çatalca, İstanbul.
  Önceden 5 site Şahintepe / Başakşehir, 2 site Kestanelik gösteriyordu; ahsapkasa ve
  Sanayi Palet'te sayfadaki adres ile arama motorlarına verilen adres farklıydı.
  Metinlerdeki "Başakşehir'deki depomuz", "İkitelli'de" gibi ifadeler de Çatalca oldu.
- **Posta kodu yok:** eski kayıtlardaki 34494 Çatalca'nın kodu değil; doğrusu firmadan
  öğrenilince SEO sekmesine girilir.
- **Firma yaşı 50 yıl** (önceden 37, 40, 45 ve "kırk"). Sanayi Palet'teki
  "1980'lerden bu yana" ifadeleri 50 yılla çeliştiği için çıkarıldı; kuruluş yılı
  yazılmadı.
- Koçist sitesi kapsam dışı: orada hâlâ 37 yıl ve 34494 geçiyor.

Canlıda uygulama: Ağ yönetimi → İçerik Stüdyosu → Toplu Güncelleme. Sayfa değişecek
alanları listeler, "Uygula" yazar ve kayıt tutar; kurallar alanın o anki değerine bakar,
tekrar çalıştırmak bir şey değiştirmez. Tema varsayılanları da aynı değerlere çekildi.

## İstanbul Palet Çivi (9. site, 24 Eylül 2026)

istanbulpaletcivi.com yeniden tasarımı: `istanbulpaletcivi-theme`.

- **Renkler mevcut siteden:** lacivert `#002E5B`, gece `#0B0F3A`, sarı `#FDE428`.
  Sarı yalnızca ölçü cetvelinde, koyu zemindeki birincil düğmede ve odak halkasında.
- **Tek belirgin öğe sarı cetvel:** milimetre çentikli şerit (1 mm = 4 px). Çivi boy
  boy satılan bir ürün; ana sayfada ve her iç sayfa başlığının altında.
- **Yazı tipi grubun ortak Archivo'su;** başlıklar dar ve kalın. CSS elle yazıldı
  (`assets/site.css`), Tailwind derlemesi gerekmez.
- **İçerik:** eski sitenin 5 sayfası, 3 ürün sayfası (`/civiler/<ürün>/`) ve 4 blog
  yazısı aynı adres ve tarihle. Teknik olarak yanlış anlatımlar düzeltildi: "dökme
  çivi" dizisiz (toplu satılan) çividir, döküm değil; "tele dizili çivi" şerit
  tabancasıyla çakılır, betonarmeyi güçlendirmez. Hakkımızda çiviye göre yazıldı
  (eski metin Koçist'in kereste metninin kopyasıydı). Ölçü, stok ve fiyat bilgisi
  uydurulmadı: ürün sayfalarında "Ölçü ve fiyat için arayın".
- **Grup tutarlılığı:** Kestanelik / Çatalca adresi, 50 yıl, info@kocist.com.tr,
  WhatsApp 0532 374 98 32. Toplu Güncelleme kapsamında.
- **Görseller yapay zekâ ile üretildi** ve sayfada "Örnek görsel" olarak işaretli.
  Logo firmanın mevcut logosu.
- **Kurulum veritabanı taşımadan:** tema bir siteye atanınca sayfaları, yazıları,
  ön sayfa ve kalıcı bağlantı ayarını, Türkçe dili ve eski demo adreslerinin 410
  listesini kendisi kurar (`inc/setup.php`). Canlıda: Git deploy → Ağ Yönetimi'nde
  site aç → temayı etkinleştir.

## Telefonlar, tek adres ve 50 yıl: on site (25 Eylül 2026)

Kullanıcı kararı. Toplu Güncelleme kuralları genişletildi (`includes/admin/bulk-update.php`):

- **İki hat grubu.** Koçist ve WOOD KOCIST: sabit 0212 648 19 19, cep/WhatsApp
  0549 648 19 19. Diğer sekiz site: sabit 0212 648 10 90, cep/WhatsApp 0532 374 98 32.
  Telefon alanlarına sabit hat, WhatsApp/cep alanlarına cep yazılır; metinde geçen
  numara kendi türündeki hatla değişir. Yalnızca bu dört numara tanınır. Boş WhatsApp
  bağlantısı (Ahşap Kasa, Ahşap Ambalaj) sitenin cep hattıyla dolar. Arama motorlarına
  verilen firma telefonu (JSON-LD) her sitede sabit hat.
- **Koçist ve WOOD KOCIST de kapsamda:** adres Kestanelik ... 2125/1, Çatalca, İstanbul
  (34494 kaldırıldı; Koçist tema varsayılanı da boş); Koçist'teki "37 yıl" 50 yıl oldu.
  WOOD KOCIST'in "yarım asırlık" ifadeleri aynı anlamda olduğu için kaldı.
- Tek telefon alanı olan temalarda (Koçist, İthal/Kavak Keresteci) cep numarası metin
  olarak görünmez, WhatsApp bağlantısında durur.

Canlıda: Git deploy'dan sonra Ağ yönetimi → Geliştirici → Toplu Güncelleme → Uygula
(veritabanı Git ile taşınmaz).

## Ürün Havuzu: detay başlıkları ve kategori bazlı Excel (0.21.0, 29 Eylül 2026)

Plan: `PLAN-detay-basliklari.md` (mimar planı + kullanıcı kararları 14b).

- **Veri:** teknik detaylar `_nwcs_details` (sıralı etiket/değer). `_nwcs_spec` ondan türetilir;
  ikisini yalnızca `nwcs_product_write_details()` yazar. Okuma tembel (kayıt yoksa spec
  ayrıştırılır), migrasyon yok. Havuz önbelleği `nwcs_pool_products_v3`.
- **Başlık kaydı** ağ geneli site option `nwcs_product_headings` (anahtar = Türkçe harfleri
  sadeleştirilmiş etiket). İlk açılışta spec metinlerinden kurulur; sıra, etiketin ürünlerdeki
  ortalama yeri. **Hiçbiri kendiliğinden zorunlu değil:** zorunluluk globaldir ve Koçist'in
  228 ürününde detay yok; kendiliğinden zorunluluk onların Excel satırlarını reddederdi.
  Zorunluluk Detay başlıkları ekranından, "… üründe boş" sayısı görülerek verilir.
- **Excel:** serbest sihirbaz kaldırıldı. Kategori taslağı indirilir, `_bilgi` gizli sayfası
  kategoriyi taşır, sütun eşleştirmesi yok. Sistem sütunları büyük harfli başlıklarıyla
  birebir tanınır ("FİYAT"), böylece "Fiyat" adlı bir detay başlığıyla karışmaz. Boş hücre =
  temizle. Önizleme → uygula → tek seviyeli geri alma; hesap `admin/sync.php`, ekran
  `admin/import.php`, JavaScript gerekmez (admin-post + transient).
- **Çöp kutusu:** Excel'den düşen ürün ve formdaki "Çöp kutusuna taşı" artık silmez, çöpe
  atar; site seçimleri korunur, geri getirilince ürün eski yerine döner. Kod araması çöpü de
  kapsar (`nwcs_product_id_by_code`), aynı kodla ikinci ürün açılmaz. Geri getirme WordPress'in
  "taslak" varsayılanı yerine önceki durumu kullanır (`nwcs_untrash_product`). Geri alma
  `post_name` yazmaz: çöpteki ürünün adı `…__trashed` olur, yazılsaydı adres bozulurdu.
- **Yeni ürün sitelerde:** planda yalnızca "eşlik eden kategori" vardı; aynı mantıkla yeni
  ürün, kategorinin *bütün* ürünlerini gösteren sitelerde de seçilir (aksi hâlde Excel'den
  eklenen ürün hiçbir sitede görünmezdi). Önizlemede yazar, geri alma seçimden çıkarır.
- **Ürün açıklamaları ekranı:** HTML ↔ düz metin. Koçist'in 182 açıklamasında ölçü tablosu
  var; planın "karmaşık HTML → işaret" kuralı bunların hepsini kilitlerdi. Tablolar dosyaya
  girmez, metin yazılırken sona aynen eklenir; yalnızca görsel/bağlantı gibi içerik işaretlenir
  (yerelde 0 ürün). Alıntı (`blockquote`, WK'da 9 ürün) "> " satırlarıyla taşınır.
- **Açıklamasız ürün sayfası:** kullanıcı kararı "sayfa kapanmaz". Kural eklentide tek yerde
  (`nwcs_product_has_page`), temalar manifestte `product_page_always` ile açar; yalnızca
  WOOD KOCIST ve Koçist. Diğer siteler (İstanbul Keresteci vb.) eski kuralda: açıklamasız
  ürün kartı teklife gider. Koçist'te açıklamasız 3 ürün vardı; sayfaları artık açık.
- **Temalar:** `nwcs_product_pairs()` ürünün KENDİ sırasını döndürür (inceleme sonrası karar:
  kayıt sırası sitedeki mevcut sayfaların sırasını değiştirmesin; blog 13 ve 8'de 0 fark ölçüldü).
  Kayıt sırası yalnızca Excel sütunlarını ve formda eksik zorunlu satırın yerini belirler; tohum
  sırası WK'nın olağan çekirdek sırasıyla başlar. Excel'den detayı değişen ürün sütun sırasıyla
  yazılır, detayı değişmeyen ürüne dokunulmaz. Teknik detay föyünün ilk
  satırı "Ürün kodu" (iki sütunlu düzende tam genişlik, kalın ayraç). Açıklaması olmayan
  üründe föy 3'ten az özellikle de açılır (yoksa bir iki özellik hiç görünmezdi).
- **Havuz Paketi v2:** `details` ve `headings` taşır; v1 kabul edilir (spec'ten okunur).
- **xlsx yazıcı** kitaplıksız (ZipArchive): satır içi metin + "Metin" sayı biçimi, gizli sütun
  ve sayfa, dondurulmuş kalın başlık. Okuyucu sayfa adıyla okur ve Excel'in sayı olarak
  sakladığı hücreleri bildirir. `GLOB_BRACE` Alpine'de yok; kullanılmıyor.

### İnceleme sonrası düzeltmeler (30 Eylül 2026)

- Geri alma kaydı uygulamanın başında yazılır, her üründen sonra güncellenir (`complete`
  bayrağı); açıklama uygulaması da öyle ve `set_time_limit(300)`.
- Önizleme transient'i uygulamadan önce silinir; silme başarısızsa (ikinci basış) yazılmaz.
- `_bilgi` → kaynak bu panelden değilse dosya reddedilir; kesilen (20.000+ satır) dosya reddedilir.
- Çöpe gidecek ürünler için de önizleme sonrası değişiklik denetimi.
- Kopyalanmış satır: farklı/boş kodla yeni ürün (not), aynı kodla hata.
- Sistem sütunları `nwcs_heading_key` ile tanınır; ayrılmış anahtarlarla başlık açılmaz; eski
  "Fiyat" başlığı Excel'de "Fiyat (detay)". Etiket temizliği (':' ';') tek fonksiyonda
  (`nwcs_heading_clean_label`), değer temizliği `nwcs_detail_clean_value`; plan da onları kullanır.
- `EMPTY_TRASH_DAYS = 0` ise çöpe atma yapılmaz. Kalıcı silme `deleted_post` kancasıyla
  seçimlerden düşer. Site seçimi kaydedilince çöpteki ürün eski yerinde tutulur.
- Yüklenen dosya `move_uploaded_file` ile eklentinin geçici klasörüne alınır, okunduktan sonra silinir.
- Paylaşılan kategori listede ve önizlemede belirtilir; "hepsi" kipindeki siteler de "gösterilecek"
  listesinde; önizlemede önceki yüklemenin "Geri al" şeridi gizlenir.
- `scripts/import-kocist.php`, `import-woodkocist.php`, `seed-products.php` tek yazıcıyı kullanır.

## Ürün Havuzu: kategori ↔ site yerleşimi, Kategoriler sayfası, Son işlemler (0.22.0, 30 Eylül 2026)

Plan: `PLAN-faz2-kategoriler.md` (kullanıcı onayı: 5 açık sorunun hepsine evet, geçmiş derinliği 10).

- **Yerleşim veridir.** "Hangi kategori hangi sitede hangi üst başlığın altında" havuz
  kategorisinin terim meta'sında (`_nwcs_placement = { site_key => üst başlık }`). Taksonomi düz
  kalır. Üst başlıklar sitenin kendi panel satırlarıdır (WK: Ana Sayfa → Seriler; Koçist: Üst
  Menü'de "Ürün grubu anahtarı" dolu satırlar). Temalar kendi kategori listesini taşımaz.
- **Kategori sayfaları eklentiden:** manifestteki `catalog` şablonundan `kat-<havuz-slug>` ve
  `seri-`/`grp-<anahtar>` sayfaları üretilir (`nwcs_manifest_for_blog`, blog başına istek içi
  önbellek; taksonomi kayıtlı değilken önbelleğe alınmaz). WK'nın 7 kategori sayfası birebir
  aynı üretildi (manifest ve HTML karşılaştırması 0 fark).
- **Koçist:** takma adlar kalktı; Köpek Kulübeleri, Kedi Yuvaları ve Piknik Masaları Dekorasyon
  altında kendi alt başlığı. Dört alt adres havuz slug'ına geçti (301). Kategori adları eski menü
  metinleriyle aynı kaldı (`catalog.defaults[*].name` → `kat-<slug>` sayfasının "Kategori adı"
  alanı); `menu_*` bileşenlerinde yalnızca kategori olmayan bağlantılar (Klipsler, Bağlama
  Telleri) kaldı. Grup kategorisinde (Kereste, Ahşap Ambalaj…) olan ürün doğrudan gruba düşer
  (`catalog.parent_pool`).
- **Görünürlük yalnızca ekler:** yerleşim, kategoriye ekleme, yaratma ve çöpten dönüş seçimin
  sonuna ekler; kaldırma yalnızca o sitede başka yerleşik kategorisi olmayan ürünleri çıkarır
  (sayı sorulur, geri alınabilir). "Eşlik eden kategori/site" kuralları kaldırıldı.
- **Tohum** site başına bir kez (`nwcs_placement_seed_version = { site_key => 1 }`), `init`'te:
  canlıya dağıtımdan sonraki ilk istekte kategori sayfaları boş kalmasın diye yönetici girişi
  beklenmez. Yalnızca boş yerleşimi doldurur; seçimlere dokunmaz (blog 8/13 md5 aynı).
- **Son işlemler:** `nwcs_pool_history` (son 10), yalnızca en üstteki geri alınır. Geri alınan
  ürünün `post_modified` değeri de eski hâline konur (yoksa alttaki işlemin "elle düzenlendi"
  denetimi yanlış alarm veriyordu — QA 4). Geri almada yalnızca gerçekten geri alınan ürünler
  site seçiminden çıkar.
- **Havuz Paketi v3** yerleşimleri taşır (hedefte boş olan kategori/site çiftine yazar); v1, v2
  kabul edilir.
- **Panel:** Ürün Havuzu alt sayfaları Ürünler · Kategoriler · Detay başlıkları · Ürün
  açıklamaları · Havuz Paketi. Kategoriler sayfası tek ekranda ad, "nerede görünüyor" cümlesi,
  Sitelerde, Excel, ürün listesi (son yüklemede değişenler vurgulu), Son işlemler. Yeni kategori
  formunda benzer ad uyarısı (engellemez). Detay başlıklarında "Boş olduğu kategoriler" sütunu
  ve zorunlu yapılınca takılacak kategoriler bildirimi.
- **Bilinen, bu işin dışında:** havuz ürün önbelleği bir alt site isteğinde yeniden kurulursa
  görsel adresleri `/woodkocist/wp-content/uploads/...` biçiminde yazılıyor (çalışıyor, adres
  farklı); Koçist'in ek sayfaları site haritası işareti taşımıyor.

### İnceleme sonrası düzeltmeler (0.22.0, 30 Eylül 2026)

- **Site önbelleği:** havuz yazmalarından sonra LiteSpeed (site başına `litespeed_purge_all`) ve
  isteğe bağlı Cloudflare (`NWCS_CF_ZONE_ID`, `NWCS_CF_API_TOKEN`) istek başına bir kez
  temizlenir; sonuç panelde kısa not. LiteSpeed'in çoklu sitede site başına mı yoksa bütün
  sunucuyu mu temizlediği canlıda doğrulanmalı (yerelde eklenti yok; Havuz Paketi ile aynı yol).
- **Tohum ön denetimi:** yalnızca temasında `catalog` olan sitenin isteğinde ve anahtarı
  tohumlanmamışsa; havuzu tüketmeyen sitelerde manifest taranmaz.
- **Üst başlık anahtarı slug:** yerleşim anahtarı satır metninin slug'ı; başlığın havuz
  kategorisi slug ile bulunur (Koçist `parent_pool` slug). Artık olmayan başlığa bağlı
  yerleşim ("yetim") sessizce taşınmaz ya da silinmez; Kategoriler sayfası uyarır.
- **Üst başlık kategorileri** (WOODPets, Dekorasyon…) etiketli, "görünmeyen" sayımına girmez,
  yerleştirilemez, silinemez.
- **Kategori silme** sunucu tarafı onaylı (kaç ürün hangi sitede görünmez olur) ve "Son
  işlemler"den geri alınabilir (kategori, ürün bağları, yerleşim, site seçimleri). Toplu
  budama yalnızca ürünü ve yeri olmayanları siler.
- Kategori ekranı kendi son yüklemesini gösterir; üstte başka işlem varsa geri alma sırasını
  söyler. WK'da ürünü olmayan yerleşik kategori için panel "bu sitede ürünü olunca görünür"
  der (sitede 404 kalır; Koçist'te boş kategori sayfası mevcut davranış).


## WOOD KOCIST ürün sayfası: canlı düzenin kopyası (30 Eylül 2026)

Firma canlı woodkocist.com.tr ürün sayfasını seviyor ("değiştirmeyin"); plan
`PLAN-wk-urun-sayfasi.md`. Yalnızca `woodkocist-theme` değişti; eklenti ve Koçist aynı.

- **Tek iskelet, tüm ürünlerde:** konum → [özet | galeri] → özetin altında 4 başlıklı akordeon
  (Teknik Detaylar açık, Ürün Açıklaması, Lojistik ve Teslimat, Müşteri Görüşleri) → İlgili ürünler.
  Ayrıntı seviyesi (rich/medium/table/brief), özellik şeridi ve föy WK şablonundan çıktı
  (eklentide duruyor, Koçist kullanıyor).
- **Ölçüler canlının hesaplanmış stillerinden:** sütunlar 472:728, arası 80px, sticky yok;
  h1 32/40 normal kalınlık (telefonda 24/30); fiyat 32/500 ve altında üç nokta; adet 128×50 +
  "Sepete ekle" 50px; tam genişlik yeşil **Hızlı Sipariş** (#0b6f31, WhatsApp); akordeon başlığı
  18/26 #ad4d3e (canlıdaki #b65545 krem zeminde 4.37:1 kalıyordu; #ad4d3e 4.89:1, gözle aynı);
  tablo satırı 40px. Yazı tipi Archivo (normal genişlikte); sayfa zemini sitenin.
- **h1 = kod + ad** (`wk_product_heading`; ad kodla başlıyorsa yalnızca ad). Konum satırının son
  öğesi, JSON-LD `name` ve `<title>` da bu.
- **Lojistik ve Teslimat:** panelde site geneli metin (`product.labels.delivery_text`); üründe
  "Lojistik ve Teslimat" adlı detay satırı varsa o gösterilir, tablodan ve JSON-LD
  özelliklerinden düşer. "Farklı ölçü mü lazım? Özel üretim isteyin" bu panelin altında.
- **Müşteri Görüşleri:** yorum sistemi yok; dürüst boş metin ("Henüz değerlendirme yapılmadı.").
- **Akordeon erişilebilirliği:** `h2 > button[aria-expanded][aria-controls]`, panel `role=region`;
  aynı anda tek panel açık (açık başlık tekrar basılınca kapanır); JavaScript yoksa tüm paneller açık.
- **Küçük resimler** görselin üstüne bindirilmiş, ortalı sıra (canlıda 60px, köşe 6px, pasif %30).
  Telefonda 44px (dokunma hedefi) ve sığmazsa tek satırda yana kayar; canlıda 27px.
- **Fotoğrafsız ürün:** kod plakası fotoğrafların oranında (1280×1714); düzen fotoğraflıyla aynı.
  Tablette (600–1099px) plaka da fotoğraf gibi `100svh - 220px` ile sınırlı, oran korunur.
- **İçerik Stüdyosu önizlemesi:** önizleme kipinde (`nwcs_is_preview()`) dört panel açık başlar;
  kapalı paneldeki alanlar da tıklanıp vurgulanabilir. Ön yüz etkilenmez.
- **1100–1279px:** küçük resimler 50px, aralık 8px (8 resim tek satırda kalsın).
- **Arama açıklaması yedeği:** kısa açıklama ve detay metni boşsa ad + ilk üç teknik detay
  (`wk_product_fallback_description`); yerelde böyle ürün yok.
- Panelden kalkan alanlar: `wa_question`, `wa_price`, `ask_why` (yerlerini `wa_order` aldı).
  Yeni alanlar: `wa_order`, `specs_empty`, `tab_desc`, `desc_empty`, `tab_delivery`,
  `delivery_text`, `tab_reviews`, `reviews_empty`, `reviews_note`. `specs_title` → "Teknik
  Detaylar", `related_title`/`related_all` → "İlgili ürünler".
- **Bilerek farklı kalanlar:** konum satırında "Mağaza" (canlıda yok), renk seçimi yok (Teknik
  Detaylar satırı), etiket ve favori yok, "Sepete ekle" tuğla renginde (canlıda varyasyon
  seçilmeden gri), KDV notu `vat_mode`'a bağlı (canlıda "+ KDV"), sayfa zemini krem.

## Koçist ürün sayfası: canlıdaki görsel + form, altta akordeon, dürüst fiyatsız durum (30 Eylül 2026)

Plan `PLAN-kocist-urun-sayfasi.md` (kullanıcı kararları §6b, uygulama notları sonda). Eklenti
0.22.1 (yalnızca `includes/product-headings.php`'ye üç yardımcı), Koçist teması, WOOD KOCIST'te
küçük değişiklik.

- **Veri yuvası modeli:** ürün sayfasının her yuvası (başlık, kod, fiyat, kısa açıklama, galeri,
  Teknik Detaylar, Ürün Açıklaması, Lojistik ve Teslimat, ilgili ürünler) iki sitede aynı havuz
  alanından dolar; işaretleme temaya ait. Ortak kural eklentide: `NWCS_DELIVERY_LABEL`,
  `nwcs_product_is_delivery_pair`, `nwcs_product_table_specs` (detaylar − "Lojistik ve Teslimat";
  detayı olmayan üründe serbest ölçü notu "Ölçü" satırı), `nwcs_product_delivery_row`. WK'nın
  `wk_*` yardımcıları bunlara devreder (55 sayfanın HTML'i aynı kaldı).
- **Koçist düzeni:** ≥1024 `[galeri + akordeon | ad, fiyat, kısa açıklama + yapışık teklif formu]`;
  1024 altında galeri → ad/fiyat → akordeon → form, üstte "Teklif formuna git" bağlantısı
  (forma kaydırır, ilk alanı odaklar). Kaynak sırası telefondaki sırayla aynı (okuma ve klavye
  sırası bozulmaz). Form kartı yapışık, adın bulunduğu blok değil: ikisi birlikte ~950px ve
  900px ekranda Gönder düğmesi görünmez oluyordu. Kartta alanlar iki sütun (Ad | Şirket,
  Telefon | E-posta; telefonda tek sütun): kart ~550px, hata satırlarıyla ~680px; yüksekliği
  `100svh - 100px` ile sınırlı, fazlası kartın içinde kayar. 1366×768 ve 1280×800'de Gönder
  hata durumunda da görünür.
- **Görsel:** kendi oranında, kırpılmadan; fotoğrafsız üründe yer tutucu WK plakasının oranında
  (1280/1714, dikey), tablette `100svh - 220px` sınırı (WK kalıbı). 16:9 kullanılmadı.
- **Akordeon:** Teknik Detaylar açık başlar; tabloda koddan başka satır yoksa (227 ürün) Ürün
  Açıklaması. Tek panel açık, açık başlık tekrar basılınca kapanır, JavaScript yoksa hepsi açık,
  panel önizlemesinde hepsi açık. Müşteri Görüşleri yok (veri yok; SSS var).
- **Form:** `inc/form.php` iki formda ortak. Ürün formunda mesaj yerine zorunlu Adet / Ölçü
  (`_kc_size`, e-postada "Ölçü ve adet"), isteğe bağlı Şirket (`_kc_company`, "Firma"). Onay
  kutusu (`kc-consent`) iki formda zorunlu; KVKK sayfası gelene kadar bağlantısız metin, alt
  şeritteki KVKK bağlantısı gerçek adrese dönünce bağlantı kendiliğinden çıkar. Sahte başarı yok.
  Form alanlarının CSS'i `assets/css/form.css`'e taşındı (iletişim sayfası görünümü aynı).
- **Fiyatsız durum (A + B; C ertelendi):** fiyat yerine "Fiyat teklifle" + nedeni. Neden ürüne
  bağlı (stok durumu, ölçü, adet; WK'da sipariş üzerine üretim); "size özel fiyat" izlenimi yok.
  Koçist: site geneli metin + grup bazlı ezme alanları (boş başlar). Ürün bazlı "fiyat notu"
  Excel sütunu (C) şimdilik yok: plan/uygula/geri al zincirinin her halkasına dokunuyor ve
  girilecek veri yok; gerekirse `kocist_price_reason()` önce ürün alanını okuyacak şekilde genişler.
  "Aynı gün fiyat veriyoruz" notu (`product.main.note`) ve ürün SSS'sindeki aynı cümle
  kaldırıldı. Kategori sayfası metinleri, iletişim alt başlığı ve footer şeridindeki "AYNI GÜN
  FİYAT / TOPTAN FİYAT GARANTİSİ" ayrı içerik kararı (kullanıcıya soruldu). Ana sayfa kartlarına
  dokunulmadı ("Koçist ana sayfasına dokunma" kuralı).
- **JSON-LD:** `sku` ve yalnızca düz tutarda `offers` (`kocist_price_number`: "450 TL", "3.500 ₺";
  "$" içeren fiyat USD). "…'den başlayan" ve çok tutarlı metinde `offers` yok (35/37 fiyatlı ürün).
  Offer'da KDV bilgisi ve `availability` yok (veride yok; uydurulmaz). "0: BOŞ KULP 3 (75); 1: …"
  gibi numaralı aktarım artığı ne tabloda ne JSON-LD'de "Ölçü" olur.
- **Form dönüşü:** ürün formu gizli `kc_form=product` ve `kc_return` (ürün adresi,
  `wp_validate_redirect`) taşır; Referer göndermeyen tarayıcıda da ürün sayfasına döner. Dönüşte
  ilk hatalı alan, yoksa sonuç bandı odaklanır. Durum kaydı (transient) okununca silinir:
  yenilemede ya da paylaşılan adreste girilen bilgiler görünmez.
- **Akordeon ilk boyama:** `<html>`'e erken `k-js` sınıfı; kapalı paneller betik çalışmadan da
  kapalı (sıçrama yok). Betik yoksa sınıf da yok, paneller açık.

## 30 Eylül 2026 — Sık sorulan sorular (7 site, tek model)

Plan: `PLAN-sss.md` (§6b kullanıcı kararları bağlayıcı).

- **Tek model:** manifest `faq` sayfası, `/sss/`, `seo_source.type = FAQPage`; grup + soru +
  cevap satırları. Eklentide yalnızca okuyucu yardımcı (`nwcs_faq_rows`, `nwcs_faq_groups`);
  kayıt yolu, panel ve SEO kodu değişmedi.
- **SSS cevabı yalnızca firma kaynaklı metinden; tema için yazılmış süreç/teslimat/ödeme
  metinleri SSS'de cevap olarak kullanılmaz** (bağımsız inceleme sonrası: Sanayi Palet
  "Sipariş nasıl ilerler?" / "Teslimat nasıl yapılıyor?", Koçist "Ödeme nasıl yapılır?", Ahşap
  Ambalaj "ISPM 15" / "Bir ambalaj işi nasıl ilerler?" cevapları boşaltıldı; Koçist teklif
  cevabından süreç vaadi çıkarıldı).
- **Cevap politikası:** cevap yalnızca sitenin kendi, firmadan gelmiş metninden türetildi
  (ürün metinleri, teklif formu notu, belge bandı). Süre, rakam, vaat içeren
  sorular (sevkiyat süresi, minimum sipariş, ödeme, teslimat bölgesi…) cevapsız; cevapsız soru
  hiçbir yerde görünmez. Koçist'te metreküp formulü grubun kereste sitelerindeki (İthal Keresteci)
  aritmetik örnekten; firma iddiası değil.
- **Koçist:** 283 ürün sayfasında aynen basılan 5 soruluk `product.faq` kaldırıldı (kümese göre
  yazılmıştı, kereste ürününde yanlıştı; ayrıca FAQPage işareti yoktu). Ürün sayfasında
  akordeonun son satırı ürünün grubuna bağlantı: `/sss/#sss-<grup>` (SSS grup adı =
  ürün grubu, `sanitize_title` ile eşlenir); grupta cevaplı soru yoksa `/sss/`.
- **"Aynı gün" / garanti (seçenek A):** Koçist'te footer şeridi (YAZILI FİYAT TEKLİFİ, TOPTAN
  SATIŞ), güven şeridi "Toptan satış", kategori ürünlü/ürünsüz metinleri, iletişim alt başlığı
  ("yazılı teklif gönderelim"), ürün teslimat akordeonu ("Sevkiyat şekli ve süresi teklifle
  birlikte yazılı olarak bildirilir."); İstanbul Keresteci'de fiyat şeridi, form açıklaması ve
  form başarı mesajı nötrlendi. Panelde bu alanlara kayıt yapılmışsa eski metin görünmeye devam
  eder; canlıda kontrol edilmeli.
- **Tasarım:** her site kendi jetonlarıyla; soru listesi tipografik: kart ve numara yok, ince
  çizgi, `<details>` (betik yok), + / −, cevap ≤ 70ch. Geniş ekranda grup adı solda yapışık
  (Koçist, paletçi, keresteci, çivi, sanayi); Ahşap Ambalaj/Kasa tek kolon. Panel
  önizlemesinde cevaplar açık, tıklanınca doğru satır (`_row`) açılır.
- **Ana sayfaya seçme sorular yok** (kullanıcı kararı).
- **Menü bağlantısı:** `/sss/` satırı, sayfa henüz açılmamışsa (yayında değilse) menü ve alt
  bilgiden gizlenir (`*_hide_missing_sss`, Ahşap Ambalaj/Kasa'da `*_menu`); deploy ile yönetici
  girişi arasındaki sürede ziyaretçi ve önbellek 404 bağlantısı görmesin. Panel önizlemesinde
  satır kalır.
- **Kurulu sitede sürüm artışı** yalnızca yeni sayfayı açar (İstanbul Keresteci, Sanayi Palet,
  Palet Çivi); tam kurulum slogan, ön sayfa, kalıcı bağlantı ve dil ayarını yeniden yazmaz.
  Koçist'te `kocist_ensure_pages` (sürüm 5) eskisi gibi tam listeyi idempotent çalıştırır.
- Koçist ana sayfa süreç adımı 3 (`home.process`) teslimat akordeonuyla aynı nötr dile çekildi
  (koordinatör talebi; ana sayfanın başka yerine dokunulmadı).

## Fotoğraf Kutusu: dosya adı = ürün kodu (0.23.0, 2 Ekim 2026)

- **Neden koda göre eşleme:** Excel'in anahtarı ürün kodu; fotoğrafın anahtarı da ürün kodu
  olunca kullanıcı tek kural öğrenir. Görsel tanıma ya da ürün adına göre tahmin yok: yanlış
  ürüne bağlanan fotoğraf sessiz hatadır. Ayraç serbest, önek + en uzun kod; 5+ karakter
  kodlarda içerme yedeği (mevcut "woodpets-w-dog-v06-img010" adları çalışsın diye).
- **Neden parti = medya kitaplığı (geçici klasör yok):** yükleme panelin WebP dönüşümünden
  aynen geçer, ara boyutlar bir kez üretilir, uygulama yalnızca meta yazar. Bekleyen ekler
  `_nwcs_photo_batch` ile işaretli; Medya Havuzu'nda gizli, 24 saatte süpürülür.
- **Neden plan transient'ta saklanmaz:** önizleme her görünümde partideki eklerden ve havuzun o
  anki kodlarından yeniden hesaplanır; bayat plan yoktur. Uygulama ilk iş bekleyen partiyi siler
  (iki kez basma güvenli), ürün başına yazmadan önce kayda geçer (yarıda kesilirse geri alınır).
- **Geri alma:** galeri uygulamadaki hâlindeyse eskisi yazılır, değilse dokunulmaz (kept);
  yalnızca bu yüklemede gelen ve başka üründe kullanılmayan dosyalar silinir.
- **Ürün kodu zorunlu; otomatik kod verilmez (kullanıcı kararı, 2 Ekim 2026).** Kodları firma
  girer ve fotoğrafları buna göre adlandırır. Ürün formu boş kodla kaydetmez; Excel'de ÜRÜN KODU
  boş satır hata verir ve yüklenmez; yeni ürüne URN kodu üretilmez (`nwcs_ensure_product_code`
  artık çağrılmıyor). Yerelde 320 yayındaki ürünün 237'si kodsuz (Koçist ağırlıklı); kodları
  girilene kadar bu ürünlere fotoğraf bağlanamaz, Excel'de satırları hata verir. Havuz Paketi
  (geliştirici aracı) paketteki kodu yazar; kod boşsa ya da başka üründeyse ürünü kodsuz ekler,
  otomatik kod vermez; ona dokunulmadı.
- **C (Excel'e fotoğraf sütunu) ertelendi:** dosya adı kuralı yeterli görüldü.
- **"Kitaplıktan ekle" arama oldu:** son-200 listesi yerine sunucu araması (JS'siz GET formu).
  Stüdyo'daki site istisnası görsel listesi kapsam dışı (kullanıcı kararı).

## Ürün sayfası adresi: site başına düzenlenebilir (8 Ekim 2026)

Plan: `PLAN-urun-adresi.md`. Yeni sistem yok; mevcut site istisnalarına (`nwcs_products_overrides`)
iki alan eklendi: `slug` (bu sitedeki özel adres; boşsa havuzdaki `post_name`) ve `slug_old`
(o sitede daha önce kullanılmış özel adresler, en fazla 20).

- **Tek kaynak:** `nwcs_site_products()` satırın `slug` ve `url`'ini özel adresle değiştirir,
  havuz adresi `pool_slug` olarak kalır. Bu yüzden temalarda değişiklik gerekmedi: kartlar, SEO
  (sayfa listesi temadan `url` ile gelir), Koçist teklif formu (`kc_product` = satırın slug'ı)
  ve WOOD KOCIST sepeti (ürün kimliğiyle çalışır) olduğu gibi doğru. Durum şeridindeki
  "Sitede gör" havuz ürününden hesaplandığı için `nwcs_product_site_slug()` ile düzeltildi.
- **301 sırası:** `/urun/<x>/` sitede bulunamazsa önce ürün adres geçmişine (`pool_slug`,
  `slug_old`) bakılır, sonra Yönlendirmeler listesine, en son 404. Hedef her zaman güncel
  adres; zincir yok (eski adres → güncel, tek adım). Sorgu dizesi korunur.
- **Çakışma kuralı (aynı site):** başka ürünün güncel adresi (özel ya da otomatik), çöp
  kutusundaki ürünün özel adresi ve sitenin sayfaları reddedilir. Başka ürünün havuz
  (otomatik) adresi, o ürün sitede özel adres kullansa bile alınamaz: o adres eski ürüne 301
  verir ve arama motorunda kayıtlı olabilir. Başka ürünün *eski özel* adresi alınabilir; o adres
  diğer üründen düşer. "Otomatiğe dön" de aynı kontrolden geçer.
- **Sonradan aynı adla havuz ürünü:** havuz `post_name` yalnızca havuzda tekildir; bir sitede
  özel adresle çakışırsa `nwcs_site_product_by_slug()` özel adresi tercih eder.
- **Adres alanını yalnızca Özelleştirmeler yazar.** `nwcs_save_site_product_settings()` (Stüdyo,
  toplu göster/gizle, Havuz Paketi) kayıttaki `slug`/`slug_old`'u aynen korur; "Özelleştirmeyi
  kaldır" adresi otomatiğe döndürür ama eski adresleri tutar (yönlenmeye devam eder).
- **Eski teklif bağlantıları:** `nwcs_product_slug_resolve()` bir adresin güncel karşılığını
  verir (güncel, havuz ya da eski adres). Koçist `?urun=` ön dolgusu (`inc/quote.php`) ve
  ürün formu (`inc/form.php`, `kc_product`) eşleşmeden önce bunu çağırır.
- **Sürüm 0.23.2:** canlıda statik dosyalar bir yıl önbellekte (immutable); `overrides.js` ve
  `admin.css` değiştiği için sürüm artırıldı.
- Ürün çöpe gidip geri gelince adres korunur (istisna kaydı silinmez). Kalıcı silmede istisna
  kaydıyla birlikte gider.
- Kapsam dışı (kullanıcı kararı): Excel sütunu, `/urun/` kökünü değiştirmek, Stüdyo'da ikinci
  bir adres formu.

## Ortak ürünlerde asıl site: vazgeçildi (8 Ekim 2026)

`PLAN-asil-site.md` uygulandı, müşteri isteğiyle tamamen geri alındı: iki sitede de görünen
ürünün iki sayfası da kendini canonical gösterir, ikisi de aranabilir. Kalan tek parça:
Koçist ürün sayfaları (`/urun/<slug>/`) site haritasına girer (`kocist-theme/inc/catalog.php`,
`'sitemap' => true`); önceden hiç girmiyordu.

## Kategori Excel'inde SİTE sütunu (8 Ekim 2026)

Müşteri aynı ürünün siteye göre ayrı yazılmış kopyalarını açacak; Excel'de hangi ürünün hangi
sitede olduğunu görmek ve seçmek istiyor. Kod: `includes/admin/sync.php` (SİTE bölümü),
önizleme `includes/admin/import.php`, kontrol `scripts/dev/check-site-column.php`.

- **Siteler:** `nwcs_catalog_sites()` içinde teması ürün gösteren siteler (`nwcs_sync_sites()`).
  "Hepsi" bunların tamamı; yeni katalog sitesi kendiliğinden girer.
- **Okuma:** virgül/noktalı virgül; her parça `nwcs_search_fold` + harf/rakam dışı atılır;
  alan adı yazıldıysa ilk parçası. Tanınan: panel adı, `site_key`, alt klasör, (ağ alan adından
  farklıysa) alan adı. İki siteye uyan yazım tanınmaz. `SİTE` ayrılmış başlık anahtarıdır
  (`site`); bu adla detay başlığı açılmaz.
- **Boş = değişmez** (diğer sütunlardaki "boş = temizle"nin bilinçli istisnası).
- **Yazım:** gizleme `hidden` istisnası, gösterme `nwcs_product_show_on_site()` (gizlemeyi
  kaldırır, "Seçilenler" kipinde seçime ekler). Seçimden çıkarmak yerine gizleme seçildi: iki
  kipte de çalışır, kategori yerleşimi ürünü yeniden seçime eklese de gizli kalır, durum
  şeridinde "gizlenmiş" diye görünür. Uygulamanın sonunda (yerleşim eklemesinden sonra), site
  başına en fazla iki yazım.
- **Geri alma:** kayıt `visibility` = site → ürün → önceki `hidden` ve seçimde olup olmadığı
  (yalnızca değişenler). Geri alınan ürünlerde gizleme aynen konur, işlemin seçime eklediği
  çıkarılır. Sonradan elle düzenlendiği için geri alınmayan ürünün sitesi de değişmez.
- Sütun ÜRÜN ADI'ndan sonra (4. sütun); eski taslaklar (SİTE'siz) aynen çalışır.

## Medya Havuzu: alt metinleri Excel ile (0.25.1, 12 Ekim 2026)

Çok sayıda ürün görselinin alt metni (SEO/GEO ve erişilebilirlik) tek tek kartlardan
yazılamıyordu. Kategori Excel'i / Ürün açıklamaları ile aynı akış: indir → doldur → yükle →
önizleme → uygula → "Son işlemler"den geri al. Kod `includes/admin/media-alt.php`, kontrol
`scripts/dev/check-alt-excel.php`.

- **Kapsam:** Tümü (havuz sitesindeki görsel ekleri), Alt metni boş olanlar (meta yok ya da
  yalnızca boşluk), Kategori (yayındaki ürünlerin galerileri; ürün sırası, sonra galeri sırası).
  Fotoğraf Kutusu'nda bekleyen dosyalar (`_nwcs_photo_batch`) hiçbirine girmez.
- **Dosya:** "Görseller" · "Nasıl kullanılır" · gizli `_bilgi` (`tur=alt`, `kaynak`). Başka
  panelden ya da başka ekranın dosyası `nwcs_sync_read_info` ile reddedilir; alt dosyası başka
  ekrana yüklenirse Medya Havuzu'nu söyler. Eşleşme yalnızca gizli `KİMLİK` ile; `DOSYA`,
  `ÜRÜN`, `SİTE` bilgi. Bilinmeyen kimlik, görsel olmayan ek (video), çift satır, kimliksiz
  satır ve 250 karakteri aşan metin satır hatasıdır, diğer satırlar uygulanabilir.
- **Uzun metin kesilmez, hata olur:** yarım cümle sitede yanlış okunur; kullanıcı kısaltsın.
- **Boş hücre = sil** (kullanıcı kararı): meta silinir. Önizlemede "N görselin alt metni
  silinecek" başlığıyla ayrı liste. Karşılaştırma `nwcs_clean_text` ile: yalnızca boşluk
  farkı "değişmedi" sayılır.
- **Geri alma kaydı** (`kind=alt`): `before` = ham önceki değer (meta yoksa `null`), `after` =
  yazılan (silmede `null`). Geri almada şimdiki değer `after` ise `before` aynen konur (null →
  meta silinir); `before` ise dokunulmaz (yarıda kesilen uygulama); başka bir şeyse elle
  değiştirilmiş sayılır, korunur ve bildirilir. Uygulama önizlemeden sonra değişen görseli
  atlar. Kayıt 50 görselde bir, yazmadan önce güncellenir (büyük dosyada option yazımı az).
- `update_post_meta` değeri unslash ettiği için `wp_slash` ile yazılır (ters bölü korunur).
- Uygulama ve geri alma `nwcs_pool_flush_cache()` çağırır: havuz önbelleği (ürün
  görsellerinin alt metni) ve site sayfa önbelleği (LiteSpeed/Cloudflare) temizlenir.
- Medya Havuzu'nun sağ sütunu artık yapışkan değil (üç kart: yükleme, alt metin Excel'i,
  Son işlemler).

## Yorumlar kapatıldı (0.25.2, 15 Ekim 2026)

sanayipalet.com'daki "Ahşap Palet Nedir?" yazısına bir bot kumar reklamı yorumu bıraktı; site
yöneticisine "onay bekleyen yorum" e-postası gitti, kuyrukta 4 yorum daha vardı. Hiçbir tema
yorum formu ya da listesi göstermiyor, ama WordPress yazılarda yorumu varsayılan olarak açık
tutuyor: botlar formu görmeden `wp-comments-post.php`'ye gönderiyor.

- **Tüm ağda kapalı** (`includes/comments.php`): `comments_open` ve `pings_open` her yazıda
  `false`. Yorum gönderimi kaydedilmeden reddedilir (`comment_closed`), REST'ten yorum
  eklenemez, pingback/trackback alınmaz. Site site Tartışma ayarı ve eski yazıların tek tek
  kapatılması yerine kod: 10 site ve yeni açılacak siteler için tek yer.
- **Formlar etkilenmez:** tüm temaların formları `admin-post.php` işleyicileriyle kendi kayıt
  türlerine yazar (`kc_quote`, `wk_order`, `wk_request` …); yorum sistemine dokunan form yok.
- **Yorumlar ekranı yerinde kalır:** daha önce gelmiş yorumlar oradan silinir. Yorum
  istenirse (ör. blog) filtre site bazında açılmalı ve spam koruması eklenmeli.

## Ürün meta başlığı ve meta açıklaması (0.26.0, 16 Ekim 2026)

Koçist ürün bilgilerini yapay zekâya ürettiriyor; çıktıda ürün başına meta başlık ve meta
açıklama var. Ürün sayfasında bunların yazılacağı yer yoktu: `<title>` "ad – site" (60'ı
aşarsa yalnızca ad), açıklama kısa açıklamanın 160'ta kesilmiş hâliydi (yerel veride 321
ürünün 256'sında kısa açıklama 160'ı aşıyor, arama sonucunda yarım cümle).

- **Havuzda, ürün başına** (`_nwcs_meta_title`, `_nwcs_meta_desc`; tek satır). Site başına
  ayrı alan açılmadı: müşteri aynı ürünü siteye göre ayrı kopyalar olarak giriyor (SİTE
  sütunu), her kopyanın kendi metası var. Ürün formunda "Arama sonucu (meta)" bölümü, 60/160
  sayaçlı (`assets/seo.js` artık Ürün Havuzu sayfalarında da yüklenir; yer tutucu otomatik
  değer, açıklama kesilmeden: 160'ı aşan otomatik açıklamada sayaç kırmızı).
- **Meta başlık olduğu gibi yazılır, site adı eklenmez** (kullanıcı kararı: sorumluluk
  içeriği yazanda). Ek sayfa sözleşmesine (`nwcs_seo_extra_pages`) isteğe bağlı `title`
  anahtarı eklendi; doluysa `<title>`, `og:title` ve JSON-LD WebPage adı odur, boşsa eski
  kural. Product adı ve konum yolu ürün adında kalır. SEO ve GEO puan ekranı aynı kuralı
  kullanır.
- **Meta açıklama doluysa** temalar `description` olarak onu verir: meta açıklama,
  `og:description`, JSON-LD (WebPage, Product) ve llms.txt satırı. Tek değer, ayrı ayrı
  yönetilmez. Boşsa eski sıra (kısa açıklama → metin → ad / WK'da ad + ilk üç detay).
- Koçist ve WOOD KOCIST temaları; havuz ürünü gösteren başka tema yok (İstanbul Keresteci'nin
  ürünleri kendi gizli sayfalarında, kendi "Arama ve Paylaşım" alanlarıyla).
- Excel geri alma kaydı (`nwcs_import_snapshot`) iki alanı da tutar; 0.26.0 öncesi kayıtlarda
  alan yoksa geri alma ona dokunmaz. Havuz Paketi iki alanı taşır (paket sürümü aynı: alan
  isteğe bağlı, eski pakette boş kalır). Havuz önbelleği anahtarı `v6`.
- **Eski önbellek satırları:** anahtar değişince eskisi okunmuyor, WordPress'in tembel silmesi
  de çalışmıyor; yerelde `nwcs_pool_products` … `_v5` beş satır (~750 KB) birikmişti. Yeni
  önbellek ilk kez kurulurken bunlar bir kez silinir (`nwcs_pool_cache_pruned` havuz
  seçeneği anahtarı tutar). Anahtar yeniden yükseltilirse listeye bir öncekini ekleyin.
- Doğrulama (yerel): meta boşken Koçist ve WOOD KOCIST'in 335 ürün sayfasının `<head>`'i ve
  iki sitenin llms.txt metni eski kodla aynı. Tek fark önbellek yeniden kurulumundan: eski `v5`
  önbelleğinde 27 görsel adresi `/kocist/wp-content/uploads/...` önekiyle kalmıştı (ikisi de
  açılıyor; yeni hâli doğru adres).

## Kategori Excel'i: meta ve ürün açıklaması sütunları (0.26.0, 16 Ekim 2026)

Müşteri ürüne dair düzenleyebileceği her şeyi tek dosyada istiyor (yapay zekâ çıktısı doğrudan
dosyaya konuyor). Kategori Excel'i bu "kapsamlı" dosya oldu; ayrı bir motor kurulmadı.

- **Yeni sütunlar:** `META BAŞLIK (en fazla 60 karakter)`, `META AÇIKLAMA (en fazla 160
  karakter)`, `ÜRÜN AÇIKLAMASI (detay sayfası metni)`; sonda bilgi sütunları `SAYFA ADRESİ
  (bilgi, panelden değişir)` ve `EK İÇERİK (bilgi)`. Taslak sürümü 2; sürüm 1 dosyaları da
  yüklenir (olmayan sütunun alanına dokunulmaz, eski kural).
- **Başlıktaki parantez:** sınırlar başlıkta yazıyor, yapay zekâ ve dolduran kişi oradan görür
  (kullanıcı önerisi). Okumada sondaki parantez **yalnızca açıklama yazılan üç sütunda** atılır
  ("Meta Başlık (maks 60)" de tanınır). Genel kural olamazdı: Koçist'in "Fiyat (Tek)",
  "Fiyat (Set)" detay başlıkları FİYAT sayılıp "sütun iki kez var" hatası veriyordu (64
  kategorilik denemede yakalandı). Kayıtlı detay başlığıyla aynı adlı sütun her zaman detay
  başlığıdır. Ayrılmış anahtarlara (`nwcs_heading_reserved_keys`) `metabaslik`,
  `metaaciklama`, `urunaciklamasi`, `sayfaadresi`, `ekicerik` eklendi: bu adlarla detay
  başlığı açılmaz.
- **Sistem sütununa benzeyen yeni sütun hata verir:** "KISA AÇIKLAMA (en fazla 300)" gibi,
  parantezsiz adı bir sistem sütunu olan ve o sistem sütunu dosyada olmayan yeni başlık
  sessizce detay başlığı olurdu (kısa açıklama değişmez, ürünlere o adla detay yazılırdı).
  Asıl sütun dosyadaysa ("Fiyat (Toptan)" + FİYAT) yeni başlık olarak kabul edilir. "(detay)"
  eki büyük/küçük harf duyarsız; ayrılmış adla kayıtlı olmayan "X (detay)" hata verir.
- **Bilgi sütunları okunmaz:** başlığında "(bilgi" olan ya da adı SAYFA ADRESİ / EK İÇERİK olan
  sütun; taslakta olmayan bilgi sütunu önizlemede not olarak yazar. SAYFA ADRESİ ilk hâlinde
  bilgi sütunuydu; aynı gün kullanıcı kararıyla düzenlenebilir oldu (aşağıda, "Kapsamlı
  Excel'den sayfa adresi").
- **Boş meta satır bırakmaz:** `nwcs_product_set_meta_text()` boş değerde meta satırını siler
  (form, Excel, geri alma, Havuz Paketi); "satır yok" ile "boş" aynı anlamda.
- **Ürün açıklaması** Ürün açıklamaları ekranının dönüşümünü kullanır (`nwcs_desc_html_to_text`
  / `nwcs_desc_text_to_html`): tablolar dosyaya girmez, sona aynen eklenir; metni değişmeyen
  hücre ürüne dokunmaz. Özel içerik işareti ("[HTML içerik — panelden düzenleyin]") yalnızca
  o ürünün kendi satırında geçerli; kopyalanan satırda ya da yeni üründe satır hata verir
  (yoksa işaret metin olarak yazılırdı). Ürün açıklamaları ekranı tüm ürünleri tek dosyada
  taşımak için kaldı.
- **Boş hücre = sil** (kullanıcı kararı, sürüyor). Meta boşsa sayfa otomatik değere döner;
  önizlemede "boş (otomatik)" gri yazar, kırmızı değil. Açıklaması silinecek ürünler
  önizlemenin başında ayrıca sayılır.
- **Uzunluk uyarı, hata değil:** 60/160'ı aşan meta yüklenir; önizleme hangi ürünlerde aştığını
  söyler (yalnızca değişen ya da yeni değerlerde). Google piksel kesiyor, sınır kesin değil.
- Doğrulama (yerel): 64 kategorinin taslağı değiştirilmeden yüklendi: kodlu 145 satırın hepsi
  "aynı", 237 hata satırının hepsi kodsuz ürün (beklenen), başka not yok. Bir kategoride kod
  ekleme, meta yazma, açıklamaya paragraf ekleme (tablo korundu), açıklama silme, yeni ürün,
  adres hücresi ve kopyalanmış işaret uyarıları; uygulama ve geri alma (dört ürün aynı hâline
  döndü, yeni ürün çöpe gitti). Eski biçimli dosya meta/açıklamaya dokunmadı.

## Hızlı Excel: kod, ad, fiyat, site (0.26.0, 16 Ekim 2026)

Müşteri ilk ayrıntılı girişten sonra kritik alanları (kullanıcı kararı: ürün kodu, adı, fiyatı,
site) toplu güncellemek için dar bir dosya istiyor. Ayrı motor kurulmadı: kategori Excel'iyle
aynı `tur=urunler`, `_bilgi` sayfasında `sablon=hizli`; aynı "Yükle", önizleme, uygula, geri al.

- **Sütunlar:** KİMLİK (gizli), ÜRÜN KODU *, ÜRÜN ADI *, FİYAT, SİTE. Kategori başına (kategori
  sayfasında "Hızlı Excel indir") ya da **tüm ürünler** (Kategoriler sayfası, kategori seçili
  değilken "Tüm ürünler: Hızlı Excel" kutusu; `kategori` boş).
- **Yalnızca güncelleme:** eşleşmeyen satır hata ("yeni ürün eklenemez"); dosyadan silinen
  satıra dokunulmaz (çöp kutusu önerisi yok, yalnızca 20 satır bırakıp yüklemek güvenli);
  çöpteki ürünün satırı hata verir (geri getirmez). Dosyaya eklenen başka sütun (KISA AÇIKLAMA,
  detay başlığı) okunmaz, önizleme yazar; zorunlu detay başlığı denetimi yok.
- **Kodsuz ürün:** "her ürünün kodu olur" kuralı sürüyor; ama hızlı dosyada kodu olmayan ve
  satırı değişmemiş ürün hata sayılmaz, önizlemede tek satırla sayılır (tüm ürünler dosyasında
  yerelde 234 hata satırı olurdu). Değiştirilen kodsuz satır hata verir; kod girilen satır
  güncellenir (kod girmenin en hızlı yolu bu dosya).
- Tüm ürünler dosyasında "SİTE'de yazılan siteye kategori yerleşmemiş" notu çıkmaz (tek bir
  kategori yerleşimi yok). "Son işlemler"de kayıt "… (Hızlı Excel)" adıyla görünür.
- Doğrulama (yerel, TEST deneme verisiyle): kategori ve tüm ürünler dosyası değiştirilmeden
  yüklendi (98 aynı, 234 kodsuz atlandı, hata yok); fiyat (salt rakam → "4.000 ₺"), ad, kod ve
  site değişikliği; silinen satır, yeni satır, değiştirilmiş kodsuz satır, çöpteki ürün ve
  eklenen sütun; uygula + geri al (12 deneme ürünü ve site ayarları birebir eski hâlinde);
  panelden gerçek indirme ve yükleme. Kapsamlı dosya için 66 kategorilik tam tur yine temiz.

## Kapsamlı Excel'den sayfa adresi (0.26.0, 16 Ekim 2026)

8 Ekim'de "adres Excel'e girmez" denmişti; müşteri ürün linkini de yapay zekâya ürettirdiği
için kullanıcı kararıyla kaldırıldı. Yeni mekanizma kurulmadı: panelin Özelleştirmeler →
Sayfa adresi yolu (`nwcs_override_set_slug`: site başına özel adres, çakışma denetimi, önceki
adres eski adreslere → 301) Excel'den toplu çağrılır. Havuz adresi (`post_name`) var olan
üründe değişmez; hep ürüne yönlenen kalıcı takma ad olarak kalır.

- **Sütun:** `SAYFA ADRESİ (yalnızca /urun/ sonrası değişir; boş = değişmez)`, ÜRÜN
  AÇIKLAMASI'nın yanında. Hücre ürünün göründüğü her sitedeki tam adresi satır satır yazar.
  Taslak sürümü 3; sürüm 2'nin "(bilgi, panelden değişir)" başlıklı sütunu okunmaz, önizleme
  "taslağı yeniden indirin" der (başlık okumada "(bilgi" denetimi parantez atmadan önce).
- **Okuma** (`nwcs_sync_address_lines`): tam adres satırı sitenin `/urun/` önekiyle (http/https,
  www. önemsiz) kendi sitesine; yalnızca adres yazılmışsa ("ahsap-cardak", "/urun/ahsap-cardak")
  ürünün bütün sitelerine. `nwcs_slug_clean` ile sadeleşir. "otomatik" yazılırsa havuz adresine
  döner. Ürün iki sitede farklı adresliyse her satır kendi sitesini değiştirir; aynı siteye iki
  farklı adres, site belirtilmemiş birden çok adres ya da hiçbir sitede görünmeyen üründe
  adres değişikliği satır hatasıdır.
- **Boş hücre = değişmez** (kullanıcı kararı; SİTE gibi "boş = sil" kuralının istisnası): boş
  hücre özel adresi otomatiğe döndürseydi bağlantı fark edilmeden değişirdi.
- **Dosyanın tamamıyla denetim** (`nwcs_sync_check_addresses`, planda, yazmadan):
  `nwcs_sync_slug_conflict` panelin kurallarını uygular, iki ekle: aynı dosyada adresinden
  ayrılan ürünün özel adresi alınabilir (zincir: A b'ye, B a'nın eski adresine; önizleme "A'nın
  bu adresle paylaşılmış bağlantıları artık B'yi açar" der) ve çöp kutusundaki ürünlerin havuz
  adresi de dolu sayılır. Aynı sitede aynı yeni adresi iki satır istemesi ve sıraya konamayan
  değişiklik (takas: A b'ye, B a'ya) hatadır; hatalı satır ürünün diğer alanlarıyla birlikte
  yüklenmez. Başka ürünün eski adresi alınırsa not (o eski bağlantı artık ona yönlenmez).
- **Yeni ürün:** hücrede tek adres varsa havuz adresi o olur (görüneceği sitelerde ve havuzda
  çakışma denetimi; WordPress'in sessiz "-2"si uygulamadan sonra da denetlenir). Başka ürünün
  adresi yazılmışsa (kopyalanan satır) yok sayılır, not; adres addan üretilir.
- **Uygulama:** adresler en sonda, ürünlerin diğer alanlarından ve yeni ürünlerden sonra;
  zincir için ilerleme kalmayana kadar yinelenir. Geri alma kaydı site başına önceki hâli
  (`slug`, `slug_old`) yazmadan önce tutar. Alınamayan adres (arada panelden alınmış) özette
  listelenir; ürünün diğer değişiklikleri uygulanmış kalır.
- **Geri alma** ürünün değişiklik zamanından bağımsız (adres yazımı onu değiştirmez): şimdiki
  özel adres uygulanan adresse önceki hâl geri yazılır, uygulanan adres eski adreslere girer
  (arada paylaşılan bağlantı yönlenir); panelden sonradan değiştirilmişse dokunulmaz. Zincir
  geri alınınca bir ürünün eski adresleri başka ürünün güncel adresini tutmaz.
- **Koçist'in eski site yönlendirme listesi** (`scripts/data/kocist-redirects.txt`) havuz
  adreslerini hedefliyor; özel adres verilince iki adım olur (liste → havuz adresi → güncel
  adres). Liste yalnızca 404'te çalıştığı ve havuz adresi hep yönlendiği için döngü olmaz.
- **Tanınmayan tam adres hata:** alan adıyla yazılmış ama hiçbir sitenin `/urun/` önekine uymayan
  satır (eski siteden yapıştırılmış link, yazım hatası) bütün sitelere uygulanmaz, satır hata
  verir. Önek eşleşince adres önekten sonrası alınır (adresi "urun" olan ürün de çalışır).
- Hata mesajı hangi sitenin adresi olduğunu söyler; zincir/eski adres notları yalnızca
  yüklenecek satırlar için yazılır. Önizleme (ürün, site) çiftlerini sayar ("3 sayfa adresi
  değişecek (2 ürün)"), değişiklik tablosu adresi `/urun/…/` biçiminde gösterir.
- **Geri almada çakışma:** önceki adres arada (panelden) başka bir ürüne verilmişse geri
  yazılmaz, "önceki adresi artık başka bir üründe" diye listelenir; aynı turda geri alınan
  zincir kendi içinde çakışma sayılmaz.
- Hızlı Excel'e adres girmez (eklenen SAYFA ADRESİ sütunu okunmaz).
- Doğrulama (yerel, TEST deneme verisi): yalnızca adres yazma, "otomatik", iki sitede farklı
  adresli üründe tek siteyi değiştirme, iki siteye tek adres, zincir, adresli yeni ürün,
  kopyalanan satır; uygulama sonrası eski adresler 301 ile yeni adreslere; geri alma (ürün
  verisi ve site seçimleri aynı, yalnızca eski adres eklemeleri). Hatalar: takas, aynı adres
  iki satırda, sayfa adresi ("iletisim"), gizli ürün, geçersiz adres, çöpteki ürünün adresi;
  eski adres notu; sürüm 2 taslağı.
- Yerel havuz 16 Ekim'de kullanıcı isteğiyle sadeleştirildi (TEST deneme ürünleri kaldı);
  canlıda bütün ürünlerin kodu var (kullanıcı beyanı).

## Panel metinleri: hata ve uyarı mesajları sade Türkçe (0.26.0, 16 Ekim 2026)

Kullanıcı kararı: paneldeki her hata ve uyarı, teknik bilgisi olmayan birinin (Koçist'te ürün
giren kişi) sorunu anlayıp düzeltebileceği sade Türkçeyle yazılır. Excel'lerin "Nasıl
kullanılır" sayfaları da aynı dille: Koçist'in notu odur, ürünleri yapay zekâya girdiren kişi
ve yapay zekâ da oradan okur (ayrı bir yapay zekâ talimat sayfası eklenmedi, kullanıcı kararı).

Yazım kuralları:
1. Önce sorun, sonra ne yapılacağı; kısa cümleler. "Ürün kodu boş. Bu satıra ürünün kodunu yazın."
2. Teknik kelime yok: sistem sütunu, tekil, KİMLİK, taslak, havuz (menü adı "Ürün Havuzu"
   hariç), slug, sunucu, transient, manifest, override, JSON, ZipArchive, post, meta (alan adı
   "meta başlık" hariç)… Ekranlar paneldeki menü adlarıyla: "Ürün Havuzu › Kategoriler".
3. Sorunlu değer ve yeri gösterilir: hangi kod, hangi sütun, hangi site.
4. Kullanıcının çözemeyeceği sorunda açıkça "geliştiriciye haber verin"; gerekirse teknik
   ayrıntı en sonda parantez içinde ("(Teknik: ZipArchive yok)"). Geliştirici menüsündeki
   ekranlarda kısa teknik not kalabilir.
5. Suçlamayan, sakin dil; ünlem yok.
6. Yer tutucular (%s, %d, %1$s) sayı, sıra ve türüyle aynı kalır; değişirse sayfa hata verir.

## Renk grupları: aynı ürünün renkleri tek kart (0.27.0, 19 Ekim 2026)

Plan: `PLAN-renk-sss-teklif.md` §1. WOOD KOCIST'te aynı ürünün renkleri (Adirondack, kedi
yuvası) ayrı ürün olarak giriliyor; kategoride her renk ayrı kart oluyor, ürün sayfasında
diğer renklere geçiş yoktu.

- **Renkler ayrı ürün kalır** (kod, fotoğraf, fiyat, sayfa, sepet): varyant modeli kurulmadı,
  Excel değişmedi. Grup yalnızca bir bağdır: havuz kategorisinin terim meta'sı
  `_nwcs_color_groups` = sıralı `{ id, label, members: [ { product_id, color_name, color_hex } ] }`.
  Veritabanı göçü yok; canlıya alma dosya yüklemekten ibaret.
- **Kurallar:** bir ürün havuz genelinde tek grupta (kayıtta denetlenir; elle bozulmuş veride
  haritada ilk gelen kazanır); üye o kategoride olmalı. Çöpteki, silinmiş, kategoriden çıkmış
  üye silinmez, sitede yok sayılır, panelde uyarıyla durur (geri gelirse grup kendiliğinden
  tamamlanır).
- **Okuma:** `nwcs_color_map()` (ürün → grup, sıra) havuz sitesinin transient'ında; kategori
  önbelleğiyle birlikte temizlenir (`nwcs_pool_categories_flush`, dolayısıyla
  `nwcs_pool_flush_cache` ve ürün-kategori bağı değişimi). Site ürün dizini istek içinde.
- **Sadeleştirme (`nwcs_color_collapse`)** temanın verdiği listeye uygulanır: grubun o sitede
  görünen (seçili, gizli değil, sayfası var) ve listede bulunan ilk üyesi kalır, grubun listedeki
  ilk yerine oturur; renk sayısı ve düğmeler site geneli. Sitede ikiden az renk görünüyorsa grup
  yok sayılır. Kategori, seri/grup, mağaza, ana sayfa listeleri ve menü/kategori sayıları
  (`nwcs_site_category_tree` dahil) sadeleşir; arama (kodla arayan doğru ürünü bulmalı), sepet,
  site haritası ve llms.txt sadeleşmez.
- **Kart:** renk yuvarlağı yok, yalnızca "{sayi} renk" (iki temada panel metni). Başlık öne çıkan
  ürünün sitedeki adı; adında renk adı geçmiyorsa " – <renk>" (Türkçe harf ve büyük/küçük harf
  farkı `nwcs_search_fold` ile gözetilmez). Parantezli adın ("Amber (Açık Meşe)") bir parçası
  adda geçiyorsa renk var sayılır (`nwcs_color_guess` ile aynı bölme).
- **Ürün sayfası:** "<başlık>: <renk>" ve 44 px dokunma alanlı yuvarlak bağlantılar; ad bağlantı
  metni (fareyle/odakla balon, yokken ekran okuyucu metni; gizliyken kaydırma alanı yaratmaz).
  Açık renkte ince çizgi (WCAG bağıl parlaklık > 0,7). İlgili ürünler şeridi aynı grubun
  renklerini göstermez.
- **SEO:** her renk kendi adresi ve canonical'ı; Product'a `color`, `inProductGroupWithID`,
  `isVariantOf`; ayrıca `ProductGroup` (`@id` sitedeki ilk rengin adresi + `#renk-grubu`, her
  renk sayfasında aynı; `productGroupID` = `RENK-<grup kimliği>`, üye sırası değişse de sabit;
  `variesBy` color; `hasVariant` sitedeki renkler).
- **Geri alma** (`kind=renk`): kategorinin önceki ve sonraki tüm grupları. Şimdiki hâl `after`
  değilse (başka yoldan değişmiş) yazılmaz, kayıt nedeniyle listeden düşer. Kategori silinince
  gruplar terimle gider; "kategori" kaydı onları da tutar, geri almada geri yazılır.
- Panel JS'siz çalışır (↑ ↓ ve "Gruptan çıkar" formu o hâliyle kaydeder; Enter "Grubu kaydet"i
  kullanır); JS satırı yerinde taşır, aramayı ve yuvarlağı canlı gösterir.
- Doğrulama: `scripts/dev/check-color-groups.php` (105 kontrol: istek yolları, geri alma,
  sadeleştirme, gizli öne çıkan, tek üye, site başına adres, HTTP ile iki sitenin kategori ve
  ürün sayfası, JSON-LD); başsız Chrome ile iki tema, panel, 390 px.

## Kategoriye özel sık sorulan sorular (0.27.0, 19 Ekim 2026)

Plan: `PLAN-renk-sss-teklif.md` §2. Sitenin `/sss/` sayfası genel soruları taşıyor; kategoriye
özgü soru (kamelya kaç kişilik, çatı neyle kaplanır) için yer yoktu.

- **Veri:** havuz kategorisinin terim meta'sı `_nwcs_cat_faq` = `[ site_key => [ { q, a } ] ]`;
  site başına ayrı liste, en fazla 10 satır. Soru tek satır (200 karakter), cevap düz metin
  (2000 karakter, etiket yok, en fazla bir boş satır paragraf boşluğu). Göç yok.
- **Site başına liste:** iki sitede aynı cevap kopya içerik sayılır; ortak liste yerine her
  sitenin kendi listesi. Kayıt engellenmez, uyarı verilir: aynı kategorinin yerleştiği diğer
  sitedeki cevapla `nwcs_seo_near_text` (SEO tutarlılık kontrolüyle aynı kural, oradan
  çıkarıldı: biçim aynı ya da `similar_text` ≥ %85; uzunluk farkı eşiği aşıyorsa hesaplanmaz).
  30 karakterden kısa cevap ("Evet.") uyarı vermez.
- **Cevabı boş soru** kaydedilir, sitede ve şemada görünmez (sitenin SSS'iyle aynı kural:
  firmadan cevap bekleyen soru). Sorusu boş cevap kaydedilmez; taslak bir kez geri gelir.
- **Sekmeler:** yalnızca kategorinin gerçekten göründüğü siteler (yerleşimi var ve başlığı
  duruyor). Yerleşimi kalkan sitenin listesi silinmez; panel "kayıtlı N soru duruyor" der,
  yeniden yerleşince geri gelir. Üst başlığın kendi kategorisinde soru yok (yalnızca alt
  kategori sayfaları).
- **Kategori sayfasının adresi** eklentinin ürettiği `kat-<slug>` panel sayfasının adresidir;
  iki temanın çizdiği adresle aynı. FAQPage bu adresle eşleşen sayfada eklenir
  (`nwcs_cat_faq_for_context`): Koçist'te sayfa temanın ek sayfası, WOOD KOCIST'te manifest
  sayfası; iki durumda da tema ek bir şey bildirmez. Sitenin SSS sayfasıyla aynı yol: sayfa
  düğümü `["WebPage","FAQPage"]` + `mainEntity`; ayrı FAQPage düğümü yok, ürün sayfasında hiç
  yok. Koçist'in özel arama başlığı JSON-LD'de WebPage'i artık `#webpage` kimliğiyle bulur.
- **Ürün sayfası:** `nwcs_product_place` ile bulunan kategorinin ilk 3 sorusu + "Tüm sorular"
  (`#sss`). Koçist'te bu, akordeonun son satırındaki grup SSS bağlantısının yerini alır;
  kategoride soru yoksa eski bağlantı kalır.
- **llms.txt:** site SSS'inden sonra "Kategorilere özel sık sorulan sorular": kategori başlığı
  (sayfa adresi `#sss`) ve "- soru cevap" satırları; ürünsüz kategoriyi gizleyen sitede
  (WOOD KOCIST) görünmeyen kategori atlanır.
- **Geri alma** (`kind=sss`): o sitenin önceki ve sonraki listesi; şimdiki hâl `after` değilse
  yazılmaz, kayıt düşer. Aynı kullanıcı, kategori ve sitenin art arda kayıtları 30 dakika
  içinde birleşir (renk gruplarıyla aynı); oturumun başına dönen kayıt düşer. Kategori
  silinince sorular "kategori" kaydında durur, geri almada geri yazılır.
- Panel JS'siz çalışır: sekmeler bağlantı (`?sss_site=`), ↑ ↓ ve "Sil" formu o hâliyle kaydeder,
  sonda bir boş "Yeni soru" satırı. JS: sekmeler yerinde (ok tuşları), satır yerinde taşınır
  ve silinir, "Soru ekle".
- Doğrulama: `scripts/dev/check-cat-faq.php` (95 kontrol: istek yolları, doğrulama, birleşme,
  geri alma, kopya uyarısı, panel, HTTP ile iki sitenin kategori ve ürün sayfası, JSON-LD,
  llms.txt, sayfaların E_ALL çizimi); başsız Chrome ile panel (JS'li ve JS'siz), iki tema, 390 px.

## Koçist: arama ve teklif sepeti (kocist-theme 0.6.0, NWCS 0.27.0, 19 Ekim 2026)

Plan: `PLAN-renk-sss-teklif.md` §3. Koçist B2B: ürünlerin çoğu fiyatsız; ziyaretçi birden çok
ürün için tek seferde fiyat isteyemiyor, sitede arama yoktu. Sipariş ve ödeme kurulmadı.

- **Arama adresi `/arama/?ara=`** (`/kategoriler/?ara=` değil): `/kategoriler/` dizine giren,
  kendi canonical'ı olan sayfa; sorgulu hâli noindex olunca arama motoruna çelişen işaret gider.
  `/arama/` her zaman `noindex, follow` (site kapalıyken WordPress'in `nofollow`'u kalır), site
  haritasında yok, gizli manifest sayfası olduğu için llms.txt'de yok; üst menüdeki form hep aynı
  adrese gider. Sayfa (ve `/teklif-sepeti/`) diğer tema sayfaları gibi WordPress sayfası
  (`KOCIST_PAGES_VERSION` 7): yeniden yazma kuralıyla kurulsaydı WordPress isteği blog listesi
  sanıyor, SEO bağlamı ve menü işareti bloga düşüyordu. Sayfa açılana kadar (yönetici panele
  girene kadar) üst menü düğmeleri ve ekleme formu basılmaz, ziyaretçi 404'e gitmez.
- **Eşleştirme eklentide ortak** (`includes/search.php`): `nwcs_search_fold` (products.php'den
  taşındı, davranışı aynı), `nwcs_search_compact` (yalnızca harf ve rakam), `nwcs_search_rank`,
  `nwcs_search_products`. Derece: 0 kod tam, 1 kod terimle başlıyor, 2 kodda geçiyor, 3 adda ya
  da kategoride (Koçist'te sitedeki grup ve kategori adı da) geçiyor, 4 kelimeler ayrı ayrı
  geçiyor; eşitte liste sırası. Alanlar ayrı sadeleşir ("|" ayracı): bir kelime iki alanın
  birleşiminden eşleşmez. Renk grubu sadeleşmez. En az 2 harf/rakam, sayfada en fazla 60 sonuç.
- **WOOD KOCIST:** `wk_filter_products` yalnızca katlamayı alır (`nwcs_search_fold`); eşleştirme
  eskisi gibi tek parça, sıra mağazanın sıralaması. Sonuç: Türkçe harfsiz terimlerde aynı;
  "CICEKLI", "İĞNE" gibi terimler artık Türkçe harfli ürünü de bulur. Sepet ve sipariş değişmedi.
- **Sepet çerezi** `kc_teklif` = `kimlik:adet|...` (WOOD KOCIST biçimi; ayrı ad, iki site yerelde
  aynı alan adında). Yol sitenin adresi (`/kocist/` yerelde, `/` canlıda), SameSite=Lax, 30 gün;
  betik de okuyup yazar (HttpOnly değil). En fazla 30 satır, satırda 9999 adet (B2B adetleri
  büyük; WOOD KOCIST 99). Üst menüdeki rozet ürün çeşidi sayısıdır, adet toplamı değil.
- **Önbellek:** sayfalar önbelleğe girer; sayı betikle çerezden. Sepet sayfası, ürün sayfasının
  `?teklif=` dönüşü ve işleyiciler `DONOTCACHEPAGE` + `X-LiteSpeed-Cache-Control: no-cache`.
- **Nonce:** ekle/güncelle/çıkar/temizle nonce'suz (WOOD KOCIST sepetiyle aynı gerekçe:
  önbellekteki sayfada nonce eskir; yalnızca o tarayıcının çerezi değişir). E-posta gönderimi
  nonce'lu (sepet sayfası önbelleğe girmez).
- **JavaScript'siz:** ürün sayfasında form `admin-post.php`'ye gider, sayfa
  `?teklif=eklendi#teklif-sepeti-ekle` ile döner; dönüş çerezi okur: ürün sepette değilse
  (çerez kapalı) "eklendi" demez, ne yapılacağını yazar. Sepette +/− ve "Çıkar" gönder
  düğmesi; formun ilk düğmesi görünmez "güncelle" (Enter bir satırın −'sine basmasın). Betikle
  ekleme sayfayı yenilemez, sonucu canlı bölgede duyurur, çerezi okuyup doğrular; sepette adet
  değişince form 600 ms sonra kendisi gönderilir.
- **E-posta:** mevcut akış: `kc_quote` kaydı ("Teklif Talepleri"), eklentinin bildirimi
  (`kocist_form_recipient`). Ürün listesi `_kc_items` (eklentiye `items` → "Ürünler" etiketi
  eklendi) ve kayıt metninde; satır "kod – ad – adet" ve sayfa adresi. Eklenti bildirimi normalde
  isteğin sonuna erteler ve sonucu yansıtmaz; burada `nwcs_form_send_notification` hemen
  çağrılır ve sonucu beklenir (kuyruk ikinci kez göndermez). Gitmezse: kayıt durur (panelde
  "failed"), ziyaretçiye dürüst hata, sepet ve yazılanlar korunur. Alan kuralları ürün formuyla
  aynı (ad; telefon ya da e-posta; onay). `kocist_quote_redirect`'e dönüş çapası parametresi
  eklendi.
- **Müşteriye kopya gönderilmedi:** eklentinin bildirim akışı yalnızca site sahibine yazar;
  formdan gelen bir adrese otomatik e-posta, oturum açılmayan bir formu üçüncü kişilere e-posta
  gönderen bir araca çevirir (bot tuzağı ve nonce bunu durdurmaz) ve gönderen adres Koçist'in
  alan adı değil. Karar kullanıcıda.
- **WhatsApp:** hepsi aynı gruptaysa grubun numarası, karışık ya da grubu yoksa genel numara
  (`product.main.secondary_url`, yoksa İletişim sayfasının WhatsApp'ı). Mesaj giriş satırı panel
  metni, satır kalıbı `{kod} – {urun} – {adet} adet` (kodsuz üründe ayraç düşer). Adres en
  fazla 1900 karakter: önce sayfa adresleri, sonra liste kısalır ("+N ürün daha"). Bağlantı
  `esc_attr` ile basılır: `esc_url` kodlanmış satır sonlarını (`%0A`) siliyor, mesaj tek satıra
  yapışıyordu; adres temada kurulur (`https://wa.me/` + rakamlar + `rawurlencode`).
- **Kart:** kategori sayfasının kartı `kocist_product_card()`'a taşındı (çıktı bire bir aynı),
  arama sonuçları da onu kullanır.
- **İnceleme düzeltmeleri (Fable):**
  - terimli `/arama/?ara=` önbelleğe alınmaz (rastgele terimler LiteSpeed önbelleğini
    doldurmasın); terimsiz `/arama/` alınır;
  - sepetin dönüş adresinde yol kodu çözülerek denetlenir (`%2e%2e`, iç içe `%252e`) ve son kapı
    `wp_validate_redirect`;
  - hız sınırı: IP'nin tuzlu özeti (`wp_hash`) geçici kayıtta; 30 saniyede bir, saatte 10
    talep; teklif sepeti e-postası ve tek ürünlük teklif/iletişim formu ortak sayar. Yalnızca
    geçerli (kaydedilecek) talep sayılır; sınırda kayıt ve e-posta yok, dürüst hata, yazılanlar ve
    sepet korunur. IP, WOOD KOCIST ödeme formu gibi `CF-Connecting-IP`, yoksa `REMOTE_ADDR`
    (yalnızca hız sınırı; başka yetki buna dayanmaz);
  - "eklendi" metni "bu üründen {adet} adet": üst menüdeki rozet ürün çeşidini sayar;
  - her arama formunun kendi bölge adı (`aria-label`; panel metni).
- Doğrulama: `scripts/dev/check-quote-basket.php` (arama yardımcıları, sayfalar, çerez,
  JS'siz işleyiciler, HTTP ile Set-Cookie ve yönlendirme, gönderim ve Mailpit, WhatsApp,
  WOOD KOCIST arama ve sepet, E_ALL); başsız Chrome ile masaüstü (JS'li ve JS'siz) ve 390 px.

## Renk listesi: düzenlenebilir renkler (NWCS 0.27.1, 20 Ekim 2026)

Plan: `PLAN-renk-sss-teklif.md` §1'in devamı. Hazır renkler kodda sabitti (13 renk); "Özel renk"
her üyede ayrı girildiği için yeniden kullanılamıyor, liste kod değişmeden düzenlenemiyordu.

- **Veri:** ağ ayarı `nwcs_color_palette` = sıralı `[ { id, name, hex } ]`. Ayar yoksa eski 13
  renk (`nwcs_color_default_presets`; kimlik addan türer, her istekte aynı); okuma yazmaz, ayar
  ilk kayıtta yazılır. Göç yok. `nwcs_color_presets()` imzası aynı (ad => ton) ve listeyi okur:
  seçim listesi, ürün adından tahmin ve adla dolma değişmeden yeni renkleri de bilir.
- **Üye kopya tutar, bağ değil:** üye kendi `color_name` ve `color_hex`'ini saklar; listeden silme
  ya da ad değiştirme grupları bozmaz, sitede görünen hep üyenin kendi değeri. Üyede renk kimliği
  tutulmadı: "kullanan" eşleşmesi katlanmış adla (`nwcs_search_fold`), böylece 0.27.0 verisi ve
  elle yazılmış adlar da sayılır. Bedeli: aynı adı başka tonla yazan üye de "kullanan" sayılır;
  onay sayfası onu da listeler, işaret kaldırılabilir.
- **Yayılma onaylı ve renk başına:** kullanılan rengin adı ya da tonu değişince "Bu rengi kullanan
  N ürün de güncellensin" (işaretli gelir); işaretsiz renkte yalnızca liste değişir. Ton değişince
  üyenin adı korunur, ad değişince yeni ad yazılır. Tek geçiş: iki rengin adını takas etmek
  zincirleme değişiklik yapmaz. Kullanan yoksa onay sorulmaz. Liste onay arasında başka sekmede
  değiştiyse kaydedilmez.
- **Kurallar:** ad zorunlu ve tekil (Türkçe harf, büyük/küçük harf farkı yok), ton `nwcs_color_hex`,
  en fazla 60 renk (seçim listesi kullanışlı kalsın). Hata varsa hiçbir şey yazılmaz, taslak bir
  kez geri gelir. Formda olmayan kayıtlı renk silinmiş sayılmaz (başka sekmede eklenmiş olabilir);
  silme açık gelir (`sil`, JS'te `silinen[]`).
- **Geri alma** (`kind=palet`): önceki liste ve bu işlemin güncellediği üyelerin önceki ad/tonu.
  Liste ya da o üyeler sonradan değiştiyse hiçbir şeye dokunulmaz, neden yazılır, kayıt düşer
  (diğer türler gibi yığını kilitlemesin). Aynı kişinin art arda kayıtları 30 dakika içinde
  birleşir; oturumun başına dönen kayıt düşer, ilk kayıtsa ayar silinir (ilk 13 renge döner).
  Üye değişince havuz ve site önbelleği temizlenir; yalnızca liste değişince gerekmez.
- **Renk grupları'ndan "listeye kaydet":** grup kaydından önce ayrı bir `palet` kaydı ("Renk
  grupları’ndan"), grup değişmese de. Aynı ad aynı tonla varsa sessiz; başka tonla varsa liste
  değişmez, üye kaydedilir, uyarı Renkler ekranına bağlanır. Liste doluysa eklenmez, söylenir.
  Kutucuk yalnızca "Özel renk" seçiliyken görünür (CSS `:has`, JS'siz de).
- Panel JS'siz çalışır. JS: satır yerinde taşınır, eklenir, silinir; üye satırında ad ile seçim
  "bağlıyken" (seçim boş ya da seçili rengin adı yazılı) listedeki bir ad yazılınca seçim o renge
  geçer; özel renge dokunulmaz.
- Doğrulama: `scripts/dev/check-color-palette.php` (90 kontrol: istek yolları, doğrulama, kullanım,
  onay işaretli/işaretsiz/karışık, ad takası, geri alma ve reddi, birleşme, listeye kaydet, adla
  dolma, menü; başka kategorilerin gruplarına dokunulmadığı denetlenir); `check-color-groups.php`,
  `check-cat-faq.php`, `check-quote-basket.php` geçti; başsız Chrome ile Renkler ekranı, onay,
  grup satırı (JS'li ve JS'siz) ve 390 px.

## Canlı arama: yazarken liste ve önizleme (NWCS 0.27.2, kocist-theme 0.6.1, woodkocist-theme 0.2.2, 20 Ekim 2026)

Eski woodkocist.com.tr'deki WooCommerce canlı aramasının karşılığı: üst menüdeki kutuya yazarken
büyük bir liste, genişte yanında seçili ürünün önizlemesi ve sepete ekleme.

- **Tek uç, tek betik, eklentide:** `includes/live-search.php` (REST `nwcs/v1/arama`) ve
  `assets/live-search.js` (combobox, vurgu, önizleme). Tema açar: `nwcs_live_search` süzgeci
  `search` (sonuç sayfasının araması) ve `url` (sonuç sayfası adresi) verir; açmayan sitede yol
  kayıtlı değil (404). Öğeyi tema kartındaki gibi biçimler (`nwcs_live_search_item`): WOOD KOCIST
  fiyat + `wk_price_note()`, fiyatsızda "Fiyat teklifle", `can_add = wk_can_buy`; Koçist fiyatsızda
  `kategoriler.texts.price_quote`, her ürün eklenebilir (sepet sayfası açıksa), adres kartın adresi
  (sayfası yoksa teklif formu). Görünüm her temada kendi değişkenleri ve sınıflarıyla (önek
  `k-ls` / `wk-ls`; adet seçici, düğmeler ve kod plakası temanın sınıfları).
- **Sonuç sayfasıyla aynı liste:** uç sitenin kendi sonuç sayfasının aramasını çağırır (Koçist
  `kocist_search`, WOOD KOCIST `wk_filter_products( '', q, '' )`): 8 öğe sayfanın ilk 8 kartı, N
  sayfanın sayısı, renk grubu sadeleşmez. WOOD KOCIST mağaza araması da (kullanıcı kararı) Koçist'le
  aynı sıralı ortak aramayı kullanır (`nwcs_search_products`): "adr k" = "ADR-K", tam kod en üstte;
  0.27.0'daki "tek parça" kararının yerini alır. Seçilen sıralama (fiyat, ad) yine ezer.
- **Girdi:** yalnızca `q`; `sanitize_text_field`, 60 karakter, en az 2 harf/rakam (kısa terim 200 ve
  boş liste). Dizi `q` REST doğrulamasında 400. Ürün listesi ve fiyatlar sunucuda; istemciden başka
  bir şey okunmaz.
- **Önbellek:** `Cache-Control: public, max-age=300` (tarayıcı; geri silmede ve aynı terimde istek
  yok) ve `X-LiteSpeed-Cache-Control: no-cache` (rastgele terimler sunucu önbelleğini doldurmasın;
  terimli `/arama/` ile aynı gerekçe). Betik `credentials: 'omit'` ile ister; oturum çerezi gitmez.
- **Görsel:** havuz sitesinde tek geçişle `thumbnail` (liste, 56 px) ve `medium_large` (önizleme,
  ölçüleriyle). 'medium' (300 px) 360 px'lik önizlemede bulanık kalıyordu. Koçist'te yer tutucu
  (seed) görsel gösterilmez (`kocist_product_image`). Kısa metin düz yazı, ~160 karakter, kelime
  sınırında "…".
- **Vurgu istemcide, güvenli:** `nwcs_search_fold` JS'te birebir (İ→i, I→ı, küçük harf, ı ğ ü ş ö
  ç â î û); harf/rakam haritası üzerinden asıl metindeki konuma döner, böylece "adr k" kodda
  "ADR-K"yı kalın yapar. Terim bütünüyle geçmiyorsa kelimeleri (2 harften uzun) ayrı ayrı. Liste
  `createElement` + `textContent`; sunucu metni hiçbir yerde HTML olarak işlenmez, adresler
  yalnızca http(s) ya da site içi. Ayar JSON'u `JSON_HEX_TAG` ile basılır.
- **ARIA ve klavye:** kutu `role=combobox`, `aria-autocomplete=list`, `aria-expanded`,
  `aria-controls` → `role=listbox`; öğeler `<a role=option tabindex=-1>` (orta tıkla yeni sekme
  çalışsın). ↑ ↓ "kutu → ilk … son → kutu" döner, `aria-activedescendant` ve `aria-selected`;
  fareyle üzerine gelmek de seçer (`mousemove`: liste farenin altında yenilenince kaymaz). Enter
  seçili ürünü açar, seçili yoksa form gönderilir. Esc yalnızca listeyi kapatır
  (`stopPropagation`: Koçist'te arama çubuğu ve çekmece açık kalır). Listeye basınca odak kutuda
  kalır (`mousedown` önlenir; adet kutusu hariç); dışarı basınca ve sekmeyle formdan çıkınca kapanır.
  Sayı canlı bölgede ("12 ürün bulundu"), yazmayı bölmesin diye 450 ms sonra.
- **İstek:** 200 ms bekleme, eski istek `AbortController` ile iptal, sıra numarası (geç gelen eski
  cevap çizilmez), terim başına sayfa içi önbellek; cevap 150 ms'yi geçerse "Aranıyor…" ya da ince
  şerit. Uç cevap vermezse liste açılmaz, form eskisi gibi.
- **Ekleme mevcut yoldan:** önizleme `nwcs-live-add` olayı gönderir, sonucu `detail.result`'tan
  okur. Koçist `teklif.js` ürün sayfasının ekleme kodunu `addToBasket()`'a ayırdı (form ve liste
  aynı fonksiyon: çerez, doğrulama, üst menü sayısı; "bu üründen N adet" + "Sepete git", dolu ve
  çerez kapalı metinleri). WOOD KOCIST `site.js` `addToCart()`'a ayırdı ve mevcut "Sepete eklendi"
  panelini açar (`notified`: liste ayrıca metin yazmaz). Çerez mantığı tek yerde kaldı; olayı
  karşılayan betik yoksa liste ürün sayfasına gider. Ödeme akışı değişmedi.
- **Yerleşim:** liste formun altında, sayfanın üstüne biner (yer kaydırmaz); yükseklik pencereye
  göre, liste kendi içinde kayar, "Tüm sonuçlar (N)" altta yapışık. Önizleme yalnızca ≥ 1024 px.
  Koçist telefon çekmecesinde liste akışta: çekmece kendi içinde kayan bir kutu, üstüne binen liste
  kırpılıyordu; kayma yalnızca yazınca olur. WOOD KOCIST telefonda alttaki WhatsApp şeridi kadar yer
  ayrılır (kökün `margin-bottom`'u; betik hesaba katar). WOOD KOCIST'in `.wk-search__form input`
  kuralı `> input` oldu: önizlemedeki adet kutusunu da koyu temaya boyuyordu. Koçist'in adet
  seçicisi (`.k-qty`) `teklif.css`'ten her sayfada yüklenen `header.css`'e taşındı.
- **Gizli olduğu yerde yok:** Koçist'te arama sayfası yayında değilken (`kocist_page_ready`) uç,
  betik ve kutu işareti (`data-nwcs-live`) yok; panel önizlemesinde liste açılmaz (tıklama alanı
  düzenlemeye açar). Arama sayfasındaki büyük kutuda canlı liste yok (sonuçlar zaten altında).
  JavaScript yoksa hiçbir şey değişmez.
- **Metinler panelde:** Koçist Tüm Sayfalar › "Üst Menü: Arama ve Teklif Sepeti" (`live_all`,
  `live_count`, `live_loading`, `live_code`, `live_list`, `live_error`); boş sonuç Arama Sayfası'nın,
  ekleme Teklif Sepeti'nin metinleri. WOOD KOCIST Tüm Sayfalar › "Üst Menü" (aynı adlar + adet
  kutusu ve `live_full`); boş sonuç Mağaza'nın, fiyat ve düğmeler Ürün Kartları'nın metinleri.
- Doğrulama: `scripts/dev/check-live-search.php` (85 kontrol: uç biçimi ve sınırlar, iki sitede
  sonuç sayfasıyla sıra ve sayı eşitliği (işlev ve HTTP'de kartlar), Türkçe katlama, renk grubu,
  site biçimi (KDV hariç/dahil), uç olmayan siteler ve gizli arama sayfası, önbellek başlıkları,
  sayfalardaki betik ve ayar, E_ALL); `check-quote-basket`, `check-color-groups`, `check-cat-faq`,
  `check-color-palette` geçti; başsız Chrome ile iki sitede 1280 px (liste, kalın eşleşme, ↑ ↓,
  Enter, Esc, dışarı tıklama, önizleme, teklif sepetine / sepete ekleme ve üst menü sayısı, sonuç
  yok) ve 390 px (yalnızca liste, yatay kaydırma yok), konsol hatası yok.

## Ürün XML beslemesi: Ailways ürün aktarıcısı (NWCS 0.27.3, 20 Ekim 2026)

Ailways asistanının ürün aktarıcısı her siteden ürün listesini XML olarak okur (alanlar: Ürün Adı*,
Ürün Kodu*, Fiyat*, İndirimsiz Fiyat, Görsel URL, Ürün Linki, Kategori, Açıklama, KDV Oranı).

- **Site başına adres:** `<site>/urunler.xml` (canlıda `https://<alan-adı>/urunler.xml`). Ürün
  adresleri siteye göre değiştiği için tek ağ beslemesi değil. Yalnızca katalog siteleri
  (`nwcs_catalog_site_key()` dolu: Koçist, WOOD KOCIST); diğerleri normal 404. Eklentide, tema
  değişikliği yok (canlı aramadaki gibi tema süzgeci gerekmedi: katalog bilgisi manifestte).
- **Yönlendirme:** `parse_request`'te tam eşitlik (sitenin ev yolu + `urunler.xml`; sorgu metni yok
  sayılır). Yeniden yazma kuralı yok (kalıcı bağlantı yenilemesi gerekmez); sorgu ve kanonik
  yönlendirmeden önce çıkıldığı için sona eğik çizgi ya da yönlendirme eklenmez. Alt klasörde
  (yerel) ve eşlenmiş alan adında (canlı) aynı kod. `urunler.xml/` ve benzerleri besleme değil.
- **Satırlar:** `nwcs_site_products()` (seçim, gizli ürünler, site başına ad ve adres). Renk grubu
  sadeleşmez: her renk ayrı satır, `<renk>` renk adı (aktarıcıda her ürün kendi kodu ve sayfasıyla).
  Kodsuz eski ürün satır olarak kalır, `<kod>` boş (uydurulmaz).
- **Fiyat:** Koçist JSON-LD Offer kuralı (`kocist_price_number`; eklentinin `seo.php`'si yalnızca o
  sayıyı alır) eklentiye taşındı (`nwcs_product_feed_price`): "10.250 ₺" → `10250.00`. Fiyatsız,
  "…'den başlayan", birden çok tutar, birimsiz sayı ve 0 → **boş** (= teklif). "$" (USD) fiyat da
  boş: beslemede para birimi alanı yok, aktarıcı TL sanardı. WOOD KOCIST'in `wk_price_number`'ı daha
  gevşek (rakamları toplar); beslemede iki site aynı sıkı kuralı kullanır. `indirimsiz_fiyat` boş
  (sistemde indirim yok); `kdv_orani` şimdilik boş (kullanıcı kararı).
- **Görseller:** havuz sitesinde tam boy (`full`), galeri sırasıyla (site istisnası görseli önde).
  Adres o sitenin sayfalarındaki gibi kurulur (istek o sitede; canlıda sitenin alan adı, yerelde
  `/kocist/wp-content/uploads/...`). `gorsel` ilki, `ek_gorseller` kalanlar `|` ile, `gorseller`
  hepsi iç içe `<url>` (liste okuyan aktarıcılar için; üst düzeydeki `<gorsel>` ile karışmasın). Yer tutucu yazılmaz: Koçist'in kuralı (alt metinde
  "yer tutucu"/"örnek", temadaki demo dosya adı) ve seed dosyaları (`ornek-urun-*`).
- **Kategori:** `Üst başlık > Kategori` (`nwcs_product_place`); yalnızca üst başlığın kendi
  kategorisindeyse üst başlık; yerleşimi yoksa havuzdaki ilk kategori adı. **Açıklama:** sitedeki
  kısa açıklama; boşsa uzun açıklamanın ilk ~300 karakteri, kelime sınırında "…".
- **Metin:** düz yazı (etiketler atılır, etiket olmayan "<" korunur), UTF-8, `ENT_XML1` ile
  kaçırılır; XML 1.0'da yasak kontrol karakterleri atılır.
- **Başlıklar ve önbellek:** `application/xml; charset=UTF-8`, `X-Robots-Tag: noindex`,
  `Cache-Control: public, max-age=900`; HEAD gövdesiz. Ayrı çıktı önbelleği (transient) yok:
  liste önbellekli havuzdan kurulur, görsel kayıtları tek seferde okunur (`_prime_post_caches`);
  panel değişikliğinden sonra besleme hiç eskimez. Site haritasında ve llms.txt'de yok.
- Doğrulama: `scripts/dev/check-product-feed.php` (iki sitede HTTP: geçerli XML, satır sayısı ve
  sıra, alanlar; fiyat (16 örnek, Koçist kuralıyla eşitlik); gizli ürün, site başına adres ve ad;
  renk grubu; görsel sırası, tam boy, sitenin adresi, yer tutucu; kategori; açıklama; özel
  karakterler; başlıklar, HEAD, tam eşitlik; katalog olmayan siteler 404; E_ALL);
  `check-live-search`, `check-quote-basket`, `check-color-groups` geçti.

## İstanbul Keresteci: SSS işareti, llms.txt sayfaları, IndexNow (24 Ekim 2026)

Denetim raporunun panelden yapılamayan kalemleri; hepsi temada (`inc/seo-extra.php`), ortak eklentiye dokunulmadı.

- **SSS işareti:** ürün sayfalarının soruları (`content/product-extras.php`) ve yönetimden yazılan sayfaların "Sık sorulan sorular" bölümü (h2 altındaki h3 + paragraf; ilçe sayfaları) ayrı bir `FAQPage` bloğu olarak yazılır. Panelin SSS sayfasını eklenti zaten işaretliyor; ona dokunulmaz.
- **llms.txt:** eklenti yalnızca manifest ve ürün sayfalarını listeliyor. Yönetimden yazılan yayındaki sayfalar (ilçe sayfaları) "Bölge ve bilgi sayfaları" bölümü olarak eklenir (eklenti çıktısı tamponla yakalanır).
- **sameAs:** Üst Bilgi Şeridi'ne girilen sosyal ağ adresleri firma kaydına `sameAs` olarak eklenir.
- **IndexNow:** yazı ya da sayfa yayınlanınca, güncellenince ya da yayından kalkınca adres api.indexnow.org'a (Bing, Yandex) bildirilir. Anahtar `ik_indexnow_key` seçeneğinde, dosyası `/<anahtar>.txt`. Arama motorlarına kapalı ya da yerel sitede çalışmaz. Panelden (İçerik Stüdyosu) yapılan değişiklikler bildirilmez.
- **Ana sayfa görseli:** tema fotoğrafına alt metin.

## kavakkeresteci.com: SEO-GEO-AEO düzeltmeleri (29 Ekim 2026)

Raşit Bey'in isteği: marka bilgisi kocist.com.tr esas, bütün ürünlerde istenen ebatta özel kesim, metinler kocist.com.tr ile aynı olmasın (kopya içerik).

- **Çalışma saatleri arama verisinde:** kereste-base, çocuk temanın manifestindeki `opening_hours` listesini firma düğümüne `openingHoursSpecification` olarak ekler (`kr_seo_opening_hours`, `nwcs_seo_graph` süzgeci; Koçist temasındaki kuralın aynısı). Kavak Keresteci: kocist.com.tr'deki gibi Pzt–Cmt 08:00–19:00, Pazar 10:00–19:00. İthal Keresteci'de liste boş, saat yazılmaz.
- **Harita:** İletişim sayfasının "Harita Bağlantısı" alanı firma düğümüne `hasMap` olarak eklenir (iki kuzen sitede).
- **Kopya içerik:** kocist.com.tr'nin kavak ürünlerini anlatırken kullandığı "hafif ve kolay işlenen" kalıbı hero başlığından ve kavak kartından çıktı; Hakkımızda'daki "50 yıldır" cümlesi aynı bilgiyi kocist.com.tr/kurumsal/ ile farklı cümleyle veriyor (1975'te başlayan aile işi, Koçist Grup).
- **Özel kesim:** OSB ve takoz teknik bilgilerine "Ölçü: İstenen ebatta kesim"; SSS'deki ölçü sorusu dört ürünü kapsıyor.
- Manifest değişiklikleri yalnızca varsayılandır; canlı metinler veritabanındadır ve İçerik Stüdyosu'ndan girilir. Telefonlar bu değişiklikte yok (hat grubu kararı, 25 Eylül 2026, kullanıcıya soruldu).
