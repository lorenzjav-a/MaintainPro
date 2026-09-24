<?php
$residentFollowUps = array_values(array_filter($c['timeline'] ?? [], static function (array $event): bool {
    return !empty($event['publicReporterFollowup']) || ($event['eventType'] ?? '') === 'resident_followup'
        || in_array($event['title'] ?? '', ['Reporter submitted information', 'Resident follow-up submitted'], true);
}));
?>
<?php if ($residentFollowUps): ?>
<section class="case-section resident-response-section" aria-labelledby="resident-responses-heading">
  <h3 id="resident-responses-heading"><?= br_icon('inbox') ?>Reporter responses</h3>
  <p class="form-text">Responses are append-only and were submitted through the private tracking page.</p>
  <div class="resident-response-list">
    <?php foreach (array_reverse($residentFollowUps) as $response): ?>
    <article class="resident-response-card">
      <div class="resident-response-meta"><strong>Additional information</strong><span><?= h(br_date($response['date'], true)) ?></span></div>
      <p><?= h($response['note'] ?? '') ?></p>
      <?php if (!empty($response['photo']) || !empty($response['evidenceId'])): ?><span class="status status-submitted">Additional evidence attached</span><?php endif ?>
    </article>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>
