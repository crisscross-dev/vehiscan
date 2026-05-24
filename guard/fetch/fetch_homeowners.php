<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

// Guard authorization check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized: Guard role required',
        'error' => 'Unauthorized: Guard role required'
    ]));
}

try {
    // Auth
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired or invalid',
            'error' => 'Session expired or invalid'
        ]);
        exit;
    }

    $plateFilter = isset($_GET['plate']) ? trim($_GET['plate']) : null;

    // Fetch raw DB fields using prepared statement
    $sql = "SELECT h.id, h.name, h.address, h.contact_number, h.vehicle_type, h.color, h.plate_number, h.created_at, h.owner_img AS owner_img_raw, h.car_img AS car_img_raw FROM homeowners h WHERE h.name IS NOT NULL AND h.account_status = 'approved' ORDER BY h.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helper to normalize plate
    $normalize = function($s) {
        if (!$s) return '';
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    };

    if ($plateFilter) {
        $needle = $normalize($plateFilter);
        $rows = array_values(array_filter($rows, function($r) use ($needle, $normalize) {
            return $needle !== '' && $normalize($r['plate_number']) === $needle;
        }));
    }

    // Normalize image paths and build output records
    $uploadsDir = realpath(__DIR__ . '/../../uploads');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/');
    $siteBase = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);

    $homeowners = [];
    foreach ($rows as $r) {
        $ownerRaw = $r['owner_img_raw'] ?? null;
        $carRaw = $r['car_img_raw'] ?? null;

        // Normalize owner path
        $normOwner = null;
        if (!empty($ownerRaw)) {
            $p = ltrim($ownerRaw, '/');
            if (preg_match('#^uploads/#i', $p)) {
                $normOwner = $p;
            } elseif (preg_match('#^homeowners/#i', $p)) {
                $normOwner = 'uploads/' . preg_replace('#^homeowners/#i', 'homeowners/', $p);
            } elseif (preg_match('#^vehicles/#i', $p)) {
                $normOwner = 'uploads/' . preg_replace('#^vehicles/#i', 'vehicles/', $p);
            } else {
                $normOwner = 'uploads/homeowners/' . basename($p);
            }
        }

        // Normalize car path
        $normCar = null;
        if (!empty($carRaw)) {
            $p2 = ltrim($carRaw, '/');
            if (preg_match('#^uploads/#i', $p2)) {
                $normCar = $p2;
            } elseif (preg_match('#^vehicles/#i', $p2)) {
                $normCar = 'uploads/' . preg_replace('#^vehicles/#i', 'vehicles/', $p2);
            } elseif (preg_match('#^homeowners/#i', $p2)) {
                $normCar = 'uploads/' . preg_replace('#^homeowners/#i', 'homeowners/', $p2);
            } else {
                $normCar = 'uploads/vehicles/' . basename($p2);
            }
        }

        $out = [
            'id' => $r['id'],
            'name' => $r['name'],
            'address' => $r['address'],
            'contact' => $r['contact_number'],  // Fixed: was 'contact', should be 'contact_number'
            'vehicle_type' => $r['vehicle_type'],
            'color' => $r['color'],
            'plate_number' => $r['plate_number'],
            'created_at' => $r['created_at'],
            'owner_img' => $normOwner,
            'car_img' => $normCar,
            'owner_img_url' => $normOwner ? $siteBase . '/' . ltrim($normOwner, '/') : null,
            'car_img_url' => $normCar ? $siteBase . '/' . ltrim($normCar, '/') : null,
            'owner_img_exists' => false,
            'car_img_exists' => false,
        ];

        // server-side file checks
        if ($normOwner) {
            $rel = preg_replace('#^uploads/#i', '', ltrim($normOwner, '/'));
            $serverPath = $uploadsDir . '/' . $rel;
            $out['owner_img_exists'] = is_readable($serverPath) && is_file($serverPath);
        }
        if ($normCar) {
            $rel2 = preg_replace('#^uploads/#i', '', ltrim($normCar, '/'));
            $serverPath2 = $uploadsDir . '/' . $rel2;
            $out['car_img_exists'] = is_readable($serverPath2) && is_file($serverPath2);
        }

        $homeowners[] = $out;
    }

    echo json_encode($homeowners);

} catch (PDOException $e) {
    error_log('[FETCH_HOMEOWNERS ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch homeowners',
        'error' => 'Failed to fetch homeowners'
    ]);
}
?>