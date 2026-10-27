/* Menu builder: nested list with drag and drop (before / after / underneath), keyboard buttons, add panel. Plain JavaScript. */
(function () {
  'use strict';
  var form = document.querySelector('[data-menu-form]');
  var tree = document.querySelector('[data-menu-tree]');
  var tpl = document.getElementById('menuItemTpl');
  if (!form || !tree || !tpl) { return; }
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var maxDepth = parseInt(form.getAttribute('data-max-depth'), 10) || 3;
  var empty = $('[data-menu-empty]');
  var typeNames = { page: 'Page', link: 'Link', home: 'Home page', heading: 'Heading' };
  var uid = 0;
  var dirty = function () { form.dispatchEvent(new Event('input', { bubbles: true })); };

  function children(li) { return $(':scope > .ap-mtree-children', li); }
  function depthOf(li) { var d = 1, p = li.parentElement.closest('[data-mi]'); while (p) { d++; p = p.parentElement.closest('[data-mi]'); } return d; }
  function subDepth(li) { var m = 0; $$(':scope > .ap-mtree-children > [data-mi]', li).forEach(function (c) { m = Math.max(m, subDepth(c)); }); return m + 1; }

  function refresh(li) {
    var d = li._data;
    $('[data-mi-label]', li).textContent = d.label || '(no label)';
    $('[data-mi-type]', li).textContent = typeNames[d.type] || d.type;
    var warn = $('[data-mi-warn]', li);
    var problem = '';
    if (d.type === 'page' && d.page_live === false) { problem = 'Hidden until the page is published'; }
    if (d.type === 'link' && !d.url) { problem = 'Needs an address'; }
    warn.hidden = !problem; warn.textContent = problem;
    var chips = $('[data-mi-chips]', li);
    if (chips) {
      var c = [];
      if (d.device === 'desktop') { c.push('<i class="fa-solid fa-desktop"></i> Big screens'); }
      if (d.device === 'mobile') { c.push('<i class="fa-solid fa-mobile-screen"></i> Phones'); }
      if (d.show_to === 'guests') { c.push('<i class="fa-solid fa-user-slash"></i> Not signed in'); }
      if (d.show_to === 'staff') { c.push('<i class="fa-solid fa-user-lock"></i> Staff'); }
      if (d.style === 'button') { c.push('<i class="fa-solid fa-hand-pointer"></i> Button'); }
      chips.innerHTML = c.map(function (t) { return '<span>' + t + '</span>'; }).join('') + (d.badge ? '<span class="is-badge">' + d.badge.replace(/[<>&"]/g, '') + '</span>' : '');
    }
    var pageBox = $('[data-mi-page]', li);
    if (pageBox) { pageBox.textContent = (d.page_title || '') + (d.page_path ? '  ' + d.page_path : ''); }
    var prev = $('[data-mi-icon-preview] i', li);
    if (prev) { prev.className = 'fa-solid ' + (/^fa-[a-z0-9-]+$/.test(d.icon || '') ? d.icon : 'fa-circle-dot'); }
    var n = $$('[data-mi]', children(li)).length;
    $('[data-mi-remove-children]', li).textContent = n ? ' (and ' + n + ' item' + (n === 1 ? '' : 's') + ' under it)' : '';
  }

  function make(d) {
    var li = tpl.content.firstElementChild.cloneNode(true);
    li._data = { type: d.type || 'link', page_id: d.page_id || null, label: d.label || '', url: d.url || '', new_tab: !!d.new_tab, icon: d.icon || '', css_class: d.css_class || '', show_to: d.show_to || 'all',
      device: d.device || 'all', badge: d.badge || '', style: d.style || 'link',
      page_title: d.page_title || null, page_path: d.page_path || null, page_live: d.page_live === undefined ? null : d.page_live };
    var id = 'mi' + (++uid);
    $$('[data-only]', li).forEach(function (el) { if (el.getAttribute('data-only') !== li._data.type) { el.remove(); } });
    $$('[data-hide]', li).forEach(function (el) { if (el.getAttribute('data-hide') === li._data.type) { el.remove(); } });
    $$('[data-f]', li).forEach(function (input) {
      var f = input.getAttribute('data-f');
      input.id = id + '_' + f;
      var label = input.closest('[class*=col-], .form-check');
      var lab = label ? $('label', label) : null; if (lab) { lab.setAttribute('for', input.id); }
      if (input.type === 'checkbox') { input.checked = !!li._data[f]; } else { input.value = li._data[f] || ''; }
      input.addEventListener(input.type === 'checkbox' || input.tagName === 'SELECT' ? 'change' : 'input', function () {
        li._data[f] = input.type === 'checkbox' ? input.checked : input.value; refresh(li);
      });
    });
    var edit = $('[data-mi-edit]', li);
    var toggle = $('[data-mi-toggle]', li);
    toggle.setAttribute('aria-controls', id + '_edit'); edit.id = id + '_edit';
    toggle.addEventListener('click', function () { var open = edit.hidden; edit.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); li.classList.toggle('is-open', open); if (open) { var first = $('[data-f=label]', edit); if (first) { first.focus(); } } });
    $('[data-mi-remove]', li).addEventListener('click', function () {
      var n = $$('[data-mi]', children(li)).length;
      var go = function () { li.remove(); state(); dirty(); };
      if (n && window.apAsk) { window.apAsk('Remove "' + (li._data.label || 'this item') + '" and the ' + n + ' item(s) under it from the menu? Pages themselves are not deleted.', go, 'Remove from the menu'); } else { go(); }
    });
    $$('[data-mi-move]', li).forEach(function (b) { b.addEventListener('click', function () { move(li, b.getAttribute('data-mi-move')); b.focus(); }); });
    (d.children || []).forEach(function (c) { children(li).appendChild(make(c)); });
    refresh(li);
    return li;
  }

  function move(li, dir) {
    var list = li.parentElement;
    if (dir === 'up' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); }
    else if (dir === 'down' && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); }
    else if (dir === 'in') {
      var prev = li.previousElementSibling;
      if (!prev) { return window.apToast && window.apToast('info', 'There is no item above to put it under.'); }
      if (depthOf(prev) + subDepth(li) > maxDepth) { return window.apToast && window.apToast('warning', 'Menus can be at most ' + maxDepth + ' levels deep.'); }
      children(prev).appendChild(li);
    } else if (dir === 'out') {
      var parentLi = list.closest('[data-mi]');
      if (!parentLi) { return; }
      parentLi.parentElement.insertBefore(li, parentLi.nextElementSibling);
    } else { return; }
    state(); dirty();
  }

  function state() {
    empty.hidden = !!$('[data-mi]', tree);
    $$('[data-mi]', tree).forEach(function (li) {
      li.classList.toggle('has-children', !!$(':scope > .ap-mtree-children > [data-mi]', li));
      $('[data-mi-move="up"]', li).disabled = !li.previousElementSibling;
      $('[data-mi-move="down"]', li).disabled = !li.nextElementSibling;
      $('[data-mi-move="out"]', li).disabled = !li.parentElement.closest('[data-mi]');
      $('[data-mi-move="in"]', li).disabled = !li.previousElementSibling || depthOf(li.previousElementSibling) + subDepth(li) > maxDepth;
      refresh(li);
    });
  }

  function serialise(list) {
    return $$(':scope > [data-mi]', list).map(function (li) {
      var d = li._data;
      return { type: d.type, page_id: d.page_id, label: d.label.trim(), url: d.url.trim(), new_tab: d.new_tab, icon: d.icon.trim(), css_class: d.css_class.trim(), show_to: d.show_to, device: d.device, badge: d.badge.trim(), style: d.style, children: serialise(children(li)) };
    });
  }

  /* ---- load ---- */
  var data = [];
  try { data = JSON.parse(($('#menuData') || {}).textContent || '[]'); } catch (e) { data = []; }
  data.forEach(function (d) { tree.appendChild(make(d)); });
  state();

  form.addEventListener('submit', function (ev) {
    var bad = $$('[data-mi]', tree).filter(function (li) { return !li._data.label.trim() || (li._data.type === 'link' && !li._data.url.trim()); });
    if (bad.length) {
      ev.preventDefault();
      var e = $('[data-mi-edit]', bad[0]); if (e.hidden) { $('[data-mi-toggle]', bad[0]).click(); }
      bad[0].scrollIntoView({ block: 'center' });
      window.apToast && window.apToast('warning', 'Every item needs a label, and every link an address.');
      return;
    }
    $('[data-menu-items]', form).value = JSON.stringify(serialise(tree));
  });

  /* ---- open / close all ---- */
  $$('[data-tree-expand]').forEach(function (b) {
    b.addEventListener('click', function () {
      var open = b.getAttribute('data-tree-expand') === 'open';
      $$('[data-mi]', tree).forEach(function (li) { var e = $('[data-mi-edit]', li); if (e.hidden === open) { $('[data-mi-toggle]', li).click(); } });
    });
  });

  /* ---- add panel ---- */
  function add(d, open) {
    var li = make(d);
    tree.appendChild(li);
    state(); dirty();
    li.classList.add('is-new'); setTimeout(function () { li.classList.remove('is-new'); }, 1500);
    if (open) { $('[data-mi-toggle]', li).click(); }
    li.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    return li;
  }
  var filter = $('[data-page-filter]');
  if (filter) {
    filter.addEventListener('input', function () {
      var q = filter.value.trim().toLowerCase();
      $$('[data-page-pick] .ap-page-pick-row').forEach(function (r) { r.hidden = q !== '' && r.getAttribute('data-title').indexOf(q) === -1; });
    });
  }
  var pickAll = $('[data-pick-all]');
  if (pickAll) { pickAll.addEventListener('click', function () { var boxes = $$('[data-pick-page]').filter(function (c) { return !c.closest('[hidden]'); }); var on = boxes.some(function (c) { return !c.checked; }); boxes.forEach(function (c) { c.checked = on; }); pickAll.textContent = on ? 'Untick all' : 'Tick all'; }); }
  var addPages = $('[data-add-pages]');
  if (addPages) {
    addPages.addEventListener('click', function () {
      var picked = $$('[data-pick-page]:checked');
      if (!picked.length) { return window.apToast && window.apToast('info', 'Tick the pages to add first.'); }
      picked.forEach(function (c) { add({ type: 'page', page_id: parseInt(c.value, 10), label: c.getAttribute('data-title-raw'), page_title: c.getAttribute('data-title-raw'), page_path: c.getAttribute('data-path'), page_live: c.getAttribute('data-live') === '1' }); c.checked = false; });
      window.apToast && window.apToast('success', picked.length + ' page' + (picked.length === 1 ? '' : 's') + ' added. Save the menu to keep them.');
    });
  }
  var addLink = $('[data-add-link]');
  if (addLink) {
    addLink.addEventListener('click', function () {
      var l = $('#addLinkLabel'), u = $('#addLinkUrl');
      if (!l.value.trim() || !u.value.trim()) { (l.value.trim() ? u : l).focus(); return window.apToast && window.apToast('info', 'Type a label and an address.'); }
      add({ type: 'link', label: l.value.trim(), url: u.value.trim(), new_tab: $('#addLinkNew').checked });
      l.value = ''; u.value = ''; $('#addLinkNew').checked = false;
    });
  }
  var addHome = $('[data-add-home]');
  if (addHome) { addHome.addEventListener('click', function () { add({ type: 'home', label: 'Home' }); }); }
  var addHeading = $('[data-add-heading]');
  if (addHeading) { addHeading.addEventListener('click', function () { add({ type: 'heading', label: 'New heading' }, true); }); }

  /* ---- drag and drop: top quarter = before, bottom quarter = after, right half of the middle = underneath ---- */
  var dragging = null, marker = null;
  function clearMarks() { $$('.drop-before, .drop-after, .drop-inside', tree).forEach(function (el) { el.classList.remove('drop-before', 'drop-after', 'drop-inside'); }); }
  tree.addEventListener('mousedown', function (ev) { var h = ev.target.closest('[data-mi-handle]'); if (h) { h.closest('[data-mi]').setAttribute('draggable', 'true'); } });
  tree.addEventListener('mouseup', function () { $$('[data-mi][draggable]', tree).forEach(function (li) { li.removeAttribute('draggable'); }); });
  tree.addEventListener('dragstart', function (ev) {
    var li = ev.target.closest && ev.target.closest('[data-mi]');
    if (!li || li.getAttribute('draggable') !== 'true') { return; }
    dragging = li; li.classList.add('is-dragging');
    if (ev.dataTransfer) { ev.dataTransfer.effectAllowed = 'move'; try { ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* ignore */ } }
  });
  /* pages and the "Other" buttons can be dragged from the left straight into place */
  document.addEventListener('dragstart', function (ev) {
    var src = ev.target.closest && ev.target.closest('[data-drag-new]');
    if (!src) { return; }
    var d;
    if (src.hasAttribute('data-add-home')) { d = { type: 'home', label: 'Home' }; }
    else if (src.hasAttribute('data-add-heading')) { d = { type: 'heading', label: 'New heading' }; }
    else { var c = $('[data-pick-page]', src); d = { type: 'page', page_id: parseInt(c.value, 10), label: c.getAttribute('data-title-raw'), page_title: c.getAttribute('data-title-raw'), page_path: c.getAttribute('data-path'), page_live: c.getAttribute('data-live') === '1' }; }
    dragging = make(d); dragging._isNew = true; tree.classList.add('is-receiving');
    if (ev.dataTransfer) { ev.dataTransfer.effectAllowed = 'copy'; try { ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* ignore */ } }
  });
  document.addEventListener('dragend', function () {
    if (!dragging || !dragging._isNew) { return; }
    tree.classList.remove('is-receiving');
    if (dragging.parentElement) { delete dragging._isNew; dragging.classList.add('is-new'); var li = dragging; setTimeout(function () { li.classList.remove('is-new'); }, 1500); state(); dirty(); }
    dragging = null; marker = null; clearMarks();
  });
  tree.parentElement.addEventListener('dragover', function (ev) {
    if (dragging && dragging._isNew && !ev.target.closest('.ap-mi-row')) { ev.preventDefault(); clearMarks(); marker = null; }
  });
  tree.parentElement.addEventListener('drop', function (ev) {
    if (dragging && dragging._isNew && !marker) { ev.preventDefault(); tree.appendChild(dragging); }
  });
  tree.addEventListener('dragover', function (ev) {
    if (!dragging) { return; }
    var row = ev.target.closest('.ap-mi-row');
    var li = row && row.closest('[data-mi]');
    if (!li || li === dragging || dragging.contains(li)) { return; }
    ev.preventDefault();
    var r = row.getBoundingClientRect();
    var y = (ev.clientY - r.top) / r.height;
    var right = ev.clientX > r.left + Math.min(r.width / 2, 260);
    var where = right && y > 0.25 && y < 0.75 ? 'inside' : (y < 0.5 ? 'before' : 'after');
    if (where === 'inside' && depthOf(li) + subDepth(dragging) > maxDepth) { where = 'after'; }
    if (where === 'after' && depthOf(li) - 1 + subDepth(dragging) > maxDepth) { where = 'before'; }
    clearMarks(); row.classList.add('drop-' + where); marker = { li: li, where: where };
  });
  tree.addEventListener('dragleave', function (ev) { if (!tree.contains(ev.relatedTarget)) { clearMarks(); marker = null; } });
  tree.addEventListener('drop', function (ev) {
    if (!dragging) { return; }
    ev.preventDefault(); ev.stopPropagation();
    if (!marker && dragging._isNew) { tree.appendChild(dragging); return; }
    if (marker) {
      if (marker.where === 'inside') { children(marker.li).appendChild(dragging); }
      else { marker.li.parentElement.insertBefore(dragging, marker.where === 'before' ? marker.li : marker.li.nextElementSibling); }
    }
  });
  tree.addEventListener('dragend', function () {
    if (!dragging || dragging._isNew) { return; }
    dragging.classList.remove('is-dragging'); dragging.removeAttribute('draggable'); dragging = null; marker = null;
    clearMarks(); state(); dirty();
  });

  /* ---- layout choice cards ---- */
  $$('.ap-loc-list input').forEach(function (r) { r.addEventListener('change', function () { $$('.ap-loc-list .ap-choice').forEach(function (c) { c.classList.toggle('is-checked', $('input', c).checked); }); }); });
})();
