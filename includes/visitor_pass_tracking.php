<?php
/**
 * Visitor pass scan tracking helpers.
 */

function ensureVisitorPassScanLogsTable(PDO $pdo): bool
{
    static $initialized = false;
    static $tableExists = false;
    
    if ($initialized) {
        return $tableExists;
    }

    // Check if table already exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'visitor_pass_scan_logs'");
        $tableExists = (bool)$stmt->fetchColumn();
        
        if ($tableExists) {
            $initialized = true;
            return true;
        }
    } catch (Exception $e) {
        error_log('[VISITOR_PASS] Table check failed: ' . $e->getMessage());
        $initialized = true;
        return false;
    }

    // Try to create table if it doesn't exist (for development environments)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_pass_scan_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_pass_id INT NOT NULL,
            homeowner_id INT NULL,
            qr_token VARCHAR(128) NULL,
            scan_status VARCHAR(32) NOT NULL,
            scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            scanner_ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            notes TEXT NULL,
            INDEX idx_pass (visitor_pass_id),
            INDEX idx_homeowner (homeowner_id),
            INDEX idx_scanned_at (scanned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $tableExists = true;
    } catch (Exception $e) {
        // CREATE TABLE failed (likely permission issue on restrictive hosting)
        error_log('[VISITOR_PASS] Table creation failed: ' . $e->getMessage());
        $tableExists = false;
    }

    $initialized = true;
    return $tableExists;
}

function getVisitorPassScanStats(PDO $pdo, int $passId): array
{
    if (!ensureVisitorPassScanLogsTable($pdo)) {
        // Table doesn't exist - return default stats
        return [
            'scan_count' => 0,
            'first_scanned_at' => null,
            'last_scanned_at' => null,
        ];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS scan_count, MIN(scanned_at) AS first_scanned_at, MAX(scanned_at) AS last_scanned_at FROM visitor_pass_scan_logs WHERE visitor_pass_id = ?");
        $stmt->execute([$passId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'scan_count' => (int)($row['scan_count'] ?? 0),
            'first_scanned_at' => $row['first_scanned_at'] ?? null,
            'last_scanned_at' => $row['last_scanned_at'] ?? null,
        ];
    } catch (Exception $e) {
        error_log('[VISITOR_PASS] Error querying stats: ' . $e->getMessage());
        return [
            'scan_count' => 0,
            'first_scanned_at' => null,
            'last_scanned_at' => null,
        ];
    }
}

function recordVisitorPassScan(PDO $pdo, array $pass, string $scanStatus, string $scannerIp, string $userAgent, ?string $notes = null): void
{
    if (!ensureVisitorPassScanLogsTable($pdo)) {
        // Table doesn't exist - log a warning but don't fail
        error_log('[VISITOR_PASS] Cannot record scan - table does not exist');
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO visitor_pass_scan_logs (visitor_pass_id, homeowner_id, qr_token, scan_status, scanner_ip, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (int)($pass['id'] ?? 0),
            isset($pass['homeowner_id']) ? (int)$pass['homeowner_id'] : null,
            $pass['qr_token'] ?? null,
            $scanStatus,
            $scannerIp !== '' ? $scannerIp : null,
            $userAgent !== '' ? substr($userAgent, 0, 255) : null,
            $notes,
        ]);
    } catch (Exception $e) {
        error_log('[VISITOR_PASS] Error recording scan: ' . $e->getMessage());
        // Don't throw - allow the request to continue even if scan recording fails
    }
}
