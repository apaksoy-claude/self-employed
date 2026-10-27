<?php
declare(strict_types=1);

namespace AP\Modules\Media\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Media\Galleries;
use AP\Modules\Media\Media;

/** Galleries in the admin: list, make and arrange, delete, and the same on many at once. */
final class GalleryController
{
    private function find(int $id): array
    {
        return Media::db()->table('media_galleries')->where('id', $id)->first() ?? throw new HttpException(404);
    }

    public function index(Request $request): Response
    {
        $term = trim($request->string('q'));
        $q = Media::db()->table('media_galleries');
        if ($term !== '') {
            $q->search(['name', 'slug', 'description'], $term);
        }
        $status = in_array($request->string('status'), ['published', 'draft'], true) ? $request->string('status') : '';
        if ($status !== '') {
            $q->where('status', $status);
        }
        $rows = $q->orderBy('name')->get();
        $covers = [];
        foreach ($rows as $g) {
            $m = Galleries::cover($g);
            $covers[(int) $g['id']] = $m ? Media::fileUrl($m, 'thumb') : '';
        }
        return view('media::galleries', ['title' => 'Galleries', 'breadcrumbs' => ['Media Library' => admin_url('media'), 'Galleries' => null], 'rows' => $rows, 'covers' => $covers, 'counts' => Galleries::counts(),
            'q' => $term, 'status' => $status, 'canEdit' => can('media.edit'), 'canDelete' => can('media.delete')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        $g = (int) $id > 0 ? $this->find((int) $id) : null;
        $items = [];
        foreach ($g ? Galleries::items((int) $g['id']) : [] as $it) {
            $items[] = ['id' => (int) $it['media']['id'], 'caption' => $it['caption']] + ['thumb' => Media::fileUrl($it['media'], 'thumb'), 'name' => (string) $it['media']['original_name']];
        }
        return view('media::gallery_form', ['title' => $g ? 'Change Gallery' : 'New Gallery', 'breadcrumbs' => ['Media Library' => admin_url('media'), 'Galleries' => admin_url('media/galleries'), ($g ? 'Change' : 'New') => null],
            'g' => $g, 'items' => $items, 'canUpload' => can('media.upload'), 'base' => admin_url('media')]);
    }

    public function save(Request $request): Response
    {
        $db = Media::db();
        $id = $request->int('id');
        $name = trim($request->string('name'));
        if ($name === '') {
            return back_with_errors(['name' => ['Give the gallery a name.']], $request->post, admin_url('media/galleries/' . ($id ?: 'new')));
        }
        $old = $id > 0 ? $this->find($id) : null;
        $slugIn = trim($request->string('slug'));
        $data = [
            'name' => mb_substr($name, 0, 150), 'slug' => Galleries::freeSlug($slugIn !== '' ? $slugIn : ($old['slug'] ?? $name), $id),
            'description' => trim($request->string('description')) ?: null,
            'layout' => isset(Galleries::LAYOUTS[$request->string('layout')]) ? $request->string('layout') : 'grid',
            'columns' => max(1, min(8, $request->int('columns', 3))), 'gap' => max(0, min(40, $request->int('gap', 8))),
            'ratio' => isset(Galleries::RATIOS[$request->string('ratio')]) ? $request->string('ratio') : 'square',
            'lightbox' => $request->bool('lightbox') ? 1 : 0, 'captions' => $request->bool('captions') ? 1 : 0,
            'status' => $request->string('status') === 'published' ? 'published' : 'draft', 'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $items = json_decode($request->string('items'), true);
        $items = is_array($items) ? $items : [];
        $ids = array_values(array_unique(array_filter(array_map(static fn($i) => (int) ($i['id'] ?? 0), $items))));
        $valid = $ids ? array_map('intval', array_column($db->table('media')->whereIn('id', $ids)->where('kind', 'image')->select('id')->get(), 'id')) : [];
        $cover = $request->int('cover_id');
        $data['cover_id'] = in_array($cover, $valid, true) ? $cover : null;
        if ($id > 0) {
            $db->table('media_galleries')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $data['updated_at'];
            $id = (int) $db->table('media_galleries')->insertGetId($data);
        }
        $db->table('media_gallery_items')->where('gallery_id', $id)->delete();
        $n = 0;
        $seen = [];
        foreach ($items as $i) {
            $mid = (int) ($i['id'] ?? 0);
            if (!in_array($mid, $valid, true) || isset($seen[$mid])) {
                continue;
            }
            $seen[$mid] = true;
            $db->table('media_gallery_items')->insert(['gallery_id' => $id, 'media_id' => $mid, 'caption' => mb_substr(trim((string) ($i['caption'] ?? '')), 0, 300) ?: null, 'sort_order' => ++$n]);
        }
        Activity::log('media.gallery.save', 'Saved the gallery "' . $data['name'] . '"', 'media_gallery', $id);
        session()->toast('success', 'Gallery saved.');
        return redirect($request->string('after') === 'stay' ? admin_url('media/galleries/' . $id) : admin_url('media/galleries'));
    }

    public function delete(Request $request, string $id): Response
    {
        $g = $this->find((int) $id);
        $this->remove([(int) $id]);
        Activity::log('media.gallery.delete', 'Deleted the gallery "' . $g['name'] . '"', 'media_gallery', (int) $id);
        session()->toast('success', 'Gallery deleted. Its pictures stay in the library.');
        return redirect(admin_url('media/galleries'));
    }

    /** Publish, unpublish or delete several galleries at once. */
    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))));
        if (!$ids) {
            session()->toast('warning', 'Tick some galleries first.');
            return redirect(admin_url('media/galleries'));
        }
        $action = $request->string('action');
        $db = Media::db();
        if ($action === 'publish' || $action === 'draft') {
            $n = $db->table('media_galleries')->whereIn('id', $ids)->update(['status' => $action === 'publish' ? 'published' : 'draft', 'updated_at' => gmdate('Y-m-d H:i:s')]);
            session()->toast('success', $n . ($action === 'publish' ? ' gallery(ies) published.' : ' gallery(ies) set to draft.'));
        } elseif ($action === 'delete' && can('media.delete')) {
            $this->remove($ids);
            Activity::log('media.gallery.delete', count($ids) . ' gallery(ies) deleted', 'media_gallery', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', count($ids) . ' gallery(ies) deleted. Their pictures stay in the library.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        return redirect(admin_url('media/galleries'));
    }

    /** @param list<int> $ids */
    private function remove(array $ids): void
    {
        Media::db()->table('media_gallery_items')->whereIn('gallery_id', $ids)->delete();
        Media::db()->table('media_galleries')->whereIn('id', $ids)->delete();
    }

    /** From the library: put the selected pictures into a gallery (a new one when "new" is given). */
    public function addTo(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))));
        if (!$ids) {
            return Response::json(['ok' => false, 'message' => 'Select some pictures first.'], 422);
        }
        $target = $request->string('gallery');
        if ($target === 'new') {
            $name = trim($request->string('name')) ?: 'New gallery ' . gmdate('j M Y');
            $now = gmdate('Y-m-d H:i:s');
            $gid = (int) Media::db()->table('media_galleries')->insertGetId(['name' => mb_substr($name, 0, 150), 'slug' => Galleries::freeSlug($name), 'layout' => 'grid', 'columns' => 3, 'gap' => 8, 'ratio' => 'square',
                'lightbox' => 1, 'captions' => 1, 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
        } else {
            $gid = (int) $this->find((int) $target)['id'];
        }
        $n = Galleries::add($gid, $ids);
        return Response::json(['ok' => true, 'message' => $n . ' picture(s) added to the gallery' . ($n < count($ids) ? ' (the rest were already in it or are not pictures)' : ''), 'edit' => admin_url('media/galleries/' . $gid)]);
    }
}
