<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

// Authorization check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("
         SELECT vp.*,
             h.name as homeowner_name,
               h.address,
               h.contact_number as homeowner_contact
        FROM visitor_passes vp
         INNER JOIN homeowners h ON vp.homeowner_id = h.id
         WHERE vp.status = 'pending' AND h.account_status = 'approved'
        ORDER BY vp.created_at DESC
        LIMIT 200
    ");
    
    echo json_encode($stmt->fetchAll());
    
} catch (PDOException $e) {
    error_log("Get pending passes error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load pending passes']);
}
