# N8N Workflow Şablonları

Bu klasörde Satın Alma Talep Sistemi için hazırlanmış N8N workflow şablonları bulunmaktadır.

## Mevcut Workflow'lar

### 1. Onay Süreci Workflow (approval_workflow.json)
**Amaç:** Talep onay sürecini otomatikleştirir

**Özellikler:**
- Aciliyet seviyesine göre farklı bildirim türleri
- SAS ve GS onay aşamaları
- E-posta ve Telegram bildirimleri
- SAK komisyon kontrolü (3000 TL üzeri)

**Webhook URL'leri:**
- `/webhook/approval-webhook` - Ana onay webhook'u

### 2. RFQ Teklif Toplama Workflow (rfq_workflow.json)
**Amaç:** Teklif toplama sürecini otomatikleştirir

**Özellikler:**
- Toplu tedarikçi e-posta gönderimi
- Teklif verme linkli e-postalar
- Son tarih hatırlatmaları
- Teklif alındı bildirimleri

**Webhook URL'leri:**
- `/webhook/rfq-webhook` - RFQ başlatma
- `/webhook/deadline-reminder` - Son tarih hatırlatma
- `/webhook/quotation-received` - Teklif alındı

### 3. SAK Komisyon Workflow (commission_workflow.json)
**Amaç:** Komisyon sürecini otomatikleştirir

**Özellikler:**
- Komisyon üyelerine davet e-postaları
- Toplantı hatırlatmaları
- Onay/red karar bildirimleri
- Telegram entegrasyonu

**Webhook URL'leri:**
- `/webhook/commission-webhook` - Komisyon davet
- `/webhook/meeting-reminder` - Toplantı hatırlatma
- `/webhook/commission-decision` - Komisyon kararı

## Kurulum Adımları

### 1. N8N Sunucu Kurulumu
```bash
# Docker ile N8N kurulumu
docker run -d --name n8n -p 5678:5678 n8nio/n8n

# Veya npm ile
npm install n8n -g
n8n start
```

### 2. Workflow Import Etme
1. N8N web arayüzüne gidin (http://localhost:5678)
2. "Import from file" seçeneğini kullanın
3. JSON dosyasını seçin ve import edin
4. Webhook URL'lerini kontrol edin
5. Credential'ları ayarlayın

### 3. Credential Ayarları

#### E-posta (SMTP)
```
Host: smtp.gmail.com (veya mail sunucunuz)
Port: 587
User: noreply@kutahyam.tr
Password: [app password]
```

#### Telegram Bot
```
Bot Token: [Telegram bot token]
Chat ID: -1001234567890 (grup ID'si)
```

### 4. Webhook URL Konfigürasyonu
Webhook URL'leri aşağıdaki formatı takip etmelidir:
```
https://satinalma.kutahyam.tr/webhook/[webhook-name]
```

## Sistem Entegrasyonu

### PHP Sistemden Workflow Tetikleme
```php
// Workflow tetikleme örneği
$workflowData = [
    'request_id' => $requestId,
    'title' => $title,
    'requester_name' => $requesterName,
    'department' => $department,
    'estimated_amount' => $amount,
    'urgency_level' => $urgency,
    'approval_url' => $approvalUrl,
    'rejection_url' => $rejectionUrl
];

$response = workflow()->triggerWebhook('approval-webhook', $workflowData);
```

### Webhook Güvenliği
- HMAC imza doğrulaması kullanılır
- Token bazlı yetkilendirme
- IP whitelist kontrolü

## Test Etme

1. **Onay Workflow Test:**
   - Yeni bir talep oluşturun
   - E-posta ve Telegram bildirimlerini kontrol edin
   - Onay linkini test edin

2. **RFQ Workflow Test:**
   - Teklif toplama başlatın
   - Tedarikçi e-postalarını kontrol edin
   - Teklif verme formunu test edin

3. **Komisyon Workflow Test:**
   - Komisyon toplantısı planlayın
   - Davet e-postalarını kontrol edin
   - Karar bildirimi test edin

## Sorun Giderme

### Yaygın Sorunlar
1. **Webhook çalışmıyor:**
   - URL'lerin doğru olduğundan emin olun
   - Firewall ayarlarını kontrol edin
   - N8N loglarını inceleyin

2. **E-posta gönderilmiyor:**
   - SMTP ayarlarını kontrol edin
   - Credential'ların doğru olduğundan emin olun
   - Spam klasörünü kontrol edin

3. **Telegram çalışmıyor:**
   - Bot token'ın doğru olduğundan emin olun
   - Chat ID'nin doğru olduğundan emin olun
   - Bot'un gruba eklendiğinden emin olun

### Debug
N8N execution loglarını kontrol edin:
```
GET /rest/executions
```

## Güncelleme

Workflow'ları güncellemek için:
1. Mevcut workflow'u durdur
2. Yeni JSON dosyasını import et
3. Ayarları yeniden kontrol et
4. Workflow'u yeniden başlat

## Destek

Sorunlar için:
- N8N dokümanları: https://docs.n8n.io
- Sistem logları: `/var/log/n8n`
- Admin paneli: `https://satinalma.kutahyam.tr/admin/n8n_setup.php`