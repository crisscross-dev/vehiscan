<?php
// DB smoke-test. Upload to project root, open in browser, then remove.
// Created to help diagnose hosted 500 errors related to DB connectivity.
require __DIR__ . '/config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        defined('DB_HOST') ? DB_HOST : getenv('DB_HOST'),
        defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: 3306),
        defined('DB_NAME') ? DB_NAME : getenv('DB_NAME'),
        defined('DB_CHARSET') ? DB_CHARSET : (getenv('DB_CHARSET') ?: 'utf8mb4')
    );

    $user = defined('DB_USER') ? DB_USER : getenv('DB_USER');
    $pass = defined('DB_PASS') ? DB_PASS : getenv('DB_PASS');

    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'DB OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB ERROR: ' . htmlspecialchars($e->getMessage());
}

// Security note: remove this file after testing to avoid exposing DB connection
// behavior or error messages on a public site.
