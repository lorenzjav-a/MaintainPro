<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
br_public_header('Community concern reporting and resolution');
?>
<section class="landing-hero" aria-labelledby="landing-title">
  <div class="landing-hero-copy">
    <span class="eyebrow">A SMARTER WAY TO MANAGE COMMUNITY CONCERNS</span>
    <h1 id="landing-title">Report Concerns. Track Progress. Build a Better Community.</h1>
    <p class="landing-lead">MaintainPro gives residents and barangay teams one clear, dependable way to report local issues, coordinate action, and follow every concern through resolution.</p>
    <div class="landing-actions"><a class="btn btn-primary btn-lg" href="report-concern.php">Report a Concern<?= br_icon('arrow') ?></a><a class="btn btn-light btn-lg" href="track.php"><?= br_icon('search') ?>Track Concern</a><a class="btn btn-light btn-lg" href="#about">Learn More</a></div>
    <p class="landing-trust-note"><?= br_icon('shield') ?><span>Guest reporting is available. Exact locations and evidence stay private.</span></p>
  </div>
  <div class="community-visual community-visual-hero" aria-hidden="true"><span class="community-orbit community-orbit-one"></span><span class="community-orbit community-orbit-two"></span><div class="community-center"><?= br_icon('building') ?></div><div class="community-node community-node-report"><?= br_icon('clipboardList') ?><span>Report</span></div><div class="community-node community-node-people"><?= br_icon('users') ?><span>Coordinate</span></div><div class="community-node community-node-resolve"><?= br_icon('checkCircle') ?><span>Resolve</span></div><span class="community-ground"></span></div>
</section>

<section class="landing-benefits" aria-label="MaintainPro benefits">
  <?php foreach ([['clipboardList', 'Organized Reports', 'Keep concern details, locations, evidence, and updates together in one reliable record.'], ['users', 'Better Coordination', 'Help officials and assigned personnel work from the same clear information and next steps.'], ['checkCircle', 'Clear Progress', 'Give residents a straightforward way to follow updates from submission to resolution.']] as [$icon, $title, $copy]): ?>
    <article class="landing-benefit"><span class="landing-icon"><?= br_icon($icon) ?></span><div><h2><?= h($title) ?></h2><p><?= h($copy) ?></p></div></article>
  <?php endforeach ?>
</section>

<section id="about" class="landing-section landing-about" aria-labelledby="about-title">
  <div class="landing-section-copy"><span class="eyebrow">BUILT FOR COMMUNITY ACTION</span><h2 id="about-title">A clearer path from concern to resolution</h2><p>MaintainPro connects residents, barangay officials, and field personnel through a shared concern-management process. It replaces scattered follow-ups with organized records, accountable assignments, and visible progress.</p><p>The platform supports responsible reporting while protecting sensitive location details and photo evidence within the concern record.</p><a class="landing-text-link" href="#how-it-works">See how the process works<?= br_icon('arrow') ?></a></div>
  <div class="community-visual community-visual-about" aria-hidden="true"><div class="about-map-path"></div><span class="about-map-point about-map-point-start"><?= br_icon('pin') ?></span><span class="about-map-point about-map-point-team"><?= br_icon('users') ?></span><span class="about-map-point about-map-point-done"><?= br_icon('checkCircle') ?></span><div class="about-visual-copy"><strong>One shared process</strong><span>Residents and barangay teams stay connected from first report to final update.</span></div></div>
</section>

<section id="how-it-works" class="landing-section landing-process" aria-labelledby="process-title">
  <div class="landing-section-heading"><span class="eyebrow">HOW IT WORKS</span><h2 id="process-title">Four practical stages, one transparent workflow</h2><p>Each concern moves through a clear process shaped around the work barangay teams already do.</p></div>
  <ol class="process-list">
    <?php foreach ([['clipboardList', 'Report', 'Share the concern type, location, description, and optional photo evidence.'], ['search', 'Assess', 'An authorized official reviews the report, checks its details, and determines the next action.'], ['users', 'Assign', 'The concern is assigned to the appropriate personnel for coordinated field work.'], ['checkCircle', 'Resolve', 'Progress and evidence are recorded until the work is reviewed and the concern is closed.']] as $index => [$icon, $title, $copy]): ?>
      <li class="process-step"><span class="process-number"><?= $index + 1 ?></span><span class="landing-icon"><?= br_icon($icon) ?></span><h3><?= h($title) ?></h3><p><?= h($copy) ?></p></li>
    <?php endforeach ?>
  </ol>
</section>

<section id="features" class="landing-section" aria-labelledby="features-title">
  <div class="landing-section-heading"><span class="eyebrow">KEY FEATURES</span><h2 id="features-title">Everything needed to keep concerns moving</h2><p>Focused tools support accurate reporting, timely action, and useful updates without adding unnecessary complexity.</p></div>
  <div class="landing-feature-grid">
    <?php foreach ([['clipboardList', 'Concern Reporting', 'Submit structured concern details as a resident or guest, with clear categories and location fields.'], ['camera', 'Photo Evidence', 'Attach private JPG, PNG, or WebP evidence to help authorized teams understand the issue.'], ['clock', 'Progress Tracking', 'Use a private reference and tracking code to review status updates and respond to information requests.'], ['book', 'Suggested Solutions', 'Receive practical temporary guidance based on the selected concern while the barangay reviews the report.']] as [$icon, $title, $copy]): ?>
      <article class="landing-feature-card"><span class="landing-icon"><?= br_icon($icon) ?></span><h3><?= h($title) ?></h3><p><?= h($copy) ?></p></article>
    <?php endforeach ?>
  </div>
</section>

<section class="landing-section landing-users" aria-labelledby="users-title">
  <div class="landing-section-heading"><span class="eyebrow">WHO CAN USE MAINTAINPRO</span><h2 id="users-title">Designed around every role in the response process</h2></div>
  <div class="landing-role-grid">
    <?php foreach ([['users', 'Residents', 'Report concerns, add useful evidence, follow progress, and provide requested information.'], ['shield', 'Officials and Admins', 'Assess incoming reports, prioritize work, assign teams or crews, and review outcomes.'], ['tool', 'Personnel', 'Accept team work offers, record progress, and submit completion evidence from the field.']] as [$icon, $title, $copy]): ?>
      <article class="landing-role"><span class="landing-icon"><?= br_icon($icon) ?></span><div><h3><?= h($title) ?></h3><p><?= h($copy) ?></p></div></article>
    <?php endforeach ?>
  </div>
</section>

<section id="faq" class="landing-section landing-faq" aria-labelledby="faq-title">
  <div class="landing-section-heading"><span class="eyebrow">FREQUENTLY ASKED QUESTIONS</span><h2 id="faq-title">Helpful answers before you get started</h2></div>
  <div class="faq-list">
    <?php foreach ([['Do I need an account to report a concern?', 'No. Guests can submit a concern without creating an account. Registered residents can also use their account for a connected experience.'], ['How do I track a concern after submitting it?', 'Keep the private reference number and tracking code shown after submission, then enter both on the Track Concern page.'], ['Can I add a photo to my report?', 'Yes. You can attach one optional JPG, PNG, or WebP photo up to 5 MB when reporting or providing requested follow-up information.'], ['Who can see my exact location and evidence?', 'Sensitive location details and evidence are limited to the reporter, authorized barangay officials, and assigned personnel handling the concern.'], ['What happens after a report is submitted?', 'An authorized official assesses the concern and may request more information, link a duplicate report, or assign it to personnel for action.'], ['Should I use MaintainPro for an emergency?', 'No. For immediate danger or an active emergency, contact the appropriate local emergency service directly.']] as [$question, $answer]): ?>
      <details class="faq-item"><summary><?= h($question) ?><span class="faq-chevron"><?= br_icon('chevron') ?></span></summary><p><?= h($answer) ?></p></details>
    <?php endforeach ?>
  </div>
</section>

<section class="landing-cta" aria-labelledby="cta-title"><div><span class="eyebrow">COMMUNITY CARE STARTS WITH A CLEAR REPORT</span><h2 id="cta-title">Ready to Make a Difference?</h2><p>Share a concern today and help your barangay respond with better information and clearer coordination.</p></div><a class="btn btn-light btn-lg" href="report-concern.php">Report a Concern<?= br_icon('arrow') ?></a></section>
<?php br_public_footer(); ?>
