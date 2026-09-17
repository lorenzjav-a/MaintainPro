<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/database/database.php';

// Only databases created by this helper can be removed by it.
final class TestDatabase
{
    public readonly string $name;

    public function __construct()
    {
        $this->name = 'maintainpro_test_' . bin2hex(random_bytes(8));
        DatabaseMaintenance::create($this->name);
        try {
            DatabaseMaintenance::initialize($this->connect());
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
        DatabaseMaintenance::dropTestDatabase($this->name);
    }
}
