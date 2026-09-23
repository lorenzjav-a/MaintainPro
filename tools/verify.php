<?php
declare(strict_types=1);
// Run first-party PHP syntax checks and the existing disposable-data suites.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$withBrowser = in_array('--browser', array_slice($argv, 1), true);
foreach (array_slice($argv, 1) as $argument) {
    if ($argument !== '--browser') {
        fwrite(STDERR, "Usage: php tools/verify.php [--browser]\n");
        exit(2);
    }
}

function runVerification(array $arguments, string $root, bool $quiet = false): void
{
    $process = proc_open([PHP_BINARY, ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $root, null, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot start verification process.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    if (!$quiet || $status !== 0) echo $output;
    if ($status !== 0) throw new RuntimeException('Verification failed: ' . implode(' ', $arguments));
}

try {
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filtered = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $file): bool {
        return !$file->isDir() || !in_array($file->getFilename(), ['.git', '.data', 'vendor', 'tmp'], true);
    });
    $count = 0;
    foreach (new RecursiveIteratorIterator($filtered) as $file) {
        if ($file->getExtension() !== 'php') continue;
        runVerification(['-l', $file->getPathname()], $root, true);
        $count++;
    }
    echo "PASS: $count first-party PHP syntax checks.\n";
    foreach (['sql-boundary.php', 'workflow.php', 'store.php', 'features.php', 'password-reset.php', 'http.php'] as $suite) runVerification(['tests/' . $suite], $root);
    if ($withBrowser) runVerification(['tests/browser.php'], $root);
    echo "All requested verification suites passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
