<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/push-admin.js')) . '?v=' . e(AP_VERSION) . '"></script>');
$badge = ['sending' => ['Sending', 'info'], 'sent' => ['Sent', 'success'], 'cancelled' => ['Stopped', 'secondary']];
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/app')) ?>"><i class="fa-solid fa-sliders me-1"></i>Settings</a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="fa-solid fa-triangle-exclamation me-2"></i><?= !$status['supported'] ? 'This server cannot send push notifications (the PHP openssl and curl extensions are needed).' : (!$status['keys'] ? 'Push notifications need keys first.' : 'Push notifications are switched off.') ?></span>
        <?php if ($status['supported']): ?><a class="btn btn-sm btn-dark" href="<?= e(admin_url('settings/app')) ?>">Open App and notifications settings</a><?php endif; ?>
    </div>
<?php endif; ?>
<div class="ap-stat-row mb-3">
    <div class="ap-stat-card"><i class="fa-solid fa-users" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['subscribers']) ?></strong><span>Browsers signed up</span></div></div>
    <div class="ap-stat-card"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['new30']) ?></strong><span>New in the last 30 days</span></div></div>
    <div class="ap-stat-card"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['sent30']) ?></strong><span>Delivered in 30 days</span></div></div>
    <div class="ap-stat-card"><i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['clicks30']) ?></strong><span>Opened in 30 days</span></div></div>
</div>
<div class="ap-settings ap-two">
    <div class="ap-sections">
        <section class="ap-section">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-regular fa-bell" aria-hidden="true"></i>Write a notification</h2></div>
            <div class="ap-section-body">
                <form method="post" action="<?= e(admin_url('push')) ?>" data-push-form data-unsaved-warning novalidate>
                    <?= csrf_field() ?>
                    <div class="ap-push-compose">
                        <div class="ap-push-fields">
                            <?= Form::field(['name' => 'title', 'label' => 'Title', 'value' => '', 'placeholder' => 'Our summer sale starts today', 'help' => 'Up to 60 letters.', 'attrs' => ['maxlength' => 60, 'data-push-title' => true], 'required' => true]) ?>
                            <?= Form::field(['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rows' => 3, 'value' => '', 'placeholder' => 'Up to 30% off for three days only.', 'help' => 'Up to 160 letters. Short is best.', 'attrs' => ['maxlength' => 160, 'data-push-body' => true]]) ?>
                            <?= Form::field(['name' => 'url', 'label' => 'Opens this page', 'value' => '', 'placeholder' => 'sale or https://…', 'help' => 'Empty: the home page.', 'attrs' => ['list' => 'pushPages']]) ?>
                            <?= Form::field(['name' => 'image', 'label' => 'Large picture (optional)', 'type' => 'image', 'value' => '', 'help' => 'Shown by Android and Windows. 2:1 works best.']) ?>
                        </div>
                        <div class="ap-push-preview" aria-hidden="true">
                            <div class="ap-push-preview-label">Preview</div>
                            <div class="ap-push-card">
                                <div class="ap-push-app"><span class="ap-push-dot"></span><?= e(\AP\Modules\Pwa\AppSite::shortName()) ?> · now</div>
                                <strong data-push-preview-title>Your title</strong>
                                <span data-push-preview-body>Your message shows here.</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 mb-3">
                        <button type="submit" class="btn btn-primary"<?= $ready ? '' : ' disabled' ?> data-confirm="Send this notification to <?= number_format((int) $stats['subscribers']) ?> browser(s) now?"><i class="fa-regular fa-paper-plane me-1"></i>Send to everyone</button>
                        <button type="button" class="btn btn-outline-secondary" data-push-test data-url="<?= e(admin_url('push/test')) ?>"<?= $ready ? '' : ' disabled' ?>><i class="fa-solid fa-flask me-1"></i>Send me a test</button>
                    </div>
                </form>
                <?php if (class_exists(\AP\Modules\Pages\Pages::class) && \AP\Modules\Pages\Pages::ready()): ?>
                    <datalist id="pushPages"><?php foreach (\AP\Modules\Pages\Pages::db()->table('pages')->whereNull('deleted_at')->where('status', 'published')->orderBy('path')->limit(300)->get() as $p): ?><option value="<?= e($p['path']) ?>"><?= e($p['title']) ?></option><?php endforeach; ?></datalist>
                <?php endif; ?>
            </div>
        </section>

        <section class="ap-section">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Sent before</h2></div>
            <?php if (!$history): ?>
                <div class="ap-section-body"><?= Ui::emptyState('Nothing sent yet.', 'Notifications you send show up here with how many were delivered and opened.', null, null, 'fa-bell') ?></div>
            <?php else: ?>
                <div class="table-responsive"><table class="table mb-0 align-middle">
                    <thead><tr><th>Notification</th><th>To</th><th class="text-end">Delivered</th><th class="text-end">Opened</th><th>Status</th><th>When</th></tr></thead><tbody>
                    <?php foreach ($history as $m): $b = $badge[$m['status']] ?? [ucfirst((string) $m['status']), 'secondary']; ?>
                        <tr>
                            <td><strong><?= e($m['title']) ?></strong><?php if ($m['body']): ?><div class="small text-muted"><?= e(mb_strimwidth((string) $m['body'], 0, 90, '…')) ?></div><?php endif; ?></td>
                            <td class="small"><?= $m['audience'] === 'all' ? 'Everyone' : 'Test' ?></td>
                            <td class="text-end"><?= number_format((int) $m['sent']) ?><span class="text-muted small"> / <?= number_format((int) $m['total']) ?></span><?php if ((int) $m['failed'] > 0): ?><div class="small text-danger"><?= (int) $m['failed'] ?> failed</div><?php endif; ?></td>
                            <td class="text-end"><?= number_format((int) $m['clicks']) ?></td>
                            <td><?= Ui::badge($b[0], $b[1]) ?></td>
                            <td class="small text-muted text-nowrap"><?= e(format_date((string) $m['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>
    </div>
    <aside class="ap-settings-aside">
        <div class="ap-aside-card" data-push-me data-key="<?= e($cfgKey) ?>" data-sw="<?= e(url('sw.js')) ?>" data-subscribe="<?= e(url('push/subscribe')) ?>">
            <h2><i class="fa-solid fa-laptop"></i>This browser</h2>
            <p data-push-me-text><?= $mine > 0 ? 'Your browser gets notifications, so "Send me a test" reaches you.' : 'Allow notifications on this browser to receive your own tests.' ?></p>
            <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" data-push-allow<?= $ready ? '' : ' disabled' ?>><i class="fa-regular fa-bell me-1"></i>Allow on this browser</button>
        </div>
        <div class="ap-aside-card"><h2><i class="fa-regular fa-lightbulb"></i>Good notifications</h2><ul>
            <li>Say what is in it for the reader in the title.</li>
            <li>Send at most a few a week, at a time people read their phone.</li>
            <li>Link to the exact page, not just the home page.</li>
        </ul></div>
    </aside>
</div>
<?php $this->endSection(); ?>
