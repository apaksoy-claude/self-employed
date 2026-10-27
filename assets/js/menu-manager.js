/* Settings > Admin Menu: drag pages and groups, rename, show or hide. Posts the whole arrangement as JSON. */
(function () {
  var form = document.querySelector('[data-menu-manager]');
  if (!form) { return; }
  var box = form.querySelector('[data-mm-groups]');
  var drag = null; // {el, kind}

  function items(g) { return g.querySelectorAll('[data-mm-item]'); }
  function refresh() {
    box.querySelectorAll('[data-mm-group]').forEach(function (g) {
      var n = items(g).length;
      var c = g.querySelector('[data-mm-count]'); if (c) { c.textContent = n + (n === 1 ? ' page' : ' pages'); }
      var e = g.querySelector('[data-mm-empty]'); if (e) { e.hidden = n > 0; }
    });
  }
  function serialize() {
    var out = [];
    box.querySelectorAll('[data-mm-group]').forEach(function (g) {
      var name = g.querySelector('[data-mm-gname]').value.trim();
      if (!name) { return; }
      var list = [];
      items(g).forEach(function (li) {
        list.push({ url: li.getAttribute('data-url'), label: li.querySelector('[data-mm-label]').value.trim(), hide: li.querySelector('[data-mm-show]').checked ? 0 : 1 });
      });
      out.push({ name: name, items: list });
    });
    return JSON.stringify(out);
  }
  form.addEventListener('submit', function () { form.querySelector('[data-menu-json]').value = serialize(); });
  form.addEventListener('change', function (e) {
    var sw = e.target.closest ? e.target.closest('[data-mm-show]') : null;
    if (sw) { sw.closest('[data-mm-item]').classList.toggle('is-off', !sw.checked); }
  });

  // Dragging: the handle arms the row, then the list under the pointer decides where it lands.
  box.addEventListener('mousedown', function (e) {
    var h = e.target.closest('[data-mm-handle], [data-mm-group-handle]');
    if (!h) { return; }
    var el = h.closest(h.hasAttribute('data-mm-handle') ? '[data-mm-item]' : '[data-mm-group]');
    el.setAttribute('draggable', 'true');
  });
  box.addEventListener('dragstart', function (e) {
    var el = e.target.closest ? e.target.closest('[draggable="true"]') : null;
    if (!el) { return; }
    drag = { el: el, kind: el.hasAttribute('data-mm-item') ? 'item' : 'group' };
    el.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', 'menu'); } catch (x) { /* some browsers need data */ }
  });
  box.addEventListener('dragover', function (e) {
    if (!drag) { return; }
    e.preventDefault();
    if (drag.kind === 'item') {
      var list = e.target.closest('[data-mm-list]') || (e.target.closest('[data-mm-group]') || document.createElement('i')).querySelector('[data-mm-list]');
      if (!list) { return; }
      var over = e.target.closest('[data-mm-item]');
      if (over && over !== drag.el) {
        var r = over.getBoundingClientRect();
        list.insertBefore(drag.el, e.clientY < r.top + r.height / 2 ? over : over.nextSibling);
      } else if (!over) { list.appendChild(drag.el); }
    } else {
      var g = e.target.closest('[data-mm-group]');
      if (g && g !== drag.el) {
        var rg = g.getBoundingClientRect();
        box.insertBefore(drag.el, e.clientY < rg.top + rg.height / 2 ? g : g.nextSibling);
      }
    }
    refresh();
  });
  box.addEventListener('drop', function (e) { if (drag) { e.preventDefault(); } });
  box.addEventListener('dragend', function () {
    if (drag) { drag.el.classList.remove('is-dragging'); drag.el.removeAttribute('draggable'); }
    drag = null; refresh();
    form.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // Keyboard: arrow keys on a handle move the row.
  box.addEventListener('keydown', function (e) {
    var h = e.target.closest ? e.target.closest('[data-mm-handle], [data-mm-group-handle]') : null;
    if (!h || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) { return; }
    e.preventDefault();
    var el = h.closest(h.hasAttribute('data-mm-handle') ? '[data-mm-item]' : '[data-mm-group]');
    var sib = e.key === 'ArrowUp' ? el.previousElementSibling : el.nextElementSibling;
    if (!sib) { return; }
    el.parentNode.insertBefore(el, e.key === 'ArrowUp' ? sib : sib.nextSibling);
    h.focus(); refresh();
  });

  form.addEventListener('click', function (e) {
    var del = e.target.closest ? e.target.closest('[data-mm-group-del]') : null;
    if (del) {
      var g = del.closest('[data-mm-group]');
      var first = box.querySelector('[data-mm-group]');
      if (box.querySelectorAll('[data-mm-group]').length < 2) { return; }
      var target = g === first ? g.nextElementSibling : first;
      items(g).forEach(function (li) { target.querySelector('[data-mm-list]').appendChild(li); });
      g.remove(); refresh();
    }
    if (e.target.closest && e.target.closest('[data-mm-add-group]')) {
      var tpl = box.querySelector('[data-mm-group]').cloneNode(true);
      tpl.querySelector('[data-mm-list]').innerHTML = '';
      tpl.querySelector('[data-mm-gname]').value = 'New Group';
      box.appendChild(tpl); refresh();
      var inp = tpl.querySelector('[data-mm-gname]'); inp.focus(); inp.select();
    }
  });
  refresh();
})();
