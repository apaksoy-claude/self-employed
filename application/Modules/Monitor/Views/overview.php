<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $s */
$this->extend('admin::layout');
$pct = static fn(?float $v): string => $v === null ? '—' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
$down = !empty($s['state']['down']);
?>
<?php $this->section('actions'); ?>
<form method="post" action="<?= e(admin_url('system/monitoring/run/uptime')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-rotate me-1"></i>Check now</button></form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<div class="ap-health-bar is-<?= $down ? 'bad' : 'ok' ?> mb-3">
    <span class="ap-health-icon"><i class="fa-solid <?= $down ? 'fa-circle-xmark' : 'fa-heart-pulse' ?>" aria-hidden="true"></i></span>
    <div class="ap-health-text"><strong><?= $down ? 'The website did not answer at the last check' : 'The website is up' ?></strong>
        <span><?= $s['last'] ? 'Last check ' . e(format_date((string) $s['last']['created_at'], 'd M H:i')) . ': ' . (int) $s['last']['status_code'] . ' in ' . number_format((int) $s['last']['ms']) . ' ms' : 'Not checked yet. Checks run with the scheduled tasks.' ?></span></div>
</div>
<div class="ap-stat-row mb-3">
    <a class="ap-stat-card" href="<?= e(admin_url('system/monitoring/uptime')) ?>"><i class="fa-solid fa-heart-pulse"></i><div><strong><?= $pct($s['up24']) ?></strong><span>Up in the last 24 hours (7 days: <?= $pct($s['up7']) ?>)</span></div></a>
    <a class="ap-stat-card" href="<?= e(admin_url('system/monitoring/speed')) ?>"><i class="fa-solid fa-gauge-high"></i><div><strong><?= $s['avg'] ? number_format($s['avg']) . ' ms' : '—' ?></strong><span>Home page on average, <?= (int) $s['slow_pages'] ?> slow page(s)</span></div></a>
    <a class="ap-stat-card" href="<?= e(admin_url('system/monitoring/links')) ?>"><i class="fa-solid fa-link-slash"></i><div><strong><?= (int) $s['broken'] ?></strong><span>Broken links</span></div></a>
    <a class="ap-stat-card" href="<?= e(admin_url('system/monitoring/pictures')) ?>"><i class="fa-solid fa-images"></i><div><strong><?= (int) $s['pictures'] ?></strong><span>Pictures to look at</span></div></a>
    <a class="ap-stat-card" href="<?= e(admin_url('logs/errors')) ?>"><i class="fa-solid fa-bug"></i><div><strong><?= (int) $s['errors'] ?></strong><span>Errors to look at</span></div></a>
</div>
<section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-chart-column"></i>Recent checks</h2></div>
    <div class="ap-section-body pb-3">
        <?php if (!$recent): ?><p class="small text-muted mb-0">No checks yet.</p><?php else: $max = max(1, max(array_map(static fn($r) => (int) $r['ms'], $recent))); ?>
            <div class="ap-spark" role="img" aria-label="How long the home page took at the last <?= count($recent) ?> checks">
                <?php foreach (array_reverse($recent) as $r): ?><i class="<?= (int) $r['ok'] ? '' : 'is-bad' ?>" style="height:<?= max(4, (int) round((int) $r['ms'] / $max * 100)) ?>%" title="<?= e(format_date((string) $r['created_at'], 'd M H:i') . ': ' . (int) $r['status_code'] . ', ' . (int) $r['ms'] . ' ms') ?>"></i><?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1"><span><?= e(format_date((string) end($recent)['created_at'], 'd M H:i')) ?></span><span>slowest <?= number_format($max) ?> ms</span><span>now</span></div>
        <?php endif; ?>
        <p class="small text-muted mt-3 mb-0">These checks run from your own server. If the whole server stops, they stop too: give the monitor address from the <a href="<?= e(admin_url('system/health') . '#health-settings') ?>">health check</a> to an outside service such as UptimeRobot.</p>
    </div></section>
<?php $this->endSection(); ?>
