-- Preserve existing official administrators. Newly created officials have no
-- system-administration capability unless an administrator explicitly grants it.
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_system_admin TINYINT(1) NOT NULL DEFAULT 0;
UPDATE users SET is_system_admin=1 WHERE role='official';
