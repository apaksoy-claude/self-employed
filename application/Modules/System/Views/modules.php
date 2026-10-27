<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/modules.js')) . '?v=' . e(AP_VERSION) . '"></script>');

$optional = array_values(array_filter($rows, static fn($m) => !$m['core']));
$core = array_values(array_filter($rows, static fn($m) => $m['core']));
$byCat = [];
foreach ($optional as $m) {
    $byCat[title_case((string) ($m['category'] ?? 'More'))][] = $m;
}
$order = ['Content' => 1, 'Marketing' => 2, 'Tools' => 3];
uksort($byCat, static fn($a, $b) => [$order[$a] ?? 9, $a] <=> [$order[$b] ?? 9, $b]);
$on = count(array_filter($optional, static fn($m) => $m['enabled']));
$attention = count(array_filter($rows, static fn($m) => $m['pending'] || ($m['state'] && (int) $m['state']['auto_disabled'] === 1 && !(int) $m['state']['enabled'])));
$label = static fn(string $name): string => (string) ($rows[array_search($name, array_column($rows, 'name'), true)]['label'] ?? $name);
?>
<?php $this->section('content'); ?>
<div class="ap-modules" data-modules data-toggle-url="<?= e(admin_url('system/modules')) ?>">
    <div class="ap-mod-intro"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
        <p>Switch on only the parts your website uses. A part that is off disappears everywhere: its pages, menu links and scheduled work. Its data is kept, so switching it on again brings everything back.</p></div>
    <?php if ($recovery): ?><div class="alert alert-warning">Recovery mode is on, so optional modules are not loaded for this session even if they show as on.</div><?php endif; ?>

    <div class="ap-mod-toolbar">
        <div class="ap-tabs-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" placeholder="Find a module" aria-label="Find a module" data-mod-search data-shortcut-search></div>
        <div class="ap-segmented" role="radiogroup" aria-label="Show">
            <input type="radio" class="btn-check" name="modShow" id="modAll" value="all" checked data-mod-filter><label class="btn" for="modAll">All <span data-count="all"><?= count($optional) ?></span></label>
            <input type="radio" class="btn-check" name="modShow" id="modOn" value="on" data-mod-filter><label class="btn" for="modOn">On <span data-count="on"><?= $on ?></span></label>
            <input type="radio" class="btn-check" name="modShow" id="modOff" value="off" data-mod-filter><label class="btn" for="modOff">Off <span data-count="off"><?= count($optional) - $on ?></span></label>
            <?php if ($attention): ?><input type="radio" class="btn-check" name="modShow" id="modAtt" value="attention" data-mod-filter><label class="btn" for="modAtt">Needs attention <span><?= $attention ?></span></label><?php endif; ?>
        </div>
    </div>

    <?php foreach ($byCat as $cat => $mods): $catOn = count(array_filter($mods, static fn($m) => $m['enabled'])); ?>
        <section class="ap-mod-cat" data-mod-cat>
            <div class="ap-mod-cat-head"><h2><?= e($cat) ?></h2><span data-cat-count><?= $catOn ?> of <?= count($mods) ?> on</span></div>
            <div class="ap-mod-grid">
            <?php foreach ($mods as $m):
                $auto = $m['state'] && (int) $m['state']['auto_disabled'] === 1 && !(int) $m['state']['enabled'];
                $flags = implode(' ', array_filter([$m['enabled'] ? 'on' : 'off', ($auto || $m['pending']) ? 'attention' : '']));
                $settingsUrl = $m['setting_groups'] ? admin_url('settings/' . $m['setting_groups'][0]) : null;
                $did = 'mod_' . $m['name'];
                ?>
                <article class="ap-mod-card<?= $m['enabled'] ? ' is-on' : '' ?>" id="module-<?= e($m['name']) ?>" data-mod="<?= e($flags) ?>" data-name="<?= e($m['name']) ?>" data-text="<?= e(mb_strtolower($m['label'] . ' ' . $m['name'] . ' ' . $m['description'] . ' ' . $cat)) ?>">
                    <div class="ap-mod-top">
                        <span class="ap-mod-icon"><i class="fa-solid <?= e((string) ($m['icon'] ?? 'fa-puzzle-piece')) ?>" aria-hidden="true"></i></span>
                        <h3><?= e(title_case((string) $m['label'])) ?></h3>
                        <label class="ap-onoff" title="<?= $m['enabled'] ? 'Switch off' : 'Switch on' ?>">
                            <input type="checkbox" role="switch" data-mod-switch<?= $m['enabled'] ? ' checked' : '' ?> aria-label="<?= e($m['label']) ?> on or off">
                            <span class="ap-onoff-track"><span class="ap-onoff-on">ON</span><span class="ap-onoff-off">OFF</span></span>
                        </label>
                    </div>
                    <p class="ap-mod-desc"><?= e((string) $m['description']) ?></p>
                    <?php if (($m['kept_text'] ?? '') !== ''): ?><div class="ap-mod-kept"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><?= e($m['kept_text']) ?></div><?php endif; ?>
                    <?php if ($auto): ?><div class="ap-mod-kept is-bad"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Switched off automatically: <?= e((string) $m['state']['disabled_reason']) ?></div><?php endif; ?>
                    <?php if ($m['pending']): ?><div class="ap-mod-kept"><i class="fa-solid fa-database" aria-hidden="true"></i><?= count($m['pending']) ?> database update(s) waiting.
                        <form method="post" action="<?= e(admin_url('system/modules/' . $m['name'] . '/migrate')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0 align-baseline">Apply now</button></form></div><?php endif; ?>
                    <?php if ($m['requires']): ?><div class="ap-mod-needs"><i class="fa-solid fa-link" aria-hidden="true"></i>Needs: <?= e(implode(', ', array_map($label, (array) $m['requires']))) ?></div><?php endif; ?>
                    <?php if ($m['needed_by']): ?><div class="ap-mod-needs"><i class="fa-solid fa-link" aria-hidden="true"></i>Needed by: <?= e(implode(', ', $m['needed_by'])) ?></div><?php endif; ?>
                    <?php if (!empty($m['when_off'])): ?><p class="ap-mod-off"><strong>When off:</strong> <?= e((string) $m['when_off']) ?></p><?php endif; ?>
                    <div class="ap-mod-foot">
                        <?php if ($settingsUrl): ?><a href="<?= e($settingsUrl) ?>"><i class="fa-solid fa-sliders me-1"></i>Settings</a><?php endif; ?>
                        <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#<?= e($did) ?>" aria-expanded="false" aria-controls="<?= e($did) ?>">Details</button>
                        <span class="ms-auto" data-mod-state><?= $m['enabled'] ? 'Switched on' : 'Switched off' ?></span>
                    </div>
                    <div class="collapse" id="<?= e($did) ?>"><ul class="ap-mod-details">
                        <li>Version <?= e((string) $m['version']) ?> · folder <code>Modules/<?= e((string) $m['folder']) ?></code></li>
                        <li><?= count((array) $m['permissions']) ?> permission(s), <?= count((array) $m['menu']) ?> menu item(s), <?= count((array) $m['widgets']) ?> dashboard box(es), <?= count((array) $m['settings']) ?> setting(s)</li>
                        <?php if ($m['tasks']): ?><li>Scheduled tasks: <?= e(implode(', ', $m['tasks'])) ?></li><?php endif; ?>
                        <?php if ($m['jobs']): ?><li>Background jobs: <?= e(implode(', ', $m['jobs'])) ?></li><?php endif; ?>
                        <?php if ($m['templates']): ?><li>Email templates: <?= e(implode(', ', $m['templates'])) ?></li><?php endif; ?>
                    </ul></div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <p class="ap-mod-none text-muted" data-mod-none hidden>No module matches.</p>

    <section class="ap-mod-cat">
        <div class="ap-mod-cat-head"><h2>Always on</h2><span><?= count($core) ?> built in</span></div>
        <div class="ap-section"><div class="ap-section-body pb-3">
            <p class="small text-muted mb-2">The core of the website. These cannot be switched off.</p>
            <div class="ap-core-chips">
                <?php foreach ($core as $m): ?><span class="ap-core-chip" title="<?= e((string) $m['description']) ?>"><i class="fa-solid fa-check" aria-hidden="true"></i><?= e(title_case((string) $m['label'])) ?><?php if ($m['pending']): ?> <i class="fa-solid fa-database text-warning" title="Database update waiting"></i><?php endif; ?></span><?php endforeach; ?>
            </div>
        </div></div>
    </section>
</div>
<?php $this->endSection(); ?>
