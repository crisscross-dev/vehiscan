<?php
// guard/pages/fetch_rfid_scan.php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

// Security: Only guards can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

const SCAN_FRESH_WINDOW_SECONDS = 300;

function isRecentScanTimestamp(?string $value): bool
{
    if (!$value) {
        return false;
    }

    try {
        $scanTime = new DateTimeImmutable($value);
        $now = new DateTimeImmutable('now', $scanTime->getTimezone());
        $age = $now->getTimestamp() - $scanTime->getTimestamp();

        return $age >= 0 && $age <= SCAN_FRESH_WINDOW_SECONDS;
    } catch (Throwable $e) {
        return false;
    }
}

function hasMatchingActiveBinding(array $scan): bool
{
    $boundRfid = trim((string) ($scan['bound_rfid'] ?? ''));
    if ($boundRfid === '') {
        // Legacy or unbound records are allowed to pass through.
        return true;
    }

    $scannedRfid = trim((string) ($scan['rfid_uid'] ?? ''));
    if ($scannedRfid === '') {
        return false;
    }

    return strcasecmp($boundRfid, $scannedRfid) === 0;
}

try {
    // Get mode (single or multiple)
    $mode = $_GET['mode'] ?? 'single';
    if (!in_array($mode, ['single', 'multiple'], true)) {
        $mode = 'single';
    }
    
    if ($mode === 'multiple') {
        // Return last 5 scans for carousel
        // Join with both homeowners (legacy) and vehicles (RFID-aware)
        $stmt = $pdo->query("
                 SELECT DISTINCT r.plate_number, r.rfid_uid, r.status, r.log_time, r.created_at,
                                     COALESCE(h.id, h2.id) AS homeowner_id,
                   COALESCE(h.name, h2.name) AS name,
                   COALESCE(h.address, h2.address) AS address,
                   COALESCE(h.contact_number, h2.contact_number) AS contact,
                   COALESCE(h.owner_img, h2.owner_img) AS owner_img,
                   COALESCE(v.vehicle_type, h.vehicle_type) AS vehicle_type,
                   COALESCE(v.color, h.color) AS color,
                   COALESCE(h.car_img, h2.car_img) AS car_img,
                   v.rfid_uid AS bound_rfid
            FROM recent_logs r
            LEFT JOIN homeowners h ON r.plate_number = h.plate_number
            LEFT JOIN vehicles v ON r.plate_number = v.plate_number AND v.is_active = 1
            LEFT JOIN homeowners h2 ON v.homeowner_id = h2.id
            WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $scans = array_values(array_filter($scans, static function (array $row): bool {
            return isRecentScanTimestamp($row['created_at'] ?? null)
                && hasMatchingActiveBinding($row);
        }));
        
        if (empty($scans)) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $scans]);
        exit;
    }
    
    // Default: Single most recent scan
    // Join with both homeowners (legacy) and vehicles (RFID-aware)
    $stmt = $pdo->query("
        SELECT r.log_id, r.plate_number, r.rfid_uid, r.status, r.log_time, r.created_at,
             COALESCE(h.id, h2.id) AS homeowner_id,
               COALESCE(h.name, h2.name) AS name,
               COALESCE(h.address, h2.address) AS address,
               COALESCE(h.contact_number, h2.contact_number) AS contact,
               COALESCE(h.owner_img, h2.owner_img) AS owner_img,
               COALESCE(v.vehicle_type, h.vehicle_type) AS vehicle_type,
               COALESCE(v.color, h.color) AS color,
               COALESCE(h.car_img, h2.car_img) AS car_img,
               v.rfid_uid AS bound_rfid
        FROM recent_logs r
        LEFT JOIN homeowners h ON r.plate_number = h.plate_number
        LEFT JOIN vehicles v ON r.plate_number = v.plate_number AND v.is_active = 1
        LEFT JOIN homeowners h2 ON v.homeowner_id = h2.id
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scan) {
        echo json_encode([
            'success' => false, 
            'message' => 'No recent scan detected',
            'data' => null
        ]);
        exit;
    }

    if (!isRecentScanTimestamp($scan['created_at'] ?? null)) {
        echo json_encode([
            'success' => false,
            'message' => 'No new scan detected',
            'data' => null
        ]);
        exit;
    }

    if (!hasMatchingActiveBinding($scan)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'RFID mismatch for active vehicle binding',
            'data' => null
        ]);
        exit;
    }

    $logTime = $scan['log_time'] ?? null;
    if (!empty($logTime)) {
        try {
            $logTimeObject = new DateTimeImmutable($logTime);
            $logTime = $logTimeObject->format('g:i:s A');
        } catch (Throwable $e) {
            // Leave raw value if parsing fails.
        }
    }

    // Check if homeowner exists
    if (!$scan['name']) {
        echo json_encode([
            'success' => false,
            'message' => 'Unknown plate number: ' . $scan['plate_number'],
            'data' => [
                'plate_number' => $scan['plate_number'],
                'rfid_uid' => $scan['rfid_uid'],
                'status' => $scan['status'],
                'name' => 'UNKNOWN',
                'address' => 'Not registered',
                'contact' => 'N/A',
                'vehicle_type' => 'Unknown',
                'color' => 'Unknown',
                'owner_img' => null,
                'car_img' => null
            ]
        ]);
        exit;
    }

    // Return full homeowner data with RFID info
    echo json_encode([
        'success' => true,
        'message' => 'Scan retrieved',
        'data' => [
            'log_id' => $scan['log_id'],
            'name' => $scan['name'],
            'address' => $scan['address'],
            'contact' => $scan['contact'],
            'vehicle_type' => $scan['vehicle_type'],
            'color' => $scan['color'],
            'plate_number' => $scan['plate_number'],
            'homeowner_id' => isset($scan['homeowner_id']) ? (int)$scan['homeowner_id'] : 0,
            'rfid_uid' => $scan['rfid_uid'],
            'bound_rfid' => $scan['bound_rfid'],
            'owner_img' => $scan['owner_img'],
            'car_img' => $scan['car_img'],
            'status' => $scan['status'],
            'log_time' => $scan['log_time'],
            'created_at' => $scan['created_at']
        ]
    ]);

} catch (PDOException $e) {
    error_log('RFID fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'data' => null
    ]);
} catch (Throwable $e) {
    error_log('RFID fetch unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch scan data',
        'data' => null
    ]);
}