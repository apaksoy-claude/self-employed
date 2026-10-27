<?php declare(strict_types=1);
/** Money > Bank Import. @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Money\Bank;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$catOptions = ['' => 'Choose When Added'];
foreach ($cats as $id => $c) {
    $catOptions[(string) $id] = ($c['kind'] === 'income' ? 'Income: ' : 'Cost: ') . $c['name'];
}
$err = form_errors('statement');
?>
<?php $this->section('content'); ?>
<div class="mn-page bk-page">
    <p class="text-muted">Download a statement from your online banking as a CSV or OFX file and load it here. Each line is shown before anything is added: money out becomes an expense, money in becomes a payment. Lines already added are never added twice, so loading overlapping statements is safe.</p>
    <section class="mn-card">
        <header><div><h2><i class="fa-solid fa-building-columns" aria-hidden="true"></i>Load A Statement</h2><p>CSV from any bank, or OFX and QFX. Up to 5 MB.</p></div></header>
        <form method="post" action="<?= e(admin_url('money/bank/upload')) ?>" enctype="multipart/form-data" class="mn-form" novalidate><?= csrf_field() ?>
            <div class="ap-dropzone<?= $err ? ' is-invalid' : '' ?>" data-dropzone tabindex="0">
                <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
                <div>Drop the statement here, or <span class="ap-dz-pick">choose the file</span></div>
                <input type="file" name="statement" class="visually-hidden" accept=".csv,.ofx,.qfx,.txt,text/csv" aria-label="Choose the statement file">
                <ul class="ap-dz-list list-unstyled small mb-0"></ul>
            </div>
            <?php if ($err): ?><div class="invalid-feedback d-block"><?= e((string) $err[0]) ?></div><?php endif; ?>
            <div class="row mt-3">
                <div class="col-md-6 ap-field"><label class="form-label" for="bk_account">Which Account</label>
                    <input class="form-control" id="bk_account" name="account" maxlength="120" list="bk_accounts" placeholder="Business current account" value="<?= e((string) old('account', $accounts[0] ?? '')) ?>">
                    <datalist id="bk_accounts"><?php foreach ($accounts as $a): ?><option value="<?= e($a) ?>"></option><?php endforeach; ?></datalist>
                    <div class="form-text">Use the same name each time for the same account.</div></div>
            </div>
            <button class="btn btn-primary mn-btn mt-2" type="submit" data-loading="Reading…"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Read The Statement</button>
        </form>
    </section>

    <section class="mn-card">
        <header><div><h2><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Rules</h2><p>When a line's description contains the words, it gets this category, stream and title. The first rule that matches is used: drag them into order.</p></div></header>
        <form method="post" action="<?= e(admin_url('money/bank/rules')) ?>" data-bg-save novalidate><?= csrf_field() ?>
            <?= Form::field(['name' => 'rules', 'label' => 'Rules', 'type' => 'repeater', 'value' => $rules, 'add_label' => 'Add A Rule', 'max_rows' => 200, 'fields' => [
                'keyword' => ['label' => 'When It Contains', 'placeholder' => 'SHELL, AMAZON, client name'],
                'direction' => ['label' => 'For', 'type' => 'select', 'options' => Bank::DIRECTIONS, 'width' => 'sm'],
                'category' => ['label' => 'Category', 'type' => 'select', 'options' => $catOptions],
                'stream' => ['label' => 'Stream', 'type' => 'select', 'options' => ['' => 'Not Assigned'] + array_map('strval', $streams)],
                'title' => ['label' => 'Title', 'placeholder' => 'Keep the bank\'s words']]]) ?>
            <button class="btn btn-primary mn-btn" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span>Save Rules</span></button>
        </form>
    </section>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Recent Imports</h2><p>Take one back if it went wrong: what it added goes to the bin.</p></div></header>
            <?php if (!$imports): ?><p class="text-muted mb-0">No statements loaded yet.</p><?php else: ?>
            <ul class="mn-rows">
                <?php foreach ($imports as $i): ?>
                    <li><span><?= e((string) $i['file_name']) ?><small><?= e(Money::showDate(substr((string) $i['created_at'], 0, 10))) ?><?= $i['account'] ? ' · ' . e((string) $i['account']) : '' ?> · <?= (int) $i['added_out'] ?> expense(s), <?= (int) $i['added_in'] ?> payment(s)</small></span>
                        <form method="post" action="<?= e(admin_url('money/bank/imports/' . (int) $i['id'] . '/undo')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger mn-btn" type="submit" data-confirm="Take back everything this import added? The records go to the bin."><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Take Back</button></form></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        <section class="mn-card">
            <header><div><h2><i class="fa-solid fa-table-columns" aria-hidden="true"></i>Saved Bank Layouts</h2><p>The columns of each bank's CSV, remembered after the first time.</p></div></header>
            <?php if (!$layouts): ?><p class="text-muted mb-0">None yet. Most banks' columns are found by their names; if not, you choose them once and save them here.</p><?php else: ?>
            <ul class="mn-rows">
                <?php foreach ($layouts as $l): ?>
                    <li><span><?= e((string) $l['name']) ?><small>Saved <?= e(Money::showDate(substr((string) $l['created_at'], 0, 10))) ?></small></span>
                        <form method="post" action="<?= e(admin_url('money/bank/layouts/' . (int) $l['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="mn-icon" type="submit" data-confirm="Remove this layout?" aria-label="Remove" title="Remove"><i class="fa-solid fa-trash"></i></button></form></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php $this->endSection(); ?>
