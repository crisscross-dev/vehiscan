<?php
// Direct connection attempt using values from .env.hosting
$host = 'auth-db2045.hstgr.io';
$port = '3306';
$db   = 'u321426185_Vehiscan_DB';
$user = 'u321426185_Vehiscan_DB';
$pass = 'Vehiscan_password123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 5,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "CONNECTED_HOSTING\n";
} catch (PDOException $e) {
    echo "ERROR_HOSTING:" . $e->getMessage() . "\n";
    exit(1);
}
