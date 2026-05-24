<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

// CSRF token validation using InputSanitizer
$csrfToken = InputSanitizer::post('csrf_token', 'string');
if (!InputSanitizer::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request token']);
    exit();
}

$homeownerId = (int) ($_SESSION['homeowner_id'] ?? 0);
if ($homeownerId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$requestText = InputSanitizer::post('request_text', 'string');
$draftPayloadRaw = isset($_POST['draft_payload']) ? trim((string) $_POST['draft_payload']) : '';

if (empty($requestText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please describe the changes you would like to request.']);
    exit();
}

if (mb_strlen($requestText) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request description is too long (maximum 2000 characters).']);
    exit();
}

$draftPayloadJson = null;
if ($draftPayloadRaw !== '') {
    $draftPayloadJson = json_decode($draftPayloadRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($draftPayloadJson)) {
        error_log('[ProfileRequest] Ignoring invalid draft payload for homeowner ' . $homeownerId);
        $draftPayloadJson = null;
    }
}

try {
    // Rate limit submissions: max 3 requests per hour per homeowner.
    $limiter = new RateLimiter($pdo);
    $rateKey = 'homeowner_' . $homeownerId;
    $rate = $limiter->check($rateKey, 'profile_request_submit', 3, 60);
    if (!$rate['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait before submitting another profile update request.'
        ]);
        exit();
    }

    // Check if homeowner already has an open request or a just-completed recent request.
    $stmt = $pdo->prepare("
        SELECT id FROM profile_update_requests
        WHERE homeowner_id = ?
          AND (
              status IN ('pending', 'acknowledged')
              OR (status = 'completed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR))
          )
        LIMIT 1
    ");
    $stmt->execute([$homeownerId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message'  => 'You already have an open request. Please wait for it to be reviewed before submitting a new one.'
        ]);
        exit();
    }

    // Handle file uploads for images
    if ($draftPayloadJson !== null) {
        $uploadDir = __DIR__ . '/../../uploads/profile_requests/';
        
        $images = ['owner_img', 'car_img'];
        foreach ($images as $imgKey) {
            if (isset($_FILES[$imgKey]) && $_FILES[$imgKey]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$imgKey]['name'], PATHINFO_EXTENSION);
                $newName = $homeownerId . '_' . $imgKey . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES[$imgKey]['tmp_name'], $uploadDir . $newName)) {
                    $draftPayloadJson[$imgKey] = 'profile_requests/' . $newName;
                }
            }
        }
    }

    // Insert the new request
    $stmt = $pdo->prepare("
        INSERT INTO profile_update_requests (homeowner_id, request_text, draft_payload, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$homeownerId, $requestText, $draftPayloadJson !== null ? json_encode($draftPayloadJson, JSON_UNESCAPED_UNICODE) : null]);
    $limiter->recordAttempt($rateKey, 'profile_request_submit', ['status' => 'submitted']);

    echo json_encode([
        'success' => true,
        'message' => 'Your request has been submitted. An administrator will review it and contact you if needed.'
    ]);

} catch (PDOException $e) {
    error_log('[ProfileRequest] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
