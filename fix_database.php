<?php
/**
 * Database Fix Script
 * Fixes database column issues and missing columns
 */

require_once 'config/env.php';
require_once 'lib/helpers.php';

// Force load environment
EnvConfig::load();

echo "Starting database fix...\n";

try {
    $db = DatabaseHelper::getInstance();
    
    // Get current columns in purchase_requests table
    $columns = $db->fetchAll("SHOW COLUMNS FROM purchase_requests");
    $existingColumns = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $existingColumns) . "\n";
    
    // Remove old budget_estimate column if exists
    if (in_array('budget_estimate', $existingColumns)) {
        echo "Removing old budget_estimate column...\n";
        $db->execute("ALTER TABLE purchase_requests DROP COLUMN budget_estimate");
        echo "✅ Removed budget_estimate column\n";
    }
    
    // Ensure estimated_amount column exists with correct type
    if (!in_array('estimated_amount', $existingColumns)) {
        echo "Adding estimated_amount column...\n";
        $db->execute("ALTER TABLE purchase_requests ADD COLUMN estimated_amount DECIMAL(15,2) DEFAULT 0.00 AFTER urgency_level");
        echo "✅ Added estimated_amount column\n";
    } else {
        echo "Updating estimated_amount column type...\n";
        $db->execute("ALTER TABLE purchase_requests MODIFY COLUMN estimated_amount DECIMAL(15,2) DEFAULT 0.00");
        echo "✅ Updated estimated_amount column type\n";
    }
    
    // Ensure justification column exists
    if (!in_array('justification', $existingColumns)) {
        echo "Adding justification column...\n";
        $db->execute("ALTER TABLE purchase_requests ADD COLUMN justification TEXT AFTER description");
        echo "✅ Added justification column\n";
    }
    
    // Ensure request_number column exists
    if (!in_array('request_number', $existingColumns)) {
        echo "Adding request_number column...\n";
        $db->execute("ALTER TABLE purchase_requests ADD COLUMN request_number VARCHAR(50) AFTER id");
        echo "✅ Added request_number column\n";
        
        // Update existing records with request numbers
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
        echo "✅ Updated existing request numbers\n";
    }
    
    // Ensure requested_delivery_date is nullable
    $deliveryDateColumn = array_filter($columns, function($col) {
        return $col['Field'] === 'requested_delivery_date';
    });
    
    if (!empty($deliveryDateColumn)) {
        $deliveryDateColumn = array_values($deliveryDateColumn)[0];
        if ($deliveryDateColumn['Null'] !== 'YES') {
            echo "Making requested_delivery_date nullable...\n";
            $db->execute("ALTER TABLE purchase_requests MODIFY COLUMN requested_delivery_date DATE NULL");
            echo "✅ Made requested_delivery_date nullable\n";
        }
    } else {
        echo "Adding requested_delivery_date column...\n";
        $db->execute("ALTER TABLE purchase_requests ADD COLUMN requested_delivery_date DATE NULL AFTER estimated_amount");
        echo "✅ Added requested_delivery_date column\n";
    }
    
    // Update empty estimated_amount values to 0
    echo "Updating empty estimated_amount values...\n";
    $db->execute("UPDATE purchase_requests SET estimated_amount = 0.00 WHERE estimated_amount IS NULL OR estimated_amount = ''");
    echo "✅ Updated empty estimated_amount values\n";
    
    echo "\nDatabase fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>