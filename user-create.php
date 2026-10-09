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
  <div class="panel-header"><div><div class="eyebrow">ACCOUNT MANAGEMENT</div><h2 class="panel-title" id="created-account-heading" tabindex="-1">Account Invitation Sent</h2></div></div>
  <div class="case-summary created-account-summary">
    <div class="summary-item"><span class="label">Full name</span><strong data-created="name"></strong></div>
    <div class="summary-item"><span class="label">Email address</span><strong data-created="email"></strong></div>
    <div class="summary-item"><span class="label">Account type</span><strong data-created="role"></strong></div>
    <div class="summary-item" id="created-team" hidden><span class="label">Assigned team</span><strong data-created="team"></strong></div>
    <div class="summary-item"><span class="label">Account status</span><strong data-created="status">Pending Setup</strong></div>
  </div>
  <div class="new-form-body"><div class="alert" role="status" data-invitation-result></div><p class="form-text mb-0">No password or setup token is displayed. The recipient creates their own password from the single-use invitation link.</p></div>
  <div class="form-footer justify-content-end"><a class="btn btn-light" href="users.php">Back to User Management</a><a class="btn btn-primary" data-view-created href="users.php">View Account</a></div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
