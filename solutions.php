<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
extract(br_page('solutions', ['official']));
require __DIR__ . '/includes/layout/header.php';
br_heading($pageTitle, 'Learn from actions recorded in previously verified concerns.');
$verified = array_values(array_filter($cases, fn($c) => $c['status'] === 'Verified' && $c['resolution']));
usort($verified, fn($a, $b) => strtotime($b['updatedAt']) <=> strtotime($a['updatedAt']));
$groups = br_group($verified, 'category');
?>
<section class="panel p-4 mb-4"><h2 class="h4">Public recommendation rules</h2><p>Maintain three suggested actions per concern type. These curated actions appear on the anonymous form. Never include names, exact addresses, account information or private case notes.</p><form method="post" action="api.php" data-action="save_rule"><?php br_concern_choices([], false); ?><button class="btn btn-light mb-3" type="button" id="load-rule">Load current suggestions</button><?php foreach ([1, 2, 3] as $number): ?><label class="form-label">Suggested action <?= $number ?><textarea class="form-control" name="action<?= $number ?>" required maxlength="700" rows="2"></textarea></label><?php endforeach ?><button class="btn btn-primary" type="submit">Save public suggestions</button></form><details class="mt-4"><summary>Restore built-in rules</summary><form method="post" action="api.php" data-action="reset_rule" class="mt-3"><?php br_concern_choices([], false); ?><button class="btn btn-light" type="submit">Restore defaults for this type</button></form></details></section>
<div class="info-callout"><?= br_icon('book') ?> Matches use concern categories and verified records. Previous actions are reference material; the official reviews and approves the recommendation for each new concern.</div>
<div class="knowledge-grid">
  <?php foreach ($groups as $group): $reference = array_values(array_filter($verified, fn($c) => $c['category'] === $group['label']))[0]; ?>
  <section class="knowledge-card"><span class="status status-verified"><?= $group['count'] ?> verified case<?= $group['count'] === 1 ? '' : 's' ?></span><h3><?= h($group['label']) ?></h3><p><?= h($reference['resolution']['notes']) ?></p><div class="small-meta">Latest reference: <?= h($reference['id']) ?> · Verified <?= h(br_date($reference['updatedAt'])) ?></div><a class="link-button" href="<?= h(br_url('complaint.php', ['id' => $reference['id']])) ?>">Read case &amp; timeline <?= br_icon('arrow') ?></a></section>
  <?php endforeach ?>
  <?php if (!$groups): ?><div class="empty-state"><h3>No verified cases yet</h3><p>Official-reviewed resolutions will appear here as reference material.</p></div><?php endif ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
