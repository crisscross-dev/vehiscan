<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

try {
    $columns = $pdo->query("SHOW COLUMNS FROM recent_logs")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('created_at', $columns, true)) {
        $timeExpr = "TIME_FORMAT(rl.created_at, '%h:%i:%s %p')";
        $orderExpr = 'rl.created_at';
    } elseif (in_array('log_time', $columns, true)) {
        $timeExpr = "TIME_FORMAT(rl.log_time, '%h:%i:%s %p')";
        $orderExpr = 'rl.log_time';
    } else {
        throw new RuntimeException('No supported timestamp column found on recent_logs');
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($perPage < 1) {
        $perPage = 10;
    }
    $perPage = min($perPage, 100);
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->query("SELECT COUNT(*) FROM recent_logs");
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("\
        SELECT rl.plate_number,
               $timeExpr as time,
               rl.status,
               h.name, h.vehicle_type
        FROM recent_logs rl
        LEFT JOIN homeowners h ON rl.plate_number = h.plate_number
        ORDER BY $orderExpr DESC, rl.log_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'scans' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int)ceil($total / $perPage)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
