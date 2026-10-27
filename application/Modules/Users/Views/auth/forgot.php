<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::auth_layout'); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('forgot')) ?>" novalidate>
    <?= csrf_field() ?>
    <p class="text-muted small">Enter the email address you use to log in. If it matches an account, we will send a link to choose a new password.</p>
    <?= \AP\Core\Form::field(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'username', 'autofocus' => true]]) ?>
    <?= \AP\Core\Captcha::widget('login') ?>
    <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-paper-plane me-1"></i>Email me a link</button>
    <a class="d-block text-center mt-3 small" href="<?= e(admin_url('login')) ?>">Back to login</a>
</form>
<?php $this->endSection(); ?>
