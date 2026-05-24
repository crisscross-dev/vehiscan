<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// CSRF validation
requireRequestMethod('POST');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}
$csrf = $data['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
if (!InputSanitizer::validateCsrf((string)$csrf)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

try {
    // Get random homeowners
    $stmt = $pdo->query("SELECT plate_number FROM homeowners ORDER BY RAND() LIMIT 5");
    $plates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($plates)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No homeowners found']);
        exit;
    }
    
    $count = 0;
    $statuses = ['IN', 'IN', 'IN', 'OUT']; // 75% entries
    
    foreach ($plates as $plate) {
        $status = $statuses[array_rand($statuses)];
        $log_time = date('H:i:s', strtotime('-' . rand(0, 60) . ' minutes'));
        
        $stmt = $pdo->prepare("INSERT INTO recent_logs (plate_number, status, log_time) VALUES (?, ?, ?)");
        $stmt->execute([$plate, $status, $log_time]);
        $count++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Generated $count demo log entries"
    ]);
} catch (Exception $e) {
    error_log('Demo logs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate logs']);
}
