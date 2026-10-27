<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<button type="button" class="btn btn-sm btn-outline-secondary" data-arrange-toggle data-arrange-group="<?= e($group) ?>" data-arrange-url="<?= e(admin_url('settings/layout')) ?>" title="Drag cards and settings to where you want them, and make them half or full width"><i class="fa-solid fa-up-down-left-right me-1"></i><span>Arrange</span></button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-arrange-reset data-arrange-reset-url="<?= e(admin_url('settings/layout/reset')) ?>" hidden><i class="fa-solid fa-rotate-left me-1"></i>Reset Layout</button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-cards="open"><i class="fa-solid fa-angles-down me-1"></i><?= e(__('core.open_all')) ?></button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-cards="close"><i class="fa-solid fa-angles-up me-1"></i><?= e(__('core.collapse_all')) ?></button>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-settings">

    <form method="post" action="<?= e(admin_url('settings/' . $group)) ?>" class="ap-sections" data-unsaved-warning data-settings-form novalidate>
        <?= csrf_field() ?>
        <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true">Save</button>
        <?php if ($errors = form_errors()): ?>
            <div class="alert alert-danger mb-0" role="alert"><i class="fa-solid fa-circle-exclamation me-2"></i>Some settings need another look. The problems are marked below.</div>
        <?php endif; ?>
        <?php $this->slot('settings.top.' . $group); ?>
        <?php if (!empty($meta['columns'])): ?><div class="ap-cards-cols"><?php endif; ?>
        <?php $this->include('settings::_sections', ['group' => $group, 'sections' => $sections, 'meta' => $meta, 'canEdit' => $canEdit]); ?>
        <?php if (!empty($meta['columns'])): ?></div><?php endif; ?>
        <?php if ($canEdit): ?><?= Form::saveBar() ?><?php else: ?>
            <div class="alert alert-info mb-0"><i class="fa-solid fa-lock me-2"></i>You can look at these settings but not change them. Ask an administrator if something needs changing.</div>
        <?php endif; ?>
    </form>

    <aside class="ap-settings-aside">
        <?php $this->slot('settings.aside.' . $group); ?>
        <?php if ($meta['tips']): ?>
            <div class="ap-aside-card"><h2><i class="fa-regular fa-lightbulb"></i>Tips</h2><ul>
                <?php foreach ((array) $meta['tips'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <div class="ap-aside-card"><h2><i class="fa-solid fa-shield-halved"></i>About these settings</h2>
            <p>Keys and passwords are stored encrypted and are never shown again. To change one, type the new value. Every change is recorded in the <a href="<?= e(admin_url('settings/history')) ?>">change history</a>.</p>
        </div>
    </aside>
</div>
<?php $this->endSection(); ?>
