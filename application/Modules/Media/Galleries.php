<?php
declare(strict_types=1);

namespace AP\Modules\Media;

use AP\Core\Kernel;
use Throwable;

/** Galleries: named sets of library pictures, in an order you choose, shown on a page of their own or inside any page with [gallery slug="name"]. */
final class Galleries
{
    public const LAYOUTS = ['grid' => 'Grid', 'masonry' => 'Masonry (different heights)', 'slider' => 'Slider (swipe sideways)'];
    public const RATIOS = ['square' => 'Square', 'landscape' => 'Landscape (4:3)', 'portrait' => 'Portrait (3:4)', 'wide' => 'Wide (16:9)', 'original' => 'Keep each picture\'s own shape'];

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && Media::db()->hasTable('media_galleries');
        } catch (Throwable) {
            return false;
        }
    }

    public static function slugify(string $text): string
    {
        return \AP\Modules\Pages\Pages::slugify($text) ?: 'gallery';
    }

    /** A slug nobody else uses (the gallery itself, when changing, is ignored). */
    public static function freeSlug(string $slug, int $ignore = 0): string
    {
        $base = self::slugify($slug);
        $try = $base;
        $n = 1;
        while (Media::db()->table('media_galleries')->where('slug', $try)->where('id', '!=', $ignore)->exists()) {
            $try = $base . '-' . (++$n);
        }
        return $try;
    }

    /** @return array<string,mixed>|null */
    public static function find(string $key, bool $onlyLive = true): ?array
    {
        $q = Media::db()->table('media_galleries');
        ctype_digit($key) ? $q->where('id', (int) $key) : $q->where('slug', $key);
        $g = $q->first();
        if (!$g || ($onlyLive && $g['status'] !== 'published')) {
            return null;
        }
        return $g;
    }

    /** Pictures of a gallery in order. @return list<array{item_id:int,caption:string,media:array<string,mixed>}> */
    public static function items(int $galleryId): array
    {
        $rows = Media::db()->table('media_gallery_items')->where('gallery_id', $galleryId)->orderBy('sort_order')->orderBy('id')->get();
        if (!$rows) {
            return [];
        }
        $media = [];
        foreach (Media::db()->table('media')->whereIn('id', array_map(static fn($r) => (int) $r['media_id'], $rows))->get() as $m) {
            $media[(int) $m['id']] = $m;
        }
        $out = [];
        foreach ($rows as $r) {
            if (isset($media[(int) $r['media_id']])) {
                $out[] = ['item_id' => (int) $r['id'], 'caption' => (string) ($r['caption'] ?? ''), 'media' => $media[(int) $r['media_id']]];
            }
        }
        return $out;
    }

    /** Number of pictures in each gallery. @return array<int,int> */
    public static function counts(): array
    {
        $out = [];
        foreach (Media::db()->table('media_gallery_items')->select('gallery_id')->get() as $r) {
            $out[(int) $r['gallery_id']] = ($out[(int) $r['gallery_id']] ?? 0) + 1;
        }
        return $out;
    }

    /** Puts pictures at the end of a gallery (pictures already in it are skipped). Returns how many were added. */
    public static function add(int $galleryId, array $mediaIds): int
    {
        $db = Media::db();
        $have = array_map('intval', array_column($db->table('media_gallery_items')->where('gallery_id', $galleryId)->select('media_id')->get(), 'media_id'));
        $next = (int) $db->table('media_gallery_items')->where('gallery_id', $galleryId)->orderBy('sort_order', 'desc')->value('sort_order') + 1;
        $n = 0;
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds))));
        $valid = $ids ? array_map('intval', array_column($db->table('media')->whereIn('id', $ids)->where('kind', 'image')->select('id')->get(), 'id')) : [];
        foreach ($ids as $id) {
            if (in_array($id, $valid, true) && !in_array($id, $have, true)) {
                $db->table('media_gallery_items')->insert(['gallery_id' => $galleryId, 'media_id' => $id, 'sort_order' => $next++]);
                $n++;
            }
        }
        if ($n) {
            $db->table('media_galleries')->where('id', $galleryId)->update(['updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        return $n;
    }

    /** The cover: the chosen picture, else the first one. @return array<string,mixed>|null */
    public static function cover(array $g): ?array
    {
        if (!empty($g['cover_id'])) {
            $m = Media::db()->table('media')->where('id', (int) $g['cover_id'])->first();
            if ($m) {
                return $m;
            }
        }
        $first = Media::db()->table('media_gallery_items')->where('gallery_id', (int) $g['id'])->orderBy('sort_order')->orderBy('id')->first();
        return $first ? Media::db()->table('media')->where('id', (int) $first['media_id'])->first() : null;
    }

    /** The gallery as html, for its own page and for the [gallery] short code. */
    public static function render(array $g): string
    {
        $items = self::items((int) $g['id']);
        if (!$items) {
            return '';
        }
        $layout = isset(self::LAYOUTS[$g['layout']]) ? (string) $g['layout'] : 'grid';
        $ratio = isset(self::RATIOS[$g['ratio']]) ? (string) $g['ratio'] : 'square';
        $cols = max(1, min(8, (int) $g['columns']));
        $gap = max(0, min(40, (int) $g['gap']));
        $lightbox = (int) $g['lightbox'] === 1;
        $captions = (int) $g['captions'] === 1;
        $html = '<div class="apg apg-' . e($layout) . ' apg-r-' . e($ratio) . '" style="--apg-cols:' . $cols . ';--apg-gap:' . $gap . 'px" data-apg' . ($lightbox ? ' data-apg-lightbox' : '') . '>';
        foreach ($items as $it) {
            $m = $it['media'];
            $thumb = Media::fileUrl($m, 'medium') ?: Media::fileUrl($m);
            $alt = (string) ($m['alt'] ?: ($it['caption'] ?: $m['title']));
            $full = Media::url((string) $m['path']);
            $cap = $it['caption'] !== '' ? $it['caption'] : (string) ($m['caption'] ?? '');
            $img = '<img src="' . e($thumb) . '" alt="' . e($alt) . '" loading="lazy"' . ($m['width'] && $m['height'] ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : '') . '>';
            $html .= '<figure class="apg-item">'
                . ($lightbox ? '<a href="' . e($full) . '" data-apg-full="' . e($full) . '" data-apg-caption="' . e($cap) . '" aria-label="' . e($alt !== '' ? $alt : 'Open picture') . '">' . $img . '</a>' : $img)
                . ($captions && $cap !== '' ? '<figcaption>' . e($cap) . '</figcaption>' : '') . '</figure>';
        }
        $html .= '</div>';
        if ($lightbox) {
            $html .= '<script src="' . e(asset('js/gallery.js')) . '?v=' . e(AP_VERSION) . '" defer></script>';
        }
        return $html;
    }

    /** Swaps [gallery slug="holiday"] (or id="3") in page content for the gallery. */
    public static function shortcode(string $html): string
    {
        if (!str_contains($html, '[gallery') || !self::ready()) {
            return $html;
        }
        return (string) preg_replace_callback('#(?:<p>\s*)?\[gallery\s+(?:slug|id)\s*=\s*["\x{201C}\x{201D}]?([A-Za-z0-9\-_\pL]+)["\x{201C}\x{201D}]?\s*\](?:\s*</p>)?#u', static function (array $m): string {
            $g = self::find($m[1]);
            return $g ? self::render($g) : '';
        }, $html);
    }

    public static function boot(): void
    {
        events()->addFilter('pages.content', static fn(string $html): string => self::shortcode($html));
        events()->addFilter('editor.inserts', static function (array $list): array {
            if (!self::ready()) {
                return $list;
            }
            foreach (Media::db()->table('media_galleries')->where('status', 'published')->orderBy('name')->limit(20)->get() as $g) {
                $list[] = ['label' => 'Insert gallery: ' . $g['name'], 'icon' => 'fa-images', 'html' => '<p>[gallery slug="' . $g['slug'] . '"]</p>'];
            }
            return $list;
        });
    }
}
