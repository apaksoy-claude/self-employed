<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">Your admin account</h1>
<p class="text-muted">This account is the Super Admin: it can do everything. You can add more staff later.</p>
<form method="post" action="<?= e(url('install/admin')) ?>" novalidate><?= csrf_field() ?>
    <?= Form::field(['name' => 'name', 'label' => 'Your name', 'value' => $admin['name'], 'required' => true]) ?>
    <?= Form::field(['name' => 'email', 'label' => 'Email (used to log in)', 'type' => 'email', 'value' => $admin['email'], 'required' => true]) ?>
    <div class="row">
        <?= Form::field(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'col' => 'col-md-6', 'help' => 'At least 10 characters with upper and lower case letters and a number.']) ?>
        <?= Form::field(['name' => 'password_confirmation', 'label' => 'Repeat password', 'type' => 'password', 'required' => true, 'col' => 'col-md-6']) ?>
    </div>
    <a class="btn btn-link" href="<?= e(url('install/site')) ?>">Back</a><button class="btn btn-primary" type="submit">Continue</button>
</form>
<?php $this->endSection(); ?>
