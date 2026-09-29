<?php $weekly = br_store()->weeklyConcerns($actor['id']); ?>
<section class="panel mt-4" id="weekly-concerns">
  <div class="panel-header"><div><h2 class="panel-title">Top concerns this week</h2><p class="panel-subtitle"><?= h($weekly['label']) ?> · Monday–Sunday · Asia/Manila</p></div></div>
  <div class="report-body">
    <p class="form-text">Top five category/type groups by reports received, including anonymous and guest reports. Suggestions support official review; no assignment, priority, or status is changed automatically.</p>
    <?php foreach ($weekly['groups'] as $i => $group): ?>
    <article class="weekly-concern">
      <div class="d-flex flex-wrap justify-content-between gap-2"><h3 class="section-title"><?= $i + 1 ?>. <?= h($group['type'] ?: $group['category']) ?></h3><span class="count-pill"><?= $group['count'] ?> <?= $group['count'] === 1 ? 'report' : 'reports' ?></span></div>
      <p class="form-text"><?= h($group['category']) ?> · Recommended review priority: <?= h($group['priority']) ?><?= $group['recurring'] ? ' · Recurring issue history' : '' ?></p>
      <?php if ($group['areas']): ?><p>Reported areas: <?= h(implode(', ', array_keys($group['areas']))) ?></p><?php endif ?>
      <?php if ($group['keyPoints']): ?><h4 class="form-label">Common keypoints</h4><p><?= h(implode(' · ', $group['keyPoints'])) ?></p><?php endif ?>
      <h4 class="form-label">Suggested solutions</h4><ol><?php foreach ($group['actions'] as $action): ?><li><?= h($action) ?></li><?php endforeach ?></ol>
      <?php foreach ($group['officialActions'] as $point=>$actions): ?><details class="mb-3"><summary><?= h($point) ?> — choose an official action</summary><ol class="mt-3"><?php foreach($actions as $rule): ?><li class="mb-3"><?= h($rule['action_text']) ?><br><a class="btn btn-light btn-sm mt-2" href="<?= h(br_url('action-plans.php',['rule'=>$rule['id']])) ?>">Create Action Plan</a></li><?php endforeach ?></ol><?php if(!$actions): ?><p class="form-text">No active actions for this keypoint.</p><?php endif ?><a href="<?= h(br_url('official-solutions.php',['category'=>$group['category'],'type'=>$group['type'],'keypoint'=>$point])) ?>">Manage suggested actions</a></details><?php endforeach ?>
      <a class="btn btn-light btn-sm" href="<?= h(br_url('complaints.php', ['week' => $weekly['date'], 'category' => $group['category'], 'type' => $group['type']])) ?>">View Related Concerns</a>
    </article>
    <?php endforeach ?>
    <?php if (!$weekly['groups']): ?><p class="text-muted mb-0">No concerns submitted this week yet.</p><?php endif ?>
  </div>
</section>
<?php $plans=br_store()->actionPlans($actor['id'],'',$weekly['date'],1,5); ?>
<section class="panel mt-4" id="weekly-action-plans"><div class="panel-header"><h2 class="panel-title">Weekly action plans</h2><a class="link-button" href="action-plans.php">View all plans</a></div><div class="panel-body"><?php require __DIR__.'/action-plan-list.php'; ?></div></section>
