<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('reports', ['official']);
extract($context);
try {
    $reportPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
    if ($reportPage === false || $reportPage < 1) throw new DomainException('Choose a valid report page.');
    $report = br_store()->concernReport($actor['id'], $_GET, $reportPage);
} catch (DomainException $error) { br_page_error($context, 422, 'Check report filters', $error->getMessage()); }
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Filter saved concerns, review matching insights, and download a branded report.', '<a class="btn btn-primary" href="'.h(br_url('reports-pdf.php', ConcernReportFilters::query($report['filters']))).'">'.br_icon('download').'Export to PDF</a>');
$metrics = $report['metrics'];
$groups = $report['groups'];
require __DIR__ . '/includes/components/report-filters.php';

$locations = $groups['location'];
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
    <p>Key results across the <?= (int)$metrics['total'] ?> matching concerns.</p>
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
    <p>Independent scope: all concerns and action plans for the current week; report filters do not apply.</p>
  </div>
  <?php require __DIR__ . '/includes/components/weekly-concerns.php'; ?>
</section>

<?php
$structuredReports = [
    'Most common concern types' => ['rows' => $groups['type'], 'subtitle' => 'Specific issues in matching concerns'],
    'Common key points' => ['rows' => $groups['keypoint'], 'subtitle' => 'Details repeated across matching concerns'],
    'Concerns by priority' => ['rows' => $groups['priority'], 'subtitle' => 'Current official priority levels'],
    'Concerns reported per month' => ['rows' => $groups['month'], 'subtitle' => 'Matching submissions in chronological order'],
    'Active personnel workload' => ['rows' => $groups['workload'], 'subtitle' => 'Matching Assigned and In Progress concerns only'],
];
?>
<section class="report-section" aria-labelledby="reports-patterns-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Patterns</p><h2 class="section-title" id="reports-patterns-title">What the records show</h2></div>
    <p>Ranked views make frequent issues and workload easier to compare.</p>
  </div>
  <div class="report-grid report-grid-ranked">
    <?php foreach ($structuredReports as $title => $rankedReport): ?>
      <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title"><?= h($title) ?></h3><p class="panel-subtitle"><?= h($rankedReport['subtitle']) ?></p></div></div><div class="report-body"><?php $renderRanking($rankedReport['rows']); ?></div></section>
    <?php endforeach ?>
  </div>
</section>

<section class="report-section" aria-labelledby="reports-distribution-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Distribution</p><h2 class="section-title" id="reports-distribution-title">Where concerns are concentrated</h2></div>
    <p>Compare category, location, status, and repeat-report signals.</p>
  </div>
  <div class="report-grid">
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concerns by category</h3><p class="panel-subtitle">Matching concerns grouped by category</p></div></div><?php br_chart([], true, $groups['category']); ?></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Frequently reported locations</h3><p class="panel-subtitle">Grouped by purok when available</p></div></div><div class="report-body"><?php $renderRanking($locations, 'No location data recorded yet.'); ?></div></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concern status breakdown</h3><p class="panel-subtitle">Current position in the response workflow</p></div></div><div class="report-body report-status-list"><?php $statusRows = $groups['status']; foreach ($statusRows as $row): ?><div class="insight-row"><?= br_status(['status' => $row['label']]) ?><strong><?= (int)$row['count'] ?></strong></div><?php endforeach ?><?php if (!$statusRows): ?><div class="report-empty-state"><?= br_icon('inbox') ?><p>No status data recorded yet.</p></div><?php endif ?></div></section>
    <section class="panel report-card"><div class="panel-header"><div><h3 class="panel-title">Concerns reported more than once</h3><p class="panel-subtitle">Categories with multiple matching reports</p></div></div><div class="report-body"><?php $repeated = array_values(array_filter($groups['category'], fn($row) => $row['count'] > 1)); $renderRanking($repeated, 'No category has been reported more than once.'); ?><p class="form-text report-card-note">Repeated categories are a planning signal, not proof that the same problem recurred.</p></div></section>
  </div>
  <p class="form-text report-method-note">These statistics use every matching concern, not just the preview page. *Average includes currently Resolved and official-closed concerns; reopened, referred, and rejected cases are excluded.</p>
</section>

<section class="report-section" aria-labelledby="reports-operations-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Follow-up</p><h2 class="section-title" id="reports-operations-title">Recurring issues and team capacity</h2></div>
    <p>Independent scope: recurrence uses its configured history period; team capacity uses all current work. Report filters do not apply.</p>
  </div>
  <div class="report-followup-stack">
    <?php require __DIR__ . '/includes/components/recurring-issues.php'; require __DIR__ . '/includes/components/workload-table.php'; ?>
  </div>
</section>

<?php
$decisions = $report['decisions'];
$overridden = $report['overrides'];
$satisfaction = $report['satisfaction'];
?>
<section class="report-section" aria-labelledby="reports-quality-title">
  <div class="report-section-heading">
    <div><p class="report-section-kicker">Quality review</p><h2 class="section-title" id="reports-quality-title">Decisions and resident feedback</h2></div>
    <p>Official priority decisions and resolution ratings for matching concerns only.</p>
  </div>
  <div class="report-quality-grid">
    <section class="panel"><div class="panel-header"><div><h3 class="panel-title">Recommended and official priorities</h3><p class="panel-subtitle"><?= $report['decisionCount'] ?> confirmed decisions · <?= $overridden ?> overridden · <?= $metrics['total']-$report['decisionCount'] ?> without a recorded decision</p></div></div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>System recommendation</th><th>Low decision</th><th>Medium decision</th><th>High decision</th><th>Urgent decision</th></tr></thead><tbody>
      <?php foreach (ComplaintWorkflow::PRIORITIES as $recommended): ?><tr><th><?= h($recommended) ?></th><?php foreach (ComplaintWorkflow::PRIORITIES as $finalPriority): ?><td><?= (int)($decisions[$recommended][$finalPriority] ?? 0) ?></td><?php endforeach ?></tr><?php endforeach ?>
      </tbody></table></div><p class="form-text p-3 mb-0">Latest saved official decision per concern. Earlier decisions remain in the activity history. Legacy concerns need a new assessment or edit before they enter this comparison.</p>
    </section>
    <section class="panel"><div class="panel-header"><div><h3 class="panel-title">Resolution satisfaction</h3><p class="panel-subtitle">Private feedback from original reporters</p></div></div><div class="panel-body">
      <div class="satisfaction-summary"><div><strong><?= h($satisfaction['summary']['average'] ?? '—') ?></strong><span>Average rating out of 5</span></div><div><strong><?= (int)$satisfaction['summary']['responses'] ?></strong><span>Feedback responses</span></div></div>
      <?php if ($satisfaction['categories']): ?><div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Responses</th><th>Average rating</th></tr></thead><tbody><?php foreach($satisfaction['categories'] as $row): ?><tr><td><?= h($row['category']) ?></td><td><?= (int)$row['responses'] ?></td><td><?= h($row['average']) ?> / 5</td></tr><?php endforeach ?></tbody></table></div><?php else: ?><div class="report-empty-state report-empty-state-compact"><?= br_icon('checkCircle') ?><p>No resolution feedback has been submitted yet.</p></div><?php endif ?>
      <h4 class="section-title report-comments-title">Recent comments</h4><?php foreach($satisfaction['recent'] as $row): ?><article class="case-section report-comment"><a href="<?= h(br_url('concern.php',['id'=>$row['complaint_id']])) ?>"><?= h($row['complaint_id']) ?></a> · <?= (int)$row['rating'] ?> / 5<p><?= h($row['comment']) ?></p></article><?php endforeach ?><?php if (!$satisfaction['recent']): ?><p class="form-text">No recent comments.</p><?php endif ?><p class="form-text mb-0">Feedback is private to the original reporter and officials. These statistics do not include reporter identities.</p>
    </div></section>
  </div>
</section>
<?php require __DIR__ . '/includes/components/report-preview.php'; ?>
<script src="assets/js/reports.js?v=<?= filemtime(__DIR__.'/assets/js/reports.js') ?>" defer></script>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
