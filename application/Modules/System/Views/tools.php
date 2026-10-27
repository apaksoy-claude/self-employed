<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<p class="text-muted">Everything the command line can do is also available here, because many hosts do not give you a terminal.</p>
<div class="row g-3">
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Database updates (migrations)</div><div class="card-body">
        <?php if ($pending): ?>
            <p><?= Ui::badge(count($pending) . ' waiting', 'warning') ?> These changes have not been applied yet:</p>
            <ul class="small"><?php foreach ($pending as $p): ?><li><code><?= e($p['module']) ?></code> <?= e($p['name']) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p><?= Ui::badge('Up to date', 'success') ?> The database matches the code.</p><?php endif; ?>
        <form method="post" action="<?= e(admin_url('system/tools/migrate')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-primary"<?= $pending ? '' : ' disabled' ?>>Run pending migrations</button></form>
        <?php if ($debug): ?><form method="post" action="<?= e(admin_url('system/tools/rollback')) ?>" class="d-inline" data-confirm="Roll back the last batch? This can delete data."><?= csrf_field() ?><button class="btn btn-outline-danger">Roll back last batch</button></form><?php endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Clear cache</div><div class="card-body">
        <p class="small text-muted">Use this after changing files by hand. Safe to do any time.</p>
        <form method="post" action="<?= e(admin_url('system/tools/cache')) ?>" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
            <button class="btn btn-primary" name="what" value="all"><i class="fa-solid fa-broom me-1"></i>Clear everything</button>
            <button class="btn btn-outline-secondary" name="what" value="data">Data cache</button>
            <button class="btn btn-outline-secondary" name="what" value="opcache">OPcache</button>
        </form>
    </div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Bootstrap and Font Awesome</div><div class="card-body">
        <?php if (vendor_missing()): ?>
            <p class="small text-muted">The admin loads them from a CDN at the moment. Download a copy into <code>assets/vendor</code> so it also works offline and faster.</p>
            <form method="post" action="<?= e(admin_url('system/tools/vendor')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="fa-solid fa-download me-1"></i>Download Now</button></form>
        <?php else: ?>
            <p class="mb-0"><?= Ui::badge('Installed', 'success') ?> A local copy is in <code>assets/vendor</code>.</p>
        <?php endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Install an update</div><div class="card-body">
        <p class="small text-muted">Upload the zip of a new version, check it and install it with a backup made first.</p>
        <?php if (can('system.updates')): ?><a class="btn btn-outline-primary" href="<?= e(admin_url('system/updates')) ?>"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Open Updates</a><?php endif; ?>
    </div></div></div>
    <?php if ($debug): ?>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Developer: run tests</div><div class="card-body">
        <form method="post" action="<?= e(admin_url('system/tools/tests')) ?>"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="fa-solid fa-vial me-1"></i>Run all tests</button></form>
        <?php if ($tests): ?>
            <table class="table table-sm mt-3 mb-0"><tbody>
            <?php foreach ($tests as $t): ?><tr><td><?= $t['ok'] ? Ui::badge('PASS', 'success') : Ui::badge('FAIL', 'danger') ?></td><td><?= e($t['name']) ?></td><td class="small text-muted"><?= e($t['detail']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div></div></div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
