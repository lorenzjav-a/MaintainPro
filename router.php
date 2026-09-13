<?php
declare(strict_types=1);
// Pass this router to PHP's development server to protect private project files.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)(?:\.[^/]*|includes|tests|database)(?:/|$)~i', $path) || str_contains($path, '\\')) {
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
