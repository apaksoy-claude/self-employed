/* Sign-in pages: show or hide the password, and stop a second press while signing in. Plain JavaScript. */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-reveal]');
    if (!b) { return; }
    var input = document.getElementById(b.getAttribute('data-reveal'));
    if (!input) { return; }
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    var i = b.querySelector('i'); if (i) { i.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'; }
    input.focus();
  });
  Array.prototype.forEach.call(document.querySelectorAll('.ap-auth form'), function (f) {
    f.addEventListener('submit', function () {
      var btn = f.querySelector('button[type=submit]');
      if (btn && !btn.disabled) { setTimeout(function () { btn.disabled = true; btn.classList.add('is-busy'); }, 0); }
    });
  });
})();
