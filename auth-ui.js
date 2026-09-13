(function () {
  'use strict';
  var busy = false;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
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
        var destinations = ['index.php', 'login.php', 'login.php?view=verify', 'login.php?view=reset'];
        window.location.assign(destinations.indexOf(body.redirect) >= 0 ? body.redirect : 'login.php');
      })
      .catch(function (err) { Swal.fire({icon: 'error', title: 'Unable to continue', text: err.message, confirmButtonText: 'Try again'}); })
      .finally(function () {
        busy = false;
        document.body.classList.remove('app-busy');
        document.getElementById('auth-form').removeAttribute('aria-busy');
        buttons.forEach(function (button) { button.disabled = false; });
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
  if (resend) resend.addEventListener('click', function () { send('request_reset', {}); });
  var showPassword = document.getElementById('show-password');
  if (showPassword) showPassword.addEventListener('change', function (event) {
    document.getElementById('account-password').type = event.target.checked ? 'text' : 'password';
    var confirm = document.getElementById('confirm-password');
    if (confirm) confirm.type = event.target.checked ? 'text' : 'password';
  });
}());
