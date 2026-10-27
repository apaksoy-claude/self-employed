<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Newsletter\Controllers\SubscriberController;

$this->extend('admin::layout');
$qs = array_filter(['tab' => $tab !== 'subscribed' ? $tab : null, 'q' => $q]);
?>
<?php $this->section('actions'); ?>
<?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/newsletter')) ?>"><i class="fa-solid fa-sliders me-1"></i>Sign-up Settings</a><?php endif; ?>
<a class="btn btn-primary" href="<?= e(admin_url('subscribers/export') . ($qs ? '?' . http_build_query($qs) : '')) ?>"><i class="fa-solid fa-file-csv me-1"></i>Download</a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-news-stats mb-3">
    <div><strong><?= number_format((int) $counts['subscribed']) ?></strong><span>on the list</span></div>
    <div><strong><?= number_format((int) $month) ?></strong><span>joined in 30 days</span></div>
    <div><strong><?= number_format((int) $counts['left']) ?></strong><span>left</span></div>
</div>
<div class="ap-tabs-bar mb-3">
    <nav class="nav ap-tabs" aria-label="Subscribers">
        <?php foreach (SubscriberController::TABS as $k => $label): ?>
            <a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= e(admin_url('subscribers') . ($k !== 'subscribed' ? '?tab=' . $k : '')) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="ap-tab-count"><?= (int) ($counts[$k] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="ap-tabs-search" role="search"><?php if ($tab !== 'subscribed'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Email or name" aria-label="Search subscribers" data-shortcut-search></form>
</div>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState($q !== '' ? 'Nobody matches.' : 'No subscribers yet.', 'Add the "Newsletter sign-up" block to the footer, or type [newsletter] in any page.', admin_url('footer'), 'Open the Footer', 'fa-envelope-circle-check') ?></div></div>
<?php else: ?>
<?php $manage = can('newsletter.manage'); ?>
<form method="post" action="<?= e(admin_url('subscribers/bulk')) ?>">
<?php if ($manage): ?><?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
    <div class="ap-bulkbar alert alert-primary py-2 align-items-center gap-2" role="region" aria-label="Bulk actions">
        <span><strong data-bulk-count>0</strong> selected</span>
        <select class="form-select form-select-sm w-auto" name="action" aria-label="Bulk action"><option value="leave">Mark as left</option><option value="rejoin">Put back on the list</option><option value="delete">Remove for good</option></select>
        <button class="btn btn-sm btn-primary" type="submit" data-confirm="Apply this to the ticked subscribers?"><?= e(__('core.apply')) ?></button>
    </div>
<?php endif; ?>
<div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><?php if ($manage): ?><th style="width:36px"><input class="form-check-input" type="checkbox" id="bulkAll" aria-label="Select all"></th><?php endif; ?><th>Email</th><th>Signed up</th><th>Where</th><th>Status</th><?php if (can('newsletter.manage')): ?><th class="text-end"><span class="visually-hidden">Actions</span></th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($pager['data'] as $s): ?>
        <tr>
            <?php if ($manage): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" data-bulk-item aria-label="Select <?= e((string) $s['email']) ?>"></td><?php endif; ?>
            <td><a class="fw-semibold text-decoration-none" href="mailto:<?= e((string) $s['email']) ?>"><?= e((string) $s['email']) ?></a><?php if ($s['name']): ?><div class="small text-muted"><?= e((string) $s['name']) ?></div><?php endif; ?></td>
            <td class="small text-nowrap"><?= e(format_date((string) $s['created_at'], 'd M Y, H:i')) ?></td>
            <td class="small text-muted ap-break"><?= $s['source'] ? e((string) (parse_url((string) $s['source'], PHP_URL_PATH) ?: '/')) : '—' ?></td>
            <td><?= $s['status'] === 'subscribed' ? '<span class="ap-status-pill is-sent">On the list</span>' : '<span class="ap-status-pill">Left ' . e(format_date((string) $s['left_at'])) . '</span>' ?></td>
            <?php if (can('newsletter.manage')): ?><td class="text-end"><button class="btn btn-sm btn-light text-danger" type="submit" form="sub-del-<?= (int) $s['id'] ?>" aria-label="Remove <?= e((string) $s['email']) ?>"><i class="fa-solid fa-trash-can"></i></button><form id="sub-del-<?= (int) $s['id'] ?>" method="post" action="<?= e(admin_url('subscribers/' . (int) $s['id'] . '/delete')) ?>" data-confirm="Remove <?= e((string) $s['email']) ?> for good?" hidden><?= csrf_field() ?></form></td><?php endif; ?>
        </tr>
    <?php endforeach; ?></tbody></table></div>
    <div class="ap-table-foot"><span><?= number_format((int) $pager['total']) ?> total · page <?= (int) $pager['page'] ?> of <?= (int) $pager['last_page'] ?></span><?= Ui::pagination($pager, $qs) ?></div>
</div>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
