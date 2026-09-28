<?php
$official = $actor['role'] === 'official';
?>
<nav class="mobile-dock" aria-label="Quick navigation">
  <a href="index.php"<?= $activePage === 'overview' ? ' class="active" aria-current="page"' : '' ?>><?= br_icon('grid') ?><span>Overview</span></a>
  <a href="complaints.php"<?= $activePage === 'complaints' ? ' class="active" aria-current="page"' : '' ?>><?= br_icon('inbox') ?><span><?= $actor['role'] === 'personnel' ? 'Assignments' : 'Concerns' ?></span></a>
  <a class="mobile-primary" href="report-concern.php"><?= br_icon('inbox') ?><span>Report</span></a>
  <a href="history.php"<?= $activePage === 'history' ? ' class="active" aria-current="page"' : '' ?>><?= br_icon('clock') ?><span>History</span></a>
  <button type="button" data-menu aria-controls="workspace-sidebar" aria-expanded="false"><?= br_icon('menu') ?><span>More</span></button>
</nav>
