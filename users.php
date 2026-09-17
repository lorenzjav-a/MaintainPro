<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('users', ['official']));
$users = br_store()->users($actor['id']);
require __DIR__ . '/includes/layout/header.php';
br_heading('MaintainPro user management', 'Create official and personnel accounts, assign teams, and manage resident access.', '<a class="btn btn-primary" href="user-create.php">' . br_icon('plus') . 'Create account</a>');
?>
<div class="account-access-note account-guide"><strong>How accounts are created</strong><p>Residents can register from the sign-in page. Authorized officials create barangay official and personnel accounts here. Personnel must have an assigned team.</p><p>New accounts receive a temporary password and must change it before accessing the workspace.</p></div>
<section class="panel user-register"><div class="panel-header"><div><h2 class="panel-title">Workspace accounts <span class="count-pill"><?= count($users) ?></span></h2><p class="panel-subtitle">Deactivated accounts cannot sign in. Complaint histories are retained.</p></div></div>
  <div class="table-responsive"><table class="table mb-0"><caption class="visually-hidden">Saved workspace user accounts</caption><thead><tr><th scope="col">Account</th><th scope="col">Role / team</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    <?php foreach ($users as $user): ?>
    <tr><td><strong><?= h($user['name']) ?><?php if ($user['id'] === $actor['id']): ?> <span class="count-pill">YOU</span><?php endif ?></strong><small><?= h($user['email']) ?></small></td><td><span class="role-pill"><?= h(br_role($user['role'])) ?></span><?php if ($user['team']): ?><small><?= h($user['team']) ?></small><?php endif ?></td><td><span class="status <?= $user['active'] ? 'status-verified' : 'status-rejected' ?>"><?= $user['active'] ? ($user['must_change_password'] ? 'Password change required' : 'Active') : 'Inactive' ?></span></td><td><a class="btn btn-light btn-sm" href="<?= h(br_url('user-edit.php', ['id' => $user['id']])) ?>" aria-label="Manage account for <?= h($user['name']) ?>">Manage</a></td></tr>
    <?php endforeach ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
