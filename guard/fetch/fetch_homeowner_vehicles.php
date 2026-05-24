<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$homeownerId = isset($_GET['homeowner_id']) ? (int)$_GET['homeowner_id'] : 0;
$plateInput = trim((string)($_GET['plate'] ?? ''));

if ($homeownerId <= 0 && $plateInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'homeowner_id or plate is required']);
    exit;
}

try {
    $vehicleImgAvailable = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_img'");
        $vehicleImgAvailable = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $schemaErr) {
        $vehicleImgAvailable = false;
    }

    if ($homeownerId <= 0 && $plateInput !== '') {
        $normalizedPlate = strtoupper(preg_replace('/[^A-Z0-9]/', '', $plateInput));

        if ($normalizedPlate !== '') {
            $byVehicle = $pdo->prepare("\n                SELECT homeowner_id\n                FROM vehicles\n                WHERE REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') = ?\n                ORDER BY is_active DESC, id DESC\n                LIMIT 1\n            ");
            $byVehicle->execute([$normalizedPlate]);
            $homeownerId = (int)($byVehicle->fetchColumn() ?: 0);

            if ($homeownerId <= 0) {
                $byHomeownerPlate = $pdo->prepare("\n                    SELECT id\n                    FROM homeowners\n                    WHERE REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') = ?\n                    LIMIT 1\n                ");
                $byHomeownerPlate->execute([$normalizedPlate]);
                $homeownerId = (int)($byHomeownerPlate->fetchColumn() ?: 0);
            }
        }
    }

    if ($homeownerId <= 0) {
        echo json_encode(['success' => true, 'vehicles' => [], 'homeowner_id' => 0]);
        exit;
    }

    $vehicleImgSelect = $vehicleImgAvailable
        ? "COALESCE(NULLIF(v.vehicle_img, ''), NULLIF(h.car_img, ''), '') AS vehicle_img"
        : "COALESCE(NULLIF(h.car_img, ''), '') AS vehicle_img";

    $stmt = $pdo->prepare("\n        SELECT v.id, v.plate_number, v.vehicle_type, v.color, v.rfid_uid, v.is_active, {$vehicleImgSelect}\n        FROM vehicles v\n        LEFT JOIN homeowners h ON h.id = v.homeowner_id\n        WHERE v.homeowner_id = ?\n        ORDER BY v.is_active DESC, v.id DESC\n    ");
    $stmt->execute([$homeownerId]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'vehicles' => $vehicles, 'homeowner_id' => $homeownerId]);
} catch (Throwable $e) {
    error_log('[FETCH_HOMEOWNER_VEHICLES] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch vehicles']);
}
