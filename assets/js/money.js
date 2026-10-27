/* Money pages: styled date picker, drop zone for receipts, small helpers. No other script is needed. */
(function () {
  'use strict';
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function parse(text) {
    var m = /^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/.exec((text || '').trim());
    if (!m) { return null; }
    var d = new Date(+m[3], +m[2] - 1, +m[1]);
    return d.getFullYear() === +m[3] && d.getMonth() === +m[2] - 1 && d.getDate() === +m[1] ? d : null;
  }
  function show(d) { return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear(); }

  // Date picker with three views: days, months and years. Click the month or the year in the header to jump.
  var pop = null, current = null, view = null, mode = 'days', yearStart = 0;
  function close() { if (pop) { pop.remove(); pop = null; current = null; } }
  function place() {
    if (!pop || !current) { return; }
    var r = current.getBoundingClientRect(), h = pop.offsetHeight, w = pop.offsetWidth;
    var top = r.bottom + 6;
    if (top + h > window.innerHeight - 8 && r.top - h - 6 > 8) { top = r.top - h - 6; }
    pop.style.top = Math.max(8, top) + 'px';
    pop.style.left = Math.max(8, Math.min(r.left, document.documentElement.clientWidth - w - 8)) + 'px';
  }
  function setValue(d) {
    current.value = d ? show(d) : '';
    current.dispatchEvent(new Event('input', { bubbles: true }));
    current.dispatchEvent(new Event('change', { bubbles: true }));
    close();
  }

  function draw() {
    if (!pop) { return; }
    var y = view.getFullYear(), mo = view.getMonth(), picked = parse(current.value), today = new Date(), h = '';
    var nav = function (dir, label, step) { return '<button type="button" class="ap-dp-nav" data-step="' + step + '" aria-label="' + label + '"><i class="fa-solid fa-chevron-' + dir + '"></i></button>'; };
    if (mode === 'days') {
      var first = new Date(y, mo, 1), lead = (first.getDay() + 6) % 7, days = new Date(y, mo + 1, 0).getDate();
      h += '<div class="ap-dp-head">' + nav('left', 'Previous month', -1) + '<div class="ap-dp-title"><button type="button" data-mode="months" class="ap-dp-pick">' + MONTHS[mo] + '</button><button type="button" data-mode="years" class="ap-dp-pick">' + y + '</button></div>' + nav('right', 'Next month', 1) + '</div><div class="ap-dp-grid">';
      DAYS.forEach(function (d) { h += '<span class="ap-dp-dow">' + d + '</span>'; });
      for (var i = 0; i < lead; i++) { h += '<span></span>'; }
      for (var d = 1; d <= days; d++) {
        var isPicked = picked && picked.getFullYear() === y && picked.getMonth() === mo && picked.getDate() === d;
        var isToday = today.getFullYear() === y && today.getMonth() === mo && today.getDate() === d;
        h += '<button type="button" data-day="' + d + '" class="' + (isPicked ? 'is-picked ' : '') + (isToday ? 'is-today' : '') + '">' + d + '</button>';
      }
      h += '</div>';
    } else if (mode === 'months') {
      h += '<div class="ap-dp-head">' + nav('left', 'Previous year', -1) + '<div class="ap-dp-title"><button type="button" data-mode="years" class="ap-dp-pick">' + y + '</button></div>' + nav('right', 'Next year', 1) + '</div><div class="ap-dp-cells">';
      MONTHS.forEach(function (m, n) {
        h += '<button type="button" data-month="' + n + '" class="' + (picked && picked.getFullYear() === y && picked.getMonth() === n ? 'is-picked ' : '') + (today.getFullYear() === y && today.getMonth() === n ? 'is-today' : '') + '">' + m.slice(0, 3) + '</button>';
      });
      h += '</div>';
    } else {
      h += '<div class="ap-dp-head">' + nav('left', 'Earlier years', -12) + '<div class="ap-dp-title"><strong>' + yearStart + ' – ' + (yearStart + 11) + '</strong></div>' + nav('right', 'Later years', 12) + '</div><div class="ap-dp-cells">';
      for (var k = 0; k < 12; k++) {
        var yy = yearStart + k;
        h += '<button type="button" data-year="' + yy + '" class="' + (picked && picked.getFullYear() === yy ? 'is-picked ' : '') + (today.getFullYear() === yy ? 'is-today' : '') + '">' + yy + '</button>';
      }
      h += '</div>';
    }
    h += '<div class="ap-dp-foot"><button type="button" data-today>Today</button>' + (current.value ? '<button type="button" data-clear>Clear</button>' : '') + '</div>';
    pop.innerHTML = h;
    place();
  }

  function open(input) {
    close();
    current = input;
    var start = parse(input.value) || new Date();
    view = new Date(start.getFullYear(), start.getMonth(), 1);
    mode = 'days';
    pop = document.createElement('div');
    pop.className = 'ap-dp';
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-label', 'Choose a date');
    document.body.appendChild(pop);
    draw();
    pop.addEventListener('mousedown', function (e) { e.preventDefault(); });
    pop.addEventListener('click', function (e) {
      e.apDp = true;
      var b = e.target.closest('button');
      if (!b) { return; }
      if (b.dataset.step) {
        var n = +b.dataset.step;
        if (mode === 'days') { view = new Date(view.getFullYear(), view.getMonth() + n, 1); }
        else if (mode === 'months') { view = new Date(view.getFullYear() + n, view.getMonth(), 1); }
        else { yearStart += n; }
        draw(); return;
      }
      if (b.dataset.mode) { mode = b.dataset.mode; if (mode === 'years') { yearStart = view.getFullYear() - 4; } draw(); return; }
      if (b.dataset.month) { view = new Date(view.getFullYear(), +b.dataset.month, 1); mode = 'days'; draw(); return; }
      if (b.dataset.year) { view = new Date(+b.dataset.year, view.getMonth(), 1); mode = 'months'; draw(); return; }
      if (b.dataset.day) { setValue(new Date(view.getFullYear(), view.getMonth(), +b.dataset.day)); return; }
      if (b.hasAttribute('data-today')) { setValue(new Date()); return; }
      if (b.hasAttribute('data-clear')) { setValue(null); }
    });
  }

  document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches('input[data-date]')) { open(e.target); } });
  document.addEventListener('click', function (e) {
    if (e.apDp) { return; }
    if (e.target.matches && e.target.matches('input[data-date]')) { if (current !== e.target) { open(e.target); } } else if (pop) { close(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pop) { close(); } if (e.key === 'Tab' && pop) { close(); } });
  document.addEventListener('input', function (e) { if (pop && e.target === current) { var d = parse(current.value); if (d) { view = new Date(d.getFullYear(), d.getMonth(), 1); mode = 'days'; draw(); } } });
  window.addEventListener('resize', place);
  document.addEventListener('scroll', place, true);

  // Filters that apply when changed.
  document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('select[data-autosubmit]') && e.target.form) { e.target.form.submit(); } });

  // Show or hide a box with a tick box.
  function wireToggles(root) { root.querySelectorAll('[data-toggle-target]').forEach(function (cb) {
    var box = document.querySelector(cb.getAttribute('data-toggle-target'));
    if (!box) { return; }
    var sync = function () { box.style.display = cb.checked ? '' : 'none'; };
    cb.addEventListener('change', sync);
    sync();
  }); }

  // Drop zone for receipts.
  function wireZones(root) { root.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('input[type=file]'), list = zone.querySelector('.ap-dz-list');
    function names() {
      list.innerHTML = '';
      Array.prototype.forEach.call(input.files, function (f) {
        var li = document.createElement('li');
        li.textContent = f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + ' KB)';
        list.appendChild(li);
      });
    }
    zone.addEventListener('click', function (e) { if (e.target !== input) { input.click(); } });
    zone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', names);
    ['dragenter', 'dragover'].forEach(function (n) { zone.addEventListener(n, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (n) { zone.addEventListener(n, function (e) { e.preventDefault(); zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f) { dt.items.add(f); });
        Array.prototype.forEach.call(e.dataTransfer.files, function (f) { dt.items.add(f); });
        input.files = dt.files;
        names();
      }
    });
  }); }
  wireToggles(document); wireZones(document);

  // With "A pop-up" chosen in Settings > Money, every link to a new invoice, expense, payment, vehicle or trip opens as a pop-up.
  var NEW_LINK = /\/money\/(invoices|expenses|income|vehicles|trips)\/(new|\d+\/(edit|items\/new|items\/\d+|payments\/new))(\?|$)/;
  // ---- Pop-up forms: links with data-money-modal load the same form (?modal=1) into a dialog. ----
  var overlay = null, lastFocus = null;
  function closeModal() { if (overlay) { overlay.remove(); overlay = null; document.body.style.overflow = ''; if (lastFocus && lastFocus.focus) { lastFocus.focus(); } } }
  function openModal(url, trigger) {
    closeModal();
    lastFocus = trigger || null;
    overlay = document.createElement('div');
    overlay.className = 'mn-modal-back';
    var box = document.createElement('div');
    box.className = 'mn-modal';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mn-modal-load">Loading…</div>';
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { closeModal(); } });
    var sep = url.indexOf('?') === -1 ? '?' : '&';
    fetch(url + sep + 'modal=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error('bad'); } return r.text(); })
      .then(function (html) {
        box.innerHTML = '<button type="button" class="mn-icon mn-modal-x" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' + html;
        if (box.querySelector('.mn-two')) { box.classList.add('is-wide'); }
        wireToggles(box); wireZones(box);
        var first = box.querySelector('input[type=text]:not([data-date]), textarea, select');
        if (first) { first.focus(); }
      })
      .catch(function () { window.location.href = url; });
  }
  function showErrors(form, errors) {
    form.querySelectorAll('[data-error]').forEach(function (n) { n.textContent = ''; n.style.display = 'none'; });
    form.querySelectorAll('.is-invalid').forEach(function (n) { n.classList.remove('is-invalid'); });
    var firstBad = null;
    Object.keys(errors || {}).forEach(function (name) {
      var wrap = form.querySelector('[data-field="' + name + '"]');
      var msg = [].concat(errors[name])[0];
      if (!wrap) { return; }
      var slot = wrap.querySelector('[data-error]');
      if (slot) { slot.textContent = msg; slot.style.display = 'block'; }
      var input = wrap.querySelector('input,select,textarea');
      if (input) { input.classList.add('is-invalid'); firstBad = firstBad || input; }
    });
    if (firstBad) { firstBad.focus(); }
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-money-modal], a[href*="/money/"]') : null;
    if (a && !a.hasAttribute('data-money-modal') && !(document.documentElement.getAttribute('data-money-add') === 'modal' && NEW_LINK.test(a.getAttribute('href') || ''))) { a = null; }
    if (a && !e.metaKey && !e.ctrlKey && !e.shiftKey && e.button === 0) { e.preventDefault(); openModal(a.getAttribute('href'), a); return; }
    if (e.target.closest && e.target.closest('[data-modal-close]')) { e.preventDefault(); closeModal(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay) { closeModal(); } });
  /* Removing an attachment while editing: done in the background, the window stays open. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-file-remove]') : null;
    if (!b) { return; }
    e.preventDefault();
    if (b.disabled) { return; }
    var form = b.closest('form'), tok = form && form.querySelector('input[name=_token]'), data = new FormData();
    if (tok) { data.set('_token', tok.value); }
    b.disabled = true;
    fetch(b.getAttribute('data-file-remove'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) { throw new Error('no'); }
        var line = b.closest('.mn-fileline'); if (line) { line.remove(); }
        if (window.apToast) { window.apToast('success', j.message || 'File removed.'); }
      })
      .catch(function () { b.disabled = false; if (window.apToast) { window.apToast('danger', 'Could not remove the file. Try again.'); } });
  });
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!overlay || !overlay.contains(form) || form.enctype === 'multipart/form-data' && form.querySelector('input[type=file]').files.length) { return; }
    e.preventDefault();
    var data = new FormData(form);
    var btn = e.submitter;
    if (btn && btn.name) { data.set(btn.name, btn.value); }
    var action = (btn && btn.getAttribute('formaction')) || form.getAttribute('action');
    fetch(action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
      .then(function (res) {
        if (res.j && res.j.ok) { window.location.href = res.j.redirect || window.location.href; return; }
        if (res.j && res.j.errors) { showErrors(form, res.j.errors); return; }
        form.submit();
      })
      .catch(function () { form.submit(); });
  }, true);

  // ---- Invoice items: drag to reorder, and the discount box. ----
  var table = document.getElementById('items');
  if (table && table.dataset.orderUrl) {
    var dragging = null;
    var body = table.tBodies[0];
    body.addEventListener('dragstart', function (e) { var tr = e.target.closest('tr[data-id]'); if (!tr) { return; } dragging = tr; tr.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', tr.dataset.id); } catch (x) { /* old browsers */ } });
    body.addEventListener('dragover', function (e) {
      var tr = e.target.closest('tr[data-id]');
      if (!dragging || !tr) { return; }
      e.preventDefault();
      body.querySelectorAll('.is-over').forEach(function (n) { n.classList.remove('is-over'); });
      if (tr !== dragging) { tr.classList.add('is-over'); }
    });
    body.addEventListener('drop', function (e) {
      var tr = e.target.closest('tr[data-id]');
      if (!dragging || !tr) { return; }
      e.preventDefault();
      if (tr !== dragging) { body.insertBefore(dragging, tr); save(); }
    });
    body.addEventListener('dragend', function () { if (dragging) { dragging.classList.remove('is-dragging'); } body.querySelectorAll('.is-over').forEach(function (n) { n.classList.remove('is-over'); }); dragging = null; });
    function save() {
      var data = new FormData();
      data.set('_token', table.dataset.token || '');
      body.querySelectorAll('tr[data-id]').forEach(function (tr) { data.append('ids[]', tr.dataset.id); });
      fetch(table.dataset.orderUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-discount-open]') : null;
    if (!t) { return; }
    e.preventDefault();
    var box = document.getElementById('discount');
    if (box) { box.hidden = !box.hidden; var i = box.querySelector('input'); if (i && !box.hidden) { i.focus(); i.select(); } }
  });

  // ---- Dashboard builder: drag cards, pick widths, add and remove cards. Saves by itself. ----
  var board = document.getElementById('board');
  if (board) {
    var bar = document.getElementById('board-bar');
    var dragCard = null;
    var cards = function () { return [].slice.call(board.querySelectorAll(':scope > .mn-w')); };
    var saveBoard = function () {
      var data = new FormData();
      data.set('_token', board.dataset.token || '');
      cards().forEach(function (c) { data.append('ids[]', c.dataset.id); data.set('w[' + c.dataset.id + ']', c.dataset.w); });
      fetch(board.dataset.saveUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    };
    var setEditing = function (on) {
      board.classList.toggle('is-editing', on);
      if (bar) { bar.hidden = !on; }
      document.querySelectorAll('button[data-board-edit][aria-pressed]').forEach(function (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      cards().forEach(function (c) { if (on) { c.setAttribute('draggable', 'true'); } else { c.removeAttribute('draggable'); } });
    };
    var clearMarks = function () { board.querySelectorAll('.is-drop-before,.is-drop-after').forEach(function (n) { n.classList.remove('is-drop-before', 'is-drop-after'); }); };
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('[data-board-edit]') : null;
      if (t) { e.preventDefault(); setEditing(!board.classList.contains('is-editing')); }
    });
    board.addEventListener('click', function (e) {
      var card = e.target.closest('.mn-w');
      if (!card || !board.classList.contains('is-editing')) { return; }
      var size = e.target.closest('[data-size]');
      var move = e.target.closest('[data-move]');
      var rem = e.target.closest('[data-remove]');
      if (size) {
        card.className = card.className.replace(/\bmn-w-\d+\b/, 'mn-w-' + size.dataset.size);
        card.dataset.w = size.dataset.size;
        card.querySelectorAll('[data-size]').forEach(function (b) { b.classList.toggle('is-on', b === size); });
        saveBoard();
      } else if (move) {
        var list = cards(), i = list.indexOf(card), j = i + parseInt(move.dataset.move, 10);
        if (j >= 0 && j < list.length) { board.insertBefore(card, move.dataset.move === '1' ? list[j].nextSibling : list[j]); saveBoard(); card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
      } else if (rem) {
        var name = card.querySelector('.mn-w-name');
        if (window.confirm('Take "' + (name ? name.textContent : 'this card') + '" off the dashboard?')) { card.remove(); saveBoard(); }
      }
    });
    board.addEventListener('dragstart', function (e) {
      var card = e.target.closest ? e.target.closest('.mn-w') : null;
      if (!card || !board.classList.contains('is-editing') || card.parentNode !== board) { return; }
      dragCard = card; card.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', card.dataset.id); } catch (x) { /* old browsers */ }
    });
    board.addEventListener('dragover', function (e) {
      if (!dragCard) { return; }
      e.preventDefault();
      clearMarks();
      var over = e.target.closest('.mn-w');
      if (over && over !== dragCard) {
        var r = over.getBoundingClientRect();
        var after = r.width < board.clientWidth * 0.9 ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
        over.classList.add(after ? 'is-drop-after' : 'is-drop-before');
      }
    });
    board.addEventListener('drop', function (e) {
      if (!dragCard) { return; }
      e.preventDefault();
      var over = e.target.closest('.mn-w');
      if (over && over !== dragCard) { board.insertBefore(dragCard, over.classList.contains('is-drop-after') ? over.nextSibling : over); }
      else if (!over) { board.appendChild(dragCard); }
      clearMarks(); saveBoard();
    });
    board.addEventListener('dragend', function () { if (dragCard) { dragCard.classList.remove('is-dragging'); } dragCard = null; clearMarks(); });
  }
  // The card form shows only the fields that belong to the chosen kind of card.
  function widgetFields(sel) {
    var form = sel.closest('form');
    var custom = ['metric', 'chart', 'breakdown', 'list'];
    form.querySelectorAll('[data-for]').forEach(function (n) { n.hidden = custom.indexOf(sel.value) === -1 || n.dataset.for.split(' ').indexOf(sel.value) === -1; });
  }
  document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('[data-widget-type]')) { widgetFields(e.target); } });

  // Bank statement check: one box ticks every row that can be added.
  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'bank-all') { document.querySelectorAll('.bank-row:not(:disabled)').forEach(function (c) { c.checked = e.target.checked; }); }
  });


  // Filter bar: show or hide the extra filters, reveal custom dates, apply a ready-made period at once.
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-filter-toggle]') : null;
    if (!b) { return; }
    var panel = b.closest('form').querySelector('[data-filter-panel]');
    panel.hidden = !panel.hidden;
    b.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
  });
  document.addEventListener('change', function (e) {
    if (!e.target.matches || !e.target.matches('[data-period]')) { return; }
    var form = e.target.closest('form');
    var custom = form.querySelector('.mn-custom');
    if (e.target.value === 'custom') { custom.hidden = false; var f = custom.querySelector('input'); if (f) { f.focus(); } } else { form.submit(); }
  });

  // Receipts: pictures and PDFs open in a viewer on the page; other files open or download as usual.
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-file-preview]') : null;
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
    e.preventDefault();
    var overlay = document.createElement('div');
    overlay.className = 'mn-modal-back';
    var box = document.createElement('div');
    box.className = 'mn-modal mn-view';
    var head = document.createElement('div');
    head.className = 'mn-view-head';
    var name = document.createElement('span');
    name.textContent = a.getAttribute('data-name') || '';
    var open = document.createElement('a');
    open.className = 'btn btn-sm btn-outline-secondary mn-btn ms-auto';
    open.href = a.href; open.target = '_blank'; open.rel = 'noopener'; open.textContent = 'Open In New Tab';
    var x = document.createElement('button');
    x.type = 'button'; x.className = 'mn-icon mn-modal-x'; x.setAttribute('aria-label', 'Close'); x.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    head.appendChild(name); head.appendChild(open);
    var body;
    if (a.getAttribute('data-file-preview') === 'image') { body = document.createElement('img'); body.alt = name.textContent; } else { body = document.createElement('iframe'); body.title = name.textContent; }
    body.src = a.href;
    box.appendChild(x); box.appendChild(head); box.appendChild(body);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    function close() { overlay.remove(); document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); }
    function onKey(ev) { if (ev.key === 'Escape') { close(); } }
    x.addEventListener('click', close);
    overlay.addEventListener('mousedown', function (ev) { if (ev.target === overlay) { close(); } });
    document.addEventListener('keydown', onKey);
  });


  // Bulk actions: tick rows, then the bar above the table acts on all of them.
  function bulkSync(form) {
    var one = [].slice.call(document.querySelectorAll('input[data-bulk-one][form="' + form.id + '"]'));
    var n = one.filter(function (c) { return c.checked; }).length;
    form.hidden = n === 0;
    var c = form.querySelector('[data-bulk-n]'); if (c) { c.textContent = n; }
    var all = document.querySelector('input[data-bulk-all="' + form.id + '"]');
    if (all) { all.checked = n > 0 && n === one.length; all.indeterminate = n > 0 && n < one.length; }
    one.forEach(function (x) { var tr = x.closest('tr'); if (tr) { tr.classList.toggle('is-ticked', x.checked); } });
  }
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t.matches) { return; }
    if (t.matches('input[data-bulk-all]')) {
      var form = document.getElementById(t.getAttribute('data-bulk-all'));
      document.querySelectorAll('input[data-bulk-one][form="' + form.id + '"]').forEach(function (c) { c.checked = t.checked; });
      bulkSync(form);
    } else if (t.matches('input[data-bulk-one]')) {
      var f = document.getElementById(t.getAttribute('form')); if (f) { bulkSync(f); }
    } else if (t.matches('select[data-bulk-op]') && t.value !== '') {
      var bf = t.closest('form');
      bf.querySelector('input[type=hidden][name=do]').value = t.getAttribute('data-bulk-op'); bf.querySelector('input[type=hidden][name=value]').value = t.value; bf.submit();
    }
  });
  document.addEventListener('click', function (e) {
    var go = e.target.closest ? e.target.closest('[data-bulk-go]') : null;
    if (go) {
      var f = go.closest('form'), inp = f.querySelector('[data-bulk-input="' + go.getAttribute('data-bulk-go') + '"]');
      if (!inp.value.trim()) { inp.focus(); return; }
      f.querySelector('input[type=hidden][name=do]').value = go.getAttribute('data-bulk-go'); f.querySelector('input[type=hidden][name=value]').value = inp.value.trim(); f.submit();
    }
    var clr = e.target.closest ? e.target.closest('[data-bulk-clear]') : null;
    if (clr) {
      var form = clr.closest('form');
      document.querySelectorAll('input[data-bulk-one][form="' + form.id + '"], input[data-bulk-all="' + form.id + '"]').forEach(function (c) { c.checked = false; });
      bulkSync(form);
    }
  });

  // Close the Actions menu when clicking elsewhere.
  document.addEventListener('click', function (e) { document.querySelectorAll('details.mn-menu[open]').forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } }); });
})();

/* Print buttons: a click on [data-print] opens the print dialog (the page's security policy blocks inline onclick). */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest && ev.target.closest('[data-print]');
  if (b) { ev.preventDefault(); window.print(); }
});

/* Searchable choice box (Fields::picker): type to narrow the list; choosing an invoice fills in client, stream and amount. */
(function () {
  'use strict';
  function closeAll(except) {
    document.querySelectorAll('[data-pick] .mn-pick-panel:not([hidden])').forEach(function (p) {
      if (except && except.contains(p)) { return; }
      p.hidden = true;
      var b = p.parentNode.querySelector('[data-pick-open]'); if (b) { b.setAttribute('aria-expanded', 'false'); }
    });
  }
  function filter(box) {
    var q = (box.querySelector('[data-pick-q]').value || '').toLowerCase().trim().split(/\s+/).filter(Boolean), shown = 0;
    box.querySelectorAll('.mn-pick-item').forEach(function (it) {
      var hay = it.getAttribute('data-search') || '', ok = it.classList.contains('is-none') ? !q.length : q.every(function (w) { return hay.indexOf(w) !== -1; });
      it.hidden = !ok; if (ok && !it.classList.contains('is-none')) { shown++; }
    });
    box.querySelector('.mn-pick-empty').hidden = shown > 0 || !q.length;
  }
  function fill(form, name, val, onlyEmpty) {
    var el = form.querySelector('[name="' + name + '"]'); if (!el || val === null) { return; }
    if (onlyEmpty && el.value.trim() !== '' && el.value !== '0') { return; }
    el.value = val; el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  document.addEventListener('click', function (e) {
    var t = e.target; if (!t.closest) { return; }
    var open = t.closest('[data-pick-open]');
    if (open) {
      var box = open.closest('[data-pick]'), panel = box.querySelector('.mn-pick-panel'), was = !panel.hidden;
      closeAll(); panel.hidden = was; open.setAttribute('aria-expanded', was ? 'false' : 'true');
      if (!was) { var q = box.querySelector('[data-pick-q]'); q.value = ''; filter(box); q.focus(); }
      return;
    }
    var it = t.closest('.mn-pick-item');
    if (it) {
      var b = it.closest('[data-pick]'), form = it.closest('form');
      b.querySelector('input[type="hidden"]').value = it.getAttribute('data-value');
      b.querySelector('[data-pick-text]').textContent = it.getAttribute('data-label');
      b.querySelectorAll('.mn-pick-item.is-on').forEach(function (x) { x.classList.remove('is-on'); }); it.classList.add('is-on');
      b.querySelector('.mn-pick-panel').hidden = true; b.querySelector('[data-pick-open]').setAttribute('aria-expanded', 'false');
      if (form && it.hasAttribute('data-balance')) {
        fill(form, 'contact_id', it.getAttribute('data-contact'), true); fill(form, 'stream_id', it.getAttribute('data-stream'), true);
        fill(form, 'amount', it.getAttribute('data-balance'), true); fill(form, 'title', it.getAttribute('data-title'), true);
      }
      return;
    }
    if (!t.closest('[data-pick]')) { closeAll(); }
  });
  document.addEventListener('input', function (e) { var q = e.target.closest ? e.target.closest('[data-pick-q]') : null; if (q) { filter(q.closest('[data-pick]')); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.querySelector('[data-pick] .mn-pick-panel:not([hidden])')) { e.stopPropagation(); closeAll(); }
    if (e.key === 'Enter' && e.target.closest && e.target.closest('[data-pick-q]')) {
      e.preventDefault(); var first = e.target.closest('[data-pick]').querySelector('.mn-pick-item:not([hidden]):not(.is-none)'); if (first) { first.click(); }
    }
  }, true);
})();

// Receipt Inbox: on a phone, saving starts as soon as a photo is taken.
(function () {
  var f = document.querySelector('form[data-snap]');
  if (!f || !window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) { return; }
  var input = f.querySelector('input[type=file]');
  if (input) { input.addEventListener('change', function () { if (input.files.length) { f.submit(); } }); }
})();

// Scheduled Reports form: show only the parts that fit the choices made, and do not post the hidden ones.
(function () {
  var form = document.querySelector('.rp-form');
  if (!form) { return; }
  function sync() {
    form.querySelectorAll('[data-rp-when]').forEach(function (box) {
      var ok = true;
      box.getAttribute('data-rp-when').split(';').forEach(function (rule) {
        var p = rule.split(':'), el = form.elements[p[0]];
        if (el && p[1].split(',').indexOf(el.value) === -1) { ok = false; }
      });
      if (box.parentElement && box.parentElement.closest('[data-rp-when][hidden]')) { ok = false; }
      box.hidden = !ok;
      box.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !ok; });
    });
  }
  form.addEventListener('change', sync);
  sync();
})();

// A switch that saves as soon as it is flipped.
document.addEventListener('change', function (e) { var t = e.target; if (t.matches && t.matches('input[data-submit-on-change]') && t.form) { t.form.submit(); } });

/* Forms marked data-bg-save are saved in the background: no reload, a message when done, and the boxes with a problem marked. */
(function () {
  'use strict';
  function clear(form) {
    form.querySelectorAll('[data-bg-err]').forEach(function (n) { n.remove(); });
    form.querySelectorAll('.is-invalid').forEach(function (n) { n.classList.remove('is-invalid'); });
  }
  function mark(form, errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (name) {
      var msg = [].concat(errors[name])[0];
      var field = form.querySelector('[name="' + name + '"]') || form.querySelector('[data-name="' + name + '"]');
      if (!field) { return; }
      if (field.matches('input,select,textarea')) { field.classList.add('is-invalid'); }
      var note = document.createElement('div');
      note.className = 'invalid-feedback d-block';
      note.setAttribute('data-bg-err', '');
      note.textContent = msg;
      var wrap = field.closest('.ap-field') || field.parentNode;
      wrap.appendChild(note);
      first = first || field;
    });
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus) { first.focus({ preventScroll: true }); } }
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-bg-save]') || !window.fetch) { return; }
    var btn = e.submitter;
    if (btn && btn.hasAttribute('formaction')) { return; }
    e.preventDefault();
    var go = form.querySelector('[data-bg-go]');
    var label = go ? go.querySelector('span') : null;
    var before = label ? label.textContent : '';
    if (go) { go.disabled = true; }
    if (label) { label.textContent = 'Saving…'; }
    clear(form);
    fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) {
          if (window.apToast) { window.apToast('success', j.message || 'Saved.'); }
          form.dispatchEvent(new CustomEvent('bg-saved', { detail: j, bubbles: true }));
          var swap = form.getAttribute('data-bg-swap');
          var target = swap ? form.closest(swap) : null;
          if (target && j.html) {
            var holder = document.createElement('div');
            holder.innerHTML = j.html;
            var fresh = holder.firstElementChild;
            if (fresh) { target.replaceWith(fresh); }
          }
          return;
        }
        mark(form, (j && j.errors) || {});
        if (window.apToast) { window.apToast('warning', (j && j.message) || 'Please check the form.'); }
      })
      .catch(function () { if (window.apToast) { window.apToast('danger', 'Could not save. Check your connection and try again.'); } })
      .then(function () { if (go) { go.disabled = false; } if (label) { label.textContent = before; } });
  });
})();
