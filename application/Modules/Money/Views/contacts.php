<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Controllers\ContactController;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-outline-primary mn-btn" href="<?= e(admin_url('money/contacts/statements')) ?>"><i class="fa-solid fa-envelope-open-text me-1"></i>Email Statements</a> <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/contacts/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Contact</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<form method="get" action="<?= e(admin_url('money/contacts')) ?>" class="ap-money-filters d-flex flex-wrap align-items-center gap-2 mb-3" role="search">
    <input type="search" class="form-control form-control-sm w-auto flex-grow-1" style="max-width:20rem" name="q" value="<?= e($q) ?>" placeholder="Search" aria-label="Search" data-shortcut-search>
    <button class="btn btn-sm btn-primary mn-fbtn" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
    <?php if (\AP\Modules\Money\Money::$filtering): ?><a class="btn btn-sm btn-outline-secondary mn-fbtn" href="<?= e(admin_url('money/contacts')) ?>?clear=1"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Clear Filters</a><?php endif; ?>
</form>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No contacts yet.', 'Clients you invoice and companies you pay appear here. They are also added when you type a new client on an invoice.', admin_url('money/contacts/new'), 'New Contact', 'fa-address-book') ?></div></div>
<?php else: ?>
    <?php if ($canEdit): ?><?= Bulk::bar('bulk-contacts', admin_url('money/contacts/bulk'), [['op'=>'archive','label'=>'Archive'],['op'=>'restore','label'=>'Bring Back'],['op'=>'delete','label'=>'Delete','icon'=>'fa-trash','danger'=>true,'ask'=>'Delete the ticked contacts? Any that records use are archived instead.']], 'contacts') ?><?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><?php if ($canEdit): ?><?= Bulk::head('bulk-contacts') ?><?php endif; ?><th>Name</th><th>Kind</th><th>Contact</th><th class="text-end">Invoices</th><th class="text-end">Payments</th><th class="text-end">Expenses</th><?= \AP\Modules\Money\CustomFields::th('contact') ?><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?></tr></thead><tbody>
        <?php \AP\Modules\Money\CustomFields::preload('contact', array_column($rows, 'id')); ?>
        <?php foreach ($rows as $c): $u = $uses[(int) $c['id']] ?? []; ?>
            <tr<?= (int) $c['active'] ? '' : ' class="text-muted"' ?>>
                <?php if ($canEdit): ?><?= Bulk::cell('bulk-contacts', (int) $c['id'], (string) $c['name']) ?><?php endif; ?>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/contacts/' . (int) $c['id'])) ?>"<?= Money::modalAttr() ?>><?= e((string) $c['name']) ?></a><?= (int) $c['active'] ? '' : ' ' . Ui::badge('Archived', 'secondary') ?></td>
                <td class="small"><?= e(ContactController::KINDS[$c['kind']] ?? 'Client') ?><?php if (($c['kind'] ?? 'client') === 'client' && \AP\Modules\Money\Portal::on()): ?> <a class="mn-icon" href="<?= e(admin_url('money/contacts/' . (int) $c['id'] . '/portal')) ?>" target="_blank" rel="noopener" title="Open Client Portal" aria-label="Open Client Portal"><i class="fa-solid fa-link"></i></a> <a class="mn-icon" href="<?= e(admin_url('money/contacts/' . (int) $c['id'] . '/statement')) ?>" target="_blank" rel="noopener" title="Statement (PDF)" aria-label="Statement (PDF)"><i class="fa-solid fa-file-lines"></i></a> <a class="mn-icon" href="<?= e(admin_url('money/contacts/' . (int) $c['id'] . '/statement/email')) ?>"<?= Money::modalAttr() ?> title="Email Statement" aria-label="Email Statement"><i class="fa-solid fa-envelope"></i></a><?php endif; ?></td>
                <td class="small"><?= e(implode(' · ', array_filter([(string) $c['email'], (string) $c['phone'], (string) $c['city']]))) ?></td>
                <td class="text-end"><a href="<?= e(admin_url('money/invoices?year=all&q=' . rawurlencode((string) $c['name']))) ?>"><?= (int) ($u['invoices'] ?? 0) ?></a></td>
                <td class="text-end"><a href="<?= e(admin_url('money/income?year=all&contact=' . (int) $c['id'])) ?>"><?= (int) ($u['payments'] ?? 0) ?></a></td>
                <td class="text-end"><a href="<?= e(admin_url('money/expenses?year=all&contact=' . (int) $c['id'])) ?>"><?= (int) ($u['expenses'] ?? 0) ?></a></td>
                <?= \AP\Modules\Money\CustomFields::td('contact', (int) $c['id']) ?>
                <?php $this->include('money::_row_actions', ['edit' => admin_url('money/contacts/' . (int) $c['id']), 'delete' => admin_url('money/contacts/' . (int) $c['id'] . '/delete'), 'ask' => 'Delete ' . $c['name'] . '? If records use it, it is archived instead.', 'canEdit' => $canEdit]); ?>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
