<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$qs = array_filter(['q' => $filters['q'], 'user' => $filters['user'] ?: null, 'action' => $filters['action'], 'when' => $filters['when'], 'from' => $filters['from'], 'to' => $filters['to'], 'per_page' => $filters['per_page']], static fn($v) => $v !== '' && $v !== null);
$details = static function (array $a): string {
    $after = json_decode((string) $a['after_values'], true);
    if (!is_array($after) || !$after) {
        return '';
    }
    $parts = [];
    foreach (array_slice($after, 0, 4, true) as $k => $v) {
        $parts[] = $k . ': ' . (is_scalar($v) || $v === null ? mb_strimwidth((string) $v, 0, 60, '…') : '…');
    }
    return implode(' · ', $parts);
};
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('logs/activity/export') . '?' . http_build_query($qs)) ?>"><i class="fa-solid fa-file-csv me-1"></i><?= e(__('core.export_csv')) ?></a>
<?php if (can('logs.manage')): ?><form method="post" action="<?= e(admin_url('logs/activity/purge')) ?>" data-confirm="Remove old activity entries?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-broom me-1"></i>Purge old entries</button></form><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('hubside'); ?>
<form class="ap-filter-card" method="get">
    <div class="ap-fc-title"><span>Filter activity</span><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
    <label class="ap-fc-label" for="fcQ">Search</label>
    <input type="search" class="form-control" id="fcQ" name="q" value="<?= e($filters['q']) ?>" placeholder="Action, details or email" data-shortcut-search>
    <label class="ap-fc-label">Who and what</label>
    <select class="form-select mb-2" name="user" aria-label="Who"><option value="0">Anyone</option>
        <?php foreach ($users as $uid => $uname): ?><option value="<?= (int) $uid ?>"<?= $filters['user'] === (int) $uid ? ' selected' : '' ?>><?= e($uname) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="action" aria-label="About"><option value="">About anything</option>
        <?php foreach ($areas as $k => $l): ?><option value="<?= e($k) ?>"<?= $filters['action'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <?= Ui::whenField($filters['when'], $filters['from'], $filters['to']) ?>
    <button class="btn btn-primary w-100 mt-3" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply filters</button>
    <?php if ($qs): ?><a class="d-block text-center small mt-2" href="<?= e(admin_url('logs/activity')) ?>">Clear the filters</a><?php endif; ?>
</form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState(__('core.no_results'), __('core.no_results_hint'), null, null, 'fa-list-check') ?></div></div>
<?php else: ?>
<div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle ap-log-table">
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th></tr></thead>
    <tbody>
    <?php foreach ($pager['data'] as $a): $has = $a['before_values'] || $a['after_values']; ?>
        <tr>
            <td class="text-nowrap small"><?= e(format_date($a['created_at'], 'd M Y H:i')) ?></td>
            <td class="small"><?= e($a['user_name'] ?? 'System') ?><div class="text-muted"><?= e((string) $a['ip']) ?></div></td>
            <td><div class="fw-medium"><?= e((string) $a['description']) ?></div><div class="small text-muted"><?= e(trim($a['subject_type'] . ($a['subject_id'] !== null && $a['subject_id'] !== '' ? ' #' . $a['subject_id'] : '')) ?: $a['action']) ?></div></td>
            <td class="small text-muted ap-break"><?= e($details($a)) ?>
                <?php if ($has): ?><button class="btn btn-link btn-sm p-0 d-block" type="button" data-bs-toggle="collapse" data-bs-target="#a<?= (int) $a['id'] ?>" aria-expanded="false">Before and after</button><?php endif; ?></td>
        </tr>
        <?php if ($has): ?>
        <tr class="collapse" id="a<?= (int) $a['id'] ?>"><td colspan="4" class="bg-body-tertiary"><div class="row g-3 small">
            <div class="col-md-6"><div class="fw-semibold">Before</div><pre class="ap-trace mb-0"><?= e($a['before_values'] ? (string) json_encode(json_decode((string) $a['before_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—') ?></pre></div>
            <div class="col-md-6"><div class="fw-semibold">After</div><pre class="ap-trace mb-0"><?= e($a['after_values'] ? (string) json_encode(json_decode((string) $a['after_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—') ?></pre></div>
        </div></td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="ap-table-foot"><span><?= number_format((int) $pager['total']) ?> total · page <?= (int) $pager['page'] ?> of <?= (int) $pager['last_page'] ?></span><?= Ui::pagination($pager, $qs) ?></div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
