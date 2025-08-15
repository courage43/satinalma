<?php
$page_title = 'Ana Sayfa - Dashboard';
require_once 'includes/header.php';
require_once 'lib/helpers.php';

$db = DatabaseHelper::getInstance();
$user_id = $auth->getCurrentUserId();
$user_role = $auth->getCurrentUserRole();

// İstatistikler
$stats = [];

// Kullanıcının talepleri
if ($user_role == 'kullanici') {
    $stats = $db->fetchRow(
        "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'sas_incelemede' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ('sas_incelemede', 'gs_incelemede') THEN 1 ELSE 0 END) as in_approval,
            SUM(CASE WHEN status = 'teklif_toplama' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'red_edildi' THEN 1 ELSE 0 END) as rejected
        FROM purchase_requests WHERE requester_id = ?",
        [$user_id]
    );
}

// SAS ve GS için onay bekleyen talepler
if (in_array($user_role, ['satin_alma_sorumlusu', 'genel_sekreter'])) {
    $approval_stats = $db->fetchRow(
        "SELECT COUNT(*) as pending_approvals
         FROM workflow_tasks wt
         WHERE wt.assigned_to = ? AND wt.status = 'pending'",
        [$user_id]
    );
}

// Son talepler
$recent_requests_query = "SELECT pr.*, u.first_name, u.last_name, pc.name as category_name
                         FROM purchase_requests pr
                         LEFT JOIN users u ON pr.requester_id = u.id
                         LEFT JOIN purchase_categories pc ON pr.category_id = pc.id
                         WHERE 1=1";

if ($user_role == 'kullanici') {
    $recent_requests_query .= " AND pr.requester_id = ?";
    $params = [$user_id];
} else {
    $params = [];
}

$recent_requests_query .= " ORDER BY pr.created_at DESC LIMIT 10";
$recent_requests = $db->fetchAll($recent_requests_query, $params);

// Status renkleri - Use helper class
function getStatusColor($status) {
    return StatusHelper::getStatusColor($status);
}

function getStatusText($status) {
    return StatusHelper::getStatusText($status);
}
?>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <h5><i class="fas fa-user-circle me-2"></i>Hoş Geldiniz, <?= $auth->getCurrentUserName() ?>!</h5>
            <p class="mb-0">Rol: <strong><?= ucfirst(str_replace('_', ' ', $user_role)) ?></strong></p>
        </div>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <?php if ($user_role == 'kullanici'): ?>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['total_requests'] ?? 0 ?></h4>
                            <p class="mb-0">Toplam Talep</p>
                        </div>
                        <div><i class="fas fa-file-alt fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= ($stats['pending'] ?? 0) + ($stats['in_approval'] ?? 0) ?></h4>
                            <p class="mb-0">Onay Bekleyen</p>
                        </div>
                        <div><i class="fas fa-clock fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['approved'] ?? 0 ?></h4>
                            <p class="mb-0">Onaylanan</p>
                        </div>
                        <div><i class="fas fa-check fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['rejected'] ?? 0 ?></h4>
                            <p class="mb-0">Reddedilen</p>
                        </div>
                        <div><i class="fas fa-times fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (in_array($user_role, ['satin_alma_sorumlusu', 'genel_sekreter'])): ?>
        <div class="col-md-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $approval_stats['pending_approvals'] ?? 0 ?></h4>
                            <p class="mb-0">Onayınızı Bekleyen Talepler</p>
                        </div>
                        <div><i class="fas fa-tasks fa-2x"></i></div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="my_requests.php?filter=pending_approval" class="text-white">
                        Detayları Gör <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Hızlı İşlemler -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt me-2"></i>Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="new_request.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-plus-circle me-2"></i>Yeni Talep Oluştur
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="my_requests.php" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-file-alt me-2"></i>Taleplerim
                        </a>
                    </div>
                    <?php if (in_array($user_role, ['satin_alma_sorumlusu', 'genel_sekreter'])): ?>
                    <div class="col-md-3">
                        <a href="my_requests.php?filter=pending_approval" class="btn btn-warning w-100 mb-2">
                            <i class="fas fa-tasks me-2"></i>Onay Bekleyenler
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($user_role == 'sistem_yoneticisi'): ?>
                    <div class="col-md-3">
                        <a href="admin/users.php" class="btn btn-secondary w-100 mb-2">
                            <i class="fas fa-users-cog me-2"></i>Kullanıcı Yönetimi
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="admin/settings.php" class="btn btn-dark w-100 mb-2">
                            <i class="fas fa-cog me-2"></i>Sistem Ayarları
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="admin/n8n_setup.php" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-robot me-2"></i>N8N Kurulumu
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Son Talepler -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>Son Talepler</h5>
                <a href="my_requests.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_requests)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>Henüz talep bulunmuyor.</p>
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
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td><strong><?= $request['request_number'] ?></strong></td>
                                    <td><?= htmlspecialchars($request['title']) ?></td>
                                    <td><?= $request['category_name'] ?></td>
                                    <?php if ($user_role != 'kullanici'): ?>
                                    <td><?= $request['first_name'] . ' ' . $request['last_name'] ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($request['status']) ?>">
                                            <?= getStatusText($request['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <a href="my_requests.php?view=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
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

<?php require_once 'includes/footer.php'; ?>