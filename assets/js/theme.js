(function () {
  'use strict';

  var storageKey = 'maintainpro:theme';
  var root = document.documentElement;
  var allowed = ['light', 'dark'];

  function preference() {
    try {
      var saved = localStorage.getItem(storageKey);
      return allowed.indexOf(saved) >= 0 ? saved : 'light';
    } catch (error) {
      return 'light';
    }
  }

  function updateControls(value) {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      var dark = value === 'dark';
      var next = dark ? 'light' : 'dark';
      button.setAttribute('aria-label', 'Switch to ' + next + ' theme');
      button.setAttribute('title', 'Switch to ' + next + ' theme');
      button.setAttribute('aria-pressed', String(dark));
      var label = button.querySelector('.theme-toggle-label');
      if (label) label.textContent = dark ? 'Dark theme' : 'Light theme';
    });
  }

  function apply(value, announce) {
    root.dataset.theme = value;
    root.dataset.bsTheme = value;
    root.style.colorScheme = value;
    var themeColor = document.querySelector('meta[name="theme-color"]');
    if (!themeColor) {
      themeColor = document.createElement('meta');
      themeColor.name = 'theme-color';
      document.head.appendChild(themeColor);
    }
    if (themeColor) themeColor.content = value === 'dark' ? '#101c21' : '#f4f7f8';
    updateControls(value);
    if (announce) {
      document.dispatchEvent(new CustomEvent('maintainpro:themechange', {
        detail: {theme: value}
      }));
    }
  }

  var current = preference();
  apply(current, false);

  function choose(value) {
    if (allowed.indexOf(value) < 0) return;
    try { localStorage.setItem(storageKey, value); } catch (error) { /* Keep the in-page preference. */ }
    current = value;
    apply(current, true);
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateControls(current);
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-theme-toggle]');
      if (button) choose(current === 'dark' ? 'light' : 'dark');
    });
    requestAnimationFrame(function () { root.dataset.themeReady = 'true'; });
  });

  window.addEventListener('storage', function (event) {
    if (event.key !== storageKey) return;
    current = preference();
    apply(current, true);
  });
}());
