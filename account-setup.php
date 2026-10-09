<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/view.php';
if (isset($_GET['token'])) {
    $token=is_string($_GET['token'])?strtolower(trim($_GET['token'])):'';
    $context=br_store()->inspectInvitation($token);
    if ($context['status']==='valid') $context['token']=$token;
    $_SESSION['br_invitation_context']=$context;
    session_regenerate_id(true);
    $_SESSION['br_csrf']=bin2hex(random_bytes(32));
}
header('Referrer-Policy: no-referrer');
$context=is_array($_SESSION['br_invitation_context'] ?? null)?$_SESSION['br_invitation_context']:['status'=>'invalid'];
$status=$context['status'] ?? 'invalid';
$valid=$status==='valid';
$title=$valid?'Welcome to MaintainPro':match($status) {
    'expired'=>'Invitation Expired','used'=>'Invitation Already Used','revoked'=>'Invitation Replaced','inactive'=>'Account Unavailable',default=>'Invalid Invitation'
};
$message=$valid?'Your account is almost ready. Create a secure password to activate it.':match($status) {
    'expired'=>'This invitation has expired. Please contact your administrator to request a new invitation.',
    'used'=>'This invitation has already been used. You can sign in or reset your password if needed.',
    'revoked'=>'A newer invitation replaced this link. Ask your administrator for the latest invitation email.',
    'inactive'=>'This account is currently deactivated. Contact your system administrator.',
    default=>'This account setup link is not valid. Please request a new invitation.'
};
$role=match($context['role'] ?? '') {'official'=>'Barangay Official','personnel'=>'Barangay Personnel','resident'=>'Resident',default=>''};
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?= h($_SESSION['br_csrf']) ?>"><meta name="theme-color" content="#102b32"><title><?= h($title) ?> &middot; MaintainPro</title><link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml"><script src="assets/js/theme.js?v=<?= filemtime(__DIR__.'/assets/js/theme.js') ?>"></script><script src="assets/js/account-setup.js?v=<?= filemtime(__DIR__.'/assets/js/account-setup.js') ?>" defer></script><link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__.'/assets/css/app.css') ?>"><?php if($valid): ?><script src="assets/vendor/sweetalert2.all.min.js" defer></script><script src="assets/js/auth.js?v=<?= filemtime(__DIR__.'/assets/js/auth.js') ?>" defer></script><?php endif ?></head>
<body class="auth-page"><main class="auth-layout"><section class="auth-story"><a class="brand" href="landing.php"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community concern management</small></div></a><div class="auth-story-content"><span class="auth-kicker">COMMUNITY CARE, CONNECTED</span><h1>Your secure MaintainPro workspace starts here.</h1><p>Set your own password, then sign in normally to access the account prepared for you.</p></div><div class="auth-story-footer"><span class="auth-status-dot"></span>Private, single-use account activation.</div></section><section class="auth-form-side"><?= br_theme_switcher('theme-switcher-auth') ?><div class="auth-card"><span class="eyebrow text-muted">ACCOUNT INVITATION</span><h2><?= h($title) ?></h2><p class="auth-description"><?= h($message) ?></p>
<?php if($valid): ?><div class="case-summary mb-4"><div class="summary-item"><span class="label">Account holder</span><strong><?= h($context['name']) ?></strong></div><div class="summary-item"><span class="label">Account type</span><strong><?= h($role) ?></strong></div><?php if(!empty($context['team'])): ?><div class="summary-item"><span class="label">Assigned team</span><strong><?= h($context['team']) ?></strong></div><?php endif ?><div class="summary-item"><span class="label">Invitation expires</span><strong><?= h(br_date((int)$context['expires_at'],true)) ?></strong></div></div><form id="auth-form" method="post" action="auth.php" data-action="accept_invitation"><div class="mb-3"><label class="form-label" for="account-password">New Password</label><input id="account-password" type="password" name="password" class="form-control" autocomplete="new-password" required minlength="10" maxlength="72" aria-describedby="password-help"><div class="form-check mt-2"><input id="show-password" type="checkbox" class="form-check-input"><label class="form-check-label" for="show-password">Show password</label></div><p id="password-help" class="form-text">Use at least 10 characters and no more than 72 bytes. A longer mix of words, numbers, and symbols is stronger.</p><div class="password-strength" aria-live="polite"><span data-password-strength>Strength: waiting for password</span></div></div><div class="mb-4"><label class="form-label" for="confirm-password">Confirm Password</label><input id="confirm-password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required minlength="10" maxlength="72"></div><button class="btn btn-primary w-100 auth-submit" type="submit">Activate My Account <span aria-hidden="true">&rarr;</span></button></form><?php else: ?><div class="d-grid gap-2"><a class="btn btn-primary" href="login.php">Go to sign in</a><a class="btn btn-light" href="landing.php">Return to MaintainPro</a></div><?php endif ?><p class="auth-footnote">MaintainPro &middot; Community Care, Connected</p></div></section></main></body></html>
