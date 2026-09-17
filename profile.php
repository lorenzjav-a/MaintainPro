<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('profile'));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Update your account details and password.');
?>
<section class="panel profile-panel"><div class="panel-header"><div><h2 class="panel-title"><?= h($actor['name']) ?></h2><p class="panel-subtitle"><?= h(br_role($actor['role'])) ?><?= $actor['team'] ? ' · ' . h($actor['team']) : '' ?></p></div><span class="avatar me"><?= h(br_initials($actor['name'])) ?></span></div>
  <div class="panel-body"><form method="post" action="api.php" data-action="profile"><div class="row g-3">
    <div class="col-sm-6"><label class="form-label" for="profile-name">Full name</label><input class="form-control" id="profile-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= h($actor['name']) ?>"></div>
    <div class="col-sm-6"><label class="form-label" for="profile-email">Email address</label><input class="form-control" id="profile-email" name="email" type="email" required maxlength="254" autocomplete="username" value="<?= h($actor['email']) ?>"></div>
    <div class="col-12"><label class="form-label" for="current-password">Current password</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required><p class="form-text">Confirm your current password to save account changes.</p></div>
    <div class="col-sm-6"><label class="form-label" for="new-password">New password <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="new-password" name="new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div>
    <div class="col-sm-6"><label class="form-label" for="confirm-new-password">Confirm new password</label><input class="form-control" id="confirm-new-password" name="confirm_new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div>
  </div><p class="form-text mt-3">Leave the new password fields empty to keep your current password. Use at least 10 characters for a new one.</p><button class="btn btn-primary mt-3" type="submit"><?= br_icon('check') ?>Save profile</button></form></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
