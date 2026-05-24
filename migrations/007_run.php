<?php
require_once __DIR__ . '/../db.php';

$sql = file_get_contents(__DIR__ . '/007_create_password_reset_tokens.sql');

try {
    $pdo->exec($sql);
    echo "Migration 007 applied successfully.\n";

    // Verify table
    $cols = $pdo->query("DESCRIBE password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
    echo "Table columns: " . implode(', ', $cols) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
