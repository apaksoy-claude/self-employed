<?php
declare(strict_types=1);

namespace AP\Modules\Settings;

use AP\Modules\Admin\Preferences;

/**
 * How one person likes a settings page arranged: the order of the cards, which cards sit half width side by side, and the order and
 * width of the fields inside each card. Kept per person and per page; with nothing saved every card and field is full width.
 */
final class Layout
{
    private const PREF = 'settings_layout';

    /** @return array{order:list<string>,cards:array<string,int>,fields:array<string,string>,forder:array<string,list<string>>} */
    public static function for(string $group): array
    {
        $uid = (int) (auth_user()['id'] ?? 0);
        $all = $uid > 0 ? (array) Preferences::get($uid, self::PREF, []) : [];
        return self::clean((array) ($all[$group] ?? []));
    }

    public static function save(string $group, array $layout): void
    {
        $uid = (int) (auth_user()['id'] ?? 0);
        if ($uid <= 0) {
            return;
        }
        $all = (array) Preferences::get($uid, self::PREF, []);
        $all[$group] = self::clean($layout);
        Preferences::set($uid, self::PREF, $all);
    }

    public static function reset(string $group): void
    {
        $uid = (int) (auth_user()['id'] ?? 0);
        if ($uid <= 0) {
            return;
        }
        $all = (array) Preferences::get($uid, self::PREF, []);
        unset($all[$group]);
        Preferences::set($uid, self::PREF, $all);
    }

    /** Card address from its title, so a card keeps its place when other cards come and go. */
    public static function cardKey(string $title): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'card';
    }

    /** Puts the cards of a page (title => fields) in the person's order; cards they never moved keep their place at the end. */
    public static function order(array $sections, array $layout): array
    {
        $by = [];
        foreach ($sections as $title => $defs) {
            $by[self::cardKey((string) $title)] = (string) $title;
        }
        $out = [];
        foreach ($layout['order'] as $k) {
            if (isset($by[$k])) {
                $out[$by[$k]] = $sections[$by[$k]];
                unset($by[$k]);
            }
        }
        foreach ($by as $title) {
            $out[$title] = $sections[$title];
        }
        return $out;
    }

    /** Puts the fields of one card in the person's order. @param array<string,array> $defs */
    public static function orderFields(array $defs, array $layout, string $cardKey): array
    {
        $out = [];
        foreach ($layout['forder'][$cardKey] ?? [] as $k) {
            if (isset($defs[$k])) {
                $out[$k] = $defs[$k];
            }
        }
        return $out + $defs;
    }

    /** Anything unexpected in saved or posted layouts is dropped. */
    public static function clean(array $in): array
    {
        $key = static fn($k): string => preg_match('/^[a-z0-9_.-]{1,120}$/', (string) $k) ? (string) $k : '';
        $out = ['order' => [], 'cards' => [], 'fields' => [], 'forder' => []];
        foreach (array_slice((array) ($in['order'] ?? []), 0, 100) as $k) {
            if (($k = $key($k)) !== '' && !in_array($k, $out['order'], true)) {
                $out['order'][] = $k;
            }
        }
        foreach (array_slice((array) ($in['cards'] ?? []), 0, 100, true) as $k => $v) {
            if (($k = $key($k)) !== '') {
                $out['cards'][$k] = (int) $v === 2 ? 2 : 1;
            }
        }
        foreach (array_slice((array) ($in['fields'] ?? []), 0, 600, true) as $k => $v) {
            if (($k = $key($k)) !== '') {
                $out['fields'][$k] = $v === 'half' ? 'half' : 'full';
            }
        }
        foreach (array_slice((array) ($in['forder'] ?? []), 0, 100, true) as $c => $list) {
            if (($c = $key($c)) === '' || !is_array($list)) {
                continue;
            }
            foreach (array_slice($list, 0, 200) as $k) {
                if (($k = $key($k)) !== '' && !in_array($k, $out['forder'][$c] ?? [], true)) {
                    $out['forder'][$c][] = $k;
                }
            }
        }
        return $out;
    }
}
