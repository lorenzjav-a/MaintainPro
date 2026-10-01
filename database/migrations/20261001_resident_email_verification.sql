-- Existing accounts remain verified. New self-registered residents are inserted unverified.
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS registration_verifications (
    challenge CHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL UNIQUE,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT NOT NULL,
    sent_at BIGINT NOT NULL,
    CONSTRAINT registration_verifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    attempted_at BIGINT NOT NULL,
    INDEX registration_attempt_bucket (bucket, attempted_at),
    INDEX registration_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
