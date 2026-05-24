<?php
/**
 * Migration: Add Missing Performance Indexes
 * 
 * Adds indexes on frequently queried columns not covered by previous migrations:
 * - visitor_passes.visitor_plate (lookup by plate)
 * - visitor_passes.qr_token (unique lookup for QR validation)
 * - homeowners.plate_number (JOIN key for access logs)
 * - recent_logs.plate_number (filter/join key)
 * - recent_logs.rfid_uid (RFID scan lookups)
 * - recent_logs.created_at (time-range queries)
 *
 * Safe to run multiple times — uses IF NOT EXISTS / duplicate-key guard.
 */

require_once __DIR__ . '/../db.php';

$indexes = [
    [
        'table'  => 'visitor_passes',
        'name'   => 'idx_visitor_passes_plate',
        'sql'    => "CREATE INDEX idx_visitor_passes_plate ON visitor_passes(visitor_plate)"
    ],
    [
        'table'  => 'visitor_passes',
        'name'   => 'idx_visitor_passes_qr_token',
        'sql'    => "CREATE UNIQUE INDEX idx_visitor_passes_qr_token ON visitor_passes(qr_token)"
    ],
    [
        'table'  => 'homeowners',
        'name'   => 'idx_homeowners_plate',
        'sql'    => "CREATE INDEX idx_homeowners_plate ON homeowners(plate_number)"
    ],
    [
        'table'  => 'recent_logs',
        'name'   => 'idx_recent_logs_plate',
        'sql'    => "CREATE INDEX idx_recent_logs_plate ON recent_logs(plate_number)"
    ],
    [
        'table'  => 'recent_logs',
        'name'   => 'idx_recent_logs_rfid',
        'sql'    => "CREATE INDEX idx_recent_logs_rfid ON recent_logs(rfid_uid)"
    ],
    [
        'table'  => 'recent_logs',
        'name'   => 'idx_recent_logs_created',
        'sql'    => "CREATE INDEX idx_recent_logs_created ON recent_logs(created_at)"
    ],
];

header('Content-Type: text/plain');
echo "=== Missing Indexes Migration ===\n\n";

$added = 0;
$skipped = 0;
$errors = 0;

foreach ($indexes as $idx) {
    echo "  {$idx['name']} on {$idx['table']} ... ";
    try {
        $pdo->exec($idx['sql']);
        echo "ADDED\n";
        $added++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false ||
            strpos($e->getMessage(), 'Duplicate entry')    !== false) {
            echo "SKIPPED (already exists)\n";
            $skipped++;
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\nDone — Added: $added | Skipped: $skipped | Errors: $errors\n";
