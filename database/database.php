<?php
declare(strict_types=1);

function br_database_config(): array
{
    return [
        'host' => getenv('BR_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('BR_DB_PORT') ?: '3306',
        'name' => getenv('BR_DB_NAME') ?: 'maintainpro',
        'user' => getenv('BR_DB_USER') ?: 'root',
        'password' => getenv('BR_DB_PASSWORD') !== false ? getenv('BR_DB_PASSWORD') : '',
    ];
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
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
