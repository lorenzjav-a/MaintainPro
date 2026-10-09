<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-layout.php';
$actor = br_actor();
if ($actor && !$actor['must_change_password']) {
    require_once __DIR__ . '/includes/page.php';
    extract(br_page('track'));
    require __DIR__ . '/includes/layout/header.php';
} else {
    br_public_header('Track Concern');
}
br_heading('Track Concern', 'Follow your reported concerns or use the reference number and private code saved after guest submission.'); ?>
<?php if ($actor && !$actor['must_change_password']): ?>
<section class="panel p-4 public-form-panel mb-4" aria-labelledby="account-tracking-heading">
  <h2 class="section-title" id="account-tracking-heading">Concerns reported with your account</h2>
  <p class="form-text">View progress, updates, and messages for your own reports, including those submitted anonymously. No tracking code is needed.</p>
  <a class="btn btn-primary" href="complaints.php?scope=mine"><?= br_icon('clipboardList') ?>My Reported Concerns</a>
</section>
<?php endif ?>
<section class="panel p-4 public-form-panel" aria-labelledby="private-tracking-heading">
  <h2 class="section-title" id="private-tracking-heading">Track with private details</h2>
  <p class="form-text">For a concern submitted as a guest, enter both values from your submission receipt. No account is required.</p>
  <form id="public-track" method="post" action="public-api.php"><label class="form-label">Concern Reference Number<input class="form-control" name="reference" placeholder="CON-2026-000123" required maxlength="64" autocomplete="off"></label><label class="form-label">Tracking Code<input class="form-control tracking-code" name="trackingCode" required pattern="[a-f0-9]{48}" maxlength="48" autocomplete="off"></label><div class="alert alert-danger" id="public-error" role="alert" hidden></div><button type="submit" class="btn btn-primary">View status</button></form>
</section>
<section class="panel p-4 mt-4 public-form-panel" id="tracking-result" hidden aria-live="polite"></section>
<section class="panel p-4 mt-4 public-form-panel message-panel" id="guest-conversation" hidden aria-labelledby="guest-conversation-heading">
  <div class="d-flex align-items-center gap-2"><h2 class="section-title flex-grow-1 mb-0" id="guest-conversation-heading">Conversation</h2><button class="btn btn-light btn-sm" type="button" id="guest-chat-minimize">Minimize</button><button class="btn btn-light btn-sm" type="button" id="guest-chat-close">Close</button></div>
  <p class="form-text">Messages stay with this concern. Keep your tracking code private.</p>
  <button class="btn btn-light mb-2" type="button" id="guest-load-older" hidden>Load older messages</button>
  <div class="message-thread" id="guest-message-thread" aria-live="polite"></div>
  <p class="form-text" id="guest-message-closed" hidden>This conversation is read-only. An official can reopen the concern if more work is needed.</p>
  <form id="guest-message-form" class="mt-3"><label class="form-label" for="guest-message-body">Write a message</label><textarea class="form-control mb-3" id="guest-message-body" name="body" rows="3" maxlength="2000" required placeholder="Write a message..."></textarea><button class="btn btn-primary" type="submit">Send Message</button></form>
  <div class="alert alert-danger mt-3" id="guest-message-error" role="alert" hidden></div>
</section>
<button class="chat-widget-launcher guest-chat-launcher" id="guest-chat-launcher" type="button" aria-label="Open concern conversation" aria-controls="guest-conversation" aria-expanded="false" hidden><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-4-.9L3 20l1.4-4.4a8 8 0 1 1 15.6-4.1Z"/><path d="M8 11.5h8M8 14.5h5"/></svg><span class="chat-widget-badge" id="guest-chat-badge" hidden>0</span></button>
<span class="guest-chat-hint" id="guest-chat-hint" hidden>New messages available</span>
<section class="panel p-4 mt-4 public-form-panel followup-panel" id="followup-panel" hidden aria-labelledby="followup-heading">
  <h2 class="section-title" id="followup-heading">Send Additional Information</h2>
  <p class="form-text">Your response is added to the concern history and cannot be edited after submission. Do not include your name or contact information.</p>
  <form id="public-followup" method="post" action="public-api.php">
    <label class="form-label" for="followup-description">Additional description / information<textarea class="form-control" id="followup-description" name="description" rows="4" maxlength="4000" required placeholder="Answer the barangay’s request with the details you know."></textarea></label>
    <?php br_upload('followup-photo', 'Additional photo / evidence'); ?>
    <p class="form-text">Only take a photo when it is safe. Avoid faces, personal documents, and contact details.</p>
    <div class="alert alert-danger" id="followup-error" role="alert" hidden></div>
    <div class="alert alert-success" id="followup-success" role="status" hidden>Your information was submitted for reassessment.</div>
    <button class="btn btn-primary" type="submit">Submit Additional Information</button>
  </form>
</section>
<noscript><p class="alert alert-warning">Enable JavaScript to securely check your tracking details.</p></noscript>
<?php if ($actor && !$actor['must_change_password']) require __DIR__ . '/includes/layout/footer.php'; else br_public_footer(); ?>
