-- Personnel progress is separate from the official plan snapshot and status.
CREATE TABLE IF NOT EXISTS action_plan_progress (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action_plan_id BIGINT UNSIGNED NOT NULL,
    actor_id VARCHAR(64) NULL,
    actor_name VARCHAR(100) NOT NULL,
    note TEXT NOT NULL,
    evidence_id CHAR(32) NULL UNIQUE,
    file_path VARCHAR(255) NULL UNIQUE,
    mime_type VARCHAR(40) NULL,
    created_at BIGINT NOT NULL,
    INDEX plan_progress_date (action_plan_id,created_at,id),
    INDEX plan_progress_actor (actor_id),
    CONSTRAINT plan_progress_plan_fk FOREIGN KEY (action_plan_id) REFERENCES weekly_action_plans(id) ON DELETE CASCADE,
    CONSTRAINT plan_progress_actor_fk FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
