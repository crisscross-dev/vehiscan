<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once __DIR__ . '/../../db.php';

try {
    // Check if visitor_passes table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'visitor_passes'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'passes' => [],
            'message' => 'Visitor passes table not yet created'
        ]);
        exit;
    }
    
    // Build query to show only currently valid visitor passes.
    // Keep active/approved passes visible only while the current time is inside the validity window.
    $query = "
        SELECT 
            vp.id,
            vp.visitor_name,
            vp.visitor_plate,
            vp.purpose,
            vp.valid_from,
            vp.valid_until,
            vp.status,
            vp.qr_code,
            vp.created_at,
            COALESCE(h.name, CONCAT(h.first_name, ' ', h.last_name)) as homeowner_name
        FROM visitor_passes vp
        LEFT JOIN homeowners h ON vp.homeowner_id = h.id
                WHERE vp.status IN ('active', 'approved')
                    AND NOW() BETWEEN vp.valid_from AND vp.valid_until
                ORDER BY vp.valid_until ASC, vp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $passes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'passes' => $passes,
        'count' => count($passes)
    ]);
    
} catch (Exception $e) {
    error_log('Visitor fetch error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch visitor passes'
    ]);
}
