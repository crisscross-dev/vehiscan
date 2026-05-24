<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$defaultTitle = 'VehiScan';
$defaultDisplayName = trim((string)($_SESSION['username'] ?? 'Guard'));
if ($defaultDisplayName === '') {
    $defaultDisplayName = 'Guard';
}

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'guard_ui_preferences'");
    $tableExists = (bool)$tableExistsStmt->fetchColumn();

    if (!$tableExists) {
        echo json_encode([
            'success' => true,
            'data' => [
                'dashboard_title' => $defaultTitle,
                'display_name' => $defaultDisplayName
            ]
        ]);
        exit();
    }

    $stmt = $pdo->prepare('SELECT dashboard_title, display_name FROM guard_ui_preferences WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $dashboardTitle = trim((string)($row['dashboard_title'] ?? $defaultTitle));
    $displayName = trim((string)($row['display_name'] ?? $defaultDisplayName));

    if ($dashboardTitle === '') {
        $dashboardTitle = $defaultTitle;
    }
    if ($displayName === '') {
        $displayName = $defaultDisplayName;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'dashboard_title' => $dashboardTitle,
            'display_name' => $displayName
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load preferences',
        'data' => [
            'dashboard_title' => $defaultTitle,
            'display_name' => $defaultDisplayName
        ]
    ]);
}
