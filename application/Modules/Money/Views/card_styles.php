<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\View;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<form method="post" action="<?= e(admin_url('appearance/cards')) ?>"><?= csrf_field() ?>
    <p class="text-muted mb-3">Pick how the quarter and month cards on the dashboard look. Each card below shows a quarter and a month in that style. You can also give a single card its own style from the dashboard: choose Customise, then the paintbrush on the card.</p>
    <?= View::render('money::_card_picker', ['field' => 'style', 'current' => $current, 'common' => $current, 'inherit' => false]) ?>
    <div class="mn-actions">
        <button class="btn btn-primary mn-btn" type="submit" name="after" value="list" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Use This Style</button>
        <button class="btn btn-outline-primary mn-btn" type="submit" name="after" value="stay"><i class="fa-solid fa-floppy-disk me-1"></i>Use &amp; Stay</button>
        <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('settings')) ?>">Cancel</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
