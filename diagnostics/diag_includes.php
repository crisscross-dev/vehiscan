<?php
// Includes & PDO diagnostics — remove after use.
$token = 'vehiscan-temp-diagnostics';
if (!isset($_GET['key']) || $_GET['key'] !== $token) {
    http_response_code(403);
    echo "Forbidden. Provide ?key={$token}\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
$base = __DIR__ . '/../';
$files = [
    'includes/compat.php',
    'includes/session_helpers.php',
    'db.php',
    'includes/rate_limiter.php'
];
foreach ($files as $f) {
    $p = realpath($base . $f) ?: ($base . $f);
    echo $f . ': ' . (file_exists($base . $f) ? 'exists' : 'missing') . ', readable=' . (is_readable($base . $f) ? 'yes' : 'no') . "\n";
}

echo "\nPDO / Extensions:\n";
echo "pdo extension loaded: " . (extension_loaded('pdo') ? 'yes' : 'no') . "\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
if (extension_loaded('pdo')) {
    try {
        $drivers = PDO::getAvailableDrivers();
        echo "PDO drivers: " . implode(', ', $drivers) . "\n";
    } catch (Throwable $e) {
        echo "Error listing PDO drivers: " . $e->getMessage() . "\n";
    }
}

// Small DB connection test if db.php exists
if (file_exists($base . 'db.php')) {
    echo "\nAttempting DB connection using db.php...\n";
    try {
        // include in isolated scope
        require $base . 'db.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->query("SELECT 1");
            $ok = $stmt->fetchColumn();
            echo "DB simple query result: " . ($ok ? $ok : 'no') . "\n";
        } else {
            echo "PDO not created by db.php\n";
        }
    } catch (Throwable $e) {
        echo "DB connection error: " . $e->getMessage() . "\n";
    }
}

?>