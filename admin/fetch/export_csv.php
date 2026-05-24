<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Unauthorized access');
}

// require PDO connection (db.php)
require_once __DIR__ . '/../../db.php';

// Prevent any accidental output before headers
if (ob_get_length()) ob_end_clean();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="homeowners_export.csv"');
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
fputcsv($out, ['ID', 'Name', 'Plate', 'Vehicle', 'Contact', 'Address']);

// Fetch rows using PDO
try {
    $stmt = $pdo->query("SELECT id, name, plate_number, vehicle_type, contact_number, address FROM homeowners WHERE account_status = 'approved' ORDER BY id DESC LIMIT 50000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $line = array_map('sanitizeCsvValue', [
            $row['id'] ?? '',
            $row['name'] ?? '',
            $row['plate_number'] ?? '',
            $row['vehicle_type'] ?? '',
            $row['contact_number'] ?? '',
            $row['address'] ?? ''
        ]);
        fputcsv($out, $line);
    }
} catch (Exception $e) {
    error_log('[EXPORT_CSV] Error: ' . $e->getMessage());
    fputcsv($out, ['error', 'Export failed']);
}

fclose($out);
exit;
