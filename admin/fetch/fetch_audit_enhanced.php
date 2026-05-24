<?php
/**
 * Fetch Enhanced Audit Logs
 * 
 * API endpoint to retrieve audit logs with filtering
 * Requires Super Admin or Admin authentication
 * 
 * @version 1.0.0
 * @created 2025-11-20
 */
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

// Check authentication
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

try {
    AuditLogger::init($pdo);
    
    // Get filter parameters
    $eventType = $_GET['event_type'] ?? null;
    $severity = $_GET['severity'] ?? null;
    $status = $_GET['status'] ?? null;
    $username = $_GET['username'] ?? null;
    $dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
    $dateToRaw = trim((string)($_GET['date_to'] ?? ''));
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $dateFrom = null;
    $dateTo = null;

    if ($dateFromRaw !== '') {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateFromRaw);
        if (!$parsed || $parsed->format('Y-m-d') !== $dateFromRaw) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date_from format. Expected YYYY-MM-DD.'
            ]);
            exit;
        }
        $dateFrom = $parsed->setTime(0, 0, 0);
    }

    if ($dateToRaw !== '') {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateToRaw);
        if (!$parsed || $parsed->format('Y-m-d') !== $dateToRaw) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date_to format. Expected YYYY-MM-DD.'
            ]);
            exit;
        }
        $dateTo = $parsed->setTime(23, 59, 59);
    }

    if ($dateFrom && $dateTo) {
        if ($dateFrom > $dateTo) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'date_from must be earlier than or equal to date_to.'
            ]);
            exit;
        }

        $daysDiff = (int)$dateFrom->diff($dateTo)->days;
        if ($daysDiff > 366) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Date range cannot exceed 366 days.'
            ]);
            exit;
        }
    }
    
    // Build query
    $sql = "SELECT * FROM audit_logs_enhanced WHERE 1=1";
    $params = [];
    
    if ($eventType) {
        $sql .= " AND event_type = ?";
        $params[] = $eventType;
    }
    
    if ($severity) {
        $sql .= " AND severity = ?";
        $params[] = $severity;
    }
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    if ($username) {
        $sql .= " AND username = ?";
        $params[] = $username;
    }

    if ($dateFrom) {
        $sql .= " AND created_at >= ?";
        $params[] = $dateFrom->format('Y-m-d H:i:s');
    }

    if ($dateTo) {
        $sql .= " AND created_at <= ?";
        $params[] = $dateTo->format('Y-m-d H:i:s');
    }
    
    // Get total count
    $countSql = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add ordering and pagination - use integer casting to avoid PDO string quoting
    $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    foreach ($logs as &$log) {
        if ($log['old_values']) {
            $log['old_values'] = json_decode($log['old_values'], true);
        }
        if ($log['new_values']) {
            $log['new_values'] = json_decode($log['new_values'], true);
        }
    }
    
    // Get statistics if requested
    $stats = null;
    if (isset($_GET['include_stats']) && $_GET['include_stats'] === 'true') {
        $stats = AuditLogger::getStats(7);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log('Audit log fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch audit logs. Please try again later.'
    ]);
}
