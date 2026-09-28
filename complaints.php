<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('complaints'));
require __DIR__ . '/includes/layout/header.php';
$description = ($scope ?? '') === 'mine' ? 'Concerns you submitted, kept separate from personnel work assignments.' : ($actor['role'] === 'personnel'
    ? 'Assignments for ' . $actor['team'] . ', ordered by priority. Start work or record your next update.'
    : 'Review concerns, recommend actions, and coordinate the response.');
if (isset($weeklyPeriod)) $description = 'Related weekly concerns · ' . $weeklyPeriod['label'] . ' · ' . br_query('category') . ' / ' . (br_query('type') ?: 'Legacy type');
br_heading($pageTitle, $description, $actor['role'] === 'official' ? br_export() : '');
if ($actor['role'] === 'personnel' && $scope !== 'mine') {
    require __DIR__ . '/includes/components/stats.php';
    require __DIR__ . '/includes/components/banner.php';
}
require __DIR__ . '/includes/components/complaint-table.php';
require __DIR__ . '/includes/layout/footer.php';
