<?php
require_once __DIR__ . '/../config.php';

$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 5,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "CONNECTED\n";
} catch (PDOException $e) {
    // Print only the exception message for diagnosis (do not log credentials)
    echo "ERROR:" . $e->getMessage() . "\n";
    exit(1);
}
