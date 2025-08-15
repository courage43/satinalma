<?php
/**
 * System Health Check
 * Bu dosya sistemin çalışır durumda olup olmadığını kontrol eder
 */

header('Content-Type: application/json');

$health = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '2.0',
    'checks' => []
];

try {
    // PHP version check
    $health['checks']['php_version'] = [
        'status' => version_compare(PHP_VERSION, '8.0', '>=') ? 'OK' : 'FAIL',
        'value' => PHP_VERSION,
        'required' => '8.0+'
    ];

    // Environment file check
    $health['checks']['env_file'] = [
        'status' => file_exists('.env') ? 'OK' : 'FAIL',
        'message' => file_exists('.env') ? 'Environment file exists' : 'Environment file missing'
    ];

    // Database connection check
    if (file_exists('.env')) {
        require_once 'config/env.php';
        EnvConfig::load();
        
        try {
            require_once 'lib/helpers.php';
            $db = DatabaseHelper::getInstance();
            
            // Test simple query
            $result = $db->fetchRow("SELECT 1 as test");
            
            $health['checks']['database'] = [
                'status' => $result && $result['test'] == 1 ? 'OK' : 'FAIL',
                'message' => 'Database connection successful'
            ];
            
            // Check important tables
            $tables = ['users', 'purchase_requests', 'workflow_tasks'];
            $missing_tables = [];
            
            foreach ($tables as $table) {
                $exists = $db->fetchRow("SHOW TABLES LIKE ?", [$table]);
                if (!$exists) {
                    $missing_tables[] = $table;
                }
            }
            
            $health['checks']['tables'] = [
                'status' => empty($missing_tables) ? 'OK' : 'FAIL',
                'message' => empty($missing_tables) ? 'All required tables exist' : 'Missing tables: ' . implode(', ', $missing_tables)
            ];
            
        } catch (Exception $e) {
            $health['checks']['database'] = [
                'status' => 'FAIL',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    } else {
        $health['checks']['database'] = [
            'status' => 'SKIP',
            'message' => 'Skipped - no environment file'
        ];
    }

    // Directory permissions check
    $directories = ['uploads', 'logs', 'config'];
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $health['checks']['dir_' . $dir] = [
                'status' => is_writable($dir) ? 'OK' : 'WARN',
                'message' => is_writable($dir) ? 'Directory writable' : 'Directory not writable'
            ];
        }
    }

    // PHP extensions check
    $required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    $health['checks']['php_extensions'] = [
        'status' => empty($missing_extensions) ? 'OK' : 'FAIL',
        'message' => empty($missing_extensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missing_extensions)
    ];

    // Check if any critical errors
    $critical_errors = array_filter($health['checks'], function($check) {
        return $check['status'] === 'FAIL';
    });

    if (!empty($critical_errors)) {
        $health['status'] = 'ERROR';
        http_response_code(500);
    }

} catch (Exception $e) {
    $health['status'] = 'ERROR';
    $health['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>