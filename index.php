<?php
/**
 * Main Landing Page
 * Redirects authenticated users to dashboard, shows login for guests
 */

require_once 'config/auth.php';

$auth = Auth::getInstance();

// If user is already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Show login page
$page_title = 'Satın Alma Talep Sistemi - Giriş';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 20px;
        }
        .login-body {
            padding: 40px;
        }
        .logo-section h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .logo-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e1e8ed;
            padding: 12px 15px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .system-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="login-card">
                        <div class="row g-0">
                            <div class="col-lg-5">
                                <div class="login-header h-100 d-flex flex-column justify-content-center">
                                    <div class="logo-section">
                                        <i class="fas fa-shopping-cart fa-4x mb-4"></i>
                                        <h1>Satın Alma</h1>
                                        <h2>Talep Sistemi</h2>
                                        <p class="mt-3">Kütahya Ticaret ve Sanayi Odası</p>
                                        <p>Modern, Güvenli, Hızlı</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="login-body">
                                    <h3 class="text-center mb-4">Sisteme Giriş</h3>
                                    
                                    <?php if (isset($_GET['error'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Kullanıcı adı veya şifre hatalı!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_GET['message'])): ?>
                                        <div class="alert alert-info alert-dismissible fade show">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <?= htmlspecialchars($_GET['message']) ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form action="login.php" method="POST">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">
                                                <i class="fas fa-user me-2"></i>Kullanıcı Adı
                                            </label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   required autofocus placeholder="Kullanıcı adınızı girin">
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="password" class="form-label">
                                                <i class="fas fa-lock me-2"></i>Şifre
                                            </label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   required placeholder="Şifrenizi girin">
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-login btn-lg">
                                                <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <div class="system-info">
                                        <h6><i class="fas fa-info-circle me-2"></i>Sistem Özellikleri</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><i class="fas fa-check text-success me-2"></i>Otomatik onay süreçleri</li>
                                            <li><i class="fas fa-check text-success me-2"></i>E-posta ve Telegram bildirimleri</li>
                                            <li><i class="fas fa-check text-success me-2"></i>N8N workflow entegrasyonu</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Güvenli token bazlı onaylar</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            Güvenli bağlantı ile korunmaktadır
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Info -->
            <div class="text-center mt-4">
                <p class="text-white">
                    <i class="fas fa-globe me-2"></i>
                    <strong>satinalma.kutahyam.tr</strong>
                </p>
                <p class="text-white-50 small">
                    © <?= date('Y') ?> Kaan Varol - Tüm hakları saklıdır
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>