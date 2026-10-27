<?php declare(strict_types=1);
/** @var array $stats */
?>
<?php if (can('mail.manage')): ?>
<div class="ap-aside-card">
    <h2><i class="fa-solid fa-paper-plane"></i>Send a Test Email</h2>
    <p class="mb-2">Save your settings first, then send a test to any address.</p>
    <form method="post" action="<?= e(admin_url('mail/send-test')) ?>" class="d-flex gap-2"><?= csrf_field() ?>
        <input type="email" class="form-control form-control-sm" name="to" required value="<?= e((string) (auth_user()['email'] ?? '')) ?>" aria-label="Send the test to">
        <button class="btn btn-sm btn-primary">Send</button>
    </form>
</div>
<?php endif; ?>
<?php if (can('mail.log')): ?>
<div class="ap-aside-card">
    <h2 class="d-flex justify-content-between"><span><i class="fa-solid fa-inbox me-2"></i>Email Log</span><small class="text-muted fw-normal">last 7 days</small></h2>
    <dl class="ap-mail-stats">
        <dt>Sent</dt><dd><?= (int) $stats['sent'] ?></dd>
        <dt>Waiting</dt><dd><?= (int) $stats['waiting'] ?></dd>
        <dt>Failed</dt><dd class="<?= $stats['failed'] ? 'text-danger' : '' ?>"><?= (int) $stats['failed'] ?></dd>
    </dl>
    <a class="btn btn-sm btn-primary w-100" href="<?= e(admin_url('mail/log')) ?>">Open the Email Log</a>
</div>
<?php endif; ?>
