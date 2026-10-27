<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<p class="text-muted">Each kind of cost goes into one box of the Self Assessment self-employment pages. Check these once; every report follows them. A tick in "Counts as a business cost" means it is taken off profit. "Claimable with mileage rate" is for parking and tolls: they stay as costs even on a vehicle that uses the mileage rate.</p>
<form method="post" action="<?= e(admin_url('money/categories/save')) ?>" data-unsaved-warning><?= csrf_field() ?>
    <?php if ($canEdit): ?>
    <section class="ap-section mb-3"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-plus" aria-hidden="true"></i>Add A Category</h2></div><div class="ap-section-body"><div class="row g-2">
        <div class="col-md-5"><input class="form-control" name="new_name" maxlength="150" placeholder="Name" aria-label="New category name"></div>
        <div class="col-md-5"><select class="form-select" name="new_box" aria-label="Box for the new category"><?php foreach (Money::HMRC_BOXES as $k => $label): ?><option value="<?= e($k) ?>"<?= $k === 'other' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary mn-btn" type="submit" name="add" value="1"><i class="fa-solid fa-plus me-1"></i>Add</button></div>
    </div></div></section>

    <?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>Category</th><th>Box on the tax return</th><th class="text-center">Counts as a business cost</th><th class="text-center">Claimable with mileage rate</th><th class="text-end">Expenses</th><th class="text-end">Total</th><?php if ($canEdit): ?><th aria-label="Delete"></th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($cats as $c): $u = $used[(int) $c['id']] ?? [0, 0.0]; ?>
            <tr>
                <td><?php if ($canEdit): ?><input class="form-control form-control-sm fw-semibold" name="cat[<?= (int) $c['id'] ?>][name]" value="<?= e((string) $c['name']) ?>" maxlength="150" aria-label="Name of category <?= e((string) $c['name']) ?>"><?php else: ?><span class="fw-semibold"><?= e((string) $c['name']) ?></span><?php endif; ?></td>
                <td><select class="form-select form-select-sm" name="cat[<?= (int) $c['id'] ?>][hmrc_box]" aria-label="Box for <?= e((string) $c['name']) ?>"<?= $canEdit ? '' : ' disabled' ?>>
                    <?php foreach (Money::HMRC_BOXES as $k => $label): ?><option value="<?= e($k) ?>"<?= $c['hmrc_box'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="cat[<?= (int) $c['id'] ?>][allowable]" value="1"<?= $c['allowable'] ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?> aria-label="<?= e((string) $c['name']) ?> counts as a business cost"></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="cat[<?= (int) $c['id'] ?>][with_mileage]" value="1"<?= !empty($c['with_mileage']) ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?> aria-label="<?= e((string) $c['name']) ?> can be claimed on top of the mileage rate"></td>
                <td class="text-end"><a href="<?= e(admin_url('money/expenses?year=all&category=' . (int) $c['id'])) ?>"><?= (int) $u[0] ?></a></td>
                <td class="text-end text-nowrap"><?= e(Money::gbp($u[1])) ?></td>
                <?php if ($canEdit): ?><td class="text-end"><button class="mn-icon" type="submit" formaction="<?= e(admin_url('money/categories/' . (int) $c['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete <?= e((string) $c['name']) ?>?<?= (int) $u[0] > 0 ? ' Its ' . (int) $u[0] . ' expenses will have no category.' : '' ?>" aria-label="Delete <?= e((string) $c['name']) ?>" title="Delete"><i class="fa-solid fa-trash"></i></button></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!empty($used[0])): ?><tr class="table-warning"><td colspan="4">No category</td><td class="text-end"><a href="<?= e(admin_url('money/expenses?year=all&category=-1')) ?>"><?= (int) $used[0][0] ?></a></td><td class="text-end"><?= e(Money::gbp($used[0][1])) ?></td><?php if ($canEdit): ?><td></td><?php endif; ?></tr><?php endif; ?>
        </tbody></table></div></div>
    <?php if ($canEdit): ?><div class="mt-3"><button class="btn btn-primary mn-btn" type="submit" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save</button></div><?php endif; ?>
</form>
</div>
<?php $this->endSection(); ?>
