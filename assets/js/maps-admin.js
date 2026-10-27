/* Maps settings: drag services into order, test keys, find an address and set the place on a small map. Plain JavaScript. */
(function () {
  'use strict';
  var form = document.querySelector('[data-maps-form]');
  if (!form) { return; }
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var token = ($('input[name=_token]', form) || {}).value || '';
  var list = $('[data-svc-list]', form);
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var changed = function () { form.dispatchEvent(new Event('input', { bubbles: true })); };

  /* ---- order: places, drag and drop, buttons ---- */
  function places() {
    var n = 0;
    $$('[data-svc]', list).forEach(function (li, i, all) {
      var on = $('[data-svc-on]', li).checked;
      var needs = li.getAttribute('data-needs-key') === '1';
      var key = $('[data-svc-key]', li);
      var ready = on && (!needs || (key && key.value.trim() !== ''));
      li.classList.toggle('is-on', on);
      var p = $('[data-svc-place]', li);
      if (ready) { n++; p.textContent = n === 1 ? 'Used first' : 'Back-up ' + (n - 1); p.className = 'apm-place ' + (n === 1 ? 'is-first' : 'is-backup'); }
      else { p.textContent = on ? 'Needs its key' : 'Off'; p.className = 'apm-place' + (on ? ' is-warn' : ''); }
      $('[data-svc-move="-1"]', li).disabled = i === 0;
      $('[data-svc-move="1"]', li).disabled = i === all.length - 1;
    });
  }
  list.addEventListener('change', function (ev) { if (ev.target.matches('[data-svc-on]')) { places(); } });
  list.addEventListener('input', function (ev) { if (ev.target.matches('[data-svc-key]')) { places(); } });
  list.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-svc-move]'); if (!b) { return; }
    var li = b.closest('[data-svc]');
    if (b.getAttribute('data-svc-move') === '-1' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); }
    else if (li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); }
    places(); changed(); b.focus();
  });
  var dragging = null;
  list.addEventListener('dragstart', function (ev) {
    var g = ev.target.closest && ev.target.closest('[data-svc-grip]'); if (!g) { return; }
    dragging = g.closest('[data-svc]'); dragging.classList.add('is-dragging');
    try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setDragImage(dragging, 20, 20); ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* ignore */ }
  });
  list.addEventListener('dragover', function (ev) {
    if (!dragging) { return; }
    var over = ev.target.closest('[data-svc]'); if (!over || over === dragging) { return; }
    ev.preventDefault();
    var r = over.getBoundingClientRect();
    list.insertBefore(dragging, ev.clientY < r.top + r.height / 2 ? over : over.nextElementSibling);
  });
  list.addEventListener('drop', function (ev) { if (dragging) { ev.preventDefault(); } });
  list.addEventListener('dragend', function () { if (dragging) { dragging.classList.remove('is-dragging'); dragging = null; places(); changed(); } });
  places();

  /* ---- test a service ---- */
  list.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-svc-test]'); if (!b) { return; }
    var li = b.closest('[data-svc]'), out = $('[data-svc-result]', li), key = $('[data-svc-key]', li);
    out.className = 'apm-result small'; out.textContent = 'Checking…'; b.disabled = true;
    var fd = new FormData(); fd.append('_token', token); fd.append('service', li.getAttribute('data-svc')); fd.append('key', key ? key.value : '');
    fetch(form.getAttribute('data-test-url'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { out.className = 'apm-result small ' + (d.ok ? 'is-ok' : 'is-bad'); out.innerHTML = '<i class="fa-solid ' + (d.ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' me-1"></i>' + esc(d.message || ''); })
      .catch(function () { out.className = 'apm-result small is-bad'; out.textContent = 'The check could not run. Try again.'; })
      .then(function () { b.disabled = false; });
  });

  /* ---- the small map ---- */
  var lat = $('[data-map-lat]', form), lng = $('[data-map-lng]', form), zoom = $('[data-map-zoom]', form), zoomOut = $('[data-map-zoom-out]', form);
  var box = $('[data-map-admin]', form), map = null, marker = null;
  function pos() { return [parseFloat(lat.value) || 0, parseFloat(lng.value) || 0]; }
  if (window.L && box) {
    var tiles = []; try { tiles = JSON.parse(box.getAttribute('data-tiles') || '[]'); } catch (e) { tiles = []; }
    tiles.sort(function (a, b) { return (b.on ? 1 : 0) - (a.on ? 1 : 0); });
    map = window.L.map(box, { scrollWheelZoom: false }).setView(pos(), parseInt(zoom.value, 10) || 14);
    var i = 0, layer = null;
    var useTiles = function () {
      if (layer) { map.removeLayer(layer); }
      var t = tiles[i]; if (!t) { return; }
      var errors = 0, loaded = 0;
      layer = window.L.tileLayer(t.tiles, { attribution: t.attr, maxZoom: 19, subdomains: 'abcd', detectRetina: true }).addTo(map);
      layer.on('tileload', function () { loaded++; });
      layer.on('tileerror', function () { if (++errors > 3 && !loaded && i < tiles.length - 1) { i++; useTiles(); } });
    };
    useTiles();
    marker = window.L.marker(pos(), { draggable: !lat.disabled, keyboard: true, title: 'The place' }).addTo(map);
    var put = function (ll, move) {
      lat.value = ll.lat.toFixed(6); lng.value = ll.lng.toFixed(6); marker.setLatLng(ll); if (move) { map.setView(ll); } changed();
    };
    marker.on('dragend', function () { put(marker.getLatLng(), false); });
    if (!lat.disabled) { map.on('click', function (e) { put(e.latlng, false); }); }
    map.on('zoomend', function () { zoom.value = map.getZoom(); zoomOut.textContent = map.getZoom(); });
    [lat, lng].forEach(function (inp) { inp.addEventListener('change', function () { var p = pos(); marker.setLatLng(p); map.setView(p); }); });
  } else if (box) {
    box.innerHTML = '<div class="apm-map-off">The map preview could not load. You can still type the position.</div>';
  }
  zoom.addEventListener('input', function () { zoomOut.textContent = zoom.value; if (map) { map.setZoom(parseInt(zoom.value, 10)); } });

  /* ---- find an address ---- */
  var find = $('[data-map-find]', form), go = $('[data-map-find-go]', form), results = $('[data-map-results]', form);
  function search() {
    var q = find.value.trim();
    if (q.length < 3) { find.focus(); return; }
    results.hidden = false; results.innerHTML = '<div class="apm-res-msg">Looking…</div>'; go.disabled = true;
    var fd = new FormData(); fd.append('_token', token); fd.append('q', q);
    fetch(form.getAttribute('data-find-url'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.results || !d.results.length) { results.innerHTML = '<div class="apm-res-msg">' + esc(d.error || 'Nothing found.') + '</div>'; return; }
        results.innerHTML = d.results.map(function (r, n) { return '<button type="button" class="apm-res" data-n="' + n + '"><i class="fa-solid fa-location-dot me-2"></i>' + esc(r.label) + '</button>'; }).join('')
          + '<div class="apm-res-src">' + (d.source === 'google' ? 'Results from Google' : 'Results from OpenStreetMap') + '</div>';
        $$('.apm-res', results).forEach(function (b) {
          b.addEventListener('click', function () {
            var r = d.results[+b.getAttribute('data-n')];
            lat.value = (+r.lat).toFixed(6); lng.value = (+r.lng).toFixed(6);
            if (map) { marker.setLatLng([r.lat, r.lng]); map.setView([r.lat, r.lng], Math.max(map.getZoom(), 15)); }
            results.hidden = true; changed();
          });
        });
      })
      .catch(function () { results.innerHTML = '<div class="apm-res-msg">The search could not run. Try again.</div>'; })
      .then(function () { go.disabled = false; });
  }
  go.addEventListener('click', search);
  find.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); search(); } });
})();
