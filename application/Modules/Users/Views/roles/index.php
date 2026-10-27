<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<a class="btn btn-primary" href="<?= e(admin_url('roles/create')) ?>"><i class="fa-solid fa-plus me-1"></i>Add role</a>
<?php $this->endSection(); ?>
<div class="ap-hub"><div class="ap-hub-nav"><?php $this->include('users::roles._nav', ['tab' => 'roles']); ?></div><div class="ap-hub-body">
<div class="ap-section"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Role</th><th>Description</th><th>Permissions</th><th>Staff</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($roles as $r): ?>
        <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(admin_url('roles/' . (int) $r['id'] . '/edit')) ?>"><?= e($r['name']) ?></a> <?= (int) $r['is_system'] ? Ui::badge('Built in', 'secondary') : '' ?></td>
            <td class="text-muted"><?= e($r['description']) ?></td>
            <td><?= (int) $r['perm_count'] ?></td><td><?= (int) $r['user_count'] ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('roles/' . (int) $r['id'] . '/edit')) ?>"><i class="fa-solid fa-pen"></i><span class="visually-hidden"><?= e(__('core.edit')) ?></span></a>
                <?php if (!(int) $r['is_system']): ?>
                <form class="d-inline" method="post" action="<?= e(admin_url('roles/' . (int) $r['id'] . '/delete')) ?>" data-confirm="<?= e(__('core.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i><span class="visually-hidden"><?= e(__('core.delete')) ?></span></button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<p class="small text-muted mt-2">Switch whole areas on or off for each role in <a href="<?= e(admin_url('roles/access')) ?>">Who can open what</a>. Open a role to choose the finer permissions, such as editing or deleting.</p>
</div></div>
<?php $this->endSection(); ?>
