<?php
/**
 * System Initialization Script
 * Run this once to set up missing tables and basic data
 */

require_once 'config/env.php';
require_once 'lib/helpers.php';

// Force load environment
EnvConfig::load();

echo "Starting system initialization...\n";

try {
    $db = DatabaseHelper::getInstance();
    
    // Create purchase_categories table if not exists
    $db->execute("
        CREATE TABLE IF NOT EXISTS purchase_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default categories if empty
    $categoryCount = $db->fetchRow("SELECT COUNT(*) as count FROM purchase_categories")['count'];
    
    if ($categoryCount == 0) {
        echo "Inserting default categories...\n";
        
        $defaultCategories = [
            ['name' => 'Bilgi İşlem', 'description' => 'Bilgisayar, yazılım ve IT ekipmanları'],
            ['name' => 'Ofis Malzemeleri', 'description' => 'Kırtasiye, ofis mobilyaları'],
            ['name' => 'Temizlik Malzemeleri', 'description' => 'Temizlik ürünleri ve ekipmanları'],
            ['name' => 'Araç Gereç', 'description' => 'Çeşitli araç ve gereçler'],
            ['name' => 'Hizmet Alımı', 'description' => 'Dış kaynak hizmet alımları'],
            ['name' => 'Bakım Onarım', 'description' => 'Bakım ve onarım hizmetleri']
        ];
        
        foreach ($defaultCategories as $category) {
            $db->insert('purchase_categories', $category);
        }
    }
    
    // Create purchase_requests table if not exists  
    $db->execute("
        CREATE TABLE IF NOT EXISTS purchase_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            request_number VARCHAR(50) NOT NULL UNIQUE,
            requester_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            justification TEXT,
            category_id INT,
            urgency_level ENUM('düşük', 'orta', 'yüksek', 'acil') DEFAULT 'orta',
            estimated_amount DECIMAL(15,2) DEFAULT 0,
            requested_delivery_date DATE,
            status ENUM('taslak', 'sas_incelemede', 'gs_incelemede', 'teklif_toplama', 'sak1_karari_bekliyor', 'sak2_karari_bekliyor', 'yk_fiyat_karari_bekliyor', 'siparis_verildi', 'teslimat_bekliyor', 'kontrol_asamasinda', 'fatura_bekliyor', 'odeme_asamasinda', 'tamamlandi', 'red_edildi', 'iptal_edildi') DEFAULT 'sas_incelemede',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (requester_id) REFERENCES users(id),
            FOREIGN KEY (category_id) REFERENCES purchase_categories(id),
            INDEX idx_status (status),
            INDEX idx_requester (requester_id),
            INDEX idx_created (created_at)
        )
    ");

    // Create request_items table if not exists
    $db->execute("
        CREATE TABLE IF NOT EXISTS request_items (
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

    // Create departments table if not exists
    $db->execute("
        CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default departments if empty
    $deptCount = $db->fetchRow("SELECT COUNT(*) as count FROM departments")['count'];
    
    if ($deptCount == 0) {
        echo "Inserting default departments...\n";
        
        $defaultDepartments = [
            ['name' => 'Bilgi İşlem', 'code' => 'BIM'],
            ['name' => 'İnsan Kaynakları', 'code' => 'IK'],
            ['name' => 'Mali İşler', 'code' => 'MAL'],
            ['name' => 'Satın Alma', 'code' => 'SAT'],
            ['name' => 'Genel Sekreterlik', 'code' => 'GS']
        ];
        
        foreach ($defaultDepartments as $dept) {
            $db->insert('departments', $dept);
        }
    }
    
    // Create roles table if not exists
    $db->execute("
        CREATE TABLE IF NOT EXISTS roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default roles if empty
    $roleCount = $db->fetchRow("SELECT COUNT(*) as count FROM roles")['count'];
    
    if ($roleCount == 0) {
        echo "Inserting default roles...\n";
        
        $defaultRoles = [
            ['name' => 'sistem_yoneticisi', 'display_name' => 'Sistem Yöneticisi', 'description' => 'Tam sistem erişimi'],
            ['name' => 'kullanici', 'display_name' => 'Kullanıcı', 'description' => 'Talep oluşturabilir'],
            ['name' => 'satin_alma_sorumlusu', 'display_name' => 'Satın Alma Sorumlusu', 'description' => 'İlk onay aşaması'],
            ['name' => 'genel_sekreter', 'display_name' => 'Genel Sekreter', 'description' => 'İkinci onay aşaması'],
            ['name' => 'sak1_uyesi', 'display_name' => 'SAK1 Üyesi', 'description' => 'Komisyon üyesi'],
            ['name' => 'sak2_uyesi', 'display_name' => 'SAK2 Üyesi', 'description' => 'Komisyon üyesi'],
            ['name' => 'yonetim_kurulu_uyesi', 'display_name' => 'Yönetim Kurulu Üyesi', 'description' => 'YK üyesi']
        ];
        
        foreach ($defaultRoles as $role) {
            $db->insert('roles', $role);
        }
    }
    
    // Create users table if not exists (simplified version)
    $db->execute("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            role_id INT NOT NULL,
            department_id INT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (role_id) REFERENCES roles(id),
            FOREIGN KEY (department_id) REFERENCES departments(id)
        )
    ");
    
    // Create default admin user if no users exist
    $userCount = $db->fetchRow("SELECT COUNT(*) as count FROM users")['count'];
    
    if ($userCount == 0) {
        echo "Creating default admin user...\n";
        
        $adminRoleId = $db->fetchRow("SELECT id FROM roles WHERE name = 'sistem_yoneticisi'")['id'];
        $itDeptId = $db->fetchRow("SELECT id FROM departments WHERE code = 'BIM'")['id'];
        
        $adminUser = [
            'username' => 'admin',
            'email' => 'admin@kutahyam.tr',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'first_name' => 'Sistem',
            'last_name' => 'Yöneticisi',
            'role_id' => $adminRoleId,
            'department_id' => $itDeptId
        ];
        
        $db->insert('users', $adminUser);
        echo "Admin user created: admin / 123456\n";
    }
    
    echo "System initialization completed successfully!\n";
    echo "You can now login with: admin / 123456\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>