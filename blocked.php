<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('blocked', ['official']));
$result = br_store()->blockedConcerns($actor['id'], max(1, (int)br_query('p', '1')));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Review delays, approve requested actions, and help personnel resume work.');
?>
<section class="panel"><div class="panel-header"><h2 class="panel-title">Work awaiting assistance <span class="count-pill"><?= (int)$result['total'] ?></span></h2></div><div class="panel-body">
<?php foreach ($result['items'] as $row): $concern = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); $block = $concern['blocked']; ?>
<article class="case-section"><h3 class="section-title"><a href="<?= h(br_url('complaint.php', ['id' => $concern['id']])) ?>"><?= h($concern['id'] . ' · ' . $concern['title']) ?></a></h3>
<p><strong><?= h($block['reason']) ?></strong> · <?= h($concern['assignedName'] ?? 'Unassigned') ?></p><p><?= h($block['recommendedAction']) ?></p>
<?php if (!empty($block['officialInstructions'])): ?><p>Official instructions: <?= h($block['officialInstructions']) ?></p><?php endif ?>
<?php if (!empty($block['expectedAt'])): ?><p class="form-text">Expected availability: <?= h(br_date($block['expectedAt'])) ?></p><?php endif ?>
<a class="btn btn-light btn-sm" href="<?= h(br_url('complaint.php', ['id' => $concern['id']])) ?>">Review blocked work</a></article>
<?php endforeach ?>
<?php if (!$result['items']): ?><p>No blocked work requires review.</p><?php endif ?>
<?php br_pagination('blocked.php', $result); ?>
</div></section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
