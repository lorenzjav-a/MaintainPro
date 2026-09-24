<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('settings', ['official']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    br_page_error($context, 405, 'Use workspace settings', 'Download a backup using the button in Workspace settings.');
}
if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['br_csrf'], $_POST['csrf'])) {
    br_page_error($context, 403, 'Refresh workspace settings', 'Your session changed. Refresh Workspace settings and download the backup again.');
}
try {
    $backup = br_store()->generateBackup($context['actor']['id']);
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    echo $backup['content'];
} catch (Throwable $error) {
    error_log($error->__toString());
    br_page_error($context, 500, 'Backup unavailable', 'The backup could not be generated. Please try again.');
}
