<?php declare(strict_types=1);
/** The stock count page body, also sent back after saving in the background. @var \AP\Core\View $this */
use AP\Modules\Money\Money;
use AP\Modules\Money\Stock;
?>
<div class="mn-page" data-stock-count>
    <p class="text-muted">Count what is on the shelf and type it in. Any difference is saved as a stock count on that day, so the records match what you have.
        <?= $basis === 'traditional' ? 'With traditional accounting, count on the last day of the tax year (' . e(Money::showDate($yearEnd)) . '): the value of what is left is your closing stock.' : '' ?></p>
    <form method="post" action="<?= e(admin_url('money/stock/count')) ?>" data-bg-save data-bg-swap="[data-stock-count]" novalidate><?= csrf_field() ?>
        <section class="mn-card">
            <header><div><h2><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>Stock On <?= e(Money::showDate($date)) ?></h2><p>Value at cost on that day: <strong><?= e(Money::gbp($value)) ?></strong></p></div>
                <div class="ap-field" style="min-width: 12rem"><label class="form-label" for="sc_date">Counted On</label><input class="form-control" id="sc_date" name="date" value="<?= e(Money::showDate($date)) ?>" data-date autocomplete="off" inputmode="numeric"></div></header>
            <?php if (!$items): ?><p class="text-muted mb-0">No stock items yet.</p><?php else: ?>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                <thead><tr><th>Item</th><th class="text-end">Records Say</th><th style="width: 11rem">You Counted</th></tr></thead><tbody>
                <?php foreach ($items as $i): ?><tr><td><strong><?= e((string) $i['name']) ?></strong><?php if ($i['sku']): ?> <span class="small text-muted"><?= e((string) $i['sku']) ?></span><?php endif; ?></td>
                    <td class="text-end"><?= e(Stock::qty($i['qty'])) ?></td>
                    <td><div class="ap-field m-0"><input class="form-control form-control-sm" name="counted[<?= (int) $i['id'] ?>]" inputmode="decimal" placeholder="<?= e(Stock::qty($i['qty'])) ?>" aria-label="Counted for <?= e((string) $i['name']) ?>"<?= $canEdit ? '' : ' disabled' ?>></div></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <?php if ($canEdit && $items): ?><div class="vt-file-go"><button class="btn btn-primary mn-btn" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span>Save Count</span></button><span class="small text-muted">Leave a box empty when it matches.</span></div><?php endif; ?>
        </section>
    </form>
</div>
