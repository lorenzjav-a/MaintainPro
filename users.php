<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('users', ['official']));
$users = br_store()->users($actor['id']);
require __DIR__ . '/includes/layout/header.php';
br_heading('MaintainPro user management', 'Create and manage resident, barangay official, and personnel accounts.', '<a class="btn btn-primary" href="user-create.php">' . br_icon('plus') . 'Create account</a>');
?>
<div class="account-access-note account-guide"><strong>How accounts are created</strong><p>Residents may register from the sign-in page, report as a guest, or receive an account created by a system administrator. Administrators can create resident, barangay official, and personnel accounts here. Personnel must have an assigned team.</p><p>Accounts created here receive a temporary password and must change it before accessing the workspace.</p></div>
<section class="panel user-register"><div class="panel-header"><div><h2 class="panel-title">Workspace accounts <span class="count-pill"><?= count($users) ?></span></h2><p class="panel-subtitle">Deactivated accounts cannot sign in. Concern histories are retained.</p></div></div>
  <div class="table-responsive"><table class="table record-table mb-0"><caption class="visually-hidden">Saved workspace user accounts</caption><thead><tr><th scope="col">Account</th><th scope="col">Role / team</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    <?php foreach ($users as $user): ?>
    <tr><td data-label="Account"><strong><?= h($user['name']) ?><?php if ($user['id'] === $actor['id']): ?> <span class="count-pill">YOU</span><?php endif ?></strong><small><?= h($user['email']) ?></small></td><td data-label="Role / team"><span class="role-pill"><?= h(br_role($user['role'])) ?></span><?php if ($user['is_system_admin']): ?> <span class="count-pill">ADMIN</span><?php endif ?><?php if ($user['team']): ?><small><?= h($user['team']) ?></small><?php endif ?></td><td data-label="Status"><span class="status <?= !$user['active'] ? 'status-rejected' : (!$user['email_verified'] ? 'status-submitted' : 'status-verified') ?>"><?= !$user['active'] ? 'Inactive' : (!$user['email_verified'] ? 'Email verification pending' : ($user['must_change_password'] ? 'Password change required' : 'Active')) ?></span></td><td data-label="Action"><a class="btn btn-light btn-sm" href="<?= h(br_url('user-edit.php', ['id' => $user['id']])) ?>" aria-label="Manage account for <?= h($user['name']) ?>">Manage</a></td></tr>
    <?php endforeach ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/components/workload-table.php'; require __DIR__ . '/includes/layout/footer.php'; ?>
