<?php
/**
 * RFID Scan History Endpoint
 * 
 * Returns RFID scan history from rfid_scan_log.
 * Used by admin panel to view scan logs and binding history.
 * 
 * GET /api/rfid/history.php?limit=50&result=access_granted&vehicle_id=123
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

// Multi-role session: use guard session ONLY when no admin/superadmin cookie exists
$hasAdminCookie = isset($_COOKIE['vehiscan_admin']) || isset($_COOKIE['vehiscan_superadmin']);
if (isset($_COOKIE['vehiscan_guard']) && !$hasAdminCookie) {
    require_once __DIR__ . '/../../includes/session_guard.php';
} else {
    require_once __DIR__ . '/../../includes/session_admin_unified.php';
}

// Auth check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'guard'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$resultFilter = $_GET['result'] ?? '';
$vehicleId = (int)($_GET['vehicle_id'] ?? 0);

try {
    $where = [];
    $params = [];

    if (!empty($resultFilter)) {
        $allowed = ['access_granted', 'access_denied', 'uid_bound', 'unknown_uid', 'binding_failed', 'duplicate_scan', 'error'];
        if (in_array($resultFilter, $allowed)) {
            $where[] = 'sl.scan_result = ?';
            $params[] = $resultFilter;
        }
    }

    if ($vehicleId > 0) {
        $where[] = 'sl.vehicle_id = ?';
        $params[] = $vehicleId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT sl.id, sl.rfid_uid, sl.reader_id, sl.scan_result, sl.input_source,
               sl.error_message, sl.ip_address, sl.scanned_at,
               v.plate_number, v.vehicle_type,
               h.name as owner_name,
               bs.status as binding_status
        FROM rfid_scan_log sl
        LEFT JOIN vehicles v ON sl.vehicle_id = v.id
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        LEFT JOIN rfid_binding_sessions bs ON sl.binding_session_id = bs.id
        $whereClause
        ORDER BY sl.scanned_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get summary stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN scan_result = 'access_granted' THEN 1 ELSE 0 END) as granted,
            SUM(CASE WHEN scan_result = 'access_denied' THEN 1 ELSE 0 END) as denied,
            SUM(CASE WHEN scan_result = 'uid_bound' THEN 1 ELSE 0 END) as bound,
            SUM(CASE WHEN scan_result = 'unknown_uid' THEN 1 ELSE 0 END) as unknown
        FROM rfid_scan_log
        WHERE scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetch();

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'stats' => $stats,
        'count' => count($logs)
    ]);
    exit;

} catch (PDOException $e) {
    error_log('[RFID_HISTORY] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
