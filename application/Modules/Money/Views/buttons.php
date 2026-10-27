<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<form method="post" action="<?= e(admin_url('appearance/buttons')) ?>"><?= csrf_field() ?>
    <p class="text-muted mb-3">Pick how the buttons look across the whole admin. Each card shows the main button, a white secondary button, an outline button and a delete button in that style.</p>
    <div class="mn-styles" role="radiogroup" aria-label="Button style">
    <?php foreach ($styles as $key => [$label, $about]): ?>
        <label class="mn-style" data-btn-demo="<?= e($key) ?>">
            <input class="form-check-input" type="radio" name="style" value="<?= e($key) ?>"<?= $current === $key ? ' checked' : '' ?> aria-label="<?= e($label) ?>">
            <div class="mn-style-head"><strong><?= e($label) ?></strong><small><?= e($about) ?></small></div>
            <div class="mn-style-row">
                <span class="btn btn-primary btn-sm" aria-hidden="true"><i class="fa-solid fa-check me-1"></i>Save</span>
                <span class="btn btn-outline-secondary btn-sm" aria-hidden="true">Cancel</span>
                <span class="btn btn-outline-primary btn-sm" aria-hidden="true">Save &amp; Stay</span>
                <span class="btn btn-outline-danger btn-sm" aria-hidden="true"><i class="fa-solid fa-trash"></i></span>
            </div>
        </label>
    <?php endforeach; ?>
    </div>
    <div class="mn-actions">
        <button class="btn btn-primary mn-btn" type="submit" name="after" value="list" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Use This Style</button>
        <button class="btn btn-outline-primary mn-btn" type="submit" name="after" value="stay"><i class="fa-solid fa-floppy-disk me-1"></i>Use &amp; Stay</button>
        <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('settings')) ?>">Cancel</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
