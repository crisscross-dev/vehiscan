<?php
ob_start();
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['homeowner_id'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT vp.*,
               0 AS scan_count, 
               NULL AS first_scanned_at, 
               NULL AS last_scanned_at,
               CASE
                   WHEN vp.status IN ('active', 'approved') AND NOW() > vp.valid_until THEN 'expired'
                   WHEN vp.status IN ('active', 'approved') AND NOW() < vp.valid_from THEN 'upcoming'
                   ELSE vp.status
               END AS display_status
        FROM visitor_passes vp
        WHERE vp.homeowner_id = ?
        ORDER BY vp.created_at DESC
    ");
    $stmt->execute([$_SESSION['homeowner_id']]);
    $passes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'passes' => $passes
    ]);
} catch (Exception $e) {
    ob_end_clean();
    error_log('Error fetching visitor passes: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load visitor passes'
    ]);
}
