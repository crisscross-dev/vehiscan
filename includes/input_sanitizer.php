<?php
/**
 * Input Sanitizer
 * Centralized input validation and sanitization layer
 * Use this to secure all $_POST, $_GET, and $_REQUEST data
 */

class InputSanitizer {
    
    /**
     * Sanitize string input
     * @param mixed $input
     * @param int $maxLength
     * @return string
     */
    public static function sanitizeString($input, $maxLength = null) {
        if (is_null($input)) {
            return '';
        }
        
        $sanitized = htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        
        if ($maxLength !== null && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize integer input
     * @param mixed $input
     * @return int|null
     */
    public static function sanitizeInt($input) {
        if (is_null($input) || $input === '') {
            return null;
        }
        
        return filter_var($input, FILTER_VALIDATE_INT) !== false 
            ? (int)$input 
            : null;
    }
    
    /**
     * Sanitize email input
     * @param mixed $input
     * @return string|null
     */
    public static function sanitizeEmail($input) {
        if (is_null($input) || $input === '') {
            return null;
        }
        
        $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false 
            ? $email 
            : null;
    }
    
    /**
     * Sanitize URL input
     * @param mixed $input
     * @return string|null
     */
    public static function sanitizeUrl($input) {
        if (is_null($input) || $input === '') {
            return null;
        }
        
        $url = filter_var(trim($input), FILTER_SANITIZE_URL);
        
        return filter_var($url, FILTER_VALIDATE_URL) !== false 
            ? $url 
            : null;
    }
    
    /**
     * Sanitize boolean input
     * @param mixed $input
     * @return bool
     */
    public static function sanitizeBool($input) {
        if (is_bool($input)) {
            return $input;
        }
        
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
    
    /**
     * Sanitize float/decimal input
     * @param mixed $input
     * @return float|null
     */
    public static function sanitizeFloat($input) {
        if (is_null($input) || $input === '') {
            return null;
        }
        
        return filter_var($input, FILTER_VALIDATE_FLOAT) !== false 
            ? (float)$input 
            : null;
    }
    
    /**
     * Sanitize array input (recursively sanitize all values)
     * @param mixed $input
     * @return array
     */
    public static function sanitizeArray($input) {
        if (!is_array($input)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitizedKey = self::sanitizeString($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::sanitizeArray($value);
            } else {
                $sanitized[$sanitizedKey] = self::sanitizeString($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get and sanitize POST parameter
     * @param string $key
     * @param string $type (string, int, email, bool, float, array)
     * @param mixed $default
     * @return mixed
     */
    public static function post($key, $type = 'string', $default = null) {
        if (!isset($_POST[$key])) {
            return $default;
        }
        
        return self::sanitizeByType($_POST[$key], $type, $default);
    }
    
    /**
     * Get and sanitize GET parameter
     * @param string $key
     * @param string $type
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $type = 'string', $default = null) {
        if (!isset($_GET[$key])) {
            return $default;
        }
        
        return self::sanitizeByType($_GET[$key], $type, $default);
    }
    
    /**
     * Sanitize by type
     * @param mixed $value
     * @param string $type
     * @param mixed $default
     * @return mixed
     */
    private static function sanitizeByType($value, $type, $default) {
        switch ($type) {
            case 'int':
            case 'integer':
                $result = self::sanitizeInt($value);
                return $result !== null ? $result : $default;
                
            case 'email':
                $result = self::sanitizeEmail($value);
                return $result !== null ? $result : $default;
                
            case 'url':
                $result = self::sanitizeUrl($value);
                return $result !== null ? $result : $default;
                
            case 'bool':
            case 'boolean':
                return self::sanitizeBool($value);
                
            case 'float':
            case 'double':
                $result = self::sanitizeFloat($value);
                return $result !== null ? $result : $default;
                
            case 'array':
                return self::sanitizeArray($value);
                
            case 'string':
            default:
                return self::sanitizeString($value);
        }
    }
    
    /**
     * Validate CSRF token
     * @param string $token
     * @return bool
     */
    public static function validateCsrf($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        if (!is_string($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     * @return string
     */
    public static function generateCsrf() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Sanitize filename for upload
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename($filename) {
        // Remove any path components
        $filename = basename($filename);
        
        // Remove special characters except dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots (potential directory traversal)
        $filename = preg_replace('/\.+/', '.', $filename);
        
        return $filename;
    }
    
    /**
     * Validate file upload
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Max file size in bytes
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        // Check for upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'File too large'];
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'No file uploaded'];
            default:
                return ['valid' => false, 'error' => 'Upload error'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File exceeds maximum size'];
        }
        
        // Check MIME type if specified
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'Invalid file type'];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }
}

/**
 * Helper functions for backward compatibility
 */

/**
 * Shorthand for InputSanitizer::post()
 */
function sanitize_post($key, $type = 'string', $default = null) {
    return InputSanitizer::post($key, $type, $default);
}

/**
 * Shorthand for InputSanitizer::get()
 */
function sanitize_get($key, $type = 'string', $default = null) {
    return InputSanitizer::get($key, $type, $default);
}
