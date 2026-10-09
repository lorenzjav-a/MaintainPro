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
    'messages','my-action-plans','new-complaint','notifications','official-solutions','profile','profile-photo',
    'public-api','report-concern','reports','reports-pdf','router','settings','solutions','track',
    'transparency','user-create','user-edit','user-guide','users'];
foreach ($entrypoints as $entrypoint) {
    $path = $root . '/' . $entrypoint . '.php';
    if (!is_file($path)) throw new RuntimeException('Missing production page: ' . $entrypoint);
    $files[] = $path;
}
foreach (['assets', 'includes', 'database', 'vendor/phpmailer', 'vendor/dompdf'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) throw new RuntimeException('Missing production directory: ' . $directory);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile() && !$file->isLink()) $files[] = $file->getPathname();
}
foreach (['.htaccess', '.env.example', 'README.md', 'DEPLOYMENT.md', 'uploads/.htaccess', 'config/app.php', 'config/database.php', 'config/features.php', 'config/mail.example.php', 'tools/notify-deadlines.php', 'tools/cleanup-security.php', 'tools/health-check.php', 'tools/audit-uploads.php', 'docs/PRODUCTION_READINESS_REPORT.md', 'deployment/apache-vhost.conf.example', 'deployment/nginx.conf.example'] as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing production file: ' . $relative);
    $files[] = $path;
}
$files[]=$root.'/docs/SYSTEM_DEMO_GUIDE.md';
$files[]=$root.'/docs/REPORTING.md';
sort($files, SORT_STRING);
foreach (array_unique($files) as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (preg_match('~(^|/)(?:\.data|tests|tmp|backups|\.git)(?:/|$)|(?:^|/)(?:mail\.local\.php|\.env|[^/]+\.(?:log|bak|zip))$~i', $relative)) continue;
    $archive->addFile($path, $relative);
}
$archive->addFromString('uploads/evidence/.htaccess', "Require all denied\n");
$archive->addFromString('uploads/profiles/.htaccess', "Require all denied\n");
unset($archive);
$inspect=new PharData($output);
$entries=[];
foreach (new RecursiveIteratorIterator($inspect) as $file) {
    $relative=str_replace('\\','/',substr($file->getPathname(),strlen('phar://'.$output)+1));
    $entries[]=$relative;
    $forbidden=preg_match('~(^|/)(?:\.git|\.data|tests|tmp|backups|logs)(?:/|$)|(?:^|/)(?:mail\.local\.php|\.env|[^/]+\.(?:log|bak|zip))$~i',$relative)
        || (str_ends_with(strtolower($relative),'.sql') && !str_starts_with($relative,'database/migrations/'));
    if ($forbidden) throw new RuntimeException('Forbidden release entry: '.$relative);
}
if (!in_array('.env.example',$entries,true) || !in_array('DEPLOYMENT.md',$entries,true)) throw new RuntimeException('Release configuration documentation is incomplete.');
unset($inspect);

// Release ZIPs are reproducible artifacts, not database backups. Keep a small
// local history so repeated builds cannot grow .data without bound.
$retentionValue = getenv('RELEASE_RETENTION');
$retentionValue = $retentionValue === false ? '3' : trim($retentionValue);
if (!preg_match('/\A(?:[1-9]|1[0-9]|20)\z/', $retentionValue)) throw new RuntimeException('RELEASE_RETENTION must be between 1 and 20.');
$releaseFiles = glob($outputDir . '/maintainpro-release-*.zip') ?: [];
rsort($releaseFiles, SORT_STRING);
$removed = 0;
foreach (array_slice($releaseFiles, (int)$retentionValue) as $oldRelease) {
    if (!is_file($oldRelease) || !unlink($oldRelease)) throw new RuntimeException('Unable to remove expired release artifact: ' . basename($oldRelease));
    $removed++;
}
echo $output, PHP_EOL, 'Inspected ',count($entries),' release entries; private runtime data is excluded.',PHP_EOL;
echo 'Release retention kept the newest ',(int)$retentionValue,' archive(s) and removed ',$removed,' older artifact(s).',PHP_EOL;
