<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Widgets;

$w ??= null;
$type = (string) old('type', $w['type'] ?? 'metric');
$series = (array) old('series', $w['series'] ?? ['income', 'costs']);
$vis = static fn(string $types): string => in_array($type, explode(' ', $types), true) ? '' : ' hidden';
$streams = ['0' => 'Any stream'] + array_map('strval', $streams);
$categories = ['0' => 'Any category'] + array_map('strval', $categories);
?>
<form method="post" action="<?= e(admin_url('money/dashboard/widgets/save')) ?>" class="mn-form" data-widget-form data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $w ? 'Change This Card' : 'Add A Card' ?></h2>
    <input type="hidden" name="id" value="<?= e((string) ($w['id'] ?? '')) ?>">
    <div class="mn-fields">
        <?php if ($w): ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
        <?php else: ?>
            <div class="mn-field" data-field="type"><label class="form-label" for="f_type">What Should It Show?</label>
                <select class="form-select" id="f_type" name="type" data-widget-type>
                    <optgroup label="Build Your Own">
                        <?php foreach (Widgets::CUSTOM as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </optgroup>
                    <?php if ($ready): ?><optgroup label="Ready-Made Cards"><?php foreach ($ready as $k => $b): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($b['label']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                </select>
                <div class="form-text">Ready-made cards are the ones you took off the dashboard earlier.</div>
                <div class="invalid-feedback" data-error></div></div>
        <?php endif; ?>

        <div class="mn-field" data-for="metric chart breakdown list"<?= $vis('metric chart breakdown list') ?>>
            <?= Fields::text('title', 'Title', $w['title'] ?? '', ['maxlength' => 60, 'placeholder' => 'Leave empty to name it for you']) ?>
        </div>
        <?= Fields::select('w', 'Width', Widgets::WIDTHS, (string) ($w['w'] ?? 4), ['col' => 'is-half']) ?>

        <div class="mn-field is-half" data-for="metric" data-field="measure"<?= $vis('metric') ?>><?= Fields::select('measure', 'Show', Widgets::MEASURES, $w['measure'] ?? 'income') ?></div>
        <div class="mn-field is-half" data-for="metric breakdown list" data-field="period"<?= $vis('metric breakdown list') ?>><?= Fields::select('period', 'Over', Widgets::PERIODS, $w['period'] ?? 'tax_year') ?></div>
        <div class="mn-field is-half" data-for="breakdown" data-field="by"<?= $vis('breakdown') ?>><?= Fields::select('by', 'Split', Widgets::BREAKDOWNS, $w['by'] ?? 'stream') ?></div>
        <div class="mn-field is-half" data-for="list" data-field="source"<?= $vis('list') ?>><?= Fields::select('source', 'Show', Widgets::SOURCES, $w['source'] ?? 'income') ?></div>
        <div class="mn-field is-half" data-for="list" data-field="count"<?= $vis('list') ?>><?= Fields::text('count', 'How Many', $w['count'] ?? 6, ['inputmode' => 'numeric', 'help' => 'Between 3 and 10.']) ?></div>

        <div class="mn-field is-half" data-for="chart" data-field="months"<?= $vis('chart') ?>><?= Fields::select('months', 'Months', ['3' => 'Last 3 months', '6' => 'Last 6 months', '12' => 'Last 12 months', '24' => 'Last 24 months'], (string) ($w['months'] ?? 12)) ?></div>
        <div class="mn-field is-half" data-for="chart" data-field="style"<?= $vis('chart') ?>><?= Fields::select('style', 'Drawn As', ['bars' => 'Bars', 'line' => 'Lines'], $w['style'] ?? 'bars') ?></div>
        <div class="mn-field" data-for="chart" data-field="series"<?= $vis('chart') ?>>
            <span class="form-label">Lines Or Bars To Show</span>
            <div class="mn-checks">
                <?php foreach (Widgets::SERIES as $k => $l): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="series[]" value="<?= e($k) ?>"<?= in_array($k, $series, true) ? ' checked' : '' ?>><span class="form-check-label"><?= e($l) ?></span></label><?php endforeach; ?>
            </div>
            <div class="invalid-feedback" data-error></div>
        </div>

        <div class="mn-field is-half" data-for="metric chart breakdown list" data-field="stream_id"<?= $vis('metric chart breakdown list') ?>><?= Fields::select('stream_id', 'Only This Income Stream', $streams, (string) ($w['stream_id'] ?? 0)) ?></div>
        <div class="mn-field is-half" data-for="metric chart breakdown list" data-field="category_id"<?= $vis('metric chart breakdown list') ?>><?= Fields::select('category_id', 'Only This Cost Category', $categories, (string) ($w['category_id'] ?? 0), ['help' => 'Choosing a category leaves income out.']) ?></div>
        <div class="mn-field" data-for="metric" data-field="compare"<?= $vis('metric') ?>>
            <label class="form-switch form-check"><input type="hidden" name="compare" value="0"><input class="form-check-input" type="checkbox" role="switch" name="compare" value="1"<?= (int) old('compare', $w['compare'] ?? 1) ? ' checked' : '' ?>> <span class="form-check-label">Compare with the period before</span></label>
        </div>
    </div>
    <?= Forms::buttons(admin_url(), !empty($modal)) ?>
</form>
