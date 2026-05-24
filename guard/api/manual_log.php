<?php
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

// Check role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guard', 'admin', 'super_admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = InputSanitizer::post('csrf_token', 'string') ?: ($jsonInput['csrf_token'] ?? '');

if (!InputSanitizer::validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$plateNumber = strtoupper(InputSanitizer::post('plate_number', 'string') ?: ($jsonInput['plate_number'] ?? ''));
$direction = InputSanitizer::post('direction', 'string') ?: ($jsonInput['direction'] ?? 'in');
$notes = InputSanitizer::post('notes', 'string') ?: ($jsonInput['notes'] ?? 'Manual log entry');

if (empty($plateNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Plate number is required']);
    exit;
}

if (!in_array($direction, ['in', 'out'])) {
    $direction = 'in';
}

try {
    // Check if plate matches any active vehicle
    $stmt = $pdo->prepare("
        SELECT v.id, v.vehicle_type, v.color, u.name as homeowner_name, u.id as homeowner_id 
        FROM vehicles v 
        LEFT JOIN users u ON v.owner_id = u.id 
        WHERE UPPER(v.plate_number) = ? 
        AND v.status = 'active'
    ");
    $stmt->execute([$plateNumber]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    // Also check visitor passes if no homeowner vehicle found
    $visitor = null;
    if (!$vehicle) {
        $stmt = $pdo->prepare("
            SELECT id, visitor_name, purpose 
            FROM visitor_passes 
            WHERE UPPER(visitor_plate) = ? 
            AND status IN ('active', 'approved') 
            AND valid_from <= NOW() 
            AND valid_until >= NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$plateNumber]);
        $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $status = 'granted';
    $accessType = 'unknown';

    if ($vehicle) {
        $accessType = 'homeowner';
    } elseif ($visitor) {
        $accessType = 'visitor';
        $pdo->prepare("UPDATE visitor_passes SET scan_count = scan_count + 1, last_scanned_at = NOW(), status = IF(status = 'approved', 'active', status) WHERE id = ?")->execute([$visitor['id']]);
    } else {
        $accessType = 'unregistered';
    }

    // Insert log
    $stmt = $pdo->prepare("
        INSERT INTO rfid_scan_log (rfid_uid, plate_number, access_type, direction, status, notes) 
        VALUES ('MANUAL_ENTRY', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$plateNumber, $accessType, $direction, $status, $notes]);

    echo json_encode([
        'success' => true,
        'message' => 'Manual log entry recorded successfully',
        'access_type' => $accessType,
        'matched_entity' => $vehicle ? $vehicle['homeowner_name'] : ($visitor ? $visitor['visitor_name'] : 'Unknown Vehicle')
    ]);
} catch (Exception $e) {
    error_log('[MANUAL_LOG] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
