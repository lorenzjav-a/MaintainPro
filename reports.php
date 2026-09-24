<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('reports', ['official']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Use concern history to understand recurring concerns and community needs.', br_export());
$locations = br_group($cases, fn($c) => $c['locationDetails']['purok'] ?? (preg_match('/Purok \d+/i', $c['location'], $match) ? $match[0] : $c['location']));
?>
<div class="stats-grid">
  <?php br_stat('Official-closed', $metrics['verified'], 'Outcomes closed after review', 'checkCircle', 'green', 'verified');
  br_stat('Awaiting official review', $metrics['resolved'], 'Work marked resolved by personnel', 'clock', 'blue', 'resolved');
  br_stat('Ever reopened', $metrics['reopened'], 'Reports needing another attempt', 'refresh', 'rose', 'reopened'); ?>
  <div class="stat-card"><div class="stat-top"><span>Average resolution time</span><span class="stat-icon"><?= br_icon('clock') ?></span></div><div class="number"><?= h($metrics['average']) ?><small class="stat-unit"> days</small></div><div class="stat-caption">Submitted → latest resolution*</div></div>
</div>
<?php
$points = [];
foreach ($cases as $case) foreach ($case['keyPoints'] ?? [] as $point) $points[] = ['point' => $point];
$structuredReports = [
    'Most common concern types' => br_group($cases, fn($c) => $c['concernType'] ?? 'Legacy / unspecified'),
    'Common key points' => br_group($points, 'point'),
    'Concerns by priority' => br_group($cases, 'priority'),
    'Concerns reported per month' => br_group($cases, fn($c) => date('Y-m', strtotime($c['createdAt']))),
    'Active personnel workload' => br_group(array_filter($cases, fn($c) => in_array($c['status'], ['Assigned', 'In Progress'], true)), fn($c) => $c['assignedName'] ?? 'Legacy team assignment — select personnel'),
]; ?>
<div class="report-grid mt-4"><?php foreach ($structuredReports as $title => $rows): ?><section class="panel"><div class="panel-header"><h2 class="panel-title"><?= h($title) ?></h2></div><div class="report-body"><?php foreach ($rows as $row): ?><div class="insight-row"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?></strong></div><?php endforeach ?><?php if (!$rows): ?><p class="text-muted">No data yet.</p><?php endif ?></div></section><?php endforeach ?></div>
<div class="report-grid">
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns by category</h2></div><?php br_chart($cases, true); ?></section>
  <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Frequently reported locations</h2><p class="panel-subtitle">Grouped by purok when available</p></div></div><div class="report-body"><?php foreach ($locations as $row): ?><div class="insight-row"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?> reports</strong></div><?php endforeach ?></div></section>
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Concern status breakdown</h2></div><div class="report-body"><?php foreach (br_group($cases, 'status') as $row): ?><div class="insight-row"><?= br_status(['status' => $row['label']]) ?><strong><?= $row['count'] ?></strong></div><?php endforeach ?></div></section>
  <section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns reported more than once</h2></div><div class="report-body"><?php foreach (br_group($cases, 'category') as $row): if ($row['count'] <= 1) continue; ?><div class="insight-row"><span><?= h($row['label']) ?></span><strong><?= $row['count'] ?> reports</strong></div><?php endforeach ?><p class="form-text mt-3">Repeated categories are a planning signal, not proof that the same problem recurred.</p></div></section>
</div>
<p class="form-text mt-3">All statistics use the saved concern records. *Average includes currently Resolved and official-closed concerns; reopened, referred, and rejected cases are excluded.</p>
<?php require __DIR__ . '/includes/components/recurring-issues.php'; require __DIR__ . '/includes/components/workload-table.php'; ?>
<?php
$decisions = array_values(array_filter($cases, fn($case) => !empty($case['priorityDecision'])));
$overridden = count(array_filter($decisions, fn($case) => $case['priorityDecision']['overridden']));
?>
<section class="panel mt-4"><div class="panel-header"><div><h2 class="panel-title">Recommended and official priorities</h2><p class="panel-subtitle"><?= count($decisions) ?> confirmed decisions · <?= $overridden ?> overridden · <?= count($cases)-count($decisions) ?> without a recorded decision</p></div></div>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>System recommendation</th><th>Low decision</th><th>Medium decision</th><th>High decision</th><th>Urgent decision</th></tr></thead><tbody>
<?php foreach (ComplaintWorkflow::PRIORITIES as $recommended): ?><tr><th><?= h($recommended) ?></th><?php foreach (ComplaintWorkflow::PRIORITIES as $finalPriority): ?><td><?= count(array_filter($decisions,fn($case) => $case['priorityDecision']['recommended'] === $recommended && $case['priorityDecision']['priority'] === $finalPriority)) ?></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div><p class="form-text p-3 mb-0">Latest saved official decision per concern. Earlier decisions remain in the activity history. Legacy concerns need a new assessment/edit before they enter this comparison.</p></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
