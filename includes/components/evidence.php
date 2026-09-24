<?php
$evidenceEntries = ConcernInsights::evidence($c);
$initialEvidence = null; $completionEvidence = null;
foreach ($evidenceEntries as $evidenceEntry) {
    if ($evidenceEntry['evidenceType'] === 'Initial Evidence' && $initialEvidence === null) $initialEvidence = $evidenceEntry;
    if ($evidenceEntry['evidenceType'] === 'Completion Evidence') $completionEvidence = $evidenceEntry;
}
$evidenceUrl = fn($entry) => !empty($entry['evidenceId']) ? br_url('evidence.php',['id' => $entry['evidenceId']]) : ($entry['photo'] ?? '');
?>
<section class="case-section" id="evidence"><h3>Before &amp; After</h3>
<div class="evidence-comparison">
<?php foreach (['Before — Initial Evidence' => $initialEvidence, 'After — Latest Completion Evidence' => $completionEvidence] as $label => $entry): ?>
<figure class="evidence-card"><figcaption><strong><?= h($label) ?></strong></figcaption><?php if ($entry): ?><a href="<?= h($evidenceUrl($entry)) ?>" target="_blank" rel="noopener"><img src="<?= h($evidenceUrl($entry)) ?>" alt="<?= h($label) ?>" loading="lazy"></a><p><?= h(br_date($entry['date'],true)) ?></p><?php else: ?><p class="evidence-empty"><?= str_starts_with($label,'Before') ? 'No initial photo was submitted.' : 'No completion evidence recorded yet.' ?></p><?php endif ?></figure>
<?php endforeach ?>
</div>
<?php if ($completionEvidence && !in_array($c['status'],['Resolved','Verified'],true)): ?><p class="form-text">The completion image is from an earlier attempt. This concern is still open.</p><?php endif ?>
<details class="mt-3" open><summary>Evidence timeline · <?= count($evidenceEntries) ?> images</summary><div class="evidence-gallery mt-3">
<?php foreach ($evidenceEntries as $entry): ?><figure class="evidence-card"><figcaption><strong><?= h($entry['evidenceType']) ?></strong><span><?= h($entry['workStatus'] ?? '') ?></span></figcaption><a href="<?= h($evidenceUrl($entry)) ?>" target="_blank" rel="noopener"><img src="<?= h($evidenceUrl($entry)) ?>" alt="<?= h($entry['evidenceType']) ?>" loading="lazy"></a><p><?= h($entry['actor']) ?> · <?= h(br_date($entry['date'],true)) ?></p><?php if (!empty($entry['note'])): ?><p class="evidence-note"><?= h($entry['note']) ?></p><?php endif ?></figure><?php endforeach ?>
<?php if (!$evidenceEntries): ?><p class="text-muted">No image evidence yet.</p><?php endif ?>
</div></details></section>
