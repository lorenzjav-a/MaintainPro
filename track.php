<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
br_public_header('Track Concern'); ?>
<div class="page-heading"><div><h1>Track Concern</h1><p>Use the reference number and private code saved after submission.</p></div></div><section class="panel p-4 public-form-panel"><form id="public-track" method="post" action="public-api.php"><label class="form-label">Concern Reference Number<input class="form-control" name="reference" placeholder="CON-2026-000123" required maxlength="64" autocomplete="off"></label><label class="form-label">Tracking Code<input class="form-control tracking-code" name="trackingCode" required pattern="[a-f0-9]{48}" maxlength="48" autocomplete="off"></label><div class="alert alert-danger" id="public-error" role="alert" hidden></div><button type="submit" class="btn btn-primary">View status</button></form></section>
<section class="panel p-4 mt-4 public-form-panel" id="tracking-result" hidden aria-live="polite"></section>
<section class="panel p-4 mt-4 public-form-panel followup-panel" id="followup-panel" hidden aria-labelledby="followup-heading">
  <h2 class="section-title" id="followup-heading">Send Additional Information</h2>
  <p class="form-text">Your response is added to the concern history and cannot be edited after submission. Do not include your name or contact information.</p>
  <form id="public-followup" method="post" action="public-api.php">
    <label class="form-label" for="followup-description">Additional description / information<textarea class="form-control" id="followup-description" name="description" rows="4" maxlength="4000" required placeholder="Answer the barangay’s request with the details you know."></textarea></label>
    <?php br_upload('followup-photo', 'Additional photo / evidence'); ?>
    <p class="form-text">Only take a photo when it is safe. Avoid faces, personal documents, and contact details.</p>
    <div class="alert alert-danger" id="followup-error" role="alert" hidden></div>
    <div class="alert alert-success" id="followup-success" role="status" hidden>Your information was submitted for reassessment.</div>
    <button class="btn btn-primary" type="submit">Submit Additional Information</button>
  </form>
</section>
<noscript><p class="alert alert-warning">Enable JavaScript to securely check your tracking details.</p></noscript>
<?php br_public_footer(); ?>
