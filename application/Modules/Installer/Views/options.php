<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">Options</h1>
<form method="post" action="<?= e(url('install/options')) ?>" novalidate><?= csrf_field() ?>
    <h2 class="h6 text-uppercase text-muted">Emails from the site (optional)</h2>
    <p class="small text-muted">Used when the site sends emails. Sending itself is set up in a later version; you can leave these blank and fill them in under Settings > Email.</p>
    <div class="row">
        <?= Form::field(['name' => 'from_name', 'label' => 'Sender name', 'value' => $state['email']['from_name'] ?? $siteName, 'col' => 'col-md-6']) ?>
        <?= Form::field(['name' => 'from_address', 'label' => 'Sender email address', 'type' => 'email', 'value' => $state['email']['from_address'] ?? '', 'col' => 'col-md-6']) ?>
    </div>
    <h2 class="h6 text-uppercase text-muted mt-3">Starting content</h2>
    <div class="form-check"><input class="form-check-input" type="radio" name="content" id="c_blank" value="blank" checked><label class="form-check-label" for="c_blank">Blank site</label></div>
    <div class="form-check mb-3"><input class="form-check-input" type="radio" name="content" id="c_sample" value="sample"><label class="form-check-label" for="c_sample">Include sample content <span class="text-muted small">(sample pages arrive with the Pages module in a later version)</span></label></div>
    <h2 class="h6 text-uppercase text-muted mt-3">Optional modules</h2>
    <?php if (!$optional): ?><p class="text-muted small">No optional modules are included in this package.</p><?php endif; ?>
    <?php foreach ($optional as $name => $m): ?>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="modules[]" id="m_<?= e($name) ?>" value="<?= e($name) ?>"<?= in_array($name, $state['modules'] ?? [], true) ? ' checked' : '' ?>>
        <label class="form-check-label" for="m_<?= e($name) ?>"><strong><?= e($m['label']) ?></strong><span class="d-block small text-muted"><?= e($m['description']) ?></span></label></div>
    <?php endforeach; ?>
    <p class="small text-muted mt-3">You can switch modules on and off at any time under System > Modules.</p>
    <a class="btn btn-link" href="<?= e(url('install/admin')) ?>">Back</a><button class="btn btn-primary" type="submit">Review</button>
</form>
<?php $this->endSection(); ?>
