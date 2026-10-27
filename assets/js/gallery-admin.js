/* Media > Galleries: the list (tick several, act on all) and the editor (drag to order, pick, upload, captions). */
(function () {
  'use strict';
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return [].slice.call((r || document).querySelectorAll(s)); }
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  function toast(t, m) { if (window.apToast) { window.apToast(t, m); } }
  function post(url, data) {
    var fd = new FormData(); fd.append('_token', csrf);
    Object.keys(data).forEach(function (k) { [].concat(data[k]).forEach(function (v) { fd.append(Array.isArray(data[k]) ? k + '[]' : k, v); }); });
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  // ---- the list ----
  var list = $('[data-gal-list]');
  if (list) {
    var all = $('[data-gal-all]', list), ones = $$('[data-gal-one]', list), acts = $$('[data-gal-act]', list), nTxt = $('[data-gal-n]', list);
    function sync() {
      var n = ones.filter(function (c) { return c.checked; }).length;
      if (nTxt) { nTxt.textContent = n ? n + ' selected' : 'Nothing selected'; }
      acts.forEach(function (b) { b.disabled = n === 0; });
      if (all) { all.checked = n > 0 && n === ones.length; all.indeterminate = n > 0 && n < ones.length; }
      ones.forEach(function (c) { var card = c.closest('.ap-gal-card'); if (card) { card.classList.toggle('is-selected', c.checked); } });
    }
    if (all) { all.addEventListener('change', function () { ones.forEach(function (c) { c.checked = all.checked; }); sync(); }); }
    ones.forEach(function (c) { c.addEventListener('change', sync); });
    sync();
  }

  // ---- the editor ----
  var form = $('[data-gal-form]');
  if (!form) { return; }
  var base = form.getAttribute('data-base');
  var ul = $('[data-gal-items]', form), json = $('[data-gal-json]', form), cover = $('[data-gal-cover]', form), emptyP = $('[data-gal-empty]', form), cnt = $('[data-gal-count]', form), rm = $('[data-gal-remove-sel]', form);
  var items = [];
  try { items = JSON.parse(document.getElementById('galItems').textContent) || []; } catch (e) { items = []; }

  function li(it) {
    var el = document.createElement('li');
    el.className = 'ap-gal-item'; el.setAttribute('data-id', it.id); el.draggable = false;
    var grip = document.createElement('span'); grip.className = 'ap-gal-grip'; grip.setAttribute('data-gal-grip', ''); grip.tabIndex = 0; grip.setAttribute('role', 'button'); grip.setAttribute('aria-label', 'Move ' + it.name); grip.title = 'Drag to change the order'; grip.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
    var tick = document.createElement('input'); tick.type = 'checkbox'; tick.className = 'form-check-input'; tick.setAttribute('data-gal-tick', ''); tick.setAttribute('aria-label', 'Select ' + it.name);
    var img = document.createElement('img'); img.src = it.thumb; img.alt = ''; img.loading = 'lazy';
    var cap = document.createElement('input'); cap.type = 'text'; cap.className = 'form-control form-control-sm'; cap.maxLength = 300; cap.placeholder = 'Caption (optional)'; cap.value = it.caption || ''; cap.setAttribute('data-gal-cap', ''); cap.setAttribute('aria-label', 'Caption for ' + it.name);
    var star = document.createElement('button'); star.type = 'button'; star.className = 'mn-icon'; star.setAttribute('data-gal-star', ''); star.title = 'Use as the cover'; star.setAttribute('aria-label', 'Use ' + it.name + ' as the cover'); star.innerHTML = '<i class="fa-regular fa-star"></i>';
    var del = document.createElement('button'); del.type = 'button'; del.className = 'mn-icon'; del.setAttribute('data-gal-del', ''); del.title = 'Remove from this gallery'; del.setAttribute('aria-label', 'Remove ' + it.name); del.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    el.appendChild(grip); el.appendChild(tick); el.appendChild(img); el.appendChild(cap); el.appendChild(star); el.appendChild(del);
    return el;
  }
  function has(id) { return $$('.ap-gal-item', ul).some(function (e) { return e.getAttribute('data-id') === String(id); }); }
  function add(it) { if (!has(it.id)) { ul.appendChild(li(it)); } refresh(); }
  function refresh() {
    var rows = $$('.ap-gal-item', ul);
    cnt.textContent = rows.length ? '(' + rows.length + ')' : '';
    emptyP.hidden = rows.length > 0;
    rows.forEach(function (r) {
      var on = cover.value === r.getAttribute('data-id');
      r.classList.toggle('is-cover', on);
      var s = $('[data-gal-star] i', r); if (s) { s.className = on ? 'fa-solid fa-star' : 'fa-regular fa-star'; }
    });
    rm.disabled = !$$('[data-gal-tick]:checked', ul).length;
  }
  items.forEach(function (it) { ul.appendChild(li(it)); });
  refresh();

  form.addEventListener('submit', function () {
    json.value = JSON.stringify($$('.ap-gal-item', ul).map(function (r) { return { id: Number(r.getAttribute('data-id')), caption: $('[data-gal-cap]', r).value }; }));
  });
  ul.addEventListener('click', function (e) {
    var d = e.target.closest('[data-gal-del]'), s = e.target.closest('[data-gal-star]');
    if (d) { var r = d.closest('.ap-gal-item'); if (cover.value === r.getAttribute('data-id')) { cover.value = '0'; } r.remove(); refresh(); }
    if (s) { var id = s.closest('.ap-gal-item').getAttribute('data-id'); cover.value = cover.value === id ? '0' : id; refresh(); }
  });
  ul.addEventListener('change', function (e) { if (e.target.matches('[data-gal-tick]')) { refresh(); } });
  rm.addEventListener('click', function () { $$('[data-gal-tick]:checked', ul).forEach(function (t) { var r = t.closest('.ap-gal-item'); if (cover.value === r.getAttribute('data-id')) { cover.value = '0'; } r.remove(); }); refresh(); });

  // drag to order
  var drag = null;
  ul.addEventListener('mousedown', function (e) { var g = e.target.closest('[data-gal-grip]'); if (g) { g.closest('.ap-gal-item').draggable = true; } });
  ul.addEventListener('dragstart', function (e) { var el = e.target.closest ? e.target.closest('.ap-gal-item') : null; if (!el || !el.draggable) { return; } drag = el; el.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'g'); } catch (x) { /* ok */ } });
  ul.addEventListener('dragover', function (e) {
    if (!drag) { return; }
    e.preventDefault();
    var over = e.target.closest('.ap-gal-item');
    if (over && over !== drag) { var r = over.getBoundingClientRect(); ul.insertBefore(drag, e.clientY < r.top + r.height / 2 ? over : over.nextSibling); }
  });
  ul.addEventListener('dragend', function () { if (drag) { drag.classList.remove('is-dragging'); drag.draggable = false; } drag = null; form.dispatchEvent(new Event('input', { bubbles: true })); });
  ul.addEventListener('keydown', function (e) {
    var g = e.target.closest ? e.target.closest('[data-gal-grip]') : null;
    if (!g || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) { return; }
    e.preventDefault();
    var el = g.closest('.ap-gal-item'), sib = e.key === 'ArrowUp' ? el.previousElementSibling : el.nextElementSibling;
    if (sib) { ul.insertBefore(el, e.key === 'ArrowUp' ? sib : sib.nextSibling); g.focus(); }
  });

  // upload (button or drop) straight into the gallery
  function upload(files) {
    [].slice.call(files).filter(function (f) { return /^image\//.test(f.type); }).forEach(function (f) {
      var fd = new FormData(); fd.append('_token', csrf); fd.append('file', f); fd.append('only', 'image');
      fetch(base + '/upload', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (x) { if (x.ok) { add({ id: x.item.id, thumb: x.item.thumb || x.item.url, name: x.item.name, caption: '' }); } else { toast('danger', (x.name ? x.name + ': ' : '') + (x.error || 'Upload failed')); } })
        .catch(function () { toast('danger', 'Upload failed'); });
    });
  }
  var up = $('[data-gal-upload]', form); if (up) { up.addEventListener('change', function () { upload(up.files); up.value = ''; }); }
  var drop = $('[data-gal-drop]', form);
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (n) { drop.addEventListener(n, function (e) { if (e.dataTransfer && [].indexOf.call(e.dataTransfer.types || [], 'Files') > -1) { e.preventDefault(); drop.classList.add('is-over'); } }); });
    ['dragleave', 'drop'].forEach(function (n) { drop.addEventListener(n, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); upload(e.dataTransfer.files); } });
  }

  // library picker
  var picker = $('[data-gal-picker]'), grid = $('[data-gal-pgrid]'), pn = $('[data-gal-pn]'), pickBtn = $('[data-gal-pick]'), more = $('[data-gal-more]'), pq = $('[data-gal-pq]');
  var chosen = {}, page = 1, last = 1, term = '', t;
  function nChosen() { return Object.keys(chosen).length; }
  function pSync() { var n = nChosen(); pn.textContent = n + ' chosen'; pickBtn.disabled = n === 0; }
  function load(reset) {
    if (reset) { page = 1; grid.innerHTML = ''; }
    fetch(base + '/list?kind=image&per_page=60&page=' + page + '&q=' + encodeURIComponent(term), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (x) {
        last = x.last_page;
        x.items.forEach(function (m) {
          var b = document.createElement('button'); b.type = 'button'; b.className = 'ap-gal-pick'; b.setAttribute('aria-pressed', chosen[m.id] ? 'true' : 'false'); b.title = m.name;
          var img = document.createElement('img'); img.src = m.thumb || m.url; img.alt = m.name; img.loading = 'lazy'; b.appendChild(img);
          if (has(m.id)) { b.classList.add('is-in'); b.disabled = true; b.title = m.name + ' (already here)'; }
          b.addEventListener('click', function () { if (chosen[m.id]) { delete chosen[m.id]; b.setAttribute('aria-pressed', 'false'); } else { chosen[m.id] = m; b.setAttribute('aria-pressed', 'true'); } pSync(); });
          grid.appendChild(b);
        });
        more.hidden = page >= last;
        if (!x.items.length && page === 1) { grid.innerHTML = '<p class="text-muted">No pictures found.</p>'; }
      });
  }
  function openPicker() { chosen = {}; term = ''; pq.value = ''; pSync(); picker.hidden = false; document.body.style.overflow = 'hidden'; load(true); }
  function closePicker() { picker.hidden = true; document.body.style.overflow = ''; }
  $('[data-gal-open-picker]', form).addEventListener('click', openPicker);
  $$('[data-gal-picker-close]', picker).forEach(function (b) { b.addEventListener('click', closePicker); });
  picker.addEventListener('mousedown', function (e) { if (e.target === picker) { closePicker(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !picker.hidden) { closePicker(); } });
  more.addEventListener('click', function () { page++; load(false); });
  pq.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { term = pq.value.trim(); load(true); }, 250); });
  pickBtn.addEventListener('click', function () { Object.keys(chosen).forEach(function (id) { var m = chosen[id]; add({ id: m.id, thumb: m.thumb || m.url, name: m.name, caption: '' }); }); closePicker(); });
})();
