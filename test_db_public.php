<?php
// Public DB smoke-test. Does NOT include config.php or any auth logic.
// Use this when protected endpoints redirect to login.

function parseDotEnv($path)
{
    $vars = [];
    if (!file_exists($path)) {
        return $vars;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // Remove surrounding quotes
        $v = preg_replace('/^\"|\"$|^\'|\'$/', '', $v);
        $vars[$k] = $v;
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

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'DB OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB ERROR: ' . htmlspecialchars($e->getMessage());
}

// Remove this file after testing.
