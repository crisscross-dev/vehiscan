<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || ($_SESSION['role'] ?? '') !== 'guard') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$input = [];
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

$postedToken = (string)($input['csrf_token'] ?? '');
if (!InputSanitizer::validateCsrf($postedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$logId = (int)($input['log_id'] ?? 0);
$plate = strtoupper(trim((string)($input['plate_number'] ?? '')));
$reason = trim((string)($input['reason'] ?? ''));
$reason = preg_replace('/\s+/', ' ', $reason ?? '');

if ($logId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid log entry']);
    exit();
}

if ($reason !== '' && strlen($reason) > 255) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Reason must not exceed 255 characters']);
    exit();
}

try {
    // Check if table exists for flag functionality
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'guard_log_flags'");
    $tableExists = (bool)$tableExistsStmt->fetchColumn();
    
    if (!$tableExists) {
        // Table doesn't exist - try to create it (for development)
        // On restrictive hosting, this may fail gracefully
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS guard_log_flags (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                log_id BIGINT UNSIGNED NOT NULL,
                plate_number VARCHAR(32) NOT NULL,
                flagged_by_user_id INT UNSIGNED NOT NULL,
                reason VARCHAR(255) NULL,
                status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_guard_log_flags_log_id (log_id),
                KEY idx_guard_log_flags_plate_number (plate_number),
                KEY idx_guard_log_flags_flagged_by (flagged_by_user_id),
                KEY idx_guard_log_flags_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) {
            // CREATE TABLE failed (likely permission issue on restrictive hosting)
            // Return success with note that flagging is unavailable
            error_log('[FLAG_LOG] Table creation failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Flagging service temporarily unavailable']);
            exit();
        }
    }

    $logStmt = $pdo->prepare('SELECT log_id, plate_number FROM recent_logs WHERE log_id = ? LIMIT 1');
    $logStmt->execute([$logId]);
    $logRow = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$logRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Log entry not found']);
        exit();
    }

    $resolvedPlate = strtoupper(trim((string)($logRow['plate_number'] ?? '')));
    if ($plate === '') {
        $plate = $resolvedPlate;
    }

    if ($plate !== $resolvedPlate) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Log entry data mismatch']);
        exit();
    }

    $dupStmt = $pdo->prepare('SELECT id FROM guard_log_flags WHERE log_id = ? AND status = "open" LIMIT 1');
    $dupStmt->execute([$logId]);
    $existingOpen = $dupStmt->fetchColumn();
    if ($existingOpen) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This log is already flagged']);
        exit();
    }

    $insertStmt = $pdo->prepare('INSERT INTO guard_log_flags (log_id, plate_number, flagged_by_user_id, reason, status) VALUES (?, ?, ?, ?, "open")');
    $insertStmt->execute([$logId, $plate, $userId, $reason !== '' ? $reason : null]);

    echo json_encode([
        'success' => true,
        'message' => 'Log entry flagged for review',
        'data' => [
            'flag_id' => (int)$pdo->lastInsertId(),
            'log_id' => $logId,
            'plate_number' => $plate,
            'status' => 'open'
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to flag entry']);
}
