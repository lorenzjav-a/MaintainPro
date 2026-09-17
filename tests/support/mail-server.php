<?php
declare(strict_types=1);
// A loopback SMTP inbox for integration tests. It never forwards email.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--serve') {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if (!$socket) exit(1);
    echo stream_socket_get_name($socket, false), "\n";
    flush();
    while ($client = @stream_socket_accept($socket, 60)) {
        stream_set_timeout($client, 10);
        fwrite($client, "220 localhost Test SMTP\r\n");
        $recipient = '';
        while (($line = fgets($client)) !== false) {
            $command = strtoupper(strtok(trim($line), ' '));
            if ($command === 'EHLO' || $command === 'HELO') fwrite($client, "250-localhost\r\n250 8BITMIME\r\n");
            elseif ($command === 'RCPT') { $recipient = trim($line); fwrite($client, "250 OK\r\n"); }
            elseif ($command === 'DATA') {
                fwrite($client, "354 Send data\r\n");
                $body = '';
                while (($part = fgets($client)) !== false && rtrim($part, "\r\n") !== '.') $body .= $part;
                file_put_contents($argv[2], json_encode(['recipient' => $recipient, 'body' => $body]) . "\n", FILE_APPEND | LOCK_EX);
                fwrite($client, "250 Message accepted\r\n");
            } elseif ($command === 'QUIT') { fwrite($client, "221 Bye\r\n"); break; }
            else fwrite($client, "250 OK\r\n");
        }
        fclose($client);
    }
    exit;
}

final class TestMailServer
{
    private $process;
    private array $pipes = [];
    private string $inbox;
    private array $environment = [];

    public function __construct()
    {
        $this->inbox = tempnam(sys_get_temp_dir(), 'maintainpro-mail-test-');
        $this->process = proc_open([PHP_BINARY, __FILE__, '--serve', $this->inbox],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes,
            __DIR__, null, ['bypass_shell' => true, 'create_no_window' => true]);
        if (!is_resource($this->process)) throw new RuntimeException('Cannot start SMTP test inbox.');
        stream_set_timeout($this->pipes[1], 5);
        $address = trim(fgets($this->pipes[1]) ?: '');
        if (!preg_match('/^127\.0\.0\.1:([0-9]+)$/', $address, $match)) {
            $this->stop();
            throw new RuntimeException('SMTP test inbox did not start.');
        }
        foreach (['HOST' => '127.0.0.1', 'PORT' => $match[1], 'AUTH' => '0', 'ENCRYPTION' => 'none',
            'USERNAME' => '', 'PASSWORD' => '', 'FROM_EMAIL' => 'maintainpro@example.test', 'FROM_NAME' => 'MaintainPro'] as $key => $value) {
            $key = 'BR_SMTP_' . $key;
            $this->environment[$key] = getenv($key);
            putenv($key . '=' . $value);
        }
    }

    public function messages(): array
    {
        return array_map(fn($line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR), file($this->inbox, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    public function stop(): void
    {
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); }
        foreach ($this->pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        foreach ($this->environment as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
        if (is_file($this->inbox)) unlink($this->inbox);
    }
}
