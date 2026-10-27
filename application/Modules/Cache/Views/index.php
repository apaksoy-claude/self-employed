<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/cache')) ?>"><i class="fa-solid fa-sliders me-1"></i>Cache settings</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php if (!$enabled): ?><div class="alert alert-warning">The data cache is switched off in settings, so nothing new is being stored.</div><?php endif; ?>
<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Cached items</div><div class="display-6"><?= (int) $stats['files'] ?></div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Space used</div><div class="display-6"><?= e(format_bytes($stats['bytes'])) ?></div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Already expired</div><div class="display-6"><?= (int) $stats['expired'] ?></div></div></div></div>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">What is cached</div>
<?php if (!$stats['groups']): ?>
    <div class="card-body"><?= Ui::emptyState('The cache is empty', 'Items appear here as the site and admin do their work.', null, null, 'fa-bolt') ?></div>
<?php else: ?>
    <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Group</th><th>Items</th><th>Size</th><th></th></tr></thead><tbody>
    <?php foreach ($stats['groups'] as $g => $info): ?>
        <tr><td><code><?= e($g) ?></code></td><td><?= (int) $info['files'] ?></td><td><?= e(format_bytes($info['bytes'])) ?></td>
        <td class="text-end"><form method="post" action="<?= e(admin_url('system/cache/clear')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" name="what" value="group:<?= e($g) ?>">Clear this group</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Clear</div><div class="card-body">
    <form method="post" action="<?= e(admin_url('system/cache/clear')) ?>" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
        <button class="btn btn-primary" name="what" value="all"><i class="fa-solid fa-broom me-1"></i>Clear everything</button>
        <button class="btn btn-outline-secondary" name="what" value="expired">Only expired items</button>
        <button class="btn btn-outline-secondary" name="what" value="opcache">Reset OPcache</button>
    </form>
    <p class="small text-muted mt-3 mb-0">Clearing the cache is always safe. The site just rebuilds what it needs.
    <?php if ($opcache): ?>OPcache is on (<?= (int) ($opcache['opcache_statistics']['num_cached_scripts'] ?? 0) ?> scripts cached). Reset it after uploading new files by FTP.<?php endif; ?></p>
</div></div>
<?php $this->endSection(); ?>
