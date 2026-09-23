<?php
declare(strict_types=1);
// Always use a disposable MySQL database and a separate local PHP server.
require __DIR__ . '/support/database.php';
require __DIR__ . '/support/mail-server.php';
$testDatabase = new TestDatabase();
$server = null;
$mailServer = null;
$serverLog = tempnam(sys_get_temp_dir(), 'maintainpro-http-');
$previousDatabase = getenv('BR_DB_NAME');
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
    httpCheck(auth($adminJar, 'setup', $adminData, $adminCsrf)['status'] === 200, 'first official setup');
    $adminCsrf = token($adminJar);
    $guestCsrf = token($guestJar, 'report-concern.php');
    httpCheck(auth($guestJar, 'register', $adminData, $guestCsrf)['status'] === 422, 'registration retired');
    httpCheck(req($guestJar, 'api.php')['status'] === 401, 'anonymous private API denied');
    httpCheck(req($guestJar, 'public-api.php')['status'] === 405, 'public API POST only');
    $public = fn(string $action, array $data, ?string $csrf = null) => req($guestJar, 'public-api.php', ['action' => $action, 'data' => $data], $csrf ?? $guestCsrf);
    $report = ['category' => 'Roads and Infrastructure', 'concernType' => 'Pothole', 'keyPoints' => ['Deep', 'Near school'], 'purok' => 'PRIVATE-PUROK', 'street' => 'PRIVATE-STREET', 'exactArea' => 'PRIVATE-GATE', 'description' => '<script>alert(1)</script>'];
    httpCheck($public('submit', $report, '')['status'] === 403, 'anonymous report CSRF');
    $recommendations = $public('suggestions', $report);
    httpCheck($recommendations['status'] === 200 && count($recommendations['json']['suggestions']) === 3, 'three public suggestions');
    httpCheck($public('submit', array_replace($report, ['street' => '']))['status'] === 422, 'private location required');
    httpCheck($public('submit', array_replace($report, ['concernType' => 'Exposed wiring']))['status'] === 422, 'dependent type validation');
    httpCheck($public('submit', array_replace($report, ['keyPoints' => ['forged']]))['status'] === 422, 'key point validation');
    $receipt = $public('submit', $report);
    httpCheck($receipt['status'] === 200, 'anonymous concern submission');
    $receipt = $receipt['json']['receipt']; $id = $receipt['reference'];
    httpCheck(preg_match('/^CON-[0-9]{4}-[0-9]{6,}$/', $id) === 1 && strlen($receipt['trackingCode']) === 48, 'reference and secure code');
    $tracked = $public('track', $receipt);
    httpCheck($tracked['status'] === 200 && !str_contains($tracked['body'], 'PRIVATE') && !str_contains($tracked['body'], '<script>'), 'safe tracking allowlist');
    httpCheck($public('track', array_replace($receipt, ['trackingCode' => str_repeat('0', 48)]))['status'] === 422, 'wrong code blocked');
    httpCheck($public('track', $receipt, '')['status'] === 403, 'tracking CSRF');
    $created = post($adminJar, 'create_user', ['name' => 'HTTP Staff', 'email' => 'staff@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew'], $adminCsrf);
    httpCheck($created['status'] === 200, 'staff created');
    $account = $created['json']['created_account']; $staffId = $account['id'];
    httpCheck(!str_contains(json_encode($created['json']['state']), $account['temporary_password']), 'credential returned once outside state');
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
    httpCheck(count($mail) === 1 && str_contains($mail[0]['recipient'], 'staff@example.test') && str_contains($mail[0]['body'], $id), 'SMTP recipient and reference');
    httpCheck(!str_contains($mail[0]['body'], 'PRIVATE') && !str_contains($mail[0]['body'], 'INTERNAL'), 'assignment mail minimizes private data');
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
    $case = httpCase($resolved, $id); $evidenceId = end($case['timeline'])['evidenceId'];
    httpCheck(req($staffJar, 'evidence.php?id=' . $evidenceId)['status'] === 200, 'assigned evidence delivery');
    httpCheck(req($otherJar, 'evidence.php?id=' . $evidenceId)['status'] === 404 && req($guestJar, 'evidence.php?id=' . $evidenceId)['status'] === 403, 'evidence private');
    $tracked = $public('track', $receipt);
    httpCheck(count($tracked['json']['concern']['progress']) === 3 && !str_contains($tracked['body'], 'INTERNAL') && !str_contains($tracked['body'], 'staff') && !str_contains($tracked['body'], 'image'), 'public progress excludes private work and identity');
    httpCheck(post($staffJar, 'verify', [], $staffCsrf, $id, 6)['status'] === 422, 'personnel cannot close');
    httpCheck(post($adminJar, 'verify', [], $adminCsrf, $id, 6)['status'] === 200, 'official closure');
    httpCheck($public('track', $receipt)['json']['concern']['status'] === 'Closed', 'public closure status');
    $rules = $report + ['action1' => 'Inspect safely.', 'action2' => 'Assess suitable temporary repair.', 'action3' => 'Plan permanent repair.'];
    httpCheck(post($staffJar, 'save_rule', $rules, $staffCsrf)['status'] === 422, 'personnel cannot manage recommendations');
    httpCheck(post($adminJar, 'save_rule', $rules, $adminCsrf)['status'] === 200, 'official rule management');
    httpCheck($public('suggestions', $report)['json']['suggestions'][0] === 'Inspect safely.', 'public form uses curated library');
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
    $mail = $mailServer->messages();
    preg_match('/\b([0-9]{6})\b/', end($mail)['body'], $match); $otp = $match[1] ?? '';
    httpCheck(strlen($otp) === 6, 'OTP received');
    $resetCsrf = token($resetJar, 'login.php?view=verify');
    httpCheck(auth($resetJar, 'verify_reset', ['code' => $otp], $resetCsrf)['status'] === 200, 'OTP verified');
    httpCheck(auth($resetJar, 'reset_password', ['password' => 'Recovered-password-42', 'confirm_password' => 'Recovered-password-42'], token($resetJar, 'login.php?view=reset'))['status'] === 200, 'password reset');
    httpCheck(req($staffJar, 'api.php')['status'] === 401, 'reset revokes sessions');
    httpCheck(auth($resetJar, 'login', ['email' => 'staff@example.test', 'password' => 'Recovered-password-42'], token($resetJar, 'login.php'))['status'] === 200, 'recovered staff login');
    httpCheck(post($adminJar, 'reopen', ['feedback' => 'Needs further work'], $adminCsrf, $id, 7)['status'] === 200, 'official reopens');
    httpCheck(post($adminJar, 'assess', $assessment, $adminCsrf, $id, 8)['status'] === 200, 'reassessment');
    $mailServer->stop(); $mailServer = null;
    $failedMail = post($adminJar, 'assign', ['personnelId' => $otherCreated['id']], $adminCsrf, $id, 9);
    httpCheck($failedMail['status'] === 200 && $failedMail['json']['notification_sent'] === false && httpCase($failedMail, $id)['assignedUserId'] === $otherCreated['id'], 'SMTP failure does not roll back assignment');
    httpCheck(str_contains(req($adminJar, 'complaint.php?id=' . $id)['body'], 'email could not be sent'), 'assignment failure notice');
    httpCheck(req($resetJar, 'complaint.php?id=' . $id)['status'] === 404 && req($resetJar, 'evidence.php?id=' . $evidenceId)['status'] === 404, 'reassignment revokes previous staff access');
    foreach (['.data/before-anonymous-20260921.sql', 'includes/store.php', 'config/mail.local.php', 'database/migrations/20260921_anonymous_concerns.sql', 'vendor/phpmailer/src/PHPMailer.php', 'tests/store.php', 'tools/check-mail.php', '%63onfig/mail.local.php'] as $path) httpCheck(req($guestJar, $path)['status'] === 404, 'private path ' . $path);
    httpCheck(!preg_match('/(?:Fatal error|Warning|Notice):/', file_get_contents($serverLog)), 'no PHP runtime diagnostics');
    echo "PASS: $checks HTTP, page, anonymous/privacy, staff, SMTP, OTP and permission checks.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($mailServer) $mailServer->stop();
    putenv($previousDatabase === false ? 'BR_DB_NAME' : 'BR_DB_NAME=' . $previousDatabase);
    $testDatabase->drop();
    if ($serverLog && is_file($serverLog)) unlink($serverLog);
    foreach ($jars as $cookieFile) if (is_file($cookieFile)) unlink($cookieFile);
}
