<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;

$item ??= null;
$q = $item ? rtrim(rtrim(number_format((float) $item['quantity'], 3, '.', ''), '0'), '.') : '1';
?>
<form method="post" action="<?= e(admin_url('money/invoices/' . (int) $inv['id'] . '/items/save')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $item ? 'Change Item' : 'Add Item' ?> <small><?= e((string) $inv['number']) ?></small></h2>
    <input type="hidden" name="item_id" value="<?= (int) ($item['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::text('title', 'Title', $item['title'] ?? '', ['required' => true, 'maxlength' => 255, 'placeholder' => 'Design work']) ?>
        <?= Fields::area('description', 'Description', $item['description'] ?? '', ['rows' => 3]) ?>
        <?= Fields::text('quantity', 'Quantity', $q, ['col' => 'is-third', 'inputmode' => 'decimal']) ?>
        <?= Fields::text('unit', 'Unit', $item['unit'] ?? '', ['col' => 'is-third', 'placeholder' => 'Hours', 'maxlength' => 30]) ?>
        <?= Fields::text('rate', 'Rate (£)', $item ? number_format((float) $item['rate'], 2, '.', '') : '', ['col' => 'is-third', 'inputmode' => 'decimal', 'placeholder' => '0.00']) ?>
        <?php if (\AP\Modules\Money\Vat::on()): ?>
            <?= Fields::select('vat_rate', 'VAT', \AP\Modules\Money\Vat::options((string) $inv['issue_date']), $item['vat_rate'] ?? (string) setting('money.vat_default', 'standard'), ['col' => 'is-half', 'none' => 'No VAT', 'none_value' => '',
                'help' => 'The rate is added on top of the price above.']) ?>
        <?php endif; ?>
    </div>
    <?= Forms::buttons(admin_url('money/invoices/' . (int) $inv['id']), !empty($modal)) ?>
</form>
