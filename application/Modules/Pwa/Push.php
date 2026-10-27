<?php
declare(strict_types=1);

namespace AP\Modules\Pwa;

use AP\Core\App;
use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Logger;
use AP\Core\Queue;
use Throwable;

/** Push notifications: who allowed them, sending a message to all of them in batches, and the numbers. */
final class Push
{
    public const BATCH = 100;

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function tablesReady(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('push_subscriptions');
        } catch (Throwable) {
            return false;
        }
    }

    /** Switched on, with keys, tables and a server that can do it. */
    public static function ready(): bool
    {
        return (bool) setting('push.enabled', false) && (string) setting('push.public_key', '') !== '' && settings()->hasValue('push.private_key')
            && WebPush::supported() && self::tablesReady();
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $subs = 0;
        try {
            $subs = self::tablesReady() ? self::db()->table('push_subscriptions')->count() : 0;
        } catch (Throwable) {
            $subs = 0;
        }
        return ['supported' => WebPush::supported(), 'keys' => (string) setting('push.public_key', '') !== '' && settings()->hasValue('push.private_key'),
            'enabled' => (bool) setting('push.enabled', false), 'subscribers' => $subs, 'public_key' => (string) setting('push.public_key', ''), 'tables' => self::tablesReady()];
    }

    public static function makeKeys(): void
    {
        $k = WebPush::generateKeys();
        settings()->set('push.public_key', $k['public'], (int) (auth_user()['id'] ?? 0) ?: null);
        settings()->set('push.private_key', $k['private'], (int) (auth_user()['id'] ?? 0) ?: null);
    }

    public static function button(string $where): string
    {
        if (!self::ready()) {
            return '';
        }
        $text = (string) setting('push.prompt_text');
        return '<button type="button" class="ap-push-btn ap-push-' . e($where) . '" data-ap-push hidden title="' . e($text) . '"><i class="fa-regular fa-bell" aria-hidden="true"></i><span data-ap-push-label>' . e(__('pwa.push_on')) . '</span></button>';
    }

    public static function subject(): string
    {
        $mail = trim((string) setting('push.contact', '')) ?: trim((string) setting('general.admin_email', ''));
        return $mail !== '' ? 'mailto:' . $mail : url('/');
    }

    /** Saves (or refreshes) a browser's subscription. */
    public static function subscribe(string $endpoint, string $p256dh, string $auth, ?int $userId, string $lang, string $agent): bool
    {
        try {
            $keysOk = strlen(WebPush::unb64($p256dh)) === 65 && strlen(WebPush::unb64($auth)) >= 16;
        } catch (Throwable) {
            $keysOk = false;
        }
        if (!$keysOk || !preg_match('#^https://[^\s]{10,690}$#', $endpoint)) {
            return false;
        }
        $hash = sha1($endpoint);
        $now = gmdate('Y-m-d H:i:s');
        $row = ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth, 'user_id' => $userId, 'language' => mb_substr($lang, 0, 10), 'user_agent' => mb_substr($agent, 0, 255), 'failures' => 0, 'last_seen' => $now];
        if (self::db()->table('push_subscriptions')->where('endpoint_hash', $hash)->exists()) {
            self::db()->table('push_subscriptions')->where('endpoint_hash', $hash)->update($row);
        } else {
            self::db()->table('push_subscriptions')->insert($row + ['endpoint_hash' => $hash, 'created_at' => $now]);
        }
        return true;
    }

    public static function unsubscribe(string $endpoint): void
    {
        self::db()->table('push_subscriptions')->where('endpoint_hash', sha1($endpoint))->delete();
    }

    /**
     * Starts sending a message: to "all" browsers, or "user:5" (the browsers of one staff member, for a test).
     * @return int the message id
     */
    public static function start(string $title, string $body, string $url, string $image, string $audience = 'all'): int
    {
        $q = self::db()->table('push_subscriptions');
        if (str_starts_with($audience, 'user:')) {
            $q->where('user_id', (int) substr($audience, 5));
        }
        $total = $q->count();
        $id = self::db()->table('push_messages')->insertGetId([
            'title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 300) ?: null, 'url' => mb_substr($url, 0, 500) ?: null, 'image' => mb_substr($image, 0, 500) ?: null,
            'status' => $total > 0 ? 'sending' : 'sent', 'audience' => mb_substr($audience, 0, 12), 'total' => $total, 'sent' => 0, 'failed' => 0, 'clicks' => 0,
            'created_by' => auth_user()['id'] ?? null, 'created_at' => gmdate('Y-m-d H:i:s'), 'finished_at' => $total > 0 ? null : gmdate('Y-m-d H:i:s'),
        ]);
        if ($total > 0) {
            $queue = App::instance()->has(Queue::class) ? App::instance()->make(Queue::class) : null;
            if ($queue && $audience === 'all') {
                $queue->push('push.send', ['message_id' => $id, 'after' => 0], 0, 'push', 2);
            } else {
                self::sendBatch($id, 0); // a test, or no queue: send straight away
            }
        }
        return $id;
    }

    /** Sends to the next batch of browsers, then queues the batch after it. */
    public static function sendBatch(int $messageId, int $after): void
    {
        $m = self::db()->table('push_messages')->where('id', $messageId)->first();
        if (!$m || $m['status'] === 'cancelled') {
            return;
        }
        $pub = (string) setting('push.public_key', '');
        $priv = (string) setting('push.private_key', '');
        $q = self::db()->table('push_subscriptions')->where('id', '>', $after);
        if (str_starts_with((string) $m['audience'], 'user:')) {
            $q->where('user_id', (int) substr((string) $m['audience'], 5));
        }
        $subs = $q->orderBy('id')->limit(self::BATCH)->get();
        $payload = json_encode(self::payload($m), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sent = 0;
        $failed = 0;
        $last = $after;
        $stopped = false;
        foreach ($subs as $s) {
            if (\AP\Core\Scheduler::nearlyOutOfTime(3)) {
                $stopped = true; // the scheduler run is nearly over: the next run carries on from here
                break;
            }
            try {
                $r = WebPush::send($s, (string) $payload, self::subject(), $pub, $priv);
            } catch (Throwable $e) {
                $r = ['ok' => false, 'gone' => false, 'message' => $e->getMessage()];
            }
            if (!empty($r['skipped'])) {
                $stopped = true;
                break;
            }
            $last = (int) $s['id'];
            if ($r['ok']) {
                $sent++;
                if ((int) $s['failures'] > 0) {
                    self::db()->table('push_subscriptions')->where('id', $last)->update(['failures' => 0]);
                }
                continue;
            }
            $failed++;
            if ($r['gone'] || (int) $s['failures'] >= 4) {
                self::db()->table('push_subscriptions')->where('id', $last)->delete(); // the visitor switched notifications off
            } else {
                self::db()->table('push_subscriptions')->where('id', $last)->increment('failures');
            }
        }
        self::db()->table('push_messages')->where('id', $messageId)->update(['sent' => (int) $m['sent'] + $sent, 'failed' => (int) $m['failed'] + $failed]);
        if ($stopped || count($subs) === self::BATCH) {
            try {
                App::instance()->make(Queue::class)->push('push.send', ['message_id' => $messageId, 'after' => $last], $stopped ? 30 : 0, 'push', 2);
                return;
            } catch (Throwable $e) {
                Logger::exception($e);
            }
        }
        self::db()->table('push_messages')->where('id', $messageId)->update(['status' => 'sent', 'finished_at' => gmdate('Y-m-d H:i:s')]);
    }

    /** @return array<string,string> what the service worker shows */
    public static function payload(array $m): array
    {
        $url = trim((string) ($m['url'] ?? ''));
        if ($url === '') {
            $url = url('/');
        } elseif (!preg_match('#^https?://#i', $url)) {
            $url = url(ltrim($url, '/'));
        }
        $icons = AppSite::icons();
        $image = trim((string) ($m['image'] ?? ''));
        if ($image !== '' && !preg_match('#^https?://#i', $image)) {
            $image = url(ltrim($image, '/'));
        }
        return array_filter([
            'title' => (string) $m['title'], 'body' => (string) ($m['body'] ?? ''), 'url' => $url, 'image' => $image,
            'icon' => $icons[192] ?? '', 'badge' => $icons[192] ?? '', 'tag' => 'ap-' . (int) $m['id'], 'click' => url('push/click/' . (int) $m['id']),
        ], static fn($v) => $v !== '');
    }
}
