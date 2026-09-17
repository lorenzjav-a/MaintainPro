<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('complaints'));
require __DIR__ . '/includes/layout/header.php';
$description = match ($actor['role']) {'resident' => 'Your reports, recommendations, and progress in one place.', 'personnel' => 'Assignments for ' . $actor['team'] . ', ordered by priority. Start work or record your next update.', default => 'Review concerns, recommend actions, and coordinate the response.'};
br_heading($pageTitle, $description, $actor['role'] === 'resident' ? br_primary($actor) : ($actor['role'] === 'official' ? br_export() : ''));
if ($actor['role'] === 'personnel') {
    require __DIR__ . '/includes/components/stats.php';
    require __DIR__ . '/includes/components/banner.php';
}
require __DIR__ . '/includes/components/complaint-table.php';
require __DIR__ . '/includes/layout/footer.php';
