<?php $inbox = br_store()->notifications($actor['id']); ?>
<details class="notification-menu">
  <summary aria-label="Notifications" class="notification-trigger">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
    <span class="notification-badge" data-unread-count<?= !$inbox['unread'] ? ' hidden' : '' ?>><?= (int)$inbox['unread'] ?></span>
    <span class="visually-hidden"> unread notifications</span>
  </summary>
  <div class="notification-dropdown"><div class="notification-heading"><strong>Notifications</strong><a href="notifications.php">View all</a></div>
    <div data-recent-notifications><?php foreach (array_slice($inbox['items'],0,5) as $notice): ?>
      <a class="notification-item<?= !$notice['is_read'] ? ' unread' : '' ?>" href="<?= h($notice['target_url']) ?>" data-notification-link="<?= (int)$notice['id'] ?>"><strong><?= h($notice['title']) ?></strong><span><?= h($notice['message']) ?></span></a>
    <?php endforeach; if (!$inbox['items']): ?><p class="p-3 mb-0 text-muted">No notifications yet.</p><?php endif ?></div>
    <div class="notification-heading"><button class="link-button" type="button" data-notification-read-all>Mark all as read</button></div>
  </div>
</details>
