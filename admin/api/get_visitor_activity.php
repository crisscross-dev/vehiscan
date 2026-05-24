<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
header('Content-Type: application/json');

requireRequestMethod('GET');

require_once __DIR__ . '/../../includes/session_admin_unified.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM recent_logs 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$days]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'activity' => $activity]);
} catch (PDOException $e) {
    error_log('Visitor activity query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
}
