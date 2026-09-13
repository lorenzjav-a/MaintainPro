-- MaintainPro: import into the maintainpro database. No sample accounts or complaints.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('resident', 'official', 'personnel') NOT NULL,
    team VARCHAR(100) NOT NULL DEFAULT '',
    active TINYINT NOT NULL DEFAULT 1,
    auth_version INT NOT NULL DEFAULT 1,
    created_at VARCHAR(35) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL,
    auth_version INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT NOT NULL,
    reset_token_hash CHAR(64) DEFAULT NULL,
    reset_expires_at BIGINT DEFAULT NULL,
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    requested_at BIGINT NOT NULL,
    INDEX reset_requests_bucket (bucket, requested_at),
    INDEX reset_requests_time (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaints (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    resident_id VARCHAR(64) NOT NULL,
    team VARCHAR(100) NOT NULL,
    status VARCHAR(64) NOT NULL,
    version INT NOT NULL,
    created_at VARCHAR(35) NOT NULL,
    updated_at VARCHAR(35) NOT NULL,
    payload LONGTEXT NOT NULL,
    INDEX complaints_resident (resident_id),
    INDEX complaints_team (team),
    CONSTRAINT complaints_resident_fk FOREIGN KEY (resident_id) REFERENCES users(id),
    CONSTRAINT complaints_payload_json CHECK (JSON_VALID(payload))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This counter also serializes writes, including first-account setup.
INSERT INTO settings (name, value) VALUES ('next_id', '1')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    attempted_at BIGINT NOT NULL,
    INDEX attempts_bucket (bucket, attempted_at),
    INDEX attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
