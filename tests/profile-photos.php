<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
$testDatabase = new TestDatabase();
$store = new ComplaintStore($testDatabase->connect());
$checks = 0;
function photoCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function photoDenied(callable $work, string $label): void {
    try { $work(); } catch (DomainException) { photoCheck(true, $label); return; }
    throw new RuntimeException('FAIL: expected rejection: ' . $label);
}
$password = 'Profile-photo-password-42';
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
$backupPath = null;
try {
    $user = $store->setup(['name'=>'Photo Official','email'=>'photo@example.test','password'=>$password]);
    photoCheck(array_key_exists('profile_photo_path', $user) && $user['profile_photo_path'] === null, 'new account has no profile photo');
    $store->updateProfile($user['id'], ['name'=>$user['name'],'email'=>$user['email'],'current_password'=>$password,'photo'=>$png]);
    $saved = $store->user($user['id']);
    $firstPath = (string)$saved['profile_photo_path'];
    photoCheck($firstPath !== '' && $saved['profile_photo_mime'] === 'image/png', 'validated profile photo metadata saved');
    $databasePhoto = $store->profilePhoto($user['id']);
    $expectedBytes = base64_decode(substr($png, strpos($png, ',') + 1), true);
    photoCheck(is_string($databasePhoto['profile_photo_data'] ?? null)
        && is_string($expectedBytes)
        && hash_equals(hash('sha256', $expectedBytes), hash('sha256', $databasePhoto['profile_photo_data'])),
        'validated profile photo bytes stored in the database');
    photoCheck(ProfilePhotoStorage::storedFile($firstPath)['mime_type'] === 'image/png', 'profile photo stored as a validated image file');
    $backup = $store->generateFullBackup($user['id']);
    $backupPath = $backup['path'];
    $archive = new PharData($backupPath);
    photoCheck(isset($archive[$firstPath]) && str_contains($archive['RESTORE.txt']->getContent(), 'uploads/profiles'), 'full backup includes profile photo and restore instructions');
    unset($archive);
    unlink($backupPath);
    $backupPath = null;
    photoDenied(fn()=>$store->updateProfile($user['id'], ['name'=>$user['name'],'email'=>$user['email'],'current_password'=>'wrong','remove_photo'=>'1']), 'current password protects profile photo changes');
    photoCheck($store->user($user['id'])['profile_photo_path'] === $firstPath, 'failed change preserves current profile photo');
    photoDenied(fn()=>$store->updateProfile($user['id'], ['name'=>$user['name'],'email'=>$user['email'],'current_password'=>$password,'photo'=>'data:image/png;base64,'.base64_encode('<?php echo 1; ?>')]), 'script masquerading as profile photo rejected');
    photoCheck($store->user($user['id'])['profile_photo_path'] === $firstPath, 'invalid replacement preserves current profile photo');
    $store->updateProfile($user['id'], ['name'=>$user['name'],'email'=>$user['email'],'current_password'=>$password,'photo'=>$png]);
    $secondPath = (string)$store->user($user['id'])['profile_photo_path'];
    photoCheck($secondPath !== $firstPath && ProfilePhotoStorage::storedFile($firstPath) === null, 'replacement deletes the superseded file after commit');
    $store->updateProfile($user['id'], ['name'=>$user['name'],'email'=>$user['email'],'current_password'=>$password,'remove_photo'=>'1']);
    $removed = $store->user($user['id']);
    photoCheck($removed['profile_photo_path'] === null && $removed['profile_photo_mime'] === null, 'profile photo can be removed');
    photoCheck(($store->profilePhoto($user['id'])['profile_photo_data'] ?? null) === null, 'removal clears profile photo bytes from the database');
    photoCheck(ProfilePhotoStorage::storedFile($secondPath) === null, 'removal deletes the saved file');
    echo "PASS: $checks profile-photo validation, persistence, replacement, password and removal checks.\n";
} finally {
    if ($backupPath !== null && is_file($backupPath)) unlink($backupPath);
    unset($store);
    $testDatabase->drop();
}
