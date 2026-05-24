<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$input = [];
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
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

$normalize = static function ($value): string {
    $text = trim((string)$value);
    $text = preg_replace('/\s+/', ' ', $text ?? '');
    return trim((string)$text);
};

$incomingTitle = array_key_exists('dashboard_title', $input) ? $normalize($input['dashboard_title']) : null;
$incomingDisplayName = array_key_exists('display_name', $input) ? $normalize($input['display_name']) : null;

if ($incomingTitle === null && $incomingDisplayName === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No fields provided']);
    exit();
}

$defaultTitle = 'VehiScan';
$defaultDisplayName = trim((string)($_SESSION['username'] ?? 'Guard'));
if ($defaultDisplayName === '') {
    $defaultDisplayName = 'Guard';
}

try {
    // Check if table exists for UI preferences
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'guard_ui_preferences'");
    $tableExists = (bool)$tableExistsStmt->fetchColumn();
    
    if (!$tableExists) {
        // Table doesn't exist - try to create it (for development)
        // On restrictive hosting, this may fail gracefully
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS guard_ui_preferences (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                dashboard_title VARCHAR(80) NOT NULL DEFAULT 'VehiScan',
                display_name VARCHAR(80) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_guard_ui_preferences_user (user_id),
                KEY idx_guard_ui_preferences_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) {
            // CREATE TABLE failed (likely permission issue on restrictive hosting)
            // Return defaults without persisting
            error_log('[UI_PREFS] Table creation failed: ' . $e->getMessage());
            // Proceed with defaults only - don't persist
            $existing = [];
        }
    }

    $stmt = $pdo->prepare('SELECT dashboard_title, display_name FROM guard_ui_preferences WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $dashboardTitle = $incomingTitle ?? trim((string)($existing['dashboard_title'] ?? $defaultTitle));
    $displayName = $incomingDisplayName ?? trim((string)($existing['display_name'] ?? $defaultDisplayName));

    if ($dashboardTitle === '') {
        $dashboardTitle = $defaultTitle;
    }
    if ($displayName === '') {
        $displayName = $defaultDisplayName;
    }

    if (strlen($dashboardTitle) < 3 || strlen($dashboardTitle) > 80) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Dashboard title must be 3-80 characters']);
        exit();
    }

    if (strlen($displayName) < 2 || strlen($displayName) > 80) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Guard name must be 2-80 characters']);
        exit();
    }

    $upsert = $pdo->prepare(
        'INSERT INTO guard_ui_preferences (user_id, dashboard_title, display_name) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE dashboard_title = VALUES(dashboard_title), display_name = VALUES(display_name), updated_at = CURRENT_TIMESTAMP'
    );
    $upsert->execute([$userId, $dashboardTitle, $displayName]);

    echo json_encode([
        'success' => true,
        'data' => [
            'dashboard_title' => $dashboardTitle,
            'display_name' => $displayName
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save preferences']);
}
