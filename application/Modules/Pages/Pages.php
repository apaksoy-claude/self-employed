<?php
declare(strict_types=1);

namespace AP\Modules\Pages;

use AP\Core\DB;
use AP\Core\HtmlSanitizer;
use AP\Core\Kernel;
use AP\Core\Router;
use Throwable;

/** Shared page logic: addresses, publishing state, revisions and the dashboard box. */
final class Pages
{
    public const STATUSES = [
        'draft' => ['label' => 'Draft', 'badge' => 'secondary', 'icon' => 'fa-pen-ruler'],
        'published' => ['label' => 'Published', 'badge' => 'success', 'icon' => 'fa-circle-check'],
        'scheduled' => ['label' => 'Scheduled', 'badge' => 'info', 'icon' => 'fa-clock'],
    ];

    /** Page layouts. Themes can add more with the "pages.templates" filter. */
    public const TEMPLATES = [
        'default' => ['label' => 'Standard', 'help' => 'Title at the top, text in a readable column.'],
        'wide' => ['label' => 'Full width', 'help' => 'Title at the top, content across the whole page.'],
        'plain' => ['label' => 'No title', 'help' => 'Only the content. Handy for landing pages.'],
    ];

    /** Addresses that never belong to a page, besides the first parts of the system's own routes. */
    private const RESERVED = ['admin', 'install', 'cron', 'lang', 'recovery', 'assets', 'application', 'tools', 'docs', 'index.php', 'robots.txt', 'sitemap.xml', 'favicon.ico'];

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('pages');
        } catch (Throwable) {
            return false;
        }
    }

    public static function boot(): void
    {
        if (!self::ready()) {
            return;
        }
        // Fill the "Home page" choices with published pages (only needed on the admin pages).
        $onAdmin = str_starts_with(trim((string) request()->path, '/') . '/', trim(admin_path(), '/') . '/');
        try {
            $options = ['0' => 'The built-in welcome page'];
            foreach ($onAdmin ? self::db()->table('pages')->whereNull('deleted_at')->where('status', 'published')->orderBy('title')->limit(500)->get() : [] as $p) {
                $options[(string) $p['id']] = $p['title'] . '  (/' . $p['path'] . ')';
            }
            $def = settings()->definition('pages.home_id');
            if ($def !== null && $onAdmin) {
                settings()->define('pages.home_id', ['options' => $options] + $def);
            }
        } catch (Throwable) {
            // The settings page still works with the default choice.
        }
        // The front page shows the chosen home page.
        events()->addFilter('site.home', static function (mixed $response) {
            $id = (int) setting('pages.home_id', 0);
            if ($id <= 0) {
                return $response;
            }
            $page = self::db()->table('pages')->where('id', $id)->whereNull('deleted_at')->first();
            return $page && self::isLive($page) ? Controllers\PublicController::render($page, true) : $response;
        });
        // Published pages go into the sitemap (made by the SEO module).
        events()->addFilter('sitemap.urls', static function (array $urls): array {
            if (!setting('seo.sitemap_pages', true)) {
                return $urls;
            }
            $home = (int) setting('pages.home_id', 0);
            foreach (self::db()->table('pages')->whereNull('deleted_at')->whereIn('status', ['published', 'scheduled'])->where('noindex', 0)->orderBy('path')->limit(5000)->get() as $p) {
                if (!self::isLive($p) || (int) $p['id'] === $home) {
                    continue;
                }
                $urls[] = ['loc' => self::url($p), 'lastmod' => (string) $p['updated_at']];
            }
            return $urls;
        });
    }

    /** @return array<string, array{label:string,help:string}> */
    public static function templates(): array
    {
        return (array) filter('pages.templates', self::TEMPLATES);
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** Published, or scheduled with its time already passed (the scheduler may not have run yet). */
    public static function isLive(array $page): bool
    {
        if (!empty($page['deleted_at'])) {
            return false;
        }
        if ($page['status'] === 'published') {
            return true;
        }
        return $page['status'] === 'scheduled' && !empty($page['publish_at']) && (string) $page['publish_at'] <= self::now();
    }

    public static function url(array $page): string
    {
        return (int) setting('pages.home_id', 0) === (int) $page['id'] ? url('/') : url('/' . $page['path']);
    }

    /** Lower-case address part made of letters, numbers and hyphens. Keeps non-English letters. */
    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'ä' => 'a', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '&' => ' and '];
        $text = strtr($text, $map);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        return trim(mb_substr(trim($text, '-'), 0, 120), '-');
    }

    /** @return list<string> */
    public static function reserved(): array
    {
        $out = self::RESERVED;
        try {
            $out = array_merge($out, app(Router::class)->reservedSegments());
        } catch (Throwable) {
            // no router (tests)
        }
        $out[] = admin_path();
        return array_values(array_unique(array_map('strtolower', $out)));
    }

    /** Full address of a page: parent addresses then its own slug. */
    public static function buildPath(string $slug, ?int $parentId): string
    {
        $parts = [$slug];
        $seen = [];
        while ($parentId) {
            if (isset($seen[$parentId])) {
                break;
            }
            $seen[$parentId] = true;
            $parent = self::db()->table('pages')->where('id', $parentId)->first();
            if (!$parent) {
                break;
            }
            array_unshift($parts, (string) $parent['slug']);
            $parentId = $parent['parent_id'] ? (int) $parent['parent_id'] : null;
        }
        return implode('/', $parts);
    }

    /** A slug that is free at that level: "about", "about-2", "about-3"... */
    public static function uniqueSlug(string $slug, ?int $parentId, int $ignoreId = 0): string
    {
        $base = $slug !== '' ? $slug : 'page';
        $candidate = $base;
        $n = 2;
        while (self::slugTaken($candidate, $parentId, $ignoreId)) {
            $candidate = $base . '-' . $n++;
        }
        return $candidate;
    }

    public static function slugTaken(string $slug, ?int $parentId, int $ignoreId = 0): bool
    {
        if ($parentId === null && in_array(strtolower($slug), self::reserved(), true)) {
            return true;
        }
        $q = self::db()->table('pages')->where('slug', $slug)->whereNull('deleted_at')->where('id', '!=', $ignoreId);
        $parentId ? $q->where('parent_id', $parentId) : $q->whereNull('parent_id');
        return $q->exists();
    }

    /** After a page moved or was renamed, its children's addresses change too. */
    public static function rebuildChildPaths(int $parentId, int $depth = 0): void
    {
        if ($depth > 20) {
            return;
        }
        foreach (self::db()->table('pages')->where('parent_id', $parentId)->get() as $child) {
            self::db()->table('pages')->where('id', (int) $child['id'])->update(['path' => self::buildPath((string) $child['slug'], $parentId)]);
            self::rebuildChildPaths((int) $child['id'], $depth + 1);
        }
    }

    /** Ids of a page and everything below it (a page cannot sit under itself). @return list<int> */
    public static function descendants(int $id): array
    {
        $out = [$id];
        $queue = [$id];
        while ($queue && count($out) < 2000) {
            $next = array_shift($queue);
            foreach (self::db()->table('pages')->where('parent_id', $next)->pluck('id') as $child) {
                $out[] = (int) $child;
                $queue[] = (int) $child;
            }
        }
        return $out;
    }

    /** Pages to choose a parent from, indented by level. @return array<int,string> */
    public static function parentOptions(int $excludeId = 0): array
    {
        $exclude = $excludeId ? self::descendants($excludeId) : [];
        $rows = self::db()->table('pages')->whereNull('deleted_at')->orderBy('sort_order')->orderBy('title')->get();
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $out = [];
        $walk = static function (int $parent, int $level) use (&$walk, &$out, $byParent, $exclude): void {
            foreach ($byParent[$parent] ?? [] as $r) {
                if (in_array((int) $r['id'], $exclude, true) || $level > 8) {
                    continue;
                }
                $out[(int) $r['id']] = str_repeat('— ', $level) . $r['title'];
                $walk((int) $r['id'], $level + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function saveRevision(array $page, string $note = ''): void
    {
        $user = auth_user();
        self::db()->table('page_revisions')->insert([
            'page_id' => (int) $page['id'], 'user_id' => $user['id'] ?? null, 'user_name' => $user['name'] ?? null,
            'title' => (string) $page['title'], 'content' => $page['content'],
            'data' => json_encode(array_intersect_key($page, array_flip(['slug', 'parent_id', 'status', 'publish_at', 'template', 'excerpt', 'seo_title', 'seo_description', 'social_image', 'noindex'])), JSON_UNESCAPED_UNICODE),
            'note' => $note !== '' ? mb_substr($note, 0, 190) : null, 'created_at' => self::now(),
        ]);
        $keep = max(1, (int) setting('pages.revisions_keep', 25));
        $ids = self::db()->table('page_revisions')->where('page_id', (int) $page['id'])->orderBy('id', 'desc')->pluck('id');
        $old = array_slice(array_map('intval', $ids), $keep);
        if ($old) {
            self::db()->table('page_revisions')->whereIn('id', $old)->delete();
        }
    }

    public static function publishDue(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $n = self::db()->table('pages')->where('status', 'scheduled')->whereNotNull('publish_at')->where('publish_at', '<=', self::now())
            ->whereNull('deleted_at')->update(['status' => 'published', 'updated_at' => self::now()]);
        if ($n) {
            event('pages.changed', 0);
        }
        return $n;
    }

    public static function emptyBin(): int
    {
        $days = (int) setting('pages.trash_days', 30);
        if ($days <= 0 || !self::ready()) {
            return 0;
        }
        $ids = self::db()->table('pages')->whereNotNull('deleted_at')->where('deleted_at', '<', gmdate('Y-m-d H:i:s', time() - $days * 86400))->pluck('id');
        foreach ($ids as $id) {
            self::destroy((int) $id);
        }
        return count($ids);
    }

    public static function destroy(int $id): void
    {
        self::db()->table('page_revisions')->where('page_id', $id)->delete();
        self::db()->table('pages')->where('parent_id', $id)->update(['parent_id' => null]);
        self::db()->table('pages')->where('id', $id)->delete();
        if ((int) setting('pages.home_id', 0) === $id) {
            settings()->set('pages.home_id', '0');
        }
        event('pages.changed', $id);
    }

    /** Clean editor HTML (no scripts, no event handlers, safe links only). */
    public static function cleanHtml(string $html): string
    {
        $clean = HtmlSanitizer::clean($html);
        // Empty paragraphs left behind by the editor.
        $clean = (string) preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $clean);
        // Loose text at the top level goes into a paragraph.
        if ($clean !== '' && !preg_match('#^\s*<(p|h[1-6]|ul|ol|blockquote|pre|table|figure|div|hr|img|dl)\b#i', $clean)) {
            $cut = preg_match('#<(p|h[1-6]|ul|ol|blockquote|pre|table|figure|div|hr|dl)\b#i', $clean, $m, PREG_OFFSET_CAPTURE) ? (int) $m[0][1] : strlen($clean);
            $clean = '<p>' . trim(substr($clean, 0, $cut)) . '</p>' . substr($clean, $cut);
        }
        return trim($clean);
    }

    public static function widget(): string
    {
        if (!self::ready()) {
            return '<p class="small text-muted mb-0">Press "Finish update" to start using pages.</p>';
        }
        $counts = ['published' => 0, 'draft' => 0, 'scheduled' => 0];
        foreach (self::db()->table('pages')->select('status')->whereNull('deleted_at')->get() as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
        }
        $html = '<div class="d-flex gap-3 mb-3 small">';
        foreach ($counts as $s => $n) {
            $html .= '<a class="text-decoration-none" href="' . e(admin_url('pages') . '?status=' . $s) . '"><span class="fw-semibold fs-5 d-block">' . $n . '</span>' . e(self::STATUSES[$s]['label']) . '</a>';
        }
        $html .= '</div>';
        $recent = self::db()->table('pages')->whereNull('deleted_at')->orderBy('updated_at', 'desc')->limit(4)->get();
        if ($recent) {
            $html .= '<ul class="list-unstyled small mb-3">';
            foreach ($recent as $p) {
                $html .= '<li class="d-flex justify-content-between gap-2 py-1"><a class="text-truncate" href="' . e(admin_url('pages/' . (int) $p['id'] . '/edit')) . '">' . e($p['title']) . '</a><span class="text-muted text-nowrap">' . e(time_ago((string) $p['updated_at'])) . '</span></li>';
            }
            $html .= '</ul>';
        }
        if (can('pages.edit')) {
            $html .= '<a class="btn btn-sm btn-outline-primary" href="' . e(admin_url('pages/create')) . '"><i class="fa-solid fa-plus me-1"></i>Add a Page</a>';
        }
        return $html;
    }
}
