<?php
// auth/logout.php
// Universal logout handler - handles all session types
ob_start(); // Buffer output to prevent warnings from blocking setcookie()

// Isolate from other XAMPP apps to prevent cross-app GC
$appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
if (!is_dir($appSavePath)) { mkdir($appSavePath, 0700, true); }
ini_set('session.save_path', $appSavePath);
ini_set('session.gc_maxlifetime', 3600);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/audit_logger.php';

// Initialize audit logger and capture username before destroying sessions
$username = null;
$role = null;
try {
    AuditLogger::init($pdo);

    // Try to get username from any active session (only check cookies that exist)
    foreach (['vehiscan_superadmin', 'vehiscan_admin', 'vehiscan_guard', 'vehiscan_homeowner', 'vehiscan_session'] as $sName) {
        if (!isset($_COOKIE[$sName])) {
            continue;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name($sName);
        session_id($_COOKIE[$sName]);
        session_start();
        if (isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
            $role = $_SESSION['role'] ?? 'unknown';
            session_write_close();
            break;
        }
        session_write_close();
    }
} catch (Exception $e) {
    // Audit logger not available
}

// Function to destroy a specific session
function destroySessionByName($sessionName) {
    // Only process if the browser actually has this cookie
    if (!isset($_COOKIE[$sessionName])) {
        return;
    }

    // Close current session if active
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Open the exact session the cookie points to
    session_name($sessionName);
    session_id($_COOKIE[$sessionName]);
    session_start();

    // Destroy session data
    $_SESSION = array();
    session_unset();
    session_destroy();

    // Expire the cookie
    setcookie($sessionName, '', time() - 3600, '/');
    unset($_COOKIE[$sessionName]);
}

// Destroy all possible session types (including Super Admin)
$sessionTypes = ['vehiscan_superadmin', 'vehiscan_admin', 'vehiscan_guard', 'vehiscan_homeowner', 'vehiscan_session'];

foreach ($sessionTypes as $sessionName) {
    destroySessionByName($sessionName);
}

// Log the logout action
error_log("User logged out - All sessions destroyed at " . date('Y-m-d H:i:s'));

// Log to audit system
try {
    if ($username) {
        AuditLogger::logAuth($role . '_logout', true, $username);
    }
} catch (Exception $e) {
    // Audit logger not available
}

// If AJAX (fetch) request, return JSON
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

// Otherwise, redirect normally to login page
header("Location: login.php");
exit;
?>

