<?php
date_default_timezone_set('Asia/Manila');
/**
 * RFID Scan Endpoint
 * 
 * Receives RFID UID scans from hardware readers (ESP32/Arduino) or the simulator.
 * Authenticates via API key, logs scans, and handles access control.
 * 
 * POST /api/rfid/scan.php
 * Headers: X-API-Key: <api_key>, X-Reader-ID: <reader_id>
 * Body: { "rfid_uid": "ABC123DEF456" }
 * 
 * OR for simulator/internal: POST with session auth
 * Body: rfid_uid=<uid>&csrf_token=<token>
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
require_once __DIR__ . '/../../includes/input_sanitizer.php';

// Determine authentication method
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$readerId = $_SERVER['HTTP_X_READER_ID'] ?? '';
$inputSource = 'unknown';
$apiKeyId = null;

if (!empty($apiKey)) {
    // Hardware reader authentication via API key
    try {
        $stmt = $pdo->prepare("SELECT id, reader_id, is_active FROM rfid_api_keys WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $key = $stmt->fetch();

        if (!$key || !$key['is_active']) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or inactive API key']);
            exit;
        }

        // Verify reader ID matches if provided
        if (!empty($readerId) && $key['reader_id'] !== $readerId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Reader ID mismatch']);
            exit;
        }

        $apiKeyId = $key['id'];
        $inputSource = 'api_key';
        $readerId = $key['reader_id'];

        // Update last used timestamp
        $pdo->prepare("UPDATE rfid_api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$apiKeyId]);
    } catch (PDOException $e) {
        error_log('[RFID_SCAN] API key validation error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Authentication error']);
        exit;
    }
} else {
    // Session-based authentication (simulator or admin)
    // Priority: 1. Explicit session_type request, 2. Guard cookie (no admin), 3. Admin Unified
    $requestedType = InputSanitizer::post('session_type', 'string') ?: InputSanitizer::get('session_type', 'string');
    $hasAdminCookie = isset($_COOKIE['vehiscan_admin']) || isset($_COOKIE['vehiscan_superadmin']);
    
    if ($requestedType === 'guard' || (isset($_COOKIE['vehiscan_guard']) && !$hasAdminCookie)) {
        require_once __DIR__ . '/../../includes/session_guard.php';
    } else {
        require_once __DIR__ . '/../../includes/session_admin_unified.php';
    }
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'guard'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // CSRF validation for session-based requests (never accept token from query string)
    $postedToken = InputSanitizer::post('csrf_token', 'string');
    
    // Fallback for JSON body if shared via AJAX with different content type
    if (empty($postedToken)) {
        $jsonBody = json_decode(file_get_contents('php://input'), true);
        $postedToken = $jsonBody['csrf_token'] ?? '';
    }

    if (!InputSanitizer::validateCsrf($postedToken)) {
        error_log('[RFID_SCAN] CSRF failure — session_name=' . session_name()
            . ', role=' . ($_SESSION['role'] ?? 'none')
            . ', has_session_token=' . (isset($_SESSION['csrf_token']) ? 'yes' : 'no')
            . ', has_provided_token=' . (!empty($postedToken) ? 'yes' : 'no'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $inputSource = 'session';
}

// Get RFID UID from request body
$rfidUid = '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $body = json_decode(file_get_contents('php://input'), true);
    $rfidUid = trim($body['rfid_uid'] ?? '');
} else {
    $rfidUid = trim($_POST['rfid_uid'] ?? '');
}

if (empty($rfidUid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'RFID UID is required']);
    exit;
}

// Sanitize RFID UID (alphanumeric only, max 32 chars)
$rfidUid = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rfidUid));
if (strlen($rfidUid) > 32 || strlen($rfidUid) < 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid RFID UID format']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check 1: Is there an active binding session waiting for this scan?
    $currentUserId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $bindingSession = null;

    // Prioritize session initiated by THIS user if we are in an admin session
    if ($inputSource === 'session' && $currentUserId > 0) {
        $bindStmt = $pdo->prepare("
            SELECT id, target_type, target_id, initiated_by 
            FROM rfid_binding_sessions 
            WHERE status = 'pending' AND expires_at > NOW() AND initiated_by = ?
            ORDER BY created_at DESC 
            LIMIT 1 FOR UPDATE
        ");
        $bindStmt->execute([$currentUserId]);
        $bindingSession = $bindStmt->fetch();
    }

    // Fallback: Check for ANY active pending session
    if (!$bindingSession) {
        $bindStmt = $pdo->prepare("\n            SELECT id, target_type, target_id, initiated_by \n            FROM rfid_binding_sessions \n            WHERE status = 'pending' AND expires_at > NOW()\n            ORDER BY created_at DESC \n            LIMIT 1 FOR UPDATE\n        ");
        $bindStmt->execute();
        $bindingSession = $bindStmt->fetch();
    }

    if ($bindingSession) {
        // Complete the binding session
        $targetId = $bindingSession['target_id'];

        // Check if this UID is already bound to another ACTIVE vehicle
        // Lock this check as well to prevent concurrent identical binds
        $dupCheck = $pdo->prepare("SELECT id, plate_number FROM vehicles WHERE rfid_uid = ? AND id != ? AND is_active = 1 FOR UPDATE");
        $dupCheck->execute([$rfidUid, $targetId]);
        $duplicate = $dupCheck->fetch();

        if ($duplicate) {
            // UID already in use
            $pdo->prepare("UPDATE rfid_binding_sessions SET status = 'cancelled', completed_at = NOW() WHERE id = ?")
                ->execute([$bindingSession['id']]);

            // Log the failed binding
            logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'binding_failed', $inputSource, null, $bindingSession['id'],
                "UID already bound to vehicle {$duplicate['plate_number']}");

            $pdo->commit();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'scan_result' => 'binding_failed',
                'message' => "This RFID tag is already bound to vehicle {$duplicate['plate_number']}"
            ]);
            exit;
        }

        // Bind the RFID UID to the vehicle
        $pdo->prepare("UPDATE vehicles SET rfid_uid = ?, rfid_bound_at = NOW(), rfid_bound_by = ? WHERE id = ?")
            ->execute([$rfidUid, $bindingSession['initiated_by'], $targetId]);

        // Mark session as completed
        $pdo->prepare("UPDATE rfid_binding_sessions SET status = 'completed', scanned_uid = ?, completed_at = NOW() WHERE id = ?")
            ->execute([$rfidUid, $bindingSession['id']]);

        // Get vehicle info for response
        $vInfo = $pdo->prepare("SELECT v.plate_number, v.vehicle_type, h.name FROM vehicles v LEFT JOIN homeowners h ON v.homeowner_id = h.id WHERE v.id = ?");
        $vInfo->execute([$targetId]);
        $vehicleInfo = $vInfo->fetch();

        // Log the successful binding
        logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'uid_bound', $inputSource, $targetId, $bindingSession['id'], null);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'scan_result' => 'uid_bound',
            'message' => "RFID tag bound to {$vehicleInfo['plate_number']} ({$vehicleInfo['name']})",
            'data' => [
                'rfid_uid' => $rfidUid,
                'plate_number' => $vehicleInfo['plate_number'],
                'vehicle_type' => $vehicleInfo['vehicle_type'],
                'owner_name' => $vehicleInfo['name']
            ]
        ]);
        exit;
    }

    // Check 2: Is this UID bound to a vehicle? (Normal access scan)
    // Use FOR UPDATE to prevent rapid double scans toggling IN/OUT concurrently
    $vehicleStmt = $pdo->prepare("
        SELECT v.id, v.plate_number, v.vehicle_type, v.color, v.homeowner_id, v.rfid_bound_at,
               h.name, h.address, h.contact_number, h.owner_img, h.car_img, h.account_status
        FROM vehicles v
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        WHERE v.rfid_uid = ? AND v.is_active = 1 FOR UPDATE
    ");
    $vehicleStmt->execute([$rfidUid]);
    $vehicle = $vehicleStmt->fetch();

    if ($vehicle) {
        $authorizedOwnerName = $vehicle['name'];
        $hasAuthorizedAccess = (($vehicle['account_status'] ?? '') === 'approved');

        // Fallback path is opt-in to avoid accidental approval bypass.
        $allowSharedFallback = (getenv('ALLOW_SHARED_RFID_FALLBACK') === '1');
        if (!$hasAuthorizedAccess && $allowSharedFallback && tableExists($pdo, 'vehicle_shared_access')) {
            $sharedStmt = $pdo->prepare("
                SELECT h.id, h.name
                FROM vehicle_shared_access vsa
                INNER JOIN homeowners h ON h.id = vsa.homeowner_id
                WHERE vsa.vehicle_id = ? AND vsa.is_active = 1 AND h.account_status = 'approved'
                ORDER BY vsa.id ASC
                LIMIT 1
            ");
            $sharedStmt->execute([$vehicle['id']]);
            $sharedAccess = $sharedStmt->fetch();
            if ($sharedAccess) {
                $hasAuthorizedAccess = true;
                $authorizedOwnerName = $sharedAccess['name'] ?? $authorizedOwnerName;
            }
        }

        // Check if homeowner account is active
        if (!$hasAuthorizedAccess) {
            logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'access_denied', $inputSource, $vehicle['id'], null,
                'Homeowner account not approved');

            $pdo->commit();
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'scan_result' => 'access_denied',
                'message' => 'Access denied - account not approved',
                'data' => [
                    'plate_number' => $vehicle['plate_number'],
                    'name' => $vehicle['name']
                ]
            ]);
            exit;
        }


        // Anti-passback / Debounce check
        $recentScan = $pdo->prepare("SELECT scanned_at FROM rfid_scan_log WHERE rfid_uid = ? AND scan_result = 'access_granted' ORDER BY id DESC LIMIT 1");
        $recentScan->execute([$rfidUid]);
        $lastScan = $recentScan->fetch();
        
        if ($lastScan) {
            $secondsSinceLastScan = time() - strtotime($lastScan['scanned_at']);
            if ($secondsSinceLastScan < 60) { // 60 seconds cooldown
                logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'access_denied', $inputSource, $vehicle['id'], null, 'Anti-passback cooldown active');
                $pdo->commit();
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'scan_result' => 'cooldown',
                    'message' => 'Anti-passback: Please wait a moment before scanning again.',
                    'data' => [
                        'plate_number' => $vehicle['plate_number'],
                        'name' => $authorizedOwnerName
                    ]
                ]);
                exit;
            }
        }

        // Determine IN/OUT status by checking last log entry (also lock it)
        $lastLog = $pdo->prepare("SELECT status, log_time FROM recent_logs WHERE plate_number = ? ORDER BY log_id DESC LIMIT 1 FOR UPDATE");
        $lastLog->execute([$vehicle['plate_number']]);
        $lastEntry = $lastLog->fetch();

        $newStatus = (!$lastEntry || $lastEntry['status'] === 'OUT') ? 'IN' : 'OUT';

        // Insert into recent_logs (this is what the guard panel reads)
        $pdo->prepare("INSERT INTO recent_logs (plate_number, rfid_uid, status, log_time) VALUES (?, ?, ?, CURTIME())")
            ->execute([$vehicle['plate_number'], $rfidUid, $newStatus]);

        // Log the access scan
        logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'access_granted', $inputSource, $vehicle['id'], null, null);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'scan_result' => 'access_granted',
            'message' => "Vehicle {$vehicle['plate_number']} - $newStatus",
            'data' => [
                'rfid_uid' => $rfidUid,
                'plate_number' => $vehicle['plate_number'],
                'vehicle_type' => $vehicle['vehicle_type'],
                'color' => $vehicle['color'],
                'name' => $authorizedOwnerName,
                'address' => $vehicle['address'],
                'contact' => $vehicle['contact_number'],
                'owner_img' => $vehicle['owner_img'],
                'car_img' => $vehicle['car_img'],
                'status' => $newStatus,
                'direction' => $newStatus === 'IN' ? 'Entering' : 'Exiting'
            ]
        ]);
        exit;
    }

    // Check 3: Is this UID already in the system but INACTIVE?
    $inactiveStmt = $pdo->prepare("SELECT v.*, h.name FROM vehicles v LEFT JOIN homeowners h ON v.homeowner_id = h.id WHERE v.rfid_uid = ? AND v.is_active = 0 LIMIT 1");
    $inactiveStmt->execute([$rfidUid]);
    $inactiveVehicle = $inactiveStmt->fetch();

    if ($inactiveVehicle) {
        logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'unknown_uid', $inputSource, null, null, "UID bound to inactive record ({$inactiveVehicle['plate_number']})");
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'scan_result' => 'unknown_uid',
            'inactive_uid' => true,
            'message' => "This RFID tag belongs to an inactive account ({$inactiveVehicle['plate_number']}). Please re-bind it."
        ]);
        exit;
    }

    logScan($pdo, $rfidUid, $readerId, $apiKeyId, 'unknown_uid', $inputSource, null, null, 'No vehicle bound to this UID');

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'scan_result' => 'unknown_uid',
        'unknown_uid' => true,
        'message' => 'New RFID tag - not bound to any vehicle.'
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[RFID_SCAN] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

/**
 * Log an RFID scan to the rfid_scan_log table
 */
function logScan($pdo, $rfidUid, $readerId, $apiKeyId, $scanResult, $inputSource, $vehicleId, $bindingSessionId, $errorMessage) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rfid_scan_log 
            (rfid_uid, reader_id, api_key_id, scan_result, input_source, vehicle_id, binding_session_id, error_message, ip_address, scanned_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $rfidUid,
            $readerId ?: null,
            $apiKeyId,
            $scanResult,
            $inputSource,
            $vehicleId,
            $bindingSessionId,
            $errorMessage,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log('[RFID_SCAN] Failed to log scan: ' . $e->getMessage());
    }
}

function tableExists($pdo, $tableName) {
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $cache[$tableName] = (bool)$stmt->fetchColumn();
        return $cache[$tableName];
    } catch (Throwable $e) {
        return false;
    }
}
