<?php
$page_title = 'N8N Workflow Kurulumu';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/lib/audit_log.php';

// Only allow system administrators
$auth->requireRole(['sistem_yoneticisi']);

$db = DatabaseHelper::getInstance();
$message = '';
$message_type = '';

// Available workflows
$availableWorkflows = [
    'approval_workflow.json' => [
        'name' => 'Onay Süreci Workflow',
        'description' => 'Talep onay sürecini otomatikleştir',
        'webhooks' => [
            '/webhook/approval-webhook' => 'Ana onay webhook\'u'
        ],
        'features' => [
            'Aciliyet kontrolü',
            'E-posta bildirimleri',
            'Telegram bildirimleri', 
            'SAK komisyon kontrolü'
        ]
    ],
    'rfq_workflow.json' => [
        'name' => 'RFQ Teklif Toplama Workflow',
        'description' => 'Teklif toplama sürecini otomatikleştir',
        'webhooks' => [
            '/webhook/rfq-webhook' => 'RFQ başlatma webhook\'u',
            '/webhook/deadline-reminder' => 'Son tarih hatırlatma webhook\'u',
            '/webhook/quotation-received' => 'Teklif alındı webhook\'u'
        ],
        'features' => [
            'Toplu tedarikçi e-postası',
            'Son tarih hatırlatması',
            'Teklif bildirimleri',
            'Telegram entegrasyonu'
        ]
    ],
    'commission_workflow.json' => [
        'name' => 'SAK Komisyon Workflow',
        'description' => 'Komisyon sürecini otomatikleştir',
        'webhooks' => [
            '/webhook/commission-webhook' => 'Komisyon davet webhook\'u',
            '/webhook/meeting-reminder' => 'Toplantı hatırlatma webhook\'u',
            '/webhook/commission-decision' => 'Komisyon karar webhook\'u'
        ],
        'features' => [
            'Komisyon davet e-postaları',
            'Toplantı hatırlatmaları',
            'Karar bildirimleri',
            'Otomatik onay/red işlemleri'
        ]
    ]
];

// Handle workflow download
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    $filepath = dirname(__DIR__) . '/n8n_workflows/' . $filename;
    
    if (file_exists($filepath) && array_key_exists($filename, $availableWorkflows)) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $message = 'Workflow dosyası bulunamadı.';
        $message_type = 'danger';
    }
}

// Handle configuration save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        $config = [
            'n8n_url' => $_POST['n8n_url'] ?? '',
            'n8n_api_key' => $_POST['n8n_api_key'] ?? '',
            'webhook_base_url' => $_POST['webhook_base_url'] ?? '',
            'telegram_chat_id' => $_POST['telegram_chat_id'] ?? ''
        ];
        
        // Create webhook_configs table if not exists
        $db->execute("
            CREATE TABLE IF NOT EXISTS webhook_configs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_type VARCHAR(100) NOT NULL,
                webhook_url VARCHAR(500) NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Save configuration to database
        foreach ($config as $key => $value) {
            if (!empty($value)) {
                $existing = $db->fetchRow("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
                
                if ($existing) {
                    $db->update('system_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                } else {
                    $db->insert('system_settings', [
                        'setting_key' => $key,
                        'setting_value' => $value
                    ]);
                }
            }
        }
        
        // Setup webhook configurations for N8N
        if (!empty($config['webhook_base_url'])) {
            $webhook_base = rtrim($config['webhook_base_url'], '/');
            
            // Clear existing webhook configs
            $db->execute("DELETE FROM webhook_configs WHERE event_type IN ('new_request', 'approval_update')");
            
            // Add N8N webhook configurations
            $webhooks = [
                ['event_type' => 'new_request', 'webhook_url' => 'https://otomasyon.kutahyam.tr/webhook/approval-webhook'],
                ['event_type' => 'approval_update', 'webhook_url' => 'https://otomasyon.kutahyam.tr/webhook/approval-webhook']
            ];
            
            foreach ($webhooks as $webhook) {
                $db->insert('webhook_configs', $webhook);
            }
        }
        
        audit()->log('n8n_config', 'N8N configuration updated', ['config_keys' => array_keys($config)]);
        
        $message = 'N8N konfigürasyonu başarıyla kaydedildi.';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = 'Konfigürasyon kaydedilirken hata oluştu: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current configuration
$currentConfig = [];
$configResult = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'n8n_%' OR setting_key LIKE 'webhook_%' OR setting_key = 'telegram_chat_id'");
foreach ($configResult as $setting) {
    $currentConfig[$setting['setting_key']] = $setting['setting_value'];
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-robot me-2"></i>N8N Workflow Kurulumu</h1>
        </div>
    </div>
</div>

<!-- Configuration Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-cogs me-2"></i>N8N Konfigürasyonu</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_config">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="n8n_url" class="form-label">N8N Sunucu URL'i</label>
                                <input type="url" class="form-control" id="n8n_url" name="n8n_url" 
                                       value="<?= htmlspecialchars($currentConfig['n8n_url'] ?? 'http://localhost:5678') ?>"
                                       placeholder="http://localhost:5678">
                                <div class="form-text">N8N sunucunuzun URL'i</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="n8n_api_key" class="form-label">N8N API Key</label>
                                <input type="password" class="form-control" id="n8n_api_key" name="n8n_api_key" 
                                       value="<?= htmlspecialchars($currentConfig['n8n_api_key'] ?? '') ?>"
                                       placeholder="n8n_api_key">
                                <div class="form-text">N8N API erişimi için gerekli</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="webhook_base_url" class="form-label">Webhook Base URL</label>
                                <input type="url" class="form-control" id="webhook_base_url" name="webhook_base_url" 
                                       value="<?= htmlspecialchars($currentConfig['webhook_base_url'] ?? 'https://satinalma.kutahyam.tr') ?>"
                                       placeholder="https://satinalma.kutahyam.tr">
                                <div class="form-text">Webhook URL'lerin temel adresi</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telegram_chat_id" class="form-label">Telegram Chat ID</label>
                                <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" 
                                       value="<?= htmlspecialchars($currentConfig['telegram_chat_id'] ?? '-1001234567890') ?>"
                                       placeholder="-1001234567890">
                                <div class="form-text">Bildirimler için Telegram grup ID'si</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Konfigürasyonu Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Templates -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-download me-2"></i>Hazır Workflow Şablonları</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    Aşağıdaki workflow şablonlarını indirip N8N sunucunuza import edebilirsiniz.
                    Her workflow otomatik olarak sistemle entegre çalışacak şekilde hazırlanmıştır.
                </p>
                
                <?php foreach ($availableWorkflows as $filename => $workflow): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="card-title mb-2"><?= $workflow['name'] ?></h6>
                                <p class="card-text text-muted mb-2"><?= $workflow['description'] ?></p>
                                
                                <div class="mb-2">
                                    <strong>Özellikler:</strong>
                                    <ul class="mb-0">
                                        <?php foreach ($workflow['features'] as $feature): ?>
                                            <li><?= $feature ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                
                                <div class="mb-0">
                                    <strong>Webhook URL'leri:</strong>
                                    <ul class="mb-0">
                                        <?php foreach ($workflow['webhooks'] as $url => $desc): ?>
                                            <li><code><?= $url ?></code> - <?= $desc ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="?download=<?= urlencode($filename) ?>" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>İndir
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Setup Instructions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-book me-2"></i>Kurulum Talimatları</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>N8N Workflow Kurulum Adımları:</h6>
                    <ol class="mb-0">
                        <li><strong>N8N'i Başlatın:</strong> N8N sunucunuzu başlatın ve web arayüzüne erişin</li>
                        <li><strong>Workflow'u İndirin:</strong> Yukarıdaki şablonlardan birini indirin</li>
                        <li><strong>Import Edin:</strong> N8N'de "Import from file" seçeneğini kullanın</li>
                        <li><strong>Webhook URL'lerini Güncelleyin:</strong> Webhook node'larında URL'leri kontrol edin</li>
                        <li><strong>Credential'ları Ayarlayın:</strong> E-posta ve Telegram credential'larını ekleyin</li>
                        <li><strong>Workflow'u Aktif Edin:</strong> Workflow'u aktif duruma getirin</li>
                        <li><strong>Test Edin:</strong> Bir test talebi oluşturarak workflow'u test edin</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Önemli Notlar:</h6>
                    <ul class="mb-0">
                        <li>N8N sunucunuz sürekli çalışır durumda olmalıdır</li>
                        <li>Webhook URL'leri internet erişimine açık olmalıdır</li>
                        <li>SMTP ve Telegram bot ayarları doğru yapılmış olmalıdır</li>
                        <li>Workflow'lar arasında sıralama önemlidir</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Back to Dashboard -->
<div class="row mt-4">
    <div class="col-12">
        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Ana Sayfaya Dön
        </a>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>