<?php
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db.php';

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'visitor_pass_scan_logs'");
    $hasScanLogs = (bool)$tableExistsStmt->fetchColumn();

    if (!$hasScanLogs) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'logs' => [],
            'count' => 0,
            'pagination' => [
                'page' => 1,
                'per_page' => 25,
                'total_count' => 0,
                'total_pages' => 1,
            ],
            'message' => 'Scan history table not found yet'
        ]);
        exit();
    }

    $page = (int)($_GET['page'] ?? 1);
    $page = max(1, min($page, 10000));

    $perPage = (int)($_GET['per_page'] ?? ($_GET['limit'] ?? 25));
    $perPage = max(5, min($perPage, 100));

    $search = trim((string)($_GET['search'] ?? ''));
    if (strlen($search) > 100) {
        $search = substr($search, 0, 100);
    }

    $scanStatus = strtolower(trim((string)($_GET['scan_status'] ?? '')));
    $allowedStatuses = ['used_first_time', 'repeat_scan', 'scan'];
    if ($scanStatus !== '' && !in_array($scanStatus, $allowedStatuses, true)) {
        $scanStatus = '';
    }

    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $isValidDate = static function (string $value): bool {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    };

    if ($dateFrom !== '' && !$isValidDate($dateFrom)) {
        $dateFrom = '';
    }
    if ($dateTo !== '' && !$isValidDate($dateTo)) {
        $dateTo = '';
    }

    if ($dateFrom !== '' && $dateTo !== '' && strcmp($dateFrom, $dateTo) > 0) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $whereClauses = [];
    $params = [];

    if ($search !== '') {
        $whereClauses[] = "(
            vp.visitor_name LIKE :search
            OR vp.visitor_plate LIKE :search
            OR COALESCE(h.name, CONCAT(h.first_name, ' ', h.last_name), '') LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    if ($scanStatus !== '') {
        $whereClauses[] = 'vpsl.scan_status = :scan_status';
        $params[':scan_status'] = $scanStatus;
    }

    if ($dateFrom !== '') {
        $whereClauses[] = 'vpsl.scanned_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $whereClauses[] = 'vpsl.scanned_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $countSql = "
        SELECT COUNT(*)
        FROM visitor_pass_scan_logs vpsl
        LEFT JOIN visitor_passes vp ON vp.id = vpsl.visitor_pass_id
        LEFT JOIN homeowners h ON h.id = vpsl.homeowner_id
        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $paramName => $paramValue) {
        $countStmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            vpsl.id,
            vpsl.scan_status,
            vpsl.scanned_at,
            vp.visitor_name,
            vp.visitor_plate,
            vp.status AS pass_status,
            COALESCE(h.name, CONCAT(h.first_name, ' ', h.last_name), 'Unknown Homeowner') AS homeowner_name
        FROM visitor_pass_scan_logs vpsl
        LEFT JOIN visitor_passes vp ON vp.id = vpsl.visitor_pass_id
        LEFT JOIN homeowners h ON h.id = vpsl.homeowner_id
        {$whereSql}
        ORDER BY vpsl.scanned_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramName => $paramValue) {
        $stmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'count' => count($logs),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_count' => $totalCount,
            'total_pages' => $totalPages,
        ],
        'filters' => [
            'search' => $search,
            'scan_status' => $scanStatus,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]
    ]);
} catch (Throwable $e) {
    error_log('[GUARD_FETCH_VISITOR_SCAN_LOGS] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch visitor scan history'
    ]);
}
