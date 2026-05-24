<?php
/**
 * Admin-only DB backup wrapper
 * Requires `mysqldump` available on the host and proper shell access.
 * Creates a timestamped SQL dump in `backups_db/` and returns a download link.
 */
require_once __DIR__ . '/../includes/session_admin_unified.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Check admin session
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    echo 'Forbidden: admin access required.';
    exit();
}

$backupDir = __DIR__ . '/../backups_db';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}

$timestamp = date('Ymd_His');
$filename = "vehiscan_db_{$timestamp}.sql";
$filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

// Read DB credentials from config constants or env
$dbHost = defined('DB_HOST') ? DB_HOST : getenv('DB_HOST');
$dbPort = defined('DB_PORT') ? DB_PORT : getenv('DB_PORT');
$dbName = defined('DB_NAME') ? DB_NAME : getenv('DB_NAME');
$dbUser = defined('DB_USER') ? DB_USER : getenv('DB_USER');
$dbPass = defined('DB_PASS') ? DB_PASS : getenv('DB_PASS');

// Build mysqldump command
$mysqldump = 'mysqldump';
$cmd = sprintf(
    "%s --single-transaction --quick --lock-tables=false -h %s -P %s -u %s %s %s > %s",
    escapeshellcmd($mysqldump),
    escapeshellarg($dbHost),
    escapeshellarg($dbPort ?: '3306'),
    escapeshellarg($dbUser),
    $dbPass ? ("-p" . escapeshellarg($dbPass)) : "",
    escapeshellarg($dbName),
    escapeshellarg($filepath)
);

// Try executing
exec($cmd . ' 2>&1', $output, $ret);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>DB Backup</title></head>
<body style="font-family:system-ui,Segoe UI,Arial; padding:20px;">
<h1>Database Backup</h1>
<?php
if ($ret === 0) {
    echo "<p style='color:green;'>Backup created: <a href=\"../backups_db/{$filename}\">{$filename}</a></p>";
    echo "<p>Save this file offsite. Consider encrypting backups for production.</p>";
} else {
    echo "<p style='color:red;'>Backup failed (mysqldump exit: {$ret}).</p>";
    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    echo "<p>Alternative: use your hosting provider's DB export tool or run <code>mysqldump</code> from the server shell.</p>";
}
?>
<p><a href="../admin/admin_panel.php">→ Admin Panel</a></p>
</body>
</html>