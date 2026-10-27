<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$rows = $pager['data'];
$qs = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'role' => $filters['role'] ?: null, 'per_page' => $filters['per_page'], 'sort' => $filters['sort'], 'dir' => $filters['dir']], static fn($v) => $v !== '' && $v !== null);
?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<?php if (can('users.create')): ?><a class="btn btn-primary" href="<?= e(admin_url('users/create')) ?>"><i class="fa-solid fa-plus me-1"></i>Add staff account</a><?php endif; ?>
<?php $this->endSection(); ?>

<form class="ap-toolbar mb-3" method="get">
    <input type="search" class="form-control w-auto" style="min-width:220px" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name or email" aria-label="Search" data-shortcut-search>
    <select class="form-select w-auto" name="status" data-autosubmit aria-label="Status">
        <option value="">All statuses</option>
        <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
        <option value="disabled"<?= $filters['status'] === 'disabled' ? ' selected' : '' ?>>Disabled</option>
    </select>
    <select class="form-select w-auto" name="role" data-autosubmit aria-label="Role">
        <option value="0">All roles</option>
        <?php foreach ($roles as $rid => $rname): ?><option value="<?= (int) $rid ?>"<?= $filters['role'] === (int) $rid ? ' selected' : '' ?>><?= e($rname) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select w-auto" name="per_page" data-autosubmit aria-label="Per page">
        <?php foreach ([10, 20, 50, 100] as $n): ?><option value="<?= $n ?>"<?= $filters['per_page'] === $n ? ' selected' : '' ?>><?= $n ?> per page</option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('core.search')) ?></button>
    <?php if ($qs && array_diff_key($qs, ['per_page' => 1, 'sort' => 1, 'dir' => 1])): ?><a class="btn btn-link" href="<?= e(admin_url('users')) ?>"><?= e(__('core.reset')) ?></a><?php endif; ?>
    <div class="dropdown ms-auto"><button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fa-solid fa-table-columns me-1"></i><?= e(__('core.columns')) ?></button><div class="dropdown-menu dropdown-menu-end" id="columnPicker"></div></div>
</form>

<?php if (!$rows): ?>
    <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState(__('core.no_results'), $qs ? __('core.no_results_hint') : 'Add your first staff account.', can('users.create') ? admin_url('users/create') : null, 'Add staff account', 'fa-users') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('users/bulk')) ?>">
    <?= csrf_field() ?>
    <?php if (can('users.edit')): ?>
    <div class="ap-bulkbar alert alert-primary py-2 align-items-center gap-2" role="region" aria-label="Bulk actions">
        <span><strong data-bulk-count>0</strong> selected</span>
        <select class="form-select form-select-sm w-auto" name="action" aria-label="Bulk action">
            <option value="activate">Activate</option><option value="deactivate">Deactivate</option>
            <?php if (can('users.delete')): ?><option value="delete">Delete</option><?php endif; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit" data-confirm="Apply this action to the selected accounts?"><?= e(__('core.apply')) ?></button>
    </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover mb-0" data-columns="users">
        <thead><tr>
            <th style="width:36px"><input class="form-check-input" type="checkbox" id="bulkAll" aria-label="Select all"></th>
            <?= Ui::sortTh('Name', 'name', $filters['sort'], $filters['dir'], $qs, 'name') ?>
            <?= Ui::sortTh('Email', 'email', $filters['sort'], $filters['dir'], $qs, 'email') ?>
            <?= Ui::sortTh('Role', 'role_name', $filters['sort'], $filters['dir'], $qs, 'role') ?>
            <?= Ui::sortTh('Status', 'status', $filters['sort'], $filters['dir'], $qs, 'status') ?>
            <?= Ui::th('2FA', 'twofa') ?>
            <?= Ui::sortTh('Last login', 'last_login_at', $filters['sort'], $filters['dir'], $qs, 'last') ?>
            <th class="text-end"><span class="visually-hidden"><?= e(__('core.actions')) ?></span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
            <tr>
                <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" data-bulk-item aria-label="Select <?= e($u['name']) ?>"></td>
                <td><a href="<?= e(admin_url('users/' . (int) $u['id'] . '/edit')) ?>" class="fw-semibold text-decoration-none"><?= e($u['name']) ?></a><?php if ((int) $u['is_super']): ?> <span class="badge text-bg-dark">Super Admin</span><?php endif; ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['role_name'] ?? '—') ?></td>
                <td><?= $u['status'] === 'active' ? Ui::badge('Active', 'success') : Ui::badge('Disabled', 'secondary') ?></td>
                <td><?= (int) $u['two_factor_enabled'] ? '<i class="fa-solid fa-shield-halved text-success" title="On"></i>' : '<span class="text-muted">Off</span>' ?></td>
                <td class="text-muted small"><?= e($u['last_login_at'] ? format_date($u['last_login_at']) : 'Never') ?></td>
                <td class="text-end text-nowrap">
                    <?php if (can('users.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('users/' . (int) $u['id'] . '/edit')) ?>"><i class="fa-solid fa-pen"></i><span class="visually-hidden"><?= e(__('core.edit')) ?></span></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</form>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
    <div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div>
    <?= Ui::pagination($pager, $qs) ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
