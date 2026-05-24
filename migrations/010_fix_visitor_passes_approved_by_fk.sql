-- Migration: Repair visitor_passes.approved_by foreign key target
-- Date: 2026-04-16
-- Description: Ensure approved_by references users(id) instead of legacy super_admin(id)

SET @schema_name = DATABASE();

SET @fk_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'visitor_passes'
      AND COLUMN_NAME = 'approved_by'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @ref_table = (
    SELECT REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'visitor_passes'
      AND COLUMN_NAME = 'approved_by'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @drop_sql = IF(
    @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE visitor_passes DROP FOREIGN KEY ', @fk_name),
    'SELECT "No approved_by FK to drop" AS message'
);
PREPARE stmt_drop FROM @drop_sql;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @add_sql = IF(
    @ref_table <> 'users' OR @ref_table IS NULL,
    'ALTER TABLE visitor_passes ADD CONSTRAINT fk_visitor_passes_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "approved_by FK already points to users" AS message'
);
PREPARE stmt_add FROM @add_sql;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;
