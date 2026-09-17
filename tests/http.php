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
    $adminJar = jar(); $residentJar = jar(); $otherJar = jar(); $staffJar = jar(); $demoJar = jar();
    $password = 'Http-test-password-42';
    $adminData = ['name' => 'HTTP Official', 'email' => 'http-official@example.test', 'password' => $password, 'confirm_password' => $password];
    $residentData = ['name' => 'HTTP Resident', 'email' => 'http-resident@example.test', 'password' => $password, 'confirm_password' => $password, 'role' => 'official'];
    httpCheck(req($adminJar, 'api.php')['status'] === 401, 'anonymous API access denied');
    $setup = req($adminJar, 'login.php');
    httpCheck(str_contains($setup['body'], 'data-action="setup"'), 'empty test database shows setup');
    httpCheck(!str_contains($setup['body'], 'open-demo'), 'sample workspace entry removed');
    $adminCsrf = token($adminJar, 'login.php');
    httpCheck(auth($adminJar, 'setup', $adminData, '')['status'] === 403, 'setup requires CSRF');
    httpCheck(auth($adminJar, 'setup', $adminData, $adminCsrf)['status'] === 200, 'first official setup');
    $newCsrf = token($adminJar);
    httpCheck($newCsrf !== $adminCsrf, 'CSRF rotated at sign-in');
    $adminCsrf = $newCsrf;
    $adminState = req($adminJar, 'api.php');
    httpCheck($adminState['json']['mode'] === 'account' && count($adminState['json']['cases']) === 0, 'saved workspace starts empty');
    httpCheck(!str_contains($adminState['body'], 'password_hash'), 'password hashes absent from API');
    httpCheck(auth($adminJar, 'setup', $adminData, $adminCsrf)['status'] === 422, 'setup cannot be repeated');
    httpCheck(auth($residentJar, 'register', $residentData, token($residentJar, 'login.php'))['status'] === 200, 'resident registers');
    $residentCsrf = token($residentJar);
    $residentState = req($residentJar, 'api.php');
    httpCheck($residentState['json']['actor']['role'] === 'resident', 'registration role elevation ignored');
    $residentId = $residentState['json']['actor']['id'];
    httpCheck(post($residentJar, 'switch_role', ['role' => 'official'], $residentCsrf)['status'] === 403, 'saved account cannot switch roles');
    httpCheck(post($residentJar, 'reset', [], $residentCsrf)['status'] === 403, 'saved records cannot be reset with demo action');
    httpCheck(post($residentJar, 'create_user', [], $residentCsrf)['status'] === 422, 'resident cannot create staff');
    $otherData = array_replace($residentData, ['name' => 'Other HTTP Resident', 'email' => 'http-other@example.test']);
    httpCheck(auth($otherJar, 'register', $otherData, token($otherJar, 'login.php'))['status'] === 200, 'second resident registers');
    $otherCsrf = token($otherJar);
    $staffCreated = post($adminJar, 'create_user', ['name' => 'HTTP Staff', 'email' => 'http-staff@example.test', 'role' => 'personnel', 'team' => 'Sanitation team'], $adminCsrf);
    httpCheck($staffCreated['status'] === 200, 'official creates personnel without manually choosing password');
    $temporaryPassword = $staffCreated['json']['created_account']['temporary_password'];
    httpCheck(strlen($temporaryPassword) >= 20 && !str_contains(json_encode($staffCreated['json']['state']), $temporaryPassword), 'generated password returned once outside saved state');
    $report = ['title' => 'HTTP drainage report', 'category' => 'Drainage and flooding', 'description' => 'Blocked drainage at a test location.', 'location' => 'Test Street, Purok 3', 'suggestion' => 'Inspect and clear it.'];
    $submitted = post($residentJar, 'submit', $report, $residentCsrf);
    httpCheck($submitted['status'] === 200, 'resident submits saved report');
    $id = $submitted['json']['id'];
    httpCheck(count(req($otherJar, 'api.php')['json']['cases']) === 0, 'separate resident scope');
    httpCheck(post($otherJar, 'verify', [], $otherCsrf, $id, 1)['status'] === 422, 'other resident cannot alter report');
    $assessment = ['category' => 'Drainage and flooding', 'priority' => 'High', 'recommendation' => 'Inspect and clear the drain.'];
    httpCheck(post($adminJar, 'assess', $assessment, $adminCsrf, $id, 1)['status'] === 200, 'official assesses');
    $stale = post($adminJar, 'assess', array_replace($assessment, ['priority' => 'Urgent']), $adminCsrf, $id, 1);
    httpCheck($stale['status'] === 409 && httpCase($stale, $id)['priority'] === 'High', 'stale HTTP update rejected with fresh state');
    httpCheck(post($adminJar, 'assign', ['team' => 'Sanitation team'], $adminCsrf, $id, 2)['status'] === 200, 'official assigns');
    $staffLogin = auth($staffJar, 'login', ['email' => 'http-staff@example.test', 'password' => $temporaryPassword], token($staffJar, 'login.php'));
    httpCheck($staffLogin['status'] === 200 && $staffLogin['json']['redirect'] === 'login.php?view=change-password', 'temporary login requires own password');
    httpCheck(req($staffJar, 'api.php')['status'] === 403, 'pending account cannot read complaints');
    httpCheck(req($staffJar, 'api.php?export=csv')['status'] === 403, 'pending account cannot export');
    $forcedPage = req($staffJar, 'index.php');
    httpCheck(str_contains($forcedPage['body'], 'data-action="change_password"') && !str_contains($forcedPage['body'], 'Resident registration'), 'index redirects to forced password screen');
    $staffCsrf = token($staffJar, 'login.php');
    httpCheck(post($staffJar, 'start', [], $staffCsrf, $id, 3)['status'] === 403, 'pending account cannot act on complaints');
    $change = ['current_password' => $temporaryPassword, 'password' => $password, 'confirm_password' => $password];
    httpCheck(auth($staffJar, 'change_password', $change, '')['status'] === 403, 'initial change requires CSRF');
    httpCheck(auth($staffJar, 'change_password', array_replace($change, ['current_password' => 'wrong']), $staffCsrf)['status'] === 422, 'initial change validates temporary password');
    httpCheck(auth($staffJar, 'change_password', array_replace($change, ['confirm_password' => 'wrong']), $staffCsrf)['status'] === 422, 'initial change validates confirmation');
    httpCheck(auth($staffJar, 'change_password', $change, $staffCsrf)['status'] === 200, 'own password completes onboarding');
    $staffCsrf = token($staffJar);
    $staffState = req($staffJar, 'api.php');
    $staffId = $staffState['json']['actor']['id'];
    httpCheck(count($staffState['json']['cases']) === 1, 'assigned team sees report');
    httpCheck(!str_contains(req($adminJar, 'api.php')['body'], $temporaryPassword), 'later API reads never repeat temporary password');
    httpCheck(post($staffJar, 'start', [], $staffCsrf, $id, 3)['status'] === 200, 'personnel starts work');
    httpCheck(post($staffJar, 'resolve', ['notes' => 'Drain cleared and flow checked.'], $staffCsrf, $id, 4)['status'] === 200, 'personnel resolves');
    httpCheck(post($residentJar, 'verify', ['feedback' => 'Confirmed fixed.'], $residentCsrf, $id, 5)['status'] === 200, 'resident verifies');
    httpCheck(auth($residentJar, 'logout', [], $residentCsrf)['status'] === 200, 'resident logs out');
    httpCheck(req($residentJar, 'api.php')['status'] === 401, 'logged-out session loses access');
    httpCheck(auth($residentJar, 'login', ['email' => $residentData['email'], 'password' => $password], token($residentJar, 'login.php'))['status'] === 200, 'resident logs back in');
    $residentCsrf = token($residentJar);
    httpCheck(httpCase(req($residentJar, 'api.php'), $id)['status'] === 'Verified', 'records persist across logout and login');
    httpCheck(auth($demoJar, 'demo', [], token($demoJar, 'login.php'))['status'] === 422, 'removed demo action is rejected');
    httpCheck(req($demoJar, 'api.php')['status'] === 401, 'demo request cannot bypass sign-in');
    httpCheck(post($adminJar, 'reset', [], $adminCsrf)['status'] === 403, 'official cannot restore sample records');
    httpCheck(count(req($adminJar, 'api.php')['json']['cases']) === 1, 'rejected reset preserves saved records');
    $parallelJar = jar();
    httpCheck(auth($parallelJar, 'login', ['email' => $residentData['email'], 'password' => $password], token($parallelJar, 'login.php'))['status'] === 200, 'second session signs in');
    $newPassword = 'Http-new-password-74';
    $profile = ['name' => 'Updated HTTP Resident', 'email' => 'http-updated@example.test', 'current_password' => $password, 'new_password' => $newPassword, 'confirm_new_password' => $newPassword];
    httpCheck(post($residentJar, 'profile', $profile, $residentCsrf)['status'] === 200, 'profile and password updated');
    httpCheck(req($parallelJar, 'api.php')['status'] === 401, 'password change revokes previous sessions');
    httpCheck(req($residentJar, 'api.php')['status'] === 200, 'password-change session remains signed in');
    httpCheck(post($adminJar, 'update_user', ['role' => 'personnel', 'team' => 'Sanitation team', 'active' => '0'], $adminCsrf, $staffId)['status'] === 200, 'official deactivates account');
    httpCheck(req($staffJar, 'api.php')['status'] === 401, 'deactivation revokes active session');
    httpCheck(req($residentJar, 'api.php?export=csv')['status'] === 403, 'resident cannot export all records');
    $csv = req($adminJar, 'api.php?export=csv');
    httpCheck($csv['status'] === 200 && str_contains($csv['body'], 'HTTP drainage report') && str_contains($csv['body'], 'Verified'), 'official CSV reflects saved outcome');
    httpCheck(post($adminJar, 'assess', $assessment, '', $id, 6)['status'] === 403, 'mutation requires CSRF');
    $resetJar = jar();
    $forgot = req($resetJar, 'login.php?view=forgot');
    httpCheck(str_contains($forgot['body'], 'data-action="request_reset"') && !str_contains($forgot['body'], 'id="account-password"'), 'forgot-password screen asks only for email');
    httpCheck(str_contains(req($resetJar, 'login.php')['body'], 'Forgot password?'), 'sign-in links to recovery');
    httpCheck(str_contains(req($resetJar, 'login.php?view=reset')['body'], 'data-action="request_reset"'), 'new-password screen guarded before verification');
    $resetCsrf = token($resetJar, 'login.php?view=forgot');
    httpCheck(auth($resetJar, 'request_reset', ['email' => 'http-updated@example.test'], '')['status'] === 403, 'OTP request requires CSRF');
    $request = auth($resetJar, 'request_reset', ['email' => 'http-updated@example.test'], $resetCsrf);
    httpCheck($request['status'] === 200 && $request['json']['redirect'] === 'login.php?view=verify', 'OTP request opens verification');
    $emails = $mailServer->messages();
    httpCheck(count($emails) === 1 && str_contains($emails[0]['recipient'], 'http-updated@example.test'), 'PHPMailer delivers OTP through SMTP to registered email');
    $mailBody = quoted_printable_decode($emails[0]['body']);
    if (!preg_match('/reset code is: ([0-9]{6})/', $mailBody, $match)) throw new RuntimeException('Test email did not contain OTP.');
    $otp = $match[1];
    httpCheck(str_contains($mailBody, 'Content-Type: multipart/alternative') && str_contains($mailBody, '10 minutes'), 'email includes HTML, plain text, and expiry');
    httpCheck(!str_contains($request['body'], $otp) && !str_contains($request['body'], 'token'), 'request API does not expose OTP or reset grant');
    $resetCsrf = token($resetJar, 'login.php?view=verify');
    $recoveryPassword = ['password' => 'Recovered-password-92', 'confirm_password' => 'Recovered-password-92'];
    httpCheck(auth($resetJar, 'reset_password', $recoveryPassword, $resetCsrf)['status'] === 422, 'API prevents reset before OTP');
    httpCheck(auth($resetJar, 'request_reset', [], $resetCsrf)['status'] === 422, 'resend cooldown enforced over HTTP');
    httpCheck(auth($resetJar, 'verify_reset', ['code' => '000000'], $resetCsrf)['status'] === 422, 'incorrect code rejected over HTTP');
    $outsiderJar = jar();
    $outsiderCsrf = token($outsiderJar, 'login.php');
    httpCheck(auth($outsiderJar, 'verify_reset', ['code' => $otp], $outsiderCsrf)['status'] === 422, 'OTP cannot verify a different browser session');
    $verification = auth($resetJar, 'verify_reset', ['code' => $otp], $resetCsrf);
    httpCheck($verification['status'] === 200 && $verification['json']['redirect'] === 'login.php?view=reset', 'correct code unlocks new-password screen');
    httpCheck(!str_contains($verification['body'], 'token'), 'verification grant stays on server');
    $resetPage = req($resetJar, 'login.php?view=reset');
    httpCheck(str_contains($resetPage['body'], 'data-action="reset_password"') && str_contains($resetPage['body'], 'id="confirm-password"'), 'verified session sees password confirmation');
    $resetCsrf = token($resetJar, 'login.php?view=reset');
    httpCheck(auth($resetJar, 'reset_password', array_replace($recoveryPassword, ['confirm_password' => 'mismatch']), $resetCsrf)['status'] === 422, 'reset confirmation mismatch rejected');
    httpCheck(auth($resetJar, 'reset_password', $recoveryPassword, '')['status'] === 403, 'password reset requires CSRF');
    httpCheck(auth($resetJar, 'reset_password', $recoveryPassword, $resetCsrf)['status'] === 200, 'verified password reset succeeds');
    httpCheck(req($residentJar, 'api.php')['status'] === 401, 'email reset revokes existing signed-in session');
    $signIn = req($resetJar, 'login.php');
    httpCheck(str_contains($signIn['body'], 'Your password was reset.'), 'reset success shown at sign-in');
    $resetCsrf = token($resetJar, 'login.php');
    httpCheck(auth($resetJar, 'reset_password', $recoveryPassword, $resetCsrf)['status'] === 422, 'reset session cannot be reused');
    httpCheck(auth($resetJar, 'login', ['email' => 'http-updated@example.test', 'password' => $newPassword], $resetCsrf)['status'] === 422, 'previous password stops working');
    httpCheck(auth($resetJar, 'login', ['email' => 'http-updated@example.test', 'password' => $recoveryPassword['password']], $resetCsrf)['status'] === 200, 'recovered account can sign in');
    httpCheck(httpCase(req($resetJar, 'api.php'), $id)['status'] === 'Verified', 'reset preserves complaint records');
    $unknownJar = jar();
    $unknownRequest = auth($unknownJar, 'request_reset', ['email' => 'not-registered@example.test'], token($unknownJar, 'login.php?view=forgot'));
    httpCheck($unknownRequest['json'] === $request['json'] && count($mailServer->messages()) === 1, 'unknown email gets same response without sending');
    foreach (['.data/private', 'includes/store.php', 'includes/mail.local.php', 'includes/PHPMailer/src/PHPMailer.php', 'database/database.php', 'tests/store.php', '%2edata/private', 'database/schema.sql', 'database/setup.php', 'config/database.php', 'config/mail.local.php', 'config/mail.example.php', 'vendor/phpmailer/src/PHPMailer.php', 'tools/check-mail.php', 'tests/support/database.php', '%63onfig/mail.local.php'] as $privatePath) httpCheck(req($adminJar, $privatePath)['status'] === 404, 'private path blocked: ' . $privatePath);
    foreach (['assets/css/app.css', 'assets/js/app.js', 'assets/js/auth.js', 'assets/images/favicon.svg', 'assets/vendor/bootstrap.min.css', 'assets/vendor/sweetalert2.all.min.js'] as $asset) httpCheck(req($adminJar, $asset)['status'] === 200, 'local dependency served: ' . $asset);
    require __DIR__ . '/pages.php';
    echo "PASS: $checks HTTP checks for accounts, complaint workflow, PHP pages, role permissions, PHPMailer SMTP, OTP recovery, sessions, and private-file protection.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($mailServer) $mailServer->stop();
    putenv($previousDatabase === false ? 'BR_DB_NAME' : 'BR_DB_NAME=' . $previousDatabase);
    $testDatabase->drop();
    if ($serverLog && is_file($serverLog)) unlink($serverLog);
    foreach ($jars as $cookieFile) if (is_file($cookieFile)) unlink($cookieFile);
}
