/* AP Framework admin 1.2: plain JavaScript, no dependencies besides Bootstrap's bundle. */
(function () {
  'use strict';
  var body = document.body;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ignore */ } },
    list: function (k) { try { var v = JSON.parse(localStorage.getItem(k) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
  };
  var token = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

  /* ---- one drag-and-drop engine for every list, card and row: pointer based, so it is smooth, scrolls the page by itself near the edges, lets the mouse wheel work, and works with touch ---- */
  var apDrag = function (o) {
    var st = null;
    function scrollerOf(el) {
      while (el && el !== document.body && el.nodeType === 1) {
        var ov = getComputedStyle(el).overflowY;
        if ((ov === 'auto' || ov === 'scroll') && el.scrollHeight > el.clientHeight + 2) { return el; }
        el = el.parentNode;
      }
      return null;
    }
    function sibs() { return Array.prototype.filter.call(st.parent.children, function (c) { return c !== st.item && c.matches(o.pick || o.item); }); }
    function target(x, y) {
      var el = document.elementFromPoint(x, y);
      while (el && el.parentNode !== st.parent) { el = el.parentNode; }
      return el && el.nodeType === 1 && el !== st.item && el.matches(o.pick || o.item) ? el : null;
    }
    function place(x, y) {
      var slot = st.item, over = target(x, y), list = sibs(), ref;
      if (over) {
        var r = over.getBoundingClientRect(), s = slot.getBoundingClientRect();
        var sameRow = Math.min(r.bottom, s.bottom) - Math.max(r.top, s.top) > Math.min(r.height, s.height) * 0.5;
        var forward = !!(slot.compareDocumentPosition(over) & Node.DOCUMENT_POSITION_FOLLOWING);
        var go = sameRow ? (forward ? x > r.left + r.width / 2 : x < r.left + r.width / 2) : (forward ? y > r.top + r.height / 2 : y < r.top + r.height / 2);
        if (!go) { return; }
        ref = forward ? over.nextSibling : over;
      } else if (list.length) {
        var first = list[0].getBoundingClientRect(), last = list[list.length - 1].getBoundingClientRect();
        if (y > last.bottom) { ref = list[list.length - 1].nextSibling; } else if (y < first.top) { ref = list[0]; } else { return; }
      } else { return; }
      if (ref === slot || slot.nextSibling === ref) { return; }
      var from = list.map(function (el) { return el.getBoundingClientRect(); });
      st.parent.insertBefore(slot, ref);
      var moves = [];
      list.forEach(function (el, i) {
        var a = el.getBoundingClientRect(), dx = from[i].left - a.left, dy = from[i].top - a.top;
        if (dx || dy) { el.style.transition = 'none'; el.style.transform = 'translate(' + dx + 'px,' + dy + 'px)'; moves.push(el); }
      });
      if (moves.length) {
        void st.parent.offsetWidth;
        moves.forEach(function (el) { el.style.transition = 'transform .16s ease'; el.style.transform = ''; });
        setTimeout(function () { moves.forEach(function (el) { el.style.transition = ''; }); }, 200);
      }
    }
    function show() { st.ghost.style.left = (st.x - st.ox) + 'px'; st.ghost.style.top = (st.y - st.oy) + 'px'; }
    function loop() {
      st.raf = 0;
      if (!st || !st.live) { return; }
      var top = 0, bottom = window.innerHeight;
      if (st.sc) { var r = st.sc.getBoundingClientRect(); top = Math.max(0, r.top); bottom = Math.min(bottom, r.bottom); }
      var edge = Math.min(150, (bottom - top) / 3), d = 0;
      if (st.y < top + edge) { d = -(top + edge - st.y) / edge; } else if (st.y > bottom - edge) { d = (st.y - (bottom - edge)) / edge; }
      d = Math.max(-1.3, Math.min(1.3, d));
      var v = Math.round(Math.sign(d) * Math.pow(Math.abs(d), 1.5) * 46);
      if (!v) { return; }
      (st.sc || window).scrollBy(0, v);
      place(st.x, st.y);
      st.raf = requestAnimationFrame(loop);
    }
    function begin() {
      var it = st.item, r = it.getBoundingClientRect(), g;
      st.sc = scrollerOf(st.parent);
      if (it.tagName === 'TR') {
        g = document.createElement('table');
        g.className = (it.closest('table') || {}).className || '';
        var tb = document.createElement('tbody'), tr = it.cloneNode(true);
        Array.prototype.forEach.call(it.children, function (c, i) { if (tr.children[i]) { tr.children[i].style.width = c.getBoundingClientRect().width + 'px'; } });
        tb.appendChild(tr); g.appendChild(tb);
      } else { g = it.cloneNode(true); }
      [g].concat($$('[id]', g)).forEach(function (n) { n.removeAttribute('id'); });
      $$('[name]', g).forEach(function (n) { n.removeAttribute('name'); });
      g.classList.add('ap-drag-ghost');
      g.style.width = r.width + 'px'; g.style.height = it.tagName === 'TR' ? '' : r.height + 'px';
      st.ox = st.x0 - r.left; st.oy = st.y0 - r.top;
      st.ghost = g; document.body.appendChild(g);
      it.classList.add('ap-drop-slot');
      document.body.classList.add('ap-is-dragging');
      st.live = true; show();
    }
    function move(ev) {
      if (!st || (st.id != null && ev.pointerId !== st.id)) { return; }
      st.x = ev.clientX; st.y = ev.clientY;
      if (!st.live) {
        if (Math.hypot(st.x - st.x0, st.y - st.y0) < 4) { return; }
        begin();
      }
      ev.preventDefault();
      show(); place(st.x, st.y);
      if (!st.raf) { st.raf = requestAnimationFrame(loop); }
    }
    function end(keep) {
      var s = st; st = null;
      document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up);
      document.removeEventListener('pointercancel', cancel); document.removeEventListener('keydown', key);
      if (!s.live) { return; }
      if (s.raf) { cancelAnimationFrame(s.raf); }
      document.body.classList.remove('ap-is-dragging');
      var swallow = function (e) { e.preventDefault(); e.stopPropagation(); };
      document.addEventListener('click', swallow, true);
      setTimeout(function () { document.removeEventListener('click', swallow, true); }, 60);
      if (!keep) { s.parent.insertBefore(s.item, s.next); }
      var r = s.item.getBoundingClientRect();
      s.ghost.style.transition = 'left .15s ease, top .15s ease, opacity .15s ease';
      s.ghost.style.left = r.left + 'px'; s.ghost.style.top = r.top + 'px'; s.ghost.style.opacity = '.6';
      setTimeout(function () { s.ghost.remove(); s.item.classList.remove('ap-drop-slot'); }, 160);
      if (keep && s.item.nextSibling !== s.next && o.done) { o.done(s.item); }
    }
    function up() { if (st) { end(true); } }
    function cancel() { if (st) { end(false); } }
    function key(ev) { if (ev.key === 'Escape' && st) { end(false); } }
    o.root.addEventListener('pointerdown', function (ev) {
      if (st || (ev.button != null && ev.button > 0) || (o.enabled && !o.enabled())) { return; }
      var h = ev.target.closest(o.handle);
      if (!h || !o.root.contains(h)) { return; }
      var item = o.itemOf ? o.itemOf(h) : h.closest(o.item);
      if (!item || !item.parentNode) { return; }
      ev.preventDefault();
      st = { item: item, parent: item.parentNode, next: item.nextSibling, x0: ev.clientX, y0: ev.clientY, x: ev.clientX, y: ev.clientY, live: false, id: ev.pointerId, raf: 0 };
      document.addEventListener('pointermove', move, { passive: false }); document.addEventListener('pointerup', up);
      document.addEventListener('pointercancel', cancel); document.addEventListener('keydown', key);
    });
  };

  /* ---- theme ---- */
  var root = document.documentElement;
  function applyTheme(pref) {
    var t = pref === 'auto' ? (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : pref;
    root.setAttribute('data-bs-theme', t);
    root.setAttribute('data-theme-pref', pref);
    var icon = $('#themeToggle i');
    if (icon) { icon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
  }
  applyTheme(root.getAttribute('data-theme-pref') || 'auto');
  var themeBtn = $('#themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      var fd = new FormData();
      fd.append('theme', next);
      fd.append('_token', token());
      fetch(body.getAttribute('data-theme-url'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    });
  }

  /* ---- sidebar: open/close, find in menu, sections, pins ---- */
  function toggleSidebar() {
    if (window.innerWidth < 992) { body.classList.toggle('sidebar-open'); }
    else { body.classList.toggle('sidebar-collapsed'); store.set('ap.sidebar', body.classList.contains('sidebar-collapsed') ? '1' : '0'); }
  }
  if (store.get('ap.sidebar') === '1' && window.innerWidth >= 992) { body.classList.add('sidebar-collapsed'); }
  var sb = $('#sidebarToggle');
  if (sb) { sb.addEventListener('click', toggleSidebar); }
  var backdrop = $('[data-sidebar-backdrop]');
  if (backdrop) { backdrop.addEventListener('click', function () { body.classList.remove('sidebar-open'); }); }

  var closedNav = store.list('ap.nav.closed');
  function setSection(sec, open) {
    sec.classList.toggle('is-closed', !open);
    var b = $('[data-section-toggle]', sec);
    if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  }
  function saveSections() {
    store.set('ap.nav.closed', JSON.stringify($$('.ap-nav-section.is-closed[data-section]').map(function (s) { return s.getAttribute('data-section'); })));
  }
  $$('.ap-nav-section[data-section]').forEach(function (sec) {
    var hasActive = !!$('.ap-nav-link.active', sec);
    if (closedNav.indexOf(sec.getAttribute('data-section')) !== -1 && !hasActive) { setSection(sec, false); }
    var b = $('[data-section-toggle]', sec);
    if (b) { b.addEventListener('click', function () { setSection(sec, sec.classList.contains('is-closed')); saveSections(); }); }
  });
  $$('[data-sections]').forEach(function (b) {
    b.addEventListener('click', function () {
      var open = b.getAttribute('data-sections') === 'open';
      $$('.ap-nav-section[data-section]').forEach(function (s) { setSection(s, open); });
      saveSections();
    });
  });
  var menuFilter = $('[data-menu-filter]');
  if (menuFilter) {
    menuFilter.addEventListener('input', function () {
      var q = menuFilter.value.trim().toLowerCase();
      var any = false;
      $$('.ap-nav-section').forEach(function (sec) {
        var shown = 0;
        $$('.ap-nav-item', sec).forEach(function (li) {
          var ok = q === '' || (li.getAttribute('data-label') || '').indexOf(q) !== -1;
          li.hidden = !ok;
          if (ok) { shown++; }
        });
        if (sec.hasAttribute('data-pinned-section')) { sec.hidden = q !== '' || !$$('.ap-nav-item', sec).length; return; }
        sec.hidden = shown === 0;
        if (q !== '' && shown) { sec.classList.remove('is-closed'); }
        if (q === '') { sec.classList.toggle('is-closed', store.list('ap.nav.closed').indexOf(sec.getAttribute('data-section')) !== -1 && !$('.ap-nav-link.active', sec)); }
        if (shown) { any = true; }
      });
      var nm = $('[data-menu-nomatch]'); if (nm) { nm.hidden = any; }
    });
    menuFilter.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { var first = $$('.ap-nav-item:not([hidden]) .ap-nav-link').filter(function (a) { return a.offsetParent !== null; })[0]; if (first) { ev.preventDefault(); window.location = first.href; } }
      if (ev.key === 'Escape') { menuFilter.value = ''; menuFilter.dispatchEvent(new Event('input')); }
    });
  }
  function rebuildPinned(pins) {
    var list = $('[data-pinned-list]');
    var sec = $('[data-pinned-section]');
    if (!list || !sec) { return; }
    list.innerHTML = '';
    pins.forEach(function (url) {
      var sel = '[data-url="' + (window.CSS && CSS.escape ? CSS.escape(url) : url) + '"]';
      var src = $('.ap-nav-section[data-section] .ap-nav-item' + sel) || $('[data-hub-nav] [data-pin-src]' + sel);
      if (!src) { return; }
      var li = document.createElement('li');
      li.className = 'ap-nav-item';
      li.setAttribute('data-url', url);
      li.setAttribute('data-sort-id', url);
      var grip = document.createElement('span');
      grip.className = 'ap-pin-grip'; grip.setAttribute('data-sort-handle', ''); grip.tabIndex = 0; grip.setAttribute('role', 'button'); grip.title = 'Drag to change the order';
      grip.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
      li.appendChild(grip);
      li.setAttribute('data-label', src.getAttribute('data-label') || '');
      var link = $('a', src).cloneNode(true);
      link.className = 'ap-nav-link'; link.removeAttribute('aria-current');
      $$('.ap-snav-ext', link).forEach(function (x) { x.remove(); });
      li.appendChild(link);
      list.appendChild(li);
    });
    sec.hidden = !list.children.length;
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-pin]');
    if (!b) { return; }
    ev.preventDefault();
    var fd = new FormData();
    fd.append('url', b.getAttribute('data-pin'));
    fd.append('_token', token());
    fetch(body.getAttribute('data-pin-url'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { window.apToast('warning', d.message || 'That did not work.'); return; }
        var li = b.closest('.ap-nav-item, [data-pin-src]');
        var label = ($('a span', li) || {}).textContent || '';
        li.classList.toggle('is-pinned', d.pinned);
        b.setAttribute('aria-pressed', d.pinned ? 'true' : 'false');
        b.setAttribute('aria-label', (d.pinned ? 'Unpin ' : 'Pin ') + label);
        b.title = d.pinned ? 'Unpin' : 'Pin to the top';
        rebuildPinned(d.pins || []);
      })
      .catch(function () { window.apToast('danger', 'Could not save. Check your connection and try again.'); });
  });

  /* ---- tooltips, popovers and toasts ---- */
  if (window.bootstrap) {
    $$('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
    $$('[data-bs-toggle="popover"]').forEach(function (el) { new bootstrap.Popover(el); });
    $$('.toast[data-autoshow]').forEach(function (el) { new bootstrap.Toast(el, { delay: 5000 }).show(); });
  }
  window.apToast = function (type, message) {
    var box = $('#toasts');
    if (!box || !window.bootstrap) { return; }
    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + type + ' border-0';
    el.setAttribute('role', 'status');
    var inner = document.createElement('div'); inner.className = 'd-flex';
    var b = document.createElement('div'); b.className = 'toast-body'; b.textContent = message;
    inner.appendChild(b); el.appendChild(inner); box.appendChild(el);
    new bootstrap.Toast(el, { delay: 5000 }).show();
  };

  /* ---- search popup: click the top-bar search, press Ctrl+K or "/" ---- */
  var cmd = $('[data-cmd]');
  var cmdInput = cmd ? $('[data-cmd-input]', cmd) : null;
  var cmdList = cmd ? $('[data-cmd-list]', cmd) : null;
  var cmdAll = cmd ? $('[data-cmd-all]', cmd) : null;
  var gs = $('[data-global-search]');
  var cmdData = {};
  try { cmdData = JSON.parse(($('#apCmdData') || {}).textContent || '{}'); } catch (e) { cmdData = {}; }
  var cmdTimer = null, cmdReq = 0, cmdActive = -1, cmdLastFocus = null, cmdCache = {};
  function cmdMark(text, q) {
    var safe = esc(text);
    (q || '').split(/\s+/).filter(function (w) { return w.length > 1; }).forEach(function (w) {
      var re = new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
      safe = safe.replace(re, '<mark class="ap-hl">$1</mark>');
    });
    return safe;
  }
  function cmdItems() { return $$('.ap-cmd-item', cmdList); }
  function cmdSelect(i) {
    var items = cmdItems();
    if (!items.length) { cmdActive = -1; cmdInput.removeAttribute('aria-activedescendant'); return; }
    cmdActive = (i + items.length) % items.length;
    items.forEach(function (a, n) { a.classList.toggle('is-active', n === cmdActive); a.setAttribute('aria-selected', n === cmdActive ? 'true' : 'false'); });
    items[cmdActive].scrollIntoView({ block: 'nearest' });
    cmdInput.setAttribute('aria-activedescendant', items[cmdActive].id);
  }
  function cmdRow(r, i, q) {
    return '<a class="ap-cmd-item" role="option" id="apcmd' + i + '" href="' + esc(r.url) + '" style="--i:' + i + '">'
      + '<span class="ap-cmd-ico"><i class="fa-solid ' + esc(r.icon || 'fa-file-lines') + '" aria-hidden="true"></i></span>'
      + '<span class="ap-cmd-text"><span class="ap-cmd-title">' + cmdMark(r.title, q) + '</span>' + (r.subtitle ? '<span class="ap-cmd-sub">' + cmdMark(r.subtitle, q) + '</span>' : '') + '</span>'
      + '<i class="fa-solid fa-arrow-turn-down ap-cmd-enter" aria-hidden="true"></i></a>';
  }
  function cmdRender(sections, q) {
    var html = '', n = 0;
    sections.forEach(function (sec) {
      if (!sec.rows.length) { return; }
      html += '<div class="ap-cmd-group" role="presentation">' + esc(sec.label) + '</div>';
      sec.rows.forEach(function (r) { html += cmdRow(r, n++, q); });
    });
    if (!n) {
      html = q ? '<div class="ap-cmd-empty"><i class="fa-regular fa-face-meh-blank"></i><strong>Nothing found for "' + esc(q) + '"</strong><span>Try a shorter or different word.</span></div>'
        : '<div class="ap-cmd-empty"><i class="fa-solid fa-magnifying-glass"></i><strong>Search the whole admin</strong><span>Pages, settings, staff, content and logs.</span></div>';
    }
    cmdList.innerHTML = html;
    cmdList.classList.remove('is-anim'); void cmdList.offsetWidth; cmdList.classList.add('is-anim');
    if (cmdAll) { cmdAll.hidden = !q; cmdAll.href = cmd.getAttribute('data-search-url') + '?q=' + encodeURIComponent(q); }
    cmdSelect(0);
  }
  function cmdHome() {
    var L = cmdData.labels || {};
    var recent = cmdData.recent || [], most = cmdData.most || [];
    var sections = [{ label: L.recent || 'Recently opened', rows: recent }, { label: L.most || 'Most used', rows: most }];
    if (!recent.length && !most.length) { sections = [{ label: L.start || 'Good places to start', rows: cmdData.start || [] }]; }
    cmdRender(sections, '');
  }
  function cmdSearch(q) {
    if (q.length < 2) { cmdHome(); return; }
    if (cmdCache[q]) { cmdShow(q, cmdCache[q]); return; }
    cmd.classList.add('is-loading');
    var id = ++cmdReq;
    fetch(cmd.getAttribute('data-search-url') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { cmdCache[q] = d.results || []; if (id === cmdReq && cmdInput.value.trim() === q) { cmdShow(q, cmdCache[q]); } })
      .catch(function () { /* Enter still opens the full results page */ })
      .then(function () { if (id === cmdReq) { cmd.classList.remove('is-loading'); } });
  }
  function cmdShow(q, rows) {
    var sections = [], by = {};
    rows.forEach(function (r) {
      if (!by[r.group]) { by[r.group] = { label: r.group, rows: [] }; sections.push(by[r.group]); }
      by[r.group].rows.push(r);
    });
    cmdRender(sections, q);
  }
  function cmdOpen(text) {
    if (!cmd || !cmd.hidden) { return; }
    cmdLastFocus = document.activeElement;
    cmd.hidden = false;
    document.documentElement.classList.add('ap-cmd-open');
    requestAnimationFrame(function () { cmd.classList.add('is-open'); });
    cmdInput.value = text || '';
    cmdSearch(cmdInput.value.trim());
    setTimeout(function () { cmdInput.focus(); cmdInput.setSelectionRange(cmdInput.value.length, cmdInput.value.length); }, 30);
  }
  function cmdClose() {
    if (!cmd || cmd.hidden) { return; }
    cmd.classList.remove('is-open');
    cmd.classList.add('is-closing');
    setTimeout(function () { cmd.hidden = true; cmd.classList.remove('is-closing'); document.documentElement.classList.remove('ap-cmd-open'); }, 180);
    if (cmdLastFocus && cmdLastFocus !== gs && cmdLastFocus.focus) { cmdLastFocus.focus(); }
  }
  window.apSearch = { open: cmdOpen, close: cmdClose };
  if (cmd) {
    if (gs) {
      var openFromBar = function (ev) { ev.preventDefault(); gs.blur(); cmdOpen(gs.value); };
      gs.addEventListener('mousedown', openFromBar);
      gs.addEventListener('focus', openFromBar);
      gs.addEventListener('keydown', function (ev) { if (ev.key.length === 1) { ev.preventDefault(); gs.blur(); cmdOpen(ev.key); } });
    }
    cmdInput.addEventListener('input', function () {
      clearTimeout(cmdTimer);
      var q = cmdInput.value.trim();
      cmdTimer = setTimeout(function () { cmdSearch(q); }, q.length < 2 ? 0 : 140);
    });
    cmdInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); cmdSelect(cmdActive + 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); cmdSelect(cmdActive - 1); }
      else if (ev.key === 'Home' && ev.ctrlKey) { ev.preventDefault(); cmdSelect(0); }
      else if (ev.key === 'Enter') {
        ev.preventDefault();
        var items = cmdItems();
        var q = cmdInput.value.trim();
        if (cmdActive >= 0 && items[cmdActive]) { window.location = items[cmdActive].href; }
        else if (q) { window.location = cmd.getAttribute('data-search-url') + '?q=' + encodeURIComponent(q); }
      }
      else if (ev.key === 'Escape') { ev.preventDefault(); if (cmdInput.value) { cmdInput.value = ''; cmdHome(); } else { cmdClose(); } }
      else if (ev.key === 'Tab') { ev.preventDefault(); cmdSelect(cmdActive + (ev.shiftKey ? -1 : 1)); }
    });
    cmdList.addEventListener('mousemove', function (ev) {
      var a = ev.target.closest('.ap-cmd-item');
      if (a) { var i = cmdItems().indexOf(a); if (i !== cmdActive) { cmdSelect(i); } }
    });
    $$('[data-cmd-close]', cmd).forEach(function (b) { b.addEventListener('click', cmdClose); });
  }

  /* ---- confirm dialogs: <form data-confirm="Sure?"> or <button data-confirm="..."> ---- */
  var pending = null;
  var modalEl = $('#confirmModal');
  var modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-confirm]');
    if (!t || t.dataset.confirmed === '1') { return; }
    if (t.tagName === 'FORM') { return; }
    ev.preventDefault();
    ask(t.getAttribute('data-confirm'), function () {
      t.dataset.confirmed = '1';
      dirty = false;
      if (t.form && (t.type === 'submit')) { t.form.requestSubmit ? t.form.requestSubmit(t) : t.form.submit(); }
      else if (t.href) { window.location = t.href; }
      else { t.click(); }
    });
  });
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f.hasAttribute('data-confirm') && f.dataset.confirmed !== '1') {
      ev.preventDefault();
      ask(f.getAttribute('data-confirm'), function () { f.dataset.confirmed = '1'; f.submit(); });
    }
  }, true);
  function ask(message, ok, title) {
    if (!modal) { if (window.confirm(message)) { ok(); } return; }
    $('#confirmTitle').textContent = title || body.getAttribute('data-confirm-title');
    $('#confirmBody').textContent = message || '';
    $('#confirmYes').textContent = body.getAttribute('data-confirm-yes');
    $('#confirmNo').textContent = body.getAttribute('data-confirm-no');
    pending = ok;
    modal.show();
  }
  window.apAsk = ask;
  var yes = $('#confirmYes');
  if (yes) { yes.addEventListener('click', function () { modal.hide(); if (pending) { var p = pending; pending = null; p(); } }); }

  /* ---- unsaved changes warning and the save bar: <form data-unsaved-warning> ---- */
  var dirty = false;
  function markDirty(f, on) {
    dirty = on;
    var bar = $('[data-savebar]', f);
    if (bar) {
      bar.classList.toggle('is-dirty', on);
      var note = $('[data-savebar-note]', bar);
      if (note) { note.textContent = on ? 'You have changes that are not saved yet.' : 'Changes take effect as soon as you save.'; }
    }
  }
  $$('form[data-unsaved-warning]').forEach(function (f) {
    f.addEventListener('input', function () { markDirty(f, true); });
    f.addEventListener('change', function () { markDirty(f, true); });
    f.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---- dynamic rows (repeater) ---- */
  var repSnapshots = [];
  // Each repeater is set up once: on the page, and in content added later (such as a pop-up form) through window.apRepeaters(root).
  function initRep(rep) {
    if (rep.hasAttribute('data-rep-ready')) { return; }
    rep.setAttribute('data-rep-ready', '');
    var rows = $('[data-rows]', rep);
    repSnapshots.push({ rows: rows, html: rows.innerHTML });
    var counter = 1000 + $$('[data-row]', rows).length;
    rep.addEventListener('click', function (ev) {
      var b = ev.target.closest('button');
      if (!b || !rep.contains(b)) { return; }
      var row = b.closest('[data-row]');
      if (b.hasAttribute('data-rep-add')) {
        var tpl = $('template[data-row-template]', rep);
        var n = $$('[data-row]', rows).length + 1;
        var html = tpl.innerHTML.split('__i__').join(String(counter++)).split('__n__').join(String(n));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var fresh = wrap.firstElementChild;
        rows.appendChild(fresh);
        var first = $('input:not([type=hidden]), select', fresh); if (first) { first.focus(); }
      } else if (b.hasAttribute('data-rep-remove') && row) {
        var next = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        var focus = next ? $('input:not([type=hidden]), select', next) : $('[data-rep-add]', rep);
        if (focus) { focus.focus(); }
      } else if (b.hasAttribute('data-rep-up') && row && row.previousElementSibling) {
        rows.insertBefore(row, row.previousElementSibling); b.focus();
      } else if (b.hasAttribute('data-rep-down') && row && row.nextElementSibling) {
        rows.insertBefore(row.nextElementSibling, row); b.focus();
      } else { return; }
      repSync(rep);
    });
    apDrag({ root: rows, handle: '[data-rep-handle]', item: '[data-row]', done: function () { repSync(rep); } });
    repSync(rep, true);
  }
  $$('[data-repeater]').forEach(initRep);
  window.apRepeaters = function (root) { $$('[data-repeater]', root || document).forEach(initRep); };
  function repSync(rep, quiet) {
    var rows = $$('[data-row]', rep);
    var max = parseInt(rep.getAttribute('data-max') || '100', 10);
    var add = $('[data-rep-add]', rep); if (add) { add.disabled = rows.length >= max; }
    var empty = $('[data-empty]', rep); if (empty) { empty.hidden = rows.length > 0; }
    rows.forEach(function (r, i) {
      var up = $('[data-rep-up]', r), down = $('[data-rep-down]', r);
      if (up) { up.disabled = i === 0; up.setAttribute('aria-label', 'Move row ' + (i + 1) + ' up'); }
      if (down) { down.disabled = i === rows.length - 1; down.setAttribute('aria-label', 'Move row ' + (i + 1) + ' down'); }
      var rm = $('[data-rep-remove]', r); if (rm) { rm.setAttribute('aria-label', 'Remove row ' + (i + 1)); }
    });
    if (!quiet) { var f = rep.closest('form'); if (f) { f.dispatchEvent(new Event('input', { bubbles: true })); } }
  }

  /* ---- settings pages: cards, the settings menu, undo, secrets and colours ---- */
  var sform = $('[data-settings-form]');
  var cardKey = 'ap.cards.' + location.pathname;
  function setCard(card, open) {
    card.classList.toggle('is-closed', !open);
    var b = $('[data-card-toggle]', card); if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  }
  function saveCards() { store.set(cardKey, JSON.stringify($$('[data-card].is-closed').map(function (c) { return c.id; }))); }
  var closedCards = store.list(cardKey);
  $$('[data-card]').forEach(function (card) {
    if (closedCards.indexOf(card.id) !== -1 && !$('.is-invalid, .invalid-feedback', card)) { setCard(card, false); }
    var b = $('[data-card-toggle]', card);
    if (b) { b.addEventListener('click', function () { setCard(card, card.classList.contains('is-closed')); saveCards(); }); }
  });
  $$('[data-cards]').forEach(function (b) {
    b.addEventListener('click', function () { var open = b.getAttribute('data-cards') === 'open'; $$('[data-card]').forEach(function (c) { setCard(c, open); }); saveCards(); });
  });
  function showHash() {
    if (!location.hash || location.hash.length < 2) { return; }
    var el = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (!el) { return; }
    var card = el.closest('[data-card]'); if (card) { setCard(card, true); }
    var wrap = el.closest('.ap-field-wrap') || el;
    wrap.classList.remove('is-highlight'); void wrap.offsetWidth; wrap.classList.add('is-highlight');
    wrap.scrollIntoView({ block: 'center' });
    var input = $('input:not([type=hidden]), select, textarea', wrap); if (input) { input.focus({ preventScroll: true }); }
  }
  showHash();
  window.addEventListener('hashchange', showHash);

  var snav = $('[data-settings-nav]');
  if (snav) {
    var catKey = 'ap.snav.closed';
    var closedCats = store.list(catKey);
    var setCat = function (cat, open) { cat.classList.toggle('is-closed', !open); var b = $('button', cat); if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); } };
    var saveCats = function () { store.set(catKey, JSON.stringify($$('[data-snav-cat].is-closed', snav).map(function (c) { return c.getAttribute('data-snav-cat'); }))); };
    var small = window.innerWidth < 992;
    $$('[data-snav-cat]', snav).forEach(function (cat) {
      // On phones only the group with the open page starts expanded, so the settings come first.
      if ((small || closedCats.indexOf(cat.getAttribute('data-snav-cat')) !== -1) && !$('a.active', cat)) { setCat(cat, false); }
      $('button', cat).addEventListener('click', function () { setCat(cat, cat.classList.contains('is-closed')); if (!small) { saveCats(); } });
    });
    $$('[data-snav]', snav).forEach(function (b) {
      b.addEventListener('click', function () { var open = b.getAttribute('data-snav') === 'open'; $$('[data-snav-cat]', snav).forEach(function (c) { setCat(c, open); }); saveCats(); });
    });
    var sf = $('[data-snav-filter]', snav);
    if (sf) {
      sf.addEventListener('input', function () {
        var q = sf.value.trim().toLowerCase();
        $$('[data-snav-cat]', snav).forEach(function (cat) {
          var shown = 0;
          $$('[data-snav-item]', cat).forEach(function (li) { var ok = q === '' || li.getAttribute('data-snav-item').indexOf(q) !== -1; li.hidden = !ok; if (ok) { shown++; } });
          cat.hidden = shown === 0;
          if (q !== '' && shown) { setCat(cat, true); }
        });
        var none = $('[data-snav-nomatch]', snav);
        if (none) { none.hidden = q === '' || $$('[data-snav-cat]:not([hidden])', snav).length > 0; }
      });
    }
    var act = $('a.active', snav); if (act && act.scrollIntoView) { act.scrollIntoView({ block: 'nearest' }); }
  }

  document.addEventListener('click', function (ev) {
    var u = ev.target.closest('[data-undo]');
    if (u) {
      var f = u.closest('form');
      if (!f) { return; }
      f.reset();
      repSnapshots.forEach(function (s) { if (f.contains(s.rows)) { s.rows.innerHTML = s.html; repSync(s.rows.closest('[data-repeater]'), true); } });
      $$('[data-color-hex]', f).forEach(function (hex) { var c = document.getElementById(hex.getAttribute('data-color-hex')); if (c) { c.value = hex.value; } });
      markDirty(f, false);
      window.apToast('info', 'Your changes were undone.');
      return;
    }
    var r = ev.target.closest('[data-reveal]');
    if (r) {
      var inp = document.getElementById(r.getAttribute('data-reveal'));
      if (inp) { inp.type = inp.type === 'password' ? 'text' : 'password'; $('i', r).className = inp.type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash'; }
    }
  });
  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t.hasAttribute && t.hasAttribute('data-color-for')) { var hex = document.getElementById(t.getAttribute('data-color-for')); if (hex) { hex.value = t.value.toUpperCase(); } }
    if (t.hasAttribute && t.hasAttribute('data-color-hex') && /^#[0-9a-f]{6}$/i.test(t.value)) { var c = document.getElementById(t.getAttribute('data-color-hex')); if (c) { c.value = t.value; } }
  });

  /* ---- bulk actions: checkbox[data-bulk-item], #bulkAll, .ap-bulkbar ---- */
  var bar = $('.ap-bulkbar');
  function syncBulk() {
    var items = $$('input[data-bulk-item]');
    var n = items.filter(function (i) { return i.checked; }).length;
    if (bar) { bar.classList.toggle('show', n > 0); var c = $('[data-bulk-count]', bar); if (c) { c.textContent = n; } }
    var all = $('#bulkAll'); if (all) { all.checked = n > 0 && n === items.length; all.indeterminate = n > 0 && n < items.length; }
  }
  document.addEventListener('change', function (ev) {
    if (ev.target.id === 'bulkAll') { $$('input[data-bulk-item]').forEach(function (i) { i.checked = ev.target.checked; }); }
    if (ev.target.matches('input[data-bulk-item], #bulkAll')) { syncBulk(); }
  });

  /* ---- column picker: <table data-columns="key"> with th[data-col]; dropdown #columnPicker ---- */
  $$('table[data-columns]').forEach(function (table) {
    var key = 'ap.cols.' + table.getAttribute('data-columns');
    var hidden = store.list(key);
    function apply() {
      $$('th[data-col]', table).forEach(function (th) {
        var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
        var off = hidden.indexOf(th.getAttribute('data-col')) !== -1;
        $$('tr', table).forEach(function (tr) { var cell = tr.children[idx]; if (cell) { cell.classList.toggle('ap-col-hidden', off); } });
      });
    }
    var menu = $('#columnPicker');
    if (menu) {
      $$('th[data-col]', table).forEach(function (th) {
        var id = th.getAttribute('data-col');
        var label = document.createElement('label'); label.className = 'dropdown-item';
        var cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'form-check-input me-2'; cb.checked = hidden.indexOf(id) === -1;
        cb.addEventListener('change', function () {
          hidden = hidden.filter(function (h) { return h !== id; });
          if (!cb.checked) { hidden.push(id); }
          store.set(key, JSON.stringify(hidden)); apply();
        });
        label.appendChild(cb); label.appendChild(document.createTextNode(th.textContent.trim()));
        menu.appendChild(label);
      });
    }
    apply();
  });

  /* ---- filter cards: "Between dates" shows the two date boxes ---- */
  document.addEventListener('change', function (ev) {
    if (!ev.target.matches || !ev.target.matches('[data-when]')) { return; }
    var d = ev.target.parentNode.querySelector('[data-when-dates]');
    if (d) { d.hidden = ev.target.value !== 'custom'; }
  });

  /* ---- auto-submit: <select data-autosubmit> ---- */
  document.addEventListener('change', function (ev) {
    if (ev.target.matches('[data-autosubmit]') && ev.target.form) { ev.target.form.submit(); }
  });

  /* ---- copy buttons: <button data-copy="text"> ---- */
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-copy]');
    if (!b) { return; }
    var text = b.getAttribute('data-copy');
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(function () { window.apToast('success', 'Copied'); }); }
  });

  /* ---- choice cards: <div class="ap-cards"> radios ---- */
  document.addEventListener('change', function (ev) {
    var r = ev.target;
    if (r.type === 'radio' && r.closest('.ap-cards')) {
      $$('.ap-card-opt', r.closest('.ap-cards')).forEach(function (c) { c.classList.toggle('is-checked', $('input', c).checked); });
    }
  });

  /* ---- presets: buttons that fill several fields at once (data-preset='{"field":"value"}') ---- */
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-preset]');
    if (!b) { return; }
    var form = b.closest('form'); var data = {};
    try { data = JSON.parse(b.getAttribute('data-preset')); } catch (e) { return; }
    Object.keys(data).forEach(function (name) {
      var els = form ? form.querySelectorAll('[name="' + name + '"]') : [];
      Array.prototype.forEach.call(els, function (el) {
        if (el.type === 'hidden') { return; }
        if (el.type === 'radio') { el.checked = String(el.value) === String(data[name]); }
        else if (el.type === 'checkbox') { el.checked = !!data[name] && data[name] !== '0'; }
        else { el.value = data[name]; }
        el.dispatchEvent(new Event('change', { bubbles: true }));
        var wrap = el.closest('.ap-field-wrap'); if (wrap) { wrap.classList.remove('is-highlight'); void wrap.offsetWidth; wrap.classList.add('is-highlight'); }
      });
    });
    $$('.ap-preset', b.parentNode).forEach(function (x) { x.classList.toggle('is-on', x === b); });
    if (form) { form.dispatchEvent(new Event('input', { bubbles: true })); }
    window.apToast('info', b.textContent.trim() + ' details filled in. Add your own username and password, then save.');
  });

  /* ---- show-if: [data-show-if='[{"email_driver":["smtp"]}]'] shows only when a rule matches ---- */
  function fieldValue(form, name) {
    var els = form.querySelectorAll('[name="' + name + '"]');
    var val = null;
    Array.prototype.forEach.call(els, function (el) {
      if (el.type === 'radio') { if (el.checked) { val = el.value; } }
      else if (el.type === 'checkbox') { val = el.checked ? '1' : '0'; }
      else if (el.type !== 'hidden' || val === null) { val = el.value; }
    });
    return val;
  }
  function applyShowIf() {
    $$('[data-show-if]').forEach(function (el) {
      var form = el.closest('form'); if (!form) { return; }
      var rules = []; try { rules = JSON.parse(el.getAttribute('data-show-if')); } catch (e) { rules = []; }
      var show = rules.some(function (rule) { return Object.keys(rule).every(function (k) { var v = fieldValue(form, k); return v !== null && rule[k].indexOf(String(v)) !== -1; }); });
      el.hidden = !show;
    });
  }
  document.addEventListener('change', function (ev) { if (ev.target.form && document.querySelector('[data-show-if]')) { applyShowIf(); } });
  applyShowIf();

  /* ---- image fields: preview, clear, upload into the media library ---- */
  function imgPreview(wrap) {
    var input = $('input[type=text]', wrap), thumb = $('[data-image-thumb]', wrap);
    var v = input.value.trim();
    thumb.innerHTML = /^(https?:\/\/|\/)/.test(v) ? '<img alt="">' : '<i class="fa-regular fa-image"></i>';
    var img = $('img', thumb); if (img) { img.src = v; img.onerror = function () { thumb.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning"></i>'; }; }
  }
  document.addEventListener('input', function (ev) { var w = ev.target.closest && ev.target.closest('[data-image-field]'); if (w) { imgPreview(w); } });
  document.addEventListener('change', function (ev) { var w = ev.target.closest && ev.target.closest('[data-image-field]'); if (w && ev.target.type === 'text') { imgPreview(w); } });
  document.addEventListener('click', function (ev) {
    var c = ev.target.closest('[data-image-clear]');
    if (c) { var w = c.closest('[data-image-field]'); var i = $('input[type=text]', w); i.value = ''; i.dispatchEvent(new Event('change', { bubbles: true })); imgPreview(w); return; }
    var u = ev.target.closest('[data-image-upload]');
    if (u) { $('[data-image-file]', u.closest('[data-image-field]')).click(); }
  });
  document.addEventListener('change', function (ev) {
    var fi = ev.target;
    if (!fi.matches || !fi.matches('[data-image-file]') || !fi.files.length) { return; }
    var wrap = fi.closest('[data-image-field]'); var cfgEl = document.getElementById('apMediaPicker');
    if (!cfgEl) { return; }
    var fd = new FormData(); fd.append('file', fi.files[0]); fd.append('only', 'image'); fd.append('_token', token());
    var btn = $('[data-image-upload]', wrap); btn.disabled = true;
    fetch(cfgEl.getAttribute('data-upload-url'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false; fi.value = '';
        if (!d.ok) { window.apToast('danger', d.error || 'Upload failed'); return; }
        var i = $('input[type=text]', wrap); i.value = d.item.url; i.dispatchEvent(new Event('change', { bubbles: true })); imgPreview(wrap);
        window.apToast('success', 'Image uploaded to the media library');
      })
      .catch(function () { btn.disabled = false; window.apToast('danger', 'Upload failed'); });
  });

  /* ---- secret text areas: hidden like a password until "show" ---- */
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-reveal-area]'); if (!b) { return; }
    var t = document.getElementById(b.getAttribute('data-reveal-area'));
    if (t) { t.classList.toggle('is-shown'); $('i', b).className = t.classList.contains('is-shown') ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'; }
  });

  /* ---- drop zones: <label data-dropzone><input type="file">...<span data-drop-name></span></label> ---- */
  $$('[data-dropzone]').forEach(function (zone) {
    var input = $('input[type=file]', zone);
    var nameEl = $('[data-drop-name]', zone);
    if (!input) { return; }
    function show() {
      var f = input.files && input.files[0];
      zone.classList.toggle('has-file', !!f);
      if (nameEl) { nameEl.textContent = f ? f.name + ' (' + (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB') + ')' : ''; }
    }
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (n) { zone.addEventListener(n, function (ev) { ev.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'dragend', 'drop'].forEach(function (n) { zone.addEventListener(n, function () { zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (ev) {
      ev.preventDefault();
      var files = ev.dataTransfer && ev.dataTransfer.files;
      if (!files || !files.length) { return; }
      try { input.files = files; } catch (e) { return; }
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
  /* A file dropped outside a zone must not make the browser open it. */
  ['dragover', 'drop'].forEach(function (n) {
    window.addEventListener(n, function (ev) {
      if (ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') !== -1 && !(ev.target.closest && ev.target.closest('[data-dropzone]'))) { ev.preventDefault(); }
    });
  });

  /* ---- sortable lists: <tbody|ul data-sortable data-sort-url="..."> with children [data-sort-id] and a [data-sort-handle] ---- */
  $$('[data-sortable]').forEach(function (list) {
    var dragging = null;
    var url = list.getAttribute('data-sort-url');
    function items() { return $$(':scope > [data-sort-id]', list); }
    function save() {
      if (!url) { return; }
      var fd = new FormData();
      fd.append('_token', token());
      items().forEach(function (it) { fd.append('ids[]', it.getAttribute('data-sort-id')); });
      fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { window.apToast(d.ok ? 'success' : 'danger', d.message || (d.ok ? 'Order saved' : 'Could not save the order')); })
        .catch(function () { window.apToast('danger', 'Could not save the order'); });
    }
    apDrag({ root: list, handle: '[data-sort-handle]', item: '[data-sort-id]', itemOf: function (h) { var i = h.closest('[data-sort-id]'); return i && i.parentNode === list ? i : null; }, done: save });
    /* keyboard: focus a handle and use the arrow keys */
    list.addEventListener('keydown', function (ev) {
      var h = ev.target.closest && ev.target.closest('[data-sort-handle]');
      if (!h || (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown')) { return; }
      var it = h.closest('[data-sort-id]');
      var other = ev.key === 'ArrowUp' ? it.previousElementSibling : it.nextElementSibling;
      if (!other) { return; }
      ev.preventDefault();
      list.insertBefore(it, ev.key === 'ArrowUp' ? other : other.nextSibling);
      h.focus(); save();
    });
  });

  /* ---- arranging a settings page: drag cards and settings, and switch them between half and full width ---- */
  (function () {
    var toggle = document.querySelector('[data-arrange-toggle]');
    var form = document.querySelector('[data-settings-form]');
    var grid = document.querySelector('[data-arrange-cards]');
    if (!toggle || !form || !grid) { return; }
    var reset = document.querySelector('[data-arrange-reset]');
    var dragging = null, timer = null;
    function post(url, data) {
      var fd = new FormData();
      fd.append('_token', token());
      Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
    }
    function collect() {
      var lay = { order: [], cards: {}, fields: {}, forder: {} };
      $$('[data-card-key]', grid).forEach(function (c) {
        var ck = c.getAttribute('data-card-key');
        lay.order.push(ck);
        lay.cards[ck] = c.getAttribute('data-cols') === '2' ? 2 : 1;
        lay.forder[ck] = [];
        $$('[data-field-key]', c).forEach(function (f) {
          var fk = f.getAttribute('data-field-key');
          lay.forder[ck].push(fk);
          lay.fields[fk] = f.classList.contains('ap-field-full') ? 'full' : 'half';
        });
      });
      return lay;
    }
    function save() {
      clearTimeout(timer);
      timer = setTimeout(function () {
        post(toggle.getAttribute('data-arrange-url'), { group: toggle.getAttribute('data-arrange-group'), layout: JSON.stringify(collect()) })
          .then(function (d) { window.apToast(d.ok ? 'success' : 'danger', d.message || (d.ok ? 'Layout saved' : 'Could not save the layout')); })
          .catch(function () { window.apToast('danger', 'Could not save the layout'); });
      }, 250);
    }
    function setArranging(on) {
      form.classList.toggle('is-arranging', on);
      toggle.querySelector('span').textContent = on ? 'Done Arranging' : 'Arrange';
      toggle.classList.toggle('btn-primary', on);
      toggle.classList.toggle('btn-outline-secondary', !on);
      if (reset) { reset.hidden = !on; }
    }
    toggle.addEventListener('click', function () { setArranging(!form.classList.contains('is-arranging')); });
    if (reset) {
      reset.addEventListener('click', function () {
        if (!window.confirm('Put every card and setting on this page back to full width in the original order?')) { return; }
        post(reset.getAttribute('data-arrange-reset-url'), { group: toggle.getAttribute('data-arrange-group') }).then(function () { window.location.reload(); });
      });
    }
    form.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-arr-width]');
      if (!b || !form.classList.contains('is-arranging')) { return; }
      ev.preventDefault();
      var card = b.closest('.ap-field-wrap'), isCard = !card;
      if (isCard) {
        card = b.closest('[data-card-key]');
        var two = card.getAttribute('data-cols') !== '2';
        card.setAttribute('data-cols', two ? '2' : '1');
        b.querySelector('span').textContent = two ? 'Half Width' : 'Full Width';
      } else {
        var full = !card.classList.contains('ap-field-full');
        card.classList.toggle('ap-field-full', full);
        b.querySelector('span').textContent = full ? 'Full Width' : 'Half Width';
      }
      save();
    });
    /* dragging: a card within the page, a setting within its card */
    apDrag({ root: form, handle: '[data-arr-handle]', item: '[data-card-key], .ap-field-wrap', pick: '[data-card-key], [data-field-key]',
      itemOf: function (h) { return h.closest('.ap-field-wrap') || h.closest('[data-card-key]'); },
      enabled: function () { return form.classList.contains('is-arranging'); }, done: save });
    /* keyboard: focus a handle and use the arrow keys */
    form.addEventListener('keydown', function (ev) {
      var h = ev.target.closest && ev.target.closest('[data-arr-handle]');
      if (!h || (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown' && ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight')) { return; }
      var it = h.closest('.ap-field-wrap') || h.closest('[data-card-key]');
      var back = ev.key === 'ArrowUp' || ev.key === 'ArrowLeft';
      var other = back ? it.previousElementSibling : it.nextElementSibling;
      while (other && !other.matches('[data-card-key], [data-field-key]')) { other = back ? other.previousElementSibling : other.nextElementSibling; }
      if (!other) { return; }
      ev.preventDefault();
      it.parentNode.insertBefore(it, back ? other : other.nextSibling);
      h.focus(); save();
    });
  })();

  /* ---- keyboard shortcuts ---- */
  var gPressed = false, gTimer = null;
  document.addEventListener('keydown', function (ev) {
    var tag = (ev.target.tagName || '').toLowerCase();
    var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || ev.target.isContentEditable;
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
      if (cmd) { ev.preventDefault(); if (cmd.hidden) { cmdOpen(''); } else { cmdInput.focus(); cmdInput.select(); } }
      return;
    }
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
      var form = ev.target.closest('form') || $('form[data-unsaved-warning]');
      if (form) { ev.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.submit(); }
      return;
    }
    if (typing || ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
    if (cmd && !cmd.hidden) { return; }
    if (ev.key === '/') { if (cmd) { ev.preventDefault(); cmdOpen(''); } }
    else if (ev.key === '[') { toggleSidebar(); }
    else if (ev.key === '?') { var m = $('#shortcutsModal'); if (m && window.bootstrap) { new bootstrap.Modal(m).show(); } }
    else if (ev.key === 'g') { gPressed = true; clearTimeout(gTimer); gTimer = setTimeout(function () { gPressed = false; }, 900); }
    else if (ev.key === 'd' && gPressed) { window.location = body.getAttribute('data-admin-url'); }
  });

  /* ---- dismissable notices: <div data-notice> with <form data-notice-form> buttons (sent in the background) ---- */
  document.addEventListener('submit', function (ev) {
    var f = ev.target.closest && ev.target.closest('[data-notice-form]');
    if (!f || !window.fetch) { return; }
    ev.preventDefault();
    var box = f.closest('[data-notice]');
    var btns = box ? box.querySelectorAll('button') : [];
    Array.prototype.forEach.call(btns, function (b) { b.disabled = true; });
    fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (d) {
        if (box) {
          box.style.height = box.offsetHeight + 'px';
          box.classList.add('is-leaving');
          setTimeout(function () { box.style.height = '0px'; }, 20);
          setTimeout(function () { box.remove(); }, 380);
        }
        if (d && d.message && window.apToast) { window.apToast('info', d.message); }
      })
      .catch(function () { f.submit(); });
  });
})();
