<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::auth_layout'); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('reset/' . $token)) ?>" novalidate>
    <?= csrf_field() ?>
    <?= \AP\Core\Form::field(['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'help' => 'At least ' . (int) setting('security.password_min_length', 10) . ' characters.', 'attrs' => ['autocomplete' => 'new-password', 'autofocus' => true]]) ?>
    <?= \AP\Core\Form::field(['name' => 'password_confirmation', 'label' => 'Type it again', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
    <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-key me-1"></i>Save password</button>
</form>
<?php $this->endSection(); ?>
