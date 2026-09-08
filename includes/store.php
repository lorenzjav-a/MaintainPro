<?php
declare(strict_types=1);
require_once __DIR__ . '/domain.php';

final class ConflictException extends DomainException {}

final class ComplaintStore
{
    private PDO $db;

    public function __construct(?string $path = null)
    {
        $path ??= dirname(__DIR__) . '/.data/barangayresolve.sqlite';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $this->db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000');
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL, role TEXT NOT NULL CHECK(role IN ('resident','official','personnel')),
            team TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1,
            auth_version INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS complaints (
            id TEXT PRIMARY KEY, resident_id TEXT NOT NULL REFERENCES users(id),
            team TEXT NOT NULL, status TEXT NOT NULL, version INTEGER NOT NULL,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL, payload TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS complaints_resident ON complaints(resident_id);
        CREATE INDEX IF NOT EXISTS complaints_team ON complaints(team);
        CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL);
        INSERT OR IGNORE INTO settings(name,value) VALUES('next_id','1');
        CREATE TABLE IF NOT EXISTS login_attempts (bucket TEXT NOT NULL, attempted_at INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS attempts_bucket ON login_attempts(bucket,attempted_at);");
        $columns = $this->db->query('PRAGMA table_info(users)')->fetchAll();
        if (!in_array('auth_version', array_column($columns, 'name'), true)) {
            $this->db->exec('ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 1');
        }
        $this->db->exec('PRAGMA optimize');
    }

    private function transaction(callable $work): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $work();
            $this->db->exec('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function needsSetup(): bool
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    }

    public function user(string $id): ?array
    {
        $q = $this->db->prepare('SELECT id,name,email,role,team,active,auth_version,created_at FROM users WHERE id=?');
        $q->execute([$id]);
        $user = $q->fetch();
        if (!$user) return null;
        $user['active'] = (bool)$user['active'];
        return $user;
    }

    public function actor(string $id): ?array
    {
        $user = $this->user($id);
        return $user && $user['active'] ? $user : null;
    }

    private function authorizeOfficial(string $id): array
    {
        $actor = $this->actor($id);
        if (!$actor || $actor['role'] !== 'official') throw new DomainException('Only a barangay official can manage accounts.');
        return $actor;
    }

    public function users(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->query('SELECT id,name,email,role,team,active,created_at FROM users ORDER BY created_at DESC,name')->fetchAll();
    }

    private static function name(mixed $name): string
    {
        if (!is_string($name) || mb_strlen(trim($name)) < 2 || mb_strlen(trim($name)) > 100) throw new DomainException('Enter a full name between 2 and 100 characters.');
        return trim($name);
    }

    private static function email(mixed $email): string
    {
        if (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
        return strtolower(trim($email));
    }

    private static function password(mixed $password): string
    {
        if (!is_string($password) || trim($password) === '' || mb_strlen($password) < 10 || strlen($password) > 72) throw new DomainException('Use at least 10 characters and no more than 72 bytes for the password.');
        return $password;
    }

    private function insertUser(array $data, string $role, string $team = ''): array
    {
        $name = self::name($data['name'] ?? '');
        $email = self::email($data['email'] ?? '');
        $password = self::password($data['password'] ?? '');
        if (!in_array($role, ['resident', 'official', 'personnel'], true)) throw new DomainException('Choose a valid role.');
        if ($role === 'personnel' && !in_array($team, ComplaintDemo::TEAMS, true)) throw new DomainException('Choose a team for the personnel account.');
        if ($role !== 'personnel') $team = '';
        $id = 'user-' . bin2hex(random_bytes(12));
        try {
            $q = $this->db->prepare('INSERT INTO users(id,name,email,password_hash,role,team,created_at) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $team, date(DATE_ATOM)]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 19) throw new DomainException('That email address is already registered.');
            throw $e;
        }
        return $this->user($id);
    }

    public function setup(array $data): array
    {
        return $this->transaction(function () use ($data) {
            if (!$this->needsSetup()) throw new DomainException('The workspace is already set up. Sign in or register as a resident.');
            return $this->insertUser($data, 'official');
        });
    }

    public function register(array $data): array
    {
        return $this->transaction(function () use ($data) {
            if ($this->needsSetup()) throw new DomainException('Set up the first official account before resident registration.');
            // Public registration never accepts a client-supplied role or team.
            return $this->insertUser($data, 'resident');
        });
    }

    public function createUser(string $officialId, array $data): array
    {
        return $this->transaction(function () use ($officialId, $data) {
            $this->authorizeOfficial($officialId);
            return $this->insertUser($data, is_string($data['role'] ?? null) ? $data['role'] : '', is_string($data['team'] ?? null) ? $data['team'] : '');
        });
    }

    public function updateUser(string $officialId, string $id, array $data): void
    {
        $this->transaction(function () use ($officialId, $id, $data) {
            $this->authorizeOfficial($officialId);
            $user = $this->user($id);
            if (!$user) throw new DomainException('Account not found.');
            $role = $data['role'] ?? '';
            if (!in_array($role, ['resident', 'official', 'personnel'], true)) throw new DomainException('Choose a valid role.');
            $team = $role === 'personnel' ? ($data['team'] ?? '') : '';
            if ($role === 'personnel' && !in_array($team, ComplaintDemo::TEAMS, true)) throw new DomainException('Choose a personnel team.');
            if (!isset($data['active']) || !in_array($data['active'], ['1', '0'], true)) throw new DomainException('Choose an account status.');
            $active = $data['active'] === '1';
            if ($id === $officialId && (!$active || $role !== 'official')) throw new DomainException('You cannot deactivate or remove official access from your own account.');
            $q = $this->db->prepare('UPDATE users SET role=?,team=?,active=? WHERE id=?');
            $q->execute([$role, $team, $active ? 1 : 0, $id]);
        });
    }

    public function updateProfile(string $id, array $data): void
    {
        $this->transaction(function () use ($id, $data) {
            if (!$this->actor($id)) throw new DomainException('Sign in again to update your profile.');
            $name = self::name($data['name'] ?? '');
            $email = self::email($data['email'] ?? '');
            $q = $this->db->prepare('SELECT password_hash FROM users WHERE id=?');
            $q->execute([$id]);
            $hash = $q->fetchColumn();
            if (!is_string($data['current_password'] ?? null) || !password_verify($data['current_password'], $hash)) throw new DomainException('Enter your current password to save account changes.');
            $newPassword = $data['new_password'] ?? '';
            if ($newPassword !== '' && $newPassword !== ($data['confirm_new_password'] ?? null)) throw new DomainException('The new passwords do not match.');
            if ($newPassword !== '') $hash = password_hash(self::password($newPassword), PASSWORD_DEFAULT);
            try {
                $q = $this->db->prepare('UPDATE users SET name=?,email=?,password_hash=?,auth_version=auth_version+? WHERE id=?');
                $q->execute([$name, $email, $hash, $newPassword !== '' ? 1 : 0, $id]);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 19) throw new DomainException('That email address is already registered.');
                throw $e;
            }
        });
    }

    public function login(mixed $email, mixed $password, string $client): array
    {
        if (!is_string($email) || !is_string($password) || strlen($email) > 254 || strlen($password) > 1024) throw new DomainException('The email or password is incorrect.');
        $email = strtolower(trim($email));
        $bucket = hash('sha256', $email . '|' . $client);
        $blocked = false;
        $this->transaction(function () use ($bucket, &$blocked) {
            $q = $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
            $q->execute([time() - 900]);
            $q = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE bucket=?');
            $q->execute([$bucket]);
            $blocked = (int)$q->fetchColumn() >= 5;
            if (!$blocked) {
                $q = $this->db->prepare('INSERT INTO login_attempts(bucket,attempted_at) VALUES(?,?)');
                $q->execute([$bucket, time()]);
            }
        });
        if ($blocked) throw new DomainException('Too many sign-in attempts. Please try again in 15 minutes.');
        $q = $this->db->prepare('SELECT id,password_hash,active FROM users WHERE email=?');
        $q->execute([$email]);
        $row = $q->fetch();
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $row['password_hash'] ?? $dummy);
        if (!$row || !$valid || !$row['active']) throw new DomainException('The email or password is incorrect, or the account is inactive.');
        $q = $this->db->prepare('DELETE FROM login_attempts WHERE bucket=?');
        $q->execute([$bucket]);
        return $this->user($row['id']);
    }

    public function state(): array
    {
        $cases = [];
        foreach ($this->db->query('SELECT payload,version FROM complaints ORDER BY created_at DESC,id DESC') as $row) {
            $c = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
            $c['version'] = (int)$row['version'];
            $cases[] = $c;
        }
        return ['nextId' => (int)$this->db->query("SELECT value FROM settings WHERE name='next_id'")->fetchColumn(), 'cases' => $cases];
    }

    public function mutate(string $userId, string $action, string $id, array $data, mixed $expectedVersion): string
    {
        return $this->transaction(function () use ($userId, $action, $id, $data, $expectedVersion) {
            $actor = $this->actor($userId);
            if (!$actor) throw new DomainException('Your account is inactive. Please sign in again.');
            $state = $this->state();
            if ($action === 'submit') {
                $id = ComplaintDemo::submit($state, $actor, $data);
                $c = $state['cases'][0];
                $c['version'] = 1;
                $q = $this->db->prepare('UPDATE settings SET value=? WHERE name=?');
                $q->execute([(string)$state['nextId'], 'next_id']);
                $q = $this->db->prepare('INSERT INTO complaints(id,resident_id,team,status,version,created_at,updated_at,payload) VALUES(?,?,?,?,?,?,?,?)');
                $q->execute([$id, $c['residentId'], $c['team'], $c['status'], 1, $c['createdAt'], $c['updatedAt'], json_encode($c, JSON_THROW_ON_ERROR)]);
            } else {
                $index = array_search($id, array_column($state['cases'], 'id'), true);
                if ($index === false || !ComplaintDemo::canSee($state['cases'][$index], $actor)) throw new DomainException('Complaint not found or unavailable to your account.');
                if (!is_int($expectedVersion) || $expectedVersion !== $state['cases'][$index]['version']) throw new ConflictException('Another user updated this complaint. Close and reopen it to load the latest record before saving.');
                ComplaintDemo::apply($state, $actor, $id, $action, $data);
                $c = $state['cases'][$index];
                $c['version']++;
                $q = $this->db->prepare('UPDATE complaints SET team=?,status=?,version=?,updated_at=?,payload=? WHERE id=?');
                $q->execute([$c['team'], $c['status'], $c['version'], $c['updatedAt'], json_encode($c, JSON_THROW_ON_ERROR), $id]);
            }
            return $id;
        });
    }
}
