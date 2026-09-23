<?php if (!empty($c['dueAt'])): ?><p class="info-callout"><strong>Target completion:</strong> <?= h(date('M j, Y · g:i A',(int)$c['dueAt'])) ?> (Asia/Manila)<?php if ($c['dueAt'] < time() && in_array($c['status'],['Assigned','In Progress'],true)): ?> · <strong>Overdue</strong><?php endif ?></p><?php endif ?>
<?php if ($actor['role'] === 'official'):
    $related = br_store()->relatedConcerns($actor['id'],$c['id']);
    $level = ConcernInsights::recurrenceLevel(count($related));
    $priorityAdvice = $c['priorityRecommendation'] ?? ConcernInsights::priority($c);
?>
<section class="case-section" id="recurrence"><h3><?= h($level) ?><?= count($related) >= 3 ? ' issue detected' : ' · recurrence check' ?></h3>
<p><?= count($related) ?> report(s) with the same category, type, purok and street during the last <?= (int)ConcernInsights::config()['recurrence_days'] ?> days.</p>
<?php if ($related): ?><details><summary>View <?= count($related) ?> related concerns</summary><ul class="mt-2"><?php foreach ($related as $relatedCase): ?><li><a href="<?= h(br_url('concern.php',['id' => $relatedCase['id']])) ?>"><?= h($relatedCase['id']) ?></a> · <?= h(br_date($relatedCase['created_at'])) ?> · <?= h($relatedCase['status'] === 'Verified' ? 'Closed' : $relatedCase['status']) ?></li><?php endforeach ?></ul></details><?php else: ?><p class="form-text">Legacy reports without structured locations are not grouped.</p><?php endif ?>
</section>
<section class="case-section"><h3>Priority recommendation: <?= h($priorityAdvice['priority']) ?></h3><ul><?php foreach ($priorityAdvice['reasons'] as $reason): ?><li><?= h($reason) ?></li><?php endforeach ?></ul><p class="form-text">Score <?= (int)$priorityAdvice['score'] ?> · <?= empty($c['priorityDecision']) ? 'Awaiting official confirmation. The current default is not a final assessment.' : ('Official priority: ' . h($c['priority']) . ' · ' . ($c['priorityDecision']['overridden'] ? 'Recommendation overridden' : 'Recommendation accepted')) ?></p></section>
<?php endif ?>
