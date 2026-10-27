<?php
declare(strict_types=1);

use AP\Core\View;
use AP\Modules\Footer\Footer;

return [
    'name' => 'footer',
    'label' => 'Footer builder',
    'version' => '1.4.7',
    'description' => 'Build the bottom of every page with drag and drop: rows, columns and blocks such as your logo, menus, links, social media and contact details.',
    'icon' => 'fa-table-columns',
    'category' => 'Content',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'The website shows the simple footer again (footer menu, social icons and copyright). Your footer is kept.',
    'permissions' => [
        'footer.manage' => 'Change the footer of the website',
    ],
    'menu' => [
        ['label' => 'Footer', 'icon' => 'fa-table-columns', 'url' => 'footer', 'group' => 'Content', 'order' => 2.5, 'permission' => 'footer.manage',
            'info' => 'The bottom of every page: rows and columns of blocks, the bottom bar with the copyright and links, and the colours.',
            'keywords' => 'footer bottom columns copyright links logo about social contact small print back to top builder'],
    ],
    'settings' => [
        'footer.enabled' => ['group' => 'footer', 'label' => 'Use the footer builder', 'type' => 'checkbox', 'default' => true, 'hidden' => true],
        'footer.layout' => ['group' => 'footer', 'label' => 'Footer layout', 'type' => 'text', 'default' => '', 'hidden' => true],
    ],
    'boot' => static function ($app): void {
        try {
            if ((bool) setting('footer.enabled')) {
                View::inject('footer_replace', static fn(): string => Footer::render());
            }
        } catch (\Throwable) {
            // settings not ready (installing): the simple footer shows
        }
    },
    'actions' => [
        'footer.edit' => ['label' => 'Change the footer', 'icon' => 'fa-table-columns', 'url' => 'footer', 'permission' => 'footer.manage', 'keywords' => 'footer columns copyright bottom links', 'info' => 'Footer'],
        'footer.copyright' => ['label' => 'Change the copyright line', 'icon' => 'fa-copyright', 'url' => 'footer#footer-bottom', 'permission' => 'footer.manage', 'keywords' => 'copyright all rights reserved bottom bar small print', 'info' => 'Footer'],
    ],
];
