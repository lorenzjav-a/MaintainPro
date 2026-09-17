<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('reports', ['official']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Use complaint history to understand recurring concerns and community needs.', br_export());
$locations = br_group($cases, fn($c) => preg_match('/Purok \d+/i', $c['location'], $match) ? $match[0] : $c['location']);
?>
<div class="stats-grid">
  <?php br_stat('Resident-verified', $metrics['verified'], 'Confirmed outcomes', 'checkCircle', 'green', 'verified');
  br_stat('Awaiting verification', $metrics['resolved'], 'Work marked resolved by personnel', 'clock', 'blue', 'resolved');
  br_stat('Ever reopened', $metrics['reopened'], 'Reports needing another attempt', 'refresh', 'rose', 'reopened'); ?>
  <div class="stat-card"><div class="stat-top"><span>Average resolution time</span><span class="stat-icon"><?= br_icon('clock') ?></span></div><div class="number"><?= h($metrics['average']) ?><small class="stat-unit"> days</small></div><div class="stat-caption">Submitted → latest resolution*</div></div>
</div>
<div class="report-grid">
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns by category</h2></div><?php br_chart($cases, true); ?></section>
  <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Frequently reported locations</h2><p class="panel-subtitle">Grouped by purok when available</p></div></div><div class="report-body"><?php foreach ($locations as $row): ?><div class="insight-row"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?> reports</strong></div><?php endforeach ?></div></section>
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Complaint status breakdown</h2></div><div class="report-body"><?php foreach (br_group($cases, 'status') as $row): ?><div class="insight-row"><?= br_status(['status' => $row['label']]) ?><strong><?= $row['count'] ?></strong></div><?php endforeach ?></div></section>
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns reported more than once</h2></div><div class="report-body"><?php foreach (br_group($cases, 'category') as $row): if ($row['count'] <= 1) continue; ?><div class="insight-row"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?> reports</strong></div><?php endforeach ?><p class="form-text mt-3">Repeated categories are a planning signal, not proof that the same problem recurred.</p></div></section>
</div>
<p class="form-text mt-3">All statistics use the saved complaint records. *Average includes currently Resolved and Verified complaints; reopened, referred, and rejected cases are excluded.</p>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
