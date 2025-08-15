<?php
$page_title = 'Sistem Ayarları';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/lib/audit_log.php';

// Only allow system administrators
$auth->requireRole(['sistem_yoneticisi']);

$db = DatabaseHelper::getInstance();
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        // Update system settings (you can expand this)
        $settings = [
            'app_name' => $_POST['app_name'] ?? '',
            'app_url' => $_POST['app_url'] ?? '',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '',
            'smtp_user' => $_POST['smtp_user'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'telegram_bot_token' => $_POST['telegram_bot_token'] ?? '',
            'approval_timeout_hours' => $_POST['approval_timeout_hours'] ?? '',
            'min_quotation_count' => $_POST['min_quotation_count'] ?? '',
            'sak_threshold' => $_POST['sak_threshold'] ?? ''
        ];
        
        // Create settings table if not exists
        $db->execute("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Update or insert settings
        foreach ($settings as $key => $value) {
            if (!empty($value)) {
                $existing = $db->fetchRow(
                    "SELECT id FROM system_settings WHERE setting_key = ?",
                    [$key]
                );
                
                if ($existing) {
                    $db->update(
                        'system_settings',
                        ['setting_value' => $value],
                        'setting_key = ?',
                        [$key]
                    );
                } else {
                    $db->insert('system_settings', [
                        'setting_key' => $key,
                        'setting_value' => $value
                    ]);
                }
            }
        }
        
        // Log the change
        audit()->log(
            'system_config',
            'System settings updated',
            ['additional_data' => ['updated_settings' => array_keys($settings)]]
        );
        
        $message = 'Sistem ayarları başarıyla güncellendi.';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = 'Ayarları güncellerken hata oluştu: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current settings
$currentSettings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settingsResult as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Get some system stats
$stats = [
    'total_users' => $db->fetchRow("SELECT COUNT(*) as count FROM users")['count'],
    'total_requests' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_requests")['count'],
    'pending_approvals' => $db->fetchRow("SELECT COUNT(*) as count FROM workflow_tasks WHERE status = 'pending'")['count'],
    'total_categories' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_categories")['count']
];
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
            <h1 class="h2"><i class="fas fa-cog me-2"></i>Sistem Ayarları</h1>
        </div>
    </div>
</div>

<!-- System Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?= $stats['total_users'] ?></h5>
                <p class="card-text">Toplam Kullanıcı</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info"><?= $stats['total_requests'] ?></h5>
                <p class="card-text">Toplam Talep</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning"><?= $stats['pending_approvals'] ?></h5>
                <p class="card-text">Bekleyen Onaylar</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><?= $stats['total_categories'] ?></h5>
                <p class="card-text">Kategoriler</p>
            </div>
        </div>
    </div>
</div>

<!-- Settings Form -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-wrench me-2"></i>Sistem Konfigürasyonu</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_input() ?>
                    
                    <!-- General Settings -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3">Genel Ayarlar</h6>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="app_name" class="form-label">Uygulama Adı</label>
                                <input type="text" class="form-control" id="app_name" name="app_name" 
                                       value="<?= htmlspecialchars($currentSettings['app_name'] ?? EnvConfig::get('APP_NAME', 'Satın Alma Talep Sistemi')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="app_url" class="form-label">Uygulama URL'i</label>
                                <input type="url" class="form-control" id="app_url" name="app_url" 
                                       value="<?= htmlspecialchars($currentSettings['app_url'] ?? EnvConfig::get('APP_URL', 'https://satinalma.kutahyam.tr')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3 mt-4">E-posta Ayarları</h6>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="smtp_host" class="form-label">SMTP Sunucu</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_host'] ?? EnvConfig::get('SMTP_HOST', '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_port'] ?? EnvConfig::get('SMTP_PORT', '587')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="smtp_user" class="form-label">SMTP Kullanıcı Adı</label>
                                <input type="email" class="form-control" id="smtp_user" name="smtp_user" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_user'] ?? EnvConfig::get('SMTP_USER', '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="smtp_from_email" class="form-label">Gönderici E-posta</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_from_email'] ?? EnvConfig::get('SMTP_FROM_EMAIL', 'noreply@kutahyam.tr')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Telegram Settings -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3 mt-4">Telegram Ayarları</h6>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telegram_bot_token" class="form-label">Telegram Bot Token</label>
                                <input type="text" class="form-control" id="telegram_bot_token" name="telegram_bot_token" 
                                       value="<?= htmlspecialchars($currentSettings['telegram_bot_token'] ?? EnvConfig::get('TELEGRAM_BOT_TOKEN', '')) ?>">
                                <div class="form-text">Telegram bot ile bildirim göndermek için gerekli</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Rules -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2 mb-3 mt-4">İş Kuralları</h6>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="approval_timeout_hours" class="form-label">Onay Zaman Aşımı (Saat)</label>
                                <input type="number" class="form-control" id="approval_timeout_hours" name="approval_timeout_hours" 
                                       value="<?= htmlspecialchars($currentSettings['approval_timeout_hours'] ?? EnvConfig::get('APPROVAL_TIMEOUT_HOURS', '48')) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_quotation_count" class="form-label">Minimum Teklif Sayısı</label>
                                <input type="number" class="form-control" id="min_quotation_count" name="min_quotation_count" 
                                       value="<?= htmlspecialchars($currentSettings['min_quotation_count'] ?? EnvConfig::get('MIN_QUOTATION_COUNT', '3')) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="sak_threshold" class="form-label">SAK Komisyon Eşiği (TL)</label>
                                <input type="number" class="form-control" id="sak_threshold" name="sak_threshold" step="0.01"
                                       value="<?= htmlspecialchars($currentSettings['sak_threshold'] ?? EnvConfig::get('SAK_THRESHOLD', '3000')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="../dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Ayarları Kaydet
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Sistem Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>PHP Sürümü:</strong></td>
                                <td><?= PHP_VERSION ?></td>
                            </tr>
                            <tr>
                                <td><strong>Sistem:</strong></td>
                                <td><?= php_uname('s') . ' ' . php_uname('r') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Sunucu Yazılımı:</strong></td>
                                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Bellek Limiti:</strong></td>
                                <td><?= ini_get('memory_limit') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Upload Limit:</strong></td>
                                <td><?= ini_get('upload_max_filesize') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Max Execution Time:</strong></td>
                                <td><?= ini_get('max_execution_time') ?> saniye</td>
                            </tr>
                            <tr>
                                <td><strong>Session Path:</strong></td>
                                <td><?= session_save_path() ?: 'Default' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Timezone:</strong></td>
                                <td><?= date_default_timezone_get() ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>