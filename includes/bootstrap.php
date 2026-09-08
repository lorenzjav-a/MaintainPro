<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barangayresolve-sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('The demo session directory could not be created.');
}
session_save_path($sessionDirectory);
ini_set('session.use_strict_mode', '1');
session_name('barangayresolve_demo');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/store.php';
$_SESSION['br_mode'] ??= isset($_SESSION['br_state']) ? 'demo' : 'account';
$_SESSION['br_csrf'] ??= bin2hex(random_bytes(32));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

function br_store(): ComplaintStore
{
    static $store;
    return $store ??= new ComplaintStore(getenv('BR_DATABASE_PATH') ?: null);
}
function br_is_demo(): bool
{
    return $_SESSION['br_mode'] === 'demo';
}
function br_actor(): ?array
{
    if (br_is_demo()) return ComplaintDemo::actor($_SESSION['br_role'] ?? 'official', $_SESSION['br_team'] ?? 'Sanitation team');
    $actor = isset($_SESSION['br_user_id']) ? br_store()->actor($_SESSION['br_user_id']) : null;
    return $actor && (int)$actor['auth_version'] === ($_SESSION['br_auth_version'] ?? null) ? $actor : null;
}
function br_state(): array
{
    return br_is_demo() ? ($_SESSION['br_state'] ??= ComplaintDemo::seed()) : br_store()->state();
}
function br_enter_account(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['br_mode'] = 'account';
    $_SESSION['br_user_id'] = $user['id'];
    $_SESSION['br_auth_version'] = (int)$user['auth_version'];
    $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
}
