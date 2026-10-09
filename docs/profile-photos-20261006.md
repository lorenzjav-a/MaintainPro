# Profile management and account photos — October 6, 2026

The shared profile page now separates the account photo, identity fields, optional password change, and current-password confirmation into readable sections. All resident, personnel, and official accounts use the same page and behavior.

Profile photos accept JPG, PNG, and WebP images up to 5 MB and 20 megapixels. The browser provides an immediate circular preview. The server revalidates the decoded image contents before storing the bytes in MariaDB and keeping a protected copy under `uploads/profiles`. Raw files are blocked by the existing Apache and development-router rules; `profile-photo.php` serves only the current signed-in account's image, reading the database copy first and using the protected file as a compatibility fallback. Initials remain the fallback when no image is saved.

Saving, replacing, or removing a photo requires the current account password. Failed database writes delete newly created files. Successful replacement or removal deletes the superseded file after the transaction commits. Audit history records that the photo changed without storing its path or contents in audit metadata.

Migrations `20261006_profile_photos.sql` and `20261006_profile_photo_data.sql` add nullable path, MIME, and `MEDIUMBLOB` columns to `users`. Applying the migrations does not insert or change account records. Full backups contain the database photo bytes as part of `database.sql`, plus the protected profile file copies and their restoration instructions.

Verification includes dedicated database and file persistence, invalid-image, password, replacement, removal, cleanup, private endpoint, desktop, and mobile checks. Test databases and profile files are isolated and removed by the test harness.
