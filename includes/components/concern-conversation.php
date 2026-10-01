<section class="case-section message-panel" id="conversation" data-concern-id="<?= h($c['id']) ?>" data-last-id="<?= (int)$conversation['lastId'] ?>">
  <div class="d-flex align-items-center justify-content-between gap-2"><h3><?= br_icon('inbox') ?>Conversation</h3><?php if ($conversation['unread']): ?><span class="count-pill"><?= (int)$conversation['unread'] ?> new</span><?php endif ?></div>
  <p class="form-text">Messages stay with this concern. Staff-only notes are never shown to reporters.</p>
  <button class="btn btn-light mb-2" type="button" id="concern-load-older"<?= count($conversation['items'])===50?'':' hidden' ?>>Load older messages</button>
  <div class="message-thread" id="concern-message-thread" aria-live="polite">
  <?php foreach ($conversation['items'] as $message): ?>
    <article class="message-entry<?= $message['visibility']==='staff' ? ' message-internal' : '' ?>" data-message-id="<?= (int)$message['id'] ?>">
      <div class="message-meta"><strong><?= h($message['sender_name']) ?></strong><span class="role-pill"><?= h(ucfirst($message['sender_role'])) ?></span><?php if ($message['visibility']==='staff'): ?><span class="status status-assigned">Internal Note</span><?php endif ?><time datetime="<?= h(date(DATE_ATOM,(int)$message['created_at'])) ?>"><?= h(date('M j, Y · g:i A',(int)$message['created_at'])) ?></time></div>
      <p><?= nl2br(h($message['body'])) ?></p>
    </article>
  <?php endforeach ?>
  <?php if (!$conversation['items']): ?><p class="form-text message-empty">No messages yet. Start the conversation by sending an update.</p><?php endif ?>
  </div>
  <?php if ($conversation['canSend']): ?>
  <form id="concern-message-form" class="mt-3">
    <?php if ($conversation['canWriteInternal']): ?><label class="form-label" for="concern-message-visibility">Send as</label><select class="form-select mb-3" id="concern-message-visibility" name="visibility"><option value="reporter">Message to Reporter</option><option value="staff">Internal Note — staff only</option></select><?php endif ?>
    <label class="form-label" for="concern-message-body">Write a message</label><textarea class="form-control mb-3" id="concern-message-body" name="body" rows="3" maxlength="2000" required placeholder="Write a message..."></textarea>
    <button class="btn btn-primary" type="submit">Send Message</button>
  </form>
  <?php else: ?><p class="form-text mt-3">This conversation is read-only. An official can reopen the concern if more work is needed.</p><?php endif ?>
</section>
