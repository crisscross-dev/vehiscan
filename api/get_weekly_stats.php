<?php
/**
 * Weekly Stats API Endpoint
 * Returns 7-day activity data for dashboard chart
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/security_headers.php';

// Security: Role-based access control
require_once __DIR__ . '/../includes/session_admin_unified.php';
require_once __DIR__ . '/../includes/request_method_helper.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../db.php';

requireRequestMethod('GET');

try {
    // Get last 7 days of data in a single query
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as log_date, COUNT(*) as cnt
        FROM recent_logs
        WHERE DATE(created_at) >= ?
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$startDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $labels = [];
    $values = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('D', strtotime($date));
        $values[] = (int)($rows[$date] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    error_log('Weekly stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch weekly stats'
    ]);
}
