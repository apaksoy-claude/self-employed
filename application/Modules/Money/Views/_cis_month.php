<?php declare(strict_types=1);
/** One tax month on Money > CIS, also sent back after a statement or return is marked. @var \AP\Core\View $this */
use AP\Modules\Money\Cis;
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
$s = $state[$month];
$sum = static function (array $rows): array {
    $t = ['gross' => 0.0, 'materials' => 0.0, 'deducted' => 0.0, 'paid' => 0.0];
    foreach ($rows as $r) {
        foreach ($t as $k => $v) {
            $t[$k] = round($v + $r[$k], 2);
        }
    }
    return $t;
};
$ended = $range[1] < $today;
?>
<div class="vt-main" data-vat-period>
    <section class="mn-card vt-top">
        <header><div><h2><?= e(Cis::monthLabel($month)) ?><?php if ($s['label'] !== ''): ?> <span class="mn-chip <?= e($s['tone']) ?>"><?= e($s['label']) ?></span><?php endif; ?></h2>
            <p><?php if (!$ended): ?>This tax month runs until <?= e(Money::showDate($range[1])) ?>.<?php else: ?>Ended <?= e(Money::showDate($range[1])) ?>.<?php endif; ?>
                <?php if ($con): ?> Your return is due by <strong><?= e(Money::showDate($due['file'])) ?></strong> and the payment by <strong><?= e(Money::showDate($due['pay'])) ?></strong>.<?php endif; ?></p></div></header>
    </section>

    <?php if ($sub): $t = $sum($in); ?>
    <section class="mn-card">
        <header><div><h2><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>Paid To You</h2><p>From your invoices and payments in this tax month. Each contractor should give you a statement by the 19th: tick it when it matches.</p></div></header>
        <?php if (!$in): ?><p class="text-muted mb-0">No CIS payments in this tax month.</p><?php else: ?>
        <div class="table-responsive"><table class="table mn-table align-middle mb-0">
            <thead><tr><th>Contractor</th><th class="text-end">Gross</th><th class="text-end">Materials</th><th class="text-end">Taken Off</th><th class="text-end">Paid To You</th><th class="text-end">Statement</th></tr></thead><tbody>
            <?php foreach ($in as $cid => $r): $liable = $r['gross'] - $r['materials']; $pc = $liable > 0 ? $r['deducted'] / $liable * 100 : 0.0; $exp = Cis::expected($r['gross'], $r['materials'], $rate);
                $got = isset($statements[$month . ':' . $cid]); ?>
                <tr><td><strong><?= e($names[$cid] ?? 'No contact') ?></strong><?php if (abs($exp - $r['deducted']) > 0.05 && $liable > 0): ?><div class="small text-warning-emphasis"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>About <?= e((string) round($pc)) ?>% taken off; you expect <?= e($rate) ?>%</div><?php endif; ?></td>
                    <td class="text-end"><?= $g($r['gross']) ?></td><td class="text-end"><?= $g($r['materials']) ?></td><td class="text-end"><?= $g($r['deducted']) ?></td><td class="text-end"><?= $g($r['paid']) ?></td>
                    <td class="text-end"><?php if ($canEdit): ?>
                        <form method="post" action="<?= e(admin_url('money/cis/mark')) ?>" class="d-inline" data-bg-save data-bg-swap="[data-vat-period]"><?= csrf_field() ?>
                            <input type="hidden" name="role" value="sub"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="contact_id" value="<?= (int) $cid ?>"><input type="hidden" name="undo" value="<?= $got ? '1' : '0' ?>">
                            <button class="btn btn-sm <?= $got ? 'btn-success' : 'btn-outline-secondary' ?> mn-btn" type="submit" data-bg-go><i class="fa-solid <?= $got ? 'fa-circle-check' : 'fa-circle' ?> me-1" aria-hidden="true"></i><span><?= $got ? 'Received' : 'Tick When Received' ?></span></button>
                        </form>
                    <?php else: ?><?= $got ? 'Received' : '—' ?><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody><tfoot><tr><td>Total</td><td class="text-end"><?= $g($t['gross']) ?></td><td class="text-end"><?= $g($t['materials']) ?></td><td class="text-end"><?= $g($t['deducted']) ?></td><td class="text-end"><?= $g($t['paid']) ?></td><td></td></tr></tfoot></table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($con): $t = $sum($out); ?>
    <section class="mn-card">
        <header><div><h2><i class="fa-solid fa-people-carry-box" aria-hidden="true"></i>Paid To Subcontractors</h2><p>From expenses paid to subcontractors in this tax month. Give each one their statement by the 19th.</p></div></header>
        <?php if (!$out): ?><p class="text-muted mb-0">No payments to subcontractors in this tax month.<?= ($returnsFrom ?? null) !== null && $month >= $returnsFrom ? ' If that is right, you may still need to file a nil return unless you have told HMRC you are not paying anyone.' : '' ?></p><?php else: ?>
        <div class="table-responsive"><table class="table mn-table align-middle mb-0">
            <thead><tr><th>Subcontractor</th><th class="text-end">Gross</th><th class="text-end">Materials</th><th class="text-end">Taken Off</th><th class="text-end">Paid To Them</th><th class="text-end">Statement</th></tr></thead><tbody>
            <?php foreach ($out as $cid => $r): ?>
                <tr><td><strong><?= e($names[$cid] ?? 'No contact') ?></strong></td><td class="text-end"><?= $g($r['gross']) ?></td><td class="text-end"><?= $g($r['materials']) ?></td><td class="text-end"><?= $g($r['deducted']) ?></td><td class="text-end"><?= $g($r['paid']) ?></td>
                    <td class="text-end"><?php if ($cid > 0): ?><a class="btn btn-sm btn-outline-secondary mn-btn" href="<?= e(admin_url('money/cis/statement?month=' . $month . '&contact=' . (int) $cid)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-lines me-1" aria-hidden="true"></i>Statement</a><?php else: ?><span class="small text-muted">Choose who was paid</span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody><tfoot><tr><td>Total</td><td class="text-end"><?= $g($t['gross']) ?></td><td class="text-end"><?= $g($t['materials']) ?></td><td class="text-end"><?= $g($t['deducted']) ?></td><td class="text-end"><?= $g($t['paid']) ?></td><td></td></tr></tfoot></table></div>
        <div class="vt-net mt-3"><span>Pay HMRC By <?= e(Money::showDate($due['pay'])) ?></span><strong><?= $g($t['deducted']) ?></strong><small>The CIS you took off this month, for your monthly return</small></div>
        <?php endif; ?>
    </section>

    <section class="mn-card vt-file">
        <header><div><h2><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Monthly Return</h2><p><?= $filed ? 'Filed on ' . e(Money::showDate((string) $filed['done_on'])) . ($filed['reference'] ? ' · reference ' . e((string) $filed['reference']) : '') . '.' : 'File it with HMRC, then note it here.' ?></p></div></header>
        <?php if (!$ended): ?>
            <p class="text-muted mb-0">You can mark the return as filed once the tax month ends on <?= e(Money::showDate($range[1])) ?>.</p>
        <?php elseif ($canEdit): ?>
            <form method="post" action="<?= e(admin_url('money/cis/mark')) ?>" data-bg-save data-bg-swap="[data-vat-period]" novalidate><?= csrf_field() ?>
                <input type="hidden" name="role" value="contractor"><input type="hidden" name="month" value="<?= e($month) ?>">
                <div class="row">
                    <div class="col-md-4 ap-field"><label class="form-label" for="cs_done_on">Filed On</label>
                        <input class="form-control" id="cs_done_on" name="done_on" value="<?= e(Money::showDate((string) ($filed['done_on'] ?? $today))) ?>" data-date autocomplete="off" inputmode="numeric" placeholder="19/05/2026"></div>
                    <div class="col-md-8 ap-field"><label class="form-label" for="cs_reference">HMRC Reference</label>
                        <input class="form-control" id="cs_reference" name="reference" value="<?= e((string) ($filed['reference'] ?? '')) ?>" maxlength="60" placeholder="From HMRC's confirmation"></div>
                </div>
                <div class="vt-file-go"><button class="btn btn-primary mn-btn" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span><?= $filed ? 'Save Changes' : 'Mark As Filed' ?></span></button></div>
            </form>
            <?php if ($filed): ?>
                <form method="post" action="<?= e(admin_url('money/cis/mark')) ?>" class="vt-unfile" data-bg-save data-bg-swap="[data-vat-period]"><?= csrf_field() ?>
                    <input type="hidden" name="role" value="contractor"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="undo" value="1">
                    <button class="btn btn-link btn-sm text-danger px-0" type="submit" data-bg-go data-confirm="Take the filed mark off this return?"><span>Not Filed After All</span></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
