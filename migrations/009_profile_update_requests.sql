-- Migration 009: Create profile_update_requests table
-- Allows homeowners to submit requests for admin review when they need
-- profile information corrected (e.g. registration mistakes).

CREATE TABLE IF NOT EXISTS `profile_update_requests` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `homeowner_id`  INT NOT NULL,
    `request_text`  TEXT NOT NULL,
    `status`        ENUM('pending','acknowledged','completed','rejected') NOT NULL DEFAULT 'pending',
    `admin_notes`   TEXT NULL DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_homeowner_id` (`homeowner_id`),
    KEY `idx_status`       (`status`),
    KEY `idx_created_at`   (`created_at`),
    CONSTRAINT `fk_pur_homeowner`
        FOREIGN KEY (`homeowner_id`) REFERENCES `homeowners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
