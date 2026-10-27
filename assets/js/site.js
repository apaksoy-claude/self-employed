/* AP Framework public site: cookie consent and small helpers. Plain JavaScript, no libraries. */
(function () {
  'use strict';
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var notice = document.querySelector('[data-ap-consent-notice]');

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function writeCookie(name, value, days) {
    var d = new Date(Date.now() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  /* Turn held-back code into real code: <script type="text/plain" data-ap-consent> and <template data-ap-consent>. */
  var activated = false;
  function activate() {
    if (activated) { return; }
    activated = true;
    $$('script[type="text/plain"][data-ap-consent]').forEach(function (old) {
      var s = document.createElement('script');
      Array.prototype.slice.call(old.attributes).forEach(function (a) {
        if (a.name !== 'type' && a.name !== 'data-ap-consent' && a.name !== 'data-type') { s.setAttribute(a.name, a.value); }
      });
      var t = old.getAttribute('data-type');
      if (t && !/javascript/i.test(t)) { s.type = t; }
      if (old.nonce) { s.nonce = old.nonce; }
      if (!old.src) { s.text = old.textContent; }
      old.parentNode.replaceChild(s, old);
    });
    $$('template[data-ap-consent]').forEach(function (tpl) {
      var frag = tpl.content ? tpl.content.cloneNode(true) : null;
      if (frag) { tpl.parentNode.replaceChild(frag, tpl); }
    });
    document.dispatchEvent(new CustomEvent('ap:consent', { detail: { given: true } }));
  }

  function show() {
    if (!notice) { return; }
    notice.hidden = false;
    requestAnimationFrame(function () { notice.classList.add('is-shown'); });
  }
  function hide() {
    if (!notice) { return; }
    notice.classList.remove('is-shown');
    setTimeout(function () { notice.hidden = true; }, 220);
  }

  var answer = readCookie('ap_consent');
  if (!notice) {
    /* No notice on this page means nothing waits for consent, or the site does not ask. */
    if ($$('[data-ap-consent]').length && answer !== 'no') { activate(); }
  } else if (answer === 'yes') {
    activate();
  } else if (answer !== 'no') {
    show();
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-ap-consent-answer]');
    if (b) {
      var yes = b.getAttribute('data-ap-consent-answer') === 'yes';
      writeCookie('ap_consent', yes ? 'yes' : 'no', parseInt((notice && notice.getAttribute('data-days')) || '180', 10));
      hide();
      if (yes) { activate(); } else if (activated) { location.reload(); }
      return;
    }
    var link = ev.target.closest && ev.target.closest('[data-ap-cookie-settings]');
    if (link) { ev.preventDefault(); show(); }
  });
  window.apConsent = { show: show, given: function () { return readCookie('ap_consent') === 'yes'; } };

  /* ---- the website as an app: service worker, install button, "add to home screen" box, push notifications ---- */
  var cfgEl = document.getElementById('apAppData');
  var cfg = {};
  try { cfg = cfgEl ? JSON.parse(cfgEl.textContent) : {}; } catch (e) { cfg = {}; }
  if (!cfg.sw || !('serviceWorker' in navigator)) { return; }
  var T = cfg.text || {};
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } } };
  var regReady = navigator.serviceWorker.register(cfg.sw).then(function () { return navigator.serviceWorker.ready; }).catch(function () { return null; });

  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var deferred = null;
  var installBtn = document.querySelector('[data-ap-install]');
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  window.addEventListener('beforeinstallprompt', function (ev) {
    ev.preventDefault();
    deferred = ev;
    if (installBtn && cfg.install) { installBtn.hidden = false; }
    maybePrompt();
  });
  window.addEventListener('appinstalled', function () { if (installBtn) { installBtn.hidden = true; } store.set('ap_app_prompt', 'done'); });
  function install() {
    if (!deferred) { return; }
    deferred.prompt();
    deferred.userChoice.then(function () { deferred = null; if (installBtn) { installBtn.hidden = true; } });
  }
  if (installBtn) { installBtn.addEventListener('click', install); }

  var visits = parseInt(store.get('ap_visits') || '0', 10);
  try { if (!sessionStorage.getItem('ap_visit_counted')) { sessionStorage.setItem('ap_visit_counted', '1'); visits += 1; store.set('ap_visits', String(visits)); } } catch (e) { /* private mode */ }
  var prompted = false;
  function maybePrompt() {
    if (prompted || !cfg.prompt || standalone || store.get('ap_app_prompt') || visits < (cfg.promptVisits || 1)) { return; }
    if (!deferred && !ios) { return; }
    prompted = true;
    var box = document.createElement('div');
    box.className = 'ap-app-prompt';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', T.promptTitle || '');
    box.innerHTML = '<div class="ap-app-prompt-icon"><i class="fa-solid fa-mobile-screen-button"></i></div><div class="ap-app-prompt-text"><strong></strong><span></span></div>'
      + '<div class="ap-app-prompt-buttons"><button type="button" class="btn btn-sm btn-outline-secondary" data-later></button>' + (ios ? '' : '<button type="button" class="btn btn-sm btn-dark" data-go></button>') + '</div>';
    box.querySelector('strong').textContent = T.promptTitle || '';
    box.querySelector('span').textContent = ios ? (T.prompt + ' ' + T.ios) : T.prompt;
    box.querySelector('[data-later]').textContent = T.later || 'Not now';
    var go = box.querySelector('[data-go]');
    if (go) { go.textContent = T.install || 'Install'; go.addEventListener('click', function () { box.remove(); store.set('ap_app_prompt', 'done'); install(); }); }
    box.querySelector('[data-later]').addEventListener('click', function () { box.classList.remove('is-shown'); store.set('ap_app_prompt', String(Date.now())); setTimeout(function () { box.remove(); }, 250); });
    document.body.appendChild(box);
    requestAnimationFrame(function () { box.classList.add('is-shown'); });
  }
  if (ios) { setTimeout(maybePrompt, 1500); }

  /* push notifications: [data-ap-push] buttons */
  var pushBtns = Array.prototype.slice.call(document.querySelectorAll('[data-ap-push]'));
  if (!cfg.push || !cfg.pushKey || !('PushManager' in window) || !('Notification' in window) || !pushBtns.length) { return; }
  function keyBytes(k) {
    var raw = atob((k + '='.repeat((4 - k.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
    return out;
  }
  function b64(buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function post(url, sub) {
    var fd = new FormData();
    fd.append('_token', csrf());
    fd.append('endpoint', sub.endpoint);
    if (sub.getKey) { fd.append('p256dh', b64(sub.getKey('p256dh'))); fd.append('auth', b64(sub.getKey('auth'))); }
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
  }
  function paint(on) {
    pushBtns.forEach(function (b) {
      b.hidden = false;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      var l = b.querySelector('[data-ap-push-label]');
      if (l) { l.textContent = on ? T.pushOff : T.pushOn; }
      var i = b.querySelector('i');
      if (i) { i.className = on ? 'fa-solid fa-bell' : 'fa-regular fa-bell'; }
    });
  }
  regReady.then(function (reg) {
    if (!reg) { return; }
    if (Notification.permission === 'denied') { paint(false); pushBtns.forEach(function (b) { b.title = T.pushDenied; }); return; }
    reg.pushManager.getSubscription().then(function (s) { paint(!!s); });
    pushBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        reg.pushManager.getSubscription().then(function (s) {
          if (s) {
            return post(cfg.pushOff, s).then(function () { return s.unsubscribe(); }).then(function () { paint(false); });
          }
          return Notification.requestPermission().then(function (p) {
            if (p !== 'granted') { b.title = T.pushDenied; alertLike(T.pushDenied); return; }
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(cfg.pushKey) })
              .then(function (sub) { return post(cfg.pushUrl, sub).then(function () { paint(true); alertLike(T.pushThanks); }); });
          });
        }).catch(function () { /* the browser said no */ });
      });
    });
  });
  function alertLike(msg) {
    var n = document.createElement('div');
    n.className = 'ap-site-toast';
    n.setAttribute('role', 'status');
    n.textContent = msg;
    document.body.appendChild(n);
    requestAnimationFrame(function () { n.classList.add('is-shown'); });
    setTimeout(function () { n.classList.remove('is-shown'); setTimeout(function () { n.remove(); }, 300); }, 3500);
  }

  /* footer: fold blocks with a heading on phones, and "Back to top" */
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest && ev.target.closest('.ap-fb-toggle');
    if (t) {
      var b = t.closest('.ap-fb'); var on = !b.classList.contains('is-open');
      b.classList.toggle('is-open', on); t.setAttribute('aria-expanded', on ? 'true' : 'false');
      return;
    }
    var top = ev.target.closest && ev.target.closest('[data-ap-top]');
    if (top) {
      ev.preventDefault();
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }
  });
})();
