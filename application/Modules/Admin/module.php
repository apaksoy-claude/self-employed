<?php
declare(strict_types=1);

return [
    'name' => 'admin',
    'label' => 'Admin panel',
    'version' => '1.5.0',
    'description' => 'The admin shell: layout, menu with pinning, top-bar search, dashboard, toasts and shared list helpers.',
    'core' => true,
    'routes' => 'routes.php',
    'menu' => [
        ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => '', 'group' => 'Main', 'order' => 1, 'info' => 'An overview of the website. Modules add their own boxes here.', 'keywords' => 'home overview'],
        ['label' => 'Admin Menu', 'icon' => 'fa-bars-staggered', 'url' => 'settings/admin-menu', 'hub' => 'settings', 'category' => 'Look and languages', 'order' => 41, 'permission' => 'settings.view',
            'info' => 'Arrange the menu on the left: drag pages into the order you like, move them between groups, rename them, or hide the ones you do not use.',
            'keywords' => 'admin menu sidebar order arrange drag drop hide show enable disable rename groups navigation'],
    ],
    'setting_groups' => [
        'admin' => ['label' => 'Admin panel', 'icon' => 'fa-table-columns', 'category' => 'Look and languages', 'order' => 40,
            'description' => 'How the admin panel looks for everyone on the team.',
            'keywords' => 'login sign in page background dark logo',
            'tips' => ['Each person can pin their favourite pages to the top of the menu with the pin icon.', 'Press Ctrl + K anywhere in the admin to search pages, settings and staff.']],
    ],
    'settings' => [
        'admin.font' => ['group' => 'admin', 'section' => 'Look', 'label' => 'Font', 'type' => 'segmented', 'default' => 'poppins', 'options' => ['poppins' => 'Poppins', 'system' => 'System font (fastest)'], 'rules' => 'required|in:poppins,system', 'help' => 'Poppins loads from Google Fonts. The system font uses what the computer already has.', 'order' => 1],
        'admin.sidebar_style' => ['group' => 'admin', 'section' => 'Look', 'label' => 'Menu colour', 'type' => 'segmented', 'default' => 'navy', 'options' => ['navy' => 'Navy', 'light' => 'Light'], 'rules' => 'required|in:navy,light', 'help' => 'The colour of the top bar and the menu on the left.', 'order' => 2],
        'admin.accent' => ['group' => 'admin', 'section' => 'Look', 'label' => 'Accent colour', 'type' => 'color', 'default' => '#2563eb', 'rules' => 'required|regex:/^#[0-9A-Fa-f]{6}$/', 'help' => 'Used for buttons, links and the selected menu item.', 'order' => 3, 'width' => 'half'],
        'admin.favicon' => ['group' => 'admin', 'section' => 'Look', 'label' => 'Browser tab icon (favicon)', 'type' => 'image', 'default' => '', 'rules' => 'nullable|string|max:500',
            'help' => 'The small picture on the browser tab of the admin panel and the sign-in page. Square works best (PNG, SVG or ICO). Empty: the site icon from Settings > General is used.', 'order' => 3.5],
        'admin.menu_layout' => ['group' => 'admin', 'label' => 'Menu arrangement', 'type' => 'text', 'default' => '', 'hidden' => true],
        'admin.dashboard_cards' => ['group' => 'admin', 'section' => 'Look', 'label' => 'Standard dashboard cards', 'type' => 'segmented', 'default' => 'hide', 'options' => ['show' => 'Show', 'hide' => 'Hide'], 'rules' => 'required|in:show,hide',
            'help' => 'The greeting, health check, messages, pages, media and other cards on the admin home page. Cards added by other modules (such as Money) stay.', 'order' => 4],
        // The sign-in page
        'admin.login_style' => ['group' => 'admin', 'section' => 'Sign-in page', 'section_icon' => 'fa-right-to-bracket', 'label' => 'Background', 'type' => 'segmented', 'default' => 'dark',
            'options' => ['dark' => 'Dark', 'light' => 'Light'], 'rules' => 'required|in:dark,light', 'help' => 'The page behind the sign-in box (also used for "Forgot your password?" and two-step sign-in).', 'order' => 20],
        'admin.login_bg' => ['group' => 'admin', 'section' => 'Sign-in page', 'width' => 'half', 'label' => 'Dark background colour', 'type' => 'color', 'default' => '#222b3c', 'rules' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'show_if' => ['admin.login_style' => 'dark'], 'order' => 21],
        'admin.login_logo' => ['group' => 'admin', 'section' => 'Sign-in page', 'label' => 'Logo', 'type' => 'image', 'default' => '', 'rules' => 'nullable|string|max:500',
            'help' => 'Shown at the top of the sign-in box. Empty: the icon below and the site name.', 'order' => 22],
        'admin.login_icon' => ['group' => 'admin', 'section' => 'Sign-in page', 'width' => 'half', 'label' => 'Icon next to the site name', 'type' => 'text', 'default' => 'fa-layer-group', 'rules' => 'nullable|regex:/^fa-[a-z0-9-]+$/',
            'placeholder' => 'fa-layer-group', 'help' => 'A Font Awesome icon name, such as fa-utensils or fa-car. Empty: no icon.', 'order' => 23],
        'admin.login_title' => ['group' => 'admin', 'section' => 'Sign-in page', 'label' => 'Heading', 'type' => 'text', 'default' => 'Sign in to the admin panel', 'rules' => 'required|string|max:80', 'order' => 24],
        'admin.login_text' => ['group' => 'admin', 'section' => 'Sign-in page', 'label' => 'Text under the heading', 'type' => 'text', 'default' => 'Use the email address and password of your admin account.', 'rules' => 'nullable|string|max:200', 'order' => 25],
        'admin.login_note' => ['group' => 'admin', 'section' => 'Sign-in page', 'label' => 'Show what to do after forgetting the password', 'type' => 'checkbox', 'default' => true,
            'help' => 'A short note under the form: a link to reset it by email when emails are set up, otherwise "another administrator can set a new one".', 'order' => 26],
        'admin.login_languages' => ['group' => 'admin', 'section' => 'Sign-in page', 'label' => 'Show the language choice (EN / TR) when there are several languages', 'type' => 'checkbox', 'default' => true, 'order' => 27],
    ],
    'actions' => [
        'admin.login_look' => ['label' => 'Change the sign-in page', 'icon' => 'fa-right-to-bracket', 'url' => 'settings/admin', 'permission' => 'settings.view',
            'keywords' => 'login log in sign in page look background dark logo heading', 'info' => 'Admin panel settings'],
    ],
];
