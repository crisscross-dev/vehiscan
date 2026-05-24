<?php
/**
 * Check Plate Number Availability
 * Used during homeowner registration to validate plate uniqueness
 * Returns JSON with availability status
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$plateNumber = isset($_GET['plate']) ? trim($_GET['plate']) : '';

if (empty($plateNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Plate number required']);
    exit;
}

// Format and validate plate number
$plateNumber = strtoupper($plateNumber);
$plateNumber = preg_replace('/[^A-Z0-9\-]/', '', $plateNumber);

if (strlen($plateNumber) < 3 || strlen($plateNumber) > 7) {
    http_response_code(400);
    echo json_encode(['available' => false, 'message' => 'Invalid plate number format']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM homeowners WHERE plate_number = ? LIMIT 1");
    $stmt->execute([$plateNumber]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode([
            'available' => false,
            'message' => 'Plate number already registered to: ' . htmlspecialchars($existing['name'] ?? 'Unknown'),
            'success' => true
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message' => 'Plate number available',
            'success' => true
        ]);
    }
} catch (Exception $e) {
    error_log('Plate check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to verify plate number'
    ]);
}
?>
