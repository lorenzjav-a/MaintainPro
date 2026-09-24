<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
$context = br_page('audit', ['official']);
extract($context);
$filters = ['date' => br_query('date'), 'action' => br_query('action'), 'user' => br_query('user')];
try { $result = br_store()->auditLogs($actor['id'], $filters, max(1, (int)br_query('p', '1'))); }
catch (DomainException $error) { br_page_error($context, 422, 'Check audit filters', $error->getMessage()); }
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Review account, assignment, location, and workspace changes.');
?>
<section class="panel user-register"><div class="panel-body">
<form method="get" class="row g-3 align-items-end mb-4">
<div class="col-md-3"><label class="form-label">Date<input class="form-control" name="date" type="date" value="<?= h($filters['date']) ?>"></label></div>
<div class="col-md-4"><label class="form-label">Account<select class="form-select" name="user"><option value="">All accounts</option><?php foreach (br_store()->users($actor['id']) as $user): ?><option value="<?= h($user['id']) ?>"<?= $filters['user'] === $user['id'] ? ' selected' : '' ?>><?= h($user['name']) ?></option><?php endforeach ?></select></label></div>
<div class="col-md-3"><label class="form-label">Action code<input class="form-control" name="action" value="<?= h($filters['action']) ?>" maxlength="120" placeholder="e.g. assignment_changed"></label></div>
<div class="col-md-2"><button class="btn btn-light" type="submit">Apply filters</button></div></form>
<div class="table-responsive"><table class="table"><caption class="visually-hidden">Authorized workspace actions</caption><thead><tr><th>Date</th><th>Account</th><th>Action</th><th>Record</th><th>Changes</th></tr></thead><tbody>
<?php foreach ($result['items'] as $entry): ?><tr><td><?= h(br_date($entry['created_at'], true)) ?></td><td><?= h($entry['actor_name']) ?></td><td><?= h(ucwords(str_replace('_', ' ', $entry['action']))) ?></td><td><?= h($entry['entity_label']) ?></td><td><details><summary>View changes</summary><pre class="text-wrap"><?= h(json_encode(json_decode($entry['changes'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details></td></tr><?php endforeach ?>
<?php if (!$result['items']): ?><tr><td colspan="5">No actions match these filters.</td></tr><?php endif ?>
</tbody></table></div>
<?php br_pagination('audit.php', $result, $filters); ?>
</div></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
