<?php
// Included by http.php with its isolated database, sessions, and server.
require_once dirname(__DIR__) . '/includes/store.php';
$isolatedStore = new ComplaintStore($testDatabase->connect());
$officialId = req($adminJar, 'api.php')['json']['actor']['id'];
$caseNow = fn(string $reference) => $isolatedStore->concernForActor($officialId, $reference);
$save = fn(string $jar, string $csrf, string $action, string $reference, array $data = []) => post($jar, $action, $data, $csrf, $reference, $caseNow($reference)['version']);
foreach (['settings.php', 'audit.php', 'blocked.php'] as $page) {
    httpCheck(req($adminJar, $page)['status'] === 200, 'official page ' . $page);
    httpCheck(req($otherJar, $page)['status'] === 403, 'personnel direct URL protection ' . $page);
    httpCheck(str_contains(req($guestJar, $page)['body'], 'data-action="login"'), 'anonymous page protection ' . $page);
}
$block = ['blockReason' => 'Weather Delay', 'recommendedAction' => 'Wait for safe conditions', 'expectedAt' => date('Y-m-d', time() + 86400)];
httpCheck($save($otherJar, $otherCsrf, 'block', $id, $block)['status'] === 200, 'personnel block endpoint');
httpCheck(str_contains(req($adminJar, 'blocked.php')['body'], 'Weather Delay'), 'blocked queue shows reason');
pageHas(pageDocument($adminJar, 'complaint.php?id=' . $id), '//form[@data-action="manage_block"]//option[@value="approve"]', 'block form uses backend decision values');
httpCheck($save($otherJar, $otherCsrf, 'manage_block', $id, ['decision' => 'resume'])['status'] === 422, 'personnel approval denied');
httpCheck($save($adminJar, $adminCsrf, 'manage_block', $id, ['decision' => 'instructions', 'instructions' => 'Stay clear until the storm passes'])['status'] === 200, 'official block instructions');
httpCheck(str_contains(req($otherJar, 'complaint.php?id=' . $id)['body'], 'Stay clear until the storm passes'), 'personnel sees official instructions');
httpCheck($save($adminJar, $adminCsrf, 'manage_block', $id, ['decision' => 'resume'])['status'] === 200, 'official resumes work');
httpCheck(!str_contains(req($adminJar, 'blocked.php')['body'], 'Weather Delay'), 'resumed concern removed from blocked queue');
$followReceipt = $public('submit', $report)['json']['receipt'];
$followId = $followReceipt['reference'];
httpCheck($save($adminJar, $adminCsrf, 'request_information', $followId, ['notes' => 'Which side of the street?'])['status'] === 200, 'official information request endpoint');
$trackResponse = $public('track', $followReceipt);
httpCheck($trackResponse['json']['concern']['canFollowUp'] && str_contains($trackResponse['body'], 'Which side'), 'request appears in private tracking');
httpCheck($public('followup', $followReceipt + ['description' => 'East side', 'photo' => $png], '')['status'] === 403, 'followup CSRF');
httpCheck($public('followup', array_replace($followReceipt, ['trackingCode' => str_repeat('0', 48), 'description' => 'Forged']))['status'] === 422, 'followup wrong token denied');
httpCheck($public('followup', $followReceipt + ['description' => 'East side', 'photo' => $png])['status'] === 200, 'reporter supplies extra evidence');
pageHas(pageDocument($adminJar, 'complaint.php?id=' . $followId), '//*[@id="resident-responses-heading"]', 'reporter response section renders');
$followEvent = end($caseNow($followId)['timeline']);
httpCheck(req($adminJar, 'evidence.php?id=' . $followEvent['evidenceId'])['status'] === 200, 'followup evidence privately served');
httpCheck($public('followup', $followReceipt + ['description' => 'Replay'])['status'] === 422, 'followup replay blocked');
httpCheck($save($adminJar, $adminCsrf, 'link_concern', $followId, ['primaryConcernId' => $id])['status'] === 200, 'link concern endpoint');
$linkedPage = pageDocument($adminJar, 'complaint.php?id=' . $followId);
pageHas($linkedPage, '//*[@id="linked-reports"]', 'linked primary summary renders');
httpCheck($linkedPage->query('//form[@data-action="edit" or @data-action="assess" or @data-action="assign"]')->length === 0, 'linked report has no separate editable work forms');
pageHas(pageDocument($adminJar, 'complaint.php?id=' . $id), '//*[@id="linked-reports"]//a', 'primary lists linked report');
httpCheck(req($otherJar, 'complaint.php?id=' . $followId)['status'] === 404, 'primary assignee cannot access other resident report');
httpCheck(!str_contains(req($otherJar, 'complaint.php?id=' . $id)['body'], $followId), 'primary personnel page does not expose linked report IDs');
httpCheck($public('track', $followReceipt)['json']['concern']['status'] === $caseNow($id)['status'], 'linked public progress follows primary');
httpCheck(post($otherJar, 'create_location', ['name' => 'Purok 5'], $otherCsrf)['status'] === 422, 'personnel cannot configure locations');
httpCheck(post($adminJar, 'create_location', ['name' => 'Purok 5'], $adminCsrf)['status'] === 200, 'official configures locations');
$locationId = (string)$isolatedStore->locations()[0]['id'];
pageHas(pageDocument($guestJar, 'report-concern.php'), '//select[@name="locationId"]//option[@value="' . $locationId . '"]', 'report uses managed location choices');
httpCheck(post($adminJar, 'update_location', ['name' => 'Purok Five', 'sortOrder' => '1'], $adminCsrf, $locationId)['status'] === 200, 'location edit endpoint');
httpCheck(post($adminJar, 'toggle_location', [], $adminCsrf, $locationId)['status'] === 200, 'location deactivate endpoint');
httpCheck(!str_contains(req($guestJar, 'report-concern.php')['body'], '<option value="' . $locationId . '"'), 'inactive location removed from public selection');
httpCheck(post($adminJar, 'toggle_location', [], $adminCsrf, $locationId)['status'] === 200, 'location reactivate endpoint');
httpCheck(req($adminJar, 'audit.php?date=invalid')['status'] === 422, 'invalid audit filter handled');
httpCheck(str_contains(req($adminJar, 'audit.php?action=assignment_changed')['body'], 'Assignment Changed'), 'audit filters render records');
function backupRequest(string $cookie, string $token): array {
    global $base;
    $request = curl_init($base . '/backup.php');
    curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf' => $token]), CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($request);
    return ['status' => curl_getinfo($request, CURLINFO_RESPONSE_CODE), 'body' => $body];
}
httpCheck(req($adminJar, 'backup.php')['status'] === 405, 'backup requires deliberate POST');
httpCheck(backupRequest($otherJar, $otherCsrf)['status'] === 403, 'backup personnel denied');
httpCheck(backupRequest($adminJar, '')['status'] === 403, 'backup CSRF');
$backupResponse = backupRequest($adminJar, $adminCsrf);
httpCheck($backupResponse['status'] === 200 && str_starts_with($backupResponse['body'], '-- MaintainPro database backup.'), 'official backup download');
httpCheck(str_contains(req($adminJar, 'audit.php?action=database_backup_generated')['body'], 'Database Backup Generated'), 'backup audit visible');
$storedEvidence = $isolatedStore->evidenceRecord($officialId, $evidenceId);
httpCheck(req($guestJar, $storedEvidence['file_path'])['status'] === 404 && req($otherJar, $storedEvidence['file_path'])['status'] === 404, 'raw uploaded files blocked for all visitors');
// Existing inline evidence must continue working after switching new uploads to files.
$legacyCase = $caseNow($id); $legacyId = bin2hex(random_bytes(16));
$legacyCase['timeline'][0]['photo'] = $png;
$legacyCase['timeline'][0]['evidenceId'] = $legacyId;
(new MaintainProDatabase($testDatabase->connect()))->updateComplaint($legacyCase);
httpCheck(req($adminJar, 'evidence.php?id=' . $legacyId)['status'] === 200 && req($otherJar, 'evidence.php?id=' . $legacyId)['status'] === 200, 'legacy inline evidence remains readable to authorized staff');
httpCheck(req($resetJar, 'evidence.php?id=' . $legacyId)['status'] === 404, 'legacy evidence uses same assignment permissions');
