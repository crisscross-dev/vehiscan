<?php
/**
 * RFID API Key Validation Endpoint
 * 
 * Validates API keys for hardware RFID readers.
 * Returns reader configuration and status.
 * 
 * POST /api/rfid/validate.php
 * Headers: X-API-Key: <api_key>
 * Body: { "reader_id": "READER_01" }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/cors_helper.php';
applyTrustedCors(['POST', 'OPTIONS'], ['Content-Type', 'X-API-Key', 'X-Reader-ID']);
if (handleCorsPreflight()) {
    exit;
}

require_once __DIR__ . '/../../includes/request_method_helper.php';
requireRequestMethod('POST');

require_once __DIR__ . '/../../db.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey)) {
    // Try JSON body
    $body = json_decode(file_get_contents('php://input'), true);
    $apiKey = $body['api_key'] ?? '';
}

if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'API key is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, reader_id, description, is_active, created_at, last_used_at 
        FROM rfid_api_keys 
        WHERE api_key = ?
    ");
    $stmt->execute([$apiKey]);
    $key = $stmt->fetch();

    if (!$key) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid API key']);
        exit;
    }

    if (!$key['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'API key is deactivated']);
        exit;
    }

    // Update last used
    $pdo->prepare("UPDATE rfid_api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$key['id']]);

    // Check if there's an active binding session
    $bindStmt = $pdo->query("
        SELECT id, target_type, target_id, expires_at 
        FROM rfid_binding_sessions 
        WHERE status = 'pending' AND expires_at > NOW() 
        LIMIT 1
    ");
    $activeBinding = $bindStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'API key valid',
        'data' => [
            'reader_id' => $key['reader_id'],
            'description' => $key['description'],
            'active_binding_session' => $activeBinding ? true : false,
            'scan_endpoint' => '/api/rfid/scan.php'
        ]
    ]);
    exit;

} catch (PDOException $e) {
    error_log('[RFID_VALIDATE] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
