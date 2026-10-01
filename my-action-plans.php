<?php
declare(strict_types=1);
require __DIR__.'/includes/page.php';
$context=br_page('my-action-plans',['personnel']);
extract($context);
$id=br_query('id');
$plan=null;
if ($id!=='') {
    if (!preg_match('/\A[1-9][0-9]{0,17}\z/',$id)) br_page_error($context,404,'Action plan unavailable','Choose an assigned action plan.');
    $plan=br_store()->visibleActionPlan($actor['id'],(int)$id);
    if (!$plan) br_page_error($context,404,'Action plan unavailable','Choose an assigned action plan.');
}
try { $plans=br_store()->myActionPlans($actor['id'],br_query('status'),max(1,(int)br_query('p','1'))); }
catch (DomainException $error) { br_page_error($context,422,'Check filters',$error->getMessage()); }
require __DIR__.'/includes/layout/header.php';
br_heading($pageTitle,'Review your assigned weekly work, record progress and submit completed results.');
if ($plan):
?>
<section class="panel mb-4"><div class="panel-header"><div><h2 class="panel-title">Plan #<?= (int)$plan['id'] ?> · <?= h($plan['title']) ?></h2><p class="panel-subtitle"><?= h($plan['status']) ?></p></div></div><div class="panel-body">
  <div class="row g-3">
    <div class="col-md-6"><span class="form-label">Week</span><p><?= h($plan['week_start']) ?></p></div>
    <div class="col-md-6"><span class="form-label">Category / concern</span><p><?= h($plan['category'].' / '.$plan['concern_type']) ?></p></div>
    <div class="col-md-6"><span class="form-label">Keypoint</span><p><?= h($plan['keypoint']) ?></p></div>
    <div class="col-md-6"><span class="form-label">Team</span><p><?= h($plan['team']) ?></p></div>
    <div class="col-md-6"><span class="form-label">Assigned person</span><p><?= h($actor['name']) ?></p></div>
    <div class="col-md-6"><span class="form-label">Target date</span><p><?= h($plan['target_date'] ?: 'Not set') ?></p></div>
    <div class="col-md-6"><span class="form-label">Created</span><p><?= h(date('M j, Y',(int)$plan['created_at'])) ?></p></div>
  </div>
  <h3 class="section-title">Selected action</h3><p><?= h($plan['selected_solution']) ?></p>
  <h3 class="section-title">Instructions</h3><p><?= nl2br(h($plan['notes'] ?: 'No additional instructions.')) ?></p>
  <?php if ($plan['outcome']): ?><h3 class="section-title">Outcome</h3><p><?= nl2br(h($plan['outcome'])) ?></p><?php endif ?>
  <?php if (in_array($plan['status'],['Planned','Ongoing'],true)): ?>
  <form method="post" action="api.php" data-action="personnel_action_plan" data-id="<?= (int)$plan['id'] ?>">
    <input type="hidden" name="version" value="<?= (int)$plan['version'] ?>">
    <?php if ($plan['status']==='Planned'): ?><input type="hidden" name="step" value="start"><?php else: ?><label class="form-label" for="plan-step">Update type</label><select class="form-select mb-3" id="plan-step" name="step"><option value="progress">Record progress</option><option value="complete">Mark completed</option></select><?php endif ?>
    <label class="form-label" for="plan-note"><?= $plan['status']==='Planned'?'Starting note (optional)':'Progress or outcome note' ?></label>
    <textarea class="form-control mb-3" id="plan-note" name="note" rows="3" maxlength="4000"<?= $plan['status']==='Ongoing'?' required':'' ?>></textarea>
    <label class="form-label" for="plan-photo">Work evidence (optional)</label><input class="form-control mb-3" id="plan-photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="plan-photo-help"><p class="form-text" id="plan-photo-help">JPG, PNG or WebP, up to 1 MB. Evidence is visible only to authorized staff.</p>
    <button class="btn btn-primary" type="submit"><?= $plan['status']==='Planned'?'Start work':'Save update' ?></button>
  </form>
  <?php endif ?>
</div></section>
<section class="panel mb-4"><div class="panel-header"><h2 class="panel-title">Progress history</h2></div><div class="panel-body">
<?php foreach ($plan['progress'] as $update): ?><div class="activity-item"><div><strong><?= h($update['actor_name']) ?></strong><p class="form-text"><?= h(date('M j, Y g:i A',(int)$update['created_at'])) ?></p><p><?= nl2br(h($update['note'])) ?></p><?php if ($update['evidence_id']): ?><a href="<?= h(br_url('evidence.php',['id'=>$update['evidence_id']])) ?>" target="_blank" rel="noopener">View work evidence</a><?php endif ?></div></div><?php endforeach ?>
<?php if (!$plan['progress']): ?><p class="form-text">No progress updates yet.</p><?php endif ?>
</div></section>
<?php endif ?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">My action plans</h2></div><div class="panel-body">
<form method="get" class="row g-3 align-items-end mb-3"><div class="col-md-4"><label class="form-label" for="plan-status">Status</label><select class="form-select" id="plan-status" name="status"><?php br_options(['Planned','Ongoing','Completed','Cancelled'],br_query('status'),'All statuses'); ?></select></div><div class="col-md-4"><button class="btn btn-light" type="submit">Apply filter</button></div></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Plan</th><th>Week / target</th><th>Team</th><th>Status</th></tr></thead><tbody>
<?php foreach ($plans['items'] as $row): ?><tr><td><a href="<?= h(br_url('my-action-plans.php',['id'=>$row['id']])) ?>">#<?= (int)$row['id'] ?> · <?= h($row['title']) ?></a><p class="form-text mb-0"><?= h($row['category'].' / '.$row['concern_type'].' / '.$row['keypoint']) ?></p></td><td><?= h($row['week_start']) ?><p class="form-text mb-0"><?= h($row['target_date'] ?: 'No target date') ?></p></td><td><?= h($row['team']) ?></td><td><?= h($row['status']) ?></td></tr><?php endforeach ?>
<?php if (!$plans['items']): ?><tr><td colspan="4">No action plans in this view.</td></tr><?php endif ?>
</tbody></table></div>
<?php br_pagination('my-action-plans.php',$plans,['status'=>br_query('status')]); ?>
</div></section>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
