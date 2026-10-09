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
br_heading($pageTitle,'Plan, assign and record outcomes. Completed plans remain available for reference.','<a class="btn btn-light" href="reports.php#weekly-concerns">Weekly Top Concerns</a>');
if ($plan || $rule):
$selected=$plan ?? $rule;
?>
<section class="panel action-plan-editor mb-4">
  <div class="panel-header"><div><h2 class="panel-title"><?= $plan?'Edit action plan':'Create action plan' ?></h2><p class="panel-subtitle"><?= $plan ? 'Update assignment, schedule, status, and recorded outcome.' : 'Turn the selected official response into scheduled work.' ?></p></div></div>
  <div class="panel-body">
    <aside class="action-plan-context" aria-label="Selected concern and solution">
      <div><span>Category</span><strong><?= h($selected['category']) ?></strong></div>
      <div><span>Concern type</span><strong><?= h($selected['concern_type']) ?></strong></div>
      <div><span>Keypoint</span><strong><?= h($selected['keypoint']) ?></strong></div>
      <div class="action-plan-context-solution"><span>Selected solution</span><strong><?= h($plan['selected_solution'] ?? $rule['action_text']) ?></strong></div>
    </aside>
    <form method="post" action="api.php" data-action="<?= $plan?'update_action_plan':'create_action_plan' ?>" data-id="<?= (int)($plan['id'] ?? 0) ?>">
      <?php if($plan): ?><input type="hidden" name="version" value="<?= (int)$plan['version'] ?>"><?php else: ?><input type="hidden" name="ruleId" value="<?= (int)$rule['id'] ?>"><input type="hidden" name="ruleVersion" value="<?= (int)$rule['version'] ?>"><input type="hidden" name="requestKey" value="<?= h(bin2hex(random_bytes(32))) ?>"><?php endif ?>
      <div class="action-plan-form-grid">
        <section class="action-plan-form-section" aria-labelledby="action-information-title">
          <div class="action-plan-section-heading"><h3 class="section-title" id="action-information-title">Action information</h3><p class="form-text">Describe the work and expected result.</p></div>
          <label class="form-label" for="plan-title">Action title</label><input class="form-control" id="plan-title" name="title" required maxlength="180" value="<?= h($plan['title'] ?? $rule['concern_type'].' - '.$rule['keypoint']) ?>">
          <label class="form-label" for="plan-notes">Description / notes</label><textarea class="form-control" id="plan-notes" name="notes" maxlength="4000" rows="5"><?= h($plan['notes'] ?? '') ?></textarea>
          <?php if($plan): ?><label class="form-label" for="plan-outcome">Result / outcome notes <span class="form-label-note">(required when completed)</span></label><textarea class="form-control" id="plan-outcome" name="outcome" maxlength="4000" rows="5"><?= h($plan['outcome']) ?></textarea><?php endif ?>
        </section>
        <section class="action-plan-form-section" aria-labelledby="assignment-scheduling-title">
          <div class="action-plan-section-heading"><h3 class="section-title" id="assignment-scheduling-title">Assignment and scheduling</h3><p class="form-text">Choose who is responsible and when the work is due.</p></div>
          <label class="form-label" for="plan-team">Responsible team</label><select class="form-select" id="plan-team" name="team" required><?php br_options(ComplaintWorkflow::TEAMS,$plan['team'] ?? '','Choose a team'); ?></select>
          <label class="form-label" for="plan-personnel">Assigned personnel <span class="form-label-note">(optional)</span></label><select class="form-select" id="plan-personnel" name="personnelId"><option value="">Team only</option><?php foreach(br_store()->workloads($actor['id']) as $worker): if(!$worker['active']) continue; ?><option value="<?= h($worker['id']) ?>"<?= ($plan['assigned_user_id'] ?? '')===$worker['id']?' selected':'' ?>><?= h($worker['name'].' / '.$worker['team']) ?></option><?php endforeach ?></select>
          <label class="form-label" for="plan-target-date">Target completion date <span class="form-label-note">(optional)</span></label><input type="date" class="form-control" id="plan-target-date" name="targetDate" value="<?= h($plan['target_date'] ?? '') ?>">
          <?php if($plan): ?><label class="form-label" for="plan-status">Status</label><select class="form-select" id="plan-status" name="status"><?php br_options(['Planned','Ongoing','Completed','Cancelled'],$plan['status']); ?></select><?php endif ?>
        </section>
      </div>
      <div class="action-plan-form-footer"><?php if($plan): ?><p class="form-text">Created <?= h(date('M j, Y',(int)$plan['created_at'])) ?><?= $plan['completed_at']?' · Completed '.h(date('M j, Y',(int)$plan['completed_at'])):'' ?></p><?php endif ?><button type="submit" class="btn btn-primary"><?= $plan?'Save action plan':'Create planned action' ?></button></div>
    </form>
  </div>
</section><?php endif ?>
<?php if ($plan): ?><section class="panel mb-4"><div class="panel-header"><h2 class="panel-title">Personnel progress</h2></div><div class="panel-body">
<?php foreach ($plan['progress'] as $update): ?><div class="activity-item"><div><strong><?= h($update['actor_name']) ?></strong><p class="form-text"><?= h(date('M j, Y g:i A',(int)$update['created_at'])) ?></p><p><?= nl2br(h($update['note'])) ?></p><?php if ($update['evidence_id']): ?><a href="<?= h(br_url('evidence.php',['id'=>$update['evidence_id']])) ?>" target="_blank" rel="noopener">View work evidence</a><?php endif ?></div></div><?php endforeach ?>
<?php if (!$plan['progress']): ?><p class="form-text">No personnel updates yet.</p><?php endif ?>
</div></section><?php endif ?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Action plan history</h2></div><div class="panel-body">
<form method="get" class="row g-3 align-items-end mb-3 action-plan-filter-form"><div class="col-md-4"><label class="form-label">Status<select class="form-select" name="status"><?php br_options(['Planned','Ongoing','Completed','Cancelled'],br_query('status'),'All statuses'); ?></select></label></div><div class="col-md-4"><label class="form-label">Week beginning<input type="date" class="form-control" name="week" value="<?= h(br_query('week')) ?>"></label></div><div class="col-md-4"><button class="btn btn-light" type="submit">Apply filters</button></div></form>
<?php require __DIR__.'/includes/components/action-plan-list.php'; br_pagination('action-plans.php',$plans,['status'=>br_query('status'),'week'=>br_query('week')]); ?>
</div></section>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
