<?php
declare(strict_types=1);

namespace AP\Modules\Notifications;

use AP\Core\App;
use AP\Core\DB;
use AP\Core\Logger;
use Throwable;

/** The bell in the top bar: things you should know about. Other modules call Notifications::add(); it never throws. */
final class Notifications
{
    /** kind => [label, icon] */
    public const KINDS = [
        'error' => ['Errors and failed tasks', 'fa-bug'],
        'mail' => ['Emails that could not be sent', 'fa-envelope-circle-check'],
        'contact' => ['New contact messages', 'fa-inbox'],
        'money_in' => ['Payments received', 'fa-hand-holding-dollar'],
        'money_auto' => ['Repeating invoices and expenses made', 'fa-rotate'],
        'money_due' => ['Invoices that became overdue', 'fa-clock'],
        'money_mail' => ['Receipt emails imported', 'fa-envelope-open-text'],
    ];
    public const LEVELS = ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-xmark'];

    private static bool $busy = false;

    public static function db(): DB
    {
        /** @var DB $db */
        $db = App::instance()->make(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return \AP\Core\Kernel::schemaCurrent() && self::db()->hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Add a notification. $key stops the same thing being told twice (within a day, or ever when $once is true).
     * Returns false when switched off, repeated, or not possible; never throws.
     */
    public static function add(string $kind, string $level, string $title, string $body = '', string $url = '', ?string $key = null, bool $once = false): bool
    {
        if (self::$busy) {
            return false;
        }
        self::$busy = true;
        try {
            if (!self::ready() || !(bool) setting('notify.on', true) || !(bool) setting('notify.kind_' . $kind, true)) {
                return false;
            }
            $db = self::db();
            $key = $key !== null ? substr(md5($key), 0, 32) : null;
            if ($key !== null) {
                $q = $db->table('notifications')->where('dedupe', $key);
                if (!$once) {
                    $q->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - 86400));
                }
                if ($q->exists()) {
                    return false;
                }
            }
            $db->table('notifications')->insert(['kind' => substr($kind, 0, 20), 'level' => isset(self::LEVELS[$level]) ? $level : 'info', 'title' => mb_substr($title, 0, 255), 'body' => mb_substr($body, 0, 600) ?: null,
                'url' => $url !== '' ? mb_substr($url, 0, 500) : null, 'dedupe' => $key, 'created_at' => gmdate('Y-m-d H:i:s')]);
            return true;
        } catch (Throwable $e) {
            Logger::warning('Could not add a notification: ' . $e->getMessage());
            return false;
        } finally {
            self::$busy = false;
        }
    }

    /** @return list<array<string,mixed>> newest first, each with a 'read' flag for this person */
    public static function latest(int $userId, int $limit = 8, string $filter = 'all', int $offset = 0): array
    {
        if (!self::ready()) {
            return [];
        }
        $db = self::db();
        $q = $db->table('notifications')->orderBy('id', 'desc');
        if (in_array($filter, array_keys(self::KINDS), true)) {
            $q->where('kind', $filter);
        }
        $rows = $filter === 'unread' ? $q->limit(400)->get() : $q->limit($limit + $offset + 400)->get();
        $read = array_flip(array_map('intval', $db->table('notification_reads')->where('user_id', $userId)->pluck('notification_id')));
        foreach ($rows as &$r) {
            $r['read'] = isset($read[(int) $r['id']]);
        }
        unset($r);
        if ($filter === 'unread') {
            $rows = array_values(array_filter($rows, static fn($r) => !$r['read']));
        }
        return array_slice($rows, $offset, $limit);
    }

    public static function unread(int $userId): int
    {
        if (!self::ready()) {
            return 0;
        }
        $db = self::db();
        $ids = array_map('intval', $db->table('notifications')->orderBy('id', 'desc')->limit(500)->pluck('id'));
        if (!$ids) {
            return 0;
        }
        $read = $db->table('notification_reads')->where('user_id', $userId)->whereIn('notification_id', $ids)->count();
        return max(0, count($ids) - $read);
    }

    /** @param list<int>|null $ids null means all */
    public static function markRead(int $userId, ?array $ids = null): void
    {
        if (!self::ready() || $userId <= 0) {
            return;
        }
        $db = self::db();
        $all = $ids ?? array_map('intval', $db->table('notifications')->orderBy('id', 'desc')->limit(500)->pluck('id'));
        $have = array_flip(array_map('intval', $db->table('notification_reads')->where('user_id', $userId)->pluck('notification_id')));
        foreach ($all as $id) {
            if ($id > 0 && !isset($have[$id])) {
                $db->table('notification_reads')->insert(['notification_id' => $id, 'user_id' => $userId]);
            }
        }
    }

    public static function prune(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $db = self::db();
        $cut = gmdate('Y-m-d H:i:s', time() - max(1, (int) setting('notify.keep_days', 30)) * 86400);
        $old = array_map('intval', $db->table('notifications')->where('created_at', '<', $cut)->pluck('id'));
        if ($old) {
            $db->table('notification_reads')->whereIn('notification_id', $old)->delete();
            $db->table('notifications')->whereIn('id', $old)->delete();
        }
        return count($old);
    }

    /** "5 minutes ago" for the list. */
    public static function ago(string $utc): string
    {
        $s = max(0, time() - (int) strtotime($utc . ' UTC'));
        return match (true) {
            $s < 60 => 'Just now',
            $s < 3600 => (int) ($s / 60) . ' min ago',
            $s < 86400 => (int) ($s / 3600) . ' hr ago',
            $s < 7 * 86400 => (int) ($s / 86400) . ' day' . ((int) ($s / 86400) === 1 ? '' : 's') . ' ago',
            default => format_date($utc, 'd M Y'),
        };
    }

    /** The bell and its drop-down, for the top bar. */
    public static function bell(): string
    {
        $uid = (int) (auth_user()['id'] ?? 0);
        if ($uid <= 0 || !self::ready() || !(bool) setting('notify.on', true)) {
            return '';
        }
        $n = self::unread($uid);
        return '<div class="dropdown ap-bell" data-bell data-feed="' . e(admin_url('notifications/feed')) . '" data-poll="' . (int) max(0, (int) setting('notify.poll', 60)) . '">'
            . '<button class="ap-top-btn ap-bell-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications" title="Notifications"><i class="fa-regular fa-bell"></i>'
            . '<span class="ap-bell-count" data-bell-count' . ($n > 0 ? '' : ' hidden') . '>' . ($n > 99 ? '99+' : $n) . '</span></button>'
            . '<div class="dropdown-menu dropdown-menu-end ap-bell-menu" data-bell-menu>' . self::menuHtml($uid) . '</div></div>';
    }

    public static function menuHtml(int $uid): string
    {
        $rows = self::latest($uid, 8);
        $n = self::unread($uid);
        $h = '<div class="ap-bell-head"><strong>Notifications</strong>' . ($n > 0 ? '<form method="post" action="' . e(admin_url('notifications/read')) . '">' . csrf_field() . '<button class="ap-bell-link" type="submit">Mark all read</button></form>' : '') . '</div>';
        if (!$rows) {
            return $h . '<div class="ap-bell-empty"><i class="fa-regular fa-bell-slash"></i><span>You are all caught up.</span></div>' . self::footHtml();
        }
        $h .= '<div class="ap-bell-list">';
        foreach ($rows as $r) {
            $h .= self::itemHtml($r);
        }
        return $h . '</div>' . self::footHtml();
    }

    private static function footHtml(): string
    {
        return '<div class="ap-bell-foot"><a href="' . e(admin_url('notifications')) . '">See Everything</a><a href="' . e(admin_url('settings/notifications')) . '">Settings</a></div>';
    }

    public static function itemHtml(array $r): string
    {
        return '<a class="ap-bell-item is-' . e((string) $r['level']) . (!empty($r['read']) ? '' : ' is-new') . '" href="' . e(admin_url('notifications/' . (int) $r['id'] . '/open')) . '">'
            . '<i class="fa-solid ' . e(self::LEVELS[$r['level']] ?? 'fa-circle-info') . '" aria-hidden="true"></i>'
            . '<span><strong>' . e((string) $r['title']) . '</strong>' . ($r['body'] ? '<small>' . e((string) $r['body']) . '</small>' : '') . '<em>' . e(self::ago((string) $r['created_at'])) . '</em></span></a>';
    }
}
