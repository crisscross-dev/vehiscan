<?php
// Migration to add qr_code and qr_token columns to visitor_passes table
require_once __DIR__ . '/../db.php';

try {
    // Check if columns exist
    $result = $pdo->query("SHOW COLUMNS FROM visitor_passes LIKE 'qr_code'");
    $qrCodeExists = $result->rowCount() > 0;
    
    $result = $pdo->query("SHOW COLUMNS FROM visitor_passes LIKE 'qr_token'");
    $qrTokenExists = $result->rowCount() > 0;
    
    if (!$qrCodeExists) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN qr_code TEXT NULL AFTER status");
        echo "[OK] Added qr_code column to visitor_passes\n";
    } else {
        echo "[INFO]  qr_code column already exists\n";
    }
    
    if (!$qrTokenExists) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN qr_token VARCHAR(255) NULL UNIQUE AFTER qr_code");
        echo "[OK] Added qr_token column to visitor_passes\n";
    } else {
        echo "[INFO]  qr_token column already exists\n";
    }
    
    echo "\n[OK] Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "[FAIL] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
