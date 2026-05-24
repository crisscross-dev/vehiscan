<?php
/**
 * Direct Database Query to verify pending accounts
 */

require_once __DIR__ . '/../db.php';

echo "=== DIRECT DATABASE TEST ===\n\n";

// Show pending homeowners
echo "[1] Pending Homeowners:\n";
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, account_status, created_at FROM homeowners WHERE account_status = 'pending' ORDER BY created_at DESC");
$homeowners = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($homeowners) . "\n";
foreach ($homeowners as $row) {
    echo "  - ID {$row['id']}: {$row['name']} ({$row['email']}) - {$row['created_at']}\n";
}

// Show pending users
echo"\n[2] Pending Users:\n";
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, account_status, created_at FROM users WHERE account_status = 'pending' ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($users) . "\n";
foreach ($users as $row) {
    echo "  - ID {$row['id']}: {$row['name']} ({$row['email']}) - {$row['created_at']}\n";
}

// Show test data from previous session
echo "\n[3] Combined Total:\n";
$totalPending = count($homeowners) + count($users);
echo "Total pending accounts: $totalPending\n";

if ($totalPending > 0) {
    echo "\n✓ DATABASE HAS PENDING ACCOUNTS\n";
    echo "✓ They should appear in the approval list\n";
} else {
    echo "\n✗ NO PENDING ACCOUNTS IN DATABASE\n";
}

// Show API would return
echo "\n[4] What API Should Return:\n";
$combined = array_merge($homeowners, $users);
usort($combined, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo "JSON Output (formatted):\n";
echo json_encode([
    'success' => true,
    'accounts' => array_map(function($h) { 
        return [
            'id' => $h['id'],
            'name' => $h['name'],
            'email' => $h['email'],
            'account_status' => $h['account_status'],
            'created_at' => $h['created_at'],
            'role' => strpos($h['name'], '@') === false ? 'homeowner' : 'user',
            'account_type' => strpos($h['name'], '@') === false ? 'homeowner' : 'user'
        ];
    }, $combined),
    'count' => $totalPending
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

echo "\n=== TEST COMPLETE ===\n";
?>
