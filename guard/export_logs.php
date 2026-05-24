<?php
require_once __DIR__ . '/../includes/session_guard.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once __DIR__ . '/../db.php';

try {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="access_logs_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Sanitize CSV values to prevent formula injection
    $sanitize = function($value) {
        if (is_string($value) && isset($value[0]) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    };

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write CSV header
    fputcsv($output, [
        'Timestamp',
        'Homeowner Name',
        'Plate Number',
        'Status',
        'Vehicle Type',
        'Color'
    ]);
    
    // Fetch all logs with homeowner info
    $stmt = $pdo->query("
        SELECT 
            al.created_at as timestamp,
            h.name as homeowner_name,
            al.plate_number,
            al.status,
            h.vehicle_type,
            h.color
        FROM recent_logs al
        LEFT JOIN homeowners h ON al.plate_number = h.plate_number
        ORDER BY al.created_at DESC, al.log_id DESC
        LIMIT 50000
    ");
    
    // Write data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, array_map($sanitize, [
            date('Y-m-d H:i:s', strtotime($row['timestamp'])),
            $row['homeowner_name'] ?? 'N/A',
            $row['plate_number'] ?? 'N/A',
            $row['status'] ?? 'Unknown',
            $row['vehicle_type'] ?? 'N/A',
            $row['color'] ?? 'N/A'
        ]));
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    error_log("[GUARD] Export logs error: " . $e->getMessage());
    
    // If headers already sent (CSV started), we can't change to JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Export failed. Please try again later.'
        ]);
    }
    exit();
}
