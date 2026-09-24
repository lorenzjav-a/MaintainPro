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
    return $config;
}
