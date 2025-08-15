<?php
$page_title = 'Taleplerim';
require_once 'includes/header.php';
require_once 'lib/helpers.php';

$db = DatabaseHelper::getInstance();
$user_id = $auth->getCurrentUserId();
$user_role = $auth->getCurrentUserRole();

// View specific request
$view_request_id = $_GET['view'] ?? null;

// Filtreleme
$status_filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

if ($view_request_id) {
    // Single request view
    $request = $db->fetchRow(
        "SELECT pr.*, u.first_name, u.last_name, u.email, u.phone,
                pc.name as category_name, d.name as department_name
         FROM purchase_requests pr
         LEFT JOIN users u ON pr.requester_id = u.id
         LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
         LEFT JOIN departments d ON u.department_id = d.id
         WHERE pr.id = ? AND (pr.requester_id = ? OR ? IN ('sistem_yoneticisi', 'satin_alma_sorumlusu', 'genel_sekreter'))",
        [$view_request_id, $user_id, $user_role]
    );
    
    if (!$request) {
        header('Location: my_requests.php');
        exit;
    }
    
    // Get request items
    $items = $db->fetchAll(
        "SELECT * FROM request_items WHERE request_id = ? ORDER BY id",
        [$view_request_id]
    );
    
    // Get workflow history
    $workflow_history = $db->fetchAll(
        "SELECT wt.*, u.first_name, u.last_name, wt.created_at as task_date
         FROM workflow_tasks wt
         LEFT JOIN users u ON wt.assigned_to = u.id
         WHERE wt.request_id = ?
         ORDER BY wt.created_at DESC",
        [$view_request_id]
    );
} else {
    // List view
    $query = "SELECT pr.*, pc.name as category_name,
                     u.first_name, u.last_name
              FROM purchase_requests pr
              LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
              LEFT JOIN users u ON pr.requester_id = u.id
              WHERE 1=1";
    
    $params = [];
    
    // User role based filtering
    if ($user_role == 'kullanici') {
        $query .= " AND pr.requester_id = ?";
        $params[] = $user_id;
    } elseif ($status_filter == 'pending_approval') {
        // Show requests that need approval from current user
        $query .= " AND pr.id IN (
            SELECT DISTINCT wt.request_id 
            FROM workflow_tasks wt 
            WHERE wt.assigned_to = ? AND wt.status = 'pending'
        )";
        $params[] = $user_id;
    }
    
    if ($status_filter && $status_filter != 'pending_approval') {
        $query .= " AND pr.status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $query .= " AND (pr.title LIKE ? OR pr.request_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY pr.created_at DESC";
    
    $requests = $db->fetchAll($query, $params);
}
?>

<?php if ($view_request_id): ?>
    <!-- Single Request View -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="fas fa-file-alt me-2"></i>Talep Detayı</h2>
                <a href="my_requests.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                </a>
            </div>
        </div>
    </div>

    <!-- Request Details -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Talep Bilgileri</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="150"><strong>Talep No:</strong></td>
                            <td><?= htmlspecialchars($request['request_number']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Başlık:</strong></td>
                            <td><?= htmlspecialchars($request['title']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Açıklama:</strong></td>
                            <td><?= nl2br(htmlspecialchars($request['description'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Gerekçe:</strong></td>
                            <td><?= nl2br(htmlspecialchars($request['justification'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Kategori:</strong></td>
                            <td><?= htmlspecialchars($request['category_name'] ?? 'Belirtilmemiş') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Aciliyet:</strong></td>
                            <td>
                                <span class="badge bg-<?= StatusHelper::getUrgencyColor($request['urgency_level']) ?>">
                                    <?= StatusHelper::getUrgencyText($request['urgency_level']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Tahmini Tutar:</strong></td>
                            <td><?= number_format($request['estimated_amount'], 2) ?> TL</td>
                        </tr>
                        <tr>
                            <td><strong>Talep Tarihi:</strong></td>
                            <td><?= DateHelper::formatTurkish($request['created_at']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Durum:</strong></td>
                            <td>
                                <span class="badge bg-<?= StatusHelper::getStatusColor($request['status']) ?>">
                                    <?= StatusHelper::getStatusText($request['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Request Items -->
            <?php if (!empty($items)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Talep Edilen Kalemler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Kalem Adı</th>
                                    <th>Açıklama</th>
                                    <th>Miktar</th>
                                    <th>Birim</th>
                                    <th>Birim Fiyat</th>
                                    <th>Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_amount = 0;
                                foreach ($items as $item): 
                                    $item_total = $item['quantity'] * $item['estimated_unit_price'];
                                    $total_amount += $item_total;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= number_format($item['quantity'], 2) ?></td>
                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                    <td><?= number_format($item['estimated_unit_price'], 2) ?> TL</td>
                                    <td><?= number_format($item_total, 2) ?> TL</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th colspan="5">Toplam Tutar:</th>
                                    <th><?= number_format($total_amount, 2) ?> TL</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Requester Info -->
            <div class="card">
                <div class="card-header">
                    <h5>Talep Eden Bilgileri</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Ad Soyad:</strong></td>
                            <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>E-posta:</strong></td>
                            <td><?= htmlspecialchars($request['email']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Telefon:</strong></td>
                            <td><?= htmlspecialchars($request['phone'] ?? 'Belirtilmemiş') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Departman:</strong></td>
                            <td><?= htmlspecialchars($request['department_name'] ?? 'Belirtilmemiş') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Workflow History -->
            <?php if (!empty($workflow_history)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5>İşlem Geçmişi</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($workflow_history as $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?= $history['status'] == 'completed' ? 'success' : 'warning' ?>"></div>
                            <div class="timeline-content">
                                <h6><?= htmlspecialchars($history['task_type']) ?></h6>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) ?>
                                </p>
                                <small class="text-muted">
                                    <?= DateHelper::formatTurkish($history['task_date']) ?>
                                </small>
                                <?php if ($history['notes']): ?>
                                <p class="mt-1"><?= nl2br(htmlspecialchars($history['notes'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- List View -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="fas fa-file-alt me-2"></i>
                    <?php if ($status_filter == 'pending_approval'): ?>
                        Onayınızı Bekleyen Talepler
                    <?php else: ?>
                        Taleplerim
                    <?php endif; ?>
                </h2>
                <a href="new_request.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Yeni Talep
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Durum Filtresi</label>
                            <select name="filter" class="form-select">
                                <option value="">Tüm Durumlar</option>
                                <?php if (in_array($user_role, ['satin_alma_sorumlusu', 'genel_sekreter'])): ?>
                                <option value="pending_approval" <?= $status_filter == 'pending_approval' ? 'selected' : '' ?>>Onayınızı Bekleyenler</option>
                                <?php endif; ?>
                                <option value="sas_incelemede" <?= $status_filter == 'sas_incelemede' ? 'selected' : '' ?>>SAS İncelemede</option>
                                <option value="gs_incelemede" <?= $status_filter == 'gs_incelemede' ? 'selected' : '' ?>>GS İncelemede</option>
                                <option value="teklif_toplama" <?= $status_filter == 'teklif_toplama' ? 'selected' : '' ?>>Teklif Toplama</option>
                                <option value="tamamlandi" <?= $status_filter == 'tamamlandi' ? 'selected' : '' ?>>Tamamlandı</option>
                                <option value="red_edildi" <?= $status_filter == 'red_edildi' ? 'selected' : '' ?>>Reddedildi</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Arama</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Talep numarası veya başlık ara..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Talep bulunamadı.</p>
                            <a href="new_request.php" class="btn btn-primary">İlk Talebi Oluştur</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Talep No</th>
                                        <th>Başlık</th>
                                        <th>Kategori</th>
                                        <?php if ($user_role != 'kullanici'): ?>
                                        <th>Talep Eden</th>
                                        <?php endif; ?>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($request['request_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($request['title']) ?></td>
                                        <td><?= htmlspecialchars($request['category_name'] ?? 'Belirtilmemiş') ?></td>
                                        <?php if ($user_role != 'kullanici'): ?>
                                        <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></td>
                                        <?php endif; ?>
                                        <td><?= number_format($request['estimated_amount'], 2) ?> TL</td>
                                        <td>
                                            <span class="badge bg-<?= StatusHelper::getStatusColor($request['status']) ?>">
                                                <?= StatusHelper::getStatusText($request['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= DateHelper::formatTurkish($request['created_at']) ?></td>
                                        <td>
                                            <a href="?view=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (in_array($user_role, ['satin_alma_sorumlusu', 'genel_sekreter']) && $status_filter == 'pending_approval'): ?>
                                            <a href="approve.php?request_id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 15px;
    width: 2px;
    height: calc(100% + 5px);
    background: #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    border-left: 3px solid #dee2e6;
}
</style>

<?php require_once 'includes/footer.php'; ?>