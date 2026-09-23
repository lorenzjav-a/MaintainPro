<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$actor = br_actor();
if (!$actor || $actor['must_change_password']) { http_response_code(403); exit; }
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id)) { http_response_code(404); exit; }
foreach (ComplaintWorkflow::visible(br_state(), $actor) as $case) {
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
