<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$sep = $kind === 'income' ? 'income' : 'expenses';
$top = $groups ? max(array_column($groups, 'amount')) : 0;
?>
<div class="mn-form mn-bdp is-<?= $kind === 'income' ? 'income' : 'costs' ?>">
    <header class="mn-bdp-head">
        <div><h2 class="mn-form-title"><?= $kind === 'income' ? 'Income' : 'Expense' ?> Breakdown</h2><div class="mn-bdp-sub"><?= e($month) ?></div></div>
    </header>
    <?php if (!$groups): ?>
        <p class="text-muted">Nothing <?= $kind === 'income' ? 'came in' : 'counted as a cost' ?> in this time.</p>
    <?php else: ?>
        <div class="mn-bdp-list" tabindex="0" aria-label="What <?= $kind === 'income' ? 'income' : 'costs' ?> are made of">
        <?php foreach ($groups as $gr): $w = $top > 0 ? max(1.5, round($gr['amount'] / $top * 100, 1)) : 0; ?>
            <div class="mn-bdp-row">
                <div class="mn-bdp-line"><span class="mn-bdp-name"><?php if ($gr['link'] !== ''): ?><a href="<?= e(admin_url('money/' . $sep) . '?year=all&' . $range . '&' . $gr['link']) ?>" title="<?= (int) $gr['count'] ?> <?= $gr['count'] === 1 ? 'entry' : 'entries' ?>"><?= e($gr['label']) ?></a><?php else: ?><?= e($gr['label']) ?><?php endif; ?></span><strong class="mn-bdp-amt"><?= e(Money::gbp($gr['amount'])) ?></strong></div>
                <div class="mn-bdp-bar"><span style="width:<?= $w ?>%"></span></div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="mn-bdp-total"><span>Total <?= $kind === 'income' ? 'Income' : 'Expenses' ?></span><strong><?= e(Money::gbp($total)) ?></strong></div>
        <?php if ($kind === 'costs' && $skipped > 0): ?><p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-circle-info me-1"></i><?= e(Money::gbp($skipped)) ?> more was spent in this time but does not count as a business cost (for example costs covered by the mileage rate, or things that are not allowable).</p><?php endif; ?>
    <?php endif; ?>
    <div class="mn-actions"><a class="btn btn-primary mn-btn" href="<?= e($list) ?>"><i class="fa-solid fa-list me-1"></i>See The <?= $kind === 'income' ? 'Payments' : 'Expenses' ?></a>
        <?php if (!empty($modal)): ?><button class="btn btn-outline-secondary mn-btn" type="button" data-modal-close>Close</button><?php endif; ?></div>
</div>
