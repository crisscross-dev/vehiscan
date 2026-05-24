<?php
/**
 * Add new vehicle for homeowner
 */
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['homeowner_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'error' => 'Unauthorized']);
    exit();
}

requireRequestMethod('POST');

// Validate CSRF token using InputSanitizer
$csrfToken = InputSanitizer::post('csrf_token', 'string');
if (!InputSanitizer::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'error' => 'Invalid CSRF token']);
    exit();
}

try {
    $vehicleType = InputSanitizer::post('vehicle_type', 'string');
    $vehicleTypeOther = InputSanitizer::post('vehicle_type_other', 'string');
    $color = InputSanitizer::post('color', 'string');
    $colorOther = InputSanitizer::post('color_other', 'string');
    $plateNumber = strtoupper(InputSanitizer::post('plate_number', 'string'));
    $isPrimary = InputSanitizer::post('is_primary', 'bool', false);
    
    // Validate required fields
    if (empty($vehicleType) || empty($color) || empty($plateNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required', 'error' => 'All fields are required']);
        exit();
    }

    $vehicleType = trim($vehicleType);
    if (strcasecmp($vehicleType, 'Car') === 0) {
        $vehicleType = 'Sedan';
    }

    $allowedVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'E-bike', 'Truck', 'Other'];
    if ($vehicleType === 'Other') {
        $vehicleType = trim($vehicleTypeOther);
        if ($vehicleType === '' || strlen($vehicleType) > 40) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle type (max 40 characters)', 'error' => 'Please provide a valid custom vehicle type (max 40 characters)']);
            exit();
        }
    } elseif (!in_array($vehicleType, $allowedVehicleTypes, true)) {
        // Backward compatibility: accept legacy/custom values from older clients.
        if ($vehicleType === '' || strlen($vehicleType) > 40) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle type', 'error' => 'Invalid vehicle type']);
            exit();
        }
    }

    $color = trim($color);
    $allowedColors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Yellow', 'Orange', 'Other'];
    if ($color === 'Other') {
        $color = trim($colorOther);
        if ($color === '' || strlen($color) > 30) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle color (max 30 characters)', 'error' => 'Please provide a valid custom vehicle color (max 30 characters)']);
            exit();
        }
    } elseif (!in_array($color, $allowedColors, true)) {
        // Backward compatibility: accept legacy/custom values from older clients.
        if ($color === '' || strlen($color) > 30) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle color', 'error' => 'Invalid vehicle color']);
            exit();
        }
    }

    $plateValidation = InputValidator::validatePlateNumber($plateNumber);
    if (!$plateValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $plateValidation['message'], 'error' => $plateValidation['message']]);
        exit();
    }
    $plateNumber = $plateValidation['formatted'];
    
    // Check for duplicate plate number
    $stmt = $pdo->prepare("SELECT 1 FROM vehicles WHERE plate_number = ? LIMIT 1");
    $stmt->execute([$plateNumber]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This plate number is already registered', 'error' => 'This plate number is already registered']);
        exit();
    }

    // Keep primary state deterministic: first active vehicle becomes primary by default.
    if (!$isPrimary) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE homeowner_id = ? AND is_active = TRUE");
        $stmt->execute([$_SESSION['homeowner_id']]);
        $activeVehicleCount = (int)$stmt->fetchColumn();
        if ($activeVehicleCount === 0) {
            $isPrimary = true;
        }
    }
    
    // Handle vehicle image upload using InputSanitizer
    $vehicleImg = null;
    if (isset($_FILES['vehicle_img']) && $_FILES['vehicle_img']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadRes = InputSanitizer::validateFileUpload($_FILES['vehicle_img'], ['image/jpeg', 'image/png', 'image/webp'], 5 * 1024 * 1024);
        
        if (!$uploadRes['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $uploadRes['error'], 'error' => $uploadRes['error']]);
            exit();
        }
        
        $uploadDir = __DIR__ . '/../../uploads/vehicles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $originalName = $_FILES['vehicle_img']['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $filename = 'vehicle_' . uniqid('', true) . '.' . $ext;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['vehicle_img']['tmp_name'], $destination)) {
            $vehicleImg = 'vehicles/' . $filename;
        }
    }
    
    // If setting as primary, unset other primary vehicles
    if ($isPrimary) {
        $pdo->prepare("UPDATE vehicles SET is_primary = FALSE WHERE homeowner_id = ?")
            ->execute([$_SESSION['homeowner_id']]);
    }
    
    // Insert new vehicle
    // Insert new vehicle
    $stmt = $pdo->prepare("
        INSERT INTO vehicles (homeowner_id, vehicle_type, color, plate_number, is_primary, is_active, registered_at)
        VALUES (?, ?, ?, ?, ?, TRUE, NOW())
    ");

    $stmt->execute([
        $_SESSION['homeowner_id'],
        $vehicleType,
        $color,
        $plateNumber,
        $isPrimary
    ]);
        
    // Sync homeowners table if this is the new primary vehicle
    if ($isPrimary) {
        $stmt = $pdo->prepare("
            UPDATE homeowners 
            SET plate_number = ?, 
                vehicle_type = ?, 
                color = ?
                " . ($vehicleImg !== null ? ", car_img = ?" : "") . "
            WHERE id = ?
        ");
        
        $params = [$plateNumber, $vehicleType, $color];
        if ($vehicleImg !== null) $params[] = $vehicleImg;
        $params[] = $_SESSION['homeowner_id'];
        
        $stmt->execute($params);
    } elseif ($vehicleImg !== null) {
        // Just update image if it's currently empty
        $stmt = $pdo->prepare("UPDATE homeowners SET car_img = ? WHERE id = ? AND (car_img IS NULL OR car_img = '')");
        $stmt->execute([$vehicleImg, $_SESSION['homeowner_id']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Vehicle added successfully',
        'vehicle_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("Add vehicle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add vehicle. Please try again later.',
        'error' => 'Failed to add vehicle. Please try again later.'
    ]);
}
