<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('reports', ['official']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Use concern history to understand recurring concerns and community needs.', br_export());

$locations = br_group($cases, fn($c) => $c['locationDetails']['purok'] ?? (preg_match('/Purok \d+/i', $c['location'], $match) ? $match[0] : $c['location']));
$renderRanking = static function (array $rows, string $emptyMessage = 'No data recorded yet.'): void {
    $counts = array_map(fn($row) => (int)$row['count'], $rows);
    $maximum = $counts ? max($counts) : 1;
    if (!$rows) { ?>
        <div class="report-empty-state"><?= br_icon('chart') ?><p><?= h($emptyMessage) ?></p></div>
    <?php return; }
    foreach ($rows as $row): ?>
        <div class="report-ranking-row">
            <div class="report-ranking-label"><span><?= h($row['label']) ?></span><strong><?= (int)$row['count'] ?></strong></div>
            <progress max="<?= $maximum ?>" value="<?= (int)$row['count'] ?>" aria-label="<?= h($row['label']) ?>: <?= (int)$row['count'] ?>"></progress>
        </div>
    <?php endforeach;
};
?>

<section class="report-section" aria-labelledby="reports-overview-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Overview</p><h2 class="section-title" id="reports-overview-title">Current outcomes</h2></div>
    <p>Key results across all saved concerns.</p>
  </div>
  <div class="stats-grid reports-stats-grid">
    <?php br_stat('Official-closed', $metrics['verified'], 'Outcomes closed after review', 'checkCircle', 'green', 'verified');
    br_stat('Awaiting official review', $metrics['resolved'], 'Work marked resolved by personnel', 'clock', 'blue', 'resolved');
    br_stat('Ever reopened', $metrics['reopened'], 'Reports needing another attempt', 'refresh', 'rose', 'reopened'); ?>
    <div class="stat-card"><div class="stat-top"><span>Average resolution time</span><span class="stat-icon"><?= br_icon('clock') ?></span></div><div class="number"><?= h($metrics['average']) ?><small class="stat-unit"> days</small></div><div class="stat-caption">Submitted → latest resolution*</div></div>
  </div>
</section>

<section class="report-section" aria-labelledby="reports-week-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">This week</p><h2 class="section-title" id="reports-week-title">Concerns and planned response</h2></div>
    <p>Review what residents reported and the action plans created for this period.</p>
  </div>
  <?php require __DIR__ . '/includes/components/weekly-concerns.php'; ?>
</section>

<?php
$points = [];
foreach ($cases as $case) foreach ($case['keyPoints'] ?? [] as $point) $points[] = ['point' => $point];
$structuredReports = [
    'Most common concern types' => ['rows' => br_group($cases, fn($c) => $c['concernType'] ?? 'Legacy / unspecified'), 'subtitle' => 'Specific issues reported most often'],
    'Common key points' => ['rows' => br_group($points, 'point'), 'subtitle' => 'Details repeated across concern descriptions'],
    'Concerns by priority' => ['rows' => br_group($cases, 'priority'), 'subtitle' => 'Current official priority levels'],
    'Concerns reported per month' => ['rows' => br_group($cases, fn($c) => date('Y-m', strtotime($c['createdAt']))), 'subtitle' => 'Submission volume over time'],
    'Active personnel workload' => ['rows' => br_group(array_filter($cases, fn($c) => in_array($c['status'], ['Assigned', 'In Progress'], true)), fn($c) => $c['assignedName'] ?? 'Legacy team assignment — select personnel'), 'subtitle' => 'Assigned and in-progress work'],
];
?>
<section class="report-section" aria-labelledby="reports-patterns-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Patterns</p><h2 class="section-title" id="reports-patterns-title">What the records show</h2></div>
    <p>Ranked views make frequent issues and workload easier to compare.</p>
  </div>
  <div class="report-grid report-grid-ranked">
    <?php foreach ($structuredReports as $title => $report): ?>
      <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title"><?= h($title) ?></h3><p class="panel-subtitle"><?= h($report['subtitle']) ?></p></div></div><div class="report-body"><?php $renderRanking($report['rows']); ?></div></section>
    <?php endforeach ?>
  </div>
</section>

<section class="report-section" aria-labelledby="reports-distribution-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Distribution</p><h2 class="section-title" id="reports-distribution-title">Where concerns are concentrated</h2></div>
    <p>Compare category, location, status, and repeat-report signals.</p>
  </div>
  <div class="report-grid">
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concerns by category</h3><p class="panel-subtitle">All saved concerns grouped by category</p></div></div><?php br_chart($cases, true); ?></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Frequently reported locations</h3><p class="panel-subtitle">Grouped by purok when available</p></div></div><div class="report-body"><?php $renderRanking($locations, 'No location data recorded yet.'); ?></div></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concern status breakdown</h3><p class="panel-subtitle">Current position in the response workflow</p></div></div><div class="report-body report-status-list"><?php $statusRows = br_group($cases, 'status'); foreach ($statusRows as $row): ?><div class="insight-row"><?= br_status(['status' => $row['label']]) ?><strong><?= (int)$row['count'] ?></strong></div><?php endforeach ?><?php if (!$statusRows): ?><div class="report-empty-state"><?= br_icon('inbox') ?><p>No status data recorded yet.</p></div><?php endif ?></div></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concerns reported more than once</h3><p class="panel-subtitle">Categories with more than one saved report</p></div></div><div class="report-body"><?php $repeated = array_values(array_filter(br_group($cases, 'category'), fn($row) => $row['count'] > 1)); $renderRanking($repeated, 'No category has been reported more than once.'); ?><p class="form-text report-card-note">Repeated categories are a planning signal, not proof that the same problem recurred.</p></div></section>
  </div>
  <p class="form-text report-method-note">All statistics use the saved concern records. *Average includes currently Resolved and official-closed concerns; reopened, referred, and rejected cases are excluded.</p>
</section>

<section class="report-section" aria-labelledby="reports-operations-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Follow-up</p><h2 class="section-title" id="reports-operations-title">Recurring issues and team capacity</h2></div>
    <p>Use these operational views to coordinate repeat concerns and active assignments.</p>
  </div>
  <div class="report-followup-stack">
    <?php require __DIR__ . '/includes/components/recurring-issues.php'; require __DIR__ . '/includes/components/workload-table.php'; ?>
  </div>
</section>

<?php
$decisions = array_values(array_filter($cases, fn($case) => !empty($case['priorityDecision'])));
$overridden = count(array_filter($decisions, fn($case) => $case['priorityDecision']['overridden']));
$satisfaction = br_store()->feedbackAnalytics($actor['id']);
?>
<section class="report-section" aria-labelledby="reports-quality-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Quality review</p><h2 class="section-title" id="reports-quality-title">Decisions and resident feedback</h2></div>
    <p>Compare official priority decisions and review resolution ratings.</p>
  </div>
  <div class="report-quality-grid">
    <section class="panel"><div class="panel-header"><div><h3 class="panel-title">Recommended and official priorities</h3><p class="panel-subtitle"><?= count($decisions) ?> confirmed decisions · <?= $overridden ?> overridden · <?= count($cases)-count($decisions) ?> without a recorded decision</p></div></div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>System recommendation</th><th>Low decision</th><th>Medium decision</th><th>High decision</th><th>Urgent decision</th></tr></thead><tbody>
      <?php foreach (ComplaintWorkflow::PRIORITIES as $recommended): ?><tr><th><?= h($recommended) ?></th><?php foreach (ComplaintWorkflow::PRIORITIES as $finalPriority): ?><td><?= count(array_filter($decisions,fn($case) => $case['priorityDecision']['recommended'] === $recommended && $case['priorityDecision']['priority'] === $finalPriority)) ?></td><?php endforeach ?></tr><?php endforeach ?>
      </tbody></table></div><p class="form-text p-3 mb-0">Latest saved official decision per concern. Earlier decisions remain in the activity history. Legacy concerns need a new assessment or edit before they enter this comparison.</p>
    </section>
    <section class="panel"><div class="panel-header"><div><h3 class="panel-title">Resolution satisfaction</h3><p class="panel-subtitle">Private feedback from original reporters</p></div></div><div class="panel-body">
      <div class="satisfaction-summary"><div><strong><?= h($satisfaction['summary']['average'] ?? '—') ?></strong><span>Average rating out of 5</span></div><div><strong><?= (int)$satisfaction['summary']['responses'] ?></strong><span>Feedback responses</span></div></div>
      <?php if ($satisfaction['categories']): ?><div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Responses</th><th>Average rating</th></tr></thead><tbody><?php foreach($satisfaction['categories'] as $row): ?><tr><td><?= h($row['category']) ?></td><td><?= (int)$row['responses'] ?></td><td><?= h($row['average']) ?> / 5</td></tr><?php endforeach ?></tbody></table></div><?php else: ?><div class="report-empty-state report-empty-state-compact"><?= br_icon('checkCircle') ?><p>No resolution feedback has been submitted yet.</p></div><?php endif ?>
      <h4 class="section-title report-comments-title">Recent comments</h4><?php foreach($satisfaction['recent'] as $row): ?><article class="case-section report-comment"><a href="<?= h(br_url('concern.php',['id'=>$row['complaint_id']])) ?>"><?= h($row['complaint_id']) ?></a> · <?= (int)$row['rating'] ?> / 5<p><?= h($row['comment']) ?></p></article><?php endforeach ?><?php if (!$satisfaction['recent']): ?><p class="form-text">No recent comments.</p><?php endif ?><p class="form-text mb-0">Feedback is private to the original reporter and officials. These statistics do not include reporter identities.</p>
    </div></section>
  </div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
