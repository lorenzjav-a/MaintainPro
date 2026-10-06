(function () {
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var busy = false, navigating = false;
  var Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 3800, timerProgressBar: true});
  var workflowPositionKey = 'maintainpro:concern-workflow-position';

  function rememberWorkflowPosition(concernId, action, form) {
    var record = document.querySelector('[data-case-id]');
    if (!record || !concernId || record.dataset.caseId !== concernId) return;
    var section = form.closest('[data-workflow-section]');
    try {
      sessionStorage.setItem(workflowPositionKey, JSON.stringify({
        concernId: concernId,
        action: action,
        section: section ? section.dataset.workflowSection : '',
        scrollY: Math.max(0, Math.round(window.scrollY)),
        savedAt: Date.now()
      }));
    } catch (error) {
      // Scroll restoration is optional when tab storage is unavailable.
    }
  }

  function restoreWorkflowPosition() {
    var record = document.querySelector('[data-case-id]');
    if (!record) return;
    var saved;
    try { saved = JSON.parse(sessionStorage.getItem(workflowPositionKey) || 'null'); }
    catch (error) { sessionStorage.removeItem(workflowPositionKey); return; }
    if (!saved) return;
    var queryAction = new URLSearchParams(window.location.search).get('saved');
    if (saved.concernId !== record.dataset.caseId || saved.action !== queryAction || Date.now() - Number(saved.savedAt) > 120000) {
      sessionStorage.removeItem(workflowPositionKey);
      return;
    }
    sessionStorage.removeItem(workflowPositionKey);
    var mapped = {
      assess: ['assignment', 'assessment', 'barangay-assessment'],
      assign: ['assignment', 'current-assignment'],
      start: ['work-progress', 'current-assignment'],
      note: ['work-progress'],
      resolve: ['resolution-review', 'work-result'],
      block: ['blocked-status', 'blocked-management'],
      manage_block: ['blocked-management', 'work-progress', 'blocked-status'],
      request_information: ['waiting-information'],
      exception: ['reopen', 'barangay-assessment'],
      reopen: ['assessment'],
      verify: ['reopen', 'work-result'],
      information: ['reported-information'],
      link_concern: ['link-concern', 'current-assignment']
    };
    var names = (mapped[saved.action] || []).concat(saved.section || '');
    var target = null;
    names.some(function (name) {
      if (!/^[a-z-]+$/.test(name)) return false;
      target = document.querySelector('[data-workflow-section="' + name + '"]');
      return !!target;
    });
    var restore = function () {
      if (target) {
        if (target.tagName === 'DETAILS') target.open = true;
        target.scrollIntoView({block: 'center', behavior: 'auto'});
      } else {
        window.scrollTo({top: Math.min(Number(saved.scrollY) || 0, Math.max(0, document.documentElement.scrollHeight - window.innerHeight)), behavior: 'auto'});
      }
    };
    if (document.readyState === 'complete') requestAnimationFrame(restore);
    else window.addEventListener('load', function () { requestAnimationFrame(restore); }, {once: true});
  }

  restoreWorkflowPosition();

  function request(endpoint, action, data, id) {
    var record = document.querySelector('[data-case-id]');
    var version = record && record.dataset.caseId === id ? Number(record.dataset.version) : null;
    return fetch(endpoint, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
      body: JSON.stringify({action: action, data: data, id: id || '', version: version})
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) {
          var error = new Error(body.error || 'The request could not be completed.');
          error.status = response.status;
          error.code = body.code;
          error.redirect = body.redirect;
          throw error;
        }
        return body;
      });
    });
  }

  function showError(error) {
    if (error.code === 'csrf_expired') {
      return Swal.fire({icon: 'warning', title: 'Your session changed', text: 'Signing in or switching accounts in another tab changes the shared session. Your entries are still here; this action was not saved.', showCancelButton: true, confirmButtonText: 'Refresh security token', cancelButtonText: 'Keep my draft'})
        .then(function (answer) {
          if (!answer.isConfirmed) return;
          return fetch('api.php?view=session', {credentials: 'same-origin', cache: 'no-store'}).then(function (response) {
            return response.json().then(function (session) {
              if (!response.ok) {
                var sessionError = new Error(session.error || 'Sign in again to continue.');
                sessionError.status = response.status; sessionError.redirect = session.redirect;
                throw sessionError;
              }
              var pageUser = document.querySelector('meta[name="account-id"]');
              var pageVersion = document.querySelector('meta[name="account-version"]');
              if (!pageUser || !pageVersion || session.userId !== pageUser.content || String(session.authVersion) !== pageVersion.content) {
                return Swal.fire({icon: 'warning', title: 'Account or access changed', text: 'This tab belongs to an earlier sign-in. Copy any notes you need, then reload and check the current account. The old action will not be resubmitted.', showCancelButton: true, confirmButtonText: 'Reload current account', cancelButtonText: 'Keep my draft'}).then(function (choice) { if (choice.isConfirmed) window.location.reload(); });
              }
              if (!/^[a-f0-9]{64}$/.test(session.csrf)) throw new Error('Unable to refresh the security token.');
              csrf = session.csrf;
              document.querySelector('meta[name="csrf-token"]').content = csrf;
              // Keep the original concern version: a later stale-write check must still apply.
              return Swal.fire({icon: 'success', title: 'Ready to try again', text: 'Your entries have been kept. Review them and press Save again. Nothing was automatically submitted.', confirmButtonText: 'Return to form'});
            });
          }).catch(showError);
        });
    }
    if (error.status === 401 || error.redirect === 'login.php?view=change-password') {
      return Swal.fire({icon: 'warning', title: 'Sign in again', text: 'Your session ended or needs a password change. Copy any unsaved notes before continuing.', showCancelButton: true, confirmButtonText: 'Go to sign in', cancelButtonText: 'Keep my draft'}).then(function (answer) {
        if (answer.isConfirmed) window.location.assign(error.redirect === 'login.php?view=change-password' ? error.redirect : 'login.php');
      });
    }
    if (error.status === 409) {
      // Keep the submitted draft and its original version until the user reloads.
      // Never retry an old draft with a fresh version automatically.
      return Swal.fire({icon: 'warning', title: 'This concern has changed', text: 'Another user updated this concern. Copy any unsaved text you need, then reload to review the latest record before saving.', showCancelButton: true, confirmButtonText: 'Reload latest record', cancelButtonText: 'Keep my draft'}).then(function (answer) {
        if (answer.isConfirmed) window.location.reload();
      });
    }
    return Swal.fire({icon: 'error', title: 'Unable to complete action', text: error.message || 'Please try again.', confirmButtonText: 'Got it'});
  }

  function readPhoto(file) {
    return new Promise(function (resolve, reject) {
      if (!file) { resolve(''); return; }
      if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) < 0 || file.size > 1048576) {
        reject(new Error('Choose a JPG, PNG, or WebP image smaller than 1 MB.')); return;
      }
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(new Error('The photo could not be read. Please select it again.')); };
      reader.readAsDataURL(file);
    });
  }

  function confirmation(action, data) {
    var messages = {
      resolve: ['Record this resolution?', 'An official will review the evidence before closing the concern.', 'Mark as resolved'],
      reopen: ['Reopen this concern?', 'Your feedback will return the concern to the barangay for reassessment.', 'Reopen concern'],
      verify: ['Confirm the concern is resolved?', 'This records official review and closes the concern.', 'Confirm resolution'],
      exception: ['Record this assessment outcome?', 'The selected outcome and your reason will be added to the concern timeline.', 'Record outcome'],
      request_information: ['Request more information?', 'The request will appear in the reporter\'s account or private tracking page and remain in the concern timeline.', 'Send request'],
      link_concern: ['Link these reports?', 'The original report stays recorded, but work progress and assignment will follow the primary concern.', 'Link reports'],
      block: ['Mark this work as blocked?', 'Officials will be notified and the reason will be recorded in the concern timeline.', 'Mark as blocked']
    };
    var copy = messages[action];
    if (action === 'update_user' && data.active === '0') copy = ['Deactivate this account?', 'This account will no longer be able to sign in. Concern histories will be retained.', 'Deactivate account'];
    return copy ? Swal.fire({icon: 'question', title: copy[0], text: copy[1], showCancelButton: true, showCloseButton: true, allowOutsideClick: false, focusCancel: true, confirmButtonText: copy[2], cancelButtonText: 'Go back'}).then(function (answer) { return answer.isConfirmed; }) : Promise.resolve(true);
  }

  function showCreatedAccount(account) {
    var panel = document.getElementById('created-account');
    var roles = {official: 'Barangay official', personnel: 'Barangay personnel', resident: 'Resident'};
    ['name', 'email', 'team', 'role'].forEach(function (key) {
      panel.querySelector('[data-created="' + key + '"]').textContent = key === 'role' ? roles[account.role] : (account[key] || '');
    });
    document.getElementById('created-team').hidden = !account.team;
    // Only the one-time response and this field hold the password. No browser storage.
    document.getElementById('created-password').value = account.temporary_password;
    document.getElementById('account-form-panel').hidden = true;
    panel.hidden = false;
    document.getElementById('created-account-heading').focus();
    panel.scrollIntoView({block: 'start'});
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.hasAttribute('data-action')) return; // GET searches navigate normally.
    event.preventDefault();
    if (busy) return;
    var action = form.dataset.action, id = form.dataset.id || '', data = {};
    new FormData(form).forEach(function (value, key) {
      if (typeof value === 'string') data[key] = key.indexOf('password') >= 0 ? value : value.trim();
    });
    data.keyPoints = new FormData(form).getAll('keyPoints');
    data.actions = new FormData(form).getAll('actions');
    if (action === 'verification') action = event.submitter ? event.submitter.value : 'verify';
    if (action === 'reopen' && !data.feedback) {
      document.getElementById('feedback').focus();
      showError(new Error('Please describe what still needs attention before reopening the concern.')); return;
    }
    var blank = Array.prototype.find.call(form.querySelectorAll('[required]'), function (input) { return !input.value.trim(); });
    if (blank) { blank.focus(); showError(new Error('Please complete all required fields. A field cannot contain only spaces.')); return; }
    if (action === 'profile' && data.new_password !== data.confirm_new_password) { showError(new Error('The new passwords do not match.')); return; }
    var file = form.querySelector('input[type="file"]');
    var buttons = Array.prototype.slice.call(form.querySelectorAll('button'));
    busy = true;
    buttons.forEach(function (button) { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    readPhoto(file && file.files[0]).then(function (photo) {
      if (file) { data.photo = photo; data.photoName = file.files[0] ? file.files[0].name : ''; }
      return confirmation(action, data);
    }).then(function (confirmed) {
      if (!confirmed) return;
      document.body.classList.add('app-busy');
      return request('api.php', action, data, id).then(function (result) {
        if (action === 'create_user') { showCreatedAccount(result.created_account); form.reset(); return; }
        if (result.redirect) { navigating = true; window.location.assign(result.redirect); return; }
        var destination = action === 'profile' ? 'profile.php'
          : action === 'update_user' ? 'users.php'
          : action === 'save_official_rules' ? 'official-solutions.php'
          : ['create_action_plan','update_action_plan'].includes(action) ? 'action-plans.php'
          : action === 'personnel_action_plan' ? 'my-action-plans.php'
          : ['save_rule', 'reset_rule'].includes(action) ? 'solutions.php'
          : ['create_location', 'update_location', 'toggle_location'].includes(action) ? 'settings.php'
          : 'complaint.php';
        var query = new URLSearchParams({saved: action});
        if (destination === 'complaint.php' || destination === 'action-plans.php' || destination === 'my-action-plans.php') query.set('id', result.id || id);
        if (action === 'save_official_rules') { query.set('category',data.category); query.set('type',data.concernType); query.set('keypoint',data.keypoint); }
        if (destination === 'complaint.php') rememberWorkflowPosition(result.id || id, action, form);
        navigating = true;
        window.location.assign(destination + '?' + query.toString());
      });
    }).catch(showError).finally(function () {
      document.body.classList.remove('app-busy');
      form.removeAttribute('aria-busy');
      if (!navigating) { busy = false; buttons.forEach(function (button) { button.disabled = false; }); }
    });
  });

  function updateSla(form) {
    var advice = form && form.querySelector('[data-sla-dates]');
    if (!advice) return;
    var dates = JSON.parse(advice.dataset.slaDates), priority = form.querySelector('[name="priority"]').value;
    advice.querySelector('[data-sla-label]').textContent = dates[priority].replace('T',' ');
  }
  document.addEventListener('change', function(event) { if (event.target.name === 'priority') updateSla(event.target.closest('form')); });
  document.addEventListener('click', function(event) {
    var button = event.target.closest('[data-accept-sla], [data-move-rule], [data-accept-priority]');
    if (!button) return;
    var form = button.closest('form');
    if (button.hasAttribute('data-accept-priority')) { setTimeout(function() { updateSla(form); }, 0); return; }
    if (button.hasAttribute('data-accept-sla')) {
      var dates = JSON.parse(form.querySelector('[data-sla-dates]').dataset.slaDates);
      form.querySelector('[name="dueAt"]').value = dates[form.querySelector('[name="priority"]').value];
      return;
    }
    var slot = button.closest('[data-rule-slot]'), target = form.querySelector('[data-rule-slot="' + (Number(slot.dataset.ruleSlot) + Number(button.dataset.moveRule)) + '"]');
    if (!target) return;
    var first = slot.querySelector('textarea'), second = target.querySelector('textarea'), value = first.value;
    first.value = second.value; second.value = value;
    var a = slot.querySelector('input[type="checkbox"]'), b = target.querySelector('input[type="checkbox"]'), active = a.checked;
    a.checked = b.checked; b.checked = active; second.focus();
  });

  var menuReturnFocus = null;
  var menuBackground = [];
  function toggleMenu(open) {
    var sidebar = document.getElementById('workspace-sidebar');
    if (open) menuReturnFocus = document.activeElement;
    sidebar.classList.toggle('mobile-open', open);
    document.querySelector('.sidebar-scrim').hidden = !open;
    document.body.classList.toggle('navigation-open', open);
    if (open) {
      menuBackground = Array.from(document.querySelectorAll('.topbar,.workspace-toolbar,.main,.mobile-dock,.chat-widget'));
      menuBackground.forEach(function (element) { element.inert = true; });
    } else {
      menuBackground.forEach(function (element) { element.inert = false; });
      menuBackground = [];
    }
    document.querySelectorAll('[data-menu][aria-expanded]').forEach(function (button) { button.setAttribute('aria-expanded', String(open)); });
    if (open) sidebar.querySelector('a').focus();
    else if (menuReturnFocus && menuReturnFocus.isConnected) menuReturnFocus.focus();
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('button');
    if (!target) return; // All navigation links keep native Back/new-tab behavior.
    if (target.hasAttribute('data-menu')) toggleMenu(!document.getElementById('workspace-sidebar').classList.contains('mobile-open'));
    else if (target.hasAttribute('data-refresh')) window.location.reload();
    else if (target.hasAttribute('data-help')) Swal.fire({title: 'One concern. A complete journey.', html: document.getElementById('workflow-help').innerHTML, confirmButtonText: 'Explore the workspace', width: 620});
    else if (target.hasAttribute('data-logout') && !busy) {
      if (document.getElementById('workspace-sidebar').classList.contains('mobile-open')) toggleMenu(false);
      busy = true;
      Swal.fire({icon: 'question', title: 'Sign out of your workspace?', text: 'Saved concerns and account information will remain available when you sign in again.', showCancelButton: true, showCloseButton: true, allowOutsideClick: false, focusCancel: true, confirmButtonText: 'Sign out', cancelButtonText: 'Stay signed in'}).then(function (answer) {
        if (answer.isConfirmed) return request('auth.php', 'logout', {}).then(function () { navigating = true; window.location.assign('login.php'); });
      }).catch(showError).finally(function () { if (!navigating) busy = false; });
    } else if (target.hasAttribute('data-use-recommendation')) {
      var previous = document.getElementById(target.dataset.useRecommendation), textarea = document.getElementById('recommendation');
      if (previous && textarea) {
        textarea.value = previous.value; textarea.focus(); textarea.scrollIntoView({block: 'center', behavior: 'smooth'});
        Toast.fire({icon: 'info', title: 'Draft copied. Review it before saving the assessment.'});
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    var sidebar = document.getElementById('workspace-sidebar');
    if (!sidebar.classList.contains('mobile-open')) return;
    if (event.key === 'Escape') {
      toggleMenu(false);
    } else if (event.key === 'Tab') {
      var controls = Array.from(sidebar.querySelectorAll('a,button,summary')).filter(function (element) { return !element.disabled && element.getClientRects().length; });
      var first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });

  window.addEventListener('resize', function () {
    if (innerWidth > 767 && document.getElementById('workspace-sidebar').classList.contains('mobile-open')) toggleMenu(false);
  });

  function updateConditionalFields() {
    var outcome = document.getElementById('exception-status');
    if (outcome) {
      var referral = outcome.value === 'Referred to Another Office';
      document.getElementById('office-wrap').hidden = !referral;
      document.getElementById('receiving-office').required = referral;
    }
    var role = document.getElementById('user-role');
    if (role) {
      document.getElementById('user-team-wrap').hidden = role.value !== 'personnel';
      document.getElementById('user-team').required = role.value === 'personnel';
      document.getElementById('user-admin-wrap').hidden = role.value !== 'official';
      document.getElementById('user-admin').disabled = role.value !== 'official' || document.getElementById('user-admin').dataset.self === '1';
    }
  }
  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target.id === 'exception-status' || target.id === 'user-role') updateConditionalFields();
    else if (target.id === 'profile-photo-remove') {
      var profileInput = document.getElementById('profile-photo'), profilePreview = document.querySelector('[data-preview="profile-photo"]');
      if (profileInput) profileInput.value = '';
      if (profilePreview) {
        profilePreview.replaceChildren();
        if (target.checked) {
          var fallback = document.createElement('span');
          fallback.className = 'avatar me profile-photo-avatar';
          fallback.setAttribute('role', 'img');
          fallback.setAttribute('aria-label', 'No profile photo selected');
          fallback.textContent = profilePreview.dataset.initials;
          profilePreview.appendChild(fallback);
        } else {
          var currentPhoto = document.createElement('img');
          currentPhoto.className = 'avatar me profile-photo-avatar';
          currentPhoto.src = 'profile-photo.php';
          currentPhoto.alt = 'Current profile photo';
          profilePreview.appendChild(currentPhoto);
        }
      }
    }
    else if (target.type === 'file') {
      var preview = document.querySelector('[data-preview="' + target.id + '"]');
      var selected = target.files[0];
      if (target.id === 'profile-photo' && selected) {
        var removePhoto = document.getElementById('profile-photo-remove');
        if (removePhoto) removePhoto.checked = false;
      }
      readPhoto(selected).then(function (photo) {
        if (!preview || target.files[0] !== selected) return;
        preview.replaceChildren();
        if (photo) { var image = document.createElement('img'); image.className = 'upload-preview'; image.src = photo; image.alt = 'Selected photo preview'; preview.appendChild(image); }
      }).catch(function (error) { target.value = ''; if (preview) preview.replaceChildren(); showError(error); });
    }
  });
  updateConditionalFields();
  window.addEventListener('pagehide', function () {
    var password = document.getElementById('created-password');
    if (password) password.value = '';
  });
  window.addEventListener('pageshow', function (event) {
    // Recheck authentication, permissions, and record versions after cached Back/Forward.
    if (event.persisted) window.location.reload();
  });
}());
