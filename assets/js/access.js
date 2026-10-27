/* Roles and access: switches that share a permission move together, "All" per group and role, find an area. */
(function () {
  'use strict';
  var form = document.querySelector('[data-access]');
  if (!form) { return; }
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  function count(role) {
    var seen = {}, n = 0;
    $$('input[type=checkbox][data-role="' + role + '"]', form).forEach(function (cb) { if (cb.checked && !seen[cb.dataset.perm]) { seen[cb.dataset.perm] = 1; n++; } });
    var c = document.querySelector('[data-role-count="' + role + '"]');
    if (c) { var base = parseInt(c.getAttribute('data-base') || '0', 10); c.textContent = n + base; }
  }
  // Areas without a switch (everyone) also count, so remember them once.
  $$('[data-role-count]').forEach(function (c) {
    var role = c.getAttribute('data-role-count'), seen = {}, n = 0;
    $$('input[type=checkbox][data-role="' + role + '"]', form).forEach(function (cb) { if (cb.checked && !seen[cb.dataset.perm]) { seen[cb.dataset.perm] = 1; n++; } });
    c.setAttribute('data-base', String(parseInt(c.textContent, 10) - n));
  });
  form.addEventListener('change', function (ev) {
    var cb = ev.target;
    if (!cb.matches('input[type=checkbox][data-perm]')) { return; }
    $$('input[type=checkbox][data-role="' + cb.dataset.role + '"][data-perm="' + cb.dataset.perm + '"]', form).forEach(function (o) { o.checked = cb.checked; });
    count(cb.dataset.role);
  });
  $$('[data-all]', form).forEach(function (b) {
    b.addEventListener('click', function () {
      var boxes = $$('[data-access-group="' + b.dataset.all + '"] input[type=checkbox][data-role="' + b.dataset.role + '"]:not(:disabled)', form);
      var on = boxes.some(function (x) { return !x.checked; });
      boxes.forEach(function (x) { x.checked = on; x.dispatchEvent(new Event('change', { bubbles: true })); });
      b.textContent = on ? 'None' : 'All';
    });
  });
  var find = form.querySelector('[data-access-find]');
  if (find) {
    find.addEventListener('input', function () {
      var t = find.value.toLowerCase().trim(), any = false;
      $$('[data-access-group]', form).forEach(function (g) {
        var shown = 0;
        $$('[data-access-row]', g).forEach(function (r) { var ok = !t || r.getAttribute('data-text').indexOf(t) !== -1; r.hidden = !ok; if (ok) { shown++; } });
        g.hidden = !shown; if (shown) { any = true; }
      });
      form.querySelector('[data-access-none]').hidden = any;
    });
  }
})();
