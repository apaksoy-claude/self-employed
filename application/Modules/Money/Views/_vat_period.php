<?php declare(strict_types=1);
/** One VAT period on Money > VAT, also sent back after it is marked as filed. @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\VatController;
use AP\Modules\Money\Money;
use AP\Modules\Money\Vat;

$g = static fn($n) => e(Money::gbp($n));
$b = $boxes;
$net = (float) $b['box5'];
$chips = ['filed' => ['Filed', 'mn-chip-good'], 'due' => ['To File', 'mn-chip-warn'], 'late' => ['Late', 'mn-chip-bad'], 'running' => ['Still Running', 'mn-chip-info']];
[$cl, $cc] = $chips[$state];
$q = 'period=' . rawurlencode($p['from']);
$whole = ['box6', 'box7', 'box8', 'box9'];
?>
<div class="vt-main mn-stack" data-vat-period>
    <section class="mn-card vt-top">
        <header><div><h2><?= e($p['label']) ?> <span class="mn-chip <?= e($cc) ?>"><?= e($cl) ?></span></h2>
            <p><?php if ($state === 'running'): ?>The period ends on <?= e(Money::showDate($p['to'])) ?>. The figures grow as you add records.
                <?php elseif ($state === 'filed'): ?>Filed on <?= e(Money::showDate((string) $record['filed_on'])) ?><?= $record['reference'] ? ' · reference ' . e((string) $record['reference']) : '' ?>.
                <?php else: ?>The return and any payment are due by <strong><?= e(Money::showDate($p['due'])) ?></strong><?= $state === 'late' ? ', which has passed' : '' ?>.<?php endif; ?></p></div>
            <div class="vt-actions">
                <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/vat/csv?' . $q)) ?>"><i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>Download CSV</a>
                <button class="btn btn-outline-secondary mn-btn mn-noprint" type="button" data-print><i class="fa-solid fa-print me-1" aria-hidden="true"></i>Print</button>
            </div></header>
        <div class="vt-net<?= $net < 0 ? ' is-back' : '' ?>"><span><?= $net < 0 ? 'HMRC Owes You' : 'To Pay HMRC' ?></span><strong><?= $g(abs($net)) ?></strong>
            <small><?= $b['flat'] ? 'Flat Rate Scheme' : 'Standard scheme' ?><?= $b['cash'] ? ' · cash accounting: counted on the days money came in and went out' : ' · counted on invoice and receipt dates' ?></small></div>
        <?php if ($changed): ?><div class="vt-warn"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Records in this period changed after you filed.</strong> The boxes show the figures now, with what you filed below each changed one. Small mistakes can usually be put right on your next return.</div></div><?php endif; ?>
    </section>

    <section class="mn-card vt-boxes">
        <header><div><h2><i class="fa-solid fa-table-list" aria-hidden="true"></i>The Nine Boxes</h2><p>Type these into your VAT software. Boxes 6 to 9 are whole pounds.</p></div></header>
        <ol class="vt-list">
            <?php foreach (VatController::BOXES as $k => [$label, $help]): $n = (int) substr($k, 3); ?>
                <li class="<?= $k === 'box5' ? 'is-key' : '' ?><?= in_array($k, ['box2', 'box8', 'box9'], true) ? ' is-quiet' : '' ?>">
                    <b class="vt-no"><?= $n ?></b>
                    <div><strong><?= e($label) ?></strong><small><?= e($help) ?><?php if ($b['flat'] && $k === 'box1'): ?> On the Flat Rate Scheme this is your flat rate of your takings with VAT.<?php elseif ($b['flat'] && $k === 'box6'): ?> On the Flat Rate Scheme, with VAT included.<?php elseif ($b['flat'] && $k === 'box4'): ?> On the Flat Rate Scheme only equipment of £2,000 or more with VAT.<?php endif; ?></small></div>
                    <span class="vt-amt"><?= in_array($k, $whole, true) ? e('£' . number_format((float) $b[$k], 0)) : $g($k === 'box5' ? abs((float) $b[$k]) : $b[$k]) ?>
                        <?php if (isset($changed[$k])): ?><em>Filed: <?= in_array($k, $whole, true) ? e('£' . number_format($changed[$k], 0)) : $g($changed[$k]) ?></em><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="mn-card vt-file">
        <header><div><h2><i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= $record ? 'Filed' : 'After You File' ?></h2>
            <p><?= $record ? 'The figures you filed are kept with this note.' : 'Note the day you filed and the reference HMRC gave. It keeps a copy of the figures as they are now.' ?></p></div></header>
        <?php if ($state === 'running'): ?>
            <p class="text-muted mb-0">You can mark this return as filed once the period ends on <?= e(Money::showDate($p['to'])) ?>.</p>
        <?php elseif (!$canEdit): ?>
            <p class="text-muted mb-0"><?= $record ? 'Filed on ' . e(Money::showDate((string) $record['filed_on'])) : 'Not marked as filed yet.' ?></p>
        <?php else: ?>
            <form method="post" action="<?= e(admin_url('money/vat/file')) ?>" class="vt-fileform" data-bg-save data-bg-swap="[data-vat-period]" novalidate><?= csrf_field() ?>
                <input type="hidden" name="period" value="<?= e($p['from']) ?>">
                <div class="row">
                    <div class="col-md-4 ap-field"><label class="form-label" for="vt_filed_on">Filed On</label>
                        <input class="form-control" id="vt_filed_on" name="filed_on" value="<?= e(Money::showDate((string) ($record['filed_on'] ?? Money::today()))) ?>" data-date autocomplete="off" inputmode="numeric" placeholder="27/10/2026"></div>
                    <div class="col-md-8 ap-field"><label class="form-label" for="vt_reference">HMRC Reference</label>
                        <input class="form-control" id="vt_reference" name="reference" value="<?= e((string) ($record['reference'] ?? '')) ?>" maxlength="60" placeholder="From the confirmation in your VAT software"></div>
                    <div class="col-12 ap-field mt-2"><label class="form-label" for="vt_note">Note</label>
                        <input class="form-control" id="vt_note" name="note" value="<?= e((string) ($record['note'] ?? '')) ?>" maxlength="2000" placeholder="Paid by direct debit, for example"></div>
                </div>
                <div class="vt-file-go">
                    <button class="btn btn-primary mn-btn" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span><?= $record ? 'Save Changes' : 'Mark As Filed' ?></span></button>
                    <?php if ($changed): ?><span class="small text-muted">Saving again keeps the figures as they are now.</span><?php endif; ?>
                </div>
            </form>
            <?php if ($record): ?>
                <form method="post" action="<?= e(admin_url('money/vat/unfile')) ?>" class="vt-unfile" data-bg-save data-bg-swap="[data-vat-period]"><?= csrf_field() ?>
                    <input type="hidden" name="period" value="<?= e($p['from']) ?>">
                    <button class="btn btn-link btn-sm text-danger px-0" type="submit" data-bg-go data-confirm="Take the filed mark off this return? The copy of the figures you filed is removed."><span>Not Filed After All</span></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="mn-card vt-detail">
        <header><div><h2><i class="fa-solid fa-magnifying-glass-dollar" aria-hidden="true"></i>What Is Behind The Figures</h2><p>Every sale and every cost with VAT reclaimed in this period.</p></div></header>
        <?php foreach (['sales' => ['Sales', 'VAT'], 'buys' => ['Costs With VAT Reclaimed', 'VAT Reclaimed']] as $k => [$title, $col]): $list = $b[$k]; ?>
            <details class="vt-lines"<?= count($list) <= 8 && $list ? ' open' : '' ?>>
                <summary><span><?= e($title) ?></span><em class="mn-chip mn-chip-mute"><?= count($list) ?></em></summary>
                <?php if (!$list): ?><p class="text-muted small mb-0">None in this period.</p><?php else: ?>
                <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                    <thead><tr><th>Date</th><th>What</th><th class="text-end">Amount</th><th class="text-end"><?= e($col) ?></th></tr></thead><tbody>
                    <?php foreach ($list as $r): ?><tr><td class="text-nowrap"><?= e(Money::showDate($r['date'])) ?></td><td><?= e($r['what']) ?></td><td class="text-end"><?= $g($r['gross']) ?></td><td class="text-end"><?= $g($r['vat']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </section>
</div>
