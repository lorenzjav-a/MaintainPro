<?php
$activePage = match ($page) {'complaint' => 'complaints', 'user-create', 'user-edit' => 'users', default => $page};
$links = [['overview', 'index.php', 'Dashboard', 'grid', null], ['complaints', 'complaints.php', $titles['complaints'], 'inbox', $metrics['total']]];
$links[] = ['new-complaint', 'report-concern.php', 'Report Concern', 'inbox', null];
if ($actor['role'] !== 'resident') $links[] = ['my-reports', 'complaints.php?scope=mine', 'My reported concerns', 'clipboard', null];
if ($page === 'complaints' && ($scope ?? '') === 'mine' && $actor['role'] !== 'resident') $activePage = 'my-reports';
if ($actor['role'] === 'official') $links[] = ['assessment', 'complaints.php?tab=assessment', 'Needs assessment', 'clipboard', $metrics['assessment']];
$records = [['history', 'history.php', 'Resolution history', 'clock', null]];
$records[] = ['notifications', 'notifications.php', 'Notifications', 'bell', null];
if ($actor['role'] === 'personnel') $records[] = ['my-action-plans', 'my-action-plans.php', 'My action plans', 'clipboard', null];
if ($actor['role'] === 'official') {
    if ($actor['is_system_admin']) $records[] = ['admin', 'admin.php', 'Administration', 'shield', null];
    $records[] = ['reports', 'reports.php', 'Reports & insights', 'chart', null];
    $records[] = ['solutions', 'solutions.php', 'Solution library', 'book', null];
    $records[] = ['official-solutions', 'official-solutions.php', 'Official action library', 'book', null];
    $records[] = ['action-plans', 'action-plans.php', 'Weekly action plans', 'clipboard', null];
    if ($actor['is_system_admin']) $records[] = ['users', 'users.php', 'User management', 'users', null];
    $records[] = ['blocked', 'blocked.php', 'Blocked work', 'tool', null];
    if ($actor['is_system_admin']) {
        $records[] = ['settings', 'settings.php', 'Workspace settings', 'building', null];
        $records[] = ['audit', 'audit.php', 'Audit history', 'clock', null];
    }
}
?>
<aside class="sidebar" id="workspace-sidebar" aria-label="Main navigation">
  <a href="index.php" class="brand"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community care, connected</small></div></a>
  <div class="workspace-label">WORKSPACE</div>
  <nav class="nav-list">
    <?php foreach ([$links, $records] as $section => $items): ?>
    <?php if ($section): ?><div class="sidebar-line"></div><div class="workspace-label">RECORDS & LEARNING</div><?php endif ?>
    <?php foreach ($items as [$key, $url, $label, $icon, $count]): ?>
    <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= h($url) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><?= br_icon($icon) ?><span><?= h($label) ?></span><?php if ($count !== null): ?><span class="nav-count"><?= $count ?></span><?php endif ?></a>
    <?php endforeach; endforeach ?>
  </nav>
  <div class="sidebar-bottom"><button type="button" class="nav-link" data-help><?= br_icon('help') ?>How it works</button><div class="workspace-note"><strong><span class="workspace-dot"></span>Saved workspace</strong><p>Reports and their histories are saved, even after you sign out.</p></div><div class="sidebar-footer"><?= br_icon('building') ?>Barangay community services</div></div>
</aside>
