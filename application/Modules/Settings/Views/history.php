<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<div class="ap-settings no-aside">
    <div class="ap-sections">
        <?php if ($key !== ''): ?><div><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('settings/history')) ?>"><i class="fa-solid fa-xmark me-1"></i>Show every setting</a></div><?php endif; ?>
        <div class="card ap-card"><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>When</th><th>Setting</th><th>Old value</th><th>New value</th><th>Changed by</th></tr></thead>
            <tbody>
            <?php foreach ($pager['data'] as $h): $label = $defs[$h['name']]['label'] ?? $h['name']; ?>
                <tr>
                    <td class="text-nowrap"><?= e(format_date($h['changed_at'])) ?></td>
                    <td><a class="text-decoration-none" href="?key=<?= e(rawurlencode($h['name'])) ?>"><?= e($label) ?></a></td>
                    <td class="text-muted ap-code text-break"><?= e(mb_strimwidth((string) $h['old_value'], 0, 80, '…')) ?></td>
                    <td class="ap-code text-break"><?= e(mb_strimwidth((string) $h['new_value'], 0, 80, '…')) ?></td>
                    <td><?= e($h['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pager['data']): ?><tr><td colspan="5"><?= Ui::emptyState('No changes yet', 'Changes to settings will be listed here.', null, null, 'fa-clock-rotate-left') ?></td></tr><?php endif; ?>
            </tbody></table></div></div>
        <div><?= Ui::pagination($pager, array_filter(['key' => $key])) ?></div>
    </div>
</div>
<?php $this->endSection(); ?>
