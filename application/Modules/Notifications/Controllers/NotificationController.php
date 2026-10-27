<?php
declare(strict_types=1);

namespace AP\Modules\Notifications\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Notifications\Notifications;

final class NotificationController
{
    private function uid(): int
    {
        return (int) (auth_user()['id'] ?? 0);
    }

    public function index(Request $request): Response
    {
        if (!Notifications::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Notifications again.');
            return redirect(admin_url());
        }
        $filter = $request->string('show');
        $filter = ($filter === 'unread' || isset(Notifications::KINDS[$filter])) ? $filter : 'all';
        $page = max(1, $request->int('page', 1));
        $per = 25;
        $rows = Notifications::latest($this->uid(), $per + 1, $filter, ($page - 1) * $per);
        return view('notifications::index', ['title' => 'Notifications', 'breadcrumbs' => ['Notifications' => null], 'rows' => array_slice($rows, 0, $per), 'more' => count($rows) > $per, 'page' => $page, 'filter' => $filter, 'unread' => Notifications::unread($this->uid())]);
    }

    /** Opens the thing a notification is about, and marks it read. */
    public function open(Request $request, string $id): Response
    {
        $n = Notifications::db()->table('notifications')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        Notifications::markRead($this->uid(), [(int) $n['id']]);
        $url = (string) ($n['url'] ?? '');
        $own = $url !== '' && (str_starts_with($url, admin_url()) || (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')));
        return redirect($own ? $url : admin_url('notifications'));
    }

    public function read(Request $request): Response
    {
        $ids = array_map('intval', (array) ($request->all()['ids'] ?? []));
        Notifications::markRead($this->uid(), $ids ?: null);
        return back(admin_url('notifications'));
    }

    public function clear(Request $request): Response
    {
        $n = Notifications::prune();
        $db = Notifications::db();
        $db->table('notification_reads')->delete();
        $db->table('notifications')->delete();
        session()->toast('success', 'All notifications cleared.');
        return redirect(admin_url('notifications'));
    }

    /** What the bell asks for now and then. */
    public function feed(Request $request): Response
    {
        return Response::json(['unread' => Notifications::unread($this->uid()), 'html' => Notifications::menuHtml($this->uid())]);
    }
}
