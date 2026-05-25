<?php
/**
 * Combined hardening checks runner.
 *
 * Run: php tests/run_hardening_checks.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$php = PHP_BINARY;
$baseDir = __DIR__;

$suites = [
    'Approvals regressions' => $baseDir . '/run_approvals_regressions.php',
    'Visitor pass contract regression' => $baseDir . '/regression_visitor_pass_contract.php',
    'Visitor pass runtime regression' => $baseDir . '/regression_visitor_pass_runtime.php',
    'DB integrity audit' => $baseDir . '/db_integrity_audit.php',
];

$runtimeDbSuites = [
    'Visitor pass runtime regression',
    'DB integrity audit',
];

/**
 * Detect whether DB is reachable in this environment.
 * If unavailable, DB-dependent runtime suites are skipped to keep static policy
 * checks actionable during local/CI runs without database access.
 */
function vehiscanDbAvailable(): bool
{
    try {
        require_once dirname(__DIR__) . '/config.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);

        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$dbAvailable = vehiscanDbAvailable();

$failed = 0;

echo "=== Hardening Checks Runner ===\n\n";

foreach ($suites as $label => $path) {
    if (!$dbAvailable && in_array($label, $runtimeDbSuites, true)) {
        echo "[SKIP] {$label} (database unavailable in current environment)\n\n";
        continue;
    }

    if (!is_file($path)) {
        echo "[FAIL] {$label}: missing file {$path}\n\n";
        $failed++;
        continue;
    }

    if (!$dbAvailable && $label === 'Approvals regressions') {
        putenv('VEHISCAN_SKIP_DB_RUNTIME=1');
    } else {
        putenv('VEHISCAN_SKIP_DB_RUNTIME');
    }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path);
    echo "[RUN ] {$label}\n";

    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    if (!empty($output)) {
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }

    if ($code !== 0) {
        echo "[FAIL] {$label} exited with code {$code}\n\n";
        $failed++;
    } else {
        echo "[PASS] {$label}\n\n";
    }
}

if ($failed > 0) {
    echo "Completed with {$failed} failing suite(s).\n";
    exit(1);
}

echo "All hardening checks passed.\n";
exit(0);
