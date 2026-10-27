<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<div class="row"><div class="col-lg-6 col-xl-5">
<?php if (!empty($forced)): ?><div class="alert alert-warning">You need to choose a new password before you can continue.</div><?php endif; ?>
<form method="post" action="<?= e(admin_url('password')) ?>" data-unsaved-warning novalidate>
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
    <?= \AP\Core\Form::field(['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
    <?= \AP\Core\Form::field(['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'help' => 'At least ' . (int) setting('security.password_min_length', 10) . ' characters. The rules are set in Settings > Security.']) ?>
    <?= \AP\Core\Form::field(['name' => 'password_confirmation', 'label' => 'Repeat new password', 'type' => 'password', 'required' => true]) ?>
    </div></div>
    <?= \AP\Core\Form::saveButtons() ?>
</form>
</div></div>
<?php $this->endSection(); ?>
