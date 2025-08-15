<?php
/**
 * Token-based Approval Handler
 * Handles one-time token approvals from email/telegram links
 */

require_once 'config/auth.php';
require_once 'config/security.php';
require_once 'lib/workflow_engine.php';
require_once 'lib/audit_log.php';

$page_title = 'Onay İşlemi';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['action'])) {
    try {
        $token = $_POST['token'];
        $action = $_POST['action'];
        $comments = $_POST['comments'] ?? null;
        
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        // Process approval through workflow engine
        $result = workflow()->processApproval($token, $action, $comments);
        
        flashSuccess($result['message']);
        header('Location: approval_queue.php');
        exit;
        
    } catch (Exception $e) {
        flashError('İşlem başarısız: ' . $e->getMessage());
    }
}

// Get approval details from token
$approvalData = null;
$error = null;

if (isset($_GET['token']) && isset($_GET['action'])) {
    try {
        $token = $_GET['token'];
        $action = $_GET['action'];
        
        // Parse token to get details (without validating/consuming it)
        $security = SecurityManager::getInstance();
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) === 2) {
            $payload = json_decode(base64_decode($tokenParts[0]), true);
            
            if ($payload && isset($payload['expires'])) {
                if (time() > $payload['expires']) {
                    throw new Exception('Onay linki süresi dolmuş');
                }
                
                // Get task details using task_id from token
                $db = DatabaseHelper::getInstance();
                $task = $db->fetchRow(
                    "SELECT wt.*, pr.request_number, pr.title, pr.description, pr.estimated_amount, 
                            pr.urgency_level, pr.created_at,
                            u.first_name, u.last_name, u.email,
                            d.name as department_name
                     FROM workflow_tasks wt
                     LEFT JOIN purchase_requests pr ON wt.request_id = pr.id
                     LEFT JOIN users u ON pr.requester_id = u.id  
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE wt.id = ? AND wt.status = 'pending'",
                    [$payload['task_id']]
                );
                
                if (!$task) {
                    throw new Exception('Görev bulunamadı veya zaten işlenmiş');
                }
                
                $approvalData = [
                    'task' => $task,
                    'action' => $action,
                    'token' => $token
                ];
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header_modern.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Onay İşlemi</h1>
        <p class="mt-2 text-sm text-gray-600">
            Satın alma talebi onay işlemini gerçekleştirin
        </p>
    </div>

    <?php if ($error): ?>
        <!-- Error State -->
        <div class="rounded-md bg-danger-50 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-danger-800">Hata</h3>
                    <p class="mt-1 text-sm text-danger-700"><?= FormatHelper::text($error) ?></p>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                Ana Sayfaya Dön
            </a>
        </div>
        
    <?php elseif ($approvalData): ?>
        <!-- Approval Form -->
        <div class="bg-white shadow-sm rounded-lg">
            <!-- Request Details -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Talep Detayları</h2>
                
                <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Talep No</dt>
                        <dd class="text-sm text-gray-900 font-mono"><?= FormatHelper::text($approvalData['task']['request_number']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Aciliyet</dt>
                        <dd class="text-sm">
                            <?= StatusHelper::badge($approvalData['task']['urgency_level'], ucfirst($approvalData['task']['urgency_level'])) ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Başlık</dt>
                        <dd class="text-sm text-gray-900"><?= FormatHelper::text($approvalData['task']['title']) ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Açıklama</dt>
                        <dd class="text-sm text-gray-900"><?= FormatHelper::text($approvalData['task']['description']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tahmini Tutar</dt>
                        <dd class="text-sm text-gray-900"><?= FormatHelper::currency($approvalData['task']['estimated_amount']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Talep Tarihi</dt>
                        <dd class="text-sm text-gray-900"><?= DateHelper::formatTurkish($approvalData['task']['created_at'], true) ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Talep Eden</dt>
                        <dd class="text-sm text-gray-900">
                            <?= FormatHelper::text($approvalData['task']['first_name'] . ' ' . $approvalData['task']['last_name']) ?>
                            <span class="text-gray-500">(<?= FormatHelper::text($approvalData['task']['department_name']) ?>)</span>
                        </dd>
                    </div>
                </dl>
            </div>
            
            <!-- Approval Form -->
            <form method="POST" class="px-6 py-4">
                <?= csrf_input() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($approvalData['token']) ?>">
                <input type="hidden" name="action" value="<?= htmlspecialchars($approvalData['action']) ?>">
                
                <div class="mb-6">
                    <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">
                        Yorumlar (İsteğe bağlı)
                    </label>
                    <textarea 
                        id="comments" 
                        name="comments" 
                        rows="4" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                        placeholder="Onay/ret gerekçenizi buraya yazabilirsiniz..."
                    ></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <?php if ($approvalData['action'] === 'approve'): ?>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-success-600 hover:bg-success-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success-500">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Talebi Onayla
                        </button>
                    <?php else: ?>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-danger-600 hover:bg-danger-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-danger-500">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Talebi Reddet
                        </button>
                    <?php endif; ?>
                    
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        İptal
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Security Notice -->
        <div class="mt-6 bg-amber-50 border-l-4 border-amber-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-amber-700">
                        <strong>Güvenlik Uyarısı:</strong> Bu onay linki tek kullanımlıktır ve 48 saat süreyle geçerlidir. 
                        İşlem gerçekleştirildikten sonra link geçersiz hale gelecektir.
                    </p>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- No Token State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h2 class="mt-4 text-lg font-medium text-gray-900">Onay linki gerekli</h2>
            <p class="mt-2 text-sm text-gray-500">
                Bu sayfaya e-posta veya Telegram üzerinden gönderilen onay linki ile erişmeniz gerekmektedir.
            </p>
            <div class="mt-6">
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                    Ana Sayfaya Dön
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer_modern.php'; ?>