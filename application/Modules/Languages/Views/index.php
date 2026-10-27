<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$manage = can('languages.manage');
$on = count(array_filter($languages, static fn($l) => (int) $l['enabled']));
$defaultName = '';
foreach ($languages as $l) {
    if ($l['code'] === $default) {
        $defaultName = (string) $l['name'];
    }
}
?>
<?php $this->section('actions'); ?>
<?php if ($manage): ?><a class="btn btn-primary" href="#add-language"><i class="fa-solid fa-plus me-1"></i>Add a Language</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-settings ap-two">
    <div class="ap-sections">
        <section class="ap-section">
            <div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-language" aria-hidden="true"></i>Your Languages</h2>
                <span class="ap-lang-stats"><span><?= count($languages) ?> added</span><span><?= $on ?> on</span><span>Default: <?= e($defaultName) ?></span></span></div>
            <?php if ($manage && count($languages) > 1): ?><p class="small text-muted px-3 pt-2 mb-0"><i class="fa-solid fa-grip-vertical me-1"></i>Drag a language by its handle to change its place in the switcher.</p><?php endif; ?>
            <div class="table-responsive"><table class="table mb-0 align-middle ap-lang-table">
                <thead><tr><?php if ($manage): ?><th style="width:2.2rem"><span class="visually-hidden">Order</span></th><?php endif; ?><th>Language</th><th>Translated</th><th class="text-center">On</th><th class="text-center">Default</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody<?= $manage ? ' data-sortable data-sort-url="' . e(admin_url('languages/reorder')) . '"' : '' ?>>
                <?php foreach ($languages as $l): $p = $progress[$l['code']]; $isDefault = $l['code'] === $default; $lOn = (int) $l['enabled'] === 1; ?>
                    <tr data-sort-id="<?= e($l['code']) ?>" class="<?= $lOn ? '' : 'is-off' ?>">
                        <?php if ($manage): ?><td><button type="button" class="ap-handle-btn" data-sort-handle title="Drag to change the order (or use the arrow keys)" aria-label="Move <?= e($l['name']) ?>"><i class="fa-solid fa-grip-vertical"></i></button></td><?php endif; ?>
                        <td><div class="d-flex align-items-center gap-2"><span class="ap-lang-code"><?= e(strtoupper($l['code'])) ?></span>
                            <div><div class="fw-semibold"><?= e($l['name']) ?><?= $l['direction'] === 'rtl' ? ' ' . Ui::badge('right to left', 'light') : '' ?></div><div class="small text-muted" lang="<?= e($l['code']) ?>"><?= e($l['native_name']) ?></div></div></div></td>
                        <td style="min-width:120px"><div class="progress" style="height:7px" role="progressbar" aria-label="Translated" aria-valuenow="<?= (int) $p['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $p['percent'] >= 100 ? 'bg-success' : '' ?>" style="width:<?= (int) $p['percent'] ?>%"></div></div>
                            <div class="small text-muted mt-1"><?= (int) $p['percent'] ?>% · <?= (int) $p['done'] ?> of <?= (int) $p['total'] ?><?= ($n = (int) ($edited[$l['code']] ?? 0)) ? ' · ' . $n . ' changed by you' : '' ?></div></td>
                        <td class="text-center">
                            <?php if ($manage && !$isDefault): ?>
                                <form method="post" action="<?= e(admin_url('languages/' . $l['code'] . '/toggle')) ?>"><?= csrf_field() ?>
                                    <div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" data-autosubmit<?= $lOn ? ' checked' : '' ?> aria-label="<?= e($l['name']) ?> on for visitors"></div></form>
                            <?php else: ?>
                                <div class="form-check form-switch d-inline-block m-0" title="<?= $isDefault ? 'The default language is always on' : '' ?>"><input class="form-check-input" type="checkbox" role="switch" disabled<?= $lOn ? ' checked' : '' ?> aria-label="<?= e($l['name']) ?> on for visitors"></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($isDefault): ?><span class="ap-lang-star is-on" title="The default language"><i class="fa-solid fa-star" aria-hidden="true"></i><span class="visually-hidden">Default</span></span>
                            <?php elseif ($manage): ?><form method="post" action="<?= e(admin_url('languages/' . $l['code'] . '/default')) ?>" data-confirm="Make <?= e($l['name']) ?> the default language? Visitors who did not choose a language get it."><?= csrf_field() ?>
                                <button class="ap-lang-star" title="Make it the default" aria-label="Make <?= e($l['name']) ?> the default"><i class="fa-regular fa-star"></i></button></form>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if (can('languages.translate')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('languages/' . $l['code'] . '/translations')) ?>" title="Translate <?= e($l['name']) ?>" aria-label="Translate <?= e($l['name']) ?>"><i class="fa-solid fa-pen"></i><span class="d-none d-xxl-inline ms-1">Translate</span></a><?php endif; ?>
                            <?php if ($manage || can('languages.translate')): ?>
                            <div class="dropdown d-inline-block"><button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More for <?= e($l['name']) ?>"><i class="fa-solid fa-ellipsis"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($manage): ?><li><a class="dropdown-item" href="<?= e(admin_url('languages/' . $l['code'] . '/edit')) ?>"><i class="fa-solid fa-pen-to-square fa-fw me-2 text-muted"></i>Change name or direction</a></li><?php endif; ?>
                                    <?php if (can('languages.translate')): ?><li><a class="dropdown-item" href="<?= e(admin_url('languages/' . $l['code'] . '/export')) ?>"><i class="fa-solid fa-file-export fa-fw me-2 text-muted"></i>Download the wording</a></li>
                                        <li><a class="dropdown-item" href="<?= e(admin_url('languages/' . $l['code'] . '/translations')) ?>#importBox"><i class="fa-solid fa-file-import fa-fw me-2 text-muted"></i>Upload a language pack</a></li><?php endif; ?>
                                    <?php if ($manage && $l['code'] !== 'en' && !$isDefault): ?><li><hr class="dropdown-divider"></li>
                                        <li><form method="post" action="<?= e(admin_url('languages/' . $l['code'] . '/delete')) ?>" data-confirm="Remove <?= e($l['name']) ?> and everything you translated in it?"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="fa-solid fa-trash-can fa-fw me-2"></i>Remove</button></form></li><?php endif; ?>
                                </ul></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody></table></div>
        </section>

        <?php if ($sections && can('settings.view')): ?>
            <form method="post" action="<?= e(admin_url('settings/languages')) ?>" class="ap-sections" id="language-options" data-unsaved-warning novalidate><?= csrf_field() ?>
                <input type="hidden" name="return" value="<?= e(admin_url('languages') . '#language-options') ?>">
                <?php $this->include('settings::_sections', ['group' => 'languages', 'sections' => $sections, 'meta' => ['icon' => 'fa-language'], 'canEdit' => $canEdit]); ?>
                <?php if ($canEdit): ?><?= Form::saveBar() ?><?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <aside class="ap-settings-aside">
        <?php if ($manage): ?>
        <div class="ap-aside-card" id="add-language"><h2><i class="fa-solid fa-plus"></i>Add a Language</h2>
            <p>A new language starts switched off, so visitors do not see it half finished. Translate it (or upload a language pack), then switch it on. Anything not translated shows in English.</p>
            <form method="post" action="<?= e(admin_url('languages')) ?>" class="mt-2"><?= csrf_field() ?><input type="hidden" name="mode" value="catalogue">
                <label class="form-label small" for="addLang">Language</label>
                <select class="form-select mb-2<?= form_errors('catalogue') ? ' is-invalid' : '' ?>" id="addLang" name="catalogue" required><option value="">Choose a language</option>
                    <?php foreach ($available as $code => $name): ?><option value="<?= e((string) $code) ?>"><?= e($name) ?></option><?php endforeach; ?></select>
                <?php foreach ((array) form_errors('catalogue') as $er): ?><div class="invalid-feedback d-block mb-2"><?= e($er) ?></div><?php endforeach; ?>
                <button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Add</button>
            </form>
            <button class="btn btn-link btn-sm px-0 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#customLang" aria-expanded="<?= form_errors('code') || form_errors('name') ? 'true' : 'false' ?>" aria-controls="customLang">Not in the list?</button>
            <div class="collapse<?= form_errors('code') || form_errors('name') ? ' show' : '' ?>" id="customLang">
                <form method="post" action="<?= e(admin_url('languages')) ?>" class="mt-2"><?= csrf_field() ?><input type="hidden" name="mode" value="custom">
                    <div class="row g-2"><?= Form::field(['name' => 'code', 'label' => 'Code', 'placeholder' => 'cy', 'col' => 'col-4']) ?><?= Form::field(['name' => 'name', 'label' => 'English name', 'placeholder' => 'Welsh', 'col' => 'col-8']) ?></div>
                    <div class="row g-2"><?= Form::field(['name' => 'native_name', 'label' => 'Its own name', 'placeholder' => 'Cymraeg', 'col' => 'col-7']) ?><?= Form::field(['name' => 'direction', 'label' => 'Writing', 'type' => 'select', 'options' => ['ltr' => 'Left to right', 'rtl' => 'Right to left'], 'col' => 'col-5']) ?></div>
                    <button class="btn btn-outline-primary w-100">Add This Language</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <div class="ap-aside-card"><h2><i class="fa-solid fa-circle-info"></i>Good to Know</h2>
            <ul>
                <li>The star marks the default language: visitors who did not choose one get it.</li>
                <li>Visitors switch with the language button next to the menu or in the footer.</li>
                <li>In Content > Footer you can also add a "Language choice" block.</li>
                <?php if (can('settings.view')): ?><li>The first day of the week is in <a href="<?= e(admin_url('settings/localisation')) ?>">Language and Region</a>.</li><?php endif; ?>
            </ul>
        </div>
    </aside>
</div>
<?php $this->endSection(); ?>
