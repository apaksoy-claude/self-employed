<?php
declare(strict_types=1);

namespace AP\Modules\Pwa\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Pwa\Push;
use AP\Modules\Pwa\WebPush;
use Throwable;

final class PushController
{
    public function index(Request $request): Response
    {
        $status = Push::status();
        $history = [];
        $stats = ['subscribers' => $status['subscribers'], 'new30' => 0, 'sent30' => 0, 'clicks30' => 0];
        if (Push::tablesReady()) {
            $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
            $history = Push::db()->table('push_messages')->orderBy('id', 'desc')->limit(30)->get();
            $stats['new30'] = Push::db()->table('push_subscriptions')->where('created_at', '>=', $since)->count();
            foreach (Push::db()->table('push_messages')->where('created_at', '>=', $since)->where('audience', 'all')->get() as $m) {
                $stats['sent30'] += (int) $m['sent'];
                $stats['clicks30'] += (int) $m['clicks'];
            }
        }
        $me = Push::tablesReady() ? Push::db()->table('push_subscriptions')->where('user_id', (int) auth_user()['id'])->count() : 0;
        return view('pwa::push', [
            'title' => 'Push notifications', 'breadcrumbs' => ['Push notifications' => null], 'status' => $status, 'ready' => Push::ready(),
            'history' => $history, 'stats' => $stats, 'mine' => $me, 'cfgKey' => (string) setting('push.public_key', ''),
        ]);
    }

    /** @return array{0:array<string,string>,1:array<string,list<string>>} */
    private function input(Request $request): array
    {
        $d = ['title' => trim($request->string('title')), 'body' => trim($request->string('body')), 'url' => trim($request->string('url')), 'image' => trim($request->string('image'))];
        $errors = [];
        if ($d['title'] === '' || mb_strlen($d['title']) > 60) {
            $errors['title'][] = 'Write a title of up to 60 letters.';
        }
        if (mb_strlen($d['body']) > 160) {
            $errors['body'][] = 'Keep the message to 160 letters; phones cut longer ones.';
        }
        if ($d['url'] !== '' && preg_match('#^[a-z][a-z0-9+.-]*:#i', $d['url']) && !preg_match('#^https?://#i', $d['url'])) {
            $errors['url'][] = 'Use a page of this site or a web address starting with https://.';
        }
        return [$d, $errors];
    }

    public function send(Request $request): Response
    {
        if (!Push::ready()) {
            session()->toast('warning', 'Switch on push notifications and make the keys first.');
            return redirect(admin_url('settings/app'));
        }
        [$d, $errors] = $this->input($request);
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('push'));
        }
        $id = Push::start($d['title'], $d['body'], $d['url'], $d['image'], 'all');
        $m = Push::db()->table('push_messages')->where('id', $id)->first();
        Activity::log('push.send', 'Push notification sent: ' . $d['title'] . ' (' . (int) $m['total'] . ' browser(s))', 'push', (string) $id);
        session()->toast('success', (int) $m['total'] > 0 ? 'Sending to ' . number_format((int) $m['total']) . ' browser(s). The numbers below update as it goes.' : 'Nobody has allowed notifications yet, so nothing was sent.');
        return redirect(admin_url('push'));
    }

    public function test(Request $request): Response
    {
        if (!Push::ready()) {
            return Response::json(['ok' => false, 'message' => 'Switch on push notifications and make the keys first.'], 409);
        }
        [$d, $errors] = $this->input($request);
        if ($errors) {
            return Response::json(['ok' => false, 'message' => reset($errors)[0]], 422);
        }
        $id = Push::start($d['title'], $d['body'], $d['url'], $d['image'], 'user:' . (int) auth_user()['id']);
        $m = Push::db()->table('push_messages')->where('id', $id)->first();
        if ((int) $m['total'] === 0) {
            return Response::json(['ok' => false, 'message' => 'This browser has not allowed notifications yet. Press "Allow on this browser" first.'], 409);
        }
        return Response::json(['ok' => (int) $m['sent'] > 0, 'message' => (int) $m['sent'] > 0 ? 'Test sent to your browser(s). It should appear in a few seconds.' : 'The push service refused it. Make new keys and allow notifications again.']);
    }

    public function keys(Request $request): Response
    {
        if (!WebPush::supported()) {
            session()->toast('danger', 'This server cannot make push keys: the PHP openssl and curl extensions are needed.');
            return redirect(admin_url('settings/app'));
        }
        try {
            $had = (string) setting('push.public_key', '') !== '';
            Push::makeKeys();
            if ($had && Push::tablesReady()) {
                // Old browsers were signed up with the old key and can no longer be reached.
                Push::db()->table('push_subscriptions')->delete();
            }
            Activity::log('push.keys', $had ? 'Made new push notification keys' : 'Made push notification keys');
            session()->toast('success', $had ? 'New keys made. Visitors need to allow notifications again.' : 'Keys made. Switch on push notifications and save.');
        } catch (Throwable $e) {
            session()->toast('danger', $e->getMessage());
        }
        return redirect(admin_url('settings/app'));
    }
}
