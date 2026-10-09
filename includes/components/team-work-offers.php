<?php
$teamOffers=br_store()->pendingTeamOffers($actor['id']);
if ($teamOffers): ?>
<section class="panel mb-4" data-workflow-section="team-work-offers">
  <div class="panel-header"><div><h2 class="panel-title">Pending Work Offers <span class="count-pill"><?= count($teamOffers) ?></span></h2><p class="panel-subtitle">Concerns assigned to <?= h($actor['team']) ?>. The first eligible personnel member to accept becomes responsible for the work.</p></div></div>
  <div class="table-responsive"><table class="table"><caption class="visually-hidden">Pending team work offers</caption><thead><tr><th>Concern</th><th>Category / type</th><th>Priority</th><th>Location</th><th>Target completion</th><th>Assigned</th><th>Action</th></tr></thead><tbody>
  <?php foreach($teamOffers as $offer): ?><tr><td data-label="Concern"><a class="case-link" href="<?= h(br_url('complaint.php',['id'=>$offer['id']])) ?>"><?= h($offer['id']) ?></a></td><td data-label="Category / type"><?= h($offer['category']) ?><br><span class="form-text"><?= h($offer['concernType']) ?></span></td><td data-label="Priority"><?= br_priority($offer) ?></td><td data-label="Location"><?= h($offer['location']) ?></td><td data-label="Target completion"><?= !empty($offer['dueAt'])?h(br_date($offer['dueAt'],true)):'Not set' ?></td><td data-label="Assigned"><?= h(br_date($offer['assignedAt'],true)) ?></td><td data-label="Action"><div class="d-flex flex-wrap gap-2"><form method="post" action="api.php" data-action="accept_work" data-id="<?= h($offer['id']) ?>"><button class="btn btn-primary btn-sm" type="submit">Accept Work</button></form><form method="post" action="api.php" data-action="decline_work" data-id="<?= h($offer['id']) ?>"><button class="btn btn-danger btn-sm" type="submit">Not Available</button></form></div></td></tr><?php endforeach ?>
  </tbody></table></div>
</section>
<?php endif ?>
