CREATE TABLE IF NOT EXISTS account_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_by VARCHAR(64) NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    used_at BIGINT UNSIGNED NULL,
    revoked_at BIGINT UNSIGNED NULL,
    delivery_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    sent_at BIGINT UNSIGNED NULL,
    UNIQUE KEY account_invitation_token (token_hash),
    INDEX account_invitation_user (user_id,created_at),
    INDEX account_invitation_expiry (expires_at,used_at,revoked_at),
    INDEX account_invitation_creator (created_by),
    CONSTRAINT account_invitation_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT account_invitation_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
