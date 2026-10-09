(function () {
  'use strict';
  var form = document.getElementById('report-filters');
  if (!form) return;
  var catalog = JSON.parse(document.getElementById('report-filter-catalog').textContent);
  var category = form.elements.category, period = form.elements.period;
  function options(select, groups, label) {
    var previous = select.value;
    var values = category.value ? (groups[category.value] || []) : Array.from(new Set(Object.values(groups).flat()));
    select.replaceChildren(new Option(label, ''));
    values.forEach(function (value) { select.add(new Option(value, value)); });
    if (values.includes(previous)) select.value = previous;
  }
  category.addEventListener('change', function () {
    options(form.elements.type, catalog.types, 'All types');
    options(form.elements.keypoint, catalog.points, 'All key points');
  });
  ['start', 'end'].forEach(function (name) {
    form.elements[name].addEventListener('input', function () { period.value = 'custom'; });
  });
  period.addEventListener('change', function () {
    if (period.value !== 'custom') { form.elements.start.value = ''; form.elements.end.value = ''; }
  });
})();
