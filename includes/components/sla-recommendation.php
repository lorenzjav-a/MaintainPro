<?php $recommendedDates = ConcernInsights::recommendedDates(); ?>
<div class="priority-advice mb-3" data-sla-dates="<?= h(json_encode($recommendedDates)) ?>">
  <strong>Recommended completion date: <span data-sla-label><?= h(str_replace('T',' ', $recommendedDates[$c['priority']])) ?></span> (Asia/Manila)</strong>
  <p class="form-text">Based on the selected priority. Accept it, choose a different date, or leave the target empty.</p>
  <button type="button" class="btn btn-light btn-sm" data-accept-sla>Accept recommended date</button>
</div>
<label class="form-label">Target completion (optional, Asia/Manila)<input class="form-control" type="datetime-local" name="dueAt" value="<?= !empty($c['dueAt']) ? h(date('Y-m-d\TH:i',(int)$c['dueAt'])) : '' ?>"></label>
