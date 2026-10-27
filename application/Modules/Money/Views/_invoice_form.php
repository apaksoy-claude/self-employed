<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
use AP\Modules\Money\Recurring;

$inv ??= null;
?>
<form method="post" action="<?= e(admin_url('money/invoices/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $inv ? 'Change Invoice' : 'New Invoice' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($inv['id'] ?? 0) ?>">
    <div class="mn-fields">
        <div class="mn-field is-half"><span class="form-label">Invoice number</span><div class="mn-readonly"><strong><?= e((string) ($inv['number'] ?? $number)) ?></strong><small><?= $inv ? 'Fixed' : 'Counts up by itself' ?></small></div></div>
        <?= Fields::select('status', 'Status', ['sent' => 'Sent', 'draft' => 'Draft'], $inv['status'] ?? 'sent', ['col' => 'is-half']) ?>
        <?= Fields::text('issue_date', 'Bill date', Money::showDate($inv['issue_date'] ?? $today), ['date' => true, 'col' => 'is-half']) ?>
        <?= Fields::text('due_date', 'Due date', Money::showDate($inv['due_date'] ?? ($inv ? null : $due)), ['date' => true, 'col' => 'is-half']) ?>
        <?= Fields::select('contact_id', 'Client', $contacts, $inv['contact_id'] ?? 0, ['none' => 'No client yet', 'col' => 'is-half']) ?>
        <?= Fields::text('new_client', 'Or add a new client', '', ['col' => 'is-half', 'placeholder' => 'Client name', 'maxlength' => 190]) ?>
        <?= Fields::select('stream_id', 'Income source', $streams, $inv['stream_id'] ?? 0, ['none' => 'Not assigned']) ?>
        <?= Fields::area('note', 'Note', $inv['note'] ?? '') ?>
        <?= \AP\Modules\Money\CustomFields::form('invoice', (int) ($inv['id'] ?? 0)) ?>
    </div>
    <?php $rep = (int) old('recurring', $inv['recurring'] ?? 0) === 1; ?>
    <div class="mn-repeat">
        <label class="form-switch form-check"><input type="hidden" name="recurring" value="0"><input class="form-check-input" type="checkbox" role="switch" name="recurring" value="1" data-toggle-target="#invrepeat"<?= $rep ? ' checked' : '' ?>> <span class="form-check-label">Repeat this invoice automatically</span></label>
        <div id="invrepeat" class="mn-repeat-box">
            <div class="mn-repeat-row">
                <span>Make a new invoice every</span>
                <input class="form-control" style="width:5rem" name="repeat_every" inputmode="numeric" value="<?= e((string) old('repeat_every', ($inv['repeat_every'] ?? 0) ?: 1)) ?>" aria-label="How many">
                <select class="form-select" name="repeat_unit" aria-label="Unit" style="width:auto"><?php foreach (Recurring::UNITS as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) old('repeat_unit', $inv['repeat_unit'] ?? 'months') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            </div>
            <div class="mn-fields mt-2">
                <?= Fields::text('next_date', 'Next invoice date', Money::showDate($inv['next_date'] ?? null), ['date' => true, 'col' => 'is-half', 'help' => 'Leave empty to start one period after the bill date.']) ?>
                <?= Fields::text('max_cycles', 'Stop after this many invoices', (string) ($inv['max_cycles'] ?? 0), ['col' => 'is-half', 'inputmode' => 'numeric', 'help' => '0 means never stop.']) ?>
                <?= Fields::text('repeat_until', 'Stop On This Date', Money::showDate($inv['repeat_until'] ?? null), ['date' => true, 'col' => 'is-half', 'help' => 'No invoice is made after this day. Leave empty to carry on.']) ?>
            </div>
            <label class="form-switch form-check mt-2"><input type="hidden" name="auto_email" value="0"><input class="form-check-input" type="checkbox" role="switch" name="auto_email" value="1"<?= (int) old('auto_email', $inv['auto_email'] ?? (int) (bool) setting('money.recurring_email', false)) === 1 ? ' checked' : '' ?>> <span class="form-check-label">Email each new invoice to the client with a link to pay it</span></label>
            <p class="small text-muted mb-0">Each new invoice gets the next number, a copy of the items, and is made as <?= (string) setting('money.recurring_status', 'sent') === 'draft' ? 'a draft' : 'sent' ?> (change this in Settings > Money).</p>
            <div class="mn-field" data-field="repeat_every"><div class="invalid-feedback" data-error<?= form_errors('repeat_every') ? ' style="display:block"' : '' ?>><?= e((string) (form_errors('repeat_every')[0] ?? '')) ?></div></div>
        </div>
    </div>
    <?= Forms::buttons(admin_url('money/invoices'), !empty($modal), $inv ? admin_url('money/invoices/' . (int) $inv['id'] . '/delete') : '', 'Delete this invoice?') ?>
</form>
