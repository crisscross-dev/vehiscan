<?php
/**
 * Runtime Regression Test: Approvals Role Policy
 *
 * Executes approvals endpoints in isolated PHP subprocesses while simulating
 * admin and super_admin sessions. This validates actual runtime behavior
 * without mutating application data.
 *
 * Run: php tests/regression_approvals_runtime.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

class ApprovalsRuntimeRegressionTest
{
    private int $passed = 0;
    private int $failed = 0;
    private string $root;

    public function __construct()
    {
        $this->root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    }

    public function run(): int
    {
        echo "=== Runtime Approvals Role Policy Tests ===\n\n";

        $this->testAdminDenied();
        $this->testSuperAdminAllowed();

        echo "\n=== Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";

        if ($this->failed > 0) {
            echo "\nREGRESSION DETECTED\n";
            return 1;
        }

        echo "\nAll runtime approvals policy checks passed.\n";
        return 0;
    }

    private function testAdminDenied(): void
    {
        echo "[TEST] Admin role is denied approvals surface\n";

        $checks = [
            ['admin/api/check_pending_approvals.php', 'GET', 'Unauthorized'],
            ['admin/api/get_pending_accounts.php', 'GET', 'Unauthorized'],
            ['admin/api/get_pending_approval_overview.php', 'GET', 'Unauthorized'],
            ['admin/api/approve_user_account.php', 'GET', 'Unauthorized'],
            ['admin/fetch/fetch_approvals.php', 'GET', 'Unauthorized - Super admin access required'],
        ];

        foreach ($checks as [$endpoint, $method, $expected]) {
            $result = $this->runProbe('admin', $method, $endpoint);
            $ok = stripos($result['output'], $expected) !== false;

            if ($ok) {
                $this->pass("$endpoint returns denial marker for admin");
            } else {
                $this->fail("$endpoint missing expected denial marker for admin", $result['output']);
            }
        }

        echo "\n";
    }

    private function testSuperAdminAllowed(): void
    {
        echo "[TEST] Super admin can access approvals surface\n";

        $checks = [
            [
                'endpoint' => 'admin/api/check_pending_approvals.php',
                'method' => 'GET',
                'validator' => function (string $output): bool {
                    return stripos($output, '"success":true') !== false;
                },
                'label' => 'pending approvals count endpoint succeeds for super_admin',
            ],
            [
                'endpoint' => 'admin/api/get_pending_accounts.php',
                'method' => 'GET',
                'validator' => function (string $output): bool {
                    return stripos($output, 'Unauthorized') === false;
                },
                'label' => 'pending accounts endpoint is not blocked for super_admin',
            ],
            [
                'endpoint' => 'admin/api/get_pending_approval_overview.php',
                'method' => 'GET',
                'validator' => function (string $output): bool {
                    return stripos($output, '"success":true') !== false;
                },
                'label' => 'approval overview endpoint succeeds for super_admin',
            ],
            [
                'endpoint' => 'admin/fetch/fetch_approvals.php',
                'method' => 'GET',
                'validator' => function (string $output): bool {
                    return stripos($output, 'Account Approvals') !== false;
                },
                'label' => 'approvals fetch fragment renders for super_admin',
            ],
            [
                'endpoint' => 'admin/api/approve_user_account.php',
                'method' => 'GET',
                'validator' => function (string $output): bool {
                    return stripos($output, 'Method not allowed') !== false;
                },
                'label' => 'approve action endpoint reaches method guard for super_admin',
            ],
        ];

        foreach ($checks as $check) {
            $result = $this->runProbe('super_admin', $check['method'], $check['endpoint']);
            $ok = ($check['validator'])($result['output']);

            if ($ok) {
                $this->pass($check['label']);
            } else {
                $this->fail($check['label'], $result['output']);
            }
        }

        echo "\n";
    }

    private function runProbe(string $role, string $method, string $endpoint): array
    {
        $endpointPath = $this->root . '/' . ltrim($endpoint, '/');
        $endpointPath = str_replace('\\', '/', $endpointPath);

        $probeFile = tempnam(sys_get_temp_dir(), 'vsprobe_');
        if ($probeFile === false) {
            return ['exit_code' => 1, 'output' => 'Failed to create temp probe file'];
        }

        $probeCode = <<<'PHP'
<?php
session_name('vehiscan_admin');
session_start();
$_SESSION['role'] = $argv[1] ?? 'admin';
$_SESSION['username'] = 'runtime-test-' . ($_SESSION['role'] ?? 'admin');
$_SERVER['REQUEST_METHOD'] = strtoupper($argv[2] ?? 'GET');
include $argv[3];
PHP;
        file_put_contents($probeFile, $probeCode);

        $cmd = escapeshellarg(PHP_BINARY)
            . ' -d display_errors=0 '
            . escapeshellarg($probeFile)
            . ' ' . escapeshellarg($role)
            . ' ' . escapeshellarg(strtoupper($method))
            . ' ' . escapeshellarg($endpointPath);

        $lines = [];
        $exitCode = 0;
        exec($cmd, $lines, $exitCode);
        @unlink($probeFile);

        return [
            'exit_code' => $exitCode,
            'output' => trim(implode("\n", $lines)),
        ];
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo "  PASS: {$message}\n";
    }

    private function fail(string $message, string $output): void
    {
        $this->failed++;
        echo "  FAIL: {$message}\n";
        if ($output !== '') {
            $snippet = substr($output, 0, 240);
            echo "        Output: {$snippet}\n";
        }
    }
}

$runner = new ApprovalsRuntimeRegressionTest();
exit($runner->run());
