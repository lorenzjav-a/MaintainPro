<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
br_public_header('Track Concern'); ?>
<div class="page-heading"><div><h1>Track Concern</h1><p>Use the reference number and private code saved after submission.</p></div></div><section class="panel p-4 public-form-panel"><form id="public-track" method="post" action="public-api.php"><label class="form-label">Concern Reference Number<input class="form-control" name="reference" placeholder="CON-2026-000123" required maxlength="64" autocomplete="off"></label><label class="form-label">Tracking Code<input class="form-control tracking-code" name="trackingCode" required pattern="[a-f0-9]{48}" maxlength="48" autocomplete="off"></label><div class="alert alert-danger" id="public-error" role="alert" hidden></div><button type="submit" class="btn btn-primary">View status</button></form></section><section class="panel p-4 mt-4 public-form-panel" id="tracking-result" hidden aria-live="polite"></section>
<noscript><p class="alert alert-warning">Enable JavaScript to securely check your tracking details.</p></noscript>
<?php br_public_footer(); ?>
