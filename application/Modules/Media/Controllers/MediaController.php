<?php
declare(strict_types=1);

namespace AP\Modules\Media\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Upload;
use AP\Modules\Media\Media;
use Throwable;

final class MediaController
{
    private function find(int $id): array
    {
        return Media::db()->table('media')->where('id', $id)->first() ?? throw new HttpException(404);
    }

    private function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    public function index(Request $request): Response
    {
        return view('media::index', [
            'title' => 'Media Library', 'breadcrumbs' => ['Media Library' => null],
            'about' => 'Images and files used on the website. Drop files anywhere on this page to upload them. Click a file to describe it, copy its address or delete it.',
            'folders' => Media::folders(), 'counts' => Media::countAll(), 'kinds' => Media::KINDS, 'open' => $request->int('open'),
            'max' => Upload::maxBytes(), 'allowed' => Upload::allowed(),
            'galleries' => \AP\Modules\Media\Galleries::ready() ? Media::db()->table('media_galleries')->orderBy('name')->pluck('name', 'id') : [],
        ]);
    }

    /** JSON list for the library and the picker: ?q=&kind=&folder=(all|0|id)&sort=&page= */
    public function list(Request $request): Response
    {
        $q = Media::db()->table('media');
        $term = $request->string('q');
        if ($term !== '') {
            $q->search(['title', 'original_name', 'alt', 'caption'], $term);
        }
        $kind = $request->string('kind');
        if (isset(Media::KINDS[$kind])) {
            $q->where('kind', $kind);
        }
        $folder = $request->string('folder', 'all');
        if ($folder === '0') {
            $q->whereNull('folder_id');
        } elseif (ctype_digit($folder)) {
            $q->where('folder_id', (int) $folder);
        }
        [$col, $dir] = match ($request->string('sort')) {
            'oldest' => ['id', 'asc'], 'name' => ['title', 'asc'], 'size' => ['size', 'desc'], default => ['id', 'desc'],
        };
        $q->orderBy($col, $dir);
        $per = min(120, max(12, $request->int('per_page', 60)));
        $pager = $q->paginate(max(1, $request->int('page', 1)), $per);
        return $this->json([
            'items' => array_map([Media::class, 'present'], $pager['data']), 'page' => $pager['page'], 'last_page' => $pager['last_page'], 'total' => (int) $pager['total'],
            'folders' => Media::folders(), 'counts' => Media::countAll(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $m = $this->find($id);
        return $this->json(['item' => Media::present($m), 'usage' => Media::usage($m)]);
    }

    /** One file per request, so each file gets its own progress bar and its own error. */
    public function upload(Request $request): Response
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || is_array($file['name'] ?? null)) {
            return $this->json(['ok' => false, 'error' => 'No file arrived. If it was big, it may be over the server limit of ' . format_bytes(Upload::maxBytes()) . '.'], 422);
        }
        $only = $request->string('only') === 'image' ? Upload::IMAGES : null;
        $check = Upload::check($file, $only);
        if (!$check['ok']) {
            return $this->json(['ok' => false, 'error' => $check['error'], 'name' => $check['name']], 422);
        }
        $folder = $request->int('folder') ?: null;
        if ($folder && !Media::db()->table('media_folders')->where('id', $folder)->exists()) {
            $folder = null;
        }
        try {
            $row = Media::store($check, $folder);
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e, $request);
            return $this->json(['ok' => false, 'error' => $e->getMessage(), 'name' => $check['name']], 500);
        }
        Activity::log('media.upload', 'Uploaded "' . $row['original_name'] . '"', 'media', (int) $row['id']);
        return $this->json(['ok' => true, 'item' => Media::present($row)]);
    }

    public function update(Request $request, int $id): Response
    {
        $m = $this->find($id);
        $data = [];
        foreach (['title' => 190, 'alt' => 300, 'caption' => 500] as $f => $max) {
            if ($request->has($f)) {
                $data[$f] = mb_substr(trim($request->string($f)), 0, $max) ?: null;
            }
        }
        if ($request->has('folder_id')) {
            $fid = $request->int('folder_id') ?: null;
            $data['folder_id'] = $fid && Media::db()->table('media_folders')->where('id', $fid)->exists() ? $fid : null;
        }
        if ($data) {
            $data['updated_at'] = gmdate('Y-m-d H:i:s');
            Media::db()->table('media')->where('id', $id)->update($data);
        }
        return $this->json(['ok' => true, 'message' => 'Saved', 'item' => Media::present($this->find($id))]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $m = $this->find($id);
        Media::delete($m);
        Activity::log('media.delete', 'Deleted "' . $m['original_name'] . '"', 'media', $id);
        return $this->json(['ok' => true, 'message' => 'File deleted']);
    }

    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))));
        if (!$ids) {
            return $this->json(['ok' => false, 'message' => 'Select some files first.'], 422);
        }
        $action = $request->string('action');
        if ($action === 'move') {
            if (!can('media.edit')) {
                throw new HttpException(403);
            }
            $fid = $request->int('folder_id') ?: null;
            if ($fid && !Media::db()->table('media_folders')->where('id', $fid)->exists()) {
                return $this->json(['ok' => false, 'message' => 'That folder no longer exists.'], 422);
            }
            $n = Media::db()->table('media')->whereIn('id', $ids)->update(['folder_id' => $fid, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            return $this->json(['ok' => true, 'message' => $n . ' file(s) moved']);
        }
        if ($action === 'delete') {
            if (!can('media.delete')) {
                throw new HttpException(403);
            }
            $n = 0;
            foreach (Media::db()->table('media')->whereIn('id', $ids)->get() as $m) {
                Media::delete($m);
                $n++;
            }
            Activity::log('media.delete', $n . ' file(s) deleted', 'media', implode(',', array_slice($ids, 0, 20)));
            return $this->json(['ok' => true, 'message' => $n . ' file(s) deleted']);
        }
        return $this->json(['ok' => false, 'message' => 'Unknown action.'], 422);
    }

    /** The selected files in one zip, with their original names. */
    public function zip(Request $request): Response
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? [])))), 0, 300);
        if (!$ids || !class_exists(\ZipArchive::class)) {
            session()->toast('warning', $ids ? 'This server cannot make zip files.' : 'Select some files first.');
            return redirect(admin_url('media'));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'apz');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            session()->toast('danger', 'The zip file could not be made.');
            return redirect(admin_url('media'));
        }
        $used = [];
        $total = 0;
        foreach (Media::db()->table('media')->whereIn('id', $ids)->get() as $m) {
            $p = (string) $m['path'];
            $file = (Media::$base ?? AP_BASE) . '/' . $p;
            if (!str_starts_with($p, Media::DIR . '/') || str_contains($p, '..') || !is_file($file)) {
                continue;
            }
            $total += (int) filesize($file);
            if ($total > 400 * 1048576) {
                break; // keep the download a sensible size
            }
            $name = preg_replace('/[^\w. -]+/u', '_', (string) $m['original_name']) ?: 'file';
            $n = 1;
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            while (isset($used[strtolower($name)])) {
                $name = $base . '-' . (++$n) . ($ext !== '' ? '.' . $ext : '');
            }
            $used[strtolower($name)] = true;
            $zip->addFile($file, $name);
        }
        $zip->close();
        $body = (string) file_get_contents($tmp);
        @unlink($tmp);
        Activity::log('media.zip', 'Downloaded ' . count($used) . ' file(s) as a zip', 'media', implode(',', array_slice($ids, 0, 20)));
        return Response::download($body, 'media-' . gmdate('Y-m-d') . '.zip', 'application/zip');
    }

    public function folderStore(Request $request): Response
    {
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            return $this->json(['ok' => false, 'message' => 'Give the folder a name of up to 120 characters.'], 422);
        }
        $parent = $request->int('parent_id') ?: null;
        if ($parent && !Media::db()->table('media_folders')->where('id', $parent)->exists()) {
            $parent = null;
        }
        $id = Media::db()->table('media_folders')->insertGetId(['name' => $name, 'parent_id' => $parent, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return $this->json(['ok' => true, 'message' => 'Folder created', 'id' => $id, 'folders' => Media::folders()]);
    }

    public function folderUpdate(Request $request, int $id): Response
    {
        Media::db()->table('media_folders')->where('id', $id)->first() ?? throw new HttpException(404);
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            return $this->json(['ok' => false, 'message' => 'Give the folder a name of up to 120 characters.'], 422);
        }
        Media::db()->table('media_folders')->where('id', $id)->update(['name' => $name]);
        return $this->json(['ok' => true, 'message' => 'Folder renamed', 'folders' => Media::folders()]);
    }

    /** Files and sub-folders move up to the folder above, nothing is deleted. */
    public function folderDestroy(Request $request, int $id): Response
    {
        $f = Media::db()->table('media_folders')->where('id', $id)->first() ?? throw new HttpException(404);
        $parent = $f['parent_id'] ? (int) $f['parent_id'] : null;
        Media::db()->table('media')->where('folder_id', $id)->update(['folder_id' => $parent]);
        Media::db()->table('media_folders')->where('parent_id', $id)->update(['parent_id' => $parent]);
        Media::db()->table('media_folders')->where('id', $id)->delete();
        return $this->json(['ok' => true, 'message' => 'Folder removed; its files moved up', 'folders' => Media::folders()]);
    }
}
