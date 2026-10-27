<?php
declare(strict_types=1);

namespace AP\Modules\Languages\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Translator;
use AP\Core\Validator;
use AP\Modules\Languages\Catalogue;
use AP\Modules\Languages\Locales;

final class LanguageController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function row(string $code): array
    {
        return $this->db()->table('languages')->where('code', $code)->first() ?? throw new HttpException(404);
    }

    public function index(Request $request): Response
    {
        /** @var Translator $t */
        $t = app(Translator::class);
        $rows = array_values(Locales::all());
        $progress = [];
        foreach ($rows as $r) {
            $progress[$r['code']] = Locales::progress($t, $r['code']);
        }
        $edited = [];
        foreach ($this->db()->table('translations')->groupBy('locale')->select('locale', 'COUNT(*) AS n')->get() as $e) {
            $edited[$e['locale']] = (int) $e['n'];
        }
        $have = array_column($rows, 'code');
        $available = [];
        foreach (Catalogue::LANGUAGES as $code => [$name, $native]) {
            if (!in_array($code, $have, true)) {
                $available[$code] = $name . ($native !== $name ? " ({$native})" : '');
            }
        }
        return view('languages::index', [
            'title' => 'Languages', 'breadcrumbs' => ['Languages' => null],
            'languages' => $rows, 'progress' => $progress, 'edited' => $edited, 'available' => $available, 'default' => Locales::defaultCode(),
            'about' => 'The languages of your website. Switch them on when they are ready, drag them into the order of the switcher, and translate any wording.',
            'sections' => settings()->sections('languages'), 'canEdit' => can('settings.edit'),
        ]);
    }

    public function store(Request $request): Response
    {
        $custom = $request->string('mode') === 'custom';
        if ($custom) {
            $v = Validator::make($request->post, ['code' => 'required|string|max:10', 'name' => 'required|string|max:80', 'native_name' => 'required|string|max:80'], ['code' => 'Code', 'name' => 'Name', 'native_name' => 'Name in its own language']);
            $code = $request->string('code');
            $errors = $v->errors();
            if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)) {
                $errors['code'] = ['Use a code like fr, pt or pt-BR (letters only, lower case first part).'];
            }
            $name = $request->string('name');
            $native = $request->string('native_name');
            $rtl = $request->string('direction') === 'rtl';
        } else {
            $code = $request->string('catalogue');
            $errors = isset(Catalogue::LANGUAGES[$code]) ? [] : ['catalogue' => ['Choose a language from the list.']];
            [$name, $native, $rtl] = Catalogue::LANGUAGES[$code] ?? ['', '', false];
        }
        if (!$errors && $this->db()->table('languages')->where('code', $code)->exists()) {
            $errors[$custom ? 'code' : 'catalogue'] = ['That language has already been added.'];
        }
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('languages'));
        }
        $this->db()->table('languages')->insert([
            'code' => $code, 'name' => $name, 'native_name' => $native, 'direction' => $rtl ? 'rtl' : 'ltr', 'enabled' => 0,
            'is_default' => 0, 'sort_order' => (int) $this->db()->table('languages')->count() * 10, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Locales::flush();
        Activity::log('languages.create', "Added language {$name} ({$code})", 'language', $code);
        session()->toast('success', "{$name} added. Translate it, then switch it on.");
        return redirect(admin_url("languages/{$code}/translations"));
    }

    public function edit(Request $request, string $code): Response
    {
        $row = $this->row($code);
        return view('languages::edit', ['title' => 'Edit ' . $row['name'], 'breadcrumbs' => ['Languages' => admin_url('languages'), $row['name'] => null], 'l' => $row]);
    }

    public function update(Request $request, string $code): Response
    {
        $row = $this->row($code);
        $v = Validator::make($request->post, ['name' => 'required|string|max:80', 'native_name' => 'required|string|max:80', 'sort_order' => 'nullable|integer'], ['name' => 'Name', 'native_name' => 'Name in its own language', 'sort_order' => 'Order']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), $request->post, admin_url("languages/{$code}/edit"));
        }
        $data = ['name' => $request->string('name'), 'native_name' => $request->string('native_name'), 'direction' => $request->string('direction') === 'rtl' ? 'rtl' : 'ltr', 'sort_order' => $request->int('sort_order')];
        $this->db()->table('languages')->where('code', $code)->update($data);
        Locales::flush();
        Activity::log('languages.update', "Edited language {$code}", 'language', $code, array_intersect_key($row, $data), $data);
        session()->toast('success', 'Language saved.');
        return redirect(admin_url('languages'));
    }

    public function toggle(Request $request, string $code): Response
    {
        $row = $this->row($code);
        $new = (int) $row['enabled'] ? 0 : 1;
        if (!$new && $code === Locales::defaultCode()) {
            session()->toast('danger', 'The default language cannot be switched off. Choose another default first.');
            return back(admin_url('languages'));
        }
        $this->db()->table('languages')->where('code', $code)->update(['enabled' => $new]);
        Locales::flush();
        Activity::log('languages.toggle', ($new ? 'Switched on ' : 'Switched off ') . $code, 'language', $code);
        session()->toast('success', $new ? 'Language switched on. Visitors can now choose it.' : 'Language switched off.');
        return back(admin_url('languages'));
    }

    public function makeDefault(Request $request, string $code): Response
    {
        $row = $this->row($code);
        $this->db()->table('languages')->where('is_default', 1)->update(['is_default' => 0]);
        $this->db()->table('languages')->where('code', $code)->update(['is_default' => 1, 'enabled' => 1]);
        settings()->set('localisation.default_language', $code, auth_user()['id'] ?? null);
        Locales::flush();
        Activity::log('languages.default', "Made {$row['name']} the default language", 'language', $code);
        session()->toast('success', "{$row['name']} is now the default language.");
        return back(admin_url('languages'));
    }

    public function destroy(Request $request, string $code): Response
    {
        $row = $this->row($code);
        if ($code === 'en' || $code === Locales::defaultCode()) {
            session()->toast('danger', $code === 'en' ? 'English is built in and cannot be removed.' : 'The default language cannot be removed.');
            return back(admin_url('languages'));
        }
        $this->db()->table('translations')->where('locale', $code)->delete();
        $this->db()->table('languages')->where('code', $code)->delete();
        Locales::flush();
        Activity::log('languages.delete', "Removed language {$row['name']} ({$code}) and its edited translations", 'language', $code);
        session()->toast('success', 'Language removed.');
        return redirect(admin_url('languages'));
    }

    /** Drag and drop in the languages list: ids[] arrive in the new order. */
    public function reorder(Request $request): Response
    {
        $codes = array_values(array_filter((array) ($request->post['ids'] ?? []), 'is_string'));
        $known = array_column($this->db()->table('languages')->get(), 'code');
        $n = 0;
        foreach ($codes as $code) {
            if (in_array($code, $known, true)) {
                $this->db()->table('languages')->where('code', $code)->update(['sort_order' => $n * 10]);
                $n++;
            }
        }
        \AP\Modules\Languages\Locales::flush();
        return Response::json(['ok' => true, 'message' => 'Order saved']);
    }
}
