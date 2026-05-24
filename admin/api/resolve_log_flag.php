<?php
/**
 * Resolve Log Flag API (Admin)
 * 
 * Allows admins to resolve flags raised by guards.
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

requireRequestMethod('POST');

// Get raw input
$input = json_decode(file_get_contents('php://input'), true);
$flagId = (int)($input['flag_id'] ?? 0);
$logId = (int)($input['log_id'] ?? 0);
$csrfToken = (string)($input['csrf_token'] ?? '');

if (!$csrfToken || !hash_equals($csrfToken, $_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

if ($flagId <= 0 || $logId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Check if flag exists
    $stmt = $pdo->prepare("SELECT status FROM guard_log_flags WHERE id = ? AND log_id = ?");
    $stmt->execute([$flagId, $logId]);
    $flag = $stmt->fetch();

    if (!$flag) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Flag not found']);
        exit();
    }

    if ($flag['status'] === 'resolved') {
        echo json_encode(['success' => true, 'message' => 'Already resolved']);
        exit();
    }

    // Resolve it
    $stmt = $pdo->prepare("UPDATE guard_log_flags SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$flagId]);

    // Audit log
    try {
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (username, action, table_name, record_id, ip_address) VALUES (?, ?, 'guard_log_flags', ?, ?)");
        $auditStmt->execute([
            $_SESSION['username'],
            'RESOLVE_FLAG',
            $flagId,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'Flag resolved successfully']);

} catch (Exception $e) {
    error_log("[RESOLVE_FLAG] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
