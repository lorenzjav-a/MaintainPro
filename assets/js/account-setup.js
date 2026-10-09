(function () {
  'use strict';
  var url = new URL(window.location.href);
  if (!url.searchParams.has('token')) return;
  window.history.replaceState(null, document.title, url.pathname + url.hash);
}());
