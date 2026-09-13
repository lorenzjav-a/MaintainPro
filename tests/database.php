<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/database/database.php';

// Only databases created by this helper can be removed by it.
final class TestDatabase
{
    public readonly string $name;
    private PDO $server;

    public function __construct()
    {
        $this->name = 'maintainpro_test_' . bin2hex(random_bytes(8));
        $this->server = br_database(serverOnly: true);
        $this->server->exec('CREATE DATABASE `' . $this->name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $this->connect()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
        } catch (Throwable $e) {
            $this->drop();
            throw $e;
        }
    }

    public function connect(): PDO
    {
        return br_database($this->name);
    }

    public function drop(): void
    {
        if (!preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $this->name)) {
            throw new RuntimeException('Refusing to remove a non-test database.');
        }
        $this->server->exec('DROP DATABASE `' . $this->name . '`');
    }
}
