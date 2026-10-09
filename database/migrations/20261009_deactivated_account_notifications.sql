-- Track each administrator-controlled deactivation without inventing dates for
-- accounts that were already inactive before this migration.
ALTER TABLE users
 ADD COLUMN IF NOT EXISTS deactivated_at BIGINT UNSIGNED DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS deactivated_by VARCHAR(64) DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS deactivation_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
 ADD INDEX IF NOT EXISTS idx_users_deactivation_review (active,deactivated_at);

SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deactivated_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `users` ADD CONSTRAINT `mp_users_deactivated_by_fk` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
