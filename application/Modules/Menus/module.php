<?php
declare(strict_types=1);

use AP\Core\View;
use AP\Modules\Menus\Menus;

return [
    'name' => 'menus',
    'label' => 'Menus',
    'version' => '1.4.5',
    'description' => 'Menus for the top and bottom of the site, built with drag and drop from pages and links.',
    'icon' => 'fa-bars',
    'category' => 'Content',
    'when_off' => 'Menus are no longer shown on the website. They are kept.',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('menus')->count()) ? $n . ' menu(s) are kept.' : '',
    'core' => false,
    'default_enabled' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'menus.manage' => 'Build and change menus',
    ],
    'menu' => [
        ['label' => 'Menus', 'icon' => 'fa-bars-staggered', 'url' => 'menus', 'group' => 'Content', 'order' => 2, 'permission' => 'menus.manage',
            'info' => 'The menus visitors use to move around the site. Drag items to change their order or to put one under another.', 'keywords' => 'navigation header footer links nav'],
    ],
    'search' => [
        'menus' => ['label' => 'Menus', 'icon' => 'fa-bars-staggered', 'permission' => 'menus.manage',
            'handler' => static function (string $term, int $limit): array {
                if (!Menus::ready()) {
                    return [];
                }
                $rows = Menus::db()->table('menus')->search(['name'], $term)->orderBy('name')->limit($limit)->get();
                return array_map(static fn($m) => ['title' => (string) $m['name'], 'subtitle' => 'Menu' . ($m['location'] ? ' · ' . (Menus::locations()[$m['location']]['label'] ?? $m['location']) : ''),
                    'url' => admin_url('menus/' . (int) $m['id']), 'score' => 5], $rows);
            }],
    ],
    'boot' => static function ($app): void {
        // Menus show in the site layout's "menu" and "footer_menu" places; themes can call Menus::render() themselves.
        View::inject('menu', static fn(): string => Menus::render('header'));
        View::inject('footer_menu', static fn(): string => Menus::render('footer'));
        events()->listen('pages.changed', static function (): void {
            Menus::flush();
        });
    },
    'actions' => [
        'menus.create' => ['label' => 'Add a menu', 'icon' => 'fa-bars', 'url' => 'menus', 'permission' => 'menus.manage', 'keywords' => 'new menu navigation links', 'info' => 'Menus'],
    ],
];
