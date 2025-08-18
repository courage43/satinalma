<?php
$page_title = 'Kategori Yönetimi';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/lib/audit_log.php';

// Only allow system administrators
$auth->requireRole(['sistem_yoneticisi']);

$db = DatabaseHelper::getInstance();
$message = '';
$message_type = '';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_category':
                $categoryData = [
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description'] ?? ''),
                    'is_active' => 1
                ];
                
                // Check if category name already exists
                $existing = $db->fetchRow(
                    "SELECT id FROM purchase_categories WHERE name = ?",
                    [$categoryData['name']]
                );
                
                if ($existing) {
                    throw new Exception('Bu kategori adı zaten mevcut.');
                }
                
                $categoryId = $db->insert('purchase_categories', $categoryData);
                
                audit()->log(
                    'category_create',
                    "New category created: {$categoryData['name']}",
                    ['table' => 'purchase_categories', 'id' => $categoryId]
                );
                
                $message = 'Kategori başarıyla oluşturuldu.';
                $message_type = 'success';
                break;
                
            case 'update_category':
                $categoryId = $_POST['category_id'];
                $categoryData = [
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description'] ?? '')
                ];
                
                // Check if category name already exists (excluding current)
                $existing = $db->fetchRow(
                    "SELECT id FROM purchase_categories WHERE name = ? AND id != ?",
                    [$categoryData['name'], $categoryId]
                );
                
                if ($existing) {
                    throw new Exception('Bu kategori adı zaten mevcut.');
                }
                
                $db->update('purchase_categories', $categoryData, 'id = ?', [$categoryId]);
                
                audit()->log(
                    'category_update',
                    "Category updated: {$categoryData['name']}",
                    ['table' => 'purchase_categories', 'id' => $categoryId]
                );
                
                $message = 'Kategori başarıyla güncellendi.';
                $message_type = 'success';
                break;
                
            case 'toggle_status':
                $categoryId = $_POST['category_id'];
                $isActive = $_POST['is_active'];
                
                $db->update(
                    'purchase_categories',
                    ['is_active' => $isActive],
                    'id = ?',
                    [$categoryId]
                );
                
                audit()->log(
                    'category_status',
                    "Category status changed: ID {$categoryId}",
                    ['table' => 'purchase_categories', 'id' => $categoryId]
                );
                
                $message = 'Kategori durumu güncellendi.';
                $message_type = 'success';
                break;
        }
        
    } catch (Exception $e) {
        $message = 'İşlem başarısız: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get all categories with usage statistics
$categories = $db->fetchAll("
    SELECT pc.*, 
           COUNT(pr.id) as usage_count
    FROM purchase_categories pc
    LEFT JOIN purchase_requests pr ON pc.id = pr.category_id
    GROUP BY pc.id
    ORDER BY pc.is_active DESC, pc.name ASC
");
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-tags me-2"></i>Kategori Yönetimi</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Yeni Kategori
            </button>
        </div>
    </div>
</div>

<!-- Categories Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kategori Adı</th>
                                <th>Açıklama</th>
                                <th>Kullanım Sayısı</th>
                                <th>Durum</th>
                                <th>Oluşturma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= $category['id'] ?></td>
                                <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                                <td><?= htmlspecialchars($category['description']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $category['usage_count'] ?> talep</span>
                                </td>
                                <td>
                                    <?php if ($category['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= DateHelper::formatTurkish($category['created_at']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editCategory(<?= htmlspecialchars(json_encode($category)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($category['usage_count'] == 0): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $category['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $category['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                                onclick="return confirm('Kategori durumunu değiştirmek istediğinizden emin misiniz?')">
                                            <i class="fas fa-<?= $category['is_active'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted" title="Kullanımda olan kategori durumu değiştirilemez">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Kategori Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create_category">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Kategori Adı *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kategori Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kategori Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Kategori Adı *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
            </form>
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

<script>
function editCategory(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description;
    
    const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    editModal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>