<?php
/**
 * Check for pending approvals count
 * Returns count of homeowner accounts pending approval
 */
require_once __DIR__ . '/../../includes/security_headers.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

try {
    // Count all pending account approvals (homeowners + users)
    $homeownersPending = (int)$pdo->query(" 
        SELECT COUNT(*) 
        FROM homeowners 
        WHERE account_status = 'pending'
    ")->fetchColumn();

    $usersPending = (int)$pdo->query(" 
        SELECT COUNT(*) 
        FROM users 
        WHERE account_status = 'pending'
    ")->fetchColumn();

    $pendingCount = $homeownersPending + $usersPending;

    echo json_encode([
        'success' => true,
        'pending_count' => $pendingCount
    ]);

} catch (Exception $e) {
    error_log('Check pending approvals error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check approvals. Please try again later.'
    ]);
}
