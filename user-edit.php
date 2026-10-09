<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('user-edit', ['official']);
$user = br_store()->user(br_query('id'));
if (!$user) br_page_error($context, 404, 'Account unavailable', 'The requested account was not found.');
extract($context);
$assignedWork=$user['role']==='personnel' ? br_store()->assignedWorkCounts($actor['id'],$user['id']) : ['concerns'=>0,'actionPlans'=>0];
$replacementWorkers=$user['role']==='personnel' ? br_store()->workloads($actor['id']) : [];
$pageTitle = 'Manage ' . $user['name'];
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Account management', '<a class="btn btn-light" href="users.php">Back to user management</a>');
if (br_query('invitation')==='sent'): ?><div class="alert alert-success" role="status">The mail service accepted the replacement invitation.</div><?php elseif(br_query('invitation')==='failed'): ?><div class="alert alert-warning" role="status">The replacement invitation was created, but email delivery failed. Check SMTP configuration and try again.</div><?php endif;
if ($user['pending_setup']): ?>
<section class="panel mb-4"><div class="panel-header"><div><h2 class="panel-title">Pending Setup</h2><p class="panel-subtitle">This account cannot sign in or receive staff work until its invitation is completed.</p></div></div><div class="panel-body"><div class="case-summary"><div class="summary-item"><span class="label">Delivery</span><strong><?= h(ucfirst((string)($user['invitation_delivery_status'] ?? 'pending'))) ?></strong></div><div class="summary-item"><span class="label">Invitation sent</span><strong><?= $user['invitation_sent_at'] ? h(br_date($user['invitation_sent_at'],true)) : 'Not sent' ?></strong></div><div class="summary-item"><span class="label">Expires</span><strong><?= $user['invitation_expires_at'] ? h(br_date($user['invitation_expires_at'],true)) : 'No active invitation' ?></strong></div></div><form class="mt-3" method="post" action="api.php" data-action="resend_invitation" data-id="<?= h($user['id']) ?>"><label class="form-label" for="resend-password">Your current password</label><input class="form-control" id="resend-password" name="current_password" type="password" autocomplete="current-password" required maxlength="72"><p class="form-text">Resending revokes every earlier unused setup link. Active accounts must use Forgot Password instead.</p><button class="btn btn-primary" type="submit">Resend Invitation</button></form></div></section>
<?php endif;
require __DIR__ . '/includes/components/user-form.php';
require __DIR__ . '/includes/layout/footer.php';
