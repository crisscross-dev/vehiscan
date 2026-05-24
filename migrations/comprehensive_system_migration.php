<?php
/**
 * COMPREHENSIVE SYSTEM MIGRATION
 * Run this file to apply all database changes
 */

require_once __DIR__ . '/../db.php';

echo "Starting system-wide database migrations...\n\n";

try {
    // 1. USERS TABLE
    echo "1. Updating users table...\n";
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('email', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE AFTER username");
        echo "  [OK] Added email\n";
    }
    if (!in_array('account_status', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN account_status ENUM('pending','approved','rejected') DEFAULT 'approved' AFTER password");
        echo "  [OK] Added account_status\n";
    }
    if (!in_array('email_verified', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER email");
        echo "  [OK] Added email_verified\n";
    }
    if (!in_array('verification_token', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL AFTER email_verified");
        echo "  [OK] Added verification_token\n";
    }
    if (!in_array('approved_by', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approved_by INT NULL AFTER account_status");
        echo "  [OK] Added approved_by\n";
    }
    if (!in_array('approved_at', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approved_at DATETIME NULL AFTER approved_by");
        echo "  [OK] Added approved_at\n";
    }
    if (!in_array('rejection_reason', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN rejection_reason TEXT NULL AFTER approved_at");
        echo "  [OK] Added rejection_reason\n";
    }
    
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('first_name', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) AFTER rejection_reason");
        $pdo->exec("ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) AFTER first_name");
        $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) AFTER middle_name");
        $pdo->exec("ALTER TABLE users ADD COLUMN suffix VARCHAR(20) AFTER last_name");
        echo "  [OK] Added name fields\n";
    }
    echo "[OK] Users table updated\n\n";
    
    // 2. HOMEOWNERS TABLE
    echo "2. Updating homeowners table...\n";
    $homeCols = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('username', $homeCols)) {
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN username VARCHAR(50) AFTER id");
        $pdo->exec("UPDATE homeowners SET username = LOWER(CONCAT(REPLACE(name, ' ', ''), id))");
        echo "  [OK] Added username\n";
    }
    if (!in_array('email', $homeCols)) {
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN email VARCHAR(255) AFTER username");
        echo "  [OK] Added email\n";
    }
    if (in_array('contact', $homeCols) && !in_array('contact_number', $homeCols)) {
        $pdo->exec("ALTER TABLE homeowners CHANGE contact contact_number VARCHAR(20)");
        echo "  [OK] Renamed contact to contact_number\n";
    }
    if (!in_array('subdivision', $homeCols)) {
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN subdivision VARCHAR(100) DEFAULT 'Ville de Palme' AFTER address");
        echo "  [OK] Added subdivision\n";
    }
    
    $homeCols = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('first_name', $homeCols)) {
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN first_name VARCHAR(100) AFTER subdivision");
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN middle_name VARCHAR(100) AFTER first_name");
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN last_name VARCHAR(100) AFTER middle_name");
        $pdo->exec("ALTER TABLE homeowners ADD COLUMN suffix VARCHAR(20) AFTER last_name");
        $pdo->exec("UPDATE homeowners SET first_name = SUBSTRING_INDEX(name, ' ', 1), last_name = SUBSTRING_INDEX(name, ' ', -1) WHERE name IS NOT NULL");
        echo "  [OK] Added name fields\n";
    }
    echo "[OK] Homeowners table updated\n\n";
    
    // 3. VEHICLES TABLE
    echo "3. Creating vehicles table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        homeowner_id INT NOT NULL,
        plate_number VARCHAR(20) NOT NULL UNIQUE,
        vehicle_type VARCHAR(50) NOT NULL,
        color VARCHAR(30) NOT NULL,
        brand VARCHAR(50),
        model VARCHAR(50),
        year INT,
        is_primary BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (homeowner_id) REFERENCES homeowners(id) ON DELETE CASCADE,
        INDEX idx_homeowner (homeowner_id),
        INDEX idx_plate (plate_number)
    )");
    $pdo->exec("INSERT IGNORE INTO vehicles (homeowner_id, plate_number, vehicle_type, color, is_primary)
        SELECT id, plate_number, vehicle_type, color, TRUE FROM homeowners WHERE plate_number IS NOT NULL AND plate_number != ''");
    echo "[OK] Vehicles table created\n\n";
    
    // 4. VISITOR_PASSES TABLE
    echo "4. Updating visitor_passes table...\n";
    $vpCols = $pdo->query("SHOW COLUMNS FROM visitor_passes")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('subdivision', $vpCols)) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN subdivision VARCHAR(100) DEFAULT 'Ville de Palme' AFTER homeowner_id");
        echo "  [OK] Added subdivision\n";
    }
    if (!in_array('logo_path', $vpCols)) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN logo_path VARCHAR(255) DEFAULT '/ville_de_palme.png' AFTER subdivision");
        echo "  [OK] Added logo_path\n";
    }
    if (!in_array('is_active', $vpCols)) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER status");
        $pdo->exec("UPDATE visitor_passes SET is_active = (status IN ('approved', 'active'))");
        echo "  [OK] Added is_active\n";
    }
    if (!in_array('contact_number', $vpCols)) {
        $pdo->exec("ALTER TABLE visitor_passes ADD COLUMN contact_number VARCHAR(20) AFTER visitor_name");
        echo "  [OK] Added contact_number\n";
    }
    echo "[OK] Visitor passes table updated\n\n";
    
    // 5. NEW TABLES
    echo "5. Creating new support tables...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('user','homeowner') NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL,
        INDEX idx_token (token)
    )");
    echo "  [OK] email_verification_tokens\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_approval_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('user','homeowner') NOT NULL,
        action ENUM('approved','rejected','reactivated') NOT NULL,
        approved_by INT NOT NULL,
        reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id, user_type)
    )");
    echo "  [OK] account_approval_log\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS subdivision_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subdivision_name VARCHAR(100) NOT NULL UNIQUE,
        logo_path VARCHAR(255) DEFAULT '/ville_de_palme.png',
        primary_color VARCHAR(7) DEFAULT '#1a56db',
        secondary_color VARCHAR(7) DEFAULT '#e8f4f8',
        contact_email VARCHAR(255),
        contact_phone VARCHAR(20),
        is_active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("INSERT IGNORE INTO subdivision_settings (subdivision_name) VALUES ('Ville de Palme')");
    echo "  [OK] subdivision_settings\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        homeowner_id INT NULL,
        plate_number VARCHAR(20) NOT NULL,
        name VARCHAR(255),
        vehicle_type VARCHAR(100),
        color VARCHAR(50),
        status ENUM('IN','OUT') NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        visitor_pass_id INT NULL,
        INDEX idx_plate (plate_number),
        INDEX idx_timestamp (timestamp)
    )");
    echo "  [OK] access_logs\n";
    
    echo "[OK] All support tables created\n\n";
    
    echo "══════════════════════════════════════════\n";
    echo "[OK] ALL MIGRATIONS COMPLETED!\n";
    echo "══════════════════════════════════════════\n";
    
} catch (PDOException $e) {
    echo "[FAIL] Error: " . $e->getMessage() . "\n";
}
