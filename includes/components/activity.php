<?php
$events = [];
foreach ($cases as $case) foreach ($case['timeline'] as $event) $events[] = ['id' => $case['id'], 'event' => $event];
usort($events, fn($a, $b) => strtotime($b['event']['date']) <=> strtotime($a['event']['date']));
?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Latest activity</h2><span class="count-pill">RECENT</span></div><div class="activity-list">
  <?php foreach (array_slice($events, 0, 4) as $entry): ?>
  <div class="activity-item"><span class="activity-mark"><?= br_icon(str_contains(strtolower($entry['event']['title']), 'closed') ? 'check' : 'clock') ?></span><div><p><a href="<?= h(br_url('complaint.php', ['id' => $entry['id']])) ?>"><?= h($entry['id']) ?></a> · <?= h($entry['event']['title']) ?></p><small><?= h(br_date($entry['event']['date'], true)) ?></small></div></div>
  <?php endforeach ?>
  <?php if (!$events): ?><p class="text-muted small">No activity yet.</p><?php endif ?>
</div></section>
