<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$outputDir = $root . '/.data/releases';
if (!is_dir($outputDir) && !mkdir($outputDir, 0700, true) && !is_dir($outputDir)) throw new RuntimeException('Cannot create private release directory.');
$output = $outputDir . '/maintainpro-release-' . date('Ymd-His') . '.zip';
$archive = new PharData($output, 0, null, Phar::ZIP);
$files = [];

// Explicit entry points keep future development scripts out of releases.
$entrypoints = ['action-plans','admin','api','audit','auth','backup','blocked','complaint',
    'complaints','concern','concerns','evidence','history','index','landing','login',
    'my-action-plans','new-complaint','notifications','official-solutions','profile',
    'public-api','report-concern','reports','router','settings','solutions','track',
    'transparency','user-create','user-edit','users'];
foreach ($entrypoints as $entrypoint) {
    $path = $root . '/' . $entrypoint . '.php';
    if (!is_file($path)) throw new RuntimeException('Missing production page: ' . $entrypoint);
    $files[] = $path;
}
foreach (['assets', 'includes', 'database', 'vendor/phpmailer'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) throw new RuntimeException('Missing production directory: ' . $directory);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile() && !$file->isLink()) $files[] = $file->getPathname();
}
foreach (['.htaccess', 'uploads/.htaccess', 'config/app.php', 'config/database.php', 'config/features.php', 'config/mail.example.php', 'tools/notify-deadlines.php'] as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing production file: ' . $relative);
    $files[] = $path;
}
sort($files, SORT_STRING);
foreach (array_unique($files) as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (preg_match('~(^|/)(?:\.data|tests|tmp|backups|\.git)(?:/|$)|(?:^|/)(?:mail\.local\.php|\.env|[^/]+\.(?:log|bak|zip))$~i', $relative)) continue;
    $archive->addFile($path, $relative);
}
$archive->addFromString('uploads/evidence/.htaccess', "Require all denied\n");
unset($archive);
echo $output, PHP_EOL;
