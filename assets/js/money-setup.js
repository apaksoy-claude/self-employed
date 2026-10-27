/* Business setup: the kind-of-work tiles, the live "What Will Be Added" list (built the same way as Trades::plan() in PHP),
   sending Money > Business Setup in the background, and "Not Now" on the dashboard banner. */
(function () {
  'use strict';

  var KINDS = { categories: 'skip_cat', streams: 'skip_stream', paid_by: 'skip_by', paid_with: 'skip_with' };

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  function low(s) { return String(s || '').trim().toLowerCase(); }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  // The same plan as Trades::plan() in PHP: picked trades first (in the order of the packs), then vehicle running costs,
  // then working from home, then everyday costs. The first one with a name wins. Kept apart so the tests can check it.
  function planFrom(data, keys, vehicle, home) {
    keys = Object.keys(data.packs).filter(function (k) { return keys.indexOf(k) !== -1; });
    var sources = keys.map(function (k) { return data.packs[k]; });
    if (vehicle) { sources.push(data.vehicle); }
    if (home) { sources.push(data.home); }
    sources.push(data.base);
    var out = { trades: keys, categories: [], streams: [], paid_by: [], paid_with: [] };
    var seen = { c: {}, s: {}, b: {}, w: {} };
    sources.forEach(function (src) {
      (src.categories || []).forEach(function (c) {
        if (seen.c[low(c.name)]) { return; }
        seen.c[low(c.name)] = 1;
        out.categories.push({ name: c.name, box: c.box, cost: c.cost, group: src.label, icon: src.icon });
      });
      (src.streams || []).forEach(function (s) {
        if (seen.s[low(s.name)]) { return; }
        seen.s[low(s.name)] = 1;
        out.streams.push({ name: s.name, colour: s.colour });
      });
    });
    keys.forEach(function (k) {
      (data.packs[k].paid_by || []).forEach(function (p) { if (!seen.b[low(p)]) { seen.b[low(p)] = 1; out.paid_by.push(p); } });
    });
    [data.base.paid_with || []].concat((keys.length ? keys : ['other']).map(function (k) { return (data.packs[k] || {}).paid_with || []; })).forEach(function (list) {
      list.forEach(function (p) { if (!seen.w[low(p)]) { seen.w[low(p)] = 1; out.paid_with.push(p); } });
    });
    return out;
  }
  window.apSetupPlan = planFrom;

  function boot(root) {
    var data;
    try { data = JSON.parse(root.getAttribute('data-plan') || '{}'); } catch (e) { return; }
    if (!data.packs || !data.base) { return; }
    var form = root.closest('form');
    var preview = root.querySelector('[data-su-preview]');
    var count = root.querySelector('[data-su-count]');
    var vehicle = root.querySelector('input[name="vehicle"][type="checkbox"]');
    var home = root.querySelector('input[name="home"][type="checkbox"]');
    var touched = vehicle ? vehicle.getAttribute('data-touched') === '1' : true;
    var existing = data.existing || {};
    var skipped = { categories: {}, streams: {}, paid_by: {}, paid_with: {} };

    function mode() {
      var r = root.querySelector('input[name="mode"]:checked') || root.querySelector('input[name="mode"]');
      return r ? r.value : 'ready';
    }
    function picked() {
      var on = [];
      each(root.querySelectorAll('input[name="trades[]"]:checked'), function (i) { on.push(i.value); });
      return Object.keys(data.packs).filter(function (k) { return on.indexOf(k) !== -1; });
    }

    function plan() {
      return planFrom(data, picked(), !!(vehicle && vehicle.checked), !!(home && home.checked));
    }

    function item(kind, name, sub, dot) {
      var there = (existing[kind] || []).indexOf(low(name)) !== -1;
      var lab = el('label', 'su-item' + (there ? ' is-there' : ''));
      var box = el('input');
      box.type = 'checkbox';
      box.className = 'form-check-input';
      box.setAttribute('data-kind', kind);
      box.value = name;
      box.checked = there ? true : !skipped[kind][low(name)];
      box.disabled = there;
      box.setAttribute('aria-label', (there ? name + ', already there' : 'Add ' + name));
      if (!box.checked) { lab.classList.add('is-off'); }
      lab.appendChild(box);
      if (dot) { var d = el('span', 'su-dot'); d.style.background = dot; lab.appendChild(d); }
      lab.appendChild(el('span', '', name));
      if (sub) { lab.appendChild(el('small', '', sub)); }
      if (there) { lab.appendChild(el('em', 'su-there', 'Already There')); }
      return { node: lab, adds: !there && box.checked };
    }

    function section(title, groups) {
      var sec = el('div', 'su-sec');
      sec.appendChild(el('h3', '', title));
      groups.forEach(function (g) {
        var wrap = el('div', 'su-group');
        if (g.title) {
          var h = el('h4');
          if (g.icon) { var i = el('i', 'fa-solid ' + g.icon); i.setAttribute('aria-hidden', 'true'); h.appendChild(i); }
          h.appendChild(document.createTextNode(g.title));
          wrap.appendChild(h);
        }
        var items = el('div', 'su-items');
        g.nodes.forEach(function (n) { items.appendChild(n); });
        wrap.appendChild(items);
        sec.appendChild(wrap);
      });
      return sec;
    }

    function render() {
      var m = mode();
      each(root.querySelectorAll('[data-su-ready]'), function (n) { n.hidden = m === 'fresh'; });
      var fresh = root.querySelector('[data-su-fresh]');
      if (fresh) { fresh.hidden = m !== 'fresh'; }
      each(root.querySelectorAll('.su-trade, .su-mode, .su-switch'), function (l) {
        var i = l.querySelector('input:not([type="hidden"])');
        l.classList.toggle('is-on', !!(i && i.checked));
      });
      if (!preview) { return; }
      var p = plan();
      preview.textContent = '';
      if (!p.trades.length) {
        preview.appendChild(el('p', 'su-empty', 'Pick a kind of work above to see what will be added.'));
        if (count) { count.textContent = ''; }
        return;
      }
      var adds = 0;
      var tally = function (r) { if (r.adds) { adds++; } return r.node; };
      var order = [];
      var byGroup = {};
      p.categories.forEach(function (c) {
        if (!byGroup[c.group]) { byGroup[c.group] = { title: c.group, icon: c.icon, nodes: [] }; order.push(c.group); }
        byGroup[c.group].nodes.push(tally(item('categories', c.name, (data.boxes[c.box] || c.box) + (c.cost ? '' : ' · Not Taken Off Profit'))));
      });
      preview.appendChild(section('Expense Categories', order.map(function (g) { return byGroup[g]; })));
      if (p.streams.length) {
        preview.appendChild(section('Income Streams', [{ nodes: p.streams.map(function (s) { return tally(item('streams', s.name, '', s.colour)); }) }]));
      }
      if (p.paid_by.length) {
        preview.appendChild(section('Ways Of Getting Paid', [{ nodes: p.paid_by.map(function (s) { return tally(item('paid_by', s)); }) }]));
      }
      if (p.paid_with.length) {
        preview.appendChild(section('Ways Of Paying', [{ nodes: p.paid_with.map(function (s) { return tally(item('paid_with', s)); }) }]));
      }
      if (count) { count.textContent = adds === 0 ? 'Nothing New' : adds + ' New ' + (adds === 1 ? 'Item' : 'Items'); }
    }

    // What the person unticked goes with the form, so the server leaves exactly those out.
    function addSkips() {
      if (!form) { return; }
      each(form.querySelectorAll('input.su-skip'), function (n) { n.remove(); });
      Object.keys(KINDS).forEach(function (kind) {
        Object.keys(skipped[kind]).forEach(function (name) {
          if (!skipped[kind][name]) { return; }
          var h = el('input', 'su-skip');
          h.type = 'hidden';
          h.name = KINDS[kind] + '[]';
          h.value = name;
          form.appendChild(h);
        });
      });
    }

    root.addEventListener('change', function (e) {
      var t = e.target;
      if (t.matches('input[name="trades[]"]')) {
        if (!touched && vehicle) {
          vehicle.checked = Array.prototype.some.call(root.querySelectorAll('input[name="trades[]"]:checked'), function (i) { return i.getAttribute('data-vehicle') === '1'; });
        }
      } else if (t === vehicle) {
        touched = true;
      } else if (t.matches('input[data-kind]')) {
        skipped[t.getAttribute('data-kind')][low(t.value)] = !t.checked;
      }
      render();
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        addSkips();
        if (!form.hasAttribute('data-su-ajax') || !window.fetch) { return; }
        e.preventDefault();
        var btn = form.querySelector('.su-go');
        var label = btn ? btn.querySelector('span') : null;
        var before = label ? label.textContent : '';
        if (btn) { btn.disabled = true; }
        if (label) { label.textContent = 'Adding…'; }
        var fail = function (msg) {
          if (window.apToast) { window.apToast('danger', msg); } else { window.alert(msg); }
        };
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j || !j.ok) { fail((j && j.message) || 'That did not work. Please try again.'); return; }
            existing = j.existing || existing;
            skipped = { categories: {}, streams: {}, paid_by: {}, paid_with: {} };
            var res = document.querySelector('[data-su-result]');
            if (res) {
              var txt = res.querySelector('[data-su-result-text]');
              if (txt) { txt.textContent = j.message || ''; }
              res.hidden = false;
              res.focus({ preventScroll: true });
              res.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            render();
          })
          .catch(function () { fail('That did not work. Please check your connection and try again.'); })
          .then(function () {
            if (btn) { btn.disabled = false; }
            if (label) { label.textContent = before; }
          });
      });
    }
    render();
  }

  function start() {
    each(document.querySelectorAll('[data-setup]'), boot);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }

  // "Not Now" on the dashboard banner hides it straight away and remembers that in the background.
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-su-dismiss]') : null;
    if (!b) { return; }
    e.preventDefault();
    var banner = b.closest('[data-su-banner]');
    if (banner) { banner.remove(); }
    var fd = new FormData();
    fd.append('_token', b.getAttribute('data-token') || '');
    fetch(b.getAttribute('data-su-dismiss'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
  });
})();
