<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('complaint');
$c = br_find_case($context, br_query('id'));
extract($context);
$pageTitle = $c['id'] . ' · ' . $c['title'];
require __DIR__ . '/includes/layout/header.php';
br_heading($c['title'], 'Concern ' . $c['id'] . ' · ' . $c['category'], '<a class="btn btn-light" href="concerns.php">Back to concerns</a>');
$steps = ['Submitted' => 'Report', 'Under Review' => 'Assess & recommend', 'Assigned' => 'Assign', 'In Progress' => 'Take action', 'Resolved' => 'Resolve', 'Verified' => 'Close'];
$stepIndex = $c['status'] === 'Reopened' ? 1 : array_search($c['status'], array_keys($steps), true);
?>
<article class="panel detail-page" data-case-id="<?= h($c['id']) ?>" data-version="<?= (int)$c['version'] ?>">
  <div class="case-summary">
    <div class="summary-item"><span class="label">Current status</span><?= br_status($c) ?></div>
    <div class="summary-item"><span class="label">Priority</span><?= br_priority($c) ?></div>
    <div class="summary-item"><span class="label">Reported by</span><strong><?= h($c['resident']) ?></strong></div>
    <div class="summary-item"><span class="label">Submitted</span><strong><?= h(br_date($c['createdAt'], true)) ?></strong></div>
    <div class="summary-item"><span class="label">Assigned team</span><strong><?= h($c['team'] ?: 'Not yet assigned') ?></strong></div>
  </div>
  <div class="workflow-steps" aria-label="Concern progress">
    <?php foreach (array_values($steps) as $i => $label): ?>
    <div class="workflow-step <?= $stepIndex !== false && $i < $stepIndex ? 'complete' : ($i === $stepIndex ? 'current' : '') ?>"<?= $i === $stepIndex ? ' aria-current="step"' : '' ?>><span class="step-circle"><?= $stepIndex !== false && $i < $stepIndex ? br_icon('check') : $i + 1 ?></span><span><?= h($label) ?></span></div>
    <?php endforeach ?>
  </div>
  <div class="case-layout"><div class="case-main">
    <?php if (in_array($c['status'], ['Reopened', 'Returned for Information', 'Rejected', 'Referred to Another Office'], true)): ?><div class="status-note"><strong><?= h(br_status_label($c['status'])) ?></strong><br><?= h($c['timeline'][count($c['timeline']) - 1]['note']) ?></div><?php endif ?>
    <section class="case-section"><h3><?= br_icon('inbox') ?>Reported concern</h3><p class="case-description"><?= h($c['description']) ?></p><p><?= br_icon('pin') ?> <?= h($c['location']) ?></p></section>
    <section class="case-section"><h3>Key Points</h3><div class="d-flex flex-wrap gap-2"><?php foreach ($c['keyPoints'] ?? [] as $point): ?><span class="status status-assigned"><?= h($point) ?></span><?php endforeach ?></div><?php if (empty($c['keyPoints'])): ?><p class="text-muted">No key points selected / legacy record.</p><?php endif ?><p class="mt-3">Assigned personnel: <strong><?= h($c['assignedName'] ?? 'Awaiting individual assignment') ?></strong></p><p class="form-text">Location and photos on this page are private to authorized staff.</p></section>
    <?php require __DIR__ . '/includes/components/resident-followups.php'; require __DIR__ . '/includes/components/linked-concerns.php'; ?>
    <?php if (!empty($c['residentGuidance'])): ?><details class="case-section suggestion-box"><summary>Temporary guidance shared with the resident</summary><p class="form-text mt-3">Reference only: these steps were generated for the resident at submission while waiting for help. Staff follow the official action plan below. This does not indicate that the resident performed these steps.</p><ol class="resident-guidance-list"><?php foreach ($c['residentGuidance'] as $step): ?><li><?= h($step) ?></li><?php endforeach ?></ol></details><?php endif ?>
    <?php if (!empty($c['suggestions']) || !empty($c['suggestion'])): ?><details class="case-section suggestion-box"><summary>Earlier suggestions (legacy record)</summary><p class="form-text mt-3">Preserved from the earlier reporting form for historical reference. These are not the temporary resident guidance introduced in the updated form.</p><?php if (!empty($c['suggestions'])): ?><ol><?php foreach ($c['suggestions'] as $i => $suggestion): ?><li><?= h($suggestion) ?><?= ($c['selectedSuggestion'] ?? null) === $i ? ' (preference recorded on the earlier form)' : '' ?></li><?php endforeach ?></ol><?php else: ?><p><?= h($c['suggestion']) ?></p><?php endif ?></details><?php endif ?>
    <section class="case-section recommendation-box"><h3><?= br_icon('shield') ?>Barangay recommended action</h3><p><?= h($c['recommendation'] ?: 'The barangay has not recorded an official recommendation yet.') ?></p><?php if ($c['recommendation']): ?><div class="recommendation-author">Official assessment · <?= h($c['assessment'] ?: 'No additional notes') ?></div><?php endif ?></section>
    <?php if ($c['resolution']): ?><section class="case-section"><h3><?= br_icon('checkCircle') ?><?= $c['status'] === 'Reopened' ? 'Previous resolution attempt' : 'Recorded resolution' ?></h3><p><?= h($c['resolution']['notes']) ?></p><div class="photo-label"><?= h($c['resolution']['team']) ?> · <?= h(br_date($c['resolution']['date'], true)) ?></div></section><?php endif ?>
    <?php if ($c['feedback']): ?><section class="case-section suggestion-box"><div class="eyebrow">REVIEW FEEDBACK</div><p><?= h($c['feedback']) ?></p></section><?php endif ?>
    <?php if ($actor['role'] === 'official' && br_review($c)):
        $matches = array_values(array_filter($cases, fn($x) => $x['id'] !== $c['id'] && $x['category'] === $c['category'] && $x['status'] === 'Verified' && $x['resolution']));
    ?>
    <div class="related-box"><h4><?= br_icon('book') ?> Similar official-closed cases <span class="count-pill"><?= count($matches) ?></span></h4>
      <?php foreach (array_slice($matches, 0, 3) as $i => $previous): ?><div class="related-case"><strong><a href="<?= h(br_url('complaint.php', ['id' => $previous['id']])) ?>"><?= h($previous['id']) ?> · <?= h($previous['title']) ?></a></strong><p><?= h($previous['resolution']['notes']) ?></p><textarea id="recommendation-draft-<?= $i ?>" hidden><?= h($previous['recommendation'] ?: $previous['resolution']['notes']) ?></textarea><button type="button" class="link-button mt-2" data-use-recommendation="recommendation-draft-<?= $i ?>">Use previous recommendation as a draft</button></div><?php endforeach ?>
      <?php if (!$matches): ?><p class="form-text mb-0">No official-closed cases in this category yet.</p><?php endif ?>
      <p class="form-text mb-0 mt-2">Category match only. Review applicability before saving the official action.</p>
    </div>
    <?php endif ?>
    <?php require __DIR__ . '/includes/components/concern-insights.php'; require __DIR__ . '/includes/components/evidence.php'; require __DIR__ . '/includes/components/complaint-actions.php'; ?>
  </div><aside class="case-timeline"><h3><?= br_icon('clock') ?>Activity timeline</h3>
    <?php foreach (array_reverse($c['timeline']) as $event): ?><div class="timeline-entry"><strong><?= h(($event['title'] ?? '') === 'Returned for Information' ? 'Information requested from reporter' : $event['title']) ?></strong><span class="timeline-date"><?= h(br_date($event['date'], true)) ?><br><?= h($event['actor']) ?></span><p><?= h($event['note']) ?></p>
    <?php if (!empty($event['priorityDecision'])): ?><p class="form-text">System priority: <?= h($event['priorityDecision']['recommended']) ?> · Official priority: <?= h($event['priorityDecision']['priority']) ?> (<?= $event['priorityDecision']['overridden'] ? 'overridden' : 'accepted' ?>)</p><?php endif ?>
    <?php if (!empty($event['dueAt'])): ?><p class="form-text">Target completion: <?= h(date('M j, Y · g:i A',(int)$event['dueAt'])) ?></p><?php endif ?>
    <?php if (!empty($event['changes'])): ?><details><summary>Changed information</summary><dl class="mt-2 small"><?php foreach ($event['changes']['before'] as $field => $previousValue): $currentValue = $event['changes']['after'][$field] ?? ''; if ($previousValue === $currentValue) continue; ?><dt><?= h(ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $field))) ?></dt><dd>Before: <?= h(is_array($previousValue) ? implode(', ', $previousValue) : $previousValue) ?><br>After: <?= h(is_array($currentValue) ? implode(', ', $currentValue) : $currentValue) ?></dd><?php endforeach ?></dl></details><?php endif ?>
    <?php if (!empty($event['photo'])): ?><span class="photo-label"><?= h(ConcernInsights::evidenceStage($event)) ?></span><img class="case-photo" src="<?= h(!empty($event['evidenceId']) ? br_url('evidence.php', ['id' => $event['evidenceId']]) : $event['photo']) ?>" loading="lazy" alt="<?= h(ConcernInsights::evidenceStage($event)) ?>"><?php endif ?></div><?php endforeach ?>
  </aside></div>
</article>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
