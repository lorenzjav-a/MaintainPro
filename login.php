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
$recovery = !$setup && !$changingPassword && in_array($view, ['forgot', 'verify', 'reset'], true);
if ($recovery && (($view === 'verify' && (!isset($_SESSION['br_reset_challenge']) || ($_SESSION['br_reset_until'] ?? 0) <= time()))
    || ($view === 'reset' && (!isset($_SESSION['br_reset_token']) || ($_SESSION['br_reset_verified_until'] ?? 0) <= time())))) {
    header('Location: login.php?view=forgot');
    exit;
}
$register = !$setup && !$changingPassword && $view === 'register';
$action = $setup ? 'setup' : ($register ? 'register' : 'login');
$title = $setup ? 'Set up your barangay workspace' : ($register ? 'Create your resident account' : 'Sign in to MaintainPro');
$description = $setup ? 'Create the first official account to manage complaints and personnel.' : ($register ? 'Report concerns, follow the response, and verify the result.' : 'Sign in to follow concerns and keep community action moving.');
if ($recovery) {
    [$action, $title, $description] = match ($view) {
        'forgot' => ['request_reset', 'Forgot your password?', 'Enter the email address you used for your account. We will email a code to verify it.'],
        'verify' => ['verify_reset', 'Check your email', 'If an active account matches that email address, a six-digit code will arrive. Check your inbox and spam folder.'],
        'reset' => ['reset_password', 'Choose a new password', 'Your email code has been verified. Enter and confirm your new password.'],
    };
}
if ($changingPassword) {
    $action = 'change_password';
    $title = 'Create your own password';
    $description = 'You signed in with a temporary password. Choose a new password before opening your MaintainPro workspace.';
}
$showPassword = !$recovery || $view === 'reset';
$confirmPassword = $setup || $register || $changingPassword || ($recovery && $view === 'reset');
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
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
  <script src="assets/vendor/sweetalert2.all.min.js" defer></script>
  <script src="auth-ui.js" defer></script>
</head>
<body class="auth-page">
  <main class="auth-layout">
    <section class="auth-story">
      <a class="brand" href="login.php"><img src="favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community complaint management</small></div></a>
      <div class="auth-story-content">
        <span class="auth-kicker">A CONNECTED BARANGAY</span>
        <h1>A clear path from concern to resolution.</h1>
        <p>One place for residents, barangay officials, and personnel to work together.</p>
        <ol class="auth-journey">
          <li><span>01</span><div><strong>Report & suggest</strong><p>Share the concern and the solution you have in mind.</p></div></li>
          <li><span>02</span><div><strong>Review & take action</strong><p>The barangay recommends the next step and assigns the right team.</p></div></li>
          <li><span>03</span><div><strong>Resolve & verify</strong><p>Record the work, confirm the result, and learn from the outcome.</p></div></li>
        </ol>
      </div>
      <div class="auth-story-footer"><span class="auth-status-dot"></span>Community services, with a complete record.</div>
    </section>
    <section class="auth-form-side">
      <div class="auth-card">
        <span class="eyebrow text-muted"><?= $setup ? 'FIRST-TIME SETUP' : ($recovery ? 'PASSWORD RECOVERY' : 'SAVED WORKSPACE') ?></span>
        <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="auth-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($resetDone): ?><div class="alert alert-success" role="status">Your password was reset. Sign in with your new password.</div><?php endif ?>
        <?php if ($recovery): ?><p class="form-text">Step <?= ['forgot' => 1, 'verify' => 2, 'reset' => 3][$view] ?> of 3 · Email → Verify code → New password</p><?php endif ?>
        <?php if (!$setup && !$recovery && !$changingPassword): ?>
        <nav class="auth-tabs" aria-label="Account access">
          <a class="<?= !$register ? 'active' : '' ?>" href="login.php" <?= !$register ? 'aria-current="page"' : '' ?>>Sign in</a>
          <a class="<?= $register ? 'active' : '' ?>" href="login.php?view=register" <?= $register ? 'aria-current="page"' : '' ?>>Register as resident</a>
        </nav>
        <?php endif ?>
        <form id="auth-form" data-action="<?= $action ?>">
          <?php if ($setup || $register): ?>
          <div class="mb-3"><label class="form-label" for="account-name">Full name</label><input id="account-name" name="name" class="form-control" autocomplete="name" required minlength="2" maxlength="100" placeholder="Your full name"></div>
          <?php endif ?>
          <?php if ((!$recovery || $view === 'forgot') && !$changingPassword): ?>
          <div class="mb-3"><label class="form-label" for="account-email">Email address</label><input id="account-email" type="email" name="email" class="form-control" autocomplete="username" required maxlength="254" placeholder="you@example.com"></div>
          <?php endif ?>
          <?php if ($recovery && $view === 'verify'): ?>
          <div class="mb-3"><label class="form-label" for="reset-code">Email verification code</label><input id="reset-code" name="code" class="form-control form-control-lg text-center" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="reset-code-help" placeholder="000000"><p id="reset-code-help" class="form-text">Enter the latest six-digit code. It expires in 10 minutes and allows up to five attempts. Keep using this browser.</p></div>
          <?php endif ?>
          <?php if ($showPassword): ?>
          <?php if ($changingPassword): ?><div class="mb-3"><label class="form-label" for="temporary-password">Current temporary password</label><input id="temporary-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required maxlength="72"></div><?php endif ?>
          <div class="mb-3"><label class="form-label" for="account-password">Password</label><input id="account-password" type="password" name="password" class="form-control" autocomplete="<?= $action === 'login' ? 'current-password' : 'new-password' ?>" required <?= $action !== 'login' ? 'minlength="10" maxlength="72" aria-describedby="password-help"' : '' ?>><div class="form-check mt-2"><input id="show-password" type="checkbox" class="form-check-input"><label class="form-check-label" for="show-password">Show password</label></div><?php if ($action !== 'login'): ?><p id="password-help" class="form-text">Use at least 10 characters. Passwords are stored as secure hashes.</p><?php endif ?></div>
          <?php endif ?>
          <?php if ($confirmPassword): ?>
          <div class="mb-4"><label class="form-label" for="confirm-password">Confirm password</label><input id="confirm-password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required minlength="10" maxlength="72"></div>
          <?php endif ?>
          <button class="btn btn-primary w-100 auth-submit" type="submit"><?= $changingPassword ? 'Save password and continue' : ($recovery ? ['forgot' => 'Send verification code', 'verify' => 'Verify code', 'reset' => 'Reset password'][$view] : ($setup ? 'Create official account' : ($register ? 'Create resident account' : 'Sign in to workspace'))) ?><span aria-hidden="true">→</span></button>
        </form>
        <?php if ($changingPassword): ?><p class="mt-3 text-center"><button id="account-signout" type="button" class="btn btn-light">Sign out</button></p><?php endif ?>
        <?php if (!$setup && !$recovery && !$changingPassword): ?>
        <div class="auth-support">
          <?php if (!$register): ?><p class="auth-register-prompt">Don't have a resident account? <a href="login.php?view=register">Register here</a>.</p><?php endif ?>
          <p class="auth-account-note">Barangay official and personnel accounts are issued by authorized barangay officials.</p>
          <?php if (!$register): ?><p class="auth-recovery-link"><a href="login.php?view=forgot">Forgot password?</a></p><?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($recovery && $view === 'verify'): ?><div class="mt-3 text-center"><button id="resend-code" type="button" class="btn btn-light w-100">Send a new code</button><p class="form-text mt-2">Wait 60 seconds between requests. A new code replaces the previous one.</p><a href="login.php?view=forgot">Use a different email address</a></div><?php endif ?>
        <?php if ($recovery): ?><p class="mt-3 text-center"><a href="login.php">Back to sign in</a></p><?php endif ?>
        <p class="auth-footnote">MaintainPro · Community complaint management</p>
      </div>
    </section>
  </main>
</body>
</html>
