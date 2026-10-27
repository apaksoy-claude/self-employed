<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $m */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$reply = 'mailto:' . rawurlencode((string) $m['email']) . '?subject=' . rawurlencode('Re: ' . ($m['subject'] ?: 'Your message'));
$statusForm = static fn(string $to, string $label, string $class, string $icon, string $extra = ''): string => '<form method="post" action="' . e(admin_url('messages/' . (int) $m['id'] . '/status')) . '" class="d-inline">'
    . csrf_field() . '<input type="hidden" name="status" value="' . $to . '">' . $extra . '<button class="btn ' . $class . '"><i class="fa-solid ' . $icon . ' me-1"></i>' . e($label) . '</button></form>';
?>
<?php $this->section('actions'); ?>
<?php if ($prev): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('messages/' . (int) $prev)) ?>" title="Newer message"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
<?php if ($next): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('messages/' . (int) $next)) ?>" title="Older message"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-settings ap-two">
    <div class="ap-sections">
        <?php if ($m['status'] === 'spam'): ?>
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                <span><i class="fa-solid fa-shield-halved me-2"></i>Held as possible spam<?= $m['spam_reason'] ? ': ' . e((string) $m['spam_reason']) : '' ?>. It was not emailed to you.</span>
                <?= $statusForm('new', 'Not spam, send it to me', 'btn-sm btn-dark', 'fa-check', '<input type="hidden" name="send" value="1">') ?>
            </div>
        <?php endif; ?>
        <section class="ap-section">
            <div class="ap-section-body ap-message">
                <div class="ap-message-head">
                    <span class="ap-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $m['name'], 0, 1))) ?></span>
                    <div><strong><?= e((string) $m['name']) ?></strong> <span class="text-muted">&lt;<?= e((string) $m['email']) ?>&gt;</span>
                        <div class="small text-muted"><?= e(format_date((string) $m['created_at'])) ?><?= $m['phone'] ? ' · ' . e((string) $m['phone']) : '' ?></div></div>
                </div>
                <?php if ($m['subject']): ?><h2 class="h5 mt-3 mb-2"><?= e((string) $m['subject']) ?></h2><?php endif; ?>
                <div class="ap-message-body"><?= nl2br(e((string) $m['message'])) ?></div>
                <div class="d-flex flex-wrap gap-2 mt-3 mb-2">
                    <a class="btn btn-primary" href="<?= e($reply) ?>"><i class="fa-solid fa-reply me-1"></i>Reply by email</a>
                    <?php if ($m['phone']): ?><a class="btn btn-outline-secondary" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $m['phone'])) ?>"><i class="fa-solid fa-phone me-1"></i>Call</a><?php endif; ?>
                    <?php if ($m['status'] !== 'replied'): ?><?= $statusForm('replied', 'Mark as answered', 'btn-outline-success', 'fa-check-double') ?><?php endif; ?>
                    <?php if ($m['status'] !== 'spam'): ?><?= $statusForm('spam', 'Spam', 'btn-outline-warning', 'fa-ban') ?><?php endif; ?>
                    <?= $statusForm('new', 'Mark as new', 'btn-outline-secondary', 'fa-envelope') ?>
                    <form method="post" action="<?= e(admin_url('messages/' . (int) $m['id'] . '/delete')) ?>" class="d-inline" data-confirm="Delete this message?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-trash-can me-1"></i>Delete</button></form>
                </div>
            </div>
        </section>
    </div>
    <aside class="ap-settings-aside">
        <div class="ap-aside-card"><h2><i class="fa-solid fa-circle-info"></i>Details</h2>
            <div class="ap-status-rows">
                <div><span>Status</span><strong><?= e(['new' => 'New', 'read' => 'Read', 'replied' => 'Answered', 'spam' => 'Spam'][$m['status']] ?? $m['status']) ?></strong></div>
                <?php if ($m['page_url']): ?><div><span>Sent from</span><strong class="ap-break"><a href="<?= e((string) $m['page_url']) ?>" target="_blank" rel="noopener"><?= e((string) (parse_url((string) $m['page_url'], PHP_URL_PATH) ?: '/')) ?></a></strong></div><?php endif; ?>
                <div><span>Internet address</span><strong><?= e((string) $m['ip']) ?></strong></div>
            </div>
            <?php if (can('spam.manage') && $m['ip']): ?><a class="btn btn-sm btn-outline-danger w-100 mt-2" href="<?= e(admin_url('spam/blocked') . '?ip=' . rawurlencode((string) $m['ip'])) ?>"><i class="fa-solid fa-ban me-1"></i>Block this address</a><?php endif; ?>
        </div>
        <?php if ($others): ?>
            <div class="ap-aside-card"><h2><i class="fa-solid fa-clock-rotate-left"></i>Earlier from <?= e(explode(' ', (string) $m['name'])[0]) ?></h2><ul class="list-unstyled ms-0 ps-0">
                <?php foreach ($others as $o): ?><li><a href="<?= e(admin_url('messages/' . (int) $o['id'])) ?>"><?= e(mb_strimwidth((string) ($o['subject'] ?: $o['message']), 0, 50, '…')) ?></a> <span class="text-muted"><?= e(format_date((string) $o['created_at'])) ?></span></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
    </aside>
</div>
<?php $this->endSection(); ?>
