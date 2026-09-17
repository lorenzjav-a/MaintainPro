<?php $role = $user['role'] ?? 'personnel'; $ownAccount = $user && $user['id'] === $actor['id']; ?>
<section class="panel form-page" id="account-form-panel">
  <form method="post" action="api.php" data-action="<?= $user ? 'update_user' : 'create_user' ?>"<?= $user ? ' data-id="' . h($user['id']) . '"' : '' ?>>
    <div class="new-form-body"><div class="row g-3">
      <?php if ($user): ?>
      <div class="col-12"><p class="info-callout mb-0"><?= h($user['email']) ?><br><?= $ownAccount ? 'You cannot deactivate or remove official access from your own account.' : 'Changes take effect on the account’s next request.' ?></p></div>
      <?php else: ?>
      <div class="col-sm-6"><label class="form-label" for="user-name">Full name</label><input class="form-control" id="user-name" name="name" required minlength="2" maxlength="100" autocomplete="off"></div>
      <div class="col-sm-6"><label class="form-label" for="user-email">Email address</label><input class="form-control" id="user-email" name="email" type="email" required maxlength="254" autocomplete="off"></div>
      <div class="col-12"><p class="info-callout mb-0">MaintainPro generates a temporary password. You will see it once after creating the account. The user must choose their own password at first sign-in.</p></div>
      <?php endif ?>
      <div class="col-sm-6"><label class="form-label" for="user-role">Account type</label><select class="form-select" id="user-role" name="role" required><?php foreach ($ownAccount ? ['official'] : ['official', 'personnel', 'resident'] as $option): ?><option value="<?= $option ?>"<?= $option === $role ? ' selected' : '' ?>><?= h(br_role($option)) ?></option><?php endforeach ?></select></div>
      <div class="col-sm-6" id="user-team-wrap"<?= $role !== 'personnel' ? ' hidden' : '' ?>><label class="form-label" for="user-team">Assigned team</label><select class="form-select" id="user-team" name="team"<?= $role === 'personnel' ? ' required' : '' ?>><?php br_options(ComplaintWorkflow::TEAMS, $user['team'] ?? '', 'Choose a team'); ?></select></div>
      <?php if ($user): ?><div class="col-sm-6"><label class="form-label" for="user-active">Account status</label><select class="form-select" id="user-active" name="active"><option value="1"<?= $user['active'] ? ' selected' : '' ?>>Active</option><?php if (!$ownAccount): ?><option value="0"<?= !$user['active'] ? ' selected' : '' ?>>Inactive</option><?php endif ?></select></div><?php endif ?>
    </div></div>
    <div class="form-footer"><small><?= $user ? 'Existing complaint records are retained.' : 'Public registration creates resident accounts only.' ?></small><button class="btn btn-primary" type="submit"><?= $user ? 'Save access settings' : 'Create account' ?></button></div>
  </form>
</section>
