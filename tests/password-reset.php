<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/database.php';
$testDatabase = new TestDatabase();
$db = $testDatabase->connect();
$store = new ComplaintStore($db);
$checks = 0;
function resetCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function resetDenied(callable $call, string $label): void {
    try { $call(); } catch (DomainException $e) { resetCheck(true, $label); return; }
    throw new RuntimeException('FAIL: expected rejection: ' . $label);
}
function clearResetRate(PDO $db): void { $db->exec('DELETE FROM password_reset_requests'); }
$messages = [];
$send = function (string $email, string $code) use (&$messages) { $messages[] = compact('email', 'code'); };
$old = 'Original-password-42';
$new = ['password' => 'Replacement-password-82', 'confirm_password' => 'Replacement-password-82'];
try {
    $admin = $store->setup(['name' => 'Reset Official', 'email' => 'official@example.test', 'password' => $old]);
    $user = $store->register(['name' => 'Reset Resident', 'email' => 'resident@example.test', 'password' => $old]);
    $id = $store->requestPasswordReset('  RESIDENT@example.test ', 'client-one', $send);
    $code = $messages[0]['code'];
    resetCheck($messages[0]['email'] === $user['email'] && preg_match('/^[0-9]{6}$/', $code) === 1, 'OTP sent to normalized registered address');
    $row = $db->query('SELECT * FROM password_resets')->fetch();
    resetCheck($row['otp_hash'] !== $code && password_verify($code, $row['otp_hash']), 'only OTP hash stored');
    resetCheck((int)$row['expires_at'] >= time() + 595, 'OTP expiry is ten minutes');
    resetCheck($store->login($user['email'], $old, 'before-reset')['id'] === $user['id'], 'request does not change password');
    resetDenied(fn() => $store->resetPassword($id, '', $new), 'cannot skip OTP verification');
    resetDenied(fn() => $store->verifyPasswordReset($id, '000000'), 'wrong code rejected');
    resetCheck((int)$db->query('SELECT attempts FROM password_resets')->fetchColumn() === 1, 'wrong attempt persists');
    $token = $store->verifyPasswordReset($id, $code);
    resetCheck(strlen($token) === 64, 'verification creates high entropy grant');
    $row = $db->query('SELECT * FROM password_resets')->fetch();
    resetCheck($row['otp_hash'] === '' && $row['reset_token_hash'] === hash('sha256', $token), 'OTP consumed and grant hashed');
    resetDenied(fn() => $store->verifyPasswordReset($id, $code), 'OTP cannot be reused');
    resetDenied(fn() => $store->resetPassword($id, 'incorrect-token', $new), 'wrong grant rejected');
    resetDenied(fn() => $store->resetPassword($id, $token, ['password' => 'short', 'confirm_password' => 'short']), 'weak new password rejected');
    resetDenied(fn() => $store->resetPassword($id, $token, array_replace($new, ['confirm_password' => 'different'])), 'confirmation required');
    $store->resetPassword($id, $token, $new);
    resetCheck((int)$store->user($user['id'])['auth_version'] === 2, 'reset revokes all prior sessions');
    resetCheck((int)$db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 0, 'reset grant removed');
    resetCheck($store->login($user['email'], $new['password'], 'after-reset')['id'] === $user['id'], 'new password signs in');
    resetDenied(fn() => $store->login($user['email'], $old, 'old-password'), 'old password fails');
    resetDenied(fn() => $store->resetPassword($id, $token, $new), 'completed reset cannot be replayed');

    clearResetRate($db);
    $id = $store->requestPasswordReset($user['email'], 'five-attempts', $send);
    $code = end($messages)['code'];
    for ($i = 0; $i < 5; $i++) resetDenied(fn() => $store->verifyPasswordReset($id, '000000'), 'incorrect OTP attempt');
    resetDenied(fn() => $store->verifyPasswordReset($id, $code), 'correct OTP rejected after five failures');
    resetCheck((int)$db->query('SELECT attempts FROM password_resets')->fetchColumn() === 5, 'attempt cap persists');

    clearResetRate($db);
    $id = $store->requestPasswordReset($user['email'], 'expiry', $send);
    $code = end($messages)['code'];
    $db->exec('UPDATE password_resets SET expires_at=0');
    resetDenied(fn() => $store->verifyPasswordReset($id, $code), 'expired OTP rejected');
    clearResetRate($db);
    $id = $store->requestPasswordReset($user['email'], 'grant-expiry', $send);
    $token = $store->verifyPasswordReset($id, end($messages)['code']);
    $db->exec('UPDATE password_resets SET reset_expires_at=0');
    resetDenied(fn() => $store->resetPassword($id, $token, $new), 'expired verification grant rejected');

    clearResetRate($db);
    $oldId = $store->requestPasswordReset($user['email'], 'resend-a', $send);
    $oldCode = end($messages)['code'];
    resetDenied(fn() => $store->requestPasswordReset($user['email'], 'resend-b', $send), 'email cooldown applies across IPs');
    $db->exec('UPDATE password_reset_requests SET requested_at=requested_at-61');
    $id = $store->requestPasswordReset($user['email'], 'resend-b', $send);
    resetDenied(fn() => $store->verifyPasswordReset($oldId, $oldCode), 'resend invalidates previous challenge');
    resetCheck($store->verifyPasswordReset($id, end($messages)['code']) !== '', 'latest code works');
    $db->exec('UPDATE password_reset_requests SET requested_at=requested_at-61');
    $store->requestPasswordReset($user['email'], 'resend-c', $send);
    $db->exec('UPDATE password_reset_requests SET requested_at=requested_at-61');
    resetDenied(fn() => $store->requestPasswordReset($user['email'], 'resend-d', $send), 'three emails per fifteen minutes');

    clearResetRate($db);
    $sentBefore = count($messages);
    $unknown = $store->requestPasswordReset('unknown@example.test', 'unknown', $send);
    resetCheck(strlen($unknown) === 64 && count($messages) === $sentBefore, 'unknown account gets opaque challenge without email');
    resetDenied(fn() => $store->verifyPasswordReset($unknown, '123456'), 'unknown challenge cannot verify');
    for ($i = 0; $i < 10; $i++) $store->requestPasswordReset('unknown' . $i . '@example.test', 'same-ip', $send);
    resetDenied(fn() => $store->requestPasswordReset('unknown-last@example.test', 'same-ip', $send), 'IP rate limit spans different emails');
    resetDenied(fn() => $store->requestPasswordReset('bad-email', 'bad', $send), 'invalid email rejected');

    clearResetRate($db);
    $id = $store->requestPasswordReset($user['email'], 'deactivate', $send);
    $code = end($messages)['code'];
    $store->updateUser($admin['id'], $user['id'], ['role' => 'resident', 'active' => '0']);
    resetDenied(fn() => $store->verifyPasswordReset($id, $code), 'inactive account cannot verify');
    clearResetRate($db);
    $sentBefore = count($messages);
    $store->requestPasswordReset($user['email'], 'inactive', $send);
    resetCheck(count($messages) === $sentBefore, 'inactive account receives no code');
    $store->updateUser($admin['id'], $user['id'], ['role' => 'resident', 'active' => '1']);
    resetDenied(fn() => $store->verifyPasswordReset($id, $code), 'reactivation cannot restore old code');

    clearResetRate($db);
    $id = $store->requestPasswordReset($user['email'], 'profile-change', $send);
    $token = $store->verifyPasswordReset($id, end($messages)['code']);
    $store->updateProfile($user['id'], ['name' => 'Reset Resident', 'email' => 'changed@example.test', 'current_password' => $new['password']]);
    resetDenied(fn() => $store->resetPassword($id, $token, $new), 'email change invalidates verification');
    clearResetRate($db);
    $id = $store->requestPasswordReset($admin['email'], 'official-reset', $send);
    $token = $store->verifyPasswordReset($id, end($messages)['code']);
    $store->updateProfile($admin['id'], ['name' => $admin['name'], 'email' => $admin['email'], 'current_password' => $old, 'new_password' => $new['password'], 'confirm_new_password' => $new['password']]);
    resetDenied(fn() => $store->resetPassword($id, $token, $new), 'password change invalidates outstanding reset');

    clearResetRate($db);
    $id = $store->requestPasswordReset($admin['email'], 'failed-mail', function () { throw new RuntimeException('Test SMTP failure'); });
    $q = $db->prepare('SELECT COUNT(*) FROM password_resets WHERE id=?');
    $q->execute([$id]);
    resetCheck((int)$q->fetchColumn() === 0, 'failed email invalidates challenge');
    resetCheck($store->login($admin['email'], $new['password'], 'failed-mail-login')['id'] === $admin['id'], 'mail failure leaves password intact');
    echo "PASS: $checks OTP, expiry, replay, throttling, account binding, and password-reset checks.\n";
} finally {
    unset($store, $db);
    $testDatabase->drop();
}
