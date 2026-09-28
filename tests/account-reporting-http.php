<?php
declare(strict_types=1);
if (!isset($testDatabase, $adminJar)) throw new RuntimeException('Run through tests/http.php.');
$reporterJar = jar(); $reporterCsrf = token($reporterJar, 'login.php?view=register');
$newResident = ['name' => 'Reporting Resident HTTP', 'email' => 'reporter@example.test', 'password' => $password, 'confirm_password' => $password, 'role' => 'official'];
httpCheck(auth($reporterJar, 'register', $newResident, $reporterCsrf)['status'] === 200, 'resident registration restored');
$reporterCsrf = token($reporterJar);
$reporter = req($reporterJar, 'api.php')['json']['actor'];
httpCheck($reporter['role'] === 'resident', 'registration ignores forged role');
$reportStaff = post($adminJar, 'create_user', ['name' => 'Reporting Personnel HTTP', 'email' => 'reporting-staff@example.test', 'role' => 'personnel', 'team' => 'Maintenance crew'], $adminCsrf)['json']['created_account'];
$reportStaffJar = jar();
httpCheck(auth($reportStaffJar, 'login', ['email' => $reportStaff['email'], 'password' => $reportStaff['temporary_password']], token($reportStaffJar, 'login.php'))['status'] === 200, 'new personnel sign in');
httpCheck(auth($reportStaffJar, 'change_password', ['current_password' => $reportStaff['temporary_password'], 'password' => $password, 'confirm_password' => $password], token($reportStaffJar, 'login.php'))['status'] === 200, 'new personnel onboarding');
$reportStaffCsrf = token($reportStaffJar);
$accountReport = ['category' => 'Waste Management', 'concernType' => 'Uncollected garbage', 'keyPoints' => ['Bad odor'], 'purok' => 'Reporting area', 'street' => 'Report street', 'exactArea' => 'Near crossing'];
$availableLocations = (new ComplaintStore($testDatabase->connect()))->locations();
if ($availableLocations) $accountReport['locationId'] = (string)$availableLocations[0]['id'];
$reportedIds = [];
foreach ([[$reporterJar, $reporterCsrf, $reporter], [$reportStaffJar, $reportStaffCsrf, $reportStaff], [$adminJar, $adminCsrf, req($adminJar, 'api.php')['json']['actor']]] as [$accountJar, $accountCsrf, $account]) {
    $form = pageDocument($accountJar, 'report-concern.php');
    pageHas($form, '//form[@id="public-report" and @data-action="submit"]', 'all roles reuse workspace report form');
    pageHas($form, '//input[@type="checkbox" and @name="isAnonymous"]', 'anonymous option available');
    httpCheck(str_contains($form->evaluate('string(//body)'), '0 of 3 concern submissions used today.'), 'initial allowance shown');
    httpCheck(post($accountJar, 'submit', $accountReport, '')['status'] === 403, 'account report requires CSRF');
    for ($i = 0; $i < 3; $i++) {
        $sent = post($accountJar, 'submit', $accountReport + ['isAnonymous' => $i === 1, 'residentId' => 'forged-account', 'role' => 'official'], $accountCsrf);
        httpCheck($sent['status'] === 200, 'account role can submit within allowance');
        $caseId = $sent['json']['id']; $reportedIds[] = $caseId;
        $detail = pageDocument($adminJar, 'complaint.php?id=' . $caseId);
        $case = httpCase(req($adminJar, 'api.php'), $caseId);
        if ($i === 1) {
            httpCheck($case['resident'] === 'Anonymous' && $case['submitterRole'] === null, 'API anonymous label without role');
            httpCheck(!str_contains(json_encode($case), $account['id']) && !str_contains(json_encode($case), $account['name']), 'API omits anonymous owner identity');
            httpCheck(!str_contains($detail->evaluate('string(//article[@data-case-id])'), $account['name']), 'normal concern details hide anonymous name');
            pageHas($detail, '//div[contains(@class,"case-summary")]//strong[text()="Anonymous"]', 'details show Anonymous');
        } else pageHas($detail, '//div[contains(@class,"case-summary")]//strong[text()="' . $account['name'] . '"]', 'identified name shown');
        pageDocument($accountJar, 'complaint.php?id=' . $caseId);
    }
    httpCheck(post($accountJar, 'submit', $accountReport, $accountCsrf)['status'] === 422, 'fourth direct POST blocked');
    httpCheck(req($accountJar, 'public-api.php', ['action' => 'submit', 'data' => $accountReport], $accountCsrf)['status'] === 422, 'public endpoint cannot bypass signed-in account limit');
    $limited = pageDocument($accountJar, 'report-concern.php');
    pageHas($limited, '//form[@id="public-report"]//button[@type="submit" and @disabled]', 'limit shown after refresh');
    httpCheck(str_contains($limited->evaluate('string(//body)'), '3 of 3 concern submissions used today.'), 'used count shown after reload');
    pageDocument($accountJar, 'complaints.php?scope=mine');
    pageDocument($accountJar, 'history.php?scope=mine');
}
$queue = pageDocument($reportStaffJar, 'complaints.php');
httpCheck(!str_contains($queue->evaluate('string(//main)'), 'Uncollected garbage Concern'), 'submitted reports do not mix into work queue');
foreach ([$reporterJar, $reportStaffJar] as $accountJar) {
    foreach (['reports.php', 'users.php', 'settings.php'] as $path) pageDocument($accountJar, $path, 403);
    httpCheck(req($accountJar, 'api.php?view=weekly')['status'] === 403, 'weekly API official only');
    pageDocument($accountJar, 'complaints.php?week=' . ConcernInsights::week()['date'] . '&type=Uncollected%20garbage', 403);
}
$weeklyResponse = req($adminJar, 'api.php?view=weekly');
httpCheck($weeklyResponse['status'] === 200, 'official weekly API');
$weeklyData = $weeklyResponse['json'];
$waste = array_values(array_filter($weeklyData['groups'], fn($g) => $g['type'] === 'Uncollected garbage'))[0];
httpCheck($waste['count'] >= 9 && count($waste['actions']) === 3 && $waste['keyPoints'][0] === 'Bad odor', 'weekly API combines roles and exactly three keypoint solutions');
pageHas(pageDocument($adminJar, 'index.php'), '//*[@id="weekly-concerns"]//h4[contains(.,"Common keypoints")]', 'weekly UI displays common keypoints');
httpCheck(pageDocument($adminJar, 'index.php')->query('//*[@id="weekly-concerns"]//h4[contains(.,"Suggested solutions")]/following-sibling::ol[1]/li')->length >= 3, 'weekly UI displays ordered solutions');
$relatedUrl = 'complaints.php?' . http_build_query(['week' => $weeklyData['date'], 'category' => $waste['category'], 'type' => $waste['type']]);
$related = pageDocument($adminJar, $relatedUrl);
foreach ($reportedIds as $caseId) httpCheck(str_contains($related->evaluate('string(//main)'), $caseId), 'weekly related list includes contributing report');
httpCheck($related->query('//table[contains(@class,"complaint-table")]/tbody/tr')->length === $waste['count'], 'related row count equals ranking count');
pageDocument($adminJar, 'complaints.php?week=2026-02-30&type=x', 422);
$csv = req($adminJar, 'api.php?export=csv')['body'];
$stream = fopen('php://temp', 'w+'); fwrite($stream, $csv); rewind($stream);
fgetcsv($stream);
$anonymousRows = 0;
while (($line = fgetcsv($stream)) !== false) if (in_array($line[0], [$reportedIds[1], $reportedIds[4], $reportedIds[7]], true)) {
    httpCheck($line[21] === 'Anonymous' && $line[22] === '', 'CSV hides anonymous submitter and role');
    $anonymousRows++;
}
fclose($stream);
httpCheck($anonymousRows === 3, 'all anonymous role reports appear in CSV');
