/* Media library page and the file picker used everywhere in the admin. Plain JavaScript.
   Picker:  window.apMediaPicker.open({ kind: 'image', title: 'Choose an image', onSelect: function (item) { ... } })
   Buttons: <button data-media-pick="#inputId" data-media-kind="image"> fills the input with the chosen file's address. */
(function () {
  'use strict';
  var cfg = document.getElementById('apMediaPicker');
  if (!cfg) { return; }
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  var token = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var toast = function (t, m) { if (window.apToast) { window.apToast(t, m); } };
  var listUrl = cfg.getAttribute('data-list-url');
  var uploadUrl = cfg.getAttribute('data-upload-url');
  var base = listUrl.replace(/\/list$/, '');
  var maxBytes = parseInt(cfg.getAttribute('data-max'), 10) || 0;
  var allowed = (cfg.getAttribute('data-allowed') || '').split(',').filter(Boolean);
  var canUpload = cfg.getAttribute('data-can-upload') === '1';
  var IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico'];

  function bytes(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  function getJSON(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }); }
  function post(url, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) { Object.keys(data || {}).forEach(function (k) { var v = data[k]; if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else { fd.append(k, v == null ? '' : v); } }); }
    fd.append('_token', token());
    return fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().then(function (d) { d._status = r.status; return d; }); });
  }
  function qs(o) { return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }

  /* ---------- uploading: one request per file, three at a time, each with its own progress bar ---------- */
  function upload(files, opts) {
    var queue = Array.prototype.slice.call(files);
    var list = opts.list;
    var running = 0, done = 0, total = queue.length, okCount = 0;
    function row(file) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="ap-up-name"></span><span class="ap-up-state"></span><div class="progress"><div class="progress-bar" style="width:0%"></div></div>';
      $('.ap-up-name', li).textContent = file.name;
      if (list) { list.appendChild(li); }
      return li;
    }
    function finish(li, ok, text) {
      li.classList.add(ok ? 'is-ok' : 'is-bad');
      $('.ap-up-state', li).innerHTML = ok ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i> ' + esc(text);
      $('.progress-bar', li).style.width = '100%';
      if (ok) { setTimeout(function () { li.classList.add('is-gone'); setTimeout(function () { li.remove(); }, 400); }, 1800); }
      done++; running--;
      if (done === total && opts.onAll) { opts.onAll(okCount); }
      next();
    }
    function next() {
      while (running < 3 && queue.length) {
        var file = queue.shift();
        var li = row(file);
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        var only = opts.only === 'image' ? IMAGES : null;
        if (allowed.length && allowed.indexOf(ext) === -1) { running++; finish(li, false, '.' + ext + ' files are not allowed'); continue; }
        if (only && only.indexOf(ext) === -1) { running++; finish(li, false, 'Only images can be used here'); continue; }
        if (maxBytes && file.size > maxBytes) { running++; finish(li, false, 'Too big (' + bytes(file.size) + ', the limit is ' + bytes(maxBytes) + ')'); continue; }
        running++;
        (function (file, li) {
          var fd = new FormData();
          fd.append('file', file); fd.append('_token', token());
          if (opts.folder && opts.folder !== 'all') { fd.append('folder', opts.folder); }
          if (opts.only) { fd.append('only', opts.only); }
          var xhr = new XMLHttpRequest();
          xhr.open('POST', uploadUrl);
          xhr.setRequestHeader('Accept', 'application/json');
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
          xhr.upload.onprogress = function (e) { if (e.lengthComputable) { $('.progress-bar', li).style.width = Math.round(e.loaded / e.total * 95) + '%'; } };
          xhr.onload = function () {
            var d = null; try { d = JSON.parse(xhr.responseText); } catch (e) { d = null; }
            if (d && d.ok) { okCount++; finish(li, true); if (opts.onItem) { opts.onItem(d.item); } }
            else { finish(li, false, (d && d.error) || (xhr.status === 413 ? 'Too big for the server' : 'Upload failed (' + xhr.status + ')')); }
          };
          xhr.onerror = function () { finish(li, false, 'Connection lost'); };
          xhr.send(fd);
        })(file, li);
      }
    }
    next();
  }

  /* ---------- tiles ---------- */
  function tile(item, opts) {
    var el = document.createElement('div');
    el.className = 'ap-mt';
    el.setAttribute('data-id', item.id);
    el.tabIndex = 0;
    el.setAttribute('role', 'button');
    el.setAttribute('aria-label', (item.title || item.name) + ', ' + item.size_text);
    var pic = item.thumb ? '<img src="' + esc(item.thumb) + '" alt="" loading="lazy">' : '<i class="fa-solid ' + esc(item.icon) + '"></i><b>' + esc(item.ext) + '</b>';
    el.innerHTML = '<div class="ap-mt-pic">' + pic + '</div>'
      + '<div class="ap-mt-meta"><span class="ap-mt-title"></span><small>' + esc(item.size_text) + (item.width ? ' · ' + item.width + '×' + item.height : '') + '</small></div>'
      + (opts.select ? '<label class="ap-mt-check" title="Select"><input type="checkbox" class="form-check-input" aria-label="Select"></label>' : '')
      + (item.kind === 'image' && !item.alt && opts.warnAlt ? '<span class="ap-mt-flag" title="No description for screen readers yet">ALT</span>' : '');
    $('.ap-mt-title', el).textContent = item.title || item.name;
    if (opts.drag) { el.draggable = true; }
    el._item = item;
    return el;
  }

  /* =====================================================================
     The picker (a modal on any admin page)
     ===================================================================== */
  var picker = null;
  function buildPicker() {
    var m = document.createElement('div');
    m.className = 'modal fade ap-picker'; m.tabIndex = -1; m.setAttribute('aria-hidden', 'true');
    m.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">'
      + '<div class="modal-header"><h2 class="modal-title h5" data-p-title>Choose a file</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
      + '<div class="modal-body">'
      + (canUpload ? '<label class="ap-drop py-2 mb-2" data-p-drop><input type="file" multiple class="visually-hidden" data-p-input><i class="fa-solid fa-cloud-arrow-up ap-drop-icon"></i><span class="ap-drop-text"><strong>Drop files here or click to upload</strong><br><span class="small text-muted" data-p-limits></span></span></label><ul class="ap-upload-list" data-p-uploads></ul>' : '')
      + '<div class="ap-toolbar mb-2"><input type="search" class="form-control form-control-sm w-auto flex-grow-1" placeholder="Search" aria-label="Search files" data-p-q><select class="form-select form-select-sm w-auto" aria-label="Folder" data-p-folder></select></div>'
      + '<div class="ap-media-grid is-picker" data-p-grid></div><div class="text-center my-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-p-more hidden>Show more</button></div>'
      + '<p class="text-muted text-center my-4" data-p-empty hidden>No files yet. Upload one above.</p></div>'
      + '<div class="modal-footer"><span class="me-auto small text-muted text-truncate" data-p-chosen>Click a file to choose it.</span><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-p-use disabled>Use This File</button></div>'
      + '</div></div>';
    document.body.appendChild(m);
    var st = { page: 1, q: '', folder: 'all', kind: '', chosen: null, onSelect: null };
    var grid = $('[data-p-grid]', m), more = $('[data-p-more]', m), use = $('[data-p-use]', m);
    var modal = new bootstrap.Modal(m);
    function load(reset) {
      if (reset) { st.page = 1; grid.innerHTML = ''; }
      getJSON(listUrl + '?' + qs({ q: st.q, folder: st.folder, kind: st.kind, page: st.page, per_page: 48 })).then(function (d) {
        d.items.forEach(function (it) { grid.appendChild(tile(it, {})); });
        more.hidden = d.page >= d.last_page;
        $('[data-p-empty]', m).hidden = grid.children.length > 0;
        var sel = $('[data-p-folder]', m);
        if (reset && sel.options.length === 0) {
          sel.innerHTML = '<option value="all">All folders</option><option value="0">Not in a folder</option>' + d.folders.map(function (f) { return '<option value="' + f.id + '">' + '— '.repeat(f.depth) + esc(f.name) + '</option>'; }).join('');
        }
      });
    }
    function choose(el) {
      $$('.ap-mt.is-chosen', grid).forEach(function (x) { x.classList.remove('is-chosen'); });
      el.classList.add('is-chosen');
      st.chosen = el._item;
      use.disabled = false;
      $('[data-p-chosen]', m).textContent = (el._item.title || el._item.name) + ' · ' + el._item.size_text;
    }
    grid.addEventListener('click', function (ev) { var t = ev.target.closest('.ap-mt'); if (t) { choose(t); } });
    grid.addEventListener('dblclick', function (ev) { var t = ev.target.closest('.ap-mt'); if (t) { choose(t); use.click(); } });
    grid.addEventListener('keydown', function (ev) { var t = ev.target.closest('.ap-mt'); if (t && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); choose(t); if (ev.key === 'Enter') { use.click(); } } });
    use.addEventListener('click', function () { if (st.chosen && st.onSelect) { var it = st.chosen; modal.hide(); st.onSelect(it); } });
    more.addEventListener('click', function () { st.page++; load(false); });
    var t = null;
    $('[data-p-q]', m).addEventListener('input', function (ev) { clearTimeout(t); t = setTimeout(function () { st.q = ev.target.value.trim(); load(true); }, 300); });
    $('[data-p-folder]', m).addEventListener('change', function (ev) { st.folder = ev.target.value; load(true); });
    var input = $('[data-p-input]', m), drop = $('[data-p-drop]', m);
    function send(files) {
      upload(files, { list: $('[data-p-uploads]', m), folder: st.folder, only: st.kind === 'image' ? 'image' : '', onItem: function (it) {
        var el = tile(it, {}); grid.insertBefore(el, grid.firstChild); $('[data-p-empty]', m).hidden = true; choose(el);
      } });
    }
    if (input) {
      input.addEventListener('change', function () { send(input.files); input.value = ''; });
      ['dragenter', 'dragover'].forEach(function (n) { drop.addEventListener(n, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
      ['dragleave', 'drop'].forEach(function (n) { drop.addEventListener(n, function () { drop.classList.remove('is-over'); }); });
      drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { send(e.dataTransfer.files); } });
    }
    return {
      open: function (o) {
        o = o || {};
        st.kind = o.kind || ''; st.onSelect = o.onSelect || null; st.chosen = null; st.q = ''; use.disabled = true;
        $('[data-p-q]', m).value = '';
        $('[data-p-title]', m).textContent = o.title || (st.kind === 'image' ? 'Choose an Image' : 'Choose a File');
        $('[data-p-chosen]', m).textContent = 'Click a file to choose it.';
        var lim = $('[data-p-limits]', m);
        if (lim) { lim.textContent = 'Up to ' + bytes(maxBytes) + ' each · ' + (st.kind === 'image' ? allowed.filter(function (e) { return IMAGES.indexOf(e) !== -1; }) : allowed).join(', '); }
        load(true);
        modal.show();
      }
    };
  }
  window.apMediaPicker = { open: function (o) { if (!window.bootstrap) { return; } picker = picker || buildPicker(); picker.open(o); } };
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-media-pick]');
    if (!b) { return; }
    ev.preventDefault();
    var input = document.querySelector(b.getAttribute('data-media-pick'));
    window.apMediaPicker.open({ kind: b.getAttribute('data-media-kind') || '', onSelect: function (it) {
      if (input) { input.value = it.url; input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); }
    } });
  });

  /* =====================================================================
     The media library page
     ===================================================================== */
  var app = document.querySelector('[data-media-app]');
  if (!app) { return; }
  var can = { edit: app.getAttribute('data-can-edit') === '1', del: app.getAttribute('data-can-delete') === '1', up: app.getAttribute('data-can-upload') === '1' };
  var grid = $('[data-media-grid]', app), more = $('[data-media-more]', app), emptyBox = $('[data-media-empty]', app);
  var bulk = $('[data-media-bulk]', app), folderList = $('[data-folder-list]', app);
  var st = { folder: 'all', q: '', kind: '', sort: 'newest', page: 1, view: 'grid', selected: {}, folders: [] };
  try { st.view = localStorage.getItem('ap.media.view') || 'grid'; } catch (e) { /* ignore */ }

  function load(reset) {
    if (reset) { st.page = 1; grid.innerHTML = ''; }
    getJSON(listUrl + '?' + qs({ q: st.q, kind: st.kind, folder: st.folder, sort: st.sort, page: st.page })).then(function (d) {
      d.items.forEach(function (it) { var el = tile(it, { select: can.edit || can.del, drag: can.edit, warnAlt: true }); if (st.selected[it.id]) { el.classList.add('is-selected'); $('input', el).checked = true; } grid.appendChild(el); });
      more.hidden = d.page >= d.last_page;
      emptyBox.hidden = grid.children.length > 0;
      folders(d.folders, d.counts);
    });
  }
  function folders(list, counts) {
    st.folders = list;
    $('[data-count-all]', app).textContent = counts.all;
    $('[data-count-unsorted]', app).textContent = counts.unsorted;
    $$('[data-folder-item]', folderList).forEach(function (li) { li.remove(); });
    list.forEach(function (f) {
      var li = document.createElement('li');
      li.setAttribute('data-folder-item', f.id);
      li.innerHTML = '<button type="button" class="ap-mf" data-folder="' + f.id + '" data-drop-folder="' + f.id + '" style="padding-left:' + (0.6 + f.depth * 0.9) + 'rem"><i class="fa-solid fa-folder"></i><span></span><em>' + f.count + '</em></button>'
        + (can.edit ? '<div class="dropdown ap-mf-menu"><button type="button" class="btn btn-sm" data-bs-toggle="dropdown" aria-label="Folder options"><i class="fa-solid fa-ellipsis-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-folder-rename="' + f.id + '"><i class="fa-solid fa-pen me-2"></i>Rename</button></li><li><button class="dropdown-item" type="button" data-folder-sub="' + f.id + '"><i class="fa-solid fa-folder-plus me-2"></i>New Folder Inside</button></li><li><button class="dropdown-item text-danger" type="button" data-folder-delete="' + f.id + '"><i class="fa-solid fa-trash-can me-2"></i>Remove Folder</button></li></ul></div>' : '');
      $('span', li).textContent = f.name;
      folderList.appendChild(li);
    });
    $$('[data-folder]', folderList).forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-folder') === String(st.folder)); });
    var sel = $('[data-bulk-folder]', app);
    if (sel) { sel.innerHTML = '<option value="">Move to…</option><option value="0">Not in a folder</option>' + list.map(function (f) { return '<option value="' + f.id + '">' + '— '.repeat(f.depth) + esc(f.name) + '</option>'; }).join(''); }
  }
  function setView(v) {
    st.view = v; grid.classList.toggle('is-list', v === 'list');
    $$('[data-media-view]', app).forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-media-view') === v); });
    try { localStorage.setItem('ap.media.view', v); } catch (e) { /* ignore */ }
  }
  setView(st.view);
  $$('[data-media-view]', app).forEach(function (b) { b.addEventListener('click', function () { setView(b.getAttribute('data-media-view')); }); });
  var qt = null;
  $('[data-media-q]', app).addEventListener('input', function (ev) { clearTimeout(qt); qt = setTimeout(function () { st.q = ev.target.value.trim(); load(true); }, 300); });
  $('[data-media-kind]', app).addEventListener('change', function (ev) { st.kind = ev.target.value; load(true); });
  $('[data-media-sort]', app).addEventListener('change', function (ev) { st.sort = ev.target.value; load(true); });
  more.addEventListener('click', function () { st.page++; load(false); });
  folderList.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-folder]');
    if (b) { st.folder = b.getAttribute('data-folder'); load(true); return; }
    var r = ev.target.closest('[data-folder-rename]'), s = ev.target.closest('[data-folder-sub]'), d = ev.target.closest('[data-folder-delete]');
    if (r) { var id = r.getAttribute('data-folder-rename'); var f = st.folders.filter(function (x) { return String(x.id) === id; })[0];
      askName('Rename Folder', f ? f.name : '', function (name) { post(base + '/folders/' + id, { name: name }).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); load(true); }); }); }
    if (s) { newFolder(s.getAttribute('data-folder-sub')); }
    if (d) { var did = d.getAttribute('data-folder-delete'); var go = function () { post(base + '/folders/' + did + '/delete', {}).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); if (String(st.folder) === did) { st.folder = 'all'; } load(true); }); };
      if (window.apAsk) { window.apAsk('Remove this folder? Its files and folders move up one level; nothing is deleted.', go, 'Remove folder'); } else { go(); } }
  });
  var fm = document.getElementById('folderModal'), fmModal = fm && window.bootstrap ? new bootstrap.Modal(fm) : null, fmDone = null;
  function askName(title, value, done) {
    if (!fmModal) { var n = window.prompt(title, value); if (n && n.trim()) { done(n.trim()); } return; }
    $('[data-folder-title]', fm).textContent = title; $('#folderName', fm).value = value || ''; fmDone = done; fmModal.show();
    setTimeout(function () { $('#folderName', fm).focus(); $('#folderName', fm).select(); }, 300);
  }
  if (fm) { $('[data-folder-form]', fm).addEventListener('submit', function (ev) { ev.preventDefault(); var v = $('#folderName', fm).value.trim(); if (!v) { return; } fmModal.hide(); if (fmDone) { fmDone(v); } }); }
  function newFolder(parent) {
    askName(parent ? 'New Folder Inside' : 'New Folder', '', function (name) {
      post(base + '/folders', { name: name, parent_id: parent || '' }).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); load(true); });
    });
  }
  var nf = $('[data-folder-new]', app); if (nf) { nf.addEventListener('click', function () { newFolder(st.folder !== 'all' && st.folder !== '0' ? st.folder : ''); }); }

  /* selection and bulk actions */
  function selectedIds() { return Object.keys(st.selected).filter(function (k) { return st.selected[k]; }); }
  function syncBulk() { var n = selectedIds().length; bulk.hidden = n === 0; $('[data-bulk-n]', bulk).textContent = n; }
  grid.addEventListener('change', function (ev) {
    var cb = ev.target.closest('.ap-mt-check input'); if (!cb) { return; }
    var el = cb.closest('.ap-mt'); st.selected[el._item.id] = cb.checked; el.classList.toggle('is-selected', cb.checked); syncBulk();
  });
  $('[data-bulk-all]', bulk).addEventListener('click', function () { $$('.ap-mt', grid).forEach(function (el) { st.selected[el._item.id] = true; el.classList.add('is-selected'); var c = $('input', el); if (c) { c.checked = true; } }); syncBulk(); });
  $('[data-bulk-none]', bulk).addEventListener('click', function () { st.selected = {}; $$('.ap-mt', grid).forEach(function (el) { el.classList.remove('is-selected'); var c = $('input', el); if (c) { c.checked = false; } }); syncBulk(); });
  var bf = $('[data-bulk-folder]', bulk);
  if (bf) { bf.addEventListener('change', function () { if (bf.value === '') { return; } post(base + '/bulk', { action: 'move', folder_id: bf.value, ids: selectedIds() }).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); st.selected = {}; syncBulk(); load(true); }); }); }
  // Add the selection to a gallery (existing or new), or download it as one zip file.
  var bg = $('[data-bulk-gallery]', bulk);
  if (bg) { bg.addEventListener('change', function () {
    if (bg.value === '') { return; }
    var data = { gallery: bg.value, ids: selectedIds() };
    if (bg.value === 'new') { var n = window.prompt('Name of the new gallery', ''); if (n === null) { bg.value = ''; return; } data.name = n; }
    post(base + '/galleries/add', data).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); bg.value = ''; if (x.ok) { st.selected = {}; syncBulk(); load(true); } });
  }); }
  var bz = $('[data-bulk-zip]', bulk);
  if (bz) { bz.addEventListener('click', function () {
    var f = document.createElement('form'); f.method = 'post'; f.action = base + '/zip'; f.style.display = 'none';
    var t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = token(); f.appendChild(t);
    selectedIds().forEach(function (id) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; f.appendChild(i); });
    document.body.appendChild(f); f.submit(); setTimeout(function () { f.remove(); }, 1000);
  }); }
  var bd = $('[data-bulk-delete]', bulk);
  if (bd) { bd.addEventListener('click', function () { var ids = selectedIds(); var go = function () { post(base + '/bulk', { action: 'delete', ids: ids }).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); st.selected = {}; syncBulk(); load(true); }); };
    if (window.apAsk) { window.apAsk('Delete ' + ids.length + ' file(s) for good? Pages that show them will have missing images.', go, 'Delete files'); } else { go(); } }); }

  /* drag files onto folders */
  grid.addEventListener('dragstart', function (ev) {
    var el = ev.target.closest('.ap-mt'); if (!el || !can.edit) { return; }
    var ids = st.selected[el._item.id] ? selectedIds() : [String(el._item.id)];
    ev.dataTransfer.setData('application/x-ap-media', ids.join(','));
    ev.dataTransfer.effectAllowed = 'move';
    el.classList.add('is-dragging');
  });
  grid.addEventListener('dragend', function (ev) { var el = ev.target.closest('.ap-mt'); if (el) { el.classList.remove('is-dragging'); } });
  folderList.addEventListener('dragover', function (ev) {
    var b = ev.target.closest('[data-drop-folder]');
    if (!b || Array.prototype.indexOf.call(ev.dataTransfer.types, 'application/x-ap-media') === -1) { return; }
    ev.preventDefault(); $$('.is-drop', folderList).forEach(function (x) { x.classList.remove('is-drop'); }); b.classList.add('is-drop');
  });
  folderList.addEventListener('dragleave', function (ev) { var b = ev.target.closest('[data-drop-folder]'); if (b && !b.contains(ev.relatedTarget)) { b.classList.remove('is-drop'); } });
  folderList.addEventListener('drop', function (ev) {
    var b = ev.target.closest('[data-drop-folder]'); var data = ev.dataTransfer.getData('application/x-ap-media');
    if (!b || !data) { return; }
    ev.preventDefault(); b.classList.remove('is-drop');
    post(base + '/bulk', { action: 'move', folder_id: b.getAttribute('data-drop-folder'), ids: data.split(',') }).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); st.selected = {}; syncBulk(); load(true); });
  });

  /* details panel */
  var panelEl = document.getElementById('mediaPanel');
  var panel = panelEl && window.bootstrap ? new bootstrap.Offcanvas(panelEl) : null;
  function openItem(id) {
    getJSON(base + '/' + id).then(function (d) {
      var it = d.item, body = $('[data-panel-body]', panelEl);
      var ro = can.edit ? '' : ' readonly';
      body.innerHTML = '<div class="ap-mp-preview">' + (it.kind === 'image' ? '<img src="' + esc(it.medium || it.url) + '" alt="">' : '<i class="fa-solid ' + esc(it.icon) + '"></i><b>' + esc(it.ext) + '</b>') + '</div>'
        + '<dl class="ap-up-facts small mt-3"><dt>File</dt><dd>' + esc(it.name) + '</dd><dt>Size</dt><dd>' + esc(it.size_text) + (it.width ? ' · ' + it.width + ' × ' + it.height + ' px' : '') + '</dd><dt>Added</dt><dd>' + esc(it.date) + '</dd></dl>'
        + '<label class="form-label small mb-1" for="mpUrl">Address</label><div class="input-group input-group-sm mb-3"><input class="form-control" id="mpUrl" readonly value="' + esc(it.url) + '"><button class="btn btn-outline-secondary" type="button" data-copy="' + esc(it.url) + '" title="Copy"><i class="fa-regular fa-copy"></i></button><a class="btn btn-outline-secondary" href="' + esc(it.url) + '" target="_blank" rel="noopener" title="Open"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>'
        + '<label class="form-label small mb-1" for="mpTitle">Title</label><input class="form-control form-control-sm mb-3" id="mpTitle" data-mp="title" maxlength="190"' + ro + '>'
        + (it.kind === 'image' ? '<label class="form-label small mb-1" for="mpAlt">Description for screen readers and search engines</label><textarea class="form-control form-control-sm mb-1" id="mpAlt" rows="2" data-mp="alt" maxlength="300"' + ro + ' placeholder="What the image shows, for example: Our team at the London office"></textarea><div class="form-text mb-3">Used as the "alt" text when the image is put on a page.</div>' : '')
        + '<label class="form-label small mb-1" for="mpCaption">Caption</label><input class="form-control form-control-sm mb-3" id="mpCaption" data-mp="caption" maxlength="500"' + ro + '>'
        + '<div class="small mb-3" data-mp-usage></div>'
        + (can.del ? '<button type="button" class="btn btn-sm btn-outline-danger w-100" data-mp-delete><i class="fa-solid fa-trash-can me-1"></i>Delete This File</button>' : '');
      $('[data-mp=title]', body).value = it.title;
      if ($('[data-mp=alt]', body)) { $('[data-mp=alt]', body).value = it.alt; }
      $('[data-mp=caption]', body).value = it.caption;
      $('[data-mp-usage]', body).innerHTML = d.usage.length ? '<div class="fw-semibold mb-1"><i class="fa-solid fa-link me-1"></i>Used in</div>' + d.usage.map(function (u) { return '<a class="d-block" href="' + esc(u.url) + '">' + esc(u.title) + '</a>'; }).join('') : '<span class="text-muted"><i class="fa-solid fa-link-slash me-1"></i>Not used in any page.</span>';
      if (can.edit) {
        $$('[data-mp]', body).forEach(function (f) {
          f.addEventListener('change', function () {
            var data = {}; data[f.getAttribute('data-mp')] = f.value;
            post(base + '/' + it.id, data).then(function (x) { toast(x.ok ? 'success' : 'danger', x.ok ? 'Saved' : (x.message || 'Could not save')); if (x.ok) { refreshTile(x.item); } });
          });
        });
      }
      var del = $('[data-mp-delete]', body);
      if (del) { del.addEventListener('click', function () { var go = function () { post(base + '/' + it.id + '/delete', {}).then(function (x) { toast(x.ok ? 'success' : 'danger', x.message); if (panel) { panel.hide(); } load(true); }); };
        if (window.apAsk) { window.apAsk(d.usage.length ? 'This file is used in ' + d.usage.length + ' page(s). Delete it anyway?' : 'Delete this file for good?', go, 'Delete file'); } else { go(); } }); }
      if (panel) { panel.show(); }
    });
  }
  function refreshTile(item) { var el = $('.ap-mt[data-id="' + item.id + '"]', grid); if (el) { var n = tile(item, { select: can.edit || can.del, drag: can.edit, warnAlt: true }); el.replaceWith(n); } }
  grid.addEventListener('click', function (ev) { if (ev.target.closest('.ap-mt-check')) { return; } var t = ev.target.closest('.ap-mt'); if (t) { openItem(t._item.id); } });
  grid.addEventListener('keydown', function (ev) { var t = ev.target.closest('.ap-mt'); if (t && ev.key === 'Enter') { openItem(t._item.id); } });

  /* uploading on the page: the drop box, the "Upload Files" button, or dropping anywhere */
  var input = $('[data-media-input]', app), list = $('[data-upload-list]', app), overlay = $('[data-drop-overlay]');
  function send(files) { upload(files, { list: list, folder: st.folder, onAll: function (n) { if (n) { toast('success', n + ' file(s) uploaded'); load(true); } } }); }
  if (input && can.up) {
    input.addEventListener('change', function () { send(input.files); input.value = ''; });
    $$('[data-media-choose]').forEach(function (b) { b.addEventListener('click', function () { input.click(); }); });
    var depth = 0;
    var isFiles = function (ev) { return ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') !== -1; };
    window.addEventListener('dragenter', function (ev) { if (isFiles(ev)) { depth++; overlay.hidden = false; } });
    window.addEventListener('dragleave', function (ev) { if (isFiles(ev)) { depth = Math.max(0, depth - 1); if (!depth) { overlay.hidden = true; } } });
    window.addEventListener('dragover', function (ev) { if (isFiles(ev)) { ev.preventDefault(); } });
    window.addEventListener('drop', function (ev) { if (!isFiles(ev)) { return; } ev.preventDefault(); depth = 0; overlay.hidden = true; if (ev.dataTransfer.files.length) { send(ev.dataTransfer.files); } });
  }
  load(true);
  var open = parseInt(app.getAttribute('data-open'), 10);
  if (open) { openItem(open); }
})();
