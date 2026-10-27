<?php declare(strict_types=1);
/** @var array $status @var array $push */
$ok = !$status['problems'];
?>
<div class="ap-aside-card">
    <h2><i class="fa-solid fa-signal"></i>Status</h2>
    <div class="ap-status-tile <?= $status['enabled'] ? ($ok ? 'is-ok' : 'is-warn') : 'is-off' ?>">
        <i class="fa-solid <?= $status['enabled'] ? ($ok ? 'fa-mobile-screen-button' : 'fa-triangle-exclamation') : 'fa-power-off' ?>" aria-hidden="true"></i>
        <div><strong><?= $status['enabled'] ? ($ok ? 'Ready to install' : 'Almost ready') : 'App switched off' ?></strong>
            <span><?= $status['enabled'] ? ($ok ? 'Phones can add the site to the home screen.' : 'See below what is still needed.') : 'Switch on "Visitors can add the site to their phone".' ?></span></div>
    </div>
    <?php if ($status['problems']): ?><ul class="mt-2"><?php foreach ($status['problems'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($status['icons']): ?>
        <div class="ap-app-icons mt-2"><?php foreach ($status['icons'] as $size => $src): ?><figure><img src="<?= e($src) ?>" alt="" width="48" height="48"><figcaption><?= (int) $size ?> px</figcaption></figure><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="ap-status-rows mt-2">
        <div><span>Push notifications</span><strong><?= !$push['supported'] ? 'Not possible on this server' : (!$push['keys'] ? 'Needs keys' : ($push['enabled'] ? 'On' : 'Off')) ?></strong></div>
        <div><span>Browsers signed up</span><strong><?= number_format((int) $push['subscribers']) ?></strong></div>
    </div>
    <?php if ($push['supported'] && can('settings.edit')): ?>
        <form method="post" action="<?= e(admin_url('push/keys')) ?>" class="mt-3"<?= $push['keys'] ? ' data-confirm="Make new keys? Everyone who allowed notifications will have to allow them again."' : '' ?>><?= csrf_field() ?>
            <button class="btn btn-sm <?= $push['keys'] ? 'btn-outline-secondary' : 'btn-primary' ?> w-100"><i class="fa-solid fa-key me-1"></i><?= $push['keys'] ? 'Make new keys' : 'Make the keys' ?></button>
        </form>
    <?php endif; ?>
    <?php if ($push['keys'] && can('push.send')): ?><a class="btn btn-sm btn-outline-primary w-100 mt-2" href="<?= e(admin_url('push')) ?>"><i class="fa-regular fa-bell me-1"></i>Send a notification</a><?php endif; ?>
</div>
<div class="ap-aside-card"><h2><i class="fa-regular fa-lightbulb"></i>Tips</h2><ul>
    <li>iPhones show notifications only after the site was added to the home screen (iOS 16.4 or newer).</li>
    <li>Ask for notifications sparingly: one useful message a week keeps people signed up.</li>
    <li>The address of the app file: <a href="<?= e($status['manifest']) ?>" target="_blank" rel="noopener">manifest.webmanifest</a>.</li>
</ul></div>
