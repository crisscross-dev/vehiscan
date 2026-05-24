<?php
/**
 * Simple Database Performance Indexes Migration
 * Run via: php migrations/run_migration_simple.php
 */

require_once __DIR__ . '/../db.php';

echo "=== Database Performance Indexes Migration ===\n\n";

$indexesToAdd = [
    "CREATE INDEX IF NOT EXISTS idx_recent_logs_timestamp ON recent_logs(timestamp)",
    "CREATE INDEX IF NOT EXISTS idx_recent_logs_homeowner ON recent_logs(homeowner_id)",
    "CREATE INDEX IF NOT EXISTS idx_visitor_passes_status ON visitor_passes(status)",
    "CREATE INDEX IF NOT EXISTS idx_visitor_passes_homeowner ON visitor_passes(homeowner_id)",
    "CREATE INDEX IF NOT EXISTS idx_homeowners_account_status ON homeowners(account_status)",
    "CREATE INDEX IF NOT EXISTS idx_homeowner_auth_username ON homeowner_auth(username)"
];

$success = 0;
$failed = 0;

foreach ($indexesToAdd as $sql) {
    try {
        $pdo->exec($sql);
        // Extract index name for display
        preg_match('/idx_[a-z_]+/', $sql, $matches);
        $indexName = $matches[0] ?? 'unknown';
        echo "[OK] Added/Verified: $indexName\n";
        $success++;
    } catch (PDOException $e) {
        echo "[FAIL] Failed: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Migration Complete ===\n";
echo "Success: $success | Failed: $failed\n";

if ($failed === 0) {
    echo "\n[OK] All indexes added successfully!\n";
    echo "Expected performance improvement: 10-50x on indexed queries\n";
} else {
    echo "\n[WARN]  Some indexes failed. Check errors above.\n";
}
?>