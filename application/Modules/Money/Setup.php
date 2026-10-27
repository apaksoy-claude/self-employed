<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\DB;
use AP\Core\Request;
use AP\Core\Settings;

/**
 * Sets Money up for the kind of work someone does, from the installer or from Money > Business Setup.
 * It only ever adds: categories and income streams that already exist (by name, ignoring case) are left alone,
 * and payment options are added to the end of the lists. "Fresh Start" adds nothing but still records the answers.
 */
final class Setup
{
    public const SETTING = 'money.setup';

    /** The starting "Paid by" (money in) and "Paid with" (money out) choices, before anyone changes them. */
    public const PAID_BY = ['Bank Transfer', 'Cash', 'Card', 'Stripe', 'PayPal'];
    public const PAID_WITH = ['Card', 'Bank Transfer', 'Cash', 'Direct Debit'];

    /** What was chosen last time: mode, trades, vehicle, home and when. Empty when setup has never been run. @return array<string,mixed> */
    public static function saved(?Settings $settings = null): array
    {
        $raw = ($settings ?? settings())->get(self::SETTING, '');
        $v = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($v) ? $v : [];
    }

    /** Whether vehicles and mileage are shown (Settings > Money > Features). */
    public static function usesVehicles(): bool
    {
        return (bool) setting('money.feature_vehicles', true);
    }

    /**
     * The answers in a posted form.
     * @return array{mode:string,trades:list<string>,vehicle:bool,home:bool,dashboard:bool,skip:array<string,list<string>>,business:array{name:string,email:string}|null}
     */
    public static function fromRequest(Request $request, bool $withBusiness = false): array
    {
        $skip = [];
        foreach (['categories' => 'skip_cat', 'streams' => 'skip_stream', 'paid_by' => 'skip_by', 'paid_with' => 'skip_with'] as $k => $field) {
            $skip[$k] = array_values(array_unique(array_map(static fn($v) => mb_strtolower(trim((string) $v)), array_filter((array) $request->input($field, []), 'is_scalar'))));
        }
        $on = static fn(string $k): bool => in_array((string) $request->input($k, '0'), ['1', 'on', 'true'], true);
        return [
            'mode' => $request->string('mode') === 'fresh' ? 'fresh' : 'ready',
            'trades' => Trades::clean((array) $request->input('trades', [])),
            'vehicle' => $on('vehicle'), 'home' => $on('home'), 'dashboard' => $on('dashboard'), 'skip' => $skip,
            'business' => $withBusiness ? ['name' => mb_substr(trim($request->string('biz_name')), 0, 120), 'email' => mb_substr(mb_strtolower(trim($request->string('biz_email'))), 0, 190)] : null,
        ];
    }

    /**
     * What the installer's Your Business page shows: the answers so far (or sensible starting ones) and the plan data.
     * @param array<string,mixed> $state the installer's answers so far
     * @return array<string,mixed>
     */
    public static function installData(array $state): array
    {
        $choice = (array) ($state['steps']['business'] ?? []) + [
            'mode' => 'ready', 'trades' => [], 'vehicle' => false, 'home' => false, 'dashboard' => true,
            'business' => ['name' => (string) ($state['site']['name'] ?? ''), 'email' => (string) ($state['admin']['email'] ?? '')],
        ];
        $existing = ['categories' => [], 'streams' => [], 'paid_by' => array_map('mb_strtolower', self::PAID_BY), 'paid_with' => array_map('mb_strtolower', self::PAID_WITH)];
        return ['choice' => $choice, 'planJson' => self::planData($existing), 'vehicleTouched' => isset($state['steps']['business'])];
    }

    /** The installer's Your Business answers, checked. @return array{errors:array<string,list<string>>,value:array<string,mixed>} */
    public static function installSave(Request $request): array
    {
        $choice = self::fromRequest($request, true);
        return ['errors' => self::validate($choice), 'value' => $choice];
    }

    /** @param array<string,mixed> $choice @return array<string,list<string>> problems, by field */
    public static function validate(array $choice): array
    {
        $errors = [];
        if (($choice['mode'] ?? 'ready') === 'ready' && empty($choice['trades'])) {
            $errors['trades'] = ['Pick at least one kind of work, or choose Fresh Start.'];
        }
        $email = (string) ($choice['business']['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['biz_email'] = ['Please type a full email address, such as you@example.com.'];
        }
        return $errors;
    }

    /**
     * What will be added for this choice once the ticked-off items and what already exists are left out.
     * @param array<string,mixed> $choice
     * @param array<string,list<string>> $existing lower-case names already there, by kind
     * @return array{categories:list<array<string,mixed>>,streams:list<array<string,mixed>>,paid_by:list<string>,paid_with:list<string>,layout:list<array<string,mixed>>}
     */
    public static function toAdd(array $choice, array $existing = []): array
    {
        if (($choice['mode'] ?? 'ready') === 'fresh') {
            return ['categories' => [], 'streams' => [], 'paid_by' => [], 'paid_with' => [], 'layout' => []];
        }
        $plan = Trades::plan((array) ($choice['trades'] ?? []), !empty($choice['vehicle']), !empty($choice['home']));
        $skip = (array) ($choice['skip'] ?? []);
        $keep = static function (string $kind, string $name) use ($skip, $existing): bool {
            $n = mb_strtolower($name);
            return !in_array($n, (array) ($skip[$kind] ?? []), true) && !in_array($n, (array) ($existing[$kind] ?? []), true);
        };
        return [
            'categories' => array_values(array_filter($plan['categories'], static fn($c) => $keep('categories', $c['name']))),
            'streams' => array_values(array_filter($plan['streams'], static fn($s) => $keep('streams', $s['name']))),
            'paid_by' => array_values(array_filter($plan['paid_by'], static fn($p) => $keep('paid_by', $p))),
            'paid_with' => array_values(array_filter($plan['paid_with'], static fn($p) => $keep('paid_with', $p))),
            'layout' => !empty($choice['dashboard']) ? $plan['layout'] : [],
        ];
    }

    /**
     * Lower-case names of what is already there, so nothing is added twice.
     * @return array{categories:list<string>,streams:list<string>,paid_by:list<string>,paid_with:list<string>}
     */
    public static function existing(DB $db, Settings $settings): array
    {
        $lower = static fn(array $names): array => array_values(array_unique(array_map(static fn($n) => mb_strtolower(trim((string) $n)), $names)));
        $cats = $streams = [];
        try {
            $cats = $db->table('money_categories')->where('kind', 'expense')->pluck('name');
            $streams = $db->table('money_streams')->pluck('name');
        } catch (\Throwable) {
            // The tables are made by the installer just before this runs; during the questions they do not exist yet.
        }
        return ['categories' => $lower($cats), 'streams' => $lower($streams), 'paid_by' => $lower(self::labels($settings, 'money.paid_by', self::PAID_BY)),
            'paid_with' => $lower(self::labels($settings, 'money.paid_with', self::PAID_WITH))];
    }

    /**
     * Adds what was chosen and remembers the answers. Safe to run again: nothing is added twice.
     * @param array<string,mixed> $choice from fromRequest()
     * @return array{categories:int,streams:int,paid_by:int,paid_with:int,dashboard:bool,vehicle:bool}
     */
    public static function apply(DB $db, Settings $settings, array $choice, ?int $userId = null): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $add = self::toAdd($choice, self::existing($db, $settings));
        $order = (int) $db->table('money_categories')->where('kind', 'expense')->orderBy('sort_order', 'desc')->value('sort_order');
        foreach ($add['categories'] as $c) {
            $db->table('money_categories')->insert(['kind' => 'expense', 'name' => $c['name'], 'hmrc_box' => $c['box'], 'allowable' => $c['allowable'], 'with_mileage' => $c['with_mileage'],
                'sort_order' => ++$order, 'active' => 1, 'created_at' => $now]);
        }
        $sOrder = (int) $db->table('money_streams')->orderBy('sort_order', 'desc')->value('sort_order');
        foreach ($add['streams'] as $s) {
            $db->table('money_streams')->insert(['name' => $s['name'], 'colour' => $s['colour'], 'sort_order' => ++$sOrder, 'active' => 1, 'created_at' => $now]);
        }
        foreach (['paid_by' => self::PAID_BY, 'paid_with' => self::PAID_WITH] as $kind => $default) {
            if ($add[$kind]) {
                $rows = array_map(static fn($l) => ['label' => $l], array_merge(self::labels($settings, 'money.' . $kind, $default), $add[$kind]));
                $settings->set('money.' . $kind, $rows, $userId);
            }
        }
        $dashboard = false;
        if ($add['layout']) {
            $ids = [];
            foreach ($db->table('money_categories')->where('kind', 'expense')->select('id', 'name')->get() as $r) {
                $ids[mb_strtolower((string) $r['name'])] ??= (int) $r['id'];
            }
            $layout = [];
            foreach ($add['layout'] as $w) {
                if (isset($w['category'])) {
                    $id = $ids[mb_strtolower((string) $w['category'])] ?? 0;
                    if ($id === 0) {
                        continue;
                    }
                    $w['category_id'] = $id;
                    unset($w['category']);
                }
                $clean = Widgets::clean($w);
                if ($clean) {
                    $layout[] = $clean;
                }
            }
            $settings->set(Widgets::SETTING, json_encode($layout, JSON_UNESCAPED_UNICODE), $userId);
            $dashboard = true;
        }
        $vehicle = !empty($choice['vehicle']);
        $settings->set('money.feature_vehicles', $vehicle ? '1' : '0', $userId);
        $biz = (array) ($choice['business'] ?? []);
        foreach (['name', 'email'] as $k) {
            if (trim((string) ($biz[$k] ?? '')) !== '') {
                $settings->set('money.biz_' . $k, trim((string) $biz[$k]), $userId);
            }
        }
        $before = self::saved($settings);
        $trades = array_values(array_unique(array_merge((array) ($before['trades'] ?? []), (array) ($choice['trades'] ?? []))));
        $settings->set(self::SETTING, json_encode(['mode' => ($before['mode'] ?? '') === 'ready' ? 'ready' : (string) ($choice['mode'] ?? 'ready'), 'trades' => Trades::clean($trades),
            'vehicle' => $vehicle, 'home' => !empty($choice['home']) || !empty($before['home']), 'at' => $now]), $userId);
        return ['categories' => count($add['categories']), 'streams' => count($add['streams']), 'paid_by' => count($add['paid_by']), 'paid_with' => count($add['paid_with']),
            'dashboard' => $dashboard, 'vehicle' => $vehicle];
    }

    /** One sentence about what a choice adds, for the installer's last page. @param array<string,mixed> $choice */
    public static function describe(array $choice): string
    {
        $veh = !empty($choice['vehicle']) ? 'Vehicles and mileage shown.' : 'Vehicles and mileage hidden.';
        if (($choice['mode'] ?? 'ready') === 'fresh') {
            return 'Fresh start: nothing added. ' . $veh;
        }
        $add = self::toAdd($choice, ['paid_by' => array_map('mb_strtolower', self::PAID_BY), 'paid_with' => array_map('mb_strtolower', self::PAID_WITH)]);
        $n = static fn(int $c, string $one, string $many): string => $c . ' ' . ($c === 1 ? $one : $many);
        return 'Ready for ' . Trades::names((array) $choice['trades']) . ': ' . $n(count($add['categories']), 'category', 'categories') . ', ' . $n(count($add['streams']), 'income stream', 'income streams')
            . ($add['layout'] ? ', dashboard cards to suit' : '') . '. ' . $veh;
    }

    /** The result of apply() in words. @param array<string,mixed> $r */
    public static function result(array $r): string
    {
        $parts = [];
        foreach (['categories' => ['category', 'categories'], 'streams' => ['income stream', 'income streams'], 'paid_by' => ['way of getting paid', 'ways of getting paid'], 'paid_with' => ['way of paying', 'ways of paying']] as $k => [$one, $many]) {
            if (($r[$k] ?? 0) > 0) {
                $parts[] = $r[$k] . ' ' . ($r[$k] === 1 ? $one : $many);
            }
        }
        $text = $parts ? 'Added ' . implode(', ', $parts) . '.' : 'Nothing new to add: you already have all of it.';
        return $text . (!empty($r['dashboard']) ? ' The dashboard cards are set for your kind of work.' : '') . (!empty($r['vehicle']) ? ' Vehicles and Mileage Log are in the menu.' : ' Vehicles and Mileage Log are hidden.');
    }

    /**
     * Everything the browser needs to show the same plan as plan() while the person ticks and unticks, as JSON.
     * @param array<string,list<string>> $existing
     */
    public static function planData(array $existing): string
    {
        $part = static fn(array $p): array => ['label' => $p['label'], 'icon' => (string) ($p['icon'] ?? 'fa-tag'), 'categories' => array_map(static fn($c) => ['name' => (string) $c[0], 'box' => (string) $c[1],
            'cost' => !in_array((string) $c[1], Money::NOT_COSTS, true) && ($c['allowable'] ?? true)], (array) ($p['categories'] ?? [])),
            'streams' => array_map(static fn($s) => ['name' => (string) $s[0], 'colour' => (string) $s[1]], (array) ($p['streams'] ?? [])),
            'paid_by' => (array) ($p['paid_by'] ?? []), 'paid_with' => (array) ($p['paid_with'] ?? []), 'vehicle' => !empty($p['vehicle'])];
        $packs = [];
        foreach (Trades::PACKS as $k => $p) {
            $packs[$k] = $part($p);
        }
        return (string) json_encode(['packs' => $packs, 'vehicle' => $part(Trades::VEHICLE), 'home' => $part(Trades::HOME), 'base' => $part(Trades::BASE),
            'boxes' => Money::BOX_SHORT, 'existing' => $existing], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** A list setting's labels, whether stored as rows, JSON or not stored yet. @param list<string> $default @return list<string> */
    private static function labels(Settings $settings, string $key, array $default): array
    {
        $v = $settings->has($key) ? $settings->get($key, null) : null;
        if (is_string($v)) {
            $v = json_decode($v, true);
        }
        if (!is_array($v)) {
            return $default;
        }
        $out = [];
        foreach ($v as $r) {
            $l = trim((string) (is_array($r) ? ($r['label'] ?? '') : $r));
            if ($l !== '') {
                $out[] = $l;
            }
        }
        return $out;
    }
}
