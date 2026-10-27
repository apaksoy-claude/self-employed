<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
use AP\Modules\Money\Stock;
?>
<form method="post" action="<?= e(admin_url('money/stock/' . (int) $item['id'] . '/adjust')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title">Add Or Take Out <small><?= e((string) $item['name']) ?> · <?= e(Stock::qty($level['qty'])) ?> in stock</small></h2>
    <div class="mn-fields">
        <?= Fields::text('qty', 'How Many', '', ['inputmode' => 'decimal', 'placeholder' => '3 or -2', 'col' => 'is-third', 'help' => 'A minus takes some out.']) ?>
        <?= Fields::text('move_date', 'Date', Money::showDate(Money::today()), ['date' => true, 'col' => 'is-third']) ?>
        <?= Fields::text('cost', 'Cost Each (£)', '', ['inputmode' => 'decimal', 'placeholder' => 'Average cost', 'col' => 'is-third', 'help' => 'Only when adding. Stock you bought is best added on its expense.']) ?>
        <?= Fields::select('reason', 'Why', array_combine(Stock::REASONS, Stock::REASONS), 'Damaged Or Lost', ['col' => 'is-half']) ?>
        <?= Fields::text('note', 'Note', '', ['maxlength' => 200, 'col' => 'is-half']) ?>
    </div>
    <?= Forms::buttons(admin_url('money/stock'), !empty($modal)) ?>
</form>
