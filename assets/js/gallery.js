/* Public galleries: click a picture to see it big, with arrows, keyboard and swipe. Written without any library. */
(function () {
  'use strict';
  var box, img, cap, list = [], at = 0, startX = null;
  function build() {
    box = document.createElement('div'); box.className = 'apg-lb'; box.hidden = true; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Picture viewer');
    box.innerHTML = '<button type="button" class="apg-lb-x" aria-label="Close">&times;</button><button type="button" class="apg-lb-n apg-lb-prev" aria-label="Previous">&#8249;</button><figure><img alt=""><figcaption></figcaption></figure><button type="button" class="apg-lb-n apg-lb-next" aria-label="Next">&#8250;</button>';
    document.body.appendChild(box);
    img = box.querySelector('img'); cap = box.querySelector('figcaption');
    box.addEventListener('click', function (e) {
      if (e.target.closest('.apg-lb-x') || e.target === box) { close(); }
      else if (e.target.closest('.apg-lb-prev')) { go(-1); } else if (e.target.closest('.apg-lb-next')) { go(1); }
    });
    box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (e) { if (startX === null) { return; } var d = e.changedTouches[0].clientX - startX; startX = null; if (Math.abs(d) > 40) { go(d < 0 ? 1 : -1); } });
    document.addEventListener('keydown', function (e) { if (box.hidden) { return; } if (e.key === 'Escape') { close(); } if (e.key === 'ArrowLeft') { go(-1); } if (e.key === 'ArrowRight') { go(1); } });
  }
  function show() { var a = list[at]; img.src = a.getAttribute('data-apg-full'); img.alt = (a.querySelector('img') || {}).alt || ''; cap.textContent = a.getAttribute('data-apg-caption') || ''; cap.hidden = !cap.textContent; box.querySelectorAll('.apg-lb-n').forEach(function (b) { b.hidden = list.length < 2; }); }
  function go(d) { at = (at + d + list.length) % list.length; show(); }
  function close() { box.hidden = true; document.body.style.overflow = ''; if (last) { last.focus(); } }
  var last = null;
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-apg-lightbox] a[data-apg-full]') : null;
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
    e.preventDefault();
    if (!box) { build(); }
    list = [].slice.call(a.closest('[data-apg]').querySelectorAll('a[data-apg-full]'));
    at = list.indexOf(a); last = a; show(); box.hidden = false; document.body.style.overflow = 'hidden'; box.querySelector('.apg-lb-x').focus();
  });
})();
