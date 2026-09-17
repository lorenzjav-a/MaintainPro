<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('solutions', ['official']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Learn from actions recorded in previously verified complaints.');
$verified = array_values(array_filter($cases, fn($c) => $c['status'] === 'Verified' && $c['resolution']));
usort($verified, fn($a, $b) => strtotime($b['updatedAt']) <=> strtotime($a['updatedAt']));
$groups = br_group($verified, 'category');
?>
<div class="info-callout"><?= br_icon('book') ?> Matches use complaint categories and verified records. Previous actions are reference material; the official reviews and approves the recommendation for each new concern.</div>
<div class="knowledge-grid">
  <?php foreach ($groups as $group): $reference = array_values(array_filter($verified, fn($c) => $c['category'] === $group['label']))[0]; ?>
  <section class="knowledge-card"><span class="status status-verified"><?= $group['count'] ?> verified case<?= $group['count'] === 1 ? '' : 's' ?></span><h3><?= h($group['label']) ?></h3><p><?= h($reference['resolution']['notes']) ?></p><div class="small-meta">Latest reference: <?= h($reference['id']) ?> · Verified <?= h(br_date($reference['updatedAt'])) ?></div><a class="link-button" href="<?= h(br_url('complaint.php', ['id' => $reference['id']])) ?>">Read case &amp; timeline <?= br_icon('arrow') ?></a></section>
  <?php endforeach ?>
  <?php if (!$groups): ?><div class="empty-state"><h3>No verified cases yet</h3><p>Resident-verified resolutions will appear here as reference material.</p></div><?php endif ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
