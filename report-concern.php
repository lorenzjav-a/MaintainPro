<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
$actor=br_actor();
$requestedCategory=br_query('category');
$selectedCategory=isset(ConcernCatalog::TYPES[$requestedCategory])?$requestedCategory:'';
if (isset($_SESSION['br_user_id']) && !$actor) { header('Location: login.php'); exit; }
if ($actor) {
    require_once __DIR__ . '/includes/page.php';
    extract(br_page('new-complaint'));
    $allowance=br_store()->submissionAllowance($actor['id']);
    require __DIR__ . '/includes/layout/header.php';
    br_heading('Report a Concern','Describe the problem in three short steps.');
    require __DIR__ . '/includes/components/submission-allowance.php';
} else {
    br_public_header('Report a Concern');
    br_heading('Report a Concern','Describe the problem in three short steps. No account is required.');
}
?>
<noscript><p class="alert alert-warning">Enable JavaScript to use the category choices and submit this form.</p></noscript>
<section class="panel p-4 public-form-panel" id="report-panel"><form id="public-report" method="post" action="<?= $actor?'api.php':'public-api.php' ?>"<?= $actor?' data-action="submit"':'' ?>>
<section class="report-step" aria-labelledby="report-step-1"><div class="report-step-heading"><span>1</span><div><h2 id="report-step-1" class="section-title">What is the concern?</h2><p>Choose the category and details that best describe the problem.</p></div></div><?php br_concern_choices(['category'=>$selectedCategory]); ?></section>
<section class="report-step" aria-labelledby="report-step-2"><div class="report-step-heading"><span>2</span><div><h2 id="report-step-2" class="section-title">Where is it?</h2><p>Give enough detail for barangay staff to find the location.</p></div></div><?php br_location_fields(); ?></section>
<section class="report-step" aria-labelledby="report-step-3"><div class="report-step-heading"><span>3</span><div><h2 id="report-step-3" class="section-title">Add helpful details</h2><p>A short description or photo can help staff assess the concern.</p></div></div>
<label class="form-label" for="description">Description (optional)</label><textarea id="description" name="description" class="form-control mb-4" rows="3" maxlength="4000" placeholder="What should staff know? Avoid names or contact details."></textarea>
<?php br_upload('report-photo','Photo evidence'); ?><p class="form-text">Only take a photo when it is safe. Avoid faces or personal documents.</p>
<?php if ($actor): ?><details class="report-options mt-4"><summary>Reporting identity options</summary><div class="mt-3"><p class="form-text">Identified reports display <?= h($actor['name']) ?> · <?= h(br_role($actor['role'])) ?>.</p><label class="choice-card"><input class="form-check-input" type="checkbox" name="isAnonymous" value="1" aria-describedby="identity-help"><span>Submit this concern anonymously</span></label><p class="form-text" id="identity-help">Your account stays privately linked for limits and follow-up access.</p></div></details><?php endif ?>
</section>
<details class="resident-guidance mb-4" aria-labelledby="guidance-heading"><summary id="guidance-heading">Safety guidance while you wait</summary><div class="mt-3"><p class="guidance-intro">Temporary safety steps based on the selected concern. Barangay staff remain responsible for assessment and repair.</p><div id="suggestions" aria-live="polite" aria-busy="false">Select a category and concern type to see guidance.</div></div></details>
<div class="spam-field" aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="alert alert-danger" id="public-error" role="alert" hidden></div><button type="submit" class="btn btn-primary btn-lg"<?= $actor&&!$allowance['remaining']?' disabled':'' ?>>Submit Concern</button>
</form></section>
<?php if (!$actor): ?>
<section id="receipt" class="panel p-4 public-form-panel" hidden tabindex="-1"><span class="eyebrow">CONCERN RECEIVED</span><h2 class="section-title">Save your private tracking details</h2><p>Keep both values. The tracking code is shown only here and cannot be recovered using a name or email.</p><label class="form-label">Concern Reference Number<input id="receipt-reference" readonly class="form-control"></label><label class="form-label">Tracking Code<input id="receipt-code" readonly class="form-control tracking-code"></label><p class="form-text">Anyone with both values can view the general status. Your exact location, images and staff notes remain private.</p><button class="btn btn-primary" type="button" id="save-receipt">Download tracking details &amp; guidance</button> <a class="btn btn-light" href="track.php">Track Concern</a><div id="receipt-guidance" class="mt-4"></div></section>
<?php endif; if ($actor) require __DIR__ . '/includes/layout/footer.php'; else br_public_footer(); ?>
