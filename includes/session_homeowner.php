<?php
require_once __DIR__ . '/session_helpers.php';
/**
 * Homeowner Session Management
 */

// Configure session — isolate from other XAMPP apps
initializeVehiscanSessionPath();
ini_set('session.gc_maxlifetime', 3600); // Prevent GC from deleting other sessions in shared save_path

ini_set('session.cookie_secure', vehiscanIsHttpsRequest() ? '1' : '0');

// Removed aggressive cookie cleanup to allow simultaneous multi-role sessions (Guard, Admin, Homeowner).
// Each role now has its own unique session cookie name.
/*
foreach (['vehiscan_admin', 'vehiscan_superadmin', 'vehiscan_guard'] as $sName) {
    if (isset($_COOKIE[$sName])) {
        setcookie($sName, '', time() - 3600, '/');
        unset($_COOKIE[$sName]);
    }
}
*/

vehiscanStartNamedSession('vehiscan_homeowner');

$isAjaxRequest = vehiscanIsAjaxRequest();

// Check if homeowner is logged in
if (!isset($_SESSION['homeowner_id']) || (($_SESSION['role'] ?? '') !== 'homeowner')) {
    if (php_sapi_name() === 'cli') {
        return; // Allow CLI access for testing
    }
    
    // For AJAX/API requests, return JSON error
    if ($isAjaxRequest) {
        vehiscanJsonExit(401, ['success' => false, 'error' => 'Session expired']);
    }
    
    // For regular requests, redirect to login
    header('Location: ' . vehiscanLoginPath());
    exit();
}

// Session timeout check (30 minutes)
$session_lifetime = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_lifetime)) {
    vehiscanClearSessionAndCookie();
    
    if ($isAjaxRequest) {
        vehiscanJsonExit(401, ['success' => false, 'error' => 'Session timeout']);
    }
    
    header('Location: ' . vehiscanLoginPath('timeout=1'));
    exit();
}

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

$_SESSION['last_activity'] = time();

// Auto-generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = vehiscanGenerateCsrfToken();
}
