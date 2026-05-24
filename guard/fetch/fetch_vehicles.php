<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pagination = normalizePagination($_GET['page'] ?? 1, $_GET['per_page'] ?? 25, [10, 25, 50, 100], 25, 10000);
    $page = $pagination['page'];
    $perPage = $pagination['per_page'];
    $offset = $pagination['offset'];

    $search = trim((string)($_GET['search'] ?? ''));
    $params = [];
    $where = "WHERE h.account_status = 'approved'";

    if ($search !== '') {
        $where .= " AND (v.plate_number LIKE ? OR h.name LIKE ? OR v.vehicle_type LIKE ? OR v.color LIKE ? OR COALESCE(v.rfid_uid, '') LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $countStmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM vehicles v\n        INNER JOIN homeowners h ON h.id = v.homeowner_id\n        $where\n    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $clamped = clampPaginationPage($page, $total, $perPage);
    $page = $clamped['page'];
    $totalPages = $clamped['total_pages'];
    $offset = $clamped['offset'];

    $sql = "\n        SELECT\n            v.id, v.homeowner_id, v.plate_number, v.vehicle_type, v.color, v.rfid_uid,\n            v.is_active, v.is_primary, h.name AS homeowner_name\n        FROM vehicles v\n        INNER JOIN homeowners h ON h.id = v.homeowner_id\n        $where\n        ORDER BY v.is_active DESC, v.is_primary DESC, v.id DESC\n        LIMIT $perPage OFFSET $offset\n    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Throwable $e) {
    error_log('[FETCH_VEHICLES] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch vehicles']);
}
