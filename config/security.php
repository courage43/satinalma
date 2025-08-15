<?php
/**
 * Security Configuration and CSRF Protection
 */

require_once 'env.php';

class SecurityManager {
    private static $instance = null;
    private $csrfSecret;
    private $hmacSecret;
    
    private function __construct() {
        $this->csrfSecret = EnvConfig::required('CSRF_SECRET');
        $this->hmacSecret = EnvConfig::required('HMAC_SECRET');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * CSRF Token Generation and Validation
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function validateCSRF($token) {
        return $this->validateCSRFToken($token);
    }
    
    public function requireCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!$this->validateCSRFToken($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
    
    /**
     * HMAC Signature for API Communication
     */
    public function generateHMACSignature($data, $timestamp = null) {
        $timestamp = $timestamp ?: time();
        $payload = is_array($data) ? json_encode($data) : $data;
        $message = $timestamp . '.' . $payload;
        return hash_hmac('sha256', $message, $this->hmacSecret);
    }
    
    public function validateHMACSignature($data, $signature, $timestamp, $toleranceSeconds = 300) {
        // Check timestamp tolerance (5 minutes default)
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }
        
        $expectedSignature = $this->generateHMACSignature($data, $timestamp);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Secure Password Hashing
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * One-time Token Generation (for approval links)
     */
    public function generateOneTimeToken($userId, $action, $expiryHours = 48) {
        $payload = [
            'user_id' => $userId,
            'action' => $action,
            'expires' => time() + ($expiryHours * 3600),
            'nonce' => bin2hex(random_bytes(16))
        ];
        
        $token = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $token, $this->csrfSecret);
        
        return $token . '.' . $signature;
    }
    
    public function validateOneTimeToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($payload, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->csrfSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return false;
        }
        
        // Check expiry
        if (time() > $data['expires']) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Input Sanitization
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return (int) $input;
            case 'float':
                return (float) $input;
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            default:
                return trim($input);
        }
    }
    
    /**
     * Rate Limiting
     */
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * SQL Injection Prevention Helper
     */
    public function preparePlaceholders($count) {
        return implode(',', array_fill(0, $count, '?'));
    }
}

// Global security instance
$security = SecurityManager::getInstance();

// CSRF Helper Functions
function csrf_token() {
    global $security;
    return $security->generateCSRFToken();
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_input() {
    return csrf_field();
}

function csrf_meta() {
    return '<meta name="csrf-token" content="' . csrf_token() . '">';
}
?>