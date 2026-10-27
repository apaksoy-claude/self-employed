<?php declare(strict_types=1);
use AP\Modules\Money\Filters;
/**
 * The richer filter bar. Needs: $action, $year, $years, $q, $period, $from, $to, $query (what is in the address now),
 * $fields (list of ['name','label','options'=>[value=>label],'value'] or ['label','min'=>['name','value'],'max'=>[...]]),
 * $chips (list of [text, [names to drop]]), $reset (address with every filter off).
 */
$open = (bool) $chips;
?>
<form method="get" action="<?= e($action) ?>" class="mn-filters mb-3" role="search">
    <div class="ap-money-filters d-flex flex-wrap align-items-center gap-2">
        <select class="form-select form-select-sm w-auto" name="year" aria-label="Tax year" data-autosubmit>
            <option value="all"<?= $year === 'all' ? ' selected' : '' ?>>All Years</option>
            <?php foreach ($years as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>>Tax year <?= e($y) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm w-auto" name="period" aria-label="Within" data-period>
            <?php foreach (Filters::PERIODS as $k => $label): ?><option value="<?= e($k) ?>"<?= $period === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <span class="d-inline-flex align-items-center gap-1 mn-custom"<?= $period === 'custom' ? '' : ' hidden' ?>>
            <input type="text" class="form-control form-control-sm" name="from" value="<?= e($from) ?>" data-date autocomplete="off" placeholder="From" aria-label="From date" style="width:8.5rem">
            <span class="text-muted small">to</span>
            <input type="text" class="form-control form-control-sm" name="to" value="<?= e($to) ?>" data-date autocomplete="off" placeholder="To" aria-label="To date" style="width:8.5rem">
        </span>
        <input type="search" class="form-control form-control-sm w-auto flex-grow-1" style="min-width:10rem;max-width:18rem" name="q" value="<?= e($q) ?>" placeholder="Search" aria-label="Search" data-shortcut-search>
        <button class="btn btn-sm btn-outline-secondary mn-fbtn" type="button" data-filter-toggle aria-expanded="<?= $open ? 'true' : 'false' ?>"><i class="fa-solid fa-sliders me-1"></i>More Filters<?= $chips ? ' <span class="mn-count">' . count($chips) . '</span>' : '' ?></button>
        <button class="btn btn-sm btn-primary mn-fbtn" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Apply</button>
        <?php if (\AP\Modules\Money\Money::$filtering): ?><a class="btn btn-sm btn-outline-secondary mn-fbtn" href="<?= e($reset) ?>" title="Forget the filters and show everything"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Clear Filters</a><?php endif; ?>
    </div>
    <div class="mn-fpanel" data-filter-panel<?= $open ? '' : ' hidden' ?>>
        <?php foreach ($fields as $f): ?>
            <?php if (isset($f['min'])): ?>
                <div class="mn-ff"><label><?= e($f['label']) ?></label><div class="d-flex align-items-center gap-1">
                    <input type="text" inputmode="decimal" class="form-control form-control-sm" name="<?= e($f['min']['name']) ?>" value="<?= e((string) $f['min']['value']) ?>" placeholder="Least £" aria-label="<?= e($f['label']) ?> least">
                    <span class="text-muted small">to</span>
                    <input type="text" inputmode="decimal" class="form-control form-control-sm" name="<?= e($f['max']['name']) ?>" value="<?= e((string) $f['max']['value']) ?>" placeholder="Most £" aria-label="<?= e($f['label']) ?> most"></div></div>
            <?php else: ?>
                <div class="mn-ff"><label for="f-<?= e($f['name']) ?>"><?= e($f['label']) ?></label>
                    <select class="form-select form-select-sm" id="f-<?= e($f['name']) ?>" name="<?= e($f['name']) ?>">
                        <?php foreach ($f['options'] as $v => $label): ?><option value="<?= e((string) $v) ?>"<?= (string) $f['value'] === (string) $v ? ' selected' : '' ?>><?= e((string) $label) ?></option><?php endforeach; ?>
                    </select></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($chips): ?>
        <div class="mn-chips"><?php foreach ($chips as [$text, $drop]): ?><a class="mn-fchip" href="<?= e(Filters::without($action, $query, $drop)) ?>" title="Remove this filter"><?= e($text) ?><i class="fa-solid fa-xmark" aria-hidden="true"></i></a><?php endforeach; ?>
            <a class="small ms-1" href="<?= e($reset) ?>">Clear All</a></div>
    <?php endif; ?>
</form>
