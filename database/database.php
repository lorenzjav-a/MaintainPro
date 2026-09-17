<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

// All SQL belongs in this file. Callers pass values to named operations.
final class MaintainProDatabase
{
    public function __construct(private PDO $connection) {}

    private function run(string $sql, array $values = []): PDOStatement
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    public function transaction(callable $work): mixed
    {
        $this->connection->beginTransaction();
        try {
            // Lock before reads so setup, permissions, IDs and versions see current state.
            $lock = $this->run("SELECT value FROM settings WHERE name='next_id' FOR UPDATE");
            if ($lock->fetchColumn() === false) throw new RuntimeException('Run database/setup.php to initialize storage.');
            $lock->closeCursor();
            $result = $work();
            $this->connection->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $error;
        }
    }

    // Accounts
    public function userCount(): int
    {
        return (int)$this->run('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function user(string $id): array|false
    {
        return $this->run('SELECT id,name,email,role,team,active,auth_version,must_change_password,created_at FROM users WHERE id=?', [$id])->fetch();
    }

    public function users(): array
    {
        return $this->run('SELECT id,name,email,role,team,active,must_change_password,created_at FROM users ORDER BY created_at DESC,name')->fetchAll();
    }

    public function insertUser(string $id, string $name, string $email, string $hash, string $role, string $team, string $createdAt): void
    {
        $this->run('INSERT INTO users(id,name,email,password_hash,role,team,created_at) VALUES(?,?,?,?,?,?,?)', [$id, $name, $email, $hash, $role, $team, $createdAt]);
    }

    public function requirePasswordChange(string $id): void
    {
        $this->run('UPDATE users SET must_change_password=1 WHERE id=?', [$id]);
    }

    public function passwordHash(string $id): string|false
    {
        return $this->run('SELECT password_hash FROM users WHERE id=?', [$id])->fetchColumn();
    }

    public function replacePassword(string $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?', [$hash, $id]);
    }

    public function updateUser(string $id, string $role, string $team, bool $active): void
    {
        $this->run('UPDATE users SET role=?,team=?,active=? WHERE id=?', [$role, $team, $active ? 1 : 0, $id]);
    }

    public function updateProfile(string $id, string $name, string $email, string $hash, bool $revokeSessions): void
    {
        $this->run('UPDATE users SET name=?,email=?,password_hash=?,auth_version=auth_version+? WHERE id=?', [$name, $email, $hash, $revokeSessions ? 1 : 0, $id]);
    }

    public function loginUser(string $email): array|false
    {
        return $this->run('SELECT id,password_hash,active FROM users WHERE email=?', [$email])->fetch();
    }

    // Sign-in throttling
    public function deleteOldLoginAttempts(int $before): void
    {
        $this->run('DELETE FROM login_attempts WHERE attempted_at < ?', [$before]);
    }

    public function loginAttemptCount(string $bucket): int
    {
        return (int)$this->run('SELECT COUNT(*) FROM login_attempts WHERE bucket=?', [$bucket])->fetchColumn();
    }

    public function recordLoginAttempt(string $bucket, int $at): void
    {
        $this->run('INSERT INTO login_attempts(bucket,attempted_at) VALUES(?,?)', [$bucket, $at]);
    }

    public function clearLoginAttempts(string $bucket): void
    {
        $this->run('DELETE FROM login_attempts WHERE bucket=?', [$bucket]);
    }

    // Password recovery
    public function deleteOldResetRequests(int $before): void
    {
        $this->run('DELETE FROM password_reset_requests WHERE requested_at < ?', [$before]);
    }

    public function deleteExpiredResets(int $now): void
    {
        $this->run('DELETE FROM password_resets WHERE expires_at < ? AND (reset_expires_at IS NULL OR reset_expires_at < ?)', [$now, $now]);
    }

    public function resetRequestRate(string $bucket): array
    {
        return $this->run('SELECT COUNT(*) AS total, MAX(requested_at) AS latest FROM password_reset_requests WHERE bucket=?', [$bucket])->fetch();
    }

    public function recordResetRequest(string $bucket, int $at): void
    {
        $this->run('INSERT INTO password_reset_requests(bucket,requested_at) VALUES(?,?)', [$bucket, $at]);
    }

    public function resetUser(string $email): array|false
    {
        return $this->run('SELECT id,email,auth_version FROM users WHERE email=? AND active=1', [$email])->fetch();
    }

    public function deleteUserResets(string $userId): void
    {
        $this->run('DELETE FROM password_resets WHERE user_id=?', [$userId]);
    }

    public function insertReset(string $id, string $userId, string $email, int $authVersion, string $hash, int $expiresAt): void
    {
        $this->run('INSERT INTO password_resets(id,user_id,email,auth_version,otp_hash,expires_at) VALUES(?,?,?,?,?,?)', [$id, $userId, $email, $authVersion, $hash, $expiresAt]);
    }

    public function deleteReset(string $id): void
    {
        $this->run('DELETE FROM password_resets WHERE id=?', [$id]);
    }

    public function resetRequest(string $id): array|false
    {
        return $this->run('SELECT r.* FROM password_resets r JOIN users u ON u.id=r.user_id
            WHERE r.id=? AND u.active=1 AND r.auth_version=u.auth_version AND r.email=u.email', [$id])->fetch();
    }

    public function recordResetAttempt(string $id): void
    {
        $this->run('UPDATE password_resets SET attempts=attempts+1 WHERE id=?', [$id]);
    }

    public function grantPasswordReset(string $id, string $tokenHash, int $expiresAt): void
    {
        $this->run('UPDATE password_resets SET reset_token_hash=?,reset_expires_at=?,otp_hash=? WHERE id=?', [$tokenHash, $expiresAt, '', $id]);
    }

    // Complaint persistence
    public function complaints(): array
    {
        return $this->run('SELECT payload,version FROM complaints ORDER BY created_at DESC,id DESC')->fetchAll();
    }

    public function nextComplaintId(): int
    {
        return (int)$this->run("SELECT value FROM settings WHERE name='next_id'")->fetchColumn();
    }

    public function setNextComplaintId(int $nextId): void
    {
        $this->run('UPDATE settings SET value=? WHERE name=?', [(string)$nextId, 'next_id']);
    }

    public function insertComplaint(array $complaint): void
    {
        $this->run('INSERT INTO complaints(id,resident_id,team,status,version,created_at,updated_at,payload) VALUES(?,?,?,?,?,?,?,?)', [
            $complaint['id'], $complaint['residentId'], $complaint['team'], $complaint['status'], $complaint['version'],
            $complaint['createdAt'], $complaint['updatedAt'], json_encode($complaint, JSON_THROW_ON_ERROR),
        ]);
    }

    public function updateComplaint(array $complaint): void
    {
        $this->run('UPDATE complaints SET team=?,status=?,version=?,updated_at=?,payload=? WHERE id=?', [
            $complaint['team'], $complaint['status'], $complaint['version'], $complaint['updatedAt'],
            json_encode($complaint, JSON_THROW_ON_ERROR), $complaint['id'],
        ]);
    }
}

// CLI installation and disposable test database operations.
final class DatabaseMaintenance
{
    private static function requireCli(): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Database maintenance requires the command line.');
    }

    public static function create(string $name, bool $ifNotExists = false): void
    {
        self::requireCli();
        // The connection factory validates the database identifier before interpolation.
        $server = br_database($name, serverOnly: true);
        $server->exec('CREATE DATABASE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . '`' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public static function initialize(PDO $connection): void
    {
        self::requireCli();
        $connection->exec(self::schema());
        $columns = $connection->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('must_change_password', $columns, true)) {
            $connection->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT NOT NULL DEFAULT 0');
        }
    }

    public static function requireTestDatabase(string $name): void
    {
        self::requireCli();
        if (!preg_match('/\Amaintainpro_test_[a-f0-9]{16}\z/', $name)) {
            throw new RuntimeException('This operation requires a disposable test database.');
        }
    }

    public static function dropTestDatabase(string $name): void
    {
        self::requireTestDatabase($name);
        br_database($name, serverOnly: true)->exec('DROP DATABASE `' . $name . '`');
    }

    public static function schema(): string
    {
        return <<<'SQL'
-- MaintainPro: import into the maintainpro database. No sample accounts or complaints.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('resident', 'official', 'personnel') NOT NULL,
    team VARCHAR(100) NOT NULL DEFAULT '',
    active TINYINT NOT NULL DEFAULT 1,
    auth_version INT NOT NULL DEFAULT 1,
    must_change_password TINYINT NOT NULL DEFAULT 0,
    created_at VARCHAR(35) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL,
    auth_version INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT NOT NULL,
    reset_token_hash CHAR(64) DEFAULT NULL,
    reset_expires_at BIGINT DEFAULT NULL,
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    requested_at BIGINT NOT NULL,
    INDEX reset_requests_bucket (bucket, requested_at),
    INDEX reset_requests_time (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaints (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    resident_id VARCHAR(64) NOT NULL,
    team VARCHAR(100) NOT NULL,
    status VARCHAR(64) NOT NULL,
    version INT NOT NULL,
    created_at VARCHAR(35) NOT NULL,
    updated_at VARCHAR(35) NOT NULL,
    payload LONGTEXT NOT NULL,
    INDEX complaints_resident (resident_id),
    INDEX complaints_team (team),
    CONSTRAINT complaints_resident_fk FOREIGN KEY (resident_id) REFERENCES users(id),
    CONSTRAINT complaints_payload_json CHECK (JSON_VALID(payload))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This counter also serializes writes, including first-account setup.
INSERT INTO settings (name, value) VALUES ('next_id', '1')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    attempted_at BIGINT NOT NULL,
    INDEX attempts_bucket (bucket, attempted_at),
    INDEX attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}

// Test fixtures use this same SQL boundary and cannot target the workspace database.
final class DatabaseTestFixtures
{
    public function __construct(private PDO $connection)
    {
        DatabaseMaintenance::requireTestDatabase((string)$connection->query('SELECT DATABASE()')->fetchColumn());
    }

    public function clearResetRate(): void
    {
        $this->connection->exec('DELETE FROM password_reset_requests');
    }

    public function reset(string $id): array|false
    {
        $statement = $this->connection->prepare('SELECT * FROM password_resets WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetch();
    }

    public function resetCount(): int
    {
        return (int)$this->connection->query('SELECT COUNT(*) FROM password_resets')->fetchColumn();
    }

    public function expireResetCodes(): void
    {
        $this->connection->exec('UPDATE password_resets SET expires_at=0');
    }

    public function expireResetGrants(): void
    {
        $this->connection->exec('UPDATE password_resets SET reset_expires_at=0');
    }

    public function elapseResetCooldown(): void
    {
        $this->connection->exec('UPDATE password_reset_requests SET requested_at=requested_at-61');
    }
}
