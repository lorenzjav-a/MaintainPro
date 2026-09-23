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
  function showGuidance(container, steps, heading) {
    container.replaceChildren();
    if (!Array.isArray(steps) || steps.length !== 3) return;
    if (heading) {
      container.append(node('h3', heading, 'section-title'), node('p', 'Temporary steps based on your report at submission. Follow current instructions from the barangay or emergency responders.', 'guidance-intro'));
    }
    var list = node('ol', '', 'resident-guidance-list');
    steps.forEach(function (text) { list.append(node('li', text)); });
    container.append(list);
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
    try { var result = await send('guidance', values(form)); result.residentGuidance.forEach(function (text, index) { form.querySelector('[name=action' + (index + 1) + ']').value = text; }); }
    catch (error) { Swal.fire({icon: 'error', text: error.message}); }
  });
  var generation = 0, busy = false, receiptGuidance = [];
  if (report) {
    report.querySelector('[data-concern-choices]').addEventListener('change', async function () {
      var version = ++generation, container = document.getElementById('suggestions'), data = values(report);
      container.replaceChildren(node('p', data.concernType ? 'Loading your temporary guidance…' : 'Select a category and concern type.'));
      container.setAttribute('aria-busy', data.concernType ? 'true' : 'false');
      if (!data.concernType) return;
      try {
        var result = await send('guidance', data);
        if (version !== generation) return;
        showGuidance(container, result.residentGuidance);
      } catch (error) { if (version === generation) container.replaceChildren(node('p', 'Temporary guidance could not be loaded. You can still submit your concern; guidance will appear with your tracking details.')); }
      finally { if (version === generation) container.setAttribute('aria-busy', 'false'); }
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
        receiptGuidance = response.receipt.residentGuidance || [];
        showGuidance(document.getElementById('receipt-guidance'), receiptGuidance, 'While you wait');
        document.getElementById('report-panel').hidden = true;
        var receipt = document.getElementById('receipt'); receipt.hidden = false; receipt.focus(); receipt.scrollIntoView({block: 'start'});
        // The receipt is not written into URLs, cookies or browser storage.
      } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
      finally { busy = false; button.disabled = false; }
    });
    document.getElementById('save-receipt').addEventListener('click', function () {
      var text = 'MaintainPro\nReference: ' + document.getElementById('receipt-reference').value + '\nTracking Code: ' + document.getElementById('receipt-code').value + '\nKeep these private. Use the Track Concern page.\n';
      text += '\nWhile you wait — temporary steps for residents\n' + receiptGuidance.map(function (step, i) { return (i + 1) + '. ' + step; }).join('\n') + '\nGuidance is based on your report at submission. Follow current instructions from the barangay or emergency responders.\n';
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
      panel.append(node('h2', result.reference, 'section-title'), node('p', result.category + ' / ' + result.concernType), node('p', 'Status: ' + result.status, 'fw-bold'), node('p', 'Reported: ' + new Date(result.reportedAt).toLocaleString()), node('p', 'Last updated: ' + new Date(result.updatedAt).toLocaleString()));
      result.progress.forEach(function (entry) { panel.append(node('p', new Date(entry.date).toLocaleString() + ' — ' + entry.status)); });
      if (result.residentGuidance && result.residentGuidance.length === 3) {
        var guidance = node('section', '', 'resident-guidance mt-4');
        showGuidance(guidance, result.residentGuidance, 'Temporary guidance from your report');
        panel.append(guidance);
      }
      panel.hidden = false;
    } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
    finally { busy = false; button.disabled = false; }
  });
}());
