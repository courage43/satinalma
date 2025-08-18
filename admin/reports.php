<?php
$page_title = 'Raporlar ve İstatistikler';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/lib/helpers.php';

// Only allow system administrators
$auth->requireRole(['sistem_yoneticisi']);

$db = DatabaseHelper::getInstance();

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// General statistics
$generalStats = [
    'total_requests' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_requests")['count'],
    'total_users' => $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'total_categories' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_categories WHERE is_active = 1")['count'],
    'pending_requests' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_requests WHERE status IN ('sas_incelemede', 'gs_incelemede')")['count'],
    'completed_requests' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_requests WHERE status = 'tamamlandi'")['count'],
    'rejected_requests' => $db->fetchRow("SELECT COUNT(*) as count FROM purchase_requests WHERE status = 'red_edildi'")['count']
];

// Requests by status for selected date range
$statusStats = $db->fetchAll("
    SELECT status, COUNT(*) as count,
           SUM(estimated_amount) as total_amount
    FROM purchase_requests 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
    ORDER BY count DESC
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Requests by category for selected date range
$categoryStats = $db->fetchAll("
    SELECT pc.name as category_name, COUNT(pr.id) as count,
           SUM(pr.estimated_amount) as total_amount
    FROM purchase_categories pc
    LEFT JOIN purchase_requests pr ON pc.id = pr.category_id 
        AND pr.created_at BETWEEN ? AND ?
    GROUP BY pc.id, pc.name
    HAVING count > 0
    ORDER BY count DESC
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Requests by user/department for selected date range
$userStats = $db->fetchAll("
    SELECT u.first_name, u.last_name, d.name as department_name,
           COUNT(pr.id) as count,
           SUM(pr.estimated_amount) as total_amount
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN purchase_requests pr ON u.id = pr.requester_id 
        AND pr.created_at BETWEEN ? AND ?
    GROUP BY u.id
    HAVING count > 0
    ORDER BY count DESC
    LIMIT 10
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Monthly trend data for the last 12 months
$monthlyTrend = $db->fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as count,
           SUM(estimated_amount) as total_amount
    FROM purchase_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

// Average approval times
$approvalTimes = $db->fetchAll("
    SELECT AVG(TIMESTAMPDIFF(HOUR, pr.created_at, wt.completed_at)) as avg_hours,
           pr.status
    FROM purchase_requests pr
    JOIN workflow_tasks wt ON pr.id = wt.request_id
    WHERE wt.status = 'completed' 
        AND wt.completed_at IS NOT NULL
        AND pr.created_at BETWEEN ? AND ?
    GROUP BY pr.status
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Raporlar ve İstatistikler</h1>
        </div>
    </div>
</div>

<!-- Date Filter -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filtrele
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- General Statistics -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?= $generalStats['total_requests'] ?></h3>
                <p class="mb-0">Toplam Talep</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?= $generalStats['total_users'] ?></h3>
                <p class="mb-0">Aktif Kullanıcı</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?= $generalStats['total_categories'] ?></h3>
                <p class="mb-0">Kategori</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?= $generalStats['pending_requests'] ?></h3>
                <p class="mb-0">Beklemede</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?= $generalStats['completed_requests'] ?></h3>
                <p class="mb-0">Tamamlanan</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger"><?= $generalStats['rejected_requests'] ?></h3>
                <p class="mb-0">Reddedilen</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Status Distribution -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Durum Dağılımı (<?= date('d.m.Y', strtotime($start_date)) ?> - <?= date('d.m.Y', strtotime($end_date)) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Durum</th>
                                <th>Adet</th>
                                <th>Toplam Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusStats as $stat): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= StatusHelper::getStatusColor($stat['status']) ?>">
                                        <?= StatusHelper::getStatusText($stat['status']) ?>
                                    </span>
                                </td>
                                <td><?= $stat['count'] ?></td>
                                <td><?= number_format($stat['total_amount'], 2) ?> TL</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Kategori Dağılımı</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Adet</th>
                                <th>Toplam Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryStats as $stat): ?>
                            <tr>
                                <td><?= htmlspecialchars($stat['category_name']) ?></td>
                                <td><?= $stat['count'] ?></td>
                                <td><?= number_format($stat['total_amount'], 2) ?> TL</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Users -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>En Aktif Kullanıcılar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Departman</th>
                                <th>Talep Sayısı</th>
                                <th>Toplam Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userStats as $stat): ?>
                            <tr>
                                <td><?= htmlspecialchars($stat['first_name'] . ' ' . $stat['last_name']) ?></td>
                                <td><?= htmlspecialchars($stat['department_name'] ?? 'Belirtilmemiş') ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $stat['count'] ?></span>
                                </td>
                                <td><?= number_format($stat['total_amount'], 2) ?> TL</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trend -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Aylık Trend (Son 12 Ay)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ay</th>
                                <th>Adet</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($monthlyTrend, -6) as $trend): ?>
                            <tr>
                                <td><?= date('M Y', strtotime($trend['month'] . '-01')) ?></td>
                                <td><?= $trend['count'] ?></td>
                                <td><?= number_format($trend['total_amount'] / 1000, 1) ?>K</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5>Rapor Dışa Aktarma</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <button class="btn btn-success w-100" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Excel'e Aktar
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-danger w-100" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-2"></i>PDF'e Aktar
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-info w-100" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Yazdır
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="../dashboard.php" class="btn btn-secondary w-100">
                            <i class="fas fa-arrow-left me-2"></i>Ana Sayfa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Create a simple CSV export
    const data = [
        ['Tarih Aralığı', '<?= $start_date ?>', '<?= $end_date ?>'],
        [''],
        ['Genel İstatistikler'],
        ['Toplam Talep', '<?= $generalStats['total_requests'] ?>'],
        ['Aktif Kullanıcı', '<?= $generalStats['total_users'] ?>'],
        ['Bekleyen Talepler', '<?= $generalStats['pending_requests'] ?>'],
        ['Tamamlanan Talepler', '<?= $generalStats['completed_requests'] ?>'],
        ['Reddedilen Talepler', '<?= $generalStats['rejected_requests'] ?>'],
        [''],
        ['Durum Dağılımı'],
        ['Durum', 'Adet', 'Toplam Tutar']
    ];
    
    <?php foreach ($statusStats as $stat): ?>
    data.push(['<?= StatusHelper::getStatusText($stat['status']) ?>', '<?= $stat['count'] ?>', '<?= $stat['total_amount'] ?>']);
    <?php endforeach; ?>
    
    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    data.forEach(function(rowArray) {
        let row = rowArray.join(",");
        csvContent += row + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "satin_alma_raporu_<?= date('Y-m-d') ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    window.print();
}
</script>

<style>
@media print {
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    .btn {
        display: none !important;
    }
}
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>