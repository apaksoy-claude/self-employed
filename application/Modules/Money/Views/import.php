<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$r = is_array($report ?? null) ? $report : null;
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php if ($r): ?>
    <section class="ap-section mb-3"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i>What Was Imported</h2></div>
        <div class="table-responsive"><table class="table mb-0"><tbody>
            <?php foreach (['categories' => 'Categories', 'contacts' => 'Clients', 'expenses' => 'Expenses', 'invoices' => 'Invoices', 'invoice_lines' => 'Invoice lines', 'income' => 'Payments', 'files' => 'Attached files'] as $k => $label): ?>
                <tr><td><?= e($label) ?></td><td class="text-end"><?= (int) ($r[$k] ?? 0) ?></td></tr>
            <?php endforeach; ?>
            <tr><td>Already there, left alone</td><td class="text-end"><?= (int) ($r['skipped_existing'] ?? 0) ?></td></tr>
            <?php foreach ((array) ($r['totals'] ?? []) as $k => $amt): ?><tr class="fw-semibold"><td>Total of <?= e((string) $k) ?></td><td class="text-end"><?= e(Money::gbp($amt)) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <div class="ap-section-body small text-muted">Compare these totals with the same totals in RISE before you trust the new records.</div>
    </section>
    <?php foreach (['missing_files' => 'Files named in the backup but not found', 'orphan_payments' => 'Payments for invoices that were deleted in RISE (kept as income without an invoice)', 'unfiled_categories' => 'Categories with no tax box chosen (set them on the Categories page)', 'warnings' => 'Warnings'] as $k => $label): if (empty($r[$k])) { continue; } ?>
        <div class="alert alert-warning"><strong><?= e($label) ?> (<?= count((array) $r[$k]) ?>)</strong>
            <ul class="mb-0 mt-1 small"><?php foreach (array_slice((array) $r[$k], 0, 25) as $line): ?><li><?= e(is_array($line) ? implode(' · ', array_map('strval', $line)) : (string) $line) ?></li><?php endforeach; ?></ul></div>
    <?php endforeach; ?>
<?php endif; ?>

<section class="ap-section" style="max-width:820px"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-file-import" aria-hidden="true"></i>Import From RISE CRM</h2></div>
<form method="post" action="<?= e(admin_url('money/import')) ?>" enctype="multipart/form-data" class="ap-section-body" data-loading-form><?= csrf_field() ?>
    <p>Brings over clients, expense categories, expenses, invoices, payments and the receipts attached to expenses. Nothing in RISE is changed. You can run it again safely: records that came over before are skipped (<?= (int) $already ?> remembered so far).</p>
    <div class="mb-3"><label class="form-label" for="dump">Database backup (.sql from phpMyAdmin)</label>
        <input class="form-control" type="file" id="dump" name="dump" accept=".sql">
        <div class="form-text">The server accepts files up to <?= (int) $maxMb ?> MB. For a bigger backup, copy it into <code><?= e($dropPath) ?></code> with FTP and choose it below.</div></div>
    <?php if ($waiting): ?>
        <div class="mb-3"><label class="form-label" for="server_dump">Or a backup already on the server</label>
            <select class="form-select" id="server_dump" name="server_dump"><option value="">None</option><?php foreach ($waiting as $w): ?><option value="<?= e($w) ?>"><?= e($w) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="mb-3"><label class="form-label" for="attachments">Attached files (zip of the timeline_files folder)</label>
        <input class="form-control" type="file" id="attachments" name="attachments" accept=".zip">
        <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="use_server_files" value="1"> <span class="form-check-label">Use the files already copied into the import folder instead</span></label></div>
    <button class="btn btn-primary mn-btn" type="submit" data-loading="Importing… this can take a minute"><i class="fa-solid fa-file-import me-1"></i>Start Import</button>
</form></section>
</div>
<?php $this->endSection(); ?>
