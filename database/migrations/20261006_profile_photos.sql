-- Profile photos are stored as validated files outside the database.
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_mime VARCHAR(32) DEFAULT NULL;
