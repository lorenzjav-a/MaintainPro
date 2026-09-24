<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/database/database.php';

// Only databases created by this helper can be removed by it.
final class TestDatabase
{
    public readonly string $name;
    private string|false $previousEvidence;

    public function __construct()
    {
        $this->name = 'maintainpro_test_' . bin2hex(random_bytes(8));
        $this->previousEvidence=getenv('BR_EVIDENCE_TEST_DATABASE');
        putenv('BR_EVIDENCE_TEST_DATABASE='.$this->name);
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

    public function assertHealthyLog(): void
    {
        $path = dirname(__DIR__, 2) . '/.data/logs/' . $this->name . '.log';
        $log = is_file($path) ? file_get_contents($path) : '';
        if (preg_match('/(?:PHP (?:Warning|Notice|Fatal error|Deprecated)|(?:Exception|Error):)/', $log)) {
            throw new RuntimeException('PHP diagnostics in isolated application log: ' . $log);
        }
    }

    public function drop(): void
    {
        DatabaseMaintenance::dropTestDatabase($this->name);
        $directory=dirname(__DIR__,2).'/uploads/evidence/'.$this->name;
        $resolved=realpath($directory);
        $parent=realpath(dirname($directory));
        if ($resolved!==false && $parent!==false && dirname($resolved)===$parent && basename($resolved)===$this->name) {
            foreach (glob($resolved.'/*') ?: [] as $path) if (is_file($path) && dirname(realpath($path))===$resolved) unlink($path);
            rmdir($resolved);
        }
        putenv($this->previousEvidence===false?'BR_EVIDENCE_TEST_DATABASE':'BR_EVIDENCE_TEST_DATABASE='.$this->previousEvidence);
        $log = dirname(__DIR__, 2) . '/.data/logs/' . $this->name . '.log';
        if (is_file($log)) unlink($log);
    }
}
