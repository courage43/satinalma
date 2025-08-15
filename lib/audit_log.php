<?php
/**
 * Audit Log System
 * Tracks all important system activities for compliance and debugging
 */

require_once 'helpers.php';

class AuditLogger {
    private $db;
    private static $instance = null;
    
    // Log levels
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    // Event types
    const EVENT_LOGIN = 'login';
    const EVENT_LOGOUT = 'logout';
    const EVENT_REQUEST_CREATE = 'request_create';
    const EVENT_REQUEST_UPDATE = 'request_update';
    const EVENT_REQUEST_DELETE = 'request_delete';
    const EVENT_APPROVAL = 'approval';
    const EVENT_REJECTION = 'rejection';
    const EVENT_QUOTATION_ADD = 'quotation_add';
    const EVENT_QUOTATION_UPDATE = 'quotation_update';
    const EVENT_COMMISSION_DECISION = 'commission_decision';
    const EVENT_ORDER_CREATE = 'order_create';
    const EVENT_DELIVERY = 'delivery';
    const EVENT_INVOICE = 'invoice';
    const EVENT_PAYMENT = 'payment';
    const EVENT_EMAIL_SENT = 'email_sent';
    const EVENT_TELEGRAM_SENT = 'telegram_sent';
    const EVENT_WEBHOOK_CALL = 'webhook_call';
    const EVENT_API_CALL = 'api_call';
    const EVENT_FILE_UPLOAD = 'file_upload';
    const EVENT_FILE_DELETE = 'file_delete';
    const EVENT_SYSTEM_CONFIG = 'system_config';
    const EVENT_USER_CREATE = 'user_create';
    const EVENT_USER_UPDATE = 'user_update';
    const EVENT_SECURITY_VIOLATION = 'security_violation';
    
    private function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->ensureAuditTable();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create audit_logs table if not exists
     */
    private function ensureAuditTable() {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            level ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
            user_id INT NULL,
            username VARCHAR(50) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            request_method VARCHAR(10) NULL,
            request_uri VARCHAR(500) NULL,
            related_table VARCHAR(50) NULL,
            related_id INT NULL,
            description TEXT NOT NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            additional_data JSON NULL,
            session_id VARCHAR(128) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_event_type (event_type),
            INDEX idx_user_id (user_id),
            INDEX idx_level (level),
            INDEX idx_created_at (created_at),
            INDEX idx_related (related_table, related_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->execute($sql);
        } catch (Exception $e) {
            error_log('Failed to create audit_logs table: ' . $e->getMessage());
        }
    }
    
    /**
     * Log an event
     */
    public function log($eventType, $description, $options = []) {
        try {
            $data = [
                'event_type' => $eventType,
                'level' => $options['level'] ?? self::LEVEL_INFO,
                'user_id' => $this->getCurrentUserId(),
                'username' => $this->getCurrentUsername(),
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'related_table' => $options['table'] ?? null,
                'related_id' => $options['id'] ?? null,
                'description' => $description,
                'old_values' => isset($options['old_values']) ? json_encode($options['old_values']) : null,
                'new_values' => isset($options['new_values']) ? json_encode($options['new_values']) : null,
                'additional_data' => isset($options['additional_data']) ? json_encode($options['additional_data']) : null,
                'session_id' => session_id() ?: null,
                'created_at' => DateHelper::now()
            ];
            
            $this->db->insert('audit_logs', $data);
            
            // Log critical events to file as well
            if ($data['level'] === self::LEVEL_CRITICAL) {
                error_log("CRITICAL AUDIT: {$eventType} - {$description} - User: {$data['username']} - IP: {$data['ip_address']}");
            }
            
        } catch (Exception $e) {
            // Never let audit logging break the main application
            error_log('Audit logging failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Log login attempt
     */
    public function logLogin($username, $success = true, $reason = null) {
        $this->log(
            self::EVENT_LOGIN,
            $success ? "Successful login for user: {$username}" : "Failed login attempt for user: {$username}" . ($reason ? " - {$reason}" : ''),
            [
                'level' => $success ? self::LEVEL_INFO : self::LEVEL_WARNING,
                'additional_data' => [
                    'success' => $success,
                    'username' => $username,
                    'reason' => $reason
                ]
            ]
        );
    }
    
    /**
     * Log request operations
     */
    public function logRequestOperation($operation, $requestId, $requestNumber, $oldData = null, $newData = null) {
        $eventType = match($operation) {
            'create' => self::EVENT_REQUEST_CREATE,
            'update' => self::EVENT_REQUEST_UPDATE,
            'delete' => self::EVENT_REQUEST_DELETE,
            default => self::EVENT_REQUEST_UPDATE
        };
        
        $this->log(
            $eventType,
            "Purchase request {$operation}: {$requestNumber}",
            [
                'table' => 'purchase_requests',
                'id' => $requestId,
                'old_values' => $oldData,
                'new_values' => $newData
            ]
        );
    }
    
    /**
     * Log approval/rejection
     */
    public function logApproval($action, $requestId, $requestNumber, $comments = null, $role = null) {
        $eventType = $action === 'approve' ? self::EVENT_APPROVAL : self::EVENT_REJECTION;
        
        $this->log(
            $eventType,
            "Request {$requestNumber} {$action}ed by {$role}" . ($comments ? " - Comments: {$comments}" : ''),
            [
                'table' => 'purchase_requests',
                'id' => $requestId,
                'additional_data' => [
                    'action' => $action,
                    'role' => $role,
                    'comments' => $comments
                ]
            ]
        );
    }
    
    /**
     * Log commission decision
     */
    public function logCommissionDecision($commissionType, $requestId, $decision, $selectedQuotation = null, $comments = null) {
        $this->log(
            self::EVENT_COMMISSION_DECISION,
            "{$commissionType} commission decision: {$decision} for request ID {$requestId}",
            [
                'table' => 'commission_decisions',
                'id' => $requestId,
                'additional_data' => [
                    'commission_type' => $commissionType,
                    'decision' => $decision,
                    'selected_quotation_id' => $selectedQuotation,
                    'comments' => $comments
                ]
            ]
        );
    }
    
    /**
     * Log communication (email/telegram)
     */
    public function logCommunication($type, $recipient, $subject, $success = true, $error = null) {
        $eventType = $type === 'email' ? self::EVENT_EMAIL_SENT : self::EVENT_TELEGRAM_SENT;
        
        $this->log(
            $eventType,
            $success ? 
                "{$type} sent to {$recipient}: {$subject}" : 
                "{$type} failed to {$recipient}: {$subject}" . ($error ? " - {$error}" : ''),
            [
                'level' => $success ? self::LEVEL_INFO : self::LEVEL_WARNING,
                'additional_data' => [
                    'type' => $type,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'success' => $success,
                    'error' => $error
                ]
            ]
        );
    }
    
    /**
     * Log webhook calls
     */
    public function logWebhook($url, $payload, $response = null, $httpCode = null) {
        $success = $httpCode >= 200 && $httpCode < 300;
        
        $this->log(
            self::EVENT_WEBHOOK_CALL,
            "Webhook call to {$url}" . ($success ? ' successful' : ' failed'),
            [
                'level' => $success ? self::LEVEL_INFO : self::LEVEL_WARNING,
                'additional_data' => [
                    'url' => $url,
                    'payload' => $payload,
                    'response' => $response,
                    'http_code' => $httpCode,
                    'success' => $success
                ]
            ]
        );
    }
    
    /**
     * Log API calls
     */
    public function logAPICall($endpoint, $method, $payload = null, $response = null, $apiKey = null) {
        $this->log(
            self::EVENT_API_CALL,
            "API call: {$method} {$endpoint}",
            [
                'additional_data' => [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'payload' => $payload,
                    'response' => $response,
                    'api_key_used' => $apiKey ? FormatHelper::mask($apiKey, 8) : null
                ]
            ]
        );
    }
    
    /**
     * Log security violations
     */
    public function logSecurityViolation($type, $details) {
        $this->log(
            self::EVENT_SECURITY_VIOLATION,
            "Security violation: {$type}",
            [
                'level' => self::LEVEL_CRITICAL,
                'additional_data' => [
                    'violation_type' => $type,
                    'details' => $details
                ]
            ]
        );
    }
    
    /**
     * Log file operations
     */
    public function logFileOperation($operation, $filename, $size = null, $success = true) {
        $eventType = $operation === 'upload' ? self::EVENT_FILE_UPLOAD : self::EVENT_FILE_DELETE;
        
        $this->log(
            $eventType,
            "File {$operation}: {$filename}" . ($size ? " ({$size} bytes)" : ''),
            [
                'level' => $success ? self::LEVEL_INFO : self::LEVEL_WARNING,
                'additional_data' => [
                    'operation' => $operation,
                    'filename' => $filename,
                    'size' => $size,
                    'success' => $success
                ]
            ]
        );
    }
    
    /**
     * Get audit logs with filtering
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = ?';
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['level'])) {
            $where[] = 'level = ?';
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = 'description LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        $sql = "SELECT * FROM audit_logs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get current user ID from session
     */
    private function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current username from session
     */
    private function getCurrentUsername() {
        return $_SESSION['username'] ?? 'anonymous';
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
    
    /**
     * Generate audit report
     */
    public function generateReport($dateFrom, $dateTo, $eventTypes = []) {
        $where = ['created_at BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        
        if (!empty($eventTypes)) {
            $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
            $where[] = "event_type IN ({$placeholders})";
            $params = array_merge($params, $eventTypes);
        }
        
        $sql = "SELECT 
                    event_type,
                    level,
                    COUNT(*) as event_count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_event,
                    MAX(created_at) as last_event
                FROM audit_logs 
                WHERE " . implode(' AND ', $where) . "
                GROUP BY event_type, level
                ORDER BY event_count DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Clean old logs (for maintenance)
     */
    public function cleanOldLogs($daysToKeep = 365) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $deletedCount = $this->db->execute(
            "DELETE FROM audit_logs WHERE created_at < ? AND level NOT IN ('error', 'critical')",
            [$cutoffDate]
        )->rowCount();
        
        $this->log(
            self::EVENT_SYSTEM_CONFIG,
            "Cleaned {$deletedCount} old audit log entries (older than {$daysToKeep} days)",
            ['level' => self::LEVEL_INFO]
        );
        
        return $deletedCount;
    }
}

// Global audit logger instance
function audit() {
    return AuditLogger::getInstance();
}

// Convenience functions
function auditLogin($username, $success, $reason = null) {
    audit()->logLogin($username, $success, $reason);
}

function auditRequest($operation, $requestId, $requestNumber, $oldData = null, $newData = null) {
    audit()->logRequestOperation($operation, $requestId, $requestNumber, $oldData, $newData);
}

function auditApproval($action, $requestId, $requestNumber, $comments = null, $role = null) {
    audit()->logApproval($action, $requestId, $requestNumber, $comments, $role);
}

function auditEmail($recipient, $subject, $success = true, $error = null) {
    audit()->logCommunication('email', $recipient, $subject, $success, $error);
}

function auditTelegram($recipient, $subject, $success = true, $error = null) {
    audit()->logCommunication('telegram', $recipient, $subject, $success, $error);
}

function auditWebhook($url, $payload, $response = null, $httpCode = null) {
    audit()->logWebhook($url, $payload, $response, $httpCode);
}

function auditSecurity($type, $details) {
    audit()->logSecurityViolation($type, $details);
}
?>