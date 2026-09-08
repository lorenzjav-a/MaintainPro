<?php
declare(strict_types=1);
// Run against a separate server with BR_DATABASE_PATH set to a new test file.
$base = $argv[1] ?? 'http://127.0.0.1:8081';
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
    $adminJar = jar(); $residentJar = jar(); $otherJar = jar(); $staffJar = jar(); $demoJar = jar();
    $password = 'Http-test-password-42';
    $adminData = ['name' => 'HTTP Official', 'email' => 'http-official@example.test', 'password' => $password, 'confirm_password' => $password];
    $residentData = ['name' => 'HTTP Resident', 'email' => 'http-resident@example.test', 'password' => $password, 'confirm_password' => $password, 'role' => 'official'];
    httpCheck(req($adminJar, 'api.php')['status'] === 401, 'anonymous API access denied');
    $setup = req($adminJar, 'login.php');
    httpCheck(str_contains($setup['body'], 'data-action="setup"'), 'empty test database shows setup');
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
    httpCheck(post($adminJar, 'create_user', ['name' => 'HTTP Staff', 'email' => 'http-staff@example.test', 'password' => $password, 'role' => 'personnel', 'team' => 'Sanitation team'], $adminCsrf)['status'] === 200, 'official creates personnel');
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
    httpCheck(auth($staffJar, 'login', ['email' => 'http-staff@example.test', 'password' => $password], token($staffJar, 'login.php'))['status'] === 200, 'personnel signs in');
    $staffCsrf = token($staffJar);
    $staffState = req($staffJar, 'api.php');
    $staffId = $staffState['json']['actor']['id'];
    httpCheck(count($staffState['json']['cases']) === 1, 'assigned team sees report');
    httpCheck(post($staffJar, 'start', [], $staffCsrf, $id, 3)['status'] === 200, 'personnel starts work');
    httpCheck(post($staffJar, 'resolve', ['notes' => 'Drain cleared and flow checked.'], $staffCsrf, $id, 4)['status'] === 200, 'personnel resolves');
    httpCheck(post($residentJar, 'verify', ['feedback' => 'Confirmed fixed.'], $residentCsrf, $id, 5)['status'] === 200, 'resident verifies');
    httpCheck(auth($residentJar, 'logout', [], $residentCsrf)['status'] === 200, 'resident logs out');
    httpCheck(req($residentJar, 'api.php')['status'] === 401, 'logged-out session loses access');
    httpCheck(auth($residentJar, 'login', ['email' => $residentData['email'], 'password' => $password], token($residentJar, 'login.php'))['status'] === 200, 'resident logs back in');
    $residentCsrf = token($residentJar);
    httpCheck(httpCase(req($residentJar, 'api.php'), $id)['status'] === 'Verified', 'records persist across logout and login');
    httpCheck(auth($demoJar, 'demo', [], token($demoJar, 'login.php'))['status'] === 200, 'separate demo remains available');
    $demoCsrf = token($demoJar);
    $demoState = req($demoJar, 'api.php')['json'];
    httpCheck($demoState['mode'] === 'demo' && count($demoState['cases']) === 14, 'demo has fictional records');
    httpCheck(post($demoJar, 'reset', [], $demoCsrf)['status'] === 200, 'demo reset works');
    httpCheck(count(req($adminJar, 'api.php')['json']['cases']) === 1, 'demo reset cannot affect saved workspace');
    httpCheck(post($demoJar, 'create_user', [], $demoCsrf)['status'] === 422, 'demo official cannot create saved accounts');
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
    foreach (['.data/http-integration.sqlite', 'includes/store.php', 'tests/store.php', '%2edata/http-integration.sqlite'] as $privatePath) httpCheck(req($adminJar, $privatePath)['status'] === 404, 'private path blocked');
    foreach (['styles.css', 'app.js', 'auth-ui.js', 'assets/vendor/bootstrap.min.css', 'assets/vendor/sweetalert2.all.min.js'] as $asset) httpCheck(req($adminJar, $asset)['status'] === 200, 'local dependency served');
    echo "PASS: $checks HTTP checks for accounts, workflow, persistence, role isolation, CSRF, session revocation, exports, demo separation, and private-file protection.\n";
} finally {
    foreach ($jars as $cookieFile) if (is_file($cookieFile)) unlink($cookieFile);
}
