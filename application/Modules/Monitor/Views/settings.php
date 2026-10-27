<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<form method="post" action="<?= e(admin_url('settings/monitor')) ?>" class="ap-sections ap-monitor-settings" data-unsaved-warning data-settings-form novalidate>
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e(admin_url('system/monitoring/settings')) ?>">
    <?php if (form_errors()): ?><div class="alert alert-danger mb-0">Some settings need another look.</div><?php endif; ?>
    <?php $this->include('settings::_sections', ['group' => 'monitor', 'sections' => $sections, 'meta' => $meta, 'canEdit' => $canEdit]); ?>
    <?php if ($canEdit): ?><?= Form::saveBar() ?><?php endif; ?>
</form>
<?php $this->endSection(); ?>
