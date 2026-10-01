<?php
declare(strict_types=1);
// Always use a disposable MySQL database and a separate local PHP server.
require __DIR__ . '/support/database.php';
require_once dirname(__DIR__) . '/includes/concern-catalog.php';
require __DIR__ . '/support/mail-server.php';
$testDatabase = new TestDatabase();
$server = null;
$mailServer = null;
$serverLog = tempnam(sys_get_temp_dir(), 'maintainpro-http-');
$previousDatabase = getenv('BR_DB_NAME');
$previousSetupKey=getenv('APP_SETUP_KEY');
putenv('APP_SETUP_KEY='.str_repeat('a',64));
$checks = 0;
$jars = [];
function httpCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function jar(): string {
    global $jars;
    $path = tempnam(sys_get_temp_dir(), 'br-http-cookie-');
    if ($path === false) throw new RuntimeException('Cannot create test cookie jar');
    $jars[] = $path;
    return $path;
}
function req(string $jar, string $path, ?array $data = null, string $csrf = ''): array {
    global $base;
    $handle = curl_init($base . '/' . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15]);
    if ($data !== null) {
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]]);
    }
    $body = curl_exec($handle);
    if ($body === false) throw new RuntimeException('HTTP connection failed: ' . curl_error($handle));
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_setopt($handle, CURLOPT_COOKIELIST, 'FLUSH');
    unset($handle);
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}
function token(string $jar, string $page = 'index.php'): string {
    $r = req($jar, $page);
    if (!preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['body'], $m)) throw new RuntimeException('CSRF token missing');
    return $m[1];
}
function post(string $jar, string $action, array $data, string $csrf, string $id = '', ?int $version = null): array {
    return req($jar, 'api.php', ['action' => $action, 'data' => $data, 'id' => $id, 'version' => $version], $csrf);
}
function auth(string $jar, string $action, array $data, string $csrf): array {
    return req($jar, 'auth.php', ['action' => $action, 'data' => $data], $csrf);
}
function httpCase(array $r, string $id): array {
    $data = $r['json']['state'] ?? $r['json'];
    foreach ($data['cases'] as $c) if ($c['id'] === $id) return $c;
    throw new RuntimeException('Record missing in HTTP response');
}
try {
    $mailServer = new TestMailServer();
    putenv('BR_DB_NAME=' . $testDatabase->name);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!$socket) throw new RuntimeException('Cannot reserve test port: ' . $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $server = proc_open([PHP_BINARY, '-S', $address, 'router.php'], [
        0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a'],
    ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($server)) throw new RuntimeException('Cannot start test server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Test server did not start: ' . file_get_contents($serverLog));
    $adminJar = jar(); $guestJar = jar(); $staffJar = jar(); $otherJar = jar();
    $password = 'Http-test-password-42';
    $adminCsrf = token($adminJar, 'login.php');
    $adminData = ['name' => 'HTTP Official', 'email' => 'official@example.test', 'password' => $password, 'confirm_password' => $password];
    httpCheck(auth($adminJar, 'setup', $adminData, '')['status'] === 403, 'setup CSRF');
    httpCheck(auth($adminJar, 'setup', $adminData, $adminCsrf)['status'] === 422, 'localhost cannot bypass installation key');
    $adminData['setup_key']=str_repeat('a',64);
    httpCheck(auth($adminJar, 'setup', $adminData, $adminCsrf)['status'] === 200, 'first official setup with installation key');
    $adminCsrf = token($adminJar);
    if (in_array('--accounts-only', array_slice($argv, 1), true)) {
        require __DIR__ . '/account-pages.php';
        return; // The finally block still removes the disposable database/server.
    }
    $guestCsrf = token($guestJar, 'report-concern.php');
    $duplicateJar=jar();
    $mailBefore=count($mailServer->messages());
    $duplicate=auth($duplicateJar,'register',$adminData,token($duplicateJar,'login.php?view=register'));
    httpCheck($duplicate['status']===200 && $duplicate['json']['redirect']==='login.php?view=verify-registration'
        && count($mailServer->messages())===$mailBefore,'duplicate email receives generic registration response without mail');
    $verificationJar=jar();
    $registrationData=['name'=>'Email Verified Resident','email'=>'verified@example.test','password'=>$password,'confirm_password'=>$password];
    httpCheck(auth($verificationJar,'register',$registrationData,token($verificationJar,'login.php?view=register'))['status']===200,'resident registration creates pending account');
    httpCheck(req($verificationJar,'api.php')['status']===401,'pending resident has no account session');
    httpCheck(auth($verificationJar,'login',$registrationData,token($verificationJar,'login.php'))['status']===422,'pending resident cannot sign in');
    $verificationMail=$mailServer->messages();
    preg_match('/\b([0-9]{6})\b/',end($verificationMail)['body'] ?? '',$verificationMatch);
    httpCheck(auth($verificationJar,'verify_registration',['code'=>'000000'],token($verificationJar,'login.php?view=verify-registration'))['status']===422,'wrong registration code refused');
    httpCheck(auth($verificationJar,'verify_registration',['code'=>$verificationMatch[1] ?? ''],token($verificationJar,'login.php?view=verify-registration'))['status']===200
        && req($verificationJar,'api.php')['json']['actor']['role']==='resident','verified resident gains account access');
    httpCheck(req($guestJar, 'api.php')['status'] === 401, 'anonymous private API denied');
    httpCheck(req($guestJar, 'public-api.php')['status'] === 405, 'public API POST only');
    $public = fn(string $action, array $data, ?string $csrf = null) => req($guestJar, 'public-api.php', ['action' => $action, 'data' => $data], $csrf ?? $guestCsrf);
    $report = ['category' => 'Roads and Infrastructure', 'concernType' => 'Pothole', 'keyPoints' => ['Deep', 'Near school'], 'purok' => 'PRIVATE-PUROK', 'street' => 'PRIVATE-STREET', 'exactArea' => 'PRIVATE-GATE', 'description' => '<script>alert(1)</script>'];
    httpCheck($public('submit', $report, '')['status'] === 403, 'anonymous report CSRF');
    $recommendations = $public('guidance', $report);
    httpCheck($recommendations['status'] === 200 && count($recommendations['json']['residentGuidance']) === 3, 'three public resident guidance steps');
    httpCheck($public('suggestions', $report)['json']['suggestions'] === $recommendations['json']['residentGuidance'], 'old preview endpoint returns resident guidance too');
    httpCheck($public('submit', array_replace($report, ['street' => '']))['status'] === 422, 'private location required');
    httpCheck($public('submit', array_replace($report, ['concernType' => 'Exposed wiring']))['status'] === 422, 'dependent type validation');
    httpCheck($public('submit', array_replace($report, ['keyPoints' => ['forged']]))['status'] === 422, 'key point validation');
    $receipt = $public('submit', $report + ['selectedSuggestion' => '1', 'residentGuidance' => ['PRIVATE-INJECTED'], '_residentGuidance' => ['PRIVATE-INJECTED']]);
    httpCheck($receipt['status'] === 200, 'anonymous concern submission');
    $receipt = $receipt['json']['receipt']; $id = $receipt['reference'];
    httpCheck($receipt['residentGuidance'] === $recommendations['json']['residentGuidance'], 'receipt uses server-generated guidance');
    httpCheck(preg_match('/^CON-[0-9]{4}-[0-9]{6,}$/', $id) === 1 && strlen($receipt['trackingCode']) === 48, 'reference and secure code');
    $tracked = $public('track', $receipt);
    httpCheck($tracked['status'] === 200 && !str_contains($tracked['body'], 'PRIVATE') && !str_contains($tracked['body'], '<script>'), 'safe tracking allowlist');
    httpCheck($tracked['json']['concern']['residentGuidance'] === $receipt['residentGuidance'], 'guidance accessible later through private tracking');
    httpCheck($public('track', array_replace($receipt, ['trackingCode' => str_repeat('0', 48)]))['status'] === 422, 'wrong code blocked');
    httpCheck($public('track', $receipt, '')['status'] === 403, 'tracking CSRF');
    $created = post($adminJar, 'create_user', ['name' => 'HTTP Staff', 'email' => 'staff@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew'], $adminCsrf);
    httpCheck($created['status'] === 200, 'staff created');
    $account = $created['json']['created_account']; $staffId = $account['id'];
    httpCheck(!str_contains(req($adminJar, 'api.php')['body'], $account['temporary_password']), 'credential returned once outside state');
    httpCheck(auth($staffJar, 'login', ['email' => $account['email'], 'password' => $account['temporary_password']], token($staffJar, 'login.php'))['status'] === 200, 'temporary login');
    httpCheck(req($staffJar, 'api.php')['status'] === 403, 'onboarding gates data');
    httpCheck(str_contains(req($staffJar, 'complaint.php?id=' . $id)['body'], 'data-action="change_password"'), 'onboarding direct URL gate');
    httpCheck(auth($staffJar, 'change_password', ['current_password' => $account['temporary_password'], 'password' => $password, 'confirm_password' => $password], token($staffJar, 'login.php'))['status'] === 200, 'staff activates');
    $staffCsrf = token($staffJar);
    $otherCreated = post($adminJar, 'create_user', ['name' => 'Other Staff', 'email' => 'other@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew'], $adminCsrf)['json']['created_account'];
    auth($otherJar, 'login', ['email' => $otherCreated['email'], 'password' => $otherCreated['temporary_password']], token($otherJar, 'login.php'));
    auth($otherJar, 'change_password', ['current_password' => $otherCreated['temporary_password'], 'password' => $password, 'confirm_password' => $password], token($otherJar, 'login.php'));
    $otherCsrf = token($otherJar);
    httpCheck(req($staffJar, 'concern.php?id=' . $id)['status'] === 404, 'unassigned staff denied private detail');
    httpCheck(post($staffJar, 'assess', [], $staffCsrf, $id, 1)['status'] === 422, 'personnel assessment denied');
    $assessment = ['priority' => 'High', 'recommendation' => 'INTERNAL-RECOMMENDATION', 'assessment' => 'INTERNAL-NOTE'];
    httpCheck(post($adminJar, 'assess', $assessment, '', $id, 1)['status'] === 403, 'staff write CSRF');
    httpCheck(post($adminJar, 'assess', $assessment, $adminCsrf, $id, 1)['status'] === 200, 'official assesses');
    httpCheck(post($adminJar, 'assess', $assessment, $adminCsrf, $id, 1)['status'] === 409, 'stale edits rejected');
    $assignment = post($adminJar, 'assign', ['personnelId' => $staffId], $adminCsrf, $id, 2);
    httpCheck($assignment['status'] === 200 && $assignment['json']['notification_sent'] === true, 'assignment email success');
    $mail = $mailServer->messages();
    $assignmentMail=end($mail);
    httpCheck(count($mail) === 2 && str_contains($assignmentMail['recipient'], 'staff@example.test') && str_contains($assignmentMail['body'], $id), 'SMTP recipient and reference');
    httpCheck(!str_contains($assignmentMail['body'], 'PRIVATE') && !str_contains($assignmentMail['body'], 'INTERNAL'), 'assignment mail minimizes private data');
    httpCheck(count(req($staffJar, 'api.php')['json']['cases']) === 1 && count(req($otherJar, 'api.php')['json']['cases']) === 0, 'same-team isolation');
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
    $work = ['workStatus' => 'Inspection completed', 'actions' => ['Inspection'], 'photo' => $png];
    httpCheck(post($staffJar, 'start', [], $staffCsrf, $id, 3)['status'] === 422, 'start requires image');
    httpCheck(post($staffJar, 'start', array_replace($work, ['photo' => 'data:image/png;base64,' . base64_encode('<?php echo 1; ?>')]), $staffCsrf, $id, 3)['status'] === 422, 'script masquerading as PNG rejected');
    httpCheck(post($staffJar, 'start', $work, $staffCsrf, $id, 3)['status'] === 200, 'structured start without typing');
    httpCheck(post($staffJar, 'note', array_replace($work, ['photo' => '']), $staffCsrf, $id, 4)['status'] === 422, 'progress requires evidence');
    httpCheck(post($staffJar, 'note', $work + ['notes' => 'INTERNAL-WORK-NOTE'], $staffCsrf, $id, 4)['status'] === 200, 'progress persisted');
    httpCheck(post($staffJar, 'resolve', array_replace($work, ['workStatus' => 'Fully repaired', 'photo' => '']), $staffCsrf, $id, 5)['status'] === 422, 'completion requires evidence');
    $resolved = post($staffJar, 'resolve', array_replace($work, ['workStatus' => 'Fully repaired', 'actions' => ['Repair']]), $staffCsrf, $id, 5);
    httpCheck($resolved['status'] === 200, 'structured resolution');
    $case = httpCase(req($staffJar, 'api.php'), $id); $evidenceId = end($case['timeline'])['evidenceId'];
    httpCheck(req($staffJar, 'evidence.php?id=' . $evidenceId)['status'] === 200, 'assigned evidence delivery');
    httpCheck(req($otherJar, 'evidence.php?id=' . $evidenceId)['status'] === 404 && req($guestJar, 'evidence.php?id=' . $evidenceId)['status'] === 403, 'evidence private');
    $tracked = $public('track', $receipt);
    httpCheck(count($tracked['json']['concern']['progress']) === 3 && !str_contains($tracked['body'], 'INTERNAL') && !str_contains($tracked['body'], 'staff') && !str_contains($tracked['body'], 'image'), 'public progress excludes private work and identity');
    httpCheck(post($staffJar, 'verify', [], $staffCsrf, $id, 6)['status'] === 422, 'personnel cannot close');
    httpCheck(post($adminJar, 'verify', [], $adminCsrf, $id, 6)['status'] === 200, 'official closure');
    httpCheck($public('track', $receipt)['json']['concern']['status'] === 'Closed', 'public closure status');
    $rules = $report + ['purpose' => ConcernCatalog::GUIDANCE_PURPOSE, 'action1' => 'Use another safe route.', 'action2' => 'Keep children away from the damaged surface.', 'action3' => 'Do not try to patch the road yourself.'];
    httpCheck(post($staffJar, 'save_rule', $rules, $staffCsrf)['status'] === 422, 'personnel cannot manage recommendations');
    httpCheck(post($adminJar, 'save_rule', $rules, $adminCsrf)['status'] === 200, 'official rule management');
    httpCheck($public('guidance', $report)['json']['residentGuidance'][0] === 'Use another safe route.', 'public form uses curated resident guidance');
    httpCheck(post($adminJar, 'reset_rule', $rules, $adminCsrf)['status'] === 200, 'restore default suggestions');
    httpCheck(post($adminJar, 'update_user', ['role' => 'personnel', 'team' => 'Maintenance crew', 'active' => '1', 'email' => 'bad-email'], $adminCsrf, $staffId)['status'] === 422, 'edit staff email validation');
    $csv = req($adminJar, 'api.php?export=csv');
    httpCheck($csv['status'] === 200 && str_contains($csv['body'], 'Key points') && str_contains($csv['body'], 'Deep; Near school'), 'structured CSV report');
    httpCheck(req($staffJar, 'api.php?export=csv')['status'] === 403, 'personnel export forbidden');
    require __DIR__ . '/pages.php';
    // Persistent staff notifications have their own owner checks and CSRF boundary.
    httpCheck(req($guestJar,'api.php?view=notifications')['status']===401,'anonymous notification API blocked');
    pageHas(pageDocument($guestJar,'notifications.php'),'//form[@data-action="login"]','anonymous notification page gated');
    $inbox = req($staffJar,'api.php?view=notifications');
    httpCheck($inbox['status']===200 && $inbox['json']['unread']>0,'authenticated unread badge data');
    httpCheck(!str_contains($inbox['body'],'PRIVATE') && !str_contains($inbox['body'],'INTERNAL') && !str_contains($inbox['body'],'payload'),'notifications minimize private data');
    $notificationId = (string)$inbox['json']['items'][0]['id'];
    httpCheck(post($staffJar,'read_notification',[],'',$notificationId)['status']===403,'notification read CSRF');
    httpCheck(post($otherJar,'read_notification',[],$otherCsrf,$notificationId)['status']===422,'other user cannot mark notification');
    httpCheck(post($staffJar,'read_notification',[],$staffCsrf,$notificationId)['status']===200,'notification marked read through API');
    httpCheck(post($staffJar,'read_notification',[],$staffCsrf,'../1')['status']===422,'invalid notification ID rejected');
    httpCheck(post($staffJar,'read_all_notifications',[],$staffCsrf)['json']['unread']===0,'mark all unread count zero');
    httpCheck(req($adminJar,'api.php?view=notifications')['json']['unread']>0,'staff reads do not mark official notifications');
    foreach ([$adminJar,$staffJar] as $inboxJar) {
        $document=pageDocument($inboxJar,'notifications.php');
        pageHas($document,'//*[@data-notification-read-all]','notification center read all control');
        pageHas($document,'//*[@data-notification-link]','notification center links');
        pageHas($document,'//*[contains(@class,"notification-trigger")]','header notification bell');
    }
    $detailDocument=pageDocument($adminJar,'concern.php?id='.$id);
    pageHas($detailDocument,'//*[@id="evidence"]','before after section');
    pageHas($detailDocument,'//*[@id="recurrence"]','recurrence section');
    pageHas($detailDocument,'//*[@data-accept-priority]','priority recommendation control');
    httpCheck(str_contains(req($adminJar,'reports.php')['body'],'Recommended and official priorities'),'priority comparison report');
    httpCheck(str_contains(req($adminJar,'users.php')['body'],'Personnel workload'),'account workload table');
    // Exercise the preserved OTP recovery through the real local SMTP path.
    $resetJar = jar(); $resetCsrf = token($resetJar, 'login.php?view=forgot');
    httpCheck(auth($resetJar, 'request_reset', ['email' => 'staff@example.test'], $resetCsrf)['status'] === 200, 'reset request SMTP');
    $verifyScreen = req($resetJar, 'login.php?view=verify');
    httpCheck(str_contains($verifyScreen['body'], 'Verify your email') && preg_match('/id="resend-code"[^>]*data-seconds="[0-9]+"[^>]*disabled/', $verifyScreen['body']) === 1, 'verification page displays disabled resend countdown');
    httpCheck(auth($resetJar, 'request_reset', [], token($resetJar, 'login.php?view=verify'))['status'] === 422, 'direct resend cannot bypass server cooldown');
    $mail = $mailServer->messages();
    httpCheck(str_contains(end($mail)['body'] ?? '', 'MaintainPro Password Reset Code'), 'PHPMailer sends recovery subject through SMTP');
    preg_match('/\b([0-9]{6})\b/', end($mail)['body'], $match); $otp = $match[1] ?? '';
    httpCheck(strlen($otp) === 6, 'OTP received');
    $resetCsrf = token($resetJar, 'login.php?view=verify');
    httpCheck(auth($resetJar, 'verify_reset', ['code' => $otp], $resetCsrf)['status'] === 200, 'OTP verified');
    httpCheck(auth($resetJar, 'reset_password', ['password' => 'Recovered-password-42', 'confirm_password' => 'Recovered-password-42'], token($resetJar, 'login.php?view=reset'))['status'] === 200, 'password reset');
    $replay = auth($resetJar, 'reset_password', ['password' => 'Another-password-42', 'confirm_password' => 'Another-password-42'], token($resetJar, 'login.php'));
    httpCheck($replay['status'] === 200 && $replay['json']['redirect'] === 'login.php?view=forgot', 'used browser reset grant redirects to recovery');
    httpCheck(req($staffJar, 'api.php')['status'] === 401, 'reset revokes sessions');
    httpCheck(auth($resetJar, 'login', ['email' => 'staff@example.test', 'password' => 'Recovered-password-42'], token($resetJar, 'login.php'))['status'] === 200, 'recovered staff login');
    httpCheck(post($adminJar, 'reopen', ['feedback' => 'Needs further work'], $adminCsrf, $id, 7)['status'] === 200, 'official reopens');
    httpCheck(post($adminJar, 'assess', $assessment, $adminCsrf, $id, 8)['status'] === 200, 'reassessment');
    $mailServer->stop(); $mailServer = null;
    $failedMail = post($adminJar, 'assign', ['personnelId' => $otherCreated['id']], $adminCsrf, $id, 9);
    httpCheck($failedMail['status'] === 200 && $failedMail['json']['notification_sent'] === false && httpCase(req($adminJar, 'api.php'), $id)['assignedUserId'] === $otherCreated['id'], 'SMTP failure does not roll back assignment');
    httpCheck(str_contains(req($adminJar, 'complaint.php?id=' . $id)['body'], 'email could not be sent'), 'assignment failure notice');
    httpCheck(req($resetJar, 'complaint.php?id=' . $id)['status'] === 404 && req($resetJar, 'evidence.php?id=' . $evidenceId)['status'] === 404, 'reassignment revokes previous staff access');
    require __DIR__ . '/extended-http.php';
    require __DIR__ . '/account-reporting-http.php';
    require __DIR__ . '/system-upgrade-http.php';
    $limitedOfficial = post($adminJar, 'create_user', ['name' => 'Limited Official', 'email' => 'limited-official@example.test', 'role' => 'official'], $adminCsrf)['json']['created_account'];
    $limitedJar = jar();
    httpCheck(auth($limitedJar, 'login', ['email' => $limitedOfficial['email'], 'password' => $limitedOfficial['temporary_password']], token($limitedJar, 'login.php'))['status'] === 200, 'standard official signs in');
    httpCheck(auth($limitedJar, 'change_password', ['current_password' => $limitedOfficial['temporary_password'], 'password' => $password, 'confirm_password' => $password], token($limitedJar, 'login.php'))['status'] === 200, 'standard official finishes onboarding');
    $limitedCsrf = token($limitedJar);
    foreach (['admin.php', 'users.php', 'user-create.php', 'settings.php', 'audit.php'] as $restricted) httpCheck(req($limitedJar, $restricted)['status'] === 403, 'standard official blocked from ' . $restricted);
    httpCheck(req($limitedJar, 'reports.php')['status'] === 200, 'standard official keeps operational analytics');
    httpCheck(req($limitedJar, 'api.php')['json']['users'] === [], 'standard official API does not disclose account directory');
    httpCheck(post($limitedJar, 'create_user', ['name' => 'Denied', 'email' => 'denied@example.test', 'role' => 'resident'], $limitedCsrf)['status'] === 422, 'standard official cannot create accounts through API');
    httpCheck(req($limitedJar, 'backup.php')['status'] === 403, 'standard official blocked from backup endpoint');
    foreach (['.data/before-anonymous-20260921.sql', 'includes/store.php', 'config/mail.local.php', 'database/migrations/20260921_anonymous_concerns.sql', 'vendor/phpmailer/src/PHPMailer.php', 'tests/store.php', 'tools/check-mail.php', '%63onfig/mail.local.php'] as $path) httpCheck(req($guestJar, $path)['status'] === 404, 'private path ' . $path);
    $testDatabase->assertHealthyLog();
    httpCheck(!preg_match('/(?:Fatal error|Warning|Notice):/', file_get_contents($serverLog)), 'no PHP runtime diagnostics');
    echo "PASS: $checks HTTP, page, anonymous/privacy, staff, SMTP, OTP and permission checks.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($mailServer) $mailServer->stop();
    putenv($previousDatabase === false ? 'BR_DB_NAME' : 'BR_DB_NAME=' . $previousDatabase);
    putenv($previousSetupKey === false ? 'APP_SETUP_KEY' : 'APP_SETUP_KEY='.$previousSetupKey);
    $testDatabase->drop();
    if ($serverLog && is_file($serverLog)) unlink($serverLog);
    foreach ($jars as $cookieFile) if (is_file($cookieFile)) unlink($cookieFile);
}
