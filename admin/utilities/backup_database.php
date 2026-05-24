<?php
// Clear any output buffers and suppress errors in output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../db.php';

/**
 * Get the secure backup directory path.
 * Prioritizes BACKUP_DIR env var, falls back to directory outside webroot.
 * 
 * @return string Path to backup directory
 */
function getSecureBackupDirectory() {
    // Check environment variable first
    $envBackupDir = getenv('BACKUP_DIR');
    if ($envBackupDir && is_dir($envBackupDir) && is_writable($envBackupDir)) {
        return rtrim($envBackupDir, '/\\');
    }
    
    // Fallback: Try to use a directory outside the web root
    // Standard XAMPP structure: htdocs/Vehiscan-RFID -> go up to xampp/ level
    $webRootParent = dirname(dirname(__DIR__)); // goes up to xampp/
    $secureDir = $webRootParent . '/vehiscan_backups';
    
    // If parent of webroot isn't writable, use app-level backups with enhanced protection
    if (!is_writable($webRootParent)) {
        $secureDir = __DIR__ . '/../backups_db_secure';
    }
    
    return rtrim($secureDir, '/\\');
}

requireRequestMethod('POST');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// CSRF validation (accept only JSON body or POST body, never query string)
$inputData = json_decode(file_get_contents('php://input'), true);
if (!is_array($inputData)) {
    $inputData = [];
}
$csrf = $inputData['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
if (!InputSanitizer::validateCsrf((string)$csrf)) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Clear any accumulated output
ob_end_clean();
header('Content-Type: application/json');

// Set PDO error mode to exception
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Get secure backup directory
    $backup_dir = getSecureBackupDirectory();
    
    // Ensure backup directory exists with proper permissions
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0700, true)) {
            throw new Exception('Failed to create backup directory at: ' . $backup_dir);
        }
    }
    
    // Verify directory is writable
    if (!is_writable($backup_dir)) {
        throw new Exception('Backup directory is not writable: ' . $backup_dir);
    }

    // Ensure backup directory is protected from direct web access (if in webroot)
    $htaccessPath = $backup_dir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        $denyAllRules = "# Deny direct web access to DB backups\n"
            . "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order deny,allow\n"
            . "    Deny from all\n"
            . "</IfModule>\n";
        @file_put_contents($htaccessPath, $denyAllRules);
    }
    
    // Create index.php to prevent directory listing
    $indexPath = $backup_dir . '/index.php';
    if (!file_exists($indexPath)) {
        @file_put_contents($indexPath, "<?php // Backup directory - access denied\nhttp_response_code(403);\nexit();\n");
    }

    $filename = 'vehiscan_backup_' . date('Y-m-d_His') . '.sql';
    $filepath = $backup_dir . '/' . $filename;

    // Get database config from constants
    $host = DB_HOST;
    $db = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    // Create backup using mysqldump with properly escaped arguments
    // All credentials are shell-escaped to prevent command injection
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --skip-comments %s > %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($db),
        escapeshellarg($filepath)
    );
    exec($command, $output, $return_var);

    if ($return_var === 0 && file_exists($filepath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'size' => round(filesize($filepath) / 1024, 2) . ' KB'
        ]);
    } else {
        // Fallback: PHP-based backup
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "-- VehiScan Database Backup\n";
        $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            // Sanitize table name to prevent SQL injection
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $sql .= "DROP TABLE IF EXISTS `$safeTable`;\n";
            
            // Use prepared statement for SHOW CREATE TABLE
            $stmt = $pdo->prepare("SHOW CREATE TABLE `$safeTable`");
            $stmt->execute();
            $create = $stmt->fetch(PDO::FETCH_NUM);
            if (!$create || !isset($create[1])) {
                continue; // Skip if table creation statement not available
            }
            $sql .= $create[1] . ";\n\n";
            
            // Use prepared statement for SELECT
            $stmt = $pdo->prepare("SELECT * FROM `$safeTable`");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $values = array_map(function($val) use ($pdo) {
                        return $val === null ? 'NULL' : $pdo->quote($val);
                    }, array_values($row));
                    $sql .= "INSERT INTO `$safeTable` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        file_put_contents($filepath, $sql);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully (PHP mode)',
            'filename' => $filename,
            'size' => round(filesize($filepath) / 1024, 2) . ' KB'
        ]);
    }
} catch (Exception $e) {
    error_log('Backup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Backup failed. Please check server logs for details.'
    ]);
}
exit();
