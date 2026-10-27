<?php
declare(strict_types=1);

namespace AP\Modules\Languages\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Translator;
use AP\Modules\Languages\Locales;

final class TranslationController
{
    private const PER_PAGE = 40;

    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function translator(): Translator
    {
        /** @var Translator $t */
        $t = app(Translator::class);
        return $t;
    }

    private function language(string $code): array
    {
        return $this->db()->table('languages')->where('code', $code)->first() ?? throw new HttpException(404);
    }

    public function index(Request $request, string $code): Response
    {
        $lang = $this->language($code);
        $t = $this->translator();
        $namespaces = array_keys($t->namespaces());
        sort($namespaces);
        $ns = in_array($request->string('ns'), $namespaces, true) ? $request->string('ns') : $namespaces[0];
        $filter = in_array($request->string('filter'), ['all', 'missing', 'edited'], true) ? $request->string('filter') : 'all';
        $term = mb_strtolower(trim($request->string('q')));

        $en = $t->fileLines($ns, 'en');
        $file = $code === 'en' ? $en : $t->fileLines($ns, $code);
        $custom = Locales::overrides($ns, $code);
        // English reference shown beside the box is always the original file wording.
        $rows = [];
        foreach ($en as $key => $source) {
            $current = $custom[$key] ?? ($file[$key] ?? '');
            $state = isset($custom[$key]) ? 'edited' : (($file[$key] ?? '') !== '' ? 'file' : 'missing');
            if ($filter === 'missing' && $state !== 'missing') {
                continue;
            }
            if ($filter === 'edited' && $state !== 'edited') {
                continue;
            }
            if ($term !== '' && !str_contains(mb_strtolower($key . ' ' . $source . ' ' . $current), $term)) {
                continue;
            }
            $rows[] = ['key' => (string) $key, 'source' => (string) $source, 'value' => (string) $current, 'state' => $state, 'file' => (string) ($file[$key] ?? '')];
        }
        $total = count($rows);
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($last, $request->int('page', 1)));
        $summary = [];
        foreach ($namespaces as $n) {
            $nEn = $t->fileLines($n, 'en');
            $nHave = $code === 'en' ? $nEn : $t->fileLines($n, $code) + Locales::overrides($n, $code);
            $missing = 0;
            foreach ($nEn as $k => $_) {
                if (($nHave[$k] ?? '') === '') {
                    $missing++;
                }
            }
            $summary[$n] = ['total' => count($nEn), 'missing' => $missing];
        }
        return view('languages::translations', [
            'title' => 'Translate: ' . $lang['name'], 'breadcrumbs' => ['Languages' => admin_url('languages'), $lang['name'] => null],
            'l' => $lang, 'ns' => $ns, 'summary' => $summary, 'rows' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'pager' => ['page' => $page, 'last_page' => $last, 'total' => $total], 'filter' => $filter, 'q' => $request->string('q'),
            'progress' => Locales::progress($t, $code),
        ]);
    }

    public function save(Request $request, string $code): Response
    {
        $this->language($code);
        $t = $this->translator();
        $ns = $request->string('ns');
        if (!isset($t->namespaces()[$ns])) {
            throw new HttpException(404);
        }
        $en = $t->fileLines($ns, 'en');
        $file = $code === 'en' ? $en : $t->fileLines($ns, $code);
        $values = (array) ($request->post['t'] ?? []);
        $keys = array_map('strval', (array) ($request->post['keys'] ?? []));
        $saved = 0;
        $removed = 0;
        $now = gmdate('Y-m-d H:i:s');
        foreach ($keys as $key) {
            if (!array_key_exists($key, $en)) {
                continue;
            }
            $value = str_replace("\r\n", "\n", (string) ($values[$key] ?? ''));
            $q = $this->db()->table('translations')->where('ns', $ns)->where('locale', $code)->where('tkey', $key);
            // Empty, or the same as the file, means "use the file wording".
            if (trim($value) === '' || $value === (string) ($file[$key] ?? '')) {
                $removed += $q->delete();
                continue;
            }
            if ($q->exists()) {
                (clone $q)->update(['value' => $value, 'updated_by' => auth_user()['id'] ?? null, 'updated_at' => $now]);
            } else {
                $this->db()->table('translations')->insert(['ns' => $ns, 'locale' => $code, 'tkey' => $key, 'value' => $value, 'updated_by' => auth_user()['id'] ?? null, 'updated_at' => $now]);
            }
            $saved++;
        }
        Locales::flush();
        Activity::log('languages.translate', "Edited translations ({$code}, {$ns}): {$saved} saved, {$removed} reverted", 'language', $code);
        session()->toast('success', "Saved. {$saved} changed" . ($removed ? ", {$removed} back to the original wording" : '') . '.');
        $back = admin_url("languages/{$code}/translations") . '?' . http_build_query(array_filter(['ns' => $ns, 'filter' => $request->string('filter'), 'q' => $request->string('q'), 'page' => $request->int('page', 1)]));
        return redirect($back);
    }

    public function export(Request $request, string $code): Response
    {
        $lang = $this->language($code);
        $t = $this->translator();
        $out = [];
        foreach (array_keys($t->namespaces()) as $ns) {
            $en = $t->fileLines($ns, 'en');
            $have = ($code === 'en' ? $en : $t->fileLines($ns, $code)) ;
            $have = Locales::overrides($ns, $code) + $have;
            $rows = array_intersect_key($have, $en);
            if ($rows) {
                $out[$ns] = $rows;
            }
        }
        $json = json_encode(['format' => 'ap-language-pack', 'version' => 1, 'code' => $code, 'name' => $lang['name'], 'native_name' => $lang['native_name'], 'direction' => $lang['direction'], 'exported' => gmdate('c'), 'strings' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return Response::download((string) $json, "language-{$code}.json", 'application/json');
    }

    public function import(Request $request, string $code): Response
    {
        $this->language($code);
        $file = $request->files['pack'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            session()->toast('danger', 'Choose a language pack file (.json, up to 5 MB).');
            return back(admin_url("languages/{$code}/translations"));
        }
        $data = json_decode((string) file_get_contents((string) $file['tmp_name']), true);
        if (!is_array($data) || ($data['format'] ?? '') !== 'ap-language-pack' || !is_array($data['strings'] ?? null)) {
            session()->toast('danger', 'That file is not a language pack exported from this system.');
            return back(admin_url("languages/{$code}/translations"));
        }
        $t = $this->translator();
        $overwrite = $request->bool('overwrite');
        $added = 0;
        $skipped = 0;
        $now = gmdate('Y-m-d H:i:s');
        foreach ($data['strings'] as $ns => $lines) {
            if (!isset($t->namespaces()[$ns]) || !is_array($lines)) {
                continue;
            }
            $en = $t->fileLines((string) $ns, 'en');
            $file = $code === 'en' ? $en : $t->fileLines((string) $ns, $code);
            $existing = Locales::overrides((string) $ns, $code);
            foreach ($lines as $key => $value) {
                if (!is_string($value) || $value === '' || !array_key_exists((string) $key, $en) || $value === (string) ($file[$key] ?? '')) {
                    continue;
                }
                if (isset($existing[$key]) && !$overwrite) {
                    $skipped++;
                    continue;
                }
                $row = ['value' => $value, 'updated_by' => auth_user()['id'] ?? null, 'updated_at' => $now];
                $q = $this->db()->table('translations')->where('ns', (string) $ns)->where('locale', $code)->where('tkey', (string) $key);
                if ($q->exists()) {
                    $q->update($row);
                } else {
                    $this->db()->table('translations')->insert($row + ['ns' => (string) $ns, 'locale' => $code, 'tkey' => (string) $key]);
                }
                $added++;
            }
        }
        Locales::flush();
        Activity::log('languages.import', "Imported a language pack into {$code}: {$added} strings", 'language', $code);
        session()->toast('success', "{$added} string(s) imported" . ($skipped ? ", {$skipped} skipped because they were already edited (tick \"Replace\" to overwrite)" : '') . '.');
        return redirect(admin_url("languages/{$code}/translations"));
    }

    public function reset(Request $request, string $code): Response
    {
        $this->language($code);
        $n = $this->db()->table('translations')->where('locale', $code)->delete();
        Locales::flush();
        Activity::log('languages.reset', "Removed {$n} edited translations from {$code}", 'language', $code);
        session()->toast('success', "{$n} edited translation(s) removed. The original wording is back.");
        return redirect(admin_url("languages/{$code}/translations"));
    }
}
