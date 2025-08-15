<?php
/**
 * Database Upgrade Script
 * Run this to add missing columns to existing database
 */

require_once 'config/env.php';
require_once 'lib/helpers.php';

// Force load environment
EnvConfig::load();

echo "Starting database upgrade...\n";

try {
    $db = DatabaseHelper::getInstance();
    
    // Check if purchase_requests table exists
    $tables = $db->fetchAll("SHOW TABLES LIKE 'purchase_requests'");
    
    if (empty($tables)) {
        echo "purchase_requests table does not exist. Please run init_system.php first.\n";
        exit(1);
    }
    
    echo "Checking purchase_requests table structure...\n";
    
    // Get current columns
    $columns = $db->fetchAll("SHOW COLUMNS FROM purchase_requests");
    $existingColumns = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $existingColumns) . "\n";
    
    // Add missing columns
    $requiredColumns = [
        'estimated_amount' => "ALTER TABLE purchase_requests ADD COLUMN estimated_amount DECIMAL(15,2) DEFAULT 0 AFTER urgency_level",
        'request_number' => "ALTER TABLE purchase_requests ADD COLUMN request_number VARCHAR(50) NOT NULL UNIQUE AFTER id",
        'justification' => "ALTER TABLE purchase_requests ADD COLUMN justification TEXT AFTER description"
    ];
    
    foreach ($requiredColumns as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            echo "Adding column: {$column}...\n";
            try {
                $db->execute($sql);
                echo "✅ Added column: {$column}\n";
            } catch (Exception $e) {
                echo "⚠️ Could not add {$column}: " . $e->getMessage() . "\n";
                
                // For request_number, if it fails due to uniqueness, try without UNIQUE
                if ($column === 'request_number' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "Trying to add request_number without UNIQUE constraint...\n";
                    $db->execute("ALTER TABLE purchase_requests ADD COLUMN request_number VARCHAR(50) AFTER id");
                    echo "✅ Added request_number column\n";
                    
                    // Update empty request_numbers
                    echo "Updating empty request numbers...\n";
                    $requests = $db->fetchAll("SELECT id FROM purchase_requests WHERE request_number IS NULL OR request_number = ''");
                    foreach ($requests as $request) {
                        $requestNumber = 'ST-' . date('Y') . '-' . sprintf('%06d', $request['id']);
                        $db->update(
                            'purchase_requests',
                            ['request_number' => $requestNumber],
                            'id = ?',
                            [$request['id']]
                        );
                    }
                    echo "✅ Updated request numbers\n";
                }
            }
        } else {
            echo "✅ Column {$column} already exists\n";
        }
    }
    
    // Update status column if needed
    echo "Checking status column...\n";
    $statusColumn = array_filter($columns, function($col) {
        return $col['Field'] === 'status';
    });
    
    if (!empty($statusColumn)) {
        $statusColumn = array_values($statusColumn)[0];
        if (strpos($statusColumn['Type'], 'sas_incelemede') === false) {
            echo "Updating status column enum values...\n";
            $db->execute("
                ALTER TABLE purchase_requests 
                MODIFY COLUMN status ENUM(
                    'taslak', 'sas_incelemede', 'gs_incelemede', 'teklif_toplama', 
                    'sak1_karari_bekliyor', 'sak2_karari_bekliyor', 'yk_fiyat_karari_bekliyor', 
                    'siparis_verildi', 'teslimat_bekliyor', 'kontrol_asamasinda', 
                    'fatura_bekliyor', 'odeme_asamasinda', 'tamamlandi', 
                    'red_edildi', 'iptal_edildi'
                ) DEFAULT 'sas_incelemede'
            ");
            echo "✅ Updated status column\n";
        }
    }
    
    // Check request_items table
    echo "Checking request_items table...\n";
    $itemTables = $db->fetchAll("SHOW TABLES LIKE 'request_items'");
    
    if (empty($itemTables)) {
        echo "Creating request_items table...\n";
        $db->execute("
            CREATE TABLE request_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                request_id INT NOT NULL,
                item_name VARCHAR(200) NOT NULL,
                description TEXT,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit VARCHAR(50) DEFAULT 'Adet',
                estimated_unit_price DECIMAL(15,2) DEFAULT 0,
                estimated_total_price DECIMAL(15,2) DEFAULT 0,
                specifications TEXT,
                brand_preference VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
                INDEX idx_request (request_id)
            )
        ");
        echo "✅ Created request_items table\n";
    } else {
        echo "✅ request_items table already exists\n";
    }
    
    echo "\n";
    echo "Database upgrade completed successfully!\n";
    echo "You can now create purchase requests without column errors.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>