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
foreach (['landing.php', 'index.php', 'report-concern.php', 'new-complaint.php', 'track.php', 'login.php'] as $path) pageDocument($guestJar, $path);
$form = pageDocument($guestJar, 'report-concern.php');
httpCheck($form->query('//input[@name="name" or @name="email" or @name="password" or @name="title"]')->length === 0, 'no identity/title fields');
pageHas($form, '//select[@name="category" and @required]', 'category choice');
pageHas($form, '//select[@name="concernType" and @required]', 'dependent type');
pageHas($form, '//*[@data-key-points]', 'key point section');
pageHas($form, '//input[@name="exactArea" and @required]', 'required exact area');
httpCheck($form->query('//textarea[@name="description" and @required]')->length === 0, 'description optional');
pageHas($form, '//*[@id="suggestions"]', 'suggestions preview');
foreach (['complaints.php', 'concerns.php', 'history.php', 'reports.php', 'solutions.php', 'users.php', 'profile.php', 'user-create.php', 'user-edit.php?id=' . $staffId, 'complaint.php?id=' . $id] as $path) {
    pageHas(pageDocument($guestJar, $path), '//form[@data-action="login"]', 'anonymous private page gated');
    pageDocument($adminJar, $path);
}
foreach (['reports.php', 'solutions.php', 'users.php', 'user-create.php', 'user-edit.php?id=' . $staffId] as $path) pageDocument($staffJar, $path, 403);
foreach (['index.php', 'complaints.php', 'history.php', 'profile.php', 'concern.php?id=' . $id] as $path) pageDocument($staffJar, $path);
pageDocument($otherJar, 'concern.php?id=' . $id, 404);
pageDocument($adminJar, 'concern.php?id[]=bad', 404);
pageDocument($adminJar, 'user-edit.php?id=missing', 404);
$detail = pageDocument($adminJar, 'complaint.php?id=' . $id);
pageHas($detail, '//article[@data-version="7"]', 'version on detail');
pageHas($detail, '//form[@data-action="edit"]', 'official edits concern at all stages');
pageHas($detail, '//form[@data-action="reopen"]', 'official reopens closed concern');
httpCheck(!str_contains(req($adminJar, 'complaint.php?id=' . $id)['body'], '<script>alert(1)</script>'), 'untrusted descriptions escaped');
pageHas(pageDocument($adminJar, 'history.php'), '//a[contains(@href,"' . $id . '")]', 'closed concern in history');
pageHas(pageDocument($adminJar, 'solutions.php'), '//form[@data-action="save_rule"]', 'curated library editor');
pageHas(pageDocument($adminJar, 'solutions.php'), '//a[contains(@href,"' . $id . '")]', 'historical solution library preserved');
httpCheck(str_contains(req($adminJar, 'reports.php')['body'], 'Common key points') && str_contains(req($adminJar, 'reports.php')['body'], 'Deep'), 'structured analytics');
pageHas(pageDocument($adminJar, 'user-edit.php?id=' . $staffId), '//input[@name="email" and @type="email" and @required]', 'personnel email editing');
pageHas(pageDocument($adminJar, 'complaints.php?category=Roads%20and%20Infrastructure'), '//a[contains(@href,"' . $id . '")]', 'new category filtering');
httpCheck(pageDocument($adminJar, 'complaints.php?search=impossible-match')->query('//table//a[contains(@href,"' . $id . '")]')->length === 0, 'server search filtering');
