<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Notifications\Notifications;

$this->extend('admin::layout');
$tabs = ['all' => 'Everything', 'unread' => 'Not Read Yet'] + array_map(static fn($k) => $k[0], Notifications::KINDS);
?>
<?php $this->section('actions'); ?>
<?php if ($unread > 0): ?><form method="post" action="<?= e(admin_url('notifications/read')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-check-double me-1"></i>Mark All Read</button></form><?php endif; ?>
<?php if ($rows): ?><form method="post" action="<?= e(admin_url('notifications/clear')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit" data-confirm="Remove every notification?"><i class="fa-solid fa-trash me-1"></i>Clear All</button></form><?php endif; ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/notifications')) ?>"><i class="fa-solid fa-gear me-1"></i>Settings</a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-bell-page">
    <nav class="ap-bell-filters" aria-label="Show"><?php foreach ($tabs as $k => $label): ?><a class="<?= $filter === $k ? 'is-on' : '' ?>" href="<?= e(admin_url('notifications') . ($k === 'all' ? '' : '?show=' . $k)) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
    <?php if (!$rows): ?>
        <div class="ap-section"><div class="ap-section-body text-muted text-center py-5"><i class="fa-regular fa-bell-slash fa-2x mb-2 d-block"></i>Nothing here. You are all caught up.</div></div>
    <?php else: ?>
        <div class="ap-section ap-bell-all"><?php foreach ($rows as $r): ?><?= Notifications::itemHtml($r) ?><?php endforeach; ?></div>
        <div class="d-flex justify-content-between mt-3">
            <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(admin_url('notifications') . '?show=' . $filter . '&page=' . ($page - 1)) ?>">Newer</a><?php else: ?><span></span><?php endif; ?>
            <?php if ($more): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(admin_url('notifications') . '?show=' . $filter . '&page=' . ($page + 1)) ?>">Older</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
