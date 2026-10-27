/* Admin > Push notifications: live preview, "Allow on this browser" and "Send me a test". */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var token = function () { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; };
  var toast = function (t, m) { if (window.apToast) { window.apToast(t, m); } };

  var form = $('[data-push-form]');
  if (form) {
    var t = $('[data-push-title]', form), b = $('[data-push-body]', form);
    var pt = $('[data-push-preview-title]'), pb = $('[data-push-preview-body]');
    var sync = function () { pt.textContent = t.value.trim() || 'Your title'; pb.textContent = b.value.trim() || 'Your message shows here.'; };
    t.addEventListener('input', sync); b.addEventListener('input', sync); sync();
    var test = $('[data-push-test]', form);
    if (test) {
      test.addEventListener('click', function () {
        if (!t.value.trim()) { t.focus(); toast('warning', 'Write a title first.'); return; }
        var fd = new FormData(form);
        test.disabled = true;
        fetch(test.getAttribute('data-url'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { toast(d.ok ? 'success' : 'warning', d.message); })
          .catch(function () { toast('danger', 'Could not send the test.'); })
          .then(function () { test.disabled = false; });
      });
    }
  }

  var me = $('[data-push-me]');
  var allow = me ? $('[data-push-allow]', me) : null;
  function keyBytes(k) {
    var pad = '='.repeat((4 - k.length % 4) % 4);
    var raw = atob((k + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
    return out;
  }
  function b64(buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  if (allow) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      allow.disabled = true;
      $('[data-push-me-text]', me).textContent = 'This browser cannot get push notifications.';
    }
    allow.addEventListener('click', function () {
      allow.disabled = true;
      Notification.requestPermission().then(function (p) {
        if (p !== 'granted') { throw new Error('Notifications are blocked for this site in the browser settings.'); }
        return navigator.serviceWorker.register(me.getAttribute('data-sw'));
      }).then(function () { return navigator.serviceWorker.ready; })
        .then(function (reg) { return reg.pushManager.getSubscription().then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(me.getAttribute('data-key')) }); }); })
        .then(function (sub) {
          var fd = new FormData();
          fd.append('_token', token()); fd.append('endpoint', sub.endpoint); fd.append('p256dh', b64(sub.getKey('p256dh'))); fd.append('auth', b64(sub.getKey('auth')));
          return fetch(me.getAttribute('data-subscribe'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
        })
        .then(function (d) { toast(d.ok ? 'success' : 'warning', d.ok ? 'This browser now gets notifications.' : d.message); if (d.ok) { $('[data-push-me-text]', me).textContent = 'Your browser gets notifications, so "Send me a test" reaches you.'; } })
        .catch(function (e) { toast('warning', e.message || 'Could not switch notifications on.'); })
        .then(function () { allow.disabled = false; });
    });
  }
})();
