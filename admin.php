<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('admin', ['official']));

$sections = [
    [
        'title' => 'Concern operations',
        'description' => 'Review reports, assess needs, assign work, monitor progress, and close completed concerns.',
        'links' => [
            ['All concerns', 'complaints.php'],
            ['Needs assessment', 'complaints.php?tab=assessment'],
            ['Blocked work', 'blocked.php'],
            ['Resolution history', 'history.php'],
            ['Report a concern', 'report-concern.php'],
            ['My reported concerns', 'complaints.php?scope=mine'],
        ],
    ],
    [
        'title' => 'Accounts and communication',
        'description' => 'Create accounts, manage access, review notifications, and maintain your official profile.',
        'links' => [
            ['User management', 'users.php'],
            ['Create account', 'user-create.php'],
            ['Notifications', 'notifications.php'],
            ['My profile', 'profile.php'],
        ],
    ],
    [
        'title' => 'Reports and guidance',
        'description' => 'Use concern trends, weekly priorities, and saved guidance to support barangay decisions.',
        'links' => [
            ['Reports and insights', 'reports.php'],
            ['Weekly concern analysis', 'reports.php'],
            ['Solution library', 'solutions.php'],
            ['Official action library', 'official-solutions.php'],
            ['Weekly action plans', 'action-plans.php'],
            ['Official dashboard', 'index.php'],
        ],
    ],
    [
        'title' => 'Workspace controls',
        'description' => 'Configure locations, review activity, and protect the barangay records with a backup.',
        'links' => [
            ['Workspace settings', 'settings.php'],
            ['Database backup', 'settings.php#database-backup'],
            ['Audit history', 'audit.php'],
        ],
    ],
    [
        'title' => 'Public services',
        'description' => 'Open the same public pages used for community reporting and private concern tracking.',
        'links' => [
            ['Public information page', 'landing.php'],
            ['Public report form', 'report-concern.php'],
            ['Track a concern', 'track.php'],
        ],
    ],
];

require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Access every barangay official function from one place.', '<a class="btn btn-primary" href="report-concern.php">' . br_icon('inbox') . 'Report Concern</a>');
?>
<div class="account-access-note account-guide"><strong>Barangay official access</strong><p>Your official account can review every concern, complete every workflow action, manage accounts and workspace settings, view reports, and create backups.</p><p>Private tracking codes and account passwords remain protected because they identify the resident or account holder.</p></div>
<div class="report-grid">
  <?php foreach ($sections as $section): ?>
  <section class="panel">
    <div class="panel-header"><div><h2 class="panel-title"><?= h($section['title']) ?></h2><p class="panel-subtitle"><?= h($section['description']) ?></p></div></div>
    <div class="panel-body d-flex flex-wrap gap-2">
      <?php foreach ($section['links'] as [$label, $url]): ?><a class="btn btn-light" href="<?= h($url) ?>"><?= h($label) ?></a><?php endforeach ?>
    </div>
  </section>
  <?php endforeach ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
