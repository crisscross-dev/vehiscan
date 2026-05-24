<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('POST');

if (!in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
require_once __DIR__ . '/qr_helper.php';

AuditLogger::init($pdo);

// Validate CSRF token using InputSanitizer
$posted = InputSanitizer::post('csrf_token', 'string');
if (!InputSanitizer::validateCsrf($posted)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Sanitize all inputs
$homeowner_id = InputSanitizer::post('homeowner_id', 'int', 0);
$visitor_name = InputSanitizer::post('visitor_name', 'string');
$visitor_plate = strtoupper(InputSanitizer::post('visitor_plate', 'string'));
$purpose = InputSanitizer::post('purpose', 'string');
$valid_from = InputSanitizer::post('valid_from', 'string');
$valid_until = InputSanitizer::post('valid_until', 'string');
$is_recurring = InputSanitizer::post('is_recurring', 'int', 0);

if (!$visitor_name || !$visitor_plate || !$valid_from) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name, plate, and start date are required']);
    exit;
}

// Validate visitor name length
if (strlen($visitor_name) < 2 || strlen($visitor_name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name must be 2-100 characters']);
    exit;
}

$plateValidation = InputValidator::validatePlateNumber($visitor_plate);
if (!$plateValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $plateValidation['message']]);
    exit;
}
$visitor_plate = $plateValidation['formatted'];

// Validate date formats and logic
$fromTs = strtotime($valid_from);

if ($fromTs === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (!empty($valid_until)) {
    $submittedUntil = strtotime($valid_until);
    if ($submittedUntil === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid end date format']);
        exit;
    }

    if (date('Y-m-d', $submittedUntil) !== date('Y-m-d', $fromTs)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Visitor passes must expire on the same day they start']);
        exit;
    }
}

$untilTs = strtotime(date('Y-m-d', $fromTs) . ' 23:59:59');
if ($untilTs === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to determine expiration time']);
    exit;
}

// Prevent passes that are too short to be useful.
if ((($untilTs - $fromTs) / 60) < 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visit duration must be at least 30 minutes']);
    exit;
}

// Don't allow start dates more than 5 minutes in the past
$fiveMinutesAgo = time() - (5 * 60);
if ($fromTs < $fiveMinutesAgo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past']);
    exit;
}

try {
    // Generate unique token for QR code
    $qrToken = generateSecureToken();
    
    $stmt = $pdo->prepare("
        INSERT INTO visitor_passes 
        (homeowner_id, visitor_name, visitor_plate, purpose, valid_from, valid_until, is_recurring, qr_token, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$homeowner_id, $visitor_name, $visitor_plate, $purpose, $valid_from, date('Y-m-d H:i:s', $untilTs), $is_recurring, $qrToken]);
    
    $passId = $pdo->lastInsertId();
    
    // Generate QR code
    $qrCode = generateVisitorPassQR($passId, $qrToken, $pdo);
    
    if ($qrCode) {
        // Update pass with QR code
        $stmt = $pdo->prepare("UPDATE visitor_passes SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qrCode, $passId]);
    }

    try {
        AuditLogger::logDataChange('visitor_pass_created', 'visitor_passes', (int)$passId, null, [
            'homeowner_id' => $homeowner_id,
            'visitor_name' => $visitor_name,
            'visitor_plate' => $visitor_plate,
            'purpose' => $purpose,
            'valid_from' => $valid_from,
            'valid_until' => date('Y-m-d H:i:s', $untilTs),
            'is_recurring' => (int)$is_recurring,
            'status' => 'pending',
            'qr_code' => !empty($qrCode),
        ]);
    } catch (Exception $auditError) {
        error_log('Create visitor pass audit error: ' . $auditError->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Visitor pass created successfully']);
} catch (PDOException $e) {
    error_log("Create pass error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
