<?php declare(strict_types=1);
/** @var array $w @var string $name @var string $own @var string $common */
use AP\Core\View;
use AP\Modules\Money\Controllers\Forms;
?>
<form method="post" action="<?= e(admin_url('money/dashboard/cards/style')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title">Card Style: <?= e($name) ?></h2>
    <input type="hidden" name="id" value="<?= e((string) $w['id']) ?>">
    <p class="text-muted">Choose how this card looks. Pick "Same As Everywhere" to follow the style in Settings.</p>
    <?= View::render('money::_card_picker', ['field' => 'style', 'current' => $own, 'common' => $common, 'inherit' => true]) ?>
    <?= Forms::buttons(admin_url(), !empty($modal)) ?>
</form>
