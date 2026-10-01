<?php
declare(strict_types=1);
require __DIR__.'/includes/page.php';
$context=br_page('action-plans',['official']); extract($context);
$plan=br_query('id')!=='' ? br_store()->visibleActionPlan($actor['id'],(int)br_query('id')) : null;
if (br_query('id')!=='' && !$plan) br_page_error($context,404,'Action plan unavailable','Choose a saved action plan.');
$rule=br_query('rule')!=='' ? br_store()->selectedOfficialRule($actor['id'],(int)br_query('rule')) : null;
if (br_query('rule')!=='' && (!$rule || !$rule['active'])) br_page_error($context,422,'Suggested action unavailable','Choose an active action from Weekly Top Concerns.');
try { $plans=br_store()->actionPlans($actor['id'],br_query('status'),br_query('week'),max(1,(int)br_query('p','1'))); }
catch(DomainException $error) { br_page_error($context,422,'Check filters',$error->getMessage()); }
require __DIR__.'/includes/layout/header.php';
br_heading($pageTitle,'Plan, assign and record outcomes. Completed plans remain available for reference.','<a class="btn btn-light" href="index.php#weekly-concerns">Weekly Top Concerns</a>');
if ($plan || $rule):
$selected=$plan ?? $rule;
?>
<section class="panel mb-4"><div class="panel-header"><h2 class="panel-title"><?= $plan?'Edit action plan':'Create action plan' ?></h2></div><div class="panel-body">
<p><?= h($selected['category'].' / '.$selected['concern_type'].' / '.$selected['keypoint']) ?></p><p><strong>Selected solution:</strong> <?= h($plan['selected_solution'] ?? $rule['action_text']) ?></p>
<form method="post" action="api.php" data-action="<?= $plan?'update_action_plan':'create_action_plan' ?>" data-id="<?= (int)($plan['id'] ?? 0) ?>">
<?php if($plan): ?><input type="hidden" name="version" value="<?= (int)$plan['version'] ?>"><?php else: ?><input type="hidden" name="ruleId" value="<?= (int)$rule['id'] ?>"><input type="hidden" name="ruleVersion" value="<?= (int)$rule['version'] ?>"><input type="hidden" name="requestKey" value="<?= h(bin2hex(random_bytes(32))) ?>"><?php endif ?>
<label class="form-label">Action title<input class="form-control" name="title" required maxlength="180" value="<?= h($plan['title'] ?? $rule['concern_type'].' - '.$rule['keypoint']) ?>"></label>
<label class="form-label">Description / notes<textarea class="form-control" name="notes" maxlength="4000" rows="3"><?= h($plan['notes'] ?? '') ?></textarea></label>
<div class="row g-3"><div class="col-md-6"><label class="form-label">Responsible team<select class="form-select" name="team" required><?php br_options(ComplaintWorkflow::TEAMS,$plan['team'] ?? '','Choose a team'); ?></select></label></div>
<div class="col-md-6"><label class="form-label">Assigned personnel (optional)<select class="form-select" name="personnelId"><option value="">Team only</option><?php foreach(br_store()->workloads($actor['id']) as $worker): if(!$worker['active']) continue; ?><option value="<?= h($worker['id']) ?>"<?= ($plan['assigned_user_id'] ?? '')===$worker['id']?' selected':'' ?>><?= h($worker['name'].' / '.$worker['team']) ?></option><?php endforeach ?></select></label></div>
<div class="col-md-6"><label class="form-label">Target completion date (optional)<input type="date" class="form-control" name="targetDate" value="<?= h($plan['target_date'] ?? '') ?>"></label></div>
<?php if($plan): ?><div class="col-md-6"><label class="form-label">Status<select class="form-select" name="status"><?php br_options(['Planned','Ongoing','Completed','Cancelled'],$plan['status']); ?></select></label></div><?php endif ?></div>
<?php if($plan): ?><label class="form-label">Result / outcome notes (required when completed)<textarea class="form-control" name="outcome" maxlength="4000" rows="3"><?= h($plan['outcome']) ?></textarea></label><p class="form-text">Created <?= h(date('M j, Y',(int)$plan['created_at'])) ?><?= $plan['completed_at']?' · Completed '.h(date('M j, Y',(int)$plan['completed_at'])):'' ?></p><?php endif ?>
<button type="submit" class="btn btn-primary"><?= $plan?'Save action plan':'Create planned action' ?></button>
</form></div></section><?php endif ?>
<?php if ($plan): ?><section class="panel mb-4"><div class="panel-header"><h2 class="panel-title">Personnel progress</h2></div><div class="panel-body">
<?php foreach ($plan['progress'] as $update): ?><div class="activity-item"><div><strong><?= h($update['actor_name']) ?></strong><p class="form-text"><?= h(date('M j, Y g:i A',(int)$update['created_at'])) ?></p><p><?= nl2br(h($update['note'])) ?></p><?php if ($update['evidence_id']): ?><a href="<?= h(br_url('evidence.php',['id'=>$update['evidence_id']])) ?>" target="_blank" rel="noopener">View work evidence</a><?php endif ?></div></div><?php endforeach ?>
<?php if (!$plan['progress']): ?><p class="form-text">No personnel updates yet.</p><?php endif ?>
</div></section><?php endif ?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Action plan history</h2></div><div class="panel-body">
<form method="get" class="row g-3 align-items-end mb-3"><div class="col-md-4"><label class="form-label">Status<select class="form-select" name="status"><?php br_options(['Planned','Ongoing','Completed','Cancelled'],br_query('status'),'All statuses'); ?></select></label></div><div class="col-md-4"><label class="form-label">Week beginning<input type="date" class="form-control" name="week" value="<?= h(br_query('week')) ?>"></label></div><div class="col-md-4"><button class="btn btn-light" type="submit">Apply filters</button></div></form>
<?php require __DIR__.'/includes/components/action-plan-list.php'; br_pagination('action-plans.php',$plans,['status'=>br_query('status'),'week'=>br_query('week')]); ?>
</div></section>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
