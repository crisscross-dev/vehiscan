<?php
/**
 * Get Visitor Pass Scan Logs
 * Returns detailed visitor pass scanning activity with pagination and filtering
 */

require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';

// Ensure admin access
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';

header('Content-Type: application/json');

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'visitor_pass_scan_logs'");
    $hasScanLogs = (bool)$tableExistsStmt->fetchColumn();

    if (!$hasScanLogs) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 20,
                'total' => 0,
                'total_pages' => 1,
            ]
        ]);
        exit;
    }

    $page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
    $per_page = (int)($_GET['per_page'] ?? 20);
    if ($per_page < 5) {
        $per_page = 5;
    } elseif ($per_page > 50) {
        $per_page = 50;
    }
    $search = trim($_GET['search'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    
    // Build query
    $query = "
        SELECT 
            vpsl.id,
            vpsl.visitor_pass_id,
            vpsl.homeowner_id,
            vpsl.scan_status,
            vpsl.scanned_at,
            vpsl.scanner_ip,
            vpsl.notes,
            vp.visitor_name,
            vp.valid_from,
            vp.valid_until,
            h.name AS homeowner_name,
            h.email AS homeowner_email
        FROM visitor_pass_scan_logs vpsl
        LEFT JOIN visitor_passes vp ON vpsl.visitor_pass_id = vp.id
        LEFT JOIN homeowners h ON vpsl.homeowner_id = h.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $query .= " AND (vp.visitor_name LIKE ? OR h.name LIKE ? OR h.email LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $query .= " AND vpsl.scan_status = ?";
        $params[] = $status_filter;
    }
    
    // Date range filter
    if (!empty($date_from)) {
        $query .= " AND DATE(vpsl.scanned_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $query .= " AND DATE(vpsl.scanned_at) <= ?";
        $params[] = $date_to;
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM visitor_pass_scan_logs vpsl 
                    LEFT JOIN visitor_passes vp ON vpsl.visitor_pass_id = vp.id 
                    LEFT JOIN homeowners h ON vpsl.homeowner_id = h.id 
                    WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (vp.visitor_name LIKE ? OR h.name LIKE ? OR h.email LIKE ?)";
    }
    if (!empty($status_filter)) {
        $count_query .= " AND vpsl.scan_status = ?";
    }
    if (!empty($date_from)) {
        $count_query .= " AND DATE(vpsl.scanned_at) >= ?";
    }
    if (!empty($date_to)) {
        $count_query .= " AND DATE(vpsl.scanned_at) <= ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    
    // Add pagination
    $offset = ($page - 1) * $per_page;
    $query .= " ORDER BY vpsl.scanned_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Fetch data
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $formatted_logs = [];
    foreach ($logs as $log) {
        $formatted_logs[] = [
            'id' => (int)$log['id'],
            'visitor_pass_id' => (int)$log['visitor_pass_id'],
            'visitor_name' => htmlspecialchars($log['visitor_name'] ?? 'N/A'),
            'homeowner_name' => htmlspecialchars($log['homeowner_name'] ?? 'N/A'),
            'homeowner_email' => htmlspecialchars($log['homeowner_email'] ?? ''),
            'scan_status' => ucfirst($log['scan_status']),
            'scanned_at' => $log['scanned_at'],
            'scanned_at_formatted' => date('M d, Y g:i A', strtotime($log['scanned_at'])),
            'valid_from' => $log['valid_from'],
            'valid_until' => $log['valid_until'],
            'scanner_ip' => htmlspecialchars($log['scanner_ip'] ?? 'N/A'),
            'notes' => htmlspecialchars($log['notes'] ?? '')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int)ceil($total / $per_page)
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Visitor pass logs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch visitor pass logs'
    ]);
}
?>
