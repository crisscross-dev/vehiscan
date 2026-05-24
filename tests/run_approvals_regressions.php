<?php
/**
 * Convenience runner for approvals regression suites.
 *
 * Run: php tests/run_approvals_regressions.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$php = PHP_BINARY;
$baseDir = __DIR__;

$suites = [
    'Policy checks' => $baseDir . '/regression_approvals_policy.php',
    'Runtime checks' => $baseDir . '/regression_approvals_runtime.php',
];

$failed = 0;

echo "=== Approvals Regression Runner ===\n\n";

foreach ($suites as $label => $path) {
    if (!is_file($path)) {
        echo "[FAIL] {$label}: missing file {$path}\n\n";
        $failed++;
        continue;
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

echo "All approvals regression suites passed.\n";
exit(0);
