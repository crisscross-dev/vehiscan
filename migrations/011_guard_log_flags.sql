-- 011_guard_log_flags.sql
-- Stores guard-generated flags on access log entries for review workflows.

CREATE TABLE IF NOT EXISTS guard_log_flags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  log_id BIGINT UNSIGNED NOT NULL,
  plate_number VARCHAR(32) NOT NULL,
  flagged_by_user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_guard_log_flags_log_id (log_id),
  KEY idx_guard_log_flags_plate_number (plate_number),
  KEY idx_guard_log_flags_flagged_by (flagged_by_user_id),
  KEY idx_guard_log_flags_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
