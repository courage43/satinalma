<?php
// API Authentication System for N8N Integration
require_once '../config/database.php';

class APIAuth {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function authenticate() {
        $headers = apache_request_headers();
        $api_key = $headers['X-API-Key'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? null;
        
        if (!$api_key) {
            $this->sendError(401, 'API key required');
            return false;
        }
        
        // API key'i veritabanından kontrol et
        $query = "SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$api_key]);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError(401, 'Invalid API key');
            return false;
        }
        
        $api_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Son kullanım tarihini güncelle
        $update_query = "UPDATE api_keys SET last_used = NOW() WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->execute([$api_data['id']]);
        
        return $api_data;
    }
    
    public function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    public function sendSuccess($data, $message = 'Success') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    public function generateAPIKey($name, $description = '', $permissions = []) {
        $api_key = bin2hex(random_bytes(32));
        
        $query = "INSERT INTO api_keys (name, api_key, description, permissions, is_active) VALUES (?, ?, ?, ?, 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$name, $api_key, $description, json_encode($permissions)]);
        
        return $api_key;
    }
}

// API keys tablosunu oluştur (eğer yoksa)
$db = new Database();
$conn = $db->getConnection();

$create_api_keys_table = "CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL
)";

try {
    $conn->exec($create_api_keys_table);
} catch (PDOException $e) {
    // Tablo zaten var
}
?>