<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/view.php';
function br_public_header(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?= h($_SESSION['br_csrf']) ?>"><title><?= h($title) ?> · MaintainPro</title><link rel="icon" href="assets/images/favicon.svg"><link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>"><script src="assets/js/public.js?v=<?= filemtime(__DIR__ . '/../assets/js/public.js') ?>" defer></script></head>
<body class="public-page"><script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/theme.js') ?>"></script><a class="visually-hidden-focusable" href="#main-content">Skip to content</a><header class="public-header"><div class="public-header-inner"><a class="brand" href="landing.php" aria-label="MaintainPro home"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community care, connected</small></div></a><button class="public-menu-toggle" type="button" data-public-menu-toggle aria-expanded="false" aria-controls="public-navigation" aria-label="Open navigation"><?= br_icon('menu') ?></button><nav id="public-navigation" data-public-navigation aria-label="Public navigation"><a class="public-nav-link" href="landing.php#about">About</a><a class="public-nav-link" href="landing.php#how-it-works">How It Works</a><a class="public-nav-link" href="landing.php#features">Features</a><a class="public-nav-link" href="landing.php#faq">FAQ</a><?= br_theme_switcher('theme-switcher-public') ?><a class="public-nav-link public-nav-cta" href="login.php">Login</a></nav></div></header><main id="main-content" class="public-main">
<?php }
function br_public_footer(): void { ?>
</main><footer class="public-footer"><div class="public-footer-inner"><div class="public-footer-brand"><a class="brand" href="landing.php"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community care, connected</small></div></a><p>A focused platform for reporting, coordinating, and resolving barangay concerns.</p></div><nav aria-label="Footer navigation"><a href="landing.php#about">About</a><a href="landing.php#how-it-works">How It Works</a><a href="report-concern.php">Report a Concern</a><a href="track.php">Track Concern</a><a href="user-guide.php">User Guide</a><a href="login.php">Login</a></nav></div><div class="public-footer-meta"><span>&copy; <?= date('Y') ?> MaintainPro</span><span>Community Concern &amp; Resolution Management</span></div></footer></body></html>
<?php }

// Shared dependent selectors also used by the official editor and Solution Library.
function br_concern_choices(array $values = [], bool $keyPoints = true): void { ?>
<div data-concern-choices data-selected-type="<?= h($values['concernType'] ?? '') ?>" data-selected-points="<?= h(json_encode($values['keyPoints'] ?? [])) ?>">
<script type="application/json" class="concern-catalog"><?= json_encode(['types' => ConcernCatalog::TYPES, 'points' => ConcernCatalog::POINTS], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">Category<select name="category" class="form-select" required><option value="">Select category</option><?php br_options(array_keys(ConcernCatalog::TYPES), $values['category'] ?? ''); ?></select></label></div><div class="col-md-6"><label class="form-label">Concern type<select name="concernType" class="form-select" required><option value="">Select category first</option></select></label></div></div>
<?php if ($keyPoints): ?><fieldset class="mb-0"><legend class="form-label">Key points <span class="text-muted">(select any that apply)</span></legend><div class="choice-grid" data-key-points></div></fieldset><?php endif ?>
</div>
<?php }
function br_location_fields(array $values = []): void {
    $locations = br_store()->locations();
    $hasRegistry = br_store()->hasLocations();
    $currentId = (string)($values['purokId'] ?? '');
    $knownCurrent = in_array($currentId, array_map(fn($row) => (string)$row['id'], $locations), true);
?>
<fieldset><legend class="form-label">Location details</legend><p class="form-text">This address is visible only to the reporter, barangay officials, and assigned personnel.</p><div class="row g-3">
<div class="col-md-6"><label class="form-label">Barangay / Purok / Sitio
<?php if ($hasRegistry): ?>
<select class="form-select" name="locationId" <?= empty($values['purok']) ? 'required' : '' ?>>
<?php if (!empty($values['purok']) && !$knownCurrent): ?><option value="<?= h($currentId) ?>" selected><?= h($values['purok']) ?> (recorded location)</option>
<?php else: ?><option value="">Select Purok / Sitio</option><?php endif ?>
<?php foreach ($locations as $location): ?><option value="<?= (int)$location['id'] ?>"<?= $currentId === (string)$location['id'] ? ' selected' : '' ?>><?= h($location['name']) ?></option><?php endforeach ?>
</select><?php if (!empty($values['purok'])): ?><input type="hidden" name="purok" value="<?= h($values['purok']) ?>"><?php endif ?>
<?php else: ?><input class="form-control" name="purok" maxlength="120" value="<?= h($values['purok'] ?? '') ?>" required><?php endif ?>
</label><?php if ($hasRegistry && !$locations): ?><p class="form-text">No locations are currently available. Please contact the barangay office.</p><?php endif ?></div>
<?php foreach (['street' => 'Street / road / path', 'exactArea' => 'Exact area or nearest identifiable place', 'landmark' => 'Landmark (optional)'] as $key => $label): ?><div class="col-md-6"><label class="form-label"><?= h($label) ?><input class="form-control" name="<?= $key ?>" maxlength="120" value="<?= h($values[$key] ?? '') ?>" <?= $key !== 'landmark' ? 'required' : '' ?>></label></div><?php endforeach ?></div></fieldset>
<?php }
