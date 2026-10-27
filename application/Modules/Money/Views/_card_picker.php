<?php declare(strict_types=1);
/** @var string $field name of the radio group @var string $current chosen key ('' = same as everywhere) @var string $common the style everything uses @var bool $inherit show the "same as everywhere" choice */
use AP\Modules\Money\CardStyles;

$inherit ??= false;
?>
<div class="cs-pick" role="radiogroup" aria-label="Card style">
    <?php if ($inherit): ?>
        <label class="cs-opt">
            <input class="form-check-input" type="radio" name="<?= e($field) ?>" value=""<?= $current === '' ? ' checked' : '' ?> aria-label="Same as everywhere">
            <div class="cs-opt-head"><strong>Same As Everywhere</strong><small>Follows the card style chosen in Settings (now <?= e(CardStyles::STYLES[$common][0]) ?>).</small></div>
            <?= CardStyles::sample($common) ?>
        </label>
    <?php endif; ?>
    <?php foreach (CardStyles::STYLES as $key => [$label, $about]): ?>
        <label class="cs-opt">
            <input class="form-check-input" type="radio" name="<?= e($field) ?>" value="<?= e($key) ?>"<?= $current === $key ? ' checked' : '' ?> aria-label="<?= e($label) ?>">
            <div class="cs-opt-head"><strong><?= e($label) ?></strong><small><?= e($about) ?></small></div>
            <?= CardStyles::sample($key) ?>
        </label>
    <?php endforeach; ?>
</div>
