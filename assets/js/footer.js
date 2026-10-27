/* Footer builder: rows of 12-part columns holding blocks, all moved with drag and drop (and buttons for the keyboard). Plain JavaScript. */
(function () {
  'use strict';
  var form = document.querySelector('[data-footer-form]');
  var dataEl = document.getElementById('footerData');
  if (!form || !dataEl) { return; }
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var cfg = JSON.parse(dataEl.textContent || '{}');
  var L = cfg.layout, BLOCKS = cfg.blocks || {};
  var max = { rows: +form.getAttribute('data-max-rows') || 6, cols: +form.getAttribute('data-max-cols') || 6, blocks: +form.getAttribute('data-max-blocks') || 10 };
  var rowsEl = $('[data-rows]', form);
  var lastCol = null; // the column the palette buttons add to
  var open = null;    // the block whose settings are open
  var uid = 0;
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var toast = function (t, m) { if (window.apToast) { window.apToast(t, m); } };
  var PRESETS = [[12], [6, 6], [4, 4, 4], [3, 3, 3, 3], [4, 8], [8, 4], [5, 3, 4], [6, 3, 3], [3, 3, 6], [2, 2, 2, 2, 2, 2]];

  function changed() { form.dispatchEvent(new Event('input', { bubbles: true })); preview(); }

  function newBlock(type) {
    var b = { type: type };
    (BLOCKS[type].fields || []).forEach(function (f) { b[f.key] = f.type === 'links' ? [] : (f['default'] !== undefined ? f['default'] : (f.type === 'checkbox' ? false : '')); });
    return b;
  }

  function summary(b) {
    var def = BLOCKS[b.type] || {};
    if (b.title) { return b.title; }
    if (b.type === 'menu') { var f = (def.fields || []).filter(function (x) { return x.key === 'menu'; })[0]; return f && f.options[b.menu] ? f.options[b.menu] : ''; }
    if (b.type === 'links') { return (b.links || []).length + ' link(s)'; }
    if (b.type === 'button') { return b.label || ''; }
    if (b.type === 'about' || b.type === 'text') { return String(b.text || b.html || '').replace(/<[^>]+>/g, '').slice(0, 50); }
    return def.help || '';
  }

  /* ---------------------------------------------------------------- drawing */
  function render() {
    rowsEl.innerHTML = '';
    if (!L.rows.length) { rowsEl.innerHTML = '<div class="apf-empty">No rows yet. Press "Add a Row".</div>'; }
    L.rows.forEach(function (row, ri) { rowsEl.appendChild(drawRow(row, ri)); });
    $('[data-add-row]', form).disabled = L.rows.length >= max.rows;
  }

  function drawRow(row, ri) {
    var total = row.columns.reduce(function (n, c) { return n + c.width; }, 0);
    var el = document.createElement('div');
    el.className = 'apf-row'; el._row = row;
    var presets = PRESETS.map(function (p) {
      var on = p.join(',') === row.columns.map(function (c) { return c.width; }).join(',');
      return '<button type="button" class="apf-preset' + (on ? ' is-on' : '') + '" data-preset="' + p.join(',') + '" title="' + p.length + ' column(s): ' + p.join(' + ') + '" aria-label="' + p.length + ' columns, ' + p.join(' + ') + '">'
        + p.map(function (w) { return '<i style="flex:' + w + '"></i>'; }).join('') + '</button>';
    }).join('');
    var sel = function (name, options, cur) { return '<select class="form-select form-select-sm" data-row-f="' + name + '" aria-label="' + (name === 'phone' ? 'On phones' : 'Line up') + '">' + Object.keys(options).map(function (k) { return '<option value="' + k + '"' + (k === cur ? ' selected' : '') + '>' + esc(options[k]) + '</option>'; }).join('') + '</select>'; };
    el.innerHTML = '<div class="apf-row-head">'
      + '<span class="apf-grip" draggable="true" data-drag="row" title="Drag to move the row" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>'
      + '<strong>Row ' + (ri + 1) + '</strong><span class="apf-total' + (total > 12 ? ' is-over' : '') + '">' + total + ' of 12' + (total > 12 ? ': the last column goes onto a new line' : '') + '</span>'
      + '<span class="apf-presets" role="group" aria-label="Column layout">' + presets + '</span>'
      + '<span class="apf-row-opts"><label class="small text-muted">Phones</label>' + sel('phone', cfg.phone, row.phone) + '<label class="small text-muted">Line up</label>' + sel('align', cfg.align, row.align) + '</span>'
      + '<span class="apf-tools"><button type="button" data-row-move="-1" title="Move up" aria-label="Move row up"' + (ri === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button>'
      + '<button type="button" data-row-move="1" title="Move down" aria-label="Move row down"' + (ri === L.rows.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button>'
      + '<button type="button" data-row-remove class="text-danger" title="Remove the row" aria-label="Remove row"><i class="fa-solid fa-trash-can"></i></button></span></div>'
      + '<div class="apf-cols" data-cols></div>'
      + (row.columns.length < max.cols ? '<button type="button" class="apf-add-col" data-add-col><i class="fa-solid fa-plus me-1"></i>Add a column</button>' : '');
    var cols = $('[data-cols]', el);
    row.columns.forEach(function (col, ci) { cols.appendChild(drawCol(row, col, ci)); });
    return el;
  }

  function drawCol(row, col, ci) {
    var el = document.createElement('div');
    el.className = 'apf-col' + (lastCol === col ? ' is-target' : ''); el._col = col; el._row = row;
    el.style.gridColumn = 'span ' + Math.min(12, col.width);
    var w = '<select class="form-select form-select-sm apf-width" data-col-width aria-label="Width of the column">';
    for (var i = 1; i <= 12; i++) { w += '<option value="' + i + '"' + (i === col.width ? ' selected' : '') + '>' + i + '/12</option>'; }
    w += '</select>';
    el.innerHTML = '<div class="apf-col-head"><span class="apf-grip" draggable="true" data-drag="col" title="Drag to move the column" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>' + w
      + '<span class="apf-tools"><button type="button" data-col-move="-1" title="Move left" aria-label="Move column left"' + (ci === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-left"></i></button>'
      + '<button type="button" data-col-move="1" title="Move right" aria-label="Move column right"' + (ci === row.columns.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-right"></i></button>'
      + '<button type="button" data-col-remove class="text-danger" title="Remove the column" aria-label="Remove column"><i class="fa-solid fa-xmark"></i></button></span></div>'
      + '<div class="apf-blocks" data-blocks></div>'
      + '<div class="dropdown"><button type="button" class="apf-add-block" data-bs-toggle="dropdown" aria-expanded="false"' + (col.blocks.length >= max.blocks ? ' disabled' : '') + '><i class="fa-solid fa-plus me-1"></i>Add a block</button><ul class="dropdown-menu">'
      + Object.keys(BLOCKS).map(function (t) { return '<li><button type="button" class="dropdown-item" data-add-block="' + t + '"><i class="fa-solid ' + BLOCKS[t].icon + ' fa-fw me-2 text-muted"></i>' + esc(BLOCKS[t].label) + '</button></li>'; }).join('')
      + '</ul></div>';
    var list = $('[data-blocks]', el);
    if (!col.blocks.length) { list.innerHTML = '<div class="apf-drop-hint">Drop a block here</div>'; }
    col.blocks.forEach(function (b, bi) { list.appendChild(drawBlock(col, b, bi)); });
    return el;
  }

  function drawBlock(col, b, bi) {
    var def = BLOCKS[b.type] || { label: b.type, icon: 'fa-cube', fields: [] };
    var el = document.createElement('div');
    var isOpen = open === b;
    el.className = 'apf-block' + (isOpen ? ' is-open' : ''); el._block = b; el._col = col;
    var id = 'apfb' + (++uid);
    el.innerHTML = '<div class="apf-block-row"><span class="apf-grip" draggable="true" data-drag="block" title="Drag to move the block" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>'
      + '<button type="button" class="apf-block-toggle" data-block-toggle aria-expanded="' + isOpen + '" aria-controls="' + id + '"><i class="fa-solid ' + def.icon + '" aria-hidden="true"></i><span><strong>' + esc(def.label) + '</strong><small>' + esc(summary(b)) + '</small></span><i class="fa-solid fa-chevron-down ms-auto"></i></button></div>'
      + '<div class="apf-block-edit" id="' + id + '"' + (isOpen ? '' : ' hidden') + '><div class="row g-2" data-fields></div>'
      + '<div class="apf-block-foot"><span class="apf-tools"><button type="button" data-block-move="-1" title="Move up" aria-label="Move block up"' + (bi === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button>'
      + '<button type="button" data-block-move="1" title="Move down" aria-label="Move block down"' + (bi === col.blocks.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button>'
      + '<button type="button" data-block-col="-1" title="To the column on the left" aria-label="Move block to the column on the left"><i class="fa-solid fa-arrow-left"></i></button>'
      + '<button type="button" data-block-col="1" title="To the column on the right" aria-label="Move block to the column on the right"><i class="fa-solid fa-arrow-right"></i></button></span>'
      + '<button type="button" class="btn btn-link btn-sm text-danger px-0" data-block-remove><i class="fa-solid fa-trash-can me-1"></i>Remove</button></div></div>';
    if (isOpen) { fillFields($('[data-fields]', el), def.fields || [], b, el); }
    return el;
  }

  function fillFields(box, fields, b, blockEl) {
    fields.forEach(function (f) {
      var fid = 'apff' + (++uid);
      var wrap = document.createElement('div');
      wrap.className = f.half ? 'col-sm-6' : 'col-12';
      var v = b[f.key];
      if (f.type === 'checkbox') {
        wrap.innerHTML = '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="' + fid + '"' + (v ? ' checked' : '') + '><label class="form-check-label small" for="' + fid + '">' + esc(f.label) + '</label></div>';
      } else if (f.type === 'links') {
        wrap.innerHTML = '<div class="form-label small mb-1">' + esc(f.label) + '</div>';
        wrap.appendChild(linksEditor(b[f.key] = b[f.key] || [], function () { $('.apf-block-toggle small', blockEl).textContent = summary(b); }));
        box.appendChild(wrap); return;
      } else {
        var input;
        if (f.type === 'select') {
          input = '<select class="form-select form-select-sm" id="' + fid + '">' + Object.keys(f.options).map(function (k) { return '<option value="' + esc(k) + '"' + (k === v ? ' selected' : '') + '>' + esc(f.options[k]) + '</option>'; }).join('') + '</select>';
        } else if (f.type === 'textarea') {
          input = '<textarea class="form-control form-control-sm" rows="3" id="' + fid + '" maxlength="' + (f.max || 1500) + '" placeholder="' + esc(f.placeholder || '') + '">' + esc(v) + '</textarea>';
        } else {
          var t = f.type === 'number' ? 'number' : 'text';
          input = '<input type="' + t + '" class="form-control form-control-sm" id="' + fid + '" value="' + esc(v) + '"' + (f.max && t === 'text' ? ' maxlength="' + f.max + '"' : '') + (t === 'number' ? ' min="' + (f.min || 0) + '" max="' + (f.max || 9999) + '"' : '') + ' placeholder="' + esc(f.placeholder || (f.type === 'url' ? '/contact or https://…' : '')) + '">';
          if (f.type === 'image') { input = '<div class="input-group input-group-sm">' + input + '<button type="button" class="btn btn-outline-secondary" data-pick-image><i class="fa-regular fa-images me-1"></i>Choose</button></div>'; }
        }
        wrap.innerHTML = '<label class="form-label small" for="' + fid + '">' + esc(f.label) + '</label>' + input + (f.help ? '<div class="form-text">' + esc(f.help) + '</div>' : '');
      }
      box.appendChild(wrap);
      var el = $('#' + fid, wrap);
      var sync = function () {
        b[f.key] = f.type === 'checkbox' ? el.checked : (f.type === 'number' ? parseInt(el.value, 10) || 0 : el.value);
        $('.apf-block-toggle small', blockEl).textContent = summary(b);
        changed();
      };
      el.addEventListener(f.type === 'checkbox' || f.type === 'select' ? 'change' : 'input', sync);
      var pick = $('[data-pick-image]', wrap);
      if (pick) {
        pick.addEventListener('click', function () {
          if (!window.apMediaPicker) { return toast('info', 'Paste the address of the picture, or switch on the Media library.'); }
          window.apMediaPicker.open({ kind: 'image', title: 'Choose the Logo', onSelect: function (it) { el.value = it.url; sync(); } });
        });
      }
    });
  }

  /* a small list of links: label, address, new tab; rows can be dragged */
  function linksEditor(list, onChange) {
    var box = document.createElement('div');
    box.className = 'apf-links';
    function draw() {
      box.innerHTML = '';
      list.forEach(function (l, i) {
        var r = document.createElement('div');
        r.className = 'apf-link'; r._i = i;
        r.innerHTML = '<span class="apf-grip" draggable="true" data-drag="link" aria-hidden="true" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span>'
          + '<input type="text" class="form-control form-control-sm" maxlength="80" placeholder="Label" aria-label="Label of link ' + (i + 1) + '" value="' + esc(l.label) + '" data-k="label">'
          + '<input type="text" class="form-control form-control-sm" maxlength="500" placeholder="/privacy or https://…" aria-label="Address of link ' + (i + 1) + '" value="' + esc(l.url) + '" data-k="url">'
          + '<label class="apf-newtab" title="Open in a new tab"><input type="checkbox" class="form-check-input" data-k="new_tab"' + (l.new_tab ? ' checked' : '') + '> <i class="fa-solid fa-arrow-up-right-from-square"></i><span class="visually-hidden">Open link ' + (i + 1) + ' in a new tab</span></label>'
          + '<button type="button" class="btn btn-sm btn-light" data-up aria-label="Move link ' + (i + 1) + ' up"' + (i === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button>'
          + '<button type="button" class="btn btn-sm btn-light text-danger" data-del aria-label="Remove link ' + (i + 1) + '"><i class="fa-solid fa-xmark"></i></button>';
        $$('[data-k]', r).forEach(function (inp) {
          inp.addEventListener(inp.type === 'checkbox' ? 'change' : 'input', function () { l[inp.getAttribute('data-k')] = inp.type === 'checkbox' ? inp.checked : inp.value; onChange(); changed(); });
        });
        $('[data-up]', r).addEventListener('click', function () { list.splice(i - 1, 0, list.splice(i, 1)[0]); draw(); onChange(); changed(); });
        $('[data-del]', r).addEventListener('click', function () { list.splice(i, 1); draw(); onChange(); changed(); });
        box.appendChild(r);
      });
      var add = document.createElement('button');
      add.type = 'button'; add.className = 'btn btn-sm btn-outline-primary'; add.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Add a link';
      add.disabled = list.length >= 30;
      add.addEventListener('click', function () { list.push({ label: '', url: '', new_tab: false }); draw(); onChange(); var ins = $$('[data-k=label]', box); if (ins.length) { ins[ins.length - 1].focus(); } });
      box.appendChild(add);
    }
    var from = null;
    box.addEventListener('dragstart', function (ev) { var g = ev.target.closest && ev.target.closest('[data-drag=link]'); if (!g) { return; } ev.stopPropagation(); from = g.closest('.apf-link')._i; var row = g.closest('.apf-link'); row.classList.add('is-dragging'); try { ev.dataTransfer.setDragImage(row, 10, 10); ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* ignore */ } });
    box.addEventListener('dragover', function (ev) { if (from === null) { return; } ev.preventDefault(); ev.stopPropagation(); });
    box.addEventListener('drop', function (ev) {
      if (from === null) { return; }
      ev.preventDefault(); ev.stopPropagation();
      var over = ev.target.closest('.apf-link'); var to = over ? over._i : list.length - 1;
      if (over && ev.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2 && to < from) { to++; }
      if (to !== from) { list.splice(to, 0, list.splice(from, 1)[0]); }
      from = null; draw(); onChange(); changed();
    });
    box.addEventListener('dragend', function () { from = null; $$('.is-dragging', box).forEach(function (r) { r.classList.remove('is-dragging'); }); });
    draw();
    return box;
  }

  /* ---------------------------------------------------------------- buttons */
  function colIndex(col) { for (var r = 0; r < L.rows.length; r++) { var i = L.rows[r].columns.indexOf(col); if (i > -1) { return [r, i]; } } return null; }
  function addBlock(col, type) {
    if (!col) {
      if (!L.rows.length) { L.rows.push({ phone: 'stack', align: 'start', columns: [{ width: 12, blocks: [] }] }); }
      col = L.rows[L.rows.length - 1].columns[0] || (L.rows[L.rows.length - 1].columns[0] = { width: 12, blocks: [] });
    }
    if (col.blocks.length >= max.blocks) { return toast('warning', 'A column can hold up to ' + max.blocks + ' blocks.'); }
    var b = newBlock(type); col.blocks.push(b); open = b; lastCol = col;
    render(); changed();
  }

  rowsEl.addEventListener('click', function (ev) {
    var t = ev.target.closest('button'); if (!t) { return; }
    var rowEl = t.closest('.apf-row'), colEl = t.closest('.apf-col'), blockEl = t.closest('.apf-block');
    var row = rowEl && rowEl._row, col = colEl && colEl._col;
    if (colEl) { lastCol = col; $$('.apf-col.is-target', rowsEl).forEach(function (c) { c.classList.remove('is-target'); }); colEl.classList.add('is-target'); }
    var ri = row ? L.rows.indexOf(row) : -1;
    if (t.hasAttribute('data-preset')) {
      var widths = t.getAttribute('data-preset').split(',').map(Number);
      var blocks = []; row.columns.forEach(function (c) { blocks.push(c.blocks); });
      var cols = widths.map(function (w, i) { return { width: w, blocks: blocks[i] || [] }; });
      for (var k = widths.length; k < blocks.length; k++) { cols[cols.length - 1].blocks = cols[cols.length - 1].blocks.concat(blocks[k]); }
      row.columns = cols; render(); changed();
    } else if (t.hasAttribute('data-row-move')) {
      var to = ri + (+t.getAttribute('data-row-move')); L.rows.splice(to, 0, L.rows.splice(ri, 1)[0]); render(); changed();
    } else if (t.hasAttribute('data-row-remove')) {
      var n = row.columns.reduce(function (a, c) { return a + c.blocks.length; }, 0);
      var go = function () { L.rows.splice(ri, 1); render(); changed(); };
      if (n && window.apAsk) { window.apAsk('Remove row ' + (ri + 1) + ' and its ' + n + ' block(s)?', go, 'Remove the row'); } else { go(); }
    } else if (t.hasAttribute('data-add-col')) {
      var used = row.columns.reduce(function (a, c) { return a + c.width; }, 0);
      row.columns.push({ width: Math.max(2, Math.min(12, 12 - used) || 3), blocks: [] }); render(); changed();
    } else if (t.hasAttribute('data-col-move')) {
      var ci = row.columns.indexOf(col), cto = ci + (+t.getAttribute('data-col-move')); row.columns.splice(cto, 0, row.columns.splice(ci, 1)[0]); render(); changed();
    } else if (t.hasAttribute('data-col-remove')) {
      var goc = function () { row.columns.splice(row.columns.indexOf(col), 1); render(); changed(); };
      if (col.blocks.length && window.apAsk) { window.apAsk('Remove this column and its ' + col.blocks.length + ' block(s)?', goc, 'Remove the column'); } else { goc(); }
    } else if (t.hasAttribute('data-add-block')) {
      addBlock(col, t.getAttribute('data-add-block'));
    } else if (t.hasAttribute('data-block-toggle')) {
      open = open === blockEl._block ? null : blockEl._block; render();
    } else if (t.hasAttribute('data-block-move')) {
      var b = blockEl._block, bi = col.blocks.indexOf(b); col.blocks.splice(bi + (+t.getAttribute('data-block-move')), 0, col.blocks.splice(bi, 1)[0]); render(); changed();
    } else if (t.hasAttribute('data-block-col')) {
      var all = []; L.rows.forEach(function (r) { r.columns.forEach(function (c) { all.push(c); }); });
      var target = all[all.indexOf(col) + (+t.getAttribute('data-block-col'))];
      if (!target) { return toast('info', 'There is no column on that side.'); }
      target.blocks.push(col.blocks.splice(col.blocks.indexOf(blockEl._block), 1)[0]); lastCol = target; render(); changed();
    } else if (t.hasAttribute('data-block-remove')) {
      col.blocks.splice(col.blocks.indexOf(blockEl._block), 1); render(); changed();
    }
  });
  rowsEl.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t.hasAttribute('data-col-width')) { t.closest('.apf-col')._col.width = parseInt(t.value, 10) || 4; render(); changed(); }
    else if (t.hasAttribute('data-row-f')) { t.closest('.apf-row')._row[t.getAttribute('data-row-f')] = t.value; changed(); }
  });
  $('[data-add-row]', form).addEventListener('click', function () {
    if (L.rows.length >= max.rows) { return; }
    var cols = [{ width: 4, blocks: [] }, { width: 4, blocks: [] }, { width: 4, blocks: [] }];
    L.rows.push({ phone: 'stack', align: 'start', columns: cols }); lastCol = cols[0]; render(); changed();
  });
  $$('[data-pal]', form).forEach(function (p) { p.addEventListener('click', function () { addBlock(lastCol && colIndex(lastCol) ? lastCol : null, p.getAttribute('data-pal')); }); });

  /* ---------------------------------------------------------------- drag and drop */
  var drag = null; // {kind, row, col, block, type}
  function clearMarks() { $$('.drop-before, .drop-after, .drop-in', form).forEach(function (e) { e.classList.remove('drop-before', 'drop-after', 'drop-in'); }); }
  form.addEventListener('dragstart', function (ev) {
    var g = ev.target.closest && ev.target.closest('[data-drag], [data-pal]');
    if (!g || g.getAttribute('data-drag') === 'link') { return; }
    if (g.hasAttribute('data-pal')) { drag = { kind: 'new', type: g.getAttribute('data-pal') }; }
    else {
      var kind = g.getAttribute('data-drag');
      var rowEl = g.closest('.apf-row'), colEl = g.closest('.apf-col'), blockEl = g.closest('.apf-block');
      drag = { kind: kind, row: rowEl && rowEl._row, col: colEl && colEl._col, block: blockEl && blockEl._block };
      var el = kind === 'row' ? rowEl : (kind === 'col' ? colEl : blockEl);
      el.classList.add('is-dragging');
      try { ev.dataTransfer.setDragImage(el, 16, 16); } catch (e) { /* ignore */ }
    }
    form.classList.add('is-dragging-' + drag.kind);
    try { ev.dataTransfer.effectAllowed = drag.kind === 'new' ? 'copy' : 'move'; ev.dataTransfer.setData('application/x-ap-block', '1'); } catch (e) { /* ignore */ }
  });
  function target(ev) {
    if (!drag) { return null; }
    if (drag.kind === 'row') {
      var r = ev.target.closest('.apf-row'); if (!r) { return null; }
      var rr = r.getBoundingClientRect(); return { el: r, where: ev.clientY < rr.top + rr.height / 2 ? 'before' : 'after' };
    }
    if (drag.kind === 'col') {
      var c = ev.target.closest('.apf-col');
      if (c) { var cr = c.getBoundingClientRect(); return { el: c, where: ev.clientX < cr.left + cr.width / 2 ? 'before' : 'after' }; }
      var cols = ev.target.closest('.apf-row'); return cols ? { el: $('[data-cols]', cols), where: 'in' } : null;
    }
    var b = ev.target.closest('.apf-block');
    if (b && b._block !== drag.block) { var br = b.getBoundingClientRect(); return { el: b, where: ev.clientY < br.top + br.height / 2 ? 'before' : 'after' }; }
    var col = ev.target.closest('.apf-col'); return col && !b ? { el: $('[data-blocks]', col), where: 'in' } : null;
  }
  form.addEventListener('dragover', function (ev) {
    var t = target(ev); if (!t) { return; }
    ev.preventDefault(); clearMarks(); t.el.classList.add('drop-' + t.where);
    try { ev.dataTransfer.dropEffect = drag.kind === 'new' ? 'copy' : 'move'; } catch (e) { /* ignore */ }
  });
  form.addEventListener('drop', function (ev) {
    var t = target(ev); if (!t) { return; }
    ev.preventDefault();
    var after = t.where === 'after' ? 1 : 0;
    if (drag.kind === 'row') {
      var from = L.rows.indexOf(drag.row); L.rows.splice(from, 1);
      var to = L.rows.indexOf(t.el._row); L.rows.splice(to < 0 ? L.rows.length : to + after, 0, drag.row);
    } else if (drag.kind === 'col') {
      var destRow = t.where === 'in' ? t.el.closest('.apf-row')._row : t.el._row;
      if (destRow !== drag.row && destRow.columns.length >= max.cols) { toast('warning', 'A row can have up to ' + max.cols + ' columns.'); return end(); }
      drag.row.columns.splice(drag.row.columns.indexOf(drag.col), 1);
      var at = t.where === 'in' ? destRow.columns.length : destRow.columns.indexOf(t.el._col) + after;
      destRow.columns.splice(at, 0, drag.col);
    } else {
      var destCol = t.where === 'in' ? t.el.closest('.apf-col')._col : t.el._col;
      var b = drag.kind === 'new' ? newBlock(drag.type) : drag.block;
      if ((drag.kind === 'new' || destCol !== drag.col) && destCol.blocks.length >= max.blocks) { toast('warning', 'A column can hold up to ' + max.blocks + ' blocks.'); return end(); }
      if (drag.kind !== 'new') { drag.col.blocks.splice(drag.col.blocks.indexOf(b), 1); } else { open = b; }
      var pos = t.where === 'in' ? destCol.blocks.length : destCol.blocks.indexOf(t.el._block) + after;
      destCol.blocks.splice(pos, 0, b); lastCol = destCol;
    }
    end(); render(); changed();
  });
  function end() { clearMarks(); $$('.is-dragging', form).forEach(function (e) { e.classList.remove('is-dragging'); }); form.className = form.className.replace(/\bis-dragging-\w+/g, '').trim(); drag = null; }
  form.addEventListener('dragend', end);

  /* ---------------------------------------------------------------- bottom bar and look */
  var linksBox = $('[data-bottom-links]', form);
  linksBox.appendChild(linksEditor(L.bottom.links, function () {}));
  $$('[data-b]', form).forEach(function (el) {
    el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', function () {
      L.bottom[el.getAttribute('data-b')] = el.type === 'checkbox' ? el.checked : el.value;
      if (el.getAttribute('data-b') === 'show') { $('[data-when-bottom]', form).classList.toggle('is-muted', !el.checked); }
      changed();
    });
  });
  $('[data-when-bottom]', form).classList.toggle('is-muted', !L.bottom.show);
  $$('[data-s]', form).forEach(function (el) {
    el.addEventListener(el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT' ? 'change' : 'input', function () {
      var k = el.getAttribute('data-s');
      if (el.type === 'radio' && !el.checked) { return; }
      L.style[k] = el.type === 'checkbox' ? el.checked : el.value;
      if (k === 'theme') {
        $$('.apf-theme', form).forEach(function (l) { l.classList.toggle('is-checked', $('input', l).checked); });
        $('[data-custom-colours]', form).hidden = el.value !== 'custom';
      }
      changed();
    });
  });

  /* ---------------------------------------------------------------- live preview */
  var frame = $('[data-preview]', form), wait = $('[data-preview-wait]', form), timer = null, seq = 0;
  var token = ($('meta[name=csrf-token]') || {}).content || ($('input[name=_token]', form) || {}).value || '';
  function preview() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var mine = ++seq; wait.hidden = false;
      var fd = new FormData(); fd.append('_token', token); fd.append('layout', JSON.stringify(L));
      fetch(form.getAttribute('data-preview-url'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
        .then(function (html) { if (mine === seq) { frame.srcdoc = html; } })
        .catch(function () { /* keep the last preview */ })
        .then(function () { if (mine === seq) { wait.hidden = true; } });
    }, 350);
  }
  var wrapEl = $('[data-preview-wrap]', form);
  function fit() {
    // "Computer" shows the footer as on a 1280 pixel wide screen, scaled down to fit the box.
    var phone = wrapEl.classList.contains('is-phone');
    var avail = wrapEl.clientWidth - 24, w = phone ? Math.min(390, avail) : 1280, scale = phone ? 1 : Math.min(1, avail / w);
    var h = 260;
    try { h = Math.min(1400, Math.max(200, frame.contentDocument.documentElement.scrollHeight)); } catch (e) { /* ignore */ }
    frame.style.width = w + 'px'; frame.style.height = h + 'px';
    frame.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
    wrapEl.style.height = (h * scale + 24) + 'px';
  }
  frame.addEventListener('load', function () { fit(); setTimeout(fit, 300); });
  window.addEventListener('resize', fit);
  $$('[data-preview-size]', form).forEach(function (b) {
    b.addEventListener('click', function () {
      $$('[data-preview-size]', form).forEach(function (x) { x.classList.toggle('active', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      wrapEl.classList.toggle('is-phone', b.getAttribute('data-preview-size') === 'phone');
      fit(); setTimeout(fit, 120);
    });
  });

  form.addEventListener('submit', function () { $('[data-footer-layout]', form).value = JSON.stringify(L); });

  render();
  preview();
})();
