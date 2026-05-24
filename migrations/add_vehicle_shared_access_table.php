<?php
require_once __DIR__ . '/../db.php';

try {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS vehicle_shared_access (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            vehicle_id INT NOT NULL,\n            homeowner_id INT NOT NULL,\n            is_active TINYINT(1) NOT NULL DEFAULT 1,\n            created_by INT NULL,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            UNIQUE KEY uniq_vehicle_homeowner (vehicle_id, homeowner_id),\n            INDEX idx_vehicle_active (vehicle_id, is_active),\n            INDEX idx_homeowner_active (homeowner_id, is_active),\n            CONSTRAINT fk_vehicle_shared_access_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,\n            CONSTRAINT fk_vehicle_shared_access_homeowner FOREIGN KEY (homeowner_id) REFERENCES homeowners(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n    ");

    echo "[OK] vehicle_shared_access table is ready\n";
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}
