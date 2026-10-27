/* The invoice pay page: print button and a calm "working" state on the pay buttons. */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-print]');
    if (b) { ev.preventDefault(); window.print(); }
  });
  var form = document.querySelector('.pay-form');
  if (!form) { return; }
  form.addEventListener('submit', function (ev) {
    var amount = form.querySelector('input[name="amount"]');
    if (amount) {
      var v = amount.value.replace(/[£,\s]/g, '');
      if (v !== '' && !/^\d+(\.\d{1,2})?$/.test(v)) { ev.preventDefault(); amount.focus(); amount.classList.add('is-bad'); return; }
      amount.value = v;
    }
    setTimeout(function () {
      form.querySelectorAll('.pay-btn').forEach(function (b) { b.disabled = true; b.classList.add('is-busy'); });
    }, 0);
  });
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) { form.querySelectorAll('.pay-btn').forEach(function (b) { b.disabled = false; b.classList.remove('is-busy'); }); }
  });
})();
