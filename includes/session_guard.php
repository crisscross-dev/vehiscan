<?php
require_once __DIR__ . '/session_helpers.php';
// Ensure small compatibility helpers exist in case of partial deploys
require_once __DIR__ . '/compat.php';
// Configure session for guard access
initializeVehiscanSessionPath();
// Use Lax for local network testing, Strict for production
ini_set('session.gc_maxlifetime', 28800); // 8 hours (guard shift)
ini_set('session.cookie_lifetime', 0); // Session cookie (until browser closes)
// Enable secure cookie if HTTPS is active
ini_set('session.cookie_secure', vehiscanIsHttpsRequest() ? '1' : '0');

// Removed aggressive cookie cleanup to allow simultaneous multi-role sessions (Guard, Admin, Homeowner).
// Each role now has its own unique session cookie name.
/* 
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUEST_WITH']) && strtolower($_SERVER['HTTP_X_REQUEST_WITH']) == 'xmlhttprequest') || 
                 (isset($_GET['ajax']) && $_GET['ajax'] == '1') ||
                 (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);

if (!$isAjaxRequest) {
    foreach (['vehiscan_admin', 'vehiscan_superadmin', 'vehiscan_homeowner'] as $sName) {
        if (isset($_COOKIE[$sName])) {
            setcookie($sName, '', time() - 3600, '/');
            unset($_COOKIE[$sName]);
        }
    }
}
*/

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    vehiscanStartNamedSession('vehiscan_guard');
    
    // Debug session start (only in development)
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('Guard Session Started: ' . json_encode([
            'session_id' => session_id(),
            'time' => date('Y-m-d H:i:s')
        ]));
    }
}

// Session timeout: 8 hours (one guard shift)
$guard_session_lifetime = 28800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $guard_session_lifetime)) {
    vehiscanClearSessionAndCookie();
    
    // For AJAX requests, return JSON error
    if (vehiscanIsAjaxRequest()) {
        vehiscanJsonExit(401, ['success' => false, 'error' => 'Session expired after shift timeout']);
    }
    
    header('Location: ' . vehiscanLoginPath('timeout=1'));
    exit();
}

// Inactivity timeout: 30 minutes of no activity (even within shift)
// This prevents abandoned sessions from being used
$guard_inactivity_timeout = 1800; // 30 minutes
if (isset($_SESSION['created_at'])) {
    $session_age = time() - $_SESSION['created_at'];
    if (isset($_SESSION['last_activity'])) {
        $inactivity_duration = time() - $_SESSION['last_activity'];
        if ($inactivity_duration > $guard_inactivity_timeout) {
            // Inactivity timeout triggered
            vehiscanClearSessionAndCookie();
            
            if (vehiscanIsAjaxRequest()) {
                vehiscanJsonExit(401, ['success' => false, 'error' => 'Session expired due to inactivity']);
            }
            
            header('Location: ' . vehiscanLoginPath('timeout=1'));
            exit();
        }
    }
}

// Update last activity timestamp for next inactivity check
$_SESSION['last_activity'] = time();
// Track session creation time (used for debugging and audit)
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = vehiscanGenerateCsrfToken();
}

if (($_SESSION['role'] ?? '') !== 'guard') {
    vehiscanClearSessionAndCookie();

    if (vehiscanIsAjaxRequest()) {
        vehiscanJsonExit(401, ['success' => false, 'error' => 'Unauthorized']);
    }

    header('Location: ' . vehiscanLoginPath());
    exit();
}

// Expose CSRF via header for JS auto-refresh
header('X-CSRF-Token: ' . $_SESSION['csrf_token']);

if (!function_exists('logAudit')) {
    function logAudit($action, $table = null, $record_id = null, $details = null) {
        if (!isset($_SESSION['username'])) return;
        global $pdo;
        if (!isset($pdo)) return;
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetch();
            if (!$check) return;
            $stmt = $pdo->prepare("INSERT INTO audit_logs (username, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['username'], $action, $table, $record_id, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}
    }
}