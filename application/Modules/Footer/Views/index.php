<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $layout @var array $blocks */
use AP\Modules\Footer\Footer;

$this->extend('admin::layout');
$json = static fn($v): string => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/footer.js')) . '?v=' . e(AP_VERSION) . '"></script>');
$s = $layout['style'];
$b = $layout['bottom'];
$opts = static function (array $options, string $current): string {
    $h = '';
    foreach ($options as $k => $l) {
        $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $current ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h;
};
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Site</a>
<form method="post" action="<?= e(admin_url('footer/reset')) ?>" data-confirm="Put the footer back to the starting layout? Your rows, columns and blocks are replaced."><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Start Again</button></form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('footer')) ?>" data-footer-form data-unsaved-warning data-preview-url="<?= e(admin_url('footer/preview')) ?>"
      data-max-rows="<?= Footer::MAX_ROWS ?>" data-max-cols="<?= Footer::MAX_COLS ?>" data-max-blocks="<?= Footer::MAX_BLOCKS ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="layout" value="" data-footer-layout>

    <div class="apf-top">
        <label class="apf-switch"><input type="hidden" name="enabled" value="0"><span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="footerOn"<?= $enabled ? ' checked' : '' ?>></span>
            <span><strong>Use this footer on the website</strong><small>Off: the simple footer shows (footer menu, social icons and copyright).</small></span></label>
        <span class="apf-top-tip"><i class="fa-solid fa-hand-pointer"></i>Drag blocks from the right into a column. Drag <i class="fa-solid fa-grip-vertical mx-1"></i> to move rows, columns and blocks.</span>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <section class="ap-section mb-3">
                <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-table-columns" aria-hidden="true"></i>Rows and Columns</h2>
                    <span class="small text-muted">Each row is 12 parts wide.</span></div>
                <div class="ap-section-body pb-3">
                    <div class="apf-rows" data-rows aria-label="Footer rows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-add-row><i class="fa-solid fa-plus me-1"></i>Add a Row</button>
                </div>
            </section>

            <section class="ap-section mb-3" id="footer-bottom">
                <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-grip-lines" aria-hidden="true"></i>Bottom Bar</h2>
                    <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="fbShow" data-b="show"<?= $b['show'] ? ' checked' : '' ?>><label class="form-check-label small" for="fbShow">Show</label></span></div>
                <div class="ap-section-body pb-3" data-when-bottom>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label" for="fbCopy">Copyright line</label>
                            <input type="text" class="form-control" id="fbCopy" maxlength="300" data-b="copyright" value="<?= e($b['copyright']) ?>">
                            <div class="form-text">{year} becomes <?= e(date('Y')) ?> and {site} the site name.</div></div>
                        <div class="col-md-4"><label class="form-label" for="fbAlign">Layout</label>
                            <select class="form-select" id="fbAlign" data-b="align"><?= $opts(['between' => 'Copyright left, links right', 'center' => 'Everything in the centre'], $b['align']) ?></select></div>
                        <div class="col-12"><div class="form-label">Links in the bottom bar</div>
                            <div data-bottom-links></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="fbExtra" data-b="extra"<?= $b['extra'] ? ' checked' : '' ?>>
                            <label class="form-check-label" for="fbExtra">Links other parts add</label><div class="form-text mt-0">Such as "Cookie settings" and "Get notifications".</div></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="fbTop" data-b="top"<?= $b['top'] ? ' checked' : '' ?>>
                            <label class="form-check-label" for="fbTop">"Back to top" link</label></div></div>
                        <div class="col-12"><label class="form-label" for="fbSmall">Small print <span class="text-muted small">(optional)</span></label>
                            <textarea class="form-control" id="fbSmall" rows="2" maxlength="2000" data-b="small_print" placeholder="Company number 01234567. Registered office: 1 High Street, London."><?= e($b['small_print']) ?></textarea>
                            <div class="form-text">A line in smaller letters under the bottom bar, for example company details.</div></div>
                    </div>
                </div>
            </section>

            <section class="ap-section mb-3">
                <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-eye" aria-hidden="true"></i>Preview</h2>
                    <span class="btn-group btn-group-sm" role="group" aria-label="Preview size">
                        <button type="button" class="btn btn-outline-secondary active" data-preview-size="desktop" aria-pressed="true"><i class="fa-solid fa-desktop me-1"></i>Computer</button>
                        <button type="button" class="btn btn-outline-secondary" data-preview-size="phone" aria-pressed="false"><i class="fa-solid fa-mobile-screen me-1"></i>Phone</button>
                    </span></div>
                <div class="ap-section-body pb-3"><div class="apf-preview" data-preview-wrap><iframe title="Footer preview" data-preview loading="lazy"></iframe><span class="apf-preview-wait" data-preview-wait>Updating…</span></div>
                    <div class="form-text">The preview changes as you work. Links do not open here.</div></div>
            </section>

            <div class="ap-savebar">
                <span class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Visitors see the footer as soon as you save.</span>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save Footer</button>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="apf-side">
                <section class="ap-section mb-3">
                    <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-cubes" aria-hidden="true"></i>Blocks</h2></div>
                    <div class="ap-section-body pb-3">
                        <p class="small text-muted mb-2">Drag a block into a column, or press it to add it to the column you last used.</p>
                        <div class="apf-palette" data-palette>
                            <?php foreach ($blocks as $type => $def): ?>
                                <button type="button" class="apf-pal" draggable="true" data-pal="<?= e((string) $type) ?>" title="<?= e($def['help']) ?>">
                                    <i class="fa-solid <?= e($def['icon']) ?>" aria-hidden="true"></i><span><strong><?= e($def['label']) ?></strong><small><?= e($def['help']) ?></small></span></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <section class="ap-section mb-3">
                    <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-palette" aria-hidden="true"></i>Look</h2></div>
                    <div class="ap-section-body pb-3">
                        <div class="form-label">Colours</div>
                        <div class="apf-themes" role="radiogroup" aria-label="Colours">
                            <?php foreach (Footer::THEMES as $k => $l): ?>
                                <label class="apf-theme is-<?= e($k) ?><?= $s['theme'] === $k ? ' is-checked' : '' ?>"><input type="radio" name="apf_theme" value="<?= e($k) ?>" data-s="theme"<?= $s['theme'] === $k ? ' checked' : '' ?>><span class="apf-swatch"></span><span><?= e($l) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2 mt-1" data-custom-colours<?= $s['theme'] === 'custom' ? '' : ' hidden' ?>>
                            <?php foreach (['bg' => 'Background', 'text' => 'Text', 'accent' => 'Links on hover'] as $k => $l): ?>
                                <div class="col-4"><label class="form-label small" for="fc_<?= $k ?>"><?= e($l) ?></label><input type="color" class="form-control form-control-color w-100" id="fc_<?= $k ?>" data-s="<?= $k ?>" value="<?= e($s[$k]) ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-6"><label class="form-label small" for="fsWidth">Width</label><select class="form-select form-select-sm" id="fsWidth" data-s="width"><?= $opts(Footer::WIDTHS, $s['width']) ?></select></div>
                            <div class="col-6"><label class="form-label small" for="fsSpace">Space</label><select class="form-select form-select-sm" id="fsSpace" data-s="spacing"><?= $opts(Footer::SPACING, $s['spacing']) ?></select></div>
                        </div>
                        <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="fsFold" data-s="fold"<?= $s['fold'] ? ' checked' : '' ?>>
                            <label class="form-check-label" for="fsFold">Fold blocks with a heading on phones</label><div class="form-text mt-0">Visitors tap the heading to open it, so the footer stays short.</div></div>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" id="fsBorder" data-s="border"<?= $s['border'] ? ' checked' : '' ?>>
                            <label class="form-check-label" for="fsBorder">Line above the footer</label></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="footerData"><?= $json(['layout' => $layout, 'blocks' => $blocks, 'phone' => Footer::PHONE, 'align' => Footer::ALIGN]) ?></script>
<?php $this->endSection(); ?>
