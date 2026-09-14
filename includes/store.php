<?php
declare(strict_types=1);
require_once __DIR__ . '/domain.php';
require_once dirname(__DIR__) . '/database/database.php';

final class ConflictException extends DomainException {}

final class ComplaintStore
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? br_database();
    }

    private function transaction(callable $work): mixed
    {
        $this->db->beginTransaction();
        try {
            // Lock before any reads so simultaneous writes see the latest committed state.
            // This protects setup, account permissions, complaint IDs and version checks.
            $lock = $this->db->query("SELECT value FROM settings WHERE name='next_id' FOR UPDATE");
            if ($lock->fetchColumn() === false) throw new RuntimeException('Run database/setup.php to initialize storage.');
            $lock->closeCursor();
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function needsSetup(): bool
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    }

    public function user(string $id): ?array
    {
        $q = $this->db->prepare('SELECT id,name,email,role,team,active,auth_version,must_change_password,created_at FROM users WHERE id=?');
        $q->execute([$id]);
        $user = $q->fetch();
        if (!$user) return null;
        $user['active'] = (bool)$user['active'];
        $user['must_change_password'] = (bool)$user['must_change_password'];
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
        if (!$actor || $actor['must_change_password'] || $actor['role'] !== 'official') throw new DomainException('Only a barangay official with a completed account can manage accounts.');
        return $actor;
    }

    public function users(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->query('SELECT id,name,email,role,team,active,must_change_password,created_at FROM users ORDER BY created_at DESC,name')->fetchAll();
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
        if ($role === 'personnel' && !in_array($team, ComplaintWorkflow::TEAMS, true)) throw new DomainException('Choose a team for the personnel account.');
        if ($role !== 'personnel') $team = '';
        $id = 'user-' . bin2hex(random_bytes(12));
        try {
            $q = $this->db->prepare('INSERT INTO users(id,name,email,password_hash,role,team,created_at) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $team, date(DATE_ATOM)]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
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
            $temporaryPassword = 'MP-' . bin2hex(random_bytes(10));
            $user = $this->insertUser(array_replace($data, ['password' => $temporaryPassword]), is_string($data['role'] ?? null) ? $data['role'] : '', is_string($data['team'] ?? null) ? $data['team'] : '');
            $q = $this->db->prepare('UPDATE users SET must_change_password=1 WHERE id=?');
            $q->execute([$user['id']]);
            return $this->user($user['id']) + ['temporary_password' => $temporaryPassword];
        });
    }

    public function changeTemporaryPassword(string $id, array $data): array
    {
        return $this->transaction(function () use ($id, $data) {
            $user = $this->actor($id);
            if (!$user || !$user['must_change_password']) throw new DomainException('This account does not require an initial password change.');
            $q = $this->db->prepare('SELECT password_hash FROM users WHERE id=?');
            $q->execute([$id]);
            $hash = $q->fetchColumn();
            $current = $data['current_password'] ?? null;
            if (!is_string($current) || !password_verify($current, $hash)) throw new DomainException('Enter your current temporary password.');
            $password = self::password($data['password'] ?? '');
            if ($password !== ($data['confirm_password'] ?? null)) throw new DomainException('The passwords do not match.');
            if (password_verify($password, $hash)) throw new DomainException('Choose a password different from your temporary password.');
            $q = $this->db->prepare('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?');
            $q->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $q = $this->db->prepare('DELETE FROM password_resets WHERE user_id=?');
            $q->execute([$id]);
            return $this->user($id);
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
            if ($role === 'personnel' && !in_array($team, ComplaintWorkflow::TEAMS, true)) throw new DomainException('Choose a personnel team.');
            if (!isset($data['active']) || !in_array($data['active'], ['1', '0'], true)) throw new DomainException('Choose an account status.');
            $active = $data['active'] === '1';
            if ($id === $officialId && (!$active || $role !== 'official')) throw new DomainException('You cannot deactivate or remove official access from your own account.');
            $q = $this->db->prepare('UPDATE users SET role=?,team=?,active=? WHERE id=?');
            $q->execute([$role, $team, $active ? 1 : 0, $id]);
            if (!$active) {
                $q = $this->db->prepare('DELETE FROM password_resets WHERE user_id=?');
                $q->execute([$id]);
            }
        });
    }

    public function updateProfile(string $id, array $data): void
    {
        $this->transaction(function () use ($id, $data) {
            $actor = $this->actor($id);
            if (!$actor || $actor['must_change_password']) throw new DomainException('Complete your initial password change before updating your profile.');
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
                if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
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

    public function requestPasswordReset(mixed $email, string $client, callable $send): string
    {
        $email = self::email($email);
        $challenge = bin2hex(random_bytes(32));
        $code = (string)random_int(100000, 999999);
        $recipient = $this->transaction(function () use ($email, $client, $challenge, $code) {
            $now = time();
            $q = $this->db->prepare('DELETE FROM password_reset_requests WHERE requested_at < ?');
            $q->execute([$now - 900]);
            $q = $this->db->prepare('DELETE FROM password_resets WHERE expires_at < ? AND (reset_expires_at IS NULL OR reset_expires_at < ?)');
            $q->execute([$now, $now]);
            $buckets = [hash('sha256', 'email:' . $email) => 3, hash('sha256', 'ip:' . $client) => 10];
            foreach ($buckets as $bucket => $limit) {
                $q = $this->db->prepare('SELECT COUNT(*) AS total, MAX(requested_at) AS latest FROM password_reset_requests WHERE bucket=?');
                $q->execute([$bucket]);
                $row = $q->fetch();
                if ((int)$row['total'] >= $limit || ($limit === 3 && (int)$row['latest'] > $now - 60)) {
                    throw new DomainException('Please wait at least 60 seconds between codes. After repeated requests, try again in 15 minutes.');
                }
            }
            foreach ($buckets as $bucket => $limit) {
                $q = $this->db->prepare('INSERT INTO password_reset_requests(bucket,requested_at) VALUES(?,?)');
                $q->execute([$bucket, $now]);
            }
            $q = $this->db->prepare('SELECT id,email,auth_version FROM users WHERE email=? AND active=1');
            $q->execute([$email]);
            $user = $q->fetch();
            // Hash even for unknown addresses; the public response is identical.
            $hash = password_hash($code, PASSWORD_DEFAULT);
            if (!$user) return null;
            $q = $this->db->prepare('DELETE FROM password_resets WHERE user_id=?');
            $q->execute([$user['id']]);
            $q = $this->db->prepare('INSERT INTO password_resets(id,user_id,email,auth_version,otp_hash,expires_at) VALUES(?,?,?,?,?,?)');
            $q->execute([$challenge, $user['id'], $user['email'], $user['auth_version'], $hash, $now + 600]);
            return $user['email'];
        });
        if ($recipient !== null) {
            try {
                $send($recipient, $code);
            } catch (Throwable $e) {
                $this->transaction(function () use ($challenge) {
                    $q = $this->db->prepare('DELETE FROM password_resets WHERE id=?');
                    $q->execute([$challenge]);
                });
                // Do not log the message body, code, SMTP credentials, or recipient.
                error_log('MaintainPro: password reset email delivery failed. Check SMTP connectivity and sender credentials.');
                // Keep the same public response for existing and unknown accounts.
            }
        }
        return $challenge;
    }

    private function resetRequest(string $challenge): array|false
    {
        $q = $this->db->prepare('SELECT r.* FROM password_resets r JOIN users u ON u.id=r.user_id
            WHERE r.id=? AND u.active=1 AND r.auth_version=u.auth_version AND r.email=u.email');
        $q->execute([$challenge]);
        return $q->fetch();
    }

    public function verifyPasswordReset(string $challenge, mixed $code): string
    {
        $token = $this->transaction(function () use ($challenge, $code) {
            $row = $this->resetRequest($challenge);
            if (!$row || $row['reset_token_hash'] !== null || (int)$row['expires_at'] <= time() || (int)$row['attempts'] >= 5) return null;
            $q = $this->db->prepare('UPDATE password_resets SET attempts=attempts+1 WHERE id=?');
            $q->execute([$challenge]);
            if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/', $code) || !password_verify($code, $row['otp_hash'])) return null;
            $token = bin2hex(random_bytes(32));
            $q = $this->db->prepare('UPDATE password_resets SET reset_token_hash=?,reset_expires_at=?,otp_hash=? WHERE id=?');
            $q->execute([hash('sha256', $token), time() + 600, '', $challenge]);
            return $token;
        });
        // Reject after commit so failed attempts cannot be rolled back.
        if ($token === null) throw new DomainException('The code is incorrect, expired, or no longer available. Request a new code if needed.');
        return $token;
    }

    public function resetPassword(string $challenge, string $token, array $data): void
    {
        $password = self::password($data['password'] ?? '');
        if ($password !== ($data['confirm_password'] ?? null)) throw new DomainException('The passwords do not match.');
        $this->transaction(function () use ($challenge, $token, $password) {
            $row = $this->resetRequest($challenge);
            if (!$row || !$row['reset_token_hash'] || (int)$row['reset_expires_at'] <= time()
                || !hash_equals($row['reset_token_hash'], hash('sha256', $token))) {
                throw new DomainException('Verify a new email code before resetting your password.');
            }
            $q = $this->db->prepare('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?');
            $q->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            $q = $this->db->prepare('DELETE FROM password_resets WHERE user_id=?');
            $q->execute([$row['user_id']]);
        });
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
            if ($actor['must_change_password']) throw new DomainException('Change your temporary password before accessing complaints.');
            $state = $this->state();
            if ($action === 'submit') {
                $id = ComplaintWorkflow::submit($state, $actor, $data);
                $c = $state['cases'][0];
                $c['version'] = 1;
                $q = $this->db->prepare('UPDATE settings SET value=? WHERE name=?');
                $q->execute([(string)$state['nextId'], 'next_id']);
                $q = $this->db->prepare('INSERT INTO complaints(id,resident_id,team,status,version,created_at,updated_at,payload) VALUES(?,?,?,?,?,?,?,?)');
                $q->execute([$id, $c['residentId'], $c['team'], $c['status'], 1, $c['createdAt'], $c['updatedAt'], json_encode($c, JSON_THROW_ON_ERROR)]);
            } else {
                $index = array_search($id, array_column($state['cases'], 'id'), true);
                if ($index === false || !ComplaintWorkflow::canSee($state['cases'][$index], $actor)) throw new DomainException('Complaint not found or unavailable to your account.');
                if (!is_int($expectedVersion) || $expectedVersion !== $state['cases'][$index]['version']) throw new ConflictException('Another user updated this complaint. Close and reopen it to load the latest record before saving.');
                ComplaintWorkflow::apply($state, $actor, $id, $action, $data);
                $c = $state['cases'][$index];
                $c['version']++;
                $q = $this->db->prepare('UPDATE complaints SET team=?,status=?,version=?,updated_at=?,payload=? WHERE id=?');
                $q->execute([$c['team'], $c['status'], $c['version'], $c['updatedAt'], json_encode($c, JSON_THROW_ON_ERROR), $id]);
            }
            return $id;
        });
    }
}
