<?php $suggestedPriority = $c['priorityRecommendation'] ?? ConcernInsights::priority($c); ?>
<div class="priority-advice mb-3">
  <strong>Recommended priority: <?= h($suggestedPriority['priority']) ?></strong>
  <p class="form-text mb-2">System suggestion · score <?= (int)$suggestedPriority['score'] ?>. You make the final decision.</p>
  <ul><?php foreach ($suggestedPriority['reasons'] as $reason): ?><li><?= h($reason) ?></li><?php endforeach ?></ul>
  <button type="button" class="btn btn-light btn-sm" data-accept-priority="<?= h($suggestedPriority['priority']) ?>">Accept recommendation</button>
  <p class="form-text mb-0 mt-2" data-priority-feedback role="status">You can also choose a different priority below. Save the form to confirm your choice.</p>
</div>
