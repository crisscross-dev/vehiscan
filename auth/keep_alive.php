<?php
/**
 * Keep-Alive Endpoint
 * Updates session activity to prevent timeout
 */

// Parse request body for an explicit role when available.
$payload = json_decode(file_get_contents('php://input'), true);
$requestedRole = null;
if (is_array($payload) && isset($payload['role']) && is_string($payload['role'])) {
    $requestedRole = strtolower(trim($payload['role']));
}

// Derive role from the referrer as a fallback for pages that don't send a role payload.
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$refRole = null;
if (stripos($referrer, '/homeowners/') !== false) {
    $refRole = 'homeowner';
} elseif (stripos($referrer, '/guard/') !== false) {
    $refRole = 'guard';
} elseif (stripos($referrer, '/admin/') !== false) {
    $refRole = 'admin';
}

$hasAdminCookie = isset($_COOKIE['vehiscan_superadmin']) || isset($_COOKIE['vehiscan_admin']);
$useRole = $requestedRole ?: $refRole;

if ($useRole === 'homeowner' && isset($_COOKIE['vehiscan_homeowner'])) {
    require_once __DIR__ . '/../includes/session_homeowner.php';
} elseif ($useRole === 'guard' && isset($_COOKIE['vehiscan_guard'])) {
    require_once __DIR__ . '/../includes/session_guard.php';
} elseif (($useRole === 'admin' || $useRole === 'super_admin') && $hasAdminCookie) {
    require_once __DIR__ . '/../includes/session_admin_unified.php';
} elseif ($hasAdminCookie) {
    require_once __DIR__ . '/../includes/session_admin_unified.php';
} elseif (isset($_COOKIE['vehiscan_guard'])) {
    require_once __DIR__ . '/../includes/session_guard.php';
} elseif (isset($_COOKIE['vehiscan_homeowner'])) {
    require_once __DIR__ . '/../includes/session_homeowner.php';
} else {
    // No recognized role cookie, fail closed.
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No active session'
    ]);
    exit;
}

header('Content-Type: application/json');

// Update last activity
if (isset($_SESSION['username'])) {
    $_SESSION['last_activity'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session updated'
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No active session'
    ]);
}
