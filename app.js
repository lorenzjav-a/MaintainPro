(function () {
  'use strict';
  var state, currentCase = null, busy = false, preparing = false, caseOpener = null, formOpener = null;
  var ui = {page: 'overview', search: '', tab: 'all', category: '', priority: '', status: '', mobile: false};
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var caseModal = new bootstrap.Modal(document.getElementById('case-modal'));
  var formModal = new bootstrap.Modal(document.getElementById('form-modal'));
  var Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 3800, timerProgressBar: true});
  var paths = {
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    inbox: '<path d="M4 4h16l2 12v4H2v-4L4 4Z"/><path d="M2 16h6l2 3h4l2-3h6"/>',
    clipboard: '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    checkCircle: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    arrow: '<path d="M4 12h15m-5-5 5 5-5 5"/>',
    chevron: '<path d="m9 5 7 7-7 7"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
    pin: '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    chart: '<path d="M4 3v17h17M9 15v-4M14 15V6M19 15V9"/>',
    book: '<path d="M12 5v16M3 3c4 0 7 0 9 2 2-2 5-2 9-2v16c-4 0-7 0-9 2-2-2-5-2-9-2V3Z"/>',
    users: '<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M16 4a3 3 0 0 1 0 6M19 14a5 5 0 0 1 3 5v2"/>',
    shield: '<path d="m12 2 8 3v7c0 5-8 10-8 10S4 17 4 12V5l8-3Z"/><path d="m8 11 3 3 5-6"/>',
    help: '<circle cx="12" cy="12" r="9"/><path d="M9.5 8a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16v.2"/>',
    download: '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    building: '<path d="m3 9 9-6 9 6H3Zm2 1v10m7-10v10m7-10v10M2 21h20"/>',
    flag: '<path d="M5 22V3m0 1c5-5 9 5 15 0v10c-6 5-10-5-15 0"/>',
    tool: '<path d="M14 6a5 5 0 0 0-6 6l-5 5a2 2 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-3 3-4-4 3-3Z"/>',
    refresh: '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 7a8 8 0 0 1 13 0L20 12M4 12l1.5 5a8 8 0 0 0 13 0"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/>',
    spark: '<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>'
  };
  function icon(name) { return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' + (paths[name] || paths.inbox) + '</svg>'; }
  function e(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function slug(v) { return v.toLowerCase().replace(/[^a-z0-9]+/g, '-'); }
  function date(v, full) { return new Date(v).toLocaleDateString('en-PH', full ? {month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'} : {month: 'short', day: 'numeric'}); }
  function status(c) { return '<span class="status status-' + slug(c.status) + '">' + e(c.status) + '</span>'; }
  function priority(c) { return '<span class="priority priority-' + slug(c.priority) + '"><span class="priority-dot"></span>' + e(c.priority) + '</span>'; }
  function nextStep(c) {
    var copy = {
      resident: {Submitted: 'Waiting for barangay review', 'Under Review': 'Barangay is preparing the action', Assigned: 'A response team is assigned', 'In Progress': 'Work is underway', Resolved: 'Review and verify the result', Verified: 'Closed after your confirmation', Reopened: 'Returned for another assessment', 'Returned for Information': 'Add the requested information', Rejected: 'Read the recorded reason', 'Referred to Another Office': 'Follow the referral details'},
      official: {Submitted: 'Review and recommend an action', 'Under Review': 'Assign the responsible team', Assigned: 'Waiting for the team to accept', 'In Progress': 'Monitor the team’s work', Resolved: 'Waiting for resident verification', Verified: 'Complete and recorded', Reopened: 'Reassess and assign another action', 'Returned for Information': 'Waiting for resident details', Rejected: 'No further action', 'Referred to Another Office': 'Monitor the referral separately'},
      personnel: {Assigned: 'Accept this assignment', 'In Progress': 'Add an update or record resolution', Resolved: 'Waiting for resident verification', Verified: 'Complete and recorded', Reopened: 'Waiting for reassignment'}
    };
    return (copy[state.actor.role] && copy[state.actor.role][c.status]) || 'Open for details';
  }
  function find(id) { return state.cases.filter(function (c) { return c.id === id; })[0]; }
  function active(c) { return ['Verified', 'Rejected', 'Referred to Another Office'].indexOf(c.status) < 0; }
  function review(c) { return ['Submitted', 'Under Review', 'Reopened'].indexOf(c.status) >= 0; }
  function verified(c) { return c.status === 'Verified'; }
  function options(values, selected, placeholder) { return (placeholder ? '<option value="">' + e(placeholder) + '</option>' : '') + values.map(function (v) { return '<option value="' + e(v) + '"' + (v === selected ? ' selected' : '') + '>' + e(v) + '</option>'; }).join(''); }
  function metrics() {
    var cases = state.cases, completed = cases.filter(function (c) { return ['Resolved', 'Verified'].indexOf(c.status) >= 0 && c.resolution; });
    return {total: cases.length, assessment: cases.filter(review).length, pending: cases.filter(active).length, progress: cases.filter(function (c) { return c.status === 'In Progress'; }).length, resolved: cases.filter(function (c) { return c.status === 'Resolved'; }).length, verified: cases.filter(verified).length, urgent: cases.filter(function (c) { return c.priority === 'Urgent' && active(c); }).length, reopened: cases.filter(function (c) { return c.reopenCount > 0; }).length, average: completed.length ? (completed.reduce(function (n, c) { return n + Math.max(0, new Date(c.resolution.date) - new Date(c.createdAt)) / 86400000; }, 0) / completed.length).toFixed(1) : '—'};
  }
  function group(cases, key) {
    var result = {};
    cases.forEach(function (c) { var k = typeof key === 'function' ? key(c) : c[key]; result[k] = (result[k] || 0) + 1; });
    return Object.keys(result).map(function (k) { return {label: k, count: result[k]}; }).sort(function (a, b) { return b.count - a.count || a.label.localeCompare(b.label); });
  }
  function api(action, data, id) {
    var record = state && id ? find(id) : null;
    return fetch('api.php', {method: action ? 'POST' : 'GET', credentials: 'same-origin', headers: action ? {'Content-Type': 'application/json', 'X-CSRF-Token': csrf} : {}, body: action ? JSON.stringify({action: action, data: data || {}, id: id || '', version: record ? record.version : null}) : undefined})
      .then(function (response) { return response.json().then(function (body) {
        if (response.status === 401) window.location.assign('login.php');
        if (!response.ok) {
          if (response.status === 409 && body.state) state = body.state;
          var requestError = new Error(body.error || 'The request could not be completed.');
          requestError.status = response.status;
          throw requestError;
        }
        return body;
      }); });
  }
  function isDemo() { return state.mode !== 'account'; }
  function roleLabel(role) { return {official: 'Barangay official', resident: 'Resident', personnel: 'Barangay personnel'}[role] || role; }
  function initials(name) { return name.split(/\s+/).filter(Boolean).map(function (part) { return part.charAt(0); }).slice(0, 2).join('').toUpperCase(); }
  function workspaceToolbar() {
    var a = state.actor;
    if (!isDemo()) return '<div class="demo-toolbar saved-toolbar"><span class="demo-label">SAVED WORKSPACE</span><span class="workspace-account-label">' + e(roleLabel(a.role)) + (a.team ? ' · ' + e(a.team) : '') + '</span><div class="account-toolbar-actions"><button class="link-button" data-refresh>' + icon('refresh') + ' Refresh</button><button class="link-button" data-nav="profile">My profile</button><button class="link-button" data-logout>Sign out</button></div></div>';
    return '<div class="demo-toolbar"><span class="demo-label">DEMO</span><label for="role-select">Explore as</label><select id="role-select" class="form-select form-select-sm" aria-label="Switch demo role">' + options(['official', 'resident', 'personnel'], a.role).replace('>official<', '>Barangay official<').replace('>resident<', '>Resident<').replace('>personnel<', '>Barangay personnel<') + '</select>' +
      (a.role === 'personnel' ? '<select id="team-select" class="form-select form-select-sm team-select" aria-label="Choose personnel team">' + options(state.teams, a.team) + '</select>' : '') +
      '<span class="demo-hint">Try the complete journey by switching roles.</span><button class="link-button" data-reset>Reset sample data</button><a class="link-button ms-2" href="login.php">Sign in</a></div>';
  }
  function dialog(settings) {
    // Keep confirmations inside Bootstrap's active focus trap.
    settings.target = document.querySelector('.modal.show') || document.body;
    settings.keydownListenerCapture = true;
    return Swal.fire(settings);
  }
  function error(err) { dialog({icon: 'error', title: 'Unable to complete action', text: err.message || 'Please try again.', confirmButtonText: 'Got it'}); }
  function resetFilters() { ui.search = ''; ui.tab = 'all'; ui.category = ''; ui.priority = ''; ui.status = ''; }
  function navigate(page, tab) { ui.page = page; resetFilters(); ui.tab = tab || 'all'; ui.mobile = false; render(); window.scrollTo({top: 0, behavior: 'instant'}); }
  function quick(tab) { navigate('complaints', tab); }
  function navItem(page, label, name, count) { return '<button class="nav-link' + (ui.page === page ? ' active' : '') + '" data-nav="' + page + '"' + (ui.page === page ? ' aria-current="page"' : '') + '>' + icon(name) + '<span>' + label + '</span>' + (count !== undefined ? '<span class="nav-count">' + count + '</span>' : '') + '</button>'; }
  function mobileDock() {
    var resident = state.actor.role === 'resident', official = state.actor.role === 'official';
    var mainAction = resident
      ? '<button class="mobile-primary" data-new aria-label="Report a concern">' + icon('plus') + '<span>Report</span></button>'
      : '<button class="mobile-primary" data-quick="' + (official ? 'assessment' : 'pending') + '">' + icon(official ? 'clipboard' : 'tool') + '<span>' + (official ? 'Review' : 'My work') + '</span></button>';
    return '<nav class="mobile-dock" aria-label="Quick navigation"><button class="' + (ui.page === 'overview' ? 'active' : '') + '" data-nav="overview">' + icon('grid') + '<span>Overview</span></button><button class="' + (ui.page === 'complaints' ? 'active' : '') + '" data-nav="complaints">' + icon('inbox') + '<span>' + (resident ? 'My reports' : official ? 'Complaints' : 'Assignments') + '</span></button>' + mainAction + '<button class="' + (ui.page === 'history' ? 'active' : '') + '" data-nav="history">' + icon('clock') + '<span>History</span></button><button data-menu>' + icon('menu') + '<span>More</span></button></nav>';
  }
  function render(preserveFocus) {
    var focused = preserveFocus ? document.activeElement : null, focusId = focused && focused.id, caret = focused && focused.selectionStart;
    var m = metrics(), a = state.actor, official = a.role === 'official', resident = a.role === 'resident';
    var pageNames = {overview: 'Overview', complaints: resident ? 'My complaints' : official ? 'All complaints' : 'Assigned work', history: 'Resolution history', insights: 'Reports & insights', knowledge: 'Solution library', users: 'User management', profile: 'My profile'};
    if ((!official && ['insights', 'knowledge', 'users'].indexOf(ui.page) >= 0) || (isDemo() && ['users', 'profile'].indexOf(ui.page) >= 0)) ui.page = 'overview';
    document.getElementById('app').innerHTML =
      '<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a><div class="shell">' +
      (ui.mobile ? '<button class="sidebar-scrim" data-menu aria-label="Close navigation"></button>' : '') +
      '<aside class="sidebar' + (ui.mobile ? ' mobile-open' : '') + '" aria-label="Main navigation"><a href="./" class="brand"><img src="favicon.svg" alt=""><div><div class="brand-title">Barangay<span>Resolve</span></div><small>Community care, connected</small></div></a>' +
      '<div class="workspace-label">WORKSPACE</div><nav class="nav-list">' + navItem('overview', 'Overview', 'grid') + navItem('complaints', pageNames.complaints, 'inbox', m.total) +
      (official ? '<button class="nav-link" data-quick="assessment">' + icon('clipboard') + '<span>Needs assessment</span><span class="nav-count">' + m.assessment + '</span></button>' : '') +
      '<div class="sidebar-line"></div><div class="workspace-label">RECORDS & LEARNING</div>' + navItem('history', 'Resolution history', 'clock') +
      (official ? navItem('insights', 'Reports & insights', 'chart') + navItem('knowledge', 'Solution library', 'book') : '') + (!isDemo() && official ? '<div class="sidebar-line"></div>' + navItem('users', 'User management', 'users') : '') + '</nav>' +
      '<div class="sidebar-bottom"><button class="nav-link" data-help>' + icon('help') + 'How it works</button><div class="demo-note"><strong><span class="demo-dot"></span>' + (isDemo() ? 'Prototype workspace' : 'Saved workspace') + '</strong><p>' + (isDemo() ? 'Fictional reports. Changes stay in your current PHP demo session.' : 'Reports and their histories are saved, even after you sign out.') + '</p></div><div class="sidebar-footer">' + icon('building') + 'Barangay community services</div></div></aside>' +
      '<header class="topbar"><div class="breadcrumb-label"><button class="icon-btn menu-toggle" data-menu aria-label="Open navigation">' + icon('menu') + '</button><span class="workspace-crumb">Workspace</span><span class="workspace-crumb">/</span><strong>' + pageNames[ui.page] + '</strong></div><div class="topbar-right"><span class="date-label">' + new Date().toLocaleDateString('en-PH', {weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'}) + '</span><div class="profile"><span class="avatar me">' + e(initials(a.name)) + '</span><div><div class="profile-name">' + e(a.name) + '</div><div class="profile-role">' + e(roleLabel(a.role)) + '</div></div></div></div></header>' +
      workspaceToolbar() + '<main class="main" id="main-content">' + pageContent() + '<footer class="main-footer"><span>BarangayResolve · Community Complaint & Resolution Management</span><span>' + (isDemo() ? 'Sample data only · Demo session' : 'Saved account workspace') + '</span></footer></main>' + mobileDock() + '</div>';
    if (focusId) { var input = document.getElementById(focusId); if (input) { input.focus(); if (typeof caret === 'number' && input.setSelectionRange) input.setSelectionRange(caret, caret); } }
  }
  function heading(title, description, actions) {
    return '<div class="page-heading"><div><h1>' + title + '</h1><p>' + description + '</p></div><div class="heading-actions">' + (actions || '') + '</div></div>';
  }
  function usersPage() {
    return heading('User management', 'Manage resident accounts, official access, and personnel teams.', '<button class="btn btn-primary" data-add-user>' + icon('plus') + 'Create account</button>') +
      '<section class="panel user-register"><div class="panel-header"><div><h2 class="panel-title">Workspace accounts <span class="count-pill">' + state.users.length + '</span></h2><p class="panel-subtitle">Deactivated accounts cannot sign in. Complaint histories are retained.</p></div></div><div class="table-responsive"><table class="table mb-0"><caption class="visually-hidden">Saved workspace user accounts</caption><thead><tr><th scope="col">Account</th><th scope="col">Role / team</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>' +
      state.users.map(function (u) { return '<tr><td><strong>' + e(u.name) + (u.id === state.actor.id ? ' <span class="count-pill">YOU</span>' : '') + '</strong><small>' + e(u.email) + '</small></td><td><span class="role-pill">' + e(roleLabel(u.role)) + '</span>' + (u.team ? '<small>' + e(u.team) + '</small>' : '') + '</td><td><span class="status ' + (Number(u.active) ? 'status-verified' : 'status-rejected') + '">' + (Number(u.active) ? 'Active' : 'Inactive') + '</span></td><td><button class="btn btn-light btn-sm" data-edit-user="' + e(u.id) + '" aria-label="Manage account for ' + e(u.name) + '">Manage</button></td></tr>'; }).join('') +
      '</tbody></table></div></section>';
  }
  function profilePage() {
    var a = state.actor;
    return heading('My profile', 'Update your account details and password.') +
      '<section class="panel profile-panel"><div class="panel-header"><div><h2 class="panel-title">' + e(a.name) + '</h2><p class="panel-subtitle">' + e(roleLabel(a.role)) + (a.team ? ' · ' + e(a.team) : '') + '</p></div><span class="avatar me">' + e(initials(a.name)) + '</span></div><div class="panel-body"><form data-action="profile"><div class="row g-3"><div class="col-sm-6"><label class="form-label" for="profile-name">Full name</label><input class="form-control" id="profile-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="' + e(a.name) + '"></div><div class="col-sm-6"><label class="form-label" for="profile-email">Email address</label><input class="form-control" id="profile-email" name="email" type="email" required maxlength="254" autocomplete="username" value="' + e(a.email) + '"></div><div class="col-12"><label class="form-label" for="current-password">Current password</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required><p class="form-text">Confirm your current password to save account changes.</p></div><div class="col-sm-6"><label class="form-label" for="new-password">New password <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="new-password" name="new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div><div class="col-sm-6"><label class="form-label" for="confirm-new-password">Confirm new password</label><input class="form-control" id="confirm-new-password" name="confirm_new_password" type="password" minlength="10" maxlength="72" autocomplete="new-password"></div></div><p class="form-text mt-3">Leave the new password fields empty to keep your current password. Use at least 10 characters for a new one.</p><button class="btn btn-primary mt-3" type="submit">' + icon('check') + 'Save profile</button></form></div></section>';
  }
  function userForm(id, trigger) {
    if (isDemo() || state.actor.role !== 'official') return;
    var user = id ? state.users.filter(function (u) { return u.id === id; })[0] : null;
    if (id && !user) return;
    var role = user ? user.role : 'personnel';
    formOpener = trigger || null;
    document.getElementById('form-content').innerHTML =
      '<div class="modal-header"><div><div class="eyebrow">ACCOUNT MANAGEMENT</div><h2 class="modal-title" id="form-heading">' + (user ? 'Manage ' + e(user.name) : 'Create a workspace account') + '</h2></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Close account form" type="button"></button></div><div class="modal-body"><form data-action="' + (user ? 'update_user' : 'create_user') + '"' + (user ? ' data-id="' + e(user.id) + '"' : '') + '><div class="new-form-body"><div class="row g-3">' +
      (user ? '<div class="col-12"><p class="info-callout mb-0">' + e(user.email) + (user.id === state.actor.id ? '<br>You cannot deactivate or remove official access from your own account.' : '<br>Changes take effect on the account’s next request.') + '</p></div>' : '<div class="col-sm-6"><label class="form-label" for="user-name">Full name</label><input class="form-control" id="user-name" name="name" required minlength="2" maxlength="100" autocomplete="off"></div><div class="col-sm-6"><label class="form-label" for="user-email">Email address</label><input class="form-control" id="user-email" name="email" type="email" required maxlength="254" autocomplete="off"></div><div class="col-12"><label class="form-label" for="user-password">Initial password</label><input class="form-control" id="user-password" name="password" type="password" required minlength="10" maxlength="72" autocomplete="new-password"><p class="form-text">Use at least 10 characters. The user can change it in My profile. This system does not send account emails.</p></div>') +
      '<div class="col-sm-6"><label class="form-label" for="user-role">Account role</label><select class="form-select" id="user-role" name="role" required>' + ['resident', 'personnel', 'official'].map(function (r) { return '<option value="' + r + '"' + (r === role ? ' selected' : '') + '>' + roleLabel(r) + '</option>'; }).join('') + '</select></div><div class="col-sm-6" id="user-team-wrap"' + (role !== 'personnel' ? ' hidden' : '') + '><label class="form-label" for="user-team">Personnel team</label><select class="form-select" id="user-team" name="team"' + (role === 'personnel' ? ' required' : '') + '>' + options(state.teams, user ? user.team : '', 'Choose a team') + '</select></div>' +
      (user ? '<div class="col-sm-6"><label class="form-label" for="user-active">Account status</label><select class="form-select" id="user-active" name="active"><option value="1"' + (Number(user.active) ? ' selected' : '') + '>Active</option><option value="0"' + (!Number(user.active) ? ' selected' : '') + '>Inactive</option></select></div>' : '') +
      '</div></div><div class="form-modal-footer"><small>' + (user ? 'Existing complaint records are retained.' : 'Public registration creates resident accounts only.') + '</small><button class="btn btn-primary" type="submit">' + (user ? 'Save access settings' : 'Create account') + '</button></div></form></div>';
    formModal.show(trigger);
  }
  function signOut() {
    dialog({icon: 'question', title: 'Sign out of your workspace?', text: 'Saved complaints and account information will remain available when you sign in again.', showCancelButton: true, confirmButtonText: 'Sign out', cancelButtonText: 'Stay signed in'}).then(function (r) {
      if (!r.isConfirmed) return;
      return fetch('auth.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({action: 'logout', data: {}})})
        .then(function (response) { if (!response.ok) throw new Error('Unable to sign out. Refresh the page and try again.'); window.location.assign('login.php'); }).catch(error);
    });
  }
  function exportButton() { return '<a class="btn btn-light" href="api.php?export=csv" download>' + icon('download') + 'Export report</a>'; }
  function primaryButton() {
    if (state.actor.role === 'resident') return '<button class="btn btn-primary" data-new>' + icon('plus') + 'Report a concern</button>';
    if (state.actor.role === 'official') return '<button class="btn btn-primary" data-quick="assessment">' + icon('clipboard') + 'Review complaints</button>';
    return '<button class="btn btn-primary" data-quick="pending">' + icon('clipboard') + 'View assignments</button>';
  }
  function stat(label, number, caption, name, color, tab) {
    return '<button class="stat-card ' + color + '" data-quick="' + tab + '"><div class="stat-top"><span>' + label + '</span><span class="stat-icon">' + icon(name) + '</span></div><div class="number">' + number + '</div><div class="stat-caption">' + caption + '</div></button>';
  }
  function stats() {
    var m = metrics(), official = state.actor.role === 'official';
    return '<div class="stats-grid">' + stat('Total complaints', m.total, 'All reports in this workspace', 'inbox', '', 'all') +
      stat(official ? 'Needs assessment' : 'Open complaints', official ? m.assessment : m.pending, official ? 'Review & recommend an action' : 'Including resident verification', 'clipboard', 'amber', official ? 'assessment' : 'pending') +
      stat('In progress', m.progress, 'Action underway with personnel', 'tool', 'blue', 'progress') +
      stat('Verified resolutions', m.verified, 'Confirmed by the reporting resident', 'checkCircle', 'green', 'verified') + '</div>';
  }
  function banner() {
    var m = metrics(), a = state.actor;
    if (a.role === 'official') return '<div class="attention-banner"><div class="attention-icon">' + icon('clipboard') + '</div><div class="attention-copy"><strong>' + m.assessment + ' complaint' + (m.assessment === 1 ? '' : 's') + ' need' + (m.assessment === 1 ? 's' : '') + ' your assessment</strong><p>' + (m.urgent ? m.urgent + ' urgent concern' + (m.urgent === 1 ? ' is' : 's are') + ' awaiting action. ' : '') + 'Review the details and recommend the next step.</p></div><button class="link-button" data-quick="assessment">Review queue ' + icon('arrow') + '</button></div>';
    if (a.role === 'resident') return '<div class="attention-banner"><div class="attention-icon">' + icon('checkCircle') + '</div><div class="attention-copy"><strong>' + (m.resolved ? m.resolved + ' resolution' + (m.resolved === 1 ? ' is' : 's are') + ' ready for your verification' : 'Your voice helps improve the community') + '</strong><p>' + (m.resolved ? 'Check the completed work. Confirm the outcome or tell us what still needs attention.' : 'Submit a concern, suggest a solution, and follow the barangay’s response.') + '</p></div><button class="link-button" data-quick="' + (m.resolved ? 'resolved' : 'all') + '">' + (m.resolved ? 'Verify outcome' : 'Track my reports') + ' ' + icon('arrow') + '</button></div>';
    return '<div class="attention-banner"><div class="attention-icon">' + icon('tool') + '</div><div class="attention-copy"><strong>' + e(a.team) + ' · ' + state.cases.filter(function (c) { return c.status === 'Assigned'; }).length + ' new assignment(s)</strong><p>Follow the official recommended action and record the work performed.</p></div><button class="link-button" data-quick="pending">Open work queue ' + icon('arrow') + '</button></div>';
  }
  function chart(cases, full) {
    var data = group(cases, 'category'), shown = full ? data : data.slice(0, 5), max = data.length ? data[0].count : 1;
    return '<div class="category-chart">' + (shown.length ? shown.map(function (r) { return '<div class="chart-row"><div class="chart-label"><span>' + e(r.label) + '</span><strong>' + r.count + '</strong></div><div class="chart-track"><div class="chart-fill" style="width:' + (r.count / max * 100) + '%"></div></div></div>'; }).join('') : '<p class="text-muted small">No category data yet.</p>') + '</div>';
  }
  function activity() {
    var events = [];
    state.cases.forEach(function (c) { c.timeline.forEach(function (t) { events.push({id: c.id, event: t}); }); });
    events.sort(function (a, b) { return new Date(b.event.date) - new Date(a.event.date); });
    return '<div class="panel"><div class="panel-header"><h2 class="panel-title">Latest activity</h2><span class="count-pill">' + (isDemo() ? 'DEMO' : 'RECENT') + '</span></div><div class="activity-list">' + events.slice(0, 4).map(function (r) { return '<div class="activity-item"><span class="activity-mark">' + icon(r.event.title.indexOf('verified') >= 0 ? 'check' : 'clock') + '</span><div><p><button data-open="' + e(r.id) + '">' + e(r.id) + '</button> · ' + e(r.event.title) + '</p><small>' + date(r.event.date, true) + '</small></div></div>'; }).join('') + '</div></div>';
  }
  function bottomPanels() {
    var m = metrics(), ratio = m.total ? Math.round(m.verified / m.total * 100) : 0;
    var teams = group(state.cases.filter(function (c) { return c.team && ['Assigned', 'In Progress', 'Reopened'].indexOf(c.status) >= 0; }), 'team');
    return '<div class="bottom-panels"><section class="panel"><div class="panel-header"><h2 class="panel-title">Team workload</h2><span class="count-pill">ACTIVE WORK</span></div><div class="team-list">' +
      (teams.length ? teams.slice(0, 3).map(function (r) { return '<div class="team-row"><span class="team-icon">' + icon('users') + '</span><span class="team-name">' + e(r.label) + '</span><span class="team-badge">' + r.count + ' open</span></div>'; }).join('') : '<p class="text-muted small">No active team assignments.</p>') + '</div></section>' +
      '<section class="panel"><div class="panel-header"><h2 class="panel-title">Closing the loop</h2>' + icon('checkCircle') + '</div><div class="resolution-summary"><div class="completion-ring" style="--pct:' + ratio + '%" role="img" aria-label="' + ratio + ' percent of complaints verified"><span>' + ratio + '%</span></div><div class="resolution-copy"><strong>' + m.verified + ' of ' + m.total + ' reports resident-verified</strong><p>' + m.resolved + ' resolved and awaiting verification.<br>A completed action still needs the resident’s confirmation.</p><button class="link-button" data-nav="history">View resolution history ' + icon('arrow') + '</button></div></div></section></div>';
  }
  function filteredCases() {
    var list = state.cases.filter(function (c) {
      if (ui.page === 'history' && !(c.resolution || ['Verified', 'Rejected', 'Referred to Another Office'].indexOf(c.status) >= 0)) return false;
      if (ui.tab === 'pending' && !active(c)) return false;
      if (ui.tab === 'assessment' && !review(c)) return false;
      if (ui.tab === 'progress' && c.status !== 'In Progress') return false;
      if (ui.tab === 'resolved' && c.status !== 'Resolved') return false;
      if (ui.tab === 'verified' && c.status !== 'Verified') return false;
      if (ui.tab === 'urgent' && !(c.priority === 'Urgent' && active(c))) return false;
      if (ui.tab === 'reopened' && !c.reopenCount) return false;
      if (ui.category && c.category !== ui.category) return false;
      if (ui.priority && c.priority !== ui.priority) return false;
      if (ui.status && c.status !== ui.status) return false;
      var haystack = [c.id, c.title, c.location, c.category, c.resident, c.team].join(' ').toLowerCase();
      return !ui.search || haystack.indexOf(ui.search.toLowerCase().trim()) >= 0;
    });
    list.sort(function (a, b) { return new Date(ui.page === 'history' ? b.updatedAt : b.createdAt) - new Date(ui.page === 'history' ? a.updatedAt : a.createdAt); });
    return list;
  }
  function tab(label, value) { return '<button class="filter-tab' + (ui.tab === value ? ' active' : '') + '" data-tab="' + value + '" aria-pressed="' + (ui.tab === value) + '">' + label + '</button>'; }
  function searchField() { return '<div class="search-field">' + icon('search') + '<input id="case-search" type="search" aria-label="Search complaints by ID, title, location, category, resident, or team" placeholder="Search complaints…" value="' + e(ui.search) + '"></div>'; }
  function complaintTable(compact) {
    if (!state.cases.length) {
      var role = state.actor.role;
      return '<section class="panel workspace-empty">' + icon(role === 'personnel' ? 'tool' : 'inbox') + '<h3>' + (role === 'resident' ? 'Your first report starts here' : role === 'personnel' ? 'No work assigned yet' : 'Your complaint register is ready') + '</h3><p>' + (role === 'resident' ? 'Report a community concern, suggest a solution, and follow the barangay’s response.' : role === 'personnel' ? 'Complaints assigned to ' + e(state.actor.team) + ' will appear here.' : 'Residents can now create accounts and submit concerns. Create personnel accounts so your teams can receive assignments.') + '</p>' + (role === 'resident' ? primaryButton() : role === 'official' && !isDemo() ? '<button class="btn btn-primary" data-nav="users">' + icon('users') + 'Manage personnel accounts</button>' : '') + '</section>';
    }
    var all = filteredCases(), rows = compact ? all.slice(0, 6) : all;
    var tabs = tab('All concerns', 'all') + tab('Needs action', 'pending') + tab('Verified', 'verified');
    if (['all', 'pending', 'verified'].indexOf(ui.tab) < 0) tabs += tab({assessment: 'Assessment', progress: 'In progress', resolved: 'To verify', urgent: 'Urgent', reopened: 'Ever reopened'}[ui.tab], ui.tab);
    return '<section class="panel"><div class="panel-header"><div><h2 class="panel-title">' + (compact ? 'Recent complaints' : ui.page === 'history' ? 'Complaint outcomes' : 'Complaint register') + ' <span class="count-pill">' + all.length + '</span></h2>' + (!compact ? '<p class="panel-subtitle">Open a complaint to view its details and available actions.</p>' : '') + '</div>' + (compact ? '<button class="link-button" data-nav="complaints">View all ' + icon('arrow') + '</button>' : '') + '</div>' +
      '<div class="panel-toolbar"><div class="filter-tabs" aria-label="Complaint filters">' + tabs + '</div>' + (compact ? searchField() : '') + '</div>' +
      (!compact ? '<div class="filter-row pt-3">' + searchField() + '<select class="form-select form-select-sm" id="category-filter" aria-label="Filter by category">' + options(state.categories, ui.category, 'All categories') + '</select><select class="form-select form-select-sm" id="priority-filter" aria-label="Filter by priority">' + options(state.priorities, ui.priority, 'All priorities') + '</select><select class="form-select form-select-sm" id="status-filter" aria-label="Filter by status">' + options(state.statuses, ui.status, 'All statuses') + '</select></div>' : '') +
      (rows.length ? '<div class="table-responsive"><table class="table complaint-table"><caption class="visually-hidden">Complaints available to your account</caption><thead><tr><th scope="col">Complaint</th><th scope="col" class="category-cell">Category</th><th scope="col">Priority</th><th scope="col">Status & next step</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead><tbody>' + rows.map(function (c) { return '<tr><td data-label="Complaint"><button class="case-link" data-open="' + e(c.id) + '">' + e(c.title) + '</button><div class="case-meta"><span class="case-ref">' + e(c.id) + '</span>' + icon('pin') + e(c.location) + '</div></td><td class="category-cell" data-label="Category">' + e(c.category) + '</td><td data-label="Priority">' + priority(c) + '</td><td data-label="Status">' + status(c) + '<span class="next-step">' + e(nextStep(c)) + '</span></td><td class="open-cell"><button class="open-case-btn" data-open="' + e(c.id) + '">Open ' + icon('chevron') + '</button></td></tr>'; }).join('') + '</tbody></table></div>' :
      '<div class="empty-state">' + icon('inbox') + '<h3>No complaints match this view</h3><p>Try a different search or clear the filters.</p><button class="btn btn-light btn-sm" data-clear>Clear all filters</button></div>') +
      '<div class="table-foot"><span>Showing ' + rows.length + ' of ' + all.length + ' complaints</span>' + ((ui.tab !== 'all' || ui.search || ui.category || ui.priority || ui.status) ? '<button class="link-button" data-clear>Clear filters</button>' : '<span>Updated with each action</span>') + '</div></section>';
  }
  function pageContent() {
    var a = state.actor, m = metrics();
    if (ui.page === 'users') return usersPage();
    if (ui.page === 'profile') return profilePage();
    if (ui.page === 'overview') return heading(a.role === 'resident' ? 'Your community, your concerns' : a.role === 'personnel' ? 'Your team’s work overview' : 'Complaint overview', a.role === 'resident' ? 'Report a concern and follow the action taken by your barangay.' : 'Keep community concerns moving toward a verified resolution.', (a.role === 'official' ? exportButton() : '') + primaryButton()) + stats() + banner() +
      '<div class="overview-grid"><div>' + complaintTable(true) + bottomPanels() + '</div><aside class="side-stack"><section class="panel"><div class="panel-header"><h2 class="panel-title">Complaints by category</h2></div>' + chart(state.cases, false) + '<div class="chart-note">Top categories · ' + m.total + ' total reports</div></section>' + activity() + '<section class="workflow-card">' + icon('shield') + '<h3>Resolution is a shared effort.</h3><p>The barangay recommends and acts.<br>The resident confirms the outcome.</p><button class="link-button" data-help>Explore the complaint journey ' + icon('arrow') + '</button></section></aside></div>';
    if (ui.page === 'complaints') return heading(a.role === 'resident' ? 'My complaints' : a.role === 'personnel' ? 'Assigned work' : 'All complaints', a.role === 'resident' ? 'Your reports, recommendations, and progress in one place.' : a.role === 'personnel' ? 'Work assigned to ' + e(a.team) + '.' : 'Review concerns, recommend actions, and coordinate the response.', a.role === 'resident' ? primaryButton() : a.role === 'official' ? exportButton() : '') + complaintTable(false);
    if (ui.page === 'history') return heading('Resolution history', 'Resolution attempts, resident decisions, and referrals — with their full timelines.', a.role === 'official' ? exportButton() : '') + complaintTable(false);
    if (ui.page === 'insights') return reports();
    return knowledge();
  }
  function reports() {
    var m = metrics(), locations = group(state.cases, function (c) { var match = c.location.match(/Purok \d+/i); return match ? match[0] : c.location; });
    return heading('Reports & insights', 'Use complaint history to understand recurring concerns and community needs.', exportButton()) +
      '<div class="stats-grid">' + stat('Resident-verified', m.verified, 'Confirmed outcomes', 'checkCircle', 'green', 'verified') + stat('Awaiting verification', m.resolved, 'Work marked resolved by personnel', 'clock', 'blue', 'resolved') + stat('Ever reopened', m.reopened, 'Reports needing another attempt', 'refresh', 'rose', 'reopened') + '<div class="stat-card"><div class="stat-top"><span>Average resolution time</span><span class="stat-icon">' + icon('clock') + '</span></div><div class="number">' + m.average + '<small style="font-size:15px;letter-spacing:0"> days</small></div><div class="stat-caption">Submitted → latest resolution*</div></div></div>' +
      '<div class="report-grid"><section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns by category</h2></div>' + chart(state.cases, true) + '</section><section class="panel"><div class="panel-header"><div><h2 class="panel-title">Frequently reported locations</h2><p class="panel-subtitle">Grouped by purok when available</p></div></div><div class="report-body">' + locations.map(function (r) { return '<div class="insight-row"><span>' + e(r.label) + '</span><strong>' + r.count + ' reports</strong></div>'; }).join('') + '</div></section>' +
      '<section class="panel"><div class="panel-header"><h2 class="panel-title">Complaint status breakdown</h2></div><div class="report-body">' + group(state.cases, 'status').map(function (r) { return '<div class="insight-row">' + status({status: r.label}) + '<strong>' + r.count + '</strong></div>'; }).join('') + '</div></section>' +
      '<section class="panel"><div class="panel-header"><h2 class="panel-title">Concerns reported more than once</h2></div><div class="report-body">' + group(state.cases, 'category').filter(function (r) { return r.count > 1; }).map(function (r) { return '<div class="insight-row"><span>' + e(r.label) + '</span><strong>' + r.count + ' reports</strong></div>'; }).join('') + '<p class="form-text mt-3">Repeated categories are a planning signal, not proof that the same problem recurred.</p></div></section></div><p class="form-text mt-3">All statistics use the ' + (isDemo() ? 'demo session' : 'saved complaint records') + '. *Average includes currently Resolved and Verified complaints; reopened, referred, and rejected cases are excluded.</p>';
  }
  function knowledge() {
    var groups = group(state.cases.filter(function (c) { return verified(c) && c.resolution; }), 'category');
    return heading('Solution library', 'Learn from actions recorded in previously verified complaints.') + '<div class="info-callout">' + icon('book') + ' Matches use complaint categories and verified records. Previous actions are reference material; the official reviews and approves the recommendation for each new concern.</div><div class="knowledge-grid">' +
      (groups.length ? groups.map(function (g) {
        var c = state.cases.filter(function (x) { return x.category === g.label && verified(x) && x.resolution; }).sort(function (x, y) { return new Date(y.updatedAt) - new Date(x.updatedAt); })[0];
        return '<section class="knowledge-card"><span class="status status-verified">' + g.count + ' verified case' + (g.count === 1 ? '' : 's') + '</span><h3>' + e(g.label) + '</h3><p>' + e(c.resolution.notes) + '</p><div class="small-meta">Latest reference: ' + e(c.id) + ' · Verified ' + date(c.updatedAt) + '</div><button class="link-button" data-open="' + e(c.id) + '">Read case & timeline ' + icon('arrow') + '</button></section>';
      }).join('') : '<div class="empty-state"><h3>No verified cases yet</h3><p>Resident-verified resolutions will appear here as reference material.</p></div>') + '</div>';
  }
  function steps(c) {
    var names = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Verified'], labels = ['Report', 'Assess & recommend', 'Assign', 'Take action', 'Resolve', 'Verify'];
    var index = names.indexOf(c.status); if (c.status === 'Reopened') index = 1;
    return '<div class="workflow-steps" aria-label="Complaint progress">' + labels.map(function (label, i) { return '<div class="workflow-step ' + (i < index ? 'complete' : i === index ? 'current' : '') + '"' + (i === index ? ' aria-current="step"' : '') + '><span class="step-circle">' + (i < index ? icon('check') : (i + 1)) + '</span><span>' + label + '</span></div>'; }).join('') + '</div>';
  }
  function photoHtml(photo, label) { return photo ? '<img class="case-photo" src="' + e(photo) + '" alt="' + e(label) + '"><div class="photo-label">' + e(label) + '</div>' : '<div class="photo-label">No ' + e(label.toLowerCase()) + ' attached.</div>'; }
  function uploadField(id, label) { return '<label class="form-label" for="' + id + '">' + label + ' <span class="text-muted fw-normal">(optional)</span></label><div class="upload-zone"><input class="form-control form-control-sm" type="file" id="' + id + '" name="photoFile" accept="image/jpeg,image/png,image/webp" aria-describedby="' + id + '-help"><p class="form-text" id="' + id + '-help">JPG, PNG, or WebP · Up to 1 MB · ' + (isDemo() ? 'Stored in this demo session.' : 'Saved with the complaint record.') + '</p><div data-preview="' + id + '"></div></div>'; }
  function actionPanel(c) {
    var a = state.actor, html = '';
    if (a.role === 'official' && review(c)) {
      html = '<section class="action-panel"><h3>Assess & recommend</h3><p>Review the resident’s suggestion and record the barangay’s official plan.</p><form data-action="assess" data-id="' + e(c.id) + '"><div class="row"><div class="col-sm-8"><label class="form-label" for="assess-category">Complaint category</label><select class="form-select" id="assess-category" name="category" required>' + options(state.categories, c.category) + '</select></div><div class="col-sm-4"><label class="form-label" for="assess-priority">Priority</label><select class="form-select" id="assess-priority" name="priority" required>' + options(state.priorities, c.priority) + '</select></div><div class="col-12"><label class="form-label" for="recommendation">Barangay recommended action <span class="text-danger">*</span></label><textarea class="form-control" id="recommendation" name="recommendation" rows="4" maxlength="4000" required placeholder="Describe the inspection, action, and follow-up the barangay recommends.">' + e(c.recommendation) + '</textarea></div><div class="col-12"><label class="form-label" for="assessment">Assessment notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="assessment" name="assessment" rows="2" maxlength="3000">' + e(c.assessment) + '</textarea></div></div><div class="action-footer"><small class="form-text">The final recommendation comes from the barangay.</small><button class="btn btn-primary" type="submit">' + icon('check') + 'Save assessment</button></div></form>';
      if (c.status === 'Under Review' && c.recommendation) html += '<div class="section-divider"></div><h3>Assign the recommended action</h3><form data-action="assign" data-id="' + e(c.id) + '"><label class="form-label" for="assign-team">Responsible personnel or team</label><select class="form-select" id="assign-team" name="team" required>' + options(state.teams, c.team, 'Choose a responsible team') + '</select><div class="action-footer"><small class="form-text">The team will see this in its assigned work.</small><button class="btn btn-primary" type="submit">' + icon('users') + 'Assign complaint</button></div></form>';
      html += '<details class="mt-4"><summary class="link-button">Return, refer, or reject this report</summary><form class="mt-3" data-action="exception" data-id="' + e(c.id) + '"><label class="form-label" for="exception-status">Alternative assessment outcome</label><select id="exception-status" name="status" class="form-select mb-3">' + options(['Returned for Information', 'Referred to Another Office', 'Rejected'], 'Returned for Information') + '</select><div id="office-wrap" hidden><label class="form-label" for="receiving-office">Receiving office</label><input class="form-control mb-3" id="receiving-office" name="office" maxlength="200"></div><label class="form-label" for="exception-notes">Reason and next steps</label><textarea class="form-control" id="exception-notes" name="notes" maxlength="3000" required></textarea><button class="btn btn-light mt-3" type="submit">Record outcome</button></form></details></section>';
    } else if (a.role === 'personnel' && c.status === 'Assigned') {
      html = '<section class="action-panel"><h3>Ready to begin?</h3><p>Review the recommended action above, then accept the assignment and start work.</p><button class="btn btn-primary" data-start="' + e(c.id) + '">' + icon('tool') + 'Accept & start work</button></section>';
    } else if (a.role === 'personnel' && c.status === 'In Progress') {
      html = '<section class="action-panel"><h3>Record a progress update</h3><form data-action="note" data-id="' + e(c.id) + '"><label class="form-label" for="progress-notes">Action or update</label><textarea class="form-control" id="progress-notes" name="notes" required maxlength="4000" placeholder="What has the team done so far?"></textarea><button class="btn btn-light mt-3" type="submit">Add progress note</button></form><div class="section-divider"></div><h3>Record the resolution</h3><p>Explain what was done and add completion evidence when available.</p><form data-action="resolve" data-id="' + e(c.id) + '"><label class="form-label" for="resolution-notes">Work performed <span class="text-danger">*</span></label><textarea class="form-control" id="resolution-notes" name="notes" required maxlength="4000" rows="4" placeholder="Describe the completed action and how the outcome was checked."></textarea><div class="mt-3">' + uploadField('resolution-photo', 'Completion photo') + '</div><div class="action-footer"><small class="form-text">The reporting resident will verify the result.</small><button class="btn btn-primary" type="submit">' + icon('checkCircle') + 'Mark as resolved</button></div></form></section>';
    } else if (a.role === 'resident' && c.status === 'Resolved') {
      html = '<section class="action-panel"><h3>Was your concern resolved?</h3><p>Review the team’s resolution and check the actual result. Your confirmation closes the complaint.</p><form data-action="verification" data-id="' + e(c.id) + '"><label class="form-label" for="feedback">Feedback <span class="text-muted fw-normal">(required if the problem remains)</span></label><textarea class="form-control" id="feedback" name="feedback" maxlength="3000" placeholder="Tell the barangay about the result."></textarea><div class="action-footer"><button class="btn btn-light" type="submit" name="decision" value="reopen">' + icon('refresh') + 'Problem still exists</button><button class="btn btn-primary" type="submit" name="decision" value="verify">' + icon('checkCircle') + 'Yes, resolved</button></div></form></section>';
    } else if (a.role === 'resident' && c.status === 'Returned for Information') {
      html = '<section class="action-panel"><h3>Provide the requested information</h3><p>Your response will return this complaint to the barangay’s review queue.</p><form data-action="information" data-id="' + e(c.id) + '"><label class="form-label" for="additional-info">Additional details</label><textarea class="form-control" name="notes" id="additional-info" required maxlength="2000" placeholder="Add the location, landmark, or information requested."></textarea><div class="mt-3">' + uploadField('additional-photo', 'Updated supporting photo') + '</div><button class="btn btn-primary mt-3" type="submit">Send information</button></form></section>';
    } else {
      var next = {Submitted: 'The barangay official will review this report and provide a recommended action.', 'Under Review': 'The barangay official is assessing this concern and preparing the assignment.', Assigned: 'The assigned team can sign in and accept this work.', 'In Progress': 'The assigned team is carrying out the recommended action.', Resolved: 'The reporting resident can now confirm the result or reopen the complaint.', Verified: 'The reporting resident confirmed the resolution. This complaint is part of the historical record.', Reopened: 'The barangay official will reassess this concern and coordinate another action.', 'Returned for Information': 'The reporting resident needs to provide the information requested.', Rejected: 'The reason for rejecting this report is recorded in its timeline.', 'Referred to Another Office': 'This concern was referred to ' + (c.referral || 'another office') + '. A referral is not a verified resolution.'};
      html = '<div class="info-callout mt-4 mb-0">' + icon('clock') + ' ' + e(next[c.status]) + '</div>';
    }
    return html;
  }
  function related(c) {
    if (state.actor.role !== 'official' || !review(c)) return '';
    var matches = state.cases.filter(function (x) { return x.id !== c.id && x.category === c.category && verified(x) && x.resolution; });
    return '<div class="related-box"><h4>' + icon('book') + ' Similar verified cases <span class="count-pill">' + matches.length + '</span></h4>' +
      (matches.length ? matches.slice(0, 3).map(function (x) { return '<div class="related-case"><strong>' + e(x.id) + ' · ' + e(x.title) + '</strong><p>' + e(x.resolution.notes) + '</p><button class="link-button mt-2" data-use-recommendation="' + e(x.id) + '">Use previous recommendation as a draft</button></div>'; }).join('') : '<p class="mb-0" style="font-size:12px">No verified cases in this category yet.</p>') + '<p class="form-text mb-0 mt-2">Category match only. Review applicability before saving the official action.</p></div>';
  }
  function renderCase() {
    var c = find(currentCase); if (!c) { caseModal.hide(); return; }
    var exception = ['Reopened', 'Returned for Information', 'Rejected', 'Referred to Another Office'].indexOf(c.status) >= 0;
    document.getElementById('case-content').innerHTML =
      '<div class="modal-header"><div><div class="eyebrow">COMPLAINT ' + e(c.id) + ' &nbsp; / &nbsp; ' + e(c.category) + '</div><h2 class="modal-title" id="case-heading">' + e(c.title) + '</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close complaint details"></button></div>' +
      '<div class="modal-body"><div class="case-summary"><div class="summary-item"><span class="label">Current status</span>' + status(c) + '</div><div class="summary-item"><span class="label">Priority</span>' + priority(c) + '</div><div class="summary-item"><span class="label">Reported by</span><strong>' + e(c.resident) + '</strong></div><div class="summary-item"><span class="label">Submitted</span><strong>' + date(c.createdAt, true) + '</strong></div><div class="summary-item"><span class="label">Assigned team</span><strong>' + e(c.team || 'Not yet assigned') + '</strong></div></div>' + steps(c) +
      '<div class="case-layout"><div class="case-main">' + (exception ? '<div class="status-note"><strong>' + e(c.status) + '</strong><br>' + e(c.timeline[c.timeline.length - 1].note) + '</div>' : '') +
      '<section class="case-section"><h3>' + icon('inbox') + 'Reported concern</h3><p class="case-description">' + e(c.description) + '</p><p>' + icon('pin') + ' ' + e(c.location) + '</p>' + photoHtml(c.photo, 'Supporting photo') + '</section>' +
      '<section class="case-section suggestion-box"><div class="eyebrow">RESIDENT SUGGESTED SOLUTION</div><p>' + e(c.suggestion || 'No solution suggested by the resident.') + '</p></section>' +
      '<section class="case-section recommendation-box"><h3>' + icon('shield') + 'Barangay recommended action</h3><p>' + e(c.recommendation || 'The barangay has not recorded an official recommendation yet.') + '</p>' + (c.recommendation ? '<div class="recommendation-author">Official assessment · ' + e(c.assessment || 'No additional notes') + '</div>' : '') + '</section>' +
      (c.resolution ? '<section class="case-section"><h3>' + icon('checkCircle') + (c.status === 'Reopened' ? 'Previous resolution attempt' : 'Recorded resolution') + '</h3><p>' + e(c.resolution.notes) + '</p><div class="photo-label">' + e(c.resolution.team) + ' · ' + date(c.resolution.date, true) + '</div>' + photoHtml(c.resolution.photo, 'Completion photo') + '</section>' : '') +
      (c.feedback ? '<section class="case-section suggestion-box"><div class="eyebrow">RESIDENT FEEDBACK</div><p>' + e(c.feedback) + '</p></section>' : '') +
      related(c) + actionPanel(c) + '</div><aside class="case-timeline"><h3>' + icon('clock') + 'Activity timeline</h3>' +
      c.timeline.slice().reverse().map(function (t) { return '<div class="timeline-entry"><strong>' + e(t.title) + '</strong><span class="timeline-date">' + date(t.date, true) + '<br>' + e(t.actor) + '</span><p>' + e(t.note) + '</p>' + (t.photo ? '<img class="case-photo" src="' + e(t.photo) + '" alt="Completion evidence recorded with this event">' : '') + '</div>'; }).join('') + '</aside></div></div>';
  }
  function openCase(id, trigger) {
    function show() {
      if (!find(id)) { error(new Error('This complaint is no longer available to your account.')); return; }
      currentCase = id; caseOpener = trigger || null; renderCase(); caseModal.show(trigger);
    }
    if (isDemo()) show();
    else api().then(function (data) { state = data; render(); show(); }).catch(error);
  }
  function newComplaint(trigger) {
    if (state.actor.role !== 'resident') return;
    formOpener = trigger || null;
    document.getElementById('form-content').innerHTML =
      '<div class="modal-header"><div><div class="eyebrow">RESIDENT PORTAL · ' + e(state.actor.name) + '</div><h2 class="modal-title" id="form-heading">Report a community concern</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close report form"></button></div>' +
      '<div class="modal-body"><form id="new-complaint-form" data-action="submit"><div class="new-form-body"><div class="info-callout">Describe the concern clearly and include a nearby landmark. The barangay will assess the report and decide on the recommended action.</div><div class="row"><div class="col-12"><label class="form-label" for="new-title">Complaint title <span class="text-danger">*</span></label><input class="form-control" id="new-title" name="title" required maxlength="140" placeholder="e.g. Clogged drainage along Mabini Street"></div><div class="col-md-6"><label class="form-label" for="new-category">Category <span class="text-danger">*</span></label><select class="form-select" id="new-category" name="category" required>' + options(state.categories, '', 'Choose a category') + '</select></div><div class="col-md-6"><label class="form-label" for="new-location">Location / landmark <span class="text-danger">*</span></label><input class="form-control" id="new-location" name="location" required maxlength="250" placeholder="Street, purok, and nearby landmark"></div><div class="col-12"><label class="form-label" for="new-description">What happened? <span class="text-danger">*</span></label><textarea class="form-control" id="new-description" name="description" required rows="4" maxlength="4000" placeholder="Describe the concern, when you noticed it, and how it affects the community."></textarea></div><div class="col-12"><label class="form-label" for="new-suggestion">Your suggested solution <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="new-suggestion" name="suggestion" rows="2" maxlength="2000" placeholder="What do you think could help?"></textarea><p class="form-text">Your suggestion will be reviewed. The barangay makes the final recommendation.</p></div><div class="col-12">' + uploadField('new-photo', 'Supporting photo') + '</div></div></div><div class="form-modal-footer"><small>Submitting as ' + e(state.actor.name) + (isDemo() ? ' · Demo resident' : '') + '<br>Fields marked * are required.</small><button class="btn btn-primary" type="submit">' + icon('plus') + 'Submit complaint</button></div></form></div>';
    formModal.show(trigger);
  }
  function readPhoto(file) {
    return new Promise(function (resolve, reject) {
      if (!file) { resolve(''); return; }
      if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) < 0 || file.size > 1048576) { reject(new Error('Choose a JPG, PNG, or WebP image smaller than 1 MB.')); return; }
      var reader = new FileReader(); reader.onload = function () { resolve(reader.result); }; reader.onerror = function () { reject(new Error('The photo could not be read. Please select it again.')); }; reader.readAsDataURL(file);
    });
  }
  function mutate(action, data, id, form) {
    if (busy) return Promise.resolve(null);
    busy = true;
    document.body.classList.add('app-busy');
    if (form) form.setAttribute('aria-busy', 'true');
    var buttons = form ? Array.prototype.slice.call(form.querySelectorAll('button')) : [];
    buttons.forEach(function (b) { b.disabled = true; });
    return api(action, data, id).then(function (result) {
      state = result.state; render();
      if (action === 'submit') {
        document.getElementById('form-modal').addEventListener('hidden.bs.modal', function afterSubmit() {
          document.getElementById('form-modal').removeEventListener('hidden.bs.modal', afterSubmit);
          openCase(result.id);
        });
        formModal.hide();
      } else if (action === 'create_user' || action === 'update_user') {
        formModal.hide();
      } else if (currentCase) {
        renderCase();
        var heading = document.getElementById('case-heading');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
      }
      var messages = {submit: 'Complaint submitted. Reference: ' + result.id, assess: 'Assessment and official recommendation saved.', assign: 'Complaint assigned to the selected team.', start: 'Work started. You can now record progress.', note: 'Progress update added to the timeline.', resolve: 'Resolution recorded. Awaiting resident verification.', verify: 'Resolution verified. Complaint closed.', reopen: 'Complaint reopened for barangay reassessment.', information: 'Additional information sent for review.', exception: 'Assessment outcome recorded.'};
      if (messages[action]) Toast.fire({icon: 'success', title: messages[action]});
      if (['create_user', 'update_user', 'profile'].indexOf(action) >= 0) Toast.fire({icon: 'success', title: action === 'create_user' ? 'Account created.' : action === 'profile' ? 'Your profile was saved.' : 'Account access updated.'});
      return result;
    }).catch(function (err) {
      if (err.status === 409) {
        render();
        if (currentCase && find(currentCase)) renderCase();
      }
      error(err);
      return null;
    }).finally(function () {
      busy = false;
      document.body.classList.remove('app-busy');
      if (form) form.removeAttribute('aria-busy');
      buttons.forEach(function (b) { b.disabled = false; });
    });
  }
  function help() {
    dialog({title: 'One concern. A complete journey.', html: '<div class="text-start"><p><strong>1. Resident:</strong> report a concern and suggest a solution.</p><p><strong>2. Official:</strong> assess the category and priority, save the official recommendation, then assign a team.</p><p><strong>3. Personnel:</strong> use the assigned team’s view, start work, record updates, and submit the resolution.</p><p><strong>4. Resident:</strong> verify the result or reopen the concern.</p><hr><p class="small text-muted mb-0">' + (isDemo() ? 'Use “Explore as” to switch roles. The demo resident is Alex Santos. Demo changes stay in your PHP session and may expire. Reset restores fictional sample data.' : 'Your account determines which complaints and actions are available. Officials manage personnel accounts and teams. Saved complaints and photos remain available after sign-out.') + ' No notifications are sent.</p></div>', confirmButtonText: 'Explore the workspace', width: 620});
  }
  document.addEventListener('click', function (event) {
    var target = event.target.closest('button,a'); if (!target || !state) return;
    if (target.hasAttribute('data-nav')) navigate(target.getAttribute('data-nav'));
    else if (target.hasAttribute('data-quick')) quick(target.getAttribute('data-quick'));
    else if (target.hasAttribute('data-tab')) { ui.tab = target.getAttribute('data-tab'); render(); }
    else if (target.hasAttribute('data-open')) openCase(target.getAttribute('data-open'), target);
    else if (target.hasAttribute('data-new')) newComplaint(target);
    else if (target.hasAttribute('data-menu')) { ui.mobile = !ui.mobile; render(); }
    else if (target.hasAttribute('data-clear')) { resetFilters(); render(); }
    else if (target.hasAttribute('data-help')) help();
    else if (target.hasAttribute('data-add-user')) userForm(null, target);
    else if (target.hasAttribute('data-edit-user')) userForm(target.getAttribute('data-edit-user'), target);
    else if (target.hasAttribute('data-logout')) signOut();
    else if (target.hasAttribute('data-refresh')) api().then(function (data) { state = data; render(); Toast.fire({icon: 'success', title: 'Workspace refreshed.'}); }).catch(error);
    else if (target.hasAttribute('data-start')) mutate('start', {}, target.getAttribute('data-start'));
    else if (target.hasAttribute('data-use-recommendation')) {
      var previous = find(target.getAttribute('data-use-recommendation')), textarea = document.getElementById('recommendation');
      if (previous && textarea) { textarea.value = previous.recommendation || previous.resolution.notes; textarea.focus(); textarea.scrollIntoView({block: 'center', behavior: 'smooth'}); Toast.fire({icon: 'info', title: 'Draft copied. Review it before saving the assessment.'}); }
    } else if (target.hasAttribute('data-reset')) {
      dialog({icon: 'question', title: 'Reset this demo session?', text: 'Your added complaints, changes, and photos will be removed. The fictional sample reports will be restored.', showCancelButton: true, confirmButtonText: 'Reset sample data', cancelButtonText: 'Keep my changes'}).then(function (r) { if (r.isConfirmed) mutate('reset').then(function (result) { if (result) { currentCase = null; resetFilters(); render(); Toast.fire({icon: 'success', title: 'Sample data restored.'}); } }); });
    }
  });
  document.addEventListener('input', function (event) { if (event.target.id === 'case-search') { ui.search = event.target.value; render(true); } });
  document.addEventListener('change', function (event) {
    var target = event.target;
    if (target.id === 'role-select' || target.id === 'team-select') {
      var role = document.getElementById('role-select').value, team = document.getElementById('team-select');
      var previousRole = state.actor.role;
      mutate('switch_role', {role: role, team: team ? team.value : undefined}).then(function (result) { if (result) { currentCase = null; navigate('overview'); Toast.fire({icon: 'info', title: role === previousRole ? 'Viewing ' + state.actor.name : 'Switched to ' + (role === 'official' ? 'barangay official' : role) + ' view.'}); } else render(); });
    } else if (['category-filter', 'priority-filter', 'status-filter'].indexOf(target.id) >= 0) {
      ui[target.id.replace('-filter', '')] = target.value; render();
    } else if (target.id === 'exception-status') {
      document.getElementById('office-wrap').hidden = target.value !== 'Referred to Another Office';
      document.getElementById('receiving-office').required = target.value === 'Referred to Another Office';
    } else if (target.id === 'user-role') {
      var teamField = document.getElementById('user-team');
      document.getElementById('user-team-wrap').hidden = target.value !== 'personnel';
      teamField.required = target.value === 'personnel';
    } else if (target.type === 'file') {
      var preview = document.querySelector('[data-preview="' + target.id + '"]');
      readPhoto(target.files[0]).then(function (photo) { if (preview) preview.innerHTML = photo ? '<img class="upload-preview" src="' + e(photo) + '" alt="Selected photo preview">' : ''; }).catch(function (err) { target.value = ''; if (preview) preview.innerHTML = ''; error(err); });
    }
  });
  document.addEventListener('submit', function (event) {
    var form = event.target; if (!form.hasAttribute('data-action')) return;
    event.preventDefault(); if (busy || preparing) return;
    var action = form.getAttribute('data-action'), id = form.getAttribute('data-id'), data = {};
    var fields = new FormData(form);
    fields.forEach(function (value, key) { if (typeof value === 'string') data[key] = key.indexOf('password') >= 0 ? value : value.trim(); });
    if (action === 'verification') action = event.submitter ? event.submitter.value : 'verify';
    if (action === 'reopen' && !data.feedback) { document.getElementById('feedback').focus(); error(new Error('Please describe what still needs attention before reopening the complaint.')); return; }
    var required = Array.prototype.slice.call(form.querySelectorAll('[required]'));
    var blank = required.filter(function (input) { return !input.value.trim(); })[0];
    if (blank) { blank.focus(); error(new Error('Please complete all required fields. A field cannot contain only spaces.')); return; }
    var file = form.querySelector('input[type="file"]');
    preparing = true;
    readPhoto(file && file.files[0]).then(function (photo) {
      if (file) data.photo = photo;
      if (['resolve', 'reopen', 'verify', 'exception'].indexOf(action) >= 0) {
        var messages = {resolve: ['Record this resolution?', 'The reporting resident will still need to verify the result.', 'Mark as resolved'], reopen: ['Reopen this complaint?', 'Your feedback will return the concern to the barangay for reassessment.', 'Reopen complaint'], verify: ['Confirm the concern is resolved?', 'This will close the complaint as resident-verified.', 'Confirm resolution'], exception: ['Record this assessment outcome?', 'The selected outcome and your reason will be added to the complaint timeline.', 'Record outcome']};
        return dialog({icon: 'question', title: messages[action][0], text: messages[action][1], showCancelButton: true, confirmButtonText: messages[action][2], cancelButtonText: 'Go back'}).then(function (r) { if (r.isConfirmed) return mutate(action, data, id, form); });
      }
      return mutate(action, data, id, form);
    }).catch(error).finally(function () { preparing = false; });
  });
  document.getElementById('case-modal').addEventListener('hidden.bs.modal', function () {
    var fallback = currentCase && document.querySelector('[data-open="' + currentCase + '"]');
    currentCase = null;
    if (caseOpener && caseOpener.isConnected) caseOpener.focus();
    else if (fallback) fallback.focus();
    else { var nav = document.querySelector('[aria-current="page"]'); if (nav) nav.focus(); }
  });
  document.getElementById('form-modal').addEventListener('shown.bs.modal', function () { var input = document.getElementById('new-title'); if (input) input.focus(); });
  document.getElementById('form-modal').addEventListener('hidden.bs.modal', function () {
    if (formOpener && formOpener.isConnected) formOpener.focus();
    else { var fallback = document.querySelector('[data-new]'); if (fallback) fallback.focus(); }
  });
  api().then(function (data) { state = data; render(); }).catch(function (err) {
    document.getElementById('app').innerHTML = '<main class="error-panel"><h1>Unable to open the workspace</h1><p>' + e(err.message) + '</p><p>Open this project through XAMPP’s Apache server or the PHP development server.</p><a class="btn btn-primary" href="./">Try again</a></main>';
  });
}());
