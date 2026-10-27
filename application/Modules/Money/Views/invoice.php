<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Controllers\InvoiceController;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$tone = ['paid' => 'success', 'part' => 'warning', 'unpaid' => 'danger', 'overdue' => 'dark', 'draft' => 'secondary', 'cancelled' => 'secondary'];
$g = static fn($n) => e(Money::gbp($n));
$id = (int) $inv['id'];
$balance = max(0.0, Money::invoiceBalance($inv, $paid));
$modal = Money::modalAttr();
$editable = $canEdit && $state !== 'cancelled';
$vatShow = \AP\Modules\Money\Vat::onInvoice($inv, $items);
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?>
    <?php $canPayNow = $balance > 0.004 && !in_array($state, ['draft', 'cancelled', 'paid'], true); ?>
    <?php if ($canPayNow): ?>
        <a class="btn btn-success mn-btn" href="<?= e(\AP\Modules\Money\Pay::url($inv)) ?>" target="_blank" rel="noopener" title="Opens the page your client pays on"><i class="fa-solid fa-credit-card me-1"></i>Pay Invoice</a>
    <?php elseif ($state === 'paid'): ?>
        <span class="badge text-bg-success mn-paidmark"><i class="fa-solid fa-circle-check me-1"></i>Invoice Paid</span>
    <?php endif; ?>
    <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/invoices/' . $id . '/payments/new')) ?>"><i class="fa-solid fa-circle-plus me-1"></i>Add Payment</a>
    <details class="mn-menu"><summary class="btn btn-outline-secondary mn-btn"><i class="fa-solid fa-wrench me-1"></i>Actions <i class="fa-solid fa-chevron-down ms-1"></i></summary>
        <div class="mn-menu-list">
            <a href="<?= e(admin_url('money/invoices/' . $id . '/edit')) ?>"><i class="fa-solid fa-pen"></i>Edit Invoice</a>
            <a href="<?= e(admin_url('money/invoices/' . $id . '/pdf')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i>View PDF</a>
            <a href="<?= e(admin_url('money/invoices/' . $id . '/pdf?print=1')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-download"></i>Save As PDF</a>
            <?php if ($client): ?><a href="<?= e(admin_url('money/contacts/' . (int) $client['id'] . '/statement')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-lines"></i>Client Statement</a><?php endif; ?>
            <?php if ($state === 'paid'): ?><a href="<?= e(\AP\Modules\Money\Pay::url($inv)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i>Paid Invoice</a><?php endif; ?>
            <a href="<?= e(admin_url('money/invoices/' . $id . '/email')) ?>"<?= $modal ?>><i class="fa-solid fa-paper-plane"></i><?= $state === 'paid' ? 'Email Paid Invoice' : 'Email Invoice' ?></a>
            <?php if ($state === 'overdue' || ($state === 'unpaid' && !empty($inv['emailed_at']))): ?><a href="<?= e(admin_url('money/invoices/' . $id . '/email?kind=reminder')) ?>"<?= $modal ?>><i class="fa-solid fa-bell"></i>Send Reminder</a><?php endif; ?>
            <a href="<?= e(\AP\Modules\Money\Pay::url($inv)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i>Open Pay Page</a>
            <button type="button" data-copy="<?= e(\AP\Modules\Money\Pay::url($inv)) ?>"><i class="fa-solid fa-link"></i>Copy Pay Link</button>
            <?php foreach (['sent' => ['Mark As Sent', 'fa-paper-plane'], 'draft' => ['Mark As Draft', 'fa-file-pen'], 'cancelled' => ['Cancel Invoice', 'fa-ban']] as $k => [$label, $ic]): if ($inv['status'] === $k) { continue; } ?>
                <form method="post" action="<?= e(admin_url('money/invoices/' . $id . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= e($k) ?>"><button type="submit"><i class="fa-solid <?= e($ic) ?>"></i><?= e($label) ?></button></form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(admin_url('money/invoices/' . $id . '/delete')) ?>"><?= csrf_field() ?><button type="submit" class="is-danger" data-confirm="Delete this invoice?"><i class="fa-solid fa-trash"></i>Delete Invoice</button></form>
        </div></details>
<?php else: ?>
    <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/invoices/' . $id . '/pdf?print=1')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1"></i>Save As PDF</a>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<div class="mn-grid mn-g-invoice">
    <section class="mn-card mn-paper">
        <div class="mn-paper-top">
            <div class="mn-biz">
                <?php if ($biz['logo'] !== ''): ?><img src="<?= e($biz['logo']) ?>" alt="<?= e($biz['name']) ?>" class="mn-logo"><?php endif; ?>
                <strong><?= e($biz['name']) ?></strong>
                <?php foreach (['address', 'phone', 'email', 'website'] as $f): if ($biz[$f] !== ''): ?><span><?= $f === 'phone' ? 'Phone: ' : ($f === 'email' ? 'Email: ' : ($f === 'website' ? 'Website: ' : '')) ?><?= nl2br(e($biz[$f])) ?></span><?php endif; endforeach; ?>
                <?php if ($vatShow && \AP\Modules\Money\Vat::number() !== ''): ?><span>VAT number: <?= e(\AP\Modules\Money\Vat::number()) ?></span><?php endif; ?>
            </div>
            <div class="mn-inv-meta"><h1><?= e((string) $inv['number']) ?></h1>
                <span>Bill date: <?= e(Money::showDate((string) $inv['issue_date'])) ?></span>
                <?php if ($inv['due_date']): ?><span>Due date: <?= e(Money::showDate((string) $inv['due_date'])) ?></span><?php endif; ?></div>
        </div>
        <div class="mn-billto"><small>Bill to</small><strong><?= e((string) ($client['name'] ?? 'No client')) ?></strong>
            <?php if ($client): ?><span><?= e(implode(', ', array_filter([(string) $client['address'], (string) $client['city'], (string) $client['postcode']]))) ?></span><?php endif; ?></div>

        <div class="table-responsive"><table class="table mn-items align-middle" id="items" data-order-url="<?= e(admin_url('money/invoices/' . $id . '/items/order')) ?>" data-token="<?= e(csrf_token()) ?>">
            <thead><tr><th style="width:28px"></th><th>Item</th><th class="text-end">Quantity</th><th class="text-end">Rate</th><?php if ($vatShow): ?><th class="text-end">VAT</th><?php endif; ?><th class="text-end">Total</th><th class="mn-noprint" style="width:84px"></th></tr></thead><tbody>
            <?php foreach ($items as $it): ?>
                <tr data-id="<?= (int) $it['id'] ?>"<?= $editable ? ' draggable="true"' : '' ?>>
                    <td class="mn-handle mn-noprint"><?php if ($editable): ?><i class="fa-solid fa-grip-vertical" aria-hidden="true" title="Drag to reorder"></i><?php endif; ?></td>
                    <td><strong><?= e((string) $it['title']) ?></strong><?php if ($it['description']): ?><div class="small text-muted"><?= nl2br(e((string) $it['description'])) ?></div><?php endif; ?></td>
                    <td class="text-end text-nowrap"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 3), '0'), '.')) ?> <?= e((string) $it['unit']) ?></td>
                    <td class="text-end text-nowrap"><?= $g($it['rate']) ?></td><?php if ($vatShow): ?><td class="text-end text-nowrap"><?= e(\AP\Modules\Money\Vat::lineLabel((string) ($it['vat_rate'] ?? ''), (string) $inv['issue_date'])) ?></td><?php endif; ?><td class="text-end text-nowrap"><?= $g($it['total']) ?></td>
                    <td class="text-end mn-noprint text-nowrap"><?php if ($editable): ?>
                        <a class="mn-icon" href="<?= e(admin_url('money/invoices/' . $id . '/items/' . (int) $it['id'])) ?>" aria-label="Change item"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" action="<?= e(admin_url('money/invoices/' . $id . '/items/' . (int) $it['id'] . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><button class="mn-icon" type="submit" data-confirm="Remove this item?" aria-label="Remove item"><i class="fa-solid fa-xmark"></i></button></form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="<?= $vatShow ? 7 : 6 ?>" class="text-muted py-4">No items yet. Add what you charged for.</td></tr><?php endif; ?>
            </tbody></table></div>
        <?php if ($editable): ?><a class="btn btn-outline-secondary mn-btn mn-noprint mt-3" href="<?= e(admin_url('money/invoices/' . $id . '/items/new')) ?>"><i class="fa-solid fa-circle-plus me-1"></i>Add Item</a><?php endif; ?>

        <div class="mn-totals">
            <div><span>Sub total</span><strong><?= $g($inv['subtotal']) ?></strong></div>
            <div><span>Discount</span><strong>-<?= $g($inv['discount']) ?>
                <?php if ($editable): ?><a class="mn-icon mn-noprint" href="#discount" data-discount-open aria-label="Change discount"><i class="fa-solid fa-pen"></i></a><?php endif; ?></strong></div>
            <?php if ($editable): ?><form method="post" action="<?= e(admin_url('money/invoices/' . $id . '/discount')) ?>" class="mn-discount mn-noprint" id="discount" hidden><?= csrf_field() ?>
                <input class="form-control" name="discount" value="<?= e(number_format((float) $inv['discount'], 2, '.', '')) ?>" inputmode="decimal" aria-label="Discount in pounds"><button class="btn btn-primary btn-sm mn-btn" type="submit">Save</button></form><?php endif; ?>
            <?php if ($vatShow): ?><div><span>VAT</span><strong><?= $g($inv['tax']) ?></strong></div><?php endif; ?>
            <div class="is-big"><span>Total</span><strong><?= $g($inv['total']) ?></strong></div>
            <div><span>Paid</span><strong><?= $g($paid) ?></strong></div>
            <div class="is-big"><span>Balance due</span><strong><?= $g($balance) ?></strong></div>
        </div>
        <?php if ($inv['note']): ?><p class="mn-paper-note"><?= nl2br(e((string) $inv['note'])) ?></p><?php endif; ?>
        <?php if ($biz['footer'] !== ''): ?><p class="mn-paper-note"><?= e($biz['footer']) ?></p><?php endif; ?>
    </section>

    <aside class="mn-side mn-noprint mn-stack">
        <section class="mn-card">
            <div class="mn-list">
                <div><span>Client</span><strong><?= e((string) ($client['name'] ?? '—')) ?></strong></div>
                <div><span>Status</span><strong><?= Ui::badge(InvoiceController::STATES[$state] ?? $state, $tone[$state] ?? 'secondary') ?></strong></div>
                <?= \AP\Modules\Money\CustomFields::detail('invoice', $id) ?>
                <?php if ((int) $inv['recurring'] === 1): ?><div><span>Repeats</span><strong>Every <?= (int) $inv['repeat_every'] ?> <?= e((string) \AP\Modules\Money\Recurring::UNITS[$inv['repeat_unit']] ?? '') ?></strong></div>
                <div><span>Next invoice</span><strong><?= e(Money::showDate((string) $inv['next_date'])) ?></strong></div>
                <div><span>Made so far</span><strong><?= (int) $inv['cycles_done'] ?><?= (int) $inv['max_cycles'] > 0 ? ' of ' . (int) $inv['max_cycles'] : '' ?></strong></div><?php endif; ?>
                <?php if ((int) $inv['parent_id'] > 0): ?><div><span>Made from</span><strong><a href="<?= e(admin_url('money/invoices/' . (int) $inv['parent_id'])) ?>">its repeating invoice</a></strong></div><?php endif; ?>
                <div><span>Income source</span><strong><?= e((string) $stream) ?: '—' ?></strong></div>
                <div><span>Total</span><strong><?= $g($inv['total']) ?></strong></div>
                <div class="mn-total"><span>Balance due</span><strong><?= $g($balance) ?></strong></div>
            </div>
        </section>
        <section class="mn-card">
            <header><div><h2>Payments</h2></div><?php if ($canEdit): ?><a class="btn btn-sm btn-primary mn-btn" href="<?= e(admin_url('money/invoices/' . $id . '/payments/new')) ?>"><i class="fa-solid fa-plus me-1"></i>Add</a><?php endif; ?></header>
            <ul class="mn-rows">
                <?php foreach ($payments as $p): ?>
                    <li><a href="<?= e(admin_url('money/income/' . (int) $p['id'])) ?>"><span><?= e(Money::showDate((string) $p['income_date'])) ?><small><?= e((string) ($p['payment_method'] ?: 'Payment')) ?></small></span><strong class="mn-pos"><?= $g($p['amount']) ?></strong></a></li>
                <?php endforeach; ?>
                <?php if (!$payments): ?><li class="mn-empty">No payments yet.</li><?php endif; ?>
            </ul>
        </section>
    </aside>
</div>
</div>
<?php $this->endSection(); ?>
