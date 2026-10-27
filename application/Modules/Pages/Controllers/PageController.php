<?php
declare(strict_types=1);

namespace AP\Modules\Pages\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;
use AP\Modules\Admin\Ui;
use AP\Modules\Pages\Pages;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class PageController
{
    private const FIELDS = ['title', 'slug', 'parent_id', 'status', 'publish_at', 'template', 'content', 'excerpt', 'seo_title', 'seo_description', 'social_image', 'noindex'];

    private function db(): DB
    {
        return Pages::db();
    }

    private function find(int $id, bool $withTrashed = false): array
    {
        $q = $this->db()->table('pages')->where('id', $id);
        if (!$withTrashed) {
            $q->whereNull('deleted_at');
        }
        return $q->first() ?? throw new HttpException(404);
    }

    public function index(Request $request): Response
    {
        $tab = in_array($request->string('status'), ['published', 'draft', 'scheduled', 'bin'], true) ? $request->string('status') : 'all';
        $sort = Ui::sort($request->string('sort'), ['tree', 'title', 'path', 'status', 'updated_at', 'created_at'], 'tree');
        $dir = $request->string('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = Ui::perPage($request->int('per_page', 50));
        $term = $request->string('q');

        $q = $this->db()->table('pages')->select('pages.*', 'users.name AS editor_name')->join('users', 'pages.updated_by', '=', 'users.id', 'LEFT');
        $tab === 'bin' ? $q->whereNotNull('pages.deleted_at') : $q->whereNull('pages.deleted_at');
        if (in_array($tab, ['published', 'draft', 'scheduled'], true)) {
            $q->where('pages.status', $tab);
        }
        if ($term !== '') {
            $q->search(['pages.title', 'pages.path', 'pages.seo_title'], $term);
        }
        $sort === 'tree' ? $q->orderBy('pages.path', 'asc') : $q->orderBy('pages.' . $sort, $dir);
        $pager = $q->paginate($request->int('page', 1), $perPage);

        $counts = ['all' => 0, 'published' => 0, 'draft' => 0, 'scheduled' => 0, 'bin' => 0];
        foreach ($this->db()->table('pages')->select('status', 'deleted_at')->get() as $r) {
            if ($r['deleted_at']) {
                $counts['bin']++;
                continue;
            }
            $counts['all']++;
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
        }
        return view('pages::index', [
            'title' => 'Pages', 'breadcrumbs' => ['Pages' => null], 'pager' => $pager, 'counts' => $counts, 'tab' => $tab,
            'homeId' => (int) setting('pages.home_id', 0),
            'filters' => ['q' => $term, 'status' => $tab === 'all' ? '' : $tab, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir],
        ]);
    }

    public function create(Request $request): Response
    {
        $page = ['id' => 0, 'title' => '', 'slug' => '', 'path' => '', 'parent_id' => $request->int('parent') ?: null, 'status' => 'draft', 'publish_at' => null,
            'template' => 'default', 'content' => '', 'excerpt' => '', 'seo_title' => '', 'seo_description' => '', 'social_image' => '', 'noindex' => 0,
            'created_at' => null, 'updated_at' => null, 'deleted_at' => null, 'author_id' => null];
        return $this->form($page);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id));
    }

    private function form(array $page): Response
    {
        $id = (int) $page['id'];
        $revisions = $id ? $this->db()->table('page_revisions')->select('id', 'user_name', 'title', 'note', 'created_at')->where('page_id', $id)->orderBy('id', 'desc')->limit(50)->get() : [];
        $author = $id && $page['author_id'] ? $this->db()->table('users')->where('id', (int) $page['author_id'])->value('name') : null;
        return view('pages::form', [
            'title' => $id ? 'Edit Page' : 'Add a Page', 'page' => $page, 'revisions' => $revisions, 'author' => $author,
            'about' => 'Write the page, choose its address and when it goes live. Search engine fields are optional: the title and the start of the text are used when they are empty.',
            'breadcrumbs' => ['Pages' => admin_url('pages'), ($id ? $page['title'] : 'Add a Page') => null],
            'parents' => Pages::parentOptions($id), 'templates' => Pages::templates(), 'canPublish' => can('pages.publish'),
            'isHome' => $id && (int) setting('pages.home_id', 0) === $id, 'publishLocal' => self::toLocal($page['publish_at']),
            'live' => $id && Pages::isLive($page), 'publicUrl' => $id ? Pages::url($page) : '', 'baseUrl' => rtrim(url('/'), '/') . '/',
            'editorMode' => (string) setting('pages.editor_mode', 'visual'), 'timezone' => (string) setting('general.timezone', 'UTC'),
        ]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id): Response
    {
        return $this->save($request, $this->find($id));
    }

    private function save(Request $request, ?array $page): Response
    {
        $id = $page ? (int) $page['id'] : 0;
        $input = [];
        foreach (self::FIELDS as $f) {
            $input[$f] = $request->input($f, '');
        }
        $input['content'] = is_string($input['content']) ? $input['content'] : '';
        $templates = Pages::templates();
        $v = Validator::make($input, [
            'title' => 'required|string|max:190', 'slug' => 'nullable|string|max:190',
            'status' => 'required|in:' . implode(',', array_keys(Pages::STATUSES)),
            'template' => 'required|in:' . implode(',', array_keys($templates)),
            'excerpt' => 'nullable|string|max:500', 'seo_title' => 'nullable|string|max:190', 'seo_description' => 'nullable|string|max:300',
            'social_image' => 'nullable|string|max:500',
        ], ['title' => 'Title', 'slug' => 'Address', 'status' => 'Status', 'template' => 'Layout', 'excerpt' => 'Summary', 'seo_title' => 'Search engine title',
            'seo_description' => 'Search engine description', 'social_image' => 'Sharing image']);
        $errors = $v->fails() ? $v->errors() : [];

        $parentId = (int) $input['parent_id'] ?: null;
        if ($parentId !== null) {
            $parent = $this->db()->table('pages')->where('id', $parentId)->whereNull('deleted_at')->first();
            if (!$parent || ($id && in_array($parentId, Pages::descendants($id), true))) {
                $errors['parent_id'][] = 'Choose another parent page: a page cannot sit under itself or under one of its own sub-pages.';
            }
        }
        $status = (string) $input['status'];
        if (!can('pages.publish')) {
            $status = $page ? (string) $page['status'] : 'draft';
        }
        $publishAt = null;
        if ($status === 'scheduled') {
            $publishAt = self::toUtc((string) $input['publish_at']);
            if ($publishAt === null) {
                $errors['publish_at'][] = 'Choose the date and time the page should go live.';
            }
        } elseif ($status === 'published') {
            $publishAt = $page && $page['status'] === 'published' && $page['publish_at'] ? (string) $page['publish_at'] : Pages::now();
        }
        $image = trim((string) $input['social_image']);
        if ($image !== '' && !\AP\Core\HtmlSanitizer::safeUrl($image, true)) {
            $errors['social_image'][] = 'Use an address starting with https:// or /.';
        }
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('pages'));
        }

        $wanted = Pages::slugify((string) ($input['slug'] !== '' ? $input['slug'] : $input['title']));
        $slug = Pages::uniqueSlug($wanted, $parentId, $id);
        $now = Pages::now();
        $me = (int) (auth_user()['id'] ?? 0) ?: null;
        $data = [
            'title' => trim((string) $input['title']), 'slug' => $slug, 'path' => Pages::buildPath($slug, $parentId), 'parent_id' => $parentId,
            'status' => $status, 'publish_at' => $publishAt, 'template' => (string) $input['template'],
            'content' => Pages::cleanHtml($input['content']), 'excerpt' => trim((string) $input['excerpt']) ?: null,
            'seo_title' => trim((string) $input['seo_title']) ?: null, 'seo_description' => trim((string) $input['seo_description']) ?: null,
            'social_image' => $image ?: null, 'noindex' => $request->bool('noindex') ? 1 : 0, 'updated_by' => $me, 'updated_at' => $now,
        ];

        if ($page === null) {
            $id = $this->db()->table('pages')->insertGetId($data + ['author_id' => $me, 'created_at' => $now, 'sort_order' => 0]);
            Activity::log('pages.create', 'Page "' . $data['title'] . '" added', 'page', $id, null, ['status' => $status, 'path' => $data['path']]);
        } else {
            $changed = false;
            foreach (['title', 'content', 'excerpt', 'seo_title', 'seo_description', 'social_image', 'template', 'slug', 'parent_id', 'noindex'] as $k) {
                if ((string) ($page[$k] ?? '') !== (string) ($data[$k] ?? '')) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                Pages::saveRevision($page);
            }
            $this->db()->table('pages')->where('id', $id)->update($data);
            if ($page['path'] !== $data['path']) {
                Pages::rebuildChildPaths($id);
            }
            Activity::log('pages.update', 'Page "' . $data['title'] . '" saved', 'page', $id,
                ['status' => $page['status'], 'path' => $page['path']], ['status' => $status, 'path' => $data['path']]);
        }
        event('pages.changed', $id);
        if ($slug !== $wanted && $wanted !== '') {
            session()->toast('warning', 'The address "' . $wanted . '" was taken, so the page uses "' . $slug . '".');
        }
        $msg = match ($status) {
            'published' => $page && $page['status'] === 'published' ? 'Page updated.' : 'Page published.',
            'scheduled' => 'Page scheduled for ' . format_date($publishAt) . '.',
            default => 'Draft saved.',
        };
        session()->toast('success', $msg);
        return redirect(admin_url('pages/' . $id . '/edit'));
    }

    /** Live address suggestion while typing: GET pages/slug?title=..&slug=..&parent=..&id=.. */
    public function slug(Request $request): Response
    {
        $parent = $request->int('parent') ?: null;
        $wanted = Pages::slugify($request->string('slug') !== '' ? $request->string('slug') : $request->string('title'));
        $slug = $wanted === '' ? '' : Pages::uniqueSlug($wanted, $parent, $request->int('id'));
        $path = $slug === '' ? '' : Pages::buildPath($slug, $parent);
        return Response::json(['slug' => $slug, 'path' => $path, 'url' => $path === '' ? '' : url('/' . $path), 'changed' => $slug !== $wanted]);
    }

    public function preview(Request $request, int $id): Response
    {
        return PublicController::render($this->find($id), false, true);
    }

    public function duplicate(Request $request, int $id): Response
    {
        $page = $this->find($id);
        $parent = $page['parent_id'] ? (int) $page['parent_id'] : null;
        $slug = Pages::uniqueSlug($page['slug'] . '-copy', $parent);
        $now = Pages::now();
        $copy = array_intersect_key($page, array_flip(['parent_id', 'template', 'content', 'excerpt', 'seo_title', 'seo_description', 'social_image', 'noindex', 'sort_order']));
        $newId = $this->db()->table('pages')->insertGetId($copy + ['title' => $page['title'] . ' (copy)', 'slug' => $slug, 'path' => Pages::buildPath($slug, $parent),
            'status' => 'draft', 'publish_at' => null, 'author_id' => auth_user()['id'] ?? null, 'updated_by' => auth_user()['id'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        Activity::log('pages.duplicate', 'Page "' . $page['title'] . '" copied', 'page', $newId);
        session()->toast('success', 'A copy was made as a draft.');
        return redirect(admin_url('pages/' . $newId . '/edit'));
    }

    public function makeHome(Request $request, int $id): Response
    {
        $page = $this->find($id);
        if (!Pages::isLive($page)) {
            session()->toast('warning', 'Publish the page first, then make it the home page.');
            return back(admin_url('pages'));
        }
        settings()->set('pages.home_id', (string) $id, (int) (auth_user()['id'] ?? 0) ?: null);
        event('pages.changed', $id);
        Activity::log('pages.home', '"' . $page['title'] . '" is now the home page', 'page', $id);
        session()->toast('success', '"' . $page['title'] . '" is now the home page.');
        return back(admin_url('pages'));
    }

    public function trash(Request $request, int $id): Response
    {
        $page = $this->find($id);
        $this->moveToBin([$id]);
        Activity::log('pages.trash', 'Page "' . $page['title'] . '" moved to the bin', 'page', $id);
        session()->toast('success', '"' . $page['title'] . '" was moved to the bin.');
        return redirect(admin_url('pages'));
    }

    public function restore(Request $request, int $id): Response
    {
        $page = $this->find($id, true);
        $this->restoreFromBin([$id]);
        session()->toast('success', '"' . $page['title'] . '" is back (as a draft).');
        return redirect(admin_url('pages/' . $id . '/edit'));
    }

    public function destroy(Request $request, int $id): Response
    {
        $page = $this->find($id, true);
        if (!$page['deleted_at']) {
            session()->toast('warning', 'Move the page to the bin first.');
            return back(admin_url('pages'));
        }
        Pages::destroy($id);
        Activity::log('pages.delete', 'Page "' . $page['title'] . '" deleted for good', 'page', $id);
        session()->toast('success', 'The page was deleted for good.');
        return redirect(admin_url('pages') . '?status=bin');
    }

    public function emptyBin(Request $request): Response
    {
        $ids = $this->db()->table('pages')->whereNotNull('deleted_at')->pluck('id');
        foreach ($ids as $id) {
            Pages::destroy((int) $id);
        }
        Activity::log('pages.empty_bin', count($ids) . ' page(s) deleted from the bin');
        session()->toast('success', count($ids) . ' page(s) deleted for good.');
        return redirect(admin_url('pages'));
    }

    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))));
        $action = $request->string('action');
        if (!$ids) {
            session()->toast('warning', 'Tick at least one page first.');
            return back(admin_url('pages'));
        }
        $now = Pages::now();
        switch ($action) {
            case 'publish':
            case 'draft':
                if (!can('pages.publish')) {
                    throw new HttpException(403);
                }
                $data = ['status' => $action === 'publish' ? 'published' : 'draft', 'updated_at' => $now, 'updated_by' => auth_user()['id'] ?? null];
                if ($action === 'publish') {
                    $this->db()->table('pages')->whereIn('id', $ids)->whereNull('publish_at')->update(['publish_at' => $now]);
                }
                $n = $this->db()->table('pages')->whereIn('id', $ids)->whereNull('deleted_at')->update($data);
                $msg = $n . ' page(s) ' . ($action === 'publish' ? 'published.' : 'changed to drafts.');
                break;
            case 'trash':
                if (!can('pages.delete')) {
                    throw new HttpException(403);
                }
                $n = $this->moveToBin($ids);
                $msg = $n . ' page(s) moved to the bin.';
                break;
            case 'restore':
                if (!can('pages.delete')) {
                    throw new HttpException(403);
                }
                $n = $this->restoreFromBin($ids);
                $msg = $n . ' page(s) restored as drafts.';
                break;
            case 'destroy':
                if (!can('pages.delete')) {
                    throw new HttpException(403);
                }
                $n = 0;
                foreach ($this->db()->table('pages')->whereIn('id', $ids)->whereNotNull('deleted_at')->pluck('id') as $id) {
                    Pages::destroy((int) $id);
                    $n++;
                }
                $msg = $n . ' page(s) deleted for good.';
                break;
            default:
                session()->toast('warning', 'Choose what to do with the ticked pages.');
                return back(admin_url('pages'));
        }
        event('pages.changed', 0);
        Activity::log('pages.bulk', ucfirst($action) . ': ' . count($ids) . ' page(s)', 'page', implode(',', array_slice($ids, 0, 20)));
        session()->toast('success', $msg);
        return back(admin_url('pages'));
    }

    /** JSON for the "View" button of a revision. */
    public function revision(Request $request, int $id, int $rev): Response
    {
        $r = $this->db()->table('page_revisions')->where('id', $rev)->where('page_id', $id)->first() ?? throw new HttpException(404);
        return Response::json(['title' => $r['title'], 'content' => (string) $r['content'], 'when' => format_date((string) $r['created_at']), 'by' => (string) ($r['user_name'] ?? '')]);
    }

    public function restoreRevision(Request $request, int $id, int $rev): Response
    {
        $page = $this->find($id);
        $r = $this->db()->table('page_revisions')->where('id', $rev)->where('page_id', $id)->first() ?? throw new HttpException(404);
        Pages::saveRevision($page, 'Before going back to ' . format_date((string) $r['created_at']));
        $data = json_decode((string) $r['data'], true) ?: [];
        $update = ['title' => $r['title'], 'content' => $r['content'], 'updated_at' => Pages::now(), 'updated_by' => auth_user()['id'] ?? null];
        foreach (['template', 'excerpt', 'seo_title', 'seo_description', 'social_image', 'noindex'] as $k) {
            if (array_key_exists($k, $data)) {
                $update[$k] = $data[$k];
            }
        }
        if (isset($update['template']) && !isset(Pages::templates()[$update['template']])) {
            unset($update['template']);
        }
        $this->db()->table('pages')->where('id', $id)->update($update);
        event('pages.changed', $id);
        Activity::log('pages.revision', 'Page "' . $page['title'] . '" went back to the version of ' . format_date((string) $r['created_at']), 'page', $id);
        session()->toast('success', 'The page went back to the version of ' . format_date((string) $r['created_at']) . '. The version you replaced is kept as a revision.');
        return redirect(admin_url('pages/' . $id . '/edit'));
    }

    /** @param list<int> $ids */
    private function moveToBin(array $ids): int
    {
        $n = $this->db()->table('pages')->whereIn('id', $ids)->whereNull('deleted_at')->update(['deleted_at' => Pages::now()]);
        // Sub-pages move up to the top level so they keep working.
        foreach ($ids as $id) {
            foreach ($this->db()->table('pages')->where('parent_id', $id)->whereNull('deleted_at')->get() as $child) {
                $slug = Pages::uniqueSlug((string) $child['slug'], null, (int) $child['id']);
                $this->db()->table('pages')->where('id', (int) $child['id'])->update(['parent_id' => null, 'slug' => $slug, 'path' => $slug]);
                Pages::rebuildChildPaths((int) $child['id']);
            }
        }
        event('pages.changed', 0);
        if (in_array((int) setting('pages.home_id', 0), $ids, true)) {
            settings()->set('pages.home_id', '0');
            session()->toast('info', 'The home page went to the bin, so the built-in welcome page is shown again.');
        }
        return $n;
    }

    /** @param list<int> $ids */
    private function restoreFromBin(array $ids): int
    {
        $n = 0;
        foreach ($this->db()->table('pages')->whereIn('id', $ids)->whereNotNull('deleted_at')->get() as $p) {
            $parent = $p['parent_id'] && $this->db()->table('pages')->where('id', (int) $p['parent_id'])->whereNull('deleted_at')->exists() ? (int) $p['parent_id'] : null;
            $slug = Pages::uniqueSlug((string) $p['slug'], $parent, (int) $p['id']);
            $this->db()->table('pages')->where('id', (int) $p['id'])->update(['deleted_at' => null, 'status' => 'draft', 'parent_id' => $parent, 'slug' => $slug,
                'path' => Pages::buildPath($slug, $parent), 'updated_at' => Pages::now()]);
            $n++;
        }
        event('pages.changed', 0);
        return $n;
    }

    /** "2026-10-23T14:30" in the site's time zone → UTC "2026-10-23 13:30:00". */
    public static function toUtc(string $local): ?string
    {
        $local = trim($local);
        if ($local === '' || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $local)) {
            return null;
        }
        try {
            $dt = new DateTimeImmutable(str_replace('T', ' ', $local), new DateTimeZone((string) setting('general.timezone', 'UTC') ?: 'UTC'));
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    public static function toLocal(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) setting('general.timezone', 'UTC') ?: 'UTC'))->format('Y-m-d\TH:i');
        } catch (Throwable) {
            return '';
        }
    }
}
