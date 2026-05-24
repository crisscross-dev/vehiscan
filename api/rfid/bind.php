<?php
/**
 * RFID Binding Session Management
 * 
 * Manages RFID binding sessions - initiate, cancel, check status.
 * Used by admin panel to bind RFID tags to vehicles.
 * 
 * POST /api/rfid/bind.php
 *   action=initiate  - Start a new binding session for a vehicle
 *   action=cancel    - Cancel an active binding session
 *   action=unbind    - Remove RFID binding from a vehicle
 *   action=status    - Check current binding session status (also supports GET)
 * 
 * GET /api/rfid/bind.php?action=status&session_id=<id>
 */

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Multi-role session: use guard session ONLY when no admin/superadmin cookie exists.
// When admin cookies are present, always use admin_unified so the CSRF token matches.
$hasAdminCookie = isset($_COOKIE['vehiscan_admin']) || isset($_COOKIE['vehiscan_superadmin']);
if (isset($_COOKIE['vehiscan_guard']) && !$hasAdminCookie) {
    require_once __DIR__ . '/../../includes/session_guard.php';
} else {
    require_once __DIR__ . '/../../includes/session_admin_unified.php';
}

// Auth check - admin, super_admin, or guard can bind RFID
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'guard'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Validate CSRF token with diagnostic logging on failure
 */
function requireCsrf($context) {
    $csrfToken = InputSanitizer::post('csrf_token', 'string');
    if (!InputSanitizer::validateCsrf($csrfToken)) {
        error_log("[RFID_BIND] CSRF mismatch on {$context} — session_name=" . session_name()
            . ', role=' . ($_SESSION['role'] ?? 'none')
            . ', has_session_token=' . (isset($_SESSION['csrf_token']) ? 'yes' : 'no')
            . ', has_posted_token=' . (!empty($csrfToken) ? 'yes' : 'no'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = InputSanitizer::post('action', 'string', '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = InputSanitizer::get('action', 'string', '');
}

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

switch ($action) {
    case 'initiate':
        handleInitiate();
        break;
    case 'cancel':
        handleCancel();
        break;
    case 'unbind':
        handleUnbind();
        break;
    case 'status':
        handleStatus();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

/**
 * Initiate a new RFID binding session
 */
function handleInitiate() {
    global $pdo;

    requireCsrf('initiate');

    $vehicleId = InputSanitizer::post('vehicle_id', 'int');
    if (!$vehicleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verify vehicle exists and lock it
        $stmt = $pdo->prepare("
            SELECT v.id, v.plate_number, v.rfid_uid, v.vehicle_type, h.name 
            FROM vehicles v 
            LEFT JOIN homeowners h ON v.homeowner_id = h.id 
            WHERE v.id = ? FOR UPDATE
        ");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();

        if (!$vehicle) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            exit;
        }

        // Check if vehicle already has an RFID bound
        if (!empty($vehicle['rfid_uid'])) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Vehicle {$vehicle['plate_number']} already has RFID tag bound ({$vehicle['rfid_uid']}). Unbind first."
            ]);
            exit;
        }

        // Cancel any existing pending sessions for this vehicle (locked by default in UPDATE)
        $pdo->prepare("
            UPDATE rfid_binding_sessions 
            SET status = 'cancelled', completed_at = NOW() 
            WHERE target_id = ? AND target_type = 'vehicle' AND status = 'pending'
        ")->execute([$vehicleId]);

        // Also cancel any other pending sessions (only one at a time)
        $pdo->prepare("
            UPDATE rfid_binding_sessions 
            SET status = 'cancelled', completed_at = NOW() 
            WHERE status = 'pending'
        ")->execute();

        // Create binding session
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minute timeout

        $stmt = $pdo->prepare("
            INSERT INTO rfid_binding_sessions 
            (session_token, target_type, target_id, initiated_by, initiated_by_role, status, expires_at, created_at) 
            VALUES (?, 'vehicle', ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $sessionToken,
            $vehicleId,
            $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0,
            $_SESSION['role'],
            $expiresAt
        ]);

        $pdo->commit();

        $sessionId = $pdo->lastInsertId();

        logAudit('RFID binding initiated', 'vehicles', $vehicleId, 
            "Binding session #{$sessionId} for {$vehicle['plate_number']}");

        echo json_encode([
            'success' => true,
            'message' => "Binding session started for {$vehicle['plate_number']}. Scan an RFID tag within 5 minutes.",
            'data' => [
                'session_id' => (int)$sessionId,
                'session_token' => $sessionToken,
                'vehicle_id' => (int)$vehicleId,
                'plate_number' => $vehicle['plate_number'],
                'vehicle_type' => $vehicle['vehicle_type'],
                'owner_name' => $vehicle['name'],
                'expires_at' => $expiresAt,
                'timeout_seconds' => 300
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('[RFID_BIND] Initiate error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

/**
 * Cancel an active binding session
 */
function handleCancel() {
    global $pdo;

    requireCsrf('cancel');

    $sessionId = InputSanitizer::post('session_id', 'int');
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE rfid_binding_sessions 
            SET status = 'cancelled', completed_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$sessionId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No active session found to cancel']);
            exit;
        }

        logAudit('RFID binding cancelled', 'rfid_binding_sessions', $sessionId, 'Binding session cancelled by user');

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Binding session cancelled']);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[RFID_BIND] Cancel error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

/**
 * Unbind RFID from a vehicle
 */
function handleUnbind() {
    global $pdo;

    requireCsrf('unbind');

    $vehicleId = InputSanitizer::post('vehicle_id', 'int');
    if (!$vehicleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get current binding info and lock it
        $stmt = $pdo->prepare("SELECT id, plate_number, rfid_uid FROM vehicles WHERE id = ? FOR UPDATE");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();

        if (!$vehicle) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            exit;
        }

        if (empty($vehicle['rfid_uid'])) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Vehicle has no RFID tag bound']);
            exit;
        }

        $oldUid = $vehicle['rfid_uid'];

        // Remove binding
        $pdo->prepare("UPDATE vehicles SET rfid_uid = NULL, rfid_bound_at = NULL, rfid_bound_by = NULL WHERE id = ?")
            ->execute([$vehicleId]);

        logAudit('RFID unbound', 'vehicles', $vehicleId, 
            "Removed RFID {$oldUid} from {$vehicle['plate_number']}");

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "RFID tag removed from {$vehicle['plate_number']}",
            'data' => [
                'vehicle_id' => (int)$vehicleId,
                'plate_number' => $vehicle['plate_number'],
                'old_rfid_uid' => $oldUid
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[RFID_BIND] Unbind error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

/**
 * Check binding session status (supports GET and POST)
 */
function handleStatus() {
    global $pdo;

    // Release the session file lock early — status checks are read-only and
    // don't modify session data.  Without this, the 2-second polling interval
    // holds the lock during DB queries, blocking concurrent POST requests
    // (initiate/cancel/unbind) that need CSRF validation from the same session.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $sessionId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sessionId = InputSanitizer::get('session_id', 'int');
    } else {
        $sessionId = InputSanitizer::post('session_id', 'int');
    }

    if (!$sessionId) {
        // Return latest active session if no ID specified
        try {
            $stmt = $pdo->query("
                SELECT bs.*, v.plate_number, v.vehicle_type, h.name as owner_name
                FROM rfid_binding_sessions bs
                LEFT JOIN vehicles v ON bs.target_id = v.id AND bs.target_type = 'vehicle'
                LEFT JOIN homeowners h ON v.homeowner_id = h.id
                WHERE bs.status = 'pending' AND bs.expires_at > NOW()
                ORDER BY bs.created_at DESC 
                LIMIT 1
            ");
            $session = $stmt->fetch();

            if (!$session) {
                echo json_encode(['success' => true, 'active' => false, 'message' => 'No active binding session']);
                exit;
            }

            $remaining = max(0, strtotime($session['expires_at']) - time());

            echo json_encode([
                'success' => true,
                'active' => true,
                'data' => [
                    'session_id' => (int)$session['id'],
                    'status' => $session['status'],
                    'plate_number' => $session['plate_number'],
                    'vehicle_type' => $session['vehicle_type'],
                    'owner_name' => $session['owner_name'],
                    'expires_at' => $session['expires_at'],
                    'remaining_seconds' => $remaining
                ]
            ]);
            exit;

        } catch (PDOException $e) {
            error_log('[RFID_BIND] Status check error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("
            SELECT bs.*, v.plate_number, v.vehicle_type, h.name as owner_name
            FROM rfid_binding_sessions bs
            LEFT JOIN vehicles v ON bs.target_id = v.id AND bs.target_type = 'vehicle'
            LEFT JOIN homeowners h ON v.homeowner_id = h.id
            WHERE bs.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit;
        }

        // Check if session has expired
        if ($session['status'] === 'pending' && strtotime($session['expires_at']) < time()) {
            $pdo->prepare("UPDATE rfid_binding_sessions SET status = 'timeout', completed_at = NOW() WHERE id = ?")
                ->execute([$sessionId]);
            $session['status'] = 'timeout';
        }

        $remaining = $session['status'] === 'pending' ? max(0, strtotime($session['expires_at']) - time()) : 0;

        echo json_encode([
            'success' => true,
            'active' => $session['status'] === 'pending',
            'data' => [
                'session_id' => (int)$session['id'],
                'status' => $session['status'],
                'scanned_uid' => $session['scanned_uid'],
                'plate_number' => $session['plate_number'],
                'vehicle_type' => $session['vehicle_type'],
                'owner_name' => $session['owner_name'],
                'expires_at' => $session['expires_at'],
                'completed_at' => $session['completed_at'],
                'remaining_seconds' => $remaining
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('[RFID_BIND] Status error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}
