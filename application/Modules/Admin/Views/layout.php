<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\ModuleManager;
use AP\Modules\Admin\Hub;
use AP\Modules\Admin\Preferences;

/** @var ModuleManager $modules */
$modules = app(ModuleManager::class);
$user = auth_user();
$theme = (string) session()->get('admin_theme', 'auto');
$pins = Preferences::pins();

// Menu: group the items the person may open, find the current page.
$groups = [];
$byUrl = [];
$current = null;
$currentLen = -1;
foreach ($modules->menu() as $item) {
    if ($item['permission'] && !can((string) $item['permission'])) {
        continue;
    }
    $url = trim((string) $item['url'], '/');
    $path = '/' . trim(admin_path() . '/' . $url, '/');
    $item['active'] = $url === '' ? is_active($path, false) : is_active($path);
    if ($item['active'] && strlen($url) > $currentLen) {
        $current = $item;
        $currentLen = strlen($url);
    }
    $item['label'] = title_case((string) $item['label']);
    // A number next to the item (new messages, problems to look at), from 'badge' => fn(): int in module.php.
    $item['badge_count'] = 0;
    if (isset($item['badge']) && is_callable($item['badge'])) {
        try {
            $item['badge_count'] = max(0, (int) ($item['badge'])());
        } catch (\Throwable) {
            $item['badge_count'] = 0;
        }
    }
    $byUrl[$url] = $item;
    // Pages that live in a hub (Settings, System) are listed on the left of those pages, not in the sidebar.
    if (empty($item['hub'])) {
        $groups[title_case((string) $item['group'])][] = $item;
    }
}
// The Administration group: one link per hub.
$currentHub = Hub::current();
foreach (Hub::sidebar() as $i => $link) {
    $hubItem = ['label' => $link['label'], 'icon' => $link['icon'], 'url' => $link['url'], 'group' => 'Administration', 'order' => $i + 1,
        'info' => $link['info'], 'keywords' => $link['hub'], 'permission' => null, 'hub_link' => $link['hub'], 'active' => false];
    $groups['Administration'][] = $hubItem;
    $byUrl[$link['url']] ??= $hubItem;
}
// Pinned pages may come from the Settings and System menus too.
foreach (array_keys(Hub::HUBS) as $hubKey) {
    foreach (Hub::items($hubKey) as $hi) {
        $byUrl[$hi['url']] ??= ['label' => title_case($hi['label']), 'icon' => $hi['icon'], 'url' => $hi['url'], 'group' => Hub::HUBS[$hubKey]['label'],
            'order' => $hi['order'], 'keywords' => $hi['keywords'], 'permission' => null, 'active' => false];
    }
}
foreach ($groups as &$items) {
    usort($items, static fn($a, $b) => $a['order'] <=> $b['order']);
    // Only the closest match is highlighted (so "Settings" is not lit up on "Settings > History").
    foreach ($items as &$it) {
        $it['active'] = isset($it['hub_link']) ? $currentHub === $it['hub_link'] : ($currentHub === null && $current !== null && $it['url'] === $current['url']);
    }
    unset($it);
}
unset($items);
$groupOrder = ['Main' => 0, 'Money' => 0.5, 'Content' => 1, 'Marketing' => 2, 'Users' => 5, 'Administration' => 20];
uksort($groups, static fn($a, $b) => ($groupOrder[$a] ?? 6) <=> ($groupOrder[$b] ?? 6) ?: strcmp($a, $b));

$groups = \AP\Modules\Admin\MenuLayout::apply($groups);
$siteName = (string) setting('general.site_name', 'AP Framework');
$pageTitle = $title ?? 'Admin';

// Remember this page for "Recently opened" and "Most used" in the search popup.
$relPath = trim((string) preg_replace('#^/?' . preg_quote(trim(admin_path(), '/'), '#') . '#', '', '/' . trim(request()->path, '/')), '/');
$hubActive = $currentHub !== null ? Hub::activeUrl($currentHub) : '';
$trackIcon = 'fa-file-lines';
$trackArea = 'Admin';
if ($currentHub !== null) {
    $trackArea = title_case(Hub::HUBS[$currentHub]['label']);
    foreach (Hub::items($currentHub) as $hi) {
        if ($hi['url'] === $hubActive) { $trackIcon = $hi['icon']; break; }
    }
} elseif ($current !== null) {
    $trackIcon = (string) $current['icon'];
    $trackArea = title_case((string) $current['group']);
} elseif ($relPath === '') {
    $trackIcon = 'fa-gauge-high';
}
if (empty($noTrack) && request()->method === 'GET' && $relPath !== 'search') {
    Preferences::track($relPath, (string) $pageTitle, $trackIcon, $trackArea);
}
$history = Preferences::history();
$recentUrls = array_column($history['recent'], 'url');
$cmdData = [
    'recent' => array_map(static fn($r) => ['title' => (string) $r['title'], 'subtitle' => (string) ($r['area'] ?? ''), 'icon' => (string) ($r['icon'] ?? 'fa-file-lines'), 'url' => admin_url((string) $r['url'])], array_slice($history['recent'], 0, 5)),
    'most' => array_map(static fn($r) => ['title' => (string) $r['title'], 'subtitle' => (string) ($r['area'] ?? ''), 'icon' => (string) ($r['icon'] ?? 'fa-file-lines'), 'url' => admin_url((string) $r['url'])], Preferences::mostUsed(4, array_slice($recentUrls, 0, 5))),
    'start' => [],
];
foreach (['', 'pages', 'media', 'users', 'settings', 'system'] as $startUrl) {
    if (isset($byUrl[$startUrl]) && count($cmdData['start']) < 5) {
        $si = $byUrl[$startUrl];
        $cmdData['start'][] = ['title' => (string) $si['label'], 'subtitle' => (string) ($si['group'] ?? 'Admin'), 'icon' => (string) $si['icon'], 'url' => admin_url($startUrl)];
    }
}
$pageInfo = (string) ($about ?? ($current['info'] ?? ''));
$crumbs = $breadcrumbs ?? [];
$toasts = session()->toasts();
$font = (string) setting('admin.font', 'poppins');
$accent = (string) setting('admin.accent', '#2563eb');
$accent = preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : '#2563eb';
$sidebarStyle = (string) setting('admin.sidebar_style', 'navy');
$initial = mb_strtoupper(mb_substr(trim((string) ($user['name'] ?? '?')), 0, 1)) ?: '?';
$siteInitial = mb_strtoupper(mb_substr(trim($siteName), 0, 1)) ?: 'A';
$slug = static fn(string $s): string => trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
$renderItem = static function (array $item, bool $inPinned) use ($pins): string {
    $url = trim((string) $item['url'], '/');
    $isPinned = in_array($url, $pins, true);
    $html = '<li class="ap-nav-item' . ($isPinned ? ' is-pinned' : '') . '" data-url="' . e($url) . '"' . ($inPinned ? ' data-sort-id="' . e($url) . '"' : '') . ' data-label="' . e(mb_strtolower($item['label'] . ' ' . $item['group'] . ' ' . ($item['keywords'] ?? ''))) . '">'
        . ($inPinned ? '<span class="ap-pin-grip" data-sort-handle tabindex="0" role="button" aria-label="' . e('Move ' . $item['label'] . ' (drag, or use the arrow keys)') . '" title="Drag to change the order"><i class="fa-solid fa-grip-vertical"></i></span>' : '')
        . '<a class="ap-nav-link' . ($item['active'] ? ' active' : '') . '" href="' . e(admin_url($url)) . '"' . ($item['active'] ? ' aria-current="page"' : '') . '>'
        . '<i class="fa-solid ' . e($item['icon']) . ' fa-fw" aria-hidden="true"></i><span>' . e($item['label']) . '</span>'
        . (!empty($item['badge_count']) ? '<span class="ap-nav-badge" aria-label="' . (int) $item['badge_count'] . ' new">' . ((int) $item['badge_count'] > 99 ? '99+' : (int) $item['badge_count']) . '</span>' : '') . '</a>';
    if (!$inPinned) {
        $html .= '<button type="button" class="ap-pin" data-pin="' . e($url) . '" aria-pressed="' . ($isPinned ? 'true' : 'false') . '" aria-label="' . e(($isPinned ? 'Unpin ' : 'Pin ') . $item['label']) . '" title="' . ($isPinned ? 'Unpin' : 'Pin to the top') . '"><i class="fa-solid fa-thumbtack"></i></button>';
    }
    return $html . '</li>';
};
?>
<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(text_direction()) ?>" data-bs-theme="<?= $theme === 'auto' ? 'light' : e($theme) ?>" data-theme-pref="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <?php if ($font === 'poppins'): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(vendor_url('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(vendor_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=<?= e(AP_VERSION) ?>">
    <style>:root{--ap-accent:<?= e($accent) ?>}</style>
    <?= favicon_tag('admin.favicon', 'general.favicon') ?>
    <?php $this->slot('admin.head'); ?>
</head>
<body class="ap-admin ap-font-<?= e($font === 'system' ? 'system' : 'poppins') ?> ap-side-<?= e($sidebarStyle === 'light' ? 'light' : 'navy') ?>" data-admin-url="<?= e(admin_url()) ?>" data-theme-url="<?= e(admin_url('theme')) ?>" data-pin-url="<?= e(admin_url('preferences/pin')) ?>" data-confirm-title="<?= e(__('core.delete')) ?>" data-confirm-yes="<?= e(__('core.yes')) ?>" data-confirm-no="<?= e(__('core.cancel')) ?>" data-unsaved="<?= e(__('core.unsaved_changes')) ?>">
<a class="visually-hidden-focusable" href="#main"><?= e(__('admin.skip_to_content')) ?></a>

<header class="ap-topbar">
    <div class="ap-top-left">
        <button class="ap-top-btn" type="button" id="sidebarToggle" aria-label="<?= e(__('admin.toggle_menu')) ?>" aria-controls="sidebar"><i class="fa-solid fa-bars"></i></button>
        <a class="ap-brand" href="<?= e(admin_url()) ?>">
            <span class="ap-brand-mark" aria-hidden="true"><?= e($siteInitial) ?></span>
            <span class="ap-brand-name"><?= e($siteName) ?></span>
            <span class="ap-brand-badge">ADMIN</span>
        </a>
    </div>
    <form class="ap-search" method="get" action="<?= e(admin_url('search')) ?>" role="search" data-search-form>
        <i class="fa-solid fa-magnifying-glass ap-search-icon" aria-hidden="true"></i>
        <input type="search" name="q" class="ap-search-input" placeholder="<?= e(__('admin.search_placeholder')) ?>" aria-label="<?= e(__('admin.search_label')) ?>" autocomplete="off"
               data-global-search aria-haspopup="dialog" value="<?= e($term ?? '') ?>">
    </form>
    <div class="ap-top-right">
        <?php $this->slot('admin.topbar'); ?>
        <button class="ap-top-btn" type="button" id="themeToggle" aria-label="<?= e(__('admin.dark_mode')) ?>" title="<?= e(__('admin.dark_mode')) ?>"><i class="fa-solid fa-moon"></i></button>
        <a class="ap-top-btn ap-top-text d-none d-md-inline-flex" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i><span><?= e(__('admin.view_site')) ?></span></a>
        <div class="dropdown">
            <button class="ap-user" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="ap-avatar" aria-hidden="true"><?= e($initial) ?></span>
                <span class="ap-user-name d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span>
                <i class="fa-solid fa-caret-down small" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="<?= e(admin_url('profile')) ?>"><i class="fa-regular fa-id-card fa-fw me-2"></i><?= e(__('admin.profile')) ?></a></li>
                <li><a class="dropdown-item" href="<?= e(admin_url('password')) ?>"><i class="fa-solid fa-key fa-fw me-2"></i><?= e(__('admin.change_password')) ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?>
                        <button class="dropdown-item" type="submit"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i><?= e(__('admin.logout')) ?></button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="ap-shell">
    <aside class="ap-sidebar" id="sidebar" aria-label="<?= e(__('admin.main_menu')) ?>">
        <div class="ap-side-tools">
            <div class="ap-side-find">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" placeholder="<?= e(__('admin.find_in_menu')) ?>" aria-label="<?= e(__('admin.find_in_menu')) ?>" data-menu-filter>
            </div>
            <div class="ap-side-buttons">
                <button type="button" data-sections="open"><i class="fa-solid fa-angles-down"></i><?= e(__('core.open_all')) ?></button>
                <button type="button" data-sections="close"><i class="fa-solid fa-angles-up"></i><?= e(__('core.collapse_all')) ?></button>
            </div>
        </div>
        <nav class="ap-nav" aria-label="<?= e(__('admin.main_menu')) ?>">
            <div class="ap-nav-section ap-nav-pinned" data-pinned-section<?= $pins ? '' : ' hidden' ?>>
                <div class="ap-nav-heading"><span><i class="fa-solid fa-thumbtack me-1"></i><?= e(__('admin.pinned')) ?></span></div>
                <ul class="ap-nav-list" data-pinned-list data-sortable data-sort-url="<?= e(admin_url('preferences/pins')) ?>">
                    <?php foreach ($pins as $p): if (!isset($byUrl[$p])) { continue; } ?>
                        <?= $renderItem($byUrl[$p], true) ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php foreach ($groups as $group => $items): $sid = 'nav-' . $slug((string) $group); ?>
                <div class="ap-nav-section" data-section="<?= e($sid) ?>">
                    <button type="button" class="ap-nav-heading" data-section-toggle aria-expanded="true" aria-controls="<?= e($sid) ?>">
                        <span><?= e($group) ?></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <ul class="ap-nav-list" id="<?= e($sid) ?>">
                        <?php foreach ($items as $item): ?><?= $renderItem($item, false) ?><?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
            <p class="ap-nav-nomatch" data-menu-nomatch hidden><?= e(__('admin.menu_nomatch')) ?></p>
        </nav>
        <div class="ap-side-foot"><span><?= e($siteName) ?> v<?= e(AP_VERSION) ?></span><?php if (can('system.view')): ?><a href="<?= e(admin_url('system')) ?>"><?= e(__('admin.system')) ?></a><?php endif; ?></div>
    </aside>
    <div class="ap-backdrop" data-sidebar-backdrop></div>

    <main id="main" class="ap-main" tabindex="-1">
        <?php if (session()->get('ap_recovery')): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <span><i class="fa-solid fa-life-ring me-2"></i><?= e(__('admin.recovery_on')) ?></span>
                <a class="btn btn-sm btn-outline-dark" href="<?= e(url('recovery/exit')) ?>"><?= e(__('admin.recovery_exit')) ?></a>
            </div>
        <?php endif; ?>
        <?php if (!\AP\Core\Kernel::schemaCurrent() && can('system.tools')): ?>
            <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
                <span><i class="fa-solid fa-arrows-rotate me-2"></i><?= e(__('admin.update_pending')) ?></span>
                <form method="post" action="<?= e(admin_url('system/tools/migrate')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-dark"><?= e(__('admin.update_finish')) ?></button></form>
            </div>
        <?php endif; ?>
        <?php if (vendor_missing()): ?>
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert"><span><i class="fa-solid fa-circle-info me-2"></i>Bootstrap and Font Awesome are loading from a CDN. Download them once so the admin works offline.</span>
                <?php if (can('system.tools')): ?><form method="post" action="<?= e(admin_url('system/tools/vendor')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-download me-1"></i>Download Now</button></form><?php endif; ?></div>
        <?php endif; ?>
        <?php foreach ($modules->autoDisabled() as $bad): if (!can('system.modules')) { break; } ?>
            <div class="alert alert-danger" role="alert">
                <strong><i class="fa-solid fa-shield-halved me-2"></i><?= e(__('admin.safe_mode_title')) ?></strong><br>
                <?= e(__('admin.safe_mode_text', ['module' => $bad['label'], 'reason' => $bad['reason']])) ?>
                <div class="mt-2 d-flex gap-2">
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(admin_url('system/modules')) ?>">Open module manager</a>
                    <form method="post" action="<?= e(admin_url('modules/acknowledge')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= e(__('admin.dismiss')) ?></button></form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($modules->problems() as $p): if (!$p['core'] || !can('system.modules')) { continue; } ?>
            <div class="alert alert-danger" role="alert"><strong>Built-in module "<?= e($p['module']) ?>" had a problem:</strong> <?= e($p['reason']) ?></div>
        <?php endforeach; ?>
        <?php foreach (settings()->undecryptable() as $bad): ?>
            <div class="alert alert-warning" role="alert"><i class="fa-solid fa-key me-2"></i><?= e(__('admin.secret_warning', ['setting' => $bad['label']])) ?></div>
        <?php endforeach; ?>

        <?php if (empty($hideHeader)): ?>
        <div class="ap-pagehead">
            <div class="ap-pagehead-text">
                <h1 class="ap-title"><?= e($pageTitle) ?><?php if ($pageInfo !== ''): ?>
                    <button type="button" class="ap-info" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="bottom" title="<?= e(__('core.about_page')) ?>" data-bs-content="<?= e($pageInfo) ?>" aria-label="<?= e(__('core.about_page')) ?>"><i class="fa-solid fa-circle-info"></i></button>
                <?php endif; ?></h1>
                <nav aria-label="breadcrumb"><ol class="breadcrumb ap-crumbs">
                    <li class="breadcrumb-item"><a href="<?= e(admin_url()) ?>"><?= e(__('core.home')) ?></a></li>
                    <?php if ($crumbs): ?>
                        <?php foreach ($crumbs as $label => $link): ?>
                            <?php if ($link): ?><li class="breadcrumb-item"><a href="<?= e($link) ?>"><?= e($label) ?></a></li>
                            <?php else: ?><li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li><?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($pageTitle) ?></li>
                    <?php endif; ?>
                </ol></nav>
                <?php if ($this->has('subtitle')): ?><div class="ap-subtitle"><?php $this->show('subtitle'); ?></div><?php endif; ?>
            </div>
            <?php if ($this->has('actions')): ?><div class="ap-pageactions"><?php $this->show('actions'); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($currentHub !== null && empty($noHub)): ?>
            <div class="ap-hub">
                <div class="ap-hub-nav"><?php if ($this->has('hubside')): ?><div class="ap-hubside"><?php $this->show('hubside'); ?></div><?php endif; ?><?php $this->include('admin::_hubnav', ['hub' => $currentHub, 'activeUrl' => Hub::activeUrl($currentHub)]); ?></div>
                <div class="ap-hub-body"><?php $this->show('content'); ?></div>
            </div>
        <?php else: ?>
            <?php $this->show('content'); ?>
        <?php endif; ?>
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts" aria-live="polite">
    <?php foreach ($toasts as $t): $type = in_array($t['type'], ['success', 'danger', 'warning', 'info'], true) ? $t['type'] : 'info'; ?>
        <div class="toast align-items-center text-bg-<?= e($type) ?> border-0" role="status" data-autoshow="1">
            <div class="d-flex"><div class="toast-body"><?= e($t['message']) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="ap-cmd" id="apCmd" role="dialog" aria-modal="true" aria-label="<?= e(__('admin.search_label')) ?>" hidden data-cmd data-search-url="<?= e(admin_url('search')) ?>">
    <div class="ap-cmd-backdrop" data-cmd-close></div>
    <div class="ap-cmd-panel">
        <div class="ap-cmd-head">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" class="ap-cmd-input" placeholder="<?= e(__('admin.search_placeholder')) ?>" aria-label="<?= e(__('admin.search_label')) ?>" autocomplete="off" spellcheck="false"
                   data-cmd-input role="combobox" aria-expanded="true" aria-controls="apCmdList" aria-autocomplete="list">
            <kbd class="ap-cmd-esc">Esc</kbd>
            <button type="button" class="ap-cmd-x" data-cmd-close aria-label="<?= e(__('core.close')) ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ap-cmd-list" id="apCmdList" role="listbox" data-cmd-list></div>
        <div class="ap-cmd-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> <?= e(__('admin.search_move')) ?></span>
            <span><kbd>Enter</kbd> <?= e(__('admin.search_open')) ?></span>
            <span><kbd>Esc</kbd> <?= e(__('admin.search_close')) ?></span>
            <a href="<?= e(admin_url('search')) ?>" class="ms-auto" data-cmd-all hidden><?= e(__('admin.search_all')) ?> <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</div>
<script type="application/json" id="apCmdData"><?= json_encode($cmdData + ['labels' => ['recent' => __('admin.search_recent'), 'most' => __('admin.search_most_used'), 'start' => __('admin.search_start')]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="confirmTitle"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="confirmBody"></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="confirmNo"></button><button type="button" class="btn btn-danger" id="confirmYes"></button></div>
</div></div></div>



<script src="<?= e(vendor_url('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
<?php $this->slot('admin.body_end'); ?>
</body>
</html>
