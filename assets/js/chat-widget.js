(function () {
  'use strict';
  var root = document.getElementById('chat-widget');
  if (!root) return;
  var panel = document.getElementById('chat-widget-panel'), launcher = document.getElementById('chat-widget-launcher');
  var list = document.getElementById('chat-widget-list'), activeArea = document.getElementById('chat-widget-active');
  var search = document.getElementById('chat-widget-search'), searchWrap = document.getElementById('chat-widget-search-wrap');
  var messages = document.getElementById('chat-widget-messages'), form = document.getElementById('chat-widget-compose');
  var older = document.getElementById('chat-widget-older'), badge = document.getElementById('chat-widget-badge');
  var errorBox = document.getElementById('chat-widget-error'), title = document.getElementById('chat-widget-title');
  var subtitle = document.getElementById('chat-widget-subtitle'), back = document.getElementById('chat-widget-back');
  var visibility = document.getElementById('chat-widget-visibility'), visibilityLabel = document.getElementById('chat-widget-visibility-label');
  var toast = document.getElementById('chat-toast'), toastText = document.getElementById('chat-toast-text');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var overview = null, selected = null, last = 0, busy = false, lastNoticeId = null, toastNotice = null, visibleRows = 6;
  function make(tag, value, className) {
    var item = document.createElement(tag); item.textContent = value;
    if (className) item.className = className;
    return item;
  }
  function initials(value) {
    return value.trim().split(/\s+/).slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase() || '?';
  }
  function listDate(value) {
    if (!value) return '';
    var date = new Date(value), today = new Date();
    if (date.toDateString() === today.toDateString()) return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
    return date.toLocaleDateString([], {month: 'short', day: 'numeric'});
  }
  function fail(error) { errorBox.textContent = error.message || 'Unable to load messages.'; errorBox.hidden = false; }
  function target(notice) {
    try {
      var url = new URL(notice.target_url, location.href), page = url.pathname.split('/').pop(), id = url.searchParams.get('id');
      if (!id || url.origin !== location.origin) return null;
      if (page === 'messages.php' && /^\d+$/.test(id)) return {kind: 'staff', id: id};
      if ((page === 'concern.php' || page === 'complaint.php') && /^CON-[0-9]{4}-[0-9]{6,}$/.test(id)) return {kind: 'concern', id: id};
    } catch (error) { return null; }
    return null;
  }
  function showToast(notice) {
    if (!target(notice)) return;
    toastNotice = notice;
    toastText.textContent = notice.message || 'You received a new message.';
    toast.hidden = false;
  }
  function renderList() {
    if (!overview || selected) return;
    var previousTop = list.scrollTop;
    list.replaceChildren();
    var rows = [];
    (overview.concerns || []).forEach(function (item) {
      rows.push({kind: 'concern', id: item.id, title: item.id, subtitle: item.last_sender ? item.last_sender + ' · ' + (item.last_sender_role || 'Reporter') : 'Concern conversation',
        preview: item.last_body || 'No messages yet.', unread: Number(item.unread || 0), date: item.last_message_at ? Number(item.last_message_at) * 1000 : Date.parse(item.updated_at) || 0});
    });
    (overview.staff || []).forEach(function (item) {
      rows.push({kind: 'staff', id: String(item.id), title: item.title, subtitle: item.participants || 'Staff conversation',
        preview: item.last_body || 'No messages yet.', unread: Number(item.unread || 0), date: Number(item.updated_at) * 1000});
    });
    (overview.people || []).forEach(function (person) {
      rows.push({kind: 'person', id: person.id, title: person.name,
        subtitle: person.role === 'official' ? 'Official' : 'Personnel' + (person.team ? ' · ' + person.team : ''),
        preview: 'Open or start a direct chat', unread: 0, date: 0});
    });
    rows.sort(function (a, b) { return Number(b.kind === 'person') - Number(a.kind === 'person') || b.date - a.date; });
    rows.slice(0, visibleRows).forEach(function (row) {
      var button = make('button', '', 'chat-widget-list-item' + (row.kind === 'person' ? ' chat-widget-person' : '')); button.type = 'button';
      button.dataset.kind = row.kind;
      button.setAttribute('aria-label', 'Open ' + row.title + '. ' + row.preview.slice(0, 100));
      var avatar = make('span', row.kind === 'concern' ? '#' : initials(row.title), 'chat-widget-avatar');
      var content = make('span', '', 'chat-widget-row-content'), headingRow = make('span', '', 'chat-widget-row-heading');
      var heading = make('strong', row.title), context = make('small', row.subtitle, 'chat-widget-row-context'), preview = make('small', row.preview.slice(0, 100), 'chat-widget-row-preview');
      headingRow.append(heading);
      if (row.date) {
        var time = make('time', listDate(row.date));
        time.dateTime = new Date(row.date).toISOString();
        headingRow.append(time);
      }
      content.append(headingRow, context, preview);
      button.append(avatar, content);
      if (row.unread) button.append(make('span', String(row.unread), 'chat-widget-row-unread'));
      button.addEventListener('click', function () { if (row.kind === 'person') startContact(row.id, button); else open(row.kind, row.id); });
      list.append(button);
    });
    if (rows.length > visibleRows) {
      var more = make('button', 'Show ' + Math.min(6, rows.length - visibleRows) + ' more results', 'chat-widget-more');
      more.type = 'button';
      more.addEventListener('click', function () {
        var firstNew = visibleRows;
        visibleRows += 6;
        renderList();
        var next = list.querySelectorAll('.chat-widget-list-item')[firstNew];
        if (next) next.focus();
      });
      list.append(more);
    }
    if (!rows.length) list.append(make('p', search.value.trim() ? 'No matching conversations or staff.' : 'No conversations yet. Open a concern to start one.', 'chat-widget-empty'));
    list.scrollTop = previousTop;
  }
  async function startContact(id, button) {
    if (button.disabled) return;
    button.disabled = true; errorBox.hidden = true;
    try {
      var response = await fetch('api.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({action: 'create_staff_conversation', data: {recipientId: id}})});
      var result = await response.json();
      if (!response.ok || !result.id) throw new Error(result.error || 'Unable to open staff chat.');
      await fetchOverview();
      open('staff', String(result.id));
    } catch (error) { fail(error); }
    finally { button.disabled = false; }
  }
  async function fetchOverview() {
    if (document.hidden) return;
    try {
      var query = search.value.trim();
      var params = new URLSearchParams({view: 'chat_overview'}); if (query) params.set('q', query);
      var response = await fetch('api.php?' + params.toString(), {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) return;
      var result = await response.json();
      if (query !== search.value.trim()) return;
      overview = result;
      var total = Number(overview.counts.concerns || 0) + Number(overview.counts.staff || 0);
      badge.textContent = String(total); badge.hidden = total === 0;
      launcher.setAttribute('aria-label', total ? 'Open messages, ' + total + ' unread' : 'Open messages');
      document.dispatchEvent(new CustomEvent('maintainpro:notifications', {detail: overview.notifications}));
      var chatNotices = (overview.notifications.items || []).filter(function (notice) { return notice.type === 'concern_message' || (notice.type === 'staff_message' && notice.title !== 'New staff conversation'); });
      var newest = chatNotices.reduce(function (id, notice) { return Math.max(id, Number(notice.id)); }, 0);
      if (lastNoticeId !== null && newest > lastNoticeId) {
        var fresh = chatNotices.find(function (notice) { return Number(notice.id) === newest && !Number(notice.is_read); });
        if (fresh) showToast(fresh);
      }
      lastNoticeId = Math.max(lastNoticeId || 0, newest);
      renderList();
    } catch (error) { /* Polling resumes when the connection returns. */ }
  }
  function appendMessage(message, prepend) {
    if (messages.querySelector('[data-message-id="' + Number(message.id) + '"]')) return;
    var card = make('article', '', 'message-entry' + (message.visibility === 'staff' ? ' message-internal' : ''));
    card.dataset.messageId = Number(message.id);
    var meta = make('div', '', 'message-meta');
    meta.append(make('strong', message.sender_name), make('span', message.sender_role, 'role-pill'));
    if (message.visibility === 'staff') meta.append(make('span', 'Internal Note', 'status status-assigned'));
    meta.append(make('time', new Date(Number(message.created_at) * 1000).toLocaleString()));
    card.append(meta, make('p', message.body));
    var empty = messages.querySelector('.message-empty'); if (empty) empty.remove();
    if (prepend) messages.prepend(card);
    else { messages.append(card); last = Math.max(last, Number(message.id)); messages.scrollTop = messages.scrollHeight; }
  }
  async function fetchThread(initial) {
    if (!selected || busy || document.hidden) return;
    busy = true;
    try {
      var query = new URLSearchParams({view: selected.kind === 'staff' ? 'staff_messages' : 'concern_messages', id: selected.id, after: String(initial ? 0 : last)});
      var response = await fetch('api.php?' + query.toString(), {credentials: 'same-origin', cache: 'no-store'});
      var result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Conversation unavailable.');
      if (initial) { messages.replaceChildren(); last = 0; }
      (result.items || []).forEach(function (message) { appendMessage(message, false); });
      if (initial && !result.items.length) messages.append(make('p', 'No messages yet. Start the conversation by sending an update.', 'chat-widget-empty message-empty'));
      if (initial) older.hidden = result.items.length < 50;
      var canSend = selected.kind === 'staff' || !!result.canSend;
      form.hidden = !canSend;
      document.getElementById('chat-widget-readonly').hidden = canSend;
      var internal = selected.kind === 'concern' && !!result.canWriteInternal;
      visibility.hidden = !internal; visibilityLabel.hidden = !internal;
      errorBox.hidden = true;
      await fetchOverview();
    } catch (error) { fail(error); }
    finally { busy = false; }
  }
  function setOpen(open) { panel.hidden = !open; launcher.setAttribute('aria-expanded', open ? 'true' : 'false'); if (!open) launcher.focus(); }
  function showList() {
    selected = null; last = 0; title.textContent = 'Messages'; subtitle.textContent = 'Recent conversations';
    panel.classList.remove('has-active');
    back.hidden = true; searchWrap.hidden = false; list.hidden = false; activeArea.hidden = true; errorBox.hidden = true; renderList();
    if (!panel.hidden) requestAnimationFrame(function () { search.focus(); });
  }
  function open(kind, id) {
    setOpen(true);
    if (!kind || !id) { showList(); fetchOverview(); return; }
    selected = {kind: kind, id: String(id)};
    panel.classList.add('has-active');
    var row = kind === 'staff' ? (overview && overview.staff || []).find(function (item) { return String(item.id) === String(id); })
      : (overview && overview.concerns || []).find(function (item) { return item.id === id; });
    title.textContent = row ? (row.title || row.id) : (kind === 'concern' ? id : 'Staff conversation');
    subtitle.textContent = kind === 'concern' ? 'Concern ' + id : (row && row.participants || 'Staff coordination');
    back.hidden = false; searchWrap.hidden = true; list.hidden = true; activeArea.hidden = false; errorBox.hidden = true;
    messages.replaceChildren(); last = 0; older.hidden = true; form.hidden = true;
    requestAnimationFrame(function () { back.focus(); });
    fetchThread(true);
  }
  window.MaintainProChat = {open: open, target: target};
  var searchTimer;
  search.addEventListener('input', function () { visibleRows = 6; list.scrollTop = 0; clearTimeout(searchTimer); searchTimer = setTimeout(fetchOverview, 250); });
  launcher.addEventListener('click', function () { if (!panel.hidden) { setOpen(false); return; } if (selected) { setOpen(true); fetchThread(false); } else open(); });
  back.addEventListener('click', showList);
  document.getElementById('chat-widget-minimize').addEventListener('click', function () { setOpen(false); });
  document.getElementById('chat-widget-close').addEventListener('click', function () { showList(); setOpen(false); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) setOpen(false);
  });
  older.addEventListener('click', async function () {
    var first = messages.querySelector('[data-message-id]'); if (!first || !selected) return;
    older.disabled = true;
    try {
      var query = new URLSearchParams({view: selected.kind === 'staff' ? 'staff_messages' : 'concern_messages', id: selected.id, before: first.dataset.messageId});
      var response = await fetch('api.php?' + query.toString(), {credentials: 'same-origin', cache: 'no-store'});
      var result = await response.json(); if (!response.ok) throw new Error(result.error || 'Unable to load older messages.');
      (result.items || []).forEach(function (message) { appendMessage(message, true); });
      older.hidden = result.items.length < 50;
    } catch (error) { fail(error); }
    finally { older.disabled = false; }
  });
  form.addEventListener('submit', async function (event) {
    event.preventDefault(); if (!selected || busy) return;
    var button = form.querySelector('button[type=submit]'); button.disabled = true;
    try {
      var data = {body: form.elements.body.value};
      if (selected.kind === 'concern' && !visibility.hidden) data.visibility = visibility.value;
      var response = await fetch('api.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({action: selected.kind === 'staff' ? 'send_staff_message' : 'send_concern_message', id: selected.id, data: data})});
      var result = await response.json(); if (!response.ok) throw new Error(result.error || 'Unable to send message.');
      form.elements.body.value = ''; errorBox.hidden = true; await fetchThread(false);
    } catch (error) { fail(error); }
    finally { button.disabled = false; }
  });
  document.getElementById('chat-toast-view').addEventListener('click', async function () {
    var notice = toastNotice, destination = notice && target(notice); toast.hidden = true;
    if (!destination) return;
    try { if (window.MaintainProNotifications) await window.MaintainProNotifications.read(String(notice.id)); } catch (error) { /* Thread access is checked separately. */ }
    open(destination.kind,destination.id);
  });
  document.getElementById('chat-toast-dismiss').addEventListener('click', function () { toast.hidden = true; toastNotice = null; });
  setInterval(function () { if (document.hidden) return; fetchOverview(); if (selected && !panel.hidden) fetchThread(false); }, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { fetchOverview(); if (selected && !panel.hidden) fetchThread(false); } });
  fetchOverview().then(function () {
    var params = new URLSearchParams(location.search);
    if (params.get('open_chat') === '1' && params.get('id')) {
      var page = location.pathname.split('/').pop();
      if (page === 'messages.php') open('staff',params.get('id'));
      else if (page === 'concern.php' || page === 'complaint.php') open('concern',params.get('id'));
    }
  });
}());
