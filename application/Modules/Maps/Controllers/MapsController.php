<?php
declare(strict_types=1);

namespace AP\Modules\Maps\Controllers;

use AP\Core\Activity;
use AP\Core\Http;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Maps\Maps;

final class MapsController
{
    public function index(Request $request): Response
    {
        return view('maps::index', [
            'title' => 'Maps', 'breadcrumbs' => ['Settings' => admin_url('settings'), 'Maps' => null],
            'about' => 'The map services your website uses, in order: the first one that is ready shows the map, and if it cannot load the next one takes over.',
            'services' => Maps::services(), 'first' => Maps::first(), 'canEdit' => can('settings.edit'),
        ]);
    }

    public function save(Request $request): Response
    {
        $errors = [];
        $order = [];
        foreach ((array) ($request->post['service'] ?? []) as $id => $row) {
            if (!isset(Maps::SERVICES[$id]) || !is_array($row)) {
                continue;
            }
            $order[] = ['id' => (string) $id, 'on' => ($row['on'] ?? '0') === '1'];
            $keyName = Maps::SERVICES[$id]['key'];
            if ($keyName !== '') {
                $key = trim((string) ($row['key'] ?? ''));
                if ($key !== '' && !preg_match('/^[A-Za-z0-9._\-]{8,300}$/', $key)) {
                    $errors[] = 'The ' . Maps::SERVICES[$id]['label'] . ' key has characters a key cannot have. Copy it again.';
                    continue;
                }
                settings()->set($keyName, $key);
            }
        }
        $num = static fn(string $k, float $min, float $max): ?float => is_numeric($request->post[$k] ?? null) && (float) $request->post[$k] >= $min && (float) $request->post[$k] <= $max ? (float) $request->post[$k] : null;
        $lat = $num('lat', -90, 90);
        $lng = $num('lng', -180, 180);
        if ($lat === null || $lng === null) {
            $errors[] = 'The position needs a latitude between -90 and 90 and a longitude between -180 and 180.';
        } else {
            settings()->set('maps.lat', (string) round($lat, 7));
            settings()->set('maps.lng', (string) round($lng, 7));
        }
        settings()->set('maps.services', json_encode($order) ?: '');
        settings()->set('maps.zoom', (int) ($num('zoom', 1, 19) ?? 14));
        settings()->set('maps.height', (int) ($num('height', 120, 1200) ?? 360));
        settings()->set('maps.lookup_wait', (int) ($num('lookup_wait', 2, 20) ?? 6));
        settings()->set('maps.label', mb_substr(trim($request->string('label')), 0, 120));
        settings()->set('maps.address', mb_substr(trim($request->string('address')), 0, 300));
        foreach (['marker', 'scroll_zoom', 'directions'] as $k) {
            settings()->set('maps.' . $k, $request->string($k) === '1');
        }
        Activity::log('maps.save', 'Map settings saved', 'settings', 'maps', [], ['services' => implode(', ', array_map(static fn($s) => $s['id'] . ($s['on'] ? '' : ' (off)'), $order))]);
        if ($errors) {
            session()->toast('warning', implode(' ', $errors) . ' Everything else was saved.');
        } else {
            session()->toast('success', Maps::first() ? 'Map settings saved.' : 'Saved, but no map service is ready, so no maps show. Switch one on.');
        }
        return redirect(admin_url('maps'));
    }

    public function test(Request $request): Response
    {
        return $this->withBudget(static fn(): array => Maps::test($request->string('service'), $request->string('key')));
    }

    public function find(Request $request): Response
    {
        return $this->withBudget(static fn(): array => Maps::find($request->string('q')));
    }

    /** Outside map services may only keep this page waiting for a few seconds ("maps.lookup_wait"). */
    private function withBudget(callable $fn): Response
    {
        $before = Http::$deadline;
        Http::$deadline = Http::sooner(microtime(true) + max(2, min(20, (int) setting('maps.lookup_wait'))));
        try {
            return Response::json($fn());
        } finally {
            Http::$deadline = $before;
        }
    }
}
