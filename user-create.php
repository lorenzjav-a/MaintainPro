<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('user-create', ['official']));
$user = null;
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Account management', '<a class="btn btn-light" href="users.php">Back to user management</a>');
require __DIR__ . '/includes/components/user-form.php';
?>
<section class="panel form-page" id="created-account" aria-labelledby="created-account-heading" hidden>
  <div class="panel-header"><div><div class="eyebrow">ACCOUNT MANAGEMENT</div><h2 class="panel-title" id="created-account-heading" tabindex="-1">Account created</h2></div></div>
  <div class="case-summary created-account-summary">
    <div class="summary-item"><span class="label">Full name</span><strong data-created="name"></strong></div>
    <div class="summary-item"><span class="label">Email address</span><strong data-created="email"></strong></div>
    <div class="summary-item"><span class="label">Account type</span><strong data-created="role"></strong></div>
    <div class="summary-item" id="created-team" hidden><span class="label">Assigned team</span><strong data-created="team"></strong></div>
  </div>
  <div class="new-form-body"><label class="form-label" for="created-password">Temporary password <span class="text-muted fw-normal">(shown once)</span></label><input id="created-password" class="form-control" type="text" readonly autocomplete="off" aria-describedby="created-password-help"><p id="created-password-help" class="form-text mt-3 mb-0">Copy this password and share it privately with the account holder before leaving or refreshing this page. They must replace it at first sign-in. Account invitations are not emailed.</p></div>
  <div class="form-footer justify-content-end"><a class="btn btn-primary" href="users.php">I have saved the account details</a></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
