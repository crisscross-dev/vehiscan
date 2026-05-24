<?php
/**
 * Get activity logs for homeowner's vehicles
 */
ob_start();
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['homeowner_id'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'error' => 'Unauthorized']);
    exit();
}

try {
    $period = $_GET['period'] ?? 'week'; // day, week, month
    $vehicleId = $_GET['vehicle_id'] ?? null;
    
    // Determine date range
    switch ($period) {
        case 'day':
            $dateFrom = date('Y-m-d 00:00:00');
            $groupBy = "DATE_FORMAT(COALESCE(al.created_at, al.log_time), '%H:00')";
            $dateFormat = '%H:00';
            break;
        case 'month':
            $dateFrom = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $groupBy = "DATE(COALESCE(al.created_at, al.log_time))";
            $dateFormat = '%Y-%m-%d';
            break;
        case 'week':
        default:
            $dateFrom = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $groupBy = "DATE(COALESCE(al.created_at, al.log_time))";
            $dateFormat = '%Y-%m-%d';
            break;
    }
    
    // Get plate numbers for this homeowner
    $stmt = $pdo->prepare("
        SELECT DISTINCT plate_number FROM (
            SELECT plate_number FROM homeowners WHERE id = ?
            UNION
            SELECT plate_number FROM vehicles WHERE homeowner_id = ? AND is_active = 1
        ) AS all_plates
        WHERE plate_number IS NOT NULL AND plate_number != ''
    ");
    $stmt->execute([$_SESSION['homeowner_id'], $_SESSION['homeowner_id']]);
    $plateNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($plateNumbers)) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'activity' => [],
            'summary' => [
                'total_entries' => 0,
                'total_exits' => 0,
                'total_logs' => 0
            ]
        ]);
        exit();
    }
    
    $placeholders = implode(',', array_fill(0, count($plateNumbers), '?'));
    
    // Get activity data grouped by time period
    $query = "
        SELECT 
            DATE_FORMAT(COALESCE(al.created_at, al.log_time), '$dateFormat') as period,
            SUM(CASE WHEN al.status = 'IN' THEN 1 ELSE 0 END) as entries,
            SUM(CASE WHEN al.status = 'OUT' THEN 1 ELSE 0 END) as exits,
            COUNT(*) as total
        FROM recent_logs al
        WHERE al.plate_number IN ($placeholders)
          AND COALESCE(al.created_at, al.log_time) >= ?
        GROUP BY $groupBy
        ORDER BY COALESCE(al.created_at, al.log_time) ASC
    ";
    
    $params = array_merge($plateNumbers, [$dateFrom]);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary
    $summaryQuery = "
        SELECT 
            SUM(CASE WHEN status = 'IN' THEN 1 ELSE 0 END) as total_entries,
            SUM(CASE WHEN status = 'OUT' THEN 1 ELSE 0 END) as total_exits,
            COUNT(*) as total_logs
        FROM recent_logs
        WHERE plate_number IN ($placeholders)
          AND COALESCE(created_at, log_time) >= ?
    ";
    
    $stmt = $pdo->prepare($summaryQuery);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'period' => $period,
        'activity' => $activity,
        'summary' => $summary
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Get vehicle activity error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch activity data',
        'error' => 'Failed to fetch activity data'
    ]);
}
