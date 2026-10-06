<?php
declare(strict_types=1);

function br_database_config(): array
{
    require_once __DIR__ . '/app.php';
    $value = static fn(string $primary, string $legacy, string $default): string => (string)br_env($primary, br_env($legacy, $default));
    $config = [
        'host' => $value('DB_HOST', 'BR_DB_HOST', '127.0.0.1'),
        'port' => $value('DB_PORT', 'BR_DB_PORT', '3306'),
        'name' => $value('DB_NAME', 'BR_DB_NAME', 'maintainpro'),
        'user' => $value('DB_USER', 'BR_DB_USER', 'root'),
        'password' => $value('DB_PASSWORD', 'BR_DB_PASSWORD', ''),
    ];
    if (br_app_config()['production'] && (strtolower(trim($config['user'])) === 'root' || $config['password'] === '')) throw new RuntimeException('Production requires a dedicated database account with a non-empty password.');
    return $config;
}

function br_database(?string $name = null, bool $serverOnly = false): PDO
{
    $config = br_database_config();
    $name ??= $config['name'];
    if (!preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $name)) {
        throw new RuntimeException('Use only letters, numbers, and underscores for the database name.');
    }
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port']
        . ($serverOnly ? '' : ';dbname=' . $name) . ';charset=utf8mb4';
    $connection = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
    // Keep server-only connections consistent too; the selected database still
    // controls table defaults when a database is later created or selected.
    $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $connection;
}
