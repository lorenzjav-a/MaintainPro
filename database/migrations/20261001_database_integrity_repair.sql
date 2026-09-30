-- Reconcile imported or interrupted schemas after the September 29 upgrade.
-- The runner checks types, orphan data and JSON before executing these idempotent DDL statements.
ALTER TABLE complaints
 ADD COLUMN IF NOT EXISTS assigned_user_id VARCHAR(64) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.assignedUserId')),'null')) STORED,
 ADD COLUMN IF NOT EXISTS concern_type VARCHAR(100) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.concernType')),'null')) STORED,
 ADD COLUMN IF NOT EXISTS due_at BIGINT GENERATED ALWAYS AS (CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.dueAt')),'null') AS UNSIGNED)) STORED,
 ADD COLUMN IF NOT EXISTS category_name VARCHAR(100) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.category')),'null')) STORED,
 ADD COLUMN IF NOT EXISTS purok_key VARCHAR(140) GENERATED ALWAYS AS (
   CASE WHEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purokId')),'null') IS NOT NULL
   THEN CONCAT('id:',JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purokId')))
   ELSE CONCAT('name:',LOWER(TRIM(REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purok')),'[[:space:]]+',' ')))) END) STORED,
 ADD COLUMN IF NOT EXISTS street_normalized VARCHAR(140) GENERATED ALWAYS AS (
   TRIM(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(
   LOWER(REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.street')),'null'),''),'.','')),
   '[[:space:]]+',' '),'(^| )st( |$)',' street '),'(^| )rd( |$)',' road '),'(^| )ave( |$)',' avenue '),'[[:space:]]+',' '))) STORED,
 ADD COLUMN IF NOT EXISTS recurrence_key CHAR(64) GENERATED ALWAYS AS (
   CASE WHEN JSON_EXTRACT(payload,'$.locationDetails.purok') IS NOT NULL AND JSON_EXTRACT(payload,'$.concernType') IS NOT NULL
   THEN SHA2(LOWER(CONCAT_WS('|',JSON_UNQUOTE(JSON_EXTRACT(payload,'$.category')),JSON_UNQUOTE(JSON_EXTRACT(payload,'$.concernType')),
     JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purok')),JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.street')))),256)
   ELSE NULL END) STORED,
 ADD INDEX IF NOT EXISTS idx_assignment_status (assigned_user_id,status),
 ADD INDEX IF NOT EXISTS idx_recurrence_date (recurrence_key,created_at),
 ADD INDEX IF NOT EXISTS idx_created (created_at),
 ADD INDEX IF NOT EXISTS idx_status_due (status,due_at),
 ADD INDEX IF NOT EXISTS idx_concern_match (category_name,concern_type,purok_key,created_at),
 ADD INDEX IF NOT EXISTS idx_reporter_date (resident_id,created_at);

ALTER TABLE complaints MODIFY recurrence_key CHAR(64) GENERATED ALWAYS AS (
 CASE WHEN concern_type IS NOT NULL AND purok_key IS NOT NULL AND street_normalized <> ''
 THEN SHA2(LOWER(CONCAT_WS('|',category_name,concern_type,purok_key,street_normalized)),256)
 ELSE NULL END) STORED AFTER street_normalized;

SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_logs' AND COLUMN_NAME='actor_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `audit_logs` ADD CONSTRAINT `mp_ir_audit_logs_actor_id_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='complaints' AND COLUMN_NAME='resident_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `complaints` ADD CONSTRAINT `mp_ir_complaints_resident_id_fk` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_evidence' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `concern_evidence` ADD CONSTRAINT `mp_ir_concern_evidence_complaint_id_fk` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_evidence' AND COLUMN_NAME='uploaded_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `concern_evidence` ADD CONSTRAINT `mp_ir_concern_evidence_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_feedback' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `concern_feedback` ADD CONSTRAINT `mp_ir_concern_feedback_complaint_id_fk` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_feedback' AND COLUMN_NAME='reporter_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `concern_feedback` ADD CONSTRAINT `mp_ir_concern_feedback_reporter_id_fk` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_tracking' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `concern_tracking` ADD CONSTRAINT `mp_ir_concern_tracking_complaint_id_fk` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duplicate_dismissals' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `duplicate_dismissals` ADD CONSTRAINT `mp_ir_duplicate_dismissals_complaint_id_fk` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duplicate_dismissals' AND COLUMN_NAME='candidate_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `duplicate_dismissals` ADD CONSTRAINT `mp_ir_duplicate_dismissals_candidate_id_fk` FOREIGN KEY (`candidate_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duplicate_dismissals' AND COLUMN_NAME='dismissed_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `duplicate_dismissals` ADD CONSTRAINT `mp_ir_duplicate_dismissals_dismissed_by_fk` FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `notifications` ADD CONSTRAINT `mp_ir_notifications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='related_concern_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `notifications` ADD CONSTRAINT `mp_ir_notifications_related_concern_id_fk` FOREIGN KEY (`related_concern_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='official_solution_rules' AND COLUMN_NAME='created_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `official_solution_rules` ADD CONSTRAINT `mp_ir_official_solution_rules_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='official_solution_rules' AND COLUMN_NAME='updated_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `official_solution_rules` ADD CONSTRAINT `mp_ir_official_solution_rules_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_resets' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `password_resets` ADD CONSTRAINT `mp_ir_password_resets_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weekly_action_plans' AND COLUMN_NAME='solution_rule_id' AND REFERENCED_TABLE_NAME='official_solution_rules' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `weekly_action_plans` ADD CONSTRAINT `mp_ir_weekly_action_plans_solution_rule_id_fk` FOREIGN KEY (`solution_rule_id`) REFERENCES `official_solution_rules` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weekly_action_plans' AND COLUMN_NAME='assigned_user_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `weekly_action_plans` ADD CONSTRAINT `mp_ir_weekly_action_plans_assigned_user_id_fk` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weekly_action_plans' AND COLUMN_NAME='created_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE `weekly_action_plans` ADD CONSTRAINT `mp_ir_weekly_action_plans_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='complaints' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%json_valid(payload)%'), 'SELECT 1', 'ALTER TABLE `complaints` ADD CONSTRAINT `mp_ir_complaints_payload_json` CHECK (JSON_VALID(`payload`))');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='solution_rules' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%json_valid(actions)%'), 'SELECT 1', 'ALTER TABLE `solution_rules` ADD CONSTRAINT `mp_ir_solution_rules_actions_json` CHECK (JSON_VALID(`actions`))');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='official_solution_rules' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%sort_order%between%1%3%'), 'SELECT 1', 'ALTER TABLE `official_solution_rules` ADD CONSTRAINT `mp_ir_official_rule_sort` CHECK (`sort_order` BETWEEN 1 AND 3)');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='official_solution_rules' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%active%in%0%1%'), 'SELECT 1', 'ALTER TABLE `official_solution_rules` ADD CONSTRAINT `mp_ir_official_rule_active` CHECK (`active` IN (0,1))');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
