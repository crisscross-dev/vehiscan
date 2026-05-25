<?php
/**
 * Database Connection with Environment Configuration Support
 * 
 * This file establishes a secure PDO connection to the database.
 * Configuration can be loaded from .env file or uses defaults.
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Database connection parameters
$host = DB_HOST;
$port = DB_PORT;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = DB_CHARSET;

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use native prepared statements for better security
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Set timeout for connection
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    define('DB_AVAILABLE', true);
} catch (PDOException $e) {
    // Log error securely without exposing details
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
    define('DB_AVAILABLE', false);

    // For CLI show a brief stderr message and return
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Database connection failed: " . $e->getMessage() . PHP_EOL);
        return;
    }

    // Determine request type (API/AJAX vs regular page)
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isApi = str_contains($uri, '/api/') || str_contains($uri, '/admin/api/');
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isApi || stripos($accept, 'application/json') !== false || $isAjax) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'service_unavailable', 'message' => 'Database unavailable. Try again later.']);
        exit;
    }

    // Serve a maintenance HTML page if present to avoid exposing internals
    $maintenanceFile = __DIR__ . '/public_html/maintenance.html';
    if (file_exists($maintenanceFile)) {
        http_response_code(503);
        readfile($maintenanceFile);
        exit;
    }

    // Fallback minimal maintenance response
    http_response_code(503);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1>Site temporarily unavailable</h1><p>We are performing maintenance. Please try again later.</p></body></html>';
    exit;
}
?>
