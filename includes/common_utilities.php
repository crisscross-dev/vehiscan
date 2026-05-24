<?php
/**
 * Common Utility Functions
 * Shared functions used across the system
 */

/**
 * Format contact number to standard format
 * @param string $number
 * @return string
 */
function formatContactNumber($number) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $number);
    
    // Format based on length
    if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '0') {
        // Format: 0XXX-XXX-XXXX
        return substr($cleaned, 0, 4) . '-' . substr($cleaned, 4, 3) . '-' . substr($cleaned, 7);
    } elseif (strlen($cleaned) === 10) {
        // Format: XXX-XXX-XXXX
        return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6);
    }
    
    return $cleaned;
}

/**
 * Sanitize user input
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input ?? '')), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random token
 * @param int $length
 * @return string
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Format file size for display
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

/**
 * Format a datetime value for display
 * @param string|null $value
 * @param bool $includeTime
 * @return string
 */
function formatDisplayDateTime($value, $includeTime = true) {
    if (empty($value)) {
        return '-';
    }

    try {
        $dateTime = new DateTime((string)$value);
    } catch (Exception $e) {
        return (string)$value;
    }

    return $includeTime ? $dateTime->format('M j, Y g:i A') : $dateTime->format('M j, Y');
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Build full name from parts
 * @param string $firstName
 * @param string $middleName
 * @param string $lastName
 * @param string $suffix
 * @return string
 */
function buildFullName($firstName, $middleName = '', $lastName = '', $suffix = '') {
    $parts = array_filter([$firstName, $middleName, $lastName, $suffix]);
    return implode(' ', $parts);
}
