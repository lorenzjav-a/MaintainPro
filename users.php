<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('users', ['official']));
$allUsers = br_store()->users($actor['id']);
$now = time();
$review = br_query('status', 'all');
if (!in_array($review, ['all', 'deactivated-7'], true)) $review = 'all';
$reviewDue = static fn(array $user): bool => !$user['active'] && $user['deactivated_at'] !== null && $user['deactivated_at'] <= $now - 7 * 86400;
$dueCount = count(array_filter($allUsers, $reviewDue));
$users = $review === 'deactivated-7' ? array_values(array_filter($allUsers, $reviewDue)) : $allUsers;
require __DIR__ . '/includes/layout/header.php';
br_heading('MaintainPro user management', 'Create and manage resident, barangay official, and personnel accounts.', '<a class="btn btn-primary" href="user-create.php">' . br_icon('plus') . 'Create Account</a>');
?>
<div class="account-access-note account-guide"><strong>How accounts are created</strong><p>Residents may self-register from the sign-in page or report as a guest. System administrators can invite resident, barangay official, and personnel accounts here; personnel must have an assigned team.</p><p>Invited accounts remain Pending Setup until the recipient uses the secure email link to create a password.</p></div>
<section class="panel user-register"><div class="panel-header"><div><h2 class="panel-title">Workspace accounts <span class="count-pill"><?= count($users) ?></span></h2><p class="panel-subtitle">Deactivated accounts cannot sign in. Concern histories are retained.</p></div></div>
  <div class="panel-toolbar"><nav class="filter-tabs" aria-label="Account review filters"><a class="filter-tab<?= $review === 'all' ? ' active' : '' ?>" href="users.php"<?= $review === 'all' ? ' aria-current="page"' : '' ?>>All accounts</a><a class="filter-tab<?= $review === 'deactivated-7' ? ' active' : '' ?>" href="<?= h(br_url('users.php', ['status' => 'deactivated-7'])) ?>"<?= $review === 'deactivated-7' ? ' aria-current="page"' : '' ?>>Deactivated 7+ days <span class="count-pill"><?= $dueCount ?></span></a></nav></div>
  <div class="table-responsive"><table class="table record-table mb-0"><caption class="visually-hidden">Saved workspace user accounts</caption><thead><tr><th scope="col">Account</th><th scope="col">Role / team</th><th scope="col">Status</th><th scope="col">Invitation</th><th scope="col">Action</th></tr></thead><tbody>
    <?php foreach ($users as $user): ?>
    <?php $inactiveDays = $user['deactivated_at'] === null ? null : intdiv(max(0, $now - $user['deactivated_at']), 86400); ?>
    <?php $statusLabel=!$user['active']?'Deactivated':($user['pending_setup']?'Pending Setup':(!$user['email_verified']?'Email verification required':($user['must_change_password']?'Password change required':'Active'))); ?>
    <tr><td data-label="Account"><strong><?= h($user['name']) ?><?php if ($user['id'] === $actor['id']): ?> <span class="count-pill">YOU</span><?php endif ?></strong><small><?= h($user['email']) ?></small></td><td data-label="Role / team"><span class="role-pill"><?= h(br_role($user['role'])) ?></span><?php if ($user['is_system_admin']): ?> <span class="count-pill">ADMIN</span><?php endif ?><?php if ($user['team']): ?><small><?= h($user['team']) ?></small><?php endif ?></td><td data-label="Status"><span class="status <?= !$user['active'] ? 'status-rejected' : (($user['pending_setup'] || !$user['email_verified']) ? 'status-submitted' : 'status-verified') ?>"><?= h($statusLabel) ?></span><?php if (!$user['active']): ?><small><?= $user['deactivated_at'] === null ? 'Deactivation date unavailable' : h(br_date($user['deactivated_at'], true)) ?></small><?php if ($inactiveDays !== null): ?><small><?= $inactiveDays ?> full day<?= $inactiveDays === 1 ? '' : 's' ?> inactive</small><?php endif ?><?php endif ?></td><td data-label="Invitation"><?php if($user['invitation_created_at']!==null): ?><strong><?= h(ucfirst((string)$user['invitation_delivery_status'])) ?></strong><small>Created <?= h(br_date($user['invitation_created_at'],true)) ?></small><small>Expires <?= h(br_date($user['invitation_expires_at'],true)) ?></small><?php else: ?><span class="form-text">Not applicable</span><?php endif ?></td><td data-label="Action"><a class="btn btn-light btn-sm" href="<?= h(br_url('user-edit.php', ['id' => $user['id']])) ?>" aria-label="Manage account for <?= h($user['name']) ?>"><?= $user['active'] ? 'Manage' : 'Review / reactivate' ?></a></td></tr>
    <?php endforeach ?>
    <?php if (!$users): ?><tr><td colspan="5"><p class="form-text mb-0">No accounts match this review filter.</p></td></tr><?php endif ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/components/workload-table.php'; require __DIR__ . '/includes/layout/footer.php'; ?>
