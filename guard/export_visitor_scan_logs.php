<?php
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../db.php';

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'visitor_pass_scan_logs'");
    $hasScanLogs = (bool)$tableExistsStmt->fetchColumn();

    if (!$hasScanLogs) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Scan history table not found yet'
        ]);
        exit();
    }

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

    $exportScope = strtolower(trim((string)($_GET['scope'] ?? 'all')));
    if ($exportScope !== 'page') {
        $exportScope = 'all';
    }

    $limitClause = 'LIMIT 50000';
    $filenameSuffix = 'all';
    if ($exportScope === 'page') {
        $page = (int)($_GET['page'] ?? 1);
        $page = max(1, min($page, 10000));

        $perPage = (int)($_GET['per_page'] ?? 25);
        $perPage = max(5, min($perPage, 100));

        $offset = ($page - 1) * $perPage;
        $limitClause = 'LIMIT :limit OFFSET :offset';
        $filenameSuffix = 'page_' . $page;
    }

    $stmt = $pdo->prepare("
        SELECT
            vpsl.scanned_at,
            vpsl.scan_status,
            vp.visitor_name,
            vp.visitor_plate,
            COALESCE(h.name, CONCAT(h.first_name, ' ', h.last_name), 'Unknown Homeowner') AS homeowner_name,
            vp.status AS pass_status
        FROM visitor_pass_scan_logs vpsl
        LEFT JOIN visitor_passes vp ON vp.id = vpsl.visitor_pass_id
        LEFT JOIN homeowners h ON h.id = vpsl.homeowner_id
        {$whereSql}
        ORDER BY vpsl.scanned_at DESC
        {$limitClause}
    ");

    foreach ($params as $paramName => $paramValue) {
        $stmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
    }
    if ($exportScope === 'page') {
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visitor_scan_history_' . $filenameSuffix . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $sanitize = static function ($value) {
        if (is_string($value) && isset($value[0]) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    };

    fputcsv($output, [
        'Scanned At',
        'Visitor Name',
        'Visitor Plate',
        'Homeowner',
        'Scan Status',
        'Pass Status',
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $scanStatus = str_replace('_', ' ', (string)($row['scan_status'] ?? 'scan'));
        fputcsv($output, array_map($sanitize, [
            (string)($row['scanned_at'] ?? ''),
            (string)($row['visitor_name'] ?? 'Unknown Visitor'),
            (string)($row['visitor_plate'] ?? ''),
            (string)($row['homeowner_name'] ?? 'Unknown Homeowner'),
            $scanStatus,
            (string)($row['pass_status'] ?? ''),
        ]));
    }

    fclose($output);
    exit();
} catch (Throwable $e) {
    error_log('[GUARD_EXPORT_VISITOR_SCAN_LOGS] ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to export visitor scan history'
        ]);
    }
    exit();
}
