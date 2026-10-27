<?php
declare(strict_types=1);

namespace AP\Modules\Seo\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Seo\Seo;

final class RedirectController
{
    public function index(Request $request): Response
    {
        if (!Seo::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Redirects again.');
            return redirect(admin_url());
        }
        $db = Seo::db();
        $tab = $request->string('tab') === 'missing' ? 'missing' : 'redirects';
        $q = trim($request->string('q'));
        $page = max(1, $request->int('page', 1));
        if ($tab === 'redirects') {
            $query = $db->table('redirects');
            if ($q !== '') {
                $query->search(['from_path', 'to_url', 'note'], $q);
            }
            $pager = $query->orderBy('from_path')->paginate($page, 50);
        } else {
            $query = $db->table('not_found_log');
            if ($q !== '') {
                $query->search(['path', 'referrer'], $q);
            }
            $pager = $query->orderBy('hits', 'desc')->orderBy('last_seen', 'desc')->paginate($page, 50);
        }
        $edit = $request->int('edit') > 0 ? $db->table('redirects')->where('id', $request->int('edit'))->first() : null;
        return view('seo::redirects', [
            'title' => 'Redirects', 'breadcrumbs' => ['Redirects' => null], 'tab' => $tab, 'q' => $q, 'pager' => $pager, 'edit' => $edit,
            'prefill' => Seo::normalise($request->string('from')),
            'counts' => ['redirects' => $db->table('redirects')->count(), 'missing' => $db->table('not_found_log')->count()],
            'logging' => (bool) setting('seo.log_404', true), 'test' => session()->flashed('redirect_test'),
        ]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,list<string>>} */
    private function input(Request $request, int $ignoreId = 0): array
    {
        $from = Seo::normalise($request->string('from_path'));
        $to = trim($request->string('to_url'));
        $code = in_array($request->int('code', 301), [301, 302], true) ? $request->int('code', 301) : 301;
        $errors = [];
        if ($from === '') {
            $errors['from_path'][] = 'Type the old address, for example old-page or blog/*.';
        } elseif (mb_strlen($from) > 500) {
            $errors['from_path'][] = 'The old address is too long.';
        } elseif (str_contains(rtrim($from, '*'), '*')) {
            $errors['from_path'][] = 'A * may only be at the very end, for example blog/*.';
        }
        $admin = trim(admin_path(), '/');
        if ($from === $admin || str_starts_with($from, $admin . '/')) {
            $errors['from_path'][] = 'The admin cannot be redirected.';
        }
        if ($to === '') {
            $errors['to_url'][] = 'Type where visitors should go: a page like new-page, or a full web address.';
        } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $to) && !preg_match('#^https?://#i', $to)) {
            $errors['to_url'][] = 'Only web addresses (https://…) or addresses on this site can be used.';
        } elseif (!preg_match('#^https?://#i', $to) && Seo::normalise($to) === rtrim($from, '/*') && !str_ends_with($from, '*')) {
            $errors['to_url'][] = 'That would send visitors round in a circle.';
        }
        if (!$errors) {
            $dupe = Seo::db()->table('redirects')->where('from_path', $from);
            if ($ignoreId > 0) {
                $dupe->where('id', '!=', $ignoreId);
            }
            if ($dupe->exists()) {
                $errors['from_path'][] = 'There is already a redirect for this address. Change that one instead.';
            }
        }
        if (!preg_match('#^https?://#i', $to)) {
            $to = ltrim($to, '/');
        }
        return [['from_path' => $from, 'to_url' => mb_substr($to, 0, 500), 'code' => $code, 'note' => mb_substr(trim($request->string('note')), 0, 190) ?: null], $errors];
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->input($request);
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('redirects'));
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = Seo::db()->table('redirects')->insertGetId($data + ['enabled' => 1, 'hits' => 0, 'created_at' => $now, 'updated_at' => $now]);
        // The address now goes somewhere, so it no longer belongs in the "not found" list.
        Seo::db()->table('not_found_log')->where('path', $data['from_path'])->delete();
        Activity::log('seo.redirect', 'Redirect added: /' . $data['from_path'] . ' → ' . $data['to_url'], 'redirect', (string) $id, [], $data);
        session()->toast('success', 'Redirect added. Visitors to /' . $data['from_path'] . ' now go to ' . $data['to_url'] . '.');
        return redirect(admin_url('redirects'));
    }

    public function update(Request $request, string $id): Response
    {
        $row = Seo::db()->table('redirects')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        [$data, $errors] = $this->input($request, (int) $id);
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('redirects') . '?edit=' . (int) $id);
        }
        Seo::db()->table('redirects')->where('id', (int) $id)->update($data + ['updated_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('seo.redirect', 'Redirect changed: /' . $data['from_path'], 'redirect', $id, array_intersect_key($row, $data), $data);
        session()->toast('success', 'Redirect saved.');
        return redirect(admin_url('redirects'));
    }

    public function toggle(Request $request, string $id): Response
    {
        $row = Seo::db()->table('redirects')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $on = !(int) $row['enabled'];
        Seo::db()->table('redirects')->where('id', (int) $id)->update(['enabled' => $on ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        if ($request->isAjax() || $request->wantsJson()) {
            return Response::json(['ok' => true, 'enabled' => $on, 'message' => $on ? 'Redirect switched on' : 'Redirect switched off']);
        }
        session()->toast('success', $on ? 'Redirect switched on.' : 'Redirect switched off.');
        return back(admin_url('redirects'));
    }

    public function delete(Request $request, string $id): Response
    {
        $row = Seo::db()->table('redirects')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        Seo::db()->table('redirects')->where('id', (int) $id)->delete();
        Activity::log('seo.redirect', 'Redirect removed: /' . $row['from_path'], 'redirect', $id, $row, []);
        session()->toast('success', 'Redirect removed.');
        return back(admin_url('redirects'));
    }

    /** "Where does this address go?" */
    public function test(Request $request): Response
    {
        $path = Seo::normalise($request->string('address') !== '' ? (string) (parse_url($request->string('address'), PHP_URL_PATH) ?? '') : '');
        $base = trim((string) parse_url(base_url(), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, mb_strtolower($base) . '/')) {
            $path = substr($path, strlen($base) + 1);
        }
        $r = $path !== '' ? Seo::match($path) : null;
        session()->flash('redirect_test', ['address' => '/' . $path, 'to' => $r ? Seo::target($r) : null, 'code' => $r ? (int) $r['code'] : null, 'rule' => $r ? '/' . $r['from_path'] : null]);
        return redirect(admin_url('redirects'));
    }

    public function forget(Request $request, string $id): Response
    {
        Seo::db()->table('not_found_log')->where('id', (int) $id)->delete();
        if ($request->isAjax() || $request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => 'Removed from the list']);
        }
        return redirect(admin_url('redirects') . '?tab=missing');
    }

    public function clear(): Response
    {
        $n = Seo::db()->table('not_found_log')->delete();
        Activity::log('seo.not_found.clear', 'Emptied the list of addresses not found (' . $n . ')');
        session()->toast('success', 'The list of addresses not found is empty.');
        return redirect(admin_url('redirects') . '?tab=missing');
    }
}
