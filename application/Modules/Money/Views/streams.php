<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<p class="text-muted">An income stream is a source of work, such as client work, online sales or a market stall. Give payments, invoices and costs a stream to see how each one is doing on the dashboard and in reports. Deleting a stream that is in use archives it: old records keep its name, but it is no longer offered for new ones.</p>
<form method="post" action="<?= e(admin_url('money/streams/save')) ?>" data-unsaved-warning><?= csrf_field() ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>Name</th><th>Colour</th><th class="text-center">Offered For New Records</th><th class="text-end">Payments</th><th class="text-end">Expenses</th><th class="text-end">Invoices</th><?php if ($canEdit): ?><th aria-label="Delete"></th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($rows as $s): $u = $uses[(int) $s['id']] ?? []; ?>
            <tr>
                <td><input class="form-control form-control-sm fw-semibold" name="s[<?= (int) $s['id'] ?>][name]" value="<?= e((string) $s['name']) ?>" maxlength="120" aria-label="Name of stream <?= e((string) $s['name']) ?>"<?= $canEdit ? '' : ' disabled' ?>></td>
                <td><input class="form-control form-control-color" type="color" name="s[<?= (int) $s['id'] ?>][colour]" value="<?= e((string) $s['colour']) ?>" aria-label="Colour of <?= e((string) $s['name']) ?>"<?= $canEdit ? '' : ' disabled' ?>></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="s[<?= (int) $s['id'] ?>][active]" value="1"<?= (int) $s['active'] ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?> aria-label="<?= e((string) $s['name']) ?> is offered for new records"></td>
                <td class="text-end"><a href="<?= e(admin_url('money/income?year=all&stream=' . (int) $s['id'])) ?>"><?= (int) ($u['payments'] ?? 0) ?></a></td>
                <td class="text-end"><a href="<?= e(admin_url('money/expenses?year=all&stream=' . (int) $s['id'])) ?>"><?= (int) ($u['expenses'] ?? 0) ?></a></td>
                <td class="text-end"><?= (int) ($u['invoices'] ?? 0) ?></td>
                <?php if ($canEdit): ?><td class="text-end"><button class="mn-icon" type="submit" formaction="<?= e(admin_url('money/streams/' . (int) $s['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete <?= e((string) $s['name']) ?>? If records use it, it is archived instead." aria-label="Delete <?= e((string) $s['name']) ?>" title="Delete"><i class="fa-solid fa-trash"></i></button></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-muted py-4">No income streams yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    <?php if ($canEdit): ?>
    <section class="ap-section mt-3"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-plus" aria-hidden="true"></i>Add An Income Stream</h2></div><div class="ap-section-body"><div class="row g-2">
        <div class="col-md-9"><input class="form-control" name="new_name" maxlength="120" placeholder="Name" aria-label="New stream name"></div>
        <div class="col-md-3"><input class="form-control form-control-color w-100" type="color" name="new_colour" value="#4f6bed" aria-label="Colour of the new stream"></div>
    </div></div></section>
    <div class="mt-3"><button class="btn btn-primary mn-btn" type="submit" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save</button></div>
    <?php endif; ?>
</form>
</div>
<?php $this->endSection(); ?>
