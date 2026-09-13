<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maintainpro-sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('The session directory could not be created.');
}
session_save_path($sessionDirectory);
ini_set('session.use_strict_mode', '1');
session_name('maintainpro');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/store.php';
$_SESSION['br_csrf'] ??= bin2hex(random_bytes(32));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

function br_store(): ComplaintStore
{
    static $store;
    return $store ??= new ComplaintStore();
}
function br_actor(): ?array
{
    $actor = isset($_SESSION['br_user_id']) ? br_store()->actor($_SESSION['br_user_id']) : null;
    return $actor && (int)$actor['auth_version'] === ($_SESSION['br_auth_version'] ?? null) ? $actor : null;
}
function br_state(): array
{
    return br_store()->state();
}
function br_enter_account(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['br_user_id'] = $user['id'];
    $_SESSION['br_auth_version'] = (int)$user['auth_version'];
    $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
}
