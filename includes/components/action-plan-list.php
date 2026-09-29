<div class="table-responsive"><table class="table"><thead><tr><th>Action</th><th>Responsible team</th><th>Target</th><th>Status</th></tr></thead><tbody>
<?php foreach($plans['items'] as $planRow): ?><tr><td><a href="<?= h(br_url('action-plans.php',['id'=>$planRow['id']])) ?>"><?= h($planRow['title']) ?></a><p class="form-text mb-0"><?= h($planRow['category'].' / '.$planRow['keypoint']) ?></p></td><td><?= h($planRow['team']) ?><p class="form-text mb-0"><?= h($planRow['personnel_name'] ?? 'Team only') ?></p></td><td><?= h($planRow['target_date'] ?? 'No target date') ?></td><td><?= h($planRow['status']) ?></td></tr><?php endforeach ?>
<?php if(!$plans['items']): ?><tr><td colspan="4">No action plans in this view.</td></tr><?php endif ?>
</tbody></table></div>
