<?php
declare(strict_types=1);
if (!isset($testDatabase, $adminJar)) throw new RuntimeException('Run tests/http.php for page checks.');
function pageDocument(string $jar, string $path, int $expected = 200): DOMXPath
{
    static $assets = [];
    $r = req($jar, $path);
    httpCheck($r['status'] === $expected, $path . ' status ' . $expected);
    httpCheck(!preg_match('/(?:Fatal error|Warning|Notice):/', $r['body']), $path . ' no PHP diagnostics');
    $doc = new DOMDocument(); $prior = libxml_use_internal_errors(true); $doc->loadHTML($r['body']); libxml_clear_errors(); libxml_use_internal_errors($prior);
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//script[@src]/@src | //link[@rel="stylesheet" or @rel="icon"]/@href | //img[not(starts-with(@src,"data:"))]/@src') as $attribute) {
        $asset = $attribute->value;
        if (isset($assets[$asset])) continue;
        httpCheck((str_starts_with($asset, 'assets/') || str_starts_with($asset, 'evidence.php?')) && req($jar, $asset)['status'] === 200, 'local asset ' . $asset);
        $assets[$asset] = true;
    }
    foreach ($xpath->query('//form[@data-action] | //form[@id="public-report" or @id="public-track"]') as $form) httpCheck(strtolower($form->getAttribute('method')) === 'post', 'sensitive form uses POST');
    httpCheck(!preg_match('/\bcomplaints?\b/i', $xpath->evaluate('string(//body)')), $path . ' concern terminology');
    return $xpath;
}
function pageHas(DOMXPath $doc, string $query, string $label): void { httpCheck($doc->query($query)->length > 0, $label); }
foreach (['landing.php', 'index.php', 'report-concern.php', 'new-complaint.php', 'track.php', 'login.php', 'user-guide.php'] as $path) pageDocument($guestJar, $path);
foreach (['landing.php', 'report-concern.php', 'track.php', 'user-guide.php'] as $path) {
    pageHas(pageDocument($guestJar, $path), '//nav[@id="public-navigation"]//a[@href="track.php" and contains(.,"Track Concern")]', 'guest tracking in public navigation on ' . $path);
}
pageHas(pageDocument($guestJar, 'landing.php'), '//div[@class="landing-actions"]//a[@href="track.php" and contains(@class,"btn")]', 'landing hero has Track Concern button');
pageHas(pageDocument($guestJar, 'login.php'), '//a[@href="track.php" and contains(@class,"btn")]', 'authentication page offers tracking without sign-in');
$guestTracking = pageDocument($guestJar, 'track.php');
pageHas($guestTracking, '//form[@id="public-track"]//input[@name="reference" and @required]', 'guest tracking requires reference');
pageHas($guestTracking, '//form[@id="public-track"]//input[@name="trackingCode" and @required]', 'guest tracking requires private code');
httpCheck($guestTracking->query('//*[@id="account-tracking-heading"]')->length === 0, 'guest tracking does not display account-only access');
$form = pageDocument($guestJar, 'report-concern.php');
httpCheck($form->query('//input[@name="name" or @name="email" or @name="password" or @name="title"]')->length === 0, 'no identity/title fields');
pageHas($form, '//select[@name="category" and @required]', 'category choice');
pageHas(pageDocument($guestJar,'report-concern.php?category=Street%20Lighting'),'//select[@name="category"]/option[@selected and @value="Street Lighting"]','landing category preselects report form');
pageHas($form, '//select[@name="concernType" and @required]', 'dependent type');
pageHas($form, '//*[@data-key-points]', 'key point section');
pageHas($form, '//input[@name="exactArea" and @required]', 'required exact area');
httpCheck($form->query('//textarea[@name="description" and @required]')->length === 0, 'description optional');
pageHas($form, '//*[@id="suggestions"]', 'suggestions preview');
pageHas($form, '//*[@id="guidance-heading" and contains(., "Safety guidance")]', 'guidance is addressed to residents');
httpCheck($form->query('//*[@name="selectedSuggestion"]')->length === 0, 'no proposed-solution choice on public form');
pageHas($form, '//*[@id="receipt-guidance"]', 'receipt has resident guidance section');
foreach (['complaints.php', 'concerns.php', 'history.php', 'admin.php', 'reports.php', 'solutions.php', 'users.php', 'profile.php', 'user-create.php', 'user-edit.php?id=' . $staffId, 'complaint.php?id=' . $id] as $path) {
    pageHas(pageDocument($guestJar, $path), '//form[@data-action="login"]', 'anonymous private page gated');
    pageDocument($adminJar, $path);
}
foreach (['admin.php', 'reports.php', 'solutions.php', 'users.php', 'user-create.php', 'user-edit.php?id=' . $staffId] as $path) pageDocument($staffJar, $path, 403);
foreach (['index.php', 'complaints.php', 'history.php', 'profile.php', 'concern.php?id=' . $id] as $path) pageDocument($staffJar, $path);
pageDocument($otherJar, 'concern.php?id=' . $id, 404);
pageDocument($adminJar, 'concern.php?id[]=bad', 404);
pageDocument($adminJar, 'user-edit.php?id=missing', 404);
$detail = pageDocument($adminJar, 'complaint.php?id=' . $id);
pageHas($detail, '//details/summary[contains(., "Temporary guidance shared with the resident")]', 'staff guidance is a read-only reference');
httpCheck(!str_contains($detail->evaluate('string(//body)'), 'reporter preference') && !str_contains($detail->evaluate('string(//body)'), 'FOR ASSESSMENT'), 'guidance not presented as resident preference or staff plan');
pageHas($detail, '//article[@data-version="8"]', 'version on detail');
httpCheck($detail->query('//form[@data-action="edit"]')->length === 0, 'official has no general edit-report form');
pageHas($detail, '//*[contains(@class,"record-note") and contains(.,"preserved as originally reported")]', 'detail marks the original report as read-only');
$staffDetail = pageDocument($staffJar, 'concern.php?id=' . $id);
httpCheck($staffDetail->query('//form[@data-action="edit"]')->length === 0, 'personnel has no edit-report form');
pageHas($detail, '//form[@data-action="reopen"]', 'official reopens closed concern');
httpCheck(!str_contains(req($adminJar, 'complaint.php?id=' . $id)['body'], '<script>alert(1)</script>'), 'untrusted descriptions escaped');
pageHas(pageDocument($adminJar, 'history.php'), '//a[contains(@href,"' . $id . '")]', 'closed concern in history');
pageHas(pageDocument($adminJar, 'solutions.php'), '//form[@data-action="save_rule"]', 'curated library editor');
pageHas(pageDocument($adminJar, 'solutions.php'), '//a[contains(@href,"' . $id . '")]', 'historical solution library preserved');
$admin = pageDocument($adminJar, 'admin.php');
foreach (['complaints.php', 'blocked.php', 'users.php', 'reports.php', 'solutions.php', 'settings.php', 'audit.php', 'track.php'] as $destination) {
    pageHas($admin, '//a[starts-with(@href,"' . $destination . '")]', 'administration links to ' . $destination);
}
httpCheck(str_contains($admin->evaluate('string(//body)'), 'Private tracking codes and account passwords remain protected'), 'administration explains identity protection');
httpCheck(str_contains(req($adminJar, 'reports.php')['body'], 'Common key points') && str_contains(req($adminJar, 'reports.php')['body'], 'Deep'), 'structured analytics');
pageHas(pageDocument($adminJar, 'user-edit.php?id=' . $staffId), '//input[@name="email" and @type="email" and @required]', 'personnel email editing');
pageHas(pageDocument($adminJar, 'complaints.php?category=Roads%20and%20Infrastructure'), '//a[contains(@href,"' . $id . '")]', 'new category filtering');
httpCheck(pageDocument($adminJar, 'complaints.php?search=impossible-match')->query('//table//a[contains(@href,"' . $id . '")]')->length === 0, 'server search filtering');
