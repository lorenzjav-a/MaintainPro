CREATE TABLE IF NOT EXISTS concern_team_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    complaint_id VARCHAR(64) NOT NULL,
    team VARCHAR(100) NOT NULL,
    assigned_by VARCHAR(64) NULL,
    assigned_at BIGINT UNSIGNED NOT NULL,
    due_at BIGINT UNSIGNED NULL,
    status ENUM('open','accepted','replaced','cancelled') NOT NULL DEFAULT 'open',
    accepted_by VARCHAR(64) NULL,
    accepted_at BIGINT UNSIGNED NULL,
    INDEX team_assignment_concern (complaint_id,status,id),
    INDEX team_assignment_queue (team,status,assigned_at),
    INDEX team_assignment_official (assigned_by),
    INDEX team_assignment_acceptor (accepted_by),
    CONSTRAINT team_assignment_concern_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    CONSTRAINT team_assignment_official_fk FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT team_assignment_acceptor_fk FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS concern_team_recipients (
    assignment_id BIGINT UNSIGNED NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    response ENUM('pending','accepted','declined','closed') NOT NULL DEFAULT 'pending',
    responded_at BIGINT UNSIGNED NULL,
    PRIMARY KEY (assignment_id,user_id),
    INDEX team_recipient_queue (user_id,response,assignment_id),
    CONSTRAINT team_recipient_assignment_fk FOREIGN KEY (assignment_id) REFERENCES concern_team_assignments(id) ON DELETE CASCADE,
    CONSTRAINT team_recipient_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
