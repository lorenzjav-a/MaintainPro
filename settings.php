<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('settings', ['official']));
$locations = br_store()->locations($actor['id']);
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Manage reporting locations and save a database backup.');
?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Purok / Sitio locations</h2></div><div class="panel-body">
<p class="form-text">Once locations are added, residents select from the active list. Deactivating a location keeps past concern records intact.</p>
<form method="post" action="api.php" data-action="create_location" class="row g-3 align-items-end mb-4">
<div class="col-md-7"><label class="form-label">Location name<input class="form-control" name="name" required maxlength="120"></label></div>
<div class="col-md-3"><label class="form-label">Display order<input class="form-control" type="number" name="sortOrder" min="0" max="10000" value="0" required></label></div>
<div class="col-md-2"><button class="btn btn-primary" type="submit">Add location</button></div>
</form>
<?php if (!$locations): ?><p>No locations configured yet. The reporting form currently accepts a typed Purok / Sitio.</p><?php endif ?>
<?php foreach ($locations as $location): ?>
<article class="case-section">
<h3 class="section-title"><?= h($location['name']) ?> <span class="status <?= $location['active'] ? 'status-verified' : 'status-rejected' ?>"><?= $location['active'] ? 'Active' : 'Inactive' ?></span></h3>
<form method="post" action="api.php" data-action="update_location" data-id="<?= (int)$location['id'] ?>" class="row g-3 align-items-end">
<div class="col-md-7"><label class="form-label">Location name<input class="form-control" name="name" value="<?= h($location['name']) ?>" required maxlength="120"></label></div>
<div class="col-md-3"><label class="form-label">Display order<input class="form-control" type="number" name="sortOrder" min="0" max="10000" value="<?= (int)$location['sort_order'] ?>" required></label></div>
<div class="col-md-2"><button class="btn btn-light" type="submit">Save</button></div></form>
<form method="post" action="api.php" data-action="toggle_location" data-id="<?= (int)$location['id'] ?>" class="mt-2"><button class="btn btn-light btn-sm" type="submit"><?= $location['active'] ? 'Deactivate' : 'Activate' ?> location</button></form>
</article><?php endforeach ?>
</div></section>
<section class="panel mt-4"><div class="panel-header"><h2 class="panel-title">Database backup</h2></div><div class="panel-body">
<p>Download a SQL copy of the records. Keep this file private; it contains account and concern data.</p>
<p class="form-text">Uploaded images are separate files. Back up the protected uploads/evidence folder together with the database. Restore SQL only into an empty database.</p>
<form method="post" action="backup.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['br_csrf']) ?>"><button class="btn btn-primary" type="submit"><?= br_icon('download') ?>Download database backup</button></form>
</div></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
