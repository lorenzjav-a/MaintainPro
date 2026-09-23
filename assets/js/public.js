(function () {
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function values(form) {
    var fd = new FormData(form), data = {};
    fd.forEach(function (value, key) { if (typeof value === 'string') data[key] = value.trim(); });
    data.keyPoints = fd.getAll('keyPoints');
    return data;
  }
  async function send(action, data) {
    var response = await fetch('public-api.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({action: action, data: data})});
    var result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to complete request.');
    return result;
  }
  function node(tag, text, className) {
    var el = document.createElement(tag); el.textContent = text; if (className) el.className = className; return el;
  }
  document.querySelectorAll('[data-concern-choices]').forEach(function (wrapper) {
    var catalog = JSON.parse(wrapper.querySelector('.concern-catalog').textContent);
    var category = wrapper.querySelector('[name=category]'), type = wrapper.querySelector('[name=concernType]'), points = wrapper.querySelector('[data-key-points]');
    function update(initial) {
      var selected = initial ? JSON.parse(wrapper.dataset.selectedPoints || '[]') : [];
      type.replaceChildren(new Option('Select concern type', ''));
      (catalog.types[category.value] || []).forEach(function (value) { type.add(new Option(value, value)); });
      if (initial) type.value = wrapper.dataset.selectedType || '';
      if (points) {
        points.replaceChildren();
        (catalog.points[category.value] || []).forEach(function (value) {
          var label = node('label', '', 'choice-card'), input = document.createElement('input');
          input.type = 'checkbox'; input.name = 'keyPoints'; input.value = value; input.checked = selected.includes(value); input.className = 'form-check-input';
          label.append(input, node('span', value)); points.append(label);
        });
      }
    }
    category.addEventListener('change', function () { update(false); }); update(true);
  });
  var report = document.getElementById('public-report'), track = document.getElementById('public-track');
  var loadRule = document.getElementById('load-rule');
  if (loadRule) loadRule.addEventListener('click', async function () {
    var form = loadRule.closest('form');
    try { var result = await send('suggestions', values(form)); result.suggestions.forEach(function (text, index) { form.querySelector('[name=action' + (index + 1) + ']').value = text; }); }
    catch (error) { Swal.fire({icon: 'error', text: error.message}); }
  });
  var generation = 0, busy = false;
  if (report) {
    report.querySelector('[data-concern-choices]').addEventListener('change', async function () {
      var version = ++generation, container = document.getElementById('suggestions'), data = values(report);
      container.replaceChildren(node('p', data.concernType ? 'Loading suggestions…' : 'Select a category and concern type.'));
      if (!data.concernType) return;
      try {
        var result = await send('suggestions', data);
        if (version !== generation) return;
        container.replaceChildren();
        result.suggestions.forEach(function (text, index) {
          var label = node('label', '', 'suggestion-choice'), radio = document.createElement('input');
          radio.type = 'radio'; radio.name = 'selectedSuggestion'; radio.value = String(index); radio.className = 'form-check-input';
          label.append(radio, node('span', text)); container.append(label);
        });
        var skip = node('label', '', 'suggestion-choice'), input = document.createElement('input');
        input.type = 'radio'; input.name = 'selectedSuggestion'; input.value = ''; input.checked = true; input.className = 'form-check-input';
        skip.append(input, node('span', 'No preference — let the barangay assess.')); container.append(skip);
      } catch (error) { if (version === generation) container.replaceChildren(node('p', error.message)); }
    });
    report.addEventListener('submit', async function (event) {
      event.preventDefault(); if (busy) return;
      busy = true; var button = report.querySelector('button[type=submit]'); button.disabled = true;
      var errorBox = document.getElementById('public-error'); errorBox.hidden = true;
      try {
        var data = values(report), file = report.querySelector('input[type=file]').files[0];
        if (file) {
          if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 1048576) throw new Error('Use a JPG, PNG or WebP photo no larger than 1 MB.');
          data.photo = await new Promise(function (resolve, reject) { var reader = new FileReader(); reader.onload = function () { resolve(reader.result); }; reader.onerror = function () { reject(new Error('Unable to read photo.')); }; reader.readAsDataURL(file); });
        }
        var response = await send('submit', data);
        document.getElementById('receipt-reference').value = response.receipt.reference;
        document.getElementById('receipt-code').value = response.receipt.trackingCode;
        document.getElementById('report-panel').hidden = true;
        var receipt = document.getElementById('receipt'); receipt.hidden = false; receipt.focus(); receipt.scrollIntoView({block: 'start'});
        // The receipt is not written into URLs, cookies or browser storage.
      } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
      finally { busy = false; button.disabled = false; }
    });
    document.getElementById('save-receipt').addEventListener('click', function () {
      var text = 'MaintainPro\nReference: ' + document.getElementById('receipt-reference').value + '\nTracking Code: ' + document.getElementById('receipt-code').value + '\nKeep these private. Use the Track Concern page.\n';
      var url = URL.createObjectURL(new Blob([text], {type: 'text/plain'})), link = document.createElement('a');
      link.href = url; link.download = 'MaintainPro-tracking.txt'; link.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
  }
  if (track) track.addEventListener('submit', async function (event) {
    event.preventDefault(); if (busy) return; busy = true;
    var errorBox = document.getElementById('public-error'), panel = document.getElementById('tracking-result'), button = track.querySelector('button');
    errorBox.hidden = true; panel.hidden = true; panel.replaceChildren(); button.disabled = true;
    try {
      var result = (await send('track', values(track))).concern;
      panel.append(node('h2', result.reference, 'h4'), node('p', result.category + ' / ' + result.concernType), node('p', 'Status: ' + result.status, 'fw-bold'), node('p', 'Reported: ' + new Date(result.reportedAt).toLocaleString()), node('p', 'Last updated: ' + new Date(result.updatedAt).toLocaleString()));
      result.progress.forEach(function (entry) { panel.append(node('p', new Date(entry.date).toLocaleString() + ' — ' + entry.status)); });
      panel.hidden = false;
    } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
    finally { busy = false; button.disabled = false; }
  });
}());
