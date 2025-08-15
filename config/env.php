<?php
/**
 * Environment Configuration Loader
 * .env dosyasını okuyup sistem genelinde kullanılabilir hale getirir
 */

class EnvConfig {
    private static $config = [];
    private static $loaded = false;
    
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        $envPath = $path ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($envPath)) {
            throw new Exception('.env file not found at: ' . $envPath);
        }
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$config[$key] = $value;
                
                // Set as environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
        
        self::$loaded = true;
    }
    
    public static function get($key, $default = null) {
        self::load();
        return self::$config[$key] ?? $default;
    }
    
    public static function required($key) {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new Exception("Required environment variable '$key' is not set");
        }
        return $value;
    }
    
    public static function bool($key, $default = false) {
        $value = self::get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    public static function int($key, $default = 0) {
        return (int) self::get($key, $default);
    }
    
    public static function all() {
        self::load();
        return self::$config;
    }
}

// Auto-load environment variables
try {
    EnvConfig::load();
} catch (Exception $e) {
    // Development mode: show error
    if (EnvConfig::get('APP_DEBUG', false)) {
        die('Environment configuration error: ' . $e->getMessage());
    }
    // Production mode: log error and continue with defaults
    error_log('Environment configuration error: ' . $e->getMessage());
}
?>