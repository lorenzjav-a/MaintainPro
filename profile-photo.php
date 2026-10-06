<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile-photo-storage.php';
$actor = br_actor();
if (!$actor || $actor['must_change_password']) { http_response_code(403); exit; }
$record = br_store()->profilePhoto($actor['id']);
$databasePhoto = $record !== null
    ? ProfilePhotoStorage::databasePhoto($record['profile_photo_data'] ?? null, $record['profile_photo_mime'] ?? null)
    : null;
if ($databasePhoto !== null) {
    header('Content-Type: ' . $databasePhoto['mime_type']);
    header('Content-Length: ' . $databasePhoto['file_size']);
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: inline; filename="profile-photo.' . $databasePhoto['extension'] . '"');
    echo $databasePhoto['data'];
    exit;
}

$path = is_string($record['profile_photo_path'] ?? null) ? $record['profile_photo_path'] : '';
$file = $path !== '' ? ProfilePhotoStorage::storedFile($path) : null;
if ($file === null || (($record['profile_photo_mime'] ?? '') !== '' && $record['profile_photo_mime'] !== $file['mime_type'])) {
    http_response_code(404);
    exit;
}
$handle = @fopen($file['path'], 'rb');
if ($handle === false) { http_response_code(404); exit; }
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . $file['file_size']);
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Content-Disposition: inline; filename="profile-photo.' . $file['extension'] . '"');
fpassthru($handle);
fclose($handle);
