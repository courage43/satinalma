<?php
require_once 'env.php';
require_once dirname(__DIR__) . '/lib/helpers.php';

class Auth {
    private $db;
    private static $instance = null;
    
    private function __construct() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
        
        $this->db = DatabaseHelper::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function login($username, $password) {
        $user = $this->db->fetchRow(
            "SELECT u.*, r.name as role_name FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ? AND u.is_active = 1",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            return true;
        }
        return false;
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        if (!$this->hasRole($roles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getCurrentUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public function getCurrentUserName() {
        return $_SESSION['full_name'] ?? '';
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

// Global auth instance
$auth = Auth::getInstance();
?>