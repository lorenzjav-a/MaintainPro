-- Keep the validated profile image in MariaDB as well as the protected file copy.
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_data MEDIUMBLOB DEFAULT NULL;
