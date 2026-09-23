-- MaintainPro anonymous concerns. Select the existing database before importing.
-- Repeatable on XAMPP MariaDB/MySQL. Preserve all existing accounts and records.
ALTER TABLE complaints MODIFY resident_id VARCHAR(64) NULL;

CREATE TABLE IF NOT EXISTS concern_tracking (
    complaint_id VARCHAR(64) NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    CONSTRAINT concern_tracking_case_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    attempted_at BIGINT NOT NULL,
    INDEX public_attempt_bucket (bucket,attempted_at),
    INDEX public_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solution_rules (
    category VARCHAR(100) NOT NULL,
    concern_type VARCHAR(100) NOT NULL,
    actions LONGTEXT NOT NULL,
    PRIMARY KEY (category,concern_type),
    CONSTRAINT solution_rules_json CHECK (JSON_VALID(actions))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing complaints.payload is validated JSON. New records add typed JSON arrays
-- keyPoints, suggestions and timeline[].actions, structured locationDetails, and
-- assignedUserId. Evidence stays private in that record with actorId/date/evidenceId.
-- No resident records, previous evidence or historical case data are deleted.
