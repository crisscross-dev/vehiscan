<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

require_once __DIR__ . '/../../includes/session_homeowner.php';

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/cache_invalidator.php';

// Rate limiting check (10 visitor passes per hour per homeowner)
$homeownerId = $_SESSION['homeowner_id'];
$rateLimiter = new RateLimiter($pdo);
$rateCheck = $rateLimiter->check("homeowner_$homeownerId", 'visitor_pass', 10, 60);

if (!$rateCheck['allowed']) {
    $resetTs = strtotime((string)($rateCheck['reset_time'] ?? ''));
    $minutesLeft = $resetTs ? max(1, (int)ceil(($resetTs - time()) / 60)) : 60;
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Too many visitor pass requests. Please try again in {$minutesLeft} minutes."
    ]);
    exit();
}

// Safety check: homeowner must still be approved before creating a pass.
$accountCheck = $pdo->prepare("SELECT account_status FROM homeowners WHERE id = ? LIMIT 1");
$accountCheck->execute([$homeownerId]);
$account = $accountCheck->fetch(PDO::FETCH_ASSOC);
if (!$account || ($account['account_status'] ?? '') !== 'approved') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Account is not approved for visitor pass requests.'
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

// Validate CSRF token (timing-safe comparison)
if (!InputSanitizer::validateCsrf((string)($data['csrf_token'] ?? ''))) {
    http_response_code(403);
    $rateLimiter->recordAttempt("homeowner_$homeownerId", 'visitor_pass', ['error' => 'csrf_token']);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Validate input
$visitor_name = trim($data['visitor_name'] ?? '');
$purpose = trim($data['purpose'] ?? '');
$visitor_plate = strtoupper(trim($data['visitor_plate'] ?? ''));
$valid_from = $data['valid_from'] ?? '';
$valid_until = $data['valid_until'] ?? '';

// Validate required fields
if (empty($visitor_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name is required']);
    exit();
}

if (strlen($visitor_name) < 2 || strlen($visitor_name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name must be 2-100 characters']);
    exit();
}

if (empty($purpose)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Purpose is required']);
    exit();
}

if (strlen($purpose) < 3 || strlen($purpose) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Purpose must be 3-500 characters']);
    exit();
}

// Validate plate number if provided
if (!empty($visitor_plate)) {
    $plateValidation = InputValidator::validatePlateNumber($visitor_plate);
    if (!$plateValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $plateValidation['message']]);
        exit();
    }
    $visitor_plate = $plateValidation['formatted'];
}

if (empty($valid_from)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid from date is required']);
    exit();
}

$from = strtotime($valid_from);
$now = time();
$fiveMinutesAgo = $now - (5 * 60); // Allow 5-minute grace period

if ($from === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Please use the date picker.']);
    exit();
}

// Same-day only: valid until must fall on the same local date as valid from.
$fromDate = date('Y-m-d', $from);
$dayEnd = strtotime($fromDate . ' 23:59:59');
$until = $dayEnd;

if (!empty($valid_until)) {
    $submittedUntil = strtotime($valid_until);
    if ($submittedUntil === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid end date format. Please use the date picker.']);
        exit();
    }

    if (date('Y-m-d', $submittedUntil) !== $fromDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Visitor passes are valid for the selected day only.']);
        exit();
    }
}

if ($dayEnd === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unable to determine the pass expiration time.']);
    exit();
}

$until = $dayEnd;

// Allow dates within last 5 minutes (form filling time)
if ($from < $fiveMinutesAgo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date cannot be more than 5 minutes in the past']);
    exit();
}

// Check minimum duration (30 minutes)
$durationMinutes = ($until - $from) / 60;
if ($durationMinutes < 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visit duration must be at least 30 minutes']);
    exit();
}

// Enforce same-day validity window only.
if (date('Y-m-d', $until) !== $fromDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor passes must expire on the same day they start.']);
    exit();
}

try {
    // Generate QR token
    $qr_token = bin2hex(random_bytes(32));

    // Insert visitor pass
    $stmt = $pdo->prepare("
        INSERT INTO visitor_passes 
        (homeowner_id, visitor_name, purpose, visitor_plate, valid_from, valid_until, qr_token, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $_SESSION['homeowner_id'],
        $visitor_name,
        $purpose,
        $visitor_plate ?: null,
        date('Y-m-d H:i:s', $from),
        date('Y-m-d H:i:s', $until),
        $qr_token
    ]);

    $pass_id = $pdo->lastInsertId();

    $rateLimiter->recordAttempt("homeowner_$homeownerId", 'visitor_pass', [
        'status' => 'created',
        'pass_id' => $pass_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Visitor pass created successfully',
        'pass_id' => $pass_id
    ]);

} catch (PDOException $e) {
    error_log("Create visitor pass error: " . $e->getMessage());

    // Record failed attempt
    $rateLimiter->recordAttempt("homeowner_$homeownerId", 'visitor_pass', [
        'error' => 'database_error'
    ]);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
