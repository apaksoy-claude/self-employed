/* Styled drop-down lists for every <select class="form-select"> in the admin.
   The real select stays in the page (hidden), so forms, labels, change events and auto-submit keep working. */
(function () {
  'use strict';
  var open = null;

  function text(opt) { return opt ? opt.textContent.replace(/\s+/g, ' ').trim() : ''; }

  function enhance(sel) {
    if (sel.dataset.apSel || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native') || sel.closest('[data-native-selects]')) { return; }
    sel.dataset.apSel = '1';
    var wrap = document.createElement('div');
    wrap.className = 'ap-sel' + (sel.classList.contains('form-select-sm') ? ' is-sm' : '') + (sel.classList.contains('w-auto') ? ' is-auto' : '');
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.classList.add('ap-sel-native');
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ap-sel-btn';
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    var label = document.createElement('span');
    label.className = 'ap-sel-label';
    var chev = document.createElement('i');
    chev.className = 'fa-solid fa-chevron-down ap-sel-chev';
    chev.setAttribute('aria-hidden', 'true');
    btn.appendChild(label);
    btn.appendChild(chev);
    wrap.appendChild(btn);
    if (sel.getAttribute('aria-label')) { btn.setAttribute('aria-label', sel.getAttribute('aria-label')); }
    if (sel.id) {
      document.querySelectorAll('label[for="' + sel.id.replace(/"/g, '') + '"]').forEach(function (l) { l.addEventListener('click', function (e) { e.preventDefault(); btn.focus(); }); });
    }

    var panel = null, items = [], active = -1, typed = '', typedAt = 0;

    function sync() {
      var o = sel.options[sel.selectedIndex];
      label.textContent = text(o) || ' ';
      btn.classList.toggle('is-placeholder', !!o && o.value === '' && !!sel.querySelector('option[value=""]'));
      btn.disabled = sel.disabled;
      btn.classList.toggle('is-invalid', sel.classList.contains('is-invalid'));
    }

    function close(focus) {
      if (panel) { panel.remove(); panel = null; }
      btn.setAttribute('aria-expanded', 'false');
      wrap.classList.remove('is-open');
      if (open === close) { open = null; }
      if (focus) { btn.focus(); }
    }

    function place() {
      if (!panel) { return; }
      var r = btn.getBoundingClientRect();
      panel.style.minWidth = Math.max(r.width, 160) + 'px';
      var h = panel.offsetHeight, below = window.innerHeight - r.bottom - 10, above = r.top - 10;
      var top = (h > below && above > below) ? Math.max(8, r.top - h - 6) : r.bottom + 6;
      panel.style.maxHeight = Math.max(160, (h > below && above > below ? above : below)) + 'px';
      panel.style.top = top + 'px';
      panel.style.left = Math.max(8, Math.min(r.left, document.documentElement.clientWidth - panel.offsetWidth - 8)) + 'px';
    }

    function choose(i) {
      var o = items[i] && items[i].opt;
      if (!o || o.disabled) { return; }
      var changed = sel.selectedIndex !== o.index;
      sel.selectedIndex = o.index;
      sync();
      close(true);
      if (changed) {
        sel.dispatchEvent(new Event('input', { bubbles: true }));
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    function mark(i, scroll) {
      items.forEach(function (it, n) { it.el.classList.toggle('is-active', n === i); });
      active = i;
      if (scroll && items[i]) { items[i].el.scrollIntoView({ block: 'nearest' }); }
    }

    function step(dir) {
      var n = active;
      for (var k = 0; k < items.length; k++) {
        n = (n + dir + items.length) % items.length;
        if (items[n].el.style.display !== 'none' && !items[n].opt.disabled) { mark(n, true); return; }
      }
    }

    function openList() {
      if (sel.disabled) { return; }
      if (open) { open(); }
      panel = document.createElement('div');
      panel.className = 'ap-sel-panel';
      panel.setAttribute('role', 'listbox');
      var search = null;
      var count = sel.options.length;
      if (count > 9) {
        search = document.createElement('input');
        search.type = 'search';
        search.className = 'ap-sel-search';
        search.placeholder = 'Search';
        search.setAttribute('aria-label', 'Search the list');
        panel.appendChild(search);
      }
      var list = document.createElement('div');
      list.className = 'ap-sel-list';
      panel.appendChild(list);
      items = [];
      var group = null;
      Array.prototype.forEach.call(sel.children, function (child) {
        var opts = child.tagName === 'OPTGROUP' ? Array.prototype.slice.call(child.children) : [child];
        if (child.tagName === 'OPTGROUP') {
          var g = document.createElement('div');
          g.className = 'ap-sel-group';
          g.textContent = child.label;
          list.appendChild(g);
        }
        opts.forEach(function (o) {
          var el = document.createElement('div');
          el.className = 'ap-sel-opt' + (o.disabled ? ' is-disabled' : '') + (o.selected ? ' is-selected' : '');
          el.setAttribute('role', 'option');
          el.setAttribute('aria-selected', o.selected ? 'true' : 'false');
          var t = document.createElement('span');
          t.textContent = text(o) || ' ';
          el.appendChild(t);
          if (o.selected) { var c = document.createElement('i'); c.className = 'fa-solid fa-check'; c.setAttribute('aria-hidden', 'true'); el.appendChild(c); }
          var idx = items.length;
          el.addEventListener('mousedown', function (e) { e.preventDefault(); });
          el.addEventListener('click', function () { choose(idx); });
          el.addEventListener('mousemove', function () { if (active !== idx && !o.disabled) { mark(idx, false); } });
          list.appendChild(el);
          items.push({ el: el, opt: o, text: text(o).toLowerCase() });
        });
      });
      document.body.appendChild(panel);
      btn.setAttribute('aria-expanded', 'true');
      wrap.classList.add('is-open');
      open = function () { close(false); };
      mark(Math.max(0, sel.selectedIndex), false);
      place();
      if (items[active]) { items[active].el.scrollIntoView({ block: 'center' }); }
      if (search) {
        search.focus();
        search.addEventListener('input', function () {
          var q = search.value.trim().toLowerCase(), first = -1;
          items.forEach(function (it, n) { var hit = it.text.indexOf(q) !== -1; it.el.style.display = hit ? '' : 'none'; if (hit && first < 0 && !it.opt.disabled) { first = n; } });
          if (first >= 0) { mark(first, true); }
        });
        search.addEventListener('keydown', function (e) { listKeys(e); });
      }
    }

    function listKeys(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); step(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
      else if (e.key === 'Enter') { e.preventDefault(); choose(active); }
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); }
      else if (e.key === 'Tab') { close(false); }
    }

    btn.addEventListener('click', function () { panel ? close(false) : openList(); });
    btn.addEventListener('keydown', function (e) {
      if (panel) { listKeys(e); return; }
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) !== -1) { e.preventDefault(); openList(); return; }
      if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
        var now = Date.now();
        typed = (now - typedAt > 700 ? '' : typed) + e.key.toLowerCase();
        typedAt = now;
        for (var i = 0; i < sel.options.length; i++) {
          if (!sel.options[i].disabled && text(sel.options[i]).toLowerCase().indexOf(typed) === 0) {
            sel.selectedIndex = i; sync();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            break;
          }
        }
      }
    });
    sel.addEventListener('change', sync);
    if (sel.form) { sel.form.addEventListener('reset', function () { setTimeout(sync, 0); }); }
    new MutationObserver(sync).observe(sel, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'class', 'selected'] });
    sync();
    wrap._close = close;
    document.addEventListener('mousedown', function (e) { if (panel && !panel.contains(e.target) && !wrap.contains(e.target)) { close(false); } });
    window.addEventListener('resize', place);
    document.addEventListener('scroll', function (e) { if (panel && !panel.contains(e.target)) { place(); } }, true);
  }

  function scan(root) {
    (root.querySelectorAll ? root.querySelectorAll('select.form-select') : []).forEach(enhance);
  }
  function start() {
    scan(document);
    new MutationObserver(function (list) {
      list.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (n.nodeType !== 1) { return; }
          if (n.matches && n.matches('select.form-select')) { enhance(n); }
          scan(n);
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
