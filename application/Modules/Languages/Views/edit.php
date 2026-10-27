<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('languages/' . $l['code'])) ?>" data-unsaved-warning novalidate>
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <div class="mb-3"><span class="form-label d-block">Code</span><code><?= e($l['code']) ?></code></div>
        <?= Form::field(['name' => 'name', 'label' => 'English name', 'value' => $l['name'], 'required' => true]) ?>
        <?= Form::field(['name' => 'native_name', 'label' => 'Name in its own language', 'value' => $l['native_name'], 'required' => true, 'help' => 'Shown in the language switcher.']) ?>
        <?= Form::field(['name' => 'direction', 'label' => 'Text direction', 'type' => 'select', 'value' => $l['direction'], 'options' => ['ltr' => 'Left to right', 'rtl' => 'Right to left (Arabic, Hebrew, Persian, Urdu)']]) ?>
        <?= Form::field(['name' => 'sort_order', 'label' => 'Order in the switcher', 'type' => 'number', 'value' => $l['sort_order'], 'help' => 'Smaller numbers come first.']) ?>
    </div></div>
    <?= Form::saveButtons(false, admin_url('languages')) ?>
</form>
<?php $this->endSection(); ?>
