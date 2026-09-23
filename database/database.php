<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

// All SQL belongs in this file. Callers pass values to named operations.
final class MaintainProDatabase
{
    public function workloads(): array
    {
        return $this->run("SELECT u.id,u.name,u.team,u.active,
            COALESCE(SUM(c.status IN ('Assigned','In Progress')),0) AS active_work,
            COALESCE(SUM(c.status IN ('Resolved','Verified')),0) AS completed
            FROM users u LEFT JOIN complaints c ON c.assigned_user_id=u.id
            WHERE u.role='personnel' GROUP BY u.id,u.name,u.team,u.active ORDER BY u.name")->fetchAll();
    }

    public function navigationCounts(array $actor): array
    {
        return $this->run("SELECT COUNT(*) AS total,COALESCE(SUM(status IN ('Submitted','Under Review','Reopened')),0) AS assessment
            FROM complaints WHERE ?='official' OR assigned_user_id=?", [$actor['role'],$actor['id']])->fetch();
    }

    public function recurrenceGroups(string $since, int $minimum = 2): array
    {
        return $this->run("SELECT g.*,c.concern_type,
            JSON_UNQUOTE(JSON_EXTRACT(c.payload,'$.locationDetails.purok')) AS area,
            JSON_UNQUOTE(JSON_EXTRACT(c.payload,'$.locationDetails.street')) AS street
            FROM (SELECT recurrence_key,COUNT(*) AS total,MAX(created_at) AS latest,MIN(id) AS example_id
              FROM complaints WHERE created_at>=? AND recurrence_key IS NOT NULL
              GROUP BY recurrence_key HAVING COUNT(*)>=? ORDER BY total DESC,latest DESC LIMIT 100) g
            JOIN complaints c ON c.id=g.example_id ORDER BY g.total DESC,g.latest DESC", [$since,$minimum])->fetchAll();
    }

    public function recurrenceFor(string $id, string $since): array
    {
        return $this->run('SELECT c.id,c.status,c.created_at,c.concern_type,c.recurrence_key FROM complaints c
            JOIN complaints source ON source.recurrence_key=c.recurrence_key
            WHERE source.id=? AND c.created_at>=? ORDER BY c.created_at DESC,c.id DESC', [$id,$since])->fetchAll();
    }

    public function createNotification(string $user, string $type, string $title, string $message, string $concern, string $event, bool $queue = false): void
    {
        $target = $queue ? 'concerns.php' : 'concern.php?id=' . rawurlencode($concern);
        $this->run('INSERT INTO notifications (user_id,type,title,message,related_concern_id,target_url,event_key,created_at)
            SELECT users.id,?,?,?,?,?,?,? FROM users WHERE users.id=? AND active=1 AND role IN (\'official\',\'personnel\')
            ON DUPLICATE KEY UPDATE notifications.id=notifications.id', [$type,$title,$message,$concern,$target,hash('sha256',$event),time(),$user]);
    }

    public function officialIds(): array
    {
        return $this->run("SELECT id FROM users WHERE active=1 AND role='official'")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function notifications(array $actor, int $before = 0): array
    {
        $rows = $this->run('SELECT n.id,n.type,n.title,n.message,n.related_concern_id,n.target_url,n.is_read,n.created_at,n.read_at,c.assigned_user_id
            FROM notifications n LEFT JOIN complaints c ON c.id=n.related_concern_id
            WHERE n.user_id=? AND (?=0 OR n.id<?) ORDER BY n.id DESC LIMIT 30', [$actor['id'],$before,$before])->fetchAll();
        foreach ($rows as &$row) {
            // A historical assignment message does not restore access after reassignment.
            if ($actor['role'] !== 'official' && $row['assigned_user_id'] !== $actor['id']) $row['target_url'] = 'concerns.php';
            unset($row['assigned_user_id']);
        }
        unset($row);
        return ['items' => $rows, 'unread' => (int)$this->run('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$actor['id']])->fetchColumn()];
    }

    public function markNotificationsRead(string $user, ?int $id): void
    {
        if ($id === null) $this->run('UPDATE notifications SET is_read=1,read_at=? WHERE user_id=? AND is_read=0', [time(),$user]);
        else {
            if (!$this->run('SELECT id FROM notifications WHERE id=? AND user_id=?', [$id,$user])->fetchColumn()) throw new DomainException('Notification unavailable.');
            $this->run('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,?) WHERE id=? AND user_id=?', [time(),$id,$user]);
        }
    }

    // Call inside the normal write transaction. One claim per cooldown, including concurrent requests.
    public function claimAlert(string $key, int $seconds): bool
    {
        $key = hash('sha256',$key);
        $last = $this->run('SELECT last_sent FROM feature_alerts WHERE alert_key=?', [$key])->fetchColumn();
        if ($last !== false && (int)$last > time()-$seconds) return false;
        $this->run('INSERT INTO feature_alerts (alert_key,last_sent) VALUES (?,?) ON DUPLICATE KEY UPDATE last_sent=VALUES(last_sent)', [$key,time()]);
        return true;
    }

    public function dueAssignments(int $until): array
    {
        return $this->run("SELECT id,assigned_user_id,due_at FROM complaints WHERE status IN ('Assigned','In Progress') AND due_at IS NOT NULL AND due_at<=? AND assigned_user_id IS NOT NULL", [$until])->fetchAll();
    }

    public function recordPublicAttempt(string $bucket, int $limit, int $window): bool
    {
        $this->run('DELETE FROM public_attempts WHERE attempted_at < ?', [time() - 3600]);
        $count = $this->run('SELECT COUNT(*) FROM public_attempts WHERE bucket=? AND attempted_at>?', [$bucket, time() - $window])->fetchColumn();
        if ((int)$count >= $limit) return false;
        $this->run('INSERT INTO public_attempts (bucket,attempted_at) VALUES (?,?)', [$bucket, time()]);
        return true;
    }

    public function insertTracking(string $id, string $hash): void
    {
        $this->run('INSERT INTO concern_tracking (complaint_id,token_hash) VALUES (?,?)', [$id, $hash]);
    }

    public function trackedConcern(string $id, string $hash): ?array
    {
        return $this->run('SELECT c.payload FROM complaints c JOIN concern_tracking t ON t.complaint_id=c.id WHERE c.id=? AND t.token_hash=?', [$id, $hash])->fetch() ?: null;
    }

    public function solutionRules(): array
    {
        return $this->run('SELECT category,concern_type,actions FROM solution_rules ORDER BY category,concern_type')->fetchAll();
    }

    public function saveSolutionRule(string $category, string $type, array $actions): void
    {
        $this->run('INSERT INTO solution_rules (category,concern_type,actions) VALUES (?,?,?) ON DUPLICATE KEY UPDATE actions=VALUES(actions)', [$category, $type, json_encode($actions, JSON_THROW_ON_ERROR)]);
    }

    public function deleteSolutionRule(string $category, string $type): void
    {
        $this->run('DELETE FROM solution_rules WHERE category=? AND concern_type=?', [$category, $type]);
    }

    public function updateAccountIdentity(string $id, string $name, string $email): void
    {
        $this->run('UPDATE users SET name=?,email=? WHERE id=?', [$name, $email, $id]);
    }
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
        $this->run('UPDATE users SET auth_version=auth_version+IF(active<>? OR role<>?,1,0),role=?,team=?,active=? WHERE id=?', [$active ? 1 : 0, $role, $role, $team, $active ? 1 : 0, $id]);
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
        return $this->run("SELECT id,email,auth_version FROM users WHERE email=? AND active=1 AND role IN ('official','personnel')", [$email])->fetch();
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
        $connection->exec(self::anonymousMigration());
        $connection->exec(self::insightsMigration());
    }

    public static function insightsMigration(): string
    {
        return file_get_contents(__DIR__ . '/migrations/20260921_staff_insights.sql');
    }

    public static function anonymousMigration(): string
    {
        // This is also supplied as an importable SQL migration for existing installs.
        return file_get_contents(__DIR__ . '/migrations/20260921_anonymous_concerns.sql');
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
    public function ageConcern(string $id, string $date): void
    {
        $statement = $this->connection->prepare("UPDATE complaints SET created_at=?,payload=JSON_SET(payload,'$.createdAt',?) WHERE id=?");
        $statement->execute([$date,$date,$id]);
    }

    public function elapseDeadlineSweep(): void
    {
        $statement = $this->connection->prepare('DELETE FROM feature_alerts WHERE alert_key=?');
        $statement->execute([hash('sha256','deadline-sweep')]);
    }
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
