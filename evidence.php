<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/evidence-storage.php';
$actor = br_actor();
if (!$actor || $actor['must_change_password']) { http_response_code(403); exit; }
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id)) { http_response_code(404); exit; }
$store = br_store();
$record = method_exists($store, 'evidenceRecord') ? $store->evidenceRecord($actor['id'], $id) : null;
if (is_array($record)) {
    $file = EvidenceStorage::storedFile(is_string($record['file_path'] ?? null) ? $record['file_path'] : '');
    if ($file === null || (!empty($record['mime_type']) && $record['mime_type'] !== $file['mime_type'])) {
        http_response_code(404);
        exit;
    }
    $handle = @fopen($file['path'], 'rb');
    if ($handle === false) { http_response_code(404); exit; }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Content-Disposition: inline; filename="evidence-' . $id . '.' . $file['extension'] . '"');
    fpassthru($handle);
    fclose($handle);
    exit;
}

// Compatibility for evidence saved in complaint JSON before file storage.
foreach (ComplaintWorkflow::visible($store->state(), $actor) as $case) {
    foreach ($case['timeline'] as $event) {
        if (($event['evidenceId'] ?? '') !== $id || empty($event['photo'])) continue;
        if (!preg_match('~^data:(image/(?:jpeg|png|webp));base64,(.+)$~D', $event['photo'], $match)) break;
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$match[1]];
        header('Content-Type: ' . $match[1]);
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Content-Disposition: inline; filename="evidence-' . $id . '.' . $extension . '"');
        echo base64_decode($match[2], true);
        exit;
    }
}
http_response_code(404);
