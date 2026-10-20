# WordPress siteleri, tek içerik paneli — yerel geliştirme ortamı

Tek bir WordPress **Multisite** ağı altında, tasarımları birbirinden bağımsız, aynı
firmaya (Koçist Orman Ürünleri) ait gerçek siteler. Hedef: 10 site, tek panel.

| Ne | Yerel adres |
| --- | --- |
| Koçist / ahsapkasa.com yeniden tasarımı (Tailwind) | http://localhost:8080/ahsapkasa/ |
| — Hakkımızda | http://localhost:8080/ahsapkasa/hakkimizda/ |
| — Hizmetlerimiz | http://localhost:8080/ahsapkasa/hizmetlerimiz/ |
| — İletişim (çalışan teklif formu) | http://localhost:8080/ahsapkasa/iletisim/ |
| Koçist / istanbulpaletci.com yeniden tasarımı (Tailwind) | http://localhost:8080/istanbulpaletci/ |
| — Ürünlerimiz (+ 5 ürün sayfası) | http://localhost:8080/istanbulpaletci/urunlerimiz/ |
| — Blog (WordPress yazıları) | http://localhost:8080/istanbulpaletci/blog/ |
| — İletişim (çalışan teklif formu) | http://localhost:8080/istanbulpaletci/iletisim/ |
| ithalkeresteci.com yeniden tasarımı (kuzen tema) | http://localhost:8080/ithalkeresteci/ |
| — Ürünler (+ 4 ürün sayfası) | http://localhost:8080/ithalkeresteci/urunler/ |
| kavakkeresteci.com yeniden tasarımı (kuzen tema) | http://localhost:8080/kavakkeresteci/ |
| — Ürünler (+ 4 ürün sayfası) | http://localhost:8080/kavakkeresteci/urunler/ |
| Koçist (kocist.com.tr; yapım aşamasında, Emirhan) | http://localhost:8080/kocist/ |
| İstanbul Keresteci (Emirhan) | http://localhost:8080/istanbul-keresteci/ |
| Sanayi Palet (Emirhan) | http://localhost:8080/sanayi-palet/ |
| Ahşap Ambalaj Sanayi (Emirhan) | http://localhost:8080/ahsapambalaj/ |
| Ağ yönetimi (Network Admin) | http://localhost:8080/wp-admin/network/ |
| — İçerik Stüdyosu | http://localhost:8080/wp-admin/network/admin.php?page=nwcs-studio |
| — SEO ve GEO | http://localhost:8080/wp-admin/network/admin.php?page=nwcs-seo |
| Giriş | http://localhost:8080/wp-login.php |

> Bu yerel bir geliştirme ortamıdır. Gerçek `kocist.com.tr` / `istanbulpaletci.com` /
> `ahsapkasa.com` alan adlarına, DNS'e veya canlı sitelere dokunulmaz.
>
> Sitelerdeki fotoğraflar firmanın kendi sitelerinden alınan gerçek fotoğraflardır
> (`resources/`); metinler de o sitelerdeki içeriğin kendisidir. İlk aşamadaki iki
> demo site (`/kocist/`, `/paletci/`) kaldırıldı; kaldırılmadan önceki veritabanının
> yedeği `backups/` klasöründedir (Git'e girmez).

## Gereksinimler

- **Docker Desktop** (Compose v2+) — çalışır durumda
- Git
- Boş portlar: **8080** (WordPress). Veritabanı host'a açılmaz, 3306 çakışması olmaz.

Başka bir şey kurmanız gerekmiyor: PHP, MySQL ve WP-CLI container içinde gelir.
WordPress **7.1.1** kullanılır (MCP için gereken Abilities API, 6.9'dan beri çekirdekte).

## Kurulum (sıfırdan)

```bash
cp .env.example .env          # PowerShell: Copy-Item .env.example .env
# .env içindeki şifreleri kendinize göre değiştirin

docker compose up -d
docker compose --profile cli run --rm --entrypoint sh wpcli /scripts/install.sh
```

`install.sh` tekrar çalıştırılabilir; var olanı bozmaz. Yaptıkları:

1. WordPress'i kurar, ardından **alt dizin tabanlı** Multisite ağına çevirir
2. `network-content-studio` eklentisini ağ genelinde etkinleştirir
3. Türkçe dil paketini kurar
4. Betiğin başındaki `SITES` listesindeki her site için (şu an `ahsapkasa`, `istanbulpaletci`,
   `ithalkeresteci`, `kavakkeresteci`): alt siteyi açar, temasını atar, dilini Türkçe yapar ve kendi
   seed betiğiyle içeriğini kurar. **Var olan bir siteye dokunmaz**: seed alanları
   varsayılana geri yazdığı için yalnızca site ilk kez oluşurken çalışır.

Yönetici kullanıcı adı ve şifresi `.env` dosyasındadır (`WP_ADMIN_USER`,
`WP_ADMIN_PASSWORD`). `.env` sürüm kontrolüne girmez.

## Sık kullanılan komutlar

```bash
# Başlat / durdur
docker compose up -d
docker compose stop

# Claude Code'u MCP ile bağla
bash scripts/mcp-setup.sh

# İmaj etiketi yükseltildiğinde WordPress çekirdeğini eşitle
docker compose exec wordpress sh /scripts/update-core.sh
docker compose --profile cli run --rm wpcli --path=/var/www/html core update-db --network

# İçerik envanterini manifestten yeniden üret
docker compose --profile cli run --rm --entrypoint sh wpcli -c \
  "wp --path=/var/www/html eval-file /scripts/inventory.php --url=http://localhost:8080/istanbulpaletci/ --quiet"

# Herhangi bir WP-CLI komutu (örn. site listesi)
docker compose --profile cli run --rm wpcli --path=/var/www/html site list

# Her şeyi sil ve sıfırdan kur (veritabanı ve WP core dahil)
docker compose down -v
docker compose up -d
docker compose --profile cli run --rm --entrypoint sh wpcli /scripts/install.sh
```

> Git Bash kullanıyorsanız `/scripts/...` yolunun Windows yoluna çevrilmesini
> engellemek için komutların başına `MSYS_NO_PATHCONV=1` ekleyin.

## İçerik Stüdyosu paneli

Giriş yaptıktan sonra **Ağ Yönetimi → İçerik Stüdyosu**:
http://localhost:8080/wp-admin/network/admin.php?page=nwcs-studio

Panelin düzeni:

- **Ortada sitenin çalışan önizlemesi.** Değiştirmek istediğiniz yazıya, görsele veya
  butona doğrudan tıklarsınız; ilgili alan solda açılır ve seçili alan vurgulanır.
  Üstte masaüstü/telefon genişliği arasında geçiş yapılır.
- **Solda sayfa sekmeleri ve bölüm listesi** — Türkçe adlarıyla (Hero, Ürün Kartları,
  Footer…). Bölümün yanındaki **↑ ↓** okları ana sayfa sırasını değiştirir; üst menü ve
  footer "sabit" olarak işaretlidir. Bir bölüme tıklandığında aynı sütun düzenleyiciye
  döner, **← Bölümler** ile geri dönülür.
- **Üst şeritte site seçici** — ağdaki manifestli her site (şu an ahsapkasa, istanbulpaletci,
  ithalkeresteci ve kavakkeresteci). Seçim değiştiğinde panel o sitenin manifestine göre yeniden kurulur.
- **Her sayfanın son bölümü "Arama ve Paylaşım"**: o sayfanın Google başlığı, açıklaması
  ve paylaşım görseli (bkz. [SEO ve GEO](#seo-ve-geo)).

Teknik bilgi gerekmez: manifest, alan anahtarı gibi kavramlar panelde görünmez;
her alan kendi Türkçe adıyla listelenir.

Yapabilecekleriniz:

| İşlem | Nasıl |
| --- | --- |
| Metin / bağlantı düzenleme | Alanı doldurun; bağlantılarda `/kurumsal/`, `#teklif`, `tel:`, `mailto:` kabul edilir |
| Görsel değiştirme | Medya kitaplığından seçin **veya** yeni dosya yükleyin (seçili sitenin medya kitaplığına gider) |
| Görsel kaldırma | Seçim kutusunda "— Görsel yok (kaldır) —" |
| Alt metin | Görselin altındaki alan; WordPress medya kaydına yazılır |
| İkon değiştirme | 16 ikonluk listeden seçim |
| Satır ekleme / silme | Tekrarlı bileşenlerde **+ Satır ekle** / **Sil** |
| Sıra değiştirme (menü dahil) | Satırlardaki **↑ ↓** düğmeleri |
| Yayınlama | **Kaydet ve Yayınla** — kaydettiğiniz anda sitede görünür |
| Vazgeçme | **Vazgeç** — kaydedilmemiş değişiklikleri atıp son kayıtlı hâle döner |
| Bölüm sırası | Bölüm listesindeki **↑ ↓** okları; kaydetme gerekmez, anında yayınlanır |
| Önizleme | Ortadaki canlı önizleme; kaydettikten sonra kendiliğinden yenilenir |

Panel başlığının altındaki uyarı taslak olmadığını açıkça söyler; formda değişiklik
yaptığınızda "kaydedilmedi" rozeti çıkar ve sayfadan ayrılmak isterseniz tarayıcı uyarır.

JavaScript kapalıyken de panel çalışır: bölüm bağlantıları normal sayfa geçişi olur ve
form klasik yolla gönderilir. Bu durumda tıkla-düzenle ve anlık yenileme devre dışı kalır.

## Claude Code ↔ WordPress (MCP)

WordPress 6.9 ile **Abilities API** çekirdeğe girdi; bu demo o API'yi kullanır ve
resmî [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) eklentisiyle
MCP'ye açar. Adapter her şeyi kendiliğinden açmaz — hangi yeteneklerin sunulacağı
[mcp-server.php](wp-content/plugins/network-content-studio/includes/mcp-server.php)
içinde tek tek sayılır. Bu demoda açılan dört yetenek:

| Araç | Ne yapar |
| --- | --- |
| `nwcs-list-sites` | Demo sitelerini listeler (okuma) |
| `nwcs-describe-site` | Sitenin sayfa/bileşen/alan yapısını verir (okuma) |
| `nwcs-get-field` | Tek alanın değerini okur |
| `nwcs-update-field` | Tek alanı günceller (yazma) |

Yazma korumaları: `manage_network_options` yetkisi, yalnızca ağdaki manifestli demo
siteleri, yalnızca yerel adresler (`localhost`, `127.0.0.1`, `.test`, `.local`),
yalnızca manifestte tanımlı alanlar, panelle aynı tür bazlı temizleme. Görsel alanları
ve toplu değişiklikler bu yüzeyin dışındadır.

Bağlanmak için:

```bash
bash scripts/mcp-setup.sh
```

Windows'ta Git Bash'ten çalıştırın (proje klasöründe sağ tık → "Open Git Bash here").
Docker Desktop açık olmalı.

Betik MCP Adapter'ı kurar/etkinleştirir, `admin` için bir **uygulama parolası** üretir
(aynı isimli eskiler silinir) ve proje kökünde **`.mcp.json`** dosyasını yazar. Claude Code
bu dosyayı kendiliğinden okur; ayrıca `claude` CLI kurulu olması gerekmez (VS Code
eklentisiyle de çalışır). Dosya kimlik bilgisi taşıdığı için `.gitignore`'dadır; depoda
yalnızca [.mcp.json.example](.mcp.json.example) durur.

Sonra: VS Code penceresini yeniden başlatın → proje MCP sunucusu onayını kabul edin →
`/mcp` ile bağlantıyı doğrulayın.

Terminalde doğal dille:

> Paletçi ana sayfasındaki hero başlığını "Ölçüye özel ahşap palet" yap

Uç nokta: `http://localhost:8080/wp-json/nwcs/v1/mcp` (streamable HTTP; `initialize` →
`Mcp-Session-Id` → `tools/call`).

## Ürün Havuzu

**Ağ Yönetimi → Ürün Havuzu**
http://localhost:8080/wp-admin/network/admin.php?page=nwcs-pool

Ürünler burada **bir kez** girilir; siteler oradan beslenir. Her siteye tek tek ürün
girme ihtiyacı yoktur.

- Ürün alanları: ad, kart açıklaması, fiyat, **detay başlıkları** (teknik detaylar), kategoriler,
  **görseller (galeri)**, ürün açıklaması (detay sayfası metni).
- Havuzun alt sayfaları, sırayla: **Ürünler** (liste ve form), **Kategoriler** (yeni kategori,
  sitelerde yerleşim, Excel, son işlemler), **Ürün açıklamaları**, **Detay başlıkları**.
- Ağ yönetimi sol menüsü: son kullanıcı menüleri art arda **İçerik Stüdyosu**, **Ürün Havuzu**,
  **Medya Havuzu**; geliştirici ekranları en altta tek **Geliştirici** menüsünde: SEO ve GEO,
  Yönlendirmeler, Toplu Güncelleme, Görsel Yer Tutucu, E-posta Testi, **Havuz Paketi**
  (kurulumlar arası taşıma, ileri düzey). Sayfa adresleri (`admin.php?page=…`) değişmedi.
- Ürün formundaki **Kategoriler** kutusundan ürünün kategorileri seçilir; kategori açma, ad
  değiştirme ve silme **Kategoriler** sayfasındadır.
- **Arama, kategori filtresi ve sayfalama** listenin üstünde. Arama ürün adında ve kodunda
  yapılır; Türkçe harf ve büyük/küçük harf farkı gözetilmez ("civi" = "ÇİVİ").
- **Eksik** sütunu ve süzgeci (0.23.0): her satırda eksikler küçük çip olarak yazar
  (fotoğraf · açıklama · detay; tamsa "—"). Süzgeç: fotoğrafı olmayan, açıklaması olmayan,
  teknik detayı olmayan, hiçbir sitede görünmeyen. Sayfalama ve toplu işlem süzgeci korur;
  süzgeç yokken sayının yanında "N üründe fotoğraf yok" bağlantısı. Örnek görsel
  (`ornek-urun-*.png`) fotoğraf sayılır.
- **Sitelerde durum şeridi** (ürün formunun başı, 0.23.0): site başına "görünüyor ·
  WOODGarden › Kamelyalar · 12 / 59" ya da "görünmüyor: neden" ve tek tık düzeltme:
  **Listeye ekle** (seçili değil), **Göster** (toplu işlemle gizlenmiş), **Çöpten geri getir**;
  kategorisi siteye yerleşmemişse Kategoriler sayfasına bağlantı (üst başlık seçimi gerekir,
  otomatik yapılmaz). Görünen sitede "Sitede gör ↗". Altında eksikler. "Göster" toplu
  işlemdeki "sitede göster" ile aynı yazıcıyı kullanır (`nwcs_product_show_on_site`).
- **Toplu işlem**: birden çok ürünü seçip "çöp kutusuna taşı", "şu sitede göster/gizle" ya da
  "kategoriye ekle". İşlemden sonra liste aynı süzgeçle (kategori, arama) döner.
- **Kategori bazlı Excel** (aşağıda): kategorinin dosyasını indir, düzenle, yükle. CSV ile giriş
  0.20.0'da, serbest Excel sihirbazı 0.21.0'da kaldırıldı.
- **Çöp kutusu**: "Çöp kutusuna taşı" ürünü sitelerden kaldırır ama silmez; üst şeritteki
  "Çöp kutusu" görünümünden **Geri getir** (sitelerde eski yerine döner) ya da **Kalıcı sil**.
  Çöp kutusunda da ürünler seçilip toplu geri getirilir ya da kalıcı silinir (onaylı). Toplu
  kalıcı silmede site seçimleri sonda tek geçişte temizlenir. Yüzlerce ürünlük seçimlerde
  sunucu zaman aşımına karşı işlemi sayfa sayfa yapın.
- Kaydedilemeyen form (başka üründe kullanılan kod, boş ad) hiçbir şey yazmaz; girilenler
  formda geri gelir ve kodun hangi üründe kullanıldığı yazar.
- **Görseller** ağ ana sitesinin WordPress medya kitaplığına yüklenir ve oradan silinir.
  Bir ürüne bağlı görsel silinemez; önce ürünün görselini değiştirmeniz gerekir.
  Toplu fotoğraf için **Fotoğraf Kutusu** (aşağıda). Formdaki **Kitaplıktan ekle** bir arama
  kutusudur (dosya adı, başlık, alt metin ya da ürün kodu; en çok 30 sonuç, en yeni önce;
  boş aramada son 30). JavaScript kapalıyken kutu formu yeniden açar, sonuçlar işaretlenip
  "Kaydet"le galerinin sonuna eklenir. Eski "son 200 görsel" açılır listesi kalktı.
- **Fiyat boş bırakılırsa** sitede fiyat yerine **“Teklif al”** görünür.
- Tabloda her ürünün hangi sitelerde göründüğü yazar.

### Hangi site neyi gösterir

**Kategori yerleşimi (0.22.0).** Bir havuz kategorisinin hangi sitede, hangi üst başlığın
altında göründüğü veridir ve tek yerden yönetilir: **Ürün Havuzu → Kategoriler →
kategori → Sitelerde**. Üst başlıklar sitenin kendi panel satırlarıdır:

| Site | Üst başlık | Nereden gelir |
| --- | --- | --- |
| WOOD KOCIST | Seri (WOODPets, WOODGarden, WOODLiving) | İçerik Stüdyosu → Ana Sayfa → Seriler |
| Koçist | Ürün grubu (Kereste, Ambalaj, Dekorasyon, Hırdavat) | Tüm Sayfalar → Üst Menü, "Ürün grubu anahtarı" dolu satırlar |

- Yerleşim havuz kategorisinin terim meta'sında durur: `_nwcs_placement = { site_key => üst başlık }`.
  Taksonomi düz kalır (hiyerarşi yok). Temalar kendi kategori listesini taşımaz; eklentinin
  `nwcs_site_category_tree()` ve `nwcs_product_place()` fonksiyonlarını okur
  (`includes/product-categories.php`).
- Her yerleşik kategori için eklenti, temanın manifestindeki `catalog` şablonundan panel
  sayfası üretir: `kat-<havuz-slug>` (WK'da görünür sekme, Koçist'te gizli), üst başlıklar için
  `seri-<anahtar>` / `grp-<anahtar>`. Kategorinin sayfa metni (menüdeki ad, başlık, tanıtım
  cümlesi) Kategoriler sayfasındaki **"Sayfa metni: … düzenle ↗"** bağlantısıyla açılır.
- Adresler: WK `/urun-kategori/<seri>/<havuz-slug>/`, Koçist `/kategoriler/<grup>/<havuz-slug>/`.
  Koçist'te adresi havuz slug'ına geçen dört eski adres 301 ile yönlenir (kamelya → kamelyalar,
  cardak → cardaklar, ahsap-sezlong → ahsap-sezlonglar, adirondack-sandalye → adirondack).
- WK menüsünde ve mağazada yalnızca **bu sitede ürünü olan** kategori görünür; Koçist menüsü
  bütün yerleşik kategorileri gösterir (ürünü olmayan kategori "teklif alın" sayfasıdır).
  Havuzda karşılığı olmayan metin kategoriler (Klipsler, Bağlama Telleri) Koçist'in
  `menu_hirdavat` bileşeninde çapalı bağlantı olarak kalır.

**Görünürlük.** Kategori bir siteye yerleşince ürünleri o sitede görünür. Site "Seçilenler"
kipindeyse ürün kimlikleri seçim listesinin **sonuna** eklenir: yerleşim yapıldığında, ürün
kategoriye eklendiğinde (form, toplu işlem), ürün yaratıldığında ya da çöp kutusundan geri
geldiğinde (form, Excel, Havuz Paketi). "Hepsi" kipinde bir şey yazılmaz. Sürekli eşitleme
yoktur: İçerik Stüdyosu'ndan çıkarılan ürün geri eklenmez. Yerleşim kaldırılırsa o sitede başka
yerleşik kategorisi olmayan ürünler seçimden çıkar; önce kaç ürün olduğu sorulur ve işlem
"Son işlemler"den geri alınabilir.

İçerik Stüdyosu'nda ürün bölümünde sıra, siteye özel ad/fiyat ve gizleme aynen durur:

| Ayar | Ne yapar |
| --- | --- |
| **Hepsi** | Havuzdaki tüm ürünler görünür; yeni ürün eklenince otomatik çıkar |
| **Seçilenler** | Yalnızca işaretledikleriniz; sırayı ↑↓ ile siz verirsiniz |
| **İstisnalar** | Bu siteye özel ad, açıklama, görsel, fiyat; ya da ürünü bu sitede gizleme |

Havuzu kullanacak yeni bir tema manifestine `products` türünde bir alan, kategori yerleşimi
için `catalog` bloğu ekler (örnek: `woodkocist-theme/content-manifest.php`). İlk kurulumda
`catalog.seed` bir kez yerleşim yazar (site başına, `nwcs_placement_seed_version`). Havuzu
örnek ürünlerle doldurmak için (yalnızca geliştirmede):
`wp eval-file /scripts/seed-products.php --url=http://localhost:8080/`.

### Site önbelleği (LiteSpeed, Cloudflare)

Havuz verisini değiştiren her işlemden sonra (ürün, kategori, yerleşim, Excel, açıklama, geri
alma, başlık) sitelerin sayfa önbelleği kendiliğinden temizlenir (`includes/cache-purge.php`):

- **LiteSpeed Cache** eklentisi kuruluysa havuz ürünlerini gösteren her sitede
  `litespeed_purge_all` çağrılır. Kurulu değilse hiçbir şey olmaz.
- **Cloudflare** yalnızca `wp-config.php`'de iki sabit tanımlıysa temizlenir ("Purge Everything"):

  ```php
  define( 'NWCS_CF_ZONE_ID', 'bolge-kimligi' );   // birden çok alan adı: virgülle ayırın
  define( 'NWCS_CF_API_TOKEN', '...' );           // yalnızca "Zone → Cache Purge" izinli API anahtarı
  ```

  Bölge kimliği Cloudflare'de alan adının Overview sayfasının sağ altındadır; anahtar
  My Profile → API Tokens → Create Token ile yalnızca Cache Purge izniyle açılır. Anahtar
  depoya konmaz, hiçbir yere yazılmaz. Temizlenemezse panel düz Türkçe uyarır ("Cloudflare
  panelinden Purge Everything yapın").

## Ürün kodu

**Ürün kodu zorunlu; otomatik kod verilmez (0.23.0).** Kodu firma girer; fotoğraf adları bu
kodla başlar (Fotoğraf Kutusu). Ürün formunda kod alanı zorunludur: boş kodla kaydedilmez,
girilenler formda kalır ve alan işaretlenir. Kodu olmayan eski ürünler formda açılınca kod
yazılmadan kaydedilemez (bilinçli). Excel'de ÜRÜN KODU boş satır yüklenmez. Aynı kod iki üründe
kullanılamaz. Eski `URN-0006` biçimli kodlar (önceki sürümlerin ürettiği) olduğu gibi kalır.

Excel yüklemesinde eşleşme sırası: gizli **KİMLİK** sütunu, yoksa **ürün kodu**, ikisi de yoksa
yeni ürün. Çöp kutusundaki ürünler de aranır: çöpteki bir ürünün kodu gelirse ürün geri
getirilir, aynı koddan ikinci ürün açılmaz. Kod değişikliği ürünün adresini (`/urun/…/`)
değiştirmez.

## Site bazlı özelleştirme (Özelleştirmeler)

Bir ürün bütün sitelerde havuzdaki hâliyle görünür. Bir site için farklı bir ad, kart
açıklaması, fiyat ya da öne çıkan görsel isteniyorsa, Ürün Havuzu'nda ürünü düzenlerken
**Özelleştirmeler** bölümünden site site girilir. Sayfadan ayrılmadan kaydedilir.

- Boş bıraktığınız alan havuzdaki değeri kullanır.
- "Bu sitede farklı fiyat" işaretlenip boş bırakılırsa o sitede *"Teklif al"* görünür.
- **Özelleştirmeyi kaldır** o sitenin bütün istisnalarını siler, ürün havuzdaki hâline döner.
- Temasında ürün bölümü olmayan siteler burada listelenmez, ayrıca belirtilir.

### Ürün sayfası adresi (site başına)

Ürün sayfası `/urun/<adres>/` ilk eklendiğinde ürün adından otomatik oluşur (havuzdaki
adres). Her site için ayrı ayrı değiştirilebilir: Ürün Havuzu → ürün formu →
**Özelleştirmeler** → ilgili sitenin kutusu → **Sayfa adresi** → Kaydet.

- Yazılan adres temizlenir: Türkçe harfler sadeleşir (ş→s, ı→i…), boşluklar tire olur.
- Aynı sitede başka bir ürünün adresi (otomatik adresi dahil) ya da bir sayfanın adresi (ör. `iletisim`) alınamaz;
  panel düz Türkçe bir hata gösterir ve hiçbir alanı kaydetmez.
- Eski adresler kaybolmaz: havuzdaki adres ve o sitede daha önce kullanılmış adresler (en
  fazla 20) güncel adrese **301** ile yönlenir; Yönlendirmeler listesine elle kayıt gerekmez.
- **Otomatiğe dön** yalnızca adresi havuzdaki adrese döndürür; özel adres de yönlenmeye devam eder.
- Kartlar, menüler, site haritası, canonical, Product verisi, llms.txt ve Koçist teklif formu
  kendiliğinden yeni adresi kullanır. Diğer sitelerde ürünün adresi değişmez.
- İçerik Stüdyosu'ndaki ürün satırı adresi yalnızca gösterir; **değiştir ↗** bu kutuya götürür.
- Excel'de adres sütunu yoktur; `/urun/` kökü sabittir.

## Detay başlıkları

Ürün sayfasındaki teknik detaylar (Ahşap Cinsi: Çam, Kurulum: …) her üründe
`_nwcs_details` kaydında sıralı etiket/değer listesi olarak durur. Eski tek satırlık
`_nwcs_spec` metni bundan türetilir; ikisini yazan tek fonksiyon
`nwcs_product_write_details()`'tir (form, Excel, Havuz Paketi, başlık ekranı hep onu çağırır).

**Ürün Havuzu → Detay başlıkları** ekranı:

- Her başlığın kaç üründe ve hangi kategorilerde kullanıldığı, kaç üründe boş olduğu.
- **Sıra** (↑ ↓): kategori Excel'indeki sütunların sırası ve formda yeni satırların yeri.
  Sitede her ürün **kendi** sırasıyla görünür; Excel'den detayı yazılan ürün sütun sırasını alır.
  Taşımalar sayfa yenilenmeden yapılır, "Değişiklikleri kaydet" ile tek seferde yazılır.
- **Zorunlu**: kategori Excel'inde her üründe dolu olmalı; boş satır yüklenmez. Formda yalnızca
  "eksik" uyarısı verir. Kutu işaretlenince kaç üründe boş olduğu görünür.
- Sistem sütunlarıyla aynı ad (Kimlik, Ürün kodu, Ürün adı, Fiyat, Kısa açıklama) yeni başlık
  olamaz. Kayıtta zaten olan "Fiyat" (Koçist zımba verisi) Excel'de "Fiyat (detay)" sütunudur.
- **Düzenle**: yeniden adlandır (kullanan ürünlerde de değişir), başka başlıkla birleştir,
  hiçbir üründe kullanılmıyorsa sil.

Ürün formundaki **Detay başlıkları** bloğunda zorunlular üstte, sabit adlı; diğerleri
başlık + değer satırlarıdır. "+ Başlık ekle" yeni satır açar; yeni yazılan başlık listeye
kendiliğinden eklenir. Kayıt ilk açılışta havuzdaki spec metinlerinden kurulur; hiçbir başlık
kendiliğinden zorunlu olmaz.

Temalar değerleri `nwcs_product_pairs( $product )` ile okur (ürünün kendi sırasıyla).

## Kategori bazlı Excel

Ürün Havuzu → **Kategoriler** → kategori → **Ürünleri Excel ile girin** → **Excel indir**. İnen dosya
(`urunler-<kategori>-<tarih>.xlsx`):

- **Ürünler** sayfası: `KİMLİK` (gizli) · `ÜRÜN KODU *` · `ÜRÜN ADI *` · `SİTE` · `FİYAT` · `KISA AÇIKLAMA` ·
  zorunlu başlıklar (`*`) · kategorinin ürünlerinde kullanılan diğer başlıklar. Hücreler metin
  biçiminde; başlık satırı dondurulmuş.
- **Nasıl kullanılır** sayfası: numaralı 1–8 adım.
- **_bilgi** (gizli): kategori ve indirme zamanı. Yükleme kategoriyi buradan okur; bu sayfa
  yoksa dosya reddedilir ("Bu dosya panelden indirilen taslak değil").

Düzenlenen dosya aynı yerdeki **Yükle** ile yüklenir (başka bir kategorinin dosyası
yüklenirse önizleme o kategorinin sayfasında açılır). Sütun eşleştirmesi yoktur.
Önce **önizleme** gelir, hiçbir şey yazılmaz: kaç ürün güncellenecek / eklenecek / çöp kutusuna
gidecek / kaç satır hatalı, her ürünün alan alan farkı ve hatalı satırların nedeni.
Önizleme ürünlerin hangi sitelerde görüneceğini yerleşimden söyler ("Koçist (Dekorasyon) ve
WOOD KOCIST (WOODGarden) sitelerinde görünecek"); kategori hiçbir siteye yerleşmemişse
uyarır. Yeni ürün satırlarında fiyat ve zorunlu başlık değerleri görünür. Hatalı satırların
hepsi aynı zorunlu başlıkta takıldıysa Detay başlıkları'na bağlantılı ipucu çıkar.
**Değişiklikleri uygula** yazar, **Vazgeç** iptal eder.

Kurallar:

- **Boş hücre = temizle.** Boş fiyat sitede "Teklif al" olur, boş başlık üründen çıkar.
- Dosya yalnızca indirildiği panelde yüklenir (`_bilgi` → kaynak); başka panelden gelen dosya
  reddedilir, çünkü KİMLİK'ler kuruluma özeldir. 20.000 satırı aşan dosya reddedilir.
- Sistem sütunları harf farkı gözetmeden tanınır ("Fiyat" = "FİYAT").
- **Zorunlu başlık boşsa** satır yüklenmez, ürün olduğu gibi kalır (satır numarası ve nedeni yazar).
  Adı boş satır, dosyada iki kez geçen kod, başka üründe kullanılan kod de aynı şekilde reddedilir.
  **ÜRÜN KODU boş** satır da yüklenmez ("ÜRÜN KODU boş. Her ürünün kodu olmalı; fotoğraflar bu
  kodla eşleşir."); ürün olduğu gibi kalır, çöp kutusuna gitmez. Taslakta sütun "ÜRÜN KODU *"
  olarak gelir; eski taslaklardaki "ÜRÜN KODU" başlığı da tanınır; sütun hiç yoksa dosya reddedilir.
  **Kopyalanmış satır** (aynı gizli KİMLİK): kodu farklıysa yeni ürün sayılır (önizlemede not
  düşülür); kodu da aynıysa hata verir.
- **Dosyada olmayan ürün**: kategoride olup dosyada bulunmayan ürünler önizlemede kırmızı
  kutuda, diğer kategorileri ve göründükleri sitelerle listelenir. "Evet, bu N ürünü çöp
  kutusuna taşı" işaretlenirse çöp kutusuna gider; işaretlenmezse dokunulmaz.
- **Yeni satır** yalnızca indirilen kategoriye eklenir ("eşlik eden kategori" kuralı 0.22.0'da
  kalktı); kategorinin yerleştiği sitelerde seçilir (bkz. Görünürlük). Kod zorunlu; otomatik
  kod verilmez. Önizlemeden sonra kod başka ürüne verildiyse satır eklenmez (atlananlarda yazar).
- **Yeni sütun** (en az bir hücresi dolu) yeni detay başlığı olur.
- **SİTE** (zorunlu değil): ürünün görüneceği site. `Hepsi` (havuzu kullanan bütün siteler:
  bugün Koçist ve WOOD KOCIST; yeni katalog sitesi kendiliğinden girer) ya da site adları
  virgülle/noktalı virgülle. Yazım serbest: `Koçist`, `KOÇİST`, `kocist`, `WOOD KOCIST`,
  `wood kocist`, `woodkocist.com.tr`. Anlaşılmayan değer satır hatasıdır ("SİTE değeri
  anlaşılamadı: “kocis”. Yazılabilecekler: Hepsi, Koçist, WOOD KOCIST (virgülle birden fazla).").
  **Boş = değişmez** (bu sütunda "boş = temizle" ürünü her yerden gizlerdi); yeni üründe boşsa
  kategorinin yerleşimi geçerli. Yazılmayan sitede ürün **gizlenir** (toplu "gizle" ile aynı
  istisna; silinmez, durum şeridinden ya da yeniden "Hepsi" yazarak geri gelir), yazılan sitede
  gösterilir. Taslakta SİTE bugünkü hâliyle dolu gelir ("Hepsi" ya da adlar; hiçbir sitede
  değilse boş, önizleme bunu not eder). Önizleme ürün başına "Koçist’ten kalkacak",
  "WOOD KOCIST’e eklenecek" yazar; yazılan siteye kategori yerleşmemişse uyarır. Geri alma
  görünürlüğü birebir eski hâline getirir.
- Salt rakam fiyat (Excel'in sayı yaptığı ya da "3500" yazılan) "3.500 ₺" biçimine getirilir;
  "48.500 ₺", "450 TL" gibi metne dokunulmaz.
- Dosyada olmayan alanlara dokunulmaz: görseller, uzun açıklama, tablolar, sıra, diğer
  kategoriler, sitelere özel ayarlar.
- Dosyayı indirdikten sonra panelde değişen ürünler önizlemede uyarılır (Excel kazanır);
  önizlemeden sonra değişen ürün (çöpe gidecek olanlar dahil) atlanır.
- Ürünleri birden çok sitede görünen kategori önizlemede "Ortak kategori" uyarısıyla
  belirtilir; güncellenecek her ürünün siteleri yazar.
- "Uygula"ya iki kez basmak ikinci kez yazmaz. Geri alma kaydı her üründen sonra güncellenir:
  istek yarıda kesilse de "Geri al" yapılanları geri alır (kayıt "yarıda kesildi" der).
- Sunucuda çöp kutusu kapalıysa (`EMPTY_TRASH_DAYS = 0`) hiçbir şey çöpe atılmaz (kalıcı silme
  olurdu); önizleme ve form bunu söyler.
- Çöpteki ürün sitelerin seçiminde yerinde kalır (site kaydedilse de); kalıcı silinen ürün
  (`deleted_post`) seçimlerden çıkar.

**Son işlemler** (Kategoriler ve Ürün açıklamaları sayfalarında aynı kart): ürün Excel'i,
açıklama Excel'i ve yerleşim değişiklikleri `nwcs_pool_history` yığınında tutulur (son 10
işlem). Yalnızca **en üstteki** geri alınır; sıra en yeniden eskiye. Önizleme, uygulanırsa geri
alma sırasını yazar. Geri alma: yeni ürünler çöp kutusuna gider (ve sitelerin seçiminden
çıkar), güncellenenler eski hâline döner, çöpe gidenler geri gelir, bu yüklemede doğan
başlıklar kaldırılır. Geri alınan ürünün değişiklik zamanı da eski hâline konur; böylece
sıradaki işlemin "işlemden sonra elle düzenlendi" denetimi yalnızca gerçek elle düzenlemeyi
yakalar (o ürüne dokunulmaz, raporlanır). Eski tek kayıtlar (`nwcs_sync_last`,
`nwcs_desc_last`) ilk açılışta yığına taşınır.

Paylaşılan kategorilere dikkat: Çardaklar, Kamelyalar ve Adirondack hem WOOD KOCIST hem Koçist
ürünlerini içerir. Bu kategorinin dosyasından silinen satır iki sitede de ürünü kaldırır;
önizlemedeki "Göründüğü siteler" bunu gösterir.

## Fotoğraf Kutusu (0.23.0)

**Ürün Havuzu → Kategoriler → kategori → Fotoğrafları yükleyin** (Excel kartının altında).
Excel'in anahtarı ürün kodu; fotoğrafın anahtarı da ürün kodu.

- **Dosya adı ürün koduyla başlar:** `W-KAM-400DUB-1.jpg`, `W-KAM-400DUB-2.jpg` … Ayraç serbest:
  tire, alt çizgi, nokta, boşluk, parantez aynı sayılır (`w_kam_400dub (2).JPEG` olur). Koddan
  sonraki ilk sayı galerideki sıradır; sayısız dosya numaralılardan sonra gelir; ilk fotoğraf
  kartta kullanılır. Birden çok kod uyarsa en uzunu kazanır (`W-KAM-400` kodu
  `W-KAM-400DUB-1`'i yakalamaz). Kod adın başında değilse, 5 karakterden uzun kodlar adın içinde
  de aranır (`woodpets-w-dog-v06-img010.webp` → `W-DOG-V06`, sıra 10). Başka kategorideki bir
  ürünün koduyla başlayan dosya o ürüne bağlanır (önizlemede ayrı grupta yazar).
- **Kodu olmayan ürüne fotoğraf bağlanamaz.** Ürün kodu zorunlu; otomatik kod verilmez. Kart,
  kategoride kaç ürünün kodu olmadığını yazar (yerelde 320 üründen 237'sinin kodu yok; çoğu
  Koçist). Kodu firma ürün formundan ya da Excel'in ÜRÜN KODU sütunundan girer.
- JPG, PNG, WebP ve iPhone HEIC; dosya başına ağ sınırı (Ağ Ayarları "En fazla yükleme dosya
  boyutu"; **canlıda 1500 → 10240 KB şart**). Dosyalar tarayıcıdan tek tek, sırayla yüklenir
  (ilerleme ve dosya başına sorun satırı); yükleme Medya Havuzu'yla aynı WebP dönüşümünden
  geçer. Sunucu HEIC açamazsa dosya reddedilir ("sunucu HEIC açamadı; JPG olarak kaydedip
  yükleyin"); tarayıcının gösteremeyeceği dosya galeriye girmez. GIF/SVG reddedilir.
- **Önizleme** (hiçbir ürün değişmez): ürün başına yeni fotoğraflar sırasıyla (ölçü, yatay ya da
  oranı farklıysa not), mevcut galeri soluk; dosya başına **Çıkar** kutusu; eşleşmeyen dosyalar
  nedeniyle (kod bulunamadı, çöp kutusundaki ürünün kodu, tür, boyut). Mevcut galerisi olan
  ürünlerde **Sona ekle** (varsayılan) ya da **Galeriyi değiştir** (eski fotoğraflar Medya
  Havuzu'nda kalır).
- **Fotoğrafları uygula:** galeri (`_nwcs_gallery`) ve öne çıkan görsel yazılır; ek başlığı
  "<KOD> görsel NN", alt metin boşsa ürün adı. Eşleşmeyen ve çıkarılan dosyalar silinir.
  "Son işlemler"e kayıt düşer; **Geri al** galerileri eski hâline getirir, bu yüklemede gelen
  ve başka üründe kullanılmayan dosyaları siler; sonradan elle değişen ürüne dokunmaz.
  İki kez basma, eski sekme, başka sekmede yeni yükleme güvenli (açık hata metni).
- Dosyalar doğrudan havuzun medya kitaplığına "parti" işaretiyle (`_nwcs_photo_batch`) yüklenir;
  kullanıcı başına tek bekleyen parti (1 saat). Bekleyen dosyalar Medya Havuzu'nda ve kitaplık
  aramasında görünmez; uygulanmayan parti 24 saat sonra Kategoriler sayfası açılırken silinir.
  Yeni yükleme başlatılırsa bekleyen eskisi silinir (kart bunu yazar).
- **JavaScript kapalıyken:** çoklu dosya alanı + "Yükle" (bir seferde en çok `max_file_uploads`
  dosya, toplam `post_max_size`; aşılırsa açık hata). Önizleme, Çıkar, Uygula, Geri al aynı.
- Kod: `includes/photos.php` (eşleme, plan, uygula, geri al), `includes/admin/pool-photos.php`
  (kart, önizleme, istekler), `assets/pool-photos.js`. Denemeler:
  `scripts/dev/check-photo-match.php`, `scripts/dev/check-photo-flow.php`,
  `scripts/dev/check-required-code.php` (zorunlu kod).

İçerik Stüdyosu'ndaki ürün satırlarında kodun yanında üç nokta (fotoğraf, açıklama, teknik
detay; dolu = var); fotoğrafı yoksa "Fotoğraf ekle ↗" kategorinin Fotoğraf Kutusu'nu açar.

## Renk grupları (0.27.0)

**Ürün Havuzu → Kategoriler → kategori → Renk grupları** (Fotoğraf Kutusu'nun altında).
Excel'de her renk ayrı ürün olarak girilmeye devam eder (kendi kodu, fotoğrafı, fiyatı, sayfası);
burada aynı ürünün renkleri tek gruba toplanır. Excel değişmez.

- **Renk grubu oluştur:** kategorinin ürünlerinden en az ikisi işaretlenir (kod ve ada göre
  aranır). Başka bir renk grubundaki ürün seçilemez, nedeni yazar; bir ürün havuz genelinde
  tek grupta olur. Ürün adında hazır bir renk geçiyorsa renk adı ve yuvarlağı dolar.
- **Grup kartı:** başlık ("Renk"; "Çatı rengi" gibi değişir), üye satırları: sıra ↑ ↓, küçük
  resim, kod, ad, renk adı, renk yuvarlağı (hazır renkler: Beyaz, Siyah, Antrasit, Gri, Kiremit
  Kırmızı, Zümrüt Yeşili, Teak, Ceviz, Doğal Çam, Amber (Açık Meşe), Nussbaum, Mavi, Sarı ya da
  özel renk), "Gruptan çıkar", "Gruba ürün ekle", "Grubu sil…" (onay sorulur). 1. satır
  **öne çıkan**dır. Çöpteki, silinmiş ya da kategoriden çıkmış üye uyarıyla görünür, sitede
  yok sayılır. Ürünler tablosunda "renk grubu: X" rozeti; ürün formunda tek satır
  "Renk grubu: Renk · 3 renk · Düzenle ↗" (ikinci bir düzenleme yeri yok).
- Her değişiklik "Son işlemler"e düşer (`renk`) ve geri alınır; kayıttan sonra havuz ve site
  önbelleği temizlenir. JavaScript yoksa her düğme formu kaydeder; JS ile ↑ ↓ satırı yerinde
  taşır, "Grubu kaydet" yazar.
- **Sitede (Koçist, WOOD KOCIST):** kategori, seri/grup, mağaza ve ana sayfa listelerinde grup
  tek kart: o sitede görünen ilk üye (öne çıkan gizliyse sıradaki), adında renk yoksa sonunda
  " – <renk>", altında "3 renk" (panel metni `{sayi} renk`: Koçist Ortak Metinler, WOOD KOCIST
  Ürün Kartları). Menü ve kategori sayıları da tek kart sayar. Sitede tek üye görünüyorsa grup
  yok sayılır. **Arama, sepet, site haritası ve llms.txt** her rengi ayrı ürün olarak görür.
- **Ürün sayfası:** başlığın altında "Renk: Antrasit" ve yuvarlak bağlantılar; her biri o rengin
  bu sitedeki sayfası (site başına adres dahil). Bulunulan renk `aria-current="page"` ve
  halkalı; renk adı fareyle ve odakla görünür. Her renk sayfası kendi canonical'ında kalır;
  JSON-LD'de Product `color`, `inProductGroupWithID`, `isVariantOf` ve bir `ProductGroup`
  (`productGroupID`, `variesBy`, `hasVariant`: sitedeki renkler).
- Veri: havuz kategorisinin terim meta'sı `_nwcs_color_groups`; okuma havuz başına önbellekli
  haritadan (`nwcs_color_map`). Kod `includes/color-groups.php`, `includes/admin/color-groups.php`,
  `assets/color-groups.{js,css}`. Deneme `scripts/dev/check-color-groups.php`.

## Renk listesi (0.27.1)

**Ürün Havuzu → Renkler** (Kategoriler'in hemen altında). Renk grupları'ndaki renk seçim listesi
buradan gelir: bir renk bir kez eklenir, her grupta tek tıkla seçilir.

- **Satır:** renk yuvarlağı, ad, ton (renk seçici ve kodu), kaç üründe kullanıldığı ("6 üründe":
  renk adı Türkçe harf ve büyük/küçük harf farkı gözetilmeden eşleşen grup üyeleri), ↑ ↓, "Sil";
  altta "+ Renk ekle" ve "Kaydet". İlk açılışta eski 13 hazır renk; en fazla 60 renk. Ad zorunlu
  ve tekil, ton geçerli olmalı; hata varsa hiçbir şey kaydedilmez, yazılanlar geri gelir.
- Ürünler kendi renk adını ve tonunu saklar: listeden renk silmek grupları bozmaz. Kullanılan bir
  rengin adı ya da tonu değişince önce onay sayfası gelir: renk başına etkilenen ürünler (kod, ad,
  kategori) ve "Bu rengi kullanan N ürün de güncellensin" (işaretli gelir). İşaretli renklerde
  bütün kategorilerdeki üyelerin tonu, ad değiştiyse adı da güncellenir; site önbelleği temizlenir.
- **Renk grupları'nda:** "Özel renk" seçilince "Bu rengi listeye kaydet"; grup kaydedilince ad
  listede yoksa eklenir. Aynı ad başka tonla kayıtlıysa liste değişmez, üye yine kaydedilir ve
  "‘<ad>’ listede başka bir tonla kayıtlı; listeyi Renkler ekranından düzenleyin." uyarısı çıkar.
  Grup kartında "Renk listesini düzenle ↗". Listedeki bir rengin adı yazılınca seçim o renge geçer.
- Her kayıt "Son işlemler"e düşer (`palet`) ve geri alınır (liste ve güncellenen ürünler); o
  ürünler sonradan değiştiyse geri alma yapılmaz, neden yazılır. JavaScript yoksa her düğme formu
  kaydeder (↑ ↓, "Sil", sondaki boş satırla "+ Renk ekle").
- Veri: ağ ayarı `nwcs_color_palette` = sıralı `[ { id, name, hex } ]` (yoksa ilk 13 renk). Kod
  `includes/admin/color-palette.php`, `assets/color-palette.js`; okuma `includes/color-groups.php`
  (`nwcs_color_palette`, `nwcs_color_presets`). Deneme `scripts/dev/check-color-palette.php`.

## Kategoriye özel sık sorulan sorular (0.27.0)

**Ürün Havuzu → Kategoriler → kategori → Sık sorulan sorular** (Renk grupları'nın altında).
Kategorinin yerleştiği her site için bir sekme ve kendi listesi (en fazla 10 soru); yerleşmediği
site için sekme yok, nedeni bir satırla yazılır. Önerilen: kategoriye özgü 3–6 soru; teslimat,
montaj, ödeme gibi genel sorular sitenin `/sss/` sayfasında kalır.

- **Satır:** soru (tek satır) ve cevap (düz metin, boş satır paragraf); ↑ ↓, "Sil", "Soru ekle",
  sekme başına "Soruları kaydet". Cevabı boş soru kaydedilir ama sitede ve arama verisinde
  görünmez (satırda yazar). Cevabı olup sorusu boş satır kaydedilmez; yazılanlar geri gelir.
- **Kopya uyarısı:** aynı kategorinin başka bir sitesinde aynı ya da çok benzer (%85) cevap
  varsa kayıt yapılır, uyarı soruyu ve siteyi söyler (30 karakterden kısa cevaplar sayılmaz).
  Benzerlik SEO ve GEO tutarlılık kontrolüyle aynı (`nwcs_seo_near_text`).
- "Son işlemler"e `sss` kaydı düşer, geri alınır; aynı kişinin aynı kategori ve sitedeki art
  arda kayıtları 30 dakika içinde tek kayıtta birleşir. Kategori başlığında "SSS: 4 soru".
- **Sitede:** kategori sayfasında (Koçist `/kategoriler/<grup>/<kategori>/`, WOOD KOCIST
  `/urun-kategori/<seri>/<kategori>/`) ürünlerin altında "Sık sorulan sorular" akordeonu
  (`#sss`) ve yalnızca bu sayfada FAQPage (sayfa düğümü `WebPage` + `FAQPage`). Ürün sayfasında
  kategorinin ilk 3 sorusu ve "Tüm sorular" (kategori sayfası `#sss`); FAQ şeması yok. Koçist'te
  kategoride soru yoksa akordeonun son satırı eskisi gibi grubun SSS bağlantısıdır.
  `llms.txt`'de "Kategorilere özel sık sorulan sorular" bölümü. Başlık ve bağlantı metinleri
  panelde: Koçist Kategoriler → `faq_title`, Ürün sayfası → `faq_title`, `faq_all`; WOOD KOCIST
  Mağaza → `faq_title`, Ürün Sayfaları → `faq_title`, `faq_all`.
- Veri: havuz kategorisinin terim meta'sı `_nwcs_cat_faq` = `[ site_key => [ { q, a } ] ]`.
  Kod `includes/category-faq.php`, `includes/admin/category-faq.php`,
  `assets/category-faq.{js,css}`. Deneme `scripts/dev/check-cat-faq.php`.

## Koçist: arama ve teklif sepeti (kocist-theme 0.6.0)

Sipariş ve ödeme **yok**: ziyaretçi ürünleri toplar, hepsi için tek seferde fiyat ister.

- **Arama `/arama/?ara=<terim>`:** üst menüde büyüteç (betik yoksa arama sayfasına giden
  bağlantı, betikle menünün altında açılan kutu); telefonda çekmecenin başında arama kutusu.
  Kod, ad ve kategori adında arar; Türkçe harf, büyük/küçük harf, boşluk ve tire farkı yok
  ("adrk", "ADR K", "adr-k" aynı). Sıra: kodu tam tutan, kodu terimle başlayan, kodunda geçen,
  adında/kategorisinde geçen, kelimeleri ayrı ayrı geçen. Renk grubu sadeleşmez (her renk ayrı
  kart). Kartlar kategori sayfasının kartı; başlık "‘ADR’ için 6 ürün"; sonuç yoksa iletişim ve
  WhatsApp. En az 2 harf, en fazla 60 sonuç. Sayfa `noindex, follow`, site haritasında ve
  llms.txt'de yok; terimli arama önbelleğe alınmaz (terimsiz `/arama/` alınır). Üç arama
  formunun her birinin kendi bölge adı var ("Ürün ara", "Menüde ürün ara", "Sonuçlarda yeniden ara"). Yardımcılar eklentide ortak (`includes/search.php`: `nwcs_search_fold`,
  `nwcs_search_products`); WOOD KOCIST mağaza araması da aynı katlamayı kullanır.
- **Teklif sepeti `/teklif-sepeti/`:** ürün sayfasında adet ve "Teklif sepetine ekle" (fiyatlı ya
  da fiyatsız her ürün; tek ürünlük teklif formu ve WhatsApp yerinde). Sepet tarayıcıda çerezde
  (`kc_teklif`, `kimlik:adet|...`, en fazla 30 ürün, satırda en fazla 9999 adet); ad, kod ve fiyat
  her zaman havuzdan okunur. Üst menüdeki sayı (ürün çeşidi) betikle çerezden yazılır: sayfalar
  önbellekte kalabilir; sepet sayfası, işleyiciler ve ürün sayfasının `?teklif=` dönüşü
  önbelleğe alınmaz. Satırlar: küçük resim, kod, ad, varsa fiyat (toplam yok), adet − +, Çıkar.
- **Gönderme:** (1) **WhatsApp:** form yok; hazır mesaj (her ürün "kod – ad – adet" ve sayfa
  adresi; adres 1900 karakteri geçerse önce adresler, sonra listenin sonu "+N ürün daha").
  Bütün ürünler aynı gruptaysa grubun numarası (Ürün Sayfaları › WhatsApp ve Teklif), değilse
  genel numara (ürün sayfalarının WhatsApp butonu). Sepet kendiliğinden boşalmaz; "Sepeti
  temizle" var. (2) **E-posta:** ad soyad, telefon ya da e-posta, firma ve not (isteğe bağlı),
  KVKK onayı; bot tuzağı ve nonce. "Teklif Talepleri"ne kaydolur, bildirim `info@kocist.com.tr`'ye
  gider ("Ürünler" listesiyle). Bildirim gidemezse ziyaretçi başarı değil hata görür (kayıt
  panelde durur, sepet ve yazılanlar korunur); başarıda sepet boşalır, "Talebiniz alındı".
  Hız sınırı: aynı adresten 30 saniyede bir, saatte en fazla 10 talep (teklif formuyla ortak);
  sınırda "Az önce bir talep gönderdiniz; lütfen biraz sonra yeniden deneyin." ve yazılanlar korunur.
- Bütün metinler panelde: Tüm Sayfalar › "Üst Menü: Arama ve Teklif Sepeti", gizli sayfalar
  "Arama Sayfası" ve "Teklif Sepeti" (Sayfa bul ya da önizlemede tıklayarak).
- Sayfalar tema sürüm 7'de açılır (`kocist_ensure_pages`): canlıya aldıktan sonra yönetici
  panele bir kez girmeli; o zamana kadar üst menüdeki düğmeler ve ekleme formu görünmez.
- Kod: tema `inc/search.php`, `inc/basket.php`, `template-parts/search-page.php`,
  `template-parts/basket-page.php`, `assets/js/teklif.js`, `assets/css/teklif.css`. Deneme
  `scripts/dev/check-quote-basket.php`.

## Ürün açıklamaları (toplu)

**Ürün Havuzu → Ürün açıklamaları**: bir kategori (ya da tüm ürünler) seçilip Excel indirilir:
`KİMLİK` (gizli) · `ÜRÜN KODU` · `ÜRÜN ADI` · `ÜRÜN AÇIKLAMASI` · `EK İÇERİK (bilgi)`.

- Açıklama düz metne çevrilir: kalın alt başlıklar kendi satırında, paragraflar boş satırla
  ayrılır, madde listesi "• ", alıntı "> " ile. Yüklemede tersine çevrilir (noktasız kısa tek
  satır alt başlık olur).
- Açıklamadaki **tablolar** dosyaya girmez ve hiç değişmez (EK İÇERİK sütununda yazar).
- Görsel, bağlantı gibi özel içerikli hücre "[HTML içerik — panelden düzenleyin]" yazar;
  dokunulmazsa değişmez.
- Metni değişmeyen açıklamaya dokunulmaz. **Boş hücre açıklamayı siler; ürün sayfası
  kapanmaz** (aşağıda). Önizleme ve "Son işlemler"den geri alma var.

## Sınırlar (Excel)

- En fazla **20.000 satır**.
- Sunucu yükleme sınırı `docker/php-uploads.ini` ile 32 MB'a çıkarılmıştır (WordPress imajının
  varsayılanı 2 MB'dır).
- Görseller Excel'le taşınmaz; Fotoğraf Kutusu'ndan (dosya adı = ürün kodu) ya da ürün
  formundan bağlanır.
- İçerik Stüdyosu'ndaki ürün satırının "Görsel (bu sitede)" listesi son 200 görseli gösterir;
  ürün galerisi için havuz formundaki kitaplık aramasını kullanın.
- `.xls` (eski biçim) ve `.csv` yüklenmez; Excel'de "Farklı Kaydet → .xlsx" seçin.
- Excel, "1/2" gibi değerleri tarihe çevirebilir; talimat sayfası uyarır.

## Medya Havuzu

**Ağ Yönetimi → Medya Havuzu**
http://localhost:8080/wp-admin/network/admin.php?page=nwcs-media

Site görselleri (logo, hero, blog) buraya yüklenir; **ürün fotoğrafları kategorinin Fotoğraf
Kutusu'ndan** yüklenir (buradan yüklenen görsel ürüne kendiliğinden bağlanmaz).
Görsellerin tek yerden yönetildiği sayfa: bir seferde birden fazla dosya yükleyin
(isteğe bağlı ortak alt metinle), arayın, başlık ve alt metni düzenleyin, silin.
Her görselin hangi üründe kullanıldığı kartında yazar (ürüne bağlantılı; çöp kutusundaki
ürünler de sayılır); kullanımdaki görsel silinemez, önce ürünün galerisinden çıkarılır.
Kullanılmayan görseller seçilip **Seçilenleri sil** ile toplu silinir (kalıcı, geri alınamaz);
seçime kullanımdaki görsel karışırsa atlanır ve bildirimde sayısı yazar. Büyük seçimlerde
sayfa sayfa silin.

**Alt metinleri Excel ile düzenleyin (0.25.1, `includes/admin/media-alt.php`):** sağ sütundaki
kutuda görsel grubu seçilip Excel indirilir: **Tümü**, **Alt metni boş olanlar** ya da bir
**kategori** (o kategorideki yayındaki ürünlerin galerilerindeki görseller). Sütunlar:
`KİMLİK` (gizli) · `DOSYA` · `ÜRÜN` (kod · ad) · `SİTE` · `ALT METİN`; yalnızca `ALT METİN`
okunur. Tek satır, en fazla 250 karakter (uzunu satır hatası). **Boş hücre alt metni siler**;
önizleme silinecek görselleri ayrıca listeler. Değişmeyen satıra dokunulmaz. Uygulama "Son
işlemler"e girer (Medya Havuzu'nda da görünür) ve geri alınabilir; geri alma eski değeri
birebir koyar, sonradan elle değiştirilen görsele dokunmaz. Fotoğraf Kutusu'nda bekleyen
dosyalar dosyaya girmez. Kontrol: `scripts/dev/check-alt-excel.php`.

**WebP'ye çevirme (0.22.2, `includes/images.php`):** panelden yüklenen JPG, PNG ve HEIC
fotoğraflar yükleme anında en fazla 2560 px'e küçültülür, yönü düzeltilir ve WebP olarak
kaydedilir (kalite 82); orijinal saklanmaz (temalar "full" boyutu da kullanıyor), EXIF/konum
düşer. Ara boyutlar da WebP. Küçültülmeyen ve WebP'si daha büyük çıkan (zaten iyi sıkıştırılmış)
dosya olduğu gibi kalır. GIF ve SVG'ye, ziyaretci form eklerine dokunulmaz; sunucu WebP yazamazsa
dosya olduğu gibi kalır. Eski görseller kendiliğinden çevrilmez.
Yükleme sınırı: ağda **Ayarlar → En fazla yükleme dosya boyutu** varsayılanı 1500 KB; telefon
fotoğrafları için 10240 KB yapın (PHP `upload_max_filesize` / `post_max_size` da en az bu kadar
olmalı). Yükleme formu gerçek sınırı yazar; büyük ya da bozuk dosyada bildirim nedenini söyler.

### Ürün detay sayfası

Bir ürünün **açıklaması (detay sayfası metni)** doldurulursa o ürün kendi sayfasını kazanır:
`/<site>/urun/<ürün-adresi>/` — kart da oraya bağlanır. Metin boşsa çoğu sitede ürünün
sayfası yoktur (adres 404 verir) ve kart doğrudan teklif bölümüne gider.

**WOOD KOCIST ve Koçist**te (manifestte `'product_page_always' => true`) açıklaması olmayan
ürünün sayfası da açıktır: ad, görsel, fiyat ve teknik detaylar görünür, yalnızca açıklama
bölümü olmaz; kartlar sayfaya bağlanır ve sayfa site haritasına girer. Kural tek yerde:
`nwcs_product_has_page()`. Detay sayfası da site istisnalarını uygular.

**WOOD KOCIST ürün sayfası** canlı woodkocist.com.tr düzeninin kopyasıdır: solda kod + ad,
fiyat, adet + Sepete ekle, yeşil Hızlı Sipariş (WhatsApp) ve dört başlıklı akordeon (Teknik
Detaylar, Ürün Açıklaması, Lojistik ve Teslimat, Müşteri Görüşleri); sağda büyük galeri, küçük
resimler görselin üstünde. Sekme adları ve Lojistik metni İçerik Stüdyosu → Ürün Sayfaları'nda;
üründe "Lojistik ve Teslimat" detay satırı varsa o ürün için o metin gösterilir.

**Koçist ürün sayfası** üstte canlı kocist.com.tr düzenini izler: solda büyük galeri, sağda ad,
fiyat ve sayfanın içindeki **teklif formu** (Ad Soyad, Şirket, Telefon, E-posta, Adet / Ölçü,
onay kutusu, Gönder + WhatsApp). Galerinin altında WOOD KOCIST'teki üç başlıklı akordeon
(Teknik Detaylar, Ürün Açıklaması, Lojistik ve Teslimat); veri yuvaları iki sitede aynı havuz
alanlarından dolar, "Lojistik ve Teslimat" kuralı eklentide ortak (`nwcs_product_table_specs`,
`nwcs_product_delivery_row`). Telefonda sıra: galeri → ad/fiyat → akordeon → form; üstteki
"Teklif formuna git" bağlantısı forma kaydırır. Form gerçek gönderim yapar (Teklif Talepleri +
info@kocist.com.tr bildirimi), başarı ve hata aynı sayfaya döner. Fiyatı olmayan üründe
"Fiyat teklifle" ve **nedeni** yazar (site geneli metin; Ürün Sayfaları → WhatsApp ve Teklif'te
grup bazlı metin girilebilir). WOOD KOCIST'te de fiyatsız üründe neden satırı var
(Tüm Sayfalar → Ürün Kartları → "Fiyatı olmayan ürün: nedeni").

## Depoda ne var, ne yok

```
docker-compose.yml          WordPress + MariaDB + WP-CLI
docker/apache-wp.conf       .htaccess (mod_rewrite) izni
scripts/install.sh          Tekrarlanabilir kurulum (site listesi betiğin başında)
scripts/seed-<site>.php     Sitenin ilk içeriği (sayfalar, görseller, alan değerleri)
scripts/data/               Seed betiklerinin metin verisi (ör. blog yazıları)
scripts/seed-products.php   Ürün havuzuna örnek ürün (yalnızca geliştirme)
scripts/inventory.php       CONTENT-INVENTORY.md üreteci
build/                      Tailwind kaynakları (tema başına bir dosya)
wp-content/themes/ahsapkasa-theme/
wp-content/themes/istanbulpaletci-theme/
wp-content/themes/kereste-base/           kuzen kereste sitelerinin ana teması
wp-content/themes/ithalkeresteci-theme/   çocuk tema (renk + içerik)
wp-content/themes/kavakkeresteci-theme/   çocuk tema (renk + içerik)
wp-content/plugins/network-content-studio/
backups/                    Veritabanı yedekleri (Git'e girmez)
```

WordPress core Git'e **kopyalanmaz**; `wp_core` adlı Docker volume'unda durur.
Yüklenen medya da volume'dadır (`.gitignore`).

## ahsapkasa yeniden tasarımı (Tailwind)

`ahsapkasa-theme`, ahsapkasa.com'un (Koçist Orman Ürünleri) yeniden tasarımıdır.
Diğer iki temadan farkı, görünümünü elle yazılmış CSS yerine **Tailwind ile derlenmiş**
bir stil dosyasından almasıdır. Panele bağlanma biçimi aynıdır: kök dizinindeki
`content-manifest.php` sayesinde İçerik Stüdyosu bu siteyi de tanır, eklentide
hiçbir değişiklik gerekmez.

Tasarım kararları ve gerekçeleri: [DECISIONS.md](DECISIONS.md)

### CSS'i yeniden derleme

Şablonlarda yeni bir Tailwind sınıfı kullandığınızda CSS'i yeniden üretmek gerekir:

```bash
cd build
npm install          # yalnızca ilk seferde
npm run build        # iki temayı da derler
npm run build:ahsapkasa          # yalnızca bir tema
npm run build:istanbulpaletci
npm run watch:istanbulpaletci    # izleyerek sürekli derleme (tema başına)
```

Her temanın kendi kaynağı vardır (`build/ahsapkasa.css`, `build/istanbulpaletci.css`).
Çıktı ilgili temanın `assets/tailwind.css` dosyasına yazılır ve
depoya dahildir; yani siteyi çalıştırmak için Node kurmanız gerekmez, yalnızca
tasarımı değiştirecekseniz gerekir.

### Üst menüdeki WhatsApp düğmesi

"Teklif alın" düğmesinin yanında bir WhatsApp düğmesi vardır. Bağlantısı İçerik Stüdyosu'ndan
girilir (Tüm Sayfalar → Üst Menü → **WhatsApp Bağlantısı**), örneğin
`https://wa.me/905XXXXXXXXX`.

Bağlantı girilmediği sürece düğme görünür ama **tıklanamaz** (`aria-disabled`, klavye
sırasından çıkarılmış, soluk); kimseyi boş bir adrese götürmez. Bağlantı girilince
kendiliğinden normal bir bağlantıya döner ve yeni sekmede açılır.

### Ürün bazlı teklif düğmesi

Hizmetlerimiz sayfasındaki her kutuda kendi **"Bu ürün için teklif al"** düğmesi vardır.
Düğme iletişim sayfasına `?urun=<ürün adı>#teklif` adresiyle gider; form o ürünü kendiliğinden
seçili getirir ve doğrudan forma kaydırır. Hatalı gönderimden sonra kullanıcının kendi seçimi
adresteki değerin önüne geçer.

### Hero slaytı ve görsel büyütme

Ana sayfadaki hero, İçerik Stüdyosu'ndan yönetilen bir slayt gösterisidir (Ana Sayfa →
Giriş (Hero) → Arka Plan Slaytları). Slayt eklemek/çıkarmak için satır ekleyip görsel seçmek
yeterli; tek slayt kalırsa noktalar kendiliğinden gizlenir.

Hizmetler sayfasında her ürünün dört görsel alanı vardır. Kapak görseline tıklandığında
görsel büyür ve o ürünün diğer görselleri arasında ok tuşlarıyla veya düğmelerle gezilebilir.

Büyütülmüş görselde ayrıca **yakınlaştırma** vardır: fare tekerleği, alttaki −/+ düğmeleri,
görsele tıklama (%100 ↔ %200) ve klavyede `+` / `−` / `0`. Yakınken görsel sürüklenerek
gezilir. Görsel değiştirildiğinde veya pencere kapandığında yakınlaştırma sıfırlanır.

### Teklif formu

İletişim sayfasındaki form sahte bir başarı ekranı göstermez. Gönderim
`admin-post.php` üzerinden doğrulanır (nonce, zorunlu alanlar, bot tuzağı) ve
`Teklif İstekleri` kayıt türüne **özel** olarak yazılır. Gelen istekleri
`http://localhost:8080/ahsapkasa/wp-admin/edit.php?post_type=ak_quote`
adresinden görebilirsiniz. Demoda e-posta gönderimi yoktur; kayıt WordPress
içinde tutulur.

## istanbulpaletci yeniden tasarımı (Tailwind)

`istanbulpaletci-theme`, istanbulpaletci.com'un (yine Koçist Orman Ürünleri) yeniden
tasarımıdır. Canlı sitedeki 10 sayfa korunmuştur: Anasayfa, Hakkımızda, Ürünlerimiz,
beş ürün sayfası (`/urunlerimiz/ahsap-palet/` …), Blog ve İletişim. Eklentide hiçbir
değişiklik gerekmedi; İçerik Stüdyosu siteyi "Koçist · istanbulpaletci.com" adıyla,
11 düzenlenebilir sayfa ve 157 alanla kendiliğinden tanır.

Sıfırdan kurulum (ağ kuruluyken):

```bash
docker compose run --rm wpcli site create --slug=istanbulpaletci --title="İstanbul Paletçi"
docker compose run --rm wpcli theme enable istanbulpaletci-theme --network
docker compose run --rm wpcli theme activate istanbulpaletci-theme --url=http://localhost:8080/istanbulpaletci/
docker compose run --rm wpcli language core install tr_TR
docker compose run --rm wpcli site switch-language tr_TR --url=http://localhost:8080/istanbulpaletci/
docker compose run --rm wpcli eval-file /scripts/seed-istanbulpaletci.php --url=http://localhost:8080/istanbulpaletci/
```

Kurulum betiği tekrar çalıştırılabilir; sayfa, görsel ve yazıları ikinci kez
oluşturmaz, ama alan değerlerini manifest varsayılanlarına **geri yazar**. Site sahibi
panelden içerik girdikten sonra çalıştırmayın.

- **Ürün sayfaları tek şablondur.** Manifestte `'template' => 'product'` işaretli
  sayfalar ürün sayılır; üst menünün açılır paneli, ana sayfa ızgarası, Ürünlerimiz
  listesi, alt bilgi ve form seçenekleri bu listeden beslenir.
- **Blog yazıları** WordPress'in kendi yazılarıdır (site yönetiminde Yazılar). Panel
  yalnızca blog sayfasının başlığını ve düğme metinlerini yönetir.
- **Teklif formu** ana sayfada ve İletişim'de aynıdır; gönderimler
  `http://localhost:8080/istanbulpaletci/wp-admin/edit.php?post_type=ip_quote`
  altında görülür. Ad, ölçü ve e-posta **ya da** telefon zorunludur.
- **Görseller:** ana sayfa slaytları kolaj olduğu için (tek görselde 3–8 fotoğraf)
  kurulum betiği içlerinden tek fotoğraf kırpar. Tema demosundan kalan stok görseller
  ve ahsapkasa ile aynı manzara fotoğrafları kullanılmaz.

## Kuzen kereste siteleri (ithalkeresteci, kavakkeresteci)

İki alan adı bugün yalnızca kocist.com.tr'yi çerçeve içinde gösteriyor. Yeniden
tasarımda her biri **kendi adının karşılığı olan odaklı bir kereste sitesi** oluyor;
ikisi aynı tasarımı paylaşan kuzenler:

```
wp-content/themes/kereste-base/          ana tema: şablonlar, form, stil
wp-content/themes/ithalkeresteci-theme/  çocuk tema: renk (style.css) + içerik (manifest)
wp-content/themes/kavakkeresteci-theme/  çocuk tema: renk (style.css) + içerik (manifest)
```

- Çocuk temanın `style.css` dosyası yalnızca renk değişkenleri tanımlar ("parti
  boyası"): ithal petrol; kavak Koçist logosunun yeşili, kahve şablon
  harfler, koyu ahşap kahvesi alt bilgi. Tanımlanabilen değişkenler: `--site-mark`,
  `--site-mark-deep`, `--site-mark-wash`, `--site-stamp`, `--site-ink`, `--site-stone`.
- Çocuk temanın `content-manifest.php` dosyası ana temanın kurucusunu çağırır ve
  siteye özgü metinleri, dört ürünü ve ana sayfada hangi modüllerin görüneceğini verir.
  Ana sayfa modülleri: ithal → toptan tedarik bandı; kavak → kullanım alanları.
  Yalnızca listedeki modüllerin alanları panelde görünür.
- Üst menüde ve alt bilgide **Koçist logosu** (grup logosu alanı) ve yeşil
  **WhatsApp** düğmesi (üst menü, mobil menü, ürün sayfası, iletişim).
- Seed betikleri kardeş site bağlantılarını (kuzen köprüsü, alt bilgi) yerel ağda
  buradaki kopyalara çevirir; canlıda alan adları manifestteki haliyle kalır.
- Ana sayfanın hero'su tam genişlik paket ucu fotoğrafı ve ipe asılı **paket etiketi**
  (sitenin rozeti ve dört ürünü, her satır ürün sayfasına gider). Hero fotoğrafı yapay
  zekayla üretilmiş örnek görsel; köşesinde "Örnek görsel" notu var, gerçek fotoğraf
  yüklenince not panelden silinir. "Teklif al" her sayfadaki teklif penceresini açar. Teklif istekleri site
  yönetiminde **Teklif İstekleri** altında (`kr_quote`).
- Sayfalar: Anasayfa, Ürünler, 4 ürün, Sık sorulan sorular, Hakkımızda, İletişim.
  Kaynak sitenin 604 adresi yerine 9.
- Fotoğraflar firmanın kendi sitesinden (`resources/kereste/`); ortalarında firmanın
  filigranı var, filigransız asılları gelince panelden değiştirilir.

Kurulum: `install.sh` içindeki `SITES` listesinde; tek başına
`wp eval-file /scripts/seed-ithalkeresteci.php --url=http://localhost:8080/ithalkeresteci/`
(kavak için `seed-kavakkeresteci.php` ve `/kavakkeresteci/`).
Ana tema ayrıca `build/kereste.css` kaynağından derlenir (`npm run build:kereste`).
Ahşap Ambalaj'ın Tailwind kaynağı tema içindedir; `npm run build:ahsapambalaj` derler.

## SEO ve GEO

**Ağ Yönetimi → Geliştirici → SEO ve GEO**
http://localhost:8080/wp-admin/network/admin.php?page=nwcs-seo

Ağdaki bütün sitelerin arama motoru (SEO) ve yapay zekâ araması (GEO) ayarları tek
sekmede. Temalar bu konuda hiçbir şey yapmaz; eklenti manifesti olan her siteye
kendiliğinden uygular.

**Ağ özeti** her sitenin durumunu (elle iyileştirilen sayfa sayısı, firma bilgisi
eksikleri, arama motorlarına açık/kapalı) ve **siteler arası tutarlılığı** gösterir:
aynı firmanın sitelerinde resmî unvan, telefon ve adres birebir aynı yazılmalı.

**Site görünümü**: her sayfanın Google sonucundaki hâli (önizleme), altında açılan
düzenleyici (arama başlığı, açıklama, paylaşım görseli; karakter sayacıyla) ve sağda
sitenin firma bilgisi. Aynı "Arama ve Paylaşım" alanları İçerik Stüdyosu'nda her
sayfanın son bölümü olarak da durur.

**Boş bırakılan her şey otomatik dolar**: başlık sayfanın kendi başlığından, açıklama
öne çıkan cümlesinden, görsel sayfanın ana görselinden gelir. Hiçbir şey girilmese de
her sayfa başlık, açıklama ve paylaşım görseliyle yayına çıkar.

Kendiliğinden üretilenler:

| Ne | Nerede |
| --- | --- |
| Belge başlığı (60 karakteri aşarsa site adı eklenmez), meta açıklama | her sayfa |
| Tek `canonical` adres (WordPress'in yazmadığı ana sayfa, blog listesi, arşivler dahil) | her sayfa |
| Paylaşım önizlemesi (Open Graph, X); görseli olmayan sayfada site görseli | her sayfa |
| Yapılandırılmış veri (JSON-LD): firma, web sitesi, sayfa, konum yolu | her sayfa |
| Grup ilişkisi (`parentOrganization`): kardeş siteler aynı grubun parçası | firma bilgisinde grup girilmişse |
| Ürün verisi, şartname satırları dahil (fiyat/puan uydurulmaz) | `seo_source.type = Product` sayfalar |
| Soru-cevap verisi (`FAQPage`) | `seo_source.type = FAQPage` sayfalar |
| Blog yazısı verisi | yazılar |
| `/llms.txt`: yapay zekâ için sitenin özeti, ürün ölçüleri ve sık sorulanlar dahil | her site |
| Arşivlerde (yazar, tarih, arama, kategori, etiket) `noindex` | her site |
| Site haritasından kullanıcı listesi ve kategori/etiket arşivlerinin çıkarılması | her site |

Tema tarafında isteğe bağlı iki ipucu (`content-manifest.php`):

```php
'seo_site_defaults' => array( 'name' => '…', 'phone' => '…', … ), // firma bilgisinin ilk değerleri
// sayfa başına:
'seo_source' => array(
	'title'       => 'card.name',     // otomatik başlık hangi alandan
	'description' => 'detail.lead',
	'image'       => 'card.image',
	'type'        => 'Product',       // ürün verisi üret
	'properties'  => 'specs.rows',    // şartname satırları (label/value)
),
// soru-cevap sayfası:
'seo_source' => array( 'type' => 'FAQPage', 'questions' => 'items.rows' ), // question/answer satırları
```

Manifestte olmayan ama temanın çizdiği sayfalar (tek şablonla çizilen ürün alt
sayfaları gibi) ve görselleri tema klasöründe tutan temalar için süzgeçler:

```php
add_filter( 'nwcs_seo_extra_pages', fn( $pages ) => … );   // url, name, description, image, type, properties
add_filter( 'nwcs_seo_default_image', fn() => nwcs_seo_theme_file_image( 'assets/img/hero.jpg', 'Açıklama' ) );
add_filter( 'nwcs_seo_default_logo', fn() => nwcs_seo_theme_file_image( 'assets/img/logo.png', 'Firma' ) );
```

**Kardeş siteler `sameAs` değildir.** `sameAs` "aynı kurum" demektir; firma bilgisindeki
"Firmanın diğer adresleri" yalnızca o firmanın kendi hesapları (sosyal medya, Google
İşletme) içindir. Siteler arasındaki bağ "Bağlı olduğu grup" alanıyla kurulur.

`seo` bileşen adı ve `site_seo` sayfa adı eklentiye ayrılmıştır; temalar kullanmaz.
Kararlar ve kontrol listesi: [DECISIONS.md](DECISIONS.md) → "SEO ve GEO — kalıcı ilke".

### Sık sorulan sorular (`/sss/`)

Kereste siteleri dışındaki yedi sitede (Koçist, İstanbul Paletçi, İstanbul Keresteci, Palet
Çivi, Sanayi Palet, Ahşap Ambalaj, Ahşap Kasa) aynı model: manifestte `faq` sayfası
(`head` + `items.rows` [grup, soru, cevap] + `more`), adres `/sss/`, şablon `page-sss.php`
(Koçist'te `page.php` → `sections/faq.php`). Temalar satırları eklentinin
`nwcs_faq_rows()` / `nwcs_faq_groups()` yardımcısından okur: **cevabı boş soru sitede,
JSON-LD'de ve llms.txt'te görünmez**; firma cevabı gelince panelden doldurulur. Hiç cevaplı
soru yoksa `FAQPage` işareti de yazılmaz. Sayfa, yönetici panele ilk girdiğinde temanın kurulum
sürümüyle kendiliğinden açılır (canlıda WP-CLI gerekmez); açılana kadar menüdeki SSS satırı
gizlidir. Deploy sonrası önce yöneticiyle panele girin, sonra önbelleği temizleyin. Ahşap Ambalaj
ve Ahşap Kasa'da menüdeki SSS bağlantısı ayrıca yalnızca cevaplı soru varken görünür. Panelde kayıtlı menüsü olan sitede (yerelde
İstanbul Paletçi, Ahşap Kasa) yeni "SSS" satırı panelden eklenir. Firmadan beklenen cevaplar:
DEVAM.md.

### Uyumluluk puanı

SEO ve GEO sekmesi her site için 0–100 puan gösterir (SEO ve GEO alt puanlarıyla).
Ağ özetinde sütun, site görünümünde kontrol listesi: eksik olan, kaç puan kaybettirdiği
ve nasıl düzeltileceği. 85 ve üstü "Güçlü", 60–84 "Orta", altı "Zayıf".

## Natro'ya taşıma paketi

```bash
DOMAIN=panel.kocist.com.tr ADMIN_EMAIL=ad@firma.com sh scripts/export-natro.sh
```

Yerel veritabanına dokunmaz; geçici bir kopya üzerinde adresleri `https://DOMAIN`'e
çevirir (serileştirilmiş veri korunur, yazıların `guid` sütunu bilerek değişmez), 8
sitenin hepsini **arama motorlarına kapatır** (deneme), MCP uygulama parolalarını siler
ve eklentisini kapatır, yönetici parolasını yeniler (ekrana yazılmaz; paketteki
`YONETICI-PAROLASI.txt`). Çıktı `dist/natro-<tarih>/` (Git'e girmez): `natro.sql`,
`wp-content.tar.gz` (temalar ve eklenti Git'teki sürümden), `wp-config-ek.php`,
`htaccess.txt`, `KURULUM.md` (cPanel adımları) ve dokunulmamış `yerel-yedek.sql`.

Deneme bitince site site: alan adı bağlanır, DNS çevrilir, eski adres yönlendirmeleri
test edilir, en son o sitenin "arama motorlarına açık" ayarı açılır.

### Canlıya çıkan siteler (domain mapping)

```bash
DOMAIN=panel.sanayipalet.com SCHEME=http MAP="istanbul-keresteci=istanbulkeresteci.com istanbulpaletci=istanbulpaletci.com" sh scripts/export-natro.sh
```

`MAP`'teki siteler pakette kendi alan adına bağlanır: adresleri (diğer sitelerden verilen
bağlantılar dahil) `https://alanadi` olur, arama motorlarına açılır; diğerleri panel altında
kapalı kalır. Alan adının SSL sertifikası sunucuda kurulu olmalı (panelin sertifikası
bağlanan alan adlarını kapsamaz). Geçiş, alan adının belge kökünün WordPress klasörüne
çevrilmesiyle olur; geri dönüş eski klasör yolunu geri yazmaktır.

### Kod güncellemesi: cPanel Git Version Control

Kök dizindeki `.cpanel.yml` yalnızca 9 temayı ve eklentiyi WordPress'in `wp-content`
klasörüne kopyalar (`rsync --delete`, yalnızca bu klasörlerde). İçerik (metin, görsel,
ayarlar) veritabanındadır ve panelden yönetilir; Git ile taşınmaz.

1. cPanel → Git Version Control → Create: "Clone a Repository" açık, adres
   `https://github.com/bugraberatkok/demo-wordpress-multisite.git`, depo yolu
   `/home/u7198936/repositories/demo-wordpress-multisite` (**web'e açık bir klasör değil**).
2. Güncellemede: Manage → Pull or Deploy → **Update from Remote**, sonra **Deploy HEAD Commit**.
   Dağıtım elle tetiklenir; main'e gelen her commit kendiliğinden canlıya çıkmaz.

## Eski adres yönlendirmeleri (301)

**Ağ Yönetimi → Geliştirici → Yönlendirmeler.** Her sitenin eski adres listesi: eski
sitenin Google'daki ve başka sitelerdeki adresleri yeni sayfalara taşınır (301) ya da
kaldırıldığı bildirilir (410). Kural yalnızca adres sitede bulunamadığında çalışır;
var olan sayfayı etkilemez. Yollar sitenin köküne göredir, alan adı değişince de geçerli.

```
/urunler/ahsap-palet/    /urunler/#paletler     301
/tag/*                   /blog/                 önekle eşleşen bütün adresler
/portfolio-item/*        410                    eski temanın demo sayfaları
```

İlk listeler canlı alan adları taranarak çıkarıldı (`scripts/data/redirects.php`) ve
`wp eval-file /scripts/load-redirects.php` ile yüklenir (`--dry-run` önce gösterir).
**Bir alan adının DNS'i yeni sunucuya çevrilmeden önce o sitenin listesi hazır
olmalı.** Koçist (kocist.com.tr, ~600 eski adres) listesi kendi geçişinden önce eklenecek.

## Formlar ve e-posta

Bütün sitelerin teklif/iletişim formları gönderimi WordPress'e kaydeder (site
yönetiminde "Teklif İstekleri" ya da "İletişim Mesajları"). Eklenti her yeni kayıtta
sitenin firma e-postasına (SEO ve GEO → Firma bilgisi → E-posta; boşsa yönetici
e-postası) bildirim gönderir; müşterinin e-postası "Yanıtla" adresindedir. Alıcı
`nwcs_form_recipient` süzgeciyle değiştirilebilir.

Yerelde posta sunucusu olmadığı için e-posta gitmez (günlüğe "E-posta gonderilemedi"
yazılır; form yine çalışır). Canlıda (Natro cPanel):

1. Her alan adı için bir posta kutusu açın (ör. `form@alanadi` ya da `info@alanadi`).
2. WP Mail SMTP (ya da FluentSMTP) kurun; SMTP ile bu kutudan gönderin (465 SSL /
   587 TLS). PHP `mail()`'e bırakmayın: `wordpress@alanadi` göndericisi çoğu zaman
   spama düşer.
3. cPanel → Email Deliverability: her alan adında SPF ve DKIM'i onarın, DMARC kaydı
   ekleyin (başta `p=none`). DNS Natro'da değilse kayıtları DNS sağlayıcısına girin.
4. Alıcı adreslerini firma ile teyit edin (SEO sekmesindeki e-postalar).
5. Canlıda her siteden bir deneme formu gönderin.

Dikkat: sayfa önbelleği (LiteSpeed vb.) açılırsa form içeren sayfalar (iletişim,
kereste sitelerinde her sayfa, istanbulpaletci ana sayfa) önbellek dışında tutulmalı;
yoksa formun güvenlik belirteci süresi dolar.

## Yeni site ekleme

Tema klasörünü yüklemek **tek başına yetmez**: tema, sitenin tasarımı ve düzenlenebilir
alanlarının listesidir (`content-manifest.php`); sitenin kendisi ayrıca açılır.

1. Tema klasörünü `wp-content/themes/` altına koyun (hostingde FTP/cPanel ile;
   yerelde ayrıca `docker-compose.yml`'e bir bağlama satırı).
2. Ağ Yönetimi → Siteler → **Yeni site**: adres ve başlık.
3. Ağ Yönetimi → Temalar → temayı **Ağda etkinleştir**; sitenin Görünüm menüsünden
   temayı etkinleştirin.
4. Manifestteki sayfa adresleri için WordPress sayfalarını açın (ör. `/hakkimizda/`).
5. İçerik Stüdyosu ve SEO ve GEO sekmesi siteyi kendiliğinden tanır; içerik oradan girilir.

Yerelde bu adımların hepsini `scripts/install.sh` içindeki `SITES` listesine bir satır
ve bir `seed-<site>.php` ekleyerek yaparsınız.

**Silme**: önce Ağ Yönetimi → Siteler'den siteyi silin, sonra tema klasörünü kaldırın.
Tersi sırada yapılırsa site temasız kalır ve boş sayfa gösterir.

## Mimari özet

- **Alan manifesti**: her tema kökünde `content-manifest.php` — sayfa → bileşen → alan
  (Türkçe etiket + tür). Saf PHP dizisi döndürür, WordPress'e bağımlı değildir; böylece
  ağ paneli `switch_to_blog()` ile tema kodu yüklemeye güvenmeden, dosya sisteminden
  okuyabilir.
- **Veri katmanı**: değerler ilgili alt sitenin kendi `nwcs_content` option kaydında
  tutulur. Temalarda gömülü metin yoktur; her şey bu kayıttan okunur ve sunucu yeniden
  başlatılınca da korunur.
- **Medya**: örnek görseller gerçek WordPress medya kayıtlarıdır (alt metin dahil),
  site bazında `uploads/sites/<id>/` altında durur.
- **İkonlar**: eklenti içinde sabit, 16 ikonluk güvenli liste. Panelden serbest
  SVG/HTML girilemez.
- **Bölüm sırası**: ana sayfa bölümleri `nwcs_section_order` kaydında tutulur; manifestte
  "sıralanabilir" işaretli bölümler dışına çıkılamaz. Sıralama arayüzü MVP 3'te gelecek.

Düzenlenebilir alanların tam listesi: [CONTENT-INVENTORY.md](CONTENT-INVENTORY.md)
Teknik kararlar ve gerekçeleri: [DECISIONS.md](DECISIONS.md)

## Durum

- **MVP 1 — tamamlandı**: Multisite ağı, iki bağımsız tema, demo içerik, örnek alt sayfalar.
- **MVP 2 — tamamlandı**: Network Admin içinde İçerik Stüdyosu; sağda dikey site seçici,
  sayfa/bileşen listesi, tüm metin/görsel/ikon/bağlantı alanlarının düzenlenmesi, satır
  ekle-sil-sırala, hemen yayınla + vazgeç.
- **MVP 3 — tamamlandı**: panel ortasında canlı önizleme ve tıkla-düzenle, ana sayfa
  bölümlerini ↑↓ ile sıralama, Abilities API + resmî MCP Adapter ile Claude Code
  bağlantısı (dört yetenek, en az yetkili).
- **MVP 5 — tamamlandı**: kategori listesi, çoklu ürün görseli, Medya Havuzu sayfası,
  önizlemede sayfa algısı düzeltmesi, katlanan yönetim menüsü, gözü yormayan arayüz;
  ayrıca CSV içe/dışa aktarma, arama-filtre-sayfalama, toplu işlemler ve önbellek.
- **MVP 4 — tamamlandı**: merkezî ürün havuzu (Ağ Yönetimi'nde kendi sayfası, WordPress
  medya kitaplığı, hepsi/seçilenler kipleri, site bazlı istisnalar, boş fiyatta
  "Teklif al", örnek ürün detay sayfası) ve panel arayüzünün elden geçirilmesi
  (site seçici üst şeritte, büyük canlı önizleme, renklendirilmiş düzen,
  katlanabilir ikon seçici).

Bu demonun kapsamı dışında bırakılanlar (canlıya geçişte ayrı iş): taslak/revizyon akışı,
gerçek e-posta teslimi ve spam koruması, alan adı eşlemesi, e-ticaret.

- **ahsapkasa yeniden tasarımı — tamamlandı**: ahsapkasa.com'un dört sayfalık yeniden
  tasarımı (ana sayfa, hakkımızda, hizmetlerimiz, iletişim), Tailwind ile derlenen
  stil, animasyonlu üst menü, dörtlü ürün karesi ve ortasındaki tek teklif düğmesi,
  çalışan teklif formu. Ağdaki üçüncü site olarak İçerik Stüdyosu'ndan yönetilir.
- **istanbulpaletci yeniden tasarımı — tamamlandı**: istanbulpaletci.com'un 10 sayfası
  (ana sayfa, hakkımızda, ürünlerimiz, beş ürün sayfası, blog, iletişim), şartname
  tabloları, görselli açılır ürün menüsü, çalışan teklif formu, Türkçe dil paketi.
- **Demo sitelerin kaldırılması**: `/kocist/` ve `/paletci/` siteleri, temaları ve demo
  seed betikleri kaldırıldı; kurulum betiği gerçek siteleri kurar. Öncesinin yedeği
  `backups/` altında.
- **SEO ve GEO — tamamlandı**: ağ yönetiminde yeni sekme; bütün sitelere otomatik meta,
  paylaşım önizlemesi, yapılandırılmış veri, `llms.txt`; siteler arası firma bilgisi
  tutarlılığı denetimi.
- **ithalkeresteci yeniden tasarımı — tamamlandı**: iframe'le kocist.com.tr'yi gösteren alan
  adı için odaklı kereste sitesi; ortak ana tema (`kereste-base`) + çocuk tema, paket
  etiketi ürün kartları, teklif penceresi. m³ hesaplayıcısı sonradan kaldırıldı; hero
  yeniden tasarlandı (DECISIONS.md).
- **kavakkeresteci yeniden tasarımı — tamamlandı**: aynı ana tema, yeşil-kahve parti
  boyası; kavak kereste, çıta, ahşap takoz, OSB levha; ana sayfada "kullanım alanları".
  İki siteye Koçist logosu ve WhatsApp düğmesi eklendi. Ürün listesi, ölçüler, adres ve
  fotoğraflar satıcının geri bildirimini bekliyor.
