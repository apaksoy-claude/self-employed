<?php declare(strict_types=1);
/** Money > Demo Data. @var \AP\Core\View $this */
$this->extend('admin::layout');
$names = ['money_invoices' => 'Invoices', 'money_invoice_items' => 'Invoice lines', 'money_income' => 'Payments', 'money_expenses' => 'Expenses', 'money_contacts' => 'Clients and suppliers',
    'money_trips' => 'Mileage trips', 'money_vehicles' => 'Vehicles', 'money_assets' => 'Equipment', 'money_categories' => 'Categories', 'money_streams' => 'Income streams', 'money_stock_items' => 'Stock items', 'money_stock_moves' => 'Stock movements'];
?>
<?php $this->section('content'); ?>
<div class="mn-page dm-page">
    <?php if (!$loaded): ?>
    <section class="mn-card dm-card">
        <header><div><h2><i class="fa-solid fa-flask" aria-hidden="true"></i>Try Money With Demo Data</h2><p>Adds about a year of made-up clients, invoices, payments, expenses, mileage and equipment, so every page has something to show. Names end in "(Demo)" and invoice numbers start with DEMO.</p></div></header>
        <form method="post" action="<?= e(admin_url('money/demo/load')) ?>" class="dm-form"><?= csrf_field() ?>
            <div class="ap-field"><label class="form-label" for="dm_trade">Made For</label>
                <select class="form-select" id="dm_trade" name="trade"><?php foreach ($packs as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $trade ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-primary mn-btn" type="submit" data-loading="Adding…"><i class="fa-solid fa-flask me-1" aria-hidden="true"></i>Add Demo Data</button>
        </form>
        <p class="mn-foot">Your own records are never changed. When you are done, Delete All Demo Data takes away exactly what was added.</p>
    </section>
    <?php else: ?>
    <section class="mn-card dm-card is-on">
        <header><div><h2><i class="fa-solid fa-flask" aria-hidden="true"></i>Demo Data Is In Use</h2><p>These made-up records are mixed in with yours. Delete them all with one button before you start for real.</p></div></header>
        <ul class="dm-counts"><?php foreach ($names as $t => $label): if (empty($counts[$t])) { continue; } ?><li><strong><?= (int) $counts[$t] ?></strong><span><?= e($label) ?></span></li><?php endforeach; ?></ul>
        <form method="post" action="<?= e(admin_url('money/demo/clear')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e(admin_url('money/demo')) ?>">
            <button class="btn btn-danger mn-btn" type="submit" data-confirm="Delete all demo data? Your own records stay as they are."><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Delete All Demo Data</button></form>
    </section>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
