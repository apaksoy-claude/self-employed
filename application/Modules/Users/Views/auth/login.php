<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::auth_layout'); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('login')) ?>" novalidate>
    <?= csrf_field() ?>
    <?= \AP\Core\Form::field(['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'username', 'autofocus' => true]]) ?>
    <?= \AP\Core\Form::field(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
    <?php if (($keepDays = (int) setting('security.remember_days', 0)) > 0): ?>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember"><label class="form-check-label small" for="remember">Keep me signed in for <?= $keepDays ?> day<?= $keepDays === 1 ? '' : 's' ?></label></div>
    <?php endif; ?>
    <?= \AP\Core\Captcha::widget('login') ?>
    <button class="btn btn-primary w-100 ap-auth-btn" type="submit">Sign in</button>
    <?php if (setting('admin.login_note', true)): ?>
        <p class="ap-auth-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <?php if (\AP\Modules\Users\PasswordResets::available()): ?><span>Forgotten your password? <a href="<?= e(admin_url('forgot')) ?>">Get a link to set a new one</a> by email.</span>
            <?php else: ?><span>Forgotten your password? Another administrator can set a new one for you. A reset link by email works once email sending is set up.</span><?php endif; ?></p>
    <?php elseif (\AP\Modules\Users\PasswordResets::available()): ?><a class="d-block text-center mt-3 small" href="<?= e(admin_url('forgot')) ?>">Forgot your password?</a><?php endif; ?>
</form>
<?php $this->endSection(); ?>
