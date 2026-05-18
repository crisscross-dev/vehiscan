<?php
/**
 * Check for new access logs
 * Returns latest log ID and count of new logs
 */
require_once __DIR__ . '/../../includes/security_headers.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

try {
    // Get latest log ID
    $stmt = $pdo->query("SELECT MAX(log_id) as latest_id, COUNT(*) as total FROM recent_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $latestId = (int)($result['latest_id'] ?? 0);
    $total = (int)($result['total'] ?? 0);
    
    // Get last seen ID from request (sanitized)
    $lastSeenId = InputSanitizer::get('last_seen', 'int', 0);
    
    // Calculate new count
    if ($lastSeenId > 0 && $latestId > $lastSeenId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recent_logs WHERE log_id > ?");
        $stmt->execute([$lastSeenId]);
        $newCount = (int)$stmt->fetchColumn();
    } else {
        $newCount = 0;
    }
    
    echo json_encode([
        'success' => true,
        'latest_log_id' => $latestId,
        'total_logs' => $total,
        'new_count' => $newCount,
        'has_new' => $newCount > 0
    ]);
    
} catch (Exception $e) {
    error_log('Check new logs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check logs'
    ]);
}
