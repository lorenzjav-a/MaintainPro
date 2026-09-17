<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('new-complaint', ['resident']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Resident portal · ' . $actor['name'], '<a class="btn btn-light" href="complaints.php">Back to my complaints</a>');
?>
<section class="panel form-page"><form id="new-complaint-form" method="post" action="api.php" data-action="submit">
  <div class="new-form-body"><div class="info-callout">Describe the concern clearly and include a nearby landmark. The barangay will assess the report and decide on the recommended action.</div><div class="row">
    <div class="col-12"><label class="form-label" for="new-title">Complaint title <span class="text-danger">*</span></label><input class="form-control" id="new-title" name="title" required maxlength="140" placeholder="e.g. Clogged drainage along Mabini Street"></div>
    <div class="col-md-6"><label class="form-label" for="new-category">Category <span class="text-danger">*</span></label><select class="form-select" id="new-category" name="category" required><?php br_options(ComplaintWorkflow::CATEGORIES, '', 'Choose a category'); ?></select></div>
    <div class="col-md-6"><label class="form-label" for="new-location">Location / landmark <span class="text-danger">*</span></label><input class="form-control" id="new-location" name="location" required maxlength="250" placeholder="Street, purok, and nearby landmark"></div>
    <div class="col-12"><label class="form-label" for="new-description">What happened? <span class="text-danger">*</span></label><textarea class="form-control" id="new-description" name="description" required rows="4" maxlength="4000" placeholder="Describe the concern, when you noticed it, and how it affects the community."></textarea></div>
    <div class="col-12"><label class="form-label" for="new-suggestion">Your suggested solution <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="new-suggestion" name="suggestion" rows="2" maxlength="2000" placeholder="What do you think could help?"></textarea><p class="form-text">Your suggestion will be reviewed. The barangay makes the final recommendation.</p></div>
    <div class="col-12"><?php br_upload('new-photo', 'Supporting photo'); ?></div>
  </div></div>
  <div class="form-footer"><small>Submitting as <?= h($actor['name']) ?><br>Fields marked * are required.</small><button class="btn btn-primary" type="submit"><?= br_icon('plus') ?>Submit complaint</button></div>
</form></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
