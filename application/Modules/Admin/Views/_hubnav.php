<?php declare(strict_types=1);
/** @var string $hub @var string $activeUrl */
use AP\Modules\Admin\Hub;
use AP\Modules\Admin\Preferences;

$isSettings = $hub === 'settings';
$term = $isSettings ? request()->string('q') : '';
$pins = Preferences::pins();
$hubLabel = title_case(Hub::HUBS[$hub]['label']);
?>
<nav class="ap-settings-nav" aria-label="<?= e($hubLabel) ?> pages" data-settings-nav data-hub-nav="<?= e($hub) ?>">
    <div class="ap-snav-title"><i class="fa-solid <?= e(Hub::HUBS[$hub]['icon']) ?>" aria-hidden="true"></i><?= e($hubLabel) ?></div>
    <?php if ($isSettings): ?>
        <form class="ap-snav-find" method="get" action="<?= e(admin_url('settings')) ?>" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm" name="q" placeholder="Search settings" aria-label="Search settings" data-snav-filter value="<?= e($term) ?>">
        </form>
    <?php else: ?>
        <div class="ap-snav-find">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Find a page" aria-label="Find a page" data-snav-filter>
        </div>
    <?php endif; ?>
    <div class="ap-snav-buttons">
        <button type="button" data-snav="open"><i class="fa-solid fa-angles-down me-1"></i><?= e(__('core.open_all')) ?></button>
        <button type="button" data-snav="close"><i class="fa-solid fa-angles-up me-1"></i><?= e(__('core.collapse_all')) ?></button>
    </div>
    <div class="ap-snav-scroll">
        <?php foreach (Hub::grouped($hub) as $cat): $cid = 'snav-' . $hub . '-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', $cat['key']), '-'); ?>
            <div class="ap-snav-cat" data-snav-cat="<?= e($cid) ?>">
                <button type="button" aria-expanded="true" aria-controls="<?= e($cid) ?>"><span><?= e($cat['label']) ?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                <ul id="<?= e($cid) ?>">
                    <?php foreach ($cat['items'] as $item): $on = $item['url'] === $activeUrl; $label = title_case($item['label']); $pinned = in_array($item['url'], $pins, true); ?>
                        <li data-snav-item="<?= e(mb_strtolower($label . ' ' . $cat['label'] . ' ' . $item['keywords'])) ?>" data-pin-src data-url="<?= e($item['url']) ?>" data-label="<?= e(mb_strtolower($label . ' ' . $hubLabel)) ?>" class="<?= $pinned ? 'is-pinned' : '' ?>">
                            <a class="<?= $on ? 'active' : '' ?>" href="<?= e(admin_url($item['url'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
                                <i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
                                <?php if ($item['shortcut']): ?><i class="fa-solid fa-arrow-up-right-from-square ap-snav-ext" aria-hidden="true" title="Opens its own page"></i><?php endif; ?>
                            </a>
                            <button type="button" class="ap-pin" data-pin="<?= e($item['url']) ?>" aria-pressed="<?= $pinned ? 'true' : 'false' ?>" aria-label="<?= e(($pinned ? 'Unpin ' : 'Pin ') . $label) ?>" title="<?= $pinned ? 'Unpin' : 'Pin to the top of the main menu' ?>"><i class="fa-solid fa-thumbtack"></i></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <p class="ap-snav-nomatch small text-muted mb-0" data-snav-nomatch hidden><?= $isSettings ? 'No settings page matches. Press Enter to search every setting.' : 'No page matches.' ?></p>
    </div>
</nav>
