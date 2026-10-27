<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<p class="text-muted">Change the wording of the emails your site sends. Templates from modules that are switched off are hidden.</p>
<?php if (!$templates): ?>
    <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState('No templates', '', null, null, 'fa-file-lines') ?></div></div>
<?php else: ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Email</th><th>Subject</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($templates as $t): ?>
        <tr>
            <td><div class="fw-semibold"><?= e($t['label']) ?></div><div class="small text-muted"><?= e($t['description']) ?></div></td>
            <td class="small"><?= e($t['subject']) ?></td>
            <td class="text-nowrap"><?= $t['enabled'] ? '' : Ui::badge('Switched off', 'secondary') . ' ' ?><?= $t['customised'] ? Ui::badge('Edited', 'info') : Ui::badge('Original wording', 'light') ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('mail/templates/' . $t['key'])) ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php $this->endSection(); ?>
