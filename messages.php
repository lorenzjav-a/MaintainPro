<?php
declare(strict_types=1);
require __DIR__.'/includes/page.php';
$context=br_page('messages',['official','personnel']);
extract($context);
$contacts=br_store()->staffContacts($actor['id']);
$search=br_query('q');
if (mb_strlen($search)>100) br_page_error($context,422,'Search is too long','Use a shorter conversation search.');
$contactMatches=$search!==''?br_store()->staffContacts($actor['id'],$search):[];
$selected=br_query('id');
$thread=null;
if ($selected!=='') {
    if (!preg_match('/\A[1-9][0-9]{0,17}\z/',$selected)) br_page_error($context,404,'Conversation unavailable','Choose a staff conversation.');
    try { $thread=br_store()->staffConversationPage($actor['id'],(int)$selected); }
    catch (DomainException) { br_page_error($context,404,'Conversation unavailable','Choose a staff conversation available to your account.'); }
}
$staffInbox=br_store()->staffInbox($actor['id'],$search);
require __DIR__.'/includes/layout/header.php';
br_heading('Staff messages','Coordinate concern and maintenance work with other barangay staff.');
?>
<div class="row g-4 staff-message-layout<?= $thread?' has-thread':'' ?>">
  <div class="col-lg-4">
    <section class="panel"><div class="panel-header"><h2 class="panel-title">Conversations</h2></div><form class="staff-message-search" method="get" action="messages.php"><label class="visually-hidden" for="staff-message-search">Search conversations and staff</label><input class="form-control" id="staff-message-search" type="search" name="q" maxlength="100" value="<?= h($search) ?>" placeholder="Search title, person, or work item"><button class="btn btn-light" type="submit">Search</button></form><div class="panel-body staff-message-inbox">
      <?php if ($contactMatches): ?><p class="staff-search-group">Staff</p><?php foreach ($contactMatches as $contact): ?><button class="staff-conversation-link staff-contact-result" type="button" data-contact-id="<?= h($contact['id']) ?>"><strong><?= h($contact['name']) ?></strong><small><?= h(br_role($contact['role']).($contact['team']?' · '.$contact['team']:'')) ?></small><small>Open or start a direct conversation</small></button><?php endforeach ?><?php endif ?>
      <?php if ($staffInbox && $contactMatches): ?><p class="staff-search-group">Conversations</p><?php endif ?>
      <?php foreach ($staffInbox as $item): ?><a class="staff-conversation-link<?= $thread && (int)$item['id']===(int)$thread['conversation']['id']?' active':'' ?>" href="<?= h(br_url('messages.php',['id'=>$item['id']])) ?>"><strong><?= h($item['title']) ?></strong><?php if ($item['unread']): ?><span class="count-pill"><?= (int)$item['unread'] ?> unread</span><?php endif ?><small><?= h($item['participants']) ?></small><small class="staff-message-preview"><?= h($item['last_body'] ? mb_strimwidth($item['last_body'],0,90,'…') : 'No messages yet.') ?></small><time><?= h(date('M j, g:i A',(int)$item['updated_at'])) ?></time></a><?php endforeach ?>
      <?php if (!$staffInbox && !$contactMatches): ?><p class="form-text p-3 mb-0"><?= $search!==''?'No matching conversations or staff.':'No conversations yet.' ?></p><?php endif ?>
    </div></section>
    <section class="panel mt-4"><div class="panel-header"><h2 class="panel-title">Start a conversation</h2></div><div class="panel-body">
      <form id="staff-conversation-form">
        <label class="form-label" for="staff-recipient">Staff member</label><select class="form-select mb-3" id="staff-recipient" name="recipientId" required><option value="">Choose a staff member</option><?php foreach ($contacts as $contact): ?><option value="<?= h($contact['id']) ?>"><?= h($contact['name'].' · '.br_role($contact['role']).($contact['team']?' / '.$contact['team']:'')) ?></option><?php endforeach ?></select>
        <label class="form-label" for="staff-title">Conversation title (optional)</label><input class="form-control mb-3" id="staff-title" name="title" maxlength="180" placeholder="Work coordination">
        <details class="mb-3"><summary>Link to a work item (optional)</summary><div class="pt-3">
        <label class="form-label" for="staff-concern">Concern reference (optional)</label><input class="form-control mb-3" id="staff-concern" name="concernId" maxlength="64" placeholder="CON-2026-000123">
        <label class="form-label" for="staff-plan">Action plan ID (optional)</label><input class="form-control mb-3" id="staff-plan" name="planId" inputmode="numeric" pattern="[0-9]*" placeholder="5">
        <p class="form-text">Choose at most one work item. Both staff members must be authorized for it.</p>
        </div></details>
        <button class="btn btn-primary" type="submit">Create Conversation</button>
      </form>
    </div></section>
  </div>
  <div class="col-lg-8">
    <section class="panel" id="staff-message-panel"<?= $thread ? ' data-conversation-id="'.(int)$thread['conversation']['id'].'" data-last-id="'.(int)$thread['lastId'].'"' : '' ?>>
      <?php if ($thread): $item=$thread['conversation']; ?>
      <div class="panel-header"><div><h2 class="panel-title"><?= h($item['title']) ?></h2><p class="panel-subtitle"><?= h(implode(', ',array_column($thread['members'],'name'))) ?></p><?php if ($item['related_concern_id']): ?><a href="<?= h(br_url('concern.php',['id'=>$item['related_concern_id']])) ?>">Related concern <?= h($item['related_concern_id']) ?></a><?php elseif ($item['related_action_plan_id']): ?><a href="<?= h(br_url('action-plans.php',['id'=>$item['related_action_plan_id']])) ?>">Related action plan #<?= (int)$item['related_action_plan_id'] ?></a><?php endif ?></div></div>
      <div class="panel-body">
        <button class="btn btn-light mb-2" type="button" id="staff-load-older"<?= count($thread['items'])===50?'':' hidden' ?>>Load older messages</button>
        <div class="message-thread" id="staff-message-thread" aria-live="polite">
          <?php foreach ($thread['items'] as $message): ?><article class="message-entry" data-message-id="<?= (int)$message['id'] ?>"><div class="message-meta"><strong><?= h($message['sender_name']) ?></strong><span class="role-pill"><?= h(br_role($message['sender_role'])) ?></span><time datetime="<?= h(date(DATE_ATOM,(int)$message['created_at'])) ?>"><?= h(date('M j, Y · g:i A',(int)$message['created_at'])) ?></time></div><p><?= nl2br(h($message['body'])) ?></p></article><?php endforeach ?>
          <?php if (!$thread['items']): ?><p class="form-text message-empty">No messages yet. Start the conversation by sending an update.</p><?php endif ?>
        </div>
        <form id="staff-message-form" class="mt-3"><label class="form-label" for="staff-message-body">Write a message</label><textarea class="form-control mb-3" id="staff-message-body" name="body" rows="3" maxlength="2000" required placeholder="Write a message..."></textarea><button class="btn btn-primary" type="submit">Send Message</button></form>
      </div>
      <?php else: ?><div class="panel-body"><p class="form-text mb-0">Choose a conversation or start one with a staff member.</p></div><?php endif ?>
    </section>
  </div>
</div>
<?php require __DIR__.'/includes/layout/footer.php'; ?>
