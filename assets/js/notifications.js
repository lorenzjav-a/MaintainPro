(function () {
  'use strict';
  function update(inbox) {
    document.querySelectorAll('[data-unread-count]').forEach(function (badge) {
      badge.textContent = inbox.unread; badge.hidden = !inbox.unread;
    });
    var list = document.querySelector('[data-recent-notifications]');
    if (!list) return;
    list.replaceChildren();
    inbox.items.slice(0, 5).forEach(function (notice) {
      var link = document.createElement('a'), title = document.createElement('strong'), message = document.createElement('span');
      link.className = 'notification-item' + (Number(notice.is_read) ? '' : ' unread');
      link.href = notice.target_url; link.dataset.notificationLink = notice.id;
      title.textContent = notice.title; message.textContent = notice.message;
      link.append(title, message); list.append(link);
    });
    if (!inbox.items.length) { var empty = document.createElement('p'); empty.className = 'p-3 mb-0 text-muted'; empty.textContent = 'No notifications yet.'; list.append(empty); }
  }
  function read(id) {
    return fetch('api.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify({action: id ? 'read_notification' : 'read_all_notifications', id: id || ''})
    }).then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.error || 'Unable to update notification.'); return body; }); })
      .then(function (body) {
        update(body);
        document.querySelectorAll('[data-notification-row]').forEach(function (row) {
          if (id && row.dataset.notificationRow !== id) return;
          row.classList.remove('unread'); row.querySelector('[data-read-label]').textContent = 'Read';
          var button = row.querySelector('[data-notification-read]'); if (button) button.remove();
        });
      });
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-notification-link]');
    var button = event.target.closest('[data-notification-read], [data-notification-read-all]');
    var accept = event.target.closest('[data-accept-priority]');
    if (accept) {
      var form = accept.closest('form'), select = form.querySelector('[name="priority"]');
      select.value = accept.dataset.acceptPriority; select.focus();
      var feedback = form.querySelector('[data-priority-feedback]');
      if (feedback) feedback.textContent = 'Recommendation selected. Save this form to confirm the official priority.';
    }
    if (!link && !button) return;
    if (link && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) return;
    event.preventDefault();
    var id = link ? link.dataset.notificationLink : button.dataset.notificationRead;
    if (button) button.disabled = true;
    read(id).then(function () { if (link) window.location.assign(link.href); })
      .catch(function (error) { Swal.fire({icon:'error', title:'Unable to update notification', text:error.message}); })
      .finally(function () { if (button) button.disabled = false; });
  });
  // Small authenticated response; no concern payloads, addresses or images in polling.
  setInterval(function () {
    if (document.hidden) return;
    fetch('api.php?view=notifications', {credentials:'same-origin'}).then(function (response) {
      if (response.ok) return response.json();
    }).then(function (body) { if (body) update(body); }).catch(function () {});
  }, 60000);
  document.addEventListener('click', function (event) {
    var menu = document.querySelector('.notification-menu');
    if (menu && !menu.contains(event.target)) menu.open = false;
  });
  document.addEventListener('keydown', function (event) {
    var menu = document.querySelector('.notification-menu');
    if (event.key === 'Escape' && menu && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
  });
  document.addEventListener('change', function (event) {
    var form = event.target.closest('form[data-action="edit"]');
    if (!form || ['category','concernType','keyPoints'].indexOf(event.target.name) < 0) return;
    var accept = form.querySelector('[data-accept-priority]');
    if (accept) {
      accept.disabled = true;
      form.querySelector('[data-priority-feedback]').textContent = 'Selections changed. Save the concern information to calculate a fresh recommendation, then review the priority again.';
    }
  });
})();
