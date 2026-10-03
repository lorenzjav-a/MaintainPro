<?php
$activePage = match ($page) {'complaint' => 'complaints', 'user-create', 'user-edit' => 'users', default => $page};
$links = [['overview', 'index.php', 'Dashboard', 'grid', null], ['complaints', 'complaints.php', $actor['role']==='personnel'?'My Work':'Concerns', 'inbox', $metrics['total']]];
$links[] = ['new-complaint', 'report-concern.php', 'Report Concern', 'plus', null];
if ($page === 'complaints' && ($scope ?? '') === 'mine' && $actor['role'] !== 'resident') $activePage = 'my-reports';
$links[] = ['notifications', 'notifications.php', 'Notifications', 'bell', null];
if ($actor['role'] !== 'resident') $links[] = ['messages', 'messages.php', 'Messages', 'inbox', $messageCounts['staff'] ?? 0];
$links[] = ['user-guide', 'user-guide.php', 'User Guide', 'help', null];
$records = [['history', 'history.php', 'Concern history', 'clock', null]];
if ($actor['role'] !== 'resident') $records[] = ['my-reports', 'complaints.php?scope=mine', 'My reported concerns', 'clipboard', null];
if ($actor['role'] === 'personnel') $records[] = ['my-action-plans', 'my-action-plans.php', 'My action plans', 'clipboard', null];
if ($actor['role'] === 'official') {
    $records[] = ['reports', 'reports.php', 'Reports & insights', 'chart', null];
    $records[] = ['solutions', 'solutions.php', 'Solution library', 'book', null];
    $records[] = ['official-solutions', 'official-solutions.php', 'Official action library', 'book', null];
    $records[] = ['action-plans', 'action-plans.php', 'Weekly action plans', 'clipboard', null];
    $records[] = ['blocked', 'blocked.php', 'Blocked work', 'tool', null];
}
$administration=$actor['is_system_admin'] ? [
    ['admin','admin.php','Administration','shield',null],['users','users.php','User management','users',null],
    ['settings','settings.php','Workspace settings','building',null],['audit','audit.php','Audit history','clock',null],
    ['backup','settings.php#database-backup','Database backup','download',null],
] : [];
?>
<aside class="sidebar" id="workspace-sidebar" aria-label="Main navigation">
  <a href="index.php" class="brand"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community care, connected</small></div></a>
  <div class="workspace-label">WORKSPACE</div>
  <nav class="nav-list">
    <?php foreach ($links as [$key, $url, $label, $icon, $count]): ?>
    <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= h($url) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><?= br_icon($icon) ?><span><?= h($label) ?></span><?php if ($count !== null): ?><span class="nav-count"><?= $count ?></span><?php endif ?></a>
    <?php endforeach ?>
    <?php if ($records): ?><details class="sidebar-tools"<?= in_array($activePage,array_column($records,0),true)?' open':'' ?>><summary><?= br_icon('tool') ?><span>More Tools</span></summary><div><?php foreach ($records as [$key,$url,$label,$icon,$count]): ?><a class="nav-link<?= $activePage===$key?' active':'' ?>" href="<?= h($url) ?>"><?= br_icon($icon) ?><span><?= h($label) ?></span></a><?php endforeach ?></div></details><?php endif ?>
    <?php if ($administration): ?><details class="sidebar-tools"<?= in_array($activePage,array_column($administration,0),true)?' open':'' ?>><summary><?= br_icon('shield') ?><span>Administration</span></summary><div><?php foreach ($administration as [$key,$url,$label,$icon,$count]): ?><a class="nav-link<?= $activePage===$key?' active':'' ?>" href="<?= h($url) ?>"><?= br_icon($icon) ?><span><?= h($label) ?></span></a><?php endforeach ?></div></details><?php endif ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="sidebar-footer"><?= br_icon('building') ?><?= h(br_role($actor['role'])) ?><?= $actor['team']?' · '.h($actor['team']):'' ?></div>
    <button class="btn btn-danger sidebar-signout" type="button" data-logout>Sign Out</button>
  </div>
</aside>
