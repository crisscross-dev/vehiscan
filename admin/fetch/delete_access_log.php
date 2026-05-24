<?php
// admin/fetch/delete_access_log.php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('POST');

// SECURITY: Only super_admin and admin can delete logs - NOT guards
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Unauthorized - Only administrators can delete logs']));
}

header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'Access log deletion is disabled by policy.'
]);
exit;

require_once __DIR__ . '/../../db.php';

// Validate CSRF token
$csrf = $_SESSION['csrf_token'] ?? '';
$posted = $_POST['csrf_token'] ?? '';
if (!hash_equals($csrf, (string)$posted)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate log_id
$log_id = intval($_POST['log_id'] ?? 0);
if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM recent_logs WHERE log_id = ?");
    $ok = $stmt->execute([$log_id]);
    
    if ($ok) {
        // Log audit trail
        if (function_exists('logAudit')) {
            logAudit('DELETE', 'recent_logs', $log_id, "Deleted access log");
        }
        echo json_encode(['success' => true, 'message' => "Access log #{$log_id} deleted successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete log']);
    }
} catch (Exception $e) {
    error_log("Delete log error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred while deleting the log.']);
}
