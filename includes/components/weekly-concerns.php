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
      <a class="btn btn-light btn-sm" href="<?= h(br_url('complaints.php', ['week' => $weekly['date'], 'category' => $group['category'], 'type' => $group['type']])) ?>">View Related Concerns</a>
    </article>
    <?php endforeach ?>
    <?php if (!$weekly['groups']): ?><p class="text-muted mb-0">No concerns submitted this week yet.</p><?php endif ?>
  </div>
</section>
