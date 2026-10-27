<?php
declare(strict_types=1);

namespace AP\Modules\Pages\Controllers;

use AP\Core\HtmlSanitizer;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Pages\Pages;

/** Shows content pages to visitors. */
final class PublicController
{
    public function show(Request $request, string $path): Response
    {
        if (str_starts_with(trim($path, '/'), 'pay/')) {
            throw new HttpException(404); // an old or mistyped payment link says so, instead of a blank page
        }
        if (\AP\Modules\Site\Site::blank()) {
            return \AP\Modules\Site\Site::blankResponse();
        }
        if (!Pages::ready()) {
            throw new HttpException(404);
        }
        $path = trim(mb_strtolower($path), '/');
        $page = Pages::db()->table('pages')->where('path', $path)->whereNull('deleted_at')->first();
        if (!$page || !Pages::isLive($page)) {
            throw new HttpException(404);
        }
        // The home page lives at the site address only, so search engines see one copy.
        if ((int) setting('pages.home_id', 0) === (int) $page['id']) {
            return redirect(url('/'), 301);
        }
        return self::render($page, false);
    }

    public static function render(array $page, bool $isHome, bool $preview = false): Response
    {
        $site = (string) setting('general.site_name', 'My Website');
        $title = (string) ($page['seo_title'] ?: strtr((string) setting('pages.title_format', '{title} | {site}'), ['{title}' => $page['title'], '{site}' => $site]));
        if ($isHome && !$page['seo_title']) {
            $title = $site . ((string) setting('general.tagline', '') !== '' ? ' | ' . setting('general.tagline') : '');
        }
        $description = (string) ($page['seo_description'] ?: ($page['excerpt'] ?: HtmlSanitizer::text((string) preg_replace('/\[[a-z][a-z0-9_-]*[^\]]*\]/i', '', (string) $page['content']), 160)));
        $canonical = $isHome ? url('/') : url('/' . $page['path']);
        $image = (string) ($page['social_image'] ?? '');
        if ($image !== '' && str_starts_with($image, '/')) {
            $image = url($image);
        }
        $crumbs = [];
        if (!$isHome && $page['parent_id'] && setting('pages.show_breadcrumbs', true)) {
            $parentId = (int) $page['parent_id'];
            $guard = 0;
            while ($parentId && $guard++ < 10) {
                $p = Pages::db()->table('pages')->where('id', $parentId)->whereNull('deleted_at')->first();
                if (!$p) {
                    break;
                }
                array_unshift($crumbs, ['title' => $p['title'], 'url' => Pages::isLive($p) ? Pages::url($p) : null]);
                $parentId = (int) ($p['parent_id'] ?? 0);
            }
        }
        // Modules turn short codes such as [contact-form] into what they stand for.
        $page['content'] = (string) filter('pages.content', (string) $page['content'], $page);
        $response = view('pages::public.page', [
            'page' => $page, 'title' => $title, 'isHome' => $isHome, 'preview' => $preview, 'crumbs' => $crumbs,
            'meta' => ['description' => $description, 'canonical' => $canonical, 'robots' => ($preview || (int) $page['noindex']) ? 'noindex,nofollow' : '',
                'og_title' => (string) ($page['seo_title'] ?: $page['title']), 'og_image' => $image, 'og_type' => $isHome ? 'website' : 'article'],
        ]);
        if ($preview) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}
