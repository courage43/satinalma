<?php
$page_title = 'Yeni Satın Alma Talebi';
require_once 'includes/header_modern.php';
require_once 'lib/helpers.php';
require_once 'lib/workflow_engine.php';
require_once 'lib/audit_log.php';

$message = '';
$message_type = '';

$db = DatabaseHelper::getInstance();

// Kategorileri getir
$categories = $db->fetchAll("SELECT * FROM purchase_categories ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        $db->beginTransaction();
        
        // Talep numarası oluştur
        $request_number = FormatHelper::generateReference('ST', date('Y'));
        
        // Calculate total estimated amount
        $estimated_amount = 0.00;
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name']) && isset($item['quantity']) && isset($item['unit_price'])) {
                    $quantity = floatval($item['quantity'] ?: 0);
                    $unit_price = floatval($item['unit_price'] ?: 0);
                    $estimated_amount += $quantity * $unit_price;
                }
            }
        }
        
        // Ana talep kaydı
        $requestData = [
            'request_number' => $request_number,
            'requester_id' => $auth->getCurrentUserId(),
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'justification' => trim($_POST['justification'] ?? ''),
            'category_id' => !empty($_POST['category_id']) ? intval($_POST['category_id']) : null,
            'urgency_level' => $_POST['urgency_level'] ?? 'orta',
            'estimated_amount' => round($estimated_amount, 2),
            'requested_delivery_date' => !empty($_POST['requested_delivery_date']) ? $_POST['requested_delivery_date'] : null,
            'status' => 'sas_incelemede'
        ];
        
        $request_id = $db->insert('purchase_requests', $requestData);
        
        // Talep kalemlerini kaydet
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name'])) {
                    $quantity = floatval($item['quantity'] ?: 1);
                    $unit_price = floatval($item['unit_price'] ?: 0);
                    $total_price = round($quantity * $unit_price, 2);
                    
                    $itemData = [
                        'request_id' => $request_id,
                        'item_name' => trim($item['name']),
                        'description' => trim($item['description'] ?? ''),
                        'quantity' => $quantity,
                        'unit' => trim($item['unit'] ?? 'Adet'),
                        'estimated_unit_price' => $unit_price,
                        'estimated_total_price' => $total_price,
                        'specifications' => trim($item['specifications'] ?? ''),
                        'brand_preference' => trim($item['brand_preference'] ?? '')
                    ];
                    
                    $db->insert('request_items', $itemData);
                }
            }
        }
        
        // Start automated workflow
        $workflowData = array_merge($requestData, [
            'request_id' => $request_id
        ]);
        
        $taskId = workflow()->startWorkflow($request_id, $workflowData);
        
        $db->commit();
        
        // Log the request creation
        auditRequest('create', $request_id, $request_number, null, $requestData);
        
        // Trigger webhook for new request
        $webhook_url = "https://satinalma.kutahyam.tr/api/webhooks/new_request.php";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request_id' => $request_id]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
        
        flashSuccess('Satın alma talebi başarıyla oluşturuldu. Talep numarası: ' . $request_number . '. Onay sürecine alındı.');
        
        // Redirect to prevent resubmission
        header('Location: my_requests.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        flashError('Talep oluşturulurken hata oluştu: ' . $e->getMessage());
    }
}
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Yeni Satın Alma Talebi</h1>
            <p class="mt-2 text-sm text-gray-600">
                Satın alma sürecini başlatmak için talep formunu doldurun
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="my_requests.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Taleplerim
            </a>
        </div>
    </div>
</div>

<div class="bg-white shadow-sm rounded-lg">
    <form method="POST" id="requestForm" class="p-6 space-y-6">
        <?= csrf_input() ?>
        
        <!-- Basic Information -->
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Temel Bilgiler</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Talep Başlığı *</label>
                        <input type="text" id="title" name="title" required 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                               placeholder="Örn: Bilgisayar ve Yazıcı Alımı">
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori *</label>
                        <select id="category_id" name="category_id" required 
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="">Kategori seçiniz</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= FormatHelper::text($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Açıklama *</label>
                <textarea id="description" name="description" rows="3" required
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                          placeholder="Satın alınacak ürün/hizmetin detaylı açıklaması..."></textarea>
            </div>
            
            <div>
                <label for="justification" class="block text-sm font-medium text-gray-700 mb-2">Gerekçe *</label>
                <textarea id="justification" name="justification" rows="3" required
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                          placeholder="Bu satın alma talebinin gerekçesi ve önemi..."></textarea>
            </div>
            
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <label for="urgency_level" class="block text-sm font-medium text-gray-700 mb-2">Aciliyet Durumu</label>
                    <select id="urgency_level" name="urgency_level" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        <option value="düşük">Düşük</option>
                        <option value="orta" selected>Orta</option>
                        <option value="yüksek">Yüksek</option>
                        <option value="acil">Acil</option>
                    </select>
                </div>
                <div>
                    <label for="budget_estimate" class="block text-sm font-medium text-gray-700 mb-2">Tahmini Bütçe (TL)</label>
                    <input type="number" id="budget_estimate" name="budget_estimate" step="0.01"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                           placeholder="0.00">
                </div>
                <div>
                    <label for="requested_delivery_date" class="block text-sm font-medium text-gray-700 mb-2">Talep Edilen Teslimat Tarihi</label>
                    <input type="date" id="requested_delivery_date" name="requested_delivery_date"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                </div>
            </div>
        </div>
        
        <!-- Items Section -->
        <div class="border-t border-gray-200 pt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Talep Kalemleri</h3>
                <button type="button" id="addItemBtn" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Kalem Ekle
                </button>
            </div>
            
            <div id="itemsContainer" class="space-y-6">
                <div class="item-row bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ürün/Hizmet Adı *</label>
                            <input type="text" name="items[0][name]" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                   placeholder="Ürün adı">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Miktar *</label>
                            <input type="number" name="items[0][quantity]" min="1" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm item-quantity">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birim</label>
                            <input type="text" name="items[0][unit]" placeholder="Adet"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birim Fiyat (TL)</label>
                            <input type="number" name="items[0][unit_price]" step="0.01"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm item-price">
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="remove-item w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-danger-600 hover:bg-danger-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-danger-500" style="display:none;">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marka Tercihi</label>
                            <input type="text" name="items[0][brand_preference]"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                   placeholder="Marka adı (isteğe bağlı)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                            <textarea name="items[0][description]" rows="2"
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                      placeholder="Ürün açıklaması"></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teknik Özellikler</label>
                        <textarea name="items[0][specifications]" rows="2"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                  placeholder="Teknik özellikler ve gereksinimler"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="border-t border-gray-200 pt-6">
            <div class="flex justify-end space-x-3">
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    İptal
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Talep Oluştur
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let itemCount = 1;

document.getElementById('addItemBtn').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'item-row bg-gray-50 rounded-lg p-4';
    newRow.innerHTML = `
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ürün/Hizmet Adı *</label>
                <input type="text" name="items[${itemCount}][name]" required
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                       placeholder="Ürün adı">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Miktar *</label>
                <input type="number" name="items[${itemCount}][quantity]" min="1" required
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm item-quantity">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Birim</label>
                <input type="text" name="items[${itemCount}][unit]" placeholder="Adet"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Birim Fiyat (TL)</label>
                <input type="number" name="items[${itemCount}][unit_price]" step="0.01"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm item-price">
            </div>
            <div class="flex items-end">
                <button type="button" class="remove-item w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-danger-600 hover:bg-danger-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-danger-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marka Tercihi</label>
                <input type="text" name="items[${itemCount}][brand_preference]"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                       placeholder="Marka adı (isteğe bağlı)">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                <textarea name="items[${itemCount}][description]" rows="2"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                          placeholder="Ürün açıklaması"></textarea>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Teknik Özellikler</label>
            <textarea name="items[${itemCount}][specifications]" rows="2"
                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                      placeholder="Teknik özellikler ve gereksinimler"></textarea>
        </div>
    `;
    
    container.appendChild(newRow);
    itemCount++;
    
    updateRemoveButtons();
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
        e.target.closest('.item-row').remove();
        updateRemoveButtons();
    }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, index) => {
        const removeBtn = row.querySelector('.remove-item');
        if (rows.length > 1) {
            removeBtn.style.display = 'flex';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

// Auto-calculate total amounts
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('item-quantity') || e.target.classList.contains('item-price')) {
        calculateTotal();
    }
});

function calculateTotal() {
    let total = 0;
    const items = document.querySelectorAll('.item-row');
    
    items.forEach(item => {
        const quantity = parseFloat(item.querySelector('.item-quantity')?.value || 0);
        const price = parseFloat(item.querySelector('.item-price')?.value || 0);
        total += quantity * price;
    });
    
    // Update budget estimate field if it's empty
    const budgetField = document.getElementById('budget_estimate');
    if (!budgetField.value || budgetField.value == 0) {
        budgetField.value = total.toFixed(2);
    }
}

// Initialize remove buttons visibility
updateRemoveButtons();
</script>

<?php include 'includes/footer_modern.php'; ?>