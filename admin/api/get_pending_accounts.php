<?php
/**
 * Get Pending User Accounts for Admin Approval
 */

require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Load pending homeowners
    $homeownersStmt = $pdo->query(" 
        SELECT
            h.id,
            h.name,
            h.first_name,
            h.middle_name,
            h.last_name,
            h.suffix,
            h.email,
            h.contact_number,
            h.address,
            h.plate_number,
            h.vehicle_type,
            h.color,
            h.owner_img,
            h.car_img,
            h.created_at,
            COALESCE(ha.username, h.username) AS username,
            'homeowner' AS role,
            'homeowner' AS account_type
        FROM homeowners h
        LEFT JOIN homeowner_auth ha ON h.id = ha.homeowner_id
        WHERE h.account_status = 'pending'
    ");

    // Load pending users
    $usersStmt = $pdo->query(" 
        SELECT
            u.id,
            NULL AS name,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.suffix,
            u.email,
            NULL AS contact_number,
            NULL AS address,
            NULL AS plate_number,
            NULL AS vehicle_type,
            NULL AS color,
            NULL AS owner_img,
            NULL AS car_img,
            u.created_at,
            u.username,
            COALESCE(NULLIF(u.role, ''), 'user') AS role,
            'user' AS account_type
        FROM users u
        WHERE u.account_status = 'pending'
    ");

    $accounts = array_merge(
        $homeownersStmt->fetchAll(PDO::FETCH_ASSOC),
        $usersStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    usort($accounts, static function (array $a, array $b): int {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    echo json_encode($accounts);
} catch (PDOException $e) {
    error_log('Error loading pending accounts: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load pending accounts']);
}
