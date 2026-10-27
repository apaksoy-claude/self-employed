<?php
declare(strict_types=1);

namespace AP\Modules\Footer;

use AP\Core\HtmlSanitizer;
use AP\Core\ModuleManager;
use AP\Core\View;
use AP\Modules\Menus\Menus;
use AP\Modules\Site\Site;
use Throwable;

/**
 * The footer builder: rows of columns (widths out of 12) holding blocks, a bottom bar and the look.
 * Stored as one JSON setting ("footer.layout"). Modules add block types with the "footer.blocks" filter
 * and render them with the "footer.block" filter: filter('footer.block', '', $block) returns the HTML.
 */
final class Footer
{
    public const MAX_ROWS = 6;
    public const MAX_COLS = 6;
    public const MAX_BLOCKS = 10;
    public const MAX_LINKS = 30;

    public const THEMES = ['light' => 'Light', 'soft' => 'Soft grey', 'dark' => 'Dark', 'custom' => 'My own colours'];
    public const WIDTHS = ['container' => 'Same as the page', 'wide' => 'Wide', 'full' => 'Edge to edge'];
    public const SPACING = ['compact' => 'Compact', 'normal' => 'Normal', 'roomy' => 'Roomy'];
    public const PHONE = ['stack' => 'One under another', 'two' => 'Two side by side'];
    public const ALIGN = ['start' => 'Left', 'center' => 'Centre', 'end' => 'Right'];

    /**
     * Block types: label, icon, short help and the fields the builder shows.
     * $forEditor lists every menu by name (one database query); the website does not need the names, so it skips that.
     */
    public static function blocks(bool $forEditor = false): array
    {
        $menus = ['loc:footer' => 'The footer menu (Menus > Footer menu)', 'loc:header' => 'The main menu'];
        try {
            if ($forEditor && class_exists(Menus::class) && Menus::ready()) {
                foreach (Menus::db()->table('menus')->orderBy('name')->get() as $m) {
                    $menus['id:' . $m['id']] = 'Menu: ' . $m['name'];
                }
            }
        } catch (Throwable) {
            // no menus yet
        }
        $title = ['key' => 'title', 'label' => 'Heading', 'type' => 'text', 'placeholder' => 'Optional', 'max' => 80];
        $blocks = [
            'logo' => ['label' => 'Logo and name', 'icon' => 'fa-copyright', 'help' => 'Your logo, the site name and the tagline.', 'fields' => [
                ['key' => 'image', 'label' => 'Logo picture', 'type' => 'image', 'help' => 'Empty: the site name is written instead.'],
                ['key' => 'height', 'label' => 'Logo height (pixels)', 'type' => 'number', 'min' => 16, 'max' => 160, 'default' => 40, 'half' => true],
                ['key' => 'show_name', 'label' => 'Write the site name', 'type' => 'checkbox', 'default' => true, 'half' => true],
                ['key' => 'tagline', 'label' => 'Text under it', 'type' => 'textarea', 'placeholder' => 'Empty: the tagline from Settings > General', 'max' => 400],
            ]],
            'about' => ['label' => 'About text', 'icon' => 'fa-align-left', 'help' => 'A heading and a few sentences.', 'fields' => [
                $title, ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'max' => 1500, 'default' => ''],
            ]],
            'menu' => ['label' => 'Menu', 'icon' => 'fa-bars-staggered', 'help' => 'One of your menus as a list of links.', 'fields' => [
                $title, ['key' => 'menu', 'label' => 'Which menu', 'type' => 'select', 'options' => $menus, 'default' => 'loc:footer'],
                ['key' => 'inline', 'label' => 'Links side by side', 'type' => 'checkbox', 'default' => false],
            ]],
            'links' => ['label' => 'Links', 'icon' => 'fa-link', 'help' => 'Your own list of links, without making a menu.', 'fields' => [
                $title, ['key' => 'links', 'label' => 'Links', 'type' => 'links'],
                ['key' => 'inline', 'label' => 'Links side by side', 'type' => 'checkbox', 'default' => false],
            ]],
            'social' => ['label' => 'Social media', 'icon' => 'fa-share-nodes', 'help' => 'The icons from Settings > Social Media.', 'fields' => [
                $title, ['key' => 'names', 'label' => 'Write the names next to the icons', 'type' => 'checkbox', 'default' => false],
            ]],
            'contact' => ['label' => 'Contact details', 'icon' => 'fa-address-card', 'help' => 'From Settings > Contact Details.', 'fields' => [
                $title,
                ['key' => 'address', 'label' => 'Address', 'type' => 'checkbox', 'default' => true, 'half' => true],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'checkbox', 'default' => true, 'half' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'checkbox', 'default' => true, 'half' => true],
                ['key' => 'hours', 'label' => 'Opening hours', 'type' => 'checkbox', 'default' => false, 'half' => true],
            ]],
            'text' => ['label' => 'Text or HTML', 'icon' => 'fa-code', 'help' => 'Anything else: simple HTML such as <strong>, <a> and <br> is kept.', 'fields' => [
                $title, ['key' => 'html', 'label' => 'Text', 'type' => 'textarea', 'max' => 5000, 'default' => ''],
            ]],
            'button' => ['label' => 'Button', 'icon' => 'fa-hand-pointer', 'help' => 'A button such as "Book a call".', 'fields' => [
                $title, ['key' => 'label', 'label' => 'Button text', 'type' => 'text', 'default' => 'Get in touch', 'max' => 60, 'half' => true],
                ['key' => 'url', 'label' => 'Address', 'type' => 'url', 'default' => '/contact', 'half' => true],
                ['key' => 'style', 'label' => 'Look', 'type' => 'select', 'options' => ['solid' => 'Filled', 'outline' => 'Outline'], 'default' => 'solid', 'half' => true],
                ['key' => 'new_tab', 'label' => 'Open in a new tab', 'type' => 'checkbox', 'default' => false, 'half' => true],
            ]],
        ];
        if (self::moduleOn('languages')) {
            $blocks['languages'] = ['label' => 'Language choice', 'icon' => 'fa-language', 'help' => 'Links to the other languages of the site (when there is more than one).', 'fields' => [$title]];
        }
        return (array) filter('footer.blocks', $blocks);
    }

    public static function moduleOn(string $name): bool
    {
        try {
            return app(ModuleManager::class)->isLoaded($name);
        } catch (Throwable) {
            return false;
        }
    }

    /** A sensible footer for a new site. */
    public static function defaults(): array
    {
        return [
            'rows' => [
                ['phone' => 'stack', 'align' => 'start', 'columns' => [
                    ['width' => 5, 'blocks' => [['type' => 'logo', 'show_name' => true, 'height' => 40, 'image' => '', 'tagline' => ''], ['type' => 'social', 'title' => '', 'names' => false]]],
                    ['width' => 3, 'blocks' => [['type' => 'menu', 'title' => 'Links', 'menu' => 'loc:footer', 'inline' => false]]],
                    ['width' => 4, 'blocks' => [['type' => 'contact', 'title' => 'Contact', 'address' => true, 'phone' => true, 'email' => true, 'hours' => false]]],
                ]],
            ],
            'bottom' => ['show' => true, 'copyright' => '© {year} {site}. All rights reserved.', 'links' => [], 'extra' => true, 'top' => true, 'small_print' => '', 'align' => 'between'],
            'style' => ['theme' => 'light', 'bg' => '#151b26', 'text' => '#d7dbe3', 'accent' => '#ffffff', 'width' => 'container', 'spacing' => 'normal', 'fold' => true, 'border' => true],
        ];
    }

    public static function layout(): array
    {
        $raw = (string) setting('footer.layout');
        $data = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($data) ? self::clean($data) : self::defaults();
    }

    /** Make posted (or stored) data safe and complete. */
    public static function clean(array $in): array
    {
        $def = self::defaults();
        $types = self::blocks();
        $rows = [];
        foreach (array_slice(array_values(array_filter((array) ($in['rows'] ?? []), 'is_array')), 0, self::MAX_ROWS) as $row) {
            $cols = [];
            foreach (array_slice(array_values(array_filter((array) ($row['columns'] ?? []), 'is_array')), 0, self::MAX_COLS) as $col) {
                $blocks = [];
                foreach (array_slice(array_values(array_filter((array) ($col['blocks'] ?? []), 'is_array')), 0, self::MAX_BLOCKS) as $b) {
                    $type = (string) ($b['type'] ?? '');
                    if (!isset($types[$type])) {
                        continue;
                    }
                    $blocks[] = self::cleanBlock($b, $types[$type]['fields']);
                }
                $cols[] = ['width' => max(1, min(12, (int) ($col['width'] ?? 4))), 'blocks' => $blocks];
            }
            $rows[] = ['phone' => self::pick($row['phone'] ?? '', self::PHONE, 'stack'), 'align' => self::pick($row['align'] ?? '', self::ALIGN, 'start'), 'columns' => $cols];
        }
        $b = (array) ($in['bottom'] ?? []);
        $s = (array) ($in['style'] ?? []);
        return [
            'rows' => $rows,
            'bottom' => [
                'show' => (bool) ($b['show'] ?? true),
                'copyright' => mb_substr(trim((string) ($b['copyright'] ?? $def['bottom']['copyright'])), 0, 300),
                'links' => self::cleanLinks((array) ($b['links'] ?? [])),
                'extra' => (bool) ($b['extra'] ?? true),
                'top' => (bool) ($b['top'] ?? true),
                'small_print' => mb_substr(trim((string) ($b['small_print'] ?? '')), 0, 2000),
                'align' => self::pick($b['align'] ?? '', ['between' => 'Spread out', 'center' => 'Centre'], 'between'),
            ],
            'style' => [
                'theme' => self::pick($s['theme'] ?? '', self::THEMES, 'light'),
                'bg' => self::colour($s['bg'] ?? '', $def['style']['bg']),
                'text' => self::colour($s['text'] ?? '', $def['style']['text']),
                'accent' => self::colour($s['accent'] ?? '', $def['style']['accent']),
                'width' => self::pick($s['width'] ?? '', self::WIDTHS, 'container'),
                'spacing' => self::pick($s['spacing'] ?? '', self::SPACING, 'normal'),
                'fold' => (bool) ($s['fold'] ?? true),
                'border' => (bool) ($s['border'] ?? true),
            ],
        ];
    }

    private static function cleanBlock(array $b, array $fields): array
    {
        $out = ['type' => (string) $b['type']];
        foreach ($fields as $f) {
            $k = $f['key'];
            $v = $b[$k] ?? ($f['default'] ?? null);
            switch ($f['type']) {
                case 'checkbox':
                    $out[$k] = (bool) $v;
                    break;
                case 'number':
                    $out[$k] = max((int) ($f['min'] ?? 0), min((int) ($f['max'] ?? 9999), (int) ($v ?? $f['default'] ?? 0)));
                    break;
                case 'select':
                    // A menu chosen by number is checked by its shape, so reading the footer needs no list of menus.
                    $ok = isset($f['options'][(string) $v]) || ($k === 'menu' && preg_match('/^(loc:[a-z_]{1,40}|id:\d{1,10})$/', (string) $v));
                    $out[$k] = $ok ? (string) $v : (string) ($f['default'] ?? array_key_first($f['options']));
                    break;
                case 'links':
                    $out[$k] = self::cleanLinks((array) $v);
                    break;
                case 'image':
                case 'url':
                    $u = trim((string) $v);
                    $out[$k] = $u !== '' && HtmlSanitizer::safeUrl($u, $f['type'] === 'image') ? mb_substr($u, 0, 500) : '';
                    break;
                default:
                    $out[$k] = mb_substr(trim((string) $v), 0, (int) ($f['max'] ?? 500));
            }
        }
        return $out;
    }

    /** @return list<array{label:string,url:string,new_tab:bool}> */
    private static function cleanLinks(array $links): array
    {
        $out = [];
        foreach ($links as $l) {
            if (!is_array($l) || count($out) >= self::MAX_LINKS) {
                continue;
            }
            $label = mb_substr(trim((string) ($l['label'] ?? '')), 0, 80);
            $url = mb_substr(trim((string) ($l['url'] ?? '')), 0, 500);
            if ($label === '' || $url === '' || !HtmlSanitizer::safeUrl($url)) {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url, 'new_tab' => !empty($l['new_tab'])];
        }
        return $out;
    }

    private static function pick(mixed $v, array $allowed, string $default): string
    {
        return is_string($v) && isset($allowed[$v]) ? $v : $default;
    }

    private static function colour(mixed $v, string $default): string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $default;
    }

    private static function href(string $u): string
    {
        return str_starts_with($u, '/') && !str_starts_with($u, '//') ? url($u) : $u;
    }

    /* ------------------------------------------------------------------ the website */

    public static function render(?array $layout = null): string
    {
        $l = $layout ?? self::layout();
        $s = $l['style'];
        $classes = 'ap-footer is-' . $s['theme'] . ' is-' . $s['spacing'] . ($s['fold'] ? ' is-fold' : '') . ($s['border'] ? ' has-border' : '');
        $style = $s['theme'] === 'custom' ? ' style="--apf-bg:' . e($s['bg']) . ';--apf-text:' . e($s['text']) . ';--apf-accent:' . e($s['accent']) . '"' : '';
        $wrap = ['container' => 'container', 'wide' => 'container-xxl', 'full' => 'container-fluid px-4'][$s['width']];
        $html = '<footer class="' . e($classes) . '"' . $style . '><div class="' . $wrap . '">';
        foreach ($l['rows'] as $ri => $row) {
            $inner = '';
            foreach ($row['columns'] as $col) {
                $blocks = '';
                foreach ($col['blocks'] as $bi => $b) {
                    $blocks .= self::block($b, 'f' . $ri . '-' . $bi . '-' . substr(md5(json_encode($b) ?: ''), 0, 4));
                }
                $phone = $row['phone'] === 'two' ? 'col-6' : 'col-12';
                $inner .= '<div class="' . $phone . ' col-md-' . (int) $col['width'] . ' ap-footer-col">' . $blocks . '</div>';
            }
            if ($inner !== '') {
                $html .= '<div class="row g-4 ap-footer-row text-md-' . e($row['align']) . '">' . $inner . '</div>';
            }
        }
        $b = $l['bottom'];
        if ($b['show']) {
            $links = '';
            foreach ($b['links'] as $k) {
                $links .= '<a href="' . e(self::href($k['url'])) . '"' . ($k['new_tab'] ? ' target="_blank" rel="noopener"' : '') . '>' . e($k['label']) . '</a>';
            }
            if ($b['extra']) {
                $links .= View::slotHtml('footer_links');
            }
            if ($b['top']) {
                $links .= '<a href="#" class="ap-footer-top" data-ap-top><i class="fa-solid fa-arrow-up me-1" aria-hidden="true"></i>' . e(__('site.back_to_top')) . '</a>';
            }
            $copy = strtr($b['copyright'], ['{year}' => date('Y'), '{site}' => (string) setting('general.site_name', 'My Website')]);
            $html .= '<div class="ap-footer-bottom is-' . e($b['align']) . '"><span>' . View::slotHtml('footer') . e($copy) . '</span>'
                . ($links !== '' ? '<span class="ap-footer-links">' . $links . '</span>' : '') . '</div>';
            if ($b['small_print'] !== '') {
                $html .= '<div class="ap-footer-small">' . nl2br(e($b['small_print'])) . '</div>';
            }
        }
        return $html . '</div></footer>';
    }

    private static function block(array $b, string $id): string
    {
        $body = match ($b['type']) {
            'logo' => self::logo($b),
            'about' => $b['text'] !== '' ? '<p>' . nl2br(e($b['text'])) . '</p>' : '',
            'menu' => self::menu($b),
            'links' => self::linkList($b['links'], (bool) $b['inline']),
            'social' => self::social((bool) $b['names']),
            'contact' => self::contact($b),
            'text' => $b['html'] !== '' ? '<div class="ap-fb-text">' . HtmlSanitizer::clean(nl2br($b['html'], false)) . '</div>' : '',
            'button' => $b['label'] !== '' && $b['url'] !== '' ? '<a class="btn ' . ($b['style'] === 'outline' ? 'btn-outline-light ap-fb-btn-outline' : 'ap-fb-btn') . '" href="' . e(self::href($b['url'])) . '"'
                . ($b['new_tab'] ? ' target="_blank" rel="noopener"' : '') . '>' . e($b['label']) . '</a>' : '',
            'languages' => self::languages(),
            default => (string) filter('footer.block', '', $b),
        };
        if ($body === '') {
            return '';
        }
        $title = trim((string) filter('footer.block_title', trim((string) ($b['title'] ?? '')), $b));
        if ($title === '') {
            return '<div class="ap-fb ap-fb-' . e($b['type']) . '">' . $body . '</div>';
        }
        return '<div class="ap-fb ap-fb-' . e($b['type']) . ' has-title"><h2 class="ap-fb-title"><button type="button" class="ap-fb-toggle" aria-expanded="false" aria-controls="' . e($id) . '">' . e($title)
            . '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button><span class="ap-fb-heading">' . e($title) . '</span></h2><div class="ap-fb-body" id="' . e($id) . '">' . $body . '</div></div>';
    }

    private static function logo(array $b): string
    {
        $name = (string) setting('general.site_name', 'My Website');
        $tag = $b['tagline'] !== '' ? $b['tagline'] : (string) setting('general.tagline', '');
        $h = '<a class="ap-fb-brand" href="' . e(url('/')) . '">';
        if ($b['image'] !== '') {
            $h .= '<img src="' . e(self::href($b['image'])) . '" alt="' . e($name) . '" style="height:' . (int) $b['height'] . 'px;width:auto" loading="lazy">';
        }
        if ($b['show_name'] || $b['image'] === '') {
            $h .= '<span>' . e($name) . '</span>';
        }
        $h .= '</a>';
        return $h . ($tag !== '' ? '<p class="ap-fb-tagline">' . nl2br(e($tag)) . '</p>' : '');
    }

    private static function menu(array $b): string
    {
        if (!class_exists(Menus::class) || !self::moduleOn('menus')) {
            return '';
        }
        [$kind, $ref] = explode(':', (string) $b['menu'], 2) + ['', ''];
        $items = $kind === 'id' ? Menus::itemsOfMenu((int) $ref) : Menus::items($ref);
        $links = [];
        foreach ($items as $i) {
            if ($i['url'] === '#') {
                foreach ($i['children'] as $c) {
                    $links[] = ['label' => $c['label'], 'url' => $c['url'], 'new_tab' => $c['new_tab'], 'active' => $c['active']];
                }
                continue;
            }
            $links[] = ['label' => $i['label'], 'url' => $i['url'], 'new_tab' => $i['new_tab'], 'active' => $i['active']];
        }
        return self::linkList($links, (bool) $b['inline'], false);
    }

    private static function linkList(array $links, bool $inline, bool $resolve = true): string
    {
        if (!$links) {
            return '';
        }
        $h = '<ul class="ap-fb-links' . ($inline ? ' is-inline' : '') . '">';
        foreach ($links as $l) {
            $h .= '<li><a href="' . e($resolve ? self::href($l['url']) : $l['url']) . '"' . ($l['new_tab'] ? ' target="_blank" rel="noopener"' : '') . (!empty($l['active']) ? ' aria-current="page"' : '') . '>' . e($l['label']) . '</a></li>';
        }
        return $h . '</ul>';
    }

    private static function social(bool $names): string
    {
        if (!class_exists(Site::class)) {
            return '';
        }
        if (!$names) {
            return Site::socialIcons('ap-social ap-fb-social');
        }
        $links = Site::social();
        if (!$links) {
            return '';
        }
        $target = setting('social.new_tab', true) ? ' target="_blank" rel="noopener me"' : ' rel="me"';
        $h = '<ul class="ap-fb-links ap-fb-social-names">';
        foreach ($links as $l) {
            $h .= '<li><a href="' . e($l['url']) . '"' . $target . '><i class="' . e($l['icon']) . ' me-2" aria-hidden="true"></i>' . e($l['label']) . '</a></li>';
        }
        return $h . '</ul>';
    }

    private static function contact(array $b): string
    {
        $rows = '';
        $address = trim((string) setting('contact.address', ''));
        $phone = trim((string) setting('contact.phone', ''));
        $email = trim((string) setting('contact.email', ''));
        if ($b['address'] && $address !== '') {
            $rows .= '<li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' . nl2br(e($address)) . '</span></li>';
        }
        if ($b['phone'] && $phone !== '') {
            $rows .= '<li><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone) ?? '') . '">' . e($phone) . '</a></li>';
        }
        if ($b['email'] && $email !== '') {
            $rows .= '<li><i class="fa-solid fa-envelope" aria-hidden="true"></i><a href="mailto:' . e($email) . '">' . e($email) . '</a></li>';
        }
        if ($b['hours']) {
            $hours = setting('contact.opening_hours');
            $list = '';
            foreach (is_array($hours) ? $hours : [] as $h) {
                if (!is_array($h) || trim((string) ($h['days'] ?? '')) === '') {
                    continue;
                }
                $list .= '<span class="d-block">' . e((string) $h['days']) . ': ' . (!empty($h['closed']) && $h['closed'] !== '0' ? e(__('site.closed')) : e((string) ($h['hours'] ?? ''))) . '</span>';
            }
            if ($list !== '') {
                $rows .= '<li><i class="fa-regular fa-clock" aria-hidden="true"></i><span>' . $list . '</span></li>';
            }
        }
        return $rows !== '' ? '<ul class="ap-fb-contact">' . $rows . '</ul>' : '';
    }

    private static function languages(): string
    {
        try {
            $enabled = \AP\Modules\Languages\Locales::enabled();
        } catch (Throwable) {
            return '';
        }
        if (count($enabled) < 2) {
            return '';
        }
        $current = locale();
        $h = '<ul class="ap-fb-links is-inline">';
        foreach ($enabled as $code => $l) {
            $h .= '<li><a href="' . e(url('lang/' . $code) . '?back=' . rawurlencode(request()->path)) . '" hreflang="' . e((string) $code) . '"' . ($code === $current ? ' aria-current="true"' : '') . '>' . e((string) $l['native_name']) . '</a></li>';
        }
        return $h . '</ul>';
    }
}
