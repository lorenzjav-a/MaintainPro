<?php
declare(strict_types=1);
require __DIR__ . '/includes/page.php';
extract(br_page('notifications'));
$before = max(0,(int)br_query('before'));
$inboxPage = br_store()->notifications($actor['id'],$before);
require __DIR__ . '/includes/layout/header.php';
br_heading('Notifications','Updates about your concerns and work assignments.','<button type="button" class="btn btn-light" data-notification-read-all>Mark all as read</button>');
?>
<section class="panel notification-center">
<?php foreach ($inboxPage['items'] as $notice): ?>
  <article class="notification-row<?= !$notice['is_read'] ? ' unread' : '' ?>" data-notification-row="<?= (int)$notice['id'] ?>">
    <div><a href="<?= h($notice['target_url']) ?>" data-notification-link="<?= (int)$notice['id'] ?>"><strong><?= h($notice['title']) ?></strong></a><p><?= h($notice['message']) ?></p><small><?= h(date('M j, Y · g:i A',(int)$notice['created_at'])) ?> · <span data-read-label><?= $notice['is_read'] ? 'Read' : 'Unread' ?></span></small></div>
    <?php if (!$notice['is_read']): ?><button type="button" class="btn btn-light btn-sm" data-notification-read="<?= (int)$notice['id'] ?>">Mark as read</button><?php endif ?>
  </article>
<?php endforeach; if (!$inboxPage['items']): ?><p class="p-4 mb-0 text-muted">No notifications here yet.</p><?php endif ?>
</section>
<div class="d-flex gap-3 mt-3"><?php if ($before): ?><a href="notifications.php">Latest notifications</a><?php endif; if (count($inboxPage['items']) === 30): ?><a href="<?= h(br_url('notifications.php',['before' => end($inboxPage['items'])['id']])) ?>">Older notifications</a><?php endif ?></div>
<p class="form-text mt-3">If an assignment has changed, its notification opens your current work queue. Access to concern details still follows your account permissions.</p>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
