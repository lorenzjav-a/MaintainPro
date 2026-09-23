<?php
declare(strict_types=1);
// Browser checks use a disposable database; never point them at maintainpro.
require __DIR__ . '/support/database.php';
require __DIR__ . '/support/mail-server.php';
$mailServer = new TestMailServer();
$testDatabase = new TestDatabase();
$server = null;
$serverLog = tempnam(sys_get_temp_dir(), 'maintainpro-browser-');
$oldDatabase = getenv('BR_DB_NAME');
try {
    putenv('BR_DB_NAME=' . $testDatabase->name);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if (!$socket) throw new RuntimeException($message);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, dirname(__DIR__), null, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($server)) throw new RuntimeException('Cannot start test server.');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $code, $message, .1);
        if ($probe) { fclose($probe); break; }
        usleep(100000);
    }
    $runtime = getenv('BR_TEST_NODE') ?: (getenv('LOCALAPPDATA') . '/Programs/Microsoft VS Code/Code.exe');
    if (!is_file($runtime)) throw new RuntimeException('Set BR_TEST_NODE to Node.js 22+ (or a compatible Electron executable).');
    $environment = getenv();
    $environment['BR_TEST_URL'] = 'http://' . $address;
    $environment['ELECTRON_RUN_AS_NODE'] = '1';
    $runner = proc_open([$runtime, __DIR__ . '/browser.cjs'], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $runnerPipes, dirname(__DIR__), $environment, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($runner)) throw new RuntimeException('Cannot start browser checks.');
    fclose($runnerPipes[0]);
    $status = proc_close($runner);
    if ($status !== 0) throw new RuntimeException('Browser checks failed.');
    if (preg_match('/(?:Fatal error|Warning|Notice):/', file_get_contents($serverLog))) throw new RuntimeException('PHP diagnostics occurred during browser checks.');
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    putenv($oldDatabase === false ? 'BR_DB_NAME' : 'BR_DB_NAME=' . $oldDatabase);
    $testDatabase->drop();
    $mailServer->stop();
    if (is_file($serverLog)) unlink($serverLog);
}
