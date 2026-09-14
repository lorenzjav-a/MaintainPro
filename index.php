<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$actor = br_actor();
if ($actor === null || $actor['must_change_password']) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#102b32">
  <meta name="description" content="MaintainPro prototype: report, assess, recommend, assign, resolve, and verify community concerns.">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['br_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <title>MaintainPro · Complaint management</title>
  <link rel="icon" href="favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="styles.css">
  <script src="assets/vendor/bootstrap.bundle.min.js" defer></script>
  <script src="assets/vendor/sweetalert2.all.min.js" defer></script>
  <script src="app.js" defer></script>
</head>
<body>
  <div id="app"><div class="initial-load"><span class="spinner-border text-success" aria-hidden="true"></span><p role="status">Opening your barangay workspace…</p></div></div>
  <div class="modal fade" id="case-modal" tabindex="-1" aria-labelledby="case-heading" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content" id="case-content"></div></div>
  </div>
  <div class="modal fade" id="form-modal" tabindex="-1" aria-labelledby="form-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content" id="form-content"></div></div>
  </div>
  <noscript><p>Please enable JavaScript to use this interactive prototype.</p></noscript>
</body>
</html>
