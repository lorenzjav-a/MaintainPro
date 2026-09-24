-- Complete storage required by existing evidence and location workflows.
CREATE TABLE IF NOT EXISTS concern_evidence (
 id CHAR(32) NOT NULL PRIMARY KEY,
 complaint_id VARCHAR(64) NOT NULL,
 uploaded_by VARCHAR(64) NULL,
 evidence_type VARCHAR(32) NOT NULL,
 file_path VARCHAR(255) NOT NULL UNIQUE,
 original_filename VARCHAR(180) NOT NULL,
 mime_type VARCHAR(32) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_at BIGINT NOT NULL,
 INDEX evidence_concern (complaint_id,created_at),
 FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
 FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 sort_order INT NOT NULL DEFAULT 0,
 active TINYINT NOT NULL DEFAULT 1,
 created_at BIGINT NOT NULL,
 updated_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
