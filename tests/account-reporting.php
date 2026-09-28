<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/store.php';
require __DIR__ . '/support/database.php';
date_default_timezone_set('Asia/Manila');
$report = ['category' => 'Street Lighting', 'concernType' => 'Light not working', 'keyPoints' => ['Completely dark'], 'purok' => 'Purok 1', 'street' => 'Main Street', 'exactArea' => 'Crossing'];
if (($argv[1] ?? '') === '--worker') {
    DatabaseMaintenance::requireTestDatabase($argv[2]);
    $store = new ComplaintStore(br_database($argv[2]));
    try { $store->submitAccount($argv[3], $report); echo 'saved'; }
    catch (DomainException $e) { if (!str_contains($e->getMessage(), 'maximum of 3')) throw $e; echo 'limited'; }
    exit;
}
$test = new TestDatabase(); $checks = 0;
function reportCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function reportDenied(callable $call, string $label): void {
    try { $call(); } catch (DomainException $e) { reportCheck(true, $label); return; }
    throw new RuntimeException('FAIL: expected denial: ' . $label);
}
try {
    $db = $test->connect(); $store = new ComplaintStore($db); $fixtures = new DatabaseTestFixtures($db);
    $password = 'Reporting-password-42';
    $official = $store->setup(['name' => 'Report Official', 'email' => 'official@example.test', 'password' => $password]);
    $resident = $store->register(['name' => 'Report Resident', 'email' => 'resident@example.test', 'password' => $password, 'role' => 'official', 'team' => 'Maintenance crew']);
    reportCheck($resident['role'] === 'resident' && $resident['team'] === '', 'registration cannot elevate account');
    $personnel = $store->createUser($official['id'], ['name' => 'Report Personnel', 'email' => 'personnel@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew']);
    reportDenied(fn() => $store->submitAccount($personnel['id'], $report), 'temporary account cannot report');
    $personnel = $store->changeTemporaryPassword($personnel['id'], ['current_password' => $personnel['temporary_password'], 'password' => $password, 'confirm_password' => $password]);
    reportCheck($store->login($resident['email'], $password, 'resident-login')['id'] === $resident['id'], 'resident can sign in');
    $ids = [];
    foreach ([$resident, $personnel, $official] as $account) {
        for ($i = 0; $i < 3; $i++) {
            reportCheck($store->submissionAllowance($account['id'])['used'] === $i, $account['role'] . ' allowance before submission');
            $id = $store->mutate($account['id'], 'submit', '', $report + ['isAnonymous' => $i === 1, 'residentId' => $official['id'], 'role' => 'official', 'assignedUserId' => $account['id'], 'priority' => 'Urgent'], null);
            $ids[$account['role']][] = $id;
            $raw = array_values(array_filter($store->state()['cases'], fn($c) => $c['id'] === $id))[0];
            reportCheck($raw['residentId'] === $account['id'], 'session account owns report despite forged input');
            reportCheck($raw['assignedUserId'] === null && $raw['priority'] === 'Medium' && $raw['status'] === 'Submitted', 'submission neither assigns nor changes priority');
            $view = $store->concernForActor($official['id'], $id);
            reportCheck(!array_key_exists('residentId', $view), 'private account ID excluded from projection');
            if ($i === 1) {
                $json = json_encode($view);
                reportCheck($view['resident'] === 'Anonymous' && $view['submitterRole'] === null, 'anonymous label has no role');
                foreach ([$account['id'], $account['name'], $account['email']] as $secret) reportCheck(!str_contains($json, $secret), 'anonymous identity omitted from payload');
            } else reportCheck($view['resident'] === $account['name'] && $view['submitterRole'] === $account['role'], 'identified name and role from account');
            reportCheck($store->user($account['id'])['role'] === $account['role'], 'submitting preserves role');
        }
        reportDenied(fn() => (new ComplaintStore($test->connect()))->submitAccount($account['id'], $report), 'fourth report denied on fresh connection');
        reportCheck($store->submissionAllowance($account['id'])['used'] === 3, 'anonymous report counted');
    }
    reportCheck($store->recentConcerns($personnel['id']) === [], 'own reports do not enter work queue');
    reportCheck(count($store->pagedConcerns($personnel['id'], ['scope' => 'mine'])['items']) === 3, 'personnel own report list');
    reportCheck($store->concernForActor($resident['id'], $ids['personnel'][0]) === null, 'resident cannot see other reports');
    reportDenied(fn() => $store->weeklyConcerns($resident['id']), 'resident cannot read official weekly analytics');
    reportDenied(fn() => $store->weeklyConcerns($personnel['id']), 'personnel cannot read official weekly analytics');
    $weekly = $store->weeklyConcerns($official['id']);
    reportCheck(count($weekly['groups']) === 1 && $weekly['groups'][0]['count'] === 9, 'weekly count includes all roles and anonymous reports');
    reportCheck(count($weekly['groups'][0]['actions']) === 3 && $weekly['groups'][0]['recurring'], 'weekly suggestions return exactly three recurrence-aware solutions');
    reportCheck(!str_contains(json_encode($weekly), $resident['id']) && !str_contains(json_encode($weekly), $resident['name']), 'weekly statistics omit identity');
    reportCheck(count($store->notifications($official['id'])['items']) >= 9, 'submission notifications still work');
    $store->submitGuest($report, 'weekly-guest');
    reportCheck($store->weeklyConcerns($official['id'])['groups'][0]['count'] === 10, 'guest workflow preserved and counted');
    $store->updateUser($official['id'], $personnel['id'], ['role' => 'official', 'active' => '1']);
    reportDenied(fn() => $store->submitAccount($personnel['id'], $report), 'role change cannot reset account allowance');
    $store->updateUser($official['id'], $personnel['id'], ['role' => 'personnel', 'team' => 'Maintenance crew', 'active' => '1']);
    $target = $ids['personnel'][0];
    $worker = $store->createUser($official['id'], ['name' => 'Other Worker', 'email' => 'other@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew']);
    $store->mutate($official['id'], 'assess', $target, ['priority' => 'High', 'recommendation' => 'Inspect the light.'], 1);
    $store->mutate($official['id'], 'assign', $target, ['personnelId' => $worker['id']], 2);
    reportDenied(fn() => $store->mutate($personnel['id'], 'start', $target, [], 3), 'reporter cannot work on another assignee task');
    reportCheck(!$store->concernForActor($personnel['id'], $target)['canWork'], 'reporter UI receives no work permission');
    $store->mutate($official['id'], 'request_information', $ids['resident'][1], ['notes' => 'Which light?'], 1);
    $store->mutate($resident['id'], 'information', $ids['resident'][1], ['description' => 'The light beside the crossing.'], 2);
    reportCheck($store->concernForActor($official['id'], $ids['resident'][1])['status'] === 'Submitted', 'account follow-up returns concern to review');
    reportCheck($store->submissionAllowance($resident['id'])['used'] === 3, 'follow-ups do not consume a new submission');
    $midnight = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $fixtures->ageConcern($ids['resident'][0], $midnight->modify('-1 second')->format(DATE_ATOM));
    reportCheck($store->submissionAllowance($resident['id'])['used'] === 2, 'previous calendar day excluded');
    $fixtures->ageConcern($ids['resident'][0], $midnight->format(DATE_ATOM));
    reportCheck($store->submissionAllowance($resident['id'])['used'] === 3, 'midnight belongs to current day');
    foreach ($ids['resident'] as $id) $fixtures->ageConcern($id, $midnight->modify('-1 day')->format(DATE_ATOM));
    reportCheck($store->submissionAllowance($resident['id'])['used'] === 0, 'allowance resets when all reports are on previous day');
    $store->submitAccount($resident['id'], $report);
    reportCheck($store->submissionAllowance($resident['id'])['used'] === 1, 'can submit on following day');
    // Fixed dates cover a year boundary as well as Monday/Sunday semantics.
    $period = ConcernInsights::week(new DateTimeImmutable('2027-01-03T23:59:59+08:00'));
    reportCheck($period['date'] === '2026-12-28' && str_starts_with($period['end'], '2027-01-04'), 'Monday week crosses year correctly');
    $fixtures->ageConcern($ids['official'][0], $weekly['end']);
    $countAtEnd = $store->weeklyConcerns($official['id'])['groups'][0]['count'];
    $fixtures->ageConcern($ids['official'][0], $weekly['start']);
    reportCheck($store->weeklyConcerns($official['id'])['groups'][0]['count'] === $countAtEnd + 1, 'weekly end excluded and start included');
    $race = $store->register(['name' => 'Concurrent Reporter', 'email' => 'race@example.test', 'password' => $password]);
    $store->submitAccount($race['id'], $report); $store->submitAccount($race['id'], $report);
    $workers = [];
    for ($i = 0; $i < 4; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $test->name, $race['id']], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
        fclose($pipes[0]); $workers[] = [$process, $pipes];
    }
    $saved = 0;
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        reportCheck(proc_close($process) === 0 && $errors === '', 'concurrent worker completed');
        if ($output === 'saved') $saved++;
    }
    reportCheck($saved === 1 && $store->submissionAllowance($race['id'])['used'] === 3, 'concurrent submissions cannot exceed daily limit');
    $sample = $store->state()['cases'][0];
    $rankingCases = [];
    for ($i = 0; $i < 7; $i++) for ($j = 0; $j <= $i; $j++) $rankingCases[] = array_replace($sample, ['category' => 'Other', 'concernType' => 'Type ' . $i]);
    $ranking = ConcernInsights::weekly($rankingCases, []);
    reportCheck(count($ranking) === 5 && array_column($ranking, 'count') === [7, 6, 5, 4, 3], 'top five groups ranked by frequency');
    reportCheck($store->concernForActor($official['id'], $ids['official'][1])['resident'] === 'Anonymous', 'anonymous official preserved before assessment');
    $store->mutate($official['id'], 'assess', $ids['official'][1], ['priority' => 'High', 'recommendation' => 'Inspect affected fixtures.'], 1);
    $anonymousAfterReview = json_encode($store->concernForActor($personnel['id'], $ids['official'][1]) ?? $store->concernForActor($official['id'], $ids['official'][1]));
    reportCheck(!str_contains($anonymousAfterReview, $official['id']) && !str_contains($anonymousAfterReview, $official['name']), 'self-review does not leak anonymous reporter through priority history');
    $code = '';
    $resetId = $store->requestPasswordReset($resident['email'], 'reporting-reset', function ($email, $otp) use (&$code) { $code = $otp; });
    reportCheck(strlen($code) === 6, 'restored resident accounts can request password recovery');
    $resetToken = $store->verifyPasswordReset($resetId, $code);
    $store->resetPassword($resetId, $resetToken, ['password' => 'Replacement-password-91', 'confirm_password' => 'Replacement-password-91']);
    reportCheck($store->login($resident['email'], 'Replacement-password-91', 'after-report-reset')['id'] === $resident['id'], 'resident password recovery preserves account access');
    echo "PASS: $checks account reporting, privacy, daily limits, concurrency, permissions and weekly insights checks.\n";
} finally { $test->drop(); }
