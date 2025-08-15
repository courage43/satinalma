# 📖 Satın Alma Talep Sistemi - Kullanım Kılavuzu

## 🏠 Sisteme Giriş

### İlk Giriş
1. Web tarayıcınızda `https://satinalma.kutahyam.tr` adresine gidin
2. **Varsayılan admin hesabı:**
   - **Kullanıcı Adı:** `admin`
   - **Şifre:** `123456`
3. ⚠️ **İlk girişten sonra mutlaka şifrenizi değiştirin!**

### Ana Sayfa Özellikleri
- **Hoş geldin mesajı** ve kullanıcı bilgileri
- **İstatistik kartları** (talep sayıları, durumlar)
- **Hızlı işlemler** menüsü
- **Son talepler** listesi

## 👥 Kullanıcı Rolleri ve Yetkileri

### 🔧 Sistem Yöneticisi
**Yetkiler:**
- ✅ Tüm sistem ayarları
- ✅ Kullanıcı yönetimi
- ✅ N8N workflow kurulumu
- ✅ Sistem logları ve raporları
- ✅ Tüm talepleri görüntüleme

**Menüler:**
- `Kullanıcı Yönetimi` - Yeni kullanıcı ekleme/düzenleme
- `Sistem Ayarları` - SMTP, Telegram, iş kuralları
- `N8N Kurulumu` - Workflow şablonları indirme

### 👤 Normal Kullanıcı
**Yetkiler:**
- ✅ Yeni talep oluşturma
- ✅ Kendi taleplerini görüntüleme
- ✅ Talep durumu takibi

**Menüler:**
- `Yeni Talep Oluştur` - Satın alma talebi formu
- `Taleplerim` - Kendi talep listesi

### 🏢 Satın Alma Sorumlusu (SAS)
**Yetkiler:**
- ✅ Gelen talepleri onaylama/reddetme
- ✅ Tüm talepleri görüntüleme
- ✅ Teklif süreçlerini yönetme

**Menüler:**
- `Onay Bekleyenler` - İlk onay gereken talepler
- `Taleplerim` - Tüm talepler listesi

### 🎯 Genel Sekreter (GS)
**Yetkiler:**
- ✅ SAS'tan gelen talepleri onaylama/reddetme
- ✅ Tüm talepleri görüntüleme
- ✅ Üst yönetim onayına gönderme

**Menüler:**
- `Onay Bekleyenler` - İkinci onay gereken talepler
- `Taleplerim` - Tüm talepler listesi

## 📝 Yeni Talep Oluşturma

### Adım Adım Talep Süreci

#### 1. Talep Formu
- `Dashboard > Yeni Talep Oluştur` butonuna tıklayın
- **Zorunlu Alanlar:**
  - `Talep Başlığı` - Kısa açıklayıcı başlık
  - `Detaylı Açıklama` - Talep edilen malzeme/hizmet detayları
  - `Gerekçe` - Neden bu talep gerekli?
  - `Kategori` - Talep türü seçin
  - `Aciliyet Seviyesi` - Düşük, Orta, Yüksek, Acil

#### 2. Kalem Detayları
- `Kalem Ekle` ile yeni satır ekleyin
- **Her kalem için:**
  - `Kalem Adı` - Tam ürün/hizmet adı
  - `Açıklama` - Teknik özellikler, marka tercihi
  - `Miktar` - Sayısal değer
  - `Birim` - Adet, Kg, Lt, vb.
  - `Tahmini Birim Fiyat` - TL cinsinden

#### 3. Talep Gönderimi
- Tüm bilgileri kontrol edin
- `Talebi Gönder` butonuna tıklayın
- Sistem otomatik talep numarası verir (örn: ST-2025-000001)

### 📋 Talep Durumları

| Durum | Açıklama | Ne Yapmalı? |
|-------|----------|-------------|
| 🔄 **SAS İncelemede** | Satın alma sorumlusu inceliyor | Bekleyin |
| 🔍 **GS İncelemede** | Genel sekreter inceliyor | Bekleyin |
| 📋 **Teklif Toplama** | Tedarikçilerden teklif toplanıyor | Süreç takibi yapın |
| ⚖️ **SAK Karарı Bekliyor** | Komisyon kararı bekleniyor | Komis yon toplantısını bekleyin |
| ✅ **Onaylandı** | Talep onaylandı, sipariş aşamasında | - |
| ❌ **Reddedildi** | Talep reddedildi | Ret gerekçesini kontrol edin |

## 🔍 Talep Takibi

### Taleplerim Sayfası
- `Dashboard > Taleplerim` menüsünden erişin
- **Filtreleme Seçenekleri:**
  - Durum bazlı filtreleme
  - Tarih aralığı seçimi
  - Talep numarası ile arama

### Talep Detayı Görüntüleme
- Talep listesinde `👁️ Görüntüle` butonuna tıklayın
- **Görebileceğiniz Bilgiler:**
  - Tam talep bilgileri
  - Kalem detayları ve tutarlar
  - Talep eden kişi bilgileri
  - İşlem geçmişi (timeline)
  - Onay/red durumları

## ✅ Onay Süreçleri

### SAS/GS Onay İşlemleri

#### E-posta ile Onay
1. Sisteme yeni talep geldiğinde e-posta alırsınız
2. E-postadaki `ONAYLA` veya `REDDET` linkine tıklayın
3. Açılan sayfada kararınızı onaylayın
4. İsteğe bağlı not ekleyebilirsiniz

#### Web Arayüzü ile Onay
1. `Dashboard > Onay Bekleyenler` menüsüne gidin
2. İlgili talebin `✓ Onayla` butonuna tıklayın
3. Onay formunu doldurun:
   - Karar: Onayla/Reddet
   - Not: İsteğe bağlı açıklama
4. `Kararı Gönder` butonuna tıklayın

#### Toplu İşlemler
- Birden fazla talep seçebilirsiniz
- `Seçili Talepleri Onayla` ile toplu onay
- Her talep için ayrı not ekleyebilirsiniz

## 🤖 N8N Workflow Entegrasyonu

### Otomatik Bildirimler
Sistem aşağıdaki durumlarda otomatik bildirim gönderir:

#### E-posta Bildirimleri
- ✉️ Yeni talep oluşturulduğunda (SAS'a)
- ✉️ Talep onaylandığında (talep edene)
- ✉️ Talep reddedildiğinde (talep edene)
- ✉️ Acil talep oluşturulduğunda (tüm yöneticilere)

#### Telegram Bildirimleri
- 📱 Yeni talep bildirimi
- 📱 Onay/red kararları
- 📱 Kritik süreç güncellemeleri
- 📱 Sistem uyarıları

### Workflow Türleri

#### 1. Onay Süreci Workflow
- Otomatik onaylayıcı belirleme
- Aciliyet kontrolü
- Zaman aşımı takibi
- Hatırlatma mesajları

#### 2. Teklif Toplama Workflow
- Tedarikçi listesi yönetimi
- Otomatik RFQ gönderimi
- Son tarih hatırlatmaları
- Teklif karşılaştırma raporları

#### 3. Komisyon Workflow
- Toplantı planlaması
- Üye davetleri
- Karar bildirimleri
- Tutanak takibi

## ⚙️ Admin Panel İşlemleri

### Kullanıcı Yönetimi
1. `Admin > Kullanıcı Yönetimi` menüsünden erişin
2. **Yeni Kullanıcı Ekleme:**
   - `Yeni Kullanıcı` butonuna tıklayın
   - Kişisel bilgileri doldurun
   - Rol ve departman seçin
   - Güçlü şifre belirleyin

3. **Kullanıcı Durumu Değiştirme:**
   - Kullanıcı listesinde `⚡ Aktifleştir/Pasifleştir` butonu
   - Onay mesajını kabul edin

### Sistem Ayarları
1. `Admin > Sistem Ayarları` menüsünden erişin
2. **Yapılandırılabilir Ayarlar:**
   - Uygulama adı ve URL
   - SMTP e-posta ayarları
   - Telegram bot ayarları
   - İş kuralları (zaman aşımları, eşik değerler)

3. **Ayarları Kaydetme:**
   - Değişiklikleri yapın
   - `Ayarları Kaydet` butonuna tıklayın
   - Sistem otomatik olarak güncellenecektir

### N8N Kurulum Yönetimi
1. `Admin > N8N Kurulumu` menüsünden erişin
2. **Workflow Şablonları:**
   - Hazır JSON dosyalarını indirin
   - N8N sunucunuza import edin
   - Webhook URL'lerini yapılandırın

## 🔍 Raporlama ve İstatistikler

### Dashboard İstatistikleri
- **Kullanıcılar için:**
  - Toplam talep sayısı
  - Onay bekleyen talepler
  - Onaylanan talepler
  - Reddedilen talepler

- **Yöneticiler için:**
  - Bekleyen onaylar
  - Süreç istatistikleri
  - Performans metrikleri

### Detaylı Raporlar
- Tarih aralığı bazlı raporlar
- Departman bazlı analizler
- Kategori bazlı harcama raporları
- Tedarikçi performans raporları

## 🔐 Güvenlik ve Gizlilik

### Şifre Güvenliği
- **Minimum gereksinimler:**
  - En az 8 karakter
  - Büyük ve küçük harf
  - Sayı içermeli
  - Özel karakter önerilen

### Oturum Yönetimi
- Otomatik oturum zaman aşımı: 1 saat
- Güvenli çıkış için `Çıkış` butonunu kullanın
- Şüpheli aktivite durumunda oturumlar sonlandırılır

### Veri Güvenliği
- Tüm veriler encrypted olarak saklanır
- SSL/HTTPS zorunlu
- Dosya yüklemeleri güvenlik kontrolünden geçer
- Kullanıcı işlemleri audit log'a kaydedilir

## 📱 Mobil Uyumluluğu

### Responsive Tasarım
- Tüm cihazlarda uyumlu çalışır
- Mobil cihazlarda kolay navigasyon
- Dokunmatik ekran optimizasyonu

### Mobil Özellikler
- Hızlı talep oluşturma
- Push bildirimleri (gelecek güncelleme)
- Offline çalışma desteği (sınırlı)

## ❓ Sık Sorulan Sorular (SSS)

### Q: Talep oluşturduktan sonra değişiklik yapabilir miyim?
**A:** Hayır, onay sürecine giren talepler değiştirilemez. Yeni talep oluşturmanız gerekir.

### Q: Reddedilen talep için ne yapmalıyım?
**A:** Red gerekçesini kontrol edip eksikleri tamamlayarak yeni talep oluşturabilirsiniz.

### Q: Acil talep ne zaman kullanılmalı?
**A:** Sadece gerçekten acil durumlar için kullanın. Sistem otomatik olarak üst yönetime bildirim gönderir.

### Q: E-posta bildirimi almıyorum, ne yapmalıyım?
**A:** Spam klasörünü kontrol edin. Sorun devam ederse sistem yöneticisine başvurun.

### Q: Büyük dosya yükleme sorunu yaşıyorum.
**A:** Maksimum dosya boyutu 10MB'dır. Büyük dosyaları sıkıştırın veya bölün.

### Q: Şifremi unuttum, nasıl sıfırlayabilirim?
**A:** Şu anda manuel şifre sıfırlama gerekiyor. Sistem yöneticisine başvurun.

## 📞 Destek ve İletişim

### Teknik Destek
- **E-posta:** destek@kutahyam.tr
- **Telefon:** +90 274 XXX XX XX
- **Çalışma Saatleri:** 08:30 - 17:30 (Hafta içi)

### Sistem Durumu
- **Sistem Sağlığı:** `https://satinalma.kutahyam.tr/health-check.php`
- **Bakım Duyuruları:** E-posta ve Telegram üzerinden bildirilir

### Eğitim ve Dokümantasyon
- **Video Eğitimler:** Yakında eklenecek
- **Kullanıcı Rehberi:** Bu doküman
- **API Dokümantasyonu:** Geliştiriciler için ayrı doküman

---

**📝 Not:** Bu kılavuz sistem versiyonu 2.0 için hazırlanmıştır. Güncellemeher zaman bu dokümanı kontrol edin.

**⭐ İpucu:** Sistem hakkında önerilerinizi destek ekibimizle paylaşmaktan çekinmeyin!