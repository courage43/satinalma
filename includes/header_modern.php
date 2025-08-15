<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/security.php';
require_once dirname(__DIR__) . '/config/env.php';

$auth->requireLogin();
?>
<!DOCTYPE html>
<html lang="tr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? EnvConfig::get('APP_NAME', 'Satın Alma Talep Sistemi') ?></title>
    
    <!-- CSRF Token -->
    <?= csrf_meta() ?>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        success: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        },
                        warning: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                        },
                        danger: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            200: '#fecaca',
                            300: '#fca5a5',
                            400: '#f87171',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            800: '#991b1b',
                            900: '#7f1d1d',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Heroicons -->
    <script src="https://unpkg.com/@heroicons/react@2.0.18/24/outline/index.js" type="module"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
        [x-cloak] { display: none !important; }
        
        .notification-enter {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .notification-enter-active {
            transform: translateX(0);
            opacity: 1;
            transition: all 0.3s ease-out;
        }
        
        .notification-leave-active {
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease-in;
        }
    </style>
</head>
<body class="h-full font-sans">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200" x-data="{ mobileMenuOpen: false, userMenuOpen: false }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <!-- Logo and Main Navigation -->
                <div class="flex">
                    <div class="flex flex-shrink-0 items-center">
                        <a href="dashboard.php" class="flex items-center">
                            <div class="h-8 w-8 bg-primary-600 rounded-lg flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <span class="ml-3 text-xl font-semibold text-gray-900"><?= EnvConfig::get('APP_NAME', 'Satın Alma') ?></span>
                        </a>
                    </div>
                    
                    <!-- Desktop Navigation -->
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-8">
                        <a href="dashboard.php" class="border-primary-500 text-gray-900 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            </svg>
                            Ana Sayfa
                        </a>
                        
                        <?php if ($auth->hasRole(['kullanici', 'ilgili_birim_personeli', 'satin_alma_sorumlusu', 'genel_sekreter'])): ?>
                        <a href="new_request.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Yeni Talep
                        </a>
                        
                        <a href="my_requests.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Taleplerim
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasRole(['satin_alma_sorumlusu', 'genel_sekreter', 'sak1_uyesi', 'sak2_uyesi', 'yonetim_kurulu_uyesi'])): ?>
                        <a href="approval_queue.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium relative">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            Onaylar
                            <!-- Notification Badge -->
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-danger-500 rounded-full">3</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right side menu -->
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <!-- Notifications -->
                    <button type="button" class="relative rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <span class="sr-only">Bildirimleri görüntüle</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 -5v-3a6 6 0 1 0 -12 0v3l-5 5h5m7 0v1a3 3 0 0 1 -6 0v-1m6 0H9"></path>
                        </svg>
                        <span class="absolute -top-1 -right-1 block h-2 w-2 rounded-full bg-danger-400 ring-2 ring-white"></span>
                    </button>
                    
                    <!-- Profile dropdown -->
                    <div class="relative ml-3" x-data="{ open: false }">
                        <div>
                            <button @click="open = !open" type="button" class="flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2" id="user-menu-button">
                                <span class="sr-only">Kullanıcı menüsünü aç</span>
                                <div class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center">
                                    <span class="text-sm font-medium text-primary-700"><?= strtoupper(substr($auth->getCurrentUserName(), 0, 2)) ?></span>
                                </div>
                                <span class="ml-3 text-sm font-medium text-gray-700"><?= $auth->getCurrentUserName() ?></span>
                                <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" x-cloak>
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm text-gray-500">Rol</p>
                                <p class="text-sm font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $auth->getCurrentUserRole())) ?></p>
                            </div>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Profil Ayarları</a>
                            <a href="notifications.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Bildirimler</a>
                            <?php if ($auth->hasRole(['sistem_yoneticisi'])): ?>
                            <a href="admin/users.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Yönetim Paneli</a>
                            <?php endif; ?>
                            <div class="border-t border-gray-100">
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Çıkış Yap</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <span class="sr-only">Ana menüyü aç</span>
                        <svg x-show="!mobileMenuOpen" class="block h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        <svg x-show="mobileMenuOpen" class="block h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div x-show="mobileMenuOpen" class="sm:hidden" x-cloak>
            <div class="space-y-1 pb-3 pt-2">
                <a href="dashboard.php" class="border-primary-500 bg-primary-50 text-primary-700 block border-l-4 py-2 pl-3 pr-4 text-base font-medium">Ana Sayfa</a>
                <a href="new_request.php" class="border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 block border-l-4 py-2 pl-3 pr-4 text-base font-medium">Yeni Talep</a>
                <a href="my_requests.php" class="border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 block border-l-4 py-2 pl-3 pr-4 text-base font-medium">Taleplerim</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="min-h-full">
        <main class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">