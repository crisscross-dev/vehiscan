<?php
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

requireRequestMethod('POST');

$csrf = $_POST['csrf_token'] ?? '';
if (!InputSanitizer::validateCsrf((string)$csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$homeownerId = (int)($_POST['homeowner_id'] ?? 0);
$visitorName = strtoupper(trim((string)($_POST['visitor_name'] ?? '')));
$visitorPlate = strtoupper(trim((string)($_POST['visitor_plate'] ?? '')));
$purpose = trim((string)($_POST['purpose'] ?? ''));
$validFrom = trim((string)($_POST['valid_from'] ?? ''));
$validUntil = trim((string)($_POST['valid_until'] ?? ''));

if ($homeownerId <= 0 || $visitorName === '' || $purpose === '' || $validFrom === '' || $validUntil === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please complete all required fields']);
    exit;
}

if (strlen($visitorName) < 2 || strlen($visitorName) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name must be 2-100 characters']);
    exit;
}

if (strlen($purpose) < 3 || strlen($purpose) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Purpose must be 3-500 characters']);
    exit;
}

if ($visitorPlate !== '') {
    $plateValidation = InputValidator::validatePlateNumber($visitorPlate);
    if (!$plateValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $plateValidation['message']]);
        exit;
    }
    $visitorPlate = $plateValidation['formatted'];
}

$fromTs = strtotime($validFrom);
$untilTs = strtotime($validUntil);
$now = time();
$fiveMinutesAgo = $now - (5 * 60);

if ($fromTs === false || $untilTs === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}
if ($untilTs <= $fromTs) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid until date must be after valid from date']);
    exit;
}

if (date('Y-m-d', $untilTs) !== date('Y-m-d', $fromTs)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor passes must expire on the same day they start']);
    exit;
}

$untilTs = strtotime(date('Y-m-d', $fromTs) . ' 23:59:59');
if ($untilTs === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unable to determine expiration time']);
    exit;
}

if ($fromTs < $fiveMinutesAgo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date cannot be more than 5 minutes in the past']);
    exit;
}

$durationMinutes = ($untilTs - $fromTs) / 60;
if ($durationMinutes < 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visit duration must be at least 30 minutes']);
    exit;
}
try {
    $homeownerStmt = $pdo->prepare('SELECT id, name, account_status FROM homeowners WHERE id = ? LIMIT 1');
    $homeownerStmt->execute([$homeownerId]);
    $homeowner = $homeownerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$homeowner || ($homeowner['account_status'] ?? '') !== 'approved') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Selected homeowner is missing or not approved']);
        exit;
    }

    $qrToken = bin2hex(random_bytes(32));

    $insert = $pdo->prepare("\n        INSERT INTO visitor_passes\n        (homeowner_id, visitor_name, purpose, visitor_plate, valid_from, valid_until, qr_token, status, created_at)\n        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())\n    ");
    $insert->execute([
        $homeownerId,
        $visitorName,
        $purpose,
        $visitorPlate !== '' ? $visitorPlate : null,
        date('Y-m-d H:i:s', $fromTs),
        date('Y-m-d H:i:s', $untilTs),
        $qrToken
    ]);

    $passId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Visitor pass request submitted for approval',
        'pass_id' => $passId
    ]);
} catch (Throwable $e) {
    error_log('[GUARD_CREATE_VISITOR_REQUEST] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create visitor request']);
}
