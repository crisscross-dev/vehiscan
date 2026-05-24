<?php
/**
 * DB Integrity Audit
 *
 * Run: php tests/db_integrity_audit.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';

$checks = [
    [
        'name' => 'Approved homeowners must have active homeowner_auth',
        'sql' => "
            SELECT COUNT(*) AS c
            FROM homeowners h
            LEFT JOIN homeowner_auth ha ON ha.homeowner_id = h.id
            WHERE h.account_status = 'approved'
              AND (ha.id IS NULL OR ha.is_active <> 1)
        ",
        'max' => 0,
    ],
    [
        'name' => 'Rejected/pending homeowners must not have active homeowner_auth',
        'sql' => "
            SELECT COUNT(*) AS c
            FROM homeowners h
            LEFT JOIN homeowner_auth ha ON ha.homeowner_id = h.id
            WHERE h.account_status IN ('pending', 'rejected')
              AND ha.is_active = 1
        ",
        'max' => 0,
    ],
    [
        'name' => 'Pending visitor passes must have a linked homeowner',
        'sql' => "
            SELECT COUNT(*) AS c
            FROM visitor_passes vp
            WHERE vp.status = 'pending'
              AND (vp.homeowner_id IS NULL OR vp.homeowner_id = 0)
        ",
        'max' => 0,
    ],
    [
        'name' => 'Active visitor passes must be linked to approved homeowners',
        'sql' => "
            SELECT COUNT(*) AS c
            FROM visitor_passes vp
            LEFT JOIN homeowners h ON h.id = vp.homeowner_id
            WHERE vp.status = 'active'
              AND (h.id IS NULL OR h.account_status <> 'approved')
        ",
        'max' => 0,
    ],
    [
        'name' => 'visitor_passes referencing missing homeowners',
        'sql' => "
            SELECT COUNT(*) AS c
            FROM visitor_passes vp
            LEFT JOIN homeowners h ON h.id = vp.homeowner_id
            WHERE vp.homeowner_id IS NOT NULL
              AND h.id IS NULL
        ",
        'max' => 0,
    ],
];

$failed = 0;

echo "=== DB Integrity Audit ===\n\n";

foreach ($checks as $index => $check) {
    $stmt = $pdo->query($check['sql']);
    $count = (int)$stmt->fetchColumn();

    $pass = $count <= $check['max'];
    $status = $pass ? 'PASS' : 'FAIL';

    echo sprintf("[%s] %d. %s => %d\n", $status, $index + 1, $check['name'], $count);

    if (!$pass) {
        $failed++;
    }
}

echo "\n";
if ($failed > 0) {
    echo "Integrity audit failed checks: {$failed}\n";
    exit(1);
}

echo "Integrity audit passed.\n";
exit(0);
