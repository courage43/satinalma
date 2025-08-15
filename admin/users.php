<?php
$page_title = 'Kullanıcı Yönetimi';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/lib/audit_log.php';

// Only allow system administrators
$auth->requireRole(['sistem_yoneticisi']);

$db = DatabaseHelper::getInstance();
$message = '';
$message_type = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_user':
                $userData = [
                    'username' => $_POST['username'],
                    'email' => $_POST['email'],
                    'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'phone' => $_POST['phone'] ?? null,
                    'role_id' => $_POST['role_id'],
                    'department_id' => $_POST['department_id'] ?? null,
                    'is_active' => 1
                ];
                
                $userId = $db->insert('users', $userData);
                
                audit()->log(
                    'user_create',
                    "New user created: {$userData['username']}",
                    ['table' => 'users', 'id' => $userId]
                );
                
                $message = 'Kullanıcı başarıyla oluşturuldu.';
                $message_type = 'success';
                break;
                
            case 'update_status':
                $userId = $_POST['user_id'];
                $isActive = $_POST['is_active'];
                
                $db->update(
                    'users',
                    ['is_active' => $isActive],
                    'id = ?',
                    [$userId]
                );
                
                audit()->log(
                    'user_update',
                    "User status updated: ID {$userId}",
                    ['table' => 'users', 'id' => $userId]
                );
                
                $message = 'Kullanıcı durumu güncellendi.';
                $message_type = 'success';
                break;
        }
        
    } catch (Exception $e) {
        $message = 'İşlem başarısız: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get all users with their roles and departments
$users = $db->fetchAll("
    SELECT u.*, r.display_name as role_name, d.name as department_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.created_at DESC
");

// Get roles and departments for dropdowns
$roles = $db->fetchAll("SELECT * FROM roles WHERE is_active = 1 ORDER BY display_name");
$departments = $db->fetchAll("SELECT * FROM departments WHERE is_active = 1 ORDER BY name");
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
            <h1 class="h2"><i class="fas fa-users me-2"></i>Kullanıcı Yönetimi</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Yeni Kullanıcı
            </button>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı Adı</th>
                                <th>Ad Soyad</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Departman</th>
                                <th>Durum</th>
                                <th>Oluşturma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($user['role_name'] ?? 'Rol Yok') ?></span>
                                </td>
                                <td><?= htmlspecialchars($user['department_name'] ?? 'Departman Yok') ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= DateHelper::formatTurkish($user['created_at']) ?></td>
                                <td>
                                    <?php if ($user['id'] != $auth->getCurrentUserId()): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $user['is_active'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                                    onclick="return confirm('Kullanıcı durumunu değiştirmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                                                <?= $user['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Kendi hesabınız</span>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Ad *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Soyad *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre *</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role_id" class="form-label">Rol *</label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <option value="">Rol seçiniz</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Departman</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">Departman seçiniz</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= $department['id'] ?>"><?= htmlspecialchars($department['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kullanıcı Oluştur</button>
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>