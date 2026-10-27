<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$err = form_errors('ip');
?>
<?php $this->section('content'); ?>
<div class="ap-hub">
    <div class="ap-hub-nav"><?php $this->include('spam::_nav', ['tab' => $tab]); ?></div>
    <div class="ap-hub-body ap-sections">
        <section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-ban" aria-hidden="true"></i>Block an address</h2></div>
            <div class="ap-section-body pb-3">
                <p class="ap-section-intro">A blocked address cannot send any form on the site. Use a range such as 203.0.113.0/24 to block a whole network. Your own address is <code><?= e($me) ?></code>.</p>
                <form method="post" action="<?= e(admin_url('spam/blocked')) ?>" class="row g-2 align-items-end" novalidate><?= csrf_field() ?>
                    <div class="col-md-4"><label class="form-label" for="bIp">Address or range</label><input class="form-control<?= $err ? ' is-invalid' : '' ?>" id="bIp" name="ip" value="<?= e((string) old('ip', $prefill)) ?>" placeholder="203.0.113.7" required>
                        <?php foreach ($err as $m): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endforeach; ?></div>
                    <div class="col-md-4"><label class="form-label" for="bNote">Note</label><input class="form-control" id="bNote" name="note" value="<?= e((string) old('note', '')) ?>" placeholder="Why it is blocked" maxlength="190"></div>
                    <div class="col-md-2"><label class="form-label" for="bDays">For</label><select class="form-select" id="bDays" name="days"><option value="0">Always</option><option value="1">1 day</option><option value="7">7 days</option><option value="30">30 days</option><option value="365">A year</option></select></div>
                    <div class="col-md-2"><button class="btn btn-danger w-100" type="submit"><i class="fa-solid fa-ban me-1"></i>Block</button></div>
                </form>
            </div></section>
        <?php if (!$rows): ?>
            <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No blocked addresses.', 'Block addresses from the log or the overview with one click, or type one above.', null, null, 'fa-ban') ?></div></div>
        <?php else: ?>
            <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>Address</th><th>Note</th><th class="text-end">Stopped</th><th>Until</th><th>Added</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $r): $expired = $r['expires_at'] !== null && (string) $r['expires_at'] < gmdate('Y-m-d H:i:s'); ?>
                    <tr class="<?= $expired ? 'text-muted' : '' ?>">
                        <td><code class="ap-path"><?= e((string) $r['ip']) ?></code></td>
                        <td class="small"><?= e((string) ($r['note'] ?? '')) ?></td>
                        <td class="text-end"><?= number_format((int) $r['hits']) ?></td>
                        <td class="small text-nowrap"><?= $r['expires_at'] ? e(format_date((string) $r['expires_at'])) . ($expired ? ' (ended)' : '') : 'Always' ?></td>
                        <td class="small text-muted text-nowrap"><?= e(format_date((string) $r['created_at'])) ?></td>
                        <td class="text-end"><form method="post" action="<?= e(admin_url('spam/blocked/' . (int) $r['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Unblock</button></form></td>
                    </tr>
                <?php endforeach; ?></tbody></table></div></div>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
