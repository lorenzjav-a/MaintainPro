<?php
$compact = $compact ?? false;
$listPage = $page === 'history' ? 'history.php' : ($compact ? 'index.php' : 'complaints.php');
$tab = br_query('tab', 'all');
$tabLabels = ['all' => 'All concerns', 'pending' => 'Needs action', 'verified' => 'Verified', 'assessment' => 'Assessment', 'progress' => 'In progress', 'resolved' => 'To verify', 'urgent' => 'Urgent', 'reopened' => 'Ever reopened', 'submitted' => 'New reports', 'assigned' => 'New assignments', 'work' => 'Active work', 'reopened_now' => 'Reopened'];
if (!isset($tabLabels[$tab])) $tab = 'all';
$filters = ['tab' => $tab, 'search' => br_query('search'), 'category' => br_query('category'), 'priority' => br_query('priority'), 'status' => br_query('status')];
$filtered = array_values(array_filter($cases, function ($c) use ($filters, $page) {
    if ($page === 'history' && !($c['resolution'] || in_array($c['status'], ['Verified', 'Rejected', 'Referred to Another Office'], true))) return false;
    $matches = match ($filters['tab']) {
        'pending' => br_active($c), 'assessment' => br_review($c), 'urgent' => $c['priority'] === 'Urgent' && br_active($c),
        'work' => in_array($c['status'], ['Assigned', 'In Progress'], true), 'reopened' => $c['reopenCount'] > 0,
        'assigned' => $c['status'] === 'Assigned', 'submitted' => $c['status'] === 'Submitted', 'progress' => $c['status'] === 'In Progress',
        'resolved' => $c['status'] === 'Resolved', 'verified' => $c['status'] === 'Verified', 'reopened_now' => $c['status'] === 'Reopened', default => true,
    };
    if (!$matches) return false;
    foreach (['category', 'priority', 'status'] as $key) if ($filters[$key] !== '' && $filters[$key] !== $c[$key]) return false;
    return $filters['search'] === '' || mb_stripos(implode(' ', array_intersect_key($c, array_flip(['id', 'title', 'location', 'category', 'resident', 'team']))), $filters['search']) !== false;
}));
usort($filtered, function ($a, $b) use ($actor, $page) {
    if ($actor['role'] === 'personnel' && $page === 'complaints') {
        $rank = ['Urgent' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        return $rank[$a['priority']] <=> $rank[$b['priority']] ?: strtotime($a['createdAt']) <=> strtotime($b['createdAt']);
    }
    $key = $page === 'history' ? 'updatedAt' : 'createdAt';
    return strtotime($b[$key]) <=> strtotime($a[$key]);
});
$rows = $compact ? array_slice($filtered, 0, 6) : $filtered;
$shownTabs = ['all', 'pending', 'verified'];
if (!in_array($tab, $shownTabs, true)) $shownTabs[] = $tab;
?>
<?php if (!$cases): ?>
<section class="panel workspace-empty">
  <?= br_icon($actor['role'] === 'personnel' ? 'tool' : 'inbox') ?>
  <h3><?= match ($actor['role']) {'resident' => 'Your first report starts here', 'personnel' => 'No work assigned yet', default => 'Your complaint register is ready'} ?></h3>
  <p><?= h(match ($actor['role']) {'resident' => 'Report a community concern, suggest a solution, and follow the barangay’s response.', 'personnel' => 'Complaints assigned to ' . $actor['team'] . ' will appear here.', default => 'Residents can now create accounts and submit concerns. Create personnel accounts so your teams can receive assignments.'}) ?></p>
  <?php if ($actor['role'] === 'resident'): ?><?= br_primary($actor) ?><?php elseif ($actor['role'] === 'official'): ?><a class="btn btn-primary" href="users.php"><?= br_icon('users') ?>Manage personnel accounts</a><?php endif ?>
</section>
<?php else: ?>
<section class="panel">
  <div class="panel-header"><div><h2 class="panel-title"><?= $compact ? 'Recent complaints' : ($page === 'history' ? 'Complaint outcomes' : 'Complaint register') ?> <span class="count-pill"><?= count($filtered) ?></span></h2><?php if (!$compact): ?><p class="panel-subtitle">Open a complaint to view its details and available actions.</p><?php endif ?></div><?php if ($compact): ?><a class="link-button" href="complaints.php">View all <?= br_icon('arrow') ?></a><?php endif ?></div>
  <div class="panel-toolbar"><nav class="filter-tabs" aria-label="Complaint filters"><?php foreach ($shownTabs as $value): ?><a class="filter-tab<?= $tab === $value ? ' active' : '' ?>" href="<?= h(br_url($listPage, array_replace($filters, ['tab' => $value]))) ?>"<?= $tab === $value ? ' aria-current="true"' : '' ?>><?= h($tabLabels[$value]) ?></a><?php endforeach ?></nav></div>
  <form class="filter-row pt-3" method="get" action="<?= h($listPage) ?>" role="search">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="search-field"><?= br_icon('search') ?><input id="case-search" name="search" type="search" aria-label="Search complaints by ID, title, location, category, resident, or team" placeholder="Search complaints…" value="<?= h($filters['search']) ?>"></div>
    <?php if (!$compact): ?>
    <select class="form-select form-select-sm" name="category" aria-label="Filter by category"><?php br_options(ComplaintWorkflow::CATEGORIES, $filters['category'], 'All categories'); ?></select>
    <select class="form-select form-select-sm" name="priority" aria-label="Filter by priority"><?php br_options(ComplaintWorkflow::PRIORITIES, $filters['priority'], 'All priorities'); ?></select>
    <select class="form-select form-select-sm" name="status" aria-label="Filter by status"><?php br_options(ComplaintWorkflow::STATUSES, $filters['status'], 'All statuses'); ?></select>
    <?php endif ?>
    <button class="btn btn-light btn-sm" type="submit">Apply filters</button>
  </form>
  <?php if ($rows): ?>
  <div class="table-responsive"><table class="table complaint-table"><caption class="visually-hidden">Complaints available to your account</caption><thead><tr><th scope="col">Complaint</th><th scope="col" class="category-cell">Category</th><th scope="col">Priority</th><th scope="col">Status &amp; next step</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead><tbody>
    <?php foreach ($rows as $row): $caseUrl = br_url('complaint.php', ['id' => $row['id']]); ?>
    <tr><td data-label="Complaint"><a class="case-link" href="<?= h($caseUrl) ?>"><?= h($row['title']) ?></a><div class="case-meta"><span class="case-ref"><?= h($row['id']) ?></span><?= br_icon('pin') ?><?= h($row['location']) ?></div></td><td class="category-cell" data-label="Category"><?= h($row['category']) ?></td><td data-label="Priority"><?= br_priority($row) ?></td><td data-label="Status"><?= br_status($row) ?><span class="next-step"><?= h(br_next_step($row, $actor['role'])) ?></span></td><td class="open-cell"><a class="open-case-btn" href="<?= h($caseUrl) ?>">Open <?= br_icon('chevron') ?></a></td></tr>
    <?php endforeach ?>
  </tbody></table></div>
  <?php else: ?><div class="empty-state"><?= br_icon('inbox') ?><h3>No complaints match this view</h3><p>Try a different search or clear the filters.</p><a class="btn btn-light btn-sm" href="<?= h($listPage) ?>">Clear all filters</a></div><?php endif ?>
  <div class="table-foot"><span>Showing <?= count($rows) ?> of <?= count($filtered) ?> complaints</span><a class="link-button" href="<?= h($listPage) ?>">Clear filters</a></div>
</section>
<?php endif ?>
