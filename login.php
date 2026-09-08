<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (!br_is_demo() && br_actor()) {
    header('Location: index.php');
    exit;
}
$setup = br_store()->needsSetup();
$register = !$setup && ($_GET['view'] ?? '') === 'register';
$action = $setup ? 'setup' : ($register ? 'register' : 'login');
$title = $setup ? 'Set up your barangay workspace' : ($register ? 'Create your resident account' : 'Welcome back');
$description = $setup ? 'Create the first official account to manage complaints and personnel.' : ($register ? 'Report concerns, follow the response, and verify the result.' : 'Sign in to follow concerns and keep community action moving.');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['br_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <meta name="theme-color" content="#102b32">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · BarangayResolve</title>
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="styles.css">
  <script src="assets/vendor/sweetalert2.all.min.js" defer></script>
  <script src="auth-ui.js" defer></script>
</head>
<body class="auth-page">
  <main class="auth-layout">
    <section class="auth-story">
      <a class="brand" href="login.php"><img src="favicon.svg" alt=""><div><div class="brand-title">Barangay<span>Resolve</span></div><small>Community complaint management</small></div></a>
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
        <span class="eyebrow text-muted"><?= $setup ? 'FIRST-TIME SETUP' : 'SAVED WORKSPACE' ?></span>
        <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="auth-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!$setup): ?>
        <nav class="auth-tabs" aria-label="Account access">
          <a class="<?= !$register ? 'active' : '' ?>" href="login.php" <?= !$register ? 'aria-current="page"' : '' ?>>Sign in</a>
          <a class="<?= $register ? 'active' : '' ?>" href="login.php?view=register" <?= $register ? 'aria-current="page"' : '' ?>>Resident registration</a>
        </nav>
        <?php endif ?>
        <form id="auth-form" data-action="<?= $action ?>">
          <?php if ($setup || $register): ?>
          <div class="mb-3"><label class="form-label" for="account-name">Full name</label><input id="account-name" name="name" class="form-control" autocomplete="name" required minlength="2" maxlength="100" placeholder="Your full name"></div>
          <?php endif ?>
          <div class="mb-3"><label class="form-label" for="account-email">Email address</label><input id="account-email" type="email" name="email" class="form-control" autocomplete="username" required maxlength="254" placeholder="you@example.com"></div>
          <div class="mb-3"><label class="form-label" for="account-password">Password</label><input id="account-password" type="password" name="password" class="form-control" autocomplete="<?= $action === 'login' ? 'current-password' : 'new-password' ?>" required <?= $action !== 'login' ? 'minlength="10" maxlength="72" aria-describedby="password-help"' : '' ?>><div class="form-check mt-2"><input id="show-password" type="checkbox" class="form-check-input"><label class="form-check-label" for="show-password">Show password</label></div><?php if ($action !== 'login'): ?><p id="password-help" class="form-text">Use at least 10 characters. Passwords are stored as secure hashes.</p><?php endif ?></div>
          <?php if ($setup || $register): ?>
          <div class="mb-4"><label class="form-label" for="confirm-password">Confirm password</label><input id="confirm-password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required minlength="10" maxlength="72"></div>
          <?php endif ?>
          <button class="btn btn-primary w-100 auth-submit" type="submit"><?= $setup ? 'Create official account' : ($register ? 'Create resident account' : 'Sign in to workspace') ?><span aria-hidden="true">→</span></button>
        </form>
        <div class="auth-demo"><strong>Want to explore first?</strong><p>Try the full workflow with fictional reports and switch between demo roles.</p><button class="btn btn-light w-100" id="open-demo" type="button">Explore the prototype <span aria-hidden="true">↗</span></button></div>
        <p class="auth-footnote">BarangayResolve · Local project prototype</p>
      </div>
    </section>
  </main>
</body>
</html>
