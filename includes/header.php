<?php
// Backward compatibility header - redirects to modern system
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/security.php';
require_once dirname(__DIR__) . '/config/env.php';

$auth->requireLogin();

// Auto-migrate old files to modern header
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$modernFiles = ['new_request.php', 'approve.php'];

// If this is a modern file, redirect to modern header
if (in_array($currentFile, $modernFiles)) {
    include 'header_modern.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? EnvConfig::get('APP_NAME', 'Satın Alma Talep Sistemi') ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSRF Token -->
    <?= csrf_meta() ?>
    
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 240px;
            padding-top: 70px;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                top: 5rem;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="dashboard.php">
            <i class="fas fa-shopping-cart me-2"></i>
            <?= EnvConfig::get('APP_NAME', 'Satın Alma Sistemi') ?>
        </a>
        
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <div class="dropdown">
                    <a class="nav-link px-3 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?= $auth->getCurrentUserName() ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted"><?= ucfirst(str_replace('_', ' ', $auth->getCurrentUserRole())) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Ana Sayfa
                            </a>
                        </li>
                        
                        <?php if ($auth->hasRole(['kullanici', 'ilgili_birim_personeli', 'satin_alma_sorumlusu', 'genel_sekreter'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'new_request.php' ? 'active' : '' ?>" href="new_request.php">
                                <i class="fas fa-plus-circle me-2"></i>
                                Yeni Talep
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'my_requests.php' ? 'active' : '' ?>" href="my_requests.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Taleplerim
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasRole(['satin_alma_sorumlusu', 'genel_sekreter', 'sak1_uyesi', 'sak2_uyesi', 'yonetim_kurulu_uyesi'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'approval_queue.php' ? 'active' : '' ?>" href="approval_queue.php">
                                <i class="fas fa-tasks me-2"></i>
                                Onay Kuyruğu
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasRole(['sistem_yoneticisi'])): ?>
                        <li class="nav-item mt-3">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>Yönetim</span>
                            </h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/users.php">
                                <i class="fas fa-users me-2"></i>
                                Kullanıcı Yönetimi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/settings.php">
                                <i class="fas fa-cog me-2"></i>
                                Sistem Ayarları
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show mt-3">
                        <?= $_SESSION['flash_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>