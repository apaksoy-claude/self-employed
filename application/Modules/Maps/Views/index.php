<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $services */
$this->extend('admin::layout');
\AP\Core\View::inject('admin.body_end', '<link rel="stylesheet" href="' . e(asset('lib/leaflet/leaflet.css')) . '"><script src="' . e(asset('lib/leaflet/leaflet.js')) . '"></script><script src="' . e(asset('js/maps-admin.js')) . '?v=' . e(AP_VERSION) . '"></script>');
$dis = $canEdit ? '' : ' disabled';
$tilesFor = [];
foreach ($services as $id => $s) {
    if ($s['kind'] === 'tiles' && ($s['key'] === '' || $s['value'] !== '')) {
        $tilesFor[] = ['id' => $id, 'tiles' => str_replace('{key}', rawurlencode($s['value']), $s['tiles']), 'attr' => $s['attr'], 'on' => $s['on']];
    }
}
$check = static fn(string $k): string => setting($k) ? ' checked' : '';
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('maps')) ?>" class="ap-settings ap-two" data-maps-form data-unsaved-warning novalidate
      data-test-url="<?= e(admin_url('maps/test')) ?>" data-find-url="<?= e(admin_url('maps/find')) ?>"><?= csrf_field() ?>
    <div class="ap-sections">
        <div class="ap-health-bar is-<?= $first ? 'ok' : 'warn' ?>" data-maps-status>
            <span class="ap-health-icon"><i class="fa-solid <?= $first ? 'fa-map-location-dot' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i></span>
            <div class="ap-health-text"><strong><?= $first ? 'Maps work' : 'No map service is ready' ?></strong>
                <span><?= $first ? e($first['label']) . ' shows your maps' . (count(array_filter($services, static fn($s) => $s['ready'])) > 1 ? ', with a back-up if it cannot load.' : '. Switch on a second service as a back-up.') : 'Switch on a service below (CARTO Light and OpenStreetMap need no key).' ?></span></div>
        </div>

        <section class="ap-section" id="map-services">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Map Services</h2></div>
            <div class="ap-section-body pb-3">
                <p class="small text-muted mb-2"><i class="fa-solid fa-grip-vertical me-1"></i>Drag the services into order. The first one that is on and has its key is used; the ones after it take over if it cannot load.</p>
                <ol class="apm-list" data-svc-list>
                    <?php foreach ($services as $id => $s): $fid = 'svc_' . $id; ?>
                        <li class="apm-svc<?= $s['on'] ? ' is-on' : '' ?>" data-svc="<?= e($id) ?>" data-needs-key="<?= $s['key'] !== '' ? '1' : '0' ?>">
                            <div class="apm-svc-row">
                                <span class="apf-grip" draggable="true" data-svc-grip title="Drag to change the order" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                                <span class="apm-place" data-svc-place></span>
                                <span class="apm-icon"><i class="fa-solid <?= e($s['icon']) ?>" aria-hidden="true"></i></span>
                                <div class="apm-text"><strong><?= e($s['label']) ?></strong> <?= $s['key'] === '' ? '<span class="apm-tag is-free">No key</span>' : '<span class="apm-tag">Needs a key</span>' ?>
                                    <small><?= e($s['help']) ?></small></div>
                                <span class="apf-tools"><button type="button" data-svc-move="-1" aria-label="Move <?= e($s['label']) ?> up"<?= $dis ?>><i class="fa-solid fa-arrow-up"></i></button><button type="button" data-svc-move="1" aria-label="Move <?= e($s['label']) ?> down"<?= $dis ?>><i class="fa-solid fa-arrow-down"></i></button></span>
                                <input type="hidden" name="service[<?= e($id) ?>][on]" value="0">
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="service[<?= e($id) ?>][on]" value="1" id="<?= e($fid) ?>_on" data-svc-on<?= $s['on'] ? ' checked' : '' ?><?= $dis ?> aria-label="Use <?= e($s['label']) ?>"></div>
                            </div>
                            <?php if ($s['key'] !== ''): ?>
                                <div class="apm-key">
                                    <label class="form-label small" for="<?= e($fid) ?>_key"><?= $id === 'mapbox' ? 'Access token' : 'Key' ?></label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control ap-code" id="<?= e($fid) ?>_key" name="service[<?= e($id) ?>][key]" value="<?= e($s['value']) ?>" maxlength="300" autocomplete="off" spellcheck="false" data-svc-key<?= $dis ?> placeholder="<?= $id === 'mapbox' ? 'pk.…' : 'Paste the key' ?>">
                                        <button type="button" class="btn btn-outline-secondary" data-svc-test<?= $dis ?>><i class="fa-solid fa-plug-circle-check me-1"></i>Test</button>
                                    </div>
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-1"><span class="apm-result small" data-svc-result aria-live="polite"></span>
                                        <a class="small" href="<?= e($s['signup']) ?>" target="_blank" rel="noopener">Get a key <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a></div>
                                    <div class="form-text">This key is seen by visitors' browsers, as with every map key. Limit it to your website's address in the service's settings.</div>
                                </div>
                            <?php else: ?>
                                <div class="apm-key apm-key-free"><button type="button" class="btn btn-sm btn-link px-0" data-svc-test<?= $dis ?>><i class="fa-solid fa-plug-circle-check me-1"></i>Check it answers</button> <span class="apm-result small" data-svc-result aria-live="polite"></span></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>

        <section class="ap-section" id="map-place">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>The Place on the Map</h2></div>
            <div class="ap-section-body pb-3">
                <label class="form-label" for="mapFind">Find an address</label>
                <div class="input-group"><input type="search" class="form-control" id="mapFind" name="address" value="<?= e((string) setting('maps.address')) ?>" maxlength="300" placeholder="10 Downing Street, London" data-map-find<?= $dis ?>>
                    <button type="button" class="btn btn-outline-primary" data-map-find-go<?= $dis ?>><i class="fa-solid fa-magnifying-glass me-1"></i>Find</button></div>
                <div class="apm-results" data-map-results hidden></div>
                <div class="form-text">Or click the map, or drag the marker, to put it in the right place.</div>
                <div class="apm-map mt-3" data-map-admin data-tiles="<?= e((string) json_encode($tilesFor)) ?>" aria-label="Map: click to set the place"></div>
                <div class="row g-3 mt-1">
                    <div class="col-sm-4"><label class="form-label" for="mapLat">Latitude</label><input type="text" inputmode="decimal" class="form-control" id="mapLat" name="lat" value="<?= e((string) setting('maps.lat')) ?>" data-map-lat<?= $dis ?>></div>
                    <div class="col-sm-4"><label class="form-label" for="mapLng">Longitude</label><input type="text" inputmode="decimal" class="form-control" id="mapLng" name="lng" value="<?= e((string) setting('maps.lng')) ?>" data-map-lng<?= $dis ?>></div>
                    <div class="col-sm-4"><label class="form-label" for="mapZoom">Zoom <span class="text-muted small" data-map-zoom-out><?= (int) setting('maps.zoom') ?></span></label><input type="range" class="form-range mt-2" min="1" max="19" id="mapZoom" name="zoom" value="<?= (int) setting('maps.zoom') ?>" data-map-zoom<?= $dis ?>></div>
                    <div class="col-12"><label class="form-label" for="mapLabel">Name on the marker <span class="text-muted small">(optional)</span></label><input type="text" class="form-control" id="mapLabel" name="label" maxlength="120" value="<?= e((string) setting('maps.label')) ?>" placeholder="<?= e((string) setting('general.site_name')) ?>"<?= $dis ?>>
                        <div class="form-text">Shown when visitors press the marker.</div></div>
                </div>
            </div>
        </section>

        <section class="ap-section">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-palette" aria-hidden="true"></i>Look</h2></div>
            <div class="ap-section-body pb-3"><div class="row g-3">
                <div class="col-sm-6"><label class="form-label" for="mapHeight">Height on pages (pixels)</label><input type="number" class="form-control" id="mapHeight" name="height" min="120" max="1200" value="<?= (int) setting('maps.height') ?>"<?= $dis ?>></div>
                <div class="col-sm-6"><label class="form-label" for="mapWait">Longest an address search or key test may wait (seconds)</label><input type="number" class="form-control" id="mapWait" name="lookup_wait" min="2" max="20" value="<?= (int) setting('maps.lookup_wait') ?>"<?= $dis ?>>
                    <div class="form-text">If a map service is slow, the search gives up after this, so this page never hangs.</div></div>
                <div class="col-12">
                    <?php foreach (['marker' => ['maps.marker', 'Show a marker on the place'], 'scroll_zoom' => ['maps.scroll_zoom', 'Zoom with the mouse wheel', 'Off is calmer: pages scroll past the map, and visitors use the + and - buttons.'], 'directions' => ['maps.directions', 'Show a "Get directions" link under the map']] as $k => $d): ?>
                        <div class="form-check form-switch mb-2"><input type="hidden" name="<?= $k ?>" value="0"><input class="form-check-input" type="checkbox" role="switch" id="map_<?= $k ?>" name="<?= $k ?>" value="1"<?= $check($d[0]) ?><?= $dis ?>>
                            <label class="form-check-label" for="map_<?= $k ?>"><?= e($d[1]) ?></label><?php if (!empty($d[2])): ?><div class="form-text mt-0"><?= e($d[2]) ?></div><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </section>
        <?php if ($canEdit): ?>
            <div class="ap-savebar"><span class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Maps on the website change as soon as you save.</span>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button></div>
        <?php endif; ?>
    </div>
    <aside class="ap-settings-aside">
        <div class="ap-aside-card"><h2><i class="fa-solid fa-map-pin"></i>Putting a Map on a Page</h2>
            <p>Type <code>[map]</code> in a page, or press the map button in the page editor.</p>
            <p class="mt-2">You can change one map: <code>[map zoom="16" height="300"]</code>, or show another place: <code>[map lat="51.50" lng="-0.12" label="Our shop"]</code>.</p>
            <p class="mt-2">In <a href="<?= e(admin_url('footer')) ?>">Content &gt; Footer</a>, the Map block puts a small map in the footer.</p>
        </div>
        <div class="ap-aside-card"><h2><i class="fa-solid fa-shield-halved"></i>Good to Know</h2>
            <ul>
                <li>Free services need no account. Paid services count each map shown; their free allowances are enough for most small websites.</li>
                <li>With Google Maps on, the website's security rules allow Google's map scripts on public pages only.</li>
                <li>The address search uses Google when it has a key, otherwise OpenStreetMap.</li>
            </ul>
        </div>
    </aside>
</form>
<?php $this->endSection(); ?>
