<?php
/**
 * Bulk Employee Actions API
 * 
 * Handles bulk suspension and activation of user accounts.
 * Accessible by admins and super admins.
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';

header('Content-Type: application/json');

// Security check: Only admins and super admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

requireRequestMethod('POST');

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) ? (array)$input['ids'] : [];
$action = isset($input['action']) ? (string)$input['action'] : '';
$csrfToken = isset($input['csrf_token']) ? (string)$input['csrf_token'] : '';

// Validate CSRF
if (!InputSanitizer::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No accounts selected']);
    exit();
}

if (!in_array($action, ['suspend', 'active'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action requested']);
    exit();
}

$statusMap = [
    'suspend' => 'suspended',
    'active' => 'active'
];
$targetStatus = $statusMap[$action];
$successCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    // Prepare update statement
    $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ? AND role != 'super_admin'");
    
    foreach ($ids as $userId) {
        $userId = (int)$userId;
        if (!$userId) continue;

        // Skip self
        if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
            continue;
        }

        $stmt->execute([$targetStatus, $userId]);
        if ($stmt->rowCount() > 0) {
            $successCount++;
            // Log audit
            try {
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (username, action, table_name, ip_address, created_at) VALUES (?, ?, 'users', ?, NOW())");
                $auditStmt->execute([
                    $_SESSION['username'] ?? 'System',
                    "BULK_" . strtoupper($action) . " (User ID: $userId)",
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);
            } catch (Exception $e) {
                // Ignore audit errors to prevent breaking the transaction
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => $successCount > 0,
        'message' => "Successfully updated $successCount accounts to $targetStatus.",
        'count' => $successCount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[BULK_EMPLOYEE] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again later.']);
}
