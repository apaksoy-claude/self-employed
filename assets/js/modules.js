/* System > Modules: find and filter cards, switch modules on and off without leaving the page. */
(function () {
  'use strict';
  var root = document.querySelector('[data-modules]');
  if (!root) { return; }
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var search = root.querySelector('[data-mod-search]');
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  function filter() { var f = root.querySelector('[data-mod-filter]:checked'); return f ? f.value : 'all'; }
  function apply() {
    var term = (search.value || '').toLowerCase().trim(), f = filter(), any = false;
    $$('[data-mod-cat]', root).forEach(function (cat) {
      var shown = 0;
      $$('.ap-mod-card', cat).forEach(function (c) {
        var ok = (f === 'all' || c.getAttribute('data-mod').split(' ').indexOf(f) !== -1) && (!term || c.getAttribute('data-text').indexOf(term) !== -1);
        c.hidden = !ok; if (ok) { shown++; }
      });
      cat.hidden = !shown; if (shown) { any = true; }
    });
    root.querySelector('[data-mod-none]').hidden = any;
  }
  function counts() {
    var cards = $$('.ap-mod-card', root), on = cards.filter(function (c) { return c.classList.contains('is-on'); }).length;
    var set = function (k, v) { var n = root.querySelector('[data-count="' + k + '"]'); if (n) { n.textContent = v; } };
    set('all', cards.length); set('on', on); set('off', cards.length - on);
    $$('[data-mod-cat]', root).forEach(function (cat) {
      var cs = $$('.ap-mod-card', cat), o = cs.filter(function (c) { return c.classList.contains('is-on'); }).length;
      cat.querySelector('[data-cat-count]').textContent = o + ' of ' + cs.length + ' on';
    });
  }
  search.addEventListener('input', apply);
  $$('[data-mod-filter]', root).forEach(function (r) { r.addEventListener('change', apply); });
  function paint(card, on) {
    card.classList.toggle('is-on', on);
    card.querySelector('[data-mod-switch]').checked = on;
    card.querySelector('[data-mod-state]').textContent = on ? 'Switched on' : 'Switched off';
    var flags = card.getAttribute('data-mod').split(' ').filter(function (x) { return x !== 'on' && x !== 'off'; });
    flags.unshift(on ? 'on' : 'off');
    card.setAttribute('data-mod', flags.join(' '));
    counts();
  }
  function send(card, want) {
    var sw = card.querySelector('[data-mod-switch]');
    sw.disabled = true;
    var fd = new FormData(); fd.append('_token', token); fd.append('enable', want ? '1' : '0');
    fetch(root.getAttribute('data-toggle-url') + '/' + card.getAttribute('data-name') + '/toggle', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { paint(card, !!d.enabled); if (window.apToast) { window.apToast(d.ok ? 'success' : 'danger', d.message); } })
      .catch(function () { paint(card, !want); if (window.apToast) { window.apToast('danger', 'Could not change the module. Check your connection.'); } })
      .then(function () { sw.disabled = false; });
  }
  $$('[data-mod-switch]', root).forEach(function (sw) {
    sw.addEventListener('change', function () {
      var card = sw.closest('.ap-mod-card'), want = sw.checked;
      if (!want && window.apAsk) {
        sw.checked = true;
        var off = card.querySelector('.ap-mod-off');
        window.apAsk((off ? off.textContent.replace(/^When off:\s*/, '') + ' ' : '') + 'Switch it off?', function () { send(card, false); }, 'Switch off ' + card.querySelector('h3').textContent);
        return;
      }
      send(card, want);
    });
  });
  if (location.hash) { var t = document.querySelector(location.hash); if (t && t.classList.contains('ap-mod-card')) { t.classList.add('is-target'); } }
})();
