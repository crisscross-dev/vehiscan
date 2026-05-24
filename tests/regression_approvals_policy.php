<?php
/**
 * Regression Test: Approvals Endpoint Role Gates
 * 
 * Validates that all approvals-related endpoints enforce super-admin-only access.
 * Use this script to prevent accidental role-policy regressions during future updates.
 * 
 * Run from CLI: php tests/regression_approvals_policy.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple test runner without external dependencies
class ApprovalsRegressionTest {
    private $results = [];
    private $passed = 0;
    private $failed = 0;

    public function run() {
        echo "=== Approvals Endpoint Role-Gate Regression Tests ===\n\n";
        
        $this->testEndpointRoleCheck();
        $this->testFetchComponentRoleCheck();
        $this->testPollingGuard();
        
        $this->printSummary();
        exit($this->failed > 0 ? 1 : 0);
    }

    private function testEndpointRoleCheck() {
        echo "[TEST] Endpoint Authorization Checks\n";
        
        $endpoints = [
            'admin/api/check_pending_approvals.php',
            'admin/api/get_pending_accounts.php',
            'admin/api/get_pending_approval_overview.php',
            'admin/api/approve_user_account.php',
        ];
        
        foreach ($endpoints as $file) {
            $path = __DIR__ . '/../' . $file;
            if (!file_exists($path)) {
                $this->fail("File not found: $file");
                continue;
            }
            
            $content = file_get_contents($path);
            $hasRoleCheck = strpos($content, "['role'] !== 'super_admin'") !== false;
            
            if ($hasRoleCheck) {
                $this->pass("$file enforces super_admin role check");
            } else {
                $this->fail("$file missing or incorrect role check");
            }
        }
        
        echo "\n";
    }

    private function testFetchComponentRoleCheck() {
        echo "[TEST] Fetch Component Authorization Check\n";
        
        $path = __DIR__ . '/../admin/fetch/fetch_approvals.php';
        if (!file_exists($path)) {
            $this->fail("fetch_approvals.php not found");
            return;
        }
        
        $content = file_get_contents($path);
        $hasRoleCheck = strpos($content, "['role'] !== 'super_admin'") !== false;
        
        if ($hasRoleCheck) {
            $this->pass("fetch_approvals.php enforces super_admin role check");
        } else {
            $this->fail("fetch_approvals.php missing or incorrect role check");
        }
        
        echo "\n";
    }

    private function testPollingGuard() {
        echo "[TEST] Polling Guard in Realtime Updates\n";
        
        $path = __DIR__ . '/../assets/js/admin/realtime-updates.js';
        if (!file_exists($path)) {
            $this->fail("realtime-updates.js not found");
            return;
        }
        
        $content = file_get_contents($path);
        // Check that polling checks for badge presence
        $hasGuard = strpos($content, "getElementById('pendingApprovalsBadge')") !== false;
        
        if ($hasGuard) {
            $this->pass("realtime-updates.js checks for approvals badge before polling");
        } else {
            $this->fail("realtime-updates.js missing approval badge guard");
        }
        
        echo "\n";
    }

    private function pass($message) {
        $this->passed++;
        echo "  ✓ PASS: $message\n";
        $this->results[] = ['status' => 'PASS', 'message' => $message];
    }

    private function fail($message) {
        $this->failed++;
        echo "  ✗ FAIL: $message\n";
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
    }

    private function printSummary() {
        echo "=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed > 0) {
            echo "\n⚠️  REGRESSION DETECTED: Role-gate policy may have changed.\n";
        } else {
            echo "\n✓ All approvals policy checks passed.\n";
        }
    }
}

// Run the test
$test = new ApprovalsRegressionTest();
$test->run();
