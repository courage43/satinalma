# Satın Alma Talep Sistemi - Kurulum Kılavuzu

## 📋 Sistem Gereksinimleri

### Sunucu Gereksinimleri
- **PHP 8.0+** (önerilen: PHP 8.1+)
- **MySQL 8.0+** veya **MariaDB 10.4+**
- **Apache 2.4+** veya **Nginx 1.18+**
- **SSL Sertifikası** (HTTPS zorunlu)
- **Cron Jobs** desteği

### PHP Eklentileri
```bash
# Gerekli PHP eklentileri
php-mysqli
php-pdo
php-pdo-mysql
php-json
php-mbstring
php-openssl
php-curl
php-zip
php-gd
```

### Donanım Gereksinimleri
- **RAM:** Minimum 2GB, önerilen 4GB+
- **Disk:** Minimum 1GB boş alan
- **CPU:** 2 core önerilen

## 🚀 Hızlı Kurulum

### 1. Dosyaları Sunucuya Yükleyin
```bash
# Projeyi web root dizinine kopyalayın
cp -r satinalma-sistemi/* /var/www/html/
chown -R www-data:www-data /var/www/html/
chmod -R 755 /var/www/html/
```

### 2. Veritabanı Oluşturun
```sql
-- MySQL/MariaDB'de veritabanı oluşturun
CREATE DATABASE satin_alma_sistem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'satinalma_user'@'localhost' IDENTIFIED BY 'guvenli_sifre_123';
GRANT ALL PRIVILEGES ON satin_alma_sistem.* TO 'satinalma_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Ortam Değişkenlerini Ayarlayın
```bash
# .env dosyasını oluşturun
cp .env.example .env
nano .env
```

**`.env` dosyası içeriği:**
```env
# Veritabanı Ayarları
DB_HOST=localhost
DB_NAME=satin_alma_sistem
DB_USER=satinalma_user
DB_PASS=guvenli_sifre_123

# Güvenlik Anahtarları (değiştirin!)
APP_SECRET_KEY=32_karakter_random_anahtar_buraya
CSRF_SECRET=32_karakter_csrf_anahtari_buraya
HMAC_SECRET=32_karakter_hmac_anahtari_buraya

# Uygulama Ayarları
APP_NAME=Satın Alma Talep Sistemi
APP_URL=https://satinalma.kutahyam.tr
APP_ENV=production

# E-posta Ayarları
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=noreply@kutahyam.tr
SMTP_PASS=uygulama_sifresi
SMTP_FROM_EMAIL=noreply@kutahyam.tr
SMTP_FROM_NAME=Satın Alma Sistemi

# Telegram Ayarları (isteğe bağlı)
TELEGRAM_BOT_TOKEN=bot_token_buraya
TELEGRAM_CHAT_ID=-1001234567890

# N8N Entegrasyon (isteğe bağlı)
N8N_URL=http://localhost:5678
N8N_API_KEY=n8n_api_anahtari
N8N_WEBHOOK_SECRET=webhook_secret_anahtari

# İş Kuralları
APPROVAL_TIMEOUT_HOURS=48
MIN_QUOTATION_COUNT=3
SAK_THRESHOLD=3000
```

### 4. Sistemi Başlatın
```bash
# Kurulum scriptini çalıştırın
php init_system.php

# Veritabanı güncellemelerini uygulayın
php upgrade_database.php
```

### 5. Web Sunucu Ayarları

#### Apache (.htaccess)
```apache
RewriteEngine On

# HTTPS zorla
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Güvenlik başlıkları
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000"

# Dosya erişim kısıtlamaları
<Files ".env">
    Deny from all
</Files>

<FilesMatch "\.(env|log|sql)$">
    Deny from all
</FilesMatch>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name satinalma.kutahyam.tr;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name satinalma.kutahyam.tr;
    root /var/www/html;
    index index.php;

    # SSL ayarları
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # PHP işleme
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Güvenlik
    location ~ /\.(env|git) {
        deny all;
    }

    # Güvenlik başlıkları
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
}
```

## 🔧 Detaylı Konfigürasyon

### E-posta Ayarları

#### Gmail SMTP
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=app-specific-password  # 2FA aktifse uygulama şifresi
```

#### Outlook/Hotmail SMTP
```env
SMTP_HOST=smtp.live.com
SMTP_PORT=587
SMTP_USER=your-email@outlook.com
SMTP_PASS=your-password
```

### Telegram Bot Kurulumu

1. **BotFather ile bot oluşturun:**
   ```
   /start
   /newbot
   Bot adını girin: Satın Alma Bot
   Bot kullanıcı adını girin: @satinalma_bot
   ```

2. **Bot token'ını kaydedin**

3. **Grup oluşturun ve bot'u ekleyin**

4. **Chat ID'yi öğrenin:**
   ```bash
   curl https://api.telegram.org/bot[BOT_TOKEN]/getUpdates
   ```

### Cron Jobs Ayarları

```bash
# Crontab'ı düzenleyin
crontab -e

# Aşağıdaki satırları ekleyin:
# Her 30 dakikada hatırlatma kontrolü
*/30 * * * * php /var/www/html/cron/process_reminders.php

# Günlük raporlar (her gün saat 09:00)
0 9 * * * php /var/www/html/cron/daily_reports.php

# Log temizliği (her pazartesi saat 02:00)
0 2 * * 1 find /var/www/html/logs -name "*.log" -mtime +30 -delete
```

## 👥 İlk Kullanıcı Ayarları

### 1. Admin Hesabı
Sistem kurulumu sonrası otomatik olarak oluşturulan admin hesabı:
```
Kullanıcı Adı: admin
Şifre: 123456
```

**⚠️ GÜVENLİK UYARISI:** İlk giriş sonrası mutlaka şifreyi değiştirin!

### 2. Departmanlar ve Roller

#### Varsayılan Departmanlar:
- Bilgi İşlem (BIM)
- İnsan Kaynakları (IK)  
- Mali İşler (MAL)
- Satın Alma (SAT)
- Genel Sekreterlik (GS)

#### Varsayılan Roller:
- **Sistem Yöneticisi:** Tüm sistem erişimi
- **Kullanıcı:** Talep oluşturabilir
- **Satın Alma Sorumlusu:** İlk onay aşaması
- **Genel Sekreter:** İkinci onay aşaması
- **SAK1/SAK2 Üyesi:** Komisyon üyeleri
- **Yönetim Kurulu Üyesi:** YK onayları

### 3. Yeni Kullanıcı Ekleme

Admin panelinden (`/admin/users.php`):
1. **Kullanıcı Yönetimi** bölümüne gidin
2. **Yeni Kullanıcı** butonuna tıklayın
3. Bilgileri doldurun:
   - Kullanıcı adı, e-posta, ad-soyad
   - Rol ve departman seçin
   - Güçlü şifre belirleyin

## 🤖 N8N Workflow Entegrasyonu

### 1. N8N Kurulumu

#### Docker ile:
```bash
docker run -d \
  --name n8n \
  -p 5678:5678 \
  -v n8n_data:/home/node/.n8n \
  n8nio/n8n
```

#### NPM ile:
```bash
npm install n8n -g
n8n start
```

### 2. Workflow'ları Import Etme

1. N8N web arayüzüne gidin: `http://localhost:5678`
2. "Import from file" seçin
3. `n8n_workflows/` klasöründeki JSON dosyalarını import edin:
   - `approval_workflow.json` - Onay süreçleri
   - `rfq_workflow.json` - Teklif toplama
   - `commission_workflow.json` - Komisyon süreçleri

### 3. Webhook URL'leri

Sistem aşağıdaki webhook'ları bekler:
```
https://your-n8n-domain.com/webhook/approval-webhook
https://your-n8n-domain.com/webhook/rfq-webhook
https://your-n8n-domain.com/webhook/commission-webhook
```

## 🔐 Güvenlik Ayarları

### 1. Dosya İzinleri
```bash
# Güvenli dosya izinleri
find /var/www/html -type f -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;
chmod 600 /var/www/html/.env
chmod +x /var/www/html/cron/*.php
```

### 2. Veritabanı Güvenliği
```sql
-- Root hesabını devre dışı bırak
UPDATE mysql.user SET plugin = 'mysql_native_password' WHERE User = 'root';
DELETE FROM mysql.user WHERE User = '';
DELETE FROM mysql.user WHERE User = 'root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
FLUSH PRIVILEGES;
```

### 3. SSL/TLS Ayarları
- **Let's Encrypt** ile ücretsiz SSL sertifikası
- **A+ SSL Rating** için güçlü cipher suites
- **HSTS** başlığı aktif

## 📊 İzleme ve Bakım

### 1. Log Dosyaları
```bash
# Log dosyalarının konumu
/var/www/html/logs/app.log          # Uygulama logları
/var/www/html/logs/audit.log        # Denetim logları
/var/www/html/logs/workflow.log     # Workflow logları
/var/www/html/logs/error.log        # Hata logları
```

### 2. Veritabanı Yedekleme
```bash
#!/bin/bash
# Günlük veritabanı yedeği
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u satinalma_user -p satin_alma_sistem > /backup/db_backup_$DATE.sql
find /backup -name "db_backup_*.sql" -mtime +7 -delete
```

### 3. Sistem Durumu İzleme
```bash
# Sistem sağlık kontrolü scripti
#!/bin/bash
curl -f https://satinalma.kutahyam.tr/health-check.php || echo "Site down!"
```

## 🔄 Güncelleme

### 1. Yedekleme
```bash
# Kod ve veritabanı yedeği alın
tar -czf backup_$(date +%Y%m%d).tar.gz /var/www/html
mysqldump -u satinalma_user -p satin_alma_sistem > db_backup_$(date +%Y%m%d).sql
```

### 2. Güncelleme Uygulama
```bash
# Yeni dosyaları kopyalayın
cp -r new-version/* /var/www/html/

# Veritabanı güncellemelerini uygulayın
php upgrade_database.php

# Cache temizliği
rm -rf /tmp/cache/*
```

## 🆘 Sorun Giderme

### Yaygın Sorunlar

#### 1. Veritabanı Bağlantı Hatası
```
Çözüm:
- .env dosyasındaki DB bilgilerini kontrol edin
- MySQL servisinin çalıştığından emin olun
- Kullanıcı izinlerini kontrol edin
```

#### 2. E-posta Gönderilmiyor
```
Çözüm:
- SMTP ayarlarını kontrol edin
- Firewall'da 587 portunu açın
- Gmail için uygulama şifresi kullanın
```

#### 3. Dosya Yükleme Hatası
```
Çözüm:
- uploads/ klasörü izinlerini kontrol edin (755)
- PHP upload_max_filesize ayarını artırın
- Disk alanını kontrol edin
```

#### 4. 500 Internal Server Error
```
Çözüm:
- error.log dosyasını kontrol edin
- PHP syntax hatalarını kontrol edin
- .htaccess dosyasını kontrol edin
```

## 📞 Destek

### İletişim
- **Geliştirici:** Kaan Varol
- **E-posta:** destek@kutahyam.tr
- **Dokümantasyon:** https://docs.satinalma.kutahyam.tr

### Sistem Bilgileri
- **Versiyon:** 2.0
- **Son Güncelleme:** 2025
- **PHP Versiyon:** 8.1+
- **Lisans:** MIT

---

**📝 Not:** Bu kılavuz sisteminizin güvenli ve stabil çalışması için yazılmıştır. Adımları sırasıyla takip etmeniz önemlidir.

**⚠️ Üretim Ortamı Uyarısı:** Canlı sistemde değişiklik yapmadan önce mutlaka test ortamında deneyiniz.