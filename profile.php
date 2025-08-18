<?php
$page_title = 'Profil Ayarları';
require_once 'includes/header.php';
require_once 'lib/helpers.php';

$db = DatabaseHelper::getInstance();
$user_id = $auth->getCurrentUserId();
$message = '';
$message_type = '';

// Get user details
$user = $db->fetchRow(
    "SELECT u.*, r.display_name as role_name, d.name as department_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN departments d ON u.department_id = d.id
     WHERE u.id = ?",
    [$user_id]
);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        SecurityManager::getInstance()->validateCSRF($_POST['csrf_token'] ?? '');
        
        $updateData = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? '')
        ];
        
        // Check if email is unique (excluding current user)
        $existingUser = $db->fetchRow(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$updateData['email'], $user_id]
        );
        
        if ($existingUser) {
            throw new Exception('Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
        }
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception('Mevcut şifrenizi girmeniz gerekiyor.');
            }
            
            if (!password_verify($_POST['current_password'], $user['password'])) {
                throw new Exception('Mevcut şifre hatalı.');
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception('Yeni şifreler eşleşmiyor.');
            }
            
            if (strlen($_POST['new_password']) < 6) {
                throw new Exception('Şifre en az 6 karakter olmalıdır.');
            }
            
            $updateData['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }
        
        $db->update('users', $updateData, 'id = ?', [$user_id]);
        
        // Log the change
        audit()->log(
            'profile_update',
            'User profile updated',
            ['user_id' => $user_id, 'updated_fields' => array_keys($updateData)]
        );
        
        $message = 'Profil bilgileriniz başarıyla güncellendi.';
        $message_type = 'success';
        
        // Refresh user data
        $user = $db->fetchRow(
            "SELECT u.*, r.display_name as role_name, d.name as department_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = ?",
            [$user_id]
        );
        
    } catch (Exception $e) {
        $message = 'Profil güncellenirken hata oluştu: ' . $e->getMessage();
        $message_type = 'danger';
    }
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-user-cog me-2"></i>Profil Ayarları</h2>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Kişisel Bilgiler</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_input() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Ad *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($user['first_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Soyad *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($user['last_name']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Şifre Değiştir <small class="text-muted">(isteğe bağlı)</small></h6>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Geri Dön
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Hesap Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Kullanıcı Adı:</strong></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Rol:</strong></td>
                        <td>
                            <span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Departman:</strong></td>
                        <td><?= htmlspecialchars($user['department_name'] ?? 'Belirtilmemiş') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Hesap Durumu:</strong></td>
                        <td>
                            <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                <?= $user['is_active'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Kayıt Tarihi:</strong></td>
                        <td><?= DateHelper::formatTurkish($user['created_at']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Son Güncelleme:</strong></td>
                        <td><?= DateHelper::formatTurkish($user['updated_at']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5>Şifre Güvenliği</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-check text-success me-2"></i>En az 6 karakter</li>
                    <li><i class="fas fa-check text-success me-2"></i>Büyük ve küçük harf</li>
                    <li><i class="fas fa-check text-success me-2"></i>Sayı içermeli</li>
                    <li><i class="fas fa-check text-warning me-2"></i>Özel karakter önerilen</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('new_password').addEventListener('input', function() {
    const newPass = this.value;
    const confirmPass = document.getElementById('confirm_password');
    const currentPass = document.getElementById('current_password');
    
    if (newPass.length > 0) {
        currentPass.required = true;
        confirmPass.required = true;
    } else {
        currentPass.required = false;
        confirmPass.required = false;
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    
    if (newPass !== confirmPass) {
        this.setCustomValidity('Şifreler eşleşmiyor');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>