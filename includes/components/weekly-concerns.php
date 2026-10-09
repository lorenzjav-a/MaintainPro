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
        <header class="weekly-concern-header">
          <div><p class="weekly-concern-rank">Concern <?= $i + 1 ?></p><h4 class="section-title"><?= h($group['type'] ?: $group['category']) ?></h4><p class="form-text weekly-concern-category"><?= h($group['category']) ?></p></div>
          <div class="weekly-concern-signals"><span class="count-pill"><?= $group['count'] ?> <?= $group['count'] === 1 ? 'report' : 'reports' ?></span><span class="priority-pill priority-<?= h(strtolower($group['priority'])) ?>"><?= h($group['priority']) ?> priority</span><?php if ($group['recurring']): ?><span class="recurring-pill">Recurring issue</span><?php endif ?></div>
        </header>
        <div class="weekly-concern-layout">
          <div class="weekly-concern-summary">
            <?php if ($group['areas']): ?><section class="weekly-info-block"><h4 class="form-label">Reported areas</h4><p><?= h(implode(', ', array_keys($group['areas']))) ?></p></section><?php endif ?>
            <?php if ($group['keyPoints']): ?><section class="weekly-info-block"><h4 class="form-label">Common keypoints</h4><div class="weekly-keypoint-chips"><?php foreach ($group['keyPoints'] as $keypoint): ?><span><?= h($keypoint) ?></span><?php endforeach ?></div></section><?php endif ?>
            <section class="weekly-info-block weekly-general-actions"><h4 class="form-label">Suggested solutions</h4><ol><?php foreach ($group['actions'] as $action): ?><li><?= h($action) ?></li><?php endforeach ?></ol></section>
            <a class="btn btn-light btn-sm" href="<?= h(br_url('complaints.php', ['week' => $weekly['date'], 'category' => $group['category'], 'type' => $group['type']])) ?>">View Related Concerns</a>
          </div>
          <section class="weekly-official-actions" aria-labelledby="official-actions-<?= $i ?>">
            <div class="weekly-subsection-heading"><h4 class="form-label" id="official-actions-<?= $i ?>">Official action selection</h4><p class="form-text">Choose an approved response for each relevant keypoint.</p></div>
            <div class="weekly-action-grid">
              <?php foreach ($group['officialActions'] as $point=>$actions): ?><details class="weekly-action-card"><summary><span><?= h($point) ?></span><small><?= count($actions) ?> <?= count($actions) === 1 ? 'action' : 'actions' ?></small></summary><div class="weekly-action-card-body"><ol><?php foreach($actions as $rule): ?><li><p><?= h($rule['action_text']) ?></p><a class="btn btn-light btn-sm" href="<?= h(br_url('action-plans.php',['rule'=>$rule['id']])) ?>">Create Action Plan</a></li><?php endforeach ?></ol><?php if(!$actions): ?><p class="form-text">No active actions for this keypoint.</p><?php endif ?><a class="weekly-manage-link" href="<?= h(br_url('official-solutions.php',['category'=>$group['category'],'type'=>$group['type'],'keypoint'=>$point])) ?>">Manage suggested actions</a></div></details><?php endforeach ?>
              <?php if (!$group['officialActions']): ?><div class="report-empty-state report-empty-state-compact weekly-action-empty"><?= br_icon('clipboard') ?><div><strong>No keypoint actions in this concern</strong><p>Manage the official action library to add selectable responses.</p></div></div><?php endif ?>
            </div>
          </section>
        </div>
      </article>
      <?php endforeach ?>
      <?php if (!$weekly['groups']): ?><div class="report-empty-state"><?= br_icon('inbox') ?><div><strong>No concerns submitted this week</strong><p>New reports for this Monday–Sunday period will appear here.</p></div></div><?php endif ?>
    </div>
  </section>
  <section class="panel" id="weekly-action-plans"><div class="panel-header"><div><h3 class="panel-title">Weekly action plans</h3><p class="panel-subtitle">Planned work with targets in this period</p></div><a class="link-button" href="action-plans.php">View all plans</a></div><div class="panel-body"><?php if ($plans['items']): require __DIR__.'/action-plan-list.php'; else: ?><div class="report-empty-state"><?= br_icon('clipboard') ?><div><strong>No action plans in this view</strong><p>Plans with targets this week will appear here.</p></div></div><?php endif ?></div></section>
</div>
