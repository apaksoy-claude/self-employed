<?php
declare(strict_types=1);

namespace AP\Modules\Newsletter\Controllers;

use AP\Core\Activity;
use AP\Core\CsvExport;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Newsletter\Newsletter;

final class SubscriberController
{
    public const TABS = ['subscribed' => 'On the list', 'left' => 'Left', 'all' => 'Everyone'];

    private function query(string $tab, string $q)
    {
        $b = Newsletter::db()->table('newsletter_subscribers');
        if ($tab !== 'all') {
            $b->where('status', $tab);
        }
        return $b->search(['email', 'name'], $q);
    }

    public function index(Request $request): Response
    {
        if (!Newsletter::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Subscribers again.');
            return redirect(admin_url());
        }
        $tab = isset(self::TABS[$request->string('tab')]) ? $request->string('tab') : 'subscribed';
        $q = trim($request->string('q'));
        $counts = [];
        foreach (array_keys(self::TABS) as $t) {
            $counts[$t] = $this->query($t, '')->count();
        }
        $month = Newsletter::db()->table('newsletter_subscribers')->where('status', 'subscribed')->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - 30 * 86400))->count();
        return view('newsletter::index', [
            'title' => 'Subscribers', 'breadcrumbs' => ['Subscribers' => null],
            'about' => 'People who signed up for your news. Download the list and import it into your email tool (Mailchimp, Brevo, MailerLite and others).',
            'tab' => $tab, 'q' => $q, 'counts' => $counts, 'month' => $month,
            'pager' => $this->query($tab, $q)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->paginate(max(1, $request->int('page', 1)), 25),
        ]);
    }

    public function export(Request $request): Response
    {
        if (!Newsletter::ready()) {
            return redirect(admin_url());
        }
        $tab = isset(self::TABS[$request->string('tab')]) ? $request->string('tab') : 'subscribed';
        $rows = [];
        foreach ($this->query($tab, trim($request->string('q')))->orderBy('created_at')->get() as $s) {
            $rows[] = [$s['email'], $s['name'], $s['status'] === 'subscribed' ? 'subscribed' : 'left', $s['created_at'], $s['left_at'], $s['consent'], $s['source'], Newsletter::leaveUrl($s)];
        }
        Activity::log('newsletter.export', 'Downloaded ' . count($rows) . ' newsletter subscriber(s)', 'newsletter', $tab);
        return CsvExport::response('subscribers-' . date('Y-m-d') . '.csv', ['Email', 'First name', 'Status', 'Signed up (UTC)', 'Left (UTC)', 'Agreed to', 'Signed up on', 'Leave link'], $rows);
    }

    public function delete(Request $request, int $id): Response
    {
        $row = Newsletter::db()->table('newsletter_subscribers')->where('id', $id)->first();
        if ($row) {
            Newsletter::db()->table('newsletter_subscribers')->where('id', $id)->delete();
            Activity::log('newsletter.delete', 'Removed subscriber ' . $row['email'], 'newsletter', $id);
            session()->toast('success', $row['email'] . ' removed.');
        }
        return redirect(admin_url('subscribers'));
    }

    /** Mark as left, put back, or remove several subscribers at once. */
    public function bulk(Request $request): Response
    {
        $tab = isset(self::TABS[$request->string('tab')]) ? $request->string('tab') : 'subscribed';
        $back = admin_url('subscribers') . ($tab !== 'subscribed' ? '?tab=' . $tab : '');
        if (!Newsletter::ready()) {
            return redirect(admin_url());
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))))), 0, 500);
        if (!$ids) {
            session()->toast('warning', 'Tick some subscribers first.');
            return redirect($back);
        }
        $q = static fn() => Newsletter::db()->table('newsletter_subscribers')->whereIn('id', $ids);
        $do = $request->string('action');
        if ($do === 'delete') {
            $n = $q()->delete();
            Activity::log('newsletter.delete', $n . ' subscriber(s) removed together', 'newsletter', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' subscriber(s) removed.');
        } elseif ($do === 'leave') {
            $n = $q()->where('status', 'subscribed')->update(['status' => 'left', 'left_at' => gmdate('Y-m-d H:i:s')]);
            Activity::log('newsletter.leave', $n . ' subscriber(s) marked as left', 'newsletter', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' subscriber(s) marked as left.');
        } elseif ($do === 'rejoin') {
            $n = $q()->where('status', 'left')->update(['status' => 'subscribed', 'left_at' => null]);
            Activity::log('newsletter.rejoin', $n . ' subscriber(s) put back on the list', 'newsletter', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' subscriber(s) put back on the list.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        return redirect($back);
    }
}
