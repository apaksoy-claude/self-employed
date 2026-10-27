<?php
declare(strict_types=1);

use AP\Core\Kernel;
use AP\Core\Logger;
use AP\Core\Translator;
use AP\Core\View;
use AP\Modules\Languages\Locales;

return [
    'name' => 'languages',
    'label' => 'Languages',
    'version' => '1.4.6',
    'description' => 'Add languages, let visitors switch between them, and edit any wording from the admin without touching files.',
    'core' => true,
    'requires' => ['settings'],
    'routes' => 'routes.php',
    'permissions' => [
        'languages.view' => 'See languages',
        'languages.manage' => 'Add, switch on and off, and remove languages',
        'languages.translate' => 'Edit translations and wording',
    ],
    'menu' => [
        ['label' => 'Languages', 'icon' => 'fa-language', 'url' => 'languages', 'hub' => 'settings', 'category' => 'Look and languages', 'group' => 'Settings', 'order' => 11, 'permission' => 'languages.view', 'info' => 'Languages offered on the site and the admin, and the wording for each one.', 'keywords' => 'translations wording'],
    ],
    'setting_groups' => [
        'languages' => ['label' => 'Language options', 'icon' => 'fa-language', 'hub' => 'languages', 'category' => 'Look and languages', 'order' => 11.5, 'url' => 'languages#language-options',
            'description' => 'How visitors get their language, the language switcher and the language of the admin.', 'keywords' => 'language switcher browser detect admin language remember'],
    ],
    'settings' => [
        'languages.detect_browser' => ['group' => 'languages', 'section' => 'Choosing the language', 'section_icon' => 'fa-language', 'label' => 'Choose the language from the visitor\'s browser', 'type' => 'checkbox', 'default' => true, 'help' => 'First-time visitors get their browser\'s language if you offer it, otherwise the default.', 'order' => 10],
        'languages.remember_days' => ['group' => 'languages', 'section' => 'Choosing the language', 'width' => 'half', 'label' => 'Remember a visitor\'s choice for (days)', 'type' => 'number', 'default' => 365, 'rules' => 'integer|min:1|max:3650', 'order' => 11],
        'languages.admin_language' => ['group' => 'languages', 'section' => 'Choosing the language', 'width' => 'half', 'label' => 'Admin panel language', 'type' => 'select', 'default' => '', 'options' => ['' => 'Same as the visitor\'s choice'], 'help' => 'Force the admin panel into one language, whatever visitors see.', 'order' => 12],
        'languages.show_switcher' => ['group' => 'languages', 'section' => 'Language switcher', 'section_icon' => 'fa-repeat', 'label' => 'Show a language switcher', 'type' => 'checkbox', 'default' => true, 'help' => 'Appears when more than one language is switched on.', 'order' => 20],
        'languages.switcher_place' => ['group' => 'languages', 'section' => 'Language switcher', 'label' => 'Where on the website', 'type' => 'segmented', 'default' => 'nav',
            'options' => ['nav' => 'Next to the menu', 'footer' => 'In the footer', 'both' => 'Both'], 'rules' => 'required|in:nav,footer,both', 'show_if' => ['languages.show_switcher' => '1'], 'order' => 21],
        'languages.switcher_label' => ['group' => 'languages', 'section' => 'Language switcher', 'label' => 'It shows', 'type' => 'segmented', 'default' => 'code',
            'options' => ['code' => 'Short code (EN)', 'name' => 'The name (English)', 'both' => 'Both (EN English)'], 'rules' => 'required|in:code,name,both', 'show_if' => ['languages.show_switcher' => '1'], 'order' => 22],
        'languages.switcher_admin' => ['group' => 'languages', 'section' => 'Language switcher', 'label' => 'Also in the admin top bar', 'type' => 'checkbox', 'default' => true, 'show_if' => ['languages.show_switcher' => '1'], 'order' => 23],
    ],
    'boot' => static function ($app): void {
        if (!Kernel::schemaCurrent()) {
            return;
        }
        try {
            /** @var Translator $t */
            $t = $app->make(Translator::class);
            $enabled = Locales::enabled();
            // Make the language drop-downs in settings list the real languages.
            $options = [];
            foreach ($enabled as $code => $l) {
                $options[$code] = $l['name'] . ($l['native_name'] !== $l['name'] ? ' (' . $l['native_name'] . ')' : '');
            }
            $settings = settings();
            if (($def = $settings->definition('localisation.default_language')) !== null && $options) {
                $settings->define('localisation.default_language', ['options' => $options, 'help' => 'The language visitors get when they have not chosen one. Add more under Languages.'] + $def);
            }
            if (($def = $settings->definition('languages.admin_language')) !== null) {
                $settings->define('languages.admin_language', ['options' => ['' => 'Same as the visitor\'s choice'] + $options] + $def);
            }
            $t->setOverrides(static fn(string $ns, string $locale): array => Locales::overrides($ns, $locale));
            $request = request();
            $code = Locales::resolve($request);
            $t->setLocale($code);
            $queryLang = $request->query['lang'] ?? null;
            if (is_string($queryLang) && isset($enabled[$queryLang])) {
                Locales::remember($queryLang, $request);
            }
            // Language switcher in the admin top bar and the public navigation.
            if ((bool) setting('languages.show_switcher') && count($enabled) > 1) {
                $show = (string) setting('languages.switcher_label');
                $label = static function (string $code, array $l) use ($show): string {
                    $short = strtoupper(explode('-', $code)[0]);
                    return match ($show) { 'name' => (string) $l['native_name'], 'both' => $short . ' ' . $l['native_name'], default => $short };
                };
                $render = static function (string $where) use ($enabled, $label): string {
                    $current = locale();
                    $up = $where === 'footer' ? ' dropup' : '';
                    $html = '<div class="dropdown ap-lang-switch' . $up . '"><button type="button" class="btn btn-sm ' . ($where === 'footer' ? 'btn-link p-0 text-reset' : 'btn-outline-secondary') . ' dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Language"><i class="fa-solid fa-language me-1" aria-hidden="true"></i>'
                        . e($label($current, $enabled[$current] ?? ['native_name' => $current])) . '</button><ul class="dropdown-menu dropdown-menu-end">';
                    foreach ($enabled as $code => $l) {
                        $html .= '<li><a class="dropdown-item' . ($code === $current ? ' active' : '') . '" href="' . e(url('lang/' . $code) . '?back=' . rawurlencode(request()->path)) . '" hreflang="' . e($code) . '" lang="' . e($code) . '">' . e($l['native_name']) . '</a></li>';
                    }
                    return $html . '</ul></div>';
                };
                if ((bool) setting('languages.switcher_admin')) {
                    View::inject('admin.topbar', static fn(): string => $render('admin'));
                }
                $place = (string) setting('languages.switcher_place');
                if ($place !== 'footer') {
                    View::inject('nav', static fn(): string => $render('site'));
                }
                if ($place !== 'nav') {
                    View::inject('footer_links', static fn(): string => $render('footer'), 80);
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Languages module could not load languages: ' . $e->getMessage());
        }
    },
    'actions' => [
        'languages.add' => ['label' => 'Add a language', 'icon' => 'fa-language', 'url' => 'languages', 'permission' => 'languages.manage', 'keywords' => 'new language translate', 'info' => 'Languages'],
    ],
    'search' => [
        'languages' => ['label' => 'Languages', 'icon' => 'fa-language', 'permission' => 'languages.view', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('languages')->search(['name', 'native_name', 'code'], $term)->orderBy('name')->limit($limit)->get();
                return array_map(static fn($l) => ['title' => (string) $l['name'], 'subtitle' => 'Language · ' . $l['code'] . ' · translate its wording', 'url' => admin_url('languages/' . $l['code'] . '/translations'), 'score' => 3], $rows);
            }],
    ],
];
