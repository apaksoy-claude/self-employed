<?php
declare(strict_types=1);

namespace AP\Modules\Contact\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Contact\Contact;

final class MessageController
{
    public const TABS = ['inbox' => 'Inbox', 'new' => 'New', 'replied' => 'Answered', 'spam' => 'Marked as spam'];

    public function index(Request $request): Response
    {
        if (!Contact::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Messages again.');
            return redirect(admin_url());
        }
        $tab = array_key_exists($request->string('tab'), self::TABS) ? $request->string('tab') : 'inbox';
        $q = Contact::db()->table('contact_messages');
        match ($tab) {
            'new' => $q->where('status', 'new'),
            'replied' => $q->where('status', 'replied'),
            'spam' => $q->where('status', 'spam'),
            default => $q->where('status', '!=', 'spam'),
        };
        $term = trim($request->string('q'));
        if ($term !== '') {
            $q->search(['name', 'email', 'subject', 'message'], $term);
        }
        $counts = [];
        foreach (Contact::db()->table('contact_messages')->select('status')->get() as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
        }
        $counts['inbox'] = array_sum($counts) - ($counts['spam'] ?? 0);
        return view('contact::index', ['title' => 'Messages', 'breadcrumbs' => ['Messages' => null], 'tab' => $tab, 'q' => $term,
            'pager' => $q->orderBy('id', 'desc')->paginate(max(1, $request->int('page', 1)), 30), 'counts' => $counts, 'to' => Contact::to()]);
    }

    public function show(Request $request, string $id): Response
    {
        $m = Contact::db()->table('contact_messages')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        if ($m['status'] === 'new') {
            Contact::db()->table('contact_messages')->where('id', (int) $id)->update(['status' => 'read', 'read_at' => gmdate('Y-m-d H:i:s')]);
            $m['status'] = 'read';
        }
        $prev = Contact::db()->table('contact_messages')->where('id', '>', (int) $id)->where('status', '!=', 'spam')->orderBy('id')->value('id');
        $next = Contact::db()->table('contact_messages')->where('id', '<', (int) $id)->where('status', '!=', 'spam')->orderBy('id', 'desc')->value('id');
        $others = Contact::db()->table('contact_messages')->where('email', $m['email'])->where('id', '!=', (int) $id)->orderBy('id', 'desc')->limit(10)->get();
        return view('contact::show', ['title' => $m['subject'] ?: 'Message from ' . $m['name'], 'breadcrumbs' => ['Messages' => admin_url('messages'), (string) $m['name'] => null],
            'm' => $m, 'prev' => $prev, 'next' => $next, 'others' => $others]);
    }

    public function status(Request $request, string $id): Response
    {
        $m = Contact::db()->table('contact_messages')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $to = $request->string('status');
        if (!in_array($to, ['new', 'read', 'replied', 'spam'], true)) {
            throw new HttpException(422);
        }
        Contact::db()->table('contact_messages')->where('id', (int) $id)->update(['status' => $to]);
        if ($m['status'] === 'spam' && $to !== 'spam' && $request->string('send') === '1') {
            Contact::notify((int) $id); // it was held by mistake: send it on now
        }
        $labels = ['new' => 'Marked as new.', 'read' => 'Marked as read.', 'replied' => 'Marked as answered.', 'spam' => 'Moved to spam.'];
        session()->toast('success', $labels[$to]);
        return $to === 'spam' ? redirect(admin_url('messages')) : back(admin_url('messages/' . (int) $id));
    }

    public function delete(Request $request, string $id): Response
    {
        $m = Contact::db()->table('contact_messages')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        Contact::db()->table('contact_messages')->where('id', (int) $id)->delete();
        Activity::log('contact.delete', 'Deleted a message from ' . $m['email'], 'contact_message', $id);
        session()->toast('success', 'Message deleted.');
        return redirect(admin_url('messages'));
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $action = $request->string('action');
        if (!$ids) {
            session()->toast('warning', 'Tick one or more messages first.');
            return back(admin_url('messages'));
        }
        $q = Contact::db()->table('contact_messages')->whereIn('id', $ids);
        $n = match ($action) {
            'read' => $q->update(['status' => 'read']),
            'replied' => $q->update(['status' => 'replied']),
            'spam' => $q->update(['status' => 'spam']),
            'delete' => $q->delete(),
            default => 0,
        };
        if ($action === 'delete') {
            Activity::log('contact.delete', 'Deleted ' . $n . ' message(s)');
        }
        session()->toast('success', $n . ' message(s) changed.');
        return back(admin_url('messages'));
    }
}
