-- Account creation already requests an audit entry within its transaction.
-- Supply the missing persistence without changing existing account records.
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id VARCHAR(64) NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id VARCHAR(255) NULL,
    entity_label VARCHAR(255) NOT NULL,
    changes LONGTEXT NOT NULL CHECK (JSON_VALID(changes)),
    created_at VARCHAR(35) NOT NULL,
    INDEX audit_created (created_at),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
