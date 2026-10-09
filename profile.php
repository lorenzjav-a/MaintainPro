<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('profile'));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Manage your photo, account details, and password.');
?>
<?php if (br_query('email-verification')==='complete'): ?><div class="alert alert-success" role="status">Your new email address is verified and active. Other signed-in sessions were revoked.</div><?php endif ?>
<?php if (isset($_SESSION['br_email_change_challenge'])): ?><section class="panel profile-panel mb-4"><div class="panel-header"><div><h2 class="panel-title">Verify your new email</h2><p class="panel-subtitle">Enter the six-digit code sent to the new address. Your current email remains active until this succeeds.</p></div></div><div class="panel-body"><form method="post" action="api.php" data-action="verify_email_change"><label class="form-label" for="email-change-code">Verification code</label><input class="form-control" id="email-change-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required><p class="form-text">The code expires in 10 minutes and allows five attempts.</p><button class="btn btn-primary" type="submit">Verify new email</button></form></div></section><?php endif ?>
<section class="panel profile-panel">
  <div class="panel-header profile-panel-header"><div><h2 class="panel-title"><?= h($actor['name']) ?></h2><p class="panel-subtitle"><?= h(br_role($actor['role'])) ?><?= $actor['team'] ? ' · ' . h($actor['team']) : '' ?></p></div><?= br_avatar($actor, 'avatar me profile-header-avatar', true) ?></div>
  <div class="panel-body"><form method="post" action="api.php" data-action="profile">
    <section class="profile-form-section" aria-labelledby="profile-photo-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-photo-heading">Profile photo</h3><p class="form-text">This photo appears beside your name in your workspace.</p></div>
      <div class="profile-photo-picker">
        <div class="profile-photo-preview" data-preview="profile-photo" data-initials="<?= h(br_initials($actor['name'])) ?>" data-has-photo="<?= !empty($actor['profile_photo_path']) ? '1' : '0' ?>"><?= br_avatar($actor, 'avatar me profile-photo-avatar', true) ?></div>
        <div class="profile-photo-controls"><label class="form-label" for="profile-photo">Update your photo</label><div class="profile-photo-actions"><button class="btn btn-primary" type="button" data-choose-profile-photo><?= br_icon('camera') ?><span>Choose photo</span></button>
          <?php if (!empty($actor['profile_photo_path'])): ?><button class="btn btn-danger profile-photo-remove-button" type="button" data-remove-profile-photo><?= br_icon('trash') ?><span>Remove photo</span></button><?php endif ?></div>
          <input class="visually-hidden" id="profile-photo" name="photoFile" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help profile-photo-status"><input id="profile-photo-remove" type="hidden" name="remove_photo" value="0"><p class="form-text" id="profile-photo-help">JPG, PNG, or WebP · Up to 5 MB. A square photo works best.</p><p class="form-text profile-photo-status" id="profile-photo-status" role="status" aria-live="polite"></p>
        </div>
      </div>
    </section>
    <section class="profile-form-section" aria-labelledby="profile-account-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-account-heading">Account details</h3><p class="form-text">Changing your email sends a verification code to the new address. Your current email remains active until verification succeeds.</p></div>
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="profile-name">Full name</label><input class="form-control" id="profile-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= h($actor['name']) ?>"></div><div class="col-sm-6"><label class="form-label" for="profile-email">Email address</label><input class="form-control" id="profile-email" name="email" type="email" required maxlength="254" autocomplete="username" value="<?= h($actor['email']) ?>"></div></div>
    </section>
    <section class="profile-form-section" aria-labelledby="profile-password-heading">
      <div class="profile-section-heading"><h3 class="section-title" id="profile-password-heading">Change password</h3><p class="form-text">Leave both fields empty to keep your current password.</p></div>
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="new-password">New password <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="new-password" name="new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div><div class="col-sm-6"><label class="form-label" for="confirm-new-password">Confirm new password</label><input class="form-control" id="confirm-new-password" name="confirm_new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div></div>
      <p class="form-text mt-2">New passwords must contain at least 10 characters.</p>
    </section>
    <section class="profile-confirmation" aria-labelledby="profile-confirm-heading"><div><h3 class="section-title" id="profile-confirm-heading">Confirm changes</h3><p class="form-text">Enter your current password before saving any profile changes.</p></div><label class="form-label" for="current-password">Current password</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required></section>
    <div class="profile-form-actions"><button class="btn btn-primary" type="submit"><?= br_icon('save') ?>Save profile</button></div>
  </form></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
