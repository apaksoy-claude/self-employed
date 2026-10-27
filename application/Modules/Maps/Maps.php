<?php
declare(strict_types=1);

namespace AP\Modules\Maps;

use AP\Core\View;
use AP\Core\Http;
use Throwable;

/**
 * Maps on the website, from an ordered list of map services: the first one that is on and ready is used, and when it
 * cannot load in the visitor's browser the next one takes over. Put a map on a page with [map] (or [map zoom="15" height="300"]),
 * or with the "Map" block in the footer builder. In PHP: Maps::render(['zoom' => 14]).
 */
final class Maps
{
    /** @var null|callable(string):array{code:int,body:string,error:string} replaced in tests */
    public static $fetcher = null;

    private static bool $assets = false;

    public const SERVICES = [
        'carto' => ['label' => 'CARTO Light', 'icon' => 'fa-map', 'kind' => 'tiles', 'key' => '', 'free' => true,
            'tiles' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', 'attr' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'help' => 'A calm, light map that suits most websites. No key needed (free for small and medium sites).', 'signup' => 'https://carto.com/basemaps'],
        'osm' => ['label' => 'OpenStreetMap', 'icon' => 'fa-map-location-dot', 'kind' => 'tiles', 'key' => '', 'free' => true,
            'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'attr' => '&copy; OpenStreetMap contributors',
            'help' => 'The classic, colourful street map. No key needed; very busy websites should use a paid service.', 'signup' => 'https://operations.osmfoundation.org/policies/tiles/'],
        'maptiler' => ['label' => 'MapTiler', 'icon' => 'fa-layer-group', 'kind' => 'tiles', 'key' => 'maps.maptiler_key', 'free' => false,
            'tiles' => 'https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key={key}', 'attr' => '&copy; MapTiler &copy; OpenStreetMap contributors',
            'help' => 'Detailed street maps with a free plan. Needs a key.', 'signup' => 'https://cloud.maptiler.com/account/keys/'],
        'mapbox' => ['label' => 'Mapbox', 'icon' => 'fa-cube', 'kind' => 'tiles', 'key' => 'maps.mapbox_token', 'free' => false,
            'tiles' => 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}@2x?access_token={key}', 'attr' => '&copy; Mapbox &copy; OpenStreetMap contributors',
            'help' => 'Sharp street maps with a free monthly allowance. Needs a public access token (it starts with pk.).', 'signup' => 'https://account.mapbox.com/access-tokens/'],
        'google' => ['label' => 'Google Maps', 'icon' => 'fa-location-dot', 'kind' => 'google', 'key' => 'maps.google_key', 'free' => false,
            'help' => 'The map most people know. Needs a key with the "Maps JavaScript API" switched on, and a billing account at Google.', 'signup' => 'https://console.cloud.google.com/google/maps-apis/credentials'],
    ];

    /** The services in their chosen order, each with "on", "ready" (on and has the key it needs) and the key. */
    public static function services(): array
    {
        $saved = json_decode((string) setting('maps.services'), true);
        $order = [];
        foreach (is_array($saved) ? $saved : [] as $s) {
            if (is_array($s) && isset(self::SERVICES[$s['id'] ?? ''])) {
                $order[(string) $s['id']] = !empty($s['on']);
            }
        }
        foreach (self::SERVICES as $id => $d) {
            $order += [$id => in_array($id, ['carto', 'osm'], true)];
        }
        $out = [];
        foreach ($order as $id => $on) {
            $d = self::SERVICES[$id];
            $key = $d['key'] !== '' ? trim((string) setting($d['key'])) : '';
            $out[$id] = $d + ['id' => $id, 'on' => $on, 'value' => $key, 'ready' => $on && ($d['key'] === '' || $key !== '')];
        }
        return $out;
    }

    /** @return list<array{id:string,kind:string,tiles?:string,attr?:string,key?:string}> what the browser needs, in order */
    public static function chain(): array
    {
        $out = [];
        foreach (self::services() as $id => $s) {
            if (!$s['ready']) {
                continue;
            }
            $out[] = $s['kind'] === 'google' ? ['id' => $id, 'kind' => 'google', 'key' => $s['value']]
                : ['id' => $id, 'kind' => 'tiles', 'tiles' => str_replace('{key}', rawurlencode($s['value']), $s['tiles']), 'attr' => $s['attr']];
        }
        return $out;
    }

    public static function first(): ?array
    {
        foreach (self::services() as $s) {
            if ($s['ready']) {
                return $s;
            }
        }
        return null;
    }

    public static function usesGoogle(): bool
    {
        return (bool) array_filter(self::chain(), static fn($s) => $s['kind'] === 'google');
    }

    public static function boot(): void
    {
        events()->addFilter('pages.content', static function (string $html): string {
            if (!str_contains($html, '[map')) {
                return $html;
            }
            return (string) preg_replace_callback('#(<p>\s*)?\[map((?:\s+[a-z]+="[^"]*")*)\s*\](\s*</p>)?#', static function (array $m): string {
                preg_match_all('#([a-z]+)="([^"]*)"#', $m[2], $attrs, PREG_SET_ORDER);
                $o = [];
                foreach ($attrs as $a) {
                    $o[$a[1]] = html_entity_decode($a[2], ENT_QUOTES);
                }
                return self::render($o);
            }, $html);
        });
        events()->addFilter('editor.inserts', static fn(array $list): array => [...$list, ['label' => 'Insert a map', 'icon' => 'fa-map-location-dot', 'html' => '<p>[map]</p>']]);
        events()->addFilter('footer.blocks', static fn(array $b): array => $b + ['map' => ['label' => 'Map', 'icon' => 'fa-map-location-dot', 'help' => 'A small map of the place set in Settings > Maps.', 'fields' => [
            ['key' => 'title', 'label' => 'Heading', 'type' => 'text', 'placeholder' => 'Optional', 'max' => 80],
            ['key' => 'height', 'label' => 'Height (pixels)', 'type' => 'number', 'min' => 120, 'max' => 600, 'default' => 200, 'half' => true],
            ['key' => 'zoom', 'label' => 'Zoom (1 to 19)', 'type' => 'number', 'min' => 1, 'max' => 19, 'default' => 14, 'half' => true],
        ]]]);
        events()->addFilter('footer.block', static fn(string $html, array $b): string => ($b['type'] ?? '') === 'map'
            ? self::render(['height' => (string) ($b['height'] ?? 200), 'zoom' => (string) ($b['zoom'] ?? 14), 'directions' => '0', 'class' => 'ap-map-footer']) : $html);
        events()->addFilter('csp.policy', static function (string $csp, $request = null): string {
            if (!self::usesGoogle() || ($request && str_starts_with((string) $request->path, '/' . admin_path()))) {
                return $csp;
            }
            $hosts = ' https://maps.googleapis.com https://maps.gstatic.com';
            $csp = (string) preg_replace('/script-src ([^;]*)/', 'script-src $1' . $hosts, $csp, 1);
            return (string) preg_replace('/connect-src ([^;]*)/', 'connect-src $1' . $hosts, $csp, 1);
        });
    }

    /** A map for the website. Options: lat, lng, zoom, height, label, marker ("0"/"1"), directions ("0"/"1"), class. */
    public static function render(array $o = []): string
    {
        $chain = self::chain();
        if (!$chain) {
            return '';
        }
        $num = static fn(string $k, float $min, float $max, float $def): float => is_numeric($o[$k] ?? null) ? max($min, min($max, (float) $o[$k])) : $def;
        $lat = $num('lat', -90, 90, (float) setting('maps.lat'));
        $lng = $num('lng', -180, 180, (float) setting('maps.lng'));
        $zoom = (int) $num('zoom', 1, 19, (float) setting('maps.zoom'));
        $height = (int) $num('height', 120, 1200, (float) setting('maps.height'));
        $label = trim((string) ($o['label'] ?? setting('maps.label')));
        $bool = static fn(string $k, string $setting): bool => isset($o[$k]) ? !in_array((string) $o[$k], ['0', 'no', 'false', ''], true) : (bool) setting($setting);
        $cfg = ['lat' => $lat, 'lng' => $lng, 'zoom' => $zoom, 'label' => mb_substr($label, 0, 120), 'marker' => $bool('marker', 'maps.marker'),
            'scroll' => (bool) setting('maps.scroll_zoom'), 'services' => $chain, 'failed' => __('site.map_failed')];
        $json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $html = '';
        if (!self::$assets) {
            self::$assets = true;
            $html .= '<link rel="stylesheet" href="' . e(asset('lib/leaflet/leaflet.css')) . '">';
            View::inject('body_end', static fn(): string => '<script nonce="' . e(csp_nonce()) . '" src="' . e(asset('lib/leaflet/leaflet.js')) . '"></script>'
                . '<script nonce="' . e(csp_nonce()) . '" src="' . e(asset('js/maps.js')) . '?v=' . e(AP_VERSION) . '"></script>', 95);
        }
        $class = preg_replace('/[^a-z0-9_ -]/i', '', (string) ($o['class'] ?? '')) ?? '';
        $html .= '<div class="ap-map-wrap ' . e($class) . '"><div class="ap-map" style="height:' . $height . 'px" data-ap-map="' . e($json) . '" role="region" aria-label="' . e($label !== '' ? $label : __('site.map')) . '"></div>';
        if ($bool('directions', 'maps.directions')) {
            $html .= '<a class="ap-map-directions" href="' . e('https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lng) . '" target="_blank" rel="noopener"><i class="fa-solid fa-route me-1" aria-hidden="true"></i>' . e(__('site.directions')) . '</a>';
        }
        return $html . '</div>';
    }

    /** For tests: let the next render() add the scripts again. */
    public static function reset(): void
    {
        self::$assets = false;
    }

    /* ------------------------------------------------------------------ admin helpers */

    private static function get(string $url): array
    {
        if (self::$fetcher) {
            return (self::$fetcher)($url);
        }
        $r = Http::request($url, ['timeout' => 12, 'connect_timeout' => 6, 'max_redirects' => 3, 'user_agent' => 'AP Framework/' . AP_VERSION . ' (+' . url('/') . ')', 'headers' => ['Referer: ' . url('/')]]);
        return ['code' => $r['code'], 'body' => $r['body'], 'error' => $r['skipped'] ? 'it took too long' : $r['error']];
    }

    /** Checks a key with the service. @return array{ok:bool,message:string} */
    public static function test(string $id, string $key): array
    {
        $key = trim($key);
        if (!isset(self::SERVICES[$id])) {
            return ['ok' => false, 'message' => 'Unknown service.'];
        }
        if (self::SERVICES[$id]['key'] !== '' && $key === '') {
            return ['ok' => false, 'message' => 'Type the key first.'];
        }
        $url = match ($id) {
            'google' => 'https://maps.googleapis.com/maps/api/staticmap?center=0,0&zoom=1&size=1x1&key=' . rawurlencode($key),
            'carto' => 'https://a.basemaps.cartocdn.com/light_all/1/0/0.png',
            'osm' => 'https://tile.openstreetmap.org/1/0/0.png',
            default => str_replace(['{z}', '{x}', '{y}', '{key}', '{s}', '{r}'], ['1', '0', '0', rawurlencode($key), 'a', ''], (string) self::SERVICES[$id]['tiles']),
        };
        try {
            $r = self::get($url);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the service: ' . $e->getMessage()];
        }
        if ($r['code'] === 0) {
            return ['ok' => false, 'message' => 'Could not reach the service' . ($r['error'] !== '' ? ': ' . $r['error'] : '.')];
        }
        if ($r['code'] >= 200 && $r['code'] < 300) {
            return ['ok' => true, 'message' => self::SERVICES[$id]['key'] === '' ? 'Working.' : 'The key works.'];
        }
        $why = trim(strip_tags(mb_substr($r['body'], 0, 300)));
        $hint = match (true) {
            $r['code'] === 401, $r['code'] === 403 => $id === 'google' ? 'Google refused the key. Check that the Maps JavaScript and Maps Static APIs are on, billing is set up, and your website address is allowed for this key.' : 'The service refused the key. Check it was copied in full and is allowed for your website address.',
            $r['code'] === 429 => 'Too many requests for now. Try again in a few minutes.',
            default => 'The service answered ' . $r['code'] . '.',
        };
        return ['ok' => false, 'message' => $hint . ($why !== '' && $r['code'] !== 403 ? ' (' . mb_substr($why, 0, 140) . ')' : '')];
    }

    /** Finds places for an address, with Google when it is on and has a key, otherwise OpenStreetMap. @return array{results:list<array{label:string,lat:float,lng:float}>,error:string,source:string} */
    public static function find(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return ['results' => [], 'error' => 'Type at least 3 letters.', 'source' => ''];
        }
        $google = self::services()['google'] ?? null;
        $useGoogle = $google && $google['on'] && $google['value'] !== '';
        $url = $useGoogle
            ? 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode($q) . '&key=' . rawurlencode($google['value'])
            : 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=6&addressdetails=0&q=' . rawurlencode($q);
        try {
            $r = self::get($url);
        } catch (Throwable $e) {
            return ['results' => [], 'error' => 'The address search could not be reached.', 'source' => ''];
        }
        $data = json_decode($r['body'], true);
        if ($r['code'] !== 200 || !is_array($data)) {
            return ['results' => [], 'error' => 'The address search could not be reached' . ($r['error'] !== '' ? ' (' . $r['error'] . ')' : '') . '. You can still click the map or type the position.', 'source' => ''];
        }
        $out = [];
        if ($useGoogle) {
            if (($data['status'] ?? '') !== 'OK' && ($data['status'] ?? '') !== 'ZERO_RESULTS') {
                return ['results' => [], 'error' => 'Google said: ' . ($data['error_message'] ?? $data['status'] ?? 'no answer') . ' (the Geocoding API must be on for this key).', 'source' => 'google'];
            }
            foreach (array_slice((array) ($data['results'] ?? []), 0, 6) as $g) {
                $out[] = ['label' => (string) ($g['formatted_address'] ?? ''), 'lat' => (float) ($g['geometry']['location']['lat'] ?? 0), 'lng' => (float) ($g['geometry']['location']['lng'] ?? 0)];
            }
        } else {
            foreach ($data as $n) {
                if (is_array($n) && isset($n['lat'], $n['lon'])) {
                    $out[] = ['label' => (string) ($n['display_name'] ?? ''), 'lat' => (float) $n['lat'], 'lng' => (float) $n['lon']];
                }
            }
        }
        return ['results' => $out, 'error' => $out ? '' : 'Nothing found for that address.', 'source' => $useGoogle ? 'google' : 'osm'];
    }
}
