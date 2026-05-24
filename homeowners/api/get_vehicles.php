<?php
/**
 * Get all vehicles for logged-in homeowner
 * Uses the canonical 'vehicles' table and joins with homeowners for images
 */
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
    // Get all vehicles for this homeowner from the canonical vehicles table
    // Join with homeowners to get the car_img
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.vehicle_type,
            v.color,
            v.plate_number,
            h.car_img AS vehicle_img,
            v.brand,
            v.model,
            v.year,
            v.is_primary,
            v.is_active,
            v.rfid_uid,
            v.registered_at as created_at
        FROM vehicles v
        LEFT JOIN homeowners h ON h.id = v.homeowner_id
        WHERE v.homeowner_id = ? AND v.is_active = 1
        ORDER BY v.is_primary DESC, v.registered_at DESC
    ");
    
    $stmt->execute([$_SESSION['homeowner_id']]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Get vehicles error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch vehicles. Please try again later.',
        'error' => 'Failed to fetch vehicles. Please try again later.'
    ]);
}
