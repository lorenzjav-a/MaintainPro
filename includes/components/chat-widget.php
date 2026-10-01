<div class="chat-widget" id="chat-widget">
  <section class="chat-widget-panel panel" id="chat-widget-panel" aria-label="Messages" hidden>
    <div class="chat-widget-header">
      <button class="btn btn-light btn-sm" type="button" id="chat-widget-back" hidden aria-label="Back to conversations">Back</button>
      <div class="chat-widget-heading"><strong id="chat-widget-title">Messages</strong><small id="chat-widget-subtitle">Recent conversations</small></div>
      <button class="btn btn-light btn-sm" type="button" id="chat-widget-minimize" aria-label="Minimize chat">Minimize</button>
      <button class="btn btn-light btn-sm" type="button" id="chat-widget-close" aria-label="Close chat">Close</button>
    </div>
    <div class="chat-widget-search" id="chat-widget-search-wrap"><label class="visually-hidden" for="chat-widget-search">Search conversations<?= in_array($actor['role'],['official','personnel'],true)?' and staff':'' ?></label><input class="form-control" id="chat-widget-search" type="search" maxlength="100" placeholder="<?= in_array($actor['role'],['official','personnel'],true)?'Search concern, title, or staff':'Search concern reference' ?>" autocomplete="off"></div>
    <div class="chat-widget-list" id="chat-widget-list" aria-label="Conversations"></div>
    <div class="chat-widget-active" id="chat-widget-active" hidden>
      <button class="btn btn-light btn-sm" type="button" id="chat-widget-older" hidden>Load older messages</button>
      <div class="chat-widget-messages" id="chat-widget-messages" aria-live="polite"></div>
      <form class="chat-widget-compose" id="chat-widget-compose">
        <label class="form-label" for="chat-widget-visibility" id="chat-widget-visibility-label" hidden>Send as</label>
        <select class="form-select" id="chat-widget-visibility" name="visibility" hidden><option value="reporter">Message to Reporter</option><option value="staff">Internal Note — staff only</option></select>
        <label class="visually-hidden" for="chat-widget-body">Write a message</label>
        <textarea class="form-control" id="chat-widget-body" name="body" rows="2" maxlength="2000" required placeholder="Write a message..."></textarea>
        <button class="btn btn-primary" type="submit">Send Message</button>
      </form>
      <p class="form-text chat-widget-readonly" id="chat-widget-readonly" hidden>This conversation is read-only.</p>
    </div>
    <div class="alert alert-danger chat-widget-error" id="chat-widget-error" role="alert" hidden></div>
  </section>
  <button class="chat-widget-launcher" id="chat-widget-launcher" type="button" aria-label="Open messages" aria-controls="chat-widget-panel" aria-expanded="false">
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-4-.9L3 20l1.4-4.4a8 8 0 1 1 15.6-4.1Z"/><path d="M8 11.5h8M8 14.5h5"/></svg>
    <span class="chat-widget-badge" id="chat-widget-badge"<?= empty($messageCounts['concerns']) && empty($messageCounts['staff'])?' hidden':'' ?>><?= (int)(($messageCounts['concerns'] ?? 0)+($messageCounts['staff'] ?? 0)) ?></span>
  </button>
</div>
<div class="chat-toast panel" id="chat-toast" role="status" hidden><strong>New Message</strong><p id="chat-toast-text"></p><div><button class="btn btn-primary btn-sm" type="button" id="chat-toast-view">View Message</button><button class="btn btn-light btn-sm" type="button" id="chat-toast-dismiss">Dismiss</button></div></div>
