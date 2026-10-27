<?php declare(strict_types=1);
/** Filter bar shared by the lists. Needs $action, $year, $years, $q and optionally $extra (html) */
?>
<form method="get" action="<?= e($action) ?>" class="ap-money-filters d-flex flex-wrap align-items-center gap-2 mb-3" role="search">
    <select class="form-select form-select-sm w-auto" name="year" aria-label="Tax year" data-autosubmit>
        <option value="all"<?= $year === 'all' ? ' selected' : '' ?>>All Years</option>
        <?php foreach ($years as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>>Tax year <?= e($y) ?></option><?php endforeach; ?>
    </select>
    <?= $extra ?? '' ?>
    <input type="search" class="form-control form-control-sm w-auto" name="q" value="<?= e($q) ?>" placeholder="Search" aria-label="Search" data-shortcut-search>
    <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
    <?php if (\AP\Modules\Money\Money::$filtering): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($action) ?>?clear=1" title="Forget the filters and show everything"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Clear Filters</a><?php endif; ?>
</form>
