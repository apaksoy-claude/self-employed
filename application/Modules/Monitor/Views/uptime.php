<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<form method="post" action="<?= e(admin_url('system/monitoring/run/uptime')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-rotate me-1"></i>Check now</button></form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<section class="ap-section mb-3"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-triangle-exclamation"></i>Problems and slow answers</h2></div>
    <?php if (!$problems): ?><div class="ap-section-body"><?= Ui::emptyState('No problems.', 'Every check of the home page answered in time.', null, null, 'fa-circle-check') ?></div>
    <?php else: ?><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>When</th><th>Answer</th><th class="text-end">Time</th><th>Problem</th></tr></thead><tbody>
        <?php foreach (array_slice($problems, 0, 50) as $r): ?><tr><td class="small text-nowrap"><?= e(format_date((string) $r['created_at'], 'd M H:i')) ?></td><td><span class="ap-status-pill <?= (int) $r['ok'] ? 'is-queued' : 'is-failed' ?>"><?= (int) $r['status_code'] ?: 'none' ?></span></td>
            <td class="text-end"><?= number_format((int) $r['ms']) ?> ms</td><td class="small text-muted"><?= e((string) ($r['error'] ?: ((int) $r['ok'] ? 'Slow' : 'Error page'))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></section>
<section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-list"></i>Every check</h2></div>
    <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>When</th><th>Answer</th><th class="text-end">Time</th></tr></thead><tbody>
        <?php foreach (array_slice($rows, 0, 100) as $r): ?><tr><td class="small text-nowrap"><?= e(format_date((string) $r['created_at'], 'd M H:i')) ?></td><td><span class="ap-status-pill <?= (int) $r['ok'] ? 'is-sent' : 'is-failed' ?>"><?= (int) $r['status_code'] ?: 'none' ?></span></td><td class="text-end"><?= number_format((int) $r['ms']) ?> ms</td></tr><?php endforeach; ?>
    </tbody></table></div></section>
<?php $this->endSection(); ?>
