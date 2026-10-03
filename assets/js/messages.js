(function () {
  'use strict';
  var concern = document.getElementById('conversation');
  var staff = document.getElementById('staff-message-panel');
  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (!csrf || (!concern && !staff)) return;
  var panel = concern || staff;
  var thread = document.getElementById(concern ? 'concern-message-thread' : 'staff-message-thread');
  var form = document.getElementById(concern ? 'concern-message-form' : 'staff-message-form');
  var last = Number(panel.dataset.lastId || 0);
  var olderButton = document.getElementById(concern ? 'concern-load-older' : 'staff-load-older');
  var polling = false;
  function element(tag, value, className) {
    var item = document.createElement(tag);
    item.textContent = value;
    if (className) item.className = className;
    return item;
  }
  function appendMessage(message, older) {
    if (thread.querySelector('[data-message-id="' + Number(message.id) + '"]')) return;
    var article = element('article', '', 'message-entry' + (message.visibility === 'staff' ? ' message-internal' : ''));
    article.dataset.messageId = Number(message.id);
    var meta = element('div', '', 'message-meta');
    meta.append(element('strong', message.sender_name), element('span', message.sender_role, 'role-pill'));
    if (message.visibility === 'staff') meta.append(element('span', 'Internal Note', 'status status-assigned'));
    var time = element('time', new Date(Number(message.created_at) * 1000).toLocaleString());
    time.dateTime = new Date(Number(message.created_at) * 1000).toISOString();
    meta.append(time);
    article.append(meta, element('p', message.body));
    var empty = thread.querySelector('.message-empty');
    if (empty) empty.remove();
    if (older) thread.prepend(article);
    else {
      thread.append(article);
      last = Math.max(last, Number(message.id));
      panel.dataset.lastId = String(last);
      thread.scrollTop = thread.scrollHeight;
    }
  }
  async function fetchMessages() {
    if (!thread || polling || document.visibilityState !== 'visible') return;
    if (concern) { var bounds = panel.getBoundingClientRect(); if (bounds.bottom <= 0 || bounds.top >= innerHeight) return; }
    polling = true;
    try {
      var query = new URLSearchParams({view: concern ? 'concern_messages' : 'staff_messages', id: concern ? panel.dataset.concernId : panel.dataset.conversationId, after: String(last)});
      var response = await fetch('api.php?' + query.toString(), {credentials: 'same-origin', cache: 'no-store'});
      if (response.status === 401 || response.status === 403) { clearInterval(timer); return; }
      var result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to load messages.');
      (result.items || []).forEach(function (message) { appendMessage(message, false); });
      if (concern && !result.canSend && form) { form.replaceWith(element('p', 'This conversation is read-only. An official can reopen the concern if more work is needed.', 'form-text mt-3')); form = null; }
      if (concern && result.canSend && !form) window.location.reload();
    } catch (error) { /* Polling retries when the network returns. */ }
    finally { polling = false; }
  }
  if (olderButton) olderButton.addEventListener('click', async function () {
    var first = thread.querySelector('[data-message-id]');
    if (!first) { olderButton.hidden = true; return; }
    olderButton.disabled = true;
    try {
      var query = new URLSearchParams({view: concern ? 'concern_messages' : 'staff_messages', id: concern ? panel.dataset.concernId : panel.dataset.conversationId, before: first.dataset.messageId});
      var response = await fetch('api.php?' + query.toString(), {credentials: 'same-origin', cache: 'no-store'});
      var result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to load messages.');
      (result.items || []).forEach(function (message) { appendMessage(message, true); });
      olderButton.hidden = result.items.length < 50;
    } catch (error) { Swal.fire({icon: 'error', text: error.message}); }
    finally { olderButton.disabled = false; }
  });
  async function post(action, id, data) {
    var response = await fetch('api.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf.content}, body: JSON.stringify({action: action, id: id, data: data})});
    var result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to send message.');
    return result;
  }
  document.querySelectorAll('.staff-contact-result').forEach(function (contact) {
    contact.addEventListener('click', async function () {
      if (contact.disabled) return;
      contact.disabled = true;
      try {
        var result = await post('create_staff_conversation', '', {recipientId: contact.dataset.contactId});
        window.location.assign(result.redirect);
      } catch (error) {
        Swal.fire({icon: 'error', text: error.message});
        contact.disabled = false;
      }
    });
  });
  if (form) form.addEventListener('submit', async function (event) {
    event.preventDefault();
    var button = form.querySelector('button[type=submit]');
    button.disabled = true;
    try {
      var data = {body: form.elements.body.value};
      if (concern && form.elements.visibility) data.visibility = form.elements.visibility.value;
      var sent = await post(concern ? 'send_concern_message' : 'send_staff_message', concern ? panel.dataset.concernId : panel.dataset.conversationId, data);
      appendMessage(sent, false);
      if (staff) {
        var active = document.querySelector('.staff-conversation-link.active');
        if (active) { var preview = active.querySelector('.staff-message-preview'), time = active.querySelector('time'); if (preview) preview.textContent = data.body.slice(0, 90); if (time) time.textContent = new Date().toLocaleString(); }
      }
      form.elements.body.value = '';
      await fetchMessages();
    } catch (error) { Swal.fire({icon: 'error', text: error.message}); }
    finally { button.disabled = false; }
  });
  var create = document.getElementById('staff-conversation-form');
  if (create) create.addEventListener('submit', async function (event) {
    event.preventDefault();
    var button = create.querySelector('button[type=submit]');
    button.disabled = true;
    try {
      var data = {}, fields = new FormData(create);
      fields.forEach(function (value, key) { data[key] = String(value).trim(); });
      var result = await post('create_staff_conversation', '', data);
      window.location.assign(result.redirect);
    } catch (error) { Swal.fire({icon: 'error', text: error.message}); button.disabled = false; }
  });
  var timer = setInterval(fetchMessages, 15000);
  if (concern && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) { if (entries.some(function (entry) { return entry.isIntersecting; })) fetchMessages(); });
    observer.observe(concern);
  }
  document.addEventListener('visibilitychange', fetchMessages);
}());
