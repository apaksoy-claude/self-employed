<?php
declare(strict_types=1);

use AP\Modules\Maps\Maps;

return [
    'name' => 'maps',
    'label' => 'Maps',
    'version' => '1.4.7',
    'description' => 'Maps on your pages and in the footer, from the map services you choose, with a back-up service when one cannot load.',
    'icon' => 'fa-map-location-dot',
    'category' => 'Content',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'Maps disappear from pages and the footer ([map] is left out). Your settings are kept.',
    'menu' => [
        ['label' => 'Maps', 'icon' => 'fa-map-location-dot', 'url' => 'maps', 'hub' => 'settings', 'category' => 'Site', 'group' => 'Settings', 'order' => 8.5, 'permission' => 'settings.view',
            'info' => 'Which map services to use and in what order, their keys, the place the maps show and how they look.',
            'keywords' => 'map maps google maps openstreetmap mapbox maptiler leaflet location address directions api key'],
    ],
    'setting_groups' => [
        'maps' => ['label' => 'Maps', 'icon' => 'fa-map-location-dot', 'hub' => 'maps', 'category' => 'Site', 'order' => 8.5, 'url' => 'maps',
            'description' => 'Map services, keys, the place on the map and the look.', 'keywords' => 'map google mapbox openstreetmap'],
    ],
    'settings' => [
        'maps.services' => ['group' => 'maps', 'label' => 'Map services and their order', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.google_key' => ['group' => 'maps', 'label' => 'Google Maps key', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.mapbox_token' => ['group' => 'maps', 'label' => 'Mapbox access token', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.maptiler_key' => ['group' => 'maps', 'label' => 'MapTiler key', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.lat' => ['group' => 'maps', 'label' => 'Latitude', 'type' => 'text', 'default' => '51.5074', 'hidden' => true],
        'maps.lng' => ['group' => 'maps', 'label' => 'Longitude', 'type' => 'text', 'default' => '-0.1278', 'hidden' => true],
        'maps.zoom' => ['group' => 'maps', 'label' => 'Zoom', 'type' => 'number', 'default' => 14, 'hidden' => true],
        'maps.label' => ['group' => 'maps', 'label' => 'Name on the marker', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.address' => ['group' => 'maps', 'label' => 'Address searched', 'type' => 'text', 'default' => '', 'hidden' => true],
        'maps.height' => ['group' => 'maps', 'label' => 'Height', 'type' => 'number', 'default' => 360, 'hidden' => true],
        'maps.marker' => ['group' => 'maps', 'label' => 'Show a marker', 'type' => 'checkbox', 'default' => true, 'hidden' => true],
        'maps.scroll_zoom' => ['group' => 'maps', 'label' => 'Zoom with the mouse wheel', 'type' => 'checkbox', 'default' => false, 'hidden' => true],
        'maps.lookup_wait' => ['group' => 'maps', 'label' => 'Longest an address search or key test may wait (seconds)', 'type' => 'number', 'default' => 6, 'hidden' => true],
        'maps.directions' => ['group' => 'maps', 'label' => 'Show "Get directions"', 'type' => 'checkbox', 'default' => true, 'hidden' => true],
    ],
    'boot' => static function (): void {
        Maps::boot();
    },
    'actions' => [
        'maps.key' => ['label' => 'Add a Google Maps key', 'icon' => 'fa-key', 'url' => 'maps#map-services', 'permission' => 'settings.view', 'keywords' => 'google maps api key mapbox token maptiler', 'info' => 'Maps'],
        'maps.wait' => ['label' => 'Map address search time limit', 'icon' => 'fa-gauge-high', 'url' => 'maps#mapWait', 'permission' => 'settings.view', 'keywords' => 'slow map address search timeout wait speed', 'info' => 'Maps'],
        'maps.place' => ['label' => 'Change the place on the map', 'icon' => 'fa-location-crosshairs', 'url' => 'maps#map-place', 'permission' => 'settings.view', 'keywords' => 'map address location position latitude longitude', 'info' => 'Maps'],
    ],
];
