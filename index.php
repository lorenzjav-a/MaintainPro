<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
if (!br_actor()) { header('Location: landing.php'); exit; }
extract(br_page('overview'));
require __DIR__ . '/includes/layout/header.php';
$description = match ($actor['role']) {'official' => 'Assess new reports, coordinate teams, and follow reopened concerns.', 'resident' => 'Track your reports and confirm completed work.', default => 'Track assignments and completed work for ' . $actor['team'] . '.'};
br_heading($pageTitle, $description, ($actor['role'] === 'official' ? br_export() : '') . br_primary($actor));
require __DIR__ . '/includes/components/stats.php';
require __DIR__ . '/includes/components/banner.php';
?>
<div class="overview-grid">
  <div>
    <?php $compact = true; require __DIR__ . '/includes/components/complaint-table.php'; ?>
    <?php if ($actor['role'] === 'official'):
        $teams = br_group(array_filter($cases, fn($c) => $c['team'] && in_array($c['status'], ['Assigned', 'In Progress', 'Reopened'], true)), 'team');
        $ratio = $metrics['total'] ? (int)round($metrics['verified'] / $metrics['total'] * 100) : 0;
    ?>
    <div class="bottom-panels">
      <section class="panel"><div class="panel-header"><h2 class="panel-title">Team workload</h2><span class="count-pill">ACTIVE WORK</span></div><div class="team-list">
        <?php foreach (array_slice($teams, 0, 3) as $team): ?><div class="team-row"><span class="team-icon"><?= br_icon('users') ?></span><span class="team-name"><?= h($team['label']) ?></span><span class="team-badge"><?= $team['count'] ?> open</span></div><?php endforeach ?>
        <?php if (!$teams): ?><p class="text-muted small">No active team assignments.</p><?php endif ?>
      </div></section>
      <section class="panel"><div class="panel-header"><h2 class="panel-title">Closing the loop</h2><?= br_icon('checkCircle') ?></div><div class="resolution-summary"><div class="completion-ring" style="--pct:<?= $ratio ?>%" role="img" aria-label="<?= $ratio ?> percent of concerns verified"><span><?= $ratio ?>%</span></div><div class="resolution-copy"><strong><?= $metrics['verified'] ?> of <?= $metrics['total'] ?> reports official-reviewed</strong><p><?= $metrics['resolved'] ?> resolved and awaiting official review.<br>An official reviews the evidence before closing the concern.</p><a class="link-button" href="history.php">View resolution history <?= br_icon('arrow') ?></a></div></div></section>
    </div>
    <?php require __DIR__ . '/includes/components/recurring-issues.php'; ?>
    <?php endif ?>
  </div>
  <aside class="side-stack">
    <?php if ($actor['role'] === 'official'): ?><section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns by category</h2></div><?php br_chart($cases); ?><div class="chart-note">Top categories · <?= $metrics['total'] ?> total reports</div></section><?php endif ?>
    <?php require __DIR__ . '/includes/components/activity.php'; ?>
    <section class="workflow-card">
    <?php if ($actor['role'] === 'official'): ?>
      <?= br_icon('users') ?><h3>Build your barangay team.</h3><p>Create official and personnel accounts, assign teams, and manage account access.</p><a class="link-button" href="users.php">User management <?= br_icon('arrow') ?></a>
    <?php else: ?>
      <?= br_icon($actor['role'] === 'resident' ? 'checkCircle' : 'tool') ?><h3><?= $actor['role'] === 'resident' ? 'Your confirmation closes the report.' : 'Record the work as it happens.' ?></h3><p><?= $actor['role'] === 'resident' ? 'Once work is marked resolved, review the result. Verify it or explain what still needs attention.' : 'Start assigned work, add progress notes, and submit the resolution for official review.' ?></p><button class="link-button" type="button" data-help>How the process works <?= br_icon('arrow') ?></button>
    <?php endif ?>
    </section>
  </aside>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
