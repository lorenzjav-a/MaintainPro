-- Concern communication and internal staff coordination are separate records.
CREATE TABLE IF NOT EXISTS concern_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    complaint_id VARCHAR(64) NOT NULL,
    sender_user_id VARCHAR(64) NULL,
    sender_role ENUM('official','personnel','resident','guest','system') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    visibility ENUM('reporter','staff') NOT NULL,
    body TEXT NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    INDEX concern_messages_thread (complaint_id,id),
    INDEX concern_messages_sender (sender_user_id),
    INDEX concern_messages_visibility (complaint_id,visibility,id),
    CONSTRAINT concern_messages_concern_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE RESTRICT,
    CONSTRAINT concern_messages_sender_fk FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS concern_message_reads (
    complaint_id VARCHAR(64) NOT NULL,
    reader_key VARCHAR(80) NOT NULL,
    last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    read_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (complaint_id,reader_key),
    CONSTRAINT concern_message_reads_concern_fk FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    context_type ENUM('direct','concern','action_plan') NOT NULL,
    related_concern_id VARCHAR(64) NULL,
    related_action_plan_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    created_by VARCHAR(64) NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    updated_at BIGINT UNSIGNED NOT NULL,
    INDEX staff_conversations_recent (updated_at,id),
    INDEX staff_conversations_concern (related_concern_id),
    INDEX staff_conversations_plan (related_action_plan_id),
    INDEX staff_conversations_creator (created_by),
    CONSTRAINT staff_conversations_concern_fk FOREIGN KEY (related_concern_id) REFERENCES complaints(id) ON DELETE RESTRICT,
    CONSTRAINT staff_conversations_plan_fk FOREIGN KEY (related_action_plan_id) REFERENCES weekly_action_plans(id) ON DELETE RESTRICT,
    CONSTRAINT staff_conversations_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_conversation_members (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    joined_at BIGINT UNSIGNED NOT NULL,
    last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (conversation_id,user_id),
    INDEX staff_members_inbox (user_id,active,conversation_id),
    CONSTRAINT staff_members_conversation_fk FOREIGN KEY (conversation_id) REFERENCES staff_conversations(id) ON DELETE CASCADE,
    CONSTRAINT staff_members_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_user_id VARCHAR(64) NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_role ENUM('official','personnel') NOT NULL,
    body TEXT NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    INDEX staff_messages_thread (conversation_id,id),
    INDEX staff_messages_sender (sender_user_id),
    CONSTRAINT staff_messages_conversation_fk FOREIGN KEY (conversation_id) REFERENCES staff_conversations(id) ON DELETE RESTRICT,
    CONSTRAINT staff_messages_sender_fk FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
