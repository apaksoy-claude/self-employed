<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Contact\Controllers\MessageController;

$this->extend('admin::layout');
$qs = array_filter(['tab' => $tab !== 'inbox' ? $tab : null, 'q' => $q]);
$badge = ['new' => ['New', 'primary'], 'read' => ['Read', 'secondary'], 'replied' => ['Answered', 'success'], 'spam' => ['Spam', 'warning']];
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/contact_form')) ?>"><i class="fa-solid fa-sliders me-1"></i>Form settings</a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if ($to === ''): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Messages are kept here but not emailed to anyone yet. Add an address in <a href="<?= e(admin_url('settings/contact_form')) ?>">Contact form settings</a>.</div>
<?php endif; ?>
<div class="ap-tabs-bar mb-3">
    <nav class="nav ap-tabs" aria-label="Messages">
        <?php foreach (MessageController::TABS as $k => $label): ?>
            <a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= e(admin_url('messages') . ($k !== 'inbox' ? '?tab=' . $k : '')) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="ap-tab-count"><?= (int) ($counts[$k] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="ap-tabs-search" role="search"><?php if ($tab !== 'inbox'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Name, email or words" aria-label="Search messages" data-shortcut-search></form>
</div>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState($q !== '' ? 'No message matches.' : 'No messages here.', 'Put the contact form on a page: type [contact-form], or press the envelope button in the page editor.', admin_url('pages'), 'Open Pages', 'fa-envelope-open-text') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('messages/bulk')) ?>"><?= csrf_field() ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-sm btn-outline-secondary" name="action" value="read">Mark as read</button>
        <button class="btn btn-sm btn-outline-secondary" name="action" value="replied">Mark as answered</button>
        <?php if ($tab !== 'spam'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="spam">Move to spam</button><?php endif; ?>
        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" data-confirm="Delete the ticked messages?">Delete</button>
    </div>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle ap-messages">
        <thead><tr><th style="width:32px"><input type="checkbox" class="form-check-input" id="bulkAll" aria-label="Tick all"></th><th>From</th><th>Message</th><th>Status</th><th>When</th></tr></thead><tbody>
        <?php foreach ($pager['data'] as $m): $b = $badge[$m['status']] ?? ['?', 'secondary']; ?>
            <tr class="<?= $m['status'] === 'new' ? 'is-new' : '' ?>">
                <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $m['id'] ?>" data-bulk-item aria-label="Tick"></td>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('messages/' . (int) $m['id'])) ?>"><?= e((string) $m['name']) ?></a><div class="small text-muted"><?= e((string) $m['email']) ?></div></td>
                <td><a class="text-reset text-decoration-none d-block" href="<?= e(admin_url('messages/' . (int) $m['id'])) ?>"><?php if ($m['subject']): ?><strong><?= e((string) $m['subject']) ?></strong> · <?php endif; ?><span class="text-muted"><?= e(mb_strimwidth((string) $m['message'], 0, 110, '…')) ?></span></a>
                    <?php if ($m['status'] === 'spam' && $m['spam_reason']): ?><div class="small text-warning-emphasis">Held: <?= e((string) $m['spam_reason']) ?></div><?php endif; ?></td>
                <td><?= Ui::badge($b[0], $b[1]) ?></td>
                <td class="small text-muted text-nowrap"><?= e(format_date((string) $m['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
</form>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
<?php $this->endSection(); ?>
