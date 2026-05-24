<?php
/**
 * Approvals Page Diagnostics
 * Shows what happens when approvals fetch component is loaded
 */

// Check 1: Session status
session_start();

echo "=== APPROVALS PAGE DIAGNOSTICS ===\n\n";

echo "[CHECK 1] Session Information:\n";
echo "  Session ID: " . session_id() . "\n";
echo "  Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "  User Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "  Super Admin ID: " . ($_SESSION['super_admin_id'] ?? 'NOT SET') . "\n";
echo "  Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";

// Check 2: Authorization
echo "\n[CHECK 2] Authorization Check:\n";
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    echo "  ✓ User HAS super_admin role\n";
} else {
    echo "  ✗ User MISSING super_admin role\n";
    echo "    - Approvals API will REJECT with 403 Unauthorized\n";
}

// Check 3: Approvals endpoint security
echo "\n[CHECK 3] Approvals API Security:\n";
require_once __DIR__ . '/../includes/session_admin_unified.php';

// Check if session_admin_unified allows this user
echo "  session_admin_unified included\n";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    echo "  ✗ NOT authorized - API will return 403\n";
} else {
    echo "  ✓ Authorized - API will return pending accounts\n";
}

// Check 4: Database
echo "\n[CHECK 4] Pending Accounts in Database:\n";
require_once __DIR__ . '/../db.php';

$pending = $pdo->query("SELECT COUNT(*) FROM homeowners WHERE account_status = 'pending'")->fetchColumn();
echo "  Pending homeowners: $pending\n";

if ($pending > 0) {
    echo "  ✓ Database has pending account(s)\n";
} else {
    echo "  ✗ Database has NO pending accounts\n";
}

echo "\n=== DIAGNOSIS ===\n";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    echo "ISSUE: User is not logged in as super_admin\n";
    echo "ACTION: Ensure admin is logged in with super_admin role\n";
} else if ($pending === 0) {
    echo "ISSUE: No pending accounts in database\n";
    echo "ACTION: Create test accounts or register new homeowners/users\n";
} else {
    echo "OK: Everything looks correct!\n";
    echo "  - User has super_admin role\n";
    echo "  - Database has pending accounts\n";
    echo "  - API should return data\n";
    echo "\nPossible remaining issues:\n";
    echo "  1. CSRF token not being sent in fetch\n";
    echo "  2. Browser console showing JavaScript errors\n";
    echo "  3. Network request blocked or failing\n";
    echo "  4. API endpoint permissions issue\n";
}

echo "\n";
?>
