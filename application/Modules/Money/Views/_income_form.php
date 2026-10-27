<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;

$i ??= null;
?>
<form method="post" action="<?= e(admin_url('money/income/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $i ? 'Change Payment' : 'New Payment' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::picker('invoice_id', 'Invoice', Money::invoiceChoices((int) ($i['invoice_id'] ?? 0)), $i['invoice_id'] ?? 0, ['none' => 'Not for an invoice', 'search' => 'Search by client, number, date or amount…', 'help' => 'Choose an invoice and this payment is counted against it. The client, stream and amount are filled in for you.']) ?>
        <?= Fields::text('title', 'Where did it come from', $i['title'] ?? '', ['required' => true, 'maxlength' => 255, 'placeholder' => 'Uber weekly statement']) ?>
        <?= Fields::text('income_date', 'Date received', Money::showDate($i['income_date'] ?? Money::today()), ['date' => true, 'col' => 'is-half']) ?>
        <?= Fields::text('amount', 'Amount (£)', $i ? number_format((float) $i['amount'], 2, '.', '') : '', ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => '0.00']) ?>
        <?= Fields::select('stream_id', 'Stream', $streams, $i['stream_id'] ?? 0, ['none' => 'Not assigned', 'col' => 'is-half']) ?>
        <?= Fields::select('contact_id', 'Client', $contacts, $i['contact_id'] ?? 0, ['none' => 'Nobody in particular', 'col' => 'is-half']) ?>
        <?= Fields::select('payment_method', 'Paid By', Money::payOptions('money.paid_by', (string) ($i['payment_method'] ?? '')), $i['payment_method'] ?? '', ['none' => 'Not Said', 'none_value' => '', 'col' => 'is-half']) ?>
        <?= Fields::text('reference', 'Reference', $i['reference'] ?? '', ['col' => 'is-half', 'maxlength' => 120]) ?>
        <?= Fields::area('description', 'Notes', $i['description'] ?? '') ?>
        <?= \AP\Modules\Money\CustomFields::form('income', (int) ($i['id'] ?? 0)) ?>
    </div>
    <?= Forms::buttons(admin_url('money/income'), !empty($modal), $i ? admin_url('money/income/' . (int) $i['id'] . '/delete') : '', 'Delete this payment?') ?>
</form>
