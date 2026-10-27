/* Page editor helpers: live address, character counters, search result preview, status, revisions. Plain JavaScript. */
(function () {
  'use strict';
  var form = document.querySelector('[data-page-form]');
  if (!form) { return; }
  var $ = function (s, c) { return (c || form).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || form).querySelectorAll(s)); };
  var title = $('[data-page-title]');
  var slug = $('[data-page-slug]');
  var parent = $('[data-page-parent]');
  var note = $('[data-slug-note]');
  var parentPrefix = $('[data-slug-parent]');
  var base = form.getAttribute('data-base-url') || '/';
  var fullPath = '';
  var touched = slug && slug.hasAttribute('data-slug-locked');

  /* ---- address: made from the title until the person edits it ---- */
  function local(text) {
    var map = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'ä': 'a', 'ß': 'ss', 'é': 'e', 'è': 'e', 'ê': 'e', 'á': 'a', 'à': 'a', 'â': 'a', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ñ': 'n', '&': ' and ' };
    return text.toLowerCase().trim().replace(/[çğıöşüäßéèêáàâíóúñ&]/g, function (c) { return map[c]; })
      .replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '').slice(0, 120);
  }
  var timer = null;
  function check() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var params = new URLSearchParams({ title: title.value, slug: slug.value, parent: parent ? parent.value : '', id: form.getAttribute('data-page-id') || '0' });
      fetch(form.getAttribute('data-slug-url') + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          fullPath = d.path || '';
          var cut = fullPath.lastIndexOf('/');
          if (parentPrefix) { parentPrefix.textContent = cut > 0 ? fullPath.slice(0, cut + 1) : ''; }
          if (note) {
            note.className = 'small ' + (d.changed ? 'text-warning' : 'text-success');
            note.innerHTML = d.slug === '' ? '' : (d.changed ? '<i class="fa-solid fa-triangle-exclamation me-1"></i>Taken, will be saved as <strong></strong>' : '<i class="fa-solid fa-check me-1"></i>Free');
            var s = note.querySelector('strong'); if (s) { s.textContent = d.slug; }
          }
          serp();
        })
        .catch(function () { /* saving still works */ });
    }, 350);
  }
  if (title && slug) {
    title.addEventListener('input', function () { if (!touched) { slug.value = local(title.value); } check(); serp(); });
    slug.addEventListener('input', function () { touched = slug.value.trim() !== ''; check(); });
    slug.addEventListener('blur', function () { if (slug.value) { slug.value = local(slug.value); } });
    if (parent) { parent.addEventListener('change', check); }
    check();
  }
  var copy = $('[data-copy-url]');
  if (copy) {
    copy.addEventListener('click', function () {
      var u = base + (fullPath || slug.value);
      if (navigator.clipboard) { navigator.clipboard.writeText(u).then(function () { window.apToast && window.apToast('success', 'Address copied'); }); }
    });
  }

  /* ---- character counters: data-count="60" (data-count-soft = advice, not a limit) ---- */
  $$('[data-count]').forEach(function (el) {
    var out = form.querySelector('[data-count-for="' + el.id + '"]');
    var max = parseInt(el.getAttribute('data-count'), 10);
    function upd() {
      var n = el.value.length;
      if (!out) { return; }
      out.textContent = n + ' / ' + max;
      out.className = n > max ? 'text-danger fw-semibold' : (n > max * 0.9 ? 'text-warning' : 'text-muted');
    }
    el.addEventListener('input', function () { upd(); serp(); });
    upd();
  });

  /* ---- Google-style preview ---- */
  var seoTitle = $('#pg_seo_title');
  var seoDesc = $('#pg_seo_description');
  var excerpt = $('#pg_excerpt');
  var source = $('[data-ed-source]');
  var area = $('[data-ed-area]');
  function clip(s, n) { s = s.replace(/\s+/g, ' ').trim(); return s.length > n ? s.slice(0, n - 1).replace(/\s+\S*$/, '') + '…' : s; }
  function serp() {
    var t = $('[data-serp-title]');
    if (!t) { return; }
    var fmt = form.getAttribute('data-title-format') || '{title}';
    var name = form.getAttribute('data-site-name') || '';
    var tt = (seoTitle && seoTitle.value.trim()) || fmt.replace('{title}', (title && title.value.trim()) || 'Page title').replace('{site}', name);
    t.textContent = clip(tt, 62);
    var text = (seoDesc && seoDesc.value.trim()) || (excerpt && excerpt.value.trim()) || (area && area.innerText) || (source ? source.value.replace(/<[^>]+>/g, ' ') : '');
    $('[data-serp-desc]').textContent = clip(text || 'The start of the page text appears here.', 160);
    $('[data-serp-url]').textContent = (base + (fullPath || (slug && slug.value) || '')).replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/\//g, ' › ');
  }
  if (area) { area.addEventListener('input', function () { clearTimeout(area._t); area._t = setTimeout(serp, 400); }); }
  serp();

  /* ---- sharing image preview ---- */
  var img = $('[data-social-image]');
  var thumb = $('[data-social-thumb]');
  function thumbUpd() {
    if (!img || !thumb) { return; }
    var v = img.value.trim();
    thumb.innerHTML = '';
    if (/^(https?:\/\/|\/)/.test(v)) {
      var i = document.createElement('img'); i.alt = ''; i.src = v;
      i.onerror = function () { thumb.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning" title="The image could not be loaded"></i>'; };
      thumb.appendChild(i);
    } else { thumb.innerHTML = '<i class="fa-regular fa-image"></i>'; }
  }
  if (img) { img.addEventListener('change', thumbUpd); img.addEventListener('blur', thumbUpd); thumbUpd(); }

  /* ---- status: schedule box, help line and button label ---- */
  var help = $('[data-status-help]');
  var schedule = $('[data-schedule-box]');
  var saveLabel = $('[data-save-label] span');
  var isNew = (form.getAttribute('data-page-id') || '0') === '0';
  function statusUpd() {
    var s = ($('[data-page-status]:checked') || {}).value || 'draft';
    if (schedule) { schedule.hidden = s !== 'scheduled'; }
    var texts = { draft: 'Only staff can see a draft. Use Preview to check it.', published: 'Visitors can see the page as soon as you save.', scheduled: 'The page goes live by itself at the time you choose.' };
    if (help) { help.textContent = texts[s] || ''; }
    if (saveLabel) { saveLabel.textContent = s === 'published' ? (isNew ? 'Publish' : 'Save and Publish') : (s === 'scheduled' ? 'Schedule' : (isNew ? 'Save Draft' : 'Save as Draft')); }
  }
  $$('[data-page-status]').forEach(function (r) { r.addEventListener('change', statusUpd); });
  statusUpd();

  /* ---- layout choice cards ---- */
  $$('.ap-choice input').forEach(function (r) {
    r.addEventListener('change', function () { $$('.ap-choice').forEach(function (c) { c.classList.toggle('is-checked', c.querySelector('input').checked); }); });
  });

  /* ---- revisions: look at an older version in a sandboxed frame ---- */
  var revModalEl = document.getElementById('revModal');
  var revModal = revModalEl && window.bootstrap ? new bootstrap.Modal(revModalEl) : null;
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-rev-view]');
    if (!b || !revModal) { return; }
    fetch(b.getAttribute('data-rev-view'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        revModalEl.querySelector('[data-rev-title]').textContent = d.title;
        revModalEl.querySelector('[data-rev-meta]').textContent = d.when + (d.by ? ' · ' + d.by : '');
        var css = 'body{font-family:system-ui,sans-serif;line-height:1.6;color:#2a3140;padding:4px 8px}img{max-width:100%;height:auto}table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px}';
        revModalEl.querySelector('[data-rev-frame]').srcdoc = '<!doctype html><meta charset="utf-8"><style>' + css + '</style>' + (d.content || '<p><em>No text.</em></p>');
        revModal.show();
      })
      .catch(function () { window.apToast && window.apToast('danger', 'Could not load that version.'); });
  });
})();
