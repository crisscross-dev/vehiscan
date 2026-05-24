<?php
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_admin_unified.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';
require_once __DIR__ . '/../includes/input_validator.php';
require_once __DIR__ . '/../includes/request_method_helper.php';
header('Content-Type: application/json');

function normalizeNamePart(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return preg_replace_callback('/\b([a-z])/', function ($m) {
        return strtoupper($m[1]);
    }, strtolower($value));
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

requireRequestMethod('POST');

// CSRF validation
$csrfToken = InputSanitizer::post('csrf_token', 'string');
if (!InputSanitizer::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Sanitize inputs
$id = InputSanitizer::post('id', 'int', 0);
$name = InputSanitizer::post('name', 'string');
$firstName = normalizeNamePart(InputSanitizer::post('first_name', 'string'));
$lastName = normalizeNamePart(InputSanitizer::post('last_name', 'string'));
$contact = InputSanitizer::post('contact', 'string');
$plate = InputSanitizer::post('plate_number', 'string');
$vehicle = InputSanitizer::post('vehicle_type', 'string');
$vehicleOther = InputSanitizer::post('vehicle_type_other', 'string');
$color = InputSanitizer::post('color', 'string');
$colorOther = InputSanitizer::post('color_other', 'string');

if ($firstName !== '' || $lastName !== '') {
    $name = trim($firstName . ' ' . $lastName);
}

// Validate inputs
if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name must be 2-100 characters']);
    exit;
}

if (!empty($plate)) {
    $plateCheck = InputValidator::validatePlateNumber($plate);
    if (!$plateCheck['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $plateCheck['message']]);
        exit;
    }
    $plate = $plateCheck['formatted'];
}

if (!empty($plate)) {
    $dupStmt = $pdo->prepare("SELECT id, name FROM homeowners WHERE plate_number = ? AND id <> ? LIMIT 1");
    $dupStmt->execute([$plate, $id]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
        http_response_code(409);
        $dupName = htmlspecialchars($duplicate['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        echo json_encode(['success' => false, 'message' => 'Plate number already linked to homeowner: ' . $dupName]);
        exit;
    }
}

if (!empty($contact)) {
    $phoneCheck = InputValidator::validatePhoneNumber($contact);
    if (!$phoneCheck['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $phoneCheck['message']]);
        exit;
    }
    $contact = $phoneCheck['formatted'];
}

try {
    if (strcasecmp($vehicle, 'Car') === 0) {
        $vehicle = 'Sedan';
    }
    $allowedVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'E-bike', 'Truck', 'Other'];
    if (!empty($vehicle) && !in_array($vehicle, $allowedVehicleTypes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle type']);
        exit;
    }
    if ($vehicle === 'Other') {
        $vehicle = trim($vehicleOther);
        if ($vehicle === '' || strlen($vehicle) > 40) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle type (max 40 characters)']);
            exit;
        }
    }

    $allowedColors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Yellow', 'Orange', 'Other'];
    if (!empty($color) && !in_array($color, $allowedColors, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle color']);
        exit;
    }
    if ($color === 'Other') {
        $color = trim($colorOther);
        if ($color === '' || strlen($color) > 30) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle color (max 30 characters)']);
            exit;
        }
    }

    $homeownerColumns = [];
    try {
        $homeownerColumns = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $homeownerColumns = [];
    }

    $setClauses = ['name=?', 'contact_number=?', 'plate_number=?', 'vehicle_type=?'];
    $params = [$name, $contact, $plate, $vehicle];

    if (in_array('color', $homeownerColumns, true)) {
        $setClauses[] = 'color=?';
        $params[] = $color;
    }

    $hasSplitNames = in_array('first_name', $homeownerColumns, true) && in_array('last_name', $homeownerColumns, true);
    if ($hasSplitNames) {
        $setClauses[] = 'first_name=?';
        $setClauses[] = 'last_name=?';
        $params[] = $firstName;
        $params[] = $lastName;
    }

    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE homeowners SET " . implode(', ', $setClauses) . " WHERE id=?");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => 'Record updated']);
} catch (Exception $e) {
    error_log('[HOMEOWNER_SAVE] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred']);
}
