<?php
declare(strict_types=1);
// Included by http.php using its disposable database, local server and isolated cookie jars.
if (!isset($testDatabase, $adminJar)) throw new RuntimeException('Run tests/http.php to execute the page checks.');

function pageDocument(string $jar, string $path, int $expected = 200): DOMXPath
{
    static $checkedAssets = [];
    $response = req($jar, $path);
    httpCheck($response['status'] === $expected, $path . ' HTTP status ' . $expected);
    httpCheck(!preg_match('/(?:Fatal error|Warning|Notice):/', $response['body']), $path . ' has no PHP diagnostics');
    $doc = new DOMDocument();
    $prior = libxml_use_internal_errors(true);
    $doc->loadHTML($response['body']);
    libxml_clear_errors();
    libxml_use_internal_errors($prior);
    $xpath = new DOMXPath($doc);
    // Every rendered document must use working local assets after a file move.
    foreach ($xpath->query('//script[@src]/@src | //link[@rel="stylesheet" or @rel="icon"]/@href | //img[not(starts-with(@src,"data:"))]/@src') as $attribute) {
        $asset = $attribute->value;
        if (isset($checkedAssets[$asset])) continue;
        httpCheck(str_starts_with($asset, 'assets/') && req($jar, $asset)['status'] === 200, 'rendered asset exists: ' . $asset);
        $checkedAssets[$asset] = true;
    }
    foreach ($xpath->query('//form[@data-action]') as $form) {
        httpCheck(strtolower($form->getAttribute('method')) === 'post', 'account and complaint forms cannot put credentials in a GET URL');
    }
    return $xpath;
}

function pageHas(DOMXPath $doc, string $query, string $label): void
{
    httpCheck($doc->query($query)->length > 0, $label);
}

$allPages = ['index.php', 'complaints.php', 'history.php', 'reports.php', 'solutions.php', 'users.php', 'profile.php', 'new-complaint.php', 'complaint.php?id=BR-1', 'user-create.php', 'user-edit.php?id=' . $staffId];
$anonymous = jar();
foreach ($allPages as $path) {
    $doc = pageDocument($anonymous, $path);
    pageHas($doc, '//form[@data-action="login"]', 'anonymous ' . $path . ' requires login');
}

// The recovered resident is signed in in resetJar; the old resident session was revoked.
$pageResident = $resetJar;
$pageResidentCsrf = token($pageResident);
httpCheck(post($adminJar, 'update_user', ['role' => 'personnel', 'team' => 'Sanitation team', 'active' => '1'], $adminCsrf, $staffId)['status'] === 200, 'reactivate test personnel');
httpCheck(auth($staffJar, 'login', ['email' => 'http-staff@example.test', 'password' => $password], token($staffJar, 'login.php'))['status'] === 200, 'personnel normal login');
$staffCsrf = token($staffJar);
$pageOfficial = post($adminJar, 'create_user', ['name' => 'Page Official', 'email' => 'page-official@example.test', 'role' => 'official'], $adminCsrf);
httpCheck($pageOfficial['status'] === 200, 'create additional official');
$pageOfficialAccount = $pageOfficial['json']['created_account'];
$pageOfficialJar = jar();
httpCheck(auth($pageOfficialJar, 'login', ['email' => $pageOfficialAccount['email'], 'password' => $pageOfficialAccount['temporary_password']], token($pageOfficialJar, 'login.php'))['status'] === 200, 'additional official temporary login');
foreach ($allPages as $path) {
    $doc = pageDocument($pageOfficialJar, $path);
    pageHas($doc, '//form[@data-action="change_password"]', 'temporary password gates ' . $path);
}
httpCheck(auth($pageOfficialJar, 'change_password', ['current_password' => $pageOfficialAccount['temporary_password'], 'password' => $password, 'confirm_password' => $password], token($pageOfficialJar, 'login.php'))['status'] === 200, 'official completes temporary password change');

foreach ([$adminJar => 'Administrative dashboard', $pageResident => 'Resident dashboard', $staffJar => 'Personnel dashboard'] as $cookie => $heading) {
    $doc = pageDocument($cookie, 'index.php');
    pageHas($doc, '//h1[text()="' . $heading . '"]', 'PHP renders ' . $heading);
    pageHas($doc, '//aside//a[@href="index.php" and @aria-current="page"]', 'dashboard active sidebar');
    pageHas($doc, '//nav[@aria-label="Quick navigation"]/a[@href="complaints.php"]', 'native mobile complaint link');
    httpCheck($doc->query('//*[@id="case-modal" or @id="form-modal" or @data-nav or @data-open]')->length === 0, 'shared feature modals and SPA links removed');
}
foreach ([$adminJar, $pageResident, $staffJar] as $cookie) {
    foreach (['complaints.php', 'history.php', 'profile.php'] as $path) pageDocument($cookie, $path);
}
foreach (['reports.php', 'solutions.php', 'users.php', 'user-create.php', 'user-edit.php?id=' . $staffId] as $path) {
    pageDocument($adminJar, $path);
    pageDocument($pageResident, $path, 403);
    pageDocument($staffJar, $path, 403);
}
pageDocument($adminJar, 'new-complaint.php', 403);
pageDocument($staffJar, 'new-complaint.php', 403);
$newPage = pageDocument($pageResident, 'new-complaint.php');
pageHas($newPage, '//form[@data-action="submit"]//input[@name="title"]', 'dedicated complaint form');
$createPage = pageDocument($adminJar, 'user-create.php');
pageHas($createPage, '//form[@data-action="create_user"]//select[@name="team" and @required]', 'new personnel require team');
httpCheck(!str_contains(req($adminJar, 'user-create.php')['body'], $pageOfficialAccount['temporary_password']), 'temporary password is absent on fresh page loads');
$self = req($adminJar, 'api.php')['json']['actor']['id'];
$selfPage = pageDocument($adminJar, 'user-edit.php?id=' . $self);
httpCheck($selfPage->query('//select[@name="role"]/option')->length === 1 && $selfPage->query('//select[@name="active"]/option')->length === 1, 'own account form preserves official access');
httpCheck(post($adminJar, 'update_user', ['role' => 'resident', 'active' => '0'], $adminCsrf, $self)['status'] === 422, 'API still prevents own account lockout');
pageDocument($adminJar, 'user-edit.php?id=missing', 404);
pageDocument($adminJar, 'complaint.php?id=missing', 404);
pageDocument($adminJar, 'complaint.php?id[]=bad', 404);

$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
$pageReport = ['title' => 'Page concern <script>alert(1)</script>', 'category' => 'Drainage and flooding', 'description' => 'Description & evidence', 'location' => 'Page test, Purok 4', 'suggestion' => 'Inspect <the drain>', 'photo' => $png];
$created = post($pageResident, 'submit', $pageReport, $pageResidentCsrf);
httpCheck($created['status'] === 200, 'page flow complaint submission');
$pageId = $created['json']['id'];
$detailUrl = 'complaint.php?id=' . $pageId;
$v = 1;
$doc = pageDocument($pageResident, $detailUrl);
pageHas($doc, '//article[@data-version="1"]', 'page exposes original record version');
pageHas($doc, '//img[@alt="Supporting photo"]', 'supporting evidence rendered');
httpCheck(!str_contains(req($pageResident, $detailUrl)['body'], '<script>alert(1)</script>'), 'complaint HTML escapes untrusted title');
httpCheck($doc->query('//form[@data-action="assess"]')->length === 0, 'resident sees no official actions');
pageDocument($otherJar, $detailUrl, 404);
pageDocument($staffJar, $detailUrl, 404);
pageHas(pageDocument($adminJar, $detailUrl), '//form[@data-action="assess"]', 'official sees assessment');
pageHas(pageDocument($pageResident, 'complaints.php?search=Page%20concern'), '//a[contains(@href,"' . $pageId . '")]', 'server search finds complaint');
$none = pageDocument($pageResident, 'complaints.php?search=not-a-match');
httpCheck($none->query('//table[contains(@class,"complaint-table")]//a[contains(@href,"' . $pageId . '")]')->length === 0, 'server search filters complaint');
$historyBefore = pageDocument($pageResident, 'history.php');
httpCheck($historyBefore->query('//table//a[contains(@href,"' . $pageId . '")]')->length === 0, 'history excludes unresolved report');

$step = function (string $cookie, string $csrfValue, string $action, array $data, string $status) use ($pageId, &$v): void {
    $result = post($cookie, $action, $data, $csrfValue, $pageId, $v);
    httpCheck($result['status'] === 200, 'page flow action ' . $action);
    $record = httpCase($result, $pageId);
    httpCheck($record['status'] === $status, 'page flow status ' . $status);
    $v = $record['version'];
};
$step($adminJar, $adminCsrf, 'exception', ['status' => 'Returned for Information', 'notes' => 'Please confirm the landmark.'], 'Returned for Information');
pageHas(pageDocument($pageResident, $detailUrl), '//form[@data-action="information"]', 'resident sees requested information form');
$step($pageResident, $pageResidentCsrf, 'information', ['notes' => 'Near the yellow store.', 'photo' => $png], 'Submitted');
$step($adminJar, $adminCsrf, 'assess', ['category' => 'Drainage and flooding', 'priority' => 'Urgent', 'recommendation' => 'Clear the drain.', 'assessment' => 'Inspected on site.'], 'Under Review');
pageHas(pageDocument($adminJar, $detailUrl), '//form[@data-action="assign"]', 'saved assessment enables assignment form');
httpCheck(post($adminJar, 'assign', ['team' => 'Sanitation team'], $adminCsrf, $pageId, 1)['status'] === 409, 'old page version cannot overwrite record');
$step($adminJar, $adminCsrf, 'assign', ['team' => 'Sanitation team'], 'Assigned');
pageHas(pageDocument($staffJar, $detailUrl), '//form[@data-action="start"]', 'assigned personnel sees start action');
$step($staffJar, $staffCsrf, 'start', [], 'In Progress');
pageHas(pageDocument($staffJar, $detailUrl), '//form[@data-action="note"]', 'personnel progress form');
pageHas(pageDocument($staffJar, $detailUrl), '//form[@data-action="resolve"]', 'personnel resolution form');
$step($staffJar, $staffCsrf, 'note', ['notes' => 'First inspection completed.'], 'In Progress');
$step($staffJar, $staffCsrf, 'resolve', ['notes' => 'First clearing completed.', 'photo' => $png], 'Resolved');
pageHas(pageDocument($pageResident, $detailUrl), '//form[@data-action="verification"]', 'resident verification form');
$step($pageResident, $pageResidentCsrf, 'reopen', ['feedback' => 'Water still backs up.'], 'Reopened');
pageHas(pageDocument($adminJar, $detailUrl), '//form[@data-action="assess"]', 'reopening restores assessment');
$step($adminJar, $adminCsrf, 'assess', ['category' => 'Drainage and flooding', 'priority' => 'High', 'recommendation' => 'Inspect downstream blockage.'], 'Under Review');
$step($adminJar, $adminCsrf, 'assign', ['team' => 'Sanitation team'], 'Assigned');
$step($staffJar, $staffCsrf, 'start', [], 'In Progress');
$step($staffJar, $staffCsrf, 'resolve', ['notes' => 'Downstream blockage cleared.', 'photo' => $png], 'Resolved');
$step($pageResident, $pageResidentCsrf, 'verify', ['feedback' => 'Water now flows freely.'], 'Verified');
$finalPage = pageDocument($pageResident, $detailUrl);
pageHas($finalPage, '//article[@data-version="' . $v . '"]', 'refresh renders latest version');
pageHas($finalPage, '//div[contains(@class,"timeline-entry")]//img', 'prior resolution evidence preserved in timeline');
pageHas(pageDocument($pageResident, 'history.php'), '//a[contains(@href,"' . $pageId . '")]', 'verified complaint appears in history');
pageHas(pageDocument($adminJar, 'solutions.php'), '//a[contains(@href,"' . $pageId . '")]', 'verified complaint appears in solution library');
pageHas(pageDocument($adminJar, 'users.php'), '//a[@href="user-create.php"]', 'native account creation link');
pageHas(pageDocument($adminJar, 'users.php'), '//aside//a[@href="users.php" and @aria-current="page"]', 'current page sidebar highlight');
httpCheck(req($pageResident, 'api.php')['json']['actor']['id'] === $residentId && req($adminJar, 'api.php')['json']['actor']['id'] === $self && req($staffJar, 'api.php')['json']['actor']['id'] === $staffId, 'parallel profile sessions retain all three identities through workflow');
httpCheck(auth($pageOfficialJar, 'logout', [], token($pageOfficialJar))['status'] === 200, 'additional official logout');
httpCheck(req($adminJar, 'api.php')['status'] === 200 && req($staffJar, 'api.php')['status'] === 200 && req($pageResident, 'api.php')['status'] === 200, 'one isolated logout leaves other test sessions signed in');
httpCheck(auth($pageOfficialJar, 'login', ['email' => $pageOfficialAccount['email'], 'password' => $password], token($pageOfficialJar, 'login.php'))['status'] === 200, 'official normal login after onboarding');
