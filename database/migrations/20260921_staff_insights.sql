-- MariaDB (XAMPP). Re-runnable; existing records and images are preserved.
-- Generated columns index existing JSON without a second source of truth.
ALTER TABLE complaints
 ADD COLUMN IF NOT EXISTS assigned_user_id VARCHAR(64) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.assignedUserId')), 'null')) STORED,
 ADD COLUMN IF NOT EXISTS concern_type VARCHAR(100) GENERATED ALWAYS AS (NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.concernType')), 'null')) STORED,
 ADD COLUMN IF NOT EXISTS recurrence_key CHAR(64) GENERATED ALWAYS AS (
   CASE WHEN JSON_EXTRACT(payload,'$.locationDetails.purok') IS NOT NULL AND JSON_EXTRACT(payload,'$.concernType') IS NOT NULL
   THEN SHA2(LOWER(CONCAT_WS('|',
     TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.category'))),
     TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.concernType'))),
     REGEXP_REPLACE(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.purok'))), '[[:space:]]+', ' '),
     REGEXP_REPLACE(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.locationDetails.street'))), '[[:space:]]+', ' '))),256)
   ELSE NULL END) STORED,
 ADD COLUMN IF NOT EXISTS due_at BIGINT GENERATED ALWAYS AS (CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.dueAt')), 'null') AS UNSIGNED)) STORED,
 ADD INDEX IF NOT EXISTS idx_assignment_status (assigned_user_id,status),
 ADD INDEX IF NOT EXISTS idx_recurrence_date (recurrence_key,created_at),
 ADD INDEX IF NOT EXISTS idx_created (created_at),
 ADD INDEX IF NOT EXISTS idx_status_due (status,due_at);

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id VARCHAR(64) NOT NULL,
 type VARCHAR(40) NOT NULL,
 title VARCHAR(150) NOT NULL,
 message VARCHAR(500) NOT NULL,
 related_concern_id VARCHAR(64) NULL,
 target_url VARCHAR(255) NOT NULL,
 event_key CHAR(64) NOT NULL,
 is_read TINYINT NOT NULL DEFAULT 0,
 created_at BIGINT NOT NULL,
 read_at BIGINT NULL,
 UNIQUE KEY uq_notification_event (user_id,event_key),
 INDEX idx_notification_inbox (user_id,is_read,id),
 INDEX idx_notification_recent (user_id,id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (related_concern_id) REFERENCES complaints(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert delivery latches, never cached recurrence counts or workload totals.
CREATE TABLE IF NOT EXISTS feature_alerts (
 alert_key CHAR(64) NOT NULL PRIMARY KEY,
 last_sent BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
