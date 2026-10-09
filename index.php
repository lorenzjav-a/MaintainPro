<?php
declare(strict_types=1);
require __DIR__.'/includes/page.php';
if (!br_actor()) { header('Location: landing.php'); exit; }
extract(br_page('overview'));
require __DIR__.'/includes/layout/header.php';
$description=match($actor['role']) {
    'official'=>'Review what needs attention, assign work, and close completed concerns.',
    'resident'=>'Report a concern and follow the latest updates from the barangay.',
    default=>'See your assignments, continue work, and submit completion evidence.',
};
br_heading($pageTitle,$description,br_primary($actor).'<a class="btn btn-light" href="track.php">'.br_icon('search').'Track Concern</a>');
require __DIR__.'/includes/components/banner.php';
require __DIR__.'/includes/components/submission-allowance.php';
require __DIR__.'/includes/components/stats.php';
if($actor['role']==='personnel') require __DIR__.'/includes/components/team-work-offers.php';
?>
<div class="overview-grid dashboard-focus">
  <div><?php $compact=true; require __DIR__.'/includes/components/complaint-table.php'; ?></div>
  <aside class="side-stack">
    <section class="panel"><div class="panel-header"><h2 class="panel-title">Messages needing attention</h2></div><div class="panel-body"><p class="mb-2"><strong><?= (int)$messageCounts['concerns'] ?></strong> new concern <?= (int)$messageCounts['concerns']===1?'message':'messages' ?></p><?php if($actor['role']!=='resident'): ?><p class="mb-3"><strong><?= (int)$messageCounts['staff'] ?></strong> new staff <?= (int)$messageCounts['staff']===1?'message':'messages' ?></p><a class="btn btn-light btn-sm" href="messages.php">Open Messages</a><?php else: ?><a class="btn btn-light btn-sm" href="complaints.php?scope=mine">Open My Concerns</a><?php endif ?></div></section>
    <section class="workflow-card"><?php if($actor['role']==='official'): ?><?= br_icon('clipboard') ?><h3>Review the next concern.</h3><p>Start with submitted or reopened concerns, then assign the responsible team after assessment.</p><a class="link-button" href="complaints.php?tab=assessment">Open review queue <?= br_icon('arrow') ?></a><?php elseif($actor['role']==='personnel'): ?><?= br_icon('tool') ?><h3>Continue your assigned work.</h3><p>Review team work offers, add evidence, or report a delay when work cannot continue.</p><a class="link-button" href="complaints.php?tab=work">Open My Work <?= br_icon('arrow') ?></a><?php else: ?><?= br_icon('inbox') ?><h3>Keep track of your concerns.</h3><p>Your status, latest update, and barangay replies are available from My Concerns.</p><a class="link-button" href="complaints.php?scope=mine">Open My Concerns <?= br_icon('arrow') ?></a><?php endif ?></section>
    <section class="panel"><div class="panel-header"><h2 class="panel-title">Need help?</h2></div><div class="panel-body"><p class="form-text">The User Guide explains the complete process for each role.</p><a class="link-button" href="user-guide.php">Open User Guide <?= br_icon('arrow') ?></a></div></section>
  </aside>
</div>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
