<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
if (!in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}
require_once __DIR__ . '/../../db.php';

$type = $_GET['type'] ?? 'daily';
$format = $_GET['format'] ?? 'html';
$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d');

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    http_response_code(400);
    exit('Invalid date format. Use YYYY-MM-DD.');
}
if (strtotime($from) === false || strtotime($to) === false) {
    http_response_code(400);
    exit('Invalid date values.');
}

// Generate report based on type
switch ($type) {
    case 'daily':
        $title = 'Daily Activity Report - ' . date('F d, Y');
        $data = $pdo->query("
            SELECT r.*, h.name, h.vehicle_type
            FROM recent_logs r
            LEFT JOIN homeowners h ON r.plate_number = h.plate_number
            WHERE DATE(r.created_at) = CURDATE()
            ORDER BY r.created_at DESC
            LIMIT 10000
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'monthly':
        $title = 'Monthly Summary - ' . date('F Y');
        $data = $pdo->query("
            SELECT DATE(created_at) as date, 
                   COUNT(*) as total,
                   SUM(CASE WHEN status='IN' THEN 1 ELSE 0 END) as allowed,
                   SUM(CASE WHEN status='OUT' THEN 1 ELSE 0 END) as denied
            FROM recent_logs
            WHERE MONTH(created_at) = MONTH(CURDATE())
            AND YEAR(created_at) = YEAR(CURDATE())
            GROUP BY DATE(created_at)
            ORDER BY date
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'homeowners':
        $title = 'Homeowner Directory';
        $data = $pdo->query("
            SELECT id, name, address, contact_number, plate_number, vehicle_type, color
            FROM homeowners
            ORDER BY name
            LIMIT 10000
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'visitors':
        $title = 'Visitor Passes Report';
        $data = $pdo->query("
            SELECT vp.*, h.name as homeowner_name
            FROM visitor_passes vp
            JOIN homeowners h ON vp.homeowner_id = h.id
            ORDER BY vp.created_at DESC
            LIMIT 10000
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'security':
        $title = 'Security Incidents Report';
        $data = $pdo->query("
            SELECT r.*, h.name, h.vehicle_type
            FROM recent_logs r
            LEFT JOIN homeowners h ON r.plate_number = h.plate_number
            WHERE r.status = 'OUT'
            ORDER BY r.created_at DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'custom':
        $title = 'Custom Report: ' . htmlspecialchars($from) . ' to ' . htmlspecialchars($to);
        $stmt = $pdo->prepare("
            SELECT r.*, h.name, h.vehicle_type
            FROM recent_logs r
            LEFT JOIN homeowners h ON r.plate_number = h.plate_number
            WHERE DATE(r.created_at) BETWEEN ? AND ?
            ORDER BY r.created_at DESC
            LIMIT 10000
        ");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    default:
        $title = 'Report';
        $data = [];
}

// Output format
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='100%'>$title</th></tr>";
    if (!empty($data)) {
        echo "<tr>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th>".htmlspecialchars($key ?? '')."</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>".htmlspecialchars($val ?? '')."</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
    exit;
}

// HTML format
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($title ?? '') ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:hover { background: #f5f5f5; }
        .report-header { margin-bottom: 20px; }
        .report-meta { color: #7f8c8d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1><?= htmlspecialchars($title ?? '') ?></h1>
        <div class="report-meta">
            Generated: <?= date('F d, Y g:i:s A') ?><br>
            By: <?= htmlspecialchars($_SESSION['username'] ?? '') ?><br>
            Total Records: <?= count($data) ?>
        </div>
    </div>
    
    <?php if (empty($data)): ?>
        <p>No data available for this report.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php foreach (array_keys($data[0]) as $key): ?>
                        <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars($val ?? '-') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
