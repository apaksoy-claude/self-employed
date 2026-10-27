<?php
declare(strict_types=1);

use AP\Core\Cache;

return [
    'name' => 'cache',
    'label' => 'Cache',
    'version' => '1.4.0',
    'description' => 'A file-based data cache that other modules use to stay fast on shared hosting, with controls to see and clear it.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => ['cache.manage' => 'See and clear the cache'],
    'menu' => [
        ['label' => 'Cache', 'icon' => 'fa-bolt', 'url' => 'system/cache', 'hub' => 'system', 'category' => 'Maintenance', 'group' => 'System', 'order' => 14, 'permission' => 'cache.manage', 'info' => 'Saved copies that make pages faster. Clear them if a change does not show.', 'keywords' => 'clear cache speed'],
    ],
    'settings' => [
        'cache.enabled' => ['group' => 'cache', 'section' => 'Data cache', 'label' => 'Use the data cache', 'type' => 'checkbox', 'default' => true, 'help' => 'Keeps results of slow work (translations, lists) for a while. Switch off while debugging.', 'order' => 1],
        'cache.default_ttl' => ['group' => 'cache', 'section' => 'Data cache', 'width' => 'half', 'label' => 'Keep cached items for (seconds)', 'type' => 'number', 'default' => 3600, 'rules' => 'integer|min:10|max:2592000', 'help' => 'How long an item is trusted before it is rebuilt. 3600 is one hour.', 'order' => 2],
    ],
    'schedule' => [
        'cache.prune' => ['label' => 'Remove expired cache files', 'description' => 'Deletes cache entries that are past their time.', 'cron' => '17 * * * *',
            'handler' => static fn(): string => Cache::instance()->prune() . ' expired cache file(s) removed.'],
    ],
    'boot' => static function ($app): void {
        $app->set(Cache::class, Cache::instance());
    },
    'actions' => [
        'cache.clear' => ['label' => 'Clear the cache', 'icon' => 'fa-broom', 'url' => 'system/cache', 'permission' => 'cache.manage', 'keywords' => 'clear empty refresh not showing', 'info' => 'Cache'],
    ],
];
