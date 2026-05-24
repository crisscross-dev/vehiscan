<?php
// admin/fetch/export_logs_csv.php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Unauthorized access');
}

require_once __DIR__ . '/../../db.php';

// Prevent any accidental output before headers
if (ob_get_length()) ob_end_clean();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="access_logs_export.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Sanitize CSV values to prevent formula injection
function sanitizeCsvValue($value) {
    if (is_string($value) && isset($value[0]) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

// Open output stream
$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    exit("Unable to open output stream");
}

// Column headers
fputcsv($out, ['Log ID', 'Date/Time', 'Plate Number', 'Status', 'Owner Name', 'Vehicle Type']);

// Fetch rows using PDO
try {
    $month = trim((string)($_GET['month'] ?? ''));
    if ($month === '') {
        $month = date('Y-m');
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        http_response_code(400);
        fputcsv($out, ['error', 'Invalid month format']);
        fclose($out);
        exit;
    }

    $plateFilter = strtoupper(trim((string)($_GET['plate'] ?? '')));
    $plateFilter = preg_replace('/[^A-Z0-9\- ]/', '', $plateFilter);
    $plateCanonical = str_replace([' ', '-'], '', $plateFilter);

    $conditions = ["DATE_FORMAT(r.created_at, '%Y-%m') = :month"];
    $params = [':month' => $month];

    if ($plateCanonical !== '') {
        $conditions[] = "REPLACE(REPLACE(UPPER(r.plate_number), ' ', ''), '-', '') LIKE :plate";
        $params[':plate'] = '%' . $plateCanonical . '%';
    }

    $stmt = $pdo->prepare("
        SELECT r.log_id, r.created_at, r.plate_number, r.status, h.name, h.vehicle_type
        FROM recent_logs r
        LEFT JOIN homeowners h ON r.plate_number = h.plate_number
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY r.created_at DESC, r.log_id DESC
        LIMIT 50000
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $line = array_map('sanitizeCsvValue', [
            $row['log_id'] ?? '',
            $row['created_at'] ?? '',
            $row['plate_number'] ?? '',
            $row['status'] ?? '',
            $row['name'] ?? 'Unknown',
            $row['vehicle_type'] ?? '-'
        ]);
        fputcsv($out, $line);
    }
} catch (Exception $e) {
    error_log('[EXPORT_LOGS_CSV] Error: ' . $e->getMessage());
    fputcsv($out, ['error', 'Export failed']);
}

fclose($out);
exit;
