<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
?>
<form method="post" action="<?= e(admin_url('money/invoices/' . (int) $inv['id'] . '/payments/save')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title">Add Payment <small><?= e((string) $inv['number']) ?></small></h2>
    <div class="mn-fields">
        <?= Fields::text('income_date', 'Payment date', Money::showDate($today), ['date' => true, 'col' => 'is-half']) ?>
        <?= Fields::text('amount', 'Amount (£)', number_format($balance, 2, '.', ''), ['col' => 'is-half', 'inputmode' => 'decimal', 'help' => 'Still owed: ' . Money::gbp($balance)]) ?>
        <?= Fields::select('payment_method', 'Paid By', Money::payOptions('money.paid_by'), '', ['none' => 'Not Said', 'none_value' => '', 'col' => 'is-half']) ?>
        <?= Fields::text('reference', 'Reference', '', ['col' => 'is-half', 'maxlength' => 120]) ?>
        <?= Fields::area('note', 'Note', '', ['rows' => 2]) ?>
    </div>
    <?= Forms::buttons(admin_url('money/invoices/' . (int) $inv['id']), !empty($modal)) ?>
</form>
