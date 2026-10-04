<?php
$weekly = br_store()->weeklyConcerns($actor['id']);
$plans = br_store()->actionPlans($actor['id'], '', $weekly['date'], 1, 5);
$weeklyHasActivity = !empty($weekly['groups']) || !empty($plans['items']);
?>
<div class="weekly-overview-grid<?= $weeklyHasActivity ? ' weekly-overview-grid-active' : '' ?>">
  <section class="panel" id="weekly-concerns">
    <div class="panel-header"><div><h3 class="panel-title">Top concerns this week</h3><p class="panel-subtitle"><?= h($weekly['label']) ?> · Monday–Sunday · Asia/Manila</p></div></div>
    <div class="report-body">
      <?php if ($weekly['groups']): ?><p class="form-text weekly-guidance">Top five category and type groups by reports received. Suggestions support official review; no assignment, priority, or status changes automatically.</p><?php endif ?>
      <?php foreach ($weekly['groups'] as $i => $group): ?>
      <article class="weekly-concern">
        <div class="d-flex flex-wrap justify-content-between gap-2"><h4 class="section-title"><?= $i + 1 ?>. <?= h($group['type'] ?: $group['category']) ?></h4><span class="count-pill"><?= $group['count'] ?> <?= $group['count'] === 1 ? 'report' : 'reports' ?></span></div>
        <p class="form-text"><?= h($group['category']) ?> · Recommended review priority: <?= h($group['priority']) ?><?= $group['recurring'] ? ' · Recurring issue history' : '' ?></p>
        <?php if ($group['areas']): ?><p><strong class="report-inline-label">Reported areas</strong><?= h(implode(', ', array_keys($group['areas']))) ?></p><?php endif ?>
        <?php if ($group['keyPoints']): ?><h4 class="form-label">Common keypoints</h4><p><?= h(implode(' · ', $group['keyPoints'])) ?></p><?php endif ?>
        <h4 class="form-label">Suggested solutions</h4><ol><?php foreach ($group['actions'] as $action): ?><li><?= h($action) ?></li><?php endforeach ?></ol>
        <?php foreach ($group['officialActions'] as $point=>$actions): ?><details class="mb-3"><summary><?= h($point) ?> — choose an official action</summary><ol class="mt-3"><?php foreach($actions as $rule): ?><li class="mb-3"><?= h($rule['action_text']) ?><br><a class="btn btn-light btn-sm mt-2" href="<?= h(br_url('action-plans.php',['rule'=>$rule['id']])) ?>">Create Action Plan</a></li><?php endforeach ?></ol><?php if(!$actions): ?><p class="form-text">No active actions for this keypoint.</p><?php endif ?><a href="<?= h(br_url('official-solutions.php',['category'=>$group['category'],'type'=>$group['type'],'keypoint'=>$point])) ?>">Manage suggested actions</a></details><?php endforeach ?>
        <a class="btn btn-light btn-sm" href="<?= h(br_url('complaints.php', ['week' => $weekly['date'], 'category' => $group['category'], 'type' => $group['type']])) ?>">View Related Concerns</a>
      </article>
      <?php endforeach ?>
      <?php if (!$weekly['groups']): ?><div class="report-empty-state"><?= br_icon('inbox') ?><div><strong>No concerns submitted this week</strong><p>New reports for this Monday–Sunday period will appear here.</p></div></div><?php endif ?>
    </div>
  </section>
  <section class="panel" id="weekly-action-plans"><div class="panel-header"><div><h3 class="panel-title">Weekly action plans</h3><p class="panel-subtitle">Planned work with targets in this period</p></div><a class="link-button" href="action-plans.php">View all plans</a></div><div class="panel-body"><?php if ($plans['items']): require __DIR__.'/action-plan-list.php'; else: ?><div class="report-empty-state"><?= br_icon('clipboard') ?><div><strong>No action plans in this view</strong><p>Plans with targets this week will appear here.</p></div></div><?php endif ?></div></section>
</div>
