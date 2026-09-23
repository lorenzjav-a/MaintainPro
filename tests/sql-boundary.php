<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Flag SQL literals outside the central database file without mistaking XPath
// query() calls or ordinary comments for database access.
$root = dirname(__DIR__);
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filtered = new RecursiveCallbackFilterIterator($directory, static function (SplFileInfo $file): bool {
    if ($file->isDir()) return !in_array($file->getFilename(), ['.git', '.data', 'vendor', 'tmp'], true);
    return !str_ends_with($file->getFilename(), '.local.php');
});
$violations = [];
$files = 0;
$sqlPattern = '~\b(?:SELECT\s+[^;]+?\s+FROM\b|SELECT\s+\w+\s*\(|INSERT\s+INTO\b|REPLACE\s+INTO\b|UPDATE\s+\S+\s+SET\b|DELETE\s+FROM\b|(?:CREATE|ALTER|DROP|TRUNCATE)\s+(?:TABLE|DATABASE)\b|SHOW\s+(?:COLUMNS|TABLES|DATABASES)\b)~is';
foreach (new RecursiveIteratorIterator($filtered) as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($relative === 'database/database.php' || str_starts_with($relative, 'database/migrations/')) continue;
    if ($file->getExtension() === 'sql') {
        $violations[] = $relative;
        continue;
    }
    if ($file->getExtension() !== 'php') continue;
    $files++;
    foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
        if (!is_array($token) || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) continue;
        if (preg_match($sqlPattern, $token[1])) $violations[] = $relative . ':' . $token[2];
    }
}
if ($violations) {
    fwrite(STDERR, "FAIL: Move SQL into database/database.php:\n" . implode("\n", array_unique($violations)) . "\n");
    exit(1);
}
echo "PASS: SQL boundary checked across $files PHP files; importable migrations are confined to database/migrations.\n";
