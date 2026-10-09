<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('settings', ['official']));
$locations = br_store()->locations($actor['id']);
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Manage reporting locations and save a database backup.');
?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Purok / Sitio locations</h2></div><div class="panel-body">
<p class="form-text">Once locations are added, residents choose an entry in this list. Removing a location excludes it from future reports while past concern records retain the location recorded when they were submitted.</p>
<form method="post" action="api.php" data-action="create_location" class="row g-3 align-items-end mb-4">
<div class="col-md-7"><label class="form-label">Location name<input class="form-control" name="name" required maxlength="120"></label></div>
<div class="col-md-3"><label class="form-label">Display order<input class="form-control" type="number" name="sortOrder" min="0" max="10000" value="0" required></label></div>
<div class="col-md-2 d-flex justify-content-end"><button class="btn btn-primary" type="submit">Add location</button></div>
</form>
<?php if (!$locations): ?><p>No locations configured yet. The reporting form currently accepts a typed Purok / Sitio.</p><?php endif ?>
<?php foreach ($locations as $location): ?>
<article class="case-section">
<h3 class="section-title"><?= h($location['name']) ?></h3>
<form method="post" action="api.php" data-action="update_location" data-id="<?= (int)$location['id'] ?>" class="row g-3 align-items-end">
<div class="col-md-7"><label class="form-label">Location name<input class="form-control" name="name" value="<?= h($location['name']) ?>" required maxlength="120"></label></div>
<div class="col-md-3"><label class="form-label">Display order<input class="form-control" type="number" name="sortOrder" min="0" max="10000" value="<?= (int)$location['sort_order'] ?>" required></label></div>
<div class="col-md-2 d-flex justify-content-end"><button class="btn btn-light" type="submit">Save</button></div></form>
<form method="post" action="api.php" data-action="delete_location" data-id="<?= (int)$location['id'] ?>" class="mt-2 d-flex justify-content-end"><button class="btn btn-danger btn-sm" type="submit">Delete location</button></form>
</article><?php endforeach ?>
</div></section>
<section class="panel mt-4" id="database-backup"><div class="panel-header"><h2 class="panel-title">Database backup</h2></div><div class="panel-body">
<p>Download a SQL copy of the records. Keep this file private; it contains account and concern data.</p>
<p class="form-text">Uploaded images are separate files. Back up the protected uploads/evidence folder together with the database. Restore SQL only into an empty database.</p>
<form method="post" action="backup.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['br_csrf']) ?>"><label class="form-label" for="database-backup-password">Your current password</label><input class="form-control mb-3" id="database-backup-password" name="current_password" type="password" autocomplete="current-password" required maxlength="72"><button class="btn btn-primary" type="submit"><?= br_icon('download') ?>Download database backup</button></form>
</div></section>
<section class="panel mt-4"><div class="panel-header"><h2 class="panel-title">Full System Backup</h2></div><div class="panel-body"><p>Download one ZIP with database records, all referenced evidence files and restore instructions.</p><p class="form-text">Configuration secrets, account passwords, reset grants, sessions and logs are excluded. After restoring this ZIP, account holders must reset their passwords through configured email recovery. Keep the archive private.</p><form method="post" action="backup.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['br_csrf']) ?>"><input type="hidden" name="kind" value="full"><label class="form-label" for="full-backup-password">Your current password</label><input class="form-control mb-3" id="full-backup-password" name="current_password" type="password" autocomplete="current-password" required maxlength="72"><button class="btn btn-primary" type="submit"><?= br_icon('download') ?>Download Full System Backup</button></form></div></section>
<section class="panel mt-4" id="factory-reset"><div class="panel-header"><h2 class="panel-title">Factory reset</h2></div><div class="panel-body">
<div class="alert alert-danger" role="alert"><strong>This permanently erases the entire workspace.</strong> All accounts, concerns, tracking records, messages, notifications, action plans, locations, audit history, security records, evidence, and profile photos will be removed. The current session will end and MaintainPro will return to first-time setup.</div>
<p class="form-text">Before continuing, download and securely store a Full System Backup. Configure a server-side <code>APP_SETUP_KEY</code> of at least 32 characters so the new first administrator can be created after the reset.</p>
<form method="post" action="api.php" data-action="factory_reset">
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="factory-reset-password">Your current password<input class="form-control" id="factory-reset-password" name="current_password" type="password" autocomplete="current-password" required maxlength="72"></label></div>
<div class="col-md-6"><label class="form-label" for="factory-reset-confirmation">Type RESET MAINTAINPRO<input class="form-control" id="factory-reset-confirmation" name="confirmation" type="text" autocomplete="off" required maxlength="17" spellcheck="false"></label></div></div>
<div class="d-flex justify-content-end mt-3"><button class="btn btn-danger" type="submit">Reset entire system</button></div>
</form></div></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
