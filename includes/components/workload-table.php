<?php $workloads = br_store()->workloads($actor['id']); ?>
<section class="panel mt-4"><div class="panel-header"><h2 class="panel-title">Personnel workload</h2><span class="count-pill">CURRENT</span></div>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Personnel</th><th>Team</th><th>Active work</th><th>Completed</th><th>Workload</th></tr></thead><tbody>
<?php foreach ($workloads as $worker): ?><tr><td><?= h($worker['name']) ?><?= !$worker['active'] ? ' (inactive)' : '' ?></td><td><?= h($worker['team']) ?></td><td><?= (int)$worker['active_work'] ?></td><td><?= (int)$worker['completed'] ?></td><td><span class="workload-badge workload-<?= (int)$worker['active_work'] >= 6 ? 'high' : ((int)$worker['active_work'] >= 3 ? 'moderate' : 'available') ?>"><?= h(ConcernInsights::workloadLabel((int)$worker['active_work'])) ?></span></td></tr><?php endforeach ?>
<?php if (!$workloads): ?><tr><td colspan="5">No personnel accounts yet.</td></tr><?php endif ?>
</tbody></table></div><p class="form-text p-3 mb-0">Active = Assigned or In Progress. Completed = currently Resolved or Closed. Inactive accounts are excluded from assignment recommendations.</p></section>
