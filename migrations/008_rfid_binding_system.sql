-- Migration 008: RFID Binding System Tables
-- Creates/ensures tables for RFID tag binding and scan logging
-- These tables may already exist in the database from manual creation

-- RFID API Keys - Authentication for hardware readers
CREATE TABLE IF NOT EXISTS rfid_api_keys (
    id INT(11) NOT NULL AUTO_INCREMENT,
    api_key VARCHAR(64) NOT NULL,
    reader_id VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_api_key (api_key),
    KEY idx_reader_id (reader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID Binding Sessions - Track RFID-to-vehicle binding workflow
CREATE TABLE IF NOT EXISTS rfid_binding_sessions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    session_token VARCHAR(64) NOT NULL,
    target_type ENUM('vehicle', 'homeowner') NOT NULL DEFAULT 'vehicle',
    target_id INT(11) NOT NULL,
    initiated_by INT(11) NOT NULL,
    initiated_by_role ENUM('admin', 'super_admin', 'guard') NOT NULL,
    status ENUM('pending', 'completed', 'timeout', 'cancelled') NOT NULL DEFAULT 'pending',
    scanned_uid VARCHAR(32) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_session_token (session_token),
    KEY idx_status (status),
    KEY idx_target (target_type, target_id),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID Scan Log - Every scan from any source
CREATE TABLE IF NOT EXISTS rfid_scan_log (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rfid_uid VARCHAR(32) NOT NULL,
    reader_id VARCHAR(50) DEFAULT NULL,
    api_key_id INT(11) DEFAULT NULL,
    scan_result ENUM('access_granted', 'access_denied', 'uid_bound', 'unknown_uid', 'binding_failed', 'duplicate_scan', 'error') NOT NULL,
    input_source ENUM('simulator', 'usb_hid', 'api_key', 'unknown') NOT NULL DEFAULT 'unknown',
    vehicle_id INT(11) DEFAULT NULL,
    binding_session_id INT(11) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rfid_uid (rfid_uid),
    KEY idx_scan_result (scan_result),
    KEY idx_scanned_at (scanned_at),
    KEY idx_vehicle_id (vehicle_id),
    KEY idx_binding_session (binding_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID Simulator - Quick simulation log
CREATE TABLE IF NOT EXISTS rfid_simulator (
    id INT(11) NOT NULL AUTO_INCREMENT,
    plate_number VARCHAR(20) DEFAULT NULL,
    rfid_uid VARCHAR(32) DEFAULT NULL,
    simulated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add RFID columns to vehicles table (if not already present)
-- These ALTER statements use a procedure to check column existence first

DELIMITER //

DROP PROCEDURE IF EXISTS add_rfid_columns_to_vehicles//

CREATE PROCEDURE add_rfid_columns_to_vehicles()
BEGIN
    -- Add rfid_uid column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'rfid_uid'
    ) THEN
        ALTER TABLE vehicles ADD COLUMN rfid_uid VARCHAR(32) DEFAULT NULL UNIQUE AFTER plate_number;
    END IF;

    -- Add rfid_bound_at column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'rfid_bound_at'
    ) THEN
        ALTER TABLE vehicles ADD COLUMN rfid_bound_at DATETIME DEFAULT NULL AFTER rfid_uid;
    END IF;

    -- Add rfid_bound_by column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'rfid_bound_by'
    ) THEN
        ALTER TABLE vehicles ADD COLUMN rfid_bound_by INT(11) DEFAULT NULL AFTER rfid_bound_at;
    END IF;

    -- Add rfid_uid column to recent_logs
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recent_logs' AND COLUMN_NAME = 'rfid_uid'
    ) THEN
        ALTER TABLE recent_logs ADD COLUMN rfid_uid VARCHAR(32) DEFAULT NULL AFTER plate_number;
    END IF;
END//

DELIMITER ;

CALL add_rfid_columns_to_vehicles();
DROP PROCEDURE IF EXISTS add_rfid_columns_to_vehicles;
