-- Migration: Create User and Visitor Portal Tables
-- Date: 2025-12-02
-- Description: Add tables and authentication for homeowner and visitor portals

-- Table for homeowner portal authentication
CREATE TABLE IF NOT EXISTS homeowner_auth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    homeowner_id INT NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (homeowner_id) REFERENCES homeowners(id) ON DELETE CASCADE,
    INDEX idx_username (username),
    INDEX idx_homeowner_id (homeowner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for visitor portal authentication (temporary tokens)
CREATE TABLE IF NOT EXISTS visitor_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_pass_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL,
    FOREIGN KEY (visitor_pass_id) REFERENCES visitor_passes(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure visitor_passes table has all needed columns
ALTER TABLE visitor_passes 
ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected', 'expired', 'used') DEFAULT 'pending' AFTER valid_until,
ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER status,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approved_at,
ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER rejection_reason;

-- Add foreign key for approved_by if it doesn't exist
SET @fk_exists = (SELECT COUNT(*) 
                  FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE TABLE_SCHEMA = 'vehiscan_vdp' 
                  AND TABLE_NAME = 'visitor_passes' 
                  AND CONSTRAINT_NAME = 'fk_visitor_passes_approved_by');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE visitor_passes ADD CONSTRAINT fk_visitor_passes_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
