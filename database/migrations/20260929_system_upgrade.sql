-- Existing-installation repair. No records are removed or reassigned.
-- If existing orphans/invalid JSON prevent a constraint, fix their ownership explicitly
-- and rerun setup. Earlier successful DDL is safe to rerun.
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='complaints' AND COLUMN_NAME='resident_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE complaints ADD CONSTRAINT mp_complaints_resident_id_fk FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE RESTRICT');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_tracking' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE concern_tracking ADD CONSTRAINT mp_concern_tracking_complaint_id_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE notifications ADD CONSTRAINT mp_notifications_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='related_concern_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE notifications ADD CONSTRAINT mp_notifications_related_concern_id_fk FOREIGN KEY (related_concern_id) REFERENCES complaints(id) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_resets' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE password_resets ADD CONSTRAINT mp_password_resets_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_logs' AND COLUMN_NAME='actor_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE audit_logs ADD CONSTRAINT mp_audit_logs_actor_id_fk FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_evidence' AND COLUMN_NAME='complaint_id' AND REFERENCED_TABLE_NAME='complaints' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE concern_evidence ADD CONSTRAINT mp_concern_evidence_complaint_id_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='concern_evidence' AND COLUMN_NAME='uploaded_by' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'), 'SELECT 1', 'ALTER TABLE concern_evidence ADD CONSTRAINT mp_concern_evidence_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='complaints' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%json_valid(payload)%'), 'SELECT 1', 'ALTER TABLE complaints ADD CONSTRAINT mp_complaints_json CHECK (JSON_VALID(payload))');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;
SET @mp_sql = IF(EXISTS(SELECT 1 FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='solution_rules' AND LOWER(REPLACE(CHECK_CLAUSE,'`','')) LIKE '%json_valid(actions)%'), 'SELECT 1', 'ALTER TABLE solution_rules ADD CONSTRAINT mp_solution_rules_json CHECK (JSON_VALID(actions))');
PREPARE mp_stmt FROM @mp_sql; EXECUTE mp_stmt; DEALLOCATE PREPARE mp_stmt;

ALTER TABLE solution_rules CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
INSERT INTO settings(name,value) SELECT 'setup_complete','1' WHERE EXISTS(SELECT 1 FROM users WHERE role='official')
ON DUPLICATE KEY UPDATE value='1';

ALTER TABLE complaints
 ADD COLUMN IF NOT EXISTS category_name VARCHAR(100) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.category')),'null')) STORED,
 ADD COLUMN IF NOT EXISTS purok_key VARCHAR(140) GENERATED ALWAYS AS (
   CASE WHEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purokId')),'null') IS NOT NULL
   THEN CONCAT('id:',JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purokId')))
   ELSE CONCAT('name:',LOWER(TRIM(REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purok')),'[[:space:]]+',' ')))) END) STORED,
 ADD COLUMN IF NOT EXISTS street_normalized VARCHAR(140) GENERATED ALWAYS AS (
   TRIM(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(
   LOWER(REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.street')),'null'),''),'.','')),
   '[[:space:]]+',' '),'(^| )st( |$)',' street '),'(^| )rd( |$)',' road '),'(^| )ave( |$)',' avenue '),'[[:space:]]+',' '))) STORED,
 ADD INDEX IF NOT EXISTS idx_concern_match (category_name,concern_type,purok_key,created_at),
 ADD INDEX IF NOT EXISTS idx_reporter_date (resident_id,created_at);

ALTER TABLE complaints MODIFY recurrence_key CHAR(64) GENERATED ALWAYS AS (
 CASE WHEN concern_type IS NOT NULL AND purok_key IS NOT NULL AND street_normalized <> ''
 THEN SHA2(LOWER(CONCAT_WS('|',category_name,concern_type,purok_key,street_normalized)),256)
 ELSE NULL END) STORED AFTER street_normalized;

CREATE TABLE IF NOT EXISTS duplicate_dismissals (
 complaint_id VARCHAR(64) NOT NULL,
 candidate_id VARCHAR(64) NOT NULL,
 dismissed_by VARCHAR(64) NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(complaint_id,candidate_id),
 FOREIGN KEY(complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
 FOREIGN KEY(candidate_id) REFERENCES complaints(id) ON DELETE CASCADE,
 FOREIGN KEY(dismissed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS official_solution_rules (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 category VARCHAR(100) NOT NULL,
 concern_type VARCHAR(100) NOT NULL,
 keypoint VARCHAR(100) NOT NULL,
 action_text VARCHAR(700) NOT NULL,
 sort_order TINYINT UNSIGNED NOT NULL,
 active TINYINT NOT NULL DEFAULT 1,
 created_by VARCHAR(64) NULL,
 updated_by VARCHAR(64) NULL,
 created_at BIGINT NOT NULL,
 updated_at BIGINT NOT NULL,
 version INT NOT NULL DEFAULT 1,
 UNIQUE KEY official_rule_slot(category,concern_type,keypoint,sort_order),
 CHECK(sort_order BETWEEN 1 AND 3),
 CHECK(active IN (0,1)),
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekly_action_plans (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 solution_rule_id BIGINT UNSIGNED NULL,
 category VARCHAR(100) NOT NULL,
 concern_type VARCHAR(100) NOT NULL,
 keypoint VARCHAR(100) NOT NULL,
 selected_solution VARCHAR(700) NOT NULL,
 title VARCHAR(180) NOT NULL,
 notes TEXT NOT NULL,
 team VARCHAR(100) NOT NULL,
 assigned_user_id VARCHAR(64) NULL,
 created_by VARCHAR(64) NULL,
 week_start DATE NOT NULL,
 target_date DATE NULL,
 status ENUM('Planned','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
 completed_at BIGINT NULL,
 outcome TEXT NOT NULL,
 created_at BIGINT NOT NULL,
 updated_at BIGINT NOT NULL,
 version INT NOT NULL DEFAULT 1,
 request_key CHAR(64) NOT NULL UNIQUE,
 INDEX plans_week_status(week_start,status),
 INDEX plans_assignee(assigned_user_id,status),
 FOREIGN KEY(solution_rule_id) REFERENCES official_solution_rules(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS concern_feedback (
 complaint_id VARCHAR(64) NOT NULL PRIMARY KEY,
 reporter_id VARCHAR(64) NULL,
 rating TINYINT UNSIGNED NOT NULL CHECK(rating BETWEEN 1 AND 5),
 comment VARCHAR(2000) NOT NULL DEFAULT '',
 created_at BIGINT NOT NULL,
 updated_at BIGINT NOT NULL,
 INDEX feedback_recent(created_at),
 FOREIGN KEY(complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
 FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
