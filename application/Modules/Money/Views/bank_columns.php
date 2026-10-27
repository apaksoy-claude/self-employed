<?php declare(strict_types=1);
/** Bank Import: choose the columns of a CSV once. @var \AP\Core\View $this */
use AP\Modules\Money\Bank;

$this->extend('admin::layout');
$width = 0;
foreach ($table as $r) {
    $width = max($width, count($r));
}
$colsOf = array_flip((array) ($map['cols'] ?? []));
$headerRow = (int) old('header', (string) $map['header']);
$oldRoles = (array) old('role', []);
$err = form_errors('role') ?: form_errors('date_format');
?>
<?php $this->section('content'); ?>
<div class="mn-page bk-page">
    <p class="text-muted">This is the first statement in this layout. Say which column is which; save the layout and statements from this bank are read by themselves next time.</p>
    <form method="post" action="<?= e(admin_url('money/bank/' . $token . '/columns')) ?>" novalidate><?= csrf_field() ?>
        <section class="mn-card">
            <header><div><h2><i class="fa-solid fa-table-columns" aria-hidden="true"></i><?= e((string) $s['name']) ?></h2><p>The first lines of the file.</p></div></header>
            <?php if ($err): ?><div class="alert alert-warning py-2"><?= e((string) $err[0]) ?></div><?php endif; ?>
            <div class="table-responsive bk-preview"><table class="table mn-table align-middle mb-0 small">
                <thead><tr><th>Row</th>
                    <?php for ($c = 0; $c < $width; $c++): $role = (string) ($oldRoles[$c] ?? ($colsOf[$c] ?? 'skip')); ?>
                        <th><select class="form-select form-select-sm" name="role[<?= $c ?>]" aria-label="Column <?= $c + 1 ?>"><?php foreach (Bank::ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $role === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></th>
                    <?php endfor; ?></tr></thead>
                <tbody><?php foreach ($table as $i => $r): ?><tr class="<?= $i === $headerRow ? 'is-head' : '' ?>"><td class="text-muted"><?= $i + 1 ?></td><?php for ($c = 0; $c < $width; $c++): ?><td><?= e((string) ($r[$c] ?? '')) ?></td><?php endfor; ?></tr><?php endforeach; ?></tbody>
            </table></div>
            <div class="row mt-3">
                <div class="col-md-4 ap-field"><label class="form-label" for="bk_header">Column Names Are On</label>
                    <select class="form-select" id="bk_header" name="header"><option value="-1"<?= $headerRow === -1 ? ' selected' : '' ?>>No Row Of Names</option>
                        <?php foreach ($table as $i => $r): ?><option value="<?= $i ?>"<?= $headerRow === $i ? ' selected' : '' ?>>Row <?= $i + 1 ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4 ap-field"><label class="form-label" for="bk_fmt">Dates Are Written Like</label>
                    <select class="form-select" id="bk_fmt" name="date_format"><?php foreach (Bank::DATE_FORMATS as $f => $ex): ?><option value="<?= e($f) ?>"<?= (string) old('date_format', (string) ($map['date_format'] ?? 'd/m/Y')) === $f ? ' selected' : '' ?>><?= e($ex) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4 ap-field"><label class="form-label" for="bk_layout">Save This Layout As</label>
                    <input class="form-control" id="bk_layout" name="layout_name" maxlength="120" placeholder="My bank, business account" value="<?= e((string) old('layout_name', '')) ?>">
                    <div class="form-text">Leave empty to use it this time only.</div></div>
            </div>
            <label class="form-switch form-check mt-2"><input type="hidden" name="negate" value="0"><input class="form-check-input" type="checkbox" role="switch" name="negate" value="1"<?= !empty($map['negate']) ? ' checked' : '' ?>> <span class="form-check-label">Money out shows as a plus (some credit card statements)</span></label>
        </section>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary mn-btn" type="submit"><i class="fa-solid fa-arrow-right me-1" aria-hidden="true"></i>Continue</button>
        </div>
    </form>
    <form method="post" action="<?= e(admin_url('money/bank/' . $token . '/discard')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-link px-0" type="submit">Put It Away</button></form>
</div>
<?php $this->endSection(); ?>
