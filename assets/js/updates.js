/* System > Updates: upload and check a package, install it with a spinner, go back, see details. Plain JavaScript. */
(function () {
  'use strict';
  var root = document.querySelector('[data-updates]');
  if (!root) { return; }
  var $ = function (s, c) { return (c || root).querySelector(s); };
  var token = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  var result = $('[data-up-result]');
  var form = $('[data-up-form]');
  var fileInput = $('[data-up-file]');
  var checkBtn = $('[data-up-check]');
  var staged = null;

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') { n.className = attrs[k]; } else if (k === 'text') { n.textContent = attrs[k]; } else { n.setAttribute(k, attrs[k]); }
    });
    (kids || []).forEach(function (c) { if (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
    return n;
  }
  function icon(cls) { var i = el('i', { class: cls }); i.setAttribute('aria-hidden', 'true'); return i; }
  function clear(node) { while (node.firstChild) { node.removeChild(node.firstChild); } }
  function post(url, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) { Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); }); }
    fd.append('_token', token);
    var pw = document.querySelector('[data-up-password]');
    if (pw && !fd.has('password')) { fd.append('password', pw.value); }
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (t) {
          try { return JSON.parse(t); } catch (e) {
            var hint = r.status === 419 || r.status === 413
              ? 'The upload was refused, usually because the file is bigger than the server allows.'
              : 'The server sent an unexpected answer (status ' + r.status + ').';
            return { ok: false, error: hint, errors: [hint] };
          }
        });
      });
  }
  function busy(text, sub) {
    clear(result);
    result.appendChild(el('div', { class: 'ap-up-busy' }, [icon('fa-solid fa-spinner fa-spin'), el('div', {}, [el('strong', { text: text }), el('div', { class: 'small text-muted', text: sub || '' })])]));
  }
  function lines(list, cls) {
    var ul = el('ul', { class: cls || 'ap-up-lines' });
    (list || []).forEach(function (t) { ul.appendChild(el('li', { text: t })); });
    return ul;
  }
  function panel(kind, title, kids) {
    clear(result);
    var ic = kind === 'ok' ? 'fa-solid fa-circle-check' : (kind === 'warn' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-xmark');
    result.appendChild(el('div', { class: 'ap-up-panel ap-up-' + kind }, [
      el('div', { class: 'ap-up-panel-head' }, [icon(ic), el('strong', { text: title })]),
      el('div', { class: 'ap-up-panel-body' }, kids)
    ]));
  }
  function button(label, cls, iconCls, handler) {
    var b = el('button', { type: 'button', class: cls }, [icon(iconCls + ' me-1'), label]);
    b.addEventListener('click', handler);
    return b;
  }
  function reset() {
    clear(result); staged = null;
    if (fileInput) { fileInput.value = ''; fileInput.dispatchEvent(new Event('change')); }
    if (form) { form.hidden = false; }
  }

  /* ---- step 1: upload and check ---- */
  if (fileInput && checkBtn) {
    fileInput.addEventListener('change', function () { checkBtn.disabled = !(fileInput.files && fileInput.files.length); });
    checkBtn.addEventListener('click', function () {
      if (!fileInput.files || !fileInput.files.length) { return; }
      var fd = new FormData();
      fd.append('package', fileInput.files[0]);
      form.hidden = true;
      busy('Uploading and checking the package…', 'Nothing is changed yet.');
      post(root.getAttribute('data-upload-url'), fd).then(showCheck).catch(function () {
        form.hidden = false;
        panel('bad', 'The upload failed', [el('p', { class: 'mb-0', text: 'Check your connection and try again.' })]);
      });
    });
  }
  function checksList(checks) {
    var ul = el('ul', { class: 'ap-up-checks' });
    (checks || []).forEach(function (c) {
      ul.appendChild(el('li', { class: c.ok ? 'ok' : 'bad' }, [icon(c.ok ? 'fa-solid fa-check' : 'fa-solid fa-xmark'), el('span', { text: c.label })]));
    });
    return ul;
  }
  function showCheck(d) {
    if (!d.ok) {
      form.hidden = false;
      panel('bad', 'This package cannot be installed', [
        lines(d.errors, 'ap-up-errors'),
        d.checks && d.checks.length ? checksList(d.checks) : null,
        el('p', { class: 'small text-muted mb-0', text: 'Nothing was changed on your site.' })
      ]);
      return;
    }
    staged = d;
    var s = d.summary;
    var facts = el('dl', { class: 'ap-up-facts' }, [
      el('dt', { text: 'Version' }), el('dd', {}, [s.from + ' → ', el('strong', { text: s.to })]),
      el('dt', { text: 'Package' }), el('dd', { text: d.title || d.file || '' }),
      el('dt', { text: 'Files' }), el('dd', { text: s.add + ' new, ' + s.replace + ' replaced, ' + s.delete + ' removed' + (s.keep ? ', ' + s.keep + ' of yours left alone' : '') })
    ]);
    var actions = el('div', { class: 'd-flex flex-wrap gap-2 mt-3' }, [
      button('Install version ' + s.to, 'btn btn-success', 'fa-solid fa-download', install),
      button('Cancel', 'btn btn-outline-secondary', 'fa-solid fa-xmark', reset)
    ]);
    panel('ok', 'Checked: ready to install', [
      facts,
      d.notes ? el('div', { class: 'ap-up-notes', text: d.notes }) : null,
      checksList(d.checks),
      el('p', { class: 'small text-muted mt-2 mb-0', text: 'A backup of your database and of every file that is replaced is made first.' }),
      actions
    ]);
  }

  /* ---- step 2 and 3: install, then finish the database part with the new code ---- */
  function install() {
    if (!staged) { return; }
    var to = staged.summary.to;
    busy('Installing version ' + to + '…', 'Please keep this page open. This takes a few seconds.');
    post(root.getAttribute('data-install-url'), { id: staged.id }).then(function (d) {
      if (!d.ok) {
        panel('bad', 'The update was not installed', [el('p', { text: d.error || 'Something went wrong.' }), d.log && d.log.length ? lines(d.log) : null,
          el('p', { class: 'small text-muted mb-2', text: 'Your site is unchanged.' }), button('Close', 'btn btn-outline-secondary', 'fa-solid fa-xmark', function () { window.location.reload(); })]);
        return;
      }
      busy('Finishing the update…', 'Updating the database with the new version.');
      finish(d.log || [], to);
    }).catch(function () {
      panel('warn', 'The connection was interrupted', [el('p', { text: 'The update may have been installed. Reload this page to see the result.' }), button('Reload', 'btn btn-dark', 'fa-solid fa-rotate', function () { window.location.reload(); })]);
    });
  }
  function finish(log, to) {
    post(root.getAttribute('data-finish-url'), {}).then(function (d) {
      var all = log.concat(d.log || []);
      if (d.ok) {
        panel('ok', 'Updated to version ' + (d.version || to), [lines(all), el('div', { class: 'mt-3' }, [button('Reload the page', 'btn btn-primary', 'fa-solid fa-rotate', function () { window.location.reload(); })])]);
      } else {
        var rb = $('[data-up-rollback]');
        panel('bad', 'The files were updated but the database step failed', [el('p', { text: d.error || 'Something went wrong.' }), lines(all),
          el('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, [
            button('Try again', 'btn btn-dark', 'fa-solid fa-rotate', function () { busy('Finishing the update…', ''); finish(log, to); }),
            rb ? button('Go back to the previous version', 'btn btn-outline-danger', 'fa-solid fa-rotate-left', function () { rollback(rb); }) : null
          ])]);
      }
    }).catch(function () {
      panel('warn', 'The connection was interrupted', [el('p', { text: 'Reload this page. If a yellow "Finish update" bar shows, press it.' }), button('Reload', 'btn btn-dark', 'fa-solid fa-rotate', function () { window.location.reload(); })]);
    });
  }

  /* ---- pending finish bar ---- */
  var pend = $('[data-up-finish]');
  if (pend) {
    pend.addEventListener('click', function () {
      pend.disabled = true; pend.textContent = 'Finishing…';
      post(root.getAttribute('data-finish-url'), {}).then(function () { window.location.reload(); });
    });
  }

  /* ---- go back ---- */
  var rbBtn = $('[data-up-rollback]');
  function rollback(btn) {
    var out = $('[data-up-rollback-result]') || result;
    var run = function () {
      clear(out);
      out.appendChild(el('div', { class: 'ap-up-busy' }, [icon('fa-solid fa-spinner fa-spin'), el('div', {}, [el('strong', { text: 'Going back…' }), el('div', { class: 'small text-muted', text: 'Please keep this page open.' })])]));
      post(root.getAttribute('data-rollback-url'), { backup: btn.getAttribute('data-up-rollback') }).then(function (d) {
        if (!d.ok) { clear(out); out.appendChild(el('div', { class: 'alert alert-danger small mb-0', text: d.error || 'Could not go back.' })); return; }
        return post(root.getAttribute('data-finish-url'), {}).then(function (f) {
          clear(out);
          if (f.ok) {
            out.appendChild(el('div', { class: 'alert alert-success small mb-2', text: 'Went back to version ' + (f.version || btn.getAttribute('data-to')) + '.' }));
            out.appendChild(button('Reload the page', 'btn btn-sm btn-primary', 'fa-solid fa-rotate', function () { window.location.reload(); }));
          } else {
            out.appendChild(el('div', { class: 'alert alert-warning small mb-0', text: 'The files were put back but the database step failed: ' + (f.error || '') }));
          }
        });
      }).catch(function () { clear(out); out.appendChild(el('div', { class: 'alert alert-warning small mb-0', text: 'The connection was interrupted. Reload the page to see where things stand.' })); });
    };
    if (window.apAsk) { window.apAsk('Put version ' + btn.getAttribute('data-to') + ' back? Your data stays, but database updates are not undone.', run, 'Go back to the previous version'); } else if (window.confirm('Go back?')) { run(); }
  }
  if (rbBtn) { rbBtn.addEventListener('click', function () { rollback(rbBtn); }); }

  /* ---- history details ---- */
  var history = [];
  try { history = JSON.parse((document.getElementById('apUpdateHistory') || {}).textContent || '[]'); } catch (e) { history = []; }
  var modalEl = document.getElementById('updateDetails');
  var statusText = { installed: 'Success', failed: 'Failed (files put back)', failed_after_files: 'Needs attention', files_done: 'Unfinished', rolled_back: 'Went back', rolling_back: 'Unfinished' };
  root.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-up-details]');
    if (!b || !modalEl || !window.bootstrap) { return; }
    var h = history[parseInt(b.getAttribute('data-up-details'), 10)];
    if (!h) { return; }
    modalEl.querySelector('[data-ud-title]').textContent = (h.title || 'Update') + ': ' + h.from + ' → ' + h.to;
    var body = modalEl.querySelector('[data-ud-body]');
    clear(body);
    var f = h.files || {};
    body.appendChild(el('dl', { class: 'ap-up-facts' }, [
      el('dt', { text: 'Result' }), el('dd', { text: statusText[h.status] || h.status }),
      el('dt', { text: 'When' }), el('dd', { text: new Date(h.time).toLocaleString() }),
      h.by ? el('dt', { text: 'Installed by' }) : null, h.by ? el('dd', { text: h.by }) : null,
      el('dt', { text: 'Files' }), el('dd', { text: (f.added || 0) + ' new, ' + (f.replaced || 0) + ' replaced, ' + (f.deleted || 0) + ' removed, ' + (f.kept || 0) + ' left alone' }),
      h.db_backup ? el('dt', { text: 'Database backup' }) : null, h.db_backup ? el('dd', { text: h.db_backup }) : null,
      h.seconds !== undefined ? el('dt', { text: 'Took' }) : null, h.seconds !== undefined ? el('dd', { text: h.seconds + ' s' }) : null
    ]));
    if (h.error) { body.appendChild(el('div', { class: 'alert alert-danger small', text: h.error })); }
    if (h.notes) { body.appendChild(el('h3', { class: 'h6', text: 'What was new' })); body.appendChild(el('div', { class: 'ap-up-notes', text: h.notes })); }
    if (h.log && h.log.length) { body.appendChild(el('h3', { class: 'h6 mt-3', text: 'What happened' })); body.appendChild(lines(h.log)); }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
})();
