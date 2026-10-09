<?php
declare(strict_types=1);
require_once __DIR__ . '/error-handler.php';
date_default_timezone_set('Asia/Manila');
$app = br_app_config();
if (PHP_SAPI !== 'cli' && $app['force_https'] && !br_request_is_https()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(400); exit('HTTPS is required.'); }
    $parts=parse_url($app['url']);
    $origin='https://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'');
    $requestTarget=(string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($requestTarget==='' || $requestTarget[0]!=='/') $requestTarget='/';
    $target=$origin.$requestTarget;
    header('Location: ' . $target, true, 308);
    exit;
}
if (PHP_SAPI !== 'cli' && $app['maintenance']) {
    http_response_code(503);
    header('Retry-After: 900');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance · MaintainPro</title><script src="assets/js/theme.js"></script><link rel="stylesheet" href="assets/css/app.css"><main class="container py-5"><section class="panel p-4 mx-auto" style="max-width:720px"><h1 class="section-title">Scheduled maintenance</h1><p>MaintainPro is temporarily unavailable while maintenance is being completed.</p></section></main></html>';
    exit;
}
$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maintainpro-sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('The session directory could not be created.');
}
session_save_path($sessionDirectory);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
// Keep authentication only for the current browser session. With no expiry
// or Max-Age attribute, the browser removes this cookie when it fully closes.
ini_set('session.cookie_lifetime', '0');
session_name('maintainpro');
session_set_cookie_params([
    'lifetime' => 0,
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => br_request_is_https(),
    'path' => '/',
]);
session_start();
if (isset($_SESSION['br_user_id'], $_SESSION['br_last_activity'])) {
    $timeout = ($_SESSION['br_role'] ?? '') === 'resident' ? $app['resident_timeout'] : $app['staff_timeout'];
    if (time() - (int)$_SESSION['br_last_activity'] > $timeout) {
        foreach (['br_user_id','br_auth_version','br_role','br_last_activity'] as $key) unset($_SESSION[$key]);
        $_SESSION['br_session_expired'] = true;
        $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
        session_regenerate_id(true);
    }
}
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/store.php';
$_SESSION['br_csrf'] ??= bin2hex(random_bytes(32));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
if ($app['production'] && br_request_is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

function br_store(): ComplaintStore
{
    static $store;
    return $store ??= new ComplaintStore();
}
function br_actor(): ?array
{
    $actor = isset($_SESSION['br_user_id']) ? br_store()->actor($_SESSION['br_user_id']) : null;
    if (!$actor || (int)$actor['auth_version'] !== ($_SESSION['br_auth_version'] ?? null)) return null;
    $_SESSION['br_last_activity'] = time();
    $_SESSION['br_role'] = $actor['role'];
    return $actor;
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
    $_SESSION['br_role'] = $user['role'];
    $_SESSION['br_last_activity'] = time();
    $_SESSION['br_csrf'] = bin2hex(random_bytes(32));
}
