<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('history'));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Resolution attempts, official closure decisions, and referrals — with their full timelines.', $actor['role'] === 'official' ? br_export() : '');
require __DIR__ . '/includes/components/complaint-table.php';
require __DIR__ . '/includes/layout/footer.php';
