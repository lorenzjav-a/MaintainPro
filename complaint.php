<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('complaint');
$c = br_find_case($context, br_query('id'));
extract($context);
$pageTitle = $c['id'] . ' · ' . $c['title'];
require __DIR__ . '/includes/layout/header.php';
br_heading($c['title'], 'Complaint ' . $c['id'] . ' · ' . $c['category'], '<a class="btn btn-light" href="complaints.php">Back to complaints</a>');
$steps = ['Submitted' => 'Report', 'Under Review' => 'Assess & recommend', 'Assigned' => 'Assign', 'In Progress' => 'Take action', 'Resolved' => 'Resolve', 'Verified' => 'Verify'];
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
  <div class="workflow-steps" aria-label="Complaint progress">
    <?php foreach (array_values($steps) as $i => $label): ?>
    <div class="workflow-step <?= $stepIndex !== false && $i < $stepIndex ? 'complete' : ($i === $stepIndex ? 'current' : '') ?>"<?= $i === $stepIndex ? ' aria-current="step"' : '' ?>><span class="step-circle"><?= $stepIndex !== false && $i < $stepIndex ? br_icon('check') : $i + 1 ?></span><span><?= h($label) ?></span></div>
    <?php endforeach ?>
  </div>
  <div class="case-layout"><div class="case-main">
    <?php if (in_array($c['status'], ['Reopened', 'Returned for Information', 'Rejected', 'Referred to Another Office'], true)): ?><div class="status-note"><strong><?= h($c['status']) ?></strong><br><?= h($c['timeline'][count($c['timeline']) - 1]['note']) ?></div><?php endif ?>
    <section class="case-section"><h3><?= br_icon('inbox') ?>Reported concern</h3><p class="case-description"><?= h($c['description']) ?></p><p><?= br_icon('pin') ?> <?= h($c['location']) ?></p><?php br_photo($c['photo'], 'Supporting photo'); ?></section>
    <section class="case-section suggestion-box"><div class="eyebrow">RESIDENT SUGGESTED SOLUTION</div><p><?= h($c['suggestion'] ?: 'No solution suggested by the resident.') ?></p></section>
    <section class="case-section recommendation-box"><h3><?= br_icon('shield') ?>Barangay recommended action</h3><p><?= h($c['recommendation'] ?: 'The barangay has not recorded an official recommendation yet.') ?></p><?php if ($c['recommendation']): ?><div class="recommendation-author">Official assessment · <?= h($c['assessment'] ?: 'No additional notes') ?></div><?php endif ?></section>
    <?php if ($c['resolution']): ?><section class="case-section"><h3><?= br_icon('checkCircle') ?><?= $c['status'] === 'Reopened' ? 'Previous resolution attempt' : 'Recorded resolution' ?></h3><p><?= h($c['resolution']['notes']) ?></p><div class="photo-label"><?= h($c['resolution']['team']) ?> · <?= h(br_date($c['resolution']['date'], true)) ?></div><?php br_photo($c['resolution']['photo'], 'Completion photo'); ?></section><?php endif ?>
    <?php if ($c['feedback']): ?><section class="case-section suggestion-box"><div class="eyebrow">RESIDENT FEEDBACK</div><p><?= h($c['feedback']) ?></p></section><?php endif ?>
    <?php if ($actor['role'] === 'official' && br_review($c)):
        $matches = array_values(array_filter($cases, fn($x) => $x['id'] !== $c['id'] && $x['category'] === $c['category'] && $x['status'] === 'Verified' && $x['resolution']));
    ?>
    <div class="related-box"><h4><?= br_icon('book') ?> Similar verified cases <span class="count-pill"><?= count($matches) ?></span></h4>
      <?php foreach (array_slice($matches, 0, 3) as $i => $previous): ?><div class="related-case"><strong><a href="<?= h(br_url('complaint.php', ['id' => $previous['id']])) ?>"><?= h($previous['id']) ?> · <?= h($previous['title']) ?></a></strong><p><?= h($previous['resolution']['notes']) ?></p><textarea id="recommendation-draft-<?= $i ?>" hidden><?= h($previous['recommendation'] ?: $previous['resolution']['notes']) ?></textarea><button type="button" class="link-button mt-2" data-use-recommendation="recommendation-draft-<?= $i ?>">Use previous recommendation as a draft</button></div><?php endforeach ?>
      <?php if (!$matches): ?><p class="form-text mb-0">No verified cases in this category yet.</p><?php endif ?>
      <p class="form-text mb-0 mt-2">Category match only. Review applicability before saving the official action.</p>
    </div>
    <?php endif ?>
    <?php require __DIR__ . '/includes/components/complaint-actions.php'; ?>
  </div><aside class="case-timeline"><h3><?= br_icon('clock') ?>Activity timeline</h3>
    <?php foreach (array_reverse($c['timeline']) as $event): ?><div class="timeline-entry"><strong><?= h($event['title']) ?></strong><span class="timeline-date"><?= h(br_date($event['date'], true)) ?><br><?= h($event['actor']) ?></span><p><?= h($event['note']) ?></p><?php if (!empty($event['photo'])): ?><img class="case-photo" src="<?= h($event['photo']) ?>" alt="Completion evidence recorded with this event"><?php endif ?></div><?php endforeach ?>
  </aside></div>
</article>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
