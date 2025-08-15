<?php
/**
 * Common Helper Functions Library
 * SQL, format, date operations and utility functions
 */

require_once dirname(__DIR__) . '/config/env.php';

class DatabaseHelper {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                EnvConfig::required('DB_HOST'),
                EnvConfig::required('DB_NAME')
            );
            
            $this->pdo = new PDO(
                $dsn,
                EnvConfig::required('DB_USER'),
                EnvConfig::required('DB_PASS'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL Error: {$e->getMessage()}, SQL: $sql");
            throw $e;
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchRow($sql, $params = []) {
        return $this->execute($sql, $params)->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        return $this->execute($sql, $params)->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Insert record and return ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->execute($sql, $data);
        return $this->lastInsertId();
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $params);
    }
}

class DateHelper {
    /**
     * Get Turkish timezone
     */
    public static function getTimezone() {
        return new DateTimeZone(EnvConfig::get('TIMEZONE', 'Europe/Istanbul'));
    }
    
    /**
     * Get current datetime in Turkish timezone
     */
    public static function now($format = 'Y-m-d H:i:s') {
        return (new DateTime('now', self::getTimezone()))->format($format);
    }
    
    /**
     * Format date to Turkish format
     */
    public static function formatTurkish($date, $includeTime = false) {
        if (!$date) return '-';
        
        $dt = new DateTime($date, self::getTimezone());
        $format = $includeTime ? 'd.m.Y H:i' : 'd.m.Y';
        return $dt->format($format);
    }
    
    /**
     * Get relative time (2 saat önce, 3 gün önce)
     */
    public static function timeAgo($date) {
        if (!$date) return '-';
        
        $now = new DateTime('now', self::getTimezone());
        $past = new DateTime($date, self::getTimezone());
        $diff = $now->diff($past);
        
        if ($diff->y > 0) return $diff->y . ' yıl önce';
        if ($diff->m > 0) return $diff->m . ' ay önce';
        if ($diff->d > 0) return $diff->d . ' gün önce';
        if ($diff->h > 0) return $diff->h . ' saat önce';
        if ($diff->i > 0) return $diff->i . ' dakika önce';
        return 'Az önce';
    }
    
    /**
     * Add business days (excluding weekends)
     */
    public static function addBusinessDays($date, $days) {
        $dt = new DateTime($date, self::getTimezone());
        
        for ($i = 0; $i < $days; $i++) {
            $dt->add(new DateInterval('P1D'));
            
            // Skip weekends
            while ($dt->format('N') >= 6) {
                $dt->add(new DateInterval('P1D'));
            }
        }
        
        return $dt->format('Y-m-d');
    }
    
    /**
     * Check if date is within business hours
     */
    public static function isBusinessHours($date = null) {
        $dt = $date ? new DateTime($date, self::getTimezone()) : new DateTime('now', self::getTimezone());
        
        $dayOfWeek = (int)$dt->format('N'); // 1=Monday, 7=Sunday
        $hour = (int)$dt->format('H');
        
        // Monday to Friday, 8:00 to 18:00
        return ($dayOfWeek >= 1 && $dayOfWeek <= 5) && ($hour >= 8 && $hour < 18);
    }
}

class FormatHelper {
    /**
     * Format currency (Turkish Lira)
     */
    public static function currency($amount, $currency = 'TRY') {
        if ($amount === null || $amount === '') return '-';
        
        $formatted = number_format((float)$amount, 2, ',', '.');
        
        switch ($currency) {
            case 'TRY':
                return '₺' . $formatted;
            case 'USD':
                return '$' . $formatted;
            case 'EUR':
                return '€' . $formatted;
            default:
                return $formatted . ' ' . $currency;
        }
    }
    
    /**
     * Format file size
     */
    public static function fileSize($bytes) {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }
    
    /**
     * Sanitize and format text
     */
    public static function text($text, $maxLength = null) {
        if (!$text) return '';
        
        $text = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
        
        if ($maxLength && strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }
        
        return $text;
    }
    
    /**
     * Format phone number
     */
    public static function phone($phone) {
        if (!$phone) return '-';
        
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($clean) === 11 && substr($clean, 0, 1) === '0') {
            // Turkish mobile: 0532 123 45 67
            return substr($clean, 0, 4) . ' ' . substr($clean, 4, 3) . ' ' . substr($clean, 7, 2) . ' ' . substr($clean, 9, 2);
        }
        
        return $phone;
    }
    
    /**
     * Generate reference number
     */
    public static function generateReference($prefix = 'REF', $year = null) {
        $year = $year ?: date('Y');
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return sprintf('%s-%s-%s', $prefix, $year, $timestamp . $random);
    }
    
    /**
     * Mask sensitive data
     */
    public static function mask($text, $visibleChars = 4, $maskChar = '*') {
        if (strlen($text) <= $visibleChars) {
            return str_repeat($maskChar, strlen($text));
        }
        
        $visible = substr($text, 0, $visibleChars);
        $masked = str_repeat($maskChar, strlen($text) - $visibleChars);
        
        return $visible . $masked;
    }
}

class ValidationHelper {
    /**
     * Validate email
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate Turkish identity number
     */
    public static function tcKimlik($tcno) {
        if (!preg_match('/^[1-9][0-9]{10}$/', $tcno)) {
            return false;
        }
        
        $digits = str_split($tcno);
        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        
        $check1 = ($oddSum * 7 - $evenSum) % 10;
        $check2 = ($oddSum + $evenSum + $digits[9]) % 10;
        
        return $check1 == $digits[9] && $check2 == $digits[10];
    }
    
    /**
     * Validate Turkish tax number
     */
    public static function vergiNo($taxno) {
        if (!preg_match('/^[0-9]{10}$/', $taxno)) {
            return false;
        }
        
        $digits = str_split($taxno);
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $temp = ($digits[$i] + $i + 1) % 10;
            $sum += ($temp * pow(2, 9 - $i)) % 9;
        }
        
        $checkDigit = ($sum % 9);
        return $checkDigit == $digits[9];
    }
    
    /**
     * Validate IBAN
     */
    public static function iban($iban) {
        $iban = strtoupper(preg_replace('/[^A-Z0-9]/', '', $iban));
        
        if (strlen($iban) !== 26 || substr($iban, 0, 2) !== 'TR') {
            return false;
        }
        
        // Move first 4 characters to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        
        // Convert letters to numbers
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (is_numeric($char)) {
                $numeric .= $char;
            } else {
                $numeric .= (ord($char) - ord('A') + 10);
            }
        }
        
        // Check modulo 97
        return bcmod($numeric, '97') === '1';
    }
}

class FileHelper {
    /**
     * Upload file securely
     */
    public static function upload($file, $directory, $allowedTypes = null) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        $allowedTypes = $allowedTypes ?: explode(',', EnvConfig::get('ALLOWED_EXTENSIONS', 'pdf,doc,docx,jpg,png'));
        $maxSize = EnvConfig::int('MAX_FILE_SIZE', 10485760); // 10MB default
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size: ' . FormatHelper::fileSize($maxSize));
        }
        
        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('File type not allowed. Allowed: ' . implode(', ', $allowedTypes));
        }
        
        // Create directory if not exists
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = rtrim($directory, '/') . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'extension' => $extension
        ];
    }
    
    /**
     * Delete file safely
     */
    public static function delete($filepath) {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}

class StatusHelper {
    /**
     * Get status badge HTML
     */
    public static function badge($status, $text = null) {
        $text = $text ?: self::getStatusText($status);
        $color = self::getStatusColor($status);
        
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-%s-100 text-%s-800">%s</span>',
            $color,
            $color,
            FormatHelper::text($text)
        );
    }
    
    /**
     * Get status color
     */
    public static function getStatusColor($status) {
        $colors = [
            'taslak' => 'gray',
            'sas_incelemede' => 'yellow',
            'gs_incelemede' => 'blue',
            'teklif_toplama' => 'indigo',
            'sak1_karari_bekliyor' => 'purple',
            'sak2_karari_bekliyor' => 'purple',
            'yk_fiyat_karari_bekliyor' => 'pink',
            'siparis_verildi' => 'green',
            'teslimat_bekliyor' => 'green',
            'kontrol_asamasinda' => 'blue',
            'fatura_bekliyor' => 'yellow',
            'odeme_asamasinda' => 'orange',
            'tamamlandi' => 'green',
            'red_edildi' => 'red',
            'iptal_edildi' => 'gray'
        ];
        
        return $colors[$status] ?? 'gray';
    }
    
    /**
     * Get status text
     */
    public static function getStatusText($status) {
        $texts = [
            'taslak' => 'Taslak',
            'sas_incelemede' => 'SAS İncelemede',
            'gs_incelemede' => 'GS İncelemede',
            'teklif_toplama' => 'Teklif Toplama',
            'sak1_karari_bekliyor' => 'SAK1 Bekliyor',
            'sak2_karari_bekliyor' => 'SAK2 Bekliyor',
            'yk_fiyat_karari_bekliyor' => 'YK Bekliyor',
            'siparis_verildi' => 'Sipariş Verildi',
            'teslimat_bekliyor' => 'Teslimat Bekliyor',
            'kontrol_asamasinda' => 'Kontrol Aşamasında',
            'fatura_bekliyor' => 'Fatura Bekliyor',
            'odeme_asamasinda' => 'Ödeme Aşamasında',
            'tamamlandi' => 'Tamamlandı',
            'red_edildi' => 'Reddedildi',
            'iptal_edildi' => 'İptal Edildi'
        ];
        
        return $texts[$status] ?? $status;
    }
}

// Flash message helpers
function flash($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function flashSuccess($message) {
    flash($message, 'success');
}

function flashError($message) {
    flash($message, 'error');
}

function flashWarning($message) {
    flash($message, 'warning');
}

function flashInfo($message) {
    flash($message, 'info');
}
?>