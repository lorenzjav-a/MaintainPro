<?php
declare(strict_types=1);
require_once __DIR__ . '/domain.php';
require_once dirname(__DIR__) . '/database/database.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/evidence-storage.php';
require_once __DIR__ . '/profile-photo-storage.php';
require_once __DIR__ . '/planning.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/reporting.php';

final class ConflictException extends DomainException {}

final class ComplaintStore
{
    use ConcernPlanning;
    use ConcernMessaging;
    private MaintainProDatabase $db;
    private array $pendingEvidence = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = new MaintainProDatabase($db ?? br_database());
    }

    private function transaction(callable $work): mixed
    {
        $this->pendingEvidence = [];
        try {
            $result = $this->db->transaction($work);
            $this->pendingEvidence = [];
            return $result;
        } catch (Throwable $error) {
            foreach ($this->pendingEvidence as $path) EvidenceStorage::delete($path);
            $this->pendingEvidence = [];
            throw $error;
        }
    }

    private static function decodeConcern(array $row): array
    {
        $c = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
        $c['version'] = (int)$row['version'];
        return $c;
    }

    private function concern(string $id, bool $forUpdate = false): ?array
    {
        $row = $this->db->complaint($id, $forUpdate);
        return $row ? self::decodeConcern($row) : null;
    }

    private function managedLocation(array $data, bool $allowInactive = false): ?array
    {
        $id = $data['locationId'] ?? $data['purokId'] ?? null;
        if ((is_int($id) && $id > 0) || (is_string($id) && preg_match('/\A[1-9][0-9]{0,9}\z/', $id))) {
            $location = $this->db->location((int)$id);
            if ($location && ($allowInactive || (bool)$location['active'])) return $location;
            throw new DomainException('Choose an available Purok / Sitio.');
        }
        if ($id !== null && $id !== '') throw new DomainException('Choose an available Purok / Sitio.');
        $locations=$this->db->locations($allowInactive);
        $name=ComplaintWorkflow::text($data['purok'] ?? '', 'Purok / Sitio',120);
        foreach ($locations as $location) if (mb_strtolower($location['name'])===mb_strtolower($name)) return $location;
        // Existing installations retain free-text reporting until an official configures locations.
        if (!$this->db->locations(true)) return null;
        throw new DomainException('Choose an available Purok / Sitio.');
    }

    private function persistLatestEvidence(array &$c, ?string $uploadedBy, string $originalFilename = ''): void
    {
        $last = array_key_last($c['timeline']);
        if ($last === null || empty($c['timeline'][$last]['photo'])) return;
        $event = &$c['timeline'][$last];
        $metadata = EvidenceStorage::storeDataUri($event['photo'], $originalFilename);
        $this->pendingEvidence[] = $metadata['file_path'];
        $type = match ($event['evidenceType'] ?? '') {
            'Initial Evidence' => 'resident_report',
            'Resident Follow-up' => 'resident_followup',
            'Completion Evidence' => 'after',
            'Inspection Evidence' => 'before',
            default => 'during',
        };
        try {
            $this->db->insertEvidence($metadata['id'], $c['id'], $uploadedBy, $type, $metadata['file_path'],
                $metadata['original_filename'], $metadata['mime_type'], $metadata['file_size'], $metadata['width'], $metadata['height'], time());
        } catch (Throwable $error) {
            EvidenceStorage::delete($metadata['file_path']);
            throw $error;
        }
        $event['evidenceId'] = $metadata['id'];
        $event['photo'] = '';
        if (($c['photo'] ?? '') !== '' && ($event['evidenceType'] ?? '') === 'Initial Evidence') {
            $c['photo'] = '';
            $c['initialEvidenceId'] = $metadata['id'];
        }
        if (!empty($c['resolution']['photo']) && ($event['evidenceType'] ?? '') === 'Completion Evidence') {
            $c['resolution']['photo'] = '';
            $c['resolution']['evidenceId'] = $metadata['id'];
        }
        unset($event);
    }

    public function needsSetup(): bool
    {
        return $this->db->setupAvailable();
    }

    public function user(string $id): ?array
    {
        $user = $this->db->user($id);
        if (!$user) return null;
        $user['active'] = (bool)$user['active'];
        $user['email_verified'] = (bool)$user['email_verified'];
        $user['is_system_admin'] = (bool)$user['is_system_admin'];
        $user['must_change_password'] = (bool)$user['must_change_password'];
        $user['deactivated_at'] = $user['deactivated_at'] === null ? null : (int)$user['deactivated_at'];
        $user['deactivation_sequence'] = (int)$user['deactivation_sequence'];
        foreach (['invitation_created_at','invitation_expires_at','invitation_sent_at'] as $field) $user[$field] = $user[$field] === null ? null : (int)$user[$field];
        $user['pending_setup'] = $user['active'] && !$user['email_verified'] && !$user['must_change_password'] && $user['invitation_created_at'] !== null && in_array($user['role'],['resident','official','personnel'],true);
        return $user;
    }

    public function actor(string $id): ?array
    {
        $user = $this->user($id);
        return $user && $user['active'] && $user['email_verified'] && in_array($user['role'], ['resident', 'official', 'personnel'], true) ? $user : null;
    }

    public function confirmPassword(string $id, mixed $password): void
    {
        $actor=$this->actor($id);
        $hash=$actor ? $this->db->passwordHash($id) : false;
        if (!is_string($password) || !is_string($hash) || !password_verify($password,$hash)) throw new DomainException('Enter your current password to continue.');
    }

    public function profilePhoto(string $id): ?array
    {
        $actor = $this->actor($id);
        if (!$actor || $actor['must_change_password']) return null;
        $photo = $this->db->profilePhoto($id);
        return is_array($photo) ? $photo : null;
    }

    private function authorizeOfficial(string $id): array
    {
        $actor = $this->actor($id);
        if (!$actor || $actor['must_change_password'] || $actor['role'] !== 'official') throw new DomainException('Only a barangay official with a completed account can manage accounts.');
        return $actor;
    }

    private function authorizeAdmin(string $id): array
    {
        $actor = $this->authorizeOfficial($id);
        if (!$actor['is_system_admin']) throw new DomainException('System administrator access is required.');
        return $actor;
    }

    public function users(string $officialId): array
    {
        $this->authorizeAdmin($officialId);
        return array_map(function (array $user): array {
            $user['active'] = (bool)$user['active'];
            $user['email_verified'] = (bool)$user['email_verified'];
            $user['is_system_admin'] = (bool)$user['is_system_admin'];
            $user['must_change_password'] = (bool)$user['must_change_password'];
            $user['deactivated_at'] = $user['deactivated_at'] === null ? null : (int)$user['deactivated_at'];
            $user['deactivation_sequence'] = (int)$user['deactivation_sequence'];
            foreach (['invitation_created_at','invitation_expires_at','invitation_sent_at'] as $field) $user[$field] = $user[$field] === null ? null : (int)$user[$field];
            $user['pending_setup'] = $user['active'] && !$user['email_verified'] && !$user['must_change_password'] && $user['invitation_created_at'] !== null && in_array($user['role'],['resident','official','personnel'],true);
            return $user;
        }, $this->db->users());
    }

    public function workloads(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->workloads();
    }

    public function locations(?string $officialId = null): array
    {
        if ($officialId !== null) $this->authorizeOfficial($officialId);
        return $this->db->locations($officialId !== null);
    }

    public function hasLocations(): bool
    {
        return (bool)$this->db->locations(true);
    }

    public function createLocation(string $officialId, array $data): void
    {
        $this->transaction(function () use ($officialId, $data) {
            $actor = $this->authorizeAdmin($officialId);
            $name = ComplaintWorkflow::text($data['name'] ?? '', 'Purok / Sitio name', 120);
            $sort = filter_var($data['sortOrder'] ?? 0, FILTER_VALIDATE_INT);
            if ($sort === false || $sort < 0 || $sort > 10000) throw new DomainException('Use a valid display order.');
            try {
                $id = $this->db->insertLocation($name, (int)$sort, time());
            } catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0) === 1062) throw new DomainException('That Purok / Sitio already exists.');
                throw $error;
            }
            $this->db->recordAudit($actor, 'location_created', 'location', (string)$id, $name, ['sortOrder' => (int)$sort]);
        });
    }

    public function updateLocation(string $officialId, int $id, array $data, bool $toggle = false): void
    {
        $this->transaction(function () use ($officialId, $id, $data, $toggle) {
            $actor = $this->authorizeAdmin($officialId);
            $current = $this->db->location($id);
            if (!$current) throw new DomainException('Location not found.');
            $name = $toggle ? $current['name'] : ComplaintWorkflow::text($data['name'] ?? '', 'Purok / Sitio name', 120);
            $sort = $toggle ? (int)$current['sort_order'] : filter_var($data['sortOrder'] ?? $current['sort_order'], FILTER_VALIDATE_INT);
            if ($sort === false || $sort < 0 || $sort > 10000) throw new DomainException('Use a valid display order.');
            $active = $toggle ? !(bool)$current['active'] : (bool)$current['active'];
            try {
                $this->db->updateLocation($id, $name, (int)$sort, $active, time());
            } catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0) === 1062) throw new DomainException('That Purok / Sitio already exists.');
                throw $error;
            }
            $action = $toggle ? ($active ? 'location_activated' : 'location_deactivated') : 'location_updated';
            $this->db->recordAudit($actor, $action, 'location', (string)$id, $name,
                ['previousName' => $current['name'], 'sortOrder' => (int)$sort]);
        });
    }

    public function deleteLocation(string $officialId, int $id): void
    {
        $this->transaction(function () use ($officialId, $id) {
            $actor = $this->authorizeAdmin($officialId);
            $current = $this->db->location($id);
            if (!$current) throw new DomainException('Location not found.');
            $this->db->deleteLocation($id);
            $this->db->recordAudit($actor, 'location_deleted', 'location', (string)$id, $current['name'],
                ['sortOrder' => (int)$current['sort_order']]);
        });
    }

    /** @return array{deletedUploads:int,failedUploads:int} */
    public function factoryReset(string $officialId, array $data): array
    {
        $actor=$this->authorizeAdmin($officialId);
        $this->confirmPassword($officialId,$data['current_password'] ?? null);
        if (!is_string($data['confirmation'] ?? null) || !hash_equals('RESET MAINTAINPRO',trim($data['confirmation']))) {
            throw new DomainException('Type RESET MAINTAINPRO exactly to confirm the factory reset.');
        }
        $setupKey=getenv('APP_SETUP_KEY');
        if (!is_string($setupKey) || strlen($setupKey)<32) {
            throw new DomainException('Configure APP_SETUP_KEY with at least 32 characters on the server before resetting. It is required to create the new first administrator.');
        }
        $this->db->factoryReset();
        $evidence=EvidenceStorage::purge();
        $profiles=ProfilePhotoStorage::purge();
        $failed=$evidence['failed']+$profiles['failed'];
        br_security_log('factory_reset_completed',['actor_role'=>$actor['role'],'result'=>$failed===0?'success':'upload_cleanup_incomplete']);
        return ['deletedUploads'=>$evidence['deleted']+$profiles['deleted'],'failedUploads'=>$failed];
    }

    public function blockedConcerns(string $officialId, int $page = 1, int $perPage = 20): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->blockedConcerns(max(1, $page), min(50, max(1, $perPage)));
    }

    public function auditLogs(string $officialId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $this->authorizeAdmin($officialId);
        $allowed = ['date' => '', 'action' => '', 'user' => ''];
        foreach ($allowed as $key => $default) {
            $value = $filters[$key] ?? $default;
            $allowed[$key] = is_string($value) ? mb_substr(trim($value), 0, 120) : '';
        }
        if ($allowed['date'] !== '' && !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $allowed['date'])) throw new DomainException('Choose a valid audit date.');
        return $this->db->auditLogs($allowed, max(1, $page), min(50, max(1, $perPage)));
    }

    public function concernLinks(string $userId, string $id): array
    {
        $actor = $this->authorizeOfficial($userId);
        $c = $this->concern($id);
        if (!$c || !ComplaintWorkflow::canSee($c, $actor)) throw new DomainException('Concern not found or unavailable to your account.');
        return $this->db->concernLinks($id);
    }

    public function evidenceRecord(string $userId, string $evidenceId): ?array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) return null;
        return $this->db->evidenceForActor($evidenceId, $actor);
    }

    public function legacyEvidence(string $userId, string $evidenceId): ?string
    {
        $actor=$this->actor($userId);
        if (!$actor || $actor['must_change_password']) return null;
        $row=$this->db->legacyEvidenceConcern($evidenceId,$actor);
        if (!$row) return null;
        $c=self::decodeConcern($row);
        foreach ($c['timeline'] as $event) if (($event['evidenceId'] ?? '')===$evidenceId && !empty($event['photo'])) return $event['photo'];
        return null;
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

    public function sweepDeactivatedAccounts(?int $now = null): int
    {
        $now ??= time();
        if ($now < 7 * 86400) throw new InvalidArgumentException('Use a valid notification sweep time.');
        return $this->transaction(function () use ($now): int {
            $accounts = $this->db->deactivatedAccountsDue($now - 7 * 86400);
            foreach ($accounts as $account) {
                $days = intdiv(max(0, $now - (int)$account['deactivated_at']), 86400);
                $this->db->createDeactivationReviewNotifications($account, $days, $now);
            }
            return count($accounts);
        });
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

    private function insertUser(array $data, string $role, string $team = '', bool $isAdmin = false): array
    {
        $name = self::name($data['name'] ?? '');
        $email = self::email($data['email'] ?? '');
        $password = self::password($data['password'] ?? '');
        if (!in_array($role, ['resident', 'official', 'personnel'], true)) throw new DomainException('Choose a valid role.');
        if ($role === 'personnel' && !in_array($team, ComplaintWorkflow::TEAMS, true)) throw new DomainException('Choose a team for the personnel account.');
        if ($role !== 'personnel') $team = '';
        $id = 'user-' . bin2hex(random_bytes(12));
        try {
            $this->db->insertUser($id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $team, date(DATE_ATOM), $isAdmin);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
            throw $e;
        }
        return $this->user($id);
    }

    public function setup(array $data, string $client = ''): array
    {
        if (PHP_SAPI !== 'cli') $this->publicLimit('setup', $client);
        return $this->transaction(function () use ($data, $client) {
            if (!$this->needsSetup()) throw new DomainException('The workspace is already set up. Sign in with your staff account.');
            $setupKey = getenv('APP_SETUP_KEY');
            $provided = is_string($data['setup_key'] ?? null) ? $data['setup_key'] : '';
            if (PHP_SAPI !== 'cli' && (!is_string($setupKey) || strlen($setupKey) < 32 || !hash_equals($setupKey, $provided))) {
                throw new DomainException('Initial browser setup requires the configured installation key. Contact the server administrator.');
            }
            $user = $this->insertUser($data, 'official', '', true);
            $this->db->completeSetup();
            $this->db->recordAudit($user, 'official_account_created', 'user', $user['id'], $user['name'], ['initialSetup' => true]);
            return $user;
        });
    }

    public function register(array $data): array
    {
        if (PHP_SAPI !== 'cli') throw new DomainException('Use email verification to create a resident account.');
        return $this->transaction(function () use ($data) {
            if ($this->needsSetup()) throw new DomainException('The barangay must finish workspace setup first.');
            return $this->insertUser($data, 'resident');
        });
    }

    public function assignedWorkCounts(string $officialId, string $userId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->assignedWorkCounts($userId);
    }

    public function requestRegistration(array $data, string $client, callable $send): string
    {
        $name=self::name($data['name'] ?? '');
        $email=self::email($data['email'] ?? '');
        $password=self::password($data['password'] ?? '');
        $challenge=bin2hex(random_bytes(32));
        $code=(string)random_int(100000,999999);
        $recipient=$this->transaction(function() use($name,$email,$password,$client,$challenge,$code) {
            if ($this->needsSetup()) throw new DomainException('The barangay must finish workspace setup first.');
            $now=time();
            $bucket=hash('sha256','registration-ip:'.$client);
            $this->db->removeOldRegistrationAttempts($now-3600);
            if ($this->db->registrationAttempts($bucket,$now-3600)>=20) throw new DomainException('Too many registration attempts from this connection. Try again in an hour.');
            $this->db->recordRegistrationAttempt($bucket,$now);
            $existing=$this->db->registrationUser($email);
            if ($existing && ((int)$existing['email_verified'] || $existing['role']!=='resident' || !(int)$existing['active']
                || !password_verify($password,$existing['password_hash']))) return null;
            $emailBucket=hash('sha256','registration-email:'.$email);
            if ($this->db->registrationAttempts($emailBucket,$now-900)>=3 || $now-$this->db->latestRegistrationAttempt($emailBucket)<60) {
                throw new DomainException('Wait before requesting another code. You can request up to three codes per email in 15 minutes.');
            }
            $this->db->recordRegistrationAttempt($emailBucket,$now);
            $id=$existing['id'] ?? 'user-'.bin2hex(random_bytes(12));
            if ($existing) $this->db->removeRegistrationChallengeForUser($id);
            else {
                try { $this->db->insertPendingResident($id,$name,$email,password_hash($password,PASSWORD_DEFAULT),date(DATE_ATOM)); }
                catch (PDOException $e) {
                    if ((int)($e->errorInfo[1] ?? 0)===1062) return null;
                    throw $e;
                }
            }
            $this->db->createRegistrationChallenge($challenge,$id,password_hash($code,PASSWORD_DEFAULT),$now+600,$now);
            return $email;
        });
        if ($recipient!==null) {
            try { $send($recipient,$code); }
            catch (Throwable) { error_log('MaintainPro: registration verification email delivery failed.'); }
        }
        return $challenge;
    }

    public function resendRegistration(string $challenge, string $client, callable $send): void
    {
        $code=(string)random_int(100000,999999);
        $recipient=$this->transaction(function() use($challenge,$client,$code) {
            $row=$this->db->registrationChallenge($challenge,true);
            if (!$row || (int)$row['email_verified'] || !(int)$row['active'] || $row['role']!=='resident') return null;
            $now=time();
            $bucket=hash('sha256','registration-resend:'.$client);
            $this->db->removeOldRegistrationAttempts($now-3600);
            if ($now-(int)$row['sent_at']<60 || $this->db->registrationAttempts($bucket,$now-900)>=10
                || $this->db->registrationAttempts(hash('sha256','registration-email:'.$row['email']),$now-900)>=3) {
                throw new DomainException('Wait before requesting another code. You can request up to three codes per email in 15 minutes.');
            }
            foreach ([$bucket,hash('sha256','registration-email:'.$row['email'])] as $key) $this->db->recordRegistrationAttempt($key,$now);
            $this->db->replaceRegistrationCode($challenge,password_hash($code,PASSWORD_DEFAULT),$now+600,$now);
            return $row['email'];
        });
        if ($recipient!==null) {
            try { $send($recipient,$code); }
            catch (Throwable) { error_log('MaintainPro: registration verification email delivery failed.'); }
        }
    }

    public function verifyRegistration(string $challenge, mixed $code): array
    {
        return $this->transaction(function() use($challenge,$code) {
            $row=$this->db->registrationChallenge($challenge,true);
            if (!$row || (int)$row['email_verified'] || !(int)$row['active'] || $row['role']!=='resident'
                || (int)$row['expires_at']<=time() || (int)$row['attempts']>=5) throw new DomainException('The code is incorrect or expired. Request a new code.');
            $this->db->recordRegistrationCodeAttempt($challenge);
            if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/',$code) || !password_verify($code,$row['otp_hash'])) {
                // The attempt must commit even when verification fails.
                return null;
            }
            $this->db->completeRegistration($challenge,$row['user_id']);
            $user=$this->user($row['user_id']);
            $this->db->recordAudit($user,'resident_email_verified','user',$user['id'],$user['name'],[]);
            return $user;
        }) ?? throw new DomainException('The code is incorrect or expired. Request a new code.');
    }

    public function createUser(string $officialId, array $data): array
    {
        return $this->transaction(function () use ($officialId, $data) {
            $actor = $this->authorizeAdmin($officialId);
            if (!in_array($data['role'] ?? '', ['resident', 'official', 'personnel'], true)) throw new DomainException('Choose a valid account role.');
            if (isset($data['isAdmin']) && !in_array($data['isAdmin'], ['0', '1'], true)) throw new DomainException('Choose a valid administrator setting.');
            if ($data['role'] !== 'official' && ($data['isAdmin'] ?? '0') === '1') throw new DomainException('Only an official can receive administrator access.');
            $isAdmin = $data['role'] === 'official' && ($data['isAdmin'] ?? '0') === '1';
            $name=self::name($data['name'] ?? '');
            $email=self::email($data['email'] ?? '');
            $role=(string)$data['role'];
            $team=$role==='personnel' ? (string)($data['team'] ?? '') : '';
            if ($role==='personnel' && !in_array($team,ComplaintWorkflow::TEAMS,true)) throw new DomainException('Choose a team for the personnel account.');
            $id='user-'.bin2hex(random_bytes(12));
            $token=bin2hex(random_bytes(32));
            $now=time();
            try {
                $this->db->insertInvitedUser($id,$name,$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$role,$team,date(DATE_ATOM),$isAdmin);
            } catch(PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0)===1062) throw new DomainException('That email address is already registered.');
                throw $e;
            }
            $invitationId=$this->db->createAccountInvitation($id,$actor['id'],hash('sha256',$token),$now,$now+86400);
            $user=$this->user($id);
            $this->db->recordAudit($actor,$role.'_account_created','user',$id,$name,['role'=>$role,'team'=>$team,'isSystemAdmin'=>$isAdmin,'status'=>'pending_setup']);
            return $user+['invitation_id'=>$invitationId,'invitation_token'=>$token];
        });
    }

    public function resendInvitation(string $officialId,string $userId): array
    {
        return $this->transaction(function() use($officialId,$userId) {
            $actor=$this->authorizeAdmin($officialId);
            $this->db->lockedUser($userId);
            $user=$this->user($userId);
            if (!$user || !$user['pending_setup']) throw new DomainException('Only a Pending Setup account can receive another invitation. Active users must use Forgot Password.');
            $now=time();
            $rate=$this->db->invitationResendState($userId,$now-900);
            if ((int)$rate['total']>=3 || ((int)$rate['latest']>$now-60 && $rate['latest_status']==='sent')) throw new DomainException('Wait before resending. You can send up to three invitations in 15 minutes.');
            $token=bin2hex(random_bytes(32));
            $invitationId=$this->db->createAccountInvitation($userId,$actor['id'],hash('sha256',$token),$now,$now+86400);
            return $user+['invitation_id'=>$invitationId,'invitation_token'=>$token];
        });
    }

    public function recordInvitationDelivery(string $officialId,string $userId,int $invitationId,bool $sent,bool $resend=false): void
    {
        $this->transaction(function() use($officialId,$userId,$invitationId,$sent,$resend) {
            $actor=$this->authorizeAdmin($officialId);
            $user=$this->user($userId);
            if (!$user) throw new DomainException('Account not found.');
            $this->db->markInvitationDelivery($invitationId,$sent,time());
            $this->db->recordAudit($actor,$resend?'account_invitation_resent':'account_invitation_sent','user',$userId,$user['name'],['delivery'=>$sent?'accepted':'failed']);
        });
    }

    public function inspectInvitation(mixed $token): array
    {
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/',$token)) return ['status'=>'invalid'];
        $row=$this->db->accountInvitationByToken(hash('sha256',$token));
        if (!$row) return ['status'=>'invalid'];
        $status=$row['used_at']!==null || (int)$row['email_verified'] ? 'used'
            : ($row['revoked_at']!==null ? 'revoked' : ((int)$row['expires_at']<=time() ? 'expired' : (!(int)$row['active'] ? 'inactive' : 'valid')));
        return ['status'=>$status,'name'=>$row['name'],'role'=>$row['role'],'team'=>$row['team'],'expires_at'=>(int)$row['expires_at']];
    }

    public function acceptInvitation(string $token,array $data): void
    {
        $password=self::password($data['password'] ?? '');
        if ($password!==($data['confirm_password'] ?? null)) throw new DomainException('The passwords do not match.');
        if (!preg_match('/\A[a-f0-9]{64}\z/',$token)) throw new DomainException('This invitation link is invalid. Request a new invitation from your administrator.');
        $this->transaction(function() use($token,$password) {
            $row=$this->db->accountInvitationByToken(hash('sha256',$token),true);
            if (!$row) throw new DomainException('This invitation link is invalid. Request a new invitation from your administrator.');
            if ($row['used_at']!==null || (int)$row['email_verified']) throw new DomainException('This invitation was already used. Sign in or use Forgot Password if you need help.');
            if ($row['revoked_at']!==null) throw new DomainException('This invitation was replaced. Ask your administrator for the latest invitation.');
            if ((int)$row['expires_at']<=time()) throw new DomainException('This invitation has expired. Ask your administrator to send a new invitation.');
            if (!(int)$row['active']) throw new DomainException('This account is deactivated. Contact your administrator.');
            $this->db->completeAccountInvitation((int)$row['id'],$row['user_id'],password_hash($password,PASSWORD_DEFAULT),time());
            $this->db->deleteUserResets($row['user_id']);
            $user=$this->user($row['user_id']);
            $this->db->recordAudit($user,'account_invitation_accepted','user',$user['id'],$user['name'],[]);
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
            $this->db->recordAudit($user,'initial_password_changed','user',$id,$user['name'],[]);
            return $this->user($id);
        });
    }

    public function updateUser(string $officialId, string $id, array $data): void
    {
        $this->transaction(function () use ($officialId, $id, $data) {
            $this->db->lockAccountAdministration();
            $actor = $this->authorizeAdmin($officialId);
            $this->db->lockedUser($id);
            $user = $this->user($id);
            if (!$user) throw new DomainException('Account not found.');
            $role = $data['role'] ?? '';
            if (!in_array($role, ['resident', 'official', 'personnel'], true)) throw new DomainException('Choose a valid role.');
            $team = $role === 'personnel' ? ($data['team'] ?? '') : '';
            if ($role === 'personnel' && !in_array($team, ComplaintWorkflow::TEAMS, true)) throw new DomainException('Choose a personnel team.');
            if (!isset($data['active']) || !in_array($data['active'], ['1', '0'], true)) throw new DomainException('Choose an account status.');
            $active = $data['active'] === '1';
            if (isset($data['isAdmin']) && !in_array($data['isAdmin'], ['0', '1'], true)) throw new DomainException('Choose a valid administrator setting.');
            if ($role !== 'official' && ($data['isAdmin'] ?? '0') === '1') throw new DomainException('Only an official can receive administrator access.');
            $isAdmin = $role === 'official' && ($data['isAdmin'] ?? ($user['is_system_admin'] ? '1' : '0')) === '1';
            if ($id === $officialId && (!$active || $role !== 'official')) throw new DomainException('You cannot deactivate or remove official access from your own account.');
            if ($id === $officialId && !$isAdmin) throw new DomainException('You cannot remove administrator access from your own account.');
            if ($user['active'] && $user['is_system_admin'] && (!$active || !$isAdmin || $role !== 'official') && $this->db->activeAdminCount() <= 1) throw new DomainException('Assign another active system administrator before removing the last one.');
            $reassigned=['concerns'=>0,'actionPlans'=>0];
            if ($user['role']==='personnel' && (!$active || $role!=='personnel' || $team!==$user['team'])) {
                $concerns=$this->db->activeAssignedConcerns($id);
                $plans=$this->db->activeAssignedPlans($id);
                if ($concerns || $plans) {
                    $replacementId=$data['reassignTo'] ?? '';
                    if (!is_string($replacementId) || $replacementId==='' || $replacementId===$id) {
                        throw new DomainException('This account still has '.count($concerns).' active concern(s) and '.count($plans).' active action plan(s). Choose another active personnel account to reassign all work before changing this account.');
                    }
                    $this->db->lockedUser($replacementId);
                    $replacement=$this->user($replacementId);
                    if (!$replacement || !$replacement['active'] || !$replacement['email_verified'] || $replacement['must_change_password'] || $replacement['role']!=='personnel' || !filter_var($replacement['email'],FILTER_VALIDATE_EMAIL)) {
                        throw new DomainException('Choose another active personnel account with a valid email address.');
                    }
                    foreach ($concerns as $row) {
                        $before=self::decodeConcern($row);
                        $changed=ComplaintWorkflow::reassignActiveWork($before,$actor,$replacement);
                        $this->db->updateComplaint($changed,$before['version']);
                        (new ConcernNotifications($this->db))->changed($changed,$before,'assign');
                        $this->db->recordAudit($actor,'assignment_changed','concern',$changed['id'],$changed['id'],['from'=>$id,'to'=>$replacementId,'accountChange'=>true]);
                        $reassigned['concerns']++;
                    }
                    foreach ($plans as $plan) {
                        $this->db->reassignActivePlan((int)$plan['id'],$id,$replacementId,$replacement['team'],(int)$plan['version']);
                        $this->db->createPlanNotification($replacementId,'plan_assignment','Action plan assigned','Action plan #'.$plan['id'].' has been reassigned to you.',(int)$plan['id'],'plan:'.$plan['id'].':account-reassign:'.$plan['version']);
                        $this->db->createPlanNotification($id,'plan_reassigned','Action plan reassigned','Action plan #'.$plan['id'].' is no longer assigned to you.',(int)$plan['id'],'plan:'.$plan['id'].':account-moved:'.$plan['version']);
                        $this->db->recordAudit($actor,'action_plan_reassigned','action_plan',(string)$plan['id'],'Action plan #'.$plan['id'],['from'=>$id,'to'=>$replacementId,'accountChange'=>true]);
                        $reassigned['actionPlans']++;
                    }
                    $this->db->recordAudit($actor,'personnel_work_reassigned','user',$id,$user['name'],['to'=>$replacementId]+$reassigned);
                }
            }
            $newDeactivation = (bool)$user['active'] && !$active;
            $reactivated = !(bool)$user['active'] && $active;
            $deactivatedAt = $newDeactivation ? time() : ($active ? null : $user['deactivated_at']);
            $deactivatedBy = $newDeactivation ? $actor['id'] : ($active ? null : $user['deactivated_by']);
            $this->db->updateUser($id, $role, $team, $active, $isAdmin, $deactivatedAt, $deactivatedBy, $newDeactivation);
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
            if ($user['pending_setup'] && (!$active || $name!==$user['name'] || $email!==$user['email'] || $role!==$user['role'] || $team!==$user['team'] || $isAdmin!==$user['is_system_admin'])) {
                $this->db->revokeAccountInvitations($id,time());
            }
            $changes = [];
            if ($role !== $user['role']) $changes['role'] = ['from' => $user['role'], 'to' => $role];
            if ($team !== $user['team']) $changes['team'] = ['from' => $user['team'], 'to' => $team];
            if ($active !== (bool)$user['active']) $changes['active'] = ['from' => (bool)$user['active'], 'to' => $active];
            if ($isAdmin !== $user['is_system_admin']) $changes['isSystemAdmin'] = ['from' => $user['is_system_admin'], 'to' => $isAdmin];
            if ($name !== $user['name']) $changes['nameChanged'] = true;
            if ($email !== $user['email']) $changes['emailChanged'] = true;
            if ($newDeactivation) $changes['deactivation'] = ['at' => $deactivatedAt, 'by' => $actor['id']];
            if ($reactivated) $changes['reactivation'] = ['previouslyDeactivatedAt' => $user['deactivated_at'], 'by' => $actor['id']];
            if ($changes) {
                if ($reassigned['concerns'] || $reassigned['actionPlans']) $changes['reassignedWork']=$reassigned;
                $action = isset($changes['active']) ? ($active ? 'account_activated' : 'account_deactivated')
                    : (isset($changes['role']) ? 'role_changed' : (isset($changes['team']) ? 'team_changed' : 'account_updated'));
                $this->db->recordAudit($actor, $action, 'user', $id, $name, $changes);
            }
        });
    }

    public function updateProfile(string $id, array $data): void
    {
        $newPhotoPath = null;
        $oldPhotoPath = '';
        $photoChanged = false;
        $photoData = null;
        try {
            $this->transaction(function () use ($id, $data, &$newPhotoPath, &$oldPhotoPath, &$photoChanged, &$photoData) {
                $actor = $this->actor($id);
                if (!$actor || $actor['must_change_password']) throw new DomainException('Complete your initial password change before updating your profile.');
                $name = self::name($data['name'] ?? '');
                $email = self::email($data['email'] ?? '');
                $hash = $this->db->passwordHash($id);
                if (!is_string($data['current_password'] ?? null) || !password_verify($data['current_password'], $hash)) throw new DomainException('Enter your current password to save account changes.');
                $newPassword = $data['new_password'] ?? '';
                if ($newPassword !== '' && $newPassword !== ($data['confirm_new_password'] ?? null)) throw new DomainException('The new passwords do not match.');
                if ($newPassword !== '') $hash = password_hash(self::password($newPassword), PASSWORD_DEFAULT);

                $oldPhotoPath = is_string($actor['profile_photo_path'] ?? null) ? $actor['profile_photo_path'] : '';
                $photoPath = $oldPhotoPath;
                $photoMime = is_string($actor['profile_photo_mime'] ?? null) ? $actor['profile_photo_mime'] : '';
                if (($data['photo'] ?? '') !== '') {
                    $stored = ProfilePhotoStorage::storeDataUri($data['photo']);
                    $newPhotoPath = $stored['file_path'];
                    $photoPath = $stored['file_path'];
                    $photoMime = $stored['mime_type'];
                    $photoData = $stored['data'];
                    $photoChanged = true;
                } elseif (($data['remove_photo'] ?? '') === '1') {
                    $photoPath = '';
                    $photoMime = '';
                    $photoChanged = $oldPhotoPath !== '' || !empty($actor['has_profile_photo']);
                }
                try {
                    $this->db->updateProfile($id, $name, $email, $hash, $newPassword !== '', $photoChanged, $photoPath, $photoMime, $photoData);
                } catch (PDOException $e) {
                    if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new DomainException('That email address is already registered.');
                    throw $e;
                }
                $this->db->recordAudit($actor,'profile_updated','user',$id,$name,[
                    'nameChanged'=>$name!==$actor['name'], 'emailChanged'=>$email!==$actor['email'],
                    'passwordChanged'=>$newPassword!=='', 'photoChanged'=>$photoChanged,
                ]);
            });
        } catch (Throwable $error) {
            if ($newPhotoPath !== null) ProfilePhotoStorage::delete($newPhotoPath);
            throw $error;
        }
        if ($photoChanged && $oldPhotoPath !== '' && $oldPhotoPath !== $newPhotoPath) ProfilePhotoStorage::delete($oldPhotoPath);
    }

    public function requestEmailChange(string $id, mixed $newEmail, mixed $currentPassword, callable $send): string
    {
        $email = self::email($newEmail);
        $this->confirmPassword($id, $currentPassword);
        $code = (string)random_int(100000, 999999);
        $challenge = bin2hex(random_bytes(32));
        $recipient = $this->transaction(function () use ($id, $email, $code, $challenge) {
            $actor = $this->actor($id);
            if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in again before changing your email address.');
            if ($email === $actor['email']) throw new DomainException('Enter a different email address.');
            $existing = $this->db->registrationUser($email);
            if ($existing && $existing['id'] !== $id) throw new DomainException('That email address is unavailable.');
            $this->db->replaceEmailChange($challenge,$id,$email,password_hash($code,PASSWORD_DEFAULT),time()+600,time());
            return $email;
        });
        $send($recipient,$code);
        return $challenge;
    }

    public function verifyEmailChange(string $id, string $challenge, mixed $code): array
    {
        return $this->transaction(function () use ($id,$challenge,$code) {
            $row=$this->db->emailChange($challenge,true);
            if (!$row || $row['user_id']!==$id || !(int)$row['active'] || !(int)$row['email_verified']
                || (int)$row['expires_at']<=time() || (int)$row['attempts']>=5) throw new DomainException('The code is incorrect or expired. Request a new email change code.');
            $this->db->recordEmailChangeAttempt($challenge);
            if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/',$code) || !password_verify($code,$row['otp_hash'])) return null;
            try { $this->db->completeEmailChange($challenge,$id,$row['new_email']); }
            catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0)===1062) throw new DomainException('That email address is unavailable.');
                throw $error;
            }
            $actor=$this->user($id);
            $this->db->recordAudit($actor,'email_changed','user',$id,$actor['name'],['oldEmailChanged'=>true]);
            return ['old_email'=>$row['email'],'new_email'=>$row['new_email'],'name'=>$row['name'],'user'=>$actor];
        }) ?? throw new DomainException('The code is incorrect or expired. Request a new email change code.');
    }

    public function login(mixed $email, mixed $password, string $client): array
    {
        if (!is_string($email) || !is_string($password) || strlen($email) > 254 || strlen($password) > 1024) throw new DomainException('The email or password is incorrect.');
        $email = strtolower(trim($email));
        $bucket = hash('sha256', 'login-pair|' . $email . '|' . $client);
        $emailBucket = hash('sha256', 'login-email|' . $email);
        $clientBucket = hash('sha256', 'login-client|' . $client);
        $blocked = false;
        $this->transaction(function () use ($bucket,$emailBucket,$clientBucket,&$blocked) {
            $this->db->deleteOldLoginAttempts(time() - 900);
            $blocked = $this->db->loginAttemptCount($bucket) >= 5
                || $this->db->loginAttemptCount($emailBucket) >= 20
                || $this->db->loginAttemptCount($clientBucket) >= 50;
            if (!$blocked) {
                foreach ([$bucket,$emailBucket,$clientBucket] as $key) $this->db->recordLoginAttempt($key,time());
            }
        });
        if ($blocked) {
            br_security_log('login_throttled',['result'=>'blocked']);
            throw new DomainException('Too many sign-in attempts. Please try again in 15 minutes.');
        }
        $row = $this->db->loginUser($email);
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $row['password_hash'] ?? $dummy);
        if (!$row || !$valid || !$row['active'] || !$this->actor($row['id'])) {
            br_security_log('login_failed',['result'=>'invalid_credentials']);
            throw new DomainException('The email or password is incorrect, or the account is inactive.');
        }
        foreach ([$bucket,$emailBucket] as $key) $this->db->clearLoginAttempts($key);
        $user=$this->user($row['id']);
        if ($user['role']!=='resident') br_security_log('staff_login',['actor_role'=>$user['role'],'result'=>'success']);
        return $user;
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
        $result = $this->transaction(function () use ($challenge, $code) {
            $row = $this->db->resetRequest($challenge);
            if (!$row || $row['reset_token_hash'] !== null) return ['error' => 'This verification code is no longer available. Please request a new code.'];
            if ((int)$row['expires_at'] <= time()) return ['error' => 'This verification code has expired. Please request a new code.'];
            if ((int)$row['attempts'] >= 5) return ['error' => 'Too many incorrect attempts. Please request a new verification code.'];
            $this->db->recordResetAttempt($challenge);
            if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/', $code) || !password_verify($code, $row['otp_hash'])) {
                return ['error' => (int)$row['attempts'] + 1 >= 5
                    ? 'Too many incorrect attempts. Please request a new verification code.'
                    : 'The verification code is incorrect. Please try again.'];
            }
            $token = bin2hex(random_bytes(32));
            $this->db->grantPasswordReset($challenge, hash('sha256', $token), time() + 600);
            return ['token' => $token];
        });
        // Reject after commit so failed attempts cannot be rolled back.
        if (isset($result['error'])) throw new DomainException($result['error']);
        return $result['token'];
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
            $user=$this->user($row['user_id']);
            $this->db->recordAudit($user,'password_reset_completed','user',$user['id'],$user['name'],[]);
        });
    }

    public function state(): array
    {
        $cases = [];
        foreach ($this->db->complaints() as $row) $cases[] = self::decodeConcern($row);
        return ['nextId' => $this->db->nextComplaintId(), 'cases' => $cases];
    }

    public function concernForActor(string $userId, string $id): ?array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) return null;
        $c = $this->concern($id);
        $offer=$actor['role']==='personnel' ? $this->db->teamOfferForUser($id,$actor['id']) : false;
        $pendingOffer=$offer && $offer['response']==='pending';
        if (!$c || (!ComplaintWorkflow::canSee($c,$actor) && !$pendingOffer)) return null;
        $c=$this->presentConcern($c,$actor);
        if ($pendingOffer) $c['teamOffer']=['assignmentId'=>(int)$offer['id'],'assignedAt'=>(int)$offer['assigned_at']];
        if ($actor['role']==='official') $c['teamAssignment']=$this->db->teamAssignmentSummary($id);
        return $c;
    }

    public function pendingTeamOffers(string $userId): array
    {
        $actor=$this->actor($userId);
        if (!$actor || $actor['must_change_password'] || $actor['role']!=='personnel') return [];
        return array_map(function(array $row) use($actor) {
            $c=$this->presentConcern(self::decodeConcern($row),$actor);
            $c['assignmentId']=(int)$row['assignment_id'];
            $c['assignedAt']=(int)$row['assigned_at'];
            return $c;
        },$this->db->pendingTeamOffers($userId));
    }

    public function teamAssignmentRecipients(string $officialId,string $concernId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->currentTeamRecipients($concernId);
    }

    public function pagedConcerns(string $userId, array $filters, int $page = 1, int $perPage = 20, bool $history = false): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed staff account.');
        foreach (['tab', 'search', 'category', 'priority', 'status', 'scope', 'team', 'personnel', 'week', 'type'] as $key) {
            $filters[$key] = is_string($filters[$key] ?? null) ? mb_substr(trim($filters[$key]), 0, $key === 'search' ? 120 : 100) : '';
        }
        if ($filters['week'] !== '') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$filters['week'],new DateTimeZone('Asia/Manila'));
            if ($actor['role']!=='official' || !$date || $date->format('Y-m-d')!==$filters['week'] || $date->format('N')!=='1') throw new DomainException('Choose a valid week from the official dashboard.');
        }
        $result = $this->db->pagedComplaints($actor, $filters, max(1, $page), min(50, max(1, $perPage)), $history);
        $result['items'] = array_map(fn(array $row) => $this->presentConcern(self::decodeConcern($row), $actor), $result['items']);
        return $result;
    }

    public function recentConcerns(string $userId, int $limit = 20): array
    {
        $page = $this->pagedConcerns($userId, ['tab' => 'all'], 1, min(50, max(1, $limit)));
        return $page['items'];
    }

    public function metrics(string $userId, bool $own = false): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed staff account.');
        return $this->db->complaintMetrics($actor,$own);
    }

    public function dashboardGroups(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return $this->db->dashboardGroups();
    }

    public function publicStatistics(): array
    {
        return $this->db->publicStatistics();
    }

    public function reportOptions(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        return ['categories'=>array_values(array_unique([...array_keys(ConcernCatalog::TYPES),...ComplaintWorkflow::CATEGORIES])),
            'statuses'=>ComplaintWorkflow::STATUSES,'priorities'=>ComplaintWorkflow::PRIORITIES,
            'teams'=>ComplaintWorkflow::TEAMS,'keypoints'=>array_values(array_unique(array_merge(...array_values(ConcernCatalog::POINTS)))),
            'personnel'=>$this->db->workloads(),'location'=>$this->db->locations(true)];
    }

    public function concernReport(string $officialId, array $input, int $page = 1, bool $export = false): array
    {
        $actor = $this->authorizeOfficial($officialId);
        $options = $this->reportOptions($officialId);
        $filters = ConcernReportFilters::validate($input,$options);
        return $this->db->reportSnapshot(function () use ($actor,$options,$filters,$page,$export) {
            $total = $this->db->reportCount($actor,$filters);
            // Check host capacity before collecting export rows; never silently cap records.
            if ($export) ConcernReportCapacity::check($total);
            $page = min(max(1,$page), max(1,(int)ceil($total / 20)));
            $summary = new ConcernReportSummary(); $preview = []; $index = 0;
            foreach ($this->db->reportRows($actor,$filters) as $c) {
                $summary->add($c);
                if ($export || ($index >= ($page-1)*20 && $index < $page*20)) $preview[] = $c;
                $index++;
            }
            return $summary->result() + ['filters'=>$filters,'options'=>$options,'labels'=>ConcernReportFilters::labels($filters,$options),
                'preview'=>['items'=>$preview,'total'=>$total,'page'=>$page,'perPage'=>20],
                'satisfaction'=>$this->db->reportFeedback($actor,$filters)];
        });
    }

    public function mutate(string $userId, string $action, string $id, array $data, mixed $expectedVersion): string
    {
        if ($action === 'submit') return $this->submitAccount($userId, $data);
        return $this->transaction(function () use ($userId, $action, $id, $data, $expectedVersion) {
            $actor = $this->actor($userId);
            if (!$actor) throw new DomainException('Your account is inactive. Please sign in again.');
            if ($actor['must_change_password']) throw new DomainException('Change your temporary password before accessing concerns.');
            if ($action === 'edit') throw new DomainException('Submitted concern details cannot be edited.');
            $before = $this->concern($id, true);
            if (!$before) throw new DomainException('Concern not found or unavailable to your account.');
            if (in_array($action,['accept_work','decline_work'],true)) {
                if ($actor['role']!=='personnel') throw new DomainException('Only eligible personnel can respond to a team assignment.');
                $offer=$this->db->teamOfferForUser($id,$actor['id'],true);
                if (!$offer || $offer['response']!=='pending' || !(int)$offer['active'] || !(int)$offer['email_verified'] || $offer['team']!==$actor['team']) throw new DomainException('This team assignment is no longer available.');
                if ($action==='decline_work') {
                    $this->db->declineTeamOffer((int)$offer['id'],$actor['id']);
                    (new ConcernNotifications($this->db))->teamResponse($before,$actor,false);
                    $this->db->recordAudit($actor,'team_assignment_declined','concern',$id,$id,['team'=>$actor['team']]);
                    return $id;
                }
                ComplaintWorkflow::acceptTeamWork($before,$actor);
                $before['version']++;
                $this->db->acceptTeamOffer((int)$offer['id'],$actor['id']);
                $this->db->updateComplaint($before,$before['version']-1);
                (new ConcernNotifications($this->db))->changed($before,null,'accept_work');
                $this->db->recordAudit($actor,'team_assignment_accepted','concern',$id,$id,['team'=>$actor['team']]);
                return $id;
            }
            if (!ComplaintWorkflow::canSee($before, $actor)) throw new DomainException('Concern not found or unavailable to your account.');
            if (!is_int($expectedVersion) || $expectedVersion !== $before['version']) throw new ConflictException('Another user updated this concern. Refresh the page to load the latest record before saving.');
            if ($action !== 'link_concern' && $this->db->primaryConcernId($id) !== null) throw new DomainException('This report follows its primary concern and cannot receive separate work actions.');

            if ($action === 'information') {
                if (($before['residentId'] ?? null) !== $actor['id']) throw new DomainException('Only the reporter can provide this follow-up.');
                $c = $before;
                ComplaintWorkflow::reporterFollowup($c, $data);
                $c['version']++;
                $this->persistLatestEvidence($c, $actor['id'], is_string($data['photoName'] ?? null) ? $data['photoName'] : '');
                $this->db->updateComplaint($c, $before['version']);
                (new ConcernNotifications($this->db))->changed($c, $before, 'followup');
                return $id;
            }

            // Never trust internal assignee, location, primary, or guidance fields supplied by the client.
            unset($data['_assignee'], $data['_location'], $data['_primary'], $data['_residentGuidance'], $data['_suggestions']);
            if ($action === 'assign') {
                if (!is_string($data['team'] ?? null) || !in_array($data['team'],ComplaintWorkflow::TEAMS,true)) throw new DomainException('Choose a valid team or crew.');
            }
            if ($action === 'link_concern') {
                if ($actor['role'] !== 'official') throw new DomainException('Only a barangay official can link reports.');
                $primaryId = is_string($data['primaryConcernId'] ?? null) ? $data['primaryConcernId'] : '';
                $primaryId = $this->db->rootConcernId($primaryId);
                $primary = $primaryId !== '' ? $this->concern($primaryId, true) : null;
                if (!$primary || $primaryId === $id || $this->db->linkedConcernCount($id) > 0) throw new DomainException('Choose an independent primary concern. A primary with linked reports cannot become a linked report.');
                $data['_primary'] = $primary;
            }

            $state = ['nextId' => $this->db->nextComplaintId(), 'cases' => [$before]];
            ComplaintWorkflow::apply($state, $actor, $id, $action, $data);
            $c = $state['cases'][0];
            if (in_array($action, ['start', 'note', 'resolve', 'assign', 'reopen'], true) && !empty($before['blocked']['active'])) $c['blocked'] = null;
            $c['version']++;
            $this->persistLatestEvidence($c, $actor['id'], is_string($data['photoName'] ?? null) ? $data['photoName'] : '');

            // Link and blocked-work metadata already live in this concern and its audit timeline.
            // Save them atomically instead of maintaining a second, inconsistent copy.
            $this->db->updateComplaint($c, $before['version']);
            if ($action==='assign') $this->db->createTeamAssignment($id,$c['team'],$actor['id'],$c['dueAt'] ?? null);
            if (in_array($action,['reopen','link_concern'],true)) $this->db->cancelOpenTeamAssignments($id);
            if ($action === 'reopen') $this->db->insertConcernMessage($id,null,'system','MaintainPro','reporter','Concern reopened.');
            (new ConcernNotifications($this->db))->changed($c, $before, $action);
            if ($action === 'assess') $this->notifyDuplicateSuggestions($c);
            if (($before['dueAt'] ?? null)!==($c['dueAt'] ?? null)) $this->db->recordAudit($actor,'target_date_changed','concern',$id,$id,['before'=>$before['dueAt'] ?? null,'after'=>$c['dueAt'] ?? null]);
            $audit = match ($action) {
                'assign' => 'assignment_changed', 'verify' => 'concern_manually_closed', 'reopen' => 'concern_reopened',
                'link_concern' => 'concern_linked', 'manage_block' => 'blocked_work_managed', default => null,
            };
            if ($audit !== null) $this->db->recordAudit($actor, $audit, 'concern', $id, $id, ['status' => $c['status']]);
            return $id;
        });
    }

    public function submissionAllowance(string $userId): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed account.');
        $start = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        $used = $this->db->submissionCount($actor['id'], $start->format(DATE_ATOM), $start->modify('+1 day')->format(DATE_ATOM));
        return ['used' => $used, 'limit' => 3, 'remaining' => max(0, 3 - $used), 'date' => $start->format('Y-m-d')];
    }

    public function submitAccount(string $userId, array $data): string
    {
        return $this->transaction(function () use ($userId, $data) {
            // The existing counter lock serializes the count and insert across sessions.
            $allowance = $this->submissionAllowance($userId);
            if ($allowance['remaining'] === 0) throw new DomainException('You have reached the maximum of 3 concern submissions for today. You may submit another concern tomorrow.');
            $actor = $this->actor($userId);
            $data['_residentGuidance'] = $this->suggestions($data);
            $data['_location'] = $this->managedLocation($data);
            $state = ['nextId' => $this->db->nextComplaintId(), 'cases' => []];
            $id = ComplaintWorkflow::submit($state, $actor, $data);
            $c = $state['cases'][0];
            $c['version'] = 1;
            $this->db->setNextComplaintId($state['nextId']);
            $this->db->insertComplaint($c);
            $this->persistLatestEvidence($c, $actor['id'], is_string($data['photoName'] ?? null) ? $data['photoName'] : '');
            if (!empty($c['initialEvidenceId'])) $this->db->updateComplaint($c, 1);
            (new ConcernNotifications($this->db))->changed($c, null, 'submit');
            $this->notifyDuplicateSuggestions($c);
            return $id;
        });
    }

    // Apply before any concern is rendered or returned to a client. Ownership checks
    // always use the private stored record, never these presentation fields.
    public function presentConcern(array $c, array $actor): array
    {
        $owner = $c['residentId'] ?? null;
        $anonymous = (bool)($c['isAnonymous'] ?? !$owner);
        $c['isAnonymous'] = $anonymous;
        $c['isOwn'] = $owner !== null && $owner === $actor['id'];
        $c['reporterChannel'] = $owner !== null ? 'account' : 'tracking';
        $c['canWork'] = $actor['role'] === 'official' || ($actor['role'] === 'personnel' && ($c['assignedUserId'] ?? null) === $actor['id']);
        $pendingRequest = ComplaintWorkflow::pendingInformationRequest($c);
        $c['informationRequestPending'] = $pendingRequest !== null;
        $c['informationRequest'] = $pendingRequest === null ? null : [
            'message' => (string)($pendingRequest['note'] ?? ''),
            'requestedAt' => (string)($pendingRequest['date'] ?? ''),
        ];
        $reporter = !$anonymous && $owner ? $this->user($owner) : null;
        $c['resident'] = $anonymous ? 'Anonymous' : ($reporter['name'] ?? $c['resident']);
        $c['submitterRole'] = $anonymous ? null : ($reporter['role'] ?? 'resident');
        if ($actor['role']==='resident') $c['timeline']=array_values(array_filter($c['timeline'],fn($event)=>empty($event['internal'])));
        foreach ($c['timeline'] as &$event) {
            if ($anonymous && $owner && ($event['actorId'] ?? null) === $owner) $event['actor'] = 'Anonymous';
            unset($event['actorId'], $event['priorityDecision']['actorId']);
            if (isset($event['block'])) {
                if ($anonymous && $owner && ($event['block']['reportedById'] ?? null) === $owner) $event['block']['reportedBy'] = 'Anonymous';
                unset($event['block']['reportedById']);
            }
        }
        unset($event);
        if (isset($c['blocked'])) {
            if ($anonymous && $owner && ($c['blocked']['reportedById'] ?? null) === $owner) $c['blocked']['reportedBy'] = 'Anonymous';
            unset($c['blocked']['reportedById']);
        }
        unset($c['residentId'], $c['resolution']['uploadedBy'], $c['priorityDecision']['actorId']);
        return $c;
    }

    public function visibleConcerns(string $userId): array
    {
        $actor = $this->actor($userId);
        if (!$actor || $actor['must_change_password']) throw new DomainException('Sign in with a completed account.');
        return array_map(fn($c) => $this->presentConcern($c, $actor), ComplaintWorkflow::visible($this->state(), $actor));
    }

    public function weeklyConcerns(string $officialId): array
    {
        $this->authorizeOfficial($officialId);
        $period = ConcernInsights::week();
        $rows = $this->db->weeklyComplaints($period['start'], $period['end']);
        $cases = array_map(fn($row) => self::decodeConcern($row), $rows);
        $groups=ConcernInsights::weekly($cases, $this->recurrenceGroups($officialId));
        foreach ($groups as &$group) {
            $group['officialActions']=[];
            foreach ($group['keyPoints'] as $point) $group['officialActions'][$point]=$this->db->officialRules($group['category'],$group['type'],$point,true);
            if ($group['keyPoints']) {
                $texts=[];
                foreach ($group['officialActions'] as $rules) foreach ($rules as $rule) if ($rule['action_text']!=='') $texts[]=$rule['action_text'];
                $group['actions']=array_slice(array_values(array_unique($texts)),0,3);
            }
        }
        unset($group);
        return $period + ['groups'=>$groups];
    }

    public function publicLimit(string $purpose, string $client): void
    {
        $reporting = in_array($purpose, ['report', 'followup', 'setup'], true);
        $limit = match ($purpose) {'guest_message' => 12, 'staff_message' => 30, 'message_poll' => 100, default => $reporting ? 5 : 40};
        $window = in_array($purpose,['staff_message'],true) ? 60 : ($reporting ? 3600 : 900);
        $allowed = $this->transaction(fn() => $this->db->recordPublicAttempt(hash('sha256', $purpose . '|' . $client), $limit, $window));
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
            $data['_residentGuidance'] = $this->suggestions($data);
            $data['_location'] = $this->managedLocation($data);
            $state = ['nextId' => $this->db->nextComplaintId(), 'cases' => []];
            $id = ComplaintWorkflow::submit($state, ['id' => null, 'role' => 'guest', 'name' => 'Anonymous resident'], $data);
            $case = $state['cases'][0];
            $case['version'] = 1;
            $this->db->setNextComplaintId($state['nextId']);
            $this->db->insertComplaint($case);
            $this->persistLatestEvidence($case, null, is_string($data['photoName'] ?? null) ? $data['photoName'] : '');
            if (!empty($case['initialEvidenceId'])) $this->db->updateComplaint($case, 1);
            (new ConcernNotifications($this->db))->changed($case,null,'submit');
            $this->notifyDuplicateSuggestions($case);
            $token = bin2hex(random_bytes(24));
            $this->db->insertTracking($id, hash('sha256', $token));
            return ['reference' => $id, 'trackingCode' => $token, 'residentGuidance' => $case['residentGuidance']];
        });
    }

    private function publicConcern(array $c): array
    {
        $source = $c;
        $primaryId = $this->db->primaryConcernId($c['id']);
        if ($primaryId !== null) {
            $primary = $this->concern($primaryId);
            if ($primary) $source = $primary;
        }
        $progress = [];
        foreach ($source['timeline'] as $event) {
            if (isset($event['workStatus']) && in_array($event['workStatus'], ConcernCatalog::WORK_STATUSES, true)) {
                $progress[] = ['date' => $event['date'], 'status' => $event['workStatus']];
            }
        }
        $followups = [];
        foreach ($c['timeline'] as $event) {
            if (!empty($event['publicReporterFollowup'])) {
                $followups[] = ['description' => (string)($event['note'] ?? ''), 'submittedAt' => (string)($event['date'] ?? '')];
            }
        }
        $pendingRequest = ComplaintWorkflow::pendingInformationRequest($c);
        $request = $pendingRequest === null ? null : ['message' => (string)($pendingRequest['note'] ?? ''), 'requestedAt' => (string)($pendingRequest['date'] ?? '')];
        $guidance = ConcernCatalog::validGuidance($c['residentGuidance'] ?? null) ? $c['residentGuidance'] : [];
        $status = $source['status'] === 'Verified' ? 'Closed' : ($c['status'] === 'Returned for Information' ? 'Needs More Information' : $source['status']);
        return [
            'reference' => $c['id'], 'category' => $c['category'], 'concernType' => $c['concernType'], 'status' => $status,
            'reportedAt' => $c['createdAt'], 'updatedAt' => $source['updatedAt'], 'progress' => $progress,
            'residentGuidance' => $guidance, 'informationRequest' => $request,
            'followUps' => $followups, 'canFollowUp' => $request !== null, 'linked' => $primaryId !== null,
        ];
    }

    public function track(mixed $reference, mixed $token, string $client): array
    {
        $this->publicLimit('track', $client);
        if (!is_string($reference) || strlen($reference) > 64 || !is_string($token) || !preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/', $reference) || !preg_match('/\A[a-f0-9]{48}\z/', $token)) throw new DomainException('Reference or tracking code not found.');
        $row = $this->db->trackedConcern($reference, hash('sha256', $token));
        if (!$row) throw new DomainException('Reference or tracking code not found.');
        return $this->publicConcern(self::decodeConcern($row));
    }

    public function submitFollowup(array $data, string $client): array
    {
        $this->publicLimit('followup', $client);
        $reference = $data['reference'] ?? null;
        $token = $data['trackingCode'] ?? null;
        if (!is_string($reference) || !preg_match('/\ACON-[0-9]{4}-[0-9]{6,}\z/', $reference)
            || !is_string($token) || !preg_match('/\A[a-f0-9]{48}\z/', $token)) {
            throw new DomainException('Reference or tracking code not found.');
        }
        return $this->transaction(function () use ($data, $reference, $token) {
            $row = $this->db->trackedConcern($reference, hash('sha256', $token), true);
            if (!$row) throw new DomainException('Reference or tracking code not found.');
            $before = self::decodeConcern($row);
            $c = $before;
            ComplaintWorkflow::reporterFollowup($c, $data);
            $c['version']++;
            $this->persistLatestEvidence($c, null, is_string($data['photoName'] ?? null) ? $data['photoName'] : '');
            $this->db->updateComplaint($c, $before['version']);
            (new ConcernNotifications($this->db))->changed($c, $before, 'followup');
            return $this->publicConcern($c);
        });
    }

    public function saveRule(string $officialId, array $data, bool $reset = false): void
    {
        $this->transaction(function () use ($officialId, $data, $reset) {
            $actor = $this->authorizeOfficial($officialId);
            [$category, $type] = ConcernCatalog::selections($data);
            if ($reset) {
                $this->db->deleteSolutionRule($category, $type);
                $this->db->recordAudit($actor, 'resident_guidance_reset', 'guidance', $category . '|' . $type, $category . ' / ' . $type, []);
                return;
            }
            if (($data['purpose'] ?? '') !== ConcernCatalog::GUIDANCE_PURPOSE) throw new DomainException('Reload the Solution Library to write temporary guidance for residents.');
            $actions = [];
            foreach (['action1', 'action2', 'action3'] as $key) $actions[] = ComplaintWorkflow::text($data[$key] ?? '', 'Resident guidance step', 700);
            if (count(array_unique($actions)) !== 3) throw new DomainException('Provide three different temporary steps for residents.');
            $this->db->saveSolutionRule($category, $type, ['purpose' => ConcernCatalog::GUIDANCE_PURPOSE, 'steps' => $actions]);
            $this->db->recordAudit($actor, 'resident_guidance_changed', 'guidance', $category . '|' . $type, $category . ' / ' . $type, []);
        });
    }

    public function generateBackup(string $officialId): array
    {
        return $this->transaction(function () use ($officialId) {
            $actor = $this->authorizeAdmin($officialId);
            $content = $this->db->sqlBackup();
            $this->db->recordAudit($actor, 'database_backup_generated', 'system', null, 'MaintainPro database', ['bytes' => strlen($content)]);
            return ['filename' => 'maintainpro-' . date('Ymd-His') . '.sql', 'content' => $content];
        });
    }

    public function generateFullBackup(string $officialId): array
    {
        $temporary=null;
        try {
            return $this->transaction(function() use($officialId,&$temporary) {
                $actor=$this->authorizeAdmin($officialId);
                $base=tempnam(sys_get_temp_dir(),'maintainpro-backup-');
                if ($base===false) throw new RuntimeException('Cannot create a private backup file.');
                unlink($base);
                $temporary=$base.'.zip';
                $zip=null;
                $addString=null;
                $addFile=null;
                $close=null;
                if (class_exists('ZipArchive')) {
                    $zip=new ZipArchive();
                    $opened=$zip->open($temporary,ZipArchive::CREATE|ZipArchive::OVERWRITE);
                    if ($opened!==true) throw new RuntimeException('Cannot create the ZIP backup archive.');
                    $addString=static function(string $name,string $content) use($zip): void {
                        if (!$zip->addFromString($name,$content)) throw new RuntimeException('Cannot add data to the ZIP backup archive.');
                    };
                    $addFile=static function(string $path,string $name) use($zip): void {
                        if (!$zip->addFile($path,$name)) throw new RuntimeException('Cannot add an uploaded file to the ZIP backup archive.');
                    };
                    $close=static function() use($zip): void {
                        if (!$zip->close()) throw new RuntimeException('Cannot finish the ZIP backup archive.');
                    };
                } elseif (class_exists('PharData') && class_exists('Phar')) {
                    $zip=new PharData($temporary,0,null,Phar::ZIP);
                    $addString=static function(string $name,string $content) use($zip): void { $zip->addFromString($name,$content); };
                    $addFile=static function(string $path,string $name) use($zip): void { $zip->addFile($path,$name); };
                    $close=static function() use(&$zip): void { unset($zip); };
                } else {
                    throw new RuntimeException('ZIP support is not enabled on this server. Enable the PHP zip or phar extension.');
                }
                $addString('database.sql',$this->db->sqlBackup(true));
                $files=$this->db->evidenceFiles();
                $missing=[];
                foreach ($files as $relative) {
                    $file=EvidenceStorage::storedFile($relative);
                    if (!$file) { $missing[]=$relative; continue; }
                    $addFile($file['path'],$relative);
                }
                $profileFiles=$this->db->profilePhotoFiles();
                foreach ($profileFiles as $relative) {
                    $file=ProfilePhotoStorage::storedFile($relative);
                    if (!$file) { $missing[]=$relative; continue; }
                    $addFile($file['path'],$relative);
                }
                $restore="MaintainPro full data backup\nCreated: ".date(DATE_ATOM)."\n\n1. Install the matching MaintainPro source and PHP/MariaDB environment.\n2. Configure database and SMTP settings separately. Configuration secrets are excluded.\n3. Import database.sql into an EMPTY database. Run php database/setup.php.\n4. Copy uploads/evidence and uploads/profiles to their protected application directories, preserving paths. Inline legacy evidence is in SQL.\n5. Account passwords and password-reset grants are excluded. Use Forgot password after restoring; SMTP must be configured. All previous account sessions must be discarded.\n6. Guest tracking HASHES are retained so existing private tracking codes keep working. No plaintext tracking codes are stored.\n\nThis archive contains private concern records, evidence, and profile photos. Keep it in authorized storage. No source, configuration, sessions, logs or temporary files are included.\n";
                if ($missing) {
                    $restore.="\nWARNING: ".count($missing)." database-referenced upload(s) were already missing from storage when this backup was created. See MISSING_FILES.txt.\n";
                    $addString('MISSING_FILES.txt',"Files missing when this backup was created:\n".implode("\n",$missing)."\n");
                }
                $addString('RESTORE.txt',$restore);
                $close();
                clearstatcache(true,$temporary);
                $bytes=is_file($temporary) ? filesize($temporary) : false;
                if ($bytes===false || $bytes<4) throw new RuntimeException('The ZIP backup archive could not be finalized.');
                $this->db->recordAudit($actor,'full_backup_created','system',null,'MaintainPro full system backup',['evidenceFiles'=>count($files)-count(array_filter($missing,fn($path)=>str_starts_with($path,'uploads/evidence/'))),'profilePhotos'=>count($profileFiles)-count(array_filter($missing,fn($path)=>str_starts_with($path,'uploads/profiles/'))),'missingFiles'=>count($missing),'bytes'=>$bytes]);
                return ['filename'=>'maintainpro-full-'.date('Ymd-His').'.zip','path'=>$temporary];
            });
        } catch (Throwable $error) {
            if ($temporary!==null && is_file($temporary)) unlink($temporary);
            throw $error;
        }
    }

}
