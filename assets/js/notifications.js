/* The notification bell: keeps the count and the list fresh without reloading the page. */
(function () {
  'use strict';
  var bell = document.querySelector('[data-bell]');
  if (!bell || !window.fetch) { return; }
  var count = bell.querySelector('[data-bell-count]'), menu = bell.querySelector('[data-bell-menu]');
  var every = parseInt(bell.getAttribute('data-poll'), 10) || 0;
  function refresh() {
    if (document.hidden) { return; }
    fetch(bell.getAttribute('data-feed'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) { return; }
        count.textContent = d.unread > 99 ? '99+' : String(d.unread);
        count.hidden = d.unread < 1;
        if (!bell.querySelector('.dropdown-menu.show')) { menu.innerHTML = d.html; }
      }).catch(function () {});
  }
  if (every > 0) { setInterval(refresh, Math.max(15, every) * 1000); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && every > 0) { refresh(); } });
})();
