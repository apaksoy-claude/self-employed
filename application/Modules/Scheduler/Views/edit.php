<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\CronExpression;
use AP\Core\Form;

$this->extend('admin::layout');
$cron = (string) $task['cron'];
$preset = 'custom'; $time = '03:00'; $minute = 0; $weekday = 1; $monthday = 1;
$map = ['* * * * *' => 'minute', '*/5 * * * *' => '5min', '*/15 * * * *' => '15min', '*/30 * * * *' => '30min'];
if (isset($map[$cron])) { $preset = $map[$cron]; }
elseif (preg_match('/^(\d+) \* \* \* \*$/', $cron, $m)) { $preset = 'hourly'; $minute = (int) $m[1]; }
elseif (preg_match('/^(\d+) (\d+) \* \* \*$/', $cron, $m)) { $preset = 'daily'; $time = sprintf('%02d:%02d', $m[2], $m[1]); }
elseif (preg_match('/^(\d+) (\d+) \* \* ([0-6])$/', $cron, $m)) { $preset = 'weekly'; $time = sprintf('%02d:%02d', $m[2], $m[1]); $weekday = (int) $m[3]; }
elseif (preg_match('/^(\d+) (\d+) (\d+) \* \*$/', $cron, $m)) { $preset = 'monthly'; $time = sprintf('%02d:%02d', $m[2], $m[1]); $monthday = (int) $m[3]; }
$preset = old('preset', $preset);
?>
<?php $this->section('content'); ?>
<p class="text-muted"><?= e($def['description'] ?? '') ?></p>
<form method="post" action="<?= e(admin_url('system/scheduler/' . (int) $task['id'])) ?>" data-unsaved-warning novalidate>
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <?= Form::field(['name' => 'enabled', 'label' => 'This task is switched on', 'type' => 'checkbox', 'value' => (int) $task['enabled']]) ?>
        <?= Form::field(['name' => 'preset', 'label' => 'How often', 'type' => 'select', 'value' => $preset, 'options' => [
            'minute' => 'Every minute', '5min' => 'Every 5 minutes', '15min' => 'Every 15 minutes', '30min' => 'Every 30 minutes',
            'hourly' => 'Every hour', 'daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month', 'custom' => 'Custom (advanced)',
        ], 'attrs' => ['data-preset' => true]]) ?>
        <div class="row g-3">
            <div class="col-md-3" data-show="hourly"><?= Form::field(['name' => 'minute', 'label' => 'At minute', 'type' => 'number', 'value' => $minute, 'attrs' => ['min' => 0, 'max' => 59]]) ?></div>
            <div class="col-md-3" data-show="daily,weekly,monthly"><?= Form::field(['name' => 'time', 'label' => 'At time', 'type' => 'time', 'value' => $time, 'help' => 'In the site timezone (' . e((string) setting('general.timezone', 'UTC')) . ').']) ?></div>
            <div class="col-md-3" data-show="weekly"><?= Form::field(['name' => 'weekday', 'label' => 'On', 'type' => 'select', 'value' => $weekday, 'options' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday']]) ?></div>
            <div class="col-md-3" data-show="monthly"><?= Form::field(['name' => 'monthday', 'label' => 'On day of month', 'type' => 'number', 'value' => $monthday, 'attrs' => ['min' => 1, 'max' => 28]]) ?></div>
            <div class="col-md-6" data-show="custom"><?= Form::field(['name' => 'cron', 'label' => 'Cron expression', 'value' => $cron, 'help' => 'Five parts: minute hour day-of-month month weekday. Example: 30 3 * * 1-5 is 03:30 on weekdays.', 'attrs' => ['class' => 'font-monospace']]) ?></div>
        </div>
        <div class="small text-muted">Currently: <strong><?= e(CronExpression::valid($cron) ? (new CronExpression($cron))->describe() : $cron) ?></strong> &middot; default: <?= e((new CronExpression($default))->describe()) ?></div>
    </div></div>
    <?= Form::saveButtons(false, admin_url('system/scheduler')) ?>
</form>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
  var sel = document.querySelector('[data-preset]');
  function apply() { document.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = el.getAttribute('data-show').split(',').indexOf(sel.value) === -1; }); }
  sel.addEventListener('change', apply); apply();
})();
</script>
<?php $this->endSection(); ?>
