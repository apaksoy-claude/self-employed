<?php declare(strict_types=1);
/** @var array $status @var array|null $result */
$tone = ['ready' => 'ok', 'setup' => 'warn', 'testing' => 'grey'][$status['state']] ?? 'grey';
$pill = ['ready' => 'Ready', 'setup' => 'Needs set-up', 'testing' => 'Test mode'][$status['state']] ?? '';
?>
<div class="ap-mail-status ap-mail-status-<?= e($tone) ?>">
    <span class="ap-mail-status-icon"><i class="fa-solid <?= $status['state'] === 'testing' ? 'fa-flask' : 'fa-paper-plane' ?>" aria-hidden="true"></i></span>
    <div class="ap-mail-status-text">
        <strong>Sending with <?= e($status['label']) ?><?= $status['target'] !== '' ? ' · ' . e($status['target']) : '' ?></strong>
        <span><?= $status['backup'] !== '' ? 'Backup: ' . e($status['backup']) : 'No backup way to send' ?><?php foreach ($status['problems'] as $p): ?> · <span class="text-warning-emphasis"><?= e($p) ?></span><?php endforeach; ?></span>
    </div>
    <span class="ap-pill ap-pill-<?= e($tone) ?>"><?= e($pill) ?></span>
    <?php if (can('mail.manage') && $status['driver'] !== 'log'): ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?= e(admin_url('mail/test-connection')) ?>" formnovalidate title="Connects and logs in without sending anything. Uses the saved settings."><i class="fa-solid fa-plug me-1"></i>Test Connection</button>
    <?php endif; ?>
</div>
<?php if ($result): ?>
    <div class="alert alert-<?= $result['ok'] ? 'success' : 'danger' ?> mb-0" role="status">
        <strong><?= e($result['title']) ?>:</strong> <?= e($result['message']) ?>
        <?php if (!empty($result['transcript'])): ?>
            <details class="mt-2"><summary class="small">Conversation with the server</summary><pre class="small mb-0 mt-2 ap-transcript"><?= e(implode("\n", $result['transcript'])) ?></pre></details>
        <?php endif; ?>
    </div>
<?php endif; ?>
