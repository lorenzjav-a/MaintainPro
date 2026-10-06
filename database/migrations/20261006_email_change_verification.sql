CREATE TABLE IF NOT EXISTS email_change_verifications (
    challenge CHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    new_email VARCHAR(254) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    sent_at BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_email_change_user (user_id),
    KEY idx_email_change_expiry (expires_at),
    CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
