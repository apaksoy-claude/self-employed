<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $s */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$rows = $s['speed'];
uasort($rows, static fn($a, $b) => ($b['ms'] / max(1, $b['n'])) <=> ($a['ms'] / max(1, $a['n'])));
$slow = (int) setting('monitor.speed_slow', 1500);
?>
<?php $this->section('actions'); ?>
<form method="post" action="<?= e(admin_url('system/monitoring/run/speed')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-stopwatch me-1"></i>Measure now</button></form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-gauge-high"></i>Pages in the last 7 days, slowest first</h2></div>
    <?php if (!$rows): ?><div class="ap-section-body"><?= Ui::emptyState('Nothing measured yet.', 'Pages are measured every hour; press "Measure now" to start.', null, null, 'fa-stopwatch') ?></div>
    <?php else: ?><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Page</th><th class="text-end">Average</th><th>Speed</th><th class="text-end">Slow times</th></tr></thead><tbody>
        <?php foreach ($rows as $url => $p): $avg = (int) round($p['ms'] / max(1, $p['n'])); ?>
            <tr><td class="ap-break"><a href="<?= e((string) $url) ?>" target="_blank" rel="noopener"><?= e((string) (parse_url((string) $url, PHP_URL_PATH) ?: '/')) ?></a></td><td class="text-end"><?= number_format($avg) ?> ms</td>
                <td style="min-width:140px"><div class="ap-bar"><i style="width:<?= min(100, (int) round($avg / max(1, $slow) * 100)) ?>%;<?= $avg > $slow ? 'background:#dc2626' : '' ?>"></i></div></td>
                <td class="text-end"><?= (int) $p['slow'] ?> of <?= (int) $p['n'] ?></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<p class="small text-muted mt-2">Slow means more than <?= number_format($slow) ?> ms, measured from your own server. Big pictures and outside widgets are common causes.</p>
<?php $this->endSection(); ?>
