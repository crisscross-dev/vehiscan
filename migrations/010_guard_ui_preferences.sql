-- 010_guard_ui_preferences.sql
-- Stores guard-specific UI preferences for dashboard personalization.

CREATE TABLE IF NOT EXISTS guard_ui_preferences (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  dashboard_title VARCHAR(80) NOT NULL DEFAULT 'Access Logs',
  display_name VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guard_ui_preferences_user (user_id),
  KEY idx_guard_ui_preferences_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
