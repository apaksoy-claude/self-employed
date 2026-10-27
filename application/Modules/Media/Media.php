<?php
declare(strict_types=1);

namespace AP\Modules\Media;

use AP\Core\DB;
use AP\Core\Image;
use AP\Core\Kernel;
use AP\Core\Upload;
use Throwable;

/** The media library: where files are kept on disk, how they are added, described and removed. */
final class Media
{
    public const DIR = 'assets/uploads';
    /** Folder that holds "assets/uploads"; tests point it somewhere temporary. */
    public static ?string $base = null;

    public const KINDS = ['image' => ['Images', 'fa-image'], 'document' => ['Documents', 'fa-file-lines'], 'video' => ['Videos', 'fa-film'], 'audio' => ['Audio', 'fa-music'], 'file' => ['Other files', 'fa-file']];

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('media');
        } catch (Throwable) {
            return false;
        }
    }

    /** Folder on disk where uploads live (created with protection files on first use). */
    public static function root(): string
    {
        $dir = (self::$base ?? AP_BASE) . '/' . self::DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', implode("\n", [
                '# Written by the media library: files here are only ever served, never run.',
                'Options -Indexes',
                '<FilesMatch "\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|asp|aspx|jsp|html?|shtml|js)$">',
                '    <IfModule mod_authz_core.c>',
                '        Require all denied',
                '    </IfModule>',
                '    <IfModule !mod_authz_core.c>',
                '        Order allow,deny',
                '        Deny from all',
                '    </IfModule>',
                '</FilesMatch>',
                '<IfModule mod_headers.c>',
                '    Header set X-Content-Type-Options "nosniff"',
                '    <FilesMatch "\.svg$">',
                '        Header set Content-Security-Policy "default-src \'none\'; style-src \'unsafe-inline\'; img-src data:"',
                '    </FilesMatch>',
                '</IfModule>',
                '',
            ]));
            @file_put_contents($dir . '/index.html', '');
        }
        return $dir;
    }

    public static function url(string $path): string
    {
        return url($path);
    }

    /** Public address of a file or one of its smaller copies ("thumb", "medium"). */
    public static function fileUrl(array $m, string $size = ''): string
    {
        $variants = json_decode((string) ($m['variants'] ?? ''), true) ?: [];
        if ($size !== '' && isset($variants[$size]['path'])) {
            return self::url((string) $variants[$size]['path']);
        }
        return self::url((string) $m['path']);
    }

    /**
     * Keep a checked upload: move it into place, shrink big photos, make small copies, write the database row.
     * @param array<string,mixed> $check the result of Upload::check()
     */
    public static function store(array $check, ?int $folderId = null, bool $moveUploaded = true): array
    {
        $sub = setting('uploads.organise', 'month') === 'month' ? gmdate('Y/m') : 'files';
        $dir = self::root() . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('The uploads folder (assets/uploads) cannot be written. Set its permissions to 755 in your file manager.');
        }
        $base = pathinfo($check['name'], PATHINFO_FILENAME);
        $ext = $check['ext'];
        $name = $base . '.' . $ext;
        $n = 1;
        while (is_file($dir . '/' . $name)) {
            $name = $base . '-' . (++$n) . '.' . $ext;
        }
        $target = $dir . '/' . $name;
        $moved = $moveUploaded ? @move_uploaded_file($check['tmp'], $target) : @copy($check['tmp'], $target);
        if (!$moved) {
            throw new \RuntimeException('The file could not be saved in the uploads folder. Check that assets/uploads can be written.');
        }
        @chmod($target, 0644);
        $rel = self::DIR . '/' . $sub . '/' . $name;
        $width = $check['width'];
        $height = $check['height'];
        $variants = [];
        if ($check['kind'] === 'image' && Image::supports($ext)) {
            $quality = (int) setting('uploads.jpeg_quality', 82);
            if (setting('uploads.process_images', true)) {
                $fit = Image::fit($target, (int) setting('uploads.image_max_width', 2400), $quality);
                if ($fit) {
                    $width = $fit['width'];
                    $height = $fit['height'];
                }
            }
            foreach (['medium' => (int) setting('uploads.medium_width', 1200), 'thumb' => (int) setting('uploads.thumb_width', 400)] as $size => $w) {
                if ($w <= 0) {
                    continue;
                }
                $vName = pathinfo($name, PATHINFO_FILENAME) . '-' . $w . '.' . $ext;
                $made = Image::variant($target, $dir . '/' . $vName, $w, $quality);
                if ($made) {
                    $variants[$size] = ['path' => self::DIR . '/' . $sub . '/' . $vName, 'width' => $made['width'], 'height' => $made['height']];
                }
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $title = ucfirst(trim((string) preg_replace('/[-_]+/', ' ', pathinfo($check['name'], PATHINFO_FILENAME))));
        $row = [
            'folder_id' => $folderId ?: null, 'path' => $rel, 'original_name' => mb_substr((string) $check['name'], 0, 255), 'title' => mb_substr($title, 0, 190),
            'alt' => null, 'caption' => null, 'kind' => (string) $check['kind'], 'mime' => (string) $check['mime'], 'ext' => $ext, 'size' => (int) (filesize($target) ?: $check['size']),
            'width' => $width, 'height' => $height, 'variants' => $variants ? json_encode($variants, JSON_UNESCAPED_SLASHES) : null,
            'uploaded_by' => auth_user()['id'] ?? null, 'created_at' => $now, 'updated_at' => $now,
        ];
        $row['id'] = self::db()->table('media')->insertGetId($row);
        return $row;
    }

    /** Removes the files on disk and the row. */
    public static function delete(array $m): void
    {
        $paths = [(string) $m['path']];
        foreach (json_decode((string) ($m['variants'] ?? ''), true) ?: [] as $v) {
            $paths[] = (string) ($v['path'] ?? '');
        }
        foreach ($paths as $p) {
            if ($p !== '' && str_starts_with($p, self::DIR . '/') && !str_contains($p, '..')) {
                @unlink((self::$base ?? AP_BASE) . '/' . $p);
            }
        }
        self::db()->table('media')->where('id', (int) $m['id'])->delete();
    }

    /** Pages (and other content) that mention a file, so nobody deletes an image a page still shows. @return list<array{title:string,url:string}> */
    public static function usage(array $m): array
    {
        $out = [];
        $needle = '/' . ltrim((string) $m['path'], '/');
        $short = substr((string) $m['path'], strlen('assets/'));
        try {
            if (self::db()->hasTable('pages')) {
                foreach (self::db()->table('pages')->whereNull('deleted_at')->whereRaw('(content LIKE ? OR social_image LIKE ?)', ['%' . $short . '%', '%' . $short . '%'])->limit(20)->get() as $p) {
                    $out[] = ['title' => 'Page: ' . $p['title'], 'url' => admin_url('pages/' . (int) $p['id'] . '/edit')];
                }
            }
        } catch (Throwable) {
            // usage is a hint, never a blocker
        }
        return (array) filter('media.usage', $out, $m, $needle);
    }

    /** @return array<string,mixed> what the admin screens and the picker need about one file */
    public static function present(array $m): array
    {
        $variants = json_decode((string) ($m['variants'] ?? ''), true) ?: [];
        return [
            'id' => (int) $m['id'], 'folder_id' => $m['folder_id'] ? (int) $m['folder_id'] : null, 'title' => (string) ($m['title'] ?? ''), 'alt' => (string) ($m['alt'] ?? ''),
            'caption' => (string) ($m['caption'] ?? ''), 'name' => (string) $m['original_name'], 'kind' => (string) $m['kind'], 'ext' => (string) $m['ext'], 'mime' => (string) $m['mime'],
            'size' => (int) $m['size'], 'size_text' => format_bytes((int) $m['size']), 'width' => $m['width'] ? (int) $m['width'] : null, 'height' => $m['height'] ? (int) $m['height'] : null,
            'url' => self::url((string) $m['path']), 'path' => '/' . ltrim((string) $m['path'], '/'),
            'thumb' => $m['kind'] === 'image' ? self::fileUrl($m, isset($variants['thumb']) ? 'thumb' : '') : null,
            'medium' => isset($variants['medium']) ? self::url((string) $variants['medium']['path']) : null,
            'date' => format_date((string) $m['created_at']), 'icon' => self::KINDS[$m['kind']][1] ?? 'fa-file',
        ];
    }

    /** @return list<array{id:int,name:string,parent_id:?int,count:int,depth:int}> */
    public static function folders(): array
    {
        $rows = self::db()->table('media_folders')->orderBy('name')->get();
        $counts = [];
        foreach (self::db()->table('media')->select('folder_id')->get() as $r) {
            $k = (int) ($r['folder_id'] ?? 0);
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        $by = [];
        foreach ($rows as $r) {
            $by[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $out = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$out, $by, $counts): void {
            foreach ($by[$parent] ?? [] as $r) {
                $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'parent_id' => $r['parent_id'] ? (int) $r['parent_id'] : null, 'count' => $counts[(int) $r['id']] ?? 0, 'depth' => $depth];
                if ($depth < 4) {
                    $walk((int) $r['id'], $depth + 1);
                }
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function countAll(): array
    {
        $all = 0;
        $unsorted = 0;
        foreach (self::db()->table('media')->select('folder_id')->get() as $r) {
            $all++;
            if (!$r['folder_id']) {
                $unsorted++;
            }
        }
        return ['all' => $all, 'unsorted' => $unsorted];
    }

    public static function check(array $file, ?array $only = null): array
    {
        return Upload::check($file, $only);
    }
}
