<?php
declare(strict_types=1);
// Pass this router to PHP's development server to protect private project files.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)\.[^/]*(?:/|$)~', $path)
    // Match the root PHP dependencies, not public browser assets/vendor.
    || preg_match('~^/vendor(?:/|$)~i', $path)
    || preg_match('~(?:^|/)(?:includes|config|tools|tests|database|logs|backups|uploads)(?:/|$)~i', $path)
    || preg_match('~\.(?:log|sql|sqlite|db|bak|backup|dump|zip)(?:\.(?:gz|zip))?$~i', $path)
    || str_contains($path, '\\') || str_contains($path, "\0")) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
$root = __DIR__;
$file = realpath($root . $path);
if ($file !== false && !str_starts_with($file, $root . DIRECTORY_SEPARATOR) && $file !== $root) {
    http_response_code(404);
    return true;
}
if ($path === '/') {
    require $root . '/index.php';
    return true;
}
return false;
