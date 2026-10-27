<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Needs A Look</h2><p>Tidy these up for clean records</p></div></header>
        <ul class="mn-todo">
            <?php $any = false;
            if ($invoices['rows']['overdue']['count'] > 0): $any = true; ?><li class="is-bad"><i class="fa-solid fa-clock" aria-hidden="true"></i><span><?= (int) $invoices['rows']['overdue']['count'] ?> overdue invoice(s) worth <?= $g($invoices['rows']['overdue']['amount']) ?></span><a href="<?= e(admin_url('money/invoices?year=all&state=overdue')) ?>">Open</a></li><?php endif;
            if ($uncat > 0): $any = true; ?><li class="is-warn"><i class="fa-solid fa-tags" aria-hidden="true"></i><span><?= (int) $uncat ?> expense(s) have no category in <?= e($year) ?></span><a href="<?= e(admin_url('money/expenses?year=' . $year . '&category=-1')) ?>">Open</a></li><?php endif;
            if ($noReceipt > 0): $any = true; ?><li class="is-info"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span><?= (int) $noReceipt ?> expense(s) of <?= $g($receiptOver) ?> or more have no receipt</span><a href="<?= e(admin_url('money/expenses?year=' . $year)) ?>">Open</a></li><?php endif;
            if (($o['not_costs']['home_use'] ?? 0) > 0): $any = true; ?><li class="is-warn"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Use-of-home costs of <?= $g($o['not_costs']['home_use']) ?> are not counted yet. Choose a share or tick them as costs.</span><a href="<?= e(admin_url('money/categories')) ?>">Categories</a></li><?php endif;
            if (\AP\Modules\Money\Mileage::vehicles() && !Money::db()->table('money_trips')->whereNull('deleted_at')->where('trip_date', '>=', $o['from'])->where('trip_date', '<=', $o['to'])->exists()): $any = true; ?><li class="is-warn"><i class="fa-solid fa-route" aria-hidden="true"></i><span>No miles logged in <?= e($year) ?> yet</span><a href="<?= e(admin_url('money/trips')) ?>">Mileage Log</a></li><?php endif;
            if (($o['mileage'] ?? 0) > 0 || ($o['not_costs']['mileage_covered'] ?? 0) > 0): ?><li class="is-info"><i class="fa-solid fa-car-side" aria-hidden="true"></i><span>Mileage rate adds <?= $g($o['mileage']) ?> to costs<?= ($o['not_costs']['mileage_covered'] ?? 0) > 0 ? '; ' . $g($o['not_costs']['mileage_covered']) . ' of those vehicles\' running costs are left out' : '' ?></span><a href="<?= e(admin_url('money/vehicles')) ?>">Vehicles</a></li><?php endif;
            if (!$any): ?><li class="is-good"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Everything looks tidy.</span></li><?php endif; ?>
            <li class="is-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Costs are your allowable costs only. Pension, personal spending and equipment are left out.</span><a href="<?= e(admin_url('money/categories')) ?>">Categories</a></li>
        </ul>
    </section>
