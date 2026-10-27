<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="ap-hub">
    <div class="ap-hub-nav"><?php $this->include('spam::_nav', ['tab' => $tab]); ?></div>
    <div class="ap-hub-body">
        <form method="post" action="<?= e(admin_url('settings/spam')) ?>" class="ap-sections" data-unsaved-warning data-settings-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e(admin_url('spam/rules')) ?>">
            <?php if (form_errors()): ?><div class="alert alert-danger mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>Some rules need another look. The problems are marked below.</div><?php endif; ?>
            <?php $this->include('settings::_sections', ['group' => 'spam', 'sections' => $sections, 'meta' => $meta, 'canEdit' => $canEdit]); ?>
            <?php if ($canEdit): ?><?= Form::saveBar() ?><?php endif; ?>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
