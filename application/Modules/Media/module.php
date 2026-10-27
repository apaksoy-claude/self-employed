<?php
declare(strict_types=1);

use AP\Core\View;
use AP\Modules\Media\Media;

return [
    'name' => 'media',
    'label' => 'Media library',
    'version' => '1.4.3',
    'description' => 'Images and files for the website: drag-and-drop uploads, folders, automatic resizing, descriptions and a picker for editors.',
    'icon' => 'fa-photo-film',
    'category' => 'Content',
    'when_off' => 'The Media Library and the Choose buttons disappear. Files stay on the server and keep working on pages.',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('media')->count()) ? $n . ' file(s) are kept.' : '',
    'core' => false,
    'default_enabled' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'media.view' => 'See the media library and pick files',
        'media.upload' => 'Upload files',
        'media.edit' => 'Describe, rename and move files, manage folders',
        'media.delete' => 'Delete files',
    ],
    'menu' => [
        ['label' => 'Media library', 'icon' => 'fa-photo-film', 'url' => 'media', 'group' => 'Content', 'order' => 3, 'permission' => 'media.view',
            'info' => 'Images and files used on the website. Drop files anywhere on the page to upload them; big photos are made smaller automatically.', 'keywords' => 'images files uploads photos pictures documents gallery'],
        ['label' => 'Galleries', 'icon' => 'fa-images', 'url' => 'media/galleries', 'group' => 'Content', 'order' => 3.5, 'permission' => 'media.view',
            'info' => 'Albums of pictures from the library, in the order you choose. Show one on its own page, or put it inside any page.', 'keywords' => 'gallery galleries albums slideshow photos portfolio lightbox masonry slider shortcode'],
    ],
    'boot' => static function ($app): void {
        \AP\Modules\Media\Galleries::boot();
    },
    'settings' => [
        'uploads.process_images' => ['group' => 'uploads', 'section' => 'Images', 'label' => 'Shrink big photos and remove camera data', 'type' => 'checkbox', 'default' => true,
            'help' => 'Photos wider than "Largest image width" are made smaller, turned upright, and their location and camera details are removed.', 'order' => 10],
        'uploads.jpeg_quality' => ['group' => 'uploads', 'section' => 'Images', 'width' => 'half', 'label' => 'Photo quality (30 to 100)', 'type' => 'number', 'default' => 82, 'rules' => 'integer|min:30|max:100',
            'help' => '82 looks the same as the original for most photos and is much smaller.', 'order' => 11],
        'uploads.medium_width' => ['group' => 'uploads', 'section' => 'Images', 'width' => 'half', 'label' => 'Medium copy width (px)', 'type' => 'number', 'default' => 1200, 'rules' => 'integer|min:0|max:4000',
            'help' => 'A smaller copy for content columns. 0 makes none.', 'order' => 12],
        'uploads.thumb_width' => ['group' => 'uploads', 'section' => 'Images', 'width' => 'half', 'label' => 'Thumbnail width (px)', 'type' => 'number', 'default' => 400, 'rules' => 'integer|min:0|max:1200',
            'help' => 'Used in the media library and in lists. 0 makes none.', 'order' => 13],
        'uploads.organise' => ['group' => 'uploads', 'section' => 'Files', 'width' => 'half', 'label' => 'Keep files in', 'type' => 'select', 'default' => 'month', 'options' => ['month' => 'A folder per month (2026/10)', 'none' => 'One folder'], 'rules' => 'required|in:month,none',
            'help' => 'Only changes where new uploads go on the server. Folders in the media library are separate.', 'order' => 14],
    ],
    'search' => [
        'media' => ['label' => 'Media', 'icon' => 'fa-photo-film', 'permission' => 'media.view',
            'handler' => static function (string $term, int $limit): array {
                if (!Media::ready()) {
                    return [];
                }
                $rows = Media::db()->table('media')->search(['title', 'original_name', 'alt'], $term)->orderBy('created_at', 'desc')->limit($limit)->get();
                return array_map(static fn($m) => ['title' => (string) ($m['title'] ?: $m['original_name']), 'subtitle' => $m['original_name'] . ' · ' . format_bytes((int) $m['size']),
                    'url' => admin_url('media') . '?open=' . (int) $m['id'], 'icon' => Media::KINDS[$m['kind']][1] ?? 'fa-file', 'score' => 4], $rows);
            }],
    ],
    'widgets' => [
        ['id' => 'media.summary', 'title' => 'Media Library', 'icon' => 'fa-photo-film', 'order' => 25, 'permission' => 'media.view',
            'render' => static function (): string {
                if (!Media::ready()) {
                    return '<p class="small text-muted mb-0">Press "Finish update" to start using the media library.</p>';
                }
                $n = Media::countAll()['all'];
                $size = (int) Media::db()->value('SELECT COALESCE(SUM(size), 0) FROM ' . Media::db()->t('media'));
                $recent = Media::db()->table('media')->where('kind', 'image')->orderBy('id', 'desc')->limit(6)->get();
                $html = '<p class="small mb-2"><strong>' . $n . '</strong> file' . ($n === 1 ? '' : 's') . ' · ' . e(format_bytes($size)) . '</p><div class="ap-media-strip">';
                foreach ($recent as $m) {
                    $html .= '<a href="' . e(admin_url('media') . '?open=' . (int) $m['id']) . '"><img src="' . e(Media::fileUrl($m, 'thumb')) . '" alt="" loading="lazy"></a>';
                }
                return $html . '</div><a class="btn btn-sm btn-outline-primary mt-2" href="' . e(admin_url('media')) . '"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Files</a>';
            }],
    ],
    'boot' => static function ($app): void {
        // The file picker is available on every admin page for people who may see the library.
        View::inject('admin.body_end', static function (): string {
            if (!can('media.view') || !Media::ready()) {
                return '';
            }
            return '<div id="apMediaPicker" hidden data-list-url="' . e(admin_url('media/list')) . '" data-upload-url="' . e(admin_url('media/upload')) . '" data-can-upload="' . (can('media.upload') ? '1' : '0') . '"'
                . ' data-max="' . (int) \AP\Core\Upload::maxBytes() . '" data-allowed="' . e(implode(',', \AP\Core\Upload::allowed())) . '"></div>'
                . '<script src="' . e(asset('js/media.js')) . '"></script>';
        }, 50);
    },
    'actions' => [
        'media.upload' => ['label' => 'Upload files', 'icon' => 'fa-cloud-arrow-up', 'url' => 'media', 'permission' => 'media.upload', 'keywords' => 'add image photo picture document upload', 'info' => 'Media library'],
    ],
];
