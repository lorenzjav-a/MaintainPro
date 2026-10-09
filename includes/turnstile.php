<?php
declare(strict_types=1);

final class TurnstileException extends DomainException
{
    public function __construct(string $message, public readonly string $reason = 'invalid', public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

final class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @param callable(array):array|null $transport */
    public function __construct(
        private readonly string $secretKey,
        private readonly array $allowedHostnames,
        private readonly mixed $transport = null,
    ) {}

    public function verify(mixed $token, string $expectedAction, ?string $remoteIp = null): void
    {
        if (!is_string($token) || trim($token) === '') {
            throw new TurnstileException('Complete the security verification before continuing.', 'missing');
        }
        $token = trim($token);
        if (strlen($token) > 2048) throw new TurnstileException('The security verification response is invalid. Please try again.', 'invalid');

        $payload = ['secret' => $this->secretKey, 'response' => $token];
        if (is_string($remoteIp) && filter_var($remoteIp, FILTER_VALIDATE_IP)) $payload['remoteip'] = $remoteIp;
        try {
            $result = is_callable($this->transport) ? ($this->transport)($payload) : $this->request($payload);
        } catch (TurnstileException $error) {
            throw $error;
        } catch (Throwable $error) {
            br_security_log('turnstile_unavailable', ['result' => 'transport_error']);
            throw new TurnstileException('Security verification is temporarily unavailable. Please try again.', 'unavailable', 503);
        }
        if (!is_array($result)) throw new TurnstileException('Security verification is temporarily unavailable. Please try again.', 'unavailable', 503);

        $errors = is_array($result['error-codes'] ?? null) ? $result['error-codes'] : [];
        if (($result['success'] ?? false) !== true) {
            $duplicate = in_array('timeout-or-duplicate', $errors, true);
            br_security_log('turnstile_rejected', ['result' => $duplicate ? 'expired_or_reused' : 'invalid']);
            throw new TurnstileException(
                $duplicate ? 'Security verification expired or was already used. Complete it again.' : 'Security verification could not be confirmed. Please try again.',
                $duplicate ? 'expired_or_reused' : 'invalid'
            );
        }
        $hostname = strtolower(trim((string)($result['hostname'] ?? '')));
        $allowed = array_map(static fn($value) => strtolower(trim((string)$value)), $this->allowedHostnames);
        if ($hostname === '' || !in_array($hostname, $allowed, true)) {
            br_security_log('turnstile_rejected', ['result' => 'hostname_mismatch']);
            throw new TurnstileException('Security verification was issued for a different website. Please refresh and try again.', 'hostname_mismatch');
        }
        if (!hash_equals($expectedAction, (string)($result['action'] ?? ''))) {
            br_security_log('turnstile_rejected', ['result' => 'action_mismatch']);
            throw new TurnstileException('Security verification did not match this account action. Please try again.', 'action_mismatch');
        }
    }

    private function request(array $payload): array
    {
        $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        if (function_exists('curl_init')) {
            $handle = curl_init(self::ENDPOINT);
            if ($handle === false) throw new RuntimeException('Unable to initialize Turnstile verification.');
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $response = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            if (!is_string($response) || $status < 200 || $status >= 300) throw new RuntimeException('Turnstile Siteverify failed: ' . ($error !== '' ? $error : 'HTTP ' . $status));
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $body, 'timeout' => 8, 'ignore_errors' => true,
            ]]);
            $response = @file_get_contents(self::ENDPOINT, false, $context);
            if (!is_string($response)) throw new RuntimeException('Turnstile Siteverify request failed.');
        }
        $decoded = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('Turnstile Siteverify returned invalid JSON.');
        return $decoded;
    }
}

function br_turnstile_action(string $authAction): ?string
{
    return ['login' => 'login', 'register' => 'register', 'request_reset' => 'password_recovery'][$authAction] ?? null;
}

function br_verify_turnstile(string $authAction, array &$data): void
{
    // Resends use the existing server-side recovery challenge and its database cooldown.
    if ($authAction === 'request_reset' && !is_string($data['email'] ?? null)) return;
    $expected = br_turnstile_action($authAction);
    if ($expected === null) return;
    $config = br_app_config()['turnstile'];
    if (empty($config['enabled'])) return;
    $token = $data['cf-turnstile-response'] ?? null;
    unset($data['cf-turnstile-response']);
    (new TurnstileVerifier($config['secret_key'], $config['allowed_hostnames']))->verify($token, $expected, $_SERVER['REMOTE_ADDR'] ?? null);
}
