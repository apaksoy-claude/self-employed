<?php declare(strict_types=1);
/** Bank Import: every line of the statement, with what it becomes. @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$opts = ['out' => ['0' => 'No Category'], 'in' => ['0' => 'No Category']];
foreach ($cats as $id => $c) {
    $opts[$c['kind'] === 'income' ? 'in' : 'out'][(string) $id] = $c['name'];
}
$chip = ['new' => ['New', 'mn-chip-info'], 'done' => ['Already Added', 'mn-chip-good'], 'match' => ['Check', 'mn-chip-warn']];
$total = ['in' => 0.0, 'out' => 0.0];
foreach ($rows as $r) {
    $total[$r['dir']] += abs((float) $r['amount']);
}
?>
<?php $this->section('content'); ?>
<div class="mn-page bk-page">
    <div class="mn-grid mn-g4">
        <section class="mn-card mn-kpi"><span>Lines</span><strong><?= count($rows) ?></strong><small><?= e((string) $s['name']) ?><?= !empty($s['account']) ? ' · ' . e((string) $s['account']) : '' ?></small></section>
        <section class="mn-card mn-kpi is-main"><span>New</span><strong><?= (int) $count['new'] ?></strong><small>Ticked to add</small></section>
        <section class="mn-card mn-kpi"><span>Already Added</span><strong><?= (int) $count['done'] ?></strong><small>From an earlier statement</small></section>
        <section class="mn-card mn-kpi"><span>To Check</span><strong><?= (int) $count['match'] ?></strong><small>Look like records added by hand</small></section>
    </div>
    <form method="post" action="<?= e(admin_url('money/bank/' . $token . '/import')) ?>" data-bank-review novalidate><?= csrf_field() ?>
        <input type="hidden" name="payload" value="" data-bank-payload>
        <section class="mn-card">
            <header><div><h2><i class="fa-solid fa-list-check" aria-hidden="true"></i>Review Each Line</h2><p>Money in <?= e(Money::gbp($total['in'])) ?> · money out <?= e(Money::gbp($total['out'])) ?><?= !empty($s['map']['layout_name']) ? ' · read with the "' . e((string) $s['map']['layout_name']) . '" layout' : '' ?>. Change any title or category before adding.</p></div>
                <div class="bk-bulk"><select class="form-select form-select-sm" data-bank-setcat aria-label="Set the category of ticked lines"><option value="">Set Category For Ticked…</option>
                    <optgroup label="Costs"><?php foreach ($opts['out'] as $k => $l): if ($k === '0') { continue; } ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></optgroup>
                    <optgroup label="Income"><?php foreach ($opts['in'] as $k => $l): if ($k === '0') { continue; } ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></optgroup></select></div></header>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0 bk-table">
                <thead><tr><th style="width:2rem"><input class="form-check-input" type="checkbox" data-bank-all aria-label="Tick every new line" checked></th><th>Date</th><th>What It Becomes</th><th class="text-end">Amount</th><th>Category</th><th>Note</th></tr></thead><tbody>
                <?php foreach ($rows as $r): $n = (int) $r['n']; $done = $r['status'] === 'done'; [$cl, $cc] = $chip[$r['status']]; ?>
                    <tr data-bank-row="<?= $n ?>" class="<?= $done ? 'is-done' : '' ?>">
                        <td><input class="form-check-input" type="checkbox" name="pick[<?= $n ?>]" value="1" aria-label="Add this line"<?= $r['status'] === 'new' ? ' checked' : '' ?><?= $done ? ' disabled' : '' ?>></td>
                        <td class="text-nowrap"><?= e(Money::showDate((string) $r['date'])) ?></td>
                        <td><input class="form-control form-control-sm" name="title[<?= $n ?>]" value="<?= e((string) $r['title']) ?>" maxlength="255" aria-label="Title"<?= $done ? ' disabled' : '' ?>>
                            <small class="text-muted"><?= $r['dir'] === 'out' ? 'Expense' : 'Payment' ?> · <?= e((string) $r['desc']) ?></small></td>
                        <td class="text-end text-nowrap <?= $r['dir'] === 'out' ? 'mn-neg' : 'mn-pos' ?>"><?= $r['dir'] === 'out' ? '-' : '+' ?><?= e(Money::gbp(abs((float) $r['amount']))) ?></td>
                        <td><select class="form-select form-select-sm" name="category[<?= $n ?>]" aria-label="Category"<?= $done ? ' disabled' : '' ?>><?php foreach ($opts[$r['dir']] as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $r['category'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                            <?php if ($r['dir'] === 'in' && $invoices && !$done): ?><select class="form-select form-select-sm mt-1" name="invoice[<?= $n ?>]" aria-label="For an invoice"><option value="0">Not For An Invoice</option><?php foreach ($invoices as $k => $l): ?><option value="<?= (int) $k ?>"<?= (int) $r['invoice'] === (int) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?></td>
                        <td><span class="mn-chip <?= e($cc) ?>"><?= e($cl) ?></span><?php if ($r['note'] !== ''): ?><div class="small text-muted mt-1"><?= e((string) $r['note']) ?></div><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <div class="vt-file-go"><button class="btn btn-primary mn-btn" type="submit" data-loading="Adding…"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Add The Ticked Lines</button><span class="small text-muted" data-bank-n></span></div>
        </section>
    </form>
    <form method="post" action="<?= e(admin_url('money/bank/' . $token . '/discard')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-link px-0" type="submit">Put It Away Without Adding Anything</button></form>
</div>
<?php $this->endSection(); ?>
