<?php
/**
 * Regression Test: Visitor Pass Contract
 *
 * Run: php tests/regression_visitor_pass_contract.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

class VisitorPassContractRegression
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
        echo "=== Visitor Pass Contract Regression ===\n\n";

        $this->assertContains(
            'admin/api/approve_visitor_pass.php',
            "INNER JOIN homeowners h ON vp.homeowner_id = h.id",
            'approve endpoint requires linked homeowner join'
        );

        $this->assertContains(
            'admin/api/approve_visitor_pass.php',
            "FOR UPDATE",
            'approve endpoint locks pending pass row during transaction'
        );

        $this->assertContains(
            'admin/api/visitor_pass_form.php',
            "if (!\$homeowner_id || !\$visitor_name || !\$visitor_plate || !\$purpose || !\$valid_from)",
            'visitor pass form requires homeowner_id and valid_from (not valid_until) for POST'
        );

        $this->assertContains(
            'admin/api/visitor_pass_form.php',
            "'pending', 'cancelled', 'rejected', 'expired'",
            'visitor pass form restricts editable status options'
        );

        $this->assertContains(
            'admin/api/visitor_pass_form.php',
            "WHERE account_status = 'approved'",
            'visitor pass form homeowner dropdown uses approved homeowners only'
        );

        $this->assertContains(
            'admin/api/get_pending_passes.php',
            "WHERE vp.status = 'pending' AND h.account_status = 'approved'",
            'pending passes endpoint filters to approved homeowners only'
        );

        $this->assertContains(
            'admin/fetch/fetch_visitors.php',
            "WHERE vp.status = 'pending' AND h.account_status = 'approved'",
            'visitors fetch pending list filters to approved homeowners only'
        );

        echo "\n=== Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";

        if ($this->failed > 0) {
            echo "\nREGRESSION DETECTED\n";
            return 1;
        }

        echo "\nAll visitor pass contract checks passed.\n";
        return 0;
    }

    private function assertContains(string $relativePath, string $needle, string $label): void
    {
        $path = $this->root . '/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            $this->fail($label . ' (missing file: ' . $relativePath . ')');
            return;
        }

        $content = (string)file_get_contents($path);
        if (strpos($content, $needle) !== false) {
            $this->pass($label);
            return;
        }

        $this->fail($label);
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo "  PASS: {$message}\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        echo "  FAIL: {$message}\n";
    }
}

$runner = new VisitorPassContractRegression();
exit($runner->run());
