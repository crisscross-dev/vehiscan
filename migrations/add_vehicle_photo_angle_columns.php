<?php
/**
 * Adds optional vehicle photo angle columns to homeowners table.
 * Run once via: php migrations/add_vehicle_photo_angle_columns.php
 */

require_once __DIR__ . '/../db.php';

$columnsToAdd = [
    'car_img_front' => "ALTER TABLE homeowners ADD COLUMN car_img_front VARCHAR(255) NULL AFTER car_img",
    'car_img_left' => "ALTER TABLE homeowners ADD COLUMN car_img_left VARCHAR(255) NULL AFTER car_img_front",
    'car_img_right' => "ALTER TABLE homeowners ADD COLUMN car_img_right VARCHAR(255) NULL AFTER car_img_left",
    'car_img_rear' => "ALTER TABLE homeowners ADD COLUMN car_img_rear VARCHAR(255) NULL AFTER car_img_right",
];

try {
    $existing = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columnsToAdd as $col => $sql) {
        if (in_array($col, $existing, true)) {
            echo "[SKIP] $col already exists\n";
            continue;
        }
        $pdo->exec($sql);
        echo "[OK] Added $col\n";
    }

    echo "Done.\n";
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
}
