<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::auth_layout'); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('2fa')) ?>" novalidate>
    <?= csrf_field() ?>
    <p class="text-muted small">Open your authenticator app and type the 6-digit code for this site.</p>
    <?= \AP\Core\Form::field(['name' => 'code', 'label' => 'Authenticator code', 'type' => 'text', 'required' => true, 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => '8', 'autofocus' => true]]) ?>
    <?php if (($trustDays = (int) setting('security.trust_days', 30)) > 0): ?>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trust" value="1" id="trust"><label class="form-check-label small" for="trust">Do not ask again on this browser for <?= $trustDays ?> day<?= $trustDays === 1 ? '' : 's' ?></label></div>
    <?php endif; ?>
    <button class="btn btn-primary w-100" type="submit">Verify and log in</button>
    <a class="d-block text-center mt-3 small" href="<?= e(admin_url('login')) ?>">Back to login</a>
</form>
<?php $this->endSection(); ?>
