<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
br_public_header('Report a Concern'); ?>
<div class="page-heading"><div><h1>Report a Concern</h1><p>Choose what you noticed. No account or personal details needed.</p></div></div>
<noscript><p class="alert alert-warning">Enable JavaScript to use the category choices and submit this form.</p></noscript>
<section class="panel p-4 public-form-panel" id="report-panel"><form id="public-report" method="post" action="public-api.php">
<?php br_concern_choices(); br_location_fields(); ?>
<div class="mb-4"><label class="form-label" for="description">5. Additional details (optional)</label><textarea id="description" name="description" class="form-control" rows="3" maxlength="4000" placeholder="Anything else staff should know? Avoid names or contact details."></textarea></div>
<div class="mb-4"><?php br_upload('report-photo', '6. Photo'); ?><p class="form-text">Only take a photo when it is safe. Avoid faces or personal documents.</p></div>
<section class="mb-4 resident-guidance" aria-labelledby="guidance-heading"><h2 id="guidance-heading" class="form-label">7. While you wait</h2><p class="guidance-intro">Three temporary steps for you to follow while waiting for staff to address the concern. The barangay will assess the report and arrange the main repair or response.</p><div id="suggestions" aria-live="polite" aria-busy="false">Select a category and concern type to see your guidance.</div></section>
<div class="spam-field" aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="alert alert-danger" id="public-error" role="alert" hidden></div><button type="submit" class="btn btn-primary">Submit Concern</button>
</form></section>
<section id="receipt" class="panel p-4 public-form-panel" hidden tabindex="-1"><span class="eyebrow">CONCERN RECEIVED</span><h2 class="section-title">Save your private tracking details</h2><p>Keep both values. The tracking code is shown only here and cannot be recovered using a name or email.</p><label class="form-label">Concern Reference Number<input id="receipt-reference" readonly class="form-control"></label><label class="form-label">Tracking Code<input id="receipt-code" readonly class="form-control tracking-code"></label><p class="form-text">Anyone with both values can view the general status. Your exact location, images and staff notes remain private.</p><button class="btn btn-primary" type="button" id="save-receipt">Download tracking details &amp; guidance</button> <a class="btn btn-light" href="track.php">Track Concern</a><div id="receipt-guidance" class="mt-4"></div></section>
<?php br_public_footer(); ?>
