(function () {
  'use strict';
  var busy = false;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function send(action, data) {
    if (busy) return;
    busy = true;
    var buttons = document.querySelectorAll('button');
    buttons.forEach(function (button) { button.disabled = true; });
    fetch('auth.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({action: action, data: data})})
      .then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.error || 'Please try again.'); return body; }); })
      .then(function (body) { window.location.assign(body.redirect === 'index.php' ? 'index.php' : 'login.php'); })
      .catch(function (err) { Swal.fire({icon: 'error', title: 'Unable to continue', text: err.message, confirmButtonText: 'Try again'}); })
      .finally(function () { busy = false; buttons.forEach(function (button) { button.disabled = false; }); });
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
  document.getElementById('open-demo').addEventListener('click', function () { send('demo', {}); });
  document.getElementById('show-password').addEventListener('change', function (event) {
    document.getElementById('account-password').type = event.target.checked ? 'text' : 'password';
    var confirm = document.getElementById('confirm-password');
    if (confirm) confirm.type = event.target.checked ? 'text' : 'password';
  });
}());
