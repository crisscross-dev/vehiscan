<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pendingHomeowners = (int)$pdo->query("SELECT COUNT(*) FROM homeowners WHERE account_status = 'pending'")->fetchColumn();
    $pendingUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'pending'")->fetchColumn();
    $pendingAccounts = $pendingHomeowners + $pendingUsers;

    $profileRequests = 0;
    $profileTableExists = (bool)$pdo->query("SHOW TABLES LIKE 'profile_update_requests'")->fetchColumn();
    if ($profileTableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM profile_update_requests WHERE status IN ('pending', 'acknowledged')");
        $profileRequests = (int)$stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'pending_accounts' => $pendingAccounts,
        'pending_profile_requests' => $profileRequests,
        'total_pending_actions' => $pendingAccounts + $profileRequests,
        'profile_requests_enabled' => $profileTableExists
    ]);
} catch (Throwable $e) {
    error_log('[PENDING_APPROVAL_OVERVIEW] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load approval overview']);
}
