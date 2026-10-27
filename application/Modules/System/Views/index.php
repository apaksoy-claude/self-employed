<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<?php if ($phpOutdated): ?><div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>PHP <?= e(PHP_VERSION) ?> no longer receives security fixes. Ask your host to switch to PHP 8.2 or newer.</div><?php endif; ?>
<?php if ($debug): ?><div class="alert alert-warning"><i class="fa-solid fa-bug me-2"></i>Debug mode is on. Turn it off on a live site (set <code>'debug' => false</code> in <code>application/config/local.php</code>).</div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent fw-semibold">Server</div>
        <table class="table table-sm mb-0"><tbody>
        <?php foreach ($info as $k => $v): ?><tr><th class="w-50 fw-normal text-muted"><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?>
        <?php if ($disk['total']): ?><tr><th class="fw-normal text-muted">Disk space</th><td><?= e(format_bytes($disk['free'])) ?> free of <?= e(format_bytes($disk['total'])) ?></td></tr><?php endif; ?>
        </tbody></table></div></div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">PHP extensions</div><div class="card-body d-flex flex-wrap gap-2">
            <?php foreach ($extensions as $ext => $ok): ?><?= Ui::badge($ext . ($ok ? '' : ' (missing)'), $ok ? 'success' : 'danger') ?><?php endforeach; ?>
        </div></div>
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">Folder permissions</div>
            <ul class="list-group list-group-flush">
            <?php foreach ($perms as $label => $p): ?>
                <li class="list-group-item d-flex justify-content-between gap-3"><div><code><?= e($label) ?></code><?php if (!$p['writable']): ?><div class="small text-muted"><?= e($p['fix']) ?></div><?php endif; ?></div>
                <div><?= !$p['exists'] ? Ui::badge('Missing', 'danger') : ($p['writable'] ? Ui::badge('Writable', 'success') : Ui::badge('Read only', 'warning')) ?></div></li>
            <?php endforeach; ?>
            </ul></div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Version</div><div class="card-body small">AP Framework <?= e(AP_VERSION) ?>. Install updates from <a href="<?= e(admin_url('system/updates')) ?>">System &gt; Updates</a>.</div></div>
    </div>
    <?php if ($recoveryKey !== ''): ?>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-life-ring me-2"></i>Recovery link</div><div class="card-body">
        <p class="small text-muted mb-2">If a module ever breaks the site, open this private link. It starts the admin with every optional module switched off so you can fix things. Keep it somewhere safe and do not share it.</p>
        <div class="input-group"><input class="form-control ap-code" readonly value="<?= e(url('recovery/' . $recoveryKey)) ?>" aria-label="Recovery link"><button class="btn btn-outline-secondary" type="button" data-copy="<?= e(url('recovery/' . $recoveryKey)) ?>">Copy</button></div>
    </div></div></div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
