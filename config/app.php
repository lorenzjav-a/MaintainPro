<?php
declare(strict_types=1);

/**
 * Small application-level settings that are safe to load before the database.
 * APP_DEBUG is intentionally off unless it is explicitly enabled.
 */
function br_app_config(): array
{
    static $config;
    if (isset($config)) return $config;

    $debugValue = getenv('APP_DEBUG');
    $debug = $debugValue !== false
        && in_array(strtolower(trim($debugValue)), ['1', 'true', 'yes', 'on'], true);

    $root = dirname(__DIR__);
    $config = [
        'debug' => $debug,
        'log_directory' => $root . DIRECTORY_SEPARATOR . '.data' . DIRECTORY_SEPARATOR . 'logs',
        'log_file' => $root . DIRECTORY_SEPARATOR . '.data' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'maintainpro.log',
    ];
    // Disposable HTTP/browser tests keep expected mail failures out of the live log.
    $testDatabase = getenv('BR_DB_NAME');
    if (is_string($testDatabase) && preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $testDatabase)) {
        $config['log_file'] = $config['log_directory'] . DIRECTORY_SEPARATOR . $testDatabase . '.log';
    }
    return $config;
}
