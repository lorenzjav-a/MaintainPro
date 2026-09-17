<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('user-edit', ['official']);
$user = br_store()->user(br_query('id'));
if (!$user) br_page_error($context, 404, 'Account unavailable', 'The requested account was not found.');
extract($context);
$pageTitle = 'Manage ' . $user['name'];
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Account management', '<a class="btn btn-light" href="users.php">Back to user management</a>');
require __DIR__ . '/includes/components/user-form.php';
require __DIR__ . '/includes/layout/footer.php';
