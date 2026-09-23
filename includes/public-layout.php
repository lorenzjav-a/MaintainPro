<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/view.php';
function br_public_header(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?= h($_SESSION['br_csrf']) ?>"><title><?= h($title) ?> · MaintainPro</title><link rel="icon" href="assets/images/favicon.svg"><link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>"><script src="assets/js/public.js?v=<?= filemtime(__DIR__ . '/../assets/js/public.js') ?>" defer></script></head>
<body class="public-page"><a class="visually-hidden-focusable" href="#main-content">Skip to content</a><header class="public-header"><a class="brand" href="landing.php"><img src="assets/images/favicon.svg" alt=""><div><div class="brand-title">Maintain<span>Pro</span></div><small>Community care, connected</small></div></a><nav aria-label="Public navigation"><a href="report-concern.php">Report a Concern</a><a href="track.php">Track Concern</a><a class="btn btn-light" href="login.php">Staff login</a></nav></header><main id="main-content" class="public-main">
<?php }
function br_public_footer(): void { ?>
</main><footer class="public-footer">MaintainPro · Community Concern &amp; Resolution Management</footer></body></html>
<?php }

// Shared dependent selectors also used by the official editor and Solution Library.
function br_concern_choices(array $values = [], bool $keyPoints = true): void { ?>
<div data-concern-choices data-selected-type="<?= h($values['concernType'] ?? '') ?>" data-selected-points="<?= h(json_encode($values['keyPoints'] ?? [])) ?>">
<script type="application/json" class="concern-catalog"><?= json_encode(['types' => ConcernCatalog::TYPES, 'points' => ConcernCatalog::POINTS], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">1. Category<select name="category" class="form-select" required><option value="">Select category</option><?php br_options(array_keys(ConcernCatalog::TYPES), $values['category'] ?? ''); ?></select></label></div><div class="col-md-6"><label class="form-label">2. Specific concern<select name="concernType" class="form-select" required><option value="">Select category first</option></select></label></div></div>
<?php if ($keyPoints): ?><fieldset class="mb-4"><legend class="form-label">3. Key points <span class="text-muted">(select any that apply)</span></legend><div class="choice-grid" data-key-points></div></fieldset><?php endif ?>
</div>
<?php }
function br_location_fields(array $values = []): void { ?>
<fieldset class="mb-4"><legend class="form-label">4. Private location</legend><p class="form-text">Only barangay officials and assigned personnel can see this address. Do not include your name or contact information.</p><div class="row g-3">
<?php foreach (['purok' => 'Barangay / Purok / Sitio', 'street' => 'Street / road / path', 'exactArea' => 'Exact area or nearest identifiable place', 'landmark' => 'Landmark (optional)'] as $key => $label): ?><div class="col-md-6"><label class="form-label"><?= h($label) ?><input class="form-control" name="<?= $key ?>" maxlength="120" value="<?= h($values[$key] ?? '') ?>" <?= $key !== 'landmark' ? 'required' : '' ?>></label></div><?php endforeach ?></div></fieldset>
<?php }
