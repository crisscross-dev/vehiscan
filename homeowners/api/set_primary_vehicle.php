<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
header('Content-Type: application/json');

requireRequestMethod('POST');

require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';

$homeownerId = $_SESSION['homeowner_id'];

$rawInput = file_get_contents('php://input');
$data = [];

if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

$vehicleId = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : 0;

// Validate CSRF token
$csrfToken = $data['csrf_token'] ?? '';
if (!InputSanitizer::validateCsrf((string)$csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

if ($vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
    exit();
}

try {
    $pdo->beginTransaction();

    // First, unset all other primary vehicles for this homeowner
    $pdo->prepare("UPDATE vehicles SET is_primary = FALSE WHERE homeowner_id = ? AND is_active = 1")->execute([$homeownerId]);
    
    // Set this vehicle as primary
    $stmt = $pdo->prepare("UPDATE vehicles SET is_primary = TRUE WHERE id = ? AND homeowner_id = ? AND is_active = 1");
    $stmt->execute([$vehicleId, $homeownerId]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vehicle not found or inactive']);
        exit();
    }

    // Get the new primary vehicle details to sync with homeowners table
    $stmt = $pdo->prepare("SELECT plate_number, vehicle_type, color FROM vehicles WHERE id = ?");
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vehicle) {
        // Update homeowners table for backward compatibility/quick access
        $updateHomeowner = $pdo->prepare("
            UPDATE homeowners 
            SET plate_number = ?, vehicle_type = ?, color = ? 
            WHERE id = ?
        ");
        $updateHomeowner->execute([
            $vehicle['plate_number'],
            $vehicle['vehicle_type'],
            $vehicle['color'],
            $homeownerId
        ]);
    }

    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Primary vehicle updated']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
}
