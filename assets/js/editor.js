/* AP rich text editor: a toolbar over a contenteditable area, kept in sync with a textarea that is posted.
   Markup: <section data-editor> toolbar [data-ed], [data-ed-area], textarea[data-ed-source] </section>
   The server cleans the HTML on save, so this only needs to be friendly, not secure. Plain JavaScript. */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  $$('[data-editor]').forEach(function (ed) {
    var area = $('[data-ed-area]', ed);
    var src = $('[data-ed-source]', ed);
    var block = $('[data-ed-block]', ed);
    var words = $('[data-ed-words]', ed);
    var modeBtn = $('[data-ed-mode]', ed);
    var modeLabel = $('[data-ed-mode-label]', ed);
    var form = ed.closest('form');
    var html = ed.getAttribute('data-editor-mode') === 'html';
    var saved = null;

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }
    area.innerHTML = src.value;

    function sync() { if (!html) { src.value = tidy(area.innerHTML); } count(); }
    function tidy(h) { return h.replace(/<p><br><\/p>$/i, '').replace(/&nbsp;/g, ' ').trim(); }
    function count() {
      var text = html ? src.value.replace(/<[^>]+>/g, ' ') : area.innerText;
      var n = (text.trim().match(/\S+/g) || []).length;
      if (words) { words.textContent = n + (n === 1 ? ' word' : ' words') + (n > 200 ? ' · about ' + Math.max(1, Math.round(n / 200)) + ' min read' : ''); }
    }
    function setMode(toHtml) {
      if (toHtml) { src.value = tidy(area.innerHTML); } else { area.innerHTML = src.value; }
      html = toHtml;
      ed.classList.toggle('is-html', html);
      if (modeLabel) { modeLabel.textContent = html ? 'Visual' : 'HTML'; }
      $$('[data-ed], [data-ed-block], [data-ed-link], [data-ed-image], [data-ed-table], [data-ed-insert]', ed).forEach(function (b) { b.disabled = html; });
      (html ? src : area).focus();
      count();
    }
    function keep() {
      var sel = window.getSelection();
      if (sel && sel.rangeCount && area.contains(sel.anchorNode)) { saved = sel.getRangeAt(0).cloneRange(); }
    }
    function restore() {
      area.focus();
      if (saved) { var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(saved); }
    }
    function exec(cmd, val) { restore(); document.execCommand(cmd, false, val || null); sync(); state(); }
    function insertHtml(h) { restore(); document.execCommand('insertHTML', false, h); sync(); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML.replace(/"/g, '&quot;'); }
    function state() {
      $$('[data-ed]', ed).forEach(function (b) {
        var c = b.getAttribute('data-ed');
        var on = false;
        try { on = ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight'].indexOf(c) !== -1 && document.queryCommandState(c); } catch (e) { on = false; }
        b.classList.toggle('is-on', !!on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (block) {
        var v = '';
        try { v = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) { v = ''; }
        block.value = ['h2', 'h3', 'h4', 'blockquote', 'pre'].indexOf(v) !== -1 ? v : 'p';
      }
    }

    setMode(html);
    /* Start every page with a real paragraph, so the first line is not loose text. */
    function ensureBlock() {
      if (area.innerHTML.trim() === '' || area.innerHTML === '<br>') {
        area.innerHTML = '<p><br></p>';
        var r = document.createRange(); r.setStart(area.firstChild, 0); r.collapse(true);
        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
      }
    }
    area.addEventListener('focus', ensureBlock);
    area.addEventListener('input', sync);
    area.addEventListener('keyup', function () { keep(); state(); });
    area.addEventListener('mouseup', function () { keep(); state(); });
    area.addEventListener('blur', keep);
    src.addEventListener('input', count);
    if (form) { form.addEventListener('submit', function () { if (!html) { src.value = tidy(area.innerHTML); } }); }

    /* Pasting: keep structure, drop styling from Word and websites. */
    area.addEventListener('paste', function (ev) {
      var cd = ev.clipboardData;
      if (!cd) { return; }
      var h = cd.getData('text/html');
      if (!h) { return; }
      ev.preventDefault();
      var tmp = document.createElement('div');
      tmp.innerHTML = h;
      $$('style, script, meta, link, title, xml, o\\:p', tmp).forEach(function (n) { n.remove(); });
      $$('*', tmp).forEach(function (n) {
        Array.prototype.slice.call(n.attributes).forEach(function (a) {
          if (['href', 'src', 'alt', 'colspan', 'rowspan'].indexOf(a.name) === -1) { n.removeAttribute(a.name); }
        });
        if (n.tagName === 'SPAN' || n.tagName === 'FONT') { n.replaceWith.apply(n, Array.prototype.slice.call(n.childNodes)); }
      });
      document.execCommand('insertHTML', false, tmp.innerHTML);
      sync();
    });

    ed.addEventListener('mousedown', function (ev) { if (ev.target.closest('.ap-editor-toolbar button')) { ev.preventDefault(); } });
    $$('[data-ed]', ed).forEach(function (b) { b.addEventListener('click', function () { exec(b.getAttribute('data-ed')); }); });
    if (block) { block.addEventListener('change', function () { exec('formatBlock', '<' + block.value + '>'); }); }
    if (modeBtn) { modeBtn.addEventListener('click', function () { setMode(!html); }); }
    var full = $('[data-ed-full]', ed);
    if (full) {
      full.addEventListener('click', function () {
        var on = !ed.classList.contains('is-full');
        ed.classList.toggle('is-full', on);
        document.body.classList.toggle('ap-ed-fullscreen', on);
        $('i', full).className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
      });
      document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && ed.classList.contains('is-full')) { full.click(); } });
    }

    /* Link */
    var linkModalEl = document.getElementById('edLinkModal');
    var linkModal = linkModalEl && window.bootstrap ? new bootstrap.Modal(linkModalEl) : null;
    function openLink() {
      keep();
      if (!linkModal) { var u = window.prompt('Link address'); if (u) { exec('createLink', u); } return; }
      var a = saved && saved.startContainer.parentElement ? saved.startContainer.parentElement.closest('a') : null;
      $('#edLinkUrl').value = a ? a.getAttribute('href') : '';
      $('#edLinkText').value = '';
      $('#edLinkNew').checked = a ? a.getAttribute('target') === '_blank' : false;
      linkModal.show();
      setTimeout(function () { $('#edLinkUrl').focus(); }, 300);
    }
    var lb = $('[data-ed-link]', ed);
    if (lb) { lb.addEventListener('click', openLink); }
    area.addEventListener('keydown', function (ev) { if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') { ev.preventDefault(); ev.stopPropagation(); openLink(); } });
    var linkOk = document.getElementById('edLinkOk');
    if (linkOk) {
      linkOk.addEventListener('click', function () {
        var url = $('#edLinkUrl').value.trim();
        if (!url) { return; }
        var empty = !saved || saved.collapsed;
        var text = $('#edLinkText').value.trim() || url;
        var target = $('#edLinkNew').checked ? ' target="_blank" rel="noopener"' : '';
        linkModal.hide();
        if (empty) { insertHtml('<a href="' + esc(url) + '"' + target + '>' + esc(text) + '</a>'); return; }
        exec('createLink', url);
        var sel = window.getSelection();
        var a = sel && sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement).closest('a') : null;
        if (a) { if (target) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener'); } else { a.removeAttribute('target'); } }
        sync();
      });
    }

    /* Image */
    var imgModalEl = document.getElementById('edImageModal');
    var imgModal = imgModalEl && window.bootstrap ? new bootstrap.Modal(imgModalEl) : null;
    var ib = $('[data-ed-image]', ed);
    if (ib) {
      ib.addEventListener('click', function () {
        keep();
        if (!imgModal) { var u = window.prompt('Image address'); if (u) { insertHtml('<img src="' + esc(u) + '" alt="">'); } return; }
        ['#edImgUrl', '#edImgAlt', '#edImgCaption'].forEach(function (s) { $(s).value = ''; });
        imgModal.show();
        setTimeout(function () { $('#edImgUrl').focus(); }, 300);
      });
    }
    var pickBtn = imgModalEl ? imgModalEl.querySelector('[data-ed-pick]') : null;
    if (pickBtn) {
      pickBtn.addEventListener('click', function () {
        if (!window.apMediaPicker) { return; }
        imgModal.hide();
        window.apMediaPicker.open({ kind: 'image', title: 'Choose an Image', onSelect: function (it) {
          $('#edImgUrl').value = it.medium || it.url;
          if (!$('#edImgAlt').value) { $('#edImgAlt').value = it.alt || ''; }
          if (!$('#edImgCaption').value) { $('#edImgCaption').value = it.caption || ''; }
          setTimeout(function () { imgModal.show(); }, 350);
        } });
      });
    }
    var imgOk = document.getElementById('edImgOk');
    if (imgOk) {
      imgOk.addEventListener('click', function () {
        var url = $('#edImgUrl').value.trim();
        if (!url) { return; }
        var img = '<img src="' + esc(url) + '" alt="' + esc($('#edImgAlt').value.trim()) + '">';
        var cap = $('#edImgCaption').value.trim();
        imgModal.hide();
        insertHtml(cap ? '<figure>' + img + '<figcaption>' + esc(cap) + '</figcaption></figure><p></p>' : img);
      });
    }

    /* Ready-made pieces from modules, for example a contact form: <button data-ed-insert="<p>[contact-form]</p>"> */
    $$('[data-ed-insert]', ed).forEach(function (b) { b.addEventListener('click', function () { insertHtml(b.getAttribute('data-ed-insert')); }); });

    /* Table */
    var tb = $('[data-ed-table]', ed);
    if (tb) {
      tb.addEventListener('click', function () {
        var rows = '';
        for (var r = 0; r < 3; r++) { rows += '<tr>' + (r === 0 ? '<th>Heading</th><th>Heading</th><th>Heading</th>' : '<td>…</td><td>…</td><td>…</td>') + '</tr>'; }
        insertHtml('<table><tbody>' + rows + '</tbody></table><p></p>');
      });
    }
    count();
  });
})();
