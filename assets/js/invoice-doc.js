/* The printable invoice: the button prints, and ?print=1 opens the print box by itself. */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-print]');
    if (b) { ev.preventDefault(); window.print(); }
  });
  if (/[?&]print=1\b/.test(location.search)) { window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); }); }
})();
