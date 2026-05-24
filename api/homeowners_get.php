<?php
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_admin_unified.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';
require_once __DIR__ . '/../includes/request_method_helper.php';
header('Content-Type: application/json');

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, name, contact_number, plate_number, vehicle_type FROM homeowners ORDER BY id DESC LIMIT 1000");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log('[HOMEOWNERS_GET] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load homeowners']);
}
