<?php
declare(strict_types=1);

namespace AP\Modules\Settings\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Settings;
use AP\Core\Validator;

final class SettingsController
{
    /** Settings pages the person may see, grouped by category for the menu on the left. */
    private function nav(): array
    {
        $out = [];
        foreach (settings()->groups() as $key => $g) {
            if (!empty($g['permission']) && !can((string) $g['permission'])) {
                continue;
            }
            $out[(string) $g['category']][$key] = $g;
        }
        return $out;
    }

    private function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    public function index(Request $request): Response
    {
        $term = mb_strtolower($request->string('q'));
        if ($term === '') {
            foreach ($this->nav() as $items) {
                foreach ($items as $key => $g) {
                    if (empty($g['url']) && ($g['hub'] ?? 'settings') === 'settings') {
                        return redirect(admin_url('settings/' . $key));
                    }
                }
            }
            return redirect(admin_url());
        }
        $groups = settings()->groups();
        $hits = [];
        foreach (settings()->definitions() as $key => $def) {
            if (!isset($groups[$def['group']])) {
                continue;
            }
            if (str_contains(mb_strtolower($def['label'] . ' ' . $def['help'] . ' ' . $def['section'] . ' ' . $key), $term)) {
                $hits[$def['group']][$key] = $def;
            }
        }
        return view('settings::search', [
            'title' => 'Search settings', 'term' => $request->string('q'), 'hits' => $hits, 'groups' => $groups, 'nav' => $this->nav(), 'active' => '',
            'breadcrumbs' => ['Settings' => admin_url('settings'), 'Search' => null],
        ]);
    }

    public function group(Request $request, string $group): Response
    {
        $meta = settings()->group($group) ?? throw new HttpException(404);
        if (!empty($meta['permission']) && !can((string) $meta['permission'])) {
            throw new HttpException(403);
        }
        if (!empty($meta['url'])) {
            return redirect(admin_url((string) $meta['url']));
        }
        return view('settings::group', [
            'title' => $meta['label'] . ' settings', 'group' => $group, 'meta' => $meta, 'nav' => $this->nav(), 'active' => $group,
            'sections' => settings()->sections($group), 'canEdit' => can('settings.edit'),
            'about' => $meta['description'] ?: 'Change how this part of the website works. Every change is recorded in the settings history.',
            'breadcrumbs' => (($meta['hub'] ?? 'settings') === 'system' ? ['System' => admin_url('system')] : ['Settings' => admin_url('settings')]) + [$meta['label'] . ' settings' => null],
        ]);
    }

    public function save(Request $request, string $group): Response
    {
        $defs = settings()->grouped()[$group] ?? throw new HttpException(404);
        $meta = settings()->group($group) ?? [];
        if (!empty($meta['permission']) && !can((string) $meta['permission'])) {
            throw new HttpException(403);
        }
        $rules = [];
        $labels = [];
        $data = [];
        foreach ($defs as $key => $def) {
            $field = $this->field($key);
            $labels[$field] = $def['label'];
            $raw = $request->post[$field] ?? null;
            if ($def['type'] === 'repeater') {
                $data[$field] = Settings::rows($def, is_array($raw) ? $raw : []);
                continue;
            }
            $data[$field] = is_array($raw) ? null : $raw;
            if ($def['rules'] !== '') {
                $rules[$field] = $def['rules'];
            }
        }
        $errors = Validator::make($data, $rules, $labels)->errors();
        foreach ($defs as $key => $def) {
            $field = $this->field($key);
            $value = $data[$field];
            if (in_array($def['type'], ['select', 'segmented', 'cards'], true) && $def['options'] && $value !== null && !array_key_exists((string) $value, $def['options'])) {
                $errors[$field][] = $def['label'] . ' has an option that is not allowed.';
            }
            if ($def['type'] === 'timezone' && !in_array((string) $value, \DateTimeZone::listIdentifiers(), true)) {
                $errors[$field][] = 'Please choose a time zone from the list.';
            }
            if ($def['type'] === 'color' && $value !== null && $value !== '' && !preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) {
                $errors[$field][] = $def['label'] . ' must be a colour such as #2563EB.';
            }
            if ($def['type'] === 'repeater') {
                foreach ($this->rowErrors($def, $value) as $msg) {
                    $errors[$field][] = $msg;
                }
            }
        }
        // Modules that show their settings on their own page send "return" so people come back there.
        $return = $request->string('return');
        $back = $return !== '' && str_starts_with($return, admin_url() . '/') && strpos($return, '//', strlen(admin_url())) === false ? $return : admin_url('settings/' . $group);
        if ($errors) {
            return back_with_errors($errors, $request->post, $back);
        }

        $before = [];
        $after = [];
        $uid = (int) auth_user()['id'];
        foreach ($defs as $key => $def) {
            $field = $this->field($key);
            $value = $data[$field];
            if ($def['secret'] ?? false) {
                if (!empty($request->post[$field . '__remove']) && settings()->hasValue($key)) {
                    settings()->set($key, '', $uid);
                    $before[$key] = '********';
                    $after[$key] = '(removed)';
                    continue;
                }
                if ($value === null || $value === '') {
                    continue;
                }
                settings()->set($key, (string) $value, $uid);
                $before[$key] = '********';
                $after[$key] = '********';
                continue;
            }
            $old = settings()->get($key);
            if ($def['type'] === 'repeater') {
                if ($old != $value) {
                    $before[$key] = $old;
                    $after[$key] = $value;
                }
                settings()->set($key, $value, $uid);
                continue;
            }
            $new = $def['type'] === 'checkbox' ? ($value === '1' || $value === 1 || $value === true) : (string) ($value ?? '');
            if ((string) (is_bool($old) ? (int) $old : (is_array($old) ? json_encode($old) : $old)) !== (string) (is_bool($new) ? (int) $new : $new)) {
                $before[$key] = $old;
                $after[$key] = $new;
            }
            settings()->set($key, is_bool($new) ? ($new ? '1' : '0') : $new, $uid);
        }
        if ($after) {
            Activity::log('settings.save', 'Changed ' . count($after) . ' setting(s) in ' . $group, 'settings', $group, $before, $after);
            event('settings.saved', array_keys($after));
        }
        session()->toast('success', $after ? 'Settings saved.' : 'Nothing was changed.');
        return redirect($back);
    }

    /** Check each row of a list against its columns' rules. @return string[] */
    private function rowErrors(array $def, array $rows): array
    {
        $out = [];
        $fields = Settings::repeaterFields((array) $def['fields']);
        if (count($rows) > (int) $def['max_rows']) {
            $out[] = $def['label'] . ' can have at most ' . (int) $def['max_rows'] . ' rows.';
        }
        if (count($rows) < (int) $def['min_rows']) {
            $out[] = $def['label'] . ' needs at least ' . (int) $def['min_rows'] . ' row(s).';
        }
        foreach ($rows as $i => $row) {
            $rules = [];
            $labels = [];
            foreach ($fields as $name => $f) {
                if ($f['rules'] !== '') {
                    $rules[$name] = str_contains((string) $f['rules'], 'required') ? $f['rules'] : 'nullable|' . $f['rules'];
                }
                $labels[$name] = $f['label'];
            }
            if ($rules) {
                foreach (Validator::make($row, $rules, $labels)->errors() as $msgs) {
                    foreach ($msgs as $m) {
                        $out[] = 'Row ' . ($i + 1) . ': ' . $m;
                    }
                }
            }
            foreach ($fields as $name => $f) {
                if ($f['type'] === 'select' && $f['options'] && ($row[$name] ?? '') !== '' && !array_key_exists($row[$name], (array) $f['options'])) {
                    $out[] = 'Row ' . ($i + 1) . ': ' . $f['label'] . ' has an option that is not allowed.';
                }
            }
        }
        return $out;
    }

    public function reset(Request $request): Response
    {
        $key = $request->string('key');
        $def = settings()->definition($key) ?? throw new HttpException(404);
        $perm = (string) (settings()->group((string) $def['group'])['permission'] ?? '');
        if ($perm !== '' && !can($perm)) {
            throw new HttpException(403);
        }
        $old = settings()->get($key);
        settings()->reset($key, (int) auth_user()['id']);
        Activity::log('settings.reset', 'Reset "' . $def['label'] . '" to its default', 'settings', $key, [$key => ($def['secret'] ?? false) ? '********' : $old], [$key => $def['default']]);
        event('settings.saved', [$key]);
        session()->toast('success', '"' . $def['label'] . '" was reset to its default.');
        return redirect(admin_url('settings/' . $def['group']));
    }

    /** Saves how this person arranged a settings page (their own view, so viewing settings is enough). */
    public function layout(Request $request): Response
    {
        $group = $request->string('group');
        if (!preg_match('/^[a-z0-9_]{1,60}$/', $group)) {
            return Response::json(['ok' => false, 'message' => 'Unknown page'], 422);
        }
        $data = json_decode($request->string('layout'), true);
        try {
            \AP\Modules\Settings\Layout::save($group, is_array($data) ? $data : []);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'message' => 'Press "Finish update" first, then try again.'], 409);
        }
        return Response::json(['ok' => true, 'message' => 'Layout saved']);
    }

    public function layoutReset(Request $request): Response
    {
        $group = $request->string('group');
        if (preg_match('/^[a-z0-9_]{1,60}$/', $group)) {
            try {
                \AP\Modules\Settings\Layout::reset($group);
            } catch (\Throwable) {
                return Response::json(['ok' => false, 'message' => 'Could not reset the layout'], 409);
            }
        }
        return Response::json(['ok' => true, 'message' => 'Layout reset']);
    }

    public function history(Request $request): Response
    {
        /** @var DB $db */
        $db = app(DB::class);
        $q = $db->table('setting_history')->select('setting_history.*', 'users.name AS user_name')->join('users', 'setting_history.user_id', '=', 'users.id', 'LEFT');
        if ($request->string('key') !== '') {
            $q->where('setting_history.name', $request->string('key'));
        }
        $pager = $q->orderBy('setting_history.id', 'DESC')->paginate($request->int('page', 1), 30);
        return view('settings::history', [
            'title' => 'Settings history', 'pager' => $pager, 'defs' => settings()->definitions(), 'key' => $request->string('key'), 'nav' => $this->nav(), 'active' => '__history',
            'about' => 'Every change to a setting, who made it and when. Passwords and keys are never shown.',
            'breadcrumbs' => ['Settings' => admin_url('settings'), 'History' => null],
        ]);
    }
}
