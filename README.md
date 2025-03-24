# Google İşletme Sorgulama

<p align="center">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3">
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5">
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript">
  <img src="https://img.shields.io/badge/license-MIT-green?style=for-the-badge" alt="License">
</p>

<p align="center">
  <img src="https://netcloud.com.tr/resources/uploads/logo/2023-10-27/wisecp-turkiye-nin-dijital-hizmetler-otomasyonu.png" alt="Google İşletme Sorgulama" width="300">
</p>

Bu uygulama, **Netcloud Information Technologies LTD.** tarafından ücretsiz olarak geliştirilmiş, Google'dan işletme bilgilerini toplamak için kullanılan bir araçtır. Anahtar kelime ve konum belirterek Google'da yerel işletme aramalarını otomatik olarak yapabilir, sonuçları Excel uyumlu CSV dosyalarına aktarabilirsiniz.

## 🌟 Özellikler

- 🔍 Google'da yerel işletme sorgulama
- 📋 İşletme ismi, adres, telefon, açılış saatleri, website, derecelendirme ve yorum sayısı verilerini toplama
- 📊 CSV ve Excel uyumlu formatlarda dışa aktarma
- 🔄 Proxy rotasyonu ile IP engellenmesini önleme
- 🛠️ Türkçe karakter desteği
- 📱 Mobil uyumlu modern arayüz
- ⚡ Hızlı veri toplama ve işleme

## 📋 Gereksinimler

- PHP 7.4 veya daha yüksek sürüm
- cURL PHP eklentisi
- DOM PHP eklentisi
- Çalışan bir web sunucusu (Apache, Nginx vb.)

## 🚀 Kurulum

1. Bu 2 dosyayı indirin:

2. Dosyaları web sunucunuzun kök dizinine veya bir alt dizine kopyalayın.

3. Proxy ayarlarını kendi proxy bilgilerinizle güncelleyin:
```php
$proxyList = [
    "proxy_ip1:port:kullanici:sifre",
    "proxy_ip2:port:kullanici:sifre",
    "proxy_ip3:port:kullanici:sifre",
    // Daha fazla proxy ekleyebilirsiniz
];
```

4. Tarayıcınızdan uygulamayı açın ve kullanmaya başlayın.

## 📊 Kullanım

1. **Anahtar Kelime** alanına arama yapmak istediğiniz işletme tipini girin (örn: "süt ürünleri", "restoran", "berber").

2. **Konum** alanına işletmelerin bulunmasını istediğiniz yeri girin (örn: "Mersin", "Ankara", "İstanbul/Kadıköy").

3. **Sonuç Sayfası Sayısı** alanından kaç sayfa sonuç toplamak istediğinizi seçin (her sayfa yaklaşık 20 sonuç içerir).

4. **Sorgulama Başlat** butonuna tıklayın ve sonuçların toplanmasını bekleyin.

5. İşlem tamamlandığında, sonuçları CSV veya Excel uyumlu CSV formatında indirebilirsiniz.

## 📁 CSV Dosyasını Excel'de Açma

Excel uyumlu CSV dosyasını açmak için:

1. Excel'i açın ve yeni boş bir çalışma kitabı oluşturun.
2. **Veri** sekmesine tıklayın.
3. **Metinden/CSV'den Al** (veya **Dış Veri Al**) seçeneğine tıklayın.
4. İndirdiğiniz CSV dosyasını seçin.
5. Açılan sihirbazda **Sınırlayıcı** olarak **Sekme** seçin.
6. **UTF-8** kodlamasını seçin.
7. **Yükle** düğmesine tıklayın.

## 🔧 Kod Yapısı

Proje iki ana dosyadan oluşur:

1. **index.php** - Web arayüzü ve kullanıcı etkileşimi
2. **GoogleScraper.php** - Google'dan veri çekme ve işleme mantığı

### Önemli Sınıflar ve Yöntemler

- `GoogleScraper` sınıfı - Tüm web kazıma işlemlerini yönetir
  - `searchBusinesses()` - Google aramasını gerçekleştirir ve sonuçları toplar
  - `saveToCSV()` - Sonuçları standart CSV formatında kaydeder
  - `saveToExcelCSV()` - Sonuçları Excel uyumlu CSV formatında kaydeder
  - `cleanResults()` - Ham verileri temizler ve düzenler

## ⚠️ Yasal Uyarılar

Bu uygulama eğitim ve bilgi amaçlı geliştirilmiştir. Kullanım şunları içerir:

1. Google'ın Hizmet Şartları'na aykırı olabilecek otomatik veri toplama.
2. Google tarafından IP adresinizin engellenme riski.
3. Toplanan verilerin kullanımı konusunda yasal kısıtlamalar olabilir.

Bu uygulamayı kullanmadan önce yerel yasaları ve Google'ın Hizmet Şartları'nı göz önünde bulundurun. Netcloud Information Technologies LTD., uygulamanın kullanımından doğabilecek herhangi bir sonuçtan sorumlu değildir.

## 🛠️ Özelleştirme

### Proxy Yönetimi

Daha iyi sonuçlar için, birden fazla ve güvenilir proxy kullanmanız önerilir. Proxy listesini `index.php` dosyasında güncelleyebilirsiniz:

```php
$proxyList = [
    "ip1:port:kullanici:sifre",
    "ip2:port:kullanici:sifre",
    // ...
];
```

### İstek Sınırlaması 

IP engellenmesini önlemek için istek sınırlama parametrelerini `GoogleScraper.php` dosyasında ayarlayabilirsiniz:

```php
private $retryCount = 3;    // Başarısız istekler için yeniden deneme sayısı
private $retryDelay = 60;   // Yeniden denemeler arasındaki bekleme süresi (saniye)
private $minRequestDelay = 5; // İstekler arasındaki minimum bekleme süresi (saniye)
```

### Uygulamadan Görüntüler
<img src="https://i.imgur.com/jJiVH1S.png" alt="Netcloud Information Technologies" width="500">
<img src="https://i.imgur.com/gaHg5SF.png" alt="Netcloud Information Technologies" width="500">

## 📜 Lisans

Bu proje MIT lisansı altında dağıtılmaktadır. Daha fazla bilgi için `LICENSE` dosyasına bakın.

## 🤝 Katkıda Bulunma

Katkılarınızı memnuniyetle karşılıyoruz. Lütfen bir pull request göndermeden önce değişikliklerinizi tartışmak için bir issue açın.

## 🙏 Teşekkürler

Bu proje, Netcloud Information Technologies LTD. tarafından topluluk için ücretsiz olarak geliştirilmiştir. Teşekkürler!

---

<p align="center">
  <a href="https://netcloud.com.tr" target="_blank">
    <img src="https://netcloud.com.tr/resources/uploads/logo/2023-10-27/wisecp-turkiye-nin-dijital-hizmetler-otomasyonu.png" alt="Netcloud Information Technologies" width="200">
  </a>
</p>
<p align="center">
  © 2025 Netcloud Information Technologies LTD. Tüm hakları saklıdır.
</p>
