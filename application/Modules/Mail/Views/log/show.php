<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(admin_url('mail/log')) ?>"><?= e(__('core.back')) ?></a>
        <?php if ($m['body']): ?><form method="post" action="<?= e(admin_url('mail/log/' . (int) $m['id'] . '/resend')) ?>" data-confirm="Send this email again?"><?= csrf_field() ?><button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Send again</button></form><?php endif; ?>
    </div>
<?php $this->endSection(); ?>
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><dl class="row mb-0 small">
    <dt class="col-sm-2">Status</dt><dd class="col-sm-10"><?= Ui::badge(ucfirst($m['status']), ['sent' => 'success', 'failed' => 'danger'][$m['status']] ?? 'secondary') ?></dd>
    <dt class="col-sm-2">To</dt><dd class="col-sm-10"><?= e($m['to_address']) ?></dd>
    <dt class="col-sm-2">Queued/created</dt><dd class="col-sm-10"><?= e(format_date($m['created_at'])) ?></dd>
    <?php if ($m['sent_at']): ?><dt class="col-sm-2">Sent</dt><dd class="col-sm-10"><?= e(format_date($m['sent_at'])) ?> via <?= e($m['driver']) ?></dd><?php endif; ?>
    <?php if ($m['template']): ?><dt class="col-sm-2">Template</dt><dd class="col-sm-10"><code><?= e($m['template']) ?></code></dd><?php endif; ?>
    <?php if ($m['error']): ?><dt class="col-sm-2">Problem</dt><dd class="col-sm-10 text-danger"><?= e($m['error']) ?></dd><?php endif; ?>
</dl></div></div>
<?php if ($m['body']): ?>
<div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Message</div><div class="card-body p-0"><iframe title="Email" sandbox="" style="width:100%;height:560px;border:0" srcdoc="<?= e($m['body']) ?>"></iframe></div></div>
<?php else: ?><p class="text-muted">The message text was not kept for this entry.</p><?php endif; ?>
<?php $this->endSection(); ?>
