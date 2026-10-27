<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e((int) $role['id'] ? admin_url('roles/' . (int) $role['id']) : admin_url('roles')) ?>" data-unsaved-warning novalidate>
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <?= Form::field(['name' => 'name', 'label' => 'Role name', 'value' => $role['name'], 'required' => true]) ?>
        <?= Form::field(['name' => 'description', 'label' => 'Description', 'value' => $role['description'], 'help' => 'Optional. Helps you remember what this role is for.']) ?>
    </div></div>
    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Permissions</div><div class="card-body">
        <p class="text-muted small">Tick what people with this role are allowed to do. Modules that are switched off do not appear here.</p>
        <div class="row g-3">
        <?php foreach ($catalogue as $module => $perms): ?>
            <div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100" data-perm-group>
                <div class="d-flex justify-content-between align-items-center mb-2"><strong><?= e($modules[$module] ?? $module) ?></strong>
                    <label class="small text-muted mb-0"><input type="checkbox" class="form-check-input me-1" data-toggle-group> All</label></div>
                <?php foreach ($perms as $perm => $label): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm[]" value="<?= e($perm) ?>" id="p_<?= e(str_replace('.', '_', $perm)) ?>"<?= in_array($perm, $granted, true) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="p_<?= e(str_replace('.', '_', $perm)) ?>"><?= e($label) ?></label></div>
                <?php endforeach; ?>
            </div></div>
        <?php endforeach; ?>
        </div>
    </div></div>
    <?= Form::saveButtons(false, admin_url('roles')) ?>
</form>
<script nonce="<?= e(csp_nonce()) ?>">
document.querySelectorAll('[data-toggle-group]').forEach(function (t) {
  t.addEventListener('change', function () {
    t.closest('[data-perm-group]').querySelectorAll('input[name="perm[]"]').forEach(function (c) { c.checked = t.checked; });
  });
});
</script>
<?php $this->endSection(); ?>
