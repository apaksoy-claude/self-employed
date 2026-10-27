/* Maps on the website: tries each map service in the chosen order and moves on when one cannot load. Plain JavaScript with Leaflet. */
(function () {
  'use strict';
  var me = document.currentScript;
  var nonce = me ? (me.nonce || me.getAttribute('nonce') || '') : '';
  var googleWaiting = null;

  function loadGoogle(key) {
    if (window.google && window.google.maps && window.google.maps.Map) { return Promise.resolve(); }
    if (googleWaiting) { return googleWaiting; }
    googleWaiting = new Promise(function (resolve, reject) {
      window.apGoogleReady = function () { resolve(); };
      window.gm_authFailure = function () { reject(new Error('key')); document.dispatchEvent(new Event('ap-google-failed')); };
      var s = document.createElement('script');
      if (nonce) { s.nonce = nonce; }
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&callback=apGoogleReady&loading=async';
      s.async = true; s.onerror = function () { reject(new Error('load')); };
      document.head.appendChild(s);
      setTimeout(function () { reject(new Error('timeout')); }, 9000);
    });
    return googleWaiting;
  }

  function show(el, cfg, i) {
    var svc = cfg.services[i];
    el.innerHTML = '';
    if (!svc) {
      el.classList.add('is-failed');
      el.innerHTML = '<div class="ap-map-failed"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i><span>' + (cfg.failed || 'The map could not be shown.') + '</span>'
        + '<a href="https://www.openstreetmap.org/?mlat=' + cfg.lat + '&mlon=' + cfg.lng + '#map=' + cfg.zoom + '/' + cfg.lat + '/' + cfg.lng + '" target="_blank" rel="noopener">openstreetmap.org</a></div>';
      return;
    }
    var next = function () { if (el._apMap) { try { el._apMap.remove(); } catch (e) { /* ignore */ } el._apMap = null; } show(el, cfg, i + 1); };
    if (svc.kind === 'google') {
      var failed = function () { document.removeEventListener('ap-google-failed', failed); next(); };
      loadGoogle(svc.key).then(function () {
        document.addEventListener('ap-google-failed', failed);
        var g = window.google.maps;
        var m = new g.Map(el, { center: { lat: cfg.lat, lng: cfg.lng }, zoom: cfg.zoom, gestureHandling: cfg.scroll ? 'greedy' : 'cooperative', mapTypeControl: false, streetViewControl: false });
        if (cfg.marker) {
          var mk = new g.Marker({ position: { lat: cfg.lat, lng: cfg.lng }, map: m, title: cfg.label || '' });
          if (cfg.label) { var iw = new g.InfoWindow({ content: document.createTextNode(cfg.label) }); mk.addListener('click', function () { iw.open({ anchor: mk, map: m }); }); }
        }
      }, next);
      return;
    }
    if (!window.L) { return next(); }
    var map = window.L.map(el, { scrollWheelZoom: !!cfg.scroll, attributionControl: true }).setView([cfg.lat, cfg.lng], cfg.zoom);
    el._apMap = map;
    var loaded = 0, errors = 0, done = false;
    var layer = window.L.tileLayer(svc.tiles, { attribution: svc.attr, maxZoom: 19, subdomains: 'abcd', detectRetina: true }).addTo(map);
    layer.on('tileload', function () { loaded++; });
    layer.on('tileerror', function () { if (!done && ++errors > 3 && loaded === 0) { done = true; next(); } });
    setTimeout(function () { if (!done && loaded === 0 && el._apMap === map) { done = true; next(); } }, 8000);
    if (cfg.marker) {
      var m = window.L.marker([cfg.lat, cfg.lng], { title: cfg.label || '', alt: cfg.label || '' }).addTo(map);
      if (cfg.label) { m.bindPopup(document.createTextNode(cfg.label)); }
    }
  }

  function start() {
    if (window.L && window.L.Icon && window.L.Icon.Default && me) {
      window.L.Icon.Default.imagePath = me.src.replace(/js\/maps\.js.*$/, 'lib/leaflet/images/');
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-ap-map]'), function (el) {
      var cfg; try { cfg = JSON.parse(el.getAttribute('data-ap-map')); } catch (e) { return; }
      if (!cfg || !cfg.services) { return; }
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) { if (entries[0].isIntersecting) { io.disconnect(); show(el, cfg, 0); } }, { rootMargin: '200px' });
        io.observe(el);
      } else { show(el, cfg, 0); }
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
