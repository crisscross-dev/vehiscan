<?php
/**
 * Input Validation Library
 * Centralized validation functions for security and consistency
 */

class InputValidator {
    
    /**
     * Validate username format
     * @param string $username
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateUsername($username) {
        if (empty($username)) {
            return ['valid' => false, 'message' => 'Username is required'];
        }
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['valid' => false, 'message' => 'Username must be 3-50 characters'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
    
    /**
     * Validate password strength
     * @param string $password
     * @param int $minLength
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePassword($password, $minLength = 12, $requireSymbol = false) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'Password is required'];
        }
        
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'message' => "Password must be at least $minLength characters"];
        }
        
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }

        if ($requireSymbol && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one symbol (special character)'];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
    
    /**
     * Validate plate number format
     * @param string $plate
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePlateNumber($plate) {
        if (empty($plate)) {
            return ['valid' => false, 'message' => 'Plate number is required'];
        }
        
        $plate = strtoupper(trim($plate));
        
        if (strlen($plate) < 3 || strlen($plate) > 7) {
            return ['valid' => false, 'message' => 'Plate number must be 3-7 characters'];
        }
        
        if (!preg_match('/^[A-Z0-9\-]+$/', $plate)) {
            return ['valid' => false, 'message' => 'Plate number can only contain letters, numbers, and hyphens'];
        }
        
        return ['valid' => true, 'message' => 'Valid', 'formatted' => $plate];
    }
    
    /**
     * Validate phone number
     * @param string $phone
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePhoneNumber($phone) {
        if (empty($phone)) {
            return ['valid' => false, 'message' => 'Phone number is required'];
        }
        
        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // Check if it's a valid Philippine number or generic format
        if (!preg_match('/^[\+]?[0-9]{7,15}$/', $cleaned)) {
            return ['valid' => false, 'message' => 'Invalid phone number format'];
        }
        
        return ['valid' => true, 'message' => 'Valid', 'formatted' => $cleaned];
    }
    
    /**
     * Validate uploaded image file
     * @param array $file $_FILES array element
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateImageUpload($file, $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No file uploaded'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload error: ' . $file['error']];
        }
        
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1048576, 1);
            return ['valid' => false, 'message' => "File size exceeds maximum of {$maxMB}MB"];
        }
        
        // Verify actual file type (not just extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'message' => 'Only JPEG and PNG images are allowed'];
        }
        
        // Verify image dimensions
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'Invalid image file'];
        }
        
        // Check minimum dimensions
        if ($imageInfo[0] < 50 || $imageInfo[1] < 50) {
            return ['valid' => false, 'message' => 'Image is too small (minimum 50x50 pixels)'];
        }
        
        // Check maximum dimensions (prevent huge images)
        if ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
            return ['valid' => false, 'message' => 'Image is too large (maximum 4000x4000 pixels)'];
        }
        
        return ['valid' => true, 'message' => 'Valid', 'mime_type' => $mimeType, 'dimensions' => $imageInfo];
    }
    
    /**
     * Sanitize string for output
     * @param string $string
     * @return string
     */
    public static function sanitizeOutput($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     * @param string $email
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email is required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
}
