<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>When</th><th>Result</th><th>IP address</th><th>Browser</th></tr></thead>
    <tbody>
    <?php foreach ($pager['data'] as $h): ?>
        <tr><td><?= e(format_date($h['created_at'])) ?></td><td><?= (int) $h['success'] ? Ui::badge('Success', 'success') : Ui::badge('Failed' . ($h['reason'] ? ': ' . $h['reason'] : ''), 'danger') ?></td><td><?= e($h['ip']) ?></td><td class="small text-muted"><?= e($h['user_agent']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pager['data']): ?><tr><td colspan="4"><?= Ui::emptyState('No logins recorded yet', '', null, null, 'fa-clock-rotate-left') ?></td></tr><?php endif; ?>
    </tbody></table></div></div>
<div class="mt-3"><?= Ui::pagination($pager, []) ?></div>
<?php $this->endSection(); ?>
