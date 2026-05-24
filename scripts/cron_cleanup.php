<?php
/**
 * Scheduled Cleanup Script
 * 
 * Cleans up expired/stale data from various tables.
 * Run via cron job: php /path/to/scripts/cron_cleanup.php
 * Recommended schedule: daily at 2:00 AM
 * 
 * Example crontab entry:
 *   0 2 * * * /usr/bin/php /var/www/html/scripts/cron_cleanup.php >> /var/log/vehiscan_cleanup.log 2>&1
 */

// CLI-only execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$startTime = microtime(true);
$results = [];

// 1. Clean up expired RFID binding sessions (older than 24 hours past expiry)
try {
    $stmt = $pdo->prepare("
        DELETE FROM rfid_binding_sessions 
        WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND status IN ('timeout', 'cancelled', 'completed')
        LIMIT 5000
    ");
    $stmt->execute();
    $results['rfid_binding_sessions'] = $stmt->rowCount();
} catch (PDOException $e) {
    $results['rfid_binding_sessions'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] rfid_binding_sessions error: ' . $e->getMessage());
}

// 2. Mark expired pending RFID binding sessions as timed out
try {
    $stmt = $pdo->prepare("
        UPDATE rfid_binding_sessions 
        SET status = 'timeout' 
        WHERE status = 'pending' 
        AND expires_at < NOW()
        LIMIT 1000
    ");
    $stmt->execute();
    $results['rfid_binding_timeouts'] = $stmt->rowCount();
} catch (PDOException $e) {
    $results['rfid_binding_timeouts'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] rfid_binding_timeouts error: ' . $e->getMessage());
}

// 3. Mark expired visitor passes
try {
    $stmt = $pdo->prepare("
        UPDATE visitor_passes 
        SET status = 'expired' 
        WHERE status = 'approved' 
        AND valid_until < NOW()
        LIMIT 5000
    ");
    $stmt->execute();
    $results['visitor_passes_expired'] = $stmt->rowCount();
} catch (PDOException $e) {
    $results['visitor_passes_expired'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] visitor_passes_expired error: ' . $e->getMessage());
}

// 4. Clean up old rate limit entries (older than 7 days)
try {
    $stmt = $pdo->prepare("
        DELETE FROM rate_limits 
        WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 7 DAY)
        LIMIT 10000
    ");
    $stmt->execute();
    $results['rate_limits_cleaned'] = $stmt->rowCount();
} catch (PDOException $e) {
    $results['rate_limits_cleaned'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] rate_limits error: ' . $e->getMessage());
}

// 5. Clean up old RFID scan log entries (older than 90 days)
try {
    $stmt = $pdo->prepare("
        DELETE FROM rfid_scan_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        LIMIT 50000
    ");
    $stmt->execute();
    $results['rfid_scan_log_cleaned'] = $stmt->rowCount();
} catch (PDOException $e) {
    $results['rfid_scan_log_cleaned'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] rfid_scan_log error: ' . $e->getMessage());
}

// 6. Clean up old enhanced audit logs (older than 365 days)
try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'audit_logs_enhanced'");
    if ($tableExistsStmt->fetchColumn()) {
        $stmt = $pdo->prepare("\
            DELETE FROM audit_logs_enhanced
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
            LIMIT 50000
        ");
        $stmt->execute();
        $results['audit_logs_enhanced_cleaned'] = $stmt->rowCount();
    } else {
        $results['audit_logs_enhanced_cleaned'] = 'SKIPPED: table not found';
    }
} catch (PDOException $e) {
    $results['audit_logs_enhanced_cleaned'] = 'ERROR: ' . $e->getMessage();
    error_log('[CRON_CLEANUP] audit_logs_enhanced error: ' . $e->getMessage());
}

$elapsed = round(microtime(true) - $startTime, 3);

// Output summary
$timestamp = date('Y-m-d H:i:s');
echo "[$timestamp] Cleanup completed in {$elapsed}s\n";
foreach ($results as $task => $count) {
    echo "  - $task: $count rows affected\n";
}
echo "\n";
