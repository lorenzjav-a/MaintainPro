<?php
$primary = is_array($c['primaryConcern'] ?? null) ? $c['primaryConcern'] : null;
$primaryId = (string)($c['primaryConcernId'] ?? ($primary['id'] ?? ''));
$linkedReports = is_array($c['linkedConcerns'] ?? null)
    ? $c['linkedConcerns']
    : (is_array($c['linkedReports'] ?? null) ? $c['linkedReports'] : []);
$linkedCount = (int)($c['linkedReportCount'] ?? count($linkedReports));
?>
<?php if ($primaryId !== '' || $linkedCount > 0): ?>
<section class="case-section linked-concern-summary" id="linked-reports" aria-labelledby="linked-reports-heading">
  <h3 id="linked-reports-heading"><?= br_icon('refresh') ?>Same physical issue</h3>
  <?php if ($primaryId !== ''): ?>
  <p>This report is linked to primary concern <a href="<?= h(br_url('complaint.php', ['id' => $primaryId])) ?>"><strong><?= h($primaryId) ?></strong></a>. Its original reference and report remain preserved; work progress follows the primary concern.</p>
  <?php else: ?>
  <p><strong><?= $linkedCount ?> resident report<?= $linkedCount === 1 ? '' : 's' ?></strong> <?= $linkedCount === 1 ? 'is' : 'are' ?> connected to this primary concern.</p>
  <?php endif ?>
  <?php if ($linkedReports): ?>
  <details class="mt-3"><summary>Open linked reports</summary><ul class="linked-report-list mt-3">
    <?php foreach ($linkedReports as $linked): ?>
    <li><a href="<?= h(br_url('complaint.php', ['id' => $linked['id'] ?? ''])) ?>"><?= h($linked['id'] ?? '') ?></a><span><?= h(br_status_label($linked['status'] ?? 'Submitted')) ?><?php if (!empty($linked['createdAt'] ?? $linked['created_at'] ?? '')): ?> · <?= h(br_date((string)($linked['createdAt'] ?? $linked['created_at']))) ?><?php endif ?></span></li>
    <?php endforeach ?>
  </ul></details>
  <?php endif ?>
</section>
<?php endif ?>
