<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
$isNew = !(int) $user['id'];
$action = $isNew ? admin_url('users') : admin_url('users/' . (int) $user['id']);
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($action) ?>" data-unsaved-warning novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">Details</div><div class="card-body">
                <?= Form::field(['name' => 'name', 'label' => 'Full name', 'value' => $user['name'], 'required' => true]) ?>
                <?= Form::field(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user['email'], 'required' => true, 'help' => 'Used to log in.']) ?>
                <div class="row">
                    <?= Form::field(['name' => 'role_id', 'label' => 'Role', 'type' => 'select', 'value' => $user['role_id'] ?? '', 'options' => ['' => 'No role (only individual permissions)'] + $roles, 'help' => 'A role is a ready-made set of permissions.', 'col' => 'col-md-6']) ?>
                    <?= Form::field(['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $user['status'], 'options' => ['active' => 'Active', 'disabled' => 'Disabled'], 'help' => 'Disabled staff cannot log in.', 'col' => 'col-md-6']) ?>
                </div>
                <?php if ($canSuper): ?>
                    <?= Form::field(['name' => 'is_super', 'label' => 'Super Admin (can do everything, ignores permissions)', 'type' => 'checkbox', 'value' => $user['is_super']]) ?>
                <?php endif; ?>
            </div></div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">Password</div><div class="card-body">
                <?php if ($isNew && \AP\Modules\Users\PasswordResets::available()): ?>
                    <?= Form::field(['name' => 'invite', 'label' => 'Email them an invitation so they choose their own password', 'type' => 'checkbox', 'value' => 1, 'help' => 'Recommended. The password boxes below are then ignored.', 'attrs' => ['data-invite-toggle' => true]]) ?>
                <?php endif; ?>
                <?= Form::field(['name' => 'password', 'label' => $isNew ? 'Password' : 'New password', 'type' => 'password', 'required' => false, 'help' => ($isNew ? '' : 'Leave blank to keep the current password. ') . 'At least ' . (int) $passwordHint . ' characters.', 'attrs' => ['autocomplete' => 'new-password']]) ?>
                <?= Form::field(['name' => 'must_change_password', 'label' => 'Ask them to choose a new password at next login', 'type' => 'checkbox', 'value' => $user['must_change_password']]) ?>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Individual permissions <i class="fa-regular fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" title="Override the role for this person only. Leave on 'Use role' unless you need an exception."></i></div>
            <div class="card-body" style="max-height:520px;overflow:auto">
                <?php foreach ($catalogue as $module => $perms): ?>
                    <div class="fw-semibold small text-uppercase text-muted mt-2"><?= e($modules[$module] ?? $module) ?></div>
                    <?php foreach ($perms as $perm => $label): $cur = isset($overrides[$perm]) ? ((int) $overrides[$perm] === 1 ? 'allow' : 'deny') : ''; ?>
                        <div class="d-flex justify-content-between align-items-center gap-2 py-1">
                            <label class="small mb-0" for="perm_<?= e(str_replace('.', '_', $perm)) ?>"><?= e($label) ?></label>
                            <select class="form-select form-select-sm w-auto" id="perm_<?= e(str_replace('.', '_', $perm)) ?>" name="perm[<?= e($perm) ?>]">
                                <option value="">Use role</option><option value="allow"<?= $cur === 'allow' ? ' selected' : '' ?>>Allow</option><option value="deny"<?= $cur === 'deny' ? ' selected' : '' ?>>Deny</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>
    <?= Form::saveButtons($isNew, admin_url('users')) ?>
</form>
<?php if (!$isNew): ?>
<div class="card border-0 shadow-sm mt-4"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <a class="btn btn-outline-secondary" href="<?= e(admin_url('users/' . (int) $user['id'] . '/history')) ?>"><i class="fa-solid fa-clock-rotate-left me-1"></i>Login history</a>
    <form method="post" action="<?= e(admin_url('users/' . (int) $user['id'] . '/logout-everywhere')) ?>" data-confirm="End all sessions for this account?"><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-right-from-bracket me-1"></i>Log out everywhere</button></form>
    <?php if (can('users.delete') && (int) $user['id'] !== (int) auth_user()['id']): ?>
    <form method="post" action="<?= e(admin_url('users/' . (int) $user['id'] . '/delete')) ?>" data-confirm="<?= e(__('core.confirm_delete')) ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i><?= e(__('core.delete')) ?></button></form>
    <?php endif; ?>
</div></div>
<?php endif; ?>
<?php $this->endSection(); ?>
