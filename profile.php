<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('profile'));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Manage your photo, account details, and password.');
?>
<section class="panel profile-panel">
  <div class="panel-header profile-panel-header"><div><h2 class="panel-title"><?= h($actor['name']) ?></h2><p class="panel-subtitle"><?= h(br_role($actor['role'])) ?><?= $actor['team'] ? ' · ' . h($actor['team']) : '' ?></p></div><?= br_avatar($actor, 'avatar me profile-header-avatar', true) ?></div>
  <div class="panel-body"><form method="post" action="api.php" data-action="profile">
    <section class="profile-form-section" aria-labelledby="profile-photo-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-photo-heading">Profile photo</h3><p class="form-text">This photo appears beside your name in your workspace.</p></div>
      <div class="profile-photo-picker">
        <div class="profile-photo-preview" data-preview="profile-photo" data-initials="<?= h(br_initials($actor['name'])) ?>" data-has-photo="<?= !empty($actor['profile_photo_path']) ? '1' : '0' ?>"><?= br_avatar($actor, 'avatar me profile-photo-avatar', true) ?></div>
        <div class="profile-photo-controls"><label class="form-label" for="profile-photo">Choose a photo</label><input class="form-control" id="profile-photo" name="photoFile" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help"><p class="form-text" id="profile-photo-help">JPG, PNG, or WebP · Up to 1 MB. A square photo works best.</p>
          <?php if (!empty($actor['profile_photo_path'])): ?><label class="form-check profile-photo-remove"><input class="form-check-input" id="profile-photo-remove" type="checkbox" name="remove_photo" value="1"><span class="form-check-label">Remove my current photo</span></label><?php endif ?>
        </div>
      </div>
    </section>
    <section class="profile-form-section" aria-labelledby="profile-account-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-account-heading">Account details</h3><p class="form-text">Use the name and email address you want associated with your account.</p></div>
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="profile-name">Full name</label><input class="form-control" id="profile-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= h($actor['name']) ?>"></div><div class="col-sm-6"><label class="form-label" for="profile-email">Email address</label><input class="form-control" id="profile-email" name="email" type="email" required maxlength="254" autocomplete="username" value="<?= h($actor['email']) ?>"></div></div>
    </section>
    <section class="profile-form-section" aria-labelledby="profile-password-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-password-heading">Change password</h3><p class="form-text">Leave both fields empty to keep your current password.</p></div>
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="new-password">New password <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="new-password" name="new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div><div class="col-sm-6"><label class="form-label" for="confirm-new-password">Confirm new password</label><input class="form-control" id="confirm-new-password" name="confirm_new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div></div>
      <p class="form-text mt-2">New passwords must contain at least 10 characters.</p>
    </section>
    <section class="profile-confirmation" aria-labelledby="profile-confirm-heading"><div><h3 class="section-title" id="profile-confirm-heading">Confirm changes</h3><p class="form-text">Enter your current password before saving any profile changes.</p></div><label class="form-label" for="current-password">Current password</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required></section>
    <div class="profile-form-actions"><button class="btn btn-primary" type="submit"><?= br_icon('check') ?>Save profile</button></div>
  </form></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
