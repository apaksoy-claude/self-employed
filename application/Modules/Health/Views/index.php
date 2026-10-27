<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $latest */
use AP\Core\Form;
use AP\Modules\Health\Health;

$this->extend('admin::layout');
$res = (array) ($latest['results'] ?? []);
$red = (int) $latest['red'];
$amber = (int) $latest['amber'];
$tone = $red ? 'bad' : ($amber ? 'warn' : 'ok');
$headline = $red ? 'Needs fixing' : ($amber ? 'Working, with things worth a look' : 'Everything is working');
$icon = ['ok' => 'fa-circle-check', 'info' => 'fa-circle-info', 'warn' => 'fa-triangle-exclamation', 'bad' => 'fa-circle-xmark'];
$grouped = [];
foreach ($res as $id => $r) {
    $grouped[$r['group']][$id] = $r;
}
$checksByGroup = [];
foreach ($checks as $id => $c) {
    $checksByGroup[$c['group']][$id] = $c;
}
?>
<?php $this->section('content'); ?>
<div class="ap-settings ap-two">
    <div class="ap-sections">
        <form method="post" action="<?= e(admin_url('system/health/run')) ?>" class="ap-health-bar is-<?= $tone ?>"><?= csrf_field() ?>
            <span class="ap-health-icon"><i class="fa-solid <?= $red ? 'fa-circle-xmark' : ($amber ? 'fa-triangle-exclamation' : 'fa-heart-pulse') ?>" aria-hidden="true"></i></span>
            <div class="ap-health-text"><strong><?= e($headline) ?></strong>
                <span>Last checked <?= e(format_date((string) $latest['created_at'], 'd M Y, H:i')) ?> (<?= e(['auto' => 'automatically', 'manual' => 'by hand', 'page' => 'when this page opened'][$latest['source']] ?? $latest['source']) ?>) · score <?= (int) $latest['score'] ?>/100</span></div>
            <span class="ap-health-pill is-bad"><?= $red ?> to fix</span><span class="ap-health-pill is-warn"><?= $amber ?> to look at</span><span class="ap-health-pill is-ok"><?= (int) $latest['green'] ?> working</span>
            <label class="form-check small m-0"><input class="form-check-input" type="checkbox" name="test_email" value="1"> <span class="form-check-label">Also send a real test email</span></label>
            <button class="btn btn-primary btn-sm" type="submit" data-loading="Checking…"><i class="fa-solid fa-stethoscope me-1"></i>Check everything now</button>
        </form>

        <?php foreach (Health::GROUPS as $g => [$gLabel, $gIcon]): if (empty($grouped[$g])) { continue; } ?>
            <section class="ap-section">
                <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid <?= e($gIcon) ?>" aria-hidden="true"></i><?= e($gLabel) ?></h2></div>
                <div class="ap-health-list">
                    <?php foreach ($grouped[$g] as $id => $r): ?>
                        <div class="ap-health-row is-<?= e($r['status']) ?>" id="check-<?= e((string) $id) ?>">
                            <i class="fa-solid <?= e($icon[$r['status']] ?? 'fa-circle') ?>" aria-hidden="true"></i>
                            <div><strong><?= e($r['label']) ?></strong><span><?= e($r['message']) ?></span></div>
                            <small><?= (int) $r['ms'] ?> ms</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <form method="post" action="<?= e(admin_url('system/health/settings')) ?>" class="ap-sections" id="health-settings" data-unsaved-warning novalidate><?= csrf_field() ?>
            <section class="ap-section">
                <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-list-check" aria-hidden="true"></i>What to check</h2></div>
                <div class="ap-section-body">
                    <?php foreach (Health::GROUPS as $g => [$gLabel]): if (empty($checksByGroup[$g])) { continue; } ?>
                        <div class="ap-fc-label mt-0"><?= e($gLabel) ?></div>
                        <div class="ap-check-grid">
                            <?php foreach ($checksByGroup[$g] as $id => $c): ?>
                                <label class="form-check"><input type="hidden" name="check[<?= e((string) $id) ?>]" value="0"><input class="form-check-input" type="checkbox" name="check[<?= e((string) $id) ?>]" value="1"<?= in_array($id, $skip, true) ? '' : ' checked' ?><?= $canEdit ? '' : ' disabled' ?>> <span class="form-check-label"><?= e($c['label']) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php $this->include('settings::_sections', ['group' => 'health', 'sections' => $sections, 'meta' => ['icon' => 'fa-heart-pulse'], 'canEdit' => $canEdit]); ?>
            <?php if ($monitorUrl !== ''): ?>
                <section class="ap-section"><div class="ap-section-body pb-3">
                    <label class="form-label">Monitor address</label>
                    <div class="input-group"><input type="text" class="form-control ap-code" value="<?= e($monitorUrl) ?>" readonly aria-label="Monitor address"><button type="button" class="btn btn-outline-secondary" data-copy="<?= e($monitorUrl) ?>"><i class="fa-regular fa-copy me-1"></i>Copy</button></div>
                    <label class="form-check small mt-2"><input class="form-check-input" type="checkbox" name="new_monitor" value="1"> <span class="form-check-label">Make a new secret address (the old one stops working)</span></label>
                </div></section>
            <?php endif; ?>
            <?php if ($canEdit): ?><?= Form::saveBar() ?><?php endif; ?>
        </form>
    </div>
    <aside class="ap-settings-aside">
        <div class="ap-aside-card"><h2><i class="fa-solid fa-clock-rotate-left"></i>History</h2>
            <div class="ap-health-history">
                <?php foreach ($history as $h): $t = (int) $h['red'] ? 'bad' : ((int) $h['amber'] ? 'warn' : 'ok'); ?>
                    <div class="is-<?= $t ?>"><i class="fa-solid <?= $icon[$t] ?>" aria-hidden="true"></i><span><?= e(format_date((string) $h['created_at'], 'd M, H:i')) ?></span><small><?= e(['auto' => 'automatically', 'manual' => 'by hand', 'page' => 'page opened'][$h['source']] ?? $h['source']) ?> · <?= (int) $h['red'] ?>/<?= (int) $h['amber'] ?></small></div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2">The numbers are red / amber results.</p>
        </div>
        <div class="ap-aside-card"><h2><i class="fa-solid fa-clock"></i>Cron job</h2>
            <p>For checks and emails at night too, add this in cPanel &gt; Advanced &gt; Cron Jobs, every 5 minutes.</p>
            <pre class="ap-cron mt-2"><?= e($cron) ?></pre>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="<?= e($cron) ?>"><i class="fa-regular fa-copy me-1"></i>Copy</button>
        </div>
        <?php foreach (['staging' => ['Staging mode', 'fa-eye-slash', 'Keep on until launch so Google does not index the new site.', (bool) setting('seo.noindex', false), 'visible to search engines', 'hidden from search engines'],
            'maintenance' => ['Maintenance mode', 'fa-person-digging', 'Visitors see a "back shortly" page. You can still use the whole site.', (bool) setting('maintenance.enabled', false), 'site open', 'visitors see "back shortly"']] as $k => [$l, $i, $d, $on, $offText, $onText]): ?>
            <div class="ap-aside-card"><h2><i class="fa-solid <?= $i ?>"></i><?= $l ?></h2><p><?= e($d) ?></p>
                <div class="d-flex justify-content-between align-items-center mt-2"><span class="ap-status-pill<?= $on ? ' is-queued' : ' is-sent' ?>"><?= $on ? 'On: ' . $onText : 'Off: ' . $offText ?></span>
                    <?php if ($canEdit): ?><form method="post" action="<?= e(admin_url('system/health/switch')) ?>"><?= csrf_field() ?><input type="hidden" name="what" value="<?= $k ?>"><button class="btn btn-sm btn-outline-secondary"><?= $on ? 'Turn off' : 'Turn on' ?></button></form><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
    </aside>
</div>
<?php $this->endSection(); ?>
