<?php // Visibility is checked before rendering. The API independently authorizes every write. ?>
<?php if ($actor['role'] === 'official' && br_review($c)): ?>
<section class="action-panel"><h3>Assess &amp; recommend</h3><p>Review the resident’s suggestion and record the barangay’s official plan.</p>
  <form method="post" action="api.php" data-action="assess" data-id="<?= h($c['id']) ?>">
    <div class="row">
      <div class="col-sm-8"><label class="form-label" for="assess-category">Complaint category</label><select class="form-select" id="assess-category" name="category" required><?php br_options(ComplaintWorkflow::CATEGORIES, $c['category']); ?></select></div>
      <div class="col-sm-4"><label class="form-label" for="assess-priority">Priority</label><select class="form-select" id="assess-priority" name="priority" required><?php br_options(ComplaintWorkflow::PRIORITIES, $c['priority']); ?></select></div>
      <div class="col-12"><label class="form-label" for="recommendation">Barangay recommended action <span class="text-danger">*</span></label><textarea class="form-control" id="recommendation" name="recommendation" rows="4" maxlength="4000" required placeholder="Describe the inspection, action, and follow-up the barangay recommends."><?= h($c['recommendation']) ?></textarea></div>
      <div class="col-12"><label class="form-label" for="assessment">Assessment notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="assessment" name="assessment" rows="2" maxlength="3000"><?= h($c['assessment']) ?></textarea></div>
    </div><div class="action-footer"><small class="form-text">The final recommendation comes from the barangay.</small><button class="btn btn-primary" type="submit"><?= br_icon('check') ?>Save assessment</button></div>
  </form>
  <?php if ($c['status'] === 'Under Review' && $c['recommendation'] !== ''): ?>
  <div class="section-divider"></div><h3>Assign the recommended action</h3>
  <form method="post" action="api.php" data-action="assign" data-id="<?= h($c['id']) ?>"><label class="form-label" for="assign-team">Responsible personnel or team</label><select class="form-select" id="assign-team" name="team" required><?php br_options(ComplaintWorkflow::TEAMS, $c['team'], 'Choose a responsible team'); ?></select><div class="action-footer"><small class="form-text">The team will see this in its assigned work.</small><button class="btn btn-primary" type="submit"><?= br_icon('users') ?>Assign complaint</button></div></form>
  <?php endif ?>
  <details class="mt-4"><summary class="link-button">Return, refer, or reject this report</summary>
    <form class="mt-3" method="post" action="api.php" data-action="exception" data-id="<?= h($c['id']) ?>">
      <label class="form-label" for="exception-status">Alternative assessment outcome</label><select id="exception-status" name="status" class="form-select mb-3"><?php br_options(['Returned for Information', 'Referred to Another Office', 'Rejected'], 'Returned for Information'); ?></select>
      <div id="office-wrap" hidden><label class="form-label" for="receiving-office">Receiving office</label><input class="form-control mb-3" id="receiving-office" name="office" maxlength="200"></div>
      <label class="form-label" for="exception-notes">Reason and next steps</label><textarea class="form-control" id="exception-notes" name="notes" maxlength="3000" required></textarea><button class="btn btn-light mt-3" type="submit">Record outcome</button>
    </form>
  </details>
</section>
<?php elseif ($actor['role'] === 'personnel' && $actor['team'] === $c['team'] && $c['status'] === 'Assigned'): ?>
<section class="action-panel"><h3>Ready to begin?</h3><p>Review the recommended action above, then accept the assignment and start work.</p><form method="post" action="api.php" data-action="start" data-id="<?= h($c['id']) ?>"><button class="btn btn-primary" type="submit"><?= br_icon('tool') ?>Accept &amp; start work</button></form></section>
<?php elseif ($actor['role'] === 'personnel' && $actor['team'] === $c['team'] && $c['status'] === 'In Progress'): ?>
<section class="action-panel"><h3>Record a progress update</h3>
  <form method="post" action="api.php" data-action="note" data-id="<?= h($c['id']) ?>"><label class="form-label" for="progress-notes">Action or update</label><textarea class="form-control" id="progress-notes" name="notes" required maxlength="4000" placeholder="What has the team done so far?"></textarea><button class="btn btn-light mt-3" type="submit">Add progress note</button></form>
  <div class="section-divider"></div><h3>Record the resolution</h3><p>Explain what was done and add completion evidence when available.</p>
  <form method="post" action="api.php" data-action="resolve" data-id="<?= h($c['id']) ?>"><label class="form-label" for="resolution-notes">Work performed <span class="text-danger">*</span></label><textarea class="form-control" id="resolution-notes" name="notes" required maxlength="4000" rows="4" placeholder="Describe the completed action and how the outcome was checked."></textarea><div class="mt-3"><?php br_upload('resolution-photo', 'Completion photo'); ?></div><div class="action-footer"><small class="form-text">The reporting resident will verify the result.</small><button class="btn btn-primary" type="submit"><?= br_icon('checkCircle') ?>Mark as resolved</button></div></form>
</section>
<?php elseif ($actor['role'] === 'resident' && $actor['id'] === $c['residentId'] && $c['status'] === 'Resolved'): ?>
<section class="action-panel"><h3>Was your concern resolved?</h3><p>Review the team’s resolution and check the actual result. Your confirmation closes the complaint.</p><form method="post" action="api.php" data-action="verification" data-id="<?= h($c['id']) ?>"><label class="form-label" for="feedback">Feedback <span class="text-muted fw-normal">(required if the problem remains)</span></label><textarea class="form-control" id="feedback" name="feedback" maxlength="3000" placeholder="Tell the barangay about the result."></textarea><div class="action-footer"><button class="btn btn-light" type="submit" name="decision" value="reopen"><?= br_icon('refresh') ?>Problem still exists</button><button class="btn btn-primary" type="submit" name="decision" value="verify"><?= br_icon('checkCircle') ?>Yes, resolved</button></div></form></section>
<?php elseif ($actor['role'] === 'resident' && $actor['id'] === $c['residentId'] && $c['status'] === 'Returned for Information'): ?>
<section class="action-panel"><h3>Provide the requested information</h3><p>Your response will return this complaint to the barangay’s review queue.</p><form method="post" action="api.php" data-action="information" data-id="<?= h($c['id']) ?>"><label class="form-label" for="additional-info">Additional details</label><textarea class="form-control" name="notes" id="additional-info" required maxlength="2000" placeholder="Add the location, landmark, or information requested."></textarea><div class="mt-3"><?php br_upload('additional-photo', 'Updated supporting photo'); ?></div><button class="btn btn-primary mt-3" type="submit">Send information</button></form></section>
<?php else:
    $next = [
        'Submitted' => 'The barangay official will review this report and provide a recommended action.',
        'Under Review' => 'The barangay official is assessing this concern and preparing the assignment.',
        'Assigned' => 'The assigned team can sign in and accept this work.',
        'In Progress' => 'The assigned team is carrying out the recommended action.',
        'Resolved' => 'The reporting resident can now confirm the result or reopen the complaint.',
        'Verified' => 'The reporting resident confirmed the resolution. This complaint is part of the historical record.',
        'Reopened' => 'The barangay official will reassess this concern and coordinate another action.',
        'Returned for Information' => 'The reporting resident needs to provide the information requested.',
        'Rejected' => 'The reason for rejecting this report is recorded in its timeline.',
        'Referred to Another Office' => 'This concern was referred to ' . ($c['referral'] ?? 'another office') . '. A referral is not a verified resolution.',
    ]; ?>
<div class="info-callout mt-4 mb-0"><?= br_icon('clock') ?> <?= h($next[$c['status']] ?? 'Read the timeline for the next steps.') ?></div>
<?php endif ?>
