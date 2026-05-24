header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

// Security: Only admins and super_admins can simulate scans
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    error_log('[RFID_SIM] Unauthorized access attempt');
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once __DIR__ . '/../../db.php';

requireRequestMethod('POST');

// Validate CSRF token
$csrf = $_SESSION['csrf_token'] ?? '';
$posted = $_POST['csrf_token'] ?? '';
if (!InputSanitizer::validateCsrf((string)$posted)) {
    error_log('[RFID_SIM] Invalid CSRF token');
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid security token']));
}

$plate = $_POST['plate_number'] ?? '';
$rfidUid = trim($_POST['rfid_uid'] ?? '');
$scanMode = $_POST['scan_mode'] ?? 'plate'; // 'plate' or 'rfid'

// RFID UID Scan Mode - route through the RFID scan API
if ($scanMode === 'rfid' && !empty($rfidUid)) {
    // Sanitize UID
    $rfidUid = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $rfidUid));
    if (strlen($rfidUid) < 4 || strlen($rfidUid) > 32) {
        exit(json_encode(['success' => false, 'message' => 'Invalid RFID UID format (4-32 hex characters)']));
    }

    // Check if there's an active binding session
    $bindStmt = $pdo->prepare("
        SELECT bs.id, bs.target_id, v.plate_number, h.name
        FROM rfid_binding_sessions bs
        LEFT JOIN vehicles v ON bs.target_id = v.id
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        WHERE bs.status = 'pending' AND bs.expires_at > NOW()
        LIMIT 1
    ");
    $bindStmt->execute();
    $bindingSession = $bindStmt->fetch(PDO::FETCH_ASSOC);

    if ($bindingSession) {
        // Complete the binding
        $dupCheck = $pdo->prepare("SELECT id, plate_number FROM vehicles WHERE rfid_uid = ? AND id != ?");
        $dupCheck->execute([$rfidUid, $bindingSession['target_id']]);
        $dup = $dupCheck->fetch();

        if ($dup) {
            $pdo->prepare("UPDATE rfid_binding_sessions SET status = 'cancelled', completed_at = NOW() WHERE id = ?")
                ->execute([$bindingSession['id']]);
            exit(json_encode([
                'success' => false,
                'message' => "UID already bound to {$dup['plate_number']}",
                'scan_result' => 'binding_failed'
            ]));
        }

        $pdo->prepare("UPDATE vehicles SET rfid_uid = ?, rfid_bound_at = NOW(), rfid_bound_by = ? WHERE id = ?")
            ->execute([$rfidUid, $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0, $bindingSession['target_id']]);
        $pdo->prepare("UPDATE rfid_binding_sessions SET status = 'completed', scanned_uid = ?, completed_at = NOW() WHERE id = ?")
            ->execute([$rfidUid, $bindingSession['id']]);

        // Log to rfid_scan_log
        try {
            $pdo->prepare("INSERT INTO rfid_scan_log (rfid_uid, scan_result, input_source, vehicle_id, binding_session_id, ip_address, scanned_at) VALUES (?, 'uid_bound', 'simulator', ?, ?, ?, NOW())")
                ->execute([$rfidUid, $bindingSession['target_id'], $bindingSession['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}

        exit(json_encode([
            'success' => true,
            'message' => "RFID bound to {$bindingSession['plate_number']} ({$bindingSession['name']})",
            'scan_result' => 'uid_bound',
            'plate' => $bindingSession['plate_number'],
            'name' => $bindingSession['name'],
            'rfid_uid' => $rfidUid
        ]));
    }

    // Normal scan - look up vehicle by RFID UID
    $vStmt = $pdo->prepare("
        SELECT v.id, v.plate_number, v.vehicle_type, h.name
        FROM vehicles v
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        WHERE v.rfid_uid = ? AND v.is_active = 1
    ");
    $vStmt->execute([$rfidUid]);
    $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        // Log unknown UID scan
        try {
            $pdo->prepare("INSERT INTO rfid_scan_log (rfid_uid, scan_result, input_source, ip_address, scanned_at) VALUES (?, 'unknown_uid', 'simulator', ?, NOW())")
                ->execute([$rfidUid, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}

        exit(json_encode([
            'success' => false,
            'message' => "Unknown RFID tag ($rfidUid) - not bound to any vehicle",
            'scan_result' => 'unknown_uid'
        ]));
    }

    // Toggle IN/OUT
    $lastLog = $pdo->prepare("SELECT status FROM recent_logs WHERE plate_number = ? ORDER BY log_id DESC LIMIT 1");
    $lastLog->execute([$vehicle['plate_number']]);
    $last = $lastLog->fetch();
    $newStatus = (!$last || $last['status'] === 'OUT') ? 'IN' : 'OUT';

    $pdo->prepare("INSERT INTO recent_logs (plate_number, rfid_uid, status, log_time) VALUES (?, ?, ?, CURTIME())")
        ->execute([$vehicle['plate_number'], $rfidUid, $newStatus]);

    // Log to rfid_scan_log
    try {
        $pdo->prepare("INSERT INTO rfid_scan_log (rfid_uid, scan_result, input_source, vehicle_id, ip_address, scanned_at) VALUES (?, 'access_granted', 'simulator', ?, ?, NOW())")
            ->execute([$rfidUid, $vehicle['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {}

    // Log to rfid_simulator
    try {
        $pdo->prepare("INSERT INTO rfid_simulator (plate_number, rfid_uid, simulated_at) VALUES (?, ?, NOW())")
            ->execute([$vehicle['plate_number'], $rfidUid]);
    } catch (Exception $e) {}

    $statusMessage = $newStatus === 'IN' ? 'Entry Logged' : 'Exit Logged';
    exit(json_encode([
        'success' => true,
        'message' => "RFID scan: {$vehicle['plate_number']} - $statusMessage",
        'scan_result' => 'access_granted',
        'plate' => $vehicle['plate_number'],
        'name' => $vehicle['name'],
        'status' => $statusMessage,
        'direction' => $newStatus,
        'rfid_uid' => $rfidUid
    ]));
}

// Legacy Plate-based scan mode
if (empty($plate)) {
    exit(json_encode(['success' => false, 'message' => 'Plate number required']));
}

try {
    // Check if homeowner exists
    $stmt = $pdo->prepare("SELECT name, vehicle_type, plate_number FROM homeowners WHERE plate_number = ?");
    $stmt->execute([$plate]);
    $homeowner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$homeowner) {
        exit(json_encode([
            'success' => false, 
            'message' => 'Vehicle not registered in system'
        ]));
    }
    
    // Check the last scan status for this plate to toggle IN/OUT
    $stmt = $pdo->prepare(
        "SELECT status FROM recent_logs WHERE plate_number = ? ORDER BY log_id DESC LIMIT 1"
    );
    $stmt->execute([$plate]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Toggle status: if last was IN, make it OUT; if OUT or no record, make it IN
    $newStatus = 'IN'; // Default for first scan
    if ($lastLog) {
        $newStatus = ($lastLog['status'] === 'IN') ? 'OUT' : 'IN';
    }
    
    // Insert into recent_logs table (this is what the guard panel reads)
    // log_time is TIME field (use CURTIME()), created_at auto-populates
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO recent_logs (plate_number, status, log_time) VALUES (?, ?, CURTIME())"
        );
        $result = $stmt->execute([$plate, $newStatus]);

        if (!$result) {
            error_log('[RFID_SIM] Failed to insert into recent_logs');
            exit(json_encode([
                'success' => false,
                'message' => 'Failed to create log entry'
            ]));
        }

    } catch (PDOException $e) {
        // If an unexpected schema or DB error occurs, log it securely
        error_log('[RFID_SIM] Database insert error: ' . $e->getMessage());
        exit(json_encode([
            'success' => false,
            'message' => 'Failed to create log entry. Please check server logs.'
        ]));
    }
    
    // Also log to rfid_simulator table for admin tracking
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rfid_simulator (plate_number, simulated_at) 
            VALUES (?, NOW())
        ");
        $stmt->execute([$plate]);
    } catch (PDOException $e) {
        // Non-critical error - simulator table might not exist
        error_log('[RFID_SIM] Warning: Could not insert into rfid_simulator: ' . $e->getMessage());
    }
    
    $statusMessage = $newStatus === 'IN' ? 'Entry Logged' : 'Exit Logged';
    
    exit(json_encode([
        'success' => true,
        'message' => 'RFID scan simulated successfully',
        'plate' => $plate,
        'name' => $homeowner['name'],
        'status' => $statusMessage,
        'direction' => $newStatus
    ]));
    
} catch (PDOException $e) {
    error_log('[RFID_SIM] Database error: ' . $e->getMessage());
    exit(json_encode([
        'success' => false, 
        'message' => 'A database error occurred. Please check server logs.'
    ]));
}
