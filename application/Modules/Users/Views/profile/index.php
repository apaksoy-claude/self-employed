<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<?php if ($suggest2fa): ?><div class="alert alert-info"><i class="fa-solid fa-shield-halved me-2"></i>Two-factor login adds a second step when you log in. We recommend turning it on below.</div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">Details</div><div class="card-body">
            <form method="post" action="<?= e(admin_url('profile')) ?>" data-unsaved-warning novalidate><?= csrf_field() ?>
                <?= Form::field(['name' => 'name', 'label' => 'Name', 'value' => $user['name'], 'required' => true]) ?>
                <?= Form::field(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user['email'], 'required' => true]) ?>
                <?= Form::saveButtons() ?>
            </form>
            <hr><a class="btn btn-outline-secondary" href="<?= e(admin_url('password')) ?>"><i class="fa-solid fa-key me-1"></i>Change password</a>
        </div></div>

        <div class="card border-0 shadow-sm mb-3" id="twofactor"><div class="card-header bg-transparent fw-semibold">Two-factor login</div><div class="card-body">
        <?php if ((int) $user['two_factor_enabled']): ?>
            <p><?= Ui::badge('On', 'success') ?> You will be asked for a code from your authenticator app when you log in.</p>
            <form method="post" action="<?= e(admin_url('profile/2fa/disable')) ?>" data-confirm="Turn off two-factor login?"><?= csrf_field() ?>
                <?= Form::field(['name' => 'password', 'label' => 'Your password', 'type' => 'password', 'id' => 'disable_pw', 'help' => 'Enter your password to turn it off.']) ?>
                <?php foreach (form_errors('disable_password') as $err): ?><div class="text-danger small mb-2"><?= e($err) ?></div><?php endforeach; ?>
                <button class="btn btn-outline-danger">Turn off</button>
            </form>
        <?php elseif ($setupSecret !== ''): ?>
            <p>1. In your authenticator app (Google Authenticator, Microsoft Authenticator, Authy...), add a new account using this setup key:</p>
            <p><code class="fs-6 user-select-all"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code>
               <button class="btn btn-sm btn-outline-secondary ms-2" type="button" data-copy="<?= e($setupSecret) ?>">Copy</button></p>
            <p class="small text-muted">Choose "time based" if asked. On a phone you can also open <a href="<?= e($setupUri) ?>">this setup link</a>.</p>
            <p>2. Type the 6-digit code the app shows:</p>
            <form method="post" action="<?= e(admin_url('profile/2fa/enable')) ?>"><?= csrf_field() ?>
                <?= Form::field(['name' => 'code', 'label' => 'Code', 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => '8']]) ?>
                <button class="btn btn-primary">Turn on two-factor login</button>
            </form>
        <?php else: ?>
            <p>Add a second step to your login using an authenticator app on your phone.</p>
            <form method="post" action="<?= e(admin_url('profile/2fa/start')) ?>"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="fa-solid fa-shield-halved me-1"></i>Set up</button></form>
        <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3" id="sessions"><div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">Active sessions
            <form method="post" action="<?= e(admin_url('profile/sessions/revoke-all')) ?>" data-confirm="Log out of every other device?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Log out everywhere else</button></form></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($sessions as $s): $current = $s['token_hash'] === $currentHash; ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div><div class="small fw-semibold"><?= e($s['ip']) ?> <?= $current ? Ui::badge('This device', 'primary') : '' ?></div>
                    <div class="small text-muted text-break"><?= e($s['user_agent']) ?></div><div class="small text-muted">Last active <?= e(time_ago($s['last_active'])) ?></div></div>
                    <?php if (!$current): ?><form method="post" action="<?= e(admin_url('profile/sessions/revoke')) ?>"><?= csrf_field() ?><input type="hidden" name="session" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-outline-secondary">End</button></form><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul></div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Recent logins</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($history as $h): ?>
                <li class="list-group-item small d-flex justify-content-between"><span><?= (int) $h['success'] ? '<i class="fa-solid fa-circle-check text-success me-1"></i>Success' : '<i class="fa-solid fa-circle-xmark text-danger me-1"></i>Failed' ?> · <?= e($h['ip']) ?></span><span class="text-muted"><?= e(format_date($h['created_at'])) ?></span></li>
            <?php endforeach; ?>
        </ul></div>
    </div>
</div>
<?php $this->endSection(); ?>
