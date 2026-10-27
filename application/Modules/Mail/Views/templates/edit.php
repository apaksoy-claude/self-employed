<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
$vars = (array) $t['variables'] + $globals;
?>
<?php $this->section('content'); ?>
<?php $this->section('subtitle'); ?><?= e($t['description']) ?><?php $this->endSection(); ?>
<?php if ($t['customised']): ?><?php $this->section('actions'); ?>
    <form method="post" action="<?= e(admin_url('mail/templates/' . $key . '/reset')) ?>" data-confirm="Go back to the original wording? Your edits will be lost."><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Reset to original</button></form>
<?php $this->endSection(); ?><?php endif; ?>
<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" action="<?= e(admin_url('mail/templates/' . $key)) ?>" id="tplForm" data-unsaved-warning novalidate>
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                <?= Form::field(['name' => 'enabled', 'label' => 'Send this email', 'type' => 'checkbox', 'value' => $t['enabled'] ? 1 : 0, 'help' => 'Switch off to stop this email being sent at all.']) ?>
                <?= Form::field(['name' => 'subject', 'label' => 'Subject', 'value' => $t['subject'], 'required' => true]) ?>
                <?= Form::field(['name' => 'body', 'label' => 'Message (HTML)', 'type' => 'textarea', 'value' => $t['body'], 'rows' => 16, 'required' => true, 'attrs' => ['class' => 'font-monospace', 'spellcheck' => 'false', 'style' => 'font-size:.85rem']]) ?>
                <div class="small text-muted mb-1">Click a placeholder to insert it where the cursor is:</div>
                <div class="d-flex flex-wrap gap-1" id="chips">
                    <?php foreach ($vars as $name => $label): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-insert="{{<?= e($name) ?>}}" title="<?= e($label) ?>">{{<?= e($name) ?>}}</button>
                    <?php endforeach; ?>
                </div>
                <div class="form-text">Use <code>{{name}}</code> for text. Links and buttons made by the system use three braces, like <code>{{{link}}}</code>, and are already safe.</div>
            </div></div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= e(__('core.save')) ?></button>
                <button class="btn btn-outline-primary" type="submit" name="stay" value="1"><?= e(__('core.save_stay')) ?></button>
                <a class="btn btn-link" href="<?= e(admin_url('mail/templates')) ?>"><?= e(__('core.cancel')) ?></a>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-semibold">Preview</span><span class="small text-muted" id="pvSubject"></span></div>
            <div class="card-body p-0"><iframe id="pv" title="Email preview" sandbox="" style="width:100%;height:520px;border:0" srcdoc=""></iframe></div></div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Send a test of this version</div><div class="card-body">
            <form method="post" action="<?= e(admin_url('mail/templates/' . $key . '/test')) ?>" id="testForm"><?= csrf_field() ?>
                <input type="hidden" name="subject" id="tSubject"><input type="hidden" name="body" id="tBody">
                <div class="input-group"><input type="email" class="form-control" name="to" value="<?= e($me['email'] ?? '') ?>" aria-label="Send test to"><button class="btn btn-outline-primary">Send test</button></div>
                <div class="form-text">Uses sample values for the placeholders and includes any changes you have not saved yet.</div>
            </form>
        </div></div>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
  var form = document.getElementById('tplForm'), body = form.querySelector('[name=body]'), subj = form.querySelector('[name=subject]');
  var pv = document.getElementById('pv'), pvs = document.getElementById('pvSubject'), timer = null;
  var url = <?= json_encode(admin_url('mail/templates/' . $key . '/preview')) ?>, token = <?= json_encode(csrf_token()) ?>;
  function preview() {
    var fd = new FormData(); fd.append('_token', token); fd.append('subject', subj.value); fd.append('body', body.value);
    fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (d) { pv.srcdoc = d.html; pvs.textContent = d.subject; }).catch(function () {});
  }
  function later() { clearTimeout(timer); timer = setTimeout(preview, 400); }
  body.addEventListener('input', later); subj.addEventListener('input', later); preview();
  document.getElementById('chips').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-insert]'); if (!b) { return; }
    var target = document.activeElement === subj ? subj : body, s = target.selectionStart || 0, e = target.selectionEnd || 0;
    target.value = target.value.slice(0, s) + b.getAttribute('data-insert') + target.value.slice(e);
    target.focus(); target.selectionStart = target.selectionEnd = s + b.getAttribute('data-insert').length; later();
  });
  document.getElementById('testForm').addEventListener('submit', function () {
    document.getElementById('tSubject').value = subj.value; document.getElementById('tBody').value = body.value;
  });
})();
</script>
<?php $this->endSection(); ?>
