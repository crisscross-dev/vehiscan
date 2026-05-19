<?php
// Verbose public DB smoke-test. Writes diagnostic to error log.
error_log('test_db_public_v2 started: ' . date('c'));

function parseDotEnv($path)
{
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $vars[trim($k)] = trim(preg_replace('/^"|"$|^\'|\'$/', '', trim($v)));
    }
    return $vars;
}

$env = getenv('DB_HOST') !== false ? [] : parseDotEnv(__DIR__ . '/.env');
$dbHost = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '127.0.0.1');
$dbPort = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');
$dbName = getenv('DB_NAME') ?: ($env['DB_NAME'] ?? 'vehiscan_vdp');
$dbUser = getenv('DB_USER') ?: ($env['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($env['DB_PASS'] ?? '');
$dbCharset = getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8mb4');

error_log("test_db_public_v2 using DB host={$dbHost} port={$dbPort} db={$dbName} user={$dbUser}");

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    echo "DB OK\n";
    error_log('test_db_public_v2: DB OK');
} catch (Throwable $e) {
    http_response_code(500);
    $msg = 'DB ERROR: ' . $e->getMessage();
    echo htmlspecialchars($msg);
    error_log('test_db_public_v2: ' . $msg);
}

// Remove when done
