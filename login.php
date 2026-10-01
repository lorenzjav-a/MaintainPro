<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$signedIn = br_actor();
$changingPassword = $signedIn && $signedIn['must_change_password'];
if ($signedIn && !$changingPassword) {
    header('Location: index.php');
    exit;
}
$setup = br_store()->needsSetup();
$view = $_GET['view'] ?? '';
$registrationVerification = !$setup && !$changingPassword && $view === 'verify-registration';
if ($registrationVerification && !isset($_SESSION['br_registration_challenge'])) {
    header('Location: login.php?view=register');
    exit;
}
$recovery = !$setup && !$changingPassword && in_array($view, ['forgot', 'verify', 'reset'], true);
if ($recovery && (($view === 'verify' && !isset($_SESSION['br_reset_challenge']))
    || ($view === 'reset' && (!isset($_SESSION['br_reset_token']) || ($_SESSION['br_reset_verified_until'] ?? 0) <= time())))) {
    header('Location: login.php?view=forgot');
    exit;
}
$registering = !$setup && !$changingPassword && $view === 'register';
$action = $setup ? 'setup' : ($registering ? 'register' : 'login');
$title = $setup ? 'Set up your barangay workspace' : ($registering ? 'Create your resident account' : 'Sign in to MaintainPro');
$description = $setup ? 'Create the first official account to manage concerns and personnel.' : ($registering ? 'Report concerns and follow their progress from your own account.' : 'Residents, barangay officials and personnel use their own accounts.');
if ($recovery) {
    [$action, $title, $description] = match ($view) {
        'forgot' => ['request_reset', 'Forgot your password?', 'Enter the email address you used for your account. We will email a code to verify it.'],
        'verify' => ['verify_reset', 'Verify your email', 'If an active MaintainPro account matches that email address, a six-digit verification code has been sent. Check your inbox and spam folder.'],
        'reset' => ['reset_password', 'Create new password', 'Your email code has been verified. Enter and confirm your new password.'],
    };
}
if ($registrationVerification) {
    $action='verify_registration';
    $title='Verify your email';
    $description='If a new resident account was created for that address, a six-digit code was sent. Check your inbox and spam folder.';
}
if ($changingPassword) {
    $action = 'change_password';
    $title = 'Create your own password';
    $description = 'You signed in with a temporary password. Choose a new password before opening your MaintainPro workspace.';
}
$showPassword = (!$recovery && !$registrationVerification) || $view === 'reset';
$confirmPassword = $setup || $registering || $changingPassword || ($recovery && $view === 'reset');
$resetDone = !$recovery && !empty($_SESSION['br_password_reset_done']);
unset($_SESSION['br_password_reset_done']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['br_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <meta name="theme-color" content="#102b32">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · MaintainPro</title>
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
  <script src="assets/vendor/sweetalert2.all.min.js" defer></script>
  <script src="assets/js/auth.js?v=<?= filemtime(__DIR__ . '/assets/js/auth.js') ?>" defer></script>
</head>
<body class="auth-page">
  <main class="auth-layout">
    <section class="auth-story">
      <a class="brand" href="landing.php"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community concern management</small></div></a>
      <div class="auth-story-content">
        <span class="auth-kicker">A CONNECTED BARANGAY</span>
        <h1>A clear path from concern to resolution.</h1>
        <p>Everyone can report concerns while barangay officials and personnel coordinate the response.</p>
        <ol class="auth-journey">
          <li><span>01</span><div><strong>Report & receive guidance</strong><p>Choose the concern and key points. Temporary safety guidance is provided.</p></div></li>
          <li><span>02</span><div><strong>Review & take action</strong><p>The barangay recommends the next step and assigns the right team.</p></div></li>
          <li><span>03</span><div><strong>Resolve & review</strong><p>Personnel record evidence; officials review and close the concern.</p></div></li>
        </ol>
      </div>
      <div class="auth-story-footer"><span class="auth-status-dot"></span>Community services, with a complete record.</div>
    </section>
    <section class="auth-form-side">
      <div class="auth-card">
        <span class="eyebrow text-muted"><?= $setup ? 'FIRST-TIME SETUP' : ($recovery ? 'PASSWORD RECOVERY' : ($registrationVerification ? 'RESIDENT VERIFICATION' : 'SAVED WORKSPACE')) ?></span>
        <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="auth-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($resetDone): ?><div class="alert alert-success" role="status">Your password was reset. Sign in with your new password.</div><?php endif ?>
        <?php if ($recovery && $view === 'verify' && ($_SESSION['br_reset_until'] ?? 0) <= time()): ?><div class="alert alert-warning" role="status">This verification code has expired. Please request a new code.</div><?php endif ?>
        <?php if ($recovery): ?><p class="form-text">Step <?= ['forgot' => 1, 'verify' => 2, 'reset' => 3][$view] ?> of 3 · Email → Verify code → New password</p><?php endif ?>
        <?php if (!$setup && !$recovery && !$registrationVerification && !$changingPassword): ?>
        <nav class="auth-tabs" aria-label="Account access"><a<?= !$registering ? ' class="active" aria-current="page"' : '' ?> href="login.php">Sign in</a><a<?= $registering ? ' class="active" aria-current="page"' : '' ?> href="login.php?view=register">Resident registration</a></nav>
        <?php endif ?>
        <noscript><p class="info-callout">Enable JavaScript to sign in or manage your account.</p></noscript>
        <form id="auth-form" method="post" action="auth.php" data-action="<?= $action ?>">
          <?php if ($setup): ?><label class="form-label">Installation setup key<input type="password" class="form-control" name="setup_key" required minlength="32" maxlength="256" autocomplete="off"></label><p class="form-text">Enter the installation key configured by the server administrator. Setup closes permanently after the first official account is created.</p><?php endif ?>
          <?php if ($setup || $registering): ?>
          <div class="mb-3"><label class="form-label" for="account-name">Full name</label><input id="account-name" name="name" class="form-control" autocomplete="name" required minlength="2" maxlength="100" placeholder="Your full name"></div>
          <?php endif ?>
          <?php if ((!$recovery || $view === 'forgot') && !$registrationVerification && !$changingPassword): ?>
          <div class="mb-3"><label class="form-label" for="account-email">Email address</label><input id="account-email" type="email" name="email" class="form-control" autocomplete="username" required maxlength="254" placeholder="you@example.com"></div>
          <?php endif ?>
          <?php if ($recovery && $view === 'verify'): ?>
          <div class="mb-3"><label class="form-label" for="reset-code">6-digit OTP</label><input id="reset-code" name="code" class="form-control form-control-lg text-center" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="reset-code-help" placeholder="000000"><p id="reset-code-help" class="form-text">Enter the latest six-digit code. It expires in 10 minutes and allows up to five attempts. Keep using this browser.</p></div>
          <?php endif ?>
          <?php if ($registrationVerification): ?>
          <div class="mb-3"><label class="form-label" for="registration-code">Email verification code</label><input id="registration-code" name="code" class="form-control form-control-lg text-center" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="registration-code-help" placeholder="000000"><p id="registration-code-help" class="form-text">The code expires in 10 minutes and allows five attempts. Keep using this browser.</p></div>
          <?php endif ?>
          <?php if ($showPassword): ?>
          <?php if ($changingPassword): ?><div class="mb-3"><label class="form-label" for="temporary-password">Current temporary password</label><input id="temporary-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required maxlength="72"></div><?php endif ?>
          <div class="mb-3"><label class="form-label" for="account-password"><?= $recovery && $view === 'reset' ? 'New password' : 'Password' ?></label><input id="account-password" type="password" name="password" class="form-control" autocomplete="<?= $action === 'login' ? 'current-password' : 'new-password' ?>" required <?= $action !== 'login' ? 'minlength="10" maxlength="72" aria-describedby="password-help"' : '' ?>><div class="form-check mt-2"><input id="show-password" type="checkbox" class="form-check-input"><label class="form-check-label" for="show-password">Show password</label></div><?php if ($action !== 'login'): ?><p id="password-help" class="form-text">Use at least 10 characters. Passwords are stored as secure hashes.</p><?php endif ?></div>
          <?php endif ?>
          <?php if ($confirmPassword): ?>
          <div class="mb-4"><label class="form-label" for="confirm-password">Confirm password</label><input id="confirm-password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required minlength="10" maxlength="72"></div>
          <?php endif ?>
          <button class="btn btn-primary w-100 auth-submit" type="submit"><?= $changingPassword ? 'Save password and continue' : ($registrationVerification ? 'Verify email' : ($recovery ? ['forgot' => 'Send OTP', 'verify' => 'Verify code', 'reset' => 'Reset password'][$view] : ($setup ? 'Create official account' : ($registering ? 'Create resident account' : 'Sign in to workspace')))) ?><span aria-hidden="true">→</span></button>
        </form>
        <?php if ($changingPassword): ?><p class="mt-3 text-center"><button id="account-signout" type="button" class="btn btn-light">Sign out</button></p><?php endif ?>
        <?php if (!$setup && !$recovery && !$registrationVerification && !$changingPassword): ?>
        <div class="auth-support">
          <p class="auth-register-prompt">Residents can <a href="report-concern.php">report anonymously</a>. No account needed.</p>
          <p class="auth-account-note">Barangay official and personnel accounts are issued by authorized barangay officials.</p>
          <p class="auth-recovery-link"><a href="login.php?view=forgot">Forgot password?</a></p>
        </div>
        <?php endif ?>
        <?php if ($recovery && $view === 'verify'): $resendWait = max(0, 60 - (time() - (int)($_SESSION['br_reset_sent_at'] ?? 0))); ?><div class="mt-3 text-center"><button id="resend-code" type="button" class="btn btn-light w-100" data-seconds="<?= $resendWait ?>"<?= $resendWait > 0 ? ' disabled' : '' ?>><?= $resendWait > 0 ? 'Resend code in ' . sprintf('%02d:%02d', intdiv($resendWait, 60), $resendWait % 60) : 'Resend Code' ?></button><p class="form-text mt-2">A new code replaces the previous one.</p><a href="login.php?view=forgot">Use a different email address</a></div><?php endif ?>
        <?php if ($registrationVerification): ?><div class="mt-3 text-center"><button id="resend-registration" type="button" class="btn btn-light w-100">Send a new code</button><p class="form-text mt-2">Wait 60 seconds between requests. If your registration did not complete, return to registration.</p><a href="login.php?view=register">Return to registration</a></div><?php endif ?>
        <?php if ($recovery): ?><p class="mt-3 text-center"><a href="login.php">Back to sign in</a></p><?php endif ?>
        <p class="auth-footnote">MaintainPro · Community concern management</p>
      </div>
    </section>
  </main>
</body>
</html>
