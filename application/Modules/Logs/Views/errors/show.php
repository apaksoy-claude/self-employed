<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Modules\Admin\Ui; $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<?php $this->section('subtitle'); ?><?= (int) $e['resolved'] ? Ui::badge('Fixed', 'success') : Ui::badge('Open', 'warning') ?> Seen <?= (int) $e['occurrences'] ?> time(s)<?php $this->endSection(); ?>
<?php $this->section('actions'); ?>
<?php if (can('logs.manage')): ?>
    <form method="post" action="<?= e(admin_url('logs/errors/bulk')) ?>"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $e['id'] ?>">
        <?php if ((int) $e['resolved']): ?><button class="btn btn-outline-secondary" name="action" value="reopen">Re-open</button>
        <?php else: ?><button class="btn btn-success" name="action" value="fix"><i class="fa-solid fa-check me-1"></i>Mark fixed</button><?php endif; ?>
    </form>
    <?php endif; ?>
<?php $this->endSection(); ?>
<?php if ($hint = \AP\Modules\Logs\ErrorHints::explain($e)): ?>
    <div class="ap-hint mb-3"><div><strong>What it means:</strong> <?= e($hint['means']) ?></div><div><strong>How to fix:</strong> <?= e($hint['fix']) ?></div>
        <?php if (!empty($hint['link'])): ?><a class="btn btn-sm btn-primary mt-2" href="<?= e($hint['link']) ?>"><?= e($hint['link_label']) ?> <i class="fa-solid fa-arrow-right ms-1"></i></a><?php endif; ?></div>
<?php endif; ?>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h6 text-muted"><?= e($e['class']) ?></h2>
    <p class="fs-5 text-break"><?= e($e['message']) ?></p>
    <dl class="row mb-0 small">
        <dt class="col-sm-3">File</dt><dd class="col-sm-9 ap-code text-break"><?= e($e['file']) ?>:<?= (int) $e['line'] ?></dd>
        <dt class="col-sm-3">Page</dt><dd class="col-sm-9 text-break"><?= e($e['method']) ?> <?= e($e['url']) ?></dd>
        <dt class="col-sm-3">Seen</dt><dd class="col-sm-9"><?= (int) $e['occurrences'] ?> time(s), first <?= e(format_date($e['first_seen'])) ?>, last <?= e(format_date($e['last_seen'])) ?></dd>
        <dt class="col-sm-3">Last visitor IP</dt><dd class="col-sm-9"><?= e($e['ip']) ?></dd>
    </dl>
</div></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">Stack trace</div><div class="card-body"><pre class="ap-trace mb-0"><?= e($e['trace']) ?></pre></div></div>
<?php if ($request_data): ?><div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">Request data (passwords and tokens hidden)</div><div class="card-body"><pre class="ap-trace mb-0"><?= e(json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div></div><?php endif; ?>
<?php $this->endSection(); ?>
