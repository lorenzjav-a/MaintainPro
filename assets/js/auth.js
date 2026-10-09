(function () {
  'use strict';
  var busy = false;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var turnstileContainer = document.getElementById('turnstile-widget');
  var turnstileStatus = document.getElementById('turnstile-status');
  var turnstileWidgetId = null;
  function renderTurnstile() {
    if (!turnstileContainer || !window.turnstile) return;
    if (turnstileWidgetId !== null) window.turnstile.remove(turnstileWidgetId);
    turnstileContainer.replaceChildren();
    turnstileWidgetId = window.turnstile.render(turnstileContainer, {
      sitekey: turnstileContainer.dataset.sitekey,
      action: turnstileContainer.dataset.action,
      theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
      size: 'flexible',
      appearance: 'always',
      'refresh-expired': 'auto',
      'refresh-timeout': 'auto',
      callback: function () { if (turnstileStatus) turnstileStatus.textContent = 'Security verification complete.'; },
      'expired-callback': function () { if (turnstileStatus) turnstileStatus.textContent = 'Security verification expired. Please complete it again.'; },
      'error-callback': function () { if (turnstileStatus) turnstileStatus.textContent = 'Security verification could not load. Check your connection and try again.'; }
    });
  }
  if (turnstileContainer) {
    if (window.turnstile) renderTurnstile();
    else window.addEventListener('load', renderTurnstile, {once: true});
    document.addEventListener('maintainpro:themechange', renderTurnstile);
  }
  function resetTurnstile() {
    if (turnstileWidgetId !== null && window.turnstile) window.turnstile.reset(turnstileWidgetId);
    if (turnstileStatus) turnstileStatus.textContent = 'Security verification is required.';
  }
  function send(action, data) {
    if (busy) return;
    busy = true;
    document.body.classList.add('app-busy');
    document.getElementById('auth-form').setAttribute('aria-busy', 'true');
    var buttons = document.querySelectorAll('button');
    buttons.forEach(function (button) { button.disabled = true; });
    fetch('auth.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({action: action, data: data})})
      .then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.error || 'Please try again.'); return body; }); })
      .then(function (body) {
        var destinations = ['index.php', 'login.php', 'login.php?view=verify', 'login.php?view=reset', 'login.php?view=change-password', 'login.php?view=verify-registration'];
        window.location.assign(destinations.indexOf(body.redirect) >= 0 ? body.redirect : 'login.php');
      })
      .catch(function (err) { resetTurnstile(); Swal.fire({icon: 'error', title: 'Unable to continue', text: err.message, confirmButtonText: 'Try again'}); })
      .finally(function () {
        busy = false;
        document.body.classList.remove('app-busy');
        document.getElementById('auth-form').removeAttribute('aria-busy');
        buttons.forEach(function (button) { button.disabled = false; });
        updateResetResend();
      });
  }
  document.getElementById('auth-form').addEventListener('submit', function (event) {
    event.preventDefault();
    var data = {};
    new FormData(event.target).forEach(function (value, key) { data[key] = key.indexOf('password') >= 0 ? value : value.trim(); });
    if (Object.prototype.hasOwnProperty.call(data, 'confirm_password') && data.password !== data.confirm_password) {
      Swal.fire({icon: 'warning', title: 'Passwords do not match', text: 'Enter the same password in both fields.', confirmButtonText: 'Go back'});
      return;
    }
    send(event.target.getAttribute('data-action'), data);
  });
  var resend = document.getElementById('resend-code');
  var resendReadyAt = resend ? Date.now() + Math.max(0, Number(resend.dataset.seconds) || 0) * 1000 : 0;
  function updateResetResend() {
    if (!resend) return;
    var remaining = Math.max(0, Math.ceil((resendReadyAt - Date.now()) / 1000));
    resend.disabled = busy || remaining > 0;
    resend.textContent = remaining > 0
      ? 'Resend code in ' + String(Math.floor(remaining / 60)).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0')
      : 'Resend Code';
  }
  updateResetResend();
  if (resend) window.setInterval(updateResetResend, 1000);
  var resendRegistration = document.getElementById('resend-registration');
  var signOut = document.getElementById('account-signout');
  if (signOut) signOut.addEventListener('click', function () { send('logout', {}); });
  if (resend) resend.addEventListener('click', function () { send('request_reset', {}); });
  if (resendRegistration) resendRegistration.addEventListener('click', function () { send('resend_registration', {}); });
  var showPassword = document.getElementById('show-password');
  if (showPassword) showPassword.addEventListener('change', function (event) {
    document.getElementById('account-password').type = event.target.checked ? 'text' : 'password';
    var confirm = document.getElementById('confirm-password');
    if (confirm) confirm.type = event.target.checked ? 'text' : 'password';
  });
  var passwordInput = document.getElementById('account-password');
  var strength = document.querySelector('[data-password-strength]');
  if (passwordInput && strength) passwordInput.addEventListener('input', function () {
    var value=passwordInput.value, score=[value.length>=10,value.length>=14,/[a-z]/.test(value)&&/[A-Z]/.test(value),/[0-9]/.test(value),/[^A-Za-z0-9]/.test(value)].filter(Boolean).length;
    strength.textContent='Strength: '+(value.length===0?'waiting for password':score<=2?'basic':score<=4?'good':'strong');
    strength.parentElement.dataset.strength=String(score);
  });
}());
