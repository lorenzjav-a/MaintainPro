<?php
declare(strict_types=1);

/**
 * Small application-level settings that are safe to load before the database.
 * APP_DEBUG is intentionally off unless it is explicitly enabled.
 */
function br_env(string $name, mixed $default = null): mixed
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function br_env_bool(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false) return $default;
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($parsed === null) throw new RuntimeException($name . ' must be true or false.');
    return $parsed;
}

function br_normalize_app_url(string $url): string
{
    $url=rtrim(trim($url),'/');
    return preg_replace('~\Ahttps?://maintainprosystem\.online(?=[:/]|\z)~i','https://www.maintainprosystem.online',$url) ?? $url;
}

function br_app_config(): array
{
    static $config;
    if (isset($config)) return $config;

    $environment = strtolower(trim((string)br_env('APP_ENV', 'development')));
    if (!in_array($environment, ['development', 'staging', 'production', 'test'], true)) throw new RuntimeException('APP_ENV is invalid.');
    $url = br_normalize_app_url((string)br_env('APP_URL', br_env('BR_APP_URL', 'https://www.maintainprosystem.online')));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) throw new RuntimeException('APP_URL must be an absolute HTTP or HTTPS URL.');
    $production = $environment === 'production';
    if ($production && parse_url($url, PHP_URL_SCHEME) !== 'https') throw new RuntimeException('Production APP_URL must use HTTPS.');

    $root = dirname(__DIR__);
    $config = [
        'environment' => $environment,
        'production' => $production,
        'debug' => br_env_bool('APP_DEBUG', false),
        'url' => $url,
        'key' => (string)br_env('APP_KEY', ''),
        'version' => trim((string)br_env('APP_VERSION', '1.0.0')),
        'force_https' => br_env_bool('APP_FORCE_HTTPS', $production),
        'trust_proxy' => br_env_bool('APP_TRUST_PROXY', false),
        'maintenance' => br_env_bool('APP_MAINTENANCE', false),
        'resident_timeout' => max(900, min(86400, (int)br_env('SESSION_RESIDENT_TIMEOUT', '7200'))),
        'staff_timeout' => max(900, min(14400, (int)br_env('SESSION_STAFF_TIMEOUT', '2700'))),
        'log_directory' => $root . DIRECTORY_SEPARATOR . '.data' . DIRECTORY_SEPARATOR . 'logs',
        'log_file' => $root . DIRECTORY_SEPARATOR . '.data' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'maintainpro.log',
        'turnstile' => [
            'site_key' => trim((string)br_env('TURNSTILE_SITE_KEY', '')),
            'secret_key' => trim((string)br_env('TURNSTILE_SECRET_KEY', '')),
            'allowed_hostnames' => ['maintainprosystem.online', 'www.maintainprosystem.online'],
        ],
    ];
    if ($production && $config['debug']) throw new RuntimeException('APP_DEBUG must be false in production.');
    if ($production && strlen($config['key']) < 32) throw new RuntimeException('APP_KEY must contain at least 32 characters in production.');
    if ($production && !$config['force_https']) throw new RuntimeException('APP_FORCE_HTTPS must be true in production.');
    if ($config['version'] === '' || strlen($config['version']) > 40) throw new RuntimeException('APP_VERSION is invalid.');
    $config['turnstile']['enabled'] = $config['turnstile']['site_key'] !== '' && $config['turnstile']['secret_key'] !== '';
    if ($production && !$config['turnstile']['enabled']) throw new RuntimeException('TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are required in production.');
    // Disposable HTTP/browser tests keep expected mail failures out of the live log.
    $testDatabase = getenv('BR_DB_NAME');
    if (is_string($testDatabase) && preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $testDatabase)) {
        $config['log_file'] = $config['log_directory'] . DIRECTORY_SEPARATOR . $testDatabase . '.log';
        $config['environment'] = 'test';
        $config['production'] = false;
        $config['force_https'] = false;
        $config['maintenance'] = false;
    }
    return $config;
}

function br_canonical_redirect_target(array $config,string $requestHost,string $requestTarget,bool $isHttps): ?string
{
    $parts=parse_url((string)($config['url'] ?? ''));
    $canonicalHost=strtolower((string)($parts['host'] ?? ''));
    $requestHost=strtolower(trim($requestHost));
    $requestHost=preg_replace('/:\d+\z/','',$requestHost) ?? '';
    $wrongHost=($config['production'] ?? false) && $canonicalHost!=='' && !hash_equals($canonicalHost,$requestHost);
    $wrongScheme=($config['force_https'] ?? false) && !$isHttps;
    if (!$wrongHost && !$wrongScheme) return null;
    if ($requestTarget==='' || $requestTarget[0]!=='/' || str_contains($requestTarget,"\r") || str_contains($requestTarget,"\n")) $requestTarget='/';
    $origin='https://'.$canonicalHost.(isset($parts['port'])?':'.(int)$parts['port']:'');
    return $origin.$requestTarget;
}

function br_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    $config = br_app_config();
    return $config['trust_proxy'] && strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
}
