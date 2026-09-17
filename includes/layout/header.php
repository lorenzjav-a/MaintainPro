<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#102b32">
  <meta name="description" content="MaintainPro: report, assess, recommend, assign, resolve, and verify community concerns.">
  <meta name="csrf-token" content="<?= h($_SESSION['br_csrf']) ?>">
  <title><?= h($pageTitle) ?> · MaintainPro</title>
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <script src="assets/vendor/sweetalert2.all.min.js" defer></script>
  <script src="assets/js/app.js" defer></script>
</head>
<body data-page="<?= h($page) ?>">
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a>
<div class="shell">
  <button class="sidebar-scrim" data-menu aria-label="Close navigation" hidden></button>
  <?php require __DIR__ . '/sidebar.php'; require __DIR__ . '/topbar.php'; ?>
  <main class="main" id="main-content">
    <noscript><p class="info-callout">You can browse records and use search without JavaScript. Enable JavaScript to submit forms and account actions.</p></noscript>
    <?php
    $notices = ['submit' => 'Complaint submitted.', 'assess' => 'Assessment and official recommendation saved.', 'assign' => 'Complaint assigned to the selected team.', 'start' => 'Work started. You can now record progress.', 'note' => 'Progress update added to the timeline.', 'resolve' => 'Resolution recorded. Awaiting resident verification.', 'verify' => 'Complaint closed after resident verification.', 'reopen' => 'Complaint reopened for barangay reassessment.', 'information' => 'Additional information sent for review.', 'exception' => 'Assessment outcome recorded.', 'update_user' => 'Account access updated.', 'profile' => 'Your profile was saved.'];
    $notice = $notices[br_query('saved')] ?? '';
    ?>
    <?php if ($notice): ?><div class="alert alert-success" role="status"><?= h($notice) ?></div><?php endif ?>
