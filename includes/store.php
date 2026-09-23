<?php
declare(strict_types=1);
require_once __DIR__ . '/domain.php';
require_once dirname(__DIR__) . '/database/database.php';
require_once __DIR__ . '/notifications.php';

final class ConflictException extends DomainException {}

final class ComplaintStore
{
    private MaintainProDatabase $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = new MaintainProDatabase($db ?? br_database());
    }

    private function transaction(callable $work): mixed
    {
        return $this->db->transaction($work);
    }

    public function needsSetup(): bool
    {
        return $this->db->userCount() === 0;
    }

    public function user(string $id): ?array
    {
        $user = $this->db->user($id);
        if (!$user) return null;
        $user['active'] = (bool)$user['active'];
        $user['must_change_password'] = (bool)$user['must_change_password'];
        return $user;
    }

    public function actor(string $id): ?array
    {
        $user = $this->user($id);
        return $user && $user['active'] && in_array($user['role'], ['official', 'personnel'], true) ? $user : null;
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
        return $this->db->users();
    }

    public function workloads(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->workloads();
    }

    public function navigationCounts(string $userId): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed staff account.');
        return $this->db->navigationCounts($actor);
    }

    public function recurrenceGroups(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->recurrenceGroups(date(DATE_ATOM, time() - (int)ConcernInsights::config()['recurrence_days'] * 86400));
    }

    public function relatedConcerns(string $officialId, string $id): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->recurrenceFor($id,date(DATE_ATOM,time() - (int)ConcernInsights::config()['recurrence_days'] * 86400));
    }

    public function notifications(string $userId, int $before = 0): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed staff account.');
        $this->sweepDeadlines();
        return $this->db->notifications($actor,max(0,$before));
    }

    public function readNotifications(string $userId, ?int $id): void
    {
        $this->transaction(function () use ($userId,$id) {
            $actor = $this->actor($userId);
            if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed staff account.');
            $this->db->markNotificationsRead($userId,$id);
        });
    }

    public function sweepDeadlines(): void
    {
        $this->transaction(fn() => (new ConcernNotifications($this->db))->deadlines());
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
            $this->db->insertUser($id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $team, date(DATE_ATOM));
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
            throw $e;
        }
        return $this->user($id);
    }

    public function setup(array $data): array
    {
        return $this->transaction(function () use ($data) {
            if (!$this->needsSetup()) throw new DomainException('The workspace is already set up. Sign in with your staff account.');
            return $this->insertUser($data, 'official');
        });
    }

    public function register(array $data): array
    {
        throw new DomainException('Resident accounts are no longer required. Use Report a Concern.');
    }

    public function createUser(string $officialId, array $data): array
    {
        return $this->transaction(function () use ($officialId, $data) {
            $this->authorizeOfficial($officialId);
            if (!in_array($data['role'] ?? '', ['official', 'personnel'], true)) throw new DomainException('Create an official or personnel account. Residents report anonymously.');
            $temporaryPassword = 'MP-' . bin2hex(random_bytes(10));
            $user = $this->insertUser(array_replace($data, ['password' => $temporaryPassword]), is_string($data['role'] ?? null) ? $data['role'] : '', is_string($data['team'] ?? null) ? $data['team'] : '');
            $this->db->requirePasswordChange($user['id']);
            return $this->user($user['id']) + ['temporary_password' => $temporaryPassword];
        });
    }

    public function changeTemporaryPassword(string $id, array $data): array
    {
        return $this->transaction(function () use ($id, $data) {
            $user = $this->actor($id);
            if (!$user || !$user['must_change_password']) throw new DomainException('This account does not require an initial password change.');
            $hash = $this->db->passwordHash($id);
            $current = $data['current_password'] ?? null;
            if (!is_string($current) || !password_verify($current, $hash)) throw new DomainException('Enter your current temporary password.');
            $password = self::password($data['password'] ?? '');
            if ($password !== ($data['confirm_password'] ?? null)) throw new DomainException('The passwords do not match.');
            if (password_verify($password, $hash)) throw new DomainException('Choose a password different from your temporary password.');
            $this->db->replacePassword($id, password_hash($password, PASSWORD_DEFAULT));
            $this->db->deleteUserResets($id);
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
            if ($role === 'resident' && $user['role'] !== 'resident') throw new DomainException('Resident accounts are retired. Choose a staff role or deactivate the account.');
            $team = $role === 'personnel' ? ($data['team'] ?? '') : '';
            if ($role === 'personnel' && !in_array($team, ComplaintWorkflow::TEAMS, true)) throw new DomainException('Choose a personnel team.');
            if (!isset($data['active']) || !in_array($data['active'], ['1', '0'], true)) throw new DomainException('Choose an account status.');
            $active = $data['active'] === '1';
            if ($id === $officialId && (!$active || $role !== 'official')) throw new DomainException('You cannot deactivate or remove official access from your own account.');
            $this->db->updateUser($id, $role, $team, $active);
            $name = self::name($data['name'] ?? $user['name']);
            $email = self::email($data['email'] ?? $user['email']);
            try { $this->db->updateAccountIdentity($id, $name, $email); }
            catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
                throw $e;
            }
            if ($email !== $user['email']) $this->db->deleteUserResets($id);
            if (!$active) {
                $this->db->deleteUserResets($id);
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
            $hash = $this->db->passwordHash($id);
            if (!is_string($data['current_password'] ?? null) || !password_verify($data['current_password'], $hash)) throw new DomainException('Enter your current password to save account changes.');
            $newPassword = $data['new_password'] ?? '';
            if ($newPassword !== '' && $newPassword !== ($data['confirm_new_password'] ?? null)) throw new DomainException('The new passwords do not match.');
            if ($newPassword !== '') $hash = password_hash(self::password($newPassword), PASSWORD_DEFAULT);
            try {
                $this->db->updateProfile($id, $name, $email, $hash, $newPassword !== '');
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
            $this->db->deleteOldLoginAttempts(time() - 900);
            $blocked = $this->db->loginAttemptCount($bucket) >= 5;
            if (!$blocked) {
                $this->db->recordLoginAttempt($bucket, time());
            }
        });
        if ($blocked) throw new DomainException('Too many sign-in attempts. Please try again in 15 minutes.');
        $row = $this->db->loginUser($email);
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $row['password_hash'] ?? $dummy);
        if (!$row || !$valid || !$row['active'] || !$this->actor($row['id'])) throw new DomainException('The email or password is incorrect, or staff access is unavailable. Residents can report without signing in.');
        $this->db->clearLoginAttempts($bucket);
        return $this->user($row['id']);
    }

    public function requestPasswordReset(mixed $email, string $client, callable $send): string
    {
        $email = self::email($email);
        $challenge = bin2hex(random_bytes(32));
        $code = (string)random_int(100000, 999999);
        $recipient = $this->transaction(function () use ($email, $client, $challenge, $code) {
            $now = time();
            $this->db->deleteOldResetRequests($now - 900);
            $this->db->deleteExpiredResets($now);
            $buckets = [hash('sha256', 'email:' . $email) => 3, hash('sha256', 'ip:' . $client) => 10];
            foreach ($buckets as $bucket => $limit) {
                $row = $this->db->resetRequestRate($bucket);
                if ((int)$row['total'] >= $limit || ($limit === 3 && (int)$row['latest'] > $now - 60)) {
                    throw new DomainException('Please wait at least 60 seconds between codes. After repeated requests, try again in 15 minutes.');
                }
            }
            foreach ($buckets as $bucket => $limit) {
                $this->db->recordResetRequest($bucket, $now);
            }
            $user = $this->db->resetUser($email);
            // Hash even for unknown addresses; the public response is identical.
            $hash = password_hash($code, PASSWORD_DEFAULT);
            if (!$user) return null;
            $this->db->deleteUserResets($user['id']);
            $this->db->insertReset($challenge, $user['id'], $user['email'], (int)$user['auth_version'], $hash, $now + 600);
            return $user['email'];
        });
        if ($recipient !== null) {
            try {
                $send($recipient, $code);
            } catch (Throwable $e) {
                $this->transaction(function () use ($challenge) {
                    $this->db->deleteReset($challenge);
                });
                // Do not log the message body, code, SMTP credentials, or recipient.
                error_log('MaintainPro: password reset email delivery failed. Check SMTP connectivity and sender credentials.');
                // Keep the same public response for existing and unknown accounts.
            }
        }
        return $challenge;
    }

    public function verifyPasswordReset(string $challenge, mixed $code): string
    {
        $token = $this->transaction(function () use ($challenge, $code) {
            $row = $this->db->resetRequest($challenge);
            if (!$row || $row['reset_token_hash'] !== null || (int)$row['expires_at'] <= time() || (int)$row['attempts'] >= 5) return null;
            $this->db->recordResetAttempt($challenge);
            if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/', $code) || !password_verify($code, $row['otp_hash'])) return null;
            $token = bin2hex(random_bytes(32));
            $this->db->grantPasswordReset($challenge, hash('sha256', $token), time() + 600);
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
            $row = $this->db->resetRequest($challenge);
            if (!$row || !$row['reset_token_hash'] || (int)$row['reset_expires_at'] <= time()
                || !hash_equals($row['reset_token_hash'], hash('sha256', $token))) {
                throw new DomainException('Verify a new email code before resetting your password.');
            }
            $this->db->replacePassword($row['user_id'], password_hash($password, PASSWORD_DEFAULT));
            $this->db->deleteUserResets($row['user_id']);
        });
    }

    public function state(): array
    {
        $cases = [];
        foreach ($this->db->complaints() as $row) {
            $c = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
            $c['version'] = (int)$row['version'];
            $cases[] = $c;
        }
        return ['nextId' => $this->db->nextComplaintId(), 'cases' => $cases];
    }

    public function mutate(string $userId, string $action, string $id, array $data, mixed $expectedVersion): string
    {
        return $this->transaction(function () use ($userId, $action, $id, $data, $expectedVersion) {
            $actor = $this->actor($userId);
            if (!$actor) throw new DomainException('Your account is inactive. Please sign in again.');
            if ($actor['must_change_password']) throw new DomainException('Change your temporary password before accessing concerns.');
            $state = $this->state();
            if ($action === 'submit') {
                throw new DomainException('Use the public Report a Concern page.');
            } else {
                $index = array_search($id, array_column($state['cases'], 'id'), true);
                if ($index === false || !ComplaintWorkflow::canSee($state['cases'][$index], $actor)) throw new DomainException('Concern not found or unavailable to your account.');
                if (!is_int($expectedVersion) || $expectedVersion !== $state['cases'][$index]['version']) throw new ConflictException('Another user updated this concern. Refresh the page to load the latest record before saving.');
                // Never trust internal assignee/suggestion fields supplied by the client.
                unset($data['_assignee'], $data['_suggestions']);
                if ($action === 'assign') {
                    $assignedId = $data['personnelId'] ?? '';
                    $assigned = is_string($assignedId) ? $this->user($assignedId) : null;
                    if (!$assigned || !$assigned['active'] || $assigned['role'] !== 'personnel' || !filter_var($assigned['email'], FILTER_VALIDATE_EMAIL)) throw new DomainException('Choose active personnel with a valid email address.');
                    $data['_assignee'] = $assigned;
                }
                if ($action === 'edit' && !empty($state['cases'][$index]['concernType'])) $data['_suggestions'] = $this->suggestions($data);
                $before = $state['cases'][$index];
                ComplaintWorkflow::apply($state, $actor, $id, $action, $data);
                $c = $state['cases'][$index];
                $c['version']++;
                $this->db->updateComplaint($c);
                (new ConcernNotifications($this->db))->changed($c,$before,$action);
            }
            return $id;
        });
    }

    public function publicLimit(string $purpose, string $client): void
    {
        $allowed = $this->transaction(fn() => $this->db->recordPublicAttempt(hash('sha256', $purpose . '|' . $client), $purpose === 'report' ? 5 : 40, $purpose === 'report' ? 3600 : 900));
        if (!$allowed) throw new DomainException('Too many requests. Please try again later.');
    }

    public function suggestions(array $data): array
    {
        [$category, $type, $points] = ConcernCatalog::selections($data);
        return ConcernCatalog::suggestions($category, $type, $points, $this->db->solutionRules());
    }

    public function submitGuest(array $data, string $client): array
    {
        $this->publicLimit('report', $client);
        if (($data['website'] ?? '') !== '') throw new DomainException('Unable to submit this report.');
        return $this->transaction(function () use ($data) {
            if ($this->needsSetup()) throw new DomainException('This workspace is not ready to receive concerns yet.');
            $data['_suggestions'] = $this->suggestions($data);
            $state = $this->state();
            $id = ComplaintWorkflow::submit($state, ['id' => null, 'role' => 'guest', 'name' => 'Anonymous resident'], $data);
            $case = $state['cases'][0];
            $case['version'] = 1;
            $this->db->setNextComplaintId($state['nextId']);
            $this->db->insertComplaint($case);
            (new ConcernNotifications($this->db))->changed($case,null,'submit');
            $token = bin2hex(random_bytes(24));
            $this->db->insertTracking($id, hash('sha256', $token));
            return ['reference' => $id, 'trackingCode' => $token];
        });
    }

    public function track(mixed $reference, mixed $token, string $client): array
    {
        $this->publicLimit('track', $client);
        if (!is_string($reference) || strlen($reference) > 64 || !is_string($token) || !preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/', $reference) || !preg_match('/\A[a-f0-9]{48}\z/', $token)) throw new DomainException('Reference or tracking code not found.');
        $row = $this->db->trackedConcern($reference, hash('sha256', $token));
        if (!$row) throw new DomainException('Reference or tracking code not found.');
        $c = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
        // Explicit allowlist: never return free-text notes, addresses, images or staff identities.
        $progress = [];
        foreach ($c['timeline'] as $event) {
            if (isset($event['workStatus']) && in_array($event['workStatus'], ConcernCatalog::WORK_STATUSES, true)) $progress[] = ['date' => $event['date'], 'status' => $event['workStatus']];
        }
        return ['reference' => $c['id'], 'category' => $c['category'], 'concernType' => $c['concernType'], 'status' => $c['status'] === 'Verified' ? 'Closed' : $c['status'], 'reportedAt' => $c['createdAt'], 'updatedAt' => $c['updatedAt'], 'progress' => $progress];
    }

    public function saveRule(string $officialId, array $data, bool $reset = false): void
    {
        $this->transaction(function () use ($officialId, $data, $reset) {
            $this->authorizeOfficial($officialId);
            [$category, $type] = ConcernCatalog::selections($data);
            if ($reset) { $this->db->deleteSolutionRule($category, $type); return; }
            $actions = [];
            foreach (['action1', 'action2', 'action3'] as $key) $actions[] = ComplaintWorkflow::text($data[$key] ?? '', 'Suggested action', 700);
            if (count(array_unique($actions)) !== 3) throw new DomainException('Provide three different suggested actions.');
            $this->db->saveSolutionRule($category, $type, $actions);
        });
    }
}
